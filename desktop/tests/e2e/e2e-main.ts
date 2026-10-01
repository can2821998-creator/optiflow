/**
 * END-TO-END SMOKE TEST — runs the REAL DesktopApp (same main/preload/shell code as the
 * product) inside Electron, against a disposable OptiFlow test server, with a synthetic
 * Medula served from local fixtures for https://gss.sgk.gov.tr (intercepted ONLY inside
 * this test's Medula session; nothing like this exists in the product build).
 *
 *   node scripts/build.mjs && OPTIFLOW_TEST_URL=http://127.0.0.1:8081 node tests/e2e/build-e2e.mjs
 *   E2E_EMAIL=<store e-mail> E2E_USER=<staff user> xvfb-run -a electron dist-e2e/e2e-main.js
 * Optional: E2E_SCREENSHOT=/path/prefix writes toolbar/view screenshots.
 */
import { app, BrowserWindow, type WebContents } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import { DesktopApp } from '../../src/main/app-controller';
import { loadConfig } from '../../src/main/config';
import { initLogging } from '../../src/main/logging';
import { installGlobalGuards } from '../../src/main/navigation';
import { hardenAppEarly } from '../../src/main/security';
import { applyHardRefreshHeaders, clearMedulaSession, restartMedulaBrowser, setupSessions } from '../../src/main/sessions';
import http from 'node:http';
import { session as eSession } from 'electron';
import { MSG } from '../../src/shared/messages';

const ROOT = path.resolve(__dirname, '..');
const FIX = path.join(ROOT, 'tests', 'fixtures');
const SITE = path.join(ROOT, 'tests', 'e2e', 'medula-site');
const ROUTES: Record<string, string> = {
  '/': path.join(FIX, 'giris.html'),
  '/Optik_Firma2_Web/login.faces': path.join(FIX, 'giris.html'),
  '/Optik_Firma2_Web/index.faces': path.join(FIX, 'liste.html'),
  '/Optik/Liste.aspx': path.join(FIX, 'liste.html'),
  '/Optik/Cerceveli.aspx': path.join(SITE, 'cerceveli.html'),
  '/Optik/ReceteDetay.aspx': path.join(FIX, 'recete-uzak.html'),
  '/menu.html': path.join(FIX, 'menu-cercevesi.html'),
};

const results: Array<{ name: string; ok: boolean; info?: string }> = [];
const check = (name: string, ok: boolean, info?: unknown) => {
  results.push({ name, ok, info: info === undefined ? undefined : String(info).slice(0, 300) });
  console.log(`${ok ? '  ✓' : '  ✗'} ${name}${ok || info === undefined ? '' : '  → ' + String(info).slice(0, 300)}`);
};
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
async function waitFor(fn: () => boolean | Promise<boolean>, ms = 15000, step = 150): Promise<boolean> {
  const end = Date.now() + ms;
  while (Date.now() < end) {
    try {
      if (await fn()) return true;
    } catch {
      /* retry */
    }
    await sleep(step);
  }
  return false;
}
const js = <T = unknown>(wc: WebContents, code: string) => wc.executeJavaScript(code) as Promise<T>;
async function loadAndWait(wc: WebContents, url: string) {
  await wc.loadURL(url).catch(() => undefined);
  await waitFor(() => !wc.isLoading());
}
async function submitForm(wc: WebContents, fields: Record<string, string>) {
  const done = new Promise<void>((r) => wc.once('did-finish-load', () => r()));
  await js(
    wc,
    `(() => { const f = document.querySelector('form'); ${Object.entries(fields)
      .map(([k, v]) => `f.querySelector('[name="${k}"]').value = ${JSON.stringify(v)};`)
      .join('')} f.submit(); })()`,
  );
  await done;
  await waitFor(() => !wc.isLoading());
}

hardenAppEarly();
app.whenReady().then(async () => {
  const cfg = loadConfig();
  initLogging('info');
  installGlobalGuards();
  const sessions = setupSessions('5.0.0-e2e', new URL(cfg.optiflowBaseUrl).origin, cfg.medulaAllowedHosts);
  const medulaHeaders: Array<string | null> = [];
  sessions.medula.protocol.handle('https', async (req) => {
    const u = new URL(req.url);
    medulaHeaders.push(req.headers.get('cache-control'));
    // Real SGK behaviour after the Optik login: 302 to the SAME host over plain http.
    if (u.hostname === 'gss.sgk.gov.tr' && u.pathname === '/Optik_Firma2_Web/girisYap') {
      return new Response('', { status: 302, headers: { location: 'http://gss.sgk.gov.tr/Optik_Firma2_Web/index.faces' } });
    }
    const file = u.hostname === 'gss.sgk.gov.tr' ? ROUTES[u.pathname] : undefined;
    if (!file) return new Response('not found', { status: 404 });
    return new Response(fs.readFileSync(file), { headers: { 'content-type': 'text/html; charset=utf-8' } });
  });

  // 4.12.0: the E2E store gets every merkez feature (list check, offline copy, barcode).
  const merkezSql = (q: string) =>
    process.env.E2E_LITE_SQL === '1'
      ? require('node:child_process').execFileSync('mysql', ['-uof', `-p${process.env.E2E_DB_PW || ''}`, '-N', '-B', process.env.E2E_DB || 'optiflow2', '-e', q], { encoding: 'utf8' }).trim()
      : '';
  merkezSql(`UPDATE magazalar SET ozellikler='["sgk_mutabakat","cevrimdisi","barkod"]' WHERE email='${process.env.E2E_EMAIL}'`);
  const tenantDb = merkezSql(`SELECT db_name FROM magazalar WHERE email='${process.env.E2E_EMAIL}'`);
  const tenantSql = (q: string) =>
    tenantDb ? require('node:child_process').execFileSync('mysql', ['-uof', `-p${process.env.E2E_DB_PW || ''}`, '-N', '-B', tenantDb, '-e', q], { encoding: 'utf8' }).trim() : '';

  const d = new DesktopApp(cfg, sessions, path.join(ROOT, 'dist'));
  d.create();
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const A = d as any;
  const owc: WebContents = A.optiflowView.webContents;
  const BASE = cfg.optiflowBaseUrl;
  const login = async () => {
    await loadAndWait(owc, `${BASE}/magaza-giris.php`);
    await submitForm(owc, { email: process.env.E2E_EMAIL!, password: process.env.E2E_STORE_PW || 'Magaza1234' });
    await submitForm(owc, { username: process.env.E2E_USER!, password: process.env.E2E_USER_PW || 'Personel123' });
  };

  try {
    console.log('OptiFlow view');
    check('splash shown until the first page load', A.state.optiflow.everLoaded !== true && !A.optiflowView.getVisible());
    check('OptiFlow loads', await waitFor(() => A.state.optiflow.loaded === true, 20000), JSON.stringify(A.state.optiflow));
    check('desktop opens straight into the store login (no marketing page)', /magaza-giris\.php$/.test(owc.getURL()) && (await js<boolean>(owc, "!!document.querySelector('.pro-baslik')")), owc.getURL());
    check('window uses the toolbar as title bar (no classic menu bar)', A.win.getMenu?.() == null || A.win.isMenuBarVisible() === false);
    check('UA marker reaches the server on navigations', /OptiFlowDesktop\//.test(owc.getUserAgent()) && /OptiFlowDesktop\//.test(await js<string>(owc, 'navigator.userAgent')));
    await login();
    check('normal OptiFlow login works inside the shell', /index\.php/.test(owc.getURL()) && (await js<string>(owc, 'document.title')).startsWith('Siparişler'), owc.getURL());

    const api = await js<string[]>(owc, 'window.optiflowDesktop ? Object.keys(window.optiflowDesktop).sort() : null');
    check('narrow API exposed on OptiFlow origin only', JSON.stringify(api) === JSON.stringify(['aktar', 'aktarimDinle', 'durum', 'medulaAc', 'surum']), JSON.stringify(api));
    check('no Node in OptiFlow page', (await js<string>(owc, 'typeof require + typeof process + typeof module + typeof Buffer')) === 'undefined'.repeat(4));
    check('no ipcRenderer reachable', (await js<string>(owc, 'typeof window.ipcRenderer + typeof window.electron')) === 'undefined'.repeat(2));

    await loadAndWait(owc, `${BASE}/sgk-aktar.php`);
    check(
      'SGK page shows desktop card, not extension setup',
      await js<boolean>(owc, "!!document.querySelector('[data-masaustu-kart]') && !document.body.innerText.includes('Köprü kurulumu')"),
      await js<string>(owc, 'document.title'),
    );
    check('no service worker controls the desktop page', await js<boolean>(owc, '!navigator.serviceWorker || !navigator.serviceWorker.controller'));
    check('no PWA install bar in desktop', await js<boolean>(owc, "!document.querySelector('.pwa-bar')"));
    await waitFor(() => !!A.state.account);
    check('toolbar knows the account (store + user + Pro package)', !!A.state.account && A.state.account.supportMode === false && A.state.account.package === 'pro' && A.state.account.transferAllowed === true, JSON.stringify(A.state.account));

    console.log('Navigation / popup security');
    await js(owc, "location.href = 'file:///etc/passwd'").catch(() => undefined);
    await sleep(600);
    check('OptiFlow view cannot navigate to file://', owc.getURL().startsWith(BASE), owc.getURL());
    const created = new Promise<BrowserWindow>((r) => app.once('browser-window-created', (_e, w) => r(w)));
    await js(owc, "void window.open('sgk-aktar.php', '_blank')");
    const popup = await Promise.race([created, sleep(8000).then(() => null)]);
    check('same-origin popup (print pages) opens', !!popup);
    if (popup) {
      await waitFor(() => !popup.webContents.isLoading() && popup.webContents.getURL().startsWith(BASE));
      check('popup gets NO privileged API', (await js<string>(popup.webContents, 'typeof window.optiflowDesktop')) === 'undefined');
      check('popup page still rendered as desktop (no extension instructions)', await js<boolean>(popup.webContents, "!document.body.innerText.includes('Köprü kurulumu')"));
      popup.close();
      await sleep(300);
    }
    await js(owc, "void window.open('javascript:alert(1)')");
    await sleep(400);
    check('javascript: popup is refused', BrowserWindow.getAllWindows().length === 1);

    console.log('Medula view');
    A.command('goster-medula');
    const mwcReady = await waitFor(() => !!A.medulaView && !A.medulaView.webContents.isLoading() && A.medulaView.webContents.getURL().startsWith('https://gss.sgk.gov.tr/'));
    check('Medula opens inside the app, on the configured Optik login page', mwcReady && A.medulaView.webContents.getURL() === cfg.medulaHomeUrl, A.medulaView?.webContents.getURL());
    const mwc: WebContents = A.medulaView.webContents;
    check('Medula UA has no Electron/OptiFlow marker', !/Electron|OptiFlow/i.test(await js<string>(mwc, 'navigator.userAgent')) && !/Electron|OptiFlow/i.test(mwc.getUserAgent()));
    check('Medula page has no Node, no OptiFlow API', (await js<string>(mwc, 'typeof require + typeof process + typeof window.optiflowDesktop + typeof window.optiflowShell')) === 'undefined'.repeat(4));
    check('Medula page is untouched (no injected button)', await js<boolean>(mwc, "!document.getElementById('optiflow-kopru-dugme')"));

    await A.runTransfer();
    check('login screen → "Medula oturumu sona ermiş", nothing sent', A.state.transfer.phase === 'hata' && A.state.transfer.message === MSG.medulaOturumBitti, JSON.stringify(A.state.transfer));

    await loadAndWait(mwc, 'https://gss.sgk.gov.tr/Optik/Liste.aspx');
    await A.runTransfer();
    check('list page → "Reçete ekranı bulunamadı" (no guessing)', A.state.transfer.message === MSG.receteYok, A.state.transfer.message);

    if (tenantDb) {
      console.log('Medula list check (4.12.0)');
      await waitFor(() => A.state.account?.features?.sgk_mutabakat === true, 5000);
      let sent: string[] | null = null;
      const origCheck = A.api.checkList.bind(A.api);
      A.api.checkList = async (n: string[], c: string, acc: unknown) => {
        sent = n;
        return origCheck(n, c, acc);
      };
      await A.runListCheck();
      A.api.checkList = origCheck;
      check('only prescription numbers leave the Medula frame', JSON.stringify(sent) === JSON.stringify(['1A2B3C', '9Z8Y7X']), JSON.stringify(sent));
      check('toolbar reports how many are missing', /Listede 2 reçete: \d tanesi|Listedeki 2 reçetenin/.test(A.state.notice?.text ?? ''), A.state.notice?.text);
      const acildi = await waitFor(() => /sgk-mutabakat\.php\?kontrol=1/.test(owc.getURL()) && !owc.isLoading(), 10000);
      check('OptiFlow opens the reconciliation with the missing numbers', acildi && (await js<string>(owc, 'document.body.innerText')).includes('9Z8Y7X'), owc.getURL());
      await loadAndWait(owc, `${BASE}/sgk-aktar.php`); // back where the transfer tests expect it
      A.command('goster-medula');
    }

    await js(mwc, "location.href = 'https://evil.example/phish'").catch(() => undefined);
    await sleep(700);
    check('Medula cannot navigate to a non-SGK host', mwc.getURL().startsWith('https://gss.sgk.gov.tr/'), mwc.getURL());

    await loadAndWait(mwc, 'https://gss.sgk.gov.tr/Optik/Cerceveli.aspx');
    await waitFor(() => mwc.mainFrame.framesInSubtree.length >= 3 && mwc.mainFrame.framesInSubtree.every((f) => f.url !== ''));
    await sleep(1200);
    check('probe finds the prescription in a CHILD frame', A.state.medula.probe?.detectedPrescription === true, JSON.stringify(A.state.medula.probe));

    console.log('Transfer');
    const stored = await A.runTransfer();
    check('transfer succeeds', stored.phase === 'basarili' && stored.incomingId > 0, JSON.stringify(stored));
    const opened = await waitFor(() => /sgk-aktar\.php\?gelen=\d+/.test(owc.getURL()) && !owc.isLoading(), 10000);
    if (process.env.E2E_SCREENSHOT) fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-preview.png`, (await owc.capturePage()).toPNG());
    check('OptiFlow opens the received prescription automatically (from SGK page)', opened, owc.getURL());
    const body = await js<string>(owc, 'document.body.innerText');
    check(
      'existing review screen shows parsed values',
      body.includes('Çözümlenen reçete') && (await js<string>(owc, "document.querySelector('input[name=sag_sph]').value")) === '-1.50',
      body.slice(0, 200),
    );
    check('desktop view kept after automatic navigation (UA marker on every request)', await js<boolean>(owc, "!!document.querySelector('[data-masaustu-kart]')"));
    check('values are NOT written to an order without confirmation', await js<boolean>(owc, "!!document.querySelector('input[name=eylem][value=uygula]')"));

    console.log('Transfer from the OptiFlow page button (preload API)');
    await loadAndWait(owc, `${BASE}/sgk-aktar.php`);
    const viaPage = await js<{ asama: string; gelenId?: number }>(owc, 'window.optiflowDesktop.aktar()');
    check('OptiFlow page button triggers the same trusted flow', viaPage.asama === 'basarili' && (viaPage.gelenId ?? 0) > 0, JSON.stringify(viaPage));
    check('page never receives patient summary through the API', !('ozet' in (viaPage as object)) && !('summary' in (viaPage as object)));
    await waitFor(() => !owc.isLoading());
    if (process.env.E2E_SCREENSHOT) {
      A.setLayout('bolunmus');
      await sleep(1500);
      fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-shell.png`, (await A.win.webContents.capturePage()).toPNG());
      fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-optiflow.png`, (await owc.capturePage()).toPNG());
      fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-medula.png`, (await mwc.capturePage()).toPNG());
      A.setLayout('sekme');
    }

    console.log('Session expiry + retry');
    await sessions.optiflow.clearStorageData({ storages: ['cookies'] });
    await A.runTransfer();
    check(
      'expired OptiFlow session → Turkish message, retry kept in memory',
      A.state.transfer.message === MSG.oturumDoldu && A.state.transfer.canRetry === true,
      JSON.stringify(A.state.transfer),
    );
    await waitFor(() => !owc.isLoading());
    await login();
    await A.retryTransfer();
    check('retry after re-login succeeds without re-reading Medula', A.state.transfer.phase === 'basarili', JSON.stringify(A.state.transfer));
    check('retry payload discarded after success', A.retry.has() === false);

    console.log('Medula login robustness (browser restart + hard refresh)');
    {
      // The fake Medula above bypasses the network stack, so verify the header hook on a REAL server.
      const seen: Array<string | undefined> = [];
      const srv = http.createServer((req, res) => {
        seen.push(req.headers['cache-control'] as string | undefined);
        res.setHeader('Cache-Control', 'max-age=3600');
        res.end('<p>ok</p>');
      });
      await new Promise<void>((r) => srv.listen(0, '127.0.0.1', () => r()));
      const port = (srv.address() as { port: number }).port;
      const ts = eSession.fromPartition('e2e-hardrefresh');
      applyHardRefreshHeaders(ts, [`http://127.0.0.1:${port}/*`]);
      const w = new BrowserWindow({ show: false, webPreferences: { session: ts, sandbox: true } });
      await w.loadURL(`http://127.0.0.1:${port}/giris`);
      await w.loadURL(`http://127.0.0.1:${port}/giris`); // second load would come from cache without the hook
      w.destroy();
      srv.close();
      check('Medula requests are sent like a hard refresh (both loads hit the server with no-cache)', seen.length >= 2 && seen.every((h) => h === 'no-cache'), JSON.stringify(seen));
    }
    await sessions.medula.cookies.set({ url: 'https://gss.sgk.gov.tr/Optik_Firma2_Web/', name: 'JSESSIONID', value: 'eski-oturum' });
    await sessions.medula.cookies.set({ url: 'https://gss.sgk.gov.tr/', name: 'kalici', value: '1', expirationDate: Math.floor(Date.now() / 1000) + 86400 });
    const removed = await restartMedulaBrowser(sessions.medula);
    const left = (await sessions.medula.cookies.get({})).map((c) => c.name);
    check('restart drops stale session cookies (JSESSIONID) but keeps long-lived ones', removed >= 1 && !left.includes('JSESSIONID') && left.includes('kalici'), JSON.stringify(left));
    {
      const blockedBefore = A.state.notice;
      await loadAndWait(mwc, 'https://gss.sgk.gov.tr/Optik_Firma2_Web/girisYap').catch(() => undefined);
      await waitFor(() => !mwc.isLoading() && mwc.getURL().endsWith('/index.faces'));
      check(
        'SGK login redirect to http:// same host is followed over https (not blocked)',
        mwc.getURL() === 'https://gss.sgk.gov.tr/Optik_Firma2_Web/index.faces' && A.state.notice === blockedBefore,
        `${mwc.getURL()} notice=${JSON.stringify(A.state.notice)}`,
      );
      await loadAndWait(mwc, 'http://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces').catch(() => undefined);
      await waitFor(() => !mwc.isLoading());
      check('typed http:// SGK address is opened as https', mwc.getURL() === 'https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces', mwc.getURL());
    }
    await A.command('medula-sifirla');
    await waitFor(() => !mwc.isLoading() && mwc.getURL() === cfg.medulaHomeUrl);
    check('"Medula\'yı sıfırla" reopens the Optik login page', mwc.getURL() === cfg.medulaHomeUrl, mwc.getURL());

    console.log('Medula session clearing');
    await sessions.medula.cookies.set({ url: 'https://gss.sgk.gov.tr/', name: 'ASP.NET_SessionId', value: 'e2e' });
    await clearMedulaSession(sessions.medula);
    check('clearing removes Medula cookies', (await sessions.medula.cookies.get({})).length === 0);
    check('OptiFlow cookies untouched by Medula clear', (await sessions.optiflow.cookies.get({})).length > 0);

    console.log('Lite store (package switched to Lite on the server)');
    if (process.env.E2E_LITE_SQL === '1') {
      const { execFileSync } = await import('node:child_process');
      const sql = (v: string) => execFileSync('mysql', ['-uof', `-p${process.env.E2E_DB_PW || ''}`, process.env.E2E_DB || 'optiflow2', '-e', `UPDATE magazalar SET surum='${v}' WHERE email='${process.env.E2E_EMAIL}'`]);
      sql('lite');
      await loadAndWait(owc, `${BASE}/sgk-aktar.php`);
      await waitFor(() => A.state.account?.package === 'lite');
      check('Lite: toolbar shows locked "Pro ile aktar"', A.state.account?.transferAllowed === false);
      let extracted = false;
      const origExtract = A.medula.extract.bind(A.medula);
      A.medula.extract = async () => {
        extracted = true;
        return origExtract();
      };
      await A.runTransfer();
      A.medula.extract = origExtract;
      check('Lite: transfer refused without reading Medula', A.state.transfer.phase === 'hata' && A.state.transfer.message === MSG.proGerekli && !extracted, JSON.stringify(A.state.transfer));
      check('Lite: SGK page shows locked Pro card, ÜTS locked in menu', await js<boolean>(owc, "!!document.querySelector('.pro-kilitli') && !!document.querySelector('.nav-kilitli')"));
      if (process.env.E2E_SCREENSHOT) {
        await sleep(800);
        fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-lite-shell.png`, (await A.win.webContents.capturePage()).toPNG());
        fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-lite-sgk.png`, (await owc.capturePage()).toPNG());
        await loadAndWait(owc, `${BASE}/pro.php?ozellik=uts`);
        await sleep(900);
        fs.writeFileSync(`${process.env.E2E_SCREENSHOT}-lite-promo.png`, (await owc.capturePage()).toPNG());
      }
      sql('pro');
    }

    if (tenantDb) {
      console.log('Offline copy + barcode (4.12.0)');
      await loadAndWait(owc, `${BASE}/order-new.php`);
      {
        const done = new Promise<void>((r) => owc.once('did-finish-load', () => r()));
        await js(owc, `(() => { const f = document.querySelector('[name=first_name]').form;
          const set = (n, v) => { const el = f.querySelector('[name="' + n + '"]'); if (el) el.value = v; };
          set('first_name', 'Çevrim'); set('last_name', 'Dışı'); set('phone', '05321112233'); set('total_amount', '500,00'); set('deposit', '0');
          HTMLFormElement.prototype.submit.call(f); })()`);
        await done;
        await waitFor(() => !owc.isLoading());
      }
      A.offlineFetchedAt = 0;
      await loadAndWait(owc, `${BASE}/index.php`);
      check('offline copy taken after a page load (feature on)', await waitFor(() => A.state.offline?.available === true && (A.state.offline?.count ?? 0) > 0, 10000), JSON.stringify(A.state.offline));
      const bin = path.join(app.getPath('userData'), 'cevrimdisi.bin');
      check('offline copy on disk is never plaintext', !fs.existsSync(bin) || !fs.readFileSync(bin).toString('utf8').includes('Çevrim'));
      A.openOfflineWindow();
      const ow = A.offlineWin as BrowserWindow;
      await waitFor(() => !!ow && !ow.webContents.isLoading());
      await sleep(600);
      check('offline window lists the orders (read-only)', (await js<string>(ow.webContents, "document.getElementById('liste').innerText")).includes('Çevrim Dışı'));
      check('offline window has no Node and no other API', (await js<string>(ow.webContents, 'typeof require + typeof process + typeof window.optiflowShell + typeof window.optiflowDesktop')) === 'undefined'.repeat(4));
      ow.close();
      await sleep(300);

      tenantSql("DELETE FROM frame_items WHERE barcode = '8690000000017'");
      tenantSql("INSERT INTO frame_items (brand, model, barcode, qty, min_qty, is_active, created_at) VALUES ('E2E', 'Barkod', '8690000000017', 1, 1, 1, NOW())");
      await loadAndWait(owc, `${BASE}/index.php`);
      check('barcode reader script is loaded in the desktop', await js<boolean>(owc, "!!document.querySelector('script[src*=\"barkod.js\"]')"));
      A.command('goster-optiflow');
      await sleep(200);
      owc.focus();
      await js(owc, 'document.activeElement && document.activeElement.blur && document.activeElement.blur()');
      for (const ch of '8690000000017') {
        owc.sendInputEvent({ type: 'keyDown', keyCode: ch });
        owc.sendInputEvent({ type: 'char', keyCode: ch });
        owc.sendInputEvent({ type: 'keyUp', keyCode: ch });
      }
      owc.sendInputEvent({ type: 'keyDown', keyCode: 'Enter' });
      owc.sendInputEvent({ type: 'keyUp', keyCode: 'Enter' });
      check('scanning a frame barcode opens the frame card', await waitFor(() => /cerceve\.php\?duzenle=\d+/.test(owc.getURL()), 8000), owc.getURL());
      tenantSql("DELETE FROM frame_items WHERE barcode = '8690000000017'");

      await loadAndWait(owc, `${BASE}/index.php`);
      {
        const done = new Promise<void>((r) => owc.once('did-finish-load', () => r()));
        await js(owc, "HTMLFormElement.prototype.submit.call(document.querySelector('form[action=\"logout.php\"]'))"); // real logout (POST + CSRF)
        await done;
        await waitFor(() => !owc.isLoading());
        await sleep(800);
      }
      check('logout wipes the offline copy', await waitFor(() => A.state.offline?.available === false, 5000) && !fs.existsSync(bin));
    }

    console.log('Logs');
    const logDir = app.getPath('logs');
    const logText = fs.readdirSync(logDir).map((f) => fs.readFileSync(path.join(logDir, f), 'utf8')).join('\n');
    check('desktop log contains no patient data, T.C. no, csrf or cookies', !/AYŞE|YILMAZ|12345678901|[a-f0-9]{64}|optiflow=[^\[]/.test(logText) && logText.includes('bridge.transfer.ok'));
  } catch (e) {
    check('no exception', false, (e as Error).stack);
  }

  const failed = results.filter((r) => !r.ok).length;
  console.log(`\nE2E: ${results.length - failed} passed, ${failed} failed`);
  fs.writeFileSync(path.join(ROOT, 'e2e-result.json'), JSON.stringify(results, null, 2));
  app.exit(failed ? 1 : 0);
});

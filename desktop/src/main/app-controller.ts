/**
 * DesktopApp — owns the window, the two views, the shell state and the
 * transfer workflow. Small helpers live in their own modules; this file wires
 * them together and holds the (single) mutable state object.
 */
import { app, BrowserWindow, dialog, net, safeStorage, shell, WebContentsView, type IpcMainEvent, type IpcMainInvokeEvent, type Menu, type WebContents } from 'electron';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { IPC, OFFLINE_REFRESH_MS, TOOLBAR_HEIGHT } from '../shared/constants';
import { MSG, messageForLoadError } from '../shared/messages';
import { redactUrl } from '../shared/redact';
import type { AccountInfo, DesktopConfig, ShellState, TransferState } from '../shared/types';
import { MedulaController } from './bridge/medula-controller';
import { incomingUrl, isMedulaUrl, isOptiflowUrl, optiflowEndpoint } from './bridge/origin-policy';
import { RetryStore } from './bridge/retry-store';
import { buildPayload, OptiflowApi, type TransferPayload } from './bridge/transfer-service';
import { attachDownloads, openLastDownload, showLastDownloadInFolder } from './downloads';
import { parseShellCommand, type ShellCommand } from './ipc-validation';
import { exportDiagnostics, log } from './logging';
import { buildMenu } from './menu';
import { attachMedulaGuards, attachOptiflowGuards, attachShellGuards, type NavDeps } from './navigation';
import { MedulaGirisKasasi, neSorulmali } from './medula-giris-kasasi';
import { OfflineStore } from './offline-store';
import { medulaPrefs, offlinePrefs, optiflowPrefs, shellPrefs } from './security';
import { clearMedulaSession, restartMedulaBrowser, type Sessions } from './sessions';
import { Updater } from './updater';

const ABORTED = -3;

export class DesktopApp {
  private win!: BrowserWindow;
  private optiflowView!: WebContentsView;
  private medulaView: WebContentsView | null = null;
  private medula: MedulaController | null = null;
  private readonly api: OptiflowApi;
  private readonly retry = new RetryStore();
  private updater!: Updater;
  private menu: Menu | null = null;
  private busy = false;
  private probeTimer: ReturnType<typeof setTimeout> | null = null;
  private onlineTimer: ReturnType<typeof setInterval> | null = null;
  private lastOptiflowFailedUrl: string | null = null;
  /** 4.12.0 — read-only offline copy (DPAPI-encrypted on disk). */
  private offline!: OfflineStore;
  private offlineWin: BrowserWindow | null = null;
  private offlineFetchedAt = 0;
  private offlineUrl = '';
  /** 5.4.0 — saved Medula login (DPAPI, this PC only) and the last "Giriş" press awaiting its result. */
  private kasa!: MedulaGirisKasasi;
  private bekleyenGiris: { kullanici: string; sifre: string; degisim: boolean; at: number } | null = null;
  private girisSorusuAcik = false;
  private readonly shellUrl: string;
  private readonly iconPath: string;
  /** Desktop opens straight into the store login (never the marketing landing page). */
  private readonly startUrl: string;
  private state: ShellState;

  constructor(
    private readonly cfg: DesktopConfig,
    private readonly sessions: Sessions,
    private readonly distDir: string,
  ) {
    this.api = new OptiflowApi(cfg, (u, i) => sessions.optiflow.fetch(u, i));
    this.shellUrl = pathToFileURL(path.join(distDir, 'shell', 'index.html')).toString();
    this.iconPath = path.join(distDir, '..', 'assets', 'icons', 'icon.png');
    this.startUrl = optiflowEndpoint(cfg, 'magaza-giris.php', {});
    this.state = {
      version: app.getVersion(),
      appEnv: cfg.appEnv,
      activeView: 'optiflow',
      layout: 'sekme',
      online: net.isOnline(),
      optiflow: { loaded: false },
      medula: { opened: false, canGoBack: false, canGoForward: false },
      transfer: { phase: 'bos', message: '', canRetry: false },
      update: { status: 'kapali' },
    };
  }

  /* ------------------------------------------------------------------ setup */

  create(): void {
    this.offline = new OfflineStore(path.join(app.getPath('userData'), 'cevrimdisi.bin'), {
      available: () => safeStorage.isEncryptionAvailable(),
      encrypt: (t) => safeStorage.encryptString(t),
      decrypt: (b) => safeStorage.decryptString(b),
    });
    this.kasa = new MedulaGirisKasasi(
      path.join(app.getPath('userData'), 'medula-giris.bin'),
      path.join(app.getPath('userData'), 'medula-giris-ayar.json'),
      {
        available: () => safeStorage.isEncryptionAvailable(),
        encrypt: (t) => safeStorage.encryptString(t),
        decrypt: (b) => safeStorage.decryptString(b),
      },
    );
    this.offlineUrl = pathToFileURL(path.join(this.distDir, 'shell', 'cevrimdisi.html')).toString();
    this.state.offline = this.offline.meta();
    this.win = new BrowserWindow({
      width: 1440,
      height: 900,
      minWidth: 980,
      minHeight: 640,
      show: false,
      title: 'OptiFlow Pro',
      backgroundColor: '#141012',
      icon: this.iconPath,
      // The navy toolbar doubles as the title bar; Windows draws its own min/max/close on top.
      titleBarStyle: 'hidden',
      titleBarOverlay: { color: '#141012', symbolColor: '#f3eef0', height: TOOLBAR_HEIGHT },
      autoHideMenuBar: true,
      webPreferences: shellPrefs(this.distDir),
    });
    attachShellGuards(this.win.webContents);
    void this.win.loadURL(this.shellUrl);
    this.win.once('ready-to-show', () => this.win.show());
    for (const ev of ['resize', 'maximize', 'unmaximize', 'enter-full-screen', 'leave-full-screen'] as const) {
      this.win.on(ev as 'resize', () => this.layout());
    }
    this.watchCrashes(this.win.webContents, 'shell');

    this.optiflowView = new WebContentsView({ webPreferences: optiflowPrefs(this.distDir, this.cfg, app.getVersion()) });
    this.win.contentView.addChildView(this.optiflowView);
    const owc = this.optiflowView.webContents;
    // session.setUserAgent does not reach navigation requests of an existing webContents:
    // set it on the webContents too (verified in tests/e2e).
    owc.setUserAgent(this.sessions.optiflow.getUserAgent());
    attachOptiflowGuards(owc, this.navDeps());
    this.watchCrashes(owc, 'optiflow');
    this.watchOptiflowLoads(owc);
    this.registerOptiflowIpc(owc);

    this.registerShellIpc(this.win.webContents);
    attachDownloads(this.sessions.optiflow, 'optiflow', { onDone: (d) => this.downloadNotice(d) });
    attachDownloads(this.sessions.medula, 'medula', { onDone: (d) => this.downloadNotice(d) });

    this.updater = new Updater(this.cfg, (u) => this.patch({ update: u }));
    this.updater.start();

    this.menu = this.buildAppMenu();
    // No classic menu bar: the "⋯" button opens this menu; shortcuts are handled in onShortcut().
    this.win.setMenu(null);
    for (const wc of [this.win.webContents, owc]) this.attachShortcuts(wc);

    this.onlineTimer = setInterval(() => {
      const online = net.isOnline();
      if (online !== this.state.online) {
        log.info('network.online', { online });
        this.patch({ online });
      }
    }, 10_000);

    this.layout();
    log.info('optiflow.load', { url: redactUrl(this.startUrl) });
    void owc.loadURL(this.startUrl);
  }

  private buildAppMenu(): Menu {
    return buildMenu(
      {
        showOptiflow: () => this.showView('optiflow'),
        showMedula: () => this.openMedula(),
        reloadOptiflow: () => this.reloadOptiflow(),
        medulaHome: () => this.openMedula(this.cfg.medulaHomeUrl, true),
        clearMedulaSession: () => void this.confirmClearMedula(),
        resetMedula: () => void this.command('medula-sifirla'),
        medulaSifresi: () => void this.medulaSifresiPenceresi(),
        medulaTeklifAcik: () => this.kasa.teklifAcik(),
        medulaTeklifAyarla: (acik) => {
          this.kasa.teklifAyarla(acik);
          this.menu = this.buildAppMenu();
        },
        toggleLayout: () => this.setLayout(this.state.layout === 'sekme' ? 'bolunmus' : 'sekme'),
        checkUpdates: () =>
          void this.updater.check().then((msg) => {
            if (msg) this.patch({ notice: { kind: 'info', text: msg } });
          }),
        exportDiagnostics: () => void exportDiagnostics(this.win, { appEnv: this.cfg.appEnv, server: new URL(this.cfg.optiflowBaseUrl).origin }),
        openLogsFolder: () => void shell.openPath(app.getPath('logs')),
        about: () => this.about(),
      },
      !app.isPackaged,
    );
  }

  focus(): void {
    if (!this.win || this.win.isDestroyed()) return;
    if (this.win.isMinimized()) this.win.restore();
    this.win.focus();
  }

  shutdown(): void {
    this.retry.clear();
    this.bekleyenGiris = null;
    // "Beni hatırla" çerezi (OptiFlow) diske yazılmış olsun.
    void this.sessions.optiflow.cookies.flushStore().catch(() => undefined);
    this.medula?.dispose();
    this.updater?.stop();
    if (this.onlineTimer) clearInterval(this.onlineTimer);
  }

  /** App shortcuts work in every view (the classic menu bar is hidden). */
  private attachShortcuts(wc: WebContents): void {
    wc.on('before-input-event', (event, input) => {
      if (input.type !== 'keyDown') return;
      const key = input.key.toLowerCase();
      let handled = true;
      if (input.control && !input.alt && key === '1') this.showView('optiflow');
      else if (input.control && !input.alt && key === '2') this.showView('medula');
      else if (input.control && !input.alt && key === '3') this.setLayout(this.state.layout === 'sekme' ? 'bolunmus' : 'sekme');
      else if (key === 'f5' && !input.control) {
        if (wc === this.medulaView?.webContents) wc.reload();
        else this.reloadOptiflow();
      } else if (input.alt && !input.control && key === 'alt') this.menu?.popup({ window: this.win });
      else handled = false;
      if (handled) event.preventDefault();
    });
  }

  private navDeps(): NavDeps {
    return {
      cfg: this.cfg,
      iconPath: this.iconPath,
      routeToMedula: (url) => this.openMedula(url, true),
      onBlocked: (url, where) => {
        let host = '';
        try {
          host = new URL(url).host;
        } catch {
          host = 'geçersiz adres';
        }
        this.patch({
          notice: {
            kind: 'warn',
            text: where === 'medula' ? `Medula dışındaki adres açılmadı: ${host}` : `Bu adres OptiFlow içinde açılamaz: ${host}`,
          },
        });
      },
    };
  }

  /* ------------------------------------------------------------------ state */

  private patch(p: Partial<ShellState>): void {
    this.state = { ...this.state, ...p };
    if (!this.win.isDestroyed()) this.win.webContents.send(IPC.shellState, this.state);
    if (p.transfer) this.pushTransferToOptiflow(p.transfer);
    if (p.optiflow || p.medula || p.activeView || p.layout) this.layout();
  }

  private setTransfer(t: Partial<TransferState> & Pick<TransferState, 'phase' | 'message'>): void {
    this.patch({ transfer: { canRetry: this.retry.has(), at: new Date().toISOString(), ...t } });
  }

  private pushTransferToOptiflow(t: TransferState): void {
    const wc = this.optiflowView?.webContents;
    if (!wc || wc.isDestroyed() || !isOptiflowUrl(wc.getURL(), this.cfg)) return;
    wc.send(IPC.optiflowTransferEvent, this.publicTransfer(t));
  }

  private publicTransfer(t: TransferState): Record<string, unknown> {
    // No patient summary goes to the page; it has its own authoritative list.
    return { asama: t.phase, mesaj: t.message, zaman: t.at, gelenId: t.incomingId, yenidenDenenebilir: t.canRetry };
  }

  /* ----------------------------------------------------------------- layout */

  private layout(): void {
    if (!this.win || this.win.isDestroyed()) return;
    const [w = 0, h = 0] = this.win.getContentSize();
    const top = TOOLBAR_HEIGHT;
    const height = Math.max(0, h - top);
    const ofErr = !!this.state.optiflow.error || !this.state.optiflow.everLoaded;
    const mdErr = !!this.state.medula.error;
    const md = this.medulaView;

    if (this.state.layout === 'bolunmus' && md) {
      const left = Math.round(w * 0.5);
      this.optiflowView.setBounds({ x: 0, y: top, width: left, height });
      md.setBounds({ x: left, y: top, width: w - left, height });
      this.optiflowView.setVisible(!ofErr);
      md.setVisible(!mdErr);
      return;
    }
    const full = { x: 0, y: top, width: w, height };
    this.optiflowView.setBounds(full);
    md?.setBounds(full);
    const showMd = this.state.activeView === 'medula' && !!md;
    this.optiflowView.setVisible(!showMd && !ofErr);
    md?.setVisible(showMd && !mdErr);
  }

  private showView(v: 'optiflow' | 'medula'): void {
    if (v === 'medula' && !this.medulaView) return this.openMedula();
    this.patch({ activeView: v });
  }

  private setLayout(l: 'sekme' | 'bolunmus'): void {
    if (l === 'bolunmus' && !this.medulaView) this.ensureMedulaView(this.cfg.medulaHomeUrl);
    this.patch({ layout: l });
  }

  /* ---------------------------------------------------------------- OptiFlow */

  private watchOptiflowLoads(wc: WebContents): void {
    wc.on('did-fail-load', (_e, code, desc, url, isMainFrame) => {
      if (!isMainFrame || code === ABORTED) return;
      log.warn('optiflow.load.failed', { code, desc, url: redactUrl(url) });
      this.lastOptiflowFailedUrl = isOptiflowUrl(url, this.cfg) ? url : null;
      const offline = this.offline.meta();
      this.patch({
        optiflow: { loaded: false, everLoaded: this.state.optiflow.everLoaded, error: messageForLoadError(code) },
        offline,
        ...(offline.available ? { notice: { kind: 'warn' as const, text: 'OptiFlow’a ulaşılamıyor. Açık siparişlerin salt okunur kopyası hazır.', action: 'cevrimdisi-ac' as const } } : {}),
      });
    });
    // Explicit logout: the offline copy belongs to that session, so it goes too.
    // (did-start-navigation also sees logout.php when the server immediately redirects to the login page)
    wc.on('did-start-navigation', (_e, url, _inPlace, isMainFrame) => {
      if (!isMainFrame) return;
      try {
        if (isOptiflowUrl(url, this.cfg) && new URL(url).pathname.endsWith('/logout.php')) this.dropOffline('logout');
      } catch {
        /* ignore */
      }
    });
    wc.on('did-finish-load', () => {
      const url = wc.getURL();
      if (!isOptiflowUrl(url, this.cfg)) return;
      log.debug('optiflow.load.ok', { url: redactUrl(url) });
      this.patch({ optiflow: { loaded: true, everLoaded: true, url: redactUrl(url) } });
      void this.refreshAccount();
    });
  }

  /** Account label for the toolbar. Only on page loads – never polled, so the PHP idle timeout still works. */
  private async refreshAccount(): Promise<AccountInfo | undefined> {
    const r = await this.api.status();
    const account = r.ok ? r.value.account : undefined;
    this.patch({ account });
    if (account) void this.syncOffline(account);
    return account;
  }

  /* ---------------------------------------------------------------- offline */

  /** Keeps the offline copy in step with the logged-in account and the server-side feature flag. */
  private async syncOffline(account: AccountInfo): Promise<void> {
    const owner = this.offline.owner();
    if (!account.features.cevrimdisi) {
      if (owner) this.dropOffline('feature-off');
      return;
    }
    const changed = !!owner && (owner.storeId !== account.storeId || owner.userId !== account.userId);
    if (changed) this.dropOffline('account-changed');
    if (!changed && Date.now() - this.offlineFetchedAt < OFFLINE_REFRESH_MS) return;
    this.offlineFetchedAt = Date.now();
    const r = await this.api.snapshot();
    if (!r.ok) return;
    if (r.value.magaza.id !== account.storeId || r.value.kullanici.id !== account.userId) return;
    this.offline.save(r.value);
    log.info('offline.saved', { count: r.value.siparisler.length, encrypted: safeStorage.isEncryptionAvailable() });
    this.patch({ offline: this.offline.meta() });
  }

  private dropOffline(reason: string): void {
    this.offline.clear();
    this.offlineFetchedAt = 0;
    this.offlineWin?.close();
    log.info('offline.cleared', { reason });
    this.patch({ offline: { available: false } });
  }

  private openOfflineWindow(): void {
    if (!this.offline.meta().available) {
      this.patch({ notice: { kind: 'warn', text: 'Kayıtlı çevrimdışı kopya yok.' } });
      return;
    }
    if (this.offlineWin && !this.offlineWin.isDestroyed()) {
      this.offlineWin.focus();
      return;
    }
    const w = new BrowserWindow({
      width: 1180,
      height: 760,
      parent: this.win,
      title: 'OptiFlow · Çevrimdışı kopya',
      icon: this.iconPath,
      autoHideMenuBar: true,
      backgroundColor: '#f5f3f3',
      webPreferences: offlinePrefs(this.distDir),
    });
    w.setMenu(null);
    attachShellGuards(w.webContents); // local page: no navigation, no popups
    const wc = w.webContents;
    wc.ipc.handle(IPC.offlineGet, (e) => (e.sender === wc && e.senderFrame?.url === this.offlineUrl ? this.offline.load() : null));
    w.on('closed', () => {
      this.offlineWin = null;
    });
    this.offlineWin = w;
    void w.loadURL(this.offlineUrl);
  }

  /* ---------------------------------------------------- SGK hak (5.3.0) */

  /**
   * "Hak sorgula": sends the visible Medula screen (hak sorgu / cam-çerçeve geçmişi) through the
   * same authenticated transfer endpoint; the server recognises the screen and opens sgk-hak.php.
   * Explicit user action only; Pro + 'sgk_hak' feature required (server re-checks).
   */
  private async runHakSorgu(): Promise<void> {
    if (this.busy) return;
    if (!this.medula || !this.medulaView) {
      this.patch({ notice: { kind: 'warn', text: MSG.medulaAcikDegil } });
      return;
    }
    this.busy = true;
    try {
      const st = await this.api.status();
      if (!st.ok) {
        this.patch({ notice: { kind: 'error', text: st.error.message } });
        if (st.error.code === 'session-expired') this.transferFailed('session-expired', st.error.message);
        return;
      }
      const account = st.value.account;
      this.patch({ account });
      if (account.supportMode) return void this.patch({ notice: { kind: 'warn', text: MSG.destekModu } });
      if (!account.transferAllowed) return void this.patch({ notice: { kind: 'warn', text: MSG.proGerekli } });
      if (!account.features.sgk_hak) return void this.patch({ notice: { kind: 'warn', text: 'SGK hak kontrolü bu mağazada açık değil.' } });

      const out = await this.medula.extractScreen();
      if (out.kind !== 'ok') {
        const text = out.kind === 'login' ? MSG.medulaOturumBitti : out.kind === 'not-medula' ? MSG.medulaDisiAdres : MSG.hakEkraniYok;
        log.info('bridge.hak.failed', { reason: out.kind });
        return void this.patch({ notice: { kind: 'warn', text } });
      }
      const payload = buildPayload(out.extraction.text, out.extraction.title || 'Medula hak sorgu');
      if (!payload) return void this.patch({ notice: { kind: 'warn', text: MSG.hakEkraniYok } });
      let r = await this.api.transfer(payload, st.value.csrf, account, app.getVersion());
      if (!r.ok && r.error.code === 'account-changed') {
        const st2 = await this.api.status();
        if (st2.ok) r = await this.api.transfer(payload, st2.value.csrf, st2.value.account, app.getVersion());
      }
      if (!r.ok) {
        log.warn('bridge.hak.send-failed', { code: r.error.code });
        return void this.patch({ notice: { kind: 'error', text: r.error.message } });
      }
      log.info('bridge.hak.ok', { incomingId: r.value.incomingId });
      this.patch({ notice: { kind: 'info', text: MSG.hakGonderildi } });
      this.openIncoming(r.value.incomingId); // sgk-aktar.php?gelen=… → server redirects to sgk-hak.php
    } finally {
      this.busy = false;
    }
  }

  /* ------------------------------------------------------ Medula list check */

  /** "Listeyi kontrol et": which prescriptions on the Medula list are not in OptiFlow yet. */
  private async runListCheck(): Promise<void> {
    if (this.busy) return;
    if (!this.medula || !this.medulaView) {
      this.patch({ notice: { kind: 'warn', text: MSG.medulaAcikDegil } });
      return;
    }
    this.busy = true;
    try {
      const st = await this.api.status();
      if (!st.ok) {
        this.patch({ notice: { kind: 'error', text: st.error.message } });
        return;
      }
      const account = st.value.account;
      this.patch({ account });
      if (!account.features.sgk_mutabakat) {
        this.patch({ notice: { kind: 'warn', text: 'SGK mutabakat bu mağazada açık değil.' } });
        return;
      }
      const out = await this.medula.readList();
      if (out.kind !== 'ok') {
        const text =
          out.kind === 'login' ? MSG.medulaOturumBitti : out.kind === 'not-medula' ? MSG.medulaDisiAdres : 'Bu ekranda reçete numarası bulunamadı. Medula’da reçete listesini açın.';
        this.patch({ notice: { kind: 'warn', text } });
        return;
      }
      const r = await this.api.checkList(out.numaralar, st.value.csrf, account);
      if (!r.ok) {
        this.patch({ notice: { kind: 'error', text: r.error.message } });
        return;
      }
      const v = r.value;
      log.info('bridge.list.checked', { total: v.total, missing: v.missing });
      this.patch({
        notice: {
          kind: v.missing ? 'warn' : 'info',
          text: v.missing ? `Listede ${v.total} reçete: ${v.missing} tanesi OptiFlow’a aktarılmamış.` : `Listedeki ${v.total} reçetenin hepsi OptiFlow’da kayıtlı.`,
        },
      });
      void this.optiflowView.webContents.loadURL(optiflowEndpoint(this.cfg, 'sgk-mutabakat.php', { kontrol: '1' }));
      if (this.state.layout === 'sekme') this.patch({ activeView: 'optiflow' });
    } finally {
      this.busy = false;
    }
  }

  private reloadOptiflow(): void {
    const wc = this.optiflowView.webContents;
    this.patch({ optiflow: { loaded: false, everLoaded: this.state.optiflow.everLoaded } });
    if (this.lastOptiflowFailedUrl) void wc.loadURL(this.lastOptiflowFailedUrl);
    else if (isOptiflowUrl(wc.getURL(), this.cfg)) wc.reload();
    else void wc.loadURL(this.startUrl);
    this.lastOptiflowFailedUrl = null;
  }

  /* ------------------------------------------------------------------ Medula */

  private ensureMedulaView(initialUrl: string): WebContentsView {
    if (this.medulaView) return this.medulaView;
    const view = new WebContentsView({ webPreferences: medulaPrefs(this.distDir, this.cfg) });
    this.win.contentView.addChildView(view);
    this.medulaView = view;
    const wc = view.webContents;
    wc.setUserAgent(this.sessions.medula.getUserAgent());
    attachMedulaGuards(wc, this.navDeps());
    this.attachShortcuts(wc);
    this.watchCrashes(wc, 'medula');
    this.medula = new MedulaController(wc, this.cfg, (y) => {
      this.bekleyenGiris = { ...y, at: Date.now() };
    });

    const nav = () => {
      let host: string | undefined;
      try {
        host = new URL(wc.getURL()).host;
      } catch {
        host = undefined;
      }
      this.patch({
        medula: {
          ...this.state.medula,
          opened: true,
          canGoBack: wc.navigationHistory.canGoBack(),
          canGoForward: wc.navigationHistory.canGoForward(),
          host,
        },
      });
      this.scheduleProbe();
    };
    wc.on('did-navigate', (_e, url) => {
      log.info('medula.navigate', { url: redactUrl(url) });
      nav();
    });
    wc.on('did-navigate-in-page', nav);
    wc.on('did-frame-finish-load', () => this.scheduleProbe());
    wc.on('did-fail-load', (_e, code, desc, url, isMainFrame) => {
      if (!isMainFrame || code === ABORTED) return;
      log.warn('medula.load.failed', { code, desc, url: redactUrl(url) });
      this.patch({ medula: { ...this.state.medula, error: code === -106 ? MSG.internetYok : MSG.medulaBaglanti } });
    });
    wc.on('did-finish-load', () => {
      if (this.state.medula.error) this.patch({ medula: { ...this.state.medula, error: undefined } });
    });

    this.patch({ medula: { ...this.state.medula, opened: true } });
    void wc.loadURL(isMedulaUrl(initialUrl, this.cfg) ? initialUrl : this.cfg.medulaHomeUrl);
    return view;
  }

  /** Open (or focus) Medula. A URL is only followed if it is on an allowed SGK host. */
  private openMedula(url?: string, navigate = false): void {
    const existed = !!this.medulaView;
    const view = this.ensureMedulaView(url ?? this.cfg.medulaHomeUrl);
    if (existed && navigate && url && isMedulaUrl(url, this.cfg)) void view.webContents.loadURL(url);
    if (this.state.layout === 'sekme') this.patch({ activeView: 'medula' });
  }

  private scheduleProbe(): void {
    if (this.probeTimer) clearTimeout(this.probeTimer);
    this.probeTimer = setTimeout(async () => {
      if (!this.medula) return;
      const probe = await this.medula.probe();
      this.patch({ medula: { ...this.state.medula, probe } });
      if (probe?.looksLikeLogin) void this.girisEkrani();
      else if (probe && this.bekleyenGiris) void this.girisBasarili();
    }, 700);
  }

  /* ------------------------------------------------- Medula girişi (5.4.0) */

  /** Giriş ekranı açıldı: kayıtlı bilgiyle doldur, "Giriş"e basılınca değerleri yakala. */
  private async girisEkrani(): Promise<void> {
    if (!this.medula) return;
    const kayit = this.kasa.al();
    const sonuclar = await this.medula.giris(kayit, this.kasa.kullanilabilir());
    if (sonuclar.includes('doldu')) {
      log.info('medula.giris.dolduruldu');
      this.patch({ notice: { kind: 'info', text: 'Medula kullanıcı adı ve şifreniz yazıldı. Güvenlik kodunu girip “Giriş”e basın.' } });
    }
  }

  /** "Giriş"ten sonra giriş ekranı kapandı: giriş başarılı sayılır; kaydetmeyi / güncellemeyi sor. */
  private async girisBasarili(): Promise<void> {
    const y = this.bekleyenGiris;
    this.bekleyenGiris = null;
    if (!y || Date.now() - y.at > 3 * 60_000 || this.girisSorusuAcik) return;
    const karar = neSorulmali(y, this.kasa.al(), this.kasa.teklifAcik(), this.kasa.kullanilabilir());
    if (karar.soru === 'yok' || !karar.kayit) return;
    this.girisSorusuAcik = true;
    try {
      const guncelle = karar.soru === 'guncelle';
      const r = await dialog.showMessageBox(this.win, {
        type: 'question',
        title: 'Medula şifresi',
        message: guncelle ? 'Kayıtlı Medula şifresi güncellensin mi?' : 'Medula giriş bilgileriniz bu bilgisayara kaydedilsin mi?',
        detail:
          'Bir dahaki girişte kullanıcı adı ve şifre kendiliğinden yazılır; siz yalnızca güvenlik kodunu girersiniz.\n\n' +
          'Bilgi yalnızca bu bilgisayarda, Windows hesabınıza bağlı şifrelemeyle saklanır. OptiFlow sunucusuna ya da başka bir yere gönderilmez. ' +
          'İstediğiniz zaman Menü › SGK / Medula › “Kayıtlı Medula şifresi” ile silebilirsiniz.',
        buttons: guncelle ? ['Güncelle', 'Şimdi değil'] : ['Kaydet', 'Şimdi değil', 'Bu bilgisayarda sorma'],
        defaultId: 0,
        cancelId: 1,
        noLink: true,
      });
      if (r.response === 0) {
        const ok = this.kasa.kaydet(karar.kayit);
        log.info('medula.giris.kaydedildi', { ok, guncelleme: guncelle });
        this.patch({ notice: ok ? { kind: 'info', text: guncelle ? 'Medula şifresi güncellendi.' : 'Medula giriş bilgileri bu bilgisayara kaydedildi.' } : { kind: 'warn', text: 'Bu bilgisayarda şifreli saklama kullanılamıyor; bilgi kaydedilmedi.' } });
      } else if (r.response === 2) {
        this.kasa.teklifAyarla(false);
        this.menu = this.buildAppMenu();
      }
    } finally {
      this.girisSorusuAcik = false;
    }
  }

  private async medulaSifresiPenceresi(): Promise<void> {
    const o = this.kasa.ozet();
    if (!o.kayitli) {
      await dialog.showMessageBox(this.win, {
        type: 'info',
        title: 'Kayıtlı Medula şifresi',
        message: 'Bu bilgisayarda kayıtlı Medula şifresi yok.',
        detail: this.kasa.kullanilabilir()
          ? 'Medula’ya bir kez giriş yaptığınızda OptiFlow Pro kaydetmeyi önerir.'
          : 'Bu bilgisayarda şifreli saklama kullanılamadığı için şifre kaydedilemiyor.',
        buttons: ['Tamam'],
      });
      return;
    }
    const r = await dialog.showMessageBox(this.win, {
      type: 'info',
      title: 'Kayıtlı Medula şifresi',
      message: `Kayıtlı kullanıcı: ${o.kullanici}`,
      detail: 'Bilgi yalnızca bu bilgisayarda, Windows hesabınıza bağlı şifrelemeyle saklanıyor. Silerseniz Medula girişinde kullanıcı adı ve şifreyi yeniden yazarsınız.',
      buttons: ['Kapat', 'Sil'],
      defaultId: 0,
      cancelId: 0,
      noLink: true,
    });
    if (r.response === 1) {
      this.kasa.sil();
      log.info('medula.giris.silindi');
      this.patch({ notice: { kind: 'info', text: 'Kayıtlı Medula şifresi silindi.' } });
    }
  }

  private async confirmClearMedula(): Promise<void> {
    const r = await dialog.showMessageBox(this.win, {
      type: 'warning',
      buttons: ['Vazgeç', 'Temizle'],
      defaultId: 0,
      cancelId: 0,
      title: 'Medula oturumunu temizle',
      message: 'Medula oturumu ve çerezleri silinsin mi?',
      detail: 'Medula’ya yeniden giriş yapmanız gerekecek. OptiFlow oturumunuz ve kayıtlı Medula şifreniz (varsa) etkilenmez.',
    });
    if (r.response !== 1) return;
    this.retry.clear();
    await clearMedulaSession(this.sessions.medula);
    if (this.medulaView) void this.medulaView.webContents.loadURL(this.cfg.medulaHomeUrl);
    this.setTransfer({ phase: 'bos', message: '', canRetry: false });
    this.patch({ notice: { kind: 'info', text: 'Medula oturumu temizlendi.' } });
  }

  /* ---------------------------------------------------------------- transfer */

  /** Explicit user action only (toolbar button or OptiFlow page button). */
  private async runTransfer(): Promise<TransferState> {
    if (this.busy) return this.state.transfer;
    if (!this.medula || !this.medulaView) {
      this.setTransfer({ phase: 'hata', message: MSG.medulaAcikDegil });
      return this.state.transfer;
    }
    if (this.state.account && !this.state.account.transferAllowed) {
      log.info('bridge.transfer.blocked', { reason: 'pro-required' });
      this.setTransfer({ phase: 'hata', message: MSG.proGerekli, canRetry: false });
      return this.state.transfer;
    }
    this.busy = true;
    try {
      log.info('bridge.extraction.started');
      this.setTransfer({ phase: 'okunuyor', message: MSG.okunuyor });
      const out = await this.medula.extract();
      if (out.kind !== 'ok') {
        const message =
          out.kind === 'login'
            ? MSG.medulaOturumBitti
            : out.kind === 'not-medula'
              ? MSG.medulaDisiAdres
              : out.kind === 'failed'
                ? MSG.receteOkunamadi
                : MSG.receteYok;
        log.info('bridge.extraction.failed', { reason: out.kind });
        this.setTransfer({ phase: 'hata', message });
        return this.state.transfer;
      }
      log.info('bridge.extraction.succeeded', { fields: out.extraction.extractedFieldCount });
      const payload = buildPayload(out.extraction.text, out.extraction.title);
      if (!payload) {
        this.setTransfer({ phase: 'hata', message: MSG.metinKisa });
        return this.state.transfer;
      }
      this.retry.put(payload); // memory only, until success or TTL
      return await this.send(payload);
    } finally {
      this.busy = false;
    }
  }

  private async retryTransfer(): Promise<void> {
    if (this.busy) return;
    const payload = this.retry.get();
    if (!payload) {
      this.setTransfer({ phase: 'hata', message: MSG.yenidenDenemeYok, canRetry: false });
      return;
    }
    this.busy = true;
    try {
      await this.send(payload);
    } finally {
      this.busy = false;
    }
  }

  private async send(payload: TransferPayload, attempt = 1): Promise<TransferState> {
    this.setTransfer({ phase: 'gonderiliyor', message: MSG.gonderiliyor });
    const st = await this.api.status();
    if (!st.ok) return this.transferFailed(st.error.code, st.error.message);
    const account = st.value.account;
    this.patch({ account });
    if (account.supportMode) return this.transferFailed('support-mode', MSG.destekModu);
    if (!account.transferAllowed) {
      this.retry.clear();
      return this.transferFailed('pro-required', MSG.proGerekli);
    }

    const r = await this.api.transfer(payload, st.value.csrf, account, app.getVersion());
    if (!r.ok) {
      // CSRF rotated or session re-issued between the two calls: one automatic retry.
      if (r.error.code === 'account-changed' && attempt === 1) return this.send(payload, 2);
      return this.transferFailed(r.error.code, r.error.message);
    }
    this.retry.clear();
    log.info('bridge.transfer.ok', { incomingId: r.value.incomingId, found: r.value.found });
    this.setTransfer({
      phase: 'basarili',
      message: MSG.basarili,
      summary: r.value.summary,
      incomingId: r.value.incomingId,
      canRetry: false,
    });
    this.maybeOpenIncoming(r.value.incomingId);
    return this.state.transfer;
  }

  private transferFailed(code: string, message: string): TransferState {
    log.warn('bridge.transfer.failed', { code });
    this.setTransfer({ phase: 'hata', message });
    if (code === 'session-expired') {
      // Show the OptiFlow login; the captured payload is kept for retry after login.
      this.patch({ activeView: 'optiflow' });
      void this.optiflowView.webContents.loadURL(this.startUrl);
    }
    return this.state.transfer;
  }

  /** Navigate automatically only from the SGK page itself (never away from a half-filled form). */
  private maybeOpenIncoming(id: number): void {
    const cur = this.optiflowView.webContents.getURL();
    let onSgkPage = false;
    try {
      onSgkPage = isOptiflowUrl(cur, this.cfg) && new URL(cur).pathname.endsWith('/sgk-aktar.php');
    } catch {
      onSgkPage = false;
    }
    if (onSgkPage) this.openIncoming(id);
    // otherwise the toolbar offers the 'Reçeteyi aç' button
  }

  private openIncoming(id?: number): void {
    const target = incomingUrl(this.cfg, id ?? this.state.transfer.incomingId ?? 0);
    if (!target) return;
    void this.optiflowView.webContents.loadURL(target);
    if (this.state.layout === 'sekme') this.patch({ activeView: 'optiflow' });
  }

  /* --------------------------------------------------------------------- IPC */

  private isTrustedOptiflowSender(e: IpcMainEvent | IpcMainInvokeEvent): boolean {
    const f = e.senderFrame;
    return !!f && e.sender === this.optiflowView.webContents && f.parent === null && isOptiflowUrl(f.url, this.cfg);
  }

  private registerOptiflowIpc(wc: WebContents): void {
    // Scoped to the OptiFlow view's webContents: popups and Medula cannot reach these.
    wc.ipc.on(IPC.optiflowOpenMedula, (e) => {
      if (this.isTrustedOptiflowSender(e)) this.openMedula();
    });
    wc.ipc.handle(IPC.optiflowTransfer, async (e) => {
      if (!this.isTrustedOptiflowSender(e)) return { asama: 'hata', mesaj: 'Yetkisiz' };
      return this.publicTransfer(await this.runTransfer());
    });
    wc.ipc.handle(IPC.optiflowStatus, (e) => {
      if (!this.isTrustedOptiflowSender(e)) return {};
      return { medulaAcik: !!this.medulaView, aktarim: this.publicTransfer(this.state.transfer) };
    });
  }

  private registerShellIpc(wc: WebContents): void {
    const trusted = (e: IpcMainEvent | IpcMainInvokeEvent) => e.sender === wc && e.senderFrame?.url === this.shellUrl;
    wc.ipc.handle(IPC.shellGetState, (e) => (trusted(e) ? this.state : null));
    wc.ipc.on(IPC.shellCommand, (e, raw) => {
      if (!trusted(e)) return;
      const cmd = parseShellCommand(raw);
      if (cmd) void this.command(cmd);
    });
  }

  private async command(cmd: ShellCommand): Promise<void> {
    const md = this.medulaView?.webContents;
    switch (cmd) {
      case 'goster-optiflow':
        return this.showView('optiflow');
      case 'goster-medula':
        return this.showView('medula');
      case 'duzen-bolunmus':
        return this.setLayout('bolunmus');
      case 'duzen-sekme':
        return this.setLayout('sekme');
      case 'medula-geri':
        if (md?.navigationHistory.canGoBack()) md.navigationHistory.goBack();
        return;
      case 'medula-ileri':
        if (md?.navigationHistory.canGoForward()) md.navigationHistory.goForward();
        return;
      case 'medula-yenile':
        if (md) {
          this.patch({ medula: { ...this.state.medula, error: undefined } });
          md.reloadIgnoringCache(); // = Ctrl+F5
        }
        return;
      case 'medula-sifirla':
        // "Tarayıcıyı kapatıp aç": session cookies + cache gone, fresh login page.
        await restartMedulaBrowser(this.sessions.medula);
        this.retry.clear();
        this.openMedula(this.cfg.medulaHomeUrl, true);
        this.patch({ notice: { kind: 'info', text: 'Medula sıfırlandı. Giriş sayfası yeniden açıldı.' } });
        return;
      case 'medula-ana-sayfa':
        return this.openMedula(this.cfg.medulaHomeUrl, true);
      case 'aktar':
        await this.runTransfer();
        return;
      case 'yeniden-dene':
        return this.retryTransfer();
      case 'gelen-ac':
        return this.openIncoming();
      case 'optiflow-yenile':
      case 'hata-yeniden-dene':
        return this.reloadOptiflow();
      case 'guncelleme-indir':
        return this.updater.download();
      case 'guncelleme-kur':
        return this.updater.install();
      case 'indirme-ac':
        return openLastDownload();
      case 'indirme-klasor':
        return showLastDownloadInFolder();
      case 'bildirim-kapat':
        return this.patch({ notice: undefined });
      case 'menu':
        this.menu?.popup({ window: this.win });
        return;
      case 'liste-kontrol':
        return this.runListCheck();
      case 'cevrimdisi-ac':
        return this.openOfflineWindow();
      case 'hak-sorgula':
        return this.runHakSorgu();
    }
  }

  /* ------------------------------------------------------------ misc / crash */

  private downloadNotice(d: { ok: boolean; fileName: string }): void {
    this.patch({
      notice: d.ok
        ? { kind: 'info', text: `Dosya kaydedildi: ${d.fileName}`, action: 'indirme-klasor' }
        : { kind: 'warn', text: 'İndirme iptal edildi ya da tamamlanamadı.' },
    });
  }

  private watchCrashes(wc: WebContents, which: 'shell' | 'optiflow' | 'medula'): void {
    wc.on('render-process-gone', (_e, details) => {
      log.error('renderer.gone', { which, reason: details.reason, exitCode: details.exitCode });
      if (which === 'shell') {
        if (!this.win.isDestroyed()) void this.win.loadURL(this.shellUrl);
        return;
      }
      const text = 'Sayfa beklenmedik şekilde kapandı. “Tekrar dene” ile devam edebilirsiniz.';
      if (which === 'optiflow') this.patch({ optiflow: { loaded: false, everLoaded: this.state.optiflow.everLoaded, error: text } });
      else this.patch({ medula: { ...this.state.medula, error: text } });
    });
    wc.on('unresponsive', async () => {
      log.warn('renderer.unresponsive', { which });
      const r = await dialog.showMessageBox(this.win, {
        type: 'warning',
        buttons: ['Bekle', 'Yeniden yükle'],
        defaultId: 0,
        title: 'Yanıt vermiyor',
        message: which === 'medula' ? 'Medula ekranı yanıt vermiyor.' : 'OptiFlow ekranı yanıt vermiyor.',
      });
      if (r.response === 1) wc.reload();
    });
  }

  private about(): void {
    void dialog.showMessageBox(this.win, {
      type: 'info',
      title: 'OptiFlow Pro',
      message: `OptiFlow Pro ${app.getVersion()}`,
      detail:
        `Ortam: ${this.cfg.appEnv}\nSunucu: ${new URL(this.cfg.optiflowBaseUrl).origin}\n` +
        `Electron ${process.versions.electron} · Chromium ${process.versions.chrome}\n\n` +
        (this.state.account ? `Paket: ${this.state.account.package === 'pro' ? 'OptiFlow Pro' : 'OptiFlow Lite'}\n\n` : '') +
        'Medula köprüsü uygulamanın içindedir; Chrome eklentisi gerekmez. SGK şifreniz yalnızca siz “Kaydet” derseniz, ' +
        'yalnızca bu bilgisayarda Windows şifrelemesiyle saklanır ve OptiFlow sunucusuna gönderilmez; ' +
        'reçete yalnızca siz “Aktar” dediğinizde, o an ekranda görünen haliyle OptiFlow’a gönderilir.',
    });
  }
}

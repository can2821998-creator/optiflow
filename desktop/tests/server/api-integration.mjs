/**
 * Backend integration test for the desktop bridge + tenant isolation.
 * Runs against a DISPOSABLE OptiFlow test instance (never production!):
 *
 *   OPTIFLOW_TEST_URL=http://127.0.0.1:8081 OPTIFLOW_MERKEZ_SIFRE=... node tests/server/api-integration.mjs
 *
 * The instance's MySQL user must be allowed to CREATE DATABASE (stores are activated
 * automatically) and config.php must set merkez_admin_password (impersonation test).
 * Creates two synthetic stores; uses only synthetic fixture text.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = (process.env.OPTIFLOW_TEST_URL || '').replace(/\/$/, '');
const MERKEZ = process.env.OPTIFLOW_MERKEZ_SIFRE || '';
if (!BASE || !MERKEZ) {
  console.log('SKIP: OPTIFLOW_TEST_URL and OPTIFLOW_MERKEZ_SIFRE are required');
  process.exit(0);
}
const here = path.dirname(fileURLToPath(import.meta.url));
const METIN = fs.readFileSync(path.join(here, '../fixtures/recete-uzak.metin.txt'), 'utf8');
const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 OptiFlowDesktop/5.0.0';

let failed = 0;
let passed = 0;
function check(name, cond, extra = '') {
  if (cond) {
    passed++;
    console.log(`  ✓ ${name}`);
  } else {
    failed++;
    console.log(`  ✗ ${name} ${extra}`);
  }
}

class Client {
  constructor(ua = 'Mozilla/5.0 test') {
    this.cookies = new Map();
    this.ua = ua;
  }
  async req(p, init = {}) {
    const headers = { 'User-Agent': this.ua, ...(init.headers || {}) };
    if (this.cookies.size) headers.Cookie = [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
    const res = await fetch(BASE + '/' + p.replace(/^\//, ''), { ...init, headers, redirect: 'manual' });
    for (const c of res.headers.getSetCookie?.() ?? []) {
      const [kv] = c.split(';');
      const i = kv.indexOf('=');
      this.cookies.set(kv.slice(0, i), kv.slice(i + 1));
    }
    return res;
  }
  async csrfFrom(p) {
    const html = await (await this.req(p)).text();
    return (html.match(/name="csrf" value="([^"]+)"/) || [])[1] || '';
  }
  async form(p, fields) {
    const csrf = await this.csrfFrom(p);
    return this.req(p, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ csrf, ...fields }).toString(),
    });
  }
  async json(p, init) {
    const r = await this.req(p, init);
    let body = null;
    try {
      body = await r.json();
    } catch {
      body = null;
    }
    return { status: r.status, body, ct: r.headers.get('content-type') || '' };
  }
}

const stamp = Date.now().toString(36);
async function newStore(tag) {
  const email = `${tag}-${stamp}@deneme.test`;
  const c = new Client();
  const r = await c.form('kayit.php', {
    isim: `Deneme ${tag.toUpperCase()} Optik`,
    email,
    magaza_sifre: 'Magaza1234',
    magaza_sifre_tekrar: 'Magaza1234',
    admin_ad: `Personel ${tag.toUpperCase()}`,
    admin_kullanici: `p${tag}`,
    admin_sifre: 'Personel123',
    kvkk: '1',
  });
  if (r.status !== 303) throw new Error(`kayit ${tag} failed: ${r.status}`);
  return { email, user: `p${tag}` };
}
async function login(store, ua) {
  const c = new Client(ua);
  await c.form('magaza-giris.php', { email: store.email, password: 'Magaza1234' });
  const r = await c.form('login.php', { username: store.user, password: 'Personel123' });
  if (r.status !== 303) throw new Error('login failed');
  return c;
}
const aktar = (c, csrf, beklenen, metin = METIN) =>
  c.json('masaustu.php?action=aktar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ metin, baslik: 'Medula Optik', beklenen, istemci: { surum: 'test' } }),
  });

console.log(`OptiFlow bridge integration @ ${BASE}`);
const A = await newStore('a');
const B = await newStore('b');

// Central panel: A → Pro, B stays Lite (default)
const merkez = new Client(DESKTOP_UA);
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
async function storeId(email) {
  const html = await (await merkez.req(`merkez-panel.php?q=${encodeURIComponent(email)}`)).text();
  return Number((html.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
}
const idA = await storeId(A.email);
const idB = await storeId(B.email);
{
  const r = await merkez.form('merkez-panel.php', { action: 'surum', id: String(idA), surum: 'pro', geri: 'detay' });
  check('merkez panel sets store A to Pro', r.status === 303 && idA > 0 && idB > 0, `${r.status} ${idA} ${idB}`);
  const detay = await (await merkez.req(`merkez-panel.php?magaza=${idA}`)).text();
  check('merkez panel detail shows the Pro package', /Mevcut: <b>OptiFlow Pro<\/b>/.test(detay));
}

console.log('Authentication / session');
{
  const anon = new Client();
  const r = await anon.json('masaustu.php?action=durum');
  check('durum without session → 401 JSON', r.status === 401 && r.body?.kod === 'magaza_oturumu_yok' && r.ct.includes('json'));
}
const a = await login(A, DESKTOP_UA);
const b = await login(B, DESKTOP_UA);
{
  const r = await (await a.req('index.php')).text();
  check('user login keeps store context (4.10.0 auth fix)', /<title>Siparişler/.test(r));
}
const sa = await a.json('masaustu.php?action=durum');
const sb = await b.json('masaustu.php?action=durum');
check('durum returns store + user + csrf', sa.body?.ok && sa.body.magaza?.id > 0 && sa.body.kullanici?.id > 0 && /^[a-f0-9]{64}$/.test(sa.body.csrf));
check('stores A and B are different tenants', sa.body.magaza.id !== sb.body.magaza.id);
check('durum reports package: A pro, B lite', sa.body.paket === 'pro' && sb.body.paket === 'lite', `${sa.body.paket}/${sb.body.paket}`);
check('pro feature map: A (Pro+desktop) open, B (Lite) locked', sa.body.pro?.sgk_kopru === true && sa.body.pro?.uts === true && sb.body.pro?.sgk_kopru === false && sb.body.pro?.uts === false);
check('durum does NOT return a bridge token', !('token' in sa.body) && !('anahtar' in sa.body) && !JSON.stringify(sa.body).includes('bridge'));
const expA = { magaza_id: sa.body.magaza.id, kullanici_id: sa.body.kullanici.id };

console.log('Transfer endpoint');
{
  const r = await a.json('masaustu.php?action=aktar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ metin: METIN, beklenen: expA }),
  });
  check('POST without CSRF → 419 JSON', r.status === 419 && r.body?.kod === 'csrf');
}
{
  const r = await a.json('masaustu.php?action=aktar');
  check('GET aktar → 405', r.status === 405);
}
{
  const r = await aktar(a, sa.body.csrf, { magaza_id: sb.body.magaza.id, kullanici_id: expA.kullanici_id });
  check('expected store mismatch → 409 hesap_degisti', r.status === 409 && r.body?.kod === 'hesap_degisti');
}
{
  const r = await aktar(a, sa.body.csrf, expA, 'kısa');
  check('too-short text → 422', r.status === 422);
}
let gelenA = 0;
{
  const r = await aktar(a, sa.body.csrf, expA);
  gelenA = r.body?.gelen_id || 0;
  check('valid transfer → ok + gelen_id', r.status === 200 && r.body?.ok && gelenA > 0, JSON.stringify(r.body));
  check('server parser found ≥ 15 fields', (r.body?.bulunan ?? 0) >= 15, String(r.body?.bulunan));
  check('hedef deep link returned', r.body?.hedef === `sgk-aktar.php?gelen=${gelenA}`);
}
{
  const html = await (await a.req(`sgk-aktar.php?gelen=${gelenA}`)).text();
  check('?gelen opens the existing preview (A)', html.includes('Çözümlenen reçete') && html.includes('-1.50') && html.includes('+2.00'));
  check('preview requires confirmation form (eylem=uygula)', html.includes('name="eylem" value="uygula"'));
  check('desktop UA → desktop card, no extension setup', html.includes('data-masaustu-kart') && !html.includes('Köprü kurulumu') && html.includes('masaustu.js'));
  check('delete confirmation is CSP-safe (data-confirm, no inline onsubmit)', html.includes('data-confirm="Bu kayıt silinsin mi?"') && !html.includes('onsubmit='));
}

console.log('Lite / Pro gating');
{
  const expB = { magaza_id: sb.body.magaza.id, kullanici_id: sb.body.kullanici.id };
  const r = await aktar(b, sb.body.csrf, expB);
  check('Lite store: desktop transfer refused → 403 pro_gerekli', r.status === 403 && r.body?.kod === 'pro_gerekli');
  const uts = await b.req('uts-karekod.php');
  check('Lite store: ÜTS redirects to the Pro promo page', uts.status === 303 && /pro\.php\?ozellik=uts/.test(uts.headers.get('location') || ''));
  const promo = await (await b.req('pro.php?ozellik=uts')).text();
  check('promo page explains the feature and the Lite state', promo.includes('ÜTS karekod') && promo.includes('OptiFlow Lite') && promo.includes('pro-tablo'));
  const sgk = await (await b.req('sgk-aktar.php')).text();
  check('Lite store: SGK page keeps manual paste, shows locked Pro card, no extension token', sgk.includes('name="eylem" value="coz"') && sgk.includes('pro-kilitli') && !sgk.includes('data-masaustu-kart') && !/value="[a-f0-9]{40}"/.test(sgk));
  check('Lite store: ÜTS menu item is quietly locked (no PRO badge)', /nav-kilitli[^>]*title="OptiFlow Pro özelliği"/.test(sgk) && sgk.includes('pro.php?ozellik=uts') && !/nav-kilitli[\s\S]{0,400}?pro-rozet/.test(sgk.slice(sgk.indexOf('nav-kilitli'), sgk.indexOf('nav-kilitli') + 500)));
  const utsA = await a.req('uts-karekod.php');
  check('Pro store in desktop: ÜTS opens', utsA.status === 200);
  const sgkA = await (await a.req('sgk-aktar.php')).text();
  check('Pro store in desktop: ÜTS menu item unlocked', !sgkA.includes('nav-kilitli'));
}
{
  const webA = await login(A);
  const s = await webA.json('masaustu.php?action=durum');
  check('Pro store in a browser: features need the desktop app', s.body?.paket === 'pro' && s.body?.pro?.sgk_kopru === false);
  const r = await aktar(webA, s.body.csrf, { magaza_id: s.body.magaza.id, kullanici_id: s.body.kullanici.id });
  check('Pro store in a browser: transfer API refused → 403 pro_gerekli', r.status === 403 && r.body?.kod === 'pro_gerekli');
  const uts = await webA.req('uts-karekod.php');
  check('Pro store in a browser: ÜTS → promo page (open the app)', uts.status === 303);
  const promo = await (await webA.req('pro.php?ozellik=uts')).text();
  check('promo page tells Pro store to open the desktop app', promo.includes('OptiFlow Pro</b> uygulamasında çalışır'));
}

console.log('Desktop start page');
{
  const anon = new Client(DESKTOP_UA);
  const r = await anon.req('index.php');
  check('desktop without session: root → store login (no marketing page)', r.status === 303 && /magaza-giris\.php$/.test(r.headers.get('location') || ''));
  const g = await (await anon.req('magaza-giris.php')).text();
  check('desktop store login shows OptiFlow Pro, no sign-up link', g.includes('pro-baslik') && !g.includes('kayit.php'));
  const web = await (await new Client().req('index.php')).text();
  check('browser without session: marketing page unchanged', web.includes('Ücretsiz deneyin') || web.includes('ücretsiz'));
}

console.log('Tenant isolation');
{
  const r = await b.req(`sgk-aktar.php?gelen=${gelenA}`);
  check("store B cannot open store A's incoming id", r.status === 303);
  const list = await (await b.req('sgk-aktar.php')).text();
  check("store B list does not contain A's transfer", !list.includes('AYŞE YILMAZ'));
}

console.log('Legacy extension endpoint (browser users)');
{
  const web = await login(A);
  const html = await (await web.req('sgk-aktar.php')).text();
  check('Pro store in a browser → "open the app" card + transition extension setup', html.includes('Chrome eklentisini kullanmaya devam et') && !html.includes('data-masaustu-kart'));
  const url = (html.match(/value="([^"]*action=kopru[^"]*)"/) || [])[1]?.replace(/&amp;/g, '&') || '';
  const token = (html.match(/value="([a-f0-9]{40})"/) || [])[1] || '';
  check('bridge URL carries store id (&m=)', /action=kopru&m=\d+$/.test(url), url);
  const post = (u, t) =>
    fetch(u, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: t, metin: METIN, baslik: 'Medula' }), redirect: 'manual' });
  let r = await post(url, token);
  let j = await r.json().catch(() => null);
  check('legacy kopru with &m= works WITHOUT cookies (was broken)', r.status === 200 && j?.ok === true, `${r.status} ${JSON.stringify(j)}`);
  r = await post(url.replace(/&m=\d+/, ''), token);
  j = await r.json().catch(() => null);
  check('old URL without &m= → 401 JSON with guidance (no redirect)', r.status === 401 && /güncel köprü adresini/.test(j?.hata || ''));
  r = await post(url.replace(/&m=\d+/, `&m=${sb.body.magaza.id}`), token);
  j = await r.json().catch(() => null);
  check('extension against a Lite store → 403 (Medula aktarımı Pro paketinde)', r.status === 403 && /Pro paketindedir/.test(j?.hata || ''));
  r = await post(url.replace(/&m=\d+/, '&m=999999'), token);
  j = await r.json().catch(() => null);
  check('unknown store → same 401 message (no enumeration)', r.status === 401 && j?.hata === 'Köprü anahtarı tanınmadı');
  r = await fetch(url, { method: 'OPTIONS', headers: { Origin: 'chrome-extension://x', 'Access-Control-Request-Method': 'POST' } });
  check('CORS preflight → 204', r.status === 204);
}

console.log('Central support (impersonation)');
{
  const m = merkez;
  const gir = await m.form('merkez-panel.php', { action: 'gir', id: String(sa.body.magaza.id) });
  check('merkez → store panel', gir.status === 303);
  const s = await m.json('masaustu.php?action=durum');
  check('durum reports destek_modu', s.body?.destek_modu === true);
  const r = await aktar(m, s.body.csrf, { magaza_id: s.body.magaza.id, kullanici_id: s.body.kullanici.id });
  check('transfer refused in support mode → 403', r.status === 403 && r.body?.kod === 'destek_modu');
}

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

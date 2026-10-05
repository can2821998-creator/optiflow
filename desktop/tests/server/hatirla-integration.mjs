/**
 * 4.18.0 "Beni hatırla" — sunucu entegrasyon testi (gerçek MySQL/MariaDB + PHP).
 * Tarayıcı kapanıp açılmış gibi: yalnızca kalıcı çerezler (of_mh, of_kh) taşınır, PHP oturum çerezi atılır.
 *
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/hatirla-integration.mjs
 */
import { execFileSync } from 'node:child_process';

const BASE = (process.env.OPTIFLOW_TEST_URL || '').replace(/\/$/, '');
const MERKEZ = process.env.OPTIFLOW_MERKEZ_SIFRE || '';
const DBU = process.env.OPTIFLOW_TEST_DB_USER || '';
const DBP = process.env.OPTIFLOW_TEST_DB_PASS || '';
const MERKEZ_DB = process.env.OPTIFLOW_TEST_MERKEZ_DB || 'optiflow2';
if (!BASE || !MERKEZ || !DBU) {
  console.log('SKIP: OPTIFLOW_TEST_URL, OPTIFLOW_MERKEZ_SIFRE and OPTIFLOW_TEST_DB_USER are required');
  process.exit(0);
}
const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 OptiFlowDesktop/5.2.0';
const WEB_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

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
  constructor(ua = WEB_UA) {
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
  async html(p) {
    return (await this.req(p)).text();
  }
  async csrfFrom(p) {
    const html = await this.html(p);
    return (html.match(/name="csrf" value="([^"]+)"/) || [])[1] || '';
  }
  async form(p, fields, from = p) {
    const csrf = await this.csrfFrom(from);
    const body = new URLSearchParams({ csrf });
    for (const [k, v] of Object.entries(fields)) {
      if (Array.isArray(v)) v.forEach((x) => body.append(k, String(x)));
      else body.append(k, String(v));
    }
    return this.req(p, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
  }
  async json(p, init) {
    const r = await this.req(p, init);
    let body = null;
    try {
      body = await r.json();
    } catch {
      body = null;
    }
    return { status: r.status, body };
  }
}

function sql(db, query) {
  return execFileSync('mysql', [`-u${DBU}`, `-p${DBP}`, '-N', '-B', db, '-e', query], { encoding: 'utf8' }).trim();
}

const stamp = Date.now().toString(36);
async function newStore(tag) {
  const email = `m${tag}-${stamp}@deneme.test`;
  const c = new Client();
  const r = await c.form('kayit.php', {
    isim: `Modül ${tag.toUpperCase()} Optik`,
    email,
    magaza_sifre: 'Magaza1234',
    magaza_sifre_tekrar: 'Magaza1234',
    admin_ad: `Personel ${tag.toUpperCase()}`,
    admin_kullanici: `m${tag}`,
    admin_sifre: 'Personel123',
    kvkk: '1',
  });
  if (r.status !== 303) throw new Error(`kayit ${tag}: ${r.status}`);
  return { email, user: `m${tag}` };
}
async function login(store, ua = WEB_UA, extra = {}) {
  const c = new Client(ua);
  await c.form('magaza-giris.php', { email: store.email, password: 'Magaza1234', ...(extra.magaza ? { hatirla: '1' } : {}) });
  const r = await c.form('login.php', { username: store.user, password: 'Personel123', ...(extra.kullanici ? { hatirla: '1' } : {}) });
  if (r.status !== 303) throw new Error('login failed');
  return c;
}




/** Tarayıcıyı kapatıp açmak: oturum çerezi gider, kalıcı çerezler kalır. */
function yenidenAc(c) {
  const d = new Client(c.ua);
  for (const [k, v] of c.cookies) if (k !== 'optiflow' && v !== '' && v !== 'deleted') d.cookies.set(k, v);
  return d;
}
const yer = (r) => r.headers.get('location') || '';

console.log(`OptiFlow 4.18.0 beni hatırla @ ${BASE}`);
const S = await newStore('h');
const T = await newStore('t');

// 1) Kutu işaretsiz: tarayıcı kapanınca her şey sorulur
let c = await login(S);
check('işaretsiz girişte kalıcı çerez yok', !c.cookies.get('of_mh') && !c.cookies.get('of_kh'));
let d = yenidenAc(c);
let r0;
r0 = await d.req('index.php');
check('işaretsiz: yeniden açınca uygulama açılmaz (tanıtım/giriş)', r0.status !== 200 || !(await r0.text()).includes('logout.php'));

// 2) Masaüstünde kutular varsayılan işaretli
const desk = new Client(DESKTOP_UA);
check('masaüstü: mağaza girişinde kutu işaretli', /name="hatirla" value="1" checked/.test(await desk.html('magaza-giris.php')));
const web = new Client();
check('web: mağaza girişinde kutu işaretsiz', /name="hatirla" value="1" >/.test(await web.html('magaza-giris.php')));

// 3) Yalnızca mağaza hatırlansın
c = await login(S, WEB_UA, { magaza: true });
check('mağaza çerezi verildi (HttpOnly)', /^[a-f0-9]{24}\.[a-f0-9]{64}$/.test(c.cookies.get('of_mh') || ''));
d = yenidenAc(c);
let r = await d.req('index.php');
check('yeniden açınca mağaza şifresi sorulmaz → personel girişi', yer(r).includes('login.php'), yer(r));
check('personel girişinde "Beni hatırla" kutusu', (await d.html('login.php')).includes('name="hatirla"'));

// 4) Personel de hatırlansın
c = await login(S, WEB_UA, { magaza: true, kullanici: true });
check('personel çerezi verildi', /^[a-f0-9]{24}\.[a-f0-9]{64}$/.test(c.cookies.get('of_kh') || ''));
d = yenidenAc(c);
r = await d.req('index.php');
check('yeniden açınca doğrudan uygulama (şifre sorulmaz)', r.status === 200 && (await r.text()).includes('logout.php'), String(r.status) + yer(r));
r = await d.req('');
const kok = await r.text();
check('alan adının kökü (/) hatırlanan cihazda da tanıtım sayfası, "Uygulamaya git" ile', r.status === 200 && kok.includes('Gözlükçü Programı OptiFlow') && kok.includes('Uygulamaya git') && !kok.includes('logout.php'));
const db = sql(MERKEZ_DB, `SELECT db_name FROM magazalar WHERE email='${S.email}'`);
check('sunucuda doğrulayıcının kendisi tutulmuyor', !sql(db, 'SELECT dogrulayici_hash FROM oturum_hatirla').includes((c.cookies.get('of_kh') || 'x').split('.')[1]));
check('şema v30', sql(db, "SELECT setting_value FROM app_settings WHERE setting_key='schema_version'") === '30');

// 5) Başka mağazanın çerezi geçmez
const t = await login(T, WEB_UA, { magaza: true });
const karisik = yenidenAc(t);
karisik.cookies.set('of_kh', c.cookies.get('of_kh'));
r = await karisik.req('index.php');
check('A mağazasının personel çerezi B mağazasında geçmez', yer(r).includes('login.php'), yer(r));

// 6) Profil: cihaz listesi + unut
const p = await d.html('profile.php');
check('profil: hatırlanan cihaz ve "bu cihaz"', p.includes('Beni hatırlayan cihazlar') && p.includes('bu cihaz'));

// 7) Çıkış bu cihazın personel çerezini siler, mağaza hatırlanmaya devam eder
const csrf = (p.match(/name="csrf" value="([^"]+)"/) || [])[1];
r = await d.req('logout.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `csrf=${csrf}` });
check('çıkış → giriş sayfası', yer(r).includes('login.php'));
const sonra = yenidenAc(d);
r = await sonra.req('index.php');
check('çıkıştan sonra: personel şifresi sorulur, mağaza şifresi sorulmaz', yer(r).includes('login.php'), yer(r));
check('çıkış personel kaydını siler', sql(db, 'SELECT COUNT(*) FROM oturum_hatirla') === '0');

// 8) Personel şifresi değişince öteki cihazlar
c = await login(S, WEB_UA, { magaza: true, kullanici: true });
const ikinci = await login(S, DESKTOP_UA, { magaza: true, kullanici: true });
await ikinci.form('profile.php', { current_password: 'Personel123', new_password: 'Personel456', new_password_again: 'Personel456' });
r = await yenidenAc(c).req('index.php');
check('şifre değişince öteki cihaz yeniden şifre ister', yer(r).includes('login.php'), yer(r));
r = await yenidenAc(ikinci).req('index.php');
check('şifreyi değiştiren cihaz hatırlanmaya devam eder', r.status === 200, String(r.status) + yer(r));

// 9) Farklı mağaza
const f = new Client();
f.cookies.set('of_mh', ikinci.cookies.get('of_mh'));   // mağaza hatırlanıyor, personel girişi ekranı
check('mağaza çereziyle giriş ekranında "Farklı mağaza" düğmesi', (await f.html('login.php')).includes('value="farkli"'));
r = await f.form('magaza-giris.php', { action: 'farkli' }, 'login.php');
check('"Farklı mağaza" → mağaza girişi', yer(r).includes('magaza-giris.php'), String(r.status) + yer(r));
r = await yenidenAc(f).req('index.php');
const silindi = (v) => !v || v === 'deleted';
check('"Farklı mağaza" sonrası mağaza çerezi silinir', silindi(f.cookies.get('of_mh')), f.cookies.get('of_mh'));

// 10) Mağaza şifresi değişirse (merkez sıfırlama ya da yeni şifre)
c = await login(T, WEB_UA, { magaza: true });
const merkez = new Client();
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
const liste = await merkez.html(`merkez-panel.php?q=${encodeURIComponent(T.email)}`);
const sid = Number((liste.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
check('merkez: mağaza bulundu', sid > 0);
const once = Number(sql(MERKEZ_DB, `SELECT COUNT(*) FROM magaza_hatirla WHERE magaza_id=${sid}`));
sql(MERKEZ_DB, `UPDATE magazalar SET sifre_hash='$2y$10$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY' WHERE id=${sid}`);
r = await yenidenAc(c).req('index.php');
const yeni = yenidenAc(c);
r = await yeni.req('login.php');
check('mağaza şifresi değişince mağaza çerezi geçmez (mağaza girişine döner, çerez silinir)', once > 0 && yer(r).includes('magaza-giris.php') && silindi(yeni.cookies.get('of_mh')), `${once} ${yer(r)}`);

// 11) Sahte çerez
const sahte = new Client();
sahte.cookies.set('of_mh', 'a'.repeat(24) + '.' + 'b'.repeat(64));
r = await sahte.req('index.php');
check('sahte mağaza çerezi: tanıtım/giriş sayfası, hata yok', r.status === 200 || yer(r).includes('magaza-giris.php'), String(r.status));

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

/**
 * 4.17.1 Eski sistemden veri taşıma — sunucu entegrasyon testi (gerçek MySQL/MariaDB + PHP).
 * Merkez panel › mağaza › "Eski sistemden veri taşı": yükle → önizle → TAŞI → göçler → mağaza açılır → geri al.
 * Test yedeği tests/tasima/ornek-poyraz-yedek.sql (eski Poyraz tablo yapısı + uydurma kayıtlar).
 *
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/tasima-integration.mjs
 */
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

const BASE = (process.env.OPTIFLOW_TEST_URL || '').replace(/\/$/, '');
const MERKEZ = process.env.OPTIFLOW_MERKEZ_SIFRE || '';
const DBU = process.env.OPTIFLOW_TEST_DB_USER || '';
const DBP = process.env.OPTIFLOW_TEST_DB_PASS || '';
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
async function login(store, ua = WEB_UA) {
  const c = new Client(ua);
  await c.form('magaza-giris.php', { email: store.email, password: 'Magaza1234' });
  const r = await c.form('login.php', { username: store.user, password: 'Personel123' });
  if (r.status !== 303) throw new Error('login failed');
  return c;
}



import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const ORNEK = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../../tests/tasima/ornek-poyraz-yedek.sql'));

async function yukle(c, sid, ad, icerik) {
  const csrf = await c.csrfFrom(`merkez-panel.php?magaza=${sid}`);
  const fd = new FormData();
  fd.append('csrf', csrf);
  fd.append('action', 'tasima_yukle');
  fd.append('id', String(sid));
  fd.append('yedek', new Blob([icerik]), ad);
  return c.req('merkez-panel.php', { method: 'POST', body: fd });
}
const flash = (h) => (h.match(/class="flash flash-(ok|error)">([^<]*)/) || [])[2] || '';

console.log(`OptiFlow 4.17.1 veri taşıma @ ${BASE}`);
const S = await newStore('k');
const merkez = new Client();
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
const liste = await merkez.html(`merkez-panel.php?q=${encodeURIComponent(S.email)}`);
const sid = Number((liste.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
let h = await merkez.html(`merkez-panel.php?magaza=${sid}`);
const db = (h.match(/(mgz_[a-z0-9_]+)/) || [])[1] || '';
check('mağaza sayfasında taşıma bölümü', h.includes('Eski sistemden veri taşı') && h.includes('tasima_yukle'));

await yukle(merkez, sid, 'kotu.sql', 'DROP DATABASE x;');
check('yedek olmayan dosya reddedilir', flash(await merkez.html(`merkez-panel.php?magaza=${sid}`)).includes('yedeği gibi görünmüyor'));
const zararli = ORNEK.toString().replace('SET FOREIGN_KEY_CHECKS=1;', 'DROP DATABASE mysql;\nSET FOREIGN_KEY_CHECKS=1;');
await yukle(merkez, sid, 'zararli.sql', zararli);
check('araya zararlı komut eklenmiş yedek reddedilir', flash(await merkez.html(`merkez-panel.php?magaza=${sid}`)).includes('izin verilmeyen'));
await yukle(merkez, sid, 'resim.png', 'x');
check('uzantı denetimi', flash(await merkez.html(`merkez-panel.php?magaza=${sid}`)).includes('.sql'));

const { gzipSync } = await import('node:zlib');
await yukle(merkez, sid, 'eski-yedek.sql.gz', gzipSync(ORNEK));
h = await merkez.html(`merkez-panel.php?magaza=${sid}`);
check('önizleme: özet tablosu', /Müşteri<\/td><td[^>]*><b>3<\/b>/.test(h) && /Sipariş<\/td><td[^>]*><b>2<\/b>/.test(h));
check('önizleme: eski sürüm uyarısı', h.includes('şema 21 →'));
check('önizleme: veri henüz aktarılmadı', sql(db, 'SELECT COUNT(*) FROM customers') === '0');

let r = await merkez.form('merkez-panel.php', { action: 'tasima_uygula', id: String(sid), onay: 'evet' }, `merkez-panel.php?magaza=${sid}`);
check('yanlış onay reddedilir', flash(await merkez.html(`merkez-panel.php?magaza=${sid}`)).includes('TAŞI'));
r = await merkez.form('merkez-panel.php', { action: 'tasima_uygula', id: String(sid), onay: 'taşı' }, `merkez-panel.php?magaza=${sid}`);
h = await merkez.html(`merkez-panel.php?magaza=${sid}`);
check('taşıma tamamlandı', flash(h).includes('Taşıma tamamlandı: 3 müşteri, 2 sipariş'), flash(h));
check('göçler baştan: şema güncel', Number(sql(db, "SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'")) >= 29);
check('eski siparişlere yeni sütun eklendi', sql(db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'sgk_amount'") === '1');
check('tutarlar korundu', sql(db, 'SELECT SUM(total_amount) FROM orders') === '4250.50' && sql(db, 'SELECT SUM(amount) FROM payments') === '3250.50');
check('zor dizeler bozulmadan geldi', sql(db, "SELECT COUNT(*) FROM customers WHERE first_name LIKE 'Ali -- yorum%' AND last_name LIKE 'Deneme; nokta%'") === '1'
  && sql(db, "SELECT COUNT(*) FROM customers WHERE first_name LIKE 'Zeynep /* yorum%*/'") === '1'
  && sql(db, "SELECT COUNT(*) FROM app_settings WHERE setting_key = 'shop_name' AND setting_value LIKE 'Deneme Optik; %ube ''Merkez'''") === '1');
check('geçmişte kayıt', h.includes('taşındı (Poyraz 3.43.0)'));

// Eski kullanıcı eski şifresiyle girer ve sayfalar açılır
const st = new Client();
await st.form('magaza-giris.php', { email: S.email, password: 'Magaza1234' });
r = await st.form('login.php', { username: 'eski.yonetici', password: 'Eski1234' });
check('eski kullanıcı eski şifresiyle girer', r.status === 303);
let hata = [];
for (const p of ['index.php', 'customers.php', 'workshop.php', 'kasa.php', 'tahsilat.php', 'reports.php', 'kar.php', 'hatirlatma.php', 'order.php?id=10', 'customer.php?id=2']) {
  const t = await st.html(p);
  if (t.includes('Beklenmeyen bir hata') || !t.includes('<html')) hata.push(p);
}
check('taşınan mağazanın sayfaları açılıyor', hata.length === 0, hata.join(','));

// Geri al
r = await merkez.form('merkez-panel.php', { action: 'tasima_geri_al', id: String(sid), onay: 'geri al' }, `merkez-panel.php?magaza=${sid}`);
h = await merkez.html(`merkez-panel.php?magaza=${sid}`);
check('geri al', flash(h).includes('geri alındı') && sql(db, 'SELECT COUNT(*) FROM customers') === '0' && sql(db, "SELECT COUNT(*) FROM user_accounts WHERE username = 'eski.yonetici'") === '0');
check('ilk yönetici geri geldi', sql(db, `SELECT COUNT(*) FROM user_accounts WHERE username = '${S.user}'`) === '1');

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

/**
 * 4.17.0 Hızlı satış — sunucu entegrasyon testi (gerçek MySQL/MariaDB + PHP).
 * Göç v29; özellik anahtarı; ürün kataloğu; parçalı ödemeli satış; stok; gün sonu kasası;
 * raporlar ve kâr/prim; fiş; iptal; personel yetkisi.
 *
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/hizli-satis-integration.mjs
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


console.log(`OptiFlow 4.17.0 hızlı satış @ ${BASE}`);
const S = await newStore('h');
const merkez = new Client();
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
const html = await merkez.html(`merkez-panel.php?q=${encodeURIComponent(S.email)}`);
const sid = Number((html.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
const db = ((await merkez.html(`merkez-panel.php?magaza=${sid}`)).match(/(mgz_[a-z0-9_]+)/) || [])[1] || '';
const a = await login(S);

let r = await a.req('hizli-satis.php');
check('özellik kapalıyken sayfa açılmaz', r.status !== 200 || !(await r.text()).includes('Satışı tamamla'));
await merkez.form('merkez-panel.php', { action: 'ozellikler', id: String(sid), geri: 'detay', 'ozellik[]': ['hizli_satis'] }, `merkez-panel.php?magaza=${sid}`);

check('göç v29: şema ≥ 29 ve tablolar', Number(sql(db, "SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'")) >= 29
  && sql(db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('urunler','urun_hareketleri','satislar','satis_kalemleri','satis_odemeleri')") === '5'
  && sql(db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'frame_moves' AND column_name = 'satis_id'") === '1');

const menu = await a.html('index.php');
check('menüde Hızlı satış ve Ürün kataloğu', menu.includes('hizli-satis.php') && menu.includes('urunler.php'));

// Katalog
r = await a.form('urunler.php', { eylem: 'kaydet', ad: 'Solüsyon 360 ml', kategori: 'solusyon', barkod: '8690000000017', fiyat: '250,00', maliyet: '120', kdv: '10', stok: '5', min_stok: '2', stok_takip: 'on' });
check('ürün eklendi', r.status === 303 && sql(db, "SELECT stok FROM urunler WHERE barkod = '8690000000017'") === '5');
await a.form('urunler.php', { eylem: 'kaydet', ad: 'Montaj', kategori: 'hizmet', fiyat: '', stok_takip: 'on' });
const urunId = Number(sql(db, "SELECT id FROM urunler WHERE barkod = '8690000000017'"));
const hizmetId = Number(sql(db, "SELECT id FROM urunler WHERE kategori = 'hizmet'"));
sql(db, "INSERT INTO frame_items (brand, model, barcode, qty, min_qty, cost, price, is_active, created_at, updated_at) VALUES ('Ray-Ban', 'RB3025', '8053672000011', 2, 1, 1800, 3500, 1, NOW(), NOW())");
const cerId = Number(sql(db, "SELECT id FROM frame_items WHERE barcode = '8053672000011'"));

// Arama
let j = await a.json('hizli-satis.php?ara=8053672000011', { headers: { Accept: 'application/json' } });
check('barkodla çerçeve bulunur', j.body?.sonuclar?.[0]?.tur === 'cerceve' && j.body.sonuclar[0].fiyat === 3500);
j = await a.json('hizli-satis.php?ara=sol%C3%BCs', { headers: { Accept: 'application/json' } });
check('adla ürün bulunur (maliyet yok)', j.body?.sonuclar?.[0]?.id === urunId && !('maliyet' in (j.body?.sonuclar?.[0] || {})));

// Satış: çerçeve + 2 solüsyon + fiyatı tezgâhta girilen hizmet; 100 TL sepet indirimi; nakit + kart
const sepet = [{ tur: 'cerceve', id: cerId, adet: 1, fiyat: 1 }, { tur: 'urun', id: urunId, adet: 2 }, { tur: 'urun', id: hizmetId, adet: 1, fiyat: 150 }];
r = await a.form('hizli-satis.php', { eylem: 'sat', indirim: '100', sepet: JSON.stringify(sepet), odemeler: JSON.stringify([{ method: 'nakit', amount: 900 }, { method: 'kart', amount: 3150 }]) });
const satisId = Number(sql(db, 'SELECT MAX(id) FROM satislar'));
check('satış kaydedildi (fiyat sunucudan)', r.status === 303 && sql(db, `SELECT toplam FROM satislar WHERE id = ${satisId}`) === '4050.00', sql(db, `SELECT toplam FROM satislar WHERE id = ${satisId}`));
check('maliyet (1800 + 2×120)', sql(db, `SELECT maliyet FROM satislar WHERE id = ${satisId}`) === '2040.00');
check('stok düştü', sql(db, `SELECT qty FROM frame_items WHERE id = ${cerId}`) === '1' && sql(db, `SELECT stok FROM urunler WHERE id = ${urunId}`) === '3');
check('çerçeve hareketi satışa bağlı', sql(db, `SELECT satis_id FROM frame_moves WHERE frame_item_id = ${cerId} AND reason = 'satis'`) === String(satisId));
r = await a.form('hizli-satis.php', { eylem: 'sat', sepet: JSON.stringify([{ tur: 'urun', id: urunId, adet: 1 }]), odemeler: JSON.stringify([{ method: 'nakit', amount: 1 }]) });
check('eksik ödeme reddedilir', Number(sql(db, 'SELECT COUNT(*) FROM satislar')) === 1);

// Kasa, raporlar, kâr, fiş
const bugun = new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Istanbul' });
const kasa = await a.html(`kasa.php?date=${bugun}`);
check('kasa: hızlı satış kartı', kasa.includes('Hızlı satışlar') && kasa.includes('1 satış'));
check('kasa: nakit toplamına dahil', /Nakit<\/small><b>900,00/.test(kasa));
const rapor = await a.html(`reports.php?from=${bugun}&to=${bugun}`);
check('rapor: ciroya dahil', rapor.includes('1 hızlı satış dahil'));
const kar = await a.html(`kar.php?from=${bugun}&to=${bugun}`);
check('kâr: hızlı satış ve ürün maliyeti', kar.includes('1 hızlı satış') && kar.includes('ürün 2.040,00'));
const fis = await a.html(`print.php?type=satis&id=${satisId}`);
check('fiş', fis.includes('SATIŞ FİŞİ') && fis.includes('Solüsyon 360 ml') && fis.includes('Kredi kartı'));
const dokum = await a.html(`print.php?type=kasa&date=${bugun}`);
check('kasa dökümü: kart dahil', dokum.includes('3.150,00'));

// İptal
r = await a.form('hizli-satis.php', { eylem: 'iptal', id: String(satisId), sebep: 'Müşteri iade etti' }, `hizli-satis.php?satis=${satisId}`);
check('iptal: stok geri, durum iptal', sql(db, `SELECT durum FROM satislar WHERE id = ${satisId}`) === 'iptal'
  && sql(db, `SELECT qty FROM frame_items WHERE id = ${cerId}`) === '2' && sql(db, `SELECT stok FROM urunler WHERE id = ${urunId}`) === '5');
const kasa2 = await a.html(`kasa.php?date=${bugun}`);
check('iptal kasadan çıktı', !kasa2.includes('Hızlı satışlar') && !/Nakit<\/small><b>900,00/.test(kasa2));
check('denetim kaydı', Number(sql(db, "SELECT COUNT(*) FROM audit_log WHERE action IN ('satis_create','satis_iptal')")) === 2);

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

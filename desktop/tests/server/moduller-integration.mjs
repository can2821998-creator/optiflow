/**
 * 4.12.0 modules — backend integration test (feature flags, WhatsApp queue + consent,
 * contact lens, PayTR callback verification, e-invoice drafts + UBL-TR, lens orders,
 * purchase suggestions, SGK reconciliation, barcode, offline snapshot, cron, isolation).
 *
 * Runs against a DISPOSABLE OptiFlow test instance (never production!):
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/moduller-integration.mjs
 *
 * Fixture rows that have no UI shortcut (a lens line, a PayTR link record, a frame) are
 * inserted with the mysql CLI into the synthetic store's own database.
 */
import crypto from 'node:crypto';
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
async function login(store, ua = WEB_UA) {
  const c = new Client(ua);
  await c.form('magaza-giris.php', { email: store.email, password: 'Magaza1234' });
  const r = await c.form('login.php', { username: store.user, password: 'Personel123' });
  if (r.status !== 303) throw new Error('login failed');
  return c;
}

console.log(`OptiFlow 4.12.0 modules @ ${BASE}`);
const S = await newStore('a');
const T = await newStore('b');
const merkez = new Client();
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
async function storeInfo(email) {
  const html = await merkez.html(`merkez-panel.php?q=${encodeURIComponent(email)}`);
  const id = Number((html.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
  const detay = await merkez.html(`merkez-panel.php?magaza=${id}`);
  const db = (detay.match(/(mgz_[a-z0-9_]+)/) || [])[1] || '';
  return { id, db, detay };
}
const iS = await storeInfo(S.email);
const iT = await storeInfo(T.email);
check('synthetic stores created (own databases)', iS.id > 0 && iT.id > 0 && iS.db && iT.db && iS.db !== iT.db, `${iS.db} ${iT.db}`);

console.log('Feature flags (merkez panel)');
check('store detail lists the feature toggles, all off by default', iS.detay.includes('name="ozellik[]"') && !/name="ozellik\[\]" value="[a-z_]+" checked/.test(iS.detay));
let a = await login(S);
{
  const r = await a.html('mesajlar.php');
  check('feature closed → module page explains it is not enabled', r.includes('henüz açılmamış'));
  const idx = await a.html('index.php');
  check('feature closed → no menu entries for new modules', !idx.includes('mesajlar.php') && !idx.includes('faturalar.php') && !idx.includes('cam-siparis.php'));
}
const TUM = ['whatsapp', 'odeme_linki', 'lens_takip', 'cam_siparis', 'stok_oneri', 'efatura', 'sgk_mutabakat', 'barkod', 'cevrimdisi'];
{
  const r = await merkez.form('merkez-panel.php', { action: 'ozellikler', id: String(iS.id), geri: 'detay', 'ozellik[]': [...TUM, 'bilinmeyen'] }, `merkez-panel.php?magaza=${iS.id}`);
  check('merkez saves the feature list', r.status === 303);
  const kayit = sql(MERKEZ_DB, `SELECT ozellikler FROM magazalar WHERE id = ${iS.id}`);
  check('unknown keys are dropped server-side', !kayit.includes('bilinmeyen') && TUM.every((k) => kayit.includes(k)), kayit);
}
{
  const r = await merkez.form('merkez-panel.php', { action: 'toplu', toplu_eylem: 'ozellik_ac:whatsapp', 'sec[]': [String(iT.id)] });
  check('bulk action opens a feature for selected stores', r.status === 303 && sql(MERKEZ_DB, `SELECT ozellikler FROM magazalar WHERE id = ${iT.id}`) === '["whatsapp"]');
}
{
  const idx = await a.html('index.php');
  check('flags take effect on the next request (fresh from merkez)', idx.includes('mesajlar.php') && idx.includes('faturalar.php') && idx.includes('cam-siparis.php') && idx.includes('stok-oneri.php') && idx.includes('sgk-mutabakat.php'));
  check('desktop-only features hidden in the browser', !idx.includes('barkod.php') && !idx.includes('barkod.js'));
  const b = await a.html('barkod.php');
  check('desktop-only page refuses in the browser', b.includes('yalnızca OptiFlow Pro masaüstü'));
}
const d = new Client(DESKTOP_UA);
d.cookies = new Map(a.cookies);
{
  const r = await d.json('masaustu.php?action=durum');
  check('durum reports the feature map', r.body?.ozellikler?.whatsapp === true && r.body?.ozellikler?.barkod === true && r.body?.ozellikler?.cevrimdisi === true, JSON.stringify(r.body?.ozellikler));
  const idx = await d.html('index.php');
  check('desktop: barcode menu + reader script loaded', idx.includes('barkod.php') && idx.includes('barkod.js'));
}

console.log('Order + WhatsApp queue');
let orderId = 0;
let customerId = 0;
{
  const r = await a.form('order-new.php', {
    first_name: 'Ayşe', last_name: 'Deneme', phone: '0532 123 45 67', birth_year: '',
    order_stage: 'siparis_verildi', transaction_type: 'gozluk', lens_type: '', promised_date: '', total_amount: '1.000,00', deposit: '0', deposit_method: 'nakit',
  });
  orderId = Number(((r.headers.get('location') || '').match(/order\.php\?id=(\d+)/) || [])[1] || 0);
  check('order created', orderId > 0, r.headers.get('location'));
  customerId = Number(sql(iS.db, `SELECT customer_id FROM orders WHERE id = ${orderId}`));
}
{
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'hazirlandi' }, `order.php?id=${orderId}`);
  const m = sql(iS.db, `SELECT CONCAT(olay,'|',telefon,'|',kanal,'|',durum) FROM wa_mesajlar WHERE order_id = ${orderId}`);
  check('order → ready queues the "hazır" message (link channel, normalised phone)', m === 'hazir|905321234567|link|bekliyor', m);
  const page = await a.html('mesajlar.php');
  check('queue page offers one-click WhatsApp with the prepared text', page.includes('https://wa.me/905321234567?text=') && page.includes('data-wa-id='));
  const op = await a.html(`order.php?id=${orderId}`);
  check('order page shows the queued message state', op.includes('WhatsApp kuyruğunda'));
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'atolyede' }, `order.php?id=${orderId}`);
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'hazirlandi' }, `order.php?id=${orderId}`);
  check('same order ready twice → still one message (dedupe)', sql(iS.db, `SELECT COUNT(*) FROM wa_mesajlar WHERE order_id = ${orderId} AND olay = 'hazir'`) === '1');
}
{
  const mid = sql(iS.db, `SELECT id FROM wa_mesajlar WHERE order_id = ${orderId}`);
  const csrf = await a.csrfFrom('mesajlar.php');
  const r = await a.json('mesajlar.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' }, body: new URLSearchParams({ csrf, eylem: 'gonderildi', id: mid }).toString() });
  check('opening in WhatsApp marks the message sent (AJAX)', r.body?.ok === true && sql(iS.db, `SELECT durum FROM wa_mesajlar WHERE id = ${mid}`) === 'gonderildi');
}

console.log('Consent + contact lens');
{
  const r = await a.html(`customer.php?id=${customerId}`);
  check('customer card shows consent "Sorulmadı" and the lens form', r.includes('Sorulmadı') && r.includes('name="action" value="lens_ekle"'));
  const bugun = new Date().toISOString().slice(0, 10);
  await a.form('customer.php', { customer_id: String(customerId), action: 'lens_ekle', urun: 'Deneme Aylık 6lı', goz: 'sag', kutu_adet: '1', kutu_gun: '5', baslangic: bugun, not: '' }, `customer.php?id=${customerId}`);
  const bitis = sql(iS.db, `SELECT DATEDIFF(bitis, baslangic) FROM lens_takip WHERE customer_id = ${customerId}`);
  check('lens end date = boxes × days (one eye)', bitis === '5', bitis);
  const h = await a.html('hatirlatma.php?tur=lens');
  check('lens reminder tab lists the customer', h.includes('Ayşe Deneme') && h.includes('Deneme Aylık 6lı'));
  await a.form('hatirlatma.php', { eylem: 'kuyruga', kind: 'lens', tur: 'lens' }, 'hatirlatma.php?tur=lens');
  check('no consent → lens message NOT queued', sql(iS.db, `SELECT COUNT(*) FROM wa_mesajlar WHERE olay = 'lens'`) === '0');
  await a.form('customer.php', { customer_id: String(customerId), action: 'wa_izin', izin: '1', kaynak: 'yazili' }, `customer.php?id=${customerId}`);
  check('consent recorded with source + audit', sql(iS.db, `SELECT CONCAT(wa_izin,'|',wa_izin_kaynak) FROM customers WHERE id = ${customerId}`) === '1|yazili' && sql(iS.db, `SELECT COUNT(*) FROM audit_log WHERE action = 'wa_izin'`) === '1');
  await a.form('hatirlatma.php', { eylem: 'kuyruga', kind: 'lens', tur: 'lens' }, 'hatirlatma.php?tur=lens');
  check('with consent → lens message queued', sql(iS.db, `SELECT COUNT(*) FROM wa_mesajlar WHERE olay = 'lens'`) === '1');
  await a.form('hatirlatma.php', { eylem: 'kuyruga', kind: 'lens', tur: 'lens' }, 'hatirlatma.php?tur=lens');
  check('queuing the list again does not duplicate', sql(iS.db, `SELECT COUNT(*) FROM wa_mesajlar WHERE olay = 'lens'`) === '1');
}

console.log('WhatsApp settings (secrets encrypted at rest)');
{
  const r = await a.form('settings.php', { tab: 'whatsapp', action: 'wa_kaydet', wa_kanal: 'cloud', wa_cloud_telefon_id: '123456789', wa_cloud_token: 'EAAG-cok-gizli-token-1234', wa_api_surum: 'v21.0', wa_sablon_dil: 'tr', wa_sablon_hazir: 'optiflow_hazir', wa_oto_hazir: '1' }, 'settings.php?tab=whatsapp');
  const raw = sql(iS.db, `SELECT setting_value FROM app_settings WHERE setting_key = 'wa_cloud_token'`);
  check('token stored encrypted (not plaintext)', r.status === 303 && raw.startsWith('g') && !raw.includes('cok-gizli'), raw.slice(0, 12));
  const page = await a.html('settings.php?tab=whatsapp');
  check('settings page never echoes the token (only last 4)', !page.includes('cok-gizli-token') && page.includes('1234'));
  await a.form('settings.php', { tab: 'whatsapp', action: 'wa_kaydet', wa_kanal: 'link', wa_cloud_telefon_id: '123456789', wa_cloud_token: '', wa_oto_hazir: '1' }, 'settings.php?tab=whatsapp');
  check('empty token field keeps the stored secret', sql(iS.db, `SELECT setting_value FROM app_settings WHERE setting_key = 'wa_cloud_token'`) === raw);
}

console.log('PayTR callback (HMAC verified, idempotent)');
{
  const KEY = 'testKey123456789';
  const SALT = 'testSalt987654321';
  await a.form('settings.php', { tab: 'odeme', action: 'odeme_kaydet', paytr_merchant_id: '100200', paytr_merchant_key: KEY, paytr_merchant_salt: SALT, paytr_max_taksit: '1', paytr_gecerlilik_gun: '7' }, 'settings.php?tab=odeme');
  check('PayTR key/salt stored encrypted', !sql(iS.db, `SELECT GROUP_CONCAT(setting_value) FROM app_settings WHERE setting_key IN ('paytr_merchant_key','paytr_merchant_salt')`).includes('test'));
  const cid = `OF${iS.id}T${crypto.randomBytes(12).toString('hex')}`;
  sql(iS.db, `INSERT INTO odeme_linkleri (order_id, customer_id, tutar, callback_id, durum, link, son_kullanim, created_at) VALUES (${orderId}, ${customerId}, 250.00, '${cid}', 'olusturuldu', 'https://www.paytr.com/link/TEST', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())`);
  const tahsilat = await a.html('tahsilat.php');
  check('balance page shows the open payment link', tahsilat.includes('https://www.paytr.com/link/TEST') && tahsilat.includes('Link gönderildi'));
  const post = (fields) => fetch(`${BASE}/odeme-bildirim.php`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields).toString() }).then(async (r) => ({ s: r.status, t: await r.text() }));
  const alan = { callback_id: cid, merchant_oid: 'OID' + stamp, status: 'success', total_amount: '25000', payment_amount: '25000', currency: 'TL', test_mode: '0' };
  const imza = (f, key = KEY, salt = SALT) => crypto.createHmac('sha256', key).update(f.callback_id + f.merchant_oid + salt + f.status + f.total_amount).digest('base64');
  const kotu = await post({ ...alan, hash: imza(alan, 'yanlis', SALT) });
  check('wrong signature → rejected, nothing written', kotu.t === 'IMZA' && sql(iS.db, `SELECT COUNT(*) FROM payments WHERE order_id = ${orderId}`) === '0');
  const tamper = await post({ ...alan, total_amount: '99999', hash: imza(alan) });
  check('tampered amount → signature fails', tamper.t === 'IMZA');
  const iyi = await post({ ...alan, hash: imza(alan) });
  const pay = sql(iS.db, `SELECT CONCAT(amount,'|',method) FROM payments WHERE order_id = ${orderId}`);
  check('valid callback → "OK" + card payment of the LINK amount', iyi.t === 'OK' && pay === '250.00|kart', `${iyi.t} ${pay}`);
  check('link marked paid with merchant_oid', sql(iS.db, `SELECT CONCAT(durum,'|',merchant_oid) FROM odeme_linkleri WHERE callback_id = '${cid}'`) === `odendi|OID${stamp}`);
  const tekrar = await post({ ...alan, hash: imza(alan) });
  check('replayed callback → OK, no duplicate payment', tekrar.t === 'OK' && sql(iS.db, `SELECT COUNT(*) FROM payments WHERE order_id = ${orderId}`) === '1');
  const yabanci = await post({ ...alan, callback_id: 'baska-bicim', hash: 'x' });
  check('foreign/garbled callback id → OK, ignored', yabanci.t === 'OK');
  const cidT = `OF${iT.id}T${crypto.randomBytes(12).toString('hex')}`;
  const capraz = await post({ ...alan, callback_id: cidT, hash: imza({ ...alan, callback_id: cidT }) });
  check('callback for another store with unknown link → ignored', capraz.t === 'OK' && sql(iT.db, 'SELECT COUNT(*) FROM payments') === '0');
  const cidTest = `OF${iS.id}T${crypto.randomBytes(12).toString('hex')}`;
  sql(iS.db, `INSERT INTO odeme_linkleri (order_id, customer_id, tutar, callback_id, durum, test, created_at) VALUES (${orderId}, ${customerId}, 100.00, '${cidTest}', 'olusturuldu', 1, NOW())`);
  const testAlan = { ...alan, callback_id: cidTest, merchant_oid: 'T' + stamp, test_mode: '1' };
  const testOk = await post({ ...testAlan, hash: imza(testAlan) });
  check('TEST-mode payment → link marked, NO payment on the real order', testOk.t === 'OK' && sql(iS.db, `SELECT COUNT(*) FROM payments WHERE order_id = ${orderId}`) === '1' && sql(iS.db, `SELECT durum FROM odeme_linkleri WHERE callback_id = '${cidTest}'`) === 'odendi');
  const get = await fetch(`${BASE}/odeme-bildirim.php`);
  check('GET on callback endpoint → 405', get.status === 405);
}

console.log('e-Invoice drafts + UBL-TR');
{
  const r0 = await a.form('faturalar.php', { eylem: 'siparisten', siparis: String(orderId), sgk_ayri: '1' }, 'faturalar.php');
  const fid = Number(((r0.headers.get('location') || '').match(/fatura\.php\?id=(\d+)/) || [])[1] || 0);
  check('draft created from order', fid > 0);
  const tot = sql(iS.db, `SELECT CONCAT(ara_toplam,'|',kdv_toplam,'|',genel_toplam) FROM faturalar WHERE id = ${fid}`);
  check('KDV split: 1.000,00 incl. 10% → 909.09 + 90.91', tot === '909.09|90.91|1000.00', tot);
  const again = await a.form('faturalar.php', { eylem: 'siparisten', siparis: String(orderId), sgk_ayri: '1' }, 'faturalar.php');
  check('second draft for same order refused', again.status === 303 && sql(iS.db, `SELECT COUNT(*) FROM faturalar WHERE order_id = ${orderId}`) === '1');
  let page = await a.html(`fatura.php?id=${fid}`);
  check('checks list missing seller data before "ready"', page.includes('Satıcı VKN/TCKN geçersiz') && /<button class="btn btn-primary" disabled>/.test(page));
  await a.form('settings.php', { tab: 'efatura', action: 'efatura_kaydet', firma_unvan: 'Deneme Optik Ltd. Şti.', firma_vkn: '1234567891', firma_vergi_dairesi: 'Kadıköy', firma_adres: 'Moda Cad. 1', firma_ilce: 'Kadıköy', firma_il: 'İstanbul', fatura_seri: 'dop', fatura_kdv: '10' }, 'settings.php?tab=efatura');
  check('invalid VKN rejected by checksum', sql(iS.db, `SELECT setting_value FROM app_settings WHERE setting_key = 'firma_vkn'`) === '1234567891' && (await a.html('settings.php?tab=efatura')).includes('Satıcı VKN/TCKN geçersiz'));
  // 10 haneli geçerli VKN üret
  const vknGecerli = (v) => {
    let t = 0;
    for (let i = 0; i < 9; i++) {
      const tmp = (Number(v[i]) + 9 - i) % 10;
      let ara = (tmp * 2 ** (9 - i)) % 9;
      if (tmp !== 0 && ara === 0) ara = 9;
      t += ara;
    }
    return (10 - (t % 10)) % 10 === Number(v[9]);
  };
  let vkn = '';
  for (let k = 0; k < 10; k++) {
    const aday = '123456789' + k;
    if (vknGecerli(aday)) vkn = aday;
  }
  await a.form('settings.php', { tab: 'efatura', action: 'efatura_kaydet', firma_unvan: 'Deneme Optik Ltd. Şti.', firma_vkn: vkn, firma_vergi_dairesi: 'Kadıköy', firma_adres: 'Moda Cad. 1', firma_ilce: 'Kadıköy', firma_il: 'İstanbul', fatura_seri: 'dop', fatura_kdv: '10' }, 'settings.php?tab=efatura');
  check('series normalised to upper case', sql(iS.db, `SELECT setting_value FROM app_settings WHERE setting_key = 'fatura_seri'`) === 'DOP');
  await a.form('fatura.php', { id: String(fid), eylem: 'baslik', profil: 'EARSIVFATURA', tip: 'SATIS', gonderim_sekli: 'ELEKTRONIK', alici_tip: 'kisi', alici_ad: 'ayşe', alici_soyad: 'deneme', alici_kimlik: '12345678901', alici_ilce: 'Moda', alici_il: 'İstanbul' }, `fatura.php?id=${fid}`);
  page = await a.html(`fatura.php?id=${fid}`);
  check('invalid TCKN caught by checksum', page.includes('T.C. kimlik numarası geçersiz'));
  await a.form('fatura.php', { id: String(fid), eylem: 'baslik', profil: 'EARSIVFATURA', tip: 'SATIS', gonderim_sekli: 'ELEKTRONIK', alici_tip: 'kisi', alici_ad: 'ayşe', alici_soyad: 'deneme', alici_kimlik: '11111111111', alici_ilce: 'Moda', alici_il: 'İstanbul' }, `fatura.php?id=${fid}`);
  await a.form('fatura.php', { id: String(fid), eylem: 'satir_kaydet', ad: 'Güneş gözlüğü', miktar: '1', fiyat: '1.200,00', fiyat_tur: 'dahil', kdv_orani: '20', iskonto: '' }, `fatura.php?id=${fid}`);
  const tot2 = sql(iS.db, `SELECT CONCAT(kdv_toplam,'|',genel_toplam) FROM faturalar WHERE id = ${fid}`);
  check('mixed KDV lines total correctly (90.91 + 200.00)', tot2 === '290.91|2200.00', tot2);
  const hz = await a.form('fatura.php', { id: String(fid), eylem: 'hazir' }, `fatura.php?id=${fid}`);
  check('valid draft → ready (locked)', hz.status === 303 && sql(iS.db, `SELECT durum FROM faturalar WHERE id = ${fid}`) === 'hazir');
  const edit = await a.form('fatura.php', { id: String(fid), eylem: 'satir_sil', satir_id: '1' }, `fatura.php?id=${fid}`);
  check('ready invoice cannot be edited', edit.status === 303 && sql(iS.db, `SELECT COUNT(*) FROM fatura_satirlari WHERE fatura_id = ${fid}`) === '2');
  const xml = await (await a.req(`fatura.php?id=${fid}&xml=1`)).text();
  check('UBL-TR: Invoice-2 root, TR1.2, EARSIVFATURA', xml.includes('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2') && xml.includes('<cbc:CustomizationID>TR1.2</cbc:CustomizationID>') && xml.includes('<cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>'));
  check('UBL-TR: draft ID = series + year + zeros, TASLAK note', xml.includes(`<cbc:ID>DOP${new Date().getFullYear()}000000000</cbc:ID>`) && xml.includes('TASLAK'));
  check('UBL-TR: two KDV subtotals + payable', (xml.match(/<cac:TaxSubtotal>/g) || []).length === 4 && xml.includes('<cbc:PayableAmount currencyID="TRY">2200.00</cbc:PayableAmount>'));
  check('UBL-TR: buyer TCKN person, seller VKN, e-Arşiv SendType', xml.includes('schemeID="TCKN">11111111111') && xml.includes(`schemeID="VKN">${vkn}`) && xml.includes('<cbc:FirstName>Ayşe</cbc:FirstName>') && xml.includes('<cbc:DocumentType>ELEKTRONIK</cbc:DocumentType>'));
  check('UBL-TR: amount in words', xml.includes('İKİBİNİKİYÜZ TÜRK LİRASI'));
  let wellFormed = true;
  try {
    execFileSync('xmllint', ['--noout', '-'], { input: xml });
  } catch {
    wellFormed = false;
  }
  check('UBL-TR XML is well-formed (xmllint)', wellFormed);
}

console.log('Lens order to supplier + purchase suggestion');
{
  await a.form('suppliers.php', { action: 'create', name: 'Deneme Cam Lab', contact_name: '', phone: '0212 555 11 22', address: '', tax_no: '', note: '' }, 'suppliers.php');
  let sid = sql(iS.db, `SELECT id FROM suppliers WHERE name = 'Deneme Cam Lab'`);
  if (!sid) {
    sql(iS.db, `INSERT INTO suppliers (name, phone, is_active, created_at) VALUES ('Deneme Cam Lab', '02125551122', 1, NOW())`);
    sid = sql(iS.db, `SELECT id FROM suppliers WHERE name = 'Deneme Cam Lab'`);
  }
  sql(iS.db, `INSERT INTO prescription_records (order_id, customer_id, prescription_date, lens_type, right_sph, left_sph, pd, created_at) VALUES (${orderId}, ${customerId}, CURDATE(), 'Deneme 1.60 Blue', '-2.00', '-1.75', '62', NOW())`);
  const rx = sql(iS.db, `SELECT MAX(id) FROM prescription_records`);
  sql(iS.db, `INSERT INTO prescription_lens_items (prescription_id, lens_no, lens_label, lens_value, stock_status, item_group, eye, lens_type, sph, cyl, axis, created_at) VALUES (${rx}, 1, 'Sağ', 'Deneme 1.60 Blue', 'stokta_yok', 'uzak', 'R', 'Deneme 1.60 Blue', '-2.00', '-0.50', '180', NOW()), (${rx}, 2, 'Sol', 'Deneme 1.60 Blue', 'stokta_yok', 'uzak', 'L', 'Deneme 1.60 Blue', '-1.75', '', '', NOW())`);
  const liste = await a.html('cam-siparis.php');
  check('missing lenses listed for ordering', liste.includes('Deneme 1.60 Blue') && liste.includes('Sağ · SPH -2.00 CYL -0.50 AKS 180'));
  const oneri = await a.html('stok-oneri.php');
  check('purchase suggestion shows lenses waiting', oneri.includes('Deneme 1.60 Blue') && oneri.includes('Cam sipariş fişi oluştur'));
  const kalem = sql(iS.db, `SELECT GROUP_CONCAT(id) FROM prescription_lens_items WHERE prescription_id = ${rx}`).split(',');
  const r = await a.form('cam-siparis.php', { eylem: 'olustur', supplier_id: sid, 'kalem[]': kalem, not: 'acil' }, 'cam-siparis.php');
  const fis = Number(((r.headers.get('location') || '').match(/id=(\d+)/) || [])[1] || 0);
  const fisHtml = await a.html(`cam-siparis.php?id=${fis}`);
  check('order slip created with WhatsApp text to supplier', fis > 0 && fisHtml.includes('https://wa.me/902125551122?text=') && fisHtml.includes('data-yazdir'));
  await a.form('cam-siparis.php', { eylem: 'gonderildi', fis_id: String(fis), kanal: 'whatsapp', ref: 'LAB-77' }, `cam-siparis.php?id=${fis}`);
  check('sent → lenses become "ordered from depot"', sql(iS.db, `SELECT GROUP_CONCAT(DISTINCT stock_status) FROM prescription_lens_items WHERE cam_siparis_id = ${fis}`) === 'siparis_verildi');
  await a.form('stock.php', { tab: 'siparis', to: 'stokta_var', 'items[]': kalem }, 'stock.php?tab=siparis');
  check('arrival via Depo · Stok completes the slip', sql(iS.db, `SELECT durum FROM cam_siparisleri WHERE id = ${fis}`) === 'tamamlandi');
  sql(iS.db, `INSERT INTO frame_items (brand, model, qty, min_qty, supplier_id, is_active, created_at) VALUES ('DenemeMarka', 'DM-1', 0, 2, ${sid}, 1, NOW())`);
  const o2 = await a.html('stok-oneri.php');
  check('critical frame suggested with quantity', o2.includes('DenemeMarka DM-1') && /<b>2<\/b><\/td><\/tr>/.test(o2));
}

console.log('SGK reconciliation');
{
  sql(iS.db, `INSERT INTO sgk_incoming (user_id, kaynak, baslik, raw_text, parsed, erecete, recete_tarihi, used_order_id, created_at) VALUES (1, 'masaustu', 't', 'x', '{}', '3AB4C5D', CURDATE(), ${orderId}, NOW())`);
  const csrf = (await d.json('masaustu.php?action=durum')).body.csrf;
  const durum = (await d.json('masaustu.php?action=durum')).body;
  const r = await d.json('masaustu.php?action=recete_kontrol', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': durum.csrf }, body: JSON.stringify({ metin: 'Reçete No  Tarih\n3AB4C5D 01.09.2026 12345678901\n9ZZ8Y7X 02.09.2026 99.50', beklenen: { magaza_id: durum.magaza.id, kullanici_id: durum.kullanici.id } }) });
  check('Medula list check: 1 known, 1 missing; T.C./dates ignored', r.body?.ok && r.body.var === 1 && r.body.yok === 1 && r.body.toplam === 2, JSON.stringify(r.body));
  const page = await d.html('sgk-mutabakat.php?kontrol=1');
  check('reconciliation page lists the missing prescription', page.includes('9ZZ8Y7X') && page.includes("OptiFlow'a aktarılmamış (1)"));
  check('list text itself is not stored', sql(iS.db, "SELECT COUNT(*) FROM audit_log WHERE details LIKE '%9ZZ8Y7X%'") === '0');
  const bad = await d.json('masaustu.php?action=recete_kontrol', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': durum.csrf }, body: JSON.stringify({ metin: 'x', beklenen: { magaza_id: 999999, kullanici_id: durum.kullanici.id } }) });
  check('account mismatch → 409', bad.status === 409);
  void csrf;
}

console.log('Barcode + offline snapshot (desktop)');
{
  const r = await d.json(`barkod.php?json=1&kod=${encodeURIComponent('#' + String(orderId).padStart(5, '0'))}`);
  check('order number resolves to the order', r.body?.tur === 'siparis' && r.body.hedef === `order.php?id=${orderId}`);
  sql(iS.db, `UPDATE frame_items SET barcode = '8690000000017' WHERE brand = 'DenemeMarka'`);
  const f = await d.json('barkod.php?json=1&kod=8690000000017');
  check('frame EAN resolves to frame card', f.body?.tur === 'cerceve' && /^cerceve\.php\?duzenle=\d+$/.test(f.body.hedef));
  const g = await d.json(`barkod.php?json=1&kod=${encodeURIComponent('0108690000000017172812311012A\x1d21SN99')}`);
  check('GS1/ÜTS code with GTIN of a stocked frame → frame card', g.body?.tur === 'cerceve');
  const u = await d.html(`barkod.php?kod=${encodeURIComponent('0108699999999999172712311012345')}`);
  check('unknown ÜTS code → parsed GTIN + expiry shown', u.includes('08699999999999') && u.includes('31.12.2027'));
  const x = await d.json('barkod.php?json=1&kod=' + encodeURIComponent('https://evil.example/x'));
  check('unknown code never redirects outside (internal result page only)', x.body?.ok === false && String(x.body?.hedef).startsWith('barkod.php?kod='));
  const o = await d.json('masaustu.php?action=ozet');
  check('offline snapshot: open orders with phone, no raw ids', o.body?.ok && o.body.siparisler.some((s) => s.ad === 'Ayşe Deneme' && s.tel) && !JSON.stringify(o.body).includes('public_token'));
  const w = await a.json('masaustu.php?action=ozet');
  check('offline snapshot refused without the desktop app', w.status === 403);
}

console.log('Cron + isolation');
{
  const bad = await fetch(`${BASE}/cron.php?anahtar=yanlis`);
  check('cron with wrong key → 403', bad.status === 403);
  const panel = await merkez.html('merkez-panel.php');
  const url = (panel.match(/value="([^"]*cron\.php\?anahtar=[a-f0-9]+)"/) || [])[1] || '';
  const key = (url.match(/anahtar=([a-f0-9]+)/) || [])[1] || '';
  const ok = await fetch(`${BASE}/cron.php?anahtar=${key}`);
  const t = await ok.text();
  check('merkez shows cron URL; cron runs all stores', key.length >= 24 && ok.status === 200 && t.startsWith('tamam'), t);
  const b = await login(T);
  const f = await b.html('fatura.php?id=1');
  check('store B cannot open store A invoices (feature closed in B)', f.includes('henüz açılmamış'));
  const m = await b.html('mesajlar.php');
  check("store B's WhatsApp queue does not show A's messages", !m.includes('Ayşe Deneme') && !m.includes('905321234567'));
}

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);

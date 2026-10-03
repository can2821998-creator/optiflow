/**
 * 4.16.1 SGK ay sonu toplu faturası — sunucu entegrasyon testi (gerçek MySQL/MariaDB + PHP).
 * Göç v27; sipariş faturası yalnızca hasta payı; dönem faturası tek fatura + döküm; tekrar faturalanmaz.
 *
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/sgk-fatura-integration.mjs
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


console.log(`OptiFlow 4.16.1 SGK ay sonu faturası @ ${BASE}`);
const S = await newStore('s');
const merkez = new Client();
await merkez.form('merkez-panel.php', { action: 'giris', sifre: MERKEZ });
const html = await merkez.html(`merkez-panel.php?q=${encodeURIComponent(S.email)}`);
const sid = Number((html.match(/merkez-panel\.php\?magaza=(\d+)/) || [])[1] || 0);
const db = ((await merkez.html(`merkez-panel.php?magaza=${sid}`)).match(/(mgz_[a-z0-9_]+)/) || [])[1] || '';
await merkez.form('merkez-panel.php', { action: 'ozellikler', id: String(sid), geri: 'detay', 'ozellik[]': ['efatura'] }, `merkez-panel.php?magaza=${sid}`);
const a = await login(S);
check('göç v27: şema 27, sgk_donem sütunu, bağ tablosu', sql(db, "SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'") === '27'
  && sql(db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'faturalar' AND column_name = 'sgk_donem'") === '1'
  && sql(db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'fatura_sgk_siparisleri'") === '1');

const ids = [];
for (const [ad, teslim] of [['Bir', '2026-09-05 10:00:00'], ['İki', '2026-09-28 16:00:00'], ['Üç', '2026-08-30 12:00:00'], ['Dört', '2026-10-02 09:00:00']]) {
  const r = await a.form('order-new.php', { first_name: ad, last_name: 'Sgkli', phone: '0533 000 00 0' + ids.length, birth_year: '', order_stage: 'siparis_verildi', transaction_type: 'gozluk', lens_type: '', promised_date: '', total_amount: '1.000,00', deposit: '0', deposit_method: 'nakit' });
  const id = Number(((r.headers.get('location') || '').match(/order\.php\?id=(\d+)/) || [])[1] || 0);
  sql(db, `UPDATE orders SET sgk_amount = 150, order_stage = 'teslim_edildi', status = 'teslim_edildi', delivered_at = '${teslim}', medula_islendi_at = '${teslim}', sgk_erecete = '${['1A2B3C4', '5D6E7F8', 'Q1W2E3R', 'Z9Y8X7W'][ids.length]}' WHERE id = ${id}`);
  ids.push(id);
}
check('dört SGK\'lı, Medula\'ya işlenmiş sipariş', ids.every((x) => x > 0));
check('göç v27: orders.medula_islendi_at', sql(db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'medula_islendi_at'") === '1');
let yeni = 0;
{
  const r = await a.form('order-new.php', { first_name: 'Beş', last_name: 'Sgkli', phone: '0533 000 00 09', birth_year: '', order_stage: 'siparis_verildi', transaction_type: 'gozluk', lens_type: '', promised_date: '', total_amount: '1.000,00', deposit: '0', deposit_method: 'nakit' });
  yeni = Number(((r.headers.get('location') || '').match(/order\.php\?id=(\d+)/) || [])[1] || 0);
  let op = await a.html(`order.php?id=${yeni}`);
  check('SGK\'sız siparişte Medula düğmesi yok', !op.includes('name="action" value="medula_islendi"'));
  sql(db, `UPDATE orders SET sgk_amount = 150, sgk_erecete = '9G8H7J6' WHERE id = ${yeni}`);
  op = await a.html(`order.php?id=${yeni}`);
  check('SGK\'lı siparişte "Medula\'ya işlenmedi" ve düğme', op.includes("Medula'ya işlenmedi") && op.includes('name="action" value="medula_islendi"'));
  await a.form('order.php', { action: 'medula_islendi', order_id: String(yeni), medula_tarih: '2026-09-29' }, `order.php?id=${yeni}`);
  check('düğme işlem tarihini yazar', sql(db, `SELECT medula_islendi_at FROM orders WHERE id = ${yeni}`) === '2026-09-29 12:00:00');
  op = await a.html(`order.php?id=${yeni}`);
  check('sipariş sayfasında "Medula\'ya işlendi"', op.includes("Medula'ya işlendi</span>") && op.includes('29.09.2026'));
  await a.form('order.php', { action: 'medula_geri', order_id: String(yeni) }, `order.php?id=${yeni}`);
  check('geri alınır', sql(db, `SELECT IFNULL(medula_islendi_at, 'yok') FROM orders WHERE id = ${yeni}`) === 'yok');
}
sql(db, `INSERT INTO faturalar (order_id, uuid, alici_tip, alici_unvan, alici_kimlik, durum, genel_toplam) VALUES (${ids[0]}, UUID(), 'kurum', 'SGK', '7750409379', 'taslak', 150)`);

{
  const r = await a.form('faturalar.php', { eylem: 'siparisten', siparis: String(ids[1]), sgk_dus: '1' }, 'faturalar.php');
  const fid = Number(((r.headers.get('location') || '').match(/fatura\.php\?id=(\d+)/) || [])[1] || 0);
  check('sipariş faturası: tek taslak, hasta payı 850', sql(db, `SELECT COUNT(*) FROM faturalar WHERE order_id = ${ids[1]}`) === '1' && sql(db, `SELECT CONCAT(alici_tip,'|',genel_toplam) FROM faturalar WHERE id = ${fid}`) === 'kisi|850.00');
  const op = await a.html(`order.php?id=${ids[2]}`);
  check('sipariş sayfasındaki düğme yeni parametreyle', op.includes('name="sgk_dus" value="1"'));
}
{
  // Medula PDF dökümü (örnek: 1A2B3C4, 5D6E7F8, 9G8H7J6)
  const fs = await import('node:fs');
  const pdf = fs.readFileSync(new URL('../../../tests/sgk-fatura/ornek-medula-dokum.pdf', import.meta.url));
  const csrf = await a.csrfFrom('sgk-fatura.php?ay=2026-09');
  const fd = new FormData();
  fd.append('csrf', csrf); fd.append('ay', '2026-09'); fd.append('eylem', 'dokum');
  fd.append('dosya[]', new Blob([pdf], { type: 'application/pdf' }), 'medula-eylul.pdf');
  const up = await a.req('sgk-fatura.php', { method: 'POST', body: fd });
  check('PDF yüklendi', up.status === 303);
  const pk = await a.html('sgk-fatura.php?ay=2026-09');
  check('PDF okundu: 3 e-reçete', pk.includes('3 e-reçete numarası bulundu'));
  check('dökümde olup OptiFlow\'da işaretsiz reçete gösterilir', pk.includes('9G8H7J6') && pk.includes(`order.php?id=${yeni}#medula`));
  check('OptiFlow\'da işaretli, dökümde olmayan', pk.includes('Q1W2E3R'));
  const p = await a.html('sgk-fatura.php?ay=2026-09');
  check('dönem ekranı: Medula ayına göre 3 reçete (biri önceki aydan), ekim girmez', p.includes('3 reçete, 1 önceki aydan') && p.includes('450,00') && !p.includes(`value="${ids[3]}"`));
  check('eski usul taslak uyarısı', p.includes('Eski usulde sipariş bazında'));
  const r = await a.form('sgk-fatura.php', { ay: '2026-09', eylem: 'olustur', 'siparis[]': [ids[0], ids[1], ids[2]], medula_toplam: '' }, 'sgk-fatura.php?ay=2026-09');
  const fid = Number(((r.headers.get('location') || '').match(/fatura\.php\?id=(\d+)/) || [])[1] || 0);
  check('tek SGK faturası: 450,00, dönem 2026-09, TEMELFATURA', sql(db, `SELECT CONCAT(alici_kimlik,'|',genel_toplam,'|',sgk_donem,'|',profil) FROM faturalar WHERE id = ${fid}`) === '7750409379|450.00|2026-09|TEMELFATURA');
  check('KDV dahil tutar ayrıştı (409.09 + 40.91)', sql(db, `SELECT CONCAT(ara_toplam,'|',kdv_toplam) FROM faturalar WHERE id = ${fid}`) === '409.09|40.91');
  check('eski sipariş bazlı SGK taslağı kendiliğinden iptal', sql(db, `SELECT durum FROM faturalar WHERE order_id = ${ids[0]} AND alici_tip = 'kurum'`) === 'iptal');
  const fp = await a.html(`fatura.php?id=${fid}`);
  check('fatura sayfasında dönem ve döküm bağlantısı', fp.includes('SGK dönem faturası · Eylül 2026') && fp.includes(`print.php?type=sgk_dokum&amp;id=${fid}`));
  const dk = await a.html(`print.php?type=sgk_dokum&id=${fid}`);
  check('döküm: 3 reçete, e-reçete numaraları, toplam', dk.includes('REÇETE DÖKÜMÜ') && dk.includes('1A2B3C4') && dk.includes('Q1W2E3R') && dk.includes('450,00'));
  check('dönem tekrar açılınca liste boş', (await a.html('sgk-fatura.php?ay=2026-09')).includes('Faturalanacak reçete yok'));
  await a.form('fatura.php', { id: String(fid), eylem: 'iptal' }, `fatura.php?id=${fid}`);
  check('fatura iptal → reçeteler yeniden listede', (await a.html('sgk-fatura.php?ay=2026-09')).includes('3 reçete'));
  const r2 = await a.form('sgk-fatura.php', { ay: '2026-09', eylem: 'olustur', 'siparis[]': [ids[0], ids[1], ids[2]], medula_toplam: '448,50' }, 'sgk-fatura.php?ay=2026-09');
  const fid2 = Number(((r2.headers.get('location') || '').match(/fatura\.php\?id=(\d+)/) || [])[1] || 0);
  check('Medula toplamıyla fatura', sql(db, `SELECT genel_toplam FROM faturalar WHERE id = ${fid2}`) === '448.50');
  check('ekim dönemi: yalnızca ekim reçetesi', (await a.html('sgk-fatura.php?ay=2026-10')).includes('1 reçete'));
}

console.log(`\n${passed} geçti, ${failed} kaldı`);
process.exit(failed ? 1 : 0);

/**
 * 4.16.0 Garanti — sunucu entegrasyon testi (gerçek MySQL/MariaDB + PHP).
 * Göç v26, teslimde otomatik garanti, liste/arama (MySQL CONCAT), talep akışı, garanti kartı,
 * oturumsuz müşteri sayfaları (garanti.php / durum.php ?m=), mağaza yalıtımı, sipariş silme koruması.
 *
 *   OPTIFLOW_TEST_URL=http://localhost:8080 OPTIFLOW_MERKEZ_SIFRE=… \
 *   OPTIFLOW_TEST_DB_USER=… OPTIFLOW_TEST_DB_PASS=… node tests/server/garanti-integration.mjs
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

console.log(`OptiFlow 4.16.0 garanti @ ${BASE}`);
const S = await newStore('g');
const T = await newStore('h');
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
check('iki deneme mağazası', iS.id > 0 && iT.id > 0 && iS.db && iT.db && iS.db !== iT.db);
check('merkez panelde "garanti" anahtarı var', iS.detay.includes('value="garanti"'));
await merkez.form('merkez-panel.php', { action: 'ozellikler', id: String(iS.id), geri: 'detay', 'ozellik[]': ['garanti'] }, `merkez-panel.php?magaza=${iS.id}`);

const a = await login(S);
const b = await login(T);
check('göç v26: şema ≥ 26 ve tablolar', Number(sql(iS.db, "SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'")) >= 26
  && sql(iS.db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('garantiler','garanti_talepleri')") === '2');
check('varsayılan ayarlar (24 ay, otomatik)', sql(iS.db, "SELECT setting_value FROM app_settings WHERE setting_key = 'garanti_cerceve_ay'") === '24'
  && sql(iS.db, "SELECT setting_value FROM app_settings WHERE setting_key = 'garanti_otomatik'") === '1');
check('özellik kapalı mağazada sayfa açılmaz', (await b.html('garantiler.php')).includes('henüz açılmamış'));
check('menüde Garantiler (açık mağaza)', (await a.html('index.php')).includes('garantiler.php'));

console.log('Teslimde otomatik garanti');
let orderId = 0;
{
  const r = await a.form('order-new.php', {
    first_name: 'Zeynep', last_name: 'Garantili', phone: '0533 765 43 21', birth_year: '',
    order_stage: 'siparis_verildi', transaction_type: 'gozluk', frame_info: 'Ray-Ban RB5154 Siyah', lens_type: 'Antirefle', promised_date: '', total_amount: '3.000,00', deposit: '3.000,00', deposit_method: 'nakit',
  });
  orderId = Number(((r.headers.get('location') || '').match(/order\.php\?id=(\d+)/) || [])[1] || 0);
  check('sipariş açıldı', orderId > 0);
  const op = await a.html(`order.php?id=${orderId}`);
  check('teslimden önce sipariş kartında garanti önerisi', op.includes('id="garanti"') && op.includes('Garanti aç: Çerçeve (24 ay), Cam (24 ay)'));
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'teslim_edildi' }, `order.php?id=${orderId}`);
  check('teslimde iki garanti açıldı', sql(iS.db, `SELECT GROUP_CONCAT(kalem ORDER BY id) FROM garantiler WHERE order_id = ${orderId}`) === 'cerceve,cam');
  const bitis = sql(iS.db, `SELECT bitis FROM garantiler WHERE order_id = ${orderId} AND kalem = 'cerceve'`);
  const beklenen = sql(iS.db, 'SELECT DATE_ADD(CURDATE(), INTERVAL 24 MONTH)');
  check('bitiş = bugün + 24 ay', bitis === beklenen, `${bitis} ${beklenen}`);
  const op2 = await a.html(`order.php?id=${orderId}`);
  check('sipariş kartında garanti numarası ve kart bağlantısı', /G0000\d/.test(op2) && op2.includes(`print.php?type=garanti&amp;order=${orderId}`));
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'hazirlandi' }, `order.php?id=${orderId}`);
  await a.form('order.php', { action: 'set_stage', order_id: String(orderId), stage: 'teslim_edildi' }, `order.php?id=${orderId}`);
  check('ikinci teslimde tekrar açılmaz', sql(iS.db, `SELECT COUNT(*) FROM garantiler WHERE order_id = ${orderId}`) === '2');
}
const gid = Number(sql(iS.db, `SELECT id FROM garantiler WHERE order_id = ${orderId} AND kalem = 'cerceve'`));
const token = sql(iS.db, `SELECT token FROM garantiler WHERE id = ${gid}`);

console.log('Liste, arama, kart');
{
  check('ada göre arama (MySQL CONCAT)', (await a.html('garantiler.php?f=tumu&q=' + encodeURIComponent('zeynep garantili'))).includes('Ray-Ban RB5154'));
  check('telefona göre arama', (await a.html('garantiler.php?f=tumu&q=7654321')).includes('Ray-Ban RB5154'));
  check('30 günde bitecekler boş', (await a.html('garantiler.php?f=bitiyor')).includes('Bu listede garanti yok'));
  const det = await a.html(`garantiler.php?id=${gid}`);
  check('ayrıntı: geçerli + karekod adresi mağaza no ile', det.includes('<b>Geçerli</b>') && det.includes(`garanti.php?m=${iS.id}&amp;k=${token}`));
  const kart = await a.html(`print.php?type=garanti&order=${orderId}`);
  check('garanti kartı: iki kalem, karekod, koşullar', kart.includes('GARANTİ BELGESİ') && (kart.match(/class="track-qr garanti-kalem"/g) || []).length === 2 && kart.includes('<svg') && kart.includes('yetkisiz müdahale'));
  check('başka mağaza bu garantiyi göremez', (await b.html(`garantiler.php?id=${gid}`)).includes('henüz açılmamış'));
}

console.log('Talep akışı');
{
  sql(iS.db, "INSERT INTO suppliers (name, phone, is_active, created_at, updated_at) VALUES ('Lab Optik', '0212 555 44 33', 1, NOW(), NOW())");
  const ted = sql(iS.db, "SELECT id FROM suppliers WHERE name = 'Lab Optik'");
  let r = await a.form('garantiler.php', { garanti_id: String(gid), eylem: 'talep_ekle', sikayet: 'Sap kırıldı' }, `garantiler.php?id=${gid}`);
  check('talep açıldı', r.status === 303 && sql(iS.db, `SELECT durum FROM garanti_talepleri WHERE garanti_id = ${gid}`) === 'acik');
  const tid = sql(iS.db, `SELECT id FROM garanti_talepleri WHERE garanti_id = ${gid}`);
  check('menü rozeti', (await a.html('index.php')).match(/garantiler\.php[\s\S]{0,400}?>1</) !== null);
  await a.form('garantiler.php', { garanti_id: String(gid), talep_id: tid, eylem: 'talep_tedarikci', supplier_id: ted, gonderim: '' }, `garantiler.php?id=${gid}`);
  check('tedarikçiye gönderildi', sql(iS.db, `SELECT CONCAT(durum,'|',supplier_id) FROM garanti_talepleri WHERE id = ${tid}`) === `tedarikcide|${ted}`);
  const det = await a.html(`garantiler.php?id=${gid}`);
  check('WhatsApp bağlantısı tedarikçi numarasına, metinde arıza', det.includes('https://wa.me/902125554433?text=') && det.includes(encodeURIComponent('Sap kırıldı')));
  check('tedarikçi formunda müşteri telefonu yok', !(await a.html(`print.php?type=garanti_talep&id=${tid}`)).includes('765 43'));
  await a.form('garantiler.php', { garanti_id: String(gid), talep_id: tid, eylem: 'talep_kapat', durum: 'tamamlandi', sonuc_tur: 'degisim', sonuc: 'Yeni sap takıldı', maliyet: '85,00' }, `garantiler.php?id=${gid}`);
  check('talep tamamlandı (maliyet DECIMAL)', sql(iS.db, `SELECT CONCAT(durum,'|',sonuc_tur,'|',maliyet) FROM garanti_talepleri WHERE id = ${tid}`) === 'tamamlandi|degisim|85.00');
}

console.log('Müşteri sayfaları (oturumsuz)');
{
  const misafir = new Client('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148');
  let r = await misafir.req(`garanti.php?m=${iS.id}&k=${token}`);
  const h = await r.text();
  check('karekod sayfası mağaza girişi olmadan açılır', r.status === 200 && h.includes('Garanti geçerli') && h.includes('Ray-Ban RB5154'));
  check('müşteri sayfasında talep sonucu', h.includes('Yenisiyle değiştirildi') && h.includes('Yeni sap takıldı'));
  check('KVKK: soyadın tamamı, telefon, maliyet yok', h.includes('Zeynep G.') && !h.includes('Garantili') && !h.includes('765') && !h.includes('85,00'));
  check('müşteriye oturum açılmaz', !(await misafir.html('index.php')).includes('garantiler.php'));
  r = await misafir.req(`garanti.php?m=${iT.id}&k=${token}`);
  check('başka mağazanın no\'suyla anahtar çalışmaz', (await r.text()).includes('Garanti bulunamadı'));
  r = await misafir.req(`garanti.php?m=999999&k=${token}`);
  check('olmayan mağaza: 404', r.status === 404);
  r = await misafir.req(`garanti.php?k=${token}`);
  check('m yoksa eski davranış (mağaza girişine)', [302, 303].includes(r.status) && (r.headers.get('location') || '').includes('magaza-giris'));
  const dt = sql(iS.db, `SELECT public_token FROM orders WHERE id = ${orderId}`);
  r = await misafir.req(`durum.php?m=${iS.id}&k=${dt}`);
  const dh = await r.text();
  check('sipariş takip karekodu da oturumsuz açılır (4.16.0 düzeltmesi)', r.status === 200 && dh.includes('Teslim edildi'), `${r.status} ${dt}`);
  const yok = await misafir.html(`durum.php?m=${iS.id}&k=YANLISANAHTAR1234`);
  check('bulunamadı sayfasındaki "Siparişimi bul" mağazayı taşır', yok.includes(`siparisim-nerede.php?m=${iS.id}`));
  r = await misafir.req(`siparisim-nerede.php?m=${iS.id}`);
  check('"Siparişim nerede" oturumsuz açılır', r.status === 200);
  r = await misafir.req(`bakim.php?m=${iS.id}`);
  check('bakım kartı oturumsuz', r.status === 200);
}

console.log('Koruma');
{
  await a.form('order.php', { action: 'delete_order', order_id: String(orderId) }, `order.php?id=${orderId}`);
  check('garantili sipariş silinemez', sql(iS.db, `SELECT COUNT(*) FROM orders WHERE id = ${orderId}`) === '1');
  await a.form('garantiler.php', { garanti_id: String(gid), eylem: 'durum', durum: 'iptal' }, `garantiler.php?id=${gid}`);
  const misafir = new Client();
  check('iptal edilen garanti müşteriye "İptal edildi"', (await misafir.html(`garanti.php?m=${iS.id}&k=${token}`)).includes('İptal edildi'));
}

console.log(`\n${passed} geçti, ${failed} kaldı`);
process.exit(failed ? 1 : 0);

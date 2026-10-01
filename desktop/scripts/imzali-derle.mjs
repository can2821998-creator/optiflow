/**
 * İMZALI Windows derlemesi (4.12.0 / OptiFlow Pro 5.2).
 *
 * Kod imzalama sertifikası alındığında TEK komut:
 *   node scripts/imzali-derle.mjs            (ya da: npm run dist:win:imzali)
 *
 * İki yol desteklenir; hangisi kullanılacağı ortam değişkenlerinden anlaşılır.
 * Sırlar ASLA depoya yazılmaz; yalnızca CI gizli değişkenlerinden / oturum ortamından okunur.
 *
 * 1) .pfx sertifika (OV/EV — EV çoğunlukla donanım anahtarıyla gelir, bkz. RELEASE.md):
 *      IMZA_TURU=pfx  CSC_LINK=<.pfx yolu veya base64>  CSC_KEY_PASSWORD=<parola>
 *      IMZA_YAYINCI="<sertifikadaki CN, birebir>"
 * 2) Azure Trusted Signing (Microsoft'un bulut imzası):
 *      IMZA_TURU=azure  AZURE_TENANT_ID  AZURE_CLIENT_ID  AZURE_CLIENT_SECRET
 *      AZURE_IMZA_ENDPOINT (ör. https://weu.codesigning.azure.net/)  AZURE_IMZA_HESAP  AZURE_IMZA_PROFIL
 *      IMZA_YAYINCI="<sertifika profilindeki CN>"
 *
 * Bu derleme:
 *   - forceCodeSigning: true  → imzalanamazsa derleme HATA verir (imzasız paket çıkmaz),
 *   - verifyUpdateCodeSignature: true → kurulu uygulama, sonraki güncellemeleri ancak imza
 *     ve yayıncı adı IMZA_YAYINCI ile birebir tutarsa kurar.
 * İlk imzalı sürüm, imzasız eski sürümlerden sorunsuz güncellenir (eski sürüm imza aramaz).
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import yaml from 'js-yaml';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const env = process.env;
const tur = (env.IMZA_TURU || '').toLowerCase();
const yayinci = (env.IMZA_YAYINCI || '').trim();

function dur(mesaj) {
  console.error(`\n✗ ${mesaj}\n`);
  process.exit(1);
}
const eksik = (adlar) => adlar.filter((a) => !env[a] || !String(env[a]).trim());

if (!['pfx', 'azure'].includes(tur)) dur('IMZA_TURU=pfx ya da IMZA_TURU=azure olmalı.');
if (!yayinci) dur('IMZA_YAYINCI boş: sertifikadaki yayıncı adını (CN) birebir yazın.');
if (/^OptiFlow$/i.test(yayinci)) dur('IMZA_YAYINCI yer tutucu değer olamaz; sertifikadaki gerçek CN gerekli.');

const cfg = yaml.load(fs.readFileSync(path.join(root, 'electron-builder.yml'), 'utf8'));
cfg.win.forceCodeSigning = true;
cfg.win.verifyUpdateCodeSignature = true;

if (tur === 'pfx') {
  const e = eksik(['CSC_LINK', 'CSC_KEY_PASSWORD']);
  if (e.length) dur(`Eksik ortam değişkeni: ${e.join(', ')}`);
  cfg.win.signtoolOptions = { ...cfg.win.signtoolOptions, publisherName: [yayinci] };
  delete cfg.win.azureSignOptions;
} else {
  const e = eksik(['AZURE_TENANT_ID', 'AZURE_CLIENT_ID', 'AZURE_CLIENT_SECRET', 'AZURE_IMZA_ENDPOINT', 'AZURE_IMZA_HESAP', 'AZURE_IMZA_PROFIL']);
  if (e.length) dur(`Eksik ortam değişkeni: ${e.join(', ')}`);
  delete cfg.win.signtoolOptions; // Azure ile birlikte kullanılamaz
  cfg.win.azureSignOptions = {
    publisherName: yayinci,
    endpoint: env.AZURE_IMZA_ENDPOINT,
    codeSigningAccountName: env.AZURE_IMZA_HESAP,
    certificateProfileName: env.AZURE_IMZA_PROFIL,
  };
}

const gecici = path.join(root, 'release', '.imzali-yapilandirma.json');
fs.mkdirSync(path.dirname(gecici), { recursive: true });
fs.writeFileSync(gecici, JSON.stringify(cfg, null, 2)); // sır içermez (yalnızca yayıncı adı ve Azure hesap adları)

const calistir = (komut, args) => {
  const r = spawnSync(komut, args, { cwd: root, stdio: 'inherit', shell: process.platform === 'win32' });
  if (r.status !== 0) dur(`${komut} ${args.join(' ')} başarısız (${r.status}).`);
};
calistir('node', ['scripts/build.mjs', '--env=production']);
calistir('npx', ['electron-builder', '--win', '--x64', '--publish', 'never', '--config', gecici]);
fs.rmSync(gecici, { force: true });

console.log(`\n✓ İmzalı kurulum hazır (yayıncı: ${yayinci}). release/ içindeki exe + blockmap + latest.yml'yi yükleyin.`);
if (process.platform === 'win32') {
  console.log('  Doğrulama: powershell "Get-AuthenticodeSignature release\\OptiFlow-Pro-Setup-*.exe | Format-List"');
}

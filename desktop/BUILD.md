# Derleme ve test

## Gereksinimler

- Node.js 22+
- Windows paketi için: Windows 10/11 (veya NSIS'i çalıştırabilen bir CI ortamı)
- PHP çözümleyici testleri için: PHP 8 CLI + `mbstring` (yoksa bu testler atlanır)
- Sunucu ve E2E testleri için: tek kullanımlık bir OptiFlow test kurulumu (PHP + MySQL/MariaDB)

## Kurulum

```bash
cd desktop
npm install
```

## Ortamlar

| Ortam | Dosya | Sunucu | Not |
|---|---|---|---|
| development | `config/development.json` | `http://localhost:8080` | http yalnızca burada ve yalnızca localhost; ortam değişkenleri geçerli |
| staging | `config/staging.json` | `https://test.optiflow.com.tr` | beta güncelleme kanalı |
| production | `config/production.json` | `https://optiflow.com.tr` | ortam değişkenleri **yok sayılır** |

Yapılandırma derleme anında pakete gömülür (`scripts/build.mjs`). Geçersiz değerler
(https olmayan sunucu, izinli olmayan okuma sunucusu, http güncelleme adresi…) uygulamayı
başlatmaz. **Bu dosyalara asla şifre veya anahtar konmaz** (test denetler).

Geliştirme derlemesinde kullanılabilen değişkenler: `OPTIFLOW_BASE_URL`, `MEDULA_HOME_URL`, `LOG_LEVEL`.

## Komutlar

| Komut | Ne yapar |
|---|---|
| `npm start` | Geliştirme derlemesi + Electron |
| `npm run build` / `build:staging` / `build:production` | Yalnızca `dist/` |
| `npm run typecheck` | TypeScript (strict) |
| `npm test` | Birim, güvenlik, eski eklentiyle eşlik, PHP çözümleyici regresyonu |
| `npm run test:server` | Sunucu entegrasyonu (aşağıya bakın) |
| `npm run test:e2e` | Gerçek Electron uçtan uca (aşağıya bakın) |
| `npm run pack:dir` | Kurulum paketi olmadan açılmış uygulama klasörü (`release/`) |
| `npm run dist:win` | İmzasız Windows kurulumu — **yalnızca test** |
| `npm run release:win` | İmzalı kurulum + yayın (bkz. RELEASE.md) |

## Testler

### 1. Birim ve güvenlik (`npm test`) — 72 test

- `tests/unit/` — okuyucu, tespit, köken politikası, IPC doğrulama, API istemcisi, yeniden deneme, maskeleme, dosya adı
- `tests/legacy-parity.test.ts` — **orijinal** `kopru-eklenti/icerik.js` ile aynı metin
- `tests/parser/` — okuyucu çıktısı → gerçek `app/sgk.php`
- `tests/security/` — webPreferences ve kaynak taraması

### 2. Sunucu entegrasyonu — 28 test

Tek kullanımlık bir test kurulumu gerekir. MySQL kullanıcısının `CREATE DATABASE` yetkisi olmalı
(mağazalar kendiliğinden açılsın) ve `config.php` içinde `merkez_admin_password` tanımlı olmalı.

```bash
OPTIFLOW_TEST_URL=http://127.0.0.1:8081 OPTIFLOW_MERKEZ_SIFRE=<test şifresi> npm run test:server
```

İki sentetik mağaza açar; oturum, CSRF, hesap bağlama, mağaza izolasyonu, destek modu ve
eski eklenti uç noktasını sınar. **Canlı sunucuda çalıştırmayın.**

### 3. Uçtan uca (Electron) — 36 test

Gerçek uygulama kodu, gerçek Electron, test sunucusu; Medula yalnızca bu testte sentetik
sayfalarla taklit edilir (`tests/e2e/e2e-main.ts`, üründe böyle bir kod yok).

```bash
node scripts/build.mjs
OPTIFLOW_TEST_URL=http://127.0.0.1:8081 node tests/e2e/build-e2e.mjs
E2E_EMAIL=<test mağaza e-postası> E2E_USER=<personel kullanıcı adı> \
  npx electron dist-e2e/e2e-main.js          # Linux CI: xvfb-run -a npx electron …
```

İsteğe bağlı `E2E_SCREENSHOT=/klasör/önek` ekran görüntüsü kaydeder.
Linux'ta root olarak çalıştırmayın (Chromium sandbox'ı root'ta açılmaz); ayrı bir kullanıcı kullanın.

### Test sayfaları

`tests/fixtures/` tamamen **sentetiktir** (bkz. `_README.md`). Okuyucuyu bilerek değiştirdiyseniz
altın çıktıları `node tests/fixtures/regenerate.mjs` ile yenileyin ve farkı gözden geçirin.

## Paketleme

`electron-builder.yml`: NSIS, x64, **kullanıcı başına kurulum (yönetici izni gerekmez)**,
Başlat menüsü ve masaüstü kısayolu, kaldırma desteği, Türkçe kurulum ekranı, çıktı
`release/OptiFlow-Setup-<sürüm>.exe`. Pakete yalnızca `dist/`, ikon ve `package.json` girer
(kaynak kod ve testler girmez; doğrulandı).

**ARM64:** Şimdilik üretilmiyor. Windows on ARM x64 uygulamaları öykünmeyle çalıştırır; mağaza
bilgisayarlarında ARM yaygın değil. Gerekirse `win.target[0].arch`'a `arm64` eklemek yeterli.

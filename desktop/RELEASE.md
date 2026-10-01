# Sürüm ve dağıtım

> **Güncel durum (25.09.2026):** Güncellemeler OptiFlow hosting'inde,
> `https://optiflow.com.tr/indir/masaustu/` klasöründen dağıtılır (sunucu paketindeki
> `indir/masaustu/`). Kod imzalama sertifikası alınana kadar güncelleme **imza doğrulaması
> kapalıdır** (yalnızca HTTPS + sha512). Sertifika alınınca `electron-builder.yml` içinde
> `forceCodeSigning` ve `verifyUpdateCodeSignature` tekrar `true` yapılır.
>
> **5.2.0 (4.12.0 sunucu):** barkod okuyucu modu, çevrimdışı salt okunur kopya, Medula "Listeyi kontrol et",
> imzalı derleme hattı. Sunucu 4.12.0'dan eski ise yeni düğmeler görünmez (özellik haritası gelmez); uygulama
> eski sunucuyla da çalışır.
>
> **Otomatik güncelleme 5.1.0 ile başlar.** 5.0.0'da güncelleme kapalıydı; 5.0.0 kullanan
> mağazalar 5.1.0'ı bir kez elle kurar, sonrakiler "Güncellemeleri denetle" ile gelir.

## 0. Kod imzalama (sertifika gelince — 5.2.0 ile hazır)

İmzasız kurulum Windows SmartScreen uyarısı verir ve güncellemeler yalnızca HTTPS + sha512 ile
doğrulanır. Sertifika alınınca **tek komutla** imzalı derleme yapılır; ek yapılandırma gerekmez:

```bash
npm run dist:win:imzali      # = node scripts/imzali-derle.mjs
```

Script, `electron-builder.yml` üzerine şunları uygular: `forceCodeSigning: true` (imzalanamazsa derleme
HATA verir) ve `verifyUpdateCodeSignature: true` (kurulu uygulama sonraki güncellemeyi ancak imza ve
yayıncı adı birebir tutarsa kurar). İlk imzalı sürüm, imzasız eski sürümlerden sorunsuz güncellenir.

| Yol | Ortam değişkenleri | Not |
|---|---|---|
| **.pfx (OV)** | `IMZA_TURU=pfx`, `CSC_LINK` (.pfx yolu ya da base64), `CSC_KEY_PASSWORD`, `IMZA_YAYINCI` | Linux/macOS'ta osslsigncode, Windows'ta signtool |
| **EV sertifika (USB anahtar)** | Windows'ta, anahtar takılıyken signtool ile; `IMZA_TURU=pfx` yerine sağlayıcının imza aracı | EV genelde bulut/HSM ile gelir; sağlayıcı belgesine bakın |
| **Azure Trusted Signing** | `IMZA_TURU=azure`, `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_IMZA_ENDPOINT`, `AZURE_IMZA_HESAP`, `AZURE_IMZA_PROFIL`, `IMZA_YAYINCI` | Yalnızca Windows'ta (PowerShell); GitHub iş akışı hazır |

- `IMZA_YAYINCI` sertifikadaki **CN ile birebir** aynı olmalı (ör. `"Poyraz Optik Ltd. Şti."`). Yer tutucu
  değerle script çalışmaz.
- **Sırlar depoya asla yazılmaz**: yalnızca oturum ortamından ya da GitHub › Settings › Secrets'tan okunur.
- GitHub: `.github/workflows/optiflow-pro-surum.yml` (Actions › "OptiFlow Pro sürümü" › Run workflow)
  Windows'ta derler, `Get-AuthenticodeSignature` ile imzayı doğrular, exe + blockmap + latest.yml'yi
  artifact olarak verir.
- Doğrulama (Windows): `powershell "Get-AuthenticodeSignature release\OptiFlow-Pro-Setup-*.exe | fl"` → `Status: Valid`.
- Yerel test (5.2.0): kendinden imzalı sertifikayla script'in `forceCodeSigning` yolu ve Azure
  yapılandırması doğrulandı; gerçek imza sertifika geldiğinde Windows'ta atılır.

## 1. Sunucu tarafı (önce!)

Masaüstü uygulaması `masaustu.php`'ye ihtiyaç duyar. **OptiFlow 4.10.0'ı önce sunucuya yükleyin.**

1. Yedek alın (dosyalar + veritabanları).
2. Paketi yükleyin (`config.php` ve `storage/` korunur). Şema değişikliği yok.
3. Kontrol: `https://<site>/masaustu.php?action=durum` oturumsuz açıldığında
   `{"ok":false,"kod":"magaza_oturumu_yok"…}` dönmeli.
4. **Eklenti kullanan mağazalara duyurun:** eklenti ayarındaki köprü adresi bir kez güncellenmeli
   (SGK reçete aktar sayfasında yeni adres `…&m=<no>`).

## 2. Masaüstü sürümü

```bash
cd desktop
# package.json → "version" artırın (ör. 5.0.1). Güncelleme yalnızca daha yüksek sürüme yapılır.
npm ci
npm run typecheck && npm test
OPTIFLOW_TEST_URL=… npm run test:server        # staging kurulumunda
# E2E (bkz. BUILD.md)
npm run release:win                              # imzalı derler ve yayınlar
```

`release:win` şunları üretir ve `publish` adresine yükler:
`OptiFlow-Setup-<sürüm>.exe`, `.blockmap`, `latest.yml`.

**Kanallar:** `latest` (canlı) ve `beta` (staging). Önce staging derlemesini birkaç mağazada deneyin
(`config/staging.json` → `updateChannel: "beta"`).

## 3. Kullanıcı tarafı güncelleme

- Uygulama açıldıktan 15 sn sonra ve 6 saatte bir denetler.
- Yeni sürüm varsa araç çubuğunda **Güncelleme x.y.z** düğmesi çıkar; indirme kullanıcı tıklayınca başlar.
- İndirme bitince **Yeniden başlat ve güncelle**. Kullanıcı onayı olmadan kurulum yok.
- İmza/yayıncı doğrulanamazsa güncelleme kurulmaz; kayıtlara `updater.error` yazılır.

## 4. İlk kurulum (mağaza)

1. `OptiFlow-Setup-<sürüm>.exe` çalıştırılır; yönetici izni istemez.
2. Başlat menüsünden **OptiFlow** açılır → mağaza girişi → kullanıcı girişi (web ile aynı).
3. **SGK · Medula** sekmesi → Medula'ya personel kendisi giriş yapar.
4. Reçete detayı → **Reçeteyi aktar** → önizleme → onay.
5. Eklenti artık gerekmiyorsa Chrome'dan kaldırılabilir.

## 5. Geri alma

- Masaüstü: önceki kurulum dosyasını çalıştırın (aynı klasöre kurulur; oturumlar korunur).
  Güncelleme sunucusunda `latest.yml`'i önceki sürüme geri çevirin (otomatik sürüm düşürme kapalıdır;
  yalnızca yeni kurulumları etkiler).
- Sunucu: 4.9.0 dosyalarına dönmek masaüstü aktarımını durdurur **ve** 4.9.0'daki giriş/köprü
  hatalarını geri getirir. Önerilmez; sorun varsa yalnızca ilgili dosyayı düzeltin.

## 6. Sürüm öncesi kontrol

- [ ] `package.json` sürümü artırıldı
- [ ] `config/production.json` doğru sunucu ve güncelleme adresi
- [ ] `publisherName` sertifikayla aynı
- [ ] Birim + sunucu + E2E testleri geçti
- [ ] Geçiş raporundaki elle test listesi bir Windows 10 ve bir Windows 11 makinede yapıldı
- [ ] Sunucu 4.10.0'da

# Güvenlik

Bu uygulama sağlık ve reçete bilgisi gösterir. Öncelik sırası (spec §63): hasta gizliliği →
mağaza izolasyonu → SGK kimlik bilgisi güvenliği → işlevsel güvenilirlik → geriye uyumluluk.

## 1. Güven sınırları

| Bileşen | Güven | Ne yapabilir | Ne yapamaz |
|---|---|---|---|
| **OptiFlow sunucusu** | Güvenilir (bizim) | Kimlik doğrulama, mağaza seçimi, çözümleme, kayıt | — |
| **OptiFlow renderer** (uzak sayfa) | Yarı güvenilir | Yalnızca kendi kökeninde `window.optiflowDesktop`: Medula'yı aç, aktarımı başlat, durum oku | Node, dosya sistemi, IPC, çerez, Medula içeriği, hasta özeti |
| **Ana süreç** | Güvenilir | Tüm kararlar, ağ çağrıları, doğrulama | — |
| **Preload'lar** | Güvenilir kod, güvenilmeyen sayfada | Dar, sabit API | Genel `invoke/send`, `ipcRenderer` açmak |
| **Medula sayfası** (üçüncü taraf) | **Güvenilmez** | Kendi içinde çalışmak | Node, IPC, OptiFlow API'si, araç çubuğu, SGK dışı gezinme; okuyucuyu tetiklemek |
| **Dış web siteleri** | Güvenilmez | Varsayılan tarayıcıda açılır (yalnızca http/https/mailto/tel) | Uygulama içinde açılmak |
| **Yerel işletim sistemi** | Kullanıcının | İndirmeler yalnızca kullanıcının seçtiği yere | Sessiz dosya yazma |
| **Güncelleme altyapısı** | HTTPS + sha512 (imza sertifikası gelene kadar); imzalı derlemede imza + yayıncı | Doğrulanan kurulum | Farklı yayıncılı paket (imzalı derlemede) |
| **Çevrimdışı kopya penceresi** (yerel sayfa, 4.12.0) | Güvenilir kod | Yalnızca kopyayı okumak (tek IPC kanalı) | Gezinme, açılır pencere, ağ, Node |

## 2. Renderer ayarları

Tüm renderer'lar (araç çubuğu, OptiFlow, Medula, açılır pencereler):

```
nodeIntegration: false      nodeIntegrationInWorker: false
contextIsolation: true      sandbox: true (ayrıca app.enableSandbox())
webSecurity: true           allowRunningInsecureContent: false
webviewTag: false           devTools: yalnızca paketlenmemiş derlemede
```

`nodeIntegrationInSubFrames: true` yalnızca Medula görünümündedir ve **Node vermez**; sandbox
açıkken yalnızca preload'un alt çerçevelerde de (yalıtılmış dünyada) yüklenmesini sağlar.
`tests/security/static-security.test.ts` bu değerleri ve kaynakta yasak kalıpları
(`nodeIntegration: true`, `eval`, `BrowserView`, `@electron/remote`, genel `ipcMain.on`) denetler.

## 3. IPC

- Genel kanal yok. Her renderer'ın kendi dar kanal kümesi var (`src/shared/constants.ts`).
- Dinleyiciler `ipcMain`'e değil **ilgili `webContents.ipc`'ye** bağlıdır; açılır pencere veya
  Medula, OptiFlow kanallarına ulaşamaz.
- OptiFlow çağrıları: gönderen `webContents` + üst çerçeve + URL'nin OptiFlow kökeninde olması kontrol edilir.
- Araç çubuğu komutları beyaz listededir (`SHELL_COMMANDS`) ve ana süreçte yeniden doğrulanır.
- Medula yanıtları: ana sürecin ürettiği UUID + gönderen çerçeve belirteci + süreç no + köken + şema
  doğrulanır; boyut sınırlıdır (metin ≤ 1 000 000 karakter).

## 4. Gezinme ve açılır pencereler

Tek karar noktası: `src/main/bridge/origin-policy.ts` (birim testli).

| Görünüm | İzin | Yönlendirilen | Engellenen |
|---|---|---|---|
| OptiFlow | Yapılandırılmış köken + taban yolu, https | SGK adresi → Medula görünümü; http/https/mailto/tel → varsayılan tarayıcı | `file:`, `javascript:`, `data:`, özel protokoller, yönlendirmeyle dışarı çıkış |
| Medula | İzinli SGK sunucuları, https, varsayılan port | — | Diğer her şey (kullanıcıya uyarı); alt çerçevede https dışı |
| Araç çubuğu | Hiçbir gezinme | — | Hepsi |

- Açılır pencereler aynı oturumu paylaşır ama **yetkisizdir**: preload argümanları (güvenilir köken,
  okuma sunucuları) verilmez → `window.optiflowDesktop` tanımsız (E2E ile doğrulandı).
- `<webview>` her yerde engelli. Sertifika hataları **asla** kabul edilmez.
- İzinler: kamera/mikrofon/konum/USB/HID/ekran yakalama reddedilir. OptiFlow'a yalnızca pano yazma,
  bildirim, tam ekran; Medula'ya yalnızca pano yazma.

## 5. Kimlik doğrulama ve mağaza izolasyonu

- Masaüstünde **köprü anahtarı yok**. İstekler ana süreçten OptiFlow bölümü üzerinden gider;
  Chromium mevcut `httpOnly` PHP oturum çerezini ekler. Uygulama çerezi okumaz, kopyalamaz, saklamaz.
  Dolayısıyla Windows DPAPI ile saklanacak bir sır da yoktur.
- `POST` için CSRF zorunlu (`bootstrap.php` → `csrf_check`, `X-CSRF-Token`).
- Mağaza, **sunucu oturumundan** gelir (`tenant_gereksin()` her istekte mağazanın kendi veritabanını
  seçer). Her mağaza ayrı veritabanında olduğundan sızıntı yapısal olarak imkânsızdır.
- İstemci, aktarımdan hemen önce gördüğü mağaza/kullanıcıyı `beklenen` olarak yollar; oturum arada
  değiştiyse 409 (ör. başka bir sekmede farklı mağazaya geçilmişse reçete yanlış mağazaya düşmez).
- **Merkez destek oturumunda (impersonate) aktarım kapalıdır** (403): reçete, destek personelinin
  girdiği mağazaya yanlışlıkla yazılmaz.
- `sgk-aktar.php?gelen=ID` yalnızca **kullanıcının kendi** kaydını açar (`user_id = ?`).
- Testler (`tests/server/api-integration.mjs`): B mağazası A'nın kaydını açamaz, listesinde göremez;
  A'nın eklenti anahtarı B mağazasında geçmez; bilinmeyen mağaza ile yanlış anahtar aynı yanıtı alır.

## 6. Gizlilik

- **Reçete okuma** şifre alanlarının değerine erişmez (birim testi). SGK'ya uygulamadan istek atılmaz.
- **Medula şifresini kaydetme (5.4.0, Chrome'un şifre yöneticisi gibi):**
  - Kullanıcı Medula giriş ekranında “Giriş”e bastığında kullanıcı adı ve şifre alanlarının o anki değeri
    okunur ve YALNIZCA ana sürecin belleğine gider (`medula:giris-yakalandi`; gönderen çerçevenin SGK
    adresi ve kökeni yeniden denetlenir, değerler `parseGirisYakalandi` ile doğrulanır, kayda yazılmaz).
  - Giriş ekranı kapanırsa (giriş başarılı) kullanıcıya sorulur: **Kaydet / Şimdi değil / Bu bilgisayarda
    sorma**. Kaydet denmezse değer bellekten atılır; 3 dakika içinde sonuç gelmezse de atılır.
  - Kayıt `userData/medula-giris.bin` dosyasında, YALNIZCA `safeStorage` (Windows DPAPI, Windows hesabına
    bağlı) ile şifreli durur. Şifreleme yoksa hiç kaydedilmez. OptiFlow sunucusuna, kayıtlara (log),
    tanılama dışa aktarımına ve OptiFlow sayfasına asla gitmez.
  - Doldurma: giriş ekranında kullanıcı adı ve şifre alanları BOŞSA doldurulur; kullanıcının yazdığının
    üzerine yazılmaz. **Güvenlik kodu (resimdeki kod) ve KVKK onayı asla doldurulmaz/işaretlenmez**;
    “Giriş”e kullanıcı basar. Yani otomatik oturum açma yok, yalnızca otomatik doldurma var.
  - Şifre değiştirme ekranında yeni şifre iki kez aynı yazılırsa kayıt “güncellensin mi?” diye sorulur.
  - Silme: Menü › SGK / Medula › **Kayıtlı Medula şifresi… › Sil**. Teklifi kapatma: aynı menüde
    “Medula şifresini kaydetmeyi öner”.
- **OptiFlow “Beni hatırla” (4.18.0):** sunucu, isteğe bağlı olarak cihaza 30 gün geçerli (kullandıkça
  uzayan) bir çerez verir: `seçici:doğrulayıcı`, sunucuda yalnızca doğrulayıcının SHA-256 özeti tutulur.
  Mağaza ya da personel şifresi değişince, “Çıkış”ta ve profildeki “Tüm cihazlarda unut” ile geçersizleşir.
  Masaüstünde çerez `persist:optiflow` bölümünde kalır (OptiFlow şifresi uygulamada saklanmaz).
- Okuma yalnızca kullanıcının düğmeye basmasıyla. Arka planda yalnızca **içerik içermeyen** bir
  yoklama yapılır (araç çubuğundaki “Reçete ekranı” rozeti için).
- Okunan metin yalnızca bellekte, en fazla 15 dakika (bkz. MEDULA-BRIDGE §8).
- Masaüstü kayıtlarına hasta adı, T.C., reçete metni, telefon, çerez, CSRF, anahtar yazılmaz; her satır
  ayrıca `redact()`'ten geçer (T.C. son 2 hane hariç maskelenir, `«…»` içerikleri silinir, URL
  sorgu dizeleri atılır). E2E testi kayıt dosyasını tarar.
- Sunucu işlem geçmişine (`audit`) masaüstü aktarımı için yalnızca bulunan alan sayısı ve istemci sürümü yazılır.
- Medula ve reçete ekranlarında analitik/telemetri yok. Çökme raporlama yok.
- Medula çerezleri `persist:medula` bölümünde; Menü › SGK / Medula › **Medula oturumunu temizle**.
- **Liste kontrolü (4.12.0):** Medula reçete listesinden frame dışına YALNIZCA e-reçete numaraları çıkar
  (4–12 karakter, harf+rakam; ana süreçte `parseNumaralar` ile yeniden doğrulanır). Ad, T.C., tarih,
  tutar okunmaz. Sunucu numaraları yalnızca oturumda 30 dk tutar; işlem geçmişine yalnızca adet yazılır.
- **Çevrimdışı kopya (4.12.0, merkezden açılır):** açık siparişlerin no/ad/telefon/aşama/teslim sözü
  (yetki varsa kalan). Diskte YALNIZCA `safeStorage` (Windows DPAPI) ile şifreli; şifreleme yoksa
  yalnızca bellekte. 7 günden eskisi kullanılmaz; çıkış, mağaza/kullanıcı değişimi, özelliğin
  kapanması → silinir. Pencerede tüm içerik `textContent` ile yazılır.
- **Barkod modu:** okutulan kod yalnızca bu kurulumun içindeki göreli bir sayfaya yönlendirir.

## 7. Gizli bilgiler

Pakette **yok**: veritabanı şifresi, merkez şifresi, imza anahtarı, sunucu sırları, kalıcı ana anahtar.
`config/*.json` yalnızca herkese açık adresler içerir (test bunu denetler). İmza sertifikası
CI gizli deposundan gelir (`CSC_LINK`, `CSC_KEY_PASSWORD`).

## 8. Güncelleme güvenliği

- Yalnızca `https` besleme adresi (yapılandırma doğrulaması).
- `verifyUpdateCodeSignature: true` + `publisherName`: indirilen kurulumun Authenticode imzası
  ve yayıncı adı doğrulanmadan kurulmaz.
- `forceCodeSigning: true`: sürüm derlemesi imzasız paket üretemez.
- Kullanıcı onayı olmadan indirme veya kurulum yapılmaz. Sürüm düşürme kapalı.

## 9. Güvenlik kontrol listesi

- [x] Uzak içerikte `nodeIntegration` kapalı, `contextIsolation` açık, `sandbox` açık
- [x] Genel IPC yok; kanallar webContents'e özel ve beyaz listeli; yükler doğrulanıyor
- [x] Tek merkezî köken politikası; birebir sunucu adı eşleşmesi
- [x] Medula sayfası Node / OptiFlow API / araç çubuğu API'sine erişemiyor (E2E)
- [x] Açılır pencereler yetkisiz (E2E)
- [x] `file:` / `javascript:` / SGK dışı gezinme engelli (E2E)
- [x] Sertifika hataları reddediliyor
- [x] Reçete okuma şifre alanı değerini okumuyor (birim testi); Medula şifresi yalnızca kullanıcı “Kaydet” derse, DPAPI ile, yalnızca bu bilgisayarda (5.4.0, birim testi)
- [x] Aktarım yalnızca kullanıcı eylemiyle
- [x] Kayıtlarda hasta bilgisi yok (E2E)
- [x] Mağazalar arası erişim yok; destek oturumunda aktarım kapalı (sunucu testi)
- [x] Pakette sır yok; imzasız sürüm derlemesi engelli
- [ ] **Canlıda:** Medula'nın gerçek alan adları ve çerçeve yapısı
- [ ] **Canlıda:** kod imzalama sertifikası alınıp `npm run dist:win:imzali` ile derlenmeli; o zamana
      kadar güncelleme imza doğrulaması KAPALI (ürün sahibinin kararı, 25.09.2026) — güncelleme
      klasörüne yazma yetkisi olan herkes uygulamaya sürüm gönderebilir; hosting şifresini koruyun.
- [x] 4.12.0: liste kontrolü yalnızca numara taşır; çevrimdışı kopya DPAPI ile şifreli; yeni pencere yetkisiz.

## 10. Sunucu tarafı (4.12.0 modülleri)

- **Özellikler** merkezden, her istekte taze okunur (`magazalar.ozellikler`); tarayıcıdan değiştirilemez.
- **Entegrasyon sırları** (PayTR key/salt, WhatsApp token) `storage/.gizli-anahtar` ile şifrelenir
  (libsodium secretbox; yoksa AES-256-GCM). Ekranda yalnızca son 4 karakter görünür; boş alan eskisini korur.
- **PayTR bildirimi** (`odeme-bildirim.php`, oturumsuz): mağaza `callback_id`'den bulunur, kayıt ancak
  o mağazanın anahtarıyla HMAC doğrulandıktan sonra yazılır (`hash_equals`). Satır kilidiyle tek sefer;
  TEST modunda tahsilat yazılmaz. Siparişe yazılan tutar linkin kendi tutarıdır.
- **cron.php**: gizli anahtar (`hash_equals`), merkez kilidiyle tek çalışma; şeması eski mağazayı atlar;
  mağaza değiştirirken ayar önbelleği sıfırlanır.
- **Dış istekler** yalnızca https, TLS doğrulaması açık, yönlendirme takibi yok, yanıt ≤ 2 MB.

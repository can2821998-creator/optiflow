# Mimari

## 1. Teknoloji kararı

**Electron + TypeScript**, mevcut PHP sunucusu olduğu gibi kalır. Kaynak incelemesinden sonra
alternatifler değerlendirildi:

| Seçenek | Karar | Gerekçe |
|---|---|---|
| **Electron** | ✅ | Eklentinin içerik betiğiyle aynı modeli verir: her Medula çerçevesinde, sayfanın göremediği yalıtılmış bir ortamda kod çalıştırma (`nodeIntegrationInSubFrames` + `contextIsolation` + `sandbox`). Ayrı oturum bölümleri (`persist:medula`), ağ katmanında istek başlığı ayarı, imzalı otomatik güncelleme (`electron-updater`) hazır. Chromium sürümü sabit → Medula uyumluluğu Chrome ile aynı. |
| C# + WebView2 | ❌ | Çerçeve başına yalıtılmış betik ve çerçeveye özgü IPC mümkün ama daha fazla elle kod gerektirir; ekip TypeScript/PHP biliyor. Belirleyici bir üstünlüğü yok. |
| Tauri | ❌ | Windows'ta WebView2 kullanır; çerçeve içi yalıtılmış betik ve çerçeve kimliği doğrulama zayıf. Paket küçük ama spesifikasyondaki öncelik sırasında boyut en sonda. |

Masaüstü paketi ~100 MB (Chromium dahil). Bu, öncelik sırasına göre kabul edilebilir (spec §63).

## 2. Süreçler ve görünümler

```
┌──────────────── BrowserWindow (OptiFlow) ─────────────────────────────┐
│  Araç çubuğu — yerel file:// sayfa (dist/shell), katı CSP             │
│  preload: shell-preload  → optiflowShell.komut(<beyaz liste>)          │
├───────────────────────────────┬───────────────────────────────────────┤
│  WebContentsView: OptiFlow    │  WebContentsView: Medula              │
│  partition: persist:optiflow  │  partition: persist:medula            │
│  https://optiflow.com.tr      │  https://gss.sgk.gov.tr               │
│  preload: optiflow-preload    │  preload: medula-preload              │
│  → window.optiflowDesktop     │  (her çerçevede, yalıtılmış dünyada;  │
│    (yalnızca bu kökende)      │   sayfaya HİÇBİR şey açmaz)           │
└───────────────┬───────────────┴───────────────┬───────────────────────┘
                │ IPC (webContents'e özel)      │ IPC (çerçeveye özel, istek/yanıt)
                ▼                               ▼
        ┌─────────────────── Ana süreç (Node) ──────────────────┐
        │ app-controller · origin-policy · medula-controller     │
        │ transfer-service (session.fetch, çerezli) · logging    │
        └───────────────────────────┬────────────────────────────┘
                                    │ HTTPS (OptiFlow oturum çerezi + CSRF)
                                    ▼
                    OptiFlow sunucusu: masaustu.php → sgk_parse() → sgk_incoming
```

Görünüm düzeni: **sekmeli** (varsayılan; OptiFlow ↔ Medula) veya **yan yana** (Ctrl+3).
Medula görünümü ilk açılışta oluşturulur; çökerse OptiFlow oturumu etkilenmez.

## 3. Klasör yapısı

```
desktop/
├── config/                     development / staging / production (gizli bilgi YOK)
├── scripts/build.mjs           esbuild; config derleme anında gömülür
├── electron-builder.yml        NSIS, kullanıcı başına kurulum, imza, güncelleme
├── src/
│   ├── main/
│   │   ├── main.ts             giriş noktası (tek örnek kilidi, sertifika hatası reddi)
│   │   ├── app-controller.ts   pencere, görünümler, durum, aktarım akışı
│   │   ├── navigation.ts       gezinme / yönlendirme / açılır pencere kuralları
│   │   ├── security.ts         webPreferences fabrikaları
│   │   ├── sessions.ts         bölümler, izinler, kullanıcı aracısı
│   │   ├── downloads.ts        "Farklı kaydet", dosya adı temizliği
│   │   ├── updater.ts          electron-updater (imza doğrulamalı)
│   │   ├── logging.ts          maskelenmiş günlük + tanılama dışa aktarma
│   │   ├── menu.ts             Türkçe uygulama menüsü
│   │   ├── ipc-validation.ts   tüm IPC yüklerinin doğrulaması
│   │   ├── filename.ts, config.ts
│   │   └── bridge/
│   │       ├── origin-policy.ts      TEK adres/köken politikası
│   │       ├── medula-controller.ts  çerçeve yoklama + okuma
│   │       ├── frame-selection.ts    doğru çerçeveyi seçme
│   │       ├── transfer-service.ts   OptiFlow API istemcisi
│   │       └── retry-store.ts        yalnızca bellekte, 15 dk
│   ├── medula/                 extractor.ts (icerik.js'in birebir karşılığı), detector.ts, fields.ts
│   ├── preload/                shell / optiflow / medula preload'ları
│   ├── shell/                  araç çubuğu (HTML/CSS/TS)
│   └── shared/                 sabitler, türler, Türkçe mesajlar, maskeleme
└── tests/
    ├── unit/ security/         vitest
    ├── legacy-parity.test.ts   ORİJİNAL icerik.js ile birebir aynı metin
    ├── parser/                 gerçek app/sgk.php (PHP CLI) ile regresyon
    ├── server/                 canlı test sunucusuna entegrasyon (iki mağaza, izolasyon)
    ├── e2e/                    gerçek Electron + test sunucusu + sentetik Medula
    └── fixtures/               SENTETİK Medula sayfaları (gerçek hasta verisi yok)
```

## 4. Aktarım akışı

1. Kullanıcı **Reçeteyi aktar**'a basar (araç çubuğu ya da OptiFlow SGK sayfasındaki düğme).
2. Ana süreç, Medula üst adresinin izinli SGK sunucusunda olduğunu doğrular.
3. İzinli her çerçeveye `probe` isteği gider → yalnızca **sinyal** döner (puan, alan sayısı, giriş ekranı mı).
4. `frame-selection` en uygun reçete çerçevesini seçer; bulunamazsa “Reçete ekranı bulunamadı”,
   giriş ekranıysa “Medula oturumu sona ermiş” — **tahmin yapılmaz**.
5. Seçilen çerçeveye `extract` isteği → `«değer»` biçimli düz metin döner. Yanıt; kimliği, gönderen
   çerçeve belirteci, süreç numarası, kökeni ve şeması doğrulanarak kabul edilir.
6. Metin temizlenir, 190 000 bayta kırpılır, **yalnızca bellekte** yeniden deneme alanına konur.
7. `GET masaustu.php?action=durum` → mağaza, kullanıcı, CSRF. Destek oturumuysa durur.
8. `POST masaustu.php?action=aktar` (CSRF başlığı + `beklenen` mağaza/kullanıcı) →
   sunucu `sgk_parse()` ile çözümler, `sgk_incoming`'e yazar, `gelen_id` döner.
9. OptiFlow SGK sayfasındaysa `sgk-aktar.php?gelen=ID` açılır; değilse araç çubuğunda
   **Reçeteyi aç** düğmesi çıkar (yarım kalmış bir formdan zorla ayrılmamak için).
10. Personel önizlemede müşteri/siparişi seçip **onaylar** — mevcut akış, değişmedi.

## 5. Sunucu tarafı değişiklikleri (4.10.0)

| Dosya | Değişiklik |
|---|---|
| `masaustu.php`, `app/pages/masaustu.php` | Yeni JSON API: `durum`, `aktar` |
| `app/pages/sgk-aktar.php` | `?gelen=ID`, masaüstü kartı, eklenti adresine `&m=`, CSP uyumlu silme onayı |
| `app/bootstrap.php` | Masaüstü API ve eklenti uç noktası için mağaza seçimi; sürüm 4.10.0 |
| `app/merkez.php` | `kopru_tenant_bagla()` — eklenti için oturumsuz mağaza seçimi |
| `app/auth.php` | Girişte mağaza bağlamı korunur (kritik hata düzeltmesi) |
| `app/helpers.php` | `is_optiflow_desktop()`, JSON API için JSON hata sayfası |
| `assets/masaustu.js` | SGK sayfasındaki masaüstü kartı |
| `assets/pwa.js` | Masaüstünde servis çalışanı ve kurulum istemi yok |

Veritabanı şeması değişmedi.

## 6. Masaüstü tespiti

OptiFlow bölümündeki **her istek** ağ katmanında `… OptiFlowDesktop/<sürüm>` kullanıcı aracısıyla
gider (`sessions.ts`, `webRequest.onBeforeSendHeaders`, yalnızca OptiFlow kökenine). E2E testi,
yalnızca `setUserAgent` kullanıldığında sonraki gezinmelerin işareti kaybettiğini gösterdi; bu
yüzden ağ katmanı seçildi. Medula'ya giden istekler düz Chrome kimliğiyle gider (Electron izi yok).
PHP tarafında `is_optiflow_desktop()` yalnızca **görünüm** içindir; yetki kararı değildir.

## 7. Yazdırma ve indirmeler

- `print.php`, `ekran.php`, etiket sayfaları aynı kökenli **yetkisiz** açılır pencerelerde açılır;
  `window.print()` Chromium'un yazdırma penceresini gösterir (tarayıcıdakiyle aynı davranış).
- İndirmeler (CSV, yedek, ÜTS PDF/XLS) her zaman **Farklı kaydet** penceresiyle, temizlenmiş dosya
  adıyla, varsayılan olarak İndirilenler klasörüne kaydedilir. Araç çubuğunda “Klasörde göster”.
- ÜTS dosyaları tarayıcıda işlenmeye devam eder; masaüstü bu dosyaları sunucuya göndermez.

## 8. Çevrimdışı ve hata durumları

Bağlantı yok / DNS / zaman aşımı / sertifika / sunucu yok → araç çubuğunda ve hata panelinde Türkçe
mesaj. Okunan reçete bellekte 15 dakika tutulur ve **Tekrar dene** ile yeniden okunmadan gönderilir.
Oturum düşmüşse OptiFlow girişi açılır; giriş sonrası **Tekrar dene** aynı yükü gönderir.

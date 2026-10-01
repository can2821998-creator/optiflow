# OptiFlow Masaüstü — Geçiş Raporu

> **Ek — 25.09.2026: OptiFlow Lite / Pro (sunucu 4.11.0, masaüstü OptiFlow Pro 5.1.0)**
>
> - İlk deneme geri bildirimiyle düzeltildi: Medula başlangıç adresi
>   `https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces` (eskisi 404 veriyordu); uygulama
>   tanıtım sayfası yerine doğrudan mağaza girişiyle açılıyor.
> - Uygulama "OptiFlow Pro" oldu: araç çubuğu pencerenin başlık çubuğu, eski menü çubuğu yok
>   (menü "⋯" düğmesinde, kısayollar Ctrl+1/2/3, F5 çalışıyor), açılışta karşılama ekranı.
> - Paketler: mağaza başına Lite/Pro (merkez panel). Pro özellikler — SGK/Medula aktarımı ve ÜTS
>   karekod — yalnızca **Pro paket + OptiFlow Pro uygulaması**nda açık. Kilit sunucuda; Lite
>   mağaza tarayıcıyla aşamaz. Lite'ta ÜTS menüde sade ve kilitli, tıklanınca tanıtım sayfası.
> - **Dikkat:** mevcut mağazalar Lite başlar. Pro olması gerekenleri merkez panelden Pro yapın.
> - Testler: birim 74/74, sunucu 46/46 (Lite/Pro kilitleri dahil), uçtan uca 42/42.
> - Tanıtım sayfasındaki "Pro'ya geçmek istiyorum" düğmesi `app/pazarlama.php`'deki WhatsApp /
>   telefon / e-postadan gelir; bunlar boş olduğu için şu an yalnızca "destek ekibinizle iletişime
>   geçin" yazıyor.

OptiFlow 4.9.0 → **4.10.0 (sunucu)** + **OptiFlow Masaüstü 5.0.0 (Windows)** · 24.09.2026

## Özet

- Windows masaüstü uygulaması yazıldı (Electron + TypeScript, `desktop/`). Medula uygulamanın
  içinde açılır; reçete **Chrome eklentisi, Geliştirici modu, köprü adresi veya anahtar olmadan**
  aktarılır. Mevcut önizleme ve onay akışı aynen korundu.
- İnceleme sırasında **4.9.0'da iki kritik hata** bulundu ve gerçek PHP 8 + MariaDB üzerinde
  yeniden üretildi. İkisi de düzeltildi:
  1. **Personel girişi mağaza oturumunu siliyordu.** Mağaza ve kullanıcı girişi başarılı oluyor,
     ama sonraki sayfa tanıtım sayfasına / mağaza girişine düşüyordu.
  2. **Chrome eklentisi köprüsü çalışmıyordu.** Eklenti isteği JSON yerine mağaza girişine
     yönlendirme (303) alıyordu.
- Web/PWA sürümü çalışmaya devam ediyor; eklenti geçiş dönemi için destekleniyor.
- Veritabanı şeması değişmedi.

## Test sonuçları

| Test | Sonuç | Nerede |
|---|---|---|
| Birim + güvenlik + eski eklentiyle birebir eşlik + gerçek `app/sgk.php` regresyonu | **72/72** | `npm test` |
| Sunucu entegrasyonu (iki mağaza, CSRF, izolasyon, destek modu, eski eklenti) | **28/28** | `tests/server/` |
| Uçtan uca: gerçek Electron + gerçek PHP/MariaDB + sentetik Medula | **36/36** | `tests/e2e/` |
| TypeScript strict | temiz | `npm run typecheck` |
| Paket içeriği (asar'da kaynak/test yok) | doğrulandı | `npm run pack:dir` |

E2E testi iki gerçek hata yakaladı ve düzeltildi:
- **Masaüstü işareti sunucuya ulaşmıyordu.** `setUserAgent` yalnızca ilk gezinmede etkiliydi.
  İşaret artık ağ katmanında her OptiFlow isteğine ekleniyor.
- **PWA servis çalışanı masaüstü görünümünü bozuyordu.** Masaüstünde artık kaydedilmiyor.

Ekran görüntüsü kontrolü de bir arayüz hatası yakaladı ve düzeltildi: araç çubuğunda gizli
olması gereken düğmeler görünüyordu (CSS `display` kuralı `hidden`'ı eziyordu).

---

## A. İnceleme özeti

| Konu | Bulgu |
|---|---|
| Arka uç | PHP 8, sunucuda HTML üretimi, her sayfa `app/bootstrap.php`. CSP: `script-src 'self'` (satır içi JS yasak). |
| Kimlik | İki aşama: mağaza girişi (`magazalar` tablosu, merkez DB) → personel girişi (mağazanın kendi `user_accounts`'u). Oturum çerezi `optiflow`, httpOnly, SameSite=Lax. |
| Mağaza modeli | **Her mağaza ayrı veritabanı.** `tenant_gereksin()` her istekte `$_SESSION['magaza']`'dan mağazanın DB'sini seçer. |
| Destek | `merkez_magaza_gir()` merkez yöneticisini mağazanın ilk süper yetkilisi olarak sokar (`merkez_impersonate`). |
| Köprü anahtarı | `user_accounts.bridge_token`, **mağaza DB'sinde** → tek başına mağazayı belirleyemez (eklenti hatasının kökü). |
| SGK akışı | Eklenti → `sgk-aktar.php?action=kopru` → `sgk_parse()` → `sgk_incoming` → önizleme → onay → sipariş. |
| PWA | `sw.js` (HTML önbelleğe alınmaz), `pwa.js` kurulum çubuğu, `manifest.php`. |
| Yazdırma | `print.php`, `etiket.php`, `ekran.php` yeni pencerede; `window.print()`. |
| İndirme | `reports.php` CSV, `yedek.php`/`merkez-panel.php` ek dosya; ÜTS PDF/XLS tarayıcıda (blob). |
| Dış bağlantılar | WhatsApp (`wa.me`), harita, Google yorum, Instagram — `target=_blank`. |

## B. Değişenler

### Sunucu (OptiFlow 4.10.0)

| Dosya | Durum | Değişiklik |
|---|---|---|
| `masaustu.php` | **yeni** | Kök yönlendirici (sürüm kapısıyla) |
| `app/pages/masaustu.php` | **yeni** | Masaüstü köprü API'si: `durum`, `aktar` |
| `assets/masaustu.js` | **yeni** | SGK sayfasındaki masaüstü kartı (CSP uyumlu) |
| `app/auth.php` | değişti | **Düzeltme:** girişte mağaza bağlamı korunur |
| `app/bootstrap.php` | değişti | Masaüstü API ve eklenti için mağaza seçimi; `APP_VERSION` 4.10.0 |
| `app/merkez.php` | değişti | `kopru_tenant_bagla()` — eklenti için `&m=` ile mağaza seçimi |
| `app/helpers.php` | değişti | `is_optiflow_desktop()`; JSON API için JSON hata yanıtı |
| `app/pages/sgk-aktar.php` | değişti | `?gelen=ID`; masaüstü kartı; eklenti adresine `&m=`; CSP uyumlu silme onayı; masaüstünde anahtar üretilmez |
| `assets/pwa.js` | değişti | Masaüstünde servis çalışanı ve kurulum istemi yok |
| `VERSION.txt` | değişti | 4.10.0 notları |
| `kopru-eklenti/KURULUM.txt` | değişti | Masaüstü önerisi + yeni köprü adresi notu |
| `MASAUSTU-GECIS-RAPORU.md` | **yeni** | Bu rapor |

### Masaüstü (`desktop/`, tamamı yeni)

`package.json`, `tsconfig.json`, `vitest.config.ts`, `electron-builder.yml`, `.gitignore`,
`config/{development,staging,production}.json`, `scripts/build.mjs`,
`src/main/*` (12 dosya), `src/main/bridge/*` (5), `src/medula/*` (4), `src/preload/*` (3),
`src/shell/*` (HTML/CSS/TS + Manrope yazı tipi), `src/shared/*` (4), `tests/**`,
`assets/icons/icon.png`, `build/icon.png`,
`README.md`, `ARCHITECTURE.md`, `SECURITY.md`, `BUILD.md`, `RELEASE.md`, `MEDULA-BRIDGE.md`.

## C. Kaldırılan / kullanımdan kalkan (masaüstü için)

Masaüstünde **hiçbiri kullanılmaz**; web kullanıcıları için geçiş süresince **yerinde bırakıldı**:

| Parça | Masaüstündeki karşılığı |
|---|---|
| `kopru-eklenti/icerik.js` | `desktop/src/medula/extractor.ts` (birebir, eşlik testli) |
| `kopru-eklenti/arkaplan.js` | `desktop/src/main/bridge/transfer-service.ts` |
| `kopru-eklenti/ayarlar.html/.js` | Yok — adres/anahtar girişi gerekmiyor |
| `chrome.runtime.sendMessage`, `chrome.storage.sync` | Çerçeveye özel IPC; yapılandırma pakette |
| Köprü anahtarı (`bridge_token`) | Masaüstünde kullanılmıyor (oturum + CSRF) |
| SGK sayfasındaki “Köprü kurulumu” kartı | Masaüstünde “Medula · OptiFlow Masaüstü” kartı |

Tüm mağazalar masaüstüne geçince ayrıca kaldırılabilir: eklenti klasörü,
`sgk-aktar.php?action=kopru` uç noktası ve `kopru_tenant_bagla()`.

## D. Dokunulmayanlar

Müşteri, sipariş, reçete, atölye, stok, tedarikçi, kasa, raporlar, teklifler, hatırlatmalar,
merkez panel, bağış, etiketler, ÜTS karekod (tarayıcıda işleme korunur), **SGK çözümleyici
`app/sgk.php` (tek satır değişmedi)**, mağaza yönetimi, veritabanı geçişleri, PWA (web için).

## E. Riskler ve canlıda doğrulanması gerekenler

| # | Konu | Neden | Ne yapılmalı |
|---|---|---|---|
| 1 | **Medula'nın gerçek adresleri ve çerçeve yapısı** | Test ortamından SGK'ya erişilemedi. Başlangıç adresi `https://gss.sgk.gov.tr/` varsayıldı. | Bir mağazada Medula Optik'i açıp adres çubuğundaki alan adlarını ve reçetenin hangi çerçevede olduğunu kontrol edin; farklıysa `config/production.json`'daki `medulaHomeUrl`, `medulaAllowedHosts`, `medulaExtractHosts` güncellenir. |
| 2 | **Gerçek reçete ekranında tespit** | Tespit eşiği sentetik sayfalarla ayarlandı. | İlk mağazada birkaç reçetede rozetin “Reçete ekranı” dediğini doğrulayın. Demezse kayıttaki `bridge.extract.probe` satırına bakın. |
| 3 | **Canlı OptiFlow adresi** | `https://optiflow.com.tr` varsayıldı (config.sample'daki Search Console mülkünden). | `config/production.json` doğrulayın. Staging için `test.optiflow.com.tr` varsayıldı. |
| 4 | **Kod imzalama sertifikası** | Yoksa SmartScreen uyarısı çıkar, otomatik güncelleme çalışmaz. | RELEASE.md §0. |
| 5 | **Mağaza yazıcılarında yazdırma** | Linux test ortamında gerçek yazıcı yok. | Elle test listesi #12–14. |
| 6 | **Windows kurulumu** | Kurulum dosyası Linux'ta üretilip denenemedi; yapılandırma doğrulandı, paket içeriği kontrol edildi. | Windows'ta `npm run dist:win` + elle test listesi #1–3. |
| 7 | **Eklenti kullanan mağazalar** | 4.10.0 sonrası eklentideki köprü adresi bir kez güncellenmeli (`&m=`). Eklenti 4.9.0'da zaten çalışmıyordu, yani çalışan bir kurulum bozulmuyor. | Duyuru. |
| 8 | **Önceden var olan CSP hataları** | 4.9.0'da 13 satır içi olay işleyicisi CSP tarafından engelleniyor (web'de de): `etiket.php` yazdır düğmesi ve 3 kapsam düğmesi; `hatirlatma`, `bagis`, `push-admin` silme onayları (**onay sormadan siliyor**); `settings`, `basarilar`, `kasa`, `order` otomatik gönderen seçim kutuları; `merkez-panel` bir form. Masaüstüne özgü değil; kapsam dışı bırakıldı. | Ayrı bir düzeltmede `data-confirm` / JS dosyasına taşınmalı. `sgk-aktar.php`'deki olan düzeltildi. |
| 9 | **Oturum zaman aşımı** | Masaüstü sunucuyu yoklamaz; PHP'nin boşta kalma süresi aynen geçerli. | Beklenen davranış; aktarım sırasında düşerse giriş + Tekrar dene. |

## F. Elle test listesi (Windows 10 ve Windows 11)

**Kurulum**
1. `OptiFlow-Setup-5.0.0.exe` → yönetici izni istemeden kurulur; Başlat menüsü ve masaüstü kısayolu oluşur.
2. Uygulama ikinci kez açılınca yeni pencere açılmaz, mevcut pencere öne gelir.
3. Denetim Masası › Programlar'dan kaldırılır; kısayollar silinir.

**OptiFlow**
4. Mağaza girişi → personel girişi → Siparişler açılır (4.9.0 hatası olmamalı).
5. Birkaç modül gezilir (sipariş, müşteri, atölye, stok, kasa); çıkış yapılır → giriş ekranı.
6. PWA “Ana ekrana ekle” çubuğu çıkmaz.
7. Bir WhatsApp bağlantısı → varsayılan tarayıcıda açılır.

**Medula**
8. **SGK · Medula** → Medula uygulama içinde açılır; personel kendi SGK bilgisiyle giriş yapar.
9. Uygulama kapatılıp açılır → Medula oturumu SGK izin verdiği sürece korunur.
10. Reçete detayı açılınca araç çubuğunda yeşil **Reçete ekranı** rozeti; listede **Reçete yok**.
11. Menü › SGK / Medula › **Medula oturumunu temizle** → Medula girişi istenir; OptiFlow oturumu açık kalır.

**Yazdırma / indirme**
12. Sipariş › **Fiş** → ayrı pencere → Yazdır → yazıcı seçimi → çıktı doğru.
13. Etiket ve ÜTS karekod A4 PDF'i → **Farklı kaydet** penceresi → dosya açılır, karekod okunur.
14. Raporlar › CSV → Farklı kaydet → Excel'de Türkçe karakterler doğru.

**Köprü**
15. Reçete detayında **Reçeteyi aktar** → OptiFlow SGK sayfasındaysa önizleme kendiliğinden açılır;
    değerler Medula ekranıyla karşılaştırılır (SPH/CYL/AKS, ADD, PD, hasta, e-reçete, doktor).
16. Siparişe yazma **yalnızca onayla** olur; reçete ekranında cam tipi seçilip kaydedilir.
17. İnternet kesilip **Reçeteyi aktar** → Türkçe hata + **Tekrar dene**; bağlantı gelince Tekrar dene başarılı.
18. OptiFlow'dan çıkış yapılıp aktarım → “Oturum süreniz dolmuş” → giriş → **Tekrar dene** başarılı (Medula yeniden okunmadan).
19. Merkez panelden mağazaya girilip aktarım → “Destek (merkez) oturumunda…” reddi.
20. Menü › Yardım › **Tanılama kayıtlarını dışa aktar** → dosyada hasta adı, T.C., çerez yok.

**Web (geriye uyumluluk)**
21. Chrome'da web sürümü: SGK sayfasında eklenti kurulumu hâlâ görünür; köprü adresi `&m=` içerir.
22. Eklentiye yeni adres girilip **Atölyeye aktar** → “Köprüden gelenler”e düşer.

## G. Bilinen sınırlamalar

- Yalnızca Windows x64 (ARM64 kolayca eklenebilir; bkz. BUILD.md).
- `optiflow://` derin bağlantısı eklenmedi (spec'te isteğe bağlı; gerek görülmedi).
- Medula içindeki SGK dışı bağlantılar açılmaz (güvenlik); kullanıcıya uyarı gösterilir.
- Çevrimdışı çalışma yok (spec gereği); okunan reçete 15 dk bellekte bekletilir.
- Otomatik güncelleme, sertifika ve güncelleme sunucusu hazırlanana kadar kapalı (`updateUrl` boş).

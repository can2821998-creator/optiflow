# OptiFlow — Mevcut durum ve özellik envanteri

*5 Ekim 2026 · sunucu **4.16.4** (veritabanı şeması 27) · masaüstü **OptiFlow Pro 5.3.0** · canlı: https://optiflow.com.tr*

Bu belge analiz için hazırlandı. Bölümler:
1. Ürün ve mimari
2. Paketler (Lite / Pro)
3. Özellik envanteri
4. Entegrasyonlar
5. Güvenlik ve KVKK
6. Yayın ve altyapı
7. Testler
8. SEO ve pazarlama
9. Açık sorunlar ve riskler
10. Bekleyen işler ve kararlar

Son bölüm sayısal özet.

---

## 1. Ürün ve mimari

OptiFlow, gözlükçü ve optik atölyeleri için reçeteden teslimata yönetim sistemidir.

| Katman | Teknoloji | Not |
|---|---|---|
| Sunucu | PHP 8 + MySQL/MariaDB | Paylaşımlı hosting (Plesk). Çerçeve kullanılmıyor; `app/` modülleri + kökteki sayfa dosyaları. |
| Çok mağaza | Mağaza başına **ayrı veritabanı** | Mağazalar merkez tablosunda (`magazalar`). Merkez panelden yönetilir. |
| Web arayüzü | Sunucuda üretilen HTML + `assets/*.js` | CSP `script-src 'self'`: satır içi betik yok. PWA (servis çalışanı, bildirim). |
| Masaüstü | Electron + TypeScript (Windows) | OptiFlow ile SGK Medula tek pencerede. İş mantığı sunucuda kalır, uygulama kabuk ve köprüdür. |
| Kaynak | GitHub `can2821998-creator/optiflow` (özel) | Tek doğru kaynak. Kurulum dosyaları GitHub Releases'ta. |

**Büyüklük:** sunucu tarafı yaklaşık 39 bin satır (PHP/JS/CSS, `app` + `assets`), masaüstü kaynağı yaklaşık 3,6 bin satır TypeScript. Kökte 76 PHP dosyası, `app/pages` altında 66 sayfa.

---

## 2. Paketler: OptiFlow Lite ve OptiFlow Pro

Paket merkez panelden mağaza bazında atanır. Varsayılan **Lite**.

| | OptiFlow Lite | OptiFlow Pro |
|---|---|---|
| Nasıl çalışır | Tarayıcıda, her cihazda | Windows uygulaması; Medula yanında |
| Temel modüllerin hepsi | ✅ | ✅ |
| Medula'dan reçete aktarımı (tek tuş) | ❌ Elle yapıştırıp çözümleme var; aktarım kilitli kartla tanıtılır | ✅ |
| ÜTS karekod | ❌ Menüde kilitli; tanıtım sayfası açılır | ✅ |
| Barkod / karekod okuyucu modu | ❌ | ✅ (özellik anahtarıyla) |
| Çevrimdışı salt okunur mod | ❌ | ✅ (özellik anahtarıyla) |
| Medula "Hak sorgula" düğmesi | ❌ | ✅ (5.3.0, `sgk_hak` açıksa) |

**Pro kuralı:** mağazanın paketi Pro olmalı (sunucuda, her istekte merkezden okunur) **ve** istek OptiFlow Pro uygulamasından gelmeli. Pro paketli mağaza tarayıcıdan girerse "OptiFlow Pro uygulamasını açın" uyarısını görür.

**Fiyatlar:** tanıtım sayfasında iki paket kartı var: "Tek Mağaza" (ay + KDV) ve "Çok Şubeli" (şube / ay + KDV). Fiyat alanları **boş**, bu yüzden sayfada "Fiyat için bize yazın" görünüyor. Kurucu mağaza kampanyası hazır ama kapalı. 30 gün ücretsiz deneme var.

---

## 3. Özellik envanteri

### 3.1 Temel modüller (her mağazada açık)

| Alan | Özellikler |
|---|---|
| **Müşteri** | Müşteri listesi ve kartı, aile kartı (aile üyeleri), reçete geçmişi, müşteri kartı yazdırma, mesaj izni (kaynak + tarih). |
| **Reçete ve sipariş** | Reçete girişi ve düzenleme, yeni sipariş (gözlük, güneş, lens, tamir…; işlem türüne göre alanlar), sipariş sayfası, teklif (quote), fiş ve yazdırma şablonları. |
| **SGK** | SGK katkı payı otomasyonu; reçete aktarımı (Pro'da Medula, Lite'ta yapıştırma); e-reçete no saklama; hak tarihi hesabı. |
| **Atölye** | Aşama takibi (workshop), duvar ekranı (`ekran.php`: yalnızca sipariş no, baş harf, aşama), teslimatlar, "Siparişim nerede?" müşteri sayfası, karekodlu takip fişi. |
| **Kasa ve para** | Tahsilat, gün sonu kasa (nakit sayımı, küçük satış, harcama), bakiye takibi (söz verilen ödeme tarihi), kâr raporu, raporlar, hediye çeki (voucher). |
| **Stok** | Çerçeve stoğu ve hareketleri, cam stoğu (Depo · Stok, eksik camlar), kritik stok uyarısı, barkodlu etiket ve etiket sihirbazı. |
| **Tedarikçi** | Tedarikçi kartı ve cari, tedarikçi faturası (kâğıt), tedarikçi karnesi. |
| **Müşteriyi geri getirme** | Akıllı hatırlatmalar (gözlük zamanı, SGK hakkı, teslim alınmamış gözlük), bakım kartı, web push bildirimleri. |
| **Ekip** | Personel kullanıcıları, roller, şube yetkileri, personel primi, başarılar (rozetler), işlem kayıtları. |
| **Diğer** | Gözlük bağışı modülü ve sayacı, veritabanı yedeği indirme (`yedek.php`), mağaza adı ve rengiyle markalı müşteri sayfaları. |
| **ÜTS karekod (Pro)** | ÜTS stok/bildirim listesini (XLS, XLSX, CSV, XML) okuyup Medula'ya okutulabilir karekod etiketi (A4 PDF) üretir. SKT filtresi, toplu imha dosyası. Dosya sunucuya gönderilmez. |

### 3.2 Merkezden açılan modüller (özellik anahtarları, hepsi varsayılan kapalı)

Merkez panel › mağaza › **Özellikler** kartından ya da toplu işlemle açılır. Kapalı modül menüde görünmez, sayfası açılmaz.

| Anahtar | Modül | Sürüm | Durum |
|---|---|---|---|
| `whatsapp` | WhatsApp mesaj otomasyonu: tek tık (uygulama) ya da otomatik (Meta Cloud API, onaylı şablonlar). Olaylar: gözlük hazır, teslim alınmadı, ödeme linki, yenileme, SGK hakkı, lens, Google yorum isteği. | 4.12 | ✅ |
| `odeme_linki` | PayTR ödeme linki; HMAC doğrulamalı bildirimle tahsilat kendiliğinden yazılır. | 4.12 | ✅ |
| `lens_takip` | Kontakt lens bitiş tarihi ve tekrar sipariş hatırlatması. | 4.12 | ✅ |
| `cam_siparis` | Eksik camlar için tedarikçi fişi (yazdır / WhatsApp / e-posta); gelince kendiliğinden tamamlanır. | 4.12 | ✅ |
| `stok_oneri` | Satın alma önerisi: 90 günlük satış hızıyla kritik çerçeveler + fişe girmemiş eksik camlar. | 4.12 | ✅ |
| `efatura` | e-Arşiv / e-Fatura **hazırlığı**: taslak, TCKN/VKN kontrolü, KDV dağılımı, UBL-TR 1.2 XML, entegratör arayüzü. | 4.12 | 🟡 GİB'e gönderim yok |
| `sgk_mutabakat` | SGK mutabakatı: aylık özet, Medula listesiyle karşılaştırma, bekleyen reçeteler. | 4.12 | ✅ |
| `barkod` | USB okuyucu modu: ÜTS karekodu, çerçeve barkodu, sipariş fişi ya da garanti kartı okutunca kayıt açılır. Yalnızca masaüstü. | 4.12 | ✅ |
| `cevrimdisi` | İnternet kesilince açık siparişler ve telefonlar şifreli kopyadan okunur. Yalnızca masaüstü. | 4.12 | ✅ |
| `uts_bildirim` | ÜTS bildirimleri: mal kabul, tüketiciye verme, iade, imha, tedarikçiye iade. Kuyruk ve tekrar deneme; SGK'lı satışta bildirim Medula'ya bırakılır. | 4.13 | 🟡 ÜTS test ortamında doğrulanmadı |
| `tedarik_finans` | Alış faturası (e-Fatura UBL-TR XML/ZIP → stok, maliyet, cari), senetler, ödeme takvimi, vade uyarısı, bono yazdırma. | 4.14 | 🟡 Gerçek entegratör XML'iyle denenmedi |
| `cam_hata` | Hatalı cam / yeniden yapım: sebep, maliyet, laboratuvar iade alacağı, rapor, tedarikçi karnesinde hata oranı. | 4.15 | ✅ |
| `sgk_hak` | SGK hak kontrolü: yaşa göre süre (yetişkin 24 ay, 14 yaş altı 12 ay), Medula / e-Devlet hak ekranını okuma. | 4.15 | 🟡 Gerçek hak ekranıyla denenmedi |
| `garanti` | Garanti kaydı ve karekodlu garanti kartı. Ayrıntı: 3.3. | 4.16.0 | ✅ |

### 3.3 Son sürümlerde gelenler (4.16.x)

- **Garanti (4.16.0)**
  - Teslimde kalem bazında garanti kendiliğinden açılır (çerçeve, cam; varsayılan 24 ay).
  - Karekodlu garanti kartı basılır; müşteri kalan süreyi telefonundan, giriş yapmadan görür.
  - Garanti talebi, tedarikçiye gönderim ve tedarikçi formu, tamir geçmişi.
  - Müşteri sayfalarına mağaza numarası (`m=`) eklendi; çok mağazalı yapıda doğru mağazayı açar.
- **SGK ay sonu faturası (4.16.1)**
  - Siparişte "Medula'ya işlendi / işlenmedi" düğmesi.
  - Ay sonunda SGK'ya **tek fatura** ve reçete dökümü; sipariş faturası yalnızca hasta payını içerir.
  - Medula PDF dökümü yüklenip adet ve e-reçete numaraları karşılaştırılır. PDF okuyucu dış kütüphane kullanmıyor.
- **Tanıtım sayfası (4.16.2)**
  - Göz eşeli başlık, Lite/Pro bölümü ve farklar tablosu.
  - Gerçek ekran önizlemeleri ve önce/sonra tablosu.
- **SEO (4.16.3–4.16.4)**
  - Organization ve WebSite şeması, www → çıplak alan adı 301, rehbere 4 yeni yazı.
  - Merkez panel › **SEO · Google**: Search Console ve PageSpeed verisi; bağlantı panelden kurulur.

### 3.4 Merkez yönetim paneli (`merkez-panel.php`)

| Ekran | İçerik |
|---|---|
| **Mağazalar** | KPI'lar (aktif, beklemede, ücretli, deneme, 7 günde biten…), arama ve filtre, toplu işlemler (dondur, aktifleştir, +30 gün, plan, paket, özellik aç/kapat), CSV. |
| **Mağaza detayı** | Canlı sayımlar, plan ve deneme süresi, paket, özellikler, veritabanı bağlantısı, giriş şifresi, notlar. Personel listesi (şifre sıfırlama, pasife alma), "mağaza paneline gir" (destek), silme. |
| **Sağlık** | Tüm mağazaların veritabanı erişimi ve sayıları. |
| **İşlem günlüğü** | Paneldeki her işlem IP ve zamanla kaydedilir. |
| **Rehber** | Blog yazısı ekleme, düzenleme, yayına alma (JSON dosyası; veritabanı kullanılmaz). |
| **SEO · Google** | Search Console (tıklama, gösterim, sorgu, sıra değişimi, sayfalar), PageSpeed puanları, bağlantı ayarları, doğrulama kodları, veri uç noktası. |
| **+ Yeni mağaza** | Elle mağaza açma. |

### 3.5 OptiFlow Pro masaüstü (5.3.0)

- OptiFlow ve SGK Medula tek pencerede. Kısayollar: Ctrl+1 OptiFlow, Ctrl+2 Medula, Ctrl+3 yan yana görünüm.
- **"Reçeteyi aktar":** o an ekranda görünen reçete okunup önizlemeye gelir; siparişe yazma personel onayıyla olur. Chrome eklentisi gerekmez.
- **SGK şifresi:** okunmaz, saklanmaz; Medula'ya otomatik giriş yapılmaz.
- **Medula araçları:** "Listeyi kontrol et" (mutabakat), "Hak sorgula" (5.3.0), "Medula'yı sıfırla".
- **Diğer:** barkod okuyucu modu, çevrimdışı şifreli kopya (çıkışta silinir), otomatik güncelleme (hosting'deki `latest.yml`), maskelenmiş tanılama kaydı dışa aktarma.
- **Kod imzalama:** derleme hattı hazır; **sertifika yok**, bu yüzden imza denetimi kapalı.

---

## 4. Entegrasyonlar

| Dış sistem | Nasıl | Durum |
|---|---|---|
| SGK Medula | Pro uygulamasında gömülü tarayıcı + ekran okuma (API yok) | ✅ Sahada kullanılıyor |
| ÜTS (Sağlık Bakanlığı) | REST servisleri; token şifreli; yalnızca `*.saglik.gov.tr` | 🟡 Kod hazır, test ortamında doğrulanmadı |
| GİB e-Arşiv / e-Fatura | UBL-TR 1.2 üretimi + entegratör sürücü arayüzü | 🟡 Entegratör bağlı değil |
| Tedarikçi e-Faturası (alış) | UBL-TR XML/ZIP yükleme | 🟡 Örnek XML'le test edildi |
| PayTR | Ödeme linki + HMAC bildirim | ✅ |
| WhatsApp | Tek tık bağlantı ya da Meta Cloud API | ✅ |
| Google Analytics 4 | `G-MYLZP3G755`; yalnızca genel sayfalarda | ✅ |
| Google Search Console / PageSpeed | Hizmet hesabı (salt okunur) | 🟡 Kod canlıda; kullanıcı hizmet hesabını henüz kurmadı |
| Web Push | VAPID, servis çalışanı | ✅ |

---

## 5. Güvenlik ve KVKK

- **Veri ayrımı:** her mağazanın ayrı veritabanı var; personel yalnızca kendi şubesini görür.
- **Kimlik no:** T.C. kimlik no saklanmaz. Müşteri sayfaları soyadın tamamını, telefonu, fiyatı ve maliyeti göstermez.
- **Ölçüm araçları:** GA4 ve doğrulama etiketleri yalnızca giriş öncesi genel sayfalarda; panel sayfaları `noindex`.
- **CSP:** `script-src 'self'`, satır içi betik yok.
- **Merkez panel:** şifre hash olarak saklanabilir; giriş IP bazlı kaba kuvvet freniyle korunuyor (15 dakikada 8 deneme).
- **Gizli bilgiler:**
  - PayTR, WhatsApp ve ÜTS anahtarları `storage/.gizli-anahtar` ile şifreli.
  - `storage/` web'e kapalı (canlıda 403 doğrulandı).
  - `config.php` ve anahtarlar git'e girmiyor.
- **Masaüstü:** SGK şifresi okunmaz; kayıtlarda hasta bilgisi, şifre ya da çerez yok; köprü oturum + CSRF ile çalışır.
- **XML güvenliği:** DOCTYPE'lı XML reddedilir (XXE); ZIP'te okunan bayt sınırlı.
- **Açık KVKK işi:** SGK hak ekranından okunan satırlar ham saklanıyor; 11 haneli numara girebilir. Kullanıcı "ertele" dedi (bkz. §9).

---

## 6. Yayın ve altyapı

```
main'e sürüm push'u (APP_VERSION / desktop/package.json)
  → "Sürüm yayınla" (Actions): sunucu testleri + Windows exe derlemesi
      → GitHub Release v<sunucu>-pro<masaüstü>: exe, blockmap, latest.yml, hosting zip
  → "Canlıya al" (Actions): Release → FTPS (lftp) → hosting
      (config.php ve storage/ dokunulmaz; exe yalnızca sürüm değiştiyse; latest.yml en son)
```

- **Sırlar:** FTP_SUNUCU, FTP_KULLANICI, FTP_SIFRE, FTP_KLASOR (GitHub Secrets'ta; 04.10'da girildi).
- **Veritabanı göçü:** ilk personel girişinde kendiliğinden çalışır; yalnızca ekleme yapar, veri silmez.
- **Zamanlanmış görevler:** `cron.php?anahtar=…`. Ayrıca personel sayfaları açıkken 5 dakikada bir kendiliğinden çalışır.
- **Release'ler:** v4.15.1-pro5.3.0, v4.16.0, v4.16.1, v4.16.2, v4.16.3, **v4.16.4-pro5.3.0** (05.10, canlıda).
- **Çalışma düzeni:** her şey git'te; komutlar "gitten çek" ve "gite yükle"; durum `DURUM.md`'de.

---

## 7. Testler

| Paket | Kontrol sayısı |
|---|---|
| ÜTS (`tests/uts`) | 168 + 34 sayfa |
| Alış faturası / senet (`tests/alis`) | 104 + 27 sayfa |
| Hatalı cam / SGK hak (`tests/hata-hak`) | 70 + 26 sayfa |
| Garanti (`tests/garanti`) | 77 + 42 sayfa |
| SGK ay sonu faturası (`tests/sgk-fatura`) | 57 + 30 sayfa |
| SEO bağlantısı (`tests/seo`) | 48 |
| Sunucu entegrasyonu (yerel MariaDB) | api 46, modüller 76, garanti 37, sgk-fatura 25 |
| Masaüstü birim / uçtan uca | 91 / 61 |

Hepsi geçiyor (05.10). Birim testleri SQLite düzeneğinde çalışıyor; MySQL göçleri yerel MariaDB'de denendi.

**Kapsam boşluğu:** 4.12 öncesi çekirdek modüllerin (sipariş, kasa, stok, rapor…) ayrı birim testi yok. Bunlar entegrasyon testleriyle kısmen kapsanıyor.

---

## 8. SEO ve pazarlama durumu

- **Tanıtım sayfası:**
  - Title: "Gözlükçü Programı OptiFlow | Medula Aktarımı, SGK Faturası ve Atölye".
  - Şemalar: SoftwareApplication, FAQPage, Organization, WebSite.
  - Open Graph görseli var.
- **Rehber (blog):** 5 yazı.
  - Gözlükçü programı seçerken dikkat edilecekler
  - Medula reçetesini siparişe aktarma
  - SGK gözlük faturası (ay sonu)
  - Optik atölye sipariş takibi
  - Gözlük garanti belgesi
- **Site haritası:** dinamik (`sitemap.xml` → `sitemap.php`). `robots.txt` her şeye açık.
- **Search Console:** mülk var. Hizmet hesabı bağlantısı ve site haritası gönderimi kullanıcıda bekliyor.
- **Google'da görünürlük:** 05.10'da site henüz görünmüyordu (`site:` aramasında sonuç yok).
- **Bekleyen kullanıcı işleri:** Plesk'te HTTP→HTTPS 301; Bing'e Search Console'dan içe aktarma.
- **Eksik pazarlama verileri:** `app/pazarlama.php`'de fiyatlar ve iletişim alanlarının **tamamı boş** (şirket unvanı, adres, telefon, WhatsApp, e-posta, KVKK e-postası, Instagram, demo video). Sonuçları:
  - sitede telefon/WhatsApp düğmesi ve mobil iletişim çubuğu çıkmıyor;
  - KVKK aydınlatma metninde **veri sorumlusu** unvanı ve adresi yok;
  - Organization şemasında iletişim bilgisi yok.

---

## 9. Açık sorunlar ve riskler

| # | Konu | Etki | Not |
|---|---|---|---|
| 1 | Canlı veritabanı göçü (22 → 27) doğrulanmadı | Yüksek | İlk personel girişinde çalışır; sonucu kontrol edilmeli. |
| 2 | Gerçek Medula PDF dökümü görülmedi | Orta | SGK ay sonu karşılaştırması sahte PDF'lerle test edildi; e-reçete no 7 karakter varsayılıyor. |
| 3 | KVKK: SGK hak ekranı satırları ham saklanıyor | Orta (yasal) | Maskeleme ertelendi. |
| 4 | ÜTS servisleri test ortamında denenmedi | Orta | Alan adları (SNC, MSJ, BID) doğrulanmalı. |
| 5 | e-Fatura GİB'e gönderilmiyor | Orta (ticari) | Entegratör seçimi ve sözleşmesi gerekli. |
| 6 | Kod imzalama sertifikası yok | Orta | Windows SmartScreen uyarısı; imza denetimi kapalı. |
| 7 | 4.16.0 öncesi basılmış fişlerin karekodlarında `m=` yok | Düşük | Müşteri mağaza girişine düşer; fişi yeniden yazdırmak çözer. |
| 8 | Gerçek Medula hak ekranı ve gerçek entegratör XML'iyle deneme yok | Düşük–orta | Örnek veriyle test edildi. |
| 9 | Eksik belgeler | Düşük | `YENILIKLER-4.13/4.14/4.15.md` ve yol haritası belgesi kayıp. |
| 10 | Çekirdek modüllerde birim testi az | Düşük–orta | Bkz. §7. |
| 11 | İletişim bilgileri ve şirket unvanı boş | Yüksek (satış + KVKK) | Ziyaretçi ulaşamıyor; KVKK metninde veri sorumlusu yok. `app/pazarlama.php` doldurulmalı. |

---

## 10. Bekleyen işler ve kararlar

**Sıradaki işler (DURUM.md):**
0. SEO bağlantısını kurmak (kullanıcı: Google Cloud hizmet hesabı) ve ilk verilere göre içerik planlamak.
1. T.C. no maskeleme (SGK hak), ertelendi.
2. Canlı göç kontrolü ve gerçek Medula PDF'iyle doğrulama.
3. Sıradaki modül kararı.

**Karar bekleyen:** SGK dönem sonu paketi + kesinti defteri. Bu iş SGK ay sonu faturasının devamı: dönem kapanışı ve SGK'nın yaptığı kesintilerin takibi.

**Analiz için öneri alanları:**
- İletişim bilgileri ve şirket unvanının girilmesi (en hızlı kazanım).
- Fiyatlandırmanın belirlenmesi (sayfada fiyat yok).
- e-Fatura entegratörü seçimi.
- İmza sertifikası alımı.
- Kullanım verisi: hangi özellik anahtarının kaç mağazada açık olduğu merkez panelden çıkarılabilir.

---

## Sayısal özet

| Ölçü | Değer |
|---|---|
| Sunucu sürümü / şema | 4.16.4 / 27 |
| Masaüstü sürümü | OptiFlow Pro 5.3.0 |
| Özellik anahtarı (merkezden açılan modül) | 14 |
| Pro'ya özel yetenek | 2 (Medula aktarımı, ÜTS karekod) + 2 yalnızca masaüstü (barkod, çevrimdışı) |
| PHP dosyası (kök / `app/pages`) | 76 / 66 |
| Kod (yaklaşık) | 39 bin satır sunucu + 3,6 bin satır masaüstü TS |
| Otomatik test kontrolü | ~635 sunucu + 184 entegrasyon + 152 masaüstü |
| Rehber yazısı | 5 |
| GitHub Actions iş akışı | 4 (sürüm yayınla, canlıya al, FTP dene, Pro imzalı derleme) |

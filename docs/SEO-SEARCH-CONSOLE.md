# OptiFlow — Google Search Console ve SEO

*5 Ekim 2026 · sunucu 4.16.4*

## 1. Search Console'a siteyi ekleme (bir kez)

1. https://search.google.com/search-console adresini açın. Sitenin Google Analytics'iyle aynı Google hesabını kullanın.
2. **Mülk ekle** › **URL ön eki** › `https://optiflow.com.tr/` yazın.
3. Doğrulama yöntemlerinden **HTML etiketi**'ni seçin. Size şuna benzer bir satır verilir:
   `<meta name="google-site-verification" content="AbCdEf123..." />`
4. Satırın tamamını **Merkez panel › SEO · Google › Google doğrulama kodu** kutusuna yapıştırıp **Kaydet**'e basın. Etiket hemen tanıtım sayfasına ve rehbere eklenir, sürüm beklemek gerekmez.
5. Search Console'da **Doğrula**'ya basın.

Mülk zaten varsa ve doğrulanmışsa bu bölümü atlayın.

**Alternatif (Alan adı mülkü, DNS ile):** `optiflow.com.tr`'yi **Alan adı** olarak ekleyip verilen TXT kaydını alan adının DNS'ine (Plesk › DNS ayarları ya da alan adını aldığınız firma) eklerseniz www, http ve https hepsi tek mülkte toplanır. Kod değişikliği gerekmez.

## 2. Search Console verisini panele bağlama (bir kez, ~10 dakika)

Merkez panel › **SEO · Google** ekranı; arama sorgularını, sayfaları, tıklama/gösterim sayılarını ve mobil hız puanını gösterir.
Bunun için Google'a salt-okunur bir "hizmet hesabı" açılır.

1. **Proje açın.** https://console.cloud.google.com › üstteki proje seçici › **Yeni proje** › ad: `optiflow-seo` › **Oluştur**.
   Projenin seçili olduğundan emin olun.
2. **API'yi açın.** Menü › **API'ler ve Hizmetler › Kitaplık** › "Google Search Console API" arayın › **Etkinleştir**.
   (Hız ölçümü için ayrıca "PageSpeed Insights API" etkinleştirilebilir; isteğe bağlı.)
3. **Hizmet hesabı açın.** Menü › **IAM ve Yönetici › Hizmet hesapları** › **Hizmet hesabı oluştur** › ad: `seo-okuyucu` › **Bitti**.
   Rol vermeyin; gerekmiyor.
4. **Anahtarı indirin.** Oluşan hesaba tıklayın › **Anahtarlar** › **Anahtar ekle › Yeni anahtar oluştur** › **JSON** › **Oluştur**.
   Bir `.json` dosyası iner. Bu dosya bir şifredir: e-postayla göndermeyin, git'e koymayın.
   - Kuruluş politikası anahtar oluşturmayı engelliyorsa ("iam.disableServiceAccountKeyCreation") kişisel Gmail hesabıyla açılan projede bu engel olmaz.
5. **Panele yükleyin.** Merkez panel › **SEO · Google** › "Google hizmet hesabı anahtarı" › dosyayı seçin. Mülkü yazın:
   - Search Console'da mülk **Alan adı** türündeyse `sc-domain:optiflow.com.tr`
   - **URL ön eki** türündeyse `https://optiflow.com.tr/`

   Sonra **Kaydet**'e basın. Ekranda hizmet hesabının e-postası görünür (…@optiflow-seo.iam.gserviceaccount.com).
6. **Search Console'da izin verin.** Search Console › mülk › **Ayarlar › Kullanıcılar ve izinler › Kullanıcı ekle** › o e-postayı yapıştırın › izin: **Kısıtlı** › **Ekle**.
7. **Deneyin.** Panelde **Google'dan verileri al**'a basın.
   - "Hizmet hesabının gördüğü mülkler" satırında mülk görünmüyorsa 6. adım eksik ya da birkaç dakika bekleyin.
   - "API … disabled" hatası 2. adımın, "Kullanıcılar ve izinler" ipucu 6. adımın eksik olduğunu gösterir.
   - Yeni mülkte ilk arama verisi 2–7 gün içinde gelir; "Henüz arama verisi yok" yazması normaldir.
   - PageSpeed "Quota exceeded" derse: Google Cloud › **API'ler ve Hizmetler › Kimlik bilgileri › Kimlik bilgisi oluştur › API anahtarı**; anahtarı panelde "PageSpeed API anahtarı" kutusuna girin.

Panel ayrıca otomatik raporlar için bir **veri uç noktası** adresi verir (`seo-veri.php?t=…`). Adresteki anahtar gizlidir; sızarsa **Erişim anahtarını yenile**'ye basın.

Anahtar ve ayarlar hosting'de web'e kapalı `storage/` klasöründe durur (`storage/gsc-anahtar.json`, `storage/seo/`), otomatik yüklemede silinmez.

## 3. Doğrulamadan sonra

1. **Site haritaları** › `sitemap.xml` yazıp **Gönder**. Site haritası; ana sayfayı, rehberi ve yayındaki bütün rehber yazılarını kendiliğinden içerir.
2. **URL denetimi** › `https://optiflow.com.tr/` › **Dizine eklenmeyi iste**. Aynısını rehber yazıları için de yapın.
3. Bing için: https://www.bing.com/webmasters › **Google Search Console'dan içe aktar** (ek doğrulama gerekmez).

## 4. Plesk'te iki ayar

- **HTTP → HTTPS:** Plesk › optiflow.com.tr › **Hosting ve DNS › SSL/TLS** › "HTTP'den HTTPS'ye kalıcı, SEO dostu 301 yönlendirme" açık olsun.
- **www:** `www.optiflow.com.tr` artık `.htaccess` ile `https://optiflow.com.tr`'ye kalıcı yönlenir (4.16.3).

## 5. Sitede yapılanlar (4.16.3–4.16.4)

- Search Console / Bing doğrulama etiketleri: Merkez panel › SEO · Google (ya da `app/pazarlama.php`).
- 4.16.4: Merkez panel › **SEO · Google** ekranı (Search Console + PageSpeed verisi, bağlantı ayarları; Plesk'te `config.php` düzenlemek gerekmez).
- Ana sayfaya kuruluş ve web sitesi yapılandırılmış verisi (Organization, WebSite); mevcut SSS ve uygulama verisi korunuyor.
- `www` → çıplak alan adı 301 yönlendirmesi; WebP görseller için tarayıcı önbelleği.
- Rehbere 4 yeni yazı (aranan konularda) ve ana sayfadan yazılara iç bağlantı:
  - Medula Optik reçetesi siparişe nasıl aktarılır? (medula optik reçete)
  - SGK gözlük faturası: ay sonu toplu fatura (sgk gözlük faturası)
  - Optik atölyesinde sipariş takibi (gözlükçü sipariş takibi)
  - Gözlük garantisi: garanti belgesi ve tamir takibi (gözlük garantisi)

## 6. Sonraki adımlar (öneri)

- **Google İşletme Profili:** Bir ofis/adres varsa açın; marka aramalarında görünürlüğü artırır.
- **Düzenli içerik:** Ayda 2 yazı yeterli. Önerilen konular: Medula Optik hak sorgulama, ÜTS karekod okutma, kontakt lens hatırlatması, gözlükçüde gün sonu kasa, çok şubeli optik yönetimi.
- **Bağlantılar:** Optisyen-gözlükçü odaları, tedarikçiler ve sektör sitelerinden gelecek bağlantılar en güçlü sıralama sinyalidir.
- **Takip:** Merkez panel › SEO · Google'da (ya da Search Console › Performans'ta) "gözlükçü programı", "optik programı", "medula optik" sorgularının gösterim ve tıklamalarına ayda bir bakın.

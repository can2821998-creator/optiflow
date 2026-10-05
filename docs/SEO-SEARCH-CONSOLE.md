# OptiFlow — Google Search Console ve SEO

*5 Ekim 2026 · sunucu 4.16.3*

## 1. Search Console'a siteyi ekleme (bir kez)

1. https://search.google.com/search-console adresini açın. Sitenin Google Analytics'iyle aynı Google hesabını kullanın.
2. **Mülk ekle** › **URL ön eki** › `https://optiflow.com.tr/` yazın.
3. Doğrulama yöntemlerinden **HTML etiketi**'ni seçin. Size şuna benzer bir satır verilir:
   `<meta name="google-site-verification" content="AbCdEf123..." />`
4. Yalnızca `content="..."` içindeki kodu Claude'a gönderin. Kod `app/pazarlama.php` › `google_dogrulama` alanına yazılır, sürüm yayınlanınca siteye çıkar.
5. Site güncellendikten sonra Search Console'da **Doğrula**'ya basın.

**Alternatif (Alan adı mülkü, DNS ile):** `optiflow.com.tr`'yi **Alan adı** olarak ekleyip verilen TXT kaydını alan adının DNS'ine (Plesk › DNS ayarları ya da alan adını aldığınız firma) eklerseniz www, http ve https hepsi tek mülkte toplanır. Kod değişikliği gerekmez.

## 2. Doğrulamadan sonra

1. **Site haritaları** › `sitemap.xml` yazıp **Gönder**. Site haritası; ana sayfayı, rehberi ve yayındaki bütün rehber yazılarını kendiliğinden içerir.
2. **URL denetimi** › `https://optiflow.com.tr/` › **Dizine eklenmeyi iste**. Aynısını rehber yazıları için de yapın.
3. Bing için: https://www.bing.com/webmasters › **Google Search Console'dan içe aktar** (ek doğrulama gerekmez).

## 3. Plesk'te iki ayar

- **HTTP → HTTPS:** Plesk › optiflow.com.tr › **Hosting ve DNS › SSL/TLS** › "HTTP'den HTTPS'ye kalıcı, SEO dostu 301 yönlendirme" açık olsun.
- **www:** `www.optiflow.com.tr` artık `.htaccess` ile `https://optiflow.com.tr`'ye kalıcı yönlenir (4.16.3).

## 4. Sitede yapılanlar (4.16.3)

- Search Console / Bing doğrulama etiketleri için ayar (`google_dogrulama`, `bing_dogrulama`).
- Ana sayfaya kuruluş ve web sitesi yapılandırılmış verisi (Organization, WebSite); mevcut SSS ve uygulama verisi korunuyor.
- `www` → çıplak alan adı 301 yönlendirmesi; WebP görseller için tarayıcı önbelleği.
- Rehbere 4 yeni yazı (aranan konularda) ve ana sayfadan yazılara iç bağlantı:
  - Medula Optik reçetesi siparişe nasıl aktarılır? (medula optik reçete)
  - SGK gözlük faturası: ay sonu toplu fatura (sgk gözlük faturası)
  - Optik atölyesinde sipariş takibi (gözlükçü sipariş takibi)
  - Gözlük garantisi: garanti belgesi ve tamir takibi (gözlük garantisi)

## 5. Sonraki adımlar (öneri)

- **Google İşletme Profili:** Bir ofis/adres varsa açın; marka aramalarında görünürlüğü artırır.
- **Düzenli içerik:** Ayda 2 yazı yeterli. Önerilen konular: Medula Optik hak sorgulama, ÜTS karekod okutma, kontakt lens hatırlatması, gözlükçüde gün sonu kasa, çok şubeli optik yönetimi.
- **Bağlantılar:** Optisyen-gözlükçü odaları, tedarikçiler ve sektör sitelerinden gelecek bağlantılar en güçlü sıralama sinyalidir.
- **Takip:** Search Console › Performans'ta "gözlükçü programı", "optik programı", "medula optik" sorgularının gösterim ve tıklamalarına ayda bir bakın.

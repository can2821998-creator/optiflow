# OptiFlow — güncel durum

*Son güncelleme: 5 Ekim 2026 (4.17.1 / Pro 5.3.0)*

## Sürüm
- **Sunucu 4.17.1**, şema **29**. Masaüstü **OptiFlow Pro 5.3.0** (değişmedi).
- **Canlı site: 4.17.1 main'e gönderildi 05.10 (eski sistemden veri taşıma); masaüstü latest.yml 5.3.0** — GitHub Actions ile otomatik yüklendi (Canlıya al).
  Canlı veritabanı göçü (22 → 27) ilk personel girişinde çalışır; sonucu kontrol edilmedi.
- Özellik anahtarları (hepsi varsayılan kapalı): `hizli_satis`, `garanti`, `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`, `efatura` …
- Testler: sunucu 635/635 (uts 168+34, alis 104+27, hata-hak 70+26, garanti 77+42, sgk-fatura 57+30, seo 48, guncelleme 13, hizli-satis 72+43, tasima 46);
  sunucu entegrasyon (yerel MariaDB): api 46/46, modüller 76/76, garanti 37/37, sgk-fatura 25/25, hizli-satis 20/20, tasima 18/18.
  Masaüstü birim/E2E değişmedi (91/91, 61/61).

## Son oturumda yapılanlar (4.17.1 — eski sistemden taşıma)
- Kullanıcı eski Poyraz 3.43.0 yedeğini (şema 21) paylaştı: olduğu gibi aktarılınca sayfalar hata veriyordu (Poyraz
  "şema 21" ≠ OptiFlow 21). Merkez panel › mağaza › **Eski sistemden veri taşı** (`app/tasima.php`): yükle → önizle →
  TAŞI → ön yedek + aktarım + göçler; GERİ AL. Göçlere kendini onarma (orders.sgk_amount yoksa baştan).
- Gerçek yedekle yerelde denendi (veri depoya girmedi; yerel kopyalar silindi). Rehber: `docs/ESKI-SISTEMDEN-TASIMA.md`.
- Kullanıcıdan beklenen: canlıda yeni (boş) mağaza açıp yedeği yüklemesi.

## Önceki oturum (4.17.0 — hızlı satış)
- Hızlı satış + ürün kataloğu (`hizli_satis`): `app/satis.php`, `hizli-satis.php`, `urunler.php`, `assets/hizli-satis.js`,
  fiş (`print.php?type=satis`), göç v29. Kasa / kasa dökümü / raporlar / kâr-prim entegre. Rehber: `docs/YENILIKLER-4.17.md`.
- Masaüstü 1366 / 1100 / 820 ve telefon 390 px tarayıcıda denendi (gerçek etkileşim: okutma, arama, serbest kalem,
  parçalı ödeme, para üstü, fiş). Kullanıcıya: merkez panelden mağazada "Hızlı satış ve ürün kataloğu"nu açması gerekiyor.

## Önceki oturum (4.16.8 — rakip analizi, yükleme hatası)
- Kullanıcı OptikPanel rakip analizini paylaştı → `docs/OPTIFLOW-RAKIP-ANALIZI-OPTIKPANEL-2026-10-05.md`.
- "Ara ara Beklenmeyen hata": canlı loglar (geçici `oturum/log-oku` iş akışı, maskeli) → hepsi FTP yüklemesi
  sırasında. Düzeltme: geçici dosyayla yükleme + `.guncelleniyor` bakım ekranı (`app/guncelleme.php`).
- Cila: katalog iç notu (göç v28), yedek sayfasında phpMyAdmin dili.
- CI: düşen sunucu testi artık Actions uyarısı olarak yazılıyor (oturumdan log okunamıyor). PHP 8.2 stat önbelleği düzeltildi.
- `oturum/log-oku` uzak dalı oturumdan silinemedi (maskeli log özeti içerir) → GitHub'dan elle silinebilir.
- BULGU: kodda şube kavramı YOK ama tanıtım sayfası vaat ediyordu. Kullanıcı kararı: metin "yakında" (4.16.9) + şube özelliği yapılacak.

## Önceki oturum (4.16.6–4.16.7 — Rehber "OptiFlow Gazetesi")
- Kullanıcı isteği: rehber sayfası eskimiş gazete görünümünde. `app/pages/rehber.php` yeniden yazıldı (CSS satır içi,
  JS yok); fotoğraflar `assets/onizleme/*.webp`'ten sepya + nokta tarama. Masaüstü + telefonda kontrol edildi.

## Önceki oturum (4.16.5 — OptiFlow Pro indirme bağlantısı)
- Kullanıcı bildirdi: sitede uygulama linki yoktu. `indir.php` (sürüm/boyut latest.yml'den, `?dosya=1` doğrudan exe),
  tanıtım sayfasında menü + Pro kartı + alt bilgi, uygulama içi Pro uyarılarında indirme bağlantısı, site haritası.
- Exe imzasız: sayfada SmartScreen "Ek bilgi › Yine de çalıştır" açıklaması var (sertifika alınınca kaldır).

## Önceki oturum (4.16.4 — SEO aracı bağlantısı)
- Merkez panel › **SEO · Google**: Search Console + PageSpeed verisi panelde; hizmet hesabı anahtarı, mülk,
  PSI anahtarı, Google/Bing doğrulama kodu panelden girilir (`app/seo.php`, `storage/seo/`, `storage/gsc-anahtar.json`).
  `seo-veri.php` artık ince; eski config.php anahtarları geçerli. Canlıda daha önce hiç kurulmamıştı (503).
- Kullanıcıdan beklenen: Google Cloud'da hizmet hesabı + JSON anahtar, Search Console'da e-postaya "Kısıtlı" izin
  (adımlar `docs/SEO-SEARCH-CONSOLE.md` §2), sonra panelde "Google'dan verileri al". Mülkü zaten var.
- Bu ortamdan Google'a erişilemediği için gerçek Google yanıtıyla denenmedi; sahte yanıtlarla test edildi.

## Önceki oturum (4.16.3 — SEO)
- Search Console/Bing doğrulama ayarı, Organization/WebSite şeması, www → çıplak alan adı 301, rehbere 4 yazı.
- Kullanıcıdan beklenen: sitemap.xml gönderimi, ana sayfa + yazılar için dizine ekleme isteği. Plesk'te HTTP→HTTPS 301 açık olmalı. Rehber: `docs/SEO-SEARCH-CONSOLE.md`.
- 05.10 kontrolü: site Google'da henüz görünmüyor (site: aramasında sonuç yok).

## Önceki oturum (4.16.2)
- Tanıtım sayfası (`app/karsilama.php`) baştan tasarlandı: göz eşeli başlık, Lite/Pro iki sürüm bölümü ve
  farklar tablosu, gerçek ekran önizlemeleri (`assets/onizleme/*.webp`; arayüz değişince yeniden çekilmeli),
  önce/sonra tablosu, SGK ay sonu örneği. Masaüstü + telefonda kontrol edildi; yatay kaydırma yok.

## Önceki oturum (4.16.1)
- DÜZELTME (kullanıcı bildirdi): SGK payı sipariş başına faturalanıyordu → sipariş faturası yalnızca hasta payı;
  SGK'ya ay sonunda TEK fatura + reçete dökümü (`sgk-fatura.php`, `fatura_sgk_donem_taslagi`, print `sgk_dokum`).
- Kullanıcı senaryosu: siparişte "Medula'ya işlendi/işlenmedi" düğmesi (`orders.medula_islendi_at`); dönem = Medula
  işlem ayı. Ay sonu Medula PDF dökümü yüklenip adet + e-reçete numaraları karşılaştırılır (`app/pdf-metin.php`,
  dış kütüphanesiz PDF okuyucu; Chromium/ReportLab PDF'leriyle denendi, GERÇEK Medula PDF'iyle denenmedi).
- Canlıya otomatik yükleme: `.github/workflows/canliya-al.yml` (Release → FTPS, lftp). Kullanıcı 04.10'da
  FTP_SUNUCU / FTP_KULLANICI / FTP_SIFRE / FTP_KLASOR(.) sırlarını girdi; `ftp-dene.yml` ile bağlantı, klasör
  (canlıda 4.12.0 görüldü) ve yazma izni doğrulandı. İlk gerçek yükleme 4.16.1 ile.
- Yeni testler: `tests/sgk-fatura/` (57+30, örnek PDF), `desktop/tests/server/sgk-fatura-integration.mjs` (25).

## Önceki: masaüstü kaynağının eklenmesi
- `desktop/` (OptiFlow Pro 5.2.0 kaynağı, birim + sunucu + E2E testleri) depoya eklendi.
- `.github/workflows/optiflow-pro-surum.yml` (imzalı Windows derlemesi) eklendi.
- `docs/`: YENILIKLER-4.12.md, MASAUSTU-GECIS-RAPORU.md, 30.09 durum raporu geri getirildi.
- 05.10: analiz için güncel durum + özellik envanteri: `docs/OPTIFLOW-DURUM-RAPORU-2026-10-05.md`.

## Önceki oturumda yapılanlar
- 4.13 ÜTS bildirimleri, 4.14 alış faturası + senet, 4.15 hatalı cam + SGK hak (ayrıntı: `git log`, `VERSION.txt`).
- Devir paketi incelendi; depo bu oturumda kuruldu (geçmiş 4.12.0 hosting paketinden başlar).

## Sıradaki iş
0. **Şube özelliği** (kullanıcı kararı): şube, şubeye göre yetki, merkezden görme, transfer; bitince pazarlama.php 'yakinda' kaldır.
0. **SEO bağlantısı:** kullanıcı hizmet hesabını kurunca panelde ilk veriyi birlikte kontrol et; sorgulara göre yeni rehber yazıları planla.
1. **T.C. no maskeleme (KVKK) — kullanıcı "bir süre ertele" dedi (01.10):** `app/sgk-hak.php` › `sgk_hak_coz` okunan satırları ham saklıyor;
   `sgk_hak_sorgulari.satirlar`'a 11 haneli numara girebiliyor. Kaydetmeden önce maskele + test ekle.
2. İlk canlı yüklemeden sonra canlı veritabanı göçünü (22 → 27) ve sayfaları kontrol et; gerçek Medula PDF'iyle okuyucuyu doğrula.
3. Kullanıcıya sor: sıradaki modül (karar bekleyen: SGK dönem sonu paketi + kesinti defteri).

## Açık sorunlar / doğrulanmamış
- Hızlı satış: ürün etiketi (etiket sihirbazı yalnızca çerçeve basıyor), ürünler için Excel toplu yükleme, müşteri kartında hızlı satış geçmişi, e-Arşiv faturası taslağı henüz yok.
- Göçler v23–v27 yerel MariaDB'de çalıştı; canlı hosting MySQL'inde henüz değil (canlı şema 22).
- Medula PDF dökümünün gerçek biçimi görülmedi: kullanıcıdan örnek (kişisel veriler karartılmış) PDF iste,
  `tests/sgk-fatura/` altına sahte verili benzeriyle test ekle. e-Reçete no biçimi 7 karakter varsayılıyor.
- `ftp-dene.yml`: FTP bağlantısını siteye dokunmadan denemek için (Actions › FTP bağlantı denemesi › Run workflow).
- 4.16.0 öncesi basılmış fişlerdeki takip karekodlarında `m=` yok: müşteri telefonunda hâlâ mağaza girişine düşer
  (yeniden yazdırılan fiş düzelir).
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` hâlâ yok
  (4.12 belgeleri `docs/`'a eklendi). `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.

## Karar bekleyen
- Rakip analizi Faz 1 sırası (TCKN müşteri kartı — KVKK kuralıyla çelişir, hızlı satış ekranı, PD/yükseklik/prizma, destek düğmesi, Ctrl+K).
- SGK dönem sonu paketi + kesinti defteri.

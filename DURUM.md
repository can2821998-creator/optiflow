# OptiFlow — güncel durum

*Son güncelleme: 10 Ekim 2026 (4.30.0 / Pro 5.5.0 / Asistan 1.0.0)*

## Sürüm
- **Sunucu 4.31.0**, şema **33** (10.10 main'e birleştirildi; Sürüm yayınla + Canlıya al). Android **OptiFlow Asistan 1.1.0** (site: indir/asistan/OptiFlow-Asistan.apk; dallarda önizleme: Release `asistan-onizleme`). Masaüstü **OptiFlow Pro 5.5.0**.
- **Canlı site: 4.30.1** (10.10: Sürüm yayınla + Canlıya al başarılı; indir/asistan/OptiFlow-Asistan.apk = Asistan 1.0.1). Release v4.30.1-pro5.5.0.
- Yeni çalışma bilgisayarı (07.10): `C:\Users\Poyraz AB\Documents\optiflow`, Git 2.55 + PHP 8.3 (winget), commit kimliği
  can <can2821998@gmail.com>. Bu bilgisayarda PHP testleri çalışır (seo testi Windows ortamı yüzünden düşer).
- Yerel test kurulumu (kullanıcının PC'si, depoya girmez): `C:\Users\TEKNOPLUS\optiflow-yerel\` — taşınabilir MariaDB
  11.4 (127.0.0.1:3307, başlat: `mariadb-11.4.8-winx64\bin\mariadbd.exe --defaults-file=veri\my.ini`), şifreler ve
  uydurma veri betiği (`ornek-veri.php`) orada; depoda `config.php` (gitignore) yerel. Sunucu: `php -S 127.0.0.1:8080`.
- Çalışma ortamı (06.10): kullanıcının Windows bilgisayarı `C:\Users\TEKNOPLUS\optiflow`; Git 2.55, PHP 8.3, Node 22
  winget ile kuruldu; commit kimliği canaydnl98 <canaydnl98@gmail.com>. Windows'ta 3 bash sayfa testi (alis, sgk-fatura
  sayfa) ve seo testi ORTAM yüzünden düşer (`mktemp` /tmp yolu Windows PHP'ye geçmez, OpenSSL/dosya izni farkı);
  doğrulama CI'da (Linux).


## Son oturum (4.31.0 — sesli asistan bırakıldı → arayan kartı + cevapsız SMS)
- 1.0.1 denemesi: asistan açtı, sesi mağaza telefonundan duyuldu ama KARŞI TARAFA GİTMEDİ (medya kanalı da). Neden:
  Android yankı engelleme cihazın kendi çaldığı sesi uplink'ten siler; sıradan uygulama görüşmeye ses veremez.
  Seçenekler sunuldu (SIP hattı + yönlendirme / sesliyi bırak); kullanıcı "sesli asistanı bırak" dedi.
- 4.31.0 / Asistan 1.1.0: PHONE_STATE alıcısı + CallScreening numarası → çalarken üstte kart (SYSTEM_ALERT_WINDOW) +
  bildirim; açılmadan biterse asistan.php eylem=cevapsiz → geri aranacak + SMS metni → SmsManager. Erişilebilirlik,
  mikrofon, aramayı açma kaldırıldı. tests/asistan 90 test; yerelde uç noktalar curl ile denendi.
- Bekleyen: kullanıcı eski uygulamayı kaldırıp 1.1.0'ı kuracak (imza sırları hâlâ yok), izinler + bağlama, deneme
  araması (kart çıktı mı, açmayınca SMS geldi mi). Sesli akış ileride SIP hattıyla mümkün (kullanıcı şimdilik istemedi).

## Son oturum (4.30.1 — asistan ilk deneme)
- Kullanıcı kurdu (Play Protect'i geçti), deneme araması: asistan açtı ama HİÇ SES yok; erişilebilirlikte "Bu hizmet
  hatalı çalışıyor". Telefon büyük olasılıkla Xiaomi/HyperOS benzeri. 1.0.1: boru yazımı kilitlenmez, çökme + ses
  tanılaması (sayfadaki "Uygulama olayları"), hoparlör düğmesine erişilebilirlikle basma, Ses testi, otomatik başlatma.
- Bekleyen: kullanıcı 1.0.1 ile Ses testi + gerçek arama yapıp "Son olaylar" / "Uygulama olayları" ekranlarını gönderecek.
  İmza sırları (ASISTAN_KEYSTORE_*) GitHub'a henüz eklenmedi → her güncellemede eski uygulama kaldırılmalı.

## Son oturum (4.30.0 — telefon asistanı)
- Kullanıcı (Poyraz Optik): "Mağazaya gelen telefonları yakalamakta zorlanıyorum; asistan otomatik cevaplasın, 'Poyraz
  Optik'e hoş geldiniz' desin, sistemden bilgi verip cevaplasın." Kararlar: üçüncü parti YOK; mağaza telefonu Android 13+
  (Vodafone); asistan aynı telefonda, kimse açmazsa birkaç çalıştan sonra açar. SMS önerisini beğenmedi.
- Sunucu: `app/asistan.php` + uç nokta `asistan.php` + sayfa `telefon-asistani.php`, göç v33, özellik `telefon_asistan`.
  tests/asistan 76 test; yerel MariaDB'de curl ile uçtan uca (bağla → basla → cevap → not → geri aranacak).
- Android `android/` (Kotlin, bağımlılıksız): erişilebilirlik hizmeti + CallScreeningService, acceptRingingCall,
  hoparlör, TTS, AudioRecord → DTMF + EXTRA_AUDIO_SOURCE ile konuşma tanıma. CI'da ilk denemede derlendi.
- **Gerçek telefonda DENENMEDİ.** Riskler: görüşmede mikrofon paylaşımı (erişilebilirlik istisnası), TTS'in karşı tarafa
  hoparlörden duyulması, EXTRA_AUDIO_SOURCE desteği. Tanılama: sayfadaki "Uygulama olayları" (ses seviyesi).
- Bekleyen: kullanıcı GitHub'a ASISTAN_KEYSTORE_B64 / ASISTAN_KEYSTORE_SIFRE sırlarını ekleyecek (dosya sohbette verildi);
  10.10 "gite yükle" ile canlıya alındı; sıradaki: merkez panelden özelliği açıp telefonda kurulum + ilk gerçek arama,
  "Uygulama olayları"na göre düzeltme.

## Son oturum (4.29.0 — teklifte uzak + yakın gözlük, teklif düzenleme; TEKNOPLUS bilgisayarı)
- Kullanıcı: "Tekliflerde düzenle yok; bazı müşteriler yakın + uzak, biz tek gözlük teklifi veriyoruz."
- Teklifte 1–3 gözlük (gözlük 1 quotes satırında, 2–3 quote_gozlukler; şema 32), her gözlük ayrı siparişe döner
  (order-new.php gozluk=N), düzenleme (teklif-yeni.php?id=…, serbest teklif quote.php içinde; siparişe dönmüşse kilitli).
- Testler: tests/teklif/test.php 120 (çoklu gözlük, düzenleme, form → kayıt round-trip). Uçtan uca yerel deneme:
  optiflow-yerel/video/teklif-test.mjs (yeni teklif uzak + yakın → görünüm → döküm → düzenle → yakın gözlüğü siparişe çevir).

## Önceki oturum (4.26.0 — pazarlama stratejisi + fiyatlar)
- Pazarlama stratejisi (konumlandırma, Instagram, laboratuvar/distribütör ortaklığı, tavsiye programı, deneme
  akışı, 90 günlük plan): `docs/PAZARLAMA-STRATEJISI-2026-10-07.html` (claude.ai artifact olarak da yayında).
- Fiyatlar (kullanıcı kararı): Lite 499 ₺, Pro 999 ₺ / ay KDV dahil, yıllıkta %20 indirim. `app/pazarlama.php`
  (`aylik`, `yillik_indirim`, `pz_paket_fiyat`, `pz_tl`), karşılama kartları + SSS + Offer şeması.
  Kullanıcı kararı: 30 gün ücretsiz deneme (kartsız) aynen devam; fiyatlı kartlarda ayrıca belirtildi.
- KDV: fiyatlar KDV DAHİL (kullanıcı teyidi 07.10).
- AÇIK GÜVENLİK SORUSU: `docs/GITHUB-FTP-SIRLARI.xlsx` herkese açık depoda; içinde FTP şifresi varsa şifre
  değiştirilmeli, dosya depodan kaldırılmalı, depo private yapılmalı. Kullanıcıya iletildi, cevap bekleniyor.
- 4.26.1: karşılamada yatay taşmalar (#surumler 3B giriş, #yol gezgini, 360 px altı üst düğme / alt çubuk) giderildi.
- 4.27.0 (kullanıcı: "genel animasyonlar tekdüze, daha yaratıcı olsun" + "yazılım ofisinde değil kısmını özel vurgula"):
  kök hikâyesi sahnesi (#koken), "1 mi 2 mi?" muayenesi, büyüteç, bileme / termal yazıcı / iş emri damgası, kapanış
  eşeli (assets/karsilama-imza.js). Görsel test: başsız Chrome + CDP betiği (depoda değil); uygulama içi tarayıcı
  arka planda rAF/IO çalıştırmadığı için animasyon testine uygun değil.
- 4.28.0 (kullanıcı "başka etkileyici fikir" → dördü de seçildi): Medula aktarım simülatörü (#dene), dükkân adı
  kişiselleştirme (+ kayit.php?isim=), lensmetre kazanç hesabı (#hesap), Ishihara "30" levhası, saate göre selam,
  ay sonu bandı (assets/karsilama-deneyim.js). Kazanç varsayımları sayfada yazılı; değiştirilecekse JS'teki dakikalar.
- 4.28.1: üst menü 981–1300 px arasında kırılmıyor (az gereken bağlantılar daralınca gizlenir).
- Kurumsal kimlik (08.10): docs/kurumsal-kimlik/ — project/ = claude.ai Design System artifact'inin dosyaları (README marka kitabı,
  tokens.json app.css'ten birebir, logolar SVG, bileşen önizlemeleri), png/ = Instagram şablonları (1080²/1080×1920),
  render/*.html = düzenlenebilir şablon kaynakları; yeniden üretmek: `php docs/kurumsal-kimlik/uret.php` + başsız Chrome ile
  render/*.html ekran görüntüsü. docs/ hosting paketine girmez (surum-yayinla.yml siler).
- 4.28.2 (kullanıcı: "ara sayfaları beklemeden simülatöre atıyor, atmasın"): dükkân adı gönderilince otomatik kaydırma kaldırıldı.
- Strateji sıradakiler: ROI hesaplayıcı, kayıtta "Bizi nereden duydunuz?", tavsiye programı.

## Önceki oturum (4.22.1 — pazarlama: tanıtım videosu + iletişim bilgileri)
- app/pazarlama.php: telefon/WhatsApp 0546 743 82 99, unvan Poyraz Optik, adres Dumlupınar Mah. Ulucami Cad. 2/B
  (adreste ilçe/il yok — kullanıcıdan istenebilir). Boş kalanlar: e-posta, instagram, demo_video, fiyatlar,
  google_dogrulama (site Google'da yok → Search Console), kampanya kapalı.
- Tanıtım videosu (depo dışı: optiflow-yerel/video/): hareketli grafik, kare kare Chrome + ffmpeg; seçilen sürüm
  Drum & Bass 172 BPM, marimba + brass slogan ezgisi ("Op-ti-Flow" = Sol–Do–Mi), cihazlar yan yana. 16:9 + 9:16.
  Videolar/betikler henüz git'te değil (öneri: betikler docs/tanitim-video/, mp4 → Releases). Instagram caption verildi.
- Görsel pazarlama yol haritası (12 hafta): docs/PAZARLAMA-GORSEL-YOL-HARITASI-2026-10-07.html
  (claude.ai artifact olarak da yayında). Sıradaki: 7 özellik Reels'i, siteye hero video döngüsü + Instagram
  bağlantısı, öne çıkan kapakları. Instagram: @optiflowtr (pazarlama.php).
- 4.22.2: ana sayfaya tanıtım videosu bölümü (#film, assets/video/). 4.23.x: mercekten açılan perde.
  4.24.0: foropter sahnesi (yapışkan kaydırma, tık tık netleşen kadranlar, fırlayan ekranlar; assets/karsilama-film.js).
  4.24.1: sahne hareket azaltma tercihinde de çalışır (kullanıcının PC'sinde Windows animasyonları kapalı: MinAnimate=0;
  uygulama içi tarayıcı da bu yüzden reduced-motion bildiriyor — kaydırma animasyonunu orada izlemek için bunu bil).
  4.25.0: sayfanın tamamına sahne sistemi (assets/karsilama-sahne.js). Test: optiflow-yerel/video/sayfa-test.mjs
  (başsız Chrome, gerçek kaydırma, bölüm ekran görüntüleri + kare süresi ölçümü; uygulama içi panel arka planda
  rAF/IntersectionObserver çalıştırmadığı için animasyon testine uygun değil). 7 özellik Reels'i üretildi (depo dışı,
  optiflow-yerel/video/cikti/reels/; video hattında SIRA/KANCA/AD ortam değişkenleriyle Reels kipi).

## Önceki oturum (4.22.0 — tüm dökümler, iki tasarım; dal `oturum/dokum`)
- Kullanıcı ilk taslağı (teklif stilinin kopyası) beğenmedi: "daha dolu, şovlu, yaratıcı". İki prototip gösterildi
  (Gözlük / Bilet), kullanıcı "ikisinde de yap" dedi → `setting dokum_tema` (Ayarlar › Genel) + belgede `?tema=` düğmesi.
- `app/dokum.php`: dokum_bas (iki tasarımın başı), dokum_yol, dokum_recete/dokum_mercek (SVG iletki), dokum_halka,
  dokum_egri, dokum_ilerleme, dokum_hesap/buyuk, kartlar, imzalar, son. print.php 12 tür + garanti/sgk/teklif partial'ları.
- Bulutta yerel MariaDB + "Örnek Optik" uydurma verisi, headless Chromium: 15 belge × 2 tasarım A4 tek sayfa
  (taşma ölçümü betikle). Tüm PHP testleri + garanti/sgk-fatura/hızlı satış entegrasyonu geçti.
- 07.10 "gite yükle": main'e birleştirildi; Release v4.22.0-pro5.5.0 ve canlıya alma başarılı (dokum.css canlıda doğrulandı).
- Park edilmiş: `oturum/pwa` (alt menü özelleştirme, çek-yenile, yükleme çizgisi, simge rozeti) — main'e taşınmadı.

## Önceki oturum (4.21.1 — merkez panel + teklif dökümü tasarımı)
- Teklif PDF'i kendi şablonuyla (`app/partials/teklif-dokum.php`, `assets/teklif-dokum.css`); print.php katalog
  teklifini oraya yönlendirir. Headless Chrome ile doğrulandı: A4 tek sayfa, renkler basılıyor (3 seçenek + sözlük).
  Özellik sözlüğü `teklif_ozellik_sozlugu()`; geçerlilik `TEKLIF_GECERLILIK_GUN` = 15.
- Merkez panel stili baştan (`$stil`), koyu görünüm, band/KPI/avatar; diğer sekmeler aynı sınıflarla yeni görünümde.
- Yerelde ayrıca: `optiflow-yerel/pdf-al.php` (giriş yapıp döküm HTML'ini alır) + Chrome `--print-to-pdf` ile sayfa sayımı.

## Önceki oturum (4.21.0 — katalogdan teklif + detaylı cam kataloğu)
- Kullanıcı kurgusu: müşteri → teklif → müşteri kaydı → katalogdan cam (marka, özellik, fiyat) → çerçeve → Medula payı
  düşülür → iskonto → döküm. Kararlar: **önce SGK, sonra iskonto**; personel iskonto sınırı (Ayarlar, varsayılan %10,
  süper sınırsız); **1–3 alternatif cam** yan yana. `app/teklif.php`, `teklif-yeni.php`, `assets/teklif.js`, göç v31.
- Siparişe çevir: `order-new.php?quote_id=&secenek=` tutar = SGK + ödenecek, sgk_amount; reçete girilince SGK payı
  teklif tutarında kalır (`sgk_katki_uygula`). Döküm `print.php?type=quote`, WhatsApp metni. Rehber: `docs/YENILIKLER-4.21.md`.
- Kullanıcı isteği: cam kaydı detaylı → odak tipi (6), hammadde, indeks (1.50–1.90), yüzey, **çoklu kaplama**;
  Ayarlar katalog sekmesi gruplu form. Öneri asistanı (rx.js) çoklu kaplamayı puanlar.
- CSP'nin engellediği satır içi olaylar kaldırıldı (etiket Yazdır, kasa tarihi, sipariş personel ataması, filtreler).
- Yerelde uçtan uca: teklif #4 (3 seçenek) → sipariş #50 bakiye 16.920 = teklif; döküm A4'e sığıyor. tests/teklif 78.

## Önceki oturum (4.20.3 — PWA / mobil, kullanıcı sırası: 3 cila → 2 çevrimdışı → 1 kullanım turu)
- Kurulum: mağaza girişine manifest/iPhone/pwa.js; manifest oturumsuz (bootstrap `$manifestMisafir`); siyah açılış,
  ekran görüntüleri. Çevrimdışı ekranı yeni tema + `assets/offline.js`; sw.js v6.
- YENİ `cevrimdisi_tel` (kullanıcı kararı: masaüstüyle aynı kapsam, telefon dahil; 24 saat): `api.php?action=cevrimdisi`,
  pwa.js AES-GCM (extractable:false) → IndexedDB `optiflow-cevrimdisi`; offline.html listeler. Yerelde uçtan uca denendi
  (şifreli kayıt, çevrimdışı liste + arama, süre dolunca silme, çıkışta silme). Canlıda hiçbir mağazada açık değil.
- Kullanım turu (375 px, dokunmatik): sipariş aç → durum → tahsilat, atölye, hızlı satış. Düzeltilen: sticky kaydet
  çubuğu (body overflow-x clip), kesik düğme yazıları, 31–32 px dokunma hedefleri, atölye panosuna ulaşma.
- Açık: iPhone açılış ekranı görselleri (apple-touch-startup-image) yok; gerçek iPhone/Android cihazda denenmedi.

## Önceki oturum (4.20.2 — tasarım kayması taraması)
- 47 sayfa × (1366/390) × (açık/koyu) otomatik ölçüm (taşma, kontrast, koyu temada açık zemin) + ekran kontrolü.
- Düzeltilen: sipariş WhatsApp menüsü (kesik + beyaz-üstüne-beyaz); telefonda liste sütunları ekran dışı (tema katmanı
  telefon dolgusunu eziyordu); hata sayfası koyu temayı izlemiyordu; merkez db_port kaydı; db adında Türkçe büyük harf.
- Bilerek bırakılan: Kâr raporunda 4 sayısal sütunlu tablo telefonda yana kayar; ekran.php (atölye TV) kendi teması.
- Sıradaki (kullanıcı kararı 06.10): **PWA / mobil taraf**, sonra şube özelliği.

## Önceki oturum (4.20.1 — güvenlik denetimi)
- Genel kod/güvenlik denetimi. SQL (parametreli + beyaz liste), çıktı kaçışı, CSRF, oturum yenileme, PayTR HMAC,
  taşıma SQL doğrulaması, Electron ayarları (sandbox, contextIsolation, dış bağlantı süzgeci) sağlam bulundu.
- Düzeltilen: kurulum.php herkese açılabiliyordu (kritik) → `storage/kurulum-izni`; kontrol.php log/DB hatası sızıntısı;
  mağaza girişi + kayıt hız sınırı (`merkez_hiz_siniri`); personel oturumu ↔ mağaza bağı (`user_magaza`); sipariş
  sorgusu personel IP kilidini tetikliyordu; kayıtta DB hata metni sızıntısı. Test: `tests/guvenlik/test.php`.
- Özellik anahtarları (hepsi varsayılan kapalı): `hizli_satis`, `garanti`, `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`, `efatura` …
- Testler (05.10): sunucu tümü geçti; entegrasyon (yerel MariaDB) api 46, modüller 76, garanti 37, sgk-fatura 26,
  hizli-satis 20, tasima 18, hatirla 24; masaüstü birim 116/116; E2E 74/74.

## Önceki oturum (4.20.0 — koyu görünüm)
- Koyu görünüm (Otomatik/Koyu/Açık, menü üstündeki düğme; `assets/tema.js`, app.css "KOYU GÖRÜNÜM").
- Tanıtım/rehber görselleri önbellekten eski geliyordu → `asset()` / `?v=filemtime`.

## Önceki düzeltme (4.19.1)
- optiflow.com.tr kökü hatırlanan cihazda Siparişler açıyordu → kök her zaman tanıtım ("Uygulamaya git"). `$kokIstek` (bootstrap).

## Son oturumda yapılanlar (4.19.0 / Pro 5.5.0 — tema "Siyah & Bordo")
- Kullanıcı: "köklü, premium, üst segment" → siyah-altın serif denendi, beğenilmedi (serif, altın, çıplak). 3 prototip
  (zümrüt / indigo / bordo) gösterildi, **bordo** seçildi; "daha dolu" isteğiyle başlık bandı, simge rozetleri,
  tonlu istatistik kartları eklendi. Tüm uygulama + giriş + site + merkez panel + masaüstü çubuğu + simgeler.
- Tanıtım önizlemeleri yeniden çekildi (yerelde "Örnek Optik" mağazası, uydurma veri; depoya yalnızca webp girdi).
- Tema tek katmanda: `assets/app.css` sonundaki "4.19.0 — BORDO" bölümü + üstteki :root belirteçleri.

## Önceki oturum (4.18.0 / Pro 5.4.0 — beni hatırla + Medula şifresi)
- Kullanıcı isteği: her seferinde şifre yazmak zor; Medula şifresi Chrome'daki gibi kaydedilip otomatik girilsin.
- Sunucu: mağaza + personel "Beni hatırla" (`app/hatirla.php`, merkez `magaza_hatirla`, göç v30 `oturum_hatirla`),
  profil cihaz listesi, ayar, "Farklı mağaza" artık POST. Masaüstünde kutular varsayılan işaretli.
- Pro 5.4.0: Medula girişini yakala → başarılıysa "kaydedilsin mi?" → DPAPI ile yalnızca o PC'de; sonraki girişte doldur
  (güvenlik kodu/KVKK elle). Şifre değiştirme ekranında güncelleme. `desktop/src/medula/giris.ts`, `src/main/medula-giris-kasasi.ts`.
- Politika değişti: "SGK şifresini okumaz/saklamaz" metinleri her yerde "isterseniz yalnızca bu bilgisayarda" olarak güncellendi
  (SECURITY.md §6). Rehber: `docs/YENILIKLER-4.18.md`.

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
- Güvenlik denetiminden (06.10), düzeltilmedi — tasarım kararı gerekir:
  - Otomatik sağlama modunda tüm mağaza veritabanları merkezle AYNI MySQL kullanıcısını kullanır ve şifreler
    `magazalar.db_sifre`'de düz metin durur: tek bir SQL açığı tüm mağazalara yayılır. (Canlı Plesk "beklemede"
    modunda; orada her mağazanın kendi kullanıcısı var.) Öneri: mağaza başına kullanıcı + db_sifre'yi şifrelemek.
  - cron anahtarı adreste (`cron.php?anahtar=`) → sunucu erişim kayıtlarına düşer; başlıkla (X-Cron-Anahtar) çağırmak daha iyi.
  - Personel girişinde kullanıcı adı başına kilit (5 deneme) başkasının hesabını 15 dk kilitlemeye açık (bilinen ödünleşim).
  - Masaüstü exe imzasız (SmartScreen). Büyük tek dosyalar (merkez-panel.php 82 KB, order.php 59 KB) bakımı zorlaştırıyor.
- Tema: her sayfa tek tek gözden geçirilmedi (ana ekranlar, giriş, site, telefon kontrol edildi). Etiket baskıları ve senet (bono) eski görünümde.
- Pro 5.4.0 Medula doldurma gerçek Medula giriş ekranında denenmedi (sentetik sayfayla E2E). Alan bulma: şifreden önceki
  yazı kutusu = kullanıcı adı, sonraki = güvenlik kodu. Kullanıcıdan ilk girişte kontrol etmesi istendi.
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

# OptiFlow — güncel durum

*Son güncelleme: 23 Ekim 2026 (4.45.0 canlıda / Pro 5.6.0 / Asistan 1.1.0 askıda)* — Claude Code devir notu: **`DEVAM-2026-10-11.md`**

**Sunucu taşındı (23.10, ~16:50 canlı):** TürkTicaret paylaşımlı Plesk → kiralık sunucu (Plesk Obsidian, Administrator).
Rehber `docs/SUNUCU-TASIMA.md`. DNS (TürkTicaret paneli) A `optiflow.com.tr` → yeni IP; www/ftp CNAME; MX/mail TürkTicaret'te
kaldı. DB'ler localhost2 (merkez) + poyrazoptik, kullanıcı `optiflowdb`; magazalar satırı phpMyAdmin'den, config kurulum.php
(+storage/kurulum-izni) ile güncellendi. Eski httpdocs silindi (yedek: kullanıcıdaki site.zip); eski DB'ler 1–2 hafta durur.
SSL: eski TürkTicaret AutoSSL (Sectigo) .pem taşındı — yeni sunucuda YENİLENMEZ → bitişten önce Let's Encrypt'e geç.
Web + masaüstü çalışıyor. Bekleyen: GitHub FTP sırları (FTP_SUNUCU yeni IP/alan adı, FTP_KULLANICI `optiflow`, FTP_SIFRE,
FTP_KLASOR httpdocs) — güncellenmezse "Canlıya al" eski hostinge yükler; yeni Plesk'te zamanlanmış görev (cron.php 10 dk);
telefon asistanı denemesi; yeni Plesk'te info@ / Mail hizmeti kapatılmalı (e-posta TürkTicaret'te); Tools › Prohibited
Domain Names "resolve to other servers" yeniden açılabilir; Plesk güncellemesi yarım ("Repair"); kullanıcının hosts
dosyasında satır kalmış olabilir; Chrome "Güvenli DNS" Google'a alındı. TürkTicaret hostingi iptal edilirse e-posta da gider.

## Sürüm
- **Sunucu 4.45.0** (23.10 main'e birleştirildi: 4.43.0–4.45.0), şema **37** (v36: atölye parafları + orders.raf_yeri; v37: odeme_plani + kasa devir/küpür). Android **OptiFlow Asistan 1.1.0** (site: indir/asistan/OptiFlow-Asistan.apk; dallarda önizleme: Release `asistan-onizleme`). Masaüstü **OptiFlow Pro 5.6.0**.
- **Canlı site: 4.45.0** (23.10 "Sürüm yayınla" + "Canlıya al" başarılı; Release v4.45.0-pro5.6.0; göç v36–v37 ilk istekte çalışır).
  Not: TEKNOPLUS bilgisayarının hosts dosyasında taşıma denemesinden kalan `optiflow.com.tr → 31.186.24.236` (yeni sunucu,
  henüz SSL yok) satırları var; bu PC'de site sertifika uyarısıyla yeni sunucudan açılır. Genel DNS eski hostingi gösterir.
- Yeni çalışma bilgisayarı (07.10): `C:\Users\Poyraz AB\Documents\optiflow`, Git 2.55 + PHP 8.3 (winget), commit kimliği
  can <can2821998@gmail.com>. Bu bilgisayarda PHP testleri çalışır (seo testi Windows ortamı yüzünden düşer).
  15.10: Node 22 winget ile kuruldu (PATH'e girmedi: `%LOCALAPPDATA%\Microsoft\WinGet\Packages\OpenJS.NodeJS.22_*\node-v22.23.2-win-x64`);
  `desktop/` içinde `npm ci && npm run typecheck && npx vitest run` çalışıyor.
- Yerel test kurulumu (kullanıcının PC'si, depoya girmez): `C:\Users\TEKNOPLUS\optiflow-yerel\` — taşınabilir MariaDB
  11.4 (127.0.0.1:3307, başlat: `mariadb-11.4.8-winx64\bin\mariadbd.exe --defaults-file=veri\my.ini`), şifreler ve
  uydurma veri betiği (`ornek-veri.php`) orada; depoda `config.php` (gitignore) yerel. Sunucu: `php -S 127.0.0.1:8080`.
- Çalışma ortamı (06.10): kullanıcının Windows bilgisayarı `C:\Users\TEKNOPLUS\optiflow`; Git 2.55, PHP 8.3, Node 22
  winget ile kuruldu; commit kimliği canaydnl98 <canaydnl98@gmail.com>. Windows'ta 3 bash sayfa testi (alis, sgk-fatura
  sayfa) ve seo testi ORTAM yüzünden düşer (`mktemp` /tmp yolu Windows PHP'ye geçmez, OpenSSL/dosya izni farkı);
  doğrulama CI'da (Linux).


## Son oturum (4.45.0 — Veresiye defteri: taksit, ekstre, küpür, devir; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (23.10): "canlıya almadan 3. adıma geç: veresiye / kasa defteri".
- Yapılan: app/defter.php (taksit planı kur/dağıt/eşitle, ekstre, borçlu müşteriler, küpür, devir); tahsilat.php →
  "Veresiye defteri" (varsayılan Müşteri defteri sekmesi), defter.php ekstre (tahsilat, taksitlendir, yazdır, WhatsApp
  hesap özeti), kasa.php küpür sayımı + yarına devir (assets/kasa.js). Ödeme yazılan 3 yerde söz tarihi plana eşitlenir.
- Denendi: yerelde 3 taksit kur → ilk taksit tahsil → plan/ekstre/söz; küpür toplamı; 22.10'u 500 ₺ devirle kapatıp
  23.10'da "Dünden devir" (yerel veride 22.10 sayımı deneme kaydı olarak duruyor). Testler: defter 61; tümü geçti.
- Pilotta sor: mağaza gün sonu kasada para bırakıyor mu (devir), taksitli satışta senet/bono basılıyor mu?

## Önceki oturum (4.44.0 — Zarfsız atölye: adım parafı, kutu / raf; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (23.10): "canlıya almadan önce 2. adıma geç: atölye iş takibi (raf yeri, paraf, zarfsız)".
- Yapılan: app/atolye.php (adım at / geri al / kutu-raf / boş kutu önerisi), siparişte "İş kartı" (iş emrinin numaralı adım
  tablosu ekranda, sıradaki adım satırında), panoda kart köşesinde kutu no + paraflar + sıradaki adım; sürükle-bırak
  (workshop.js) formu kendisi kurar, Hazır'a bırakmak raf ister; iş emri fişinde "Kutu". Göç v36.
- Denendi: yerel MariaDB'de kesim → montaj → kontrol (raf) → hazır → geri al; pano ve telefon ekranı. Testler: atolye 45; tümü geçti.
- Açık soru (pilotta sor): kutu numaraları mağazada fiziksel tepsiye yazılı mı? Haber adımı, WhatsApp "hazır" mesajı
  gidince otomatik atılabilir (yol haritası 4. adım).

## Önceki oturum (4.43.0 — Yalın arayüz 1: görev odaklı iskelet; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (23.10): "mağazaların kâğıt kalem alışkanlığını bitirmeye eğil, önce köklü arayüz güncellemesi ve
  sadeleştirme"; "tasarım dilinde mükemmeliyetçiliği koru, kullananlar tekrar kullanmak istesin". Seçimler: görev odaklı
  6 bölüm; kâğıt işleri: atölye iş takibi, veresiye/kasa defteri, aranacaklar listesi, tamir/hizmet fişi; herkese birden.
- Yapılan: menü 6 bölüm + bölüm sekme şeridi (app/layout.php `bolumler`), "Yalın" CSS katmanı (bant/süs/hareket yok,
  tipografik başlık, mono sayılar, düz düğmeler), açık görünüm varsayılan. Yerelde masaüstü/telefon/koyu ekranlarla denendi.
- Testler: arayuz 57 (+13); tümü geçti. main'e birleştirme (= canlıya çıkış) kullanıcı onayı bekliyor.

## Önceki oturum (4.42.0 — KVKK: T.C. kimlik no saklanmaz; Poyraz AB bilgisayarı, main)
- Kullanıcı (17.10): "şubeyi sona bırakarak devam et" → bekleyen KVKK işi (T.C. maskeleme) yapıldı.
- `tc_maskele` (app/sgk.php): Medula metni KAYDETMEDEN önce 0 ile başlamayan 11 haneli sayılar gizlenir — Pro aktarımı,
  eklenti köprüsü, sgk_parse ('tc' hep boş), hak sorgusu satırları. Aktarım ekranında T.C. satırı kalktı.
- Göç v35 (şema 35): eski sgk_incoming / sgk_hak_sorgulari kayıtları temizlenir. Test: kvkk 29; tümü geçti.

## Önceki oturum (4.41.0 — sadeleştirme 4: reçete, teklif görüntüleme, ayarlar; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): önerilen üç adayla ("hepsiyle") devam. rx.php: PD/yükseklik ve öneri asistanı katlanır, tablo 4 sütun;
  quote.php: gözlük özeti tek satır, hesap dökümü katlanır, dönmüş gözlükte yalnız seçilen; settings.php: gruplu sekmeler +
  Genel bölüm başlıkları. Testler: tezgah 90; tümü geçti. Başsız Chrome ile ekranlar kontrol edildi.

## Önceki oturum (4.40.0 — sadeleştirme 3: ana ekran, atölye panosu, müşteri kartı; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): "sadeleştirme işlerine dönelim". Ana ekranda tekrar eden sayı kutuları çiplere, fal kutusu kalktı,
  takım hedefi alta; atölye panosunda ilgisiz sayılar/düğmeler kalktı; müşteri kartında formlar katlanır. Testler: tezgah 87.
- Sıradaki aday (onay bekliyor): rx.php (reçete düzenleme) ve teklif görüntüleme (quote.php) sadeleştirme; Ayarlar
  sekmelerini gruplama.

## Son düzeltme (4.39.2 — fişte UZAK / YAKIN ayrı bloklar, cam rozetleri, müşteri nüshasında gözlük türü) · (4.39.1 — Medula tek yakın gözlük aktarımı; TEKNOPLUS, dal oturum/sube)
- Kullanıcı bildirdi: tek yakın gözlük aktarılırken ADD aranıyor, CYL'li yakın numaralar geçmiyordu. `sgk_lens_design`
  yalnız yakın bölümünü "tek_odak_yakin" tanır; `tezgah_on_dolum` ve sgk-aktar yakın SPH/CYL/AKS'yi reçete satırına yazar;
  `rx_from_post` tek odak yakında ADD istemez. Test: tezgah 83. Bekleyen: gerçek Medula okuma gözlüğü reçetesiyle deneme.

## Önceki oturum (4.39.0 — sadeleştirme 2: sipariş sayfası, teklif formu, depo gönderimi tek yol; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): önerilen üç sadeleştirmeyle devam.
- Yapılan: order.php tek tık sonraki aşama + katlanır kartlar (elle durum, sipariş bilgileri, takip, geçmiş); teklif
  formunda ek seçenekler istenince, iskonto katlanır; gün sonu depo WhatsApp gönderimi cam sipariş fişi olarak kaydedilir
  (`depo_gonder`), kart `app/partials/depo-karti.php` ile depo sayfasının Eksik sekmesinde de; ana ekran tek giriş.
- Denendi: yerelde sipariş, teklif, depo sayfaları (başsız Chrome), depo gönder → fiş → geri al. Testler: kagitsiz 56,
  tezgah 78; tümü geçti.
- 17.10: main'e birleştirildi, canlıda.

## Önceki oturum (4.38.0 — sadeleştirme: teklif isteğe bağlı, tek ekran, menü grupları; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): teklif zorunlu olmasın (sözlü anlaşma), menü / adım / görsel / alan olarak sadeleştir.
- Yapılan: tezgah.php tek ekran (müşteri + reçete + sipariş → fiş; "Önce teklif hazırla" ikincil), adımlar 4
  (Reçete · Müşteri · Sipariş · Fiş), isteğe bağlı alanlar katlanır/koşullu (tezgah.js), menü `menu_gruplari()` ile
  Satış / Atölye ve stok / SGK / Kasa / Diğer. Testler: tezgah 76; tümü geçti. Yerelde uçtan uca denendi.

## Önceki oturum (4.37.0 — cam siparişleri + depo tek sayfa; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): "cam siparişleri ile depo stok sekmesinin farkı nedir, ikisini birleştir teke düşür".
- Yapılan: stock.php = "Cam siparişleri · depo" (Eksik / Siparişte / Gelenler / Sipariş fişleri); eksikten tedarikçiyle
  fiş oluşturulur (cam-siparis.php?id= fiş ekranı kaldı, listesi buraya yönlenir); menüden "Cam siparişleri" ve özellik
  anahtarı cam_siparis kalktı. Yerelde uçtan uca denendi (fiş → gönderildi → geldi → atölye). Testler: kagitsiz 52.
- Kâğıtsız tezgâhın "depoya WhatsApp" kartı ayrı bir yol olarak duruyor (fişsiz, tek tuş).

## Önceki oturum (4.36.0 — iş emri dikey kart + müşteri nüshası; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (17.10): iş emri kesince dik kullanılacak, daha güzel olsun; müşteri nüshası yatay kalsın, daha yaratıcı;
  "bilet / gözlük çizimi gibi tasarımları güzel yapamıyorsun" → çizim yerine tipografi.
- Yapılan: iş emri 148,5 × 210 dikey kart, yarım sayfaya 90° döndürülerek basılır (gece bandı, 01–04 numaralı bölümler,
  Tamam/Paraf/Saat tablosu; kalabalıkta `.sik`). Müşteri nüshası: göz tablosu (harfler küçülerek "GÖZLÜĞÜNÜZ
  HAZIRLANIYOR"), "Sırada ne var?" (ilerleme çubuğu kaldırıldı), nişangâh filigranı; sağda teslim, karekod, ödeme.
- Denendi: başsız Chrome PDF (A4 1 sayfa, yarım sayfa 2), 3 sipariş (en kalabalığı 4 cam) taşmasız. Testler: tezgah 74.
- 17.10: main'e birleştirildi, canlıda. Bekleyen: gerçek yazıcıda baskı + kesip dik okuma denemesi.

## Önceki oturum (4.35.0 — sipariş fişi kurumsal kimlikle; TEKNOPLUS, dal oturum/sube)
- Kullanıcı (16.10): fişi kurumsal kimlikle güzelleştir; atölye tarafı montaj personeli için detaylı ve rahat okunur,
  müşteri tarafı takip karekoduyla görsel, akılda kalıcı, yaratıcı.
- app/partials/siparis-fisi.php + assets/siparis-fisi.css baştan: üst yarı İŞ EMRİ (17 pt mono reçete, montaj ölçüleri
  kutusu, göz başına camlar + stok, tekliften seçilen katalog camı, çerçeve raf/barkod, parafli adımlar, "Siparişi aç"
  karekodu); alt yarı MÜŞTERİ NÜSHASI (göz eşeli selamlama, nişangâh içinde takip karekodu, durum çizgisi, ödeme).
  Başsız Chrome PDF: A4 1 sayfa, yarım sayfa 2 sayfa. Testler: tezgah 73.
- 16.10: main'e birleştirildi, canlıda. Bekleyen: gerçek yazıcıda (s/b lazer) baskı denemesi.

## Önceki oturum (4.34.0 — tek akış; TEKNOPLUS bilgisayarı, dal oturum/sube)
- Kullanıcı (16.10): "akışı baştan kurgulayıp sadeleştirmemiz gerek, her şey çok karıştı: reçete → Medula → OptiFlow'a aktar,
  müşteri kaydı → teklif → siparişe çevir → müşteriye kâğıt döküm → tezgâha gönder". Seçimler: "tezgâha gönder" = stoktaki
  camlar atölyeye + eksikler depoya; eski girişler menüden kalksın; teklif atlanabilir.
- Yapılan: tezgah.php = akışın girişi (① reçete, ② müşteri; sipariş AÇMAZ → `bekleyen_receteler`, göç v34); teklif
  `quotes.recete_id` ile reçeteyi taşır; order-new.php reçeteyi siparişe yazar (`recete_siparise_uygula`, app/tezgah.php):
  stoktaysa "Atölyede", eksikse depo listesi; fiş + müşteri nüshası her mağazada. Menü: "Yeni müşteri · reçete";
  "SGK reçete aktar" menüden kalktı. Adım çubuğu `akis_cubugu()`. Ayrıntı: VERSION.txt 4.34.0.
- Denendi: yerel MariaDB'de uçtan uca (elle reçete → 2 gözlüklü teklif → uzak sipariş atölyede, yakın eksik camla; teklifsiz
  yol; fiş). Testler: tezgah 69, teklif 127; diğerleri geçti (Windows'ta bilinen seo + alis/sgk-fatura sayfa testleri hariç).
- DOĞRULANMADI: Pro'dan gerçek Medula aktarımıyla (sgk-aktar.php?gelen= artık tezgah.php?gelen='e yönlenir).
- 16.10: main'e birleştirildi, canlıda (optiflow.com.tr/VERSION.txt = 4.34.0).

## Önceki oturum (4.33.0 / Pro 5.6.0 — tezgâh akışı; Poyraz AB bilgisayarı, main)
- Kullanıcı (15.10): "müşteri reçeteyi verir, tüm kaydı OptiFlow üzerinden Medula'dan kontrol edip aktarıp tezgâha bir
  çıktı alacak şekilde kurgulayalım". Seçimler: Pro reçete no'yu Medula'ya kendisi yazsın; tek ekranda cam + stok,
  çerçeve, tutar + kapora, telefon; çıktı = fiş + müşteri nüshası.
- Yapılan: `tezgah.php` (app/pages/tezgah.php + app/tezgah.php + assets/tezgah.js), Pro `medula/sorgu.ts` +
  `receteSorgula` köprüsü, fişte müşteri nüshası ve otomatik yazdırma. Akış SQLite deneme betiğiyle uçtan uca denendi
  (Medula örnek metni → form → kayıt → fiş; A4 tek sayfa, yarım sayfa 2 sayfa). Testler: tezgah 42/42, Pro 127/127.
- DOĞRULANMADI: Pro'nun gerçek Medula reçete sorgu ekranında numarayı bulup "Sorgula"ya basması (etiket sezgisi:
  "e-Reçete No"/"Reçete No"). İlk denemede kullanıcıdan geri bildirim al; kutuyu bulamazsa Pro bildirimi
  "sorgu ekranını açın" der ve numara elle yazılır. Gerekirse sorgu ekranının HTML'i (kişisel veri karartılmış) istenip fixture eklenmeli.
- Sıradaki: pilot mağazada Tezgâh'ı denet (Kâğıtsız tezgâh açık olmalı + Pro 5.6.0 kurulu); ardından önceki öneri
  "ay sonu sadeleştirme" (e-fatura toplamı + Excel, Medula'ya işlenecekler + tek tuş).

## Önceki oturum (4.32.0 — kâğıtsız tezgâh; Poyraz AB bilgisayarı, dal oturum/kagitsiz → main)
- Kullanıcı (12.10): pilotta bile reçete Medula'dan kontrol edilip ELLE forma yazılıyor, form zarfa giriyor; eksik
  camların numaraları gün sonu ayrı kâğıda yazılıp fotoğrafı WhatsApp'la depoya gidiyor ("Türkiye'deki optiklerin %90'ı").
  Medula işlemi toplu (gün/hafta sonu), SGK faturası mağazanın kendi e-fatura programından kesiliyor → ay sonu döküm
  karşılaştırması pratikte 3. iş. Odak: OptiFlow kâğıdı kaldırmalı.
- 4.32.0 (özellik `kagitsiz`, varsayılan kapalı): fiş + Siparişler ana ekranında gün sonu "depoya WhatsApp"
  kartı (gruplu, müşterisiz metin, tek tuş, geri al). Testler 45/45. Önizleme: veritabanısız betikle A5 PDF tek sayfa.
- Kullanıcıdan beklenen: merkez panelden pilot mağazada "Kâğıtsız tezgâh"ı aç; Tedarikçiler'de depoyu telefonuyla ekle;
  bir gün yarım sayfa fiş + gün sonu gönderimiyle çalışıp geri bildirim ver (fişin düzeni alıştıkları forma uyuyor mu?).
- 4.32.1 (kullanıcı: "A4'ün yarısına sığsın, ortada kesme çizgisi, kalan yarım sayfayı yazıcıya koyup kullansınlar" +
  "ekstra zarf değil, siparişin içindeki fiş üzerinden"): ayrı zarf formu kaldırıldı; siparişin "Fiş"i yarım sayfa
  (A4 üst yarı + kesme çizgisi / kesilmiş yarım sayfa), ayrıntılı A4 müşteri fişi &tam=1. app/partials/siparis-fisi.php.
- Sıradaki (öneri, onay bekliyor): ay sonu sadeleştirme — döküm karşılaştırması isteğe bağlı; ana ekran e-fatura
  programına girilecek toplam + Excel liste; "Medula'ya işlenecekler" listesi + "hepsi işlendi" tek tuş (toplu işleme
  uygun). Pazarlama mesajı "kâğıt sipariş formu yok" olarak güncellenecek (site, broşür, Instagram).
- Ayrıca bu oturumda: docs/satis-kiti/ (A4 broşür, demo senaryosu, itirazlar, WhatsApp şablonları, laboratuvar teklifi,
  ziyaret formu; `php docs/satis-kiti/uret.php` + başsız Chrome PDF).
## Önceki oturum (4.31.1 — telefon asistanı askıya alındı; TEKNOPLUS)
- Kullanıcı (12.10): "Telefon asistanını askıya al, menüde vs görünmesin; şube özelliğine geçelim."
- app/ozellik.php: 'askida' => true (telefon_asistan) → ozellik_durumu her mağazada 'kapali', merkez panel listeleri
  ozellik_yonetilenler() kullanır, panelden kaydetmede askıdaki seçim korunur; asistan-api.php askıdayken reddeder.
  Geri açmak: tanımdaki 'askida' satırını sil. Release iş akışı APK'yı yine üretir (zararsız).
- Sıradaki: şube özelliği (aynı oturumda başlandı).

## Önceki oturum (4.31.0 — sesli asistan bırakıldı → arayan kartı + cevapsız SMS)
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
*Kullanıcı (23.10): öncelik kâğıt-kalemi bitirmek + Yalın arayüz. Kullanıcı (17.10): "şubeyi sona bırakarak devam et".*
- **Kâğıtsız + Yalın yol haritası (sırayla):** (1) ✔ iskelet 4.43.0; (2) ✔ atölye iş takibi 4.44.0 (kutu/raf, paraf, zarfsız); (3) ✔ veresiye/kasa defteri 4.45.0 (taksit, ekstre, küpür, devir);
  (4) aranacaklar listesi — hazır, kontrol zamanı, tamir: tek liste + tek tuş WhatsApp/arama sonucu;
  (5) tamir/hizmet fişi — vida/nazal/ayar/tamire bırakılan gözlük (yeni, siparişten hafif). Her adımda kalan ekranları
  Yalın dile geçir (masaüstü + telefon + koyu ekran görüntüsüyle kontrol).
0. **Tek akış pilotu (4.34.0, canlıda):** pilot mağazada "Yeni müşteri · reçete" ile bir gün çalış:
   Pro'dan gerçek Medula aktarımı, fiş + nüsha düzeni, atölye/depo ayrımı için geri bildirim.
1. **SEO bağlantısı:** kullanıcı hizmet hesabını kurunca panelde ilk veriyi birlikte kontrol et; sorgulara göre yeni rehber yazıları planla.
2. Gerçek Medula PDF'iyle SGK döküm okuyucusunu doğrula (kullanıcıdan kişisel verisi karartılmış örnek PDF).
3. Kullanıcıya sor: sıradaki modül (karar bekleyen: SGK dönem sonu paketi + kesinti defteri).
4. **Şube özelliği (en sona):** şube, şubeye göre yetki, merkezden görme, transfer; bitince pazarlama.php 'yakinda' kaldır.

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

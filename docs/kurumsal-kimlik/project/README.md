OptiFlow, gözlükçüler ve optik atölyeler için yönetim sistemidir. Marka bir yazılım ofisinde değil, bir optik atölyesinde (Poyraz Optik) doğdu; kimliğin her parçası o tezgâhın aletlerinden gelir: göz eşeli, foropter mercekleri, lensmetre nişangâhı, dioptri değerleri, iş emri fişi ve damga. Bu sayfa web sitesi, uygulama ve Instagram için tek kaynaktır.

## Marka özü

- **Söz:** "Reçeteyi elle yazmazsınız. SGK katkı payı ve ay sonu faturası hazırdır."
- **Köken cümlesi:** "OptiFlow bir yazılım ofisinde değil, bir optik atölyesinde doğdu." Bu cümle değiştirilmez, kısaltılmaz; Instagram'da ve sitede "yazılım ofisinde" kısmı çizilerek gösterilir (bkz. `Damga`).
- **Kime:** Optik mağaza sahibi, optisyen, atölye çalışanı. Medula'yı, SGK faturasını, "gözlüğüm hazır mı?" telefonunu bilen kişi.
- **Vaat:** Zaman. Her mesaj bir işi kısaltır: elle yazma, hesap makinesi, defter, telefon.

## Yazım ve ses

- Türkçe, "siz" diliyle, sade ve tezgâhtan konuşur: "Aktar'a basarsınız, değerler siparişe gelir."
- Özellik değil iş anlatılır. "SGK fatura otomasyonu" yerine "Ay sonu SGK faturası bir akşamınızı yemiyor."
- Rakam somut ve gerçektir: "3,7 saniye", "48 reçete", "30 gün". Uydurma istatistik, "binlerce mutlu müşteri" yok.
- Ünlem, emoji ve "devrim niteliğinde" gibi abartı yok. Soru sorulabilir: "Hangisi daha net? 1 mi, 2 mi?"
- **Ad yazımı:** her zaman **OptiFlow** (O ve F büyük, bitişik). "Optiflow", "OPTIFLOW", "Opti Flow" yazılmaz; büyük harfe çevrilen etiketlerde bile ad olduğu gibi kalır. Sürümler: **OptiFlow Lite** (tarayıcı), **OptiFlow Pro** (Windows, Medula'nın yanında).
- Meslek terimleri doğru yazılır: Medula, SGK, e-Reçete, ÜTS, katkı payı, dioptri; ondalık virgülle ve eksi işaretiyle: `−2,00`, `0,25` (kısa çizgi değil, U+2212).
- Büyük harfe çevrilen etiketlerde sayfa dili `lang="tr"` olur ki "rehberi" → "REHBERİ" doğru çıksın.
- Fiyatlar KDV dahil yazılır: "499 ₺ / ay · KDV dahil". Deneme her zaman "30 gün ücretsiz · kart gerekmez".

## Renk

- Zemin `paper`, kart `surface`, metin `ink` / `ink-2`. Kimliğin rengi **bordo**: eylem `brand`, vurgu metni `brand-ink`, açık dolgu `brand-soft`.
- İmza degradesi: `brand-bright` → `brand` → `brand-deep`, 135°. Logo işaretinde, dolu düğmede ve en büyük göz eşeli satırında kullanılır; başka yerde kullanılmaz.
- **Gece** (`night`, `night-2`) markanın ikinci yüzüdür: koyu bantlar, Instagram koyu şablonları, uygulama kenar çubuğu. Gece üstünde metin `on-night` / `on-night-muted`, vurgu `coral`.
- `coral` yalnız gece zemininde vurgudur; açık zeminde metin olarak kullanılmaz.
- Durum renkleri (`ok`, `warn`, `danger`) bordodan ayrıdır ve her zaman bir sözcükle birlikte kullanılır ("Hazır", "Geciken").
- Mavi-mor degradeler, neon, ikinci bir marka rengi yok.

## Tipografi

- Tek aile: **Manrope** (değişken, 200–800). Başlıklar 800, gövde 500. Yardımcı aile: **IBM Plex Mono** 500, yalnız etiket ve veri için (dioptri, e-reçete no, sipariş no, tutar, "SGK REHBERİ" gibi üst etiketler).
- **Göz eşeli** markanın tipografik imzasıdır: bir cümle satır satır küçülerek dizilir (`esel-1` → `esel-4`), her satırın sağında keskinlik değeri (`0,1 … 1,0`) mono ile yazılır, satırlar `line` ile ayrılır, eşelin üstünde kalın `ink` çizgi durur. Cümle son satırda noktayla biter. Bkz. `GozEseli`.
- Başlıklarda harf aralığı eksiye, eşelde artıya ayarlanır (tokens'taki değerler). Başlıklar `text-wrap: balance`.
- Yazı tipleri SIL Open Font License ile serbesttir (depoda `assets/fonts/OFL-*.txt`).

## Logo

- Logo, bir göz/mercek işaretidir: daire içinde göz, ortasında bebek; −10° eğik. Geometri `app/pazarlama.php` → `pz_logo()` ile birebir aynıdır; yeniden çizilmez.
- Kilit (yatay): işaret + "OptiFlow" kelimesi, Manrope 800, −0,02em. İşaret yüksekliği yazı boyunun 1,5 katı, ara boşluk 0,5 katı.
- Varyantlar (`Logolar` grubu): renkli işaret (degrade), gece rengi tek renk, beyaz tek renk, uygulama simgesi (gece zemininde rounded kare).
- Açık zeminde renkli işaret + `ink` kelime; gece zemininde renkli işaret + beyaz kelime. Fotoğraf üstünde tek renk beyaz.
- Boşluk: işaretin her yanında en az işaret çapının yarısı kadar boş alan. En küçük boyut: işaret 20 px (ekran), 6 mm (baskı).
- Yapılmaz: işareti döndürmek veya düzeltmek, degradeyi değiştirmek, gölge/kontur eklemek, kelimeyi başka yazı tipiyle yazmak, işareti bir çerçeve içine sıkıştırmak.

## Motifler

- **Lensmetre nişangâhı:** iç içe üç daire + artı + çentikler; gece zemininde `coral` %20–50 opaklıkla, ince çizgi. Arka plan dokusu olarak tek tane, büyük ve kenardan taşarak.
- **Foropter:** iki yuvarlak mercek, koyu gövde. "1 mi, 2 mi?" karşılaştırmalarında; bulanık mercek eski yöntemi, net mercek OptiFlow'u taşır.
- **Damga ve çizik:** kırmızı kalemle çizilmiş eski söz + eğik "değil," damgası; sipariş adımlarında "TAMAM" damgası. Bkz. `Damga`.
- **Fiş:** termal yazıcı fişi (mono yazı, kesik çizgiler, tırtıklı alt kenar) SGK dökümü ve özet için.
- Simge seti yok; arayüz simgeleri ince çizgili (stroke 2, yuvarlak uç). Emoji kullanılmaz.

## Instagram

- Hesap: **@optiflowtr**. Profil fotoğrafı: gece zemininde renkli işaret (`ProfilVeKapaklar`). Bio bağlantısı `optiflow.com.tr/?utm_source=instagram&utm_medium=bio`.
- Kare gönderi 1080 × 1080, hikâye 1080 × 1920; kenarlarda 80 px (`ig-kenar`) boşluk. Logo her zaman sol altta, 60 px işaret + 40 px kelime.
- Dört şablon, dört içerik ayağı: **Bilgi kartı** (SGK ve Medula rehberi, kaydırmalı), **Özellik kartı** (göz eşeli), **Kök hikâyesi**, **"1 mi, 2 mi?"** karşılaştırma. Hikâyede ay sonu ve kampanya şablonu.
- Izgara ritmi: gece (koyu) ve paper (açık) şablonlar dönüşümlü; aynı renk arka arkaya en fazla iki.
- Öne çıkanlar: SGK · ATÖLYE · FİYAT · REHBER · DEMO; nişangâh ve mono etiketle.
- Görsellerde gerçek ekran görüntüsü yalnız "Örnek Optik" deneme mağazasından; gerçek müşteri adı, telefon, T.C. no asla görünmez.

## Bileşenler ve dosyalar

- Sitede dolu düğme `brand` degradesi + `on-brand`, çizgili düğme `ink` kenar; köşe `radius-btn`. Bkz. `Buton`.
- Hazır PNG'ler `Instagram` varlık grubunda; kaynak şablonlar depoda `docs/kurumsal-kimlik/`.

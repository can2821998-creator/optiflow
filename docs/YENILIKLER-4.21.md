# OptiFlow 4.21 — Katalogdan teklif ve detaylı cam kataloğu

## Kısaca

Müşteri geldiğinde **Teklifler › Yeni teklif** ekranında:

1. **Müşteri** — kayıtlı müşteriyi arayıp seçin ya da ad, soyad, telefonla yeni kaydedin.
2. **Çerçeve** — stoktan seçin (fiyatı kendiliğinden gelir), elle yazın ya da "müşterinin kendi çerçevesi" deyin.
3. **Cam seçenekleri (1–3)** — camları katalogdan marka, ad ve özellikleriyle seçin. Fiyat katalogdan gelir,
   isterseniz değiştirebilirsiniz. Müşteri seçenekleri yan yana karşılaştırır.
4. **Medula payı ve iskonto** — kullanım şekline göre SGK payı tahmin edilir (gerekirse düzeltin), iskonto oranını
   girin. Her seçenek için **ödenecek tutar** ekranda anında hesaplanır.
5. **Teklifi kaydet ve döküm al** — döküm yazdırılır / PDF kaydedilir ya da WhatsApp ile müşteriye gönderilir.
6. Müşteri karar verince seçtiği kartta **"Bu seçenekle siparişe çevir"**: tutar, Medula payı, çerçeve ve not
   siparişe dolu gelir.

## Hesap nasıl yapılır?

```
Ara toplam = cam + çerçeve
Kalan      = ara toplam − Medula (SGK) payı
İskonto    = kalan × iskonto %
Ödenecek   = kalan − iskonto
```

Örnek: cam 8.000 ₺ + çerçeve 2.000 ₺ = 10.000 ₺ · SGK 1.200 ₺ → kalan 8.800 ₺ · %10 iskonto 880 ₺ →
**müşteriden 7.920 ₺**.

Siparişe çevrilince sipariş tutarı = SGK payı + ödenecek; böylece siparişteki **kalan bakiye müşteriye söylenen
tutarla aynıdır**. Sonra reçete girildiğinde SGK payı tahminle değiştirilmez (teklifteki tutar korunur).

## İskonto yetkisi

**Ayarlar › Genel › Teklif iskontosu**: personelin girebileceği en yüksek oran (varsayılan %10).
Süper yetkili sınırsızdır. Sınırı aşan iskonto kaydedilmez.

## Cam kataloğu (Ayarlar › Ürün kataloğu)

Her cam şu bilgilerle kaydedilir:

| Alan | Örnek |
|---|---|
| Marka, ürün / seri adı, segment | Essilor · Eyezen Start · Dengeli |
| **Odak tipi** | Tek odak · yakın destekli (yorgunluk) · miyopi kontrol · progressive · ofis · bifokal |
| **Hammadde** | Organik (CR-39) · Polikarbonat · Trivex · MR-8 · MR-7 · MR-174 · Mineral |
| **Kırılma indeksi** | 1.50 · 1.53 · 1.56 · 1.59 · 1.60 · 1.67 · 1.74 · 1.80 · 1.90 |
| **Yüzey tasarımı** | Küresel · asferik · çift asferik · free-form |
| **Kaplamalar** (birden çok) | Antirefle · süper hidrofobik · Blue · UV420 · Drive · fotokromik · polarize · sert · ayna · buğu önleyici |
| Satış fiyatı (çift) | Boş bırakılırsa teklifte fiyat sorulur |

Bu bilgiler teklif ekranında, dökümde ve WhatsApp mesajında camın altında görünür. Katalog fiyatı sonradan
değişse de verilmiş teklifler değişmez.

## Ayrıca düzeltilenler

- Etiket sihirbazındaki **Yazdır** düğmesi, kasa tarih seçimi, siparişte personel atama, başarılar sayfasındaki
  seçim ve katalog filtreleri tarayıcı güvenlik kuralı (CSP) yüzünden çalışmıyordu; düzeltildi.
- Sipariş ekranında hata olunca tekliften dönüştürme bilgisi kayboluyordu; artık korunuyor.

Eski (serbest metinli) teklifler olduğu gibi görüntülenir; katalogda olmayan işler için **Serbest metinle teklif**
hâlâ Teklifler sayfasında.

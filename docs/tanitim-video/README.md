# OptiFlow · Instagram tanıtım filmleri

Kodla çizilen, kare kare çekilen dikey (1080×1920, 30 fps) Reels filmleri. Her film tek bir HTML dosyasıdır.
`ciz(t)` her kareyi t saniyesine göre çizer, bu yüzden her çekimde aynı sonuç çıkar.

| Film | Süre | Konu |
|---|---|---|
| `musteri-geldi/film.html` | 27,5 sn | Bir müşteri geldi: kapı → reçete Medula'dan tek tıkla → SGK payı düşülmüş teklif → atölye panosu → "gözlüğünüz hazır" mesajı → teslim → kapanış |

## Çekim
```bash
# Node 18+, playwright (Chromium), ffmpeg
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/musteri-geldi.mp4
# hızlı kontrol: yalnızca bazı saniyelerin PNG'si
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/k.mp4 --kare 2,8.8,20.5
```
Önizleme için `film.html` dosyasını tarayıcıda açmak yeterli (gerçek zamanlı döner).

MP4 dosyaları git'e girmez; kaynak burada durur ve film yeniden çekilir.

## Kurallar
- **Marka:** `docs/kurumsal-kimlik` kurallarına uyulur.
  - Yazı tipi Manrope ile IBM Plex Mono, renk bordo ile gece.
  - Logo `pz_logo()` ile birebir aynı.
  - Ünlem ve emoji kullanılmaz.
- **Veri:** Mağaza ("Örnek Optik"), müşteri ve tüm rakamlar uydurmadır. Gerçek müşteri verisi kullanılmaz (KVKK).
  Telefon numaraları maskeli yazılır, T.C. kimlik no hiç görünmez.
- **Müzik:** Dosyaya gömülmez. Paylaşırken Instagram'ın kendi müzik kütüphanesinden eklenir; böylece telif sorunu çıkmaz.
- **Güvenli alan:** Reels'te altta yaklaşık 380 px, üstte yaklaşık 220 px uygulama düğmeleriyle kapanır.
  Altyazılar 250–520 px arasında, sahne 560–1520 px arasında durur.

## Fikirler (sıradaki filmler)
- "Telefon çaldı, açamadık": arayan kartı, cevapsız aramaya SMS, geri aranacaklar listesi
- "Ay sonu": SGK faturası tek tıkla
- "Cam geldi mi?": tedarikçi ve atölye takibi

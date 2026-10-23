# OptiFlow · Instagram tanıtım filmleri

Kodla çizilen, kare kare çekilen dikey (1080×1920, 30 fps) Reels filmleri. Her film tek bir HTML dosyasıdır.
`ciz(t)` her kareyi t saniyesine göre çizer, bu yüzden her çekimde aynı sonuç çıkar.

| Film | Konu |
|---|---|
| `musteri-geldi/` | Bir müşteri geldi: kapı → **Medula'dan e-Reçete sorgulama** → **Reçeteyi aktar** → SGK payı düşülmüş teklif → atölye panosu → "gözlüğünüz hazır" mesajı → teslim |
| `aranacaklar/` | Bugün aranacaklar: sabah "kimi arayacağım?" → liste (hazır, gelmeyen, ödeme sözü, planlı) → Ulaşıldı / Açmadı / WhatsApp → kimse unutulmaz → müşteri gelir |
| `tamir/` | Tamir · hizmet: vidası düşen gözlük → işlem + teslim alırken görülen → bırakıldı / ücretsiz → yarım A4 fiş (üst müşteriye, alt gözlüğe) → aranacaklara düşer → teslim |

Ortak parçalar `ortak/` altında: `motor.js` (sahne tablosu, karakterler, dükkân, uygulama paneli, altyazı, kapanış) ve `stil.css`.
Her film `FILM({ sahneler: [...] })` ile yazılır. Sahnenin `ses` metni seslendirmeye gider; ses uzunsa sahne kendiliğinden uzar.

## Çekim
```bash
# Node 18+, playwright (Chromium), ffmpeg
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/musteri-geldi.mp4
# seslendirmeli: önce metinleri yaz, Actions › Seslendir'i çalıştır, ses-<film>.zip'i aç, sonra:
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/m.mp4 --sesjson docs/tanitim-video/musteri-geldi/ses.json
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/musteri-geldi.mp4 --ses ses/musteri-geldi
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
- **Müzik:** `--muzik <dosya>` ile eklenir. Konuşma sırasında kendiliğinden kısılır; müziğin kapanışı filmin sonuna oturur.
  Film müzikten uzunsa müziğin başı tekrarlanır. Seviye `--muzik-seviye 0.32` ile ayarlanır. Müzik dosyası git'e girmez.
- **Güvenli alan:** Reels'te altta yaklaşık 380 px, üstte yaklaşık 220 px uygulama düğmeleriyle kapanır.
  Altyazılar 250–520 px arasında, sahne 560–1520 px arasında durur.

## Fikirler (sıradaki filmler)
- "Telefon çaldı, açamadık": arayan kartı, cevapsız aramaya SMS, geri aranacaklar listesi
- "Cam geldi mi?": tedarikçi ve atölye takibi

## Seslendirme
- Taslak ses: Coqui TTS Türkçe (GlowTTS + HiFiGAN, **MIT**, Common Voice verisi), `.github/workflows/seslendir.yml`.
  Ticari kullanıma uygun ama kalitesi orta; yayın için kendi sesinizle kayıt ya da ücretli bir servis önerilir.
  Kayıt `<sahne-adı>.wav` adlarıyla bir klasöre konursa `--ses` ile aynen yerleşir.
- Piper `tr_TR-dfki` sesi **CC BY-NC-SA** (ticari değil): reklamda kullanılmaz.
- Kullanıcı kararı (23.10): "Ay sonu SGK faturası kıyası" filmi çıkarıldı.
- Yayın sesi (23.10): TTSMaker'da (ticari kullanım serbest) her film tek seferde okutuldu (v1/v2/v3.mp3).
  `bol.py` sesi duraklardan sahnelere böler: duraklar satır uzunluklarına göre eşleştirilir.
  ```bash
  python3 docs/tanitim-video/bol.py v1.mp3 docs/tanitim-video/musteri-geldi/ses.json ses/musteri-geldi
  node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/musteri-geldi.mp4 --ses ses/musteri-geldi --tempo 1 --muzik muzik.wav --muzik-seviye 0.32
  ```

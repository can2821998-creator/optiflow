# Telefon asistanı (OptiFlow Asistan · Android)

Mağazayı arayan müşteriye kimse açamadığında, mağaza telefonundaki **OptiFlow Asistan** uygulaması aramayı açar:

> "Poyraz Optik'e hoş geldiniz. Ben mağazamızın dijital asistanıyım. Merhaba Ayşe Yılmaz. Camlarınız geldi,
> gözlüğünüz atölyede hazırlanıyor. Tahmini teslim 12 Ekim Pazartesi. Başka bir konuda yardımcı olabilir miyim?"

Arayan **tuşla** (1 sipariş durumu · 2 adres ve çalışma saatleri · 3 not bırak) ya da **konuşarak** sorabilir
("gözlüğüm hazır mı", "camlar geldi mi", "kaçta kapanıyorsunuz", "yetkiliyle görüşmek istiyorum").
Not bırakırsa ya da asistan anlayamazsa OptiFlow'da **Geri aranacaklar** listesine düşer ve bildirim gelir.

Dışarıdan bir hizmet kullanılmaz: ses telefonun kendi Türkçe sesiyle söylenir, konuşma telefonun kendi tanımasıyla
anlaşılır. Ek ücret yoktur (yalnızca telefonun internet bağlantısı).

## Kurulum (bir kez)

1. **Merkez panel** › mağaza › özellikler › **Telefon asistanı**nı açın.
2. OptiFlow › **Atölye › Telefon asistanı** sayfasını açın.
3. Mağaza telefonuna **OptiFlow Asistan** uygulamasını indirip kurun (sayfadaki bağlantı; telefon "bilinmeyen
   kaynak" izni isteyebilir).
4. Uygulamayı açın ve sırayla:
   - **İzinleri ver** (telefon, aramayı açma, mikrofon),
   - **Arayan numarayı tanıma** görevini verin (aramalar engellenmez; yalnızca numara öğrenilir),
   - **Ses erişimi**: Ayarlar › Erişilebilirlik › Yüklü uygulamalar › OptiFlow Asistan › Aç.
     "Kısıtlanmış ayar" uyarısı çıkarsa: uygulamada **Uygulama bilgisi** › sağ üstte ⋮ › **Kısıtlanmış ayarlara izin
     ver**, sonra tekrar açın,
   - **Pil kısıtlamasını kaldırın**.
5. OptiFlow sayfasında **Bağlama kodu al** › karekodu telefonun kamerasıyla okutun › **Uygulamayı bağla**.
6. Uygulamada **Deneme konuşması** ile sesi deneyin (arama olmadan).

## Nasıl çalışır

- Telefon çalar; **ayarlanan süre** (varsayılan 20 sn) içinde siz açarsanız asistan karışmaz.
- Açılmazsa asistan aramayı açar, **hoparlörü açar**, konuşur ve dinler. Görüşme bitince kapatır.
- Arayan numara sistemde kayıtlıysa adıyla karşılanır ve siparişinin durumu hemen söylenir. Kayıtlı değilse
  siparişte kayıtlı numarayı tuşlaması istenir (o durumda isim söylenmez).
- Söylenenler: sipariş aşaması, camların durumu (tedarik ediliyor / depodan bekleniyor / geldi), tahmini teslim günü,
  adres ve çalışma saatleri (Ayarlar › Genel).
- **Söylenmeyenler:** tutar, bakiye, adres gibi kişisel bilgiler. Konuşmanın metni saklanmaz; yalnızca kısa özet ve
  bırakılan not tutulur, 90 gün sonra silinir.

## Bilinmesi gerekenler

- Android, uygulamaların görüşme sesine doğrudan girmesine izin vermez; asistan bu yüzden **hoparlörden konuşup
  mikrofondan dinler**. Telefonun sessiz bir yerde durması tanımayı belirgin şekilde iyileştirir. Tuşla seçim
  (1-2-3) gürültüde de çalışır.
- Arayan asistanı duyamıyorsa uygulamada **Asistan sesi** kanalını değiştirin (görüşme ↔ medya).
- Sorun olursa: OptiFlow › Telefon asistanı › **Uygulama olayları** — her dinlemede "ses seviyesi" yazılır.
  Seviye 0–1 ise görüşme sırasında mikrofona ses gelmiyordur (ses erişimi kapalı ya da telefon izin vermiyor).
- Telefon yeniden başlarsa asistan kendiliğinden açılır (erişilebilirlik hizmeti). Uygulamayı kaldırıp kurarsanız
  izinleri ve bağlantıyı yeniden verin.

## Teknik

- Sunucu: `app/asistan.php` (konuşma akışı, niyet, sipariş cümleleri, eşleştirme), `app/pages/asistan-api.php`
  (uç nokta `asistan.php?m=<mağaza>&eylem=…`, cihaz anahtarı `X-Asistan-Anahtar`), `app/pages/telefon-asistani.php`.
  Tablolar `asistan_cihazlar`, `asistan_aramalar` (şema 33). Test: `tests/asistan/test.php`.
- Uygulama: `android/` (Kotlin, ek kütüphane yok). `AsistanServisi` (erişilebilirlik hizmeti: arama durumu, otomatik
  açma, görüşme döngüsü), `AramaTanima` (CallScreeningService: numara), `Konusucu` (TextToSpeech), `Dinleyici`
  (AudioRecord → DTMF çözücü + Android 13 `EXTRA_AUDIO_SOURCE` ile konuşma tanıma), `AnaEkran` (kurulum).
- Derleme: `.github/workflows/android-asistan.yml`. İmza: `ASISTAN_KEYSTORE_B64` + `ASISTAN_KEYSTORE_SIFRE` sırları
  (yoksa geçici anahtar: güncellemeden önce eski sürümü kaldırmak gerekir).

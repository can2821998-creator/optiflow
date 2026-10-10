# Telefon asistanı (OptiFlow Asistan · Android)

Mağaza telefonu çalarken **arayanın sipariş durumu ekranın üstünde** görünür; telefon **açılamazsa** arayana mağaza
hattından **otomatik SMS** gider ve arama OptiFlow'da **Geri aranacaklar** listesine düşer.

> Ekrandaki kart: **Ayşe Yılmaz** · 0532 410 20 30
> • #00012 · Atölyede — Camlarınız geldi, gözlüğünüz atölyede hazırlanıyor. Tahmini teslim 12 Ekim Pazartesi. Kalan 3.000,00 ₺.

> SMS: "Poyraz Optik: Merhaba Ayşe Yılmaz, aramanıza yetişemedik. Camlarınız geldi, gözlüğünüz atölyede
> hazırlanıyor. Tahmini teslim 12 Ekim Pazartesi. Gerekirse sizi geri arayacağız. Takip: optiflow.com.tr/durum.php?…"

Dışarıdan hizmet yok: SMS mağaza telefonunun kendi hattından gider (tarifedeki SMS).

## Kurulum (bir kez)

1. **Merkez panel** › mağaza › özellikler › **Telefon asistanı**nı açın.
2. OptiFlow › **Atölye › Telefon asistanı** sayfasını açın; mağaza telefonuna uygulamayı indirip kurun
   (`indir/asistan/OptiFlow-Asistan.apk`; Play Protect uyarırsa "Yine de yükle" ya da kurulum süresince Play Protect
   taramasını kapatın).
3. Uygulamada sırayla: **OptiFlow'a bağlan** (sayfadaki "Bağlama kodu al" → karekodu okutun), **İzinler** (telefon
   durumu, SMS, bildirim), **Arayan numarayı tanıma** görevi, **Ekranda kart gösterme** ("diğer uygulamaların
   üzerinde göster"), **Pil kısıtlaması**. Xiaomi / Redmi / Tecno vb. telefonlarda **Otomatik başlat**ı da açın.
4. **Kartı dene** ile bir müşteri numarası girip kartı görün; sonra başka bir telefondan arayıp açmayın.

## Kurallar

- SMS yalnızca **kayıtlı müşteriye** ve **cep numarasına** gider; ayarda isterseniz kayıtlı olmayanlara da genel
  "sizi geri arayacağız" mesajı. Aynı numaraya **6 saatte en fazla bir** SMS. SMS'te tutar ve bakiye yazmaz.
- Her açılamayan arama **Geri aranacaklar**a düşer (bildirim gelir); aynı kişi tekrar ararsa aynı kayda "tekrar aradı"
  eklenir. "Arandı" ile kapatılır. Kayıtlar 90 gün saklanır.
- Kartta kalan tutar görünür (yalnızca mağazanın kendi telefonu; uygulama mağazaya kodla bağlanır).

## Neden sesli asistan değil?

İlk sürümde asistan aramayı açıp konuşuyordu; ancak Android, sıradan uygulamaların sesini telefon görüşmesine
vermiyor (yankı engelleme cihazın kendi çaldığı sesi siliyor). Ses mağaza telefonundan duyuldu, arayana gitmedi.
Gerçek sesli karşılama ancak aramanın bir **internet hattına (SIP)** yönlendirilmesiyle mümkün; sunucudaki sesli akış
(`asistan_basla` / `asistan_cevap`) bu ihtimal için duruyor.

## Teknik

- Sunucu: `app/asistan.php` (`asistan_bilgi`, `asistan_cevapsiz`, `asistan_mesaj_metni`, eşleştirme),
  `app/pages/asistan-api.php` (`asistan.php?m=…&eylem=bilgi|cevapsiz|mesaj_sonucu|ayar|olay|bagla`, cihaz anahtarı
  `X-Asistan-Anahtar`), `app/pages/telefon-asistani.php`. Tablolar `asistan_cihazlar`, `asistan_aramalar` (şema 33).
  Test: `tests/asistan/test.php`.
- Uygulama `android/` (Kotlin, ek kütüphane yok): `AramaTanima` (CallScreeningService → numara), `AramaAlici`
  (PHONE_STATE: çalıyor → kart, bitti ve açılmadı → cevapsız + SMS), `Kart` (üstte pencere + bildirim), `AnaEkran`.
- Derleme: `.github/workflows/android-asistan.yml`. İmza: `ASISTAN_KEYSTORE_B64` + `ASISTAN_KEYSTORE_SIFRE` sırları
  (yoksa geçici anahtar: güncellemeden önce eski sürümü kaldırmak gerekir).

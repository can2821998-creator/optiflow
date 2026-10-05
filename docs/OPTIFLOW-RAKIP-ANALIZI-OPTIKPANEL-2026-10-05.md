# OptiFlow vs OptikPanel — Rekabet Analizi

5 Ekim 2026

## Yönetici özeti

OptiFlow, gözlüğün yolculuğunu (sipariş → cam → atölye → teslim → hatırlatma) yönetmekte OptikPanel'den açıkça ileride. OptikPanel ise stok, ÜTS, çok şube ve kurumsal güven görüntüsünde önde. İki demo hesabı 5 Ekim 2026'da modül modül gezildi.

- **Konumlanma:** OptikPanel bir "perakende + stok" yazılımı: POS, barkod, etiket, transfer. OptiFlow ise bir "optik iş akışı" yazılımı: atölye panosu, cam takibi, SGK, WhatsApp, müşteri sadakati.
- **OptiFlow'un en büyük riski ürün değil, güven:** Demo sırasında 10 sayfa ara ara "Beklenmeyen bir hata oluştu" verdi. Müşteri kartında TC kimlik no yok. Uygulama içi metinlerde "phpMyAdmin ile geri yükleyin" gibi teknik ifadeler var.
- **Önüne geçmenin yolu:** Önce OptikPanel'in güçlü olduğu 5 temel alanda eşitliği yakalamak: hızlı satış ekranı, ÜTS, çok şube/transfer, stok sayımı, e-Fatura. Ardından iş akışı + WhatsApp + SGK alanını "para kazandıran yazılım" diye satmak. OptikPanel'in JSON yedeğini içe aktaran bir geçiş aracı, müşteri çalmanın en kısa yolu.

Not: OptiFlow'da Teklifler, Başarılar, SGK mutabakat, Satın alma önerisi, Çerçeve stoğu, Bağış, Bakiye, Kasa, Faturalar ve Pro sayfaları tarayıcıda ara ara hata verdi. Aynı sayfalar hemen ardından normal açıldı; içerikleri bu şekilde okundu.

## Modül modül karşılaştırma

29 alanın 13'ünde OptiFlow, 11'inde OptikPanel önde; 5'i başa baş ya da karışık. OptiFlow'un üstün olduğu alanlar müşteriyi ve parayı ilgilendiriyor. OptikPanel'inkiler ise operasyonu ve güveni.

| Alan | OptiFlow | OptikPanel | Önde |
| --- | --- | --- | --- |
| Sipariş ve atölye takibi | 6 aşamalı durum, sürükle-bırak atölye panosu, teslim sözü, geciken ve 15+ gündür hazır uyarıları, ayrı tamir akışı | Yok; reçetede yalnızca Beklemede / Hazırlanan / Biten | OptiFlow |
| Cam tedarik zinciri | Eksik cam listesi, tedarikçiye cam sipariş fişi, gelen cam, fatura bekleyen teslimat, cam cam maliyet | Yok (stok harici cam satırı girilebiliyor) | OptiFlow |
| Hızlı satış (POS) | Satış sipariş üzerinden; aksesuar gibi küçük satışlar kasada "diğer gelir" | Barkodlu sepet, normal / reçeteli satış, parçalı tahsilat, tedarikçi mahsup, ek iskonto | OptikPanel |
| Reçete | SPH/CYL/AKS, tek odak / uzak-yakın, cam satırı stokta / eksik | Uzak / yakın / ADD, Medula / Normal, e-reçete no | Başa baş (ikisinde de PD, yükseklik, prizma yok) |
| Medula / SGK | Reçete aktarımı (Pro masaüstü uygulaması), katkı payı otomatik, ay sonu SGK mutabakatı, SGK fatura taslağı | Elle giriş; aylık Medula reçete sayısı | OptiFlow |
| ÜTS | Pro'da: siparişe / stoğa karekod, gelen kabul, imha, iade | Web'de tam gelen kutusu: paket kabulü, geri çekme, askıdaki vermeyi iptal, satışta otomatik verme, test ortamı | OptikPanel |
| Müşteri kartı | Ad, soyad, telefon, doğum yılı, aile bağlantısı, reçete geçmişi, lens kaydı, WhatsApp izni | TC kimlik no, e-posta, doğum tarihi, cinsiyet, il / ilçe / adres | OptiFlow akış, OptikPanel kimlik verisi |
| Hatırlatma ve sadakat | Gözlük yenileme, SGK hakkı, teslim alınmadı, lens bitiyor, planlı arama | Doğum günü listesi | OptiFlow |
| Müşteri iletişimi | WhatsApp (tek tık + Meta Cloud otomatik), KVKK izin kaydı, Google yorum isteği | SMS şablonları (borç, doğum günü, kampanya) | OptiFlow |
| Müşteri takip sayfası | Fişteki karekodla durum sayfası, "Gözlüğünüzü Ali hazırladı", yol tarifi | Yok | OptiFlow |
| Online ödeme | PayTR ödeme linki, 12 taksite kadar | Yok | OptiFlow |
| Teklif | İyi / daha iyi / en iyi üçlü teklif, cam kataloğu ve öneri asistanı | Yok | OptiFlow |
| Stok | Çerçeve vitrini, kritik stok, satın alma önerisi (90 gün ortalaması) | Tüm ürünler, Excel ile toplu ekleme, stok çıkışı, UDI | OptikPanel |
| Stok sayımı | Yok | Telefon kamerasıyla sayım, fark ve maliyet raporu | OptikPanel |
| Etiket | Etiket sihirbazı | QR etiket tasarımı, kalibrasyon, doğrudan yazıcı köprüsü | OptikPanel |
| Çok şube | Görülmedi | Şubeler arası onaylı transfer, admin paneli | OptikPanel |
| Muhasebe ve kasa | Gün sonu kasa sayımı, bakiye yaşlandırma, ödeme sözü, tedarikçi senedi | Kasa defteri, müşteri / tedarikçi cari, gider kategorileri, iptal denetimi | Başa baş |
| Kârlılık | Sipariş bazında kâr (cam + çerçeve maliyeti), personel primi | Ürün bazında tahmini kâr | OptiFlow |
| e-Fatura / e-Arşiv | Taslak hazır, entegratör yok, alış e-Fatura içe aktarımı | Mağaza panelinde yok (Depo modülünde var) | Başa baş (kimsede canlı değil) |
| Yapay zekâ | Cam öneri asistanı | Fatura okuyup stoka aktarma, panel asistanı (müşteri kendi Gemini anahtarını alıyor) | OptikPanel |
| Personel ve yetki | Süper yetkili / personel, 2 izin anahtarı, prim oranı | Şube yöneticisi / çalışan | Başa baş |
| Denetim kaydı | Silinemez işlem geçmişi, 60+ işlem türü, IP ile | İptal / silinen işlemler ekranı | OptiFlow |
| Yedekleme | Otomatik günlük yedek (14 adet), SQL indirme | Elle JSON yedeği; geri yükleme mevcut veriyi siliyor | OptiFlow |
| Bildirim | Push bildirim, akşam günün özeti | Yok | OptiFlow |
| Ekip motivasyonu | Rozetler, XP, aylık takım hedefi | Personel satış performansı | OptiFlow |
| Dil | Türkçe | Türkçe, Azerbaycanca, İngilizce, Rusça | OptikPanel |
| Destek ve onboarding | Uygulama içinde görülmedi | Canlı destek, uygulama içi eğitim turu, Ctrl+K arama, 14 gün deneme | OptikPanel |
| Kurumsal güven | Paylaşımlı hosting izlenimi (phpMyAdmin, "hosting panelinizde") | ISO 27001 / 15504, Türkiye'de sunucu, %99,9 uptime, "500+ gözlükçü" | OptikPanel |
| B2B toptan | Yok | Optik Panel Depo: bayi siparişi, konsinye, çek, irsaliye | OptikPanel |

## OptiFlow'un üstün olduğu alanlar

OptiFlow'un asıl kozu, gözlükçünün günlük derdini çözmesi: "Cam geldi mi, gözlük hazır mı, müşteriye haber verildi mi, SGK parası tam mı geldi?" OptikPanel bu soruların hiçbirine cevap vermiyor.

1. **Atölye panosu ve teslim sözü.** Cam bekliyor → montaj → kalite kontrol → hazır akışı sürükle-bırak çalışıyor. Geciken teslimler ve "15+ gündür hazır" gözlükler ana sayfada. OptikPanel'de sipariş kavramı bile yok.
2. **Cam tedarik zinciri.** Reçetede "eksik" işaretlenen cam otomatik olarak cam sipariş fişine düşüyor. Gelince atölyeye geçiyor, fatura gelince cam cam maliyet giriliyor. Sipariş bazında gerçek kâr da bu sayede hesaplanabiliyor.
3. **SGK parasını kaybettirmeyen akış.** Medula'dan reçete aktarımı, katkı payı ön hesabı, "Medula'ya işlendi" işareti ve ay sonu mutabakatı var. Mutabakatta Medula listesi yapıştırılıp eşleşmeyen reçeteler bulunuyor. Bu, doğrudan tahsil edilmeyen SGK alacağını yakalıyor.
4. **Hatırlatma merkezi = tekrar satış makinesi.** 24 ayı geçen gözlükler, SGK hakkı doğanlar, lensi bitenler ve teslim alınmayanlar tek listede. Hepsi tek tıkla WhatsApp'tan aranabiliyor.
5. **WhatsApp + KVKK izin yönetimi.** "Gözlüğünüz hazır" bilgilendirmesi izinsiz gidiyor; kampanya türü mesajlar yalnızca izinli müşteriye gidiyor ve izin kaydı tutuluyor. OptikPanel'in SMS kampanya şablonunda izin yönetimi görünmüyor; bu onlar için İYS riski.
6. **Müşteri deneyimi.** Fişteki karekodla takip sayfası açılıyor; sayfada gözlüğü hazırlayan personel görünüyor. Teslimden sonra Google yorum isteği, PayTR ile taksitli ödeme linki ve üç seçenekli teklif var. Bunlar mağazanın cirosunu ve yorum sayısını doğrudan artıran özellikler.
7. **Yönetim araçları.** Personel primi, gamification, silinemez denetim kaydı, otomatik yedek ve akşam "günün özeti" push bildirimi var. Bunlar mağaza sahibinin dükkanda olmadan işi takip etmesini sağlıyor.

## OptiFlow'un eksikleri ve riskleri

En acil iki konu kararlılık (sayfaların ara ara hata vermesi) ve müşteri kimlik verisi. İkisi de demoda karşı tarafın hemen fark edeceği şeyler.

| # | Eksik / risk | Neden önemli | Aciliyet |
| --- | --- | --- | --- |
| 1 | 10 sayfada ara ara "Beklenmeyen bir hata oluştu" (Teklifler, Başarılar, SGK mutabakat, Satın alma önerisi, Pro, Çerçeve, Bağış, Bakiye, Kasa, Faturalar) | Demoda veya ilk hafta görülürse müşteri kaybedilir. Hata kodları loglanmalı (örn. #F2225CF2, #89AAE2E1). Ardı ardına sayfa açılınca oturum kilidi ya da hız sınırı olabilir | Kritik |
| 2 | Müşteri kartında TC kimlik no, e-posta, adres, il / ilçe, tam doğum tarihi, cinsiyet yok | SGK, e-Arşiv faturası ve ÜTS verme bildirimi için TCKN gerekiyor. OptikPanel'de hepsi var | Kritik |
| 3 | Barkodlu hızlı satış ekranı yok | Güneş gözlüğü, solüsyon, aksesuar, lens satışı sipariş açmadan 15 saniyede bitmeli. OptikPanel'in reklamı tam olarak bu ("15 sn ort. satış süresi") | Yüksek |
| 4 | e-Fatura / e-Arşiv yalnızca taslak, entegratör bağlı değil | Mağaza resmi faturayı başka programdan kesmek zorunda kalıyor. OptikPanel'de de yok, bu yüzden ilk yapan kazanır | Yüksek |
| 5 | Medula ve ÜTS yalnızca Windows Pro uygulamasında | Mac ve tablet kullananlar dışarıda kalıyor. OptikPanel ÜTS'yi web'den sunuyor | Yüksek |
| 6 | ÜTS kapsamı dar görünüyor | Paket kabulü, geri çekme talepleri, askıdaki vermeyi iptal, satışta otomatik verme ve test ortamı OptikPanel'de açıkça var | Yüksek |
| 7 | Çok şube ve şubeler arası transfer görülmedi | 2+ şubeli zincirler bu yüzden OptikPanel'i seçer | Orta |
| 8 | Genel stok zayıf: yalnızca çerçeve ve cam; Excel ile toplu yükleme ve stok sayımı yok | Geçiş yapan mağaza 500+ çerçeveyi tek tek giremez | Orta |
| 9 | Reçetede PD, montaj yüksekliği, prizma alanları yok | Progresif camda laboratuvar bu değerleri istiyor. OptikPanel'de de yok, bu bir fırsat | Orta |
| 10 | Yapay zekâ ile alış faturası okuma yok | OptikPanel bunu ana sayfasında ilk özellik olarak satıyor | Orta |
| 11 | Tek dil (Türkçe) | Azerbaycan ve KKTC pazarı OptikPanel'e kalıyor | Düşük |
| 12 | Anlaşmalı kurum indirimi yok | Kurum indirimiyle çalışan mağazalar bunu elle hesaplıyor | Düşük |
| 13 | Uygulamada destek ve onboarding görünmüyor | Canlı destek düğmesi, ilk kurulum turu ve global arama (Ctrl+K) yok | Orta |
| 14 | Güven iletişimi | "Hosting panelinizde phpMyAdmin → İçe aktar" ve "zamanlanmış görev adresini destekten isteyin" gibi ifadeler küçük, teknik bir ürün izlenimi veriyor. Karşı tarafta ISO 27001 ve "500+ gözlükçü" var | Yüksek |
| 15 | Cila hataları | Ürün kataloğunda "v28 asistanından aktarıldı · tasarım, indeks ve fiyatı kontrol edin" metni müşteriye görünüyor | Düşük |
| 16 | Personel rolleri kaba (2 rol, 2 izin anahtarı) | Kasiyer, optisyen, atölye ustası, muhasebe gibi rol ayrımı yok. OptikPanel'de de yok, bu da bir fırsat | Düşük |

## OptikPanel'in zayıf noktaları (saldırı noktaları)

OptikPanel gözlüğü satar ama teslim etmez; satışın ertesi gününden itibaren mağazayı yalnız bırakıyor. Demo ve satış görüşmelerinde şu soruları sorun:

1. **"Camı Essilor'a ne zaman sipariş verdiğinizi, ne zaman geldiğini nereden takip ediyorsunuz?"** OptikPanel'de sipariş, cam takibi, atölye ve teslim sözü yok. Reçetenin yalnızca 3 durumu var.
2. **"Gözlük hazır olunca müşteriye nasıl haber veriyorsunuz?"** Otomatik "hazır" mesajı, WhatsApp ve müşteri takip sayfası yok. SMS'te yalnızca borç, doğum günü ve kampanya şablonu var.
3. **"SGK'dan gelmesi gereken paranın tamamı geldi mi?"** Medula verisi elle giriliyor; katkı payı hesabı ve ay sonu mutabakatı yok.
4. **"2 yıl önce gözlük alan müşterinizi tekrar nasıl getiriyorsunuz?"** Yenileme, SGK hakkı ve lens bitiyor hatırlatmaları yok.
5. **"Yapay zekâ için Google'dan kendiniz anahtar almanız gerekiyor, biliyor muydunuz?"** Müşteri teknik bir iş yapmak zorunda kalıyor. Fatura ve sağlık verisi de üçüncü taraf bir yapay zekâ servisine gidiyor (KVKK sorusu).
6. **"Yedeği geri yükleyince mevcut veriniz siliniyor."** Yedekleme elle yapılıyor ve JSON formatında; otomatik yedek uyarısı da müşteriye bırakılmış.
7. **"Kampanya SMS'leri için İYS izni nerede tutuluyor?"** İzin yönetimi görünmüyor ve %30 indirim şablonu hazır geliyor. Bu, mağazaya ceza riski taşıyor.
8. **"Fiyatı ne kadar?"** Sitede fiyat yok ("size özel teklif"). Şeffaf fiyat, küçük mağazada güçlü bir silah.
9. **Online ödeme / taksit linki, teklif ve sipariş bazında gerçek kâr yok.** Kârı yalnızca ürün alış fiyatından tahmin ediyor, cam maliyetini bilmiyor.
10. **Personel rolleri kaba; müşteri kartında reçete geçmişi ve aile bağlantısı görünmüyor.** Müşteri ekranı basit bir listeden ibaret.

OptikPanel'in güçlü olduğu yer stok ve ÜTS. Bu alanlarda onları geçmeye değil, eşitlemeye çalışın; rekabeti kendi güçlü olduğunuz alanda (iş akışı ve müşteri) yapın.

## Önüne geçme planı

Sıra şöyle: önce demoda kaybettiren açıkları kapat, sonra OptikPanel'le eşitlen, en son onların kopyalaması zor özelliklerle ayrış.

### Faz 1 · 0–30 gün: demoda kaybetmemek

- [ ] Ara ara çıkan "Beklenmeyen hata"nın kök nedenini bul (oturum kilidi, hız sınırı, PHP hataları). Hata kodlarını izleme sistemine bağla (Sentry vb.) ve dışarıdan uptime izleme ekle.
- [ ] Müşteri kartına TCKN (doğrulamalı), e-posta, tam doğum tarihi, cinsiyet ve il / ilçe / adres ekle.
- [ ] Reçeteye PD (sağ / sol), montaj yüksekliği ve prizma alanlarını ekle. Bunlar OptikPanel'de de yok; "laboratuvara eksiksiz reçete" diye satılır.
- [ ] Barkodlu **hızlı satış** ekranı kur: sepet, parçalı ödeme, aksesuar, solüsyon ve güneş gözlüğü. Sipariş açmadan 15 saniyede bitsin.
- [ ] Uygulamaya canlı destek düğmesi (WhatsApp destek hattı yeter), ilk kurulum sihirbazı ve Ctrl+K global arama ekle.
- [ ] Cila: "v28 asistanından aktarıldı" gibi iç notları kaldır; phpMyAdmin / hosting dilini kullanıcı ekranından çıkar.
- [ ] Güven sayfası yayımla: sunucu lokasyonu, şifreleme, otomatik yedek, KVKK aydınlatma metni, silinemez denetim kaydı.

### Faz 2 · 30–90 gün: eşitlenmek ve tek başına öne geçmek

- [ ] **e-Fatura / e-Arşiv canlıya al** (bir özel entegratörle). Taslak altyapısı hazır ve OptikPanel mağaza panelinde bu yok. Tek başına en güçlü satış argümanı.
- [ ] **ÜTS'yi tamamla ve web'e taşı:** gelen kutusu, paket kabulü, geri çekme, askıdaki vermeyi iptal, teslimde otomatik verme bildirimi, test ortamı.
- [ ] **Medula aktarımını Windows dışına aç:** Chrome eklentisini kalıcı yap ya da bir macOS uygulaması çıkar.
- [ ] **Genel stok:** tüm ürün tipleri (lens, solüsyon, aksesuar), Excel ile toplu içe aktarma, telefon kamerasıyla stok sayımı ve fark raporu.
- [ ] **Çok şube:** şube seçimi, merkez paneli, onaylı stok transferi, şube bazında rapor.
- [ ] **Yapay zekâ ile alış faturası okuma:** OptiFlow'un kendi anahtarıyla, müşteriye anahtar aldırmadan. Zaten var olan e-Fatura XML içe aktarımıyla birleştir.
- [ ] **OptikPanel'den geçiş aracı:** OptikPanel'in "Şimdi yedekle" ile indirdiği JSON paketini (müşteri, satış, stok) içe aktar; ayrıca Excel şablonu sun. Bu, geçiş maliyetini sıfıra indirir. Önce kendi demo hesabınızdan bir yedek alıp formatı doğrulayın.

### Faz 3 · 90+ gün: kopyalanması zor ayrışma

- [ ] **Tedarikçi bağlantısı:** cam sipariş fişini e-posta / WhatsApp / B2B portal ile doğrudan depoya gönder ve durumu geri al. Bu, OptikPanel Depo'nun toptancı ağına karşı bir hamle.
- [ ] **Ayrıntılı roller:** kasiyer, optisyen, atölye, muhasebe; şube bazında yetki.
- [ ] **Anlaşmalı kurum indirimi** ve kurum bazında rapor.
- [ ] **SMS kanalı** (İYS entegrasyonlu), WhatsApp yetmediğinde yedek olarak.
- [ ] **Dil desteği:** önce Azerbaycanca ve İngilizce.
- [ ] **Sadakat:** puan / hediye çeki, aile hesabı indirimi, doğum günü mesajı (izinli müşterilere).
- [ ] **Sertifika:** ISO 27001 sürecine başla ya da en azından KVKK uyum denetimi ve sızma testi raporu yayımla.

## Pazarlama, satış ve fiyat

Ana mesaj: **"OptikPanel stok tutar, OptiFlow gözlüğü zamanında teslim eder ve müşteriyi geri getirir."** Özellik listesiyle değil, mağazaya kazandırdığı parayla satın.

| Mesaj | Kanıt (üründe) | Hedef |
| --- | --- | --- |
| "Kaçan SGK parası kalmasın" | Medula aktarımı, katkı payı hesabı, ay sonu mutabakatı | SGK'lı iş yapan tüm optikler |
| "Gözlüklerin 2 yıl sonra geri gelsin" | Hatırlatma merkezi, WhatsApp, SGK hakkı listesi | Tekrar satışı düşük mağazalar |
| "Müşteri 'gözlüğüm hazır mı' diye aramasın" | Takip sayfası, otomatik "hazır" mesajı, push bildirim | Yoğun mağazalar |
| "Hangi gözlükten gerçekten kazandın?" | Cam cam maliyet, sipariş bazında kâr, prim | Mağaza sahibi |
| "Google'da 5 yıldız" | Teslim sonrası yorum isteği | Yeni açılan mağazalar |
| "10 dakikada taşın" (Faz 2 sonrası) | OptikPanel JSON içe aktarımı | OptikPanel müşterileri |

**Demo akışı (12 dakika):**

1. Ana sayfa: "Bugün ne yapmam gerekiyor?" (geciken, hazır, tahsilat).
2. Yeni sipariş, Medula aktarımı ve katkı payının kendiliğinden gelmesi.
3. Eksik cam → cam sipariş fişi → "geldi" → atölye panosunda sürükle-bırak.
4. "Hazır" → müşteriye WhatsApp gönder ve takip sayfasını telefonda göster.
5. PayTR ödeme linki ile kalan bakiyeyi tahsil et.
6. Hatırlatma merkezi ve ay sonu SGK mutabakatı.
7. Kârlılık ekranı ve akşam "günün özeti" bildirimi.

**Fiyat önerisi:** OptikPanel fiyatını gizliyor; OptiFlow fiyatı sitede açıkça yazmalı. Mevcut Lite (tarayıcı) / Pro (Medula + ÜTS) ayrımı doğru. Bunun yanında:

- Çoklu şube ve WhatsApp otomatik (Meta ücreti yansıtılarak) ayrı eklenti ya da üst paket olmalı.
- En az 14 günlük ücretsiz deneme sunulmalı (OptikPanel'de de 14 gün).
- İlk ay ücretsiz veri taşıma eklenmeli.

Fiyatı konumlandırmadan önceki ilk adım, OptikPanel'den teklif isteyip gerçek fiyatını öğrenmek.

## Kaynaklar

- OptikPanel demo paneli ve tanıtım sitesi: [optikpanel.com](https://optikpanel.com/)
- OptiFlow demo paneli: [optiflow.com.tr](https://optiflow.com.tr/)

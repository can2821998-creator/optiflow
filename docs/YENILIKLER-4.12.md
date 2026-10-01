# OptiFlow 4.12.0 · OptiFlow Pro 5.2.0 — Yenilikler ve kurulum

Bu sürümdeki modüllerin hepsi **merkez panelinden mağaza bazında** açılır. Varsayılan olarak kapalıdırlar. Kapalı bir özellik mağazanın menüsünde görünmez, sayfası da açılmaz.

**Açmak için:** Merkez panel › mağaza › **Özellikler** kartında kutuları işaretleyip kaydedin. Birden fazla mağaza için listede mağazaları seçin ve toplu işlem menüsünden **"… — aç"** komutunu uygulayın.

| Özellik | Nerede çalışır | Menüde |
|---|---|---|
| WhatsApp mesaj otomasyonu | Web + masaüstü | WhatsApp mesajları |
| PayTR ödeme linki | Web + masaüstü | Bakiye takibi › Ödeme linki |
| Kontakt lens takibi | Web + masaüstü | Müşteri kartı; Hatırlatmalar › Kontakt lens |
| Tedarikçiye cam siparişi | Web + masaüstü | Cam siparişleri |
| Stok satın alma önerisi | Web + masaüstü | Satın alma önerisi |
| e-Arşiv / e-Fatura (hazırlık) | Web + masaüstü | Faturalar; sipariş › Fatura taslağı |
| SGK mutabakat ve bekleyen reçeteler | Web + masaüstü (liste okuma yalnızca masaüstünde) | SGK mutabakat |
| Barkod / karekod okuyucu modu | Yalnızca OptiFlow Pro | Barkod okut |
| Çevrimdışı salt okunur mod | Yalnızca OptiFlow Pro | Bağlantı kesilince araç çubuğunda |

---

## 1. Güncelleme (hosting)

1. `optiflow-4.12.0-hosting.zip` dosyasını sitenin ana klasörüne yükleyip **çıkarın** (mevcut dosyaların üzerine yazılır). `config.php` ve `storage/` içeriği korunur.
2. Siteye bir kez personel olarak giriş yapın. Veritabanı kendiliğinden güncellenir (şema 22). Hiçbir veri silinmez.
3. **Yedek uyarısı:** Entegrasyon anahtarları (PayTR, WhatsApp) veritabanında **şifreli** saklanır. Şifreyi çözen anahtar `storage/.gizli-anahtar` dosyasındadır. `storage/` klasörünü yedeklerinize mutlaka dahil edin; bu dosya kaybolursa anahtarları Ayarlar'dan yeniden girmeniz gerekir.
4. **Zamanlanmış görev (önerilir):** Merkez panelin üst kısmındaki **"Zamanlanmış görev"** bölümünde gizli bir adres görünür.
   - Plesk › Web Siteleri › Zamanlanmış Görevler › **URL getir** ile bu adresi 10 dakikada bir çağırın.
   - Görev kurulmazsa otomatik işler yalnızca personel sayfaları açıkken çalışır (5 dakikada bir).

## 2. WhatsApp mesaj otomasyonu

Mesajlar önce **kuyruğa** girer. Ayarlar › WhatsApp otomasyonu bölümünden iki kanaldan biri seçilir.

- **Tek tık (varsayılan):** Ek hesap gerekmez. "WhatsApp mesajları" ekranında her mesaj **WhatsApp'ta aç** düğmesiyle hazır metinle açılır, personel Gönder'e basar ve mesaj "gönderildi" olarak işaretlenir.
- **Otomatik (WhatsApp Business Platform):** Meta Business hesabı, doğrulanmış işletme numarası ve Meta'da **onaylanmış şablonlar** gerekir. Meta mesaj başına ücret alır.
  - Ayarlara **Phone number ID** ve **kalıcı erişim anahtarı** (system user token) girilir.
  - Her olay için Meta'da bir şablon açıp adını ayarlara yazın.
  - Şablondaki `{{1}}`, `{{2}}` … sırası aşağıdaki gibi olmalıdır:

| Olay | İzin gerekir mi | Şablon değişkenleri |
|---|---|---|
| Gözlük hazır | Hayır (bilgilendirme) | 1 ad · 2 sipariş no · 3 mağaza · 4 takip linki |
| Teslim alınmadı | Hayır | 1 ad · 2 sipariş no · 3 mağaza |
| Ödeme linki | Hayır | 1 ad · 2 sipariş no · 3 tutar · 4 ödeme linki |
| Gözlük yenileme | **Evet** | 1 ad · 2 mağaza · 3 mağaza telefonu |
| SGK hakkı | **Evet** | 1 ad · 2 hak tarihi · 3 mağaza |
| Kontakt lens bitiyor | **Evet** | 1 ad · 2 ürün · 3 bitiş · 4 mağaza |
| Google yorum isteği | **Evet** | 1 ad · 2 mağaza · 3 yorum linki |

Ayarlardaki **Test mesajı** bölümüyle kurulumu hemen deneyebilirsiniz.

**Otomatik tetikleyiciler** (Ayarlar'dan açılıp kapatılır):
- Sipariş "Hazır" olunca haber verme.
- Teslimden N gün sonra Google yorum isteği. Genel ayarlardaki yorum linki kullanılır; aynı müşteriye 180 gün içinde ikinci istek gitmez.
- Kontakt lens bitiyor bildirimi.
- İsteğe bağlı günlük listeler: teslim alınmayanlar, yenileme, SGK hakkı.

Gönderim yalnızca belirlenen saatlerde yapılır (varsayılan 09:00–21:00). Hatırlatmalar ekranındaki **"Listeyi WhatsApp kuyruğuna ekle"** düğmesi listenin tamamını tek seferde kuyruğa alır.

**Mesaj izni:** Müşteri kartındaki **WhatsApp izni** kutusundan kaydedilir: Verdi / İstemiyor, izin sözlü mü yazılı mı alındı. Kaydı kimin, ne zaman yaptığı işlem geçmişine yazılır. Tanıtım niteliği taşıyabilecek mesajlar (yenileme, SGK hakkı, lens, yorum) yalnızca izin veren müşteriye gider.

> Hangi mesajların ticari ileti sayıldığı ve İYS yükümlülükleri için hukuk danışmanınıza başvurun.

## 3. PayTR ödeme linki

1. Ayarlar › Ödeme linki bölümüne PayTR Mağaza Paneli'ndeki **merchant_id**, **merchant_key** ve **merchant_salt** değerlerini girin. Taksit sayısını ve linkin geçerlilik süresini seçin. Ayarı ilk kez yaparken **Test modu** açık olsun.
2. Bakiye takibinde bir siparişin yanındaki **Ödeme linki** düğmesine basıp linki oluşturun. WhatsApp otomasyonu açıksa mesaj kuyruğa da eklenir.
3. Müşteri kartla ödediğinde PayTR, sitenizdeki `odeme-bildirim.php` adresine haber verir.
   - Bildirimin imzası mağazanın kendi anahtarıyla doğrulanır; doğrulanınca siparişe **Kredi kartı** tahsilatı kendiliğinden yazılır.
   - Aynı bildirim birden çok kez gelse de tahsilat bir kez yazılır.
   - **Test modunda gelen ödeme tahsilat olarak yazılmaz.**
4. Bildirim adresi için sitenin **https** alan adı gerekir.

## 4. Kontakt lens takibi

Müşteri kartındaki **Kontakt lens** bölümünden satışı girin: ürün, göz, kutu adedi, bir kutunun kaç gün yettiği ve başlangıç tarihi.

- Bitiş tarihi kendiliğinden hesaplanır. İki göz aynı kutudan kullanıyorsa süre yarıya iner.
- Bitişe kalan süre Ayarlar › Kontakt lens'ten ayarlanır (varsayılan 10 gün). Süre dolmaya yaklaşan müşteri **Hatırlatmalar › Kontakt lens** sekmesine düşer.

## 5. Cam siparişi ve satın alma önerisi

**Cam siparişi:**
1. **Cam siparişleri** ekranında "stokta yok" durumundaki camları seçin, tedarikçiyi seçip sipariş fişi oluşturun.
2. Fişi yazdırın ya da **WhatsApp** veya **E-posta** ile gönderin. Tedarikçi e-postası tedarikçi kartına girilebilir.
3. **Gönderildi** deyince camlar "Depoya sipariş verildi" olur.
4. Camlar gelince **Depo · Stok** ekranında "geldi" işaretleyin. Fiş kendiliğinden tamamlanır.

**Satın alma önerisi:** Asgari stok altındaki çerçeveler için önerilen adet, son 90 günün satış hızına göre hesaplanır; sonuçlar tedarikçiye göre gruplanır.

## 6. e-Arşiv / e-Fatura (hazırlık aşaması)

Bu sürümde fatura **taslağı** hazırlanır ve kontrol edilir. **Resmi fatura değildir ve GİB'e gönderilmez.** Bir sonraki sürümde seçilecek özel entegratör bağlanınca, aynı taslaklar tek tuşla gönderilecek.

1. Ayarlar › e-Fatura bölümüne satıcı bilgilerini girin: unvan, VKN/TCKN, vergi dairesi, adres, 3 karakterlik seri. VKN ve TCKN algoritmayla kontrol edilir.
2. Sipariş sayfasında **Fatura taslağı**'na basın.
   - Hasta payı için ayrı bir taslak açılır. SGK katkısı varsa SGK payı için de ayrı bir taslak açılır.
   - SGK alıcı bilgilerini Ayarlar'dan kendi sözleşmenize ve mali müşavirinize göre doğrulayın.
3. Taslağa satır ekleyin. Fiyat KDV dahil girilir, birim fiyat KDV hariç hesaplanır.
4. **Kontrol et, hazır işaretle** düğmesine basın. Eksik alanlar listelenir; sorun yoksa fatura kilitlenir ve UBL-TR 1.2 belgesi saklanır. **UBL-TR XML** düğmesiyle önizleme indirilebilir.

KDV oranları: numaralı gözlük, cam, çerçeve ve numaralı lenste varsayılan **%10**, güneş gözlüğünde **%20**. Oranları mali müşavirinizle doğrulayın.

## 7. SGK mutabakat

- **Aylık özet:** Aktarılan reçeteler, siparişe dönüşmeyenler, SGK siparişleri, e-reçete numarası eksik olanlar ve teslim edilip faturalanabilecek olanlar.
- **Bekleyen reçeteler:**
  - OptiFlow Pro'da Medula reçete listesini açıp araç çubuğundaki **Listeyi kontrol et** düğmesine basın. Medula'dan **yalnızca e-reçete numaraları** okunur; hasta adı ve T.C. kimlik no gibi bilgiler okunmaz.
  - Web'de listeyi kopyalayıp yapıştırarak da karşılaştırabilirsiniz. Liste sunucuda saklanmaz.
  - Sonuç ekranı, Medula'da olup OptiFlow'a hiç aktarılmamış reçeteleri gösterir.
- **SGK hakkı hatırlatması:** Artık son SGK'lı reçetenin gerçek tarihinden hesaplanır ve ekranda "Hak: gg.aa.yyyy" olarak görünür.

## 8. OptiFlow Pro masaüstü (5.2.0)

- **Barkod okuyucu modu:** USB okuyucuyla, yazı alanı seçili değilken okutmanız yeterli; ilgili kayıt kendiliğinden açılır.
  - ÜTS karekodu (GS1): GTIN, son kullanma tarihi, parti ve seri no okunur.
  - Çerçeve barkodu: çerçeve kartı açılır.
  - Sipariş fişindeki karekod ya da `#00123` biçimindeki sipariş no: sipariş açılır.
  - Yazı alanına okutulan kod olduğu gibi o alana yazılır.
- **Çevrimdışı salt okunur mod:**
  - Uygulama, açık siparişlerin (no, ad, telefon, durum, teslim sözü, yetki varsa kalan bakiye) bir kopyasını 10 dakikada bir alır.
  - Kopya bilgisayarda **Windows kullanıcı hesabına bağlı şifrelemeyle** saklanır. 7 günden eskisi kullanılmaz; çıkış yapınca, mağaza veya kullanıcı değişince silinir.
  - İnternet kesilince araç çubuğunda **Çevrimdışı kopya** düğmesi çıkar. Kopyada yalnızca görüntüleme yapılabilir.

## 9. Kod imzalama

Sertifika alındığında kurulum ve güncellemeler imzalanır. Windows'un "tanınmayan uygulama" uyarısı kalkar, güncellemeler de yalnızca imzası doğrulanırsa kurulur. Ayrıntı için `desktop/RELEASE.md` › "Kod imzalama".

- **Tek komut:** `npm run dist:win:imzali`
  - .pfx sertifika için: `IMZA_TURU=pfx`
  - Azure Trusted Signing için: `IMZA_TURU=azure`
  - Her iki durumda `IMZA_YAYINCI` = sertifikadaki ad (CN).
- **GitHub'dan derleme:** Proje GitHub'a taşındığında `.github/workflows/optiflow-pro-surum.yml` Windows'ta imzalı derleme yapar ve imzayı doğrular.

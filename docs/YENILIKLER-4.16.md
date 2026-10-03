# OptiFlow 4.16 — Garanti kaydı, garanti kartı ve SGK ay sonu faturası

*3 Ekim 2026 · sunucu 4.16.1 (şema 27) · masaüstü OptiFlow Pro 5.3.0 (değişmedi)*

## SGK ay sonu faturası (4.16.1)

SGK'ya sipariş başına fatura kesilmez. Ay içinde Medula'ya işlenen reçeteler birikir, ay sonunda **tek fatura** kesilir.

### 1. Reçeteyi Medula'ya işleyince işaretleyin

- Siparişin sağdaki **SGK reçetesi** kartında "Medula'ya işlenmedi" yazar. İşlem tarihini seçip **Medula'ya işlendi**'ye basın.
- Yanlış işaretlediyseniz "İşaretlenmedi olarak geri al" deyin.
- Reçete, Medula'ya işlendiği ayın faturasına girer.
- Faturası kesilmiş reçetenin işareti, fatura iptal edilmeden kaldırılamaz.

### 2. Ay sonunda: Faturalar › SGK ay sonu faturası

Aynı ekrana SGK mutabakat › "Ay sonu faturası" bağlantısından da gidilir.

1. **Dönemi seçin.** Üstte faturalanacak reçete sayısı ve tutarı, yanında Medula'ya işlenmemiş SGK'lı siparişlerin sayısı görünür.
2. **Medula dökümüyle karşılaştırın.** Medula'dan aldığınız PDF dökümü seçip **Karşılaştır**'a basın. Sonuçta şunlar çıkar:
   - dökümdeki adet ile OptiFlow'da o ay işaretlenen adet ("tutuyor" ya da fark);
   - Medula'da olup OptiFlow'da işaretlenmemiş reçeteler (tek tıkla siparişe gidip işaretlersiniz);
   - OptiFlow'da işaretli olup dökümde olmayan reçeteler;
   - e-reçete numarası girilmemiş siparişler.

   PDF okunmazsa (taranmış ya da resim olabilir) Medula'daki listeyi kopyalayıp yapıştırın ya da adedi elle yazın. Döküm sunucuda saklanmaz.
3. **Unuttuklarınızı işaretleyin.** "Medula'ya işlendi işaretlenmemiş" listesinden seçip toplu işaretleyebilirsiniz.
4. **Faturayı oluşturun.** **Seçilenlerle SGK faturası taslağı oluştur**'a basın. Tek fatura (alıcı SGK, satırda "Optik reçete bedeli · Eylül 2026 · N reçete" yazar) ve yazdırılabilir **reçete dökümü** (fatura eki) oluşur.
   - Medula'nın dönem tutarı farklıysa "Medula dönem toplamı" kutusuna yazın; fatura o tutarla kesilir.

Aynı reçete iki kez faturalanamaz. Faturayı iptal ederseniz reçeteler yeniden seçilebilir.

### Sipariş faturası

Siparişteki "Fatura taslağı" artık yalnızca **hasta payını** içerir.
Önceki sürümde sipariş başına açılmış SGK taslakları, dönem faturası oluşturulunca kendiliğinden iptal edilir.

## GitHub'dan canlıya otomatik yükleme

Bir kez ayar yapılınca her yeni sürüm hosting'e kendiliğinden yüklenir; zip indirip yüklemeniz gerekmez.

1. **FTP kullanıcısını hazırlayın.** Plesk › optiflow.com.tr › **FTP Erişimi**'nde mevcut kullanıcıyı kullanın ya da yalnızca `httpdocs`'a erişen yeni bir kullanıcı açın.
2. **Bilgileri GitHub'a girin.** GitHub › depo › **Settings › Secrets and variables › Actions › New repository secret** ile üç sır ekleyin:
   - `FTP_SUNUCU` (ör. optiflow.com.tr)
   - `FTP_KULLANICI`
   - `FTP_SIFRE`

   Site klasörü `httpdocs` değilse dördüncü sır olarak `FTP_KLASOR` ekleyin.
3. **Bundan sonra her sürümde** sıra şöyle işler: testler, Release, ardından **Canlıya al** (FTPS ile yükleme).
   - `config.php` ve `storage/` klasörüne dokunulmaz, hiçbir dosya silinmez.
   - Masaüstü kurulumu yalnızca sürümü değiştiyse yüklenir; `latest.yml` en son yüklenir.
4. İlk personel girişinde veritabanı kendiliğinden güncellenir.

Elle tekrar yüklemek için: GitHub › Actions › **Canlıya al** › Run workflow.

---

## Garanti kaydı ve garanti kartı (4.16.0)

## Açmak

Merkez panel › mağaza › **Özellikler** › **Garanti kaydı ve garanti kartı**. Varsayılan olarak kapalıdır.
Açılınca menüde (Atölye) **Garantiler** görünür, sipariş sayfasına **Garanti** bölümü eklenir.

## Ayarlar (Garantiler sayfasının altı, yalnızca yönetici)

| Ayar | Varsayılan | Not |
|---|---|---|
| Çerçeve / cam / güneş gözlüğü / diğer garanti süresi | 24 ay | 1–120 ay. Satıcının ayıplı maldan sorumluluğu teslimden itibaren 2 yıldır (6502 s. Kanun); kısaltmadan önce danışmanınıza sorun. |
| Teslimde otomatik aç | açık | Sipariş "Teslim edildi" olunca garanti kendiliğinden açılır. |
| Talepte geçen süreyi ekle | açık | Tamamlanan talepte, talebin açık kaldığı gün sayısı garanti bitişine eklenir. |
| Garanti koşulları | hazır metin | Her satır kartta bir madde olarak basılır. |

## Günlük kullanım

1. **Garanti açılır.**
   - Teslimde kendiliğinden açılır. Gözlükte çerçeve ve cam, güneş gözlüğünde tek kalem olarak açılır.
   - İsterseniz siparişin **Garanti** bölümünden elle de açabilirsiniz: "Garanti aç" ya da "Başka kalem / farklı süre".
   - Başlangıç tarihi teslim tarihidir.
2. **Garanti kartını yazdırın.** Siparişin Garanti bölümünde › **Garanti kartı**.
   - Kartta her kalem için karekod, garanti no (G00012), başlangıç ve bitiş tarihi, koşullar ve imza alanları bulunur.
3. **Müşteri karekodu okutur.** Telefonunda mağaza girişi olmadan şunları görür:
   - garantinin geçerli olup olmadığı ve kalan süre;
   - taleplerinin durumu.
   
   Soyadının tamamı, telefonu, fiyat ve maliyet gösterilmez.
4. **Müşteri arızayla gelir.** Kartı **Barkod okut** ile okutun (ya da "G00012" yazın); garanti kaydı açılır.
   - Arızayı yazıp **Talep aç**'a basın.
   - Ürün tedarikçiye gidecekse **Tedarikçiye gönderildi** deyin.
   - **Tedarikçi formu** yazdırılır (müşteri bilgisi içermez) ya da WhatsApp'la gönderilir.
   - İş bitince **Talebi kapat**. Sonuç seçenekleri: tamir edildi, değişim, ücret iadesi ya da kapsam dışı (sebebini yazın, müşteri görür).
5. **Süresi dolmuşsa** talep açılmaz. Ücretli tamir için yeni tamir siparişi açın.
   - Müşterinin tamir siparişleri garanti sayfasında "tamir geçmişi" olarak listelenir.

**Liste filtreleri:** Geçerli · 30 günde bitecek · Açık talepler · Tedarikçide · Tümü.
Ad, telefon, ürün, seri no, G no ya da #sipariş no ile arayabilirsiniz.

## Müşteri karekod sayfalarında düzeltme

Sipariş fişindeki takip karekodu ve bakım kartı, müşterinin telefonunda **mağaza girişine** düşüyordu.
Bunun nedeni çok mağazalı yapıda sayfanın hangi mağazaya ait olduğunu bilmemesiydi.

- 4.16.0'dan itibaren adreslere mağaza numarası (`m=`) eklenir ve bu sayfalar oturum açmadan doğru mağazanın verisini gösterir.
- **Önceden basılmış** fişlerdeki karekodlarda bu numara yoktur. Onlar yalnızca mağazada (personel oturumuyla) açılır.
  Müşteriye yeni bağlantı gerekiyorsa fişi yeniden yazdırın.

## Kurulum

GitHub Releases › en son `v4.16.x-pro5.3.0`:
1. `optiflow-4.16.x-hosting.zip` dosyasını hosting ana klasörüne yükleyip üzerine çıkarın.
2. Bir kez personel girişi yapın; şema 27'ye kendiliğinden güncellenir. Yalnızca yeni tablolar eklenir.
3. Masaüstü değişmedi. 5.3.0 yüklüyse başka bir şey gerekmez.

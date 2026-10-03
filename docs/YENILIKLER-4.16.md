# OptiFlow 4.16 — Garanti kaydı, garanti kartı ve SGK ay sonu faturası

*3 Ekim 2026 · sunucu 4.16.1 (şema 27) · masaüstü OptiFlow Pro 5.3.0 (değişmedi)*

## SGK ay sonu faturası (4.16.1)

SGK'ya sipariş başına fatura kesilmez; ay içindeki reçeteler birikir, ay sonunda **tek fatura** kesilir.

- **Sipariş faturası** (siparişteki "Fatura taslağı") artık yalnızca **hasta payını** içerir; SGK payı düşülür ve notta "ay sonu toplu SGK faturasına girer" yazar.
- **Faturalar › SGK ay sonu faturası** (ya da SGK mutabakat › "Ay sonu faturası"):
  1. Dönemi seçin (ör. Eylül 2026).
  2. O ayın sonuna kadar **teslim edilmiş**, SGK payı olan ve henüz SGK faturasına girmemiş reçeteler listelenir. Önceki aydan kalanlar "önceki ay" diye işaretlidir; teslim edilmemişler listeye girmez.
  3. İstemediklerinizin işaretini kaldırın. Medula'nın dönem fatura tutarı OptiFlow toplamından farklıysa "Medula dönem toplamı"na yazın.
  4. **Seçilenlerle SGK faturası taslağı oluştur**: tek fatura (alıcı SGK, satır "Optik reçete bedeli · Eylül 2026 · N reçete") ve yazdırılabilir **reçete dökümü** (fatura eki: hasta, e-reçete no, teslim, SGK payı).
- Aynı reçete iki kez faturalanamaz. Faturayı iptal ederseniz reçeteler yeniden seçilebilir.
- Önceki sürümde sipariş başına açılmış SGK taslakları ekranın üstünde listelenir; "Hepsini iptal et" ile ya da dönem faturası oluşturulunca kendiliğinden iptal edilir. Hasta faturalarına dokunulmaz.
- SGK alıcı bilgileri (unvan, VKN, vergi dairesi) Ayarlar › e-Fatura'da; mali müşavirinizle doğrulayın.

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

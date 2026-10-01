# OptiFlow 4.16.0 — Garanti kaydı ve garanti kartı

*1 Ekim 2026 · sunucu 4.16.0 (şema 26) · masaüstü OptiFlow Pro 5.3.0 (değişmedi)*

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

GitHub Releases › `v4.16.0-pro5.3.0`:
1. `optiflow-4.16.0-hosting.zip` dosyasını hosting ana klasörüne yükleyip üzerine çıkarın.
2. Bir kez personel girişi yapın; şema 26'ya kendiliğinden güncellenir. Yalnızca yeni tablolar eklenir.
3. Masaüstü değişmedi. 5.3.0 yüklüyse başka bir şey gerekmez.

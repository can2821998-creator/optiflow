# OptiFlow 4.17 — Hızlı satış ve ürün kataloğu

*5 Ekim 2026 · sunucu 4.17.0 (şema 29) · masaüstü OptiFlow Pro 5.3.0 (değişmedi)*

Güneş gözlüğü, solüsyon, kontakt lens, kılıf gibi hazır ürünler artık **sipariş açmadan** satılır: barkodu okutun, ödemeyi alın, fişi verin. Stok kendiliğinden düşer. Satış gün sonu kasasına, raporlara ve prime yansır.

## Açmak

Merkez panel › mağaza › **Özellikler** › **Hızlı satış ve ürün kataloğu**. Varsayılan olarak kapalıdır.

Açılınca menüde iki yeni sayfa görünür:
- **Hızlı satış**: Siparişler'in hemen altında.
- **Ürün kataloğu**: Çerçeve stoğu'nun altında.

Bu sayfalar yalnızca tutarları görebilen kullanıcılara görünür.

## 1. Ürünleri kataloğa girin

**Ürün kataloğu** › sağdaki formu doldurun:

- **Ürün adı ve kategori.** Kategoriler: güneş gözlüğü, kontakt lens, solüsyon / damla, aksesuar, hizmet (montaj, tamir…) ya da diğer.
- **Barkod.** Ürünün kutusundaki barkodu okutun. Barkodu yoksa boş bırakın; sistem `PU` ile başlayan bir numara verir.
- **Satış fiyatı.** Boş bırakırsanız fiyat satış sırasında girilir. Fiyatı değişken hizmetler için kullanışlıdır.
- **Alış fiyatı.** Kâr ve prim hesabında kullanılır.
- **KDV, ilk stok ve kritik adet.**
- **Stok takibi.** Hizmetlerde kendiliğinden kapalıdır.

Ürünü açınca alttaki **Stok girişi / sayım** kutusundan üç işlem yapılır:
- **Stok girişi:** adet eklenir.
- **Sayım:** "elimde şu kadar var" denir, fark otomatik bulunur.
- **Fire:** kırılan ya da kaybolan ürün düşülür.

Her hareket ürünün altında listelenir.

Çerçeveler eskisi gibi **Çerçeve stoğu**'nda kalır; hızlı satış onları da barkodla bulur.

## 2. Satış

**Hızlı satış** ekranında imleç barkod kutusundadır.

1. **Ürünleri sepete ekleyin.** Ürünü dört yoldan biriyle ekleyebilirsiniz:
   - **Barkod okuyucuyla okutun.** Aynı ürünü tekrar okutmak adedi artırır.
   - **Telefonda ya da tablette** kutunun yanındaki göz simgesine basıp kamerayla okutun.
   - **Adını yazın** ("solüs", "kılıf") ve listeden seçin.
   - **Katalogda olmayan bir şey için** **Serbest kalem**'e basın, adını ve fiyatını yazın.
2. **Gerekirse indirim yapın.** Satırdaki **İndirim** kutusu o kaleme, sağdaki **Sepet indirimi** bütün sepete uygulanır.
3. **Ödemeyi girin.**
   - Nakit, kart, havale ya da diğer kutularına tutarları yazın.
   - **Kalanı** düğmesi kalan tutarın tamamını o yönteme yazar. Örnek: 1.000 TL nakit yazın, kart satırında **Kalanı**'na basın.
   - Kutu "Ödeme tamam" deyince satış tamamlanabilir.
4. **İsteğe bağlı alanlar.**
   - **Para üstü hesapla:** müşteriden alınan nakdi yazın, para üstü görünür.
   - **Müşteriye bağla:** ad ya da telefonla arayıp satışı müşteri kaydına bağlayın.
   - **Not.**
5. **Satışı tamamla**'ya basın. Satış numarası ve **Fiş** düğmesi çıkar.

Ekranın altında günün satışları listelenir ve fişler buradan yeniden yazdırılabilir.

**Stok bitmişse** satış durmaz: ürün eksiye düşer, sayımda düzeltilir. Ekranda "stokta N" uyarısı görünür.

## Yetkiler

- **İndirim sınırı.** Personel en fazla **%20** indirim yapabilir. Oran Ayarlar'daki `hizli_satis_indirim_yuzde` değeriyle değiştirilir. Yöneticinin sınırı yoktur.
- **Satış listesi.** Personel yalnızca kendi satışlarını görür.
- **İptal.** İptali yalnızca yönetici yapar: satışı açın › **Satışı iptal et**, sebebi yazın.
  - Ürünler stoğa geri girer.
  - Satış hiçbir toplamda sayılmaz, ama kayıt silinmez ve fişte "İPTAL" yazar.

## Nereye yansır?

| Ekran | Ne görünür |
|---|---|
| **Gün sonu kasa** | Nakit, kart ve havale toplamlarına, beklenen nakde ve personel kırılımına dahil. "Hızlı satışlar" kartında günün adedi ve tutarı. Kasa dökümü de aynı. |
| **Raporlar** | Ciroya ("N hızlı satış dahil"), tahsilata, günlük seriye, personel ve ödeme yöntemi dağılımına dahil. |
| **Kârlılık ve prim** | Satış ve maliyete (ürün alış fiyatları), brüt kâra ve personel primine dahil. |
| **Çerçeve / ürün hareketleri** | "Satış · satış #N" olarak, satışa bağlantıyla görünür. |

Kasadaki **Diğer gelirler** yine kullanılabilir. Ürün satışı içinse **Hızlı satış** önerilir, çünkü stok da düşer.

## Henüz olmayanlar (sonraki adımlar)

- Ürün etiketi basma (etiket sihirbazı şimdilik yalnızca çerçeve basıyor).
- Ürünleri Excel'den toplu yükleme.
- Müşteri kartında hızlı satış geçmişi.
- Hızlı satıştan e-Arşiv fatura taslağı.

## Kurulum

GitHub'a gönderilen sürüm kendiliğinden canlıya yüklenir. İlk personel girişinde veritabanı şema 29'a güncellenir; yalnızca yeni tablolar eklenir, mevcut veriye dokunulmaz.

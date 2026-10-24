# Beta ortamı — beta.optiflow.com.tr (4.56.0)

**Amaç:** Yeni sürüm önce pilot mağazaya çıkar, gün içinde denenir; gece kimse kullanmazken (02:30) herkese geçer.

```
main'e sürüm push'u ─► "Sürüm yayınla" (testler + Release) ─► BETA'ya yüklenir ─► pilot gün boyu dener
                                                                                     │
                       her gece 02:30 ◄──────────────────────────────────────────────┘
                       canlı o sürümde değilse + sürüm beta'da yayındaysa + CANLI_DURDUR yoksa ─► CANLIYA yüklenir
```

## Nasıl çalışır?
- Beta ile canlı **aynı veritabanlarını** kullanır. Pilot gerçek verisiyle çalışır ve iki adres arasında veri farkı oluşmaz.
- Beta'ya yalnızca merkez panelinde **"Beta kanalı (pilot)"** özelliği açık olan mağazalar girebilir. Diğer mağazalara
  "canlı adresten girin" sayfası çıkar. Bu kontrol veritabanı güncellemesinden önce yapılır, böylece başka mağazaların
  veritabanına dokunulmaz.
- Beta'da zamanlanmış görevler (cron) çalışmaz. Bu sayede WhatsApp mesajları ve e-postalar iki kez gitmez.
- Müşteriye giden bağlantılar (fiş karekodu, WhatsApp) beta'da da canlı adresle üretilir.
- Beta'dan yeni kayıt alınmaz; kayıt sayfası canlıya yönlenir. Beta arama motorlarına kapalıdır (noindex + robots).
- Beta sayfalarının üstünde koyu bir **BETA** şeridi görünür.

> **Geliştirici kuralı:** Beta'daki göç canlı veritabanını da değiştirir. Bu yüzden göçler **yalnızca ekleme** yapmalıdır.
> Bir sütunu silmek ya da adını değiştirmek iki sürüme bölünür: önce yeni sütun eklenir ve kullanılır, eskisi bir
> sonraki sürümde kaldırılır.

## Bir kez yapılacak kurulum (~20 dk)

1. **DNS (TürkTicaret › alan adı › DNS):** `beta` için bir **A kaydı** ekleyin. Değeri, `optiflow.com.tr` A kaydındaki
   sunucu IP'si olsun.
2. **Plesk › Web siteleri › optiflow.com.tr › Alt alan adı ekle:** `beta` girin. Belge kökü olarak
   `beta.optiflow.com.tr` varsayılanını bırakın. PHP sürümünü canlıyla aynı yapın (8.3).
3. **Plesk › beta.optiflow.com.tr › SSL/TLS:** Let's Encrypt sertifikası alın ve "HTTP'den HTTPS'ye yönlendir"i açın.
4. **Dosya Yöneticisi** ile `httpdocs` içinden beta klasörüne kopyalayın:
   - `config.php` (aynı veritabanı bilgileri; değiştirmeyin),
   - `storage/.gizli-anahtar`. Bu dosya **mutlaka** canlıdakiyle aynı olmalı. Eksikse beta kayıtlı şifreleri
     açamaz ve hata verir.
   - İsteğe bağlı: `storage/eposta/` ve `storage/seo/`.
   `storage/` klasörü yoksa oluşturun ve yazma izni verin.
5. **Beta'da cron kurmayın.** Kurulsa bile çalışmaz ve "yalnız canlıda" der.
6. **Merkez paneli › pilot mağaza › Özellikler:** "Beta kanalı (pilot)" özelliğini açın. Pilot mağazaya
   https://beta.optiflow.com.tr adresinden giriş yapmasını söyleyin.
7. **GitHub › depo › Settings › Secrets and variables › Actions › Variables:**
   - `BETA_ACIK` = `1`. Bu değişken tanımlanana kadar yayınlar eskisi gibi doğrudan canlıya gider.
   - İsteğe bağlı: `BETA_KLASOR` (FTP'deki klasör adı başkaysa; varsayılan `beta.optiflow.com.tr`),
     `BETA_ADRESI` (varsayılan `https://beta.optiflow.com.tr`).
8. **İlk yükleme:** Actions › "Canlıya al" › Run workflow › hedef = `beta`. Bittiğinde
   https://beta.optiflow.com.tr/VERSION.txt son sürümü göstermelidir.

## Günlük kullanım
| Durum | Ne yapılır |
|---|---|
| Normal gün | Hiçbir şey. Gün içinde çıkan sürümler beta'ya gider, en sonuncusu gece 02:30'da canlıya geçer. |
| Pilot sorun bildirdi, gece canlıya geçmesin | Variables › `CANLI_DURDUR` = `1`. Sorun düzelince düzeltmeyi yayınlayın ve değişkeni silin. |
| Acil düzeltme hemen herkese gitsin | Önce düzeltmeyi yayınlayın (beta'ya gider). Sonra Actions › "Canlıya al" › Run workflow › hedef = `canli`. Bu yolda beta şartı aranmaz. |
| Gece işi neden yüklemedi? | Actions › "Canlıya al" kaydındaki notta yazar: canlı zaten güncel / CANLI_DURDUR / sürüm beta'da görünmüyor. |

## Bilinen sınırlar
- **OptiFlow Pro (masaüstü)** sunucu adresini derleme sırasında alır ve şimdilik canlıya bağlanır. Veriler ortak olduğu için
  sorun çıkmaz; ancak pilot, masaüstünde yeni sunucu kodunu gece canlıya geçince görür. İleride: Pro'ya "beta kanalı"
  seçeneği eklenecek (yalnızca https://beta.optiflow.com.tr kabul edilecek).
- Masaüstü kurulum dosyası (exe) yalnızca canlıya yüklenir.
- Tarayıcı eklentisi köprüsü beta'da kapalıdır; eklenti canlı adresi kullanır.

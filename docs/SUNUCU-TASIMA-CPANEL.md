# OptiFlow — TürkTicaret hostingden kiralık cPanel sunucuya taşıma

*23 Ekim 2026 · sunucu 4.42.0 · şema 35*

Kodda değişiklik gerekmez. Taşınacak üç şey var:

1. **Site klasörü**: kod, `config.php`, `storage/` ve `indir/`.
2. **Veritabanları**: merkez veritabanı ve **her mağazanın kendi veritabanı**.
3. **Alan adının yönü**: DNS'te A kaydı.

Masaüstü (OptiFlow Pro), telefon asistanı ve Medula köprüsü adrese (`optiflow.com.tr`) bağlıdır.
Alan adı değişmediği için hiçbirine dokunmak gerekmez.

---

## 0. Başlamadan (taşımadan 1 gün önce)

- **DNS TTL'ini düşürün.** Alan adının DNS'i nerede yönetiliyorsa (büyük olasılıkla TürkTicaret paneli) `@` ve `www`
  kayıtlarının TTL'ini **300 saniyeye** indirin. Böylece geçiş günü yeni adres dakikalar içinde yayılır.
- Sunucu bilgilerini not edin: sunucunun **IP adresi**, cPanel kullanıcı adı, WHM (root) erişimi olup olmadığı.
- Eski hostingte e-posta kutusu (`...@optiflow.com.tr`) varsa not edin. DNS'te MX kaydına dokunulmayacak (aşağıda anlatılıyor).

## 1. Sunucuyu hazırlayın (cPanel)

**PHP ayarları**
- *MultiPHP Manager*: `optiflow.com.tr` için **PHP 8.2 veya 8.3** seçin.
- *MultiPHP INI Editor* değerleri:
  - `memory_limit = 256M`
  - `upload_max_filesize = 64M`
  - `post_max_size = 64M`
  - `max_execution_time = 120`
  - `date.timezone = Europe/Istanbul`

**PHP eklentileri**
- Gerekenler: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `json`, `sodium`.
- WHM'iniz varsa *EasyApache 4* üzerinden eklenir. Yalnızca cPanel varsa *Select PHP Version* ekranından eklenir.
  Bu ikisi de yoksa sunucu firmasından isteyin.
- Kurulumdan sonra `kontrol.php` eksik eklenti olursa gösterir.

**Veritabanları** (*Manage My Databases*)
- Eski hostingte ne kadar veritabanı varsa o kadar açın: merkez veritabanı ve her mağaza için bir tane.
- cPanel adların başına kullanıcı adınızı ekler (ör. `kullanici_optiflow`, `kullanici_magaza1`). Bu normaldir.
  Adlar değiştiği için 4. adımda mağaza bilgileri güncellenecek.
- Bir veritabanı kullanıcısı açın (güçlü şifre) ve **tüm veritabanlarına "ALL PRIVILEGES"** ile ekleyin.
  Her mağazaya ayrı kullanıcı da açabilirsiniz; tek kullanıcı daha kolaydır.

**SSL**
- DNS yeni sunucuya dönene kadar beklenir (6. adım).

## 2. Eski hostingten verileri alın

En sessiz saati seçin (mağazalar kapalıyken, ör. gece). Bu sırada kimse kayıt girmesin.
Girilirse o kayıtlar eski sunucuda kalır.

**Site klasörü**
- Plesk › *Dosya Yöneticisi* › `httpdocs` klasörünün tamamını **zip'leyin** ve indirin.
- İçinde `config.php`, `storage/` ve `indir/` (masaüstü kurulumu, APK) da olmalı.
- **`storage/.gizli-anahtar` mutlaka gelmeli.** Bu dosya PayTR ve WhatsApp anahtarlarını çözer.

**Veritabanları**
- Plesk › *Veritabanları* › her veritabanı için *Dışa aktar* ya da phpMyAdmin › *Dışa aktar* ile alın.
- Biçim **SQL**, sıkıştırma **gzip** olsun.
- Merkez veritabanı ve tüm mağaza veritabanları alınmalı.

## 3. Yeni sunucuya yükleyin

**Dosyalar**
- cPanel › *File Manager* › `public_html` içine zip'i yükleyin ve *Extract* deyin.
- `index.php` doğrudan `public_html` içinde olmalı, alt klasörde olmamalı.

**Veritabanları**
- Her `.sql.gz` dosyasını kendi yeni veritabanına içe aktarın: cPanel › *phpMyAdmin* › veritabanını seç › *İçe aktar*.
- Dosya büyükse ve SSH varsa şu komut kullanılabilir:
  `gunzip < dosya.sql.gz | mysql -u kullanici -p kullanici_dbadi`

**`config.php`** (`public_html` içinde)
- `db` bölümüne merkez veritabanının **yeni** adını, kullanıcısını ve şifresini yazın. `host` değeri `localhost` kalır.

## 4. Mağaza veritabanı bilgilerini güncelleyin

Mağaza veritabanlarının adı, kullanıcısı ve şifresi merkez veritabanındaki `magazalar` tablosunda tutulur ve hâlâ eski adları gösterir.

- `optiflow.com.tr/merkez-panel.php` sayfasına girin.
- Her mağazayı açın ve **"Veritabanı bağlantısı"** bölümüne yeni değerleri yazıp kaydedin:
  - Host: `localhost`
  - Veritabanı adı ve kullanıcı: cPanel'deki öneki taşıyan yeni adlar.

Bu adım 5. adımdaki test sırasında yapılır (sunucu henüz canlı değilken).

## 5. DNS'i değiştirmeden test edin

Kendi bilgisayarınızın `hosts` dosyasına bir satır ekleyerek siteyi yeni sunucudan açabilirsiniz.

1. Windows'ta Not Defteri'ni **yönetici olarak** açın.
2. `C:\Windows\System32\drivers\etc\hosts` dosyasını açın.
3. En alta şunu ekleyin:
   ```
   SUNUCU_IP  optiflow.com.tr
   SUNUCU_IP  www.optiflow.com.tr
   ```
4. Tarayıcıda siteyi açın. Sertifika uyarısı çıkar (SSL henüz yok); bu testte normaldir.
5. Şunları deneyin:
   - `kontrol.php`
   - merkez panel (4. adımı burada yapın)
   - bir mağazaya giriş
   - sipariş listesi, müşteri, döküm
6. Test bitince `hosts` dosyasına eklediğiniz satırları silin.

## 6. Alan adını yeni sunucuya yönlendirin

Alan adının DNS'i nerede yönetiliyorsa orada (TürkTicaret › Alan adları › DNS yönetimi):

| Kayıt | Tür | Değer |
|---|---|---|
| `@` (optiflow.com.tr) | A | **yeni sunucu IP'si** |
| `www` | A (ya da CNAME → optiflow.com.tr) | **yeni sunucu IP'si** |
| MX / e-posta kayıtları | — | **dokunmayın** (e-posta eski yerde çalışmaya devam eder) |

- **Nameserver değiştirmeyin.** Yalnızca A kaydını değiştirmek en basit yoldur ve geri alması da kolaydır.
  Nameserver'ı sunucuya taşırsanız e-posta dahil tüm DNS kayıtlarını cPanel'de yeniden kurmanız gerekir.
- TTL 300 olduğu için genellikle 5–30 dakikada yayılır. Durumu dnschecker.org gibi bir sitede izleyebilirsiniz.
- Yayıldıktan sonra cPanel › *SSL/TLS Status* › **Run AutoSSL** deyin. Ücretsiz Let's Encrypt sertifikası kurulur.

## 7. Zamanlanmış görev (cron)

`cron.php` WhatsApp gönderimini, ödeme linklerini, ÜTS kuyruğunu ve senet vade uyarılarını çalıştırır.

1. Anahtarlı tam adres merkez paneldeki "Zamanlanmış görev" kutusunda yazar. Anahtar `storage/.cron-anahtar` dosyasından gelir ve taşımayla aynı kalır.
2. cPanel › *Cron Jobs* › **her 10 dakikada bir** (`*/10 * * * *`) şu komutu ekleyin:
   ```
   curl -fsS -m 300 "https://optiflow.com.tr/cron.php?anahtar=ANAHTAR" >/dev/null
   ```
3. **Eski Plesk'teki zamanlanmış görevi silin.** İkisi farklı veritabanlarında çalışacağı için kilit birbirini görmez.

## 8. GitHub'dan otomatik canlıya alma

"Canlıya al" iş akışı her sürümde dosyaları FTP ile yükler. DNS değiştikten sonra GitHub › depo › Settings › Secrets and
variables › Actions sırlarını güncelleyin:

| Sır | Yeni değer |
|---|---|
| `FTP_SUNUCU` | sunucu IP'si ya da `optiflow.com.tr` |
| `FTP_KULLANICI` | cPanel kullanıcı adı (ya da cPanel › FTP Accounts'ta açtığınız hesap) |
| `FTP_SIFRE` | o hesabın şifresi |
| `FTP_KLASOR` | `public_html` (Plesk'teki `httpdocs` yerine) |

Sırlar güncellenmezse sonraki sürüm **eski hostinge** yüklenir.
Güncelledikten sonra Actions › "Canlıya al" › *Run workflow* ile bir kez deneyin.

## 9. Sonra

- **Eski hostingi 1–2 hafta açık tutun.** Bir sorun çıkarsa A kaydını eski IP'ye çevirmeniz yeterli.
  İptalden önce eski hostingteki dosyaları ve veritabanlarını silin (KVKK).
- **Yedek:** cPanel › *Backup* ya da firmanın JetBackup'ı. Ayrıca düzenli olarak sunucu dışına yedek alın.
  `storage/` klasörü yedeğe dahil olmalı.
- **Sunucu güvenliği** (WHM/root varsa):
  - root şifresini değiştirin, SSH'ı anahtarla kullanın;
  - güvenlik duvarını açık tutun (CSF/Imunify);
  - cPanel otomatik güncellemesini açık bırakın.
- **Yeni mağaza açılışı:** şu an "beklemede" modu sürüyor; yeni mağaza için veritabanını cPanel'de elle açıp merkez panelden
  etkinleştirirsiniz. cPanel kullanıcısı SQL ile veritabanı açamaz.
  İstenirse bir sonraki adımda cPanel API (UAPI) ile **otomatik veritabanı açma** eklenebilir. Bu bir kod işidir ve sunucuda bir API token'ı gerekir.

## Kısa kontrol listesi

- [ ] TTL 300
- [ ] PHP 8.2/8.3 + eklentiler
- [ ] Veritabanları + kullanıcı (cPanel)
- [ ] httpdocs zip → public_html
- [ ] SQL içe aktarma (merkez + tüm mağazalar)
- [ ] config.php yeni merkez DB bilgileri
- [ ] hosts ile test
- [ ] merkez panelde her mağazanın DB bilgisi güncellendi
- [ ] DNS A kaydı (@ + www) → yeni IP
- [ ] AutoSSL
- [ ] cron (yeni) + eski cron silindi
- [ ] GitHub FTP sırları (FTP_KLASOR = public_html)
- [ ] eski hosting 1–2 hafta yedek olarak açık

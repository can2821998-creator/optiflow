# OptiFlow — TürkTicaret paylaşımlı hostingden kiralık sunucuya (Plesk) taşıma

*23 Ekim 2026 · sunucu 4.42.0 · şema 35 · yeni sunucu: Plesk Obsidian, yönetici (Administrator) erişimli, boş*

Yeni sunucu da **Plesk** kullanıyor; şu anki hostingle aynı yapı. Klasör yine `httpdocs`, zamanlanmış görev yine
"URL getir", GitHub'ın canlıya alma ayarı yine aynı. **Kodda değişiklik gerekmez.**

Taşınacak üç şey var:
1. Site klasörü (`httpdocs`)
2. Veritabanları: merkez ve her mağaza
3. Alan adının A kaydı

Masaüstü (OptiFlow Pro), telefon asistanı ve Medula köprüsü `optiflow.com.tr` adresine bağlıdır. Adres değişmediği için bunlara dokunulmaz.
Sunucu IP'si bu dosyaya yazılmaz (depo herkese açık). Aşağıda `SUNUCU_IP` yazan yerlere kendi IP'nizi koyun.

---

## 0. Bir gün önce
- **TTL'i düşürün:** alan adının DNS'i nerede yönetiliyorsa (TürkTicaret › Alan adları › DNS) `@` ve `www` kayıtlarının
  TTL'ini **300** saniyeye indirin.
- **Plesk güncellemesi:** "Failed to update Plesk" uyarısı varsa Tools & Settings › Updates'ten bir kez daha deneyin.
  Yine olmazsa sunucu firmasına bildirin. Taşımaya engel değildir ama güvenlik güncellemeleri için düzelmeli.

## 1. Yeni sunucuda alan adını açın
1. Websites & Domains › **Add Domain** › *Blank website*.
2. Alan adı: `optiflow.com.tr`.
3. Hosting: **Website hosting**. Bir **sistem kullanıcısı** ve şifresi belirleyin; bu bilgiler FTP için de kullanılır, not edin.
4. Sertifika: şimdilik "Let's Encrypt" seçmeyin. DNS dönmeden alınamaz, 6. adımda alınacak.
5. **E-posta:** e-postanız TürkTicaret'te kalacaksa alan adının *Mail* ayarında e-posta hizmetini **kapatın**.
   Açık kalırsa sunucu `@optiflow.com.tr` adreslerini kendisinde arar.

**PHP ayarları** (alan adı › *PHP*):
- **8.3** (ya da 8.2), işleyici "FPM application served by Apache".
- `memory_limit 256M`, `upload_max_filesize 64M`, `post_max_size 64M`, `max_execution_time 120`.

## 2. Veritabanlarını açın
Alan adı › **Databases** › *Add Database*. Eski hostingte ne varsa açın: merkez ve her mağaza için bir tane.

İki yol var:
- **En kolayı:** eski hostingle **aynı ad, kullanıcı ve şifreyi** verin. O zaman `config.php` ve mağaza bilgileri
  hiç değişmez. Adlar ve kullanıcılar eski Plesk › Databases'te görünür. Merkezin şifresi `config.php`'de yazar.
- Ya da yeni adlar ve tek bir kullanıcı ("Access to: any database"). Bu durumda 4. adımda `config.php` ve merkez panelde her mağazanın
  **Veritabanı bağlantısı** bölümü güncellenir.

## 3. Eski hostingten alın (mağazalar kapalıyken)
Taşıma sırasında kimse kayıt girmesin; girilirse o kayıt eski sunucuda kalır.

- **Dosyalar:** eski Plesk › Files › `httpdocs` klasörünün tamamını seçip *Add to archive* deyin, zip'i indirin.
  İçinde `config.php`, `storage/` (özellikle **`storage/.gizli-anahtar`**: PayTR ve WhatsApp anahtarları) ve `indir/` olmalı.
- **Veritabanları:** eski Plesk › Databases › her biri için **Export Dump** ile indirin (merkez ve tüm mağazalar).

## 4. Yeni sunucuya yükleyin
- **Dosyalar:** yeni Plesk › Files › `httpdocs` içindeki varsayılan `index.html` dosyasını silin. Zip'i yükleyin › *Extract*.
  `index.php` doğrudan `httpdocs` içinde olmalı.
- **Veritabanları:** her veritabanı › **Import Dump** ile kendi dökümünü yükleyin.
- Veritabanlarına yeni adlar verdiyseniz:
  - `config.php` › `db` bölümüne merkezin yeni bilgilerini yazın;
  - merkez panelde her mağazanın **Veritabanı bağlantısı** bölümünü güncelleyin. Bu, 5. adımdaki test sırasında yapılır.

## 5. DNS'i değiştirmeden test edin
1. Windows'ta Not Defteri'ni **yönetici olarak** açın.
2. `C:\Windows\System32\drivers\etc\hosts` dosyasını açıp en alta şunu ekleyin:
   ```
   SUNUCU_IP  optiflow.com.tr
   SUNUCU_IP  www.optiflow.com.tr
   ```
3. Tarayıcıyı kapatıp açın. Bu deneme sırasında site yeni sunucudan gelir; sertifika uyarısı çıkması normaldir.
4. Şunları deneyin: `kontrol.php`, merkez panel, bir mağazaya giriş, sipariş, müşteri, döküm.
   Gerekirse merkez panelde veritabanı bilgilerini güncelleyin.
5. Test bitince eklediğiniz satırları silin.

## 6. Alan adını yönlendirin
TürkTicaret › Alan adları › DNS yönetimi:

| Kayıt | Tür | Değer |
|---|---|---|
| `@` | A | `SUNUCU_IP` |
| `www` | A | `SUNUCU_IP` |
| MX ve e-posta kayıtları | — | **dokunmayın** |

- **Nameserver'ları değiştirmeyin.** Yalnızca A kaydını değiştirmek en basit yoldur; sorun çıkarsa eski IP'ye geri çevirirsiniz.
- Yeni adres genellikle 5–30 dakikada yayılır (dnschecker.org'dan izlenebilir).
- Ardından yeni Plesk'te alan adı › **SSL/TLS Certificates** › *Let's Encrypt* ile `optiflow.com.tr` ve `www` için sertifika alın.
  "Redirect from http to https" açık olsun.
- Plesk panelindeki **"Güvenli değil"** uyarısı için: Tools & Settings › SSL/TLS Certificates › *Certificate for securing Plesk*
  › aynı Let's Encrypt sertifikasını seçin. Panel artık `https://optiflow.com.tr:8443` adresinden uyarısız açılır.

## 7. Zamanlanmış görev
1. Merkez paneldeki "Zamanlanmış görev" kutusunda yazan anahtarlı adresi kopyalayın.
2. Yeni Plesk › alan adı › **Scheduled Tasks** › *Fetch a URL* › her 10 dakikada bir (`*/10 * * * *`) çalışacak şekilde ekleyin.
3. **Eski hostingteki görevi silin.**

## 8. GitHub otomatik canlıya alma
Taşıma bittikten sonra GitHub › depo › Settings › Secrets and variables › Actions sırlarını güncelleyin:

| Sır | Değer |
|---|---|
| `FTP_SUNUCU` | `SUNUCU_IP` ya da `optiflow.com.tr` |
| `FTP_KULLANICI` | 1. adımdaki sistem kullanıcısı |
| `FTP_SIFRE` | onun şifresi |
| `FTP_KLASOR` | `httpdocs` (aynı kalır) |

Sırlar güncellenmezse sonraki sürüm **eski hostinge** yüklenir. Güncelledikten sonra Actions › "Canlıya al" › *Run workflow* ile bir kez deneyin.

## 9. Sonra
- **Eski hosting:** 1–2 hafta açık kalsın. İptalden önce oradaki dosyaları ve veritabanlarını silin (KVKK).
- **Yedek:** Tools & Settings › **Backup Manager** › zamanlanmış yedek; mümkünse sunucu dışına (FTP ya da bulut) da gitsin.
- **Güvenlik:** Imunify ve Plesk Firewall açık, Fail2Ban açık, yönetici şifresi güçlü, Plesk güncel.
- **Yeni mağazada otomatik veritabanı (isteğe bağlı, kod işi):** artık yönetici erişimi var.
  Yeni mağaza kaydolunca veritabanı Plesk'in kendi aracıyla (`plesk bin database` / Plesk API) otomatik açılabilir. Böylece Plesk yedeklerine de girer.
  Şimdilik "beklemede" modu sürer: yeni mağaza için veritabanını Plesk'te açıp merkez panelden etkinleştirirsiniz.

## Kontrol listesi
- [ ] TTL 300
- [ ] Add Domain + PHP 8.3 + e-posta hizmeti kapalı
- [ ] Veritabanları (tercihen eski ad, kullanıcı ve şifreyle)
- [ ] Eski httpdocs zip + dump'lar
- [ ] Yeni httpdocs'a çıkarıldı, dump'lar içe aktarıldı
- [ ] hosts ile test (kontrol.php, giriş, sipariş)
- [ ] DNS A kayıtları (@ ve www) → yeni IP
- [ ] Let's Encrypt (site ve panel)
- [ ] Yeni zamanlanmış görev eklendi, eskisi silindi
- [ ] GitHub FTP sırları güncellendi
- [ ] Eski hosting 1–2 hafta yedekte, sonra temizlenip iptal edildi

# OptiFlow — Durum raporu

*30 Eylül 2026 · sunucu **4.12.0**, masaüstü **OptiFlow Pro 5.2.0***

## 1. Özet

OptiFlow iki ürün olarak çalışıyor: web sürümü **OptiFlow Lite**, Windows uygulaması **OptiFlow Pro**.

- Chrome eklentisinin yerini alan masaüstü uygulaması OptiFlow'u ve SGK Medula'yı tek pencerede açıyor. Medula'daki reçeteyi tek tuşla OptiFlow'a aktarıyor.
- Hosting yeniden kuruldu. Otomatik güncelleme hosting üzerinden çalışıyor.
- Son sürümde (4.12.0 / 5.2.0) merkez panelinden mağaza bazında açılan dokuz yeni modül eklendi. Hepsi testten geçti ve teslim edildi.

## 2. Sürüm geçmişi

| Sürüm | Tarih | Öne çıkanlar |
|---|---|---|
| **4.9.0** | başlangıç | Devralınan PHP + MySQL sistemi (her mağazanın ayrı veritabanı var, mağazalar merkez tablosunda). |
| **4.10.0 / Masaüstü 5.0.0** | 24.09 | Electron masaüstü uygulaması (OptiFlow + Medula, güvenli köprü). 2 kritik hata düzeltmesi: personel girişi mağaza oturumunu siliyordu; eklenti köprüsü çok mağazalı yapıda çalışmıyordu. |
| **4.11.0 / Pro 5.1.0** | 25.09 | Lite/Pro paket ayrımı (SGK aktarımı ve ÜTS karekod Pro'da). Doğru Medula adresi. Uygulama tanıtım sayfası yerine doğrudan mağaza girişiyle açılıyor. "Güncellemeleri denetle" ile hosting'den güncelleme. |
| **Pro 5.1.1** | 28.09 | Medula'nın takılı kalan oturumu ve önbelleği temizleniyor; "Medula'yı sıfırla" düğmesi eklendi. |
| **Pro 5.1.2** | 28.09 | Medula girişinden sonraki "Medula dışındaki adres açılmadı" hatası düzeltildi (SGK girişte http'ye yönlendiriyordu; artık https'e çevriliyor). |
| **4.12.0 / Pro 5.2.0** | 30.09 | Merkezden açılan modüller: WhatsApp, PayTR, lens, cam siparişi, stok önerisi, e-Fatura hazırlığı, SGK mutabakat, barkod, çevrimdışı mod. Ayrıca imzalı derleme hattı. |

## 3. 4.12.0 / 5.2.0 ile gelen modüller

Hepsi **merkez panel › mağaza › Özellikler** kartından açılıp kapanıyor ve varsayılan olarak **kapalı**.

| Modül | Durum | Not |
|---|---|---|
| WhatsApp mesaj otomasyonu | ✅ Hazır | İki kanal var: tek tıkla WhatsApp uygulamasından ya da Meta Cloud API ile otomatik. Müşteri izni kaydediliyor. |
| PayTR ödeme linki | ✅ Hazır | Ödeme bildirimi HMAC ile doğrulanıyor, tahsilat kendiliğinden yazılıyor. Test modunda tahsilat yazılmıyor. |
| Kontakt lens takibi | ✅ Hazır | Bitiş tarihi hesaplanıyor, Hatırlatmalar › Kontakt lens sekmesinde listeleniyor. |
| Tedarikçiye cam siparişi | ✅ Hazır | Fiş yazdırılabiliyor ya da WhatsApp / e-posta ile gönderiliyor. Depo · Stok akışına bağlı. |
| Stok satın alma önerisi | ✅ Hazır | Kritik çerçeveler 90 günlük satış hızına göre; eksik camlar tedarikçi bazında. |
| e-Arşiv / e-Fatura | 🟡 Hazırlık | Taslak, UBL-TR 1.2 belgesi ve entegratör arayüzü hazır. **GİB'e gönderim bir sonraki sürümde.** |
| SGK mutabakat + bekleyen reçeteler | ✅ Hazır | Hak tarihi artık gerçek reçete tarihinden hesaplanıyor. Medula listesinden yalnızca reçete numaraları okunuyor. |
| Barkod / karekod okuyucu modu | ✅ Hazır (yalnızca Pro) | ÜTS karekodu, çerçeve barkodu ve sipariş no okunuyor. |
| Çevrimdışı salt okunur mod | ✅ Hazır (yalnızca Pro) | Kopya Windows hesabına bağlı şifrelemeyle saklanıyor; çıkışta siliniyor. |
| Kod imzalama hattı | 🟡 Sertifika bekliyor | `npm run dist:win:imzali` (.pfx ya da Azure) ve GitHub Actions iş akışı hazır. |

## 4. Test sonuçları (30.09)

| Test | Sonuç |
|---|---|
| Sunucu: yeni modüller | **76 / 76** |
| Sunucu: masaüstü köprüsü ve mağaza izolasyonu | **46 / 46** |
| Masaüstü birim testleri | **88 / 88** |
| Masaüstü uçtan uca (gerçek uygulama, yerel sunucu) | **57 / 57** |
| Hosting zip'inin temiz klasörde çalışması | ✅ Tüm yeni sayfalar hatasız açıldı |

Son incelemede düzeltilenler:
- Ödeme linki oluşturma ve iptal, sunucu tarafında tahsilat yetkisini kontrol etmiyordu. Artık ediyor.
- PayTR test modunda gelen ödeme, gerçek siparişin bakiyesine yazılıyordu. Artık yazılmıyor.
- Şifreleme anahtarı ve zamanlanmış görev anahtarı ilk oluşturulurken eşzamanlı isteklerde bozulabiliyordu. Artık oluşturma atomik.
- CSP yüzünden bazı silme onayları hiç sorulmuyordu (bildirim cihazı kaydı, bağış, hatırlatma). Düzeltildi.

## 5. Teslim edilen dosyalar

| Dosya | Ne için |
|---|---|
| `optiflow-4.12.0-hosting.zip` | Sunucu güncellemesi. Hosting ana klasörüne yüklenip mevcut dosyaların üzerine çıkarılır. `config.php` ve `storage/` korunur. |
| `OptiFlow-Pro-Setup-5.2.0.exe.parca1…4` + `KURULUMU-BIRLESTIR.bat` | Masaüstü kurulumu. Dosya boyutu sınırı yüzünden 4 parça halinde; .bat parçaları birleştirip SHA256 ile doğrular. |
| `latest.yml` (5.2.0) | Otomatik güncelleme bildirimi. Exe yüklendikten **sonra** hosting'e yüklenir. |
| `YENILIKLER-4.12.md` | Modüllerin kurulum rehberi, Meta şablonlarındaki değişken sırası, PayTR ve e-Fatura ayarları. |

## 6. Sizin yapmanız gerekenler

**Kurulum:**
- [ ] `optiflow-4.12.0-hosting.zip` dosyasını hosting'e yükleyip üzerine çıkarın.
- [ ] Siteye bir kez personel olarak giriş yapın; veritabanı şema 22'ye kendiliğinden güncellenir.
- [ ] Merkez panelden istediğiniz modülleri mağaza bazında açın.
- [ ] Merkez paneldeki **Zamanlanmış görev** adresini Plesk'e 10 dakikada bir çalışacak şekilde ekleyin.
- [ ] Exe'yi birleştirip `indir/masaustu/` klasörüne yükleyin, ardından `latest.yml` dosyasını yükleyin.
- [ ] `storage/` klasörünü yedeklere ekleyin. İçindeki `.gizli-anahtar` dosyası PayTR ve WhatsApp anahtarlarını çözer.

**Hesap ve bilgi gerektirenler:**
- [ ] Poyraz Optik'in paketi merkez panelde **Pro** olmalı.
- [ ] Mağaza veritabanı şifreleri merkez panelde güncel olmalı (hosting yeniden kurulurken değişmişti).
- [ ] **PayTR:** Mağaza bilgilerini girin; ilk denemeyi **test modunda** yapın.
- [ ] **WhatsApp otomatik gönderim:** Meta Business hesabı, doğrulanmış numara ve onaylı şablonlar gerekir. Hesap olmadan tek tık modu kullanılabilir.
- [ ] **e-Fatura:** Satıcı bilgilerini girin. KDV oranlarını ve SGK fatura alıcısının bilgilerini mali müşavirle doğrulayın.
- [ ] **Hukuk:** WhatsApp tanıtım mesajları ve İYS yükümlülüğü için hukuk danışmanına danışın.

## 7. Canlıda henüz denenmemiş noktalar

| Konu | Neden | Öneri |
|---|---|---|
| PayTR link oluşturma | Gerçek PayTR hesabı olmadan çağrılamadı (bildirim tarafı test edildi) | İlk linki test modunda oluşturup ödeyin. |
| WhatsApp otomatik gönderim | Meta hesabı yok | Ayarlar'daki **Test mesajı** ile deneyin. |
| Medula "Listeyi kontrol et" | Gerçek Medula liste ekranının yapısı görülmedi | İlk kullanımda sonucu gözle kontrol edin. |
| UBL-TR belgesi | GİB şematron doğrulaması yapılmadı | Entegratör bağlanırken entegratörün test servisinde doğrulanacak. |
| Kod imzalama | Sertifika yok | Sertifika gelince tek komutla derlenecek. |

## 8. Bilinen riskler ve kararlar

- **Güncelleme imza doğrulaması şu an kapalı.** Bu sizin kararınız: sertifika gelene kadar böyle kalacak. Bu süre boyunca hosting'deki `indir/masaustu` klasörüne yazabilen biri uygulamaya sürüm gönderebilir. Hosting şifresini koruyun.
- **Kurulum dosyaları imzasız.** Windows SmartScreen uyarı veriyor; sertifikayla kalkacak.
- **Medula kuralları.** Medula oturumu Chrome'daki gibi önbellek sorunu yaşayabilir. Uygulama her açılışta oturum çerezlerini temizliyor, sorun çıkarsa "Medula'yı sıfırla" düğmesi var.

## 9. Sonraki adımlar (öneri sırasıyla)

1. **e-Arşiv / e-Fatura gönderimi:** Özel entegratör seçilecek ve tek bir sürücü sınıfı eklenecek. Veri modeli ve ekranlar hazır.
2. **Kod imzalama sertifikası:** OV/EV sertifika ya da Azure Trusted Signing. Sertifika gelince hat hazır.
3. **GitHub'a taşıma:** Kod yerel bir git deposunda iki commit halinde duruyor, `optiflow.bundle` da hazır. Claude GitHub uygulaması depoya kurulunca gönderilecek.
4. **Canlı doğrulamalar:** Bölüm 7'deki beş nokta.
5. **İsteğe bağlı:** merkez panelde mağaza sağlığı ekranı (son giriş, yedek, uygulama sürümü), Pro deneme süresi.

## 10. Teknik notlar

- **Kod:** `/home/claude/src`. Sunucu PHP 8, masaüstü `desktop/` klasöründe (Electron 44 + TypeScript).
- **Belgeler:** `desktop/SECURITY.md` (güven sınırları, sırlar, ödeme bildirimi), `desktop/RELEASE.md` (sürüm ve imzalama), `VERSION.txt` (değişiklik notları).
- **Testleri çalıştırma:** `npm test` (birim); `tests/server/api-integration.mjs` ve `tests/server/moduller-integration.mjs` (yerel test sunucusu gerekir); `tests/e2e/e2e-main.ts` (xvfb ile).
- **Güncelleme adresi:** `https://optiflow.com.tr/indir/masaustu/` — `latest.yml` her zaman en son yüklenir.

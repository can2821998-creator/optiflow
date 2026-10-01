# OptiFlow Masaüstü

OptiFlow'un Windows masaüstü sürümü. Mevcut OptiFlow sunucusunu (PHP + MySQL) güvenli bir
Electron kabuğunda açar ve **SGK Medula'yı uygulamanın içinde** çalıştırır. Medula ekranındaki
reçete tek düğmeyle OptiFlow'a aktarılır:

- Chrome eklentisi, Geliştirici modu, köprü adresi veya köprü anahtarı **gerekmez**.
- SGK kullanıcı adı ve şifresi **okunmaz, saklanmaz**. Medula'ya otomatik giriş yapılmaz.
- Reçete yalnızca kullanıcı **“Reçeteyi aktar”** dediğinde, o an ekranda görünen haliyle okunur.
- Siparişe yazma, mevcut önizleme ekranında **personel onayıyla** olur (değişmedi).

İş mantığı sunucuda kalır; masaüstü uygulaması yalnızca kabuk, Medula entegrasyonu, indirme,
yazdırma, güncelleme ve tanılama sağlar.

## Hızlı başlangıç (geliştirici)

```bash
cd desktop
npm install
npm test                      # birim + güvenlik + eski eklentiyle eşlik + PHP çözümleyici testleri
npm start                     # config/development.json → http://localhost:8080
```

Yerel OptiFlow sunucusu için: `php -S 127.0.0.1:8080 -t ..` (üst klasörde `config.php` gerekir).
Farklı bir sunucu: `OPTIFLOW_BASE_URL=https://test.optiflow.com.tr npm start` (yalnızca geliştirme derlemesinde).

## Belgeler

| Dosya | İçerik |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Mimari, süreçler, klasör yapısı, veri akışı |
| [MEDULA-BRIDGE.md](MEDULA-BRIDGE.md) | Eski eklenti akışı ↔ yeni akış, okuma algoritması, API, hatalar, sorun giderme |
| [SECURITY.md](SECURITY.md) | Güven sınırları, IPC, gezinme kuralları, gizlilik, güvenlik kontrol listesi |
| [BUILD.md](BUILD.md) | Derleme, test, ortamlar, paketleme |
| [RELEASE.md](RELEASE.md) | İmzalı sürüm, otomatik güncelleme, dağıtım, geri alma |
| [../MASAUSTU-GECIS-RAPORU.md](../MASAUSTU-GECIS-RAPORU.md) | Geçiş raporu: değişenler, riskler, elle test listesi |

## Klavye kısayolları

| Kısayol | İşlem |
|---|---|
| Ctrl+1 | OptiFlow ekranı |
| Ctrl+2 | Medula ekranı |
| Ctrl+3 | Yan yana / sekmeli görünüm |
| F5 | OptiFlow sayfasını yenile |

## Kayıtlar ve veri konumları (Windows)

| Ne | Nerede |
|---|---|
| Uygulama | `%LOCALAPPDATA%\Programs\OptiFlow` (kullanıcı başına kurulum) |
| Oturumlar (OptiFlow / Medula çerezleri) | `%APPDATA%\OptiFlow\Partitions\optiflow` ve `…\medula` |
| Masaüstü kayıtları (14 gün) | `%APPDATA%\OptiFlow\logs` — hasta bilgisi, şifre, çerez yazılmaz |

Menü › Yardım › **Tanılama kayıtlarını dışa aktar…** destek için maskelenmiş tek bir metin dosyası üretir.

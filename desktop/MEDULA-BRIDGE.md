# Medula köprüsü

## 1. Eski akış (Chrome eklentisi, `kopru-eklenti/`)

```
Medula sayfası ──(içerik betiği icerik.js, all_frames)──► "Atölyeye aktar" düğmesi
      │ tıklama
      ▼
chrome.runtime.sendMessage({tip:'aktar', metin, baslik})
      ▼
arkaplan.js ── chrome.storage.sync'ten {adres, anahtar} ──► POST sgk-aktar.php?action=kopru
                                                              {token, metin, baslik}
```

Kurulum: klasörü kopyala → `chrome://extensions` → Geliştirici modu → paketlenmemiş yükle →
köprü adresi ve 40 haneli anahtarı elle gir.

**4.9.0'da bu akış çok mağazalı yapıda çalışmıyordu:** eklenti oturum çerezi taşımadığı için
`bootstrap.php` isteği `magaza-giris.php`'ye yönlendiriyordu (303). Test sunucusunda
yeniden üretildi. 4.10.0'da köprü adresi `&m=<mağaza no>` taşır ve çalışır (bkz. §7).

## 2. Yeni akış (OptiFlow Masaüstü)

```
Araç çubuğu "Reçeteyi aktar"  ─┐
OptiFlow SGK sayfası düğmesi  ─┴► ana süreç
        │ 1) Medula üst adresi izinli mi?
        │ 2) izinli çerçevelere 'probe' → sinyaller
        │ 3) en iyi reçete çerçevesi seçilir
        │ 4) o çerçeveye 'extract' → metin
        │ 5) GET  masaustu.php?action=durum   (çerezli)
        │ 6) POST masaustu.php?action=aktar   (çerez + X-CSRF-Token)
        ▼
sgk_incoming (kaynak='masaustu') ──► sgk-aktar.php?gelen=ID ──► personel onayı
```

Eklenti, `chrome.*` API'leri, eklenti ayar ekranı, köprü adresi ve köprü anahtarı masaüstünde yoktur.

## 3. Okuma algoritması (`src/medula/extractor.ts`)

`icerik.js`'in birebir TypeScript karşılığıdır. `tests/legacy-parity.test.ts`, **orijinal**
`icerik.js`'i jsdom'da çalıştırıp aynı test sayfalarında çıktıyı karşılaştırır — fark yok.

- `TreeWalker`, belge sırasıyla metin ve öğeleri dolaşır.
- `<input>/<select>/<textarea>` değeri `«değer»` olarak yazılır; boş kutu `«»` (sütun hizası için).
- `<select>`: seçili seçeneğin metni; “Seçiniz” boş sayılır.
- Onay kutusu/radyo: işaretliyse değeri ya da `evet`.
- Hücre (`td/th`) → sekme; `tr/br/div/p/table/li` → satır sonu.
- **Atlananlar:** `hidden`, `password`, `button/submit/reset/image/file` alanları; görünmeyen alanlar
  (`display:none`, `visibility:hidden`); `script/style/noscript/option/optgroup/datalist`.
- **Şifre alanlarının değeri hiç okunmaz** (birim testi, `value` okunduğunda hata fırlatan bir
  alanla bunu doğrular).
- Kullanıcı 80 karakterden uzun bir seçim yapmışsa ve metinde yoksa başa eklenir (eski davranış).
- Sayfaya düğme veya başka bir şey **eklenmez** (eklentiden farklı olarak).

Örnek çıktı:

```
SAĞ CAM
Cam	+/-	Sferik	+/-	Silendirik	Aks
«1»	«-»	«1,50»	«-»	«0,75»	«90»
```

## 4. Reçete ekranı tespiti (`src/medula/detector.ts`)

Yalnızca sinyal döner, içerik döndürmez. Metin düğümleri boşlukla birleştirilir (bitişik tablo
hücreleri yapışmasın diye), Türkçe harfler `sgk_norm()` gibi katlanır ve şu etiketler aranır:

| Sinyal | Ağırlık |
|---|---|
| `SAĞ CAM / SOL CAM / SAĞ GÖZ / SOL GÖZ` | 30 |
| `SFERİK` | 20 |
| `SİLENDİRİK / CYL`, `AKS / EKSEN / AXIS`, `E-REÇETE NO`, `REÇETE TARİHİ`, `T.C. KİMLİK NO` | 10'ar |

**Reçete ekranı** = göz etiketi + sferik + puan ≥ 60 + görünür şifre alanı yok.
Görünür şifre alanı varsa **giriş ekranı** sayılır (“Medula oturumu sona ermiş”).
Liste, menü ve giriş sayfaları testlerde reçete sayılmaz.

## 5. Çerçeveler

Eklenti `all_frames: true` ile her çerçevede çalışıyordu ve kısa metinli alt çerçevelerde
düğmeyi göstermiyordu. Masaüstünde:

- Medula preload'u her çerçevede yalıtılmış dünyada yüklenir (`nodeIntegrationInSubFrames`,
  sandbox açık → Node yok).
- Ana süreç `mainFrame.framesInSubtree` içinden **URL'si ve kökeni** izinli okuma sunucusunda
  (`medulaExtractHosts`) olan çerçeveleri seçer, hepsini yoklar, en yüksek puanlıyı okur.
- E2E testinde reçete **alt çerçevedeyken** doğru okunduğu doğrulandı.

## 6. İzinli SGK adresleri

`config/<ortam>.json`:

```json
"medulaHomeUrl": "https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces",
"medulaAllowedHosts": ["gss.sgk.gov.tr"],
"medulaExtractHosts": ["gss.sgk.gov.tr"]
```

- Yalnızca `https`, varsayılan port, **birebir sunucu adı** (son ek eşleşmesi yok:
  `gss.sgk.gov.tr.evil.com` reddedilir).
- Medula görünümü izinli sunucular dışına gidemez; kullanıcıya uyarı gösterilir.
- Medula başka bir SGK sunucusuna yönlendiriyor veya onu çerçeve olarak gömüyorsa, o sunucu
  `medulaAllowedHosts`'a (gezinme), reçete oradaysa `medulaExtractHosts`'a eklenmelidir.
  **Canlıda doğrulanmalı** (bkz. geçiş raporu, riskler).

## 7. Sunucu uç noktaları

### Masaüstü: `masaustu.php`

Kimlik: OptiFlow **oturum çerezi** (Chromium ekler; uygulama okumaz) + mağaza oturumu.
Köprü anahtarı kullanılmaz. CORS başlığı yoktur.

`GET masaustu.php?action=durum`

```json
{ "ok": true, "api": 1, "surum": "4.10.0",
  "kullanici": { "id": 3, "ad": "Ali Personel" },
  "magaza": { "id": 12, "isim": "Deneme Optik" },
  "destek_modu": false, "csrf": "<64 hex>" }
```

`POST masaustu.php?action=aktar` — başlıklar: `Content-Type: application/json`, `X-CSRF-Token`

```json
{ "metin": "…«1,50»…", "baslik": "Medula Optik",
  "beklenen": { "magaza_id": 12, "kullanici_id": 3 },
  "istemci": { "surum": "5.0.0" } }
```

Başarılı yanıt:

```json
{ "ok": true, "gelen_id": 41, "bulunan": 15, "ozet": "AYŞE YILMAZ · Sağ -1.50 / Sol +2.00",
  "hedef": "sgk-aktar.php?gelen=41" }
```

| HTTP | `kod` | Anlamı | Masaüstünün gösterdiği |
|---|---|---|---|
| 401 | `magaza_oturumu_yok` / `oturum_yok` | Oturum düşmüş | “Oturum süreniz dolmuş…” + giriş ekranı |
| 403 | `destek_modu` | Merkez destek oturumu | “Destek (merkez) oturumunda Medula aktarımı kapalıdır…” |
| 403 | `pro_gerekli` | Mağaza Lite paketinde (4.11.0) | “Medula aktarımı OptiFlow Pro paketindedir…”; Medula hiç okunmaz |
| 405 | `yontem` | Yanlış yöntem | genel hata |
| 409 | `hesap_degisti` | Oturumdaki mağaza/kullanıcı değişmiş | bir kez otomatik yeniden dener |
| 415 | `icerik_turu` | JSON değil | genel hata |
| 419 | `csrf` | CSRF geçersiz | bir kez otomatik yeniden dener |
| 422 | `metin` | Boş/çok uzun/geçersiz UTF-8 | genel hata |
| 429 | `cok_fazla` | Saatte 120'den fazla | “Çok fazla aktarım…” |

`ozet` yalnızca araç çubuğunda gösterilir; OptiFlow sayfasına preload API'si üzerinden **gitmez**.

### Eklenti (geçiş dönemi): `sgk-aktar.php?action=kopru&m=<mağaza no>`

Değişmeyen: 40 haneli anahtar, POST/JSON, 200 000 bayt sınırı, saatlik 120 sınırı, CORS `*`.
Değişen: `&m=` ile mağaza veritabanı oturumsuz seçilir; anahtar o mağazanın `user_accounts`
tablosunda aranır. `&m=` yoksa açıklayıcı 401; bilinmeyen/kapalı mağaza ile yanlış anahtar
**aynı** yanıtı alır (mağaza numarası yoklanamaz).

## 8. Yeniden deneme

- Okunan metin **yalnızca bellekte** tek bir yerde tutulur. Diske yazılmaz, günlüğe yazılmaz,
  şifrelenmesi gerekmez.
- Silinme: başarılı aktarım, yeni okuma, 15 dakika dolması, Medula oturumunu temizleme, uygulamadan çıkış.
- **Tekrar dene**, Medula'yı yeniden okumadan aynı metni gönderir (ör. OptiFlow'a yeniden
  giriş yaptıktan sonra).

## 9. Hata mesajları

| Durum | Mesaj |
|---|---|
| Medula açık değil | Önce Medula'yı açın ve reçete detayına gelin. |
| Medula dışı sayfa | Bu sayfa Medula dışında olduğu için okunmaz. |
| Giriş ekranı | Medula oturumu sona ermiş. Medula'ya yeniden giriş yapın. |
| Reçete ekranı değil | Reçete ekranı bulunamadı. Lütfen Medula'da reçete detayını açın. |
| Okuma başarısız | Reçete okunamadı. Sayfayı yenileyip tekrar deneyin. |
| İnternet yok / DNS / zaman aşımı / sunucu yok / sertifika | Türkçe ağ mesajları (`src/shared/messages.ts`) |

## 10. Güvenlik sınırları (özet)

- Medula sayfası Node'a, OptiFlow API'sine, araç çubuğu API'sine erişemez (E2E ile doğrulandı).
- Medula preload'u sayfaya hiçbir şey açmaz; yalnızca ana sürecin kendi ürettiği kimlikli isteklere yanıt verir.
- Ana süreç, yanıtı yalnızca **istek gönderdiği çerçeveden** (çerçeve belirteci + süreç no + köken) kabul eder.
- Medula çerezleri ayrı bölümde durur; hiçbir renderer'a ve OptiFlow'a verilmez.
- SGK'ya uygulama tarafından **hiç istek atılmaz**; tüm SGK trafiği kullanıcının kendi gezinmesidir.

Ayrıntı: [SECURITY.md](SECURITY.md).

## 11. Sorun giderme

| Belirti | Kontrol |
|---|---|
| Rozet “Reçete yok”, reçete açık | Reçete başka bir SGK sunucusundaki çerçevede olabilir → kayıtlarda `bridge.extract.probe frames=` değerine bakın; sunucuyu `medulaExtractHosts`'a ekleyin. |
| “Medula dışındaki adres açılmadı: …” | Medula başka bir sunucuya yönlendiriyor. Adres gerçekten SGK'ya aitse `medulaAllowedHosts`'a ekleyin. |
| “Oturum süreniz dolmuş” | OptiFlow'a yeniden giriş yapın, sonra **Tekrar dene**. |
| “Destek (merkez) oturumunda…” | Merkez panelden mağazaya girilmiş; mağaza personeliyle giriş yapın. |
| Değerler eksik | Menü › Yardım › Tanılama kayıtlarını dışa aktar. Kayıtta reçete metni yoktur; sorunu sentetik bir test sayfasıyla yeniden üretin (`tests/fixtures/_README.md`). |
| Eklenti “Köprü adresi eski” diyor | SGK reçete aktar sayfasındaki yeni adresi (…`&m=`) eklentiye girin. |

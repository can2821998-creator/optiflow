# OptiFlow tanıtım filmleri · Seslendirme metni

Bu dosyadaki satırları istediğiniz yapay zekâ ses servisine verip okutun. Gelen ses dosyalarını aşağıdaki **dosya adlarıyla**
gönderin; her ses kendi sahnesinin başına yerleşir. Ses uzun gelirse sahne kendiliğinden uzar, bir şey kesilmez.

## Ses tarifi (servise yazılacak)
> Türkçe, İstanbul ağzı, sıcak ve sakin bir anlatıcı sesi. Reklam bağırması yok, gülümseyerek konuşan bir esnaf tonu.
> "Siz" diye hitap eder. Tempo hafif hızlı ama net; cümle sonlarında kısa duraklar.

- **Ses:** Kadın ya da erkek olabilir; üç filmde **aynı ses** kullanılsın.
- **Okunuşlar:**
  - **OptiFlow** → "Opti-Flov"
  - **SGK** → "Se-Ge-Ka"
  - **Medula** → "Medula"; vurgu ortadaki hecede: me-**DU**-la
  - **WhatsApp** → "Vatsap"
  - **A4** → "A dört"
- **Biçim:** WAV ya da MP3, 44,1 ya da 48 kHz. Başta ve sonda en fazla yarım saniye sessizlik olsun. Müzik eklenmesin.
- **Süre:** Parantezdeki süre o sahnenin ideal uzunluğu. Biraz aşması sorun değil.
- **Dosya adı:** `film-sahne` biçiminde, ör. `musteri-geldi-kapi.mp3`. Hepsini tek zip'te gönderebilirsiniz.

---

## 1) Bir müşteri geldi (`musteri-geldi`)

| Dosya | Metin | İdeal |
|---|---|---|
| `musteri-geldi-kapi` | Saat on kırk iki. Kapı açıldı; Ayşe Hanım elinde reçetesiyle geldi. | ~3 sn |
| `musteri-geldi-eskiden` | Eskiden Medula, defter, hesap makinesi arasında koşardınız. | ~3 sn |
| `musteri-geldi-medula` | Şimdi önce Medula'dan reçeteyi sorgularsınız. | ~3 sn |
| `musteri-geldi-aktar` | "Reçeteyi aktar"a basarsınız; değerler siparişe kendiliğinden gelir. | ~4 sn |
| `musteri-geldi-teklif` | SGK payı düşülmüş teklif, müşterinin önünde. | ~3 sn |
| `musteri-geldi-pano` | Sipariş atölyeye düşer. Cam gelince pano haber verir. | ~3,5 sn |
| `musteri-geldi-mesaj` | Gözlük hazır olunca müşteriye mesaj gider. | ~3 sn |
| `musteri-geldi-teslim` | Telefon çalmadan, defter açılmadan: teslim. | ~2,5 sn |
| `musteri-geldi-son` | OptiFlow. Otuz gün ücretsiz deneyin. | ~3 sn |

## 2) Bugün aranacaklar (`aranacaklar`)

| Dosya | Metin | İdeal |
|---|---|---|
| `aranacaklar-sabah` | Sabah dokuz. Güne hep aynı soruyla başlarsınız: Bugün kimi arayacağım? | ~3,5 sn |
| `aranacaklar-eskiden` | Defter, yapışkan notlar, hafızanız… Biri mutlaka unutulur. | ~3 sn |
| `aranacaklar-liste` | OptiFlow listeyi her sabah kendisi çıkarır: gözlüğü hazır olanlar, gelmeyenler, ödeme sözü geçenler. | ~5 sn |
| `aranacaklar-isaretle` | Ulaşıldı, açmadı ya da WhatsApp: tek dokunuş. Açmayan, yarın yine listede. | ~4,5 sn |
| `aranacaklar-bitti` | Kimse unutulmaz. | ~1,5 sn |
| `aranacaklar-gelis` | Aranan müşteri, gözlüğünü almaya gelir. | ~2,5 sn |
| `aranacaklar-son` | OptiFlow. Otuz gün ücretsiz deneyin. | ~3 sn |

## 3) Tamir fişi (`tamir`)

| Dosya | Metin | İdeal |
|---|---|---|
| `tamir-gelis` | Öğleden sonra. Mehmet Bey'in gözlüğünün vidası düşmüş. | ~3 sn |
| `tamir-kayit` | Yapılacak işlemi seçersiniz; teslim alırken gördüğünüz çiziği de kayda geçirirsiniz. | ~4,5 sn |
| `tamir-secenek` | Bırakılacaksa hazır olacağı gün yazılır. Ücretsizse, tek dokunuş. | ~3,5 sn |
| `tamir-fis` | Fiş tek A4'e basılır, ortadan kesilir: üst yarı müşteriye, alt yarı gözlüğe. | ~4,5 sn |
| `tamir-hazir` | Tamir bitince müşteri, aranacaklar listesine kendiliğinden düşer. | ~3,5 sn |
| `tamir-teslim` | Fişin alt yarısı gözlüğün üstünde; teslim karışmaz. | ~3 sn |
| `tamir-son` | OptiFlow. Otuz gün ücretsiz deneyin. | ~3 sn |

---

## Sesler gelince
Dosyaları sohbete ekleyin ya da bir zip'te gönderin. Sesler `--ses` klasörüne `<sahne>.wav` adıyla konur
(MP3 gelirse WAV'a çevrilir), film yeniden çekilir:
```bash
node docs/tanitim-video/cek.mjs docs/tanitim-video/musteri-geldi/film.html cikti/musteri-geldi.mp4 --ses ses/musteri-geldi --tempo 1
```
`--tempo 1`: İnsan sesine ya da iyi bir yapay sese hız eklenmez, olduğu gibi kullanılır. Varsayılan 1,06'dır.

# OptiFlow — güncel durum

*Son güncelleme: 1 Ekim 2026*

## Sürüm
- **Sunucu 4.15.0**, şema **25**. Masaüstü **OptiFlow Pro 5.2.0** (değişmedi).
- Özellik anahtarları (hepsi varsayılan kapalı): `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`.
- Testler: 429/429 geçiyor (uts 168+34, alis 104+27, hata-hak 70+26).

## Son oturumda yapılanlar
- 4.13 ÜTS bildirimleri, 4.14 alış faturası + senet, 4.15 hatalı cam + SGK hak (ayrıntı: `git log`, `VERSION.txt`).
- Devir paketi incelendi; depo bu oturumda kuruldu (geçmiş 4.12.0 hosting paketinden başlar).

## Sıradaki iş
1. **Masaüstü kaynağını ekle:** masaüstü kaynağının bulunduğu bilgisayarda/oturumda `desktop/` klasörünü
   (ve varsa `tests/server`, `tests/e2e`) bu depoya kopyala, commit + push. Eski kaynak deponun geçmişi
   orada kalır; bundan sonra yalnızca bu depo kullanılır.
2. **T.C. no maskeleme (KVKK):** `app/sgk-hak.php` › `sgk_hak_coz` okunan satırları ham saklıyor;
   `sgk_hak_sorgulari.satirlar`'a 11 haneli numara girebiliyor. Kaydetmeden önce maskele + test ekle → 4.15.1.
3. Sonra (onaylı): **Garanti kaydı ve garanti kartı** (karekodlu belge, karekodla sorgu, tamir geçmişi, tedarikçiye talep).

## Açık sorunlar / doğrulanmamış
- Gerçek MySQL/MariaDB'de hiç çalıştırılmadı (göçler v23–v25, utf8mb4_bin, UNIQUE ETTN, BLOB, strict mod).
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- `app/pages/supplier.php` satır içi `<script>` (4.12'den) CSP'ye takılıyor.
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` önceki oturumda kaldı;
  bulunursa `docs/` altına eklenmeli. `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.
- Masaüstü (isteğe bağlı): "Hak sorgula" düğmesi + `action=hak`; ÜTS karekodunda GS ayırıcısını koruma.

## Karar bekleyen
- SGK dönem sonu paketi + kesinti defteri.

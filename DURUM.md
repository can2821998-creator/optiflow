# OptiFlow — güncel durum

*Son güncelleme: 1 Ekim 2026 (masaüstü kaynağı eklendi)*

## Sürüm
- **Sunucu 4.15.0**, şema **25**. Masaüstü **OptiFlow Pro 5.2.0** (değişmedi).
- Özellik anahtarları (hepsi varsayılan kapalı): `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`.
- Testler: 429/429 geçiyor (uts 168+34, alis 104+27, hata-hak 70+26); masaüstü birim 88/88.

## Son oturumda yapılanlar (masaüstü oturumu)
- `desktop/` (OptiFlow Pro 5.2.0 kaynağı, birim + sunucu + E2E testleri) depoya eklendi.
- `.github/workflows/optiflow-pro-surum.yml` (imzalı Windows derlemesi) eklendi.
- `docs/`: YENILIKLER-4.12.md, MASAUSTU-GECIS-RAPORU.md, 30.09 durum raporu geri getirildi.

## Önceki oturumda yapılanlar
- 4.13 ÜTS bildirimleri, 4.14 alış faturası + senet, 4.15 hatalı cam + SGK hak (ayrıntı: `git log`, `VERSION.txt`).
- Devir paketi incelendi; depo bu oturumda kuruldu (geçmiş 4.12.0 hosting paketinden başlar).

## Sıradaki iş
1. **T.C. no maskeleme (KVKK):** `app/sgk-hak.php` › `sgk_hak_coz` okunan satırları ham saklıyor;
   `sgk_hak_sorgulari.satirlar`'a 11 haneli numara girebiliyor. Kaydetmeden önce maskele + test ekle → 4.15.1.
2. Sonra (onaylı): **Garanti kaydı ve garanti kartı** (karekodlu belge, karekodla sorgu, tamir geçmişi, tedarikçiye talep).

## Açık sorunlar / doğrulanmamış
- Gerçek MySQL/MariaDB'de hiç çalıştırılmadı (göçler v23–v25, utf8mb4_bin, UNIQUE ETTN, BLOB, strict mod).
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- `app/pages/supplier.php` satır içi `<script>` (4.12'den) CSP'ye takılıyor.
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` hâlâ yok
  (4.12 belgeleri `docs/`'a eklendi). `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.
- Masaüstü sunucu testleri (`desktop/tests/server`) 4.12.0'a göre yazıldı; 4.15 sunucusunda yeniden
  çalıştırılmadı (MySQL test sunucusu ister).
- Depo GitHub'da **herkese açık** (public); özel yapılması önerilir.
- Masaüstü (isteğe bağlı): "Hak sorgula" düğmesi + `action=hak`; ÜTS karekodunda GS ayırıcısını koruma.

## Karar bekleyen
- SGK dönem sonu paketi + kesinti defteri.

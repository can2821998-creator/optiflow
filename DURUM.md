# OptiFlow — güncel durum

*Son güncelleme: 1 Ekim 2026 (4.15.1 / Pro 5.3.0)*

## Sürüm
- **Sunucu 4.15.1**, şema **25**. Masaüstü **OptiFlow Pro 5.3.0**.
- Özellik anahtarları (hepsi varsayılan kapalı): `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`.
- Testler: sunucu 429/429 (uts 168+34, alis 104+27, hata-hak 70+26); masaüstü birim 91/91;
  masaüstü sunucu entegrasyon 46/46 + 76/76 ve uçtan uca 61/61 (yerel MariaDB + 4.15.1 sunucusu).

## Son oturumda yapılanlar (4.15.1 / 5.3.0)
- KRİTİK: Faturalar sayfaları 4.14'ten beri çöküyordu (`tutar_yaziyla` iki kez tanımlı) → düzeltildi.
- CSP'nin engellediği satır içi betikler (yeni sipariş, teslimat/tedarikçi toplamı, durum sayfası) taşındı.
- ÜTS karekodunda GS ayırıcısı korunuyor (barkod.js + barkod.php). Pro 5.3.0: "Hak sorgula" düğmesi.
- Göçler v23–v25 MariaDB'de denendi: sorunsuz.

## Önceki: masaüstü kaynağının eklenmesi
- `desktop/` (OptiFlow Pro 5.2.0 kaynağı, birim + sunucu + E2E testleri) depoya eklendi.
- `.github/workflows/optiflow-pro-surum.yml` (imzalı Windows derlemesi) eklendi.
- `docs/`: YENILIKLER-4.12.md, MASAUSTU-GECIS-RAPORU.md, 30.09 durum raporu geri getirildi.

## Önceki oturumda yapılanlar
- 4.13 ÜTS bildirimleri, 4.14 alış faturası + senet, 4.15 hatalı cam + SGK hak (ayrıntı: `git log`, `VERSION.txt`).
- Devir paketi incelendi; depo bu oturumda kuruldu (geçmiş 4.12.0 hosting paketinden başlar).

## Sıradaki iş
1. **T.C. no maskeleme (KVKK) — kullanıcı "bir süre ertele" dedi (01.10):** `app/sgk-hak.php` › `sgk_hak_coz` okunan satırları ham saklıyor;
   `sgk_hak_sorgulari.satirlar`'a 11 haneli numara girebiliyor. Kaydetmeden önce maskele + test ekle → 4.15.2.
2. Sonra (onaylı): **Garanti kaydı ve garanti kartı** (karekodlu belge, karekodla sorgu, tamir geçmişi, tedarikçiye talep).

## Açık sorunlar / doğrulanmamış
- Göçler v23–v25 yerel MariaDB'de çalıştı; canlı hosting MySQL'inde henüz değil.
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` hâlâ yok
  (4.12 belgeleri `docs/`'a eklendi). `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.
- Masaüstü sunucu testleri (`desktop/tests/server`) 4.12.0'a göre yazıldı; 4.15 sunucusunda yeniden
  çalıştırılmadı (MySQL test sunucusu ister).
- Depo GitHub'da **herkese açık** (public); özel yapılması önerilir.

## Karar bekleyen
- SGK dönem sonu paketi + kesinti defteri.

# OptiFlow — güncel durum

*Son güncelleme: 1 Ekim 2026 (4.16.0 / Pro 5.3.0)*

## Sürüm
- **Sunucu 4.16.0**, şema **26**. Masaüstü **OptiFlow Pro 5.3.0** (değişmedi).
- Özellik anahtarları (hepsi varsayılan kapalı): `garanti` (yeni), `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`.
- Testler: sunucu 548/548 (uts 168+34, alis 104+27, hata-hak 70+26, garanti 77+42);
  sunucu entegrasyon (yerel MariaDB): api 46/46, modüller 76/76, garanti 37/37. Masaüstü birim/E2E bu oturumda
  değişmedi (91/91, 61/61 — 5.3.0).

## Son oturumda yapılanlar (4.16.0)
- Garanti kaydı ve garanti kartı (`app/garanti.php`, `garantiler.php`, `garanti.php`, sipariş kartı, yazdırma):
  teslimde otomatik açılış, karekodlu kart, müşteri sayfası, talepler (tamir geçmişi), tedarikçi formu + WhatsApp.
- DÜZELTME: müşteri karekod sayfaları (durum, bakım, siparişim-nerede) çok mağazalı yapıda müşteriyi mağaza
  girişine atıyordu → adreslerde `m=` ile mağaza seçiliyor (bootstrap `$musteriSayfasi`, `musteri_url()`).
- Yeni test: `tests/garanti/` + `desktop/tests/server/garanti-integration.mjs`; CI'ya eklendi.
- Rehber: `docs/YENILIKLER-4.16.md`.
- Sürüm yayını: main'e sürüm değişikliği push'lanınca Actions `v4.16.0-pro5.3.0` Release'ini üretir.

## Önceki: masaüstü kaynağının eklenmesi
- `desktop/` (OptiFlow Pro 5.2.0 kaynağı, birim + sunucu + E2E testleri) depoya eklendi.
- `.github/workflows/optiflow-pro-surum.yml` (imzalı Windows derlemesi) eklendi.
- `docs/`: YENILIKLER-4.12.md, MASAUSTU-GECIS-RAPORU.md, 30.09 durum raporu geri getirildi.

## Önceki oturumda yapılanlar
- 4.13 ÜTS bildirimleri, 4.14 alış faturası + senet, 4.15 hatalı cam + SGK hak (ayrıntı: `git log`, `VERSION.txt`).
- Devir paketi incelendi; depo bu oturumda kuruldu (geçmiş 4.12.0 hosting paketinden başlar).

## Sıradaki iş
1. **T.C. no maskeleme (KVKK) — kullanıcı "bir süre ertele" dedi (01.10):** `app/sgk-hak.php` › `sgk_hak_coz` okunan satırları ham saklıyor;
   `sgk_hak_sorgulari.satirlar`'a 11 haneli numara girebiliyor. Kaydetmeden önce maskele + test ekle.
2. Kullanıcıya sor: sıradaki modül (karar bekleyen: SGK dönem sonu paketi + kesinti defteri).

## Açık sorunlar / doğrulanmamış
- Göçler v23–v26 yerel MariaDB'de çalıştı; canlı hosting MySQL'inde henüz değil.
- 4.16.0 öncesi basılmış fişlerdeki takip karekodlarında `m=` yok: müşteri telefonunda hâlâ mağaza girişine düşer
  (yeniden yazdırılan fiş düzelir).
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` hâlâ yok
  (4.12 belgeleri `docs/`'a eklendi). `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.

## Karar bekleyen
- SGK dönem sonu paketi + kesinti defteri.

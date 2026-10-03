# OptiFlow — güncel durum

*Son güncelleme: 3 Ekim 2026 (4.16.1 / Pro 5.3.0)*

## Sürüm
- **Sunucu 4.16.1**, şema **27**. Masaüstü **OptiFlow Pro 5.3.0** (değişmedi).
- **Canlı site (03.10 kontrolü): 4.12.0, şema 22, latest.yml 5.1.0** — 4.13–4.16 ve Pro 5.2/5.3 henüz YÜKLENMEDİ.
  Kullanıcı girişin çalıştığını doğruladı (kontrol.php'deki poyraz2 bağlantı hatası girişleri etkilemiyor; ayrıca bakılmalı).
- Özellik anahtarları (hepsi varsayılan kapalı): `garanti`, `uts_bildirim`, `tedarik_finans`, `cam_hata`, `sgk_hak`, `efatura` …
- Testler: sunucu 604/604 (uts 168+34, alis 104+27, hata-hak 70+26, garanti 77+42, sgk-fatura 37+19);
  sunucu entegrasyon (yerel MariaDB): api 46/46, modüller 76/76, garanti 37/37, sgk-fatura 15/15.
  Masaüstü birim/E2E değişmedi (91/91, 61/61).

## Son oturumda yapılanlar (4.16.1)
- DÜZELTME (kullanıcı bildirdi): SGK payı sipariş başına ayrı faturaya konuyordu. Artık sipariş faturası yalnızca
  hasta payı; SGK'ya ay sonunda TEK fatura + reçete dökümü (`sgk-fatura.php`, `fatura_sgk_donem_taslagi`, print `sgk_dokum`).
  Aynı reçete iki kez faturalanmaz; eski sipariş bazlı SGK taslakları iptal edilir.
- 4.16.0 (önceki oturum): garanti kaydı ve garanti kartı; müşteri karekod sayfaları oturumsuz (`m=`).
- Yeni testler: `tests/sgk-fatura/`, `desktop/tests/server/sgk-fatura-integration.mjs`; CI'ya eklendi.

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
- Göçler v23–v27 yerel MariaDB'de çalıştı; canlı hosting MySQL'inde henüz değil (canlı şema 22).
- SGK dönem faturasında dönem = teslim tarihi (delivered_at). Medula'nın fatura dönemi farklı kurala göre
  çalışıyorsa (ör. Medula'da işlem tarihi) kullanıcıyla doğrula.
- 4.16.0 öncesi basılmış fişlerdeki takip karekodlarında `m=` yok: müşteri telefonunda hâlâ mağaza girişine düşer
  (yeniden yazdırılan fiş düzelir).
- ÜTS uç noktaları ve yanıt alanları (SNC, MSJ, BID) ÜTS test ortamında doğrulanmadı.
- Gerçek Medula hak ekranı metniyle `sgk_hak_metni_mi` / `sgk_hak_coz` denenmedi.
- Gerçek entegratör e-Fatura XML'iyle denenmedi (örnek: `tests/alis/ornek-efatura.xml`).
- Kayıp dosyalar: `YENILIKLER-4.13/4.14/4.15.md` ve `OPTIFLOW-YOL-HARITASI.md` hâlâ yok
  (4.12 belgeleri `docs/`'a eklendi). `optiflow-4.15.0-hosting.zip` depodan yeniden üretilebilir.

## Karar bekleyen
- SGK dönem sonu paketi + kesinti defteri.

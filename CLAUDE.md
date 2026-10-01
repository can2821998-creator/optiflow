# OptiFlow — çalışma kuralları

Bu depo OptiFlow'un **tek doğru kaynağıdır**. Kullanıcı birden fazla oturum ve bilgisayarda çalışır;
devamlılık yalnızca bu depo üzerinden sağlanır (zip / mbox / bundle taşınmaz).

## Her oturumun başında
1. `git pull` (bulut oturumunda sığ klondaysa `git fetch origin main && git rebase origin/main`).
2. **`DURUM.md`'yi oku** ve "Sıradaki iş"ten devam et. Kullanıcı başka bir şey isterse onu yap.

## Her oturumun sonunda (kullanıcı söylemese de)
1. Testleri çalıştır (aşağıda); kırık bırakma. Kırıksa DURUM.md'ye yaz.
2. **`DURUM.md`'yi güncelle**: son sürüm, bu oturumda yapılanlar (2–5 madde), yarım kalan iş, sıradaki iş, açık sorunlar.
   Kısa tut; tarihçe `git log`'da ve `VERSION.txt`'te durur.
3. Commit + `git push`. Push edilmemiş iş, başka bilgisayarda yoktur.

## Aynı anda iki oturum
Her oturum kendi dalında çalışır (`oturum/<kısa-ad>`), bitince `main`'e birleştirilir.
Tek oturum varsa doğrudan `main`.

## Yapı
- Depo kökü = sunucu (PHP, hosting'e yüklenen kod): `app/`, `assets/`, kökteki `*.php`.
- `desktop/` = OptiFlow Pro (Electron) masaüstü kaynağı. **Henüz eklenmedi** (bkz. DURUM.md).
- `tests/` = testler (hosting paketine girmez).
- Göçler: `app/migrations.php` (`SCHEMA_VERSION`); sürüm: `app/bootstrap.php` `APP_VERSION` + `VERSION.txt`.
- Özellik anahtarları: `app/ozellik.php` (yeni özellikler varsayılan kapalı, merkez panelden açılır).

## Testler (PHP 8.1+, pdo_sqlite, zip)
```bash
php tests/uts/uts-test.php && bash tests/uts/sayfa-test.sh
php tests/alis/alis-test.php && bash tests/alis/sayfa-test.sh
php tests/hata-hak/test.php && bash tests/hata-hak/sayfa-test.sh
```
`tests/uts/ortam.php` ortak SQLite düzeneğidir: yeni modüllerde SQL taşınabilir yazılır
(`NOW()`/`INTERVAL` yok, zaman PHP'de). Göçler MySQL'e özeldir, testte çalışmaz.

## Kurallar
- CSP `script-src 'self'`: satır içi `<script>` / `onclick=` yazma; JS `assets/*.js` içinde.
- Gizli bilgi commit'leme: `config.php`, `storage/.gizli-anahtar`, token'lar. (`.gitignore`'da.)
- Exe / hosting zip / büyük dosya git'e girmez → GitHub Releases.
- T.C. kimlik no saklanmaz (KVKK).
- Kullanıcıyla Türkçe konuş.

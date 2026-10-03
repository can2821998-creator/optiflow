#!/usr/bin/env bash
# SGK ay sonu faturası ekranı: Medula işareti, Medula PDF dökümü karşılaştırması, tek fatura, reçete dökümü
set -u
cd "$(dirname "$0")/../uts"
export UTS_TEST_ROOT=$(mktemp -d)
mkdir -p "$UTS_TEST_ROOT/storage/logs"
export UTS_TEST_DB="$UTS_TEST_ROOT/test.sqlite"
PDF="$UTS_TEST_ROOT/dokum.pdf"; cp ../sgk-fatura/ornek-medula-dokum.pdf "$PDF"
gecen=0; kalan=0
calis() {
  local ad="$1" bek="$2"; shift 2
  local cikti; cikti=$(php sayfa-test.php "$@" 2>&1); local kod=$?
  if [ $kod -ne 0 ] || ! grep -qF -- "$bek" <<<"$cikti"; then
    echo "  ✗ $ad (çıkış $kod)"; echo "$cikti" | head -8 | sed 's/^/     /'; kalan=$((kalan+1))
  else gecen=$((gecen+1)); fi
}
q() { printf '{"sql":"%s"}' "$1"; }
calis "hazırlık" "__FLASH__" hazirla
calis "müşteri" "" sql "$(q "INSERT INTO customers (first_name, last_name, phone) VALUES ('Ayşe', 'Yılmaz', '05321234567')")"
calis "sipariş 1" "" sql "$(q "UPDATE orders SET customer_id = 1, order_stage = 'teslim_edildi', medula_islendi_at = '2026-09-05 10:00:00', sgk_amount = 150, sgk_erecete = '1A2B3C4', total_amount = 1000 WHERE id = 1")"
calis "sipariş 2" "" sql "$(q "INSERT INTO orders (customer_id, order_stage, medula_islendi_at, sgk_amount, sgk_erecete, total_amount) VALUES (1, 'hazirlandi', '2026-08-30 10:00:00', 150, '5D6E7F8', 900)")"
calis "sipariş 3 (işaretsiz)" "" sql "$(q "INSERT INTO orders (customer_id, order_stage, sgk_amount, sgk_erecete, total_amount) VALUES (1, 'teslim_edildi', 150, '9G8H7J6', 900)")"
calis "eski taslak" "" sql "$(q "INSERT INTO faturalar (order_id, uuid, alici_tip, alici_unvan, alici_kimlik, durum, genel_toplam) VALUES (1, 'u-1', 'kurum', 'SGK', '7750409379', 'taslak', 150)")"
calis "dönem ekranı" "Faturalanacak reçeteler" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "eski taslak uyarısı" "Hepsini iptal et" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "iki reçete listede" "2 reçete, 1 önceki aydan" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "işaretsiz listesi" "işlendi işaretlenmemiş SGK" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "PDF karşılaştır" "" sgk_fatura '{"post":{"ay":"2026-09","eylem":"dokum"},"dosyalar":[["dokum.pdf","'"$PDF"'"]]}'
calis "döküm sonucu: adet" "3 e-reçete numarası bulundu" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "döküm sonucu: işaretsiz reçete bulundu" "9G8H7J6" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "döküm sonucu: başka ayda işaretli" "başka ay" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "PDF olmayan dosya" "PDF değil" sgk_fatura '{"post":{"ay":"2026-09","eylem":"dokum"},"dosyalar":[["x.pdf","'"$UTS_TEST_ROOT"'/test.sqlite"]]}'
calis "boş karşılaştırma" "PDF) seçin" sgk_fatura '{"post":{"ay":"2026-09","eylem":"dokum","metin":"","adet":""}}'
calis "elle adet" "" sgk_fatura '{"post":{"ay":"2026-09","eylem":"dokum","adet":"1"}}'
calis "elle adet tutuyor" "tutuyor" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "toplu işaretle: seçim yok" "seçin" sgk_fatura '{"post":{"ay":"2026-09","eylem":"toplu_isaretle"}}'
calis "toplu işaretle" "1 reçete Medula" sgk_fatura '{"post":{"ay":"2026-09","eylem":"toplu_isaretle","bekleyen":["3"],"tarih":"2026-09-29"}}'
calis "üç reçete listede" "3 reçete, 1 önceki aydan" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "boş seçim" "reçete seçin" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur"}}'
calis "hatalı Medula toplamı" "Medula toplamı geçersiz" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur","siparis":["1","2"],"medula_toplam":"abc"}}'
calis "oluştur" "SGK faturası taslağı oluşturuldu" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur","siparis":["1","2","3"],"medula_toplam":"450,00"}}'
calis "eski taslak kendiliğinden iptal" '"durum":"iptal"' sql "$(q "SELECT durum FROM faturalar WHERE id = 1")"
calis "dönem faturası görünür" "Eylül 2026 SGK faturası" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "liste boşaldı" "Faturalanacak reçete yok" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "döküm" "REÇETE DÖKÜMÜ" sgk_dokum '{"id":2}'
calis "döküm: e-reçete" "9G8H7J6" sgk_dokum '{"id":2}'
calis "döküm: toplam" "450,00" sgk_dokum '{"id":2}'
echo "SGK ay sonu faturası sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

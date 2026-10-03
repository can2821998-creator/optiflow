#!/usr/bin/env bash
# SGK ay sonu faturası ekranı ve reçete dökümü: uyarısız render + POST akışları
set -u
cd "$(dirname "$0")/../uts"
export UTS_TEST_ROOT=$(mktemp -d)
mkdir -p "$UTS_TEST_ROOT/storage/logs"
export UTS_TEST_DB="$UTS_TEST_ROOT/test.sqlite"
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
calis "sipariş 1" "" sql "$(q "UPDATE orders SET customer_id = 1, order_stage = 'teslim_edildi', delivered_at = '2026-09-05 10:00:00', sgk_amount = 150, sgk_erecete = '1A2B3C', total_amount = 1000 WHERE id = 1")"
calis "sipariş 2" "" sql "$(q "INSERT INTO orders (customer_id, order_stage, delivered_at, sgk_amount, total_amount) VALUES (1, 'teslim_edildi', '2026-08-30 10:00:00', 150, 900)")"
calis "eski taslak" "" sql "$(q "INSERT INTO faturalar (order_id, uuid, alici_tip, alici_unvan, alici_kimlik, durum, genel_toplam) VALUES (1, 'u-1', 'kurum', 'SGK', '7750409379', 'taslak', 150)")"
calis "dönem ekranı" "Faturalanacak reçeteler" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "eski taslak uyarısı" "Hepsini iptal et" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "eski taslak listeyi engellemez" "2 reçete, 1 önceki aydan" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "eski taslakları iptal" "eski SGK taslağı iptal edildi" sgk_fatura '{"post":{"ay":"2026-09","eylem":"eski_iptal"}}'
calis "iki reçete listede" "2 reçete, 1 önceki aydan" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "önceki ay rozeti" "önceki ay" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "boş seçim" "reçete seçin" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur"}}'
calis "hatalı Medula toplamı" "Medula toplamı geçersiz" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur","siparis":["1","2"],"medula_toplam":"abc"}}'
calis "oluştur" "SGK faturası taslağı oluşturuldu" sgk_fatura '{"post":{"ay":"2026-09","eylem":"olustur","siparis":["1","2"],"medula_toplam":"300,00"}}'
calis "dönem faturası görünür" "Eylül 2026 SGK faturası" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "liste boşaldı" "Faturalanacak reçete yok" sgk_fatura '{"get":{"ay":"2026-09"}}'
calis "döküm" "REÇETE DÖKÜMÜ" sgk_dokum '{"id":2}'
calis "döküm: e-reçete" "1A2B3C" sgk_dokum '{"id":2}'
calis "döküm: toplam" "300,00" sgk_dokum '{"id":2}'
echo "SGK ay sonu faturası sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

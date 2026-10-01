#!/usr/bin/env bash
# Hatalı cam ve SGK hak ekranları: uyarısız render + POST akışları
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
calis "hazırlık" "__FLASH__" hazirla
calis "müşteri" "" sql '{"sql":"INSERT INTO customers (first_name, last_name, birth_year) VALUES ('"'"'AYŞE'"'"', '"'"'YILMAZ'"'"', 1980)"}'
calis "sipariş müşteriye" "" sql '{"sql":"UPDATE orders SET customer_id = 1 WHERE id = 1"}'
calis "reçete" "" sql '{"sql":"INSERT INTO prescription_records (order_id) VALUES (1)"}'
calis "cam" "" sql '{"sql":"INSERT INTO prescription_lens_items (prescription_id, lens_no, eye, lens_type, supplier_id) VALUES (1, 1, '"'"'R'"'"', '"'"'Progresif'"'"', 1)"}'
calis "boş rapor" "Bu dönemde hatalı cam yok" camhata
calis "sipariş kartı (boş)" "Hatalı cam kaydet" kart_cam '{"id":1}'
calis "hata ekle" "" fn '{"f":"cam_hata_ekle","a":[1,{"neden":"laboratuvar","maliyet":650,"camlar":[1],"alacak":true}]}'
calis "sipariş kartı (dolu)" "İade bekleniyor" kart_cam '{"id":1}'
calis "rapor" "Laboratuvar hatası (tedarikçi)" camhata
calis "rapor: bekleyen iade" "Bekleyen laboratuvar iadeleri" camhata
calis "rapor dönemleri" "Son 12 ay" camhata '{"get":{"donem":"365"}}'
calis "hak kartı" "SGK hakkı bilinmiyor" kart_hak '{"id":1}'
calis "hak sayfası" "Ekranı yapıştır" sgkhak
calis "boş yapıştırma" "yapıştırın" sgkhak '{"post":{"eylem":"yapistir","metin":" "}}'
calis "yapıştır" "" sgkhak '{"post":{"eylem":"yapistir","metin":"Medula Optik Cam ve Çerçeve Bilgisi Sorgulama\nAdı Soyadı: AYŞE YILMAZ\n12.03.2025\tUZAK GÖZLÜK ÇERÇEVESİ\t15.03.2025\tX OPTİK\n"}}'
calis "okunan bilgi" "15.03.2025" sgkhak '{"get":{"yapistir":"1"}}'
calis "müşteri eşleşti" "AYŞE YILMAZ" sgkhak '{"get":{"yapistir":"1"}}'
calis "kaydet (müşterisiz)" "Müşteriyi seçin" sgkhak '{"post":{"eylem":"kaydet","gelen_id":"0","customer_id":"0"}}'
calis "kaydet" "Kaydedildi" sgkhak '{"post":{"eylem":"kaydet","gelen_id":"0","customer_id":"1","order_id":"1"}}'
calis "hak kartı medula" "Medula / e-Devlet" kart_hak '{"id":1}'
calis "son sorgular" "Son sorgular" sgkhak
calis "masaüstü aktarımı" "" sql '{"sql":"INSERT INTO sgk_incoming (user_id, kaynak, raw_text, created_at) VALUES (1, '"'"'masaustu'"'"', '"'"'HASTA HAK SORGULAMA\nGözlük hakkı vardır.\nSon teslim tarihi: 01.01.2024'"'"', '"'"'2026-10-01'"'"')"}'
calis "gelen hak ekranı" "Hak var" sgkhak '{"get":{"gelen":"1"}}'
calis "başka kullanıcının aktarımı açılmaz" "bulunamadı" sgkhak '{"get":{"gelen":"99"}}'
calis "hak süreleri" "Hak süreleri kaydedildi" sgkhak '{"post":{"eylem":"ayar","ay":"24","cocuk_ay":"12","cocuk_yas":"14"}}'
echo "Hatalı cam / SGK hak sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

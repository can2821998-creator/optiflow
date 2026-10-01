#!/usr/bin/env bash
# Alış faturası ve senet ekranları: uyarısız render + POST akışları (SQLite dosya veritabanı)
set -u
cd "$(dirname "$0")/../uts"
export UTS_TEST_ROOT=$(mktemp -d)
mkdir -p "$UTS_TEST_ROOT/storage/logs"
export UTS_TEST_DB="$UTS_TEST_ROOT/test.sqlite"
ORNEK="$(cd ../alis && pwd)/ornek-efatura.xml"
gecen=0; kalan=0
calis() {
  local ad="$1" bek="$2"; shift 2
  local cikti; cikti=$(php sayfa-test.php "$@" 2>&1); local kod=$?
  if [ $kod -ne 0 ] || ! grep -qF -- "$bek" <<<"$cikti"; then
    echo "  ✗ $ad (çıkış $kod)"; echo "$cikti" | head -8 | sed 's/^/     /'; kalan=$((kalan+1))
  else gecen=$((gecen+1)); fi
}
anahtar() { php sayfa-test.php sql '{"sql":"SELECT 1"}' >/dev/null 2>&1; php -r '$s=json_decode(file_get_contents(getenv("UTS_TEST_ROOT")."/oturum.json"),true); echo array_key_last($s["alis_yukleme"] ?? []);'; }
calis "hazırlık" "__FLASH__" hazirla
calis "yükleme ekranı" "Henüz e-fatura yüklenmedi" alis
calis "dosyasız yükleme" "XML ya da ZIP" alis '{"post":{"eylem":"yukle"}}'
calis "XML yükle" "" alis "{\"post\":{\"eylem\":\"yukle\"},\"dosyalar\":[[\"fatura.xml\",\"$ORNEK\"]]}"
A=$(anahtar)
calis "önizleme" "Gözlük Toptan Optik" alis "{\"get\":{\"onizle\":\"$A\"}}"
calis "önizleme: GTIN eşleşmesi" "Barkod/GTIN ile eşleşti" alis "{\"get\":{\"onizle\":\"$A\"}}"
calis "önizleme: yeni tedarikçi önerisi" "+ Yeni tedarikçi" alis "{\"get\":{\"onizle\":\"$A\"}}"
calis "kaydet" "cariye" alis "{\"post\":{\"eylem\":\"kaydet\",\"anahtar\":\"$A\",\"belge\":\"0\",\"tedarikci\":\"yeni\",\"vade\":\"2026-11-28\",\"kalem\":{\"1\":{\"stok\":\"1\",\"cerceve\":\"1\"},\"2\":{\"stok\":\"1\",\"cerceve\":\"yeni\"},\"3\":{\"cerceve\":\"yeni\"}},\"senet_ver\":\"1\",\"senet_vade\":\"2026-12-28\",\"senet_no\":\"B-1\"}}"
calis "senet de kaydedildi" '"durum":"bekliyor"' sql '{"sql":"SELECT durum, tutar FROM tedarikci_senetleri"}'
calis "önizleme: kayıtlı" "Kayıtlı" alis "{\"get\":{\"onizle\":\"$A\"}}"
calis "ikinci kayıt engeli" "zaten kayıtlı" alis "{\"post\":{\"eylem\":\"kaydet\",\"anahtar\":\"$A\",\"belge\":\"0\",\"tedarikci\":\"2\"}}"
calis "stok girdi" '"qty":8' sql '{"sql":"SELECT qty FROM frame_items WHERE id = 1"}'
calis "tedarikçiyi pasife al" "" sql '{"sql":"UPDATE suppliers SET is_active = 0 WHERE id = 2"}'
sed -e 's/OPT2026000000123/OPT2026000000999/' -e 's/3F2504E0-4F89-41D3-9A0C-0305E82C3301/3F2504E0-4F89-41D3-9A0C-0305E82C3999/' "$ORNEK" > "$UTS_TEST_ROOT/ikinci.xml"
calis "ikinci XML yükle" "" alis "{\"post\":{\"eylem\":\"yukle\"},\"dosyalar\":[[\"ikinci.xml\",\"$UTS_TEST_ROOT/ikinci.xml\"]]}"
B=$(anahtar)
calis "pasif tedarikçi önizlemede seçili" "pasif (VKN eşleşti)" alis "{\"get\":{\"onizle\":\"$B\"}}"
calis "kayıtlı kod hafızadan" "Önceki eşlemeden" alis "{\"get\":{\"onizle\":\"$B\"}}"
calis "bitir" "" alis "{\"post\":{\"eylem\":\"bitir\",\"anahtar\":\"$A\"}}"
calis "süresi dolan önizleme" "süresi doldu" alis "{\"get\":{\"onizle\":\"$A\"}}"
calis "son yüklenenler" "OPT2026000000123" alis
calis "takvim" "Senetler · ödeme takvimi" senet
calis "takvim: senet listede" "B-1" senet
calis "senet ver (geçersiz)" "Tutar geçersiz" senet '{"post":{"eylem":"ver","supplier_id":"2","tutar":"","vade":"2026-12-01"}}'
calis "senet ver" "carisi" senet '{"post":{"eylem":"ver","supplier_id":"2","tutar":"1.250,50","vade":"2026-12-01","senet_no":"B-2"}}'
calis "senet öde" "ödendi" senet '{"post":{"eylem":"ode","senet_id":"2","yontem":"nakit"}}'
calis "senet iptal" "iptal edildi" senet '{"post":{"eylem":"iptal","senet_id":"1"}}'
calis "yazdır" "Bin İki Yüz Elli Türk Lirası Elli Kuruş" senet '{"get":{"yazdir":"2"}}'
calis "yazdır: bono ibaresi" "BONO" senet '{"get":{"yazdir":"2"}}'
echo "Alış/senet sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

#!/usr/bin/env bash
# Garanti ekranları: uyarısız render + POST akışları (personel listesi/ayrıntı, sipariş kartı, müşteri sayfası, yazdırma)
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
yok() {   # çıktıda OLMAMASI gereken metin
  local ad="$1" yasak="$2"; shift 2
  local cikti; cikti=$(php sayfa-test.php "$@" 2>&1); local kod=$?
  if [ $kod -ne 0 ] || grep -qF -- "$yasak" <<<"$cikti"; then
    echo "  ✗ $ad (çıkış $kod)"; kalan=$((kalan+1))
  else gecen=$((gecen+1)); fi
}
calis "hazırlık" "__FLASH__" hazirla
calis "müşteri" "" sql '{"sql":"INSERT INTO customers (first_name, last_name, phone) VALUES ('"'"'Ayşe'"'"', '"'"'Yılmaz'"'"', '"'"'05321234567'"'"')"}'
calis "sipariş teslim" "" sql '{"sql":"UPDATE orders SET customer_id = 1, transaction_type = '"'"'gozluk'"'"', frame_info = '"'"'Ray-Ban RB5154'"'"', lens_type = '"'"'Progresif'"'"', order_stage = '"'"'teslim_edildi'"'"', delivered_at = '"'"'2026-09-01 10:00:00'"'"' WHERE id = 1"}'
calis "boş liste" "Bu listede garanti yok" garantiler
calis "ayar kartı" "Garanti ayarları" garantiler
calis "sipariş kartı (öneri)" "Garanti aç: Çerçeve (24 ay)" kart_garanti '{"id":1}'
calis "siparişten aç" "" fn '{"f":"garanti_siparisten_olustur","a":[1]}'
calis "sipariş kartı (dolu)" "G00001" kart_garanti '{"id":1}'
calis "sipariş kartı: yazdır bağlantısı" "print.php?type=garanti&amp;order=1" kart_garanti '{"id":1}'
calis "liste" "Ray-Ban RB5154" garantiler
calis "arama" "G00001" garantiler '{"get":{"q":"ayşe","f":"tumu"}}'
calis "filtre: talep (boş)" "Bu listede garanti yok" garantiler '{"get":{"f":"talep"}}'
calis "ayrıntı" "<b>Geçerli</b>" garantiler '{"get":{"id":"1"}}'
calis "ayrıntı: karekod" "garanti.php?m=7" garantiler '{"get":{"id":"1"}}'
calis "talep boş" "Şikâyeti" garantiler '{"post":{"garanti_id":"1","eylem":"talep_ekle","sikayet":" "}}'
calis "talep aç" "Garanti talebi açıldı" garantiler '{"post":{"garanti_id":"1","eylem":"talep_ekle","sikayet":"Menteşe gevşek"}}'
calis "ayrıntı: açık talep" "Tedarikçiye gönderildi" garantiler '{"get":{"id":"1"}}'
calis "liste: açık talep" "Mağazada" garantiler '{"get":{"f":"talep"}}'
calis "tedarikçiye gönder" "tedarikçiye gönderildi" garantiler '{"post":{"garanti_id":"1","talep_id":"1","eylem":"talep_tedarikci","supplier_id":"1","gonderim":"2026-10-01"}}'
calis "başka garantinin talebine dokunulamaz" "Talep bulunamadı" garantiler '{"post":{"garanti_id":"2","talep_id":"1","eylem":"talep_yeniden"}}'
calis "tedarikçi formu" "Menteşe gevşek" garanti_talep_formu '{"id":1}'
yok "tedarikçi formunda müşteri telefonu yok" "0532" garanti_talep_formu '{"id":1}'
calis "kapat: sonuç yok" "Sonucu seçin" garantiler '{"post":{"garanti_id":"1","talep_id":"1","eylem":"talep_kapat","durum":"tamamlandi","sonuc_tur":""}}'
calis "kapat: maliyet hatalı" "Maliyet geçersiz" garantiler '{"post":{"garanti_id":"1","talep_id":"1","eylem":"talep_kapat","durum":"tamamlandi","sonuc_tur":"tamir","maliyet":"abc"}}'
calis "kapat" "Talep tamamlandı" garantiler '{"post":{"garanti_id":"1","talep_id":"1","eylem":"talep_kapat","durum":"tamamlandi","sonuc_tur":"tamir","sonuc":"Menteşe değişti","maliyet":"120,00"}}'
calis "ayrıntı: geçmiş" "Tamir edildi" garantiler '{"get":{"id":"1"}}'
calis "düzenle" "Garanti güncellendi" garantiler '{"post":{"garanti_id":"1","eylem":"guncelle","urun":"Ray-Ban RB5154 49","seri_no":"SN-1","supplier_id":"1","bitis":"2028-12-01","kapsam":"Menteşe dahil"}}'
calis "müşteri sayfası" "Garanti geçerli" garanti_genel '{"get":{"k":"__TOKEN__"}}'
calis "müşteri sayfası: talep" "Tamir edildi" garanti_genel '{"get":{"k":"__TOKEN__"}}'
yok "müşteri sayfası: soyad ve telefon yok" "Yılmaz" garanti_genel '{"get":{"k":"__TOKEN__"}}'
yok "müşteri sayfası: maliyet yok" "120" garanti_genel '{"get":{"k":"__TOKEN__"}}'
calis "müşteri sayfası: geçersiz" "Garanti bulunamadı" garanti_genel '{"get":{"k":"YANLIS"}}'
calis "garanti kartı" "GARANTİ BELGESİ" garanti_belge '{"id":1}'
calis "garanti kartı: karekod" "<svg" garanti_belge '{"id":1}'
calis "garanti kartı: koşullar" "yetkisiz müdahale" garanti_belge '{"id":1}'
calis "ayar: hatalı süre" "120 ay" garantiler '{"post":{"eylem":"ayar","ay_cerceve":"0","ay_cam":"24","ay_gunes":"24","ay_diger":"24"}}'
calis "ayar" "Garanti ayarları kaydedildi" garantiler '{"post":{"eylem":"ayar","ay_cerceve":"36","ay_cam":"24","ay_gunes":"24","ay_diger":"12","otomatik":"1","kosullar":"Kendi koşulumuz"}}'
calis "ayar: koşul basılır" "Kendi koşulumuz" garanti_belge '{"id":1}'
calis "iptal" "Garanti iptal edildi" garantiler '{"post":{"garanti_id":"1","eylem":"durum","durum":"iptal"}}'
calis "müşteri sayfası: iptal" "İptal edildi" garanti_genel '{"get":{"k":"__TOKEN__"}}'
calis "sil" "Garanti kaydı silindi" garantiler '{"post":{"garanti_id":"1","eylem":"sil"}}'
calis "silindikten sonra" "Garanti bulunamadı" garantiler '{"get":{"id":"1"}}'
echo "Garanti sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

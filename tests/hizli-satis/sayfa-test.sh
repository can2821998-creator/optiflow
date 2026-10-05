#!/usr/bin/env bash
# Hızlı satış ekranı, ürün kataloğu ve satış fişi: uyarısız render + POST akışları
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
yok() {
  local ad="$1" yasak="$2"; shift 2
  local cikti; cikti=$(php sayfa-test.php "$@" 2>&1); local kod=$?
  if [ $kod -ne 0 ] || grep -qF -- "$yasak" <<<"$cikti"; then
    echo "  ✗ $ad (çıkış $kod)"; echo "$cikti" | head -6 | sed 's/^/     /'; kalan=$((kalan+1))
  else gecen=$((gecen+1)); fi
}
calis "hazırlık" "__FLASH__" hazirla
calis "müşteri" "" sql '{"sql":"INSERT INTO customers (first_name, last_name, phone) VALUES ('"'"'Ayşe'"'"', '"'"'Yılmaz'"'"', '"'"'05321234567'"'"')"}'
# Ürün kataloğu
calis "katalog boş" "Ürün yok" urunler
calis "ürün ekle" "Ürün kataloğa eklendi" urunler '{"post":{"eylem":"kaydet","ad":"Solüsyon 360 ml","kategori":"solusyon","barkod":"8690000000017","fiyat":"250,00","maliyet":"120","kdv":"10","stok":"4","min_stok":"2","stok_takip":"on"}}'
calis "barkodsuz ürüne PU barkod" "Ürün kataloğa eklendi" urunler '{"post":{"eylem":"kaydet","ad":"Gözlük kılıfı","kategori":"aksesuar","fiyat":"150","stok":"10","min_stok":"1","stok_takip":"on"}}'
calis "üretilen barkod" "PU" sql '{"sql":"SELECT barkod FROM urunler WHERE id = 2"}'
calis "aynı barkod reddedilir" "başka bir üründe" urunler '{"post":{"eylem":"kaydet","ad":"Kopya","barkod":"8690000000017"}}'
calis "liste" "Solüsyon 360 ml" urunler
calis "ayrıntı + hareketler" "Stok hareketleri" urunler '{"get":{"duzenle":"1"}}'
calis "stok girişi" "Stok güncellendi: +6" urunler '{"post":{"eylem":"hareket","id":"1","sebep":"giris","adet":"6"}}'
calis "sayım" "Stok güncellendi: -2" urunler '{"post":{"eylem":"hareket","id":"1","sebep":"sayim","adet":"8"}}'
calis "kritik filtre" "Gözlük kılıfı" urunler '{"get":{"f":"hepsi","q":"kılıf"}}'
# Hızlı satış ekranı
calis "ekran" "Satışı tamamla" hizli_satis
calis "ekran: barkod hedefi" "data-barkod-hedef" hizli_satis
calis "boş gün" "Bugün henüz satış yok" hizli_satis
calis "arama JSON: barkod" '"tur":"urun"' hizli_satis '{"get":{"ara":"8690000000017"}}'
calis "arama JSON: çerçeve" '"tur":"cerceve"' hizli_satis '{"get":{"ara":"8680000000017"}}'
yok "arama JSON maliyet sızdırmaz" '"maliyet"' hizli_satis '{"get":{"ara":"solüs"}}'
calis "müşteri arama JSON" '"ad":"Ayşe Yılmaz"' hizli_satis '{"get":{"musteri":"ayşe yıl"}}'
calis "müşteri arama: telefon" '"id":1' hizli_satis '{"get":{"musteri":"0532 123"}}'
calis "eksik ödeme reddedilir" "eşit olmalı" hizli_satis '{"post":{"eylem":"sat","sepet":"[{\"tur\":\"cerceve\",\"id\":1,\"adet\":1}]","odemeler":"[{\"method\":\"nakit\",\"amount\":100}]"}}'
calis "bozuk sepet" "Sepet okunamadı" hizli_satis '{"post":{"eylem":"sat","sepet":"x","odemeler":"[]"}}'
calis "satış (parçalı ödeme)" "Satış #1 tamamlandı" hizli_satis '{"post":{"eylem":"sat","indirim":"50,00","musteri_id":"1","not":"Vitrin","sepet":"[{\"tur\":\"cerceve\",\"id\":1,\"adet\":1},{\"tur\":\"urun\",\"id\":1,\"adet\":2},{\"tur\":\"serbest\",\"ad\":\"Burun pedi\",\"fiyat\":75}]","odemeler":"[{\"method\":\"nakit\",\"amount\":1000},{\"method\":\"kart\",\"amount\":1975}]"}}'
calis "stok düştü (çerçeve 3→2)" '"qty":2' sql '{"sql":"SELECT qty FROM frame_items WHERE id = 1"}'
calis "stok düştü (ürün 8→6)" '"stok":6' sql '{"sql":"SELECT stok FROM urunler WHERE id = 1"}'
calis "satış ayrıntısı" "Tamamlandı" hizli_satis '{"get":{"satis":"1"}}'
calis "ayrıntı: sepet indirimi" "Sepet indirimi" hizli_satis '{"get":{"satis":"1"}}'
calis "ayrıntı: müşteri" "Ayşe Yılmaz" hizli_satis '{"get":{"satis":"1"}}'
calis "günün listesi" "#1" hizli_satis
calis "fiş" "SATIŞ FİŞİ" satis_fis '{"get":{"id":"1"}}'
calis "fiş: ödemeler" "Kredi kartı" satis_fis '{"get":{"id":"1"}}'
calis "fiş: serbest kalem" "Burun pedi" satis_fis '{"get":{"id":"1"}}'
# Personel: kendi satışları, indirim sınırı, iptal yok
calis "personel ekranı" "Bugünkü satışlarınız" personel
yok "personel başkasının satışını görmez" "#1</a>" personel
yok "personel iptal düğmesi görmez" "Satışı iptal et" personel '{"get":{"satis":"1"}}'
calis "personel indirim sınırı" "en yüksek oranı" personel '{"post":{"eylem":"sat","indirim":"100","sepet":"[{\"tur\":\"urun\",\"id\":2,\"adet\":1}]","odemeler":"[{\"method\":\"nakit\",\"amount\":50}]"}}'
calis "personel iptal edemez" "yönetici" personel '{"post":{"eylem":"iptal","id":"1","sebep":"x"}}'
# İptal
calis "iptal: sebep zorunlu" "sebebini" hizli_satis '{"post":{"eylem":"iptal","id":"1","sebep":" "}}'
calis "iptal" "iptal edildi" hizli_satis '{"post":{"eylem":"iptal","id":"1","sebep":"Müşteri iade etti"}}'
calis "iptal: stok geri (çerçeve 3)" '"qty":3' sql '{"sql":"SELECT qty FROM frame_items WHERE id = 1"}'
calis "iptal: stok geri (ürün 8)" '"stok":8' sql '{"sql":"SELECT stok FROM urunler WHERE id = 1"}'
calis "iptal görünür" "İptal edildi" hizli_satis '{"get":{"satis":"1"}}'
calis "fiş: iptal damgası" "İPTAL EDİLMİŞTİR" satis_fis '{"get":{"id":"1"}}'
echo "Hızlı satış sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

#!/usr/bin/env bash
# ÜTS ekranlarının uyarısız render edildiğini ve POST işlemlerini sınar (SQLite dosya veritabanı).
set -u
cd "$(dirname "$0")"
export UTS_TEST_ROOT=$(mktemp -d)
mkdir -p "$UTS_TEST_ROOT/storage/logs"
export UTS_TEST_DB="$UTS_TEST_ROOT/test.sqlite"
gecen=0; kalan=0
calis() { # ad beklenen-metin senaryo json
  local ad="$1" bek="$2"; shift 2
  local cikti; cikti=$(php sayfa-test.php "$@" 2>&1); local kod=$?
  # redirect() exit ile biter (kod 0); uyarı = kod 3
  if [ $kod -ne 0 ] || ! grep -qF -- "$bek" <<<"$cikti"; then
    echo "  ✗ $ad (çıkış $kod)"; echo "$cikti" | head -8 | sed 's/^/     /'; kalan=$((kalan+1))
  else gecen=$((gecen+1)); fi
}
calis "hazırlık" "__FLASH__" hazirla
calis "mal kabul sekmesi (boş)" "Kabul bekleyen ürün yok" uts
calis "örnek gelen ekle" "" uts '{"post":{"eylem":"deneme_ornek"}}'
calis "mal kabul listesi" "DENEME Ray-Ban" uts '{"get":{"tab":"gelen"}}'
calis "kabul et" "" uts '{"post":{"eylem":"kabul","id":["1","2"],"kat":{"1":"cerceve","2":"cam"},"cerceve_stok":"1","kart_olustur":"1"}}'
calis "kabul sonrası stokta" '"durum":"stokta"' sql '{"sql":"SELECT durum FROM uts_urunler WHERE id = 1"}'
calis "stok okut" "" uts '{"post":{"eylem":"stok_okut","kod":"010868000000001721RB001","kategori":"cerceve","adet":"1"}}'
calis "stok sekmesi" "RB001" uts '{"get":{"tab":"stok"}}'
calis "stok arama karekodla" "RB001" uts '{"get":{"tab":"stok","q":"010868000000001721RB001"}}'
calis "stok arama metinle" "Ray-Ban" uts '{"get":{"tab":"stok","q":"ray"}}'
calis "stok filtre skt" "<main" uts '{"get":{"tab":"stok","durum":"skt"}}'
calis "ürün kartı" "08680000000017" uts '{"get":{"urun":"4"}}'
calis "ürün düzenle" "Ürün güncellendi" uts '{"post":{"eylem":"urun_duzenle","urun_id":"2","kategori":"cam"}}'
calis "sipariş kartı (boş)" "Karekodu okutun" siparis '{"id":1}'
calis "siparişe okut" "OKUNDU Ray-Ban" okut '{"id":1,"kod":"010868000000001721RB001"}'
calis "okutunca fiyat" "2450" okut '{"id":1,"kod":"010868000000001721RB009"}'
calis "sipariş kartı (dolu)" "RB001" siparis '{"id":1}'
calis "bildirimler sekmesi" "Alma (mal kabul)" uts '{"get":{"tab":"bildirimler","durum":"hepsi"}}'
calis "sıradakileri gönder" "" uts '{"post":{"eylem":"simdi_gonder","tab":"bildirimler"}}'
calis "iletilenler" "iletildi" uts '{"get":{"tab":"bildirimler","durum":"gonderildi"}}'
calis "imha (belge no yok)" "zorunludur" uts '{"post":{"eylem":"imha","id":["1"],"grk":"SON_KULLANMA_TARIHI_GECMIS","bno":""}}'
calis "imha" "1 ürün için imha" uts '{"post":{"eylem":"imha","id":["1"],"grk":"SON_KULLANMA_TARIHI_GECMIS","bno":"T-1"}}'
calis "imha gönder" "iletildi" uts '{"post":{"eylem":"simdi_gonder","tab":"bildirimler"}}'
calis "iade (seçim yok)" "0 ürün" uts '{"post":{"eylem":"iade","id":[],"supplier_id":"1","iade_bno":"I-1"}}'
calis "ayar sekmesi" "ÜTS bağlantısı" ayar
calis "ayar kaydet (token yok → deneme)" "ISLENDI" ayar '{"post":{"action":"uts_kaydet","uts_ortam":"canli","uts_kurum_no":"12 34","uts_gonderim":"onayli"}}'
calis "ortam deneme kaldı" '"setting_value":"deneme"' sql '{"sql":"SELECT setting_value FROM app_settings WHERE setting_key = '"'"'uts_ortam'"'"'"}'
calis "ayar kaydet (token + test)" "ISLENDI" ayar '{"post":{"action":"uts_kaydet","uts_ortam":"test","uts_kurum_no":"1234","uts_token":"dogru-token","uts_gonderim":"otomatik","uts_taban_test":"https://kotu.example.com","uts_yol_alma":"/v9/alma"}}'
calis "kötü taban adres kaydedilmedi" '"setting_value":""' sql '{"sql":"SELECT setting_value FROM app_settings WHERE setting_key = '"'"'uts_taban_test'"'"'"}'
calis "özel yol kaydedildi" 'v9' sql '{"sql":"SELECT setting_value FROM app_settings WHERE setting_key = '"'"'uts_yol_alma'"'"'"}'
calis "token şifreli" '"g' sql '{"sql":"SELECT substr(setting_value,1,3) AS s FROM app_settings WHERE setting_key = '"'"'uts_token'"'"'"}'
calis "deneme kayıtları uyarısı" "gerçekte ÜTS" uts '{"get":{"tab":"bildirimler"}}'
calis "bağlantıyı dene (sahte ÜTS)" "bağlantısı çalışıyor" ayar '{"post":{"action":"uts_test"},"tasiyici":1}'
calis "gerçek ortam sayfa" "ÜTS test ortamı" uts '{"get":{"tab":"gelen"}}'
echo "Sayfa testleri: $gecen geçti, $kalan kaldı"
rm -rf "$UTS_TEST_ROOT"
[ $kalan -eq 0 ]

# Eski atölye sisteminden OptiFlow'a taşıma

*OptiFlow 4.17.1 · Merkez panel › mağaza › "Eski sistemden veri taşı"*

Eski **Poyraz Optik Atölye** sisteminizdeki müşteri, sipariş, reçete, tahsilat, stok ve kullanıcılar tek ekrandan OptiFlow'daki bir mağazaya taşınır. phpMyAdmin'e ya da elle SQL çalıştırmaya gerek yoktur.

## Adımlar

1. **Eski sistemden yedek alın.** Eski sistemde **Yedekleme › Yedek al ve indir**. `yedek-….sql.gz` dosyası iner.
2. **Mağazayı hazırlayın.** Taşıyacağınız mağaza OptiFlow'da yoksa Merkez panel › **+ Yeni mağaza** ile açın. En güvenlisi boş, yeni bir mağazaya taşımaktır.
3. **Yedeği yükleyin.** Merkez panel › **Mağazalar** › mağazayı açın › **Eski sistemden veri taşı** › dosyayı seçin › **Yükle ve önizle**.
4. **Özeti kontrol edin.** Ekran, yedekte ve mağazada şu an kaç müşteri, sipariş, reçete, tahsilat, çerçeve, tedarikçi, teklif ve kullanıcı olduğunu yan yana gösterir.
5. **Onaylayın.** Kutuya **TAŞI** yazıp **Taşımayı başlat**'a basın. Sistem sırayla:
   - mağazanın o anki verisini otomatik yedekler,
   - yedekteki veriyi aktarır,
   - veritabanını güncel OptiFlow sürümüne yükseltir.
   
   Birkaç saniye sürer.
6. **Girin ve kontrol edin.** Personel, **eski kullanıcı adı ve şifresiyle** mağazaya girer. Siparişleri, müşterileri ve kasayı kontrol edin.

## Bir şey ters giderse

Aynı bölümde **Son taşımayı geri al**'ı açın, kutuya **GERİ AL** yazın. Mağaza taşımadan önceki haline döner.

Taşıma yarıda kesilirse de geri alma çalışır; ön yedek aktarım başlamadan alınır.

## Bilinmesi gerekenler

- **Mağazada veri varsa** taşıma o kayıtları silip yerine yedektekileri yazar. Ekran bunu kırmızı uyarıyla gösterir.
- **Kabul edilen yedekler:** yalnızca OptiFlow ve Poyraz Optik Atölye yedekleri (`.sql` / `.sql.gz`).
- **Güvenlik denetimi:** dosyada yalnızca tablo oluşturma ve veri ekleme komutlarına izin verilir. Başka bir komut varsa (ör. veritabanı silme, izin verme, tetikleyici) dosya hiç işlenmez.
- **Dosyanın saklanması:** yüklenen dosya web'e kapalı bir klasörde tutulur ve 24 saat içinde silinir. Ön yedekler geri alma için saklanır.
- **Sürüm farkı:** eski sistemin yedeğinde yazan "şema 21" OptiFlow'un şema numarasıyla aynı değildir. Taşıma bunu tanır ve güncellemeleri baştan uygular.
- **Elle aktarılan yedekler:** yedeği phpMyAdmin'le elle aktarırsanız da artık sorun çıkmaz. OptiFlow ilk girişte eksik sütunları fark edip güncellemeyi baştan yapar.
- **Yükleme sınırı:** dosya sunucunun yükleme sınırını aşarsa Plesk › PHP ayarlarında `upload_max_filesize` ve `post_max_size` değerlerini artırın. 5 Ekim yedeği 47 KB, sınırın çok altında.
- **Kaynak:** taşıma `app/tasima.php` dosyasında, testleri `tests/tasima/` ve `desktop/tests/server/tasima-integration.mjs` dosyalarında.

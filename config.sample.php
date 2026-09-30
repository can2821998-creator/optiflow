<?php
/*
 * OptiFlow — yapılandırma örneği.
 * MEVCUT KURULUMDA config.php DOSYANIZI DEĞİŞTİRMEYİN; eski dosya aynen çalışır.
 * Yeni kurulumda bu dosyayı config.php adıyla kopyalayıp düzenleyin.
 */
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'veritabani_adi',
        'user'     => 'veritabani_kullanicisi',
        'password' => 'veritabani_parolasi',
    ],
    'timezone' => 'Europe/Istanbul',

    // ÜRÜNLEŞTİRME: çok mağazalı kurulum. Bu 'db' zaten mağaza kayıt listesini (magazalar tablosu) de tutar;
    // ayrı bir veritabanı GEREKMEZ. Sunucunuz MySQL kullanıcısına yeni veritabanı açma yetkisi veriyorsa
    // (kendi VPS/kök erişimli sunucu) yeni mağazalar kayıt formunda tek adımda otomatik açılır; vermiyorsa
    // (paylaşımlı hosting/Plesk aboneliği gibi) sistem kendiliğinden "beklemede" moduna düşer ve merkez
    // panelden (aşağıdaki şifreyle) elle etkinleştirirsiniz — ayrıntı: MAGAZALAR-KURULUM-NOTU.txt.

    // Merkez panelin (merkez-panel.php — mağaza listesi, deneme/plan yönetimi, beklemede mağazaları etkinleştirme)
    // şifresi. Boş bırakılırsa panel kapalı olur.
    'merkez_admin_password' => '',

    // Yalnızca İLK kurulumda, hiç kullanıcı yokken bir kez yönetici oluşturmak için kullanılır.
    // Giriş yaptıktan sonra bu iki satırı silebilirsiniz.
    'admin_username' => 'yonetici',
    'admin_password' => 'Guclu-Bir-Parola-2026',

    // SEO veri köprüsü (seo-veri.php). Boşsa uç nokta kapalıdır. En az 24 karakter, tahmin edilemez olsun.
    // 'seo_token'    => 'buraya-uzun-rastgele-bir-dize',
    // 'gsc_key_file' => __DIR__ . '/storage/gsc-anahtar.json',   // Google hizmet hesabı anahtarı
    // 'gsc_site'     => 'sc-domain:optiflow.com.tr',              // Search Console mülkü
    // 'psi_api_key'  => 'AIza...',                                // PageSpeed Insights API anahtarı (kota için)

    // 4.12.0 — İsteğe bağlı: zamanlanmış görev (cron.php) anahtarı. Boşsa storage/.cron-anahtar dosyasında
    // bir kez üretilir ve merkez panelde tam adresiyle gösterilir. En az 24 karakter.
    // 'cron_anahtar' => 'buraya-uzun-rastgele-bir-dize',

    // Hata ayrıntılarını ekranda göstermek için geçici olarak true yapın. Canlıda false kalmalı.
    'debug' => false,
];

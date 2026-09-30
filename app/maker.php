<?php
declare(strict_types=1);

/* ==========================================================================
   "Gözlüğünüzü kim yaptı?" — müşteri ile atölyedeki usta arasında bağ kurar.

   Müşteri sayfasında gözlüğü hazırlayan kişinin (Atölye: "Kim üzerinde çalışıyor")
   ve kontrol eden kişinin ADI, kısa tanıtımı ve ustanın bıraktığı kısa NOT görünür.

   - Kimin adı görüneceği KENDİ iznine bağlıdır: her personel Profilim ekranından
     açar/kapatır. Varsayılan KAPALIDIR; izin vermeyen kişi müşteri sayfasında görünmez.
   - Yalnızca ilk ad gösterilir. Soyad, telefon vb. hiçbir zaman gösterilmez.
   - Ana veritabanı şemasına dokunmaz: kendi iki tablosunu ilk kullanımda kendisi
     oluşturur. Okuma hataları sessizce yutulur; müşteri sayfası bozulmaz.
   ========================================================================== */

function maker_ensure_tables(): void
{
    $opts = function_exists('t_opts') ? t_opts() : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS staff_profiles (
        user_id INT UNSIGNED NOT NULL PRIMARY KEY,
        show_public TINYINT(1) NOT NULL DEFAULT 0,
        title VARCHAR(60) NOT NULL DEFAULT '',
        bio VARCHAR(240) NOT NULL DEFAULT '',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $opts");
    db()->exec("CREATE TABLE IF NOT EXISTS order_maker_notes (
        order_id INT UNSIGNED NOT NULL PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        note VARCHAR(240) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $opts");
}

/** Bir personelin müşteri sayfası tanıtımı. Tablo yoksa varsayılan (kapalı) döner. */
function maker_profile(int $userId): array
{
    try {
        $r = row('SELECT show_public, title, bio FROM staff_profiles WHERE user_id = ?', [$userId]);
    } catch (Throwable $e) {
        $r = null;
    }
    return [
        'show'  => $r ? (int) $r['show_public'] === 1 : false,
        'title' => $r ? (string) $r['title'] : '',
        'bio'   => $r ? (string) $r['bio'] : '',
    ];
}

function maker_profile_save(int $userId, bool $show, string $title, string $bio): void
{
    maker_ensure_tables();
    q(
        'INSERT INTO staff_profiles (user_id, show_public, title, bio) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE show_public = VALUES(show_public), title = VALUES(title), bio = VALUES(bio)',
        [$userId, $show ? 1 : 0, mb_substr(trim($title), 0, 60), mb_substr(trim($bio), 0, 240)]
    );
}

/** Siparişe bırakılan not: ['metin' => , 'user_id' => ] ya da null. */
function maker_note_get(int $orderId): ?array
{
    try {
        $r = row('SELECT note, user_id FROM order_maker_notes WHERE order_id = ?', [$orderId]);
    } catch (Throwable $e) {
        return null;
    }
    return $r ? ['metin' => (string) $r['note'], 'user_id' => (int) $r['user_id']] : null;
}

/** Notu kaydeder; boş metin notu siler. */
function maker_note_save(int $orderId, int $userId, string $note): void
{
    $note = mb_substr(trim(preg_replace('/\s+/u', ' ', $note) ?? ''), 0, 240);
    maker_ensure_tables();
    if ($note === '') {
        q('DELETE FROM order_maker_notes WHERE order_id = ?', [$orderId]);
        return;
    }
    q(
        'INSERT INTO order_maker_notes (order_id, user_id, note) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), note = VALUES(note)',
        [$orderId, $userId, $note]
    );
}

/** "Ali Yılmaz" → "Ali" */
function maker_first_name(string $fullName): string
{
    $p = preg_split('/\s+/u', trim($fullName));
    return (string) ($p[0] ?? '');
}

/**
 * Müşteri sayfası için: gözlüğü hazırlayan/kontrol eden (izin verenler) ve not.
 * $adim: müşterinin gördüğü aşama (2 = atölyede, 3 = hazır, 4 = teslim edildi).
 * Dönen: ['kisiler' => [['ad','harf','rol','unvan','bio']], 'not' => ['metin','ad']|null, 'baslik' => string]
 */
function maker_for_order(int $orderId, int $adim): array
{
    $bos = ['kisiler' => [], 'not' => null, 'baslik' => ''];
    try {
        $o = row('SELECT assigned_to, qc_by FROM orders WHERE id = ?', [$orderId]);
        if (!$o) {
            return $bos;
        }
        $montaj  = (int) ($o['assigned_to'] ?? 0);
        $kontrol = (int) ($o['qc_by'] ?? 0);
        $ids = array_values(array_unique(array_filter([$montaj, $kontrol])));
        $bilgi = [];
        foreach ($ids as $uid) {
            $p = maker_profile($uid);
            if (!$p['show']) {
                continue;
            }
            $ad = maker_first_name((string) scalar('SELECT full_name FROM user_accounts WHERE id = ?', [$uid]));
            if ($ad === '') {
                continue;
            }
            $bilgi[$uid] = ['ad' => $ad, 'harf' => mb_strtoupper(mb_substr($ad, 0, 1)), 'unvan' => $p['title'], 'bio' => $p['bio']];
        }

        $kisiler = [];
        $mAd = isset($bilgi[$montaj]) ? $bilgi[$montaj]['ad'] : '';
        $kAd = isset($bilgi[$kontrol]) ? $bilgi[$kontrol]['ad'] : '';
        foreach ($bilgi as $uid => $b) {
            $rol = $uid === $montaj && $uid === $kontrol ? 'hazırladı ve kontrol etti' : ($uid === $montaj ? 'hazırladı' : 'kontrol etti');
            $kisiler[] = $b + ['rol' => $rol];
        }

        /* Başlık: adı ekleme almadan (Türkçe ek uyumu sorunu olmasın) kurulan cümleler */
        $baslik = '';
        if ($mAd !== '' && $kAd !== '' && $mAd !== $kAd && $adim >= 3) {
            $baslik = 'Gözlüğünüzü ' . $mAd . ' hazırladı, ' . $kAd . ' kontrol etti';
        } elseif ($mAd !== '' && $adim >= 3) {
            $baslik = 'Gözlüğünüzü ' . $mAd . ' hazırladı' . ($mAd === $kAd ? ' ve kontrol etti' : '');
        } elseif ($mAd !== '') {
            $baslik = 'Şu an gözlüğünüzle ' . $mAd . ' ilgileniyor';
        } elseif ($kAd !== '' && $adim >= 3) {
            $baslik = 'Gözlüğünüzü ' . $kAd . ' kontrol edip hazırladı';
        }

        $not = null;
        $n = maker_note_get($orderId);
        if ($n) {
            $yazan = isset($bilgi[$n['user_id']]) ? $bilgi[$n['user_id']]['ad'] : 'Atölye ekibi';
            $not = ['metin' => $n['metin'], 'ad' => $yazan];
        }

        if (!$kisiler && !$not) {
            return $bos;
        }
        if ($baslik === '' && $not) {
            $baslik = 'Atölyeden size bir not var';
        }
        return ['kisiler' => $kisiler, 'not' => $not, 'baslik' => $baslik];
    } catch (Throwable $e) {
        return $bos;
    }
}

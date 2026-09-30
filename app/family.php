<?php
declare(strict_types=1);

/* ==========================================================================
   Aile hesabı — bir ailenin gözlük yenileme / SGK zamanlarını tek ekranda görmek
   ve tek mesajla haber vermek için.

   Aile iki yolla oluşur:
   1) Elle: müşteri kartında "Aileye kişi ekle" (anne, baba, çocuk...).
   2) Öneri: aynı telefon numarasıyla kayıtlı kişiler otomatik önerilir; personel
      onaylarsa aileye eklenir (otomatik bağlanmaz; farklı aileler aynı telefonu
      kullanıyor olabilir).

   Ana şemaya dokunmaz: customer_family_links tablosunu ilk kullanımda oluşturur.
   Okuma hataları müşteri kartını bozmaz. Süreler Ayarlar › Hatırlatma'dan gelir.
   ========================================================================== */

function family_ensure_table(): void
{
    $opts = function_exists('t_opts') ? t_opts() : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS customer_family_links (
        customer_id INT UNSIGNED NOT NULL PRIMARY KEY,
        family_id INT UNSIGNED NOT NULL,
        relation VARCHAR(30) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_family (family_id)
    ) $opts");
}

function family_relations(): array
{
    return ['Eş', 'Anne', 'Baba', 'Çocuk', 'Kardeş', 'Dede / Nine', 'Diğer'];
}

/** $a ve $b'yi aynı aileye koyar. $relation: $b'nin ailedeki rolü. İki ayrı aile varsa birleştirir. */
function family_link(int $a, int $b, string $relation): void
{
    if ($a === $b || $a <= 0 || $b <= 0) {
        return;
    }
    family_ensure_table();
    $relation = in_array($relation, family_relations(), true) ? $relation : '';
    $fa = (int) scalar('SELECT family_id FROM customer_family_links WHERE customer_id = ?', [$a]);
    $fb = (int) scalar('SELECT family_id FROM customer_family_links WHERE customer_id = ?', [$b]);

    if ($fa === 0 && $fb === 0) {
        $fid = $a;
        q('INSERT INTO customer_family_links (customer_id, family_id, relation) VALUES (?, ?, ?)', [$a, $fid, '']);
    } elseif ($fa !== 0 && $fb !== 0 && $fa !== $fb) {
        $fid = $fa;                                                     // iki aileyi birleştir
        q('UPDATE customer_family_links SET family_id = ? WHERE family_id = ?', [$fid, $fb]);
    } else {
        $fid = $fa !== 0 ? $fa : $fb;
        if ($fa === 0) {
            q('INSERT INTO customer_family_links (customer_id, family_id, relation) VALUES (?, ?, ?)', [$a, $fid, '']);
        }
    }
    q(
        'INSERT INTO customer_family_links (customer_id, family_id, relation) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE family_id = VALUES(family_id), relation = VALUES(relation)',
        [$b, $fid, $relation]
    );
}

/** Bir kişiyi aileden çıkarır; ailede tek kişi kalırsa aile dağıtılır. */
function family_unlink(int $memberId): void
{
    family_ensure_table();
    $fid = (int) scalar('SELECT family_id FROM customer_family_links WHERE customer_id = ?', [$memberId]);
    q('DELETE FROM customer_family_links WHERE customer_id = ?', [$memberId]);
    if ($fid > 0 && (int) scalar('SELECT COUNT(*) FROM customer_family_links WHERE family_id = ?', [$fid]) <= 1) {
        q('DELETE FROM customer_family_links WHERE family_id = ?', [$fid]);
    }
}

/** Arama: ad, soyad veya telefonla. Zaten ailede olanlar ve kişinin kendisi hariç. */
function family_search(string $text, int $selfId, array $excludeIds): array
{
    $text = trim($text);
    if (mb_strlen($text) < 2) {
        return [];
    }
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $text) . '%';
    $digits = preg_replace('/\D+/', '', $text) ?? '';
    $excl = array_values(array_unique(array_map('intval', array_merge($excludeIds, [$selfId]))));
    $ph = implode(',', array_fill(0, count($excl), '?'));
    $sql = "SELECT id, first_name, last_name, birth_year, phone FROM customers
             WHERE id NOT IN ($ph)
               AND (CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ?)
             ORDER BY first_name, last_name LIMIT 8";
    return rows($sql, array_merge($excl, [$like, $digits !== '' ? '%' . $digits . '%' : $like]));
}

/**
 * Müşteri kartındaki Aile kartı için her şey.
 * Dönen: uyeler (kendisi dahil), tum_idler, vadesi[], vadesi_metin, wa, iliskiler
 */
function family_overview(array $c): array
{
    $id = (int) $c['id'];
    $explicit = [];
    $selfRel = '';
    try {
        $fid = (int) scalar('SELECT family_id FROM customer_family_links WHERE customer_id = ?', [$id]);
        if ($fid > 0) {
            foreach (rows('SELECT customer_id, relation FROM customer_family_links WHERE family_id = ?', [$fid]) as $r) {
                if ((int) $r['customer_id'] === $id) {
                    $selfRel = (string) $r['relation'];
                } else {
                    $explicit[(int) $r['customer_id']] = (string) $r['relation'];
                }
            }
        }
    } catch (Throwable $e) {
        $explicit = [];   // tablo henüz yok: yalnızca telefon önerileri gösterilir
    }

    $oneri = [];
    $phone = trim((string) ($c['phone'] ?? ''));
    if ($phone !== '') {
        foreach (rows('SELECT id FROM customers WHERE phone = ? AND id <> ? LIMIT 12', [$phone, $id]) as $r) {
            if (!isset($explicit[(int) $r['id']])) {
                $oneri[(int) $r['id']] = true;
            }
        }
    }

    $ids = array_merge([$id], array_keys($explicit), array_keys($oneri));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $satirlar = rows(
        "SELECT c.id, c.first_name, c.last_name, c.birth_year,
                (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = c.id AND o.order_stage <> 'iptal'
                    AND COALESCE(o.transaction_type, 'gozluk') <> 'tamir') AS son_gozluk,
                (SELECT MAX(r.prescription_date) FROM prescription_records r WHERE r.customer_id = c.id) AS son_recete
           FROM customers c WHERE c.id IN ($ph)",
        $ids
    );
    $byId = [];
    foreach ($satirlar as $s) {
        $byId[(int) $s['id']] = $s;
    }

    $a = reminder_settings();
    $yil = (int) date('Y');
    $uyeler = [];
    $vadesi = [];
    foreach ($ids as $mid) {
        if (!isset($byId[$mid])) {
            continue;
        }
        $m = $byId[$mid];
        $yas = $m['birth_year'] ? $yil - (int) $m['birth_year'] : null;
        $ben = $mid === $id;
        $sinif = $ben || isset($explicit[$mid]) ? 'uye' : 'oneri';

        $kinds = [];
        $sebep = [];
        if ($m['son_gozluk'] && reminder_months((string) $m['son_gozluk']) >= $a['renew']) {
            $kinds[] = 'yenileme';
            $sebep[] = 'gözlük yenileme';
        }
        if ($m['son_recete']) {
            $gereken = ($yas !== null && $yas < 18) ? $a['sgk_child'] : $a['sgk'];
            if (reminder_months((string) $m['son_recete']) >= $gereken) {
                $kinds[] = 'sgk';
                $sebep[] = 'SGK hakkı';
            }
        }

        $alt = [];
        $rel = $ben ? $selfRel : ($explicit[$mid] ?? '');
        if ($rel !== '') {
            $alt[] = $rel;
        }
        if ($yas !== null) {
            $alt[] = $yas . ' yaş';
        }
        $alt[] = $m['son_gozluk'] ? 'son gözlük ' . date('m.Y', (int) strtotime((string) $m['son_gozluk'])) : 'gözlük siparişi yok';

        $uyeler[] = [
            'id'      => $mid,
            'ad'      => trim($m['first_name'] . ' ' . $m['last_name']),
            'alt'     => implode(' · ', $alt),
            'ben'     => $ben,
            'oneri'   => $sinif === 'oneri',
            'rozetler' => $sebep,
        ];
        if ($sinif === 'uye' && $kinds) {
            $vadesi[] = ['id' => $mid, 'ad' => (string) $m['first_name'], 'kinds' => $kinds, 'sebep' => $sebep];
        }
    }

    $uyeSayisi = count(array_filter($uyeler, static fn(array $u): bool => !$u['oneri']));
    $metin = '';
    $mesaj = '';
    if ($vadesi && $uyeSayisi >= 2) {
        $parcalar = array_map(static fn(array $v): string => $v['ad'] . ' (' . implode(' / ', $v['sebep']) . ')', $vadesi);
        $metin = implode(', ', $parcalar);
        $mesaj = 'Merhaba ' . $c['first_name'] . ', ' . setting('shop_name', 'OptiFlow') . ' olarak yazıyoruz. '
            . 'Ailenizden şu kişilerin gözlük kontrolü zamanı gelmiş olabilir: ' . $metin . '. Size uygun bir günde bekleriz.';
    }

    return [
        'uyeler'       => $uyeler,
        'uyeSayisi'    => $uyeSayisi,
        'tum_idler'    => $ids,
        'vadesi'       => $vadesi,
        'vadesi_metin' => $metin,
        'wa'           => $mesaj !== '' && function_exists('wa_url') ? wa_url((string) ($c['phone'] ?? ''), $mesaj) : '',
        'iliskiler'    => family_relations(),
    ];
}

/** "Aileye haber verildi": zamanı gelen üyeleri ilgili hatırlatma listelerinden düşürür. Kaç kayıt işaretlendi döner. */
function family_mark_notified(array $c): int
{
    $n = 0;
    foreach (family_overview($c)['vadesi'] as $v) {
        foreach ($v['kinds'] as $kind) {
            reminder_mark($kind, (int) $v['id'], 'yapildi', ['channel' => 'whatsapp']);
            $n++;
        }
    }
    return $n;
}

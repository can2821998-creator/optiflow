<?php
declare(strict_types=1);

/* 4.16.0 — Tedarikçiye garanti talebi formu (yazdırılır). 4.22.0: ortak döküm tasarımı (app/dokum.php).
   Beklenen: $g (garanti_bul), $t (talep), $shop. Müşteri iletişim bilgisi YOK. */
$tTed = $t['supplier_id'] ? row('SELECT name, phone, email FROM suppliers WHERE id = ?', [(int) $t['supplier_id']]) : null;
$tTed ??= $g['tedarikci'] ? ['name' => $g['tedarikci'], 'phone' => $g['tedarikci_tel'], 'email' => $g['tedarikci_eposta']] : null;
$tGd = garanti_durumu($g, substr((string) ($t['gonderim'] ?: $t['created_at']), 0, 10));

echo dokum_ust('Garanti talebi', [
    ['Talep no', garanti_no((int) $g['id']) . '-' . (int) $t['id']],
    ['Tarih', date_tr((string) ($t['gonderim'] ?: $t['created_at']))],
    ['Sipariş', $g['order_id'] ? order_no((int) $g['order_id']) : '—'],
]);
echo dokum_selam('Sayın yetkili', (string) ($tTed['name'] ?? 'Tedarikçi'),
    'Aşağıdaki ürün, belirtilen arıza ile <b>garanti kapsamında incelenmek</b> üzere tarafınıza gönderilmiştir. Değerlendirme sonucunu bu form üzerinden bildirmenizi rica ederiz.'
        . (!empty($tTed['phone']) ? '<br>' . e(phone_display((string) $tTed['phone'])) . (!empty($tTed['email']) ? ' · ' . e((string) $tTed['email']) : '') : ''),
    dokum_vurgu('Garanti bitişi', date_tr((string) $g['bitis']), $tGd['kod'] === 'gecerli' ? 'gönderimde geçerli' : 'süresi dolmuş', $tGd['kod'] === 'gecerli' ? 'ok' : 'koyu'));
echo dokum_bilgi([
    ['cerceve', 'Ürün', (string) $g['urun'], garanti_kalemleri()[$g['kalem']] ?? (string) $g['kalem']],
    ['etiket', 'Seri no', (string) ($g['seri_no'] ?: '')],
    ['takvim', 'Satış tarihi', date_tr((string) $g['baslangic'])],
    ['sgk', 'Garanti no', garanti_no((int) $g['id'])],
], true);
echo '<div class="bolum">' . dokum_kutu('Arıza / şikâyet', '<p class="buyuk">' . nl2br(e((string) $t['sikayet'])) . '</p>') . '</div>';
$tSonuc = '<ul class="kontrol">' . implode('', array_map(static fn($m) => '<li>' . e($m) . '</li>', ['Onarıldı', 'Yenisiyle değiştirildi', 'Garanti dışı (açıklayın)', 'Ücretli onarım önerisi'])) . '</ul>'
    . '<div class="cizgiler"><span></span><span></span><span></span></div>';
echo '<div class="bolum">' . dokum_kutu('Tedarikçi değerlendirmesi', $tSonuc, '', 'tedarikçi doldurur') . '</div>';
echo dokum_imzalar([
    ['Gönderen', (string) (current_user()['full_name'] ?? $shop), 'Ürün yukarıdaki arıza ile garanti kapsamında gönderilmiştir.'],
    ['Teslim alan (tedarikçi)', '', 'Ürünü teslim aldım.'],
]);
echo dokum_son('<p>Bu form <b>müşteri bilgisi içermez</b>. Ürünü geri gönderirken talep numarasını belirtin.</p>');

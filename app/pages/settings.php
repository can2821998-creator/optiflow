<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_super();

$tabs = [
    'genel'       => 'Genel',
    'kullanicilar' => 'Kullanıcılar',
    'cam-tipleri' => 'Cam tipleri',
    'katalog'     => 'Ürün kataloğu',
    'sablonlar'   => 'WhatsApp şablonları',
    'hatirlatma'  => 'Hatırlatma',
    'mukerrer'    => 'Mükerrer müşteriler',
];
require_once dirname(__DIR__) . '/partials/ayarlar-moduller.php';
$tabs += moduller_ayar_sekmeleri();   // 4.12.0 merkezden açılan modüller
$tab = is_post() ? post('tab') : query('tab', 'genel');
if (!isset($tabs[$tab])) {
    $tab = 'genel';
}
$self = 'settings.php?tab=' . $tab;

/* ================================================================== */
/*  İşlemler                                                           */
/* ================================================================== */
if (is_post()) {
    $action = post('action');
    if (moduller_ayar_post($tab, $action)) {
        redirect($self);
    }

    /* ---------- Genel ---------- */
    if ($action === 'general') {
        $idle = (int) post('session_idle_minutes');
        $haritaUrl = post('shop_map_url');
        if (!preg_match('#^https://[^\s]+$#i', $haritaUrl)) {
            $haritaUrl = '';   // yalnızca https:// ile başlayan tek parça bağlantı kabul edilir
        }
        $yorumUrl = post('shop_review_url');
        if (!preg_match('#^https://[^\s]+$#i', $yorumUrl)) {
            $yorumUrl = '';   // yalnızca https:// ile başlayan tek parça bağlantı kabul edilir
        }
        $values = [
            'shop_name'            => mb_substr(post('shop_name'), 0, 80) ?: 'OptiFlow',
            'shop_phone'           => mb_substr(post('shop_phone'), 0, 40),
            'shop_address'         => mb_substr(post('shop_address'), 0, 255),
            'shop_map_url'         => mb_substr($haritaUrl, 0, 500),
            'shop_review_url'      => mb_substr($yorumUrl, 0, 500),
            'shop_hours'           => mb_substr(post('shop_hours'), 0, 300),
            'staff_see_amounts'    => isset($_POST['staff_see_amounts']) ? '1' : '0',
            'staff_take_payments'  => isset($_POST['staff_take_payments']) ? '1' : '0',
            'session_idle_minutes' => (string) max(15, min(1440, $idle ?: 480)),
            'beni_hatirla'         => isset($_POST['beni_hatirla']) ? '1' : '0',
            'commission_rate'      => number_format(max(0, min(100, (float) str_replace(',', '.', post('commission_rate')))), 2, '.', ''),
            'daily_summary'        => isset($_POST['daily_summary']) ? '1' : '0',
            'daily_summary_hour'   => (string) max(0, min(23, (int) post('daily_summary_hour'))),
            'sgk_lens_amount'      => number_format(max(0, min(2000, (float) str_replace(',', '.', post('sgk_lens_amount')))), 2, '.', ''),
        ];
        $renk = post('brand_color');
        $renkKoyu = post('brand_color_deep');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $renk)) {
            $values['brand_color'] = strtolower($renk);
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $renkKoyu)) {
            $values['brand_color_deep'] = strtolower($renkKoyu);
        }
        if ($values['staff_see_amounts'] === '0') {
            $values['staff_take_payments'] = '0';
        }
        foreach ($values as $k => $v) {
            setting_set($k, $v);
        }
        if ($values['beni_hatirla'] === '0' && table_exists_safe('oturum_hatirla')) {
            q('DELETE FROM oturum_hatirla');   // kapatıldı: hatırlanan tüm cihazlar unutulur
        }
        audit('settings_update', 'settings', null, $values);
        flash('Ayarlar kaydedildi.');
        redirect($self);
    }

    /* ---------- Hatırlatma süreleri ve metinleri ---------- */
    if ($action === 'reminder_save') {
        $sayi = static fn(string $k, int $vars, int $alt, int $ust): string
            => (string) max($alt, min($ust, (int) post($k) ?: $vars));
        $values = [
            'reminder_renew_months'     => $sayi('reminder_renew_months', 24, 3, 120),
            'reminder_sgk_months'       => $sayi('reminder_sgk_months', 36, 3, 120),
            'reminder_sgk_child_months' => $sayi('reminder_sgk_child_months', 12, 3, 120),
            'reminder_pickup_days'      => $sayi('reminder_pickup_days', 7, 1, 120),
            'reminder_msg_yenileme'     => mb_substr(post('reminder_msg_yenileme'), 0, 1000),
            'reminder_msg_sgk'          => mb_substr(post('reminder_msg_sgk'), 0, 1000),
            'reminder_msg_teslim'       => mb_substr(post('reminder_msg_teslim'), 0, 1000),
            'reminder_msg_manuel'       => mb_substr(post('reminder_msg_manuel'), 0, 1000),
            'reminder_msg_bakiye'       => mb_substr(post('reminder_msg_bakiye'), 0, 1000),
        ];
        foreach ($values as $k => $v) {
            setting_set($k, $v);
        }
        audit('settings_update', 'settings', null, ['hatırlatma' => 'güncellendi']);
        flash('Hatırlatma ayarları kaydedildi.');
        redirect($self);
    }

    /* ---------- Kullanıcılar ---------- */
    if ($action === 'user_save') {
        $uid = post_int('user_id');
        $name = mb_substr(post('full_name'), 0, 120);
        $username = mb_strtolower(post('username'));
        $role = post('role');
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if ($name === '') { $errors[] = 'Ad soyad zorunlu.'; }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) { $errors[] = 'Kullanıcı adı 3–60 karakter; harf, rakam, nokta, tire içerebilir (Türkçe karakter olmadan).'; }
        if (!isset(roles()[$role])) { $errors[] = 'Yetki geçersiz.'; }
        if ((int) scalar('SELECT COUNT(*) FROM user_accounts WHERE username = ? AND id <> ?', [$username, $uid])) { $errors[] = 'Bu kullanıcı adı kullanılıyor.'; }
        if (!$uid || $password !== '') {
            if ($p = password_problem($password)) { $errors[] = $p; }
        }
        if ($uid) {
            $existing = row('SELECT * FROM user_accounts WHERE id = ?', [$uid]);
            if (!$existing) { $errors[] = 'Kullanıcı bulunamadı.'; }
            if ($existing && $existing['role'] === 'super_yetkili' && $role !== 'super_yetkili' && active_super_count() <= 1) {
                $errors[] = 'Son süper yetkilinin yetkisi düşürülemez.';
            }
        }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($self . ($uid ? '&edit=' . $uid : ''));
        }
        if ($uid) {
            $primStr = trim(post('commission_rate'));
            $prim = $primStr === '' ? null : max(0, min(100, (float) str_replace(',', '.', $primStr)));
            update('user_accounts', ['full_name' => $name, 'username' => $username, 'role' => $role, 'commission_rate' => $prim], 'id = ?', [$uid]);
            if ($password !== '') {
                set_password($uid, $password);
                audit('user_password', 'user', $uid, ['kullanıcı' => $username, 'yöneten' => $me['full_name']]);
            }
            audit('user_update', 'user', $uid, ['ad' => $name, 'kullanıcı' => $username, 'yetki' => roles()[$role]]);
            flash('Kullanıcı güncellendi.' . ($password !== '' ? ' Yeni parola geçerli; açık oturumları kapandı.' : ''));
        } else {
            $primStr = trim(post('commission_rate'));
            $prim = $primStr === '' ? null : max(0, min(100, (float) str_replace(',', '.', $primStr)));
            $uid = insert('user_accounts', ['full_name' => $name, 'username' => $username, 'role' => $role, 'commission_rate' => $prim, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'password_changed_at' => date('Y-m-d H:i:s')]);
            audit('user_create', 'user', $uid, ['ad' => $name, 'kullanıcı' => $username, 'yetki' => roles()[$role]]);
            flash('Kullanıcı oluşturuldu.');
        }
        redirect($self);
    }
    if ($action === 'user_toggle') {
        $uid = post_int('user_id');
        $u = row('SELECT * FROM user_accounts WHERE id = ?', [$uid]);
        if (!$u || $uid === (int) $me['id']) {
            flash('Kendi hesabınızı pasife alamazsınız.', 'error');
        } elseif ((int) $u['is_active'] && $u['role'] === 'super_yetkili' && active_super_count() <= 1) {
            flash('Son aktif süper yetkili pasife alınamaz.', 'error');
        } else {
            q('UPDATE user_accounts SET is_active = 1 - is_active WHERE id = ?', [$uid]);
            audit('user_update', 'user', $uid, ['kullanıcı' => $u['username'], 'durum' => (int) $u['is_active'] ? 'Pasif' : 'Aktif']);
            flash((int) $u['is_active'] ? 'Kullanıcı pasife alındı; oturumu hemen kapanır.' : 'Kullanıcı aktifleştirildi.');
        }
        redirect($self);
    }

    /* ---------- Cam tipleri ---------- */
    if ($action === 'lens_add') {
        $name = mb_substr(post('name'), 0, 80);
        if ($name === '') {
            flash('Cam tipi adı boş olamaz.', 'error');
        } elseif ((int) scalar('SELECT COUNT(*) FROM lens_types WHERE name = ?', [$name])) {
            flash('Bu cam tipi zaten var.', 'error');
        } else {
            insert('lens_types', ['name' => $name, 'sort_order' => (int) scalar('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM lens_types')]);
            audit('lens_type_update', 'settings', null, ['eklendi' => $name]);
            flash('Cam tipi eklendi.');
        }
        redirect($self);
    }
    if ($action === 'lens_update') {
        $lid = post_int('id');
        $old = row('SELECT * FROM lens_types WHERE id = ?', [$lid]);
        $name = mb_substr(post('name'), 0, 80);
        if (!$old || $name === '') {
            flash('Cam tipi güncellenemedi.', 'error');
            redirect($self);
        }
        if ($name !== $old['name'] && (int) scalar('SELECT COUNT(*) FROM lens_types WHERE name = ?', [$name])) {
            flash('Bu ad başka bir cam tipinde kullanılıyor.', 'error');
            redirect($self);
        }
        transaction(function () use ($lid, $old, $name) {
            update('lens_types', ['name' => $name, 'sort_order' => post_int('sort_order'), 'is_active' => isset($_POST['is_active']) ? 1 : 0], 'id = ?', [$lid]);
            if ($name !== $old['name']) {
                // Yeniden adlandırma geçmiş kayıtlara da yansır.
                q('UPDATE orders SET lens_type = ? WHERE lens_type = ?', [$name, $old['name']]);
                q('UPDATE prescription_records SET lens_type = ? WHERE lens_type = ?', [$name, $old['name']]);
                q('UPDATE near_prescription_details SET lens_type = ? WHERE lens_type = ?', [$name, $old['name']]);
                foreach (rows('SELECT * FROM prescription_lens_items WHERE lens_type = ?', [$old['name']]) as $it) {
                    $it['lens_type'] = $name;
                    update('prescription_lens_items', ['lens_type' => $name, 'lens_label' => lens_item_label($it)], 'id = ?', [$it['id']]);
                }
            }
        });
        audit('lens_type_update', 'settings', null, ['önce' => $old['name'], 'sonra' => $name, 'aktif' => isset($_POST['is_active']) ? 'evet' : 'hayır']);
        flash('Cam tipi kaydedildi.');
        redirect($self);
    }

    /* ---------- Katalog ---------- */
    if ($action === 'product_save') {
        $pid = post_int('id');
        $price = post('price') === '' ? null : parse_money(post('price'));
        $data = [
            'brand'     => mb_substr(post('brand'), 0, 60),
            'name'      => mb_substr(post('name'), 0, 120),
            'design'    => post('design'),
            'tier'      => post('tier'),
            'lens_index'=> post('lens_index') ?: null,
            'coating'   => post('coating') ?: null,
            'price'     => $price,
            'note'      => mb_substr(post('note'), 0, 255) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $errors = [];
        if ($data['brand'] === '' || $data['name'] === '') { $errors[] = 'Marka ve ürün adı zorunlu.'; }
        if (!in_array($data['design'], ['tek_odak', 'progressive', 'ofis', 'bifokal'], true)) { $errors[] = 'Tasarım geçersiz.'; }
        if (!isset(product_tiers()[$data['tier']])) { $errors[] = 'Segment geçersiz.'; }
        if ($data['lens_index'] !== null && !in_array($data['lens_index'], lens_indexes(), true)) { $errors[] = 'İndeks geçersiz.'; }
        if ($data['coating'] !== null && !in_array($data['coating'], coatings(), true)) { $errors[] = 'Kaplama geçersiz.'; }
        if (post('price') !== '' && ($price === null || $price < 0)) { $errors[] = 'Fiyat geçersiz.'; }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($self . ($pid ? '&edit=' . $pid : ''));
        }
        if ($pid) {
            update('lens_products', $data, 'id = ?', [$pid]);
        } else {
            $pid = insert('lens_products', $data);
        }
        audit('catalog_update', 'product', $pid, ['ürün' => $data['brand'] . ' ' . $data['name'], 'fiyat' => $price]);
        flash('Ürün kaydedildi.');
        redirect($self);
    }
    if ($action === 'product_delete') {
        $p = row('SELECT * FROM lens_products WHERE id = ?', [post_int('id')]);
        if ($p) {
            q('UPDATE prescription_records SET advisor_product_id = NULL WHERE advisor_product_id = ?', [$p['id']]);
            q('DELETE FROM lens_products WHERE id = ?', [$p['id']]);
            audit('catalog_update', 'product', (int) $p['id'], ['silindi' => $p['brand'] . ' ' . $p['name']]);
            flash('Ürün silindi.', 'info');
        }
        redirect($self);
    }

    /* ---------- Katalog: kopyala ---------- */
    if ($action === 'product_copy') {
        $p = row('SELECT * FROM lens_products WHERE id = ?', [post_int('id')]);
        if ($p) {
            unset($p['id']);
            $p['name'] = $p['name'] . ' (kopya)';
            $newId = insert('lens_products', $p);
            audit('catalog_update', 'product', $newId, ['kopyalandı' => $p['brand'] . ' ' . $p['name']]);
            flash('Ürün kopyalandı, şimdi düzenleyebilirsiniz.');
            redirect($self . '&edit=' . $newId);
        }
        redirect($self);
    }
    if ($action === 'product_toggle') {
        $p = row('SELECT * FROM lens_products WHERE id = ?', [post_int('id')]);
        if ($p) {
            update('lens_products', ['is_active' => (int) $p['is_active'] ? 0 : 1], 'id = ?', [$p['id']]);
            flash((int) $p['is_active'] ? 'Ürün pasife alındı.' : 'Ürün aktif edildi.');
        }
        redirect('settings.php?' . post('back'));
    }

    /* ---------- Şablonlar ---------- */
    if ($action === 'template_save') {
        $tid = post_int('id');
        $data = ['name' => mb_substr(post('name'), 0, 60), 'body' => mb_substr(post('body'), 0, 1000), 'sort_order' => post_int('sort_order'), 'is_active' => isset($_POST['is_active']) ? 1 : 0];
        if ($data['name'] === '' || $data['body'] === '') {
            flash('Şablon adı ve metni zorunlu.', 'error');
            redirect($self);
        }
        $tid ? update('message_templates', $data, 'id = ?', [$tid]) : ($tid = insert('message_templates', $data));
        audit('template_update', 'template', $tid, ['şablon' => $data['name']]);
        flash('Şablon kaydedildi.');
        redirect($self);
    }
    if ($action === 'template_delete') {
        $t = row('SELECT * FROM message_templates WHERE id = ?', [post_int('id')]);
        if ($t) {
            q('DELETE FROM message_templates WHERE id = ?', [$t['id']]);
            audit('template_update', 'template', (int) $t['id'], ['silindi' => $t['name']]);
            flash('Şablon silindi.', 'info');
        }
        redirect($self);
    }

    /* ---------- Müşteri birleştirme ---------- */
    if ($action === 'merge') {
        $target = post_int('target');
        $sources = array_values(array_diff(array_map('intval', (array) ($_POST['sources'] ?? [])), [$target]));
        $t = find_customer($target);
        if (!$t || !$sources) {
            flash('Birleştirmek için ana kaydı ve en az bir kaydı seçin.', 'error');
            redirect($self);
        }
        $in = in_placeholders($sources);
        $names = array_map(static fn($c) => $c['first_name'] . ' ' . $c['last_name'] . ' #' . $c['id'], rows("SELECT * FROM customers WHERE id IN ($in)", $sources));
        transaction(function () use ($target, $sources, $in, $t) {
            q("UPDATE orders SET customer_id = ? WHERE customer_id IN ($in)", array_merge([$target], $sources));
            q("UPDATE prescription_records SET customer_id = ? WHERE customer_id IN ($in)", array_merge([$target], $sources));
            // Ana kayıtta boş olan telefon/doğum yılı diğer kayıttan tamamlanır.
            foreach (rows("SELECT * FROM customers WHERE id IN ($in)", $sources) as $s) {
                if ($t['phone'] === '' && $s['phone'] !== '') { q('UPDATE customers SET phone = ? WHERE id = ?', [$s['phone'], $target]); $t['phone'] = $s['phone']; }
                if (!$t['birth_year'] && $s['birth_year']) { q('UPDATE customers SET birth_year = ? WHERE id = ?', [$s['birth_year'], $target]); $t['birth_year'] = $s['birth_year']; }
                if ($s['notes']) { q("UPDATE customers SET notes = TRIM(CONCAT(COALESCE(notes, ''), '\n', ?)) WHERE id = ?", [$s['notes'], $target]); }
            }
            q("DELETE FROM customers WHERE id IN ($in)", $sources);
            sync_customer_to_orders($target);
        });
        audit('customer_merge', 'customer', $target, ['ana kayıt' => $t['first_name'] . ' ' . $t['last_name'], 'birleştirilen' => implode(', ', $names)]);
        flash(count($sources) . ' kayıt “' . $t['first_name'] . ' ' . $t['last_name'] . '” ile birleştirildi.');
        redirect($self);
    }

    flash('Bilinmeyen işlem.', 'error');
    redirect($self);
}

function active_super_count(): int
{
    return (int) scalar("SELECT COUNT(*) FROM user_accounts WHERE role = 'super_yetkili' AND is_active = 1");
}

/* ================================================================== */
/*  Görünüm                                                            */
/* ================================================================== */
page_start('Ayarlar', 'settings');
page_header('Ayarlar', 'Mağaza, kullanıcılar, katalog ve mesaj şablonları.', '', '', 'Yönetim');
?>
<nav class="tabs" aria-label="Ayar bölümleri">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="settings.php?tab=<?= e($k) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'genel'): ?>
  <section class="card narrow">
    <form method="post" class="grid cols-2" data-guard>
      <?= csrf_field() ?><input type="hidden" name="tab" value="genel"><input type="hidden" name="action" value="general">
      <label class="field"><span>Mağaza adı</span><input name="shop_name" value="<?= e(setting('shop_name', 'OptiFlow')) ?>"></label>
      <label class="field"><span>Mağaza telefonu</span><input name="shop_phone" value="<?= e(setting('shop_phone')) ?>"></label>
      <label class="field span-all"><span>Adres (fişlerde ve müşteri sayfasında görünür)</span><input name="shop_address" value="<?= e(setting('shop_address')) ?>"></label>
      <label class="field span-all"><span>Harita bağlantısı (isteğe bağlı)</span><input name="shop_map_url" inputmode="url" placeholder="https://maps.app.goo.gl/…" value="<?= e(setting('shop_map_url')) ?>"><small class="muted">Müşterinin “Yol tarifi” düğmesi buraya gider. Google Haritalar’da mağazanızı açın › Paylaş › Bağlantıyı kopyala ile alıp yapıştırın. Boş bırakırsanız yukarıdaki adres aranır.</small></label>
      <label class="field span-all"><span>Çalışma saatleri (müşteri sayfasında görünür)</span><textarea name="shop_hours" rows="3" maxlength="300" placeholder="Pazartesi – Cuma: 09:00 – 18:00&#10;Cumartesi: 09:00 – 14:00"><?= e(setting('shop_hours')) ?></textarea><small class="muted">Her satır ayrı gösterilir. Boş bırakırsanız müşteri sayfasında saat görünmez.</small></label>
      <label class="field span-all"><span>Google yorum bağlantısı (isteğe bağlı)</span><input name="shop_review_url" inputmode="url" placeholder="https://g.page/r/…/review" value="<?= e(setting('shop_review_url')) ?>"><small class="muted">Sipariş teslim edildikten sonra müşteri sayfasında "Google'da değerlendirin" düğmesi çıkar. Google İşletme Profili › "Yorum iste" bölümünden bağlantıyı kopyalayın. Boş bırakırsanız düğme görünmez.</small></label>
      <fieldset class="field span-all checks">
        <legend>Personel yetkileri</legend>
        <label><input type="checkbox" name="staff_see_amounts" <?= setting('staff_see_amounts', '1') === '1' ? 'checked' : '' ?>> Personel sipariş tutarlarını ve kalan bakiyeyi görebilir, yeni siparişte tutar girebilir</label>
        <label><input type="checkbox" name="staff_take_payments" <?= setting('staff_take_payments', '0') === '1' ? 'checked' : '' ?>> Personel tahsilat / kapora girebilir</label>
        <small class="muted">Raporlar, toplam açık bakiye, tutar değiştirme, silme ve iptal her zaman yalnızca süper yetkilidedir.</small>
      </fieldset>
      <label class="field"><span>Oturum zaman aşımı (dakika)</span><input type="number" min="15" max="1440" name="session_idle_minutes" value="<?= e(setting('session_idle_minutes', '480')) ?>"></label>
      <fieldset class="field checks">
        <legend>Beni hatırla</legend>
        <label><input type="checkbox" name="beni_hatirla" <?= setting('beni_hatirla', '1') !== '0' ? 'checked' : '' ?>> Personel girişte “Beni hatırla”yı kullanabilir (30 gün parola sorulmaz)</label>
        <small class="muted">Kapatırsanız hatırlanan tüm cihazlarda bir sonraki açılışta parola sorulur.</small>
      </fieldset>
      <label class="field"><span>Genel prim oranı (%)</span>
        <input name="commission_rate" inputmode="decimal" value="<?= e(number_format((float) str_replace(',', '.', setting('commission_rate', '0')), 2, ',', '')) ?>">
        <small class="muted">Kârlılık ekranındaki prim hesabı. Kişiye özel oran kullanıcı kartından girilir.</small></label>
      <fieldset class="field span-all checks">
        <legend>Günün özeti</legend>
        <label><input type="checkbox" name="daily_summary" <?= setting('daily_summary', '0') === '1' ? 'checked' : '' ?>> Akşamları telefonuma günün özetini bildirim olarak gönder</label>
        <label><span class="muted">Saat</span>
          <input type="number" min="0" max="23" name="daily_summary_hour" value="<?= e(setting('daily_summary_hour', '19')) ?>" style="max-width:90px"></label>
        <small class="muted">Özet, bu saatten sonra sisteme ilk girişte gönderilir (sunucuda zamanlanmış görev gerekmez) — günde bir kez.</small>
      </fieldset>
      <fieldset class="field span-all checks">
        <legend>Marka rengi</legend>
        <div class="grid cols-2" style="gap:14px">
          <label class="field"><span>Ana renk</span>
            <input type="color" name="brand_color" value="<?= e(brand_color()) ?>" style="height:42px;padding:4px"></label>
          <label class="field"><span>Koyu ton (gölge/gradyan için)</span>
            <input type="color" name="brand_color_deep" value="<?= e(brand_color_deep()) ?>" style="height:42px;padding:4px"></label>
        </div>
        <small class="muted">Müşteri sayfaları, fiş ve atölye ekranındaki bordo rengin yerini alır.</small>
      </fieldset>
      <fieldset class="field span-all checks">
        <legend>SGK katkı payı (tahmini)</legend>
        <label class="field" style="max-width:220px"><span>Gözlük başına taban tutar (TL)</span>
          <input name="sgk_lens_amount" inputmode="decimal" value="<?= e(number_format((float) str_replace(',', '.', setting('sgk_lens_amount', '150')), 2, ',', '')) ?>"></label>
        <small class="muted">Reçete kaydedilince, kullanım şekline göre (uzak/yakın ayrı ayrı, en fazla 2 "gözlük") bu tutar
          otomatik hesaplanıp siparişe ön dolum olarak yazılır; sipariş sayfasından elle düzeltilebilir. SUT'ta diyoptriye göre
          kademeli artış var ve dönem dönem değişiyor — burada TEK bir taban tutar kullanılıyor, kesin tutarı MEDULA'dan teyit edin.
          Sık kullanılan değerler: <b>150 TL</b> (2025 ve öncesi) veya <b>160 TL</b> (17 Ocak 2026 SUT güncellemesi — teyit önerilir).</small>
      </fieldset>
      <div class="form-actions span-all"><button class="btn btn-primary">Kaydet</button></div>
    </form>
  </section>

<?php elseif ($tab === 'kullanicilar'):
    $users = rows('SELECT * FROM user_accounts ORDER BY is_active DESC, role DESC, full_name');
    $edit = query_int('edit') ? row('SELECT * FROM user_accounts WHERE id = ?', [query_int('edit')]) : null; ?>
  <div class="split">
    <section class="card split-main">
      <div class="card-head"><h2>Kullanıcılar</h2></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Ad soyad</th><th>Kullanıcı adı</th><th>Yetki</th><th class="hide-sm">Son giriş</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr class="<?= (int) $u['is_active'] ? '' : 'row-muted' ?>">
              <td><b><?= e($u['full_name']) ?></b><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="badge sm">Siz</span>' : '' ?><?= (int) $u['is_active'] ? '' : ' <span class="badge tone-gray sm">Pasif</span>' ?></td>
              <td><code><?= e($u['username']) ?></code></td>
              <td><?= e(roles()[$u['role']] ?? $u['role']) ?></td>
              <td class="hide-sm"><?= date_tr($u['last_login_at'], true) ?></td>
              <td class="row-actions">
                <a class="btn btn-sm" href="settings.php?tab=kullanicilar&edit=<?= (int) $u['id'] ?>">Düzenle</a>
                <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="tab" value="kullanicilar"><input type="hidden" name="action" value="user_toggle"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-ghost btn-sm"><?= (int) $u['is_active'] ? 'Pasife al' : 'Aktifleştir' ?></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    <aside class="split-side">
      <section class="card">
        <div class="card-head"><h2><?= $edit ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı' ?></h2><?php if ($edit): ?><a class="link" href="settings.php?tab=kullanicilar">Yeni ekle</a><?php endif; ?></div>
        <form method="post" class="stack" autocomplete="off">
          <?= csrf_field() ?><input type="hidden" name="tab" value="kullanicilar"><input type="hidden" name="action" value="user_save"><input type="hidden" name="user_id" value="<?= $edit ? (int) $edit['id'] : '' ?>">
          <label class="field"><span>Ad soyad</span><input name="full_name" value="<?= e($edit['full_name'] ?? '') ?>" required></label>
          <label class="field"><span>Kullanıcı adı</span><input name="username" value="<?= e($edit['username'] ?? '') ?>" autocapitalize="none" pattern="[a-zA-Z0-9._\-]{3,60}" required></label>
          <label class="field"><span>Yetki</span><select name="role"><?= select_options(roles(), $edit['role'] ?? 'personel') ?></select></label>
          <label class="field"><span>Prim oranı (%)</span>
            <input name="commission_rate" inputmode="decimal" value="<?= $edit && $edit['commission_rate'] !== null ? e(number_format((float) $edit['commission_rate'], 2, ',', '')) : '' ?>" placeholder="boş = genel oran">
            <small class="muted">Kârlılık ekranındaki prim hesabında kullanılır.</small></label>
          <label class="field"><span><?= $edit ? 'Yeni parola (değiştirmek için)' : 'Parola' ?></span><input name="password" type="password" autocomplete="new-password" minlength="8" <?= $edit ? '' : 'required' ?>></label>
          <small class="muted">En az 8 karakter, harf ve rakam içermeli. Parola değişince kullanıcının açık oturumları kapanır.</small>
          <button class="btn btn-primary"><?= $edit ? 'Kaydet' : 'Oluştur' ?></button>
        </form>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'cam-tipleri'):
    $types = rows("SELECT t.*, (SELECT COUNT(*) FROM orders WHERE lens_type = t.name) AS used FROM lens_types t ORDER BY t.sort_order, t.name"); ?>
  <section class="card narrow">
    <div class="card-head"><h2>Cam tipleri</h2><small class="muted">Sipariş, reçete ve depo listelerinde kullanılır</small></div>
    <form method="post" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="tab" value="cam-tipleri"><input type="hidden" name="action" value="lens_add">
      <input name="name" placeholder="Yeni cam tipi adı" required maxlength="80" aria-label="Yeni cam tipi"><button class="btn btn-primary"><?= icon('plus') ?> Ekle</button>
    </form>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Ad</th><th class="w-num">Sıra</th><th>Aktif</th><th class="hide-sm">Kullanım</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($types as $t): $fid = 'lt' . (int) $t['id']; ?>
          <tr>
            <td><input form="<?= $fid ?>" name="name" value="<?= e($t['name']) ?>" maxlength="80" aria-label="Ad"></td>
            <td class="w-num"><input form="<?= $fid ?>" name="sort_order" type="number" value="<?= (int) $t['sort_order'] ?>" aria-label="Sıra"></td>
            <td><input form="<?= $fid ?>" type="checkbox" name="is_active" <?= (int) $t['is_active'] ? 'checked' : '' ?> aria-label="Aktif"></td>
            <td class="hide-sm muted"><?= (int) $t['used'] ?> sipariş</td>
            <td><form method="post" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="tab" value="cam-tipleri"><input type="hidden" name="action" value="lens_update"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm">Kaydet</button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="hint">Kullanılan bir tipi silmek yerine pasife alın; geçmiş kayıtlar bozulmaz. Ad değiştirilirse eski siparişler ve depo satırları da güncellenir.</p>
  </section>

<?php elseif ($tab === 'katalog'):
    $designsCat = ['tek_odak' => 'Tek odak', 'progressive' => 'Progressive', 'ofis' => 'Ofis', 'bifokal' => 'Bifokal'];
    $catQ = mb_substr(query('katq'), 0, 60);
    $catDesign = query('katd');
    $catTier = query('katt');
    $catWhere = ['1=1'];
    $catParams = [];
    if ($catQ !== '') { $catWhere[] = "(brand LIKE ? OR name LIKE ?)"; $catParams[] = "%$catQ%"; $catParams[] = "%$catQ%"; }
    if (isset($designsCat[$catDesign])) { $catWhere[] = 'design = ?'; $catParams[] = $catDesign; }
    if (isset(product_tiers()[$catTier])) { $catWhere[] = 'tier = ?'; $catParams[] = $catTier; }
    $products = rows('SELECT * FROM lens_products WHERE ' . implode(' AND ', $catWhere) . ' ORDER BY is_active DESC, design, tier, brand, name', $catParams);
    $edit = query_int('edit') ? row('SELECT * FROM lens_products WHERE id = ?', [query_int('edit')]) : null;
    $katBack = http_build_query(array_filter(['tab' => 'katalog', 'katq' => $catQ, 'katd' => $catDesign, 'katt' => $catTier])); ?>
  <div class="split">
    <section class="card split-main">
      <div class="card-head"><h2>Ürün kataloğu</h2><small class="muted">Öneri asistanı bu listeden seçenek sunar</small></div>
      <form method="get" class="filters">
        <input type="hidden" name="tab" value="katalog">
        <label class="field"><span>Ara</span><input type="search" name="katq" value="<?= e($catQ) ?>" placeholder="Marka veya ürün adı"></label>
        <label class="field"><span>Tasarım</span><select name="katd" onchange="this.form.submit()"><option value="">Tümü</option><?= select_options($designsCat, $catDesign, false) ?></select></label>
        <label class="field"><span>Segment</span><select name="katt" onchange="this.form.submit()"><option value="">Tümü</option><?= select_options(product_tiers(), $catTier) ?></select></label>
        <div class="filter-actions"><button class="btn">Filtrele</button><?php if ($catQ || $catDesign || $catTier): ?><a class="btn btn-ghost" href="settings.php?tab=katalog">Temizle</a><?php endif; ?></div>
      </form>
      <?php if (!$products): ?><?= empty_state('Sonuç bulunamadı', $catQ || $catDesign || $catTier ? 'Filtreyi temizleyip tekrar deneyin.' : 'Sağdaki formdan ilk ürününüzü ekleyin.') ?><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Ürün</th><th>Tasarım</th><th class="hide-sm">İndeks / kaplama</th><th class="num">Fiyat (çift)</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr class="<?= (int) $p['is_active'] ? '' : 'row-muted' ?>">
              <td><b><?= e($p['brand']) ?></b> <?= e($p['name']) ?><small class="block muted"><?= e(product_tiers()[$p['tier']] ?? $p['tier']) ?><?= $p['note'] ? ' · ' . e($p['note']) : '' ?></small></td>
              <td><?= e($designsCat[$p['design']] ?? $p['design']) ?></td>
              <td class="hide-sm"><?= e($p['lens_index'] ?: 'Tümü') ?> · <?= e($p['coating'] ?: '—') ?></td>
              <td class="num"><?= $p['price'] !== null ? money($p['price']) : '<span class="muted">—</span>' ?></td>
              <td class="row-actions">
                <a class="btn btn-sm" href="settings.php?<?= e($katBack) ?>&edit=<?= (int) $p['id'] ?>">Düzenle</a>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="tab" value="katalog"><input type="hidden" name="action" value="product_copy"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="icon-btn sm" title="Kopyala"><?= icon('plus') ?></button></form>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="product_toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($katBack) ?>"><button class="icon-btn sm" title="<?= (int) $p['is_active'] ? 'Pasife al' : 'Aktif et' ?>"><?= icon((int) $p['is_active'] ? 'x' : 'check') ?></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <aside class="split-side">
      <section class="card">
        <div class="card-head"><h2><?= $edit ? 'Ürünü düzenle' : 'Yeni ürün' ?></h2><?php if ($edit): ?><a class="link" href="settings.php?<?= e($katBack) ?>">Yeni ekle</a><?php endif; ?></div>
        <form method="post" class="grid cols-2">
          <?= csrf_field() ?><input type="hidden" name="tab" value="katalog"><input type="hidden" name="action" value="product_save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>">
          <label class="field"><span>Marka</span><input name="brand" value="<?= e($edit['brand'] ?? '') ?>" required></label>
          <label class="field"><span>Ürün adı</span><input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
          <label class="field"><span>Tasarım</span><select name="design"><?= select_options($designsCat, $edit['design'] ?? 'tek_odak') ?></select></label>
          <label class="field"><span>Segment</span><select name="tier"><?= select_options(product_tiers(), $edit['tier'] ?? 'dengeli') ?></select></label>
          <label class="field"><span>İndeks</span><select name="lens_index"><option value="">Tüm indeksler</option><?= select_options(lens_indexes(), $edit['lens_index'] ?? '', false) ?></select></label>
          <label class="field"><span>Kaplama</span><select name="coating"><option value="">Belirtilmedi</option><?= select_options(coatings(), $edit['coating'] ?? '', false) ?></select></label>
          <label class="field"><span>Fiyat (çift, ₺)</span><input name="price" inputmode="decimal" value="<?= isset($edit['price']) && $edit['price'] !== null ? e(number_format((float) $edit['price'], 2, ',', '.')) : '' ?>" placeholder="Boş: fiyat sorulur"></label>
          <label class="field check-field"><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] ? 'checked' : '' ?>> Aktif</label>
          <label class="field span-all"><span>Not</span><input name="note" value="<?= e($edit['note'] ?? '') ?>" maxlength="255"></label>
          <div class="form-actions span-all">
            <?php if ($edit): ?><button class="btn btn-ghost danger" form="del-product">Sil</button><?php endif; ?>
            <button class="btn btn-primary">Kaydet</button>
          </div>
        </form>
        <?php if ($edit): ?><form method="post" id="del-product" data-confirm="Ürün katalogdan silinsin mi?"><?= csrf_field() ?><input type="hidden" name="tab" value="katalog"><input type="hidden" name="action" value="product_delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"></form><?php endif; ?>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'sablonlar'):
    $templates = rows('SELECT * FROM message_templates ORDER BY sort_order, id'); ?>
  <div class="split">
    <div class="split-main">
      <?php foreach (array_merge($templates, [['id' => 0, 'name' => '', 'body' => '', 'sort_order' => (count($templates) + 1) * 10, 'is_active' => 1]]) as $t): ?>
        <section class="card">
          <div class="card-head"><h2><?= $t['id'] ? e($t['name']) : 'Yeni şablon' ?></h2></div>
          <form method="post" class="grid cols-3">
            <?= csrf_field() ?><input type="hidden" name="tab" value="sablonlar"><input type="hidden" name="action" value="template_save"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label class="field"><span>Ad</span><input name="name" value="<?= e($t['name']) ?>" maxlength="60" required></label>
            <label class="field"><span>Sıra</span><input type="number" name="sort_order" value="<?= (int) $t['sort_order'] ?>"></label>
            <label class="field check-field"><input type="checkbox" name="is_active" <?= (int) $t['is_active'] ? 'checked' : '' ?>> Aktif</label>
            <label class="field span-all"><span>Mesaj</span><textarea name="body" rows="3" maxlength="1000" required data-template-body><?= e($t['body']) ?></textarea></label>
            <div class="form-actions span-all">
              <?php if ($t['id']): ?><button class="btn btn-ghost danger" form="del-tpl-<?= (int) $t['id'] ?>">Sil</button><?php endif; ?>
              <button class="btn btn-primary"><?= $t['id'] ? 'Kaydet' : 'Ekle' ?></button>
            </div>
          </form>
          <?php if ($t['id']): ?><form method="post" id="del-tpl-<?= (int) $t['id'] ?>" data-confirm="Şablon silinsin mi?"><?= csrf_field() ?><input type="hidden" name="tab" value="sablonlar"><input type="hidden" name="action" value="template_delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"></form><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
    <aside class="split-side">
      <section class="card sticky">
        <div class="card-head"><h2>Değişkenler</h2></div>
        <p class="small muted">Mesaja eklemek için tıklayın. Gönderim anında müşterinin bilgisiyle doldurulur.</p>
        <ul class="kv">
          <?php foreach (template_placeholders() as $ph => $label): ?>
            <li><button type="button" class="chip" data-insert="<?= e($ph) ?>"><?= e($ph) ?></button><span class="muted small"><?= e($label) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <p class="hint">{tutar} veya {kalan} içeren şablonlar, tutar görme yetkisi olmayan personele gösterilmez. Mesajlar otomatik gönderilmez; WhatsApp personelin onayıyla açılır.</p>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'hatirlatma'):
    $ra = reminder_settings(); ?>
  <div class="split">
    <div class="split-main">
      <section class="card">
        <div class="card-head"><h2>Süreler</h2><small class="muted">Hatırlatma merkezindeki listeler bu sürelere göre oluşur</small></div>
        <form method="post" class="grid cols-2" data-guard>
          <?= csrf_field() ?><input type="hidden" name="tab" value="hatirlatma"><input type="hidden" name="action" value="reminder_save">
          <label class="field"><span>Gözlük yenileme (ay)</span>
            <input name="reminder_renew_months" inputmode="numeric" value="<?= (int) $ra['renew'] ?>">
            <small class="muted">Son gözlüğünün üzerinden bu kadar ay geçen müşteriler listeye girer.</small></label>
          <label class="field"><span>Teslim alınmadı (gün)</span>
            <input name="reminder_pickup_days" inputmode="numeric" value="<?= (int) $ra['pickup'] ?>">
            <small class="muted">Hazır olduğu halde bu kadar gündür alınmayan gözlükler.</small></label>
          <label class="field"><span>SGK hakkı — yetişkin (ay)</span>
            <input name="reminder_sgk_months" inputmode="numeric" value="<?= (int) $ra['sgk'] ?>"></label>
          <label class="field"><span>SGK hakkı — 18 yaş altı (ay)</span>
            <input name="reminder_sgk_child_months" inputmode="numeric" value="<?= (int) $ra['sgk_child'] ?>"></label>

          <label class="field span-all"><span>Gözlük yenileme mesajı</span>
            <textarea name="reminder_msg_yenileme" rows="3" data-template-body><?= e(setting('reminder_msg_yenileme')) ?></textarea></label>
          <label class="field span-all"><span>SGK hakkı mesajı</span>
            <textarea name="reminder_msg_sgk" rows="3" data-template-body><?= e(setting('reminder_msg_sgk')) ?></textarea></label>
          <label class="field span-all"><span>Teslim alınmadı mesajı</span>
            <textarea name="reminder_msg_teslim" rows="3" data-template-body><?= e(setting('reminder_msg_teslim')) ?></textarea></label>
          <label class="field span-all"><span>Planlı arama mesajı</span>
            <textarea name="reminder_msg_manuel" rows="3" data-template-body><?= e(setting('reminder_msg_manuel')) ?></textarea></label>
          <label class="field span-all"><span>Bakiye hatırlatma mesajı</span>
            <textarea name="reminder_msg_bakiye" rows="3" data-template-body><?= e(setting('reminder_msg_bakiye')) ?></textarea></label>

          <div class="form-actions span-all"><button class="btn btn-primary">Kaydet</button></div>
        </form>
      </section>
    </div>
    <aside class="split-side">
      <section class="card sticky">
        <div class="card-head"><h2>Değişkenler</h2></div>
        <ul class="kv">
          <li><button type="button" class="chip" data-insert="{ad}">{ad}</button><span class="muted small">Müşteri adı</span></li>
          <li><button type="button" class="chip" data-insert="{ad_soyad}">{ad_soyad}</button><span class="muted small">Ad soyad</span></li>
          <li><button type="button" class="chip" data-insert="{ay}">{ay}</button><span class="muted small">Kaç ay geçti</span></li>
          <li><button type="button" class="chip" data-insert="{gun}">{gun}</button><span class="muted small">Kaç gün geçti</span></li>
          <li><button type="button" class="chip" data-insert="{siparis_no}">{siparis_no}</button><span class="muted small">Sipariş numarası</span></li>
          <li><button type="button" class="chip" data-insert="{kalan}">{kalan}</button><span class="muted small">Kalan bakiye</span></li>
          <li><button type="button" class="chip" data-insert="{magaza}">{magaza}</button><span class="muted small">Mağaza adı</span></li>
          <li><button type="button" class="chip" data-insert="{magaza_telefon}">{magaza_telefon}</button><span class="muted small">Mağaza telefonu</span></li>
        </ul>
        <p class="hint">Mesajlar kendiliğinden gönderilmez; WhatsApp penceresi personelin onayıyla açılır ve gönderme kararı personeldedir.</p>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'mukerrer'):
    // Aynı telefon veya aynı ad soyad ile birden fazla kayıt
    $groups = rows(
        "SELECT grp, GROUP_CONCAT(id ORDER BY id) AS ids FROM (
            SELECT id, CONCAT('tel:', phone) AS grp FROM customers WHERE phone <> ''
            UNION ALL
            SELECT id, CONCAT('ad:', LOWER(first_name), ' ', LOWER(last_name)) AS grp FROM customers
         ) x GROUP BY grp HAVING COUNT(*) > 1 ORDER BY grp LIMIT 100"
    ); ?>
  <section class="card">
    <div class="card-head"><h2>Mükerrer müşteriler</h2><small class="muted">Aynı telefon veya aynı ad soyadla açılmış kayıtlar</small></div>
    <?php if (!$groups): ?><?= empty_state('Mükerrer kayıt bulunamadı') ?><?php endif; ?>
    <?php $seen = []; foreach ($groups as $g):
        if (isset($seen[$g['ids']])) { continue; }
        $seen[$g['ids']] = true;
        $ids = array_map('intval', explode(',', $g['ids']));
        $list = rows('SELECT c.*, (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) AS orders, (SELECT MAX(created_at) FROM orders WHERE customer_id = c.id) AS last_order FROM customers c WHERE c.id IN (' . in_placeholders($ids) . ') ORDER BY orders DESC, id', $ids); ?>
      <form method="post" class="merge-group" data-confirm="Seçilen kayıtlar ana kayıtla birleştirilsin mi? Siparişler ve reçeteler ana kayda taşınır, bu işlem geri alınamaz.">
        <?= csrf_field() ?><input type="hidden" name="tab" value="mukerrer"><input type="hidden" name="action" value="merge">
        <p class="small muted"><?= str_starts_with($g['grp'], 'tel:') ? 'Aynı telefon: ' . e(phone_display(substr($g['grp'], 4))) : 'Aynı ad soyad' ?></p>
        <div class="table-wrap"><table class="table compact">
          <thead><tr><th>Ana kayıt</th><th>Birleştir</th><th>Müşteri</th><th>Telefon</th><th>Sipariş</th><th class="hide-sm">Son sipariş</th></tr></thead>
          <tbody>
            <?php foreach ($list as $i => $c): ?>
              <tr>
                <td><input type="radio" name="target" value="<?= (int) $c['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> aria-label="Ana kayıt"></td>
                <td><input type="checkbox" name="sources[]" value="<?= (int) $c['id'] ?>" <?= $i > 0 ? 'checked' : '' ?> aria-label="Birleştir"></td>
                <td><a class="link" href="customer.php?id=<?= (int) $c['id'] ?>" target="_blank"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a> <small class="muted">#<?= (int) $c['id'] ?></small></td>
                <td><?= e(phone_display($c['phone']) ?: '—') ?></td>
                <td><?= (int) $c['orders'] ?></td>
                <td class="hide-sm"><?= date_tr($c['last_order']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="form-actions"><button class="btn btn-sm">Seçilenleri birleştir</button></div>
      </form>
    <?php endforeach; ?>
    <p class="hint">Aynı telefonu kullanan aile üyelerini birleştirmeyin. Birleştirme işlem geçmişine kaydedilir.</p>
  </section>
<?php elseif (isset(moduller_ayar_sekmeleri()[$tab])): ?>
  <?php moduller_ayar_goster($tab); ?>
<?php endif; ?>
<?php page_end();

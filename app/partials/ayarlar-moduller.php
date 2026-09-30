<?php
declare(strict_types=1);

/* ==========================================================================
   4.12.0 — Ayarlar: merkezden açılan modüllerin sekmeleri
   (settings.php yalnızca süper yetkiliye açıktır.)
   ========================================================================== */

function moduller_ayar_sekmeleri(): array
{
    $t = [];
    if (ozellik_acik('whatsapp')) {
        $t['whatsapp'] = 'WhatsApp otomasyonu';
    }
    if (ozellik_acik('odeme_linki')) {
        $t['odeme'] = 'Ödeme linki';
    }
    if (ozellik_acik('efatura')) {
        $t['efatura'] = 'e-Fatura';
    }
    if (ozellik_acik('lens_takip')) {
        $t['lens'] = 'Kontakt lens';
    }
    if (ozellik_acik('uts_bildirim')) {
        $t['uts'] = 'ÜTS';
    }
    return $t;
}

/** POST işler; işlediyse true (çağıran yönlendirir). */
function moduller_ayar_post(string $tab, string $action): bool
{
    $bayrak = static fn(string $k): string => post($k) === '1' ? '1' : '0';
    $sayi = static fn(string $k, int $min, int $max, int $vars): string => (string) max($min, min($max, (int) (post($k) !== '' ? post($k) : $vars)));

    if ($tab === 'whatsapp' && $action === 'wa_kaydet' && ozellik_acik('whatsapp')) {
        setting_set('wa_kanal', post('wa_kanal') === 'cloud' ? 'cloud' : 'link');
        setting_set('wa_cloud_telefon_id', preg_replace('/\D/', '', post('wa_cloud_telefon_id')) ?? '');
        if (post('wa_cloud_token') !== '') {
            gizli_ayar_yaz('wa_cloud_token', trim(post('wa_cloud_token')));
        }
        if (post('wa_cloud_token_sil') === '1') {
            gizli_ayar_yaz('wa_cloud_token', '');
        }
        setting_set('wa_api_surum', preg_match('/^v\d{1,2}\.\d$/', post('wa_api_surum')) ? post('wa_api_surum') : WA_API_SURUM_VARSAYILAN);
        setting_set('wa_sablon_dil', preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', post('wa_sablon_dil')) ? post('wa_sablon_dil') : 'tr');
        foreach (array_keys(wa_olaylar()) as $o) {
            $ad = strtolower(trim(post('wa_sablon_' . $o)));
            setting_set('wa_sablon_' . $o, preg_match('/^[a-z0-9_]{1,512}$/', $ad) ? $ad : '');
            setting_set('wa_metin_' . $o, mb_substr(trim(str_replace("\r", '', (string) ($_POST['wa_metin_' . $o] ?? ''))), 0, 1000));
        }
        foreach (['wa_oto_hazir', 'wa_oto_yorum', 'wa_oto_lens', 'wa_oto_teslim', 'wa_oto_yenileme', 'wa_oto_sgk'] as $k) {
            setting_set($k, $bayrak($k));
        }
        setting_set('wa_yorum_gun', $sayi('wa_yorum_gun', 1, 30, 2));
        setting_set('wa_saat_bas', $sayi('wa_saat_bas', 0, 23, 9));
        setting_set('wa_saat_bit', $sayi('wa_saat_bit', 1, 24, 21));
        audit('settings_update', 'settings', null, ['bölüm' => 'WhatsApp']);
        flash('WhatsApp ayarları kaydedildi.');
        return true;
    }
    if ($tab === 'whatsapp' && $action === 'wa_test' && ozellik_acik('whatsapp')) {
        $tel = wa_telefon(post('test_telefon'));
        $olay = isset(wa_olaylar()[post('test_olay')]) ? post('test_olay') : 'hazir';
        if ($tel === '') {
            flash('Test için geçerli bir cep telefonu yazın.', 'error');
            return true;
        }
        $ornek = [];
        foreach (wa_olaylar()[$olay]['degiskenler'] as $k) {
            $ornek[] = match ($k) {
                'ad' => 'Test', 'magaza' => setting('shop_name', 'OptiFlow'), 'siparis_no' => order_no(1),
                'tutar' => money(100), 'hak_tarihi', 'bitis' => date_tr(date('Y-m-d')), 'urun' => 'Örnek lens',
                default => 'https://example.com',
            };
        }
        $s = wa_cloud_gonder(['olay' => $olay, 'telefon' => $tel, 'parametreler' => json_encode($ornek, JSON_UNESCAPED_UNICODE)]);
        flash($s['ok'] ? 'Test mesajı gönderildi (Meta kimliği: ' . $s['dis_id'] . ').' : 'Gönderilemedi: ' . $s['hata'], $s['ok'] ? 'ok' : 'error');
        return true;
    }
    if ($tab === 'odeme' && $action === 'odeme_kaydet' && ozellik_acik('odeme_linki')) {
        setting_set('paytr_merchant_id', preg_replace('/\D/', '', post('paytr_merchant_id')) ?? '');
        foreach (['paytr_merchant_key', 'paytr_merchant_salt'] as $k) {
            if (post($k) !== '') {
                gizli_ayar_yaz($k, trim(post($k)));
            }
        }
        setting_set('paytr_max_taksit', $sayi('paytr_max_taksit', 1, 12, 1));
        setting_set('paytr_gecerlilik_gun', $sayi('paytr_gecerlilik_gun', 1, 30, 7));
        setting_set('paytr_test', $bayrak('paytr_test'));
        audit('settings_update', 'settings', null, ['bölüm' => 'Ödeme linki']);
        flash('Ödeme linki ayarları kaydedildi.');
        return true;
    }
    if ($tab === 'efatura' && $action === 'efatura_kaydet' && ozellik_acik('efatura')) {
        require_once dirname(__DIR__) . '/fatura.php';
        $metin = static fn(string $k, int $n): string => mb_substr(trim(post($k)), 0, $n);
        foreach (['firma_unvan' => 200, 'firma_ad' => 80, 'firma_soyad' => 80, 'firma_vergi_dairesi' => 80, 'firma_adres' => 255,
                  'firma_ilce' => 60, 'firma_il' => 60, 'firma_posta_kodu' => 10, 'firma_telefon' => 20, 'firma_web' => 120,
                  'sgk_fatura_unvan' => 200, 'sgk_fatura_vergi_dairesi' => 80, 'sgk_fatura_adres' => 255, 'sgk_fatura_ilce' => 60, 'sgk_fatura_il' => 60] as $k => $n) {
            setting_set($k, $metin($k, $n));
        }
        setting_set('firma_eposta', filter_var(trim(post('firma_eposta')), FILTER_VALIDATE_EMAIL) ?: '');
        foreach (['firma_vkn' => 11, 'firma_mersis' => 16, 'sgk_fatura_vkn' => 10] as $k => $n) {
            setting_set($k, mb_substr(preg_replace('/\D/', '', post($k)) ?? '', 0, $n));
        }
        $seri = strtoupper(post('fatura_seri'));
        setting_set('fatura_seri', preg_match('/^[A-Z0-9]{3}$/', $seri) ? $seri : 'OPT');
        setting_set('fatura_kdv', isset(fatura_kdv_oranlari()[(int) post('fatura_kdv')]) ? (string) (int) post('fatura_kdv') : '10');
        setting_set('efatura_entegrator', isset(efatura_suruculer()[post('efatura_entegrator')]) ? post('efatura_entegrator') : 'yok');
        audit('settings_update', 'settings', null, ['bölüm' => 'e-Fatura']);
        $eksik = fatura_firma_eksikleri();
        flash($eksik ? 'Kaydedildi. Eksikler: ' . implode(' ', $eksik) : 'e-Fatura bilgileri kaydedildi.', $eksik ? 'warn' : 'ok');
        return true;
    }
    if ($tab === 'uts' && $action === 'uts_kaydet' && ozellik_acik('uts_bildirim')) {
        $eskiOrtam = uts_ortam();
        $ortam = isset(UTS_ORTAMLAR[post('uts_ortam')]) ? post('uts_ortam') : 'deneme';
        setting_set('uts_kurum_no', mb_substr(preg_replace('/\D/', '', post('uts_kurum_no')) ?? '', 0, 20));
        if (post('uts_token') !== '') {
            gizli_ayar_yaz('uts_token', trim(post('uts_token')));
        }
        if (post('uts_token_sil') === '1') {
            gizli_ayar_yaz('uts_token', '');
        }
        if ($ortam !== 'deneme' && gizli_ayar('uts_token') === '') {
            flash('Test / Canlı ortam için önce sistem token\'ını girin. Ortam "Deneme" olarak kaldı.', 'error');
            $ortam = 'deneme';
        }
        setting_set('uts_ortam', $ortam);
        setting_set('uts_gonderim', post('uts_gonderim') === 'onayli' ? 'onayli' : 'otomatik');
        setting_set('uts_ad_gonder', $bayrak('uts_ad_gonder'));
        // Gelişmiş: taban adres (yalnızca *.saglik.gov.tr) ve servis yolları
        foreach (['test', 'canli'] as $o) {
            $url = rtrim(trim(post('uts_taban_' . $o)), '/');
            setting_set('uts_taban_' . $o, $url !== '' && uts_adres_guvenli_mi($url) && $url !== UTS_TABAN_VARSAYILAN[$o] ? $url : '');
        }
        foreach (array_merge(uts_turler(), uts_sorgular()) as $k => $tanim) {
            $yol = uts_yol_temizle(post('uts_yol_' . $k));
            setting_set('uts_yol_' . $k, $yol !== '' && $yol !== '/' && $yol !== $tanim['yol'] ? $yol : '');
        }
        setting_set('uts_yetki_hatasi', '');
        audit('settings_update', 'settings', null, ['bölüm' => 'ÜTS', 'ortam' => $ortam]);
        flash('ÜTS ayarları kaydedildi.');
        if ($eskiOrtam === 'deneme' && $ortam !== 'deneme') {
            flash('Gerçek ortama geçtiniz. Deneme modunda "iletildi" sayılan bildirimler ÜTS\'ye gitmedi; ÜTS › Bildirimler ekranından gönderebilirsiniz.', 'warn');
        }
        return true;
    }
    if ($tab === 'uts' && $action === 'uts_test' && ozellik_acik('uts_bildirim')) {
        if (uts_ortam() === 'deneme') {
            flash('Deneme modunda ÜTS\'ye bağlanılmaz. Ortamı Test ya da Canlı yapıp kaydedin.', 'info');
            return true;
        }
        $r = uts_gelenleri_getir();
        flash($r['ok'] ? 'ÜTS bağlantısı çalışıyor: ' . $r['sayi'] . ' kabul bekleyen ürün alındı.' : 'ÜTS bağlantısı başarısız: ' . $r['mesaj'], $r['ok'] ? 'ok' : 'error');
        return true;
    }
    if ($tab === 'lens' && $action === 'lens_kaydet' && ozellik_acik('lens_takip')) {
        setting_set('lens_hatirlat_gun', $sayi('lens_hatirlat_gun', 1, 60, 10));
        flash('Kontakt lens ayarları kaydedildi.');
        return true;
    }
    return false;
}

function moduller_ayar_goster(string $tab): void
{
    $sec = static fn(string $k, string $vars = '0'): string => setting($k, $vars) === '1' ? 'checked' : '';
    if ($tab === 'whatsapp') {
        $kanal = wa_kanal();
        $token = gizli_ayar('wa_cloud_token'); ?>
  <form method="post" class="stack" style="gap:16px">
    <?= csrf_field() ?><input type="hidden" name="tab" value="whatsapp"><input type="hidden" name="action" value="wa_kaydet">
    <section class="card">
      <div class="card-head"><h2>Gönderim kanalı</h2></div>
      <label class="check"><input type="radio" name="wa_kanal" value="link" <?= $kanal === 'link' ? 'checked' : '' ?>> <b>Tek tık (WhatsApp uygulaması)</b> — mesajlar kuyruğa girer; personel "WhatsApp'ta aç" deyip gönderir. Ek hesap gerekmez.</label>
      <label class="check"><input type="radio" name="wa_kanal" value="cloud" <?= $kanal === 'cloud' ? 'checked' : '' ?>> <b>Otomatik (WhatsApp Business Platform)</b> — Meta'da onaylı şablonlarla arka planda gider. Meta Business hesabı ve doğrulanmış numara gerekir; Meta mesaj başına ücret alır.</label>
      <div class="grid cols-2" style="margin-top:10px">
        <label class="field"><span>Telefon numarası kimliği (Phone number ID)</span><input name="wa_cloud_telefon_id" inputmode="numeric" value="<?= e(setting('wa_cloud_telefon_id', '')) ?>"></label>
        <label class="field"><span>Kalıcı erişim anahtarı <?= $token !== '' ? '(kayıtlı: ' . e(gizli_maske($token)) . ')' : '' ?></span><input name="wa_cloud_token" type="password" autocomplete="new-password" placeholder="<?= $token !== '' ? 'Değiştirmek için yazın' : 'System user token' ?>"></label>
        <label class="field"><span>Graph API sürümü</span><input name="wa_api_surum" value="<?= e(setting('wa_api_surum', WA_API_SURUM_VARSAYILAN)) ?>"></label>
        <label class="field"><span>Şablon dili</span><input name="wa_sablon_dil" value="<?= e(setting('wa_sablon_dil', 'tr')) ?>"></label>
      </div>
      <?php if ($token !== ''): ?><label class="check"><input type="checkbox" name="wa_cloud_token_sil" value="1"> Kayıtlı anahtarı sil</label><?php endif; ?>
      <p class="hint">Anahtar veritabanına şifreli yazılır. Cloud modunda mesaj metinleri Meta'daki şablondan gelir; aşağıdaki metinler Tek tık modunda kullanılır.</p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Otomatik mesajlar</h2><small class="muted">Gönderim saatleri: <?= e(gorev_saat_metni()) ?></small></div>
      <label class="check"><input type="checkbox" name="wa_oto_hazir" value="1" <?= $sec('wa_oto_hazir', '1') ?>> Sipariş “Hazır” olunca müşteriye haber ver</label>
      <label class="check"><input type="checkbox" name="wa_oto_yorum" value="1" <?= $sec('wa_oto_yorum') ?>> Teslimden sonra Google yorum isteği (izinli müşteriler, Genel ayarlardaki yorum linki)</label>
      <label class="field" style="max-width:220px"><span>Yorum isteği teslimden kaç gün sonra</span><input name="wa_yorum_gun" type="number" min="1" max="30" value="<?= e(setting('wa_yorum_gun', '2')) ?>"></label>
      <label class="check"><input type="checkbox" name="wa_oto_lens" value="1" <?= $sec('wa_oto_lens', '1') ?>> Kontakt lens bitiyor (izinli müşteriler)</label>
      <label class="check"><input type="checkbox" name="wa_oto_teslim" value="1" <?= $sec('wa_oto_teslim') ?>> Teslim alınmayan gözlükler (günlük)</label>
      <label class="check"><input type="checkbox" name="wa_oto_yenileme" value="1" <?= $sec('wa_oto_yenileme') ?>> Gözlük yenileme listesi (izinli müşteriler, günlük)</label>
      <label class="check"><input type="checkbox" name="wa_oto_sgk" value="1" <?= $sec('wa_oto_sgk') ?>> SGK hakkı doğanlar (izinli müşteriler, günlük)</label>
      <div class="grid cols-2" style="max-width:420px">
        <label class="field"><span>Gönderim başlangıç saati</span><input name="wa_saat_bas" type="number" min="0" max="23" value="<?= e(setting('wa_saat_bas', '9')) ?>"></label>
        <label class="field"><span>Bitiş saati</span><input name="wa_saat_bit" type="number" min="1" max="24" value="<?= e(setting('wa_saat_bit', '21')) ?>"></label>
      </div>
      <p class="hint">Otomatik görevler personel sayfaları açıkken 5 dakikada bir çalışır. Mağaza kapalıyken de çalışması için OptiFlow destekten zamanlanmış görev adresini isteyin.</p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Mesaj metinleri ve şablon adları</h2></div>
      <?php foreach (wa_olaylar() as $o => $t): ?>
        <div style="border-top:1px solid var(--line, #eee);padding-top:10px;margin-top:10px">
          <b><?= e($t['ad']) ?></b> <?= $t['izin'] ? '<span class="badge tone-amber">izin gerekir</span>' : '<span class="badge tone-green">bilgilendirme</span>' ?>
          <div class="grid cols-2" style="margin-top:6px">
            <label class="field"><span>Meta şablon adı (Cloud)</span><input name="wa_sablon_<?= e($o) ?>" value="<?= e(setting('wa_sablon_' . $o, '')) ?>" placeholder="örn. optiflow_<?= e($o) ?>"></label>
            <label class="field"><span>Şablon değişken sırası</span><input readonly value="<?= e(implode(', ', array_map(static fn($i, $k) => '{{' . ($i + 1) . '}} = ' . $k, array_keys($t['degiskenler']), $t['degiskenler']))) ?>"></label>
          </div>
          <label class="field"><span>Tek tık metni</span><textarea name="wa_metin_<?= e($o) ?>" rows="2" placeholder="<?= e($t['metin']) ?>"><?= e(setting('wa_metin_' . $o, '')) ?></textarea></label>
        </div>
      <?php endforeach; ?>
      <p class="hint">Değişkenler: {ad} {soyad} {magaza} {magaza_telefon} {siparis_no} {takip_linki} {tutar} {odeme_linki} {hak_tarihi} {urun} {bitis} {yorum_linki}. Boş bırakılan metin varsayılanı kullanır.</p>
    </section>

    <div class="alert alert-warn">Yenileme, SGK hakkı, lens ve yorum isteği mesajları tanıtım niteliği taşıyabilir; yalnızca açık izin veren müşterilere gider.
      İzin kaydı müşteri kartından tutulur. Hangi mesajların ticari ileti sayıldığı ve İYS yükümlülükleri için hukuk danışmanınıza başvurun.</div>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>

  <?php if ($kanal === 'cloud'): ?>
  <section class="card">
    <div class="card-head"><h2>Test mesajı</h2><small class="muted">Kayıtlı ayarlarla hemen gönderir</small></div>
    <form method="post" class="grid cols-3" style="align-items:end">
      <?= csrf_field() ?><input type="hidden" name="tab" value="whatsapp"><input type="hidden" name="action" value="wa_test">
      <label class="field"><span>Cep telefonu</span><input name="test_telefon" type="tel" placeholder="05xx xxx xx xx"></label>
      <label class="field"><span>Şablon</span><select name="test_olay"><?php foreach (wa_olaylar() as $o => $t): ?><option value="<?= e($o) ?>"><?= e($t['ad']) ?></option><?php endforeach; ?></select></label>
      <div class="form-actions"><button class="btn">Gönder</button></div>
    </form>
  </section>
  <?php endif;
        return;
    }

    if ($tab === 'odeme') {
        $b = paytr_bilgileri();
        $bildirim = odeme_bildirim_url(); ?>
  <form method="post" class="stack" style="gap:16px">
    <?= csrf_field() ?><input type="hidden" name="tab" value="odeme"><input type="hidden" name="action" value="odeme_kaydet">
    <section class="card">
      <div class="card-head"><h2>PayTR hesabı</h2><span class="badge tone-<?= paytr_hazir_mi() ? 'green' : 'gray' ?>"><?= paytr_hazir_mi() ? 'Bağlı' : 'Eksik' ?></span></div>
      <p class="hint" style="margin-top:0">PayTR Mağaza Paneli › Bilgi sayfasındaki değerleri girin. Ödemeler doğrudan mağazanızın PayTR hesabına geçer.</p>
      <div class="grid cols-3">
        <label class="field"><span>Mağaza no (merchant_id)</span><input name="paytr_merchant_id" inputmode="numeric" value="<?= e($b['merchant_id']) ?>"></label>
        <label class="field"><span>Mağaza parola (merchant_key) <?= $b['key'] !== '' ? '· kayıtlı ' . e(gizli_maske($b['key'])) : '' ?></span><input name="paytr_merchant_key" type="password" autocomplete="new-password" placeholder="<?= $b['key'] !== '' ? 'Değiştirmek için yazın' : '' ?>"></label>
        <label class="field"><span>Mağaza gizli anahtar (merchant_salt) <?= $b['salt'] !== '' ? '· kayıtlı ' . e(gizli_maske($b['salt'])) : '' ?></span><input name="paytr_merchant_salt" type="password" autocomplete="new-password" placeholder="<?= $b['salt'] !== '' ? 'Değiştirmek için yazın' : '' ?>"></label>
      </div>
      <div class="grid cols-3">
        <label class="field"><span>En fazla taksit</span><select name="paytr_max_taksit"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= (int) setting('paytr_max_taksit', '1') === $i ? 'selected' : '' ?>><?= $i === 1 ? 'Tek çekim' : $i . ' taksit' ?></option><?php endfor; ?></select></label>
        <label class="field"><span>Link geçerliliği (gün)</span><input name="paytr_gecerlilik_gun" type="number" min="1" max="30" value="<?= e(setting('paytr_gecerlilik_gun', '7')) ?>"></label>
        <label class="check" style="align-self:end"><input type="checkbox" name="paytr_test" value="1" <?= $sec('paytr_test') ?>> Test modu (gerçek para çekilmez)</label>
      </div>
      <p class="hint">Bildirim adresi: <code><?= e($bildirim ?: 'belirlenemedi') ?></code><?= odeme_bildirim_url_gecerli($bildirim) ? '' : ' <b class="text-danger">— https alan adı gerekli.</b>' ?><br>
        Ödeme bu adrese PayTR tarafından bildirilir ve imza doğrulanınca siparişe “Kredi kartı” tahsilatı olarak yazılır.</p>
    </section>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>
  <?php
        return;
    }

    if ($tab === 'efatura') {
        require_once dirname(__DIR__) . '/fatura.php';
        $f = fatura_firma();
        $eksik = fatura_firma_eksikleri();
        $in = static fn(string $ad, string $etiket, string $deger, string $ek = ''): string => '<label class="field"><span>' . e($etiket) . '</span><input name="' . e($ad) . '" value="' . e($deger) . '" ' . $ek . '></label>'; ?>
  <form method="post" class="stack" style="gap:16px">
    <?= csrf_field() ?><input type="hidden" name="tab" value="efatura"><input type="hidden" name="action" value="efatura_kaydet">
    <?php if ($eksik): ?><div class="alert alert-warn"><?= e(implode(' ', $eksik)) ?></div><?php endif; ?>
    <section class="card">
      <div class="card-head"><h2>Satıcı (mağaza) bilgileri</h2></div>
      <div class="grid cols-2">
        <?= $in('firma_unvan', 'Ticari unvan', $f['unvan']) ?>
        <?= $in('firma_vkn', 'VKN (10 hane) ya da şahıs şirketi TCKN (11 hane)', $f['kimlik'], 'inputmode="numeric" maxlength="11"') ?>
        <?= $in('firma_ad', 'Ad (şahıs şirketi)', $f['ad']) ?>
        <?= $in('firma_soyad', 'Soyad (şahıs şirketi)', $f['soyad']) ?>
        <?= $in('firma_vergi_dairesi', 'Vergi dairesi', $f['vergi_dairesi']) ?>
        <?= $in('firma_mersis', 'MERSİS no (varsa)', $f['mersis'], 'inputmode="numeric"') ?>
        <?= $in('firma_adres', 'Adres', $f['adres']) ?>
        <?= $in('firma_posta_kodu', 'Posta kodu', $f['posta_kodu'], 'inputmode="numeric"') ?>
        <?= $in('firma_ilce', 'İlçe', $f['ilce']) ?>
        <?= $in('firma_il', 'İl', $f['il']) ?>
        <?= $in('firma_eposta', 'E-posta', $f['eposta'], 'type="email"') ?>
        <?= $in('firma_telefon', 'Telefon', $f['telefon']) ?>
        <?= $in('firma_web', 'Web sitesi', $f['web']) ?>
      </div>
    </section>
    <section class="card">
      <div class="card-head"><h2>Belge</h2></div>
      <div class="grid cols-3">
        <?= $in('fatura_seri', 'Seri (3 harf/rakam)', fatura_seri(), 'maxlength="3" style="text-transform:uppercase"') ?>
        <label class="field"><span>Varsayılan KDV</span><select name="fatura_kdv"><?php foreach (fatura_kdv_oranlari() as $k => $v): ?><option value="<?= $k ?>" <?= (float) $k === fatura_varsayilan_kdv() ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Özel entegratör</span><select name="efatura_entegrator"><?php foreach (efatura_suruculer() as $k => $sinif): ?><option value="<?= e($k) ?>"><?= e((new $sinif())->ad()) ?></option><?php endforeach; ?></select></label>
      </div>
      <p class="hint">Fatura numarası ve e-imza, entegratör bağlandığında gönderim sırasında verilir. KDV oranlarını mali müşavirinizle doğrulayın (numaralı gözlük, cam ve çerçevede %10; güneş gözlüğünde %20 yaygın uygulamadır).</p>
    </section>
    <section class="card">
      <div class="card-head"><h2>SGK katkı payı faturası alıcısı</h2></div>
      <p class="hint" style="margin-top:0">Ön değerler kamuya açık kaynaklardan alınmıştır; kullanmadan önce SGK sözleşmeniz ve mali müşavirinizle doğrulayın.</p>
      <div class="grid cols-2">
        <?= $in('sgk_fatura_unvan', 'Unvan', setting('sgk_fatura_unvan', 'SOSYAL GÜVENLİK KURUMU GENEL SAĞLIK SİGORTASI GENEL MÜDÜRLÜĞÜ')) ?>
        <?= $in('sgk_fatura_vkn', 'VKN', setting('sgk_fatura_vkn', '7750409379'), 'inputmode="numeric" maxlength="10"') ?>
        <?= $in('sgk_fatura_vergi_dairesi', 'Vergi dairesi', setting('sgk_fatura_vergi_dairesi', 'Başkent')) ?>
        <?= $in('sgk_fatura_adres', 'Adres', setting('sgk_fatura_adres', 'Ziyabey Cad. No: 6 Balgat')) ?>
        <?= $in('sgk_fatura_ilce', 'İlçe', setting('sgk_fatura_ilce', 'Çankaya')) ?>
        <?= $in('sgk_fatura_il', 'İl', setting('sgk_fatura_il', 'Ankara')) ?>
      </div>
    </section>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>
  <?php
        return;
    }

    if ($tab === 'uts') {
        $token = gizli_ayar('uts_token');
        $ortam = uts_ortam(); ?>
  <form method="post" class="stack" style="gap:16px" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="tab" value="uts"><input type="hidden" name="action" value="uts_kaydet">
    <section class="card">
      <div class="card-head"><h2>ÜTS bağlantısı</h2></div>
      <p class="hint" style="margin-top:0">Sistem token'ı ÜTS'de (<b>utsuygulama.saglik.gov.tr</b>) firma yetkilisinin e-imza ya da mobil imzasıyla, <b>“Sistem Token'ı Üret”</b> seçeneğinden alınır. Token şifreli saklanır; ekranda yalnızca son 4 karakteri görünür.</p>
      <div class="grid cols-3">
        <label class="field"><span>Ortam</span><select name="uts_ortam"><?= select_options(UTS_ORTAMLAR, $ortam) ?></select></label>
        <label class="field"><span>ÜTS kurum numaranız</span><input name="uts_kurum_no" inputmode="numeric" maxlength="20" value="<?= e(uts_kurum_no()) ?>"></label>
        <label class="field"><span>Sistem token <?= $token !== '' ? '· kayıtlı ' . e(gizli_maske($token)) : '' ?></span><input name="uts_token" type="password" autocomplete="new-password" placeholder="<?= $token !== '' ? 'Değiştirmek için yazın' : 'Token\'ı yapıştırın' ?>"></label>
      </div>
      <?php if ($token !== ''): ?><label class="check"><input type="checkbox" name="uts_token_sil" value="1"> Kayıtlı token'ı sil</label><?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2>Gönderim</h2></div>
      <label class="check"><input type="radio" name="uts_gonderim" value="otomatik" <?= uts_gonderim_modu() === 'otomatik' ? 'checked' : '' ?>> <b>Otomatik</b> — ücretli satış teslim edilince "tüketiciye verme" bildirimi kendiliğinden gider.</label>
      <label class="check"><input type="radio" name="uts_gonderim" value="onayli" <?= uts_gonderim_modu() === 'onayli' ? 'checked' : '' ?>> <b>Onaylı</b> — bildirimler ÜTS › Bildirimler ekranında birikir; personel kontrol edip toplu gönderir.</label>
      <label class="check" style="margin-top:8px"><input type="checkbox" name="uts_ad_gonder" value="1" <?= setting('uts_ad_gonder', '0') === '1' ? 'checked' : '' ?>> Tüketiciye verme bildiriminde müşterinin adını ve soyadını da gönder <small class="muted">(optik ürünlerde genellikle gerekmez; ÜTS "kimlik bilgisi zorunlu" hatası verirse açın)</small></label>
      <p class="hint">SGK'lı satışlarda (e-reçete ya da SGK katkısı olan sipariş) ÜTS düşümünü Medula yapar; OptiFlow bu ürünler için bildirim göndermez.</p>
    </section>
    <details class="card">
      <summary class="card-head" style="cursor:pointer"><h2>Gelişmiş: servis adresleri</h2></summary>
      <p class="hint" style="margin-top:0">ÜTS web servis dokümanı güncellenirse buradan değiştirin. Boş bırakılan alan varsayılanı kullanır. Güvenlik gereği yalnızca <code>https://…saglik.gov.tr</code> adresleri kabul edilir.</p>
      <div class="grid cols-2">
        <?php foreach (['test', 'canli'] as $o): ?>
          <label class="field"><span><?= e(UTS_ORTAMLAR[$o]) ?> taban adresi</span><input name="uts_taban_<?= $o ?>" value="<?= e(setting('uts_taban_' . $o, '')) ?>" placeholder="<?= e(UTS_TABAN_VARSAYILAN[$o]) ?>"></label>
        <?php endforeach; ?>
        <?php foreach (array_merge(uts_turler(), uts_sorgular()) as $k => $tanim): ?>
          <label class="field"><span><?= e($tanim['ad']) ?></span><input name="uts_yol_<?= e($k) ?>" value="<?= e(setting('uts_yol_' . $k, '')) ?>" placeholder="<?= e($tanim['yol']) ?>"></label>
        <?php endforeach; ?>
      </div>
    </details>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>
  <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="tab" value="uts"><input type="hidden" name="action" value="uts_test"><button class="btn btn-sm">Bağlantıyı dene (kabul bekleyen ürünleri sorgular)</button></form>
  <?php
        return;
    }

    if ($tab === 'lens') { ?>
  <form method="post" class="card stack" style="gap:10px">
    <?= csrf_field() ?><input type="hidden" name="tab" value="lens"><input type="hidden" name="action" value="lens_kaydet">
    <div class="card-head"><h2>Kontakt lens</h2></div>
    <label class="field" style="max-width:260px"><span>Bitişten kaç gün önce hatırlatılsın</span><input name="lens_hatirlat_gun" type="number" min="1" max="60" value="<?= e((string) lens_hatirlatma_gun()) ?>"></label>
    <p class="hint">Lens satışları müşteri kartındaki “Kontakt lens” bölümünden girilir; bitişi yaklaşanlar Hatırlatmalar › Kontakt lens sekmesinde listelenir.</p>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>
  <?php }
}

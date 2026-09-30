<?php
/* Atölye ekranı — görünüm şablonu. Değişkenleri app/pages/ekran.php hazırlar.
   Ekranda fiyat, telefon veya reçete GÖSTERİLMEZ; yalnızca sipariş no, ad baş harfleri, aşama ve teslim günü. */
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#120c0a">
<title>Atölye ekranı · <?= e($shop) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('ekran.css')) ?>">
</head>
<body class="ek">
<div id="ekroot" class="ek-root">

  <header class="ek-top">
    <div class="ek-brand"><b><?= e($shop) ?></b><small>Atölye ekranı</small></div>
    <div class="ek-side">
      <div class="ek-tools">
        <button type="button" class="ek-btn" data-eylem="isim">Adları gizle</button>
        <button type="button" class="ek-btn" data-eylem="tamekran">Tam ekran</button>
      </div>
      <div class="ek-clock"><b id="eksaat"><?= e($saat) ?></b><span><?= e($tarihMetni) ?></span></div>
    </div>
  </header>

  <?php if ($hata !== ''): ?>
    <div class="ek-warn"><?= e($hata) ?></div>
  <?php endif; ?>

  <section class="ek-stats">
    <?php foreach ($ozet as $z): ?>
      <div class="ek-stat <?= e($z['sinif']) ?>"><b><?= e($z['deger']) ?></b><span><?= e($z['etiket']) ?></span></div>
    <?php endforeach; ?>
  </section>

  <section class="ek-cols">
    <?php foreach ($kolonlar as $k): ?>
      <div class="ek-col ek-<?= e($k['ton']) ?>">
        <div class="ek-col-head"><h2><?= e($k['baslik']) ?></h2><span class="ek-count"><?= e($k['sayi']) ?></span></div>
        <ul class="ek-rows" data-total="<?= e($k['sayi']) ?>">
          <?php if ($k['sayi'] === 0): ?>
            <li class="ek-empty">Bekleyen yok</li>
          <?php endif; ?>
          <?php foreach ($k['satirlar'] as $s): ?>
            <li class="ek-row">
              <span class="ek-no"><?= e($s['no']) ?></span>
              <span class="ek-who">
                <?php if ($s['ad'] !== ''): ?><b><?= e($s['ad']) ?></b><?php endif; ?>
                <?php if ($s['alt'] !== '' || $s['vade'] !== ''): ?><span class="ek-meta"><?php if ($s['alt'] !== ''): ?><small><?= e($s['alt']) ?></small><?php endif; ?><?php if ($s['vade'] !== ''): ?><span class="ek-due <?= e($s['vsinif']) ?>"><?= e($s['vade']) ?></span><?php endif; ?></span><?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
          <li class="ek-more" hidden></li>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>

  <footer class="ek-foot">
    <span><?= e($tamirSatir) ?></span>
    <span>Son güncelleme <?= e($guncelleme) ?></span>
  </footer>

</div>
<script>
(function () {
  var kok = document.getElementById('ekroot');
  var hata = 0;
  function saat() {
    var d = new Date();
    var s = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    var el = document.getElementById('eksaat');
    if (el) { el.textContent = s; }
  }
  setInterval(saat, 1000);

  /* Tercihler bu bilgisayarın tarayıcısında hatırlanır (sunucuya kaydedilmez) */
  function uygula() {
    var gizli = false;
    try { gizli = localStorage.getItem('ekranAdGizle') === '1'; } catch (x) {}
    document.body.classList.toggle('gizli-ad', gizli);
    var b1 = document.querySelector('[data-eylem="isim"]');
    if (b1) { b1.textContent = gizli ? 'Adları göster' : 'Adları gizle'; }
    var b2 = document.querySelector('[data-eylem="tamekran"]');
    if (b2) {
      if (!document.documentElement.requestFullscreen) { b2.hidden = true; }
      else { b2.textContent = document.fullscreenElement ? 'Tam ekrandan çık' : 'Tam ekran'; }
    }
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('[data-eylem]') : null;
    if (!b) { return; }
    if (b.getAttribute('data-eylem') === 'isim') {
      var gizli = document.body.classList.contains('gizli-ad');
      try { localStorage.setItem('ekranAdGizle', gizli ? '0' : '1'); } catch (x) {}
      uygula();
      sigdir();
    } else if (document.fullscreenElement) {
      document.exitFullscreen();
    } else {
      var r = document.documentElement.requestFullscreen();
      if (r && r.catch) { r.catch(function () {}); }
    }
  });
  document.addEventListener('fullscreenchange', function () { uygula(); sigdir(); });

  /* Oturum kapanırsa (ör. sunucu yeniden başladı) sessizce boş kalmasın */
  function oturumKapandi() {
    if (document.getElementById('ekoturum')) { return; }
    var d = document.createElement('div');
    d.id = 'ekoturum';
    d.className = 'ek-oturum';
    d.innerHTML = '<b>Oturum kapandı.</b> Canlı liste için <a href="login.php">yeniden giriş yapın</a>, sonra ekranı yeniden açın.';
    document.body.appendChild(d);
  }

  /* Sütuna sığmayan satırları gizler, doğru sayıyla "+N sipariş daha" yazar (kırpılan satır sessizce kaybolmasın) */
  function sigdir() {
    var uller = document.querySelectorAll('.ek-rows');
    for (var u = 0; u < uller.length; u++) {
      var ul = uller[u];
      var rows = ul.querySelectorAll('.ek-row');
      var more = ul.querySelector('.ek-more');
      var toplam = parseInt(ul.getAttribute('data-total') || '0', 10);
      var i;
      for (i = 0; i < rows.length; i++) { rows[i].style.display = ''; }
      if (more) { more.hidden = true; }
      var gorunen = rows.length;
      while (gorunen > 0 && ul.scrollHeight > ul.clientHeight + 1) {
        gorunen--;
        rows[gorunen].style.display = 'none';
      }
      if (more && toplam > gorunen && toplam > 0) {
        more.textContent = '+ ' + (toplam - gorunen) + ' sipariş daha';
        more.hidden = false;
        while (gorunen > 0 && ul.scrollHeight > ul.clientHeight + 1) {
          gorunen--;
          rows[gorunen].style.display = 'none';
          more.textContent = '+ ' + (toplam - gorunen) + ' sipariş daha';
        }
      }
    }
  }
  window.addEventListener('resize', sigdir);
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(sigdir); }
  uygula();
  sigdir();

  /* 45 saniyede bir yalnızca içeriği tazeler (sayfa yanıp sönmez); art arda 3 hata olursa uyarı gösterir */
  function yenile() {
    fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) {
        if (r.redirected && /login/i.test(r.url)) { oturumKapandi(); throw 1; }
        if (!r.ok) { throw 0; }
        return r.text();
      })
      .then(function (t) {
        var d = new DOMParser().parseFromString(t, 'text/html');
        var y = d.getElementById('ekroot');
        if (!y) { throw 0; }
        kok.innerHTML = y.innerHTML;
        uygula();
        sigdir();
        hata = 0;
        kok.classList.remove('is-offline');
      })
      .catch(function (e) { if (e === 1) { return; } hata++; if (hata >= 3) { kok.classList.add('is-offline'); } });
  }
  setInterval(yenile, 45000);

  /* Bellek ve stil güncellemeleri için saatte bir tam yenileme */
  setTimeout(function () { location.reload(); }, 3600000);
})();
</script>
</body>
</html>

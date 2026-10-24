/* 4.53.0 — Ayarlar › Çıktı görünümü: seçim değiştikçe örneği yeniler, "Karıştır" rastgele bir görünüm önerir.
   Önizleme cikti-onizle.php?… (kaydetmez); çerçeve genişliğe göre ölçeklenir. */
(function () {
  'use strict';
  var form = document.getElementById('cikti-form');
  var iframe = document.getElementById('ck-iframe');
  if (!form || !iframe) { return; }
  var tur = 'fis';
  var zamanlayici = null;

  function olcekle() {
    var kap = iframe.parentNode;
    var o = kap.clientWidth / 820;
    iframe.style.transform = 'scale(' + o + ')';
    kap.style.height = Math.round((tur === 'fis' ? 1150 : 1160) * o) + 'px';
  }

  function adres() {
    var p = new URLSearchParams();
    ['renk', 'baslik', 'yazi', 'kose', 'desen', 'isaret', 'musteri', 'atolye'].forEach(function (ad) {
      var s = form.querySelector('input[name="' + ad + '"]:checked');
      if (s) { p.set(ad, s.value); }
    });
    ['slogan', 'alt_yazi'].forEach(function (ad) {
      var el = form.querySelector('[name="' + ad + '"]');
      if (el) { p.set(ad, el.value); }
    });
    var t = form.querySelector('input[name="dokum_tema"]:checked');
    if (t) { p.set('tema', t.value); }
    p.set('tur', tur);
    return form.getAttribute('data-onizle') + '?' + p.toString();
  }

  function yenile(hemen) {
    clearTimeout(zamanlayici);
    zamanlayici = setTimeout(function () { iframe.src = adres(); olcekle(); }, hemen ? 0 : 280);
  }

  form.addEventListener('change', function () { yenile(true); });
  form.addEventListener('input', function (e) { if (e.target && e.target.type === 'text') { yenile(false); } });

  document.querySelectorAll('[data-ck-tur]').forEach(function (b) {
    b.addEventListener('click', function () {
      tur = b.getAttribute('data-ck-tur');
      document.querySelectorAll('[data-ck-tur]').forEach(function (x) { x.classList.toggle('on', x === b); });
      yenile(true);
    });
  });

  var karistir = form.querySelector('[data-ck-karistir]');
  if (karistir) {
    karistir.addEventListener('click', function () {
      ['renk', 'baslik', 'yazi', 'kose', 'desen', 'isaret', 'musteri', 'atolye', 'dokum_tema'].forEach(function (ad) {
        var hepsi = Array.prototype.slice.call(form.querySelectorAll('input[name="' + ad + '"]'));
        // "Mağaza rengim" ve "Tasarımın kendi yazısı" öneriye girmez: amaç başkalarından ayrışmak
        if (ad === 'renk' || ad === 'yazi') { hepsi = hepsi.slice(1); }
        if (hepsi.length) { hepsi[Math.floor(Math.random() * hepsi.length)].checked = true; }
      });
      yenile(true);
    });
  }

  window.addEventListener('resize', olcekle);
  olcekle();
})();

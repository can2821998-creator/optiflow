(function () {
  var p = document.querySelector('[data-print]');
  var c = document.querySelector('[data-close]');
  if (p) p.addEventListener('click', function () { window.print(); });
  // 4.33.0 tezgâh kaydından gelen fiş: yazdırma penceresi kendiliğinden açılır
  if (document.body && document.body.hasAttribute('data-otomatik-yazdir')) {
    var yazdir = function () { setTimeout(function () { window.print(); }, 250); };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(yazdir, yazdir); else window.addEventListener('load', yazdir);
  }
  if (c) c.addEventListener('click', function () { if (window.opener || history.length <= 1) window.close(); else history.back(); });
})();

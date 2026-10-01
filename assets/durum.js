/* OptiFlow — müşteri sipariş durum sayfası (4.15.1: satır içi betikten taşındı; CSP script-src 'self'). */
(function () {
  'use strict';
  /* Sipariş numarasını kopyala */
  document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('[data-kopyala]') : null;
    if (!b) { return; }
    var metin = b.getAttribute('data-kopyala') || '';
    var etiket = b.querySelector('span');
    function bitti() {
      b.classList.add('is-ok');
      if (etiket) { etiket.textContent = 'Kopyalandı'; }
      setTimeout(function () { b.classList.remove('is-ok'); if (etiket) { etiket.textContent = 'Kopyala'; } }, 1800);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(metin).then(bitti, function () {});
    } else {
      var t = document.createElement('textarea');
      t.value = metin; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { if (document.execCommand('copy')) { bitti(); } } catch (x) {}
      document.body.removeChild(t);
    }
  });
  /* Hazır değilse sayfa 2 dakikada bir kendini tazeler; yalnızca ekran açıkken */
  if (document.body && document.body.getAttribute('data-yenile') === '1') {
    var acildi = Date.now();
    setInterval(function () { if (!document.hidden) { location.reload(); } }, 120000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && Date.now() - acildi > 120000) { location.reload(); }
    });
  }
})();

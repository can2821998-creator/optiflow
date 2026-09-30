/* OptiFlow 4.12.0 — yeni modüllerin ortak küçük davranışları (CSP: satır içi betik yok). */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest) return;
    var yaz = t.closest('[data-yazdir]');
    if (yaz) { e.preventDefault(); window.print(); return; }
    var kop = t.closest('[data-kopyala]');
    if (kop && typeof kop.select === 'function') { kop.select(); }
  });
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches && t.matches('[data-auto-submit]') && t.form) { t.form.submit(); return; }
    if (!t.matches || !t.matches('[data-hepsini-sec]')) return;
    var ad = t.getAttribute('data-hepsini-sec');
    var form = t.closest('form') || document;
    form.querySelectorAll('input[type="checkbox"]').forEach(function (c) {
      if (c.name === ad) c.checked = t.checked;
    });
  });
})();

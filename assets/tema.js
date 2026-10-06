/* 4.20.0 — Açık / koyu görünüm.
   <head> içinde ERTELENMEDEN yüklenir: sayfa çizilmeden önce <html data-tema="…"> yazılır, beyaz parlama olmaz.
   Seçim bu cihazda saklanır (localStorage "of-tema": "acik" | "koyu"; yoksa işletim sistemi teması izlenir).
   Düğme ([data-tema-dugme]) sırayla: otomatik → koyu → açık → otomatik. */
(function () {
  var ANAHTAR = 'of-tema';
  var kok = document.documentElement;

  function oku() {
    try {
      var v = localStorage.getItem(ANAHTAR);
      return v === 'acik' || v === 'koyu' ? v : '';
    } catch (e) {
      return '';
    }
  }
  function sistemKoyu() {
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }
  function uygula(secim) {
    if (secim) kok.setAttribute('data-tema', secim);
    else kok.removeAttribute('data-tema');
    var koyu = secim ? secim === 'koyu' : sistemKoyu();
    kok.classList.toggle('tema-koyu', koyu);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', '#141012');
    var ad = secim === 'koyu' ? 'Koyu' : secim === 'acik' ? 'Açık' : 'Otomatik (' + (koyu ? 'koyu' : 'açık') + ')';
    var dugmeler = document.querySelectorAll('[data-tema-dugme]');
    for (var i = 0; i < dugmeler.length; i++) {
      dugmeler[i].setAttribute('title', 'Görünüm: ' + ad + ' — değiştirmek için tıklayın');
      dugmeler[i].setAttribute('aria-label', 'Görünüm: ' + ad);
    }
  }

  uygula(oku());

  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var dinle = function () { if (!oku()) uygula(''); };
    if (mq.addEventListener) mq.addEventListener('change', dinle);
    else if (mq.addListener) mq.addListener(dinle);
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    var d = t && t.closest ? t.closest('[data-tema-dugme]') : null;
    if (!d) return;
    var simdi = oku();
    var sonraki = simdi === '' ? 'koyu' : simdi === 'koyu' ? 'acik' : '';
    try {
      if (sonraki) localStorage.setItem(ANAHTAR, sonraki);
      else localStorage.removeItem(ANAHTAR);
    } catch (err) { /* saklanamazsa yalnızca bu sayfada geçerli */ }
    uygula(sonraki);
  });

  document.addEventListener('DOMContentLoaded', function () { uygula(oku()); });
})();

/* 4.20.0 — Açık / koyu görünüm.
   <head> içinde ERTELENMEDEN yüklenir: sayfa çizilmeden önce <html data-tema="…"> yazılır, beyaz parlama olmaz.
   Seçim bu cihazda saklanır (localStorage "of-tema": "koyu" | "oto"; yoksa AÇIK — 4.43.0'dan beri varsayılan açık).
   Eski "acik" kaydı da açık sayılır. Düğme ([data-tema-dugme]) sırayla: açık → koyu → otomatik → açık. */
(function () {
  var ANAHTAR = 'of-tema';
  var kok = document.documentElement;

  function oku() {
    try {
      var v = localStorage.getItem(ANAHTAR);
      return v === 'koyu' || v === 'oto' ? v : 'acik';
    } catch (e) {
      return 'acik';
    }
  }
  function sistemKoyu() {
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }
  function uygula(secim) {
    if (secim !== 'oto') kok.setAttribute('data-tema', secim);
    else kok.removeAttribute('data-tema');
    var koyu = secim === 'oto' ? sistemKoyu() : secim === 'koyu';
    kok.classList.toggle('tema-koyu', koyu);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', koyu ? '#0a0708' : '#140e10');   // 4.46.0 üst çubuk her iki görünümde gece
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
    var dinle = function () { if (oku() === 'oto') uygula('oto'); };
    if (mq.addEventListener) mq.addEventListener('change', dinle);
    else if (mq.addListener) mq.addListener(dinle);
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    var d = t && t.closest ? t.closest('[data-tema-dugme]') : null;
    if (!d) return;
    var simdi = oku();
    var sonraki = simdi === 'acik' ? 'koyu' : simdi === 'koyu' ? 'oto' : 'acik';
    try {
      if (sonraki !== 'acik') localStorage.setItem(ANAHTAR, sonraki);
      else localStorage.removeItem(ANAHTAR);
    } catch (err) { /* saklanamazsa yalnızca bu sayfada geçerli */ }
    uygula(sonraki);
  });

  document.addEventListener('DOMContentLoaded', function () { uygula(oku()); });
})();

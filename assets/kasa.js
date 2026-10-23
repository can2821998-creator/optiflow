/* 4.45.0 — Gün sonu küpür sayımı: adetler yazıldıkça toplam "Saydığınız nakit" kutusuna yazılır (kâğıt-hesap makinesi yerine).
   Küpür girilmemişse kutuya elle yazılan tutar korunur. Hesap kuruş cinsinden (yuvarlama hatası olmaz). */
(function () {
  'use strict';
  var form = document.querySelector('[data-kupur-form]');
  if (!form) return;
  var toplamKutu = form.querySelector('[data-kupur-toplam]');
  var kupurler = form.querySelectorAll('[data-kupur]');

  function yaz(kurus) {
    var tl = Math.floor(kurus / 100);
    var kr = String(kurus % 100).padStart(2, '0');
    return String(tl).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + kr;
  }

  function hesapla() {
    var kurus = 0;
    var dolu = false;
    for (var i = 0; i < kupurler.length; i++) {
      var n = parseInt(kupurler[i].value, 10);
      if (n > 0) {
        dolu = true;
        kurus += n * Math.round(parseFloat(kupurler[i].getAttribute('data-kupur')) * 100);
      }
    }
    if (dolu) toplamKutu.value = yaz(kurus);
  }

  form.addEventListener('input', function (e) {
    if (e.target && e.target.hasAttribute('data-kupur')) hesapla();
  });
})();

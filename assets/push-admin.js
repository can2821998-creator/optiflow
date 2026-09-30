/* Bildirim yazarken canlı önizleme */
(function () {
  'use strict';
  var form = document.getElementById('push-form');
  if (!form) return;
  var varsayilan = {
    title: (document.querySelector('[data-push-out="title"]') || {}).textContent || '',
    body: 'Alt başlık burada görünür.',
  };
  function bagla(alan) {
    var girdi = form.querySelector('[data-push-preview="' + alan + '"]');
    var cikti = document.querySelector('[data-push-out="' + alan + '"]');
    if (!girdi || !cikti) return;
    function yenile() {
      var v = girdi.value.trim();
      cikti.textContent = v !== '' ? v : varsayilan[alan];
      cikti.classList.toggle('is-empty', v === '');
    }
    girdi.addEventListener('input', yenile);
    yenile();
  }
  bagla('title');
  bagla('body');
})();

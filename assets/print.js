(function () {
  var p = document.querySelector('[data-print]');
  var c = document.querySelector('[data-close]');
  if (p) p.addEventListener('click', function () { window.print(); });
  if (c) c.addEventListener('click', function () { if (window.opener || history.length <= 1) window.close(); else history.back(); });
})();

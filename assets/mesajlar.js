/* OptiFlow 4.12.0 — WhatsApp kuyruğu: "WhatsApp'ta aç" tıklanınca mesaj gönderildi olarak işaretlenir. */
(function () {
  'use strict';
  var csrfEl = document.querySelector('input[name="csrf"]');
  if (!csrfEl) return;
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-wa-id]') : null;
    if (!a) return;
    var id = a.getAttribute('data-wa-id');
    var govde = new URLSearchParams();
    govde.set('eylem', 'gonderildi');
    govde.set('id', id);
    govde.set('csrf', csrfEl.value);
    fetch('mesajlar.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch', 'Content-Type': 'application/x-www-form-urlencoded' },
      body: govde.toString()
    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (!j || !j.ok) return;
      var satir = document.querySelector('tr[data-wa-satir="' + id + '"]');
      if (satir) {
        satir.style.opacity = '.45';
        var rozet = satir.querySelector('.badge');
        if (rozet) { rozet.textContent = 'Gönderildi'; rozet.className = 'badge tone-green'; }
      }
    }).catch(function () {});
  });
})();

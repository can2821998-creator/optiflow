(function () {
  'use strict';
  var pano = document.querySelector('[data-pano]');
  if (!pano) { return; }

  var dragged = null;

  pano.querySelectorAll('.drag-handle').forEach(function (handle) {
    var card = handle.closest('.pano-card');
    if (!card) { return; }
    handle.addEventListener('dragstart', function (e) {
      dragged = card;
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', card.dataset.orderId || ''); } catch (err) {}
    });
    handle.addEventListener('dragend', function () {
      card.classList.remove('is-dragging');
      dragged = null;
      pano.querySelectorAll('[data-drop-zone]').forEach(function (z) { z.classList.remove('drop-ok', 'drop-no'); });
    });
  });

  // Bir kartın hangi hedeflere bırakılabileceği: from -> {target: buton value}
  var allowed = {
    montajda: { kontrol: 'kontrol' },
    kontrol: { montajda: 'montajda', hazir: 'hazir' }
  };

  pano.querySelectorAll('[data-drop-zone]').forEach(function (zone) {
    var target = zone.dataset.dropZone;

    zone.addEventListener('dragover', function (e) {
      if (!dragged) { return; }
      var from = dragged.dataset.from;
      var map = allowed[from] || {};
      if (map[target]) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        zone.classList.add('drop-ok');
        zone.classList.remove('drop-no');
      } else if (from !== target) {
        zone.classList.add('drop-no');
        zone.classList.remove('drop-ok');
      }
    });
    zone.addEventListener('dragleave', function () {
      zone.classList.remove('drop-ok', 'drop-no');
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('drop-ok', 'drop-no');
      if (!dragged) { return; }
      var from = dragged.dataset.from;
      var map = allowed[from] || {};
      var toValue = map[target];
      if (!toValue) { return; }

      // 4.44.0: kartta gizli form yok; aşama formu burada kurulur. Hazır'a bırakmak kontrol parafıdır ve
      // kutu / raf ister: kartın kutu kutusu boşsa ona odaklanılır, doluysa kartın "Kontrol tamam" formu gönderilir.
      if (toValue === 'hazir') {
        var adimFormu = dragged.querySelector('form input[name="adim"][value="kontrol"]');
        adimFormu = adimFormu ? adimFormu.form : null;
        if (!adimFormu) { return; }
        var raf = adimFormu.querySelector('input[name="raf_yeri"]');
        if (raf && !raf.value.trim()) { raf.focus(); raf.reportValidity && raf.reportValidity(); return; }
        dragged.classList.add('pano-card-celebrate');
        setTimeout(function () { adimFormu.submit(); }, 420);
        return;
      }
      var csrf = dragged.querySelector('input[name="csrf"]');
      if (!csrf) { return; }
      var form = document.createElement('form');
      form.method = 'post';
      form.action = 'order.php';
      [[csrf.name, csrf.value], ['order_id', dragged.dataset.orderId], ['action', 'workshop_move'], ['return', 'pano'], ['to', toValue]].forEach(function (p) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = p[0]; i.value = p[1];
        form.appendChild(i);
      });
      document.body.appendChild(form);
      form.submit();
    });
  });
})();

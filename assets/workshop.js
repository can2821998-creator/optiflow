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

      var form = dragged.querySelector('form[data-form="move"]');
      if (!form) { return; }
      var btn = form.querySelector('button[name="to"][value="' + toValue + '"]');
      if (!btn) { return; }

      if (toValue === 'hazir') {
        dragged.classList.add('pano-card-celebrate');
        setTimeout(function () { btn.click(); }, 420);
      } else {
        btn.click();
      }
    });
  });
})();

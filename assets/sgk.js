/* Köprü adresi / anahtarı kutularına tıklayınca panoya kopyala */
(function () {
  'use strict';
  document.querySelectorAll('[data-kopya]').forEach(function (alan) {
    alan.style.cursor = 'pointer';
    alan.title = 'Kopyalamak için tıklayın';
    alan.addEventListener('click', function () {
      alan.select();
      var bitir = function () {
        var etiket = alan.closest('.field') && alan.closest('.field').querySelector('span');
        if (!etiket || etiket.dataset.eski) return;
        etiket.dataset.eski = etiket.textContent;
        etiket.textContent = etiket.dataset.eski + ' · kopyalandı ✓';
        setTimeout(function () { etiket.textContent = etiket.dataset.eski; delete etiket.dataset.eski; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(alan.value).then(bitir, function () {});
      } else {
        try { document.execCommand('copy'); bitir(); } catch (e) { /* yoksay */ }
      }
    });
  });
})();

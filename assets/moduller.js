/* OptiFlow 4.12.0 — yeni modüllerin ortak küçük davranışları (CSP: satır içi betik yok). */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest) return;
    var yaz = t.closest('[data-yazdir]');
    if (yaz) { e.preventDefault(); window.print(); return; }
    var onay = t.closest('button[data-onay]');
    if (onay && !window.confirm(onay.getAttribute('data-onay'))) { e.preventDefault(); return; }
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

/* 4.15.1 — satır içi betiklerden taşınanlar (CSP script-src 'self' bunları engelliyordu). */
(function () {
  'use strict';
  /* Teslimat / tedarikçi: fatura formunda kalem maliyetlerinin toplamı */
  document.querySelectorAll('[data-delivery-form]').forEach(function (form) {
    var total = form.querySelector('[data-cost-total]');
    if (!total) return;
    function recalc() {
      var sum = 0;
      form.querySelectorAll('[data-cost-input]').forEach(function (inp) {
        var v = parseFloat((inp.value || '0').replace(/\./g, '').replace(',', '.'));
        if (!isNaN(v)) sum += v;
      });
      total.textContent = sum.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    }
    form.querySelectorAll('[data-cost-input]').forEach(function (inp) { inp.addEventListener('input', recalc); });
  });

  /* Yeni sipariş: işlem türüne göre alanlar, ücretsiz tamirde tutar */
  var form = document.querySelector('.form-layout');
  if (!form || !form.querySelector('input[name="transaction_type"]')) return;
  function apply() {
    var checked = form.querySelector('input[name="transaction_type"]:checked');
    var type = checked ? checked.value : 'gozluk';
    form.querySelectorAll('.chip').forEach(function (chip) {
      var radio = chip.querySelector('input[name="transaction_type"]');
      if (radio) chip.classList.toggle('active', radio.checked);
    });
    form.querySelectorAll('[data-show-for]').forEach(function (el) {
      var types = el.getAttribute('data-show-for').split(',');
      el.hidden = types.indexOf(type) === -1;
    });
    var amountField = form.querySelector('[data-amount-field]');
    var freeToggle = form.querySelector('[data-free-toggle]');
    var amountReq = form.querySelector('[data-amount-required]');
    var isFree = type === 'tamir' && freeToggle && freeToggle.checked;
    if (amountField) {
      amountField.disabled = isFree;
      amountField.required = !isFree;
      if (isFree) amountField.value = '0';
    }
    if (amountReq) amountReq.hidden = isFree;
  }
  form.querySelectorAll('input[name="transaction_type"]').forEach(function (r) { r.addEventListener('change', apply); });
  form.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () { setTimeout(apply, 0); });
  });
  var freeToggle = form.querySelector('[data-free-toggle]');
  if (freeToggle) freeToggle.addEventListener('change', apply);
  apply();
})();

/* 4.21.0 — Yeni teklif ekranı (teklif-yeni.php): canlı hesap.
   Hesap app/teklif.php teklif_hesapla() ile aynıdır (önce SGK, sonra iskonto); sunucu kaydederken yeniden hesaplar.
   Kataloğdan cam seçilince fiyat ve SGK kullanım şekli önerilir; elle değiştirilen alana bir daha dokunulmaz. */
(function () {
  'use strict';
  var form = document.querySelector('[data-teklif]');
  if (!form) return;

  var sgkTahmin = {};
  try { sgkTahmin = JSON.parse(form.getAttribute('data-sgk-tahmin') || '{}'); } catch (e) { sgkTahmin = {}; }
  var iskontoMax = parseFloat(form.getAttribute('data-iskonto-max') || '100');

  function sayi(ham) {
    // parse_money (helpers.php) ile aynı kural: "1.250,50" · "1250.5" · "1.250" · "12,5"
    var s = String(ham || '').replace(/[\s₺]|TL/gi, '');
    if (s === '') return null;
    if (!/^-?[0-9.,]+$/.test(s)) return NaN;
    var v = s.lastIndexOf(','), n = s.lastIndexOf('.');
    if (v !== -1 && n !== -1) {
      s = v > n ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    } else if (v !== -1) {
      s = (s.split(',').length > 2) ? s.replace(/,/g, '') : s.replace(',', '.');
    } else if (n !== -1 && (s.split('.').length > 2 || /\.\d{3}$/.test(s))) {
      s = s.replace(/\./g, '');
    }
    var x = parseFloat(s);
    return isNaN(x) ? NaN : Math.round(x * 100) / 100;
  }
  function para(x) {
    return x.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  }
  function yuvarla(x) { return Math.round(x * 100) / 100; }
  function hesapla(cam, cerceve, sgk, oran) {
    var ara = yuvarla(Math.max(0, cam) + Math.max(0, cerceve));
    sgk = yuvarla(Math.min(Math.max(0, sgk), ara));
    var kalan = yuvarla(ara - sgk);
    oran = Math.min(Math.max(0, oran), 100);
    var iskonto = yuvarla(kalan * oran / 100);
    return { ara: ara, sgk: sgk, kalan: kalan, oran: oran, iskonto: iskonto, odenecek: yuvarla(kalan - iskonto) };
  }

  var dokunuldu = new WeakSet();
  form.addEventListener('input', function (e) { if (e.isTrusted) dokunuldu.add(e.target); });

  /* --- Çerçeve türü --- */
  function cerceveTuru() {
    var r = form.querySelector('input[name="cerceve_tur"]:checked');
    return r ? r.value : 'stok';
  }
  function cerceveGoster() {
    var tur = cerceveTuru();
    form.querySelectorAll('[data-cerceve]').forEach(function (el) {
      el.hidden = el.getAttribute('data-cerceve').split(',').indexOf(tur) === -1;
    });
    form.querySelectorAll('input[name="cerceve_tur"]').forEach(function (r) {
      r.closest('.chip').classList.toggle('active', r.checked);
    });
  }
  var cerceveSec = form.querySelector('[data-cerceve-sec]');
  var cerceveFiyat = form.querySelector('[data-cerceve-fiyat]');
  if (cerceveSec) {
    cerceveSec.addEventListener('change', function () {
      var o = cerceveSec.selectedOptions[0];
      if (o && o.value && !dokunuldu.has(cerceveFiyat)) {
        var f = parseFloat(o.getAttribute('data-fiyat') || '0');
        cerceveFiyat.value = f > 0 ? f.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) : '';
      }
      guncelle();
    });
  }

  /* --- Cam seçenekleri --- */
  var kullanim = form.querySelector('[data-kullanim]');
  var oneriler = { progressive: 'progressive', ofis: 'ofis', bifokal: 'bifokal', tek_odak: 'tek_odak_uzak' };
  form.querySelectorAll('[data-secenek]').forEach(function (kutu) {
    var sec = kutu.querySelector('[data-cam-sec]');
    var fiyat = kutu.querySelector('[data-cam-fiyat]');
    var baslik = kutu.querySelector('input[name^="secenek_ad"]');
    sec.addEventListener('change', function () {
      var o = sec.selectedOptions[0];
      // Katalogda fiyatı olmayan cam: fiyat elle yazılmalı (sunucu da boş fiyatı reddeder)
      var fiyatsiz = !!(o && o.value && !o.getAttribute('data-fiyat'));
      fiyat.required = fiyatsiz;
      fiyat.placeholder = fiyatsiz ? 'Fiyat sorulur — yazın' : 'Katalog fiyatı';
      if (o && o.value) {
        if (!dokunuldu.has(fiyat)) {
          var f = o.getAttribute('data-fiyat');
          fiyat.value = f ? parseFloat(f).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) : '';
        }
        if (!dokunuldu.has(baslik) && o.getAttribute('data-segment')) baslik.placeholder = o.getAttribute('data-segment');
        // İlk seçeneğin tasarımı SGK kullanım şeklini önerir (elle seçilmediyse)
        if (kutu.getAttribute('data-secenek') === '1' && kullanim && !dokunuldu.has(kullanim)) {
          var k = oneriler[o.getAttribute('data-tasarim')] || 'tek_odak_uzak';
          if (kullanim.querySelector('option[value="' + k + '"]')) kullanim.value = k;
        }
      }
      guncelle();
    });
  });
  if (kullanim) kullanim.addEventListener('change', function () { dokunuldu.add(kullanim); guncelle(); });

  /* --- SGK ve iskonto --- */
  var sgkVar = form.querySelector('[data-sgk-var]');
  var sgkTutar = form.querySelector('[data-sgk-tutar]');
  var sgkAlan = form.querySelector('[data-sgk-alan]');
  var iskonto = form.querySelector('[data-iskonto]');

  function sgkDegeri() {
    if (!sgkVar || !sgkVar.checked) return 0;
    var elle = sayi(sgkTutar.value);
    if (elle !== null && !isNaN(elle)) return elle;
    return parseFloat(sgkTahmin[kullanim ? kullanim.value : 'tek_odak_uzak'] || 0);
  }

  /* --- Özet tablosu --- */
  var ozet = form.querySelector('[data-ozet]');
  function hucre(tr, metin, sinif) {
    var td = document.createElement('td');
    if (sinif) td.className = sinif;
    td.textContent = metin;
    tr.appendChild(td);
    return td;
  }
  function guncelle() {
    cerceveGoster();
    if (sgkAlan) sgkAlan.hidden = !(sgkVar && sgkVar.checked);
    var tahmin = parseFloat(sgkTahmin[kullanim ? kullanim.value : 'tek_odak_uzak'] || 0);
    if (sgkTutar) sgkTutar.placeholder = 'Tahmin: ' + para(tahmin);

    var oran = sayi(iskonto.value);
    var oranHata = oran !== null && (isNaN(oran) || oran < 0 || oran > iskontoMax);
    iskonto.setCustomValidity(oranHata ? (iskontoMax >= 100 ? 'İskonto 0–100 arasında olmalı.' : 'İskonto yetkiniz en çok %' + iskontoMax.toLocaleString('tr-TR') + '.') : '');
    iskonto.classList.toggle('is-invalid', oranHata);
    if (oran === null || isNaN(oran)) oran = 0;

    var cerceve = cerceveTuru() === 'kendi' ? 0 : (sayi(cerceveFiyat.value) || 0);
    var sgk = sgkDegeri();
    ozet.textContent = '';
    var adet = 0;
    form.querySelectorAll('[data-secenek]').forEach(function (kutu) {
      var sec = kutu.querySelector('[data-cam-sec]');
      if (!sec.value) return;
      adet++;
      var cam = sayi(kutu.querySelector('[data-cam-fiyat]').value) || 0;
      var h = hesapla(cam, cerceve, sgk, oran);
      var baslik = kutu.querySelector('input[name^="secenek_ad"]');
      var tr = document.createElement('tr');
      var td = hucre(tr, '');
      var b = document.createElement('b');
      b.textContent = (baslik.value || baslik.placeholder);
      td.appendChild(b);
      var sm = document.createElement('small');
      sm.className = 'block muted';
      sm.textContent = sec.selectedOptions[0].textContent.split(' — ')[0];
      td.appendChild(sm);
      hucre(tr, para(h.ara), 'num');
      hucre(tr, h.sgk > 0 ? '−' + para(h.sgk) : '—', 'num');
      hucre(tr, h.iskonto > 0 ? '−' + para(h.iskonto) + ' (%' + h.oran.toLocaleString('tr-TR') + ')' : '—', 'num');
      var son = hucre(tr, '', 'num');
      var bb = document.createElement('b');
      bb.textContent = para(h.odenecek);
      son.appendChild(bb);
      ozet.appendChild(tr);
    });
    if (!adet) {
      var tr = document.createElement('tr');
      var td = hucre(tr, 'Cam seçince hesap burada görünür.', 'muted');
      td.colSpan = 5;
      ozet.appendChild(tr);
    }
  }

  form.addEventListener('input', guncelle);
  form.addEventListener('change', guncelle);
  guncelle();
})();

/* Reçete formu davranışları + optik öneri asistanı */
(function () {
  'use strict';
  var form = document.getElementById('rx-form');
  if (!form) return;

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var field = function (name) { return form.elements[name]; };
  var num = function (name) {
    var el = field(name);
    if (!el) return NaN;
    var s = String(el.value || '').replace(',', '.').replace(/\s/g, '');
    return s === '' ? NaN : parseFloat(s);
  };
  var fmtD = function (v) {
    if (isNaN(v)) return '';
    if (Math.abs(v) < 0.001) return '0.00';
    return (v > 0 ? '+' : '-') + Math.abs(v).toFixed(2);
  };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var money = function (v) {
    return v.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  };

  /* ------------------------------------------------------------------ */
  /*  Form davranışları                                                  */
  /* ------------------------------------------------------------------ */

  var designSel = $('[data-design]', form);
  var eyesSel = $('[data-eyes]', form);
  var NEAR = ['ayri_uzak_yakin', 'tek_odak_yakin'];
  var MULTI = ['progressive', 'ofis', 'bifokal'];

  function applyDesign() {
    var d = designSel.value;
    var near = NEAR.indexOf(d) >= 0;
    $('[data-near-section]').hidden = !near;
    $('[data-stock-near]').hidden = !near;
    $$('[data-col-height]').forEach(function (c) { c.classList.toggle('dim', MULTI.indexOf(d) < 0); });
    $$('[data-col-add]').forEach(function (c) { c.classList.toggle('dim', d === 'tek_odak_uzak'); });
    updateNear();
  }

  function applyEyes() {
    var e = eyesSel.value;
    $$('[data-eye-row]').forEach(function (row) {
      var off = e !== 'both' && row.getAttribute('data-eye-row') !== e;
      row.classList.toggle('off', off);
    });
  }

  function updateNear() {
    ['right', 'left'].forEach(function (side) {
      var input = $('[data-near="' + side + '"]');
      if (!input) return;
      var sph = num(side + '_sph'), add = num(side + '_add');
      var computed = fmtD((isNaN(sph) ? 0 : sph) + (isNaN(add) ? 0 : add));
      input.placeholder = isNaN(add) ? 'ADD girin' : computed;
    });
  }

  // Diyoptri alanlarını 0.25 adımına biçimlendir ve hatalıyı işaretle.
  $$('[data-diopter]', form).forEach(function (input) {
    input.addEventListener('blur', function () {
      var raw = input.value.trim();
      if (raw === '') { input.classList.remove('invalid'); return; }
      var v = parseFloat(raw.replace(',', '.'));
      var ok = !isNaN(v) && Math.abs(Math.round(v * 100) % 25) === 0 && /^[+-]?\d{0,2}([.,]\d{1,2})?$/.test(raw);
      if (ok && input.hasAttribute('data-add') && (v < 0 || v > 4)) ok = false;
      input.classList.toggle('invalid', !ok);
      input.setAttribute('aria-invalid', ok ? 'false' : 'true');
      if (ok) input.value = fmtD(v);
      updateNear();
    });
  });
  $$('[data-axis]', form).forEach(function (input) {
    input.addEventListener('blur', function () {
      var raw = input.value.trim();
      if (raw === '') { input.classList.remove('invalid'); return; }
      var ok = /^\d{1,3}$/.test(raw) && +raw <= 180;
      input.classList.toggle('invalid', !ok);
      if (ok && +raw === 0) input.value = '180';
    });
  });

  var copyBtn = $('[data-copy-right]', form);
  if (copyBtn) copyBtn.addEventListener('click', function () {
    ['sph', 'cyl', 'axis', 'add', 'pd', 'height'].forEach(function (f) {
      var r = field('right_' + f), l = field('left_' + f);
      if (r && l) l.value = r.value;
    });
    form.dispatchEvent(new Event('input', { bubbles: true }));
  });

  designSel.addEventListener('change', applyDesign);
  eyesSel.addEventListener('change', applyEyes);
  applyDesign();
  applyEyes();

  /* ------------------------------------------------------------------ */
  /*  Öneri asistanı                                                     */
  /* ------------------------------------------------------------------ */

  var panel = document.getElementById('advisor');
  var dataEl = document.getElementById('advisor-data');
  if (!panel || !dataEl) return;
  var boot = JSON.parse(dataEl.textContent || '{}');
  var out = $('[data-advisor-out]', panel);
  var attach = $('[data-advisor-attach]', panel);
  var summaryInput = $('[data-advisor-summary]', form);
  var productInput = $('[data-advisor-product]', form);
  var selectedProduct = boot.productId || 0;

  var a = function (name) { return $('[data-a="' + name + '"]', panel); };
  var aNum = function (name, def) { var v = parseFloat(a(name).value); return isNaN(v) ? def : v; };
  var aOn = function (name) { return a(name).checked; };

  // Malzeme verileri: kırılma indisi (n) ve minimum merkez kalınlığı (mm)
  var MATERIALS = [
    { idx: '1.50', n: 1.498, tc: 2.0, label: '1.50 (CR-39)' },
    { idx: '1.56', n: 1.560, tc: 1.8, label: '1.56' },
    { idx: '1.59', n: 1.586, tc: 1.2, label: '1.59 (Polikarbonat)' },
    { idx: '1.60', n: 1.597, tc: 1.5, label: '1.60' },
    { idx: '1.67', n: 1.665, tc: 1.3, label: '1.67' },
    { idx: '1.74', n: 1.727, tc: 1.2, label: '1.74' }
  ];
  var LADDER = ['1.56', '1.60', '1.67', '1.74'];
  var DESIGN_LABEL = { tek_odak: 'Tek odak', progressive: 'Progressive', ofis: 'Ofis / ara mesafe', bifokal: 'Bifokal' };

  function readRx() {
    var eyes = eyesSel.value;
    var sides = [];
    ['right', 'left'].forEach(function (side) {
      if (eyes !== 'both' && eyes !== side) return;
      var sph = num(side + '_sph'), cyl = num(side + '_cyl'), add = num(side + '_add');
      sph = isNaN(sph) ? 0 : sph; cyl = isNaN(cyl) ? 0 : cyl; add = isNaN(add) ? 0 : add;
      var axisEl = field(side + '_axis');
      var mono = num(side + '_pd');
      if (isNaN(mono)) { var pd = num('pd'); mono = isNaN(pd) ? 32 : pd / 2; }
      sides.push({
        side: side, label: side === 'right' ? 'Sağ' : 'Sol',
        sph: sph, cyl: cyl, add: add, se: sph + cyl / 2,
        m1: sph, m2: sph + cyl, mono: mono,
        axisMissing: cyl !== 0 && axisEl && axisEl.value.trim() === '',
        height: num(side + '_height'),
        entered: String(field(side + '_sph').value + field(side + '_cyl').value).trim() !== ''
      });
    });
    return sides;
  }

  // İnce mercek yaklaşımı: sagitta (mm) = y² · F / (2000 · (n − 1))
  function thickness(eye, mat, A, DBL, frame) {
    var dec = Math.max(0, (A + DBL) / 2 - eye.mono);
    var y = A / 2 + dec + 1; // +1 mm kesim payı
    var sag = function (F) { return (y * y * F) / (2000 * (mat.n - 1)); };
    var s1 = sag(eye.m1), s2 = sag(eye.m2);
    var minEdge = frame === 'vidali' ? 2.0 : frame === 'nylor' ? 1.8 : 1.0;
    var tc = mat.tc;
    if (frame === 'vidali' && mat.idx !== '1.59') tc = Math.max(tc, 1.5);
    var center = Math.max(tc, minEdge + Math.max(s1, s2));
    var edge = center - Math.min(s1, s2);
    return { center: center, edge: edge, max: Math.max(center, edge) };
  }

  function stepUp(idx, steps) {
    var i = LADDER.indexOf(idx);
    if (i < 0) return idx;
    return LADDER[Math.max(0, Math.min(LADDER.length - 1, i + steps))];
  }

  function recommend() {
    var eyes = readRx();
    var age = aNum('age', 0);
    var budget = a('budget').value;
    var screen = aNum('screen', 0);
    var frame = a('frame').value;
    var A = aNum('a', 52), DBL = aNum('dbl', 18);
    var formDesign = designSel.value;
    var reasons = [], warnings = [], tips = [];

    if (!eyes.length || !eyes.some(function (e) { return e.entered; })) {
      return null;
    }

    var P = 0, maxAdd = 0, maxCyl = 0, minusLens = true, maxPlus = 0, maxMinus = 0;
    eyes.forEach(function (e) {
      var lo = Math.min(e.m1, e.m2, 0), hi = Math.max(e.m1, e.m2, 0);
      P = Math.max(P, Math.abs(lo), hi);
      maxPlus = Math.max(maxPlus, hi); maxMinus = Math.min(maxMinus, lo);
      maxAdd = Math.max(maxAdd, e.add); maxCyl = Math.max(maxCyl, Math.abs(e.cyl));
      if (e.axisMissing) warnings.push(e.label + ' gözde CYL var ama AKS girilmemiş.');
    });
    minusLens = Math.abs(maxMinus) >= maxPlus;

    /* --- Tasarım --- */
    var design;
    if (MULTI.indexOf(formDesign) >= 0) {
      design = formDesign;
    } else if (formDesign === 'tek_odak_yakin' || formDesign === 'ayri_uzak_yakin') {
      design = 'tek_odak';
    } else {
      design = 'tek_odak';
    }
    var suggested = 'tek_odak';
    if (maxAdd > 0) {
      var deskOnly = aOn('desk') && !aOn('drive') && !aOn('outdoor');
      suggested = deskOnly ? 'ofis' : 'progressive';
      if (formDesign === 'tek_odak_uzak') {
        warnings.push('ADD değeri var ama “Tek odak · uzak” seçili. ' + (deskOnly ? 'Masa başı kullanım için ofis camı' : 'Uzak ve yakın için progressive') + ' veya ayrı yakın gözlük düşünün.');
      }
      reasons.push('ADD +' + maxAdd.toFixed(2) + ': yakın desteği gerekiyor → ' + DESIGN_LABEL[suggested].toLowerCase() + ' uygun.');
    } else if (age >= 42) {
      tips.push(age + ' yaşında ADD girilmemiş. Yakında zorlanma varsa yakın muayenesi önerin.');
    }
    if (design === 'progressive' || suggested === 'progressive') {
      if (aOn('firstprog')) tips.push('İlk kez progressive: geniş koridorlu, yumuşak tasarım seçin; 1–2 hafta alışma süresini anlatın.');
      var missingHeight = eyes.some(function (e) { return isNaN(e.height); });
      if (design === 'progressive' && missingHeight) warnings.push('Progressive için montaj yüksekliği girilmemiş.');
      tips.push('Progressive için çerçeve dikey yüksekliği en az 28–30 mm olmalı.');
    }

    /* --- İndeks --- */
    var idx = P <= 2 ? '1.56' : P <= 4 ? '1.60' : P <= 6 ? '1.67' : '1.74';
    reasons.push('En yüksek meridyen gücü ' + P.toFixed(2) + ' D → ' + idx + ' indeks başlangıç noktası.');
    if (A >= 56 && P > 3) { idx = stepUp(idx, 1); reasons.push('Büyük çerçeve (A ' + A + ' mm) kenar kalınlığını artırır → bir üst indeks.'); }
    if (budget === 'ekonomik' && P > 2) { var down = stepUp(idx, -1); if (down !== idx) { reasons.push('Ekonomik bütçe: ' + down + ' ile maliyet düşer, kalınlık farkı tabloda.'); idx = down; } }
    if (budget === 'premium' && P > 2 && idx !== '1.74') { idx = stepUp(idx, 1); reasons.push('Premium tercih: daha ince/hafif için bir üst indeks.'); }
    if ((frame === 'vidali' || frame === 'nylor') && LADDER.indexOf(idx) < LADDER.indexOf('1.60')) {
      idx = '1.60'; reasons.push((frame === 'vidali' ? 'Vidalı' : 'Misinalı') + ' çerçevede kırılma/çatlama riskine karşı en az 1.60.');
    }
    var impact = aOn('sport') || (age > 0 && age < 14) || frame === 'spor';
    if (impact && P <= 6) { idx = '1.59'; reasons.push('Darbe riski (spor / çocuk): polikarbonat 1.59 önerilir.'); }
    if (frame === 'vidali' && P <= 5 && !impact) tips.push('Vidalı çerçevede polikarbonat (1.59) veya Trivex delme çatlağına daha dayanıklıdır.');

    /* --- Kaplama --- */
    var coats = ['Antirefle'];
    if (screen >= 4) { coats.push('Blue (mavi ışık)'); reasons.push('Günde ' + screen + ' saat ekran → mavi ışık filtreli kaplama.'); }
    if (aOn('drive')) { coats.push('Drive (gece sürüş)'); reasons.push('Gece sürüşü → parlama azaltan sürüş kaplaması.'); }
    if (aOn('outdoor')) {
      coats.push('Fotokromik');
      reasons.push('Açık hava kullanımı → fotokromik cam.');
      if (aOn('drive')) tips.push('Fotokromik cam araç camı arkasında tam koyulaşmaz; sürüş için ayrıca polarize güneş gözlüğü önerin.');
    }
    var primaryCoat = coats[coats.length - 1];

    /* --- Uyarılar --- */
    if (eyes.length === 2) {
      var aniso = Math.abs(eyes[0].se - eyes[1].se);
      if (aniso >= 2) warnings.push('İki göz arasında ' + aniso.toFixed(2) + ' D fark (anizometropi): iki gözde aynı indeks ve tasarım kullanın; görüntü boyutu farkı için hekime danışılabilir.');
      if (Math.abs(eyes[0].add - eyes[1].add) > 0.5) warnings.push('Sağ ve sol ADD arasında 0.50 D’den fazla fark var, reçeteyi kontrol edin.');
    }
    if (maxCyl >= 2.5) tips.push('Yüksek astigmat (' + maxCyl.toFixed(2) + ' D): asferik/atorik tasarım periferik bozulmayı azaltır; alışma süresi uzayabilir.');
    if (maxPlus >= 4 && A >= 54) warnings.push('Yüksek artı numarada büyük çerçeve merkezi çok kalınlaştırır; daha küçük ve yuvarlak çerçeve önerin.');
    if (maxMinus <= -6 && frame !== 'kapali') warnings.push('Yüksek eksi numarada kenar kalınlığı görünür; kalın kenarı gizleyen kapalı çerçeve önerin.');
    if (P > 6) tips.push('6 D üzeri güçte asferik 1.74 ile kenar kalınlığı ve görüntü küçülmesi belirgin azalır.');

    /* --- Kalınlık --- */
    var rows = MATERIALS.map(function (m) {
      var worst = { center: 0, edge: 0, max: 0 };
      eyes.forEach(function (e) {
        var t = thickness(e, m, A, DBL, frame);
        if (t.max > worst.max) worst = t;
      });
      return { mat: m, t: worst };
    });
    var maxT = Math.max.apply(null, rows.map(function (r) { return r.t.max; }));

    /* --- Ürünler --- */
    var catalogDesign = MULTI.indexOf(design) >= 0 ? design : 'tek_odak';
    var idxVal = parseFloat(idx);
    var products = (boot.catalog || []).filter(function (p) {
      if (p.design !== catalogDesign) return false;
      if (p.index && parseFloat(p.index) + 0.001 < idxVal && !(idx === '1.59' && p.index === '1.59')) return false;
      return true;
    }).map(function (p) {
      var score = 0;
      if (p.tier === budget) score += 3;
      if (p.index === idx) score += 2; else if (!p.index) score += 1;
      if (p.coating && coats.indexOf(p.coating) >= 0) score += p.coating === primaryCoat ? 2 : 1;
      return { p: p, score: score };
    }).sort(function (x, y) {
      if (y.score !== x.score) return y.score - x.score;
      return (x.p.price == null ? 1e12 : x.p.price) - (y.p.price == null ? 1e12 : y.p.price);
    }).slice(0, 6);
    if (selectedProduct && !products.some(function (x) { return x.p.id === selectedProduct; })) selectedProduct = 0;

    return {
      design: design, suggested: suggested, idx: idx, coats: coats, reasons: reasons, warnings: warnings, tips: tips,
      rows: rows, maxT: maxT, products: products, minusLens: minusLens, P: P
    };
  }

  function render() {
    var r = recommend();
    if (!r) {
      out.innerHTML = '<p class="muted">Reçete değerlerini girdikçe öneri burada oluşur.</p>';
      syncSummary(null);
      return;
    }
    var html = '';
    html += '<div class="rec-chips">'
      + '<div><small>Tasarım</small><b>' + esc(DESIGN_LABEL[r.design === 'tek_odak' && r.suggested !== 'tek_odak' ? r.suggested : r.design]) + '</b></div>'
      + '<div><small>İndeks</small><b>' + esc(r.idx) + '</b></div>'
      + '<div><small>Kaplama</small><b>' + esc(r.coats.join(' + ')) + '</b></div></div>';

    if (r.warnings.length) {
      html += '<ul class="rec-list warn">' + r.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>';
    }

    html += '<h4>Tahmini kalınlık <small>(' + (r.minusLens ? 'kenar' : 'merkez') + ', en kalın göz)</small></h4><div class="thick">';
    r.rows.forEach(function (row) {
      var w = r.maxT ? Math.max(6, Math.round(row.t.max / r.maxT * 100)) : 0;
      var rec = row.mat.idx === r.idx;
      html += '<div class="thick-row' + (rec ? ' rec' : '') + '"><span>' + esc(row.mat.label) + '</span>'
        + '<span class="thick-bar"><i style="width:' + w + '%"></i></span>'
        + '<b>' + row.t.max.toFixed(1) + ' mm</b></div>';
    });
    html += '</div><p class="hint">Yaklaşık değerdir (±%15). Asferik tasarım ve iyi merkezleme kalınlığı azaltır.</p>';

    html += '<h4>Ürün seçenekleri</h4>';
    if (!r.products.length) {
      html += '<p class="muted small">Katalogda bu tasarıma uygun ürün yok. Ayarlar › Ürün kataloğu’ndan ekleyin.</p>';
    } else {
      html += '<div class="products">';
      r.products.forEach(function (x) {
        var p = x.p;
        var price = boot.showPrices ? (p.price != null ? money(p.price) : 'Fiyat girilmemiş') : '';
        html += '<label class="product' + (p.id === selectedProduct ? ' selected' : '') + '">'
          + '<input type="radio" name="advisor_pick" value="' + p.id + '"' + (p.id === selectedProduct ? ' checked' : '') + '>'
          + '<span><b>' + esc(p.brand + ' · ' + p.name) + '</b><small>' + esc([(boot.tiers || {})[p.tier], p.index ? p.index + ' indeks' : 'tüm indeksler', p.coating].filter(Boolean).join(' · ')) + '</small></span>'
          + (price ? '<em>' + esc(price) + '</em>' : '') + '</label>';
      });
      html += '</div>';
    }

    html += '<details class="rec-why"><summary>Gerekçe ve ipuçları</summary><ul class="rec-list">'
      + r.reasons.concat(r.tips).map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul></details>';

    var talk = customerText(r);
    html += '<h4>Müşteriye anlatım</h4><p class="talk">' + esc(talk) + '</p>'
      + '<small class="muted">Karar destek aracıdır; teşhis koymaz, reçeteyi değiştirmez.</small>';

    out.innerHTML = html;
    $$('input[name="advisor_pick"]', out).forEach(function (radio) {
      radio.addEventListener('change', function () { selectedProduct = +radio.value; render(); });
    });
    syncSummary(r, talk);
  }

  function customerText(r) {
    var d = r.design === 'tek_odak' && r.suggested !== 'tek_odak' ? r.suggested : r.design;
    var parts = [];
    if (d === 'progressive') parts.push('Tek gözlükle uzağı, ara mesafeyi ve yakını net görmenizi sağlayan progressive cam öneriyoruz.');
    else if (d === 'ofis') parts.push('Bilgisayar ve masa başı mesafesi için geniş görüş alanı sunan ofis camı öneriyoruz.');
    else if (d === 'bifokal') parts.push('Uzak ve yakın için iki ayrı bölgesi olan bifokal cam öneriyoruz.');
    else parts.push('Numaranıza uygun tek odaklı cam öneriyoruz.');
    if (r.P > 2) parts.push(r.idx + ' indeks, aynı numarayı daha ince ve hafif bir camla sağlar.');
    if (r.idx === '1.59') parts.push('Polikarbonat malzeme darbeye karşı çok dayanıklıdır.');
    if (r.coats.indexOf('Blue (mavi ışık)') >= 0) parts.push('Mavi ışık filtresi uzun ekran kullanımında göz yorgunluğunu azaltmaya yardımcı olur.');
    if (r.coats.indexOf('Drive (gece sürüş)') >= 0) parts.push('Sürüş kaplaması gece far parlamasını azaltır.');
    if (r.coats.indexOf('Fotokromik') >= 0) parts.push('Fotokromik cam güneşte koyulaşır, içeride şeffaflaşır.');
    parts.push('Antirefle kaplama yansımaları azaltır, camı daha net ve estetik gösterir.');
    return parts.join(' ');
  }

  function syncSummary(r, talk) {
    if (!attach.checked || !r) {
      if (!attach.checked) { summaryInput.value = ''; productInput.value = ''; }
      return;
    }
    var prod = null;
    (r.products || []).forEach(function (x) { if (x.p.id === selectedProduct) prod = x.p; });
    var d = r.design === 'tek_odak' && r.suggested !== 'tek_odak' ? r.suggested : r.design;
    var lines = [
      'Öneri: ' + DESIGN_LABEL[d] + ' · ' + r.idx + ' indeks · ' + r.coats.join(' + '),
      prod ? 'Seçilen ürün: ' + prod.brand + ' ' + prod.name + (boot.showPrices && prod.price != null ? ' (' + money(prod.price) + ')' : '') : '',
      'Kullanım: yaş ' + (a('age').value || '—') + ', ekran ' + (a('screen').value || 0) + ' sa, çerçeve ' + a('frame').selectedOptions[0].text + ' ' + a('a').value + '□' + a('dbl').value,
      r.warnings.length ? 'Uyarılar: ' + r.warnings.join(' ') : '',
      talk ? 'Anlatım: ' + talk : ''
    ].filter(Boolean);
    summaryInput.value = lines.join('\n');
    productInput.value = prod ? String(prod.id) : '';
  }

  var timer;
  function schedule() { clearTimeout(timer); timer = setTimeout(render, 120); }
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  panel.addEventListener('input', schedule);
  panel.addEventListener('change', schedule);
  attach.addEventListener('change', render);
  render();
})();

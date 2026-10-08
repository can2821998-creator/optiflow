/* Karşılama sayfası — ziyaretçiyi işin içine sokan deneyimler (4.28.0). Tek dış betik (CSP 'self'); satır içi kod yok.
   1) Dükkân adı: hero'daki kutuya yazılan ad, [data-dukkan] yazılarına ve kayıt bağlantılarına (?isim=) işlenir.
   2) Selam (saate göre) ve ay sonu bandı (ayın son 5 günü, ziyaretçinin kendi saatiyle).
   3) Medula aktarım simülatörü (#dene): değerler uçarak siparişe yerleşir, SGK payı ve bakiye sayılır, telefona WhatsApp.
   4) Lensmetre kazanç hesabı (#hesap): üç kadran → aylık saat ve TL; varsayımlar sayfada yazılı.
   5) Ishihara renk testi levhası: "30" → "30 gün ücretsiz".
   localStorage yalnızca kolaylık içindir (dükkân adı, kapatılan ay sonu bandı); erişilemezse sayfa yine çalışır. */
(function () {
  'use strict';
  var azHareket = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function her(sec, fn) { Array.prototype.forEach.call(document.querySelectorAll(sec), fn); }
  function yap(etiket, sinif, metin) {
    var el = document.createElement(etiket);
    if (sinif) el.className = sinif;
    if (metin !== undefined) el.textContent = metin;
    return el;
  }
  function oku(k) { try { return window.localStorage.getItem(k) || ''; } catch (e) { return ''; } }
  function yaz(k, v) { try { if (v) window.localStorage.setItem(k, v); else window.localStorage.removeItem(k); } catch (e) { /* özel pencere vb. */ } }
  function tl(n, kurus) {
    return n.toLocaleString('tr-TR', { minimumFractionDigits: kurus ? 2 : 0, maximumFractionDigits: kurus ? 2 : 0 }) + ' ₺';
  }
  function say(el, bas, son, sure, bicim, bitti) {
    if (azHareket || sure <= 0) { el.textContent = bicim(son); if (bitti) bitti(); return; }
    var t0 = null;
    function adim(t) {
      if (t0 === null) t0 = t;
      var x = Math.min(1, (t - t0) / sure), e = 1 - Math.pow(1 - x, 3);
      el.textContent = bicim(bas + (son - bas) * e);
      if (x < 1) window.requestAnimationFrame(adim); else if (bitti) bitti();
    }
    window.requestAnimationFrame(adim);
  }

  /* ---------- 1) dükkân adı ---------- */
  var VARSAYILAN = 'Örnek Optik';
  var dukkan = '';
  var kayitLinkleri = [];
  her('a[href^="kayit.php"]', function (a) { kayitLinkleri.push(a); a.setAttribute('data-kayit', a.getAttribute('href')); });
  function temizAd(s) { return String(s || '').replace(/\s+/g, ' ').trim().slice(0, 40); }
  function dukkanUygula(ad) {
    dukkan = temizAd(ad);
    her('[data-dukkan]', function (el) { el.textContent = dukkan || VARSAYILAN; });
    kayitLinkleri.forEach(function (a) {
      var h = a.getAttribute('data-kayit');
      a.setAttribute('href', dukkan ? h.split('?')[0] + '?isim=' + encodeURIComponent(dukkan) : h);
    });
    selamYaz();
  }
  var form = document.querySelector('[data-dukkan-form]');
  if (form) {
    var kutu = form.querySelector('input');
    var not = form.querySelector('.dukkan-not');
    form.hidden = false;
    var kayitli = temizAd(oku('optiflow-dukkan'));
    if (kayitli) { kutu.value = kayitli; dukkanUygula(kayitli); }
    var zaman = 0;
    function onayla(goster) {
      var ad = temizAd(kutu.value);
      dukkanUygula(ad);
      yaz('optiflow-dukkan', ad);
      if (!goster) return;
      not.textContent = '';
      if (ad) {
        not.appendChild(document.createTextNode('Tamam. Aşağıdaki ekranlarda artık '));
        not.appendChild(yap('b', '', ad));
        not.appendChild(document.createTextNode(' yazıyor; kayıt formu da bu adla hazır.'));
      }
    }
    kutu.addEventListener('input', function () { window.clearTimeout(zaman); zaman = window.setTimeout(function () { onayla(false); }, 250); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      onayla(true);
      var hedef = document.querySelector('#dene') || document.querySelector('#surumler');
      if (temizAd(kutu.value) && hedef) {
        window.setTimeout(function () { hedef.scrollIntoView({ behavior: azHareket ? 'auto' : 'smooth', block: 'start' }); }, 700);
      }
    });
  }

  /* ---------- 2) selam + ay sonu bandı ---------- */
  var selamEl = document.querySelector('[data-selam]');
  function selamYaz() {
    if (!selamEl) return;
    var s = new Date().getHours();
    var parca = s >= 5 && s < 11 ? ['Günaydın', 'Dükkân açılıyor; ilk reçete birazdan gelir.']
      : s >= 11 && s < 17 ? ['İyi günler', 'Tezgâhın en yoğun saatleri.']
      : s >= 17 && s < 22 ? ['İyi akşamlar', 'Kasa sayımı vakti.']
      : ['İyi geceler', 'Yarın sabah dükkânı OptiFlow ile açın.'];
    selamEl.textContent = parca[0] + (dukkan ? ', ' + dukkan : '') + '. ' + parca[1];
    selamEl.hidden = false;
  }
  selamYaz();

  (function aySonu() {
    var b = new Date(), sonGun = new Date(b.getFullYear(), b.getMonth() + 1, 0).getDate();
    if (sonGun - b.getDate() >= 5) return;
    var anahtar = b.getFullYear() + '-' + (b.getMonth() + 1);
    if (oku('optiflow-aysonu-kapat') === anahtar) return;
    var bant = yap('div', 'aysonu');
    bant.setAttribute('role', 'region');
    bant.setAttribute('aria-label', 'Ay sonu duyurusu');
    var ic = yap('div', 'aysonu-ic');
    var kalan = sonGun - b.getDate();
    ic.appendChild(yap('span', 'aysonu-gun', kalan === 0 ? 'Bugün ay sonu' : 'Ay sonuna ' + kalan + ' gün'));
    ic.appendChild(yap('span', 'aysonu-metin', 'SGK faturası için reçeteleri bu akşam tek tek mi sayacaksınız? OptiFlow\'da dakikalar sürer.'));
    var a = yap('a', 'aysonu-git', '30 gün ücretsiz deneyin');
    a.href = dukkan ? 'kayit.php?isim=' + encodeURIComponent(dukkan) : 'kayit.php';
    a.setAttribute('data-kayit', 'kayit.php');
    kayitLinkleri.push(a);
    ic.appendChild(a);
    var kapat = yap('button', 'aysonu-kapat', '×');
    kapat.type = 'button';
    kapat.setAttribute('aria-label', 'Duyuruyu kapat');
    kapat.addEventListener('click', function () { bant.remove(); yaz('optiflow-aysonu-kapat', anahtar); });
    bant.appendChild(ic);
    bant.appendChild(kapat);
    var header = document.querySelector('body > header');
    document.body.insertBefore(bant, header || document.body.firstChild);
  })();

  /* ---------- 3) Medula aktarım simülatörü ---------- */
  var sim = document.querySelector('[data-simulator]');
  if (sim) (function () {
    var SGK = parseInt(sim.getAttribute('data-sgk'), 10) || 0;
    var TUTAR = parseInt(sim.getAttribute('data-tutar'), 10) || 0;
    var girdiler = Array.prototype.slice.call(sim.querySelectorAll('[data-sim]'));
    var dugme = sim.querySelector('[data-sim-aktar]');
    var sureEl = sim.querySelector('[data-sim-sure]');
    var sgkEl = sim.querySelector('[data-sim-sgk]');
    var kalanEl = sim.querySelector('[data-sim-kalan]');
    var son = sim.querySelector('[data-sim-son]');
    var sonucEl = sim.querySelector('[data-sim-sonuc]');
    var tel = sim.querySelector('.sim-tel');
    var adimlar = Array.prototype.slice.call(sim.querySelectorAll('.sim-adim li'));
    var calisiyor = false;
    var ilk = {};
    girdiler.forEach(function (g) { ilk[g.getAttribute('data-sim')] = g.value; });

    function bicimle(g) {
      var tur = g.getAttribute('data-sim').slice(-2), ham = g.value.replace(',', '.').replace('−', '-').trim();
      if (tur === 'ak') {
        var a = parseInt(ham, 10);
        if (isNaN(a) || a < 0 || a > 180) return null;
        return String(a);
      }
      var d = parseFloat(ham);
      if (isNaN(d) || d < -30 || d > 30) return null;
      d = Math.round(d * 4) / 4;
      return (d > 0 ? '+' : d < 0 ? '−' : '') + Math.abs(d).toFixed(2).replace('.', ',');
    }
    function sifirla() {
      her('[data-simulator] [data-slot]', function (s) { s.textContent = ''; s.classList.remove('dolu'); });
      sgkEl.textContent = '—'; kalanEl.textContent = '—';
      adimlar.forEach(function (li) { li.classList.remove('on'); });
      tel.classList.remove('bildirim');
      sim.classList.remove('bitti');
      son.hidden = true;
      sureEl.textContent = '0,0 sn';
    }
    function aktar() {
      if (calisiyor) return;
      calisiyor = true;
      sifirla();
      dugme.disabled = true;
      sim.classList.add('aktariyor');
      var t0 = performance.now(), saat = true;
      (function tik() {
        if (!saat) return;
        sureEl.textContent = ((performance.now() - t0) / 1000).toFixed(1).replace('.', ',') + ' sn';
        window.requestAnimationFrame(tik);
      })();
      var ucuslar = girdiler.map(function (g, i) {
        var deger = bicimle(g);
        if (deger === null) { g.value = ilk[g.getAttribute('data-sim')]; deger = bicimle(g); g.classList.add('duzeltildi'); }
        else { g.value = deger; g.classList.remove('duzeltildi'); }
        var slot = sim.querySelector('[data-slot="' + g.getAttribute('data-sim') + '"]');
        var a = g.getBoundingClientRect(), b = slot.getBoundingClientRect();
        var cip = yap('span', 'sim-ucan', deger);
        cip.style.left = a.left + 'px'; cip.style.top = a.top + 'px';
        cip.style.width = a.width + 'px'; cip.style.height = a.height + 'px';
        document.body.appendChild(cip);
        var dx = (b.left + b.width / 2) - (a.left + a.width / 2), dy = (b.top + b.height / 2) - (a.top + a.height / 2);
        var anim = cip.animate([
          { transform: 'translate(0,0) scale(1)', opacity: 1 },
          { transform: 'translate(' + dx * 0.5 + 'px,' + (dy * 0.5 - 60) + 'px) scale(1.25)', opacity: 1, offset: 0.5 },
          { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(1)', opacity: 1 }
        ], { duration: azHareket ? 1 : 720, delay: azHareket ? 0 : i * 110, easing: 'cubic-bezier(.45,0,.2,1)', fill: 'forwards' });
        return anim.finished.then(function () {
          slot.textContent = deger;
          slot.classList.add('dolu');
          cip.remove();
        });
      });
      Promise.all(ucuslar).then(function () {
        say(sgkEl, 0, SGK, 600, function (v) { return '−' + tl(v, true); });
        say(kalanEl, TUTAR, TUTAR - SGK, 600, function (v) { return tl(v, true); }, function () {
          var i = 0;
          (function sonraki() {
            if (i < adimlar.length) { adimlar[i++].classList.add('on'); window.setTimeout(sonraki, azHareket ? 0 : 380); return; }
            tel.classList.add('bildirim');
            window.setTimeout(function () {
              saat = false;
              var sn = ((performance.now() - t0) / 1000).toFixed(1).replace('.', ',');
              sureEl.textContent = sn + ' sn';
              sonucEl.textContent = sn + ' saniye.';
              sim.classList.remove('aktariyor');
              sim.classList.add('bitti');
              son.hidden = false;
              dugme.disabled = false;
              calisiyor = false;
            }, azHareket ? 0 : 500);
          })();
        });
      });
    }
    dugme.addEventListener('click', aktar);
    var tekrar = sim.querySelector('[data-sim-tekrar]');
    if (tekrar) tekrar.addEventListener('click', function () { aktar(); dugme.focus(); });
    girdiler.forEach(function (g) { g.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aktar(); } }); });
  })();

  /* ---------- 4) lensmetre kazanç hesabı ---------- */
  var hesap = document.querySelector('[data-hesap]');
  if (hesap) (function () {
    var PRO = parseInt(hesap.getAttribute('data-pro'), 10) || 0;
    var EN_COK = 40;   // kadranın tam ölçeği (saat / ay)
    var NS = 'http://www.w3.org/2000/svg';
    var cetvel = hesap.querySelector('[data-lm-cetvel]');
    for (var s = 0; s <= EN_COK; s++) {
      var aci = (-135 + 270 * s / EN_COK) * Math.PI / 180, buyuk = s % 5 === 0;
      var r1 = buyuk ? 104 : 112, r2 = 122;
      var l = document.createElementNS(NS, 'line');
      l.setAttribute('x1', String(160 + r1 * Math.sin(aci))); l.setAttribute('y1', String(160 - r1 * Math.cos(aci)));
      l.setAttribute('x2', String(160 + r2 * Math.sin(aci))); l.setAttribute('y2', String(160 - r2 * Math.cos(aci)));
      l.setAttribute('class', buyuk ? 'lm-buyuk' : 'lm-kucuk');
      cetvel.appendChild(l);
      if (buyuk) {
        var t = document.createElementNS(NS, 'text');
        t.setAttribute('x', String(160 + 88 * Math.sin(aci))); t.setAttribute('y', String(160 - 88 * Math.cos(aci) + 4));
        t.setAttribute('text-anchor', 'middle');
        t.textContent = String(s);
        cetvel.appendChild(t);
      }
    }
    var ibre = hesap.querySelector('[data-lm-ibre]');
    var saatEl = hesap.querySelector('[data-h-saat]'), tlEl = hesap.querySelector('[data-h-tl]'), sonucEl = hesap.querySelector('[data-h-sonuc]');
    var g = { siparis: hesap.querySelector('#h-siparis'), sgk: hesap.querySelector('#h-sgk'), saat: hesap.querySelector('#h-saat') };
    var oncekiSaat = 0, oncekiTl = 0;
    function hesapla() {
      var siparis = +g.siparis.value, oran = +g.sgk.value / 100, deger = +g.saat.value;
      hesap.querySelector('[data-h-cikti="h-siparis"]').textContent = String(siparis);
      hesap.querySelector('[data-h-cikti="h-sgk"]').textContent = '%' + Math.round(oran * 100);
      hesap.querySelector('[data-h-cikti="h-saat"]').textContent = tl(deger);
      var sgkAdet = siparis * oran;
      var dakika = sgkAdet * (3 - 10 / 60) + sgkAdet * 1.5 + siparis * (1 + 1.5);
      var saat = dakika / 60, para = Math.round(saat * deger / 10) * 10;
      ibre.style.transform = 'rotate(' + (-135 + 270 * Math.min(saat, EN_COK) / EN_COK) + 'deg)';
      hesap.classList.toggle('tasti', saat > EN_COK);
      say(saatEl, oncekiSaat, saat, 450, function (v) { return v.toFixed(1).replace('.', ','); });
      say(tlEl, oncekiTl, para, 450, function (v) { return tl(Math.round(v / 10) * 10); });
      oncekiSaat = saat; oncekiTl = para;
      if (PRO > 0 && para >= PRO) {
        var gun = Math.max(1, Math.ceil(PRO / (para / 30)));
        sonucEl.textContent = 'OptiFlow Pro aylık ücretini yaklaşık ' + gun + ' günde çıkarır; ayın geri kalanı size kalır.';
      } else {
        sonucEl.textContent = 'Ayda ' + saat.toFixed(1).replace('.', ',') + ' saat: tezgâhta müşteriye ayrılacak zaman.';
      }
    }
    ['siparis', 'sgk', 'saat'].forEach(function (k) { g[k].addEventListener('input', hesapla); });
    hesapla();
  })();

  /* ---------- 5) Ishihara renk testi levhası ---------- */
  var lev = document.querySelector('[data-ishihara]');
  if (lev) (function () {
    var cv = lev.querySelector('canvas');
    if (!cv || !cv.getContext) return;
    lev.hidden = false;
    var W = cv.width, R = W / 2 - 6, ctx = cv.getContext('2d');
    // maske: ortada kalın "30"
    var m = document.createElement('canvas'); m.width = W; m.height = W;
    var mc = m.getContext('2d');
    mc.fillStyle = '#000';
    mc.font = '800 ' + Math.round(W * 0.5) + 'px Manrope, Arial, sans-serif';
    mc.textAlign = 'center'; mc.textBaseline = 'middle';
    mc.fillText('30', W / 2, W / 2 + W * 0.03);
    var maske = mc.getImageData(0, 0, W, W).data;
    function icinde(x, y) { var i = ((Math.round(y) * W) + Math.round(x)) * 4 + 3; return maske[i] > 100; }
    // tekrarlanabilir rastgele (her ziyaretçide aynı levha)
    var tohum = 30;
    function rnd() { tohum |= 0; tohum = tohum + 0x6D2B79F5 | 0; var t = Math.imul(tohum ^ tohum >>> 15, 1 | tohum); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }
    var SEKIL = ['#e2733a', '#ea8a4a', '#d65a2d', '#f0a35c', '#dd6a3c', '#e98f55'];
    var ZEMIN = ['#8eac5c', '#a1b76b', '#7b994e', '#b3c179', '#9aaf68', '#c2b56b', '#89a35a'];
    var noktalar = [], hucre = 24, izgara = {};
    function anahtar(x, y) { return Math.floor(x / hucre) + ':' + Math.floor(y / hucre); }
    function sigar(x, y, r) {
      var gx = Math.floor(x / hucre), gy = Math.floor(y / hucre);
      for (var i = gx - 1; i <= gx + 1; i++) for (var j = gy - 1; j <= gy + 1; j++) {
        var h = izgara[i + ':' + j]; if (!h) continue;
        for (var k = 0; k < h.length; k++) { var n = h[k]; if (Math.hypot(n[0] - x, n[1] - y) < n[2] + r + 1.6) return false; }
      }
      return true;
    }
    for (var deneme = 0; deneme < 9000; deneme++) {
      var r = 3 + rnd() * 8.5, aa = rnd() * Math.PI * 2, uz = Math.sqrt(rnd()) * (R - r);
      var x = W / 2 + Math.cos(aa) * uz, y = W / 2 + Math.sin(aa) * uz;
      if (!sigar(x, y, r)) continue;
      var n = [x, y, r];
      noktalar.push(n);
      (izgara[anahtar(x, y)] = izgara[anahtar(x, y)] || []).push(n);
    }
    ctx.fillStyle = '#f4efe6';
    ctx.beginPath(); ctx.arc(W / 2, W / 2, W / 2 - 1, 0, Math.PI * 2); ctx.fill();
    noktalar.forEach(function (n) {
      var pal = icinde(n[0], n[1]) ? SEKIL : ZEMIN;
      ctx.fillStyle = pal[Math.floor(rnd() * pal.length)];
      ctx.beginPath(); ctx.arc(n[0], n[1], n[2], 0, Math.PI * 2); ctx.fill();
    });
    lev.addEventListener('click', function () { lev.classList.toggle('acik'); });
    lev.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); lev.classList.toggle('acik'); } });
  })();
})();

/* Karşılama sayfası — imza anları (4.27.0). Tek dış betik (CSP 'self'); satır içi kod yok.
   karsilama-sahne.js'ten SONRA yüklenir (o, <html>'e .js-sahne ekler ve [data-kelime] metinlerini kelimelere böler).
   1) Kök hikâyesi (#koken): yapışkan sahne; kaydırma ilerlemesi --p (0 → 1). Çizim tamamen CSS'te.
   2) "1 mi, 2 mi?" muayenesi (#fark): karşılaştırma tablosunun satırlarından foropter oyunu kurulur. Tablo DOM'da
      kalır (arama motoru, ekran okuyucu) ve tek tuşla görünür.
   3) Büyüteç: [data-buyutec] ekran görüntülerinde noktalar; fareyle mercek gezer, noktaya yaklaşınca açıklama çıkar.
      Dokunmatikte noktaya dokunulur.
   4) Atölye: fiyat kartlarına bileme kıvılcımı, SGK dökümüne termal yazıcı başlığı.
   JS yoksa hiçbiri kurulmaz; sayfa olduğu gibi okunur. */
(function () {
  'use strict';
  var vh = window.innerHeight || 800;
  function her(sec, fn) { Array.prototype.forEach.call(document.querySelectorAll(sec), fn); }
  function yap(etiket, sinif, metin) {
    var el = document.createElement(etiket);
    if (sinif) el.className = sinif;
    if (metin !== undefined) el.textContent = metin;
    return el;
  }
  function sinirla(x, a, b) { return Math.max(a, Math.min(b, x)); }

  /* ---------- 1) kök hikâyesi ---------- */
  var koken = document.querySelector('[data-koken]');
  var kokenGorunur = false, kokenOnceki = -1;
  function kokenCiz() {
    if (!koken) return;
    var r = koken.getBoundingClientRect();
    var yol = Math.max(1, koken.offsetHeight - vh);
    var p = sinirla(-r.top / yol, 0, 1);
    if (Math.abs(p - kokenOnceki) > 0.001) {
      kokenOnceki = p;
      koken.style.setProperty('--p', p.toFixed(4));
    }
  }
  if (koken) {
    koken.style.setProperty('--p', '0');
    // "optik atölyesinde" vurgulu (kelimeler karsilama-sahne.js'te .kl olarak bölündü)
    her('#koken .koken-baslik .kl', function (s) { if (/^(optik|atölyesinde)$/i.test(s.textContent)) s.classList.add('vurgu'); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (g) { kokenGorunur = g[0].isIntersecting; if (kokenGorunur) kokenCiz(); },
        { rootMargin: '10% 0px 10% 0px' }).observe(koken);
    } else {
      kokenGorunur = true;
    }
  }

  /* ---------- 2) "1 mi, 2 mi?" muayenesi ---------- */
  var muayene = document.querySelector('[data-muayene]');
  if (muayene) (function () {
    var tablo = muayene.querySelector('.tablo-kap');
    var satirlar = [];
    Array.prototype.forEach.call(muayene.querySelectorAll('tbody tr'), function (tr) {
      var td = tr.querySelectorAll('td');
      if (td.length >= 3) satirlar.push({ is: td[0].textContent.trim(), once: td[1].textContent.trim(), ile: td[2].textContent.trim() });
    });
    if (!tablo || satirlar.length < 2) return;
    var DIOPTRI = ['−2,50', '−2,00', '−1,50', '−1,00', '−0,50', '−0,25', '0,00'];

    var kutu = yap('div', 'muayene');
    var ust = yap('div', 'muayene-ust');
    var soru = yap('p', 'muayene-soru');
    soru.innerHTML = 'Hangisi daha net? <b>1</b> mi, <b>2</b> mi?';
    var sayac = yap('span', 'muayene-sayac');
    ust.appendChild(soru); ust.appendChild(sayac);
    var isAd = yap('p', 'muayene-is');
    var govde = yap('div', 'foropter');
    var mercekler = [1, 2].map(function (n) {
      var b = yap('button', 'mercek');
      b.type = 'button';
      var cam = yap('span', 'mercek-cam');
      var metin = yap('span', 'mercek-metin');
      cam.appendChild(metin);
      b.appendChild(cam);
      b.appendChild(yap('span', 'mercek-no', String(n)));
      govde.appendChild(b);
      return { dugme: b, metin: metin, n: n };
    });
    var kopru = yap('span', 'foropter-kopru');
    kopru.setAttribute('aria-hidden', 'true');
    govde.insertBefore(kopru, mercekler[1].dugme);
    var olcek = yap('ol', 'muayene-olcek');
    olcek.setAttribute('aria-hidden', 'true');
    satirlar.forEach(function () { olcek.appendChild(yap('li')); });
    var ipucu = yap('p', 'muayene-ipucu');
    ipucu.setAttribute('aria-live', 'polite');
    var alt = yap('div', 'muayene-alt');
    var tabloDug = yap('button', 'muayene-tablo', 'Karşılaştırmayı tablo olarak görün');
    tabloDug.type = 'button';
    alt.appendChild(tabloDug);
    var sonuc = yap('div', 'muayene-sonuc');
    sonuc.hidden = true;

    kutu.appendChild(ust); kutu.appendChild(isAd); kutu.appendChild(govde); kutu.appendChild(olcek);
    kutu.appendChild(ipucu); kutu.appendChild(sonuc); kutu.appendChild(alt);
    tablo.parentNode.insertBefore(kutu, tablo);
    muayene.classList.add('muayene-acik');

    var tur = 0, netTaraf = 1, ilkBakis = 0, hataVar = false, kilit = false;
    function turKur() {
      var s = satirlar[tur];
      netTaraf = (tur === 0) ? 1 : (Math.random() < 0.5 ? 0 : 1);   // ilk turda net olan 2 numara (muayenedeki gibi)
      hataVar = false; kilit = false;
      sayac.textContent = 'Mercek ' + (tur + 1) + ' / ' + satirlar.length + ' · ' + DIOPTRI[Math.min(tur, DIOPTRI.length - 1)] + ' D';
      isAd.textContent = s.is;
      ipucu.textContent = '';
      mercekler.forEach(function (m, i) {
        var net = i === netTaraf;
        m.metin.textContent = net ? s.ile : s.once;
        m.dugme.className = 'mercek' + (net ? '' : ' bulanik');
        m.dugme.setAttribute('aria-label', m.n + ' numaralı mercek: ' + m.metin.textContent);
      });
      govde.classList.remove('degis');
      void govde.offsetWidth;
      govde.classList.add('degis');
      Array.prototype.forEach.call(olcek.children, function (li, i) { li.className = i < tur ? 'tamam' : (i === tur ? 'simdi' : ''); });
    }
    function sec(i) {
      if (kilit) return;
      var m = mercekler[i];
      if (i !== netTaraf) {
        hataVar = true;
        m.dugme.classList.remove('salla');
        void m.dugme.offsetWidth;
        m.dugme.classList.add('salla');
        ipucu.textContent = 'Bu hâlâ bulanık. Bir de ' + mercekler[netTaraf].n + ' numaraya bakın.';
        return;
      }
      kilit = true;
      if (!hataVar) ilkBakis++;
      m.dugme.classList.add('dogru');
      mercekler[1 - i].dugme.classList.add('eski');
      ipucu.textContent = 'Net. ' + satirlar[tur].is + ': ' + satirlar[tur].ile;
      window.setTimeout(function () {
        tur++;
        if (tur < satirlar.length) turKur(); else bitir();
      }, 1250);
    }
    function bitir() {
      govde.hidden = true; olcek.hidden = true; isAd.hidden = true; ipucu.textContent = '';
      sayac.textContent = 'Muayene bitti · 0,00 D';
      soru.textContent = 'Reçeteniz hazır.';
      Array.prototype.forEach.call(olcek.children, function (li) { li.className = 'tamam'; });
      var t = new Date();
      sonuc.innerHTML = '';
      var kart = yap('div', 'recete-kart');
      kart.appendChild(yap('small', 'recete-ust', 'REÇETE · ' + t.toLocaleDateString('tr-TR')));
      kart.appendChild(yap('h3', '', 'Teşhis: tezgâhta fazla elle yapılan iş.'));
      var tb = yap('div', 'recete-deger');
      [['SAĞ', '0,00'], ['SOL', '0,00'], ['İLK BAKIŞTA', ilkBakis + ' / ' + satirlar.length]].forEach(function (d) {
        var c = yap('div');
        c.appendChild(yap('small', '', d[0]));
        c.appendChild(yap('b', '', d[1]));
        tb.appendChild(c);
      });
      kart.appendChild(tb);
      var ted = yap('p', 'recete-tedavi');
      ted.innerHTML = 'Tedavi: <b>OptiFlow</b>, 30 gün, ücretsiz. Kredi kartı gerekmez.<br>Yan etkisi: ay sonu akşamları size kalır.';
      kart.appendChild(ted);
      var eylem = yap('div', 'recete-eylem');
      var kayit = yap('a', 'btn btn-red', '30 gün ücretsiz deneyin');
      kayit.href = 'kayit.php';
      var tekrar = yap('button', 'btn btn-line', 'Muayeneyi tekrarla');
      tekrar.type = 'button';
      tekrar.addEventListener('click', function () {
        tur = 0; ilkBakis = 0; sonuc.hidden = true; govde.hidden = false; olcek.hidden = false; isAd.hidden = false;
        soru.innerHTML = 'Hangisi daha net? <b>1</b> mi, <b>2</b> mi?';
        turKur();
        mercekler[0].dugme.focus();
      });
      eylem.appendChild(kayit); eylem.appendChild(tekrar);
      kart.appendChild(eylem);
      sonuc.appendChild(kart);
      sonuc.hidden = false;
    }
    mercekler.forEach(function (m, i) { m.dugme.addEventListener('click', function () { sec(i); }); });
    tabloDug.addEventListener('click', function () {
      var acik = muayene.classList.toggle('tablo-goster');
      tabloDug.textContent = acik ? 'Tabloyu gizle' : 'Karşılaştırmayı tablo olarak görün';
      if (acik) her('[data-muayene] tbody tr', function (tr) { tr.classList.add('gorunur'); });
    });
    turKur();
  })();

  /* ---------- 3) büyüteç ---------- */
  var merceK = null, notEl = null, aktifFig = null, dokunZaman = 0, sonX = 0, sonY = 0;
  var ZOOM = 2.3;
  function mercekHazirla() {
    if (merceK) return;
    merceK = yap('div', 'buyutec-mercek');
    merceK.setAttribute('aria-hidden', 'true');
    notEl = yap('div', 'buyutec-not');
    notEl.setAttribute('aria-hidden', 'true');
    document.body.appendChild(merceK);
    document.body.appendChild(notEl);
  }
  function mercekGoster(fig, x, y) {   // x, y: ekran koordinatı
    var img = fig.__img, r = img.getBoundingClientRect();
    var R = merceK.offsetWidth / 2 || 90;
    var lx = x - r.left, ly = y - r.top;
    if (lx < 0 || ly < 0 || lx > r.width || ly > r.height) { mercekGizle(); return; }
    merceK.style.backgroundImage = 'url("' + (img.currentSrc || img.src) + '")';
    merceK.style.backgroundSize = (r.width * ZOOM) + 'px ' + (r.height * ZOOM) + 'px';
    merceK.style.backgroundPosition = (R - lx * ZOOM) + 'px ' + (R - ly * ZOOM) + 'px';
    merceK.style.transform = 'translate(' + (x - R) + 'px,' + (y - R) + 'px)';
    merceK.classList.add('acik');
    // en yakın not
    var enIyi = null, enAz = 1e9;
    fig.__notlar.forEach(function (n) {
      var d = Math.hypot(n[0] * r.width - lx, n[1] * r.height - ly);
      if (d < enAz) { enAz = d; enIyi = n; }
    });
    fig.__noktalar.forEach(function (nk) { nk.classList.toggle('yakin', nk.__not === enIyi && enAz < R * 0.9); });
    if (enIyi && enAz < R * 0.9) {
      notEl.textContent = enIyi[2];
      var nx = x + R * 0.78, ny = y - R * 0.9;
      var gen = Math.min(260, window.innerWidth - 24);
      if (nx + gen > window.innerWidth - 12) nx = x - R * 0.78 - gen;
      notEl.style.width = gen + 'px';
      notEl.style.transform = 'translate(' + Math.max(12, nx) + 'px,' + Math.max(12, ny) + 'px)';
      notEl.classList.add('acik');
    } else {
      notEl.classList.remove('acik');
    }
  }
  function mercekGizle() {
    if (!merceK) return;
    merceK.classList.remove('acik');
    notEl.classList.remove('acik');
    if (aktifFig) { aktifFig.classList.remove('buyutec-aktif'); aktifFig.__noktalar.forEach(function (nk) { nk.classList.remove('yakin'); }); }
    aktifFig = null;
  }
  function noktalariYerlestir(fig) {
    var img = fig.__img;
    fig.__noktalar.forEach(function (nk) {
      nk.style.left = (img.offsetLeft + nk.__not[0] * img.offsetWidth) + 'px';
      nk.style.top = (img.offsetTop + nk.__not[1] * img.offsetHeight) + 'px';
    });
  }
  // kaydırınca mercek kapanmaz: fare yerinde durur, altındaki görüntü değişir (dokunmatikte kapanır)
  function mercekTazele() {
    if (!merceK || !aktifFig) return;
    if (dokunZaman) { mercekGizle(); return; }
    mercekGoster(aktifFig, sonX, sonY);
  }
  var buyutecler = [];
  her('[data-buyutec]', function (fig) {
    var img = fig.querySelector('img');
    var notlar;
    try { notlar = JSON.parse(fig.getAttribute('data-buyutec')); } catch (e) { return; }
    if (!img || !Array.isArray(notlar)) return;
    mercekHazirla();
    fig.__img = img; fig.__notlar = notlar; fig.__noktalar = [];
    fig.classList.add('buyutecli');
    notlar.forEach(function (n) {
      var b = yap('button', 'buyutec-nokta');
      b.type = 'button';
      b.setAttribute('aria-label', n[2]);
      b.__not = n;
      b.addEventListener('click', function (e) {
        e.preventDefault();
        var r = img.getBoundingClientRect();
        aktifFig = fig;
        dokunZaman = Date.now();
        mercekGoster(fig, r.left + n[0] * r.width, r.top + n[1] * r.height);
        var benim = dokunZaman;
        window.setTimeout(function () { if (dokunZaman === benim) mercekGizle(); }, 3600);
      });
      fig.appendChild(b);
      fig.__noktalar.push(b);
    });
    noktalariYerlestir(fig);
    img.addEventListener('load', function () { noktalariYerlestir(fig); });
    fig.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') return;
      aktifFig = fig;
      dokunZaman = 0;
      fig.classList.add('buyutec-aktif');
      sonX = e.clientX; sonY = e.clientY;
      mercekGoster(fig, sonX, sonY);
    });
    fig.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') mercekGizle(); });
    buyutecler.push(fig);
  });
  if (buyutecler.length) {
    var lead = document.querySelector('#galeri .lead');
    if (lead) lead.appendChild(document.createTextNode(' Noktaların üstüne gelin: büyüteçle yakından bakın.'));
    window.addEventListener('resize', function () { buyutecler.forEach(noktalariYerlestir); }, { passive: true });
    // şerit yana kayınca ya da sayfa kayınca açık mercek eski yerde kalmasın
    var serit = document.querySelector('.serit');
    if (serit) serit.addEventListener('scroll', mercekTazele, { passive: true });
  }

  /* ---------- 4) atölye: bileme kıvılcımı, termal yazıcı ---------- */
  her('#fiyatlar .paket', function (k) {
    var kv = yap('i', 'bileme-kivilcim');
    kv.setAttribute('aria-hidden', 'true');
    k.appendChild(kv);
    if ('MutationObserver' in window) {
      var mo = new MutationObserver(function () {
        if (k.classList.contains('gorunur')) {
          mo.disconnect();
          window.setTimeout(function () { k.classList.add('kesildi'); }, 1900);
        }
      });
      mo.observe(k, { attributes: true, attributeFilter: ['class'] });
    } else {
      k.classList.add('kesildi');
    }
  });
  her('#ay-sonu .kontrol', function (k) {
    var kap = yap('div', 'yazici-kap');
    var yz = yap('div', 'yazici');
    yz.setAttribute('aria-hidden', 'true');
    yz.appendChild(yap('span', 'yazici-isik'));
    yz.appendChild(yap('small', '', 'SGK dökümü · yazdırılıyor'));
    yz.appendChild(yap('span', 'yazici-agiz'));
    k.parentNode.insertBefore(kap, k);
    kap.appendChild(yz);
    kap.appendChild(k);
  });

  /* ---------- kaydırma döngüsü (yalnız kök sahnesi) ---------- */
  var bekliyor = false;
  function kare() { bekliyor = false; if (kokenGorunur) kokenCiz(); }
  window.addEventListener('scroll', function () {
    mercekTazele();
    if (!bekliyor) { bekliyor = true; window.requestAnimationFrame(kare); }
  }, { passive: true });
  window.addEventListener('resize', function () { vh = window.innerHeight || vh; kokenCiz(); }, { passive: true });
  kokenCiz();
})();

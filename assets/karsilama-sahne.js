/* Karşılama sayfası — sahne sistemi (4.25.0). Tek dış betik (CSP 'self'); satır içi kod yok.
   1) Giriş efektleri: seçilen öğelere data-gir verilir, görünür olunca .gorunur eklenir (CSS çizer, sıralı gecikme --sira).
   2) Kaydırma ilerlemesi: data-ilerle bölümlerine --i (0 → 1) yazılır; yalnız ekranda görünen bölümler, tek rAF döngüsünde.
   3) Foropter sahnesi (tanıtım videosu): yapışkan bölüm, --p, kadran tıkları, dioptri/keskinlik göstergeleri.
   4) Sayaçlar, hikâye metninin kelime kelime koyulaşması, sayfa ilerleme çizgisi, imleci izleyen ışık.
   Hepsi kullanıcının kaydırmasıyla ilerler; bu yüzden hareket azaltma tercihinde de çalışır (yalnız kendiliğinden dönen
   süsler CSS'te durur). JS yoksa hiçbir öğe gizlenmez, sayfa olduğu gibi görünür. */
(function () {
  'use strict';
  var kok = document.documentElement;
  kok.classList.add('js-sahne');
  var vh = window.innerHeight || 800;

  /* ---------- 1) giriş efektleri ---------- */
  var GIRIS = [
    ['.bas h2, #ay-sonu h2, .son h2, .rehber h3', 'netles'],
    ['.bas .lead, #ay-sonu .lead, .son p, .alt-not, .hikaye .ek', 'yukari'],
    ['.surum', 'dondur'],
    ['.fark tbody tr', 'satir'],
    ['.yol li', 'yukari'],
    ['#icerde .iki > div:first-child', 'soldan'],
    ['.telefon', 'olcek'],
    ['.serit figure', 'dondur'],
    ['.ay-liste li', 'soldan'],
    ['.kontrol', 'olcek'],
    ['.grup h3', 'netles'],
    ['.grup dl > div', 'yukari'],
    ['.paket', 'olcek'],
    ['.dahil span', 'yukari'],
    ['.guven > div', 'yukari'],
    ['.sss details', 'yukari'],
    ['.son .hero-ctas', 'olcek'],
    ['.rehber', 'yukari'],
  ];
  var girenler = [];
  GIRIS.forEach(function (g) {
    var sayac = new Map();
    Array.prototype.forEach.call(document.querySelectorAll(g[0]), function (el) {
      if (el.hasAttribute('data-gir')) return;
      var ust = el.parentNode, n = sayac.get(ust) || 0;
      sayac.set(ust, n + 1);
      el.setAttribute('data-gir', g[1]);
      el.style.setProperty('--sira', String(Math.min(n, 8)));
      girenler.push(el);
    });
  });

  // sayaçlar: "48" → 0'dan sayar
  function say(el) {
    var hedef = parseInt(el.getAttribute('data-say'), 10), bas = null;
    if (!(hedef > 0)) return;
    el.textContent = '0';
    function adim(t) {
      if (bas === null) bas = t;
      var x = Math.min(1, (t - bas) / 1100), e = 1 - Math.pow(1 - x, 3);
      el.textContent = String(Math.round(hedef * e));
      if (x < 1) window.requestAnimationFrame(adim);
    }
    window.requestAnimationFrame(adim);
  }
  Array.prototype.forEach.call(document.querySelectorAll('.kontrol-ust b'), function (b) {
    var n = parseInt(b.textContent, 10);
    if (n > 0) b.setAttribute('data-say', String(n));
  });
  function gorundu(el) {
    el.classList.add('gorunur');
    Array.prototype.forEach.call(el.querySelectorAll('[data-say]'), say);
  }
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (girdiler) {
      girdiler.forEach(function (g) { if (g.isIntersecting) { gorundu(g.target); io.unobserve(g.target); } });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });
    girenler.forEach(function (el) { io.observe(el); });
  } else {
    girenler.forEach(gorundu);
  }

  /* ---------- hikâye metni: kelimelere böl ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-kelime]'), function (p) {
    var kelimeler = p.textContent.trim().split(/\s+/);
    p.textContent = '';
    p.style.setProperty('--n', String(kelimeler.length));
    kelimeler.forEach(function (k, i) {
      var s = document.createElement('span');
      s.className = 'kl';
      s.style.setProperty('--k', String(i));
      s.textContent = k;
      p.appendChild(s);
      if (i < kelimeler.length - 1) p.appendChild(document.createTextNode(' '));
    });
  });

  /* ---------- 2) kaydırma ilerlemesi ---------- */
  var ILERLE = ['#surumler', '#yol', '#icerde', '#galeri', '.hikaye', '.son', '#fiyatlar'];
  var gorunenler = new Set();
  var izlenen = [];
  ILERLE.forEach(function (s) { var el = document.querySelector(s); if (el) { el.setAttribute('data-ilerle', ''); izlenen.push(el); } });
  if ('IntersectionObserver' in window) {
    var io2 = new IntersectionObserver(function (girdiler) {
      girdiler.forEach(function (g) { if (g.isIntersecting) gorunenler.add(g.target); else gorunenler.delete(g.target); });
      kaydir();
    }, { rootMargin: '15% 0px 15% 0px' });
    izlenen.forEach(function (el) { io2.observe(el); });
  } else {
    izlenen.forEach(function (el) { gorunenler.add(el); });
  }
  function ilerlet(el) {
    var r = el.getBoundingClientRect();
    // üst kenar ekranın %90'ına girince 0, alt kenar ekranın ortasına gelince 1
    var i = Math.max(0, Math.min(1, (vh * 0.9 - r.top) / (r.height + vh * 0.4)));
    var eski = el.__i;
    if (eski === undefined || Math.abs(eski - i) > 0.0015) { el.__i = i; el.style.setProperty('--i', i.toFixed(4)); }
  }

  /* ---------- 3) foropter sahnesi ---------- */
  var film = document.querySelector('[data-film]');
  var filmGorunur = false, oncekiP = -1, oncekiTik = -1;
  var BULANIK = [16, 11, 7, 4, 1.5, 0];
  var SAG = ['−2,00', '−1,50', '−1,00', '−0,50', '−0,25', '0,00'];
  var SOL = ['−1,75', '−1,25', '−0,75', '−0,50', '−0,25', '0,00'];
  var dSag, dSol, olcek;
  if (film) {
    film.classList.add('film-sahneli');
    dSag = film.querySelector('[data-diyopter-sag]');
    dSol = film.querySelector('[data-diyopter-sol]');
    olcek = film.querySelectorAll('[data-keskinlik] li');
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (g) { filmGorunur = g[0].isIntersecting; kaydir(); }, { rootMargin: '10% 0px 10% 0px' }).observe(film);
    } else filmGorunur = true;
  }
  function tikYaz(k) {
    if (k === oncekiTik) return;
    oncekiTik = k;
    film.style.setProperty('--kl', String(k));
    film.style.setProperty('--b', String(BULANIK[k]));
    if (dSag) dSag.textContent = k === 5 ? 'NET' : SAG[k];
    if (dSol) dSol.textContent = k === 5 ? 'NET' : SOL[k];
    for (var i = 0; i < olcek.length; i++) olcek[i].classList.toggle('etkin', i === k);
  }
  function filmGuncelle() {
    var r = film.getBoundingClientRect();
    var yol = Math.max(1, film.offsetHeight - vh);
    var p = Math.max(0, Math.min(1, -r.top / yol));
    if (Math.abs(p - oncekiP) > 0.0008 || p === 0 || p === 1) {
      oncekiP = p;
      film.style.setProperty('--p', p.toFixed(4));
    }
    tikYaz(Math.max(0, Math.min(5, Math.floor((p - 0.04) / 0.08) + (p >= 0.04 ? 1 : 0))));
  }

  /* ---------- 4) sayfa ilerleme çizgisi ---------- */
  var cizgi = document.createElement('div');
  cizgi.className = 'sayfa-ilerleme';
  cizgi.setAttribute('aria-hidden', 'true');
  document.body.appendChild(cizgi);

  /* ---------- tek döngü ---------- */
  var bekliyor = false;
  function kare() {
    bekliyor = false;
    if (film && filmGorunur) filmGuncelle();
    gorunenler.forEach(ilerlet);
    var top = Math.max(1, kok.scrollHeight - vh);
    cizgi.style.transform = 'scaleX(' + Math.min(1, window.scrollY / top).toFixed(4) + ')';
  }
  function kaydir() {
    if (bekliyor) return;
    bekliyor = true;
    window.requestAnimationFrame(kare);
  }
  window.addEventListener('scroll', kaydir, { passive: true });
  window.addEventListener('resize', function () { vh = window.innerHeight || 800; kaydir(); });
  kare();

  /* ---------- imleci izleyen ışık (hero, son çağrı) ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('.hero, .son'), function (el) {
    var bek = false, x = 0, y = 0;
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect();
      x = ((e.clientX - r.left) / r.width) * 100; y = ((e.clientY - r.top) / r.height) * 100;
      if (bek) return;
      bek = true;
      window.requestAnimationFrame(function () {
        bek = false;
        el.style.setProperty('--mx', x.toFixed(1) + '%');
        el.style.setProperty('--my', y.toFixed(1) + '%');
        el.classList.add('isikli');
      });
    }, { passive: true });
    el.addEventListener('pointerleave', function () { el.classList.remove('isikli'); });
  });

  /* ---------- sesli tam video penceresi ---------- */
  var ac = film && film.querySelector('[data-film-ac]');
  var pencere = document.querySelector('[data-film-dialog]');
  if (ac && pencere && typeof pencere.showModal === 'function') {
    var video = pencere.querySelector('video');
    ac.addEventListener('click', function (e) {
      e.preventDefault();
      if (!video.getAttribute('src')) video.setAttribute('src', video.getAttribute('data-src'));
      pencere.showModal();
      try { video.currentTime = 0; } catch (err) { /* meta veri henüz yok */ }
      var oyna = video.play();
      if (oyna && oyna.catch) oyna.catch(function () { /* izin yoksa kullanıcı oynat'a basar */ });
    });
    // close olayı bazı tarayıcılarda gecikmeli gelir: kapatırken hemen durdur
    var kapatPencere = function () { video.pause(); if (pencere.open) pencere.close(); };
    pencere.addEventListener('close', function () { video.pause(); });
    pencere.addEventListener('cancel', function () { video.pause(); });
    pencere.addEventListener('click', function (e) { if (e.target === pencere) kapatPencere(); });
    var kapat = pencere.querySelector('[data-film-kapat]');
    if (kapat) kapat.addEventListener('click', kapatPencere);
  }
})();

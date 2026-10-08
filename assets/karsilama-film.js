/* Karşılama sayfası — tanıtım videosu: foropter sahnesi (4.24.0).
   Bölüm uzundur, sahne ekrana yapışır (sticky). Kaydırma ilerlemesi --p (0 → 1) olarak yazılır; CSS sahneyi çizer.
   Kademeli kısımlar (kadran tıkları, bulanıklık, dioptri ve keskinlik göstergeleri) burada hesaplanır.
   JS yoksa veya hareket azaltma tercihinde: sınıf eklenmez, bölüm son hâliyle (net video + ekranlar) görünür. */
(function () {
  'use strict';
  var bolum = document.querySelector('[data-film]');
  if (!bolum) return;
  var azHareket = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- sesli tam video penceresi (her durumda) ----
  var ac = bolum.querySelector('[data-film-ac]');
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

  if (azHareket) return;

  // ---- sahne ----
  var BULANIK = [16, 11, 7, 4, 1.5, 0];                         // tık başına bulanıklık (px)
  var SAG = ['−2,00', '−1,50', '−1,00', '−0,50', '−0,25', '0,00'];
  var SOL = ['−1,75', '−1,25', '−0,75', '−0,50', '−0,25', '0,00'];
  var sag = bolum.querySelector('[data-diyopter-sag]');
  var sol = bolum.querySelector('[data-diyopter-sol]');
  var olcek = bolum.querySelectorAll('[data-keskinlik] li');
  var oncekiTik = -1;

  bolum.classList.add('film-sahneli');

  function tikYaz(k) {
    if (k === oncekiTik) return;
    oncekiTik = k;
    bolum.style.setProperty('--kl', String(k));
    bolum.style.setProperty('--b', String(BULANIK[k]));
    if (sag) sag.textContent = k === 5 ? 'NET' : SAG[k];
    if (sol) sol.textContent = k === 5 ? 'NET' : SOL[k];
    for (var i = 0; i < olcek.length; i++) olcek[i].classList.toggle('etkin', i === k);
  }
  function hesapla() {
    var r = bolum.getBoundingClientRect();
    var vh = window.innerHeight || 800;
    var yol = Math.max(1, bolum.offsetHeight - vh);
    var p = Math.max(0, Math.min(1, -r.top / yol));
    bolum.style.setProperty('--p', p.toFixed(4));
    // tıklar: p 0,06 … 0,44 arasında 5 tık
    tikYaz(Math.max(0, Math.min(5, Math.floor((p - 0.04) / 0.08) + (p >= 0.04 ? 1 : 0))));
  }
  var bekliyor = false;
  function kaydir() {
    if (bekliyor) return;
    bekliyor = true;
    window.requestAnimationFrame(function () { bekliyor = false; hesapla(); });
  }
  window.addEventListener('scroll', kaydir, { passive: true });
  window.addEventListener('resize', kaydir);
  hesapla();
})();

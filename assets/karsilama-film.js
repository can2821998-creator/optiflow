/* Karşılama sayfası — tanıtım videosu bölümü (4.23.0).
   Kaydırdıkça mercek açılır: bölüme --p (0 → 1) yazılır, CSS mercek/netleşme/etiketleri buna göre çizer.
   Dioptri göstergesi −2,75 D'den 0,00 D'ye iner. "Sesli izle" tam videoyu sayfa içi pencerede açar.
   JS yoksa: --p varsayılanı 1 (video açık), düğme mp4'ü yeni sekmede açar. */
(function () {
  'use strict';
  var bolum = document.querySelector('[data-film]');
  if (!bolum) return;
  var azHareket = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var gosterge = bolum.querySelector('[data-diyopter]');
  var sahne = bolum.querySelector('.film-ekran') || bolum;

  function yaz(p) {
    bolum.style.setProperty('--p', p.toFixed(3));
    if (!gosterge) return;
    if (p >= 0.985) { gosterge.textContent = '0,00 D · net'; return; }
    gosterge.textContent = (-2.75 * (1 - p)).toFixed(2).replace('.', ',').replace('-', '−') + ' D';
  }
  function hesapla() {
    if (azHareket) { yaz(1); return; }
    var r = sahne.getBoundingClientRect(), vh = window.innerHeight || 800;
    // ekranın üstü görünüm alanının altına girince 0; ortasına yaklaşınca 1
    var p = (vh - r.top) / (vh * 0.75);
    yaz(Math.max(0, Math.min(1, p)));
  }
  var bekliyor = false;
  function kaydir() {
    if (bekliyor) return;
    bekliyor = true;
    window.requestAnimationFrame(function () { bekliyor = false; hesapla(); });
  }
  bolum.classList.add('film-canli');
  window.addEventListener('scroll', kaydir, { passive: true });
  window.addEventListener('resize', kaydir);
  hesapla();

  // Sesli tam video: sayfa içi pencere
  var ac = bolum.querySelector('[data-film-ac]');
  var pencere = document.querySelector('[data-film-dialog]');
  if (!ac || !pencere || typeof pencere.showModal !== 'function') return;
  var video = pencere.querySelector('video');
  ac.addEventListener('click', function (e) {
    e.preventDefault();
    if (!video.getAttribute('src')) video.setAttribute('src', video.getAttribute('data-src'));
    pencere.showModal();
    try { video.currentTime = 0; } catch (err) { /* meta veri henüz yok */ }
    var oyna = video.play();
    if (oyna && oyna.catch) oyna.catch(function () { /* tarayıcı izin vermezse kullanıcı oynat'a basar */ });
  });
  // close olayı bazı tarayıcılarda gecikmeli gelir: videoyu kapatırken hemen durdur
  function kapatPencere() { video.pause(); if (pencere.open) pencere.close(); }
  pencere.addEventListener('close', function () { video.pause(); });
  pencere.addEventListener('cancel', function () { video.pause(); });   // Esc
  pencere.addEventListener('click', function (e) { if (e.target === pencere) kapatPencere(); });
  var kapat = pencere.querySelector('[data-film-kapat]');
  if (kapat) kapat.addEventListener('click', kapatPencere);
})();

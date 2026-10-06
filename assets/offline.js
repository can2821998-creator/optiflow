/* 4.20.3 — Çevrimdışı ekranı (offline.html): bağlantı gelince yenile + bekleme oyunu.
   offline.html'deki satır içi betikten taşındı (CSP: script-src 'self'). sw.js bu dosyayı önceden saklar. */
(function () {
  'use strict';

  document.getElementById('yenidenBtn').addEventListener('click', function () { location.reload(); });
  addEventListener('online', function () { location.reload(); });
  setInterval(function () { if (navigator.onLine) location.reload(); }, 5000);

  // Basit hafıza eşleştirme oyunu — sadece bağlantı yokken oyalanmak için.
  var playBtn = document.getElementById('playBtn');
  var game = document.getElementById('game');
  var board = document.getElementById('board');
  var movesEl = document.getElementById('gameMoves');
  var bestEl = document.getElementById('gameBest');
  var msgEl = document.getElementById('gameMsg');
  var EMOJI = ['🕶️', '👓', '🔍', '✨', '💎', '🌟', '🧿', '🔧'];
  var moves = 0, opened = [], lock = false, matched = 0;
  var bestKey = 'optiflow_offline_best';

  function shuffle(arr) {
    for (var i = arr.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
    }
    return arr;
  }
  function best() {
    try { return localStorage.getItem(bestKey); } catch (e) { return null; }
  }
  function setBest(v) {
    try { localStorage.setItem(bestKey, String(v)); } catch (e) { /* yoksay */ }
  }

  function start() {
    var cards = shuffle(EMOJI.concat(EMOJI));
    moves = 0; opened = []; lock = false; matched = 0;
    movesEl.textContent = '0 hamle';
    var b = best();
    bestEl.textContent = b ? ('En iyi: ' + b + ' hamle') : '';
    msgEl.textContent = '';
    board.innerHTML = '';
    cards.forEach(function (emoji) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Kart');
      btn.dataset.val = emoji;
      btn.addEventListener('click', function () { flip(btn); });
      board.appendChild(btn);
    });
  }

  function flip(btn) {
    if (lock || btn.classList.contains('flip') || btn.classList.contains('matched')) return;
    btn.textContent = btn.dataset.val;
    btn.classList.add('flip');
    opened.push(btn);
    if (opened.length !== 2) return;
    moves++;
    movesEl.textContent = moves + ' hamle';
    lock = true;
    var a = opened[0], c = opened[1];
    if (a.dataset.val === c.dataset.val) {
      a.classList.add('matched'); c.classList.add('matched');
      matched += 2;
      opened = []; lock = false;
      if (matched === EMOJI.length * 2) {
        var b = best();
        if (!b || moves < parseInt(b, 10)) { setBest(moves); msgEl.textContent = '🎉 Yeni rekor! ' + moves + ' hamlede bitirdiniz.'; }
        else { msgEl.textContent = '🎉 Bitti! ' + moves + ' hamlede tamamladınız.'; }
      }
    } else {
      setTimeout(function () {
        a.textContent = ''; c.textContent = '';
        a.classList.remove('flip'); c.classList.remove('flip');
        opened = []; lock = false;
      }, 650);
    }
  }

  playBtn.addEventListener('click', function () {
    playBtn.hidden = true;
    game.hidden = false;
    start();
  });
})();

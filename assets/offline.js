/* 4.20.3 — Çevrimdışı ekranı (offline.html): bağlantı gelince yenile + bekleme oyunu.
   offline.html'deki satır içi betikten taşındı (CSP: script-src 'self'). sw.js bu dosyayı önceden saklar. */
(function () {
  'use strict';

  document.getElementById('yenidenBtn').addEventListener('click', function () { location.reload(); });
  // Sunucu gerçekten cevap verince yenile (eskiden 5 sn'de bir körlemesine yeniliyordu: sunucu kapalı ama
  // internet varken sayfa sürekli yenilenip listedeki aramayı siliyordu). manifest.php hafif ve oturumsuz açılır.
  function yokla() {
    if (!navigator.onLine) return;
    fetch('manifest.php', { cache: 'no-store' })
      .then(function (r) { if (r.ok) location.reload(); })
      .catch(function () { /* hâlâ bağlantı yok */ });
  }
  addEventListener('online', yokla);
  setInterval(yokla, 10000);

  /* 4.20.3 — Telefonda çevrimdışı kopya (assets/pwa.js yazar). 24 saatten eskisi gösterilmez ve silinir. */
  var KOPYA_DB = 'optiflow-cevrimdisi';
  function kopyaSil() {
    try { localStorage.removeItem('of-cevrimdisi-son'); } catch (e) { /* yoksay */ }
    try { indexedDB.deleteDatabase(KOPYA_DB); } catch (e) { /* yoksay */ }
  }
  function kopyaOku() {
    return new Promise(function (ok) {
      if (!window.indexedDB || !window.crypto || !crypto.subtle) { ok(null); return; }
      var r = indexedDB.open(KOPYA_DB, 1);
      r.onupgradeneeded = function () { r.transaction.abort(); };   // hiç kopya yoksa veritabanı oluşturma
      r.onerror = function () { ok(null); };
      r.onsuccess = function () {
        var db = r.result;
        if (!db.objectStoreNames.contains('kv')) { db.close(); ok(null); return; }
        var t = db.transaction('kv', 'readonly');
        var s = t.objectStore('kv');
        var a = s.get('anahtar');
        var k = s.get('kopya');
        t.oncomplete = function () { db.close(); ok(a.result && k.result ? { anahtar: a.result, kopya: k.result } : null); };
        t.onerror = function () { db.close(); ok(null); };
      };
    });
  }
  function eleman(etiket, sinif, metin) {
    var el = document.createElement(etiket);
    if (sinif) el.className = sinif;
    if (metin) el.textContent = metin;
    return el;
  }
  function kopyaGoster(veri, olusturma) {
    var liste = document.getElementById('kopyaListe');
    var ara = document.getElementById('kopyaAra');
    var dakika = Math.max(0, Math.round((Date.now() - olusturma) / 60000));
    document.getElementById('kopyaZaman').textContent = dakika < 60 ? dakika + ' dk önce' : Math.round(dakika / 60) + ' sa önce';
    document.getElementById('kopyaUyari').textContent = (veri.magaza && veri.magaza.isim ? veri.magaza.isim + ' · ' : '')
      + (veri.kullanici && veri.kullanici.ad ? veri.kullanici.ad + ' için' : '')
      + ' · Bu bir kopyadır; değişiklik yapılamaz. 24 saat sonra kendiliğinden silinir.';
    var siparisler = Array.isArray(veri.siparisler) ? veri.siparisler : [];
    function ciz() {
      var q = (ara.value || '').toLocaleLowerCase('tr').trim();
      var rakam = q.replace(/\D/g, '');
      liste.textContent = '';
      var adet = 0;
      siparisler.forEach(function (s) {
        var metin = (s.ad + ' ' + s.no).toLocaleLowerCase('tr');
        if (q && metin.indexOf(q) === -1 && !(rakam.length >= 3 && String(s.tel || '').replace(/\D/g, '').indexOf(rakam) !== -1)) return;
        if (++adet > 200) return;
        var li = eleman('li');
        var ust = eleman('div', 'ust');
        ust.appendChild(eleman('span', 'ad', s.ad || '—'));
        ust.appendChild(eleman('span', 'no', s.no + (s.tarih ? ' · ' + s.tarih : '')));
        li.appendChild(ust);
        var alt = eleman('div', 'alt');
        alt.appendChild(eleman('span', 'asama', s.asama || ''));
        if (s.soz) alt.appendChild(document.createTextNode(' · Söz: ' + s.soz));
        if (s.kalan) { alt.appendChild(document.createTextNode(' · ')); alt.appendChild(eleman('span', 'kalan', 'Kalan ' + s.kalan)); }
        li.appendChild(alt);
        var urun = [s.cam, s.cerceve].filter(Boolean).join(' · ');
        if (urun) li.appendChild(eleman('div', 'alt', urun));
        var tel = String(s.tel || '').replace(/[^\d+]/g, '');
        if (tel.length >= 7) {
          var a = eleman('a', 'tel', '☎ ' + s.tel);
          a.href = 'tel:' + tel;
          li.appendChild(a);
        }
        liste.appendChild(li);
      });
      if (!adet) liste.appendChild(eleman('li', '', q ? 'Eşleşen sipariş yok.' : 'Açık sipariş yok.'));
    }
    ara.addEventListener('input', ciz);
    ciz();
    document.body.classList.add('kopyali');
    document.getElementById('kopya').hidden = false;
  }
  kopyaOku().then(function (k) {
    if (!k) return;
    if (!k.kopya.bitis || Date.now() > k.kopya.bitis) { kopyaSil(); return; }
    crypto.subtle.decrypt({ name: 'AES-GCM', iv: k.kopya.iv }, k.anahtar, k.kopya.veri)
      .then(function (acik) { kopyaGoster(JSON.parse(new TextDecoder().decode(acik)), k.kopya.olusturma); })
      .catch(function () { kopyaSil(); });
  });

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
    board.textContent = '';
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

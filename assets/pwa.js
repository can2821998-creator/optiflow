/* ==========================================================================
   PWA kurulumu: servis çalışanını kaydeder, "Ana ekrana ekle" çubuğunu yönetir.
   iOS Safari kendi kurulum akışını sunmadığı için orada kısa bir yönerge gösterir.
   Çubuk bir kez kapatılırsa 30 gün tekrar gösterilmez.
   ========================================================================== */
(function () {
  'use strict';

  // OptiFlow Masaüstü (4.10.0): kurulum istemleri anlamsız (zaten kurulu uygulama) ve servis
  // çalışanı gerekmez — bağlantı hatalarını masaüstü kabuğu gösterir. Ayrıca servis çalışanının
  // kendi istekleri masaüstü işaretini taşımadığı için sunucu masaüstü görünümünü seçemiyordu.
  if (/\bOptiFlowDesktop\//.test(navigator.userAgent)) return;

  if ('serviceWorker' in navigator) {
    addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () { /* yoksay */ });
    });
    // Yeni bir servis çalışanı devreye girdiğinde (güncelleme yayınlandığında)
    // sekme bunu kendiliğinden fark etmez — eski app.js/app.css hafızada
    // çalışmaya devam eder. Bu yüzden devralma anında sayfayı bir kez
    // otomatik tazeliyoruz ki güncelleme her zaman hemen görünsün.
    var tazelendi = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (tazelendi) return;
      tazelendi = true;
      location.reload();
    });
  }

  /* 4.20.3 — Telefonda çevrimdışı kopya (özellik: cevrimdisi_tel).
     Uygulama sayfası <body data-cevrimdisi="1"> ise: en çok 15 dakikada bir api.php?action=cevrimdisi alınır,
     WebCrypto AES-GCM ile şifrelenip IndexedDB'de saklanır. Anahtar dışa aktarılamaz (extractable: false) ve
     yalnızca bu tarayıcıda durur. data-cevrimdisi="0" (özellik kapalı) ya da işaretsiz sayfa (giriş ekranları,
     çıkıştan sonra) → kopya silinir. Gösterim: offline.html + assets/offline.js (24 saatten eskisi silinir). */
  var KOPYA_DB = 'optiflow-cevrimdisi';
  var KOPYA_SON = 'of-cevrimdisi-son';
  function kopyaSil() {
    try { localStorage.removeItem(KOPYA_SON); } catch (e) { /* yoksay */ }
    try { indexedDB.deleteDatabase(KOPYA_DB); } catch (e) { /* yoksay */ }
  }
  function kopyaDb() {
    return new Promise(function (ok, hata) {
      var r = indexedDB.open(KOPYA_DB, 1);
      r.onupgradeneeded = function () { r.result.createObjectStore('kv'); };
      r.onsuccess = function () { ok(r.result); };
      r.onerror = function () { hata(r.error); };
    });
  }
  function kv(db, kip, islem) {
    return new Promise(function (ok, hata) {
      var t = db.transaction('kv', kip);
      var r = islem(t.objectStore('kv'));
      t.oncomplete = function () { ok(r && r.result); };
      t.onerror = function () { hata(t.error); };
    });
  }
  function kopyaKaydet() {
    if (!window.indexedDB || !window.crypto || !crypto.subtle || !navigator.onLine) return;
    var son = 0;
    try { son = parseInt(localStorage.getItem(KOPYA_SON) || '0', 10); } catch (e) { /* yoksay */ }
    if (Date.now() - son < 15 * 60 * 1000) return;
    fetch('api.php?action=cevrimdisi', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 403 || r.status === 401) { kopyaSil(); return null; }
        return r.ok ? r.json() : null;
      })
      .then(function (veri) {
        if (!veri || !veri.ok) return null;
        return kopyaDb().then(function (db) {
          return kv(db, 'readonly', function (s) { return s.get('anahtar'); })
            .then(function (anahtar) {
              return anahtar || crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'])
                .then(function (yeni) { return kv(db, 'readwrite', function (s) { return s.put(yeni, 'anahtar'); }).then(function () { return yeni; }); });
            })
            .then(function (anahtar) {
              var iv = crypto.getRandomValues(new Uint8Array(12));
              return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, anahtar, new TextEncoder().encode(JSON.stringify(veri)))
                .then(function (sifreli) {
                  var simdi = Date.now();
                  var kayit = { iv: iv, veri: sifreli, olusturma: simdi, bitis: simdi + (veri.gecerlilik_sn || 86400) * 1000 };
                  return kv(db, 'readwrite', function (s) { return s.put(kayit, 'kopya'); });
                });
            })
            .then(function () {
              db.close();
              try { localStorage.setItem(KOPYA_SON, String(Date.now())); } catch (e) { /* yoksay */ }
            });
        });
      })
      .catch(function () { /* bir sonraki sayfada yeniden denenir */ });
  }
  addEventListener('load', function () {
    var isaret = document.body ? document.body.getAttribute('data-cevrimdisi') : null;
    if (isaret === '1') kopyaKaydet();
    else kopyaSil();
  });

  var ANAHTAR = 'pa-kurulum-kapat';
  var AY = 30 * 24 * 60 * 60 * 1000;

  function kapaliMi() {
    try {
      var t = parseInt(localStorage.getItem(ANAHTAR) || '0', 10);
      return t && (Date.now() - t) < AY;
    } catch (e) { return false; }
  }
  function kapat() {
    try { localStorage.setItem(ANAHTAR, String(Date.now())); } catch (e) { /* yoksay */ }
  }
  function kuruluMu() {
    return matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  }
  function iosMu() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent);
  }

  function cubukYap(metin, dugme, tiklama) {
    var el = document.createElement('div');
    el.className = 'pwa-bar';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-label', 'Uygulamayı yükle');
    el.innerHTML =
      '<span class="pwa-ic" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-linecap="round">' +
      '<circle cx="17.5" cy="25" r="8.5" stroke-width="2.6"/><circle cx="30.5" cy="25" r="8.5" stroke-width="2.6"/>' +
      '<path d="M22.3 22.4c1.1-.9 2.3-.9 3.4 0" stroke-width="2.6"/></svg></span>' +
      '<span class="pwa-txt">' + metin + '</span>' +
      (dugme ? '<button type="button" class="pwa-yes">' + dugme + '</button>' : '') +
      '<button type="button" class="pwa-no" aria-label="Kapat">&times;</button>';
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-on'); });

    el.querySelector('.pwa-no').addEventListener('click', function () {
      kapat(); el.classList.remove('is-on');
      setTimeout(function () { el.remove(); }, 300);
    });
    var yes = el.querySelector('.pwa-yes');
    if (yes && tiklama) {
      yes.addEventListener('click', function () {
        tiklama(function () {
          el.classList.remove('is-on');
          setTimeout(function () { el.remove(); }, 300);
        });
      });
    }
    return el;
  }

  if (kuruluMu() || kapaliMi()) return;

  // Android / masaüstü Chrome: yerleşik kurulum akışı
  var istem = null;
  addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    istem = e;
    cubukYap('Atölyeyi telefonunuza uygulama olarak ekleyin.', 'Ekle', function (bitir) {
      istem.prompt();
      istem.userChoice.then(function () { kapat(); bitir(); });
    });
  });

  // iOS: Safari kurulum istemi sunmaz, yönerge gösterilir
  if (iosMu()) {
    addEventListener('load', function () {
      setTimeout(function () {
        if (kuruluMu() || kapaliMi()) return;
        cubukYap('Ana ekrana eklemek için <b>Paylaş</b> → <b>Ana Ekrana Ekle</b>.', '', null);
      }, 2500);
    });
  }

  addEventListener('appinstalled', kapat);
})();

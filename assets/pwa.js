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

  /* ---------------- 4.21.0 — uygulama hissi ---------------- */
  var kurulu = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var hareketAz = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Ana ekran simgesinde rozet: atölyedeki iş sayısı (desteklenen telefonlarda).
  try {
    var rozet = document.querySelector('meta[name="of-rozet"]');
    if (rozet && 'setAppBadge' in navigator) {
      var n = parseInt(rozet.getAttribute('content') || '0', 10);
      if (n > 0) navigator.setAppBadge(n).catch(function () {});
      else navigator.clearAppBadge().catch(function () {});
    }
  } catch (e) { /* yoksay */ }

  // Dokunma titreşimi (Android): alt çubuk ve ana düğmeler.
  function titret() {
    if (kurulu && navigator.vibrate) { try { navigator.vibrate(8); } catch (e) { /* yoksay */ } }
  }

  // Sayfa geçişinde üstte ince yükleme çizgisi.
  var cizgi = null;
  function cizgiAc() {
    if (!document.body) return;
    if (!cizgi) {
      cizgi = document.createElement('div');
      cizgi.className = 'yukleme-cizgi';
      cizgi.setAttribute('aria-hidden', 'true');
      document.body.appendChild(cizgi);
    }
    cizgi.classList.remove('is-on');
    void cizgi.offsetWidth;
    cizgi.classList.add('is-on');
  }
  addEventListener('pageshow', function () { if (cizgi) cizgi.classList.remove('is-on'); });
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href], .tabbar button, .btn-primary') : null;
    if (!a) return;
    if (a.closest('.tabbar') || a.classList.contains('btn-primary')) titret();
    if (a.tagName !== 'A' || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
    var href = a.getAttribute('href') || '';
    if (a.target === '_blank' || a.hasAttribute('download') || href.charAt(0) === '#' || /^(mailto|tel|javascript|whatsapp):/i.test(href)) return;
    try { if (new URL(a.href, location.href).origin !== location.origin) return; } catch (err) { return; }
    cizgiAc();
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!e.defaultPrevented && f && f.target !== '_blank') cizgiAc();
  });

  // Aşağı çekip yenileme: yalnızca ana ekrana eklenmiş uygulamada (tarayıcının kendi özelliği yok).
  if (kurulu && 'ontouchstart' in window) {
    var basY = null, cekilen = 0, gosterge = null, ESIK = 78;
    var gostergeYap = function () {
      gosterge = document.createElement('div');
      gosterge.className = 'cek-yenile';
      gosterge.setAttribute('aria-hidden', 'true');
      gosterge.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M19 8a8 8 0 1 0 1 6"/><path d="M20 3v5h-5"/></svg>';
      document.body.appendChild(gosterge);
    };
    document.addEventListener('touchstart', function (e) {
      var t = e.target;
      if (window.scrollY > 0 || document.body.classList.contains('nav-open') || (t.closest && t.closest('input, textarea, select, .pano, .pano-col, .modal, [data-no-pull]'))) { basY = null; return; }
      basY = e.touches[0].clientY; cekilen = 0;
    }, { passive: true });
    document.addEventListener('touchmove', function (e) {
      if (basY === null) return;
      cekilen = Math.max(0, e.touches[0].clientY - basY);
      if (cekilen < 6) return;
      if (!gosterge) gostergeYap();
      var oran = Math.min(1, cekilen / ESIK);
      gosterge.style.transform = 'translate(-50%, ' + Math.min(cekilen * 0.6, 70) + 'px) rotate(' + Math.round(oran * 300) + 'deg)';
      gosterge.style.opacity = String(oran);
      gosterge.classList.toggle('hazir', oran >= 1);
    }, { passive: true });
    document.addEventListener('touchend', function () {
      if (basY === null) return;
      basY = null;
      if (cekilen >= ESIK && gosterge) {
        gosterge.classList.add('donuyor');
        titret();
        cizgiAc();
        location.reload();
        return;
      }
      if (gosterge) { gosterge.style.opacity = '0'; gosterge.style.transform = 'translate(-50%, 0)'; }
    }, { passive: true });
  }
  void hareketAz;

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

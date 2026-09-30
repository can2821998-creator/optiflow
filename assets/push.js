/* ==========================================================================
   Telefon bildirimi (web push) — cihaz aç/kapa denetimi.
   Profil sayfasındaki karta bağlanır: [data-push-card]
   iOS'ta bildirim yalnızca uygulama ana ekrana eklendiğinde çalışır.
   ========================================================================== */
(function () {
  'use strict';

  var kart = document.querySelector('[data-push-card]');
  if (!kart) return;

  var dugme = kart.querySelector('[data-push-toggle]');
  var deneme = kart.querySelector('[data-push-test]');
  var durum = kart.querySelector('[data-push-state]');
  var csrf = kart.getAttribute('data-csrf') || '';
  var kayit = null;
  var abone = null;

  function yaz(metin, ton) {
    if (!durum) return;
    durum.textContent = metin;
    durum.className = 'badge sm tone-' + (ton || 'gray');
  }

  function kapali(sebep) {
    yaz(sebep, 'gray');
    if (dugme) { dugme.disabled = true; }
    if (deneme) { deneme.hidden = true; }
  }

  /* Başarısızlıkta gerçek sebebi ekrana yaz — tahmin etmeye gerek kalmasın */
  function neden(metin) {
    var kutu = kart.querySelector('[data-push-reason]');
    if (!kutu) return;
    kutu.hidden = false;
    kutu.textContent = metin;
  }

  function ortam() {
    var sw = ('serviceWorker' in navigator) ? (navigator.serviceWorker.controller ? 'etkin' : 'kayıtlı değil') : 'yok';
    return 'Ortam → izin: ' + (window.Notification ? Notification.permission : 'yok')
      + ' · servis çalışanı: ' + sw
      + ' · güvenli bağlantı: ' + (window.isSecureContext ? 'evet' : 'HAYIR')
      + ' · PushManager: ' + ('PushManager' in window ? 'var' : 'yok');
  }

  /* Geçici hatalarda düğme kilitlenmesin: sebep gösterilir, tekrar denenebilir */
  function tekrarDene(rozetMetni) {
    yaz(rozetMetni, 'amber');
    if (dugme) { dugme.disabled = false; dugme.textContent = 'Tekrar dene'; dugme.classList.add('btn-primary'); }
    if (deneme) { deneme.hidden = true; }
  }

  function hataYaz(e, nerede) {
    var m = '';
    if (e && e.name) { m = e.name; }
    if (e && e.message) { m += (m ? ': ' : '') + e.message; }
    if (!m) { m = String(e); }
    neden(nerede + ' — ' + m + '\n' + ortam());
  }

  function istek(action, govde) {
    return fetch('push.php?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(govde || {}),
      credentials: 'same-origin',
    }).then(function (r) { return r.json().catch(function () { return {}; }); });
  }

  function ham(b64) {
    var pad = '='.repeat((4 - (b64.length % 4)) % 4);
    var s = (b64 + pad).replace(/-/g, '+').replace(/_/g, '/');
    var bin = atob(s);
    var arr = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    return arr;
  }

  function b64(buf) {
    var bin = '';
    var arr = new Uint8Array(buf);
    for (var i = 0; i < arr.length; i++) bin += String.fromCharCode(arr[i]);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function cihazAdi() {
    var ua = navigator.userAgent;
    var p = /iphone|ipad|ipod/i.test(ua) ? 'iPhone / iPad'
      : /android/i.test(ua) ? 'Android'
      : /mac/i.test(ua) ? 'Mac'
      : /windows/i.test(ua) ? 'Windows' : 'Cihaz';
    var t = /chrome|crios/i.test(ua) ? 'Chrome'
      : /firefox|fxios/i.test(ua) ? 'Firefox'
      : /safari/i.test(ua) ? 'Safari' : 'tarayıcı';
    return p + ' · ' + t;
  }

  function goruntule() {
    if (!dugme) return;
    if (abone) {
      dugme.textContent = 'Bu cihazda kapat';
      dugme.classList.remove('btn-primary');
      yaz('Açık', 'green');
      if (deneme) deneme.hidden = false;
    } else {
      dugme.textContent = 'Bu cihazda aç';
      dugme.classList.add('btn-primary');
      yaz('Kapalı', 'gray');
      if (deneme) deneme.hidden = true;
    }
    dugme.disabled = false;
  }

  /* --- Ortam denetimi --- */
  var iosMu = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var kuruluMu = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  if (!window.isSecureContext) {
    kapali('Güvenli bağlantı gerekli');
    neden('Bildirimler yalnızca https:// adreslerde çalışır. Siteyi https ile açın.');
    return;
  }
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    kapali(iosMu && !kuruluMu ? 'Önce ana ekrana ekleyin' : 'Bu tarayıcı desteklemiyor');
    neden('Bu tarayıcı bildirimleri desteklemiyor. Uygulama içi tarayıcıda (Instagram, WhatsApp, Facebook) açtıysanız, sağ üstteki menüden "Tarayıcıda aç" deyin.\n' + ortam());
    return;
  }
  if (iosMu && !kuruluMu) {
    kapali('Önce ana ekrana ekleyin');
    return;
  }
  if (Notification.permission === 'denied') {
    kapali('Tarayıcı izni reddedilmiş');
    return;
  }

  /* Tarayıcıda abonelik varsa sunucuya her açılışta yeniden bildirilir:
     sunucu kaydı bir şekilde kaybolduysa (tablo sonradan oluştuysa, kayıt
     silindiyse) kullanıcı hiçbir şey yapmadan kendiliğinden geri gelir. */
  function sunucuyaBildir(s) {
    if (!s) return Promise.resolve();
    var j = s.toJSON ? s.toJSON() : {};
    return istek('subscribe', {
      endpoint: s.endpoint,
      p256dh: (j.keys && j.keys.p256dh) || b64(s.getKey('p256dh')),
      auth: (j.keys && j.keys.auth) || b64(s.getKey('auth')),
      device: cihazAdi(),
    });
  }

  navigator.serviceWorker.ready.then(function (reg) {
    kayit = reg;
    return reg.pushManager.getSubscription();
  }).then(function (s) {
    abone = s;
    goruntule();
    return sunucuyaBildir(s);
  }).catch(function (e) { kapali('Hazırlanamadı'); hataYaz(e, 'Servis çalışanı hazırlanamadı'); });

  if (dugme) {
    dugme.addEventListener('click', function () {
      dugme.disabled = true;
      yaz('İşleniyor…', 'amber');

      if (abone) {
        var uc = abone.endpoint;
        abone.unsubscribe().catch(function () {}).then(function () {
          return istek('unsubscribe', { endpoint: uc });
        }).then(function () { abone = null; goruntule(); });
        return;
      }

      Notification.requestPermission().then(function (izin) {
        if (izin !== 'granted') {
          if (izin === 'denied') { kapali('Tarayıcı izni reddedildi'); } else { tekrarDene('İzin verilmedi'); }
          neden(izin === 'denied'
            ? 'Bildirim izni bu site için engellenmiş. Tarayıcı adres çubuğundaki kilit simgesi → Site ayarları → Bildirimler → İzin ver.'
            : 'İzin penceresi kapatıldı ya da yanıtlanmadı. Düğmeye tekrar basıp "İzin ver" deyin.');
          return;
        }
        return fetch('push.php?action=key', { credentials: 'same-origin' })
          .then(function (r) {
            if (!r.ok) {
              throw new Error('push.php yanıt vermedi (HTTP ' + r.status + ') — dosya sunucuya yüklendi mi?');
            }
            return r.text().then(function (t) {
              try { return JSON.parse(t); }
              catch (e) { throw new Error('push.php geçersiz yanıt verdi: ' + t.slice(0, 120)); }
            });
          })
          .then(function (d) {
            if (!d.ok) { tekrarDene('Sunucu hazır değil'); neden(d.reason || 'Sunucu anahtar üretemedi'); return; }
            if (!d.key || d.key.length < 80) { tekrarDene('Sunucu hazır değil'); neden('VAPID anahtarı geçersiz (uzunluk ' + (d.key || '').length + ')'); return; }
            var secenek = { userVisibleOnly: true, applicationServerKey: ham(d.key) };
            return kayit.pushManager.subscribe(secenek).catch(function (e) {
              // Eski/farklı anahtarla kurulmuş bir abonelik varsa temizleyip bir kez daha dene
              if (e && (e.name === 'InvalidStateError' || e.name === 'InvalidAccessError')) {
                return kayit.pushManager.getSubscription().then(function (eski) {
                  return eski ? eski.unsubscribe() : true;
                }).then(function () {
                  return kayit.pushManager.subscribe(secenek);
                });
              }
              throw e;
            }).catch(function (e) {
              var ad = e && e.name ? e.name : '';
              var ek = '';
              if (ad === 'AbortError' || ad === 'NotAllowedError') {
                ek = ' (Bu tarayıcı/cihaz bildirim servisine bağlanamıyor. Chrome ile deneyin; '
                   + 'bağlantıyı WhatsApp/Instagram içinden açtıysanız menüden "Tarayıcıda aç" deyin.)';
              }
              throw new Error('Tarayıcı aboneliği reddetti — ' + ad + ' ' + (e && e.message ? e.message : '') + ek);
            });
          })
          .then(function (s) {
            if (!s) return;
            return sunucuyaBildir(s).then(function (r) {
              if (r && r.ok) { abone = s; goruntule(); }
              else { kapali((r && r.error) ? r.error : 'Kaydedilemedi'); }
            });
          });
      }).catch(function (e) { tekrarDene('Açılamadı'); hataYaz(e, 'Bildirim açılamadı'); });
    });
  }

  if (deneme) {
    deneme.addEventListener('click', function () {
      deneme.disabled = true;
      var eski = deneme.textContent;
      deneme.textContent = 'Gönderiliyor…';
      istek('test', {}).then(function (r) {
        if (r && r.ok) {
          deneme.textContent = 'Gönderildi ✓';
        } else {
          deneme.textContent = 'Gönderilemedi';
          var not = kart.querySelector('[data-push-reason]');
          if (not) {
            not.hidden = false;
            not.textContent = (r && r.reason) ? ('Sebep: ' + r.reason) : 'Sunucu bildirimi iletemedi.';
          }
        }
        setTimeout(function () { deneme.textContent = eski; deneme.disabled = false; }, 3000);
      });
    });
  }
})();

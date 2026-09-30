/* ==========================================================================
   Çerçeve stoğu — kameradan barkod / karekod okuma.
   Tarayıcı BarcodeDetector'ı destekliyorsa (Android Chrome, yeni masaüstü
   Chrome) düğme görünür; desteklemiyorsa hiç görünmez, elle arama çalışır.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.querySelector('[data-barcode-form]');
  if (!form) return;
  var input = form.querySelector('[data-barcode-input]');
  var dugme = form.querySelector('[data-barcode-scan]');
  if (!input || !dugme) return;
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;

  dugme.hidden = false;

  var katman = null;
  var akis = null;
  var calisiyor = false;

  function kapat() {
    calisiyor = false;
    if (akis) { akis.getTracks().forEach(function (t) { t.stop(); }); akis = null; }
    if (katman) { katman.remove(); katman = null; }
  }

  function kutuAc() {
    katman = document.createElement('div');
    katman.className = 'scan-layer';
    katman.innerHTML = '<div class="scan-box">'
      + '<video playsinline muted autoplay></video>'
      + '<div class="scan-frame"></div>'
      + '<p>Barkodu çerçevenin içine getirin</p>'
      + '<button type="button" class="btn">Kapat</button>'
      + '</div>';
    document.body.appendChild(katman);
    katman.querySelector('button').addEventListener('click', kapat);
    katman.addEventListener('click', function (e) { if (e.target === katman) kapat(); });
    return katman.querySelector('video');
  }

  dugme.addEventListener('click', function () {
    if (calisiyor) { kapat(); return; }
    var video = kutuAc();
    calisiyor = true;

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (s) {
        akis = s;
        video.srcObject = s;
        return video.play();
      })
      .then(function () {
        var dedektor = new window.BarcodeDetector({
          formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'itf']
        });
        (function tara() {
          if (!calisiyor) return;
          dedektor.detect(video).then(function (kodlar) {
            if (kodlar && kodlar.length) {
              var deger = String(kodlar[0].rawValue || '').trim();
              if (deger) {
                kapat();
                input.value = deger;
                form.submit();
                return;
              }
            }
            setTimeout(tara, 250);
          }).catch(function () { setTimeout(tara, 400); });
        })();
      })
      .catch(function (e) {
        kapat();
        alert('Kamera açılamadı: ' + (e && e.message ? e.message : e)
          + '\nTarayıcı kamera iznini engellemiş olabilir; barkodu elle de yazabilirsiniz.');
      });
  });
})();

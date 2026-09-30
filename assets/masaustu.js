/* ==========================================================================
   OptiFlow Masaüstü — SGK sayfası düğmeleri (yalnızca masaüstünde yüklenir).
   window.optiflowDesktop, masaüstü uygulamasının preload betiği tarafından
   YALNIZCA OptiFlow'un kendi adresinde tanımlanır. Dar bir API'dir: Medula'yı
   aç, aktarımı başlat, son aktarım durumunu oku. Satır içi kod yok (CSP).
   ========================================================================== */
(function () {
  'use strict';
  var kart = document.querySelector('[data-masaustu-kart]');
  if (!kart) return;
  var api = window.optiflowDesktop;
  var durumEl = kart.querySelector('[data-masaustu-durum]');
  var aktarBtn = kart.querySelector('[data-masaustu="aktar"]');

  if (!api) {
    // Ör. ayrı açılmış bir pencere: köprü yalnızca ana OptiFlow penceresinde çalışır.
    if (aktarBtn) aktarBtn.hidden = true;
    if (durumEl) durumEl.textContent = 'Medula aktarımı ana OptiFlow penceresinden yapılır.';
    return;
  }

  function saat(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
  }

  function goster(t) {
    if (!durumEl || !t) return;
    durumEl.textContent = '';
    if (!t.asama || t.asama === 'bos') { durumEl.textContent = 'Son aktarım: —'; return; }
    var metin = (t.zaman ? saat(t.zaman) + ' · ' : '') + (t.mesaj || '');
    durumEl.appendChild(document.createTextNode(metin));
    if (t.asama === 'basarili' && t.gelenId) {
      var a = document.createElement('a');
      a.href = 'sgk-aktar.php?gelen=' + encodeURIComponent(String(t.gelenId));
      a.textContent = ' Önizlemeyi aç';
      a.className = 'link';
      durumEl.appendChild(a);
    }
    if (aktarBtn) aktarBtn.disabled = t.asama === 'okunuyor' || t.asama === 'gonderiliyor';
  }

  /* "Medula'yı aç" normal bir bağlantıdır (href = Medula Optik giriş adresi). Uygulama, SGK
     adreslerine giden bağlantıları kendi Medula sekmesinde açar. Böylece Medula adresi sunucudan
     güncellenebilir; kurulu uygulamanın yeniden kurulması gerekmez. */
  if (aktarBtn) {
    aktarBtn.addEventListener('click', function () {
      aktarBtn.disabled = true;
      api.aktar().then(goster, function () { aktarBtn.disabled = false; });
    });
  }
  api.durum().then(function (d) { goster(d && d.aktarim); }, function () {});
  api.aktarimDinle(goster);
})();

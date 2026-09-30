/* OptiFlow — Google Analytics 4 başlatıcı.
   Ölçüm kimliği bu betiği yükleyen <script> etiketinin data-ga4 özniteliğinden okunur
   (tek kaynak: app/pazarlama.php). Satır içi kod kullanılmaz; böylece sıkı CSP
   (script-src 'self' https://www.googletagmanager.com) korunur, 'unsafe-inline' gerekmez. */
(function () {
  var s = document.currentScript;
  var id = s && s.getAttribute('data-ga4');
  if (!id) { return; }
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', id);
})();

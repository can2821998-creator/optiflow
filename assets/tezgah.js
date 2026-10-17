/* ==========================================================================
   4.33.0 — Tezgâh (app/pages/tezgah.php). Satır içi kod yok (CSP).
   window.optiflowDesktop yalnızca OptiFlow Pro'nun ana penceresinde vardır:
     receteSorgula(no) (Pro 5.6.0+) — Medula'yı açar, numarayı reçete sorgu ekranına yazar;
     aktar() / aktarimDinle() — Medula'daki reçeteyi aktarır; aktarım bitince bu sayfa ?gelen=ID ile açılır.
   Pro yoksa (tarayıcı) kullanıcı reçeteyi elle girer.
   ========================================================================== */
(function () {
  'use strict';
  var kart = document.querySelector('[data-tezgah]');
  if (!kart) return;
  var api = window.optiflowDesktop;
  var form = kart.querySelector('[data-tezgah-sorgu]');
  var giris = kart.querySelector('[data-tezgah-no]');
  var durum = kart.querySelector('[data-tezgah-durum]');
  var aktarBtn = kart.querySelector('[data-tezgah-aktar]');

  function yaz(metin, hata) {
    if (!durum) return;
    durum.textContent = metin;
    durum.classList.toggle('text-danger', !!hata);
  }

  function temizle(s) {
    return String(s || '').replace(/[\s-]+/g, '').toLocaleUpperCase('tr-TR').replace(/İ/g, 'I');
  }

  if (!api) {
    yaz('Medula bağlantısı OptiFlow Pro (masaüstü) ile çalışır. Bu tarayıcıda reçeteyi “Reçeteyi elle gir” ile yazabilirsiniz.');
    if (form) form.addEventListener('submit', function (e) { e.preventDefault(); });
    return;
  }

  if (aktarBtn) {
    aktarBtn.hidden = false;
    aktarBtn.addEventListener('click', function () {
      aktarBtn.disabled = true;
      api.aktar().then(function (t) {
        aktarBtn.disabled = false;
        if (t && t.asama === 'hata') yaz(t.mesaj || 'Aktarılamadı.', true);
      }, function () { aktarBtn.disabled = false; });
    });
  }

  // Aktarım (bu düğmeyle ya da Pro'nun üst çubuğundan) bitince kayıt formuna geç.
  api.aktarimDinle(function (t) {
    if (t && t.asama === 'basarili' && t.gelenId) {
      window.location.href = 'tezgah.php?gelen=' + encodeURIComponent(String(t.gelenId));
    } else if (t && t.asama === 'hata') {
      yaz(t.mesaj || 'Aktarılamadı.', true);
    }
  });

  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var no = temizle(giris && giris.value);
    if (!/^[0-9A-Z]{4,12}$/.test(no) || !/\d/.test(no)) {
      yaz('e-Reçete numarası 4–12 harf/rakam olmalı.', true);
      return;
    }
    if (typeof api.receteSorgula !== 'function') {
      // Eski Pro: numarayı panoya kopyala, Medula'yı aç; kullanıcı yapıştırır.
      try { navigator.clipboard.writeText(no); } catch (x) { /* pano yoksa önemli değil */ }
      api.medulaAc();
      yaz('Medula açıldı; numara (' + no + ') panoya kopyalandı — sorgu ekranına yapıştırın. Pro\'yu güncelleyince numara kendiliğinden yazılır.');
      return;
    }
    yaz('Medula açılıyor…');
    api.receteSorgula(no).then(function (r) {
      yaz((r && r.mesaj) || 'Medula açıldı.', !(r && r.ok));
    }, function () { yaz('Medula açılamadı.', true); });
  });
})();

/* 4.38.0 — tek ekran formu: yakın alanları yalnız yakın/ayrı gözlükte, ödeme yöntemi yalnız kapora girilince. */
(function () {
  'use strict';
  var form = document.querySelector('.tezgah-form');
  if (!form) return;
  var tasarim = form.querySelector('select[name="lens_design"]');
  var yakinlar = form.querySelectorAll('[data-yakin-alan]');
  function yakinGuncelle() {
    var v = tasarim ? tasarim.value : '';
    var goster = v === 'ayri_uzak_yakin' || v === 'tek_odak_yakin';
    for (var i = 0; i < yakinlar.length; i++) yakinlar[i].hidden = !goster;
  }
  if (tasarim) { tasarim.addEventListener('change', yakinGuncelle); yakinGuncelle(); }
  var kapora = form.querySelector('[data-kapora]');
  var yontem = form.querySelector('[data-kapora-yontem]');
  function kaporaGuncelle() {
    if (!kapora || !yontem) return;
    var n = parseFloat(String(kapora.value).replace(/\./g, '').replace(',', '.'));
    yontem.hidden = !(n > 0);
  }
  if (kapora) { kapora.addEventListener('input', kaporaGuncelle); kaporaGuncelle(); }
})();

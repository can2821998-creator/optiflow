/* 4.21.0 — Yeni teklif ekranı (teklif-yeni.php): canlı hesap.
   Hesap app/teklif.php teklif_hesapla() ile aynıdır (önce SGK, sonra iskonto); sunucu kaydederken yeniden hesaplar.
   Kataloğdan cam seçilince fiyat ve SGK kullanım şekli önerilir; elle değiştirilen alana bir daha dokunulmaz.
   4.26.0 — 1–3 gözlük bloğu ([data-gozluk]): her bloğun kendi çerçevesi, camları, kullanım şekli ve SGK payı; iskonto
   ortak. Kaldırılan blok gizlenir ve alanları devre dışı kalır (gönderilmez, tarayıcı doğrulamasına takılmaz). */
(function () {
  'use strict';
  var form = document.querySelector('[data-teklif]');
  if (!form) return;

  var sgkTahmin = {};
  try { sgkTahmin = JSON.parse(form.getAttribute('data-sgk-tahmin') || '{}'); } catch (e) { sgkTahmin = {}; }
  var iskontoMax = parseFloat(form.getAttribute('data-iskonto-max') || '100');

  function sayi(ham) {
    // parse_money (helpers.php) ile aynı kural: "1.250,50" · "1250.5" · "1.250" · "12,5"
    var s = String(ham || '').replace(/[\s₺]|TL/gi, '');
    if (s === '') return null;
    if (!/^-?[0-9.,]+$/.test(s)) return NaN;
    var v = s.lastIndexOf(','), n = s.lastIndexOf('.');
    if (v !== -1 && n !== -1) {
      s = v > n ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    } else if (v !== -1) {
      s = (s.split(',').length > 2) ? s.replace(/,/g, '') : s.replace(',', '.');
    } else if (n !== -1 && (s.split('.').length > 2 || /\.\d{3}$/.test(s))) {
      s = s.replace(/\./g, '');
    }
    var x = parseFloat(s);
    return isNaN(x) ? NaN : Math.round(x * 100) / 100;
  }
  function para(x) {
    return x.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  }
  function yuvarla(x) { return Math.round(x * 100) / 100; }
  function hesapla(cam, cerceve, sgk, oran) {
    var ara = yuvarla(Math.max(0, cam) + Math.max(0, cerceve));
    sgk = yuvarla(Math.min(Math.max(0, sgk), ara));
    var kalan = yuvarla(ara - sgk);
    oran = Math.min(Math.max(0, oran), 100);
    var iskonto = yuvarla(kalan * oran / 100);
    return { ara: ara, sgk: sgk, kalan: kalan, oran: oran, iskonto: iskonto, odenecek: yuvarla(kalan - iskonto) };
  }

  var dokunuldu = new WeakSet();
  form.addEventListener('input', function (e) { if (e.isTrusted) dokunuldu.add(e.target); });
  var oneriler = { progressive: 'progressive', ofis: 'ofis', bifokal: 'bifokal', tek_odak: 'tek_odak_uzak' };
  var bloklar = Array.prototype.slice.call(form.querySelectorAll('[data-gozluk]'));

  function etkinMi(blok) { return !blok.hidden; }
  function etkinler() { return bloklar.filter(etkinMi); }

  /* --- Gözlük bloğu --- */
  function blokKur(blok) {
    var cerceveSec = blok.querySelector('[data-cerceve-sec]');
    var cerceveFiyat = blok.querySelector('[data-cerceve-fiyat]');
    var kullanim = blok.querySelector('[data-kullanim]');
    if (cerceveSec) {
      cerceveSec.addEventListener('change', function () {
        var o = cerceveSec.selectedOptions[0];
        if (o && o.value && !dokunuldu.has(cerceveFiyat)) {
          var f = parseFloat(o.getAttribute('data-fiyat') || '0');
          cerceveFiyat.value = f > 0 ? f.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) : '';
        }
      });
    }
    blok.querySelectorAll('[data-secenek]').forEach(function (kutu) {
      var sec = kutu.querySelector('[data-cam-sec]');
      var fiyat = kutu.querySelector('[data-cam-fiyat]');
      var baslik = kutu.querySelector('[data-secenek-ad]');
      sec.addEventListener('change', function () {
        var o = sec.selectedOptions[0];
        // Katalogda fiyatı olmayan cam: fiyat elle yazılmalı (sunucu da boş fiyatı reddeder)
        var fiyatsiz = !!(o && o.value && !o.getAttribute('data-fiyat'));
        fiyat.required = fiyatsiz;
        fiyat.placeholder = fiyatsiz ? 'Fiyat sorulur — yazın' : 'Katalog fiyatı';
        if (o && o.value) {
          if (!dokunuldu.has(fiyat)) {
            var f = o.getAttribute('data-fiyat');
            fiyat.value = f ? parseFloat(f).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) : '';
          }
          if (!dokunuldu.has(baslik) && o.getAttribute('data-segment')) baslik.placeholder = o.getAttribute('data-segment');
          // Bloğun ilk seçeneğinin tasarımı SGK kullanım şeklini önerir (elle seçilmediyse)
          if (kutu.getAttribute('data-secenek') === '1' && kullanim && !dokunuldu.has(kullanim)) {
            var k = oneriler[o.getAttribute('data-tasarim')] || 'tek_odak_uzak';
            // yakın gözlükte tek odak cam "yakın" kalır
            if (k === 'tek_odak_uzak' && kullanim.value === 'tek_odak_yakin') k = 'tek_odak_yakin';
            if (kullanim.querySelector('option[value="' + k + '"]')) kullanim.value = k;
          }
        }
      });
    });
    if (kullanim) kullanim.addEventListener('change', function () { dokunuldu.add(kullanim); });
  }
  bloklar.forEach(blokKur);

  function blokGuncelle(blok) {
    var tur = blok.querySelector('[data-cerceve-tur]:checked');
    tur = tur ? tur.value : 'stok';
    blok.querySelectorAll('[data-cerceve]').forEach(function (el) {
      el.hidden = el.getAttribute('data-cerceve').split(',').indexOf(tur) === -1;
    });
    blok.querySelectorAll('[data-cerceve-tur]').forEach(function (r) { r.closest('.chip').classList.toggle('active', r.checked); });
    var sgkVar = blok.querySelector('[data-sgk-var]');
    var sgkAlan = blok.querySelector('[data-sgk-alan]');
    var sgkTutar = blok.querySelector('[data-sgk-tutar]');
    var kullanim = blok.querySelector('[data-kullanim]');
    if (sgkAlan) sgkAlan.hidden = !(sgkVar && sgkVar.checked);
    var tahmin = parseFloat(sgkTahmin[kullanim ? kullanim.value : 'tek_odak_uzak'] || 0);
    if (sgkTutar) sgkTutar.placeholder = 'Tahmin: ' + para(tahmin);
    var sgk = 0;
    if (sgkVar && sgkVar.checked) {
      var elle = sayi(sgkTutar.value);
      sgk = elle !== null && !isNaN(elle) ? elle : tahmin;
    }
    var cerceve = tur === 'kendi' ? 0 : (sayi(blok.querySelector('[data-cerceve-fiyat]').value) || 0);
    return { tur: tur, sgk: sgk, cerceve: cerceve };
  }

  /* --- Gözlük ekle / kaldır --- */
  var ekleDugme = form.querySelector('[data-gozluk-ekle]');
  function alanlariAyarla(blok, acik) {
    blok.hidden = !acik;
    var aktif = blok.querySelector('[data-gozluk-aktif]');
    if (aktif) aktif.value = acik ? '1' : '0';
    blok.querySelectorAll('input, select, textarea, button').forEach(function (el) {
      if (!el.hasAttribute('data-gozluk-kaldir')) el.disabled = !acik;
    });
  }
  function adlariAyarla() {
    var cok = etkinler().length > 1;
    bloklar.forEach(function (b) {
      var ad = b.querySelector('[data-gozluk-ad]');
      if (ad) ad.placeholder = cok ? ad.getAttribute('data-ad-cok') : ad.getAttribute('data-ad-tek');
    });
    if (ekleDugme) ekleDugme.closest('[data-gozluk-ekle-alan]').hidden = etkinler().length >= bloklar.length;
  }
  bloklar.forEach(function (b) { if (b.hidden) alanlariAyarla(b, false); });
  if (ekleDugme) {
    ekleDugme.addEventListener('click', function () {
      var siradaki = bloklar.filter(function (b) { return b.hidden; })[0];
      if (!siradaki) return;
      alanlariAyarla(siradaki, true);
      adlariAyarla();
      guncelle();
      var ad = siradaki.querySelector('[data-gozluk-ad]');
      siradaki.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (ad) setTimeout(function () { ad.focus({ preventScroll: true }); }, 350);
    });
  }
  form.addEventListener('click', function (e) {
    var dugme = e.target.closest('[data-gozluk-kaldir]');
    if (!dugme) return;
    alanlariAyarla(dugme.closest('[data-gozluk]'), false);
    adlariAyarla();
    guncelle();
  });

  /* --- Özet tablosu --- */
  var iskonto = form.querySelector('[data-iskonto]');
  var ozet = form.querySelector('[data-ozet]');
  function hucre(tr, metin, sinif) {
    var td = document.createElement('td');
    if (sinif) td.className = sinif;
    td.textContent = metin;
    tr.appendChild(td);
    return td;
  }
  function guncelle() {
    var oran = sayi(iskonto.value);
    var oranHata = oran !== null && (isNaN(oran) || oran < 0 || oran > iskontoMax);
    iskonto.setCustomValidity(oranHata ? (iskontoMax >= 100 ? 'İskonto 0–100 arasında olmalı.' : 'İskonto yetkiniz en çok %' + iskontoMax.toLocaleString('tr-TR') + '.') : '');
    iskonto.classList.toggle('is-invalid', oranHata);
    if (oran === null || isNaN(oran)) oran = 0;

    ozet.textContent = '';
    var acik = etkinler();
    var cok = acik.length > 1;
    var adet = 0, enAz = 0, enCok = 0, tamam = 0;
    acik.forEach(function (blok) {
      var d = blokGuncelle(blok);
      var adInput = blok.querySelector('[data-gozluk-ad]');
      var gozlukAdi = adInput ? (adInput.value || adInput.placeholder) : 'Gözlük';
      var tutarlar = [];
      var baslikSatiri = null;
      if (cok) {
        baslikSatiri = document.createElement('tr');
        baslikSatiri.className = 'gozluk-satir';
        hucre(baslikSatiri, gozlukAdi).colSpan = 5;
        ozet.appendChild(baslikSatiri);
      }
      blok.querySelectorAll('[data-secenek]').forEach(function (kutu) {
        var sec = kutu.querySelector('[data-cam-sec]');
        if (!sec.value) return;
        adet++;
        var cam = sayi(kutu.querySelector('[data-cam-fiyat]').value) || 0;
        var h = hesapla(cam, d.cerceve, d.sgk, oran);
        tutarlar.push(h.odenecek);
        var baslik = kutu.querySelector('[data-secenek-ad]');
        var tr = document.createElement('tr');
        var td = hucre(tr, '');
        var b = document.createElement('b');
        b.textContent = (baslik.value || baslik.placeholder);
        td.appendChild(b);
        var sm = document.createElement('small');
        sm.className = 'block muted';
        sm.textContent = sec.selectedOptions[0].textContent.split(' — ')[0];
        td.appendChild(sm);
        hucre(tr, para(h.ara), 'num');
        hucre(tr, h.sgk > 0 ? '−' + para(h.sgk) : '—', 'num');
        hucre(tr, h.iskonto > 0 ? '−' + para(h.iskonto) + ' (%' + h.oran.toLocaleString('tr-TR') + ')' : '—', 'num');
        var son = hucre(tr, '', 'num');
        var bb = document.createElement('b');
        bb.textContent = para(h.odenecek);
        son.appendChild(bb);
        ozet.appendChild(tr);
      });
      if (tutarlar.length) {
        tamam++;
        enAz += Math.min.apply(null, tutarlar);
        enCok += Math.max.apply(null, tutarlar);
      } else if (baslikSatiri) {
        var bos = document.createElement('tr');
        hucre(bos, 'Bu gözlük için cam seçin.', 'muted').colSpan = 5;
        ozet.appendChild(bos);
      }
    });
    if (!adet) {
      var tr = document.createElement('tr');
      hucre(tr, 'Cam seçince hesap burada görünür.', 'muted').colSpan = 5;
      ozet.appendChild(tr);
    } else if (cok && tamam === acik.length) {
      var t = document.createElement('tr');
      t.className = 'toplam-satir';
      hucre(t, 'Toplam (' + acik.length + ' gözlük)').colSpan = 4;
      var tt = hucre(t, '', 'num');
      var tb = document.createElement('b');
      tb.textContent = yuvarla(enAz) === yuvarla(enCok) ? para(enAz) : para(enAz) + ' – ' + para(enCok);
      tt.appendChild(tb);
      ozet.appendChild(t);
    }
  }

  adlariAyarla();
  form.addEventListener('input', guncelle);
  form.addEventListener('change', guncelle);
  guncelle();
})();

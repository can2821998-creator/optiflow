/* ==========================================================================
   OptiFlow 4.17.0 — Hızlı satış ekranı
   Sepet tarayıcıda tutulur; gönderilen yalnızca ürün kimliği, adet, indirim
   (ve fiyatı katalogda olmayan kalemlerde fiyat). Tutarlar sunucuda yeniden
   hesaplanır (app/satis.php). Kullanıcıdan gelen metin her yerde textContent
   ile yazılır.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('satis-form');
  if (!form) return;
  form.hidden = false;

  var $ = function (sel, kok) { return (kok || form).querySelector(sel); };
  var $$ = function (sel, kok) { return Array.prototype.slice.call((kok || form).querySelectorAll(sel)); };

  var ara = $('[data-ara]');
  var oneriler = $('[data-oneriler]');
  var govde = $('[data-sepet]');
  var bos = $('[data-bos]');
  var genelIndirim = $('[data-genel-indirim]');
  var tamamla = $('[data-tamamla]');
  var azami = parseFloat(form.getAttribute('data-azami-indirim') || '100');
  var sepet = [];
  var sayac = 0;
  var gonderiliyor = false;

  /* ---------------- Para ---------------- */
  function para(s) {
    s = String(s == null ? '' : s).replace(/[\s₺]|TL/gi, '');
    if (s === '') return 0;
    if (!/^-?[0-9.,]+$/.test(s)) return NaN;
    var v = s.lastIndexOf(','), n = s.lastIndexOf('.');
    if (v > -1 && n > -1) {
      s = v > n ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    } else if (v > -1) {
      s = (s.split(',').length > 2) ? s.replace(/,/g, '') : s.replace(',', '.');
    } else if (n > -1 && (s.split('.').length > 2 || /\.\d{3}$/.test(s))) {
      s = s.replace(/\./g, '');
    }
    var x = parseFloat(s);
    return isNaN(x) ? NaN : Math.round(x * 100) / 100;
  }
  function tl(x) {
    return (Math.round(x * 100) / 100).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  }
  function yuvarla(x) { return Math.round(x * 100) / 100; }

  /* ---------------- Sepet ---------------- */
  function ekle(u) {
    if (u.tur !== 'serbest') {
      for (var i = 0; i < sepet.length; i++) {
        if (sepet[i].tur === u.tur && sepet[i].id === u.id) {
          sepet[i].adet = Math.min(999, sepet[i].adet + 1);
          ciz(sepet[i].key);
          return;
        }
      }
    }
    var k = {
      key: ++sayac, tur: u.tur, id: u.id || 0, ad: u.ad || '', kategori: u.kategori || '',
      fiyat: (u.fiyat === null || u.fiyat === undefined) ? null : Number(u.fiyat),
      girilen: '', adet: 1, indirim: '', stok: u.stok, takip: !!u.stok_takip
    };
    sepet.push(k);
    ciz(k.key);
  }

  function birim(k) {
    if (k.fiyat !== null && k.tur !== 'serbest') return k.fiyat;
    var v = para(k.girilen);
    return (k.girilen === '' || isNaN(v)) ? null : v;
  }

  function hucre(etiket, cls) {
    var td = document.createElement('td');
    if (cls) td.className = cls;
    if (etiket) td.setAttribute('data-etiket', etiket);
    return td;
  }

  function girdi(deger, ad, ekle) {
    var i = document.createElement('input');
    i.inputMode = 'decimal';
    i.value = deger;
    i.setAttribute('aria-label', ad);
    i.className = 'satis-kucuk';
    if (ekle) i.placeholder = ekle;
    return i;
  }

  function ciz(odak) {
    govde.textContent = '';
    sepet.forEach(function (k) {
      var tr = document.createElement('tr');
      // Ürün
      var td = hucre('Ürün');
      if (k.tur === 'serbest') {
        var ad = document.createElement('input');
        ad.value = k.ad;
        ad.placeholder = 'Kalem adı (ör. burun pedi değişimi)';
        ad.setAttribute('aria-label', 'Serbest kalem adı');
        ad.maxLength = 200;
        ad.addEventListener('input', function () { k.ad = ad.value; hesapla(); });
        td.appendChild(ad);
        if (odak === k.key) setTimeout(function () { ad.focus(); }, 0);
      } else {
        var b = document.createElement('b');
        b.textContent = k.ad;
        td.appendChild(b);
        var sm = document.createElement('small');
        sm.className = 'block muted';
        var ek = k.kategori;
        if (k.takip && typeof k.stok === 'number') {
          ek += ' · stokta ' + k.stok;
          if (k.adet > k.stok) sm.classList.add('text-warn');
        }
        sm.textContent = ek;
        td.appendChild(sm);
      }
      tr.appendChild(td);
      // Adet
      td = hucre('Adet', 'num');
      var adet = document.createElement('span');
      adet.className = 'satis-adet';
      var eksi = document.createElement('button');
      eksi.type = 'button'; eksi.className = 'btn btn-sm'; eksi.textContent = '−'; eksi.setAttribute('aria-label', 'Bir azalt');
      var say = document.createElement('input');
      say.inputMode = 'numeric'; say.value = String(k.adet); say.className = 'satis-kucuk'; say.setAttribute('aria-label', 'Adet');
      var arti = document.createElement('button');
      arti.type = 'button'; arti.className = 'btn btn-sm'; arti.textContent = '+'; arti.setAttribute('aria-label', 'Bir artır');
      eksi.addEventListener('click', function () { if (k.adet > 1) { k.adet--; ciz(); } });
      arti.addEventListener('click', function () { if (k.adet < 999) { k.adet++; ciz(); } });
      say.addEventListener('change', function () {
        var n = parseInt(say.value, 10);
        k.adet = isNaN(n) ? 1 : Math.max(1, Math.min(999, n));
        ciz();
      });
      adet.appendChild(eksi); adet.appendChild(say); adet.appendChild(arti);
      td.appendChild(adet);
      tr.appendChild(td);
      // Birim
      td = hucre('Birim fiyat', 'num');
      if (k.fiyat !== null && k.tur !== 'serbest') {
        td.textContent = tl(k.fiyat);
      } else {
        var f = girdi(k.girilen, 'Birim fiyat', 'fiyat');
        f.addEventListener('input', function () { k.girilen = f.value; hesapla(); });
        td.appendChild(f);
        if (odak === k.key && k.tur !== 'serbest') setTimeout(function () { f.focus(); }, 0);
      }
      tr.appendChild(td);
      // İndirim
      td = hucre('İndirim', 'num');
      var ind = girdi(k.indirim, 'Kalem indirimi', '0,00');
      ind.addEventListener('input', function () { k.indirim = ind.value; hesapla(); });
      td.appendChild(ind);
      tr.appendChild(td);
      // Tutar
      td = hucre('Tutar', 'num');
      var t = document.createElement('b');
      t.setAttribute('data-tutar', String(k.key));
      td.appendChild(t);
      tr.appendChild(td);
      // Sil
      td = hucre('', 'row-actions');
      var sil = document.createElement('button');
      sil.type = 'button'; sil.className = 'icon-btn'; sil.textContent = '×'; sil.title = 'Sepetten çıkar';
      sil.setAttribute('aria-label', k.ad ? k.ad + ' sepetten çıkar' : 'Kalemi sepetten çıkar');
      sil.addEventListener('click', function () {
        sepet = sepet.filter(function (x) { return x.key !== k.key; });
        ciz();
        ara.focus();
      });
      td.appendChild(sil);
      tr.appendChild(td);
      govde.appendChild(tr);
    });
    bos.hidden = sepet.length > 0;
    hesapla();
  }

  function hesapla() {
    var ara_ = 0, kInd = 0, eksikFiyat = false, gecersiz = false;
    sepet.forEach(function (k) {
      var b = birim(k);
      var hucreT = form.querySelector('[data-tutar="' + k.key + '"]');
      var i = para(k.indirim);
      if (isNaN(i) || i < 0) { gecersiz = true; i = 0; }
      if (b === null) {
        eksikFiyat = true;
        if (hucreT) hucreT.textContent = '—';
        return;
      }
      var brut = yuvarla(b * k.adet);
      if (i > brut) gecersiz = true;
      ara_ += brut;
      kInd += i;
      if (hucreT) hucreT.textContent = tl(brut - i);
      if (k.tur === 'serbest' && !k.ad.trim()) eksikFiyat = true;
    });
    var g = para(genelIndirim.value);
    if (isNaN(g) || g < 0) { gecersiz = true; g = 0; }
    var toplam = yuvarla(ara_ - kInd - g);
    if (toplam < 0) gecersiz = true;

    $('[data-ara-toplam]').textContent = tl(ara_);
    $('[data-kalem-indirim]').textContent = kInd > 0 ? '−' + tl(kInd) : tl(0);
    $('[data-toplam]').textContent = tl(Math.max(0, toplam));
    $('[data-kalem-sayisi]').textContent = sepet.length + ' kalem';

    var uyari = $('[data-indirim-uyari]');
    var oran = ara_ > 0 ? (kInd + g) / ara_ * 100 : 0;
    var asim = oran > azami + 0.001;
    uyari.hidden = !asim;
    if (asim) uyari.textContent = 'İndirim %' + oran.toFixed(1).replace('.', ',') + '. Size tanımlı en yüksek oran %' + String(azami).replace('.', ',') + '; yöneticiye onaylatın.';

    var odenen = 0;
    $$('[data-odeme]').forEach(function (inp) {
      var v = para(inp.value);
      if (isNaN(v) || v < 0) { gecersiz = true; return; }
      odenen += v;
    });
    odenen = yuvarla(odenen);
    var kalan = yuvarla(toplam - odenen);
    var kalanEl = $('[data-kalan]');
    kalanEl.className = 'satis-kalan';
    if (!sepet.length) {
      kalanEl.textContent = 'Ödeme bekleniyor';
    } else if (Math.abs(kalan) < 0.01) {
      kalanEl.textContent = 'Ödeme tamam';
      kalanEl.classList.add('tamam');
    } else if (kalan > 0) {
      kalanEl.textContent = 'Kalan: ' + tl(kalan);
    } else {
      kalanEl.textContent = 'Fazla girildi: ' + tl(-kalan);
      kalanEl.classList.add('fazla');
    }

    // Para üstü (yalnızca bilgi)
    var alinan = para($('[data-alinan]').value);
    var nakitInp = form.querySelector('[data-odeme="nakit"]');
    var nakit = nakitInp ? para(nakitInp.value) : 0;
    var pu = $('[data-paraustu]');
    if (!isNaN(alinan) && alinan > 0 && !isNaN(nakit)) {
      pu.textContent = alinan >= nakit ? 'Para üstü: ' + tl(alinan - nakit) : 'Alınan nakit, nakit tutarından az (' + tl(nakit - alinan) + ' eksik)';
    } else {
      pu.textContent = '';
    }

    tamamla.disabled = !sepet.length || eksikFiyat || gecersiz || asim || Math.abs(kalan) >= 0.01 || gonderiliyor;
    return { toplam: toplam, kalan: kalan };
  }

  $$('[data-tamami]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = btn.getAttribute('data-tamami');
      var inp = form.querySelector('[data-odeme="' + m + '"]');
      var h = hesapla();
      if (!inp || h.kalan <= 0) return;
      var mevcut = para(inp.value);
      inp.value = tl(yuvarla((isNaN(mevcut) ? 0 : mevcut) + h.kalan)).replace(' ₺', '');
      hesapla();
    });
  });
  $$('[data-odeme]').concat([genelIndirim, $('[data-alinan]')]).forEach(function (inp) {
    inp.addEventListener('input', hesapla);
  });

  /* ---------------- Ürün arama / barkod ---------------- */
  var zaman = null;
  var sonSonuc = [];

  function oneriGoster(liste) {
    sonSonuc = liste;
    oneriler.textContent = '';
    if (!liste.length) {
      var li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Bulunamadı. Katalogda yoksa Serbest kalem ile ekleyin.';
      oneriler.appendChild(li);
    }
    liste.forEach(function (u, i) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      var ad = document.createElement('b'); ad.textContent = u.ad;
      var alt = document.createElement('small');
      alt.textContent = u.kategori + (u.barkod ? ' · ' + u.barkod : '') + (u.stok_takip ? ' · stok ' + u.stok : '');
      var f = document.createElement('span');
      f.className = 'satis-oneri-fiyat';
      f.textContent = u.fiyat === null ? 'fiyat girilir' : tl(u.fiyat);
      b.appendChild(ad); b.appendChild(alt); b.appendChild(f);
      b.addEventListener('click', function () { sec(u); });
      if (i === 0) b.setAttribute('data-ilk', '');
      li.appendChild(b);
      oneriler.appendChild(li);
    });
    oneriler.hidden = false;
  }

  function sec(u) {
    ekle(u);
    ara.value = '';
    oneriler.hidden = true;
    sonSonuc = [];
    if (u.fiyat !== null) ara.focus();
  }

  function getir(metin) {
    return fetch('hizli-satis.php?ara=' + encodeURIComponent(metin), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
      .then(function (j) { return (j && j.sonuclar) || []; });
  }

  function bildir(metin) {
    var k = document.createElement('div');
    k.className = 'barkod-bildirim hata';
    k.setAttribute('role', 'status');
    k.textContent = metin;
    document.body.appendChild(k);
    setTimeout(function () { k.remove(); }, 3500);
  }

  // Okutulan / Enter'lanan kod: tam barkod eşleşmesi ya da tek sonuç doğrudan sepete girer.
  function kodIsle(kod) {
    kod = (kod || '').trim();
    if (!kod) return;
    clearTimeout(zaman);
    getir(kod).then(function (liste) {
      var tam = liste.filter(function (u) { return u.barkod && u.barkod === kod; });
      if (tam.length === 1 || liste.length === 1) {
        sec(tam[0] || liste[0]);
      } else if (!liste.length && /^\S{5,}$/.test(kod)) {
        bildir('Bu barkod katalogda yok: ' + kod);
        ara.select();
      } else {
        oneriGoster(liste);
      }
    }).catch(function () { bildir('Bağlantı hatası, tekrar deneyin.'); });
  }

  ara.addEventListener('input', function () {
    clearTimeout(zaman);
    var m = ara.value.trim();
    if (m.length < 2) { oneriler.hidden = true; return; }
    zaman = setTimeout(function () {
      getir(m).then(function (liste) { if (ara.value.trim() === m) oneriGoster(liste); }).catch(function () {});
    }, 220);
  });
  ara.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      kodIsle(ara.value);
    } else if (e.key === 'Escape') {
      oneriler.hidden = true;
    } else if (e.key === 'ArrowDown' && !oneriler.hidden) {
      var ilk = oneriler.querySelector('button');
      if (ilk) { e.preventDefault(); ilk.focus(); }
    }
  });
  // barkod.js (okuyucu modu): odak başka yerdeyken okutulan kod buraya gelir
  ara.addEventListener('barkod', function (e) { kodIsle(e.detail); });
  oneriler.addEventListener('keydown', function (e) {
    var dugmeler = $$('button', oneriler);
    var i = dugmeler.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' && i < dugmeler.length - 1) { e.preventDefault(); dugmeler[i + 1].focus(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); if (i > 0) dugmeler[i - 1].focus(); else ara.focus(); }
    if (e.key === 'Escape') { oneriler.hidden = true; ara.focus(); }
  });
  document.addEventListener('click', function (e) {
    if (!oneriler.contains(e.target) && e.target !== ara) oneriler.hidden = true;
  });

  $('[data-serbest]').addEventListener('click', function () {
    ekle({ tur: 'serbest', id: 0, ad: '', kategori: 'Serbest kalem', fiyat: null });
  });

  /* ---------------- Müşteri (isteğe bağlı) ---------------- */
  var mAra = $('[data-musteri-ara]');
  var mOneri = $('[data-musteri-oneri]');
  var mSecili = $('[data-musteri-secili]');
  var mId = $('[data-musteri-id]');
  var mZaman = null;
  mAra.addEventListener('input', function () {
    clearTimeout(mZaman);
    var m = mAra.value.trim();
    if (m.length < 2) { mOneri.hidden = true; return; }
    mZaman = setTimeout(function () {
      fetch('hizli-satis.php?musteri=' + encodeURIComponent(m), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { sonuclar: [] }; })
        .then(function (j) {
          mOneri.textContent = '';
          (j.sonuclar || []).forEach(function (c) {
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button';
            var ad = document.createElement('b'); ad.textContent = c.ad;
            var t = document.createElement('small'); t.textContent = c.tel;
            b.appendChild(ad); b.appendChild(t);
            b.addEventListener('click', function () {
              mId.value = String(c.id);
              mSecili.textContent = '';
              var s = document.createElement('b'); s.textContent = c.ad;
              var kaldir = document.createElement('button');
              kaldir.type = 'button'; kaldir.className = 'btn btn-sm'; kaldir.textContent = 'Kaldır';
              kaldir.addEventListener('click', function () { mId.value = ''; mSecili.hidden = true; mAra.value = ''; });
              mSecili.appendChild(document.createTextNode('Satış bu müşteriye bağlanacak: '));
              mSecili.appendChild(s);
              mSecili.appendChild(document.createTextNode(' '));
              mSecili.appendChild(kaldir);
              mSecili.hidden = false;
              mOneri.hidden = true;
            });
            li.appendChild(b);
            mOneri.appendChild(li);
          });
          if (!(j.sonuclar || []).length) {
            var li = document.createElement('li'); li.className = 'muted'; li.textContent = 'Müşteri bulunamadı.';
            mOneri.appendChild(li);
          }
          mOneri.hidden = false;
        }).catch(function () {});
    }, 250);
  });

  /* ---------------- Gönder ---------------- */
  form.addEventListener('submit', function (e) {
    var h = hesapla();
    if (tamamla.disabled || gonderiliyor) { e.preventDefault(); return; }
    $('[data-sepet-json]').value = JSON.stringify(sepet.map(function (k) {
      var o = { tur: k.tur, id: k.id, adet: k.adet, indirim: para(k.indirim) || 0 };
      if (k.tur === 'serbest') o.ad = k.ad.trim();
      if (k.fiyat === null || k.tur === 'serbest') o.fiyat = birim(k);
      return o;
    }));
    $('[data-odeme-json]').value = JSON.stringify($$('[data-odeme]').map(function (inp) {
      return { method: inp.getAttribute('data-odeme'), amount: para(inp.value) || 0 };
    }).filter(function (o) { return o.amount > 0; }));
    if (h.toplam < 0) { e.preventDefault(); return; }
    gonderiliyor = true;
    tamamla.disabled = true;
    tamamla.textContent = 'Kaydediliyor…';
  });
  // Formdaki Enter (ödeme alanları vb.) yanlışlıkla satışı tamamlamasın
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target !== ara) e.preventDefault();
  });

  /* ---------------- Kamera (BarcodeDetector varsa) ---------------- */
  var kamera = $('[data-kamera]');
  if (kamera && 'BarcodeDetector' in window && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    kamera.hidden = false;
    kamera.addEventListener('click', function () {
      var katman = document.createElement('div');
      katman.className = 'scan-layer';
      var kutu = document.createElement('div'); kutu.className = 'scan-box';
      var video = document.createElement('video'); video.setAttribute('playsinline', ''); video.muted = true; video.autoplay = true;
      var cer = document.createElement('div'); cer.className = 'scan-frame';
      var p = document.createElement('p'); p.textContent = 'Barkodu çerçevenin içine getirin';
      var kapatB = document.createElement('button'); kapatB.type = 'button'; kapatB.className = 'btn'; kapatB.textContent = 'Kapat';
      kutu.appendChild(video); kutu.appendChild(cer); kutu.appendChild(p); kutu.appendChild(kapatB);
      katman.appendChild(kutu);
      document.body.appendChild(katman);
      var akis = null, calis = true;
      function kapat() {
        calis = false;
        if (akis) akis.getTracks().forEach(function (t) { t.stop(); });
        katman.remove();
        ara.focus();
      }
      kapatB.addEventListener('click', kapat);
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
        akis = s; video.srcObject = s; return video.play();
      }).then(function () {
        var d = new window.BarcodeDetector({ formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'itf', 'data_matrix'] });
        (function tara() {
          if (!calis) return;
          d.detect(video).then(function (kodlar) {
            if (kodlar && kodlar.length) { var v = kodlar[0].rawValue; kapat(); kodIsle(v); return; }
            setTimeout(tara, 180);
          }).catch(function () { setTimeout(tara, 400); });
        })();
      }).catch(function () { kapat(); bildir('Kameraya erişilemedi.'); });
    });
  }

  ciz();
  ara.focus();
})();

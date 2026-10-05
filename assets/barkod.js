/* ==========================================================================
   OptiFlow 4.12.0 — Barkod okuyucu modu (yalnızca özellik açıkken yüklenir).

   USB barkod okuyucular klavye gibi çalışır: karakterleri çok hızlı yazar ve
   Enter'a basar. Bu betik yazı alanı seçili DEĞİLKEN gelen hızlı tuş dizisini
   yakalar, barkod.php ile çözer ve ilgili kaydı açar. Yazı alanına odaklıyken
   okutulan kod olduğu gibi alana yazılır (ör. çerçeve formundaki barkod alanı).
   ========================================================================== */
(function () {
  'use strict';
  var HIZ_MS = 45;       // okuyucuda tuşlar arası süre (insanlar genelde 80 ms üstü)
  var EN_AZ = 5;
  var tampon = '';
  var son = 0;
  var hizli = true;

  function yaziAlani(el) {
    if (!el) return false;
    if (el.isContentEditable) return true;
    var t = (el.tagName || '').toLowerCase();
    if (t === 'textarea' || t === 'select') return true;
    if (t === 'input') {
      var tip = (el.type || 'text').toLowerCase();
      return ['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'range', 'color'].indexOf(tip) === -1;
    }
    return false;
  }

  function bildir(metin, hata) {
    var k = document.createElement('div');
    k.className = 'barkod-bildirim' + (hata ? ' hata' : '');
    k.setAttribute('role', 'status');
    k.textContent = metin;
    document.body.appendChild(k);
    setTimeout(function () { k.remove(); }, hata ? 4000 : 1800);
  }

  function coz(kod) {
    bildir('Okundu: ' + kod);
    fetch('barkod.php?json=1&kod=' + encodeURIComponent(kod), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j) { bildir('Kod çözülemedi', true); return; }
        // Yalnızca bu kurulumun içindeki göreli adreslere gidilir.
        if (j.hedef && /^[a-z0-9-]+\.php(\?[^#]*)?$/i.test(j.hedef)) {
          window.location.href = j.hedef;
        } else {
          bildir(j.etiket || 'Kayıt bulunamadı', true);
        }
      })
      .catch(function () { bildir('Bağlantı hatası', true); });
  }

  /* GS1 alan ayırıcısı (GS, ASCII 29). Okuyucular klavye modunda bunu çoğunlukla Ctrl+] olarak
     gönderir (Türkçe Q klavyede aynı fiziksel tuş: Ctrl+Ü). Yakalanmazsa ÜTS karekodundaki
     parti / seri numarası doğru bölünemez. Yalnızca hızlı okuyucu dizisinin İÇİNDE işlenir;
     tek başına basılan Ctrl+] / Ctrl+Ü'ye dokunulmaz. */
  function gsMi(e) {
    if (e.key === '\u001d' || e.key === 'GroupSeparator' || e.keyCode === 29) return true;
    return e.ctrlKey && !e.altKey && !e.metaKey && (e.code === 'BracketRight' || e.key === ']');
  }

  document.addEventListener('keydown', function (e) {
    var simdi = Date.now();
    if (gsMi(e) && tampon && simdi - son <= HIZ_MS * 3) {
      e.preventDefault();
      tampon += '\u001d';
      son = simdi;
      var el = document.activeElement;
      if (yaziAlani(el) && typeof el.setRangeText === 'function') {
        try {
          el.setRangeText('\u001d', el.selectionStart, el.selectionEnd, 'end');
          el.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (x) { /* number/email gibi alanlar seçim desteklemez */ }
      }
      return;
    }
    if (e.ctrlKey || e.altKey || e.metaKey) return;
    var alan = yaziAlani(document.activeElement);
    if (e.key === 'Enter') {
      var kod = tampon;
      var okuyucu = hizli && kod.length >= EN_AZ && (simdi - son) < 150;
      tampon = '';
      hizli = true;
      if (okuyucu && !alan) {
        e.preventDefault();
        // 4.17.0: sayfa okunan kodu kendisi işliyorsa (ör. hızlı satış sepeti) oraya ver, sayfadan çıkma.
        var hedef = document.querySelector('[data-barkod-hedef]');
        if (hedef) {
          hedef.value = kod;
          hedef.dispatchEvent(new CustomEvent('barkod', { detail: kod, bubbles: true }));
          return;
        }
        coz(kod);
      }
      return;
    }
    if (e.key.length !== 1) return;
    if (tampon && simdi - son > HIZ_MS) {
      // yavaş yazım: insan; tamponu sıfırla ve bu tuşla yeniden başla
      tampon = '';
      hizli = true;
    }
    tampon += e.key;
    son = simdi;
    if (tampon.length > 200) { tampon = ''; }
    // Yazı alanı dışındayken okuyucunun yazdıkları sayfada kısayol tetiklemesin
    if (!alan && tampon.length > 1) { e.preventDefault(); }
  }, true);
})();

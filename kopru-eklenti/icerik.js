/* ==========================================================================
   SGK köprüsü — içerik betiği
   Medula Optik sayfasına "Atölyeye aktar" düğmesi ekler.

   ÖNEMLİ: Medula Optik reçete ekranında sferik/silendirik/aks değerleri
   <input> ve <select> kutularının İÇİNDEDİR; sayfayı kopyaladığınızda bu
   değerler metne girmez. Bu yüzden betik sayfayı DOM sırasına göre dolaşır ve
   kutulardaki değerleri «…» işaretiyle metnin doğru yerine yerleştirir:

       SAĞ CAM  Cam «1» +/- «-» Sferik «1,50» +/- «-» Silendirik «0,75» Aks «90»

   Otomatik çalışmaz, arka planda hiçbir şey göndermez, giriş bilgisi okumaz.
   ========================================================================== */
(function () {
  'use strict';
  if (window.__optiflowKopru) return;
  window.__optiflowKopru = true;
  if (window.top !== window.self && (document.body ? document.body.innerText.trim().length : 0) < 60) return;

  /* ---------- Sayfayı, kutulardaki değerlerle birlikte düz metne çevir ---------- */
  function gorunur(el) {
    if (!el || !el.getBoundingClientRect) return false;
    if (el.type === 'hidden' || el.type === 'password') return false;
    var st = window.getComputedStyle(el);
    if (!st || st.display === 'none' || st.visibility === 'hidden') return false;
    return true;
  }

  function kutuDegeri(el) {
    var ad = el.tagName.toLowerCase();
    if (ad === 'select') {
      var o = el.options && el.options[el.selectedIndex];
      var v = o ? (o.text || o.value) : '';
      v = String(v).trim();
      return (v === '' || /^se[çc]iniz$/i.test(v)) ? '' : v;
    }
    if (el.type === 'checkbox' || el.type === 'radio') {
      return el.checked ? (el.value && el.value !== 'on' ? el.value : 'evet') : '';
    }
    return String(el.value == null ? '' : el.value).trim();
  }

  /* Düğme/gizli alanlar metne girmesin */
  function kutuSayilirMi(el) {
    var ad = el.tagName.toLowerCase();
    if (ad !== 'input') return true;
    var t = (el.type || 'text').toLowerCase();
    return ['button', 'submit', 'reset', 'image', 'file', 'hidden', 'password'].indexOf(t) === -1;
  }

  function sayfaMetni() {
    var parcalar = [];
    var yurutec = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
      acceptNode: function (d) {
        if (d.nodeType === Node.TEXT_NODE) {
          var ust = d.parentElement ? d.parentElement.tagName.toLowerCase() : '';
          if (ust === 'textarea') return NodeFilter.FILTER_REJECT;   // değerini «…» olarak alıyoruz
          return d.nodeValue && d.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
        var ad = d.tagName ? d.tagName.toLowerCase() : '';
        if (ad === 'script' || ad === 'style' || ad === 'noscript') return NodeFilter.FILTER_REJECT;
        /* Açılır listenin seçenek metinleri metne girmesin; seçili değeri
           select'in kendisinden «…» olarak alıyoruz. */
        if (ad === 'option' || ad === 'optgroup' || ad === 'datalist') return NodeFilter.FILTER_REJECT;
        if (ad === 'input' || ad === 'select' || ad === 'textarea') return NodeFilter.FILTER_ACCEPT;
        if (ad === 'td' || ad === 'th') return NodeFilter.FILTER_ACCEPT;   // hücre ayracı (sekme)
        if (ad === 'tr' || ad === 'br' || ad === 'div' || ad === 'p' || ad === 'table' || ad === 'li') {
          return NodeFilter.FILTER_ACCEPT;   // satır sonu işareti için
        }
        return NodeFilter.FILTER_SKIP;
      },
    });

    var d;
    while ((d = yurutec.nextNode())) {
      if (d.nodeType === Node.TEXT_NODE) {
        parcalar.push(d.nodeValue.replace(/\s+/g, ' ').trim());
        continue;
      }
      var ad = d.tagName.toLowerCase();
      if (ad === 'input' || ad === 'select' || ad === 'textarea') {
        if (!gorunur(d) || !kutuSayilirMi(d)) continue;
        /* Boş kutular da «» olarak yazılır: sütun sırası bozulmasın
           (Cam · +/- · Sferik · +/- · Silendirik · Aks hizası buna bağlı). */
        parcalar.push('«' + kutuDegeri(d) + '»');
        continue;
      }
      if (ad === 'td' || ad === 'th') { parcalar.push('\t'); continue; }
      parcalar.push('\n');
    }

    return parcalar.join(' ')
      .replace(/[ ]*\t[ ]*/g, '\t')
      .replace(/[ \t]*\n[ \t]*/g, '\n')
      .replace(/\t{2,}/g, '\t')
      .replace(/\n{3,}/g, '\n\n')
      .replace(/[ ]{2,}/g, ' ')
      .trim();
  }

  /* ---------- Arayüz ---------- */
  var dugme = document.createElement('button');
  dugme.type = 'button';
  dugme.id = 'optiflow-kopru-dugme';
  dugme.textContent = 'Atölyeye aktar';

  var bilgi = document.createElement('div');
  bilgi.id = 'optiflow-kopru-bilgi';
  bilgi.hidden = true;

  function durum(metin, tip) {
    bilgi.hidden = false;
    bilgi.textContent = metin;
    bilgi.className = tip || '';
    clearTimeout(durum.z);
    durum.z = setTimeout(function () { bilgi.hidden = true; }, 8000);
  }

  dugme.addEventListener('click', function () {
    var metin;
    try {
      metin = sayfaMetni();
    } catch (e) {
      metin = document.body ? document.body.innerText : '';
    }
    var secili = String(window.getSelection ? window.getSelection() : '').trim();
    if (secili.length > 80 && metin.indexOf(secili.slice(0, 40)) === -1) {
      metin = secili + '\n' + metin;
    }
    if (!metin || metin.length < 20) { durum('Sayfada aktarılacak bilgi bulunamadı.', 'hata'); return; }

    dugme.disabled = true;
    var eski = dugme.textContent;
    dugme.textContent = 'Aktarılıyor…';

    chrome.runtime.sendMessage(
      { tip: 'aktar', metin: metin.slice(0, 180000), baslik: document.title || 'Medula Optik' },
      function (cevap) {
        dugme.disabled = false;
        dugme.textContent = eski;
        if (!cevap) { durum('Eklenti yanıt vermedi.', 'hata'); return; }
        if (cevap.ok) {
          durum('Aktarıldı ✓ ' + (cevap.ozet || '') + ' — Atölyedeki "Köprüden gelenler" listesinde.', 'ok');
        } else {
          durum('Aktarılamadı: ' + (cevap.hata || 'bilinmeyen hata'), 'hata');
        }
      }
    );
  });

  function yerlestir() {
    if (!document.body || document.getElementById('optiflow-kopru-dugme')) return;
    document.body.appendChild(dugme);
    document.body.appendChild(bilgi);
  }
  yerlestir();
  setTimeout(yerlestir, 1500);
})();

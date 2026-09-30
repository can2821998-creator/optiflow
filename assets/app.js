/* OptiFlow · genel arayüz davranışları (bağımlılık yok) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('input[name="csrf"]') || {}).value || '';

  /* Mobil menü */
  $$('[data-toggle-sidebar]').forEach(function (b) {
    b.addEventListener('click', function () { document.body.classList.add('nav-open'); });
  });
  $$('[data-close-sidebar]').forEach(function (b) {
    b.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.body.classList.remove('nav-open');
      $$('details.menu[open]').forEach(function (d) { d.open = false; });
    }
  });

  /* Uyarı kapatma */
  $$('[data-dismiss]').forEach(function (b) {
    b.addEventListener('click', function () { b.parentNode.remove(); });
  });

  /* Gizli bakiye */
  $$('[data-reveal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var masked = b.classList.toggle('is-masked');
      b.setAttribute('aria-pressed', masked ? 'false' : 'true');
    });
  });

  /* Açılır menüler dışarı tıklanınca kapanır */
  document.addEventListener('click', function (e) {
    $$('details.menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
  });

  /* Satırın tamamı tıklanabilir */
  $$('.rows-link tbody tr').forEach(function (tr) {
    var link = $('a.cell-link', tr);
    if (!link) return;
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, label')) return;
      if (e.metaKey || e.ctrlKey) window.open(link.href, '_blank'); else location.href = link.href;
    });
  });

  /* Onay isteyen formlar */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); e.stopImmediatePropagation(); }
    });
  });

  /* Kaydedilmemiş değişiklik uyarısı + çift gönderim engeli */
  var dirty = false;
  $$('form[data-guard]').forEach(function (f) {
    f.addEventListener('input', function () { dirty = true; });
    f.addEventListener('change', function () { dirty = true; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    dirty = false;
    var f = e.target;
    setTimeout(function () {
      $$('button:not([type="button"])', f).forEach(function (b) { b.disabled = true; });
      var submitter = e.submitter;
      if (submitter && submitter.name) {
        // Devre dışı butonun değeri gönderilmez; gizli alanla koru.
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
        f.appendChild(h);
      }
    }, 0);
  });

  /* "Kalanın tamamı" */
  $$('[data-fill-balance]').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = $('input[name="amount"]', b.closest('form'));
      if (input) { input.value = b.getAttribute('data-fill-balance'); input.focus(); }
    });
  });

  /* WhatsApp açılışını işlem geçmişine yaz */
  $$('[data-wa-log]').forEach(function (a) {
    a.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('order_id', a.getAttribute('data-order'));
      fd.append('template', a.getAttribute('data-template'));
      if (navigator.sendBeacon) navigator.sendBeacon('api.php?action=wa_log', fd);
      else fetch('api.php?action=wa_log', { method: 'POST', body: fd, credentials: 'same-origin' });
      var d = a.closest('details'); if (d) d.open = false;
    });
  });

  /* Şablon değişkeni ekleme */
  var lastTextarea = null;
  $$('[data-template-body]').forEach(function (t) { t.addEventListener('focus', function () { lastTextarea = t; }); });
  $$('[data-insert]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = lastTextarea || $('[data-template-body]');
      if (!t) return;
      var s = t.selectionStart || t.value.length, token = b.getAttribute('data-insert');
      t.value = t.value.slice(0, s) + token + t.value.slice(t.selectionEnd || s);
      t.focus();
      t.selectionStart = t.selectionEnd = s + token.length;
    });
  });

  /* Stok: toplu seçim */
  $$('form[data-bulk]').forEach(function (f) {
    var boxes = $$('input[name="items[]"]', f);
    var all = $('[data-check-all]', f);
    var bar = $('[data-bulk-bar]', f);
    var countEl = $('[data-selected-count]', f);
    function refresh() {
      var n = boxes.filter(function (b) { return b.checked; }).length;
      countEl.textContent = n + ' seçili';
      bar.classList.toggle('has-selection', n > 0);
      $$('[data-needs-selection]', f).forEach(function (b) { b.disabled = n === 0; });
      if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
    }
    if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); refresh(); });
    boxes.forEach(function (b) { b.addEventListener('change', refresh); });
    $$('tbody tr', f).forEach(function (tr) {
      tr.addEventListener('click', function (e) {
        if (e.target.closest('a, input, button')) return;
        var cb = $('input[type="checkbox"]', tr);
        if (cb) { cb.checked = !cb.checked; refresh(); }
      });
    });
    refresh();
  });

  /* Yeni sipariş: müşteri arama */
  var picker = $('[data-customer-picker]');
  if (picker) {
    var idInput = $('[data-customer-id]', picker);
    var picked = $('[data-picked]', picker);
    var body = $('[data-picker-body]', picker);
    var search = $('[data-customer-search]', picker);
    var list = $('[data-suggest]', picker);
    var required = $$('[data-new-required]', picker);
    var timer, active = -1, items = [];

    var setRequired = function (on) { required.forEach(function (i) { i.required = on; }); };
    setRequired(!idInput.value);

    var choose = function (c) {
      idInput.value = c.id;
      $('[data-picked-name]', picker).textContent = c.name;
      $('[data-picked-meta]', picker).textContent = (c.phone || 'Telefon yok') + ' · ' + c.meta;
      $('.avatar', picked).textContent = c.name.split(' ').map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toLocaleUpperCase('tr-TR');
      picked.hidden = false; body.hidden = true; list.hidden = true;
      setRequired(false);
    };
    $('[data-unpick]', picker).addEventListener('click', function () {
      idInput.value = ''; picked.hidden = true; body.hidden = false; setRequired(true);
      search.value = ''; search.focus();
    });
    var render = function () {
      list.innerHTML = '';
      if (!items.length) { list.innerHTML = '<li class="none">Kayıt bulunamadı — aşağıdan yeni müşteri ekleyin.</li>'; list.hidden = false; return; }
      items.forEach(function (c, i) {
        var li = document.createElement('li');
        var b = document.createElement('button');
        b.type = 'button';
        if (i === active) b.className = 'active';
        b.innerHTML = '<span class="avatar sm"></span><span class="cell-main"><b></b><small></small></span>';
        b.querySelector('.avatar').textContent = c.name.split(' ').map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toLocaleUpperCase('tr-TR');
        b.querySelector('b').textContent = c.name;
        b.querySelector('small').textContent = (c.phone || 'Telefon yok') + ' · ' + c.meta;
        b.addEventListener('click', function () { choose(c); });
        li.appendChild(b); list.appendChild(li);
      });
      list.hidden = false;
    };
    search.addEventListener('input', function () {
      clearTimeout(timer);
      var q = search.value.trim();
      if (q.length < 2) { list.hidden = true; return; }
      timer = setTimeout(function () {
        fetch('api.php?action=customers&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { items = d.items || []; active = -1; render(); })
          .catch(function () { list.hidden = true; });
      }, 220);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (list.hidden || active < 0)) { e.preventDefault(); return; }
      if (list.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      if (e.key === 'Enter' && active >= 0) { choose(items[active]); e.preventDefault(); }
    });
  }

  /* Kenar çubuğu genel arama: müşteri, sipariş no, teklif, (yöneticide) tedarikçi. */
  var gs = $('[data-global-search]');
  if (gs) {
    var gsInput = $('[data-global-search-input]', gs);
    var gsList = $('[data-global-search-list]', gs);
    var gsTimer, gsActive = -1, gsItems = [];

    var gsRender = function () {
      gsList.innerHTML = '';
      if (!gsItems.length) { gsList.innerHTML = '<li class="none">Sonuç bulunamadı.</li>'; gsList.hidden = false; return; }
      gsItems.forEach(function (it, i) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = it.url;
        if (i === gsActive) a.className = 'active';
        a.innerHTML = '<span class="cell-main"><b></b><small></small></span>';
        a.querySelector('b').textContent = it.label;
        a.querySelector('small').innerHTML = '<span class="type"></span>' + (it.meta ? ' · ' + it.meta.replace(/</g, '&lt;') : '');
        a.querySelector('small .type').textContent = it.type;
        li.appendChild(a); gsList.appendChild(li);
      });
      gsList.hidden = false;
    };
    gsInput.addEventListener('input', function () {
      clearTimeout(gsTimer);
      var q = gsInput.value.trim();
      if (q.length < 2) { gsList.hidden = true; return; }
      gsTimer = setTimeout(function () {
        fetch('api.php?action=global_search&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { gsItems = d.items || []; gsActive = -1; gsRender(); })
          .catch(function () { gsList.hidden = true; });
      }, 220);
    });
    gsInput.addEventListener('keydown', function (e) {
      if (gsList.hidden || !gsItems.length) return;
      if (e.key === 'ArrowDown') { gsActive = Math.min(gsItems.length - 1, gsActive + 1); gsRender(); e.preventDefault(); }
      if (e.key === 'ArrowUp') { gsActive = Math.max(0, gsActive - 1); gsRender(); e.preventDefault(); }
      if (e.key === 'Enter' && gsActive >= 0) { window.location.href = gsItems[gsActive].url; e.preventDefault(); }
      if (e.key === 'Escape') { gsList.hidden = true; }
    });
    document.addEventListener('click', function (e) {
      if (!gs.contains(e.target)) { gsList.hidden = true; }
    });
  }

  /* Tıklama dalgası: düğmelere dokunsal bir "ışık dalgası" hissi katar. */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('.btn, .icon-btn, .chip');
    if (!el || el.disabled) { return; }
    var rect = el.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height) * 1.3;
    var span = document.createElement('span');
    span.className = 'ripple';
    span.style.width = span.style.height = size + 'px';
    span.style.left = (e.clientX - rect.left - size / 2) + 'px';
    span.style.top = (e.clientY - rect.top - size / 2) + 'px';
    el.appendChild(span);
    span.addEventListener('animationend', function () { span.remove(); });
  });

  /* İstatistik sayıları: yalnızca düz tam sayı gösteren kutucuklar 0'dan gerçek
     değerine sayarak belirir (para birimi/metin içerenlere dokunulmaz). */
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    $$('.stat b').forEach(function (el) {
      var text = el.textContent.trim();
      if (!/^\d{1,5}$/.test(text)) { return; }
      var target = parseInt(text, 10);
      if (!target) { return; }
      el.classList.add('is-counting');
      var dur = 700, start = null;
      el.textContent = '0';
      function step(ts) {
        if (!start) { start = ts; }
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = String(Math.round(eased * target));
        if (p < 1) { requestAnimationFrame(step); } else { el.textContent = String(target); }
      }
      requestAnimationFrame(step);
    });
  }
})();


/* Müşteri takip bağlantısını panoya kopyala */
(function () {
  var dugme = document.querySelector('[data-copy-track]');
  var alan = document.querySelector('[data-track-link]');
  if (!dugme || !alan) return;
  dugme.addEventListener('click', function () {
    var bitir = function () {
      var eski = dugme.textContent;
      dugme.textContent = 'Kopyalandı ✓';
      setTimeout(function () { dugme.textContent = eski; }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(alan.value).then(bitir, function () { alan.select(); });
    } else {
      alan.select();
      try { document.execCommand('copy'); bitir(); } catch (e) { /* yoksay */ }
    }
  });
})();

/* ---------- Kutlama: her yerden çağrılabilen konfeti patlaması ----------
   Sipariş oluşturma, teslimat, kusursuz kasa kapanışı gibi anlarda kullanılır.
   Hareket azaltma tercihine saygı duyar. */
window.celebrate = function (originEl, count) {
  try {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var rect = originEl && originEl.getBoundingClientRect ? originEl.getBoundingClientRect() : null;
    var cx = rect ? rect.left + rect.width / 2 : innerWidth / 2;
    var cy = rect ? rect.top + rect.height / 2 : innerHeight / 3;
    var colors = ['var(--brand)', 'var(--accent)', 'var(--teal)', 'var(--green)', 'var(--brand-bright)'];
    var n = count || 22;
    for (var i = 0; i < n; i++) {
      (function () {
        var el = document.createElement('span');
        el.className = 'confetto';
        var angle = Math.random() * Math.PI * 2;
        var dist = 55 + Math.random() * 95;
        var dx = Math.cos(angle) * dist;
        var dy = Math.sin(angle) * dist - 40;
        var size = 5 + Math.random() * 5;
        el.style.left = cx + 'px';
        el.style.top = cy + 'px';
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        el.style.background = colors[i % colors.length];
        el.style.setProperty('--confetti-end', 'translate(' + dx.toFixed(0) + 'px,' + dy.toFixed(0) + 'px)');
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 950);
      })();
    }
  } catch (e) { /* kutlama başarısız olsa da uygulama akışını bozmaz */ }
};

/* [data-celebrate] içeren her öge sayfa yüklenince bir kez kutlanır. */
document.querySelectorAll('[data-celebrate]').forEach(function (el) {
  setTimeout(function () { celebrate(el); }, 250);
});

/* ---------- Ses efektleri: dosya yok, Web Audio ile anlık üretilir ---------- */
window.playChime = function (kind) {
  try {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) { return; }
    var ctx = new Ctx();
    var notes = kind === 'big' ? [523.25, 659.25, 783.99, 1046.5] : [659.25, 987.77];
    notes.forEach(function (freq, i) {
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      var start = ctx.currentTime + i * 0.09;
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.5);
      osc.connect(gain).connect(ctx.destination);
      osc.start(start);
      osc.stop(start + 0.55);
    });
    setTimeout(function () { ctx.close(); }, 1200);
  } catch (e) { /* ses çalışmasa da akışı bozmaz */ }
};

/* ---------- Büyük kutlama: aylık hedef, kritik satış gibi anlar için havai fişek ---------- */
window.celebrateBig = function (message) {
  try {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    playChime('big');
    var overlay = document.createElement('div');
    overlay.className = 'confetti-overlay';
    document.body.appendChild(overlay);
    var origins = [
      { x: innerWidth * 0.2, y: innerHeight * 0.3 },
      { x: innerWidth * 0.5, y: innerHeight * 0.2 },
      { x: innerWidth * 0.8, y: innerHeight * 0.3 },
    ];
    origins.forEach(function (o, oi) {
      setTimeout(function () {
        var fake = { getBoundingClientRect: function () { return { left: o.x, top: o.y, width: 0, height: 0 }; } };
        celebrate(fake, 34);
      }, oi * 160);
    });
    if (message) {
      var banner = document.createElement('div');
      banner.className = 'crazy-banner';
      banner.textContent = message;
      document.body.appendChild(banner);
      setTimeout(function () { banner.remove(); }, 3200);
    }
    setTimeout(function () { overlay.remove(); }, 2600);
  } catch (e) { /* yoksay */ }
};
document.querySelectorAll('[data-celebrate-big]').forEach(function (el) {
  var onceKey = el.dataset.celebrateOnce;
  if (onceKey) {
    try {
      if (localStorage.getItem('optiflow_once_' + onceKey)) { return; }
      localStorage.setItem('optiflow_once_' + onceKey, '1');
    } catch (e) {}
  }
  setTimeout(function () { celebrateBig(el.dataset.celebrateBig || ''); }, 300);
});

/* ---------- Kritik satış: büyük bir sipariş kapatılınca ekranı sallayan, dövüş oyunu tadında an ---------- */
document.querySelectorAll('[data-critical-sale]').forEach(function (el) {
  setTimeout(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    playChime('big');
    document.body.classList.add('screen-shake');
    setTimeout(function () { document.body.classList.remove('screen-shake'); }, 500);
    var flash = document.createElement('div');
    flash.className = 'crazy-banner crazy-critical';
    flash.textContent = '💥 KRİTİK SATIŞ! 💥';
    document.body.appendChild(flash);
    setTimeout(function () { celebrate(el, 30); }, 120);
    setTimeout(function () { flash.remove(); }, 2600);
  }, 250);
});

/* ---------- Gizli mod: klasik yön tuşu dizisi (Konami kodu, klavye) ---------- */
(function () {
  var sequence = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
  var pos = 0;
  document.addEventListener('keydown', function (e) {
    var key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
    pos = (key === sequence[pos]) ? pos + 1 : (key === sequence[0] ? 1 : 0);
    if (pos === sequence.length) {
      pos = 0;
      triggerDiscoMode();
    }
  });
})();

/* ---------- Gizli mod: dokunmatik alternatif (profil simgesine 7 kez hızlı dokun) ---------- */
(function () {
  var spot = document.querySelector('.sidebar-user .avatar');
  if (!spot) return;
  var taps = 0, timer = null;
  spot.style.cursor = 'pointer';
  spot.addEventListener('click', function () {
    taps++;
    clearTimeout(timer);
    timer = setTimeout(function () { taps = 0; }, 2200);
    if (taps >= 7) {
      taps = 0;
      triggerDiscoMode();
    }
  });
})();

function triggerDiscoMode() {
  document.body.classList.add('disco-mode');
  celebrateBig('🕶️ GİZLİ MOD AÇILDI! OptiFlow efsanesi sizinle! 🕶️');
  setTimeout(function () { document.body.classList.remove('disco-mode'); }, 4000);
}

/* ---------- Atölye falı: günde bir kez, oturum başına eğlenceli bir mesaj ---------- */
(function () {
  var box = document.querySelector('[data-fortune-box]');
  if (!box) return;
  var today = new Date().toISOString().slice(0, 10);
  var seenKey = 'optiflow_fortune_' + today;
  var fortunes = [
    '🔮 Bugün bir müşteri gözlüğüne öyle bir aşık olacak ki arkadaşlarına sizi önerecek.',
    '🔮 Bugün kasanız kuruş şaşmadan kapanacak gibi görünüyor.',
    '🔮 Bugün "sadece bakıyorum" diyen biri en pahalı çerçeveyle çıkacak.',
    '🔮 Bugün atölyede hiçbir cam kırılmayacak — evrenin size bir borcu var.',
    '🔮 Bugün eski bir müşteri geri dönüp "en iyi optikçi burası" diyecek.',
    '🔮 Bugün bir sipariş tam zamanında, hatta erken hazır olacak.',
    '🔮 Bugün biri gözlüğünü unutup almaya gelecek, iyi bir sohbet fırsatı doğacak.',
    '🔮 Bugün kahve molası tam zamanında gelecek. Hak ettiniz.',
  ];
  try {
    if (sessionStorage.getItem(seenKey)) { return; }
  } catch (e) {}
  var pick = fortunes[Math.floor(Math.random() * fortunes.length)];
  var textEl = box.querySelector('[data-fortune-text]');
  if (textEl) { textEl.textContent = pick; }
  box.hidden = false;
  try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
  var closeBtn = box.querySelector('[data-fortune-close]');
  if (closeBtn) { closeBtn.addEventListener('click', function () { box.hidden = true; }); }
})();

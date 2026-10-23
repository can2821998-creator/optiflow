/* OptiFlow tanıtım filmleri — ortak motor.
 * Her film FILM({...}) ile sahne tablosu verir; motor sahneleri sıraya dizer, ciz(t) her kareyi t saniyesine göre
 * çizer (rastgelelik yok). Seslendirme: her sahnenin "ses" metni window.SES listesine düşer; ölçülen ses süreleri
 * window.SES_SURE ile verilirse sahneler sesin sonuna kadar uzar.
 * Karakterler, mağaza ve veriler uydurmadır ("Örnek Optik"); gerçek müşteri verisi kullanılmaz (KVKK). */
(function () {
  'use strict';
  /* ---------- yardımcılar ---------- */
  const $ = (id) => document.getElementById(id);
  const cl = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const p = (t, a, b) => cl((t - a) / (b - a));
  const eo = (x) => 1 - Math.pow(1 - x, 3);
  const eio = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
  const geri = (x) => { const c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2); };
  const lerp = (a, b, x) => a + (b - a) * x;
  const gor = (t, a, b, f = 0.3) => Math.min(p(t, a, a + f), 1 - p(t, b - f, b));
  const css = (el, o) => { for (const k in o) el.style[k] = o[k]; };
  const at = (el, k, v) => el.setAttribute(k, v);
  let AKTIF = 1; // o an çizilen sahnenin katman görünürlüğü (geçişte örtü öğeleri de solar)
  const gorun = (el, v) => { el.style.opacity = (v * AKTIF).toFixed(3); };
  Object.assign(window, { $, cl, p, eo, eio, geri, lerp, gor, css, at, gorun });

  const LOGO = (id) => `<svg viewBox="0 0 100 100" width="100%" height="100%"><defs><linearGradient id="${id}" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs><g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="url(#${id})"/><circle cx="50" cy="50" r="7" fill="url(#${id})"/></g></svg>`;
  window.LOGO = LOGO;
  const IK = {
    ok: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>',
    ok20: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>',
    aktar: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h12M11 6l6 6-6 6M20 5v14"/></svg>',
    ara: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>',
    tel: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/></svg>',
    mesaj: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>',
    belge: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h8M9 17h6"/></svg>',
  };
  window.IK = IK;

  /* ---------- karakter ---------- */
  function kisiSVG(id, o) {
    o = Object.assign({ ceket: '#b98a5e', kol: '#a87a50', pantolon: '#3a2f33', ten: '#eab38c', boyun: '#e0a57d', sac: 'bob', sacRenk: '#3b2a24', atki: '#b4233c', canta: true, biyik: false }, o || {});
    const sac = o.sac === 'bob'
      ? `<path d="M-74 10 Q-84 -80 0 -82 Q84 -80 74 10 L74 46 Q52 54 42 34 L42 -20 Q0 -46 -42 -20 L-42 34 Q-52 54 -74 46 Z" fill="${o.sacRenk}"/>`
      : '';
    const kakul = o.sac === 'bob'
      ? `<path d="M-62 -26 Q-44 -78 8 -70 Q60 -64 64 -18 Q34 -46 -6 -40 Q-40 -36 -62 -26Z" fill="${o.sacRenk}"/>`
      : `<path d="M-63 -8 Q-70 -76 0 -80 Q70 -76 63 -8 Q58 -46 6 -48 Q-44 -50 -63 -8Z" fill="${o.sacRenk}"/>`;
    const kulak = o.sac === 'bob' ? '' : `<circle cx="-61" cy="4" r="13" fill="${o.boyun}"/><circle cx="61" cy="4" r="13" fill="${o.boyun}"/>`;
    const biyik = o.biyik ? `<path d="M-22 18 Q0 8 22 18 Q12 24 0 20 Q-12 24 -22 18Z" fill="${o.sacRenk}"/>` : '';
    const canta = o.canta ? `<path d="M62 -446 L118 -262" stroke="#5a1320" stroke-width="7"/><rect x="88" y="-276" width="82" height="72" rx="16" fill="#7e1528"/><rect x="104" y="-262" width="50" height="8" rx="4" fill="#b4233c"/>` : '';
    const atki = o.atki ? `<rect x="-52" y="-474" width="104" height="36" rx="17" fill="${o.atki}"/><rect x="12" y="-452" width="28" height="92" rx="12" fill="${o.atki}"/>` : `<path d="M-30 -462 L0 -420 L30 -462" fill="#f3ece9"/>`;
    const bacak = (s) => `<g id="${id}B${s}"><rect x="-18" y="0" width="36" height="206" rx="16" fill="${o.pantolon}"/><rect x="-26" y="192" width="52" height="24" rx="12" fill="#1f1719"/></g>`;
    const kol = (s) => `<g id="${id}K${s}"><rect x="-19" y="0" width="38" height="196" rx="19" fill="${o.kol}"/><circle cx="0" cy="200" r="21" fill="${o.ten}"/></g>`;
    return `<g id="${id}" opacity="0">
      <ellipse cx="0" cy="4" rx="96" ry="13" fill="#1b1416" opacity=".09"/>
      ${bacak('S')}${bacak('D')}${kol('S')}
      <path d="M-80 -440 Q-98 -300 -104 -186 L104 -186 Q98 -300 80 -440 Q0 -472 -80 -440Z" fill="${o.ceket}"/>
      <path d="M0 -455 L0 -190" stroke="${o.kol}" stroke-width="4"/>
      <circle cx="-14" cy="-380" r="6" fill="#00000033"/><circle cx="-14" cy="-320" r="6" fill="#00000033"/><circle cx="-14" cy="-260" r="6" fill="#00000033"/>
      ${canta}${kol('D')}
      <rect x="-16" y="-500" width="32" height="40" fill="${o.boyun}"/>
      ${atki}
      <g id="${id}Tel" opacity="0">
        <rect x="-40" y="-70" width="80" height="140" rx="14" fill="#1b1416"/><rect x="-33" y="-60" width="66" height="118" rx="9" fill="#f6eef0"/>
        <rect x="-26" y="-48" width="52" height="18" rx="6" fill="#b4233c"/><rect x="-26" y="-22" width="40" height="7" rx="3" fill="#cbbfc2"/><rect x="-26" y="-8" width="50" height="7" rx="3" fill="#cbbfc2"/>
        <circle cx="-34" cy="40" r="20" fill="${o.ten}"/><circle cx="34" cy="40" r="20" fill="${o.ten}"/></g>
      <g id="${id}Kirik" opacity="0">
        <g fill="rgba(220,235,240,.6)" stroke="#1b1416" stroke-width="7"><rect x="-74" y="-22" width="60" height="44" rx="16"/><rect x="14" y="-22" width="60" height="44" rx="16"/></g>
        <path d="M-14 -8 Q0 -16 14 -8" stroke="#1b1416" stroke-width="7" fill="none"/>
        <path d="M74 -10 L110 -14 L126 26" stroke="#1b1416" stroke-width="7" fill="none" stroke-linecap="round"/>
        <path d="M-74 -10 L-112 -4" stroke="#1b1416" stroke-width="7" stroke-linecap="round"/>
        <circle cx="-34" cy="40" r="20" fill="${o.ten}"/><circle cx="34" cy="40" r="20" fill="${o.ten}"/></g>
      <g id="${id}Kafa">
        ${sac}${kulak}<circle r="62" fill="${o.ten}"/>${kakul}
        <circle cx="-38" cy="18" r="11" fill="#f08f8f" opacity=".3"/><circle cx="38" cy="18" r="11" fill="#f08f8f" opacity=".3"/>
        <g id="${id}Goz"><ellipse cx="-22" cy="0" rx="6" ry="7.5" fill="#2a1f1c"/><ellipse cx="22" cy="0" rx="6" ry="7.5" fill="#2a1f1c"/></g>
        <path d="M-32 -18 Q-22 -24 -12 -18 M12 -18 Q22 -24 32 -18" stroke="${o.sacRenk}" stroke-width="4" fill="none" stroke-linecap="round"/>
        <path d="M-2 4 Q-6 14 2 16" stroke="#00000040" stroke-width="4" fill="none" stroke-linecap="round"/>
        ${biyik}
        <path id="${id}Agiz" d="M-18 28 Q0 38 18 28" fill="none" stroke="#7a3b2e" stroke-width="5" stroke-linecap="round"/>
        <g id="${id}Gozluk" opacity="0" fill="rgba(255,255,255,.18)" stroke="#b4233c" stroke-width="5.5"><rect x="-44" y="-15" width="40" height="30" rx="12"/><rect x="4" y="-15" width="40" height="30" rx="12"/><path d="M-4 -4 Q0 -8 4 -4" fill="none"/></g>
      </g>
      <g id="${id}Par" stroke="#b4233c" stroke-width="5" stroke-linecap="round" opacity="0"><line x1="-88" y1="-610" x2="-110" y2="-632"/><line x1="-96" y1="-560" x2="-124" y2="-560"/><line x1="88" y1="-610" x2="110" y2="-632"/><line x1="96" y1="-560" x2="124" y2="-560"/></g>
    </g>`;
  }
  /** Kişi pozu. t: genel zaman (yürüme ve göz kırpma için). */
  function kisi(id, o) {
    o = Object.assign({ x: 1180, y: 900, yuru: 0, t: 0, tel: 0, kirik: 0, gozluk: 0, gul: 8, kafa: -3, op: 1, kirp: [], par: 0, titre: 0 }, o);
    const g = $(id);
    const faz = o.t * 2 * Math.PI * 1.7;
    const salla = o.yuru ? Math.sin(faz) : 0;
    const zip = o.yuru ? Math.abs(Math.sin(faz)) * 10 : 0;
    at(g, 'transform', `translate(${o.x.toFixed(1)} ${(o.y - zip).toFixed(1)})`);
    g.style.opacity = (o.op * AKTIF).toFixed(3);
    at($(id + 'BS'), 'transform', `translate(-26 -212) rotate(${(salla * 16).toFixed(2)})`);
    at($(id + 'BD'), 'transform', `translate(26 -212) rotate(${(-salla * 16).toFixed(2)})`);
    let ks = -salla * 12, kd = salla * 12;
    if (o.tel || o.kirik) { ks = -21; kd = 21; }
    at($(id + 'KS'), 'transform', `translate(-82 -428) rotate(${ks.toFixed(2)})`);
    at($(id + 'KD'), 'transform', `translate(82 -428) rotate(${kd.toFixed(2)})`);
    const tit = o.titre ? Math.sin(o.t * 90) * 5 : 0;
    $(id + 'Tel').style.opacity = o.tel;
    at($(id + 'Tel'), 'transform', `translate(${tit.toFixed(1)} -262)`);
    $(id + 'Kirik').style.opacity = o.kirik;
    at($(id + 'Kirik'), 'transform', 'translate(0 -262)');
    $(id + 'Gozluk').style.opacity = o.gozluk;
    $(id + 'Par').style.opacity = o.par;
    at($(id + 'Agiz'), 'd', `M-18 28 Q0 ${(28 + o.gul).toFixed(1)} 18 28`);
    const k = o.kirp.some((a) => o.t > a && o.t < a + 0.13) ? 0.12 : 1;
    at($(id + 'Goz'), 'transform', `scale(1 ${k})`);
    at($(id + 'Kafa'), 'transform', `translate(0 -556) rotate(${o.kafa.toFixed(1)})`);
  }
  window.kisi = kisi;

  /* ---------- dükkân ---------- */
  function dukkanSVG() {
    const renk = ['#1b1416', '#b4233c', '#8a5a3c', '#c49a45', '#463b3e', '#d0334f', '#1b1416', '#8a5a3c'];
    let raf = '';
    [200, 320, 440].forEach((y, r) => {
      raf += `<rect x="60" y="${y}" width="720" height="14" rx="5" fill="#d9c9c5"/>`;
      for (let i = 0; i < 7; i++) {
        const x = 92 + i * 98, c = renk[(i + r * 3) % renk.length], yy = y - 30;
        raf += `<g fill="none" stroke="${c}" stroke-width="5"><rect x="${x}" y="${yy}" width="34" height="24" rx="9"/><rect x="${x + 40}" y="${yy}" width="34" height="24" rx="9"/><path d="M${x + 34} ${yy + 8} q3 -4 6 0"/></g>`;
      }
    });
    let kagit = '';
    for (let i = 0; i < 9; i++) kagit += `<rect x="${92 + (i % 3) * 3}" y="${608 - i * 9}" width="120" height="10" rx="2" fill="${i % 2 ? '#fbf8f7' : '#efe7e4'}" stroke="#cdbcb7" stroke-width="1.5" transform="rotate(${(i % 3) - 1} 150 ${608 - i * 9})"/>`;
    return `<svg id="dukkan" viewBox="0 0 1080 960">
      <rect width="1080" height="960" fill="#f1e9e6"/>
      <rect y="860" width="1080" height="100" fill="#e3d6d1"/>
      <g stroke="#d6c7c1" stroke-width="3"><line x1="0" y1="900" x2="1080" y2="900"/><line x1="160" y1="860" x2="120" y2="960"/><line x1="420" y1="860" x2="400" y2="960"/><line x1="700" y1="860" x2="710" y2="960"/><line x1="960" y1="860" x2="990" y2="960"/></g>
      <text x="70" y="104" font-family="Manrope" font-weight="800" font-size="40" letter-spacing="6" fill="#463b3e">ÖRNEK OPTİK</text>
      <rect x="70" y="122" width="96" height="6" rx="3" fill="#b4233c"/>
      <g id="saat" transform="translate(720 92)"><circle r="44" fill="#fbf8f7" stroke="#3a3033" stroke-width="7"/>
        <g stroke="#3a3033" stroke-width="3">${[0, 90, 180, 270].map((a) => `<line x1="0" y1="-36" x2="0" y2="-30" transform="rotate(${a})"/>`).join('')}</g>
        <line id="saatA" x1="0" y1="0" x2="0" y2="-22" stroke="#1b1416" stroke-width="6" stroke-linecap="round"/>
        <line id="saatB" x1="0" y1="0" x2="0" y2="-32" stroke="#b4233c" stroke-width="4" stroke-linecap="round"/><circle r="5" fill="#1b1416"/></g>
      <g>${raf}</g>
      <rect x="836" y="226" width="214" height="636" rx="6" fill="#3a3033"/>
      <rect id="disari" x="852" y="242" width="182" height="620" fill="#fff6ef"/>
      <rect x="852" y="560" width="182" height="302" fill="#f3e3d6"/>
      <g id="kapiKanat"><rect x="852" y="242" width="182" height="620" fill="#dfe5e4" stroke="#3a3033" stroke-width="10"/>
        <path d="M880 280 L930 280 L880 380 Z" fill="#fff" opacity=".55"/><rect x="866" y="520" width="12" height="70" rx="6" fill="#c49a45"/>
        <text x="943" y="430" text-anchor="middle" font-family="Manrope" font-weight="800" font-size="24" fill="#3a3033" letter-spacing="3">AÇIK</text></g>
      <g id="zil" transform="translate(1012 214)"><line x1="0" y1="-14" x2="0" y2="4" stroke="#3a3033" stroke-width="4"/>
        <path d="M-16 26 Q-16 4 0 4 Q16 4 16 26 Z" fill="#c49a45"/><circle cx="0" cy="30" r="5" fill="#a07a2c"/></g>
      <g id="dinn" stroke="#c49a45" stroke-width="5" stroke-linecap="round" opacity="0"><line x1="1040" y1="196" x2="1058" y2="184"/><line x1="1044" y1="216" x2="1066" y2="214"/><line x1="984" y1="196" x2="966" y2="184"/></g>
      <g id="optisyen" transform="translate(300 640)">
        <g id="oKolSol" transform="translate(-98 -168) rotate(8)"><rect x="-19" y="0" width="38" height="170" rx="19" fill="#ebe1de" stroke="#cdbcb7" stroke-width="3"/></g>
        <g id="oKolSag" transform="translate(98 -168)"><rect x="-19" y="0" width="38" height="170" rx="19" fill="#ebe1de" stroke="#cdbcb7" stroke-width="3"/><circle cx="0" cy="176" r="20" fill="#c98d66"/></g>
        <path d="M-112 10 L-102 -168 Q-60 -214 0 -217 Q60 -214 102 -168 L112 10 Z" fill="#fbf8f7"/>
        <path d="M-72 10 L-62 -150 L-30 -150 L-30 -118 L30 -118 L30 -150 L62 -150 L72 10 Z" fill="#b4233c"/>
        <path d="M-46 -150 L-28 -206 M46 -150 L28 -206" stroke="#b4233c" stroke-width="9" stroke-linecap="round"/>
        <rect x="-50" y="-100" width="58" height="22" rx="5" fill="#fff"/><rect x="-44" y="-93" width="38" height="5" rx="2" fill="#b4233c"/>
        <rect x="-15" y="-240" width="30" height="34" fill="#c08560"/>
        <g id="oKafa" transform="translate(0 -282)">
          <circle cx="-60" cy="4" r="13" fill="#c08560"/><circle cx="60" cy="4" r="13" fill="#c08560"/>
          <circle r="60" fill="#c98d66"/>
          <path d="M-62 -6 Q-68 -74 0 -76 Q68 -74 62 -6 Q56 -42 4 -44 Q-40 -46 -62 -6Z" fill="#2a2022"/>
          <g id="oGoz"><circle cx="-22" cy="2" r="5.5" fill="#1b1416"/><circle cx="22" cy="2" r="5.5" fill="#1b1416"/></g>
          <g fill="none" stroke="#1b1416" stroke-width="5"><circle cx="-22" cy="2" r="18"/><circle cx="22" cy="2" r="18"/><path d="M-4 0 Q0 -5 4 0"/></g>
          <path id="oKas" d="M-34 -24 Q-22 -30 -10 -24 M10 -24 Q22 -30 34 -24" stroke="#2a2022" stroke-width="4" fill="none" stroke-linecap="round"/>
          <path id="oAgiz" d="M-16 28 Q0 40 16 28" fill="none" stroke="#6b2f24" stroke-width="5" stroke-linecap="round"/>
        </g>
        <g id="oTelKol" opacity="0"><path d="M98 -160 L140 -70 L70 -262" fill="none" stroke="#ebe1de" stroke-width="38" stroke-linecap="round" stroke-linejoin="round"/>
          <rect x="52" y="-336" width="30" height="74" rx="10" fill="#1b1416" transform="rotate(14 67 -299)"/><circle cx="74" cy="-262" r="20" fill="#c98d66"/></g>
      </g>
      <g id="pTepsi"><rect x="86" y="606" width="150" height="22" rx="7" fill="#cdbcb7"/>
        <g fill="none" stroke-width="5"><g stroke="#1b1416"><rect x="104" y="586" width="34" height="22" rx="9"/><rect x="144" y="586" width="34" height="22" rx="9"/></g><g stroke="#b4233c"><rect x="192" y="588" width="18" height="18" rx="6"/></g></g></g>
      <g id="pDefter" opacity="0"><path d="M84 626 L160 604 L236 626 L236 632 L84 632Z" fill="#fbf8f7" stroke="#cdbcb7" stroke-width="2"/><path d="M160 604 L160 632" stroke="#b4233c" stroke-width="3"/>
        <g stroke="#9b8d90" stroke-width="2">${[0, 1, 2].map((i) => `<line x1="${100 + i * 4}" y1="${618 - i * 4}" x2="${148}" y2="${612 - i * 4}"/><line x1="${172}" y1="${612 - i * 4}" x2="${222 - i * 4}" y2="${618 - i * 4}"/>`).join('')}</g>
        <rect x="250" y="590" width="26" height="40" rx="4" fill="#ffe08a" transform="rotate(-8 263 610)"/><rect x="232" y="560" width="26" height="30" rx="4" fill="#ffc6cf" transform="rotate(10 245 575)"/></g>
      <g id="pKagit" opacity="0">${kagit}<g transform="translate(236 572)"><rect width="66" height="58" rx="8" fill="#3a3033"/><rect x="8" y="7" width="50" height="14" rx="3" fill="#cfe3c8"/>
        ${[0, 1, 2].map((r) => [0, 1, 2].map((c) => `<rect x="${8 + c * 18}" y="${27 + r * 10}" width="14" height="7" rx="2" fill="#8a7d80"/>`).join('')).join('')}</g></g>
      <g id="pYazici" opacity="0"><rect x="70" y="556" width="200" height="74" rx="14" fill="#3a3033"/><rect x="84" y="552" width="172" height="12" rx="5" fill="#241b1e"/>
        <circle cx="246" cy="604" r="6" fill="#66d699"/><rect x="92" y="598" width="90" height="8" rx="4" fill="#4b3f43"/></g>
      <g id="monitor"><rect x="507" y="588" width="26" height="44" fill="#3a3033"/><ellipse cx="520" cy="630" rx="56" ry="8" fill="#3a3033"/>
        <rect x="430" y="470" width="180" height="124" rx="12" fill="#141012" stroke="#3a3033" stroke-width="7"/>
        <rect x="440" y="481" width="160" height="12" rx="3" fill="#b4233c"/><rect x="446" y="503" width="90" height="8" rx="3" fill="#3d3236"/><rect x="446" y="520" width="140" height="22" rx="5" fill="#221a1d"/><rect x="446" y="550" width="140" height="22" rx="5" fill="#221a1d"/></g>
      <rect x="30" y="628" width="620" height="26" rx="9" fill="#d9c9c5"/>
      <rect x="40" y="654" width="600" height="206" fill="#2a2124"/>
      <rect x="40" y="700" width="600" height="10" fill="#b4233c"/>
      <rect x="40" y="654" width="600" height="8" fill="#1b1416" opacity=".35"/>
      <rect id="aksam" width="1080" height="960" fill="#1d1630" opacity="0" style="mix-blend-mode:multiply"/>
      <circle id="lamba" cx="300" cy="560" r="330" fill="url(#lambaIsik)" opacity="0"/>
      <defs><radialGradient id="lambaIsik"><stop offset="0" stop-color="#ffd59a" stop-opacity=".55"/><stop offset="1" stop-color="#ffd59a" stop-opacity="0"/></radialGradient></defs>
    </svg>`;
  }
  function evSVG() {
    return `<svg id="ev" viewBox="0 0 1080 960" style="opacity:0">
      <rect width="1080" height="960" fill="#f3e8e2"/><rect y="860" width="1080" height="100" fill="#e0cfc6"/>
      <defs><linearGradient id="aksamG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f9c9a8"/><stop offset="1" stop-color="#f29a86"/></linearGradient></defs>
      <rect x="560" y="140" width="400" height="420" rx="10" fill="#4a3c3f"/><rect x="576" y="156" width="368" height="388" fill="url(#aksamG)"/>
      <circle cx="840" cy="420" r="54" fill="#ffe0c2" opacity=".85"/>
      <path d="M576 500 L650 430 L720 480 L800 400 L944 500 L944 544 L576 544Z" fill="#c9776a" opacity=".55"/>
      <rect x="756" y="156" width="10" height="388" fill="#4a3c3f"/><rect x="576" y="346" width="368" height="10" fill="#4a3c3f"/>
      <rect x="40" y="560" width="470" height="210" rx="40" fill="#7e1528"/><rect x="20" y="660" width="510" height="200" rx="40" fill="#8f1a2e"/>
      <rect x="70" y="600" width="170" height="110" rx="26" fill="#d0334f"/>
      <rect x="40" y="850" width="24" height="40" fill="#3a3033"/><rect x="486" y="850" width="24" height="40" fill="#3a3033"/>
      <line x1="140" y1="200" x2="140" y2="560" stroke="#3a3033" stroke-width="6"/><path d="M80 200 L200 200 L170 120 L110 120Z" fill="#f6d9a8"/>
      <ellipse cx="560" cy="910" rx="380" ry="34" fill="#d7c2b8"/></svg>`;
  }

  /** Dükkân ayarları: kapı (0 kapalı → 1 açık), zil sallanması, optisyen, nesneler, saat, akşam tonu. */
  function dukkan(o) {
    o = Object.assign({ t: 0, kapi: 0, zil: 0, sag: -8, el: 0, tel: 0, gul: 1, kas: 0, kirp: [], tepsi: 1, defter: 0, kagit: 0, yazici: 0, saat: null, aksam: 0, lamba: 0 }, o);
    at($('kapiKanat'), 'transform', `translate(1034 0) scale(${(1 - 0.86 * o.kapi).toFixed(3)} 1) translate(-1034 0)`);
    at($('zil'), 'transform', `translate(1012 214) rotate(${(Math.sin(o.t * 22) * 22 * o.zil).toFixed(2)})`);
    $('dinn').style.opacity = o.zil > 0.15 ? (Math.sin(o.t * 18) > 0 ? 1 : 0.35) : 0;
    let sag = o.sag;
    if (o.el) sag = -150 + Math.sin(o.t * Math.PI * 3.2) * 18;
    at($('oKolSag'), 'transform', `translate(98 -168) rotate(${sag.toFixed(2)})`);
    $('oKolSag').style.opacity = o.tel ? 0 : 1;
    $('oTelKol').style.opacity = o.tel;
    const k = o.kirp.some((a) => o.t > a && o.t < a + 0.13) ? 0.12 : 1;
    at($('oGoz'), 'transform', `translate(0 ${(2 - 2 * k).toFixed(2)}) scale(1 ${k})`);
    at($('oAgiz'), 'd', o.gul >= 0 ? `M-16 28 Q0 ${(28 + 12 * o.gul).toFixed(1)} 16 28` : `M-16 34 Q0 ${(34 + 12 * o.gul).toFixed(1)} 16 34`);
    at($('oKas'), 'transform', `translate(0 ${(-6 * o.kas).toFixed(1)})`);
    $('pTepsi').style.opacity = o.tepsi; $('pDefter').style.opacity = o.defter; $('pKagit').style.opacity = o.kagit; $('pYazici').style.opacity = o.yazici;
    $('saat').style.opacity = o.saat ? 1 : 0;
    if (o.saat) {
      const [h, m] = o.saat;
      at($('saatA'), 'transform', `rotate(${((h % 12) * 30 + m * 0.5).toFixed(1)})`);
      at($('saatB'), 'transform', `rotate(${(m * 6).toFixed(1)})`);
    }
    $('aksam').style.opacity = (0.55 * o.aksam).toFixed(3);
    $('lamba').style.opacity = o.lamba;
    $('disari').style.fill = o.aksam > 0.5 ? '#3b3350' : '#fff6ef';
  }
  window.dukkan = dukkan;

  /* ---------- panel yardımcıları ---------- */
  window.sekme = (ad) => {
    $('sekOpti').className = ad === 'optiflow' ? 'on' : '';
    $('sekMed').className = ad === 'medula' ? 'on' : '';
  };
  window.gorunum = (id, v) => { $(id).style.opacity = v; };
  /** Düğmeye basma: u yerel zaman, a basma anı. Düğmede .dalga yoksa eklenir. */
  window.bas = (el, u, a) => {
    if (!el.querySelector('.dalga')) { const d = document.createElement('i'); d.className = 'dalga'; el.appendChild(d); }
    const basili = u > a && u < a + 0.2;
    el.style.transform = `scale(${basili ? 0.95 : 1})`;
    const dg = p(u, a + 0.02, a + 0.6);
    css(el.querySelector('.dalga'), { opacity: dg > 0 && dg < 1 ? 1 - dg : 0, transform: `scale(${(dg * 14).toFixed(2)})` });
  };
  /** İmleç: panel koordinatlarında (x, y). */
  window.imlec = (x, y, op, basili) => { css($('imlec'), { opacity: op, transform: `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) scale(${basili ? 0.85 : 1})` }); };
  /** İmleç yolu: noktalar [[u, x, y], ...]; tık anları listesi. */
  window.imlecYol = (u, yol, tik = [], op = 1) => {
    let x = yol[0][1], y = yol[0][2];
    for (let i = 1; i < yol.length; i++) {
      const q = eio(p(u, yol[i - 1][0], yol[i][0]));
      if (u >= yol[i - 1][0]) { x = lerp(yol[i - 1][1], yol[i][1], q); y = lerp(yol[i - 1][2], yol[i][2], q); }
    }
    imlec(x, y, op, tik.some((a) => u > a && u < a + 0.18));
  };
  /** Bir öğenin panel içindeki orta noktası (imleç hedefi için; ucun tam üstüne gelmesi için hafif kaydırılır). */
  window.uiNokta = (el, dx = 0, dy = 0) => {
    const r = el.getBoundingClientRect(), u = $('ui').getBoundingClientRect(), s = u.width / 988;
    return [(r.left + r.width / 2 - u.left) / s - 8 + dx, (r.top + r.height / 2 - u.top) / s - 6 + dy];
  };
  window.damga = (q, o) => {
    const d = $('damga');
    d.textContent = o.metin;
    css(d, { left: o.x + 'px', top: o.y + 'px' });
    d.style.transform = `rotate(${o.aci ?? -10}deg) scale(${(1.8 - 0.8 * eo(q)).toFixed(3)})`;
    gorun(d, q > 0 ? Math.min(1, q * 1.6) * (o.cik ?? 1) : 0);
  };
  window.bildirim = (q, o) => {
    const n = $('bildirim');
    if (o) { n.querySelector('h4').textContent = o.baslik; n.querySelector('p').textContent = o.metin; n.querySelector('.link').textContent = o.link || ''; }
    gorun(n, q * (o?.cik ?? 1));
    n.style.transform = `translateY(${((1 - q) * -120).toFixed(1)}px) scale(${(0.96 + 0.04 * q).toFixed(3)})`;
  };
  window.balon = (q, metin) => {
    const b = $('balon');
    if (metin) b.textContent = metin;
    gorun(b, q > 0 ? 1 : 0);
    b.style.opacity = (Math.min(1, q * 3) * AKTIF).toFixed(3);
    b.style.transform = `scale(${(0.85 + 0.15 * geri(cl(q))).toFixed(3)})`;
  };

  /* ---------- film ---------- */
  window.FILM = function (cfg) {
    const S = cfg.sahneler;
    const SES_SURE = window.SES_SURE || {};
    const ARA = 0.55; // ses bittikten sonra boşluk
    let a = 0;
    S.forEach((s) => {
      const ses = SES_SURE[s.ad] || 0;
      s.sure = Math.max(s.sure, ses ? ses + 0.15 + ARA : 0);
      s.a = a; s.b = a + s.sure; a = s.b;
    });
    window.SURE = a;
    window.SES = S.filter((s) => s.ses).map((s) => ({ id: s.ad, metin: s.ses, t: +(s.a + 0.15).toFixed(3) }));
    window.SAHNELER = S.map((s) => ({ ad: s.ad, a: +s.a.toFixed(3), b: +s.b.toFixed(3) }));

    const kisiler = Object.entries(cfg.kisiler || {}).map(([id, o]) => kisiSVG(id, o)).join('');
    const caps = S.filter((s) => s.h).map((s) => `<div class="cap" id="cap-${s.ad}"><div class="eb">${s.eb || ''}</div><h1>${s.h}</h1></div>`).join('');
    const gorunumler = Object.entries(cfg.gorunumler || {}).map(([id, html]) => `<div class="gorunum" id="${id}">${html}</div>`).join('');
    $('tuval').innerHTML = `${caps}
      <div id="sahne">${dukkanSVG()}${evSVG()}<svg id="kisiler" viewBox="0 0 1080 960">${kisiler}</svg><div class="balon" id="balon"></div></div>
      <div id="ui"><div class="bar"><span style="width:40px;height:40px;display:block">${LOGO('lgUi')}</span><span class="ad">OptiFlow</span><span class="pro">PRO</span>
        <div class="sekme"><span id="sekOpti" class="on">OptiFlow</span><span id="sekMed">SGK · Medula</span></div>
        <div class="aktar" id="aktar">${IK.aktar}Reçeteyi aktar</div></div>${gorunumler}
        <svg id="imlec" viewBox="0 0 24 24"><path d="M4 2 L4 20 L9 15.5 L12.5 22 L15.5 20.6 L12 14 L19 14 Z" fill="#fff" stroke="#141012" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
      <div id="bildirim"><div class="bas"><span class="ik">${IK.mesaj}</span><span>Mesajlar</span><span>şimdi</span></div><h4></h4><p></p><div class="link"></div></div>
      <div class="damga" id="damga"></div>
      <div id="kunye"><span>${LOGO('lgK')}</span>OptiFlow</div>
      ${cfg.ekHTML || ''}
      <div id="son"><svg id="nisan" viewBox="-100 -100 200 200" fill="none" stroke="#ff6b81" stroke-width=".5"><circle r="92"/><circle r="64"/><circle r="34"/><path d="M-100 0H100M0 -100V100"/><g id="centik"></g></svg>
        <div class="isaret" id="sonLogo">${LOGO('lgS')}</div><div class="kel" id="sonKel">OptiFlow</div>
        <div class="koken" id="koken">OptiFlow bir <span class="ciz">yazılım ofisinde<i id="kokenCiz"></i></span> değil, bir optik atölyesinde doğdu.</div>
        <div class="cta" id="cta">30 gün ücretsiz · kart gerekmez</div><div class="url" id="url">optiflow.com.tr · <b>@optiflowtr</b></div></div>`;
    let c = '';
    for (let d = 0; d < 360; d += 10) {
      const r1 = d % 30 === 0 ? 84 : 88, r = (d * Math.PI) / 180;
      c += `<line x1="${(Math.cos(r) * r1).toFixed(2)}" y1="${(Math.sin(r) * r1).toFixed(2)}" x2="${(Math.cos(r) * 92).toFixed(2)}" y2="${(Math.sin(r) * 92).toFixed(2)}"/>`;
    }
    $('centik').innerHTML = c;

    // altyazı sözcükleri
    S.filter((s) => s.h).forEach((s) => {
      const parca = (node) => {
        [...node.childNodes].forEach((n) => {
          if (n.nodeType === 3) {
            const f = document.createDocumentFragment();
            n.textContent.split(/(\s+)/).forEach((w) => {
              if (!w) return;
              if (/^\s+$/.test(w)) { f.appendChild(document.createTextNode(' ')); return; }
              const sp = document.createElement('span'); sp.className = 'w'; sp.textContent = w; f.appendChild(sp);
            });
            n.replaceWith(f);
          } else if (n.classList && n.classList.contains('s')) { n.classList.add('w'); n.appendChild(document.createElement('i')); }
          else if (n.nodeType === 1) parca(n);
        });
      };
      parca($('cap-' + s.ad).querySelector('h1'));
    });
    if (cfg.hazirla) cfg.hazirla();

    const KATMAN = { dukkan: () => $('dukkan'), ev: () => $('ev'), ui: () => $('ui'), son: () => $('son') };

    function sifirla() {
      Object.values(KATMAN).forEach((f) => { f().style.opacity = 0; });
      $('dukkan').style.transform = 'none';
      $('ui').style.transform = 'none';
      document.querySelectorAll('#kisiler > g').forEach((g) => { g.style.opacity = 0; });
      ['balon', 'bildirim', 'damga'].forEach((id) => { $(id).style.opacity = 0; });
      document.querySelectorAll('.gorunum').forEach((g) => { g.style.opacity = 0; });
      $('imlec').style.opacity = 0;
      sekme('optiflow');
      dukkan({});
      if (cfg.sifirla) cfg.sifirla();
    }

    function kapanis(u) {
      const g = eo(p(u, 0, 0.45));
      $('son').style.opacity = g;
      $('nisan').style.transform = `rotate(${(u * 6).toFixed(2)}deg) scale(${(0.9 + 0.1 * g).toFixed(3)})`;
      const l = geri(p(u, 0.25, 0.8));
      css($('sonLogo'), { transform: `scale(${l.toFixed(3)}) rotate(${((1 - l) * -40).toFixed(1)}deg)`, opacity: p(u, 0.25, 0.45) });
      const k = eo(p(u, 0.55, 0.95)); css($('sonKel'), { opacity: k, transform: `translateY(${((1 - k) * 30).toFixed(1)}px)` });
      const ko = eo(p(u, 0.95, 1.35)); css($('koken'), { opacity: ko, transform: `translateY(${((1 - ko) * 24).toFixed(1)}px)` });
      $('kokenCiz').style.transform = `scaleX(${eio(p(u, 1.5, 1.9))})`;
      const c2 = geri(p(u, 1.95, 2.35)); css($('cta'), { opacity: p(u, 1.95, 2.1), transform: `scale(${c2.toFixed(3)})` });
      $('url').style.opacity = p(u, 2.25, 2.55);
      return g;
    }

    function sahneCiz(s, u, op, zoomQ) {
      AKTIF = op;
      if (s.son) { kapanis(u); return; }
      const el = KATMAN[s.katman]();
      el.style.opacity = op;
      if (s.ciz) s.ciz(u, s);
    }

    function altyazi(t) {
      S.filter((s) => s.h).forEach((s, idx, arr) => {
        const el = $('cap-' + s.ad);
        const cik = p(t, s.b - 0.3, s.b);
        const gir = s.a === 0 ? 1 : p(t, s.a - 0.05, s.a + 0.2);
        el.style.opacity = t >= s.a - 0.1 && t <= s.b ? Math.min(gir, 1 - cik) : 0;
        el.style.transform = `translateY(${(-24 * eo(cik)).toFixed(1)}px)`;
        el.querySelectorAll('.w').forEach((w, i) => {
          const q = eo(p(t, s.a + 0.08 + i * 0.07, s.a + 0.5 + i * 0.07));
          w.style.opacity = q; w.style.transform = `translateY(${((1 - q) * 46).toFixed(1)}px)`;
        });
        (s.cizgi || []).forEach((cu, k) => {
          const i = el.querySelectorAll('.s i')[k];
          if (i) i.style.transform = `scaleX(${eio(p(t - s.a, cu, cu + 0.4))})`;
        });
      });
    }

    function ciz(t) {
      t = cl(t, 0, window.SURE - 1e-6);
      sifirla();
      const i = S.findIndex((s) => t >= s.a && t < s.b);
      const s = S[i], u = t - s.a, onceki = S[i - 1];
      const GEC = s.gecis ?? 0.4;
      if (onceki && onceki.katman !== s.katman && u < GEC && !s.son) {
        const q = eio(p(u, 0, GEC));
        sahneCiz(onceki, onceki.sure, 1 - q);
        if (onceki.katman === 'dukkan' && s.katman === 'ui' && s.zoom) {
          $('dukkan').style.transform = `scale(${(1 + 5 * q).toFixed(3)})`;
          $('dukkan').style.opacity = 1 - p(u, GEC * 0.55, GEC);
        }
        sahneCiz(s, u, q);
        if (s.katman === 'ui') $('ui').style.transform = `scale(${(0.6 + 0.4 * eo(q)).toFixed(3)})`;
      } else if (s.son && onceki && u < 0.5) {
        sahneCiz(onceki, onceki.sure, 1);
        sahneCiz(s, u, 1);
      } else {
        sahneCiz(s, u, 1);
      }
      AKTIF = 1;
      altyazi(t);
      $('kunye').style.opacity = 1 - $('son').style.opacity;
    }
    window.ciz = ciz;

    if (!/cek=1/.test(location.search)) {
      const bas0 = performance.now();
      const dongu = () => { ciz(((performance.now() - bas0) / 1000) % window.SURE); requestAnimationFrame(dongu); };
      requestAnimationFrame(dongu);
    } else ciz(0);
  };
})();

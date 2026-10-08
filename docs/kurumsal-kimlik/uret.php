<?php
/* OptiFlow kurumsal kimlik üreticisi: logolar (SVG), Instagram şablonları (render HTML + bileşen önizlemesi). */
$K = __DIR__;
$FONT = '../../../assets/fonts/';   // render/*.html → depo kökündeki assets/fonts
@mkdir("$K/render", 0777, true);
@mkdir("$K/project/assets/Logolar", 0777, true);
@mkdir("$K/project/assets/Instagram", 0777, true);

/* ---------- işaret (app/pazarlama.php pz_logo ile aynı geometri) ---------- */
$GEO = '<g transform="rotate(-10 50 50)" fill="%s"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z"/><circle cx="50" cy="50" r="7"/></g>';
$GRAD = fn (string $id) => '<defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs>';
$isaret = fn (string $id, string $tek = '') => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' . ($tek ? '' : $GRAD($id)) . sprintf($GEO, $tek ?: "url(#$id)") . '</svg>';

file_put_contents("$K/project/assets/Logolar/optiflow-isaret.svg", $isaret('g'));
file_put_contents("$K/project/assets/Logolar/optiflow-isaret-gece.svg", $isaret('', '#141012'));
file_put_contents("$K/project/assets/Logolar/optiflow-isaret-beyaz.svg", $isaret('', '#ffffff'));
file_put_contents("$K/project/assets/Logolar/optiflow-uygulama-simgesi.svg",
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff6b81"/><stop offset="1" stop-color="#b4233c"/></linearGradient></defs><rect width="100" height="100" rx="22" fill="#141012"/><g transform="translate(14 14) scale(.72)">' . sprintf($GEO, 'url(#g)') . '</g></svg>');

/* ---------- ortak şablon CSS (değerler tokens.json ile aynı) ---------- */
$ORTAK = <<<'CSS'
.ig{--paper:#f5f3f3;--ink:#1b1416;--ink-2:#463b3e;--line:#e8e1e2;--brand:#b4233c;--brand-bright:#d0334f;--brand-deep:#8f1a2e;--coral:#ff6b81;--night:#141012;--night-2:#221a1d;--on-night:#f3eef0;--on-night-muted:#ab9fa3;--lens:#fbe4e8;
  position:relative;overflow:hidden;box-sizing:border-box;font-family:Manrope,"Manrope Fallback",Arial,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.ig *{box-sizing:border-box}
.ig.kare{width:1080px;height:1080px}
.ig.dikey{width:1080px;height:1920px}
.ig .mono{font-family:"IBM Plex Mono",ui-monospace,Consolas,monospace;font-weight:500}
.ig .etiket{font-family:"IBM Plex Mono",ui-monospace,Consolas,monospace;font-weight:500;font-size:26px;letter-spacing:.14em;text-transform:uppercase}
.ig .logo{position:absolute;left:80px;bottom:72px;display:flex;align-items:center;gap:18px;font-weight:800;font-size:40px;letter-spacing:-.02em}
.ig .logo svg{width:60px;height:60px;display:block}
.ig.gece{background:radial-gradient(900px 700px at 100% 0%,rgba(208,51,79,.34),transparent 65%),var(--night);color:var(--on-night)}
.ig .nisan{position:absolute;pointer-events:none;color:rgba(255,107,129,.22)}
.ig .nisan circle,.ig .nisan path{fill:none;stroke:currentColor}
CSS;

$NISAN = '<svg class="nisan" viewBox="0 0 400 400" style="%s"><circle cx="200" cy="200" r="190" stroke-width="1.5"/><circle cx="200" cy="200" r="130"/><circle cx="200" cy="200" r="70"/><path d="M200 0v400M0 200h400"/><path d="M200 60v16M200 324v16M60 200h16M324 200h16M101 101l11 11M288 288l11 11M299 101l-11 11M112 288l-11 11" stroke-width="2.5"/></svg>';
$LOGO = fn (string $renk, string $id) => '<div class="logo" style="color:' . $renk . '">' . $isaret($id) . 'OptiFlow</div>';

/* ---------- şablonlar ---------- */
$S = [];

$S['bilgi'] = ['ad' => 'Bilgi kartı (kaydırmalı gönderi kapağı)', 'boy' => 'kare', 'css' => <<<'CSS'
.t-bilgi{padding:88px 80px}
.t-bilgi .ust{display:flex;justify-content:space-between;color:var(--coral)}
.t-bilgi h2{margin:120px 0 0;max-width:880px;font-size:104px;line-height:1.02;font-weight:800;letter-spacing:-.035em;color:#fff}
.t-bilgi h2 em{font-style:normal;color:var(--coral)}
.t-bilgi .alt{margin-top:44px;max-width:760px;font-size:34px;line-height:1.4;font-weight:600;color:var(--on-night-muted)}
.t-bilgi .kaydir{position:absolute;right:80px;bottom:84px;display:flex;align-items:center;gap:14px;color:#fff}
.t-bilgi .kaydir b{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:var(--brand-bright);font-size:34px}
CSS, 'html' => fn () => '<div class="ig kare gece t-bilgi">' . sprintf($GLOBALS['NISAN'], 'width:760px;right:-220px;bottom:-240px') .
    '<div class="ust"><span class="etiket">SGK rehberi</span><span class="etiket">1 / 6</span></div>' .
    '<h2>Gözlük hakkı <em>kaç yılda bir</em> yenilenir?</h2>' .
    '<p class="alt">Medula\'da en sık karıştırılan beş kural, tezgâhtan sade bir dille.</p>' .
    $GLOBALS['LOGO']('#fff', 'gb') . '<div class="kaydir etiket">Kaydırın <b>→</b></div></div>'];

$S['ozellik'] = ['ad' => 'Özellik kartı (göz eşeli)', 'boy' => 'kare', 'css' => <<<'CSS'
.t-ozellik{background:var(--paper);padding:96px 80px}
.t-ozellik .esel{border-top:8px solid var(--ink)}
.t-ozellik .satir{display:grid;grid-template-columns:1fr auto;align-items:baseline;gap:20px;border-bottom:2px solid var(--line);padding:6px 0 10px}
.t-ozellik .satir b{font-weight:800;letter-spacing:.02em;line-height:1;white-space:nowrap}
.t-ozellik .satir i{font-style:normal;font-family:"IBM Plex Mono",monospace;font-weight:500;font-size:24px;color:var(--ink-2)}
.t-ozellik .s1 b{font-size:200px;background:linear-gradient(135deg,#d0334f,#8f1a2e);-webkit-background-clip:text;background-clip:text;color:transparent}
.t-ozellik .s2 b{font-size:132px}.t-ozellik .s3 b{font-size:92px}.t-ozellik .s4 b{font-size:62px}
.t-ozellik .s4 i{color:var(--brand-bright)}
.t-ozellik .not{position:absolute;right:80px;bottom:88px;color:var(--ink-2);text-transform:none}
CSS, 'html' => fn () => '<div class="ig kare t-ozellik"><div class="esel">' .
    '<div class="satir s1"><b>Reçete</b><i>0,1</i></div><div class="satir s2"><b>Medula\'dan</b><i>0,4</i></div>' .
    '<div class="satir s3"><b>tek tuşla</b><i>0,6</i></div><div class="satir s4"><b>siparişe.</b><i>1,0</i></div></div>' .
    $GLOBALS['LOGO']('#1b1416', 'go') . '<div class="not etiket">OptiFlow Pro</div></div>'];

$S['koken'] = ['ad' => 'Kök hikâyesi', 'boy' => 'kare', 'css' => <<<'CSS'
.t-koken{padding:100px 80px}
.t-koken .editor{border-radius:22px;background:#1d1719;border:1.5px solid rgba(255,255,255,.12);box-shadow:0 40px 80px -40px rgba(0,0,0,.9)}
.t-koken .sekme{display:flex;align-items:center;gap:10px;padding:18px 24px;border-bottom:1.5px solid rgba(255,255,255,.08);font-size:22px;color:rgba(255,255,255,.45)}
.t-koken .sekme i{width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,.18)}
.t-koken .kod{display:flex;flex-wrap:wrap;align-items:center;gap:14px 22px;padding:30px 30px 36px;font-size:46px;color:#e9e2e4}
.t-koken .kod s{text-decoration:none;position:relative;color:rgba(233,226,228,.5)}
.t-koken .kod s::after{content:"";position:absolute;left:-3%;right:-3%;top:52%;height:7px;border-radius:4px;background:#ff3d5e;rotate:-2.5deg;box-shadow:0 0 18px rgba(255,61,94,.7)}
.t-koken .degil{display:inline-block;padding:2px 16px 6px;border:4px solid #ff3d5e;border-radius:12px;color:var(--coral);font-family:Manrope,sans-serif;font-weight:800;rotate:-7deg}
.t-koken h2{margin:70px 0 0;max-width:900px;font-size:84px;line-height:1.08;font-weight:800;letter-spacing:-.03em;color:#fff}
.t-koken h2 em{font-style:normal;color:var(--coral)}
.t-koken .imza{position:absolute;right:80px;bottom:88px;color:var(--on-night-muted)}
CSS, 'html' => fn () => '<div class="ig kare gece t-koken">' . sprintf($GLOBALS['NISAN'], 'width:900px;left:90px;top:120px') .
    '<div class="editor"><div class="sekme mono"><i></i><i></i><i></i><span>hikaye.php</span></div><div class="kod mono"><span>OptiFlow bir <s>yazılım ofisinde</s></span><span class="degil">değil,</span></div></div>' .
    '<h2>bir <em>optik atölyesinde</em> doğdu.</h2>' . $GLOBALS['LOGO']('#fff', 'gk') . '<div class="imza etiket">Poyraz Optik atölyesi</div></div>'];

$S['muayene'] = ['ad' => '"1 mi, 2 mi?" karşılaştırma', 'boy' => 'kare', 'css' => <<<'CSS'
.t-muayene{padding:96px 70px;text-align:center}
.t-muayene h2{margin:0;font-size:80px;line-height:1.05;font-weight:800;letter-spacing:-.03em;color:#fff}
.t-muayene h2 b{display:inline-grid;place-items:center;width:1.2em;height:1.2em;border:5px solid var(--coral);border-radius:50%;color:var(--coral);font-size:.78em;vertical-align:.08em}
.t-muayene .is{margin:22px 0 0;color:var(--coral)}
.t-muayene .foropter{display:grid;grid-template-columns:1fr 60px 1fr;align-items:center;margin:54px 0 0;padding:34px 40px;border-radius:200px;background:linear-gradient(180deg,#2a2124,#120e10);box-shadow:inset 0 2px 0 rgba(255,255,255,.08)}
.t-muayene .kopru{height:22px;border-radius:11px;background:#3a2f33}
.t-muayene .cam{display:grid;place-items:center;aspect-ratio:1;border-radius:50%;padding:15%;background:radial-gradient(circle at 32% 26%,rgba(255,255,255,.9),transparent 30%),radial-gradient(circle,#f7f3f4 0 58%,#e1d7da 72%);border:22px solid #0c0909;box-shadow:0 0 0 4px #3b3134;color:var(--ink);font-weight:750;font-size:36px;line-height:1.25}
.t-muayene .bulanik span{filter:blur(4px)}
.t-muayene .net{box-shadow:0 0 0 4px var(--coral),0 0 70px 10px rgba(255,107,129,.5)}
.t-muayene .no{display:flex;justify-content:space-around;margin-top:26px;color:#fff}
CSS, 'html' => fn () => '<div class="ig kare gece t-muayene"><h2>Hangisi daha net?<br><b>1</b> mi, <b>2</b> mi?</h2><p class="is etiket">SGK katkı payı</p>' .
    '<div class="foropter"><div class="cam bulanik"><span>Hesap makinesiyle tek tek bulunur</span></div><div class="kopru"></div><div class="cam net"><span>Reçeteye göre kendiliğinden çıkar</span></div></div>' .
    '<div class="no etiket"><span>1</span><span style="text-transform:none;letter-spacing:.06em">2 · OptiFlow</span></div></div>'];

/* kare logo görselleri (duyuru, paylaşım kapağı) */
$S['logo-acik'] = ['ad' => 'Logo · açık zemin', 'boy' => 'kare', 'css' => <<<'CSS'
.t-logo{display:grid;place-items:center}
.t-logo .kilit{display:flex;align-items:center;gap:44px;font-weight:800;font-size:150px;letter-spacing:-.025em}
.t-logo .kilit svg{width:220px;height:220px}
.t-logo .alt{position:absolute;left:0;right:0;bottom:120px;text-align:center}
CSS, 'html' => fn () => '<div class="ig kare t-logo" style="background:var(--paper)"><div class="kilit" style="color:var(--ink)">' . $GLOBALS['isaret']('gla') . 'OptiFlow</div><div class="alt etiket" style="color:var(--ink-2)">Gözlükçüler için yönetim sistemi</div></div>'];
$S['logo-koyu'] = ['ad' => 'Logo · gece zemini', 'boy' => 'kare', 'css' => '', 'html' => fn () => '<div class="ig kare gece t-logo"><div class="kilit" style="color:#fff">' . $GLOBALS['isaret']('glk') . 'OptiFlow</div><div class="alt etiket" style="color:var(--on-night-muted)">Gözlükçüler için yönetim sistemi</div></div>'];

$S['story'] = ['ad' => 'Hikâye (ay sonu)', 'boy' => 'dikey', 'css' => <<<'CSS'
.t-story{padding:150px 80px;background:linear-gradient(170deg,#8f1a2e 0%,#b4233c 45%,#141012 100%);color:#fff}
.t-story .gun{display:inline-block;padding:12px 26px;border-radius:999px;background:rgba(0,0,0,.28)}
.t-story .esel{margin-top:80px;border-top:8px solid #fff}
.t-story .satir{display:grid;grid-template-columns:1fr auto;align-items:baseline;border-bottom:2px solid rgba(255,255,255,.25);padding:8px 0 12px}
.t-story .satir b{font-weight:800;line-height:1;letter-spacing:.01em}
.t-story .satir i{font-style:normal;font-family:"IBM Plex Mono",monospace;font-size:26px;color:rgba(255,255,255,.7)}
.t-story .s1 b{font-size:230px;letter-spacing:.04em}.t-story .s2 b{font-size:150px}.t-story .s3 b{font-size:110px}.t-story .s4 b{font-size:110px;color:#ffd0d8}
.t-story .fis{margin:70px auto 0;width:720px;padding:34px 40px 48px;background:#fffdf9;color:var(--ink);border-radius:6px 6px 0 0;rotate:-2deg;box-shadow:0 40px 80px -30px rgba(0,0,0,.7);
  -webkit-mask:radial-gradient(14px at 50% 100%,#0000 98%,#000) 0 0/42px 100% repeat-x;mask:radial-gradient(14px at 50% 100%,#0000 98%,#000) 0 0/42px 100% repeat-x}
.t-story .fis .mono{display:flex;justify-content:space-between;padding:12px 0;border-bottom:2px dashed #e1d7da;font-size:30px}
.t-story .fis .fark{color:var(--brand);font-weight:700}
.t-story .fis small{display:block;margin-bottom:10px;font-size:22px;letter-spacing:.14em;color:var(--brand)}
.t-story .cta{position:absolute;left:80px;right:80px;bottom:220px;display:grid;place-items:center;padding:34px;border-radius:26px;background:#fff;color:var(--brand-deep);font-size:44px;font-weight:800}
.t-story .cta small{display:block;margin-top:6px;font-size:26px;font-weight:600;color:var(--ink-2)}
.t-story .logo{bottom:100px}
CSS, 'html' => fn () => '<div class="ig dikey t-story"><span class="gun etiket">Ay sonuna 2 gün</span>' .
    '<div class="esel"><div class="satir s1"><b>SGK</b><i>0,1</i></div><div class="satir s2"><b>faturası</b><i>0,2</i></div><div class="satir s3"><b>bu akşam</b><i>0,6</i></div><div class="satir s4"><b>3 dakika.</b><i>1,0</i></div></div>' .
    '<div class="fis"><small class="mono">EYLÜL · SGK DÖKÜMÜ</small><div class="mono"><span>Medula dökümü</span><b>48</b></div><div class="mono"><span>OptiFlow\'da işaretli</span><b>47</b></div><div class="mono fark"><span>Fark</span><b>1</b></div></div>' .
    '<div class="cta">30 gün ücretsiz deneyin<small>Bağlantı profilde · kart gerekmez</small></div>' . $GLOBALS['LOGO']('#fff', 'gs') . '</div>'];

$S['profil'] = ['ad' => 'Profil fotoğrafı', 'boy' => 'kare', 'css' => <<<'CSS'
.t-profil{display:grid;place-items:center;background:radial-gradient(60% 60% at 50% 40%,#2a161c,#141012 70%)}
.t-profil svg.isaret{width:600px;height:600px;filter:drop-shadow(0 30px 60px rgba(208,51,79,.45))}
CSS, 'html' => fn () => '<div class="ig kare t-profil">' . str_replace('<svg ', '<svg class="isaret" ', $GLOBALS['isaret']('gp')) . '</div>'];

$KAPAK = ['sgk' => 'SGK', 'atolye' => 'ATÖLYE', 'fiyat' => 'FİYAT', 'rehber' => 'REHBER', 'demo' => 'DEMO'];
$S['kapak'] = ['ad' => 'Öne çıkan kapakları', 'boy' => 'kare', 'css' => <<<'CSS'
.t-kapak{display:grid;place-items:center;background:var(--night);color:#fff}
.t-kapak .nisan{width:760px;left:160px;top:160px;color:rgba(255,107,129,.5)}
.t-kapak .ic{position:relative;display:grid;justify-items:center;gap:26px}
.t-kapak .ic i{width:34px;height:34px;border-radius:50%;background:var(--coral);box-shadow:0 0 30px rgba(255,107,129,.8)}
.t-kapak .ic b{font-family:"IBM Plex Mono",monospace;font-weight:500;font-size:92px;letter-spacing:.08em}
CSS, 'html' => fn (string $k = 'sgk') => '<div class="ig kare t-kapak">' . sprintf($GLOBALS['NISAN'], '') . '<div class="ic"><i></i><b>' . $GLOBALS['KAPAK'][$k] . '</b></div></div>'];

/* ---------- render HTML (yerel fontlar) ---------- */
$fontFace = <<<CSS
@font-face{font-family:Manrope;font-weight:200 800;src:url("{$FONT}manrope-latin-wght-normal.woff2") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2190-2193}
@font-face{font-family:Manrope;font-weight:200 800;src:url("{$FONT}manrope-latin-ext-wght-normal.woff2") format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+1E00-1E9F}
@font-face{font-family:"IBM Plex Mono";font-weight:500;src:url("{$FONT}ibm-plex-mono-latin-500-normal.woff2") format("woff2");unicode-range:U+0000-00FF,U+2000-206F,U+2212}
@font-face{font-family:"IBM Plex Mono";font-weight:500;src:url("{$FONT}ibm-plex-mono-latin-ext-500-normal.woff2") format("woff2");unicode-range:U+0100-02BA,U+1E00-1E9F}
CSS;
$liste = [];
foreach ($S as $k => $t) {
    $variantlar = $k === 'kapak' ? array_keys($KAPAK) : [null];
    foreach ($variantlar as $v) {
        $ad = $v ? "kapak-$v" : $k;
        $html = $v ? ($t['html'])($v) : ($t['html'])();
        file_put_contents("$K/render/$ad.html", "<!doctype html><html lang=\"tr\"><meta charset=\"utf-8\"><style>$fontFace\nhtml,body{margin:0;background:transparent}\n$ORTAK\n{$t['css']}</style>$html");
        $liste[] = [$ad, $t['boy'] === 'dikey' ? 1920 : 1080];
    }
}
file_put_contents("$K/render/liste.json", json_encode($liste));

/* ---------- bileşen önizlemeleri (fontlar tokens.css'ten gelir) ---------- */
function onizleme(string $dosya, string $marker, string $css, string $govde): void {
    @mkdir(dirname($dosya), 0777, true);
    file_put_contents($dosya, "$marker\n<!doctype html><html lang=\"tr\"><meta charset=\"utf-8\"><style>body{margin:0;padding:16px;background:var(--paper);font-family:var(--font-sans)}\n$css</style>\n$govde\n");
}
$olcek = '.olc{position:relative;overflow:hidden;border-radius:10px;box-shadow:0 10px 30px -18px rgba(0,0,0,.5)}.olc>.ig{transform:scale(var(--s));transform-origin:0 0;position:absolute;left:0;top:0}
.izgara{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start}.izgara figure{margin:0;display:grid;gap:6px}.izgara figcaption{font-size:12.5px;font-weight:700;color:var(--ink-2)}';
$kutu = fn (string $html, float $s, int $h, string $baslik) => '<figure><div class="olc" style="--s:' . $s . ';width:' . round(1080 * $s) . 'px;height:' . round($h * $s) . 'px">' . $html . '</div><figcaption>' . htmlspecialchars($baslik) . '</figcaption></figure>';

$gonderiler = '';
foreach (['bilgi', 'ozellik', 'koken', 'muayene'] as $k) { $gonderiler .= $kutu(($S[$k]['html'])(), .3, 1080, $S[$k]['ad'] . ' · 1080 × 1080'); }
$cssHepsi = $ORTAK . "\n" . implode("\n", array_map(fn ($t) => $t['css'], $S)) . "\n" . $olcek;
onizleme("$K/project/components/InstagramGonderi/preview.html", '<!-- @dsCard group="Instagram" height=760 subtitle="Kare gönderi şablonları, 1080 × 1080" -->', $cssHepsi, '<div class="izgara">' . $gonderiler . '</div>');
onizleme("$K/project/components/InstagramHikaye/preview.html", '<!-- @dsCard group="Instagram" height=560 subtitle="Hikâye şablonu, 1080 × 1920" -->', $cssHepsi, '<div class="izgara">' . $kutu(($S['story']['html'])(), .26, 1920, $S['story']['ad'] . ' · 1080 × 1920') . '</div>');
$kapaklar = $kutu(($S['profil']['html'])(), .22, 1080, 'Profil fotoğrafı');
foreach (array_keys($KAPAK) as $v) { $kapaklar .= '<figure><div class="olc yuvarlak" style="--s:.14;width:151px;height:151px;border-radius:50%">' . ($S['kapak']['html'])($v) . '</div><figcaption>' . $KAPAK[$v] . '</figcaption></figure>'; }
onizleme("$K/project/components/ProfilVeKapaklar/preview.html", '<!-- @dsCard group="Instagram" height=300 subtitle="Profil fotoğrafı ve öne çıkan kapakları" -->', $cssHepsi . '.olc.yuvarlak{border-radius:50%}', '<div class="izgara" style="align-items:center">' . $kapaklar . '</div>');
echo "tamam: " . count($liste) . " render\n";

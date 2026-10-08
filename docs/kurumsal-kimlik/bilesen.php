<?php
/* Kimlik bileşenleri: Logo, GozEseli, Damga, Buton (önizleme + README) ve varlık grubu README'leri. */
$P = __DIR__ . '/project';
$GEO = '<g transform="rotate(-10 50 50)" fill="%s"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z"/><circle cx="50" cy="50" r="7"/></g>';
$isaret = fn (string $id, string $tek = '') => '<svg viewBox="0 0 100 100" aria-hidden="true">' . ($tek ? '' : '<defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs>') . sprintf($GEO, $tek ?: "url(#$id)") . '</svg>';

function yaz(string $yol, string $icerik): void { @mkdir(dirname($yol), 0777, true); file_put_contents($yol, $icerik); }
function belge(string $marker, string $css, string $govde): string {
    return "$marker\n<!doctype html>\n<html lang=\"tr\">\n<head><meta charset=\"utf-8\"><style>\nhtml,body{margin:0}\nbody{background:var(--paper);color:var(--ink);font-family:var(--font-sans);padding:16px}\n$css\n</style></head>\n<body>\n$govde\n</body>\n</html>\n";
}

/* ---------- Logo ---------- */
yaz("$P/components/Logo/preview.html", belge('<!-- @dsCard group="Marka" height=330 subtitle="Kilit, varyantlar, boşluk" -->', <<<'CSS'
.satir{display:flex;flex-wrap:wrap;gap:16px}
.pano{flex:1 1 260px;min-width:0;display:grid;place-items:center;height:132px;border-radius:var(--radius-lg);border:1px solid var(--line)}
.pano.acik{background:#f5f3f3}.pano.gece{background:#141012;border-color:#141012}
.kilit{display:flex;align-items:center;gap:12px;font-weight:800;font-size:32px;letter-spacing:-.02em}
.kilit svg{width:48px;height:48px}
.acik .kilit{color:#1b1416}.gece .kilit{color:#fff}
.alt{display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;align-items:center}
.alt figure{margin:0;display:grid;justify-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--ink-2)}
.kutu{display:grid;place-items:center;width:84px;height:84px;border-radius:var(--radius-md);border:1px solid var(--line);background:var(--surface)}
.kutu svg{width:52px;height:52px}
.kutu.gece{background:#141012;border-color:#141012}
.bosluk{position:relative;display:grid;place-items:center;width:150px;height:84px;border:1px dashed var(--brand-ink);border-radius:6px}
.bosluk svg{width:36px;height:36px}
.bosluk span{position:absolute;font-family:var(--font-mono);font-size:10px;color:var(--brand-ink)}
.bosluk .u{top:4px}.bosluk .s{left:6px}
CSS, '<div class="satir"><div class="pano acik"><div class="kilit">' . $isaret('l1') . 'OptiFlow</div></div><div class="pano gece"><div class="kilit">' . $isaret('l2') . 'OptiFlow</div></div></div>
<div class="alt">
<figure><div class="kutu">' . $isaret('l3') . '</div>Renkli işaret</figure>
<figure><div class="kutu">' . $isaret('', '#141012') . '</div>Tek renk · gece</figure>
<figure><div class="kutu gece">' . $isaret('', '#ffffff') . '</div>Tek renk · beyaz</figure>
<figure><div class="kutu" style="border:0;background:none"><svg viewBox="0 0 100 100" style="width:72px;height:72px"><defs><linearGradient id="ua" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff6b81"/><stop offset="1" stop-color="#b4233c"/></linearGradient></defs><rect width="100" height="100" rx="22" fill="#141012"/><g transform="translate(14 14) scale(.72)">' . sprintf($GEO, 'url(#ua)') . '</g></svg></div>Uygulama simgesi</figure>
<figure><div class="bosluk"><span class="u">½ çap</span><span class="s">½</span>' . $isaret('l4') . '</div>Boş alan</figure>
</div>'));
yaz("$P/components/Logo/README.md", <<<'MD'
OptiFlow logosu: daire içinde göz biçimli mercek işareti ve yanında "OptiFlow" kelimesi.

**Ne zaman:** Her yüzeyde bir kez; sitede başlıkta, Instagram şablonlarında sol altta, belgelerde üstte.

**Kullanan sağlar:** Zemin rengi. Açık zeminde (`paper`, `surface`) renkli işaret + `ink` kelime; gece zemininde (`night`) renkli işaret + beyaz kelime; fotoğraf üstünde beyaz tek renk işaret.

**Ölçü:** İşaret yüksekliği = kelime boyu × 1,5; işaret ile kelime arası = kelime boyu × 0,5. Kelime Manrope 800, harf aralığı −0,02em. İşaretin her yanında en az yarım çap boşluk. En küçük işaret 20 px.

**Dosyalar:** `Logolar` varlık grubu — `optiflow-isaret.svg` (renkli), `optiflow-isaret-gece.svg` (#141012), `optiflow-isaret-beyaz.svg` (#ffffff), `optiflow-uygulama-simgesi.svg`, kare `logo-acik.png` ve `logo-koyu.png`.

**Yapılmaz:** İşareti döndürmek (−10° eğimi işaretin kendisinde), degradeyi değiştirmek, gölge veya kontur eklemek, kelimeyi başka yazı tipiyle ya da büyük harfle yazmak.
MD);

/* ---------- Göz eşeli ---------- */
yaz("$P/components/GozEseli/preview.html", belge('<!-- @dsCard group="Marka" height=330 subtitle="Tipografik imza" -->', <<<'CSS'
.esel{max-width:640px;border-top:5px solid var(--ink)}
.s{display:grid;grid-template-columns:1fr auto;align-items:baseline;gap:12px;border-bottom:1px solid var(--line);padding:2px 0 5px}
.s b{font-weight:800;letter-spacing:.035em;line-height:1.02;white-space:nowrap}
.s i{font-style:normal;font-family:var(--font-mono);font-size:12px;color:var(--ink-2);font-variant-numeric:tabular-nums}
.s1 b{font-size:84px;background:linear-gradient(135deg,var(--brand-bright),var(--brand-deep));-webkit-background-clip:text;background-clip:text;color:transparent}
.s2 b{font-size:58px}.s3 b{font-size:38px}.s4 b{font-size:26px}
.s4 i{color:var(--brand-ink)}
CSS, '<div class="esel"><div class="s s1"><b>Reçete</b><i>0,1</i></div><div class="s s2"><b>Medula\'dan</b><i>0,4</i></div><div class="s s3"><b>tek tuşla</b><i>0,6</i></div><div class="s s4"><b>siparişe.</b><i>1,0</i></div></div>'));
yaz("$P/components/GozEseli/README.md", <<<'MD'
Göz eşeli, bir cümleyi muayene tablosu gibi satır satır küçülterek dizen tipografik imzadır.

**Ne zaman:** Sayfa veya gönderi başına en fazla bir kez, ana mesaj için (sitenin girişi, kapanış çağrısı, Instagram özellik kartı, hikâye).

**Kullanan sağlar:** 3–5 satıra bölünmüş tek cümle; her satır için keskinlik değeri (`0,1`, `0,2`, `0,4`, `0,6`, `0,8`, `1,0` — büyükten küçüğe).

**Kurallar:** Üstte kalın `ink` çizgi; satırlar `line` ile ayrılır; yazı `esel-1` → `esel-4`, ilk satır brand degradesi; keskinlik değeri sağda mono (`veri`), son satırınki `brand-ink`. Cümle son satırda noktayla biter. Hareketli sürümde satırlar yukarıdan aşağı bulanıktan netleşir.

**Yapılmaz:** Satırları aynı boyda yazmak, değerleri karıştırmak (küçülen satıra büyüyen keskinlik), eşeli paragraf metni için kullanmak.
MD);

/* ---------- Damga ---------- */
yaz("$P/components/Damga/preview.html", belge('<!-- @dsCard group="Marka" height=230 subtitle="Kırmızı kalem, değil damgası, TAMAM" -->', <<<'CSS'
.kart{display:flex;flex-wrap:wrap;align-items:center;gap:14px 20px;padding:18px 20px;border-radius:var(--radius-md);background:#1d1719;font-family:var(--font-mono);font-size:22px;color:#e9e2e4}
.cizik{position:relative;color:rgba(233,226,228,.5)}
.cizik::after{content:"";position:absolute;left:-3%;right:-3%;top:52%;height:4px;border-radius:2px;background:#ff3d5e;transform:rotate(-2.5deg);box-shadow:0 0 12px rgba(255,61,94,.7)}
.degil{display:inline-block;padding:0 10px 3px;border:3px solid #ff3d5e;border-radius:8px;color:#ff6b81;font-family:var(--font-sans);font-weight:800;transform:rotate(-7deg)}
.adimlar{display:flex;flex-wrap:wrap;gap:22px;margin-top:22px}
.tamam{display:inline-block;padding:2px 8px;border:2px solid var(--brand-bright);border-radius:6px;color:var(--brand-ink);background:var(--paper);font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.14em;transform:rotate(-9deg)}
.adim{display:flex;align-items:center;gap:10px;font-weight:700}
.no{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--brand-bright);color:#fff;font-size:13px}
CSS, '<div class="kart"><span>OptiFlow bir <span class="cizik">yazılım ofisinde</span></span><span class="degil">değil,</span></div>
<div class="adimlar"><div class="adim"><span class="no">1</span>Reçete gelir <span class="tamam">TAMAM</span></div><div class="adim"><span class="no">2</span>Sipariş açılır <span class="tamam">TAMAM</span></div></div>'));
yaz("$P/components/Damga/README.md", <<<'MD'
Damga, eski yöntemi kırmızı kalemle çizip doğrusunu damgalayan ve biten adımı "TAMAM" diye mühürleyen işarettir.

**Ne zaman:** Kök hikâyesi ("yazılım ofisinde" çizilir, "değil," damgası), önce/sonra karşılaştırmaları, adım adım süreçler (sipariş yolu).

**Kullanan sağlar:** Çizilecek metin ve damga sözcüğü (tek kelime: "değil,", "TAMAM").

**Kurallar:** Çizik `#ff3d5e`, hafif eğik (−2,5°), parlama gölgeli; damga −7° ile −9° eğik, kenarlı, içi boş. Gece zemininde damga `coral`, açık zeminde `brand-ink` yazı + `brand-bright` kenar. Bir yüzeyde en fazla bir "değil," damgası.

**Yapılmaz:** Çiziği okunmaz yapmak (eski metin %50 opaklıkla okunur kalır), damgayı süs olarak tekrarlamak.
MD);

/* ---------- Buton ---------- */
yaz("$P/components/Buton/preview.html", belge('<!-- @dsCard group="Arayüz" height=170 subtitle="Dolu, çizgili, gece üstünde" -->', <<<'CSS'
.sira{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:15px 26px;border-radius:var(--radius-btn);font-weight:800;font-size:15.5px;text-decoration:none;border:2px solid transparent;white-space:nowrap;cursor:pointer}
.btn:focus-visible{outline:3px solid var(--brand-ink);outline-offset:3px}
.dolu{background:linear-gradient(135deg,var(--brand-bright),var(--brand-deep));color:var(--on-brand);box-shadow:var(--shadow-brand)}
.cizgi{border-color:var(--ink);color:var(--ink);background:none}
.gece{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding:18px;border-radius:var(--radius-lg);background:var(--night)}
.gece .cizgi{border-color:var(--on-night);color:var(--on-night)}
.not{font-size:12.5px;color:var(--ink-2);margin-top:8px}
CSS, '<div class="sira"><a class="btn dolu" href="#">30 gün ücretsiz deneyin</a><a class="btn cizgi" href="#">WhatsApp\'tan demo isteyin</a></div>
<div class="gece"><a class="btn dolu" href="#">Mağazamı oluştur</a><a class="btn cizgi" href="#">Giriş yap</a></div>'));
yaz("$P/components/Buton/README.md", <<<'MD'
Buton, sitedeki ve uygulamadaki eylem düğmesidir: dolu (bordo degrade) ve çizgili iki türü vardır.

**Ne zaman:** Dolu düğme yüzey başına bir ana eylem için ("30 gün ücretsiz deneyin", "Mağazamı oluştur"); çizgili düğme ikinci eylem için ("WhatsApp'tan demo isteyin", "Giriş yap").

**Kullanan sağlar:** Fiil ile başlayan, ne olacağını söyleyen kısa metin. Deneme düğmesinde her zaman "30 gün ücretsiz".

**Ölçü:** 15px × 26px iç boşluk, `radius-btn`, Manrope 800 15,5px; dolu düğmede `on-brand` yazı ve `shadow-brand`. Gece zemininde çizgili düğme `on-night` kenar.

**Yapılmaz:** Yan yana iki dolu düğme, "Tıklayın" gibi belirsiz metin, bordo dışında dolu düğme.
MD);

/* ---------- Instagram bileşen README'leri ---------- */
yaz("$P/components/InstagramGonderi/README.md", <<<'MD'
Instagram kare gönderi şablonları (1080 × 1080): bilgi kartı, özellik kartı (göz eşeli), kök hikâyesi ve "1 mi, 2 mi?" karşılaştırması.

**Ne zaman:** Bilgi kartı SGK/Medula rehberi kaydırmalı gönderilerinin kapağı; özellik kartı tek bir özelliği anlatır; kök hikâyesi hesabın sabit (pinned) gönderisi; karşılaştırma eski yöntem ile OptiFlow'u yan yana koyar.

**Kullanan sağlar:** Başlık (en fazla 7 kelime, vurgulanacak 1–3 kelime `coral`), etiket (mono, örn. "SGK REHBERİ"), kaydırmalıda sayfa numarası.

**Kurallar:** Kenar boşluğu 80 px (`ig-kenar`), logo sol altta, nişangâh tek ve kenardan taşarak; gece ve açık şablonlar ızgarada dönüşümlü. Hazır PNG'ler `Instagram` varlık grubunda; düzenlenebilir kaynaklar depoda `docs/kurumsal-kimlik/sablonlar/`.
MD);
yaz("$P/components/InstagramHikaye/README.md", <<<'MD'
Instagram hikâye şablonu (1080 × 1920): ay sonu hatırlatması — göz eşeli başlık, termal fiş ve "30 gün ücretsiz" çağrısı.

**Ne zaman:** Her ayın son günlerinde; kampanya ve duyurularda aynı iskelet (eşel + kanıt + çağrı) kullanılır.

**Kullanan sağlar:** Üst etiket ("AY SONUNA 2 GÜN"), 3–4 satırlık eşel cümlesi, fiş satırları (örnek veri), çağrı metni.

**Kurallar:** Üst 250 px ve alt 300 px Instagram arayüzü için boş bırakılır; çağrı kutusu beyaz, yazı `brand-deep`; bağlantı çıkartması çağrı kutusunun üstüne konur.
MD);
yaz("$P/components/ProfilVeKapaklar/README.md", <<<'MD'
Profil fotoğrafı ve öne çıkan hikâye kapakları.

**Profil:** gece zemininde renkli işaret, ortada; Instagram daire kırpması işareti kesmez.

**Öne çıkanlar:** SGK · ATÖLYE · FİYAT · REHBER · DEMO — gece zemini, lensmetre nişangâhı, `coral` nokta, mono etiket. Yeni kapak aynı iskeletle, tek kelimelik mono etiketle eklenir.
MD);

/* ---------- varlık grubu README'leri ---------- */
yaz("$P/assets/Logolar/README.md", "Logo dosyaları. `optiflow-isaret.svg` renkli işaret (degrade #ff6b81 → #d0334f → #8f1a2e); `optiflow-isaret-gece.svg` tek renk, mürekkep #141012 (açık zeminler); `optiflow-isaret-beyaz.svg` tek renk, mürekkep #ffffff (gece zemini ve fotoğraflar); `optiflow-uygulama-simgesi.svg` uygulama/favicon simgesi (gece zeminli yuvarlak kare); `logo-acik.png` ve `logo-koyu.png` 1080 × 1080 kare logo görselleri.\n");
yaz("$P/assets/Instagram/README.md", "Instagram için hazır PNG'ler. Kare gönderiler 1080 × 1080: `bilgi.png`, `ozellik.png`, `koken.png`, `muayene.png`. Hikâye 1080 × 1920: `story.png`. Profil fotoğrafı `profil.png`; öne çıkan kapakları `kapak-sgk.png`, `kapak-atolye.png`, `kapak-fiyat.png`, `kapak-rehber.png`, `kapak-demo.png`. Örnek metinler değiştirilecekse kaynak şablonlar depoda `docs/kurumsal-kimlik/sablonlar/`.\n");
echo "tamam\n";

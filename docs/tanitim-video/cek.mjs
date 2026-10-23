// OptiFlow tanıtım filmlerini kare kare çeker, seslendirmeyi yerleştirir ve MP4'e çevirir.
//   node docs/tanitim-video/cek.mjs <film.html> <cikti.mp4> [--fps 30] [--kare 3.5,8.2] [--ses <klasör>] [--sesjson <ses.json>]
//   --kare     : yalnızca o saniyelerin PNG'leri (hızlı kontrol)
//   --ses      : <sahne-adı>.wav dosyalarının klasörü; sahneler ses süresine göre uzar, ses sahne başına yerleşir
//   --muzik    : fon müziği dosyası; konuşmada kısılır (--muzik-seviye 0.42, --muzik-bas 1.2 sn baş kırpma)
//   --tempo    : seslendirme hız çarpanı (varsayılan 1.06; perdeyi bozmaz)
//   --sesjson  : filmin seslendirme metinlerini ses.json olarak yazar (Seslendir iş akışı bunu okur) ve çıkar
// Gerekenler: Node 18+, playwright (Chromium), ffmpeg/ffprobe.
import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync, existsSync, readdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const arg = process.argv.slice(2);
const film = path.resolve(arg[0]);
const cikti = path.resolve(arg[1] || 'film.mp4');
const deger = (ad, v) => { const i = arg.indexOf(ad); return i >= 0 ? arg[i + 1] : v; };
const fps = Number(deger('--fps', 30));
const kareler = deger('--kare', '');
const sesKlasor = deger('--ses', '');
const sesJson = deger('--sesjson', '');
const muzik = deger('--muzik', '');          // fon müziği (isteğe bağlı): konuşmada kısılır, sonu filmin sonuna denk gelir
const muzikSeviye = Number(deger('--muzik-seviye', 0.42));

// Ses ön işleme: baştaki/sondaki sessizlik kırpılır, cümle arası duraklar 0,22 sn'ye iner, hafif hızlandırılır (--tempo).
const tempo = Number(deger('--tempo', 1.06));
const sureler = {};
let sesIsli = '';
if (sesKlasor) {
  sesIsli = mkdtempSync(path.join(tmpdir(), 'optiflow-ses-'));
  for (const f of readdirSync(sesKlasor).filter((x) => x.endsWith('.wav'))) {
    const hedef = path.join(sesIsli, f);
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-i', path.join(sesKlasor, f), '-af',
      `silenceremove=start_periods=1:start_threshold=-45dB,areverse,silenceremove=start_periods=1:start_threshold=-45dB,areverse,silenceremove=stop_periods=-1:stop_duration=0.3:stop_threshold=-45dB:stop_silence=0.22,atempo=${tempo}`, hedef]);
    const s = execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', hedef]).toString().trim();
    sureler[f.replace(/\.wav$/, '')] = Number(s);
  }
}

const tarayici = await chromium.launch({ executablePath: process.env.CHROME || undefined });
const sayfa = await tarayici.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
await sayfa.addInitScript((s) => { window.SES_SURE = s; }, sureler);
sayfa.on('pageerror', (e) => console.error('sayfa hatası:', e.message));
await sayfa.goto(pathToFileURL(film).href + '?cek=1');
await sayfa.evaluate(() => document.fonts.ready);
await sayfa.waitForTimeout(300);
const sure = await sayfa.evaluate(() => window.SURE);
const SES = await sayfa.evaluate(() => window.SES || []);
console.log('süre', sure.toFixed(2), 's ·', JSON.stringify(await sayfa.evaluate(() => window.SAHNELER)));

if (sesJson) {
  writeFileSync(sesJson, JSON.stringify(SES.map(({ id, metin }) => ({ id, metin })), null, 1) + '\n');
  console.log('yazıldı:', sesJson);
  await tarayici.close();
  process.exit(0);
}

if (kareler) {
  for (const s of kareler.split(',').map(Number)) {
    await sayfa.evaluate((t) => window.ciz(t), s);
    const dosya = cikti.replace(/\.mp4$/, '') + `-${s.toFixed(2)}.png`;
    await sayfa.screenshot({ path: dosya });
    console.log(dosya);
  }
  await tarayici.close();
  process.exit(0);
}

const klasor = mkdtempSync(path.join(tmpdir(), 'optiflow-kare-'));
const toplam = Math.round(sure * fps);
for (let i = 0; i < toplam; i++) {
  await sayfa.evaluate((t) => window.ciz(t), i / fps);
  await sayfa.screenshot({ path: path.join(klasor, `k${String(i).padStart(5, '0')}.png`) });
  if (i % 120 === 0) process.stdout.write(`kare ${i}/${toplam}\n`);
}
await tarayici.close();

const ff = (a) => new Promise((ok, hata) => {
  const f = spawn('ffmpeg', ['-y', '-loglevel', 'error', ...a], { stdio: 'inherit' });
  f.on('exit', (k) => (k === 0 ? ok() : hata(new Error('ffmpeg ' + k))));
});
const video = sesKlasor || muzik ? path.join(klasor, 'v.mp4') : cikti;
await ff(['-framerate', String(fps), '-i', path.join(klasor, 'k%05d.png'), '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-profile:v', 'high',
  '-crf', '17', '-preset', 'slow', '-movflags', '+faststart', video]);

if (sesKlasor || muzik) {
  const parca = sesKlasor ? SES.filter((s) => existsSync(path.join(sesIsli, s.id + '.wav'))) : [];
  const girdi = parca.flatMap((s) => ['-i', path.join(sesIsli, s.id + '.wav')]);
  let filtre = parca.map((s, i) => `[${i + 1}:a]aresample=48000,adelay=${Math.round(s.t * 1000)}:all=1[a${i}]`).join(';');
  const L = sure;
  if (parca.length) filtre += ';' + parca.map((_, i) => `[a${i}]`).join('') + `amix=inputs=${parca.length}:normalize=0,apad=whole_dur=${L.toFixed(3)},asplit=2[vo][sc]`;
  if (muzik) {
    // Müzik: baştaki sessizlik kırpılır. Film müzikten uzunsa başı tekrarlanıp 2 sn çapraz geçişle eklenir; böylece
    // müziğin kapanışı filmin sonuna oturur. Kısaysa baştan alınıp sonda yumuşakça kısılır.
    const mi = parca.length + 1;
    const ham = Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', muzik]).toString());
    const bas = Number(deger('--muzik-bas', 1.2));
    const M = ham - bas;
    const ort = `aresample=48000,aformat=channel_layouts=stereo`;
    if (L > M) {
      const on = L - M + 2;
      filtre += `${filtre ? ';' : ''}[${mi}:a]${ort},atrim=${bas}:${(bas + on).toFixed(3)},asetpts=N/SR/TB[m1];[${mi + 1}:a]${ort},atrim=${bas},asetpts=N/SR/TB[m2];[m1][m2]acrossfade=d=2:c1=tri:c2=tri,afade=in:d=0.4[mz]`;
    } else {
      filtre += `${filtre ? ';' : ''}[${mi}:a]${ort},atrim=${bas}:${(bas + L).toFixed(3)},asetpts=N/SR/TB,afade=in:d=0.4,afade=out:st=${(L - 1.8).toFixed(3)}:d=1.8[mz]`;
    }
    filtre += `;[mz]volume=${muzikSeviye}[mzv]`;
    if (parca.length) filtre += `;[mzv][sc]sidechaincompress=threshold=0.02:ratio=6:attack=30:release=450:makeup=1[duk];[vo][duk]amix=inputs=2:normalize=0,loudnorm=I=-15:TP=-1.5:LRA=11[ses]`;
    else filtre += `;[mzv]loudnorm=I=-15:TP=-1.5:LRA=11[ses]`;
  } else filtre += `;[vo]loudnorm=I=-16:TP=-1.5:LRA=11[ses];[sc]anullsink`;
  const mGirdi = muzik ? ['-i', muzik, '-i', muzik] : [];
  await ff(['-i', video, ...girdi, ...mGirdi, '-filter_complex', filtre, '-map', '0:v', '-map', '[ses]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k',
    '-t', L.toFixed(3), '-movflags', '+faststart', cikti]);
}
rmSync(klasor, { recursive: true, force: true });
console.log('hazır:', cikti);

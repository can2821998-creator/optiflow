// OptiFlow tanıtım filmlerini kare kare çeker ve MP4'e çevirir.
//   node docs/tanitim-video/cek.mjs <film.html> <cikti.mp4> [--fps 30] [--kare 3.5,8.2]
//   --kare verilirse yalnızca o saniyelerin PNG'leri çıkar (hızlı kontrol için).
// Gerekenler: Node 18+, playwright (Chromium), ffmpeg.
import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const arg = process.argv.slice(2);
const film = path.resolve(arg[0]);
const cikti = path.resolve(arg[1] || 'film.mp4');
const deger = (ad, v) => { const i = arg.indexOf(ad); return i >= 0 ? arg[i + 1] : v; };
const fps = Number(deger('--fps', 30));
const kareler = deger('--kare', '');

const tarayici = await chromium.launch({ executablePath: process.env.CHROME || undefined });
const sayfa = await tarayici.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
await sayfa.goto(pathToFileURL(film).href + '?cek=1');
await sayfa.evaluate(() => document.fonts.ready);
await sayfa.waitForTimeout(300);
const sure = await sayfa.evaluate(() => window.SURE);

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
  if (i % 60 === 0) process.stdout.write(`kare ${i}/${toplam}\n`);
}
await tarayici.close();

await new Promise((ok, hata) => {
  const f = spawn('ffmpeg', ['-y', '-loglevel', 'error', '-framerate', String(fps), '-i', path.join(klasor, 'k%05d.png'),
    '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-crf', '17', '-preset', 'slow', '-movflags', '+faststart', cikti], { stdio: 'inherit' });
  f.on('exit', (k) => (k === 0 ? ok() : hata(new Error('ffmpeg ' + k))));
});
rmSync(klasor, { recursive: true, force: true });
console.log('hazır:', cikti);

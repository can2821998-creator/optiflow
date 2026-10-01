// Regenerates the golden *.metin.txt files from the current extractor. Review the diff!
import { build } from 'esbuild';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'ofx-')), 'x.mjs');
await build({ entryPoints: [path.join(here, '../../src/medula/extractor.ts')], bundle: true, format: 'esm', outfile: out, logLevel: 'warning' });
const m = await import(out);
for (const f of ['recete-uzak', 'recete-yakin', 'recete-duz-metin']) {
  const dom = new JSDOM(fs.readFileSync(path.join(here, `${f}.html`), 'utf8'), { url: 'https://gss.sgk.gov.tr/Optik/x', pretendToBeVisual: true });
  fs.writeFileSync(path.join(here, `${f}.metin.txt`), m.extractFrame(dom.window.document, dom.window).text);
  console.log('wrote', f);
}

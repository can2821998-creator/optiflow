/**
 * PARITY: run the ORIGINAL kopru-eklenti/icerik.js (unchanged, from the repo)
 * and the new TypeScript extractor on the same fixtures; the transferred text
 * must be identical (spec §32: "at least as reliably as the existing Chrome
 * content script"). The only expected difference: the legacy script injects its
 * own "Atölyeye aktar" button into the page, whose label ends up in its text.
 */
import fs from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { JSDOM } from 'jsdom';
import { extractFrame } from '../src/medula/extractor';
import { FIXTURES, win } from './helpers';

const LEGACY = path.resolve(__dirname, '../../kopru-eklenti/icerik.js');
const haveLegacy = fs.existsSync(LEGACY);

function runLegacy(html: string): Promise<string> {
  const dom = new JSDOM(html, { url: 'https://gss.sgk.gov.tr/Optik/x', runScripts: 'outside-only', pretendToBeVisual: true });
  return new Promise((resolve) => {
    (dom.window as unknown as Record<string, unknown>).chrome = {
      runtime: {
        sendMessage: (msg: { metin: string }, cb: (r: unknown) => void) => {
          resolve(msg.metin);
          cb({ ok: true });
        },
      },
    };
    dom.window.eval(fs.readFileSync(LEGACY, 'utf8'));
    (dom.window.document.getElementById('optiflow-kopru-dugme') as HTMLButtonElement).click();
  });
}

const stripLegacyButton = (s: string) => s.replace(/\s*Atölyeye aktar$/, '');

describe.skipIf(!haveLegacy)('legacy parity (kopru-eklenti/icerik.js ⇄ src/medula/extractor.ts)', () => {
  for (const f of fs.readdirSync(FIXTURES).filter((x) => x.endsWith('.html'))) {
    it(`identical text for ${f}`, async () => {
      const html = fs.readFileSync(path.join(FIXTURES, f), 'utf8');
      const legacy = stripLegacyButton(await runLegacy(html));
      const dom = new JSDOM(html, { url: 'https://gss.sgk.gov.tr/Optik/x', pretendToBeVisual: true });
      expect(extractFrame(dom.window.document, win(dom)).text).toBe(legacy);
    });
  }
});

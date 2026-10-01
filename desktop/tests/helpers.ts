import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';
import type { ExtractorWindow } from '../src/medula/extractor-types';

export const FIXTURES = path.join(__dirname, 'fixtures');

export function fixtureDom(name: string, url = 'https://gss.sgk.gov.tr/Optik/ReceteDetay.aspx'): JSDOM {
  return new JSDOM(fs.readFileSync(path.join(FIXTURES, name), 'utf8'), { url, pretendToBeVisual: true });
}

export function htmlDom(html: string, url = 'https://gss.sgk.gov.tr/Optik/x'): JSDOM {
  return new JSDOM(`<!doctype html><html><body>${html}</body></html>`, { url, pretendToBeVisual: true });
}

export const win = (d: JSDOM) => d.window as unknown as ExtractorWindow;

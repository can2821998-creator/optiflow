import fs from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { extractFrame, fieldValue, isCountedField, pageText } from '../../src/medula/extractor';
import { FIXTURES, fixtureDom, htmlDom, win } from '../helpers';

describe('extractor – field values', () => {
  it('reads the selected <select> option text, and treats "Seçiniz" as empty', () => {
    const d = htmlDom('<select id="a"><option>Seçiniz</option><option selected>-</option></select><select id="b"><option selected>Seçiniz</option></select>');
    expect(fieldValue(d.window.document.getElementById('a')!)).toBe('-');
    expect(fieldValue(d.window.document.getElementById('b')!)).toBe('');
  });
  it('checkbox / radio: value or "evet" when checked, empty otherwise', () => {
    const d = htmlDom('<input type="checkbox" id="a" checked><input type="radio" id="b" value="Uzak" checked><input type="checkbox" id="c">');
    const g = (id: string) => d.window.document.getElementById(id)!;
    expect(fieldValue(g('a'))).toBe('evet');
    expect(fieldValue(g('b'))).toBe('Uzak');
    expect(fieldValue(g('c'))).toBe('');
  });
  it('never counts hidden, password, button, submit, file fields', () => {
    const d = htmlDom(['hidden', 'password', 'button', 'submit', 'reset', 'image', 'file'].map((t) => `<input type="${t}">`).join(''));
    for (const el of Array.from(d.window.document.querySelectorAll('input'))) expect(isCountedField(el)).toBe(false);
  });
});

describe('extractor – page text', () => {
  it('inserts «value» markers, TAB between cells, NEWLINE between rows', () => {
    const d = htmlDom('<table><tr><td>Sferik</td><td><input value="1,50"></td></tr><tr><td>Aks</td><td><input value="90"></td></tr></table>');
    expect(pageText(d.window.document, win(d)).text).toBe('Sferik\t«1,50»\nAks\t«90»');
  });
  it('keeps empty fields as «» so columns stay aligned', () => {
    const d = htmlDom('<table><tr><td><input value=""></td><td><input value="5"></td></tr></table>');
    expect(pageText(d.window.document, win(d)).text).toBe('«»\t«5»');
  });
  it('excludes scripts, styles, option lists, hidden and invisible fields, and password VALUES', () => {
    const d = fixtureDom('recete-uzak.html');
    const t = pageText(d.window.document, win(d)).text;
    for (const secret of ['GIZLI-SCRIPT-METNI', 'GIZLI-VIEWSTATE-DEGERI', 'GIZLI-PAROLA', 'GIZLI-GORUNMEZ-ALAN', 'Kaydet', 'Vazgeç']) {
      expect(t).not.toContain(secret);
    }
    expect(t).not.toMatch(/Seçiniz/); // option texts never leak
  });
  it('never reads the value of a password field (value getter is not touched)', () => {
    const d = htmlDom('<label>Şifre <input type="password" id="p"></label><input value="x">');
    const p = d.window.document.getElementById('p')!;
    Object.defineProperty(p, 'value', {
      get() {
        throw new Error('password value read!');
      },
    });
    expect(() => extractFrame(d.window.document, win(d))).not.toThrow();
    expect(extractFrame(d.window.document, win(d)).text).toBe('Şifre «x»');
  });
  it('textarea content comes once, as «…»', () => {
    const d = htmlDom('<p>Not</p><textarea>ayrı gözlük</textarea>');
    // block marker is emitted BEFORE the element, exactly like the legacy script
    expect(pageText(d.window.document, win(d)).text).toBe('Not «ayrı gözlük»');
  });
  it('produces the golden text for every prescription fixture', () => {
    for (const f of ['recete-uzak', 'recete-yakin', 'recete-duz-metin']) {
      const d = fixtureDom(`${f}.html`);
      const golden = fs.readFileSync(path.join(FIXTURES, `${f}.metin.txt`), 'utf8');
      expect(extractFrame(d.window.document, win(d)).text, f).toBe(golden);
    }
  });
});

describe('extractor – metadata', () => {
  it('returns MedulaExtraction with diagnostics and no DOM side effects', () => {
    const d = fixtureDom('recete-uzak.html');
    const before = d.window.document.body.innerHTML;
    const x = extractFrame(d.window.document, win(d), new Date('2026-09-24T10:00:00Z'));
    expect(x.detectedPrescription).toBe(true);
    expect(x.extractedFieldCount).toBe(19);
    expect(x.timestamp).toBe('2026-09-24T10:00:00.000Z');
    expect(x.title).toBe('Medula Optik - Reçete Detay');
    expect(d.window.document.body.innerHTML).toBe(before); // unlike the extension, no button is injected
  });
  it('prepends a long user selection that is not already in the text (legacy behaviour)', () => {
    const d = htmlDom('<p>Sayfa metni burada</p>');
    const long = 'Seçili metin '.repeat(10);
    (d.window as unknown as { getSelection: () => string }).getSelection = () => long;
    expect(extractFrame(d.window.document, win(d)).text.startsWith(long.trim())).toBe(true);
  });
});

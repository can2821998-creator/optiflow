import { describe, expect, it } from 'vitest';
import { foldTr, probeDocument } from '../../src/medula/detector';
import { fixtureDom, htmlDom, win } from '../helpers';

const probe = (f: string) => {
  const d = fixtureDom(f);
  return probeDocument(d.window.document, win(d));
};

describe('prescription page detection', () => {
  it.each(['recete-uzak.html', 'recete-yakin.html', 'recete-duz-metin.html'])('detects %s', (f) => {
    const p = probe(f);
    expect(p.detectedPrescription).toBe(true);
    expect(p.looksLikeLogin).toBe(false);
  });
  it('does not guess on the prescription LIST or the menu frame', () => {
    expect(probe('liste.html').detectedPrescription).toBe(false);
    expect(probe('menu-cercevesi.html').detectedPrescription).toBe(false);
  });
  it('recognises the SGK login screen (session expired) and never treats it as a prescription', () => {
    const p = probe('giris.html');
    expect(p.looksLikeLogin).toBe(true);
    expect(p.detectedPrescription).toBe(false);
  });
  it('labels must be separate words (no false positive from glued text)', () => {
    const d = htmlDom('<p>SAGCAMSFERIK</p>');
    expect(probeDocument(d.window.document, win(d)).detectedPrescription).toBe(false);
  });
  it('adjacent table cells are not glued together (T.C. label + value)', () => {
    const d = htmlDom('<table><tr><td>T.C. Kimlik No</td><td>12345678901</td></tr></table>');
    expect(probeDocument(d.window.document, win(d)).score).toBe(10);
  });
  it('folds Turkish letters like sgk_norm()', () => {
    expect(foldTr('Sağ Cam · Sferik İşaret ÇÖĞÜŞıi')).toBe('SAG CAM SFERIK ISARET COGUSII');
  });
});

/**
 * Extractor output → REAL production parser (app/sgk.php via PHP CLI).
 * Proves the desktop capture stays compatible with sgk_parse() (spec §32).
 * Skipped automatically when `php` (with mbstring) is not installed.
 */
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { extractFrame } from '../../src/medula/extractor';
import { FIXTURES, fixtureDom, win } from '../helpers';

const HARNESS = path.join(__dirname, 'parse.php');
const ROOT = path.resolve(__dirname, '../../..');
const havePhp = (() => {
  const r = spawnSync('php', ['-r', 'echo function_exists("mb_strtoupper") ? "ok" : "no";']);
  return r.status === 0 && String(r.stdout) === 'ok' && fs.existsSync(path.join(ROOT, 'app/sgk.php'));
})();

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Parsed = Record<string, any>;
const parse = (text: string): Parsed =>
  JSON.parse(execFileSync('php', [HARNESS], { input: text, env: { ...process.env, OPTIFLOW_ROOT: ROOT } }).toString());
const parseFixture = (f: string) => {
  const d = fixtureDom(f);
  return parse(extractFrame(d.window.document, win(d)).text);
};

describe.skipIf(!havePhp)('parser regression (extractor → app/sgk.php)', () => {
  it('distance prescription: R/L SPH/CYL/AXIS, negative & positive, PD, patient, IDs, doctor, facility, report', () => {
    const r = parseFixture('recete-uzak.html');
    expect(r.sag).toEqual({ sph: '-1.50', cyl: '-0.75', aks: '90', add: '' });
    expect(r.sol).toEqual({ sph: '+2.00', cyl: '+0.50', aks: '180', add: '' });
    expect(r.pd).toBe('62');
    expect(r.hasta).toBe('AYŞE YILMAZ');
    expect(r.tc).toBe('12345678901');
    expect(r.erecete).toBe('1A2B3C');
    expect(r.doktor).toBe('MEHMET DEMİR');
    expect(r.tesis).toBe('ÖRNEK DEVLET HASTANESİ');
    expect(r.recete_tarihi).toBe('16.09.2026');
    expect(r.rapor_no).toBe('778899');
    expect(r.rapor_tarihi).toBe('15.09.2026');
    expect(r.tercih).toBe('Tek Odak'); // <select> value
    expect(r.yas).toBe('47');
    expect(r.lens_design).toBe('tek_odak_uzak');
    expect(r.bulunan).toBeGreaterThanOrEqual(15);
  });

  it('near + distance: zero sphere, empty cyl/axis, near values, ADD derived, Turkish names', () => {
    const r = parseFixture('recete-yakin.html');
    expect(r.sag).toEqual({ sph: '0.00', cyl: '-1.25', aks: '5', add: '+2.50' });
    expect(r.sol).toEqual({ sph: '-0.25', cyl: '', aks: '', add: '+2.50' });
    expect(r.yakin_sag).toEqual({ sph: '+2.50', cyl: '-1.25', aks: '5' });
    expect(r.yakin_sol.sph).toBe('+2.25');
    expect(r.ad).toBe('İSMAİL ŞÜKRÜ');
    expect(r.soyad).toBe('ÇAĞLAYANGÖZ');
    expect(r.doktor).toBe('ZEYNEP ÖRNEKOĞLU');
    expect(r.lens_design).toBe('ayri_uzak_yakin');
    expect(r.teshis).toBe(''); // missing label → stays empty, no guessing
  });

  it('read-only view (values as text), irregular whitespace, unexpected labels, combined name label', () => {
    const r = parseFixture('recete-duz-metin.html');
    expect(r.sag).toEqual({ sph: '-3.75', cyl: '0.00', aks: '0', add: '' });
    expect(r.sol).toEqual({ sph: '-4.00', cyl: '-0.25', aks: '170', add: '' });
    expect(r.hasta).toBe('FATMA NUR KAYA');
    expect(r.soyad).toBe('KAYA');
    expect(r.tc).toBe('12345678901');
    expect(r.erecete).toBe('4K5L6M');
    expect(r.doktor).toBe('');
  });

  it('login and list pages yield no prescription values', () => {
    for (const f of ['giris.html', 'liste.html', 'menu-cercevesi.html']) {
      const r = parseFixture(f);
      expect(r.sag.sph, f).toBe('');
      expect(r.sol.sph, f).toBe('');
    }
  });

  it('golden texts on disk parse identically (guards against fixture drift)', () => {
    for (const f of ['recete-uzak', 'recete-yakin', 'recete-duz-metin']) {
      const fromDisk = parse(fs.readFileSync(path.join(FIXTURES, `${f}.metin.txt`), 'utf8'));
      expect(fromDisk).toEqual(parseFixture(`${f}.html`));
    }
  });
});

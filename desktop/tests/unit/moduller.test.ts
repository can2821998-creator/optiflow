import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { parseFeatures, parseSnapshot } from '../../src/main/bridge/transfer-service';
import { parseMedulaReply, parseNumaralar } from '../../src/main/ipc-validation';
import { OfflineStore } from '../../src/main/offline-store';
import { listeNumaralari } from '../../src/medula/liste';
import { OFFLINE_MAX_AGE_MS, SHELL_COMMANDS } from '../../src/shared/constants';
import { htmlDom } from '../helpers';

describe('Medula list reader (numbers only)', () => {
  it('reads the "Reçete No" column and nothing else (no names, T.C., dates)', () => {
    const d = htmlDom(`<table>
      <tr><th>Sıra</th><th>Hasta Adı</th><th>T.C. Kimlik</th><th>E-Reçete No</th><th>Tarih</th></tr>
      <tr><td>1</td><td>AYŞE DENEME</td><td>12345678901</td><td>1A2B3C4</td><td>01.09.2026</td></tr>
      <tr><td>2</td><td>ALİ TEST</td><td>10987654321</td><td>9ZZ8Y7X</td><td>02.09.2026</td></tr>
    </table>`);
    expect(listeNumaralari(d.window.document)).toEqual(['1A2B3C4', '9ZZ8Y7X']);
  });
  it('falls back to 7-char letter+digit codes in text; ignores all-digit tokens', () => {
    const d = htmlDom('<div>Reçete 3AB4C5D hasta 12345678901 tutar 1250000 tarih 2026-09-01 kod ABCDEFG</div>');
    expect(listeNumaralari(d.window.document)).toEqual(['3AB4C5D']);
  });
  it('empty page → empty list', () => {
    expect(listeNumaralari(htmlDom('<p>Giriş</p>').window.document)).toEqual([]);
  });
});

describe('IPC validation for list replies', () => {
  it('accepts well-formed numbers, dedupes', () => {
    expect(parseNumaralar(['1A2B3C4', '1A2B3C4', '9ZZ8Y7X'])).toEqual(['1A2B3C4', '9ZZ8Y7X']);
  });
  it('rejects names, lower case, too long, no digit, too many', () => {
    expect(parseNumaralar(['AYŞE DENEME'])).toBeNull();
    expect(parseNumaralar(['1a2b3c4'])).toBeNull();
    expect(parseNumaralar(['1234567890123'])).toBeNull();
    expect(parseNumaralar(['ABCDEFG'])).toBeNull();
    expect(parseNumaralar(Array.from({ length: 1001 }, (_, i) => `A${i}`))).toBeNull();
  });
  it('parseMedulaReply supports kind "liste"', () => {
    expect(parseMedulaReply({ id: 'x'.repeat(36), kind: 'liste', ok: true, numaralar: ['1A2B3C4'] })).toEqual({ id: 'x'.repeat(36), kind: 'liste', ok: true, numaralar: ['1A2B3C4'] });
    expect(parseMedulaReply({ id: 'x'.repeat(36), kind: 'liste', ok: true, numaralar: 'hepsi' })).toBeNull();
  });
  it('shell commands include the new ones', () => {
    expect(SHELL_COMMANDS).toContain('liste-kontrol');
    expect(SHELL_COMMANDS).toContain('cevrimdisi-ac');
  });
});

describe('server responses', () => {
  it('feature map keeps only boolean entries with safe keys', () => {
    expect(parseFeatures({ whatsapp: true, barkod: false, 'x-y': true, kotu: 'evet' })).toEqual({ whatsapp: true, barkod: false });
    expect(parseFeatures(undefined)).toEqual({});
    expect(parseFeatures([true])).toEqual({});
  });
  it('snapshot is validated and truncated', () => {
    const s = parseSnapshot({ olusturma: 'x', magaza: { id: 1, isim: 'M' }, kullanici: { id: 2, ad: 'K' }, magaza_telefon: 't', siparisler: [{ no: '#00001', ad: 'A'.repeat(500), tel: 1 }] });
    expect(s?.siparisler[0]?.ad.length).toBe(120);
    expect(s?.siparisler[0]?.tel).toBe('');
    expect(parseSnapshot({ magaza: { id: '1' } })).toBeNull();
  });
});

describe('OfflineStore', () => {
  const snap = (store = 1, user = 2) => ({ olusturma: 'x', magaza: { id: store, isim: 'M' }, kullanici: { id: user, ad: 'K' }, magaza_telefon: '', siparisler: [] });
  const fakeCrypto = (on: boolean) => ({
    available: () => on,
    encrypt: (t: string) => Buffer.from(t.split('').reverse().join(''), 'utf8'),
    decrypt: (b: Buffer) => b.toString('utf8').split('').reverse().join(''),
  });
  const tmp = () => path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'of-')), 'c.bin');

  it('persists only encrypted, reloads in a new instance', () => {
    const f = tmp();
    new OfflineStore(f, fakeCrypto(true)).save(snap());
    expect(fs.readFileSync(f, 'utf8')).not.toContain('"magaza"');
    expect(new OfflineStore(f, fakeCrypto(true)).load()?.magaza.id).toBe(1);
  });
  it('without OS encryption nothing is written to disk', () => {
    const f = tmp();
    const s = new OfflineStore(f, fakeCrypto(false));
    s.save(snap());
    expect(fs.existsSync(f)).toBe(false);
    expect(s.load()?.magaza.id).toBe(1); // memory only
  });
  it('expires after max age and checks the owner', () => {
    const f = tmp();
    let now = 1_000_000;
    const s = new OfflineStore(f, fakeCrypto(true), () => now);
    s.save(snap(1, 2));
    expect(s.load({ storeId: 9, userId: 2 })).toBeNull();
    expect(s.meta().available).toBe(true);
    now += OFFLINE_MAX_AGE_MS + 1;
    expect(s.load()).toBeNull();
    expect(fs.existsSync(f)).toBe(false);
  });
  it('clear removes file and memory', () => {
    const f = tmp();
    const s = new OfflineStore(f, fakeCrypto(true));
    s.save(snap());
    s.clear();
    expect(s.meta().available).toBe(false);
    expect(fs.existsSync(f)).toBe(false);
  });
});

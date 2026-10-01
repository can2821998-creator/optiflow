import { describe, expect, it, vi } from 'vitest';
import { chooseFrame } from '../../src/main/bridge/frame-selection';
import { RetryStore } from '../../src/main/bridge/retry-store';
import { buildPayload, OptiflowApi, type FetchLike } from '../../src/main/bridge/transfer-service';
import { validateConfig } from '../../src/main/config';
import { sanitizeFilename } from '../../src/main/filename';
import { parseMedulaReply, parseShellCommand, sanitizeText, truncateUtf8 } from '../../src/main/ipc-validation';
import { redact, redactUrl } from '../../src/shared/redact';
import type { AccountInfo, MedulaProbe } from '../../src/shared/types';

const cfg = validateConfig({
  appEnv: 'production',
  optiflowBaseUrl: 'https://optiflow.com.tr',
  medulaHomeUrl: 'https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces',
  medulaAllowedHosts: ['gss.sgk.gov.tr'],
  medulaExtractHosts: ['gss.sgk.gov.tr'],
  updateUrl: '',
  updateChannel: 'latest',
  logLevel: 'info',
  allowInsecureLocalhost: false,
});

const probe = (p: Partial<MedulaProbe>): MedulaProbe => ({ detectedPrescription: false, score: 0, extractedFieldCount: 0, looksLikeLogin: false, textLength: 0, ...p });

describe('frame selection', () => {
  it('picks the detected frame with the highest score, not simply the top frame', () => {
    const c = chooseFrame([
      { frame: 'top', isTop: true, probe: probe({ score: 10 }) },
      { frame: 'menu', isTop: false, probe: probe({ score: 20 }) },
      { frame: 'content', isTop: false, probe: probe({ detectedPrescription: true, score: 100, extractedFieldCount: 19 }) },
    ]);
    expect(c).toMatchObject({ kind: 'ok', frame: 'content' });
  });
  it('reports login instead of guessing', () => {
    expect(chooseFrame([{ frame: 1, isTop: true, probe: probe({ looksLikeLogin: true }) }]).kind).toBe('login');
    expect(chooseFrame([]).kind).toBe('not-found');
  });
});

describe('IPC validation', () => {
  it('only whitelisted shell commands', () => {
    expect(parseShellCommand('aktar')).toBe('aktar');
    for (const bad of ['eval', 'aktar ', {}, 1, null, '__proto__', 'shell:komut']) expect(parseShellCommand(bad)).toBeNull();
  });
  it('rejects malformed / oversized / extra-typed Medula replies', () => {
    expect(parseMedulaReply(null)).toBeNull();
    expect(parseMedulaReply({ id: 'short', kind: 'probe', ok: true, probe: probe({}) })).toBeNull();
    expect(parseMedulaReply({ id: 'x'.repeat(36), kind: 'run', ok: true })).toBeNull();
    expect(parseMedulaReply({ id: 'x'.repeat(36), kind: 'probe', ok: true, probe: { score: 'a' } })).toBeNull();
    expect(
      parseMedulaReply({
        id: 'x'.repeat(36),
        kind: 'extract',
        ok: true,
        extraction: { text: 'a'.repeat(1_000_001), title: '', url: '', timestamp: '', detectedPrescription: true, extractedFieldCount: 1 },
      }),
    ).toBeNull();
    const ok = parseMedulaReply({ id: 'x'.repeat(36), kind: 'probe', ok: true, probe: probe({ score: 500 }) });
    expect(ok && ok.ok && ok.kind === 'probe' && ok.probe.score).toBe(100);
  });
  it('strips control characters, keeps TAB/LF, normalises CRLF', () => {
    expect(sanitizeText('a\u0000b\tc\r\nd\u001be')).toBe('ab\tc\nde');
  });
  it('byte truncation never splits a UTF-8 character', () => {
    expect(truncateUtf8('ğ'.repeat(10), 7)).toBe('ğğğ');
    expect(new TextEncoder().encode(truncateUtf8('😀😀', 5)).length).toBeLessThanOrEqual(5);
  });
});

describe('payload', () => {
  it('rejects near-empty captures and caps title/bytes', () => {
    expect(buildPayload('   kısa ', 'x')).toBeNull();
    const p = buildPayload('Ş'.repeat(200_000), 'T'.repeat(500))!;
    expect(new TextEncoder().encode(p.metin).length).toBeLessThanOrEqual(190_000);
    expect(p.baslik.length).toBe(160);
    expect(buildPayload('x'.repeat(30), '')!.baslik).toBe('Medula Optik');
  });
});

describe('retry store (memory only, TTL)', () => {
  it('expires and clears', () => {
    let now = 0;
    const r = new RetryStore(1000, () => now);
    r.put({ metin: 'm', baslik: 'b' });
    expect(r.has()).toBe(true);
    now = 1001;
    expect(r.get()).toBeNull();
    r.put({ metin: 'm', baslik: 'b' });
    r.clear();
    expect(r.has()).toBe(false);
  });
});

function jsonRes(status: number, body: unknown, extra: Partial<Response> = {}): Response {
  return { status, ok: status >= 200 && status < 300, redirected: false, headers: new Headers({ 'content-type': 'application/json' }), json: async () => body, ...extra } as Response;
}
const account: AccountInfo = { storeId: 3, storeName: 'Deneme', userId: 9, userName: 'Ali', supportMode: false, package: 'pro', transferAllowed: true, features: {} };
const csrf = 'a'.repeat(64);

describe('OptiFlow API client', () => {
  it('status → account + csrf; sends cookies via the session, never a token', async () => {
    const f = vi.fn<FetchLike>(async () =>
      jsonRes(200, { ok: true, kullanici: { id: 9, ad: 'Ali' }, magaza: { id: 3, isim: 'Deneme' }, destek_modu: false, paket: 'pro', pro: { sgk_kopru: true, uts: true }, csrf }),
    );
    const r = await new OptiflowApi(cfg, f).status();
    expect(r).toEqual({ ok: true, value: { account, csrf } });
    const [url, init] = f.mock.calls[0]!;
    expect(url).toBe('https://optiflow.com.tr/masaustu.php?action=durum');
    expect(init?.credentials).toBe('include');
    expect(JSON.stringify(init?.headers)).not.toMatch(/token/i);
  });
  it('Lite store: package reported and transfer not allowed; pre-4.11 server (no package) stays allowed', async () => {
    const base = { ok: true, kullanici: { id: 9, ad: 'Ali' }, magaza: { id: 3, isim: 'Deneme' }, csrf };
    const lite = await new OptiflowApi(cfg, async () => jsonRes(200, { ...base, paket: 'lite', pro: { sgk_kopru: false, uts: false } })).status();
    expect(lite.ok && lite.value.account).toMatchObject({ package: 'lite', transferAllowed: false });
    const weird = await new OptiflowApi(cfg, async () => jsonRes(200, { ...base, paket: 'gold', pro: {} })).status();
    expect(weird.ok && weird.value.account).toMatchObject({ package: 'lite', transferAllowed: false });
    const old = await new OptiflowApi(cfg, async () => jsonRes(200, base)).status();
    expect(old.ok && old.value.account).toMatchObject({ package: 'lite', transferAllowed: true });
  });
  it('transfer posts JSON with CSRF header and expected account binding', async () => {
    const f = vi.fn<FetchLike>(async () => jsonRes(200, { ok: true, gelen_id: 41, bulunan: 15, ozet: 'x' }));
    const r = await new OptiflowApi(cfg, f).transfer({ metin: 'm'.repeat(30), baslik: 'b' }, csrf, account, '5.0.0');
    expect(r).toEqual({ ok: true, value: { incomingId: 41, found: 15, summary: 'x' } });
    const init = f.mock.calls[0]![1]!;
    expect((init.headers as Record<string, string>)['X-CSRF-Token']).toBe(csrf);
    expect(JSON.parse(String(init.body)).beklenen).toEqual({ magaza_id: 3, kullanici_id: 9 });
  });
  it.each([
    [401, { ok: false, kod: 'oturum_yok' }, 'session-expired'],
    [403, { ok: false, kod: 'destek_modu' }, 'support-mode'],
    [403, { ok: false, kod: 'pro_gerekli' }, 'pro-required'],
    [409, { ok: false, kod: 'hesap_degisti' }, 'account-changed'],
    [419, { ok: false, kod: 'csrf' }, 'account-changed'],
    [429, { ok: false }, 'rate-limited'],
    [500, { ok: false }, 'server-error'],
  ])('HTTP %i maps to its error code', async (status, body, code) => {
    const r = await new OptiflowApi(cfg, async () => jsonRes(status, body)).transfer({ metin: 'm'.repeat(30), baslik: 'b' }, csrf, account, '5');
    expect(r.ok).toBe(false);
    if (!r.ok) expect(r.error.code).toBe(code);
  });
  it('HTML instead of JSON (login page / proxy) is a failure, not success', async () => {
    const res = { status: 200, ok: true, redirected: true, headers: new Headers({ 'content-type': 'text/html' }), json: async () => ({}) } as Response;
    const r = await new OptiflowApi(cfg, async () => res).status();
    expect(r.ok === false && r.error.code).toBe('session-expired');
  });
  it('network errors are normalised to Turkish messages and marked retryable', async () => {
    const r = await new OptiflowApi(cfg, async () => {
      throw new Error('net::ERR_INTERNET_DISCONNECTED');
    }).status();
    expect(r.ok === false && r.error).toMatchObject({ code: 'offline', retryable: true });
    const t = await new OptiflowApi(
      cfg,
      (_u, i) => new Promise((_res, rej) => i?.signal?.addEventListener('abort', () => rej(Object.assign(new Error('aborted'), { name: 'AbortError' })))),
      20,
    ).status();
    expect(t.ok === false && t.error.code).toBe('timeout');
    const c = await new OptiflowApi(cfg, async () => {
      throw new Error('net::ERR_CERT_AUTHORITY_INVALID');
    }).status();
    expect(c.ok === false && c.error).toMatchObject({ code: 'certificate', retryable: false });
  });
});

describe('redaction and filenames', () => {
  it('masks T.C. numbers, tokens, cookies, phones, «form values»', () => {
    const s = redact('tc=12345678901 token 0123456789abcdef0123456789abcdef01234567 Cookie: optiflow=abc; tel 0532 111 22 33 «AYŞE»');
    expect(s).not.toMatch(/12345678901|0123456789abcdef|abc;|111 22 33|AYŞE/);
    expect(redactUrl('https://gss.sgk.gov.tr/Optik/Detay.aspx?receteNo=1A2B3C#x')).toBe('https://gss.sgk.gov.tr/Optik/Detay.aspx?…');
  });
  it('sanitises download names', () => {
    expect(sanitizeFilename('..\\..\\Windows\\evil.exe')).toBe('evil.exe');
    expect(sanitizeFilename('a<b>:"c|?*.csv')).toBe('a_b___c___.csv');
    expect(sanitizeFilename('CON.txt')).toBe('_CON.txt');
    expect(sanitizeFilename('')).toBe('indirme');
    expect(sanitizeFilename('x'.repeat(300) + '.pdf').length).toBe(150);
  });
});

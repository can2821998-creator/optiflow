/**
 * OptiFlow backend client for the desktop bridge (spec §15–16, §21).
 *
 * AUTHENTICATION: requests are made by the MAIN process through the OptiFlow
 * session partition (session.fetch). Chromium attaches the existing httpOnly
 * PHP session cookie itself; the desktop app never reads, copies or stores it,
 * and no bridge token is needed on the desktop at all. The CSRF token is read
 * from the authenticated status endpoint right before each transfer.
 *
 * The fetch function is injected so this module is unit-testable.
 */
import { HTTP_TIMEOUT_MS, MAX_TITLE_CHARS, MAX_TRANSFER_BYTES, MIN_TRANSFER_CHARS } from '../../shared/constants';
import { MSG, normalizeNetError, type NormalizedError } from '../../shared/messages';
import type { AccountInfo, DesktopConfig, OfflineSnapshot, ServerStatusResponse, ServerTransferResponse } from '../../shared/types';
import { sanitizeText, truncateUtf8 } from '../ipc-validation';
import { optiflowEndpoint } from './origin-policy';

export type FetchLike = (input: string, init?: RequestInit) => Promise<Response>;

export interface TransferPayload {
  metin: string;
  baslik: string;
}

export type Result<T> = { ok: true; value: T } | { ok: false; error: NormalizedError };

export interface TransferSuccess {
  incomingId: number;
  found: number;
  summary: string;
}

/** Validate + normalise what we are about to send. Returns null if there is nothing worth sending. */
export function buildPayload(text: string, title: string): TransferPayload | null {
  const metin = truncateUtf8(sanitizeText(text).trim(), MAX_TRANSFER_BYTES);
  if (metin.length < MIN_TRANSFER_CHARS) return null;
  const baslik = sanitizeText(title).replace(/\s+/g, ' ').trim().slice(0, MAX_TITLE_CHARS) || 'Medula Optik';
  return { metin, baslik };
}

function err(code: NormalizedError['code'], message: string, retryable: boolean): { ok: false; error: NormalizedError } {
  return { ok: false, error: { code, message, retryable } };
}

async function readJson<T>(res: Response): Promise<T | null> {
  const ct = res.headers.get('content-type') ?? '';
  if (!ct.includes('application/json')) return null;
  try {
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

function mapServerError(status: number, body: { kod?: string; hata?: string } | null): { ok: false; error: NormalizedError } {
  const kod = body?.kod ?? '';
  if (status === 401 || kod === 'oturum_yok' || kod === 'magaza_oturumu_yok') return err('session-expired', MSG.oturumDoldu, false);
  if (kod === 'destek_modu') return err('support-mode', MSG.destekModu, false);
  if (kod === 'pro_gerekli') return err('pro-required', MSG.proGerekli, false);
  if (status === 409 || kod === 'hesap_degisti') return err('account-changed', MSG.hesapDegisti, true);
  if (status === 429) return err('rate-limited', MSG.cokFazla, true);
  if (kod === 'magaza_kapali') return err('store-closed', body?.hata || MSG.magazaKapali, false);
  if (status >= 500) return err('server-error', MSG.aktarimBasarisiz, true);
  return err('bad-response', body?.hata ? `${MSG.aktarimBasarisiz} (${body.hata})` : MSG.aktarimBasarisiz, true);
}

/** Server feature map → only boolean entries with safe keys. Older servers: none. */
export function parseFeatures(v: unknown): Record<string, boolean> {
  const out: Record<string, boolean> = {};
  if (!v || typeof v !== 'object' || Array.isArray(v)) return out;
  for (const [k, x] of Object.entries(v as Record<string, unknown>)) {
    if (/^[a-z_]{2,32}$/.test(k) && typeof x === 'boolean') out[k] = x;
  }
  return out;
}

export interface ListCheckResult {
  total: number;
  missing: number;
  known: number;
  target: string;
}

const str = (v: unknown, max: number): string => (typeof v === 'string' ? v.slice(0, max) : '');

/** Validate the offline snapshot shape (it is rendered in a local window). */
export function parseSnapshot(v: unknown): OfflineSnapshot | null {
  if (!v || typeof v !== 'object') return null;
  const b = v as Record<string, unknown>;
  const m = b.magaza as Record<string, unknown> | undefined;
  const k = b.kullanici as Record<string, unknown> | undefined;
  if (!m || !k || typeof m.id !== 'number' || typeof k.id !== 'number' || !Array.isArray(b.siparisler)) return null;
  return {
    olusturma: str(b.olusturma, 40),
    magaza: { id: m.id, isim: str(m.isim, 120) },
    kullanici: { id: k.id, ad: str(k.ad, 120) },
    magaza_telefon: str(b.magaza_telefon, 40),
    siparisler: (b.siparisler as unknown[]).slice(0, 500).map((x) => {
      const o = (x ?? {}) as Record<string, unknown>;
      return {
        no: str(o.no, 16), ad: str(o.ad, 120), tel: str(o.tel, 30), asama: str(o.asama, 40), soz: str(o.soz, 20),
        tarih: str(o.tarih, 20), cam: str(o.cam, 80), cerceve: str(o.cerceve, 80), kalan: str(o.kalan, 30),
      };
    }),
  };
}

export class OptiflowApi {
  constructor(
    private readonly cfg: DesktopConfig,
    private readonly fetchImpl: FetchLike,
    private readonly timeoutMs = HTTP_TIMEOUT_MS,
  ) {}

  private async request(url: string, init: RequestInit): Promise<Response> {
    const ac = new AbortController();
    const t = setTimeout(() => ac.abort(), this.timeoutMs);
    try {
      return await this.fetchImpl(url, { ...init, signal: ac.signal, cache: 'no-store', credentials: 'include' });
    } finally {
      clearTimeout(t);
    }
  }

  /** Who is logged in (store + user) and a fresh CSRF token. */
  async status(): Promise<Result<{ account: AccountInfo; csrf: string }>> {
    let res: Response;
    try {
      res = await this.request(optiflowEndpoint(this.cfg, 'masaustu.php', { action: 'durum' }), {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-OptiFlow-Desktop': '1' },
      });
    } catch (e) {
      return { ok: false, error: normalizeNetError(e) };
    }
    if (res.redirected) return err('session-expired', MSG.oturumDoldu, false);
    const body = await readJson<ServerStatusResponse>(res);
    if (!res.ok || !body || body.ok !== true) return mapServerError(res.status, body);
    const k = body.kullanici;
    const m = body.magaza;
    if (!k || !m || typeof body.csrf !== 'string' || !/^[a-f0-9]{16,128}$/.test(body.csrf)) {
      return err('bad-response', MSG.aktarimBasarisiz, true);
    }
    return {
      ok: true,
      value: {
        account: {
          storeId: m.id,
          storeName: m.isim,
          userId: k.id,
          userName: k.ad,
          supportMode: body.destek_modu === true,
          package: body.paket === 'pro' ? 'pro' : 'lite',
          // Older servers (4.10.0) have no package field: they had no Pro gating, so allow.
          transferAllowed: body.pro === undefined ? true : body.pro.sgk_kopru === true,
          features: parseFeatures(body.ozellikler),
        },
        csrf: body.csrf,
      },
    };
  }

  /**
   * Send one captured prescription. `expected` binds the transfer to the account the user
   * saw; the server rejects it (409) if the session now belongs to a different store/user.
   */
  async transfer(payload: TransferPayload, csrf: string, expected: AccountInfo, clientVersion: string): Promise<Result<TransferSuccess>> {
    let res: Response;
    try {
      res = await this.request(optiflowEndpoint(this.cfg, 'masaustu.php', { action: 'aktar' }), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf,
          'X-OptiFlow-Desktop': '1',
        },
        body: JSON.stringify({
          metin: payload.metin,
          baslik: payload.baslik,
          beklenen: { magaza_id: expected.storeId, kullanici_id: expected.userId },
          istemci: { surum: clientVersion },
        }),
      });
    } catch (e) {
      return { ok: false, error: normalizeNetError(e) };
    }
    if (res.redirected) return err('session-expired', MSG.oturumDoldu, false);
    const body = await readJson<ServerTransferResponse>(res);
    if (res.status === 419) return err('account-changed', MSG.hesapDegisti, true); // CSRF rotated: status → retry
    if (!res.ok || !body || body.ok !== true) return mapServerError(res.status, body);
    const id = Number(body.gelen_id);
    if (!Number.isSafeInteger(id) || id <= 0) return err('bad-response', MSG.aktarimBasarisiz, true);
    return {
      ok: true,
      value: {
        incomingId: id,
        found: Number(body.bulunan) || 0,
        summary: typeof body.ozet === 'string' ? body.ozet.slice(0, 200) : '',
      },
    };
  }

  /** 4.12.0 — compare e-prescription numbers read from the Medula list with OptiFlow. */
  async checkList(numaralar: string[], csrf: string, expected: AccountInfo): Promise<Result<ListCheckResult>> {
    let res: Response;
    try {
      res = await this.request(optiflowEndpoint(this.cfg, 'masaustu.php', { action: 'recete_kontrol' }), {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-OptiFlow-Desktop': '1' },
        body: JSON.stringify({ numaralar, beklenen: { magaza_id: expected.storeId, kullanici_id: expected.userId } }),
      });
    } catch (e) {
      return { ok: false, error: normalizeNetError(e) };
    }
    if (res.redirected) return err('session-expired', MSG.oturumDoldu, false);
    const body = await readJson<{ ok?: boolean; toplam?: number; yok?: number; var?: number; hedef?: string; kod?: string; hata?: string }>(res);
    if (res.status === 419) return err('account-changed', MSG.hesapDegisti, true);
    if (!res.ok || !body || body.ok !== true) return mapServerError(res.status, body);
    const target = body.hedef === 'sgk-mutabakat.php?kontrol=1' ? body.hedef : 'sgk-mutabakat.php?kontrol=1';
    return { ok: true, value: { total: Number(body.toplam) || 0, missing: Number(body.yok) || 0, known: Number(body.var) || 0, target } };
  }

  /** 4.12.0 — read-only copy for offline use. */
  async snapshot(): Promise<Result<OfflineSnapshot>> {
    let res: Response;
    try {
      res = await this.request(optiflowEndpoint(this.cfg, 'masaustu.php', { action: 'ozet' }), {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-OptiFlow-Desktop': '1' },
      });
    } catch (e) {
      return { ok: false, error: normalizeNetError(e) };
    }
    if (res.redirected) return err('session-expired', MSG.oturumDoldu, false);
    const body = await readJson<Record<string, unknown>>(res);
    if (!res.ok || !body || body.ok !== true) return mapServerError(res.status, body as { kod?: string; hata?: string } | null);
    const snap = parseSnapshot(body);
    return snap ? { ok: true, value: snap } : err('bad-response', MSG.aktarimBasarisiz, true);
  }
}

/**
 * CENTRAL ORIGIN / URL POLICY. Every URL decision in the app goes through here
 * (spec §12, §19, §53 "no duplicated URL checks"). Pure functions: unit-tested.
 *
 * SECURITY: exact host matching only. No suffix/wildcard matching, no regex on
 * raw strings, no "startsWith" on full URLs (https://gss.sgk.gov.tr.evil.com).
 */
import type { DesktopConfig } from '../../shared/types';

export type OptiflowDecision = 'allow' | 'route-medula' | 'external' | 'block';
export type MedulaDecision = 'allow' | 'upgrade' | 'external' | 'block';

export function parseUrl(raw: string | undefined | null): URL | null {
  if (typeof raw !== 'string' || raw.length === 0 || raw.length > 8192) return null;
  try {
    return new URL(raw);
  } catch {
    return null;
  }
}

function basePathOf(cfg: DesktopConfig): { origin: string; path: string } {
  const u = new URL(cfg.optiflowBaseUrl);
  const path = u.pathname.endsWith('/') ? u.pathname : `${u.pathname}/`;
  return { origin: u.origin, path };
}

function isLocalHttp(u: URL): boolean {
  return u.protocol === 'http:' && (u.hostname === 'localhost' || u.hostname === '127.0.0.1');
}

/** True when the URL belongs to the configured OptiFlow deployment (origin + base path). */
export function isOptiflowUrl(raw: string | undefined | null, cfg: DesktopConfig): boolean {
  const u = parseUrl(raw);
  if (!u) return false;
  if (u.username || u.password) return false;
  const base = basePathOf(cfg);
  if (u.origin !== base.origin) return false;
  if (u.protocol !== 'https:' && !(cfg.allowInsecureLocalhost && isLocalHttp(u))) return false;
  return u.pathname === base.path.slice(0, -1) || u.pathname.startsWith(base.path);
}

function hostIn(u: URL, hosts: readonly string[]): boolean {
  const h = u.hostname.toLowerCase();
  return hosts.some((x) => x.toLowerCase() === h);
}

/** True for https URLs on an allowed Medula host (default port only). */
export function isMedulaUrl(raw: string | undefined | null, cfg: DesktopConfig): boolean {
  const u = parseUrl(raw);
  if (!u || u.protocol !== 'https:' || u.port !== '' || u.username || u.password) return false;
  return hostIn(u, cfg.medulaAllowedHosts);
}

/**
 * SGK's own servers sometimes redirect to plain http on the SAME allowed host (e.g. after
 * the Optik Medula login: 302 → http://gss.sgk.gov.tr/Optik_Firma2_Web/index.faces).
 * Such a URL is never loaded as http; it is rewritten to the https equivalent.
 * Returns the https URL, or null when the URL is not an upgradeable Medula address.
 */
export function medulaHttpsUpgrade(raw: string | undefined | null, cfg: DesktopConfig): string | null {
  const u = parseUrl(raw);
  if (!u || u.protocol !== 'http:' || u.username || u.password) return null;
  if (u.port !== '' && u.port !== '443') return null;
  if (!hostIn(u, cfg.medulaAllowedHosts)) return null;
  const https = new URL(u.toString());
  https.protocol = 'https:';
  https.port = '';
  return isMedulaUrl(https.toString(), cfg) ? https.toString() : null;
}

/** True when a frame at this URL may be READ by the extractor. Stricter than isMedulaUrl. */
export function isMedulaExtractUrl(raw: string | undefined | null, cfg: DesktopConfig): boolean {
  if (!isMedulaUrl(raw, cfg)) return false;
  return hostIn(parseUrl(raw)!, cfg.medulaExtractHosts);
}

/** Origin string check for WebFrameMain.origin / IPC sender frames. */
export function isMedulaExtractOrigin(origin: string | undefined | null, cfg: DesktopConfig): boolean {
  if (!origin) return false;
  const u = parseUrl(`${origin}/`);
  return !!u && u.origin === origin && isMedulaExtractUrl(`${origin}/`, cfg);
}

export function isOptiflowOrigin(origin: string | undefined | null, cfg: DesktopConfig): boolean {
  if (!origin) return false;
  return origin === new URL(cfg.optiflowBaseUrl).origin;
}

/** Schemes we hand to the OS (default browser / mail / phone). Everything else is dropped. */
export function isSafeExternal(raw: string | undefined | null): boolean {
  const u = parseUrl(raw);
  if (!u) return false;
  if (u.protocol === 'https:' || u.protocol === 'http:') return !u.username && !u.password && u.hostname.length > 0;
  if (u.protocol === 'mailto:' || u.protocol === 'tel:') return true;
  return false; // file:, javascript:, data:, smb:, ms-*:, custom protocols …
}

/** Navigation / window.open decision for the OptiFlow view and its same-origin popups. */
export function decideForOptiflow(raw: string | undefined | null, cfg: DesktopConfig): OptiflowDecision {
  if (isOptiflowUrl(raw, cfg)) return 'allow';
  if (isMedulaUrl(raw, cfg)) return 'route-medula';
  if (isSafeExternal(raw)) return 'external';
  return 'block';
}

/** Navigation / window.open decision for the Medula view. */
export function decideForMedula(raw: string | undefined | null, cfg: DesktopConfig): MedulaDecision {
  if (raw === 'about:blank') return 'allow';
  if (isMedulaUrl(raw, cfg)) return 'allow';
  if (medulaHttpsUpgrade(raw, cfg)) return 'upgrade';
  if (isSafeExternal(raw)) return 'external';
  return 'block';
}

/** Target URL for "open this incoming prescription" – built here, never taken from the server verbatim. */
export function incomingUrl(cfg: DesktopConfig, incomingId: number): string | null {
  if (!Number.isSafeInteger(incomingId) || incomingId <= 0) return null;
  const base = basePathOf(cfg);
  return `${base.origin}${base.path}sgk-aktar.php?gelen=${incomingId}`;
}

export function optiflowEndpoint(cfg: DesktopConfig, file: string, query: Record<string, string>): string {
  if (!/^[a-z0-9-]+\.php$/.test(file)) throw new Error('invalid endpoint file');
  const base = basePathOf(cfg);
  const qs = new URLSearchParams(query).toString();
  return `${base.origin}${base.path}${file}${qs ? `?${qs}` : ''}`;
}

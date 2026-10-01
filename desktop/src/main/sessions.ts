/**
 * Session partitions (spec §11).
 *   persist:optiflow – OptiFlow cookies (PHP session "optiflow"), cache, localStorage.
 *   persist:medula   – SGK cookies only. Never readable from any renderer; never sent to OptiFlow.
 * Cookies stay inside Chromium's cookie store; the app never reads or copies them.
 */
import { app, session, type Session } from 'electron';
import { DESKTOP_UA_TOKEN, PARTITION_MEDULA, PARTITION_OPTIFLOW } from '../shared/constants';
import { log } from './logging';

const OPTIFLOW_PERMISSIONS = new Set(['clipboard-sanitized-write', 'notifications', 'fullscreen']);
const MEDULA_PERMISSIONS = new Set(['clipboard-sanitized-write']);

/** Chromium UA without "Electron/x" and "OptiFlow/x" tokens (better site compatibility, no fingerprint leak). */
export function plainChromeUA(ua: string): string {
  return ua
    .replace(/\s?Electron\/\S+/g, '')
    .replace(/\s?OptiFlow\/\S+/gi, '')
    .replace(/\s?optiflow-desktop\/\S+/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim();
}

export function optiflowUA(ua: string, version: string): string {
  return `${plainChromeUA(ua)} ${DESKTOP_UA_TOKEN}/${version}`;
}

function lockDown(ses: Session, allowed: Set<string>, name: string): void {
  ses.setPermissionRequestHandler((_wc, permission, callback) => {
    const ok = allowed.has(permission);
    if (!ok) log.info('permission.denied', { session: name, permission });
    callback(ok);
  });
  ses.setPermissionCheckHandler((_wc, permission) => allowed.has(permission));
  ses.setDevicePermissionHandler(() => false); // WebHID / WebUSB / serial: never
  ses.setDisplayMediaRequestHandler((_req, cb) => cb({})); // screen capture: never
}

export interface Sessions {
  optiflow: Session;
  medula: Session;
}

/**
 * Medula (JSF) keeps its login state in SESSION cookies (JSESSIONID…) and serves cacheable
 * login pages. In Chrome, "close the browser" drops session cookies and "hard refresh" bypasses
 * the cache — that is what SGK's "tarayıcıyı kapatın" error asks for. Electron, however,
 * PERSISTS session cookies in a persist: partition, so an expired Medula session survived app
 * restarts and the login kept failing (field report 28.09.2026).
 *
 * This removes only session cookies (no expiry date) and the HTTP cache: exactly what a browser
 * restart + hard refresh does. Long-lived cookies stay.
 */
export async function restartMedulaBrowser(ses: Session): Promise<number> {
  const cookies = await ses.cookies.get({});
  let removed = 0;
  for (const c of cookies) {
    if (c.session || c.expirationDate === undefined) {
      const host = (c.domain ?? '').replace(/^\./, '');
      const url = `${c.secure ? 'https' : 'http'}://${host}${c.path ?? '/'}`;
      try {
        await ses.cookies.remove(url, c.name);
        removed++;
      } catch {
        /* ignore a single cookie that cannot be removed */
      }
    }
  }
  await ses.cookies.flushStore();
  await ses.clearCache();
  log.info('medula.browser.restart', { sessionCookiesRemoved: removed });
  return removed;
}

/** Adds Chrome's hard-refresh request headers to every request matching `urls`. */
export function applyHardRefreshHeaders(ses: Session, urls: string[]): void {
  ses.webRequest.onBeforeSendHeaders({ urls }, (details, callback) => {
    callback({ requestHeaders: { ...details.requestHeaders, 'Cache-Control': 'no-cache', Pragma: 'no-cache' } });
  });
}

/**
 * Plain-http requests to an allowed SGK host never leave the machine as http: they are
 * redirected to the same address over https (SGK's login answers with an http redirect).
 */
export function upgradeMedulaToHttps(ses: Session, hosts: string[]): void {
  if (hosts.length === 0) return;
  ses.webRequest.onBeforeRequest({ urls: hosts.map((h) => `http://${h}/*`) }, (details, callback) => {
    try {
      const u = new URL(details.url);
      if (u.protocol === 'http:' && hosts.includes(u.hostname.toLowerCase()) && !u.username && !u.password) {
        u.protocol = 'https:';
        u.port = '';
        callback({ redirectURL: u.toString() });
        return;
      }
    } catch {
      /* fall through */
    }
    callback({});
  });
}

export function setupSessions(version: string, optiflowOrigin: string, medulaHosts: string[] = []): Sessions {
  const optiflow = session.fromPartition(PARTITION_OPTIFLOW);
  const medula = session.fromPartition(PARTITION_MEDULA);

  // The desktop marker goes ONLY to the OptiFlow server (UX hint for PHP; never an auth signal).
  // session/webContents.setUserAgent alone is NOT reliable: tests/e2e showed later navigations,
  // popups and redirects reaching PHP with the plain UA. So the header is set on EVERY request
  // to the OptiFlow origin at the network layer; navigator.userAgent is still set for page JS.
  const desktopUA = optiflowUA(optiflow.getUserAgent(), version);
  optiflow.setUserAgent(desktopUA);
  optiflow.webRequest.onBeforeSendHeaders({ urls: [`${optiflowOrigin}/*`] }, (details, callback) => {
    callback({ requestHeaders: { ...details.requestHeaders, 'User-Agent': desktopUA } });
  });
  medula.setUserAgent(plainChromeUA(medula.getUserAgent()));

  // Every Medula request behaves like Chrome's hard refresh (Ctrl+F5): the server always sends a
  // fresh page / security image that matches the current session instead of a stale cached copy.
  if (medulaHosts.length > 0) applyHardRefreshHeaders(medula, medulaHosts.map((h) => `https://${h}/*`));
  upgradeMedulaToHttps(medula, medulaHosts.map((h) => h.toLowerCase()));

  // Service workers do not inherit per-session/per-webContents UAs, so a web-app SW would
  // hide the desktop marker from PHP. The desktop does not use the PWA SW (assets/pwa.js skips
  // registration in desktop mode); remove any registered earlier, and make the app-wide
  // fallback UA a plain Chrome UA (no "Electron/…" fingerprint anywhere).
  app.userAgentFallback = plainChromeUA(app.userAgentFallback);
  void optiflow.clearStorageData({ storages: ['serviceworkers'] });

  lockDown(optiflow, OPTIFLOW_PERMISSIONS, 'optiflow');
  lockDown(medula, MEDULA_PERMISSIONS, 'medula');
  medula.setSpellCheckerEnabled(false);

  return { optiflow, medula };
}

/** "Medula oturumunu temizle": cookies, storage, cache, HTTP auth. Irreversible (user confirms first). */
export async function clearMedulaSession(ses: Session): Promise<void> {
  await ses.clearStorageData();
  await ses.clearCache();
  await ses.clearAuthCache();
  log.info('medula.session.cleared');
}

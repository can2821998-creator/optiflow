/**
 * Navigation, redirect and popup rules for every webContents (spec §19).
 * All URL decisions come from bridge/origin-policy.ts.
 */
import { app, shell, type WebContents } from 'electron';
import { redactUrl } from '../shared/redact';
import type { DesktopConfig } from '../shared/types';
import { decideForMedula, decideForOptiflow, isOptiflowUrl, isSafeExternal, medulaHttpsUpgrade } from './bridge/origin-policy';
import { log } from './logging';
import { popupPrefs } from './security';

export interface NavDeps {
  cfg: DesktopConfig;
  /** Open an SGK URL inside the Medula view (from an OptiFlow link). */
  routeToMedula: (url: string) => void;
  /** Tell the user something was blocked (toolbar notice). */
  onBlocked: (url: string, where: 'optiflow' | 'medula') => void;
  iconPath?: string;
}

export function openExternalSafe(url: string): void {
  if (!isSafeExternal(url)) {
    log.warn('external.refused', { url: redactUrl(url) });
    return;
  }
  void shell.openExternal(url);
  log.info('external.opened', { url: redactUrl(url) });
}

/** Defaults for ANY webContents, including ones we did not create explicitly. */
export function installGlobalGuards(): void {
  app.on('web-contents-created', (_e, wc) => {
    wc.on('will-attach-webview', (ev) => ev.preventDefault()); // <webview> is never allowed
    wc.setWindowOpenHandler(() => ({ action: 'deny' })); // overridden per view below
  });
}

function isBenignAbout(url: string): boolean {
  return url === 'about:blank' || url === 'about:srcdoc';
}

function childWindowOptions(deps: NavDeps) {
  return {
    width: 1024,
    height: 820,
    autoHideMenuBar: true,
    ...(deps.iconPath ? { icon: deps.iconPath } : {}),
    webPreferences: popupPrefs(),
  };
}

/** OptiFlow main view AND its same-origin popups. */
export function attachOptiflowGuards(wc: WebContents, deps: NavDeps): void {
  const { cfg } = deps;

  const onTopLevel = (ev: { preventDefault: () => void }, url: string, isRedirect: boolean) => {
    const d = decideForOptiflow(url, cfg);
    if (d === 'allow') return;
    ev.preventDefault();
    if (d === 'route-medula') deps.routeToMedula(url);
    else if (d === 'external' && !isRedirect) openExternalSafe(url);
    else {
      log.warn('optiflow.navigation.blocked', { url: redactUrl(url), redirect: isRedirect });
      deps.onBlocked(url, 'optiflow');
    }
  };
  wc.on('will-navigate', (ev) => onTopLevel(ev, ev.url, false));
  wc.on('will-redirect', (ev) => {
    if (ev.isMainFrame) onTopLevel(ev, ev.url, true);
  });
  wc.on('will-frame-navigate', (ev) => {
    if (ev.isMainFrame) return;
    if (isOptiflowUrl(ev.url, cfg) || isBenignAbout(ev.url)) return;
    ev.preventDefault();
    log.warn('optiflow.subframe.blocked', { url: redactUrl(ev.url) });
  });

  wc.setWindowOpenHandler(({ url }) => {
    const d = decideForOptiflow(url, cfg);
    // print.php, ekran.php, order.php in a new window …: same session, no privileges.
    if (d === 'allow') return { action: 'allow', overrideBrowserWindowOptions: childWindowOptions(deps) };
    if (d === 'route-medula') deps.routeToMedula(url);
    else if (d === 'external') openExternalSafe(url);
    else log.warn('optiflow.popup.blocked', { url: redactUrl(url) });
    return { action: 'deny' };
  });
  wc.on('did-create-window', (child) => {
    child.webContents.setUserAgent(wc.getUserAgent());
    attachOptiflowGuards(child.webContents, deps);
  });
}

/** Medula view AND its popups. Third-party content: strictest rules. */
export function attachMedulaGuards(wc: WebContents, deps: NavDeps): void {
  const { cfg } = deps;
  const onTopLevel = (ev: { preventDefault: () => void }, url: string, isRedirect: boolean) => {
    const d = decideForMedula(url, cfg);
    if (d === 'allow') return;
    if (d === 'upgrade') {
      // Same SGK host over plain http (SGK's login redirects this way). The navigation continues
      // (so the redirect's session cookie is kept) but the Medula session rewrites the request to
      // https before anything is sent (sessions.ts → upgradeMedulaToHttps). Never loaded as http.
      log.info('medula.navigation.upgraded', { url: redactUrl(url), redirect: isRedirect });
      return;
    }
    ev.preventDefault();
    log.warn('medula.navigation.blocked', { url: redactUrl(url), redirect: isRedirect });
    deps.onBlocked(url, 'medula');
  };
  wc.on('will-navigate', (ev) => onTopLevel(ev, ev.url, false));
  wc.on('will-redirect', (ev) => {
    if (ev.isMainFrame) onTopLevel(ev, ev.url, true);
  });
  wc.on('will-frame-navigate', (ev) => {
    if (ev.isMainFrame) return;
    // Sub-frames: https only (compatibility with embedded SGK widgets). Reading is still
    // restricted to extract-hosts by the controller, so other frames are never read.
    let ok = isBenignAbout(ev.url);
    try {
      ok = ok || new URL(ev.url).protocol === 'https:';
    } catch {
      /* invalid */
    }
    if (!ok) {
      ev.preventDefault();
      log.warn('medula.subframe.blocked', { url: redactUrl(ev.url) });
    }
  });
  wc.setWindowOpenHandler(({ url }) => {
    // SGK print / report popups stay in the Medula session, without the extractor preload arguments.
    const d = decideForMedula(url, cfg);
    if (d === 'allow') return { action: 'allow', overrideBrowserWindowOptions: childWindowOptions(deps) };
    // window.open('http://<SGK host>/…'): the Medula session rewrites it to https at the
    // network layer (sessions.ts → upgradeMedulaToHttps) before anything is sent.
    if (d === 'upgrade') return { action: 'allow', overrideBrowserWindowOptions: childWindowOptions(deps) };
    // Links to other sites from inside Medula are NOT opened automatically; the user is told.
    log.warn('medula.popup.blocked', { url: redactUrl(url) });
    deps.onBlocked(url, 'medula');
    return { action: 'deny' };
  });
  wc.on('did-create-window', (child) => {
    child.webContents.setUserAgent(wc.getUserAgent());
    attachMedulaGuards(child.webContents, deps);
  });
}

/** The local toolbar must never navigate anywhere. */
export function attachShellGuards(wc: WebContents): void {
  wc.on('will-navigate', (ev) => ev.preventDefault());
  wc.on('will-redirect', (ev) => ev.preventDefault());
  wc.setWindowOpenHandler(() => ({ action: 'deny' }));
}

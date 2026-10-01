/**
 * webPreferences factories + app-wide hardening (spec §12–13).
 * ALL renderers are sandboxed, context-isolated, without Node, without <webview>.
 * tests/security/static-security.test.ts asserts these values.
 */
import { app, type WebPreferences } from 'electron';
import path from 'node:path';
import { PARTITION_MEDULA, PARTITION_OPTIFLOW } from '../shared/constants';
import type { DesktopConfig } from '../shared/types';

const BASE: WebPreferences = Object.freeze({
  nodeIntegration: false,
  nodeIntegrationInWorker: false,
  contextIsolation: true,
  sandbox: true,
  webSecurity: true,
  allowRunningInsecureContent: false,
  webviewTag: false,
  experimentalFeatures: false,
  navigateOnDragDrop: false,
  safeDialogs: true,
});

export function preloadPath(name: 'shell-preload' | 'optiflow-preload' | 'medula-preload' | 'cevrimdisi-preload', distDir: string): string {
  return path.join(distDir, 'preload', `${name}.js`);
}

/** The local toolbar UI (file:// inside the app package). */
export function shellPrefs(distDir: string): WebPreferences {
  return { ...BASE, preload: preloadPath('shell-preload', distDir), spellcheck: false, devTools: !app.isPackaged };
}

/** Remote OptiFlow web app. Preload exposes a NARROW API, and only on the configured origin. */
export function optiflowPrefs(distDir: string, cfg: DesktopConfig, version: string): WebPreferences {
  return {
    ...BASE,
    partition: PARTITION_OPTIFLOW,
    preload: preloadPath('optiflow-preload', distDir),
    // The preload reads these to know which origin is trusted (it runs before any page script).
    additionalArguments: [`--optiflow-origin=${new URL(cfg.optiflowBaseUrl).origin}`, `--optiflow-desktop-version=${version}`],
    devTools: !app.isPackaged,
  };
}

/**
 * Remote Medula (third-party) content.
 * The preload runs in an ISOLATED WORLD in every frame (nodeIntegrationInSubFrames is required for
 * that; with sandbox:true it grants NO Node.js – the flag only controls where preloads load).
 * It exposes NOTHING to the page (no contextBridge) and only answers requests from the main process.
 */
export function medulaPrefs(distDir: string, cfg: DesktopConfig): WebPreferences {
  return {
    ...BASE,
    partition: PARTITION_MEDULA,
    preload: preloadPath('medula-preload', distDir),
    nodeIntegrationInSubFrames: true,
    additionalArguments: [`--medula-extract-hosts=${cfg.medulaExtractHosts.join(',')}`],
    spellcheck: false,
    devTools: !app.isPackaged,
  };
}

/**
 * Child windows (print pages, Medula popups). They share the opener's session (same partition,
 * so cookies work) but receive NO privileges:
 *  - additionalArguments is replaced with a marker WITHOUT the trusted origin / extract hosts,
 *    so an inherited preload exposes nothing and answers nothing;
 *  - main-process IPC handlers are scoped to the two main views' webContents, so a popup's
 *    messages are never delivered to them anyway.
 */
/** 4.12.0 — local offline-copy window: its own narrow preload, no network access needed. */
export function offlinePrefs(distDir: string): WebPreferences {
  return { ...BASE, preload: preloadPath('cevrimdisi-preload', distDir), spellcheck: false, devTools: !app.isPackaged };
}

export function popupPrefs(): WebPreferences {
  return { ...BASE, additionalArguments: ['--optiflow-popup=1'], spellcheck: false, devTools: !app.isPackaged };
}

/** App-wide switches that must be set before 'ready'. */
export function hardenAppEarly(): void {
  app.enableSandbox(); // force sandbox for every renderer, even ones we forgot
  // Remote debugging must never be reachable in production.
  if (app.isPackaged) app.commandLine.removeSwitch('remote-debugging-port');
}

/**
 * Security regression tests (spec §45 "Security tests").
 * Runtime checks run in tests/e2e; these assert the configuration and scan the source
 * for patterns that must never appear.
 */
import fs from 'node:fs';
import path from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import type { DesktopConfig } from '../../src/shared/types';

vi.mock('electron', () => ({ app: { isPackaged: true } }));
const { medulaPrefs, optiflowPrefs, popupPrefs, shellPrefs } = await import('../../src/main/security');
const { plainChromeUA, optiflowUA } = await import('../../src/main/sessions');

const cfg: DesktopConfig = {
  appEnv: 'production',
  optiflowBaseUrl: 'https://optiflow.com.tr',
  medulaHomeUrl: 'https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces',
  medulaAllowedHosts: ['gss.sgk.gov.tr'],
  medulaExtractHosts: ['gss.sgk.gov.tr'],
  updateUrl: '',
  updateChannel: 'latest',
  logLevel: 'info',
  allowInsecureLocalhost: false,
};

describe('webPreferences', () => {
  const all = {
    shell: shellPrefs('/d'),
    optiflow: optiflowPrefs('/d', cfg, '5.0.0'),
    medula: medulaPrefs('/d', cfg),
    popup: popupPrefs(),
  };
  it.each(Object.entries(all))('%s: no Node, isolated, sandboxed, web security on, no webview', (_n, p) => {
    expect(p.nodeIntegration).toBe(false);
    expect(p.nodeIntegrationInWorker).toBe(false);
    expect(p.contextIsolation).toBe(true);
    expect(p.sandbox).toBe(true);
    expect(p.webSecurity).toBe(true);
    expect(p.allowRunningInsecureContent).toBe(false);
    expect(p.webviewTag).toBe(false);
    expect(p.devTools).toBe(false); // packaged
  });
  it('separate partitions for OptiFlow and Medula', () => {
    expect(all.optiflow.partition).toBe('persist:optiflow');
    expect(all.medula.partition).toBe('persist:medula');
  });
  it('popups carry neither the trusted origin nor the extract hosts', () => {
    expect(JSON.stringify(all.popup.additionalArguments)).not.toMatch(/optiflow-origin|medula-extract-hosts/);
    expect(all.popup.preload).toBeUndefined();
  });
  it('desktop UA marker only for OptiFlow; Medula gets a plain Chrome UA', () => {
    const ua = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) OptiFlow/5.0.0 Chrome/140.0.0.0 Electron/44.4.5 Safari/537.36';
    expect(plainChromeUA(ua)).not.toMatch(/Electron|OptiFlow/);
    expect(optiflowUA(ua, '5.0.0')).toMatch(/OptiFlowDesktop\/5\.0\.0$/);
  });
});

function walk(dir: string): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]));
}
const SRC = path.resolve(__dirname, '../../src');
const files = walk(SRC).filter((f) => f.endsWith('.ts'));
const code = (f: string) => fs.readFileSync(f, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');

describe('source scan', () => {
  it('never enables nodeIntegration / disables isolation / sandbox / webSecurity', () => {
    for (const f of files) {
      const c = code(f);
      expect(c, f).not.toMatch(/nodeIntegration\s*:\s*true/);
      expect(c, f).not.toMatch(/contextIsolation\s*:\s*false/);
      expect(c, f).not.toMatch(/sandbox\s*:\s*false/);
      expect(c, f).not.toMatch(/webSecurity\s*:\s*false/);
      expect(c, f).not.toMatch(/allowRunningInsecureContent\s*:\s*true/);
    }
  });
  it('no eval / new Function / remote module / deprecated BrowserView', () => {
    for (const f of files) expect(code(f), f).not.toMatch(/\beval\s*\(|new Function\s*\(|@electron\/remote|\bBrowserView\b/);
  });
  it('preloads never expose ipcRenderer or a generic invoke/send', () => {
    for (const f of files.filter((x) => x.includes(`${path.sep}preload${path.sep}`))) {
      const c = code(f);
      expect(c, f).not.toMatch(/exposeInMainWorld\([^)]*ipcRenderer\b\s*\)/);
      expect(c, f).not.toMatch(/invoke\s*:\s*\(\s*channel/);
      expect(c, f).not.toMatch(/ipcRenderer\.(invoke|send)\(\s*(channel|ch|c)\b/);
    }
  });
  it('the Medula preload exposes nothing to the page', () => {
    const c = code(path.join(SRC, 'preload/medula-preload.ts'));
    expect(c).not.toMatch(/contextBridge|exposeInMainWorld|window\.[a-zA-Z_]+\s*=/);
  });
  it('no global ipcMain listeners (all IPC is scoped to a specific webContents)', () => {
    for (const f of files) expect(code(f), f).not.toMatch(/ipcMain\.(on|handle)\(/);
  });
  it('certificate errors are never accepted', () => {
    const c = code(path.join(SRC, 'main/main.ts'));
    expect(c).toMatch(/callback\(false\)/);
    expect(c).not.toMatch(/callback\(true\)/);
  });
  it('no secrets or DB credentials in config files', () => {
    const dir = path.resolve(__dirname, '../../config');
    for (const f of fs.readdirSync(dir)) {
      expect(fs.readFileSync(path.join(dir, f), 'utf8'), f).not.toMatch(/password|sifre|secret|token|mysql/i);
    }
  });
});

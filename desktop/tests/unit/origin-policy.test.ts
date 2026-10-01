import { describe, expect, it } from 'vitest';
import {
  decideForMedula,
  decideForOptiflow,
  incomingUrl,
  isMedulaExtractOrigin,
  isMedulaExtractUrl,
  isMedulaUrl,
  isOptiflowOrigin,
  isOptiflowUrl,
  isSafeExternal,
  medulaHttpsUpgrade,
  optiflowEndpoint,
} from '../../src/main/bridge/origin-policy';
import { validateConfig } from '../../src/main/config';
import type { DesktopConfig } from '../../src/shared/types';

const cfg: DesktopConfig = validateConfig({
  appEnv: 'production',
  optiflowBaseUrl: 'https://optiflow.com.tr',
  medulaHomeUrl: 'https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces',
  medulaAllowedHosts: ['gss.sgk.gov.tr'],
  medulaExtractHosts: ['gss.sgk.gov.tr'],
  updateUrl: '',
  updateChannel: 'latest',
  logLevel: 'info',
  allowInsecureLocalhost: true, // must be forced off in production
});

describe('origin policy', () => {
  it('production config forces allowInsecureLocalhost off', () => {
    expect(cfg.allowInsecureLocalhost).toBe(false);
  });
  it('OptiFlow: exact origin, https only', () => {
    expect(isOptiflowUrl('https://optiflow.com.tr/sgk-aktar.php?gelen=3', cfg)).toBe(true);
    expect(isOptiflowUrl('http://optiflow.com.tr/', cfg)).toBe(false);
    expect(isOptiflowUrl('https://optiflow.com.tr.evil.com/', cfg)).toBe(false);
    expect(isOptiflowUrl('https://evil.com/?https://optiflow.com.tr', cfg)).toBe(false);
    expect(isOptiflowUrl('https://user:pw@optiflow.com.tr/', cfg)).toBe(false);
    expect(isOptiflowUrl('https://optiflow.com.tr:8443/', cfg)).toBe(false);
    expect(isOptiflowOrigin('https://optiflow.com.tr', cfg)).toBe(true);
    expect(isOptiflowOrigin('null', cfg)).toBe(false);
  });
  it('OptiFlow in a sub-folder only matches that folder', () => {
    const sub = validateConfig({ ...cfg, optiflowBaseUrl: 'https://ornek.com/atolye' });
    expect(isOptiflowUrl('https://ornek.com/atolye/index.php', sub)).toBe(true);
    expect(isOptiflowUrl('https://ornek.com/atolye', sub)).toBe(true);
    expect(isOptiflowUrl('https://ornek.com/atolyex/index.php', sub)).toBe(false);
    expect(isOptiflowUrl('https://ornek.com/baska/index.php', sub)).toBe(false);
    expect(incomingUrl(sub, 7)).toBe('https://ornek.com/atolye/sgk-aktar.php?gelen=7');
  });
  it('Medula: exact allowed host, https, default port', () => {
    expect(isMedulaUrl('https://gss.sgk.gov.tr/Optik/ReceteDetay.aspx', cfg)).toBe(true);
    expect(isMedulaUrl('https://GSS.SGK.GOV.TR/', cfg)).toBe(true);
    expect(isMedulaUrl('http://gss.sgk.gov.tr/', cfg)).toBe(false);
    expect(isMedulaUrl('https://gss.sgk.gov.tr.attacker.net/', cfg)).toBe(false);
    expect(isMedulaUrl('https://evil-gss.sgk.gov.tr/', cfg)).toBe(false);
    expect(isMedulaUrl('https://gss.sgk.gov.tr:444/', cfg)).toBe(false);
    expect(isMedulaUrl('https://sgk.gov.tr/', cfg)).toBe(false);
    expect(isMedulaExtractUrl('https://gss.sgk.gov.tr/x', cfg)).toBe(true);
    expect(isMedulaExtractOrigin('https://gss.sgk.gov.tr', cfg)).toBe(true);
    expect(isMedulaExtractOrigin('https://gss.sgk.gov.tr:444', cfg)).toBe(false);
    expect(isMedulaExtractOrigin('null', cfg)).toBe(false);
  });
  it('external: only http(s), mailto, tel', () => {
    expect(isSafeExternal('https://wa.me/905321112233')).toBe(true);
    expect(isSafeExternal('mailto:a@b.c')).toBe(true);
    for (const bad of ['file:///C:/Windows/system32/calc.exe', 'javascript:alert(1)', 'data:text/html,x', 'smb://host/share', 'ms-msdt:/id', 'vbscript:x', 'optiflow://x']) {
      expect(isSafeExternal(bad), bad).toBe(false);
    }
  });
  it('decisions', () => {
    expect(decideForOptiflow('https://optiflow.com.tr/print.php?type=order&id=1', cfg)).toBe('allow');
    expect(decideForOptiflow('https://gss.sgk.gov.tr/', cfg)).toBe('route-medula');
    expect(decideForOptiflow('https://wa.me/1', cfg)).toBe('external');
    expect(decideForOptiflow('file:///etc/passwd', cfg)).toBe('block');
    expect(decideForMedula('https://gss.sgk.gov.tr/a', cfg)).toBe('allow');
    expect(decideForMedula('https://optiflow.com.tr/', cfg)).toBe('external');
    expect(decideForMedula('file:///c:/', cfg)).toBe('block');
  });
  it('SGK http redirect on the same host is upgraded to https, nothing else is', () => {
    const http = 'http://gss.sgk.gov.tr/Optik_Firma2_Web/index.faces';
    expect(decideForMedula(http, cfg)).toBe('upgrade');
    expect(medulaHttpsUpgrade(http, cfg)).toBe('https://gss.sgk.gov.tr/Optik_Firma2_Web/index.faces');
    expect(medulaHttpsUpgrade('http://gss.sgk.gov.tr:80/a?b=1', cfg)).toBe('https://gss.sgk.gov.tr/a?b=1');
    expect(medulaHttpsUpgrade('http://gss.sgk.gov.tr:8080/a', cfg)).toBeNull();
    expect(medulaHttpsUpgrade('http://gss.sgk.gov.tr.evil.com/a', cfg)).toBeNull();
    expect(medulaHttpsUpgrade('http://user:pw@gss.sgk.gov.tr/a', cfg)).toBeNull();
    expect(medulaHttpsUpgrade('http://evil.com/a', cfg)).toBeNull();
    expect(medulaHttpsUpgrade('https://gss.sgk.gov.tr/a', cfg)).toBeNull();
    expect(decideForMedula('http://evil.com/', cfg)).toBe('external');
  });
  it('incoming deep link is built locally and validated', () => {
    expect(incomingUrl(cfg, 42)).toBe('https://optiflow.com.tr/sgk-aktar.php?gelen=42');
    expect(incomingUrl(cfg, 0)).toBeNull();
    expect(incomingUrl(cfg, -1)).toBeNull();
    expect(incomingUrl(cfg, 1.5)).toBeNull();
    expect(() => optiflowEndpoint(cfg, '../x.php', {})).toThrow();
  });
  it('config validation rejects unsafe settings', () => {
    expect(() => validateConfig({ ...cfg, optiflowBaseUrl: 'http://optiflow.com.tr' })).toThrow();
    expect(() => validateConfig({ ...cfg, medulaExtractHosts: ['evil.com'] })).toThrow();
    expect(() => validateConfig({ ...cfg, medulaHomeUrl: 'https://evil.com/' })).toThrow();
    expect(() => validateConfig({ ...cfg, updateUrl: 'http://updates.example/' })).toThrow();
    expect(() => validateConfig({ ...cfg, appEnv: 'development', optiflowBaseUrl: 'http://localhost:8080', allowInsecureLocalhost: true })).not.toThrow();
    expect(() => validateConfig({ ...cfg, appEnv: 'staging', optiflowBaseUrl: 'http://localhost:8080', allowInsecureLocalhost: true })).toThrow();
  });
});

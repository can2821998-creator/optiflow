/**
 * Runtime configuration. Values are baked in at build time from config/<env>.json
 * (scripts/build.mjs → esbuild `define`). Development builds may override with
 * environment variables. NO secrets live here – anything shipped in Electron is
 * recoverable by end users (spec §52).
 */
import type { AppEnv, DesktopConfig } from '../shared/types';

declare const __OPTIFLOW_CONFIG__: DesktopConfig;

const HOST_RE = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i;

export function validateConfig(c: DesktopConfig): DesktopConfig {
  const envs: AppEnv[] = ['development', 'staging', 'production'];
  if (!envs.includes(c.appEnv)) throw new Error(`config: invalid appEnv ${String(c.appEnv)}`);

  const base = new URL(c.optiflowBaseUrl);
  const localHttp = base.protocol === 'http:' && ['localhost', '127.0.0.1'].includes(base.hostname);
  if (base.protocol !== 'https:' && !(localHttp && c.appEnv === 'development' && c.allowInsecureLocalhost)) {
    throw new Error('config: optiflowBaseUrl must be https (http://localhost only in development)');
  }
  if (base.search || base.hash || base.username || base.password) throw new Error('config: optiflowBaseUrl must be a plain origin/path');

  if (!Array.isArray(c.medulaAllowedHosts) || c.medulaAllowedHosts.length === 0) throw new Error('config: medulaAllowedHosts empty');
  for (const h of [...c.medulaAllowedHosts, ...c.medulaExtractHosts]) {
    if (!HOST_RE.test(h)) throw new Error(`config: invalid host ${h}`);
  }
  for (const h of c.medulaExtractHosts) {
    if (!c.medulaAllowedHosts.includes(h)) throw new Error(`config: extract host ${h} must also be allowed`);
  }
  const home = new URL(c.medulaHomeUrl);
  if (home.protocol !== 'https:' || !c.medulaAllowedHosts.includes(home.hostname)) {
    throw new Error('config: medulaHomeUrl must be https on an allowed host');
  }
  if (c.updateUrl) {
    const u = new URL(c.updateUrl);
    if (u.protocol !== 'https:') throw new Error('config: updateUrl must be https');
  }
  return {
    ...c,
    allowInsecureLocalhost: c.appEnv === 'development' && c.allowInsecureLocalhost === true,
  };
}

let cached: DesktopConfig | null = null;

export function loadConfig(): DesktopConfig {
  if (cached) return cached;
  const baked = { ...__OPTIFLOW_CONFIG__ };
  if (baked.appEnv === 'development') {
    // Development convenience only; staging/production builds ignore the environment.
    if (process.env.OPTIFLOW_BASE_URL) baked.optiflowBaseUrl = process.env.OPTIFLOW_BASE_URL;
    if (process.env.MEDULA_HOME_URL) baked.medulaHomeUrl = process.env.MEDULA_HOME_URL;
    if (process.env.LOG_LEVEL) baked.logLevel = process.env.LOG_LEVEL as DesktopConfig['logLevel'];
  }
  cached = validateConfig(baked);
  return cached;
}

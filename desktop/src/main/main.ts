/**
 * OptiFlow Desktop — entry point. Kept deliberately small (spec §53).
 */
import { app, dialog } from 'electron';
import path from 'node:path';
import { DesktopApp } from './app-controller';
import { loadConfig } from './config';
import { initLogging, log } from './logging';
import { installGlobalGuards } from './navigation';
import { hardenAppEarly } from './security';
import { restartMedulaBrowser, setupSessions } from './sessions';
import type { DesktopConfig } from '../shared/types';

app.setName('OptiFlow');
app.setAppUserModelId('tr.com.optiflow.desktop');
hardenAppEarly();

// One running instance: a second launch just focuses the existing window (spec §42).
if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  let desktop: DesktopApp | null = null;

  app.on('second-instance', () => desktop?.focus());

  app.whenReady().then(async () => {
    let cfg: DesktopConfig;
    try {
      cfg = loadConfig();
    } catch (e) {
      dialog.showErrorBox('OptiFlow başlatılamadı', `Yapılandırma hatası: ${String((e as Error).message)}`);
      app.exit(1);
      return;
    }
    initLogging(cfg.logLevel);
    log.info('app.start', {
      version: app.getVersion(),
      env: cfg.appEnv,
      electron: process.versions.electron,
      packaged: app.isPackaged,
    });

    installGlobalGuards();
    const sessions = setupSessions(app.getVersion(), new URL(cfg.optiflowBaseUrl).origin, cfg.medulaAllowedHosts);
    // Each app start = a fresh browser for Medula (drops expired JSF session cookies + cache).
    await restartMedulaBrowser(sessions.medula).catch(() => 0);
    desktop = new DesktopApp(cfg, sessions, path.join(__dirname, '..'));
    desktop.create();
  });

  // Certificate errors are NEVER overridden; the load fails and the user sees a message.
  app.on('certificate-error', (event, _wc, url, error, _cert, callback) => {
    event.preventDefault();
    let host = '?';
    try {
      host = new URL(url).host;
    } catch {
      /* ignore */
    }
    log.error('certificate.error', { host, error });
    callback(false);
  });

  app.on('child-process-gone', (_e, d) => log.error('child.process.gone', { type: d.type, reason: d.reason }));
  app.on('before-quit', () => desktop?.shutdown());
  app.on('window-all-closed', () => app.quit());
}

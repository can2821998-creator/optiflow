/**
 * Auto-update (spec §26) via electron-updater, generic HTTPS feed.
 *  - Disabled unless the app is packaged AND config.updateUrl is set.
 *  - Never downloads or installs without the user clicking.
 *  - Integrity: HTTPS feed + sha512 of the installer from latest.yml.
 *  - Authenticode verification is TEMPORARILY OFF until a code-signing certificate
 *    exists (electron-builder.yml: verifyUpdateCodeSignature). Turn it back on then.
 *  - No signing keys in the repo: CSC_LINK / CSC_KEY_PASSWORD come from the CI secret store.
 */
import { app } from 'electron';
import { autoUpdater } from 'electron-updater';
import type { DesktopConfig, ShellState } from '../shared/types';
import { log } from './logging';

type UpdateState = ShellState['update'];

export class Updater {
  private enabled: boolean;
  private timer: ReturnType<typeof setInterval> | null = null;

  constructor(
    cfg: DesktopConfig,
    private readonly onState: (s: UpdateState) => void,
  ) {
    this.enabled = app.isPackaged && !!cfg.updateUrl;
    if (!this.enabled) {
      this.onState({ status: 'kapali' });
      return;
    }
    autoUpdater.setFeedURL({ provider: 'generic', url: cfg.updateUrl, channel: cfg.updateChannel });
    autoUpdater.autoDownload = false;
    autoUpdater.autoInstallOnAppQuit = false;
    autoUpdater.allowDowngrade = false;
    autoUpdater.logger = {
      info: (m: unknown) => log.info('updater', { m: String(m).slice(0, 300) }),
      warn: (m: unknown) => log.warn('updater', { m: String(m).slice(0, 300) }),
      error: (m: unknown) => log.error('updater', { m: String(m).slice(0, 300) }),
      debug: () => undefined,
    };
    autoUpdater.on('checking-for-update', () => this.onState({ status: 'kontrol' }));
    autoUpdater.on('update-not-available', () => this.onState({ status: 'yok' }));
    autoUpdater.on('update-available', (i) => this.onState({ status: 'var', version: i.version }));
    autoUpdater.on('download-progress', (p) => this.onState({ status: 'indiriliyor', percent: Math.round(p.percent) }));
    autoUpdater.on('update-downloaded', (i) => this.onState({ status: 'hazir', version: i.version }));
    autoUpdater.on('error', (e) => {
      log.error('updater.error', { message: String(e?.message ?? e).slice(0, 300) });
      this.onState({ status: 'hata' });
    });
  }

  start(): void {
    if (!this.enabled) return;
    setTimeout(() => void this.check(), 15_000);
    this.timer = setInterval(() => void this.check(), 6 * 3600_000);
    (this.timer as { unref?: () => void }).unref?.();
  }

  /** Menu › "Güncellemeleri denetle". Resolves with a short Turkish result for the toolbar. */
  async check(): Promise<string | null> {
    if (!this.enabled) return app.isPackaged ? 'Otomatik güncelleme bu sürümde kapalı.' : null;
    try {
      const r = await autoUpdater.checkForUpdates();
      if (!r || !r.isUpdateAvailable) return `En son sürümü kullanıyorsunuz (${app.getVersion()}).`;
      return `Yeni sürüm var: ${r.updateInfo.version}. Araç çubuğundaki düğmeden indirebilirsiniz.`;
    } catch {
      return 'Güncelleme denetlenemedi. İnternet bağlantınızı kontrol edin.';
    }
  }

  async download(): Promise<void> {
    if (this.enabled) await autoUpdater.downloadUpdate();
  }

  install(): void {
    if (this.enabled) autoUpdater.quitAndInstall(false, true);
  }

  stop(): void {
    if (this.timer) clearInterval(this.timer);
  }
}

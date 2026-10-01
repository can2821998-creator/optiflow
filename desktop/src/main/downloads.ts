/**
 * Downloads (spec §23): always a "Save As" dialog, sanitised default name,
 * default folder = the user's Downloads folder. Nothing is written silently.
 * Covers server downloads (CSV, backups) and in-browser blobs (ÜTS PDF/XLS –
 * those never leave the PC; Electron only saves the blob locally).
 */
import { app, shell, type Session } from 'electron';
import path from 'node:path';
import { sanitizeFilename } from './filename';
import { log } from './logging';

export interface DownloadEvents {
  onDone: (info: { ok: boolean; fileName: string }) => void;
}

let lastSavedPath: string | null = null;

const FILTERS: Record<string, { name: string; extensions: string[] }> = {
  pdf: { name: 'PDF', extensions: ['pdf'] },
  csv: { name: 'CSV', extensions: ['csv'] },
  xls: { name: 'Excel', extensions: ['xls'] },
  xlsx: { name: 'Excel', extensions: ['xlsx'] },
  sql: { name: 'Yedek', extensions: ['sql'] },
  zip: { name: 'Arşiv', extensions: ['zip'] },
  json: { name: 'JSON', extensions: ['json'] },
};

export function attachDownloads(ses: Session, label: 'optiflow' | 'medula', ev: DownloadEvents): void {
  ses.on('will-download', (_e, item) => {
    const name = sanitizeFilename(item.getFilename());
    const ext = path.extname(name).slice(1).toLowerCase();
    const all = { name: 'Tüm dosyalar', extensions: ['*'] };
    item.setSaveDialogOptions({
      title: 'Dosyayı kaydet',
      defaultPath: path.join(app.getPath('downloads'), name),
      filters: FILTERS[ext] ? [FILTERS[ext]!, all] : [all],
    });
    item.once('done', (_ev, state) => {
      const ok = state === 'completed';
      if (ok) lastSavedPath = item.getSavePath();
      // file names can contain patient names → log only the extension and the outcome
      log.info('download.done', { session: label, state, ext });
      ev.onDone({ ok, fileName: ok ? path.basename(item.getSavePath()) : name });
    });
  });
}

export function openLastDownload(): void {
  if (lastSavedPath) void shell.openPath(lastSavedPath);
}

export function showLastDownloadInFolder(): void {
  if (lastSavedPath) shell.showItemInFolder(lastSavedPath);
}

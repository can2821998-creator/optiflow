/**
 * Central desktop logging (spec §28). Separate from the PHP server log.
 *  - Location: app.getPath('logs')  (Windows: %APPDATA%\OptiFlow\logs)
 *  - One file per day, 14 days retained.
 *  - EVERY line passes through redact(). Callers must never log payloads,
 *    cookies, tokens, passwords or raw prescription text.
 */
import { app, dialog, type BrowserWindow } from 'electron';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { redact } from '../shared/redact';

type Level = 'debug' | 'info' | 'warn' | 'error';
const ORDER: Record<Level, number> = { debug: 10, info: 20, warn: 30, error: 40 };
const RETAIN_DAYS = 14;

let minLevel: Level = 'info';
let dir = '';

export function initLogging(level: Level): void {
  minLevel = level;
  dir = app.getPath('logs');
  fs.mkdirSync(dir, { recursive: true });
  prune();
}

function fileFor(d = new Date()): string {
  return path.join(dir, `optiflow-desktop-${d.toISOString().slice(0, 10)}.log`);
}

function prune(): void {
  try {
    const cutoff = Date.now() - RETAIN_DAYS * 86_400_000;
    for (const f of fs.readdirSync(dir)) {
      if (!/^optiflow-desktop-\d{4}-\d{2}-\d{2}\.log$/.test(f)) continue;
      const full = path.join(dir, f);
      if (fs.statSync(full).mtimeMs < cutoff) fs.unlinkSync(full);
    }
  } catch {
    /* never let logging crash the app */
  }
}

function write(level: Level, event: string, data?: Record<string, unknown>): void {
  if (ORDER[level] < ORDER[minLevel]) return;
  let extra = '';
  if (data) {
    try {
      extra = ' ' + JSON.stringify(data);
    } catch {
      extra = ' [unserialisable]';
    }
  }
  const line = redact(`${new Date().toISOString()} ${level.toUpperCase()} ${event}${extra}`).replace(/[\r\n]+/g, ' ');
  if (!app.isPackaged) console.log(line);
  if (!dir) return;
  try {
    fs.appendFileSync(fileFor(), line + '\n', { encoding: 'utf8' });
  } catch {
    /* disk full / locked: ignore */
  }
}

export const log = {
  debug: (e: string, d?: Record<string, unknown>) => write('debug', e, d),
  info: (e: string, d?: Record<string, unknown>) => write('info', e, d),
  warn: (e: string, d?: Record<string, unknown>) => write('warn', e, d),
  error: (e: string, d?: Record<string, unknown>) => write('error', e, d),
};

/** "Tanılama kayıtlarını dışa aktar…" – support bundle as a single text file, user chooses location. */
export async function exportDiagnostics(parent: BrowserWindow | null, extra: Record<string, unknown>): Promise<void> {
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
  const opts = {
    title: 'Tanılama kayıtlarını kaydet',
    defaultPath: path.join(app.getPath('documents'), `optiflow-tanilama-${stamp}.txt`),
    filters: [{ name: 'Metin', extensions: ['txt'] }],
  };
  const res = parent ? await dialog.showSaveDialog(parent, opts) : await dialog.showSaveDialog(opts);
  if (res.canceled || !res.filePath) return;
  const header = {
    app: app.getName(),
    version: app.getVersion(),
    electron: process.versions.electron,
    chrome: process.versions.chrome,
    os: `${os.platform()} ${os.release()} ${os.arch()}`,
    ...extra,
  };
  let body = redact(JSON.stringify(header, null, 2)) + '\n\n';
  const files = fs.existsSync(dir) ? fs.readdirSync(dir).filter((f) => f.startsWith('optiflow-desktop-')).sort().slice(-7) : [];
  for (const f of files) {
    body += `===== ${f}\n` + redact(fs.readFileSync(path.join(dir, f), 'utf8')) + '\n';
  }
  fs.writeFileSync(res.filePath, body, 'utf8');
  log.info('diagnostics.exported', { files: files.length });
}

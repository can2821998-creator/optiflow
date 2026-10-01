/**
 * Medula preload — runs in an ISOLATED WORLD in every frame of the Medula view.
 *
 *  - Exposes NOTHING to the page (no contextBridge, no globals, no DOM changes).
 *  - Does nothing on its own: it only answers "probe" / "extract" requests that
 *    the main process sends after an explicit user action (or a navigation
 *    event, for the probe, which returns signals only – never content).
 *  - Refuses to run on hosts outside the extract allow-list (main re-checks).
 *  - Never reads password fields (see medula/fields.ts).
 */
import { ipcRenderer } from 'electron';
import { IPC } from '../shared/constants';
import { extractFrame } from '../medula/extractor';
import { probeDocument } from '../medula/detector';
import { listeNumaralari } from '../medula/liste';

const arg = process.argv.find((a) => a.startsWith('--medula-extract-hosts='));
const allowedHosts = (arg ? arg.split('=')[1] ?? '' : '')
  .split(',')
  .map((h) => h.trim().toLowerCase())
  .filter(Boolean);

function allowedHere(): boolean {
  try {
    return window.location.protocol === 'https:' && allowedHosts.includes(window.location.hostname.toLowerCase());
  } catch {
    return false;
  }
}

ipcRenderer.on(IPC.medulaRequest, (_event, req: unknown) => {
  const r = req as { id?: unknown; kind?: unknown } | null;
  if (!r || typeof r.id !== 'string' || (r.kind !== 'probe' && r.kind !== 'extract' && r.kind !== 'liste')) return;
  const id = r.id;
  const kind = r.kind;
  if (!allowedHere()) {
    ipcRenderer.send(IPC.medulaReply, { id, kind, ok: false, error: 'origin' });
    return;
  }
  try {
    const w = window as Window & typeof globalThis;
    if (kind === 'probe') {
      ipcRenderer.send(IPC.medulaReply, { id, kind, ok: true, probe: probeDocument(document, w) });
    } else if (kind === 'liste') {
      // Yalnızca e-reçete numaraları; hasta bilgisi frame dışına çıkmaz.
      ipcRenderer.send(IPC.medulaReply, { id, kind, ok: true, numaralar: listeNumaralari(document) });
    } else {
      ipcRenderer.send(IPC.medulaReply, { id, kind, ok: true, extraction: extractFrame(document, w) });
    }
  } catch {
    ipcRenderer.send(IPC.medulaReply, { id, kind, ok: false, error: 'exception' });
  }
});

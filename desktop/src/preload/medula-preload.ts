/**
 * Medula preload — runs in an ISOLATED WORLD in every frame of the Medula view.
 *
 *  - Exposes NOTHING to the page (no contextBridge, no globals, no DOM changes).
 *  - Does nothing on its own: it only answers "probe" / "extract" requests that
 *    the main process sends after an explicit user action (or a navigation
 *    event, for the probe, which returns signals only – never content).
 *  - Refuses to run on hosts outside the extract allow-list (main re-checks).
 *  - Reçete okuma şifre alanlarını asla okumaz (see medula/fields.ts).
 *  - 5.4.0 Medula girişi (medula/giris.ts): ana süreç isterse giriş ekranını KAYITLI bilgiyle
 *    doldurur; kullanıcı "Giriş"e bastığında alanlardaki değerleri YALNIZCA ana sürece verir
 *    (ana süreç giriş başarılı olunca "kaydedilsin mi?" diye sorar). Sayfaya hiçbir şey açılmaz.
 */
import { ipcRenderer } from 'electron';
import { IPC } from '../shared/constants';
import { extractFrame } from '../medula/extractor';
import { probeDocument } from '../medula/detector';
import { listeNumaralari } from '../medula/liste';
import { girisDoldur, girisIzle } from '../medula/giris';

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

/** Bu belgede giriş izleme kuruldu mu / kayıtlı bilgiyle doldurma denendi mi (belge başına bir kez). */
let izleniyor = false;
let dolduruldu = false;

function girisIstegi(id: string, r: { kayit?: unknown; izle?: unknown }): void {
  const w = window as Window & typeof globalThis;
  if (r.izle === true && !izleniyor) {
    izleniyor = true;
    girisIzle(document, w, (y) => {
      if (allowedHere()) ipcRenderer.send(IPC.medulaGirisYakalandi, y);
    });
  }
  const k = r.kayit as { kullanici?: unknown; sifre?: unknown } | undefined;
  if (!dolduruldu && k && typeof k.kullanici === 'string' && typeof k.sifre === 'string') {
    dolduruldu = true;
    const sonuc = girisDoldur(document, w, { kullanici: k.kullanici, sifre: k.sifre });
    if (sonuc === 'alan-yok') dolduruldu = false; // sayfa henüz tam yüklenmemiş olabilir: sonra yeniden denenir
    ipcRenderer.send(IPC.medulaReply, { id, kind: 'giris', ok: true, sonuc });
    return;
  }
  ipcRenderer.send(IPC.medulaReply, { id, kind: 'giris', ok: true, sonuc: 'izleniyor' });
}

ipcRenderer.on(IPC.medulaRequest, (_event, req: unknown) => {
  const r = req as { id?: unknown; kind?: unknown; kayit?: unknown; izle?: unknown } | null;
  if (!r || typeof r.id !== 'string' || (r.kind !== 'probe' && r.kind !== 'extract' && r.kind !== 'liste' && r.kind !== 'giris')) return;
  const id = r.id;
  const kind = r.kind;
  if (!allowedHere()) {
    ipcRenderer.send(IPC.medulaReply, { id, kind, ok: false, error: 'origin' });
    return;
  }
  try {
    const w = window as Window & typeof globalThis;
    if (kind === 'giris') {
      girisIstegi(id, r);
    } else if (kind === 'probe') {
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

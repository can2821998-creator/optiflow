/**
 * 4.12.0 — Çevrimdışı kopya penceresi (yerel, paketli sayfa) için dar API:
 * yalnızca kopyayı okuyabilir. Başka hiçbir kanal yoktur.
 */
import { contextBridge, ipcRenderer } from 'electron';
import { IPC } from '../shared/constants';

contextBridge.exposeInMainWorld(
  'optiflowCevrimdisi',
  Object.freeze({
    al: (): Promise<unknown> => ipcRenderer.invoke(IPC.offlineGet),
  }),
);

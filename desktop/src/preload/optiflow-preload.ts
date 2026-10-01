/**
 * OptiFlow preload — runs before page scripts in the OptiFlow view.
 *
 * Exposes `window.optiflowDesktop` ONLY when the document's origin is exactly the
 * configured OptiFlow origin (passed by main via additionalArguments). Popups get a
 * different argument set and therefore nothing. Every call is re-checked in the
 * main process (sender webContents + frame + URL).
 *
 * Deliberately narrow (spec §12): no generic invoke, no ipcRenderer, no tokens,
 * no cookies, no Medula content. Only: version, open Medula, start a transfer,
 * read transfer status, subscribe to status changes.
 */
import { contextBridge, ipcRenderer } from 'electron';
import { IPC } from '../shared/constants';

function argValue(name: string): string {
  const a = process.argv.find((x) => x.startsWith(`--${name}=`));
  return a ? a.slice(name.length + 3) : '';
}

const trustedOrigin = argValue('optiflow-origin');
const version = argValue('optiflow-desktop-version');

function isTrusted(): boolean {
  try {
    return trustedOrigin !== '' && window.location.origin === trustedOrigin && window.top === window;
  } catch {
    return false;
  }
}

interface PublicTransfer {
  asama: string;
  mesaj: string;
  zaman?: string;
  gelenId?: number;
  yenidenDenenebilir: boolean;
}

function toPublic(v: unknown): PublicTransfer {
  const o = (v ?? {}) as Record<string, unknown>;
  return {
    asama: typeof o.asama === 'string' ? o.asama : 'bos',
    mesaj: typeof o.mesaj === 'string' ? o.mesaj : '',
    zaman: typeof o.zaman === 'string' ? o.zaman : undefined,
    gelenId: typeof o.gelenId === 'number' ? o.gelenId : undefined,
    yenidenDenenebilir: o.yenidenDenenebilir === true,
  };
}

if (isTrusted()) {
  const listeners = new Set<(t: PublicTransfer) => void>();
  ipcRenderer.on(IPC.optiflowTransferEvent, (_e, data: unknown) => {
    const t = toPublic(data);
    for (const cb of listeners) {
      try {
        cb(t);
      } catch {
        /* page callback errors must not break the bridge */
      }
    }
  });

  contextBridge.exposeInMainWorld(
    'optiflowDesktop',
    Object.freeze({
      surum: version,
      medulaAc: (): void => {
        if (isTrusted()) ipcRenderer.send(IPC.optiflowOpenMedula);
      },
      aktar: async (): Promise<PublicTransfer> => {
        if (!isTrusted()) return toPublic({ asama: 'hata', mesaj: 'Yetkisiz' });
        return toPublic(await ipcRenderer.invoke(IPC.optiflowTransfer));
      },
      durum: async (): Promise<{ surum: string; medulaAcik: boolean; aktarim: PublicTransfer }> => {
        const r = (isTrusted() ? await ipcRenderer.invoke(IPC.optiflowStatus) : {}) as Record<string, unknown>;
        return { surum: version, medulaAcik: r.medulaAcik === true, aktarim: toPublic(r.aktarim) };
      },
      aktarimDinle: (cb: (t: PublicTransfer) => void): (() => void) => {
        if (typeof cb !== 'function') return () => undefined;
        listeners.add(cb);
        return () => listeners.delete(cb);
      },
    }),
  );
}

/**
 * Shell (toolbar) preload. The shell is a local, packaged page with a strict CSP.
 * It can only send one of the whitelisted commands (validated again in main)
 * and receive the ShellState snapshot.
 */
import { contextBridge, ipcRenderer } from 'electron';
import { IPC, SHELL_COMMANDS } from '../shared/constants';

type Cmd = (typeof SHELL_COMMANDS)[number];

contextBridge.exposeInMainWorld(
  'optiflowShell',
  Object.freeze({
    komut: (cmd: Cmd): void => {
      if ((SHELL_COMMANDS as readonly string[]).includes(cmd)) ipcRenderer.send(IPC.shellCommand, cmd);
    },
    durumAl: (): Promise<unknown> => ipcRenderer.invoke(IPC.shellGetState),
    durumDinle: (cb: (s: unknown) => void): void => {
      ipcRenderer.on(IPC.shellState, (_e, s: unknown) => cb(s));
    },
  }),
);

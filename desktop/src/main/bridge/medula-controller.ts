/**
 * Talks to the Medula preload (isolated world) in each allowed frame.
 *
 * Flow (spec §15): user action → verify top URL → probe allowed frames →
 * choose frame → ask THAT frame to extract → validate reply (sender frame
 * identity + origin + schema) → return extraction to the caller.
 *
 * The Medula page's own JavaScript cannot see or trigger any of this: the
 * preload exposes nothing to the main world, and replies are only accepted
 * from the exact frame we asked, for an id we generated.
 */
import { randomUUID } from 'node:crypto';
import type { IpcMainEvent, WebContents, WebFrameMain } from 'electron';
import type { MedulaGirisKaydi } from '../medula-giris-kasasi';
import { FRAME_REPLY_TIMEOUT_MS, IPC } from '../../shared/constants';
import { redactUrl } from '../../shared/redact';
import type { DesktopConfig, MedulaExtraction, MedulaProbe } from '../../shared/types';
import { parseGirisYakalandi, parseMedulaReply, type MedulaReply } from '../ipc-validation';
import { log } from '../logging';
import { chooseFrame, chooseTextFrame, summarizeProbes, type FrameProbe } from './frame-selection';
import { isMedulaExtractOrigin, isMedulaExtractUrl, isMedulaUrl } from './origin-policy';

interface Pending {
  frameToken: string;
  processId: number;
  kind: 'probe' | 'extract' | 'liste' | 'giris';
  resolve: (r: MedulaReply | null) => void;
  timer: ReturnType<typeof setTimeout>;
}

export type ExtractOutcome =
  | { kind: 'ok'; extraction: MedulaExtraction }
  | { kind: 'not-medula' }
  | { kind: 'login' }
  | { kind: 'not-found' }
  | { kind: 'failed' };

export class MedulaController {
  private pending = new Map<string, Pending>();

  constructor(
    private readonly wc: WebContents,
    private readonly cfg: DesktopConfig,
    /** 5.4.0 — the user pressed "Giriş" on the Medula login page (values stay in main memory). */
    private readonly onGiris: (y: { kullanici: string; sifre: string; degisim: boolean }) => void = () => {},
  ) {
    // Scoped to THIS webContents only – no global ipcMain listener.
    wc.ipc.on(IPC.medulaReply, (event, msg) => this.onReply(event, msg));
    wc.ipc.on(IPC.medulaGirisYakalandi, (event, msg) => {
      const f = event.senderFrame;
      if (!f || !isMedulaExtractUrl(f.url, this.cfg) || !isMedulaExtractOrigin(f.origin, this.cfg)) {
        log.warn('medula.giris.wrong-sender');
        return;
      }
      const y = parseGirisYakalandi(msg);
      if (y) this.onGiris(y); // never logged
    });
  }

  private onReply(event: IpcMainEvent, raw: unknown): void {
    const reply = parseMedulaReply(raw);
    if (!reply) {
      log.warn('medula.reply.invalid');
      return;
    }
    const p = this.pending.get(reply.id);
    if (!p) return; // unknown / late / forged id
    const f = event.senderFrame;
    if (!f || f.frameToken !== p.frameToken || f.processId !== p.processId || !isMedulaExtractOrigin(f.origin, this.cfg)) {
      log.warn('medula.reply.wrong-sender');
      return;
    }
    if (reply.kind !== p.kind) return;
    clearTimeout(p.timer);
    this.pending.delete(reply.id);
    p.resolve(reply);
  }

  private ask(frame: WebFrameMain, kind: 'probe' | 'extract' | 'liste' | 'giris', extra: Record<string, unknown> = {}): Promise<MedulaReply | null> {
    return new Promise((resolve) => {
      if (frame.isDestroyed() || frame.detached) return resolve(null);
      const id = randomUUID();
      const timer = setTimeout(() => {
        this.pending.delete(id);
        resolve(null);
      }, FRAME_REPLY_TIMEOUT_MS);
      this.pending.set(id, { frameToken: frame.frameToken, processId: frame.processId, kind, resolve, timer });
      try {
        frame.send(IPC.medulaRequest, { ...extra, id, kind });
      } catch {
        clearTimeout(timer);
        this.pending.delete(id);
        resolve(null);
      }
    });
  }

  /** Frames we are allowed to read: https + extract-host, both by URL and by committed origin. */
  private readableFrames(): WebFrameMain[] {
    const main = this.wc.mainFrame;
    if (!main) return [];
    return main.framesInSubtree.filter(
      (f) => !f.isDestroyed() && isMedulaExtractUrl(f.url, this.cfg) && isMedulaExtractOrigin(f.origin, this.cfg),
    );
  }

  topUrlAllowed(): boolean {
    return isMedulaUrl(this.wc.getURL(), this.cfg);
  }

  private async probeFrames(): Promise<FrameProbe<WebFrameMain>[]> {
    const frames = this.readableFrames();
    const replies = await Promise.all(frames.map((f) => this.ask(f, 'probe')));
    const out: FrameProbe<WebFrameMain>[] = [];
    replies.forEach((r, i) => {
      if (r && r.ok && r.kind === 'probe') out.push({ frame: frames[i]!, probe: r.probe, isTop: frames[i]!.parent === null });
    });
    return out;
  }

  /** Toolbar hint only (no content leaves the frame). */
  async probe(): Promise<MedulaProbe | undefined> {
    if (!this.topUrlAllowed()) return undefined;
    return summarizeProbes(await this.probeFrames());
  }

  /** Called ONLY from an explicit user action (toolbar button / OptiFlow page button). */
  async extract(): Promise<ExtractOutcome> {
    if (!this.topUrlAllowed()) return { kind: 'not-medula' };
    const probes = await this.probeFrames();
    const choice = chooseFrame(probes);
    log.info('bridge.extract.probe', { frames: probes.length, choice: choice.kind });
    if (choice.kind !== 'ok') return choice;

    const reply = await this.ask(choice.frame, 'extract');
    if (!reply || !reply.ok || reply.kind !== 'extract') {
      log.warn('bridge.extract.failed', { frame: redactUrl(choice.frame.url) });
      return { kind: 'failed' };
    }
    const x = reply.extraction;
    // The frame might have navigated between probe and extract: re-check the claimed URL.
    if (!isMedulaExtractUrl(x.frameUrl ?? choice.frame.url, this.cfg)) return { kind: 'failed' };
    if (!x.detectedPrescription) return { kind: 'not-found' };
    x.url = this.wc.getURL();
    log.info('bridge.extract.ok', { fields: x.extractedFieldCount, chars: x.text.length, frame: redactUrl(x.frameUrl) });
    return { kind: 'ok', extraction: x };
  }

  /**
   * 5.3.0 — "Hak sorgula": reads the visible Medula screen even when it is not a prescription
   * (SGK hak / cam-çerçeve geçmişi). Explicit user action only; same frame/origin checks as extract().
   */
  async extractScreen(): Promise<ExtractOutcome> {
    if (!this.topUrlAllowed()) return { kind: 'not-medula' };
    const probes = await this.probeFrames();
    const choice = chooseTextFrame(probes);
    log.info('bridge.screen.probe', { frames: probes.length, choice: choice.kind });
    if (choice.kind !== 'ok') return choice;
    const reply = await this.ask(choice.frame, 'extract');
    if (!reply || !reply.ok || reply.kind !== 'extract') return { kind: 'failed' };
    const x = reply.extraction;
    if (!isMedulaExtractUrl(x.frameUrl ?? choice.frame.url, this.cfg)) return { kind: 'failed' };
    x.url = this.wc.getURL();
    return { kind: 'ok', extraction: x };
  }

  /**
   * 4.12.0 — Reçete listesi ekranındaki e-reçete NUMARALARI (yalnızca numaralar; hasta bilgisi yok).
   * Açık kullanıcı eylemiyle çağrılır ("Listeyi kontrol et").
   */
  async readList(): Promise<{ kind: 'ok'; numaralar: string[] } | { kind: 'not-medula' } | { kind: 'login' } | { kind: 'empty' }> {
    if (!this.topUrlAllowed()) return { kind: 'not-medula' };
    const frames = this.readableFrames();
    const probes = await this.probeFrames();
    if (probes.length > 0 && probes.every((p) => p.probe.looksLikeLogin)) return { kind: 'login' };
    const replies = await Promise.all(frames.map((f) => this.ask(f, 'liste')));
    const set = new Set<string>();
    for (const r of replies) {
      if (r && r.ok && r.kind === 'liste') r.numaralar.forEach((n) => set.add(n));
    }
    log.info('bridge.list.read', { frames: frames.length, count: set.size });
    return set.size ? { kind: 'ok', numaralar: Array.from(set).slice(0, 1000) } : { kind: 'empty' };
  }

  /**
   * 5.4.0 — Giriş ekranı(ları)nda: izlemeyi kurar ve kayıt varsa boş alanları doldurur.
   * Yalnızca şifre alanı görünen (giriş / şifre değiştirme) ve okunabilir SGK çerçevelerine gider.
   */
  async giris(kayit: MedulaGirisKaydi | null, izle: boolean): Promise<string[]> {
    if (!this.topUrlAllowed() || (!kayit && !izle)) return [];
    const girisCerceveleri = (await this.probeFrames()).filter((p) => p.probe.looksLikeLogin).map((p) => p.frame);
    const replies = await Promise.all(girisCerceveleri.map((f) => this.ask(f, 'giris', { izle, ...(kayit ? { kayit } : {}) })));
    return replies.map((r) => (r && r.ok && r.kind === 'giris' ? r.sonuc : 'yanit-yok'));
  }

  dispose(): void {
    for (const p of this.pending.values()) {
      clearTimeout(p.timer);
      p.resolve(null);
    }
    this.pending.clear();
  }
}

/**
 * In-memory retry slot (spec §21). Holds AT MOST ONE captured payload so a failed
 * transfer can be retried without re-reading Medula.
 *  - Storage: process memory only. Never written to disk, never logged.
 *  - Retention: RETRY_TTL_MS (15 min), then discarded.
 *  - Deleted on: successful transfer, new capture, Medula session clear, app quit.
 */
import { RETRY_TTL_MS } from '../../shared/constants';
import type { TransferPayload } from './transfer-service';

export class RetryStore {
  private item: { payload: TransferPayload; at: number } | null = null;
  private timer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private readonly ttlMs = RETRY_TTL_MS,
    private readonly now: () => number = Date.now,
  ) {}

  put(payload: TransferPayload): void {
    this.clear();
    this.item = { payload, at: this.now() };
    this.timer = setTimeout(() => this.clear(), this.ttlMs);
    // don't keep the process alive just for this timer
    (this.timer as { unref?: () => void }).unref?.();
  }

  get(): TransferPayload | null {
    if (!this.item) return null;
    if (this.now() - this.item.at > this.ttlMs) {
      this.clear();
      return null;
    }
    return this.item.payload;
  }

  has(): boolean {
    return this.get() !== null;
  }

  clear(): void {
    if (this.timer) clearTimeout(this.timer);
    this.timer = null;
    if (this.item) this.item.payload = { metin: '', baslik: '' }; // drop the reference so the text can be GC'd
    this.item = null;
  }
}

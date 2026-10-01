/**
 * 4.12.0 — Çevrimdışı salt okunur kopya.
 *
 * İçerik: açık siparişler (no, ad, telefon, aşama, teslim sözü, yetki varsa kalan).
 * Diskte YALNIZCA işletim sisteminin kullanıcıya bağlı şifrelemesiyle (Windows DPAPI,
 * Electron safeStorage) saklanır; şifreleme yoksa yalnızca bellekte tutulur.
 * Kopya: 7 günden eskiyse kullanılmaz; mağaza/kullanıcı değişince, özellik kapanınca
 * ve kullanıcı çıkış yapınca silinir. Yazma/okuma bağımlılıkları testte taklit edilebilir.
 */
import fs from 'node:fs';
import { OFFLINE_MAX_AGE_MS } from '../shared/constants';
import type { OfflineSnapshot } from '../shared/types';

export interface Crypto {
  available(): boolean;
  encrypt(plain: string): Buffer;
  decrypt(buf: Buffer): string;
}

interface Stored {
  v: 1;
  at: number;
  storeId: number;
  userId: number;
  data: OfflineSnapshot;
}

export class OfflineStore {
  private mem: Stored | null = null;

  constructor(
    private readonly file: string,
    private readonly crypto: Crypto,
    private readonly now: () => number = Date.now,
  ) {}

  save(data: OfflineSnapshot): void {
    const rec: Stored = { v: 1, at: this.now(), storeId: data.magaza.id, userId: data.kullanici.id, data };
    this.mem = rec;
    if (!this.crypto.available()) return; // şifreleme yoksa diske YAZILMAZ
    try {
      fs.writeFileSync(this.file, this.crypto.encrypt(JSON.stringify(rec)), { mode: 0o600 });
    } catch {
      /* disk hatası: bellekteki kopya yeter */
    }
  }

  private read(): Stored | null {
    if (this.mem) return this.mem;
    if (!this.crypto.available()) return null;
    try {
      const rec = JSON.parse(this.crypto.decrypt(fs.readFileSync(this.file))) as Stored;
      if (rec?.v !== 1 || typeof rec.at !== 'number' || !rec.data) return null;
      this.mem = rec;
      return rec;
    } catch {
      return null;
    }
  }

  /** Geçerli kopya (7 günden yeni); istenirse mağaza/kullanıcı eşleşmesi aranır. */
  load(expected?: { storeId: number; userId: number }): OfflineSnapshot | null {
    const rec = this.read();
    if (!rec) return null;
    if (this.now() - rec.at > OFFLINE_MAX_AGE_MS) {
      this.clear();
      return null;
    }
    if (expected && (rec.storeId !== expected.storeId || rec.userId !== expected.userId)) return null;
    return rec.data;
  }

  meta(): { available: boolean; at?: string; count?: number } {
    const d = this.load();
    const rec = this.mem;
    return d && rec ? { available: true, at: new Date(rec.at).toISOString(), count: d.siparisler.length } : { available: false };
  }

  /** Son kaydın sahibi (hesap değişikliğini anlamak için). */
  owner(): { storeId: number; userId: number } | null {
    const rec = this.read();
    return rec ? { storeId: rec.storeId, userId: rec.userId } : null;
  }

  clear(): void {
    this.mem = null;
    try {
      fs.rmSync(this.file, { force: true });
    } catch {
      /* yoksay */
    }
  }
}

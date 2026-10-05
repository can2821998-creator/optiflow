/**
 * 5.4.0 — Kayıtlı Medula giriş bilgisi (kullanıcı adı + şifre), YALNIZCA bu bilgisayarda.
 *
 *  - Diskte yalnızca işletim sisteminin kullanıcıya bağlı şifrelemesiyle (Windows DPAPI,
 *    Electron safeStorage) durur: dosya başka bir Windows hesabında / bilgisayarda çözülemez.
 *  - Şifreleme yoksa HİÇ kaydedilmez (düz metin yedek yok).
 *  - OptiFlow sunucusuna, kayıtlara (log) ya da tanılama dışa aktarımına asla gitmez.
 *  - "Bu bilgisayarda sorma" tercihi ayrı ve gizli olmayan bir dosyadadır.
 */
import fs from 'node:fs';
import type { Crypto } from './offline-store';

export interface MedulaGirisKaydi {
  kullanici: string;
  sifre: string;
}

interface Dosya {
  v: 1;
  at: number;
  kullanici: string;
  sifre: string;
}

const KULLANICI_MAX = 100;
const SIFRE_MAX = 200;

/** Kontrol karakteri yok, boş değil, makul uzunluk. */
export function gecerliGiris(v: unknown): MedulaGirisKaydi | null {
  if (typeof v !== 'object' || v === null) return null;
  const o = v as Record<string, unknown>;
  const k = typeof o.kullanici === 'string' ? o.kullanici.trim() : '';
  const s = typeof o.sifre === 'string' ? o.sifre : '';
  // eslint-disable-next-line no-control-regex
  const kontrol = /[\u0000-\u001F\u007F]/;
  if (!k || !s || k.length > KULLANICI_MAX || s.length > SIFRE_MAX || kontrol.test(k) || kontrol.test(s)) return null;
  return { kullanici: k, sifre: s };
}

export class MedulaGirisKasasi {
  private mem: Dosya | null = null;
  private okundu = false;

  constructor(
    private readonly dosya: string,
    private readonly ayarDosyasi: string,
    private readonly crypto: Crypto,
    private readonly now: () => number = Date.now,
  ) {}

  /** Bu bilgisayarda şifreli saklama mümkün mü? */
  kullanilabilir(): boolean {
    return this.crypto.available();
  }

  private oku(): Dosya | null {
    if (this.okundu) return this.mem;
    this.okundu = true;
    if (!this.crypto.available()) return null;
    try {
      const d = JSON.parse(this.crypto.decrypt(fs.readFileSync(this.dosya))) as Dosya;
      const g = d && d.v === 1 ? gecerliGiris(d) : null;
      this.mem = g ? { v: 1, at: Number(d.at) || 0, ...g } : null;
    } catch {
      this.mem = null;
    }
    return this.mem;
  }

  al(): MedulaGirisKaydi | null {
    const d = this.oku();
    return d ? { kullanici: d.kullanici, sifre: d.sifre } : null;
  }

  /** Ekranda göstermek için: kullanıcı adının yalnızca baş ve son karakteri. */
  ozet(): { kayitli: boolean; kullanici?: string; tarih?: string } {
    const d = this.oku();
    if (!d) return { kayitli: false };
    const k = d.kullanici;
    const maske = k.length <= 2 ? '•'.repeat(k.length) : `${k[0]}${'•'.repeat(Math.min(6, k.length - 2))}${k[k.length - 1]}`;
    return { kayitli: true, kullanici: maske, tarih: d.at ? new Date(d.at).toISOString() : undefined };
  }

  /** Şifreleme yoksa false döner ve hiçbir şey yazmaz. */
  kaydet(k: MedulaGirisKaydi): boolean {
    const g = gecerliGiris(k);
    if (!g || !this.crypto.available()) return false;
    const d: Dosya = { v: 1, at: this.now(), ...g };
    try {
      fs.writeFileSync(this.dosya, this.crypto.encrypt(JSON.stringify(d)), { mode: 0o600 });
    } catch {
      return false;
    }
    this.mem = d;
    this.okundu = true;
    return true;
  }

  sil(): void {
    this.mem = null;
    this.okundu = true;
    try {
      fs.rmSync(this.dosya, { force: true });
    } catch {
      /* yoksay */
    }
  }

  /** "Medula şifresini kaydetmeyi öner" (varsayılan: açık). */
  teklifAcik(): boolean {
    try {
      const a = JSON.parse(fs.readFileSync(this.ayarDosyasi, 'utf8')) as { teklif?: unknown };
      return a.teklif !== false;
    } catch {
      return true;
    }
  }

  teklifAyarla(acik: boolean): void {
    try {
      fs.writeFileSync(this.ayarDosyasi, JSON.stringify({ teklif: acik }), { mode: 0o600 });
    } catch {
      /* yoksay */
    }
  }
}

/**
 * Giriş denemesi → başarılı mı, ne sorulmalı? Saf fonksiyon (birim testli).
 *  - 'yok'       : sorulacak bir şey yok (aynısı zaten kayıtlı, teklif kapalı, şifreleme yok…)
 *  - 'kaydet'    : kayıt yok → "kaydedilsin mi?"
 *  - 'guncelle'  : kayıtlı şifre farklı → "güncellensin mi?"
 */
export function neSorulmali(
  yakalanan: { kullanici: string; sifre: string; degisim: boolean },
  kayitli: MedulaGirisKaydi | null,
  teklifAcik: boolean,
  sifrelemeVar: boolean,
): { soru: 'yok' | 'kaydet' | 'guncelle'; kayit?: MedulaGirisKaydi } {
  if (!sifrelemeVar) return { soru: 'yok' };
  if (yakalanan.degisim) {
    // Şifre değiştirme ekranı: kullanıcı adı ekranda yoksa kayıtlı olandan alınır.
    const kullanici = yakalanan.kullanici || kayitli?.kullanici || '';
    if (!kullanici || !kayitli) return { soru: 'yok' };
    if (kayitli.kullanici === kullanici && kayitli.sifre === yakalanan.sifre) return { soru: 'yok' };
    return { soru: 'guncelle', kayit: { kullanici, sifre: yakalanan.sifre } };
  }
  const kayit = { kullanici: yakalanan.kullanici, sifre: yakalanan.sifre };
  if (kayitli && kayitli.kullanici === kayit.kullanici && kayitli.sifre === kayit.sifre) return { soru: 'yok' };
  if (kayitli && kayitli.kullanici === kayit.kullanici) return { soru: 'guncelle', kayit };
  if (!teklifAcik) return { soru: 'yok' };
  return { soru: kayitli ? 'guncelle' : 'kaydet', kayit };
}

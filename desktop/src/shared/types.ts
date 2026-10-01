/** Domain types shared between main, preloads, shell and tests. */

export type AppEnv = 'development' | 'staging' | 'production';

export interface DesktopConfig {
  appEnv: AppEnv;
  /** Origin (+ optional sub-path) of the OptiFlow server, e.g. https://optiflow.com.tr */
  optiflowBaseUrl: string;
  /** Page opened by "Medula ana sayfa". */
  medulaHomeUrl: string;
  /** Hosts the Medula view may navigate to (https only). */
  medulaAllowedHosts: string[];
  /** Hosts whose frames may be read by the extractor. Must be a subset of medulaAllowedHosts. */
  medulaExtractHosts: string[];
  /** Generic update feed (electron-updater). Empty = updates disabled. */
  updateUrl: string;
  updateChannel: 'latest' | 'beta';
  logLevel: 'debug' | 'info' | 'warn' | 'error';
  /** Development only: allow http:// for a local OptiFlow server. Ignored in production builds. */
  allowInsecureLocalhost: boolean;
}

/** Result of the lightweight page probe (NO page content, only signals). */
export interface MedulaProbe {
  detectedPrescription: boolean;
  /** 0..100 heuristic score, higher = more likely the prescription detail screen. */
  score: number;
  extractedFieldCount: number;
  /** A visible password field suggests the SGK login screen (session expired / not logged in). */
  looksLikeLogin: boolean;
  textLength: number;
}

/** What the extractor returns for ONE frame. Mirrors spec §36. */
export interface MedulaExtraction {
  text: string;
  title: string;
  url: string;
  frameUrl?: string;
  detectedPrescription: boolean;
  extractedFieldCount: number;
  timestamp: string;
}

export type TransferPhase =
  | 'bos' // idle, nothing yet
  | 'okunuyor' // extracting
  | 'gonderiliyor' // HTTP in flight
  | 'basarili'
  | 'hata';

export interface TransferState {
  phase: TransferPhase;
  /** Human-readable Turkish message for the toolbar. */
  message: string;
  /** Short summary from the server. Memory only; shown in the toolbar, never sent to the page. */
  summary?: string;
  incomingId?: number;
  canRetry: boolean;
  at?: string; // ISO timestamp
}

export type Package = 'lite' | 'pro';

export interface AccountInfo {
  storeId: number;
  storeName: string;
  userId: number;
  userName: string;
  supportMode: boolean;
  /** Store package, decided by the SERVER (merkez panel). */
  package: Package;
  /** Server says the Medula transfer is available for this store in this app. */
  transferAllowed: boolean;
  /** 4.12.0 — features the merkez panel enabled for this store (server-decided). */
  features: Record<string, boolean>;
}

/** 4.12.0 — read-only copy shown when the OptiFlow server is unreachable. */
export interface OfflineOrder {
  no: string;
  ad: string;
  tel: string;
  asama: string;
  soz: string;
  tarih: string;
  cam: string;
  cerceve: string;
  kalan: string;
}
export interface OfflineSnapshot {
  olusturma: string;
  magaza: { id: number; isim: string };
  kullanici: { id: number; ad: string };
  magaza_telefon: string;
  siparisler: OfflineOrder[];
}

export interface ShellState {
  version: string;
  appEnv: AppEnv;
  activeView: 'optiflow' | 'medula';
  layout: 'sekme' | 'bolunmus';
  online: boolean;
  optiflow: { loaded: boolean; error?: string; url?: string; everLoaded?: boolean };
  medula: {
    opened: boolean;
    canGoBack: boolean;
    canGoForward: boolean;
    host?: string;
    probe?: MedulaProbe;
    error?: string;
  };
  account?: AccountInfo;
  transfer: TransferState;
  /** 4.12.0 — offline copy available (and when it was taken). */
  offline?: { available: boolean; at?: string; count?: number };
  update: { status: 'kapali' | 'bekliyor' | 'kontrol' | 'yok' | 'var' | 'indiriliyor' | 'hazir' | 'hata'; version?: string; percent?: number };
  notice?: { kind: 'info' | 'warn' | 'error'; text: string; action?: 'indirme-ac' | 'indirme-klasor' | 'cevrimdisi-ac' };
}

/** JSON returned by masaustu.php?action=durum */
export interface ServerStatusResponse {
  ok: boolean;
  api?: number;
  surum?: string;
  kullanici?: { id: number; ad: string };
  magaza?: { id: number; isim: string };
  destek_modu?: boolean;
  paket?: string;
  pro?: Record<string, boolean>;
  ozellikler?: Record<string, boolean>;
  csrf?: string;
  kod?: string;
  hata?: string;
}

/** JSON returned by masaustu.php?action=aktar */
export interface ServerTransferResponse {
  ok: boolean;
  gelen_id?: number;
  bulunan?: number;
  ozet?: string;
  hedef?: string;
  kod?: string;
  hata?: string;
}

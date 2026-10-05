/**
 * Shared constants. Everything here is safe to ship to any process: no secrets.
 */

/** Session partitions. Separate partitions = separate cookie jars, caches and storage. */
export const PARTITION_OPTIFLOW = 'persist:optiflow';
export const PARTITION_MEDULA = 'persist:medula';

/** Marker appended to the User-Agent of the OptiFlow partition ONLY (UX hint for PHP, never auth). */
export const DESKTOP_UA_TOKEN = 'OptiFlowDesktop';

/**
 * Server-side limit in app/pages/sgk-aktar.php and app/pages/masaustu.php is 200 000 BYTES (strlen).
 * Turkish characters are 2 bytes in UTF-8, so we cap by bytes, leaving head-room.
 * The legacy extension capped at 180 000 characters, which could exceed the server limit.
 */
export const MAX_TRANSFER_BYTES = 190_000;
export const MIN_TRANSFER_CHARS = 20; // same threshold as kopru-eklenti/icerik.js
export const MAX_TITLE_CHARS = 160; // same as server mb_substr(..., 0, 160)

/** Retry payload is kept in memory only, and only this long. */
export const RETRY_TTL_MS = 15 * 60 * 1000;

/** HTTP timeouts for calls to the OptiFlow backend. */
export const HTTP_TIMEOUT_MS = 20_000;

/** How long the main process waits for a Medula frame to answer an extraction request. */
export const FRAME_REPLY_TIMEOUT_MS = 4_000;

/** Height of the shell toolbar (px). The views are laid out below it. */
export const TOOLBAR_HEIGHT = 52;

/** IPC channel names. Each renderer gets its OWN, narrow set; nothing generic. */
export const IPC = {
  // main <-> Medula preload (isolated world). Main sends requests; preload only replies.
  medulaRequest: 'medula:istek',
  medulaReply: 'medula:yanit',
  /** 5.4.0 — Medula preload → main: the user pressed "Giriş" (login form values, memory only). */
  medulaGirisYakalandi: 'medula:giris-yakalandi',

  // OptiFlow page (trusted origin only) -> main
  optiflowOpenMedula: 'optiflow:medula-ac',
  optiflowTransfer: 'optiflow:aktar',
  optiflowStatus: 'optiflow:durum',
  // main -> OptiFlow page
  optiflowTransferEvent: 'optiflow:aktarim-olayi',

  // shell toolbar -> main
  shellCommand: 'shell:komut',
  shellGetState: 'shell:durum-al',
  // main -> shell
  shellState: 'shell:durum',

  // offline window (local page) -> main
  offlineGet: 'cevrimdisi:al',
} as const;

/** The only commands the shell toolbar may send. Validated in main (see ipc-validation.ts). */
export const SHELL_COMMANDS = [
  'goster-optiflow',
  'goster-medula',
  'duzen-bolunmus',
  'duzen-sekme',
  'medula-geri',
  'medula-ileri',
  'medula-yenile',
  'medula-ana-sayfa',
  'medula-sifirla',
  'aktar',
  'yeniden-dene',
  'gelen-ac',
  'optiflow-yenile',
  'hata-yeniden-dene',
  'guncelleme-indir',
  'guncelleme-kur',
  'indirme-ac',
  'indirme-klasor',
  'bildirim-kapat',
  'menu',
  'liste-kontrol',
  'cevrimdisi-ac',
  'hak-sorgula',
] as const;

/** 4.12.0 — offline copy: refreshed at most this often while online, discarded after MAX_AGE. */
export const OFFLINE_REFRESH_MS = 10 * 60 * 1000;
export const OFFLINE_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

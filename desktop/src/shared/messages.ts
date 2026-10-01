/**
 * User-facing Turkish messages (spec §50). Developer logs stay in English.
 * Central error normalisation lives here so every surface shows the same text.
 */

export const MSG = {
  medulaBaglanti: 'Medula bağlantısı kurulamadı. İnternet bağlantınızı kontrol edin.',
  receteYok: "Reçete ekranı bulunamadı. Lütfen Medula'da reçete detayını açın.",
  receteOkunamadi: 'Reçete okunamadı. Sayfayı yenileyip tekrar deneyin.',
  aktarimBasarisiz: "OptiFlow'a aktarım başarısız oldu. Tekrar deneyebilirsiniz.",
  oturumDoldu: "Oturum süreniz dolmuş. OptiFlow'a yeniden giriş yapın.",
  medulaOturumBitti: "Medula oturumu sona ermiş. Medula'ya yeniden giriş yapın.",
  medulaAcikDegil: "Önce Medula'yı açın ve reçete detayına gelin.",
  medulaDisiAdres: 'Bu sayfa Medula dışında olduğu için okunmaz.',
  internetYok: 'İnternet bağlantısı yok. Bağlantınızı kontrol edin.',
  sunucuYok: 'OptiFlow sunucusuna ulaşılamadı. Biraz sonra tekrar deneyin.',
  zamanAsimi: 'Sunucu zamanında yanıt vermedi. Tekrar deneyebilirsiniz.',
  sertifika: 'Güvenli bağlantı doğrulanamadı (sertifika hatası). Bağlantı kesildi.',
  dnsHatasi: 'Sunucu adresi çözülemedi (DNS). İnternet bağlantınızı kontrol edin.',
  destekModu: 'Destek (merkez) oturumunda Medula aktarımı kapalıdır. Mağaza kullanıcısıyla giriş yapın.',
  hesapDegisti: 'OptiFlow hesabı aktarım sırasında değişti. Tekrar deneyin.',
  cokFazla: 'Çok fazla aktarım yapıldı, biraz bekleyin.',
  magazaKapali: 'Mağaza hesabı şu anda kullanıma kapalı.',
  okunuyor: 'Reçete okunuyor…',
  gonderiliyor: "OptiFlow'a gönderiliyor…",
  basarili: "Reçete OptiFlow'a aktarıldı. Önizlemeyi açıp kontrol edin.",
  yenidenDenemeYok: 'Yeniden denenecek bir aktarım yok ya da süresi doldu.',
  metinKisa: 'Sayfada aktarılacak bilgi bulunamadı.',
  proGerekli: 'Medula aktarımı OptiFlow Pro paketindedir. Mağazanızın paketi Lite.',
} as const;

export type NormalizedErrorCode =
  | 'offline'
  | 'dns'
  | 'timeout'
  | 'certificate'
  | 'server-unreachable'
  | 'session-expired'
  | 'support-mode'
  | 'account-changed'
  | 'rate-limited'
  | 'store-closed'
  | 'pro-required'
  | 'bad-response'
  | 'server-error'
  | 'unknown';

export interface NormalizedError {
  code: NormalizedErrorCode;
  message: string;
  /** true when retrying the same payload later can succeed. */
  retryable: boolean;
}

/** Chromium net error names (from fetch/net failures) → normalised errors. */
export function normalizeNetError(err: unknown): NormalizedError {
  const text = String((err as { message?: string } | null)?.message ?? err ?? '');
  const name = String((err as { name?: string } | null)?.name ?? '');
  if (name === 'AbortError' || /TIMED_OUT|timeout|aborted/i.test(text)) {
    return { code: 'timeout', message: MSG.zamanAsimi, retryable: true };
  }
  if (/INTERNET_DISCONNECTED|NETWORK_CHANGED|ADDRESS_UNREACHABLE/i.test(text)) {
    return { code: 'offline', message: MSG.internetYok, retryable: true };
  }
  if (/NAME_NOT_RESOLVED|NAME_RESOLUTION_FAILED/i.test(text)) {
    return { code: 'dns', message: MSG.dnsHatasi, retryable: true };
  }
  if (/CERT_|SSL_|certificate/i.test(text)) {
    return { code: 'certificate', message: MSG.sertifika, retryable: false };
  }
  if (/CONNECTION_REFUSED|CONNECTION_RESET|CONNECTION_CLOSED|CONNECTION_FAILED|EMPTY_RESPONSE/i.test(text)) {
    return { code: 'server-unreachable', message: MSG.sunucuYok, retryable: true };
  }
  return { code: 'unknown', message: MSG.aktarimBasarisiz, retryable: true };
}

/** Chromium did-fail-load error codes → user message (used for page loads). */
export function messageForLoadError(errorCode: number): string {
  if (errorCode === -106) return MSG.internetYok; // ERR_INTERNET_DISCONNECTED
  if (errorCode === -105 || errorCode === -137) return MSG.dnsHatasi; // NAME_NOT_RESOLVED / NAME_RESOLUTION_FAILED
  if (errorCode === -7 || errorCode === -118) return MSG.zamanAsimi; // TIMED_OUT / CONNECTION_TIMED_OUT
  if (errorCode <= -200 && errorCode > -300) return MSG.sertifika; // certificate errors
  return MSG.sunucuYok;
}

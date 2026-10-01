/**
 * Log redaction (spec §28). Applied to EVERY log line before it is written.
 * Defence in depth: callers must still avoid logging payloads; this catches mistakes.
 */

const RULES: Array<[RegExp, string]> = [
  // 40-hex bridge tokens and other long hex secrets (csrf is 64-hex)
  [/\b[a-f0-9]{32,}\b/gi, '[hex-redacted]'],
  // T.C. kimlik no: 11 digits, first digit non-zero. Keep last 2 digits for support correlation.
  [/\b[1-9]\d{8}(\d{2})\b/g, '*********$1'],
  // Cookie / authorization headers
  [/(cookie|set-cookie|authorization|x-csrf-token)\s*[:=]\s*[^\n;]+/gi, '$1: [redacted]'],
  // session id cookie value (our PHP session name is "optiflow")
  [/(optiflow|PHPSESSID|ASP\.NET_SessionId|JSESSIONID)=([^;\s]+)/gi, '$1=[redacted]'],
  // query-string secrets
  [/([?&](?:token|anahtar|csrf|password|sifre|parola)=)[^&\s#]+/gi, '$1[redacted]'],
  // Turkish mobile phone numbers
  [/\b0?5\d{2}[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}\b/g, '[telefon]'],
  // «…» bridge markers: whatever is inside is form data from Medula
  [/«[^»]*»/g, '«…»'],
];

export function redact(input: string): string {
  let out = input;
  for (const [re, rep] of RULES) out = out.replace(re, rep);
  return out;
}

/** For URLs: keep origin + path, drop query and fragment (Medula query strings can carry ids). */
export function redactUrl(raw: string | undefined | null): string {
  if (!raw) return '';
  try {
    const u = new URL(raw);
    return `${u.origin}${u.pathname}${u.search ? '?…' : ''}`;
  } catch {
    return '[invalid-url]';
  }
}

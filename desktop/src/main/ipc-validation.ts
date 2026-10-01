/**
 * Payload validation for every IPC message the main process accepts (spec §12).
 * Pure functions, unit-tested. Anything unexpected → null → message ignored.
 */
import { SHELL_COMMANDS } from '../shared/constants';
import type { MedulaExtraction, MedulaProbe } from '../shared/types';

export type ShellCommand = (typeof SHELL_COMMANDS)[number];

export function parseShellCommand(v: unknown): ShellCommand | null {
  return typeof v === 'string' && (SHELL_COMMANDS as readonly string[]).includes(v) ? (v as ShellCommand) : null;
}

const isObj = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v);
const isNum = (v: unknown): v is number => typeof v === 'number' && Number.isFinite(v);
const isBool = (v: unknown): v is boolean => typeof v === 'boolean';
const isStr = (v: unknown, max: number): v is string => typeof v === 'string' && v.length <= max;

export interface MedulaProbeReply {
  id: string;
  kind: 'probe';
  ok: true;
  probe: MedulaProbe;
}
export interface MedulaExtractReply {
  id: string;
  kind: 'extract';
  ok: true;
  extraction: MedulaExtraction;
}
export interface MedulaListReply {
  id: string;
  kind: 'liste';
  ok: true;
  numaralar: string[];
}
export interface MedulaErrorReply {
  id: string;
  kind: 'probe' | 'extract' | 'liste';
  ok: false;
  error: string;
}
export type MedulaReply = MedulaProbeReply | MedulaExtractReply | MedulaListReply | MedulaErrorReply;

/** e-Reçete numara listesi: en çok 1000 öğe; her biri 4–12 büyük harf/rakam ve en az bir rakam. */
export function parseNumaralar(v: unknown): string[] | null {
  if (!Array.isArray(v) || v.length > 1000) return null;
  const out: string[] = [];
  for (const x of v) {
    if (typeof x !== 'string' || !/^[0-9A-Z]{4,12}$/.test(x) || !/\d/.test(x)) return null;
    if (!out.includes(x)) out.push(x);
  }
  return out;
}

export function parseProbe(v: unknown): MedulaProbe | null {
  if (!isObj(v)) return null;
  if (!isBool(v.detectedPrescription) || !isNum(v.score) || !isNum(v.extractedFieldCount) || !isBool(v.looksLikeLogin) || !isNum(v.textLength)) return null;
  return {
    detectedPrescription: v.detectedPrescription,
    score: Math.max(0, Math.min(100, Math.round(v.score))),
    extractedFieldCount: Math.max(0, Math.round(v.extractedFieldCount)),
    looksLikeLogin: v.looksLikeLogin,
    textLength: Math.max(0, Math.round(v.textLength)),
  };
}

/** Hard upper bound for text coming out of a frame (before byte truncation for the server). */
const MAX_FRAME_TEXT_CHARS = 1_000_000;

export function parseExtraction(v: unknown): MedulaExtraction | null {
  if (!isObj(v)) return null;
  if (!isStr(v.text, MAX_FRAME_TEXT_CHARS) || !isStr(v.title, 2000) || !isStr(v.url, 8192) || !isStr(v.timestamp, 64)) return null;
  if (v.frameUrl !== undefined && !isStr(v.frameUrl, 8192)) return null;
  if (!isBool(v.detectedPrescription) || !isNum(v.extractedFieldCount)) return null;
  return {
    text: v.text,
    title: v.title,
    url: v.url,
    frameUrl: typeof v.frameUrl === 'string' ? v.frameUrl : undefined,
    detectedPrescription: v.detectedPrescription,
    extractedFieldCount: Math.max(0, Math.round(v.extractedFieldCount)),
    timestamp: v.timestamp,
  };
}

export function parseMedulaReply(v: unknown): MedulaReply | null {
  if (!isObj(v) || !isStr(v.id, 64) || v.id.length < 8) return null;
  if (v.kind !== 'probe' && v.kind !== 'extract' && v.kind !== 'liste') return null;
  if (v.ok === false) {
    return { id: v.id, kind: v.kind, ok: false, error: isStr(v.error, 200) ? v.error : 'error' };
  }
  if (v.ok !== true) return null;
  if (v.kind === 'probe') {
    const probe = parseProbe(v.probe);
    return probe ? { id: v.id, kind: 'probe', ok: true, probe } : null;
  }
  if (v.kind === 'liste') {
    const numaralar = parseNumaralar(v.numaralar);
    return numaralar ? { id: v.id, kind: 'liste', ok: true, numaralar } : null;
  }
  const extraction = parseExtraction(v.extraction);
  return extraction ? { id: v.id, kind: 'extract', ok: true, extraction } : null;
}

/** Remove control characters except TAB and LF; normalise CRLF. */
export function sanitizeText(s: string): string {
  // eslint-disable-next-line no-control-regex
  return s.replace(/\r\n?/g, '\n').replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '');
}

/** Truncate a string so its UTF-8 encoding is at most maxBytes, without splitting a code point. */
export function truncateUtf8(s: string, maxBytes: number): string {
  const enc = new TextEncoder();
  if (enc.encode(s).length <= maxBytes) return s;
  let lo = 0;
  let hi = s.length;
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2);
    if (enc.encode(s.slice(0, mid)).length <= maxBytes) lo = mid;
    else hi = mid - 1;
  }
  let out = s.slice(0, lo);
  // don't leave a lone high surrogate
  if (/[\uD800-\uDBFF]$/.test(out)) out = out.slice(0, -1);
  return out;
}

/**
 * Prescription-screen detection (spec §34). Returns SIGNALS ONLY – never page content.
 * Used to (a) pick the right frame, (b) enable/label the transfer button,
 * (c) refuse extraction on non-prescription pages instead of guessing.
 */
import type { ExtractorWindow, MedulaProbe } from './extractor-types';
import { isCountedField, isVisibleField } from './fields';

/** Same folding idea as sgk_norm() in app/sgk.php. */
export function foldTr(s: string): string {
  return s
    .replace(/[İıIi]/g, 'I')
    .replace(/[Şş]/g, 'S')
    .replace(/[Ğğ]/g, 'G')
    .replace(/[Üü]/g, 'U')
    .replace(/[Öö]/g, 'O')
    .replace(/[Çç]/g, 'C')
    .replace(/[Ââ]/g, 'A')
    .toUpperCase()
    .replace(/[^A-Z0-9+/?-]+/g, ' ')
    .replace(/\s{2,}/g, ' ');
}

const MAX_SCAN_CHARS = 400_000;

interface Signal {
  re: RegExp;
  weight: number;
  key: string;
}
const SIGNALS: Signal[] = [
  { key: 'eye', re: /\bSAG (CAM|GOZ)\b|\bSOL (CAM|GOZ)\b/, weight: 30 },
  { key: 'sph', re: /\bSFER/, weight: 20 },
  { key: 'cyl', re: /\bSIL[IE]N?D[IE]?R[IE]K\b|\bCYL\b/, weight: 10 },
  { key: 'axis', re: /\bAKS\b|\bEKSEN\b|\bAXIS\b/, weight: 10 },
  { key: 'erecete', re: /\bE ?-? ?RECETE NO\b/, weight: 10 },
  { key: 'date', re: /\bRECETE TARIHI\b/, weight: 10 },
  { key: 'tc', re: /\bT ?C KIMLIK NO\b/, weight: 10 },
];

/**
 * Text nodes joined with spaces (textContent would glue adjacent table cells:
 * "No12345678901"). Script/style/option subtrees are skipped, like the extractor.
 */
function textForProbe(doc: Document, win: ExtractorWindow): string {
  const NF = win.NodeFilter;
  const out: string[] = [];
  let len = 0;
  const walker = doc.createTreeWalker(doc.body, NF.SHOW_TEXT, {
    acceptNode(n: Node): number {
      const p = n.parentElement ? n.parentElement.tagName.toLowerCase() : '';
      if (p === 'script' || p === 'style' || p === 'noscript' || p === 'option' || p === 'textarea') return NF.FILTER_REJECT;
      return NF.FILTER_ACCEPT;
    },
  });
  let n: Node | null;
  while ((n = walker.nextNode()) && len < MAX_SCAN_CHARS) {
    const v = n.nodeValue ?? '';
    out.push(v);
    len += v.length + 1;
  }
  return out.join(' ').slice(0, MAX_SCAN_CHARS);
}

export function probeDocument(doc: Document, win: ExtractorWindow): MedulaProbe {
  const body = doc.body;
  if (!body) return { detectedPrescription: false, score: 0, extractedFieldCount: 0, looksLikeLogin: false, textLength: 0 };

  const raw = textForProbe(doc, win);
  const folded = foldTr(raw);
  const hits = new Set<string>();
  let score = 0;
  for (const s of SIGNALS) {
    if (s.re.test(folded)) {
      score += s.weight;
      hits.add(s.key);
    }
  }

  let fieldCount = 0;
  let looksLikeLogin = false;
  const fields = body.querySelectorAll('input, select, textarea');
  for (const el of Array.from(fields)) {
    const t = ((el as HTMLInputElement).type || '').toLowerCase();
    if (t === 'password') {
      // Only the TYPE is inspected; the value of a password field is never read.
      const st = win.getComputedStyle(el);
      if (st && st.display !== 'none' && st.visibility !== 'hidden') looksLikeLogin = true;
      continue;
    }
    if (isCountedField(el) && isVisibleField(el, win)) fieldCount++;
  }

  const detectedPrescription = !looksLikeLogin && hits.has('eye') && hits.has('sph') && score >= 60;
  return { detectedPrescription, score, extractedFieldCount: fieldCount, looksLikeLogin, textLength: raw.trim().length };
}

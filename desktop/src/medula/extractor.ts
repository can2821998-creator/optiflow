/* ==========================================================================
   Medula extractor — TypeScript port of kopru-eklenti/icerik.js (v1.1.0).

   WHY NOT innerText: on the Medula Optik prescription screen the sphere /
   cylinder / axis values live INSIDE <input>/<select> elements, so copying
   the page (or innerText) loses them. We walk the DOM in document order and
   insert each field's value as «value», cells as TAB, rows/blocks as NEWLINE:

       SAĞ CAM  Cam «1» +/- «-» Sferik «1,50» +/- «-» Silendirik «0,75» Aks «90»

   The PHP parser (app/sgk.php → sgk_parse) is built around exactly this
   format, so this port must stay BYTE-COMPATIBLE with the legacy script.
   tests/legacy-parity.test.ts runs both on the same fixtures and diffs them.

   Runs ONLY inside the Medula frame's isolated world (see medula-preload.ts),
   only when the main process asks, only after an explicit user action.
   Never reads password/hidden fields. Never sends anything by itself.
   ========================================================================== */
import type { ExtractorWindow, MedulaExtraction } from './extractor-types';
import { probeDocument } from './detector';
import { fieldValue, isCountedField, isVisibleField } from './fields';

export { fieldValue, isCountedField, isVisibleField };

const BLOCK_TAGS = new Set(['tr', 'br', 'div', 'p', 'table', 'li']);
const SKIP_SUBTREE = new Set(['script', 'style', 'noscript', 'option', 'optgroup', 'datalist']);

/** Legacy `sayfaMetni`, plus a count of emitted fields for diagnostics. */
export function pageText(doc: Document, win: ExtractorWindow): { text: string; fieldCount: number } {
  const parts: string[] = [];
  let fieldCount = 0;
  const NF = win.NodeFilter;
  const N = win.Node;
  if (!doc.body) return { text: '', fieldCount: 0 };

  const walker = doc.createTreeWalker(doc.body, NF.SHOW_TEXT | NF.SHOW_ELEMENT, {
    acceptNode(d: Node): number {
      if (d.nodeType === N.TEXT_NODE) {
        const parent = d.parentElement ? d.parentElement.tagName.toLowerCase() : '';
        if (parent === 'textarea') return NF.FILTER_REJECT; // value comes from the textarea itself as «…»
        return d.nodeValue && d.nodeValue.trim() ? NF.FILTER_ACCEPT : NF.FILTER_REJECT;
      }
      const tag = (d as Element).tagName ? (d as Element).tagName.toLowerCase() : '';
      if (SKIP_SUBTREE.has(tag)) return NF.FILTER_REJECT;
      if (tag === 'input' || tag === 'select' || tag === 'textarea') return NF.FILTER_ACCEPT;
      if (tag === 'td' || tag === 'th') return NF.FILTER_ACCEPT; // cell separator (TAB)
      if (BLOCK_TAGS.has(tag)) return NF.FILTER_ACCEPT; // line break marker
      return NF.FILTER_SKIP;
    },
  });

  let d: Node | null;
  while ((d = walker.nextNode())) {
    if (d.nodeType === N.TEXT_NODE) {
      parts.push((d.nodeValue ?? '').replace(/\s+/g, ' ').trim());
      continue;
    }
    const el = d as Element;
    const tag = el.tagName.toLowerCase();
    if (tag === 'input' || tag === 'select' || tag === 'textarea') {
      if (!isVisibleField(el, win) || !isCountedField(el)) continue;
      // Empty fields are emitted as «» so column alignment survives
      // (Cam · +/- · Sferik · +/- · Silendirik · Aks depends on it).
      parts.push('«' + fieldValue(el) + '»');
      fieldCount++;
      continue;
    }
    if (tag === 'td' || tag === 'th') {
      parts.push('\t');
      continue;
    }
    parts.push('\n');
  }

  const text = parts
    .join(' ')
    .replace(/[ ]*\t[ ]*/g, '\t')
    .replace(/[ \t]*\n[ \t]*/g, '\n')
    .replace(/\t{2,}/g, '\t')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/[ ]{2,}/g, ' ')
    .trim();
  return { text, fieldCount };
}

/**
 * Full extraction for one frame (legacy click handler, minus the chrome.* transport).
 * Includes the legacy "prepend a long user selection that is not already in the text" rule.
 */
export function extractFrame(doc: Document, win: ExtractorWindow, now: Date = new Date()): MedulaExtraction {
  let text: string;
  let fieldCount = 0;
  try {
    const r = pageText(doc, win);
    text = r.text;
    fieldCount = r.fieldCount;
  } catch {
    // Legacy fallback used innerText; textContent is the closest thing available everywhere.
    text = doc.body ? (doc.body as HTMLElement).innerText ?? doc.body.textContent ?? '' : '';
  }
  const selected = String(win.getSelection ? win.getSelection() : '').trim();
  if (selected.length > 80 && text.indexOf(selected.slice(0, 40)) === -1) {
    text = selected + '\n' + text;
  }
  const probe = probeDocument(doc, win);
  return {
    text,
    title: doc.title || 'Medula Optik',
    url: win.top === win ? String(win.location.href) : '',
    frameUrl: String(win.location.href),
    detectedPrescription: probe.detectedPrescription,
    extractedFieldCount: fieldCount,
    timestamp: now.toISOString(),
  };
}

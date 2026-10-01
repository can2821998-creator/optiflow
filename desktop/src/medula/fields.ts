/** Field helpers shared by extractor.ts and detector.ts (ported 1:1 from kopru-eklenti/icerik.js). */
import type { ExtractorWindow } from './extractor-types';

/** Legacy `gorunur`: rendered, not hidden, not a password field. */
export function isVisibleField(el: Element, win: ExtractorWindow): boolean {
  const anyEl = el as HTMLInputElement;
  if (!el || typeof (el as HTMLElement).getBoundingClientRect !== 'function') return false;
  if (anyEl.type === 'hidden' || anyEl.type === 'password') return false;
  const st = win.getComputedStyle(el);
  if (!st || st.display === 'none' || st.visibility === 'hidden') return false;
  return true;
}

/** Legacy `kutuDegeri`: the value a human sees in the field. */
export function fieldValue(el: Element): string {
  const tag = el.tagName.toLowerCase();
  if (tag === 'select') {
    const s = el as HTMLSelectElement;
    const o = s.options && s.options[s.selectedIndex];
    let v = o ? o.text || o.value : '';
    v = String(v).trim();
    return v === '' || /^se[çc]iniz$/i.test(v) ? '' : v;
  }
  const inp = el as HTMLInputElement;
  if (inp.type === 'checkbox' || inp.type === 'radio') {
    return inp.checked ? (inp.value && inp.value !== 'on' ? inp.value : 'evet') : '';
  }
  const v = (el as HTMLInputElement | HTMLTextAreaElement).value;
  return String(v == null ? '' : v).trim();
}

/** Legacy `kutuSayilirMi`: buttons, files, hidden and password fields never enter the text. */
export function isCountedField(el: Element): boolean {
  const tag = el.tagName.toLowerCase();
  if (tag !== 'input') return true;
  const t = ((el as HTMLInputElement).type || 'text').toLowerCase();
  return ['button', 'submit', 'reset', 'image', 'file', 'hidden', 'password'].indexOf(t) === -1;
}

/**
 * 5.6.0 — Tezgâh: OptiFlow'da yazılan e-reçete numarasını Medula'nın reçete sorgu ekranına yazar.
 *
 *  - Yalnızca personel OptiFlow'un Tezgâh sayfasında "Medula'da aç"a bastığında, ana süreç bekleyen
 *    numarayı Medula çerçevelerine gönderir; ekranda "e-Reçete No" / "Reçete No" etiketli boş ya da
 *    farklı değerli bir yazı kutusu varsa numara yazılır.
 *  - Kutunun yanındaki "Sorgula / Getir / Ara / Listele" düğmesi bulunursa ona basılır; bulunamazsa
 *    imleç kutuya konur ve personel Enter'a basar. Reçeteyi aktarmak yine personelin işidir
 *    (Medula'da kontrol eder, "Reçeteyi aktar"a basar).
 *  - Sayfadan hiçbir şey okunup dışarı verilmez; yalnızca sonuç türü döner.
 */
import type { ExtractorWindow } from './extractor-types';
import { foldTr } from './detector';
import { degerYaz } from './giris';

export type SorguSonucu = 'sorgulandi' | 'yazildi' | 'alan-yok';

const METIN_TIPLERI = new Set(['', 'text', 'search', 'tel', 'number']);
const ALAN_RE = /\bE ?-? ?RECETE\b|\bRECETE ?(NO|NUMARA|NUMARASI|KODU)\b|ERECETE|RECETENO/;
const ALAN_DEGIL_RE = /\bT ?C\b|KIMLIK|TARIH|TESIS|DOKTOR|HEKIM|BARKOD|RAPOR/;
const DUGME_RE = /\b(SORGULA|SORGU|GETIR|ARA|BUL|LISTELE|GORUNTULE)\b/;

function gorunur(el: Element, win: ExtractorWindow): boolean {
  const i = el as HTMLInputElement;
  if (i.disabled || i.readOnly) return false;
  const st = win.getComputedStyle(el);
  return !!st && st.display !== 'none' && st.visibility !== 'hidden';
}

function metin(el: Element | null | undefined): string {
  return el ? (el.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 200) : '';
}

const katla = (s: string): string =>
  // camelCase ve alt çizgili adlar da (eReceteNo, recete_no) sözcüklere ayrılsın
  foldTr(s.replace(/([a-zçğıöşü])([A-ZÇĞİÖŞÜ])/g, '$1 $2').replace(/[_:.]/g, ' '));

/**
 * Kutunun etiket adayları, yakından uzağa: (1) kendi öznitelikleri + <label>, (2) hemen önündeki metin,
 * (3) aynı satırda önceki hücre. JSF sayfaları tablo düzenlidir; uzaktaki etiketler (aynı satırdaki
 * 'T.C. Kimlik No') yakındakini ezmesin diye ilk dolu aday kullanılır.
 */
function etiketler(el: HTMLInputElement, doc: Document): string[] {
  const oz = [el.name, el.id, el.getAttribute('placeholder') ?? '', el.getAttribute('aria-label') ?? '', el.getAttribute('title') ?? ''];
  if (el.id) {
    for (const l of Array.from(doc.querySelectorAll('label'))) if (l.htmlFor === el.id) oz.push(metin(l));
  }
  oz.push(metin(el.closest('label')));
  let once = '';
  for (let n = el.previousSibling, i = 0; n && i < 3 && !once; n = n.previousSibling, i++) {
    once = n.nodeType === 3 ? (n.nodeValue ?? '').trim() : n.nodeType === 1 && (n as Element).tagName !== 'INPUT' ? metin(n as Element) : '';
  }
  let hucre = '';
  const td = el.closest('td, th');
  for (let h = td?.previousElementSibling ?? null, i = 0; h && i < 2 && !hucre; h = h.previousElementSibling, i++) hucre = metin(h);
  return [katla(oz.join(' ')), katla(once), katla(hucre)];
}

function receteNoMu(el: HTMLInputElement, doc: Document): boolean {
  for (const e of etiketler(el, doc)) {
    if (ALAN_RE.test(e)) return !ALAN_DEGIL_RE.test(e);
    if (ALAN_DEGIL_RE.test(e)) return false;
  }
  return false;
}

/** Reçete numarası kutusu: en yakın etiketi e-reçete/reçete no olan ilk görünür yazı kutusu. */
export function receteNoAlani(doc: Document, win: ExtractorWindow): HTMLInputElement | null {
  const kutular = Array.from(doc.querySelectorAll('input')).filter(
    (el): el is HTMLInputElement => METIN_TIPLERI.has((el.type || '').toLowerCase()) && gorunur(el, win),
  );
  return kutular.find((k) => receteNoMu(k, doc)) ?? null;
}

function dugmeMetni(el: Element): string {
  const i = el as HTMLInputElement;
  return foldTr([metin(el), i.value ?? '', el.getAttribute('title') ?? '', el.getAttribute('alt') ?? ''].join(' '));
}

/** Kutudan sonra gelen, aynı formdaki (form yoksa yakın kapsayıcıdaki) ilk sorgu düğmesi. */
export function sorguDugmesi(kutu: HTMLInputElement): HTMLElement | null {
  let kapsam: Element | null = kutu.form;
  if (!kapsam) {
    kapsam = kutu.parentElement;
    for (let i = 0; kapsam && i < 4 && !kapsam.querySelector('button, input[type=submit], input[type=button], input[type=image]'); i++) {
      kapsam = kapsam.parentElement;
    }
  }
  if (!kapsam) return null;
  const adaylar = Array.from(kapsam.querySelectorAll('button, input[type=submit], input[type=button], input[type=image], a')).filter(
    (el) => !(el as HTMLButtonElement).disabled && DUGME_RE.test(dugmeMetni(el)),
  ) as HTMLElement[];
  const sonra = adaylar.filter((el) => !!(kutu.compareDocumentPosition(el) & 4 /* FOLLOWING */));
  return sonra[0] ?? adaylar[0] ?? null;
}

/** Numarayı yazar; düğme bulunursa basar. Numara geçersizse hiçbir şey yapmaz. */
export function receteSorgula(doc: Document, win: ExtractorWindow, no: string): SorguSonucu {
  if (!/^[0-9A-Z]{4,12}$/.test(no)) return 'alan-yok';
  const kutu = receteNoAlani(doc, win);
  if (!kutu) return 'alan-yok';
  degerYaz(kutu, no, win);
  try {
    kutu.focus();
  } catch {
    /* odak verilemezse önemli değil */
  }
  const dugme = sorguDugmesi(kutu);
  if (!dugme) return 'yazildi';
  dugme.click();
  return 'sorgulandi';
}

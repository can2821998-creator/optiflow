/**
 * 5.4.0 — Medula giriş ekranı: kayıtlı bilgiyle doldurma ve (kullanıcı kabul ederse) kaydetme.
 *
 * Chrome'un şifre yöneticisi gibi çalışır:
 *  - Doldurma: kullanıcı adı ve şifre alanları BOŞSA kayıtlı bilgiyle doldurulur. Güvenlik kodu
 *    (resimdeki kod) asla doldurulmaz; imleç o alana konur, kodu kullanıcı yazar ve "Giriş"e basar.
 *  - Yakalama: kullanıcı "Giriş"e bastığı (form gönderildiği / Enter'a basıldığı) AN alanlardaki
 *    değerler okunur ve yalnızca ana sürece (main) gider. Ana süreç giriş başarılı olursa kullanıcıya
 *    "kaydedilsin mi?" diye sorar; cevap "Kaydet" değilse bilgi bellekten atılır.
 *  - Şifre değiştirme ekranı (birden çok şifre alanı): yeni şifre iki kez aynı yazıldıysa yakalanır;
 *    kayıt varsa "güncellensin mi?" sorulur.
 *
 * Bu dosyadaki kod Medula sayfasına HİÇBİR ŞEY açmaz; yalnızca Medula ön yüklemesinin yalıtılmış
 * dünyasında, izinli SGK adreslerinde çalışır (medula-preload.ts denetler).
 */
import type { ExtractorWindow } from './extractor-types';

export interface GirisBilgisi {
  kullanici: string;
  sifre: string;
}

export interface GirisAlanlari {
  kullanici: HTMLInputElement | null;
  sifre: HTMLInputElement;
  kod: HTMLInputElement | null;
}

export type DoldurmaSonucu = 'doldu' | 'zaten-dolu' | 'alan-yok';

const METIN_TIPLERI = new Set(['', 'text', 'email', 'tel', 'number', 'search']);
const KULLANICI_RE = /kullan|user|login|giris|tc|kimlik|kodu?ad/i;
const KOD_RE = /captcha|guvenlik|güvenlik|dogrula|doğrula|kod|code|resim|image|j_captcha/i;

function gorunur(el: Element, win: ExtractorWindow): boolean {
  const i = el as HTMLInputElement;
  if (i.disabled || i.readOnly) return false;
  const st = win.getComputedStyle(el);
  return !!st && st.display !== 'none' && st.visibility !== 'hidden';
}

function ipucu(el: HTMLInputElement): string {
  return [el.name, el.id, el.getAttribute('placeholder') ?? '', el.getAttribute('aria-label') ?? '', el.getAttribute('title') ?? ''].join(' ');
}

/** Görünür şifre alanları (sayfa sırasıyla). */
export function sifreAlanlari(doc: Document, win: ExtractorWindow): HTMLInputElement[] {
  return Array.from(doc.querySelectorAll('input')).filter(
    (el): el is HTMLInputElement => (el.type || '').toLowerCase() === 'password' && gorunur(el, win),
  );
}

/** Tek şifre alanlı giriş formu: kullanıcı adı (şifreden önceki metin alanı) ve güvenlik kodu (sonraki). */
export function girisAlanlari(doc: Document, win: ExtractorWindow): GirisAlanlari | null {
  const sifreler = sifreAlanlari(doc, win);
  if (sifreler.length !== 1) return null;
  const sifre = sifreler[0]!;
  const kapsam: ParentNode = sifre.form ?? doc;
  const metinler = Array.from(kapsam.querySelectorAll('input')).filter(
    (el): el is HTMLInputElement => METIN_TIPLERI.has((el.type || '').toLowerCase()) && gorunur(el, win),
  );
  const once = metinler.filter((el) => !!(el.compareDocumentPosition(sifre) & 4 /* FOLLOWING */));
  const sonra = metinler.filter((el) => !!(el.compareDocumentPosition(sifre) & 2 /* PRECEDING */));
  const kullanici = [...once].reverse().find((el) => KULLANICI_RE.test(ipucu(el))) ?? once[once.length - 1] ?? null;
  const kod = sonra.find((el) => KOD_RE.test(ipucu(el))) ?? sonra[0] ?? null;
  return { kullanici, sifre, kod };
}

/** Sayfanın kendi dinleyicileri de (JSF/jQuery) değişikliği görsün diye yerel setter + olaylar. */
export function degerYaz(el: HTMLInputElement, deger: string, win: ExtractorWindow): void {
  const proto = (win as unknown as { HTMLInputElement: typeof HTMLInputElement }).HTMLInputElement.prototype;
  const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
  if (setter) setter.call(el, deger);
  else el.value = deger;
  const Ev = (win as unknown as { Event: typeof Event }).Event;
  el.dispatchEvent(new Ev('input', { bubbles: true }));
  el.dispatchEvent(new Ev('change', { bubbles: true }));
}

/**
 * Kayıtlı bilgiyle doldurur. Kullanıcı bir şey yazmışsa ÜZERİNE YAZMAZ.
 * Güvenlik kodu alanı doldurulmaz; imleç oraya konur.
 */
export function girisDoldur(doc: Document, win: ExtractorWindow, k: GirisBilgisi): DoldurmaSonucu {
  const a = girisAlanlari(doc, win);
  if (!a || !a.kullanici) return 'alan-yok';
  const kul = a.kullanici.value.trim();
  if ((kul !== '' && kul !== k.kullanici) || a.sifre.value !== '') return 'zaten-dolu';
  if (kul === '') degerYaz(a.kullanici, k.kullanici, win);
  degerYaz(a.sifre, k.sifre, win);
  const odak = a.kod && a.kod.value === '' ? a.kod : a.sifre;
  try {
    odak.focus();
  } catch {
    /* odak verilemezse önemli değil */
  }
  return 'doldu';
}

export interface Yakalanan extends GirisBilgisi {
  /** true: şifre değiştirme ekranından (kullanıcı adı bilinmiyor olabilir). */
  degisim: boolean;
}

/** O an alanlarda yazılı olanı okur (yalnızca kullanıcı "Giriş"e bastığında çağrılır). */
export function girisOku(doc: Document, win: ExtractorWindow): Yakalanan | null {
  const sifreler = sifreAlanlari(doc, win);
  if (sifreler.length === 1) {
    const a = girisAlanlari(doc, win);
    if (!a || !a.kullanici) return null;
    const kullanici = a.kullanici.value.trim();
    const sifre = a.sifre.value;
    return kullanici && sifre ? { kullanici, sifre, degisim: false } : null;
  }
  if (sifreler.length >= 2) {
    // Şifre değiştirme: son iki alan "yeni şifre" + "yeni şifre (tekrar)".
    const yeni = sifreler[sifreler.length - 1]!.value;
    const tekrar = sifreler[sifreler.length - 2]!.value;
    if (!yeni || yeni !== tekrar) return null;
    const kulAlani = girisAlanlari(doc, win)?.kullanici;
    return { kullanici: kulAlani?.value.trim() ?? '', sifre: yeni, degisim: true };
  }
  return null;
}

/**
 * Giriş / şifre değiştirme formunun gönderilmesini izler. Okuma YALNIZCA kullanıcı eylemiyle olur:
 * form gönderimi, bir düğmeye tıklama ya da bir alanda Enter. Kaldırma fonksiyonu döner.
 */
export function girisIzle(doc: Document, win: ExtractorWindow, gonder: (y: Yakalanan) => void): () => void {
  let son = '';
  const oku = () => {
    const y = girisOku(doc, win);
    if (!y) return;
    const imza = `${y.degisim ? 1 : 0}\u0000${y.kullanici}\u0000${y.sifre}`;
    if (imza === son) return;
    son = imza;
    gonder(y);
  };
  const onSubmit = () => oku();
  const onClick = (e: Event) => {
    const t = e.target as Element | null;
    const btn = t && typeof t.closest === 'function' ? t.closest('button, input[type=submit], input[type=image], input[type=button], a') : null;
    if (btn) oku();
  };
  const onKey = (e: Event) => {
    const k = e as KeyboardEvent;
    const t = k.target as HTMLElement | null;
    if (k.key === 'Enter' && t && t.tagName === 'INPUT') oku();
  };
  doc.addEventListener('submit', onSubmit, true);
  doc.addEventListener('click', onClick, true);
  doc.addEventListener('keydown', onKey, true);
  return () => {
    doc.removeEventListener('submit', onSubmit, true);
    doc.removeEventListener('click', onClick, true);
    doc.removeEventListener('keydown', onKey, true);
  };
}

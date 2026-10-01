/**
 * Medula reçete LİSTESİ ekranından yalnızca e-reçete NUMARALARINI çıkarır (4.12.0).
 *
 * Gizlilik: hasta adı, T.C. kimlik no, tarih, tutar gibi hiçbir içerik frame dışına
 * çıkmaz. Yalnızca e-reçete biçimine uyan kodlar (4–12 karakter, en az bir harf ve
 * bir rakam — ör. "1A2B3C4") döner. Önce tablo başlığında "Reçete No" / "e-Reçete"
 * sütunu aranır; bulunamazsa sayfa metninde 7 karakterlik harf+rakam kodlar aranır.
 */

const HEADER_RE = /(E\s*-?\s*RE[ÇC]ETE|RE[ÇC]ETE\s*(NO|NUMARASI|NUMARA))/i;
const TOKEN_RE = /^[0-9A-Z]{4,12}$/;
const MAX = 1000;

function isCode(s: string): boolean {
  return TOKEN_RE.test(s) && /\d/.test(s) && /[A-Z]/.test(s);
}

function cellText(el: Element): string {
  return (el.textContent ?? '').replace(/\s+/g, ' ').trim().toUpperCase();
}

/** Tablolardaki "Reçete No" sütunu. */
function fromTables(doc: Document, out: Set<string>): void {
  for (const table of Array.from(doc.querySelectorAll('table')).slice(0, 50)) {
    const rows = Array.from(table.querySelectorAll('tr'));
    let col = -1;
    let headerRow = -1;
    for (let r = 0; r < Math.min(rows.length, 5) && col < 0; r++) {
      const cells = Array.from(rows[r]!.children).filter((c) => c.tagName === 'TD' || c.tagName === 'TH');
      cells.forEach((c, i) => {
        if (col < 0 && HEADER_RE.test(cellText(c))) {
          col = i;
          headerRow = r;
        }
      });
    }
    if (col < 0) continue;
    for (const row of rows.slice(headerRow + 1)) {
      const cells = Array.from(row.children).filter((c) => c.tagName === 'TD' || c.tagName === 'TH');
      const v = cells[col] ? cellText(cells[col]!) : '';
      for (const t of v.split(/[\s/,;]+/)) {
        if (isCode(t)) out.add(t);
        if (out.size >= MAX) return;
      }
    }
  }
}

/** Yedek: sayfa metninde 7 karakterlik harf+rakam kodlar. */
function fromText(doc: Document, out: Set<string>): void {
  const text = (doc.body?.innerText ?? doc.body?.textContent ?? '').slice(0, 400_000);
  const re = /(?:^|[^0-9A-Za-zÇĞİÖŞÜçğıöşü])([0-9A-Z]{7})(?=$|[^0-9A-Za-zÇĞİÖŞÜçğıöşü])/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) && out.size < MAX) {
    if (isCode(m[1]!)) out.add(m[1]!);
  }
}

export function listeNumaralari(doc: Document): string[] {
  const out = new Set<string>();
  fromTables(doc, out);
  if (out.size === 0) fromText(doc, out);
  return Array.from(out).slice(0, MAX);
}

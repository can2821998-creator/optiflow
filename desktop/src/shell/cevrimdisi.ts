/* 4.12.0 — Çevrimdışı kopya sayfası. Tüm içerik textContent ile yazılır (HTML yorumlanmaz). */
import type { OfflineSnapshot } from '../shared/types';

declare global {
  interface Window {
    optiflowCevrimdisi?: { al: () => Promise<unknown> };
  }
}

const $ = (id: string) => document.getElementById(id)!;

function hucre(tr: HTMLTableRowElement, ana: string, alt = '', sinif = ''): void {
  const td = document.createElement('td');
  if (sinif) td.className = sinif;
  td.textContent = ana;
  if (alt) {
    const s = document.createElement('small');
    s.textContent = alt;
    td.appendChild(s);
  }
  tr.appendChild(td);
}

function norm(s: string): string {
  return s.toLocaleLowerCase('tr-TR').replace(/\s+/g, '');
}

async function basla(): Promise<void> {
  const veri = (await window.optiflowCevrimdisi?.al()) as OfflineSnapshot | null;
  if (!veri) {
    $('bilgi').textContent = 'Kayıtlı kopya yok.';
    ($('bos') as HTMLElement).hidden = false;
    return;
  }
  const zaman = new Date(veri.olusturma);
  $('bilgi').textContent = `${veri.magaza.isim} · ${veri.siparisler.length} sipariş · kopya ${isNaN(zaman.getTime()) ? '' : zaman.toLocaleString('tr-TR')}`;
  const tbody = $('liste');
  const ciz = (q: string) => {
    tbody.textContent = '';
    const n = norm(q);
    let say = 0;
    for (const o of veri.siparisler) {
      if (n && !norm(`${o.no} ${o.ad} ${o.tel}`).includes(n)) continue;
      const tr = document.createElement('tr');
      hucre(tr, o.no, o.tarih);
      hucre(tr, o.ad);
      hucre(tr, o.tel);
      hucre(tr, o.asama);
      hucre(tr, o.soz);
      hucre(tr, o.cam, o.cerceve);
      hucre(tr, o.kalan, '', o.kalan ? 'kalan' : '');
      tbody.appendChild(tr);
      say++;
    }
    ($('bos') as HTMLElement).hidden = say > 0;
  };
  ciz('');
  ($('ara') as HTMLInputElement).addEventListener('input', (e) => ciz((e.target as HTMLInputElement).value));
}

void basla();

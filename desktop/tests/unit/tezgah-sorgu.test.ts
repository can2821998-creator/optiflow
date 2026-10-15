import { describe, expect, it } from 'vitest';
import { receteNoAlani, receteSorgula, sorguDugmesi } from '../../src/medula/sorgu';
import { parseMedulaReply, parseReceteNo } from '../../src/main/ipc-validation';
import { incomingUrl } from '../../src/main/bridge/origin-policy';
import { fixtureDom, htmlDom, win } from '../helpers';
import type { DesktopConfig } from '../../src/shared/types';

const doc = (html: string) => htmlDom(html);

describe('5.6.0 Tezgâh — reçete no kutusu', () => {
  it('tablo düzeninde (JSF) önceki hücredeki etiketten bulur; aynı satırdaki T.C. kutusunu atlar', () => {
    const d = doc(`<form id="f"><table><tr>
      <td>T.C. Kimlik No</td><td><input id="f:j_idt12" type="text"></td>
      <td>E-Reçete No</td><td><input id="f:j_idt14" type="text"></td>
      <td><input type="submit" value="Sorgula"></td></tr></table></form>`);
    expect(receteNoAlani(d.window.document, win(d))?.id).toBe('f:j_idt14');
  });
  it('label for=, ad (eReceteNo) ve placeholder ile bulur', () => {
    const a = doc('<label for="x">Reçete Numarası</label><input id="x">');
    expect(receteNoAlani(a.window.document, win(a))?.id).toBe('x');
    const b = doc('<input name="tarih"><input name="eReceteNo">');
    expect(receteNoAlani(b.window.document, win(b))?.name).toBe('eReceteNo');
    const c = doc('<input placeholder="e-Reçete no giriniz">');
    expect(receteNoAlani(c.window.document, win(c))).not.toBeNull();
  });
  it('reçete tarihi, T.C. ve gizli/salt okunur kutuları seçmez', () => {
    const d = doc(`<span>Reçete Tarihi</span><input name="a">
      <input name="receteNo" type="hidden"><input name="receteNo2" readonly>
      <span>T.C. Kimlik No</span><input name="b">`);
    expect(receteNoAlani(d.window.document, win(d))).toBeNull();
  });
  it('reçete detay ekranında (kutu yok) ve giriş ekranında alan bulmaz', () => {
    for (const f of ['recete-uzak.html', 'giris-guvenlik-kodu.html']) {
      const d = fixtureDom(f);
      expect(receteSorgula(d.window.document, win(d), '1A2B3C4')).toBe('alan-yok');
    }
  });
});

describe('5.6.0 Tezgâh — doldurma ve sorgulama', () => {
  it('numarayı yazar (input/change olayıyla) ve kutudan sonraki "Sorgula" düğmesine basar', () => {
    const d = doc(`<form><button type="button" id="temizle">Temizle</button>
      <span>E-Reçete No:</span><input id="no"> <button type="button" id="sorgula">Sorgula</button></form>`);
    const dd = d.window.document;
    const olaylar: string[] = [];
    dd.getElementById('no')!.addEventListener('input', () => olaylar.push('input'));
    dd.getElementById('no')!.addEventListener('change', () => olaylar.push('change'));
    let basildi = 0;
    dd.getElementById('sorgula')!.addEventListener('click', () => basildi++);
    expect(receteSorgula(dd, win(d), '1A2B3C4')).toBe('sorgulandi');
    expect((dd.getElementById('no') as HTMLInputElement).value).toBe('1A2B3C4');
    expect(olaylar).toEqual(['input', 'change']);
    expect(basildi).toBe(1);
  });
  it('düğme yoksa yalnızca yazar ve imleci kutuya koyar', () => {
    const d = doc('<span>E-Reçete No</span><input id="no">');
    expect(receteSorgula(d.window.document, win(d), '7XK2M9P')).toBe('yazildi');
    expect(d.window.document.activeElement?.id).toBe('no');
  });
  it('input[type=submit] value="Getir" de sorgu düğmesi sayılır; ilgisiz düğme sayılmaz', () => {
    const d = doc('<form><span>Reçete No</span><input id="no"><input type="submit" value="Kaydet"><input type="submit" id="g" value="Getir"></form>');
    const kutu = d.window.document.getElementById('no') as HTMLInputElement;
    expect(sorguDugmesi(kutu)?.id).toBe('g');
  });
  it('geçersiz numarada sayfaya dokunmaz', () => {
    const d = doc('<span>E-Reçete No</span><input id="no">');
    expect(receteSorgula(d.window.document, win(d), '1a2b<script>')).toBe('alan-yok');
    expect((d.window.document.getElementById('no') as HTMLInputElement).value).toBe('');
  });
});

describe('5.6.0 Tezgâh — IPC doğrulama', () => {
  it('parseReceteNo: boşluk/tire atar, büyük harfe çevirir; biçim dışını reddeder', () => {
    expect(parseReceteNo(' 1a2b-3c4 ')).toBe('1A2B3C4');
    expect(parseReceteNo('ABCDEFG')).toBeNull(); // rakam yok
    expect(parseReceteNo('12')).toBeNull();
    expect(parseReceteNo('1234567890123')).toBeNull();
    expect(parseReceteNo('1A2B3C4"><')).toBeNull();
    expect(parseReceteNo(1234567)).toBeNull();
    expect(parseReceteNo('x'.repeat(100))).toBeNull();
  });
  it('sorgu yanıtı yalnızca bilinen sonuçlarla kabul edilir', () => {
    expect(parseMedulaReply({ id: 'abcdefgh-1', kind: 'sorgu', ok: true, sonuc: 'sorgulandi' })).toEqual({ id: 'abcdefgh-1', kind: 'sorgu', ok: true, sonuc: 'sorgulandi' });
    expect(parseMedulaReply({ id: 'abcdefgh-1', kind: 'sorgu', ok: true, sonuc: 'hasta: Ali' })).toBeNull();
    expect(parseMedulaReply({ id: 'abcdefgh-1', kind: 'sorgu', ok: false, error: 'origin' })).toMatchObject({ ok: false, kind: 'sorgu' });
  });
  it('incomingUrl: Tezgâh sayfası da açılabilir, başka sayfa açılamaz', () => {
    const cfg = { optiflowBaseUrl: 'https://ornek.optiflow.test/panel/' } as unknown as DesktopConfig;
    expect(incomingUrl(cfg, 5, 'tezgah.php')).toMatch(/\/tezgah\.php\?gelen=5$/);
    expect(incomingUrl(cfg, 5)).toMatch(/\/sgk-aktar\.php\?gelen=5$/);
    expect(incomingUrl(cfg, 5, 'logout.php' as 'tezgah.php')).toBeNull();
  });
});

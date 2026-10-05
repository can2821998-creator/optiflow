import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { girisAlanlari, girisDoldur, girisIzle, girisOku, type Yakalanan } from '../../src/medula/giris';
import { probeDocument } from '../../src/medula/detector';
import { extractFrame } from '../../src/medula/extractor';
import { gecerliGiris, MedulaGirisKasasi, neSorulmali } from '../../src/main/medula-giris-kasasi';
import { parseGirisYakalandi, parseMedulaReply } from '../../src/main/ipc-validation';
import { fixtureDom, htmlDom, win } from '../helpers';

const kayit = { kullanici: 'ornek.kullanici', sifre: 'Sahte-Sifre-42' };
const giris = () => fixtureDom('giris-guvenlik-kodu.html', 'https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces');
const $ = (d: ReturnType<typeof giris>, id: string) => d.window.document.getElementById(id) as HTMLInputElement;

describe('Medula giriş alanları', () => {
  it('kullanıcı adı, şifre ve güvenlik kodu alanlarını bulur', () => {
    const d = giris();
    const a = girisAlanlari(d.window.document, win(d))!;
    expect(a.kullanici?.id).toBe('loginForm:kullaniciAdi');
    expect(a.sifre.id).toBe('loginForm:sifre');
    expect(a.kod?.id).toBe('loginForm:guvenlikKodu');
  });
  it('eski test sayfasında da (tesis kodu alanlı) kullanıcı adını bulur', () => {
    const d = fixtureDom('giris.html');
    expect(girisAlanlari(d.window.document, win(d))?.kullanici?.name).toBe('kullanici');
  });
  it('giriş ekranı olmayan sayfada (reçete) alan bulmaz', () => {
    const d = fixtureDom('recete-uzak.html');
    expect(girisAlanlari(d.window.document, win(d))).toBeNull();
    expect(girisDoldur(d.window.document, win(d), kayit)).toBe('alan-yok');
  });
});

describe('doldurma', () => {
  it('boş alanları doldurur; güvenlik kodunu ve KVKK onayını ELLEMEZ; imleci güvenlik koduna koyar', () => {
    const d = giris();
    const olaylar: string[] = [];
    $(d, 'loginForm:sifre').addEventListener('input', () => olaylar.push('input'));
    $(d, 'loginForm:sifre').addEventListener('change', () => olaylar.push('change'));
    expect(girisDoldur(d.window.document, win(d), kayit)).toBe('doldu');
    expect($(d, 'loginForm:kullaniciAdi').value).toBe(kayit.kullanici);
    expect($(d, 'loginForm:sifre').value).toBe(kayit.sifre);
    expect($(d, 'loginForm:guvenlikKodu').value).toBe('');
    expect($(d, 'loginForm:kvkk').checked).toBe(false);
    expect(d.window.document.activeElement?.id).toBe('loginForm:guvenlikKodu');
    expect(olaylar).toEqual(['input', 'change']); // sayfanın kendi dinleyicileri değişikliği görür
  });
  it('kullanıcının yazdığının üzerine yazmaz', () => {
    const d = giris();
    $(d, 'loginForm:kullaniciAdi').value = 'baska.kullanici';
    expect(girisDoldur(d.window.document, win(d), kayit)).toBe('zaten-dolu');
    expect($(d, 'loginForm:kullaniciAdi').value).toBe('baska.kullanici');
    expect($(d, 'loginForm:sifre').value).toBe('');
  });
  it('form göndermez (Giriş düğmesine kullanıcı basar)', () => {
    const d = giris();
    let gonderildi = false;
    d.window.document.getElementById('loginForm')!.addEventListener('submit', (e) => {
      gonderildi = true;
      e.preventDefault();
    });
    girisDoldur(d.window.document, win(d), kayit);
    expect(gonderildi).toBe(false);
  });
  it('şifre değiştirme ekranını (çok şifre alanı) doldurmaz', () => {
    const d = fixtureDom('sifre-degistir.html');
    expect(girisDoldur(d.window.document, win(d), kayit)).toBe('alan-yok');
  });
  it('reçete okuyucu doldurulmuş şifreyi yine de METNE KATMAZ', () => {
    const d = giris();
    girisDoldur(d.window.document, win(d), kayit);
    const x = extractFrame(d.window.document, win(d));
    expect(x.text).not.toContain(kayit.sifre);
    expect(probeDocument(d.window.document, win(d)).looksLikeLogin).toBe(true);
  });
});

describe('yakalama (yalnızca kullanıcı "Giriş"e basınca)', () => {
  it('alanlar yazılırken bir şey göndermez; gönderimde bir kez gönderir', () => {
    const d = giris();
    const gelen: Yakalanan[] = [];
    const kaldir = girisIzle(d.window.document, win(d), (y) => gelen.push(y));
    $(d, 'loginForm:kullaniciAdi').value = kayit.kullanici;
    $(d, 'loginForm:sifre').value = kayit.sifre;
    $(d, 'loginForm:sifre').dispatchEvent(new d.window.Event('input', { bubbles: true }));
    expect(gelen).toHaveLength(0);
    const form = d.window.document.getElementById('loginForm') as HTMLFormElement;
    form.addEventListener('submit', (e) => e.preventDefault());
    $(d, 'loginForm:girisBtn').click(); // tıklama + submit → yine tek kayıt
    expect(gelen).toEqual([{ ...kayit, degisim: false }]);
    kaldir();
  });
  it('Enter ile gönderimi de yakalar', () => {
    const d = giris();
    const gelen: Yakalanan[] = [];
    girisIzle(d.window.document, win(d), (y) => gelen.push(y));
    $(d, 'loginForm:kullaniciAdi').value = kayit.kullanici;
    $(d, 'loginForm:sifre').value = kayit.sifre;
    $(d, 'loginForm:guvenlikKodu').dispatchEvent(new d.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(gelen).toHaveLength(1);
  });
  it('boş şifreyle bir şey göndermez', () => {
    const d = giris();
    $(d, 'loginForm:kullaniciAdi').value = kayit.kullanici;
    expect(girisOku(d.window.document, win(d))).toBeNull();
  });
  it('şifre değiştirme: yeni şifre iki kez aynıysa yakalar, farklıysa yakalamaz', () => {
    const d = fixtureDom('sifre-degistir.html');
    const doc = d.window.document;
    (doc.getElementById('eski') as HTMLInputElement).value = 'Eski-1';
    (doc.getElementById('yeni') as HTMLInputElement).value = 'Yeni-2';
    (doc.getElementById('tekrar') as HTMLInputElement).value = 'Yeni-3';
    expect(girisOku(doc, win(d))).toBeNull();
    (doc.getElementById('tekrar') as HTMLInputElement).value = 'Yeni-2';
    expect(girisOku(doc, win(d))).toEqual({ kullanici: '', sifre: 'Yeni-2', degisim: true });
  });
  it('gizli şifre alanı giriş sayılmaz', () => {
    const d = htmlDom('<input name="kullanici" value="a"><input type="password" style="display:none" value="b">');
    expect(girisOku(d.window.document, win(d))).toBeNull();
  });
});

const sahteSifreleme = (var_ = true) => ({
  available: () => var_,
  encrypt: (t: string) => Buffer.from('ENC:' + Buffer.from(t).toString('base64')),
  decrypt: (b: Buffer) => Buffer.from(b.toString().slice(4), 'base64').toString(),
});
const gecici = () => fs.mkdtempSync(path.join(os.tmpdir(), 'medula-giris-'));

describe('MedulaGirisKasasi (yalnızca bu bilgisayar, DPAPI)', () => {
  it('şifreli kaydeder, okur, siler; dosyada düz şifre yok', () => {
    const dir = gecici();
    const f = path.join(dir, 'm.bin');
    const k = new MedulaGirisKasasi(f, path.join(dir, 'a.json'), sahteSifreleme());
    expect(k.al()).toBeNull();
    expect(k.kaydet(kayit)).toBe(true);
    expect(fs.readFileSync(f, 'utf8')).not.toContain(kayit.sifre);
    expect(new MedulaGirisKasasi(f, path.join(dir, 'a.json'), sahteSifreleme()).al()).toEqual(kayit);
    expect(k.ozet().kullanici).toMatch(/^o•+i$/);
    expect(k.ozet().kullanici).not.toContain('kullanici');
    k.sil();
    expect(fs.existsSync(f)).toBe(false);
    expect(k.al()).toBeNull();
  });
  it('şifreleme yoksa HİÇ yazmaz', () => {
    const dir = gecici();
    const f = path.join(dir, 'm.bin');
    const k = new MedulaGirisKasasi(f, path.join(dir, 'a.json'), sahteSifreleme(false));
    expect(k.kaydet(kayit)).toBe(false);
    expect(fs.existsSync(f)).toBe(false);
  });
  it('bozuk / başka hesaba ait dosya → kayıt yok', () => {
    const dir = gecici();
    const f = path.join(dir, 'm.bin');
    fs.writeFileSync(f, 'çöp');
    expect(new MedulaGirisKasasi(f, path.join(dir, 'a.json'), sahteSifreleme()).al()).toBeNull();
  });
  it('"bu bilgisayarda sorma" tercihi kalıcı; varsayılan açık', () => {
    const dir = gecici();
    const k = new MedulaGirisKasasi(path.join(dir, 'm.bin'), path.join(dir, 'a.json'), sahteSifreleme());
    expect(k.teklifAcik()).toBe(true);
    k.teklifAyarla(false);
    expect(new MedulaGirisKasasi(path.join(dir, 'm.bin'), path.join(dir, 'a.json'), sahteSifreleme()).teklifAcik()).toBe(false);
    expect(fs.readFileSync(path.join(dir, 'a.json'), 'utf8')).not.toMatch(/sifre|kullanici/);
  });
  it('geçersiz değerleri reddeder', () => {
    expect(gecerliGiris({ kullanici: ' ', sifre: 'x' })).toBeNull();
    expect(gecerliGiris({ kullanici: 'a', sifre: '' })).toBeNull();
    expect(gecerliGiris({ kullanici: 'a\nb', sifre: 'x' })).toBeNull();
    expect(gecerliGiris({ kullanici: 'a'.repeat(101), sifre: 'x' })).toBeNull();
    expect(gecerliGiris({ kullanici: ' a ', sifre: 'x' })).toEqual({ kullanici: 'a', sifre: 'x' });
  });
});

describe('ne sorulmalı', () => {
  const y = { ...kayit, degisim: false };
  it('kayıt yoksa "kaydet"; teklif kapalıysa ya da şifreleme yoksa hiçbir şey', () => {
    expect(neSorulmali(y, null, true, true).soru).toBe('kaydet');
    expect(neSorulmali(y, null, false, true).soru).toBe('yok');
    expect(neSorulmali(y, null, true, false).soru).toBe('yok');
  });
  it('aynısı kayıtlıysa sormaz; şifre değiştiyse "güncelle" (teklif kapalı olsa da)', () => {
    expect(neSorulmali(y, kayit, true, true).soru).toBe('yok');
    expect(neSorulmali({ ...y, sifre: 'Yeni' }, kayit, false, true)).toEqual({ soru: 'guncelle', kayit: { ...kayit, sifre: 'Yeni' } });
  });
  it('şifre değiştirme ekranı: kayıtlı kullanıcı adıyla güncelleme önerir; kayıt yoksa sormaz', () => {
    expect(neSorulmali({ kullanici: '', sifre: 'Yeni', degisim: true }, kayit, true, true)).toEqual({ soru: 'guncelle', kayit: { ...kayit, sifre: 'Yeni' } });
    expect(neSorulmali({ kullanici: '', sifre: 'Yeni', degisim: true }, null, true, true).soru).toBe('yok');
  });
});

describe('IPC doğrulama', () => {
  it('giriş yakalama mesajı', () => {
    expect(parseGirisYakalandi({ kullanici: ' a ', sifre: 'b', degisim: false })).toEqual({ kullanici: 'a', sifre: 'b', degisim: false });
    expect(parseGirisYakalandi({ kullanici: '', sifre: 'b', degisim: false })).toBeNull();
    expect(parseGirisYakalandi({ kullanici: '', sifre: 'b', degisim: true })).not.toBeNull();
    expect(parseGirisYakalandi({ kullanici: 'a', sifre: 'b\u0000', degisim: false })).toBeNull();
    expect(parseGirisYakalandi({ kullanici: 'a', sifre: 'x'.repeat(201), degisim: false })).toBeNull();
    expect(parseGirisYakalandi('a:b')).toBeNull();
  });
  it('giriş yanıtı', () => {
    expect(parseMedulaReply({ id: '12345678', kind: 'giris', ok: true, sonuc: 'doldu' })).toEqual({ id: '12345678', kind: 'giris', ok: true, sonuc: 'doldu' });
    expect(parseMedulaReply({ id: '12345678', kind: 'giris', ok: true, sonuc: 'sifre=x' })).toBeNull();
  });
});

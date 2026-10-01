/**
 * Toolbar renderer. Pure view: renders ShellState, sends whitelisted commands.
 * All text is set with textContent (never innerHTML) – server strings are untrusted.
 */
import type { SHELL_COMMANDS } from '../shared/constants';
import type { ShellState } from '../shared/types';

type Cmd = (typeof SHELL_COMMANDS)[number];
interface ShellApi {
  komut(c: Cmd): void;
  durumAl(): Promise<ShellState | null>;
  durumDinle(cb: (s: ShellState) => void): void;
}
const api = (window as unknown as { optiflowShell: ShellApi }).optiflowShell;

const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;

let noticeTimer: ReturnType<typeof setTimeout> | null = null;
let lastNotice: ShellState['notice'] | undefined;
let current: ShellState | null = null;

document.querySelectorAll<HTMLButtonElement>('[data-cmd]').forEach((b) => {
  b.addEventListener('click', () => api.komut(b.dataset.cmd as Cmd));
});
$('duzen').addEventListener('click', () => api.komut(current?.layout === 'bolunmus' ? 'duzen-sekme' : 'duzen-bolunmus'));
$('errorRetry').addEventListener('click', () => {
  if (current?.optiflow.error) api.komut('hata-yeniden-dene');
  else api.komut('medula-yenile');
});
$('update').addEventListener('click', () => {
  if (current?.update.status === 'var') api.komut('guncelleme-indir');
  else if (current?.update.status === 'hazir') api.komut('guncelleme-kur');
});
$('noticeAction').addEventListener('click', () => {
  if (lastNotice?.action) api.komut(lastNotice.action);
});
document.addEventListener('keydown', (e) => {
  if (e.ctrlKey && e.key === '1') api.komut('goster-optiflow');
  if (e.ctrlKey && e.key === '2') api.komut('goster-medula');
});

function showNotice(n: NonNullable<ShellState['notice']>): void {
  lastNotice = n;
  const msg = $('msg');
  msg.className = 'msg ' + (n.kind === 'info' ? 'bilgi' : 'hata');
  msg.textContent = n.text;
  msg.title = n.text;
  const a = $('noticeAction') as HTMLButtonElement;
  a.hidden = !n.action;
  a.textContent = n.action === 'indirme-klasor' ? 'Klasörde göster' : n.action === 'indirme-ac' ? 'Aç' : n.action === 'cevrimdisi-ac' ? 'Çevrimdışı kopya' : '';
  if (noticeTimer) clearTimeout(noticeTimer);
  noticeTimer = setTimeout(() => {
    noticeTimer = null;
    api.komut('bildirim-kapat');
  }, 8000);
}

function render(s: ShellState): void {
  const prevNotice = current?.notice;
  current = s;
  const split = s.layout === 'bolunmus';

  document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((b) => {
    b.setAttribute('aria-pressed', String(split || b.dataset.tab === s.activeView));
  });
  document.querySelector('.seg')!.classList.toggle('split', split);
  $('duzen').setAttribute('aria-pressed', String(split));
  $('duzen').title = split ? 'Sekmeli görünüm (Ctrl+3)' : 'Yan yana görünüm (Ctrl+3)';

  const medulaVisible = s.medula.opened && (split || s.activeView === 'medula');
  $('medulaNav').hidden = !medulaVisible;
  ($('geri') as HTMLButtonElement).disabled = !s.medula.canGoBack;
  ($('ileri') as HTMLButtonElement).disabled = !s.medula.canGoForward;
  $('host').textContent = s.medula.host ?? '';
  $('listeKontrol').hidden = !s.account?.features?.sgk_mutabakat;
  $('barkodChip').hidden = !s.account?.features?.barkod;

  // Prescription-detection badge (signals only, no content)
  const rx = $('rx');
  const p = s.medula.probe;
  rx.hidden = !s.medula.opened || !p;
  if (p) {
    rx.className = 'rx ' + (p.detectedPrescription ? 'on' : p.looksLikeLogin ? 'login' : 'off');
    rx.textContent = p.detectedPrescription ? 'Reçete ekranı' : p.looksLikeLogin ? 'Medula girişi' : 'Reçete yok';
  }

  // Transfer button + status
  const t = s.transfer;
  const busy = t.phase === 'okunuyor' || t.phase === 'gonderiliyor';
  const btn = $('transfer') as HTMLButtonElement;
  const locked = !!s.account && !s.account.transferAllowed;
  btn.disabled = busy;
  btn.classList.toggle('kilitli', locked);
  btn.classList.toggle('hazir', !locked && !!p?.detectedPrescription && !busy);
  // SVG elements have no .hidden property: toggle the attribute (styled by [hidden]).
  document.getElementById('transferIco')!.toggleAttribute('hidden', locked);
  document.getElementById('lockIco')!.toggleAttribute('hidden', !locked);
  btn.title = locked ? 'Medula aktarımı OptiFlow Pro paketindedir' : 'Medula’da açık olan reçeteyi OptiFlow’a aktarır';
  btn.querySelector('span')!.textContent = busy ? (t.phase === 'okunuyor' ? 'Okunuyor…' : 'Gönderiliyor…') : locked ? 'Pro ile aktar' : 'Reçeteyi aktar';
  $('retry').hidden = !(t.phase === 'hata' && t.canRetry);
  $('openIncoming').hidden = !(t.phase === 'basarili' && t.incomingId);

  const msg = $('msg');
  if (s.notice && s.notice !== prevNotice) showNotice(s.notice);
  if (!s.notice) {
    if (noticeTimer) clearTimeout(noticeTimer);
    noticeTimer = null;
    lastNotice = undefined;
    $('noticeAction').hidden = true;
    msg.className = 'msg ' + (t.phase === 'hata' ? 'hata' : t.phase === 'basarili' ? 'basarili' : 'bilgi');
    msg.textContent = t.message ? (t.summary && t.phase === 'basarili' ? `${t.message} (${t.summary})` : t.message) : '';
    msg.title = msg.textContent;
  }

  // Account
  const who = $('who');
  who.hidden = !s.account;
  who.classList.toggle('destek', !!s.account?.supportMode);
  who.replaceChildren();
  if (s.account) {
    const b = document.createElement('b');
    b.textContent = s.account.storeName;
    who.append(b, document.createTextNode(` · ${s.account.userName}${s.account.supportMode ? ' · destek' : ''}${s.account.package === 'lite' ? ' · Lite paket' : ''}`));
    who.title = who.textContent ?? '';
  }

  const dot = $('online');
  dot.classList.toggle('off', !s.online);
  dot.title = s.online ? 'Çevrimiçi' : 'İnternet bağlantısı yok';

  const up = $('update') as HTMLButtonElement;
  up.hidden = !['var', 'indiriliyor', 'hazir'].includes(s.update.status);
  up.disabled = s.update.status === 'indiriliyor';
  up.textContent =
    s.update.status === 'var'
      ? `Güncelleme ${s.update.version ?? ''}`
      : s.update.status === 'indiriliyor'
        ? `İndiriliyor %${s.update.percent ?? 0}`
        : s.update.status === 'hazir'
          ? 'Yeniden başlat ve güncelle'
          : '';

  // Stage panels (visible only when the active view is hidden)
  const ofErr = s.optiflow.error && (split || s.activeView === 'optiflow') ? s.optiflow.error : '';
  const mdErr = s.medula.error && (split || s.activeView === 'medula') ? s.medula.error : '';
  const err = ofErr || mdErr;
  $('errorPanel').hidden = !err;
  // Splash until the OptiFlow page has loaded once (no white flash, no half-drawn page).
  $('splashText').textContent = s.online ? 'Mağazanıza bağlanılıyor…' : 'İnternet bağlantısı bekleniyor…';
  $('errorTitle').textContent = ofErr ? 'OptiFlow açılamadı' : 'Medula açılamadı';
  $('errorText').textContent = err;
  $('idlePanel').hidden = !!err;
  const off = s.offline;
  $('offlineBtn').hidden = !(ofErr && off?.available);
  $('offlineInfo').hidden = !(ofErr && off?.available);
  $('offlineInfo').textContent = off?.available ? `Son kopya: ${off.at ? new Date(off.at).toLocaleString('tr-TR') : ''} · ${off.count ?? 0} sipariş (salt okunur)` : '';
  $('ver').textContent = `OptiFlow Pro ${s.version}${s.appEnv !== 'production' ? ' · ' + s.appEnv : ''}`;
}

api.durumDinle(render);
void api.durumAl().then((s) => s && render(s));

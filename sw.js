/* ==========================================================================
   OptiFlow — servis çalışanı (PWA)
   Kural: oturuma bağlı HTML sayfaları ASLA önbelleğe alınmaz; yalnızca
   statik varlıklar (assets/…) ve çevrimdışı ekranı saklanır. Böylece bir
   kullanıcının verisi başka bir kullanıcıya gösterilemez ve ekrandaki
   bilgiler her zaman günceldir.
   ========================================================================== */
const SURUM = 'optiflow-v4';
const KABUK = `${SURUM}-kabuk`;
const VARLIK = `${SURUM}-varlik`;

const ON_YUKLE = [
  'offline.html',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/favicon.svg',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(KABUK)
      .then((c) => c.addAll(ON_YUKLE))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((adlar) => Promise.all(
        adlar.filter((a) => !a.startsWith(SURUM)).map((a) => caches.delete(a))
      ))
      .then(() => self.clients.claim())
  );
});

function varlikMi(url) {
  return url.pathname.includes('/assets/');
}

self.addEventListener('fetch', (e) => {
  const istek = e.request;
  if (istek.method !== 'GET') return;                 // POST/PUT: her zaman ağ
  const url = new URL(istek.url);
  if (url.origin !== self.location.origin) return;    // dış kaynaklar: dokunma
  if (url.pathname.endsWith('/api.php')) return;      // canlı veri: her zaman ağ

  // 1) Statik varlıklar: ÖNCE AĞ (her zaman en güncel sürüm), ağ yoksa önbellek.
  //    Not: Eskiden "önce önbellek" idi — bu, yeni bir sürüm yayınlandığında
  //    tarayıcının günlerce eski app.js/app.css çalıştırmasına yol açıyordu.
  if (varlikMi(url)) {
    e.respondWith(
      caches.open(VARLIK).then(async (c) => {
        try {
          const agdan = await fetch(istek);
          if (agdan && agdan.status === 200) { c.put(istek, agdan.clone()); }
          return agdan;
        } catch (_) {
          const kayit = await c.match(istek);
          if (kayit) { return kayit; }
          throw _;
        }
      })
    );
    return;
  }

  // 2) Sayfa gezinmeleri: her zaman ağ; ağ yoksa çevrimdışı ekranı
  if (istek.mode === 'navigate') {
    e.respondWith(
      fetch(istek).catch(() => caches.match('offline.html', { ignoreSearch: true }))
    );
  }
});

/* ---------- Gelen bildirim ---------- */
self.addEventListener('push', (e) => {
  let veri = { title: 'OptiFlow', body: '', url: 'index.php' };
  try { if (e.data) veri = Object.assign(veri, e.data.json()); }
  catch (_) { if (e.data) veri.body = e.data.text(); }

  e.waitUntil(self.registration.showNotification(veri.title, {
    body: veri.body,
    icon: 'assets/icons/icon-192.png',
    badge: 'assets/icons/icon-192.png',
    tag: veri.tag || 'optiflow',
    renotify: true,
    dir: 'ltr',
    lang: 'tr',
    vibrate: [70, 40, 70],
    data: { url: veri.url || 'index.php' },
  }));
});

/* Bildirime tıklanınca uygulamayı aç */
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const hedef = (e.notification.data && e.notification.data.url) || 'index.php';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((liste) => {
      for (const c of liste) {
        if ('focus' in c) { c.navigate(hedef); return c.focus(); }
      }
      return self.clients.openWindow(hedef);
    })
  );
});

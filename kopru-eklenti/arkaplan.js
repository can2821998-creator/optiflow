/* Ayarlardaki adres ve anahtarla atölye sistemine gönderir. */
chrome.runtime.onMessage.addListener(function (istek, gonderen, cevapla) {
  if (!istek || istek.tip !== 'aktar') return;

  chrome.storage.sync.get(['adres', 'anahtar'], function (ayar) {
    if (!ayar.adres || !ayar.anahtar) {
      cevapla({ ok: false, hata: 'Önce eklenti ayarlarından adres ve anahtarı girin.' });
      return;
    }
    fetch(ayar.adres, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: ayar.anahtar, metin: istek.metin, baslik: istek.baslik }),
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, hata: 'HTTP ' + r.status }; }); })
      .then(function (d) { cevapla(d); })
      .catch(function (e) { cevapla({ ok: false, hata: String(e && e.message ? e.message : e) }); });
  });
  return true;  // eşzamansız yanıt
});

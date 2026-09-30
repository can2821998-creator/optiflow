var adres = document.getElementById('adres');
var anahtar = document.getElementById('anahtar');
var bilgi = document.getElementById('bilgi');

chrome.storage.sync.get(['adres', 'anahtar'], function (a) {
  adres.value = a.adres || '';
  anahtar.value = a.anahtar || '';
});

document.getElementById('kaydet').addEventListener('click', function () {
  chrome.storage.sync.set({ adres: adres.value.trim(), anahtar: anahtar.value.trim() }, function () {
    bilgi.hidden = false;
    setTimeout(function () { bilgi.hidden = true; }, 2000);
  });
});

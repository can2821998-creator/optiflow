/** Native application menu (Turkish). Dialog-based settings keep the toolbar minimal (spec §20). */
import { app, Menu, type MenuItemConstructorOptions } from 'electron';

export interface MenuHandlers {
  showOptiflow: () => void;
  showMedula: () => void;
  reloadOptiflow: () => void;
  medulaHome: () => void;
  clearMedulaSession: () => void;
  resetMedula: () => void;
  toggleLayout: () => void;
  checkUpdates: () => void;
  exportDiagnostics: () => void;
  openLogsFolder: () => void;
  about: () => void;
}

export function buildMenu(h: MenuHandlers, devTools: boolean): Menu {
  const tpl: MenuItemConstructorOptions[] = [
    {
      label: 'OptiFlow',
      submenu: [
        { label: 'OptiFlow ekranı', accelerator: 'Ctrl+1', click: h.showOptiflow },
        { label: 'Sayfayı yenile', accelerator: 'F5', click: h.reloadOptiflow },
        { type: 'separator' },
        { label: 'Yan yana / sekmeli görünüm', accelerator: 'Ctrl+3', click: h.toggleLayout },
        { type: 'separator' },
        { role: 'zoomIn', label: 'Yakınlaştır' },
        { role: 'zoomOut', label: 'Uzaklaştır' },
        { role: 'resetZoom', label: 'Gerçek boyut' },
        { role: 'togglefullscreen', label: 'Tam ekran' },
        { type: 'separator' },
        { role: 'quit', label: 'Çıkış' },
      ],
    },
    {
      label: 'Düzen',
      submenu: [
        { role: 'undo', label: 'Geri al' },
        { role: 'redo', label: 'Yinele' },
        { type: 'separator' },
        { role: 'cut', label: 'Kes' },
        { role: 'copy', label: 'Kopyala' },
        { role: 'paste', label: 'Yapıştır' },
        { role: 'selectAll', label: 'Tümünü seç' },
      ],
    },
    {
      label: 'SGK / Medula',
      submenu: [
        { label: 'Medula ekranı', accelerator: 'Ctrl+2', click: h.showMedula },
        { label: 'Medula ana sayfa', click: h.medulaHome },
        { type: 'separator' },
        { label: 'Medula\'yı sıfırla (giriş hata veriyorsa)', click: h.resetMedula },
        { label: 'Medula oturumunu temizle…', click: h.clearMedulaSession },
      ],
    },
    {
      label: 'Yardım',
      submenu: [
        { label: 'Güncellemeleri denetle', click: h.checkUpdates },
        { label: 'Tanılama kayıtlarını dışa aktar…', click: h.exportDiagnostics },
        { label: 'Kayıt klasörünü aç', click: h.openLogsFolder },
        { type: 'separator' },
        { label: `OptiFlow Masaüstü hakkında (${app.getVersion()})`, click: h.about },
        ...(devTools
          ? ([{ type: 'separator' }, { role: 'toggleDevTools', label: 'Geliştirici araçları (yalnız geliştirme)' }] as MenuItemConstructorOptions[])
          : []),
      ],
    },
  ];
  return Menu.buildFromTemplate(tpl);
}

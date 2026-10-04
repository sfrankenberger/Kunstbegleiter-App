/* Kunstbegleiter Service Worker: nur die App-Huelle fuer "Zum Home-Bildschirm", kein Cache und kein fetch-Handler
   (Safari verliert bei vom Worker beantworteten Navigationen die Sitzung, Erfahrung aus Tourtool 04.10.2026).
   Push kommt spaeter, falls gebraucht. Eingebunden mit ?v=<mtime> aus dem Layout. */
self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

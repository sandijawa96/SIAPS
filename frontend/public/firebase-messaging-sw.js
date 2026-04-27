/* eslint-disable no-undef */
importScripts('https://www.gstatic.com/firebasejs/11.6.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.6.1/firebase-messaging-compat.js');

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Register core push-related handlers at initial script evaluation.
// This avoids browser warnings when messaging initialization happens asynchronously.
self.addEventListener('push', () => {});
self.addEventListener('pushsubscriptionchange', () => {});
self.addEventListener('notificationclick', (event) => {
  event.notification?.close();
});

const loadFirebaseConfig = async () => {
  const response = await fetch('/api/push/config/web');
  const payload = await response.json();
  return payload?.data?.firebase || null;
};

let messagingPromise = null;

const getMessagingInstance = async () => {
  if (!messagingPromise) {
    messagingPromise = loadFirebaseConfig().then((config) => {
      if (!config?.apiKey || !config?.messagingSenderId || !config?.projectId || !config?.appId) {
        return null;
      }

      const app = firebase.initializeApp({
        apiKey: config.apiKey,
        authDomain: config.authDomain,
        projectId: config.projectId,
        storageBucket: config.storageBucket,
        messagingSenderId: config.messagingSenderId,
        appId: config.appId,
        measurementId: config.measurementId,
      });

      return firebase.messaging(app);
    });
  }

  return messagingPromise;
};

getMessagingInstance().then((messaging) => {
  if (!messaging) {
    return;
  }

  messaging.onBackgroundMessage((payload) => {
    const title = payload?.notification?.title || 'Notifikasi Baru';
    const options = {
      body: payload?.notification?.body || '',
      data: payload?.data || {},
    };

    self.registration.showNotification(title, options);
  });
});

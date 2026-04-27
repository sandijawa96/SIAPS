import { initializeApp, getApps } from 'firebase/app';
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';
import { deviceTokensAPI, pushConfigAPI } from './api';

let initialized = false;
let foregroundBound = false;
let registeredUserId = null;
const NOTIFICATION_UPDATED_EVENT = 'siaps-notifications-updated';
const WEB_PUSH_STATUS_EVENT = 'siaps-web-push-status';
let webPushState = {
  checked: false,
  ready: false,
  reason: 'idle',
};

const notifyNotificationStateChanged = (detail = {}) => {
  window.dispatchEvent(new CustomEvent(NOTIFICATION_UPDATED_EVENT, { detail }));
};

const updateWebPushState = (nextState) => {
  webPushState = {
    ...webPushState,
    ...nextState,
    checked: true,
  };

  window.dispatchEvent(new CustomEvent(WEB_PUSH_STATUS_EVENT, {
    detail: webPushState,
  }));
};

const getFirebaseConfig = async () => {
  const response = await pushConfigAPI.getWebConfig();
  const payload = response?.data?.data || {};
  return payload;
};

const getBrowserDeviceId = () => {
  const key = 'web_push_device_id';
  let deviceId = localStorage.getItem(key);
  if (!deviceId) {
    deviceId = `web-${crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;
    localStorage.setItem(key, deviceId);
  }
  return deviceId;
};

const getExistingBrowserDeviceId = () => localStorage.getItem('web_push_device_id');

export const getWebPushState = () => webPushState;

export const initializeWebPushNotifications = async ({ userId } = {}) => {
  const targetUserId = userId !== undefined && userId !== null ? String(userId) : '';

  if (initialized && registeredUserId === targetUserId) {
    updateWebPushState({ ready: true, reason: 'already_initialized' });
    return { success: true, message: 'already_initialized' };
  }

  try {
    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
      updateWebPushState({ ready: false, reason: 'unsupported' });
      return { success: false, message: 'unsupported' };
    }

    if (!(await isSupported())) {
      updateWebPushState({ ready: false, reason: 'messaging_not_supported' });
      return { success: false, message: 'messaging_not_supported' };
    }

    const configPayload = await getFirebaseConfig();
    if (!configPayload.enabled) {
      updateWebPushState({ ready: false, reason: 'push_disabled' });
      return { success: false, message: 'push_disabled' };
    }

    const firebase = configPayload.firebase || {};
    if (!firebase.apiKey || !firebase.messagingSenderId || !firebase.projectId || !firebase.appId) {
      updateWebPushState({ ready: false, reason: 'firebase_not_configured' });
      return { success: false, message: 'firebase_not_configured' };
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      updateWebPushState({ ready: false, reason: 'permission_denied' });
      return { success: false, message: 'permission_denied' };
    }

    const app = getApps()[0] || initializeApp({
      apiKey: firebase.apiKey,
      authDomain: firebase.authDomain,
      projectId: firebase.projectId,
      storageBucket: firebase.storageBucket,
      messagingSenderId: firebase.messagingSenderId,
      appId: firebase.appId,
      measurementId: firebase.measurementId,
    });

    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
    const messaging = getMessaging(app);
    const token = await getToken(messaging, {
      vapidKey: firebase.vapidKey,
      serviceWorkerRegistration: registration,
    });

    if (!token) {
      updateWebPushState({ ready: false, reason: 'token_unavailable' });
      return { success: false, message: 'token_unavailable' };
    }

    await deviceTokensAPI.register({
      device_id: getBrowserDeviceId(),
      device_name: window.navigator.userAgent,
      device_type: 'web',
      push_token: token,
      device_info: {
        user_agent: window.navigator.userAgent,
        language: window.navigator.language,
        platform: window.navigator.platform,
      },
    });

    if (!foregroundBound) {
      onMessage(messaging, (payload) => {
        const title = payload?.notification?.title || 'Notifikasi Baru';
        const body = payload?.notification?.body || '';
        if (Notification.permission === 'granted') {
          new Notification(title, { body });
        }
        notifyNotificationStateChanged({
          source: 'fcm',
          payload,
        });
      });
      foregroundBound = true;
    }

    initialized = true;
    registeredUserId = targetUserId;
    updateWebPushState({ ready: true, reason: 'registered' });
    return { success: true, message: 'registered' };
  } catch (_error) {
    updateWebPushState({ ready: false, reason: 'initialization_failed' });
    return { success: false, message: 'initialization_failed' };
  }
};

export const deactivateWebPushNotifications = async () => {
  const deviceId = getExistingBrowserDeviceId();

  try {
    if (deviceId) {
      await deviceTokensAPI.deactivate({ device_id: deviceId });
    }

    return { success: true, message: deviceId ? 'deactivated' : 'no_device_id' };
  } finally {
    initialized = false;
    registeredUserId = null;
    updateWebPushState({ ready: false, reason: 'deactivated' });
  }
};

export const notificationUpdatedEventName = NOTIFICATION_UPDATED_EVENT;
export const webPushStatusEventName = WEB_PUSH_STATUS_EVENT;

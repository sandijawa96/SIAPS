const normalizeRecord = (value) => {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value;
  }

  return {};
};

const readBoolean = (value, fallback = false) => {
  if (typeof value === 'boolean') {
    return value;
  }

  if (value === 1 || value === '1' || value === 'true') {
    return true;
  }

  if (value === 0 || value === '0' || value === 'false') {
    return false;
  }

  return fallback;
};

export const getNotificationData = (notification) => normalizeRecord(notification?.data);

export const getNotificationPresentation = (notification) => normalizeRecord(getNotificationData(notification).presentation);

export const getNotificationPopup = (notification) => normalizeRecord(getNotificationData(notification).popup);

export const isAnnouncementNotification = (notification) => {
  const data = getNotificationData(notification);
  const messageCategory = String(data.message_category || '').trim().toLowerCase();
  if (messageCategory === 'announcement') {
    return true;
  }
  if (messageCategory === 'system') {
    return false;
  }

  const source = String(data.source || '').trim().toLowerCase();

  return data.broadcast_campaign_id != null || source.includes('broadcast');
};

export const getNotificationCategoryLabel = (notification) => (
  isAnnouncementNotification(notification) ? 'Pengumuman' : 'Pesan Sistem'
);

export const getNotificationPresentationLabel = (notification) => {
  const presentation = getNotificationPresentation(notification);
  const popup = getNotificationPopup(notification);
  const popupEnabled = readBoolean(presentation.popup, false);
  const hasInAppFlag = Object.prototype.hasOwnProperty.call(presentation, 'in_app');
  const inAppEnabled = hasInAppFlag
    ? readBoolean(presentation.in_app, false)
    : !popupEnabled;
  const popupKind = popup.variant === 'flyer' || popup.image_url ? 'flyer' : 'popup';

  if (!popupEnabled) {
    return 'Notifikasi';
  }

  if (!inAppEnabled) {
    return popupKind === 'flyer' ? 'Flyer' : 'Popup';
  }

  return popupKind === 'flyer' ? 'Notifikasi + Flyer' : 'Notifikasi + Popup';
};

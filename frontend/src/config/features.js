const parseBoolean = (value, fallback = false) => {
  if (typeof value !== 'string') {
    return fallback;
  }

  const normalized = value.trim().toLowerCase();
  if (['1', 'true', 'yes', 'on'].includes(normalized)) {
    return true;
  }
  if (['0', 'false', 'no', 'off'].includes(normalized)) {
    return false;
  }

  return fallback;
};

export const FEATURE_FLAGS = {
  attendanceQrEnabled: parseBoolean(import.meta.env.VITE_ATTENDANCE_QR_ENABLED, false),
  liveTrackingTestRouteEnabled:
    import.meta.env.DEV || parseBoolean(import.meta.env.VITE_LIVE_TRACKING_TEST_ROUTE_ENABLED, false),
};

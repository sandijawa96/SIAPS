import api from './api';
import { getServerIsoString, getServerNowDate, getServerNowEpochMs } from './serverClock';

const TRACKING_SETTINGS_CACHE_TTL_MS = 10 * 60 * 1000;
const TRACKING_SESSION_STORAGE_KEY = 'liveTrackingSessionId';
let cachedTrackingSettings = null;
let cachedTrackingSettingsAt = 0;

const getTrackingSessionId = () => {
  if (typeof window === 'undefined') {
    return `web-${Date.now()}`;
  }

  try {
    const existing = window.sessionStorage.getItem(TRACKING_SESSION_STORAGE_KEY);
    if (existing) {
      return existing;
    }

    const generated = `web-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    window.sessionStorage.setItem(TRACKING_SESSION_STORAGE_KEY, generated);
    return generated;
  } catch (error) {
    return `web-${Date.now()}`;
  }
};

const getPlatformLabel = () => {
  if (typeof navigator === 'undefined') {
    return 'web';
  }

  return navigator.platform || navigator.userAgent || 'web';
};

const normalizeDayToken = (value = '') =>
  String(value)
    .trim()
    .toLowerCase()
    .replace(/['`’\s]+/g, '');

const parseMinuteOfDay = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;

  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (
    Number.isNaN(hour) ||
    Number.isNaN(minute) ||
    hour < 0 ||
    hour > 23 ||
    minute < 0 ||
    minute > 59
  ) {
    return null;
  }

  return (hour * 60) + minute;
};

const normalizeHariKerja = (rawValue) => {
  if (Array.isArray(rawValue)) {
    return rawValue.map((item) => String(item).trim()).filter(Boolean);
  }

  if (typeof rawValue === 'string') {
    const trimmed = rawValue.trim();
    if (!trimmed) return [];

    try {
      const decoded = JSON.parse(trimmed);
      if (Array.isArray(decoded)) {
        return decoded.map((item) => String(item).trim()).filter(Boolean);
      }
    } catch (error) {
      // Ignore JSON parse issue and continue with CSV fallback.
    }

    return trimmed.split(',').map((item) => item.trim()).filter(Boolean);
  }

  return [];
};

const getDayAliases = (dayIndex) => {
  switch (dayIndex) {
    case 1:
      return ['senin', 'monday'];
    case 2:
      return ['selasa', 'tuesday'];
    case 3:
      return ['rabu', 'wednesday'];
    case 4:
      return ['kamis', 'thursday'];
    case 5:
      return ['jumat', "jum'at", 'friday'];
    case 6:
      return ['sabtu', 'saturday'];
    case 0:
      return ['minggu', 'sunday'];
    default:
      return [];
  }
};

const extractTrackingSettings = (rawResponse) => {
  const payload = rawResponse?.data?.data ?? rawResponse?.data ?? {};
  const settings = payload?.settings ?? payload ?? {};
  const workingHours = payload?.working_hours ?? settings?.working_hours ?? {};

  return {
    jamMasuk: workingHours?.jam_masuk ?? settings?.jam_masuk ?? settings?.siswa_jam_masuk ?? null,
    jamPulang: workingHours?.jam_pulang ?? settings?.jam_pulang ?? settings?.siswa_jam_pulang ?? null,
    hariKerja: normalizeHariKerja(settings?.hari_kerja ?? payload?.hari_kerja),
  };
};

const refreshTrackingSettingsIfNeeded = async () => {
  const nowMs = getServerNowEpochMs();
  if (
    cachedTrackingSettings &&
    (nowMs - cachedTrackingSettingsAt) < TRACKING_SETTINGS_CACHE_TTL_MS
  ) {
    return cachedTrackingSettings;
  }

  try {
    const response = await api.get('/lokasi-gps/attendance-schema');
    cachedTrackingSettings = extractTrackingSettings(response);
    cachedTrackingSettingsAt = nowMs;
  } catch (error) {
    // Keep soft-fail behavior: backend policy remains source of truth.
    cachedTrackingSettings = null;
    cachedTrackingSettingsAt = nowMs;
  }

  return cachedTrackingSettings;
};

const isWithinTrackingWindow = (settings, now = getServerNowDate()) => {
  if (!settings) {
    return true;
  }

  const workingDays = (settings.hariKerja || [])
    .map((day) => normalizeDayToken(day))
    .filter(Boolean);

  if (workingDays.length > 0) {
    const dayAliases = getDayAliases(now.getDay()).map((day) => normalizeDayToken(day));
    const isWorkingDay = dayAliases.some((alias) => workingDays.includes(alias));
    if (!isWorkingDay) {
      return false;
    }
  }

  const startMinute = parseMinuteOfDay(settings.jamMasuk);
  const endMinute = parseMinuteOfDay(settings.jamPulang);

  if (startMinute === null || endMinute === null) {
    return true;
  }

  const currentMinute = (now.getHours() * 60) + now.getMinutes();
  if (endMinute < startMinute) {
    return currentMinute >= startMinute || currentMinute <= endMinute;
  }

  return currentMinute >= startMinute && currentMinute <= endMinute;
};

const canSendTrackingNow = async () => {
  // Keputusan final tetap di backend agar sesi pemantauan tambahan tetap berjalan kapan pun.
  // Tetap cek cepat agar error parser settings tidak memblokir tracking.
  await refreshTrackingSettingsIfNeeded().catch(() => {
    // no-op
  });

  return true;
};

/**
 * Service untuk mengelola GPS tracking realtime
 */
export const gpsTrackingService = {
  /**
   * Update lokasi pengguna saat ini
   * @param {Object} locationData - Data lokasi (latitude, longitude, accuracy)
   * @returns {Promise} Response update lokasi
   */
  updateUserLocation: async (locationData) => {
    try {
      const response = await api.post('/lokasi-gps/update-location', locationData);
      return response.data;
    } catch (error) {
      const status = error?.response?.status;
      // Policy-driven reject (di luar jam/hari) bukan error operasional.
      if (status === 403 || status === 422) {
        return error?.response?.data || {
          success: false,
          message: 'Realtime tracking ditolak policy',
        };
      }
      console.error('Error updating user location:', error);
      throw error;
    }
  },

  /**
   * Mengambil data pengguna aktif dengan lokasi GPS mereka
   * @returns {Promise} Response data pengguna aktif
   */
  getActiveUsersLocations: async () => {
    try {
      const response = await api.get('/lokasi-gps/active-users');
      return response.data;
    } catch (error) {
      console.error('Error fetching active users locations:', error);
      throw error;
    }
  },

  /**
   * Mengambil pengguna yang berada dalam lokasi GPS tertentu
   * @param {number} locationId - ID lokasi GPS
   * @returns {Promise} Response pengguna dalam lokasi
   */
  getUsersInLocation: async (locationId) => {
    try {
      const response = await api.get(`/lokasi-gps/${locationId}/users`);
      return response.data;
    } catch (error) {
      console.error('Error fetching users in location:', error);
      throw error;
    }
  },

  /**
   * Validasi lokasi pengguna terhadap area GPS
   * @param {Object} coordinates - Koordinat pengguna (latitude, longitude)
   * @returns {Promise} Response validasi lokasi
   */
  validateUserLocation: async (coordinates) => {
    try {
      const response = await api.post('/lokasi-gps/validate', coordinates);
      return response.data;
    } catch (error) {
      console.error('Error validating user location:', error);
      throw error;
    }
  },

  /**
   * Mengambil jarak pengguna dari lokasi GPS
   * @param {Object} coordinates - Koordinat pengguna (latitude, longitude)
   * @returns {Promise} Response jarak ke lokasi
   */
  checkDistanceToLocations: async (coordinates) => {
    try {
      const response = await api.post('/lokasi-gps/check-distance', coordinates);
      return response.data;
    } catch (error) {
      console.error('Error checking distance to locations:', error);
      throw error;
    }
  },

  /**
   * Mengambil lokasi GPS aktif
   * @returns {Promise} Response lokasi GPS aktif
   */
  getActiveGpsLocations: async () => {
    try {
      const response = await api.get('/lokasi-gps/active');
      return response.data;
    } catch (error) {
      console.error('Error fetching active GPS locations:', error);
      throw error;
    }
  },

  /**
   * Mengambil semua lokasi GPS
   * @returns {Promise} Response semua lokasi GPS
   */
  getAllGpsLocations: async () => {
    try {
      const response = await api.get('/lokasi-gps');
      return response.data;
    } catch (error) {
      console.error('Error fetching all GPS locations:', error);
      throw error;
    }
  },

  /**
   * Toggle status lokasi GPS
   * @param {number} locationId - ID lokasi GPS
   * @returns {Promise} Response toggle status
   */
  toggleLocationStatus: async (locationId) => {
    try {
      const response = await api.post(`/lokasi-gps/${locationId}/toggle`);
      return response.data;
    } catch (error) {
      console.error('Error toggling location status:', error);
      throw error;
    }
  },

  /**
   * Mengambil statistik GPS tracking
   * @returns {Promise} Response statistik
   */
  getGpsTrackingStats: async () => {
    try {
      const activeUsersResponse = await api.get('/lokasi-gps/active-users');
      const locationsResponse = await api.get('/lokasi-gps/active');
      
      const activeUsers = activeUsersResponse.data?.data?.active_users || [];
      const locations = locationsResponse.data?.data || [];
      
      return {
        success: true,
        data: {
          total_active_users: activeUsers.length,
          users_in_gps_area: activeUsers.filter(user => user.within_gps_area).length,
          users_outside_gps_area: activeUsers.filter(user => !user.within_gps_area).length,
          total_gps_locations: locations.length,
          active_gps_locations: locations.filter(loc => loc.is_active).length,
          last_updated: getServerIsoString()
        }
      };
    } catch (error) {
      console.error('Error fetching GPS tracking stats:', error);
      throw error;
    }
  },

  /**
   * Mulai tracking lokasi realtime untuk siswa tertentu dari dashboard/admin.
   * @param {Object} payload
   * @returns {Promise} Response sesi tracking
   */
  startTrackingSession: async ({ userId, minutes = null, reason = null }) => {
    const response = await api.post('/live-tracking/session/start', {
      user_id: Number(userId),
      minutes,
      reason,
    });
    return response.data;
  },

  /**
   * Hentikan sesi tracking siswa tertentu.
   * @param {number} userId
   * @returns {Promise} Response penghentian sesi
   */
  stopTrackingSession: async (userId) => {
    const response = await api.post('/live-tracking/session/stop', {
      user_id: Number(userId),
    });
    return response.data;
  },

  /**
   * Ambil daftar sesi tracking aktif.
   * @returns {Promise} Response daftar sesi aktif
   */
  getActiveTrackingSessions: async () => {
    const response = await api.get('/live-tracking/session/active');
    return response.data;
  },

  /**
   * Memulai tracking lokasi pengguna secara otomatis
   * @param {Function} onLocationUpdate - Callback ketika lokasi berubah
   * @param {Object} options - Opsi tracking
   * @returns {Object} Tracking controller
   */
  startLocationTracking: (onLocationUpdate, options = {}) => {
    const {
      enableHighAccuracy = true,
      timeout = 10000,
      maximumAge = 60000,
      updateInterval = 30000 // Update realtime setiap 30 detik
    } = options;

    let watchId = null;
    let updateIntervalId = null;
    let lastKnownPosition = null;
    let isSending = false;

    const updateLocation = async (position) => {
      if (isSending) {
        return;
      }

      isSending = true;
      const locationData = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        speed: Number.isFinite(position.coords.speed) ? position.coords.speed : null,
        heading: Number.isFinite(position.coords.heading) ? position.coords.heading : null,
        device_source: 'web',
        device_session_id: getTrackingSessionId(),
        platform: getPlatformLabel(),
        app_version: 'frontend-web'
      };

      try {
        const allowedByWindow = await canSendTrackingNow();
        if (!allowedByWindow) {
          return;
        }

        await gpsTrackingService.updateUserLocation(locationData);
      } catch (error) {
        console.error('Failed to update location:', error);
      } finally {
        lastKnownPosition = locationData;
        isSending = false;
        if (onLocationUpdate) {
          onLocationUpdate(locationData);
        }
      }
    };

    const startTracking = () => {
      if ('geolocation' in navigator) {
        // Watch position changes
        watchId = navigator.geolocation.watchPosition(
          updateLocation,
          (error) => {
            console.error('Geolocation error:', error);
          },
          {
            enableHighAccuracy,
            timeout,
            maximumAge
          }
        );

        // Set interval untuk update berkala
        updateIntervalId = setInterval(() => {
          navigator.geolocation.getCurrentPosition(
            updateLocation,
            (error) => {
              console.error('Periodic location update error:', error);
            },
            {
              enableHighAccuracy,
              timeout,
              maximumAge
            }
          );
        }, updateInterval);
      } else {
        console.error('Geolocation is not supported by this browser.');
      }
    };

    const stopTracking = () => {
      if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }
      
      if (updateIntervalId !== null) {
        clearInterval(updateIntervalId);
        updateIntervalId = null;
      }
    };

    const getCurrentPosition = () => {
      return lastKnownPosition;
    };

    // Start tracking immediately
    startTracking();

    return {
      stop: stopTracking,
      getCurrentPosition,
      isTracking: () => watchId !== null
    };
  }
};

export default gpsTrackingService;

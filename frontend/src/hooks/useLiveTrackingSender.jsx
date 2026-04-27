import { useEffect, useRef } from 'react';
import { useAuth } from './useAuth';
import { gpsTrackingService } from '../services/gpsTrackingService';

const SISWA_ROLE_ALIASES = new Set([
  'siswa',
  'siswa web',
  'siswa api',
  'siswa_web',
  'siswa_api',
]);

const normalizeRole = (roleName) =>
  String(roleName || '')
    .trim()
    .toLowerCase()
    .replace(/[_\s]+/g, ' ');

export const useLiveTrackingSender = () => {
  const { token, roles, isLoading, hasRole } = useAuth();
  const trackingControllerRef = useRef(null);

  useEffect(() => {
    const isSiswaByRoleList =
      Array.isArray(roles) && roles.some((role) => SISWA_ROLE_ALIASES.has(normalizeRole(role)));
    const isSiswa = isSiswaByRoleList || hasRole('siswa');

    const canTrack =
      !isLoading &&
      Boolean(token) &&
      isSiswa &&
      typeof window !== 'undefined' &&
      typeof navigator !== 'undefined' &&
      'geolocation' in navigator;

    const stopTracking = () => {
      if (trackingControllerRef.current) {
        trackingControllerRef.current.stop();
        trackingControllerRef.current = null;
      }
    };

    const startTracking = () => {
      if (!canTrack || trackingControllerRef.current) {
        return;
      }

      trackingControllerRef.current = gpsTrackingService.startLocationTracking(null, {
        updateInterval: 30000
      });
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        startTracking();
      } else {
        stopTracking();
      }
    };

    if (canTrack && document.visibilityState === 'visible') {
      startTracking();
    } else {
      stopTracking();
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', startTracking);
    window.addEventListener('blur', stopTracking);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('focus', startTracking);
      window.removeEventListener('blur', stopTracking);
      stopTracking();
    };
  }, [hasRole, isLoading, roles, token]);
};

export default useLiveTrackingSender;

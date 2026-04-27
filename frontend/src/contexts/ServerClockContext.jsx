import React, { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import api from '../services/api';
import {
  cacheCurrentServerClock,
  getServerClockSnapshot,
  getServerDateString,
  getServerNowEpochMs,
  subscribeServerClock,
  syncServerClockFromResponse,
} from '../services/serverClock';

const DEFAULT_SYNC_INTERVAL_MS = 60 * 1000;
const DEFAULT_TICK_INTERVAL_MS = 1000;

export const ServerClockContext = createContext(null);

const getMonotonicNowMs = () => {
  if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
    const now = performance.now();
    if (Number.isFinite(now)) {
      return now;
    }
  }

  return Date.now();
};

export const ServerClockProvider = ({
  children,
  syncIntervalMs = DEFAULT_SYNC_INTERVAL_MS,
  tickIntervalMs = DEFAULT_TICK_INTERVAL_MS,
}) => {
  const [snapshot, setSnapshot] = useState(() => getServerClockSnapshot());
  const [serverNowMs, setServerNowMs] = useState(() => getServerNowEpochMs());
  const lastResumeSyncAtRef = useRef(0);

  useEffect(() => {
    const unsubscribe = subscribeServerClock((nextSnapshot) => {
      setSnapshot(nextSnapshot);
      setServerNowMs(getServerNowEpochMs());
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    const tick = () => {
      setServerNowMs(getServerNowEpochMs());
      cacheCurrentServerClock();
    };

    tick();
    const timer = setInterval(tick, tickIntervalMs);
    return () => clearInterval(timer);
  }, [tickIntervalMs]);

  const syncFromHealthCheck = useCallback(async () => {
    try {
      const response = await api.get('/health-check', {
        headers: {
          'Cache-Control': 'no-cache',
        },
      });

      syncServerClockFromResponse(response);
    } catch (error) {
      // Keep current snapshot on network issue.
    }
  }, []);

  useEffect(() => {
    syncFromHealthCheck();

    const timer = setInterval(syncFromHealthCheck, syncIntervalMs);
    return () => clearInterval(timer);
  }, [syncFromHealthCheck, syncIntervalMs]);

  const syncAfterResume = useCallback(() => {
    setServerNowMs(getServerNowEpochMs());

    const nowMs = getMonotonicNowMs();
    if (nowMs - lastResumeSyncAtRef.current < 5000) {
      return;
    }

    lastResumeSyncAtRef.current = nowMs;
    syncFromHealthCheck();
  }, [syncFromHealthCheck]);

  useEffect(() => {
    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        syncAfterResume();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', syncAfterResume);
    window.addEventListener('online', syncAfterResume);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('focus', syncAfterResume);
      window.removeEventListener('online', syncAfterResume);
    };
  }, [syncAfterResume]);

  const value = useMemo(() => {
    const normalizedServerNowMs = Number.isFinite(Number(serverNowMs)) ? Number(serverNowMs) : null;

    return {
      ...snapshot,
      serverNowMs: normalizedServerNowMs,
      serverNowDate: normalizedServerNowMs !== null ? new Date(normalizedServerNowMs) : null,
      serverDate: normalizedServerNowMs !== null
        ? getServerDateString(normalizedServerNowMs, snapshot.timezone)
        : '',
      syncFromHealthCheck,
    };
  }, [serverNowMs, snapshot, syncFromHealthCheck]);

  return (
    <ServerClockContext.Provider value={value}>
      {children}
    </ServerClockContext.Provider>
  );
};

export default ServerClockProvider;

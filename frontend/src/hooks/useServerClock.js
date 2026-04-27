import { useContext } from 'react';
import { ServerClockContext } from '../contexts/ServerClockContext';
import {
  getServerClockSnapshot,
  getServerDateString,
  getServerNowEpochMs,
} from '../services/serverClock';

const getFallbackClockContext = () => {
  const snapshot = getServerClockSnapshot();
  const rawServerNowMs = getServerNowEpochMs();
  const serverNowMs = Number.isFinite(Number(rawServerNowMs)) ? Number(rawServerNowMs) : null;

  return {
    ...snapshot,
    serverNowMs,
    serverNowDate: serverNowMs !== null ? new Date(serverNowMs) : null,
    serverDate: serverNowMs !== null ? getServerDateString(serverNowMs, snapshot.timezone) : '',
    syncFromHealthCheck: async () => {},
  };
};

export const useServerClock = () => {
  const context = useContext(ServerClockContext);
  return context || getFallbackClockContext();
};

export default useServerClock;

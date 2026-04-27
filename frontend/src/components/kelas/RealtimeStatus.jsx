import React from 'react';
import { RefreshCw, Wifi, WifiOff, Clock } from 'lucide-react';
import { useServerClock } from '../../hooks/useServerClock';
import { formatServerTime } from '../../services/serverClock';

export const RealtimeStatus = ({ 
  isRealtime, 
  isRefreshing, 
  lastUpdated,
  onManualRefresh 
}) => {
  const { isSynced: isServerClockSynced, serverNowMs } = useServerClock();

  // Format waktu terakhir update
  const formatLastUpdated = (timestamp) => {
    if (!timestamp) return 'Belum ada pembaruan';

    if (!isServerClockSynced) {
      return 'Menunggu waktu server';
    }
    
    const updatedMs = Date.parse(timestamp);
    if (Number.isNaN(updatedMs)) {
      return 'Belum ada pembaruan';
    }

    const diffInSeconds = Math.floor((serverNowMs - updatedMs) / 1000);
    
    if (diffInSeconds < 60) {
      return 'Baru saja diperbarui';
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60);
      return `Diperbarui ${minutes} menit yang lalu`;
    } else {
      return `Diperbarui pada ${formatServerTime(updatedMs, 'id-ID') || '-'}`;
    }
  };

  return (
    <div className="flex items-center gap-2 text-sm text-gray-600">
      {/* Status realtime */}
      <div className="flex items-center gap-1">
        {isRealtime ? (
          <Wifi className="w-4 h-4 text-green-500" />
        ) : (
          <WifiOff className="w-4 h-4 text-gray-400" />
        )}
        <span className={isRealtime ? "text-green-600" : "text-gray-500"}>
          {isRealtime ? 'Realtime' : 'Manual'}
        </span>
      </div>

      {/* Divider */}
      <span className="text-gray-300">|</span>

      {/* Last updated */}
      <div className="flex items-center gap-1">
        <Clock className="w-4 h-4" />
        <span>{formatLastUpdated(lastUpdated)}</span>
      </div>

      {/* Manual refresh button */}
      <button
        onClick={onManualRefresh}
        disabled={isRefreshing}
        className={`ml-2 p-1 rounded-full transition-all duration-200
          ${isRefreshing ? 'bg-gray-100' : 'hover:bg-gray-100'}
          ${isRefreshing ? 'cursor-not-allowed' : 'cursor-pointer'}
        `}
        title="Refresh manual"
      >
        <RefreshCw 
          className={`w-4 h-4 ${isRefreshing ? 'animate-spin text-blue-500' : 'text-gray-500'}`} 
        />
      </button>
    </div>
  );
};

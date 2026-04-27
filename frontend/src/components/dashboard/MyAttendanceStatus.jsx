import React, { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle, Clock, ExternalLink, MapPin, RefreshCw, XCircle } from 'lucide-react';
import { absensiRealtimeService } from '../../services/absensiRealtimeService';
import { getServerDateString } from '../../services/serverClock';

const STATUS_LABELS = {
  hadir: 'Hadir',
  terlambat: 'Terlambat',
  izin: 'Izin',
  sakit: 'Sakit',
  alpha: 'Alpha',
  belum_absen: 'Belum Absen',
  error: 'Error',
};

const normalizeStatusKey = (rawStatus) => {
  const normalized = String(rawStatus || '').trim().toLowerCase();
  const statusMap = {
    hadir: 'hadir',
    terlambat: 'terlambat',
    izin: 'izin',
    sakit: 'sakit',
    alpha: 'alpha',
    alpa: 'alpha',
    'belum absen': 'belum_absen',
    belum_absen: 'belum_absen',
    error: 'error',
  };

  return statusMap[normalized] || 'belum_absen';
};

const hasValidCoordinate = (latitude, longitude) => (
  Number.isFinite(Number(latitude))
  && Number.isFinite(Number(longitude))
  && Number(latitude) >= -90
  && Number(latitude) <= 90
  && Number(longitude) >= -180
  && Number(longitude) <= 180
);

const formatCoordinate = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed.toFixed(6) : '-';
};

const formatAccuracy = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? `${parsed.toFixed(1)} m` : '-';
};

const mapUrl = (latitude, longitude) => (
  hasValidCoordinate(latitude, longitude) ? `https://maps.google.com/?q=${latitude},${longitude}` : null
);

const AttendancePointCard = ({
  title,
  time,
  completed,
  accentClass,
  badgeClass,
  badgeLabel,
  location,
  latitude,
  longitude,
  accuracy,
  emptyMessage,
}) => (
  <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
    <div className="flex items-start justify-between gap-3">
      <div>
        <div className="text-sm font-medium text-slate-600">{title}</div>
        <div className={`mt-2 text-2xl font-semibold ${accentClass}`}>
          {time || '--:--'}
        </div>
      </div>
      <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${badgeClass}`}>
        {badgeLabel}
      </span>
    </div>

    {completed ? (
      <div className="mt-4 space-y-2 text-sm text-slate-600">
        <div className="flex items-start justify-between gap-3">
          <span className="text-slate-500">Lokasi</span>
          <span className="text-right font-medium text-slate-900">{location || '-'}</span>
        </div>
        <div className="flex items-start justify-between gap-3">
          <span className="text-slate-500">Koordinat</span>
          <span className="text-right font-medium text-slate-900">
            {formatCoordinate(latitude)}, {formatCoordinate(longitude)}
          </span>
        </div>
        <div className="flex items-start justify-between gap-3">
          <span className="text-slate-500">Akurasi</span>
          <span className="text-right font-medium text-slate-900">{formatAccuracy(accuracy)}</span>
        </div>
        {mapUrl(latitude, longitude) ? (
          <div className="flex justify-end pt-1">
            <a
              href={mapUrl(latitude, longitude)}
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 transition hover:text-blue-700"
            >
              <MapPin className="h-3.5 w-3.5" />
              Lihat Peta
              <ExternalLink className="h-3.5 w-3.5" />
            </a>
          </div>
        ) : null}
      </div>
    ) : (
      <div className="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-500">
        {emptyMessage}
      </div>
    )}
  </div>
);

const MyAttendanceStatus = () => {
  const [myAttendance, setMyAttendance] = useState({
    date: getServerDateString(),
    has_attendance: false,
    has_checked_in: false,
    has_checked_out: false,
    status: 'belum_absen',
    status_key: 'belum_absen',
    status_label: 'Belum Absen',
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchMyAttendanceStatus = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await absensiRealtimeService.getMyAttendanceStatus();

      if (response.success) {
        setMyAttendance(response.data);
      } else {
        throw new Error(response.message || 'Gagal mengambil status absensi');
      }
    } catch (caughtError) {
      setError(caughtError.message || 'Gagal memuat status absensi');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMyAttendanceStatus();

    const interval = setInterval(fetchMyAttendanceStatus, 300000);
    return () => clearInterval(interval);
  }, []);

  const statusKey = normalizeStatusKey(myAttendance.status_key || myAttendance.status);
  const statusLabel = myAttendance.status_label || STATUS_LABELS[statusKey] || 'Belum Absen';
  const isPresenceStatus = statusKey === 'hadir' || statusKey === 'terlambat';

  const statusTone = useMemo(() => {
    switch (statusKey) {
      case 'hadir':
        return {
          icon: <CheckCircle className="h-5 w-5 text-green-500" />,
          badge: 'border-green-200 bg-green-50 text-green-700',
        };
      case 'terlambat':
        return {
          icon: <AlertTriangle className="h-5 w-5 text-amber-500" />,
          badge: 'border-amber-200 bg-amber-50 text-amber-700',
        };
      case 'izin':
      case 'sakit':
        return {
          icon: <Clock className="h-5 w-5 text-blue-500" />,
          badge: 'border-blue-200 bg-blue-50 text-blue-700',
        };
      case 'alpha':
      case 'error':
        return {
          icon: <XCircle className="h-5 w-5 text-red-500" />,
          badge: 'border-red-200 bg-red-50 text-red-700',
        };
      default:
        return {
          icon: <Clock className="h-5 w-5 text-slate-400" />,
          badge: 'border-slate-200 bg-slate-100 text-slate-700',
        };
    }
  }, [statusKey]);

  const progressText = useMemo(() => {
    if (!isPresenceStatus) {
      return statusKey === 'belum_absen' ? 'Belum ada check-in untuk hari ini.' : 'Status non-kehadiran tercatat hari ini.';
    }

    if (myAttendance.has_checked_out) {
      return 'Check-in dan check-out sudah lengkap.';
    }

    if (myAttendance.has_checked_in) {
      return 'Check-in sudah tercatat. Tunggu check-out di akhir hari.';
    }

    return 'Belum ada check-in untuk hari ini.';
  }, [isPresenceStatus, myAttendance.has_checked_in, myAttendance.has_checked_out, statusKey]);

  if (loading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="flex items-center justify-center gap-2 text-slate-600">
          <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
          <span>Memuat status absensi...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="mb-4 flex items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-red-600">
            <XCircle className="h-5 w-5" />
            <p>Error: {error}</p>
          </div>
          <button
            type="button"
            onClick={fetchMyAttendanceStatus}
            className="text-blue-600 transition hover:text-blue-700"
            title="Refresh"
          >
            <RefreshCw className="h-4 w-4" />
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
      <div className="mb-5 flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h3 className="text-base font-semibold text-slate-900 lg:text-lg">Status Absensi Hari Ini</h3>
          <p className="mt-1 text-sm text-slate-500">{myAttendance.date || getServerDateString()}</p>
        </div>
        <div className="flex items-center gap-3">
          <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium ${statusTone.badge}`}>
            {statusTone.icon}
            {statusLabel}
          </span>
          <button
            type="button"
            onClick={fetchMyAttendanceStatus}
            className="text-slate-400 transition hover:text-slate-600"
            title="Refresh"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      <div className="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        {progressText}
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <AttendancePointCard
          title="Masuk"
          time={myAttendance.check_in}
          completed={Boolean(myAttendance.has_checked_in)}
          accentClass="text-green-600"
          badgeClass={myAttendance.has_checked_in ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}
          badgeLabel={myAttendance.has_checked_in ? 'Sudah tercatat' : 'Belum tercatat'}
          location={myAttendance.location_in}
          latitude={myAttendance.latitude_in}
          longitude={myAttendance.longitude_in}
          accuracy={myAttendance.accuracy_in}
          emptyMessage={
            statusKey === 'belum_absen'
              ? 'Check-in belum dilakukan.'
              : 'Tidak ada data check-in untuk status hari ini.'
          }
        />

        <AttendancePointCard
          title="Pulang"
          time={myAttendance.check_out}
          completed={Boolean(myAttendance.has_checked_out)}
          accentClass="text-blue-600"
          badgeClass={myAttendance.has_checked_out ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600'}
          badgeLabel={myAttendance.has_checked_out ? 'Sudah tercatat' : 'Belum tercatat'}
          location={myAttendance.location_out}
          latitude={myAttendance.latitude_out}
          longitude={myAttendance.longitude_out}
          accuracy={myAttendance.accuracy_out}
          emptyMessage={
            isPresenceStatus && myAttendance.has_checked_in
              ? 'Check-out belum dilakukan.'
              : 'Belum ada data check-out untuk hari ini.'
          }
        />
      </div>

      {!myAttendance.has_attendance ? (
        <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-amber-600" />
            <p className="text-sm text-amber-800">Anda belum memiliki catatan absensi hari ini.</p>
          </div>
        </div>
      ) : null}

      {myAttendance.attendance?.keterangan ? (
        <div className="mt-4 rounded-md border border-slate-200 bg-slate-50 p-3">
          <p className="text-sm text-slate-600">
            <strong>Keterangan:</strong> {myAttendance.attendance.keterangan}
          </p>
        </div>
      ) : null}
    </div>
  );
};

export default MyAttendanceStatus;

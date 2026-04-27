import React from 'react';
import {
  Box,
  Button,
  Chip,
  Typography,
} from '@mui/material';
import {
  CalendarDays,
  Clock3,
  Download,
  Eye,
  RefreshCw,
  Settings2,
  ShieldAlert,
  Users,
  Wifi,
} from 'lucide-react';
import { useServerClock } from '../../hooks/useServerClock';
import { formatServerDate, getServerTimeString } from '../../services/serverClock';

const formatClock = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;
  return `${String(match[1]).padStart(2, '0')}:${match[2]}`;
};

const SummaryTile = ({ label, value, tone = 'slate' }) => {
  const toneMap = {
    slate: 'border-slate-200 bg-white text-slate-900',
    blue: 'border-blue-200 bg-blue-50 text-blue-900',
    green: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    amber: 'border-amber-200 bg-amber-50 text-amber-900',
    rose: 'border-rose-200 bg-rose-50 text-rose-900',
  };

  return (
    <Box className={`rounded-2xl border px-4 py-3 ${toneMap[tone] || toneMap.slate}`}>
      <Typography variant="caption" className="block text-slate-500">
        {label}
      </Typography>
      <Typography variant="h5" className="font-semibold tabular-nums">
        {value ?? 0}
      </Typography>
    </Box>
  );
};

const TrackingHeader = ({
  isSchoolHours,
  schoolHoursWindow,
  stats,
  loading,
  onRefresh,
  onExport,
  onSettings,
  onViewActiveSessions,
  canManageTrackingSession = false,
  activeSessionsCount = 0,
  liveTrackingEnabled = true,
}) => {
  const { isSynced: isServerClockSynced, serverNowMs, timezone } = useServerClock();
  const timezoneLabel = timezone || 'Asia/Jakarta';
  const timezoneSuffix = timezoneLabel === 'Asia/Jakarta' ? 'WIB' : timezoneLabel;
  const hasTrustedServerClock = isServerClockSynced && Number.isFinite(Number(serverNowMs));

  const jamMasukLabel = formatClock(schoolHoursWindow?.jamMasuk);
  const jamPulangLabel = formatClock(schoolHoursWindow?.jamPulang);
  const hasServerSchedule = Boolean(jamMasukLabel && jamPulangLabel);
  const jamLabel = hasServerSchedule
    ? `${jamMasukLabel}-${jamPulangLabel} ${timezoneSuffix}`
    : 'Jadwal belum sinkron';
  const hariLabel = Array.isArray(schoolHoursWindow?.hariKerja) && schoolHoursWindow.hariKerja.length > 0
    ? schoolHoursWindow.hariKerja.join(', ')
    : 'Hari kerja belum sinkron';
  const serverDateLabel = hasTrustedServerClock
    ? formatServerDate(serverNowMs, 'id-ID', {
        timeZone: timezoneLabel,
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      }) || '-'
    : 'Sinkronisasi waktu server';
  const serverTimeLabel = hasTrustedServerClock
    ? `${getServerTimeString(serverNowMs, timezoneLabel) || '-'} ${timezoneSuffix}`
    : 'Memuat waktu server';
  const schoolHoursLabel = hasServerSchedule
    ? (isSchoolHours ? 'Dalam jadwal' : 'Di luar jadwal')
    : 'Jadwal belum sinkron';
  const trackedCoverage = stats.total > 0
    ? `${Math.round(((stats.tracked || 0) / stats.total) * 100)}%`
    : '0%';
  const freshCoverage = stats.total > 0
    ? `${Math.round(((stats.fresh || 0) / stats.total) * 100)}%`
    : '0%';
  const needsAction = (stats.outsideArea || 0) + (stats.stale || 0) + (stats.gpsDisabled || 0) + (stats.poorGps || 0);
  const outsideSchedule = stats.outsideSchedule || 0;
  const trackingDisabled = stats.trackingDisabled || 0;

  return (
    <Box className="rounded-[28px] border border-slate-200 bg-[linear-gradient(135deg,#ffffff_0%,#f8fafc_55%,#eef6ff_100%)] p-5 shadow-sm sm:p-6">
      <Box className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
        <Box className="space-y-4">
          <Box className="flex flex-wrap items-center gap-2">
            <Chip size="small" color="primary" variant="outlined" label="Live Tracking" />
            <Chip
              size="small"
              color={!liveTrackingEnabled ? 'default' : (hasServerSchedule ? (isSchoolHours ? 'success' : 'info') : 'default')}
              icon={<Clock3 className="h-3.5 w-3.5" />}
              label={liveTrackingEnabled ? schoolHoursLabel : 'Tracking nonaktif'}
            />
            <Chip
              size="small"
              variant="outlined"
              icon={<CalendarDays className="h-3.5 w-3.5" />}
              label={serverDateLabel}
            />
            {canManageTrackingSession ? (
              <Chip
                size="small"
                color="info"
                variant="outlined"
                label={`Sesi aktif ${activeSessionsCount}`}
              />
            ) : null}
          </Box>

          <Box className="space-y-1">
            <Typography variant="h4" component="h1" className="font-semibold tracking-tight text-slate-950">
              Live Tracking Siswa
            </Typography>
            <Typography variant="body2" className="max-w-3xl text-slate-600">
              Baca kondisi umum lebih dulu, prioritaskan exception, lalu turun ke daftar dan peta hanya untuk siswa yang perlu ditindaklanjuti.
            </Typography>
          </Box>

          <Box className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <SummaryTile label="Total siswa" value={stats.total || 0} tone="slate" />
            <SummaryTile label="Sudah terekam" value={`${stats.tracked || 0} (${trackedCoverage})`} tone="blue" />
            <SummaryTile label="Realtime fresh" value={`${stats.fresh || 0} (${freshCoverage})`} tone="green" />
            <SummaryTile label="Perlu tindakan" value={needsAction} tone={needsAction > 0 ? 'amber' : 'slate'} />
          </Box>

          <Box className="flex flex-wrap gap-2 text-xs text-slate-600">
            <Chip size="small" variant="outlined" label={`Server ${serverTimeLabel}`} />
            <Chip size="small" variant="outlined" label={`Jadwal ${jamLabel}`} />
            <Chip size="small" variant="outlined" label={`Hari efektif ${hariLabel}`} />
            <Chip
              size="small"
              variant="outlined"
              color={(stats.gpsDisabled || 0) > 0 || (stats.poorGps || 0) > 0 ? 'warning' : 'default'}
              icon={<ShieldAlert className="h-3.5 w-3.5" />}
              label={`GPS bermasalah ${(stats.gpsDisabled || 0) + (stats.poorGps || 0)}`}
            />
            <Chip
              size="small"
              variant="outlined"
              color={(stats.noData || 0) > 0 ? 'default' : 'success'}
              icon={<Users className="h-3.5 w-3.5" />}
              label={`Belum ada data ${stats.noData || 0}`}
            />
            <Chip
              size="small"
              variant="outlined"
              color={(stats.fresh || 0) > 0 ? 'success' : 'default'}
              icon={<Wifi className="h-3.5 w-3.5" />}
              label={`Fresh ${stats.fresh || 0}`}
            />
            <Chip
              size="small"
              variant="outlined"
              color={trackingDisabled > 0 ? 'default' : 'default'}
              icon={<ShieldAlert className="h-3.5 w-3.5" />}
              label={`Tracking nonaktif ${trackingDisabled}`}
            />
            <Chip
              size="small"
              variant="outlined"
              color={outsideSchedule > 0 ? 'info' : 'default'}
              icon={<Clock3 className="h-3.5 w-3.5" />}
              label={`Di luar jadwal ${outsideSchedule}`}
            />
          </Box>
        </Box>

        <Box className="grid gap-2 sm:grid-cols-2 xl:min-w-[13rem] xl:grid-cols-1">
          <Button
            variant="contained"
            onClick={onRefresh}
            disabled={loading}
            startIcon={<RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />}
            className="!justify-start !rounded-2xl !px-4 !py-2.5"
          >
            Segarkan
          </Button>
          <Button
            variant="outlined"
            onClick={onExport}
            startIcon={<Download className="h-4 w-4" />}
            className="!justify-start !rounded-2xl !border-slate-300 !px-4 !py-2.5"
          >
            Export
          </Button>
          <Button
            variant="outlined"
            onClick={onSettings}
            startIcon={<Settings2 className="h-4 w-4" />}
            className="!justify-start !rounded-2xl !border-slate-300 !px-4 !py-2.5"
          >
            Pengaturan
          </Button>
          {canManageTrackingSession ? (
            <Button
              variant="outlined"
              onClick={onViewActiveSessions}
              startIcon={<Eye className="h-4 w-4" />}
              className="!justify-start !rounded-2xl !border-slate-300 !px-4 !py-2.5"
            >
              Sesi Aktif
            </Button>
          ) : null}
        </Box>
      </Box>
    </Box>
  );
};

export default TrackingHeader;

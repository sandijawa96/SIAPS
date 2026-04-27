import React from 'react';
import {
  Box,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Typography,
} from '@mui/material';
import {
  Activity,
  Clock3,
  MapPin,
  ShieldAlert,
  Wifi,
} from 'lucide-react';
import { formatServerTime } from '../../services/serverClock';

const formatClock = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;
  return `${String(match[1]).padStart(2, '0')}:${match[2]}`;
};

const percent = (value, total) => (total > 0 ? Math.round((value / total) * 100) : 0);

const ProgressRow = ({ label, value, total, barColor = '#334155', helper }) => (
  <Box className="space-y-1.5">
    <Box className="flex items-start justify-between gap-3">
      <Box>
        <Typography variant="body2" className="font-medium text-slate-800">
          {label}
        </Typography>
        {helper ? (
          <Typography variant="caption" className="text-slate-500">
            {helper}
          </Typography>
        ) : null}
      </Box>
      <Box className="text-right">
        <Typography variant="body2" className="font-semibold tabular-nums text-slate-900">
          {value}
        </Typography>
        <Typography variant="caption" className="text-slate-500">
          {percent(value, total)}%
        </Typography>
      </Box>
    </Box>
    <LinearProgress
      variant="determinate"
      value={percent(value, total)}
      className="!h-2 !rounded-full"
      sx={{
        backgroundColor: '#e2e8f0',
        '& .MuiLinearProgress-bar': {
          backgroundColor: barColor,
        },
      }}
    />
  </Box>
);

const FocusCard = ({ label, value, helper, toneClass }) => (
  <Box className={`rounded-2xl border px-4 py-3 ${toneClass}`}>
    <Typography variant="caption" className="block text-slate-500">
      {label}
    </Typography>
    <Typography variant="h5" className="font-semibold tabular-nums text-slate-950">
      {value}
    </Typography>
    <Typography variant="caption" className="text-slate-600">
      {helper}
    </Typography>
  </Box>
);

const SystemItem = ({ label, value }) => (
  <Box className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
    <Typography variant="caption" className="block text-slate-500">
      {label}
    </Typography>
    <Typography variant="body2" className="font-semibold text-slate-900">
      {value}
    </Typography>
  </Box>
);

const TrackingStats = ({ stats, isSchoolHours, lastUpdate, schoolHoursWindow, liveTrackingEnabled = true }) => {
  const total = stats.total || 0;
  const tracked = stats.tracked || 0;
  const fresh = stats.fresh || 0;
  const active = stats.active || 0;
  const outsideArea = stats.outsideArea || 0;
  const stale = stats.stale || 0;
  const gpsDisabled = stats.gpsDisabled || 0;
  const trackingDisabled = stats.trackingDisabled || 0;
  const outsideSchedule = stats.outsideSchedule || 0;
  const noData = stats.noData || 0;
  const insideArea = stats.insideArea || 0;
  const poorGps = stats.poorGps || 0;
  const moderateGps = stats.moderateGps || 0;

  const jamMasukLabel = formatClock(schoolHoursWindow?.jamMasuk);
  const jamPulangLabel = formatClock(schoolHoursWindow?.jamPulang);
  const hasServerSchedule = Boolean(jamMasukLabel && jamPulangLabel);
  const jamLabel = hasServerSchedule
    ? `${jamMasukLabel}-${jamPulangLabel} WIB`
    : 'Jadwal belum sinkron';
  const hariLabel = Array.isArray(schoolHoursWindow?.hariKerja) && schoolHoursWindow.hariKerja.length > 0
    ? schoolHoursWindow.hariKerja.join(', ')
    : 'Belum sinkron';
  const lastUpdateLabel = lastUpdate
    ? (formatServerTime(lastUpdate, 'id-ID', { hour: '2-digit', minute: '2-digit' }) || '-')
    : 'Belum ada data';

  return (
    <Box className="grid grid-cols-1 gap-4 xl:grid-cols-[1.35fr_1fr]">
      <Card className="rounded-3xl border border-slate-200 shadow-sm">
        <CardContent className="space-y-5 p-5">
          <Box className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <Box>
              <Typography variant="h6" className="font-semibold text-slate-900">
                Ringkasan Status
              </Typography>
              <Typography variant="body2" className="text-slate-600">
                Status inti yang paling perlu dibaca sebelum operator masuk ke detail siswa.
              </Typography>
            </Box>
            <Box className="flex flex-wrap gap-2">
              <Chip size="small" variant="outlined" icon={<Wifi className="h-3.5 w-3.5" />} label={`Fresh ${fresh}`} />
              <Chip size="small" variant="outlined" icon={<Activity className="h-3.5 w-3.5" />} label={`Fresh dalam area ${active}`} />
              <Chip size="small" variant="outlined" icon={<MapPin className="h-3.5 w-3.5" />} label={`Dalam area ${insideArea}`} />
              <Chip size="small" variant="outlined" icon={<ShieldAlert className="h-3.5 w-3.5" />} label={`Tracking nonaktif ${trackingDisabled}`} />
              <Chip size="small" variant="outlined" icon={<Clock3 className="h-3.5 w-3.5" />} label={`Di luar jadwal ${outsideSchedule}`} />
              <Chip size="small" variant="outlined" icon={<ShieldAlert className="h-3.5 w-3.5" />} label={`GPS lemah ${poorGps + moderateGps}`} />
            </Box>
          </Box>

          <Box className="space-y-4">
            <ProgressRow label="Sudah terekam" value={tracked} total={total} barColor="#2563eb" helper="Siswa yang sudah punya snapshot hari ini" />
            <ProgressRow label="Realtime fresh" value={fresh} total={total} barColor="#16a34a" helper="Snapshot masih dalam ambang realtime" />
            <ProgressRow label="Dalam area sekolah" value={insideArea} total={total} barColor="#0284c7" helper="Posisi berada di dalam geofence" />
            <ProgressRow label="Fresh luar area" value={outsideArea} total={total} barColor="#f97316" helper="Perlu konfirmasi lokasi" />
            <ProgressRow label="Tracking nonaktif" value={trackingDisabled} total={total} barColor="#64748b" helper="Tracking realtime dihentikan oleh admin" />
            <ProgressRow label="Di luar jadwal" value={outsideSchedule} total={total} barColor="#64748b" helper="Tracking dijeda karena di luar hari atau jam sekolah" />
            <ProgressRow label="Belum ada data" value={noData} total={total} barColor="#94a3b8" helper="Belum kirim titik hari ini" />
          </Box>
        </CardContent>
      </Card>

      <Box className="grid grid-cols-1 gap-4">
        <Card className="rounded-3xl border border-slate-200 shadow-sm">
          <CardContent className="space-y-4 p-5">
            <Box className="flex items-center gap-2">
              <Activity className="h-5 w-5 text-amber-600" />
              <Typography variant="h6" className="font-semibold text-slate-900">
                Fokus Cepat
              </Typography>
            </Box>

            <Box className="grid grid-cols-2 gap-3">
              <FocusCard label="Stale" value={stale} helper="Butuh refresh snapshot" toneClass="border-amber-200 bg-amber-50" />
              <FocusCard label="GPS mati" value={gpsDisabled} helper="Posisi tidak sedang aktif" toneClass="border-rose-200 bg-rose-50" />
              <FocusCard label="GPS lemah" value={poorGps} helper="Akurasi rendah" toneClass="border-red-200 bg-red-50" />
              <FocusCard label="Luar area" value={outsideArea} helper="Fresh tapi di luar geofence" toneClass="border-orange-200 bg-orange-50" />
            </Box>

            <Box className="flex flex-wrap gap-2">
              <Chip size="small" color={liveTrackingEnabled ? 'success' : 'default'} label={liveTrackingEnabled ? 'Tracking aktif' : 'Tracking nonaktif'} />
              <Chip size="small" color={isSchoolHours ? 'success' : 'info'} label={isSchoolHours ? 'Dalam jadwal' : 'Di luar jadwal'} />
              <Chip size="small" variant="outlined" label={`GPS sedang ${moderateGps}`} />
            </Box>
          </CardContent>
        </Card>

        <Card className="rounded-3xl border border-slate-200 shadow-sm">
          <CardContent className="space-y-4 p-5">
            <Box className="flex items-center gap-2">
              <Clock3 className="h-5 w-5 text-slate-700" />
              <Typography variant="h6" className="font-semibold text-slate-900">
                Konteks Sistem
              </Typography>
            </Box>

            <Box className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <SystemItem label="Operasional tracking" value={liveTrackingEnabled ? 'Aktif' : 'Dinonaktifkan admin'} />
              <SystemItem label="Status jadwal" value={hasServerSchedule ? (isSchoolHours ? 'Dalam jadwal' : 'Di luar jadwal') : 'Belum sinkron'} />
              <SystemItem label="Jadwal" value={jamLabel} />
              <SystemItem label="Hari efektif" value={hariLabel} />
              <SystemItem label="Update terakhir" value={lastUpdateLabel} />
            </Box>
          </CardContent>
        </Card>
      </Box>
    </Box>
  );
};

export default TrackingStats;

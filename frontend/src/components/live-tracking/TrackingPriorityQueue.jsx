import React from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Typography,
} from '@mui/material';
import {
  ArrowRight,
  Clock3,
  MapPin,
  ShieldAlert,
} from 'lucide-react';
import { formatServerDateTime } from '../../services/serverClock';
import { getTrackingStatusReasonLabel } from '../../utils/trackingStatus';

const QUEUE_CONFIG = [
  {
    key: 'gpsDisabled',
    title: 'GPS mati',
    icon: ShieldAlert,
    toneClass: 'border-rose-200 bg-rose-50/70',
    chipColor: 'error',
    emptyText: 'Tidak ada perangkat GPS mati pada scope ini.',
  },
  {
    key: 'stale',
    title: 'Stale',
    icon: Clock3,
    toneClass: 'border-amber-200 bg-amber-50/70',
    chipColor: 'warning',
    emptyText: 'Tidak ada snapshot stale pada scope ini.',
  },
  {
    key: 'outsideArea',
    title: 'Luar area',
    icon: MapPin,
    toneClass: 'border-orange-200 bg-orange-50/70',
    chipColor: 'warning',
    emptyText: 'Tidak ada siswa fresh di luar area pada scope ini.',
  },
];

const QueueCard = ({
  title,
  rows,
  icon: Icon,
  toneClass,
  chipColor,
  emptyText,
  onStudentSelect,
  onViewDetails,
}) => (
  <Card className={`h-full rounded-3xl border shadow-sm ${toneClass}`}>
    <CardContent className="space-y-4 p-5">
      <Box className="flex items-start justify-between gap-3">
        <Box className="flex items-center gap-2">
          <Icon className="h-5 w-5" />
          <Typography variant="h6" className="font-semibold text-slate-900">
            {title}
          </Typography>
        </Box>
        <Chip size="small" color={chipColor} variant="outlined" label={rows.length} />
      </Box>

      {rows.length === 0 ? (
        <Box className="rounded-2xl border border-dashed border-slate-200 bg-white/80 px-4 py-6 text-center">
          <Typography variant="body2" className="text-slate-500">
            {emptyText}
          </Typography>
        </Box>
      ) : (
        <Box className="space-y-3">
          {rows.map((student) => (
            <Box key={`${title}-${student.id}`} className="rounded-2xl border border-white/80 bg-white/95 p-3 shadow-sm">
              <Box className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <Box className="min-w-0 flex-1">
                  <Typography variant="subtitle2" className="truncate font-semibold text-slate-900">
                    {student.name}
                  </Typography>
                  <Typography variant="caption" className="block text-slate-600">
                    {student.class || 'Tanpa kelas'}
                  </Typography>
                  <Typography variant="body2" className="mt-2 text-slate-700">
                    {getTrackingStatusReasonLabel(student.trackingStatusReason, student.status)}
                  </Typography>
                  <Typography variant="caption" className="mt-1 block text-slate-500">
                    {student.lastUpdate
                      ? (formatServerDateTime(student.lastUpdate, 'id-ID') || '-')
                      : 'Belum ada data'}
                  </Typography>
                </Box>

                <Box className="flex flex-wrap gap-2 lg:justify-end">
                  <Button size="small" variant="outlined" onClick={() => onStudentSelect(student)}>
                    Fokus peta
                  </Button>
                  <Button size="small" variant="contained" endIcon={<ArrowRight className="h-3.5 w-3.5" />} onClick={() => onViewDetails(student)}>
                    Detail
                  </Button>
                </Box>
              </Box>
            </Box>
          ))}
        </Box>
      )}
    </CardContent>
  </Card>
);

const TrackingPriorityQueue = ({
  queues,
  loading,
  onStudentSelect,
  onViewDetails,
}) => {
  if (loading) {
    return (
      <Card className="rounded-3xl border border-slate-200 shadow-sm">
        <CardContent className="flex items-center justify-center gap-3 py-12">
          <CircularProgress size={28} />
          <Typography variant="body2" className="text-slate-600">
            Memuat kasus prioritas...
          </Typography>
        </CardContent>
      </Card>
    );
  }

  const totalItems = Object.values(queues || {}).reduce((sum, items) => sum + (Array.isArray(items) ? items.length : 0), 0);

  return (
    <Box className="space-y-4">
      <Box className="flex flex-wrap gap-2">
        <Chip size="small" color="primary" variant="outlined" label={`Kasus prioritas ${totalItems}`} />
        <Chip size="small" variant="outlined" label="Hanya item terbaru per kategori" />
      </Box>

      <Box className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {QUEUE_CONFIG.map((queue) => (
          <QueueCard
            key={queue.key}
            title={queue.title}
            rows={queues?.[queue.key] || []}
            icon={queue.icon}
            toneClass={queue.toneClass}
            chipColor={queue.chipColor}
            emptyText={queue.emptyText}
            onStudentSelect={onStudentSelect}
            onViewDetails={onViewDetails}
          />
        ))}
      </Box>
    </Box>
  );
};

export default TrackingPriorityQueue;

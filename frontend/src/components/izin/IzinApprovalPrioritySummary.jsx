import React from 'react';
import PropTypes from 'prop-types';
import { AlertTriangle, CalendarClock, CalendarRange, Inbox } from 'lucide-react';
import { Box, Paper, Typography } from '@mui/material';

const CARD_CONFIG = [
  {
    key: 'due_today',
    label: 'Mulai Hari Ini',
    helper: 'Perlu keputusan di hari yang sama',
    icon: CalendarClock,
    iconClass: 'text-amber-600',
    surfaceClass: 'bg-amber-50 border-amber-200',
  },
  {
    key: 'overdue',
    label: 'Terlambat Review',
    helper: 'Sudah lewat tanggal mulai izin',
    icon: AlertTriangle,
    iconClass: 'text-rose-600',
    surfaceClass: 'bg-rose-50 border-rose-200',
  },
  {
    key: 'upcoming',
    label: 'Pending Mendatang',
    helper: 'Mulai besok atau setelahnya',
    icon: CalendarRange,
    iconClass: 'text-blue-600',
    surfaceClass: 'bg-blue-50 border-blue-200',
  },
  {
    key: 'total_pending',
    label: 'Total Pending',
    helper: 'Queue review sesuai filter aktif',
    icon: Inbox,
    iconClass: 'text-slate-700',
    surfaceClass: 'bg-slate-50 border-slate-200',
  },
];

const getValue = (summary, key) => Number(summary?.[key] || 0);

const IzinApprovalPrioritySummary = ({ summary, loading }) => {
  const urgent = Number(summary?.urgent || 0);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        {CARD_CONFIG.map((item) => {
          const Icon = item.icon;
          const value = loading ? '...' : getValue(summary, item.key);

          return (
            <Paper key={item.key} className={`rounded-2xl border p-5 shadow-sm ${item.surfaceClass}`}>
              <Box className="flex items-start justify-between gap-3">
                <div>
                  <Typography variant="body2" className="font-medium text-slate-600">
                    {item.label}
                  </Typography>
                  <Typography variant="h4" className="mt-1 font-bold text-slate-900">
                    {value}
                  </Typography>
                  <Typography variant="caption" className="mt-2 block text-slate-600">
                    {item.helper}
                  </Typography>
                </div>
                <div className="rounded-xl bg-white/80 p-3 shadow-sm">
                  <Icon className={`h-5 w-5 ${item.iconClass}`} />
                </div>
              </Box>
            </Paper>
          );
        })}
      </div>

      <Paper className="rounded-2xl border border-slate-200 p-4 shadow-sm">
        <Typography variant="body2" className="font-semibold text-slate-900">
          Prioritas review
        </Typography>
        <Typography variant="body2" className="mt-1 text-slate-600">
          {loading
            ? 'Menghitung prioritas review...'
            : urgent > 0
              ? `${urgent} pengajuan perlu didahulukan karena sudah mulai hari ini atau terlambat direview.`
              : 'Tidak ada pengajuan mendesak pada filter saat ini.'}
        </Typography>
      </Paper>
    </div>
  );
};

IzinApprovalPrioritySummary.propTypes = {
  summary: PropTypes.shape({
    total_pending: PropTypes.number,
    due_today: PropTypes.number,
    overdue: PropTypes.number,
    upcoming: PropTypes.number,
    urgent: PropTypes.number,
  }),
  loading: PropTypes.bool,
};

IzinApprovalPrioritySummary.defaultProps = {
  summary: {
    total_pending: 0,
    due_today: 0,
    overdue: 0,
    upcoming: 0,
    urgent: 0,
  },
  loading: false,
};

export default IzinApprovalPrioritySummary;

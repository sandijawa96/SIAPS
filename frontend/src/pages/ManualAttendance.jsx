import React, { useEffect, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography,
} from '@mui/material';
import {
  AlertCircle,
  CheckCircle,
  Clock,
  Database,
  Download,
  Edit,
  Eye,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  User,
} from 'lucide-react';
import { useManualAttendance } from '../hooks/useManualAttendance';
import { useAuth } from '../hooks/useAuth';
import { formatServerDate, formatServerDateTime, formatServerTime, getServerNowDate, toServerCalendarDate, toServerDateInput } from '../services/serverClock';
import ManualAttendanceModal from '../components/ManualAttendanceModal';
import ManualAttendanceBulkModal from '../components/ManualAttendanceBulkModal';
import ManualAttendanceIncidentModal from '../components/ManualAttendanceIncidentModal';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'hadir', label: 'Hadir' },
  { value: 'terlambat', label: 'Terlambat' },
  { value: 'izin', label: 'Izin' },
  { value: 'sakit', label: 'Sakit' },
  { value: 'alpha', label: 'Alpha' },
];

const formatDate = (dateString) => {
  if (!dateString) {
    return '-';
  }

  try {
    const parsed = toServerCalendarDate(dateString);
    if (!parsed) {
      return String(dateString);
    }
    return formatServerDate(parsed, 'id-ID') || String(dateString);
  } catch {
    return dateString;
  }
};

const formatTime = (timeString) => {
  if (!timeString) {
    return '-';
  }

  const raw = String(timeString).trim();
  if (!raw) {
    return '-';
  }

  const parsedTimestamp = Date.parse(raw);
  if (!Number.isNaN(parsedTimestamp)) {
    return formatServerTime(parsedTimestamp, 'id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }) || raw;
  }

  const directTimeMatch = raw.match(/^(\d{2}:\d{2})(?::\d{2})?$/);
  if (directTimeMatch) {
    return directTimeMatch[1];
  }

  const isoTimeMatch = raw.match(/T(\d{2}:\d{2})(?::\d{2})?/);
  if (isoTimeMatch) {
    return isoTimeMatch[1];
  }

  const genericTimeMatch = raw.match(/\b(\d{2}:\d{2})(?::\d{2})?\b/);
  if (genericTimeMatch) {
    return genericTimeMatch[1];
  }

  return raw;
};

const getDayGapFromToday = (dateString) => {
  if (!dateString) {
    return 0;
  }

  try {
    const target = toServerCalendarDate(dateString);

    if (!target || Number.isNaN(target.getTime())) {
      return 0;
    }

    const today = toServerCalendarDate(getServerNowDate()) || getServerNowDate();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    return Math.floor((today.getTime() - target.getTime()) / 86400000);
  } catch {
    return 0;
  }
};

const getStatusChip = (status) => {
  const statusMap = {
    hadir: { color: 'success', label: 'Hadir' },
    terlambat: { color: 'warning', label: 'Terlambat' },
    izin: { color: 'info', label: 'Izin' },
    sakit: { color: 'secondary', label: 'Sakit' },
    alpha: { color: 'error', label: 'Alpha' },
  };

  const config = statusMap[status] || { color: 'default', label: status || '-' };
  return <Chip size="small" color={config.color} label={config.label} />;
};

const getIncidentBatchStatusConfig = (status, progressPercentage = 0) => {
  const normalizedStatus = String(status || '').toLowerCase();

  if (normalizedStatus === 'completed') {
    return {
      color: 'success',
      label: 'Selesai',
      detail: 'Batch selesai diproses.',
    };
  }

  if (normalizedStatus === 'failed') {
    return {
      color: 'error',
      label: 'Gagal',
      detail: 'Batch gagal diproses.',
    };
  }

  if (normalizedStatus === 'processing') {
    return {
      color: 'info',
      label: `Sedang diproses ${progressPercentage || 0}%`,
      detail: 'Aktif diproses di background.',
    };
  }

  return {
    color: 'warning',
    label: 'Dalam antrean',
    detail: 'Menunggu worker memproses batch.',
  };
};

const getAuditEntries = (attendance) => {
  if (Array.isArray(attendance?.audit_logs)) {
    return attendance.audit_logs;
  }

  if (Array.isArray(attendance?.auditLogs)) {
    return attendance.auditLogs;
  }

  return [];
};

const getLeaveApprovalAuditInfo = (attendance) => {
  const auditEntries = getAuditEntries(attendance);
  if (auditEntries.length === 0) {
    return null;
  }

  const sorted = [...auditEntries].sort((left, right) => {
    const leftTime = Date.parse(left?.performed_at || left?.created_at || '') || 0;
    const rightTime = Date.parse(right?.performed_at || right?.created_at || '') || 0;
    return rightTime - leftTime;
  });

  const matched = sorted.find((entry) => entry?.metadata?.source === 'leave_approval');
  if (!matched) {
    return null;
  }

  return {
    izinId: matched?.metadata?.izin_id || attendance?.izin_id || null,
    previousStatus: matched?.metadata?.previous_status || null,
    resolvedStatus: matched?.metadata?.resolved_status || attendance?.status || null,
    performedAt: matched?.performed_at || matched?.created_at || null,
  };
};

const getAttendanceSourceBadge = (attendance) => {
  const leaveApprovalInfo = getLeaveApprovalAuditInfo(attendance);
  if (leaveApprovalInfo) {
    return {
      label: 'Hasil Approval Izin',
      className: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    };
  }

  if (!attendance?.is_manual && String(attendance?.status || '').toLowerCase() === 'alpha') {
    return {
      label: 'Auto Alpha',
      className: 'bg-red-50 text-red-700 border border-red-200',
    };
  }

  if (attendance?.is_manual) {
    return {
      label: 'Manual',
      className: 'bg-blue-50 text-blue-700 border border-blue-200',
    };
  }

  return {
    label: 'Realtime',
    className: 'bg-slate-50 text-slate-700 border border-slate-200',
  };
};

const SOURCE_SUMMARY_CARDS = [
  {
    key: 'realtime',
    label: 'Realtime',
    iconClass: 'text-slate-600',
    surfaceClass: 'bg-slate-50 border-slate-200',
  },
  {
    key: 'manual',
    label: 'Manual',
    iconClass: 'text-blue-600',
    surfaceClass: 'bg-blue-50 border-blue-200',
  },
  {
    key: 'auto_alpha',
    label: 'Auto Alpha',
    iconClass: 'text-red-600',
    surfaceClass: 'bg-red-50 border-red-200',
  },
  {
    key: 'leave_approval',
    label: 'Approval Izin',
    iconClass: 'text-emerald-600',
    surfaceClass: 'bg-emerald-50 border-emerald-200',
  },
];

const ManualAttendance = () => {
  const { hasPermission } = useAuth();
  const {
    attendanceList = [],
    historyMeta = {},
    pendingCheckoutList = [],
    pendingCheckoutMeta = {},
    users = [],
    statistics = {},
    loading,
    pendingLoading,
    resolvingPending,
    error,
    filters = {},
    setFilters,
    createAttendance,
    updateAttendance,
    previewBulkAttendance,
    bulkCreateAttendance,
    bulkCorrectAttendance,
    incidentOptions,
    incidentOptionsLoading,
    recentIncidentBatches,
    recentIncidentBatchesLoading,
    recentIncidentBatchesRefreshedAt,
    getIncidentOptions,
    getRecentIncidentBatches,
    previewIncidentAttendance,
    createIncidentAttendance,
    getIncidentAttendanceStatus,
    exportIncidentAttendance,
    deleteAttendance,
    getHistory,
    getStatistics,
    getPendingCheckout,
    resolvePendingCheckout,
    exportData,
  } = useManualAttendance();

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showBulkModal, setShowBulkModal] = useState(false);
  const [showIncidentModal, setShowIncidentModal] = useState(false);
  const [selectedIncidentBatch, setSelectedIncidentBatch] = useState(null);
  const [selectedAttendance, setSelectedAttendance] = useState(null);
  const [attendanceToDelete, setAttendanceToDelete] = useState(null);
  const [resolveTarget, setResolveTarget] = useState(null);
  const [activeDomain, setActiveDomain] = useState('manual_entry');
  const [correctionView, setCorrectionView] = useState('all_attendance');
  const [resolveForm, setResolveForm] = useState({
    jam_pulang: '',
    reason: '',
    status: 'hadir',
    keterangan: '',
    override_reason: '',
  });
  const [resolveErrors, setResolveErrors] = useState({});
  const [searchTerm, setSearchTerm] = useState('');
  const [pendingFilters, setPendingFilters] = useState({
    user_id: '',
    date: '',
    include_overdue: false,
    page: 1,
    per_page: 10,
  });

  const canOverrideBackdate = hasPermission('manual_attendance_backdate_override');

  useEffect(() => {
    const requestFilters = {
      ...pendingFilters,
      include_overdue: canOverrideBackdate ? pendingFilters.include_overdue : false,
    };
    getPendingCheckout(requestFilters);
  }, [canOverrideBackdate, getPendingCheckout, pendingFilters]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      const normalizedSearch = searchTerm.trim();
      setFilters((prev) => {
        if (prev.search === normalizedSearch && prev.page === 1) {
          return prev;
        }

        return {
          ...prev,
          search: normalizedSearch,
          page: 1,
        };
      });
    }, 400);

    return () => clearTimeout(timeoutId);
  }, [searchTerm, setFilters]);

  const isManualDomain = activeDomain === 'manual_entry';
  const isCorrectionDomain = activeDomain === 'correction';
  const isPendingView = isCorrectionDomain && correctionView === 'pending_checkout';
  const isAutoAlphaView = isCorrectionDomain && correctionView === 'auto_alpha';
  const isCorrectionAllView = isCorrectionDomain && correctionView === 'all_attendance';
  const activeIncidentBatches = recentIncidentBatches.filter((batch) =>
    ['queued', 'processing'].includes(String(batch?.status || '').toLowerCase())
  );
  const activeIncidentBatchCount = activeIncidentBatches.length;

  useEffect(() => {
    if (isPendingView) {
      return;
    }

    setFilters((prev) => {
      let nextBucket = 'manual';
      let nextStatus = prev.status;

      if (isManualDomain) {
        nextBucket = 'manual';
        if (prev.bucket === 'auto_alpha') {
          nextStatus = '';
        }
      } else if (isCorrectionAllView) {
        nextBucket = 'correction';
        if (prev.bucket === 'auto_alpha' && prev.status === 'alpha') {
          nextStatus = '';
        }
      } else if (isAutoAlphaView) {
        nextBucket = 'auto_alpha';
        nextStatus = 'alpha';
      }

      if (
        prev.bucket === nextBucket &&
        prev.status === nextStatus &&
        prev.page === 1
      ) {
        return prev;
      }

      return {
        ...prev,
        bucket: nextBucket,
        status: nextStatus,
        page: 1,
      };
    });
  }, [isAutoAlphaView, isCorrectionAllView, isManualDomain, isPendingView, setFilters]);

  useEffect(() => {
    if (!isManualDomain || activeIncidentBatchCount === 0) {
      return undefined;
    }

    const timer = setInterval(() => {
      getRecentIncidentBatches().catch(() => {});
    }, 4000);

    return () => clearInterval(timer);
  }, [activeIncidentBatchCount, getRecentIncidentBatches, isManualDomain]);

  const handleCreateAttendance = async (data) => {
    try {
      await createAttendance(data);
      setShowCreateModal(false);
    } catch (createError) {
      console.error('Error creating attendance:', createError);
    }
  };

  const handleUpdateAttendance = async (id, data) => {
    try {
      await updateAttendance(id, data);
      setSelectedAttendance(null);
    } catch (updateError) {
      console.error('Error updating attendance:', updateError);
    }
  };

  const handleBulkAttendance = async (operation, attendanceList) => {
    if (operation === 'correct_existing') {
      return bulkCorrectAttendance(attendanceList);
    }

    return bulkCreateAttendance(attendanceList);
  };

  const handleBulkPreview = async (operation, attendanceList) => (
    previewBulkAttendance(operation, attendanceList)
  );

  const handleOpenIncidentModal = async (incidentBatch = null) => {
    setSelectedIncidentBatch(incidentBatch);
    setShowIncidentModal(true);
    if (!incidentOptions) {
      try {
        await getIncidentOptions();
      } catch {
        // handled in hook
      }
    }
  };

  const handleCloseIncidentModal = () => {
    setShowIncidentModal(false);
    setSelectedIncidentBatch(null);
  };

  const handleRefreshIncidentBatch = async (batchId) => {
    const nextBatch = await getIncidentAttendanceStatus(batchId);

    if (selectedIncidentBatch?.id === nextBatch?.id) {
      setSelectedIncidentBatch(nextBatch);
    }

    if (!['queued', 'processing'].includes(String(nextBatch?.status || ''))) {
      getRecentIncidentBatches().catch(() => {});
    }

    return nextBatch;
  };

  const handleDeleteAttendance = (attendance) => {
    setAttendanceToDelete(attendance);
  };

  const confirmDeleteAttendance = async () => {
    if (!attendanceToDelete?.id) {
      return;
    }
    try {
      await deleteAttendance(attendanceToDelete.id);
      setAttendanceToDelete(null);
    } catch (deleteError) {
      console.error('Error deleting attendance:', deleteError);
    }
  };

  const handlePendingFilterChange = (key, value) => {
    setPendingFilters((prev) => ({
      ...prev,
      [key]: value,
      page: key === 'page' ? value : 1,
    }));
  };

  const handleDomainChange = (_, nextDomain) => {
    setActiveDomain(nextDomain);
  };

  const handleCorrectionViewChange = (_, nextView) => {
    setCorrectionView(nextView);
  };

  const openResolveModal = (attendance) => {
    setResolveTarget(attendance);
    setResolveErrors({});
    setResolveForm({
      jam_pulang: '',
      reason: 'Follow up lupa tap-out',
      status: attendance?.status || 'hadir',
      keterangan: attendance?.keterangan || '',
      override_reason: '',
    });
  };

  const closeResolveModal = () => {
    if (resolvingPending) {
      return;
    }
    setResolveTarget(null);
    setResolveErrors({});
  };

  const submitResolveCheckout = async () => {
    if (!resolveTarget?.id) {
      return;
    }

    const dayGap = getDayGapFromToday(resolveTarget?.tanggal);
    const requiresOverride = dayGap > 1;
    const nextErrors = {};

    if (!resolveForm.jam_pulang) {
      nextErrors.jam_pulang = 'Jam pulang wajib diisi';
    }

    if (!resolveForm.reason || resolveForm.reason.trim().length < 10) {
      nextErrors.reason = 'Alasan minimal 10 karakter';
    }

    if (requiresOverride && !canOverrideBackdate) {
      nextErrors.override_reason = 'Anda tidak memiliki izin override H+N';
    }

    if (requiresOverride && canOverrideBackdate && (!resolveForm.override_reason || resolveForm.override_reason.trim().length < 10)) {
      nextErrors.override_reason = 'Alasan override minimal 10 karakter';
    }

    if (Object.keys(nextErrors).length > 0) {
      setResolveErrors(nextErrors);
      return;
    }

    try {
      await resolvePendingCheckout(
        resolveTarget.id,
        resolveForm,
        {
          ...pendingFilters,
          include_overdue: canOverrideBackdate ? pendingFilters.include_overdue : false,
        }
      );
      closeResolveModal();
    } catch (resolveError) {
      console.error('Error resolving checkout:', resolveError);
    }
  };

  const totalRows = Number(historyMeta.total || attendanceList.length || 0);
  const safePage = Number(historyMeta.current_page || filters.page || 1);
  const safePerPage = Number(historyMeta.per_page || filters.per_page || 15);
  const lastPage = Math.max(1, Number(historyMeta.last_page || 1));
  const from = Number(historyMeta.from || (totalRows > 0 ? (safePage - 1) * safePerPage + 1 : 0));
  const to = Number(historyMeta.to || Math.min(safePage * safePerPage, totalRows));
  const pendingCurrentPage = pendingCheckoutMeta?.current_page || pendingFilters.page || 1;
  const pendingLastPage = pendingCheckoutMeta?.last_page || 1;
  const pendingTotalRows = pendingCheckoutMeta?.total || 0;
  const resolveDayGap = getDayGapFromToday(resolveTarget?.tanggal);
  const resolveNeedsOverride = resolveDayGap > 1;
  const totalEntries = Number(statistics?.total_entries ?? statistics?.total_manual_entries ?? 0);
  const sourceSummary = statistics?.by_source || {};
  const summaryCards = isManualDomain
    ? [
        { label: 'Total Manual', value: totalEntries, icon: CheckCircle, iconClass: 'text-green-600' },
        { label: 'Hadir', value: statistics?.by_status?.hadir || 0, icon: User, iconClass: 'text-blue-600' },
        { label: 'Terlambat', value: statistics?.by_status?.terlambat || 0, icon: Clock, iconClass: 'text-yellow-600' },
        { label: 'Alpha', value: statistics?.by_status?.alpha || 0, icon: AlertCircle, iconClass: 'text-red-600' },
      ]
    : isAutoAlphaView
    ? [
        { label: 'Total Tidak Absen', value: totalEntries, icon: AlertCircle, iconClass: 'text-red-600' },
        { label: 'Alpha Otomatis', value: statistics?.by_status?.alpha || 0, icon: AlertCircle, iconClass: 'text-amber-600' },
        { label: 'Menit Alpha', value: statistics?.alpa_menit || 0, icon: Clock, iconClass: 'text-orange-600' },
        { label: 'Siap Dikoreksi', value: totalEntries, icon: Edit, iconClass: 'text-blue-600' },
      ]
    : [
        { label: 'Total Tercatat', value: totalEntries, icon: CheckCircle, iconClass: 'text-blue-600' },
        { label: 'Hadir', value: statistics?.by_status?.hadir || 0, icon: User, iconClass: 'text-blue-600' },
        { label: 'Terlambat', value: statistics?.by_status?.terlambat || 0, icon: Clock, iconClass: 'text-yellow-600' },
        { label: 'Alpha', value: statistics?.by_status?.alpha || 0, icon: AlertCircle, iconClass: 'text-red-600' },
      ];
  const correctionSourceCards = SOURCE_SUMMARY_CARDS.map((card) => ({
    ...card,
    value: Number(sourceSummary?.[card.key] || 0),
  }));

  const resetFilter = () => {
    setSearchTerm('');
    setFilters(() => ({
      bucket: isManualDomain ? 'manual' : (isAutoAlphaView ? 'auto_alpha' : 'correction'),
      status: isAutoAlphaView ? 'alpha' : '',
      date: '',
      user_id: '',
      start_date: '',
      end_date: '',
      search: '',
      page: 1,
      per_page: 15,
    }));
  };

  const resetPendingFilter = () => {
    setPendingFilters((prev) => ({
      ...prev,
      user_id: '',
      date: '',
      include_overdue: false,
      page: 1,
    }));
  };

  return (
    <div className="p-6 space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <Box className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-xl">
            <Clock className="w-6 h-6 text-blue-600" />
          </div>
          <div className="flex-1">
            <Typography variant="h5" className="font-bold text-gray-900">
              Pengelolaan Absensi
            </Typography>
            <Typography variant="body2" className="text-gray-600 mt-1">
              Pisahkan pembuatan absensi yang belum ada dari koreksi data absensi yang sudah tercatat.
            </Typography>
            <div className="flex flex-wrap gap-2 mt-3">
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                Absensi Manual
              </span>
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                Koreksi Absensi
              </span>
            </div>
          </div>
        </Box>
      </div>

      {error && (
        <Alert severity="error" className="rounded-xl" icon={<AlertCircle className="w-4 h-4" />}>
          {error}
        </Alert>
      )}

      <Paper className="rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <Tabs
          value={activeDomain}
          onChange={handleDomainChange}
          variant="fullWidth"
          sx={{
            '& .MuiTab-root': {
              textTransform: 'none',
              fontWeight: 600,
              minHeight: 58,
            },
          }}
        >
          <Tab value="manual_entry" label="Absensi Manual" />
          <Tab value="correction" label="Koreksi Absensi" />
        </Tabs>
      </Paper>

      {isCorrectionDomain && (
        <Paper className="rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <Tabs
            value={correctionView}
            onChange={handleCorrectionViewChange}
            variant="fullWidth"
            sx={{
              '& .MuiTab-root': {
                textTransform: 'none',
                fontWeight: 600,
                minHeight: 54,
              },
            }}
          >
            <Tab value="all_attendance" label="Semua Absensi" />
            <Tab value="auto_alpha" label="Tidak Absen" />
            <Tab value="pending_checkout" label="Lupa Tap-Out" />
          </Tabs>
        </Paper>
      )}

      {!isPendingView && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {summaryCards.map((card) => {
            const Icon = card.icon;

            return (
              <div key={card.label} className="bg-white rounded-xl border border-gray-200 p-5">
                <div className="flex items-center space-x-3">
                  <Icon className={`w-8 h-8 ${card.iconClass}`} />
                  <div>
                    <p className="text-sm text-gray-600">{card.label}</p>
                    <p className="text-2xl font-bold text-gray-900">{card.value}</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {isCorrectionDomain && !isPendingView && (
        <Paper className="p-5 rounded-2xl border border-gray-200 shadow-sm">
          <div className="mb-4">
            <Typography variant="subtitle1" className="font-semibold text-gray-900">
              Komposisi Sumber Data
            </Typography>
            <Typography variant="body2" className="text-gray-600">
              Ringkasan ini membantu memisahkan data koreksi dari sumber realtime, manual, auto alpha, dan approval izin.
            </Typography>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            {correctionSourceCards.map((card) => (
              <div
                key={card.key}
                className={`rounded-xl border p-4 ${card.surfaceClass}`}
              >
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm text-gray-600">{card.label}</p>
                    <p className="text-2xl font-bold text-gray-900">{card.value}</p>
                  </div>
                  <div className={`text-xs font-semibold uppercase tracking-wide ${card.iconClass}`}>
                    Source
                  </div>
                </div>
              </div>
            ))}
          </div>
        </Paper>
      )}

      {isPendingView && (
      <Paper className="p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="mb-4">
          <Typography variant="subtitle1" className="font-semibold text-gray-900">
            Inbox Lupa Tap-Out
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Default menampilkan kasus H+1. Untuk backlog di atas H+1, aktifkan override jika permission tersedia.
          </Typography>
        </div>

        <Box className="flex flex-col lg:flex-row gap-3 mb-4">
          <FormControl size="small" sx={{ minWidth: 220 }}>
            <Select
              displayEmpty
              value={pendingFilters.user_id || ''}
              onChange={(event) => handlePendingFilterChange('user_id', event.target.value)}
            >
              <MenuItem value="">Semua Siswa</MenuItem>
              {users.map((user) => (
                <MenuItem key={user.id} value={String(user.id)}>
                  {user.nama_lengkap || user.name || '-'}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <TextField
            type="date"
            size="small"
            value={pendingFilters.date || ''}
            onChange={(event) => handlePendingFilterChange('date', event.target.value)}
            sx={{ minWidth: 190 }}
          />

          {canOverrideBackdate && (
            <FormControlLabel
              control={(
                <Checkbox
                  checked={Boolean(pendingFilters.include_overdue)}
                  onChange={(event) => handlePendingFilterChange('include_overdue', event.target.checked)}
                />
              )}
              label="Tampilkan backlog H+N"
            />
          )}
        </Box>

        <Box className="flex items-center justify-between mb-3">
          <Box className="flex items-center gap-2">
            <Button variant="outlined" size="small" onClick={resetPendingFilter}>
              Reset Filter
            </Button>
            <Button
              variant="outlined"
              size="small"
              disabled={pendingLoading}
              onClick={() => getPendingCheckout({
                ...pendingFilters,
                include_overdue: canOverrideBackdate ? pendingFilters.include_overdue : false,
              })}
            >
              {pendingLoading ? 'Memuat...' : 'Muat Ulang'}
            </Button>
          </Box>
          <Typography variant="body2" color="text.secondary">
            Total pending: {pendingTotalRows}
          </Typography>
        </Box>

        <TableContainer component={Paper} className="border border-gray-200 rounded-xl overflow-hidden">
          <Table
            sx={{
              '& .MuiTableCell-head': {
                fontWeight: 600,
                backgroundColor: '#F8FAFC',
              },
            }}
          >
            <TableHead>
              <TableRow>
                <TableCell>Nama</TableCell>
                <TableCell>Tanggal</TableCell>
                <TableCell>Jam Masuk</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Gap</TableCell>
                <TableCell align="center">Aksi</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {pendingLoading && (
                <TableRow>
                  <TableCell colSpan={6} align="center">
                    Memuat daftar lupa tap-out...
                  </TableCell>
                </TableRow>
              )}

              {!pendingLoading && pendingCheckoutList.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} align="center">
                    Tidak ada kasus lupa tap-out
                  </TableCell>
                </TableRow>
              )}

              {!pendingLoading && pendingCheckoutList.map((item) => {
                const dayGap = getDayGapFromToday(item.tanggal);
                const isOverdue = dayGap > 1;

                return (
                  <TableRow key={`pending-${item.id}`} hover>
                    <TableCell>
                      <Typography variant="body2" className="font-semibold text-gray-900">
                        {item.user?.nama_lengkap || '-'}
                      </Typography>
                    </TableCell>
                    <TableCell>{formatDate(item.tanggal)}</TableCell>
                    <TableCell>{formatTime(item.jam_masuk)}</TableCell>
                    <TableCell>{getStatusChip(item.status)}</TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        color={isOverdue ? 'warning' : 'default'}
                        label={isOverdue ? `H+${dayGap}` : 'H+1'}
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Button
                        variant="contained"
                        size="small"
                        onClick={() => openResolveModal(item)}
                        disabled={isOverdue && !canOverrideBackdate}
                      >
                        Selesaikan
                      </Button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </TableContainer>

        <Box className="flex justify-end mt-3">
          <Pagination
            page={pendingCurrentPage}
            count={pendingLastPage}
            onChange={(_, value) => handlePendingFilterChange('page', value)}
            color="primary"
            size="small"
            shape="rounded"
          />
        </Box>
      </Paper>
      )}

      {!isPendingView && (
      <Paper className="p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="mb-4">
          <Typography variant="subtitle1" className="font-semibold text-gray-900">
            {isManualDomain
              ? 'Filter Absensi Manual'
              : isAutoAlphaView
                ? 'Filter Data Tidak Absen'
                : 'Filter Koreksi Absensi'}
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            {isManualDomain
              ? 'Domain ini khusus untuk membuat absensi yang sebelumnya belum memiliki data sama sekali.'
              : isAutoAlphaView
                ? 'Daftar ini berisi alpha otomatis yang belum dikoreksi. Gunakan edit untuk mengubahnya menjadi absensi yang valid.'
                : 'Domain ini menampilkan semua absensi yang sudah tercatat, baik realtime, manual, auto alpha, maupun hasil approval izin.'}
          </Typography>
        </div>

        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <TextField
            placeholder={
              isManualDomain
                ? 'Cari nama, email, atau keterangan manual...'
                : isAutoAlphaView
                  ? 'Cari nama siswa atau keterangan auto alpha...'
                  : 'Cari nama, email, atau keterangan absensi...'
            }
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            size="small"
            fullWidth
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-4 h-4 text-gray-400" />
                </InputAdornment>
              ),
            }}
          />

          {!isAutoAlphaView && (
            <FormControl size="small" sx={{ minWidth: 180 }}>
              <Select
                displayEmpty
                value={filters.status || ''}
                onChange={(event) => {
                  setFilters((prev) => ({
                    ...prev,
                    status: event.target.value,
                    page: 1,
                  }));
                }}
              >
                {STATUS_OPTIONS.map((option) => (
                  <MenuItem key={option.value || 'all'} value={option.value}>
                    {option.label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          <TextField
            type="date"
            size="small"
            value={filters.date || ''}
            onChange={(event) => {
              setFilters((prev) => ({
                ...prev,
                date: event.target.value,
                page: 1,
              }));
            }}
            sx={{ minWidth: 190 }}
          />
        </Box>

        <Box className="flex flex-wrap items-center justify-between gap-3">
          <Button
            variant="outlined"
            size="small"
            startIcon={<RotateCcw className="w-4 h-4" />}
            onClick={resetFilter}
          >
            Reset Filter
          </Button>

          <Box className="flex items-center gap-2">
            <Button
              variant="outlined"
              size="small"
              startIcon={<Download className="w-4 h-4" />}
              onClick={() => exportData && exportData()}
            >
              Export
            </Button>
            {isManualDomain && (
              <Button
                variant="outlined"
                size="small"
                color="warning"
                startIcon={<Database className="w-4 h-4" />}
                onClick={handleOpenIncidentModal}
              >
                Insiden Server
              </Button>
            )}
            {isManualDomain && (
              <Button
                variant="outlined"
                size="small"
                startIcon={<CheckCircle className="w-4 h-4" />}
                onClick={() => setShowBulkModal(true)}
              >
                Absensi Massal
              </Button>
            )}
            {isCorrectionDomain && !isPendingView && (
              <Button
                variant="outlined"
                size="small"
                startIcon={<CheckCircle className="w-4 h-4" />}
                onClick={() => setShowBulkModal(true)}
              >
                Koreksi Massal
              </Button>
            )}
            {isManualDomain && (
              <Button
                variant="contained"
                size="small"
                startIcon={<Plus className="w-4 h-4" />}
                onClick={() => setShowCreateModal(true)}
              >
                Tambah Absensi
              </Button>
            )}
          </Box>
        </Box>
      </Paper>
      )}

      {isManualDomain && (
      <Paper className="p-6 rounded-2xl border border-amber-200 bg-amber-50/40 shadow-sm">
        <Box className="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
          <div>
            <Typography variant="subtitle1" className="font-semibold text-gray-900">
              Riwayat Batch Insiden Server
            </Typography>
            <Typography variant="body2" className="text-gray-600">
              Batch terbaru untuk gangguan server. Gunakan ini untuk memantau hasil proses dan membuka ulang detail/export audit.
            </Typography>
            <Box className="flex flex-wrap items-center gap-2 mt-2">
              {activeIncidentBatchCount > 0 ? (
                <>
                  <Chip
                    size="small"
                    color="info"
                    label={`${activeIncidentBatchCount} batch aktif diproses`}
                  />
                  <Typography variant="caption" className="text-blue-700">
                    Auto-refresh tiap 4 detik selama batch masih berjalan.
                  </Typography>
                </>
              ) : (
                <Chip
                  size="small"
                  color="success"
                  variant="outlined"
                  label="Tidak ada batch aktif"
                />
              )}
              {recentIncidentBatchesRefreshedAt && (
                <Typography variant="caption" className="text-gray-600">
                  Terakhir diperbarui {formatServerDateTime(recentIncidentBatchesRefreshedAt, 'id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                  })}
                </Typography>
              )}
            </Box>
          </div>
          <Button
            variant="outlined"
            size="small"
            startIcon={<RotateCcw className="w-4 h-4" />}
            onClick={() => getRecentIncidentBatches()}
            disabled={recentIncidentBatchesLoading}
          >
            {recentIncidentBatchesLoading ? 'Memuat...' : 'Muat Ulang Batch'}
          </Button>
        </Box>

        <TableContainer component={Paper} className="border border-amber-200 rounded-xl overflow-hidden">
          <Table
            size="small"
            sx={{
              '& .MuiTableCell-head': {
                fontWeight: 600,
                backgroundColor: '#FFFBEB',
              },
            }}
          >
            <TableHead>
              <TableRow>
                <TableCell>Batch</TableCell>
                <TableCell>Tanggal</TableCell>
                <TableCell>Scope</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Dibuat</TableCell>
                <TableCell>Dilewati</TableCell>
                <TableCell>Gagal</TableCell>
                <TableCell align="center">Aksi</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {recentIncidentBatchesLoading && (
                <TableRow>
                  <TableCell colSpan={8} align="center">
                    Memuat riwayat batch insiden...
                  </TableCell>
                </TableRow>
              )}

              {!recentIncidentBatchesLoading && recentIncidentBatches.length === 0 && (
                <TableRow>
                  <TableCell colSpan={8} align="center">
                    Belum ada batch insiden server.
                  </TableCell>
                </TableRow>
              )}

              {!recentIncidentBatchesLoading && recentIncidentBatches.map((batch) => {
                const skippedTotal = Number(batch.skipped_existing_count || 0)
                  + Number(batch.skipped_leave_count || 0)
                  + Number(batch.skipped_non_required_count || 0)
                  + Number(batch.skipped_non_working_count || 0);
                const statusConfig = getIncidentBatchStatusConfig(
                  batch.status,
                  Number(batch.progress_percentage || 0)
                );

                return (
                  <TableRow key={`incident-batch-${batch.id}`} hover>
                    <TableCell>
                      <div>
                        <Typography variant="body2" className="font-semibold text-gray-900">
                          Batch #{batch.id}
                        </Typography>
                        <Typography variant="caption" className="text-gray-500">
                          {batch.creator?.nama_lengkap || '-'}
                        </Typography>
                      </div>
                    </TableCell>
                    <TableCell>{formatDate(batch.tanggal)}</TableCell>
                    <TableCell>
                      <Typography variant="body2" className="max-w-[240px]">
                        {batch.preview_summary?.scope_label || batch.scope_type || '-'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Box className="flex flex-col items-start gap-1">
                        <Chip
                          size="small"
                          color={statusConfig.color}
                          label={statusConfig.label}
                        />
                        <Typography
                          variant="caption"
                          className={['queued', 'processing'].includes(String(batch.status || '').toLowerCase())
                            ? 'text-blue-700'
                            : 'text-gray-600'}
                        >
                          {statusConfig.detail}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell>{batch.created_count || 0}</TableCell>
                    <TableCell>{skippedTotal}</TableCell>
                    <TableCell>{batch.failed_count || 0}</TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center gap-1">
                        <Button
                          size="small"
                          variant="outlined"
                          onClick={() => handleOpenIncidentModal(batch)}
                        >
                          Buka
                        </Button>
                        <Button
                          size="small"
                          variant="text"
                          startIcon={<Download className="w-4 h-4" />}
                          disabled={!batch.result_export_available}
                          onClick={() => exportIncidentAttendance(batch.id, 'xlsx', 'all')}
                        >
                          Unduh
                        </Button>
                      </Box>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </TableContainer>
      </Paper>
      )}

      {!isPendingView && (
      <TableContainer component={Paper} className="border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <Table
          sx={{
            '& .MuiTableCell-head': {
              fontWeight: 600,
              backgroundColor: '#F8FAFC',
              color: '#1F2937',
            },
            '& .MuiTableCell-root': {
              borderColor: '#E5E7EB',
            },
          }}
        >
          <TableHead>
            <TableRow>
              <TableCell width={60}>No</TableCell>
              <TableCell>Nama</TableCell>
              <TableCell>Tanggal</TableCell>
              <TableCell>Jam Masuk</TableCell>
              <TableCell>Jam Pulang</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Keterangan</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && attendanceList.length === 0 && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  {isManualDomain
                    ? 'Tidak ada data absensi manual'
                    : isAutoAlphaView
                      ? 'Tidak ada data tidak absen'
                      : 'Tidak ada data koreksi absensi'}
                </TableCell>
              </TableRow>
            )}

            {!loading && attendanceList.map((item, index) => (
              <TableRow key={item.id} hover>
                {(() => {
                  const leaveApprovalInfo = getLeaveApprovalAuditInfo(item);

                  return (
                    <>
                <TableCell>{(safePage - 1) * safePerPage + index + 1}</TableCell>
                <TableCell>
                  <div>
                    <Typography variant="body2" className="font-semibold text-gray-900">
                      {item.user?.nama_lengkap || '-'}
                    </Typography>
                    <div className="flex items-center gap-2">
                      <Typography variant="caption" className="text-gray-500">
                        {item.user?.email || '-'}
                      </Typography>
                      {(() => {
                        const sourceBadge = getAttendanceSourceBadge(item);

                        return (
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${sourceBadge.className}`}>
                            {sourceBadge.label}
                          </span>
                        );
                      })()}
                    </div>
                  </div>
                </TableCell>
                <TableCell>{formatDate(item.tanggal)}</TableCell>
                <TableCell>{formatTime(item.jam_masuk)}</TableCell>
                <TableCell>{formatTime(item.jam_pulang)}</TableCell>
                <TableCell>{getStatusChip(item.status)}</TableCell>
                <TableCell>
                  <div className="space-y-1">
                    <Typography variant="body2" className="truncate max-w-[220px]" title={item.keterangan || '-'}>
                      {item.keterangan || '-'}
                    </Typography>
                    {leaveApprovalInfo && (
                      <Typography variant="caption" className="text-emerald-700">
                        Approval izin#{leaveApprovalInfo.izinId || '-'}{leaveApprovalInfo.previousStatus ? `, sebelumnya ${leaveApprovalInfo.previousStatus}` : ''}
                      </Typography>
                    )}
                  </div>
                </TableCell>
                <TableCell align="center">
                  <Box className="flex items-center justify-center gap-1">
                    <IconButton
                      size="small"
                      color="primary"
                      onClick={() => setSelectedAttendance(item)}
                      title="Detail"
                    >
                      <Eye className="w-4 h-4" />
                    </IconButton>
                    <IconButton
                      size="small"
                      color="success"
                      onClick={() => setSelectedAttendance(item)}
                      title={isManualDomain ? 'Edit' : 'Koreksi'}
                    >
                      <Edit className="w-4 h-4" />
                    </IconButton>
                    {isManualDomain && item.is_manual && (
                      <IconButton
                        size="small"
                        color="error"
                        onClick={() => handleDeleteAttendance(item)}
                        title="Hapus"
                      >
                        <Trash2 className="w-4 h-4" />
                      </IconButton>
                    )}
                  </Box>
                </TableCell>
                    </>
                  );
                })()}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
      )}

      {!isPendingView && (
      <Paper className="mt-4 px-4 py-3 border border-gray-200 rounded-xl shadow-sm">
        <Box className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <Typography variant="body2" color="text.secondary">
            Menampilkan {from} - {to} dari {totalRows} data
          </Typography>
          <Box className="flex items-center gap-4">
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="text.secondary">
                Per halaman:
              </Typography>
              <Select
                size="small"
                value={safePerPage}
                onChange={(event) => {
                  setFilters((prev) => ({
                    ...prev,
                    per_page: Number(event.target.value),
                    page: 1,
                  }));
                }}
                sx={{ minWidth: 84 }}
              >
                {[10, 15, 25, 50].map((size) => (
                  <MenuItem key={size} value={size}>
                    {size}
                  </MenuItem>
                ))}
              </Select>
            </Box>
            <Pagination
              page={safePage}
              count={lastPage}
              onChange={(_, value) => {
                setFilters((prev) => ({
                  ...prev,
                  page: value,
                }));
              }}
              color="primary"
              shape="rounded"
              size="small"
            />
          </Box>
        </Box>
      </Paper>
      )}

      {showCreateModal && (
        <ManualAttendanceModal
          isOpen={showCreateModal}
          onClose={() => setShowCreateModal(false)}
          onSubmit={handleCreateAttendance}
          users={users}
          title="Tambah Absensi Manual"
        />
      )}

      {showBulkModal && (
        <ManualAttendanceBulkModal
          open={showBulkModal}
          onClose={() => setShowBulkModal(false)}
          onPreview={handleBulkPreview}
          onSubmit={handleBulkAttendance}
          operation={isManualDomain ? 'create_missing' : 'correct_existing'}
          users={users}
          serverDate={toServerDateInput(getServerNowDate())}
        />
      )}

      {showIncidentModal && (
        <ManualAttendanceIncidentModal
          open={showIncidentModal}
          onClose={handleCloseIncidentModal}
          options={incidentOptions}
          initialBatch={selectedIncidentBatch}
          loadingOptions={incidentOptionsLoading}
          onLoadOptions={getIncidentOptions}
          onPreview={previewIncidentAttendance}
          onStart={createIncidentAttendance}
          onRefreshBatch={handleRefreshIncidentBatch}
          onExport={exportIncidentAttendance}
          serverDate={toServerDateInput(getServerNowDate())}
        />
      )}

      {selectedAttendance && (
        <ManualAttendanceModal
          isOpen={Boolean(selectedAttendance)}
          onClose={() => setSelectedAttendance(null)}
          onSubmit={(data) => handleUpdateAttendance(selectedAttendance.id, data)}
          users={users}
          initialData={selectedAttendance}
          title={isManualDomain ? 'Edit Absensi Manual' : 'Koreksi Absensi'}
        />
      )}

      <Dialog open={Boolean(resolveTarget)} onClose={closeResolveModal} fullWidth maxWidth="sm">
        <DialogTitle>Selesaikan Lupa Tap-Out</DialogTitle>
        <DialogContent>
          {resolveTarget && (
            <Box className="space-y-4 mt-1">
              <Alert severity="info">
                <strong>{resolveTarget?.user?.nama_lengkap || '-'}</strong> - {formatDate(resolveTarget?.tanggal)} (Jam Masuk: {formatTime(resolveTarget?.jam_masuk)})
              </Alert>

              {resolveNeedsOverride && (
                <Alert severity={canOverrideBackdate ? 'warning' : 'error'}>
                  {canOverrideBackdate
                    ? `Kasus ini termasuk H+${resolveDayGap}. Override reason wajib diisi.`
                    : `Kasus ini termasuk H+${resolveDayGap}. Akun Anda tidak memiliki izin override.`}
                </Alert>
              )}

              <TextField
                label="Jam Pulang"
                type="time"
                fullWidth
                value={resolveForm.jam_pulang}
                disabled={resolvingPending}
                onChange={(event) => {
                  setResolveForm((prev) => ({ ...prev, jam_pulang: event.target.value }));
                  setResolveErrors((prev) => ({ ...prev, jam_pulang: '' }));
                }}
                InputLabelProps={{ shrink: true }}
                error={Boolean(resolveErrors.jam_pulang)}
                helperText={resolveErrors.jam_pulang || 'Maksimal 23:59 pada tanggal absensi'}
              />

              <FormControl fullWidth size="small">
                <Select
                  value={resolveForm.status || 'hadir'}
                  disabled={resolvingPending}
                  onChange={(event) => setResolveForm((prev) => ({ ...prev, status: event.target.value }))}
                >
                  <MenuItem value="hadir">Hadir</MenuItem>
                  <MenuItem value="terlambat">Terlambat</MenuItem>
                  <MenuItem value="izin">Izin</MenuItem>
                  <MenuItem value="sakit">Sakit</MenuItem>
                  <MenuItem value="alpha">Alpha</MenuItem>
                </Select>
              </FormControl>

              <TextField
                label="Keterangan (Opsional)"
                fullWidth
                multiline
                minRows={2}
                value={resolveForm.keterangan}
                disabled={resolvingPending}
                onChange={(event) => setResolveForm((prev) => ({ ...prev, keterangan: event.target.value }))}
              />

              <TextField
                label="Alasan Follow-up"
                fullWidth
                multiline
                minRows={3}
                value={resolveForm.reason}
                disabled={resolvingPending}
                onChange={(event) => {
                  setResolveForm((prev) => ({ ...prev, reason: event.target.value }));
                  setResolveErrors((prev) => ({ ...prev, reason: '' }));
                }}
                error={Boolean(resolveErrors.reason)}
                helperText={resolveErrors.reason || 'Contoh: Konfirmasi ke wali kelas, siswa lupa tap-out saat pulang'}
              />

              {resolveNeedsOverride && (
                <TextField
                  label="Alasan Override H+N"
                  fullWidth
                  multiline
                  minRows={3}
                  value={resolveForm.override_reason}
                  onChange={(event) => {
                    setResolveForm((prev) => ({ ...prev, override_reason: event.target.value }));
                    setResolveErrors((prev) => ({ ...prev, override_reason: '' }));
                  }}
                  error={Boolean(resolveErrors.override_reason)}
                  helperText={resolveErrors.override_reason || 'Wajib untuk kasus di atas H+1'}
                  disabled={!canOverrideBackdate || resolvingPending}
                />
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={closeResolveModal} disabled={resolvingPending}>
            Batal
          </Button>
          <Button
            onClick={submitResolveCheckout}
            variant="contained"
            disabled={resolvingPending || (resolveNeedsOverride && !canOverrideBackdate)}
          >
            {resolvingPending ? 'Menyimpan...' : 'Simpan Penyelesaian'}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmationModal
        open={Boolean(attendanceToDelete)}
        onClose={() => !loading && setAttendanceToDelete(null)}
        title="Hapus Absensi Manual"
        message={(
          <>
            Hapus data absensi manual untuk <strong>{attendanceToDelete?.user?.nama_lengkap || '-'}</strong> pada
            tanggal <strong>{formatDate(attendanceToDelete?.tanggal)}</strong>?
          </>
        )}
        onConfirm={loading ? () => {} : confirmDeleteAttendance}
        confirmText={loading ? 'Menghapus...' : 'Hapus'}
        type="delete"
      />
    </div>
  );
};

export default ManualAttendance;

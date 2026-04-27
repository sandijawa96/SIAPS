import React, { useState, useCallback, useEffect, useMemo, useRef } from 'react';
import {
  Container,
  Box,
  Tab,
  Tabs,
  Alert,
  CircularProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Button,
  Typography,
  DialogContentText,
  Divider,
  IconButton,
  useMediaQuery,
  Card,
  CardContent,
  Chip
} from '@mui/material';
import { useTheme } from '@mui/material/styles';
import { Map, BarChart3, AlertTriangle, RefreshCw, Square, ShieldAlert, Users } from 'lucide-react';
import { useSnackbar } from 'notistack';

// Components
import TrackingHeader from '../components/live-tracking/TrackingHeader';
import TrackingFilters from '../components/live-tracking/TrackingFilters';
import StudentList from '../components/live-tracking/StudentList';
import TrackingMap from '../components/live-tracking/TrackingMap';
import TrackingStats from '../components/live-tracking/TrackingStats';
import TrackingSettings from '../components/live-tracking/TrackingSettings';
import ExportDialog from '../components/live-tracking/ExportDialog';
import TrackingPriorityQueue from '../components/live-tracking/TrackingPriorityQueue';
import TrackingClassSummary from '../components/live-tracking/TrackingClassSummary';
import TrackingLevelSummary from '../components/live-tracking/TrackingLevelSummary';
import TrackingHomeroomSummary from '../components/live-tracking/TrackingHomeroomSummary';
import TrackingHistoryMapDialog from '../components/live-tracking/TrackingHistoryMapDialog';

// Hooks
import useTracking from '../hooks/useTracking';
import { useAuth } from '../hooks/useAuth';
import { useServerClock } from '../hooks/useServerClock';
import { formatServerDateTime, formatServerTime, getServerDateString } from '../services/serverClock';
import { getTrackingStatusReasonLabel } from '../utils/trackingStatus';

const LiveTrackingNew = () => {
  // State
  const [currentTab, setCurrentTab] = useState(0);

  // Hooks
  const { enqueueSnackbar } = useSnackbar();
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const {
    students,
    allStudents,
    mapStudents,
    mapOverflowCount,
    activeTrackingSessions,
    activeSessionsLoading,
    loading,
    error,
    isSchoolHours,
    schoolHoursWindow,
    attendanceLocations,
    selectedStudent,
    filters,
    stats,
    classSummary,
    levelSummary,
    homeroomSummary,
    historyPolicy,
    dataSources,
    historyPolicyLoading,
    historyPolicySaving,
    settings,
    pagination,
    pageSizeOptions,
    priorityQueues,
    priorityQueuesLoading,
    updateSettings,
    resetSettings,
    exportData,
    exportLoading,
    exportError,
    saveHistoryPolicy,
    updateFilters,
    selectStudent,
    refreshData,
    fetchStudentHistory,
    fetchTrackingHistoryMap,
    searchTrackingHistoryStudents,
    exportTrackingHistoryMapPdf,
    startTrackingSessionForStudent,
    stopTrackingSessionForStudent,
    fetchActiveTrackingSessions,
    setCurrentPage,
    setPageSize
  } = useTracking();

  const { hasPermission } = useAuth();
  const { serverNowMs } = useServerClock();
  const canManageTrackingSession = hasPermission('manage_live_tracking');
  const canManageHistoryPolicy = hasPermission('manage_attendance_settings');

  const availableClasses = useMemo(
    () => classSummary
      .map((row) => row.class_name)
      .filter(Boolean)
      .sort((left, right) => String(left).localeCompare(String(right), 'id')),
    [classSummary]
  );

  const availableLevels = useMemo(
    () => levelSummary
      .map((row) => row.level_name)
      .filter(Boolean)
      .sort((left, right) => String(left).localeCompare(String(right), 'id')),
    [levelSummary]
  );

  const availableHomeroomTeachers = useMemo(
    () => homeroomSummary
      .filter((row) => row.wali_kelas_id)
      .map((row) => ({
        id: row.wali_kelas_id,
        name: row.wali_kelas_name,
      }))
      .sort((left, right) => String(left.name).localeCompare(String(right.name), 'id')),
    [homeroomSummary]
  );

    // Handlers
  const handleTabChange = (event, newValue) => {
    setCurrentTab(newValue);
  };

  const handleRefresh = async () => {
    const refreshed = await refreshData();
    enqueueSnackbar(
      refreshed ? 'Data berhasil diperbarui' : 'Gagal memperbarui data',
      { variant: refreshed ? 'success' : 'error' }
    );
  };

  // Dialog states
  const [showSettings, setShowSettings] = useState(false);
  const [showExport, setShowExport] = useState(false);
  const [detailStudent, setDetailStudent] = useState(null);
  const [showTrackingSessionDialog, setShowTrackingSessionDialog] = useState(false);
  const [sessionTargetStudentId, setSessionTargetStudentId] = useState(null);
  const [sessionMinutes, setSessionMinutes] = useState('15');
  const [sessionReason, setSessionReason] = useState('Pemantauan tambahan dari dashboard');
  const [showActiveTrackingSessionsDialog, setShowActiveTrackingSessionsDialog] = useState(false);
  const [detailHistoryLoading, setDetailHistoryLoading] = useState(false);
  const [detailHistory, setDetailHistory] = useState(null);
  const [detailHistoryError, setDetailHistoryError] = useState(null);
  const [showHistoryMapDialog, setShowHistoryMapDialog] = useState(false);
  const [historyMapLoading, setHistoryMapLoading] = useState(false);
  const [historyMapError, setHistoryMapError] = useState(null);
  const [historyMapData, setHistoryMapData] = useState(null);
  const [historyMapStudentIds, setHistoryMapStudentIds] = useState([]);
  const [historyMapFocusedUserId, setHistoryMapFocusedUserId] = useState(null);
  const [historyMapSearchText, setHistoryMapSearchText] = useState('');
  const [historyMapSearchLoading, setHistoryMapSearchLoading] = useState(false);
  const [historyMapRemoteOptions, setHistoryMapRemoteOptions] = useState([]);
  const historyMapSearchDebounceRef = useRef(null);
  const historyMapSearchRequestIdRef = useRef(0);
  const [historyMapFilters, setHistoryMapFilters] = useState(() => ({
    date: getServerDateString(serverNowMs),
    startTime: '',
    endTime: '',
  }));

  const clearHistoryMapSearchDebounce = useCallback(() => {
    if (historyMapSearchDebounceRef.current) {
      window.clearTimeout(historyMapSearchDebounceRef.current);
      historyMapSearchDebounceRef.current = null;
    }
  }, []);

  const historyMapCompareOptions = useMemo(() => {
    const optionMap = new globalThis.Map();
    const appendOption = (option) => {
      const optionId = Number(option?.id);
      if (!Number.isInteger(optionId) || optionId <= 0) {
        return;
      }

      optionMap.set(optionId, {
        id: optionId,
        name: option?.name || option?.nama_lengkap || '-',
        label: option?.label || `${option?.name || option?.nama_lengkap || '-'} | ${option?.class || option?.kelas || 'N/A'} | Tingkat ${option?.level || option?.tingkat || 'N/A'}`,
      });
    };

    allStudents.forEach((student) => appendOption({
      id: student.id,
      name: student.name,
      class: student.class,
      level: student.level,
    }));

    historyMapRemoteOptions.forEach((option) => appendOption(option));

    (Array.isArray(historyMapData?.sessions) ? historyMapData.sessions : []).forEach((session) => appendOption({
      id: session?.user?.id,
      name: session?.user?.nama_lengkap,
      class: session?.user?.kelas,
      level: session?.user?.tingkat,
    }));

    return Array.from(optionMap.values())
      .sort((left, right) => String(left.label).localeCompare(String(right.label), 'id'));
  }, [allStudents, historyMapData?.sessions, historyMapRemoteOptions]);

  const handleExport = () => {
    setShowExport(true);
  };

  const handleSettings = () => {
    setShowSettings(true);
  };

  const handleExportSubmit = (exportSettings) => {
    exportData(exportSettings);
    setShowExport(false);
  };

  const handleSettingsSave = async (newSettings, nextHistoryPolicy) => {
    updateSettings(newSettings);

    if (canManageHistoryPolicy && nextHistoryPolicy) {
      const saved = await saveHistoryPolicy(nextHistoryPolicy);
      if (!saved) {
        return false;
      }
    }

    enqueueSnackbar('Pengaturan berhasil disimpan', { variant: 'success' });
    return true;
  };

  const handleClearFilters = useCallback(() => {
    updateFilters({
      status: 'all',
      area: 'all',
      search: '',
      class: '',
      tingkat: '',
      wali_kelas_id: ''
    });
  }, [updateFilters]);

  const handleFocusHomeroomTeacher = useCallback((teacherId) => {
    updateFilters({ wali_kelas_id: teacherId ? String(teacherId) : '' });
    setCurrentTab(2);
  }, [updateFilters]);

  const handleClearHomeroomTeacherFilter = useCallback(() => {
    updateFilters({ wali_kelas_id: '' });
  }, [updateFilters]);

  const handleFocusLevel = useCallback((levelName) => {
    updateFilters({ tingkat: levelName || '' });
    setCurrentTab(2);
  }, [updateFilters]);

  const handleClearLevelFilter = useCallback(() => {
    updateFilters({ tingkat: '' });
  }, [updateFilters]);

  const handleFocusClass = useCallback((className) => {
    updateFilters({ class: className || '' });
    setCurrentTab(2);
  }, [updateFilters]);

  const handleClearClassFilter = useCallback(() => {
    updateFilters({ class: '' });
  }, [updateFilters]);

  const handleViewDetails = async (student) => {
    setDetailStudent(student || null);
    setDetailHistory(null);
    setDetailHistoryError(null);

    if (!student?.id) {
      return;
    }

    setDetailHistoryLoading(true);
    const historyData = await fetchStudentHistory(student.id);
    if (historyData) {
      setDetailHistory(historyData);
    } else {
      setDetailHistoryError('Gagal memuat riwayat tracking siswa.');
    }
    setDetailHistoryLoading(false);
  };

  const loadHistoryMap = useCallback(async (
    studentIds = historyMapStudentIds,
    nextFilters = historyMapFilters,
    preferredFocusUserId = historyMapFocusedUserId
  ) => {
    const normalizedIds = Array.from(new Set(
      (Array.isArray(studentIds) ? studentIds : [studentIds])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0)
    ));

    if (normalizedIds.length === 0) {
      enqueueSnackbar('Pilih minimal satu siswa untuk melihat histori peta', { variant: 'warning' });
      return false;
    }

    setHistoryMapLoading(true);
    setHistoryMapError(null);

    const historyMapResponse = await fetchTrackingHistoryMap(normalizedIds, {
      date: nextFilters?.date,
      startTime: nextFilters?.startTime,
      endTime: nextFilters?.endTime,
    });

    if (!historyMapResponse) {
      setHistoryMapError('Gagal memuat histori peta siswa.');
      setHistoryMapLoading(false);
      return false;
    }

    const sessions = Array.isArray(historyMapResponse?.sessions) ? historyMapResponse.sessions : [];
    const fallbackFocusUserId = sessions.find((session) => session?.user?.id === preferredFocusUserId)?.user?.id
      || sessions[0]?.user?.id
      || null;

    setHistoryMapData(historyMapResponse);
    setHistoryMapFocusedUserId(fallbackFocusUserId);
    setHistoryMapLoading(false);
    return true;
  }, [enqueueSnackbar, fetchTrackingHistoryMap, historyMapFilters, historyMapFocusedUserId, historyMapStudentIds]);

  const handleHistoryMapSelectionChange = useCallback((nextIds) => {
    const normalizedIds = Array.from(new Set(
      (Array.isArray(nextIds) ? nextIds : [])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0)
    ));

    if (normalizedIds.length > 5) {
      enqueueSnackbar('Compare histori peta maksimal 5 siswa', { variant: 'warning' });
      return;
    }

    setHistoryMapStudentIds(normalizedIds);
    setHistoryMapFocusedUserId((previousFocus) => (
      normalizedIds.includes(previousFocus) ? previousFocus : (normalizedIds[0] || null)
    ));
  }, [enqueueSnackbar]);

  const handleOpenHistoryMap = useCallback(async (student) => {
    if (!student?.id) {
      return;
    }

    const defaultFilters = {
      date: getServerDateString(serverNowMs),
      startTime: '',
      endTime: '',
    };

    setHistoryMapStudentIds([student.id]);
    setHistoryMapFocusedUserId(student.id);
    setHistoryMapSearchText('');
    setHistoryMapRemoteOptions([]);
    setHistoryMapSearchLoading(false);
    setHistoryMapFilters(defaultFilters);
    setHistoryMapData(null);
    setHistoryMapError(null);
    setShowHistoryMapDialog(true);
    await loadHistoryMap([student.id], defaultFilters, student.id);
  }, [loadHistoryMap, serverNowMs]);

  const handleCloseHistoryMapDialog = useCallback(() => {
    clearHistoryMapSearchDebounce();
    historyMapSearchRequestIdRef.current += 1;
    setShowHistoryMapDialog(false);
    setHistoryMapLoading(false);
    setHistoryMapError(null);
    setHistoryMapSearchText('');
    setHistoryMapRemoteOptions([]);
    setHistoryMapSearchLoading(false);
  }, [clearHistoryMapSearchDebounce]);

  const handleHistoryMapSearchTextChange = useCallback((nextValue, reason = 'input') => {
    const normalizedValue = String(nextValue || '');
    setHistoryMapSearchText(normalizedValue);
    clearHistoryMapSearchDebounce();
    historyMapSearchRequestIdRef.current += 1;

    const trimmedValue = normalizedValue.trim();
    if (reason === 'reset') {
      setHistoryMapSearchLoading(false);
      setHistoryMapRemoteOptions([]);
      return;
    }

    if (trimmedValue.length < 2) {
      setHistoryMapSearchLoading(false);
      setHistoryMapRemoteOptions([]);
      return;
    }

    setHistoryMapSearchLoading(true);
    const requestId = historyMapSearchRequestIdRef.current;
    historyMapSearchDebounceRef.current = window.setTimeout(async () => {
      const results = await searchTrackingHistoryStudents(trimmedValue, 15);
      if (historyMapSearchRequestIdRef.current !== requestId) {
        return;
      }

      setHistoryMapRemoteOptions(Array.isArray(results) ? results : []);
      setHistoryMapSearchLoading(false);
      historyMapSearchDebounceRef.current = null;
    }, 280);
  }, [clearHistoryMapSearchDebounce, searchTrackingHistoryStudents]);

  useEffect(() => () => {
    clearHistoryMapSearchDebounce();
    historyMapSearchRequestIdRef.current += 1;
  }, [clearHistoryMapSearchDebounce]);

  const handleStartTrackingSession = async (userId) => {
    if (!historyPolicy.enabled) {
      enqueueSnackbar('Live tracking sedang dinonaktifkan oleh admin', { variant: 'warning' });
      return;
    }

    setSessionTargetStudentId(userId);
    setSessionMinutes('15');
    setSessionReason('Pemantauan tambahan dari dashboard');
    setShowTrackingSessionDialog(true);
  };

  const handleStopTrackingSession = async (userId) => {
    await stopTrackingSessionForStudent(userId);
  };

  const handleSubmitTrackingSession = async () => {
    if (!sessionTargetStudentId) {
      handleCloseTrackingSessionDialog();
      return;
    }

    if (!historyPolicy.enabled) {
      enqueueSnackbar('Live tracking sedang dinonaktifkan oleh admin', { variant: 'warning' });
      handleCloseTrackingSessionDialog();
      return;
    }

    const minutes = Number(sessionMinutes);
    if (!Number.isInteger(minutes) || minutes <= 0) {
      enqueueSnackbar('Durasi pemantauan harus angka menit yang valid', { variant: 'warning' });
      return;
    }

    if (minutes > 240) {
      enqueueSnackbar('Durasi maksimal sesi adalah 240 menit', { variant: 'warning' });
      return;
    }

    const started = await startTrackingSessionForStudent(
      sessionTargetStudentId,
      minutes,
      sessionReason
    );
    if (started) {
      setShowTrackingSessionDialog(false);
      setSessionTargetStudentId(null);
    }
  };

  const handleCloseTrackingSessionDialog = () => {
    setShowTrackingSessionDialog(false);
    setSessionTargetStudentId(null);
  };

  const handleShowActiveTrackingSessions = async () => {
    setShowActiveTrackingSessionsDialog(true);
    if (fetchActiveTrackingSessions) {
      await fetchActiveTrackingSessions();
    }
  };

  const handleCloseActiveTrackingSessionsDialog = () => {
    setShowActiveTrackingSessionsDialog(false);
  };

  const getRemainingText = (expiresAt) => {
    const expiresAtMs = Date.parse(expiresAt || '');
    if (Number.isNaN(expiresAtMs)) {
      return null;
    }

    const remainingMs = expiresAtMs - serverNowMs;
    if (remainingMs <= 0) {
      return 'Habis';
    }

    const totalMinutes = Math.max(0, Math.floor(remainingMs / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours > 0) {
      return `${hours} jam ${String(minutes).padStart(2, '0')} menit`;
    }

    return `${minutes} menit`;
  };

  const getTimeOnly = (value) => {
    const dateMs = Date.parse(value || '');
    if (Number.isNaN(dateMs)) {
      return '-';
    }

    return formatServerTime(dateMs, 'id-ID', {
      hour: '2-digit',
      minute: '2-digit'
    }) || '-';
  };

  const formatTrackingStatus = (status) => {
    if (status === 'active') return 'Dalam area';
    if (status === 'outside_area') return 'Luar area';
    if (status === 'tracking_disabled') return 'Tracking nonaktif';
    if (status === 'outside_schedule') return 'Di luar jadwal';
    if (status === 'stale') return 'Stale';
    if (status === 'gps_disabled') return 'GPS mati';
    return 'Belum ada data';
  };

  const getTrackingStatusColor = (status) => {
    if (status === 'gps_disabled') return 'error';
    if (status === 'active') return 'success';
    if (status === 'tracking_disabled') return 'default';
    if (status === 'outside_schedule') return 'info';
    if (status === 'no_data') return 'default';
    return 'warning';
  };

  const getLocationContextLabel = (student) => {
    if (!student?.hasTrackingData) {
      return 'Belum ada data tracking';
    }

    if (student.status === 'outside_schedule') {
      return student?.location?.address
        ? `Lokasi terakhir sebelum tracking dijeda: ${student.location.address}`
        : 'Tracking dijeda di luar jadwal';
    }

    if (student.status === 'tracking_disabled') {
      return student?.location?.address
        ? `Lokasi terakhir sebelum tracking dinonaktifkan: ${student.location.address}`
        : 'Tracking dinonaktifkan oleh admin';
    }

    if (student.status === 'gps_disabled') {
      return student?.location?.address
        ? `Lokasi terakhir saat GPS mati: ${student.location.address}`
        : 'GPS perangkat tidak aktif';
    }

    return student?.location?.address || 'Lokasi belum tersedia';
  };

  const formatGpsQuality = (status) => {
    if (status === 'good') return 'Baik';
    if (status === 'moderate') return 'Sedang';
    if (status === 'poor') return 'Lemah';
    return 'Tidak diketahui';
  };

  const detailHistoryRows = useMemo(() => {
    const rows = Array.isArray(detailHistory?.tracking) ? detailHistory.tracking : [];
    return [...rows]
      .sort((left, right) => Date.parse(right?.tracked_at || 0) - Date.parse(left?.tracked_at || 0))
      .slice(0, 8);
  }, [detailHistory]);

  const handleCloseDetailDialog = () => {
    setDetailStudent(null);
    setDetailHistory(null);
    setDetailHistoryError(null);
    setDetailHistoryLoading(false);
  };

  const activeSessions = useMemo(
    () => {
      const nowMs = Number(serverNowMs);
      return (Array.isArray(activeTrackingSessions) ? activeTrackingSessions : [])
        .map((session) => {
          const expiresAt = session.expires_at || session.expiresAt;
          const expiresAtMs = Date.parse(expiresAt || '');
          return {
            ...session,
            expiresAtMs,
            remainingMinutes: getRemainingText(expiresAt)
          };
        })
        .filter((session) => Number.isFinite(session.expiresAtMs) && session.expiresAtMs > nowMs);
    },
    [activeTrackingSessions, serverNowMs]
  );

  useEffect(() => {
    if (canManageTrackingSession && fetchActiveTrackingSessions) {
      fetchActiveTrackingSessions();
    }
  }, [canManageTrackingSession, fetchActiveTrackingSessions]);

  useEffect(() => {
    if (!showActiveTrackingSessionsDialog || !fetchActiveTrackingSessions) {
      return;
    }

    const refresh = () => {
      fetchActiveTrackingSessions();
    };

    refresh();
    const timer = setInterval(refresh, 15000);

    return () => {
      clearInterval(timer);
    };
  }, [fetchActiveTrackingSessions, showActiveTrackingSessionsDialog]);

  // Render loading state
  if (loading && students.length === 0) {
    return (
      <Container maxWidth="xl" className="py-8">
        <Box className="flex flex-col items-center justify-center min-h-[60vh]">
          <CircularProgress size={48} className="mb-4" />
          <span className="text-gray-600">Memuat data tracking...</span>
        </Box>
      </Container>
    );
  }

  return (
    <Container maxWidth="xl" className="space-y-5 py-6">
      {/* Header & Stats */}
      <TrackingHeader
        isSchoolHours={isSchoolHours}
        schoolHoursWindow={schoolHoursWindow}
        stats={stats}
        loading={loading}
        liveTrackingEnabled={historyPolicy.enabled}
        onRefresh={handleRefresh}
        onExport={handleExport}
        onSettings={handleSettings}
        onViewActiveSessions={handleShowActiveTrackingSessions}
        canManageTrackingSession={canManageTrackingSession}
        activeSessionsCount={activeSessions.length}
      />

      {/* Error Alert */}
      {error && (
        <Alert 
          severity="error" 
          icon={<AlertTriangle className="w-5 h-5" />}
          className="mb-4"
        >
          {error}
        </Alert>
      )}

      {!historyPolicy.enabled ? (
        <Alert severity="info" className="mb-4">
          Live tracking global sedang dinonaktifkan oleh admin. Dashboard tetap menampilkan lokasi terakhir yang sudah tersimpan, tetapi pengiriman realtime baru dihentikan sampai policy diaktifkan kembali.
        </Alert>
      ) : null}

      {/* Filters */}
      <TrackingFilters
        filters={filters}
        onFiltersChange={updateFilters}
        availableClasses={availableClasses}
        availableLevels={availableLevels}
        availableHomeroomTeachers={availableHomeroomTeachers}
        onClearFilters={handleClearFilters}
        totalResults={pagination?.total || students.length}
      />

      {/* Tabs */}
      <Box className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <Tabs
          value={currentTab}
          onChange={handleTabChange}
          className="border-b border-slate-200 px-3 pt-3"
          sx={{
            '& .MuiTab-root': {
              minHeight: 52,
              textTransform: 'none',
              fontWeight: 600,
              color: '#475569',
              borderRadius: '14px 14px 0 0',
            },
            '& .Mui-selected': {
              color: '#0f172a',
            },
            '& .MuiTabs-indicator': {
              height: 3,
              borderRadius: 999,
              backgroundColor: '#2563eb',
            },
          }}
        >
          <Tab icon={<BarChart3 className="w-4 h-4" />} label="Ringkasan" iconPosition="start" />
          <Tab icon={<ShieldAlert className="w-4 h-4" />} label="Kasus Prioritas" iconPosition="start" />
          <Tab icon={<Users className="w-4 h-4" />} label="Daftar Operasional" iconPosition="start" />
          <Tab icon={<Map className="w-4 h-4" />} label="Peta" iconPosition="start" />
        </Tabs>

        {/* Tab Panels */}
        <Box className="p-4">
          {currentTab === 0 ? (
            <Box className="space-y-6">
              <Card className="rounded-3xl border border-slate-200 shadow-sm">
                <CardContent className="space-y-3 p-5">
                  <Typography variant="subtitle1" className="font-semibold text-slate-900">
                    Urutan baca yang paling efisien
                  </Typography>
                  <Box className="flex flex-wrap gap-2">
                    <Button size="small" variant="outlined" onClick={() => setCurrentTab(0)}>1. Ringkasan</Button>
                    <Button size="small" variant="outlined" onClick={() => setCurrentTab(1)}>2. Prioritas</Button>
                    <Button size="small" variant="outlined" onClick={() => setCurrentTab(2)}>3. Daftar</Button>
                    <Button size="small" variant="outlined" onClick={() => setCurrentTab(3)}>4. Peta</Button>
                  </Box>
                  <Typography variant="body2" className="text-slate-600">
                    Mulai dari tingkat atau wali kelas, lanjut ke daftar siswa, lalu gunakan peta hanya untuk investigasi akhir.
                  </Typography>
                </CardContent>
              </Card>

              <TrackingStats
                stats={stats}
                isSchoolHours={isSchoolHours}
                schoolHoursWindow={schoolHoursWindow}
                lastUpdate={students[0]?.lastUpdate}
                liveTrackingEnabled={historyPolicy.enabled}
              />

              <Card className="rounded-3xl border border-slate-200 shadow-sm">
                <CardContent className="space-y-4 p-5">
                  <Box className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <Box>
                      <Typography variant="subtitle1" className="font-semibold text-slate-900">
                        Policy Histori Tracking
                      </Typography>
                      <Typography variant="body2" className="text-slate-600">
                        Jejak histori sekarang lebih hemat: simpan saat bergerak cukup jauh, saat status penting berubah, dan checkpoint berkala saat diam.
                      </Typography>
                    </Box>
                    <Typography variant="caption" className="text-slate-500">
                      {historyPolicyLoading ? 'Memuat policy...' : `Sumber: ${historyPolicy.source || 'config'}`}
                    </Typography>
                  </Box>
                  <Box className="flex flex-wrap gap-2">
                    <Typography
                      variant="caption"
                      className={`rounded-full border px-3 py-1 ${
                        historyPolicy.enabled
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          : 'border-slate-200 bg-slate-50 text-slate-600'
                      }`}
                    >
                      {historyPolicy.enabled ? 'Live tracking aktif' : 'Live tracking nonaktif'}
                    </Typography>
                    <Typography
                      variant="caption"
                      className={`rounded-full border px-3 py-1 ${
                        historyPolicy.readCurrentStoreEnabled
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          : 'border-slate-200 bg-slate-50 text-slate-600'
                      }`}
                    >
                      {historyPolicy.readCurrentStoreEnabled ? 'Baca current-store aktif' : 'Baca current-store nonaktif'}
                    </Typography>
                    <Typography variant="caption" className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-slate-600">
                      Daftar {dataSources.list === 'redis_current_store' ? 'Redis current-store' : 'request pipeline'}
                    </Typography>
                    <Typography variant="caption" className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-slate-600">
                      Ringkasan {dataSources.summary === 'redis_current_store' ? 'Redis current-store' : 'request pipeline'}
                    </Typography>
                    <Typography variant="caption" className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-slate-600">
                      Prioritas {dataSources.priorityQueues === 'redis_current_store' ? 'Redis current-store' : 'request pipeline'}
                    </Typography>
                  </Box>
                  <Box className="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Status operasional</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {historyPolicy.enabled ? 'Aktif' : 'Dinonaktifkan admin'}
                      </Typography>
                    </Box>
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Sampling gerak</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {Number(historyPolicy.minDistanceMeters || 20)} meter
                      </Typography>
                    </Box>
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Retensi histori</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {Number(historyPolicy.retentionDays || 30)} hari
                      </Typography>
                    </Box>
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Cleanup harian</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {historyPolicy.cleanupTime || '02:15'}
                      </Typography>
                    </Box>
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Checkpoint diam</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {Math.round(Number(historyPolicy.persistIdleSeconds || 300) / 60)} menit
                      </Typography>
                    </Box>
                    <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <Typography variant="caption" className="text-slate-500">Rebuild current-store</Typography>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {historyPolicy.currentStoreRebuildTime || '00:10'}
                      </Typography>
                    </Box>
                  </Box>
                </CardContent>
              </Card>

              <TrackingLevelSummary
                rows={levelSummary}
                selectedLevel={filters.tingkat}
                onLevelSelect={handleFocusLevel}
                onClearLevelFilter={handleClearLevelFilter}
              />

              <TrackingHomeroomSummary
                rows={homeroomSummary}
                selectedHomeroomTeacherId={filters.wali_kelas_id}
                onHomeroomTeacherSelect={handleFocusHomeroomTeacher}
                onClearHomeroomTeacherFilter={handleClearHomeroomTeacherFilter}
              />

              <TrackingClassSummary
                rows={classSummary}
                selectedClass={filters.class}
                onClassSelect={handleFocusClass}
                onClearClassFilter={handleClearClassFilter}
              />
            </Box>
          ) : currentTab === 1 ? (
            <TrackingPriorityQueue
              queues={priorityQueues}
              loading={priorityQueuesLoading}
              onStudentSelect={selectStudent}
              onViewDetails={handleViewDetails}
            />
          ) : currentTab === 2 ? (
            <StudentList
              students={students}
              loading={loading}
              error={error}
              liveTrackingEnabled={historyPolicy.enabled}
              selectedStudent={selectedStudent}
              onStudentSelect={selectStudent}
              onViewDetails={handleViewDetails}
              canManageTrackingSession={canManageTrackingSession}
              onStartTrackingSession={handleStartTrackingSession}
              onStopTrackingSession={handleStopTrackingSession}
              pagination={pagination}
              pageSizeOptions={pageSizeOptions}
              onPageChange={setCurrentPage}
              onRowsPerPageChange={setPageSize}
            />
          ) : (
            <Box className="space-y-6">
              <Box className="space-y-2 px-1">
                <Typography variant="h6" className="font-semibold text-slate-900">
                  Peta Investigasi
                </Typography>
                <Typography variant="body2" className="text-slate-600">
                  Gunakan peta untuk validasi posisi akhir. Filter dan pagination daftar tetap menentukan cohort yang benar-benar dipetakan.
                </Typography>
              </Box>

              <TrackingMap
                students={mapStudents}
                selectedStudent={selectedStudent}
                onStudentSelect={selectStudent}
                attendanceLocations={attendanceLocations}
                height={isMobile ? 460 : 680}
                settings={settings}
                totalStudents={pagination.total}
                pageMeta={pagination}
                overflowCount={mapOverflowCount}
              />
            </Box>
          )}
        </Box>
      </Box>

      {/* Settings Dialog */}
      <TrackingSettings
        open={showSettings}
        onClose={() => setShowSettings(false)}
        settings={settings}
        historyPolicy={historyPolicy}
        dataSources={dataSources}
        historyPolicyLoading={historyPolicyLoading}
        historyPolicySaving={historyPolicySaving}
        canManageHistoryPolicy={canManageHistoryPolicy}
        onSave={handleSettingsSave}
        onReset={resetSettings}
      />

      {/* Export Dialog */}
      <ExportDialog
        open={showExport}
        onClose={() => setShowExport(false)}
        onExport={handleExportSubmit}
        students={students}
        filters={filters}
        historyPolicy={historyPolicy}
        loading={exportLoading}
        error={exportError}
      />

      <Dialog
        open={showActiveTrackingSessionsDialog}
        onClose={handleCloseActiveTrackingSessionsDialog}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>Sesi Pemantauan Tambahan Aktif</DialogTitle>
        <DialogContent className="space-y-4">
          <DialogContentText>
            Daftar sesi pemantauan tambahan yang masih aktif saat ini.
          </DialogContentText>
          {activeSessionsLoading ? (
            <Box className="flex justify-center py-6">
              <CircularProgress size={36} />
            </Box>
          ) : activeSessions.length > 0 ? (
            <Box className="space-y-4">
              {activeSessions.map((session, index) => (
                <Box key={`${session.user_id}-${session.started_at}-${index}`}>
                  <Box className="border rounded-lg border-gray-200 bg-white p-3">
                    <Box className="flex items-start justify-between gap-3">
                      <Box>
                        <div className="font-medium text-gray-900">
                          {session.student_name || `Siswa #${session.user_id}`}
                        </div>
                        <DialogContentText className="!mt-1">
                          Alasan: {session.reason || 'Tidak ada keterangan'}
                        </DialogContentText>
                        <DialogContentText className="!mt-1">
                          Dimulai: {getTimeOnly(session.started_at)} | Selesai: {getTimeOnly(session.expires_at || session.expiresAt)}
                        </DialogContentText>
                        <DialogContentText className="!mt-1">
                          Sisa waktu: {session.remainingMinutes || getRemainingText(session.expires_at || session.expiresAt)}
                        </DialogContentText>
                        <DialogContentText className="!mt-1">
                          Diaktifkan oleh: {session.requested_by_name || `#${session.requested_by || '-'}`}
                        </DialogContentText>
                      </Box>
                      <Box className="flex gap-2">
                        <IconButton
                          size="small"
                          onClick={() => stopTrackingSessionForStudent(session.user_id)}
                          title="Hentikan sesi"
                          className="bg-amber-50 text-amber-700 hover:bg-amber-100"
                        >
                          <Square className="w-4 h-4" />
                        </IconButton>
                      </Box>
                    </Box>
                  </Box>
                  {index < activeSessions.length - 1 && <Divider className="my-2" />}
                </Box>
              ))}
            </Box>
          ) : (
            <DialogContentText>
              Tidak ada sesi pemantauan tambahan yang aktif saat ini.
            </DialogContentText>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseActiveTrackingSessionsDialog}>
            Tutup
          </Button>
          <Button
            onClick={handleShowActiveTrackingSessions}
            variant="contained"
            endIcon={<RefreshCw className="w-4 h-4" />}
          >
            Segarkan
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog
        open={showTrackingSessionDialog}
        onClose={handleCloseTrackingSessionDialog}
      >
        <DialogTitle>Mulai Pemantauan Tambahan</DialogTitle>
        <DialogContent sx={{ minWidth: { xs: 'auto', sm: '28rem' } }}>
          <TextField
            fullWidth
            margin="normal"
            label="Durasi (menit)"
            type="number"
            inputProps={{
              min: 1,
              max: 240
            }}
            value={sessionMinutes}
            onChange={(event) => setSessionMinutes(event.target.value)}
          />
          <TextField
            fullWidth
            multiline
            minRows={2}
            margin="normal"
            label="Alasan (opsional)"
            value={sessionReason}
            onChange={(event) => setSessionReason(event.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseTrackingSessionDialog}>Batal</Button>
          <Button variant="contained" onClick={handleSubmitTrackingSession}>
            Aktifkan
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog
        open={Boolean(detailStudent)}
        onClose={handleCloseDetailDialog}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>Detail Siswa</DialogTitle>
        <DialogContent className="space-y-4">
          <Card className="rounded-3xl border border-slate-200 shadow-none">
            <CardContent className="space-y-4 p-5">
              <Box className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <Box>
                  <Typography variant="h6" className="font-semibold text-slate-900">
                    {detailStudent?.name || '-'}
                  </Typography>
                  <Typography variant="body2" className="text-slate-600">
                    {detailStudent?.class || '-'} | Tingkat {detailStudent?.level || '-'}
                  </Typography>
                </Box>
                <Box className="flex flex-wrap gap-2">
                  <Chip
                    size="small"
                    variant="outlined"
                    color={getTrackingStatusColor(detailStudent?.status)}
                    label={formatTrackingStatus(detailStudent?.status)}
                  />
                  <Chip size="small" variant="outlined" label={formatGpsQuality(detailStudent?.gpsQualityStatus)} />
                  {detailStudent?.trackingSessionActive ? (
                    <Chip size="small" color="info" variant="outlined" label="Pemantauan tambahan aktif" />
                  ) : null}
                </Box>
              </Box>

              <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <Typography variant="caption" className="text-slate-500">
                  Status saat ini
                </Typography>
                <Typography variant="body2" className="mt-1 font-medium text-slate-900">
                  {getTrackingStatusReasonLabel(detailStudent?.trackingStatusReason, detailStudent?.status)}
                </Typography>
              </Box>

              <Box className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <Typography variant="caption" className="text-slate-500">Email</Typography>
                  <Typography variant="body2" className="font-medium text-slate-900">{detailStudent?.email || '-'}</Typography>
                </Box>
                <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <Typography variant="caption" className="text-slate-500">Wali kelas</Typography>
                  <Typography variant="body2" className="font-medium text-slate-900">{detailStudent?.homeroomTeacherName || 'Belum ditentukan'}</Typography>
                </Box>
                <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <Typography variant="caption" className="text-slate-500">Update terakhir</Typography>
                  <Typography variant="body2" className="font-medium text-slate-900">
                    {detailStudent?.lastUpdate
                      ? (formatServerDateTime(detailStudent.lastUpdate, 'id-ID') || '-')
                      : 'Belum ada data tracking'}
                  </Typography>
                </Box>
                <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                  <Typography variant="caption" className="text-slate-500">Lokasi terakhir diketahui</Typography>
                  <Typography variant="body2" className="font-medium text-slate-900">
                    {getLocationContextLabel(detailStudent)}
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>

          <Box className="space-y-3">
            <Typography variant="subtitle2" className="font-medium text-slate-900">
              Ringkasan Hari Ini
            </Typography>
            {detailHistoryLoading ? (
              <Box className="flex items-center gap-2 py-3 text-slate-600">
                <CircularProgress size={18} />
                <span>Memuat riwayat tracking...</span>
              </Box>
            ) : detailHistoryError ? (
              <Alert severity="error">{detailHistoryError}</Alert>
            ) : detailHistory ? (
              <>
                <Box className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <Typography variant="caption" className="text-slate-500">Titik tersimpan</Typography>
                    <Typography variant="body1" className="font-semibold text-slate-900">{detailHistory?.statistics?.total_points ?? 0}</Typography>
                  </Box>
                  <Box className="rounded-2xl border border-slate-200 bg-emerald-50 p-3">
                    <Typography variant="caption" className="text-emerald-700">Dalam area</Typography>
                    <Typography variant="body1" className="font-semibold text-emerald-900">{detailHistory?.statistics?.in_school_area ?? 0}</Typography>
                  </Box>
                  <Box className="rounded-2xl border border-slate-200 bg-orange-50 p-3">
                    <Typography variant="caption" className="text-orange-700">Luar area</Typography>
                    <Typography variant="body1" className="font-semibold text-orange-900">{detailHistory?.statistics?.outside_school_area ?? 0}</Typography>
                  </Box>
                  <Box className="rounded-2xl border border-slate-200 bg-blue-50 p-3">
                    <Typography variant="caption" className="text-blue-700">Persen dalam area</Typography>
                    <Typography variant="body1" className="font-semibold text-blue-900">{detailHistory?.statistics?.percentage_in_school ?? 0}%</Typography>
                  </Box>
                </Box>

                <Alert severity="info" className="rounded-2xl">
                  <Typography variant="body2" className="font-medium text-slate-900">
                    Riwayat harian ini adalah titik yang benar-benar disimpan.
                  </Typography>
                  <Typography variant="body2" className="mt-1 text-slate-700">
                    Sistem tidak menyimpan setiap ping GPS. Titik baru masuk histori saat perpindahan mencapai
                    minimal {Number(historyPolicy.minDistanceMeters || 20)} meter, saat status berubah,
                    dan checkpoint tiap {Math.max(1, Math.round(Number(historyPolicy.persistIdleSeconds || 300) / 60))} menit saat diam.
                  </Typography>
                </Alert>

                <Typography variant="subtitle2" className="pt-1 font-medium text-slate-900">
                  Riwayat Titik Tersimpan
                </Typography>
                {detailHistoryRows.length > 0 ? (
                  <Box className="space-y-2">
                    <Typography variant="caption" className="block text-slate-500">
                      Menampilkan {detailHistoryRows.length} titik terbaru dari total {detailHistory?.statistics?.total_points ?? detailHistoryRows.length} titik tersimpan hari ini.
                    </Typography>
                    {detailHistoryRows.map((row, index) => (
                      <Box
                        key={`${row?.id || 'row'}-${row?.tracked_at || index}`}
                        className="rounded-2xl border border-slate-200 bg-white p-3"
                      >
                        <Typography variant="body2" className="font-medium text-slate-900">
                          {formatServerDateTime(row?.tracked_at, 'id-ID') || '-'}
                        </Typography>
                        <Typography variant="body2" className="mt-1 text-slate-700">
                          {row?.location_name || 'Lokasi tidak diketahui'}
                        </Typography>
                        <Typography variant="caption" className="mt-1 block text-slate-500">
                          Status {row?.is_in_school_area ? 'Dalam area' : 'Luar area'} | GPS {formatGpsQuality(row?.gps_quality_status)} | Device {row?.device_source || '-'}
                        </Typography>
                        <Typography variant="caption" className="block text-slate-500">
                          Akurasi {row?.accuracy ?? '-'} m | IP {row?.ip_address || '-'}
                        </Typography>
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <DialogContentText>
                    Tidak ada riwayat tracking hari ini.
                  </DialogContentText>
                )}
              </>
            ) : (
              <DialogContentText>
                Riwayat tracking belum dimuat.
              </DialogContentText>
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button
            variant="outlined"
            onClick={() => handleOpenHistoryMap(detailStudent)}
            disabled={!detailStudent?.id}
          >
            Lihat Histori di Peta
          </Button>
          <Button onClick={handleCloseDetailDialog}>Tutup</Button>
        </DialogActions>
      </Dialog>

      <TrackingHistoryMapDialog
        open={showHistoryMapDialog}
        onClose={handleCloseHistoryMapDialog}
        loading={historyMapLoading}
        error={historyMapError}
        data={historyMapData}
        attendanceLocations={attendanceLocations}
        settings={settings}
        historyPolicy={historyPolicy}
        compareOptions={historyMapCompareOptions}
        searchText={historyMapSearchText}
        searchLoading={historyMapSearchLoading}
        onSearchTextChange={handleHistoryMapSearchTextChange}
        selectedStudentIds={historyMapStudentIds}
        onSelectedStudentIdsChange={handleHistoryMapSelectionChange}
        filters={historyMapFilters}
        onFiltersChange={(partialFilters) => setHistoryMapFilters((previous) => ({ ...previous, ...partialFilters }))}
        onApply={() => loadHistoryMap()}
        onDownloadPdf={(exportScope) => exportTrackingHistoryMapPdf(historyMapStudentIds, {
          date: historyMapFilters?.date,
          startTime: historyMapFilters?.startTime,
          endTime: historyMapFilters?.endTime,
          focusUserId: historyMapFocusedUserId,
          exportScope,
        })}
        focusedUserId={historyMapFocusedUserId}
        onFocusUserChange={setHistoryMapFocusedUserId}
        compareLimit={5}
      />
    </Container>
  );
};

export default LiveTrackingNew;



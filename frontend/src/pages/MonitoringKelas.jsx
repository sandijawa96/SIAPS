import React, { useMemo, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Paper,
  Select,
  Tab,
  Tabs,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import {
  AlertTriangle,
  CalendarDays,
  Download,
  RefreshCw,
  Search,
  ShieldAlert,
  ShieldCheck,
  Users,
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import { monitoringKelasAPI } from '../services/api';
import useMonitoringKelas from '../hooks/useMonitoringKelas';
import { formatServerDate, formatServerDateTime } from '../services/serverClock';

const FRAUD_SOURCES = [
  { value: '', label: 'Semua Tahap' },
  { value: 'attendance_precheck', label: 'Pra-cek' },
  { value: 'attendance_submit', label: 'Submit Presensi' },
];

const VALIDATION_STATUSES = [
  { value: '', label: 'Semua Status' },
  { value: 'valid', label: 'Valid' },
  { value: 'warning', label: 'Warning' },
];

const SECURITY_STATUSES = [
  { value: '', label: 'Semua Status' },
  { value: 'flagged', label: 'Warning' },
  { value: 'blocked', label: 'Blocked' },
  { value: 'allowed', label: 'Allowed' },
];

const SECURITY_STAGES = [
  { value: '', label: 'Semua Tahap' },
  { value: 'attendance_precheck', label: 'Pra-cek' },
  { value: 'attendance_submit', label: 'Submit Presensi' },
];

const ATTEMPT_TYPES = [
  { value: '', label: 'Semua Jenis Absen' },
  { value: 'masuk', label: 'Masuk' },
  { value: 'pulang', label: 'Pulang' },
];

const CASE_STATUSES = [
  { value: '', label: 'Semua Kasus' },
  { value: 'open', label: 'Terbuka' },
  { value: 'reopened', label: 'Dibuka ulang' },
  { value: 'resolved', label: 'Selesai' },
  { value: 'escalated', label: 'Dieskalasi' },
];

const CASE_SCOPES = [
  { value: 'active', label: 'Aktif' },
  { value: 'archive', label: 'Arsip' },
  { value: 'all', label: 'Semua' },
];

const STUDENT_SECURITY_SCOPES = [
  { value: 'needs_case', label: 'Perlu Kasus' },
  { value: 'in_progress', label: 'Dalam Penanganan' },
  { value: 'done', label: 'Selesai' },
  { value: 'all', label: 'Semua' },
];

const CASE_PRIORITIES = [
  { value: '', label: 'Semua Prioritas' },
  { value: 'low', label: 'Rendah' },
  { value: 'medium', label: 'Sedang' },
  { value: 'high', label: 'Tinggi' },
  { value: 'critical', label: 'Kritis' },
];

const CASE_RESOLUTIONS = [
  { value: 'student_guided', label: 'Siswa dibina' },
  { value: 'device_fixed', label: 'Perangkat diperbaiki' },
  { value: 'confirmed_violation', label: 'Terbukti melanggar' },
  { value: 'false_positive', label: 'False positive' },
  { value: 'parent_notified', label: 'Orang tua diberitahu' },
  { value: 'followed_up', label: 'Ditindaklanjuti' },
];

const ISSUE_OPTIONS = [
  { value: '', label: 'Semua Issue' },
  { value: 'mock_location_detected', label: 'Mock location / Fake GPS' },
  { value: 'outside_geofence', label: 'Di luar geofence' },
  { value: 'gps_accuracy_low', label: 'Akurasi GPS rendah' },
  { value: 'developer_options_enabled', label: 'Developer options aktif' },
  { value: 'root_or_jailbreak_detected', label: 'Root / jailbreak' },
  { value: 'emulator_detected', label: 'Emulator terdeteksi' },
  { value: 'app_clone_detected', label: 'Clone / dual app' },
  { value: 'app_tampering_detected', label: 'Integritas aplikasi bermasalah' },
  { value: 'instrumentation_detected', label: 'Instrumentation / hooking' },
  { value: 'device_lock_violation', label: 'Pelanggaran device binding' },
];

const LEAVE_STATUSES = [
  { value: '', label: 'Semua Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
];

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const getFileNameFromDisposition = (value, fallbackName) => {
  const header = String(value || '');
  const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utfMatch?.[1]) {
    return decodeURIComponent(utfMatch[1]);
  }

  const asciiMatch = header.match(/filename="?([^"]+)"?/i);
  if (asciiMatch?.[1]) {
    return asciiMatch[1];
  }

  return fallbackName;
};

const downloadBlobResponse = (response, fallbackName) => {
  const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data ?? '']);
  const fileName = getFileNameFromDisposition(response?.headers?.['content-disposition'], fallbackName);
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(url);
};

const statusChipTone = (value) => {
  const status = String(value || '').toLowerCase();
  if (status === 'warning' || status === 'flagged') {
    return 'warning';
  }
  if (status === 'valid' || status === 'approved' || status === 'allowed') {
    return 'success';
  }
  if (status === 'blocked' || status === 'rejected' || status === 'alpha') {
    return 'error';
  }
  return 'default';
};

const securityStudentStatusTone = (value) => {
  const status = String(value || '').toLowerCase();
  if (status === 'needs_reopen') {
    return 'error';
  }
  if (status === 'needs_case') {
    return 'warning';
  }
  if (status === 'in_progress') {
    return 'info';
  }
  if (status === 'done') {
    return 'success';
  }
  return 'default';
};

const asArray = (value) => (Array.isArray(value) ? value : []);

const compactText = (value, fallback = '-', maxLength = null) => {
  let normalizedFallback = fallback;
  let normalizedMaxLength = maxLength;
  if (typeof fallback === 'number' && maxLength === null) {
    normalizedMaxLength = fallback;
    normalizedFallback = '-';
  }

  const text = String(value ?? '').trim();
  if (!text) {
    return normalizedFallback;
  }

  const limit = Number(normalizedMaxLength);
  if (Number.isFinite(limit) && limit > 3 && text.length > limit) {
    return `${text.slice(0, limit - 3)}...`;
  }

  return text;
};

const renderNoticeBoxes = (boxes, keyPrefix) => {
  const rows = asArray(boxes).filter(Boolean);
  if (rows.length === 0) {
    return null;
  }

  return (
    <Box className="mt-2 space-y-2">
      {rows.map((box, index) => (
        <div key={`${keyPrefix}-${index}`} className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
          <Typography variant="caption" className="font-semibold text-amber-900">
            {box.title || box.label || 'Catatan warning'}
          </Typography>
          {box.message ? (
            <Typography variant="caption" className="mt-1 block text-amber-800">
              {box.message}
            </Typography>
          ) : null}
          {Array.isArray(box.items) && box.items.length > 0 ? (
            <Box className="mt-1 flex flex-wrap gap-1">
              {box.items.slice(0, 6).map((item, itemIndex) => (
                <Chip key={`${keyPrefix}-${index}-${itemIndex}`} size="small" variant="outlined" label={compactText(item)} />
              ))}
            </Box>
          ) : null}
          {Array.isArray(box.issues) && box.issues.length > 0 ? (
            <Box className="mt-2 flex flex-wrap gap-1">
              {box.issues.slice(0, 6).map((issue, issueIndex) => (
                <Chip
                  key={`${keyPrefix}-${index}-issue-${issue.event_key || issueIndex}`}
                  size="small"
                  color="warning"
                  variant="outlined"
                  label={issue.label || issue.event_key || '-'}
                />
              ))}
            </Box>
          ) : null}
        </div>
      ))}
    </Box>
  );
};

const renderFlagChips = (row) => {
  const flags = asArray(row?.flags).length > 0 ? asArray(row.flags) : asArray(row?.fraud_flags);
  if (flags.length === 0) {
    return <span className="text-xs text-slate-500">-</span>;
  }

  return (
    <Box className="flex flex-wrap gap-1">
      {flags.slice(0, 5).map((flag, index) => (
        <Chip
          key={`${row?.id || 'flag'}-${flag.flag_key || index}`}
          size="small"
          color="warning"
          variant="outlined"
          label={flag.label || flag.flag_key || '-'}
        />
      ))}
    </Box>
  );
};

const renderIssueChips = (row) => {
  const issues = asArray(row?.issues);
  if (issues.length === 0) {
    return <span className="text-xs text-slate-500">-</span>;
  }

  return (
    <Box className="flex flex-wrap gap-1">
      {issues.slice(0, 5).map((issue, index) => (
        <Chip
          key={`${row?.id || 'issue'}-${issue.event_key || index}`}
          size="small"
          color="warning"
          variant="outlined"
          label={issue.label || issue.event_key || '-'}
        />
      ))}
    </Box>
  );
};

const PaginationToolbar = ({ meta, loading, onPageChange, onPerPageChange }) => {
  const currentPage = Math.max(1, toNumber(meta?.current_page, 1));
  const lastPage = Math.max(1, toNumber(meta?.last_page, 1));
  const perPage = Math.max(1, toNumber(meta?.per_page, 10));
  const total = Math.max(0, toNumber(meta?.total, 0));
  const from = toNumber(meta?.from, total > 0 ? ((currentPage - 1) * perPage) + 1 : 0);
  const to = toNumber(meta?.to, Math.min(total, from + perPage - 1));

  return (
    <Box className="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between">
      <Typography variant="body2" className="text-slate-600">
        Menampilkan {from} - {to} dari {total} data
      </Typography>

      <Box className="flex items-center gap-2">
        <Typography variant="body2" className="text-slate-600">
          Per halaman
        </Typography>
        <Select
          size="small"
          value={perPage}
          onChange={(event) => onPerPageChange(Number(event.target.value))}
          sx={{ minWidth: 88 }}
          disabled={loading}
        >
          {[10, 15, 25, 50].map((size) => (
            <MenuItem key={size} value={size}>
              {size}
            </MenuItem>
          ))}
        </Select>
        <Button size="small" variant="outlined" disabled={loading || currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>
          Sebelumnya
        </Button>
        <Typography variant="body2" className="min-w-[80px] text-center text-slate-600">
          {currentPage} / {lastPage}
        </Typography>
        <Button size="small" variant="outlined" disabled={loading || currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>
          Berikutnya
        </Button>
      </Box>
    </Box>
  );
};

const MonitoringKelas = () => {
  const [classSearch, setClassSearch] = useState('');
  const [tab, setTab] = useState(0);
  const [caseDialog, setCaseDialog] = useState({ open: false, studentRow: null });
  const [caseForm, setCaseForm] = useState({ priority: 'medium', summary: '', staff_notes: '' });
  const [caseSubmitting, setCaseSubmitting] = useState(false);
  const [resolveDialog, setResolveDialog] = useState({ open: false, caseRow: null });
  const [resolveForm, setResolveForm] = useState({ resolution: 'student_guided', staff_notes: '' });
  const [resolveSubmitting, setResolveSubmitting] = useState(false);
  const [evidenceDialog, setEvidenceDialog] = useState({ open: false, caseRow: null });
  const [evidenceForm, setEvidenceForm] = useState({ evidence_type: 'screenshot', title: '', description: '', file: null });
  const [evidenceSubmitting, setEvidenceSubmitting] = useState(false);

  const {
    classes,
    classesLoading,
    classesError,
    selectedClassId,
    selectedClass,
    setSelectedClassId,
    loadClasses,
    overviewLoading,
    overviewError,
    classDetail,
    statistics,
    attendance,
    attendanceSummary,
    attendanceDate,
    setAttendanceDate,
    statisticsMonth,
    setStatisticsMonth,
    fraudRows,
    fraudSummary,
    fraudConfig,
    fraudLoading,
    fraudError,
    fraudFilters,
    setFraudFilters,
    fraudPagination,
    setFraudPagination,
    securityRows,
    securitySummary,
    securityConfig,
    securityLoading,
    securityError,
    securityFilters,
    setSecurityFilters,
    securityPagination,
    setSecurityPagination,
    securityStudentRows,
    securityStudentSummary,
    securityStudentsLoading,
    securityStudentsError,
    securityStudentScope,
    setSecurityStudentScope,
    securityStudentPagination,
    setSecurityStudentPagination,
    securityCaseRows,
    securityCasesLoading,
    securityCasesError,
    securityCaseFilters,
    setSecurityCaseFilters,
    securityCasePagination,
    setSecurityCasePagination,
    createSecurityCase,
    resolveSecurityCase,
    reopenSecurityCase,
    uploadSecurityCaseEvidence,
    leaveRows,
    leaveLoading,
    leaveError,
    leaveFilters,
    setLeaveFilters,
    leavePagination,
    setLeavePagination,
    refreshing,
    refreshAllForSelectedClass,
  } = useMonitoringKelas();

  const filteredClasses = useMemo(() => {
    const keyword = String(classSearch || '').trim().toLowerCase();
    if (!keyword) {
      return classes;
    }

    return classes.filter((row) =>
      [row.nama_lengkap, row.tingkat, row.jurusan]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(keyword))
    );
  }, [classSearch, classes]);

  const fraudTopFlags = useMemo(() => (
    Array.isArray(fraudSummary?.top_flags) ? fraudSummary.top_flags : []
  ), [fraudSummary]);

  const recentFraudWarnings = useMemo(() => (
    Array.isArray(fraudSummary?.recent_warning_assessments)
      ? fraudSummary.recent_warning_assessments
      : []
  ), [fraudSummary]);

  const securityFollowUpStudents = useMemo(() => (
    Array.isArray(securitySummary?.follow_up_students) ? securitySummary.follow_up_students : []
  ), [securitySummary]);

  const securityStageBreakdown = useMemo(() => (
    Array.isArray(securitySummary?.stage_breakdown) ? securitySummary.stage_breakdown : []
  ), [securitySummary]);

  const securityEventBreakdown = useMemo(() => (
    Array.isArray(securitySummary?.event_breakdown) ? securitySummary.event_breakdown : []
  ), [securitySummary]);

  const handleSelectClass = (classId) => {
    setSelectedClassId(classId);
    setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
    setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
    setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
    setSecurityCasePagination((previous) => ({ ...previous, current_page: 1 }));
    setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
  };

  const handleRefreshAll = async () => {
    const refreshed = await refreshAllForSelectedClass();
    if (refreshed) {
      toast.success('Monitoring kelas berhasil diperbarui');
    } else {
      toast.error('Kelas belum dipilih');
    }
  };

  const handleExportFraud = async () => {
    if (!selectedClassId) {
      return;
    }

    try {
      const response = await monitoringKelasAPI.exportClassFraudAssessments(selectedClassId, {
        ...(fraudFilters.source ? { source: fraudFilters.source } : {}),
        ...(fraudFilters.validation_status ? { validation_status: fraudFilters.validation_status } : {}),
        ...(fraudFilters.attempt_type ? { attempt_type: fraudFilters.attempt_type } : {}),
        ...(fraudFilters.flag_key ? { flag_key: fraudFilters.flag_key } : {}),
        ...(fraudFilters.date_from ? { date_from: fraudFilters.date_from } : {}),
        ...(fraudFilters.date_to ? { date_to: fraudFilters.date_to } : {}),
      });
      downloadBlobResponse(response, 'monitoring-kelas-fraud.csv');
      toast.success('Export fraud monitoring berhasil');
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal export fraud monitoring');
    }
  };

  const handleExportSecurity = async () => {
    if (!selectedClassId) {
      return;
    }

    try {
      const response = await monitoringKelasAPI.exportClassSecurityEvents(selectedClassId, {
        ...(securityFilters.issue_key ? { issue_key: securityFilters.issue_key } : {}),
        ...(securityFilters.status ? { status: securityFilters.status } : {}),
        ...(securityFilters.stage ? { stage: securityFilters.stage } : {}),
        ...(securityFilters.attempt_type ? { attempt_type: securityFilters.attempt_type } : {}),
        ...(securityFilters.date_from ? { date_from: securityFilters.date_from } : {}),
        ...(securityFilters.date_to ? { date_to: securityFilters.date_to } : {}),
      });
      downloadBlobResponse(response, 'monitoring-kelas-security.csv');
      toast.success('Export security monitoring berhasil');
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal export security monitoring');
    }
  };

  const openCreateCaseDialog = (studentRow) => {
    const studentName = studentRow?.student?.name || 'Siswa';
    const mainIssue = studentRow?.top_issues?.[0]?.label || studentRow?.last_event_label || 'indikasi keamanan presensi';
    const sequenceLabel = studentRow?.violation_sequence_label || 'Pelanggaran #1';
    const previousCase = studentRow?.case_comparison?.previous_case;
    const repeatedKeys = asArray(studentRow?.case_comparison?.repeated_issue_keys);
    const daysSincePreviousCase = studentRow?.case_comparison?.days_since_previous_case_resolved;
    const comparisonNote = previousCase
      ? `Pembanding: kasus ${previousCase.case_number || `#${previousCase.id}`} selesai ${daysSincePreviousCase ?? '-'} hari lalu. Issue berulang: ${repeatedKeys.length || 0}.`
      : '';

    setCaseDialog({ open: true, studentRow });
    setCaseForm({
      priority: toNumber(studentRow?.total_warnings, 0) >= 5 ? 'high' : 'medium',
      summary: `${sequenceLabel} - ${studentName}: ${mainIssue}`,
      staff_notes: [studentRow?.recommendation, comparisonNote].filter(Boolean).join('\n'),
    });
  };

  const closeCreateCaseDialog = () => {
    if (caseSubmitting) {
      return;
    }
    setCaseDialog({ open: false, studentRow: null });
  };

  const handleCreateCase = async () => {
    const userId = caseDialog?.studentRow?.user_id || caseDialog?.studentRow?.student?.user_id;
    if (!userId) {
      toast.error('Siswa tidak valid');
      return;
    }

    setCaseSubmitting(true);
    try {
      await createSecurityCase({
        user_id: userId,
        priority: caseForm.priority,
        summary: caseForm.summary,
        staff_notes: caseForm.staff_notes,
      });
      toast.success('Kasus tindak lanjut dibuat');
      setCaseDialog({ open: false, studentRow: null });
      setTab(2);
    } catch (error) {
      toast.error(error?.response?.data?.message || error?.message || 'Gagal membuat kasus');
    } finally {
      setCaseSubmitting(false);
    }
  };

  const openResolveCaseDialog = (caseRow) => {
    setResolveDialog({ open: true, caseRow });
    setResolveForm({ resolution: 'student_guided', staff_notes: caseRow?.staff_notes || '' });
  };

  const closeResolveCaseDialog = () => {
    if (resolveSubmitting) {
      return;
    }
    setResolveDialog({ open: false, caseRow: null });
  };

  const handleResolveCase = async () => {
    const caseId = resolveDialog?.caseRow?.id;
    if (!caseId) {
      toast.error('Kasus tidak valid');
      return;
    }

    setResolveSubmitting(true);
    try {
      await resolveSecurityCase(caseId, {
        resolution: resolveForm.resolution,
        staff_notes: resolveForm.staff_notes,
      });
      toast.success('Kasus ditandai selesai');
      setResolveDialog({ open: false, caseRow: null });
    } catch (error) {
      toast.error(error?.response?.data?.message || error?.message || 'Gagal menyelesaikan kasus');
    } finally {
      setResolveSubmitting(false);
    }
  };

  const handleReopenCase = async (caseRow) => {
    if (!caseRow?.id) {
      return;
    }

    try {
      await reopenSecurityCase(caseRow.id);
      toast.success('Kasus dibuka ulang');
    } catch (error) {
      toast.error(error?.response?.data?.message || error?.message || 'Gagal membuka ulang kasus');
    }
  };

  const openEvidenceDialog = (caseRow) => {
    setEvidenceDialog({ open: true, caseRow });
    setEvidenceForm({
      evidence_type: 'screenshot',
      title: `Bukti ${caseRow?.case_number || ''}`.trim(),
      description: '',
      file: null,
    });
  };

  const closeEvidenceDialog = () => {
    if (evidenceSubmitting) {
      return;
    }
    setEvidenceDialog({ open: false, caseRow: null });
  };

  const handleUploadEvidence = async () => {
    const caseId = evidenceDialog?.caseRow?.id;
    if (!caseId) {
      toast.error('Kasus tidak valid');
      return;
    }

    if (!evidenceForm.file && !String(evidenceForm.description || '').trim()) {
      toast.error('Isi catatan bukti atau pilih file');
      return;
    }

    const formData = new FormData();
    formData.append('evidence_type', evidenceForm.evidence_type);
    formData.append('title', evidenceForm.title || 'Bukti tindak lanjut');
    formData.append('description', evidenceForm.description || '');
    if (evidenceForm.file) {
      formData.append('file', evidenceForm.file);
    }

    setEvidenceSubmitting(true);
    try {
      await uploadSecurityCaseEvidence(caseId, formData);
      toast.success('Bukti kasus ditambahkan');
      setEvidenceDialog({ open: false, caseRow: null });
    } catch (error) {
      toast.error(error?.response?.data?.message || error?.message || 'Gagal menambahkan bukti');
    } finally {
      setEvidenceSubmitting(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <Box className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-xl">
            <ShieldCheck className="w-6 h-6 text-blue-600" />
          </div>
          <div className="flex-1">
            <Typography variant="h5" className="font-bold text-gray-900">
              Monitoring Kelas
            </Typography>
            <Typography variant="body2" className="text-gray-600 mt-1">
              Rekap terpadu untuk absensi, warning fraud, security event, dan izin per kelas.
            </Typography>
            <div className="flex flex-wrap gap-2 mt-3">
              <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full border border-blue-200 bg-blue-50 text-blue-700">
                <Users className="w-3.5 h-3.5" />
                Siap skala kelas besar
              </span>
              <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700">
                <RefreshCw className="w-3.5 h-3.5" />
                Ringkas & warning oriented
              </span>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outlined" startIcon={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />} onClick={handleRefreshAll} disabled={refreshing || !selectedClassId}>
              Refresh
            </Button>
            <Button variant="outlined" startIcon={<RefreshCw className={`w-4 h-4 ${classesLoading ? 'animate-spin' : ''}`} />} onClick={loadClasses} disabled={classesLoading}>
              Reload Kelas
            </Button>
          </div>
        </Box>
      </div>

      {classesError ? <Alert severity="error">{classesError}</Alert> : null}
      {overviewError ? <Alert severity="error">{overviewError}</Alert> : null}
      {fraudError ? <Alert severity="error">{fraudError}</Alert> : null}
      {securityError ? <Alert severity="error">{securityError}</Alert> : null}
      {securityStudentsError ? <Alert severity="error">{securityStudentsError}</Alert> : null}
      {securityCasesError ? <Alert severity="error">{securityCasesError}</Alert> : null}
      {leaveError ? <Alert severity="error">{leaveError}</Alert> : null}

      <div className="grid grid-cols-1 xl:grid-cols-[320px_1fr] gap-6">
        <Paper className="border border-gray-200 rounded-2xl p-4 space-y-4">
          <Typography variant="subtitle1" className="font-semibold text-slate-900">
            Daftar Kelas
          </Typography>
          <TextField
            size="small"
            placeholder="Cari kelas..."
            fullWidth
            value={classSearch}
            onChange={(event) => setClassSearch(event.target.value)}
            InputProps={{
              startAdornment: <Search className="w-4 h-4 mr-2 text-slate-400" />,
            }}
          />

          {classesLoading ? (
            <Box className="py-8 flex justify-center">
              <CircularProgress size={24} />
            </Box>
          ) : filteredClasses.length === 0 ? (
            <Typography variant="body2" className="text-slate-500">
              Kelas tidak ditemukan.
            </Typography>
          ) : (
            <Box className="max-h-[560px] overflow-y-auto space-y-2 pr-1">
              {filteredClasses.map((kelas) => {
                const active = kelas.id === selectedClassId;
                return (
                  <button
                    key={kelas.id}
                    type="button"
                    onClick={() => handleSelectClass(kelas.id)}
                    className={`w-full rounded-xl border p-3 text-left transition ${
                      active
                        ? 'border-blue-300 bg-blue-50'
                        : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-slate-50'
                    }`}
                  >
                    <div className="text-sm font-semibold text-slate-900">{kelas.nama_lengkap}</div>
                    <div className="mt-1 text-xs text-slate-500">Siswa {kelas.jumlah_siswa}</div>
                    <div className="mt-2 flex flex-wrap gap-1">
                      <Chip size="small" label={`Hadir ${kelas.hadir_hari_ini}`} color="success" variant="outlined" />
                      <Chip size="small" label={`Terlambat ${kelas.terlambat_hari_ini}`} color="warning" variant="outlined" />
                      <Chip size="small" label={`Tidak hadir ${kelas.tidak_hadir_hari_ini}`} color="error" variant="outlined" />
                    </div>
                  </button>
                );
              })}
            </Box>
          )}
        </Paper>

        <Paper className="border border-gray-200 rounded-2xl p-5">
          {!selectedClassId ? (
            <Box className="py-16 text-center text-slate-500">
              Pilih kelas terlebih dulu untuk menampilkan monitoring.
            </Box>
          ) : (
            <Box className="space-y-4">
              <Box className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <Typography variant="h6" className="font-semibold text-slate-900">
                    {selectedClass?.nama_lengkap || classDetail?.kelas?.nama_lengkap || 'Kelas terpilih'}
                  </Typography>
                  <Typography variant="body2" className="text-slate-600">
                    Siswa {toNumber(selectedClass?.jumlah_siswa || classDetail?.kelas?.jumlah_siswa, 0)} | Izin pending {toNumber(classDetail?.izin_pending, 0)}
                  </Typography>
                </div>
                <Box className="flex items-center gap-2">
                  {tab === 3 ? (
                    <Button size="small" variant="outlined" startIcon={<Download className="w-4 h-4" />} onClick={handleExportFraud}>
                      Export Fraud
                    </Button>
                  ) : null}
                  {tab === 4 ? (
                    <Button size="small" variant="outlined" startIcon={<Download className="w-4 h-4" />} onClick={handleExportSecurity}>
                      Export Security
                    </Button>
                  ) : null}
                </Box>
              </Box>

              <Tabs value={tab} onChange={(_, value) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
                <Tab label="Ringkasan" />
                <Tab label="Siswa Keamanan" />
                <Tab label="Kasus" />
                <Tab label="Fraud Monitoring" />
                <Tab label="Security Events" />
                <Tab label="Izin" />
                <Tab label="Absensi" />
              </Tabs>

              {tab === 0 ? (
                <Box className="space-y-5">
                  <Box className="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    {[
                      { label: 'Hadir Hari Ini', value: classDetail?.hadir_hari_ini, tone: 'bg-emerald-50 border-emerald-200 text-emerald-900' },
                      { label: 'Terlambat Hari Ini', value: classDetail?.terlambat_hari_ini, tone: 'bg-amber-50 border-amber-200 text-amber-900' },
                      { label: 'Tidak Hadir', value: classDetail?.tidak_hadir_hari_ini, tone: 'bg-rose-50 border-rose-200 text-rose-900' },
                      { label: 'Persentase Bulanan', value: `${toNumber(statistics?.persentase_kehadiran, 0).toFixed(2)}%`, tone: 'bg-blue-50 border-blue-200 text-blue-900' },
                      { label: 'Izin Pending', value: classDetail?.izin_pending, tone: 'bg-slate-50 border-slate-200 text-slate-900' },
                    ].map((item) => (
                      <div key={item.label} className={`rounded-2xl border p-4 ${item.tone}`}>
                        <div className="text-xs font-semibold uppercase tracking-[0.14em]">{item.label}</div>
                        <div className="mt-2 text-2xl font-bold">{item.value ?? 0}</div>
                      </div>
                    ))}
                  </Box>

                  <Box className="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    {[
                      { label: 'Fraud Warning', value: fraudSummary?.warning_count },
                      { label: 'Precheck Warning', value: fraudSummary?.precheck_warning_count },
                      { label: 'Submit Warning', value: fraudSummary?.submit_warning_count },
                      { label: 'Security Event', value: securitySummary?.total_events },
                      { label: 'Fake GPS', value: securitySummary?.mock_location_events },
                      { label: 'Device/App', value: securitySummary?.device_events },
                    ].map((item) => (
                      <Paper key={item.label} variant="outlined" className="rounded-xl p-3">
                        <Typography variant="caption" className="text-slate-500">{item.label}</Typography>
                        <Typography variant="h6" className="font-bold text-slate-900">{toNumber(item.value, 0)}</Typography>
                      </Paper>
                    ))}
                  </Box>

                  <Box className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Siswa Alpha Terbanyak Bulan Ini
                      </Typography>
                      {!statistics?.siswa_terbanyak_alpha?.length ? (
                        <Typography variant="body2" className="text-slate-500 mt-2">
                          Belum ada data alpha bulan ini.
                        </Typography>
                      ) : (
                        <Box className="space-y-2 mt-3">
                          {statistics.siswa_terbanyak_alpha.slice(0, 8).map((row) => (
                            <Box key={`${row.id}-${row.total_alpha}`} className="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2">
                              <div>
                                <Typography variant="body2" className="font-semibold text-slate-900">{row.nama}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row.nisn || '-'}</Typography>
                              </div>
                              <Chip size="small" label={`Alpha ${toNumber(row.total_alpha, 0)}`} color="error" variant="outlined" />
                            </Box>
                          ))}
                        </Box>
                      )}
                    </Paper>

                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Siswa Prioritas Tindak Lanjut
                      </Typography>
                      {!securityFollowUpStudents.length ? (
                        <Typography variant="body2" className="text-slate-500 mt-2">
                          Belum ada siswa prioritas dari security monitoring.
                        </Typography>
                      ) : (
                        <Box className="space-y-2 mt-3">
                          {securityFollowUpStudents.slice(0, 8).map((row) => (
                            <Box key={`${row.user_id}-${row.last_event_at || 'recent'}`} className="rounded-xl border border-slate-200 px-3 py-2">
                              <Typography variant="body2" className="font-semibold text-slate-900">{row.student_name || '-'}</Typography>
                              <Typography variant="caption" className="text-slate-500">{row.student_identifier || '-'}</Typography>
                              <Box className="flex flex-wrap gap-1 mt-2">
                                <Chip size="small" label={`Total ${toNumber(row.total_events, 0)}`} variant="outlined" />
                                <Chip size="small" label={`Fake GPS ${toNumber(row.mock_location_events, 0)}`} color="warning" variant="outlined" />
                                <Chip size="small" label={`Blocked ${toNumber(row.blocked_events, 0)}`} color="error" variant="outlined" />
                              </Box>
                            </Box>
                          ))}
                        </Box>
                      )}
                    </Paper>
                  </Box>

                  <Alert severity="info" icon={<ShieldCheck className="w-4 h-4" />}>
                    Mode monitoring aktif: warning-only. Device binding tetap jadi satu-satunya hard block.
                  </Alert>
                </Box>
              ) : null}

              {tab === 1 ? (
                <Box className="space-y-4">
                  <Box className="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    {[
                      { label: 'Siswa Dipantau', value: securityStudentSummary?.total_students },
                      { label: 'Perlu Kasus', value: securityStudentSummary?.students_need_follow_up },
                      { label: 'Lanjutan', value: securityStudentSummary?.students_with_repeat_violation },
                      { label: 'Dalam Penanganan', value: securityStudentSummary?.students_with_open_cases },
                      { label: 'Selesai', value: securityStudentSummary?.students_done },
                      { label: 'Scope', value: STUDENT_SECURITY_SCOPES.find((option) => option.value === securityStudentScope)?.label || 'Semua' },
                    ].map((item) => (
                      <Paper key={item.label} variant="outlined" className="rounded-xl p-3">
                        <Typography variant="caption" className="text-slate-500">{item.label}</Typography>
                        <Typography variant="h6" className="font-bold text-slate-900">{item.value ?? 0}</Typography>
                      </Paper>
                    ))}
                  </Box>

                  <Box className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-3">
                    <Select size="small" value={securityStudentScope} onChange={(event) => {
                      setSecurityStudentScope(event.target.value);
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {STUDENT_SECURITY_SCOPES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.issue_key} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, issue_key: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {ISSUE_OPTIONS.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.status} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, status: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {SECURITY_STATUSES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.stage} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, stage: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {SECURITY_STAGES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.attempt_type} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, attempt_type: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {ATTEMPT_TYPES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <TextField size="small" type="date" value={securityFilters.date_from} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, date_from: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <TextField size="small" type="date" value={securityFilters.date_to} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, date_to: event.target.value }));
                      setSecurityStudentPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                  </Box>

                  <Alert severity="info" icon={<ShieldAlert className="w-4 h-4" />}>
                    Default menampilkan siswa yang perlu kasus baru atau kasus lanjutan. Filter issue, status, tahap, jenis presensi, dan tanggal diterapkan presisi ke Security Event serta Fraud Assessment.
                  </Alert>

                  {securityStudentsLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Ringkasan</TableCell>
                            <TableCell>Masuk/Pulang</TableCell>
                            <TableCell>Kasus</TableCell>
                            <TableCell>Issue Utama</TableCell>
                            <TableCell>Aksi</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {securityStudentRows.length === 0 ? (
                            <TableRow><TableCell colSpan={6} align="center">Belum ada siswa dengan indikasi keamanan pada filter ini.</TableCell></TableRow>
                          ) : securityStudentRows.map((row) => {
                            const status = String(row.operational_status || '').toLowerCase();
                            const comparison = row.case_comparison || {};
                            const repeatedIssueCount = asArray(comparison.repeated_issue_keys).length;
                            const newIssueCount = asArray(comparison.new_issue_keys).length;
                            const canCreateCase = ['needs_case', 'needs_reopen'].includes(status);
                            const actionLabel = status === 'needs_reopen' ? 'Buat Kasus Lanjutan' : 'Buat Kasus';

                            return (
                              <TableRow key={row.user_id}>
                                <TableCell>
                                  <Typography variant="body2" className="font-semibold text-slate-900">{row?.student?.name || '-'}</Typography>
                                  <Typography variant="caption" className="text-slate-500">{row?.student?.identifier || '-'}</Typography>
                                  <Box className="mt-1 flex flex-wrap gap-1">
                                    <Chip
                                      size="small"
                                      label={row.operational_status_label || '-'}
                                      color={securityStudentStatusTone(row.operational_status)}
                                      variant="outlined"
                                    />
                                    <Chip size="small" label={row.violation_sequence_label || 'Pelanggaran #1'} variant="outlined" />
                                  </Box>
                                  <Typography variant="caption" className="mt-1 block text-slate-500">
                                    Terakhir {formatServerDateTime(row.latest_raw_activity_at || row.latest_activity_at, 'id-ID') || '-'}
                                  </Typography>
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Chip size="small" label={`Security ${toNumber(row.security_events_count, 0)}`} variant="outlined" />
                                    <Chip size="small" label={`Fraud ${toNumber(row.fraud_assessments_count, 0)}`} variant="outlined" />
                                    <Chip size="small" label={`Warning ${toNumber(row.total_warnings, 0)}`} color="warning" variant="outlined" />
                                    <Chip size="small" label={`Blocked ${toNumber(row.blocked_events, 0)}`} color="error" variant="outlined" />
                                  </Box>
                                  <Typography variant="caption" className="mt-1 block text-slate-600">
                                    {row.operational_status_description || '-'}
                                  </Typography>
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Chip size="small" label={`Masuk ${toNumber(row.masuk_events, 0)}`} variant="outlined" />
                                    <Chip size="small" label={`Pulang ${toNumber(row.pulang_events, 0)}`} variant="outlined" />
                                  </Box>
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Chip size="small" label={`Terbuka ${toNumber(row.open_cases, 0)}`} color={toNumber(row.open_cases, 0) > 0 ? 'warning' : 'default'} variant="outlined" />
                                    <Chip size="small" label={`Selesai ${toNumber(row.resolved_cases, 0)}`} color="success" variant="outlined" />
                                  </Box>
                                  {row?.previous_case ? (
                                    <Typography variant="caption" className="mt-1 block text-slate-600">
                                      Sebelumnya {row.previous_case.case_number || `#${row.previous_case.id}`} - {row.previous_case.status_label || row.previous_case.status || '-'}
                                    </Typography>
                                  ) : null}
                                  {comparison.days_since_previous_case_resolved !== null && comparison.days_since_previous_case_resolved !== undefined ? (
                                    <Typography variant="caption" className="block text-slate-500">
                                      Jarak {comparison.days_since_previous_case_resolved} hari dari kasus sebelumnya
                                    </Typography>
                                  ) : null}
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    {asArray(row.top_issues).length === 0 ? (
                                      <Typography variant="caption" className="text-slate-500">-</Typography>
                                    ) : asArray(row.top_issues).slice(0, 3).map((issue) => (
                                      <Chip key={`${row.user_id}-${issue.key}`} size="small" label={`${issue.label} (${toNumber(issue.total, 0)})`} color="warning" variant="outlined" />
                                    ))}
                                  </Box>
                                  {row?.previous_case ? (
                                    <Box className="mt-1 flex flex-wrap gap-1">
                                      <Chip size="small" label={`Issue berulang ${repeatedIssueCount}`} color={repeatedIssueCount > 0 ? 'error' : 'default'} variant="outlined" />
                                      <Chip size="small" label={`Issue baru ${newIssueCount}`} variant="outlined" />
                                    </Box>
                                  ) : null}
                                  <Typography variant="caption" className="mt-1 block text-slate-600">
                                    {compactText(row.recommendation, 120)}
                                  </Typography>
                                </TableCell>
                                <TableCell>
                                  {canCreateCase ? (
                                    <Button size="small" variant="contained" onClick={() => openCreateCaseDialog(row)}>
                                      {actionLabel}
                                    </Button>
                                  ) : (
                                    <Chip
                                      size="small"
                                      label={status === 'in_progress' ? 'Kasus aktif' : 'Masuk arsip'}
                                      color={status === 'in_progress' ? 'info' : 'success'}
                                      variant="outlined"
                                    />
                                  )}
                                </TableCell>
                              </TableRow>
                            );
                          })}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}

                  <PaginationToolbar
                    meta={securityStudentPagination}
                    loading={securityStudentsLoading}
                    onPageChange={(page) => setSecurityStudentPagination((previous) => ({ ...previous, current_page: page }))}
                    onPerPageChange={(perPage) => setSecurityStudentPagination((previous) => ({ ...previous, per_page: perPage, current_page: 1 }))}
                  />
                </Box>
              ) : null}

              {tab === 2 ? (
                <Box className="space-y-4">
                  <Box className="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <TextField size="small" placeholder="Cari no kasus / siswa / ringkasan" value={securityCaseFilters.search} onChange={(event) => {
                      setSecurityCaseFilters((previous) => ({ ...previous, search: event.target.value }));
                      setSecurityCasePagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <Select size="small" value={securityCaseFilters.case_scope} onChange={(event) => {
                      setSecurityCaseFilters((previous) => ({ ...previous, case_scope: event.target.value, status: '' }));
                      setSecurityCasePagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {CASE_SCOPES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityCaseFilters.status} onChange={(event) => {
                      setSecurityCaseFilters((previous) => ({ ...previous, status: event.target.value, case_scope: event.target.value ? 'all' : previous.case_scope }));
                      setSecurityCasePagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {CASE_STATUSES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityCaseFilters.priority} onChange={(event) => {
                      setSecurityCaseFilters((previous) => ({ ...previous, priority: event.target.value }));
                      setSecurityCasePagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {CASE_PRIORITIES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                  </Box>

                  {securityCasesLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Kasus</TableCell>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Ringkasan</TableCell>
                            <TableCell>Bukti</TableCell>
                            <TableCell>Waktu</TableCell>
                            <TableCell>Aksi</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {securityCaseRows.length === 0 ? (
                            <TableRow><TableCell colSpan={7} align="center">Belum ada kasus tindak lanjut keamanan.</TableCell></TableRow>
                          ) : securityCaseRows.map((row) => {
                            const closed = ['resolved', 'escalated'].includes(String(row.status || '').toLowerCase());

                            return (
                              <TableRow key={row.id}>
                                <TableCell>
                                  <Typography variant="body2" className="font-semibold text-slate-900">{row.case_number || `#${row.id}`}</Typography>
                                  <Typography variant="caption" className="text-slate-500">{formatServerDate(row.case_date, 'id-ID') || '-'}</Typography>
                                </TableCell>
                                <TableCell>
                                  <Typography variant="body2" className="font-semibold text-slate-900">{row?.student?.name || '-'}</Typography>
                                  <Typography variant="caption" className="text-slate-500">{row?.student?.identifier || '-'}</Typography>
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Chip size="small" label={row.status_label || row.status || '-'} color={closed ? 'success' : 'warning'} variant="outlined" />
                                    <Chip size="small" label={row.priority_label || row.priority || '-'} color={row.priority === 'critical' || row.priority === 'high' ? 'error' : 'default'} variant="outlined" />
                                  </Box>
                                </TableCell>
                                <TableCell>
                                  <Typography variant="body2" className="text-slate-800">{compactText(row.summary, 160) || '-'}</Typography>
                                  {row.resolution_label ? (
                                    <Typography variant="caption" className="mt-1 block text-emerald-700">{row.resolution_label}</Typography>
                                  ) : null}
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Chip size="small" label={`Item ${toNumber(row.items_count, 0)}`} variant="outlined" />
                                    <Chip size="small" label={`Bukti ${toNumber(row.evidence_count, 0)}`} variant="outlined" />
                                  </Box>
                                </TableCell>
                                <TableCell>
                                  <Typography variant="caption" className="block text-slate-600">Update {formatServerDateTime(row.updated_at, 'id-ID') || '-'}</Typography>
                                  <Typography variant="caption" className="block text-slate-500">Selesai {formatServerDateTime(row.resolved_at, 'id-ID') || '-'}</Typography>
                                </TableCell>
                                <TableCell>
                                  <Box className="flex flex-wrap gap-1">
                                    <Button size="small" variant="outlined" onClick={() => openEvidenceDialog(row)}>
                                      Tambah Bukti
                                    </Button>
                                    {closed ? (
                                      <Button size="small" variant="outlined" onClick={() => handleReopenCase(row)}>
                                        Buka Ulang
                                      </Button>
                                    ) : (
                                      <Button size="small" variant="contained" color="success" onClick={() => openResolveCaseDialog(row)}>
                                        Selesaikan
                                      </Button>
                                    )}
                                  </Box>
                                </TableCell>
                              </TableRow>
                            );
                          })}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}

                  <PaginationToolbar
                    meta={securityCasePagination}
                    loading={securityCasesLoading}
                    onPageChange={(page) => setSecurityCasePagination((previous) => ({ ...previous, current_page: page }))}
                    onPerPageChange={(perPage) => setSecurityCasePagination((previous) => ({ ...previous, per_page: perPage, current_page: 1 }))}
                  />
                </Box>
              ) : null}

              {tab === 3 ? (
                <Box className="space-y-4">
                  <Box className="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    <Select size="small" value={fraudFilters.source} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, source: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {FRAUD_SOURCES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={fraudFilters.validation_status} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, validation_status: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {VALIDATION_STATUSES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={fraudFilters.attempt_type} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, attempt_type: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      <MenuItem value="">Semua Jenis Absen</MenuItem>
                      <MenuItem value="masuk">Masuk</MenuItem>
                      <MenuItem value="pulang">Pulang</MenuItem>
                    </Select>
                    <Select size="small" value={fraudFilters.flag_key} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, flag_key: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      <MenuItem value="">Semua Flag</MenuItem>
                      {fraudTopFlags.map((flag) => (
                        <MenuItem key={flag.flag_key} value={flag.flag_key}>
                          {flag.label || flag.flag_key}
                        </MenuItem>
                      ))}
                    </Select>
                    <TextField size="small" type="date" value={fraudFilters.date_from} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, date_from: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <TextField size="small" type="date" value={fraudFilters.date_to} onChange={(event) => {
                      setFraudFilters((previous) => ({ ...previous, date_to: event.target.value }));
                      setFraudPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                  </Box>

                  <Box className="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    {[
                      { label: 'Assessment', value: fraudSummary?.total_assessments },
                      { label: 'Warning', value: fraudSummary?.warning_count },
                      { label: 'Pra-cek', value: fraudSummary?.precheck_warning_count },
                      { label: 'Submit', value: fraudSummary?.submit_warning_count },
                      { label: 'Siswa', value: fraudSummary?.unique_students },
                      { label: 'Mode', value: fraudConfig?.warning_only ? 'Warning' : (fraudConfig?.rollout_mode_label || 'Warning') },
                    ].map((item) => (
                      <Paper key={item.label} variant="outlined" className="rounded-xl p-3">
                        <Typography variant="caption" className="text-slate-500">{item.label}</Typography>
                        <Typography variant="h6" className="font-bold text-slate-900">{item.value ?? 0}</Typography>
                      </Paper>
                    ))}
                  </Box>

                  <Alert severity="info" icon={<AlertTriangle className="w-4 h-4" />}>
                    {fraudConfig?.enforcement_label || 'Warning dicatat, presensi tetap diproses. Device binding tetap jadi satu-satunya hard block.'}
                  </Alert>

                  {recentFraudWarnings.length > 0 ? (
                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Warning Terbaru
                      </Typography>
                      <Box className="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
                        {recentFraudWarnings.slice(0, 4).map((row) => (
                          <div key={`recent-fraud-${row.id}`} className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
                            <Box className="flex flex-wrap items-center justify-between gap-2">
                              <div>
                                <Typography variant="body2" className="font-semibold text-amber-950">{row?.student?.name || '-'}</Typography>
                                <Typography variant="caption" className="text-amber-800">{row?.student?.identifier || '-'}</Typography>
                              </div>
                              <Chip size="small" color="warning" variant="outlined" label={row.source_label || row.source || '-'} />
                            </Box>
                            <Typography variant="caption" className="mt-2 block text-amber-900">
                              {row.warning_summary || row.decision_reason || '-'}
                            </Typography>
                            <Typography variant="caption" className="mt-1 block text-amber-800">
                              {formatServerDateTime(row.last_seen_at || row.created_at, 'id-ID') || '-'}
                            </Typography>
                          </div>
                        ))}
                      </Box>
                    </Paper>
                  ) : null}

                  {fraudLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Tahap</TableCell>
                            <TableCell>Jenis</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Indikasi</TableCell>
                            <TableCell>Ringkasan</TableCell>
                            <TableCell>Tindak Lanjut</TableCell>
                            <TableCell>Waktu</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {fraudRows.length === 0 ? (
                            <TableRow><TableCell colSpan={8} align="center">Belum ada assessment fraud pada filter ini.</TableCell></TableRow>
                          ) : fraudRows.map((row) => (
                            <TableRow key={row.id}>
                              <TableCell>
                                <Typography variant="body2" className="font-semibold text-slate-900">{row?.student?.name || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row?.student?.identifier || '-'}</Typography>
                              </TableCell>
                              <TableCell>{row.source_label || row.source || '-'}</TableCell>
                              <TableCell>{row.attempt_type || '-'}</TableCell>
                              <TableCell><Chip size="small" label={row.validation_status_label || row.validation_status || '-'} color={statusChipTone(row.validation_status)} variant="outlined" /></TableCell>
                              <TableCell>
                                {renderFlagChips(row)}
                                <Typography variant="caption" className="mt-1 block text-slate-500">
                                  Total flag {toNumber(row.fraud_flags_count, asArray(row?.flags).length)}
                                </Typography>
                              </TableCell>
                              <TableCell>
                                <Typography variant="body2" className="text-slate-800">
                                  {row.warning_summary || row.decision_reason || '-'}
                                </Typography>
                                {renderNoticeBoxes(row.notice_boxes, `fraud-notice-${row.id}`)}
                              </TableCell>
                              <TableCell>{row.recommended_action || '-'}</TableCell>
                              <TableCell>{formatServerDateTime(row.last_seen_at || row.created_at, 'id-ID') || '-'}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}

                  {fraudTopFlags.length > 0 ? (
                    <Box className="flex flex-wrap gap-2">
                      {fraudTopFlags.slice(0, 8).map((flag) => (
                        <Chip key={flag.flag_key} size="small" label={`${flag.label || flag.flag_key} (${toNumber(flag.total, 0)})`} variant="outlined" />
                      ))}
                    </Box>
                  ) : null}

                  {toNumber(securitySummary?.total_events, 0) > 0 ? (
                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Box className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                          <Typography variant="subtitle2" className="font-semibold text-slate-900">
                            Indikasi Keamanan Terkait
                          </Typography>
                          <Typography variant="body2" className="text-slate-600">
                            {toNumber(securitySummary?.total_events, 0)} event keamanan tercatat untuk kelas ini. Data ini tetap ditampilkan di sini agar indikasi tidak hilang dari Fraud Monitoring.
                          </Typography>
                        </div>
                        <Button size="small" variant="outlined" onClick={() => setTab(4)}>
                          Buka Security Events
                        </Button>
                      </Box>
                      {securityRows.length > 0 ? (
                        <TableContainer className="mt-3">
                          <Table size="small">
                            <TableHead>
                              <TableRow>
                                <TableCell>Siswa</TableCell>
                                <TableCell>Tahap</TableCell>
                                <TableCell>Issue</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell>Waktu</TableCell>
                              </TableRow>
                            </TableHead>
                            <TableBody>
                              {securityRows.slice(0, 5).map((row) => (
                                <TableRow key={`fraud-security-${row.id}`}>
                                  <TableCell>
                                    <Typography variant="body2" className="font-semibold text-slate-900">{row?.student?.name || '-'}</Typography>
                                    <Typography variant="caption" className="text-slate-500">{row?.student?.identifier || '-'}</Typography>
                                  </TableCell>
                                  <TableCell>{row.stage_label || row.stage || '-'}</TableCell>
                                  <TableCell>
                                    {renderIssueChips(row)}
                                    <Typography variant="caption" className="mt-1 block text-slate-500">{row.message || '-'}</Typography>
                                  </TableCell>
                                  <TableCell><Chip size="small" label={row.status_label || row.status || '-'} color={statusChipTone(row.status)} variant="outlined" /></TableCell>
                                  <TableCell>{formatServerDateTime(row.last_seen_at || row.created_at, 'id-ID') || '-'}</TableCell>
                                </TableRow>
                              ))}
                            </TableBody>
                          </Table>
                        </TableContainer>
                      ) : null}
                    </Paper>
                  ) : null}

                  <PaginationToolbar
                    meta={fraudPagination}
                    loading={fraudLoading}
                    onPageChange={(page) => setFraudPagination((previous) => ({ ...previous, current_page: page }))}
                    onPerPageChange={(perPage) => setFraudPagination((previous) => ({ ...previous, per_page: perPage, current_page: 1 }))}
                  />
                </Box>
              ) : null}

              {tab === 4 ? (
                <Box className="space-y-4">
                  <Box className="grid grid-cols-2 lg:grid-cols-6 gap-3">
                    {[
                      { label: 'Event', value: securitySummary?.total_events },
                      { label: 'Warning', value: securitySummary?.flagged_events },
                      { label: 'Blocked', value: securitySummary?.blocked_events },
                      { label: 'Pra-cek', value: securitySummary?.precheck_events },
                      { label: 'Submit', value: securitySummary?.submit_events },
                      { label: 'Siswa', value: securitySummary?.unique_students },
                    ].map((item) => (
                      <Paper key={item.label} variant="outlined" className="rounded-xl p-3">
                        <Typography variant="caption" className="text-slate-500">{item.label}</Typography>
                        <Typography variant="h6" className="font-bold text-slate-900">{toNumber(item.value, 0)}</Typography>
                      </Paper>
                    ))}
                  </Box>

                  <Alert severity="info" icon={<ShieldAlert className="w-4 h-4" />}>
                    {securityConfig?.enforcement_label || 'Security monitoring menampilkan indikasi untuk evaluasi. Warning dicatat, presensi tetap diproses.'}
                  </Alert>

                  <Box className="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <Select size="small" value={securityFilters.issue_key} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, issue_key: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {ISSUE_OPTIONS.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.status} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, status: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {SECURITY_STATUSES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.stage} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, stage: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {SECURITY_STAGES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <Select size="small" value={securityFilters.attempt_type} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, attempt_type: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {ATTEMPT_TYPES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <TextField size="small" type="date" value={securityFilters.date_from} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, date_from: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <TextField size="small" type="date" value={securityFilters.date_to} onChange={(event) => {
                      setSecurityFilters((previous) => ({ ...previous, date_to: event.target.value }));
                      setSecurityPagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                  </Box>

                  <Box className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Tahap Kejadian
                      </Typography>
                      {securityStageBreakdown.length === 0 ? (
                        <Typography variant="body2" className="mt-2 text-slate-500">Belum ada breakdown tahap.</Typography>
                      ) : (
                        <Box className="mt-3 flex flex-wrap gap-2">
                          {securityStageBreakdown.map((item) => (
                            <Chip key={item.stage} size="small" variant="outlined" label={`${item.stage_label || item.stage} (${toNumber(item.total, 0)})`} />
                          ))}
                        </Box>
                      )}
                    </Paper>

                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Issue Paling Sering
                      </Typography>
                      {securityEventBreakdown.length === 0 ? (
                        <Typography variant="body2" className="mt-2 text-slate-500">Belum ada issue dominan.</Typography>
                      ) : (
                        <Box className="mt-3 flex flex-wrap gap-2">
                          {securityEventBreakdown.slice(0, 8).map((item) => (
                            <Chip key={item.event_key} size="small" color="warning" variant="outlined" label={`${item.event_label || item.event_key} (${toNumber(item.total, 0)})`} />
                          ))}
                        </Box>
                      )}
                    </Paper>
                  </Box>

                  {securityLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Tahap</TableCell>
                            <TableCell>Jenis</TableCell>
                            <TableCell>Event</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Issue</TableCell>
                            <TableCell>Waktu</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {securityRows.length === 0 ? (
                            <TableRow><TableCell colSpan={7} align="center">Belum ada security event.</TableCell></TableRow>
                          ) : securityRows.map((row) => (
                            <TableRow key={row.id}>
                              <TableCell>
                                <Typography variant="body2" className="font-semibold text-slate-900">{row?.student?.name || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row?.student?.identifier || '-'}</Typography>
                              </TableCell>
                              <TableCell>{row.stage_label || row.stage || '-'}</TableCell>
                              <TableCell>{row.attempt_type || '-'}</TableCell>
                              <TableCell>
                                <Typography variant="body2">{row.event_label || row.event_key || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row.message || '-'}</Typography>
                                {renderNoticeBoxes(row.notice_box ? [row.notice_box] : [], `security-notice-${row.id}`)}
                              </TableCell>
                              <TableCell><Chip size="small" label={row.status_label || row.status || '-'} color={statusChipTone(row.status)} variant="outlined" /></TableCell>
                              <TableCell>
                                {renderIssueChips(row)}
                                <Typography variant="caption" className="mt-1 block text-slate-500">
                                  Tercatat {toNumber(row.occurrence_count, 1)}x
                                </Typography>
                              </TableCell>
                              <TableCell>{formatServerDateTime(row.last_seen_at || row.created_at, 'id-ID') || '-'}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}

                  {securityFollowUpStudents.length > 0 ? (
                    <Paper variant="outlined" className="rounded-2xl p-4">
                      <Typography variant="subtitle2" className="font-semibold text-slate-900">
                        Siswa Prioritas Klarifikasi
                      </Typography>
                      <Box className="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
                        {securityFollowUpStudents.slice(0, 8).map((student) => (
                          <div key={`${student.user_id}-${student.last_event_at || 'latest'}`} className="rounded-xl border border-slate-200 px-3 py-2">
                            <Box className="flex flex-wrap items-start justify-between gap-2">
                              <div>
                                <Typography variant="body2" className="font-semibold text-slate-900">{student.student_name || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{student.student_identifier || '-'}</Typography>
                              </div>
                              <Chip size="small" color="warning" variant="outlined" label={`Total ${toNumber(student.total_events, 0)}`} />
                            </Box>
                            <Box className="mt-2 flex flex-wrap gap-1">
                              <Chip size="small" label={`Fake GPS ${toNumber(student.mock_location_events, 0)}`} color="warning" variant="outlined" />
                              <Chip size="small" label={`Device ${toNumber(student.device_events, 0)}`} color="warning" variant="outlined" />
                              <Chip size="small" label={`Blocked ${toNumber(student.blocked_events, 0)}`} color="error" variant="outlined" />
                            </Box>
                            <Typography variant="caption" className="mt-2 block text-slate-600">
                              {student.recommendation || '-'}
                            </Typography>
                          </div>
                        ))}
                      </Box>
                    </Paper>
                  ) : null}

                  <PaginationToolbar
                    meta={securityPagination}
                    loading={securityLoading}
                    onPageChange={(page) => setSecurityPagination((previous) => ({ ...previous, current_page: page }))}
                    onPerPageChange={(perPage) => setSecurityPagination((previous) => ({ ...previous, per_page: perPage, current_page: 1 }))}
                  />
                </Box>
              ) : null}

              {tab === 5 ? (
                <Box className="space-y-4">
                  <Box className="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <TextField size="small" placeholder="Cari nama siswa / alasan" value={leaveFilters.search} onChange={(event) => {
                      setLeaveFilters((previous) => ({ ...previous, search: event.target.value }));
                      setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <Select size="small" value={leaveFilters.status} onChange={(event) => {
                      setLeaveFilters((previous) => ({ ...previous, status: event.target.value }));
                      setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
                    }}>
                      {LEAVE_STATUSES.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)}
                    </Select>
                    <TextField size="small" placeholder="Jenis izin" value={leaveFilters.jenis_izin} onChange={(event) => {
                      setLeaveFilters((previous) => ({ ...previous, jenis_izin: event.target.value }));
                      setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <TextField size="small" type="date" value={leaveFilters.date_from} onChange={(event) => {
                      setLeaveFilters((previous) => ({ ...previous, date_from: event.target.value }));
                      setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                    <TextField size="small" type="date" value={leaveFilters.date_to} onChange={(event) => {
                      setLeaveFilters((previous) => ({ ...previous, date_to: event.target.value }));
                      setLeavePagination((previous) => ({ ...previous, current_page: 1 }));
                    }} />
                  </Box>

                  {leaveLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Jenis</TableCell>
                            <TableCell>Periode</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Alasan</TableCell>
                            <TableCell>Dibuat</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {leaveRows.length === 0 ? (
                            <TableRow><TableCell colSpan={6} align="center">Belum ada data izin.</TableCell></TableRow>
                          ) : leaveRows.map((row) => (
                            <TableRow key={row.id || `${row.user_id}-${row.created_at}`}>
                              <TableCell>
                                <Typography variant="body2" className="font-semibold text-slate-900">{row?.user?.nama_lengkap || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row?.user?.nisn || '-'}</Typography>
                              </TableCell>
                              <TableCell>{row.jenis_izin_label || row.jenis_izin || '-'}</TableCell>
                              <TableCell>
                                {formatServerDate(row.tanggal_mulai || row.start_date, 'id-ID') || '-'}
                                {' '}s.d{' '}
                                {formatServerDate(row.tanggal_selesai || row.end_date, 'id-ID') || '-'}
                              </TableCell>
                              <TableCell><Chip size="small" label={row.status || '-'} color={statusChipTone(row.status)} variant="outlined" /></TableCell>
                              <TableCell>{row.alasan || row.keterangan || '-'}</TableCell>
                              <TableCell>{formatServerDateTime(row.created_at, 'id-ID') || '-'}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}

                  <PaginationToolbar
                    meta={leavePagination}
                    loading={leaveLoading}
                    onPageChange={(page) => setLeavePagination((previous) => ({ ...previous, current_page: page }))}
                    onPerPageChange={(perPage) => setLeavePagination((previous) => ({ ...previous, per_page: perPage, current_page: 1 }))}
                  />
                </Box>
              ) : null}

              {tab === 6 ? (
                <Box className="space-y-4">
                  <Box className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <TextField
                      size="small"
                      type="date"
                      value={attendanceDate}
                      onChange={(event) => setAttendanceDate(event.target.value)}
                      InputProps={{
                        startAdornment: <CalendarDays className="w-4 h-4 mr-2 text-slate-400" />,
                      }}
                    />
                    <TextField
                      size="small"
                      type="month"
                      value={statisticsMonth}
                      onChange={(event) => setStatisticsMonth(event.target.value)}
                    />
                  </Box>

                  <Box className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    {[
                      { label: 'Hadir', value: attendanceSummary.hadir },
                      { label: 'Terlambat', value: attendanceSummary.terlambat },
                      { label: 'Izin', value: attendanceSummary.izin },
                      { label: 'Sakit', value: attendanceSummary.sakit },
                      { label: 'Alpha', value: attendanceSummary.alpha },
                    ].map((item) => (
                      <Paper key={item.label} variant="outlined" className="rounded-xl p-3 text-center">
                        <Typography variant="caption" className="text-slate-500">{item.label}</Typography>
                        <Typography variant="h6" className="font-bold text-slate-900">{toNumber(item.value, 0)}</Typography>
                      </Paper>
                    ))}
                  </Box>

                  {overviewLoading ? (
                    <Box className="py-8 flex justify-center"><CircularProgress size={24} /></Box>
                  ) : (
                    <TableContainer component={Paper} variant="outlined" className="rounded-2xl">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Siswa</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Validasi</TableCell>
                            <TableCell>Warning</TableCell>
                            <TableCell>Keterangan</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {attendance.length === 0 ? (
                            <TableRow><TableCell colSpan={5} align="center">Belum ada data absensi pada tanggal ini.</TableCell></TableRow>
                          ) : attendance.map((row) => (
                            <TableRow key={row.id || `${row.user_id}-${row.status}`}>
                              <TableCell>
                                <Typography variant="body2" className="font-semibold text-slate-900">{row?.user?.nama_lengkap || '-'}</Typography>
                                <Typography variant="caption" className="text-slate-500">{row?.user?.nisn || '-'}</Typography>
                              </TableCell>
                              <TableCell><Chip size="small" label={row.status || '-'} color={statusChipTone(row.status)} variant="outlined" /></TableCell>
                              <TableCell><Chip size="small" label={row.validation_status || 'valid'} color={statusChipTone(row.validation_status)} variant="outlined" /></TableCell>
                              <TableCell>{row.warning_summary || '-'}</TableCell>
                              <TableCell>{row.keterangan || '-'}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}
                </Box>
              ) : null}
            </Box>
          )}
        </Paper>
      </div>

      <Dialog open={caseDialog.open} onClose={closeCreateCaseDialog} maxWidth="sm" fullWidth>
        <DialogTitle>Buat Kasus Tindak Lanjut</DialogTitle>
        <DialogContent>
          <Box className="space-y-3 pt-2">
            <Alert severity="info">
              Bukti sistem terbaru dari siswa ini akan disnapshot otomatis saat kasus dibuat.
            </Alert>
            <Typography variant="body2" className="text-slate-700">
              Siswa: <strong>{caseDialog?.studentRow?.student?.name || '-'}</strong>
            </Typography>
            {caseDialog?.studentRow?.previous_case ? (
              <Alert severity="warning">
                {caseDialog.studentRow.violation_sequence_label || 'Pelanggaran lanjutan'} dibandingkan dengan kasus {caseDialog.studentRow.previous_case.case_number || `#${caseDialog.studentRow.previous_case.id}`}.
                {' '}Issue berulang: {asArray(caseDialog.studentRow?.case_comparison?.repeated_issue_keys).length}.
              </Alert>
            ) : null}
            <Select fullWidth size="small" value={caseForm.priority} onChange={(event) => setCaseForm((previous) => ({ ...previous, priority: event.target.value }))}>
              {CASE_PRIORITIES.filter((option) => option.value !== '').map((option) => (
                <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
              ))}
            </Select>
            <TextField
              fullWidth
              size="small"
              label="Ringkasan kasus"
              value={caseForm.summary}
              onChange={(event) => setCaseForm((previous) => ({ ...previous, summary: event.target.value }))}
            />
            <TextField
              fullWidth
              multiline
              minRows={4}
              label="Catatan awal / arahan tindak lanjut"
              value={caseForm.staff_notes}
              onChange={(event) => setCaseForm((previous) => ({ ...previous, staff_notes: event.target.value }))}
            />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeCreateCaseDialog} disabled={caseSubmitting}>Batal</Button>
          <Button variant="contained" onClick={handleCreateCase} disabled={caseSubmitting}>
            {caseSubmitting ? 'Menyimpan...' : 'Buat Kasus'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={evidenceDialog.open} onClose={closeEvidenceDialog} maxWidth="sm" fullWidth>
        <DialogTitle>Tambah Bukti Kasus</DialogTitle>
        <DialogContent>
          <Box className="space-y-3 pt-2">
            <Typography variant="body2" className="text-slate-700">
              Kasus: <strong>{evidenceDialog?.caseRow?.case_number || '-'}</strong>
            </Typography>
            <Select fullWidth size="small" value={evidenceForm.evidence_type} onChange={(event) => setEvidenceForm((previous) => ({ ...previous, evidence_type: event.target.value }))}>
              <MenuItem value="screenshot">Screenshot</MenuItem>
              <MenuItem value="student_statement">Pernyataan siswa</MenuItem>
              <MenuItem value="parent_confirmation">Konfirmasi orang tua</MenuItem>
              <MenuItem value="device_check">Pemeriksaan perangkat</MenuItem>
              <MenuItem value="other">Lainnya</MenuItem>
            </Select>
            <TextField
              fullWidth
              size="small"
              label="Judul bukti"
              value={evidenceForm.title}
              onChange={(event) => setEvidenceForm((previous) => ({ ...previous, title: event.target.value }))}
            />
            <TextField
              fullWidth
              multiline
              minRows={3}
              label="Keterangan bukti"
              value={evidenceForm.description}
              onChange={(event) => setEvidenceForm((previous) => ({ ...previous, description: event.target.value }))}
            />
            <Button variant="outlined" component="label">
              {evidenceForm.file ? evidenceForm.file.name : 'Pilih File Bukti'}
              <input
                type="file"
                hidden
                onChange={(event) => setEvidenceForm((previous) => ({
                  ...previous,
                  file: event.target.files?.[0] || null,
                }))}
              />
            </Button>
            <Typography variant="caption" className="block text-slate-500">
              File opsional. Jika tidak ada file, catatan bukti tetap disimpan.
            </Typography>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeEvidenceDialog} disabled={evidenceSubmitting}>Batal</Button>
          <Button variant="contained" onClick={handleUploadEvidence} disabled={evidenceSubmitting}>
            {evidenceSubmitting ? 'Menyimpan...' : 'Simpan Bukti'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={resolveDialog.open} onClose={closeResolveCaseDialog} maxWidth="sm" fullWidth>
        <DialogTitle>Selesaikan Kasus</DialogTitle>
        <DialogContent>
          <Box className="space-y-3 pt-2">
            <Typography variant="body2" className="text-slate-700">
              Kasus: <strong>{resolveDialog?.caseRow?.case_number || '-'}</strong>
            </Typography>
            <Select fullWidth size="small" value={resolveForm.resolution} onChange={(event) => setResolveForm((previous) => ({ ...previous, resolution: event.target.value }))}>
              {CASE_RESOLUTIONS.map((option) => (
                <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
              ))}
            </Select>
            <TextField
              fullWidth
              multiline
              minRows={4}
              label="Catatan hasil klarifikasi"
              value={resolveForm.staff_notes}
              onChange={(event) => setResolveForm((previous) => ({ ...previous, staff_notes: event.target.value }))}
            />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeResolveCaseDialog} disabled={resolveSubmitting}>Batal</Button>
          <Button variant="contained" color="success" onClick={handleResolveCase} disabled={resolveSubmitting}>
            {resolveSubmitting ? 'Menyimpan...' : 'Tandai Selesai'}
          </Button>
        </DialogActions>
      </Dialog>

    </div>
  );
};

export default MonitoringKelas;

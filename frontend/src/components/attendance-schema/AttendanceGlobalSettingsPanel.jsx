import React, { useEffect, useState } from 'react';
import { AlertTriangle, Camera, CheckCircle2, Clock3, Database, Download, Loader2, MapPin, RefreshCw, Save, ShieldAlert, ShieldCheck, Smartphone } from 'lucide-react';
import { toast } from 'react-hot-toast';
import api, { simpleAttendanceAPI } from '../../services/api';
import { formatServerDateTime } from '../../services/serverClock';
import DisciplineOverrideDialog from './DisciplineOverrideDialog';

const normalizeIdArray = (value) => {
  if (!value) return [];

  if (Array.isArray(value)) {
    return value
      .map((item) => Number(item))
      .filter((item) => Number.isInteger(item) && item > 0);
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      return normalizeIdArray(parsed);
    } catch (_) {
      return [];
    }
  }

  return [];
};

const mapGlobalSettings = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};
  const verificationMode = payload.verification_mode || 'async_pending';
  const templateMissingResult = verificationMode === 'sync_final' && payload.face_result_when_template_missing === 'manual_review'
    ? 'rejected'
    : (payload.face_result_when_template_missing || 'verified');
  const rejectToManualReview = verificationMode === 'sync_final'
    ? false
    : Boolean(payload.face_reject_to_manual_review ?? true);

  return {
    verification_mode: verificationMode,
    // Scope dikunci untuk siswa sesuai kebijakan sistem.
    attendance_scope: 'siswa_only',
    discipline_thresholds_enabled: Boolean(payload.discipline_thresholds_enabled ?? true),
    total_violation_minutes_semester_limit: Number(payload.total_violation_minutes_semester_limit ?? 1200),
    semester_total_violation_mode: payload.semester_total_violation_mode || 'monitor_only',
    notify_wali_kelas_on_total_violation_limit: Boolean(payload.notify_wali_kelas_on_total_violation_limit ?? false),
    notify_kesiswaan_on_total_violation_limit: Boolean(payload.notify_kesiswaan_on_total_violation_limit ?? false),
    alpha_days_semester_limit: Number(payload.alpha_days_semester_limit ?? 8),
    semester_alpha_mode: payload.semester_alpha_mode || 'alertable',
    late_minutes_monthly_limit: Number(payload.late_minutes_monthly_limit ?? 120),
    monthly_late_mode: payload.monthly_late_mode || 'monitor_only',
    notify_wali_kelas_on_late_limit: Boolean(payload.notify_wali_kelas_on_late_limit ?? false),
    notify_kesiswaan_on_late_limit: Boolean(payload.notify_kesiswaan_on_late_limit ?? false),
    notify_wali_kelas_on_alpha_limit: Boolean(payload.notify_wali_kelas_on_alpha_limit ?? true),
    notify_kesiswaan_on_alpha_limit: Boolean(payload.notify_kesiswaan_on_alpha_limit ?? true),
    auto_alpha_enabled: Boolean(payload.auto_alpha_enabled ?? true),
    auto_alpha_run_time: payload.auto_alpha_run_time || '23:50',
    discipline_alerts_enabled: Boolean(payload.discipline_alerts_enabled ?? true),
    discipline_alerts_run_time: payload.discipline_alerts_run_time || '23:57',
    live_tracking_enabled: Boolean(payload.live_tracking_enabled ?? true),
    live_tracking_retention_days: Number(payload.live_tracking_retention_days ?? 30),
    live_tracking_cleanup_time: payload.live_tracking_cleanup_time || '02:15',
    live_tracking_min_distance_meters: Number(payload.live_tracking_min_distance_meters ?? 20),
    face_verification_enabled: Boolean(payload.face_verification_enabled ?? true),
    face_template_required: Boolean(payload.face_template_required ?? true),
    face_result_when_template_missing: templateMissingResult,
    face_reject_to_manual_review: rejectToManualReview,
    face_skip_when_photo_missing: Boolean(payload.face_skip_when_photo_missing ?? true),
  };
};

const mapArrayFromResponse = (response) => {
  const rows = response?.data?.data ?? response?.data ?? [];
  return Array.isArray(rows) ? rows : [];
};

const asArray = (value) => (Array.isArray(value) ? value : []);

const formatHealthRunAt = (value) => {
  const formatted = formatServerDateTime(value, 'id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  return formatted || '-';
};

const mapSystemHealth = (response) => {
  const payload = response?.data?.data ?? response?.data;
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  return {
    overallStatus: payload.overall_status || 'unknown',
    checks: payload.checks || {},
    summary: payload.summary || {},
  };
};

const mapGovernanceLogs = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};
  const rows = Array.isArray(payload?.data) ? payload.data : [];

  return {
    items: rows,
    pagination: {
      currentPage: Number(payload?.current_page || 1),
      lastPage: Number(payload?.last_page || 1),
      total: Number(payload?.total || rows.length || 0),
    },
  };
};

const mapFraudSummary = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};

  return {
    config: payload?.config || {},
    overview: payload?.overview || {},
    topFlags: Array.isArray(payload?.top_flags) ? payload.top_flags : [],
    recentWarningAssessments: Array.isArray(payload?.recent_warning_assessments)
      ? payload.recent_warning_assessments
      : [],
    recentBlockingAttempts: Array.isArray(payload?.recent_blocking_attempts)
      ? payload.recent_blocking_attempts
      : [],
  };
};

const mapFraudAssessments = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};
  const rows = Array.isArray(payload?.data) ? payload.data : [];

  return {
    items: rows,
    pagination: {
      currentPage: Number(payload?.current_page || 1),
      lastPage: Number(payload?.last_page || 1),
      total: Number(payload?.total || rows.length || 0),
    },
  };
};

const mapSecuritySummary = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};

  return {
    overview: payload || {},
    eventBreakdown: Array.isArray(payload?.event_breakdown) ? payload.event_breakdown : [],
    stageBreakdown: Array.isArray(payload?.stage_breakdown) ? payload.stage_breakdown : [],
    followUpStudents: Array.isArray(payload?.follow_up_students) ? payload.follow_up_students : [],
  };
};

const mapSecurityEvents = (responseData) => {
  const payload = responseData?.data ?? responseData ?? {};
  const rows = Array.isArray(payload?.data) ? payload.data : [];

  return {
    items: rows,
    pagination: {
      currentPage: Number(payload?.current_page || 1),
      lastPage: Number(payload?.last_page || 1),
      total: Number(payload?.total || rows.length || 0),
    },
  };
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

const verificationModes = [
  {
    value: 'async_pending',
    title: 'Async Pending (Direkomendasikan)',
    description: 'Cocok untuk jam masuk massal. Sistem tetap responsif saat trafik tinggi.',
  },
  {
    value: 'sync_final',
    title: 'Sync Final',
    description: 'Hasil sukses/gagal langsung, tetapi lebih berat untuk trafik serentak.',
  },
];

const thresholdModeOptions = [
  { value: 'monitor_only', label: 'Monitoring saja' },
  { value: 'alertable', label: 'Trigger alert otomatis' },
];

const securityIssueFilterOptions = [
  { value: 'mock_location_detected', label: 'Mock location / Fake GPS' },
  { value: 'outside_geofence', label: 'Di luar geofence' },
  { value: 'gps_accuracy_low', label: 'Akurasi GPS rendah' },
  { value: 'mobile_app_only_violation', label: 'Absensi dari web/browser' },
  { value: 'device_lock_violation', label: 'Perangkat tidak sesuai binding' },
  { value: 'device_id_missing_on_locked_account', label: 'Device ID tidak terkirim' },
  { value: 'developer_options_enabled', label: 'Developer options aktif' },
  { value: 'root_or_jailbreak_detected', label: 'Root / jailbreak' },
  { value: 'adb_or_usb_debugging_enabled', label: 'ADB / USB debugging aktif' },
  { value: 'emulator_detected', label: 'Emulator terdeteksi' },
  { value: 'app_clone_detected', label: 'Clone / dual app' },
  { value: 'app_tampering_detected', label: 'Integritas aplikasi bermasalah' },
  { value: 'instrumentation_detected', label: 'Instrumentation / hooking' },
  { value: 'signature_mismatch_detected', label: 'Signature aplikasi tidak sesuai' },
  { value: 'magisk_risk_detected', label: 'Risiko Magisk' },
  { value: 'suspicious_device_state_detected', label: 'Status perangkat mencurigakan' },
];

const AttendanceGlobalSettingsPanel = ({ className = '' }) => {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [formData, setFormData] = useState({
    verification_mode: 'async_pending',
    attendance_scope: 'siswa_only',
    discipline_thresholds_enabled: true,
    total_violation_minutes_semester_limit: 1200,
    semester_total_violation_mode: 'monitor_only',
    notify_wali_kelas_on_total_violation_limit: false,
    notify_kesiswaan_on_total_violation_limit: false,
    alpha_days_semester_limit: 8,
    semester_alpha_mode: 'alertable',
    late_minutes_monthly_limit: 120,
    monthly_late_mode: 'monitor_only',
    notify_wali_kelas_on_late_limit: false,
    notify_kesiswaan_on_late_limit: false,
    notify_wali_kelas_on_alpha_limit: true,
    notify_kesiswaan_on_alpha_limit: true,
    auto_alpha_enabled: true,
    auto_alpha_run_time: '23:50',
    discipline_alerts_enabled: true,
    discipline_alerts_run_time: '23:57',
    live_tracking_enabled: true,
    live_tracking_retention_days: 30,
    live_tracking_cleanup_time: '02:15',
    live_tracking_min_distance_meters: 20,
    face_verification_enabled: true,
    face_template_required: true,
    face_result_when_template_missing: 'verified',
    face_reject_to_manual_review: true,
    face_skip_when_photo_missing: true,
  });

  const [locationHealth, setLocationHealth] = useState({
    activeLocationsCount: 0,
    totalSchemasCount: 0,
    gpsSchemasCount: 0,
    gpsSchemasWithBindingsCount: 0,
    gpsSchemasWithoutBindingsCount: 0,
    hasHardWarning: false,
  });
  const [systemHealth, setSystemHealth] = useState(null);
  const [disciplineOverrideSummary, setDisciplineOverrideSummary] = useState({ total: 0, active: 0 });
  const [disciplineOverrideDialogOpen, setDisciplineOverrideDialogOpen] = useState(false);
  const [governanceLogs, setGovernanceLogs] = useState([]);
  const [governanceLoading, setGovernanceLoading] = useState(false);
  const [governancePagination, setGovernancePagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });
  const [fraudSummary, setFraudSummary] = useState({
    config: {},
    overview: {},
    topFlags: [],
    recentWarningAssessments: [],
    recentBlockingAttempts: [],
  });
  const [fraudAssessments, setFraudAssessments] = useState([]);
  const [fraudLoading, setFraudLoading] = useState(false);
  const [fraudPagination, setFraudPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });
  const [fraudFilters, setFraudFilters] = useState({
    source: '',
    validation_status: '',
  });
  const [securitySummary, setSecuritySummary] = useState({
    overview: {},
    eventBreakdown: [],
    stageBreakdown: [],
    followUpStudents: [],
  });
  const [securityEvents, setSecurityEvents] = useState([]);
  const [securityLoading, setSecurityLoading] = useState(false);
  const [securityPagination, setSecurityPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });
  const [securityFilters, setSecurityFilters] = useState({
    issue_key: '',
    status: '',
    severity: '',
    stage: '',
  });

  useEffect(() => {
    const loadGovernanceLogs = async (page = 1) => {
      setGovernanceLoading(true);
      try {
        const response = await simpleAttendanceAPI.getGovernanceLogs({
          per_page: 8,
          page,
        });
        const mapped = mapGovernanceLogs(response.data);
        setGovernanceLogs(mapped.items);
        setGovernancePagination(mapped.pagination);
      } catch (error) {
        console.warn('Failed loading attendance governance logs:', error?.message || error);
        setGovernanceLogs([]);
        setGovernancePagination({
          currentPage: 1,
          lastPage: 1,
          total: 0,
        });
      } finally {
        setGovernanceLoading(false);
      }
    };

    const loadInitialData = async () => {
      setLoading(true);

      try {
        const [
          globalSettingsResponse,
          activeLocationsResponse,
          schemasResponse,
          systemHealthResponse,
          disciplineOverrideResponse,
          fraudSummaryResponse,
          fraudAssessmentsResponse,
          securitySummaryResponse,
          securityEventsResponse,
        ] = await Promise.all([
          simpleAttendanceAPI.getGlobalSettings(),
          api.get('/lokasi-gps/active').catch(() => null),
          api.get('/attendance-schemas').catch(() => null),
          simpleAttendanceAPI.getSystemHealth().catch(() => null),
          simpleAttendanceAPI.getDisciplineOverrides({ include_inactive: true }).catch(() => null),
          simpleAttendanceAPI.getFraudAssessmentSummary().catch(() => null),
          simpleAttendanceAPI.getFraudAssessments({ per_page: 8 }).catch(() => null),
          simpleAttendanceAPI.getSecurityEventSummary().catch(() => null),
          simpleAttendanceAPI.getSecurityEvents({ per_page: 8 }).catch(() => null),
        ]);

        setFormData(mapGlobalSettings(globalSettingsResponse.data));

        const activeLocations = mapArrayFromResponse(activeLocationsResponse);
        const schemas = mapArrayFromResponse(schemasResponse);
        const gpsSchemas = schemas.filter((schema) => Boolean(schema?.wajib_gps));
        const gpsSchemasWithBindingsCount = gpsSchemas.filter(
          (schema) => normalizeIdArray(schema?.lokasi_gps_ids).length > 0
        ).length;
        const gpsSchemasWithoutBindingsCount = Math.max(
          0,
          gpsSchemas.length - gpsSchemasWithBindingsCount
        );

        setLocationHealth({
          activeLocationsCount: activeLocations.length,
          totalSchemasCount: schemas.length,
          gpsSchemasCount: gpsSchemas.length,
          gpsSchemasWithBindingsCount,
          gpsSchemasWithoutBindingsCount,
          hasHardWarning:
            gpsSchemasWithoutBindingsCount > 0 && activeLocations.length === 0,
        });

        setSystemHealth(mapSystemHealth(systemHealthResponse));
        setDisciplineOverrideSummary({
          total: Number(disciplineOverrideResponse?.data?.meta?.total ?? 0),
          active: Number(disciplineOverrideResponse?.data?.meta?.active ?? 0),
        });
        setFraudSummary(mapFraudSummary(fraudSummaryResponse?.data));
        const mappedFraudAssessments = mapFraudAssessments(fraudAssessmentsResponse?.data);
        setFraudAssessments(mappedFraudAssessments.items);
        setFraudPagination(mappedFraudAssessments.pagination);
        setSecuritySummary(mapSecuritySummary(securitySummaryResponse?.data));
        const mappedSecurityEvents = mapSecurityEvents(securityEventsResponse?.data);
        setSecurityEvents(mappedSecurityEvents.items);
        setSecurityPagination(mappedSecurityEvents.pagination);
        await loadGovernanceLogs(1);
      } catch (error) {
        console.error('Failed loading attendance global settings:', error);
        toast.error('Gagal memuat pengaturan absensi');
      } finally {
        setLoading(false);
      }
    };

    loadInitialData();
  }, []);

  const refreshFraudData = async (page = 1) => {
    setFraudLoading(true);
    try {
      const params = {
        per_page: 8,
        page,
        ...(fraudFilters.source ? { source: fraudFilters.source } : {}),
        ...(fraudFilters.validation_status ? { validation_status: fraudFilters.validation_status } : {}),
      };
      const [summaryResponse, assessmentsResponse] = await Promise.all([
        simpleAttendanceAPI.getFraudAssessmentSummary(params),
        simpleAttendanceAPI.getFraudAssessments(params),
      ]);
      setFraudSummary(mapFraudSummary(summaryResponse?.data));
      const mapped = mapFraudAssessments(assessmentsResponse?.data);
      setFraudAssessments(mapped.items);
      setFraudPagination(mapped.pagination);
    } catch (error) {
      console.warn('Failed refreshing attendance fraud monitoring:', error?.message || error);
      toast.error('Gagal memuat fraud monitoring absensi');
    } finally {
      setFraudLoading(false);
    }
  };

  const refreshSecurityData = async (page = 1) => {
    setSecurityLoading(true);
    try {
      const params = {
        per_page: 8,
        page,
        ...(securityFilters.issue_key ? { issue_key: securityFilters.issue_key } : {}),
        ...(securityFilters.status ? { status: securityFilters.status } : {}),
        ...(securityFilters.severity ? { severity: securityFilters.severity } : {}),
        ...(securityFilters.stage ? { stage: securityFilters.stage } : {}),
      };
      const [summaryResponse, eventsResponse] = await Promise.all([
        simpleAttendanceAPI.getSecurityEventSummary(params),
        simpleAttendanceAPI.getSecurityEvents(params),
      ]);
      setSecuritySummary(mapSecuritySummary(summaryResponse?.data));
      const mapped = mapSecurityEvents(eventsResponse?.data);
      setSecurityEvents(mapped.items);
      setSecurityPagination(mapped.pagination);
    } catch (error) {
      console.warn('Failed refreshing attendance security monitoring:', error?.message || error);
      toast.error('Gagal memuat security monitoring absensi');
    } finally {
      setSecurityLoading(false);
    }
  };

  const handleExportFraudAssessments = async () => {
    try {
      const params = {
        ...(fraudFilters.source ? { source: fraudFilters.source } : {}),
        ...(fraudFilters.validation_status ? { validation_status: fraudFilters.validation_status } : {}),
      };
      const response = await simpleAttendanceAPI.exportFraudAssessments(params);
      downloadBlobResponse(response, 'attendance-fraud-assessments.csv');
      toast.success('Export fraud monitoring berhasil diunduh');
    } catch (error) {
      console.warn('Failed exporting fraud assessments:', error?.message || error);
      toast.error('Gagal export fraud monitoring');
    }
  };

  const handleExportSecurityEvents = async () => {
    try {
      const params = {
        ...(securityFilters.issue_key ? { issue_key: securityFilters.issue_key } : {}),
        ...(securityFilters.status ? { status: securityFilters.status } : {}),
        ...(securityFilters.severity ? { severity: securityFilters.severity } : {}),
        ...(securityFilters.stage ? { stage: securityFilters.stage } : {}),
      };
      const response = await simpleAttendanceAPI.exportSecurityEvents(params);
      downloadBlobResponse(response, 'attendance-security-events.csv');
      toast.success('Export security monitoring berhasil diunduh');
    } catch (error) {
      console.warn('Failed exporting security events:', error?.message || error);
      toast.error('Gagal export security monitoring');
    }
  };

  const refreshGovernanceLogs = async (page = 1) => {
    setGovernanceLoading(true);
    try {
      const response = await simpleAttendanceAPI.getGovernanceLogs({
        per_page: 8,
        page,
      });
      const mapped = mapGovernanceLogs(response.data);
      setGovernanceLogs(mapped.items);
      setGovernancePagination(mapped.pagination);
    } catch (error) {
      console.warn('Failed refreshing attendance governance logs:', error?.message || error);
      toast.error('Gagal memuat audit log governance');
    } finally {
      setGovernanceLoading(false);
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const payload = {
        verification_mode: formData.verification_mode,
        // Scope dikunci untuk siswa sesuai kebijakan sistem.
        attendance_scope: 'siswa_only',
        discipline_thresholds_enabled: Boolean(formData.discipline_thresholds_enabled),
        total_violation_minutes_semester_limit: Number(formData.total_violation_minutes_semester_limit ?? 0),
        semester_total_violation_mode: formData.semester_total_violation_mode,
        notify_wali_kelas_on_total_violation_limit: Boolean(formData.notify_wali_kelas_on_total_violation_limit),
        notify_kesiswaan_on_total_violation_limit: Boolean(formData.notify_kesiswaan_on_total_violation_limit),
        alpha_days_semester_limit: Number(formData.alpha_days_semester_limit ?? 0),
        semester_alpha_mode: formData.semester_alpha_mode,
        late_minutes_monthly_limit: Number(formData.late_minutes_monthly_limit ?? 0),
        monthly_late_mode: formData.monthly_late_mode,
        notify_wali_kelas_on_late_limit: Boolean(formData.notify_wali_kelas_on_late_limit),
        notify_kesiswaan_on_late_limit: Boolean(formData.notify_kesiswaan_on_late_limit),
        notify_wali_kelas_on_alpha_limit: Boolean(formData.notify_wali_kelas_on_alpha_limit),
        notify_kesiswaan_on_alpha_limit: Boolean(formData.notify_kesiswaan_on_alpha_limit),
        auto_alpha_enabled: Boolean(formData.auto_alpha_enabled),
        auto_alpha_run_time: formData.auto_alpha_run_time,
        discipline_alerts_enabled: Boolean(formData.discipline_alerts_enabled),
        discipline_alerts_run_time: formData.discipline_alerts_run_time,
        live_tracking_enabled: Boolean(formData.live_tracking_enabled),
        live_tracking_retention_days: Number(formData.live_tracking_retention_days ?? 30),
        live_tracking_cleanup_time: formData.live_tracking_cleanup_time,
        live_tracking_min_distance_meters: Number(formData.live_tracking_min_distance_meters ?? 20),
        face_verification_enabled: Boolean(formData.face_verification_enabled),
        face_template_required: Boolean(formData.face_template_required),
        face_result_when_template_missing:
          formData.verification_mode === 'sync_final' &&
          formData.face_result_when_template_missing === 'manual_review'
            ? 'rejected'
            : formData.face_result_when_template_missing,
        face_reject_to_manual_review:
          formData.verification_mode === 'sync_final'
            ? false
            : Boolean(formData.face_reject_to_manual_review),
        face_skip_when_photo_missing: Boolean(formData.face_skip_when_photo_missing),
      };

      await simpleAttendanceAPI.updateGlobalSettings(payload);

      const refreshedHealth = await simpleAttendanceAPI.getSystemHealth().catch(() => null);
      setSystemHealth(mapSystemHealth(refreshedHealth));
      await refreshGovernanceLogs(1);
      toast.success('Pengaturan absensi berhasil disimpan');
    } catch (error) {
      console.error('Failed to save attendance global settings:', error);
      toast.error('Gagal menyimpan pengaturan absensi');
    } finally {
      setSaving(false);
    }
  };

  const handleThresholdChange = (field, value, max) => {
    const parsed = Number(value);
    const normalized = Number.isFinite(parsed) ? parsed : 0;
    const bounded = Math.max(0, Math.min(normalized, max));

    setFormData((prev) => ({
      ...prev,
      [field]: bounded,
    }));
  };

  const handleTimeFieldChange = (field, value, fallback) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value || fallback,
    }));
  };

  const handleBoundedIntegerChange = (field, value, max, fallback = 0) => {
    const parsed = Number(value);
    const normalized = Number.isFinite(parsed) ? parsed : fallback;
    const bounded = Math.max(1, Math.min(normalized, max));

    setFormData((prev) => ({
      ...prev,
      [field]: bounded,
    }));
  };

  if (loading) {
    return (
      <div className={`bg-white border border-gray-200 rounded-xl p-6 ${className}`}>
        <div className="flex items-center gap-3 text-gray-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          <span className="text-sm">Memuat pengaturan absensi global...</span>
        </div>
      </div>
    );
  }

  const activeVerificationMode =
    verificationModes.find((item) => item.value === formData.verification_mode) || verificationModes[0];
  const queueHealth = systemHealth?.checks?.face_queue || {};
  const faceServiceHealth = systemHealth?.checks?.face_service || {};
  const locationCheck = systemHealth?.checks?.active_locations || {};
  const schemaCheck = systemHealth?.checks?.default_schema || {};
  const healthSummary = systemHealth?.summary || {};
  const autoAlphaCheck = systemHealth?.checks?.auto_alpha || {};
  const disciplineAlertsCheck = systemHealth?.checks?.discipline_alerts || {};
  const liveTrackingCleanupCheck = systemHealth?.checks?.live_tracking_cleanup || {};
  const queueStatus = queueHealth.status || 'unknown';
  const queueBadgeClass =
    queueStatus === 'healthy'
      ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
      : queueStatus === 'warning'
        ? 'bg-amber-100 text-amber-700 border-amber-200'
        : queueStatus === 'not_required'
          ? 'bg-blue-100 text-blue-700 border-blue-200'
          : 'bg-gray-100 text-gray-700 border-gray-200';
  const faceServiceStatus = faceServiceHealth.status || 'unknown';
  const faceServiceBadgeClass =
    faceServiceStatus === 'healthy'
      ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
      : faceServiceStatus === 'warning'
        ? 'bg-amber-100 text-amber-700 border-amber-200'
        : 'bg-gray-100 text-gray-700 border-gray-200';
  const currentLogPage = governancePagination.currentPage || 1;
  const lastLogPage = governancePagination.lastPage || 1;
  const canPrevLogPage = currentLogPage > 1;
  const canNextLogPage = currentLogPage < lastLogPage;
  const currentFraudPage = fraudPagination.currentPage || 1;
  const lastFraudPage = fraudPagination.lastPage || 1;
  const canPrevFraudPage = currentFraudPage > 1;
  const canNextFraudPage = currentFraudPage < lastFraudPage;
  const currentSecurityPage = securityPagination.currentPage || 1;
  const lastSecurityPage = securityPagination.lastPage || 1;
  const canPrevSecurityPage = currentSecurityPage > 1;
  const canNextSecurityPage = currentSecurityPage < lastSecurityPage;
  const fraudConfig = fraudSummary.config || {};
  const fraudOverview = fraudSummary.overview || {};
  const recentFraudWarnings = fraudSummary.recentWarningAssessments || [];
  const securityOverview = securitySummary.overview || {};
  const securityEventBreakdown = securitySummary.eventBreakdown || [];
  const securityStageBreakdown = securitySummary.stageBreakdown || [];
  const securityFollowUpStudents = securitySummary.followUpStudents || [];
  const faceFallbackOptions = [
    { value: 'verified', label: 'Anggap valid' },
    { value: 'manual_review', label: 'Masuk review manual' },
    { value: 'rejected', label: 'Tolak langsung' },
  ];
  const thresholdCards = [
    {
      key: 'semester_total_violation',
      title: 'A. Total Pelanggaran Semester',
      description: 'Gabungan alpha, keterlambatan, dan TAP dalam menit pada semester aktif.',
      field: 'total_violation_minutes_semester_limit',
      limitLabel: 'Batas Menit Semester',
      unit: 'menit',
      modeField: 'semester_total_violation_mode',
      waliField: 'notify_wali_kelas_on_total_violation_limit',
      kesiswaanField: 'notify_kesiswaan_on_total_violation_limit',
    },
    {
      key: 'semester_alpha',
      title: 'B. Alpha Semester',
      description: 'Jumlah hari alpha pada semester aktif.',
      field: 'alpha_days_semester_limit',
      limitLabel: 'Batas Hari Alpha Semester',
      unit: 'hari',
      modeField: 'semester_alpha_mode',
      waliField: 'notify_wali_kelas_on_alpha_limit',
      kesiswaanField: 'notify_kesiswaan_on_alpha_limit',
    },
    {
      key: 'monthly_late',
      title: 'C. Keterlambatan Bulanan',
      description: 'Akumulasi menit terlambat pada bulan berjalan atau bulan laporan terpilih.',
      field: 'late_minutes_monthly_limit',
      limitLabel: 'Batas Menit Terlambat Bulanan',
      unit: 'menit',
      modeField: 'monthly_late_mode',
      waliField: 'notify_wali_kelas_on_late_limit',
      kesiswaanField: 'notify_kesiswaan_on_late_limit',
    },
  ];
  const automationCards = [
    {
      key: 'auto_alpha',
      title: 'Auto Alpha Siswa',
      description: 'Menandai alpha otomatis untuk siswa yang terikat device tetapi tidak absen di hari kerja.',
      enabledField: 'auto_alpha_enabled',
      timeField: 'auto_alpha_run_time',
      defaultTime: '23:50',
    },
    {
      key: 'discipline_alerts',
      title: 'Evaluasi Alert Pelanggaran',
      description: 'Menjalankan evaluasi threshold disiplin dan membuat alert internal/WA sesuai rule yang alertable.',
      enabledField: 'discipline_alerts_enabled',
      timeField: 'discipline_alerts_run_time',
      defaultTime: '23:57',
    },
  ];
  const automationChecks = [
    ['Auto alpha', autoAlphaCheck],
    ['Alert threshold', disciplineAlertsCheck],
    ['Cleanup live tracking', liveTrackingCleanupCheck],
  ];
  const formatLogText = (value) =>
    String(value || '')
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  const getActorName = (log) =>
    log?.actor?.nama_lengkap || log?.actor?.name || log?.actor?.email || 'System';
  const renderNoticeBox = (box, key) => {
    if (!box) {
      return null;
    }

    const issues = asArray(box?.issues);

    return (
      <div key={key} className="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex rounded-full border border-amber-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-800">
            {box?.stage_label || formatLogText(box?.stage)}
          </span>
          <span className="text-xs font-semibold text-amber-900">{box?.title || 'Warning keamanan'}</span>
        </div>
        <p className="mt-1 text-xs text-amber-800">{box?.message || '-'}</p>
        {issues.length > 0 ? (
          <div className="mt-2 flex flex-wrap gap-2">
            {issues.map((issue) => (
              <span
                key={`${key}-${issue?.event_key || issue?.label}`}
                className="inline-flex rounded-full border border-amber-200 bg-white px-2 py-0.5 text-[11px] text-amber-900"
              >
                {issue?.label || formatLogText(issue?.event_key)}
              </span>
            ))}
          </div>
        ) : null}
      </div>
    );
  };

  return (
    <div className={`space-y-5 ${className}`}>
      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <ShieldCheck className="h-5 w-5 text-blue-600" />
              Pengaturan Global Absensi
            </h2>
            <p className="text-sm text-gray-600 mt-1">
              Atur mode verifikasi dan cakupan siswa absensi secara global.
            </p>
          </div>

          <button
            type="button"
            onClick={handleSave}
            disabled={saving}
            className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-60"
          >
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3">1. Mode Verifikasi</h3>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          {verificationModes.map((mode) => {
            const isActive = formData.verification_mode === mode.value;
            return (
              <button
                key={mode.value}
                type="button"
                onClick={() =>
                  setFormData((prev) => {
                    const next = {
                      ...prev,
                      verification_mode: mode.value,
                    };

                    if (mode.value === 'sync_final') {
                      next.face_reject_to_manual_review = false;
                      if (next.face_result_when_template_missing === 'manual_review') {
                        next.face_result_when_template_missing = 'rejected';
                      }
                    }

                    return next;
                  })
                }
                className={`text-left rounded-lg border p-4 transition ${
                  isActive
                    ? 'border-blue-500 bg-blue-50 shadow-sm'
                    : 'border-gray-200 bg-white hover:border-gray-300'
                }`}
              >
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-sm font-semibold text-gray-900">{mode.title}</p>
                    <p className="text-xs text-gray-600 mt-1">{mode.description}</p>
                  </div>
                  {isActive && <CheckCircle2 className="h-4 w-4 text-blue-600 mt-0.5" />}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Camera className="h-4 w-4 text-blue-600" />
          2. Kebijakan Verifikasi Wajah
        </h3>

        <div className="grid grid-cols-1 xl:grid-cols-4 gap-3">
          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Status Verifikasi Wajah</p>
            <p className="text-xs text-gray-600 mt-1">
              Toggle utama untuk mengaktifkan atau mematikan verifikasi wajah pada proses absensi siswa.
            </p>
            <label className="mt-3 flex items-start gap-3 text-sm text-gray-800">
              <input
                type="checkbox"
                checked={Boolean(formData.face_verification_enabled)}
                onChange={(event) =>
                  setFormData((prev) => ({
                    ...prev,
                    face_verification_enabled: event.target.checked,
                  }))
                }
                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span>
                <span className="block font-medium text-gray-900">
                  {formData.face_verification_enabled ? 'Aktif untuk absensi siswa' : 'Nonaktif'}
                </span>
                <span className="mt-1 block text-xs text-gray-600">
                  Saat nonaktif, selfie tetap mengikuti setting `wajib foto`, tetapi proses verifikasi wajah akan dilewati.
                </span>
              </span>
            </label>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Template Wajah Wajib</p>
            <p className="text-xs text-gray-600 mt-1">
              Saat aktif, siswa wajib memiliki template wajah aktif sebelum absensi, walaupun verifikasi wajah dimatikan.
            </p>
            <label className="mt-3 flex items-start gap-3 text-sm text-gray-800">
              <input
                type="checkbox"
                checked={Boolean(formData.face_template_required)}
                onChange={(event) =>
                  setFormData((prev) => ({
                    ...prev,
                    face_template_required: event.target.checked,
                  }))
                }
                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span>
                <span className="block font-medium text-gray-900">
                  {formData.face_template_required ? 'Template wajib sebelum absensi' : 'Template tidak diwajibkan'}
                </span>
                <span className="mt-1 block text-xs text-gray-600">
                  Gunakan mode ini bila sekolah ingin onboarding wajah konsisten seperti device binding.
                </span>
              </span>
            </label>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Template Wajah Tidak Tersedia</p>
            <p className="text-xs text-gray-600 mt-1">
              Menentukan hasil saat siswa belum memiliki template wajah aktif ketika verifikasi dijalankan dan policy template wajib dimatikan.
            </p>
            <label className="text-sm text-gray-700 mt-3 block">
              <span className="block text-xs text-gray-600 mb-1">Aksi sistem</span>
              <select
                value={formData.face_result_when_template_missing}
                onChange={(event) =>
                  setFormData((prev) => ({
                    ...prev,
                    face_result_when_template_missing: event.target.value,
                  }))
                }
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                {faceFallbackOptions.map((option) => {
                  const lockManualForSyncFinal =
                    formData.verification_mode === 'sync_final' &&
                    option.value === 'manual_review';

                  return (
                    <option
                      key={option.value}
                      value={option.value}
                      disabled={lockManualForSyncFinal}
                    >
                      {option.label}
                    </option>
                  );
                })}
              </select>
            </label>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Perilaku Fallback</p>
            <p className="text-xs text-gray-600 mt-1">
              Toggle ini mengatur kebijakan fallback sekolah, bukan setting teknis engine verifikasi.
            </p>
            <div className="mt-3 space-y-3">
              <label className="flex items-start gap-3 text-sm text-gray-800">
                <input
                  type="checkbox"
                  checked={Boolean(formData.face_reject_to_manual_review)}
                  disabled={formData.verification_mode === 'sync_final'}
                  onChange={(event) =>
                    setFormData((prev) => ({
                      ...prev,
                      face_reject_to_manual_review: event.target.checked,
                    }))
                  }
                  className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span>
                  <span className="block font-medium text-gray-900">Hasil gagal diarahkan ke review manual</span>
                  <span className="mt-1 block text-xs text-gray-600">
                    Jika verifikasi jatuh di bawah threshold, sistem bisa mengubah status menjadi review manual.
                    {formData.verification_mode === 'sync_final'
                      ? ' Pada mode Sync Final, opsi ini otomatis nonaktif.'
                      : ''}
                  </span>
                </span>
              </label>

              <label className="flex items-start gap-3 text-sm text-gray-800">
                <input
                  type="checkbox"
                  checked={Boolean(formData.face_skip_when_photo_missing)}
                  onChange={(event) =>
                    setFormData((prev) => ({
                      ...prev,
                      face_skip_when_photo_missing: event.target.checked,
                    }))
                  }
                  className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span>
                  <span className="block font-medium text-gray-900">Lewati verifikasi saat foto tidak tersedia</span>
                  <span className="mt-1 block text-xs text-gray-600">
                    Cocok untuk menjaga absensi tetap berjalan jika foto selfie tidak terkirim dari perangkat.
                  </span>
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="mb-3 flex items-center justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-gray-900">3. Batas Disiplin Siswa</h3>
            <p className="mt-1 text-xs text-gray-600">
              Override aktif saat ini: {disciplineOverrideSummary.active} dari {disciplineOverrideSummary.total} rule khusus.
            </p>
          </div>
          <button
            type="button"
            onClick={() => setDisciplineOverrideDialogOpen(true)}
            className="inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-100"
          >
            Override
          </button>
        </div>
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4">
          <label className="flex items-start gap-3 text-sm text-gray-800">
            <input
              type="checkbox"
              checked={Boolean(formData.discipline_thresholds_enabled)}
              onChange={(event) =>
                setFormData((prev) => ({
                  ...prev,
                  discipline_thresholds_enabled: event.target.checked,
                }))
              }
              className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span>
              <span className="block font-semibold text-gray-900">Aktifkan policy threshold disiplin v2</span>
              <span className="mt-1 block text-xs text-gray-600">
                Jika nonaktif, sistem kembali memakai fallback legacy menit/persen untuk kompatibilitas data lama.
              </span>
            </span>
          </label>
        </div>
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-3">
          {thresholdCards.map((card) => {
            const mode = formData[card.modeField];
            const alertable = formData.discipline_thresholds_enabled && mode === 'alertable';

            return (
              <div key={card.key} className="rounded-lg border border-gray-200 p-4 bg-gray-50">
                <p className="text-sm font-semibold text-gray-900">{card.title}</p>
                <p className="text-xs text-gray-600 mt-1">{card.description}</p>
                <label className="text-sm text-gray-700 mt-3 block">
                  <span className="block text-xs text-gray-600 mb-1">{card.limitLabel}</span>
                  <input
                    type="number"
                    min={0}
                    max={100000}
                    step={1}
                    value={formData[card.field]}
                    onChange={(event) =>
                      handleThresholdChange(card.field, event.target.value, card.unit === 'hari' ? 365 : 100000)
                    }
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </label>
                <label className="text-sm text-gray-700 mt-3 block">
                  <span className="block text-xs text-gray-600 mb-1">Mode indikator</span>
                  <select
                    value={mode}
                    onChange={(event) =>
                      setFormData((prev) => ({
                        ...prev,
                        [card.modeField]: event.target.value,
                      }))
                    }
                    disabled={!formData.discipline_thresholds_enabled}
                    className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-slate-100"
                  >
                    {thresholdModeOptions.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </label>

                <div className="mt-3 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-3">
                  <div className="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Routing alert</div>
                  {!formData.discipline_thresholds_enabled ? (
                    <p className="mt-2 text-xs text-gray-500">Threshold v2 belum aktif.</p>
                  ) : !alertable ? (
                    <p className="mt-2 text-xs text-gray-500">Indikator ini hanya monitoring dan tidak mengirim alert otomatis.</p>
                  ) : (
                    <div className="mt-2 space-y-2">
                      <label className="flex items-center gap-2 text-xs text-gray-700">
                        <input
                          type="checkbox"
                          checked={Boolean(formData[card.waliField])}
                          onChange={(event) =>
                            setFormData((prev) => ({
                              ...prev,
                              [card.waliField]: event.target.checked,
                            }))
                          }
                          className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        Notifikasi internal ke wali kelas
                      </label>
                      <label className="flex items-center gap-2 text-xs text-gray-700">
                        <input
                          type="checkbox"
                          checked={Boolean(formData[card.kesiswaanField])}
                          onChange={(event) =>
                            setFormData((prev) => ({
                              ...prev,
                              [card.kesiswaanField]: event.target.checked,
                            }))
                          }
                          className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        Notifikasi internal ke kesiswaan
                      </label>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <p className="text-xs text-gray-700">
            Semester memakai batas total pelanggaran menit dan batas hari alpha. Bulanan memakai batas keterlambatan menit.
          </p>
          <p className="text-xs text-gray-700 mt-2">
            Setiap indikator sekarang punya mode eksplisit: monitoring saja atau trigger alert otomatis. Alert hanya dikirim jika threshold v2 aktif dan indikator berada di mode alert.
          </p>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Clock3 className="h-4 w-4 text-blue-600" />
          4. Otomasi Absensi & Alert
        </h3>

        <div className="grid grid-cols-1 xl:grid-cols-2 gap-3">
          {automationCards.map((card) => (
            <div key={card.key} className="rounded-lg border border-gray-200 p-4 bg-gray-50">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-gray-900">{card.title}</p>
                  <p className="text-xs text-gray-600 mt-1">{card.description}</p>
                </div>
                <label className="inline-flex items-center gap-2 text-xs text-gray-700">
                  <input
                    type="checkbox"
                    checked={Boolean(formData[card.enabledField])}
                    onChange={(event) =>
                      setFormData((prev) => ({
                        ...prev,
                        [card.enabledField]: event.target.checked,
                      }))
                    }
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  Aktif
                </label>
              </div>

              <label className="text-sm text-gray-700 mt-3 block">
                <span className="block text-xs text-gray-600 mb-1">Jam eksekusi harian</span>
                <input
                  type="time"
                  value={formData[card.timeField]}
                  onChange={(event) => handleTimeFieldChange(card.timeField, event.target.value, card.defaultTime)}
                  disabled={!formData[card.enabledField]}
                  className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-slate-100"
                />
              </label>

              <p className="mt-3 text-xs text-gray-600">
                {formData[card.enabledField]
                  ? `Scheduler aktif dan akan dijalankan setiap hari pukul ${formData[card.timeField]}.`
                  : 'Scheduler nonaktif. Job tidak akan dijalankan sampai diaktifkan kembali.'}
              </p>
            </div>
          ))}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Database className="h-4 w-4 text-blue-600" />
          5. Retensi & Cleanup Live Tracking
        </h3>

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-3">
          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Live Tracking Global</p>
            <p className="text-xs text-gray-600 mt-1">
              Mengaktifkan atau mematikan pengiriman live tracking realtime dari mobile dan web sender.
            </p>
            <label className="mt-3 flex items-start gap-3 text-sm text-gray-800">
              <input
                type="checkbox"
                checked={Boolean(formData.live_tracking_enabled)}
                onChange={(event) =>
                  setFormData((prev) => ({
                    ...prev,
                    live_tracking_enabled: event.target.checked,
                  }))
                }
                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span>
                <span className="block font-medium text-gray-900">
                  {formData.live_tracking_enabled ? 'Live tracking aktif' : 'Live tracking nonaktif'}
                </span>
                <span className="mt-1 block text-xs text-gray-600">
                  Saat nonaktif, update realtime baru ditolak dan dashboard menampilkan status netral.
                </span>
              </span>
            </label>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Sampling Pergerakan</p>
            <p className="text-xs text-gray-600 mt-1">
              Histori `live_tracking` disimpan saat siswa berpindah minimal sejauh meter yang ditentukan.
            </p>
            <label className="text-sm text-gray-700 mt-3 block">
              <span className="block text-xs text-gray-600 mb-1">Minimal jarak (meter)</span>
              <input
                type="number"
                min={1}
                max={500}
                step={1}
                value={formData.live_tracking_min_distance_meters}
                onChange={(event) =>
                  handleBoundedIntegerChange('live_tracking_min_distance_meters', event.target.value, 500, 20)
                }
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </label>
            <p className="mt-2 text-[11px] text-gray-500">
              Sistem tetap menyimpan checkpoint saat ada perubahan status penting atau sesi terlalu lama diam.
            </p>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Retensi History Tracking</p>
            <p className="text-xs text-gray-600 mt-1">
              Menentukan berapa hari data `live_tracking` disimpan sebelum dibersihkan otomatis.
            </p>
            <label className="text-sm text-gray-700 mt-3 block">
              <span className="block text-xs text-gray-600 mb-1">Retensi (hari)</span>
              <input
                type="number"
                min={1}
                max={3650}
                step={1}
                value={formData.live_tracking_retention_days}
                onChange={(event) =>
                  handleBoundedIntegerChange('live_tracking_retention_days', event.target.value, 3650, 30)
                }
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </label>
          </div>

          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p className="text-sm font-semibold text-gray-900">Jadwal Cleanup Harian</p>
            <p className="text-xs text-gray-600 mt-1">
              Scheduler akan membersihkan data tracking lama setiap hari pada jam yang ditentukan.
            </p>
            <label className="text-sm text-gray-700 mt-3 block">
              <span className="block text-xs text-gray-600 mb-1">Jam cleanup</span>
              <input
                type="time"
                value={formData.live_tracking_cleanup_time}
                onChange={(event) => handleTimeFieldChange('live_tracking_cleanup_time', event.target.value, '02:15')}
                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </label>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <MapPin className="h-4 w-4 text-blue-600" />
          6. Monitor Sinkronisasi Lokasi GPS
        </h3>

        <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 mb-3">
          <p className="text-xs text-blue-800">
            Source konfigurasi aktif halaman ini: <span className="font-semibold">`/simple-attendance/global` + `/attendance-schemas`</span>.
            Endpoint <span className="font-semibold">`/settings/absensi`</span> diperlakukan legacy dan tidak dipakai untuk policy utama.
          </p>
          <p className="text-xs text-blue-800 mt-2">
            Binding lokasi pada skema akan mengikuti tipe area tiap lokasi di Manajemen Lokasi GPS, termasuk Circle dan Polygon.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2">
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Lokasi Aktif</p>
            <p className="text-lg font-semibold text-gray-900">{locationHealth.activeLocationsCount}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Total Skema</p>
            <p className="text-lg font-semibold text-gray-900">{locationHealth.totalSchemasCount}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Skema Wajib GPS</p>
            <p className="text-lg font-semibold text-gray-900">{locationHealth.gpsSchemasCount}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Skema Tanpa Binding Lokasi</p>
            <p className="text-lg font-semibold text-gray-900">{locationHealth.gpsSchemasWithoutBindingsCount}</p>
          </div>
        </div>

        {locationHealth.hasHardWarning ? (
          <div className="mt-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
            <p className="text-sm font-semibold text-red-800 flex items-center gap-2">
              <AlertTriangle className="h-4 w-4" />
              Warning Kritis Lokasi Absensi
            </p>
            <p className="text-xs text-red-700 mt-1">
              Terdapat skema wajib GPS tanpa binding lokasi, sementara tidak ada lokasi aktif.
              Absensi berpotensi gagal untuk user pada skema tersebut.
            </p>
          </div>
        ) : (
          <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
            <p className="text-xs text-emerald-800">
              Sinkronisasi lokasi terpantau aman. Pastikan skema penting tetap memiliki binding lokasi eksplisit agar validasi area Circle/Polygon tetap presisi.
            </p>
          </div>
        )}
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h3 className="text-sm font-semibold text-gray-900 mb-3">7. Health-Check Operasional</h3>

        {!systemHealth ? (
          <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <p className="text-xs text-gray-600">
              Health-check belum tersedia dari backend. Endpoint admin: <span className="font-semibold">`/simple-attendance/health-check`</span>.
            </p>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-2">
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Queue Face</p>
                <p className={`inline-flex mt-1 px-2 py-0.5 rounded-full border text-xs font-semibold ${queueBadgeClass}`}>
                  {queueStatus}
                </p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Pending Jobs</p>
                <p className="text-lg font-semibold text-gray-900">{queueHealth.pending_jobs ?? '-'}</p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Failed Jobs</p>
                <p className="text-lg font-semibold text-gray-900">{queueHealth.failed_jobs ?? '-'}</p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Queue Lag (detik)</p>
                <p className="text-lg font-semibold text-gray-900">{queueHealth.lag_seconds ?? '-'}</p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Face Service</p>
                <p className={`inline-flex mt-1 px-2 py-0.5 rounded-full border text-xs font-semibold ${faceServiceBadgeClass}`}>
                  {faceServiceStatus}
                </p>
                <p className="mt-1 text-[11px] text-gray-500">
                  {faceServiceHealth.engine || healthSummary.face_engine_version || '-'}
                </p>
              </div>
            </div>

            <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Lokasi Aktif (Health Check)</p>
                <p className="text-sm font-semibold text-gray-900 mt-1">
                  {locationCheck.count ?? locationHealth.activeLocationsCount} lokasi
                </p>
                <p className="text-xs text-gray-600 mt-1">{locationCheck.message || 'Tidak ada pesan.'}</p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Schema + Face Engine</p>
                <p className="text-sm font-semibold text-gray-900 mt-1">
                  {schemaCheck.schema_name || 'Belum ada schema default'}
                </p>
                <p className="text-xs text-gray-600 mt-1">
                  Threshold {healthSummary.face_threshold ?? '-'} | Engine {healthSummary.face_engine_version || '-'}
                </p>
              </div>
              <div className="rounded-lg border border-gray-200 px-3 py-2">
                <p className="text-xs text-gray-500">Inference Service</p>
                <p className="text-sm font-semibold text-gray-900 mt-1">
                  {faceServiceHealth.template_version || faceServiceHealth.engine || '-'}
                </p>
                <p className="text-xs text-gray-600 mt-1">
                  {(faceServiceHealth.url || healthSummary.face_service_url || '-')}
                </p>
                <p className="text-xs text-gray-600 mt-1">
                  {faceServiceHealth.message || 'Belum ada status inference service.'}
                </p>
              </div>
            </div>

            <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
              {automationChecks.map(([label, check]) => {
                const status = check?.status || 'unknown';
                const badgeClass =
                  status === 'healthy'
                    ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                    : status === 'warning'
                      ? 'bg-amber-100 text-amber-700 border-amber-200'
                      : status === 'disabled'
                        ? 'bg-slate-100 text-slate-600 border-slate-200'
                        : 'bg-gray-100 text-gray-700 border-gray-200';

                return (
                  <div key={label} className="rounded-lg border border-gray-200 px-3 py-2">
                    <div className="flex items-center justify-between gap-3">
                      <p className="text-xs text-gray-500">{label}</p>
                      <span className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold ${badgeClass}`}>
                        {status}
                      </span>
                    </div>
                    <p className="mt-1 text-sm font-semibold text-gray-900">
                      {check?.scheduled_time || '-'}
                    </p>
                    <p className="mt-1 text-xs text-gray-600">
                      {check?.message || 'Belum ada status scheduler.'}
                    </p>
                    <p className="mt-1 text-[11px] text-gray-500">
                      Terakhir jalan: {formatHealthRunAt(check?.last_run_at)}
                    </p>
                  </div>
                );
              })}
            </div>

            {systemHealth.overallStatus === 'warning' ? (
              <div className="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                <p className="text-sm font-semibold text-amber-800 flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4" />
                  Terdapat indikator operasional yang perlu ditindaklanjuti
                </p>
                <p className="text-xs text-amber-700 mt-1">
                  {queueHealth.message || faceServiceHealth.message || 'Periksa face service, queue worker, lokasi aktif, schema default, dan scheduler auto alpha / alert threshold.'}
                </p>
              </div>
            ) : (
              <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p className="text-xs text-emerald-800">
                  Health-check operasional terpantau baik. Face service, queue, dan scheduler absensi berjalan sesuai jadwal.
                </p>
              </div>
            )}
          </>
        )}
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex items-center justify-between gap-3 mb-3">
          <h3 className="text-sm font-semibold text-gray-900">8. Fraud Monitoring Absensi</h3>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleExportFraudAssessments}
              className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              <Download className="h-3.5 w-3.5" />
              Export Fraud
            </button>
            <button
              type="button"
              onClick={() => refreshFraudData(currentFraudPage)}
              disabled={fraudLoading}
              className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-60"
            >
              {fraudLoading ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <RefreshCw className="h-3.5 w-3.5" />
              )}
              Refresh Fraud
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-2">
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Rollout Mode</p>
            <p className="text-sm font-semibold text-gray-900 mt-1">
              {fraudConfig.rollout_mode || '-'}
            </p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Total Assessment</p>
            <p className="text-lg font-semibold text-gray-900">{fraudOverview.total_assessments ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Warning</p>
            <p className="text-lg font-semibold text-gray-900">{fraudOverview.warning_count ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Tanpa Warning</p>
            <p className="text-lg font-semibold text-gray-900">{Math.max(0, Number(fraudOverview.total_assessments ?? 0) - Number(fraudOverview.warning_count ?? 0))}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Pra-cek</p>
            <p className="text-lg font-semibold text-gray-900">{fraudOverview.precheck_warning_count ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Submit</p>
            <p className="text-lg font-semibold text-gray-900">{fraudOverview.submit_warning_count ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Siswa Terdampak</p>
            <p className="text-lg font-semibold text-gray-900">{fraudOverview.unique_students ?? 0}</p>
          </div>
        </div>

        <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
          <p className="text-xs text-blue-800">
            Sistem anti-fraud aktif dengan mode <span className="font-semibold">{fraudConfig.rollout_mode || 'warning_mode'}</span>.
            Semua sinyal dicatat sebagai warning-only tanpa auto reject.
            Sinyal aktif: {fraudConfig.signals_enabled ?? 0}. Assessment kini bisa berasal dari tahap pra-cek maupun submit presensi. Device binding siswa tetap diperlakukan sebagai hard block.
          </p>
        </div>

        <div className="mt-3">
          <p className="text-xs font-semibold text-gray-700 mb-2">Flag Paling Sering Muncul</p>
          {fraudSummary.topFlags?.length ? (
            <div className="flex flex-wrap gap-2">
              {fraudSummary.topFlags.slice(0, 8).map((flag) => (
                <span
                  key={flag.flag_key}
                  className="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] text-amber-800"
                >
                  <span className="font-semibold">{flag.label || flag.flag_key}</span>
                  <span>{flag.total}</span>
                </span>
              ))}
            </div>
          ) : (
            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <p className="text-xs text-gray-600">Belum ada fraud flag yang tercatat pada periode ini.</p>
            </div>
          )}
        </div>

        <div className="mt-4">
          <p className="text-xs font-semibold text-gray-700 mb-2">Warning Terbaru</p>
          {recentFraudWarnings.length ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
              {recentFraudWarnings.slice(0, 4).map((assessment) => (
                <div key={assessment.id} className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-xs font-semibold text-gray-900">{assessment?.student?.name || '-'}</p>
                      <p className="mt-1 text-[11px] text-gray-600">{assessment?.student?.identifier || '-'}</p>
                    </div>
                    <span className="inline-flex rounded-full border border-amber-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                      {assessment.source_label || assessment.source || '-'}
                    </span>
                  </div>
                  <p className="mt-2 text-xs text-amber-900">
                    {assessment.warning_summary || assessment.decision_reason || '-'}
                  </p>
                  <p className="mt-2 text-[11px] text-amber-700">
                    {assessment.created_at ? (formatServerDateTime(assessment.created_at, 'id-ID') || '-') : '-'}
                  </p>
                </div>
              ))}
            </div>
          ) : (
            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <p className="text-xs text-gray-600">Belum ada warning fraud terbaru pada filter yang dipilih.</p>
            </div>
          )}
        </div>

        <div className="mt-4">
          <p className="text-xs font-semibold text-gray-700 mb-2">Assessment Terbaru</p>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
            <select
              value={fraudFilters.source}
              onChange={(event) => setFraudFilters((prev) => ({ ...prev, source: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Tahap</option>
              <option value="attendance_precheck">Pra-cek</option>
              <option value="attendance_submit">Submit</option>
            </select>
            <select
              value={fraudFilters.validation_status}
              onChange={(event) => setFraudFilters((prev) => ({ ...prev, validation_status: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Status Validasi</option>
              <option value="warning">Warning</option>
              <option value="valid">Valid</option>
            </select>
            <button
              type="button"
              onClick={() => refreshFraudData(1)}
              className="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50"
            >
              Terapkan Filter
            </button>
          </div>
          {fraudLoading ? (
            <div className="text-sm text-gray-600 flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              Memuat fraud monitoring...
            </div>
          ) : fraudAssessments.length === 0 ? (
            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <p className="text-xs text-gray-600">Belum ada assessment fraud untuk ditampilkan.</p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto border border-gray-200 rounded-lg">
                <table className="min-w-full text-xs">
                  <thead className="bg-gray-50 border-b border-gray-200">
                    <tr>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Waktu</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Siswa</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Tahap</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Validasi</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Ringkasan</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Signals</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Flags</th>
                      <th className="text-left px-3 py-2 font-semibold text-gray-700">Rekomendasi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {fraudAssessments.map((assessment) => (
                      <tr key={assessment.id} className="border-b border-gray-100 last:border-b-0">
                        <td className="px-3 py-2 text-gray-700 whitespace-nowrap">
                          {assessment.created_at ? (formatServerDateTime(assessment.created_at, 'id-ID') || '-') : '-'}
                        </td>
                        <td className="px-3 py-2 text-gray-800">
                          <div className="font-semibold text-gray-900">{assessment?.student?.name || '-'}</div>
                          <div className="text-[11px] text-gray-500">{assessment?.student?.identifier || '-'}</div>
                        </td>
                        <td className="px-3 py-2 text-gray-800">
                          <span className="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                            {assessment.source_label || assessment.source || '-'}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-gray-800">
                          <span className="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold">
                            {assessment.validation_status_label || assessment.validation_status || '-'}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-gray-800">
                          <div className="text-[11px] text-gray-600">
                            {assessment.warning_summary || assessment.decision_reason || '-'}
                          </div>
                        </td>
                        <td className="px-3 py-2 text-gray-600">
                          {Array.isArray(assessment.flags) && assessment.flags.length > 0
                            ? assessment.flags.slice(0, 2).map((flag) => flag.label || flag.flag_key).join(', ')
                            : '-'}
                        </td>
                        <td className="px-3 py-2 text-gray-800">{assessment.fraud_flags_count ?? 0}</td>
                        <td className="px-3 py-2 text-gray-600">
                          {assessment.recommended_action || assessment.decision_reason || '-'}
                          {asArray(assessment?.notice_boxes).map((box, index) =>
                            renderNoticeBox(box, `fraud-${assessment.id}-${index}`)
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="mt-3 flex items-center justify-between gap-2">
                <p className="text-xs text-gray-600">
                  Total assessment: {fraudPagination.total}
                </p>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => refreshFraudData(currentFraudPage - 1)}
                    disabled={!canPrevFraudPage || fraudLoading}
                    className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Sebelumnya
                  </button>
                  <span className="text-xs text-gray-600">
                    Hal {currentFraudPage} / {lastFraudPage}
                  </span>
                  <button
                    type="button"
                    onClick={() => refreshFraudData(currentFraudPage + 1)}
                    disabled={!canNextFraudPage || fraudLoading}
                    className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Berikutnya
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex items-center justify-between gap-3 mb-3">
          <h3 className="text-sm font-semibold text-gray-900">9. Security Monitoring Absensi</h3>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleExportSecurityEvents}
              className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              <Download className="h-3.5 w-3.5" />
              Export Security
            </button>
            <button
              type="button"
              onClick={() => refreshSecurityData(currentSecurityPage)}
              disabled={securityLoading}
              className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-60"
            >
              {securityLoading ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <RefreshCw className="h-3.5 w-3.5" />
              )}
              Refresh Security
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2">
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Total Events</p>
            <p className="text-lg font-semibold text-gray-900">{securityOverview.total_events ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Blocked / Flagged</p>
            <p className="text-lg font-semibold text-gray-900">
              {(securityOverview.blocked_events ?? 0)} / {(securityOverview.flagged_events ?? 0)}
            </p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Siswa Terdampak</p>
            <p className="text-lg font-semibold text-gray-900">{securityOverview.unique_students ?? 0}</p>
          </div>
          <div className="rounded-lg border border-gray-200 px-3 py-2">
            <p className="text-xs text-gray-500">Fake GPS Events</p>
            <p className="text-lg font-semibold text-gray-900">{securityOverview.mock_location_events ?? 0}</p>
          </div>
        </div>

        <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
          <p className="text-xs text-amber-800">
            Panel ini memisahkan warning <span className="font-semibold">pra-cek</span> dan catatan saat <span className="font-semibold">presensi</span>.
            Absensi tidak diblokir oleh warning keamanan, tetapi semua indikator tetap dicatat untuk monitoring guru, wali kelas, dan admin.
          </p>
        </div>

        <div className="mt-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <div className="flex items-center gap-2">
              <Smartphone className="h-4 w-4 text-blue-600" />
              <p className="text-xs font-semibold text-gray-700">Breakdown Tahap</p>
            </div>
            {securityStageBreakdown.length === 0 ? (
              <p className="mt-3 text-xs text-gray-600">Belum ada data tahap warning keamanan.</p>
            ) : (
              <div className="mt-3 flex flex-wrap gap-2">
                {securityStageBreakdown.map((item) => (
                  <span
                    key={item.stage || item.stage_label}
                    className="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1 text-[11px] font-semibold text-blue-800"
                  >
                    <span>{item.stage_label || item.stage || '-'}</span>
                    <span>{item.total ?? 0}</span>
                  </span>
                ))}
              </div>
            )}
          </div>
          <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <div className="flex items-center gap-2">
              <ShieldAlert className="h-4 w-4 text-amber-600" />
              <p className="text-xs font-semibold text-gray-700">Event Paling Sering</p>
            </div>
            {securityEventBreakdown.length === 0 ? (
              <p className="mt-3 text-xs text-gray-600">Belum ada event keamanan dominan.</p>
            ) : (
              <div className="mt-3 flex flex-wrap gap-2">
                {securityEventBreakdown.slice(0, 8).map((item) => (
                  <span
                    key={item.event_key}
                    className="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800"
                  >
                    <span>{item.event_label || item.event_key || '-'}</span>
                    <span>{item.total ?? 0}</span>
                  </span>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="mt-4">
          <p className="text-xs font-semibold text-gray-700 mb-2">Event Security Terbaru</p>
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-2 mb-3">
            <select
              value={securityFilters.stage}
              onChange={(event) => setSecurityFilters((prev) => ({ ...prev, stage: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Tahap</option>
              <option value="attendance_precheck">Pra-cek</option>
              <option value="attendance_submit">Presensi</option>
            </select>
            <select
              value={securityFilters.issue_key}
              onChange={(event) => setSecurityFilters((prev) => ({ ...prev, issue_key: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Issue</option>
              {securityIssueFilterOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <select
              value={securityFilters.status}
              onChange={(event) => setSecurityFilters((prev) => ({ ...prev, status: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Status</option>
              <option value="flagged">Flagged</option>
              <option value="blocked">Blocked</option>
            </select>
            <select
              value={securityFilters.severity}
              onChange={(event) => setSecurityFilters((prev) => ({ ...prev, severity: event.target.value }))}
              className="rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700"
            >
              <option value="">Semua Severity</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
            <button
              type="button"
              onClick={() => refreshSecurityData(1)}
              className="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50"
            >
              Terapkan Filter
            </button>
          </div>

          {securityLoading ? (
            <div className="text-sm text-gray-600 flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              Memuat security monitoring...
            </div>
          ) : securityEvents.length === 0 ? (
            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <p className="text-xs text-gray-600">Belum ada security event untuk ditampilkan.</p>
            </div>
          ) : (
            <>
              <div className="space-y-3">
                {securityEvents.map((event) => (
                  <div key={event.id} className="rounded-lg border border-gray-200 p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                            {event.stage_label || event.stage || '-'}
                          </span>
                          <span className="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-gray-700">
                            {event.status_label || event.status || '-'}
                          </span>
                          <span className="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                            {event.severity_label || event.severity || '-'}
                          </span>
                        </div>
                        <p className="mt-2 text-sm font-semibold text-gray-900">{event.event_label || event.event_key || '-'}</p>
                        <p className="mt-1 text-xs text-gray-500">
                          {event?.student?.name || '-'} | {event?.student?.identifier || '-'} | {event.last_seen_at ? (formatServerDateTime(event.last_seen_at, 'id-ID') || '-') : (event.created_at ? (formatServerDateTime(event.created_at, 'id-ID') || '-') : '-')}
                        </p>
                        <p className="mt-2 text-xs text-gray-700">{event.message || '-'}</p>
                        {Number(event?.occurrence_count || 0) > 1 ? (
                          <p className="mt-2 text-[11px] font-semibold text-amber-700">
                            Tercatat {event.occurrence_count} kali pada hari yang sama
                          </p>
                        ) : null}
                      </div>
                    </div>
                    {renderNoticeBox(event.notice_box, `security-${event.id}`)}
                  </div>
                ))}
              </div>

              <div className="mt-3 flex items-center justify-between gap-2">
                <p className="text-xs text-gray-600">
                  Total event: {securityPagination.total}
                </p>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => refreshSecurityData(currentSecurityPage - 1)}
                    disabled={!canPrevSecurityPage || securityLoading}
                    className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Sebelumnya
                  </button>
                  <span className="text-xs text-gray-600">
                    Hal {currentSecurityPage} / {lastSecurityPage}
                  </span>
                  <button
                    type="button"
                    onClick={() => refreshSecurityData(currentSecurityPage + 1)}
                    disabled={!canNextSecurityPage || securityLoading}
                    className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Berikutnya
                  </button>
                </div>
              </div>
            </>
          )}
        </div>

        <div className="mt-4">
          <p className="text-xs font-semibold text-gray-700 mb-2">Siswa Prioritas Klarifikasi</p>
          {securityFollowUpStudents.length === 0 ? (
            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <p className="text-xs text-gray-600">Belum ada siswa yang perlu follow up khusus dari sisi keamanan.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
              {securityFollowUpStudents.slice(0, 8).map((student) => (
                <div key={`${student.user_id}-${student.last_event_at || 'latest'}`} className="rounded-lg border border-gray-200 px-4 py-3">
                  <p className="text-sm font-semibold text-gray-900">{student.student_name || '-'}</p>
                  <p className="mt-1 text-xs text-gray-500">{student.student_identifier || '-'} | {student.last_event_at ? (formatServerDateTime(student.last_event_at, 'id-ID') || '-') : '-'}</p>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <span className="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-gray-700">
                      Total {student.total_events ?? 0}
                    </span>
                    <span className="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                      Blocked {student.blocked_events ?? 0}
                    </span>
                    <span className="inline-flex rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-700">
                      Fake GPS {student.mock_location_events ?? 0}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex items-center justify-between gap-3 mb-3">
          <h3 className="text-sm font-semibold text-gray-900">10. Audit Governance Terbaru</h3>
          <button
            type="button"
            onClick={() => refreshGovernanceLogs(currentLogPage)}
            disabled={governanceLoading}
            className="inline-flex items-center gap-2 px-3 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-60"
          >
            {governanceLoading ? (
              <Loader2 className="h-3.5 w-3.5 animate-spin" />
            ) : (
              <RefreshCw className="h-3.5 w-3.5" />
            )}
            Refresh Log
          </button>
        </div>

        {governanceLoading ? (
          <div className="text-sm text-gray-600 flex items-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            Memuat audit log governance...
          </div>
        ) : governanceLogs.length === 0 ? (
          <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <p className="text-xs text-gray-600">Belum ada log governance absensi yang tersimpan.</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto border border-gray-200 rounded-lg">
              <table className="min-w-full text-xs">
                <thead className="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Waktu</th>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Kategori</th>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Aksi</th>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Actor</th>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Target</th>
                    <th className="text-left px-3 py-2 font-semibold text-gray-700">Catatan</th>
                  </tr>
                </thead>
                <tbody>
                  {governanceLogs.map((log) => {
                    const changedFields = Array.isArray(log?.metadata?.changed_fields)
                      ? log.metadata.changed_fields
                      : [];

                    return (
                      <tr key={log.id} className="border-b border-gray-100 last:border-b-0">
                        <td className="px-3 py-2 text-gray-700 whitespace-nowrap">
                          {log.created_at ? (formatServerDateTime(log.created_at, 'id-ID') || '-') : '-'}
                        </td>
                        <td className="px-3 py-2 text-gray-800">{formatLogText(log.category)}</td>
                        <td className="px-3 py-2 text-gray-800">{formatLogText(log.action)}</td>
                        <td className="px-3 py-2 text-gray-800">{getActorName(log)}</td>
                        <td className="px-3 py-2 text-gray-800">
                          {log.target_type ? `${log.target_type}#${log.target_id ?? '-'}` : '-'}
                        </td>
                        <td className="px-3 py-2 text-gray-600">
                          {changedFields.length > 0
                            ? `Field berubah: ${changedFields.join(', ')}`
                            : (log?.metadata?.reason || '-')}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="mt-3 flex items-center justify-between gap-2">
              <p className="text-xs text-gray-600">
                Menampilkan log terbaru. Total: {governancePagination.total}
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => refreshGovernanceLogs(currentLogPage - 1)}
                  disabled={!canPrevLogPage || governanceLoading}
                  className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  Sebelumnya
                </button>
                <span className="text-xs text-gray-600">
                  Hal {currentLogPage} / {lastLogPage}
                </span>
                <button
                  type="button"
                  onClick={() => refreshGovernanceLogs(currentLogPage + 1)}
                  disabled={!canNextLogPage || governanceLoading}
                  className="px-2.5 py-1.5 text-xs border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  Berikutnya
                </button>
              </div>
            </div>
          </>
        )}
      </div>

      <div className="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
        <p className="text-sm font-semibold text-blue-900">Ringkasan Policy Aktif</p>
        <div className="mt-2 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3 text-xs text-blue-900">
          <div className="rounded-md bg-white/70 border border-blue-100 px-3 py-2">
            <p className="font-medium">Mode Verifikasi</p>
            <p className="mt-1">{activeVerificationMode.title}</p>
          </div>
          <div className="rounded-md bg-white/70 border border-blue-100 px-3 py-2">
            <p className="font-medium">Fallback Face</p>
            <p className="mt-1">
              {formData.face_verification_enabled ? 'Aktif' : 'Nonaktif'} | Template wajib: {formData.face_template_required ? 'ya' : 'tidak'} | Template kosong: {formData.face_result_when_template_missing} | Review: {formData.face_reject_to_manual_review ? 'on' : 'off'}
            </p>
          </div>
          <div className="rounded-md bg-white/70 border border-blue-100 px-3 py-2">
            <p className="font-medium">Batas Pelanggaran</p>
            <p className="mt-1">
              {formData.discipline_thresholds_enabled
                ? `Semester ${Number(formData.total_violation_minutes_semester_limit || 0)} menit, Alpha ${Number(formData.alpha_days_semester_limit || 0)} hari, Bulanan ${Number(formData.late_minutes_monthly_limit || 0)} menit`
                : 'Threshold v2 nonaktif, fallback legacy aktif'}
            </p>
            <p className="mt-1">Override aktif: {disciplineOverrideSummary.active}</p>
          </div>
          <div className="rounded-md bg-white/70 border border-blue-100 px-3 py-2">
            <p className="font-medium">Otomasi</p>
            <p className="mt-1">
              Auto alpha {formData.auto_alpha_enabled ? formData.auto_alpha_run_time : 'off'} | Alert {formData.discipline_alerts_enabled ? formData.discipline_alerts_run_time : 'off'}
            </p>
          </div>
          <div className="rounded-md bg-white/70 border border-blue-100 px-3 py-2">
            <p className="font-medium">Live Tracking</p>
            <p className="mt-1">
              {formData.live_tracking_enabled ? 'Aktif' : 'Nonaktif'} | Sampling {Number(formData.live_tracking_min_distance_meters || 0)}m | Retensi {Number(formData.live_tracking_retention_days || 0)} hari | Cleanup {formData.live_tracking_cleanup_time}
            </p>
          </div>
        </div>
        <p className="mt-3 text-xs text-blue-800">
          Policy global aktif untuk seluruh siswa yang tidak punya override khusus. Override disiplin yang sedang aktif: {disciplineOverrideSummary.active}. Mode alert: semester {formData.semester_total_violation_mode}, alpha {formData.semester_alpha_mode}, bulanan {formData.monthly_late_mode}. Scheduler: auto alpha {formData.auto_alpha_enabled ? formData.auto_alpha_run_time : 'nonaktif'}, alert threshold {formData.discipline_alerts_enabled ? formData.discipline_alerts_run_time : 'nonaktif'}, live tracking {formData.live_tracking_enabled ? 'aktif' : 'nonaktif'}, cleanup live tracking {formData.live_tracking_cleanup_time}, sampling histori {Number(formData.live_tracking_min_distance_meters || 20)}m. Face verification {formData.face_verification_enabled ? 'aktif' : 'nonaktif'}; template wajib {formData.face_template_required ? 'aktif' : 'nonaktif'}; template kosong {formData.face_result_when_template_missing}, reject ke review {formData.face_reject_to_manual_review ? 'aktif' : 'nonaktif'}, skip foto kosong {formData.face_skip_when_photo_missing ? 'aktif' : 'nonaktif'}.
        </p>
      </div>
      <DisciplineOverrideDialog
        isOpen={disciplineOverrideDialogOpen}
        onClose={() => setDisciplineOverrideDialogOpen(false)}
        defaultConfig={{
          discipline_thresholds_enabled: formData.discipline_thresholds_enabled,
          total_violation_minutes_semester_limit: formData.total_violation_minutes_semester_limit,
          semester_total_violation_mode: formData.semester_total_violation_mode,
          notify_wali_kelas_on_total_violation_limit: formData.notify_wali_kelas_on_total_violation_limit,
          notify_kesiswaan_on_total_violation_limit: formData.notify_kesiswaan_on_total_violation_limit,
          alpha_days_semester_limit: formData.alpha_days_semester_limit,
          semester_alpha_mode: formData.semester_alpha_mode,
          late_minutes_monthly_limit: formData.late_minutes_monthly_limit,
          monthly_late_mode: formData.monthly_late_mode,
          notify_wali_kelas_on_late_limit: formData.notify_wali_kelas_on_late_limit,
          notify_kesiswaan_on_late_limit: formData.notify_kesiswaan_on_late_limit,
          notify_wali_kelas_on_alpha_limit: formData.notify_wali_kelas_on_alpha_limit,
          notify_kesiswaan_on_alpha_limit: formData.notify_kesiswaan_on_alpha_limit,
        }}
        onChanged={(summary) => setDisciplineOverrideSummary(summary)}
      />
    </div>
  );
};

export default AttendanceGlobalSettingsPanel;

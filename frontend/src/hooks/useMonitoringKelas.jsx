import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { monitoringKelasAPI } from '../services/api';
import useServerClock from './useServerClock';

const DEFAULT_PAGINATION = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
};

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizePaginatorPayload = (payload, fallbackPerPage = 10) => {
  if (!payload || typeof payload !== 'object') {
    return {
      rows: [],
      meta: {
        ...DEFAULT_PAGINATION,
        per_page: fallbackPerPage,
      },
    };
  }

  const rows = Array.isArray(payload.data)
    ? payload.data
    : Array.isArray(payload.items)
      ? payload.items
      : [];

  const currentPage = toNumber(payload.current_page, 1);
  const lastPage = Math.max(1, toNumber(payload.last_page, 1));
  const perPage = Math.max(1, toNumber(payload.per_page, fallbackPerPage));
  const total = Math.max(0, toNumber(payload.total, rows.length));
  const from = toNumber(payload.from, rows.length > 0 ? ((currentPage - 1) * perPage) + 1 : 0);
  const to = toNumber(payload.to, rows.length > 0 ? from + rows.length - 1 : 0);

  return {
    rows,
    meta: {
      current_page: currentPage,
      last_page: lastPage,
      per_page: perPage,
      total,
      from,
      to,
    },
  };
};

const normalizeClassRows = (payload) => {
  const rows = Array.isArray(payload)
    ? payload
    : Array.isArray(payload?.data)
      ? payload.data
      : [];

  return rows
    .map((row) => {
      const classLabel = row?.nama_lengkap
        || [row?.tingkat?.nama, row?.jurusan, row?.nama_kelas].filter(Boolean).join(' ')
        || (row?.nama_kelas ? String(row.nama_kelas) : null)
        || `Kelas #${row?.id ?? '-'}`;

      return {
        id: toNumber(row?.id, 0),
        nama_lengkap: classLabel,
        tingkat: row?.tingkat?.nama || null,
        jurusan: row?.jurusan || null,
        jumlah_siswa: toNumber(row?.jumlah_siswa, 0),
        hadir_hari_ini: toNumber(row?.hadir_hari_ini, 0),
        terlambat_hari_ini: toNumber(row?.terlambat_hari_ini, 0),
        tidak_hadir_hari_ini: toNumber(row?.tidak_hadir_hari_ini, 0),
        izin_pending: toNumber(row?.izin_pending, 0),
      };
    })
    .filter((row) => row.id > 0)
    .sort((left, right) => String(left.nama_lengkap).localeCompare(String(right.nama_lengkap), 'id'));
};

const parseErrorMessage = (error, fallbackMessage) => (
  error?.response?.data?.message
  || error?.message
  || fallbackMessage
);

const isDateInputValue = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || '').trim());

const isMonthInputValue = (value) => /^\d{4}-\d{2}$/.test(String(value || '').trim());

const isSuspiciousDateInput = (value, serverDate) => {
  if (!isDateInputValue(value) || !isDateInputValue(serverDate)) {
    return true;
  }

  const year = Number(String(value).slice(0, 4));
  const serverYear = Number(String(serverDate).slice(0, 4));

  return !Number.isFinite(year)
    || !Number.isFinite(serverYear)
    || year < serverYear - 1
    || year > serverYear + 1;
};

const isSuspiciousMonthInput = (value, serverDate) => {
  if (!isMonthInputValue(value) || !isDateInputValue(serverDate)) {
    return true;
  }

  const year = Number(String(value).slice(0, 4));
  const serverYear = Number(String(serverDate).slice(0, 4));

  return !Number.isFinite(year)
    || !Number.isFinite(serverYear)
    || year < serverYear - 1
    || year > serverYear + 1;
};

export const useMonitoringKelas = () => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [classes, setClasses] = useState([]);
  const [classesLoading, setClassesLoading] = useState(false);
  const [classesError, setClassesError] = useState(null);

  const [selectedClassId, setSelectedClassId] = useState(null);

  const [overviewLoading, setOverviewLoading] = useState(false);
  const [overviewError, setOverviewError] = useState(null);
  const [classDetail, setClassDetail] = useState(null);
  const [statistics, setStatistics] = useState(null);
  const [attendance, setAttendance] = useState([]);
  const [attendanceSummary, setAttendanceSummary] = useState({
    hadir: 0,
    terlambat: 0,
    izin: 0,
    sakit: 0,
    alpha: 0,
  });

  const attendanceDateTouchedRef = useRef(false);
  const statisticsMonthTouchedRef = useRef(false);
  const [attendanceDate, setAttendanceDateState] = useState('');
  const [statisticsMonth, setStatisticsMonthState] = useState('');

  const [fraudRows, setFraudRows] = useState([]);
  const [fraudSummary, setFraudSummary] = useState(null);
  const [fraudConfig, setFraudConfig] = useState(null);
  const [fraudLoading, setFraudLoading] = useState(false);
  const [fraudError, setFraudError] = useState(null);
  const [fraudFilters, setFraudFilters] = useState({
    source: '',
    validation_status: '',
    attempt_type: '',
    flag_key: '',
    date_from: '',
    date_to: '',
  });
  const [fraudPagination, setFraudPagination] = useState(DEFAULT_PAGINATION);

  const [securityRows, setSecurityRows] = useState([]);
  const [securitySummary, setSecuritySummary] = useState(null);
  const [securityConfig, setSecurityConfig] = useState(null);
  const [securityLoading, setSecurityLoading] = useState(false);
  const [securityError, setSecurityError] = useState(null);
  const [securityFilters, setSecurityFilters] = useState({
    issue_key: '',
    status: '',
    stage: '',
    attempt_type: '',
    date_from: '',
    date_to: '',
  });
  const [securityPagination, setSecurityPagination] = useState(DEFAULT_PAGINATION);

  const [securityStudentRows, setSecurityStudentRows] = useState([]);
  const [securityStudentSummary, setSecurityStudentSummary] = useState(null);
  const [securityStudentsLoading, setSecurityStudentsLoading] = useState(false);
  const [securityStudentsError, setSecurityStudentsError] = useState(null);
  const [securityStudentScope, setSecurityStudentScope] = useState('needs_case');
  const [securityStudentPagination, setSecurityStudentPagination] = useState(DEFAULT_PAGINATION);

  const [securityCaseRows, setSecurityCaseRows] = useState([]);
  const [securityCasesLoading, setSecurityCasesLoading] = useState(false);
  const [securityCasesError, setSecurityCasesError] = useState(null);
  const [securityCaseFilters, setSecurityCaseFilters] = useState({
    case_scope: 'active',
    status: '',
    priority: '',
    search: '',
  });
  const [securityCasePagination, setSecurityCasePagination] = useState(DEFAULT_PAGINATION);

  const [leaveRows, setLeaveRows] = useState([]);
  const [leaveLoading, setLeaveLoading] = useState(false);
  const [leaveError, setLeaveError] = useState(null);
  const [leaveFilters, setLeaveFilters] = useState({
    status: '',
    jenis_izin: '',
    search: '',
    date_from: '',
    date_to: '',
  });
  const [leavePagination, setLeavePagination] = useState(DEFAULT_PAGINATION);

  const [refreshing, setRefreshing] = useState(false);

  const selectedClass = useMemo(
    () => classes.find((row) => row.id === selectedClassId) || null,
    [classes, selectedClassId]
  );

  const setAttendanceDate = useCallback((value) => {
    attendanceDateTouchedRef.current = true;
    setAttendanceDateState(value);
  }, []);

  const setStatisticsMonth = useCallback((value) => {
    statisticsMonthTouchedRef.current = true;
    setStatisticsMonthState(value);
  }, []);

  const loadClasses = useCallback(async () => {
    setClassesLoading(true);
    setClassesError(null);
    try {
      const response = await monitoringKelasAPI.getClasses();
      const normalized = normalizeClassRows(response?.data);
      setClasses(normalized);

      setSelectedClassId((previousId) => {
        if (previousId && normalized.some((row) => row.id === previousId)) {
          return previousId;
        }
        return normalized[0]?.id || null;
      });
    } catch (error) {
      setClassesError(parseErrorMessage(error, 'Gagal memuat daftar kelas'));
      setClasses([]);
      setSelectedClassId(null);
    } finally {
      setClassesLoading(false);
    }
  }, []);

  const loadOverview = useCallback(async (
    classId = selectedClassId,
    month = statisticsMonth,
    date = attendanceDate
  ) => {
    if (!classId) {
      return;
    }

    setOverviewLoading(true);
    setOverviewError(null);
    try {
      const [detailResponse, statisticsResponse, attendanceResponse] = await Promise.all([
        monitoringKelasAPI.getClassDetail(classId),
        monitoringKelasAPI.getClassStatistics(classId, isMonthInputValue(month) ? { bulan: month } : {}),
        monitoringKelasAPI.getClassAttendance(classId, isDateInputValue(date) ? { tanggal: date } : {}),
      ]);

      const detailPayload = detailResponse?.data || {};
      const statisticsPayload = statisticsResponse?.data || {};
      const attendancePayload = attendanceResponse?.data || {};

      setClassDetail({
        ...detailPayload,
        kelas: detailPayload?.kelas || null,
        hadir_hari_ini: toNumber(detailPayload?.hadir_hari_ini, 0),
        terlambat_hari_ini: toNumber(detailPayload?.terlambat_hari_ini, 0),
        tidak_hadir_hari_ini: toNumber(detailPayload?.tidak_hadir_hari_ini, 0),
        izin_pending: toNumber(detailPayload?.izin_pending, 0),
      });

      setStatistics({
        persentase_kehadiran: toNumber(statisticsPayload?.persentase_kehadiran, 0),
        total_hadir: toNumber(statisticsPayload?.total_hadir, 0),
        total_tepat_waktu: toNumber(statisticsPayload?.total_tepat_waktu, 0),
        total_terlambat: toNumber(statisticsPayload?.total_terlambat, 0),
        total_tidak_hadir: toNumber(statisticsPayload?.total_tidak_hadir, 0),
        siswa_terbanyak_alpha: Array.isArray(statisticsPayload?.siswa_terbanyak_alpha)
          ? statisticsPayload.siswa_terbanyak_alpha
          : [],
      });

      const attendanceRows = Array.isArray(attendancePayload?.detail)
        ? attendancePayload.detail
        : [];

      setAttendance(attendanceRows);
      setAttendanceSummary({
        hadir: toNumber(attendancePayload?.hadir, 0),
        terlambat: toNumber(attendancePayload?.terlambat, 0),
        izin: toNumber(attendancePayload?.izin, 0),
        sakit: toNumber(attendancePayload?.sakit, 0),
        alpha: toNumber(attendancePayload?.alpha, 0),
      });
    } catch (error) {
      setOverviewError(parseErrorMessage(error, 'Gagal memuat ringkasan kelas'));
      setClassDetail(null);
      setStatistics(null);
      setAttendance([]);
      setAttendanceSummary({
        hadir: 0,
        terlambat: 0,
        izin: 0,
        sakit: 0,
        alpha: 0,
      });
    } finally {
      setOverviewLoading(false);
    }
  }, [attendanceDate, selectedClassId, statisticsMonth]);

  const loadFraud = useCallback(async (
    classId = selectedClassId,
    filters = fraudFilters,
    page = fraudPagination.current_page,
    perPage = fraudPagination.per_page
  ) => {
    if (!classId) {
      return;
    }

    setFraudLoading(true);
    setFraudError(null);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(filters?.source ? { source: filters.source } : {}),
        ...(filters?.validation_status ? { validation_status: filters.validation_status } : {}),
        ...(filters?.attempt_type ? { attempt_type: filters.attempt_type } : {}),
        ...(filters?.flag_key ? { flag_key: filters.flag_key } : {}),
        ...(filters?.date_from ? { date_from: filters.date_from } : {}),
        ...(filters?.date_to ? { date_to: filters.date_to } : {}),
      };

      const [listResult, summaryResult] = await Promise.allSettled([
        monitoringKelasAPI.getClassFraudAssessments(classId, params),
        monitoringKelasAPI.getClassFraudSummary(classId, params),
      ]);

      if (listResult.status !== 'fulfilled') {
        throw listResult.reason;
      }

      const listResponse = listResult.value;
      const listPayload = listResponse?.data?.data?.assessments
        ?? listResponse?.data?.assessments
        ?? listResponse?.data?.data
        ?? listResponse?.data;

      const normalizedPaginator = normalizePaginatorPayload(listPayload, perPage);
      setFraudRows(normalizedPaginator.rows);
      setFraudPagination(normalizedPaginator.meta);

      if (summaryResult.status === 'fulfilled') {
        const summaryResponse = summaryResult.value;
        const summaryPayload = summaryResponse?.data?.data || {};
        setFraudSummary(summaryPayload?.summary || null);
        setFraudConfig(summaryPayload?.config || null);
      } else {
        setFraudSummary(null);
        setFraudConfig(null);
        setFraudError('Ringkasan fraud gagal dimuat, data assessment tetap ditampilkan.');
      }
    } catch (error) {
      setFraudError(parseErrorMessage(error, 'Gagal memuat fraud monitoring'));
      setFraudRows([]);
      setFraudSummary(null);
      setFraudConfig(null);
      setFraudPagination({
        ...DEFAULT_PAGINATION,
        current_page: page,
        per_page: perPage,
      });
    } finally {
      setFraudLoading(false);
    }
  }, [fraudFilters, fraudPagination.current_page, fraudPagination.per_page, selectedClassId]);

  const loadSecurity = useCallback(async (
    classId = selectedClassId,
    filters = securityFilters,
    page = securityPagination.current_page,
    perPage = securityPagination.per_page
  ) => {
    if (!classId) {
      return;
    }

    setSecurityLoading(true);
    setSecurityError(null);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(filters?.issue_key ? { issue_key: filters.issue_key } : {}),
        ...(filters?.status ? { status: filters.status } : {}),
        ...(filters?.stage ? { stage: filters.stage } : {}),
        ...(filters?.attempt_type ? { attempt_type: filters.attempt_type } : {}),
        ...(filters?.date_from ? { date_from: filters.date_from } : {}),
        ...(filters?.date_to ? { date_to: filters.date_to } : {}),
      };

      const response = await monitoringKelasAPI.getClassSecurityEvents(classId, params);
      const payload = response?.data?.data || {};
      const normalizedPaginator = normalizePaginatorPayload(payload?.events, perPage);

      setSecurityRows(normalizedPaginator.rows);
      setSecurityPagination(normalizedPaginator.meta);
      setSecuritySummary(payload?.summary || null);
      setSecurityConfig(payload?.config || null);
    } catch (error) {
      setSecurityError(parseErrorMessage(error, 'Gagal memuat security monitoring'));
      setSecurityRows([]);
      setSecuritySummary(null);
      setSecurityConfig(null);
      setSecurityPagination({
        ...DEFAULT_PAGINATION,
        current_page: page,
        per_page: perPage,
      });
    } finally {
      setSecurityLoading(false);
    }
  }, [securityFilters, securityPagination.current_page, securityPagination.per_page, selectedClassId]);

  const loadSecurityStudents = useCallback(async (
    classId = selectedClassId,
    filters = securityFilters,
    scope = securityStudentScope,
    page = securityStudentPagination.current_page,
    perPage = securityStudentPagination.per_page
  ) => {
    if (!classId) {
      return;
    }

    setSecurityStudentsLoading(true);
    setSecurityStudentsError(null);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(scope ? { student_scope: scope } : {}),
        ...(filters?.issue_key ? { issue_key: filters.issue_key } : {}),
        ...(filters?.status ? { status: filters.status } : {}),
        ...(filters?.stage ? { stage: filters.stage } : {}),
        ...(filters?.attempt_type ? { attempt_type: filters.attempt_type } : {}),
        ...(filters?.date_from ? { date_from: filters.date_from } : {}),
        ...(filters?.date_to ? { date_to: filters.date_to } : {}),
      };

      const response = await monitoringKelasAPI.getClassSecurityStudents(classId, params);
      const payload = response?.data?.data || {};
      const normalizedPaginator = normalizePaginatorPayload({
        data: Array.isArray(payload?.students) ? payload.students : [],
        current_page: payload?.meta?.current_page,
        last_page: payload?.meta?.last_page,
        per_page: payload?.meta?.per_page,
        total: payload?.meta?.total,
        from: payload?.meta?.from,
        to: payload?.meta?.to,
      }, perPage);

      setSecurityStudentRows(normalizedPaginator.rows);
      setSecurityStudentPagination(normalizedPaginator.meta);
      setSecurityStudentSummary(payload?.summary || null);
    } catch (error) {
      setSecurityStudentsError(parseErrorMessage(error, 'Gagal memuat ringkasan keamanan per siswa'));
      setSecurityStudentRows([]);
      setSecurityStudentSummary(null);
      setSecurityStudentPagination({
        ...DEFAULT_PAGINATION,
        current_page: page,
        per_page: perPage,
      });
    } finally {
      setSecurityStudentsLoading(false);
    }
  }, [
    securityFilters,
    securityStudentScope,
    securityStudentPagination.current_page,
    securityStudentPagination.per_page,
    selectedClassId,
  ]);

  const loadSecurityCases = useCallback(async (
    classId = selectedClassId,
    filters = securityCaseFilters,
    page = securityCasePagination.current_page,
    perPage = securityCasePagination.per_page
  ) => {
    if (!classId) {
      return;
    }

    setSecurityCasesLoading(true);
    setSecurityCasesError(null);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(filters?.case_scope ? { case_scope: filters.case_scope } : {}),
        ...(filters?.status ? { status: filters.status } : {}),
        ...(filters?.priority ? { priority: filters.priority } : {}),
        ...(filters?.search ? { search: filters.search } : {}),
      };

      const response = await monitoringKelasAPI.getClassSecurityCases(classId, params);
      const payload = response?.data?.data?.cases
        ?? response?.data?.cases
        ?? response?.data?.data
        ?? response?.data;
      const normalizedPaginator = normalizePaginatorPayload(payload, perPage);

      setSecurityCaseRows(normalizedPaginator.rows);
      setSecurityCasePagination(normalizedPaginator.meta);
    } catch (error) {
      setSecurityCasesError(parseErrorMessage(error, 'Gagal memuat kasus tindak lanjut keamanan'));
      setSecurityCaseRows([]);
      setSecurityCasePagination({
        ...DEFAULT_PAGINATION,
        current_page: page,
        per_page: perPage,
      });
    } finally {
      setSecurityCasesLoading(false);
    }
  }, [
    securityCaseFilters,
    securityCasePagination.current_page,
    securityCasePagination.per_page,
    selectedClassId,
  ]);

  const refreshSecurityFollowUp = useCallback(async (classId = selectedClassId) => {
    if (!classId) {
      return;
    }

    await Promise.all([
      loadSecurity(classId, securityFilters, securityPagination.current_page, securityPagination.per_page),
      loadSecurityStudents(classId, securityFilters, securityStudentScope, securityStudentPagination.current_page, securityStudentPagination.per_page),
      loadSecurityCases(classId, securityCaseFilters, securityCasePagination.current_page, securityCasePagination.per_page),
    ]);
  }, [
    loadSecurity,
    loadSecurityCases,
    loadSecurityStudents,
    securityCaseFilters,
    securityCasePagination.current_page,
    securityCasePagination.per_page,
    securityFilters,
    securityPagination.current_page,
    securityPagination.per_page,
    securityStudentScope,
    securityStudentPagination.current_page,
    securityStudentPagination.per_page,
    selectedClassId,
  ]);

  const createSecurityCase = useCallback(async (payload) => {
    if (!selectedClassId) {
      throw new Error('Kelas belum dipilih');
    }

    const response = await monitoringKelasAPI.createClassSecurityCase(selectedClassId, payload);
    await refreshSecurityFollowUp(selectedClassId);

    return response?.data?.data || response?.data;
  }, [refreshSecurityFollowUp, selectedClassId]);

  const resolveSecurityCase = useCallback(async (caseId, payload) => {
    if (!selectedClassId) {
      throw new Error('Kelas belum dipilih');
    }

    const response = await monitoringKelasAPI.resolveClassSecurityCase(selectedClassId, caseId, payload);
    await refreshSecurityFollowUp(selectedClassId);

    return response?.data?.data || response?.data;
  }, [refreshSecurityFollowUp, selectedClassId]);

  const reopenSecurityCase = useCallback(async (caseId) => {
    if (!selectedClassId) {
      throw new Error('Kelas belum dipilih');
    }

    const response = await monitoringKelasAPI.reopenClassSecurityCase(selectedClassId, caseId);
    await refreshSecurityFollowUp(selectedClassId);

    return response?.data?.data || response?.data;
  }, [refreshSecurityFollowUp, selectedClassId]);

  const addSecurityCaseNote = useCallback(async (caseId, payload) => {
    if (!selectedClassId) {
      throw new Error('Kelas belum dipilih');
    }

    const response = await monitoringKelasAPI.addClassSecurityCaseNote(selectedClassId, caseId, payload);
    await refreshSecurityFollowUp(selectedClassId);

    return response?.data?.data || response?.data;
  }, [refreshSecurityFollowUp, selectedClassId]);

  const uploadSecurityCaseEvidence = useCallback(async (caseId, formData) => {
    if (!selectedClassId) {
      throw new Error('Kelas belum dipilih');
    }

    const response = await monitoringKelasAPI.uploadClassSecurityCaseEvidence(selectedClassId, caseId, formData);
    await refreshSecurityFollowUp(selectedClassId);

    return response?.data?.data || response?.data;
  }, [refreshSecurityFollowUp, selectedClassId]);

  const loadLeaves = useCallback(async (
    classId = selectedClassId,
    filters = leaveFilters,
    page = leavePagination.current_page,
    perPage = leavePagination.per_page
  ) => {
    if (!classId) {
      return;
    }

    setLeaveLoading(true);
    setLeaveError(null);
    try {
      const params = {
        page,
        per_page: perPage,
        ...(filters?.status ? { status: filters.status } : {}),
        ...(filters?.jenis_izin ? { jenis_izin: filters.jenis_izin } : {}),
        ...(filters?.search ? { search: filters.search } : {}),
        ...(filters?.date_from ? { date_from: filters.date_from } : {}),
        ...(filters?.date_to ? { date_to: filters.date_to } : {}),
      };

      const response = await monitoringKelasAPI.getClassLeaves(classId, params);
      const payload = response?.data;

      if (Array.isArray(payload)) {
        setLeaveRows(payload);
        setLeavePagination({
          current_page: 1,
          last_page: 1,
          per_page: payload.length || perPage,
          total: payload.length,
          from: payload.length ? 1 : 0,
          to: payload.length,
        });
      } else {
        const rows = Array.isArray(payload?.data) ? payload.data : [];
        const meta = payload?.meta && typeof payload.meta === 'object'
          ? payload.meta
          : {};

        const normalizedPaginator = normalizePaginatorPayload({
          data: rows,
          current_page: meta.current_page,
          last_page: meta.last_page,
          per_page: meta.per_page,
          total: meta.total,
          from: meta.from,
          to: meta.to,
        }, perPage);

        setLeaveRows(normalizedPaginator.rows);
        setLeavePagination(normalizedPaginator.meta);
      }
    } catch (error) {
      setLeaveError(parseErrorMessage(error, 'Gagal memuat data izin kelas'));
      setLeaveRows([]);
      setLeavePagination({
        ...DEFAULT_PAGINATION,
        current_page: page,
        per_page: perPage,
      });
    } finally {
      setLeaveLoading(false);
    }
  }, [leaveFilters, leavePagination.current_page, leavePagination.per_page, selectedClassId]);

  const refreshAllForSelectedClass = useCallback(async () => {
    if (!selectedClassId) {
      return false;
    }

    setRefreshing(true);
    try {
      await Promise.all([
        loadOverview(selectedClassId, statisticsMonth, attendanceDate),
        loadFraud(selectedClassId, fraudFilters, fraudPagination.current_page, fraudPagination.per_page),
        loadSecurity(selectedClassId, securityFilters, securityPagination.current_page, securityPagination.per_page),
        loadSecurityStudents(selectedClassId, securityFilters, securityStudentScope, securityStudentPagination.current_page, securityStudentPagination.per_page),
        loadSecurityCases(selectedClassId, securityCaseFilters, securityCasePagination.current_page, securityCasePagination.per_page),
        loadLeaves(selectedClassId, leaveFilters, leavePagination.current_page, leavePagination.per_page),
      ]);
      return true;
    } finally {
      setRefreshing(false);
    }
  }, [
    attendanceDate,
    fraudFilters,
    fraudPagination.current_page,
    fraudPagination.per_page,
    leaveFilters,
    leavePagination.current_page,
    leavePagination.per_page,
    loadFraud,
    loadLeaves,
    loadOverview,
    loadSecurity,
    loadSecurityCases,
    loadSecurityStudents,
    securityCaseFilters,
    securityCasePagination.current_page,
    securityCasePagination.per_page,
    securityFilters,
    securityPagination.current_page,
    securityPagination.per_page,
    securityStudentScope,
    securityStudentPagination.current_page,
    securityStudentPagination.per_page,
    selectedClassId,
    statisticsMonth,
  ]);

  useEffect(() => {
    loadClasses();
  }, [loadClasses]);

  useEffect(() => {
    if (!isServerClockSynced || !isDateInputValue(serverDate)) {
      return;
    }

    if (
      !attendanceDateTouchedRef.current
      && (attendanceDate === '' || isSuspiciousDateInput(attendanceDate, serverDate))
    ) {
      setAttendanceDateState(serverDate);
    }

    const serverMonth = serverDate.slice(0, 7);
    if (
      !statisticsMonthTouchedRef.current
      && (statisticsMonth === '' || isSuspiciousMonthInput(statisticsMonth, serverDate))
    ) {
      setStatisticsMonthState(serverMonth);
    }
  }, [attendanceDate, isServerClockSynced, serverDate, statisticsMonth]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadOverview(selectedClassId, statisticsMonth, attendanceDate);
  }, [attendanceDate, loadOverview, selectedClassId, statisticsMonth]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadFraud(selectedClassId, fraudFilters, fraudPagination.current_page, fraudPagination.per_page);
  }, [fraudFilters, fraudPagination.current_page, fraudPagination.per_page, loadFraud, selectedClassId]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadSecurity(selectedClassId, securityFilters, securityPagination.current_page, securityPagination.per_page);
  }, [loadSecurity, securityFilters, securityPagination.current_page, securityPagination.per_page, selectedClassId]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadSecurityStudents(selectedClassId, securityFilters, securityStudentScope, securityStudentPagination.current_page, securityStudentPagination.per_page);
  }, [
    loadSecurityStudents,
    securityFilters,
    securityStudentScope,
    securityStudentPagination.current_page,
    securityStudentPagination.per_page,
    selectedClassId,
  ]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadSecurityCases(selectedClassId, securityCaseFilters, securityCasePagination.current_page, securityCasePagination.per_page);
  }, [
    loadSecurityCases,
    securityCaseFilters,
    securityCasePagination.current_page,
    securityCasePagination.per_page,
    selectedClassId,
  ]);

  useEffect(() => {
    if (!selectedClassId) {
      return;
    }
    loadLeaves(selectedClassId, leaveFilters, leavePagination.current_page, leavePagination.per_page);
  }, [leaveFilters, leavePagination.current_page, leavePagination.per_page, loadLeaves, selectedClassId]);

  return {
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
    loadSecurityStudents,
    loadSecurityCases,
    createSecurityCase,
    resolveSecurityCase,
    reopenSecurityCase,
    addSecurityCaseNote,
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
  };
};

export default useMonitoringKelas;

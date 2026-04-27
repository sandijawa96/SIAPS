import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useSnackbar } from 'notistack';
import { useAuth } from './useAuth';
import { getApiUrl } from '../config/api';
import { gpsTrackingService } from '../services/gpsTrackingService';
import { useServerClock } from './useServerClock';
import { normalizeLocationRows } from '../utils/locationGeofence';
import {
  getServerDateString,
  getServerNowDate,
  getServerNowEpochMs,
  syncServerClockFromMeta,
} from '../services/serverClock';

const EMPTY_SCHOOL_WINDOW = {
  jamMasuk: null,
  jamPulang: null,
  hariKerja: [],
};

const DEFAULT_PAGE_SIZE = 100;
const MIN_PAGE_SIZE = 25;
const MAX_PAGE_SIZE = 200;
const PAGE_SIZE_STEP = 25;
const PAGE_SIZE_OPTIONS = [25, 50, 100, 150, 200];
const PRIORITY_QUEUE_PAGE_SIZE = 8;
const MAP_RENDER_LIMIT = 120;
const DEFAULT_PAGINATION = {
  page: 1,
  perPage: DEFAULT_PAGE_SIZE,
  total: 0,
  lastPage: 1,
  from: 0,
  to: 0,
};

const normalizeDayToken = (value = '') =>
  String(value)
    .trim()
    .toLowerCase()
    .replace(/['`’\s]+/g, '');

const getDayAliases = (dayIndex) => {
  switch (dayIndex) {
    case 1:
      return ['senin', 'monday'];
    case 2:
      return ['selasa', 'tuesday'];
    case 3:
      return ['rabu', 'wednesday'];
    case 4:
      return ['kamis', 'thursday'];
    case 5:
      return ['jumat', "jum'at", 'friday'];
    case 6:
      return ['sabtu', 'saturday'];
    case 0:
      return ['minggu', 'sunday'];
    default:
      return [];
  }
};

const clampPageSize = (value) => {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) {
    return DEFAULT_PAGE_SIZE;
  }

  const rounded = Math.round(numericValue / PAGE_SIZE_STEP) * PAGE_SIZE_STEP;
  return Math.min(MAX_PAGE_SIZE, Math.max(MIN_PAGE_SIZE, rounded));
};

const normalizeWorkingDays = (value) => {
  if (Array.isArray(value)) {
    return value.map((item) => String(item).trim()).filter(Boolean);
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) {
      return [];
    }

    try {
      const decoded = JSON.parse(trimmed);
      if (Array.isArray(decoded)) {
        return normalizeWorkingDays(decoded);
      }
    } catch (error) {
      // Ignore JSON parse issue, continue with CSV fallback.
    }

    const csv = trimmed.split(',').map((item) => item.trim()).filter(Boolean);
    return csv.length > 0 ? csv : [];
  }

  return [];
};

const parseMinuteOfDay = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) return null;

  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (
    Number.isNaN(hour) ||
    Number.isNaN(minute) ||
    hour < 0 ||
    hour > 23 ||
    minute < 0 ||
    minute > 59
  ) {
    return null;
  }

  return (hour * 60) + minute;
};

const isWithinSchoolWindow = (windowConfig, now) => {
  if (!windowConfig) {
    return false;
  }

  const startMinute = parseMinuteOfDay(windowConfig?.jamMasuk);
  const endMinute = parseMinuteOfDay(windowConfig?.jamPulang);
  const currentMinute = (now.getHours() * 60) + now.getMinutes();

  const normalizedDays = (windowConfig?.hariKerja || [])
    .map((day) => normalizeDayToken(day))
    .filter(Boolean);

  const dayAliases = getDayAliases(now.getDay()).map((day) => normalizeDayToken(day));
  const isWorkingDay = normalizedDays.length === 0
    ? true
    : dayAliases.some((alias) => normalizedDays.includes(alias));

  if (!isWorkingDay || startMinute === null || endMinute === null) {
    return false;
  }

  if (endMinute < startMinute) {
    return currentMinute >= startMinute || currentMinute <= endMinute;
  }

  return currentMinute >= startMinute && currentMinute <= endMinute;
};

const DEFAULT_TRACKING_SETTINGS = {
  refresh: {
    interval: 30,
    autoRefresh: true,
    refreshOnFocus: true
  },
  map: {
    defaultZoom: 15,
    theme: 'default',
    showTrafficLayer: false,
    autoCenter: true
  },
  display: {
    showInactiveStudents: true,
    showLastLocation: true,
    showAccuracyCircle: true,
    maxStudentsInList: DEFAULT_PAGE_SIZE
  },
  notifications: {
    enabled: true,
    studentOutOfArea: true,
    connectionLost: true
  }
};

const SESSION_EXPIRY_TICK_MS = 15000;

const EMPTY_STATS = {
  total: 0,
  tracked: 0,
  fresh: 0,
  active: 0,
  stale: 0,
  gpsDisabled: 0,
  trackingDisabled: 0,
  outsideSchedule: 0,
  insideArea: 0,
  outsideArea: 0,
  noData: 0,
  poorGps: 0,
  moderateGps: 0
};

const EMPTY_PRIORITY_QUEUES = {
  gpsDisabled: [],
  stale: [],
  outsideArea: []
};

const DEFAULT_HISTORY_POLICY = {
  enabled: true,
  minDistanceMeters: 20,
  retentionDays: 30,
  cleanupTime: '02:15',
  currentStoreRebuildTime: '00:10',
  readCurrentStoreEnabled: true,
  persistIdleSeconds: 300,
  samplingMode: 'movement_or_change_or_heartbeat',
  source: 'config',
};

const DEFAULT_DATA_SOURCES = {
  list: 'request_pipeline',
  summary: 'request_pipeline',
  groupedSummary: 'request_pipeline',
  priorityQueues: 'request_pipeline',
};

const isTrackingSessionActiveNow = (expiresAt, now = getServerNowEpochMs()) => {
  if (!expiresAt) {
    return false;
  }

  const expiresAtMs = Date.parse(expiresAt);
  if (Number.isNaN(expiresAtMs)) {
    return false;
  }

  return expiresAtMs > now;
};

const normalizeSettings = (raw = {}) => ({
  refresh: {
    ...DEFAULT_TRACKING_SETTINGS.refresh,
    ...(raw?.refresh || {})
  },
  map: {
    ...DEFAULT_TRACKING_SETTINGS.map,
    ...(raw?.map || {})
  },
  display: {
    ...DEFAULT_TRACKING_SETTINGS.display,
    ...(raw?.display || {}),
    maxStudentsInList: clampPageSize(
      raw?.display?.maxStudentsInList ?? DEFAULT_TRACKING_SETTINGS.display.maxStudentsInList
    )
  },
  notifications: {
    ...DEFAULT_TRACKING_SETTINGS.notifications,
    ...(raw?.notifications || {})
  }
});

const normalizeHistoryPolicy = (raw = {}) => ({
  enabled: raw?.enabled === undefined
    ? DEFAULT_HISTORY_POLICY.enabled
    : Boolean(raw?.enabled),
  minDistanceMeters: Number(raw?.min_distance_meters ?? raw?.live_tracking_min_distance_meters ?? DEFAULT_HISTORY_POLICY.minDistanceMeters),
  retentionDays: Number(raw?.retention_days ?? raw?.live_tracking_retention_days ?? DEFAULT_HISTORY_POLICY.retentionDays),
  cleanupTime: String(raw?.cleanup_time ?? raw?.live_tracking_cleanup_time ?? DEFAULT_HISTORY_POLICY.cleanupTime),
  currentStoreRebuildTime: String(raw?.current_store_rebuild_time ?? DEFAULT_HISTORY_POLICY.currentStoreRebuildTime),
  readCurrentStoreEnabled: raw?.read_current_store_enabled === undefined
    ? DEFAULT_HISTORY_POLICY.readCurrentStoreEnabled
    : Boolean(raw?.read_current_store_enabled),
  persistIdleSeconds: Number(raw?.persist_idle_seconds ?? DEFAULT_HISTORY_POLICY.persistIdleSeconds),
  samplingMode: String(raw?.sampling_mode ?? DEFAULT_HISTORY_POLICY.samplingMode),
  source: String(raw?.source ?? DEFAULT_HISTORY_POLICY.source),
});

const normalizeDataSources = (raw = {}) => ({
  list: String(raw?.list_source ?? DEFAULT_DATA_SOURCES.list),
  summary: String(raw?.summary_source ?? DEFAULT_DATA_SOURCES.summary),
  groupedSummary: String(raw?.group_summary_source ?? DEFAULT_DATA_SOURCES.groupedSummary),
  priorityQueues: String(raw?.priority_queue_source ?? DEFAULT_DATA_SOURCES.priorityQueues),
});

const normalizePriorityQueues = (rawQueues, currentServerNowMs, transformTrackingRow) => ({
  gpsDisabled: Array.isArray(rawQueues?.gps_disabled)
    ? rawQueues.gps_disabled.map((item) => transformTrackingRow(item, currentServerNowMs))
    : [],
  stale: Array.isArray(rawQueues?.stale)
    ? rawQueues.stale.map((item) => transformTrackingRow(item, currentServerNowMs))
    : [],
  outsideArea: Array.isArray(rawQueues?.outside_area)
    ? rawQueues.outside_area.map((item) => transformTrackingRow(item, currentServerNowMs))
    : [],
});

export const useTracking = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTrackingSessions, setActiveTrackingSessions] = useState([]);
  const [activeSessionsLoading, setActiveSessionsLoading] = useState(false);
  const [exportLoading, setExportLoading] = useState(false);
  const [exportError, setExportError] = useState(null);
  const [priorityQueues, setPriorityQueues] = useState(EMPTY_PRIORITY_QUEUES);
  const [priorityQueuesLoading, setPriorityQueuesLoading] = useState(false);
  const [pagination, setPagination] = useState(DEFAULT_PAGINATION);
  const [classSummary, setClassSummary] = useState([]);
  const [levelSummary, setLevelSummary] = useState([]);
  const [homeroomSummary, setHomeroomSummary] = useState([]);
  const [historyPolicy, setHistoryPolicy] = useState(DEFAULT_HISTORY_POLICY);
  const [historyPolicyLoading, setHistoryPolicyLoading] = useState(true);
  const [historyPolicySaving, setHistoryPolicySaving] = useState(false);
  const [dataSources, setDataSources] = useState(DEFAULT_DATA_SOURCES);

  const [settings, setSettings] = useState(() => {
    try {
      const savedSettings = localStorage.getItem('trackingSettings');
      if (!savedSettings) {
        return normalizeSettings();
      }

      return normalizeSettings(JSON.parse(savedSettings));
    } catch (error) {
      console.warn('Failed to parse tracking settings from localStorage. Using defaults.', error);
      return normalizeSettings();
    }
  });

  const [isSchoolHours, setIsSchoolHours] = useState(false);
  const [schoolHoursWindow, setSchoolHoursWindow] = useState(EMPTY_SCHOOL_WINDOW);
  const [attendanceLocations, setAttendanceLocations] = useState([]);
  const [selectedStudent, setSelectedStudent] = useState(null);
  const [filters, setFilters] = useState({
    status: 'all',
    search: '',
    class: '',
    tingkat: '',
    wali_kelas_id: '',
    area: 'all'
  });
  const [stats, setStats] = useState(EMPTY_STATS);

  const { baseEpochMs, syncedAtMs, timezone } = useServerClock();
  const serverClock = useMemo(() => ({
    baseEpochMs,
    syncedAtMs,
    timezone,
  }), [baseEpochMs, syncedAtMs, timezone]);

  const trackingIntervalRef = useRef(null);
  const schoolHoursIntervalRef = useRef(null);
  const isInitializedRef = useRef(false);
  const lastFetchTimeRef = useRef(0);
  const settingsRef = useRef(settings);
  const schoolHoursWindowRef = useRef(EMPTY_SCHOOL_WINDOW);
  const previousStudentsSnapshotRef = useRef(new Map());
  const hasPrimedStudentsSnapshotRef = useRef(false);

  const { token } = useAuth();
  const { enqueueSnackbar } = useSnackbar();

  useEffect(() => {
    settingsRef.current = settings;
  }, [settings]);

  useEffect(() => {
    schoolHoursWindowRef.current = schoolHoursWindow;
  }, [schoolHoursWindow]);

  const updateServerClockFromMeta = useCallback((meta) => {
    syncServerClockFromMeta(meta);
  }, []);

  const getCurrentServerEpochMs = useCallback(() => getServerNowEpochMs(), []);
  const getCurrentServerDate = useCallback(() => getServerNowDate(), []);

  const buildCurrentTrackingParams = useCallback((overrides = {}) => {
    const params = new URLSearchParams();
    const merged = {
      search: filters.search,
      status: filters.status,
      area: filters.area,
      class: filters.class,
      tingkat: filters.tingkat,
      wali_kelas_id: filters.wali_kelas_id,
      ...overrides,
    };

    if (merged.search) params.append('search', String(merged.search).trim());
    if (merged.status && merged.status !== 'all') params.append('status', merged.status);
    if (merged.area && merged.area !== 'all') params.append('area', merged.area);
    if (merged.class) params.append('class', String(merged.class).trim());
    if (merged.tingkat) params.append('tingkat', String(merged.tingkat).trim());
    if (merged.wali_kelas_id) params.append('wali_kelas_id', String(merged.wali_kelas_id).trim());
    if (merged.page) params.append('page', String(merged.page));
    if (merged.per_page) params.append('per_page', String(merged.per_page));
    if (merged.include_class_summary) params.append('include_class_summary', '1');
    if (merged.include_priority_queues) params.append('include_priority_queues', '1');
    if (merged.priority_queue_limit) params.append('priority_queue_limit', String(merged.priority_queue_limit));

    return params;
  }, [filters.area, filters.class, filters.search, filters.status, filters.tingkat, filters.wali_kelas_id]);

  const transformTrackingRow = useCallback((tracking, currentServerNowMs) => {
    const hasRawTrackingData = Boolean(tracking.has_tracking_data)
      || (tracking.latitude !== null && tracking.longitude !== null);
    const trackingSessionActiveRaw = Boolean(tracking.tracking_session_active);
    const trackingSessionExpiresAt = tracking.tracking_session_expires_at || null;
    const trackingSessionActive = trackingSessionActiveRaw && isTrackingSessionActiveNow(
      trackingSessionExpiresAt,
      currentServerNowMs
    );
    const trackingStatus = tracking.tracking_status || (hasRawTrackingData ? 'active' : 'no_data');
    const hasTrackingData = hasRawTrackingData;

    return {
      id: tracking.user_id,
      name: tracking.user?.nama_lengkap || 'Unknown',
      class: tracking.user?.kelas || 'N/A',
      level: tracking.user?.tingkat || 'N/A',
      homeroomTeacherId: tracking.user?.wali_kelas_id || null,
      homeroomTeacherName: tracking.user?.wali_kelas || 'Belum ditentukan',
      email: tracking.user?.email || '',
      status: trackingStatus,
      lastUpdate: hasTrackingData ? (tracking.tracked_at || null) : null,
      location: {
        lat: hasTrackingData && tracking.latitude !== null ? parseFloat(tracking.latitude) : null,
        lng: hasTrackingData && tracking.longitude !== null ? parseFloat(tracking.longitude) : null,
        address: hasTrackingData
          ? (tracking.location_name || tracking.current_location?.nama_lokasi || 'Unknown Location')
          : 'No tracking data',
        accuracy: hasTrackingData ? (tracking.accuracy || 0) : 0,
        speed: hasTrackingData ? (tracking.speed || 0) : 0,
        heading: hasTrackingData ? (tracking.heading || 0) : 0
      },
      isInSchoolArea: hasTrackingData ? tracking.is_in_school_area : false,
      deviceInfo: tracking.device_info || {},
      deviceSource: tracking.device_source || tracking.device_info?.source || 'unknown',
      gpsQualityStatus: tracking.gps_quality_status || 'unknown',
      ipAddress: tracking.ip_address || '',
      trackedAt: tracking.tracked_at,
      hasTrackingData,
      hasHistoricalTracking: hasRawTrackingData,
      trackingSessionActive,
      trackingSessionExpiresAt: trackingSessionActiveRaw ? trackingSessionExpiresAt : null,
      trackingSessionExpiresAtMs: trackingSessionActive ? Date.parse(trackingSessionExpiresAt) : null,
      trackingStatus,
      trackingStatusReason: tracking.tracking_status_reason || null,
      currentLocation: tracking.current_location || null,
      nearestLocation: tracking.nearest_location || null,
      distanceToNearest: tracking.distance_to_nearest || null
    };
  }, []);

  const fetchSchoolHoursWindow = useCallback(async () => {
    if (!token) {
      return;
    }

    try {
      const response = await fetch(getApiUrl('/lokasi-gps/attendance-schema?context=live_tracking_monitor'), {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        cache: 'no-store',
        credentials: 'include',
      });

      if (!response.ok) {
        return;
      }

      const result = await response.json();
      updateServerClockFromMeta(result?.meta);
      const payload = result?.data ?? {};
      const candidateSettings = payload?.settings ?? payload ?? {};
      const candidateWorkingHours = payload?.working_hours ?? candidateSettings?.working_hours ?? {};
      const candidateLocations = normalizeLocationRows(payload?.locations ?? candidateSettings?.locations ?? []);

      const jamMasuk = String(
        candidateWorkingHours?.jam_masuk ??
        candidateSettings?.jam_masuk ??
        candidateSettings?.siswa_jam_masuk ??
        ''
      ).trim();
      const jamPulang = String(
        candidateWorkingHours?.jam_pulang ??
        candidateSettings?.jam_pulang ??
        candidateSettings?.siswa_jam_pulang ??
        ''
      ).trim();
      const hariKerja = normalizeWorkingDays(
        candidateSettings?.hari_kerja ?? candidateSettings?.hariKerja
      );

      const nextWindow = {
        jamMasuk: jamMasuk || null,
        jamPulang: jamPulang || null,
        hariKerja,
      };

      schoolHoursWindowRef.current = nextWindow;
      setSchoolHoursWindow(nextWindow);
      setAttendanceLocations(candidateLocations);
      setIsSchoolHours(isWithinSchoolWindow(nextWindow, getCurrentServerDate()));
    } catch (error) {
      // Keep existing/default window on fetch failure.
    }
  }, [getCurrentServerDate, token, updateServerClockFromMeta]);

  const checkSchoolHours = useCallback(() => {
    const now = getCurrentServerDate();
    setIsSchoolHours(isWithinSchoolWindow(schoolHoursWindowRef.current, now));
  }, [getCurrentServerDate]);

  const fetchCurrentTracking = useCallback(async (options = {}) => {
    const { ignoreThrottle = false } = options;

    if (!token) return false;

    const now = getCurrentServerEpochMs();
    if (!ignoreThrottle && now - lastFetchTimeRef.current < 5000) {
      return true;
    }
    lastFetchTimeRef.current = now;

    try {
      setLoading(true);
      setPriorityQueuesLoading(true);
      setError(null);

      const requestedPerPage = clampPageSize(
        pagination.perPage || settingsRef.current?.display?.maxStudentsInList || DEFAULT_PAGE_SIZE
      );
      const requestedPage = Math.max(1, Number(pagination.page || 1));

      const params = buildCurrentTrackingParams({
        page: requestedPage,
        per_page: requestedPerPage,
        include_class_summary: true,
        include_priority_queues: true,
        priority_queue_limit: PRIORITY_QUEUE_PAGE_SIZE,
      });

      const response = await fetch(getApiUrl(`/live-tracking/current?${params.toString()}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        if (response.status === 404 || response.status === 422) {
          setStudents([]);
          setStats(EMPTY_STATS);
          setClassSummary([]);
          setLevelSummary([]);
          setHomeroomSummary([]);
          setHistoryPolicy(DEFAULT_HISTORY_POLICY);
          setDataSources(DEFAULT_DATA_SOURCES);
          setHistoryPolicyLoading(false);
          setPriorityQueues(EMPTY_PRIORITY_QUEUES);
          setPagination((previous) => ({
            ...DEFAULT_PAGINATION,
            page: 1,
            perPage: previous.perPage || requestedPerPage,
          }));
          setError(null);
          return true;
        }
        throw new Error(`HTTP ${response.status}: Failed to fetch tracking data`);
      }

      const data = await response.json();
      updateServerClockFromMeta(data?.meta);
      const currentServerNowMs = getCurrentServerEpochMs();

      if (data.success && Array.isArray(data.data)) {
        const summary = data?.meta?.summary || null;
        const nextClassSummary = Array.isArray(data?.meta?.class_summary)
          ? data.meta.class_summary
          : [];
        const nextLevelSummary = Array.isArray(data?.meta?.level_summary)
          ? data.meta.level_summary
          : [];
        const nextHomeroomSummary = Array.isArray(data?.meta?.homeroom_summary)
          ? data.meta.homeroom_summary
          : [];
        const nextHistoryPolicy = normalizeHistoryPolicy(data?.meta?.history_policy || {});
        const nextDataSources = normalizeDataSources(data?.meta || {});
        const nextPriorityQueues = normalizePriorityQueues(
          data?.meta?.priority_queues || {},
          currentServerNowMs,
          transformTrackingRow
        );
        const transformedStudents = data.data.map((tracking) => transformTrackingRow(tracking, currentServerNowMs));
        const notificationSettings = settingsRef.current?.notifications || {};

        if (hasPrimedStudentsSnapshotRef.current && notificationSettings.enabled) {
          transformedStudents.forEach((student) => {
            const previousState = previousStudentsSnapshotRef.current.get(student.id);
            if (!previousState) {
              return;
            }

            if (
              notificationSettings.studentOutOfArea &&
              previousState.hasTrackingData &&
              previousState.isInSchoolArea &&
              student.hasTrackingData &&
              student.status === 'outside_area'
            ) {
              enqueueSnackbar(`${student.name} terdeteksi keluar area sekolah`, { variant: 'warning' });
            }

            if (
              notificationSettings.connectionLost &&
              previousState.hasTrackingData &&
              !student.hasTrackingData
            ) {
              enqueueSnackbar(`Koneksi tracking ${student.name} tidak terdeteksi`, { variant: 'info' });
            }
          });
        }

        previousStudentsSnapshotRef.current = new Map(
          transformedStudents.map((student) => ([
            student.id,
            {
              hasTrackingData: student.hasTrackingData,
              isInSchoolArea: student.isInSchoolArea
            }
          ]))
        );
        hasPrimedStudentsSnapshotRef.current = true;

        const metaPagination = data?.meta?.pagination || {};
        const nextPagination = {
          page: Number(metaPagination.page || requestedPage),
          perPage: clampPageSize(metaPagination.per_page || requestedPerPage),
          total: Number(metaPagination.total || transformedStudents.length),
          lastPage: Number(metaPagination.last_page || 1),
          from: Number(metaPagination.from || (transformedStudents.length > 0 ? 1 : 0)),
          to: Number(metaPagination.to || transformedStudents.length),
        };

        const activeStudents = transformedStudents.filter((student) => student.status === 'active');
        const staleStudents = transformedStudents.filter((student) => student.status === 'stale');
        const gpsDisabledStudents = transformedStudents.filter((student) => student.status === 'gps_disabled');
        const outsideScheduleStudents = transformedStudents.filter((student) => student.status === 'outside_schedule');
        const trackingDisabledStudents = transformedStudents.filter((student) => student.status === 'tracking_disabled');
        const noDataStudents = transformedStudents.filter((student) => student.status === 'no_data' || student.status === 'no-data');
        const insideAreaStudents = transformedStudents.filter((student) => student.isInSchoolArea && student.hasTrackingData);
        const outsideAreaStudents = transformedStudents.filter((student) => student.status === 'outside_area');

        const nextStats = summary
          ? {
            total: summary.total || 0,
            tracked: (summary.total || 0) - (summary.no_data || 0),
            fresh: (summary.active || 0) + (summary.outside_area || 0),
            active: summary.active || 0,
            stale: summary.stale || 0,
            gpsDisabled: summary.gps_disabled || 0,
            trackingDisabled: summary.tracking_disabled || 0,
            outsideSchedule: summary.outside_schedule || 0,
            noData: summary.no_data || 0,
            insideArea: summary.inside_area || 0,
            outsideArea: summary.outside_area || 0,
            poorGps: summary.poor_gps || 0,
            moderateGps: summary.moderate_gps || 0
          }
          : {
            total: transformedStudents.length,
            tracked: transformedStudents.filter((student) => student.hasTrackingData).length,
            fresh: transformedStudents.filter((student) => student.status === 'active' || student.status === 'outside_area').length,
            active: activeStudents.length,
            stale: staleStudents.length,
            gpsDisabled: gpsDisabledStudents.length,
            trackingDisabled: trackingDisabledStudents.length,
            outsideSchedule: outsideScheduleStudents.length,
            noData: noDataStudents.length,
            insideArea: insideAreaStudents.length,
            outsideArea: outsideAreaStudents.length,
            poorGps: transformedStudents.filter((student) => student.gpsQualityStatus === 'poor').length,
            moderateGps: transformedStudents.filter((student) => student.gpsQualityStatus === 'moderate').length
          };

        setStudents(transformedStudents);
        setStats(nextStats);
        setPagination(nextPagination);
        setClassSummary(nextClassSummary);
        setLevelSummary(nextLevelSummary);
        setHomeroomSummary(nextHomeroomSummary);
        setHistoryPolicy(nextHistoryPolicy);
        setDataSources(nextDataSources);
        setHistoryPolicyLoading(false);
        setPriorityQueues(nextPriorityQueues);
        setSelectedStudent((previousSelected) => {
          if (!previousSelected?.id) {
            return previousSelected;
          }

          return transformedStudents.find((student) => student.id === previousSelected.id) || previousSelected;
        });
        setError(null);
        return true;
      }

      setStudents([]);
      setStats(EMPTY_STATS);
      setClassSummary([]);
      setLevelSummary([]);
      setHomeroomSummary([]);
      setHistoryPolicy(DEFAULT_HISTORY_POLICY);
      setDataSources(DEFAULT_DATA_SOURCES);
      setHistoryPolicyLoading(false);
      setPriorityQueues(EMPTY_PRIORITY_QUEUES);
      setPagination((previous) => ({
        ...DEFAULT_PAGINATION,
        page: 1,
        perPage: previous.perPage || requestedPerPage,
      }));
      setError(null);
      previousStudentsSnapshotRef.current = new Map();
      hasPrimedStudentsSnapshotRef.current = true;
      return true;
    } catch (err) {
      console.error('Error fetching tracking data:', err);
      setError(err.message);
      enqueueSnackbar(err.message, { variant: 'error' });
      setStudents([]);
      setStats(EMPTY_STATS);
      setClassSummary([]);
      setLevelSummary([]);
      setHomeroomSummary([]);
      setPriorityQueues(EMPTY_PRIORITY_QUEUES);
      setDataSources(DEFAULT_DATA_SOURCES);
      setHistoryPolicyLoading(false);
      setPagination((previous) => ({
        ...DEFAULT_PAGINATION,
        page: 1,
        perPage: previous.perPage || DEFAULT_PAGE_SIZE,
      }));
      previousStudentsSnapshotRef.current = new Map();
      hasPrimedStudentsSnapshotRef.current = true;
      return false;
    } finally {
      setLoading(false);
      setPriorityQueuesLoading(false);
    }
  }, [buildCurrentTrackingParams, enqueueSnackbar, getCurrentServerEpochMs, pagination.page, pagination.perPage, token, transformTrackingRow, updateServerClockFromMeta]);

  const fetchStudentHistory = useCallback(async (userId, date = null) => {
    if (!token || !userId) return null;

    try {
      const params = new URLSearchParams();
      params.append('user_id', userId);
      if (date) {
        params.append('date', date);
      }

      const response = await fetch(getApiUrl(`/live-tracking/history?${params}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error('Failed to fetch student history');
      }

      const data = await response.json();
      return data.success ? data.data : null;
    } catch (err) {
      console.error('Error fetching student history:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return null;
    }
  }, [token, enqueueSnackbar]);

  const fetchTrackingHistoryMap = useCallback(async (userIds, options = {}) => {
    if (!token) return null;

    const normalizedUserIds = Array.from(new Set(
      (Array.isArray(userIds) ? userIds : [userIds])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0)
    )).slice(0, 5);

    if (normalizedUserIds.length === 0) {
      return null;
    }

    try {
      const params = new URLSearchParams();
      params.append('user_ids', normalizedUserIds.join(','));

      if (options.date) {
        params.append('date', String(options.date));
      }

      if (options.startTime) {
        params.append('start_time', String(options.startTime));
      }

      if (options.endTime) {
        params.append('end_time', String(options.endTime));
      }

      const response = await fetch(getApiUrl(`/live-tracking/history-map?${params.toString()}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include'
      });

      const data = await response.json().catch(() => ({}));
      updateServerClockFromMeta(data?.meta);

      if (!response.ok || data?.success === false) {
        throw new Error(data?.message || 'Failed to fetch tracking history map');
      }

      return data?.data || null;
    } catch (err) {
      console.error('Error fetching tracking history map:', err);
      enqueueSnackbar(err.message || 'Gagal memuat histori peta tracking', { variant: 'error' });
      return null;
    }
  }, [enqueueSnackbar, token, updateServerClockFromMeta]);

  const searchTrackingHistoryStudents = useCallback(async (search = '', limit = 15) => {
    if (!token) return [];

    try {
      const params = new URLSearchParams();
      if (String(search).trim()) {
        params.append('search', String(search).trim());
      }
      params.append('limit', String(Math.max(1, Math.min(25, Number(limit) || 15))));

      const response = await fetch(getApiUrl(`/live-tracking/history-map/students?${params.toString()}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include'
      });

      const data = await response.json().catch(() => ({}));
      updateServerClockFromMeta(data?.meta);

      if (!response.ok || data?.success === false) {
        throw new Error(data?.message || 'Failed to search tracking history students');
      }

      return Array.isArray(data?.data) ? data.data : [];
    } catch (err) {
      console.error('Error searching tracking history students:', err);
      enqueueSnackbar(err.message || 'Gagal mencari siswa histori tracking', { variant: 'error' });
      return [];
    }
  }, [enqueueSnackbar, token, updateServerClockFromMeta]);

  const exportTrackingHistoryMapPdf = useCallback(async (userIds, options = {}) => {
    if (!token) return false;

    const normalizedUserIds = Array.from(new Set(
      (Array.isArray(userIds) ? userIds : [userIds])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0)
    )).slice(0, 5);

    if (normalizedUserIds.length === 0) {
      enqueueSnackbar('Pilih minimal satu siswa untuk mengunduh PDF histori peta', { variant: 'warning' });
      return false;
    }

    try {
      const params = new URLSearchParams();
      params.append('user_ids', normalizedUserIds.join(','));

      if (options.date) {
        params.append('date', String(options.date));
      }

      if (options.startTime) {
        params.append('start_time', String(options.startTime));
      }

      if (options.endTime) {
        params.append('end_time', String(options.endTime));
      }

      if (options.focusUserId) {
        params.append('focus_user_id', String(options.focusUserId));
      }

      if (options.exportScope) {
        params.append('export_scope', String(options.exportScope));
      }

      const response = await fetch(getApiUrl(`/live-tracking/history-map/export-pdf?${params.toString()}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/pdf,application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData?.message || 'Gagal mengunduh PDF histori peta');
      }

      const blob = await response.blob();
      const blobUrl = window.URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      const contentDisposition = response.headers.get('content-disposition') || '';
      const fileNameMatch = contentDisposition.match(/filename=\"?([^\";]+)\"?/i);
      anchor.href = blobUrl;
      anchor.download = fileNameMatch?.[1] || 'histori-peta-siswa.pdf';
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.URL.revokeObjectURL(blobUrl);

      return true;
    } catch (err) {
      console.error('Error exporting tracking history map PDF:', err);
      enqueueSnackbar(err.message || 'Gagal mengunduh PDF histori peta', { variant: 'error' });
      return false;
    }
  }, [enqueueSnackbar, token]);

  const fetchStudentsInRadius = useCallback(async (lat, lng, radius) => {
    if (!token) return [];

    try {
      const response = await fetch(getApiUrl('/live-tracking/users-in-radius'), {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({
          latitude: lat,
          longitude: lng,
          radius
        })
      });

      if (!response.ok) {
        throw new Error('Failed to fetch students in radius');
      }

      const data = await response.json();
      return data.success ? data.data : [];
    } catch (err) {
      console.error('Error fetching students in radius:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return [];
    }
  }, [token, enqueueSnackbar]);

  const visibleStudents = useMemo(() => {
    if (settings.display?.showInactiveStudents) {
      return students;
    }

    return students.filter((student) => student.status !== 'no_data' && student.status !== 'no-data');
  }, [settings.display?.showInactiveStudents, students]);

  const hasGpsDisabledStudents = useMemo(
    () => students.some((student) => student.status === 'gps_disabled'),
    [students]
  );

  const mapStudents = useMemo(() => {
    return visibleStudents
      .filter((student) => student.location?.lat !== null && student.location?.lng !== null)
      .slice(0, MAP_RENDER_LIMIT);
  }, [visibleStudents]);

  const mapOverflowCount = useMemo(() => {
    const mappableStudents = visibleStudents.filter((student) => student.location?.lat !== null && student.location?.lng !== null);
    return Math.max(0, mappableStudents.length - mapStudents.length);
  }, [mapStudents.length, visibleStudents]);

  const updateFilters = useCallback((newFilters) => {
    lastFetchTimeRef.current = 0;
    setFilters((previous) => ({ ...previous, ...newFilters }));
    setPagination((previous) => ({
      ...previous,
      page: 1,
    }));
  }, []);

  const selectStudent = useCallback((student) => {
    setSelectedStudent(student || null);
  }, []);

  const clearSelection = useCallback(() => {
    setSelectedStudent(null);
  }, []);

  const setCurrentPage = useCallback((page) => {
    lastFetchTimeRef.current = 0;
    setPagination((previous) => ({
      ...previous,
      page: Math.max(1, Number(page || 1)),
    }));
  }, []);

  const setPageSize = useCallback((pageSize) => {
    const normalizedPageSize = clampPageSize(pageSize);
    lastFetchTimeRef.current = 0;
    setPagination((previous) => ({
      ...previous,
      page: 1,
      perPage: normalizedPageSize,
    }));
    setSettings((previousSettings) => normalizeSettings({
      ...previousSettings,
      display: {
        ...previousSettings.display,
        maxStudentsInList: normalizedPageSize,
      },
    }));
  }, []);

  const refreshData = useCallback(async () => {
    lastFetchTimeRef.current = 0;
    return fetchCurrentTracking({ ignoreThrottle: true });
  }, [fetchCurrentTracking]);

  const startTrackingSessionForStudent = useCallback(async (userId, minutes = 15, reason = null) => {
    if (!token) {
      enqueueSnackbar('Token autentikasi tidak tersedia', { variant: 'error' });
      return false;
    }

    try {
      const data = await gpsTrackingService.startTrackingSession({
        userId,
        minutes,
        reason
      });

      if (data?.success === false) {
        enqueueSnackbar(data.message || 'Gagal mengaktifkan pemantauan tambahan', { variant: 'error' });
        return false;
      }

      enqueueSnackbar('Pemantauan tambahan berhasil diaktifkan', { variant: 'success' });
      void fetchActiveTrackingSessions();
      refreshData();
      return true;
    } catch (sessionError) {
      console.error('Failed to start tracking session:', sessionError);
      enqueueSnackbar(
        sessionError?.response?.data?.message || sessionError.message || 'Terjadi kesalahan saat mengaktifkan pemantauan tambahan',
        { variant: 'error' }
      );
      return false;
    }
  }, [enqueueSnackbar, refreshData, token]);

  const stopTrackingSessionForStudent = useCallback(async (userId) => {
    if (!token) {
      enqueueSnackbar('Token autentikasi tidak tersedia', { variant: 'error' });
      return false;
    }

    try {
      const data = await gpsTrackingService.stopTrackingSession(userId);

      if (data?.success === false) {
        enqueueSnackbar(data.message || 'Gagal menghentikan pemantauan tambahan', { variant: 'error' });
        return false;
      }

      enqueueSnackbar('Pemantauan tambahan berhasil dihentikan', { variant: 'success' });
      void fetchActiveTrackingSessions();
      refreshData();
      return true;
    } catch (sessionError) {
      console.error('Failed to stop tracking session:', sessionError);
      enqueueSnackbar(
        sessionError?.response?.data?.message || sessionError.message || 'Terjadi kesalahan saat menghentikan pemantauan tambahan',
        { variant: 'error' }
      );
      return false;
    }
  }, [enqueueSnackbar, refreshData, token]);

  const fetchActiveTrackingSessions = useCallback(async () => {
    if (!token) {
      return [];
    }

    try {
      setActiveSessionsLoading(true);
      const data = await gpsTrackingService.getActiveTrackingSessions();
      updateServerClockFromMeta(data?.meta);
      const currentServerNowMs = getCurrentServerEpochMs();
      const sessions = data?.success
        ? (data?.data?.sessions || [])
        : [];

      const normalized = sessions
        .filter((session) => {
          if (!session) {
            return false;
          }

          const expiresAt = session.expires_at || session.expiresAt;
          if (!expiresAt) {
            return false;
          }

          return Date.parse(expiresAt) > currentServerNowMs;
        })
        .map((session) => ({
          ...session,
          expiresAtMs: Date.parse(session.expires_at || session.expiresAt),
        }));

      setActiveTrackingSessions(normalized);
      return normalized;
    } catch (sessionError) {
      console.error('Error fetching active tracking sessions:', sessionError);
      setActiveTrackingSessions([]);
      return [];
    } finally {
      setActiveSessionsLoading(false);
    }
  }, [getCurrentServerEpochMs, token, updateServerClockFromMeta]);

  useEffect(() => {
    if (!token || isInitializedRef.current) return;

    isInitializedRef.current = true;

    fetchCurrentTracking({ ignoreThrottle: true });
    fetchSchoolHoursWindow();
    checkSchoolHours();

    schoolHoursIntervalRef.current = setInterval(() => {
      fetchSchoolHoursWindow();
      checkSchoolHours();
    }, 300000);

    trackingIntervalRef.current = setInterval(() => {
      void fetchCurrentTracking();
    }, 60000);

    return () => {
      if (schoolHoursIntervalRef.current) {
        clearInterval(schoolHoursIntervalRef.current);
      }
      if (trackingIntervalRef.current) {
        clearInterval(trackingIntervalRef.current);
      }
      isInitializedRef.current = false;
    };
  }, [checkSchoolHours, fetchCurrentTracking, fetchSchoolHoursWindow, token]);

  useEffect(() => {
    if (!token || !isInitializedRef.current) {
      return;
    }

    lastFetchTimeRef.current = 0;
    fetchCurrentTracking({ ignoreThrottle: true });
  }, [fetchCurrentTracking, token]);

  useEffect(() => {
    if (!token) {
      return;
    }

    const timer = setInterval(() => {
      const currentServerNowMs = getCurrentServerEpochMs();
      setStudents((previousStudents) => previousStudents.map((student) => {
        if (!student?.trackingSessionExpiresAtMs) {
          return student;
        }

        const isStillActive = student.trackingSessionExpiresAtMs > currentServerNowMs;
        if (isStillActive === Boolean(student.trackingSessionActive)) {
          return student;
        }

        return {
          ...student,
          trackingSessionActive: isStillActive
        };
      }));
    }, SESSION_EXPIRY_TICK_MS);

    return () => {
      clearInterval(timer);
    };
  }, [getCurrentServerEpochMs, token]);

  useEffect(() => {
    checkSchoolHours();
  }, [checkSchoolHours]);

  useEffect(() => {
    localStorage.setItem('trackingSettings', JSON.stringify(settings));
  }, [settings]);

  useEffect(() => {
    if (!token || !settings.refresh.autoRefresh) return;

    if (trackingIntervalRef.current) {
      clearInterval(trackingIntervalRef.current);
    }

    const minimumIntervalMs = hasGpsDisabledStudents ? 10000 : 30000;
    const intervalMs = Math.max(settings.refresh.interval * 1000, minimumIntervalMs);
    trackingIntervalRef.current = setInterval(() => {
      void fetchCurrentTracking();
    }, intervalMs);

    return () => {
      if (trackingIntervalRef.current) {
        clearInterval(trackingIntervalRef.current);
      }
    };
  }, [token, settings.refresh.interval, settings.refresh.autoRefresh, fetchCurrentTracking, hasGpsDisabledStudents]);

  useEffect(() => {
    if (!settings.refresh.refreshOnFocus) return;

    const handleFocus = () => {
      fetchCurrentTracking({ ignoreThrottle: true });
    };

    window.addEventListener('focus', handleFocus);
    return () => window.removeEventListener('focus', handleFocus);
  }, [settings.refresh.refreshOnFocus, fetchCurrentTracking]);

  const exportData = async (exportSettings) => {
    if (!token) return;

    try {
      setExportLoading(true);
      setExportError(null);

      const normalizedFormat = exportSettings.format === 'excel'
        ? 'xlsx'
        : exportSettings.format;

      const params = new URLSearchParams();
      params.append('format', normalizedFormat);
      params.append('date_range', exportSettings.dateRange);

      if (exportSettings.dateRange === 'custom') {
        params.append('start_date', exportSettings.customStartDate);
        params.append('end_date', exportSettings.customEndDate);
      }

      if (exportSettings.includeFilters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value) params.append(`filter_${key}`, value);
        });
      }

      Object.entries(exportSettings.includeFields).forEach(([field, include]) => {
        if (include) params.append(`include_${field}`, '1');
      });

      const response = await fetch(getApiUrl(`/live-tracking/export?${params}`), {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: '*/*'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error('Failed to export data');
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `tracking-export-${getServerDateString()}.${normalizedFormat}`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);

      enqueueSnackbar('Data berhasil diexport', { variant: 'success' });
    } catch (err) {
      console.error('Error exporting data:', err);
      setExportError(err.message);
      enqueueSnackbar(err.message, { variant: 'error' });
    } finally {
      setExportLoading(false);
    }
  };

  const saveHistoryPolicy = useCallback(async (policyDraft) => {
    if (!token) {
      return false;
    }

    const payload = {
      live_tracking_enabled: Boolean(
        policyDraft?.enabled ?? DEFAULT_HISTORY_POLICY.enabled
      ),
      live_tracking_min_distance_meters: Math.max(
        1,
        Number(policyDraft?.minDistanceMeters || DEFAULT_HISTORY_POLICY.minDistanceMeters)
      ),
      live_tracking_retention_days: Math.max(
        1,
        Number(policyDraft?.retentionDays || DEFAULT_HISTORY_POLICY.retentionDays)
      ),
      live_tracking_cleanup_time: String(
        policyDraft?.cleanupTime || DEFAULT_HISTORY_POLICY.cleanupTime
      ),
    };

    try {
      setHistoryPolicySaving(true);

      const response = await fetch(getApiUrl('/simple-attendance/global'), {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(payload)
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok || data?.status === 'error') {
        throw new Error(data?.message || 'Gagal menyimpan policy histori live tracking');
      }

      setHistoryPolicy((previous) => ({
        ...previous,
        ...normalizeHistoryPolicy(data?.data || payload),
      }));
      setHistoryPolicyLoading(false);
      lastFetchTimeRef.current = 0;

      return true;
    } catch (saveError) {
      console.error('Error saving live tracking history policy:', saveError);
      enqueueSnackbar(saveError.message || 'Gagal menyimpan policy histori live tracking', { variant: 'error' });
      return false;
    } finally {
      setHistoryPolicySaving(false);
    }
  }, [enqueueSnackbar, token]);

  const updateSettings = useCallback((newSettings) => {
    const normalizedSettings = normalizeSettings(newSettings);
    setSettings(normalizedSettings);
    setPagination((previous) => ({
      ...previous,
      page: 1,
      perPage: normalizedSettings.display.maxStudentsInList,
    }));
    lastFetchTimeRef.current = 0;
  }, []);

  const resetSettings = useCallback(() => {
    localStorage.removeItem('trackingSettings');
    const normalizedSettings = normalizeSettings();
    setSettings(normalizedSettings);
    setPagination((previous) => ({
      ...previous,
      page: 1,
      perPage: normalizedSettings.display.maxStudentsInList,
    }));
    lastFetchTimeRef.current = 0;
  }, []);

  return {
    students: visibleStudents,
    allStudents: students,
    mapStudents,
    mapOverflowCount,
    activeTrackingSessions,
    activeSessionsLoading,
    loading,
    error,
    isSchoolHours,
    schoolHoursWindow,
    attendanceLocations,
    serverClock,
    selectedStudent,
    filters,
    stats,
    classSummary,
    levelSummary,
    homeroomSummary,
    historyPolicy,
    historyPolicyLoading,
    historyPolicySaving,
    dataSources,
    settings,
    pagination,
    pageSizeOptions: PAGE_SIZE_OPTIONS,
    priorityQueues,
    priorityQueuesLoading,
    updateSettings,
    resetSettings,
    exportData,
    exportLoading,
    exportError,
    saveHistoryPolicy,
    fetchCurrentTracking,
    fetchStudentHistory,
    fetchTrackingHistoryMap,
    searchTrackingHistoryStudents,
    exportTrackingHistoryMapPdf,
    fetchStudentsInRadius,
    startTrackingSessionForStudent,
    stopTrackingSessionForStudent,
    fetchActiveTrackingSessions,
    updateFilters,
    selectStudent,
    clearSelection,
    refreshData,
    setCurrentPage,
    setPageSize,
    checkSchoolHours
  };
};

export default useTracking;

const DEFAULT_TIMEZONE = 'Asia/Jakarta';
const MIN_VALID_EPOCH_MS = Date.UTC(2000, 0, 1, 0, 0, 0);
const MAX_VALID_EPOCH_MS = Date.UTC(2050, 0, 1, 0, 0, 0);
const CLOCK_STORAGE_KEY = 'siaps.serverClock.v1';

const listeners = new Set();

const isFiniteNumber = (value) =>
  value !== null &&
  value !== undefined &&
  value !== '' &&
  Number.isFinite(Number(value));

const normalizeEpochMs = (value) => {
  if (!isFiniteNumber(value)) {
    return null;
  }

  let epochMs = Number(value);

  // Defensive: some backends may send UNIX seconds instead of milliseconds.
  if (Math.abs(epochMs) < 100_000_000_000) {
    epochMs *= 1000;
  }

  if (epochMs < MIN_VALID_EPOCH_MS || epochMs > MAX_VALID_EPOCH_MS) {
    return null;
  }

  return epochMs;
};

const isValidEpochMs = (value) => normalizeEpochMs(value) !== null;

const getMonotonicNowMs = () => {
  if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
    const now = performance.now();
    if (Number.isFinite(now)) {
      return now;
    }
  }

  return Date.now();
};

const normalizeTimezone = (value) => {
  if (typeof value !== 'string') {
    return DEFAULT_TIMEZONE;
  }

  const trimmed = value.trim();
  return trimmed || DEFAULT_TIMEZONE;
};

const readCachedServerClock = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const raw = window.sessionStorage?.getItem(CLOCK_STORAGE_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);
    const baseEpochMs = normalizeEpochMs(parsed?.baseEpochMs);
    if (baseEpochMs === null) {
      return null;
    }

    return {
      baseEpochMs,
      timezone: normalizeTimezone(parsed?.timezone),
    };
  } catch (error) {
    return null;
  }
};

const writeCachedServerClock = ({ baseEpochMs, timezone }) => {
  if (typeof window === 'undefined') {
    return;
  }

  const normalizedEpochMs = normalizeEpochMs(baseEpochMs);
  if (normalizedEpochMs === null) {
    return;
  }

  try {
    window.sessionStorage?.setItem(
      CLOCK_STORAGE_KEY,
      JSON.stringify({
        baseEpochMs: normalizedEpochMs,
        timezone: normalizeTimezone(timezone),
      })
    );
  } catch (error) {
    // Storage may be unavailable in private mode; live sync still works.
  }
};

const cachedServerClock = readCachedServerClock();

let serverClockState = {
  baseEpochMs: cachedServerClock?.baseEpochMs ?? null,
  syncedAtMs: null,
  syncedAtPerfMs: cachedServerClock ? getMonotonicNowMs() : null,
  timezone: cachedServerClock?.timezone ?? DEFAULT_TIMEZONE,
};

const parseEpochCandidate = (candidate) => {
  const parsed = Number(candidate);
  if (Number.isFinite(parsed)) {
    return normalizeEpochMs(parsed);
  }

  if (typeof candidate === 'string' && candidate.trim()) {
    const dateParsed = Date.parse(candidate);
    if (!Number.isNaN(dateParsed)) {
      return normalizeEpochMs(dateParsed);
    }
  }

  return null;
};

const resolveEpochFromClockPayload = (payload) => {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  if (payload.server_clock && typeof payload.server_clock === 'object') {
    const nestedEpoch = resolveEpochFromClockPayload(payload.server_clock);
    if (nestedEpoch !== null) {
      return nestedEpoch;
    }
  }

  const epochCandidates = [
    payload.server_epoch_ms,
    payload.serverEpochMs,
    payload.server_epoch,
  ];

  for (const candidate of epochCandidates) {
    const parsed = parseEpochCandidate(candidate);
    if (parsed !== null) {
      return parsed;
    }
  }

  const dateCandidates = [
    payload.server_now,
    payload.serverNow,
    payload.server_time,
    payload.serverTime,
  ];

  for (const candidate of dateCandidates) {
    const parsed = parseEpochCandidate(candidate);
    if (parsed !== null) {
      return parsed;
    }
  }

  return null;
};

const hasExplicitClockFields = (payload) => {
  if (!payload || typeof payload !== 'object') {
    return false;
  }

  const keys = [
    'server_epoch_ms',
    'serverEpochMs',
    'server_epoch',
    'server_now',
    'serverNow',
    'server_time',
    'serverTime',
    'server_clock',
    'timezone',
    'time_zone',
    'tz',
  ];

  return keys.some((key) => Object.prototype.hasOwnProperty.call(payload, key));
};

const resolveTimezoneFromClockPayload = (payload) => {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  if (payload.server_clock && typeof payload.server_clock === 'object') {
    const nestedTimezone = resolveTimezoneFromClockPayload(payload.server_clock);
    if (nestedTimezone) {
      return nestedTimezone;
    }
  }

  return normalizeTimezone(
    payload.timezone ??
    payload.time_zone ??
    payload.tz
  );
};

const getHeaderValue = (headers, names) => {
  if (!headers || typeof headers !== 'object') {
    return undefined;
  }

  for (const name of names) {
    const value = headers[name] ?? headers[String(name).toLowerCase()];
    if (value !== undefined && value !== null && value !== '') {
      return value;
    }
  }

  return undefined;
};

const resolveClockPayloadFromHeaders = (headers) => {
  const serverEpochMs = getHeaderValue(headers, ['X-Server-Epoch-Ms', 'x-server-epoch-ms']);
  const serverNow = getHeaderValue(headers, ['X-Server-Now', 'x-server-now']);
  const serverDate = getHeaderValue(headers, ['X-Server-Date', 'x-server-date']);
  const timezone = getHeaderValue(headers, ['X-Server-Timezone', 'x-server-timezone']);

  if (!serverEpochMs && !serverNow && !serverDate && !timezone) {
    return null;
  }

  return {
    server_epoch_ms: serverEpochMs,
    server_now: serverNow,
    server_date: serverDate,
    timezone,
  };
};

const emitServerClockChanged = () => {
  const snapshot = getServerClockSnapshot();
  listeners.forEach((listener) => {
    try {
      listener(snapshot);
    } catch (error) {
      // Ignore subscriber errors so one consumer does not block updates.
    }
  });
};

const applyServerClock = ({
  epochMs,
  timezone = null,
  syncedAtMs = null,
  syncedAtPerfMs = null,
}) => {
  const normalizedEpochMs = normalizeEpochMs(epochMs);
  if (normalizedEpochMs === null) {
    return false;
  }

  serverClockState = {
    baseEpochMs: normalizedEpochMs,
    syncedAtMs: isFiniteNumber(syncedAtMs) ? Number(syncedAtMs) : Date.now(),
    syncedAtPerfMs: isFiniteNumber(syncedAtPerfMs) ? Number(syncedAtPerfMs) : getMonotonicNowMs(),
    timezone: timezone ? normalizeTimezone(timezone) : serverClockState.timezone,
  };

  writeCachedServerClock(serverClockState);
  emitServerClockChanged();
  return true;
};

export const getServerClockSnapshot = () => ({
  ...serverClockState,
  isSynced:
    isFiniteNumber(serverClockState.baseEpochMs) &&
    (isFiniteNumber(serverClockState.syncedAtPerfMs) || isFiniteNumber(serverClockState.syncedAtMs)),
});

export const subscribeServerClock = (listener) => {
  if (typeof listener !== 'function') {
    return () => {};
  }

  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
};

export const syncServerClockFromMeta = (meta) => {
  if (!meta || typeof meta !== 'object') {
    return false;
  }

  const epochMs = resolveEpochFromClockPayload(meta);
  if (!isFiniteNumber(epochMs)) {
    return false;
  }

  const timezone = resolveTimezoneFromClockPayload(meta);
  return applyServerClock({ epochMs, timezone });
};

export const syncServerClockFromResponse = (response) => {
  const headerPayload = resolveClockPayloadFromHeaders(response?.headers);
  if (headerPayload && syncServerClockFromMeta(headerPayload)) {
    return true;
  }

  const payload = response?.data;

  if (payload && typeof payload === 'object') {
    if (syncServerClockFromMeta(payload.meta)) {
      return true;
    }

    if (hasExplicitClockFields(payload) && syncServerClockFromMeta(payload)) {
      return true;
    }
  }

  return false;
};

export const getServerNowEpochMs = () => {
  const { baseEpochMs, syncedAtMs, syncedAtPerfMs } = serverClockState;
  if (!isFiniteNumber(baseEpochMs)) {
    return normalizeEpochMs(Date.now()) ?? Number.NaN;
  }

  if (isFiniteNumber(syncedAtPerfMs)) {
    return Number(baseEpochMs) + (getMonotonicNowMs() - Number(syncedAtPerfMs));
  }

  if (isFiniteNumber(syncedAtMs)) {
    return Number(baseEpochMs) + (Date.now() - Number(syncedAtMs));
  }

  return Number(baseEpochMs);
};

export const cacheCurrentServerClock = () => {
  if (!isFiniteNumber(serverClockState.baseEpochMs)) {
    return false;
  }

  const currentEpochMs = normalizeEpochMs(getServerNowEpochMs());
  if (currentEpochMs === null) {
    return false;
  }

  writeCachedServerClock({
    baseEpochMs: currentEpochMs,
    timezone: serverClockState.timezone,
  });

  return true;
};

export const getServerNowDate = () => new Date(getServerNowEpochMs());

const getDateParts = (epochMs, timezone) => {
  if (!isValidEpochMs(epochMs)) {
    return null;
  }

  const date = new Date(epochMs);
  if (Number.isNaN(date.getTime())) {
    return null;
  }

  try {
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: normalizeTimezone(timezone),
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    });

    const parts = formatter.formatToParts(date);
    const map = {};
    parts.forEach((part) => {
      if (part.type !== 'literal') {
        map[part.type] = part.value;
      }
    });

    if (!map.year || !map.month || !map.day || !map.hour || !map.minute || !map.second) {
      return null;
    }

    return {
      year: map.year,
      month: map.month,
      day: map.day,
      hour: map.hour,
      minute: map.minute,
      second: map.second,
    };
  } catch (error) {
    return {
      year: String(date.getFullYear()),
      month: String(date.getMonth() + 1).padStart(2, '0'),
      day: String(date.getDate()).padStart(2, '0'),
      hour: String(date.getHours()).padStart(2, '0'),
      minute: String(date.getMinutes()).padStart(2, '0'),
      second: String(date.getSeconds()).padStart(2, '0'),
    };
  }
};

export const getServerDateString = (
  epochMs = getServerNowEpochMs(),
  timezone = getServerClockSnapshot().timezone
) => {
  const parts = getDateParts(epochMs, timezone);
  if (!parts) {
    return '';
  }

  return `${parts.year}-${parts.month}-${parts.day}`;
};

export const getServerTimeString = (
  epochMs = getServerNowEpochMs(),
  timezone = getServerClockSnapshot().timezone
) => {
  const parts = getDateParts(epochMs, timezone);
  if (!parts) {
    return '';
  }

  return `${parts.hour}:${parts.minute}:${parts.second}`;
};

export const getServerIsoString = (epochMs = getServerNowEpochMs()) => {
  if (!isValidEpochMs(epochMs)) {
    return '';
  }

  const date = new Date(epochMs);
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  return date.toISOString();
};

const resolveEpochFromValue = (value) => {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  return parseEpochCandidate(value);
};

export const formatServerDateTime = (
  value,
  locale = 'id-ID',
  options = {}
) => {
  const epochMs = resolveEpochFromValue(value);
  if (!isFiniteNumber(epochMs)) {
    return '';
  }

  const timezone = getServerClockSnapshot().timezone || DEFAULT_TIMEZONE;
  const date = new Date(Number(epochMs));
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  try {
    return new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      ...options,
    }).format(date);
  } catch (error) {
    return date.toLocaleString(locale, options);
  }
};

export const formatServerDate = (
  value,
  locale = 'id-ID',
  options = {}
) =>
  formatServerDateTime(value, locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...options,
  });

export const formatServerTime = (
  value,
  locale = 'id-ID',
  options = {}
) =>
  formatServerDateTime(value, locale, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    ...options,
  });

export const toServerDateInput = (value) => {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
    return value.trim();
  }

  const epochMs = resolveEpochFromValue(value);
  if (!isFiniteNumber(epochMs)) {
    return '';
  }

  return getServerDateString(Number(epochMs), getServerClockSnapshot().timezone);
};

export const getServerDateParts = (value) => {
  const normalizedDate = toServerDateInput(value);
  const match = normalizedDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) {
    return null;
  }

  return {
    year: Number(match[1]),
    month: Number(match[2]),
    day: Number(match[3]),
    dateString: normalizedDate,
  };
};

export const toServerCalendarDate = (value) => {
  const parts = getServerDateParts(value);
  if (!parts) {
    return null;
  }

  return new Date(parts.year, parts.month - 1, parts.day);
};

export const compareServerDate = (left, right) => {
  const leftDate = toServerDateInput(left);
  const rightDate = toServerDateInput(right);

  if (!leftDate || !rightDate) {
    return null;
  }

  if (leftDate === rightDate) {
    return 0;
  }

  return leftDate < rightDate ? -1 : 1;
};

export default {
  getServerClockSnapshot,
  subscribeServerClock,
  syncServerClockFromMeta,
  syncServerClockFromResponse,
  cacheCurrentServerClock,
  getServerNowEpochMs,
  getServerNowDate,
  getServerDateString,
  getServerTimeString,
  getServerIsoString,
  formatServerDateTime,
  formatServerDate,
  formatServerTime,
  toServerDateInput,
  getServerDateParts,
  toServerCalendarDate,
  compareServerDate,
};

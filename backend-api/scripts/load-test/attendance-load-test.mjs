#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const DEFAULTS = {
  baseUrl: process.env.BASE_URL || 'http://localhost:8000/api',
  mode: (process.env.MODE || 'validate_time').trim(), // validate_time | submit
  attendanceType: (process.env.ATTENDANCE_TYPE || 'masuk').trim(), // masuk | pulang
  durationSeconds: Number(process.env.DURATION_SECONDS || 60),
  concurrency: Number(process.env.CONCURRENCY || 50),
  timeoutMs: Number(process.env.TIMEOUT_MS || 15000),
  accuracy: Number(process.env.ACCURACY || 10),
  latitude: Number(process.env.LATITUDE || -6.75),
  longitude: Number(process.env.LONGITUDE || 108.55),
  lokasiId: process.env.LOKASI_ID ? Number(process.env.LOKASI_ID) : null,
  tokensFile: process.env.TOKENS_FILE || path.resolve(process.cwd(), 'scripts/load-test/tokens.example.json'),
  reportFile: process.env.REPORT_FILE || '',
};

const VALID_MODES = new Set(['validate_time', 'submit']);
const VALID_TYPES = new Set(['masuk', 'pulang']);

const SMALL_VALID_BASE64_IMAGE =
  'data:image/jpeg;base64,' + Buffer.from('load-test-image-content').toString('base64');

function fail(message) {
  console.error(`[ERROR] ${message}`);
  process.exit(1);
}

function ensureConfig(config) {
  if (!VALID_MODES.has(config.mode)) {
    fail(`MODE harus salah satu: ${Array.from(VALID_MODES).join(', ')}`);
  }
  if (!VALID_TYPES.has(config.attendanceType)) {
    fail(`ATTENDANCE_TYPE harus salah satu: ${Array.from(VALID_TYPES).join(', ')}`);
  }
  if (!Number.isFinite(config.durationSeconds) || config.durationSeconds <= 0) {
    fail('DURATION_SECONDS harus angka > 0');
  }
  if (!Number.isFinite(config.concurrency) || config.concurrency <= 0) {
    fail('CONCURRENCY harus angka > 0');
  }
  if (!Number.isFinite(config.timeoutMs) || config.timeoutMs <= 0) {
    fail('TIMEOUT_MS harus angka > 0');
  }
}

function normalizeTokenItem(item, index) {
  if (typeof item === 'string') {
    return {
      id: index + 1,
      token: item,
      latitude: DEFAULTS.latitude,
      longitude: DEFAULTS.longitude,
      accuracy: DEFAULTS.accuracy,
      lokasi_id: DEFAULTS.lokasiId,
    };
  }

  if (item && typeof item === 'object' && typeof item.token === 'string') {
    return {
      id: item.id ?? index + 1,
      token: item.token,
      latitude: Number.isFinite(Number(item.latitude)) ? Number(item.latitude) : DEFAULTS.latitude,
      longitude: Number.isFinite(Number(item.longitude)) ? Number(item.longitude) : DEFAULTS.longitude,
      accuracy: Number.isFinite(Number(item.accuracy)) ? Number(item.accuracy) : DEFAULTS.accuracy,
      lokasi_id:
        item.lokasi_id !== undefined && item.lokasi_id !== null && Number.isFinite(Number(item.lokasi_id))
          ? Number(item.lokasi_id)
          : DEFAULTS.lokasiId,
      foto: typeof item.foto === 'string' ? item.foto : SMALL_VALID_BASE64_IMAGE,
    };
  }

  fail(`Format token item ke-${index + 1} tidak valid. Gunakan string token atau object { token, ... }.`);
}

function loadTokens(tokensFile) {
  if (!fs.existsSync(tokensFile)) {
    fail(`File token tidak ditemukan: ${tokensFile}`);
  }

  const raw = fs.readFileSync(tokensFile, 'utf8');
  let parsed;
  try {
    parsed = JSON.parse(raw);
  } catch (error) {
    fail(`File token bukan JSON valid: ${error.message}`);
  }

  if (!Array.isArray(parsed) || parsed.length === 0) {
    fail('TOKENS_FILE harus berisi array token (minimal 1 item).');
  }

  return parsed.map(normalizeTokenItem);
}

function percentile(sortedValues, p) {
  if (sortedValues.length === 0) return 0;
  const index = Math.ceil((p / 100) * sortedValues.length) - 1;
  return sortedValues[Math.max(0, Math.min(index, sortedValues.length - 1))];
}

function createPayload(mode, tokenItem, attendanceType) {
  if (mode === 'validate_time') {
    return {
      type: attendanceType,
      // Keep backward compatibility for endpoints/versions that still read jenis_absensi.
      jenis_absensi: attendanceType,
    };
  }

  const payload = {
    jenis_absensi: attendanceType,
    latitude: tokenItem.latitude,
    longitude: tokenItem.longitude,
    accuracy: tokenItem.accuracy,
    foto: tokenItem.foto || SMALL_VALID_BASE64_IMAGE,
  };

  if (Number.isFinite(Number(tokenItem.lokasi_id))) {
    payload.lokasi_id = Number(tokenItem.lokasi_id);
  }

  return payload;
}

async function doRequest({ url, tokenItem, payload, timeoutMs }) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const startedAt = Date.now();

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${tokenItem.token}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    const elapsedMs = Date.now() - startedAt;
    const contentType = response.headers.get('content-type') || '';
    let body = null;

    if (contentType.includes('application/json')) {
      body = await response.json();
    } else {
      body = await response.text();
    }

    return {
      ok: response.ok,
      status: response.status,
      elapsedMs,
      body,
    };
  } catch (error) {
    const elapsedMs = Date.now() - startedAt;
    return {
      ok: false,
      status: 0,
      elapsedMs,
      error: error?.name === 'AbortError' ? 'TIMEOUT' : (error?.message || 'UNKNOWN_ERROR'),
      body: null,
    };
  } finally {
    clearTimeout(timeout);
  }
}

async function main() {
  ensureConfig(DEFAULTS);

  const tokens = loadTokens(DEFAULTS.tokensFile);
  const endpoint = DEFAULTS.mode === 'submit' ? '/simple-attendance/submit' : '/simple-attendance/validate-time';
  const url = `${DEFAULTS.baseUrl.replace(/\/+$/, '')}${endpoint}`;

  console.log('=== Attendance Load Test Baseline ===');
  console.log(`BASE_URL        : ${DEFAULTS.baseUrl}`);
  console.log(`MODE            : ${DEFAULTS.mode}`);
  console.log(`ATTENDANCE_TYPE : ${DEFAULTS.attendanceType}`);
  console.log(`CONCURRENCY     : ${DEFAULTS.concurrency}`);
  console.log(`DURATION        : ${DEFAULTS.durationSeconds}s`);
  console.log(`TIMEOUT         : ${DEFAULTS.timeoutMs}ms`);
  console.log(`TOKENS          : ${tokens.length}`);
  console.log(`ENDPOINT        : ${endpoint}`);
  console.log('');

  const endAt = Date.now() + DEFAULTS.durationSeconds * 1000;
  let index = 0;

  const latencies = [];
  const statusCounts = new Map();
  const codeCounts = new Map();
  const errorCounts = new Map();

  let total = 0;
  let success = 0;
  let failed = 0;

  const workers = Array.from({ length: DEFAULTS.concurrency }, async () => {
    while (Date.now() < endAt) {
      const tokenItem = tokens[index % tokens.length];
      index += 1;

      const payload = createPayload(DEFAULTS.mode, tokenItem, DEFAULTS.attendanceType);
      const result = await doRequest({
        url,
        tokenItem,
        payload,
        timeoutMs: DEFAULTS.timeoutMs,
      });

      total += 1;
      latencies.push(result.elapsedMs);

      const statusKey = String(result.status || 0);
      statusCounts.set(statusKey, (statusCounts.get(statusKey) || 0) + 1);

      if (result.ok) {
        success += 1;
      } else {
        failed += 1;
      }

      if (result.body && typeof result.body === 'object' && typeof result.body.code === 'string') {
        const code = result.body.code;
        codeCounts.set(code, (codeCounts.get(code) || 0) + 1);
      }

      if (result.error) {
        errorCounts.set(result.error, (errorCounts.get(result.error) || 0) + 1);
      }
    }
  });

  await Promise.all(workers);

  latencies.sort((a, b) => a - b);
  const elapsedTotalMs = DEFAULTS.durationSeconds * 1000;
  const rps = elapsedTotalMs > 0 ? total / (elapsedTotalMs / 1000) : 0;

  const summary = {
    config: {
      baseUrl: DEFAULTS.baseUrl,
      mode: DEFAULTS.mode,
      attendanceType: DEFAULTS.attendanceType,
      durationSeconds: DEFAULTS.durationSeconds,
      concurrency: DEFAULTS.concurrency,
      tokensCount: tokens.length,
      endpoint,
    },
    totals: {
      requests: total,
      success,
      failed,
      successRatePercent: total > 0 ? Number(((success / total) * 100).toFixed(2)) : 0,
      rps: Number(rps.toFixed(2)),
    },
    latencyMs: {
      min: latencies.length ? latencies[0] : 0,
      p50: percentile(latencies, 50),
      p95: percentile(latencies, 95),
      p99: percentile(latencies, 99),
      max: latencies.length ? latencies[latencies.length - 1] : 0,
      avg: latencies.length
        ? Number((latencies.reduce((sum, item) => sum + item, 0) / latencies.length).toFixed(2))
        : 0,
    },
    statusCounts: Object.fromEntries(statusCounts.entries()),
    responseCodeCounts: Object.fromEntries(codeCounts.entries()),
    transportErrors: Object.fromEntries(errorCounts.entries()),
  };

  console.log('=== SUMMARY ===');
  console.log(JSON.stringify(summary, null, 2));

  if (DEFAULTS.reportFile) {
    fs.writeFileSync(DEFAULTS.reportFile, JSON.stringify(summary, null, 2), 'utf8');
    console.log(`\nReport disimpan ke: ${DEFAULTS.reportFile}`);
  }
}

main().catch((error) => {
  fail(error?.stack || error?.message || String(error));
});

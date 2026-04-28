import React, { useEffect, useMemo, useState } from 'react';
import { useSnackbar } from 'notistack';
import {
  Activity,
  AlertTriangle,
  Clock,
  RefreshCw,
  Save,
  Shield,
  Smartphone,
} from 'lucide-react';
import { mobileReleasesAPI, sbtAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';

const createForm = () => ({
  enabled: true,
  exam_url: 'https://res.sman1sumbercirebon.sch.id',
  webview_user_agent: 'SBT-SMANIS/1.0',
  security_mode: 'warning',
  supervisor_code: '',
  clear_supervisor_code: false,
  minimum_app_version: '',
  require_dnd: false,
  require_screen_pinning: true,
  require_overlay_protection: true,
  ios_lock_on_background: true,
  minimum_battery_level: 20,
  heartbeat_interval_seconds: 30,
  maintenance_enabled: false,
  maintenance_message: '',
  announcement: '',
  has_supervisor_code: false,
  config_version: 1,
});

const securityModes = [
  {
    value: 'warning',
    label: 'Peringatan',
    description: 'Pelanggaran fokus dicatat, siswa masih bisa lanjut.',
  },
  {
    value: 'supervisor_code',
    label: 'Kode Pengawas',
    description: 'Siswa harus meminta kode pengawas setelah pelanggaran fokus.',
  },
  {
    value: 'locked',
    label: 'Kunci Ketat',
    description: 'Ujian tertahan sampai pengawas membuka dengan kode.',
  },
];

const formatDate = (value) =>
  formatServerDateTime(value, 'id-ID', { dateStyle: 'medium', timeStyle: 'short' }) || '-';

const formatBytes = (value) => {
  const size = Number(value);
  if (!Number.isFinite(size) || size <= 0) return '-';
  if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${size} B`;
};

const normalizeSettings = (settings = {}) => ({
  ...createForm(),
  ...settings,
  supervisor_code: '',
  clear_supervisor_code: false,
  minimum_app_version: settings.minimum_app_version || '',
  webview_user_agent: settings.webview_user_agent || 'SBT-SMANIS/1.0',
  maintenance_message: settings.maintenance_message || '',
  announcement: settings.announcement || '',
});

const releaseStatusLabel = (release) => {
  if (!release) return 'Belum ada release SBT';
  if (!release.is_published) return 'Draft';
  if (!release.is_active) return 'Tidak aktif';
  return release.update_mode === 'required' || release.update_policies?.siswa?.update_mode === 'required'
    ? 'Update wajib'
    : 'Aktif';
};

const SbtManagement = () => {
  const { enqueueSnackbar } = useSnackbar();
  const [form, setForm] = useState(createForm);
  const [summary, setSummary] = useState(null);
  const [sessions, setSessions] = useState([]);
  const [releases, setReleases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const activeMode = useMemo(
    () => securityModes.find((item) => item.value === form.security_mode) || securityModes[0],
    [form.security_mode]
  );

  const latestSbtRelease = useMemo(() => {
    return [...releases]
      .filter((release) => release.app_key === 'sbt-smanis' && release.platform === 'android')
      .sort((left, right) => Number(right.build_number || 0) - Number(left.build_number || 0))[0] || null;
  }, [releases]);

  const policyItems = useMemo(() => [
    {
      label: 'Mode keamanan',
      value: activeMode.label,
      tone: form.security_mode === 'warning' ? 'amber' : 'rose',
    },
    {
      label: 'Mode Jangan Ganggu',
      value: form.require_dnd ? 'Wajib' : 'Opsional',
      tone: form.require_dnd ? 'rose' : 'slate',
    },
    {
      label: 'Screen pinning',
      value: form.require_screen_pinning ? 'Wajib' : 'Opsional',
      tone: form.require_screen_pinning ? 'rose' : 'slate',
    },
    {
      label: 'Blok overlay',
      value: form.require_overlay_protection ? 'Aktif' : 'Opsional',
      tone: form.require_overlay_protection ? 'emerald' : 'slate',
    },
    {
      label: 'iPhone keluar app',
      value: form.ios_lock_on_background ? 'Kunci sesi' : 'Tidak aktif',
      tone: form.ios_lock_on_background ? 'emerald' : 'rose',
    },
  ], [activeMode.label, form.ios_lock_on_background, form.require_dnd, form.require_overlay_protection, form.require_screen_pinning, form.security_mode]);

  const loadData = async () => {
    setLoading(true);
    setError('');

    try {
      const [settingsResponse, summaryResponse, sessionsResponse, releasesResponse] = await Promise.all([
        sbtAPI.getSettings(),
        sbtAPI.getSummary(),
        sbtAPI.getSessions({ per_page: 8 }),
        mobileReleasesAPI.getAll({ app_key: 'sbt-smanis', platform: 'android' }),
      ]);

      setForm(normalizeSettings(settingsResponse?.data?.data || {}));
      setSummary(summaryResponse?.data?.data || null);
      setSessions(sessionsResponse?.data?.data?.items || []);
      setReleases(releasesResponse?.data?.data || []);
    } catch (loadError) {
      setError(loadError?.response?.data?.message || 'Data SBT belum bisa dimuat.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleChange = (event) => {
    const { name, type, value, checked } = event.target;
    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);

    const payload = {
      enabled: Boolean(form.enabled),
      exam_url: form.exam_url.trim(),
      webview_user_agent: form.webview_user_agent.trim() || 'SBT-SMANIS/1.0',
      security_mode: form.security_mode,
      supervisor_code: form.supervisor_code.trim() || null,
      clear_supervisor_code: Boolean(form.clear_supervisor_code),
      minimum_app_version: form.minimum_app_version.trim() || null,
      require_dnd: Boolean(form.require_dnd),
      require_screen_pinning: Boolean(form.require_screen_pinning),
      require_overlay_protection: Boolean(form.require_overlay_protection),
      ios_lock_on_background: Boolean(form.ios_lock_on_background),
      minimum_battery_level: Number(form.minimum_battery_level),
      heartbeat_interval_seconds: Number(form.heartbeat_interval_seconds),
      maintenance_enabled: Boolean(form.maintenance_enabled),
      maintenance_message: form.maintenance_message.trim() || null,
      announcement: form.announcement.trim() || null,
    };

    try {
      const response = await sbtAPI.updateSettings(payload);
      setForm(normalizeSettings(response?.data?.data || {}));
      enqueueSnackbar(response?.data?.message || 'Pengaturan SBT berhasil disimpan.', { variant: 'success' });
      await loadData();
    } catch (saveError) {
      const apiErrors = saveError?.response?.data?.errors;
      const firstError = apiErrors ? Object.values(apiErrors).flat().find(Boolean) : null;
      enqueueSnackbar(firstError || saveError?.response?.data?.message || 'Pengaturan SBT gagal disimpan.', {
        variant: 'error',
      });
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-[320px] items-center justify-center text-sm text-slate-600">
        <RefreshCw className="mr-3 h-5 w-5 animate-spin" />
        Memuat pengaturan SBT...
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-4">
        <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{error}</div>
        <button
          type="button"
          onClick={loadData}
          className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
          <RefreshCw className="mr-2 h-4 w-4" />
          Muat Ulang
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="flex items-start gap-3">
            <div className="rounded-lg bg-blue-50 p-3 text-blue-700">
              <Smartphone className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-widest text-blue-600">Smartphone Based Test</p>
              <h1 className="mt-1 text-2xl font-bold text-slate-900">SBT SMANIS</h1>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Atur jalur CBT siswa, mode fokus, kode pengawas, dan pemantauan sesi ujian dari SIAPS.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={loadData}
            className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            <RefreshCw className="mr-2 h-4 w-4" />
            Refresh
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        <SummaryCard icon={Activity} label="Sesi Aktif" value={summary?.active_sessions || 0} tone="blue" />
        <SummaryCard icon={Clock} label="Sesi Hari Ini" value={summary?.sessions_today || 0} tone="emerald" />
        <SummaryCard icon={Shield} label="Kunci Hari Ini" value={summary?.lock_events_today || 0} tone="amber" />
        <SummaryCard icon={AlertTriangle} label="Buka Kunci" value={summary?.supervisor_unlock_events_today || 0} tone="rose" />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
        <InfoPanel title="Status Aplikasi SBT">
          {latestSbtRelease ? (
            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
              <StatusBlock label="App Key" value={latestSbtRelease.app_key || 'sbt-smanis'} />
              <StatusBlock
                label="Versi Terbaru"
                value={`${latestSbtRelease.public_version || '-'} (${latestSbtRelease.build_number || '-'})`}
              />
              <StatusBlock label="Kebijakan" value={releaseStatusLabel(latestSbtRelease)} />
              <StatusBlock label="Ukuran" value={formatBytes(latestSbtRelease.file_size_bytes)} />
            </div>
          ) : (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
              Release dengan app key <span className="font-semibold">sbt-smanis</span> belum ditemukan di Pusat Download.
            </div>
          )}
          <p className="mt-3 text-sm text-slate-600">
            Aplikasi siswa hanya membaca release SBT, sehingga tidak bentrok dengan release SIAPS.
          </p>
        </InfoPanel>

        <InfoPanel title="Kebijakan Keamanan Aktif">
          <div className="grid grid-cols-1 gap-2">
            {policyItems.map((item) => (
              <PolicyRow key={item.label} label={item.label} value={item.value} tone={item.tone} />
            ))}
          </div>
          <p className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
            SBT tidak menampilkan daftar pelanggaran siswa di halaman ini. Saat siswa keluar aplikasi, sesi dikunci di perangkat dan pengawas membuka dengan kode.
          </p>
        </InfoPanel>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold text-slate-900">Pengaturan Ujian</h2>
              <p className="mt-1 text-sm text-slate-500">Versi konfigurasi: {form.config_version}</p>
            </div>
            <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
              <input
                type="checkbox"
                name="enabled"
                checked={Boolean(form.enabled)}
                onChange={handleChange}
                className="h-4 w-4 rounded border-slate-300 text-blue-600"
              />
              Aktif
            </label>
          </div>

          <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Field label="URL CBT">
              <input
                type="url"
                name="exam_url"
                value={form.exam_url}
                onChange={handleChange}
                required
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>

            <Field label="Minimum Versi App">
              <input
                type="text"
                name="minimum_app_version"
                value={form.minimum_app_version}
                onChange={handleChange}
                placeholder="Opsional, contoh 1.0.0"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>

            <Field label="User-Agent WebView CBT">
              <input
                type="text"
                name="webview_user_agent"
                value={form.webview_user_agent}
                onChange={handleChange}
                placeholder="SBT-SMANIS/1.0"
                maxLength={255}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
              <p className="mt-2 text-xs leading-5 text-slate-500">
                Agent ini dipakai WebView saat membuka CBT, sehingga bisa dicek oleh server ujian.
              </p>
            </Field>

            <Field label="Mode Keamanan">
              <select
                name="security_mode"
                value={form.security_mode}
                onChange={handleChange}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                {securityModes.map((mode) => (
                  <option key={mode.value} value={mode.value}>
                    {mode.label}
                  </option>
                ))}
              </select>
              <p className="mt-2 text-xs leading-5 text-slate-500">{activeMode.description}</p>
            </Field>

            <Field label="Kode Pengawas">
              <input
                type="password"
                name="supervisor_code"
                value={form.supervisor_code}
                onChange={handleChange}
                placeholder={form.has_supervisor_code ? 'Kosongkan jika tidak diganti' : 'Isi minimal 4 karakter'}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
              <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span>{form.has_supervisor_code ? 'Kode sudah tersimpan.' : 'Kode belum tersimpan.'}</span>
                {form.has_supervisor_code ? (
                  <label className="inline-flex items-center gap-2">
                    <input
                      type="checkbox"
                      name="clear_supervisor_code"
                      checked={Boolean(form.clear_supervisor_code)}
                      onChange={handleChange}
                      className="h-4 w-4 rounded border-slate-300 text-blue-600"
                    />
                    Hapus kode lama
                  </label>
                ) : null}
              </div>
            </Field>

            <Field label="Baterai Minimum">
              <input
                type="number"
                name="minimum_battery_level"
                min="0"
                max="100"
                value={form.minimum_battery_level}
                onChange={handleChange}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>

            <Field label="Interval Heartbeat">
              <input
                type="number"
                name="heartbeat_interval_seconds"
                min="10"
                max="300"
                value={form.heartbeat_interval_seconds}
                onChange={handleChange}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>
          </div>

          <div className="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
            <CheckboxField name="require_dnd" checked={form.require_dnd} onChange={handleChange} label="Wajib DND" />
            <CheckboxField
              name="require_screen_pinning"
              checked={form.require_screen_pinning}
              onChange={handleChange}
              label="Wajib Screen Pinning"
            />
            <CheckboxField
              name="require_overlay_protection"
              checked={form.require_overlay_protection}
              onChange={handleChange}
              label="Blok Overlay"
            />
            <CheckboxField
              name="ios_lock_on_background"
              checked={form.ios_lock_on_background}
              onChange={handleChange}
              label="iPhone Kunci Saat Keluar"
            />
          </div>

          <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Field label="Pengumuman Siswa">
              <textarea
                name="announcement"
                value={form.announcement}
                onChange={handleChange}
                rows={4}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>
            <Field label="Mode Maintenance">
              <label className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input
                  type="checkbox"
                  name="maintenance_enabled"
                  checked={Boolean(form.maintenance_enabled)}
                  onChange={handleChange}
                  className="h-4 w-4 rounded border-slate-300 text-blue-600"
                />
                Aktifkan maintenance
              </label>
              <textarea
                name="maintenance_message"
                value={form.maintenance_message}
                onChange={handleChange}
                rows={4}
                placeholder="Pesan saat aplikasi ujian ditutup sementara"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </Field>
          </div>

          <div className="mt-5 flex justify-end">
            <button
              type="submit"
              disabled={saving}
              className="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              <Save className="mr-2 h-4 w-4" />
              {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
            </button>
          </div>
        </div>

        <div className="space-y-4">
          <InfoPanel title="Status Kode Pengawas">
            <p className="text-sm text-slate-600">
              {form.has_supervisor_code
                ? `Kode aktif sejak ${formatDate(form.supervisor_code_updated_at)}.`
                : 'Kode pengawas belum dibuat.'}
            </p>
            <p className="mt-3 text-sm text-slate-600">
              Mode saat ini: <span className="font-semibold text-slate-900">{activeMode.label}</span>
            </p>
          </InfoPanel>

          <InfoPanel title="CBT">
            <p className="break-all text-sm font-medium text-slate-900">{form.exam_url}</p>
            <p className="mt-2 text-sm text-slate-600">Host diizinkan: {form.exam_host || '-'}</p>
            <p className="mt-2 break-all text-sm text-slate-600">Agent: {form.webview_user_agent || 'SBT-SMANIS/1.0'}</p>
          </InfoPanel>

          <InfoPanel title="iPhone">
            <p className="text-sm text-slate-600">
              iOS memakai deteksi background. Jika siswa keluar aplikasi, layar ujian terkunci saat kembali dan harus dibuka pengawas.
            </p>
          </InfoPanel>
        </div>
      </form>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <DataPanel title="Sesi Terbaru">
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr>
                  <th className="py-2 pr-3">Perangkat</th>
                  <th className="py-2 pr-3">Status</th>
                  <th className="py-2 pr-3">Heartbeat</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {sessions.length === 0 ? (
                  <tr>
                    <td colSpan="3" className="py-4 text-slate-500">
                      Belum ada sesi SBT.
                    </td>
                  </tr>
                ) : (
                  sessions.map((session) => (
                    <tr key={session.id}>
                      <td className="py-3 pr-3">
                        <div className="font-medium text-slate-900">{session.device_name || session.device_id || '-'}</div>
                        <div className="text-xs text-slate-500">{session.app_version || '-'} | {session.platform || '-'}</div>
                      </td>
                      <td className="py-3 pr-3">
                        <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                          {session.status}
                        </span>
                      </td>
                      <td className="py-3 pr-3 text-slate-600">{formatDate(session.last_heartbeat_at)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </DataPanel>

        <DataPanel title="Aturan Kunci Sesi">
          <div className="space-y-3 text-sm text-slate-600">
            <p>
              Android memakai screen pinning dan deteksi keluar aplikasi. iPhone memakai deteksi background karena aplikasi biasa tidak bisa memaksa single-app mode tanpa entitlement Apple.
            </p>
            <p>
              Saat keluar aplikasi terdeteksi, SBT mengunci layar ujian pada perangkat siswa. SIAPS hanya menampilkan jumlah kunci sesi dan aktivitas buka kunci, bukan daftar pelanggaran siswa.
            </p>
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800">
              Agar kunci benar-benar menahan siswa, gunakan mode keamanan <span className="font-semibold">Kode Pengawas</span> atau <span className="font-semibold">Kunci Ketat</span>.
            </div>
          </div>
        </DataPanel>
      </div>
    </div>
  );
};

const SummaryCard = ({ icon: Icon, label, value, tone }) => {
  const toneClass = {
    blue: 'bg-blue-50 text-blue-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber: 'bg-amber-50 text-amber-700',
    rose: 'bg-rose-50 text-rose-700',
  }[tone];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div className={`inline-flex rounded-lg p-2 ${toneClass}`}>
        <Icon className="h-5 w-5" />
      </div>
      <div className="mt-3 text-2xl font-bold text-slate-900">{value}</div>
      <div className="mt-1 text-sm text-slate-500">{label}</div>
    </div>
  );
};

const StatusBlock = ({ label, value }) => (
  <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{label}</p>
    <p className="mt-2 break-words text-sm font-semibold text-slate-900">{value}</p>
  </div>
);

const PolicyRow = ({ label, value, tone }) => (
  <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
    <span className="text-sm font-medium text-slate-700">{label}</span>
    <StatusBadge tone={tone}>{value}</StatusBadge>
  </div>
);

const StatusBadge = ({ tone = 'slate', children }) => {
  const toneClass = {
    slate: 'bg-slate-200 text-slate-700',
    emerald: 'bg-emerald-100 text-emerald-800',
    amber: 'bg-amber-100 text-amber-800',
    rose: 'bg-rose-100 text-rose-800',
  }[tone] || 'bg-slate-200 text-slate-700';

  return (
    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${toneClass}`}>
      {children}
    </span>
  );
};

const Field = ({ label, children }) => (
  <label className="block">
    <span className="mb-2 block text-sm font-medium text-slate-700">{label}</span>
    {children}
  </label>
);

const CheckboxField = ({ name, checked, onChange, label }) => (
  <label className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-700">
    <input
      type="checkbox"
      name={name}
      checked={Boolean(checked)}
      onChange={onChange}
      className="h-4 w-4 rounded border-slate-300 text-blue-600"
    />
    {label}
  </label>
);

const InfoPanel = ({ title, children }) => (
  <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">{title}</h3>
    <div className="mt-3">{children}</div>
  </div>
);

const DataPanel = ({ title, children }) => (
  <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 className="mb-4 text-lg font-semibold text-slate-900">{title}</h2>
    {children}
  </div>
);

export default SbtManagement;

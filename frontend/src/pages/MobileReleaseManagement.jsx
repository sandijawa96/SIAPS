import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useSnackbar } from 'notistack';
import { Download, ExternalLink, Pencil, RefreshCw, Save, Trash2, UploadCloud } from 'lucide-react';
import { mobileReleasesAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';

const createPolicyState = () => ({
  siswa: { enabled: false, update_mode: 'optional', minimum_supported_version: '', minimum_supported_build_number: '' },
  staff: { enabled: false, update_mode: 'optional', minimum_supported_version: '', minimum_supported_build_number: '' },
});

const createForm = () => ({
  app_key: 'siaps',
  app_name: 'SIAPS Mobile',
  app_description: '',
  target_audience: 'all',
  bundle_identifier: '',
  platform: 'android',
  release_channel: 'stable',
  public_version: '',
  build_number: '',
  download_url: '',
  update_mode: 'optional',
  minimum_supported_version: '',
  minimum_supported_build_number: '',
  is_active: true,
  is_published: true,
  release_notes: '',
  distribution_notes: '',
  asset_file: null,
  remove_asset: false,
  existing_download_kind: 'unavailable',
  existing_asset_name: '',
  existing_file_size_bytes: null,
  policy_overrides: createPolicyState(),
});

const platformOptions = [{ value: 'android', label: 'Android' }, { value: 'ios', label: 'iPhone / iOS' }];
const platformTabs = [
  { value: 'android', label: 'Android', description: 'Release APK Android' },
  { value: 'ios', label: 'iPhone', description: 'Release IPA iOS' },
];
const channelOptions = [{ value: 'stable', label: 'Stable' }, { value: 'internal', label: 'Internal' }, { value: 'beta', label: 'Beta' }];
const audienceOptions = [{ value: 'all', label: 'Semua Akun' }, { value: 'siswa', label: 'Siswa' }, { value: 'staff', label: 'Pegawai Sekolah' }];
const updateModeOptions = [{ value: 'optional', label: 'Optional' }, { value: 'required', label: 'Required' }];
const badgeColorByAudience = { all: 'bg-emerald-100 text-emerald-800', siswa: 'bg-sky-100 text-sky-800', staff: 'bg-amber-100 text-amber-800' };
const initialRolloutChecklist = [
  'Deploy backend dan frontend terbaru, lalu jalankan migration sebelum membuat release pertama.',
  'Siapkan APK/IPA SIAPS final dari build Flutter yang akan dirilis ke pengguna.',
  'Buat release baru dengan app key siaps, lalu upload file privat agar distribusi tetap di balik login.',
  'Samakan Public Version dan Build Number dengan versi asli hasil build Flutter.',
  'Untuk rollout pertama, gunakan mode update optional sampai unduhan dan instalasi tervalidasi di perangkat nyata.',
  'Setelah pengujian lolos, biarkan release tetap published dan active agar muncul di Pusat Aplikasi.',
];
const firstSiapsReleaseValues = [
  ['Nama Aplikasi', 'SIAPS Mobile'],
  ['App Key', 'siaps'],
  ['Audience', 'Semua Akun'],
  ['Platform', 'Android'],
  ['Channel', 'stable'],
  ['Mode Update Awal', 'optional'],
  ['Sumber Distribusi', 'Unggah file privat'],
];

const formatDateTime = (value) => {
  return formatServerDateTime(value, 'id-ID', { dateStyle: 'medium', timeStyle: 'short' }) || '-';
};

const formatFileSize = (value) => {
  const size = Number(value);
  if (!Number.isFinite(size) || size <= 0) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let current = size;
  let index = 0;
  while (current >= 1024 && index < units.length - 1) {
    current /= 1024;
    index += 1;
  }
  return `${current.toFixed(current >= 100 || index === 0 ? 0 : 1)} ${units[index]}`;
};

const resolveReleaseSaveError = (error, firstError) => {
  if (firstError) return firstError;

  if (error?.response?.status === 413) {
    return 'File terlalu besar untuk diterima server. Naikkan client_max_body_size Nginx serta upload_max_filesize/post_max_size PHP, atau gunakan URL distribusi eksternal.';
  }

  if (error?.code === 'ECONNABORTED') {
    return 'Upload melewati batas waktu koneksi. Coba ulang dengan jaringan lebih stabil, gunakan APK split-per-ABI yang lebih kecil, atau pakai URL distribusi eksternal.';
  }

  if (!error?.response) {
    return 'Koneksi upload terputus sebelum server memberi jawaban. Cek jaringan, batas reverse proxy, dan ukuran file.';
  }

  return error?.response?.data?.message || 'Gagal menyimpan distribusi aplikasi.';
};

const openDirectDownload = (downloadUrl) => {
  window.location.assign(downloadUrl);
};

const buildPayload = (form) => {
  const policies = Object.entries(form.policy_overrides)
    .filter(([, policy]) => policy.enabled)
    .map(([audience, policy]) => ({
      audience,
      update_mode: policy.update_mode,
      minimum_supported_version: policy.minimum_supported_version.trim() || null,
      minimum_supported_build_number: policy.minimum_supported_build_number ? Number(policy.minimum_supported_build_number) : null,
    }));

  const basePayload = {
    app_key: form.app_key.trim(),
    app_name: form.app_name.trim(),
    app_description: form.app_description.trim() || null,
    target_audience: form.target_audience,
    bundle_identifier: form.bundle_identifier.trim() || null,
    platform: form.platform,
    release_channel: form.release_channel,
    public_version: form.public_version.trim(),
    build_number: Number(form.build_number),
    download_url: form.download_url.trim() || null,
    update_mode: form.update_mode,
    minimum_supported_version: form.minimum_supported_version.trim() || null,
    minimum_supported_build_number: form.minimum_supported_build_number ? Number(form.minimum_supported_build_number) : null,
    is_active: Boolean(form.is_active),
    is_published: Boolean(form.is_published),
    release_notes: form.release_notes.trim() || null,
    distribution_notes: form.distribution_notes.trim() || null,
    remove_asset: Boolean(form.remove_asset),
    policies,
  };

  if (!form.asset_file) return basePayload;

  const formData = new FormData();
  Object.entries(basePayload).forEach(([key, value]) => {
    if (key === 'policies' || value === null || value === undefined || value === '') return;
    if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0');
      return;
    }
    formData.append(key, String(value));
  });
  formData.append('policies_json', JSON.stringify(policies));
  formData.append('asset_file', form.asset_file);
  return formData;
};

const MobileReleaseManagement = () => {
  const { enqueueSnackbar } = useSnackbar();
  const formSectionRef = useRef(null);
  const firstFieldRef = useRef(null);
  const [releases, setReleases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(null);
  const [saveFeedback, setSaveFeedback] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [filters, setFilters] = useState({ app_key: '', platform: 'android', release_channel: '', target_audience: '' });
  const [form, setForm] = useState(createForm);

  const loadReleases = async () => {
    setLoading(true);
    try {
      const response = await mobileReleasesAPI.getAll({
        app_key: filters.app_key || undefined,
        platform: filters.platform || undefined,
        release_channel: filters.release_channel || undefined,
        target_audience: filters.target_audience || undefined,
      });
      setReleases(Array.isArray(response?.data?.data) ? response.data.data : []);
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Gagal memuat distribusi aplikasi.', { variant: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadReleases(); }, [filters.app_key, filters.platform, filters.release_channel, filters.target_audience]);

  const stats = useMemo(() => ({
    totalReleases: releases.length,
    uniqueApps: new Set(releases.map((item) => item.app_key)).size,
    activePublished: releases.filter((item) => item.is_active && item.is_published).length,
    requiredUpdates: releases.filter((item) => item.is_active && (item.update_mode === 'required' || item.update_policies?.siswa?.update_mode === 'required' || item.update_policies?.staff?.update_mode === 'required')).length,
  }), [releases]);

  const activePlatformLabel = platformTabs.find((tab) => tab.value === filters.platform)?.label || 'Android';

  const scrollToForm = () => {
    window.requestAnimationFrame(() => {
      formSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(() => firstFieldRef.current?.focus({ preventScroll: true }), 350);
    });
  };

  const resetForm = (clearFeedback = true) => {
    setEditingId(null);
    setForm(createForm());
    setUploadProgress(null);
    if (clearFeedback) {
      setSaveFeedback(null);
    }
  };

  const startEdit = (release) => {
    const policies = release.update_policies || {};
    setEditingId(release.id);
    setForm({
      app_key: release.app_key || 'siaps',
      app_name: release.app_name || 'SIAPS Mobile',
      app_description: release.app_description || '',
      target_audience: release.target_audience || 'all',
      bundle_identifier: release.bundle_identifier || '',
      platform: release.platform || 'android',
      release_channel: release.release_channel || 'stable',
      public_version: release.public_version || '',
      build_number: release.build_number?.toString() || '',
      download_url: release.download_url || '',
      update_mode: release.update_mode || 'optional',
      minimum_supported_version: release.minimum_supported_version || '',
      minimum_supported_build_number: release.minimum_supported_build_number?.toString() || '',
      is_active: Boolean(release.is_active),
      is_published: Boolean(release.is_published),
      release_notes: release.release_notes || '',
      distribution_notes: release.distribution_notes || '',
      asset_file: null,
      remove_asset: false,
      existing_download_kind: release.download_kind || 'unavailable',
      existing_asset_name: release.asset_original_name || '',
      existing_file_size_bytes: release.file_size_bytes ?? null,
      policy_overrides: {
        siswa: { enabled: Boolean(policies.siswa), update_mode: policies.siswa?.update_mode || 'optional', minimum_supported_version: policies.siswa?.minimum_supported_version || '', minimum_supported_build_number: policies.siswa?.minimum_supported_build_number?.toString() || '' },
        staff: { enabled: Boolean(policies.staff), update_mode: policies.staff?.update_mode || 'optional', minimum_supported_version: policies.staff?.minimum_supported_version || '', minimum_supported_build_number: policies.staff?.minimum_supported_build_number?.toString() || '' },
      },
    });
    scrollToForm();
    enqueueSnackbar('Mode edit aktif. Ubah data di form, lalu klik Simpan Perubahan.', { variant: 'info' });
  };

  const handleChange = (event) => {
    const { name, type, value, checked, files } = event.target;
    if (type === 'file') {
      setUploadProgress(null);
      setSaveFeedback(null);
    }

    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : type === 'file' ? files?.[0] || null : value,
      ...(type === 'file' && files?.[0] ? { remove_asset: false } : {}),
    }));
  };

  const handlePolicyChange = (audience, field, value) => {
    setForm((current) => ({
      ...current,
      policy_overrides: {
        ...current.policy_overrides,
        [audience]: { ...current.policy_overrides[audience], [field]: value },
      },
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setSaveFeedback({
      type: 'info',
      message: form.asset_file
        ? `Mengunggah ${form.asset_file.name} (${formatFileSize(form.asset_file.size)}). Jangan tutup halaman sampai proses selesai.`
        : 'Menyimpan data release.',
    });
    setUploadProgress(form.asset_file ? 0 : null);

    try {
      const payload = buildPayload(form);
      const uploadOptions = payload instanceof FormData
        ? {
            onUploadProgress: (progressEvent) => {
              if (!progressEvent.total) {
                setUploadProgress(null);
                return;
              }

              setUploadProgress(Math.min(100, Math.round((progressEvent.loaded * 100) / progressEvent.total)));
            },
          }
        : {};
      const response = editingId
        ? await mobileReleasesAPI.update(editingId, payload, uploadOptions)
        : await mobileReleasesAPI.create(payload, uploadOptions);
      enqueueSnackbar(response?.data?.message || (editingId ? 'Distribusi aplikasi berhasil diperbarui.' : 'Distribusi aplikasi berhasil dibuat.'), { variant: 'success' });
      setSaveFeedback({
        type: 'success',
        message: response?.data?.message || (editingId ? 'Distribusi aplikasi berhasil diperbarui.' : 'Distribusi aplikasi berhasil dibuat.'),
      });
      resetForm(false);
      await loadReleases();
    } catch (error) {
      const apiErrors = error?.response?.data?.errors;
      const firstError = apiErrors ? Object.values(apiErrors).flat().find(Boolean) : null;
      const message = resolveReleaseSaveError(error, firstError);
      setSaveFeedback({ type: 'error', message });
      enqueueSnackbar(message, { variant: 'error' });
    } finally {
      setSaving(false);
      setUploadProgress(null);
    }
  };

  const handleDelete = async (release) => {
    if (!window.confirm(`Hapus release ${release.app_name} ${release.platform_label} ${release.public_version} (${release.build_number})?`)) return;
    try {
      const response = await mobileReleasesAPI.delete(release.id);
      enqueueSnackbar(response?.data?.message || 'Distribusi aplikasi berhasil dihapus.', { variant: 'success' });
      if (editingId === release.id) resetForm();
      await loadReleases();
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Gagal menghapus distribusi aplikasi.', { variant: 'error' });
    }
  };

  const handleManagedDownload = async (release) => {
    try {
      const response = await mobileReleasesAPI.getDownloadLink(release.id);
      const downloadUrl = response?.data?.data?.download_url;

      if (!downloadUrl) {
        throw new Error('Link unduhan sementara tidak tersedia.');
      }

      openDirectDownload(downloadUrl);
      enqueueSnackbar(
        release.platform === 'ios'
          ? `Installer iPhone ${release.app_name} dibuka. Gunakan Safari bila browser tidak merespons.`
          : `Unduhan ${release.app_name} disiapkan. Jika belum muncul, cek izin download browser.`,
        { variant: 'success' }
      );
    } catch (error) {
      enqueueSnackbar(
        error?.response?.data?.message || error?.message || 'Gagal mengunduh file privat.',
        { variant: 'error' }
      );
    }
  };

  return (
    <div className="space-y-6">
      <section className="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-900 text-white shadow-xl">
        <div className="grid gap-6 px-6 py-8 lg:grid-cols-[1.4fr,0.9fr] lg:px-8">
          <div className="space-y-4">
            <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-100"><UploadCloud size={14} />Internal App Catalog</div>
            <div className="space-y-2">
              <h1 className="text-3xl font-bold tracking-tight">Manajemen Distribusi Aplikasi</h1>
              <p className="max-w-2xl text-sm text-slate-200">Satu pusat distribusi untuk SIAPS Mobile, APK ujian, dan aplikasi internal lain. Release aktif tetap dikelola per app, per platform, dan per channel.</p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link to="/pusat-aplikasi" className="inline-flex items-center gap-2 rounded-full bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200"><Download size={16} />Lihat Pusat Aplikasi</Link>
              <button type="button" onClick={loadReleases} className="inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"><RefreshCw size={16} />Muat Ulang</button>
            </div>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-2xl border border-white/10 bg-white/10 p-4"><p className="text-xs uppercase tracking-[0.24em] text-cyan-100">Total Release</p><p className="mt-3 text-3xl font-semibold">{stats.totalReleases}</p></div>
            <div className="rounded-2xl border border-white/10 bg-white/10 p-4"><p className="text-xs uppercase tracking-[0.24em] text-cyan-100">Aplikasi</p><p className="mt-3 text-3xl font-semibold">{stats.uniqueApps}</p></div>
            <div className="rounded-2xl border border-white/10 bg-white/10 p-4"><p className="text-xs uppercase tracking-[0.24em] text-cyan-100">Aktif Dipublikasikan</p><p className="mt-3 text-3xl font-semibold">{stats.activePublished}</p></div>
            <div className="rounded-2xl border border-white/10 bg-white/10 p-4"><p className="text-xs uppercase tracking-[0.24em] text-cyan-100">Required Update</p><p className="mt-3 text-3xl font-semibold">{stats.requiredUpdates}</p></div>
          </div>
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-[1.1fr,0.9fr]">
        <article className="rounded-3xl border border-cyan-200 bg-cyan-50 p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="rounded-2xl bg-cyan-600 p-3 text-white">
              <UploadCloud size={20} />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-900">Checklist Deploy Awal SIAPS Mobile</h2>
              <p className="text-sm text-slate-600">Gunakan urutan ini untuk release pertama agar katalog web, update gate, dan file unduhan sinkron.</p>
            </div>
          </div>
          <ol className="mt-5 space-y-3 text-sm text-slate-700">
            {initialRolloutChecklist.map((item, index) => (
              <li key={item} className="flex gap-3">
                <span className="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-cyan-700 ring-1 ring-cyan-200">
                  {index + 1}
                </span>
                <span>{item}</span>
              </li>
            ))}
          </ol>
          <div className="mt-5 rounded-2xl border border-cyan-200 bg-white px-4 py-3 text-sm text-slate-600">
            Nilai versi harus mengikuti build Flutter asli. Contoh `version: 1.0.0+1` berarti isi <span className="font-semibold text-slate-900">Public Version = 1.0.0</span> dan <span className="font-semibold text-slate-900">Build Number = 1</span>.
          </div>
        </article>

        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Preset Release Pertama</h2>
          <p className="mt-1 text-sm text-slate-500">Jika fokus Anda saat ini hanya SIAPS Mobile, isi form pertama dengan baseline berikut.</p>
          <dl className="mt-5 space-y-3">
            {firstSiapsReleaseValues.map(([label, value]) => (
              <div key={label} className="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <dt className="text-sm font-medium text-slate-600">{label}</dt>
                <dd className="text-right text-sm font-semibold text-slate-900">{value}</dd>
              </div>
            ))}
          </dl>
          <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Kosongkan minimum version dan minimum build pada rollout pertama kecuali Anda memang ingin memaksa blok update untuk build lama.
          </div>
        </article>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.05fr,1.35fr]">
        <form ref={formSectionRef} onSubmit={handleSubmit} className={`space-y-5 rounded-3xl border bg-white p-6 shadow-sm transition ${editingId ? 'border-cyan-300 ring-4 ring-cyan-100' : 'border-slate-200'}`}>
          <div className="flex items-center justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold text-slate-900">{editingId ? 'Edit Release' : 'Release Baru'}</h2>
              <p className="text-sm text-slate-500">App metadata dipisahkan dari platform release. Untuk SIAPS, policy update dipakai oleh app update gate.</p>
            </div>
            {editingId ? <button type="button" onClick={resetForm} className="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Batal Edit</button> : null}
          </div>

          {editingId ? (
            <div className="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
              <span className="font-semibold">Mode edit aktif.</span> Form ini sedang mengubah release {form.app_name} {form.public_version ? `versi ${form.public_version}` : ''}. Field file tidak bisa otomatis terisi oleh browser; unggah file baru hanya jika ingin mengganti file.
            </div>
          ) : null}

          <div className="grid gap-4 md:grid-cols-2">
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Nama Aplikasi</span><input ref={firstFieldRef} name="app_name" value={form.app_name} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="Contoh: SIAPS Mobile" required /></label>
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">App Key</span><input name="app_key" value={form.app_key} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="siaps atau ujian" pattern="[a-z0-9]+(-[a-z0-9]+)*" required /></label>
          </div>

          <label className="block space-y-2"><span className="text-sm font-medium text-slate-700">Deskripsi Aplikasi</span><textarea name="app_description" value={form.app_description} onChange={handleChange} rows={3} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="Jelaskan fungsi aplikasi yang akan diunduh dari pusat aplikasi internal." /></label>

          <div className="grid gap-4 md:grid-cols-3">
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Audience</span><select name="target_audience" value={form.target_audience} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">{audienceOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Platform</span><select name="platform" value={form.platform} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">{platformOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Channel</span><select name="release_channel" value={form.release_channel} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">{channelOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
          </div>

          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-700">Bundle Identifier iOS</span>
            <input name="bundle_identifier" value={form.bundle_identifier} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="Contoh: id.sch.sman1sumbercirebon.sbt" />
            <span className="block text-xs text-slate-500">Wajib untuk instalasi OTA iPhone. Harus sama dengan bundle id IPA hasil Ksign.</span>
          </label>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Public Version</span><input name="public_version" value={form.public_version} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="1.2.3" required /></label>
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Build Number</span><input type="number" min="1" name="build_number" value={form.build_number} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="123" required /></label>
          </div>

          <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <div className="mb-4"><h3 className="text-sm font-semibold text-slate-900">Sumber Distribusi</h3><p className="text-xs text-slate-500">Pilih salah satu: unggah file privat atau tautan eksternal.</p></div>
            <div className="grid gap-4 md:grid-cols-2">
              <label className="space-y-2"><span className="text-sm font-medium text-slate-700">URL Distribusi Eksternal</span><input name="download_url" value={form.download_url} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="https://..." /></label>
              <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Unggah File</span><input type="file" name="asset_file" accept=".apk,.aab,.ipa,application/vnd.android.package-archive,application/octet-stream" onChange={handleChange} className="w-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700" /></label>
            </div>
            {form.asset_file ? (
              <div className="mt-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
                File dipilih: <span className="font-semibold">{form.asset_file.name}</span> ({formatFileSize(form.asset_file.size)}).
              </div>
            ) : null}
            {editingId && form.existing_download_kind === 'managed_asset' && form.existing_asset_name ? (
              <div className="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                File saat ini: <span className="font-semibold text-slate-800">{form.existing_asset_name}</span>
                {form.existing_file_size_bytes ? ` (${formatFileSize(form.existing_file_size_bytes)})` : ''}. Upload file baru hanya jika ingin mengganti file ini.
              </div>
            ) : null}
            {editingId && form.existing_download_kind === 'managed_asset' ? <label className="mt-4 flex items-center gap-3 text-sm text-slate-700"><input type="checkbox" name="remove_asset" checked={form.remove_asset} onChange={handleChange} className="h-4 w-4 rounded border-slate-300 text-cyan-600" />Hapus file privat saat simpan{form.existing_asset_name ? ` (${form.existing_asset_name})` : ''}</label> : null}
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Mode Update Global</span><select name="update_mode" value={form.update_mode} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500">{updateModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <div className="grid gap-4 md:grid-cols-2">
              <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Min Version</span><input name="minimum_supported_version" value={form.minimum_supported_version} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="Opsional" /></label>
              <label className="space-y-2"><span className="text-sm font-medium text-slate-700">Min Build</span><input type="number" min="1" name="minimum_supported_build_number" value={form.minimum_supported_build_number} onChange={handleChange} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="Opsional" /></label>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            {['siswa', 'staff'].map((audience) => {
              const policy = form.policy_overrides[audience];
              const label = audience === 'siswa' ? 'Override Siswa' : 'Override Pegawai';
              return (
                <div key={audience} className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                  <label className="flex items-center gap-3 text-sm font-medium text-slate-700"><input type="checkbox" checked={policy.enabled} onChange={(event) => handlePolicyChange(audience, 'enabled', event.target.checked)} className="h-4 w-4 rounded border-slate-300 text-cyan-600" />{label}</label>
                  <div className="mt-4 grid gap-3">
                    <select value={policy.update_mode} onChange={(event) => handlePolicyChange(audience, 'update_mode', event.target.value)} disabled={!policy.enabled} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500 disabled:bg-slate-100">{updateModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select>
                    <input value={policy.minimum_supported_version} onChange={(event) => handlePolicyChange(audience, 'minimum_supported_version', event.target.value)} disabled={!policy.enabled} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500 disabled:bg-slate-100" placeholder="Minimum version override" />
                    <input type="number" min="1" value={policy.minimum_supported_build_number} onChange={(event) => handlePolicyChange(audience, 'minimum_supported_build_number', event.target.value)} disabled={!policy.enabled} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500 disabled:bg-slate-100" placeholder="Minimum build override" />
                  </div>
                </div>
              );
            })}
          </div>

          <label className="block space-y-2"><span className="text-sm font-medium text-slate-700">Release Notes</span><textarea name="release_notes" value={form.release_notes} onChange={handleChange} rows={4} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" /></label>
          <label className="block space-y-2"><span className="text-sm font-medium text-slate-700">Instruksi Distribusi</span><textarea name="distribution_notes" value={form.distribution_notes} onChange={handleChange} rows={3} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" /></label>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"><input type="checkbox" name="is_published" checked={form.is_published} onChange={handleChange} className="h-4 w-4 rounded border-slate-300 text-cyan-600" />Publish release ini</label>
            <label className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"><input type="checkbox" name="is_active" checked={form.is_active} onChange={handleChange} className="h-4 w-4 rounded border-slate-300 text-cyan-600" />Jadikan release aktif</label>
          </div>

          {saveFeedback ? (
            <div className={`rounded-2xl border px-4 py-3 text-sm ${
              saveFeedback.type === 'error'
                ? 'border-rose-200 bg-rose-50 text-rose-800'
                : saveFeedback.type === 'success'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                  : 'border-blue-200 bg-blue-50 text-blue-800'
            }`}>
              {saveFeedback.message}
              {saving && form.asset_file ? (
                <div className="mt-3">
                  <div className="h-2 overflow-hidden rounded-full bg-white/70">
                    <div
                      className="h-full rounded-full bg-blue-600 transition-all"
                      style={{ width: `${uploadProgress ?? 10}%` }}
                    />
                  </div>
                  <div className="mt-2 text-xs font-medium">
                    {uploadProgress === null ? 'Mengunggah file...' : `Progress upload ${uploadProgress}%`}
                  </div>
                </div>
              ) : null}
            </div>
          ) : null}

          <button type="submit" disabled={saving} className="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"><Save size={16} />{saving ? (form.asset_file ? `Mengunggah${uploadProgress !== null ? ` ${uploadProgress}%` : '...'}` : 'Menyimpan...') : editingId ? 'Simpan Perubahan' : 'Buat Release'}</button>
        </form>

        <section className="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Daftar Release {activePlatformLabel}</h2>
            <p className="mt-1 text-sm text-slate-500">
              Android dan iPhone dipisahkan agar file APK dan IPA tidak tercampur saat dicek atau diuji pasang.
            </p>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-2">
            <div className="grid gap-2 sm:grid-cols-2">
              {platformTabs.map((tab) => {
                const isActive = filters.platform === tab.value;
                return (
                  <button
                    key={tab.value}
                    type="button"
                    onClick={() => setFilters((current) => ({ ...current, platform: tab.value }))}
                    className={`rounded-xl px-4 py-3 text-left transition ${
                      isActive
                        ? 'bg-slate-950 text-white shadow'
                        : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100'
                    }`}
                  >
                    <span className="block text-sm font-semibold">{tab.label}</span>
                    <span className={`mt-1 block text-xs ${isActive ? 'text-slate-300' : 'text-slate-500'}`}>
                      {tab.description}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <label className="min-w-[180px] flex-1 space-y-2"><span className="text-sm font-medium text-slate-700">Filter App Key</span><input value={filters.app_key} onChange={(event) => setFilters((current) => ({ ...current, app_key: event.target.value }))} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500" placeholder="siaps / ujian" /></label>
            <label className="min-w-[160px] flex-1 space-y-2"><span className="text-sm font-medium text-slate-700">Channel</span><select value={filters.release_channel} onChange={(event) => setFilters((current) => ({ ...current, release_channel: event.target.value }))} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500"><option value="">Semua</option>{channelOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
            <label className="min-w-[180px] flex-1 space-y-2"><span className="text-sm font-medium text-slate-700">Audience</span><select value={filters.target_audience} onChange={(event) => setFilters((current) => ({ ...current, target_audience: event.target.value }))} className="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-500"><option value="">Semua</option>{audienceOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
          </div>
          {loading ? (
            <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Memuat distribusi aplikasi...</div>
          ) : releases.length === 0 ? (
            <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Belum ada release {activePlatformLabel} yang cocok dengan filter.</div>
          ) : (
            <div className="space-y-4">
              {releases.map((release) => {
                const effectiveDownloadUrl = release.effective_download_url || release.download_url;
                return (
                  <article key={release.id} className={`rounded-3xl border p-5 transition ${editingId === release.id ? 'border-cyan-300 bg-cyan-50 ring-4 ring-cyan-100' : 'border-slate-200 bg-slate-50'}`}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                      <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">{release.app_name}</span>
                          <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">{release.platform_label}</span>
                          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${badgeColorByAudience[release.target_audience] || badgeColorByAudience.all}`}>{release.target_audience_label}</span>
                        </div>
                        <div>
                          <h3 className="text-lg font-semibold text-slate-900">{release.public_version} ({release.build_number})</h3>
                          <p className="text-sm text-slate-500">App key: {release.app_key} | Channel: {release.release_channel} | Publish: {formatDateTime(release.published_at)}</p>
                        </div>
                        <p className="max-w-3xl text-sm text-slate-600">{release.app_description || 'Tidak ada deskripsi aplikasi.'}</p>
                      </div>

                      <div className="flex flex-wrap gap-2">
                        {effectiveDownloadUrl ? (release.download_kind === 'managed_asset' || release.platform === 'ios') ? (
                          <button type="button" onClick={() => handleManagedDownload(release)} className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                            <Download size={16} />
                            {release.platform === 'ios' ? 'Uji Pasang' : 'Uji Unduh'}
                          </button>
                        ) : (
                          <a href={effectiveDownloadUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                            <ExternalLink size={16} />
                            Uji Link
                          </a>
                        ) : null}
                        <button type="button" onClick={() => startEdit(release)} className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><Pencil size={16} />Edit</button>
                        <button type="button" onClick={() => handleDelete(release)} className="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"><Trash2 size={16} />Hapus</button>
                      </div>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status</p><p className="mt-2 text-sm font-medium text-slate-800">{release.is_active ? 'Aktif' : 'Nonaktif'} / {release.is_published ? 'Published' : 'Draft'}</p></div>
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sumber</p><p className="mt-2 text-sm font-medium text-slate-800">{release.download_kind === 'managed_asset' ? 'File privat' : release.download_kind === 'external_url' ? 'URL eksternal' : '-'}</p></div>
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ukuran</p><p className="mt-2 text-sm font-medium text-slate-800">{formatFileSize(release.file_size_bytes)}</p></div>
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Policy</p><p className="mt-2 text-sm font-medium text-slate-800">{release.update_mode}</p></div>
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Release Notes</p><p className="mt-2 whitespace-pre-line text-sm text-slate-600">{release.release_notes || 'Belum ada release notes.'}</p></div>
                      <div className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Instruksi Distribusi</p><p className="mt-2 whitespace-pre-line text-sm text-slate-600">{release.distribution_notes || 'Belum ada instruksi distribusi.'}</p></div>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </section>
      </section>
    </div>
  );
};

export default MobileReleaseManagement;

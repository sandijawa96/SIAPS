import React, { useEffect, useState } from 'react';
import { Loader2, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { toast } from 'react-hot-toast';
import Dialog from '../ui/Dialog';
import { kelasAPI, simpleAttendanceAPI, siswaAPI, tingkatAPI } from '../../services/api';

const unwrapRows = (response) => {
  const payload = response?.data?.data ?? response?.data ?? [];
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
};

const thresholdModeOptions = [
  { value: 'monitor_only', label: 'Monitoring saja' },
  { value: 'alertable', label: 'Trigger alert otomatis' },
];

const buildEmptyForm = (defaults = {}) => ({
  id: null,
  scope_type: 'tingkat',
  target_tingkat_id: '',
  target_kelas_id: '',
  target_user_id: '',
  target_user: null,
  is_active: true,
  discipline_thresholds_enabled: Boolean(defaults.discipline_thresholds_enabled ?? true),
  total_violation_minutes_semester_limit: Number(defaults.total_violation_minutes_semester_limit ?? 1200),
  semester_total_violation_mode: defaults.semester_total_violation_mode || 'monitor_only',
  notify_wali_kelas_on_total_violation_limit: Boolean(defaults.notify_wali_kelas_on_total_violation_limit ?? false),
  notify_kesiswaan_on_total_violation_limit: Boolean(defaults.notify_kesiswaan_on_total_violation_limit ?? false),
  alpha_days_semester_limit: Number(defaults.alpha_days_semester_limit ?? 8),
  semester_alpha_mode: defaults.semester_alpha_mode || 'alertable',
  late_minutes_monthly_limit: Number(defaults.late_minutes_monthly_limit ?? 120),
  monthly_late_mode: defaults.monthly_late_mode || 'monitor_only',
  notify_wali_kelas_on_late_limit: Boolean(defaults.notify_wali_kelas_on_late_limit ?? false),
  notify_kesiswaan_on_late_limit: Boolean(defaults.notify_kesiswaan_on_late_limit ?? false),
  notify_wali_kelas_on_alpha_limit: Boolean(defaults.notify_wali_kelas_on_alpha_limit ?? true),
  notify_kesiswaan_on_alpha_limit: Boolean(defaults.notify_kesiswaan_on_alpha_limit ?? true),
  notes: '',
});

const buildEditForm = (override) => ({
  id: override.id,
  scope_type: override.scope_type,
  target_tingkat_id:
    override.scope_type === 'kelas'
      ? String(override.target_kelas?.tingkat_id ?? '')
      : String(override.target_tingkat_id ?? ''),
  target_kelas_id: String(override.target_kelas_id ?? ''),
  target_user_id: String(override.target_user_id ?? ''),
  target_user: override.target_user || null,
  is_active: Boolean(override.is_active),
  discipline_thresholds_enabled: Boolean(override.discipline_thresholds_enabled),
  total_violation_minutes_semester_limit: Number(override.total_violation_minutes_semester_limit ?? 0),
  semester_total_violation_mode: override.semester_total_violation_mode || 'monitor_only',
  notify_wali_kelas_on_total_violation_limit: Boolean(override.notify_wali_kelas_on_total_violation_limit),
  notify_kesiswaan_on_total_violation_limit: Boolean(override.notify_kesiswaan_on_total_violation_limit),
  alpha_days_semester_limit: Number(override.alpha_days_semester_limit ?? 0),
  semester_alpha_mode: override.semester_alpha_mode || 'alertable',
  late_minutes_monthly_limit: Number(override.late_minutes_monthly_limit ?? 0),
  monthly_late_mode: override.monthly_late_mode || 'monitor_only',
  notify_wali_kelas_on_late_limit: Boolean(override.notify_wali_kelas_on_late_limit),
  notify_kesiswaan_on_late_limit: Boolean(override.notify_kesiswaan_on_late_limit),
  notify_wali_kelas_on_alpha_limit: Boolean(override.notify_wali_kelas_on_alpha_limit),
  notify_kesiswaan_on_alpha_limit: Boolean(override.notify_kesiswaan_on_alpha_limit),
  notes: override.notes || '',
});

const summaryText = (override) => {
  if (!override.discipline_thresholds_enabled) {
    return 'Threshold v2 nonaktif untuk target ini.';
  }

  return `Semester ${override.total_violation_minutes_semester_limit} menit, alpha ${override.alpha_days_semester_limit} hari, bulanan ${override.late_minutes_monthly_limit} menit.`;
};

const getOverrideSearchText = (override) => [
  override.scope_label,
  override.scope_type,
  override.notes,
  override.target_tingkat?.nama,
  override.target_tingkat?.nama_tingkat,
  override.target_kelas?.nama_kelas,
  override.target_kelas?.nama,
  override.target_user?.nama_lengkap,
  override.target_user?.name,
  override.target_user?.nis,
  override.target_user?.nisn,
].filter(Boolean).join(' ').toLowerCase();

const DisciplineOverrideDialog = ({ isOpen, onClose, defaultConfig, onChanged }) => {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [overrides, setOverrides] = useState([]);
  const [tingkatOptions, setTingkatOptions] = useState([]);
  const [kelasOptions, setKelasOptions] = useState([]);
  const [studentResults, setStudentResults] = useState([]);
  const [studentSearch, setStudentSearch] = useState('');
  const [overrideListSearch, setOverrideListSearch] = useState('');
  const [overrideScopeFilter, setOverrideScopeFilter] = useState('all');
  const [overrideStatusFilter, setOverrideStatusFilter] = useState('all');
  const [form, setForm] = useState(() => buildEmptyForm(defaultConfig));

  const activeOverrideCount = overrides.filter((item) => item.is_active).length;
  const normalizedOverrideSearch = overrideListSearch.trim().toLowerCase();
  const filteredOverrides = overrides.filter((override) => {
    const matchesSearch = !normalizedOverrideSearch || getOverrideSearchText(override).includes(normalizedOverrideSearch);
    const matchesScope = overrideScopeFilter === 'all' || override.scope_type === overrideScopeFilter;
    const matchesStatus =
      overrideStatusFilter === 'all'
      || (overrideStatusFilter === 'active' && override.is_active)
      || (overrideStatusFilter === 'inactive' && !override.is_active);

    return matchesSearch && matchesScope && matchesStatus;
  });

  const clearListFilters = () => {
    setOverrideListSearch('');
    setOverrideScopeFilter('all');
    setOverrideStatusFilter('all');
  };

  const publishSummary = (rows) => {
    if (!onChanged) return;

    onChanged({
      total: rows.length,
      active: rows.filter((item) => item.is_active).length,
    });
  };

  const loadOverrides = async () => {
    const response = await simpleAttendanceAPI.getDisciplineOverrides({ include_inactive: true });
    const rows = Array.isArray(response?.data?.data) ? response.data.data : [];
    setOverrides(rows);
    publishSummary(rows);
    return rows;
  };

  const resetForm = () => {
    setForm(buildEmptyForm(defaultConfig));
    setStudentSearch('');
    setStudentResults([]);
  };

  const startEdit = (override) => {
    setForm(buildEditForm(override));
    setStudentSearch(override.target_user?.nama_lengkap || '');
  };

  const handleScopeChange = (scopeType) => {
    setForm((prev) => ({
      ...prev,
      scope_type: scopeType,
      target_tingkat_id: '',
      target_kelas_id: '',
      target_user_id: '',
      target_user: null,
    }));
    setStudentSearch('');
    setStudentResults([]);
  };

  const handleThresholdNumber = (field, value, max) => {
    const parsed = Number(value);
    const bounded = Number.isFinite(parsed) ? Math.max(0, Math.min(parsed, max)) : 0;

    setForm((prev) => ({ ...prev, [field]: bounded }));
  };

  useEffect(() => {
    if (!isOpen) {
      return undefined;
    }

    let isMounted = true;

    const loadData = async () => {
      setLoading(true);
      try {
        const [overrideResponse, tingkatResponse] = await Promise.all([
          simpleAttendanceAPI.getDisciplineOverrides({ include_inactive: true }),
          tingkatAPI.getAll({ is_active: true, per_page: 200 }),
        ]);

        if (!isMounted) return;

        const rows = Array.isArray(overrideResponse?.data?.data) ? overrideResponse.data.data : [];
        setOverrides(rows);
        publishSummary(rows);
        setTingkatOptions(unwrapRows(tingkatResponse));
        setForm(buildEmptyForm(defaultConfig));
        setStudentSearch('');
        setStudentResults([]);
      } catch (error) {
        console.error('Failed loading discipline overrides:', error);
        toast.error('Gagal memuat override disiplin');
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    loadData();

    return () => {
      isMounted = false;
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen || form.scope_type !== 'kelas' || !form.target_tingkat_id) {
      setKelasOptions([]);
      return undefined;
    }

    let isMounted = true;

    const loadKelas = async () => {
      try {
        const response = await kelasAPI.getByTingkat(form.target_tingkat_id);
        if (!isMounted) return;
        setKelasOptions(unwrapRows(response));
      } catch (error) {
        console.warn('Failed loading kelas options for discipline override:', error?.message || error);
        if (isMounted) {
          setKelasOptions([]);
        }
      }
    };

    loadKelas();

    return () => {
      isMounted = false;
    };
  }, [isOpen, form.scope_type, form.target_tingkat_id]);

  useEffect(() => {
    if (!isOpen || form.scope_type !== 'user') {
      setStudentResults([]);
      return undefined;
    }

    const keyword = studentSearch.trim();
    if (keyword.length < 2) {
      setStudentResults([]);
      return undefined;
    }

    let isMounted = true;
    const timer = setTimeout(async () => {
      try {
        const response = await siswaAPI.getAll({
          search: keyword,
          is_active: true,
          per_page: 12,
        });
        if (!isMounted) return;
        setStudentResults(unwrapRows(response));
      } catch (error) {
        console.warn('Failed searching students for discipline override:', error?.message || error);
        if (isMounted) {
          setStudentResults([]);
        }
      }
    }, 300);

    return () => {
      isMounted = false;
      clearTimeout(timer);
    };
  }, [isOpen, form.scope_type, studentSearch]);

  const buildPayload = () => ({
    scope_type: form.scope_type,
    target_tingkat_id: form.target_tingkat_id ? Number(form.target_tingkat_id) : null,
    target_kelas_id: form.target_kelas_id ? Number(form.target_kelas_id) : null,
    target_user_id: form.target_user_id ? Number(form.target_user_id) : null,
    is_active: Boolean(form.is_active),
    discipline_thresholds_enabled: Boolean(form.discipline_thresholds_enabled),
    total_violation_minutes_semester_limit: Number(form.total_violation_minutes_semester_limit ?? 0),
    semester_total_violation_mode: form.semester_total_violation_mode,
    notify_wali_kelas_on_total_violation_limit: Boolean(form.notify_wali_kelas_on_total_violation_limit),
    notify_kesiswaan_on_total_violation_limit: Boolean(form.notify_kesiswaan_on_total_violation_limit),
    alpha_days_semester_limit: Number(form.alpha_days_semester_limit ?? 0),
    semester_alpha_mode: form.semester_alpha_mode,
    late_minutes_monthly_limit: Number(form.late_minutes_monthly_limit ?? 0),
    monthly_late_mode: form.monthly_late_mode,
    notify_wali_kelas_on_late_limit: Boolean(form.notify_wali_kelas_on_late_limit),
    notify_kesiswaan_on_late_limit: Boolean(form.notify_kesiswaan_on_late_limit),
    notify_wali_kelas_on_alpha_limit: Boolean(form.notify_wali_kelas_on_alpha_limit),
    notify_kesiswaan_on_alpha_limit: Boolean(form.notify_kesiswaan_on_alpha_limit),
    notes: form.notes?.trim() || null,
  });

  const validateForm = () => {
    if (form.scope_type === 'tingkat' && !form.target_tingkat_id) {
      toast.error('Pilih tingkat target override');
      return false;
    }

    if (form.scope_type === 'kelas' && !form.target_kelas_id) {
      toast.error('Pilih kelas target override');
      return false;
    }

    if (form.scope_type === 'user' && !form.target_user_id) {
      toast.error('Pilih siswa target override');
      return false;
    }

    return true;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!validateForm()) {
      return;
    }

    setSaving(true);
    try {
      const payload = buildPayload();
      const response = form.id
        ? await simpleAttendanceAPI.updateDisciplineOverride(form.id, payload)
        : await simpleAttendanceAPI.createDisciplineOverride(payload);

      const saved = response?.data?.data;
      const rows = await loadOverrides();
      if (saved?.id) {
        const matched = rows.find((item) => item.id === saved.id);
        if (matched) {
          setForm(buildEditForm(matched));
          setStudentSearch(matched.target_user?.nama_lengkap || '');
        }
      }
      toast.success(form.id ? 'Override disiplin diperbarui' : 'Override disiplin disimpan');
    } catch (error) {
      console.error('Failed saving discipline override:', error);
      toast.error(error?.response?.data?.message || 'Gagal menyimpan override disiplin');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (overrideId) => {
    if (!window.confirm('Hapus override disiplin ini?')) {
      return;
    }

    setSaving(true);
    try {
      await simpleAttendanceAPI.deleteDisciplineOverride(overrideId);
      await loadOverrides();
      if (form.id === overrideId) {
        resetForm();
      }
      toast.success('Override disiplin dihapus');
    } catch (error) {
      console.error('Failed deleting discipline override:', error);
      toast.error(error?.response?.data?.message || 'Gagal menghapus override disiplin');
    } finally {
      setSaving(false);
    }
  };

  const renderRouting = (modeField, waliField, kesiswaanField) => {
    const alertable = form.discipline_thresholds_enabled && form[modeField] === 'alertable';

    if (!form.discipline_thresholds_enabled) {
      return <p className="mt-2 text-xs text-gray-500">Threshold v2 override tidak aktif.</p>;
    }

    if (!alertable) {
      return <p className="mt-2 text-xs text-gray-500">Mode monitoring saja. Alert otomatis tidak akan dikirim.</p>;
    }

    return (
      <div className="mt-2 space-y-2">
        <label className="flex items-center gap-2 text-xs text-gray-700">
          <input
            type="checkbox"
            checked={Boolean(form[waliField])}
            onChange={(event) => setForm((prev) => ({ ...prev, [waliField]: event.target.checked }))}
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          Notifikasi internal ke wali kelas
        </label>
        <label className="flex items-center gap-2 text-xs text-gray-700">
          <input
            type="checkbox"
            checked={Boolean(form[kesiswaanField])}
            onChange={(event) => setForm((prev) => ({ ...prev, [kesiswaanField]: event.target.checked }))}
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          Notifikasi internal ke kesiswaan
        </label>
      </div>
    );
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="Override Batas Disiplin Siswa" size="2xl">
      {loading ? (
        <div className="flex items-center gap-3 text-sm text-gray-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          Memuat override disiplin...
        </div>
      ) : (
        <div className="space-y-4">
          <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
            Prioritas runtime override: <span className="font-semibold">siswa &gt; kelas &gt; tingkat &gt; global</span>. Simpan di dialog ini hanya mengubah target override, tidak menyentuh save global utama.
          </div>

          <div className="grid grid-cols-1 xl:grid-cols-[0.95fr_1.35fr] gap-4">
            <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-gray-900">Daftar Override</p>
                  <p className="text-xs text-gray-600 mt-1">Total {overrides.length} override, {activeOverrideCount} aktif.</p>
                </div>
                <button
                  type="button"
                  onClick={resetForm}
                  className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100"
                >
                  <Plus className="h-3.5 w-3.5" />
                  Override Baru
                </button>
              </div>

              <div className="rounded-lg border border-gray-200 bg-white p-3 space-y-2">
                <input
                  type="text"
                  value={overrideListSearch}
                  onChange={(event) => setOverrideListSearch(event.target.value)}
                  placeholder="Cari target, siswa, NIS, kelas, catatan..."
                  className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <div className="grid grid-cols-2 gap-2">
                  <select
                    value={overrideScopeFilter}
                    onChange={(event) => setOverrideScopeFilter(event.target.value)}
                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="all">Semua target</option>
                    <option value="tingkat">Tingkat</option>
                    <option value="kelas">Kelas</option>
                    <option value="user">Siswa</option>
                  </select>
                  <select
                    value={overrideStatusFilter}
                    onChange={(event) => setOverrideStatusFilter(event.target.value)}
                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="all">Semua status</option>
                    <option value="active">Aktif saja</option>
                    <option value="inactive">Nonaktif saja</option>
                  </select>
                </div>
                <div className="flex items-center justify-between gap-2 text-[11px] text-gray-500">
                  <span>Menampilkan {filteredOverrides.length} dari {overrides.length} override</span>
                  {(normalizedOverrideSearch || overrideScopeFilter !== 'all' || overrideStatusFilter !== 'all') && (
                    <button type="button" onClick={clearListFilters} className="font-semibold text-blue-700 hover:text-blue-800">
                      Hapus filter
                    </button>
                  )}
                </div>
              </div>

              <div className="space-y-2 max-h-[560px] overflow-y-auto pr-1">
                {overrides.length === 0 ? (
                  <div className="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-4 text-xs text-gray-600">
                    Belum ada override disiplin khusus.
                  </div>
                ) : filteredOverrides.length === 0 ? (
                  <div className="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-4 text-xs text-gray-600">
                    Tidak ada override yang cocok dengan filter saat ini.
                  </div>
                ) : (
                  filteredOverrides.map((override) => {
                    const editing = form.id === override.id;
                    return (
                      <div key={override.id} className={`rounded-lg border px-3 py-3 ${editing ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white'}`}>
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="text-sm font-semibold text-gray-900">{override.scope_label}</p>
                            <p className="mt-1 text-xs text-gray-600">{summaryText(override)}</p>
                            <p className="mt-2 text-[11px] text-gray-500">Mode: semester {override.semester_total_violation_mode}, alpha {override.semester_alpha_mode}, bulanan {override.monthly_late_mode}</p>
                          </div>
                          <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${override.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                            {override.is_active ? 'Aktif' : 'Nonaktif'}
                          </span>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => startEdit(override)}
                            className="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                          >
                            <Pencil className="h-3.5 w-3.5" />
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(override.id)}
                            disabled={saving}
                            className="inline-flex items-center gap-1 rounded-md border border-red-200 px-2.5 py-1.5 text-xs text-red-700 hover:bg-red-50 disabled:opacity-60"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                            Hapus
                          </button>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </div>

            <form onSubmit={handleSubmit} className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-gray-900">{form.id ? 'Edit Override' : 'Tambah Override'}</p>
                  <p className="text-xs text-gray-600 mt-1">Override ini menimpa policy disiplin global hanya untuk target yang dipilih.</p>
                </div>
                <label className="inline-flex items-center gap-2 text-xs text-gray-700">
                  <input
                    type="checkbox"
                    checked={Boolean(form.is_active)}
                    onChange={(event) => setForm((prev) => ({ ...prev, is_active: event.target.checked }))}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  Aktif
                </label>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                {[
                  { value: 'tingkat', label: 'Per Tingkat' },
                  { value: 'kelas', label: 'Per Kelas' },
                  { value: 'user', label: 'Per Siswa' },
                ].map((option) => (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => handleScopeChange(option.value)}
                    className={`rounded-lg border px-3 py-2 text-sm font-medium ${form.scope_type === option.value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'}`}
                  >
                    {option.label}
                  </button>
                ))}
              </div>

              {form.scope_type === 'tingkat' && (
                <label className="block text-sm text-gray-700">
                  <span className="mb-1 block text-xs text-gray-600">Tingkat target</span>
                  <select
                    value={form.target_tingkat_id}
                    onChange={(event) => setForm((prev) => ({ ...prev, target_tingkat_id: event.target.value }))}
                    className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih tingkat</option>
                    {tingkatOptions.map((tingkat) => (
                      <option key={tingkat.id} value={tingkat.id}>{tingkat.nama}</option>
                    ))}
                  </select>
                </label>
              )}

              {form.scope_type === 'kelas' && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <label className="block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Filter tingkat</span>
                    <select
                      value={form.target_tingkat_id}
                      onChange={(event) => setForm((prev) => ({ ...prev, target_tingkat_id: event.target.value, target_kelas_id: '' }))}
                      className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                      <option value="">Pilih tingkat</option>
                      {tingkatOptions.map((tingkat) => (
                        <option key={tingkat.id} value={tingkat.id}>{tingkat.nama}</option>
                      ))}
                    </select>
                  </label>
                  <label className="block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Kelas target</span>
                    <select
                      value={form.target_kelas_id}
                      onChange={(event) => setForm((prev) => ({ ...prev, target_kelas_id: event.target.value }))}
                      className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                      <option value="">Pilih kelas</option>
                      {kelasOptions.map((kelas) => (
                        <option key={kelas.id} value={kelas.id}>{kelas.nama_kelas}</option>
                      ))}
                    </select>
                  </label>
                </div>
              )}

              {form.scope_type === 'user' && (
                <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                  <label className="block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Cari siswa</span>
                    <input
                      type="text"
                      value={studentSearch}
                      onChange={(event) => setStudentSearch(event.target.value)}
                      placeholder="Cari nama, NIS, atau NISN"
                      className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                  </label>

                  {form.target_user && (
                    <div className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                      <span className="font-semibold">Target terpilih:</span> {form.target_user.nama_lengkap}
                      {form.target_user.nis ? ` • NIS ${form.target_user.nis}` : ''}
                    </div>
                  )}

                  <div className="space-y-2 max-h-48 overflow-y-auto">
                    {studentResults.map((student) => (
                      <button
                        key={student.id}
                        type="button"
                        onClick={() => setForm((prev) => ({
                          ...prev,
                          target_user_id: String(student.id),
                          target_user: {
                            id: student.id,
                            nama_lengkap: student.nama_lengkap,
                            nis: student.nis,
                            nisn: student.nisn,
                          },
                        }))}
                        className="flex w-full items-start justify-between rounded-md border border-gray-200 bg-white px-3 py-2 text-left hover:border-blue-300 hover:bg-blue-50"
                      >
                        <span>
                          <span className="block text-sm font-medium text-gray-900">{student.nama_lengkap}</span>
                          <span className="mt-1 block text-xs text-gray-500">NIS {student.nis || '-'} • NISN {student.nisn || '-'}</span>
                        </span>
                        <Users className="mt-0.5 h-4 w-4 text-gray-400" />
                      </button>
                    ))}
                    {studentSearch.trim().length >= 2 && studentResults.length === 0 && (
                      <div className="rounded-md border border-dashed border-gray-300 bg-white px-3 py-2 text-xs text-gray-500">
                        Tidak ada siswa yang cocok dengan pencarian.
                      </div>
                    )}
                  </div>
                </div>
              )}

              <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4">
                <label className="flex items-start gap-3 text-sm text-gray-800">
                  <input
                    type="checkbox"
                    checked={Boolean(form.discipline_thresholds_enabled)}
                    onChange={(event) => setForm((prev) => ({ ...prev, discipline_thresholds_enabled: event.target.checked }))}
                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>
                    <span className="block font-semibold text-gray-900">Aktifkan threshold v2 untuk target override ini</span>
                    <span className="mt-1 block text-xs text-gray-600">Jika nonaktif, target override ini kembali memakai fallback legacy.</span>
                  </span>
                </label>
              </div>

              <div className="grid grid-cols-1 xl:grid-cols-3 gap-3">
                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                  <p className="text-sm font-semibold text-gray-900">A. Total Pelanggaran Semester</p>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Batas menit semester</span>
                    <input type="number" min={0} max={100000} value={form.total_violation_minutes_semester_limit} onChange={(event) => handleThresholdNumber('total_violation_minutes_semester_limit', event.target.value, 100000)} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                  </label>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Mode indikator</span>
                    <select value={form.semester_total_violation_mode} onChange={(event) => setForm((prev) => ({ ...prev, semester_total_violation_mode: event.target.value }))} className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                      {thresholdModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                  </label>
                  <div className="mt-3 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-3">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Routing alert</div>
                    {renderRouting('semester_total_violation_mode', 'notify_wali_kelas_on_total_violation_limit', 'notify_kesiswaan_on_total_violation_limit')}
                  </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                  <p className="text-sm font-semibold text-gray-900">B. Alpha Semester</p>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Batas hari alpha semester</span>
                    <input type="number" min={0} max={365} value={form.alpha_days_semester_limit} onChange={(event) => handleThresholdNumber('alpha_days_semester_limit', event.target.value, 365)} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                  </label>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Mode indikator</span>
                    <select value={form.semester_alpha_mode} onChange={(event) => setForm((prev) => ({ ...prev, semester_alpha_mode: event.target.value }))} className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                      {thresholdModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                  </label>
                  <div className="mt-3 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-3">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Routing alert</div>
                    {renderRouting('semester_alpha_mode', 'notify_wali_kelas_on_alpha_limit', 'notify_kesiswaan_on_alpha_limit')}
                  </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                  <p className="text-sm font-semibold text-gray-900">C. Keterlambatan Bulanan</p>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Batas menit terlambat bulanan</span>
                    <input type="number" min={0} max={100000} value={form.late_minutes_monthly_limit} onChange={(event) => handleThresholdNumber('late_minutes_monthly_limit', event.target.value, 100000)} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                  </label>
                  <label className="mt-3 block text-sm text-gray-700">
                    <span className="mb-1 block text-xs text-gray-600">Mode indikator</span>
                    <select value={form.monthly_late_mode} onChange={(event) => setForm((prev) => ({ ...prev, monthly_late_mode: event.target.value }))} className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                      {thresholdModeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                  </label>
                  <div className="mt-3 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-3">
                    <div className="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Routing alert</div>
                    {renderRouting('monthly_late_mode', 'notify_wali_kelas_on_late_limit', 'notify_kesiswaan_on_late_limit')}
                  </div>
                </div>
              </div>

              <label className="block text-sm text-gray-700">
                <span className="mb-1 block text-xs text-gray-600">Catatan override</span>
                <textarea value={form.notes} onChange={(event) => setForm((prev) => ({ ...prev, notes: event.target.value }))} rows={3} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Mis. aturan khusus siswa tingkat akhir" />
              </label>

              <div className="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                <button type="button" onClick={resetForm} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Reset Form</button>
                <button type="submit" disabled={saving} className="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                  {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                  {form.id ? 'Simpan Perubahan Override' : 'Simpan Override'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </Dialog>
  );
};

export default DisciplineOverrideDialog;

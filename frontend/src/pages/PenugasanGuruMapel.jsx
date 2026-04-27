import React from 'react';
import {
  Autocomplete,
  Alert,
  Box,
  Button,
  Chip,
  FormControl,
  IconButton,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
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
  BookOpen,
  Calendar,
  Clock3,
  Download,
  Edit,
  GraduationCap,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  Upload,
  User,
  X,
} from 'lucide-react';
import { guruMapelAPI } from '../services/guruMapelService';
import { getServerDateString, getServerNowEpochMs } from '../services/serverClock';
import { useAuth } from '../hooks/useAuth';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';
import ExportModalAkademik from '../components/ExportModalAkademik';
import ImportModalAkademik from '../components/ImportModalAkademik';

const emptyForm = {
  guru_id: '',
  mata_pelajaran_id: '',
  kelas_id: '',
  tahun_ajaran_id: '',
  jam_per_minggu: 0,
  status: 'aktif',
};

const GURU_MAPEL_EXPORT_FIELDS = [
  { id: 'no', label: 'No', default: true },
  { id: 'guru_nama', label: 'Nama Guru', default: true },
  { id: 'guru_nip', label: 'NIP Guru', default: true },
  { id: 'guru_email', label: 'Email Guru', default: false },
  { id: 'mapel_kode', label: 'Kode Mapel', default: true },
  { id: 'mapel_nama', label: 'Mata Pelajaran', default: true },
  { id: 'kelas', label: 'Kelas', default: true },
  { id: 'tingkat', label: 'Tingkat', default: false },
  { id: 'tahun_ajaran', label: 'Tahun Ajaran', default: true },
  { id: 'jam_per_minggu', label: 'Jam Per Minggu', default: true },
  { id: 'status', label: 'Status', default: true },
  { id: 'updated_at', label: 'Terakhir Diubah', default: false },
];

const extractFilename = (response, fallbackName) => {
  const disposition = response?.headers?.['content-disposition'] || response?.headers?.['Content-Disposition'];
  if (!disposition) {
    return fallbackName;
  }

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match && utf8Match[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const asciiMatch = disposition.match(/filename=\"?([^\";]+)\"?/i);
  if (asciiMatch && asciiMatch[1]) {
    return asciiMatch[1];
  }

  return fallbackName;
};

const downloadBlobResponse = (response, fallbackName) => {
  const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data]);
  const filename = extractFilename(response, fallbackName);
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

const toArray = (payload) => (Array.isArray(payload) ? payload : []);

const fieldClassName =
  'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';

const labelClassName = 'block text-sm font-semibold text-gray-700 mb-2';

const ModalSelectField = ({
  label,
  icon,
  value,
  onChange,
  options,
  placeholder,
  getValue,
  getLabel,
}) => (
  <div>
    <label className={labelClassName}>{label}</label>
    <div className="relative">
      <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">{icon}</div>
      <select className={fieldClassName} value={value} onChange={onChange}>
        <option value="">{placeholder}</option>
        {options.map((item) => (
          <option key={getValue(item)} value={getValue(item)}>
            {getLabel(item)}
          </option>
        ))}
      </select>
    </div>
  </div>
);

const modalAutocompleteSx = {
  '& .MuiOutlinedInput-root': {
    minHeight: 48,
    borderRadius: '0.75rem',
    backgroundColor: '#ffffff',
    '& fieldset': {
      borderColor: '#D1D5DB',
    },
    '&:hover fieldset': {
      borderColor: '#60A5FA',
    },
    '&.Mui-focused fieldset': {
      borderColor: '#3B82F6',
      borderWidth: '2px',
    },
  },
  '& .MuiInputBase-input': {
    paddingTop: '11px',
    paddingBottom: '11px',
  },
};

const ModalSearchSelectField = ({
  label,
  icon,
  value,
  onChange,
  options,
  placeholder,
  getValue,
  getLabel,
  disabled = false,
}) => {
  const safeOptions = toArray(options);
  const selectedOption = safeOptions.find((item) => String(getValue(item)) === String(value)) || null;

  return (
    <div>
      <label className={labelClassName}>{label}</label>
      <Autocomplete
        options={safeOptions}
        value={selectedOption}
        onChange={(_event, nextValue) => onChange(nextValue ? String(getValue(nextValue)) : '')}
        getOptionLabel={(option) => String(getLabel(option) || '')}
        isOptionEqualToValue={(option, selected) => String(getValue(option)) === String(getValue(selected))}
        disabled={disabled}
        noOptionsText="Data guru tidak ditemukan"
        fullWidth
        renderOption={(props, option) => (
          <li {...props} key={String(getValue(option))}>
            {getLabel(option)}
          </li>
        )}
        renderInput={(params) => (
          <TextField
            {...params}
            placeholder={placeholder}
            sx={modalAutocompleteSx}
            InputProps={{
              ...params.InputProps,
              startAdornment: (
                <>
                  <InputAdornment position="start" sx={{ color: '#9CA3AF', ml: 0.5 }}>
                    {icon}
                  </InputAdornment>
                  {params.InputProps.startAdornment}
                </>
              ),
            }}
          />
        )}
      />
    </div>
  );
};

const PenugasanFormModal = ({
  open,
  saving,
  mode,
  form,
  options,
  onClose,
  onChange,
  onSubmit,
}) => {
  if (!open) {
    return null;
  }

  const isCreate = mode === 'create';

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4 py-8">
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => !saving && onClose()} />

        <div className="relative inline-block w-full max-w-2xl overflow-hidden bg-white shadow-2xl rounded-2xl">
          <div className="px-6 py-5 bg-gradient-to-r from-blue-600 to-indigo-700">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-white/20">
                  <BookOpen className="w-5 h-5 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">
                    {isCreate ? 'Tambah Penugasan' : 'Edit Penugasan'}
                  </h3>
                  <p className="text-sm text-blue-100">
                    Sesuaikan data guru, mapel, kelas, dan tahun ajaran.
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => !saving && onClose()}
                className="p-2 transition-colors rounded-lg hover:bg-white/20"
                disabled={saving}
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          <div className="px-6 py-6 max-h-[70vh] overflow-y-auto">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <ModalSearchSelectField
                label="Guru"
                icon={<User className="w-5 h-5" />}
                value={form.guru_id}
                onChange={(nextValue) => onChange('guru_id', nextValue)}
                options={toArray(options.guru)}
                placeholder="Pilih Guru"
                getValue={(item) => String(item.id)}
                getLabel={(item) => `${item.nama_lengkap}${item.nip ? ` (${item.nip})` : ''}`}
              />

              <ModalSelectField
                label="Mata Pelajaran"
                icon={<BookOpen className="w-5 h-5" />}
                value={form.mata_pelajaran_id}
                onChange={(e) => onChange('mata_pelajaran_id', e.target.value)}
                options={toArray(options.mata_pelajaran)}
                placeholder="Pilih Mata Pelajaran"
                getValue={(item) => String(item.id)}
                getLabel={(item) => `${item.kode_mapel} - ${item.nama_mapel}`}
              />

              <ModalSelectField
                label="Kelas"
                icon={<GraduationCap className="w-5 h-5" />}
                value={form.kelas_id}
                onChange={(e) => onChange('kelas_id', e.target.value)}
                options={toArray(options.kelas)}
                placeholder="Pilih Kelas"
                getValue={(item) => String(item.id)}
                getLabel={(item) => item.nama_kelas}
              />

              <ModalSelectField
                label="Tahun Ajaran"
                icon={<Calendar className="w-5 h-5" />}
                value={form.tahun_ajaran_id}
                onChange={(e) => onChange('tahun_ajaran_id', e.target.value)}
                options={toArray(options.tahun_ajaran)}
                placeholder="Pilih Tahun Ajaran"
                getValue={(item) => String(item.id)}
                getLabel={(item) => item.nama}
              />

              <div>
                <label className={labelClassName}>Jam Per Minggu</label>
                <div className="relative">
                  <Clock3 className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                  <input
                    type="number"
                    min="0"
                    className={fieldClassName}
                    value={form.jam_per_minggu}
                    onChange={(e) => onChange('jam_per_minggu', e.target.value)}
                    placeholder="Contoh: 4"
                  />
                </div>
              </div>

              <ModalSelectField
                label="Status"
                icon={<Edit className="w-5 h-5" />}
                value={form.status}
                onChange={(e) => onChange('status', e.target.value)}
                options={[
                  { value: 'aktif', label: 'Aktif' },
                  { value: 'tidak_aktif', label: 'Tidak Aktif' },
                ]}
                placeholder="Pilih Status"
                getValue={(item) => item.value}
                getLabel={(item) => item.label}
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 border-t">
            <button
              type="button"
              onClick={() => onClose()}
              className="px-6 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50"
              disabled={saving}
            >
              Batal
            </button>
            <button
              type="button"
              onClick={onSubmit}
              className="px-6 py-2 text-sm font-medium text-white transition-all bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl hover:from-blue-700 hover:to-indigo-700 disabled:opacity-70"
              disabled={saving}
            >
              {saving ? 'Menyimpan...' : isCreate ? 'Simpan Penugasan' : 'Simpan Perubahan'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const PenugasanGuruMapel = () => {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('assign_guru_mapel');

  const [rows, setRows] = React.useState([]);
  const [options, setOptions] = React.useState({ guru: [], kelas: [], mata_pelajaran: [], tahun_ajaran: [] });
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');
  const [flash, setFlash] = React.useState(null);
  const [search, setSearch] = React.useState('');
  const [tahunAjaranId, setTahunAjaranId] = React.useState('');
  const [kelasId, setKelasId] = React.useState('');
  const [status, setStatus] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(15);
  const [formOpen, setFormOpen] = React.useState(false);
  const [confirmOpen, setConfirmOpen] = React.useState(false);
  const [formMode, setFormMode] = React.useState('create');
  const [selected, setSelected] = React.useState(null);
  const [form, setForm] = React.useState(emptyForm);
  const [saving, setSaving] = React.useState(false);
  const [showExportModal, setShowExportModal] = React.useState(false);
  const [exportProgress, setExportProgress] = React.useState(0);
  const [isExporting, setIsExporting] = React.useState(false);
  const [showImportModal, setShowImportModal] = React.useState(false);
  const [importProgress, setImportProgress] = React.useState(0);
  const [isImporting, setIsImporting] = React.useState(false);

  const loadData = React.useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [optRes, rowRes] = await Promise.all([
        guruMapelAPI.getOptions(),
        guruMapelAPI.getAll({ no_pagination: true }),
      ]);
      setOptions(optRes?.data?.data || { guru: [], kelas: [], mata_pelajaran: [], tahun_ajaran: [] });
      setRows(toArray(rowRes?.data?.data));
    } catch (e) {
      setError(e?.response?.data?.message || 'Gagal memuat data penugasan guru-mapel');
    } finally {
      setLoading(false);
    }
  }, []);

  React.useEffect(() => {
    loadData();
  }, [loadData]);

  React.useEffect(() => {
    if (!isImporting) {
      return undefined;
    }

    const currentUrl = window.location.href;
    const guardState = { import_guard: true, ts: getServerNowEpochMs() };
    window.history.pushState(guardState, document.title, currentUrl);

    const handlePopState = () => {
      window.history.pushState(guardState, document.title, currentUrl);
      setFlash({
        severity: 'warning',
        message: 'Import sedang berjalan. Tunggu hingga selesai sebelum pindah halaman.',
      });
    };

    const handleBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '';
      return '';
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [isImporting]);

  const filtered = React.useMemo(() => {
    const key = search.trim().toLowerCase();

    return rows.filter((r) => {
      const text = `${r?.guru?.nama_lengkap || ''} ${r?.mata_pelajaran?.nama_mapel || ''} ${r?.kelas?.nama_kelas || ''}`.toLowerCase();
      const bySearch = !key || text.includes(key);
      const byTa = !tahunAjaranId || String(r?.tahun_ajaran_id || '') === tahunAjaranId;
      const byKelas = !kelasId || String(r?.kelas_id || '') === kelasId;
      const byStatus = !status || String(r?.status || '') === status;

      return bySearch && byTa && byKelas && byStatus;
    });
  }, [rows, search, tahunAjaranId, kelasId, status]);

  const total = filtered.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  React.useEffect(() => {
    if (page > lastPage) {
      setPage(lastPage);
    }
  }, [page, lastPage]);

  const currentRows = React.useMemo(
    () => filtered.slice((page - 1) * perPage, page * perPage),
    [filtered, page, perPage]
  );

  const resetFilter = () => {
    setSearch('');
    setTahunAjaranId('');
    setKelasId('');
    setStatus('');
    setPage(1);
  };

  const openCreate = () => {
    setFormMode('create');
    setSelected(null);
    setForm(emptyForm);
    setFormOpen(true);
  };

  const openEdit = (row) => {
    setFormMode('edit');
    setSelected(row);
    setForm({
      guru_id: String(row.guru_id || ''),
      mata_pelajaran_id: String(row.mata_pelajaran_id || ''),
      kelas_id: String(row.kelas_id || ''),
      tahun_ajaran_id: String(row.tahun_ajaran_id || ''),
      jam_per_minggu: Number(row.jam_per_minggu || 0),
      status: row.status || 'aktif',
    });
    setFormOpen(true);
  };

  const openDelete = (row) => {
    setSelected(row);
    setConfirmOpen(true);
  };

  const submit = async () => {
    setSaving(true);

    try {
      const payload = {
        ...form,
        guru_id: Number(form.guru_id),
        mata_pelajaran_id: Number(form.mata_pelajaran_id),
        kelas_id: Number(form.kelas_id),
        tahun_ajaran_id: Number(form.tahun_ajaran_id),
        jam_per_minggu: Number(form.jam_per_minggu || 0),
      };

      if (formMode === 'create') {
        await guruMapelAPI.create(payload);
      } else {
        await guruMapelAPI.update(selected.id, payload);
      }

      setFormOpen(false);
      setFlash({
        severity: 'success',
        message: formMode === 'create' ? 'Penugasan berhasil ditambahkan' : 'Penugasan berhasil diperbarui',
      });
      await loadData();
    } catch (e) {
      setFlash({ severity: 'error', message: e?.response?.data?.message || 'Gagal menyimpan penugasan' });
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!selected?.id || saving) {
      return;
    }

    setSaving(true);
    try {
      await guruMapelAPI.delete(selected.id);
      setConfirmOpen(false);
      setFlash({ severity: 'success', message: 'Penugasan berhasil dihapus' });
      await loadData();
    } catch (e) {
      setFlash({ severity: 'error', message: e?.response?.data?.message || 'Gagal menghapus penugasan' });
    } finally {
      setSaving(false);
    }
  };

  const handleDownloadTemplate = async () => {
    const response = await guruMapelAPI.downloadTemplate();
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'Template_Import_Penugasan_Guru_Mapel.xlsx');
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const buildExportParams = React.useCallback(({ format = 'xlsx', fields = [] } = {}) => {
    const params = { format, fields };

    const keyword = search.trim();
    if (keyword) {
      params.search = keyword;
    }
    if (tahunAjaranId) {
      params.tahun_ajaran_id = tahunAjaranId;
    }
    if (kelasId) {
      params.kelas_id = kelasId;
    }
    if (status) {
      params.status = status;
    }

    return params;
  }, [search, tahunAjaranId, kelasId, status]);

  const handleExport = async ({ format = 'xlsx', fields = [] } = {}) => {
    setExportProgress(0);
    setIsExporting(true);

    const progressInterval = setInterval(() => {
      setExportProgress((prev) => Math.min(prev + 12, 90));
    }, 300);

    try {
      const response = await guruMapelAPI.exportData(buildExportParams({ format, fields }));

      clearInterval(progressInterval);
      setExportProgress(100);

      const dateStamp = getServerDateString();
      const extension = format === 'pdf' ? 'pdf' : 'xlsx';
      const fallbackName = `Penugasan_Guru_Mapel_${dateStamp}.${extension}`;
      downloadBlobResponse(response, fallbackName);

      const message = `Export penugasan guru-mapel (${format.toUpperCase()}) berhasil`;
      setFlash({ severity: 'success', message });
      return { success: true, message };
    } catch (error) {
      clearInterval(progressInterval);
      setExportProgress(0);

      const message = error?.response?.data?.message || error?.message || 'Export penugasan guru-mapel gagal';
      throw new Error(message);
    } finally {
      setIsExporting(false);
    }
  };

  const closeExportModal = () => {
    if (isExporting) {
      return;
    }

    setShowExportModal(false);
    setExportProgress(0);
  };

  const handleImport = async (formData) => {
    setImportProgress(0);
    setIsImporting(true);

    const progressInterval = setInterval(() => {
      setImportProgress((prev) => Math.min(prev + 10, 90));
    }, 500);

    try {
      const response = await guruMapelAPI.importData(formData);
      const result = response?.data || {};

      clearInterval(progressInterval);
      setImportProgress(100);

      if (!result.success) {
        const error = new Error(result.message || 'Import penugasan guru-mapel gagal');
        error.details = result.data || null;
        throw error;
      }

      setFlash({ severity: 'success', message: result.message || 'Import penugasan berhasil' });
      await loadData();

      return {
        success: true,
        message: result.message || 'Import penugasan berhasil',
        details: result.data || null,
      };
    } catch (error) {
      clearInterval(progressInterval);
      setImportProgress(0);

      const message =
        error?.response?.data?.message
        || error?.message
        || 'Import penugasan gagal';
      const details = error?.response?.data?.data || error?.details || null;

      const importError = new Error(message);
      importError.details = details;
      throw importError;
    } finally {
      setIsImporting(false);
    }
  };

  const handleImportSuccess = () => {
    setShowImportModal(false);
    setImportProgress(0);
  };

  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = total === 0 ? 0 : Math.min(page * perPage, total);

  return (
    <div className="p-6">
      <Box className="flex items-center gap-3 mb-6">
        <div className="p-2 bg-blue-100 rounded-lg">
          <BookOpen className="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <Typography variant="h4" className="font-bold text-gray-900">
            Penugasan Guru-Mapel
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Kelola penugasan guru untuk mapel, kelas, dan tahun ajaran
          </Typography>
        </div>
      </Box>

      {(error || flash) && (
        <Alert
          severity={error ? 'error' : flash?.severity || 'info'}
          className="mb-4"
          onClose={() => {
            setError('');
            setFlash(null);
          }}
        >
          {error || flash?.message}
        </Alert>
      )}

      <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <TextField
            size="small"
            fullWidth
            placeholder="Cari guru, mapel, kelas..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-4 h-4 text-gray-400" />
                </InputAdornment>
              ),
            }}
          />

          <FormControl size="small" sx={{ minWidth: 180 }}>
            <Select
              displayEmpty
              value={tahunAjaranId}
              onChange={(e) => {
                setTahunAjaranId(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Tahun Ajaran</MenuItem>
              {toArray(options.tahun_ajaran).map((item) => (
                <MenuItem key={item.id} value={String(item.id)}>
                  {item.nama}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 180 }}>
            <Select
              displayEmpty
              value={kelasId}
              onChange={(e) => {
                setKelasId(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Kelas</MenuItem>
              {toArray(options.kelas).map((item) => (
                <MenuItem key={item.id} value={String(item.id)}>
                  {item.nama_kelas}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 150 }}>
            <Select
              displayEmpty
              value={status}
              onChange={(e) => {
                setStatus(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Status</MenuItem>
              <MenuItem value="aktif">Aktif</MenuItem>
              <MenuItem value="tidak_aktif">Tidak Aktif</MenuItem>
            </Select>
          </FormControl>
        </Box>

        <Box className="flex flex-wrap items-center justify-between gap-3">
          <Button variant="outlined" size="small" startIcon={<RotateCcw className="w-4 h-4" />} onClick={resetFilter}>
            Reset Filter
          </Button>
          {canManage && (
            <Box className="flex items-center gap-2">
              <Button
                variant="outlined"
                size="small"
                startIcon={<Download className="w-4 h-4" />}
                onClick={() => setShowExportModal(true)}
                disabled={isExporting}
              >
                Export
              </Button>
              <Button
                variant="outlined"
                size="small"
                startIcon={<Upload className="w-4 h-4" />}
                onClick={() => setShowImportModal(true)}
              >
                Import
              </Button>
              <Button variant="contained" size="small" startIcon={<Plus className="w-4 h-4" />} onClick={openCreate}>
                Tambah Penugasan
              </Button>
            </Box>
          )}
        </Box>
      </Paper>

      <TableContainer component={Paper} className="border border-gray-100 shadow-sm">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell width={60}>No</TableCell>
              <TableCell>Guru</TableCell>
              <TableCell>Mapel</TableCell>
              <TableCell>Kelas</TableCell>
              <TableCell>Tahun Ajaran</TableCell>
              <TableCell>Jam/Minggu</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && currentRows.length === 0 && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Tidak ada data penugasan
                </TableCell>
              </TableRow>
            )}

            {!loading &&
              currentRows.map((row, idx) => (
                <TableRow key={row.id} hover>
                  <TableCell>{(page - 1) * perPage + idx + 1}</TableCell>
                  <TableCell>{row?.guru?.nama_lengkap || '-'}</TableCell>
                  <TableCell>
                    {row?.mata_pelajaran?.kode_mapel ? `${row.mata_pelajaran.kode_mapel} - ` : ''}
                    {row?.mata_pelajaran?.nama_mapel || '-'}
                  </TableCell>
                  <TableCell>{row?.kelas?.nama_kelas || '-'}</TableCell>
                  <TableCell>{row?.tahun_ajaran?.nama || '-'}</TableCell>
                  <TableCell>{row?.jam_per_minggu || 0}</TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={row?.status === 'aktif' ? 'Aktif' : 'Tidak Aktif'}
                      color={row?.status === 'aktif' ? 'success' : 'default'}
                      variant={row?.status === 'aktif' ? 'filled' : 'outlined'}
                    />
                  </TableCell>
                  <TableCell align="center">
                    {canManage ? (
                      <Box className="flex items-center justify-center gap-1">
                        <IconButton size="small" color="primary" onClick={() => openEdit(row)}>
                          <Edit className="w-4 h-4" />
                        </IconButton>
                        <IconButton size="small" color="error" onClick={() => openDelete(row)}>
                          <Trash2 className="w-4 h-4" />
                        </IconButton>
                      </Box>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Paper className="mt-4 px-4 py-3 border border-gray-100 shadow-sm">
        <Box className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <Typography variant="body2" color="text.secondary">
            Menampilkan {from} - {to} dari {total} data
          </Typography>

          <Box className="flex items-center gap-4">
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="text.secondary">
                Per halaman:
              </Typography>
              <Select
                size="small"
                value={perPage}
                onChange={(e) => {
                  setPerPage(Number(e.target.value));
                  setPage(1);
                }}
                sx={{ minWidth: 84 }}
              >
                {[10, 15, 25, 50].map((n) => (
                  <MenuItem key={n} value={n}>
                    {n}
                  </MenuItem>
                ))}
              </Select>
            </Box>

            <Pagination
              page={page}
              count={lastPage}
              onChange={(_, value) => setPage(value)}
              color="primary"
              shape="rounded"
              size="small"
            />
          </Box>
        </Box>
      </Paper>

      <PenugasanFormModal
        open={formOpen}
        saving={saving}
        mode={formMode}
        form={form}
        options={options}
        onClose={() => setFormOpen(false)}
        onChange={(key, value) => setForm((prev) => ({ ...prev, [key]: value }))}
        onSubmit={submit}
      />

      <ConfirmationModal
        open={confirmOpen}
        onClose={() => !saving && setConfirmOpen(false)}
        title="Hapus Penugasan"
        message={
          <>
            Hapus penugasan <strong>{selected?.guru?.nama_lengkap || '-'}</strong> untuk mapel{' '}
            <strong>{selected?.mata_pelajaran?.nama_mapel || '-'}</strong>?
          </>
        }
        onConfirm={remove}
        confirmText={saving ? 'Menghapus...' : 'Hapus'}
        type="delete"
      />

      <ExportModalAkademik
        isOpen={showExportModal}
        onClose={closeExportModal}
        onExport={handleExport}
        title="Export Penugasan Guru-Mapel"
        subtitle="Unduh laporan resmi penugasan guru-mapel (Excel/PDF)"
        entityLabel="Penugasan Guru-Mapel"
        fields={GURU_MAPEL_EXPORT_FIELDS}
        progress={exportProgress}
      />

      <ImportModalAkademik
        isOpen={showImportModal}
        onClose={() => !isImporting && setShowImportModal(false)}
        onSuccess={handleImportSuccess}
        onImport={handleImport}
        onDownloadTemplate={handleDownloadTemplate}
        title="Import Penugasan Guru-Mapel"
        subtitle="Upload file Excel untuk menambah atau memperbarui penugasan guru-mapel"
        templateLabel="Download Template Penugasan"
        progress={importProgress}
      />
    </div>
  );
};

export default PenugasanGuruMapel;

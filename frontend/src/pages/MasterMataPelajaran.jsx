import React from 'react';
import {
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
  Download,
  Edit,
  FileText,
  Hash,
  Plus,
  RotateCcw,
  School,
  Search,
  ToggleRight,
  Trash2,
  Upload,
  X,
} from 'lucide-react';
import { mataPelajaranAPI } from '../services/mataPelajaranService';
import { getServerDateString, getServerNowEpochMs } from '../services/serverClock';
import { tingkatAPI } from '../services/tingkatService';
import { useAuth } from '../hooks/useAuth';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';
import ExportModalAkademik from '../components/ExportModalAkademik';
import ImportModalAkademik from '../components/ImportModalAkademik';

const defaultFormData = {
  kode_mapel: '',
  nama_mapel: '',
  kelompok: '',
  tingkat_id: '',
  is_active: true,
  keterangan: '',
};

const MAPEL_EXPORT_FIELDS = [
  { id: 'no', label: 'No', default: true },
  { id: 'kode_mapel', label: 'Kode Mapel', default: true },
  { id: 'nama_mapel', label: 'Nama Mata Pelajaran', default: true },
  { id: 'kelompok', label: 'Kelompok', default: true },
  { id: 'tingkat', label: 'Tingkat', default: true },
  { id: 'status', label: 'Status', default: true },
  { id: 'guru_assignments_count', label: 'Jumlah Penugasan Guru', default: false },
  { id: 'jadwal_mengajar_count', label: 'Jumlah Jadwal', default: false },
  { id: 'keterangan', label: 'Keterangan', default: false },
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

const fieldClassName =
  'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';

const labelClassName = 'block text-sm font-semibold text-gray-700 mb-2';

const normalizeRows = (payload) => {
  if (!Array.isArray(payload)) {
    return [];
  }

  return payload.map((row) => ({
    ...row,
    tingkat_nama: row?.tingkat?.nama ?? '-',
  }));
};

const ModalInputField = ({
  label,
  icon,
  value,
  onChange,
  placeholder,
  required = false,
}) => (
  <div>
    <label className={labelClassName}>{label}{required ? ' *' : ''}</label>
    <div className="relative">
      <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">{icon}</div>
      <input
        className={fieldClassName}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
      />
    </div>
  </div>
);

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

const MataPelajaranFormModal = ({
  open,
  mode,
  saving,
  formData,
  tingkatRows,
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
                    {isCreate ? 'Tambah Mata Pelajaran' : 'Edit Mata Pelajaran'}
                  </h3>
                  <p className="text-sm text-blue-100">
                    Kelola data mapel untuk penugasan guru dan jadwal pelajaran.
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
              <ModalInputField
                label="Kode Mapel"
                icon={<Hash className="w-5 h-5" />}
                value={formData.kode_mapel}
                onChange={(event) => onChange('kode_mapel', event.target.value.toUpperCase())}
                placeholder="Contoh: MTK10"
                required
              />

              <ModalInputField
                label="Nama Mata Pelajaran"
                icon={<BookOpen className="w-5 h-5" />}
                value={formData.nama_mapel}
                onChange={(event) => onChange('nama_mapel', event.target.value)}
                placeholder="Contoh: Matematika"
                required
              />

              <ModalInputField
                label="Kelompok"
                icon={<FileText className="w-5 h-5" />}
                value={formData.kelompok}
                onChange={(event) => onChange('kelompok', event.target.value)}
                placeholder="Contoh: Wajib / Peminatan"
              />

              <ModalSelectField
                label="Tingkat"
                icon={<School className="w-5 h-5" />}
                value={formData.tingkat_id}
                onChange={(event) => onChange('tingkat_id', event.target.value)}
                options={tingkatRows}
                placeholder="Semua Tingkat"
                getValue={(item) => String(item.id)}
                getLabel={(item) => item.nama}
              />

              <ModalSelectField
                label="Status"
                icon={<ToggleRight className="w-5 h-5" />}
                value={formData.is_active ? 'active' : 'inactive'}
                onChange={(event) => onChange('is_active', event.target.value === 'active')}
                options={[
                  { value: 'active', label: 'Aktif' },
                  { value: 'inactive', label: 'Nonaktif' },
                ]}
                placeholder="Pilih Status"
                getValue={(item) => item.value}
                getLabel={(item) => item.label}
              />

              <div className="md:col-span-2">
                <label className={labelClassName}>Keterangan</label>
                <div className="relative">
                  <FileText className="absolute left-3 top-4 w-5 h-5 text-gray-400" />
                  <textarea
                    className={`${fieldClassName} min-h-[110px] resize-y`}
                    value={formData.keterangan}
                    onChange={(event) => onChange('keterangan', event.target.value)}
                    placeholder="Catatan tambahan (opsional)"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 border-t">
            <button
              type="button"
              onClick={onClose}
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
              {saving ? 'Menyimpan...' : isCreate ? 'Simpan Mapel' : 'Simpan Perubahan'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const MasterMataPelajaran = () => {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('manage_mapel');

  const [rows, setRows] = React.useState([]);
  const [tingkatRows, setTingkatRows] = React.useState([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');
  const [flash, setFlash] = React.useState(null);

  const [searchTerm, setSearchTerm] = React.useState('');
  const [kelompokFilter, setKelompokFilter] = React.useState('');
  const [tingkatFilter, setTingkatFilter] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(15);

  const [formOpen, setFormOpen] = React.useState(false);
  const [formMode, setFormMode] = React.useState('create');
  const [selectedRow, setSelectedRow] = React.useState(null);
  const [formData, setFormData] = React.useState(defaultFormData);
  const [saving, setSaving] = React.useState(false);

  const [confirmDeleteOpen, setConfirmDeleteOpen] = React.useState(false);
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
      const [mapelResponse, tingkatResponse] = await Promise.all([
        mataPelajaranAPI.getAll({ no_pagination: true }),
        tingkatAPI.getAll({ no_pagination: true }),
      ]);

      const mapelPayload = mapelResponse?.data?.data ?? [];
      const tingkatPayload = tingkatResponse?.data?.data ?? tingkatResponse?.data ?? [];

      const normalizedMapel = normalizeRows(mapelPayload).sort((a, b) => {
        const codeA = String(a.kode_mapel || '');
        const codeB = String(b.kode_mapel || '');
        return codeA.localeCompare(codeB, 'id');
      });

      setRows(normalizedMapel);
      setTingkatRows(Array.isArray(tingkatPayload) ? tingkatPayload : []);
    } catch (loadError) {
      setError(loadError?.response?.data?.message || 'Gagal memuat data mata pelajaran');
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

  const kelompokOptions = React.useMemo(() => {
    return Array.from(
      new Set(
        rows
          .map((item) => item.kelompok)
          .filter((item) => typeof item === 'string' && item.trim() !== '')
      )
    ).sort((a, b) => a.localeCompare(b, 'id'));
  }, [rows]);

  const filteredRows = React.useMemo(() => {
    const keyword = searchTerm.trim().toLowerCase();
    return rows.filter((item) => {
      const haystack = [
        item.kode_mapel,
        item.nama_mapel,
        item.kelompok,
        item.tingkat_nama,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      const matchKeyword = !keyword || haystack.includes(keyword);
      const matchKelompok = !kelompokFilter || item.kelompok === kelompokFilter;
      const matchTingkat = !tingkatFilter || String(item.tingkat_id || '') === tingkatFilter;
      const matchStatus =
        statusFilter === ''
        || (statusFilter === 'active' && item.is_active)
        || (statusFilter === 'inactive' && !item.is_active);

      return matchKeyword && matchKelompok && matchTingkat && matchStatus;
    });
  }, [rows, searchTerm, kelompokFilter, tingkatFilter, statusFilter]);

  const totalRows = filteredRows.length;
  const lastPage = Math.max(1, Math.ceil(totalRows / perPage));

  React.useEffect(() => {
    if (page > lastPage) {
      setPage(lastPage);
    }
  }, [page, lastPage]);

  const paginatedRows = React.useMemo(() => {
    const start = (page - 1) * perPage;
    return filteredRows.slice(start, start + perPage);
  }, [filteredRows, page, perPage]);

  const from = totalRows === 0 ? 0 : (page - 1) * perPage + 1;
  const to = totalRows === 0 ? 0 : Math.min(page * perPage, totalRows);

  const handleResetFilter = () => {
    setSearchTerm('');
    setKelompokFilter('');
    setTingkatFilter('');
    setStatusFilter('');
    setPage(1);
  };

  const openCreate = () => {
    setFormMode('create');
    setSelectedRow(null);
    setFormData(defaultFormData);
    setFormOpen(true);
  };

  const openEdit = (row) => {
    setFormMode('edit');
    setSelectedRow(row);
    setFormData({
      kode_mapel: row.kode_mapel || '',
      nama_mapel: row.nama_mapel || '',
      kelompok: row.kelompok || '',
      tingkat_id: row.tingkat_id ? String(row.tingkat_id) : '',
      is_active: !!row.is_active,
      keterangan: row.keterangan || '',
    });
    setFormOpen(true);
  };

  const openDelete = (row) => {
    setSelectedRow(row);
    setConfirmDeleteOpen(true);
  };

  const closeDialogs = () => {
    setFormOpen(false);
    setConfirmDeleteOpen(false);
    setSelectedRow(null);
  };

  const updateForm = (key, value) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const handleSubmit = async () => {
    if (!formData.kode_mapel.trim() || !formData.nama_mapel.trim()) {
      setFlash({ severity: 'error', message: 'Kode mapel dan nama mapel wajib diisi' });
      return;
    }

    setSaving(true);
    setFlash(null);
    try {
      const payload = {
        kode_mapel: formData.kode_mapel.trim().toUpperCase(),
        nama_mapel: formData.nama_mapel.trim(),
        kelompok: formData.kelompok.trim() || null,
        tingkat_id: formData.tingkat_id ? Number(formData.tingkat_id) : null,
        is_active: !!formData.is_active,
        keterangan: formData.keterangan.trim() || null,
      };

      if (formMode === 'create') {
        await mataPelajaranAPI.create(payload);
        setFlash({ severity: 'success', message: 'Mata pelajaran berhasil ditambahkan' });
      } else if (selectedRow?.id) {
        await mataPelajaranAPI.update(selectedRow.id, payload);
        setFlash({ severity: 'success', message: 'Mata pelajaran berhasil diperbarui' });
      }

      closeDialogs();
      await loadData();
    } catch (submitError) {
      const message =
        submitError?.response?.data?.message
        || submitError?.response?.data?.errors?.kode_mapel?.[0]
        || 'Gagal menyimpan mata pelajaran';
      setFlash({ severity: 'error', message });
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedRow?.id || saving) {
      return;
    }

    setSaving(true);
    setFlash(null);
    try {
      await mataPelajaranAPI.delete(selectedRow.id);
      setFlash({ severity: 'success', message: 'Mata pelajaran berhasil dihapus' });
      closeDialogs();
      await loadData();
    } catch (deleteError) {
      const message = deleteError?.response?.data?.message || 'Gagal menghapus mata pelajaran';
      setFlash({ severity: 'error', message });
    } finally {
      setSaving(false);
    }
  };

  const handleDownloadTemplate = async () => {
    const response = await mataPelajaranAPI.downloadTemplate();
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'Template_Import_Mata_Pelajaran.xlsx');
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const buildExportParams = React.useCallback(({ format = 'xlsx', fields = [] } = {}) => {
    const params = {
      format,
      fields,
    };

    const keyword = searchTerm.trim();
    if (keyword) {
      params.search = keyword;
    }
    if (kelompokFilter) {
      params.kelompok = kelompokFilter;
    }
    if (tingkatFilter) {
      params.tingkat_id = tingkatFilter;
    }
    if (statusFilter === 'active') {
      params.is_active = 'true';
    } else if (statusFilter === 'inactive') {
      params.is_active = 'false';
    }

    return params;
  }, [searchTerm, kelompokFilter, tingkatFilter, statusFilter]);

  const handleExport = async ({ format = 'xlsx', fields = [] } = {}) => {
    setExportProgress(0);
    setIsExporting(true);

    const progressInterval = setInterval(() => {
      setExportProgress((prev) => Math.min(prev + 12, 90));
    }, 300);

    try {
      const response = await mataPelajaranAPI.exportData(buildExportParams({ format, fields }));

      clearInterval(progressInterval);
      setExportProgress(100);

      const dateStamp = getServerDateString();
      const extension = format === 'pdf' ? 'pdf' : 'xlsx';
      const fallbackName = `Master_Mata_Pelajaran_${dateStamp}.${extension}`;
      downloadBlobResponse(response, fallbackName);

      const message = `Export master mata pelajaran (${format.toUpperCase()}) berhasil`;
      setFlash({ severity: 'success', message });
      return { success: true, message };
    } catch (error) {
      clearInterval(progressInterval);
      setExportProgress(0);

      const message = error?.response?.data?.message || error?.message || 'Export master mata pelajaran gagal';
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
      const response = await mataPelajaranAPI.importData(formData);
      const result = response?.data || {};

      clearInterval(progressInterval);
      setImportProgress(100);

      if (!result.success) {
        const error = new Error(result.message || 'Import mata pelajaran gagal');
        error.details = result.data || null;
        throw error;
      }

      setFlash({ severity: 'success', message: result.message || 'Import mata pelajaran berhasil' });
      await loadData();

      return {
        success: true,
        message: result.message || 'Import mata pelajaran berhasil',
        details: result.data || null,
      };
    } catch (error) {
      clearInterval(progressInterval);
      setImportProgress(0);

      const message =
        error?.response?.data?.message
        || error?.message
        || 'Import mata pelajaran gagal';
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

  return (
    <div className="p-6">
      <Box className="flex items-center gap-3 mb-6">
        <div className="p-2 bg-blue-100 rounded-lg">
          <BookOpen className="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <Typography variant="h4" className="font-bold text-gray-900">
            Master Mata Pelajaran
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Kelola data mapel sebagai sumber utama penugasan guru dan jadwal pelajaran
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
            placeholder="Cari kode atau nama mapel..."
            value={searchTerm}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setPage(1);
            }}
            size="small"
            fullWidth
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
              value={kelompokFilter}
              onChange={(event) => {
                setKelompokFilter(event.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Kelompok</MenuItem>
              {kelompokOptions.map((item) => (
                <MenuItem key={item} value={item}>
                  {item}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 180 }}>
            <Select
              displayEmpty
              value={tingkatFilter}
              onChange={(event) => {
                setTingkatFilter(event.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Tingkat</MenuItem>
              {tingkatRows.map((tingkat) => (
                <MenuItem key={tingkat.id} value={String(tingkat.id)}>
                  {tingkat.nama}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <Select
              displayEmpty
              value={statusFilter}
              onChange={(event) => {
                setStatusFilter(event.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Status</MenuItem>
              <MenuItem value="active">Aktif</MenuItem>
              <MenuItem value="inactive">Nonaktif</MenuItem>
            </Select>
          </FormControl>
        </Box>

        <Box className="flex flex-wrap items-center justify-between gap-3">
          <Button
            variant="outlined"
            size="small"
            startIcon={<RotateCcw className="w-4 h-4" />}
            onClick={handleResetFilter}
          >
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

              <Button
                variant="contained"
                size="small"
                startIcon={<Plus className="w-4 h-4" />}
                onClick={openCreate}
              >
                Tambah Mapel
              </Button>
            </Box>
          )}
        </Box>
      </Paper>

      <TableContainer component={Paper} className="border border-gray-100 shadow-sm">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell width={70}>No</TableCell>
              <TableCell>Kode</TableCell>
              <TableCell>Nama Mata Pelajaran</TableCell>
              <TableCell>Kelompok</TableCell>
              <TableCell>Tingkat</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Dipakai</TableCell>
              <TableCell align="center" width={120}>
                Aksi
              </TableCell>
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

            {!loading && paginatedRows.length === 0 && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Tidak ada data mata pelajaran
                </TableCell>
              </TableRow>
            )}

            {!loading &&
              paginatedRows.map((row, index) => (
                <TableRow key={row.id} hover>
                  <TableCell>{(page - 1) * perPage + index + 1}</TableCell>
                  <TableCell>{row.kode_mapel}</TableCell>
                  <TableCell>{row.nama_mapel}</TableCell>
                  <TableCell>{row.kelompok || '-'}</TableCell>
                  <TableCell>{row.tingkat_nama}</TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={row.is_active ? 'Aktif' : 'Nonaktif'}
                      color={row.is_active ? 'success' : 'default'}
                      variant={row.is_active ? 'filled' : 'outlined'}
                    />
                  </TableCell>
                  <TableCell>
                    {row.guru_assignments_count || 0} penugasan / {row.jadwal_mengajar_count || 0} jadwal
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
            Menampilkan {from} - {to} dari {totalRows} data
          </Typography>
          <Box className="flex items-center gap-4">
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="text.secondary">
                Per halaman:
              </Typography>
              <Select
                size="small"
                value={perPage}
                onChange={(event) => {
                  setPerPage(Number(event.target.value));
                  setPage(1);
                }}
                sx={{ minWidth: 84 }}
              >
                {[10, 15, 25, 50].map((size) => (
                  <MenuItem key={size} value={size}>
                    {size}
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

      <MataPelajaranFormModal
        open={formOpen}
        mode={formMode}
        saving={saving}
        formData={formData}
        tingkatRows={tingkatRows}
        onClose={closeDialogs}
        onChange={updateForm}
        onSubmit={handleSubmit}
      />

      <ConfirmationModal
        open={confirmDeleteOpen}
        onClose={() => !saving && closeDialogs()}
        title="Hapus Mata Pelajaran"
        message={
          <>
            Hapus mapel <strong>{selectedRow?.nama_mapel}</strong> ({selectedRow?.kode_mapel})?
          </>
        }
        onConfirm={handleDelete}
        confirmText={saving ? 'Menghapus...' : 'Hapus'}
        type="delete"
      />

      <ExportModalAkademik
        isOpen={showExportModal}
        onClose={closeExportModal}
        onExport={handleExport}
        title="Export Master Mata Pelajaran"
        subtitle="Unduh laporan resmi master mata pelajaran (Excel/PDF)"
        entityLabel="Master Mata Pelajaran"
        fields={MAPEL_EXPORT_FIELDS}
        progress={exportProgress}
      />

      <ImportModalAkademik
        isOpen={showImportModal}
        onClose={() => !isImporting && setShowImportModal(false)}
        onSuccess={handleImportSuccess}
        onImport={handleImport}
        onDownloadTemplate={handleDownloadTemplate}
        title="Import Master Mata Pelajaran"
        subtitle="Upload file Excel untuk menambah atau memperbarui data mata pelajaran"
        templateLabel="Download Template Mata Pelajaran"
        progress={importProgress}
      />
    </div>
  );
};

export default MasterMataPelajaran;

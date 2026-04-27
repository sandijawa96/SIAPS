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
  AlertTriangle,
  Calendar,
  Edit,
  Plus,
  RotateCcw,
  Search,
  Trash2,
} from 'lucide-react';
import { TahunAjaranFormModal } from '../components/modals';
import { useTahunAjaranManagement } from '../hooks/useTahunAjaranManagement';
import { useTahunAjaranModals } from '../hooks/useTahunAjaranModals';
import { formatServerDate, toServerDateInput } from '../services/serverClock';

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'draft', label: 'Draft' },
  { value: 'preparation', label: 'Persiapan' },
  { value: 'active', label: 'Aktif' },
  { value: 'completed', label: 'Selesai' },
  { value: 'archived', label: 'Arsip' },
];

const STATUS_COLORS = {
  draft: { color: 'default', label: 'Draft' },
  preparation: { color: 'warning', label: 'Persiapan' },
  active: { color: 'success', label: 'Aktif' },
  completed: { color: 'info', label: 'Selesai' },
  archived: { color: 'default', label: 'Arsip' },
};

const formatDate = (value) => {
  if (!value) {
    return '-';
  }

  return formatServerDate(value, 'id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }) || value;
};

const formatSemesterPeriod = (period) => {
  if (!period?.tanggal_mulai || !period?.tanggal_selesai) {
    return '-';
  }

  return `${formatDate(period.tanggal_mulai)} - ${formatDate(period.tanggal_selesai)}`;
};

const ManajemenTahunAjaran = () => {
  const [searchTerm, setSearchTerm] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(15);

  const {
    tahunAjaranList,
    loading,
    error,
    createTahunAjaran,
    updateTahunAjaran,
    deleteTahunAjaran,
    transitionStatus,
  } = useTahunAjaranManagement();

  const {
    showModal,
    selectedTahunAjaran,
    showConfirmModal,
    confirmData,
    openAddModal,
    openEditModal,
    closeModal,
    openConfirmModal,
    closeConfirmModal,
    executeConfirmAction,
  } = useTahunAjaranModals();

  const filteredTahunAjaran = React.useMemo(() => {
    if (!Array.isArray(tahunAjaranList)) {
      return [];
    }

    const keyword = searchTerm.trim().toLowerCase();

    return tahunAjaranList
      .filter((item) => {
        const haystack = [
          item.nama,
          item.keterangan,
          item.status_display,
          item.status,
          item.semester,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase();

        const matchSearch = keyword === '' || haystack.includes(keyword);
        const matchStatus = statusFilter === '' || String(item.status || '').toLowerCase() === statusFilter;

        return matchSearch && matchStatus;
      })
      .sort((a, b) => {
        const aEpoch = Date.parse(a.created_at || '');
        const bEpoch = Date.parse(b.created_at || '');

        if (Number.isFinite(aEpoch) && Number.isFinite(bEpoch)) {
          return bEpoch - aEpoch;
        }

        const aDate = toServerDateInput(a.created_at || '');
        const bDate = toServerDateInput(b.created_at || '');
        return String(bDate || '').localeCompare(String(aDate || ''));
      });
  }, [tahunAjaranList, searchTerm, statusFilter]);

  const totalRows = filteredTahunAjaran.length;
  const lastPage = Math.max(1, Math.ceil(totalRows / perPage));

  React.useEffect(() => {
    if (page > lastPage) {
      setPage(lastPage);
    }
  }, [page, lastPage]);

  const paginatedRows = React.useMemo(() => {
    const startIndex = (page - 1) * perPage;
    return filteredTahunAjaran.slice(startIndex, startIndex + perPage);
  }, [filteredTahunAjaran, page, perPage]);

  const from = totalRows === 0 ? 0 : (page - 1) * perPage + 1;
  const to = totalRows === 0 ? 0 : Math.min(page * perPage, totalRows);

  const totalDraft = React.useMemo(
    () => filteredTahunAjaran.filter((item) => String(item.status).toLowerCase() === 'draft').length,
    [filteredTahunAjaran],
  );
  const totalActive = React.useMemo(
    () => filteredTahunAjaran.filter((item) => String(item.status).toLowerCase() === 'active').length,
    [filteredTahunAjaran],
  );
  const totalCompleted = React.useMemo(
    () => filteredTahunAjaran.filter((item) => String(item.status).toLowerCase() === 'completed').length,
    [filteredTahunAjaran],
  );

  const handleSaveTahunAjaran = async (data) => {
    try {
      if (data.id) {
        await updateTahunAjaran(data.id, data);
      } else {
        await createTahunAjaran(data);
      }
      closeModal();
    } catch (saveError) {
      // Error toast handled in hook.
      console.error('Gagal menyimpan tahun ajaran:', saveError);
    }
  };

  const handleDeleteConfirm = (id, nama) => {
    openConfirmModal(
      { id, nama, type: 'delete' },
      async () => {
        await deleteTahunAjaran(id);
      },
    );
  };

  const handleSetActiveConfirm = (id, nama) => {
    openConfirmModal(
      { id, nama, type: 'activate' },
      async () => {
        await transitionStatus(id, 'active');
      },
    );
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setStatusFilter('');
    setPage(1);
  };

  return (
    <div className="p-6">
      <Box className="flex items-center gap-3 mb-6">
        <div className="p-2 bg-blue-100 rounded-lg">
          <Calendar className="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <Typography variant="h4" className="font-bold text-gray-900">
            Manajemen Tahun Ajaran
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Kelola siklus tahun ajaran, aktivasi, dan status akademik
          </Typography>
        </div>
      </Box>

      <Box className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Paper className="p-4 border border-gray-100 shadow-sm">
          <Typography variant="body2" color="text.secondary">
            Total Tahun Ajaran
          </Typography>
          <Typography variant="h5" className="font-bold">
            {filteredTahunAjaran.length}
          </Typography>
        </Paper>
        <Paper className="p-4 border border-gray-100 shadow-sm">
          <Typography variant="body2" color="text.secondary">
            Aktif
          </Typography>
          <Typography variant="h5" className="font-bold text-green-600">
            {totalActive}
          </Typography>
        </Paper>
        <Paper className="p-4 border border-gray-100 shadow-sm">
          <Typography variant="body2" color="text.secondary">
            Draft / Selesai
          </Typography>
          <Typography variant="h5" className="font-bold">
            {totalDraft} / {totalCompleted}
          </Typography>
        </Paper>
      </Box>

      <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <TextField
            placeholder="Cari tahun ajaran..."
            value={searchTerm}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setPage(1);
            }}
            className="flex-1 lg:max-w-md"
            size="small"
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-4 h-4 text-gray-400" />
                </InputAdornment>
              ),
            }}
          />

          <Box className="flex flex-wrap gap-3">
            <FormControl size="small" className="min-w-[160px]">
              <Select
                value={statusFilter}
                onChange={(event) => {
                  setStatusFilter(event.target.value);
                  setPage(1);
                }}
                displayEmpty
              >
                {STATUS_OPTIONS.map((option) => (
                  <MenuItem key={option.value || 'all-status'} value={option.value}>
                    {option.label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>

          </Box>
        </Box>

        <Box className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <Box className="flex items-center gap-2">
            {(searchTerm || statusFilter) && (
              <Chip label="Filter aktif" color="primary" size="small" variant="outlined" />
            )}
          </Box>

          <Box className="flex flex-wrap gap-2">
            <Button
              variant="outlined"
              color="inherit"
              size="small"
              startIcon={<RotateCcw className="w-4 h-4" />}
              onClick={handleResetFilters}
            >
              Reset Filter
            </Button>

            <Button
              variant="contained"
              size="small"
              startIcon={<Plus className="w-4 h-4" />}
              onClick={openAddModal}
              className="bg-blue-600 hover:bg-blue-700 shadow-sm"
            >
              Tambah Tahun Ajaran
            </Button>
          </Box>
        </Box>
      </Paper>

      {error && (
        <Alert severity="error" className="mb-4">
          {error}
        </Alert>
      )}

      <TableContainer component={Paper} className="shadow-sm border border-gray-100">
        <Table>
          <TableHead>
            <TableRow className="bg-gray-50">
              <TableCell>Tahun Ajaran</TableCell>
              <TableCell>Periode</TableCell>
              <TableCell>Semester</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Progress</TableCell>
              <TableCell>Jumlah Kelas</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading
              ? [...Array(5)].map((_, index) => (
                  <TableRow key={`loading-row-${index}`}>
                    <TableCell colSpan={7}>
                      <div className="h-8 w-full animate-pulse rounded bg-gray-100" />
                    </TableCell>
                  </TableRow>
                ))
              : null}

            {!loading && paginatedRows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center" className="py-8">
                  <Typography variant="body2" color="textSecondary">
                    Tidak ada data tahun ajaran
                  </Typography>
                </TableCell>
              </TableRow>
            ) : null}

            {!loading &&
              paginatedRows.map((item) => {
                const statusKey = String(item.status || '').toLowerCase();
                const statusConfig = STATUS_COLORS[statusKey] || { color: 'default', label: item.status_display || '-' };

                return (
                  <TableRow key={item.id} hover className="hover:bg-gray-50 transition-colors">
                    <TableCell>
                      <Typography variant="body2" className="font-semibold text-gray-900">
                        {item.nama}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        Diperbarui: {formatDate(item.updated_at)}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {formatDate(item.tanggal_mulai)} - {formatDate(item.tanggal_selesai)}
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" className="font-semibold">
                        {item.semester_display || 'Ganjil & Genap'}
                      </Typography>
                      <Typography variant="caption" color="text.secondary" display="block">
                        Ganjil: {formatSemesterPeriod(item.semester_periods?.ganjil)}
                      </Typography>
                      <Typography variant="caption" color="text.secondary" display="block">
                        Genap: {formatSemesterPeriod(item.semester_periods?.genap)}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={statusConfig.label}
                        color={statusConfig.color}
                        size="small"
                        variant="outlined"
                      />
                    </TableCell>
                    <TableCell>{Number(item.preparation_progress || 0)}%</TableCell>
                    <TableCell>{item.jumlah_kelas || 0}</TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center gap-1">
                        {statusKey !== 'active' && (
                          <Button
                            size="small"
                            variant="outlined"
                            color="success"
                            onClick={() => handleSetActiveConfirm(item.id, item.nama)}
                          >
                            Aktifkan
                          </Button>
                        )}

                        <IconButton
                          size="small"
                          color="primary"
                          onClick={() => openEditModal(item)}
                          title="Edit tahun ajaran"
                        >
                          <Edit className="w-4 h-4" />
                        </IconButton>

                        {statusKey !== 'active' && (
                          <IconButton
                            size="small"
                            color="error"
                            onClick={() => handleDeleteConfirm(item.id, item.nama)}
                            title="Hapus tahun ajaran"
                          >
                            <Trash2 className="w-4 h-4" />
                          </IconButton>
                        )}
                      </Box>
                    </TableCell>
                  </TableRow>
                );
              })}
          </TableBody>
        </Table>
      </TableContainer>

      {totalRows > 0 && (
        <Paper className="p-4 mt-4 shadow-sm border border-gray-100">
          <Box className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <Typography variant="body2" color="textSecondary">
              Menampilkan {from} - {to} dari {totalRows} data
            </Typography>

            <Box className="flex items-center gap-4">
              <Box className="flex items-center gap-2">
                <Typography variant="body2" color="textSecondary">
                  Per halaman:
                </Typography>
                <FormControl size="small">
                  <Select
                    value={perPage}
                    onChange={(event) => {
                      setPerPage(Number(event.target.value));
                      setPage(1);
                    }}
                    className="min-w-[80px]"
                  >
                    <MenuItem value={10}>10</MenuItem>
                    <MenuItem value={15}>15</MenuItem>
                    <MenuItem value={25}>25</MenuItem>
                    <MenuItem value={50}>50</MenuItem>
                  </Select>
                </FormControl>
              </Box>

              <Pagination
                count={lastPage}
                page={page}
                onChange={(_, nextPage) => setPage(nextPage)}
                color="primary"
                shape="rounded"
                showFirstButton
                showLastButton
                size="small"
              />
            </Box>
          </Box>
        </Paper>
      )}

      <TahunAjaranFormModal
        isOpen={showModal}
        onClose={closeModal}
        onSubmit={handleSaveTahunAjaran}
        initialData={selectedTahunAjaran}
      />

      {showConfirmModal && confirmData && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 w-full max-w-md">
            <div className="flex items-center mb-4">
              <div className="p-3 rounded-full bg-yellow-100 mr-4">
                <AlertTriangle className="w-6 h-6 text-yellow-600" />
              </div>
              <h3 className="text-lg font-medium text-gray-900">
                {confirmData.type === 'delete' ? 'Konfirmasi Hapus' : 'Konfirmasi Aktivasi'}
              </h3>
            </div>

            <div className="mb-6">
              <p className="text-sm text-gray-600">
                {confirmData.type === 'delete'
                  ? `Apakah Anda yakin ingin menghapus tahun ajaran "${confirmData.nama}"? Tindakan ini tidak dapat dibatalkan.`
                  : `Apakah Anda yakin ingin mengaktifkan tahun ajaran "${confirmData.nama}"? Tahun ajaran aktif sebelumnya akan dinonaktifkan.`}
              </p>
            </div>

            <div className="flex justify-end space-x-3">
              <button
                onClick={closeConfirmModal}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                Batal
              </button>
              <button
                onClick={executeConfirmAction}
                className={`px-4 py-2 text-sm font-medium text-white rounded-lg ${
                  confirmData.type === 'delete'
                    ? 'bg-red-600 hover:bg-red-700'
                    : 'bg-green-600 hover:bg-green-700'
                }`}
              >
                {confirmData.type === 'delete' ? 'Hapus' : 'Aktifkan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ManajemenTahunAjaran;

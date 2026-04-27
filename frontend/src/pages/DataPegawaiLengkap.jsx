import React, { useEffect, useState } from 'react';
import {
  Avatar,
  Box,
  Button,
  Chip,
  CircularProgress,
  Container,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  Grid,
  IconButton,
  InputAdornment,
  InputLabel,
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
  Briefcase,
  Clock,
  Download,
  Edit,
  Eye,
  GraduationCap,
  Home,
  Phone,
  Search,
  User,
} from 'lucide-react';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';
import { useSnackbar } from 'notistack';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth.jsx';
import { getServerDateString } from '../services/serverClock';
import pegawaiExtendedService from '../services/pegawaiExtendedService.jsx';
import roleService from '../services/roleService.jsx';

const DataPegawaiLengkap = () => {
  const navigate = useNavigate();
  const [pegawai, setPegawai] = useState([]);
  const [loading, setLoading] = useState(false);
  const [openDetailDialog, setOpenDetailDialog] = useState(false);
  const [selectedPegawai, setSelectedPegawai] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);
  const [searchTerm, setSearchTerm] = useState('');
  const [totalCount, setTotalCount] = useState(0);
  const [roleOptions, setRoleOptions] = useState([]);
  const [filters, setFilters] = useState({
    role: '',
    status_kepegawaian: '',
    is_active: '',
  });
  const { enqueueSnackbar } = useSnackbar();
  const { hasPermission } = useAuth();
  const canReadPegawai = hasPermission('view_pegawai') || hasPermission('manage_pegawai');
  const canManagePegawai = hasPermission('manage_pegawai');

  const statusKepegawaianOptions = ['ASN', 'Honorer'];

  useEffect(() => {
    fetchPegawai();
  }, [page, rowsPerPage, searchTerm, filters, canReadPegawai]);

  useEffect(() => {
    if (!canReadPegawai) {
      setRoleOptions([]);
      return;
    }

    const fetchRoleOptions = async () => {
      try {
        const response = await roleService.getPrimaryRoles();
        if (response?.success) {
          const roles = (response.data || [])
            .filter((role) => role?.is_active && !['Siswa', 'Super_Admin'].includes(role.name))
            .map((role) => role.name);
          setRoleOptions(roles);
          return;
        }
      } catch (error) {
        console.error('Error fetching role options:', error);
      }

      setRoleOptions(['Guru', 'Wali Kelas', 'Pegawai', 'Admin']);
    };

    fetchRoleOptions();
  }, [canReadPegawai]);

  const fetchPegawai = async () => {
    if (!canReadPegawai) {
      setPegawai([]);
      setTotalCount(0);
      return;
    }

    try {
      setLoading(true);
      const params = {
        page: page + 1,
        per_page: rowsPerPage,
        search: searchTerm.trim(),
      };

      if (filters.role) {
        params.role = filters.role;
      }
      if (filters.status_kepegawaian) {
        params.status_kepegawaian = filters.status_kepegawaian;
      }
      if (filters.is_active !== '') {
        params.is_active = filters.is_active;
      }

      const response = await pegawaiExtendedService.getAll(params);
      if (response.success && response.data) {
        setPegawai(response.data.data || []);
        setTotalCount(response.data.total || 0);
      } else {
        setPegawai([]);
        setTotalCount(0);
      }
    } catch (error) {
      console.error('Error fetching pegawai:', error);
      enqueueSnackbar(error.message || 'Gagal mengambil data pegawai', {
        variant: 'error',
      });
      setPegawai([]);
      setTotalCount(0);
    } finally {
      setLoading(false);
    }
  };

  const handleView = (pegawaiItem) => {
    setSelectedPegawai(pegawaiItem);
    setOpenDetailDialog(true);
  };

  const handleEdit = (pegawaiItem) => {
    if (!canManagePegawai) {
      enqueueSnackbar('Anda tidak memiliki izin untuk mengubah data pegawai', { variant: 'warning' });
      return;
    }

    const targetUserId = pegawaiItem?.id;
    if (!targetUserId) {
      enqueueSnackbar('User pegawai tidak valid', { variant: 'error' });
      return;
    }

    navigate(`/manajemen-pengguna/data-pribadi/${targetUserId}?type=pegawai`);
  };

  const handleFilterChange = (filterName, value) => {
    setFilters((prev) => ({
      ...prev,
      [filterName]: value,
    }));
    setPage(0);
  };

  const buildExportParams = () => {
    const params = {};
    const normalizedSearch = String(searchTerm ?? '').trim();
    if (normalizedSearch !== '') {
      params.search = normalizedSearch;
    }

    if (filters.role) {
      params.role = filters.role;
    }

    if (filters.status_kepegawaian) {
      params.status_kepegawaian = filters.status_kepegawaian;
    }

    if (filters.is_active !== '') {
      params.is_active = filters.is_active;
    }

    return params;
  };

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

  const handleExport = async () => {
    try {
      setExportLoading(true);
      const response = await pegawaiExtendedService.export(buildExportParams());
      downloadBlobResponse(response, `data-pegawai-lengkap-${getServerDateString()}.xlsx`);
      enqueueSnackbar('Export data pegawai lengkap berhasil', { variant: 'success' });
    } catch (error) {
      console.error('Error exporting pegawai:', error);
      enqueueSnackbar(error?.response?.data?.message || error?.message || 'Gagal export data pegawai', {
        variant: 'error',
      });
    } finally {
      setExportLoading(false);
    }
  };

  const handleChangePage = (event, newPage) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event) => {
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const totalPages = Math.max(1, Math.ceil(totalCount / rowsPerPage));
  const displayFrom = totalCount === 0 ? 0 : (page * rowsPerPage) + 1;
  const displayTo = Math.min((page + 1) * rowsPerPage, totalCount);

  const getRoleLabel = (pegawaiItem) => {
    if (!Array.isArray(pegawaiItem.roles) || pegawaiItem.roles.length === 0) {
      return '-';
    }

    return pegawaiItem.roles.map((role) => role.name).join(', ');
  };

  const renderDetailDialog = () => {
    if (!selectedPegawai) {
      return null;
    }

    return (
      <Dialog open={openDetailDialog} onClose={() => setOpenDetailDialog(false)} maxWidth="lg" fullWidth>
        <DialogTitle>
          <Box display="flex" alignItems="center" gap={2}>
            <Avatar src={resolveProfilePhotoUrl(selectedPegawai.foto_profil_url || selectedPegawai.foto_profil) || undefined} sx={{ width: 56, height: 56 }}>
              <User size={24} />
            </Avatar>
            <Box>
              <Typography variant="h6">{selectedPegawai.nama_lengkap}</Typography>
              <Typography variant="body2" color="textSecondary">
                {selectedPegawai.roles?.[0]?.name || '-'} - {selectedPegawai.nip || 'Tanpa NIP'}
              </Typography>
            </Box>
          </Box>
        </DialogTitle>
        <DialogContent>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <User size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Pribadi
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">NIK:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.nik || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tempat Lahir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.tempat_lahir || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tanggal Lahir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.tanggal_lahir || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jenis Kelamin:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">
                      {selectedPegawai.jenis_kelamin === 'L' ? 'Laki-laki' : selectedPegawai.jenis_kelamin === 'P' ? 'Perempuan' : '-'}
                    </Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Agama:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.agama || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Status Pernikahan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.status_pernikahan?.replace('_', ' ') || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Briefcase size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Kepegawaian
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Status:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedPegawai.status_kepegawaian || '-'} size="small" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">NIP:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.nip || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">NUPTK:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.nuptk || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Golongan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.golongan || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jabatan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.jabatan || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Bidang Studi:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.bidang_studi || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Home size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Alamat
                </Typography>
                <Typography variant="body2" paragraph>
                  {selectedPegawai.alamat || '-'}
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">RT/RW:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">
                      {selectedPegawai.rt && selectedPegawai.rw ? `${selectedPegawai.rt}/${selectedPegawai.rw}` : '-'}
                    </Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Kelurahan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.kelurahan || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Kecamatan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.kecamatan || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Kota/Kabupaten:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.kota_kabupaten || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Provinsi:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.provinsi || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Phone size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Kontak
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Email:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.email || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">No. Telepon:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.no_telepon || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Telepon Rumah:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.telepon_rumah || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <GraduationCap size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Pendidikan
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Pendidikan Terakhir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.pendidikan_terakhir || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jurusan:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.jurusan || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Institusi:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.institusi || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tahun Lulus:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.tahun_lulus || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Clock size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Pengaturan Absensi
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Wajib Absen:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedPegawai.wajib_absen ? 'Ya' : 'Tidak'} size="small" color={selectedPegawai.wajib_absen ? 'success' : 'default'} />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jam Masuk:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.jam_masuk || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jam Pulang:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedPegawai.jam_pulang || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">GPS Tracking:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedPegawai.gps_tracking ? 'Aktif' : 'Nonaktif'} size="small" color={selectedPegawai.gps_tracking ? 'success' : 'default'} />
                  </Grid>
                </Grid>
              </Paper>
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpenDetailDialog(false)}>Tutup</Button>
          {canManagePegawai && (
            <Button variant="contained" onClick={() => handleEdit(selectedPegawai)}>
              Buka Data Pribadi
            </Button>
          )}
        </DialogActions>
      </Dialog>
    );
  };

  return (
    <Container maxWidth="xl">
      <Box className="py-6">
        <Box className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-blue-100 rounded-lg">
            <Briefcase className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <Typography variant="h4" className="font-bold text-gray-900">
              Data Pegawai Lengkap
            </Typography>
            <Typography variant="body2" className="text-gray-600">
              Kelola data lengkap pegawai dalam sistem
            </Typography>
          </div>
        </Box>

        <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
          <Box className="flex flex-col lg:flex-row gap-4 mb-4">
            <TextField
              placeholder="Cari nama, NIP, email..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="flex-1 lg:max-w-md"
              size="small"
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <Search className="w-4 h-4 text-gray-400" />
                  </InputAdornment>
                ),
              }}
              sx={{
                '& .MuiOutlinedInput-root': {
                  '&:hover fieldset': {
                    borderColor: '#3B82F6',
                  },
                  '&.Mui-focused fieldset': {
                    borderColor: '#3B82F6',
                  },
                },
              }}
            />

            <Box className="flex flex-wrap gap-3">
              <FormControl size="small" className="min-w-[170px]">
                <InputLabel>Role</InputLabel>
                <Select
                  value={filters.role}
                  onChange={(event) => handleFilterChange('role', event.target.value)}
                  label="Role"
                >
                  <MenuItem value="">
                    <em>Semua Role</em>
                  </MenuItem>
                  {roleOptions.map((role) => (
                    <MenuItem key={role} value={role}>
                      {role}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl size="small" className="min-w-[190px]">
                <InputLabel>Status Kepegawaian</InputLabel>
                <Select
                  value={filters.status_kepegawaian}
                  onChange={(event) => handleFilterChange('status_kepegawaian', event.target.value)}
                  label="Status Kepegawaian"
                >
                  <MenuItem value="">
                    <em>Semua Status</em>
                  </MenuItem>
                  {statusKepegawaianOptions.map((status) => (
                    <MenuItem key={status} value={status}>
                      {status}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl size="small" className="min-w-[140px]">
                <InputLabel>Status</InputLabel>
                <Select
                  value={filters.is_active}
                  onChange={(event) => handleFilterChange('is_active', event.target.value)}
                  label="Status"
                >
                  <MenuItem value="">
                    <em>Semua</em>
                  </MenuItem>
                  <MenuItem value="1">Aktif</MenuItem>
                  <MenuItem value="0">Nonaktif</MenuItem>
                </Select>
              </FormControl>
            </Box>
          </Box>

          {canManagePegawai && (
            <Box className="flex justify-end gap-2">
              <Button
                variant="outlined"
                size="small"
                startIcon={<Download className="w-4 h-4" />}
                onClick={handleExport}
                disabled={exportLoading}
              >
                {exportLoading ? 'Exporting...' : 'Export'}
              </Button>
            </Box>
          )}
        </Paper>

        <TableContainer component={Paper} className="shadow-sm border border-gray-100 rounded-xl overflow-hidden">
          <Table>
            <TableHead>
              <TableRow className="bg-gray-50">
                <TableCell width={70}>No</TableCell>
                <TableCell>Nama Lengkap</TableCell>
                <TableCell>NIP</TableCell>
                <TableCell>Email</TableCell>
                <TableCell>Role</TableCell>
                <TableCell>Status Kepegawaian</TableCell>
                <TableCell>Status</TableCell>
                <TableCell align="center" width={80}>Aksi</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={8} align="center" className="py-8">
                    <CircularProgress size={24} />
                  </TableCell>
                </TableRow>
              ) : pegawai.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} align="center" className="py-8">
                    <Typography variant="body2" color="textSecondary">
                      Tidak ada data pegawai
                    </Typography>
                  </TableCell>
                </TableRow>
              ) : (
                pegawai.map((pegawaiItem, index) => (
                  <TableRow key={pegawaiItem.id} hover className="hover:bg-gray-50 transition-colors">
                    <TableCell>{(page * rowsPerPage) + index + 1}</TableCell>
                    <TableCell>
                      <Box className="flex items-center gap-3">
                        <Avatar src={resolveProfilePhotoUrl(pegawaiItem.foto_profil_url || pegawaiItem.foto_profil) || undefined} sx={{ width: 32, height: 32 }}>
                          {(pegawaiItem.nama_lengkap || 'P').charAt(0).toUpperCase()}
                        </Avatar>
                        <Box>
                          <Typography variant="body2" className="font-medium">
                            {pegawaiItem.nama_lengkap || '-'}
                          </Typography>
                          <Typography variant="caption" color="textSecondary">
                            {pegawaiItem.nik || '-'}
                          </Typography>
                        </Box>
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{pegawaiItem.nip || '-'}</Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{pegawaiItem.email || '-'}</Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{getRoleLabel(pegawaiItem)}</Typography>
                    </TableCell>
                    <TableCell>
                      <Chip label={pegawaiItem.status_kepegawaian || '-'} size="small" variant="outlined" />
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={pegawaiItem.is_active ? 'Aktif' : 'Nonaktif'}
                        size="small"
                        color={pegawaiItem.is_active ? 'success' : 'error'}
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center gap-1">
                        <IconButton size="small" onClick={() => handleView(pegawaiItem)} title="Lihat Detail">
                          <Eye className="w-4 h-4 text-blue-500" />
                        </IconButton>
                        {canManagePegawai && (
                          <IconButton size="small" onClick={() => handleEdit(pegawaiItem)} title="Buka Data Pribadi">
                            <Edit className="w-4 h-4 text-emerald-500" />
                          </IconButton>
                        )}
                      </Box>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>

        {totalCount > 0 && (
          <Paper className="p-4 mt-4 shadow-sm border border-gray-100">
            <Box className="flex flex-col sm:flex-row justify-between items-center gap-4">
              <Typography variant="body2" color="textSecondary">
                Menampilkan {displayFrom} - {displayTo} dari {totalCount} data
              </Typography>
              <Box className="flex items-center gap-4">
                <Box className="flex items-center gap-2">
                  <Typography variant="body2" color="textSecondary">
                    Per halaman:
                  </Typography>
                  <FormControl size="small">
                    <Select
                      value={rowsPerPage}
                      onChange={handleChangeRowsPerPage}
                      className="min-w-[70px]"
                    >
                      <MenuItem value={10}>10</MenuItem>
                      <MenuItem value={15}>15</MenuItem>
                      <MenuItem value={25}>25</MenuItem>
                      <MenuItem value={50}>50</MenuItem>
                      <MenuItem value={100}>100</MenuItem>
                    </Select>
                  </FormControl>
                </Box>
                <Pagination
                  count={totalPages}
                  page={page + 1}
                  onChange={(event, value) => handleChangePage(event, value - 1)}
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

        {renderDetailDialog()}
      </Box>
    </Container>
  );
};

export default DataPegawaiLengkap;

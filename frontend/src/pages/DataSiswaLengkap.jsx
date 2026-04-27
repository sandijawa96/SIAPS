import React, { useEffect, useMemo, useState } from 'react';
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
  Calendar,
  Camera,
  Download,
  Edit,
  Eye,
  GraduationCap,
  History,
  Home,
  Phone,
  School,
  Search,
  User,
  Users,
} from 'lucide-react';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';
import { useSnackbar } from 'notistack';
import { useNavigate } from 'react-router-dom';
import RiwayatKelasModal from '../components/modals/RiwayatKelasModal.jsx';
import FaceTemplateModal from '../components/users/FaceTemplateModal.jsx';
import { useAuth } from '../hooks/useAuth.jsx';
import { kelasAPI } from '../services/kelasService.js';
import { getServerDateString } from '../services/serverClock';
import siswaExtendedService from '../services/siswaExtendedService.jsx';
import tahunAjaranAPI from '../services/tahunAjaranService.js';
import { tingkatAPI } from '../services/tingkatService.js';

const DataSiswaLengkap = () => {
  const navigate = useNavigate();
  const [siswa, setSiswa] = useState([]);
  const [loading, setLoading] = useState(false);
  const [openDetailDialog, setOpenDetailDialog] = useState(false);
  const [selectedSiswa, setSelectedSiswa] = useState(null);
  const [openRiwayatModal, setOpenRiwayatModal] = useState(false);
  const [selectedSiswaForRiwayat, setSelectedSiswaForRiwayat] = useState(null);
  const [faceTemplateState, setFaceTemplateState] = useState({ open: false, user: null });
  const [exportLoading, setExportLoading] = useState(false);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);
  const [searchTerm, setSearchTerm] = useState('');
  const [totalCount, setTotalCount] = useState(0);
  const [kelasOptions, setKelasOptions] = useState([]);
  const [tingkatOptions, setTingkatOptions] = useState([]);
  const [tahunAjaranOptions, setTahunAjaranOptions] = useState([]);
  const [filters, setFilters] = useState({
    kelas_id: '',
    tingkat_id: '',
    tahun_ajaran_id: '',
    is_active: '',
  });
  const { enqueueSnackbar } = useSnackbar();
  const { hasPermission } = useAuth();
  const canReadSiswa = hasPermission('view_siswa') || hasPermission('manage_students');
  const canManageStudents = hasPermission('manage_students');
  const canAccessFaceTemplateControls = hasPermission('manage_attendance_settings') || hasPermission('unlock_face_template_submit_quota');

  useEffect(() => {
    fetchSiswa();
  }, [page, rowsPerPage, searchTerm, filters, canReadSiswa]);

  useEffect(() => {
    if (!canReadSiswa) {
      setKelasOptions([]);
      setTingkatOptions([]);
      setTahunAjaranOptions([]);
      return;
    }

    const extractList = (rawResponse) => {
      const payload = rawResponse?.data ?? rawResponse;
      if (Array.isArray(payload?.data)) {
        return payload.data;
      }
      if (Array.isArray(payload)) {
        return payload;
      }

      return [];
    };

    const mapKelasOptions = (kelasData) => (
      kelasData
        .map((kelasItem) => {
          const namaKelas = kelasItem?.nama_kelas ?? kelasItem?.namaKelas ?? '';
          if (!kelasItem?.id || !namaKelas) {
            return null;
          }

          return {
            id: String(kelasItem.id),
            nama_kelas: namaKelas,
            tingkat_id: kelasItem?.tingkat_id ? String(kelasItem.tingkat_id) : '',
            tingkat_nama: kelasItem?.tingkat ?? kelasItem?.tingkat_nama ?? kelasItem?.tingkatNama ?? '',
          };
        })
        .filter(Boolean)
    );

    const fetchKelasOptions = async () => {
      try {
        const response = await kelasAPI.getAll({
          per_page: 200,
          sort_by: 'nama_kelas',
          sort_direction: 'asc',
        });
        const kelasData = extractList(response?.data);
        setKelasOptions(mapKelasOptions(kelasData));
      } catch (error) {
        console.warn('Primary kelas options failed, using siswa fallback:', error);
        try {
          const siswaResponse = await siswaExtendedService.getAll({
            page: 1,
            per_page: 500,
          });
          const siswaData = extractList(siswaResponse?.data?.data);
          const fallbackKelasMap = new Map();

          siswaData.forEach((item) => {
            const kelasAktif = item?.kelas_aktif;
            if (kelasAktif?.id && kelasAktif?.nama_kelas) {
              const tingkatName = typeof kelasAktif?.tingkat === 'string'
                ? kelasAktif.tingkat
                : kelasAktif?.tingkat?.nama ?? '';

              fallbackKelasMap.set(String(kelasAktif.id), {
                id: String(kelasAktif.id),
                nama_kelas: kelasAktif.nama_kelas,
                tingkat_id: '',
                tingkat_nama: tingkatName,
              });
            }
          });

          setKelasOptions(Array.from(fallbackKelasMap.values()));
        } catch (fallbackError) {
          console.error('Error fetching kelas options fallback:', fallbackError);
          setKelasOptions([]);
        }
      }
    };

    const fetchTahunAjaranOptions = async () => {
      try {
        const response = await tahunAjaranAPI.getAll({
          per_page: 200,
          sort_by: 'tanggal_mulai',
          sort_direction: 'desc',
        });

        const tahunAjaranData = extractList(response);
        setTahunAjaranOptions(
          tahunAjaranData
            .filter((item) => item && item.id && item.nama)
            .map((item) => ({
              id: String(item.id),
              nama: item.nama,
            })),
        );
      } catch (error) {
        console.error('Error fetching tahun ajaran options:', error);
        setTahunAjaranOptions([]);
      }
    };

    const fetchTingkatOptions = async () => {
      try {
        const response = await tingkatAPI.getAll({ is_active: true });
        const tingkatData = extractList(response);
        setTingkatOptions(
          tingkatData
            .filter((item) => item && item.id && item.nama)
            .map((item) => ({
              id: String(item.id),
              nama: item.nama,
            })),
        );
      } catch (error) {
        console.error('Error fetching tingkat options:', error);
        setTingkatOptions([]);
      }
    };

    fetchTingkatOptions();
    fetchKelasOptions();
    fetchTahunAjaranOptions();
  }, [canReadSiswa]);

  const fetchSiswa = async () => {
    if (!canReadSiswa) {
      setSiswa([]);
      setTotalCount(0);
      return;
    }

    try {
      setLoading(true);

      const cleanParams = {
        page: page + 1,
        per_page: rowsPerPage,
      };

      const normalizedSearch = String(searchTerm ?? '').trim();
      if (normalizedSearch !== '') {
        cleanParams.search = normalizedSearch;
      }

      const kelasId = String(filters.kelas_id ?? '').trim();
      if (/^\d+$/.test(kelasId)) {
        cleanParams.kelas_id = kelasId;
      }

      const tingkatId = String(filters.tingkat_id ?? '').trim();
      if (/^\d+$/.test(tingkatId)) {
        cleanParams.tingkat_id = tingkatId;
      }

      const tahunAjaranId = String(filters.tahun_ajaran_id ?? '').trim();
      if (/^\d+$/.test(tahunAjaranId)) {
        cleanParams.tahun_ajaran_id = tahunAjaranId;
      }

      if (filters.is_active !== '') {
        cleanParams.is_active = filters.is_active;
      }

      const response = await siswaExtendedService.getAll(cleanParams);

      if (response.data && response.data.success) {
        const paginatedData = response.data.data;
        const siswaData = Array.isArray(paginatedData.data) ? paginatedData.data : [];
        setSiswa(siswaData);
        setTotalCount(paginatedData.total || 0);
      } else {
        throw new Error(response.data?.message || 'Gagal mengambil data siswa');
      }
    } catch (error) {
      console.error('Error fetching siswa:', error);
      enqueueSnackbar(error.message || 'Gagal mengambil data siswa', {
        variant: 'error',
      });
      setSiswa([]);
      setTotalCount(0);
    } finally {
      setLoading(false);
    }
  };

  const handleView = (siswaItem) => {
    setSelectedSiswa(siswaItem);
    setOpenDetailDialog(true);
  };

  const handleEdit = (siswaItem) => {
    if (!canManageStudents) {
      enqueueSnackbar('Anda tidak memiliki izin untuk mengubah data siswa', { variant: 'warning' });
      return;
    }

    const targetUserId = siswaItem?.id;
    if (!targetUserId) {
      enqueueSnackbar('User siswa tidak valid', { variant: 'error' });
      return;
    }

    navigate(`/manajemen-pengguna/data-pribadi/${targetUserId}?type=siswa`);
  };

  const handleViewRiwayatKelas = (siswaItem) => {
    setSelectedSiswaForRiwayat(siswaItem);
    setOpenRiwayatModal(true);
  };

  const handleManageFaceTemplate = (siswaItem) => {
    setFaceTemplateState({
      open: true,
      user: siswaItem,
    });
  };

  const handleCloseFaceTemplateModal = () => {
    setFaceTemplateState({
      open: false,
      user: null,
    });
  };

  const handleCloseRiwayatModal = () => {
    setOpenRiwayatModal(false);
    setSelectedSiswaForRiwayat(null);
  };

  const handleFilterChange = (filterName, value) => {
    if (filterName === 'tingkat_id') {
      setFilters((prev) => ({
        ...prev,
        tingkat_id: value,
        kelas_id: '',
      }));
      setPage(0);
      return;
    }

    setFilters((prev) => ({
      ...prev,
      [filterName]: value,
    }));
    setPage(0);
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setFilters({
      kelas_id: '',
      tingkat_id: '',
      tahun_ajaran_id: '',
      is_active: '',
    });
    setPage(0);
  };

  const buildExportParams = () => {
    const params = {};

    const normalizedSearch = String(searchTerm ?? '').trim();
    if (normalizedSearch !== '') {
      params.search = normalizedSearch;
    }

    const kelasId = String(filters.kelas_id ?? '').trim();
    if (/^\d+$/.test(kelasId)) {
      params.kelas_id = kelasId;
    }

    const tingkatId = String(filters.tingkat_id ?? '').trim();
    if (/^\d+$/.test(tingkatId)) {
      params.tingkat_id = tingkatId;
    }

    const tahunAjaranId = String(filters.tahun_ajaran_id ?? '').trim();
    if (/^\d+$/.test(tahunAjaranId)) {
      params.tahun_ajaran_id = tahunAjaranId;
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
      const response = await siswaExtendedService.exportData(buildExportParams());
      downloadBlobResponse(response, `data-siswa-lengkap-${getServerDateString()}.xlsx`);
      enqueueSnackbar('Export data siswa lengkap berhasil', { variant: 'success' });
    } catch (error) {
      console.error('Error exporting siswa:', error);
      enqueueSnackbar(error?.response?.data?.message || error?.message || 'Gagal export data siswa', {
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

  const filteredKelasOptions = useMemo(() => {
    const selectedTingkatId = String(filters.tingkat_id ?? '').trim();
    const tingkatById = new Map(
      tingkatOptions.map((tingkatItem) => [String(tingkatItem.id), tingkatItem]),
    );
    const selectedTingkatName = selectedTingkatId
      ? String(tingkatById.get(selectedTingkatId)?.nama ?? '').toLowerCase()
      : '';

    const options = kelasOptions.filter((kelasItem) => {
      if (!selectedTingkatId) {
        return true;
      }

      if (kelasItem.tingkat_id && String(kelasItem.tingkat_id) === selectedTingkatId) {
        return true;
      }

      if (selectedTingkatName && kelasItem.tingkat_nama) {
        return String(kelasItem.tingkat_nama).toLowerCase() === selectedTingkatName;
      }

      return false;
    });

    return [...options].sort((a, b) => String(a.nama_kelas).localeCompare(String(b.nama_kelas), 'id', {
      numeric: true,
      sensitivity: 'base',
    }));
  }, [kelasOptions, filters.tingkat_id, tingkatOptions]);

  const totalPages = Math.max(1, Math.ceil(totalCount / rowsPerPage));
  const displayFrom = totalCount === 0 ? 0 : (page * rowsPerPage) + 1;
  const displayTo = Math.min((page + 1) * rowsPerPage, totalCount);

  const renderDetailDialog = () => {
    if (!selectedSiswa) {
      return null;
    }

    const kelasAktif = selectedSiswa.kelas_aktif;
    const kelasAwal = selectedSiswa.kelas_awal;

    return (
      <Dialog open={openDetailDialog} onClose={() => setOpenDetailDialog(false)} maxWidth="lg" fullWidth>
        <DialogTitle>
          <Box display="flex" alignItems="center" gap={2}>
            <Avatar src={resolveProfilePhotoUrl(selectedSiswa.foto_profil_url || selectedSiswa.foto_profil) || undefined} sx={{ width: 56, height: 56 }}>
              <User size={24} />
            </Avatar>
            <Box>
              <Typography variant="h6">{selectedSiswa.nama_lengkap}</Typography>
              <Typography variant="body2" color="textSecondary">
                {selectedSiswa.nis || 'Tanpa NIS'} - {kelasAktif?.nama_kelas || 'Tanpa Kelas Aktif'}
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
                    <Typography variant="body2" color="textSecondary">NIS:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.nis || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">NISN:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.nisn || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tempat Lahir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.tempat_lahir || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tanggal Lahir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.tanggal_lahir || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Jenis Kelamin:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">
                      {selectedSiswa.jenis_kelamin === 'L' ? 'Laki-laki' : selectedSiswa.jenis_kelamin === 'P' ? 'Perempuan' : '-'}
                    </Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Agama:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.agama || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <School size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Akademik
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tahun Masuk:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.tahun_masuk || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Kelas Awal:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={kelasAwal?.nama_kelas || '-'} size="small" variant="outlined" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">TA Kelas Awal:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{kelasAwal?.tahun_ajaran?.nama || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tanggal Masuk Kelas Awal:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{kelasAwal?.tanggal_masuk || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Kelas Aktif:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={kelasAktif?.nama_kelas || '-'} size="small" color="primary" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Tingkat Aktif:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{kelasAktif?.tingkat || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">TA Aktif:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{kelasAktif?.tahun_ajaran?.nama || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Asal Sekolah:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.asal_sekolah || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Status:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedSiswa.is_active ? 'Aktif' : 'Tidak Aktif'} size="small" color={selectedSiswa.is_active ? 'success' : 'default'} />
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
                  {selectedSiswa.alamat || '-'}
                </Typography>
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
                    <Typography variant="body2">{selectedSiswa.email || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">No. HP Siswa:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.no_hp_siswa || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Users size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Data Orang Tua
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Nama Ayah:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.nama_ayah || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Pekerjaan Ayah:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.pekerjaan_ayah || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">No. HP Ayah:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.no_hp_ayah || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Nama Ibu:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.nama_ibu || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Pekerjaan Ibu:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.pekerjaan_ibu || '-'}</Typography>
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">No. HP Ibu:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Typography variant="body2">{selectedSiswa.no_hp_ibu || '-'}</Typography>
                  </Grid>
                </Grid>
              </Paper>
            </Grid>

            <Grid item xs={12} md={6}>
              <Paper sx={{ p: 2 }}>
                <Typography variant="h6" gutterBottom color="primary">
                  <Calendar size={20} style={{ marginRight: 8, verticalAlign: 'middle' }} />
                  Statistik Absensi
                </Typography>
                <Grid container spacing={1}>
                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Hadir:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedSiswa.statistik_absensi?.total_hadir || 0} size="small" color="success" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Izin:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedSiswa.statistik_absensi?.total_izin || 0} size="small" color="warning" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Sakit:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedSiswa.statistik_absensi?.total_sakit || 0} size="small" color="info" />
                  </Grid>

                  <Grid item xs={6}>
                    <Typography variant="body2" color="textSecondary">Alpha:</Typography>
                  </Grid>
                  <Grid item xs={6}>
                    <Chip label={selectedSiswa.statistik_absensi?.total_alpha || 0} size="small" color="error" />
                  </Grid>
                </Grid>
              </Paper>
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpenDetailDialog(false)}>Tutup</Button>
          {canAccessFaceTemplateControls && (
            <Button variant="outlined" onClick={() => handleManageFaceTemplate(selectedSiswa)}>
              Template Wajah
            </Button>
          )}
          {canManageStudents && (
            <Button variant="contained" onClick={() => handleEdit(selectedSiswa)}>
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
            <School className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <Typography variant="h4" className="font-bold text-gray-900">
              Data Siswa Lengkap
            </Typography>
            <Typography variant="body2" className="text-gray-600">
              Kelola data lengkap siswa dalam sistem
            </Typography>
          </div>
        </Box>

        <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
          <Box className="flex flex-col lg:flex-row gap-4 mb-4">
            <TextField
              placeholder="Cari nama, NIS, NISN, email..."
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
              <FormControl size="small" className="min-w-[150px]">
                <InputLabel>Tingkat</InputLabel>
                <Select
                  value={filters.tingkat_id}
                  onChange={(event) => handleFilterChange('tingkat_id', event.target.value)}
                  label="Tingkat"
                >
                  <MenuItem value="">
                    <em>Semua Tingkat</em>
                  </MenuItem>
                  {tingkatOptions.map((tingkatItem) => (
                    <MenuItem key={tingkatItem.id} value={tingkatItem.id}>
                      {tingkatItem.nama}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl size="small" className="min-w-[150px]">
                <InputLabel>Kelas Aktif</InputLabel>
                <Select
                  value={filters.kelas_id}
                  onChange={(event) => handleFilterChange('kelas_id', event.target.value)}
                  label="Kelas Aktif"
                >
                  <MenuItem value="">
                    <em>Semua Kelas Aktif</em>
                  </MenuItem>
                  {filteredKelasOptions.map((kelasItem) => (
                    <MenuItem key={kelasItem.id} value={kelasItem.id}>
                      {kelasItem.nama_kelas}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl size="small" className="min-w-[170px]">
                <InputLabel>TA Aktif</InputLabel>
                <Select
                  value={filters.tahun_ajaran_id}
                  onChange={(event) => handleFilterChange('tahun_ajaran_id', event.target.value)}
                  label="TA Aktif"
                >
                  <MenuItem value="">
                    <em>Semua TA Aktif</em>
                  </MenuItem>
                  {tahunAjaranOptions.map((tahunAjaran) => (
                    <MenuItem key={tahunAjaran.id} value={tahunAjaran.id}>
                      {tahunAjaran.nama}
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
                    <em>Semua Status</em>
                  </MenuItem>
                  <MenuItem value="1">Aktif</MenuItem>
                  <MenuItem value="0">Tidak Aktif</MenuItem>
                </Select>
              </FormControl>

              <Button
                variant="outlined"
                size="small"
                onClick={handleResetFilters}
                className="min-w-[130px]"
              >
                Reset Filter
              </Button>
            </Box>
          </Box>

          {canManageStudents && (
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
                <TableCell>NIS</TableCell>
                <TableCell>NISN</TableCell>
                <TableCell>Email</TableCell>
                <TableCell>Kelas Aktif</TableCell>
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
              ) : siswa.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} align="center" className="py-8">
                    <Typography variant="body2" color="textSecondary">
                      Tidak ada data siswa
                    </Typography>
                  </TableCell>
                </TableRow>
              ) : (
                siswa.map((siswaItem, index) => (
                  <TableRow key={siswaItem.id} hover className="hover:bg-gray-50 transition-colors">
                    <TableCell>{(page * rowsPerPage) + index + 1}</TableCell>
                    <TableCell>
                      <Box className="flex items-center gap-3">
                        <Avatar
                          src={resolveProfilePhotoUrl(siswaItem.foto_profil_url || siswaItem.foto_profil) || undefined}
                          sx={{ width: 32, height: 32, bgcolor: '#d1d5db', color: '#ffffff' }}
                        >
                          {!resolveProfilePhotoUrl(siswaItem.foto_profil_url || siswaItem.foto_profil) && <User className="w-4 h-4" />}
                        </Avatar>
                        <Typography variant="body2" className="font-medium">
                          {siswaItem.nama_lengkap || '-'}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{siswaItem.nis || '-'}</Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{siswaItem.nisn || '-'}</Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{siswaItem.email || '-'}</Typography>
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={siswaItem.kelas_aktif?.nama_kelas || '-'}
                        size="small"
                        variant="outlined"
                      />
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={siswaItem.is_active ? 'Aktif' : 'Tidak Aktif'}
                        size="small"
                        color={siswaItem.is_active ? 'success' : 'error'}
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center gap-1">
                        <IconButton size="small" onClick={() => handleView(siswaItem)} title="Lihat Detail">
                          <Eye className="w-4 h-4 text-blue-500" />
                        </IconButton>
                        <IconButton size="small" onClick={() => handleViewRiwayatKelas(siswaItem)} title="Riwayat Kelas">
                          <History className="w-4 h-4 text-blue-500" />
                        </IconButton>
                        {canAccessFaceTemplateControls && (
                          <IconButton size="small" onClick={() => handleManageFaceTemplate(siswaItem)} title="Template Wajah">
                            <Camera className="w-4 h-4 text-amber-600" />
                          </IconButton>
                        )}
                        {canManageStudents && (
                          <IconButton size="small" onClick={() => handleEdit(siswaItem)} title="Buka Data Pribadi">
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

        <FaceTemplateModal
          open={faceTemplateState.open}
          user={faceTemplateState.user}
          onClose={handleCloseFaceTemplateModal}
          onUpdated={(message, severity = 'info') => enqueueSnackbar(message, { variant: severity })}
        />

        <RiwayatKelasModal
          isOpen={openRiwayatModal}
          onClose={handleCloseRiwayatModal}
          siswaId={selectedSiswaForRiwayat?.id}
          siswaName={selectedSiswaForRiwayat?.nama_lengkap}
        />
      </Box>
    </Container>
  );
};

export default DataSiswaLengkap;

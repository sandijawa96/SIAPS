import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Switch,
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
  Activity,
  AlertTriangle,
  Calendar,
  CheckCircle,
  Clock,
  Eye,
  ExternalLink,
  MapPin,
  Phone,
  RefreshCw,
  RotateCcw,
  Search,
  User,
  Users,
  X,
} from 'lucide-react';
import { absensiRealtimeService } from '../services/absensiRealtimeService';
import { formatServerDate } from '../services/serverClock';
import useServerClock from '../hooks/useServerClock';

const STATUS_OPTIONS = [
  { value: 'Semua', label: 'Semua Status' },
  { value: 'Hadir', label: 'Hadir' },
  { value: 'Terlambat', label: 'Terlambat' },
  { value: 'Izin', label: 'Izin' },
  { value: 'Sakit', label: 'Sakit' },
  { value: 'Alpha', label: 'Alpha' },
  { value: 'Belum Absen', label: 'Belum Absen' },
];

const normalizeStatus = (value) => {
  const status = String(value || '').trim().toLowerCase();
  if (['hadir', 'present'].includes(status)) {
    return 'hadir';
  }
  if (['terlambat', 'late'].includes(status)) {
    return 'terlambat';
  }
  if (['izin', 'permission'].includes(status)) {
    return 'izin';
  }
  if (['sakit', 'sick'].includes(status)) {
    return 'sakit';
  }
  if (['alpha', 'absent'].includes(status)) {
    return 'alpha';
  }
  if (['belum absen', 'belum_absen', 'not checked in'].includes(status)) {
    return 'belum absen';
  }
  return status || 'hadir';
};

const formatStatusLabel = (status) => {
  switch (normalizeStatus(status)) {
    case 'hadir':
      return 'Hadir';
    case 'terlambat':
      return 'Terlambat';
    case 'izin':
      return 'Izin';
    case 'sakit':
      return 'Sakit';
    case 'alpha':
      return 'Alpha';
    case 'belum absen':
      return 'Belum Absen';
    default:
      return status || '-';
  }
};

const getStatusColor = (status) => {
  switch (normalizeStatus(status)) {
    case 'hadir':
      return 'success';
    case 'terlambat':
      return 'warning';
    case 'izin':
    case 'sakit':
      return 'info';
    case 'alpha':
    case 'belum absen':
      return 'error';
    default:
      return 'default';
  }
};

const getStatusIcon = (status) => {
  switch (normalizeStatus(status)) {
    case 'hadir':
      return <CheckCircle className="w-4 h-4 text-green-600" />;
    case 'terlambat':
      return <AlertTriangle className="w-4 h-4 text-yellow-600" />;
    case 'izin':
    case 'sakit':
      return <Users className="w-4 h-4 text-blue-600" />;
    case 'alpha':
    case 'belum absen':
      return <Clock className="w-4 h-4 text-red-600" />;
    default:
      return <Clock className="w-4 h-4 text-gray-600" />;
  }
};

const formatTime = (timeString) => {
  if (!timeString || timeString === '-') {
    return '-';
  }

  const raw = String(timeString).trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return raw || '-';
  }

  return `${String(match[1]).padStart(2, '0')}:${match[2]}`;
};

const toNumberOrNull = (value) => {
  const number = typeof value === 'string' ? Number.parseFloat(value) : Number(value);
  return Number.isFinite(number) ? number : null;
};

const hasValidCoordinate = (latitude, longitude) => (
  Number.isFinite(latitude)
  && Number.isFinite(longitude)
  && latitude >= -90
  && latitude <= 90
  && longitude >= -180
  && longitude <= 180
);

const formatCoordinate = (value) => {
  const number = toNumberOrNull(value);
  return Number.isFinite(number) ? number.toFixed(6) : '-';
};

const formatAccuracy = (value) => {
  const number = toNumberOrNull(value);
  return Number.isFinite(number) ? `${number.toFixed(1)} m` : '-';
};

const buildGoogleMapsUrl = (latitude, longitude) => {
  if (!hasValidCoordinate(latitude, longitude)) {
    return null;
  }
  return `https://maps.google.com/?q=${latitude},${longitude}`;
};

const toAvatar = (name) => (
  String(name || '')
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
);

const AbsensiRealtime = () => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [absensiData, setAbsensiData] = useState([]);
  const [summary, setSummary] = useState({
    hadir: 0,
    terlambat: 0,
    izin: 0,
    sakit: 0,
    alpha: 0,
  });
  const [paginationMeta, setPaginationMeta] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [filterStatus, setFilterStatus] = useState('Semua');
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedUser, setSelectedUser] = useState(null);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);

  const fetchAbsensiData = async () => {
    try {
      setLoading(true);
      setError('');

      const response = await absensiRealtimeService.getTodayAttendance({
        date: selectedDate || undefined,
        status: filterStatus !== 'Semua' ? filterStatus.toLowerCase() : undefined,
        search: debouncedSearchTerm || undefined,
        page,
        per_page: perPage,
      });

      if (!response.success) {
        throw new Error(response.message || 'Gagal mengambil data absensi');
      }

      const responseData = response.data || {};
      const attendances = Array.isArray(responseData.attendances) ? responseData.attendances : [];
      const incomingSummary = responseData.summary || {};
      const incomingPagination = responseData.pagination || {};

      const transformedData = attendances.map((item) => {
        const userName = item.user_name || 'Unknown User';
        const status = normalizeStatus(item.status || 'hadir');

        return {
          id: item.id,
          nama: userName,
          role: item.role || item.status_kepegawaian || 'User',
          status,
          jamMasuk: item.time || item.jam_masuk || '-',
          jamPulang: item.jam_pulang || item.jam_keluar || null,
          lokasi: item.location_in || item.location || 'Tidak diketahui',
          lokasiPulang: item.location_out || 'Tidak diketahui',
          latitudeMasuk: toNumberOrNull(item.latitude_masuk),
          longitudeMasuk: toNumberOrNull(item.longitude_masuk),
          latitudePulang: toNumberOrNull(item.latitude_pulang),
          longitudePulang: toNumberOrNull(item.longitude_pulang),
          accuracyMasuk: toNumberOrNull(item.gps_accuracy_masuk),
          accuracyPulang: toNumberOrNull(item.gps_accuracy_pulang),
          userPhotoUrl: item.user_photo_url || null,
          fotoMasukUrl: item.foto_masuk_url || null,
          fotoPulangUrl: item.foto_pulang_url || null,
          avatar: toAvatar(userName),
          metodeAbsensi: item.metode_absensi || 'selfie',
          keterangan: item.notes || item.keterangan || '-',
          durasiKerja: item.durasi_kerja || null,
          terlambat: Boolean(item.terlambat || item.is_late),
          menitTerlambat: Number(item.menit_terlambat || item.late_minutes || 0),
          tapStatus: Boolean(item.tap_status),
          tapMinutes: Number(item.tap_minutes || 0),
          totalTkMinutes: Number(item.total_tk_minutes || 0),
        };
      });

      setAbsensiData(transformedData);
      setSummary({
        hadir: Number(incomingSummary.hadir || 0),
        terlambat: Number(incomingSummary.terlambat || 0),
        izin: Number(incomingSummary.izin || 0),
        sakit: Number(incomingSummary.sakit || 0),
        alpha: Number(incomingSummary.alpha || 0),
      });
      setPaginationMeta({
        current_page: Number(incomingPagination.current_page || page),
        last_page: Number(incomingPagination.last_page || 1),
        per_page: Number(incomingPagination.per_page || perPage),
        total: Number(incomingPagination.total || transformedData.length),
        from: Number(incomingPagination.from || (transformedData.length ? 1 : 0)),
        to: Number(incomingPagination.to || transformedData.length),
      });
    } catch (fetchError) {
      setError(fetchError.message || 'Gagal memuat data absensi realtime');
      setAbsensiData([]);
      setSummary({
        hadir: 0,
        terlambat: 0,
        izin: 0,
        sakit: 0,
        alpha: 0,
      });
      setPaginationMeta({
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
        from: 0,
        to: 0,
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isServerClockSynced && serverDate && !selectedDate) {
      setSelectedDate(serverDate);
    }
  }, [isServerClockSynced, selectedDate, serverDate]);

  useEffect(() => {
    const timeout = setTimeout(() => {
      const trimmed = searchTerm.trim();
      setDebouncedSearchTerm(trimmed);
      setPage(1);
    }, 350);

    return () => clearTimeout(timeout);
  }, [searchTerm]);

  useEffect(() => {
    fetchAbsensiData();
  }, [selectedDate, filterStatus, debouncedSearchTerm, page, perPage]);

  useEffect(() => {
    if (!autoRefresh) {
      return undefined;
    }

    const interval = setInterval(() => {
      fetchAbsensiData();
    }, 300000);

    return () => clearInterval(interval);
  }, [autoRefresh, selectedDate, filterStatus, debouncedSearchTerm, page, perPage]);

  useEffect(() => {
    setPage(1);
  }, [filterStatus, selectedDate, perPage]);

  const statistics = useMemo(() => {
    return {
      hadir: Number(summary.hadir || 0),
      terlambat: Number(summary.terlambat || 0),
      izin: Number(summary.izin || 0),
      sakit: Number(summary.sakit || 0),
      alpha: Number(summary.alpha || 0),
    };
  }, [summary]);

  const totalRows = Number(paginationMeta.total || 0);
  const lastPage = Math.max(1, Number(paginationMeta.last_page || 1));
  const safePage = Number(paginationMeta.current_page || page || 1);
  const paginatedRows = absensiData;
  const from = Number(paginationMeta.from || 0);
  const to = Number(paginationMeta.to || 0);

  const resetFilter = () => {
    setSearchTerm('');
    setDebouncedSearchTerm('');
    setFilterStatus('Semua');
    setSelectedDate(isServerClockSynced && serverDate ? serverDate : '');
    setPerPage(15);
    setPage(1);
  };

  return (
    <div className="p-6 space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <Box className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-xl">
            <Activity className="w-6 h-6 text-blue-600" />
          </div>
          <div className="flex-1">
            <Typography variant="h5" className="font-bold text-gray-900">
              Absensi Realtime
            </Typography>
            <Typography variant="body2" className="text-gray-600 mt-1">
              Pantau status kehadiran siswa secara realtime berdasarkan tanggal.
            </Typography>
            <div className="flex flex-wrap gap-2 mt-3">
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                Monitoring Harian
              </span>
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                Auto Refresh 5 Menit
              </span>
            </div>
          </div>
        </Box>
      </div>

      {error && (
        <Alert severity="error" className="rounded-xl" onClose={() => setError('')}>
          {error}
        </Alert>
      )}

      <Paper className="p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="mb-4">
          <Typography variant="subtitle1" className="font-semibold text-gray-900">
            Filter Monitoring
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Gunakan kata kunci, tanggal, dan status untuk melihat data absensi yang dibutuhkan.
          </Typography>
        </div>

        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <TextField
            placeholder="Cari nama siswa, role, atau lokasi..."
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
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

          <TextField
            type="date"
            size="small"
            value={selectedDate}
            onChange={(event) => setSelectedDate(event.target.value)}
            sx={{ minWidth: 190 }}
          />

          <FormControl size="small" sx={{ minWidth: 180 }}>
            <Select
              displayEmpty
              value={filterStatus}
              onChange={(event) => setFilterStatus(event.target.value)}
            >
              {STATUS_OPTIONS.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Box>

        <Box className="flex flex-wrap items-center justify-between gap-3">
          <FormControlLabel
            control={<Switch checked={autoRefresh} onChange={(event) => setAutoRefresh(event.target.checked)} />}
            label="Auto Refresh (5 menit)"
          />

          <Box className="flex items-center gap-2">
            <Button
              variant="outlined"
              size="small"
              startIcon={<RotateCcw className="w-4 h-4" />}
              onClick={resetFilter}
            >
              Reset Filter
            </Button>
            <Button
              variant="outlined"
              size="small"
              startIcon={<RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />}
              onClick={fetchAbsensiData}
              disabled={loading}
            >
              Refresh
            </Button>
          </Box>
        </Box>
      </Paper>

      <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-green-100">
              <CheckCircle className="w-6 h-6 text-green-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Hadir</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.hadir}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-yellow-100">
              <AlertTriangle className="w-6 h-6 text-yellow-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Terlambat</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.terlambat}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-blue-100">
              <Users className="w-6 h-6 text-blue-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Izin</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.izin}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-purple-100">
              <Users className="w-6 h-6 text-purple-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Sakit</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.sakit}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-red-100">
              <Clock className="w-6 h-6 text-red-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Alpha</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.alpha}</p>
            </div>
          </div>
        </div>
      </div>

      <TableContainer component={Paper} className="border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <Table
          sx={{
            '& .MuiTableCell-head': {
              fontWeight: 600,
              backgroundColor: '#F8FAFC',
              color: '#1F2937',
            },
            '& .MuiTableCell-root': {
              borderColor: '#E5E7EB',
            },
          }}
        >
          <TableHead>
            <TableRow>
              <TableCell width={60}>No</TableCell>
              <TableCell>Siswa</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Waktu Masuk</TableCell>
              <TableCell>Waktu Pulang</TableCell>
              <TableCell>Terlambat</TableCell>
              <TableCell>TAP</TableCell>
              <TableCell>Total TK</TableCell>
              <TableCell>Lokasi</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={10} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && paginatedRows.length === 0 && (
              <TableRow>
                <TableCell colSpan={10} align="center">
                  Tidak ada data absensi untuk ditampilkan
                </TableCell>
              </TableRow>
            )}

            {!loading &&
              paginatedRows.map((item, index) => (
                <TableRow key={item.id} hover>
                  <TableCell>{(safePage - 1) * perPage + index + 1}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Avatar
                        src={item.fotoMasukUrl || item.userPhotoUrl || undefined}
                        alt={item.nama}
                        sx={{
                          width: 40,
                          height: 40,
                          fontSize: 12,
                          fontWeight: 700,
                          bgcolor: '#3b82f6',
                        }}
                        imgProps={{ loading: 'lazy' }}
                      >
                        {item.avatar || 'U'}
                      </Avatar>
                      <div>
                        <Typography variant="body2" className="font-semibold text-gray-900">
                          {item.nama}
                        </Typography>
                        <Typography variant="caption" className="text-gray-500">
                          {item.role}
                        </Typography>
                        {(item.fotoMasukUrl || item.fotoPulangUrl) && (
                          <Typography variant="caption" className="text-blue-600 block">
                            Foto absensi tersedia
                          </Typography>
                        )}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {getStatusIcon(item.status)}
                      <Chip
                        size="small"
                        label={formatStatusLabel(item.status)}
                        color={getStatusColor(item.status)}
                        variant={normalizeStatus(item.status) === 'belum absen' ? 'outlined' : 'filled'}
                      />
                    </div>
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" className="font-medium text-gray-900">
                      {formatTime(item.jamMasuk)}
                    </Typography>
                    {item.durasiKerja && (
                      <Typography variant="caption" className="text-blue-600">
                        Durasi: {item.durasiKerja}
                      </Typography>
                    )}
                  </TableCell>
                  <TableCell>{item.jamPulang ? formatTime(item.jamPulang) : '-'}</TableCell>
                  <TableCell>
                    {item.terlambat ? (
                      <Chip
                        size="small"
                        color="warning"
                        label={`+${item.menitTerlambat}m`}
                      />
                    ) : (
                      <Typography variant="body2" className="text-green-700">
                        Tepat waktu
                      </Typography>
                    )}
                  </TableCell>
                  <TableCell>
                    {item.tapStatus ? (
                      <Chip
                        size="small"
                        color="warning"
                        variant="outlined"
                        label={`${item.tapMinutes}m`}
                      />
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      color={item.totalTkMinutes > 0 ? 'error' : 'success'}
                      variant={item.totalTkMinutes > 0 ? 'filled' : 'outlined'}
                      label={`${item.totalTkMinutes}m`}
                    />
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-1 text-gray-600">
                      <div className="flex items-center gap-1">
                        <MapPin className="w-4 h-4" />
                        <span className="truncate max-w-[180px]">{item.lokasi}</span>
                      </div>
                      <Typography variant="caption" className="text-gray-500">
                        Masuk: {formatCoordinate(item.latitudeMasuk)}, {formatCoordinate(item.longitudeMasuk)}
                        {` • Akurasi: ${formatAccuracy(item.accuracyMasuk)}`}
                      </Typography>
                      {hasValidCoordinate(item.latitudePulang, item.longitudePulang) && (
                        <Typography variant="caption" className="text-gray-500">
                          Pulang: {formatCoordinate(item.latitudePulang)}, {formatCoordinate(item.longitudePulang)}
                          {` • Akurasi: ${formatAccuracy(item.accuracyPulang)}`}
                        </Typography>
                      )}
                      <div className="flex items-center gap-3">
                        {buildGoogleMapsUrl(item.latitudeMasuk, item.longitudeMasuk) && (
                          <a
                            href={buildGoogleMapsUrl(item.latitudeMasuk, item.longitudeMasuk)}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                            Peta Masuk
                          </a>
                        )}
                        {buildGoogleMapsUrl(item.latitudePulang, item.longitudePulang) && (
                          <a
                            href={buildGoogleMapsUrl(item.latitudePulang, item.longitudePulang)}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                            Peta Pulang
                          </a>
                        )}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell align="center">
                    <Box className="flex items-center justify-center gap-1">
                      <IconButton
                        size="small"
                        color="primary"
                        onClick={() => setSelectedUser(item)}
                        title="Lihat Detail"
                      >
                        <Eye className="w-4 h-4" />
                      </IconButton>
                      {normalizeStatus(item.status) === 'belum absen' && (
                        <IconButton size="small" color="success" title="Hubungi">
                          <Phone className="w-4 h-4" />
                        </IconButton>
                      )}
                    </Box>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Paper className="mt-4 px-4 py-3 border border-gray-200 rounded-xl shadow-sm">
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
              page={safePage}
              count={lastPage}
              onChange={(_, value) => setPage(value)}
              color="primary"
              shape="rounded"
              size="small"
            />
          </Box>
        </Box>
      </Paper>

      <Dialog
        open={Boolean(selectedUser)}
        onClose={() => setSelectedUser(null)}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle className="flex items-center justify-between">
          <span>Detail Absensi</span>
          <IconButton size="small" onClick={() => setSelectedUser(null)}>
            <X className="w-4 h-4" />
          </IconButton>
        </DialogTitle>
        <DialogContent dividers>
          {selectedUser && (
            <Box className="space-y-4">
              <Box className="flex items-center gap-3">
                <Avatar
                  src={selectedUser.fotoMasukUrl || selectedUser.userPhotoUrl || undefined}
                  alt={selectedUser.nama}
                  sx={{
                    width: 56,
                    height: 56,
                    fontSize: 18,
                    fontWeight: 700,
                    bgcolor: '#3b82f6',
                  }}
                >
                  {selectedUser.avatar || 'U'}
                </Avatar>
                <div>
                  <Typography variant="h6">{selectedUser.nama}</Typography>
                  <Typography variant="body2" color="text.secondary">
                    {selectedUser.role}
                  </Typography>
                </div>
              </Box>

              <div className="flex items-center gap-2">
                {getStatusIcon(selectedUser.status)}
                <Chip
                  size="small"
                  label={formatStatusLabel(selectedUser.status)}
                  color={getStatusColor(selectedUser.status)}
                />
              </div>

              <div className="space-y-2">
                <Typography variant="subtitle2" className="font-semibold text-gray-900">
                  Foto Absensi
                </Typography>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="border border-gray-200 rounded-lg p-2">
                    <Typography variant="caption" className="text-gray-600 block mb-1">
                      Foto Masuk
                    </Typography>
                    {selectedUser.fotoMasukUrl ? (
                      <a href={selectedUser.fotoMasukUrl} target="_blank" rel="noreferrer">
                        <Box
                          component="img"
                          src={selectedUser.fotoMasukUrl}
                          alt={`Foto masuk ${selectedUser.nama}`}
                          sx={{
                            width: '100%',
                            height: 170,
                            objectFit: 'cover',
                            borderRadius: '8px',
                            border: '1px solid #E5E7EB',
                          }}
                        />
                      </a>
                    ) : (
                      <Typography variant="caption" className="text-gray-500">
                        Tidak ada foto masuk
                      </Typography>
                    )}
                  </div>

                  <div className="border border-gray-200 rounded-lg p-2">
                    <Typography variant="caption" className="text-gray-600 block mb-1">
                      Foto Pulang
                    </Typography>
                    {selectedUser.fotoPulangUrl ? (
                      <a href={selectedUser.fotoPulangUrl} target="_blank" rel="noreferrer">
                        <Box
                          component="img"
                          src={selectedUser.fotoPulangUrl}
                          alt={`Foto pulang ${selectedUser.nama}`}
                          sx={{
                            width: '100%',
                            height: 170,
                            objectFit: 'cover',
                            borderRadius: '8px',
                            border: '1px solid #E5E7EB',
                          }}
                        />
                      </a>
                    ) : (
                      <Typography variant="caption" className="text-gray-500">
                        Tidak ada foto pulang
                      </Typography>
                    )}
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Calendar className="w-4 h-4 text-gray-500" />
                  <Typography variant="body2">
                    {formatServerDate(selectedDate, 'id-ID', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    }) || '-'}
                  </Typography>
                </div>
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-gray-500" />
                  <Typography variant="body2">Masuk: {formatTime(selectedUser.jamMasuk)}</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-gray-500" />
                  <Typography variant="body2">Pulang: {formatTime(selectedUser.jamPulang)}</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <AlertTriangle className="w-4 h-4 text-yellow-600" />
                  <Typography variant="body2">
                    Terlambat: {selectedUser.menitTerlambat} menit
                  </Typography>
                </div>
                <div className="flex items-start gap-2">
                  <MapPin className="w-4 h-4 text-gray-500 mt-0.5" />
                  <div>
                    <Typography variant="body2">{selectedUser.lokasi}</Typography>
                    <Typography variant="caption" className="text-gray-500 block">
                      Masuk: {formatCoordinate(selectedUser.latitudeMasuk)}, {formatCoordinate(selectedUser.longitudeMasuk)}
                      {` • Akurasi: ${formatAccuracy(selectedUser.accuracyMasuk)}`}
                    </Typography>
                    {hasValidCoordinate(selectedUser.latitudePulang, selectedUser.longitudePulang) && (
                      <Typography variant="caption" className="text-gray-500 block">
                        Pulang: {formatCoordinate(selectedUser.latitudePulang)}, {formatCoordinate(selectedUser.longitudePulang)}
                        {` • Akurasi: ${formatAccuracy(selectedUser.accuracyPulang)}`}
                      </Typography>
                    )}
                    <div className="flex items-center gap-3 mt-1">
                      {buildGoogleMapsUrl(selectedUser.latitudeMasuk, selectedUser.longitudeMasuk) && (
                        <a
                          href={buildGoogleMapsUrl(selectedUser.latitudeMasuk, selectedUser.longitudeMasuk)}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                        >
                          <ExternalLink className="w-3.5 h-3.5" />
                          Peta Masuk
                        </a>
                      )}
                      {buildGoogleMapsUrl(selectedUser.latitudePulang, selectedUser.longitudePulang) && (
                        <a
                          href={buildGoogleMapsUrl(selectedUser.latitudePulang, selectedUser.longitudePulang)}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                        >
                          <ExternalLink className="w-3.5 h-3.5" />
                          Peta Pulang
                        </a>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-start gap-2">
                  <User className="w-4 h-4 text-gray-500 mt-0.5" />
                  <Typography variant="body2" className="capitalize">
                    Metode: {selectedUser.metodeAbsensi}
                  </Typography>
                </div>
                {selectedUser.keterangan && selectedUser.keterangan !== '-' && (
                  <div className="flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 text-gray-500 mt-0.5" />
                    <Typography variant="body2">{selectedUser.keterangan}</Typography>
                  </div>
                )}
              </div>
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button variant="outlined" onClick={() => setSelectedUser(null)}>
            Tutup
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
};

export default AbsensiRealtime;

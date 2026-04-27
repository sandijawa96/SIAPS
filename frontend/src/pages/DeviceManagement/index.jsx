import React, { useState, useEffect } from 'react';
import {
  Card,
  CardContent,
  CardHeader,
  Typography,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Chip,
  Box,
  Alert,
  IconButton,
  Tooltip,
  CircularProgress
} from '@mui/material';
import {
  Smartphone,
  Delete,
  Refresh,
  Search,
  FilterList,
  Download
} from '@mui/icons-material';
import { getApiUrl } from '../../config/api';
import { formatServerDateTime } from '../../services/serverClock';
import { getStoredToken } from '../../utils/authStorage';

const getToken = () => getStoredToken();

const DeviceManagement = () => {

  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [resetDialogOpen, setResetDialogOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [resetReason, setResetReason] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [summary, setSummary] = useState({});

  useEffect(() => {
    loadDeviceBindingData();
  }, []);

  const loadDeviceBindingData = async () => {
    try {
      setLoading(true);
      setError(null);
      const token = getToken();
      const response = await fetch(getApiUrl('/device-binding/users'), {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (response.ok) {
        // Pastikan users selalu array, bahkan jika API mengembalikan null/undefined
        setUsers(Array.isArray(data.users) ? data.users : []);
        setSummary(data.summary || {});
      } else {
        throw new Error(data.message || 'Gagal memuat data');
      }
    } catch (error) {
      console.error('Error loading device binding data:', error);
      setError(error.message || 'Terjadi kesalahan saat memuat data device.');
      // Pastikan users tetap array kosong saat error
      setUsers([]);
      setSummary({});
    } finally {
      setLoading(false);
    }
  };

  const handleResetDevice = async () => {
    if (!selectedUser) return;
    try {
      const token = getToken();
      const response = await fetch(getApiUrl('/device-binding/reset'), {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          user_id: selectedUser.id,
          reason: resetReason
        })
      });

      const data = await response.json();
      if (response.ok && data.success) {
        alert('Device binding berhasil direset');
        loadDeviceBindingData(); // Reload data
        closeResetDialog(); // Close dialog
      } else {
        throw new Error(data.message || 'Gagal mereset device');
      }
    } catch (error) {
      console.error('Error resetting device:', error);
      alert(`Terjadi kesalahan saat mereset device: ${error.message}`);
    }
  };


  const openResetDialog = (user) => {
    setSelectedUser(user);
    setResetDialogOpen(true);
  };

  const closeResetDialog = () => {
    setResetDialogOpen(false);
    setSelectedUser(null);
    setResetReason('');
  };

  const getJenisPenggunaColor = (jenis) => {
    switch (jenis) {
      case 'Siswa': return 'success';
      case 'Honorer': return 'primary';
      default: return 'default';
    }
  };

  // Pastikan users adalah array sebelum menggunakan filter
  const filteredUsers = Array.isArray(users) ? users.filter(user =>
    user?.nama_lengkap?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user?.username?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user?.device_name?.toLowerCase().includes(searchTerm.toLowerCase())
  ) : [];

  if (loading) {
    return (
      <Box className="flex justify-center items-center p-8">
        <CircularProgress />
        <Typography className="ml-4">Memuat data device binding...</Typography>
      </Box>
    );
  }

  // Tampilkan error jika ada
  if (error) {
    return (
      <Box className="p-8">
        <Alert severity="error" className="mb-4">
          <Typography variant="h6">Terjadi Kesalahan</Typography>
          <Typography>{error}</Typography>
        </Alert>
        <Button
          variant="contained"
          startIcon={<Refresh />}
          onClick={loadDeviceBindingData}
        >
          Coba Lagi
        </Button>
      </Box>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <Smartphone className="w-6 h-6 text-blue-600" />
          <div>
            <Typography variant="h5" className="font-bold">
              Manajemen Perangkat Mobile
            </Typography>
            <Typography variant="body2" className="text-gray-600">
              Pantau perangkat aktif, versi SIAPS, dan binding siswa
            </Typography>
          </div>
        </div>
        <Button
          variant="outlined"
          startIcon={<Refresh />}
          onClick={loadDeviceBindingData}
        >
          Refresh
        </Button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card className="bg-blue-50 border-blue-200">
          <CardContent className="text-center">
            <Smartphone className="w-8 h-8 text-blue-600 mx-auto mb-2" />
            <Typography variant="h4" className="text-blue-600 font-bold">
              {summary.total_registered_devices || summary.total_bound_devices || 0}
            </Typography>
            <Typography variant="body2" className="text-blue-700">
              Total Device Tercatat
            </Typography>
          </CardContent>
        </Card>

        <Card className="bg-green-50 border-green-200">
          <CardContent className="text-center">
            <Smartphone className="w-8 h-8 text-green-600 mx-auto mb-2" />
            <Typography variant="h4" className="text-green-600 font-bold">
              {summary.locked_devices || 0}
            </Typography>
            <Typography variant="body2" className="text-green-700">
              Binding Siswa Terkunci
            </Typography>
          </CardContent>
        </Card>

        <Card className="bg-amber-50 border-amber-200">
          <CardContent className="text-center">
            <Smartphone className="w-8 h-8 text-amber-600 mx-auto mb-2" />
            <Typography variant="h4" className="text-amber-600 font-bold">
              {summary.tracking_only_devices || 0}
            </Typography>
            <Typography variant="body2" className="text-amber-700">
              Registrasi Non-Binding
            </Typography>
          </CardContent>
        </Card>
      </div>

      {/* Search and Filters */}
      <Card>
        <CardContent>
          <div className="flex items-center space-x-4">
            <TextField
              placeholder="Cari nama, username, atau device..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              variant="outlined"
              size="small"
              className="flex-1"
              InputProps={{
                startAdornment: <Search className="w-4 h-4 text-gray-400 mr-2" />
              }}
            />
            <Button
              variant="outlined"
              startIcon={<FilterList />}
              size="small"
            >
              Filter
            </Button>
            <Button
              variant="outlined"
              startIcon={<Download />}
              size="small"
            >
              Export
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Device Binding Table */}
      <Card>
        <CardHeader 
          title="Daftar Perangkat Mobile"
          subheader={`${filteredUsers?.length || 0} pengguna dengan perangkat tercatat`}
        />
        <CardContent>
          {!filteredUsers || filteredUsers.length === 0 ? (
            <Alert severity="info">
              Tidak ada pengguna dengan device binding yang ditemukan.
            </Alert>
          ) : (
            <TableContainer component={Paper} variant="outlined">
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Pengguna</TableCell>
                    <TableCell>Jenis Pengguna</TableCell>
                    <TableCell>Device</TableCell>
                    <TableCell>Tanggal Binding</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell align="center">Aksi</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {filteredUsers && filteredUsers.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell>
                        <div>
                          <Typography variant="subtitle2" className="font-medium">
                            {user.nama_lengkap}
                          </Typography>
                          <Typography variant="body2" className="text-gray-500">
                            @{user.username}
                          </Typography>
                          <Typography variant="body2" className="text-gray-500">
                            {user.email}
                          </Typography>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Chip 
                          label={user.jenis_pengguna || user.status_kepegawaian}
                          color={getJenisPenggunaColor(user.jenis_pengguna || user.status_kepegawaian)}
                          size="small"
                        />
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center space-x-2">
                          <Smartphone className="w-4 h-4 text-gray-400" />
                          <div>
                            <Typography variant="body2" className="font-medium">
                              {user.device_name || 'Unknown Device'}
                            </Typography>
                            <Typography variant="caption" className="text-gray-500">
                              ID: {user.device_id?.substring(0, 8)}...
                            </Typography>
                            <Typography variant="caption" className="block text-gray-500">
                              SIAPS: {user.app_version_label || '-'}
                            </Typography>
                            <Typography variant="caption" className="block text-gray-500">
                              Aktivitas: {user.last_device_activity ?
                                (formatServerDateTime(user.last_device_activity, 'id-ID', {
                                  day: '2-digit',
                                  month: 'short',
                                  year: 'numeric',
                                  hour: '2-digit',
                                  minute: '2-digit'
                                }) || '-') :
                                '-'
                              }
                            </Typography>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">
                          {user.device_bound_at ? 
                            (formatServerDateTime(user.device_bound_at, 'id-ID', {
                              day: '2-digit',
                              month: 'short',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            }) || '-') :
                            '-'
                          }
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Chip 
                          label={user.device_locked ? 'Binding Terkunci' : 'Tracking Only'}
                          color={user.device_locked ? 'success' : 'warning'}
                          size="small"
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Tooltip title="Reset Device Binding">
                          <IconButton
                            onClick={() => openResetDialog(user)}
                            color="error"
                            size="small"
                          >
                            <Delete className="w-4 h-4" />
                          </IconButton>
                        </Tooltip>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>

      {/* Reset Device Dialog */}
      <Dialog open={resetDialogOpen} onClose={closeResetDialog} maxWidth="sm" fullWidth>
        <DialogTitle>
          Reset Device Binding
        </DialogTitle>
        <DialogContent className="space-y-4">
          {selectedUser && (
            <Alert severity="warning" className="mb-4">
              Anda akan mereset device binding untuk <strong>{selectedUser.nama_lengkap}</strong>.
              Setelah direset, user dapat login di device baru.
            </Alert>
          )}

          <div className="bg-gray-50 p-3 rounded">
            <Typography variant="subtitle2" className="font-medium mb-2">
              Detail Device Saat Ini:
            </Typography>
            <Typography variant="body2">
              <strong>Device:</strong> {selectedUser?.device_name || 'Unknown'}
            </Typography>
            <Typography variant="body2">
              <strong>Versi App SIAPS:</strong> {selectedUser?.app_version_label || '-'}
            </Typography>
            <Typography variant="body2">
              <strong>Bound At:</strong> {selectedUser?.device_bound_at ? 
                (formatServerDateTime(selectedUser.device_bound_at, 'id-ID', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                }) || '-') :
                '-'
              }
            </Typography>
          </div>

          <TextField
            label="Alasan Reset (Opsional)"
            value={resetReason}
            onChange={(e) => setResetReason(e.target.value)}
            fullWidth
            multiline
            rows={3}
            placeholder="Contoh: HP rusak, ganti device, dll..."
            variant="outlined"
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={closeResetDialog} variant="outlined">
            Batal
          </Button>
          <Button 
            onClick={handleResetDevice} 
            variant="contained"
            color="error"
            startIcon={<Delete />}
          >
            Reset Device
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
};

export default DeviceManagement;

import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Box,
  Alert,
  AlertTitle,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Chip,
  Divider,
  useTheme,
  useMediaQuery,
  IconButton,
  Card,
  CardContent,
  Avatar,
  CircularProgress,
  Tooltip,
  Stack
} from '@mui/material';
import {
  History,
  X,
  Undo,
  ArrowUp,
  ArrowRight,
  GraduationCap,
  LogOut,
  UserCheck,
  Clock,
  AlertTriangle,
  CheckCircle,
  Calendar,
  School,
  User
} from 'lucide-react';
import { siswaExtendedAPI } from '../../services/siswaExtendedService';
import { formatServerDateTime } from '../../services/serverClock';
import { useServerClock } from '../../hooks/useServerClock';
import toast from 'react-hot-toast';

const RiwayatTransisiModal = ({
  open,
  onClose,
  siswa,
  onRefresh
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const { serverNowMs, timezone } = useServerClock();
  
  const [loading, setLoading] = useState(false);
  const [riwayatTransisi, setRiwayatTransisi] = useState([]);
  const [undoLoading, setUndoLoading] = useState(null);

  useEffect(() => {
    if (open && siswa?.id) {
      fetchRiwayatTransisi();
    }
  }, [open, siswa?.id]);

  const fetchRiwayatTransisi = async () => {
    try {
      setLoading(true);
      const response = await siswaExtendedAPI.getRiwayatTransisi(siswa.id);
      
      // Pastikan response.data adalah array
      let riwayat = [];
      if (response?.data?.data && Array.isArray(response.data.data)) {
        riwayat = response.data.data;
      } else if (Array.isArray(response?.data)) {
        riwayat = response.data;
      }
      
      setRiwayatTransisi(riwayat);
    } catch (error) {
      console.error('Error fetching riwayat transisi:', error);
      toast.error('Gagal memuat riwayat transisi');
      setRiwayatTransisi([]); // Set empty array on error
    } finally {
      setLoading(false);
    }
  };

  const getTransisiIcon = (type) => {
    switch (type) {
      case 'naik_kelas':
        return <ArrowUp className="text-blue-500" size={20} />;
      case 'pindah_kelas':
        return <ArrowRight className="text-orange-500" size={20} />;
      case 'lulus':
        return <GraduationCap className="text-green-500" size={20} />;
      case 'keluar':
        return <LogOut className="text-red-500" size={20} />;
      case 'aktif_kembali':
        return <UserCheck className="text-purple-500" size={20} />;
      default:
        return <Clock className="text-gray-500" size={20} />;
    }
  };

  const getTransisiLabel = (type) => {
    switch (type) {
      case 'naik_kelas':
        return 'Naik Kelas';
      case 'pindah_kelas':
        return 'Pindah Kelas';
      case 'lulus':
        return 'Lulus';
      case 'keluar':
        return 'Keluar Sekolah';
      case 'aktif_kembali':
        return 'Aktif Kembali';
      default:
        return 'Transisi';
    }
  };

  const getTransisiColor = (type) => {
    switch (type) {
      case 'naik_kelas':
        return 'primary';
      case 'pindah_kelas':
        return 'warning';
      case 'lulus':
        return 'success';
      case 'keluar':
        return 'error';
      case 'aktif_kembali':
        return 'secondary';
      default:
        return 'default';
    }
  };

  const canUndo = (transisi) => {
    // Hanya bisa undo transisi dalam 24 jam terakhir dan bukan yang sudah di-undo
    const transisiEpochMs = Date.parse(transisi?.tanggal_transisi || '');
    if (Number.isNaN(transisiEpochMs)) {
      return false;
    }

    const hoursDiff = (serverNowMs - transisiEpochMs) / (1000 * 60 * 60);
    
    return hoursDiff <= 24 && !transisi.is_undone && transisi.can_undo;
  };

  const handleUndo = async (transisi) => {
    if (!canUndo(transisi)) {
      toast.error('Transisi ini tidak dapat dibatalkan');
      return;
    }

    try {
      setUndoLoading(transisi.id);
      const serverNowLabel = formatServerDateTime(serverNowMs, 'id-ID') || '-';
      
      let response;
      switch (transisi.type) {
        case 'naik_kelas':
        case 'pindah_kelas':
          response = await siswaExtendedAPI.rollbackToKelas(siswa.id, {
            transisi_id: transisi.id,
            keterangan: `Rollback ${getTransisiLabel(transisi.type)} - ${serverNowLabel}`
          });
          break;
        case 'lulus':
          response = await siswaExtendedAPI.batalkanKelulusan(siswa.id, {
            transisi_id: transisi.id,
            keterangan: `Batalkan kelulusan - ${serverNowLabel}`
          });
          break;
        case 'keluar':
          response = await siswaExtendedAPI.kembalikanSiswa(siswa.id, {
            transisi_id: transisi.id,
            keterangan: `Kembalikan siswa - ${serverNowLabel}`
          });
          break;
        default:
          response = await siswaExtendedAPI.undoTransisi(siswa.id, transisi.id);
      }

      if (response.data.success) {
        toast.success(response.data.message || 'Transisi berhasil dibatalkan');
        await fetchRiwayatTransisi();
        if (onRefresh) {
          await Promise.resolve(onRefresh());
        }
      }
    } catch (error) {
      console.error('Error undoing transisi:', error);
      toast.error(error.response?.data?.message || 'Gagal membatalkan transisi');
    } finally {
      setUndoLoading(null);
    }
  };

  const formatDate = (dateString) => {
    return formatServerDateTime(dateString, 'id-ID', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }) || '-';
  };

  if (!siswa) return null;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      maxWidth="md"
      fullWidth
      fullScreen={isMobile}
      PaperProps={{
        sx: {
          borderRadius: isMobile ? 0 : 2,
          maxHeight: isMobile ? '100vh' : '90vh'
        }
      }}
    >
      <DialogTitle sx={{ pb: 1 }}>
        <Box display="flex" alignItems="center" justifyContent="space-between">
          <Box display="flex" alignItems="center" gap={1}>
            <History className="text-blue-500" size={24} />
            <Typography variant="h6" component="span">
              Riwayat Transisi Siswa
            </Typography>
          </Box>
          {!isMobile && (
            <IconButton onClick={onClose} size="small">
              <X size={20} />
            </IconButton>
          )}
        </Box>
      </DialogTitle>

      <DialogContent sx={{ pt: 2 }}>
        {/* Student Info */}
        <Card sx={{ mb: 3 }}>
          <CardContent>
            <Box display="flex" alignItems="center" gap={2}>
              <Avatar
                sx={{
                  bgcolor: theme.palette.primary.main,
                  width: 48,
                  height: 48
                }}
              >
                {siswa.nama?.charAt(0)?.toUpperCase() || 'S'}
              </Avatar>
              <Box flex={1}>
                <Typography variant="h6" fontWeight="medium">
                  {siswa.nama || 'Nama tidak tersedia'}
                </Typography>
                <Box display="flex" gap={2} mt={1}>
                  <Typography variant="body2" color="textSecondary">
                    NIS: {siswa.nis || '-'}
                  </Typography>
                  <Typography variant="body2" color="textSecondary">
                    NISN: {siswa.nisn || '-'}
                  </Typography>
                  <Chip
                    label={siswa.status || 'aktif'}
                    size="small"
                    color={siswa.status === 'aktif' ? 'success' : 'default'}
                  />
                </Box>
              </Box>
            </Box>
          </CardContent>
        </Card>

        {/* Info Alert */}
        <Alert severity="info" sx={{ mb: 3 }}>
          <AlertTitle>Informasi Rollback</AlertTitle>
          Anda dapat membatalkan transisi yang dilakukan dalam 24 jam terakhir. 
          Setelah dibatalkan, siswa akan dikembalikan ke status sebelumnya.
        </Alert>

        {/* Timeline */}
        {loading ? (
          <Box display="flex" justifyContent="center" alignItems="center" py={4}>
            <CircularProgress />
            <Typography variant="body2" sx={{ ml: 2 }}>
              Memuat riwayat transisi...
            </Typography>
          </Box>
        ) : riwayatTransisi.length === 0 ? (
          <Alert severity="info">
            <Typography variant="body2">
              Belum ada riwayat transisi untuk siswa ini
            </Typography>
          </Alert>
        ) : (
          <Stack spacing={2}>
            {riwayatTransisi.map((transisi, index) => (
              <Box key={transisi.id} display="flex" gap={2}>
                {/* Timeline Dot */}
                <Box display="flex" flexDirection="column" alignItems="center">
                  <Avatar
                    sx={{
                      bgcolor: `${getTransisiColor(transisi.type)}.main`,
                      width: 40,
                      height: 40,
                      mb: 1
                    }}
                  >
                    {getTransisiIcon(transisi.type)}
                  </Avatar>
                  {index < riwayatTransisi.length - 1 && (
                    <Box
                      sx={{
                        width: 2,
                        height: 40,
                        bgcolor: 'divider',
                        borderRadius: 1
                      }}
                    />
                  )}
                </Box>

                {/* Content */}
                <Card 
                  sx={{ 
                    flex: 1,
                    opacity: transisi.is_undone ? 0.6 : 1,
                    border: transisi.is_undone ? '1px dashed' : '1px solid',
                    borderColor: transisi.is_undone ? 'grey.400' : 'divider'
                  }}
                >
                  <CardContent sx={{ pb: '16px !important' }}>
                    <Box display="flex" justifyContent="space-between" alignItems="flex-start">
                      <Box flex={1}>
                        <Box display="flex" alignItems="center" gap={1} mb={1}>
                          <Typography variant="subtitle1" fontWeight="medium">
                            {getTransisiLabel(transisi.type)}
                          </Typography>
                          {transisi.is_undone && (
                            <Chip
                              label="Dibatalkan"
                              size="small"
                              color="error"
                              variant="outlined"
                            />
                          )}
                        </Box>
                        
                        <Box display="flex" alignItems="center" gap={1} mb={1}>
                          <Calendar size={14} className="text-gray-400" />
                          <Typography variant="body2" color="textSecondary">
                            {formatDate(transisi.tanggal_transisi)}
                          </Typography>
                        </Box>

                        {transisi.kelas_asal && (
                          <Box display="flex" alignItems="center" gap={1} mb={1}>
                            <School size={14} className="text-gray-400" />
                            <Typography variant="body2" color="textSecondary">
                              Dari: {transisi.kelas_asal.nama}
                            </Typography>
                          </Box>
                        )}

                        {transisi.kelas_tujuan && (
                          <Box display="flex" alignItems="center" gap={1} mb={1}>
                            <School size={14} className="text-gray-400" />
                            <Typography variant="body2" color="textSecondary">
                              Ke: {transisi.kelas_tujuan.nama}
                            </Typography>
                          </Box>
                        )}

                        {transisi.keterangan && (
                          <Typography variant="body2" color="textSecondary" sx={{ mt: 1 }}>
                            {transisi.keterangan}
                          </Typography>
                        )}

                        {transisi.processed_by && (
                          <Box display="flex" alignItems="center" gap={1} mt={1}>
                            <User size={14} className="text-gray-400" />
                            <Typography variant="caption" color="textSecondary">
                              Oleh: {transisi.processed_by.nama}
                            </Typography>
                          </Box>
                        )}
                      </Box>

                      {canUndo(transisi) && !transisi.is_undone && (
                        <Tooltip title="Batalkan Transisi">
                          <IconButton
                            size="small"
                            onClick={() => handleUndo(transisi)}
                            disabled={undoLoading === transisi.id}
                            sx={{ 
                              color: 'warning.main',
                              '&:hover': {
                                bgcolor: 'warning.light',
                                color: 'warning.dark'
                              }
                            }}
                          >
                            {undoLoading === transisi.id ? (
                              <CircularProgress size={16} />
                            ) : (
                              <Undo size={16} />
                            )}
                          </IconButton>
                        </Tooltip>
                      )}
                    </Box>
                  </CardContent>
                </Card>
              </Box>
            ))}
          </Stack>
        )}
      </DialogContent>

      <DialogActions sx={{ p: 2.5, gap: 1 }}>
        <Button
          onClick={onClose}
          variant="outlined"
          fullWidth={isMobile}
          startIcon={<X size={16} />}
        >
          Tutup
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default RiwayatTransisiModal;

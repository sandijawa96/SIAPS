import React, { useState, useMemo } from 'react';
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
  useTheme,
  useMediaQuery,
  IconButton,
  CircularProgress,
  Card,
  CardContent,
  Avatar,
  LinearProgress,
  Checkbox,
  FormControlLabel,
  Divider
} from '@mui/material';
import {
  Undo,
  X,
  AlertTriangle,
  CheckCircle,
  Clock,
  Users,
  ArrowUp,
  ArrowRight,
  GraduationCap,
  LogOut,
  UserCheck
} from 'lucide-react';
import useRollbackOperations from '../../hooks/useRollbackOperations';
import toast from 'react-hot-toast';
import { formatServerDateTime } from '../../services/serverClock';

const BulkRollbackModal = ({
  open,
  onClose,
  selectedTransisi = [],
  onSuccess
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  
  const [konfirmasi, setKonfirmasi] = useState(false);
  const [progress, setProgress] = useState({ current: 0, total: 0 });
  
  const {
    bulkRollbackLoading,
    bulkRollbackTransisi,
    validateRollback
  } = useRollbackOperations();

  // Filter transisi yang bisa di-rollback
  const validTransisi = useMemo(() => {
    return selectedTransisi.filter(item => {
      const validation = validateRollback(item.transisi);
      return validation.canUndo;
    });
  }, [selectedTransisi, validateRollback]);

  const invalidTransisi = useMemo(() => {
    return selectedTransisi.filter(item => {
      const validation = validateRollback(item.transisi);
      return !validation.canUndo;
    });
  }, [selectedTransisi, validateRollback]);

  const getTransisiIcon = (type) => {
    switch (type) {
      case 'naik_kelas':
        return <ArrowUp className="text-blue-500" size={16} />;
      case 'pindah_kelas':
        return <ArrowRight className="text-orange-500" size={16} />;
      case 'lulus':
        return <GraduationCap className="text-green-500" size={16} />;
      case 'keluar':
        return <LogOut className="text-red-500" size={16} />;
      case 'aktif_kembali':
        return <UserCheck className="text-purple-500" size={16} />;
      default:
        return <Clock className="text-gray-500" size={16} />;
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

  const handleBulkRollback = async () => {
    if (!konfirmasi) {
      toast.error('Harap centang konfirmasi untuk melanjutkan');
      return;
    }

    if (validTransisi.length === 0) {
      toast.error('Tidak ada transisi yang dapat dibatalkan');
      return;
    }

    try {
      const result = await bulkRollbackTransisi(validTransisi, {
        onProgress: (current, total, item) => {
          setProgress({ current, total });
        },
        showToast: false
      });

      if (result.successful > 0) {
        toast.success(`${result.successful} transisi berhasil dibatalkan`);
      }

      if (result.failed > 0) {
        toast.warning(`${result.failed} transisi gagal dibatalkan`);
      }

      if (onSuccess) onSuccess(result);
      onClose();
    } catch (error) {
      console.error('Error in bulk rollback:', error);
      toast.error('Gagal melakukan rollback massal');
    }
  };

  const formatDate = (dateString) => {
    return formatServerDateTime(dateString, 'id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }) || '-';
  };

  if (!open) return null;

  return (
    <Dialog
      open={open}
      onClose={bulkRollbackLoading ? undefined : onClose}
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
            <Undo className="text-orange-500" size={24} />
            <Typography variant="h6" component="span">
              Rollback Massal
            </Typography>
          </Box>
          {!isMobile && !bulkRollbackLoading && (
            <IconButton onClick={onClose} size="small">
              <X size={20} />
            </IconButton>
          )}
        </Box>
      </DialogTitle>

      <DialogContent sx={{ pt: 2 }}>
        {/* Progress Bar */}
        {bulkRollbackLoading && (
          <Box mb={3}>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={1}>
              <Typography variant="body2" color="textSecondary">
                Memproses rollback...
              </Typography>
              <Typography variant="body2" fontWeight="medium">
                {progress.current}/{progress.total}
              </Typography>
            </Box>
            <LinearProgress
              variant="determinate"
              value={(progress.current / progress.total) * 100}
              sx={{ height: 8, borderRadius: 4 }}
            />
          </Box>
        )}

        {/* Summary */}
        <Box mb={3}>
          <Typography variant="h6" gutterBottom>
            Ringkasan Rollback
          </Typography>
          
          <Box display="flex" gap={2} mb={2}>
            <Chip
              icon={<CheckCircle size={16} />}
              label={`${validTransisi.length} Dapat Dibatalkan`}
              color="success"
              variant="outlined"
            />
            <Chip
              icon={<AlertTriangle size={16} />}
              label={`${invalidTransisi.length} Tidak Dapat Dibatalkan`}
              color="error"
              variant="outlined"
            />
            <Chip
              icon={<Users size={16} />}
              label={`${selectedTransisi.length} Total`}
              color="primary"
              variant="outlined"
            />
          </Box>
        </Box>

        {/* Valid Transisi */}
        {validTransisi.length > 0 && (
          <Box mb={3}>
            <Typography variant="subtitle1" fontWeight="medium" gutterBottom>
              Transisi yang Akan Dibatalkan ({validTransisi.length})
            </Typography>
            <Card sx={{ maxHeight: 300, overflow: 'auto' }}>
              <List dense>
                {validTransisi.map((item, index) => (
                  <ListItem key={`${item.siswa.id}-${item.transisi.id}`} divider={index < validTransisi.length - 1}>
                    <ListItemIcon>
                      {getTransisiIcon(item.transisi.type)}
                    </ListItemIcon>
                    <ListItemText
                      primary={
                        <Box display="flex" alignItems="center" gap={1}>
                          <Typography variant="body2" fontWeight="medium">
                            {item.siswa.nama}
                          </Typography>
                          <Chip
                            label={getTransisiLabel(item.transisi.type)}
                            size="small"
                            color="primary"
                            variant="outlined"
                          />
                        </Box>
                      }
                      secondary={
                        <Typography variant="caption" color="textSecondary">
                          {formatDate(item.transisi.tanggal_transisi)} • NIS: {item.siswa.nis}
                        </Typography>
                      }
                    />
                  </ListItem>
                ))}
              </List>
            </Card>
          </Box>
        )}

        {/* Invalid Transisi */}
        {invalidTransisi.length > 0 && (
          <Box mb={3}>
            <Typography variant="subtitle1" fontWeight="medium" gutterBottom>
              Transisi yang Tidak Dapat Dibatalkan ({invalidTransisi.length})
            </Typography>
            <Card sx={{ maxHeight: 200, overflow: 'auto' }}>
              <List dense>
                {invalidTransisi.map((item, index) => {
                  const validation = validateRollback(item.transisi);
                  return (
                    <ListItem key={`invalid-${item.siswa.id}-${item.transisi.id}`} divider={index < invalidTransisi.length - 1}>
                      <ListItemIcon>
                        <AlertTriangle className="text-red-500" size={16} />
                      </ListItemIcon>
                      <ListItemText
                        primary={
                          <Box display="flex" alignItems="center" gap={1}>
                            <Typography variant="body2" fontWeight="medium">
                              {item.siswa.nama}
                            </Typography>
                            <Chip
                              label={getTransisiLabel(item.transisi.type)}
                              size="small"
                              color="error"
                              variant="outlined"
                            />
                          </Box>
                        }
                        secondary={
                          <Box>
                            <Typography variant="caption" color="textSecondary">
                              {formatDate(item.transisi.tanggal_transisi)} • NIS: {item.siswa.nis}
                            </Typography>
                            <Typography variant="caption" color="error" display="block">
                              Alasan: {validation.reasons.join(', ')}
                            </Typography>
                          </Box>
                        }
                      />
                    </ListItem>
                  );
                })}
              </List>
            </Card>
          </Box>
        )}

        {/* Warning Alert */}
        <Alert severity="warning" sx={{ mb: 3 }}>
          <AlertTitle>Peringatan Rollback</AlertTitle>
          <Typography variant="body2" gutterBottom>
            Rollback akan mengembalikan siswa ke status sebelum transisi dilakukan. 
            Tindakan ini tidak dapat dibatalkan.
          </Typography>
          <Typography variant="body2">
            • Siswa yang naik/pindah kelas akan dikembalikan ke kelas sebelumnya<br/>
            • Siswa yang lulus akan dikembalikan ke status aktif<br/>
            • Siswa yang keluar akan dikembalikan ke status aktif
          </Typography>
        </Alert>

        {/* Confirmation */}
        {validTransisi.length > 0 && (
          <FormControlLabel
            control={
              <Checkbox
                checked={konfirmasi}
                onChange={(e) => setKonfirmasi(e.target.checked)}
                color="warning"
                disabled={bulkRollbackLoading}
              />
            }
            label={
              <Typography variant="body2">
                Saya memahami konsekuensi rollback dan ingin melanjutkan untuk {validTransisi.length} transisi
              </Typography>
            }
          />
        )}
      </DialogContent>

      <DialogActions sx={{ p: 2.5, gap: 1 }}>
        <Button
          onClick={onClose}
          disabled={bulkRollbackLoading}
          variant="outlined"
          startIcon={<X size={16} />}
          fullWidth={isMobile}
        >
          Batal
        </Button>
        
        {validTransisi.length > 0 && (
          <Button
            onClick={handleBulkRollback}
            disabled={bulkRollbackLoading || !konfirmasi}
            variant="contained"
            color="warning"
            startIcon={bulkRollbackLoading ? <CircularProgress size={16} /> : <Undo size={16} />}
            fullWidth={isMobile}
          >
            {bulkRollbackLoading ? 'Memproses...' : `Rollback ${validTransisi.length} Transisi`}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default BulkRollbackModal;

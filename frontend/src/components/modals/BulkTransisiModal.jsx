import React, { useEffect, useMemo, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Box,
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Alert,
  useTheme,
  useMediaQuery,
  IconButton,
  CircularProgress,
  Card,
  CardContent,
  Avatar,
  Grid,
  Chip,
  LinearProgress,
  Divider,
  List,
  ListItem,
  ListItemAvatar,
  ListItemText,
  Checkbox,
  FormControlLabel
} from '@mui/material';
import {
  Users,
  ArrowUp,
  GraduationCap,
  X,
  Calendar,
  School,
  CheckCircle,
  AlertCircle,
  TrendingUp,
  AlertTriangle
} from 'lucide-react';
import { siswaExtendedAPI } from '../../services/siswaExtendedService';
import { getServerDateString } from '../../services/serverClock';
import toast from 'react-hot-toast';
import useErrorHandling from '../../hooks/useErrorHandling';
import ErrorHandlingModal from './ErrorHandlingModal';
import { useAuth } from '../../hooks/useAuth';
import useServerClock from '../../hooks/useServerClock';

const BulkTransisiModal = ({
  open,
  onClose,
  selectedSiswa = [],
  mode = 'naik-kelas', // naik-kelas, lulus
  kelasList = [],
  tingkatList = [],
  tahunAjaranList = [],
  onSuccess
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const { hasRole, hasAnyRole } = useAuth();
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const isSafeServerDate = (value) => {
    const match = String(value || '').trim().match(/^(\d{4})-\d{2}-\d{2}$/);
    if (!match) {
      return false;
    }

    const year = Number(match[1]);
    return year >= 2000 && year <= 2050;
  };
  const resolveSafeToday = () => {
    const candidate = String((isServerClockSynced ? serverDate : getServerDateString()) || '').trim();
    if (isSafeServerDate(candidate)) {
      return candidate;
    }

    return '';
  };
  
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    kelas_id: '',
    tahun_ajaran_id: '',
    tanggal: resolveSafeToday(),
    keterangan: '',
    konfirmasi: false
  });

  useEffect(() => {
    if (!open || !isServerClockSynced || !serverDate) {
      return;
    }

    setFormData((current) => ({
      ...current,
      tanggal: current.tanggal || serverDate,
    }));
  }, [isServerClockSynced, open, serverDate]);

  // Error handling
  const {
    errors,
    hasErrors,
    isRetrying,
    addError,
    clearErrors,
    retryFailedOperations
  } = useErrorHandling();

  const [showErrorModal, setShowErrorModal] = useState(false);
  const [operationResults, setOperationResults] = useState({
    successful: 0,
    failed: 0,
    total: 0
  });

  const isWaliKelas = hasRole('Wali Kelas');
  const isTransferApprover = hasAnyRole(['Super Admin', 'Admin', 'Wakasek Kurikulum']);
  const useWaliOperationalFlow = isWaliKelas && !isTransferApprover;

  const tingkatUrutanMap = useMemo(() => {
    const map = new Map();
    (Array.isArray(tingkatList) ? tingkatList : []).forEach((item) => {
      const id = Number(item?.id);
      const urutan = Number(item?.urutan);
      if (Number.isFinite(id) && Number.isFinite(urutan)) {
        map.set(id, urutan);
      }
    });
    return map;
  }, [tingkatList]);

  const getTingkatRank = (kelasItem) => {
    const directUrutan = Number(
      kelasItem?.tingkat?.urutan ??
      kelasItem?.tingkat_urutan ??
      kelasItem?.tingkatUrutan
    );
    if (Number.isFinite(directUrutan)) {
      return directUrutan;
    }

    const tingkatId = Number(
      kelasItem?.tingkat?.id ??
      kelasItem?.tingkat_id
    );
    if (Number.isFinite(tingkatId) && tingkatUrutanMap.has(tingkatId)) {
      return tingkatUrutanMap.get(tingkatId);
    }

    // Fallback for backward compatibility if rank is not available yet.
    return tingkatId || 0;
  };

  const resolveKelasTahunAjaranId = (kelasItem) => {
    const rawId = Number(
      kelasItem?.tahun_ajaran_id ??
      kelasItem?.tahunAjaranId ??
      kelasItem?.tahun_ajaran?.id ??
      kelasItem?.tahunAjaran?.id ??
      0
    );
    return Number.isFinite(rawId) && rawId > 0 ? rawId : null;
  };

  const resolveTahunAjaranSortKey = (tahunAjaranItem, fallbackId = 0) => {
    const rawDate = tahunAjaranItem?.tanggal_mulai
      ?? tahunAjaranItem?.tanggalMulai
      ?? tahunAjaranItem?.start_date
      ?? tahunAjaranItem?.startDate
      ?? null;

    if (rawDate) {
      const timestamp = Date.parse(String(rawDate));
      if (Number.isFinite(timestamp)) {
        return timestamp;
      }
    }

    const numericOrder = Number(tahunAjaranItem?.urutan);
    if (Number.isFinite(numericOrder) && numericOrder > 0) {
      return numericOrder;
    }

    return Number(fallbackId) || 0;
  };

  const currentTahunAjaranId = useMemo(() => {
    const ids = (Array.isArray(selectedSiswa) ? selectedSiswa : [])
      .map((item) => resolveKelasTahunAjaranId(item?.kelas || item))
      .filter((id) => Number.isFinite(id) && id > 0);

    if (ids.length === 0) {
      return null;
    }

    return Number(ids[0]);
  }, [selectedSiswa]);

  const availableTahunAjaranList = useMemo(() => {
    const source = Array.isArray(tahunAjaranList) ? tahunAjaranList : [];
    if (mode !== 'naik-kelas') {
      return source;
    }

    const currentId = Number(currentTahunAjaranId || 0);
    if (!currentId) {
      return source;
    }

    const currentYear = source.find((item) => Number(item?.id) === currentId) || null;
    const currentSortKey = resolveTahunAjaranSortKey(currentYear, currentId);

    const filtered = source.filter((item) => {
      const targetId = Number(item?.id || 0);
      if (!targetId || targetId === currentId) {
        return false;
      }

      const targetSortKey = resolveTahunAjaranSortKey(item, targetId);
      return targetSortKey > currentSortKey;
    });

    return filtered.length > 0 ? filtered : source.filter((item) => Number(item?.id) !== currentId);
  }, [mode, tahunAjaranList, currentTahunAjaranId]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setFormData((prev) => {
      const currentDate = String(prev.tanggal || '').trim();
      const isValidDate = isSafeServerDate(currentDate);
      if (isValidDate) {
        return prev;
      }

      return {
        ...prev,
        tanggal: resolveSafeToday(),
      };
    });
  }, [open]);

  const getTitle = () => {
    switch (mode) {
      case 'naik-kelas':
        return 'Naik Kelas Massal';
      case 'lulus':
        return 'Kelulusan Massal';
      default:
        return 'Transisi Massal';
    }
  };

  const getIcon = () => {
    switch (mode) {
      case 'naik-kelas':
        return <ArrowUp className="text-blue-500" size={24} />;
      case 'lulus':
        return <GraduationCap className="text-green-500" size={24} />;
      default:
        return <Users className="text-gray-500" size={24} />;
    }
  };

  const selectedTargetTahunAjaranId = Number(formData.tahun_ajaran_id || 0);

  // Filter kelas untuk naik kelas (hanya tingkat lebih tinggi + tahun ajaran target yang dipilih)
  const filteredKelasList = useMemo(() => {
    if (mode !== 'naik-kelas' || selectedSiswa.length === 0) return kelasList;
    
    // Ambil tingkat tertinggi dari siswa yang dipilih
    const currentRanks = selectedSiswa.map((siswa) =>
      getTingkatRank(siswa.kelas || siswa)
    );
    const maxRank = Math.max(...currentRanks);
    
    return kelasList.filter((kelas) => {
      if (getTingkatRank(kelas) <= maxRank) {
        return false;
      }

      if (!selectedTargetTahunAjaranId) {
        return false;
      }

      return Number(resolveKelasTahunAjaranId(kelas) || 0) === selectedTargetTahunAjaranId;
    });
  }, [mode, selectedSiswa, kelasList, getTingkatRank, selectedTargetTahunAjaranId]);

  useEffect(() => {
    if (!open || mode !== 'naik-kelas') {
      return;
    }

    setFormData((prev) => {
      const selectedYearId = Number(prev.tahun_ajaran_id || 0);
      const hasSelectedYear = availableTahunAjaranList.some(
        (item) => Number(item?.id || 0) === selectedYearId
      );

      if (!selectedYearId || hasSelectedYear) {
        return prev;
      }

      return {
        ...prev,
        tahun_ajaran_id: '',
        kelas_id: '',
      };
    });
  }, [open, mode, availableTahunAjaranList]);

  useEffect(() => {
    if (mode !== 'naik-kelas') {
      return;
    }

    setFormData((prev) => {
      const selectedKelasId = Number(prev.kelas_id || 0);
      if (!selectedKelasId) {
        return prev;
      }

      const kelasMasihValid = filteredKelasList.some(
        (item) => Number(item?.id || 0) === selectedKelasId
      );

      if (kelasMasihValid) {
        return prev;
      }

      return {
        ...prev,
        kelas_id: '',
      };
    });
  }, [mode, filteredKelasList]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!formData.konfirmasi) {
      toast.error('Harap centang konfirmasi untuk melanjutkan');
      return;
    }

    try {
      setLoading(true);
      clearErrors(); // Reset error state
      
      const results = await Promise.allSettled(
        selectedSiswa.map(async (siswa) => {
          try {
            const data = {
              tanggal: formData.tanggal,
              keterangan: formData.keterangan
            };

            if (mode === 'naik-kelas') {
              if (!formData.kelas_id || !formData.tahun_ajaran_id) {
                throw new Error('Kelas tujuan dan tahun ajaran harus dipilih');
              }
              if (useWaliOperationalFlow) {
                data.kelas_id = formData.kelas_id;
                data.tahun_ajaran_id = formData.tahun_ajaran_id;
                await siswaExtendedAPI.naikKelasWali(siswa.id, data);
              } else {
                data.kelas_id = formData.kelas_id;
                data.tahun_ajaran_id = formData.tahun_ajaran_id;
                data.tanggal = formData.tanggal;
                await siswaExtendedAPI.naikKelas(siswa.id, data);
              }
            } else if (mode === 'lulus') {
              data.tanggal_lulus = formData.tanggal; // Fix: backend expects tanggal_lulus
              await siswaExtendedAPI.lulusSiswa(siswa.id, data);
            }

            return { success: true, siswa };
          } catch (error) {
            // Add error to error handling system
            addError(error, siswa, mode);
            return { success: false, siswa, error };
          }
        })
      );

      const successful = results.filter(result => 
        result.status === 'fulfilled' && result.value.success
      ).length;

      const failed = results.filter(result => 
        result.status === 'rejected' || (result.status === 'fulfilled' && !result.value.success)
      ).length;

      setOperationResults({
        successful,
        failed,
        total: selectedSiswa.length
      });

      if (successful > 0) {
        toast.success(`${successful} siswa berhasil diproses`);
      }

      if (failed > 0) {
        setShowErrorModal(true);
      } else {
        if (onSuccess) onSuccess();
        onClose();
      }
    } catch (error) {
      console.error('Error in bulk transisi:', error);
      addError(error, null, mode);
      setShowErrorModal(true);
    } finally {
      setLoading(false);
    }
  };

  const handleRetry = async () => {
    const failedSiswa = selectedSiswa.filter(siswa => 
      errors.some(error => error.siswa?.id === siswa.id)
    );

    if (failedSiswa.length === 0) return;

    const retryFunction = async (operation) => {
      const data = {
        tanggal: formData.tanggal,
        keterangan: formData.keterangan
      };

      if (mode === 'naik-kelas') {
        if (useWaliOperationalFlow) {
          data.kelas_id = formData.kelas_id;
          data.tahun_ajaran_id = formData.tahun_ajaran_id;
          return await siswaExtendedAPI.naikKelasWali(operation.siswa.id, data);
        }

        data.kelas_id = formData.kelas_id;
        data.tahun_ajaran_id = formData.tahun_ajaran_id;
        data.tanggal = formData.tanggal;
        return await siswaExtendedAPI.naikKelas(operation.siswa.id, data);
      } else if (mode === 'lulus') {
        data.tanggal_lulus = formData.tanggal;
        return await siswaExtendedAPI.lulusSiswa(operation.siswa.id, data);
      }
    };

    await retryFailedOperations(retryFunction, failedSiswa.map(siswa => ({
      siswa,
      context: mode,
      id: siswa.id // Add explicit id for tracking
    })));

    if (!hasErrors) {
      setShowErrorModal(false);
      if (onSuccess) onSuccess();
      onClose();
    }
  };

  const getInitials = (name) => {
    if (!name) return 'S';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  if (!open) return null;

  return (
    <Dialog
      open={open}
      onClose={loading ? undefined : onClose}
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
            {getIcon()}
            <Typography variant="h6" component="span">
              {getTitle()}
            </Typography>
          </Box>
          {!isMobile && !loading && (
            <IconButton onClick={onClose} size="small">
              <X size={20} />
            </IconButton>
          )}
        </Box>
      </DialogTitle>

      <form onSubmit={handleSubmit}>
        <DialogContent sx={{ pt: 2 }}>
          {/* Selected Students Info */}
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Box display="flex" alignItems="center" gap={1} mb={2}>
                <Users className="text-blue-500" size={20} />
                <Typography variant="h6" fontWeight="medium">
                  Siswa Terpilih ({selectedSiswa.length})
                </Typography>
              </Box>
              
              <Box sx={{ maxHeight: 200, overflow: 'auto' }}>
                <List dense>
                  {selectedSiswa.map((siswa, index) => (
                    <ListItem key={siswa.id} divider={index < selectedSiswa.length - 1}>
                      <ListItemAvatar>
                        <Avatar
                          sx={{
                            bgcolor: theme.palette.primary.main,
                            width: 32,
                            height: 32,
                            fontSize: '0.8rem'
                          }}
                        >
                          {getInitials(siswa.nama)}
                        </Avatar>
                      </ListItemAvatar>
                      <ListItemText
                        primary={
                          <Typography variant="body2" fontWeight="medium">
                            {siswa.nama || siswa.nama_lengkap || 'Nama tidak tersedia'}
                          </Typography>
                        }
                        secondary={
                          <div style={{ display: 'flex', alignItems: 'center', gap: '4px', marginTop: '4px' }}>
                            <Typography variant="caption" color="textSecondary" component="span">
                              NIS: {siswa.nis || '-'}
                            </Typography>
                            <Chip
                              label={siswa.kelas?.nama || siswa.kelas_aktif?.nama_kelas || 'Kelas tidak diketahui'}
                              size="small"
                              color="primary"
                              variant="outlined"
                              sx={{ fontSize: '0.7rem' }}
                            />
                          </div>
                        }
                        secondaryTypographyProps={{
                          component: 'div'
                        }}
                      />
                    </ListItem>
                  ))}
                </List>
              </Box>
            </CardContent>
          </Card>

          <Box display="flex" flexDirection="column" gap={3}>
            {/* Form Fields */}
            {mode === 'naik-kelas' && (
              <>
                <FormControl fullWidth required>
                  <InputLabel>Tahun Ajaran</InputLabel>
                  <Select
                    value={formData.tahun_ajaran_id}
                    onChange={(e) => setFormData((prev) => ({
                      ...prev,
                      tahun_ajaran_id: e.target.value,
                      kelas_id: '',
                    }))}
                    label="Tahun Ajaran"
                    disabled={loading}
                  >
                    {availableTahunAjaranList.map((ta) => (
                      <MenuItem key={ta.id} value={ta.id}>
                        {ta.nama}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>

                <FormControl fullWidth required>
                  <InputLabel>Kelas Tujuan</InputLabel>
                  <Select
                    value={formData.kelas_id}
                    onChange={(e) => setFormData(prev => ({ ...prev, kelas_id: e.target.value }))}
                    label="Kelas Tujuan"
                    disabled={loading || !formData.tahun_ajaran_id}
                    startAdornment={
                      <School className="ml-2 mr-1 text-gray-400" size={20} />
                    }
                  >
                    {filteredKelasList.map((kelas) => (
                      <MenuItem key={kelas.id} value={kelas.id}>
                          <div style={{ width: '100%' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' }}>
                              <Typography variant="body2" component="span" fontWeight="medium">
                                {kelas.namaKelas || kelas.nama_kelas || kelas.nama}
                              </Typography>
                              <Chip
                                label={`${Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0)}/${Number(kelas.kapasitas ?? 0)}`}
                                color={Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0) >= Number(kelas.kapasitas ?? 0) ? 'error' : 'success'}
                                size="small"
                                sx={{ ml: 1 }}
                              />
                            </div>
                            <div style={{ marginTop: '8px', width: '100%' }}>
                              <Typography variant="caption" component="div" color="textSecondary" gutterBottom>
                                Tingkat: {kelas.tingkat?.nama || kelas.tingkat || 'N/A'} | Wali Kelas: {kelas.waliKelas || 'Belum ditentukan'}
                              </Typography>
                              <div style={{ width: '100%', marginTop: '4px' }}>
                                <LinearProgress
                                  variant="determinate"
                                  value={(() => {
                                    const kapasitas = Number(kelas.kapasitas ?? 0);
                                    const jumlah = Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0);
                                    if (!kapasitas || kapasitas <= 0) {
                                      return 0;
                                    }
                                    return (jumlah / kapasitas) * 100;
                                  })()}
                                  color={Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0) >= Number(kelas.kapasitas ?? 0) ? 'error' : 'success'}
                                  sx={{ height: 4, borderRadius: 2 }}
                                />
                              </div>
                            </div>
                          </div>
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
              </>
            )}

            <TextField
              label={`Tanggal ${mode === 'naik-kelas' ? 'Naik Kelas' : 'Kelulusan'}`}
              type="date"
              value={formData.tanggal}
              onChange={(e) => setFormData(prev => ({ ...prev, tanggal: e.target.value }))}
              fullWidth
              required
              disabled={loading}
              InputProps={{
                startAdornment: (
                  <Calendar className="ml-2 mr-1 text-gray-400" size={20} />
                )
              }}
              InputLabelProps={{
                shrink: true
              }}
            />

            <TextField
              label="Keterangan"
              multiline
              rows={3}
              value={formData.keterangan}
              onChange={(e) => setFormData(prev => ({ ...prev, keterangan: e.target.value }))}
              fullWidth
              disabled={loading}
              placeholder="Masukkan keterangan tambahan (opsional)..."
            />

            {/* Confirmation */}
            <Card sx={{ bgcolor: 'warning.light', color: 'warning.contrastText' }}>
              <CardContent>
                <Box display="flex" alignItems="flex-start" gap={2}>
                  <AlertCircle className="text-orange-600 mt-1" size={20} />
                  <Box>
                    <Typography variant="subtitle2" fontWeight="bold" gutterBottom>
                      Konfirmasi Transisi Massal
                    </Typography>
                    <Typography variant="body2" sx={{ mb: 2 }}>
                      {mode === 'naik-kelas' 
                        ? `Anda akan memindahkan ${selectedSiswa.length} siswa ke kelas yang lebih tinggi. Tindakan ini tidak dapat dibatalkan.`
                        : `Anda akan mengubah status ${selectedSiswa.length} siswa menjadi lulus. Tindakan ini tidak dapat dibatalkan.`
                      }
                    </Typography>
                    <FormControlLabel
                      control={
                        <Checkbox
                          checked={formData.konfirmasi}
                          onChange={(e) => setFormData(prev => ({ ...prev, konfirmasi: e.target.checked }))}
                          color="warning"
                        />
                      }
                      label="Saya memahami dan ingin melanjutkan"
                    />
                  </Box>
                </Box>
              </CardContent>
            </Card>

            {/* Info Alert */}
            <Alert severity="info" sx={{ borderRadius: 1 }}>
              <Box display="flex" alignItems="center" gap={1}>
                <TrendingUp size={16} />
                <Typography variant="body2">
                  {mode === 'naik-kelas' 
                    ? (
                      useWaliOperationalFlow
                        ? 'Semua siswa akan diproses naik kelas melalui endpoint wali kelas (wajib window on/off aktif).'
                        : 'Semua siswa akan dipindahkan ke kelas tujuan yang sama dengan tahun ajaran yang dipilih'
                    )
                    : 'Semua siswa akan diubah statusnya menjadi lulus pada tanggal yang ditentukan'
                  }
                </Typography>
              </Box>
            </Alert>
          </Box>
        </DialogContent>

        <DialogActions sx={{ p: 2.5, gap: 1 }}>
          <Button
            onClick={onClose}
            disabled={loading}
            variant="outlined"
            startIcon={<X size={16} />}
            fullWidth={isMobile}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="contained"
            disabled={loading || !formData.konfirmasi}
            startIcon={loading ? <CircularProgress size={16} /> : <CheckCircle size={16} />}
            fullWidth={isMobile}
            color={mode === 'naik-kelas' ? 'primary' : 'success'}
          >
            {loading ? 'Memproses...' : `Proses ${selectedSiswa.length} Siswa`}
          </Button>
        </DialogActions>
      </form>

      {/* Error Handling Modal */}
      <ErrorHandlingModal
        open={showErrorModal}
        onClose={() => setShowErrorModal(false)}
        errors={errors}
        successCount={operationResults.successful}
        totalCount={operationResults.total}
        operationType={mode}
        onRetry={handleRetry}
        retrying={isRetrying}
      />
    </Dialog>
  );
};

export default BulkTransisiModal;

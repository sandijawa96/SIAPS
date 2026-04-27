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
  LinearProgress
} from '@mui/material';
import {
  ArrowUp,
  ArrowRight,
  GraduationCap,
  LogOut,
  X,
  Calendar,
  School
} from 'lucide-react';
import siswaExtendedAPI from '../../services/siswaExtendedService';
import { formatServerDateTime, getServerDateString } from '../../services/serverClock';
import toast from 'react-hot-toast';
import { useAuth } from '../../hooks/useAuth';
import useServerClock from '../../hooks/useServerClock';

const TransisiSiswaModal = ({
  open,
  onClose,
  siswa,
  currentKelas,
  type,
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
    keterangan: ''
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

  const isWaliKelas = hasRole('Wali Kelas');
  const isTransferApprover = hasAnyRole(['Super Admin', 'Admin', 'Wakasek Kurikulum']);
  const useWaliOperationalFlow = isWaliKelas && !isTransferApprover;

  const currentTahunAjaranId = useMemo(() => {
    const rawId = Number(
      currentKelas?.tahun_ajaran_id ??
      currentKelas?.tahunAjaran?.id ??
      siswa?.kelas?.tahun_ajaran_id ??
      0
    );
    return Number.isFinite(rawId) && rawId > 0 ? rawId : null;
  }, [currentKelas, siswa]);

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

  const availableTahunAjaranList = useMemo(() => {
    const source = Array.isArray(tahunAjaranList) ? tahunAjaranList : [];
    if (type === 'pindah-kelas') {
      if (!currentTahunAjaranId) {
        return [];
      }

      const filtered = source.filter((item) => Number(item?.id) === Number(currentTahunAjaranId));
      if (filtered.length > 0) {
        return filtered;
      }

      return [{
        id: currentTahunAjaranId,
        nama: currentKelas?.tahunAjaran || `Tahun Ajaran #${currentTahunAjaranId}`,
      }];
    }

    if (type === 'naik-kelas') {
      if (!currentTahunAjaranId) {
        return source;
      }

      const currentYear = source.find((item) => Number(item?.id) === Number(currentTahunAjaranId)) || null;
      const currentSortKey = resolveTahunAjaranSortKey(currentYear, currentTahunAjaranId);

      const filtered = source.filter((item) => {
        const targetId = Number(item?.id || 0);
        if (!targetId || targetId === Number(currentTahunAjaranId)) {
          return false;
        }

        const targetSortKey = resolveTahunAjaranSortKey(item, targetId);
        return targetSortKey > currentSortKey;
      });

      return filtered.length > 0 ? filtered : source.filter((item) => Number(item?.id) !== Number(currentTahunAjaranId));
    }

    return source;
  }, [tahunAjaranList, type, currentTahunAjaranId, currentKelas]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setFormData((prev) => {
      const next = { ...prev };
      let changed = false;

      const currentDate = String(prev.tanggal || '').trim();
      const isValidDate = isSafeServerDate(currentDate);
      if (!isValidDate) {
        next.tanggal = resolveSafeToday();
        changed = true;
      }

      if (type === 'pindah-kelas' && currentTahunAjaranId && Number(prev.tahun_ajaran_id || 0) !== Number(currentTahunAjaranId)) {
        next.tahun_ajaran_id = currentTahunAjaranId;
        changed = true;
      }

      return changed ? next : prev;
    });
  }, [open, type, currentTahunAjaranId]);

  const operationModeLabel = useMemo(() => {
    if (type === 'pindah-kelas') {
      return useWaliOperationalFlow ? 'Request + Approval Kurikulum' : 'Eksekusi Langsung';
    }

    if (type === 'naik-kelas') {
      return useWaliOperationalFlow ? 'Wali Kelas (Window On/Off)' : 'Eksekusi Langsung';
    }

    return null;
  }, [type, useWaliOperationalFlow]);

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

  const buildOperationPayload = () => {
    switch (type) {
      case 'naik-kelas':
        if (useWaliOperationalFlow) {
          return {
            kelas_id: Number(formData.kelas_id),
            tahun_ajaran_id: Number(formData.tahun_ajaran_id),
            tanggal: formData.tanggal,
            keterangan: formData.keterangan || null,
          };
        }

        return {
          kelas_id: Number(formData.kelas_id),
          tahun_ajaran_id: Number(formData.tahun_ajaran_id),
          tanggal: formData.tanggal,
          keterangan: formData.keterangan || null,
        };
      case 'pindah-kelas':
        if (useWaliOperationalFlow) {
          return {
            kelas_id: Number(formData.kelas_id),
            tahun_ajaran_id: Number(formData.tahun_ajaran_id),
            tanggal: formData.tanggal,
            keterangan: formData.keterangan || null,
          };
        }

        return {
          kelas_id: Number(formData.kelas_id),
          tahun_ajaran_id: Number(formData.tahun_ajaran_id),
          tanggal: formData.tanggal,
          keterangan: formData.keterangan || null,
        };
      case 'lulus':
        return {
          tanggal_lulus: formData.tanggal,
          keterangan: formData.keterangan || null,
        };
      case 'keluar':
        return {
          tanggal_keluar: formData.tanggal,
          alasan_keluar: formData.keterangan || 'Keluar sekolah',
        };
      default:
        return {};
    }
  };

  const getTitle = () => {
    switch (type) {
      case 'naik-kelas':
        return 'Naik Kelas';
      case 'pindah-kelas':
        return 'Pindah Kelas';
      case 'lulus':
        return 'Kelulusan';
      case 'keluar':
        return 'Keluar Sekolah';
      default:
        return 'Transisi Siswa';
    }
  };

  const getIcon = () => {
    switch (type) {
      case 'naik-kelas':
        return <ArrowUp className="text-blue-500" />;
      case 'pindah-kelas':
        return <ArrowRight className="text-orange-500" />;
      case 'lulus':
        return <GraduationCap className="text-green-500" />;
      case 'keluar':
        return <LogOut className="text-red-500" />;
      default:
        return null;
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!siswa?.id) return;

    try {
      setLoading(true);
      let response;
      const payload = buildOperationPayload();

      switch (type) {
        case 'naik-kelas':
          response = useWaliOperationalFlow
            ? await siswaExtendedAPI.naikKelasWali(siswa.id, payload)
            : await siswaExtendedAPI.naikKelas(siswa.id, payload);
          break;
        case 'pindah-kelas':
          response = useWaliOperationalFlow
            ? await siswaExtendedAPI.requestPindahKelas(siswa.id, payload)
            : await siswaExtendedAPI.pindahKelas(siswa.id, payload);
          break;
        case 'lulus':
          response = await siswaExtendedAPI.lulusSiswa(siswa.id, payload);
          break;
        case 'keluar':
          response = await siswaExtendedAPI.keluarSiswa(siswa.id, payload);
          break;
        default:
          throw new Error('Tipe transisi tidak valid');
      }

      const responsePayload = response?.data || {};
      if (responsePayload.success) {
        const fallbackMessage = (() => {
          if (type === 'pindah-kelas' && useWaliOperationalFlow) {
            return 'Request pindah kelas berhasil diajukan dan menunggu approval.';
          }
          return 'Transisi berhasil';
        })();

        toast.success(responsePayload.message || fallbackMessage);
        if (onSuccess) onSuccess(responsePayload);
      }
    } catch (error) {
      console.error('Error in transisi:', error);
      const message = error?.response?.data?.message || 'Gagal melakukan transisi';
      const windowData = error?.response?.data?.data?.window;

      if (windowData && type === 'naik-kelas') {
        const openAt = formatServerDateTime(windowData.open_at, 'id-ID') || '-';
        const closeAt = formatServerDateTime(windowData.close_at, 'id-ID') || '-';
        toast.error(`${message} (window: ${openAt} s.d. ${closeAt})`);
      } else {
        toast.error(message);
      }
    } finally {
      setLoading(false);
    }
  };

  const needsKelas = type === 'naik-kelas' || type === 'pindah-kelas';
  
  const filteredKelasList = (() => {
    if (type === 'naik-kelas') {
      // Naik kelas: kelas dengan tingkat lebih tinggi + wajib tahun ajaran tujuan yang dipilih
      const currentRank = getTingkatRank(siswa?.kelas || currentKelas || {});
      const targetTahunAjaranId = Number(formData.tahun_ajaran_id || 0);
      return kelasList.filter((k) => {
        if (getTingkatRank(k) <= currentRank) {
          return false;
        }

        if (!targetTahunAjaranId) {
          return false;
        }

        if (Number(resolveKelasTahunAjaranId(k) || 0) !== targetTahunAjaranId) {
          return false;
        }

        return true;
      });
    } else if (type === 'pindah-kelas') {
      // Pindah kelas: hanya kelas tingkat sama, tahun ajaran sama, dan bukan kelas yang sama
      const currentRank = getTingkatRank(siswa?.kelas || currentKelas || {});
      const currentKelasId = Number(siswa?.kelas?.id || currentKelas?.id || 0);
      const targetTahunAjaranId = Number(currentTahunAjaranId || 0);
      return kelasList.filter((k) =>
        getTingkatRank(k) === currentRank &&
        Number(k?.id || 0) !== currentKelasId &&
        Number(resolveKelasTahunAjaranId(k) || 0) === targetTahunAjaranId
      );
    }
    return kelasList;
  })();

  useEffect(() => {
    if (!open || type !== 'naik-kelas') {
      return;
    }

    setFormData((prev) => {
      const selectedKelasId = Number(prev.kelas_id || 0);
      if (!selectedKelasId) {
        return prev;
      }

      const kelasMasihValid = filteredKelasList.some((item) => Number(item?.id || 0) === selectedKelasId);
      if (kelasMasihValid) {
        return prev;
      }

      return {
        ...prev,
        kelas_id: '',
      };
    });
  }, [open, type, filteredKelasList]);

  // Use siswa data directly with fallbacks
  const siswaData = {
    nama: siswa?.nama || siswa?.nama_lengkap || 'Nama tidak tersedia',
    nis: siswa?.nis || '-',
    nisn: siswa?.nisn || '-',
    status: siswa?.status || 'aktif',
    kelas: siswa?.kelas || {}
  };

  // If modal is not open, don't render anything
  if (!open) {
    return null;
  }

  return (
    <Dialog
      open={open}
      onClose={loading ? undefined : onClose}
      maxWidth="sm"
      fullWidth
      fullScreen={isMobile}
      PaperProps={{
        sx: {
          borderRadius: isMobile ? 0 : 2,
          margin: isMobile ? 0 : 2
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
          {/* Student Info Card */}
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Box display="flex" alignItems="flex-start" gap={2}>
                <Avatar
                  sx={{
                    bgcolor: theme.palette.primary.main,
                    width: 48,
                    height: 48
                  }}
                >
                  {siswa?.nama?.charAt(0)?.toUpperCase() || 'S'}
                </Avatar>
                <Box flex={1}>
                  <Typography variant="subtitle1" fontWeight="medium" gutterBottom>
                    {siswaData?.nama || siswa?.nama || 'Nama tidak tersedia'}
                  </Typography>
                  <Grid container spacing={2}>
                    <Grid item xs={12} sm={6}>
                      <Typography variant="caption" color="textSecondary" display="block">
                        NIS
                      </Typography>
                      <Typography variant="body2">
                        {siswaData?.nis || siswa?.nis || '-'}
                      </Typography>
                    </Grid>
                    <Grid item xs={12} sm={6}>
                      <Typography variant="caption" color="textSecondary" display="block">
                        NISN
                      </Typography>
                      <Typography variant="body2">
                        {siswaData?.nisn || siswa?.nisn || '-'}
                      </Typography>
                    </Grid>
                    <Grid item xs={12} sm={6}>
                      <div>
                        <Typography variant="caption" color="textSecondary" component="div">
                          Kelas Saat Ini
                        </Typography>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', marginTop: '4px' }}>
                          <Typography variant="body2" component="span">
                            {siswaData?.kelas?.nama || siswa?.kelas?.nama || currentKelas?.namaKelas || '-'}
                          </Typography>
                          <Chip
                            label={siswaData?.kelas?.tingkat?.nama || siswa?.kelas?.tingkat?.nama || currentKelas?.tingkat || '-'}
                            size="small"
                            color="primary"
                            variant="outlined"
                            sx={{ fontSize: '0.7rem' }}
                          />
                        </div>
                        <Typography variant="caption" color="textSecondary" component="div" sx={{ mt: 0.5 }}>
                          Wali Kelas: {siswaData?.kelas?.waliKelas || siswa?.kelas?.waliKelas || currentKelas?.waliKelas || 'Belum ditentukan'}
                        </Typography>
                      </div>
                    </Grid>
                    <Grid item xs={12} sm={6}>
                      <Typography variant="caption" color="textSecondary" display="block">
                        Status
                      </Typography>
                      <Chip
                        label={siswaData?.status || siswa?.status || 'aktif'}
                        color={(siswaData?.status || siswa?.status || 'aktif') === 'aktif' ? 'success' : 'default'}
                        size="small"
                        sx={{ fontSize: '0.75rem' }}
                      />
                    </Grid>
                  </Grid>
                </Box>
              </Box>
            </CardContent>
          </Card>

          <Box display="flex" flexDirection="column" gap={3}>
            {/* Tahun Ajaran Selection */}
            {needsKelas && (
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
                  disabled={loading || type === 'pindah-kelas'}
                >
                  {availableTahunAjaranList.map((ta) => (
                    <MenuItem key={ta.id} value={ta.id}>
                      {ta.nama}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}

            {/* Kelas Selection */}
            {needsKelas && (
              <FormControl fullWidth required>
                <InputLabel>Kelas Tujuan</InputLabel>
                <Select
                  value={formData.kelas_id}
                  onChange={(e) => setFormData((prev) => ({ ...prev, kelas_id: e.target.value }))}
                  label="Kelas Tujuan"
                  disabled={loading || (type === 'naik-kelas' && !formData.tahun_ajaran_id)}
                  startAdornment={
                    <School className="ml-2 mr-1 text-gray-400" size={20} />
                  }
                >
                  {filteredKelasList.map((kelas) => (
                    <MenuItem key={kelas.id} value={kelas.id}>
                      <Box display="flex" flexDirection="column" alignItems="flex-start" sx={{ width: '100%' }}>
                        <Box display="flex" justifyContent="space-between" width="100%" alignItems="center">
                          <Typography variant="body2" fontWeight="medium">
                            {kelas.namaKelas || kelas.nama_kelas || kelas.nama}
                          </Typography>
                          <Chip
                            label={`${Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0)}/${Number(kelas.kapasitas ?? 0)}`}
                            color={Number(kelas.jumlahSiswa ?? kelas.jumlah_siswa ?? 0) >= Number(kelas.kapasitas ?? 0) ? 'error' : 'success'}
                            size="small"
                            sx={{ ml: 1 }}
                          />
                        </Box>
                        <Box mt={1} width="100%">
                          <Typography variant="caption" color="textSecondary" display="block" gutterBottom>
                            Tingkat: {kelas.tingkat?.nama || kelas.tingkat || 'N/A'} | Wali Kelas: {kelas.waliKelas || 'Belum ditentukan'}
                          </Typography>
                          <Box sx={{ width: '100%', mt: 0.5 }}>
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
                          </Box>
                        </Box>
                      </Box>
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}

            {/* Date Input */}
            <TextField
              label="Tanggal"
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

            {/* Notes */}
            <TextField
              label="Keterangan"
              multiline
              rows={3}
              value={formData.keterangan}
              onChange={(e) => setFormData(prev => ({ ...prev, keterangan: e.target.value }))}
              fullWidth
              disabled={loading}
            />

            {/* Info Alert */}
            <Alert severity="info" sx={{ borderRadius: 1 }}>
              {operationModeLabel && (
                <Typography variant="body2" fontWeight="bold" sx={{ mb: 0.5 }}>
                  Mode: {operationModeLabel}
                </Typography>
              )}
              {type === 'naik-kelas' && 'Siswa akan dipindahkan ke kelas dengan tingkat yang lebih tinggi'}
              {type === 'pindah-kelas' && (
                useWaliOperationalFlow
                  ? 'Request pindah kelas akan diajukan untuk approval kurikulum/admin.'
                  : 'Siswa akan dipindahkan ke kelas lain dalam tingkat yang sama.'
              )}
              {type === 'lulus' && 'Siswa akan diubah statusnya menjadi lulus'}
              {type === 'keluar' && 'Siswa akan diubah statusnya menjadi keluar dari sekolah'}
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
            disabled={loading}
            startIcon={loading ? <CircularProgress size={16} /> : getIcon()}
            fullWidth={isMobile}
          >
            {loading ? 'Memproses...' : 'Simpan'}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default TransisiSiswaModal;

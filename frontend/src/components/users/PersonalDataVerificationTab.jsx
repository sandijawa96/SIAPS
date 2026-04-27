import React, { useMemo } from 'react';
import {
  Box,
  Button,
  Chip,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
  InputAdornment,
  LinearProgress,
} from '@mui/material';
import { Search, Eye, CheckCircle2, AlertTriangle, RotateCcw } from 'lucide-react';
import { formatServerDateTime } from '../../services/serverClock';

const statusColorMap = {
  draft: 'default',
  menunggu_verifikasi: 'info',
  terverifikasi: 'success',
  perlu_perbaikan: 'error',
};

const completionTierMap = {
  lengkap: {
    label: 'Lengkap',
    color: 'success',
  },
  cukup: {
    label: 'Cukup',
    color: 'warning',
  },
  kurang: {
    label: 'Kurang',
    color: 'error',
  },
};

const profileLabelMap = {
  siswa: 'Siswa',
  pegawai: 'Pegawai',
};

const formatDateTime = (value) => {
  if (!value) {
    return '-';
  }

  return formatServerDateTime(value, 'id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }) || '-';
};

const resolveCompletionTier = (item) => {
  const explicitTier = String(item?.completion_tier || item?.completion?.tier || '').trim();
  if (explicitTier && completionTierMap[explicitTier]) {
    return explicitTier;
  }

  const percentage = Number(item?.completion_percentage || 0);
  if (percentage >= 90) {
    return 'lengkap';
  }
  if (percentage >= 60) {
    return 'cukup';
  }

  return 'kurang';
};

const PersonalDataVerificationTab = ({
  rows = [],
  loading = false,
  filters = {},
  onFilterChange,
  availableTingkat = [],
  availableKelas = [],
  onOpenProfile,
  onDecision,
  canVerifySiswa = false,
  canVerifyPegawai = false,
  lockedProfileType = '',
}) => {
  const normalizedLockedProfileType = useMemo(() => {
    if (lockedProfileType === 'siswa' || lockedProfileType === 'pegawai') {
      return lockedProfileType;
    }

    return '';
  }, [lockedProfileType]);

  const filteredKelasOptions = useMemo(() => {
    const selectedTingkatId = String(filters?.tingkat_id ?? '').trim();
    if (!selectedTingkatId) {
      return availableKelas || [];
    }

    return (availableKelas || []).filter((kelasItem) => (
      String(kelasItem?.tingkat_id ?? '') === selectedTingkatId
    ));
  }, [availableKelas, filters?.tingkat_id]);

  const profileTypeOptions = useMemo(() => {
    if (normalizedLockedProfileType === 'siswa') {
      return [{ value: 'siswa', label: 'Siswa' }];
    }

    if (normalizedLockedProfileType === 'pegawai') {
      return [{ value: 'pegawai', label: 'Pegawai' }];
    }

    return [
      { value: 'all', label: 'Semua' },
      { value: 'pegawai', label: 'Pegawai' },
      { value: 'siswa', label: 'Siswa' },
    ];
  }, [normalizedLockedProfileType]);

  const profileTypeValue = normalizedLockedProfileType
    || filters.profile_type
    || 'all';

  return (
    <>
      <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
        <Box className="flex flex-col lg:flex-row gap-4">
          <TextField
            size="small"
            className="flex-1 lg:max-w-md"
            placeholder="Cari nama, username, email, NIS, NIP..."
            value={filters.search || ''}
            onChange={(event) => onFilterChange('search', event.target.value)}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-4 h-4 text-gray-400" />
                </InputAdornment>
              ),
            }}
          />

          <Box className="flex flex-wrap gap-3">
            <FormControl size="small" className="min-w-[150px]">
              <InputLabel>Tipe</InputLabel>
              <Select
                label="Tipe"
                value={profileTypeValue}
                onChange={(event) => onFilterChange('profile_type', event.target.value)}
                disabled={normalizedLockedProfileType !== ''}
              >
                {profileTypeOptions.map((option) => (
                  <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
                ))}
              </Select>
            </FormControl>

            <FormControl size="small" className="min-w-[190px]">
              <InputLabel>Status Verifikasi</InputLabel>
              <Select
                label="Status Verifikasi"
                value={filters.status_verifikasi || 'all'}
                onChange={(event) => onFilterChange('status_verifikasi', event.target.value)}
              >
                <MenuItem value="all">Semua Status</MenuItem>
                <MenuItem value="draft">Draft</MenuItem>
                <MenuItem value="menunggu_verifikasi">Menunggu Verifikasi</MenuItem>
                <MenuItem value="terverifikasi">Terverifikasi</MenuItem>
                <MenuItem value="perlu_perbaikan">Perlu Perbaikan</MenuItem>
              </Select>
            </FormControl>

            <FormControl size="small" className="min-w-[170px]">
              <InputLabel>Kelengkapan</InputLabel>
              <Select
                label="Kelengkapan"
                value={filters.completion_tier || 'all'}
                onChange={(event) => onFilterChange('completion_tier', event.target.value)}
              >
                <MenuItem value="all">Semua Tier</MenuItem>
                <MenuItem value="lengkap">Lengkap</MenuItem>
                <MenuItem value="cukup">Cukup</MenuItem>
                <MenuItem value="kurang">Kurang</MenuItem>
              </Select>
            </FormControl>

            <FormControl size="small" className="min-w-[140px]">
              <InputLabel>Tingkat</InputLabel>
              <Select
                label="Tingkat"
                value={filters.tingkat_id || ''}
                onChange={(event) => onFilterChange('tingkat_id', event.target.value)}
              >
                <MenuItem value="">Semua Tingkat</MenuItem>
                {(availableTingkat || []).map((tingkatItem) => (
                  <MenuItem key={tingkatItem.id} value={String(tingkatItem.id)}>
                    {tingkatItem.nama}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>

            <FormControl size="small" className="min-w-[170px]">
              <InputLabel>Kelas</InputLabel>
              <Select
                label="Kelas"
                value={filters.kelas_id || ''}
                onChange={(event) => onFilterChange('kelas_id', event.target.value)}
              >
                <MenuItem value="">Semua Kelas</MenuItem>
                {filteredKelasOptions.map((kelasItem) => (
                  <MenuItem key={kelasItem.id} value={String(kelasItem.id)}>
                    {kelasItem.nama_kelas}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Box>
        </Box>
      </Paper>

      <TableContainer component={Paper} className="shadow-sm">
        <Table>
          <TableHead>
            <TableRow className="bg-gray-50">
              <TableCell>Nama Pengguna</TableCell>
              <TableCell>Tipe</TableCell>
              <TableCell>Kelas / Tingkat</TableCell>
              <TableCell>Kelengkapan</TableCell>
              <TableCell>Status Verifikasi</TableCell>
              <TableCell>Update Terakhir</TableCell>
              <TableCell>Review Terakhir</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading ? (
              [...Array(6)].map((_, index) => (
                <TableRow key={index}>
                  {[...Array(8)].map((__, colIdx) => (
                    <TableCell key={colIdx}>
                      <Skeleton variant="text" />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} align="center" className="py-8">
                  <Typography variant="body2" color="textSecondary">
                    Tidak ada data verifikasi.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : (
              rows.map((item) => (
                <TableRow key={item.user_id} hover>
                  <TableCell>
                    <Typography variant="body2" className="font-medium">
                      {item.nama_lengkap || '-'}
                    </Typography>
                    <Typography variant="caption" color="textSecondary">
                      {item.username || '-'} | {item.email || '-'}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={profileLabelMap[item.profile_type] || item.profile_type || '-'}
                      color={item.profile_type === 'siswa' ? 'warning' : 'info'}
                      variant="outlined"
                    />
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2">{item.kelas_aktif || '-'}</Typography>
                    <Typography variant="caption" color="textSecondary">
                      {item.tingkat_aktif || '-'}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Box className="min-w-[180px]">
                      <Box className="flex items-center justify-between mb-1">
                        <Typography variant="body2">
                          {item.completion_percentage ?? 0}%
                        </Typography>
                        <Chip
                          size="small"
                          variant="outlined"
                          label={completionTierMap[resolveCompletionTier(item)]?.label || 'Kurang'}
                          color={completionTierMap[resolveCompletionTier(item)]?.color || 'default'}
                        />
                      </Box>
                      <LinearProgress
                        variant="determinate"
                        value={Math.max(0, Math.min(100, Number(item.completion_percentage || 0)))}
                        color={completionTierMap[resolveCompletionTier(item)]?.color || 'primary'}
                        sx={{ height: 6, borderRadius: 4, mb: 0.5 }}
                      />
                      <Typography variant="caption" color="textSecondary">
                        {item.completion?.filled ?? 0}/{item.completion?.total ?? 0} field inti
                      </Typography>
                    </Box>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={item.status_verifikasi_label || '-'}
                      color={statusColorMap[item.status_verifikasi] || 'default'}
                      variant="filled"
                    />
                  </TableCell>
                  <TableCell>{formatDateTime(item.last_personal_update_at)}</TableCell>
                  <TableCell>{formatDateTime(item.last_reviewed_at)}</TableCell>
                  <TableCell align="center">
                    <Box className="flex items-center justify-center gap-1">
                      <Tooltip title="Buka Data Pribadi">
                        <span>
                          <Button
                            size="small"
                            variant="outlined"
                            startIcon={<Eye className="w-4 h-4" />}
                            onClick={() => onOpenProfile(item)}
                          >
                            Review
                          </Button>
                        </span>
                      </Tooltip>

                      {(String(item?.profile_type || '') === 'siswa' ? canVerifySiswa : canVerifyPegawai) ? (
                        <>
                          <Tooltip title="Setujui">
                            <span>
                              <Button
                                size="small"
                                color="success"
                                onClick={() => onDecision(item, 'approve')}
                                sx={{ minWidth: '36px', px: 1 }}
                              >
                                <CheckCircle2 className="w-4 h-4" />
                              </Button>
                            </span>
                          </Tooltip>

                          <Tooltip title="Perlu Perbaikan">
                            <span>
                              <Button
                                size="small"
                                color="warning"
                                onClick={() => onDecision(item, 'needs_revision')}
                                sx={{ minWidth: '36px', px: 1 }}
                              >
                                <AlertTriangle className="w-4 h-4" />
                              </Button>
                            </span>
                          </Tooltip>

                          <Tooltip title="Reset Status">
                            <span>
                              <Button
                                size="small"
                                color="inherit"
                                onClick={() => onDecision(item, 'reset')}
                                sx={{ minWidth: '36px', px: 1 }}
                              >
                                <RotateCcw className="w-4 h-4" />
                              </Button>
                            </span>
                          </Tooltip>
                        </>
                      ) : null}
                    </Box>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
    </>
  );
};

export default PersonalDataVerificationTab;

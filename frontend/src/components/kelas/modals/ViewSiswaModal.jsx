import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Autocomplete,
  Button,
  Typography,
  Box,
  Chip,
  IconButton,
  Menu,
  MenuItem,
  Card,
  CardContent,
  Grid,
  Avatar,
  Divider,
  useTheme,
  useMediaQuery,
  Tooltip,
  Alert,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  TextField,
  ToggleButton,
  ToggleButtonGroup
} from '@mui/material';
import {
  Users,
  BookOpen,
  Calendar,
  MoreVertical,
  ArrowUp,
  ArrowRight,
  GraduationCap,
  LogOut,
  Edit,
  Trash2,
  UserPlus,
  X,
  School,
  Clock,
  MapPin,
  LayoutGrid,
  List,
  History
} from 'lucide-react';
import TransisiSiswaModal from '../../modals/TransisiSiswaModal';
import BulkTransisiModal from '../../modals/BulkTransisiModal';
import RiwayatTransisiModal from '../../modals/RiwayatTransisiModal';
import TransferRequestQueueModal from '../../modals/TransferRequestQueueModal';
import PromotionWindowSettingModal from '../../modals/PromotionWindowSettingModal';
import { kelasAPI } from '../../../services/kelasService';
import toast from 'react-hot-toast';

const ViewSiswaModal = ({
  open,
  onClose,
  kelas,
  onDeleteSiswa,
  onEditSiswa,
  kelasList = [],
  tingkatList = [],
  tahunAjaranList = [],
  activeTahunAjaran = null,
  onRefresh,
  canManageKelas = true,
  canManageStudents = false,
  canManageStudentTransitions = false,
  canManagePromotionWindow = false
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  
  const [anchorEl, setAnchorEl] = useState(null);
  const [selectedSiswa, setSelectedSiswa] = useState(null);
  const [showTransisiModal, setShowTransisiModal] = useState(false);
  const [transisiType, setTransisiType] = useState('');
  const [viewMode, setViewMode] = useState('card'); // 'card' or 'table'
  const [selectedSiswaIds, setSelectedSiswaIds] = useState([]);
  const [showBulkTransisiModal, setShowBulkTransisiModal] = useState(false);
  const [bulkTransisiMode, setBulkTransisiMode] = useState('naik-kelas');
  const [showRiwayatTransisiModal, setShowRiwayatTransisiModal] = useState(false);
  const [showTransferQueueModal, setShowTransferQueueModal] = useState(false);
  const [showPromotionWindowModal, setShowPromotionWindowModal] = useState(false);
  const [siswaRows, setSiswaRows] = useState(Array.isArray(kelas?.siswa) ? kelas.siswa : []);
  const [showAssignExistingModal, setShowAssignExistingModal] = useState(false);
  const [availableSiswaLoading, setAvailableSiswaLoading] = useState(false);
  const [availableSiswaOptions, setAvailableSiswaOptions] = useState([]);
  const [availableSiswaSearch, setAvailableSiswaSearch] = useState('');
  const [selectedAvailableSiswa, setSelectedAvailableSiswa] = useState([]);
  const [membershipViewMode, setMembershipViewMode] = useState('aktif');
  const [transitionKelasList, setTransitionKelasList] = useState([]);
  const canOpenActionMenu = canManageKelas || canManageStudents || canManageStudentTransitions;

  const getTingkatUrutan = useCallback(
    (tingkatId) => {
      const id = Number(tingkatId);
      if (!Number.isFinite(id)) {
        return null;
      }

      const match = (Array.isArray(tingkatList) ? tingkatList : []).find(
        (item) => Number(item?.id) === id
      );
      const urutan = Number(match?.urutan);
      return Number.isFinite(urutan) ? urutan : null;
    },
    [tingkatList]
  );

  const resolveTahunAjaranId = useCallback((item) => {
    const rawId = Number(
      item?.tahun_ajaran_id ??
      item?.tahunAjaranId ??
      item?.tahun_ajaran?.id ??
      item?.tahunAjaran?.id ??
      0
    );
    return Number.isFinite(rawId) && rawId > 0 ? rawId : null;
  }, []);

  const normalizeKelasWithUrutan = useCallback((source = []) => {
    return (Array.isArray(source) ? source : []).map((item) => {
      if (!item || typeof item !== 'object') {
        return item;
      }

      const urutan = getTingkatUrutan(item.tingkat_id ?? item?.tingkat?.id);

      return {
        ...item,
        tingkat_urutan: item.tingkat_urutan ?? urutan,
        tahun_ajaran_id: item.tahun_ajaran_id ?? resolveTahunAjaranId(item),
        tingkat: typeof item.tingkat === 'object'
          ? {
              ...item.tingkat,
              urutan: item.tingkat?.urutan ?? urutan
            }
          : item.tingkat
      };
    });
  }, [getTingkatUrutan, resolveTahunAjaranId]);

  const kelasListWithUrutan = useMemo(
    () => normalizeKelasWithUrutan(kelasList),
    [kelasList, normalizeKelasWithUrutan]
  );

  const transitionKelasListWithUrutan = useMemo(
    () => normalizeKelasWithUrutan(transitionKelasList),
    [transitionKelasList, normalizeKelasWithUrutan]
  );

  const kelasListForTransisi = transitionKelasListWithUrutan.length > 0
    ? transitionKelasListWithUrutan
    : kelasListWithUrutan;

  useEffect(() => {
    setSiswaRows(Array.isArray(kelas?.siswa) ? kelas.siswa : []);
  }, [kelas?.id, kelas?.siswa]);

  useEffect(() => {
    if (!open || (!canManageStudentTransitions && !canManageStudents)) {
      return;
    }

    let isActive = true;

    const loadTransitionKelasList = async () => {
      try {
        const response = await kelasAPI.getAll({ can_manage_classes: true });
        const payload = response?.data;
        const rows = Array.isArray(payload?.data)
          ? payload.data
          : (Array.isArray(payload) ? payload : []);

        if (isActive) {
          setTransitionKelasList(rows);
        }
      } catch (error) {
        if (isActive) {
          setTransitionKelasList([]);
        }
      }
    };

    loadTransitionKelasList();

    return () => {
      isActive = false;
    };
  }, [open, canManageStudentTransitions, canManageStudents]);

  const normalizeSiswaResponse = (response) => {
    const payload = response?.data;
    if (Array.isArray(payload?.data)) {
      return payload.data;
    }
    if (Array.isArray(payload)) {
      return payload;
    }
    return [];
  };

  const refreshSiswaRows = useCallback(async (mode = membershipViewMode) => {
    if (!kelas?.id) {
      return;
    }

    try {
      const response = await kelasAPI.getSiswa(kelas.id, {
        include_history: mode === 'riwayat' ? 1 : 0,
      });
      setSiswaRows(normalizeSiswaResponse(response));
    } catch (error) {
      console.error('Failed to refresh siswa rows:', error);
    }
  }, [kelas?.id, membershipViewMode]);

  useEffect(() => {
    if (!open || !kelas?.id) {
      return;
    }

    refreshSiswaRows(membershipViewMode);
  }, [open, kelas?.id, membershipViewMode, refreshSiswaRows]);

  const loadAvailableSiswa = useCallback(async (searchTerm = '') => {
    if (!kelas?.id) {
      return;
    }

    try {
      setAvailableSiswaLoading(true);
      const response = await kelasAPI.getAvailableSiswa(kelas.id, {
        search: searchTerm,
        limit: 50,
      });

      const items = Array.isArray(response?.data?.data) ? response.data.data : [];
      setAvailableSiswaOptions(items);
    } catch (error) {
      console.error('Failed to load available siswa:', error);
      setAvailableSiswaOptions([]);
    } finally {
      setAvailableSiswaLoading(false);
    }
  }, [kelas?.id]);

  useEffect(() => {
    if (!showAssignExistingModal) {
      return;
    }

    const timer = setTimeout(() => {
      loadAvailableSiswa(availableSiswaSearch);
    }, 250);

    return () => clearTimeout(timer);
  }, [showAssignExistingModal, availableSiswaSearch, loadAvailableSiswa]);

  const handleAssignExistingSiswa = async () => {
    if (!kelas?.id) {
      return;
    }

    if (!kelas?.tahun_ajaran_id) {
      toast.error('Tahun ajaran kelas tidak ditemukan');
      return;
    }

    if (!Array.isArray(selectedAvailableSiswa) || selectedAvailableSiswa.length === 0) {
      toast.error('Pilih minimal 1 siswa');
      return;
    }

    try {
      const payload = {
        siswa_ids: selectedAvailableSiswa.map((item) => item.id),
        tahun_ajaran_id: kelas.tahun_ajaran_id,
      };
      const response = await kelasAPI.assignSiswa(kelas.id, payload);
      toast.success(response?.data?.message || 'Siswa berhasil dimasukkan ke kelas');

      setSelectedAvailableSiswa([]);
      setAvailableSiswaSearch('');
      setShowAssignExistingModal(false);

      await refreshSiswaRows(membershipViewMode);
      if (onRefresh) {
        onRefresh();
      }
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal memasukkan siswa ke kelas');
    }
  };

  const handleMenuOpen = (event, siswa) => {
    if (!canOpenActionMenu) {
      return;
    }

    setAnchorEl(event.currentTarget);
    setSelectedSiswa(siswa);
  };

  const handleMenuClose = ({ preserveSelected = false } = {}) => {
    setAnchorEl(null);
    // Only reset selectedSiswa if modal is not open
    if (!preserveSelected && !showTransisiModal && !showRiwayatTransisiModal) {
      setSelectedSiswa(null);
    }
  };

  const handleTransisi = (type) => {
    if (!selectedSiswa) return;

    
    // Pass complete siswa data including kelas info
    const siswaWithKelas = {
      ...selectedSiswa,
      kelas: {
        id: kelas.id,
        nama: kelas.namaKelas,
        tingkat_id: kelas.tingkat_id,
        tahun_ajaran_id: kelas.tahun_ajaran_id ?? null,
        tingkat_urutan: getTingkatUrutan(kelas.tingkat_id),
        tingkat: {
          id: kelas.tingkat_id,
          nama: kelas.tingkat,
          urutan: getTingkatUrutan(kelas.tingkat_id)
        },
        waliKelas: kelas.waliKelas
      }
    };

    // Update selectedSiswa with kelas info before opening modal
    setSelectedSiswa(siswaWithKelas);
    setTransisiType(type);
    setShowTransisiModal(true);
    setAnchorEl(null); // Just close the menu without resetting selectedSiswa
  };

  const getInitials = (name) => {
    if (!name) return 'S';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'aktif': return 'success';
      case 'pindah': return 'warning';
      case 'naik_kelas': return 'info';
      case 'lulus': return 'info';
      case 'keluar': return 'error';
      default: return 'default';
    }
  };

  const getStatusLabel = (status) => {
    switch (String(status || '').toLowerCase()) {
      case 'aktif': return 'Aktif';
      case 'pindah': return 'Pindah';
      case 'naik_kelas': return 'Naik Kelas';
      case 'lulus': return 'Lulus';
      case 'keluar': return 'Keluar';
      default: return status || '-';
    }
  };

  const canApplyActionToSiswa = (siswaItem) =>
    String(siswaItem?.status || '').toLowerCase() === 'aktif';

  const activeSiswaRows = siswaRows.filter((item) => canApplyActionToSiswa(item));

  if (!kelas) return null;

  return (
    <>
      <Dialog
        open={open}
        onClose={onClose}
        maxWidth="lg"
        fullWidth
        fullScreen={isMobile}
        scroll="body"
        PaperProps={{
          sx: {
            borderRadius: isMobile ? 0 : 2,
            maxHeight: isMobile ? '100vh' : '90vh',
            margin: isMobile ? 0 : '16px',
            width: isMobile ? '100%' : 'calc(100% - 32px)',
            position: isMobile ? 'fixed' : 'relative',
            top: isMobile ? 0 : '5vh',
            transform: isMobile ? 'none' : 'translateY(0)',
          }
        }}
        sx={{
          '& .MuiDialog-container': {
            alignItems: isMobile ? 'stretch' : 'flex-start',
            paddingTop: isMobile ? 0 : '5vh',
            paddingBottom: isMobile ? 0 : '5vh',
          }
        }}
      >
        {/* Header */}
        <DialogTitle
          sx={{
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            color: 'white',
            p: isMobile ? 2 : 3
          }}
        >
          <Box display="flex" alignItems="center" justifyContent="space-between">
            <Box display="flex" alignItems="center" gap={2}>
              <Avatar
                sx={{
                  bgcolor: 'rgba(255,255,255,0.2)',
                  width: isMobile ? 40 : 48,
                  height: isMobile ? 40 : 48
                }}
              >
                <Users size={isMobile ? 20 : 24} />
              </Avatar>
              <Box>
                <Typography variant={isMobile ? "h6" : "h5"} fontWeight="bold">
                  Daftar Siswa
                </Typography>
                <Typography variant="body2" sx={{ opacity: 0.9 }}>
                  Kelas {kelas.namaKelas}
                </Typography>
              </Box>
            </Box>
            {!isMobile && (
              <IconButton onClick={onClose} sx={{ color: 'white' }}>
                <X size={24} />
              </IconButton>
            )}
          </Box>
        </DialogTitle>

        <DialogContent sx={{ p: 0 }}>
          {/* Class Info Card */}
          <Card sx={{ m: isMobile ? 2 : 3, mb: 2 }}>
            <CardContent>
              <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h6" fontWeight="bold">
                  Informasi Kelas
                </Typography>

                <Box display="flex" alignItems="center" gap={1}>
                  <ToggleButtonGroup
                    value={membershipViewMode}
                    exclusive
                    onChange={(_, nextMode) => {
                      if (nextMode) {
                        setMembershipViewMode(nextMode);
                      }
                    }}
                    size="small"
                    sx={{
                      '& .MuiToggleButton-root': {
                        px: isMobile ? 1 : 1.5,
                        py: 0.5,
                        border: '1px solid',
                        borderColor: 'divider'
                      }
                    }}
                  >
                    <ToggleButton value="aktif" aria-label="siswa aktif">
                      {!isMobile ? 'Aktif' : 'A'}
                    </ToggleButton>
                    <ToggleButton value="riwayat" aria-label="riwayat siswa">
                      {!isMobile ? 'Riwayat' : 'R'}
                    </ToggleButton>
                  </ToggleButtonGroup>

                  {siswaRows.length > 0 && (
                    <ToggleButtonGroup
                      value={viewMode}
                      exclusive
                      onChange={(e, newMode) => newMode && setViewMode(newMode)}
                      size="small"
                      sx={{
                        '& .MuiToggleButton-root': {
                          px: isMobile ? 1 : 2,
                          py: 0.5,
                          border: '1px solid',
                          borderColor: 'divider'
                        }
                      }}
                    >
                      <ToggleButton value="card" aria-label="card view">
                        <LayoutGrid size={16} />
                        {!isMobile && <Typography variant="caption" component="span" sx={{ ml: 1 }}>Card</Typography>}
                      </ToggleButton>
                      <ToggleButton value="table" aria-label="table view">
                        <List size={16} />
                        {!isMobile && <Typography variant="caption" component="span" sx={{ ml: 1 }}>Table</Typography>}
                      </ToggleButton>
                    </ToggleButtonGroup>
                  )}
                </Box>
              </Box>
              
              <Grid container spacing={isMobile ? 2 : 3}>
                <Grid item xs={12} sm={6} md={3}>
                  <Box display="flex" alignItems="center" gap={1}>
                    <School className="text-blue-500" size={20} />
                    <Box>
                      <Typography variant="caption" color="textSecondary">
                        Wali Kelas
                      </Typography>
                      <Typography variant="body2" fontWeight="medium">
                        {kelas.waliKelas || 'Belum ditentukan'}
                      </Typography>
                    </Box>
                  </Box>
                </Grid>
                
                <Grid item xs={12} sm={6} md={3}>
                  <Box display="flex" alignItems="center" gap={1}>
                    <Users className="text-green-500" size={20} />
                    <Box>
                      <Typography variant="caption" color="textSecondary">
                        Jumlah Siswa
                      </Typography>
                      <Typography variant="body2" fontWeight="medium">
                        {membershipViewMode === 'riwayat'
                          ? `${siswaRows.length || 0} riwayat`
                          : `${siswaRows.length || 0}/${kelas.kapasitas || 0}`}
                      </Typography>
                    </Box>
                  </Box>
                </Grid>
                
                <Grid item xs={12} sm={6} md={3}>
                  <Box display="flex" alignItems="center" gap={1}>
                    <Calendar className="text-purple-500" size={20} />
                    <Box>
                      <Typography variant="caption" color="textSecondary">
                        Tahun Ajaran
                      </Typography>
                      <Typography variant="body2" fontWeight="medium">
                        {kelas.tahunAjaran || 'N/A'}
                      </Typography>
                    </Box>
                  </Box>
                </Grid>
                
                <Grid item xs={12} sm={6} md={3}>
                  <Box display="flex" alignItems="center" gap={1}>
                    <MapPin className="text-orange-500" size={20} />
                    <Box>
                      <Typography variant="caption" color="textSecondary">
                        Tingkat
                      </Typography>
                      <Typography variant="body2" fontWeight="medium">
                        {kelas.tingkat || 'N/A'}
                      </Typography>
                    </Box>
                  </Box>
                </Grid>
              </Grid>
            </CardContent>
          </Card>

          {/* Students List */}
          <Box sx={{ px: isMobile ? 2 : 3, pb: 2 }}>
            {membershipViewMode === 'riwayat' && (
              <Alert severity="info" sx={{ mb: 2, borderRadius: 2 }}>
                Menampilkan data riwayat siswa yang pernah tercatat di kelas ini.
              </Alert>
            )}
            {siswaRows.length === 0 ? (
              <Alert 
                severity="info" 
                sx={{ 
                  borderRadius: 2,
                  '& .MuiAlert-icon': {
                    fontSize: isMobile ? '1.2rem' : '1.5rem'
                  }
                }}
              >
                <Typography variant="body2">
                  Belum ada siswa di kelas ini
                </Typography>
              </Alert>
            ) : viewMode === 'table' ? (
              <TableContainer component={Paper} sx={{ borderRadius: 2, boxShadow: theme.shadows[2] }}>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell width="50">#</TableCell>
                      <TableCell>Nama Siswa</TableCell>
                      <TableCell>NIS</TableCell>
                      <TableCell>NISN</TableCell>
                      <TableCell>Status</TableCell>
                      <TableCell>Tanggal Masuk</TableCell>
                      <TableCell>Tanggal Keluar</TableCell>
                      <TableCell align="right">Aksi</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {siswaRows.map((siswa, index) => (
                      <TableRow key={`${siswa.id}-${siswa.tanggal_masuk || 'na'}-${index}`} hover>
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>
                          <Box display="flex" alignItems="center" gap={2}>
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
                            <Typography variant="body2" fontWeight="medium">
                              {siswa.nama || 'Nama tidak tersedia'}
                            </Typography>
                          </Box>
                        </TableCell>
                        <TableCell>{siswa.nis || '-'}</TableCell>
                        <TableCell>{siswa.nisn || '-'}</TableCell>
                        <TableCell>
                          <Chip
                            label={getStatusLabel(siswa.status || 'aktif')}
                            color={getStatusColor(siswa.status || 'aktif')}
                            size="small"
                            sx={{ fontSize: '0.75rem' }}
                          />
                        </TableCell>
                        <TableCell>{siswa.tanggal_masuk || '-'}</TableCell>
                        <TableCell>{siswa.tanggal_keluar || '-'}</TableCell>
                        <TableCell align="right">
                          {canOpenActionMenu && canApplyActionToSiswa(siswa) ? (
                            <IconButton
                              size="small"
                              onClick={(e) => handleMenuOpen(e, siswa)}
                              sx={{ 
                                width: 28, 
                                height: 28,
                                '&:hover': {
                                  bgcolor: 'action.hover'
                                }
                              }}
                            >
                              <MoreVertical size={16} />
                            </IconButton>
                          ) : (
                            <Typography variant="caption" color="textSecondary">-</Typography>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            ) : (
              <Grid container spacing={isMobile ? 1 : 2}>
                {siswaRows.map((siswa, index) => (
                  <Grid item xs={12} sm={6} lg={4} key={`${siswa.id}-${siswa.tanggal_masuk || 'na'}-${index}`}>
                    <Card
                      sx={{
                        borderRadius: 2,
                        transition: 'all 0.2s ease-in-out',
                        '&:hover': {
                          transform: 'translateY(-2px)',
                          boxShadow: theme.shadows[4]
                        }
                      }}
                    >
                      <CardContent sx={{ p: isMobile ? 2 : 3 }}>
                        <Box display="flex" alignItems="flex-start" justifyContent="space-between">
                          <Box display="flex" alignItems="center" gap={2} flex={1}>
                            <Avatar
                              sx={{
                                bgcolor: theme.palette.primary.main,
                                width: isMobile ? 40 : 48,
                                height: isMobile ? 40 : 48,
                                fontSize: isMobile ? '0.9rem' : '1rem'
                              }}
                            >
                              {getInitials(siswa.nama)}
                            </Avatar>
                            <Box flex={1} minWidth={0}>
                              <Typography 
                                variant="subtitle2" 
                                fontWeight="bold"
                                noWrap
                                title={siswa.nama}
                              >
                                {siswa.nama || 'Nama tidak tersedia'}
                              </Typography>
                              <Typography variant="caption" color="textSecondary">
                                NIS: {siswa.nis || '-'}
                              </Typography>
                              {siswa.nisn && (
                                <Typography variant="caption" color="textSecondary" display="block">
                                  NISN: {siswa.nisn}
                                </Typography>
                              )}
                            </Box>
                          </Box>
                          
                          <Box display="flex" alignItems="center" gap={1}>
                            <Chip
                              label={getStatusLabel(siswa.status || 'aktif')}
                              color={getStatusColor(siswa.status || 'aktif')}
                              size="small"
                              sx={{ fontSize: '0.75rem' }}
                            />
                            {canOpenActionMenu && canApplyActionToSiswa(siswa) && (
                              <Tooltip title="Aksi">
                                <IconButton
                                  size="small"
                                  onClick={(e) => handleMenuOpen(e, siswa)}
                                  sx={{ 
                                    width: 32, 
                                    height: 32,
                                    '&:hover': {
                                      bgcolor: 'action.hover'
                                    }
                                  }}
                                >
                                  <MoreVertical size={16} />
                                </IconButton>
                              </Tooltip>
                            )}
                          </Box>
                        </Box>

                        {/* Additional Info */}
                        <Box mt={2}>
                          <Grid container spacing={1}>
                            <Grid item xs={6}>
                              <Box display="flex" alignItems="center" gap={1}>
                                <BookOpen size={14} className="text-gray-400" />
                                <Typography variant="caption" color="textSecondary">
                                  {kelas.namaKelas}
                                </Typography>
                              </Box>
                            </Grid>
                            <Grid item xs={6}>
                              <Box display="flex" alignItems="center" gap={1}>
                                <Clock size={14} className="text-gray-400" />
                                <Typography variant="caption" color="textSecondary">
                                  #{index + 1}
                                </Typography>
                              </Box>
                            </Grid>
                            {membershipViewMode === 'riwayat' && (
                              <Grid item xs={12}>
                                <Typography variant="caption" color="textSecondary">
                                  Masuk: {siswa.tanggal_masuk || '-'} | Keluar: {siswa.tanggal_keluar || '-'}
                                </Typography>
                              </Grid>
                            )}
                          </Grid>
                        </Box>
                      </CardContent>
                    </Card>
                  </Grid>
                ))}
              </Grid>
            )}
          </Box>
        </DialogContent>

        {/* Actions */}
        <DialogActions sx={{ p: isMobile ? 2 : 3, gap: 1 }}>
          <Button
            onClick={onClose}
            variant="outlined"
            fullWidth={isMobile}
            startIcon={<X size={16} />}
          >
            Tutup
          </Button>
          {membershipViewMode !== 'riwayat' && activeSiswaRows.length > 0 && canManageStudents && (
            <>
              <Button
                variant="contained"
                color="success"
                startIcon={<ArrowUp size={16} />}
                onClick={() => {
                  setBulkTransisiMode('naik-kelas');
                  setShowBulkTransisiModal(true);
                }}
                sx={{ minWidth: isMobile ? 'auto' : 160 }}
              >
                Naik Kelas Massal
              </Button>
              <Button
                variant="contained"
                color="warning"
                startIcon={<GraduationCap size={16} />}
                onClick={() => {
                  setBulkTransisiMode('lulus');
                  setShowBulkTransisiModal(true);
                }}
                sx={{ minWidth: isMobile ? 'auto' : 140 }}
              >
                Lulus Massal
              </Button>
            </>
          )}
          {canManageKelas && (
            <Button
              variant="outlined"
              startIcon={<UserPlus size={16} />}
              onClick={() => setShowAssignExistingModal(true)}
              sx={{ minWidth: isMobile ? 'auto' : 190 }}
            >
              Masukkan Siswa Existing
            </Button>
          )}
          {(canManageStudentTransitions || canManagePromotionWindow) && (
            <>
              {canManageStudentTransitions && (
                <Button
                  variant="outlined"
                  color="secondary"
                  startIcon={<ArrowRight size={16} />}
                  onClick={() => setShowTransferQueueModal(true)}
                  sx={{ minWidth: isMobile ? 'auto' : 180 }}
                >
                  Antrean Pindah Kelas
                </Button>
              )}
              {(canManagePromotionWindow || canManageStudentTransitions) && (
                <Button
                  variant="outlined"
                  color="primary"
                  startIcon={<Calendar size={16} />}
                  onClick={() => setShowPromotionWindowModal(true)}
                  sx={{ minWidth: isMobile ? 'auto' : 190 }}
                >
                  Window Naik Kelas
                </Button>
              )}
            </>
          )}
        </DialogActions>
      </Dialog>

      <Dialog
        open={showAssignExistingModal}
        onClose={() => {
          setShowAssignExistingModal(false);
          setSelectedAvailableSiswa([]);
          setAvailableSiswaSearch('');
        }}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>Masukkan Siswa Existing</DialogTitle>
        <DialogContent>
          <Alert severity="info" sx={{ mb: 2 }}>
            Hanya menampilkan siswa aktif yang belum memiliki kelas aktif.
          </Alert>
          <Autocomplete
            multiple
            options={availableSiswaOptions}
            loading={availableSiswaLoading}
            value={selectedAvailableSiswa}
            onChange={(_, value) => setSelectedAvailableSiswa(value || [])}
            inputValue={availableSiswaSearch}
            onInputChange={(_, value) => setAvailableSiswaSearch(value || '')}
            getOptionLabel={(option) =>
              `${option?.nama || '-'}${option?.nis ? ` (${option.nis})` : ''}`
            }
            isOptionEqualToValue={(option, value) => option.id === value.id}
            filterOptions={(x) => x}
            noOptionsText={availableSiswaLoading ? 'Memuat...' : 'Tidak ada siswa tersedia'}
            renderInput={(params) => (
              <TextField
                {...params}
                label="Cari siswa (nama/NIS/NISN/email)"
                placeholder="Ketik untuk mencari siswa"
                margin="normal"
              />
            )}
          />
          <Typography variant="caption" color="text.secondary">
            Kelas tujuan: {kelas?.namaKelas || '-'} | Tahun ajaran: {kelas?.tahunAjaran || '-'}
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button
            onClick={() => {
              setShowAssignExistingModal(false);
              setSelectedAvailableSiswa([]);
              setAvailableSiswaSearch('');
            }}
          >
            Batal
          </Button>
          <Button
            variant="contained"
            onClick={handleAssignExistingSiswa}
            disabled={availableSiswaLoading || selectedAvailableSiswa.length === 0}
          >
            Masukkan ke Kelas
          </Button>
        </DialogActions>
      </Dialog>

      {/* Context Menu */}
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl) && canOpenActionMenu}
        onClose={handleMenuClose}
        PaperProps={{
          sx: {
            borderRadius: 2,
            minWidth: 200,
            boxShadow: theme.shadows[8]
          }
        }}
      >
        {canManageStudentTransitions && (
          <>
            <MenuItem onClick={() => handleTransisi('naik-kelas')}>
              <ArrowUp size={16} className="mr-2 text-blue-500" />
              Naik Kelas
            </MenuItem>
            <MenuItem onClick={() => handleTransisi('pindah-kelas')}>
              <ArrowRight size={16} className="mr-2 text-orange-500" />
              Pindah Kelas
            </MenuItem>
            <MenuItem onClick={() => handleTransisi('lulus')}>
              <GraduationCap size={16} className="mr-2 text-green-500" />
              Lulus
            </MenuItem>
            <MenuItem onClick={() => handleTransisi('keluar')}>
              <LogOut size={16} className="mr-2 text-red-500" />
              Keluar Sekolah
            </MenuItem>
            <Divider />
            <MenuItem onClick={() => {
              setShowRiwayatTransisiModal(true);
              handleMenuClose({ preserveSelected: true });
            }}>
              <History size={16} className="mr-2 text-blue-500" />
              Riwayat Transisi
            </MenuItem>
          </>
        )}

        {canManageStudents && (
          <>
            {(canManageStudentTransitions || canManageKelas) && <Divider />}
            <MenuItem onClick={() => onEditSiswa(selectedSiswa)}>
              <Edit size={16} className="mr-2 text-gray-500" />
              Edit Siswa
            </MenuItem>
          </>
        )}

        {canManageKelas && (
          <MenuItem 
            onClick={() => {
              onDeleteSiswa(selectedSiswa?.id, selectedSiswa?.nama);
              handleMenuClose();
            }}
            sx={{ color: 'error.main' }}
          >
            <Trash2 size={16} className="mr-2" />
            Nonaktifkan dari Kelas
          </MenuItem>
        )}
      </Menu>

      {/* Transisi Modal */}
      {canManageStudentTransitions && (
        <TransisiSiswaModal
          open={showTransisiModal}
          onClose={() => {
            setShowTransisiModal(false);
            setTransisiType('');
            setSelectedSiswa(null);
          }}
          siswa={selectedSiswa && showTransisiModal ? {
            ...selectedSiswa,
            kelas: {
              id: kelas.id,
              nama: kelas.namaKelas,
              tingkat_id: kelas.tingkat_id,
              tingkat_urutan: getTingkatUrutan(kelas.tingkat_id),
              tingkat: {
              id: kelas.tingkat_id,
              nama: kelas.tingkat,
              urutan: getTingkatUrutan(kelas.tingkat_id)
            },
              waliKelas: kelas.waliKelas,
              tahun_ajaran_id: kelas.tahun_ajaran_id ?? null,
            }
          } : selectedSiswa}
          currentKelas={kelas}
          type={transisiType}
          kelasList={kelasListForTransisi}
          tingkatList={tingkatList}
          tahunAjaranList={tahunAjaranList}
          onSuccess={async (responsePayload) => {
            const selectedId = selectedSiswa?.id;
            const isPendingTransferRequest = transisiType === 'pindah-kelas'
              && String(responsePayload?.data?.status || '').toLowerCase() === 'pending';

            if (!isPendingTransferRequest && selectedId) {
              setSiswaRows((prev) => prev.filter((row) => row.id !== selectedId));
            }

            setShowTransisiModal(false);
            setTransisiType('');
            setSelectedSiswa(null);
            if (!isPendingTransferRequest) {
              await refreshSiswaRows(membershipViewMode);
            }
            if (onRefresh) onRefresh();
          }}
        />
      )}

      {/* Bulk Transisi Modal */}
      {canManageStudents && (
        <BulkTransisiModal
          open={showBulkTransisiModal}
          onClose={() => {
            setShowBulkTransisiModal(false);
            setSelectedSiswaIds([]);
          }}
          selectedSiswa={activeSiswaRows.map(siswa => ({
            ...siswa,
            kelas: {
              id: kelas.id,
              nama: kelas.namaKelas,
              tingkat_id: kelas.tingkat_id,
              tahun_ajaran_id: kelas.tahun_ajaran_id ?? null,
              tingkat_urutan: getTingkatUrutan(kelas.tingkat_id),
              tingkat: {
                id: kelas.tingkat_id,
                nama: kelas.tingkat,
                urutan: getTingkatUrutan(kelas.tingkat_id)
              },
              waliKelas: kelas.waliKelas
            }
          })) || []}
          mode={bulkTransisiMode}
          kelasList={kelasListForTransisi}
          tingkatList={tingkatList}
          tahunAjaranList={tahunAjaranList}
          onSuccess={() => {
            setShowBulkTransisiModal(false);
            setSelectedSiswaIds([]);
            refreshSiswaRows(membershipViewMode);
            if (onRefresh) onRefresh();
          }}
        />
      )}

      {/* Riwayat Transisi Modal */}
      {canManageStudentTransitions && (
        <RiwayatTransisiModal
          open={showRiwayatTransisiModal}
          onClose={() => {
            setShowRiwayatTransisiModal(false);
            setSelectedSiswa(null);
          }}
          siswa={selectedSiswa}
          onRefresh={async () => {
            await refreshSiswaRows(membershipViewMode);
            if (onRefresh) {
              await onRefresh();
            }
          }}
        />
      )}

      {(canManageStudentTransitions || canManagePromotionWindow) && (
        <TransferRequestQueueModal
          open={showTransferQueueModal}
          onClose={() => {
            setShowTransferQueueModal(false);
            refreshSiswaRows(membershipViewMode);
          }}
          currentKelas={kelas}
          onRefresh={onRefresh}
        />
      )}

      {(canManageStudentTransitions || canManagePromotionWindow) && (
        <PromotionWindowSettingModal
          open={showPromotionWindowModal}
          onClose={() => setShowPromotionWindowModal(false)}
          currentKelas={kelas}
          activeTahunAjaran={activeTahunAjaran}
          onRefresh={onRefresh}
        />
      )}
    </>
  );
};

export default ViewSiswaModal;

import React, { useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Box,
  Card,
  CardContent,
  Grid,
  Avatar,
  Chip,
  useTheme,
  useMediaQuery,
  IconButton,
  Alert,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  ToggleButton,
  ToggleButtonGroup,
  LinearProgress
} from '@mui/material';
import {
  BookOpen,
  Users,
  School,
  X,
  GraduationCap,
  MapPin,
  Calendar,
  LayoutGrid,
  List
} from 'lucide-react';

const ViewTingkatKelasModal = ({
  open,
  onClose,
  tingkat,
  kelasList = []
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  const [viewMode, setViewMode] = useState('card'); // 'card' or 'table'

  const getWaliKelasStatus = (waliKelas) => {
    return waliKelas ? 'success' : 'warning';
  };

  const getKapasitasColor = (jumlahSiswa, kapasitas) => {
    const percentage = (jumlahSiswa / kapasitas) * 100;
    if (percentage >= 90) return 'error';
    if (percentage >= 75) return 'warning';
    return 'success';
  };

  if (!tingkat) return null;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      maxWidth="lg"
      fullWidth
      fullScreen={isMobile}
      PaperProps={{
        sx: {
          borderRadius: isMobile ? 0 : 2,
          maxHeight: isMobile ? '100vh' : '90vh'
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
              <GraduationCap size={isMobile ? 20 : 24} />
            </Avatar>
            <Box>
              <Typography variant={isMobile ? "h6" : "h5"} fontWeight="bold">
                Daftar Kelas
              </Typography>
              <Typography variant="body2" sx={{ opacity: 0.9 }}>
                Tingkat {tingkat.nama}
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
        {/* Tingkat Info Card */}
        <Card sx={{ m: isMobile ? 2 : 3, mb: 2 }}>
          <CardContent>
            <Grid container spacing={isMobile ? 2 : 3}>
              <Grid item xs={12} sm={6} md={3}>
                <Box display="flex" alignItems="center" gap={1}>
                  <GraduationCap className="text-blue-500" size={20} />
                  <Box>
                    <Typography variant="caption" color="textSecondary">
                      Tingkat
                    </Typography>
                    <Typography variant="body2" fontWeight="medium">
                      {tingkat.nama}
                    </Typography>
                  </Box>
                </Box>
              </Grid>
              
              <Grid item xs={12} sm={6} md={3}>
                <Box display="flex" alignItems="center" gap={1}>
                  <MapPin className="text-green-500" size={20} />
                  <Box>
                    <Typography variant="caption" color="textSecondary">
                      Kode
                    </Typography>
                    <Typography variant="body2" fontWeight="medium">
                      {tingkat.kode}
                    </Typography>
                  </Box>
                </Box>
              </Grid>
              
              <Grid item xs={12} sm={6} md={3}>
                <Box display="flex" alignItems="center" gap={1}>
                  <BookOpen className="text-purple-500" size={20} />
                  <Box>
                    <Typography variant="caption" color="textSecondary">
                      Jumlah Kelas
                    </Typography>
                    <Typography variant="body2" fontWeight="medium">
                      {kelasList.length}
                    </Typography>
                  </Box>
                </Box>
              </Grid>
              
              <Grid item xs={12} sm={6} md={3}>
                <Box display="flex" alignItems="center" gap={1}>
                  <Users className="text-orange-500" size={20} />
                  <Box>
                    <Typography variant="caption" color="textSecondary">
                      Total Siswa
                    </Typography>
                    <Typography variant="body2" fontWeight="medium">
                      {kelasList.reduce((total, kelas) => total + (kelas.jumlahSiswa || 0), 0)}
                    </Typography>
                  </Box>
                </Box>
              </Grid>
            </Grid>
            
            {tingkat.deskripsi && (
              <Box mt={2}>
                <Typography variant="caption" color="textSecondary">
                  Deskripsi
                </Typography>
                <Typography variant="body2">
                  {tingkat.deskripsi}
                </Typography>
              </Box>
            )}
          </CardContent>
        </Card>

        {/* Classes List */}
        <Box sx={{ px: isMobile ? 2 : 3, pb: 2 }}>
          {/* View Mode Toggle */}
          <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
            <Typography variant="h6" fontWeight="bold">
              Daftar Kelas
            </Typography>
            
            {kelasList.length > 0 && (
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
                  {!isMobile && <Typography variant="caption" sx={{ ml: 1 }}>Card</Typography>}
                </ToggleButton>
                <ToggleButton value="table" aria-label="table view">
                  <List size={16} />
                  {!isMobile && <Typography variant="caption" sx={{ ml: 1 }}>Table</Typography>}
                </ToggleButton>
              </ToggleButtonGroup>
            )}
          </Box>

          {kelasList.length === 0 ? (
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
                Belum ada kelas di tingkat ini
              </Typography>
            </Alert>
          ) : viewMode === 'table' ? (
            <TableContainer component={Paper} sx={{ borderRadius: 2, boxShadow: theme.shadows[2] }}>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell width="50">#</TableCell>
                    <TableCell>Nama Kelas</TableCell>
                    <TableCell>Wali Kelas</TableCell>
                    <TableCell>Jumlah Siswa</TableCell>
                    <TableCell>Kapasitas</TableCell>
                    <TableCell>Persentase</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {kelasList.map((kelas, index) => (
                    <TableRow key={kelas.id} hover>
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
                            <BookOpen size={16} />
                          </Avatar>
                          <Typography variant="body2" fontWeight="medium">
                            {kelas.namaKelas}
                          </Typography>
                        </Box>
                      </TableCell>
                      <TableCell>
                        <Chip
                          label={kelas.waliKelas || 'Belum ditentukan'}
                          color={getWaliKelasStatus(kelas.waliKelas)}
                          size="small"
                          variant="outlined"
                          sx={{ fontSize: '0.75rem' }}
                        />
                      </TableCell>
                      <TableCell>{kelas.jumlahSiswa || 0}</TableCell>
                      <TableCell>{kelas.kapasitas || 0}</TableCell>
                      <TableCell>
                        <Box display="flex" alignItems="center" gap={1}>
                          <LinearProgress
                            variant="determinate"
                            value={Math.min(((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100, 100)}
                            color={getKapasitasColor(kelas.jumlahSiswa || 0, kelas.kapasitas || 1)}
                            sx={{ width: 60, height: 6, borderRadius: 3 }}
                          />
                          <Typography variant="caption" color="textSecondary">
                            {Math.round(((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100)}%
                          </Typography>
                        </Box>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          ) : (
            <Grid container spacing={isMobile ? 1 : 2}>
              {kelasList.map((kelas) => (
                <Grid item xs={12} sm={6} lg={4} key={kelas.id}>
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
                      {/* Header */}
                      <Box display="flex" alignItems="center" justifyContent="space-between" mb={2}>
                        <Box display="flex" alignItems="center" gap={2}>
                          <Avatar
                            sx={{
                              bgcolor: theme.palette.primary.main,
                              width: isMobile ? 36 : 40,
                              height: isMobile ? 36 : 40,
                              fontSize: isMobile ? '0.8rem' : '0.9rem'
                            }}
                          >
                            <BookOpen size={isMobile ? 16 : 18} />
                          </Avatar>
                          <Box>
                            <Typography variant="subtitle2" fontWeight="bold">
                              {kelas.namaKelas}
                            </Typography>
                            <Typography variant="caption" color="textSecondary">
                              Kelas {kelas.namaKelas}
                            </Typography>
                          </Box>
                        </Box>
                        
                        <Chip
                          label={`${kelas.jumlahSiswa || 0}/${kelas.kapasitas || 0}`}
                          color={getKapasitasColor(kelas.jumlahSiswa || 0, kelas.kapasitas || 1)}
                          size="small"
                          sx={{ fontSize: '0.75rem' }}
                        />
                      </Box>

                      {/* Wali Kelas */}
                      <Box mb={2}>
                        <Box display="flex" alignItems="center" gap={1} mb={1}>
                          <School size={14} className="text-gray-400" />
                          <Typography variant="caption" color="textSecondary">
                            Wali Kelas
                          </Typography>
                        </Box>
                        <Box display="flex" alignItems="center" gap={1}>
                          <Chip
                            label={kelas.waliKelas || 'Belum ditentukan'}
                            color={getWaliKelasStatus(kelas.waliKelas)}
                            size="small"
                            variant="outlined"
                            sx={{ fontSize: '0.7rem' }}
                          />
                        </Box>
                      </Box>

                      {/* Additional Info */}
                      <Grid container spacing={1}>
                        <Grid item xs={6}>
                          <Box display="flex" alignItems="center" gap={1}>
                            <Users size={12} className="text-gray-400" />
                            <Typography variant="caption" color="textSecondary">
                              {kelas.jumlahSiswa || 0} siswa
                            </Typography>
                          </Box>
                        </Grid>
                        <Grid item xs={6}>
                          <Box display="flex" alignItems="center" gap={1}>
                            <Calendar size={12} className="text-gray-400" />
                            <Typography variant="caption" color="textSecondary">
                              {kelas.tahunAjaran || 'N/A'}
                            </Typography>
                          </Box>
                        </Grid>
                      </Grid>

                      {/* Progress Bar */}
                      <Box mt={2}>
                        <Box display="flex" justifyContent="space-between" alignItems="center" mb={0.5}>
                          <Typography variant="caption" color="textSecondary">
                            Kapasitas
                          </Typography>
                          <Typography variant="caption" color="textSecondary">
                            {Math.round(((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100)}%
                          </Typography>
                        </Box>
                        <Box
                          sx={{
                            width: '100%',
                            height: 6,
                            bgcolor: 'grey.200',
                            borderRadius: 3,
                            overflow: 'hidden'
                          }}
                        >
                          <Box
                            sx={{
                              width: `${Math.min(((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100, 100)}%`,
                              height: '100%',
                              bgcolor: getKapasitasColor(kelas.jumlahSiswa || 0, kelas.kapasitas || 1) === 'error' 
                                ? 'error.main' 
                                : getKapasitasColor(kelas.jumlahSiswa || 0, kelas.kapasitas || 1) === 'warning'
                                ? 'warning.main'
                                : 'success.main',
                              borderRadius: 3,
                              transition: 'width 0.3s ease'
                            }}
                          />
                        </Box>
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
      <DialogActions sx={{ p: isMobile ? 2 : 3 }}>
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

export default ViewTingkatKelasModal;

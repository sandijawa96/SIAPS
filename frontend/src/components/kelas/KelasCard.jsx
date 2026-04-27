import React from 'react';
import {
  Card,
  CardContent,
  Typography,
  Box,
  Chip,
  IconButton,
  Button,
  Grid,
  Avatar,
  useTheme,
  useMediaQuery,
  Tooltip,
  LinearProgress
} from '@mui/material';
import {
  Edit,
  Trash2,
  Eye,
  Users,
  BookOpen,
  School,
  Calendar,
  MapPin
} from 'lucide-react';

const KelasCard = ({ kelas, onEdit, onDelete, onViewSiswa, canManageKelas = true }) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const isTablet = useMediaQuery(theme.breakpoints.down('md'));

  const getKapasitasColor = () => {
    const percentage = ((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100;
    if (percentage >= 90) return 'error';
    if (percentage >= 75) return 'warning';
    return 'success';
  };

  const getKapasitasPercentage = () => {
    return Math.min(((kelas.jumlahSiswa || 0) / (kelas.kapasitas || 1)) * 100, 100);
  };

  return (
    <Card
      sx={{
        borderRadius: 2,
        transition: 'all 0.3s ease-in-out',
        '&:hover': {
          transform: 'translateY(-4px)',
          boxShadow: theme.shadows[8]
        },
        border: `1px solid ${theme.palette.divider}`,
        overflow: 'visible'
      }}
    >
      <CardContent sx={{ p: isMobile ? 2 : 3 }}>
        {/* Header */}
        <Box display="flex" justifyContent="space-between" alignItems="flex-start" mb={2}>
          <Box display="flex" alignItems="center" gap={2} flex={1}>
            <Avatar
              sx={{
                bgcolor: theme.palette.secondary.main,
                width: isMobile ? 40 : 48,
                height: isMobile ? 40 : 48
              }}
            >
              <BookOpen size={isMobile ? 20 : 24} />
            </Avatar>
            <Box flex={1} minWidth={0}>
              <Typography 
                variant={isMobile ? "subtitle1" : "h6"} 
                fontWeight="bold"
                noWrap
                title={kelas.namaKelas}
              >
                {kelas.namaKelas}
              </Typography>
              <Typography variant="caption" color="textSecondary">
                {kelas.tingkat || 'Tingkat tidak diketahui'}
              </Typography>
            </Box>
          </Box>
          
          {canManageKelas && (
            <Box display="flex" gap={0.5}>
              <Tooltip title="Edit Kelas">
                <IconButton
                  size="small"
                  onClick={() => onEdit(kelas)}
                  sx={{
                    color: 'primary.main',
                    '&:hover': {
                      bgcolor: 'primary.50'
                    }
                  }}
                >
                  <Edit size={16} />
                </IconButton>
              </Tooltip>
              <Tooltip title="Hapus Kelas">
                <IconButton
                  size="small"
                  onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onDelete(kelas.id, kelas.namaKelas);
                  }}
                  sx={{
                    color: 'error.main',
                    '&:hover': {
                      bgcolor: 'error.50'
                    },
                    zIndex: 10
                  }}
                >
                  <Trash2 size={16} />
                </IconButton>
              </Tooltip>
            </Box>
          )}
        </Box>

        {/* Wali Kelas */}
        <Box mb={2}>
          <Box display="flex" alignItems="center" gap={1} mb={1}>
            <School size={14} className="text-gray-400" />
            <Typography variant="caption" color="textSecondary">
              Wali Kelas
            </Typography>
          </Box>
          <Chip
            label={kelas.waliKelas || 'Belum ditentukan'}
            color={kelas.waliKelas ? 'success' : 'warning'}
            size="small"
            variant="outlined"
            sx={{ fontSize: '0.75rem' }}
          />
        </Box>

        {/* Kapasitas */}
        <Box mb={2}>
          <Box display="flex" justifyContent="space-between" alignItems="center" mb={1}>
            <Box display="flex" alignItems="center" gap={1}>
              <Users size={14} className="text-gray-400" />
              <Typography variant="caption" color="textSecondary">
                Kapasitas
              </Typography>
            </Box>
            <Typography variant="caption" fontWeight="medium">
              {kelas.jumlahSiswa || 0}/{kelas.kapasitas || 0}
            </Typography>
          </Box>
          
          <LinearProgress
            variant="determinate"
            value={getKapasitasPercentage()}
            color={getKapasitasColor()}
            sx={{
              height: 6,
              borderRadius: 3,
              bgcolor: 'grey.200'
            }}
          />
          
          <Typography 
            variant="caption" 
            color="textSecondary" 
            sx={{ mt: 0.5, display: 'block' }}
          >
            {Math.round(getKapasitasPercentage())}% terisi
          </Typography>
        </Box>

        {/* Additional Info */}
        <Grid container spacing={1} sx={{ mb: 2 }}>
          <Grid item xs={6}>
            <Box display="flex" alignItems="center" gap={1}>
              <Calendar size={12} className="text-gray-400" />
              <Typography variant="caption" color="textSecondary" noWrap>
                {kelas.tahunAjaran || 'N/A'}
              </Typography>
            </Box>
          </Grid>
          <Grid item xs={6}>
            <Box display="flex" alignItems="center" gap={1}>
              <MapPin size={12} className="text-gray-400" />
              <Typography variant="caption" color="textSecondary" noWrap>
                {kelas.tahunAjaranSemesterLabel || 'Ganjil & Genap'}
              </Typography>
            </Box>
          </Grid>
        </Grid>

        {/* Keterangan */}
        {kelas.keterangan && !isMobile && (
          <Typography 
            variant="body2" 
            color="textSecondary" 
            sx={{ 
              mb: 2,
              display: '-webkit-box',
              WebkitLineClamp: 2,
              WebkitBoxOrient: 'vertical',
              overflow: 'hidden'
            }}
          >
            {kelas.keterangan}
          </Typography>
        )}

        {/* Footer Actions */}
        <Box display="flex" justifyContent="space-between" alignItems="center" gap={1}>
          <Chip
            label={`${kelas.jumlahSiswa || 0} Siswa`}
            color={getKapasitasColor()}
            size="small"
            icon={<Users size={12} />}
            sx={{ 
              fontSize: '0.75rem',
              fontWeight: 'medium'
            }}
          />
          
          <Button
            size="small"
            variant="contained"
            startIcon={<Eye size={14} />}
            onClick={() => onViewSiswa(kelas)}
            sx={{
              borderRadius: 2,
              textTransform: 'none',
              fontSize: '0.8rem',
              px: isMobile ? 1 : 2
            }}
          >
            {isMobile ? 'Lihat' : 'Lihat Siswa'}
          </Button>
        </Box>

        {/* Status Indicators for Mobile */}
        {isMobile && (
          <Box mt={2} display="flex" flexWrap="wrap" gap={0.5}>
            {kelas.waliKelas && (
              <Chip
                label="Ada Wali Kelas"
                size="small"
                color="success"
                variant="outlined"
                sx={{ fontSize: '0.7rem' }}
              />
            )}
            {kelas.keterangan && (
              <Chip
                label="Ada Keterangan"
                size="small"
                variant="outlined"
                sx={{ fontSize: '0.7rem' }}
              />
            )}
          </Box>
        )}
      </CardContent>
    </Card>
  );
};

export default KelasCard;

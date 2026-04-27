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
  Tooltip
} from '@mui/material';
import {
  Edit,
  Trash2,
  Eye,
  Users,
  BookOpen,
  GraduationCap
} from 'lucide-react';

const TingkatCard = ({ tingkat, onEdit, onDelete, onViewKelas, canManageKelas = true }) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const isTablet = useMediaQuery(theme.breakpoints.down('md'));

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
                bgcolor: theme.palette.primary.main,
                width: isMobile ? 40 : 48,
                height: isMobile ? 40 : 48
              }}
            >
              <GraduationCap size={isMobile ? 20 : 24} />
            </Avatar>
            <Box flex={1} minWidth={0}>
              <Typography 
                variant={isMobile ? "subtitle1" : "h6"} 
                fontWeight="bold"
                noWrap
                title={tingkat.nama}
              >
                {tingkat.nama}
              </Typography>
              <Typography variant="caption" color="textSecondary">
                Kode: {tingkat.kode}
              </Typography>
            </Box>
          </Box>
          
          {canManageKelas && (
            <Box display="flex" gap={0.5}>
              <Tooltip title="Edit Tingkat">
                <IconButton
                  size="small"
                  onClick={() => onEdit(tingkat)}
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
              <Tooltip title="Hapus Tingkat">
                <IconButton
                  size="small"
                  onClick={() => onDelete(tingkat.id, tingkat.nama)}
                  sx={{
                    color: 'error.main',
                    '&:hover': {
                      bgcolor: 'error.50'
                    }
                  }}
                >
                  <Trash2 size={16} />
                </IconButton>
              </Tooltip>
            </Box>
          )}
        </Box>

        {/* Description */}
        {tingkat.deskripsi && (
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
            {tingkat.deskripsi}
          </Typography>
        )}

        {/* Stats */}
        <Grid container spacing={1} sx={{ mb: 2 }}>
          <Grid item xs={6}>
            <Box
              sx={{
                textAlign: 'center',
                p: isMobile ? 1.5 : 2,
                bgcolor: 'primary.50',
                borderRadius: 2,
                border: `1px solid ${theme.palette.primary.main}20`
              }}
            >
              <Box display="flex" alignItems="center" justifyContent="center" gap={0.5} mb={0.5}>
                <BookOpen size={isMobile ? 16 : 20} className="text-blue-600" />
                <Typography 
                  variant={isMobile ? "h6" : "h5"} 
                  fontWeight="bold" 
                  color="primary.main"
                >
                  {tingkat.jumlah_kelas || 0}
                </Typography>
              </Box>
              <Typography variant="caption" color="primary.main" fontWeight="medium">
                Kelas
              </Typography>
            </Box>
          </Grid>
          
          <Grid item xs={6}>
            <Box
              sx={{
                textAlign: 'center',
                p: isMobile ? 1.5 : 2,
                bgcolor: 'success.50',
                borderRadius: 2,
                border: `1px solid ${theme.palette.success.main}20`
              }}
            >
              <Box display="flex" alignItems="center" justifyContent="center" gap={0.5} mb={0.5}>
                <Users size={isMobile ? 16 : 20} className="text-green-600" />
                <Typography 
                  variant={isMobile ? "h6" : "h5"} 
                  fontWeight="bold" 
                  color="success.main"
                >
                  {tingkat.jumlah_siswa || 0}
                </Typography>
              </Box>
              <Typography variant="caption" color="success.main" fontWeight="medium">
                Siswa
              </Typography>
            </Box>
          </Grid>
        </Grid>

        {/* Additional Info */}
        {!isMobile && (
          <Box mb={2}>
            <Grid container spacing={1}>
              <Grid item xs={6}>
                <Typography variant="caption" color="textSecondary">
                  Urutan: {tingkat.urutan || '-'}
                </Typography>
              </Grid>
              <Grid item xs={6}>
                <Typography variant="caption" color="textSecondary">
                  Status: {tingkat.is_active ? 'Aktif' : 'Tidak Aktif'}
                </Typography>
              </Grid>
            </Grid>
          </Box>
        )}

        {/* Footer Actions */}
        <Box display="flex" justifyContent="space-between" alignItems="center" gap={1}>
          <Chip
            label={tingkat.is_active ? 'Aktif' : 'Tidak Aktif'}
            color={tingkat.is_active ? 'success' : 'default'}
            size="small"
            sx={{ 
              fontSize: '0.75rem',
              fontWeight: 'medium'
            }}
          />
          
          <Button
            size="small"
            variant="outlined"
            startIcon={<Eye size={14} />}
            onClick={() => onViewKelas(tingkat)}
            sx={{
              borderRadius: 2,
              textTransform: 'none',
              fontSize: '0.8rem',
              px: isMobile ? 1 : 2
            }}
          >
            {isMobile ? 'Lihat' : 'Lihat Kelas'}
          </Button>
        </Box>

        {/* Status Indicators for Mobile */}
        {isMobile && (
          <Box mt={2} display="flex" flexWrap="wrap" gap={0.5}>
            {tingkat.jumlah_kelas > 0 && (
              <Chip
                label={`${tingkat.jumlah_kelas} Kelas`}
                size="small"
                sx={{ fontSize: '0.7rem' }}
              />
            )}
            {tingkat.jumlah_siswa > 0 && (
              <Chip
                icon={<Users size={12} />}
                label={`${tingkat.jumlah_siswa} Siswa`}
                size="small"
                color="success"
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

export default TingkatCard;

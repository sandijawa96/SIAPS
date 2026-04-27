import React, { useState } from 'react';
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
  Accordion,
  AccordionSummary,
  AccordionDetails,
  LinearProgress
} from '@mui/material';
import {
  AlertTriangle,
  X,
  RefreshCw,
  CheckCircle,
  XCircle,
  Clock,
  User,
  ChevronDown,
  Download,
  Copy
} from 'lucide-react';
import toast from 'react-hot-toast';
import { getServerDateString, getServerIsoString } from '../../services/serverClock';

const ErrorHandlingModal = ({
  open,
  onClose,
  errors = [],
  successCount = 0,
  totalCount = 0,
  operationType = 'transisi',
  onRetry,
  retrying = false
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const [expandedError, setExpandedError] = useState(null);

  const getOperationTitle = () => {
    switch (operationType) {
      case 'naik-kelas':
        return 'Naik Kelas';
      case 'lulus':
        return 'Kelulusan';
      case 'keluar':
        return 'Keluar Sekolah';
      case 'pindah-kelas':
        return 'Pindah Kelas';
      default:
        return 'Transisi Siswa';
    }
  };

  const getErrorSeverity = (error) => {
    if (error.type === 'validation') return 'warning';
    if (error.type === 'network') return 'error';
    if (error.type === 'server') return 'error';
    return 'info';
  };

  const getErrorIcon = (error) => {
    switch (error.type) {
      case 'validation':
        return <AlertTriangle className="text-orange-500" size={20} />;
      case 'network':
        return <XCircle className="text-red-500" size={20} />;
      case 'server':
        return <XCircle className="text-red-500" size={20} />;
      default:
        return <AlertTriangle className="text-gray-500" size={20} />;
    }
  };

  const handleCopyErrorLog = () => {
    const errorLog = errors.map(error => 
      `${error.siswa?.nama || 'Unknown'} (${error.siswa?.nis || 'No NIS'}): ${error.message}`
    ).join('\n');
    
    navigator.clipboard.writeText(errorLog).then(() => {
      toast.success('Error log berhasil disalin');
    }).catch(() => {
      toast.error('Gagal menyalin error log');
    });
  };

  const handleDownloadErrorReport = () => {
    const report = {
      timestamp: getServerIsoString(),
      operation: operationType,
      summary: {
        total: totalCount,
        success: successCount,
        failed: errors.length
      },
      errors: errors.map(error => ({
        siswa: {
          nama: error.siswa?.nama || 'Unknown',
          nis: error.siswa?.nis || 'No NIS',
          nisn: error.siswa?.nisn || 'No NISN'
        },
        error: {
          type: error.type,
          message: error.message,
          details: error.details
        }
      }))
    };

    const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `error-report-${operationType}-${getServerDateString()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    toast.success('Error report berhasil diunduh');
  };

  const failedCount = errors.length;
  const successRate = totalCount > 0 ? (successCount / totalCount) * 100 : 0;

  if (!open) return null;

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
            <AlertTriangle className="text-orange-500" size={24} />
            <Typography variant="h6" component="span">
              Laporan {getOperationTitle()}
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
        {/* Summary */}
        <Box mb={3}>
          <Typography variant="h6" gutterBottom>
            Ringkasan Operasi
          </Typography>
          
          <Box display="flex" gap={2} mb={2}>
            <Chip
              icon={<CheckCircle size={16} />}
              label={`${successCount} Berhasil`}
              color="success"
              variant="outlined"
            />
            <Chip
              icon={<XCircle size={16} />}
              label={`${failedCount} Gagal`}
              color="error"
              variant="outlined"
            />
            <Chip
              icon={<User size={16} />}
              label={`${totalCount} Total`}
              color="primary"
              variant="outlined"
            />
          </Box>

          {/* Progress Bar */}
          <Box mb={2}>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={1}>
              <Typography variant="body2" color="textSecondary">
                Tingkat Keberhasilan
              </Typography>
              <Typography variant="body2" fontWeight="medium">
                {successRate.toFixed(1)}%
              </Typography>
            </Box>
            <LinearProgress
              variant="determinate"
              value={successRate}
              color={successRate >= 80 ? 'success' : successRate >= 50 ? 'warning' : 'error'}
              sx={{ height: 8, borderRadius: 4 }}
            />
          </Box>

          {successCount > 0 && (
            <Alert severity="success" sx={{ mb: 2 }}>
              <AlertTitle>Operasi Berhasil</AlertTitle>
              {successCount} siswa berhasil diproses untuk {getOperationTitle().toLowerCase()}
            </Alert>
          )}
        </Box>

        {/* Error Details */}
        {errors.length > 0 && (
          <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
              <Typography variant="h6">
                Detail Kesalahan ({errors.length})
              </Typography>
              <Box display="flex" gap={1}>
                <Button
                  size="small"
                  startIcon={<Copy size={16} />}
                  onClick={handleCopyErrorLog}
                  variant="outlined"
                >
                  Salin Log
                </Button>
                <Button
                  size="small"
                  startIcon={<Download size={16} />}
                  onClick={handleDownloadErrorReport}
                  variant="outlined"
                >
                  Unduh Report
                </Button>
              </Box>
            </Box>

            <List sx={{ maxHeight: 400, overflow: 'auto' }}>
              {errors.map((error, index) => (
                <React.Fragment key={index}>
                  <Accordion
                    expanded={expandedError === index}
                    onChange={(e, isExpanded) => setExpandedError(isExpanded ? index : null)}
                    sx={{ boxShadow: 'none', border: '1px solid', borderColor: 'divider' }}
                  >
                    <AccordionSummary expandIcon={<ChevronDown size={20} />}>
                      <Box display="flex" alignItems="center" gap={2} width="100%">
                        {getErrorIcon(error)}
                        <Box flex={1}>
                          <Typography variant="subtitle2" fontWeight="medium">
                            {error.siswa?.nama || 'Siswa Tidak Dikenal'}
                          </Typography>
                          <Typography variant="caption" color="textSecondary">
                            NIS: {error.siswa?.nis || '-'} | NISN: {error.siswa?.nisn || '-'}
                          </Typography>
                        </Box>
                        <Chip
                          label={error.type || 'error'}
                          size="small"
                          color={getErrorSeverity(error)}
                          sx={{ textTransform: 'capitalize' }}
                        />
                      </Box>
                    </AccordionSummary>
                    <AccordionDetails>
                      <Alert severity={getErrorSeverity(error)} sx={{ mb: 2 }}>
                        <AlertTitle>Pesan Error</AlertTitle>
                        {error.message}
                      </Alert>
                      
                      {error.details && (
                        <Box>
                          <Typography variant="subtitle2" gutterBottom>
                            Detail Teknis:
                          </Typography>
                          <Box
                            component="pre"
                            sx={{
                              backgroundColor: 'grey.100',
                              p: 2,
                              borderRadius: 1,
                              fontSize: '0.75rem',
                              overflow: 'auto',
                              maxHeight: 200
                            }}
                          >
                            {JSON.stringify(error.details, null, 2)}
                          </Box>
                        </Box>
                      )}

                      {error.suggestions && error.suggestions.length > 0 && (
                        <Box mt={2}>
                          <Typography variant="subtitle2" gutterBottom>
                            Saran Perbaikan:
                          </Typography>
                          <List dense>
                            {error.suggestions.map((suggestion, idx) => (
                              <ListItem key={idx} sx={{ pl: 0 }}>
                                <ListItemIcon sx={{ minWidth: 24 }}>
                                  <Box
                                    sx={{
                                      width: 6,
                                      height: 6,
                                      borderRadius: '50%',
                                      backgroundColor: 'primary.main'
                                    }}
                                  />
                                </ListItemIcon>
                                <ListItemText
                                  primary={suggestion}
                                  primaryTypographyProps={{ variant: 'body2' }}
                                />
                              </ListItem>
                            ))}
                          </List>
                        </Box>
                      )}
                    </AccordionDetails>
                  </Accordion>
                  {index < errors.length - 1 && <Divider />}
                </React.Fragment>
              ))}
            </List>
          </Box>
        )}

        {/* Retry Information */}
        {errors.length > 0 && onRetry && (
          <Alert severity="info" sx={{ mt: 3 }}>
            <AlertTitle>Opsi Retry</AlertTitle>
            Anda dapat mencoba ulang operasi untuk siswa yang gagal diproses. 
            Sistem akan otomatis melewati siswa yang sudah berhasil diproses sebelumnya.
          </Alert>
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
        
        {errors.length > 0 && onRetry && (
          <Button
            onClick={onRetry}
            variant="contained"
            disabled={retrying}
            startIcon={retrying ? <RefreshCw className="animate-spin" size={16} /> : <RefreshCw size={16} />}
            fullWidth={isMobile}
            color="warning"
          >
            {retrying ? 'Mencoba Ulang...' : `Retry ${errors.length} Siswa`}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default ErrorHandlingModal;

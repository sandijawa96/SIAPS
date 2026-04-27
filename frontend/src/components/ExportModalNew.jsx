import React, { useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Box,
  Typography,
  Paper,
  LinearProgress,
  Alert,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  FormControlLabel,
  Checkbox,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  IconButton,
  Chip
} from '@mui/material';
import {
  Download,
  FileSpreadsheet,
  X,
  CheckCircle,
  Settings,
  Filter
} from 'lucide-react';

const ExportModal = ({ 
  isOpen, 
  onClose, 
  onExport, 
  userType,
  progress = 0 
}) => {
  const [state, setState] = useState({
    exporting: false,
    exportResult: null,
    format: 'xlsx',
    includeInactive: false,
    selectedFields: [],
    dateRange: 'all'
  });

  const updateState = (updates) => {
    setState(prev => ({ ...prev, ...updates }));
  };

  // Available fields for export
  const availableFields = userType === 'Pegawai' ? [
    { id: 'nama_lengkap', label: 'Nama Lengkap', default: true },
    { id: 'nip', label: 'NIP', default: true },
    { id: 'email', label: 'Email', default: true },
    { id: 'roles', label: 'Role', default: true },
    { id: 'status_kepegawaian', label: 'Status Kepegawaian', default: false },
    { id: 'is_active', label: 'Status Aktif', default: false },
    { id: 'created_at', label: 'Tanggal Dibuat', default: false }
  ] : [
    { id: 'nama_lengkap', label: 'Nama Lengkap', default: true },
    { id: 'nis', label: 'NIS', default: true },
    { id: 'email', label: 'Email', default: true },
    { id: 'kelas', label: 'Kelas', default: true },
    { id: 'tanggal_lahir', label: 'Tanggal Lahir', default: false },
    { id: 'jenis_kelamin', label: 'Jenis Kelamin', default: false },
    { id: 'is_active', label: 'Status Aktif', default: false },
    { id: 'created_at', label: 'Tanggal Dibuat', default: false }
  ];

  // Initialize selected fields with defaults
  React.useEffect(() => {
    if (state.selectedFields.length === 0) {
      const defaultFields = availableFields
        .filter(field => field.default)
        .map(field => field.id);
      updateState({ selectedFields: defaultFields });
    }
  }, [availableFields, state.selectedFields.length]);

  const handleExport = async () => {
    updateState({ exporting: true, exportResult: null });

    try {
      await onExport({
        format: state.format,
        includeInactive: state.includeInactive,
        fields: state.selectedFields,
        dateRange: state.dateRange
      });
      
      updateState({ 
        exportResult: {
          success: true,
          message: `Data ${userType} berhasil diekspor`
        },
        exporting: false
      });

      setTimeout(() => {
        handleClose();
      }, 2000);
    } catch (error) {
      console.error('Export error:', error);
      updateState({
        exporting: false,
        exportResult: {
          success: false,
          message: error.message || 'Gagal mengekspor data'
        }
      });
    }
  };

  const handleClose = () => {
    updateState({
      exporting: false,
      exportResult: null,
      format: 'xlsx',
      includeInactive: false,
      selectedFields: [],
      dateRange: 'all'
    });
    onClose();
  };

  const handleFieldToggle = (fieldId) => {
    updateState({
      selectedFields: state.selectedFields.includes(fieldId)
        ? state.selectedFields.filter(id => id !== fieldId)
        : [...state.selectedFields, fieldId]
    });
  };

  const selectAllFields = () => {
    updateState({
      selectedFields: availableFields.map(field => field.id)
    });
  };

  const selectDefaultFields = () => {
    updateState({
      selectedFields: availableFields
        .filter(field => field.default)
        .map(field => field.id)
    });
  };

  return (
    <Dialog 
      open={isOpen} 
      onClose={handleClose}
      maxWidth="md"
      fullWidth
      PaperProps={{
        className: "rounded-2xl"
      }}
    >
      <DialogTitle className="bg-gradient-to-r from-green-600 to-emerald-700 text-white">
        <Box className="flex items-center justify-between">
          <Box className="flex items-center gap-3">
            <Download className="w-6 h-6" />
            <Box>
              <Typography variant="h6" className="font-bold">
                Export Data {userType}
              </Typography>
              <Typography variant="body2" className="opacity-90">
                Download data dalam format Excel
              </Typography>
            </Box>
          </Box>
          <IconButton onClick={handleClose} className="text-white">
            <X />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent className="p-6">
        {/* Export Options */}
        <Paper className="p-4 mb-6 border border-gray-200">
          <Box className="flex items-center gap-2 mb-3">
            <Settings className="w-5 h-5 text-gray-600" />
            <Typography variant="subtitle2" className="font-semibold">
              Opsi Export
            </Typography>
          </Box>
          
          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <FormControl size="small" fullWidth>
              <InputLabel>Format File</InputLabel>
              <Select
                value={state.format}
                onChange={(e) => updateState({ format: e.target.value })}
                label="Format File"
              >
                <MenuItem value="xlsx">Excel (.xlsx)</MenuItem>
                <MenuItem value="csv">CSV (.csv)</MenuItem>
              </Select>
            </FormControl>

            <FormControl size="small" fullWidth>
              <InputLabel>Rentang Data</InputLabel>
              <Select
                value={state.dateRange}
                onChange={(e) => updateState({ dateRange: e.target.value })}
                label="Rentang Data"
              >
                <MenuItem value="all">Semua Data</MenuItem>
                <MenuItem value="active">Hanya Data Aktif</MenuItem>
                <MenuItem value="this_month">Bulan Ini</MenuItem>
                <MenuItem value="this_year">Tahun Ini</MenuItem>
              </Select>
            </FormControl>
          </Box>

          <FormControlLabel
            control={
              <Checkbox
                checked={state.includeInactive}
                onChange={(e) => updateState({ includeInactive: e.target.checked })}
                color="primary"
              />
            }
            label="Sertakan data non-aktif"
          />
        </Paper>

        {/* Field Selection */}
        <Paper className="p-4 mb-6 border border-gray-200">
          <Box className="flex items-center justify-between mb-3">
            <Box className="flex items-center gap-2">
              <Filter className="w-5 h-5 text-gray-600" />
              <Typography variant="subtitle2" className="font-semibold">
                Pilih Kolom untuk Diekspor
              </Typography>
            </Box>
            <Box className="flex gap-2">
              <Button 
                size="small" 
                variant="outlined" 
                onClick={selectDefaultFields}
              >
                Default
              </Button>
              <Button 
                size="small" 
                variant="outlined" 
                onClick={selectAllFields}
              >
                Pilih Semua
              </Button>
            </Box>
          </Box>

          <Box className="mb-3">
            <Chip 
              label={`${state.selectedFields.length} dari ${availableFields.length} kolom dipilih`}
              color="primary"
              variant="outlined"
              size="small"
            />
          </Box>

          <List dense className="max-h-48 overflow-y-auto">
            {availableFields.map((field) => (
              <ListItem 
                key={field.id}
                button
                onClick={() => handleFieldToggle(field.id)}
                className="hover:bg-gray-50 rounded"
              >
                <ListItemIcon>
                  <Checkbox
                    checked={state.selectedFields.includes(field.id)}
                    tabIndex={-1}
                    disableRipple
                    color="primary"
                  />
                </ListItemIcon>
                <ListItemText 
                  primary={field.label}
                  secondary={field.default ? 'Default' : 'Opsional'}
                />
                {field.default && (
                  <Chip 
                    label="Default" 
                    size="small" 
                    color="primary" 
                    variant="outlined"
                  />
                )}
              </ListItem>
            ))}
          </List>
        </Paper>

        {/* Progress Bar */}
        {state.exporting && (
          <Box className="mb-4">
            <Box className="flex justify-between items-center mb-2">
              <Typography variant="body2">Mengekspor data...</Typography>
              <Typography variant="body2">{progress}%</Typography>
            </Box>
            <LinearProgress 
              variant="determinate" 
              value={progress} 
              className="h-2 rounded"
            />
          </Box>
        )}

        {/* Export Result */}
        {state.exportResult && (
          <Alert 
            severity={state.exportResult.success ? 'success' : 'error'}
            className="mb-4"
            icon={state.exportResult.success ? <CheckCircle /> : undefined}
          >
            <Typography variant="subtitle2" className="font-semibold mb-1">
              {state.exportResult.success ? 'Export Berhasil!' : 'Export Gagal!'}
            </Typography>
            <Typography variant="body2">
              {state.exportResult.message}
            </Typography>
          </Alert>
        )}

        {/* Export Info */}
        <Paper className="p-4 bg-blue-50 border border-blue-200">
          <Box className="flex items-start gap-3">
            <FileSpreadsheet className="w-5 h-5 text-blue-600 mt-1" />
            <Box>
              <Typography variant="subtitle2" className="font-semibold text-blue-900 mb-2">
                Informasi Export
              </Typography>
              <List dense>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText 
                    primary="File akan didownload otomatis setelah proses selesai"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText 
                    primary="Data akan diformat sesuai dengan template standar"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText 
                    primary="File dapat langsung digunakan untuk import kembali"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
              </List>
            </Box>
          </Box>
        </Paper>
      </DialogContent>

      <DialogActions className="p-6 pt-0">
        <Button onClick={handleClose} color="inherit">
          Tutup
        </Button>
        <Button
          onClick={handleExport}
          variant="contained"
          disabled={state.exporting || state.selectedFields.length === 0}
          startIcon={<Download className="w-4 h-4" />}
          className="bg-green-600 hover:bg-green-700"
        >
          {state.exporting ? 'Mengekspor...' : 'Export Data'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ExportModal;

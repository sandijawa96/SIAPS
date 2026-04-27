import React, { useState, useCallback } from 'react';
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
  Chip,
  Divider,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  FormControlLabel,
  Switch,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  IconButton
} from '@mui/material';
import {
  Upload,
  FileText,
  Download,
  CheckCircle,
  AlertCircle,
  X,
  Cloud
} from 'lucide-react';
import { useDropzone } from 'react-dropzone';
import pegawaiService from '../services/pegawaiService';
import siswaService from '../services/siswaService';

const ImportModal = ({ 
  isOpen, 
  onClose, 
  onSuccess, 
  userType, 
  onImport,
  progress = 0 
}) => {
  const [state, setState] = useState({
    selectedFile: null,
    importing: false,
    importResult: null,
    importMode: 'auto',
    updateMode: 'partial',
    skipDuplicates: false
  });

  const updateState = (updates) => {
    setState(prev => ({ ...prev, ...updates }));
  };

  // Dropzone configuration with useCallback for performance
  const onDrop = useCallback((acceptedFiles) => {
    if (acceptedFiles.length > 0) {
      updateState({ 
        selectedFile: acceptedFiles[0], 
        importResult: null 
      });
    }
  }, []);

  const onDropRejected = useCallback((fileRejections) => {
    const error = fileRejections[0]?.errors[0];
    if (error?.code === 'file-too-large') {
      alert('File terlalu besar. Maksimal 2MB.');
    } else if (error?.code === 'file-invalid-type') {
      alert('Format file tidak didukung. Gunakan .xlsx atau .xls');
    }
  }, []);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    accept: {
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
      'application/vnd.ms-excel': ['.xls']
    },
    maxSize: 2 * 1024 * 1024, // 2MB
    disabled: state.importing,
    onDrop,
    onDropRejected
  });

  const handleImport = async () => {
    if (!state.selectedFile) {
      alert('Pilih file terlebih dahulu');
      return;
    }

    updateState({ importing: true, importResult: null });

    try {
      // Create FormData
      const formData = new FormData();
      formData.append('file', state.selectedFile);
      // Keep both snake_case and camelCase for backward/forward compatibility.
      formData.append('importMode', state.importMode);
      formData.append('updateMode', state.updateMode);
      formData.append('import_mode', state.importMode);
      formData.append('update_mode', state.updateMode);
      formData.append('skip_duplicates', state.skipDuplicates);

      const result = await onImport(formData);
      
      updateState({ 
        importResult: result,
        importing: false
      });
      
      if (result.success) {
        setTimeout(() => {
          onSuccess();
        }, 2000);
      }
    } catch (error) {
      console.error('Import error:', error);
      updateState({
        importing: false,
        importResult: {
          success: false,
          message: error.message || 'Gagal mengimpor data',
          details: error.details || error.response?.data?.data || null
        }
      });
    }
  };

  const handleClose = () => {
    if (state.importing) {
      return;
    }

    updateState({
      selectedFile: null,
      importing: false,
      importResult: null,
      importMode: 'auto',
      updateMode: 'partial',
      skipDuplicates: false
    });
    onClose();
  };

  const handleDialogClose = (_event, reason) => {
    if (state.importing && (reason === 'backdropClick' || reason === 'escapeKeyDown')) {
      return;
    }
    handleClose();
  };

  const downloadTemplate = async () => {
    try {
      let response;
      if (userType === 'pegawai') {
        response = await pegawaiService.downloadTemplate();
      } else if (userType === 'siswa') {
        response = await siswaService.downloadTemplate();
      }

      const blob = new Blob([response.data], { 
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `Template_Import_${userType === 'pegawai' ? 'Pegawai' : 'Siswa'}.xlsx`);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download template error:', error);
      alert('Gagal mendownload template');
    }
  };

  return (
    <Dialog 
      open={isOpen} 
      onClose={handleDialogClose}
      disableEscapeKeyDown={state.importing}
      maxWidth="md"
      fullWidth
      PaperProps={{
        className: "rounded-2xl"
      }}
    >
      <DialogTitle className="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <Box className="flex items-center justify-between">
          <Box className="flex items-center gap-3">
            <Upload className="w-6 h-6" />
            <Box>
              <Typography variant="h6" className="font-bold">
                Import Data {userType === 'pegawai' ? 'Pegawai' : 'Siswa'}
              </Typography>
              <Typography variant="body2" className="opacity-90">
                Upload file Excel untuk mengimpor data
              </Typography>
            </Box>
          </Box>
          <IconButton onClick={handleClose} className="text-white" disabled={state.importing}>
            <X />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent className="p-6">
        {/* Download Template Section */}
        <Paper className="p-4 mb-6 bg-blue-50 border border-blue-200">
          <Box className="flex items-start gap-3">
            <FileText className="w-5 h-5 text-blue-600 mt-1" />
            <Box className="flex-1">
              <Typography variant="subtitle2" className="font-semibold text-blue-900 mb-2">
                Download Template
              </Typography>
              <Typography variant="body2" className="text-blue-700 mb-3">
                Download template Excel untuk memastikan format data yang benar
              </Typography>
              <Button
                variant="outlined"
                size="small"
                startIcon={<Download className="w-4 h-4" />}
                onClick={downloadTemplate}
                className="border-blue-300 text-blue-700 hover:bg-blue-50"
              >
                Download Template {userType === 'pegawai' ? 'Pegawai' : 'Siswa'}
              </Button>
            </Box>
          </Box>
        </Paper>

        {/* Import Options */}
        <Paper className="p-4 mb-6 border border-gray-200">
          <Typography variant="subtitle2" className="font-semibold mb-3">
            Opsi Import
          </Typography>
          
          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormControl size="small" fullWidth>
              <InputLabel>Mode Import</InputLabel>
              <Select
                value={state.importMode}
                onChange={(e) => updateState({ importMode: e.target.value })}
                label="Mode Import"
                disabled={state.importing}
              >
                <MenuItem value="auto">Auto (Create/Update)</MenuItem>
                <MenuItem value="create-only">Create Only</MenuItem>
                <MenuItem value="update-only">Update Only</MenuItem>
              </Select>
            </FormControl>

            <FormControl size="small" fullWidth>
              <InputLabel>Mode Update</InputLabel>
              <Select
                value={state.updateMode}
                onChange={(e) => updateState({ updateMode: e.target.value })}
                label="Mode Update"
                disabled={state.importMode === 'create-only' || state.importing}
              >
                <MenuItem value="partial">Partial Update</MenuItem>
                <MenuItem value="full">Full Replace</MenuItem>
              </Select>
            </FormControl>
          </Box>

          <Box className="mt-3">
            <FormControlLabel
              control={
                <Switch
                  checked={state.skipDuplicates}
                  onChange={(e) => updateState({ skipDuplicates: e.target.checked })}
                  color="primary"
                  disabled={state.importing}
                />
              }
              label="Skip Duplicates"
            />
          </Box>
        </Paper>

        {/* File Upload Area */}
        <Paper 
          {...getRootProps()} 
          className={`p-8 border-2 border-dashed cursor-pointer transition-colors ${
            isDragActive 
              ? 'border-blue-400 bg-blue-50' 
              : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'
          }`}
        >
          <input {...getInputProps()} />
          <Box className="text-center">
            <Cloud className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <Typography variant="h6" className="mb-2">
              {isDragActive ? 'Drop file di sini...' : 'Drag & drop file atau klik untuk browse'}
            </Typography>
            <Typography variant="body2" color="textSecondary" className="mb-4">
              Excel (.xlsx, .xls) hingga 2MB
            </Typography>
            <Button variant="outlined" size="small">
              Pilih File
            </Button>
          </Box>
        </Paper>

        {/* Selected File */}
        {state.selectedFile && (
          <Paper className="p-3 mt-4 bg-green-50 border border-green-200">
            <Box className="flex items-center justify-between">
              <Box className="flex items-center gap-2">
                <FileText className="w-5 h-5 text-green-600" />
                <Box>
                  <Typography variant="body2" className="font-medium text-green-800">
                    {state.selectedFile.name}
                  </Typography>
                  <Typography variant="caption" className="text-green-600">
                    {(state.selectedFile.size / 1024 / 1024).toFixed(2)} MB
                  </Typography>
                </Box>
              </Box>
              <IconButton 
                size="small" 
                onClick={() => updateState({ selectedFile: null })}
                className="text-green-600"
                disabled={state.importing}
              >
          <X className="w-4 h-4" />
              </IconButton>
            </Box>
          </Paper>
        )}

        {/* Progress Bar */}
        {state.importing && (
          <Box className="mt-4">
            <Alert severity="warning" className="mb-3">
              Import sedang berjalan. Jangan tutup dialog, refresh, atau pindah halaman.
            </Alert>
            <Box className="flex justify-between items-center mb-2">
              <Typography variant="body2">Mengimpor data...</Typography>
              <Typography variant="body2">{progress}%</Typography>
            </Box>
            <LinearProgress 
              variant="determinate" 
              value={progress} 
              className="h-2 rounded"
            />
          </Box>
        )}

        {/* Import Result */}
        {state.importResult && (
          <Alert 
            severity={state.importResult.success ? 'success' : 'error'}
            className="mt-4"
            icon={state.importResult.success ? <CheckCircle /> : <AlertCircle />}
          >
            <Typography variant="subtitle2" className="font-semibold mb-1">
              {state.importResult.success ? 'Import Berhasil!' : 'Import Gagal!'}
            </Typography>
            <Typography variant="body2" className="mb-2">
              {state.importResult.message}
            </Typography>
            
            {state.importResult.details && (
              <Box className="mt-2">
                <Box className="flex gap-2 mb-2">
                  <Chip 
                    label={`Berhasil: ${state.importResult.details.imported ?? state.importResult.details.success ?? 0}`} 
                    color="success" 
                    size="small" 
                  />
                  <Chip 
                    label={`Gagal: ${state.importResult.details.failed ?? state.importResult.details.errors?.length ?? 0}`} 
                    color="error" 
                    size="small" 
                  />
                  {state.importResult.details.updated && (
                    <Chip 
                      label={`Updated: ${state.importResult.details.updated}`} 
                      color="info" 
                      size="small" 
                    />
                  )}
                </Box>
                
                {state.importResult.details.errors && state.importResult.details.errors.length > 0 && (
                  <Box>
                    <Typography variant="caption" className="font-medium">
                      Error Details:
                    </Typography>
                    <List dense>
                      {state.importResult.details.errors.slice(0, 5).map((error, index) => (
                        <ListItem key={index} className="py-1">
                          <ListItemIcon>
                            <AlertCircle className="w-4 h-4 text-red-500" />
                          </ListItemIcon>
                          <ListItemText 
                            primary={error} 
                            primaryTypographyProps={{ variant: 'caption' }}
                          />
                        </ListItem>
                      ))}
                      {state.importResult.details.errors.length > 5 && (
                        <ListItem className="py-1">
                          <ListItemText 
                            primary={`... dan ${state.importResult.details.errors.length - 5} error lainnya`}
                            primaryTypographyProps={{ variant: 'caption', style: { fontStyle: 'italic' } }}
                          />
                        </ListItem>
                      )}
                    </List>
                  </Box>
                )}
              </Box>
            )}
          </Alert>
        )}

        {/* Requirements */}
        <Divider className="my-4" />
        <Typography variant="subtitle2" className="font-semibold mb-2">
          Persyaratan Format File:
        </Typography>
        <List dense>
          <ListItem>
            <ListItemIcon>
              <CheckCircle className="w-4 h-4 text-green-500" />
            </ListItemIcon>
            <ListItemText primary="Format file: Excel (.xlsx, .xls)" />
          </ListItem>
          <ListItem>
            <ListItemIcon>
              <CheckCircle className="w-4 h-4 text-green-500" />
            </ListItemIcon>
            <ListItemText primary="Ukuran maksimal: 2MB" />
          </ListItem>
          <ListItem>
            <ListItemIcon>
              <CheckCircle className="w-4 h-4 text-green-500" />
            </ListItemIcon>
            <ListItemText primary="Baris pertama harus berisi header kolom" />
          </ListItem>
          <ListItem>
            <ListItemIcon>
              <CheckCircle className="w-4 h-4 text-green-500" />
            </ListItemIcon>
            <ListItemText primary="Data dimulai dari baris kedua" />
          </ListItem>
        </List>
      </DialogContent>

      <DialogActions className="p-6 pt-0">
        <Button onClick={handleClose} color="inherit" disabled={state.importing}>
          Tutup
        </Button>
        <Button
          onClick={handleImport}
          variant="contained"
          disabled={!state.selectedFile || state.importing}
          startIcon={<Upload className="w-4 h-4" />}
          className="bg-blue-600 hover:bg-blue-700"
        >
          {state.importing ? 'Mengimpor...' : 'Import Data'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ImportModal;

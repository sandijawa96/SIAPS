import React, { useCallback, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControl,
  FormControlLabel,
  IconButton,
  InputLabel,
  LinearProgress,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  MenuItem,
  Paper,
  Select,
  Switch,
  Typography,
} from '@mui/material';
import {
  AlertCircle,
  CheckCircle,
  Cloud,
  Download,
  FileText,
  Upload,
  X,
} from 'lucide-react';
import { useDropzone } from 'react-dropzone';

const defaultRequirements = [
  'Format file: Excel (.xlsx, .xls)',
  'Ukuran maksimal: 5MB',
  'Gunakan template resmi sebelum import',
  'Data dimulai dari baris setelah header',
];

const ImportModalAkademik = ({
  isOpen,
  onClose,
  onSuccess,
  onImport,
  onDownloadTemplate,
  title,
  subtitle,
  templateLabel,
  progress = 0,
  requirements = defaultRequirements,
}) => {
  const [state, setState] = useState({
    selectedFile: null,
    importing: false,
    importResult: null,
    importMode: 'auto',
    updateMode: 'partial',
    skipDuplicates: false,
  });

  const updateState = useCallback((updates) => {
    setState((prev) => ({ ...prev, ...updates }));
  }, []);

  const onDrop = useCallback((acceptedFiles) => {
    if (acceptedFiles.length > 0) {
      updateState({
        selectedFile: acceptedFiles[0],
        importResult: null,
      });
    }
  }, [updateState]);

  const onDropRejected = useCallback((fileRejections) => {
    const error = fileRejections[0]?.errors?.[0];
    if (error?.code === 'file-too-large') {
      alert('File terlalu besar. Maksimal 5MB.');
      return;
    }
    if (error?.code === 'file-invalid-type') {
      alert('Format file tidak didukung. Gunakan .xlsx atau .xls');
      return;
    }
    alert('File tidak valid untuk import.');
  }, []);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    accept: {
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
      'application/vnd.ms-excel': ['.xls'],
    },
    maxSize: 5 * 1024 * 1024,
    disabled: state.importing,
    onDrop,
    onDropRejected,
  });

  const handleImport = async () => {
    if (!state.selectedFile) {
      alert('Pilih file terlebih dahulu');
      return;
    }

    updateState({ importing: true, importResult: null });

    try {
      const formData = new FormData();
      formData.append('file', state.selectedFile);
      formData.append('import_mode', state.importMode);
      formData.append('update_mode', state.updateMode);
      formData.append('skip_duplicates', String(state.skipDuplicates));

      const result = await onImport(formData);
      const details = result?.details || result?.data || null;

      updateState({
        importing: false,
        importResult: {
          success: Boolean(result?.success),
          message: result?.message || 'Import selesai',
          details,
        },
      });

      if (result?.success) {
        setTimeout(() => {
          onSuccess?.();
        }, 1200);
      }
    } catch (error) {
      updateState({
        importing: false,
        importResult: {
          success: false,
          message: error?.message || 'Gagal mengimpor data',
          details: error?.details || error?.response?.data?.data || null,
        },
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
      skipDuplicates: false,
    });
    onClose?.();
  };

  const handleDialogClose = (_event, reason) => {
    if (state.importing && (reason === 'backdropClick' || reason === 'escapeKeyDown')) {
      return;
    }
    handleClose();
  };

  const handleDownloadTemplate = async () => {
    try {
      await onDownloadTemplate?.();
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
        className: 'rounded-2xl',
      }}
    >
      <DialogTitle className="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <Box className="flex items-center justify-between">
          <Box className="flex items-center gap-3">
            <Upload className="w-6 h-6" />
            <Box>
              <Typography variant="h6" className="font-bold">
                {title}
              </Typography>
              <Typography variant="body2" className="opacity-90">
                {subtitle}
              </Typography>
            </Box>
          </Box>

          <IconButton onClick={handleClose} className="text-white" disabled={state.importing}>
            <X />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent className="p-6">
        <Paper className="p-4 mb-6 bg-blue-50 border border-blue-200">
          <Box className="flex items-start gap-3">
            <FileText className="w-5 h-5 text-blue-600 mt-1" />
            <Box className="flex-1">
              <Typography variant="subtitle2" className="font-semibold text-blue-900 mb-2">
                Download Template
              </Typography>
              <Typography variant="body2" className="text-blue-700 mb-3">
                Gunakan template resmi agar struktur kolom sesuai dengan proses import.
              </Typography>
              <Button
                variant="outlined"
                size="small"
                startIcon={<Download className="w-4 h-4" />}
                onClick={handleDownloadTemplate}
                className="border-blue-300 text-blue-700 hover:bg-blue-50"
              >
                {templateLabel}
              </Button>
            </Box>
          </Box>
        </Paper>

        <Paper className="p-4 mb-6 border border-gray-200">
          <Typography variant="subtitle2" className="font-semibold mb-3">
            Opsi Import
          </Typography>

          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormControl size="small" fullWidth>
              <InputLabel>Mode Import</InputLabel>
              <Select
                value={state.importMode}
                onChange={(event) => updateState({ importMode: event.target.value })}
                label="Mode Import"
                disabled={state.importing}
              >
                <MenuItem value="auto">Auto (Create/Update)</MenuItem>
                <MenuItem value="create">Create Only</MenuItem>
                <MenuItem value="update">Update Only</MenuItem>
              </Select>
            </FormControl>

            <FormControl size="small" fullWidth>
              <InputLabel>Mode Update</InputLabel>
              <Select
                value={state.updateMode}
                onChange={(event) => updateState({ updateMode: event.target.value })}
                label="Mode Update"
                disabled={state.importMode === 'create' || state.importing}
              >
                <MenuItem value="partial">Partial Update</MenuItem>
                <MenuItem value="full">Full Replace</MenuItem>
              </Select>
            </FormControl>
          </Box>

          <Box className="mt-3">
            <FormControlLabel
              control={(
                <Switch
                  checked={state.skipDuplicates}
                  onChange={(event) => updateState({ skipDuplicates: event.target.checked })}
                  color="primary"
                  disabled={state.importing}
                />
              )}
              label="Skip Duplicates"
            />
          </Box>
        </Paper>

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
              Excel (.xlsx, .xls) hingga 5MB
            </Typography>
            <Button variant="outlined" size="small">
              Pilih File
            </Button>
          </Box>
        </Paper>

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

        {state.importing && (
          <Box className="mt-4">
            <Alert severity="warning" className="mb-3">
              Import sedang berjalan. Jangan tutup dialog, refresh, atau pindah halaman.
            </Alert>
            <Box className="flex justify-between items-center mb-2">
              <Typography variant="body2">Mengimpor data...</Typography>
              <Typography variant="body2">{progress}%</Typography>
            </Box>
            <LinearProgress variant="determinate" value={progress} className="h-2 rounded" />
          </Box>
        )}

        {state.importResult && (
          <Alert
            severity={state.importResult.success ? 'success' : 'error'}
            className="mt-4"
            icon={state.importResult.success ? <CheckCircle /> : <AlertCircle />}
          >
            <Typography variant="subtitle2" className="font-semibold mb-1">
              {state.importResult.success ? 'Import Berhasil' : 'Import Gagal'}
            </Typography>
            <Typography variant="body2" className="mb-2">
              {state.importResult.message}
            </Typography>

            {state.importResult.details && (
              <Box className="mt-2">
                <Box className="flex gap-2 mb-2">
                  <Chip
                    label={`Berhasil: ${
                      state.importResult.details.imported
                      || state.importResult.details.success
                      || 0
                    }`}
                    color="success"
                    size="small"
                  />
                  <Chip
                    label={`Gagal: ${
                      state.importResult.details.failed
                      || state.importResult.details.errors?.length
                      || 0
                    }`}
                    color="error"
                    size="small"
                  />
                  {state.importResult.details.updated ? (
                    <Chip
                      label={`Updated: ${state.importResult.details.updated}`}
                      color="info"
                      size="small"
                    />
                  ) : null}
                </Box>

                {Array.isArray(state.importResult.details.errors) && state.importResult.details.errors.length > 0 && (
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

        <Divider className="my-4" />
        <Typography variant="subtitle2" className="font-semibold mb-2">
          Persyaratan Format File:
        </Typography>
        <List dense>
          {requirements.map((requirement, index) => (
            <ListItem key={index}>
              <ListItemIcon>
                <CheckCircle className="w-4 h-4 text-green-500" />
              </ListItemIcon>
              <ListItemText primary={requirement} />
            </ListItem>
          ))}
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

export default ImportModalAkademik;


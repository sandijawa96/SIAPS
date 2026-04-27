import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
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
  Typography,
} from '@mui/material';
import {
  CheckCircle,
  Download,
  FileSpreadsheet,
  Filter,
  Settings,
  X,
} from 'lucide-react';

const ExportModalAkademik = ({
  isOpen,
  onClose,
  onExport,
  title = 'Export Data',
  subtitle = 'Unduh data sesuai filter aktif',
  entityLabel = 'Data',
  fields = [],
  progress = 0,
}) => {
  const availableFields = useMemo(
    () => (Array.isArray(fields) ? fields : []),
    [fields]
  );

  const [state, setState] = useState({
    exporting: false,
    exportResult: null,
    format: 'xlsx',
    selectedFields: [],
  });

  const updateState = (updates) => {
    setState((prev) => ({ ...prev, ...updates }));
  };

  const resetState = () => {
    setState({
      exporting: false,
      exportResult: null,
      format: 'xlsx',
      selectedFields: [],
    });
  };

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    if (state.selectedFields.length === 0) {
      const defaults = availableFields
        .filter((item) => item.default)
        .map((item) => item.id);
      updateState({
        selectedFields: defaults.length > 0 ? defaults : availableFields.map((item) => item.id),
      });
    }
  }, [isOpen, availableFields, state.selectedFields.length]);

  const handleFieldToggle = (fieldId) => {
    updateState({
      selectedFields: state.selectedFields.includes(fieldId)
        ? state.selectedFields.filter((id) => id !== fieldId)
        : [...state.selectedFields, fieldId],
    });
  };

  const handleClose = () => {
    if (state.exporting) {
      return;
    }

    resetState();
    onClose?.();
  };

  const selectAllFields = () => {
    updateState({
      selectedFields: availableFields.map((item) => item.id),
    });
  };

  const selectDefaultFields = () => {
    const defaults = availableFields
      .filter((item) => item.default)
      .map((item) => item.id);
    updateState({
      selectedFields: defaults.length > 0 ? defaults : availableFields.map((item) => item.id),
    });
  };

  const handleExport = async () => {
    updateState({ exporting: true, exportResult: null });

    try {
      const result = await onExport?.({
        format: state.format,
        fields: state.selectedFields,
      });

      updateState({
        exporting: false,
        exportResult: {
          success: true,
          message: result?.message || `Export ${entityLabel} berhasil`,
        },
      });

      setTimeout(() => {
        handleClose();
      }, 1200);
    } catch (error) {
      updateState({
        exporting: false,
        exportResult: {
          success: false,
          message: error?.message || `Export ${entityLabel} gagal`,
        },
      });
    }
  };

  return (
    <Dialog
      open={isOpen}
      onClose={handleClose}
      maxWidth="md"
      fullWidth
      PaperProps={{
        className: 'rounded-2xl',
      }}
    >
      <DialogTitle className="bg-gradient-to-r from-green-600 to-emerald-700 text-white">
        <Box className="flex items-center justify-between">
          <Box className="flex items-center gap-3">
            <Download className="w-6 h-6" />
            <Box>
              <Typography variant="h6" className="font-bold">
                {title}
              </Typography>
              <Typography variant="body2" className="opacity-90">
                {subtitle}
              </Typography>
            </Box>
          </Box>
          <IconButton onClick={handleClose} className="text-white" disabled={state.exporting}>
            <X />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent className="p-6">
        <Paper className="p-4 mb-6 border border-gray-200">
          <Box className="flex items-center gap-2 mb-3">
            <Settings className="w-5 h-5 text-gray-600" />
            <Typography variant="subtitle2" className="font-semibold">
              Opsi Export
            </Typography>
          </Box>

          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
            <FormControl size="small" fullWidth>
              <InputLabel>Format File</InputLabel>
              <Select
                value={state.format}
                onChange={(event) => updateState({ format: event.target.value })}
                label="Format File"
                disabled={state.exporting}
              >
                <MenuItem value="xlsx">Excel (.xlsx)</MenuItem>
                <MenuItem value="pdf">PDF (.pdf)</MenuItem>
              </Select>
            </FormControl>
          </Box>

          <Typography variant="body2" color="text.secondary">
            Data mengikuti filter aktif pada halaman saat ini.
          </Typography>
        </Paper>

        <Paper className="p-4 mb-6 border border-gray-200">
          <Box className="flex items-center justify-between mb-3">
            <Box className="flex items-center gap-2">
              <Filter className="w-5 h-5 text-gray-600" />
              <Typography variant="subtitle2" className="font-semibold">
                Pilih Kolom
              </Typography>
            </Box>

            <Box className="flex gap-2">
              <Button size="small" variant="outlined" onClick={selectDefaultFields} disabled={state.exporting}>
                Default
              </Button>
              <Button size="small" variant="outlined" onClick={selectAllFields} disabled={state.exporting}>
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

          <List dense className="max-h-56 overflow-y-auto">
            {availableFields.map((field) => (
              <ListItem
                key={field.id}
                button
                onClick={() => handleFieldToggle(field.id)}
                className="hover:bg-gray-50 rounded"
                disabled={state.exporting}
              >
                <ListItemIcon>
                  <Checkbox
                    checked={state.selectedFields.includes(field.id)}
                    onChange={() => handleFieldToggle(field.id)}
                    tabIndex={-1}
                    disableRipple
                    color="primary"
                    disabled={state.exporting}
                  />
                </ListItemIcon>
                <ListItemText
                  primary={field.label}
                  secondary={field.default ? 'Default' : 'Opsional'}
                />
                {field.default && (
                  <Chip label="Default" size="small" color="primary" variant="outlined" />
                )}
              </ListItem>
            ))}
          </List>
        </Paper>

        {state.exporting && (
          <Box className="mb-4">
            <Box className="flex justify-between items-center mb-2">
              <Typography variant="body2">Mengekspor data...</Typography>
              <Typography variant="body2">{progress}%</Typography>
            </Box>
            <LinearProgress variant="determinate" value={progress} className="h-2 rounded" />
          </Box>
        )}

        {state.exportResult && (
          <Alert
            severity={state.exportResult.success ? 'success' : 'error'}
            className="mb-4"
            icon={state.exportResult.success ? <CheckCircle /> : undefined}
          >
            <Typography variant="subtitle2" className="font-semibold mb-1">
              {state.exportResult.success ? 'Export Berhasil' : 'Export Gagal'}
            </Typography>
            <Typography variant="body2">
              {state.exportResult.message}
            </Typography>
          </Alert>
        )}

        <Paper className="p-4 bg-blue-50 border border-blue-200">
          <Box className="flex items-start gap-3">
            <FileSpreadsheet className="w-5 h-5 text-blue-600 mt-1" />
            <Box>
              <Typography variant="subtitle2" className="font-semibold text-blue-900 mb-2">
                Catatan Export
              </Typography>
              <List dense>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText
                    primary="Unduhan otomatis dimulai setelah proses selesai"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText
                    primary="Header dan metadata laporan disusun secara resmi"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
                <ListItem className="px-0 py-1">
                  <ListItemIcon className="min-w-0 mr-2">
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  </ListItemIcon>
                  <ListItemText
                    primary="Format mendukung presentasi dan audit (Excel/PDF)"
                    primaryTypographyProps={{ variant: 'body2', className: 'text-blue-700' }}
                  />
                </ListItem>
              </List>
            </Box>
          </Box>
        </Paper>
      </DialogContent>

      <DialogActions className="p-6 pt-0">
        <Button onClick={handleClose} color="inherit" disabled={state.exporting}>
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

export default ExportModalAkademik;

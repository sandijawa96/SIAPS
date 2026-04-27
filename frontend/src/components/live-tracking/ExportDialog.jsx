import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Box,
  Typography,
  FormControl,
  FormControlLabel,
  RadioGroup,
  Radio,
  Checkbox,
  TextField,
  Select,
  MenuItem,
  InputLabel,
  Divider,
  Alert,
  CircularProgress,
  Chip,
} from '@mui/material';
import {
  Download,
  FileText,
  Calendar,
  Filter,
  CheckSquare,
  X,
} from 'lucide-react';
import useServerClock from '../../hooks/useServerClock';

const FORMAT_OPTIONS = [
  { value: 'excel', label: 'Excel (.xlsx)' },
  { value: 'csv', label: 'CSV (.csv)' },
  { value: 'pdf', label: 'PDF (.pdf)' },
];

const DATE_RANGE_OPTIONS = [
  { value: 'today', label: 'Hari Ini' },
  { value: 'yesterday', label: 'Kemarin' },
  { value: 'week', label: '7 Hari Terakhir' },
  { value: 'month', label: '30 Hari Terakhir' },
  { value: 'custom', label: 'Rentang Kustom' },
];

const FIELD_OPTIONS = [
  {
    key: 'basicInfo',
    label: 'Informasi Dasar',
    description: 'Nomor urut, nama, dan kelas siswa.',
    shortLabel: 'Data',
  },
  {
    key: 'trackingState',
    label: 'Status Tracking',
    description: 'Status tracking dan status dalam area sekolah.',
    shortLabel: 'Status',
  },
  {
    key: 'locationData',
    label: 'Data Lokasi',
    description: 'Lokasi, koordinat GPS, akurasi, dan kecepatan.',
    shortLabel: 'Lokasi',
  },
  {
    key: 'timestamps',
    label: 'Waktu',
    description: 'Waktu tracking setiap titik histori.',
    shortLabel: 'Waktu',
  },
  {
    key: 'deviceInfo',
    label: 'Info Perangkat',
    description: 'Sumber device, kualitas GPS, IP, platform, dan session id.',
    shortLabel: 'Device',
  },
];

const createInitialExportSettings = () => ({
  format: 'excel',
  dateRange: 'today',
  customStartDate: '',
  customEndDate: '',
  includeFilters: true,
  includeFields: {
    basicInfo: true,
    trackingState: true,
    locationData: true,
    timestamps: true,
    deviceInfo: true,
  },
});

const ExportDialog = ({
  open,
  onClose,
  onExport,
  students,
  filters,
  historyPolicy,
  loading,
  error,
}) => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [exportSettings, setExportSettings] = useState(createInitialExportSettings);

  useEffect(() => {
    if (!open || !isServerClockSynced || !serverDate) {
      return;
    }

    setExportSettings((current) => ({
      ...current,
      customStartDate: current.customStartDate || serverDate,
      customEndDate: current.customEndDate || serverDate,
    }));
  }, [isServerClockSynced, open, serverDate]);

  const handleSettingChange = (key, value) => {
    setExportSettings((previous) => ({
      ...previous,
      [key]: value,
    }));
  };

  const handleFieldChange = (field, checked) => {
    setExportSettings((previous) => ({
      ...previous,
      includeFields: {
        ...previous.includeFields,
        [field]: checked,
      },
    }));
  };

  const handleExport = () => {
    onExport(exportSettings);
  };

  const getFilterSummary = () => {
    const activeFilters = [];
    if (filters.status !== 'all') activeFilters.push(`Status: ${filters.status}`);
    if (filters.area !== 'all') activeFilters.push(`Area: ${filters.area}`);
    if (filters.search) activeFilters.push(`Pencarian: "${filters.search}"`);
    if (filters.class) activeFilters.push(`Kelas: ${filters.class}`);
    if (filters.tingkat) activeFilters.push(`Tingkat: ${filters.tingkat}`);
    if (filters.wali_kelas_id) activeFilters.push(`Wali Kelas: #${filters.wali_kelas_id}`);
    return activeFilters;
  };

  const historyCheckpointMinutes = Math.max(
    1,
    Math.round(Number(historyPolicy?.persistIdleSeconds || 300) / 60)
  );
  const hasHistoryEmptyByDesign = ['tracking_disabled', 'outside_schedule', 'gps_disabled', 'no_data', 'no-data']
    .includes(String(filters?.status || 'all'));

  return (
    <Dialog
      open={open}
      onClose={onClose}
      maxWidth="md"
      fullWidth
      PaperProps={{
        className: 'rounded-lg',
      }}
    >
      <DialogTitle className="flex items-center gap-2 pb-2">
        <Download className="w-5 h-5 text-green-600" />
        <Typography variant="h6" className="font-semibold">
          Export Data Tracking
        </Typography>
      </DialogTitle>

      <DialogContent className="space-y-6">
        {error && (
          <Alert severity="error" className="mb-4">
            {error}
          </Alert>
        )}

        <Box className="rounded-lg bg-blue-50 p-4">
          <Typography variant="subtitle2" className="mb-2 flex items-center gap-2 font-medium">
            <FileText className="w-4 h-4" />
            Ringkasan Export
          </Typography>
          <Box className="space-y-1 text-sm text-gray-700">
            <div>
              Jumlah siswa pada daftar saat ini: <strong>{students.length}</strong>
            </div>
            {exportSettings.includeFilters && getFilterSummary().length > 0 && (
              <div>
                Filter aktif:{' '}
                {getFilterSummary().map((filter, index) => (
                  <Chip
                    key={`${filter}-${index}`}
                    label={filter}
                    size="small"
                    className="mb-1 ml-1"
                  />
                ))}
              </div>
            )}
          </Box>
        </Box>

        <Alert severity="info" className="rounded-2xl">
          <Typography variant="body2" className="font-medium text-slate-900">
            Histori export berbasis pergerakan, bukan setiap ping GPS.
          </Typography>
          <Typography variant="body2" className="mt-1 text-slate-700">
            Titik tersimpan saat perpindahan mencapai minimal {Number(historyPolicy?.minDistanceMeters || 20)} meter,
            saat status penting berubah, dan checkpoint tiap {historyCheckpointMinutes} menit saat diam.
          </Typography>
        </Alert>

        {hasHistoryEmptyByDesign ? (
          <Alert severity="warning" className="rounded-2xl">
            <Typography variant="body2">
              Filter status saat ini bisa menghasilkan file kosong. Histori live tracking hanya menyimpan titik lokasi valid,
              sehingga status seperti `Di luar jadwal`, `GPS mati`, dan `Belum ada data` tidak membentuk jejak histori titik.
            </Typography>
          </Alert>
        ) : null}

        <Box>
          <Typography variant="subtitle1" className="mb-3 flex items-center gap-2 font-medium">
            <Download className="w-4 h-4" />
            Format File
          </Typography>
          <FormControl component="fieldset">
            <RadioGroup
              value={exportSettings.format}
              onChange={(event) => handleSettingChange('format', event.target.value)}
              className="space-y-2"
            >
              {FORMAT_OPTIONS.map((option) => (
                <FormControlLabel
                  key={option.value}
                  value={option.value}
                  control={<Radio color="primary" />}
                  label={option.label}
                />
              ))}
            </RadioGroup>
          </FormControl>
        </Box>

        <Divider />

        <Box>
          <Typography variant="subtitle1" className="mb-3 flex items-center gap-2 font-medium">
            <Calendar className="w-4 h-4" />
            Rentang Tanggal
          </Typography>
          <FormControl fullWidth className="mb-3">
            <InputLabel>Pilih Rentang</InputLabel>
            <Select
              value={exportSettings.dateRange}
              onChange={(event) => handleSettingChange('dateRange', event.target.value)}
              label="Pilih Rentang"
            >
              {DATE_RANGE_OPTIONS.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          {exportSettings.dateRange === 'custom' && (
            <Box className="grid grid-cols-2 gap-3">
              <TextField
                label="Tanggal Mulai"
                type="date"
                value={exportSettings.customStartDate}
                onChange={(event) => handleSettingChange('customStartDate', event.target.value)}
                InputLabelProps={{ shrink: true }}
                fullWidth
              />
              <TextField
                label="Tanggal Selesai"
                type="date"
                value={exportSettings.customEndDate}
                onChange={(event) => handleSettingChange('customEndDate', event.target.value)}
                InputLabelProps={{ shrink: true }}
                fullWidth
              />
            </Box>
          )}
        </Box>

        <Divider />

        <Box>
          <Typography variant="subtitle1" className="mb-3 flex items-center gap-2 font-medium">
            <Filter className="w-4 h-4" />
            Opsi Filter
          </Typography>
          <FormControlLabel
            control={
              <Checkbox
                checked={exportSettings.includeFilters}
                onChange={(event) => handleSettingChange('includeFilters', event.target.checked)}
                color="primary"
              />
            }
            label={
              <Box>
                <Typography variant="body2" className="font-medium">
                  Terapkan Filter Saat Ini
                </Typography>
                <Typography variant="caption" className="text-gray-600">
                  Export hanya histori yang sesuai dengan filter daftar saat ini.
                </Typography>
              </Box>
            }
          />
        </Box>

        <Divider />

        <Box>
          <Typography variant="subtitle1" className="mb-3 flex items-center gap-2 font-medium">
            <CheckSquare className="w-4 h-4" />
            Data yang Disertakan
          </Typography>
          <Box className="space-y-3">
            {FIELD_OPTIONS.map((field) => (
              <FormControlLabel
                key={field.key}
                control={
                  <Checkbox
                    checked={exportSettings.includeFields[field.key]}
                    onChange={(event) => handleFieldChange(field.key, event.target.checked)}
                    color="primary"
                  />
                }
                label={
                  <Box className="flex items-start gap-2">
                    <span className="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                      {field.shortLabel}
                    </span>
                    <Box>
                      <Typography variant="body2" className="font-medium">
                        {field.label}
                      </Typography>
                      <Typography variant="caption" className="text-gray-600">
                        {field.description}
                      </Typography>
                    </Box>
                  </Box>
                }
              />
            ))}
          </Box>
        </Box>

        {students.length > 100 && (
          <Alert severity="warning">
            <Typography variant="body2">
              Export data dalam jumlah besar ({students.length} siswa) mungkin membutuhkan waktu lebih lama.
              Pastikan koneksi internet stabil.
            </Typography>
          </Alert>
        )}
      </DialogContent>

      <DialogActions className="border-t border-gray-200 px-6 py-4">
        <Button
          onClick={onClose}
          color="inherit"
          className="text-gray-600"
          startIcon={<X className="w-4 h-4" />}
        >
          Batal
        </Button>
        <Button
          onClick={handleExport}
          variant="contained"
          color="primary"
          disabled={loading || students.length === 0}
          startIcon={loading ? <CircularProgress size={16} /> : <Download className="w-4 h-4" />}
          className="bg-green-600 hover:bg-green-700"
        >
          {loading ? 'Mengexport...' : 'Export Data'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ExportDialog;

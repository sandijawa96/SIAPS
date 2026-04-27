import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  LinearProgress,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { AlertCircle, CheckCircle2, Database, Download, School } from 'lucide-react';
import { toServerDateInput } from '../services/serverClock';

const normalizeDateInput = (value) => toServerDateInput(value) || '';

const STATUS_OPTIONS = [
  { value: 'hadir', label: 'Hadir' },
  { value: 'terlambat', label: 'Terlambat' },
  { value: 'izin', label: 'Izin' },
  { value: 'sakit', label: 'Sakit' },
  { value: 'alpha', label: 'Alpha' },
];

const getBatchStatusSummary = (status, progressPercentage = 0) => {
  const normalizedStatus = String(status || '').toLowerCase();

  if (normalizedStatus === 'completed') {
    return {
      severity: 'success',
      title: 'Batch selesai',
      description: 'Batch insiden server selesai diproses.',
    };
  }

  if (normalizedStatus === 'failed') {
    return {
      severity: 'error',
      title: 'Batch gagal',
      description: 'Batch insiden server gagal diproses.',
    };
  }

  if (normalizedStatus === 'processing') {
    return {
      severity: 'info',
      title: `Batch sedang diproses ${progressPercentage || 0}%`,
      description: 'Batch insiden server sedang diproses di background.',
    };
  }

  return {
    severity: 'warning',
    title: 'Batch dalam antrean',
    description: 'Batch menunggu worker memproses antrean.',
  };
};

const ManualAttendanceIncidentModal = ({
  open,
  onClose,
  options,
  initialBatch = null,
  loadingOptions = false,
  onLoadOptions,
  onPreview,
  onStart,
  onRefreshBatch,
  onExport,
  serverDate = '',
}) => {
  const normalizedServerDate = useMemo(() => normalizeDateInput(serverDate), [serverDate]);
  const [selectedClasses, setSelectedClasses] = useState([]);
  const [selectedLevels, setSelectedLevels] = useState([]);
  const [exportGroup, setExportGroup] = useState('all');
  const [formData, setFormData] = useState({
    tanggal: '',
    scope_type: 'all_manageable',
    status: 'hadir',
    jam_masuk: '',
    jam_pulang: '',
    keterangan: '',
    reason: '',
  });
  const [errors, setErrors] = useState({});
  const [preview, setPreview] = useState(null);
  const [batch, setBatch] = useState(null);
  const [previewing, setPreviewing] = useState(false);
  const [starting, setStarting] = useState(false);
  const [polling, setPolling] = useState(false);
  const [previewSignature, setPreviewSignature] = useState('');

  useEffect(() => {
    if (!open) {
      return;
    }

    setSelectedClasses([]);
    setSelectedLevels([]);
    setExportGroup('all');
    setFormData({
      tanggal: initialBatch?.tanggal || normalizedServerDate,
      scope_type: initialBatch?.scope_type || 'all_manageable',
      status: initialBatch?.attendance_status || 'hadir',
      jam_masuk: initialBatch?.jam_masuk || '',
      jam_pulang: initialBatch?.jam_pulang || '',
      keterangan: initialBatch?.keterangan || '',
      reason: initialBatch?.reason || '',
    });
    setErrors({});
    setPreview(initialBatch?.preview_summary || null);
    setBatch(initialBatch || null);
    setPreviewSignature('');

    if (!options && onLoadOptions) {
      onLoadOptions();
    }
  }, [initialBatch, normalizedServerDate, onLoadOptions, open, options]);

  useEffect(() => {
    if (!open || !initialBatch || !options) {
      return;
    }

    const scopePayload = initialBatch.scope_payload || {};
    const kelasIds = Array.isArray(scopePayload.kelas_ids) ? scopePayload.kelas_ids : [];
    const tingkatIds = Array.isArray(scopePayload.tingkat_ids) ? scopePayload.tingkat_ids : [];

    setSelectedClasses((options.classes || []).filter((kelas) => kelasIds.includes(kelas.id)));
    setSelectedLevels((options.levels || []).filter((tingkat) => tingkatIds.includes(tingkat.id)));
  }, [initialBatch, open, options]);

  const currentSignature = useMemo(() => JSON.stringify({
    tanggal: normalizeDateInput(formData.tanggal),
    scope_type: formData.scope_type,
    kelas_ids: selectedClasses.map((kelas) => kelas.id),
    tingkat_ids: selectedLevels.map((tingkat) => tingkat.id),
    status: formData.status,
    jam_masuk: formData.jam_masuk,
    jam_pulang: formData.jam_pulang,
    keterangan: formData.keterangan,
    reason: formData.reason,
  }), [
    formData.jam_masuk,
    formData.jam_pulang,
    formData.keterangan,
    formData.reason,
    formData.scope_type,
    formData.status,
    formData.tanggal,
    selectedClasses,
    selectedLevels,
  ]);

  const hasFreshPreview = Boolean(preview) && previewSignature === currentSignature;
  const batchIsActive = ['queued', 'processing'].includes(String(batch?.status || ''));

  useEffect(() => {
    if (!open || !batch?.id || !batchIsActive || !onRefreshBatch) {
      return undefined;
    }

    const timer = setInterval(async () => {
      try {
        setPolling(true);
        const nextBatch = await onRefreshBatch(batch.id);
        setBatch(nextBatch);
      } catch {
        // Keep last visible state on polling issue.
      } finally {
        setPolling(false);
      }
    }, 2000);

    return () => clearInterval(timer);
  }, [batch?.id, batchIsActive, onRefreshBatch, open]);

  const updateField = (field, value) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));
    setPreview(null);
    setPreviewSignature('');
    setBatch(null);
    if (errors[field]) {
      setErrors((prev) => ({
        ...prev,
        [field]: '',
      }));
    }
  };

  const validate = () => {
    const nextErrors = {};

    if (!normalizeDateInput(formData.tanggal)) {
      nextErrors.tanggal = 'Tanggal wajib diisi';
    }

    if (!formData.scope_type) {
      nextErrors.scope_type = 'Scope wajib dipilih';
    }

    if (formData.scope_type === 'classes' && selectedClasses.length === 0) {
      nextErrors.kelas_ids = 'Pilih minimal satu kelas';
    }

    if (formData.scope_type === 'levels' && selectedLevels.length === 0) {
      nextErrors.tingkat_ids = 'Pilih minimal satu tingkat';
    }

    if (!formData.status) {
      nextErrors.status = 'Status wajib dipilih';
    }

    if (formData.status === 'terlambat' && !formData.jam_masuk) {
      nextErrors.jam_masuk = 'Jam masuk wajib untuk status terlambat';
    }

    if (!formData.reason || formData.reason.trim().length < 10) {
      nextErrors.reason = 'Alasan minimal 10 karakter';
    }

    return nextErrors;
  };

  const buildPayload = () => ({
    tanggal: normalizeDateInput(formData.tanggal),
    scope_type: formData.scope_type,
    kelas_ids: selectedClasses.map((kelas) => kelas.id),
    tingkat_ids: selectedLevels.map((tingkat) => tingkat.id),
    status: formData.status,
    jam_masuk: formData.jam_masuk || null,
    jam_pulang: formData.jam_pulang || null,
    keterangan: formData.keterangan || null,
    reason: formData.reason,
  });

  const handlePreview = async () => {
    const nextErrors = validate();
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    try {
      setPreviewing(true);
      const payload = buildPayload();
      const nextPreview = await onPreview(payload);
      setPreview(nextPreview);
      setPreviewSignature(currentSignature);
      setBatch(null);
      if (errors.preview) {
        setErrors((prev) => ({ ...prev, preview: '' }));
      }
    } catch (previewError) {
      if (previewError?.response?.data?.errors) {
        setErrors((prev) => ({
          ...prev,
          ...previewError.response.data.errors,
        }));
      }
    } finally {
      setPreviewing(false);
    }
  };

  const handleStart = async () => {
    const nextErrors = validate();
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    if (!hasFreshPreview) {
      setErrors((prev) => ({
        ...prev,
        preview: 'Lakukan pratinjau insiden server dulu sebelum menjalankan batch.',
      }));
      return;
    }

    try {
      setStarting(true);
      const nextBatch = await onStart(buildPayload());
      setBatch(nextBatch || null);
    } catch (startError) {
      if (startError?.response?.data?.errors) {
        setErrors((prev) => ({
          ...prev,
          ...startError.response.data.errors,
        }));
      }
    } finally {
      setStarting(false);
    }
  };

  const optionSummary = options?.summary || {};
  const classes = options?.classes || [];
  const levels = options?.levels || [];
  const batchStatusSummary = batch
    ? getBatchStatusSummary(batch.status, Number(batch.progress_percentage || 0))
    : null;

  return (
    <Dialog open={open} onClose={() => !starting && !polling && onClose()} maxWidth="lg" fullWidth>
      <DialogTitle>Insiden Server</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={3} sx={{ pt: 1 }}>
          <Alert severity="info" icon={<Database className="w-4 h-4" />}>
            <Typography variant="subtitle2" className="font-semibold">
              Mode khusus gangguan server
            </Typography>
            <Typography variant="body2">
              Fitur ini menghitung otomatis siswa yang belum punya absensi pada tanggal terpilih, lalu melewati yang sudah tercatat, sudah izin, atau memang tidak wajib absen.
            </Typography>
          </Alert>

          <Box className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <PaperStat
              icon={<School className="w-5 h-5 text-blue-600" />}
              label="Siswa Terkelola"
              value={optionSummary.manageable_students_count || 0}
            />
            <PaperStat
              icon={<CheckCircle2 className="w-5 h-5 text-emerald-600" />}
              label="Kelas Terkelola"
              value={optionSummary.manageable_classes_count || 0}
            />
            <PaperStat
              icon={<CheckCircle2 className="w-5 h-5 text-violet-600" />}
              label="Tingkat Terkelola"
              value={optionSummary.manageable_levels_count || 0}
            />
            <PaperStat
              icon={<AlertCircle className="w-5 h-5 text-amber-600" />}
              label="Mode"
              value="Auto Detect"
            />
          </Box>

          <Box className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <TextField
              label="Tanggal"
              type="date"
              value={formData.tanggal}
              onChange={(event) => updateField('tanggal', event.target.value)}
              InputLabelProps={{ shrink: true }}
              inputProps={{ max: normalizedServerDate }}
              error={Boolean(errors.tanggal)}
              helperText={errors.tanggal}
              fullWidth
            />
            <TextField
              select
              label="Scope"
              value={formData.scope_type}
              onChange={(event) => updateField('scope_type', event.target.value)}
              error={Boolean(errors.scope_type)}
              helperText={errors.scope_type}
              fullWidth
            >
              {(options?.scope_types || []).map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              select
              label="Status Default"
              value={formData.status}
              onChange={(event) => updateField('status', event.target.value)}
              error={Boolean(errors.status)}
              helperText={errors.status}
              fullWidth
            >
              {STATUS_OPTIONS.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </TextField>
          </Box>

          {formData.scope_type === 'classes' && (
            <Autocomplete
              multiple
              options={classes}
              value={selectedClasses}
              onChange={(_, value) => {
                setSelectedClasses(value);
                setPreview(null);
                setBatch(null);
                setPreviewSignature('');
                if (errors.kelas_ids) {
                  setErrors((prev) => ({ ...prev, kelas_ids: '' }));
                }
              }}
              disableCloseOnSelect
              filterSelectedOptions
              loading={loadingOptions}
              getOptionLabel={(option) => option.nama_lengkap || option.nama_kelas || `Kelas #${option.id}`}
              isOptionEqualToValue={(option, value) => option.id === value.id}
              renderTags={(value, getTagProps) =>
                value.map((option, index) => (
                  <Chip
                    {...getTagProps({ index })}
                    key={option.id}
                    label={`${option.nama_lengkap || option.nama_kelas} (${option.active_students_count || 0})`}
                    size="small"
                  />
                ))
              }
              renderInput={(params) => (
                <TextField
                  {...params}
                  label="Kelas"
                  placeholder="Pilih satu atau lebih kelas"
                  error={Boolean(errors.kelas_ids)}
                  helperText={errors.kelas_ids || 'Untuk insiden per kelas atau beberapa kelas sekaligus'}
                />
              )}
            />
          )}

          {formData.scope_type === 'levels' && (
            <Autocomplete
              multiple
              options={levels}
              value={selectedLevels}
              onChange={(_, value) => {
                setSelectedLevels(value);
                setPreview(null);
                setBatch(null);
                setPreviewSignature('');
                if (errors.tingkat_ids) {
                  setErrors((prev) => ({ ...prev, tingkat_ids: '' }));
                }
              }}
              disableCloseOnSelect
              filterSelectedOptions
              loading={loadingOptions}
              getOptionLabel={(option) => option.nama || `Tingkat #${option.id}`}
              isOptionEqualToValue={(option, value) => option.id === value.id}
              renderTags={(value, getTagProps) =>
                value.map((option, index) => (
                  <Chip
                    {...getTagProps({ index })}
                    key={option.id}
                    label={`${option.nama} (${option.active_students_count || 0})`}
                    size="small"
                  />
                ))
              }
              renderInput={(params) => (
                <TextField
                  {...params}
                  label="Tingkat"
                  placeholder="Pilih satu atau lebih tingkat"
                  error={Boolean(errors.tingkat_ids)}
                  helperText={errors.tingkat_ids || 'Untuk insiden per tingkat atau beberapa tingkat sekaligus'}
                />
              )}
            />
          )}

          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <TextField
              label="Jam Masuk"
              type="time"
              value={formData.jam_masuk}
              onChange={(event) => updateField('jam_masuk', event.target.value)}
              InputLabelProps={{ shrink: true }}
              error={Boolean(errors.jam_masuk)}
              helperText={errors.jam_masuk || 'Opsional, wajib untuk status terlambat'}
              fullWidth
            />
            <TextField
              label="Jam Pulang"
              type="time"
              value={formData.jam_pulang}
              onChange={(event) => updateField('jam_pulang', event.target.value)}
              InputLabelProps={{ shrink: true }}
              error={Boolean(errors.jam_pulang)}
              helperText={errors.jam_pulang}
              fullWidth
            />
          </Box>

          <TextField
            label="Keterangan"
            value={formData.keterangan}
            onChange={(event) => updateField('keterangan', event.target.value)}
            placeholder="Opsional"
            fullWidth
          />

          <TextField
            label="Alasan Insiden"
            value={formData.reason}
            onChange={(event) => updateField('reason', event.target.value)}
            multiline
            minRows={2}
            error={Boolean(errors.reason)}
            helperText={errors.reason || 'Contoh: gangguan server pusat saat jam masuk seluruh siswa'}
            fullWidth
          />

          {errors.preview && (
            <Alert severity="warning">{errors.preview}</Alert>
          )}

          {preview && (
            <Box className="space-y-3">
              <Alert severity="success">
                <Typography variant="subtitle2" className="font-semibold">
                  Pratinjau insiden server
                </Typography>
                <Typography variant="body2">
                  Scope {preview.scope_label || '-'} pada {preview.tanggal || '-'}.
                </Typography>
              </Alert>

              <Box className="grid grid-cols-1 md:grid-cols-5 gap-3">
                <PreviewStat label="Total Scope" value={preview.total_scope_users || 0} surfaceClass="bg-slate-50 border-slate-200" />
                <PreviewStat label="Sudah Tercatat" value={preview.existing_attendance_count || 0} surfaceClass="bg-blue-50 border-blue-200" />
                <PreviewStat label="Sudah Izin" value={preview.approved_leave_count || 0} surfaceClass="bg-emerald-50 border-emerald-200" />
                <PreviewStat label="Non Wajib/Libur" value={(preview.non_required_count || 0) + (preview.non_working_day_count || 0)} surfaceClass="bg-amber-50 border-amber-200" />
                <PreviewStat label="Siap Dibuat" value={preview.eligible_missing_count || 0} surfaceClass="bg-purple-50 border-purple-200" />
              </Box>

              {Array.isArray(preview.sample_eligible_students) && preview.sample_eligible_students.length > 0 && (
                <Box className="rounded-xl border border-purple-200 bg-purple-50 px-4 py-3">
                  <Typography variant="subtitle2" className="font-semibold text-purple-900">
                    Contoh siswa yang akan dibuatkan absensi
                  </Typography>
                  <Stack spacing={1} sx={{ mt: 1.5 }}>
                    {preview.sample_eligible_students.map((item) => (
                      <Box
                        key={`eligible-${item.id}`}
                        className="rounded-lg border border-purple-200 bg-white px-3 py-2"
                      >
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {item.nama_lengkap}
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          {item.kelas || item.email || '-'}
                        </Typography>
                      </Box>
                    ))}
                  </Stack>
                </Box>
              )}
            </Box>
          )}

          {batch && (
            <Box className="space-y-3">
              <Alert severity={batchStatusSummary?.severity || 'info'}>
                <Typography variant="subtitle2" className="font-semibold">
                  {batchStatusSummary?.title || `Batch insiden server #${batch.id}`}
                </Typography>
                <Typography variant="body2">
                  Batch insiden server #{batch.id}. {batchStatusSummary?.description || `Progress ${batch.progress_percentage || 0}%`}.
                </Typography>
              </Alert>

              <LinearProgress
                variant="determinate"
                value={Math.max(0, Math.min(100, Number(batch.progress_percentage || 0)))}
              />

              <Box className="grid grid-cols-1 md:grid-cols-6 gap-3">
                <PreviewStat label="Scope" value={batch.total_scope_users || 0} surfaceClass="bg-slate-50 border-slate-200" />
                <PreviewStat label="Dibuat" value={batch.created_count || 0} surfaceClass="bg-emerald-50 border-emerald-200" />
                <PreviewStat label="Skip Existing" value={batch.skipped_existing_count || 0} surfaceClass="bg-blue-50 border-blue-200" />
                <PreviewStat label="Skip Izin" value={batch.skipped_leave_count || 0} surfaceClass="bg-cyan-50 border-cyan-200" />
                <PreviewStat label="Skip Non Wajib" value={(batch.skipped_non_required_count || 0) + (batch.skipped_non_working_count || 0)} surfaceClass="bg-amber-50 border-amber-200" />
                <PreviewStat label="Gagal" value={batch.failed_count || 0} surfaceClass="bg-rose-50 border-rose-200" />
              </Box>

              {batch.result_export_available && !batchIsActive && (
                <Box className="flex flex-col md:flex-row md:items-center md:justify-end gap-3">
                  <TextField
                    select
                    size="small"
                    label="Filter Export"
                    value={exportGroup}
                    onChange={(event) => setExportGroup(event.target.value)}
                    sx={{ minWidth: 220 }}
                  >
                    <MenuItem value="all">Semua hasil</MenuItem>
                    <MenuItem value="created">Hanya dibuat</MenuItem>
                    <MenuItem value="skipped">Hanya dilewati</MenuItem>
                    <MenuItem value="failed">Hanya gagal</MenuItem>
                  </TextField>
                  <Button
                    variant="outlined"
                    size="small"
                    startIcon={<Download className="w-4 h-4" />}
                    onClick={() => onExport?.(batch.id, 'xlsx', exportGroup)}
                  >
                    Unduh Hasil Batch
                  </Button>
                </Box>
              )}

              {Array.isArray(batch.sample_failures) && batch.sample_failures.length > 0 && (
                <Box className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                  <Typography variant="subtitle2" className="font-semibold text-rose-900">
                    Sampel kegagalan
                  </Typography>
                  <Stack spacing={1} sx={{ mt: 1.5 }}>
                    {batch.sample_failures.slice(0, 10).map((item, index) => (
                      <Box
                        key={`failure-${item.user_id || index}`}
                        className="rounded-lg border border-rose-200 bg-white px-3 py-2"
                      >
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {item.nama_lengkap || `User #${item.user_id}`}
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          {item.message || 'Gagal diproses'}
                        </Typography>
                      </Box>
                    ))}
                  </Stack>
                </Box>
              )}
            </Box>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={starting || polling}>
          Tutup
        </Button>
        <Button variant="outlined" onClick={handlePreview} disabled={previewing || starting || loadingOptions}>
          {previewing ? 'Memeriksa...' : 'Pratinjau Insiden'}
        </Button>
        <Button variant="contained" onClick={handleStart} disabled={starting || previewing || !hasFreshPreview || batchIsActive}>
          {starting ? 'Menjadwalkan...' : 'Jalankan Batch'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

const PaperStat = ({ icon, label, value }) => (
  <Box className="rounded-xl border border-gray-200 bg-white px-4 py-3">
    <div className="flex items-center gap-3">
      {icon}
      <div>
        <Typography variant="caption" className="uppercase tracking-wide text-gray-500">
          {label}
        </Typography>
        <Typography variant="h6" className="font-bold text-gray-900">
          {value}
        </Typography>
      </div>
    </div>
  </Box>
);

const PreviewStat = ({ label, value, surfaceClass }) => (
  <Box className={`rounded-xl border px-4 py-3 ${surfaceClass}`}>
    <Typography variant="caption" className="uppercase tracking-wide text-gray-500">
      {label}
    </Typography>
    <Typography variant="h6" className="font-bold text-gray-900">
      {value}
    </Typography>
  </Box>
);

export default ManualAttendanceIncidentModal;

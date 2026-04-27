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
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { toServerDateInput } from '../services/serverClock';

const OPERATION_OPTIONS = [
  {
    value: 'create_missing',
    label: 'Buat untuk yang belum ada',
    description: 'Gunakan saat siswa belum punya row absensi pada tanggal tersebut. Item yang sudah punya data akan gagal.',
  },
  {
    value: 'correct_existing',
    label: 'Koreksi yang sudah ada',
    description: 'Gunakan saat data sudah ada tetapi salah, termasuk kasus auto alpha. Item tanpa data existing akan gagal.',
  },
];

const getUserLabel = (user) => {
  if (!user) {
    return '-';
  }

  return user.nama_lengkap || user.name || user.email || `User #${user.id}`;
};

const toMinutes = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return null;
  }

  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (
    Number.isNaN(hour) ||
    Number.isNaN(minute) ||
    hour < 0 ||
    hour > 23 ||
    minute < 0 ||
    minute > 59
  ) {
    return null;
  }

  return (hour * 60) + minute;
};

const normalizeDateInput = (value) => toServerDateInput(value) || '';

const ManualAttendanceBulkModal = ({
  open,
  onClose,
  onPreview,
  onSubmit,
  operation = 'create_missing',
  users = [],
  serverDate = '',
}) => {
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [formData, setFormData] = useState({
    tanggal: '',
    status: '',
    jam_masuk: '',
    jam_pulang: '',
    keterangan: '',
    reason: '',
  });
  const [errors, setErrors] = useState({});
  const [previewing, setPreviewing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [preview, setPreview] = useState(null);
  const [previewSignature, setPreviewSignature] = useState('');
  const [lastPreviewUsers, setLastPreviewUsers] = useState([]);
  const [results, setResults] = useState(null);
  const [lastSubmittedUsers, setLastSubmittedUsers] = useState([]);
  const normalizedServerDate = useMemo(() => normalizeDateInput(serverDate), [serverDate]);
  const normalizedFormDate = useMemo(() => normalizeDateInput(formData.tanggal), [formData.tanggal]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setSelectedUsers([]);
    setFormData({
      tanggal: normalizedServerDate,
      status: '',
      jam_masuk: '',
      jam_pulang: '',
      keterangan: '',
      reason: '',
    });
    setErrors({});
    setPreviewing(false);
    setSubmitting(false);
    setPreview(null);
    setPreviewSignature('');
    setLastPreviewUsers([]);
    setResults(null);
    setLastSubmittedUsers([]);
  }, [normalizedServerDate, open]);

  const operationInfo = useMemo(
    () => OPERATION_OPTIONS.find((option) => option.value === operation) || OPERATION_OPTIONS[0],
    [operation]
  );

  const failedEntries = useMemo(() => {
    if (!results?.results) {
      return [];
    }

    return results.results
      .filter((item) => !item.success)
      .map((item) => ({
        ...item,
        user: lastSubmittedUsers[item.index] || null,
      }));
  }, [lastSubmittedUsers, results]);

  const previewFailedEntries = useMemo(() => {
    if (!preview?.results) {
      return [];
    }

    return preview.results
      .filter((item) => !item.success)
      .map((item) => ({
        ...item,
        user: lastPreviewUsers[item.index] || null,
      }));
  }, [lastPreviewUsers, preview]);

  const currentSignature = useMemo(() => JSON.stringify({
    operation,
    user_ids: selectedUsers.map((user) => user.id),
    tanggal: normalizedFormDate,
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
    formData.status,
    normalizedFormDate,
    operation,
    selectedUsers,
  ]);

  const hasFreshPreview = Boolean(preview) && previewSignature === currentSignature;

  const updateField = (field, value) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));

    setPreview(null);
    setPreviewSignature('');
    setLastPreviewUsers([]);
    setResults(null);
    setLastSubmittedUsers([]);

    if (errors[field]) {
      setErrors((prev) => ({
        ...prev,
        [field]: '',
      }));
    }
  };

  const validate = () => {
    const nextErrors = {};

    if (!formData.tanggal) {
      nextErrors.tanggal = 'Tanggal wajib diisi';
    }

    if (selectedUsers.length === 0) {
      nextErrors.user_ids = 'Pilih minimal satu siswa';
    }

    if (selectedUsers.length > 100) {
      nextErrors.user_ids = 'Maksimal 100 siswa per proses';
    }

    if (!formData.status) {
      nextErrors.status = 'Status wajib dipilih';
    }

    if (formData.status === 'terlambat' && !formData.jam_masuk) {
      nextErrors.jam_masuk = 'Jam masuk wajib untuk status terlambat';
    }

    if (formData.jam_masuk && formData.jam_pulang) {
      const jamMasuk = toMinutes(formData.jam_masuk);
      const jamPulang = toMinutes(formData.jam_pulang);

      if (jamMasuk !== null && jamPulang !== null && jamPulang <= jamMasuk) {
        nextErrors.jam_pulang = 'Jam pulang harus setelah jam masuk';
      }
    }

    if (!formData.reason || formData.reason.trim().length < 10) {
      nextErrors.reason = 'Alasan minimal 10 karakter';
    }

    return nextErrors;
  };

  const handleSubmit = async () => {
    const nextErrors = validate();
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    if (!hasFreshPreview) {
      setErrors((prev) => ({
        ...prev,
        preview: 'Lakukan pratinjau batch dulu sebelum memproses.',
      }));
      return;
    }

    const attendanceList = selectedUsers.map((user) => ({
      user_id: user.id,
      tanggal: normalizedFormDate,
      status: formData.status,
      jam_masuk: formData.jam_masuk || null,
      jam_pulang: formData.jam_pulang || null,
      keterangan: formData.keterangan || null,
      reason: formData.reason,
    }));

    setSubmitting(true);
    try {
      setLastSubmittedUsers(selectedUsers);
      const response = await onSubmit(operation, attendanceList);
      setResults(response || null);
    } catch (submitError) {
      if (submitError?.response?.data?.errors) {
        setErrors(submitError.response.data.errors);
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handlePreview = async () => {
    const nextErrors = validate();
    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    const attendanceList = selectedUsers.map((user) => ({
      user_id: user.id,
      tanggal: normalizedFormDate,
      status: formData.status,
      jam_masuk: formData.jam_masuk || null,
      jam_pulang: formData.jam_pulang || null,
      keterangan: formData.keterangan || null,
      reason: formData.reason,
    }));

    setPreviewing(true);
    try {
      const response = await onPreview(operation, attendanceList);
      setPreview(response || null);
      setPreviewSignature(currentSignature);
      setLastPreviewUsers(selectedUsers);
      setResults(null);
      setLastSubmittedUsers([]);
      if (errors.preview) {
        setErrors((prev) => ({
          ...prev,
          preview: '',
        }));
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

  return (
    <Dialog open={open} onClose={() => !submitting && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>
        {operation === 'create_missing' ? 'Absensi Massal' : 'Koreksi Massal'}
      </DialogTitle>
      <DialogContent dividers>
        <Stack spacing={3} sx={{ pt: 1 }}>
          <Alert severity={operation === 'create_missing' ? 'info' : 'warning'}>
            <Typography variant="subtitle2" className="font-semibold">
              {operationInfo.label}
            </Typography>
            <Typography variant="body2">
              {operationInfo.description}
            </Typography>
          </Alert>

          <Autocomplete
            multiple
            options={users}
            value={selectedUsers}
            onChange={(_, value) => {
              setSelectedUsers(value);
              setPreview(null);
              setPreviewSignature('');
              setLastPreviewUsers([]);
              setResults(null);
              setLastSubmittedUsers([]);
              if (errors.user_ids) {
                setErrors((prev) => ({
                  ...prev,
                  user_ids: '',
                }));
              }
            }}
            disableCloseOnSelect
            filterSelectedOptions
            limitTags={4}
            getOptionLabel={getUserLabel}
            isOptionEqualToValue={(option, value) => option.id === value.id}
            renderTags={(value, getTagProps) =>
              value.map((option, index) => (
                <Chip
                  {...getTagProps({ index })}
                  key={option.id}
                  label={getUserLabel(option)}
                  size="small"
                />
              ))
            }
            renderInput={(params) => (
              <TextField
                {...params}
                label="Siswa"
                placeholder="Pilih siswa yang akan diproses"
                error={Boolean(errors.user_ids)}
                helperText={errors.user_ids || 'Maksimal 100 siswa per proses massal'}
              />
            )}
          />

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
              label="Status"
              value={formData.status}
              onChange={(event) => updateField('status', event.target.value)}
              error={Boolean(errors.status)}
              helperText={errors.status}
              fullWidth
            >
              <MenuItem value="hadir">Hadir</MenuItem>
              <MenuItem value="terlambat">Terlambat</MenuItem>
              <MenuItem value="izin">Izin</MenuItem>
              <MenuItem value="sakit">Sakit</MenuItem>
              <MenuItem value="alpha">Alpha</MenuItem>
            </TextField>
            <TextField
              label="Jam Masuk"
              type="time"
              value={formData.jam_masuk}
              onChange={(event) => updateField('jam_masuk', event.target.value)}
              InputLabelProps={{ shrink: true }}
              error={Boolean(errors.jam_masuk)}
              helperText={errors.jam_masuk || 'Wajib untuk status terlambat'}
              fullWidth
            />
          </Box>

          <Box className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <TextField
              label="Keterangan"
              value={formData.keterangan}
              onChange={(event) => updateField('keterangan', event.target.value)}
              placeholder="Opsional"
              fullWidth
            />
          </Box>

          <TextField
            label="Alasan Proses Massal"
            value={formData.reason}
            onChange={(event) => updateField('reason', event.target.value)}
            multiline
            minRows={2}
            error={Boolean(errors.reason)}
            helperText={errors.reason || 'Contoh: server timeout massal saat jam masuk kelas X-A'}
            fullWidth
          />

          <Alert severity="info">
            <Typography variant="body2">
              Ringkasan proses: {selectedUsers.length} siswa, tanggal {normalizedFormDate || '-'}, status {formData.status || '-'}.
            </Typography>
          </Alert>

          {errors.preview && (
            <Alert severity="warning">
              {errors.preview}
            </Alert>
          )}

          {preview && (
            <Box className="space-y-3">
              <Alert severity={(preview.blocked_count || 0) > 0 ? 'warning' : 'success'}>
                <Typography variant="subtitle2" className="font-semibold">
                  Pratinjau batch
                </Typography>
                <Typography variant="body2">
                  Total {preview.total || 0}, siap diproses {preview.ready_count || 0}, berpotensi gagal {preview.blocked_count || 0}.
                </Typography>
              </Alert>

              {previewFailedEntries.length > 0 && (
                <Box className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                  <Typography variant="subtitle2" className="font-semibold text-amber-900">
                    Potensi gagal saat diproses
                  </Typography>
                  <Stack spacing={1} sx={{ mt: 1.5 }}>
                    {previewFailedEntries.slice(0, 12).map((item) => (
                      <Box
                        key={`preview-${item.index}-${item.user?.id || 'unknown'}`}
                        className="rounded-lg border border-amber-200 bg-white px-3 py-2"
                      >
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {getUserLabel(item.user)}
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          {item.message || 'Berpotensi gagal diproses'}
                        </Typography>
                      </Box>
                    ))}
                    {previewFailedEntries.length > 12 && (
                      <Typography variant="caption" className="text-amber-800">
                        {previewFailedEntries.length - 12} item lain disembunyikan dari ringkasan ini.
                      </Typography>
                    )}
                  </Stack>
                </Box>
              )}
            </Box>
          )}

          {results && (
            <Box className="space-y-3">
              <Alert severity={(results.failed_count || 0) > 0 ? 'warning' : 'success'}>
                <Typography variant="subtitle2" className="font-semibold">
                  Hasil proses massal
                </Typography>
                <Typography variant="body2">
                  Total {results.total || 0}, berhasil {results.success_count || 0}, gagal {results.failed_count || 0}.
                </Typography>
              </Alert>

              {failedEntries.length > 0 && (
                <Box className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                  <Typography variant="subtitle2" className="font-semibold text-amber-900">
                    Item gagal
                  </Typography>
                  <Stack spacing={1} sx={{ mt: 1.5 }}>
                    {failedEntries.slice(0, 12).map((item) => (
                      <Box
                        key={`${item.index}-${item.user?.id || 'unknown'}`}
                        className="rounded-lg border border-amber-200 bg-white px-3 py-2"
                      >
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {getUserLabel(item.user)}
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          {item.message || 'Gagal diproses'}
                        </Typography>
                      </Box>
                    ))}
                    {failedEntries.length > 12 && (
                      <Typography variant="caption" className="text-amber-800">
                        {failedEntries.length - 12} item gagal lain disembunyikan dari ringkasan ini.
                      </Typography>
                    )}
                  </Stack>
                </Box>
              )}
            </Box>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={submitting}>
          Tutup
        </Button>
        <Button variant="outlined" onClick={handlePreview} disabled={previewing || submitting}>
          {previewing ? 'Memeriksa...' : 'Pratinjau Batch'}
        </Button>
        <Button variant="contained" onClick={handleSubmit} disabled={submitting || previewing || !hasFreshPreview}>
          {submitting
            ? 'Memproses...'
            : operation === 'create_missing'
              ? 'Proses Bulk Create'
              : 'Proses Bulk Koreksi'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ManualAttendanceBulkModal;

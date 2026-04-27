import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material';
import toast from 'react-hot-toast';
import siswaExtendedAPI from '../../services/siswaExtendedService';
import { useAuth } from '../../hooks/useAuth';
import { formatServerDateTime } from '../../services/serverClock';

const toDateTimeLocal = (isoString) => {
  if (!isoString) {
    return '';
  }

  const formatted = formatServerDateTime(isoString, 'sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });

  return formatted ? formatted.replace(' ', 'T').slice(0, 16) : '';
};

const normalizeSettings = (row = null) => ({
  is_enabled: Boolean(row?.is_enabled),
  open_at: toDateTimeLocal(row?.open_at),
  close_at: toDateTimeLocal(row?.close_at),
  notes: row?.notes || '',
  is_open_now: Boolean(row?.is_open_now),
});

const PromotionWindowSettingModal = ({
  open,
  onClose,
  currentKelas = null,
  activeTahunAjaran = null,
  onRefresh = null,
}) => {
  const { hasPermission, hasAnyRole } = useAuth();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState(normalizeSettings());
  const [hasExistingSetting, setHasExistingSetting] = useState(false);

  const canManageSettings = hasPermission('manage_tahun_ajaran') || hasAnyRole(['Super Admin', 'Admin', 'Wakasek Kurikulum']);

  const kelasId = Number(currentKelas?.id || 0);
  const tahunAjaranId = Number(activeTahunAjaran?.id || 0);

  const loadSettings = useCallback(async () => {
    if (!open || !kelasId || !tahunAjaranId) {
      return;
    }

    try {
      setLoading(true);
      const response = await siswaExtendedAPI.getWaliPromotionSettings({
        kelas_id: kelasId,
        tahun_ajaran_id: tahunAjaranId,
      });

      const rows = response?.data?.data;
      const matchedRow = Array.isArray(rows)
        ? rows.find((item) =>
          Number(item?.kelas?.id) === kelasId && Number(item?.tahun_ajaran?.id) === tahunAjaranId)
        : null;

      setHasExistingSetting(Boolean(matchedRow));
      setFormData(normalizeSettings(matchedRow));
    } catch (error) {
      console.error('Error loading promotion window settings:', error);
      toast.error(error?.response?.data?.message || 'Gagal memuat pengaturan window naik kelas');
      setHasExistingSetting(false);
      setFormData(normalizeSettings());
    } finally {
      setLoading(false);
    }
  }, [kelasId, open, tahunAjaranId]);

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  const statusChip = useMemo(() => {
    if (!formData.is_enabled) {
      return { label: 'Window OFF', color: 'default' };
    }

    if (formData.is_open_now) {
      return { label: 'Window Aktif', color: 'success' };
    }

    return { label: 'Window ON (di luar jam aktif)', color: 'warning' };
  }, [formData.is_enabled, formData.is_open_now]);

  const handleSave = async () => {
    if (!kelasId || !tahunAjaranId) {
      toast.error('Kelas atau tahun ajaran belum valid.');
      return;
    }

    try {
      setSaving(true);
      await siswaExtendedAPI.upsertWaliPromotionSetting({
        kelas_id: kelasId,
        tahun_ajaran_id: tahunAjaranId,
        is_enabled: Boolean(formData.is_enabled),
        open_at: formData.open_at || null,
        close_at: formData.close_at || null,
        notes: formData.notes?.trim() || null,
      });

      toast.success('Pengaturan window naik kelas berhasil disimpan.');
      await loadSettings();
      if (onRefresh) {
        onRefresh();
      }
    } catch (error) {
      console.error('Error saving promotion window settings:', error);
      toast.error(error?.response?.data?.message || 'Gagal menyimpan pengaturan window naik kelas');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onClose={saving ? undefined : onClose} maxWidth="sm" fullWidth>
      <DialogTitle>Pengaturan Window Naik Kelas</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          <Alert severity={canManageSettings ? 'info' : 'warning'}>
            {canManageSettings
              ? 'Atur ON/OFF dan rentang waktu agar wali kelas bisa memproses naik kelas.'
              : 'Anda hanya dapat melihat status window. Perubahan hanya bisa oleh kurikulum/admin.'}
          </Alert>

          <Box display="flex" gap={1} flexWrap="wrap">
            <Chip label={`Kelas: ${currentKelas?.namaKelas || '-'}`} variant="outlined" color="primary" />
            <Chip label={`Tahun Ajaran: ${activeTahunAjaran?.nama || '-'}`} variant="outlined" color="secondary" />
            <Chip label={statusChip.label} color={statusChip.color} />
          </Box>

          {!hasExistingSetting && (
            <Alert severity="warning">
              Belum ada setting untuk kombinasi kelas dan tahun ajaran ini. Default sistem adalah OFF.
            </Alert>
          )}

          {loading ? (
            <Box py={5} textAlign="center">
              <CircularProgress size={24} />
            </Box>
          ) : (
            <>
              <FormControlLabel
                control={(
                  <Switch
                    checked={formData.is_enabled}
                    onChange={(event) => setFormData((prev) => ({ ...prev, is_enabled: event.target.checked }))}
                    disabled={!canManageSettings || saving}
                  />
                )}
                label="Aktifkan window naik kelas"
              />

              <TextField
                label="Buka Mulai"
                type="datetime-local"
                value={formData.open_at}
                onChange={(event) => setFormData((prev) => ({ ...prev, open_at: event.target.value }))}
                disabled={!canManageSettings || saving}
                InputLabelProps={{ shrink: true }}
                fullWidth
              />

              <TextField
                label="Tutup Pada"
                type="datetime-local"
                value={formData.close_at}
                onChange={(event) => setFormData((prev) => ({ ...prev, close_at: event.target.value }))}
                disabled={!canManageSettings || saving}
                InputLabelProps={{ shrink: true }}
                fullWidth
              />

              <TextField
                label="Catatan"
                multiline
                minRows={3}
                value={formData.notes}
                onChange={(event) => setFormData((prev) => ({ ...prev, notes: event.target.value }))}
                disabled={!canManageSettings || saving}
                fullWidth
              />

              {formData.open_at && formData.close_at && formData.open_at > formData.close_at && (
                <Typography variant="caption" color="error">
                  Waktu tutup harus lebih besar atau sama dengan waktu buka.
                </Typography>
              )}
            </>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={saving}>Tutup</Button>
        {canManageSettings && (
          <Button
            onClick={handleSave}
            variant="contained"
            disabled={saving || loading}
          >
            {saving ? 'Menyimpan...' : 'Simpan'}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default PromotionWindowSettingModal;

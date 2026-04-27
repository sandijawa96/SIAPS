import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  LinearProgress,
  Typography,
} from '@mui/material';
import { Camera, RefreshCw, Trash2, Upload } from 'lucide-react';
import { faceTemplatesAPI } from '../../services/api';
import { resolveProfilePhotoUrl } from '../../utils/profilePhoto';
import { useAuth } from '../../hooks/useAuth';
import { formatServerDateTime } from '../../services/serverClock';

const FaceTemplateModal = ({ open, user, onClose, onUpdated }) => {
  const { hasPermission } = useAuth();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [templateData, setTemplateData] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');

  const userId = user?.id ?? user?.user_id ?? user?.data_pribadi_siswa?.user_id ?? null;
  const displayName = user?.data_pribadi_siswa?.nama_lengkap || user?.nama_lengkap || user?.name || '-';

  const activeTemplate = templateData?.active_template || null;
  const hasActiveTemplate = Boolean(templateData?.has_active_template);
  const submissionState = templateData?.submission_state || null;
  const canManageTemplates = hasPermission('manage_attendance_settings');
  const canUnlockSubmitQuota = canManageTemplates || hasPermission('unlock_face_template_submit_quota');
  const selfSubmitCount = Number(submissionState?.self_submit_count || 0);
  const selfSubmitLimit = Number(submissionState?.limit || 3);
  const unlockAllowanceRemaining = Number(submissionState?.unlock_allowance_remaining || 0);
  const needsAdminUnlock = Boolean(submissionState?.requires_admin_unlock);
  const unlockActionDisabled = !canUnlockSubmitQuota || saving || !needsAdminUnlock || unlockAllowanceRemaining > 0;

  const selectedFileLabel = useMemo(() => {
    if (!selectedFile) {
      return 'Belum ada file dipilih';
    }

    const sizeMb = (selectedFile.size / (1024 * 1024)).toFixed(2);
    return `${selectedFile.name} (${sizeMb} MB)`;
  }, [selectedFile]);

  const loadTemplateState = async () => {
    if (!userId) {
      return;
    }

    setLoading(true);
    setErrorMessage('');

    try {
      const response = await faceTemplatesAPI.getForUser(userId);
      setTemplateData(response.data?.data || null);
    } catch (error) {
      setTemplateData(null);
      setErrorMessage(error.response?.data?.message || 'Gagal memuat status template wajah');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (open && userId) {
      setSelectedFile(null);
      loadTemplateState();
    }
  }, [open, userId]);

  const handleEnroll = async () => {
    if (!userId || !selectedFile) {
      setErrorMessage('Pilih file foto wajah terlebih dahulu.');
      return;
    }

    setSaving(true);
    setErrorMessage('');

    try {
      await faceTemplatesAPI.enroll({
        userId,
        file: selectedFile,
      });
      setSelectedFile(null);
      await loadTemplateState();
      onUpdated?.('Template wajah berhasil diperbarui', 'success');
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Gagal menyimpan template wajah');
    } finally {
      setSaving(false);
    }
  };

  const handleUnlock = async () => {
    if (!userId) {
      return;
    }

    setSaving(true);
    setErrorMessage('');

    try {
      const response = await faceTemplatesAPI.unlockSelfSubmit(userId);
      setTemplateData(response.data?.data || null);
      onUpdated?.(response.data?.message || '1 kali submit tambahan berhasil dibuka', 'success');
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Gagal membuka 1 kali submit tambahan');
    } finally {
      setSaving(false);
    }
  };

  const handleDeactivate = async () => {
    if (!activeTemplate?.id) {
      return;
    }

    setSaving(true);
    setErrorMessage('');

    try {
      await faceTemplatesAPI.deactivate(activeTemplate.id);
      await loadTemplateState();
      onUpdated?.('Template wajah berhasil dinonaktifkan', 'success');
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Gagal menonaktifkan template wajah');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onClose={saving ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle className="flex items-center gap-2">
        <Camera className="w-5 h-5" />
        Template Wajah Siswa
      </DialogTitle>

      <DialogContent dividers>
        {(loading || saving) && <LinearProgress className="mb-4" />}

        <Box className="flex items-center gap-3 mb-4">
          <Avatar
            src={resolveProfilePhotoUrl(user?.foto_profil_url || user?.foto_profil) || undefined}
            alt={displayName}
            sx={{ width: 48, height: 48 }}
          >
            {String(displayName || '?').charAt(0).toUpperCase()}
          </Avatar>
          <Box>
            <Typography variant="subtitle1" className="font-semibold">
              {displayName}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              NIS: {user?.data_pribadi_siswa?.nis || user?.nis || '-'}
            </Typography>
          </Box>
        </Box>

        {errorMessage ? (
          <Alert severity="error" className="mb-4">
            {errorMessage}
          </Alert>
        ) : null}

        <Box className="rounded-lg border border-gray-200 p-4 mb-4">
          <Box className="flex items-center justify-between gap-3 mb-2">
            <Typography variant="subtitle2" className="font-semibold">
              Status Template Aktif
            </Typography>
            <Chip
              label={hasActiveTemplate ? 'Aktif' : 'Belum ada'}
              color={hasActiveTemplate ? 'success' : 'default'}
              size="small"
              variant="outlined"
            />
          </Box>

          {hasActiveTemplate ? (
            <Box className="space-y-2">
              {activeTemplate?.template_url ? (
                <Box
                  component="img"
                  src={activeTemplate.template_url}
                  alt="Template wajah"
                  sx={{
                    width: 120,
                    height: 120,
                    objectFit: 'cover',
                    borderRadius: 2,
                    border: '1px solid #e5e7eb',
                  }}
                />
              ) : null}
              <Typography variant="body2" color="text.secondary">
                Engine: {activeTemplate?.template_version || '-'}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Quality: {activeTemplate?.quality_score ?? '-'}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Enrolled: {formatServerDateTime(activeTemplate?.enrolled_at, 'id-ID') || '-'}
              </Typography>
            </Box>
          ) : (
            <Typography variant="body2" color="text.secondary">
              Siswa ini belum memiliki template wajah aktif.
            </Typography>
          )}
        </Box>

        <Box className="rounded-lg border border-gray-200 p-4 mb-4">
          <Box className="flex items-center justify-between gap-3 mb-2">
            <Typography variant="subtitle2" className="font-semibold">
              Kuota Self Submit Siswa
            </Typography>
            <Chip
              label={submissionState?.can_self_submit_now ? 'Bisa submit' : 'Perlu unlock admin'}
              color={submissionState?.can_self_submit_now ? 'success' : 'warning'}
              size="small"
              variant="outlined"
            />
          </Box>

          <Box className="space-y-2">
            <Typography variant="body2" color="text.secondary">
              Self submit terpakai: {selfSubmitCount}/{selfSubmitLimit}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Sisa kuota dasar: {submissionState?.base_quota_remaining ?? selfSubmitLimit}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Jatah submit tambahan aktif: {unlockAllowanceRemaining}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Terakhir submit: {formatServerDateTime(submissionState?.last_submitted_at, 'id-ID') || '-'}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Unlock terakhir: {formatServerDateTime(submissionState?.last_unlocked_at, 'id-ID') || '-'}
              {submissionState?.last_unlocked_by_name ? ` oleh ${submissionState.last_unlocked_by_name}` : ''}
            </Typography>
          </Box>
        </Box>

        {!canManageTemplates ? (
          <Alert severity="info" className="mb-4">
            Role ini hanya bisa membuka 1 kali submit tambahan. Upload manual dan reset template tetap khusus pengelola absensi.
          </Alert>
        ) : null}

        <Divider className="my-4" />

        {canManageTemplates ? (
          <>
            <Typography variant="subtitle2" className="font-semibold mb-2">
              Upload Template Baru
            </Typography>
            <Typography variant="body2" color="text.secondary" className="mb-3">
              Gunakan foto wajah tunggal yang jelas. File baru akan menggantikan template aktif sebelumnya.
            </Typography>

            <Box className="flex flex-col gap-3">
              <Button
                variant="outlined"
                component="label"
                startIcon={<Upload className="w-4 h-4" />}
                disabled={saving}
              >
                Pilih Foto
                <input
                  type="file"
                  hidden
                  accept="image/*"
                  onChange={(event) => {
                    const nextFile = event.target.files?.[0] || null;
                    setSelectedFile(nextFile);
                  }}
                />
              </Button>

              <Typography variant="body2" color="text.secondary">
                {selectedFileLabel}
              </Typography>
            </Box>
          </>
        ) : null}
      </DialogContent>

      <DialogActions className="px-6 py-4 flex items-center justify-between">
        <Box className="flex items-center gap-2">
          <Button
            color="inherit"
            startIcon={<RefreshCw className="w-4 h-4" />}
            onClick={loadTemplateState}
            disabled={loading || saving || !userId}
          >
            Refresh
          </Button>
          {canUnlockSubmitQuota ? (
            <Button
              color="warning"
              onClick={handleUnlock}
              disabled={unlockActionDisabled}
            >
              {unlockAllowanceRemaining > 0 ? 'Unlock Aktif' : 'Buka 1x Submit'}
            </Button>
          ) : null}
          {canManageTemplates ? (
            <Button
              color="error"
              startIcon={<Trash2 className="w-4 h-4" />}
              onClick={handleDeactivate}
              disabled={saving || !activeTemplate?.id}
            >
              Reset Template
            </Button>
          ) : null}
        </Box>

        <Box className="flex items-center gap-2">
          <Button onClick={onClose} disabled={saving}>
            Tutup
          </Button>
          {canManageTemplates ? (
            <Button
              variant="contained"
              onClick={handleEnroll}
              disabled={saving || !selectedFile}
            >
              Simpan Template
            </Button>
          ) : null}
        </Box>
      </DialogActions>
    </Dialog>
  );
};

export default FaceTemplateModal;

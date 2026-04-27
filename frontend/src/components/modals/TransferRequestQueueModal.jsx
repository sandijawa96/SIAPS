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
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { Check, X, Ban, RefreshCcw } from 'lucide-react';
import toast from 'react-hot-toast';
import siswaExtendedAPI from '../../services/siswaExtendedService';
import { useAuth } from '../../hooks/useAuth';

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_COLOR = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  cancelled: 'default',
};

const extractTransferRows = (response) => {
  const payload = response?.data?.data ?? response?.data ?? {};
  const items = Array.isArray(payload?.items)
    ? payload.items
    : Array.isArray(payload)
      ? payload
      : [];

  return items;
};

const TransferRequestQueueModal = ({
  open,
  onClose,
  currentKelas = null,
  onRefresh = null,
}) => {
  const { user, hasRole, hasAnyRole } = useAuth();
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [statusFilter, setStatusFilter] = useState('pending');
  const [requests, setRequests] = useState([]);
  const [actionState, setActionState] = useState({
    type: '',
    item: null,
    note: '',
  });

  const canApprove = hasAnyRole(['Super Admin', 'Admin', 'Wakasek Kurikulum']);
  const isWaliKelas = hasRole('Wali Kelas');

  const loadRequests = useCallback(async () => {
    if (!open) {
      return;
    }

    try {
      setLoading(true);
      const response = await siswaExtendedAPI.getTransferRequests({
        status: statusFilter,
        per_page: 100,
      });
      setRequests(extractTransferRows(response));
    } catch (error) {
      console.error('Error loading transfer requests:', error);
      toast.error(error?.response?.data?.message || 'Gagal memuat antrean request pindah kelas');
      setRequests([]);
    } finally {
      setLoading(false);
    }
  }, [open, statusFilter]);

  useEffect(() => {
    loadRequests();
  }, [loadRequests]);

  const filteredRows = useMemo(() => {
    if (!currentKelas?.id) {
      return requests;
    }

    return requests.filter((item) => Number(item?.kelas_asal?.id) === Number(currentKelas.id));
  }, [currentKelas?.id, requests]);

  const openActionDialog = (type, item) => {
    setActionState({
      type,
      item,
      note: '',
    });
  };

  const closeActionDialog = () => {
    setActionState({
      type: '',
      item: null,
      note: '',
    });
  };

  const submitAction = async () => {
    if (!actionState.item?.id || !actionState.type) {
      return;
    }

    if (actionState.type === 'reject' && actionState.note.trim() === '') {
      toast.error('Alasan penolakan wajib diisi.');
      return;
    }

    try {
      setSubmitting(true);

      const payload = actionState.note.trim() !== ''
        ? { approval_note: actionState.note.trim() }
        : {};

      if (actionState.type === 'approve') {
        await siswaExtendedAPI.approveTransferRequest(actionState.item.id, payload);
      } else if (actionState.type === 'reject') {
        await siswaExtendedAPI.rejectTransferRequest(actionState.item.id, payload);
      } else if (actionState.type === 'cancel') {
        await siswaExtendedAPI.cancelTransferRequest(actionState.item.id, payload);
      }

      toast.success('Perubahan status request berhasil disimpan.');
      closeActionDialog();
      await loadRequests();
      if (onRefresh) {
        onRefresh();
      }
    } catch (error) {
      console.error('Error submitting transfer request action:', error);
      toast.error(error?.response?.data?.message || 'Gagal memproses aksi request');
    } finally {
      setSubmitting(false);
    }
  };

  const getActionTitle = () => {
    if (actionState.type === 'approve') {
      return 'Setujui Request';
    }
    if (actionState.type === 'reject') {
      return 'Tolak Request';
    }
    if (actionState.type === 'cancel') {
      return 'Batalkan Request';
    }
    return 'Aksi Request';
  };

  const getActionNoteLabel = () => {
    if (actionState.type === 'reject') {
      return 'Alasan Penolakan';
    }
    if (actionState.type === 'approve') {
      return 'Catatan Approval (Opsional)';
    }
    return 'Catatan Pembatalan (Opsional)';
  };

  const canCancelRow = (row) => {
    if (row?.status !== 'pending') {
      return false;
    }

    if (canApprove) {
      return true;
    }

    return Number(row?.requested_by?.id) === Number(user?.id);
  };

  return (
    <>
      <Dialog open={open} onClose={onClose} maxWidth="lg" fullWidth>
        <DialogTitle>
          <Box display="flex" justifyContent="space-between" alignItems="center">
            <Typography variant="h6">Antrean Request Pindah Kelas</Typography>
            <Button
              variant="outlined"
              size="small"
              startIcon={<RefreshCcw size={14} />}
              onClick={loadRequests}
              disabled={loading}
            >
              Refresh
            </Button>
          </Box>
        </DialogTitle>

        <DialogContent>
          <Stack spacing={2}>
            <Alert severity="info">
              {canApprove
                ? 'Anda dapat menyetujui, menolak, atau membatalkan request pindah kelas yang pending.'
                : (isWaliKelas
                  ? 'Anda dapat memantau request pindah kelas yang Anda ajukan dan membatalkan yang masih pending.'
                  : 'Akses Anda terbatas untuk melihat antrean request pindah kelas.')}
            </Alert>

            <Box display="flex" gap={2} alignItems="center" flexWrap="wrap">
              <FormControl size="small" sx={{ minWidth: 180 }}>
                <InputLabel>Status</InputLabel>
                <Select
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value)}
                  label="Status"
                >
                  {STATUS_OPTIONS.map((statusOption) => (
                    <MenuItem key={statusOption.value} value={statusOption.value}>
                      {statusOption.label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              {currentKelas?.namaKelas && (
                <Chip
                  color="primary"
                  variant="outlined"
                  label={`Filter Kelas Asal: ${currentKelas.namaKelas}`}
                />
              )}
            </Box>

            {loading ? (
              <Box py={6} textAlign="center">
                <CircularProgress size={24} />
              </Box>
            ) : (
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Siswa</TableCell>
                    <TableCell>Kelas Asal</TableCell>
                    <TableCell>Kelas Tujuan</TableCell>
                    <TableCell>Tanggal Rencana</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Aksi</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {filteredRows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} align="center">
                        <Typography variant="body2" color="text.secondary">
                          Tidak ada data request.
                        </Typography>
                      </TableCell>
                    </TableRow>
                  ) : filteredRows.map((row) => {
                    const rowStatus = String(row?.status || '').toLowerCase();
                    const canOperate = rowStatus === 'pending';

                    return (
                      <TableRow key={row.id}>
                        <TableCell>
                          <Typography variant="body2" fontWeight={600}>
                            {row?.siswa?.nama || '-'}
                          </Typography>
                          <Typography variant="caption" color="text.secondary">
                            Pengaju: {row?.requested_by?.nama || '-'}
                          </Typography>
                        </TableCell>
                        <TableCell>{row?.kelas_asal?.nama || '-'}</TableCell>
                        <TableCell>{row?.kelas_tujuan?.nama || '-'}</TableCell>
                        <TableCell>{row?.tanggal_rencana || '-'}</TableCell>
                        <TableCell>
                          <Chip
                            label={rowStatus || '-'}
                            size="small"
                            color={STATUS_COLOR[rowStatus] || 'default'}
                            variant="outlined"
                          />
                        </TableCell>
                        <TableCell>
                          <Stack direction="row" spacing={1} flexWrap="wrap">
                            {canApprove && canOperate && (
                              <>
                                <Button
                                  size="small"
                                  color="success"
                                  variant="outlined"
                                  startIcon={<Check size={14} />}
                                  onClick={() => openActionDialog('approve', row)}
                                >
                                  Setujui
                                </Button>
                                <Button
                                  size="small"
                                  color="error"
                                  variant="outlined"
                                  startIcon={<X size={14} />}
                                  onClick={() => openActionDialog('reject', row)}
                                >
                                  Tolak
                                </Button>
                              </>
                            )}
                            {canCancelRow(row) && (
                              <Button
                                size="small"
                                color="warning"
                                variant="outlined"
                                startIcon={<Ban size={14} />}
                                onClick={() => openActionDialog('cancel', row)}
                              >
                                Batalkan
                              </Button>
                            )}
                          </Stack>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </Stack>
        </DialogContent>

        <DialogActions>
          <Button onClick={onClose}>Tutup</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={Boolean(actionState.item)} onClose={submitting ? undefined : closeActionDialog} maxWidth="sm" fullWidth>
        <DialogTitle>{getActionTitle()}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Typography variant="body2">
              Siswa: <strong>{actionState.item?.siswa?.nama || '-'}</strong>
            </Typography>
            <TextField
              label={getActionNoteLabel()}
              multiline
              minRows={3}
              value={actionState.note}
              onChange={(event) => setActionState((prev) => ({ ...prev, note: event.target.value }))}
              required={actionState.type === 'reject'}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeActionDialog} disabled={submitting}>Batal</Button>
          <Button onClick={submitAction} variant="contained" disabled={submitting}>
            {submitting ? 'Memproses...' : 'Simpan'}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default TransferRequestQueueModal;

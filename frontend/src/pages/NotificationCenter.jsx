import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  Paper,
  Stack,
  Tab,
  Tabs,
  Typography,
} from '@mui/material';
import { Bell, CheckCheck, RefreshCw, Trash2 } from 'lucide-react';
import { useSnackbar } from 'notistack';
import { notificationsAPI } from '../services/api';
import useServerClock from '../hooks/useServerClock';
import { formatServerDate, formatServerDateTime } from '../services/serverClock';
import { getNotificationPresentationLabel } from '../utils/notificationPresentation';

const formatDateTime = (value) => formatServerDateTime(value, 'id-ID') || '-';

const getNotificationLifecycle = (notification, serverNowMs = null) => {
  if (notification?.pinned_at) {
    return { label: 'Disematkan', color: 'success' };
  }

  const endValue = notification?.display_end_at || notification?.expires_at || notification?.data?.lifecycle?.expires_at;
  if (!endValue) {
    return { label: 'Aktif', color: 'primary' };
  }

  const endEpochMs = Date.parse(endValue);
  if (Number.isNaN(endEpochMs)) {
    return { label: 'Aktif', color: 'primary' };
  }

  if (Number.isFinite(Number(serverNowMs)) && endEpochMs < Number(serverNowMs)) {
    return { label: 'Berakhir', color: 'default' };
  }

  return { label: `Aktif s/d ${formatServerDate(endEpochMs, 'id-ID') || '-'}`, color: 'primary' };
};

const NotificationCenter = () => {
  const { enqueueSnackbar } = useSnackbar();
  const { isSynced: isServerClockSynced, serverNowMs } = useServerClock();
  const [tab, setTab] = useState('system');
  const [scope, setScope] = useState('active');
  const [items, setItems] = useState([]);
  const [summary, setSummary] = useState({ system: 0, announcement: 0, total: 0 });
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState('');

  const loadSummary = useCallback(async () => {
    const response = await notificationsAPI.getUnreadCount();
    const payload = response?.data?.data ?? {};

    setSummary({
      total: Number(payload?.unread_count_total ?? payload?.unread_count ?? 0) || 0,
      system: Number(payload?.system_unread_count ?? 0) || 0,
      announcement: Number(payload?.announcement_unread_count ?? 0) || 0,
    });
  }, []);

  const loadItems = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const response = await notificationsAPI.getAll({
        per_page: 30,
        category: tab,
        scope,
      });
      const rows = response?.data?.data?.data ?? [];
      setItems(Array.isArray(rows) ? rows : []);
    } catch (_error) {
      setItems([]);
      setError('Gagal memuat notifikasi');
    } finally {
      setLoading(false);
    }
  }, [scope, tab]);

  const loadData = useCallback(async () => {
    await Promise.all([loadItems(), loadSummary()]);
  }, [loadItems, loadSummary]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const unreadItemsOnCurrentView = useMemo(
    () => items.filter((item) => !item?.is_read).length,
    [items]
  );

  const handleMarkAsRead = async (notificationId) => {
    if (!notificationId || actionLoading) {
      return;
    }

    setActionLoading(true);
    try {
      await notificationsAPI.markAsRead(notificationId);
      setItems((previous) =>
        previous.map((item) =>
          item.id === notificationId ? { ...item, is_read: true } : item
        )
      );
      await loadSummary();
    } catch (_error) {
      enqueueSnackbar('Gagal menandai notifikasi', { variant: 'error' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleDelete = async (notificationId) => {
    if (!notificationId || actionLoading) {
      return;
    }

    setActionLoading(true);
    try {
      await notificationsAPI.delete(notificationId);
      setItems((previous) => previous.filter((item) => item.id !== notificationId));
      await loadSummary();
    } catch (_error) {
      enqueueSnackbar('Gagal menghapus notifikasi', { variant: 'error' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleMarkAll = async () => {
    const activeCount = scope === 'all'
      ? unreadItemsOnCurrentView
      : (tab === 'announcement' ? summary.announcement : summary.system);
    if (activeCount === 0 || actionLoading) {
      return;
    }

    setActionLoading(true);
    try {
      await notificationsAPI.markAllAsRead({ category: tab, scope });
      setItems((previous) => previous.map((item) => ({ ...item, is_read: true })));
      await loadSummary();
      enqueueSnackbar('Semua notifikasi pada tab aktif ditandai dibaca', { variant: 'success' });
    } catch (_error) {
      enqueueSnackbar('Gagal menandai semua notifikasi', { variant: 'error' });
    } finally {
      setActionLoading(false);
    }
  };

  const emptyLabel = useMemo(
    () => {
      if (scope === 'all') {
        return tab === 'announcement'
          ? 'Belum ada arsip pengumuman.'
          : 'Belum ada riwayat pesan sistem.';
      }

      return tab === 'announcement'
        ? 'Belum ada pengumuman aktif.'
        : 'Belum ada pesan sistem aktif.';
    },
    [scope, tab]
  );

  return (
    <Stack spacing={3}>
      <Paper
        sx={{
          p: 3,
          borderRadius: 4,
          border: '1px solid',
          borderColor: 'divider',
        }}
      >
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} justifyContent="space-between">
          <Box>
            <Typography variant="overline" sx={{ color: 'primary.main', fontWeight: 700 }}>
              Inbox Notifikasi
            </Typography>
            <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
              Pusat Notifikasi
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 1 }}>
              Pesan sistem dan pengumuman dipisah agar operasional lebih jelas.
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} alignItems="center">
            <Chip label={`Total unread ${summary.total}`} color="primary" variant="outlined" />
            <Button
              variant={scope === 'active' ? 'contained' : 'outlined'}
              onClick={() => setScope('active')}
              disabled={loading || actionLoading}
            >
              Aktif
            </Button>
            <Button
              variant={scope === 'all' ? 'contained' : 'outlined'}
              onClick={() => setScope('all')}
              disabled={loading || actionLoading}
            >
              Semua
            </Button>
            <Button
              variant="outlined"
              startIcon={<RefreshCw size={16} />}
              onClick={loadData}
              disabled={loading || actionLoading}
            >
              Muat ulang
            </Button>
            <Button
              variant="contained"
              startIcon={<CheckCheck size={16} />}
              onClick={handleMarkAll}
              disabled={actionLoading || (
                scope === 'all'
                  ? unreadItemsOnCurrentView === 0
                  : (tab === 'announcement' ? summary.announcement : summary.system) === 0
              )}
            >
              Tandai semua
            </Button>
          </Stack>
        </Stack>
      </Paper>

      <Paper
        sx={{
          borderRadius: 4,
          border: '1px solid',
          borderColor: 'divider',
          overflow: 'hidden',
        }}
      >
        <Tabs
          value={tab}
          onChange={(_event, value) => setTab(value)}
          sx={{
            px: 2,
            pt: 1,
            borderBottom: '1px solid',
            borderColor: 'divider',
          }}
        >
          <Tab label={`Pesan Sistem (${summary.system})`} value="system" />
          <Tab label={`Pengumuman (${summary.announcement})`} value="announcement" />
        </Tabs>

        <Box sx={{ p: 3 }}>
          {loading ? (
            <Box sx={{ py: 10, display: 'flex', justifyContent: 'center' }}>
              <CircularProgress />
            </Box>
          ) : error ? (
            <Alert severity="error">{error}</Alert>
          ) : items.length === 0 ? (
            <Paper
              variant="outlined"
              sx={{
                borderRadius: 3,
                p: 4,
                textAlign: 'center',
                color: 'text.secondary',
              }}
            >
              <Bell size={36} />
              <Typography sx={{ mt: 2 }}>{emptyLabel}</Typography>
            </Paper>
          ) : (
            <Stack divider={<Divider flexItem />}>
              {items.map((notification) => {
                const isUnread = !notification?.is_read;
                const lifecycle = getNotificationLifecycle(
                  notification,
                  isServerClockSynced ? serverNowMs : null
                );
                return (
                  <Stack
                    key={notification.id}
                    direction={{ xs: 'column', md: 'row' }}
                    spacing={2}
                    justifyContent="space-between"
                    sx={{
                      py: 2.5,
                    }}
                  >
                    <Box sx={{ minWidth: 0 }}>
                      <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
                        <Typography sx={{ fontWeight: isUnread ? 700 : 600, color: 'text.primary' }}>
                          {notification.title || 'Notifikasi'}
                        </Typography>
                        <Chip
                          size="small"
                          label={getNotificationPresentationLabel(notification)}
                          sx={{
                            bgcolor: 'primary.50',
                            color: 'primary.dark',
                            fontWeight: 700,
                          }}
                        />
                        {isUnread ? (
                          <Chip size="small" color="warning" label="Belum dibaca" />
                        ) : (
                          <Chip size="small" label="Sudah dibaca" />
                        )}
                        <Chip size="small" color={lifecycle.color} variant="outlined" label={lifecycle.label} />
                      </Stack>
                      <Typography sx={{ mt: 1.25, color: 'text.secondary', whiteSpace: 'pre-line' }}>
                        {notification.message}
                      </Typography>
                      <Typography variant="caption" sx={{ mt: 1.5, display: 'block', color: 'text.disabled' }}>
                        {formatDateTime(notification.created_at)}
                      </Typography>
                    </Box>
                    <Stack direction="row" spacing={1} sx={{ flexShrink: 0, alignSelf: { xs: 'flex-start', md: 'center' } }}>
                      {isUnread ? (
                        <Button
                          size="small"
                          variant="outlined"
                          onClick={() => handleMarkAsRead(notification.id)}
                          disabled={actionLoading}
                        >
                          Baca
                        </Button>
                      ) : null}
                      <Button
                        size="small"
                        color="error"
                        variant="text"
                        startIcon={<Trash2 size={14} />}
                        onClick={() => handleDelete(notification.id)}
                        disabled={actionLoading}
                      >
                        Hapus
                      </Button>
                    </Stack>
                  </Stack>
                );
              })}
            </Stack>
          )}
        </Box>
      </Paper>
    </Stack>
  );
};

export default NotificationCenter;

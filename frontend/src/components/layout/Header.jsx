import React, { useCallback, useEffect, useState } from 'react';
import {
  AppBar,
  Toolbar,
  Typography,
  IconButton,
  Badge,
  Avatar,
  Menu,
  MenuItem,
  Divider,
  Box,
  Paper,
  List,
  ListItem,
  ListItemText,
  CircularProgress,
  Chip,
  Tab,
  Tabs,
  useTheme,
  alpha,
  Fade,
  Popper,
  ClickAwayListener
} from '@mui/material';
import {
  Menu as MenuIcon,
  Bell,
  Settings,
  ChevronDown,
  User,
  LogOut
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useServerClock } from '../../hooks/useServerClock';
import { academicContextAPI, notificationsAPI, periodeAkademikAPI, tahunAjaranAPI } from '../../services/api';
import { notificationUpdatedEventName } from '../../services/pushNotificationService';
import { formatServerDate, getServerTimeString } from '../../services/serverClock';
import { resolveProfilePhotoUrl } from '../../utils/profilePhoto';
import {
  getNotificationPresentationLabel,
} from '../../utils/notificationPresentation';

const Header = ({ onMenuClick }) => {
  const theme = useTheme();
  const navigate = useNavigate();
  const { user, logout, hasPermission } = useAuth();
  const [notificationAnchor, setNotificationAnchor] = useState(null);
  const [userMenuAnchor, setUserMenuAnchor] = useState(null);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notificationLoading, setNotificationLoading] = useState(false);
  const [notificationActionLoading, setNotificationActionLoading] = useState(false);
  const [notificationError, setNotificationError] = useState('');
  const [notificationTab, setNotificationTab] = useState('system');
  const [notificationSummary, setNotificationSummary] = useState({
    system: 0,
    announcement: 0,
  });
  const [activeAcademicContext, setActiveAcademicContext] = useState({
    tahunAjaranLabel: '-',
    periodeLabel: '-',
  });
  const { isSynced: isServerClockSynced, serverNowMs, timezone } = useServerClock();

  // Try different possible name fields
  const displayName = user?.nama_lengkap || user?.name || user?.nama || user?.full_name || user?.username || 'Pengguna';
  const userInitial = displayName.charAt(0).toUpperCase();
  const userPhotoUrl = resolveProfilePhotoUrl(user?.foto_profil_url || user?.foto_profil);
  const settingsPath =
    hasPermission('manage_attendance_settings')
    || hasPermission('manage_settings')
    || hasPermission('manage_backups')
    || hasPermission('manage_whatsapp')
    || hasPermission('manage_broadcast_campaigns')
      ? '/pengaturan'
      : null;

  const loadUnreadCount = useCallback(async () => {
    try {
      const response = await notificationsAPI.getUnreadCount();
      const payload = response?.data?.data ?? {};
      const count = Number(payload?.unread_count_total ?? payload?.unread_count ?? 0);
      const systemCount = Number(payload?.system_unread_count ?? 0);
      const announcementCount = Number(payload?.announcement_unread_count ?? 0);
      setUnreadCount(Number.isNaN(count) ? 0 : count);
      setNotificationSummary({
        system: Number.isNaN(systemCount) ? 0 : systemCount,
        announcement: Number.isNaN(announcementCount) ? 0 : announcementCount,
      });
    } catch (error) {
      // Silent fail to avoid noisy UI during initial render.
      setUnreadCount(0);
      setNotificationSummary({
        system: 0,
        announcement: 0,
      });
    }
  }, []);

  const loadNotifications = useCallback(async () => {
    setNotificationLoading(true);
    setNotificationError('');

    try {
      const response = await notificationsAPI.getAll({
        per_page: 10,
        category: notificationTab,
      });
      const rows = response?.data?.data?.data ?? [];
      setNotifications(Array.isArray(rows) ? rows : []);
    } catch (error) {
      setNotifications([]);
      setNotificationError('Gagal memuat notifikasi');
    } finally {
      setNotificationLoading(false);
    }
  }, [notificationTab]);

  const loadActiveAcademicContext = useCallback(async () => {
    try {
      const contextResponse = await academicContextAPI.getCurrent();
      const contextPayload = contextResponse?.data?.data || null;
      const tahunAjaranLabel = contextPayload?.tahun_ajaran?.nama || null;
      const periodeLabel = contextPayload?.periode_aktif?.nama || null;

      if (tahunAjaranLabel) {
        setActiveAcademicContext({
          tahunAjaranLabel,
          periodeLabel: periodeLabel || '-',
        });
        return;
      }

      const [tahunAjaranResponse, periodeResponse] = await Promise.all([
        tahunAjaranAPI.getAll({ no_pagination: true, status: 'active' }),
        periodeAkademikAPI.getCurrentPeriode(),
      ]);

      const tahunAjaranRows = Array.isArray(tahunAjaranResponse?.data?.data)
        ? tahunAjaranResponse.data.data
        : [];
      const activeTahunAjaran = tahunAjaranRows[0] || null;
      const periode = periodeResponse?.data?.data || null;

      setActiveAcademicContext({
        tahunAjaranLabel: activeTahunAjaran?.nama || '-',
        periodeLabel: periode?.nama || '-',
      });
    } catch (error) {
      setActiveAcademicContext({
        tahunAjaranLabel: '-',
        periodeLabel: '-',
      });
    }
  }, []);

  useEffect(() => {
    loadUnreadCount();
  }, [loadUnreadCount]);

  useEffect(() => {
    loadActiveAcademicContext();
  }, [loadActiveAcademicContext]);

  useEffect(() => {
    const intervalId = window.setInterval(() => {
      loadUnreadCount();
    }, 15000);

    const handleVisibilityOrFocus = () => {
      loadUnreadCount();
    };

    const handleNotificationUpdate = () => {
      loadUnreadCount();
    };

    window.addEventListener('focus', handleVisibilityOrFocus);
    document.addEventListener('visibilitychange', handleVisibilityOrFocus);
    window.addEventListener(notificationUpdatedEventName, handleNotificationUpdate);

    return () => {
      window.clearInterval(intervalId);
      window.removeEventListener('focus', handleVisibilityOrFocus);
      document.removeEventListener('visibilitychange', handleVisibilityOrFocus);
      window.removeEventListener(notificationUpdatedEventName, handleNotificationUpdate);
    };
  }, [loadUnreadCount]);

  useEffect(() => {
    if (notificationAnchor) {
      loadNotifications();
      loadUnreadCount();
    }
  }, [notificationAnchor, loadNotifications, loadUnreadCount]);

  const timezoneLabel = timezone || 'Asia/Jakarta';
  const timeZoneSuffix = timezoneLabel === 'Asia/Jakarta' ? 'WIB' : timezoneLabel;
  const hasTrustedServerClock = isServerClockSynced && Number.isFinite(Number(serverNowMs));
  const serverDateLabel = hasTrustedServerClock
    ? formatServerDate(serverNowMs, 'id-ID', {
        timeZone: timezoneLabel,
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      }) || '-'
    : 'Sinkronisasi waktu server';
  const serverClockTime = hasTrustedServerClock
    ? getServerTimeString(serverNowMs, timezoneLabel) || '-'
    : 'Memuat...';
  const serverTimeLabel = hasTrustedServerClock ? `${serverClockTime} ${timeZoneSuffix}` : serverClockTime;

  const formatRelativeTime = (dateString) => {
    if (!dateString) {
      return '-';
    }

    if (!hasTrustedServerClock) {
      return '-';
    }

    const targetMs = Date.parse(dateString);
    if (Number.isNaN(targetMs)) {
      return '-';
    }

    const diffMs = Number(serverNowMs) - targetMs;
    const diffMinutes = Math.floor(diffMs / 60000);

    if (diffMinutes < 1) {
      return 'Baru saja';
    }
    if (diffMinutes < 60) {
      return `${diffMinutes} menit lalu`;
    }

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) {
      return `${diffHours} jam lalu`;
    }

    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays} hari lalu`;
  };

  const markAsRead = async (notificationId) => {
    if (!notificationId || notificationActionLoading) {
      return;
    }

    setNotificationActionLoading(true);

    try {
      await notificationsAPI.markAsRead(notificationId);
      setNotifications((previous) =>
        previous.map((item) =>
          item.id === notificationId ? { ...item, is_read: true } : item
        )
      );
      await loadUnreadCount();
    } catch (error) {
      setNotificationError('Gagal menandai notifikasi');
    } finally {
      setNotificationActionLoading(false);
    }
  };

  const markAllAsRead = async () => {
    const activeUnreadCount = notificationTab === 'announcement'
      ? notificationSummary.announcement
      : notificationSummary.system;

    if (notificationActionLoading || activeUnreadCount === 0) {
      return;
    }

    setNotificationActionLoading(true);
    try {
      await notificationsAPI.markAllAsRead({ category: notificationTab });
      setNotifications((previous) => previous.map((item) => ({ ...item, is_read: true })));
      await loadUnreadCount();
    } catch (error) {
      setNotificationError('Gagal menandai semua notifikasi');
    } finally {
      setNotificationActionLoading(false);
    }
  };

  const deleteNotification = async (notificationId, isUnread) => {
    if (!notificationId || notificationActionLoading) {
      return;
    }

    setNotificationActionLoading(true);
    try {
      await notificationsAPI.delete(notificationId);
      setNotifications((previous) => previous.filter((item) => item.id !== notificationId));
      if (isUnread) {
        await loadUnreadCount();
      }
    } catch (error) {
      setNotificationError('Gagal menghapus notifikasi');
    } finally {
      setNotificationActionLoading(false);
    }
  };

  const handleNotificationClick = (event) => {
    setNotificationAnchor(notificationAnchor ? null : event.currentTarget);
  };

  const handleUserMenuClick = (event) => {
    setUserMenuAnchor(userMenuAnchor ? null : event.currentTarget);
  };

  const handleClose = () => {
    setNotificationAnchor(null);
    setUserMenuAnchor(null);
  };

  const emptyNotificationLabel = notificationTab === 'announcement'
    ? 'Belum ada pengumuman'
    : 'Belum ada pesan sistem';

  const handleOpenProfile = () => {
    handleClose();
    navigate('/data-pribadi-saya');
  };

  const handleOpenSettings = () => {
    if (!settingsPath) {
      return;
    }

    handleClose();
    navigate(settingsPath);
  };

  const handleOpenNotificationCenter = () => {
    handleClose();
    navigate('/notifikasi');
  };

  return (
    <AppBar
      position="sticky"
      elevation={0}
      sx={{
        background: `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
        backdropFilter: 'blur(20px)',
        borderBottom: 'none',
        boxShadow: '0 2px 12px rgba(0,0,0,0.1)',
        zIndex: theme.zIndex.drawer + 1,
        borderRadius: 0
      }}
    >
      <Toolbar sx={{ minHeight: 64, py: 1 }}>
        {/* Left side */}
        <Box sx={{ display: 'flex', alignItems: 'center', flex: 1 }}>
          <IconButton
            edge="start"
            color="inherit"
            onClick={onMenuClick}
            sx={{
              mr: 2,
              display: { lg: 'none' },
              '&:hover': {
                backgroundColor: alpha('#fff', 0.1),
                transform: 'scale(1.05)'
              },
              transition: 'all 0.2s ease-in-out'
            }}
          >
            <MenuIcon size={20} />
          </IconButton>
          
          <Typography
            variant="h6"
            sx={{
              display: { xs: 'none', lg: 'flex' },
              flexDirection: 'column',
              fontWeight: 600,
              color: 'white',
              textShadow: '0 2px 4px rgba(0,0,0,0.1)',
              lineHeight: 1.2
            }}
          >
            <span style={{ fontSize: '0.95rem', fontWeight: 700 }}>
              Tahun Ajaran {activeAcademicContext.tahunAjaranLabel}
            </span>
            <span style={{ fontSize: '0.82rem', fontWeight: 500, opacity: 0.95 }}>
              {activeAcademicContext.periodeLabel}
            </span>
          </Typography>
          
          {/* Mobile App Name */}
          <Box
            sx={{
              display: { xs: 'flex', lg: 'none' },
              alignItems: 'center',
              gap: 1
            }}
          >
            <Box
              sx={{
                width: 32,
                height: 32,
                borderRadius: 1.5,
                background: 'rgba(255,255,255,0.95)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                border: '1px solid rgba(255,255,255,0.2)',
                overflow: 'hidden',
                p: 0.5
              }}
            >
              <Box
                component="img"
                src="/icon.png"
                alt="Logo SIAPS"
                sx={{ width: '100%', height: '100%', objectFit: 'contain' }}
              />
            </Box>
            <Typography variant="h6" sx={{ fontWeight: 600, color: 'white', fontSize: '0.9rem' }}>
              SIAP Absensi
            </Typography>
          </Box>
        </Box>

        {/* Right side */}
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Box
            sx={{
              display: { xs: 'none', md: 'flex' },
              flexDirection: 'column',
              alignItems: 'flex-end',
              mr: 1,
              px: 1,
            }}
          >
            <Typography variant="caption" sx={{ color: alpha('#fff', 0.8), lineHeight: 1.1 }}>
              Waktu Server
            </Typography>
            <Typography variant="caption" sx={{ color: alpha('#fff', 0.8), lineHeight: 1.1 }}>
              {serverDateLabel}
            </Typography>
            <Typography variant="body2" sx={{ color: '#fff', fontWeight: 600, lineHeight: 1.2 }}>
              {serverTimeLabel}
            </Typography>
          </Box>

          {/* Notifications */}
          <IconButton
            color="inherit"
            onClick={handleNotificationClick}
            sx={{
              '&:hover': {
                backgroundColor: alpha('#fff', 0.1),
                transform: 'scale(1.05)'
              },
              transition: 'all 0.2s ease-in-out'
            }}
          >
            <Badge badgeContent={unreadCount} color="error" max={99}>
              <Bell size={20} />
            </Badge>
          </IconButton>

          <Popper
            open={Boolean(notificationAnchor)}
            anchorEl={notificationAnchor}
            placement="bottom-end"
            transition
            sx={{ zIndex: 1300 }}
          >
            {({ TransitionProps }) => (
              <Fade {...TransitionProps} timeout={200}>
                <Paper
                  elevation={8}
                  sx={{
                    width: 360,
                    maxHeight: 460,
                    mt: 1,
                    borderRadius: 2,
                    overflow: 'hidden'
                  }}
                >
                  <ClickAwayListener onClickAway={handleClose}>
                    <Box>
                      <Box
                        sx={{
                          p: 2,
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                          backgroundColor: theme.palette.primary.main
                        }}
                      >
                        <Typography variant="h6" sx={{ color: 'white', fontWeight: 600 }}>
                          Notifikasi
                        </Typography>
                        {((notificationTab === 'announcement' ? notificationSummary.announcement : notificationSummary.system) > 0) && (
                          <Typography
                            variant="caption"
                            sx={{
                              color: 'white',
                              cursor: notificationActionLoading ? 'not-allowed' : 'pointer',
                              opacity: notificationActionLoading ? 0.6 : 1
                            }}
                            onClick={markAllAsRead}
                          >
                            Tandai semua
                          </Typography>
                        )}
                      </Box>
                      <Box sx={{ px: 1.5, pt: 1, borderBottom: '1px solid', borderColor: 'divider', bgcolor: '#fff' }}>
                        <Tabs
                          value={notificationTab}
                          onChange={(_event, value) => setNotificationTab(value)}
                          variant="fullWidth"
                          sx={{
                            minHeight: 40,
                            '& .MuiTab-root': {
                              minHeight: 40,
                              fontSize: '0.75rem',
                              fontWeight: 700,
                              textTransform: 'none',
                            },
                          }}
                        >
                          <Tab label={`Pesan Sistem (${notificationSummary.system})`} value="system" />
                          <Tab label={`Pengumuman (${notificationSummary.announcement})`} value="announcement" />
                        </Tabs>
                      </Box>
                      <List sx={{ p: 0, maxHeight: 340, overflow: 'auto' }}>
                        {notificationLoading && (
                          <ListItem>
                            <ListItemText
                              primary={
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                  <CircularProgress size={14} />
                                  <Typography variant="body2">Memuat notifikasi...</Typography>
                                </Box>
                              }
                            />
                          </ListItem>
                        )}

                        {!notificationLoading && notificationError && (
                          <ListItem>
                            <ListItemText
                              primary={notificationError}
                              primaryTypographyProps={{
                                fontSize: '0.8rem',
                                color: theme.palette.error.main
                              }}
                            />
                          </ListItem>
                        )}

                        {!notificationLoading && !notificationError && notifications.length === 0 && (
                          <ListItem>
                            <ListItemText
                              primary={emptyNotificationLabel}
                              primaryTypographyProps={{ fontSize: '0.85rem', color: 'text.secondary' }}
                            />
                          </ListItem>
                        )}

                        {!notificationLoading &&
                          !notificationError &&
                          notifications.map((notification, index) => {
                            const isUnread = !notification?.is_read;
                            const presentationLabel = getNotificationPresentationLabel(notification);

                            return (
                              <Box key={notification.id}>
                                <ListItem
                                  sx={{
                                    alignItems: 'flex-start',
                                    cursor: isUnread ? 'pointer' : 'default',
                                    backgroundColor: isUnread
                                      ? alpha(theme.palette.primary.main, 0.08)
                                      : 'transparent',
                                    '&:hover': {
                                      backgroundColor: alpha(theme.palette.primary.main, 0.05)
                                    }
                                  }}
                                  onClick={() => {
                                    if (isUnread) {
                                      markAsRead(notification.id);
                                    }
                                  }}
                                >
                                  <ListItemText
                                    primary={notification.title || 'Notifikasi'}
                                    secondary={
                                      <Box sx={{ mt: 0.5 }}>
                                        <Typography
                                          variant="body2"
                                          component="span"
                                          sx={{
                                            display: 'block',
                                            color: 'text.secondary',
                                            fontSize: '0.78rem',
                                            lineHeight: 1.4
                                          }}
                                        >
                                          {notification.message}
                                        </Typography>
                                        <Box sx={{ mt: 0.75, display: 'flex', alignItems: 'center', gap: 0.75, flexWrap: 'wrap' }}>
                                          <Chip
                                            size="small"
                                            label={presentationLabel}
                                            sx={{
                                              height: 22,
                                              borderRadius: 999,
                                              fontSize: '0.68rem',
                                              fontWeight: 700,
                                              bgcolor: alpha(theme.palette.primary.main, 0.1),
                                              color: theme.palette.primary.dark,
                                            }}
                                          />
                                        </Box>
                                        <Typography
                                          variant="caption"
                                          component="span"
                                          sx={{
                                            display: 'block',
                                            mt: 0.5,
                                            color: 'text.disabled',
                                            fontSize: '0.72rem'
                                          }}
                                        >
                                          {formatRelativeTime(notification.created_at)}
                                        </Typography>
                                      </Box>
                                    }
                                    primaryTypographyProps={{ fontSize: '0.875rem', fontWeight: isUnread ? 600 : 500 }}
                                  />

                                  <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 0.5 }}>
                                    {isUnread && (
                                      <Typography
                                        variant="caption"
                                        sx={{ color: theme.palette.primary.main, cursor: 'pointer' }}
                                        onClick={(event) => {
                                          event.stopPropagation();
                                          markAsRead(notification.id);
                                        }}
                                      >
                                        Baca
                                      </Typography>
                                    )}
                                    <Typography
                                      variant="caption"
                                      sx={{ color: theme.palette.error.main, cursor: 'pointer' }}
                                      onClick={(event) => {
                                        event.stopPropagation();
                                        deleteNotification(notification.id, isUnread);
                                      }}
                                    >
                                      Hapus
                                    </Typography>
                                  </Box>
                                </ListItem>
                                {index < notifications.length - 1 && <Divider />}
                              </Box>
                            );
                          })}
                      </List>
                      <Box sx={{ px: 2, py: 1.5, borderTop: '1px solid', borderColor: 'divider', bgcolor: '#fff' }}>
                        <Typography
                          variant="caption"
                          sx={{ color: theme.palette.primary.main, cursor: 'pointer', fontWeight: 700 }}
                          onClick={handleOpenNotificationCenter}
                        >
                          Lihat semua
                        </Typography>
                      </Box>
                    </Box>
                  </ClickAwayListener>
                </Paper>
              </Fade>
            )}
          </Popper>

          {/* Settings */}
          {settingsPath && (
            <IconButton
              color="inherit"
              onClick={handleOpenSettings}
              sx={{
                display: { xs: 'none', lg: 'flex' },
                '&:hover': {
                  backgroundColor: alpha('#fff', 0.1),
                  transform: 'scale(1.05)'
                },
                transition: 'all 0.2s ease-in-out'
              }}
            >
              <Settings size={20} />
            </IconButton>
          )}

          {/* Profile dropdown */}
          <Box
            onClick={handleUserMenuClick}
            sx={{
              display: 'flex',
              alignItems: 'center',
              cursor: 'pointer',
              backgroundColor: alpha('#fff', 0.1),
              borderRadius: 3,
              px: 1.5,
              py: 0.5,
              ml: 1,
              '&:hover': {
                backgroundColor: alpha('#fff', 0.2),
                transform: 'translateY(-1px)'
              },
              transition: 'all 0.3s ease'
            }}
          >
            <Avatar
              src={userPhotoUrl || undefined}
              sx={{
                width: 32,
                height: 32,
                backgroundColor: alpha('#fff', 0.2),
                backdropFilter: 'blur(10px)',
                border: `2px solid ${alpha('#fff', 0.3)}`,
                mr: 1
              }}
            >
              {!userPhotoUrl && (
                <Typography variant="body2" sx={{ fontWeight: 'bold', color: 'white' }}>
                  {userInitial}
                </Typography>
              )}
            </Avatar>
            <Box sx={{ display: 'block', mr: 1 }}>
              <Typography variant="body2" sx={{ color: 'white', fontWeight: 600, lineHeight: 1.2, fontSize: { xs: '0.8rem', lg: '0.875rem' } }}>
                {displayName}
              </Typography>
            </Box>
            <ChevronDown size={20} style={{ color: alpha('#fff', 0.8) }} />
          </Box>

          <Menu
            anchorEl={userMenuAnchor}
            open={Boolean(userMenuAnchor)}
            onClose={handleClose}
            PaperProps={{
              elevation: 8,
              sx: {
                mt: 1,
                minWidth: 200,
                borderRadius: 2,
                '& .MuiMenuItem-root': {
                  px: 2,
                  py: 1.5,
                  borderRadius: 1,
                  mx: 1,
                  my: 0.5,
                  '&:hover': {
                    backgroundColor: alpha(theme.palette.primary.main, 0.1)
                  }
                }
              }
            }}
            transformOrigin={{ horizontal: 'right', vertical: 'top' }}
            anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
          >
            <MenuItem onClick={handleOpenProfile}>
              <User size={20} style={{ marginRight: 16, color: theme.palette.primary.main }} />
              Profil Saya
            </MenuItem>
            {settingsPath && (
              <MenuItem onClick={handleOpenSettings}>
                <Settings size={20} style={{ marginRight: 16, color: theme.palette.primary.main }} />
                Pengaturan
              </MenuItem>
            )}
            <Divider sx={{ my: 1 }} />
            <MenuItem
              onClick={() => {
                handleClose();
                logout();
              }}
              sx={{
                color: theme.palette.error.main,
                '&:hover': {
                  backgroundColor: alpha(theme.palette.error.main, 0.1)
                }
              }}
            >
              <LogOut size={20} style={{ marginRight: 16 }} />
              Keluar
            </MenuItem>
          </Menu>
        </Box>
      </Toolbar>
    </AppBar>
  );
};

export default Header;

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogContent,
  IconButton,
  Typography,
} from '@mui/material';
import { ExternalLink, Info, X } from 'lucide-react';
import { notificationsAPI } from '../../services/api';
import {
  getWebPushState,
  notificationUpdatedEventName,
  webPushStatusEventName,
} from '../../services/pushNotificationService';

const FALLBACK_POLLING_INTERVAL_MS = 30000;
const INITIAL_RECHECK_DELAY_MS = 1200;
const FOLLOW_UP_RECHECK_DELAY_MS = 900;

const isTruthyFlag = (value) => value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true';

const parseJsonObject = (value) => {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value;
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return {};
  }

  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (_error) {
    return {};
  }
};

const normalizePopupPayload = (notification) => {
  const data = notification?.data && typeof notification.data === 'object' ? notification.data : {};
  const presentation = data?.presentation && typeof data.presentation === 'object' ? data.presentation : {};
  const popup = data?.popup && typeof data.popup === 'object' ? data.popup : {};

  if (!isTruthyFlag(presentation.popup)) {
    return null;
  }

  return {
    id: notification.id,
    title: popup.title || notification.title || 'Informasi',
    message: notification.message || '',
    variant: popup.variant || (popup.image_url ? 'flyer' : 'info'),
    imageUrl: popup.image_url || null,
    dismissLabel: popup.dismiss_label || 'Tutup',
    ctaLabel: popup.cta_label || null,
    ctaUrl: popup.cta_url || null,
    sticky: popup.sticky === true,
    isRead: notification.is_read === true,
    createdAt: notification.created_at || null,
  };
};

const normalizePopupPayloadFromPushMessage = (payload) => {
  const data = payload?.data && typeof payload.data === 'object' ? payload.data : {};
  const presentation = parseJsonObject(data.presentation);
  const popup = parseJsonObject(data.popup);

  if (!isTruthyFlag(presentation.popup)) {
    return null;
  }

  const notificationId = Number.parseInt(data.notification_id, 10);
  if (!Number.isFinite(notificationId) || notificationId <= 0) {
    return null;
  }

  return {
    id: notificationId,
    title: popup.title || payload?.notification?.title || data.title || 'Informasi',
    message: data.message || payload?.notification?.body || '',
    variant: popup.variant || (popup.image_url ? 'flyer' : 'info'),
    imageUrl: popup.image_url || null,
    dismissLabel: popup.dismiss_label || 'Tutup',
    ctaLabel: popup.cta_label || null,
    ctaUrl: popup.cta_url || null,
    sticky: popup.sticky === true || String(popup.sticky).toLowerCase() === 'true',
    isRead: false,
    createdAt: null,
  };
};

const emitNotificationUpdated = () => {
  window.dispatchEvent(new CustomEvent(notificationUpdatedEventName));
};

const BroadcastAnnouncementPopup = () => {
  const [activePopup, setActivePopup] = useState(null);
  const [loading, setLoading] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const [pushState, setPushState] = useState(() => getWebPushState());
  const dismissedIdsRef = useRef(new Set());
  const loadingRef = useRef(false);
  const closingRef = useRef(false);
  const followUpTimerRef = useRef(null);
  const pollingEnabled = pushState.checked && !pushState.ready;

  const applyPopupCandidate = useCallback((popupCandidate) => {
    if (!popupCandidate || dismissedIdsRef.current.has(popupCandidate.id)) {
      return;
    }

    setActivePopup((current) => {
      if (current?.id === popupCandidate.id) {
        return current;
      }
      return popupCandidate;
    });
  }, []);

  const loadPopup = useCallback(async () => {
    if (loadingRef.current || closingRef.current) {
      return;
    }

    loadingRef.current = true;
    setLoading(true);
    try {
      const response = await notificationsAPI.getAll({
        per_page: 20,
        is_read: 0,
        popup: 1,
      });
      const rows = response?.data?.data?.data ?? response?.data?.data ?? [];
      const popupCandidate = (Array.isArray(rows) ? rows : [])
        .map(normalizePopupPayload)
        .filter(Boolean)
        .find((item) => !dismissedIdsRef.current.has(item.id));

      applyPopupCandidate(popupCandidate);
    } catch (_error) {
      // non-blocking: popup broadcast should never break layout rendering
    } finally {
      loadingRef.current = false;
      setLoading(false);
    }
  }, [applyPopupCandidate]);

  const scheduleFollowUpLoad = useCallback(() => {
    if (followUpTimerRef.current) {
      window.clearTimeout(followUpTimerRef.current);
    }

    followUpTimerRef.current = window.setTimeout(() => {
      loadPopup();
    }, FOLLOW_UP_RECHECK_DELAY_MS);
  }, [loadPopup]);

  useEffect(() => {
    const handlePushStateChange = (event) => {
      const nextState = event?.detail;
      if (!nextState || typeof nextState !== 'object') {
        return;
      }

      setPushState({
        checked: nextState.checked === true,
        ready: nextState.ready === true,
        reason: nextState.reason || 'unknown',
      });
    };

    window.addEventListener(webPushStatusEventName, handlePushStateChange);
    return () => {
      window.removeEventListener(webPushStatusEventName, handlePushStateChange);
    };
  }, []);

  useEffect(() => {
    loadPopup();
    const initialRetryId = window.setTimeout(() => {
      loadPopup();
    }, INITIAL_RECHECK_DELAY_MS);

    const intervalId = pollingEnabled ? window.setInterval(() => {
      loadPopup();
    }, FALLBACK_POLLING_INTERVAL_MS) : null;

    const handleUpdate = (event) => {
      const popupCandidate = normalizePopupPayloadFromPushMessage(event?.detail?.payload);
      if (popupCandidate) {
        applyPopupCandidate(popupCandidate);
      } else {
        loadPopup();
      }
      scheduleFollowUpLoad();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        loadPopup();
        scheduleFollowUpLoad();
      }
    };

    window.addEventListener(notificationUpdatedEventName, handleUpdate);
    window.addEventListener('focus', handleUpdate);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      window.clearTimeout(initialRetryId);
      if (intervalId !== null) {
        window.clearInterval(intervalId);
      }
      if (followUpTimerRef.current) {
        window.clearTimeout(followUpTimerRef.current);
      }
      window.removeEventListener(notificationUpdatedEventName, handleUpdate);
      window.removeEventListener('focus', handleUpdate);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [applyPopupCandidate, loadPopup, pollingEnabled, scheduleFollowUpLoad]);

  const handleClose = useCallback(async () => {
    if (!activePopup?.id) {
      setActivePopup(null);
      return;
    }

    dismissedIdsRef.current.add(activePopup.id);
    closingRef.current = true;
    setIsClosing(true);
    try {
      await notificationsAPI.markAsRead(activePopup.id);
    } catch (_error) {
      // no-op: dismissed session guard prevents immediate re-open loop
    } finally {
      emitNotificationUpdated();
      setActivePopup(null);
      closingRef.current = false;
      setIsClosing(false);
    }
  }, [activePopup]);

  const handleCta = () => {
    if (activePopup?.ctaUrl) {
      window.open(activePopup.ctaUrl, '_blank', 'noopener,noreferrer');
    }
    handleClose();
  };

  const dialogTitle = useMemo(
    () => activePopup?.title || 'Informasi',
    [activePopup]
  );

  const isInfoVariant = activePopup?.variant === 'info';

  return (
    <Dialog
      open={Boolean(activePopup)}
      onClose={activePopup?.sticky ? undefined : handleClose}
      fullWidth
      maxWidth={false}
      PaperProps={{
        sx: {
          width: isInfoVariant ? { xs: 'calc(100% - 32px)', sm: 640 } : { xs: 'calc(100% - 32px)', sm: 760 },
          maxWidth: 'calc(100vw - 32px)',
          borderRadius: '28px',
          overflow: 'hidden',
          boxShadow: '0 28px 80px rgba(15, 23, 42, 0.24)',
        },
      }}
    >
      <DialogContent sx={{ p: 0 }}>
        <Box sx={{ position: 'relative', bgcolor: '#fff' }}>
          {!activePopup?.sticky ? (
            <IconButton
              onClick={handleClose}
              sx={{ position: 'absolute', right: 12, top: 12, zIndex: 2 }}
            >
              <X size={20} />
            </IconButton>
          ) : null}

          {isInfoVariant ? (
            <Box
              sx={{
                p: { xs: 3, sm: 4 },
                background: 'linear-gradient(180deg, #e0f2fe 0%, #f8fbff 46%, #ffffff 100%)',
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2 }}>
                <Box
                  sx={{
                    width: 52,
                    height: 52,
                    borderRadius: '18px',
                    display: 'grid',
                    placeItems: 'center',
                    bgcolor: '#0f172a',
                    color: '#fff',
                    flexShrink: 0,
                    boxShadow: '0 16px 30px rgba(15, 23, 42, 0.18)',
                  }}
                >
                  <Info size={22} />
                </Box>
                <Box sx={{ minWidth: 0 }}>
                  <Typography sx={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.18em', textTransform: 'uppercase', color: '#0369a1' }}>
                    Informasi Sekolah
                  </Typography>
                  <Typography sx={{ mt: 1.5, fontSize: { xs: 24, sm: 30 }, lineHeight: 1.15, fontWeight: 800, color: '#0f172a' }}>
                    {dialogTitle}
                  </Typography>
                </Box>
              </Box>
              <Box
                sx={{
                  mt: 3,
                  borderRadius: 4,
                  border: '1px solid rgba(125, 211, 252, 0.7)',
                  background: 'rgba(255, 255, 255, 0.84)',
                  backdropFilter: 'blur(8px)',
                  boxShadow: '0 20px 48px rgba(14, 116, 144, 0.08)',
                }}
              >
                {activePopup?.message ? (
                  <Box
                    sx={{
                      maxHeight: { xs: 280, sm: 340 },
                      overflowY: 'auto',
                      px: 3.5,
                      py: 3,
                    }}
                  >
                    <Typography
                      sx={{
                        fontSize: 15,
                        lineHeight: 1.9,
                        color: '#334155',
                        whiteSpace: 'pre-line',
                      }}
                    >
                      {activePopup.message}
                    </Typography>
                  </Box>
                ) : null}
              </Box>
            </Box>
          ) : (
            <Box sx={{ px: { xs: 3, sm: 5 }, pt: { xs: 3.5, sm: 4 }, pb: 3, background: 'linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)' }}>
              <Typography sx={{ fontSize: { xs: 24, sm: 30 }, fontWeight: 800, color: '#0f172a', textAlign: 'center', lineHeight: 1.15 }}>
                {dialogTitle}
              </Typography>

              {activePopup?.imageUrl ? (
                <Box sx={{ mt: 3.5, display: 'flex', justifyContent: 'center' }}>
                  <Box
                    sx={{
                      width: 'fit-content',
                      maxWidth: '100%',
                      p: { xs: 1.5, sm: 2 },
                      borderRadius: 4,
                      border: '1px solid #e2e8f0',
                      background: 'linear-gradient(180deg, #f8fafc 0%, #ffffff 100%)',
                      boxShadow: '0 22px 55px rgba(15, 23, 42, 0.08)',
                    }}
                  >
                    <Box
                      component="img"
                      src={activePopup.imageUrl}
                      alt={dialogTitle}
                      sx={{
                        display: 'block',
                        maxWidth: '100%',
                        width: 'auto',
                        maxHeight: { xs: '50vh', sm: '58vh' },
                        objectFit: 'contain',
                        borderRadius: 2.5,
                        bgcolor: '#fff',
                      }}
                    />
                  </Box>
                </Box>
              ) : null}

              {activePopup?.message ? (
                <Box
                  sx={{
                    mt: activePopup?.imageUrl ? 3 : 4,
                    mx: 'auto',
                    maxWidth: 680,
                    borderRadius: 3,
                    border: '1px solid #e2e8f0',
                    bgcolor: '#fff',
                    boxShadow: '0 14px 34px rgba(15, 23, 42, 0.05)',
                  }}
                >
                  <Box
                    sx={{
                      maxHeight: { xs: 180, sm: 220 },
                      overflowY: 'auto',
                      px: 3,
                      py: 2.5,
                    }}
                  >
                    <Typography
                      sx={{
                        fontSize: 14,
                        lineHeight: 1.85,
                        color: '#475569',
                        whiteSpace: 'pre-line',
                        textAlign: 'left',
                      }}
                    >
                      {activePopup.message}
                    </Typography>
                  </Box>
                </Box>
              ) : null}
            </Box>
          )}

          <Box
            sx={{
              borderTop: '1px solid #f1f5f9',
              px: { xs: 3, sm: 5 },
              py: 2.5,
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              gap: 2,
            }}
          >
            <Button
              onClick={handleClose}
              disabled={isClosing}
              sx={{ fontWeight: 700, color: '#059669' }}
            >
              {isClosing ? <CircularProgress size={16} sx={{ color: 'inherit' }} /> : (activePopup?.dismissLabel || 'Tutup')}
            </Button>

            {activePopup?.ctaLabel ? (
              <Button
                variant="contained"
                onClick={handleCta}
                endIcon={<ExternalLink size={16} />}
                sx={{
                  borderRadius: 2,
                  bgcolor: '#0f172a',
                  '&:hover': { bgcolor: '#1e293b' },
                  fontWeight: 700,
                }}
              >
                {activePopup.ctaLabel}
              </Button>
            ) : null}
          </Box>
        </Box>
      </DialogContent>
    </Dialog>
  );
};

export default BroadcastAnnouncementPopup;

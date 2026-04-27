import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useSnackbar } from 'notistack';
import {
  ArrowRight,
  Bell,
  Database,
  Layers,
  MapPin,
  Megaphone,
  MessageSquare,
  Settings,
  Smartphone,
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { backupsAPI, deviceTokensAPI, notificationsAPI, pushConfigAPI } from '../services/api';
import { notificationUpdatedEventName } from '../services/pushNotificationService';
import { formatServerDateTime } from '../services/serverClock';

const SettingsHub = () => {
  const { hasPermission, user } = useAuth();
  const { enqueueSnackbar } = useSnackbar();
  const [backupSummary, setBackupSummary] = useState({
    loading: false,
    latestBackupAt: null,
    totalBackups: 0,
    autoBackupEnabled: false,
    backupFrequency: 'daily',
    error: '',
  });
  const [pushSummary, setPushSummary] = useState({
    loading: false,
    enabled: false,
    configured: false,
    provider: 'fcm',
    message: 'Memuat status push...',
  });
  const [deviceTokenSummary, setDeviceTokenSummary] = useState({
    loading: false,
    tokens: [],
    error: '',
  });
  const [sendingPushTest, setSendingPushTest] = useState(false);
  const [lastPushTestResult, setLastPushTestResult] = useState(null);

  useEffect(() => {
    if (!hasPermission('manage_backups')) {
      return;
    }

    let isMounted = true;

    const loadBackupSummary = async () => {
      setBackupSummary((prev) => ({ ...prev, loading: true, error: '' }));

      try {
        const [backupsResponse, settingsResponse] = await Promise.all([
          backupsAPI.getAll(),
          backupsAPI.getSettings(),
        ]);

        if (!isMounted) {
          return;
        }

        const backups = Array.isArray(backupsResponse?.data?.data) ? backupsResponse.data.data : [];
        const settings = settingsResponse?.data?.data || {};
        const latestBackup = backups[0] || null;

        setBackupSummary({
          loading: false,
          latestBackupAt: latestBackup?.created_at || null,
          totalBackups: backups.length,
          autoBackupEnabled: Boolean(settings.auto_backup_enabled),
          backupFrequency: settings.backup_frequency || 'daily',
          error: '',
        });
      } catch (error) {
        if (!isMounted) {
          return;
        }

        setBackupSummary((prev) => ({
          ...prev,
          loading: false,
          error: error?.response?.data?.message || 'Status backup belum tersedia',
        }));
      }
    };

    loadBackupSummary();

    return () => {
      isMounted = false;
    };
  }, [hasPermission]);

  useEffect(() => {
    if (!user?.id) {
      return;
    }

    let isMounted = true;

    const loadDeviceTokens = async () => {
      setDeviceTokenSummary((prev) => ({ ...prev, loading: true, error: '' }));

      try {
        const response = await deviceTokensAPI.getAll();
        if (!isMounted) {
          return;
        }

        const tokens = Array.isArray(response?.data?.data) ? response.data.data : [];
        setDeviceTokenSummary({
          loading: false,
          tokens,
          error: '',
        });
      } catch (error) {
        if (!isMounted) {
          return;
        }

        setDeviceTokenSummary({
          loading: false,
          tokens: [],
          error: error?.response?.data?.message || 'Token device belum tersedia.',
        });
      }
    };

    loadDeviceTokens();

    return () => {
      isMounted = false;
    };
  }, [user?.id]);

  useEffect(() => {
    if (!hasPermission('manage_notifications')) {
      return;
    }

    let isMounted = true;

    const loadPushSummary = async () => {
      setPushSummary((prev) => ({ ...prev, loading: true }));

      try {
        const response = await pushConfigAPI.getWebConfig();
        if (!isMounted) {
          return;
        }

        const payload = response?.data?.data || {};
        const firebase = payload.firebase || {};
        const configured = Boolean(
          payload.enabled &&
          firebase.apiKey &&
          firebase.projectId &&
          firebase.messagingSenderId &&
          firebase.appId &&
          firebase.vapidKey
        );

        setPushSummary({
          loading: false,
          enabled: Boolean(payload.enabled),
          configured,
          provider: payload.provider || 'fcm',
          message: configured
            ? `Push ${payload.provider || 'fcm'} aktif untuk web. Mobile tetap memerlukan konfigurasi Firebase native.`
            : 'Inbox notifikasi aktif. Push real-time belum lengkap dikonfigurasi.',
        });
      } catch (error) {
        if (!isMounted) {
          return;
        }

        setPushSummary({
          loading: false,
          enabled: false,
          configured: false,
          provider: 'fcm',
          message: error?.response?.data?.message || 'Status push belum tersedia.',
        });
      }
    };

    loadPushSummary();

    return () => {
      isMounted = false;
    };
  }, [hasPermission]);

  const sections = useMemo(
    () => [
      {
        title: 'Pengaturan Absensi',
        description: 'Kelola schema absensi, policy global, assignment siswa, dan monitoring skema efektif.',
        path: '/pengaturan-sistem-absensi',
        icon: Layers,
        permission: 'manage_attendance_settings',
        badge: 'Absensi',
      },
      {
        title: 'Lokasi GPS',
        description: 'Atur titik lokasi, tipe area Circle/Polygon, validasi lokasi, dan area pemantauan.',
        path: '/manajemen-lokasi-gps',
        icon: MapPin,
        permission: 'manage_settings',
        badge: 'GPS',
      },
      {
        title: 'WhatsApp Gateway',
        description: 'Pantau status gateway dan perbarui konfigurasi notifikasi WhatsApp.',
        path: '/whatsapp-gateway',
        icon: MessageSquare,
        permission: 'manage_whatsapp',
        badge: 'WhatsApp',
      },
      {
        title: 'Release Mobile',
        description: 'Kelola katalog aplikasi internal, release Android/iPhone, mode update, dan pusat unduh setelah login.',
        path: '/rilis-mobile',
        icon: Smartphone,
        permission: 'manage_settings',
        badge: 'Mobile',
        summary: 'Distribusi aplikasi internal per app, platform, channel, dan audience.',
      },
      {
        title: 'SBT SMANIS',
        description: 'Kelola browser ujian siswa, URL CBT, kode pengawas, sesi aktif, dan log pelanggaran fokus.',
        path: '/sbt-smanis',
        icon: Smartphone,
        permission: 'manage_settings',
        badge: 'Ujian',
      },
      {
        title: 'Test Dapodik',
        description: 'Simpan alamat service lokal Dapodik dan uji koneksi awal sebelum sinkronisasi satu arah dibuat.',
        path: '/dapodik-test',
        icon: Database,
        permission: 'manage_settings',
        badge: 'Dapodik',
        summary: 'Tahap awal hanya test koneksi, belum mengubah data siswa atau pegawai.',
      },
      {
        title: 'Broadcast Message',
        description: 'Susun pesan multi-kanal untuk inbox aplikasi, popup informasi, dan WhatsApp dengan preview terpusat.',
        path: '/broadcast-message',
        icon: Megaphone,
        permission: 'manage_broadcast_campaigns',
        badge: 'Broadcast',
      },
      {
        title: 'Notifikasi Aplikasi',
        description: 'Kelola delivery notifikasi aplikasi untuk inbox dan push device.',
        path: '/pengaturan',
        icon: Bell,
        permission: 'manage_notifications',
        badge: 'Notifikasi',
        summary: pushSummary.loading
          ? 'Memuat status push...'
          : [
              `Provider: ${pushSummary.provider.toUpperCase()}`,
              `Push web: ${pushSummary.configured ? 'aktif' : 'belum aktif'}`,
              pushSummary.message,
            ].join(' | '),
      },
      {
        title: 'Manajemen Backup',
        description: 'Buat backup manual, atur retensi, dan jalankan restore saat diperlukan.',
        path: '/manajemen-backup',
        icon: Database,
        permission: 'manage_backups',
        badge: 'Backup',
        summary: backupSummary.loading
          ? 'Memuat status backup...'
          : backupSummary.error
            ? backupSummary.error
            : [
                `Auto backup: ${backupSummary.autoBackupEnabled ? `aktif (${backupSummary.backupFrequency})` : 'nonaktif'}`,
                `File backup: ${backupSummary.totalBackups}`,
                `Backup terakhir: ${
                  backupSummary.latestBackupAt
                    ? formatServerDateTime(backupSummary.latestBackupAt, 'id-ID')
                    : 'belum ada'
                }`,
              ].join(' | '),
      },
      {
        title: 'Device Management',
        description: 'Kelola binding perangkat siswa dan reset perangkat jika perlu.',
        path: '/device-management',
        icon: Smartphone,
        permission: 'manage_settings',
        badge: 'Device',
      },
    ].filter((item) => hasPermission(item.permission)),
    [backupSummary, hasPermission, pushSummary]
  );

  const sendPushTest = async () => {
    if (!user?.id) {
      enqueueSnackbar('User aktif tidak ditemukan', { variant: 'error' });
      return;
    }

    try {
      setSendingPushTest(true);
      const response = await notificationsAPI.create({
        title: 'Tes Push Notifikasi',
        message: 'Push test dari Pengaturan Sistem berhasil dikirim.',
        type: 'info',
        user_ids: [user.id],
        data: {
          source: 'settings_hub',
          trigger: 'manual_push_test',
        },
      });

      const push = response?.data?.data?.push;
      const attemptedTokens = Number(push?.attempted_tokens ?? 0);
      const successCount = Number(push?.success_count ?? 0);
      const failureCount = Number(push?.failure_count ?? 0);
      const successByType = push?.success_by_device_type || {};
      const failureByType = push?.failure_by_device_type || {};
      setLastPushTestResult({
        attemptedTokens,
        successCount,
        failureCount,
        successByType,
        failureByType,
        results: Array.isArray(push?.results) ? push.results : [],
      });

      const deviceSummary = [
        `attempted=${attemptedTokens}`,
        `success=${successCount}`,
        `failed=${failureCount}`,
      ].join(' | ');
      const tokensResponse = await deviceTokensAPI.getAll();
      const tokens = Array.isArray(tokensResponse?.data?.data) ? tokensResponse.data.data : [];
      setDeviceTokenSummary({
        loading: false,
        tokens,
        error: '',
      });
      window.dispatchEvent(new CustomEvent(notificationUpdatedEventName));
      enqueueSnackbar(
        `${push?.message || 'Notifikasi uji berhasil dibuat'} (${deviceSummary})`,
        { variant: push?.configured ? 'success' : 'warning' }
      );
    } catch (error) {
      enqueueSnackbar(
        error?.response?.data?.message || 'Gagal mengirim notifikasi uji',
        { variant: 'error' }
      );
    } finally {
      setSendingPushTest(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="rounded-2xl bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-700 p-6 text-white shadow-lg">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-200">Pusat Pengaturan</p>
            <h1 className="mt-2 text-3xl font-bold">Pengaturan Sistem</h1>
            <p className="mt-3 max-w-3xl text-sm text-blue-100">
              Semua jalur pengaturan inti dikumpulkan di sini agar tidak perlu mencari per modul.
            </p>
          </div>
          <div className="rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur">
            <Settings className="h-8 w-8 text-cyan-100" />
          </div>
        </div>
      </div>

      {sections.length === 0 ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
          Tidak ada modul pengaturan yang tersedia untuk permission akun ini.
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
          {sections.map((section) => {
            const Icon = section.icon;

            return (
              <Link
                key={section.path}
                to={section.path}
                className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div className="rounded-2xl bg-slate-100 p-3 text-slate-700 transition group-hover:bg-blue-50 group-hover:text-blue-700">
                      <Icon className="h-6 w-6" />
                    </div>
                    <div>
                      <div className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                        {section.badge}
                      </div>
                      <h2 className="mt-3 text-lg font-semibold text-slate-900">{section.title}</h2>
                      <p className="mt-2 text-sm leading-6 text-slate-600">{section.description}</p>
                      {section.summary ? (
                        <p className="mt-3 text-xs leading-5 text-slate-500">{section.summary}</p>
                      ) : null}
                    </div>
                  </div>
                  <ArrowRight className="mt-1 h-5 w-5 text-slate-400 transition group-hover:text-blue-600" />
                </div>
              </Link>
            );
          })}
        </div>
      )}

      {hasPermission('manage_notifications') ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="space-y-3">
              <h2 className="text-lg font-semibold text-slate-900">Tes Push Notifikasi</h2>
              <p className="mt-2 text-sm text-slate-600">
                Kirim notifikasi uji ke akun yang sedang login untuk memastikan inbox dan push device bekerja.
              </p>
              <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                <p className="font-medium text-slate-800">Diagnostik Token Device</p>
                <p className="mt-2">
                  {deviceTokenSummary.loading
                    ? 'Memuat token device...'
                    : deviceTokenSummary.error
                      ? deviceTokenSummary.error
                      : `Device token aktif: ${deviceTokenSummary.tokens.filter((item) => item?.is_active).length} dari ${deviceTokenSummary.tokens.length}`}
                </p>
                {!deviceTokenSummary.loading && !deviceTokenSummary.error && deviceTokenSummary.tokens.length > 0 ? (
                  <div className="mt-3 space-y-2">
                    {deviceTokenSummary.tokens.slice(0, 5).map((token) => (
                      <div
                        key={token.id}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"
                      >
                        <div className="font-medium text-slate-800">
                          {(token.device_type || 'unknown').toUpperCase()} · {token.device_name || token.device_id}
                        </div>
                        <div className="mt-1">
                          Status: {token.is_active ? 'aktif' : 'nonaktif'}{token.last_used_at ? ` · terakhir dipakai ${formatServerDateTime(token.last_used_at, 'id-ID')}` : ''}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : null}
                {lastPushTestResult ? (
                  <div className="mt-4 rounded-lg border border-slate-200 bg-white px-3 py-3 text-xs text-slate-600">
                    <div className="font-medium text-slate-800">Hasil uji push terakhir</div>
                    <div className="mt-1">
                      Attempted: {lastPushTestResult.attemptedTokens} · Success: {lastPushTestResult.successCount} · Failed: {lastPushTestResult.failureCount}
                    </div>
                    <div className="mt-1">
                      Success by device: {Object.keys(lastPushTestResult.successByType).length > 0 ? JSON.stringify(lastPushTestResult.successByType) : '{}'}
                    </div>
                    <div className="mt-1">
                      Failure by device: {Object.keys(lastPushTestResult.failureByType).length > 0 ? JSON.stringify(lastPushTestResult.failureByType) : '{}'}
                    </div>
                    {lastPushTestResult.results.length > 0 ? (
                      <div className="mt-2 space-y-1">
                        {lastPushTestResult.results.slice(-3).map((row, idx) => (
                          <div key={`${row.device_token_id || idx}-${row.token_suffix || ''}`} className="rounded bg-slate-50 px-2 py-1">
                            {(row.device_type || 'unknown').toUpperCase()} · {row.status}
                            {row.error_code ? ` · ${row.error_code}` : ''}
                            {row.http_status ? ` · HTTP ${row.http_status}` : ''}
                            {row.token_suffix ? ` · ...${row.token_suffix}` : ''}
                          </div>
                        ))}
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
            </div>
            <button
              type="button"
              onClick={sendPushTest}
              disabled={sendingPushTest || !user?.id}
              className="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              {sendingPushTest ? 'Mengirim...' : 'Kirim Notifikasi Uji'}
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
};

export default SettingsHub;

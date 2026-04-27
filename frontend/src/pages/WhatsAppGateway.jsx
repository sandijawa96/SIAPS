import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  MessageSquare,
  Send,
  RefreshCw,
  Save,
  Plug,
  PlugZap,
  QrCode,
  LogOut,
  Trash2,
  PhoneCall,
  ShieldCheck,
  Link as LinkIcon,
  Copy,
  Wand2,
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import { whatsappAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';

const initialStatus = {
  configured: false,
  connected: false,
  has_api_key: false,
  notification_enabled: true,
  delivery_tracking_ready: false,
  api_url: '',
  device_id: '',
  webhook_url: '',
  webhook_secret_configured: false,
  gateway_message: null,
  gateway_device: null,
  skip_summary: {
    missing_phone_last_24h: 0,
    disabled_last_24h: 0,
    misconfigured_last_24h: 0,
  },
};

const initialConfig = {
  api_url: '',
  api_key: '',
  device_id: '',
  webhook_secret: '',
  notification_enabled: true,
};

const initialTestPayload = {
  phone_number: '',
  message: 'Ini pesan uji koneksi WhatsApp Gateway.',
  reply_to_message_id: '',
};

const generateSecret = () => `siaps-${Math.random().toString(36).slice(2, 10)}-${Date.now().toString(36)}`;

const statusTone = (connected) => connected
  ? 'text-green-600 bg-green-50 border-green-200'
  : 'text-slate-700 bg-slate-50 border-slate-200';

const WhatsAppGateway = () => {
  const [activeTab, setActiveTab] = useState('gateway');
  const [loadingStatus, setLoadingStatus] = useState(true);
  const [loadingAutomations, setLoadingAutomations] = useState(true);
  const [loadingWebhookEvents, setLoadingWebhookEvents] = useState(true);
  const [loadingSkipEvents, setLoadingSkipEvents] = useState(true);
  const [savingConfig, setSavingConfig] = useState(false);
  const [savingAutomations, setSavingAutomations] = useState(false);
  const [sendingTest, setSendingTest] = useState(false);
  const [checkingNumber, setCheckingNumber] = useState(false);
  const [deviceAction, setDeviceAction] = useState('');

  const [statusData, setStatusData] = useState(initialStatus);
  const [config, setConfig] = useState(initialConfig);
  const [automations, setAutomations] = useState([]);
  const [recentWebhookEvents, setRecentWebhookEvents] = useState([]);
  const [recentSkipEvents, setRecentSkipEvents] = useState([]);
  const [testPayload, setTestPayload] = useState(initialTestPayload);
  const [numberToCheck, setNumberToCheck] = useState('');
  const [numberCheckResult, setNumberCheckResult] = useState(null);
  const [qrPayload, setQrPayload] = useState({
    qrcode: null,
    already_connected: false,
    message: null,
  });

  const loadStatus = useCallback(async () => {
    try {
      setLoadingStatus(true);
      const response = await whatsappAPI.getStatus();
      const payload = response?.data?.data || {};

      setStatusData({
        configured: Boolean(payload.configured),
        connected: Boolean(payload.connected),
        has_api_key: Boolean(payload.has_api_key),
        notification_enabled: payload.notification_enabled !== false,
        delivery_tracking_ready: Boolean(payload.delivery_tracking_ready),
        api_url: payload.api_url || '',
        device_id: payload.device_id || '',
        webhook_url: payload.webhook_url || '',
        webhook_secret_configured: Boolean(payload.webhook_secret_configured),
        gateway_message: payload.gateway_message || null,
        gateway_device: payload.gateway_device || null,
        skip_summary: payload.skip_summary || initialStatus.skip_summary,
      });

      setConfig((prev) => ({
        ...prev,
        api_url: payload.api_url || prev.api_url || '',
        device_id: payload.device_id || prev.device_id || '',
        notification_enabled: payload.notification_enabled !== false,
      }));
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal mengambil status WhatsApp gateway');
    } finally {
      setLoadingStatus(false);
    }
  }, []);

  const loadAutomations = useCallback(async () => {
    try {
      setLoadingAutomations(true);
      const response = await whatsappAPI.getAutomations();
      setAutomations(Array.isArray(response?.data?.data?.automations) ? response.data.data.automations : []);
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal mengambil automation WhatsApp');
    } finally {
      setLoadingAutomations(false);
    }
  }, []);

  const loadWebhookEvents = useCallback(async () => {
    try {
      setLoadingWebhookEvents(true);
      const response = await whatsappAPI.getWebhookEvents({ limit: 8 });
      setRecentWebhookEvents(Array.isArray(response?.data?.data?.events) ? response.data.data.events : []);
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal mengambil riwayat webhook WhatsApp');
    } finally {
      setLoadingWebhookEvents(false);
    }
  }, []);

  const loadSkipEvents = useCallback(async () => {
    try {
      setLoadingSkipEvents(true);
      const response = await whatsappAPI.getSkipEvents({ limit: 8 });
      setRecentSkipEvents(Array.isArray(response?.data?.data?.events) ? response.data.data.events : []);
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal mengambil riwayat skip WhatsApp');
    } finally {
      setLoadingSkipEvents(false);
    }
  }, []);

  const loadAll = useCallback(async () => {
    await Promise.all([loadStatus(), loadAutomations(), loadWebhookEvents(), loadSkipEvents()]);
  }, [loadAutomations, loadStatus, loadWebhookEvents, loadSkipEvents]);

  useEffect(() => {
    loadAll();
  }, [loadAll]);

  const connectionState = useMemo(() => {
    if (!statusData.configured) return 'Belum Dikonfigurasi';
    return statusData.connected ? 'Terhubung' : 'Tidak Terhubung';
  }, [statusData.configured, statusData.connected]);

  const handleConfigChange = (event) => {
    const { name, type, value, checked } = event.target;
    setConfig((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSaveConfig = async (event) => {
    event.preventDefault();

    if (!statusData.webhook_secret_configured && !config.webhook_secret.trim()) {
      toast.error('Webhook Secret wajib diisi sebelum pengaturan gateway disimpan.');
      return;
    }

    setSavingConfig(true);

    try {
      const payload = {
        api_url: config.api_url.trim(),
        device_id: config.device_id.trim(),
        notification_enabled: Boolean(config.notification_enabled),
      };

      if (config.api_key.trim()) {
        payload.api_key = config.api_key.trim();
      }

      if (config.webhook_secret.trim()) {
        payload.webhook_secret = config.webhook_secret.trim();
      }

      await whatsappAPI.updateSettings(payload);
      toast.success('Pengaturan WhatsApp berhasil disimpan');
      setConfig((prev) => ({
        ...prev,
        api_key: '',
        webhook_secret: '',
      }));
      await loadStatus();
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal menyimpan pengaturan WhatsApp');
    } finally {
      setSavingConfig(false);
    }
  };

  const handleAutomationChange = (index, field, value) => {
    setAutomations((prev) => prev.map((item, itemIndex) => (
      itemIndex !== index
        ? item
        : {
            ...item,
            [field]: value,
          }
    )));
  };

  const handleSaveAutomations = async () => {
    setSavingAutomations(true);

    try {
      const payload = {
        automations: automations.map((item) => ({
          key: item.key,
          enabled: Boolean(item.enabled),
          template: item.template || '',
          footer: item.footer || '',
        })),
      };

      const response = await whatsappAPI.updateAutomations(payload);
      setAutomations(Array.isArray(response?.data?.data?.automations) ? response.data.data.automations : []);
      toast.success('Automation WhatsApp berhasil disimpan');
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal menyimpan automation WhatsApp');
    } finally {
      setSavingAutomations(false);
    }
  };

  const handleSendTest = async () => {
    if (!testPayload.phone_number.trim()) {
      toast.error('Nomor tujuan test wajib diisi');
      return;
    }

    if (!testPayload.message.trim()) {
      toast.error('Pesan test wajib diisi');
      return;
    }

    setSendingTest(true);

    try {
      const response = await whatsappAPI.send({
        phone_number: testPayload.phone_number.trim(),
        message: testPayload.message.trim(),
        reply_to_message_id: testPayload.reply_to_message_id.trim() || undefined,
        type: 'pengumuman',
      });

      if (response?.data?.success === false) {
        toast.error(response?.data?.message || 'Pesan test gagal dikirim');
      } else {
        const gatewayMessageId = response?.data?.data?.gateway_message_id || response?.data?.data?.metadata?.gateway_message_id;
        toast.success(gatewayMessageId
          ? `Pesan test berhasil dikirim. Message ID: ${gatewayMessageId}`
          : (response?.data?.message || 'Pesan test berhasil dikirim'));
      }

      await loadStatus();
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Gagal mengirim pesan test');
    } finally {
      setSendingTest(false);
    }
  };

  const handleCheckNumber = async () => {
    if (!numberToCheck.trim()) {
      toast.error('Masukkan nomor yang ingin dicek');
      return;
    }

    setCheckingNumber(true);

    try {
      const response = await whatsappAPI.checkNumber({
        phone_number: numberToCheck.trim(),
      });
      setNumberCheckResult(response?.data?.data || null);
      toast.success(response?.data?.data?.exists ? 'Nomor terdaftar di WhatsApp' : 'Nomor tidak terdeteksi di WhatsApp');
    } catch (error) {
      setNumberCheckResult(null);
      toast.error(error?.response?.data?.message || 'Gagal memeriksa nomor WhatsApp');
    } finally {
      setCheckingNumber(false);
    }
  };

  const handleGenerateQr = async () => {
    setDeviceAction('generate-qr');

    try {
      const response = await whatsappAPI.generateQr({ force: true });
      const payload = response?.data?.data || {};
      setQrPayload({
        qrcode: payload.qrcode || null,
        already_connected: Boolean(payload.already_connected),
        message: response?.data?.message || null,
      });
      toast.success(response?.data?.message || 'QR gateway berhasil diambil');
      await loadStatus();
    } catch (error) {
      setQrPayload({ qrcode: null, already_connected: false, message: null });
      toast.error(error?.response?.data?.message || 'Gagal mengambil QR gateway');
    } finally {
      setDeviceAction('');
    }
  };

  const handleDeviceMutation = async (action) => {
    const confirmed = window.confirm(
      action === 'logout'
        ? 'Putuskan sesi device WhatsApp yang aktif?'
        : 'Hapus device WhatsApp dari gateway?'
    );

    if (!confirmed) {
      return;
    }

    setDeviceAction(action);

    try {
      const response = action === 'logout'
        ? await whatsappAPI.logoutDevice()
        : await whatsappAPI.deleteDevice();

      toast.success(response?.data?.message || 'Aksi device berhasil diproses');
      setQrPayload({ qrcode: null, already_connected: false, message: null });
      await loadStatus();
    } catch (error) {
      toast.error(error?.response?.data?.message || 'Aksi device gagal diproses');
    } finally {
      setDeviceAction('');
    }
  };

  const copyWebhookUrl = async () => {
    if (!statusData.webhook_url) {
      toast.error('Webhook URL belum tersedia');
      return;
    }

    try {
      await navigator.clipboard.writeText(statusData.webhook_url);
      toast.success('Webhook URL disalin');
    } catch (error) {
      toast.error('Gagal menyalin webhook URL');
    }
  };

  const tabs = [
    ['gateway', 'Setting API'],
    ['device', 'Perangkat & Webhook'],
    ['automation', 'Pesan Otomatis'],
    ['test', 'Pesan Test & Utilitas'],
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">WhatsApp Gateway</h1>
          <p className="mt-1 text-sm text-gray-600">
            Kelola koneksi gateway, utilitas device, webhook delivery, dan automation WA.
          </p>
        </div>
        <button
          type="button"
          onClick={loadAll}
          disabled={loadingStatus || loadingAutomations || loadingWebhookEvents || loadingSkipEvents}
          className="btn-secondary inline-flex items-center gap-2"
        >
          <RefreshCw className={`h-4 w-4 ${(loadingStatus || loadingAutomations || loadingWebhookEvents || loadingSkipEvents) ? 'animate-spin' : ''}`} />
          <span>Muat Ulang</span>
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-4">
        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-600">
            {statusData.connected ? <PlugZap className="h-4 w-4 text-green-600" /> : <Plug className="h-4 w-4 text-gray-500" />}
            Status Koneksi
          </div>
          <p className={`inline-flex rounded-full border px-3 py-1 text-sm font-semibold ${statusTone(statusData.connected)}`}>
            {loadingStatus ? 'Memuat...' : connectionState}
          </p>
          {statusData.gateway_message ? <p className="mt-2 text-xs text-gray-500">{statusData.gateway_message}</p> : null}
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-600">
            <MessageSquare className="h-4 w-4 text-blue-600" />
            Switch Global WA
          </div>
          <p className={`text-lg font-semibold ${statusData.notification_enabled ? 'text-blue-600' : 'text-gray-700'}`}>
            {statusData.notification_enabled ? 'Aktif' : 'Nonaktif'}
          </p>
          <p className="mt-2 text-xs text-gray-500">Jika nonaktif, semua automation WA ikut berhenti.</p>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-600">
            <ShieldCheck className="h-4 w-4 text-amber-600" />
            Webhook Delivery
          </div>
          <p className={`text-lg font-semibold ${statusData.webhook_secret_configured ? 'text-amber-600' : 'text-gray-700'}`}>
            {statusData.webhook_secret_configured ? 'Secret Tersimpan' : 'Secret Belum Diatur'}
          </p>
          <p className="mt-2 text-xs text-gray-500">Webhook dipakai untuk sinkronisasi status delivery dari gateway ke SIAPS.</p>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-4">
          <div className="mb-2 text-sm font-medium text-gray-600">Konfigurasi Dasar</div>
          <p className="text-sm text-gray-700">API URL: <span className="font-medium">{statusData.api_url || '-'}</span></p>
          <p className="mt-1 text-sm text-gray-700">Device ID: <span className="font-medium">{statusData.device_id || '-'}</span></p>
          <p className="mt-1 text-sm text-gray-700">API Key: <span className="font-medium">{statusData.has_api_key ? 'Tersimpan' : 'Belum disimpan'}</span></p>
        </div>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-2">
        <div className="flex flex-wrap gap-2">
          {tabs.map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => setActiveTab(key)}
              className={`rounded-lg px-4 py-2 text-sm font-medium transition ${
                activeTab === key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {activeTab === 'gateway' ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <div className="mb-5">
            <h2 className="text-lg font-semibold text-gray-900">Pengaturan Gateway</h2>
            <p className="mt-1 text-sm text-gray-600">
              Endpoint utama `send-message` tetap dipakai SIAPS. Device info, lifecycle device, dan delivery webhook sekarang sudah diselaraskan dengan docs gateway baru.
            </p>
          </div>

          <form onSubmit={handleSaveConfig} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">API URL</label>
                <input type="url" name="api_url" value={config.api_url} onChange={handleConfigChange} className="form-input w-full" placeholder="https://wa-gateway.domain.com" required />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Device ID / Sender</label>
                <input type="text" name="device_id" value={config.device_id} onChange={handleConfigChange} className="form-input w-full" placeholder="628xxxxxxxxxx atau device-id" required />
              </div>

              <div className="md:col-span-2">
                <label className="mb-1 block text-sm font-medium text-gray-700">API Key</label>
                <input type="text" name="api_key" value={config.api_key} onChange={handleConfigChange} className="form-input w-full" placeholder={statusData.has_api_key ? 'Biarkan kosong jika tidak diubah' : 'Masukkan API key gateway'} />
                <p className="mt-1 text-xs text-gray-500">
                  {statusData.has_api_key ? 'API key tetap tersimpan di server. Isi field ini hanya jika ingin mengganti key.' : 'API key belum tersimpan. Koneksi gateway tidak akan berjalan sampai field ini diisi.'}
                </p>
              </div>

              <div className="md:col-span-2">
                <div className="mb-1 flex items-center justify-between gap-3">
                  <label className="block text-sm font-medium text-gray-700">Webhook Secret</label>
                  <button type="button" onClick={() => setConfig((prev) => ({ ...prev, webhook_secret: generateSecret() }))} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    <Wand2 className="h-3.5 w-3.5" />
                    <span>Buat Secret Baru</span>
                  </button>
                </div>
                <input type="text" name="webhook_secret" value={config.webhook_secret} onChange={handleConfigChange} className="form-input w-full" placeholder={statusData.webhook_secret_configured ? 'Biarkan kosong jika tidak diubah' : 'Masukkan secret untuk webhook gateway'} />
                <p className="mt-1 text-xs text-gray-500">Secret ini dipakai untuk memverifikasi callback webhook dari gateway. Jika field dikosongkan saat simpan, secret lama tetap dipertahankan.</p>
              </div>
            </div>

            <label className="inline-flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" name="notification_enabled" checked={config.notification_enabled} onChange={handleConfigChange} className="form-checkbox" />
              Aktifkan notifikasi WhatsApp
            </label>

            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2 text-sm font-semibold text-amber-900">
                    <LinkIcon className="h-4 w-4" />
                    Webhook Callback URL
                  </div>
                  <p className="mt-2 break-all text-sm text-amber-900">{statusData.webhook_url || '-'}</p>
                  <p className="mt-2 text-xs text-amber-800">Set URL ini di WA gateway agar status delivery bisa dikirim balik ke SIAPS. Bila secret diaktifkan, kirim secret yang sama dari gateway.</p>
                </div>
                <button type="button" onClick={copyWebhookUrl} className="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs font-medium text-amber-900 hover:bg-amber-100">
                  <Copy className="h-3.5 w-3.5" />
                  <span>Salin URL</span>
                </button>
              </div>
            </div>

            {!statusData.delivery_tracking_ready ? (
              <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                Delivery tracking belum aktif karena `Webhook Secret` belum dikonfigurasi. SIAPS tetap bisa mengirim WA, tetapi callback delivery akan ditolak sampai secret disimpan.
              </div>
            ) : null}

            <div className="flex justify-end">
              <button type="submit" disabled={savingConfig} className="btn-primary inline-flex items-center gap-2">
                <Save className="h-4 w-4" />
                <span>{savingConfig ? 'Menyimpan...' : 'Simpan Pengaturan'}</span>
              </button>
            </div>
          </form>

          <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div className="mb-4 flex items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-gray-900">Skipped Automation & Routing</h3>
                <p className="mt-1 text-xs text-gray-500">
                  Mencatat kejadian yang sengaja tidak dicoba kirim, misalnya nomor tidak ada, switch WA off, atau konfigurasi gateway belum lengkap.
                </p>
              </div>
              <button
                type="button"
                onClick={loadSkipEvents}
                disabled={loadingSkipEvents}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${loadingSkipEvents ? 'animate-spin' : ''}`} />
                <span>Muat Ulang</span>
              </button>
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-slate-200 bg-white p-3">
                <div className="text-xs font-medium text-gray-500">Nomor Tidak Ada</div>
                <div className="mt-1 text-lg font-semibold text-slate-900">{statusData.skip_summary?.missing_phone_last_24h ?? 0}</div>
              </div>
              <div className="rounded-lg border border-slate-200 bg-white p-3">
                <div className="text-xs font-medium text-gray-500">WA Global Off</div>
                <div className="mt-1 text-lg font-semibold text-slate-900">{statusData.skip_summary?.disabled_last_24h ?? 0}</div>
              </div>
              <div className="rounded-lg border border-slate-200 bg-white p-3">
                <div className="text-xs font-medium text-gray-500">Gateway Belum Lengkap</div>
                <div className="mt-1 text-lg font-semibold text-slate-900">{statusData.skip_summary?.misconfigured_last_24h ?? 0}</div>
              </div>
            </div>

            <div className="mt-4">
              {loadingSkipEvents ? (
                <div className="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                  Memuat riwayat skip WhatsApp...
                </div>
              ) : recentSkipEvents.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                  Belum ada event skip yang tercatat.
                </div>
              ) : (
                <div className="space-y-3">
                  {recentSkipEvents.map((event) => (
                    <div key={event.id} className="rounded-lg border border-slate-200 bg-white p-3 text-sm text-gray-700">
                      <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                              {event.reason}
                            </span>
                            {event.type ? (
                              <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                {event.type}
                              </span>
                            ) : null}
                          </div>
                          <div className="mt-2 space-y-1">
                            <div>User: <span className="font-medium">{event.target_user?.name || '-'}</span></div>
                            <div>Nomor kandidat: <span className="font-medium">{event.phone_candidate || '-'}</span></div>
                          </div>
                        </div>
                        <div className="text-xs text-gray-500">
                          {formatServerDateTime(event.created_at, 'id-ID') || '-'}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      ) : null}

      {activeTab === 'device' ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div className="rounded-lg border border-gray-200 bg-white p-6">
              <div className="mb-5">
                <h2 className="text-lg font-semibold text-gray-900">Lifecycle Device Gateway</h2>
                <p className="mt-1 text-sm text-gray-600">
                  Kelola QR login, putuskan sesi, atau hapus device langsung dari SIAPS. Fitur ini sekarang memakai endpoint gateway baru.
                </p>
              </div>

              <div className="flex flex-wrap gap-3">
                <button type="button" onClick={handleGenerateQr} disabled={deviceAction !== '' || !statusData.configured} className="btn-secondary inline-flex items-center gap-2">
                  <QrCode className="h-4 w-4" />
                  <span>{deviceAction === 'generate-qr' ? 'Mengambil QR...' : 'Generate QR'}</span>
                </button>
                <button type="button" onClick={() => handleDeviceMutation('logout')} disabled={deviceAction !== '' || !statusData.configured} className="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60">
                  <LogOut className="h-4 w-4" />
                  <span>{deviceAction === 'logout' ? 'Memutus sesi...' : 'Logout Device'}</span>
                </button>
                <button type="button" onClick={() => handleDeviceMutation('delete')} disabled={deviceAction !== '' || !statusData.configured} className="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-900 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60">
                  <Trash2 className="h-4 w-4" />
                  <span>{deviceAction === 'delete' ? 'Menghapus device...' : 'Delete Device'}</span>
                </button>
              </div>

              <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div className="text-sm font-semibold text-gray-900">Device Info Terakhir</div>
                <div className="mt-3 space-y-2 text-sm text-gray-700">
                  <div>Device ID: <span className="font-medium">{statusData.device_id || '-'}</span></div>
                  <div>Connected: <span className="font-medium">{statusData.connected ? 'Ya' : 'Tidak'}</span></div>
                </div>
                <pre className="mt-4 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">
                  {JSON.stringify(statusData.gateway_device || {}, null, 2)}
                </pre>
              </div>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white p-6">
              <div className="mb-4">
                <h2 className="text-lg font-semibold text-gray-900">QR Pairing</h2>
                <p className="mt-1 text-sm text-gray-600">Scan QR ini di perangkat WhatsApp yang akan dipakai gateway.</p>
              </div>

              {qrPayload.qrcode ? (
                <div className="rounded-lg border border-gray-200 p-4">
                  <img src={qrPayload.qrcode} alt="QR WhatsApp Gateway" className="mx-auto w-full max-w-xs rounded-lg border border-gray-200" />
                  <p className="mt-4 text-center text-sm text-gray-600">{qrPayload.message || 'QR code siap dipindai.'}</p>
                </div>
              ) : (
                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                  {qrPayload.already_connected
                    ? (qrPayload.message || 'Device sudah terhubung. Logout device jika ingin pairing ulang.')
                    : 'Belum ada QR aktif. Klik Generate QR untuk mengambil QR pairing terbaru.'}
                </div>
              )}
            </div>
          </div>

          <div className="rounded-lg border border-gray-200 bg-white p-6">
            <div className="mb-4 flex items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-gray-900">Recent Webhook Delivery</h2>
                <p className="mt-1 text-sm text-gray-600">
                  Callback terbaru yang masuk dari gateway, termasuk event yang berhasil match ke notifikasi SIAPS.
                </p>
              </div>
              <button
                type="button"
                onClick={loadWebhookEvents}
                disabled={loadingWebhookEvents}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <RefreshCw className={`h-4 w-4 ${loadingWebhookEvents ? 'animate-spin' : ''}`} />
                <span>Muat Ulang</span>
              </button>
            </div>

            {loadingWebhookEvents ? (
              <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                Memuat riwayat webhook WhatsApp...
              </div>
            ) : recentWebhookEvents.length === 0 ? (
              <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                Belum ada callback webhook yang tercatat.
              </div>
            ) : (
              <div className="space-y-3">
                {recentWebhookEvents.map((event) => (
                  <div key={event.id} className="rounded-lg border border-gray-200 p-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
                            event.delivery_marked ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-700'
                          }`}>
                            {event.delivery_marked ? 'Delivered ditandai' : 'Callback diterima'}
                          </span>
                          <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                            {event.event_type || 'incoming_message'}
                          </span>
                          {event.status ? (
                            <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                              {event.status}
                            </span>
                          ) : null}
                        </div>
                        <div className="mt-3 space-y-1 text-sm text-gray-700">
                          <div>Message ID: <span className="font-medium">{event.message_id || '-'}</span></div>
                          <div>Device: <span className="font-medium">{event.device || '-'}</span></div>
                          <div>From: <span className="font-medium">{event.from_number || '-'}</span></div>
                        </div>
                      </div>

                      <div className="text-xs text-gray-500">
                        {formatServerDateTime(event.created_at, 'id-ID') || '-'}
                      </div>
                    </div>

                    {event.matched_notification ? (
                      <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                        <div className="font-semibold text-gray-900">Matched Notification</div>
                        <div className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                          <div>ID: <span className="font-medium">#{event.matched_notification.id}</span></div>
                          <div>Status: <span className="font-medium">{event.matched_notification.status || '-'}</span></div>
                          <div>Nomor: <span className="font-medium">{event.matched_notification.phone_number || '-'}</span></div>
                          <div>Tipe: <span className="font-medium">{event.matched_notification.type || '-'}</span></div>
                        </div>
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      ) : null}

      {activeTab === 'automation' ? (
        <div className="rounded-lg border border-gray-200 bg-white p-6">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Control Automation WA</h2>
              <p className="mt-1 text-sm text-gray-600">Aktifkan atau nonaktifkan automation, lalu edit isi pesan sesuai kebutuhan operasional.</p>
            </div>
            <button type="button" onClick={handleSaveAutomations} disabled={savingAutomations || loadingAutomations} className="btn-primary inline-flex items-center gap-2">
              <Save className="h-4 w-4" />
              <span>{savingAutomations ? 'Menyimpan...' : 'Simpan Automation'}</span>
            </button>
          </div>

          {loadingAutomations ? (
            <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">Memuat daftar automation WhatsApp...</div>
          ) : (
            <div className="space-y-4">
              {automations.map((automation, index) => (
                <div key={automation.key} className="rounded-lg border border-gray-200 p-4">
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                      <h3 className="text-sm font-semibold text-gray-900">{automation.label}</h3>
                      <p className="mt-1 text-xs text-gray-500">Target: {automation.audience || '-'} | Tipe: {automation.type || '-'}</p>
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                      <input type="checkbox" checked={Boolean(automation.enabled)} onChange={(event) => handleAutomationChange(index, 'enabled', event.target.checked)} className="form-checkbox" />
                      Aktif
                    </label>
                  </div>

                  <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div>
                      <label className="mb-1 block text-sm font-medium text-gray-700">Isi Pesan</label>
                      <textarea rows={6} value={automation.template || ''} onChange={(event) => handleAutomationChange(index, 'template', event.target.value)} className="form-textarea w-full" />
                    </div>

                    <div className="space-y-4">
                      <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Footer</label>
                        <input type="text" value={automation.footer || ''} onChange={(event) => handleAutomationChange(index, 'footer', event.target.value)} className="form-input w-full" placeholder="Opsional" />
                      </div>

                      <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Placeholder</label>
                        <div className="flex flex-wrap gap-2">
                          {(automation.placeholders || []).map((placeholder) => (
                            <span key={placeholder} className="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">{`{${placeholder}}`}</span>
                          ))}
                          {(automation.placeholders || []).length === 0 ? <span className="text-xs text-gray-400">Tidak ada placeholder</span> : null}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : null}

      {activeTab === 'test' ? (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <div className="rounded-lg border border-gray-200 bg-white p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Kirim Pesan Test</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Nomor Tujuan</label>
                <input type="text" value={testPayload.phone_number} onChange={(event) => setTestPayload((prev) => ({ ...prev, phone_number: event.target.value }))} className="form-input w-full" placeholder="628xxxxxxxxxx" />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Reply ke Message ID</label>
                <input type="text" value={testPayload.reply_to_message_id} onChange={(event) => setTestPayload((prev) => ({ ...prev, reply_to_message_id: event.target.value }))} className="form-input w-full" placeholder="Opsional, untuk msgid/reply chain" />
              </div>
              <div className="md:col-span-2">
                <label className="mb-1 block text-sm font-medium text-gray-700">Pesan</label>
                <textarea rows={4} value={testPayload.message} onChange={(event) => setTestPayload((prev) => ({ ...prev, message: event.target.value }))} className="form-textarea w-full" />
              </div>
            </div>

            <div className="mt-4 flex justify-end">
              <button type="button" onClick={handleSendTest} disabled={sendingTest || !statusData.configured} className="btn-secondary inline-flex items-center gap-2">
                <Send className="h-4 w-4" />
                <span>{sendingTest ? 'Mengirim...' : 'Kirim Test'}</span>
              </button>
            </div>
          </div>

          <div className="rounded-lg border border-gray-200 bg-white p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Cek Nomor WhatsApp</h2>
            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Nomor yang Dicek</label>
                <input type="text" value={numberToCheck} onChange={(event) => setNumberToCheck(event.target.value)} className="form-input w-full" placeholder="628xxxxxxxxxx" />
              </div>

              <button type="button" onClick={handleCheckNumber} disabled={checkingNumber || !statusData.configured} className="btn-secondary inline-flex items-center gap-2">
                <PhoneCall className="h-4 w-4" />
                <span>{checkingNumber ? 'Memeriksa...' : 'Check Number'}</span>
              </button>

              {numberCheckResult ? (
                <div className={`rounded-lg border p-4 text-sm ${numberCheckResult.exists ? 'border-green-200 bg-green-50 text-green-900' : 'border-slate-200 bg-slate-50 text-slate-800'}`}>
                  <div className="font-semibold">{numberCheckResult.exists ? 'Nomor terdaftar di WhatsApp' : 'Nomor tidak terdeteksi di WhatsApp'}</div>
                  <div className="mt-2">Nomor: <span className="font-medium">{numberCheckResult.phone_number}</span></div>
                  <div className="mt-1">JID: <span className="font-medium">{numberCheckResult.jid || '-'}</span></div>
                </div>
              ) : (
                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">Gunakan utilitas ini untuk preflight nomor manual broadcast atau validasi kontak orang tua/wali.</div>
              )}
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
};

export default WhatsAppGateway;

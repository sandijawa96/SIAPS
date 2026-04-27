import React, { useEffect, useMemo, useState } from 'react';
import { Database, Download, Upload, RefreshCw, HardDrive, AlertTriangle, CheckCircle } from 'lucide-react';
import { backupsAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';

const BackupManagement = () => {
  const [backups, setBackups] = useState([]);
  const [loading, setLoading] = useState(false);
  const [isCreatingBackup, setIsCreatingBackup] = useState(false);
  const [isRestoring, setIsRestoring] = useState(false);
  const [isSavingSettings, setIsSavingSettings] = useState(false);
  const [downloadingFilename, setDownloadingFilename] = useState('');
  const [error, setError] = useState('');
  const [infoMessage, setInfoMessage] = useState('');
  const [settings, setSettings] = useState({
    autoBackup: false,
    backupFrequency: 'daily',
    retentionDays: 30,
    backupTypes: ['database'],
    backupRunTime: '01:00',
    backupWeeklyDay: 1,
    backupMonthlyDay: 1,
  });
  const [manualBackupType, setManualBackupType] = useState('database');
  const [confirmState, setConfirmState] = useState({
    open: false,
    mode: 'create',
    backup: null,
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    setError('');
    await Promise.all([fetchBackups(), fetchSettings()]);
    setLoading(false);
  };

  const fetchBackups = async () => {
    try {
      const response = await backupsAPI.getAll();
      const rows = Array.isArray(response?.data?.data) ? response.data.data : [];
      setBackups(
        rows.map((item) => ({
          ...item,
          status: item.status || 'completed',
          type: item.type || 'database',
        }))
      );
    } catch (err) {
      setBackups([]);
      setError(err?.response?.data?.message || 'Gagal memuat data backup');
    }
  };

  const fetchSettings = async () => {
    try {
      const response = await backupsAPI.getSettings();
      const payload = response?.data?.data || {};
      setSettings({
        autoBackup: Boolean(payload.auto_backup_enabled),
        backupFrequency: payload.backup_frequency || 'daily',
        retentionDays: Number(payload.retention_days || 30),
        backupTypes: Array.isArray(payload.backup_types) && payload.backup_types.length > 0
          ? payload.backup_types
          : ['database'],
        backupRunTime: payload.backup_run_time || '01:00',
        backupWeeklyDay: Number(payload.backup_weekly_day || 1),
        backupMonthlyDay: Number(payload.backup_monthly_day || 1),
      });
    } catch (err) {
      setError((prev) => prev || (err?.response?.data?.message || 'Gagal memuat pengaturan backup'));
    }
  };

  const handleCreateBackup = async () => {
    setIsCreatingBackup(true);
    setInfoMessage('');
    try {
      await backupsAPI.create({
        type: manualBackupType,
        description: `Manual backup ${getTypeLabel(manualBackupType).toLowerCase()} dari dashboard web`,
      });
      setInfoMessage('Backup manual berhasil dibuat');
      await fetchBackups();
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal membuat backup');
    } finally {
      setIsCreatingBackup(false);
    }
  };

  const handleDownload = async (backup) => {
    setDownloadingFilename(backup.filename);
    setError('');
    setInfoMessage('');
    try {
      const response = await backupsAPI.getDownloadLink(backup.filename);
      const downloadUrl = response?.data?.data?.download_url;

      if (!downloadUrl) {
        throw new Error('Link unduhan sementara tidak tersedia.');
      }

      window.location.assign(downloadUrl);
      setInfoMessage(`Unduhan backup ${backup.filename} sedang disiapkan. Jika belum muncul, cek izin download browser.`);
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Gagal mengunduh backup');
    } finally {
      setDownloadingFilename('');
    }
  };

  const handleRestore = async (backup) => {
    setIsRestoring(true);
    setInfoMessage('');
    try {
      await backupsAPI.restore(backup.filename, { confirm: true });
      setInfoMessage('Restore backup berhasil dijalankan');
      await fetchBackups();
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal melakukan restore backup');
    } finally {
      setIsRestoring(false);
    }
  };

  const openCreateConfirmation = () => {
    setConfirmState({
      open: true,
      mode: 'create',
      backup: null,
    });
  };

  const openRestoreConfirmation = (backup) => {
    setConfirmState({
      open: true,
      mode: 'restore',
      backup,
    });
  };

  const closeConfirmation = () => {
    setConfirmState({
      open: false,
      mode: 'create',
      backup: null,
    });
  };

  const handleConfirmedAction = async () => {
    const { mode, backup } = confirmState;
    closeConfirmation();

    if (mode === 'restore' && backup) {
      await handleRestore(backup);
      return;
    }

    await handleCreateBackup();
  };

  const handleSettingsUpdate = async () => {
    setIsSavingSettings(true);
    setInfoMessage('');
    try {
      await backupsAPI.updateSettings({
        auto_backup_enabled: settings.autoBackup,
        backup_frequency: settings.backupFrequency,
        retention_days: Number(settings.retentionDays || 30),
        backup_types: settings.backupTypes,
        backup_run_time: settings.backupRunTime || '01:00',
        backup_weekly_day: Number(settings.backupWeeklyDay || 1),
        backup_monthly_day: Number(settings.backupMonthlyDay || 1),
      });
      setInfoMessage('Pengaturan backup berhasil disimpan');
      await fetchSettings();
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal menyimpan pengaturan backup');
    } finally {
      setIsSavingSettings(false);
    }
  };

  const formatFileSize = (sizeBytes) => {
    const bytes = Number(sizeBytes || 0);
    if (bytes <= 0) return '-';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let idx = 0;
    while (value >= 1024 && idx < units.length - 1) {
      value /= 1024;
      idx += 1;
    }
    return `${value.toFixed(1)} ${units[idx]}`;
  };

  const formatDate = (dateString) => formatServerDateTime(dateString, 'id-ID') || '-';

  const getStatusColor = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'completed' || normalized === 'success') {
      return 'text-green-600';
    }
    if (normalized === 'failed' || normalized === 'error') {
      return 'text-red-600';
    }
    if (normalized === 'in_progress' || normalized === 'running') {
      return 'text-yellow-600';
    }
    return 'text-gray-600';
  };

  const getTypeLabel = (type) => {
    const normalized = String(type || '').toLowerCase();
    if (normalized === 'auto') return 'Otomatis';
    if (normalized === 'full') return 'Full';
    if (normalized === 'files') return 'File';
    if (normalized === 'database') return 'Database';
    return 'Manual';
  };

  const getTypeWarning = (type) => {
    switch (String(type || '').toLowerCase()) {
      case 'full':
        return 'Backup full mencakup database dan file. Ukuran lebih besar dan waktu proses lebih lama.';
      case 'files':
        return 'Backup file hanya mencakup dokumen/upload. Data database tidak ikut tersimpan.';
      case 'database':
      default:
        return 'Backup database hanya mencakup data aplikasi. File upload tidak ikut tersimpan.';
    }
  };

  const getRestoreWarning = (backup) => {
    const backupType = backup?.type || 'database';
    const typeLabel = getTypeLabel(backupType);
    return `Restore ${typeLabel.toLowerCase()} dari file "${backup?.filename}" akan menimpa data yang relevan. Jalankan hanya jika Anda yakin dan sudah memahami dampaknya.`;
  };

  const toggleBackupType = (type) => {
    setSettings((prev) => {
      const current = new Set(prev.backupTypes || []);
      if (current.has(type)) {
        current.delete(type);
      } else {
        current.add(type);
      }

      const next = Array.from(current);
      return {
        ...prev,
        backupTypes: next.length > 0 ? next : ['database'],
      };
    });
  };

  const totalSizeBytes = useMemo(
    () => backups.reduce((sum, item) => sum + Number(item.size_bytes || 0), 0),
    [backups]
  );
  const latestBackup = backups.length > 0 ? backups[0] : null;

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Manajemen Backup</h1>
        <p className="text-sm text-gray-600 mt-1">Kelola backup dan restore data sistem</p>
      </div>

      {error && (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}
      {infoMessage && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {infoMessage}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b">
              <div className="flex justify-between items-center">
                <h2 className="text-lg font-semibold text-gray-900">Daftar Backup</h2>
                <div className="flex items-center gap-3">
                  <select
                    value={manualBackupType}
                    onChange={(e) => setManualBackupType(e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm"
                  >
                    <option value="database">Database</option>
                    <option value="files">File</option>
                    <option value="full">Full</option>
                  </select>
                  <button
                    onClick={openCreateConfirmation}
                    disabled={isCreatingBackup || loading}
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300"
                  >
                    {isCreatingBackup ? (
                      <>
                        <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                        Membuat Backup...
                      </>
                    ) : (
                      <>
                        <Database className="w-4 h-4 mr-2" />
                        Buat Backup Manual
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      File
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Ukuran
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tanggal
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tipe
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {loading ? (
                    <tr>
                      <td colSpan="6" className="px-6 py-8 text-center text-sm text-gray-500">
                        Memuat data backup...
                      </td>
                    </tr>
                  ) : backups.length === 0 ? (
                    <tr>
                      <td colSpan="6" className="px-6 py-8 text-center text-sm text-gray-500">
                        Belum ada backup
                      </td>
                    </tr>
                  ) : (
                    backups.map((backup) => (
                      <tr key={backup.id || backup.filename}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <Database className="w-5 h-5 text-gray-400 mr-3" />
                            <div className="text-sm font-medium text-gray-900">{backup.filename}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {backup.size || formatFileSize(backup.size_bytes)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {formatDate(backup.created_at)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span
                            className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                              backup.type === 'auto' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
                            }`}
                          >
                            {getTypeLabel(backup.type)}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`text-sm font-medium ${getStatusColor(backup.status)}`}>
                            {backup.status || 'completed'}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <div className="flex space-x-2">
                            <button
                              onClick={() => handleDownload(backup)}
                              disabled={downloadingFilename === backup.filename}
                              className="text-blue-600 hover:text-blue-900 disabled:text-gray-400"
                              title="Unduh backup"
                            >
                              {downloadingFilename === backup.filename ? (
                                <RefreshCw className="w-4 h-4 animate-spin" />
                              ) : (
                                <Download className="w-4 h-4" />
                              )}
                            </button>
                            <button
                              onClick={() => openRestoreConfirmation(backup)}
                              disabled={isRestoring}
                              className="text-green-600 hover:text-green-900 disabled:text-gray-400"
                              title="Restore backup"
                            >
                              <Upload className="w-4 h-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="space-y-6">
          <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b">
              <h3 className="text-lg font-semibold text-gray-900">Pengaturan Backup</h3>
            </div>
            <div className="p-6 space-y-4">
              <div className="flex items-center justify-between">
                <label className="text-sm font-medium text-gray-700">Backup Otomatis</label>
                <input
                  type="checkbox"
                  checked={settings.autoBackup}
                  onChange={(e) => setSettings((prev) => ({ ...prev, autoBackup: e.target.checked }))}
                  className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Frekuensi Backup</label>
                <select
                  value={settings.backupFrequency}
                  onChange={(e) => setSettings((prev) => ({ ...prev, backupFrequency: e.target.value }))}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                >
                  <option value="hourly">Per Jam</option>
                  <option value="daily">Harian</option>
                  <option value="weekly">Mingguan</option>
                  <option value="monthly">Bulanan</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Jam Eksekusi</label>
                <input
                  type="time"
                  value={settings.backupRunTime}
                  onChange={(e) => setSettings((prev) => ({ ...prev, backupRunTime: e.target.value || '01:00' }))}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                />
              </div>

              {settings.backupFrequency === 'weekly' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Hari Backup Mingguan</label>
                  <select
                    value={settings.backupWeeklyDay}
                    onChange={(e) =>
                      setSettings((prev) => ({ ...prev, backupWeeklyDay: Number(e.target.value || 1) }))
                    }
                    className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                  >
                    <option value={1}>Senin</option>
                    <option value={2}>Selasa</option>
                    <option value={3}>Rabu</option>
                    <option value={4}>Kamis</option>
                    <option value={5}>Jumat</option>
                    <option value={6}>Sabtu</option>
                    <option value={7}>Minggu</option>
                  </select>
                </div>
              )}

              {settings.backupFrequency === 'monthly' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Tanggal Backup Bulanan</label>
                  <input
                    type="number"
                    min={1}
                    max={31}
                    value={settings.backupMonthlyDay}
                    onChange={(e) =>
                      setSettings((prev) => ({ ...prev, backupMonthlyDay: Number(e.target.value || 1) }))
                    }
                    className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                  />
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Retensi (hari)</label>
                <input
                  type="number"
                  min={1}
                  max={365}
                  value={settings.retentionDays}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, retentionDays: Number(e.target.value || 30) }))
                  }
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Tipe Backup Otomatis</label>
                <div className="space-y-2">
                  {[
                    ['database', 'Database'],
                    ['files', 'File'],
                    ['full', 'Full'],
                  ].map(([value, label]) => (
                    <label key={value} className="flex items-center gap-2 text-sm text-gray-700">
                      <input
                        type="checkbox"
                        checked={settings.backupTypes.includes(value)}
                        onChange={() => toggleBackupType(value)}
                        className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                {getTypeWarning(manualBackupType)}
              </div>

              <button
                onClick={handleSettingsUpdate}
                disabled={isSavingSettings}
                className="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300"
              >
                {isSavingSettings ? 'Menyimpan...' : 'Simpan Pengaturan'}
              </button>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b">
              <h3 className="text-lg font-semibold text-gray-900">Status Sistem</h3>
            </div>
            <div className="p-6 space-y-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Backup Tersedia</span>
                <div className="flex items-center">
                  <CheckCircle className="w-4 h-4 text-green-500 mr-1" />
                  <span className="text-sm text-green-600">{backups.length} file</span>
                </div>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Total Ukuran Backup</span>
                <div className="flex items-center">
                  <HardDrive className="w-4 h-4 text-blue-500 mr-1" />
                  <span className="text-sm text-gray-600">{formatFileSize(totalSizeBytes)}</span>
                </div>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Backup Terakhir</span>
                <span className="text-sm text-gray-600">
                  {latestBackup ? formatDate(latestBackup.created_at) : '-'}
                </span>
              </div>
            </div>
          </div>

          <div className="bg-yellow-50 rounded-lg p-4">
            <div className="flex">
              <div className="flex-shrink-0">
                <AlertTriangle className="h-5 w-5 text-yellow-400" />
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-yellow-800">Peringatan</h3>
                <div className="mt-2 text-sm text-yellow-700">
                  <p>Pastikan backup otomatis tetap aktif agar pemulihan data dapat dilakukan kapan saja.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <ConfirmationModal
        open={confirmState.open}
        onClose={closeConfirmation}
        title={confirmState.mode === 'restore' ? 'Konfirmasi Restore Backup' : 'Konfirmasi Buat Backup'}
        message={
          confirmState.mode === 'restore'
            ? getRestoreWarning(confirmState.backup)
            : `Buat backup ${getTypeLabel(manualBackupType).toLowerCase()} sekarang? ${getTypeWarning(manualBackupType)}`
        }
        onConfirm={handleConfirmedAction}
        confirmText={confirmState.mode === 'restore' ? 'Restore' : 'Buat Backup'}
        cancelText="Batal"
        type="warning"
      />
    </div>
  );
};

export default BackupManagement;

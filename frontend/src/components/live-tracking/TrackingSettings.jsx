import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Box,
  Typography,
  Switch,
  FormControlLabel,
  Slider,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Divider,
  Tabs,
  Tab,
  Card,
  CardContent,
  Alert,
  TextField,
  Chip
} from '@mui/material';
import {
  Settings,
  RefreshCw,
  Map,
  Eye,
  Bell,
  Clock,
  Palette,
  Monitor,
  Wifi,
  Volume2,
  Save,
  RotateCcw
} from 'lucide-react';

const DEFAULT_HISTORY_POLICY = {
  enabled: true,
  minDistanceMeters: 20,
  retentionDays: 30,
  cleanupTime: '02:15',
  currentStoreRebuildTime: '00:10',
  readCurrentStoreEnabled: true,
  persistIdleSeconds: 300,
  source: 'config',
};

const DEFAULT_DATA_SOURCES = {
  list: 'request_pipeline',
  summary: 'request_pipeline',
  groupedSummary: 'request_pipeline',
  priorityQueues: 'request_pipeline',
};

const normalizeHistoryPolicy = (raw = {}) => ({
  enabled: raw?.enabled === undefined ? DEFAULT_HISTORY_POLICY.enabled : Boolean(raw?.enabled),
  minDistanceMeters: Number(raw?.minDistanceMeters ?? DEFAULT_HISTORY_POLICY.minDistanceMeters),
  retentionDays: Number(raw?.retentionDays ?? DEFAULT_HISTORY_POLICY.retentionDays),
  cleanupTime: String(raw?.cleanupTime ?? DEFAULT_HISTORY_POLICY.cleanupTime),
  currentStoreRebuildTime: String(raw?.currentStoreRebuildTime ?? DEFAULT_HISTORY_POLICY.currentStoreRebuildTime),
  readCurrentStoreEnabled: raw?.readCurrentStoreEnabled === undefined
    ? DEFAULT_HISTORY_POLICY.readCurrentStoreEnabled
    : Boolean(raw?.readCurrentStoreEnabled),
  persistIdleSeconds: Number(raw?.persistIdleSeconds ?? DEFAULT_HISTORY_POLICY.persistIdleSeconds),
  source: String(raw?.source ?? DEFAULT_HISTORY_POLICY.source),
});

const getDataSourceLabel = (value) => (
  value === 'redis_current_store' ? 'Redis current-store' : 'Request pipeline'
);

const TrackingSettings = ({
  open,
  onClose,
  settings,
  historyPolicy = DEFAULT_HISTORY_POLICY,
  dataSources = DEFAULT_DATA_SOURCES,
  historyPolicyLoading = false,
  historyPolicySaving = false,
  canManageHistoryPolicy = false,
  onSave,
  onReset
}) => {
  const [activeTab, setActiveTab] = useState(0);
  const [localSettings, setLocalSettings] = useState(settings);
  const [localHistoryPolicy, setLocalHistoryPolicy] = useState(() => normalizeHistoryPolicy(historyPolicy));

  useEffect(() => {
    if (!open) {
      return;
    }

    setLocalSettings(settings);
    setLocalHistoryPolicy(normalizeHistoryPolicy(historyPolicy));
  }, [historyPolicy, open, settings]);

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
  };

  const handleSettingChange = (category, key, value) => {
    setLocalSettings(prev => ({
      ...prev,
      [category]: {
        ...prev[category],
        [key]: value
      }
    }));
  };

  const handleHistoryPolicyChange = (key, value) => {
    setLocalHistoryPolicy((previous) => ({
      ...previous,
      [key]: value
    }));
  };

  const handleSave = async () => {
    const result = await onSave?.(localSettings, localHistoryPolicy);
    if (result === false) {
      return;
    }

    onClose();
  };

  const handleReset = () => {
    onReset();
  };

  const TabPanel = ({ children, value, index }) => (
    <div hidden={value !== index} className="mt-4">
      {value === index && children}
    </div>
  );

  return (
    <Dialog 
      open={open} 
      onClose={onClose} 
      maxWidth="md" 
      fullWidth
      PaperProps={{
        className: "rounded-lg"
      }}
    >
      <DialogTitle className="flex items-center gap-2 pb-2">
        <Settings className="w-5 h-5 text-blue-600" />
        <Typography variant="h6" className="font-semibold">
          Pengaturan Live Tracking
        </Typography>
      </DialogTitle>

      <DialogContent className="p-0">
        <Box className="border-b border-gray-200">
          <Tabs 
            value={activeTab} 
            onChange={handleTabChange}
            variant="scrollable"
            scrollButtons="auto"
            className="px-6"
          >
            <Tab 
              icon={<RefreshCw className="w-4 h-4" />} 
              label="Refresh" 
              className="min-h-12"
            />
            <Tab 
              icon={<Clock className="w-4 h-4" />} 
              label="Histori" 
              className="min-h-12"
            />
            <Tab 
              icon={<Map className="w-4 h-4" />} 
              label="Peta" 
              className="min-h-12"
            />
            <Tab 
              icon={<Eye className="w-4 h-4" />} 
              label="Tampilan" 
              className="min-h-12"
            />
            <Tab 
              icon={<Bell className="w-4 h-4" />} 
              label="Notifikasi" 
              className="min-h-12"
            />
          </Tabs>
        </Box>

        <Box className="p-6">
          {/* Refresh Settings */}
          <TabPanel value={activeTab} index={0}>
            <Card className="mb-4">
              <CardContent>
                <Box className="flex items-center gap-2 mb-4">
                  <RefreshCw className="w-5 h-5 text-blue-600" />
                  <Typography variant="h6" className="font-medium">
                    Pengaturan Refresh Data
                  </Typography>
                </Box>

                <Box className="space-y-4">
                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.refresh.autoRefresh}
                        onChange={(e) => handleSettingChange('refresh', 'autoRefresh', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Auto Refresh
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Refresh data secara otomatis
                        </Typography>
                      </Box>
                    }
                  />

                  {localSettings.refresh.autoRefresh && (
                    <Box className="ml-8">
                      <Typography variant="body2" className="mb-2 font-medium">
                        Interval Refresh: {localSettings.refresh.interval} detik
                      </Typography>
                      <Slider
                        value={localSettings.refresh.interval}
                        onChange={(e, value) => handleSettingChange('refresh', 'interval', value)}
                        min={30}
                        max={300}
                        step={10}
                        marks={[
                          { value: 30, label: '30s' },
                          { value: 60, label: '1m' },
                          { value: 120, label: '2m' },
                          { value: 300, label: '5m' }
                        ]}
                        className="mt-2"
                      />
                    </Box>
                  )}

                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.refresh.refreshOnFocus}
                        onChange={(e) => handleSettingChange('refresh', 'refreshOnFocus', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Refresh saat Focus
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Refresh data saat kembali ke halaman
                        </Typography>
                      </Box>
                    }
                  />
                </Box>
              </CardContent>
            </Card>

            <Alert severity="info" className="mt-4">
              <Typography variant="body2">
                Interval refresh yang terlalu cepat dapat mempengaruhi performa sistem. 
                Disarankan menggunakan interval minimal 30 detik. Saat ada siswa
                dengan status GPS mati, dashboard akan mempercepat polling
                sementara agar pemulihan ke status online terlihat lebih cepat.
                Aplikasi mobile tetap memakai realtime adaptif: sekitar 30 detik
                saat bergerak aktif dan melambat saat perangkat diam agar baterai
                dan bandwidth lebih hemat.
              </Typography>
            </Alert>
          </TabPanel>

          <TabPanel value={activeTab} index={1}>
            <Card className="mb-4">
              <CardContent>
                <Box className="flex items-center gap-2 mb-4">
                  <Clock className="w-5 h-5 text-slate-700" />
                  <Typography variant="h6" className="font-medium">
                    Policy Histori Tracking
                  </Typography>
                </Box>

                <Box className="grid gap-4 md:grid-cols-3">
                  <FormControlLabel
                    control={
                      <Switch
                        checked={Boolean(localHistoryPolicy.enabled)}
                        onChange={(event) => handleHistoryPolicyChange('enabled', event.target.checked)}
                        color="primary"
                        disabled={!canManageHistoryPolicy || historyPolicyLoading || historyPolicySaving}
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Live tracking aktif
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Jika dimatikan, mobile berhenti kirim realtime dan dashboard menandai status netral.
                        </Typography>
                      </Box>
                    }
                    className="md:col-span-3"
                  />

                  <TextField
                    label="Minimal Gerak (meter)"
                    type="number"
                    value={localHistoryPolicy.minDistanceMeters}
                    onChange={(event) => handleHistoryPolicyChange('minDistanceMeters', Math.max(1, Number(event.target.value || 20)))}
                    inputProps={{ min: 1, max: 500, step: 1 }}
                    disabled={!canManageHistoryPolicy || historyPolicyLoading || historyPolicySaving}
                    helperText="Histori baru disimpan jika perpindahan mencapai batas ini."
                    fullWidth
                  />

                  <TextField
                    label="Retensi Histori (hari)"
                    type="number"
                    value={localHistoryPolicy.retentionDays}
                    onChange={(event) => handleHistoryPolicyChange('retentionDays', Math.max(1, Number(event.target.value || 30)))}
                    inputProps={{ min: 1, max: 3650, step: 1 }}
                    disabled={!canManageHistoryPolicy || historyPolicyLoading || historyPolicySaving}
                    helperText="Data lama dibersihkan otomatis setelah melewati retensi."
                    fullWidth
                  />

                  <TextField
                    label="Jam Cleanup Harian"
                    type="time"
                    value={localHistoryPolicy.cleanupTime}
                    onChange={(event) => handleHistoryPolicyChange('cleanupTime', event.target.value || DEFAULT_HISTORY_POLICY.cleanupTime)}
                    disabled={!canManageHistoryPolicy || historyPolicyLoading || historyPolicySaving}
                    helperText="Scheduler cleanup harian memakai jam ini."
                    fullWidth
                    InputLabelProps={{ shrink: true }}
                  />
                </Box>

                <Box className="mt-4 flex flex-wrap gap-2">
                  <Chip size="small" variant="outlined" label={`Checkpoint diam ${Math.round(Number(localHistoryPolicy.persistIdleSeconds || 300) / 60)} menit`} />
                  <Chip size="small" variant="outlined" label={`Rebuild store ${localHistoryPolicy.currentStoreRebuildTime || '00:10'}`} />
                  <Chip
                    size="small"
                    variant="outlined"
                    color={localHistoryPolicy.enabled ? 'success' : 'default'}
                    label={localHistoryPolicy.enabled ? 'Live tracking aktif' : 'Live tracking nonaktif'}
                  />
                  <Chip
                    size="small"
                    variant="outlined"
                    color={localHistoryPolicy.readCurrentStoreEnabled ? 'success' : 'default'}
                    label={localHistoryPolicy.readCurrentStoreEnabled ? 'Baca current-store aktif' : 'Baca current-store nonaktif'}
                  />
                  <Chip size="small" variant="outlined" label={`Daftar ${getDataSourceLabel(dataSources.list)}`} />
                  <Chip size="small" variant="outlined" label={`Ringkasan ${getDataSourceLabel(dataSources.summary)}`} />
                  <Chip size="small" variant="outlined" label={`Sumber ${localHistoryPolicy.source || 'config'}`} />
                  {historyPolicyLoading ? (
                    <Chip size="small" color="info" variant="outlined" label="Memuat policy runtime" />
                  ) : null}
                </Box>
              </CardContent>
            </Card>

            <Alert severity={canManageHistoryPolicy ? 'info' : 'warning'} className="mt-4">
              <Typography variant="body2">
                {canManageHistoryPolicy
                  ? 'Histori disimpan saat siswa bergerak cukup jauh, saat status penting berubah, dan tetap diberi checkpoint berkala saat diam agar jejak sesi tidak putus. Toggle aktif/nonaktif di sini berlaku global.'
                  : 'Policy histori mengikuti pengaturan server. Anda bisa melihat nilainya di sini, tetapi hanya admin attendance yang dapat mengubahnya.'}
              </Typography>
            </Alert>
          </TabPanel>

          {/* Map Settings */}
          <TabPanel value={activeTab} index={2}>
            <Card className="mb-4">
              <CardContent>
                <Box className="flex items-center gap-2 mb-4">
                  <Map className="w-5 h-5 text-green-600" />
                  <Typography variant="h6" className="font-medium">
                    Pengaturan Peta
                  </Typography>
                </Box>

                <Box className="space-y-4">
                  <Box>
                    <Typography variant="body2" className="mb-2 font-medium">
                      Zoom Default: {localSettings.map.defaultZoom}
                    </Typography>
                    <Slider
                      value={localSettings.map.defaultZoom}
                      onChange={(e, value) => handleSettingChange('map', 'defaultZoom', value)}
                      min={10}
                      max={20}
                      step={1}
                      marks={[
                        { value: 10, label: '10' },
                        { value: 15, label: '15' },
                        { value: 20, label: '20' }
                      ]}
                    />
                  </Box>

                  <FormControl fullWidth>
                    <InputLabel>Tema Peta</InputLabel>
                    <Select
                      value={localSettings.map.theme}
                      onChange={(e) => handleSettingChange('map', 'theme', e.target.value)}
                      label="Tema Peta"
                    >
                      <MenuItem value="default">Default</MenuItem>
                      <MenuItem value="satellite">Satelit</MenuItem>
                      <MenuItem value="terrain">Terrain</MenuItem>
                      <MenuItem value="dark">Dark Mode</MenuItem>
                    </Select>
                  </FormControl>

                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.map.showTrafficLayer}
                        onChange={(e) => handleSettingChange('map', 'showTrafficLayer', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Tampilkan Layer Traffic
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Menampilkan informasi lalu lintas (jika provider tile dikonfigurasi)
                        </Typography>
                      </Box>
                    }
                  />

                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.map.autoCenter}
                        onChange={(e) => handleSettingChange('map', 'autoCenter', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Auto Center
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Otomatis pusatkan peta pada siswa aktif
                        </Typography>
                      </Box>
                    }
                  />
                </Box>
              </CardContent>
            </Card>
          </TabPanel>

          {/* Display Settings */}
          <TabPanel value={activeTab} index={3}>
            <Card className="mb-4">
              <CardContent>
                <Box className="flex items-center gap-2 mb-4">
                  <Eye className="w-5 h-5 text-purple-600" />
                  <Typography variant="h6" className="font-medium">
                    Pengaturan Tampilan
                  </Typography>
                </Box>

                <Box className="space-y-4">
                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.display.showInactiveStudents}
                        onChange={(e) => handleSettingChange('display', 'showInactiveStudents', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Tampilkan Siswa Tidak Aktif
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Menampilkan siswa yang belum ada data tracking
                        </Typography>
                      </Box>
                    }
                  />

                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.display.showLastLocation}
                        onChange={(e) => handleSettingChange('display', 'showLastLocation', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Tampilkan Lokasi Terakhir
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Menampilkan lokasi terakhir siswa yang diketahui
                        </Typography>
                      </Box>
                    }
                  />

                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.display.showAccuracyCircle}
                        onChange={(e) => handleSettingChange('display', 'showAccuracyCircle', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Tampilkan Lingkaran Akurasi
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Menampilkan radius akurasi lokasi GPS
                        </Typography>
                      </Box>
                    }
                  />

                  <Box>
                    <Typography variant="body2" className="mb-2 font-medium">
                      Jumlah Siswa per Halaman: {localSettings.display.maxStudentsInList}
                    </Typography>
                    <Typography variant="caption" className="text-gray-600">
                      Dipakai sebagai ukuran halaman daftar operasional. Peta hanya memakai cohort halaman aktif agar tetap ringan.
                    </Typography>
                    <Slider
                      value={localSettings.display.maxStudentsInList}
                      onChange={(e, value) => handleSettingChange('display', 'maxStudentsInList', value)}
                      min={25}
                      max={200}
                      step={25}
                      marks={[
                        { value: 25, label: '25' },
                        { value: 50, label: '50' },
                        { value: 100, label: '100' },
                        { value: 150, label: '150' },
                        { value: 200, label: '200' }
                      ]}
                    />
                  </Box>
                </Box>
              </CardContent>
            </Card>
          </TabPanel>

          {/* Notification Settings */}
          <TabPanel value={activeTab} index={4}>
            <Card className="mb-4">
              <CardContent>
                <Box className="flex items-center gap-2 mb-4">
                  <Bell className="w-5 h-5 text-orange-600" />
                  <Typography variant="h6" className="font-medium">
                    Pengaturan Notifikasi
                  </Typography>
                </Box>

                <Box className="space-y-4">
                  <FormControlLabel
                    control={
                      <Switch
                        checked={localSettings.notifications.enabled}
                        onChange={(e) => handleSettingChange('notifications', 'enabled', e.target.checked)}
                        color="primary"
                      />
                    }
                    label={
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          Aktifkan Notifikasi
                        </Typography>
                        <Typography variant="caption" className="text-gray-600">
                          Mengaktifkan semua notifikasi sistem
                        </Typography>
                      </Box>
                    }
                  />

                  {localSettings.notifications.enabled && (
                    <Box className="ml-8 space-y-3">
                      <FormControlLabel
                        control={
                          <Switch
                            checked={localSettings.notifications.studentOutOfArea}
                            onChange={(e) => handleSettingChange('notifications', 'studentOutOfArea', e.target.checked)}
                            color="primary"
                          />
                        }
                        label={
                          <Box>
                            <Typography variant="body2" className="font-medium">
                              Siswa Keluar Area
                            </Typography>
                            <Typography variant="caption" className="text-gray-600">
                              Notifikasi saat siswa keluar dari area sekolah
                            </Typography>
                          </Box>
                        }
                      />

                      <FormControlLabel
                        control={
                          <Switch
                            checked={localSettings.notifications.connectionLost}
                            onChange={(e) => handleSettingChange('notifications', 'connectionLost', e.target.checked)}
                            color="primary"
                          />
                        }
                        label={
                          <Box>
                            <Typography variant="body2" className="font-medium">
                              Koneksi Terputus
                            </Typography>
                            <Typography variant="caption" className="text-gray-600">
                              Notifikasi saat koneksi tracking terputus
                            </Typography>
                          </Box>
                        }
                      />
                    </Box>
                  )}
                </Box>
              </CardContent>
            </Card>

            {!localSettings.notifications.enabled && (
              <Alert severity="warning">
                <Typography variant="body2">
                  Notifikasi dinonaktifkan. Anda tidak akan menerima peringatan penting 
                  tentang status tracking siswa.
                </Typography>
              </Alert>
            )}
          </TabPanel>
        </Box>
      </DialogContent>

      <DialogActions className="px-6 py-4 border-t border-gray-200">
        <Box className="flex justify-between w-full">
          <Button
            onClick={handleReset}
            startIcon={<RotateCcw className="w-4 h-4" />}
            color="error"
            variant="outlined"
            className="text-red-600 border-red-300 hover:bg-red-50"
          >
            Reset Default
          </Button>
          
          <Box className="flex gap-2">
            <Button 
              onClick={onClose}
              color="inherit"
              className="text-gray-600"
            >
              Batal
            </Button>
            <Button
              onClick={handleSave}
              startIcon={<Save className="w-4 h-4" />}
              variant="contained"
              color="primary"
              disabled={historyPolicySaving}
              className="bg-blue-600 hover:bg-blue-700"
            >
              {historyPolicySaving ? 'Menyimpan...' : 'Simpan Pengaturan'}
            </Button>
          </Box>
        </Box>
      </DialogActions>
    </Dialog>
  );
};

export default TrackingSettings;

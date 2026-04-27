import React, { useMemo, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  Camera,
  Check,
  CheckCircle2,
  Clock,
  GraduationCap,
  MapPin,
  Settings,
  Sparkles,
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import attendanceSchemaService from '../../services/attendanceSchemaService';

const WORKING_DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const syncStudentScheduleToDefaults = (source = {}) => {
  const siswaJamMasuk = source.siswa_jam_masuk || source.jam_masuk_default || '07:00';
  const siswaJamPulang = source.siswa_jam_pulang || source.jam_pulang_default || '14:00';
  const siswaToleransi =
    source.siswa_toleransi !== undefined && source.siswa_toleransi !== null
      ? source.siswa_toleransi
      : (source.toleransi_default ?? 10);
  const siswaOpenTime =
    source.minimal_open_time_siswa !== undefined && source.minimal_open_time_siswa !== null
      ? source.minimal_open_time_siswa
      : (source.minimal_open_time_staff ?? 70);

  return {
    ...source,
    jam_masuk_default: siswaJamMasuk,
    jam_pulang_default: siswaJamPulang,
    toleransi_default: siswaToleransi,
    minimal_open_time_staff: siswaOpenTime,
    siswa_jam_masuk: siswaJamMasuk,
    siswa_jam_pulang: siswaJamPulang,
    siswa_toleransi: siswaToleransi,
    minimal_open_time_siswa: siswaOpenTime,
  };
};

const templates = [
  {
    id: 'siswa-default',
    name: 'Template Siswa Default',
    description: '07:00 - 14:00, toleransi 10 menit, GPS wajib, foto opsional.',
    icon: GraduationCap,
    tone: 'green',
    config: {
      schema_name: 'Skema Siswa Default',
      schema_type: 'role',
      target_role: 'Siswa',
      jam_masuk_default: '07:00',
      jam_pulang_default: '14:00',
      toleransi_default: 10,
      minimal_open_time_staff: 70,
      wajib_gps: true,
      wajib_foto: false,
      hari_kerja: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
      gps_accuracy: 20,
      is_active: true,
      is_default: false,
      priority: 1,
      verification_mode: 'async_pending',
      attendance_scope: 'siswa_only',
      siswa_jam_masuk: '07:00',
      siswa_jam_pulang: '14:00',
      siswa_toleransi: 10,
      minimal_open_time_siswa: 70,
    },
  },
  {
    id: 'siswa-custom',
    name: 'Template Siswa Kustom',
    description: 'Basis untuk konfigurasi mandiri dengan foto + GPS aktif.',
    icon: Settings,
    tone: 'purple',
    config: {
      schema_name: '',
      schema_type: 'role',
      target_role: 'Siswa',
      jam_masuk_default: '08:00',
      jam_pulang_default: '16:00',
      toleransi_default: 15,
      minimal_open_time_staff: 70,
      wajib_gps: true,
      wajib_foto: true,
      hari_kerja: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
      gps_accuracy: 20,
      is_active: true,
      is_default: false,
      priority: 0,
      verification_mode: 'async_pending',
      attendance_scope: 'siswa_only',
      siswa_jam_masuk: '08:00',
      siswa_jam_pulang: '16:00',
      siswa_toleransi: 15,
      minimal_open_time_siswa: 70,
    },
  },
];

const steps = [
  { key: 'template', label: 'Pilih Template' },
  { key: 'schedule', label: 'Atur Jadwal' },
  { key: 'requirements', label: 'Atur Persyaratan' },
  { key: 'review', label: 'Konfirmasi' },
];

const QuickSetupWizard = ({ onComplete, onCancel }) => {
  const [currentStep, setCurrentStep] = useState(1);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(false);

  const isTemplateChosen = Boolean(selectedTemplate && config);

  const activeTemplateCardTone = (tone) => {
    if (tone === 'green') return 'border-green-500 bg-green-50';
    if (tone === 'purple') return 'border-purple-500 bg-purple-50';
    return 'border-blue-500 bg-blue-50';
  };

  const selectTemplate = (template) => {
    setSelectedTemplate(template);
    setConfig(syncStudentScheduleToDefaults(template.config));
  };

  const updateField = (field, value) => {
    setConfig((prev) =>
      syncStudentScheduleToDefaults({
        ...(prev || {}),
        [field]: value,
      })
    );
  };

  const toggleWorkingDay = (day) => {
    setConfig((prev) => {
      const currentDays = Array.isArray(prev?.hari_kerja) ? prev.hari_kerja : [];
      const exists = currentDays.includes(day);
      return {
        ...prev,
        hari_kerja: exists ? currentDays.filter((item) => item !== day) : [...currentDays, day],
      };
    });
  };

  const moveNext = () => {
    if (!isTemplateChosen) {
      toast.error('Pilih template terlebih dahulu.');
      return;
    }

    if (currentStep < 4) {
      setCurrentStep((prev) => prev + 1);
    }
  };

  const moveBack = () => {
    if (currentStep > 1) {
      setCurrentStep((prev) => prev - 1);
    }
  };

  const handleSubmit = async () => {
    if (!config?.schema_name?.trim()) {
      toast.error('Nama skema wajib diisi.');
      return;
    }

    try {
      setLoading(true);
      const apiPayload = attendanceSchemaService.formatSchemaForAPI(
        syncStudentScheduleToDefaults(config)
      );
      await attendanceSchemaService.createSchema(apiPayload);
      toast.success(`Skema "${config.schema_name}" berhasil dibuat.`);
      onComplete?.();
    } catch (error) {
      console.error('Error creating schema:', error);
      toast.error('Gagal membuat skema absensi.');
    } finally {
      setLoading(false);
    }
  };

  const selectedSummary = useMemo(() => {
    if (!selectedTemplate || !config) return null;
    return {
      template: selectedTemplate.name,
      jam: `${config.siswa_jam_masuk || config.jam_masuk_default || '-'} - ${config.siswa_jam_pulang || config.jam_pulang_default || '-'}`,
      toleransi: `${config.siswa_toleransi ?? config.toleransi_default ?? 0} menit`,
      gps: config.wajib_gps ? 'Wajib' : 'Tidak wajib',
      foto: config.wajib_foto ? 'Wajib' : 'Tidak wajib',
      hariKerja: Array.isArray(config.hari_kerja) ? config.hari_kerja.join(', ') : '-',
    };
  }, [selectedTemplate, config]);

  const renderStepTemplate = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Pilih Template</h3>
        <p className="text-sm text-gray-600 mt-1">
          Pilih baseline skema untuk mempercepat konfigurasi absensi siswa.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {templates.map((template) => {
          const Icon = template.icon;
          const selected = selectedTemplate?.id === template.id;

          return (
            <button
              key={template.id}
              type="button"
              onClick={() => selectTemplate(template)}
              className={`text-left rounded-xl border p-4 transition ${
                selected ? activeTemplateCardTone(template.tone) : 'border-gray-200 hover:border-gray-300 bg-white'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="inline-flex h-10 w-10 rounded-lg bg-white/80 border border-gray-200 items-center justify-center">
                  <Icon className="h-5 w-5 text-gray-700" />
                </div>
                {selected && <CheckCircle2 className="h-5 w-5 text-blue-600" />}
              </div>
              <p className="mt-3 text-sm font-semibold text-gray-900">{template.name}</p>
              <p className="mt-1 text-xs text-gray-600">{template.description}</p>
            </button>
          );
        })}
      </div>

      {config && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
          Template aktif: <span className="font-semibold">{selectedTemplate?.name}</span>
        </div>
      )}
    </div>
  );

  const renderStepSchedule = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Atur Jadwal</h3>
        <p className="text-sm text-gray-600 mt-1">Atur jam masuk, jam pulang, toleransi, dan hari aktif absensi.</p>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Nama Skema</label>
            <input
              type="text"
              value={config?.schema_name || ''}
              onChange={(event) => updateField('schema_name', event.target.value)}
              placeholder="Contoh: Skema Siswa 2026"
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Jam Masuk</label>
            <input
              type="time"
              value={config?.siswa_jam_masuk || config?.jam_masuk_default || ''}
              onChange={(event) => updateField('siswa_jam_masuk', event.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Jam Pulang</label>
            <input
              type="time"
              value={config?.siswa_jam_pulang || config?.jam_pulang_default || ''}
              onChange={(event) => updateField('siswa_jam_pulang', event.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Toleransi (menit)</label>
            <input
              type="number"
              min="0"
              value={config?.siswa_toleransi ?? config?.toleransi_default ?? 0}
              onChange={(event) => updateField('siswa_toleransi', Number(event.target.value) || 0)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Buka Absen Sebelum (menit)</label>
            <input
              type="number"
              min="0"
              value={config?.minimal_open_time_siswa ?? config?.minimal_open_time_staff ?? 0}
              onChange={(event) => updateField('minimal_open_time_siswa', Number(event.target.value) || 0)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Hari Kerja</h4>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
          {WORKING_DAYS.map((day) => {
            const active = config?.hari_kerja?.includes(day);
            return (
              <label
                key={day}
                className={`flex items-center gap-2 px-3 py-2 rounded-md border cursor-pointer ${
                  active ? 'border-blue-500 bg-blue-50' : 'border-gray-200'
                }`}
              >
                <input
                  type="checkbox"
                  checked={Boolean(active)}
                  onChange={() => toggleWorkingDay(day)}
                  className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span className="text-xs text-gray-800">{day}</span>
              </label>
            );
          })}
        </div>
      </div>
    </div>
  );

  const renderStepRequirements = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Atur Persyaratan</h3>
        <p className="text-sm text-gray-600 mt-1">Tentukan syarat GPS, selfie/foto, dan ketentuan akurasi lokasi.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="bg-white border border-gray-200 rounded-xl p-4 space-y-4">
          <div className="flex items-center gap-2">
            <MapPin className="h-4 w-4 text-emerald-600" />
            <h4 className="text-sm font-semibold text-gray-900">GPS</h4>
          </div>
          <label className="flex items-center gap-2 text-sm text-gray-800">
            <input
              type="checkbox"
              checked={Boolean(config?.wajib_gps)}
              onChange={(event) => updateField('wajib_gps', event.target.checked)}
              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            Wajib validasi geolocation
          </label>
          <div className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700">
            Validasi lokasi mengikuti tipe area per lokasi aktif yang dikonfigurasi di menu Manajemen Lokasi GPS, baik Circle maupun Polygon.
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Akurasi GPS minimum (meter)</label>
            <input
              type="number"
              min="1"
              value={config?.gps_accuracy ?? 20}
              onChange={(event) => updateField('gps_accuracy', Number(event.target.value) || 20)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-4 space-y-4">
          <div className="flex items-center gap-2">
            <Camera className="h-4 w-4 text-purple-600" />
            <h4 className="text-sm font-semibold text-gray-900">Foto / Selfie</h4>
          </div>
          <label className="flex items-center gap-2 text-sm text-gray-800">
            <input
              type="checkbox"
              checked={Boolean(config?.wajib_foto)}
              onChange={(event) => updateField('wajib_foto', event.target.checked)}
              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            Wajib foto selfie saat check-in / check-out
          </label>
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Untuk trafik ramai, mode verifikasi disarankan <span className="font-semibold">Async Pending</span>.
          </div>
        </div>
      </div>
    </div>
  );

  const renderStepReview = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Konfirmasi</h3>
        <p className="text-sm text-gray-600 mt-1">Pastikan konfigurasi sudah benar sebelum membuat skema.</p>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Template</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.template || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Nama Skema</p>
            <p className="font-semibold text-gray-900 mt-1">{config?.schema_name || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Jam Aktif</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.jam || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Toleransi</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.toleransi || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Validasi GPS</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.gps || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3">
            <p className="text-xs text-gray-500">Selfie / Foto</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.foto || '-'}</p>
          </div>
          <div className="rounded-lg border border-gray-200 p-3 md:col-span-2">
            <p className="text-xs text-gray-500">Hari Kerja</p>
            <p className="font-semibold text-gray-900 mt-1">{selectedSummary?.hariKerja || '-'}</p>
          </div>
        </div>
      </div>
    </div>
  );

  const renderCurrentStep = () => {
    if (currentStep === 1) return renderStepTemplate();
    if (currentStep === 2) return renderStepSchedule();
    if (currentStep === 3) return renderStepRequirements();
    return renderStepReview();
  };

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
            <Sparkles className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Setup Cepat Skema Absensi</h2>
            <p className="text-sm text-gray-600 mt-1">
              Wizard ini membantu membuat skema dasar absensi siswa dalam 4 langkah ringkas. Assignment siswa dilakukan setelah skema selesai dibuat.
            </p>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
          {steps.map((step, index) => {
            const stepNumber = index + 1;
            const active = currentStep === stepNumber;
            const completed = currentStep > stepNumber;
            return (
              <div
                key={step.key}
                className={`rounded-lg border px-3 py-2 text-xs ${
                  active
                    ? 'border-blue-500 bg-blue-50 text-blue-800'
                    : completed
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                      : 'border-gray-200 text-gray-500'
                }`}
              >
                <div className="flex items-center gap-2">
                  <span
                    className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold ${
                      active
                        ? 'bg-blue-600 text-white'
                        : completed
                          ? 'bg-emerald-600 text-white'
                          : 'bg-gray-200 text-gray-700'
                    }`}
                  >
                    {completed ? <Check className="h-3 w-3" /> : stepNumber}
                  </span>
                  <span className="font-medium">{step.label}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-5">{renderCurrentStep()}</div>

      <div className="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between gap-3">
        <button
          type="button"
          onClick={moveBack}
          disabled={currentStep === 1}
          className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali
        </button>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>

          {currentStep < 4 ? (
            <button
              type="button"
              onClick={moveNext}
              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
            >
              Lanjut
              <ArrowRight className="h-4 w-4" />
            </button>
          ) : (
            <button
              type="button"
              onClick={handleSubmit}
              disabled={loading}
              className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 disabled:opacity-60"
            >
              {loading ? <span className="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <Check className="h-4 w-4" />}
              {loading ? 'Menyimpan...' : 'Buat Skema'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default QuickSetupWizard;

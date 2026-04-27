import React, { useState, useEffect } from 'react';
import { Save, ArrowLeft, Clock, Users, MapPin, Camera, ScanFace } from 'lucide-react';
import attendanceSchemaService from '../../services/attendanceSchemaService';
import { toast } from 'react-hot-toast';
import {
  getLocationAreaSummary,
  getLocationTypeLabel,
  normalizeIdArray,
} from '../../utils/locationGeofence';

const DEFAULT_WORKING_DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

const normalizeWorkingDays = (value) => {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item).trim())
      .filter((item) => item.length > 0);
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) {
        return normalizeWorkingDays(parsed);
      }
      if (typeof parsed === 'string') {
        return normalizeWorkingDays(parsed);
      }
    } catch (_) {
      // Fallback untuk payload legacy yang dipisah koma.
      if (value.includes(',')) {
        return value
          .split(',')
          .map((item) => item.trim())
          .filter((item) => item.length > 0);
      }
    }
  }

  return [...DEFAULT_WORKING_DAYS];
};

const filterStudentAttendanceRoles = (roles = []) => {
  const normalized = Array.isArray(roles) ? roles : [];
  const studentRoles = normalized.filter((role) => {
    const rawValue = String(role?.value || role?.label || '').trim().toLowerCase();
    return rawValue === 'siswa';
  });

  return [
    { value: null, label: 'Semua Siswa' },
    ...studentRoles,
  ];
};

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

const AttendanceSchemaFormSimple = ({ schema, onSave, onCancel }) => {
  const [formData, setFormData] = useState({
    schema_name: '',
    schema_type: 'global',
    target_role: null,
    target_status: null,
    schema_description: '',
    is_active: true,
    is_default: false,
    is_mandatory: true,
    priority: 0,
    jam_masuk_default: '07:00',
    jam_pulang_default: '14:00',
    toleransi_default: 10,
    minimal_open_time_staff: 70,
    wajib_gps: true,
    wajib_foto: true,
    hari_kerja: [...DEFAULT_WORKING_DAYS],
    lokasi_gps_ids: null,
    siswa_jam_masuk: '07:00',
    siswa_jam_pulang: '14:00',
    siswa_toleransi: 10,
    minimal_open_time_siswa: 70,
    gps_accuracy: 20,
    face_verification_enabled: false,
    target_tingkat_ids: [],
    target_kelas_ids: []
  });

  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [targetRoles, setTargetRoles] = useState([]);
  const [availableLocations, setAvailableLocations] = useState([]);
  const [availableTingkat, setAvailableTingkat] = useState([]);
  const [availableKelas, setAvailableKelas] = useState([]);
  const [isLoadingKelas, setIsLoadingKelas] = useState(false);

  useEffect(() => {
    if (schema) {
      const normalizedTargetRole =
        String(schema.target_role || '').trim().toLowerCase() === 'siswa' ? 'Siswa' : null;

      const normalizedSchema = syncStudentScheduleToDefaults({
        ...schema,
        target_role: normalizedTargetRole,
        target_status: null,
        hari_kerja: normalizeWorkingDays(schema.hari_kerja),
        lokasi_gps_ids: schema.lokasi_gps_ids || null,
        face_verification_enabled: Boolean(schema.face_verification_enabled ?? false),
        target_tingkat_ids: normalizeIdArray(schema.target_tingkat_ids),
        target_kelas_ids: normalizeIdArray(schema.target_kelas_ids)
      });

      setFormData(normalizedSchema);
    }
  }, [schema]);

  useEffect(() => {
    // Load target roles and GPS locations from API
    const loadData = async () => {
      try {
        // Load roles
        const roles = await attendanceSchemaService.getTargetRoles();
        setTargetRoles(filterStudentAttendanceRoles(roles));

        // Load GPS locations and tingkat using centralized API service
        try {
          // Import and use the centralized API service
          const api = (await import('../../services/api')).default;

          const tingkatResponse = await api.get('/tingkat', {
            params: { is_active: true }
          });
          const tingkatRows = tingkatResponse.data?.data || [];
          setAvailableTingkat(Array.isArray(tingkatRows) ? tingkatRows : []);

          const locationsResponse = await api.get('/lokasi-gps');
          setAvailableLocations(locationsResponse.data.data || locationsResponse.data || []);
        } catch (locationError) {
          console.warn('Failed to load GPS/tingkat options:', locationError.message);
          setAvailableTingkat([]);
          setAvailableLocations([]);
        }
      } catch (error) {
        console.error('Error loading schema options:', error);
        setTargetRoles(filterStudentAttendanceRoles(attendanceSchemaService.getTargetRolesSync()));
        setAvailableTingkat([]);
        setAvailableLocations([]);
      }
    };

    loadData();
  }, []);

  useEffect(() => {
    const loadKelasByTingkat = async () => {
      const selectedTingkat = normalizeIdArray(formData.target_tingkat_ids);

      if (selectedTingkat.length === 0) {
        setAvailableKelas([]);
        setFormData((prev) => ({
          ...prev,
          target_kelas_ids: [],
        }));
        return;
      }

      setIsLoadingKelas(true);
      try {
        const api = (await import('../../services/api')).default;
        const responses = await Promise.all(
          selectedTingkat.map((tingkatId) =>
            api.get(`/kelas/tingkat/${tingkatId}`)
          )
        );

        const kelasMap = new Map();
        responses.forEach((response) => {
          const rows = Array.isArray(response.data) ? response.data : [];
          rows.forEach((kelas) => {
            if (!kelas?.id) return;
            kelasMap.set(kelas.id, {
              id: kelas.id,
              nama: kelas.namaKelas || kelas.nama_kelas || `Kelas ${kelas.id}`,
              tingkat: kelas.tingkat || '-',
            });
          });
        });

        const mergedKelas = Array.from(kelasMap.values()).sort((a, b) =>
          a.nama.localeCompare(b.nama, 'id')
        );
        setAvailableKelas(mergedKelas);

        // Sinkronkan target_kelas_ids agar tidak menyimpan kelas di luar tingkat terpilih
        const allowedIds = new Set(mergedKelas.map((item) => item.id));
        setFormData((prev) => ({
          ...prev,
          target_kelas_ids: normalizeIdArray(prev.target_kelas_ids).filter((id) => allowedIds.has(id)),
        }));
      } catch (error) {
        console.warn('Failed to load kelas by tingkat:', error?.message || error);
        setAvailableKelas([]);
      } finally {
        setIsLoadingKelas(false);
      }
    };

    loadKelasByTingkat();
  }, [formData.target_tingkat_ids]);

  const handleInputChange = (field, value) => {
    setFormData(prev => {
      const next = {
        ...prev,
        [field]: value
      };

      if (field === 'wajib_foto' && value === false) {
        next.face_verification_enabled = false;
      }

      if (field === 'face_verification_enabled' && value === true) {
        next.wajib_foto = true;
      }

      return next;
    });
    
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: null
      }));
    }
  };

  const handleWorkingDayChange = (day, checked) => {
    setFormData(prev => ({
      ...prev,
      hari_kerja: (() => {
        const currentDays = normalizeWorkingDays(prev.hari_kerja);
        const exists = currentDays.includes(day);

        if (checked && !exists) {
          return [...currentDays, day];
        }

        if (!checked && exists) {
          return currentDays.filter((item) => item !== day);
        }

        return currentDays;
      })()
    }));
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.schema_name.trim()) {
      newErrors.schema_name = 'Nama skema harus diisi';
    }

    if (!formData.schema_type) {
      newErrors.schema_type = 'Tipe skema harus dipilih';
    }

    if (!formData.siswa_jam_masuk) {
      newErrors.siswa_jam_masuk = 'Jam masuk siswa harus diisi';
    }

    if (!formData.siswa_jam_pulang) {
      newErrors.siswa_jam_pulang = 'Jam pulang siswa harus diisi';
    }

    if ((formData.siswa_toleransi ?? 0) < 0) {
      newErrors.siswa_toleransi = 'Toleransi siswa tidak boleh negatif';
    }

    if (formData.priority < 0) {
      newErrors.priority = 'Priority tidak boleh negatif';
    }

    const normalizedWorkingDays = normalizeWorkingDays(formData.hari_kerja);
    if (normalizedWorkingDays.length === 0) {
      newErrors.hari_kerja = 'Minimal pilih satu hari kerja';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      toast.error('Mohon periksa kembali form yang diisi');
      return;
    }

    try {
      setLoading(true);

      const normalizedForSubmit = syncStudentScheduleToDefaults(formData);
      const apiData = attendanceSchemaService.formatSchemaForAPI(normalizedForSubmit);
      
      if (schema?.id) {
        await attendanceSchemaService.updateSchema(schema.id, apiData);
        toast.success('Skema berhasil diperbarui');
      } else {
        await attendanceSchemaService.createSchema(apiData);
        toast.success('Skema berhasil dibuat');
      }
      
      onSave?.();
    } catch (error) {
      console.error('Error saving schema:', error);
      toast.error('Gagal menyimpan skema');
    } finally {
      setLoading(false);
    }
  };

  const schemaTypes = attendanceSchemaService.getSchemaTypes();
  const workingDays = attendanceSchemaService.getWorkingDays();
  const selectedWorkingDays = normalizeWorkingDays(formData.hari_kerja);
  const selectedLocationIds = normalizeIdArray(formData.lokasi_gps_ids);
  const selectedLocationDetails = availableLocations.filter((location) =>
    selectedLocationIds.includes(Number(location.id))
  );

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5 flex items-start gap-3">
        <button
          onClick={onCancel}
          className="h-9 w-9 inline-flex items-center justify-center hover:bg-gray-100 rounded-lg border border-gray-200"
        >
          <ArrowLeft className="h-4 w-4" />
        </button>
        <div>
          <h2 className="text-lg font-semibold text-gray-900">
            {schema ? 'Edit Skema Absensi' : 'Tambah Skema Absensi'}
          </h2>
          <p className="text-sm text-gray-600 mt-1">
            Kelola policy skema absensi siswa secara terstruktur.
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Basic Information */}
        <div className="bg-white p-6 rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <Users className="h-5 w-5" />
            Informasi Dasar
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Nama Skema *
              </label>
              <input
                type="text"
                value={formData.schema_name}
                onChange={(e) => handleInputChange('schema_name', e.target.value)}
                placeholder="Masukkan nama skema"
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.schema_name ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.schema_name && (
                <p className="text-sm text-red-500 mt-1">{errors.schema_name}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Tipe Skema *
              </label>
              <select
                value={formData.schema_type}
                onChange={(e) => handleInputChange('schema_type', e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.schema_type ? 'border-red-500' : 'border-gray-300'
                }`}
              >
                {schemaTypes.map((type) => (
                  <option key={type.value} value={type.value}>
                    {type.label}
                  </option>
                ))}
              </select>
              {errors.schema_type && (
                <p className="text-sm text-red-500 mt-1">{errors.schema_type}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Target Role
              </label>
              <select
                value={formData.target_role || ''}
                onChange={(e) => handleInputChange('target_role', e.target.value || null)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {targetRoles.map((role) => (
                  <option key={role.value || 'null'} value={role.value || ''}>
                    {role.label}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Target Status
              </label>
              <input
                type="text"
                value="Tidak dipakai (khusus absensi siswa)"
                readOnly
                className="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-600"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Priority
              </label>
              <input
                type="number"
                min="0"
                value={formData.priority}
                onChange={(e) => handleInputChange('priority', parseInt(e.target.value) || 0)}
                placeholder="0"
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.priority ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.priority && (
                <p className="text-sm text-red-500 mt-1">{errors.priority}</p>
              )}
            </div>
          </div>

          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Deskripsi
            </label>
            <textarea
              value={formData.schema_description || ''}
              onChange={(e) => handleInputChange('schema_description', e.target.value)}
              placeholder="Deskripsi skema absensi"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex items-center space-x-6 mt-4">
            <label className="flex items-center space-x-2">
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => handleInputChange('is_active', e.target.checked)}
                className="rounded"
              />
              <span className="text-sm">Aktif</span>
            </label>

            <label className="flex items-center space-x-2">
              <input
                type="checkbox"
                checked={formData.is_default}
                onChange={(e) => handleInputChange('is_default', e.target.checked)}
                className="rounded"
              />
              <span className="text-sm">Default</span>
            </label>

            <label className="flex items-center space-x-2">
              <input
                type="checkbox"
                checked={formData.is_mandatory}
                onChange={(e) => handleInputChange('is_mandatory', e.target.checked)}
                className="rounded"
              />
              <span className="text-sm">Wajib</span>
            </label>
          </div>
        </div>

        {/* Working Hours */}
        <div className="bg-white p-6 rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <Clock className="h-5 w-5" />
            Jam Absensi Siswa (Aktif Runtime)
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Jam Masuk *
              </label>
              <input
                type="time"
                value={formData.siswa_jam_masuk}
                onChange={(e) => handleInputChange('siswa_jam_masuk', e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.siswa_jam_masuk ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.siswa_jam_masuk && (
                <p className="text-sm text-red-500 mt-1">{errors.siswa_jam_masuk}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Jam Pulang *
              </label>
              <input
                type="time"
                value={formData.siswa_jam_pulang}
                onChange={(e) => handleInputChange('siswa_jam_pulang', e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.siswa_jam_pulang ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.siswa_jam_pulang && (
                <p className="text-sm text-red-500 mt-1">{errors.siswa_jam_pulang}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Toleransi (menit)
              </label>
              <input
                type="number"
                min="0"
                value={formData.siswa_toleransi}
                onChange={(e) => handleInputChange('siswa_toleransi', parseInt(e.target.value) || 0)}
                placeholder="10"
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  errors.siswa_toleransi ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.siswa_toleransi && (
                <p className="text-sm text-red-500 mt-1">{errors.siswa_toleransi}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Buka Sebelum (menit)
              </label>
              <input
                type="number"
                min="0"
                value={formData.minimal_open_time_siswa}
                onChange={(e) => handleInputChange('minimal_open_time_siswa', parseInt(e.target.value) || 0)}
                placeholder="70"
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
          <p className="text-xs text-gray-500 mt-3">
            Nilai ini otomatis disinkronkan ke field default backend untuk kompatibilitas data lama.
          </p>
        </div>

        {/* Requirements */}
        <div className="bg-white p-6 rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <MapPin className="h-5 w-5" />
            Persyaratan Absensi
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <label className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={formData.wajib_gps}
                  onChange={(e) => handleInputChange('wajib_gps', e.target.checked)}
                  className="rounded"
                />
                <MapPin className="h-4 w-4" />
                <span className="text-sm">Wajib GPS</span>
              </label>

              <label className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={formData.wajib_foto}
                  onChange={(e) => handleInputChange('wajib_foto', e.target.checked)}
                  className="rounded"
                />
                <Camera className="h-4 w-4" />
                <span className="text-sm">Wajib Foto</span>
              </label>

              <label className="flex items-start space-x-2">
                <input
                  type="checkbox"
                  checked={Boolean(formData.face_verification_enabled)}
                  onChange={(e) => handleInputChange('face_verification_enabled', e.target.checked)}
                  className="rounded mt-1"
                />
                <span className="text-sm">
                  <span className="flex items-center gap-2">
                    <ScanFace className="h-4 w-4" />
                    Gunakan Face Recognition
                  </span>
                  <span className="block text-xs text-gray-500 mt-1">
                    Jika aktif, skema ini mengikuti mode verifikasi wajah dari Pengaturan Global. Selfie otomatis diwajibkan.
                  </span>
                </span>
              </label>

              {/* GPS Location Selection */}
              {formData.wajib_gps && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Lokasi GPS yang Diizinkan
                  </label>
                  <select
                    multiple
                    value={formData.lokasi_gps_ids || []}
                    onChange={(e) => {
                      const selectedIds = Array.from(e.target.selectedOptions, option => parseInt(option.value));
                      handleInputChange('lokasi_gps_ids', selectedIds.length > 0 ? selectedIds : null);
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    size="4"
                  >
                    {availableLocations.map((location) => (
                      <option key={location.id} value={location.id}>
                        {location.nama_lokasi} - {getLocationTypeLabel(location)} ({getLocationAreaSummary(location)})
                      </option>
                    ))}
                  </select>
                  <p className="text-xs text-gray-500 mt-1">
                    Pilih lokasi GPS yang diizinkan untuk absensi. Tiap lokasi mengikuti tipe area yang disetel di Manajemen Lokasi GPS. Kosongkan untuk semua lokasi aktif.
                  </p>
                  {availableLocations.length === 0 && (
                    <p className="text-xs text-orange-600 mt-1">
                      Belum ada lokasi GPS yang dikonfigurasi. Silakan tambahkan di menu Manajemen Lokasi GPS.
                    </p>
                  )}
                </div>
              )}
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Area Lokasi (Read-only)
                </label>
                <div className="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-sm text-gray-700 space-y-1">
                  {!formData.wajib_gps ? (
                    <p>GPS tidak diwajibkan pada skema ini.</p>
                  ) : selectedLocationDetails.length > 0 ? (
                    selectedLocationDetails.map((location) => (
                      <div key={location.id} className="flex items-center justify-between gap-3">
                        <span>{location.nama_lokasi}</span>
                        <span className="font-medium">
                          {getLocationTypeLabel(location)} - {getLocationAreaSummary(location)}
                        </span>
                      </div>
                    ))
                  ) : availableLocations.length > 0 ? (
                    <p>
                      Mengikuti tipe area masing-masing lokasi aktif (Circle atau Polygon) dari Manajemen Lokasi GPS.
                    </p>
                  ) : (
                    <p>Belum ada lokasi aktif untuk menampilkan area.</p>
                  )}
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  Perubahan tipe area dan radius/polygon dilakukan hanya di Manajemen Lokasi GPS.
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Akurasi GPS Minimum (meter)
                </label>
                <input
                  type="number"
                  min="1"
                  value={formData.gps_accuracy || 20}
                  onChange={(e) => handleInputChange('gps_accuracy', parseInt(e.target.value) || 20)}
                  placeholder="20"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Akurasi GPS minimum yang diterima untuk skema ini.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <Users className="h-5 w-5" />
            Target Tingkat dan Kelas
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Tingkat yang dicakup skema ini
              </label>
              <select
                multiple
                value={normalizeIdArray(formData.target_tingkat_ids).map((item) => String(item))}
                onChange={(e) => {
                  const selectedIds = Array.from(e.target.selectedOptions, (option) => Number(option.value));
                  handleInputChange('target_tingkat_ids', selectedIds);
                }}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                size="5"
              >
                {availableTingkat.map((tingkat) => (
                  <option key={tingkat.id} value={tingkat.id}>
                    {tingkat.nama} {tingkat.kode ? `(${tingkat.kode})` : ''}
                  </option>
                ))}
              </select>
              <p className="text-xs text-gray-500 mt-1">
                Kosongkan jika skema berlaku untuk semua tingkat.
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Kelas yang dicakup skema ini
              </label>
              <select
                multiple
                value={normalizeIdArray(formData.target_kelas_ids).map((item) => String(item))}
                onChange={(e) => {
                  const selectedIds = Array.from(e.target.selectedOptions, (option) => Number(option.value));
                  handleInputChange('target_kelas_ids', selectedIds);
                }}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                size="5"
                disabled={isLoadingKelas || normalizeIdArray(formData.target_tingkat_ids).length === 0}
              >
                {availableKelas.map((kelas) => (
                  <option key={kelas.id} value={kelas.id}>
                    {kelas.nama} - {kelas.tingkat}
                  </option>
                ))}
              </select>
              <p className="text-xs text-gray-500 mt-1">
                Kelas mengikuti tingkat yang dipilih. Pilih tingkat dulu untuk memuat kelas.
              </p>
              {isLoadingKelas && (
                <p className="text-xs text-blue-600 mt-1">Memuat daftar kelas...</p>
              )}
            </div>
          </div>
        </div>

        {/* Working Days */}
        <div className="bg-white p-6 rounded-xl border border-gray-200">
          <h3 className="text-lg font-semibold mb-4">Hari Kerja</h3>
          
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
            {workingDays.map((day) => (
              <label key={day} className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={selectedWorkingDays.includes(day)}
                  onChange={(e) => handleWorkingDayChange(day, e.target.checked)}
                  className="rounded"
                />
                <span className="text-sm">{day}</span>
              </label>
            ))}
          </div>
          {errors.hari_kerja && (
            <p className="text-sm text-red-500 mt-2">{errors.hari_kerja}</p>
          )}
        </div>

        {/* Submit Buttons */}
        <div className="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-end space-x-4">
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
          >
            {loading ? (
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            ) : (
              <Save className="h-4 w-4" />
            )}
            {loading ? 'Menyimpan...' : 'Simpan'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default AttendanceSchemaFormSimple;


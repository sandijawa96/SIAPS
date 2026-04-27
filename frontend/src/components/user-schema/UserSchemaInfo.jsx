import React, { useEffect, useMemo, useState } from 'react';
import { Calendar, Camera, Clock, Info, MapPin, RefreshCw, ScanFace, User } from 'lucide-react';
import api from '../../services/api';
import attendanceSchemaService from '../../services/attendanceSchemaService';
import { formatServerDate } from '../../services/serverClock';
import {
  getLocationAreaSummary,
  getLocationTypeLabel,
  resolveSchemaLocationDetails,
} from '../../utils/locationGeofence';

const parseWorkingDays = (rawDays) => {
  if (Array.isArray(rawDays)) return rawDays.filter(Boolean);

  if (typeof rawDays === 'string' && rawDays.trim()) {
    try {
      const parsed = JSON.parse(rawDays);
      if (Array.isArray(parsed)) return parsed.filter(Boolean);
      if (parsed && typeof parsed === 'object') return Object.values(parsed).filter(Boolean);
      return rawDays.split(',').map((item) => item.trim()).filter(Boolean);
    } catch (_) {
      return rawDays.split(',').map((item) => item.trim()).filter(Boolean);
    }
  }

  if (rawDays && typeof rawDays === 'object') {
    return Object.values(rawDays).filter(Boolean);
  }

  return [];
};

const formatDate = (value) => {
  if (!value) return '-';
  try {
    return formatServerDate(value, 'id-ID') || '-';
  } catch (_) {
    return value;
  }
};

const getAssignmentTypeMeta = (type) => {
  if (type === 'manual') return { label: 'Manual Assignment', color: 'bg-blue-100 text-blue-800' };
  if (type === 'bulk') return { label: 'Bulk Assignment', color: 'bg-purple-100 text-purple-800' };
  if (type === 'auto') return { label: 'Auto Assignment', color: 'bg-green-100 text-green-800' };
  if (type === 'default') return { label: 'Default Schema', color: 'bg-gray-100 text-gray-800' };
  return { label: 'Belum ditetapkan', color: 'bg-red-100 text-red-800' };
};

const UserSchemaInfo = ({ userId, userName }) => {
  const [schemaInfo, setSchemaInfo] = useState(null);
  const [assignmentType, setAssignmentType] = useState('none');
  const [assignmentReason, setAssignmentReason] = useState('');
  const [asnExcluded, setAsnExcluded] = useState(false);
  const [availableLocations, setAvailableLocations] = useState([]);
  const [faceTemplateInfo, setFaceTemplateInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (userId) {
      loadUserSchema();
    }
  }, [userId]);

  const loadUserSchema = async () => {
    try {
      setLoading(true);
      setError(null);
      setAsnExcluded(false);
      setFaceTemplateInfo(null);

      const [response, locations, faceTemplateResponse] = await Promise.all([
        api.get(`/attendance-schemas/user/${userId}/effective`),
        attendanceSchemaService.getGpsLocations(),
        api.get(`/face-templates/users/${userId}`).catch(() => null),
      ]);
      const payload = response.data;
      setAvailableLocations(locations);
      setFaceTemplateInfo(faceTemplateResponse?.data?.success ? faceTemplateResponse.data.data : null);

      if (!payload?.success) {
        setSchemaInfo(null);
        setAssignmentType('none');
        setAssignmentReason('');
        setError('Gagal memuat informasi skema siswa');
        return;
      }

      const effectiveType = payload.assignment_type || payload.data?.assignment_type || 'none';
      const effectiveReason = payload.assignment_reason || payload.data?.assignment_reason || '';

      if (effectiveType === 'asn_excluded') {
        setSchemaInfo(null);
        setAssignmentType(effectiveType);
        setAssignmentReason(effectiveReason);
        setAsnExcluded(true);
        return;
      }

      setSchemaInfo(payload.data || null);
      setAssignmentType(effectiveType);
      setAssignmentReason(effectiveReason);
    } catch (err) {
      console.error('Error loading user schema:', err);
      setAvailableLocations([]);
      setFaceTemplateInfo(null);
      setSchemaInfo(null);
      setAssignmentType('none');
      setAssignmentReason('');
      setError('Terjadi kesalahan saat memuat data');
    } finally {
      setLoading(false);
    }
  };

  const workingDays = useMemo(() => parseWorkingDays(schemaInfo?.hari_kerja), [schemaInfo]);
  const assignmentMeta = getAssignmentTypeMeta(assignmentType);
  const locationResolution = useMemo(
    () => resolveSchemaLocationDetails(schemaInfo, availableLocations),
    [schemaInfo, availableLocations]
  );
  const visibleLocations = useMemo(
    () => locationResolution.locations.slice(0, 3),
    [locationResolution]
  );
  const hiddenLocationCount = Math.max(0, locationResolution.locations.length - visibleLocations.length);
  const effectiveWorkingHours = {
    jamMasuk: schemaInfo?.siswa_jam_masuk || schemaInfo?.jam_masuk_default || '-',
    jamPulang: schemaInfo?.siswa_jam_pulang || schemaInfo?.jam_pulang_default || '-',
    toleransi: schemaInfo?.siswa_toleransi ?? schemaInfo?.toleransi_default ?? 0,
    bukaSebelum: schemaInfo?.minimal_open_time_siswa ?? schemaInfo?.minimal_open_time_staff ?? 0,
  };

  if (loading) {
    return (
      <div className="bg-white border border-gray-200 p-6 rounded-xl">
        <div className="animate-pulse space-y-3">
          <div className="h-5 bg-gray-200 rounded w-1/3" />
          <div className="h-4 bg-gray-200 rounded w-full" />
          <div className="h-4 bg-gray-200 rounded w-5/6" />
          <div className="h-4 bg-gray-200 rounded w-4/6" />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white border border-gray-200 p-6 rounded-xl text-center">
        <Info className="h-8 w-8 mx-auto mb-2 text-red-500" />
        <p className="text-sm text-red-600">{error}</p>
        <button
          onClick={loadUserSchema}
          className="mt-3 px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700"
        >
          Coba Lagi
        </button>
      </div>
    );
  }

  if (asnExcluded) {
    return (
      <div className="bg-white border border-gray-200 p-6 rounded-xl text-center">
        <Info className="h-8 w-8 mx-auto mb-2 text-amber-500" />
        <p className="font-semibold text-amber-700">User ASN Dikecualikan</p>
        <p className="text-sm text-gray-600 mt-1">User ASN menggunakan sistem absensi eksternal pemerintah.</p>
      </div>
    );
  }

  if (!schemaInfo) {
    return (
      <div className="bg-white border border-gray-200 p-6 rounded-xl text-center text-gray-500">
        <Info className="h-8 w-8 mx-auto mb-2" />
        <p>Tidak ada skema aktif untuk siswa ini.</p>
      </div>
    );
  }

  return (
    <div className="bg-white border border-gray-200 p-6 rounded-xl space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-2">
          <User className="h-5 w-5 text-blue-600" />
          <h3 className="text-lg font-semibold text-gray-900">Detail Skema: {userName}</h3>
        </div>
        <button
          onClick={loadUserSchema}
          className="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50"
        >
          <RefreshCw className="h-3.5 w-3.5" />
          Refresh
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div className="rounded-lg border border-gray-200 p-3">
          <p className="text-xs text-gray-500">Nama Skema</p>
          <p className="font-semibold text-gray-900 mt-1">{schemaInfo.schema_name || '-'}</p>
        </div>
        <div className="rounded-lg border border-gray-200 p-3">
          <p className="text-xs text-gray-500">Tipe Skema</p>
          <p className="font-semibold text-gray-900 mt-1">{schemaInfo.schema_type || '-'}</p>
        </div>
      </div>

      <div>
        <p className="text-xs text-gray-500 mb-1">Jenis Assignment</p>
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${assignmentMeta.color}`}>
          {assignmentMeta.label}
        </span>
        {assignmentReason && <p className="text-xs text-gray-500 mt-1">{assignmentReason}</p>}
      </div>

      <div className="border-t pt-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Clock className="h-4 w-4" />
          Jam Absensi
        </h4>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          <div>
            <p className="text-gray-500">Jam Masuk</p>
            <p className="font-medium text-gray-900">{effectiveWorkingHours.jamMasuk}</p>
          </div>
          <div>
            <p className="text-gray-500">Jam Pulang</p>
            <p className="font-medium text-gray-900">{effectiveWorkingHours.jamPulang}</p>
          </div>
          <div>
            <p className="text-gray-500">Toleransi</p>
            <p className="font-medium text-gray-900">{effectiveWorkingHours.toleransi} menit</p>
          </div>
          <div>
            <p className="text-gray-500">Buka Sebelum</p>
            <p className="font-medium text-gray-900">{effectiveWorkingHours.bukaSebelum} menit</p>
          </div>
        </div>
      </div>

      <div className="border-t pt-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Persyaratan</h4>
        <div className="flex flex-wrap gap-2">
          <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${schemaInfo.wajib_gps ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'}`}>
            <MapPin className="h-3 w-3 mr-1" />
            GPS {schemaInfo.wajib_gps ? 'Wajib' : 'Opsional'}
          </span>
          <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${schemaInfo.wajib_foto ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'}`}>
            <Camera className="h-3 w-3 mr-1" />
            Selfie {schemaInfo.wajib_foto ? 'Wajib' : 'Opsional'}
          </span>
          <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${schemaInfo.face_verification_enabled ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700'}`}>
            <ScanFace className="h-3 w-3 mr-1" />
            Face Recognition {schemaInfo.face_verification_enabled ? 'Aktif' : 'Tidak Dipakai'}
          </span>
        </div>
        <p className="mt-2 text-xs text-gray-500">
          Face recognition memakai mode dan kebijakan fallback dari Pengaturan Global; skema hanya menentukan dipakai/tidak untuk target ini.
        </p>
        {(schemaInfo.face_verification_enabled || faceTemplateInfo) && (
          <div className="mt-3 rounded-lg border border-indigo-100 bg-indigo-50 p-3">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
              <div>
                <p className="text-xs text-indigo-700 font-semibold">Template Wajah Siswa</p>
                <p className="text-sm font-medium text-gray-900 mt-1">
                  {faceTemplateInfo
                    ? (faceTemplateInfo.has_active_template ? 'Template aktif tersedia' : 'Belum ada template aktif')
                    : 'Status template tidak dimuat di halaman ini'}
                </p>
              </div>
              {faceTemplateInfo?.submission_state && (
                <div className="text-xs text-gray-600 md:text-right">
                  <p>Self submit: {faceTemplateInfo.submission_state.self_submit_count}/{faceTemplateInfo.submission_state.limit}</p>
                  <p>Unlock tersisa: {faceTemplateInfo.submission_state.unlock_allowance_remaining}</p>
                </div>
              )}
            </div>
            {faceTemplateInfo?.active_template && (
              <p className="mt-2 text-xs text-gray-600">
                Versi {faceTemplateInfo.active_template.template_version || '-'} | kualitas {faceTemplateInfo.active_template.quality_score ?? '-'}
              </p>
            )}
          </div>
        )}
        {schemaInfo.wajib_gps && (
          <div className="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p className="text-xs text-gray-500">Area Lokasi</p>
            <p className="text-sm font-medium text-gray-900 mt-1">{locationResolution.summary}</p>
            {visibleLocations.length > 0 && (
              <div className="mt-2 space-y-1.5">
                {visibleLocations.map((location) => (
                  <div key={location.id} className="flex items-center justify-between gap-3 text-xs">
                    <span className="text-gray-700">{location.nama_lokasi}</span>
                    <span className="font-medium text-gray-900">
                      {getLocationTypeLabel(location)} - {getLocationAreaSummary(location)}
                    </span>
                  </div>
                ))}
                {hiddenLocationCount > 0 && (
                  <p className="text-xs text-gray-500">+{hiddenLocationCount} lokasi lainnya</p>
                )}
                {locationResolution.missingCount > 0 && (
                  <p className="text-xs text-amber-600">
                    {locationResolution.missingCount} referensi lokasi tidak ditemukan di katalog lokasi GPS.
                  </p>
                )}
              </div>
            )}
          </div>
        )}
      </div>

      <div className="border-t pt-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Calendar className="h-4 w-4" />
          Hari Kerja
        </h4>
        {workingDays.length > 0 ? (
          <div className="flex flex-wrap gap-1.5">
            {workingDays.map((day, index) => (
              <span key={`${day}-${index}`} className="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                {day}
              </span>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500">Tidak ada hari kerja yang terdefinisi.</p>
        )}
      </div>

      {(schemaInfo.assignment_id || schemaInfo.start_date || schemaInfo.end_date) && (
        <div className="border-t pt-4">
          <h4 className="text-sm font-semibold text-gray-900 mb-3">Periode Assignment</h4>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <p className="text-gray-500">Mulai</p>
              <p className="font-medium text-gray-900">{formatDate(schemaInfo.start_date)}</p>
            </div>
            <div>
              <p className="text-gray-500">Berakhir</p>
              <p className="font-medium text-gray-900">{formatDate(schemaInfo.end_date)}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default UserSchemaInfo;

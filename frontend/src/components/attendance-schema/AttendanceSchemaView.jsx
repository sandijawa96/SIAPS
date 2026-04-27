import React, { useState, useEffect, useMemo } from 'react';
import { 
  Card, 
  CardHeader, 
  CardTitle, 
  CardContent 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  ArrowLeft, 
  Edit, 
  Clock, 
  Users, 
  MapPin, 
  Camera,
  Star,
  Calendar,
  History,
  Settings
} from 'lucide-react';
import attendanceSchemaService from '@/services/attendanceSchemaService';
import { formatServerDateTime } from '@/services/serverClock';
import {
  getLocationAreaSummary,
  getLocationTypeLabel,
  resolveSchemaLocationDetails,
} from '@/utils/locationGeofence';

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
      // ignore invalid json legacy payload
    }
  }

  return [];
};

const AttendanceSchemaView = ({ schema, onEdit, onCancel }) => {
  const [changeLogs, setChangeLogs] = useState([]);
  const [loadingLogs, setLoadingLogs] = useState(false);
  const [availableLocations, setAvailableLocations] = useState([]);
  const [loadingLocations, setLoadingLocations] = useState(false);

  useEffect(() => {
    if (schema) {
      fetchLocations();
    }

    if (schema?.id) {
      fetchChangeLogs();
    }
  }, [schema?.id]);

  const fetchChangeLogs = async () => {
    try {
      setLoadingLogs(true);
      const response = await attendanceSchemaService.getChangeLogs(schema.id);
      const rows = Array.isArray(response?.data?.data?.data)
        ? response.data.data.data
        : Array.isArray(response?.data?.data)
          ? response.data.data
        : Array.isArray(response?.data)
          ? response.data
          : [];
      setChangeLogs(rows);
    } catch (error) {
      console.error('Error fetching change logs:', error);
      // Don't show error toast for logs as it's not critical
    } finally {
      setLoadingLogs(false);
    }
  };

  const fetchLocations = async () => {
    try {
      setLoadingLocations(true);
      const locations = await attendanceSchemaService.getGpsLocations();
      setAvailableLocations(locations);
    } catch (error) {
      console.error('Error fetching GPS locations:', error);
      setAvailableLocations([]);
    } finally {
      setLoadingLocations(false);
    }
  };

  const getSchemaTypeColor = (type) => {
    const colors = {
      global: 'bg-gray-100 text-gray-800',
      siswa: 'bg-blue-100 text-blue-800',
      honorer: 'bg-yellow-100 text-yellow-800',
      asn: 'bg-green-100 text-green-800',
      guru_honorer: 'bg-purple-100 text-purple-800',
      staff_asn: 'bg-indigo-100 text-indigo-800'
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
  };

  const formatTime = (time) => {
    if (!time) return '-';
    return time.substring(0, 5); // HH:MM format
  };

  const formatDateTime = (dateTime) => {
    if (!dateTime) return '-';
    return formatServerDateTime(dateTime, 'id-ID') || '-';
  };

  const effectiveSiswaJamMasuk = schema?.siswa_jam_masuk || schema?.jam_masuk_default;
  const effectiveSiswaJamPulang = schema?.siswa_jam_pulang || schema?.jam_pulang_default;
  const effectiveSiswaToleransi =
    schema?.siswa_toleransi !== undefined && schema?.siswa_toleransi !== null
      ? schema.siswa_toleransi
      : schema?.toleransi_default;
  const effectiveSiswaOpenTime =
    schema?.minimal_open_time_siswa !== undefined && schema?.minimal_open_time_siswa !== null
      ? schema.minimal_open_time_siswa
      : schema?.minimal_open_time_staff;
  const workingDays = normalizeWorkingDays(schema?.hari_kerja);
  const locationResolution = useMemo(
    () => resolveSchemaLocationDetails(schema, availableLocations),
    [schema, availableLocations]
  );
  const visibleLocations = locationResolution.locations.slice(0, 4);
  const hiddenLocationCount = Math.max(0, locationResolution.locations.length - visibleLocations.length);

  if (!schema) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">Schema tidak ditemukan</p>
        <Button onClick={onCancel} className="mt-4">
          Kembali
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" onClick={onCancel} className="p-2 border border-gray-200">
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              {schema.schema_name}
              {schema.is_default && (
                <Star className="h-5 w-5 text-yellow-500 fill-current" />
              )}
            </h2>
            <div className="flex items-center gap-2 mt-1">
              <Badge className={getSchemaTypeColor(schema.schema_type)}>
                {schema.schema_type}
              </Badge>
              <Badge variant={schema.is_active ? 'success' : 'secondary'}>
                {schema.is_active ? 'Aktif' : 'Nonaktif'}
              </Badge>
              {schema.is_mandatory && (
                <Badge variant="destructive">Wajib</Badge>
              )}
            </div>
          </div>
        </div>
        <Button onClick={() => onEdit?.()} className="flex items-center gap-2">
          <Edit className="h-4 w-4" />
          Edit
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Settings className="h-5 w-5" />
              Informasi Dasar
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-sm font-medium text-gray-500">Tipe Skema</label>
                <p className="text-sm text-gray-900">{schema.schema_type}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Priority</label>
                <p className="text-sm text-gray-900">{schema.priority}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Target Role</label>
                <p className="text-sm text-gray-900">{schema.target_role || 'Semua Role'}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Target Status</label>
                <p className="text-sm text-gray-900">{schema.target_status || 'Semua Status'}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Version</label>
                <p className="text-sm text-gray-900">v{schema.version}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Mode Verifikasi</label>
                <p className="text-sm text-gray-900">
                  {schema.verification_mode === 'sync_final' ? 'Sync Final' : 'Async Pending'}
                </p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Dibuat</label>
                <p className="text-sm text-gray-900">{formatDateTime(schema.created_at)}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Scope Absensi</label>
                <p className="text-sm text-gray-900">
                  Siswa Saja (Terkunci)
                </p>
              </div>
            </div>
            
            {schema.schema_description && (
              <div>
                <label className="text-sm font-medium text-gray-500">Deskripsi</label>
                <p className="text-sm text-gray-900">{schema.schema_description}</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Target Information */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Users className="h-5 w-5" />
              Target Pengguna
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Role Target</span>
                <Badge variant="outline">
                  {schema.target_role || 'Semua Role'}
                </Badge>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Status Target</span>
                <Badge variant="outline">
                  {schema.target_status || 'Tidak Dipakai (Siswa Saja)'}
                </Badge>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Wajib Absen</span>
                <Badge variant={schema.is_mandatory ? 'destructive' : 'secondary'}>
                  {schema.is_mandatory ? 'Ya' : 'Tidak'}
                </Badge>
              </div>
              <div className="flex items-start justify-between gap-4">
                <span className="text-sm text-gray-600">Target Tingkat</span>
                <span className="text-sm text-gray-900 text-right">
                  {Array.isArray(schema.target_tingkat_ids) && schema.target_tingkat_ids.length > 0
                    ? `${schema.target_tingkat_ids.length} tingkat dipilih`
                    : 'Semua tingkat'}
                </span>
              </div>
              <div className="flex items-start justify-between gap-4">
                <span className="text-sm text-gray-600">Target Kelas</span>
                <span className="text-sm text-gray-900 text-right">
                  {Array.isArray(schema.target_kelas_ids) && schema.target_kelas_ids.length > 0
                    ? `${schema.target_kelas_ids.length} kelas dipilih`
                    : 'Semua kelas'}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Active Student Working Hours */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Clock className="h-5 w-5" />
              Jam Absensi Siswa (Aktif Runtime)
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-sm font-medium text-gray-500">Jam Masuk</label>
                <p className="text-lg font-semibold text-gray-900">
                  {formatTime(effectiveSiswaJamMasuk)}
                </p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Jam Pulang</label>
                <p className="text-lg font-semibold text-gray-900">
                  {formatTime(effectiveSiswaJamPulang)}
                </p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Toleransi</label>
                <p className="text-sm text-gray-900">{effectiveSiswaToleransi} menit</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-500">Buka Sebelum</label>
                <p className="text-sm text-gray-900">{effectiveSiswaOpenTime} menit</p>
              </div>
            </div>
            <p className="text-xs text-gray-500">
              Nilai ini menjadi acuan jam absensi siswa yang dipakai runtime.
            </p>
          </CardContent>
        </Card>

        {/* Requirements */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <MapPin className="h-5 w-5" />
              Persyaratan Absensi
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <MapPin className="h-4 w-4 text-green-600" />
                  <span className="text-sm text-gray-600">Wajib GPS</span>
                </div>
                <Badge variant={schema.wajib_gps ? 'success' : 'secondary'}>
                  {schema.wajib_gps ? 'Ya' : 'Tidak'}
                </Badge>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Camera className="h-4 w-4 text-blue-600" />
                  <span className="text-sm text-gray-600">Wajib Foto</span>
                </div>
                <Badge variant={schema.wajib_foto ? 'success' : 'secondary'}>
                  {schema.wajib_foto ? 'Ya' : 'Tidak'}
                </Badge>
              </div>
              {schema.wajib_gps && (
                <div className="pt-3 border-t border-gray-100 space-y-2">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-2">
                      <MapPin className="h-4 w-4 text-emerald-600" />
                      <span className="text-sm text-gray-600">Area Lokasi</span>
                    </div>
                    <span className="text-sm text-gray-900 text-right">
                      {loadingLocations ? 'Memuat lokasi...' : locationResolution.summary}
                    </span>
                  </div>
                  {!loadingLocations && visibleLocations.length > 0 && (
                    <div className="space-y-2 pl-6">
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
          </CardContent>
        </Card>

        {/* Working Days */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5" />
              Hari Kerja
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {workingDays.map((day) => (
                <Badge key={day} variant="outline">
                  {day}
                </Badge>
              ))}
            </div>
            {workingDays.length === 0 && (
              <p className="text-sm text-gray-500">Tidak ada hari kerja yang ditentukan</p>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Change Logs */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <History className="h-5 w-5" />
            Riwayat Perubahan
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loadingLogs ? (
            <div className="flex justify-center py-4">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            </div>
          ) : changeLogs.length > 0 ? (
            <div className="space-y-3">
              {changeLogs.slice(0, 5).map((log, index) => (
                <div key={index} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <Badge variant="outline" className="text-xs">
                        {log.action}
                      </Badge>
                      <span className="text-xs text-gray-500">
                        {formatDateTime(log.changed_at)}
                      </span>
                    </div>
                    {log.reason && (
                      <p className="text-sm text-gray-600">{log.reason}</p>
                    )}
                  </div>
                </div>
              ))}
              {changeLogs.length > 5 && (
                <p className="text-sm text-gray-500 text-center">
                  Dan {changeLogs.length - 5} perubahan lainnya...
                </p>
              )}
            </div>
          ) : (
            <p className="text-sm text-gray-500 text-center py-4">
              Belum ada riwayat perubahan
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default AttendanceSchemaView;

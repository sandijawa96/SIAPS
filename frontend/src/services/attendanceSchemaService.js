import api from './api';
import { getServerDateString } from './serverClock';
import { normalizeLocationRows } from '../utils/locationGeofence';

const normalizeArrayValue = (value) => {
  if (Array.isArray(value)) {
    return value;
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) {
        return parsed;
      }
      if (typeof parsed === 'string') {
        return normalizeArrayValue(parsed);
      }
    } catch (_) {
      return [];
    }
  }

  return [];
};

const normalizeWorkingDays = (value) =>
  normalizeArrayValue(value)
    .map((item) => String(item).trim())
    .filter((item) => item.length > 0);

const normalizeIdArray = (value) =>
  normalizeArrayValue(value)
    .map((item) => Number(item))
    .filter((item) => Number.isInteger(item) && item > 0);

class AttendanceSchemaService {
  // Get all attendance schemas
  async getAllSchemas() {
    try {
      const response = await api.get('/attendance-schemas');
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance schemas:', error);
      throw error;
    }
  }

  // Get specific schema by ID
  async getSchema(id) {
    try {
      const response = await api.get(`/attendance-schemas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance schema:', error);
      throw error;
    }
  }

  // Get effective schema for a user
  async getEffectiveSchema(userId) {
    try {
      const response = await api.get(`/attendance-schemas/user/${userId}/effective`);
      return response.data;
    } catch (error) {
      console.error('Error fetching effective schema:', error);
      throw error;
    }
  }

  // Create new schema (admin only)
  async createSchema(schemaData) {
    try {
      const response = await api.post('/attendance-schemas', schemaData);
      return response.data;
    } catch (error) {
      console.error('Error creating attendance schema:', error);
      throw error;
    }
  }

  // Update schema (admin only)
  async updateSchema(id, schemaData) {
    try {
      const response = await api.put(`/attendance-schemas/${id}`, schemaData);
      return response.data;
    } catch (error) {
      console.error('Error updating attendance schema:', error);
      throw error;
    }
  }

  // Delete schema (admin only)
  async deleteSchema(id) {
    try {
      const response = await api.delete(`/attendance-schemas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error deleting attendance schema:', error);
      throw error;
    }
  }

  // Toggle schema active status (admin only)
  async toggleActive(id) {
    try {
      const response = await api.patch(`/attendance-schemas/${id}/toggle-active`);
      return response.data;
    } catch (error) {
      console.error('Error toggling schema status:', error);
      throw error;
    }
  }

  // Set schema as default (admin only)
  async setDefault(id) {
    try {
      const response = await api.patch(`/attendance-schemas/${id}/set-default`);
      return response.data;
    } catch (error) {
      console.error('Error setting default schema:', error);
      throw error;
    }
  }

  // Get schema change logs (admin only)
  async getChangeLogs(id) {
    try {
      const response = await api.get(`/attendance-schemas/${id}/change-logs`);
      return response.data;
    } catch (error) {
      console.error('Error fetching change logs:', error);
      throw error;
    }
  }

  // Get all schemas (simplified method)
  async getSchemas() {
    try {
      const response = await api.get('/attendance-schemas');
      return response.data.data || response.data || [];
    } catch (error) {
      console.error('Error fetching schemas:', error);
      return [];
    }
  }

  // Get all GPS locations for schema location summaries
  async getGpsLocations() {
    try {
      const response = await api.get('/lokasi-gps');
      return normalizeLocationRows(response.data.data || response.data || []);
    } catch (error) {
      console.error('Error fetching GPS locations:', error);
      return [];
    }
  }

  // Assign schema to user (admin only)
  async assignToUser(schemaId, assignmentData) {
    try {
      const response = await api.post(`/attendance-schemas/${schemaId}/assign-user`, assignmentData);
      return response.data;
    } catch (error) {
      console.error('Error assigning schema to user:', error);
      throw error;
    }
  }

  // Assign schema to specific user by ID (simplified method)
  async assignSchemaToUser(schemaId, userId, startDate = null, endDate = null) {
    try {
      const response = await api.post(`/attendance-schemas/${schemaId}/assign-user`, {
        user_id: userId,
        start_date: startDate || getServerDateString(),
        end_date: endDate,
        assignment_type: 'manual'
      });
      return response.data;
    } catch (error) {
      console.error('Error assigning schema to user:', error);
      throw error;
    }
  }

  // Bulk assign schema (admin only)
  async bulkAssign(schemaId, assignmentData) {
    try {
      const response = await api.post(`/attendance-schemas/${schemaId}/bulk-assign`, assignmentData);
      return response.data;
    } catch (error) {
      console.error('Error bulk assigning schema:', error);
      throw error;
    }
  }

  // Bulk assign schema to multiple users (simplified method)
  async bulkAssignSchema(schemaId, userIds, startDate = null, endDate = null) {
    try {
      const response = await api.post(`/attendance-schemas/${schemaId}/bulk-assign`, {
        user_ids: userIds,
        start_date: startDate || getServerDateString(),
        end_date: endDate,
        assignment_type: 'manual'
      });
      return response.data;
    } catch (error) {
      console.error('Error bulk assigning schema:', error);
      throw error;
    }
  }

  // Auto assign schemas (admin only)
  async autoAssign(userIds = null, schemaId = null) {
    try {
      const response = await api.post('/attendance-schemas/auto-assign', {
        user_ids: userIds,
        schema_id: schemaId,
      });
      return response.data;
    } catch (error) {
      console.error('Error auto assigning schemas:', error);
      throw error;
    }
  }

  // Get schema types for dropdown (Based on attendance characteristics, not user roles)
  getSchemaTypes() {
    return [
      { value: 'hari_kerja_5', label: 'Skema 5 Hari Kerja (Senin-Jumat)' },
      { value: 'hari_kerja_6', label: 'Skema 6 Hari Kerja (Senin-Sabtu)' },
      { value: 'waktu_sekolah', label: 'Skema Waktu Sekolah (07:00-14:00)' },
      { value: 'waktu_kantor', label: 'Skema Waktu Kantor (08:00-16:00)' },
      { value: 'shift_pagi', label: 'Skema Shift Pagi (06:00-14:00)' },
      { value: 'shift_siang', label: 'Skema Shift Siang (14:00-22:00)' },
      { value: 'fleksibel', label: 'Skema Fleksibel (Toleransi Tinggi)' },
      { value: 'ketat', label: 'Skema Ketat (Toleransi Rendah)' },
      { value: 'wajib_gps', label: 'Skema Wajib GPS' },
      { value: 'tanpa_gps', label: 'Skema Tanpa GPS' },
      { value: 'wajib_foto', label: 'Skema Wajib Foto Selfie' },
      { value: 'tanpa_foto', label: 'Skema Tanpa Foto' },
      { value: 'hybrid', label: 'Skema Hybrid (WFH + WFO)' },
      { value: 'remote', label: 'Skema Remote/WFH' },
      { value: 'custom', label: 'Skema Custom' }
    ];
  }

  // Get target roles for dropdown (from API)
  async getTargetRoles() {
    try {
      const response = await api.get('/roles');
      const roles = response.data.data || [];
      
      // Format roles for dropdown
      const formattedRoles = [
        { value: null, label: 'Semua Role' },
        ...roles.map(role => ({
          value: role.name,
          label: role.display_name || role.name
        }))
      ];
      
      return formattedRoles;
    } catch (error) {
      console.error('Error fetching roles:', error);
      // Fallback to static roles if API fails
      return [
        { value: null, label: 'Semua Role' },
        { value: 'Siswa', label: 'Siswa' },
        { value: 'Guru', label: 'Guru' },
        { value: 'Staff', label: 'Staff' },
        { value: 'Admin', label: 'Admin' }
      ];
    }
  }

  // Get target roles for dropdown (synchronous version for backward compatibility)
  getTargetRolesSync() {
    return [
      { value: null, label: 'Semua Role' },
      { value: 'Siswa', label: 'Siswa' },
      { value: 'Guru', label: 'Guru' },
      { value: 'Staff', label: 'Staff' },
      { value: 'Admin', label: 'Admin' }
    ];
  }

  // Get target status for dropdown
  getTargetStatus() {
    return [
      { value: null, label: 'Semua Status' },
      { value: 'Honorer', label: 'Honorer' },
      { value: 'ASN', label: 'ASN' }
    ];
  }

  // Get working days for dropdown
  getWorkingDays() {
    return [
      'Senin',
      'Selasa',
      'Rabu',
      'Kamis',
      'Jumat',
      'Sabtu',
      'Minggu'
    ];
  }

  // Format schema for display
  formatSchemaForDisplay(schema) {
    const effectiveJamMasuk = schema.siswa_jam_masuk || schema.jam_masuk_default;
    const effectiveJamPulang = schema.siswa_jam_pulang || schema.jam_pulang_default;
    const effectiveToleransi =
      schema.siswa_toleransi !== undefined && schema.siswa_toleransi !== null
        ? schema.siswa_toleransi
        : schema.toleransi_default;
    const effectiveOpenTime =
      schema.minimal_open_time_siswa !== undefined && schema.minimal_open_time_siswa !== null
        ? schema.minimal_open_time_siswa
        : schema.minimal_open_time_staff;

    return {
      ...schema,
      working_hours: {
        jam_masuk: effectiveJamMasuk,
        jam_pulang: effectiveJamPulang,
        toleransi: effectiveToleransi,
        minimal_open_time: effectiveOpenTime
      },
      student_hours: {
        jam_masuk: effectiveJamMasuk,
        jam_pulang: effectiveJamPulang,
        toleransi: effectiveToleransi,
        minimal_open_time: effectiveOpenTime
      },
      verification: {
        mode: schema.verification_mode || 'async_pending',
        scope: 'siswa_only',
        target_tingkat_ids: normalizeIdArray(schema.target_tingkat_ids),
        target_kelas_ids: normalizeIdArray(schema.target_kelas_ids)
      },
      requirements: {
        wajib_gps: schema.wajib_gps,
        wajib_foto: schema.wajib_foto,
        face_verification_enabled: Boolean(schema.face_verification_enabled ?? false)
      },
      working_days: normalizeWorkingDays(schema.hari_kerja),
      location_ids: normalizeIdArray(schema.lokasi_gps_ids)
    };
  }

  // Format schema for API submission
  formatSchemaForAPI(formData) {
    const effectiveJamMasuk =
      formData.student_hours?.jam_masuk ||
      formData.siswa_jam_masuk ||
      formData.working_hours?.jam_masuk ||
      formData.jam_masuk_default;

    const effectiveJamPulang =
      formData.student_hours?.jam_pulang ||
      formData.siswa_jam_pulang ||
      formData.working_hours?.jam_pulang ||
      formData.jam_pulang_default;

    const effectiveToleransi =
      formData.student_hours?.toleransi ??
      formData.siswa_toleransi ??
      formData.working_hours?.toleransi ??
      formData.toleransi_default;

    const effectiveOpenTime =
      formData.student_hours?.minimal_open_time ??
      formData.minimal_open_time_siswa ??
      formData.working_hours?.minimal_open_time ??
      formData.minimal_open_time_staff;

    return {
      schema_name: formData.schema_name,
      schema_type: formData.schema_type,
      target_role: formData.target_role,
      target_status: formData.target_status,
      schema_description: formData.schema_description,
      is_active: formData.is_active,
      is_default: formData.is_default,
      is_mandatory: formData.is_mandatory,
      priority: formData.priority,
      // Mirror ke field default untuk kompatibilitas backend legacy.
      jam_masuk_default: effectiveJamMasuk,
      jam_pulang_default: effectiveJamPulang,
      toleransi_default: effectiveToleransi,
      minimal_open_time_staff: effectiveOpenTime,
      wajib_gps: formData.requirements?.wajib_gps ?? formData.wajib_gps,
      wajib_foto: formData.requirements?.wajib_foto ?? formData.wajib_foto,
      face_verification_enabled:
        formData.requirements?.face_verification_enabled ??
        formData.face_verification_enabled ??
        false,
      hari_kerja: normalizeWorkingDays(formData.working_days || formData.hari_kerja),
      lokasi_gps_ids: normalizeIdArray(formData.location_ids || formData.lokasi_gps_ids),
      siswa_jam_masuk: effectiveJamMasuk,
      siswa_jam_pulang: effectiveJamPulang,
      siswa_toleransi: effectiveToleransi,
      minimal_open_time_siswa: effectiveOpenTime,
      gps_accuracy: formData.gps_accuracy || 20,
      attendance_scope: 'siswa_only',
      target_tingkat_ids: normalizeIdArray(formData.verification?.target_tingkat_ids || formData.target_tingkat_ids),
      target_kelas_ids: normalizeIdArray(formData.verification?.target_kelas_ids || formData.target_kelas_ids)
    };
  }
}

export default new AttendanceSchemaService();

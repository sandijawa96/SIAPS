import api from './api';
import { getServerNowDate, toServerDateInput } from './serverClock';

const toTimeMinutes = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return null;
  }

  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (
    Number.isNaN(hour) ||
    Number.isNaN(minute) ||
    hour < 0 ||
    hour > 23 ||
    minute < 0 ||
    minute > 59
  ) {
    return null;
  }

  return (hour * 60) + minute;
};

class ManualAttendanceService {
  constructor() {
    this.baseURL = '/manual-attendance';
  }

  // Get users that can be managed by current user
  async getUsers() {
    try {
      const response = await api.get(`${this.baseURL}/users`);
      return response.data;
    } catch (error) {
      console.error('Error fetching manageable users:', error);
      throw this.handleError(error);
    }
  }

  // Create manual attendance
  async create(data) {
    try {
      const payload = {
        user_id: data.user_id,
        tanggal: data.tanggal,
        jam_masuk: data.jam_masuk || null,
        jam_pulang: data.jam_pulang || null,
        status: data.status,
        keterangan: data.keterangan || null,
        reason: data.reason,
        latitude_masuk: data.latitude_masuk || null,
        longitude_masuk: data.longitude_masuk || null,
        latitude_pulang: data.latitude_pulang || null,
        longitude_pulang: data.longitude_pulang || null
      };

      const response = await api.post(`${this.baseURL}/create`, payload);
      return response.data;
    } catch (error) {
      console.error('Error creating manual attendance:', error);
      throw this.handleError(error);
    }
  }

  // Update manual attendance
  async update(id, data) {
    try {
      const payload = {
        jam_masuk: data.jam_masuk || null,
        jam_pulang: data.jam_pulang || null,
        status: data.status,
        keterangan: data.keterangan || null,
        reason: data.reason,
        latitude_masuk: data.latitude_masuk || null,
        longitude_masuk: data.longitude_masuk || null,
        latitude_pulang: data.latitude_pulang || null,
        longitude_pulang: data.longitude_pulang || null
      };

      const response = await api.put(`${this.baseURL}/${id}`, payload);
      return response.data;
    } catch (error) {
      console.error('Error updating manual attendance:', error);
      throw this.handleError(error);
    }
  }

  // Delete manual attendance
  async delete(id, reason = 'Manual attendance deleted from web') {
    try {
      const response = await api.delete(`${this.baseURL}/${id}`, {
        data: { reason }
      });
      return response.data;
    } catch (error) {
      console.error('Error deleting manual attendance:', error);
      throw this.handleError(error);
    }
  }

  // Get attendance history with filters
  async getHistory(filters = {}) {
    try {
      const params = new URLSearchParams();
      
      if (filters.bucket) params.append('bucket', filters.bucket);
      if (filters.user_id) params.append('user_id', filters.user_id);
      if (filters.date) params.append('date', filters.date);
      if (filters.start_date) params.append('start_date', filters.start_date);
      if (filters.end_date) params.append('end_date', filters.end_date);
      if (filters.status) params.append('status', filters.status);
      if (filters.search) params.append('search', filters.search);
      if (filters.per_page) params.append('per_page', filters.per_page);
      if (filters.page) params.append('page', filters.page);

      const queryString = params.toString();
      const url = queryString ? `${this.baseURL}/history?${queryString}` : `${this.baseURL}/history`;
      
      const response = await api.get(url);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance history:', error);
      throw this.handleError(error);
    }
  }

  // Get attendance statistics
  async getStatistics(filters = {}) {
    try {
      const params = new URLSearchParams();
      
      if (filters.bucket) params.append('bucket', filters.bucket);
      if (filters.date && !filters.start_date && !filters.end_date) {
        params.append('start_date', filters.date);
        params.append('end_date', filters.date);
      }
      if (filters.start_date) params.append('start_date', filters.start_date);
      if (filters.end_date) params.append('end_date', filters.end_date);
      if (filters.user_id) params.append('user_id', filters.user_id);

      const queryString = params.toString();
      const url = queryString ? `${this.baseURL}/statistics?${queryString}` : `${this.baseURL}/statistics`;
      
      const response = await api.get(url);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance statistics:', error);
      throw this.handleError(error);
    }
  }

  // Get audit logs for specific attendance
  async getAuditLogs(attendanceId) {
    try {
      const response = await api.get(`${this.baseURL}/${attendanceId}/audit-logs`);
      return response.data;
    } catch (error) {
      console.error('Error fetching audit logs:', error);
      throw this.handleError(error);
    }
  }

  // Export attendance data
  async export(filters = {}) {
    try {
      const params = new URLSearchParams();
      
      if (filters.bucket) params.append('bucket', filters.bucket);
      if (filters.start_date) params.append('start_date', filters.start_date);
      if (filters.end_date) params.append('end_date', filters.end_date);
      if (filters.user_id) params.append('user_id', filters.user_id);
      if (filters.status) params.append('status', filters.status);
      if (filters.format) params.append('format', filters.format);

      const queryString = params.toString();
      const url = queryString ? `${this.baseURL}/export?${queryString}` : `${this.baseURL}/export`;
      
      const response = await api.get(url, {
        responseType: 'blob'
      });
      
      return response.data;
    } catch (error) {
      console.error('Error exporting attendance data:', error);
      throw this.handleError(error);
    }
  }

  // Validate attendance data before submission
  validateAttendanceData(data) {
    const errors = {};

    // Required fields validation
    if (!data.user_id) {
      errors.user_id = 'Pengguna harus dipilih';
    }

    if (!data.tanggal) {
      errors.tanggal = 'Tanggal harus diisi';
    } else {
      const selectedDate = toServerDateInput(data.tanggal);
      const today = toServerDateInput(getServerNowDate());
      if (selectedDate && today && selectedDate > today) {
        errors.tanggal = 'Tanggal tidak boleh lebih dari hari ini';
      }
    }

    if (!data.status) {
      errors.status = 'Status absensi harus dipilih';
    }

    if (!data.reason || data.reason.trim().length < 10) {
      errors.reason = 'Alasan harus diisi minimal 10 karakter';
    }

    // Time validation
    if (data.jam_masuk && data.jam_pulang) {
      const jamMasuk = toTimeMinutes(data.jam_masuk);
      const jamPulang = toTimeMinutes(data.jam_pulang);

      if (jamMasuk !== null && jamPulang !== null && jamPulang <= jamMasuk) {
        errors.jam_pulang = 'Jam pulang harus lebih besar dari jam masuk';
      }
    }

    // Status-specific validation
    if (data.status === 'hadir' && !data.jam_masuk) {
      errors.jam_masuk = 'Jam masuk harus diisi untuk status hadir';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  // Get attendance summary for dashboard
  async getSummary(filters = {}) {
    try {
      const params = new URLSearchParams();
      
      if (filters.start_date) params.append('start_date', filters.start_date);
      if (filters.end_date) params.append('end_date', filters.end_date);

      const queryString = params.toString();
      const url = queryString ? `${this.baseURL}/summary?${queryString}` : `${this.baseURL}/summary`;
      
      const response = await api.get(url);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance summary:', error);
      throw this.handleError(error);
    }
  }

  // Check for duplicate attendance
  async checkDuplicate(userId, date) {
    try {
      const response = await api.post(`${this.baseURL}/check-duplicate`, {
        user_id: userId,
        tanggal: date
      });
      return response.data;
    } catch (error) {
      console.error('Error checking duplicate attendance:', error);
      throw this.handleError(error);
    }
  }

  async getIncidentOptions() {
    try {
      const response = await api.get(`${this.baseURL}/incident-options`);
      return response.data;
    } catch (error) {
      console.error('Error fetching incident options:', error);
      throw this.handleError(error);
    }
  }

  async getRecentIncidents(limit = 8) {
    try {
      const response = await api.get(`${this.baseURL}/incidents`, {
        params: { limit },
      });
      return response.data;
    } catch (error) {
      console.error('Error fetching recent attendance incidents:', error);
      throw this.handleError(error);
    }
  }

  async previewIncident(payload) {
    try {
      const response = await api.post(`${this.baseURL}/incidents/preview`, payload);
      return response.data;
    } catch (error) {
      console.error('Error previewing attendance incident:', error);
      throw this.handleError(error);
    }
  }

  async createIncident(payload) {
    try {
      const response = await api.post(`${this.baseURL}/incidents`, payload);
      return response.data;
    } catch (error) {
      console.error('Error creating attendance incident:', error);
      throw this.handleError(error);
    }
  }

  async getIncident(batchId) {
    try {
      const response = await api.get(`${this.baseURL}/incidents/${batchId}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance incident batch:', error);
      throw this.handleError(error);
    }
  }

  async exportIncident(batchId, format = 'xlsx', resultGroup = 'all') {
    try {
      const response = await api.get(`${this.baseURL}/incidents/${batchId}/export`, {
        params: { format, result_group: resultGroup },
        responseType: 'blob',
      });
      return response;
    } catch (error) {
      console.error('Error exporting attendance incident batch:', error);
      throw this.handleError(error);
    }
  }

  async bulkPreview(operation, attendanceList) {
    try {
      const response = await api.post(`${this.baseURL}/bulk-preview`, {
        operation,
        attendance_list: attendanceList,
      });
      return response.data;
    } catch (error) {
      console.error('Error previewing bulk attendance:', error);
      throw this.handleError(error);
    }
  }

  // Bulk create attendance
  async bulkCreate(attendanceList) {
    try {
      const response = await api.post(`${this.baseURL}/bulk-create`, {
        attendance_list: attendanceList
      });
      return response.data;
    } catch (error) {
      console.error('Error bulk creating attendance:', error);
      throw this.handleError(error);
    }
  }

  // Get attendance by date range
  async getByDateRange(startDate, endDate, userId = null) {
    try {
      const params = new URLSearchParams();
      params.append('start_date', startDate);
      params.append('end_date', endDate);
      if (userId) params.append('user_id', userId);

      const response = await api.get(`${this.baseURL}/date-range?${params.toString()}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching attendance by date range:', error);
      throw this.handleError(error);
    }
  }

  // Get pending checkout list (default H+1)
  async getPendingCheckout(filters = {}) {
    try {
      const params = new URLSearchParams();

      if (filters.user_id) params.append('user_id', filters.user_id);
      if (filters.date) params.append('date', filters.date);
      if (filters.start_date) params.append('start_date', filters.start_date);
      if (filters.end_date) params.append('end_date', filters.end_date);
      if (filters.include_overdue) params.append('include_overdue', '1');
      if (filters.per_page) params.append('per_page', filters.per_page);
      if (filters.page) params.append('page', filters.page);

      const queryString = params.toString();
      const url = queryString
        ? `${this.baseURL}/pending-checkout?${queryString}`
        : `${this.baseURL}/pending-checkout`;

      const response = await api.get(url);
      return response.data;
    } catch (error) {
      console.error('Error fetching pending checkout list:', error);
      throw this.handleError(error);
    }
  }

  // Bulk correct existing attendance
  async bulkCorrect(attendanceList) {
    try {
      const response = await api.post(`${this.baseURL}/bulk-correct`, {
        attendance_list: attendanceList
      });
      return response.data;
    } catch (error) {
      console.error('Error bulk correcting attendance:', error);
      throw this.handleError(error);
    }
  }

  // Resolve missing checkout manually
  async resolveCheckout(attendanceId, data) {
    try {
      const payload = {
        jam_pulang: data.jam_pulang,
        reason: data.reason,
        status: data.status || null,
        keterangan: data.keterangan || null,
        override_reason: data.override_reason || null,
      };

      const response = await api.post(`${this.baseURL}/${attendanceId}/resolve-checkout`, payload);
      return response.data;
    } catch (error) {
      console.error('Error resolving pending checkout:', error);
      throw this.handleError(error);
    }
  }

  // Handle API errors
  handleError(error) {
    if (error.response) {
      // Server responded with error status
      const { status, data } = error.response;
      const validationBag = data?.errors && typeof data.errors === 'object' ? data.errors : null;
      const firstValidationMessage = validationBag
        ? Object.values(validationBag).flat().find((message) => typeof message === 'string' && message.trim().length > 0)
        : null;
      const serverMessage = data?.message || data?.error || firstValidationMessage || null;
      
      switch (status) {
        case 400:
          return new Error(serverMessage || 'Data tidak valid');
        case 401:
          return new Error(serverMessage || 'Sesi login berakhir, silakan login ulang');
        case 403:
          return new Error(serverMessage || 'Akses ditolak');
        case 404:
          return new Error(serverMessage || 'Data tidak ditemukan');
        case 422:
          return new Error(serverMessage || 'Data tidak valid');
        case 500:
          return new Error(serverMessage || 'Terjadi kesalahan server');
        default:
          return new Error(serverMessage || 'Terjadi kesalahan');
      }
    } else if (error.request) {
      // Network error
      return new Error('Tidak dapat terhubung ke server');
    } else {
      // Other error
      return new Error(error.message || 'Terjadi kesalahan');
    }
  }

  // Utility methods
  formatAttendanceData(data) {
    return {
      ...data,
      tanggal: data.tanggal ? toServerDateInput(data.tanggal) : null,
      jam_masuk: data.jam_masuk ? data.jam_masuk.substring(0, 5) : null,
      jam_pulang: data.jam_pulang ? data.jam_pulang.substring(0, 5) : null,
      created_at: data.created_at || null,
      updated_at: data.updated_at || null
    };
  }

  getStatusLabel(status) {
    const statusLabels = {
      hadir: 'Hadir',
      terlambat: 'Terlambat',
      izin: 'Izin',
      sakit: 'Sakit',
      alpha: 'Alpha'
    };
    return statusLabels[status] || status;
  }

  getStatusColor(status) {
    const statusColors = {
      hadir: 'green',
      terlambat: 'yellow',
      izin: 'blue',
      sakit: 'purple',
      alpha: 'red'
    };
    return statusColors[status] || 'gray';
  }
}

export const manualAttendanceService = new ManualAttendanceService();
export default manualAttendanceService;

import axios from 'axios';
import { getApiConfig } from '../config/api';
import { syncServerClockFromResponse } from './serverClock';
import { clearStoredAuth, getStoredToken } from '../utils/authStorage';

// Create axios instance with configuration
const api = axios.create(getApiConfig());
const DAPODIK_PREVIEW_TIMEOUT = 120000;
const DAPODIK_HEAVY_TIMEOUT = 300000;

// Request interceptor untuk menambahkan token
api.interceptors.request.use(
  (config) => {
    const token = getStoredToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Identify browser dashboard client explicitly for backend policy checks.
    config.headers['X-Client-Platform'] = config.headers['X-Client-Platform'] || 'web';
    config.headers['X-Client-App'] = config.headers['X-Client-App'] || 'dashboard-web';

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor untuk handle errors
api.interceptors.response.use(
  (response) => {
    syncServerClockFromResponse(response);
    return response;
  },
  (error) => {
    syncServerClockFromResponse(error?.response);
    if (error.response?.status === 401) {
      clearStoredAuth();
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

const blobToDataUrl = (blob) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });

const normalizeAttendanceSubmitPayload = async (data, jenisAbsensi) => {
  const payload = {
    jenis_absensi: jenisAbsensi
  };

  const assignScalar = (source, key) => {
    const value = source?.[key];
    if (value !== undefined && value !== null && value !== '') {
      payload[key] = value;
    }
  };

  if (data instanceof FormData) {
    ['latitude', 'longitude', 'accuracy', 'lokasi_id', 'kelas_id', 'keterangan', 'metode'].forEach((key) => {
      const value = data.get(key);
      if (value !== null && value !== '') {
        payload[key] = value;
      }
    });

    const photoValue = data.get('foto') || data.get('foto_checkin') || data.get('foto_checkout');
    if (typeof photoValue === 'string' && photoValue.trim() !== '') {
      payload.foto = photoValue;
    } else {
      const photoFile = data.get('photo');
      if (photoFile instanceof Blob && photoFile.size > 0) {
        payload.foto = await blobToDataUrl(photoFile);
      }
    }

    return payload;
  }

  if (data && typeof data === 'object') {
    ['latitude', 'longitude', 'accuracy', 'lokasi_id', 'kelas_id', 'keterangan', 'metode'].forEach((key) => {
      assignScalar(data, key);
    });

    if (typeof data.foto === 'string' && data.foto.trim() !== '') {
      payload.foto = data.foto;
    } else if (typeof data.foto_checkin === 'string' && data.foto_checkin.trim() !== '') {
      payload.foto = data.foto_checkin;
    } else if (typeof data.foto_checkout === 'string' && data.foto_checkout.trim() !== '') {
      payload.foto = data.foto_checkout;
    }
  }

  return payload;
};

const normalizeQueryParams = (params = {}) =>
  Object.fromEntries(
    Object.entries(params).map(([key, value]) => [
      key,
      typeof value === 'boolean' ? (value ? 1 : 0) : value
    ])
  );

const createMobileOnlyAttendanceError = () => {
  const error = new Error(
    'Absensi hanya dapat dilakukan melalui mobile app. Dashboard web hanya untuk monitoring.'
  );
  error.code = 'MOBILE_APP_ONLY';
  error.isMobileOnlyAttendance = true;
  return error;
};

const assertAttendanceSubmissionSupportedInClient = () => {
  if (typeof window !== 'undefined') {
    throw createMobileOnlyAttendanceError();
  }
};

// Simple Attendance API (new schema-based setting endpoints)
export const simpleAttendanceAPI = {
  getGlobalSettings: () => api.get('/simple-attendance/global'),
  updateGlobalSettings: (data) => api.put('/simple-attendance/global', data),
  getSystemHealth: () => api.get('/simple-attendance/health-check'),
  getGovernanceLogs: (params = {}) => api.get('/simple-attendance/governance-logs', { params }),
  getSecurityEvents: (params = {}) => api.get('/simple-attendance/security-events', { params }),
  getSecurityEventSummary: (params = {}) => api.get('/simple-attendance/security-events/summary', { params }),
  exportSecurityEvents: (params = {}) => api.get('/simple-attendance/security-events/export', { params, responseType: 'blob' }),
  getFraudAssessments: (params = {}) => api.get('/simple-attendance/fraud-assessments', { params }),
  getFraudAssessmentSummary: (params = {}) => api.get('/simple-attendance/fraud-assessments/summary', { params }),
  getFraudAssessmentById: (id) => api.get(`/simple-attendance/fraud-assessments/${id}`),
  exportFraudAssessments: (params = {}) => api.get('/simple-attendance/fraud-assessments/export', { params, responseType: 'blob' }),
  getDisciplineOverrides: (params = {}) => api.get('/simple-attendance/discipline-overrides', { params: normalizeQueryParams(params) }),
  createDisciplineOverride: (data) => api.post('/simple-attendance/discipline-overrides', data),
  updateDisciplineOverride: (id, data) => api.put(`/simple-attendance/discipline-overrides/${id}`, data),
  deleteDisciplineOverride: (id) => api.delete(`/simple-attendance/discipline-overrides/${id}`),
  getUserSettings: (userId = null) =>
    api.get(userId ? `/simple-attendance/user/${userId}` : '/simple-attendance/user'),
  getAllUsersSettings: () => api.get('/simple-attendance/users/all')
};

export const monitoringKelasAPI = {
  getClasses: () => api.get('/monitoring-kelas/kelas'),
  getClassDetail: (classId) => api.get(`/monitoring-kelas/kelas/${classId}`),
  getClassAttendance: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/absensi`, { params }),
  getClassStatistics: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/statistik`, { params }),
  getClassLeaves: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/izin`, { params }),
  getClassSecurityEvents: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/security-events`, { params }),
  exportClassSecurityEvents: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/security-events/export`, { params, responseType: 'blob' }),
  getClassSecurityStudents: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/security-students`, { params }),
  getClassSecurityStudent: (classId, userId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/security-students/${userId}`, { params }),
  getClassSecurityCases: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/security-cases`, { params }),
  createClassSecurityCase: (classId, payload) =>
    api.post(`/monitoring-kelas/kelas/${classId}/security-cases`, payload),
  updateClassSecurityCase: (classId, caseId, payload) =>
    api.patch(`/monitoring-kelas/kelas/${classId}/security-cases/${caseId}`, payload),
  resolveClassSecurityCase: (classId, caseId, payload) =>
    api.post(`/monitoring-kelas/kelas/${classId}/security-cases/${caseId}/resolve`, payload),
  reopenClassSecurityCase: (classId, caseId) =>
    api.post(`/monitoring-kelas/kelas/${classId}/security-cases/${caseId}/reopen`),
  addClassSecurityCaseNote: (classId, caseId, payload) =>
    api.post(`/monitoring-kelas/kelas/${classId}/security-cases/${caseId}/notes`, payload),
  uploadClassSecurityCaseEvidence: (classId, caseId, formData) =>
    api.post(`/monitoring-kelas/kelas/${classId}/security-cases/${caseId}/evidence`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  getClassFraudAssessments: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/fraud-assessments`, { params }),
  exportClassFraudAssessments: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/fraud-assessments/export`, { params, responseType: 'blob' }),
  getClassFraudSummary: (classId, params = {}) =>
    api.get(`/monitoring-kelas/kelas/${classId}/fraud-assessments/summary`, { params }),
  getClassFraudAssessmentById: (classId, assessmentId) =>
    api.get(`/monitoring-kelas/kelas/${classId}/fraud-assessments/${assessmentId}`),
};

// Users API
export const usersAPI = {
  getAll: (params = {}) => api.get('/users', { params }),
  getById: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  search: (query) => api.get(`/users/search?q=${query}`)
};

// Roles API
export const rolesAPI = {
  getAll: (params = {}) => api.get('/roles', { params }),
  getById: (id) => api.get(`/roles/${id}`),
  create: (data) => api.post('/roles', data),
  update: (id, data) => api.put(`/roles/${id}`, data),
  delete: (id) => api.delete(`/roles/${id}`),
  assignPermissions: (id, permissions) => api.post(`/roles/${id}/assign-permissions`, { permissions }),
  getEffectivePermissions: (payload) => api.post('/roles/effective-permissions', payload)
};

// Kelas API
export const kelasAPI = {
  getAll: (params = {}) => api.get('/kelas', { params }),
  getByTingkat: (tingkatId) => api.get(`/kelas/tingkat/${tingkatId}`),
  getById: (id) => api.get(`/kelas/${id}`),
  create: (data) => api.post('/kelas', data),
  update: (id, data) => api.put(`/kelas/${id}`, data),
  delete: (id) => api.delete(`/kelas/${id}`),
  getSiswa: (id) => api.get(`/kelas/${id}/siswa`),
  getAvailableSiswa: (id, params = {}) => api.get(`/kelas/${id}/available-siswa`, { params }),
  assignSiswa: (kelasId, payload) => api.post(`/kelas/${kelasId}/assign-siswa`, payload),
  addSiswa: (kelasId, siswaId) => api.post(`/kelas/${kelasId}/add-siswa`, { siswa_id: siswaId }),
  removeSiswa: (kelasId, siswaId) => api.delete(`/kelas/${kelasId}/siswa/${siswaId}`)
};

// Absensi API
export const absensiAPI = {
  getAll: (params = {}) => api.get('/absensi/history', { params }),
  getById: (id) => api.get(`/absensi/${id}`),
  checkIn: async (data) => {
    assertAttendanceSubmissionSupportedInClient();
    const payload = await normalizeAttendanceSubmitPayload(data, 'masuk');
    const response = await api.post('/simple-attendance/submit', payload);
    response.data = {
      ...response.data,
      success: response.data?.status === 'success'
    };
    return response;
  },
  checkOut: async (data) => {
    assertAttendanceSubmissionSupportedInClient();
    const payload = await normalizeAttendanceSubmitPayload(data, 'pulang');
    const response = await api.post('/simple-attendance/submit', payload);
    response.data = {
      ...response.data,
      success: response.data?.status === 'success'
    };
    return response;
  },
  getHistory: (params = {}) => api.get('/absensi/history', { params }),
  getStatistics: (params = {}) => api.get('/absensi/statistics', { params }),
  getTodayAttendance: (params = {}) => api.get('/dashboard/today-attendance', { params }),
  getMyStatus: () => api.get('/dashboard/my-attendance-status')
};

// Dashboard API
export const dashboardAPI = {
  getStats: () => api.get('/dashboard/stats'),
  getTodayAttendance: (params = {}) => api.get('/dashboard/today-attendance', { params }),
  getMyAttendanceStatus: () => api.get('/dashboard/my-attendance-status'),
  getRecentActivities: () => api.get('/dashboard/recent-activities'),
  getSystemStatus: () => api.get('/dashboard/system-status'),
  getRecentActivity: () => api.get('/dashboard/recent-activities')
};

// Reports API
export const reportsAPI = {
  getDailyReport: (params = {}) => api.get('/reports/attendance/daily', { params }),
  getMonthlyReport: (params = {}) => api.get('/reports/attendance/monthly', { params }),
  getYearlyReport: (params = {}) => api.get('/reports/attendance/yearly', { params }),
  exportExcel: (params = {}) => api.get('/reports/export/excel', { 
    params, 
    responseType: 'blob' 
  }),
  exportPdf: (params = {}) => api.get('/reports/export/pdf', { 
    params, 
    responseType: 'blob' 
  })
};

// Izin API
export const izinAPI = {
  getAll: (params = {}) => api.get('/izin', { params }),
  getById: (id) => api.get(`/izin/${id}`),
  create: (data) => api.post('/izin', data),
  delete: (id) => api.delete(`/izin/${id}`),
  approve: (id, data) => api.post(`/izin/${id}/approve`, data),
  reject: (id, data) => api.post(`/izin/${id}/reject`, data),
  getStatistics: (params = {}) => api.get('/izin/statistics', { params })
};

// Siswa API
export const siswaAPI = {
  getAll: (params = {}) => api.get('/siswa', { params }),
  getById: (id) => api.get(`/siswa/${id}`),
  create: (data) => api.post('/siswa', data),
  // Backward-compatibility alias used by older components
  tambah: (data) => api.post('/siswa', data),
  update: (id, data) => {
    if (data instanceof FormData) {
      if (!data.has('_method')) {
        data.append('_method', 'PUT');
      }

      return api.post(`/siswa/${id}`, data, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
    }

    return api.put(`/siswa/${id}`, data);
  },
  delete: (id) => api.delete(`/siswa/${id}`),
  import: (data) => api.post('/siswa/import', data),
  export: (params = {}) => api.get('/siswa/export', { params, responseType: 'blob' }),
  downloadTemplate: () => api.get('/siswa/template', { responseType: 'blob' }),
  resetPassword: (id) => api.post(`/siswa/${id}/reset-password`)
};

// Pegawai API
export const pegawaiAPI = {
  getAll: (params = {}) => api.get('/pegawai', { params }),
  getById: (id) => api.get(`/pegawai/${id}`),
  create: (data) => api.post('/pegawai', data),
  update: (id, data) => api.put(`/pegawai/${id}`, data),
  delete: (id) => api.delete(`/pegawai/${id}`),
  import: (data) => api.post('/pegawai/import', data),
  export: (params = {}) => api.get('/pegawai/export', { params, responseType: 'blob' }),
  downloadTemplate: () => api.get('/pegawai/template', { responseType: 'blob' }),
  resetPassword: (id) => api.post(`/pegawai/${id}/reset-password`)
};

// Status Kepegawaian API
export const statusKepegawaianAPI = {
  getAll: () => api.get('/status-kepegawaian'),
  getEnumValues: () => api.get('/status-kepegawaian/enum'),
  getById: (id) => api.get(`/status-kepegawaian/${id}`),
  create: (data) => api.post('/status-kepegawaian', data),
  update: (id, data) => api.put(`/status-kepegawaian/${id}`, data),
  delete: (id) => api.delete(`/status-kepegawaian/${id}`)
};

// Tahun Ajaran API
export const tahunAjaranAPI = {
  getAll: (params = {}) => api.get('/tahun-ajaran', { params }),
  getById: (id) => api.get(`/tahun-ajaran/${id}`),
  create: (data) => api.post('/tahun-ajaran', data),
  update: (id, data) => api.put(`/tahun-ajaran/${id}`, data),
  delete: (id) => api.delete(`/tahun-ajaran/${id}`),
  activate: (id) => api.post(`/tahun-ajaran/${id}/activate`),
  transitionStatus: (id, status) => api.post(`/tahun-ajaran/${id}/transition-status`, { status }),
  updateProgress: (id, progress) => api.post(`/tahun-ajaran/${id}/update-progress`, { progress })
};

// Tingkat API
export const tingkatAPI = {
  getAll: (params = {}) => api.get('/tingkat', { params }),
  getActive: () => api.get('/tingkat/active'),
  getById: (id) => api.get(`/tingkat/${id}`),
  create: (data) => api.post('/tingkat', data),
  update: (id, data) => api.put(`/tingkat/${id}`, data),
  delete: (id) => api.delete(`/tingkat/${id}`),
  toggleStatus: (id) => api.post(`/tingkat/${id}/toggle-status`)
};

// Periode Akademik API
export const periodeAkademikAPI = {
  getAll: (params = {}) => api.get('/periode-akademik', { params }),
  getById: (id) => api.get(`/periode-akademik/${id}`),
  getCurrentPeriode: () => api.get('/periode-akademik/current/periode'),
  checkAbsensiValidity: (data) => api.post('/periode-akademik/check/absensi-validity', data),
  create: (data) => api.post('/periode-akademik', data),
  update: (id, data) => api.put(`/periode-akademik/${id}`, data),
  delete: (id) => api.delete(`/periode-akademik/${id}`)
};

export const academicContextAPI = {
  getCurrent: (params = {}) => api.get('/academic-context/current', { params }),
};

// Event Akademik API
export const eventAkademikAPI = {
  getAll: (params = {}) => api.get('/event-akademik', { params }),
  getById: (id) => api.get(`/event-akademik/${id}`),
  getUpcomingEvents: () => api.get('/event-akademik/user/upcoming'),
  getTodayEvents: () => api.get('/event-akademik/user/today'),
  previewLiburNasional: (data) => api.post('/event-akademik/preview-libur-nasional', data),
  syncLiburNasional: (data) => api.post('/event-akademik/sync-libur-nasional', data),
  autoSyncLiburNasional: (data) => api.post('/event-akademik/auto-sync-libur-nasional', data),
  previewKalenderIndonesia: (data) => api.post('/event-akademik/preview-kalender-indonesia', data),
  syncKalenderIndonesia: (data) => api.post('/event-akademik/sync-kalender-indonesia', data),
  syncKalenderIndonesiaLengkap: (data) => api.post('/event-akademik/sync-kalender-indonesia-lengkap', data),
  autoSyncKalenderIndonesia: (data) => api.post('/event-akademik/auto-sync-kalender-indonesia', data),
  create: (data) => api.post('/event-akademik', data),
  update: (id, data) => api.put(`/event-akademik/${id}`, data),
  delete: (id) => api.delete(`/event-akademik/${id}`)
};

// Lokasi GPS API
export const lokasiGpsAPI = {
  getAll: (params = {}) => api.get('/lokasi-gps', { params }),
  getActive: () => api.get('/lokasi-gps/active'),
  getAttendanceSchema: () => api.get('/lokasi-gps/attendance-schema'),
  getById: (id) => api.get(`/lokasi-gps/${id}`),
  create: (data) => api.post('/lokasi-gps', data),
  update: (id, data) => api.put(`/lokasi-gps/${id}`, data),
  delete: (id) => api.delete(`/lokasi-gps/${id}`),
  toggle: (id) => api.post(`/lokasi-gps/${id}/toggle`),
  validateLocation: (data) => api.post('/lokasi-gps/validate', data),
  import: (data) => api.post('/lokasi-gps/import', data),
  export: (params = {}) => api.get('/lokasi-gps/export', { params, responseType: 'blob' })
};

// Notifications API
export const notificationsAPI = {
  getAll: (params = {}) => api.get('/notifications', { params }),
  getById: (id) => api.get(`/notifications/${id}`),
  markAsRead: (id) => api.post(`/notifications/${id}/read`),
  markAllAsRead: (data = {}) => api.post('/notifications/read-all', data),
  delete: (id) => api.delete(`/notifications/${id}`),
  getUnreadCount: (params = {}) => api.get('/notifications/unread/count', { params }),
  create: (data) => api.post('/notifications', data),
  broadcast: (data) => api.post('/notifications/broadcast', data)
};

export const broadcastCampaignsAPI = {
  getAll: (params = {}) => api.get('/broadcast-campaigns', { params }),
  create: (data) => api.post('/broadcast-campaigns', data),
  uploadFlyer: (file) => {
    const formData = new FormData();
    formData.append('flyer', file);
    return api.post('/broadcast-campaigns/upload-flyer', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },
};

export const attendanceDisciplineCasesAPI = {
  getAll: (params = {}) => api.get('/attendance-discipline-cases', { params }),
  getById: (id) => api.get(`/attendance-discipline-cases/${id}`),
  export: (params = {}) => api.get('/attendance-discipline-cases/export', {
    params,
    responseType: 'blob',
  }),
};

export const faceTemplatesAPI = {
  getMine: () => api.get('/face-templates/me'),
  getForUser: (userId) => api.get(`/face-templates/users/${userId}`),
  enroll: (payload) => {
    const formData = new FormData();
    formData.append('user_id', payload.userId);
    if (payload.file) {
      formData.append('foto_file', payload.file);
    }

    return api.post('/face-templates/enroll', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },
  selfSubmit: (file) => {
    const formData = new FormData();
    if (file) {
      formData.append('foto_file', file);
    }

    return api.post('/face-templates/self-submit', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },
  unlockSelfSubmit: (userId) => api.post(`/face-templates/users/${userId}/unlock-self-submit`),
  deactivate: (templateId) => api.delete(`/face-templates/${templateId}`),
};

export const deviceTokensAPI = {
  getAll: () => api.get('/device-tokens'),
  register: (data) => api.post('/device-tokens/register', data),
  deactivate: (data) => api.post('/device-tokens/deactivate', data),
};

export const pushConfigAPI = {
  getWebConfig: () => api.get('/push/config/web'),
};

const mobileReleaseUploadConfig = (options = {}) => ({
  timeout: 300000,
  ...options,
  headers: {
    'Content-Type': 'multipart/form-data',
    ...(options.headers || {})
  }
});

export const mobileReleasesAPI = {
  getAll: (params = {}) => api.get('/mobile-releases', { params }),
  getById: (id) => api.get(`/mobile-releases/${id}`),
  create: (data, options = {}) => {
    if (data instanceof FormData) {
      return api.post('/mobile-releases', data, mobileReleaseUploadConfig(options));
    }

    return api.post('/mobile-releases', data);
  },
  update: (id, data, options = {}) => {
    if (data instanceof FormData) {
      if (!data.has('_method')) {
        data.append('_method', 'PUT');
      }

      return api.post(`/mobile-releases/${id}`, data, mobileReleaseUploadConfig(options));
    }

    return api.put(`/mobile-releases/${id}`, data);
  },
  delete: (id) => api.delete(`/mobile-releases/${id}`),
  getCatalogList: (params = {}) => api.get('/mobile-releases/catalog', { params }),
  getDownloadLink: (id) => api.get(`/mobile-releases/${id}/download-link`),
  downloadAsset: (id) => api.get(`/mobile-releases/${id}/download`, { responseType: 'blob' }),
};

export const sbtAPI = {
  getSettings: () => api.get('/sbt/admin/settings'),
  updateSettings: (data) => api.put('/sbt/admin/settings', data),
  getSummary: () => api.get('/sbt/admin/summary'),
  getSessions: (params = {}) => api.get('/sbt/admin/sessions', { params }),
  getEvents: (params = {}) => api.get('/sbt/admin/events', { params }),
};

export const dapodikAPI = {
  getSettings: () => api.get('/dapodik/settings'),
  updateSettings: (data) => api.put('/dapodik/settings', data),
  testConnection: (data = {}) => api.post('/dapodik/test-connection', data),
  createStagingBatch: (data = {}) => api.post('/dapodik/staging-batches', data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  getStagingBatch: (batchId) => api.get(`/dapodik/staging-batches/${batchId}`),
  getStagingReview: (batchId, params = {}) => api.get(`/dapodik/staging-batches/${batchId}/review`, { params, timeout: DAPODIK_PREVIEW_TIMEOUT }),
  getApplyPreview: (batchId, params = {}) => api.get(`/dapodik/staging-batches/${batchId}/apply-preview`, { params, timeout: DAPODIK_PREVIEW_TIMEOUT }),
  applyStagingBatch: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/apply`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  getInputPreview: (batchId, params = {}) => api.get(`/dapodik/staging-batches/${batchId}/input-preview`, { params, timeout: DAPODIK_PREVIEW_TIMEOUT }),
  inputStagingBatch: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/input`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  getClassPreview: (batchId, params = {}) => api.get(`/dapodik/staging-batches/${batchId}/class-preview`, { params, timeout: DAPODIK_PREVIEW_TIMEOUT }),
  syncClasses: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/class-sync`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  getClassMembershipPreview: (batchId, params = {}) => api.get(`/dapodik/staging-batches/${batchId}/class-membership-preview`, { params, timeout: DAPODIK_PREVIEW_TIMEOUT }),
  syncClassMemberships: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/class-membership-sync`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  fetchStagingBatchSource: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/sources`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
  finalizeStagingBatch: (batchId, data = {}) => api.post(`/dapodik/staging-batches/${batchId}/finalize`, data, { timeout: DAPODIK_HEAVY_TIMEOUT }),
};

// QR Code API
export const qrCodeAPI = {
  attendance: (code) => api.get(`/qr-code/attendance/${code}`),
  validate: (data) => api.post('/qr-code/validate', data),
  generate: (data) => api.post('/qr-code/generate', data),
  bulk: (data) => api.post('/qr-code/bulk', data)
};

// Permissions API
export const permissionsAPI = {
  getAll: () => api.get('/permissions'),
  getByModule: () => api.get('/permissions/by-module'),
  getModules: () => api.get('/permissions/modules'),
  getById: (id) => api.get(`/permissions/${id}`),
  create: (data) => api.post('/permissions', data),
  update: (id, data) => api.put(`/permissions/${id}`, data),
  delete: (id) => api.delete(`/permissions/${id}`)
};

// Backups API
export const backupsAPI = {
  getAll: () => api.get('/backups'),
  create: (data = { type: 'database' }) => api.post('/backups', data),
  getDownloadLink: (filename) => api.get(`/backups/${filename}/download-link`),
  download: (filename) => api.get(`/backups/${filename}`, { responseType: 'blob' }),
  delete: (filename) => api.delete(`/backups/${filename}`),
  restore: (filename, data = { confirm: true }) => api.post(`/backups/${filename}/restore`, data),
  getSettings: () => api.get('/backups/settings'),
  updateSettings: (data) => api.post('/backups/settings', data),
  cleanup: () => api.post('/backups/cleanup')
};

// Activity Logs API
export const activityLogsAPI = {
  getAll: (params = {}) => api.get('/activity-logs', { params }),
  getFilters: () => api.get('/activity-logs/filters'),
  getStatistics: () => api.get('/activity-logs/statistics'),
  export: (params = {}) => api.get('/activity-logs/export', { params, responseType: 'blob' }),
  getById: (id) => api.get(`/activity-logs/${id}`),
  getUserTimeline: (userId) => api.get(`/activity-logs/user/${userId}/timeline`),
  cleanup: () => api.post('/activity-logs/cleanup')
};

// Live Tracking API
export const liveTrackingAPI = {
  getHistory: (params = {}) => api.get('/live-tracking/history', { params }),
  getCurrentTracking: () => api.get('/live-tracking/current'),
  getCurrentLocation: () => api.get('/live-tracking/current-location'),
  getUsersInRadius: (data) => api.post('/live-tracking/users-in-radius', data),
  export: (params = {}) => api.get('/live-tracking/export', { params, responseType: 'blob' }),
  startTrackingSession: (data) => api.post('/live-tracking/session/start', data),
  stopTrackingSession: (data) => api.post('/live-tracking/session/stop', data),
  getActiveTrackingSessions: (params = {}) => api.get('/live-tracking/session/active', { params })
};

// WhatsApp API
export const whatsappAPI = {
  send: (data) => api.post('/whatsapp/send', data),
  broadcast: (data) => api.post('/whatsapp/broadcast', data),
  checkNumber: (data) => api.post('/whatsapp/check-number', data),
  generateQr: (data = {}) => api.post('/whatsapp/generate-qr', data),
  logoutDevice: () => api.post('/whatsapp/logout-device'),
  deleteDevice: () => api.post('/whatsapp/delete-device'),
  getStatus: () => api.get('/whatsapp/status'),
  getWebhookEvents: (params = {}) => api.get('/whatsapp/webhook-events', { params }),
  getSkipEvents: (params = {}) => api.get('/whatsapp/skip-events', { params }),
  updateSettings: (data) => api.post('/whatsapp/settings', data),
  getAutomations: () => api.get('/whatsapp/automations'),
  updateAutomations: (data) => api.post('/whatsapp/automations', data),
};

// Settings API
export const settingsAPI = {
  getAll: () => api.get('/settings'),
  update: (data) => api.post('/settings', data),
  getSchoolProfile: () => api.get('/settings/school-profile'),
  updateSchoolProfile: (data) => api.post('/settings/school-profile', data)
};

// Personal data self-service API
export const personalDataAPI = {
  get: () => api.get('/me/personal-data'),
  getSchema: () => api.get('/me/personal-data/schema'),
  update: (data) => api.patch('/me/personal-data', data),
  getDocuments: () => api.get('/me/personal-data/documents'),
  uploadDocument: (formData) => api.post('/me/personal-data/documents', formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  }),
  deleteDocument: (documentId) => api.delete(`/me/personal-data/documents/${documentId}`),
  getReviewQueue: (params = {}) => api.get('/personal-data/review-queue', { params }),
  submitReviewDecision: (userId, data) => api.post(`/personal-data/review-queue/${userId}/decision`, data),
  getForUser: (userId) => api.get(`/users/${userId}/personal-data`),
  getSchemaForUser: (userId) => api.get(`/users/${userId}/personal-data/schema`),
  updateForUser: (userId, data) => api.patch(`/users/${userId}/personal-data`, data),
  getDocumentsForUser: (userId) => api.get(`/users/${userId}/personal-data/documents`),
  uploadDocumentForUser: (userId, formData) => api.post(`/users/${userId}/personal-data/documents`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  }),
  deleteDocumentForUser: (userId, documentId) => api.delete(`/users/${userId}/personal-data/documents/${documentId}`),
  updateAvatarForUser: (userId, file) => {
    const formData = new FormData();
    formData.append('avatar', file);
    return api.post(`/users/${userId}/personal-data/avatar`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },
  updateAvatar: (file) => {
    const formData = new FormData();
    formData.append('avatar', file);
    return api.post('/me/personal-data/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  }
};

// Auth API
export const authAPI = {
  // Web login menggunakan Sanctum
  loginWeb: (credentials) => api.post('/web/login', credentials),
  loginWebSiswa: (credentials) => api.post('/web/login-siswa', credentials),
  
  // Mobile login menggunakan JWT (untuk mobileapp)
  loginMobile: (credentials) => api.post('/mobile/login', credentials),
  
  // Login siswa mobile menggunakan JWT (untuk mobileapp)
  loginSiswa: (credentials) => api.post('/mobile/login-siswa', credentials),
  
  // Legacy login (backward compatibility)
  login: (credentials) => api.post('/login', credentials),
  
  // Profile functions (me is an alias for profile)
  me: () => api.get('/profile'),
  profile: () => api.get('/profile'),
  updateProfile: (data) => api.post('/update-profile', data),
  
  // Auth management
  logout: () => api.post('/logout'),
  refreshToken: () => api.post('/refresh-token'),
  changePassword: (data) => api.post('/change-password', data),
  
  // Password reset
  forgotPassword: (email) => api.post('/web/forgot-password', { email }),
  resetPassword: (data) => api.post('/web/reset-password', data),
  
  // Permission check
  checkPermission: (permission) => api.post('/check-permission', { permission }),

  // Role/feature profile for current user
  myFeatureProfile: () => api.get('/roles/my-feature-profile'),
  
  // Other functions (legacy)
  register: (userData) => api.post('/auth/register', userData),
  verifyEmail: (token) => api.post('/auth/verify-email', { token })
};

export default api;

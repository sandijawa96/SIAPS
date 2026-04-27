import api from './api';

export const jadwalPelajaranAPI = {
  getAll: async (params = {}) => api.get('/jadwal-pelajaran', { params }),
  getOptions: async (params = {}) => api.get('/jadwal-pelajaran/options', { params }),
  getSettings: async (params = {}) => api.get('/jadwal-pelajaran/settings', { params }),
  getMySchedule: async (params = {}) => api.get('/jadwal-pelajaran/my-schedule', { params }),
  checkConflict: async (payload) => api.post('/jadwal-pelajaran/check-conflict', payload),
  create: async (payload) => api.post('/jadwal-pelajaran', payload),
  update: async (id, payload) => api.put(`/jadwal-pelajaran/${id}`, payload),
  updateSettings: async (payload) => api.put('/jadwal-pelajaran/settings', payload),
  delete: async (id) => api.delete(`/jadwal-pelajaran/${id}`),
  publish: async (payload) => api.post('/jadwal-pelajaran/publish', payload),
  importData: async (formData) => api.post('/jadwal-pelajaran/import', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    timeout: 600000,
  }),
  exportData: async (params = {}) => api.get('/jadwal-pelajaran/export', {
    params,
    responseType: 'blob',
    timeout: 600000,
    headers: {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/pdf',
    },
  }),
  downloadTemplate: async () => api.get('/jadwal-pelajaran/template', {
    responseType: 'blob',
    headers: {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel',
    },
  }),
};

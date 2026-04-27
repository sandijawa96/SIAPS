import api from './api';

export const guruMapelAPI = {
  getAll: async (params = {}) => api.get('/guru-mapel', { params }),
  getOptions: async (params = {}) => api.get('/guru-mapel/options', { params }),
  create: async (payload) => api.post('/guru-mapel', payload),
  update: async (id, payload) => api.put(`/guru-mapel/${id}`, payload),
  delete: async (id) => api.delete(`/guru-mapel/${id}`),
  importData: async (formData) => api.post('/guru-mapel/import', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    timeout: 600000,
  }),
  exportData: async (params = {}) => api.get('/guru-mapel/export', {
    params,
    responseType: 'blob',
    timeout: 600000,
    headers: {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/pdf',
    },
  }),
  downloadTemplate: async () => api.get('/guru-mapel/template', {
    responseType: 'blob',
    headers: {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel',
    },
  }),
};

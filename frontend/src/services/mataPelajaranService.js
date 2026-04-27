import api from './api';

export const mataPelajaranAPI = {
  getAll: async (params = {}) => {
    return api.get('/mata-pelajaran', { params });
  },

  getById: async (id) => {
    return api.get(`/mata-pelajaran/${id}`);
  },

  create: async (payload) => {
    return api.post('/mata-pelajaran', payload);
  },

  update: async (id, payload) => {
    return api.put(`/mata-pelajaran/${id}`, payload);
  },

  delete: async (id) => {
    return api.delete(`/mata-pelajaran/${id}`);
  },

  importData: async (formData) => {
    return api.post('/mata-pelajaran/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      timeout: 600000,
    });
  },

  exportData: async (params = {}) => {
    return api.get('/mata-pelajaran/export', {
      params,
      responseType: 'blob',
      timeout: 600000,
      headers: {
        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/pdf',
      },
    });
  },

  downloadTemplate: async () => {
    return api.get('/mata-pelajaran/template', {
      responseType: 'blob',
      headers: {
        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel',
      },
    });
  },
};

import api from './api';

export const tingkatAPI = {
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/tingkat', { params });
      return response;
    } catch (error) {
      throw error;
    }
  },

  getById: async (id) => {
    try {
      const response = await api.get(`/tingkat/${id}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  create: async (data) => {
    try {
      const response = await api.post('/tingkat', data);
      return response;
    } catch (error) {
      throw error;
    }
  },

  update: async (id, data) => {
    try {
      const response = await api.put(`/tingkat/${id}`, data);
      return response;
    } catch (error) {
      throw error;
    }
  },

  delete: async (id) => {
    try {
      const response = await api.delete(`/tingkat/${id}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  getKelas: async (tingkatId) => {
    try {
      const response = await api.get(`/tingkat/${tingkatId}/kelas`);
      return response;
    } catch (error) {
      throw error;
    }
  }
};

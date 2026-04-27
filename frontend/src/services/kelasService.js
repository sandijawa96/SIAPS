import api from './api';

export const kelasAPI = {
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/kelas', { params });
      return response;
    } catch (error) {
      throw error;
    }
  },

  getById: async (id) => {
    try {
      const response = await api.get(`/kelas/${id}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  getByTingkat: async (tingkatId) => {
    try {
      const response = await api.get(`/kelas/tingkat/${tingkatId}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  getSiswa: async (kelasId, params = {}) => {
    try {
      const response = await api.get(`/kelas/${kelasId}/siswa`, { params });
      return response;
    } catch (error) {
      throw error;
    }
  },

  getAvailableSiswa: async (kelasId, params = {}) => {
    try {
      const response = await api.get(`/kelas/${kelasId}/available-siswa`, { params });
      return response;
    } catch (error) {
      throw error;
    }
  },

  create: async (data) => {
    try {
      const response = await api.post('/kelas', data);
      return response;
    } catch (error) {
      throw error;
    }
  },

  update: async (id, data) => {
    try {
      const response = await api.put(`/kelas/${id}`, data);
      return response;
    } catch (error) {
      throw error;
    }
  },

  delete: async (id) => {
    try {
      const response = await api.delete(`/kelas/${id}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  addSiswa: async (kelasId, siswaId) => {
    try {
      const response = await api.post(`/kelas/${kelasId}/add-siswa`, { siswa_id: siswaId });
      return response;
    } catch (error) {
      throw error;
    }
  },

  removeSiswa: async (kelasId, siswaId) => {
    try {
      const response = await api.delete(`/kelas/${kelasId}/siswa/${siswaId}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  assignSiswa: async (kelasId, payload) => {
    try {
      const response = await api.post(`/kelas/${kelasId}/assign-siswa`, payload);
      return response;
    } catch (error) {
      throw error;
    }
  },

  assignWaliKelas: async (kelasId, pegawaiId) => {
    try {
      const response = await api.post(`/kelas/${kelasId}/wali-kelas/${pegawaiId}`);
      return response;
    } catch (error) {
      throw error;
    }
  },

  removeWaliKelas: async (kelasId) => {
    try {
      const response = await api.delete(`/kelas/${kelasId}/wali-kelas`);
      return response;
    } catch (error) {
      throw error;
    }
  }
};

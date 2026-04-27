import api from './api';

const tahunAjaranService = {
  // Get all tahun ajaran
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/tahun-ajaran', { params });
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal mengambil data tahun ajaran');
    }
  },

  // Get tahun ajaran by ID
  getById: async (id) => {
    try {
      const response = await api.get(`/tahun-ajaran/${id}`);
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal mengambil data tahun ajaran');
    }
  },

  // Create new tahun ajaran
  create: async (data) => {
    try {
      const response = await api.post('/tahun-ajaran', data);
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal menambahkan tahun ajaran');
    }
  },

  // Update tahun ajaran
  update: async (id, data) => {
    try {
      const response = await api.put(`/tahun-ajaran/${id}`, data);
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal mengupdate tahun ajaran');
    }
  },

  // Delete tahun ajaran
  delete: async (id) => {
    try {
      const response = await api.delete(`/tahun-ajaran/${id}`);
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal menghapus tahun ajaran');
    }
  },

  // Set active tahun ajaran
  setActive: async (id) => {
    try {
      const response = await api.put(`/tahun-ajaran/${id}/set-active`);
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal mengatur tahun ajaran aktif');
    }
  },

  // Get active tahun ajaran
  getActive: async () => {
    try {
      const response = await api.get('/tahun-ajaran/active');
      return response.data;
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Gagal mengambil tahun ajaran aktif');
    }
  }
};

export default tahunAjaranService;

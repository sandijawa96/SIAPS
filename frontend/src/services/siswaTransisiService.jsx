import api from './api';

const siswaTransisiService = {
  // Naik kelas siswa (wali kelas with promotion window validation)
  naikKelasWali: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/naik-kelas/wali`, data);
      return response.data;
    } catch (error) {
      console.error('Error naik kelas wali:', error);
      throw error;
    }
  },

  // Naik kelas siswa
  naikKelas: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/naik-kelas`, data);
      return response.data;
    } catch (error) {
      console.error('Error naik kelas siswa:', error);
      throw error;
    }
  },

  // Pindah kelas siswa
  pindahKelas: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/pindah-kelas`, data);
      return response.data;
    } catch (error) {
      console.error('Error pindah kelas siswa:', error);
      throw error;
    }
  },

  // Wali kelas request pindah kelas (perlu approval kurikulum/admin)
  requestPindahKelas: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/pindah-kelas/request`, data);
      return response.data;
    } catch (error) {
      console.error('Error request pindah kelas siswa:', error);
      throw error;
    }
  },

  getTransferRequests: async (params = {}) => {
    try {
      const response = await api.get('/siswa-extended/transfer-requests', { params });
      return response.data;
    } catch (error) {
      console.error('Error get transfer requests:', error);
      throw error;
    }
  },

  approveTransferRequest: async (requestId, data = {}) => {
    try {
      const response = await api.post(`/siswa-extended/transfer-requests/${requestId}/approve`, data);
      return response.data;
    } catch (error) {
      console.error('Error approve transfer request:', error);
      throw error;
    }
  },

  rejectTransferRequest: async (requestId, data = {}) => {
    try {
      const response = await api.post(`/siswa-extended/transfer-requests/${requestId}/reject`, data);
      return response.data;
    } catch (error) {
      console.error('Error reject transfer request:', error);
      throw error;
    }
  },

  cancelTransferRequest: async (requestId, data = {}) => {
    try {
      const response = await api.post(`/siswa-extended/transfer-requests/${requestId}/cancel`, data);
      return response.data;
    } catch (error) {
      console.error('Error cancel transfer request:', error);
      throw error;
    }
  },

  getWaliPromotionSettings: async (params = {}) => {
    try {
      const response = await api.get('/siswa-extended/wali-promotion-settings', { params });
      return response.data;
    } catch (error) {
      console.error('Error get wali promotion settings:', error);
      throw error;
    }
  },

  upsertWaliPromotionSetting: async (data) => {
    try {
      const response = await api.put('/siswa-extended/wali-promotion-settings', data);
      return response.data;
    } catch (error) {
      console.error('Error upsert wali promotion settings:', error);
      throw error;
    }
  },

  // Lulus siswa
  lulusSiswa: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/lulus`, data);
      return response.data;
    } catch (error) {
      console.error('Error lulus siswa:', error);
      throw error;
    }
  },

  // Keluar siswa (drop out)
  keluarSiswa: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/keluar`, data);
      return response.data;
    } catch (error) {
      console.error('Error keluar siswa:', error);
      throw error;
    }
  },

  // Aktifkan kembali siswa
  aktifkanKembali: async (siswaId, data) => {
    try {
      const response = await api.post(`/siswa-extended/${siswaId}/aktifkan-kembali`, data);
      return response.data;
    } catch (error) {
      console.error('Error aktifkan kembali siswa:', error);
      throw error;
    }
  }
};

export default siswaTransisiService;

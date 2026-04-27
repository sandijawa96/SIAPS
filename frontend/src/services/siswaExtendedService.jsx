import api from './api';

export const siswaExtendedAPI = {
  // Get all siswa with extended data
  getAll: async (params = {}) => {
    try {
      console.log('siswaExtendedAPI.getAll called with params:', params);
      const response = await api.get('/siswa-extended', { params });
      console.log('siswaExtendedAPI.getAll response:', response);
      return response;
    } catch (error) {
      console.error('Error in siswaExtendedAPI.getAll:', error);
      console.error('Error response:', error.response);
      throw error;
    }
  },
  
  // Get single siswa with extended data
  getById: (id) => api.get(`/siswa-extended/${id}`),
  
  // Update siswa data
  update: (id, data) => api.put(`/siswa-extended/${id}`, data),
  
  // Delete siswa
  delete: (id) => api.delete(`/siswa-extended/${id}`),
  
  // Get riwayat kelas siswa
  getRiwayatKelas: (siswaId) => api.get(`/siswa-extended/${siswaId}/riwayat-kelas`),
  
  // Transisi siswa methods
  naikKelas: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/naik-kelas`, data),

  naikKelasWali: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/naik-kelas/wali`, data),
  
  pindahKelas: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/pindah-kelas`, data),

  requestPindahKelas: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/pindah-kelas/request`, data),

  getTransferRequests: (params = {}) => api.get('/siswa-extended/transfer-requests', { params }),

  approveTransferRequest: (requestId, data = {}) =>
    api.post(`/siswa-extended/transfer-requests/${requestId}/approve`, data),

  rejectTransferRequest: (requestId, data = {}) =>
    api.post(`/siswa-extended/transfer-requests/${requestId}/reject`, data),

  cancelTransferRequest: (requestId, data = {}) =>
    api.post(`/siswa-extended/transfer-requests/${requestId}/cancel`, data),

  getWaliPromotionSettings: (params = {}) =>
    api.get('/siswa-extended/wali-promotion-settings', { params }),

  upsertWaliPromotionSetting: (data) =>
    api.put('/siswa-extended/wali-promotion-settings', data),
  
  lulusSiswa: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/lulus`, data),
  
  keluarSiswa: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/keluar`, data),
  
  aktifkanKembali: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/aktifkan-kembali`, data),
  
  // Rollback/Undo operations
  undoTransisi: (siswaId, transisiId) => api.post(`/siswa-extended/${siswaId}/undo-transisi/${transisiId}`),
  
  rollbackToKelas: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/rollback-to-kelas`, data),
  
  batalkanKelulusan: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/batalkan-kelulusan`, data),
  
  kembalikanSiswa: (siswaId, data) => api.post(`/siswa-extended/${siswaId}/kembalikan-siswa`, data),
  
  // Get transisi history
  getRiwayatTransisi: (siswaId) => api.get(`/siswa-extended/${siswaId}/riwayat-transisi`),
  
  getTransisiTerbaru: (siswaId) => api.get(`/siswa-extended/${siswaId}/transisi-terbaru`),
  
  // Bulk operations
  bulkNaikKelas: (data) => api.post('/siswa-extended/bulk/naik-kelas', data),
  
  bulkLulus: (data) => api.post('/siswa-extended/bulk/lulus', data),
  
  bulkKeluar: (data) => api.post('/siswa-extended/bulk/keluar', data),
  
  // Bulk rollback operations
  bulkUndoTransisi: (data) => api.post('/siswa-extended/bulk/undo-transisi', data),
  
  // Statistics
  getStatistik: (params = {}) => api.get('/siswa-extended/statistik', { params }),
  
  // Export data lengkap siswa (halaman Data Siswa Lengkap)
  exportData: (params = {}) => api.get('/siswa-extended/export', {
    params,
    responseType: 'blob',
    headers: {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel',
    },
  }),

  // Import siswa menggunakan model import existing
  importData: async (formData) => {
    try {
      const response = await api.post('/siswa/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        timeout: 600000,
      });

      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },
};

export default siswaExtendedAPI;

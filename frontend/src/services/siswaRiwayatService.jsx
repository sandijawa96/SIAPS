import api from './api';

const siswaRiwayatService = {
  getRiwayatKelas: async (siswaId) => {
    try {
      const response = await api.get(`/siswa-extended/${siswaId}/riwayat-kelas`);
      return response.data;
    } catch (error) {
      console.error('Error fetching riwayat kelas:', error);
      throw error;
    }
  }
};

export default siswaRiwayatService;

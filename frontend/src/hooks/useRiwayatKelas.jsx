import { useState, useCallback } from 'react';
import siswaRiwayatService from '../services/siswaRiwayatService';
import toast from 'react-hot-toast';

export const useRiwayatKelas = () => {
  const [loading, setLoading] = useState(false);
  const [riwayat, setRiwayat] = useState([]);
  const [siswa, setSiswa] = useState(null);
  const [error, setError] = useState(null);

  const fetchRiwayatKelas = useCallback(async (siswaId) => {
    try {
      setLoading(true);
      setError(null);
      const response = await siswaRiwayatService.getRiwayatKelas(siswaId);
      
      if (response.success) {
        setRiwayat(response.data.riwayat);
        setSiswa(response.data.siswa);
      } else {
        setError(response.message || 'Gagal memuat riwayat kelas');
        toast.error(response.message || 'Gagal memuat riwayat kelas');
      }
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Terjadi kesalahan saat memuat riwayat kelas';
      setError(errorMessage);
      toast.error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    loading,
    riwayat,
    siswa,
    error,
    fetchRiwayatKelas
  };
};

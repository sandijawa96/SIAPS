import { useState } from 'react';
import { toast } from 'react-hot-toast';
import siswaTransisiService from '../services/siswaTransisiService';

export const useSiswaTransisi = () => {
  const [loading, setLoading] = useState(false);

  // Naik kelas
  const handleNaikKelas = async (siswaId, data) => {
    setLoading(true);
    try {
      const response = await siswaTransisiService.naikKelas(siswaId, data);
      toast.success(response.message || 'Siswa berhasil naik kelas');
      return response;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal memproses naik kelas');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  // Pindah kelas
  const handlePindahKelas = async (siswaId, data) => {
    setLoading(true);
    try {
      const response = await siswaTransisiService.pindahKelas(siswaId, data);
      toast.success(response.message || 'Siswa berhasil pindah kelas');
      return response;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal memproses pindah kelas');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  // Lulus
  const handleLulusSiswa = async (siswaId, data) => {
    setLoading(true);
    try {
      const response = await siswaTransisiService.lulusSiswa(siswaId, data);
      toast.success(response.message || 'Siswa berhasil diluluskan');
      return response;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal memproses kelulusan');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  // Keluar (Drop out)
  const handleKeluarSiswa = async (siswaId, data) => {
    setLoading(true);
    try {
      const response = await siswaTransisiService.keluarSiswa(siswaId, data);
      toast.success(response.message || 'Siswa berhasil dikeluarkan');
      return response;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal memproses pengeluaran siswa');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  // Aktifkan kembali
  const handleAktifkanKembali = async (siswaId, data) => {
    setLoading(true);
    try {
      const response = await siswaTransisiService.aktifkanKembali(siswaId, data);
      toast.success(response.message || 'Siswa berhasil diaktifkan kembali');
      return response;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal mengaktifkan kembali siswa');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  return {
    loading,
    handleNaikKelas,
    handlePindahKelas,
    handleLulusSiswa,
    handleKeluarSiswa,
    handleAktifkanKembali
  };
};

import { useState, useEffect } from 'react';
import { tingkatAPI } from '../services/tingkatService';
import toast from 'react-hot-toast';

export const useTingkatManagement = () => {
  const [loading, setLoading] = useState(true);
  const [tingkatList, setTingkatList] = useState([]);

  // Fetch tingkat data
  useEffect(() => {
    const fetchTingkat = async () => {
      try {
        const response = await tingkatAPI.getAll();
        if (response.data.success && Array.isArray(response.data.data)) {
          setTingkatList(response.data.data);
        } else {
          setTingkatList([]);
        }
      } catch (error) {
        toast.error('Gagal memuat data tingkat');
      } finally {
        setLoading(false);
      }
    };

    fetchTingkat();
  }, []);

  const handleDeleteTingkat = async (id, nama) => {
    try {
      setLoading(true);
      const response = await tingkatAPI.delete(id);
      if (response.data.success) {
        toast.success(response.data.message || 'Tingkat berhasil dihapus');
        const refreshResponse = await tingkatAPI.getAll();
        if (refreshResponse.data.success && Array.isArray(refreshResponse.data.data)) {
          setTingkatList(refreshResponse.data.data);
        } else {
          setTingkatList([]);
        }
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal menghapus tingkat');
    } finally {
      setLoading(false);
    }
  };

  const refreshTingkat = async () => {
    try {
      setLoading(true);
      const response = await tingkatAPI.getAll();
      if (response.data.success && Array.isArray(response.data.data)) {
        setTingkatList(response.data.data);
      }
    } catch (error) {
      toast.error('Gagal memperbarui data tingkat');
    } finally {
      setLoading(false);
    }
  };

  return {
    loading,
    tingkatList,
    handleDeleteTingkat,
    refreshTingkat
  };
};

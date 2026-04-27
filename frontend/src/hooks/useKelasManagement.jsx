import { useState, useEffect, useCallback } from 'react';
import { kelasAPI } from '../services/kelasService';
import { tahunAjaranAPI, TAHUN_AJARAN_STATUS } from '../services/tahunAjaranService';
import toast from 'react-hot-toast';

export const useKelasManagement = () => {
  const [loading, setLoading] = useState(true);
  const [kelasList, setKelasList] = useState([]);
  const [activeTahunAjaran, setActiveTahunAjaran] = useState(null);
  const [selectedTahunAjaran, setSelectedTahunAjaran] = useState(null);
  const [tahunAjaranList, setTahunAjaranList] = useState([]);
  const [viewMode, setViewMode] = useState('active'); // active, selected, can_manage, all
  const [searchTerm, setSearchTerm] = useState('');
  const [error, setError] = useState(null);

  // Fetch tahun ajaran data
  const fetchTahunAjaranData = useCallback(async () => {
    try {
      setError(null);
      
      // Fetch active tahun ajaran
      const activeResponse = await tahunAjaranAPI.getAll({ 
        status: TAHUN_AJARAN_STATUS.ACTIVE, 
        no_pagination: true 
      });
      const activeTahunAjaranData = Array.isArray(activeResponse.data) ? activeResponse.data : 
                                   (activeResponse.data?.data ? activeResponse.data.data : []);
      
      if (activeTahunAjaranData.length > 0) {
        setActiveTahunAjaran(activeTahunAjaranData[0]);
        if (!selectedTahunAjaran) {
          setSelectedTahunAjaran(activeTahunAjaranData[0]);
        }
      } else {
        setActiveTahunAjaran(null);
        if (viewMode === 'active') {
          console.warn('Tidak ada tahun ajaran aktif');
        }
      }

      // Fetch all tahun ajaran that can manage classes
      const allResponse = await tahunAjaranAPI.getAll({ 
        can_manage_classes: true, 
        no_pagination: true 
      });
      const allTahunAjaranData = Array.isArray(allResponse.data) ? allResponse.data : 
                                (allResponse.data?.data ? allResponse.data.data : []);
      
      setTahunAjaranList(allTahunAjaranData);

      // Set selected tahun ajaran if not set and we have data
      if (!selectedTahunAjaran && allTahunAjaranData.length > 0) {
        const activeTA = allTahunAjaranData.find(ta => ta.status === TAHUN_AJARAN_STATUS.ACTIVE);
        setSelectedTahunAjaran(activeTA || allTahunAjaranData[0]);
      }
    } catch (error) {
      console.error('Error fetching tahun ajaran:', error);
      setError('Gagal memuat tahun ajaran');
      toast.error('Gagal memuat tahun ajaran');
    }
  }, [selectedTahunAjaran, viewMode]);

  // Fetch kelas data based on view mode
  const fetchKelas = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      let params = {};
      
      switch (viewMode) {
        case 'active':
          if (activeTahunAjaran?.id) {
            params.tahun_ajaran_id = activeTahunAjaran.id;
          } else {
            setKelasList([]);
            setLoading(false);
            return;
          }
          break;
        case 'selected':
          if (selectedTahunAjaran?.id) {
            params.tahun_ajaran_id = selectedTahunAjaran.id;
          } else {
            setKelasList([]);
            setLoading(false);
            return;
          }
          break;
        case 'can_manage':
          params.can_manage_classes = true;
          break;
        case 'all':
          // No additional filters
          break;
        default:
          if (activeTahunAjaran?.id) {
            params.tahun_ajaran_id = activeTahunAjaran.id;
          }
          break;
      }

      const response = await kelasAPI.getAll(params);
      
      if (Array.isArray(response.data)) {
        setKelasList(response.data);
      } else if (response.data?.data && Array.isArray(response.data.data)) {
        setKelasList(response.data.data);
      } else {
        setKelasList([]);
      }
    } catch (error) {
      console.error('Error fetching kelas:', error);
      setError('Gagal memuat data kelas');
      toast.error('Gagal memuat data kelas');
      setKelasList([]);
    } finally {
      setLoading(false);
    }
  }, [activeTahunAjaran, selectedTahunAjaran, viewMode]);

  // Initial data fetch
  useEffect(() => {
    fetchTahunAjaranData();
  }, [fetchTahunAjaranData]);

  useEffect(() => {
    fetchKelas();
  }, [fetchKelas]);

  const handleDeleteKelas = useCallback(async (id, namaKelas) => {
    try {
      setLoading(true);
      const response = await kelasAPI.delete(id);
      
      if (response.data.success) {
        toast.success(response.data.message || 'Kelas berhasil dihapus');
        await fetchKelas(); // Refresh data
      } else {
        throw new Error(response.data.message || 'Gagal menghapus kelas');
      }
    } catch (error) {
      console.error('Error deleting kelas:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal menghapus kelas');
    } finally {
      setLoading(false);
    }
  }, [fetchKelas]);

  const refreshKelas = useCallback(async () => {
    await fetchKelas();
  }, [fetchKelas]);

  const refreshAll = useCallback(async () => {
    await fetchTahunAjaranData();
    await fetchKelas();
  }, [fetchTahunAjaranData, fetchKelas]);

  return {
    loading,
    kelasList,
    activeTahunAjaran,
    selectedTahunAjaran,
    setSelectedTahunAjaran,
    tahunAjaranList,
    viewMode,
    setViewMode,
    searchTerm,
    error,
    setSearchTerm,
    handleDeleteKelas,
    refreshKelas,
    refreshAll,
    fetchKelas
  };
};

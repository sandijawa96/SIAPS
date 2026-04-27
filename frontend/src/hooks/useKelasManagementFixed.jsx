import { useState, useEffect, useCallback, useRef } from 'react';
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
  
  // Refs to prevent infinite loops
  const isInitialized = useRef(false);
  const lastFetchParams = useRef(null);
  const fetchingRef = useRef(false);

  // Fetch tahun ajaran data
  const fetchTahunAjaranData = useCallback(async () => {
    if (fetchingRef.current) return;
    
    try {
      fetchingRef.current = true;
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
      } else {
        setActiveTahunAjaran(null);
      }

      // Fetch all tahun ajaran that can manage classes
      const allResponse = await tahunAjaranAPI.getAll({ 
        can_manage_classes: true, 
        no_pagination: true 
      });
      const allTahunAjaranData = Array.isArray(allResponse.data) ? allResponse.data : 
                                (allResponse.data?.data ? allResponse.data.data : []);
      
      setTahunAjaranList(allTahunAjaranData);

      // Set selected tahun ajaran only if not already set
      if (!selectedTahunAjaran && allTahunAjaranData.length > 0) {
        const activeTA = allTahunAjaranData.find(ta => ta.status === TAHUN_AJARAN_STATUS.ACTIVE);
        setSelectedTahunAjaran(activeTA || allTahunAjaranData[0]);
      }
    } catch (error) {
      console.error('Error fetching tahun ajaran:', error);
      setError('Gagal memuat tahun ajaran');
      toast.error('Gagal memuat tahun ajaran');
    } finally {
      fetchingRef.current = false;
    }
  }, []); // No dependencies to prevent loops

  // Fetch kelas data based on view mode
  const fetchKelas = useCallback(async () => {
    if (fetchingRef.current) return;
    
    try {
      setLoading(true);
      setError(null);
      fetchingRef.current = true;
      
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

      // Prevent duplicate requests with same parameters
      const paramsKey = JSON.stringify(params);
      if (lastFetchParams.current === paramsKey) {
        setLoading(false);
        return;
      }
      lastFetchParams.current = paramsKey;

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
      fetchingRef.current = false;
    }
  }, [activeTahunAjaran?.id, selectedTahunAjaran?.id, viewMode]);

  // Initial data fetch - only once
  useEffect(() => {
    if (!isInitialized.current) {
      isInitialized.current = true;
      fetchTahunAjaranData();
    }
  }, [fetchTahunAjaranData]);

  // Fetch kelas when dependencies change
  useEffect(() => {
    if (isInitialized.current && (activeTahunAjaran || selectedTahunAjaran)) {
      fetchKelas();
    }
  }, [fetchKelas]);

  const handleDeleteKelas = useCallback(async (id, namaKelas) => {
    try {
      setLoading(true);
      const response = await kelasAPI.delete(id);
      
      if (response.data.success) {
        toast.success(response.data.message || 'Kelas berhasil dihapus');
        // Reset fetch params to allow refresh
        lastFetchParams.current = null;
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
    lastFetchParams.current = null; // Reset to allow refresh
    await fetchKelas();
  }, [fetchKelas]);

  const refreshAll = useCallback(async () => {
    lastFetchParams.current = null; // Reset to allow refresh
    await fetchTahunAjaranData();
    await fetchKelas();
  }, [fetchTahunAjaranData, fetchKelas]);

  // Enhanced method to change view mode
  const changeViewMode = useCallback((newViewMode) => {
    setViewMode(newViewMode);
    lastFetchParams.current = null; // Reset to allow new fetch
  }, []);

  // Enhanced method to select tahun ajaran
  const selectTahunAjaran = useCallback((tahunAjaran) => {
    setSelectedTahunAjaran(tahunAjaran);
    if (viewMode !== 'selected') {
      setViewMode('selected');
    }
    lastFetchParams.current = null; // Reset to allow new fetch
  }, [viewMode]);

  // Get target tahun ajaran for kelas creation
  const getTargetTahunAjaran = useCallback(() => {
    switch (viewMode) {
      case 'selected':
        return selectedTahunAjaran;
      case 'active':
      default:
        return activeTahunAjaran;
    }
  }, [viewMode, selectedTahunAjaran, activeTahunAjaran]);

  // Check if can create kelas for current target
  const canCreateKelas = useCallback(() => {
    const target = getTargetTahunAjaran();
    if (!target) return false;
    
    // Can create kelas for draft, preparation, and active status
    return ['draft', 'preparation', 'active'].includes(target.status);
  }, [getTargetTahunAjaran]);

  return {
    loading,
    kelasList,
    activeTahunAjaran,
    selectedTahunAjaran,
    setSelectedTahunAjaran: selectTahunAjaran,
    tahunAjaranList,
    viewMode,
    setViewMode: changeViewMode,
    searchTerm,
    error,
    setSearchTerm,
    handleDeleteKelas,
    refreshKelas,
    refreshAll,
    fetchKelas,
    getTargetTahunAjaran,
    canCreateKelas
  };
};

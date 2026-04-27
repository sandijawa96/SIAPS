import { useState, useEffect, useCallback } from 'react';
import { tahunAjaranAPI, TAHUN_AJARAN_STATUS } from '../services/tahunAjaranService';
import toast from 'react-hot-toast';

export const useTahunAjaranManagement = () => {
  const [loading, setLoading] = useState(true);
  const [tahunAjaranList, setTahunAjaranList] = useState([]);
  const [activeTahunAjaran, setActiveTahunAjaran] = useState(null);
  const [selectedTahunAjaran, setSelectedTahunAjaran] = useState(null);
  const [viewMode, setViewMode] = useState('all'); // active, draft, preparation, all
  const [error, setError] = useState(null);

  // Fetch tahun ajaran based on view mode
  const fetchTahunAjaran = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      let params = { no_pagination: true };

      switch (viewMode) {
        case 'active':
          params.status = TAHUN_AJARAN_STATUS.ACTIVE;
          break;
        case 'draft':
          params.status = TAHUN_AJARAN_STATUS.DRAFT;
          break;
        case 'preparation':
          params.status = TAHUN_AJARAN_STATUS.PREPARATION;
          break;
        case 'can_manage':
          params.can_manage_classes = true;
          break;
        case 'all':
        default:
          // No additional filters
          break;
      }

      const response = await tahunAjaranAPI.getAll(params);
      const data = Array.isArray(response.data) ? response.data : 
                   (response.data?.data ? response.data.data : []);
      
      setTahunAjaranList(data);

      // Set active tahun ajaran
      const activeTA = data.find(ta => ta.status === TAHUN_AJARAN_STATUS.ACTIVE);
      setActiveTahunAjaran(activeTA || null);

      // Set selected tahun ajaran if not set
      if (!selectedTahunAjaran && data.length > 0) {
        setSelectedTahunAjaran(activeTA || data[0]);
      }

    } catch (error) {
      console.error('Error fetching tahun ajaran:', error);
      setError('Gagal memuat data tahun ajaran');
      toast.error('Gagal memuat data tahun ajaran');
      setTahunAjaranList([]);
    } finally {
      setLoading(false);
    }
  }, [viewMode, selectedTahunAjaran]);

  // Initial fetch
  useEffect(() => {
    fetchTahunAjaran();
  }, [fetchTahunAjaran]);

  // Create tahun ajaran
  const createTahunAjaran = useCallback(async (data) => {
    try {
      setLoading(true);
      const response = await tahunAjaranAPI.create(data);
      
      if (response.success) {
        toast.success(response.message || 'Tahun ajaran berhasil dibuat');
        await fetchTahunAjaran();
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal membuat tahun ajaran');
      }
    } catch (error) {
      console.error('Error creating tahun ajaran:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal membuat tahun ajaran');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [fetchTahunAjaran]);

  // Update tahun ajaran
  const updateTahunAjaran = useCallback(async (id, data) => {
    try {
      setLoading(true);
      const response = await tahunAjaranAPI.update(id, data);
      
      if (response.success) {
        toast.success(response.message || 'Tahun ajaran berhasil diperbarui');
        await fetchTahunAjaran();
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal memperbarui tahun ajaran');
      }
    } catch (error) {
      console.error('Error updating tahun ajaran:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal memperbarui tahun ajaran');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [fetchTahunAjaran]);

  // Delete tahun ajaran
  const deleteTahunAjaran = useCallback(async (id) => {
    try {
      setLoading(true);
      const response = await tahunAjaranAPI.delete(id);
      
      if (response.success) {
        toast.success(response.message || 'Tahun ajaran berhasil dihapus');
        await fetchTahunAjaran();
        return true;
      } else {
        throw new Error(response.message || 'Gagal menghapus tahun ajaran');
      }
    } catch (error) {
      console.error('Error deleting tahun ajaran:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal menghapus tahun ajaran');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [fetchTahunAjaran]);

  // Transition status
  const transitionStatus = useCallback(async (id, newStatus, metadata = {}) => {
    try {
      setLoading(true);
      const response = await tahunAjaranAPI.transitionStatus(id, newStatus, metadata);
      
      if (response.success) {
        toast.success(response.message || `Status berhasil diubah ke ${newStatus}`);
        await fetchTahunAjaran();
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal mengubah status');
      }
    } catch (error) {
      console.error('Error transitioning status:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal mengubah status');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [fetchTahunAjaran]);

  // Update progress
  const updateProgress = useCallback(async (id, progress, metadata = {}) => {
    try {
      const response = await tahunAjaranAPI.updateProgress(id, progress, metadata);
      
      if (response.success) {
        toast.success(response.message || 'Progress berhasil diperbarui');
        await fetchTahunAjaran();
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal memperbarui progress');
      }
    } catch (error) {
      console.error('Error updating progress:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal memperbarui progress');
      throw error;
    }
  }, [fetchTahunAjaran]);

  // Get tahun ajaran that can manage classes
  const getCanManageClasses = useCallback(async () => {
    try {
      const response = await tahunAjaranAPI.getCanManageClasses();
      return Array.isArray(response.data) ? response.data : 
             (response.data?.data ? response.data.data : []);
    } catch (error) {
      console.error('Error getting manageable tahun ajaran:', error);
      return [];
    }
  }, []);

  // Refresh data
  const refresh = useCallback(async () => {
    await fetchTahunAjaran();
  }, [fetchTahunAjaran]);

  // Helper functions
  const canCreateClasses = useCallback((tahunAjaran) => {
    if (!tahunAjaran) return false;
    return [
      TAHUN_AJARAN_STATUS.DRAFT,
      TAHUN_AJARAN_STATUS.PREPARATION,
      TAHUN_AJARAN_STATUS.ACTIVE
    ].includes(tahunAjaran.status);
  }, []);

  const canTransitionTo = useCallback((tahunAjaran, newStatus) => {
    if (!tahunAjaran) return false;
    
    const allowedTransitions = {
      [TAHUN_AJARAN_STATUS.DRAFT]: [TAHUN_AJARAN_STATUS.PREPARATION],
      [TAHUN_AJARAN_STATUS.PREPARATION]: [TAHUN_AJARAN_STATUS.ACTIVE, TAHUN_AJARAN_STATUS.DRAFT],
      [TAHUN_AJARAN_STATUS.ACTIVE]: [TAHUN_AJARAN_STATUS.COMPLETED],
      [TAHUN_AJARAN_STATUS.COMPLETED]: [TAHUN_AJARAN_STATUS.ARCHIVED],
      [TAHUN_AJARAN_STATUS.ARCHIVED]: []
    };

    return allowedTransitions[tahunAjaran.status]?.includes(newStatus) || false;
  }, []);

  const isReadyToActivate = useCallback((tahunAjaran) => {
    if (!tahunAjaran) return false;
    return tahunAjaran.status === TAHUN_AJARAN_STATUS.PREPARATION &&
           tahunAjaran.preparation_progress === 100 &&
           tahunAjaran.is_ready_to_activate;
  }, []);

  return {
    // State
    loading,
    tahunAjaranList,
    activeTahunAjaran,
    selectedTahunAjaran,
    viewMode,
    error,

    // Actions
    setSelectedTahunAjaran,
    setViewMode,
    createTahunAjaran,
    updateTahunAjaran,
    deleteTahunAjaran,
    transitionStatus,
    updateProgress,
    refresh,

    // Helpers
    canCreateClasses,
    canTransitionTo,
    isReadyToActivate,
    getCanManageClasses,

    // Fetch function
    fetchTahunAjaran
  };
};

export default useTahunAjaranManagement;

import { useState, useEffect, useCallback, useRef } from 'react';
import { kelasAPI } from '../services/kelasService';
import { tahunAjaranAPI, TAHUN_AJARAN_STATUS } from '../services/tahunAjaranService';
import { getServerIsoString } from '../services/serverClock';
import toast from 'react-hot-toast';
import { useAuth } from './useAuth';

// Custom hook untuk realtime state management
export const useRealtimeKelasManagement = () => {
  const { hasPermission } = useAuth();
  const canReadKelas = hasPermission('view_kelas') || hasPermission('manage_kelas');

  // State management
  const [state, setState] = useState({
    loading: true,
    kelasList: [],
    activeTahunAjaran: null,
    selectedTahunAjaran: null,
    tahunAjaranList: [],
    viewMode: 'active',
    searchTerm: '',
    error: null,
    lastUpdated: null,
    isRefreshing: false
  });

  // Refs untuk optimasi
  const isInitialized = useRef(false);
  const lastFetchParams = useRef(null);
  const fetchingRef = useRef(false);
  const intervalRef = useRef(null);
  const retryTimeoutRef = useRef(null);

  // Update state dengan optimistic updates
  const updateState = useCallback((updates) => {
    setState(prevState => ({
      ...prevState,
      ...updates,
      lastUpdated: getServerIsoString()
    }));
  }, []);

  // Fetch tahun ajaran data dengan error handling
  const fetchTahunAjaranData = useCallback(async (showLoading = true) => {
    if (fetchingRef.current) return;
    
    try {
      fetchingRef.current = true;
      if (showLoading) {
        updateState({ loading: true, error: null });
      }
      
      // Fetch active tahun ajaran
      const activeResponse = await tahunAjaranAPI.getAll({ 
        status: TAHUN_AJARAN_STATUS.ACTIVE, 
        no_pagination: true 
      });
      const activeTahunAjaranData = Array.isArray(activeResponse.data) ? activeResponse.data : 
                                   (activeResponse.data?.data ? activeResponse.data.data : []);
      
      // Fetch all tahun ajaran that can manage classes
      const allResponse = await tahunAjaranAPI.getAll({ 
        can_manage_classes: true, 
        no_pagination: true 
      });
      const allTahunAjaranData = Array.isArray(allResponse.data) ? allResponse.data : 
                                (allResponse.data?.data ? allResponse.data.data : []);
      
      const activeTahunAjaran = activeTahunAjaranData.length > 0 ? activeTahunAjaranData[0] : null;
      
      updateState({
        activeTahunAjaran,
        tahunAjaranList: allTahunAjaranData,
        selectedTahunAjaran: state.selectedTahunAjaran || 
          (allTahunAjaranData.find(ta => ta.status === TAHUN_AJARAN_STATUS.ACTIVE) || allTahunAjaranData[0]),
        error: null
      });

    } catch (error) {
      console.error('Error fetching tahun ajaran:', error);
      updateState({ 
        error: 'Gagal memuat tahun ajaran',
        activeTahunAjaran: null,
        tahunAjaranList: []
      });
      
      if (showLoading) {
        toast.error('Gagal memuat tahun ajaran');
      }
    } finally {
      fetchingRef.current = false;
      if (showLoading) {
        updateState({ loading: false });
      }
    }
  }, [state.selectedTahunAjaran, updateState]);

  // Fetch kelas data dengan realtime updates
  const fetchKelas = useCallback(async (showLoading = true, isBackground = false) => {
    if (!canReadKelas) {
      updateState({
        kelasList: [],
        error: null,
        loading: false,
        isRefreshing: false
      });
      return;
    }

    if (fetchingRef.current && !isBackground) return;
    
    try {
      if (!isBackground) {
        fetchingRef.current = true;
      }
      
      if (showLoading && !isBackground) {
        updateState({ loading: true, error: null });
      } else if (isBackground) {
        updateState({ isRefreshing: true });
      }
      
      let params = {};
      
      switch (state.viewMode) {
        case 'active':
          if (state.activeTahunAjaran?.id) {
            params.tahun_ajaran_id = state.activeTahunAjaran.id;
          } else {
            updateState({ kelasList: [], loading: false, isRefreshing: false });
            return;
          }
          break;
        case 'selected':
          if (state.selectedTahunAjaran?.id) {
            params.tahun_ajaran_id = state.selectedTahunAjaran.id;
          } else {
            updateState({ kelasList: [], loading: false, isRefreshing: false });
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
          if (state.activeTahunAjaran?.id) {
            params.tahun_ajaran_id = state.activeTahunAjaran.id;
          }
          break;
      }

      // Prevent duplicate requests dengan same parameters (kecuali background refresh)
      const paramsKey = JSON.stringify(params);
      if (!isBackground && lastFetchParams.current === paramsKey) {
        updateState({ loading: false, isRefreshing: false });
        return;
      }
      
      if (!isBackground) {
        lastFetchParams.current = paramsKey;
      }

      const response = await kelasAPI.getAll(params);
      
      let newKelasList = [];
      if (Array.isArray(response.data)) {
        newKelasList = response.data;
      } else if (response.data?.data && Array.isArray(response.data.data)) {
        newKelasList = response.data.data;
      }

      updateState({
        kelasList: newKelasList,
        error: null,
        loading: false,
        isRefreshing: false
      });

    } catch (error) {
      if (error?.response?.status === 403) {
        updateState({
          error: null,
          kelasList: [],
          loading: false,
          isRefreshing: false
        });
        return;
      }

      console.error('Error fetching kelas:', error);
      updateState({ 
        error: 'Gagal memuat data kelas',
        kelasList: [],
        loading: false,
        isRefreshing: false
      });
      
      if (!isBackground) {
        toast.error('Gagal memuat data kelas');
      }
    } finally {
      if (!isBackground) {
        fetchingRef.current = false;
      }
    }
  }, [canReadKelas, state.viewMode, state.activeTahunAjaran?.id, state.selectedTahunAjaran?.id, updateState]);

  // Setup realtime polling
  const startRealtimeUpdates = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
    }

    intervalRef.current = setInterval(() => {
      // Background refresh setiap 30 detik
      fetchKelas(false, true);
    }, 30000);
  }, [fetchKelas]);

  const stopRealtimeUpdates = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
  }, []);

  // Initial data fetch
  useEffect(() => {
    if (!canReadKelas) {
      updateState({
        loading: false,
        error: null,
        kelasList: [],
        isRefreshing: false
      });
      return;
    }

    if (!isInitialized.current) {
      isInitialized.current = true;
      fetchTahunAjaranData();
    }
  }, [canReadKelas, fetchTahunAjaranData, updateState]);

  // Fetch kelas when dependencies change
  useEffect(() => {
    if (canReadKelas && isInitialized.current && (state.activeTahunAjaran || state.selectedTahunAjaran)) {
      fetchKelas();
    }
  }, [canReadKelas, fetchKelas, state.activeTahunAjaran, state.selectedTahunAjaran]);

  // Start realtime updates when component mounts
  useEffect(() => {
    if (!canReadKelas) {
      stopRealtimeUpdates();
      return;
    }

    startRealtimeUpdates();
    
    // Cleanup on unmount
    return () => {
      stopRealtimeUpdates();
      if (retryTimeoutRef.current) {
        clearTimeout(retryTimeoutRef.current);
      }
    };
  }, [canReadKelas, startRealtimeUpdates, stopRealtimeUpdates]);

  // Handle visibility change untuk pause/resume updates
  useEffect(() => {
    if (!canReadKelas) {
      return;
    }

    const handleVisibilityChange = () => {
      if (document.hidden) {
        stopRealtimeUpdates();
      } else {
        startRealtimeUpdates();
        // Refresh data when page becomes visible
        fetchKelas(false, true);
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [canReadKelas, startRealtimeUpdates, stopRealtimeUpdates, fetchKelas]);

  // Action handlers dengan optimistic updates
  const handleDeleteKelas = useCallback(async (id, namaKelas) => {
    try {
      // Optimistic update - remove from list immediately
      const originalList = state.kelasList;
      updateState({
        kelasList: state.kelasList.filter(kelas => kelas.id !== id),
        loading: true
      });

      const response = await kelasAPI.delete(id);
      
      if (response.data.success) {
        toast.success(response.data.message || 'Kelas berhasil dihapus');
        // Refresh to get latest data
        lastFetchParams.current = null;
        await fetchKelas(false);
      } else {
        throw new Error(response.data.message || 'Gagal menghapus kelas');
      }
    } catch (error) {
      // Revert optimistic update on error
      updateState({ kelasList: originalList, loading: false });
      console.error('Error deleting kelas:', error);
      toast.error(error.response?.data?.message || error.message || 'Gagal menghapus kelas');
    }
  }, [state.kelasList, updateState, fetchKelas]);

  const refreshKelas = useCallback(async () => {
    lastFetchParams.current = null;
    await fetchKelas();
  }, [fetchKelas]);

  const refreshAll = useCallback(async () => {
    lastFetchParams.current = null;
    await fetchTahunAjaranData();
    await fetchKelas();
  }, [fetchTahunAjaranData, fetchKelas]);

  // Enhanced method to change view mode
  const changeViewMode = useCallback((newViewMode) => {
    updateState({ viewMode: newViewMode });
    lastFetchParams.current = null;
  }, [updateState]);

  // Enhanced method to select tahun ajaran
  const selectTahunAjaran = useCallback((tahunAjaran) => {
    updateState({ 
      selectedTahunAjaran: tahunAjaran,
      viewMode: state.viewMode !== 'selected' ? 'selected' : state.viewMode
    });
    lastFetchParams.current = null;
  }, [state.viewMode, updateState]);

  // Set search term dengan debouncing
  const setSearchTerm = useCallback((term) => {
    updateState({ searchTerm: term });
  }, [updateState]);

  // Get target tahun ajaran for kelas creation
  const getTargetTahunAjaran = useCallback(() => {
    switch (state.viewMode) {
      case 'selected':
        return state.selectedTahunAjaran;
      case 'active':
      default:
        return state.activeTahunAjaran;
    }
  }, [state.viewMode, state.selectedTahunAjaran, state.activeTahunAjaran]);

  // Check if can create kelas for current target
  const canCreateKelas = useCallback(() => {
    const target = getTargetTahunAjaran();
    if (!target) return false;
    
    return ['draft', 'preparation', 'active'].includes(target.status);
  }, [getTargetTahunAjaran]);

  return {
    // State
    ...state,
    
    // Actions
    setSearchTerm,
    setViewMode: changeViewMode,
    setSelectedTahunAjaran: selectTahunAjaran,
    handleDeleteKelas,
    refreshKelas,
    refreshAll,
    fetchKelas,
    getTargetTahunAjaran,
    canCreateKelas,
    
    // Realtime controls
    startRealtimeUpdates,
    stopRealtimeUpdates,
    
    // Utilities
    isRealtime: !!intervalRef.current
  };
};

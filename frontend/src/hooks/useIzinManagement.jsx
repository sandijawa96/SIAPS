import { useState, useCallback, useEffect } from 'react';
import { izinService } from '../services/izinService';

const resolveApprovalNote = (payload) => {
  if (typeof payload?.catatan_approval === 'string' && payload.catatan_approval.trim() !== '') {
    return payload.catatan_approval;
  }

  if (typeof payload?.catatan === 'string' && payload.catatan.trim() !== '') {
    return payload.catatan;
  }

  return '';
};

const normalizeIzinListPayload = (payload) => {
  if (payload && Array.isArray(payload.data) && payload.meta) {
    return payload;
  }

  return izinService.normalizeIzinListResponse(payload);
};

export const useIzinStatistics = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [statistics, setStatistics] = useState({
    pending: 0,
    approved: 0,
    rejected: 0
  });

  const fetchStatistics = useCallback(async (type = 'siswa') => {
    try {
      setLoading(true);
      const response = await izinService.getStatistics(type);
      setStatistics(response.data || {
        pending: 0,
        approved: 0,
        rejected: 0
      });
      return response;
    } catch (error) {
      console.error('Error fetching statistics:', error);
      setError(error.message);
      // Set default statistics to prevent UI errors
      setStatistics({
        pending: 0,
        approved: 0,
        rejected: 0
      });
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    loading,
    error,
    statistics,
    fetchStatistics
  };
};

export const useIzinApproval = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [approvalList, setApprovalList] = useState([]);
  const [filters, setFilters] = useState({
    status: 'pending',
    search: '',
    date: null
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 10
  });

  const fetchApprovalList = useCallback(async () => {
    try {
      setLoading(true);
      const response = await izinService.getIzinList({
        ...filters,
        page: pagination.current_page
      });
      const normalized = normalizeIzinListPayload(response);
      setApprovalList(normalized.data || []);
      setPagination(prev => ({
        ...prev,
        current_page: Number(normalized.meta?.current_page || prev.current_page || 1),
        last_page: Number(normalized.meta?.last_page || 1),
        total: Number(normalized.meta?.total || 0),
        per_page: Number(normalized.meta?.per_page || prev.per_page || 10),
      }));
    } catch (error) {
      console.error('Error fetching approval list:', error);
      setError(error.message);
    } finally {
      setLoading(false);
    }
  }, [filters, pagination.current_page]);

  // Fetch data on mount and when filters/page change
  useEffect(() => {
    fetchApprovalList();
  }, [fetchApprovalList]);

  const updateFilters = useCallback((newFilters) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
    setPagination(prev => ({ ...prev, current_page: 1 }));
  }, []);

  const clearFilters = useCallback(() => {
    setFilters({
      status: 'pending',
      search: '',
      date: null
    });
    setPagination(prev => ({ ...prev, current_page: 1 }));
  }, []);

  const changePage = useCallback((page) => {
    setPagination(prev => ({ ...prev, current_page: page }));
  }, []);

  const approveIzin = useCallback(async (id, catatan) => {
    try {
      setLoading(true);
      const response = await izinService.approveIzin(id, { catatan });
      
      // Update local data
      setApprovalList(prevData => 
        prevData.map(item => 
          item.id === id 
            ? { ...item, status: 'approved', catatan }
            : item
        )
      );
      
      return response;
    } catch (error) {
      setError(error.message);
      throw error;
    } finally {
      setLoading(false);
    }
  }, []);

  const rejectIzin = useCallback(async (id, catatan) => {
    try {
      setLoading(true);
      const response = await izinService.rejectIzin(id, { catatan });
      
      // Update local data
      setApprovalList(prevData => 
        prevData.map(item => 
          item.id === id 
            ? { ...item, status: 'rejected', catatan }
            : item
        )
      );
      
      return response;
    } catch (error) {
      setError(error.message);
      throw error;
    } finally {
      setLoading(false);
    }
  }, []);

  const downloadDocument = useCallback(async (id, filename) => {
    try {
      const response = await izinService.downloadDocument(id);
      return response;
    } catch (error) {
      setError(error.message);
      throw error;
    }
  }, []);

  return {
    approvalList,
    loading,
    error,
    pagination,
    filters,
    updateFilters,
    clearFilters,
    changePage,
    approveIzin,
    rejectIzin,
    downloadDocument
  };
};

export const useIzinManagement = ({ type = 'siswa', forApproval = false } = {}) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [statistics, setStatistics] = useState({
    pending: 0,
    approved: 0,
    rejected: 0
  });
  const [loadingStats, setLoadingStats] = useState(false);
  const [jenisOptions, setJenisOptions] = useState([]);

  // Fetch izin data
  const fetchData = useCallback(async (params = {}) => {
    try {
      setLoading(true);
      
      let response;
      if (forApproval) {
        // Use approval endpoint for approval pages
        response = await izinService.getForApproval({
          ...params,
          type
        });
      } else {
        // Use regular endpoint for user's own izin
        response = await izinService.getIzinList({
          ...params,
          type
        });
      }

      const normalized = normalizeIzinListPayload(response);
      setData(normalized.data || []);
      return normalized;
    } catch (error) {
      console.error('Error fetching izin data:', error);
      throw new Error(error.response?.data?.message || 'Gagal memuat data izin');
    } finally {
      setLoading(false);
    }
  }, [type, forApproval]);

  // Fetch statistics
  const fetchStatistics = useCallback(async () => {
    try {
      setLoadingStats(true);
      const response = await izinService.getStatistics(type);
      setStatistics(response.data || {
        pending: 0,
        approved: 0,
        rejected: 0
      });
      return response;
    } catch (error) {
      console.error('Error fetching statistics:', error);
      // Set default statistics to prevent UI errors
      setStatistics({
        pending: 0,
        approved: 0,
        rejected: 0
      });
    } finally {
      setLoadingStats(false);
    }
  }, [type]);

  // Fetch jenis izin options
  const fetchJenisOptions = useCallback(async () => {
    try {
      const response = await izinService.getJenisIzinOptions(type);
      setJenisOptions(response.data || []);
      return response;
    } catch (error) {
      console.error('Error fetching jenis options:', error);
      throw new Error(error.response?.data?.message || 'Gagal memuat jenis izin');
    }
  }, [type]);

  // Create izin
  const createIzin = useCallback(async (izinData) => {
    try {
      const response = await izinService.createIzin(izinData);
      
      // Refresh data after creation
      await fetchData();
      await fetchStatistics();
      
      return response;
    } catch (error) {
      console.error('Error creating izin:', error);
      throw new Error(error.response?.data?.message || 'Gagal membuat izin');
    }
  }, [fetchData, fetchStatistics]);

  // Update izin
  const updateIzin = useCallback(async (id, izinData) => {
    try {
      return await izinService.updateIzin(id, izinData);
    } catch (error) {
      console.error('Error updating izin:', error);
      throw new Error(error.response?.data?.message || error.message || 'Gagal mengupdate izin');
    }
  }, []);

  // Delete izin
  const deleteIzin = useCallback(async (id) => {
    try {
      const response = await izinService.deleteIzin(id);
      
      // Refresh data after deletion
      await fetchData();
      await fetchStatistics();
      
      return response;
    } catch (error) {
      console.error('Error deleting izin:', error);
      throw new Error(error.response?.data?.message || 'Gagal menghapus izin');
    }
  }, [fetchData, fetchStatistics]);

  // Approve izin
  const approveIzin = useCallback(async (id, approvalData) => {
    try {
      const response = await izinService.approveIzin(id, approvalData);
      const note = resolveApprovalNote(approvalData);
      
      // Update local data
      setData(prevData => 
        prevData.map(item => 
          item.id === id 
            ? { ...item, status: 'approved', catatan_approval: note }
            : item
        )
      );
      
      // Refresh statistics
      await fetchStatistics();
      
      return response;
    } catch (error) {
      console.error('Error approving izin:', error);
      throw new Error(error.response?.data?.message || 'Gagal menyetujui izin');
    }
  }, [fetchStatistics]);

  // Reject izin
  const rejectIzin = useCallback(async (id, rejectionData) => {
    try {
      const response = await izinService.rejectIzin(id, rejectionData);
      const note = resolveApprovalNote(rejectionData);
      
      // Update local data
      setData(prevData => 
        prevData.map(item => 
          item.id === id 
            ? { ...item, status: 'rejected', catatan_approval: note }
            : item
        )
      );
      
      // Refresh statistics
      await fetchStatistics();
      
      return response;
    } catch (error) {
      console.error('Error rejecting izin:', error);
      throw new Error(error.response?.data?.message || 'Gagal menolak izin');
    }
  }, [fetchStatistics]);

  // Get izin detail
  const getIzinDetail = useCallback(async (id) => {
    try {
      const response = await izinService.getIzinDetail(id);
      return response;
    } catch (error) {
      console.error('Error fetching izin detail:', error);
      throw new Error(error.response?.data?.message || 'Gagal memuat detail izin');
    }
  }, []);

  // Download document
  const downloadDocument = useCallback(async (id) => {
    try {
      const response = await izinService.downloadDocument(id);
      return response;
    } catch (error) {
      console.error('Error downloading document:', error);
      throw new Error(error.response?.data?.message || 'Gagal mengunduh dokumen');
    }
  }, []);

  return {
    // Data
    data,
    loading,
    statistics,
    loadingStats,
    jenisOptions,

    // Actions
    fetchData,
    fetchStatistics,
    fetchJenisOptions,
    createIzin,
    updateIzin,
    deleteIzin,
    approveIzin,
    rejectIzin,
    getIzinDetail,
    downloadDocument
  };
};

export default useIzinManagement;

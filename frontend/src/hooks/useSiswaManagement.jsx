import { useState, useEffect, useCallback } from 'react';
import siswaService from '../services/siswaService';
import { toast } from 'react-hot-toast';

export const useSiswaManagement = () => {
  const [siswa, setSiswa] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  });
  const [filters, setFilters] = useState({
    search: '',
    kelas_id: '',
    is_active: '',
    per_page: 15
  });

  // Fetch siswa data
  const fetchSiswa = useCallback(async (params = {}) => {
    setLoading(true);
    try {
      const mergedParams = { ...filters, ...params };
      const response = await siswaService.getAll(mergedParams);
      
      if (response.success) {
        setSiswa(response.data.data || []);
        setPagination({
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          per_page: response.data.per_page || 15,
          total: response.data.total || 0
        });
      }
    } catch (error) {
      console.error('Error fetching siswa:', error);
      toast.error(error.message || 'Gagal memuat data siswa');
      setSiswa([]);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  // Create siswa
  const createSiswa = async (data) => {
    try {
      const response = await siswaService.create(data);
      if (response.success) {
        toast.success('Siswa berhasil ditambahkan');
        await fetchSiswa();
        return response;
      }
    } catch (error) {
      console.error('Error creating siswa:', error);
      toast.error(error.message || 'Gagal menambahkan siswa');
      throw error;
    }
  };

  // Update siswa
  const updateSiswa = async (id, data) => {
    try {
      const response = await siswaService.update(id, data);
      if (response.success) {
        toast.success('Data siswa berhasil diupdate');
        await fetchSiswa();
        return response;
      }
    } catch (error) {
      console.error('Error updating siswa:', error);
      toast.error(error.message || 'Gagal mengupdate siswa');
      throw error;
    }
  };

  // Delete siswa
  const deleteSiswa = async (id) => {
    try {
      const response = await siswaService.delete(id);
      if (response.success) {
        toast.success('Siswa berhasil dihapus');
        await fetchSiswa();
        return response;
      }
    } catch (error) {
      console.error('Error deleting siswa:', error);
      toast.error(error.message || 'Gagal menghapus siswa');
      throw error;
    }
  };

  // Reset password siswa
  const resetPassword = async (id, data) => {
    try {
      const response = await siswaService.resetPassword(id, data);
      if (response.success) {
        toast.success('Password siswa berhasil direset');
        return response;
      }
    } catch (error) {
      console.error('Error resetting password:', error);
      toast.error(error.message || 'Gagal reset password');
      throw error;
    }
  };

  // Import siswa
  const importSiswa = async (file) => {
    try {
      const response = await siswaService.impor(file);
      if (response.success) {
        toast.success(`Import berhasil! ${response.data?.imported || 0} siswa ditambahkan`);
        await fetchSiswa();
        return response;
      } else {
        // Handle partial success with errors
        if (response.data?.errors && response.data.errors.length > 0) {
          toast.error(`Import selesai dengan ${response.data.errors.length} error`);
        }
        return response;
      }
    } catch (error) {
      console.error('Error importing siswa:', error);
      toast.error(error.message || 'Gagal import siswa');
      throw error;
    }
  };

  // Export siswa
  const exportSiswa = async () => {
    try {
      const response = await siswaService.ekspor();
      if (response.success) {
        toast.success('Export berhasil');
        return response;
      }
    } catch (error) {
      console.error('Error exporting siswa:', error);
      toast.error(error.message || 'Gagal export siswa');
      throw error;
    }
  };

  // Download template
  const downloadTemplate = async () => {
    try {
      const response = await siswaService.downloadTemplate();
      if (response.success) {
        toast.success('Template berhasil didownload');
        return response;
      }
    } catch (error) {
      console.error('Error downloading template:', error);
      toast.error(error.message || 'Gagal download template');
      throw error;
    }
  };

  // Update filters
  const updateFilters = useCallback((newFilters) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
  }, []);

  // Reset filters
  const resetFilters = useCallback(() => {
    setFilters({
      search: '',
      kelas_id: '',
      is_active: '',
      per_page: 15
    });
  }, []);

  // Handle pagination
  const handlePageChange = useCallback((page) => {
    fetchSiswa({ page });
  }, [fetchSiswa]);

  // Handle per page change
  const handlePerPageChange = useCallback((perPage) => {
    updateFilters({ per_page: perPage });
    fetchSiswa({ per_page: perPage, page: 1 });
  }, [updateFilters, fetchSiswa]);

  // Search siswa
  const searchSiswa = useCallback((searchTerm) => {
    updateFilters({ search: searchTerm });
    fetchSiswa({ search: searchTerm, page: 1 });
  }, [updateFilters, fetchSiswa]);

  // Filter by kelas
  const filterByKelas = useCallback((kelasId) => {
    updateFilters({ kelas_id: kelasId });
    fetchSiswa({ kelas_id: kelasId, page: 1 });
  }, [updateFilters, fetchSiswa]);

  // Filter by status
  const filterByStatus = useCallback((status) => {
    updateFilters({ is_active: status });
    fetchSiswa({ is_active: status, page: 1 });
  }, [updateFilters, fetchSiswa]);

  // Initial load
  useEffect(() => {
    fetchSiswa();
  }, []);

  // Refetch when filters change
  useEffect(() => {
    fetchSiswa();
  }, [filters]);

  return {
    // Data
    siswa,
    loading,
    pagination,
    filters,

    // Actions
    fetchSiswa,
    createSiswa,
    updateSiswa,
    deleteSiswa,
    resetPassword,
    importSiswa,
    exportSiswa,
    downloadTemplate,

    // Filter actions
    updateFilters,
    resetFilters,
    searchSiswa,
    filterByKelas,
    filterByStatus,

    // Pagination actions
    handlePageChange,
    handlePerPageChange
  };
};

export default useSiswaManagement;

import { useState, useCallback } from 'react';
import { useSnackbar } from 'notistack';
import pegawaiService from '../services/pegawaiService.jsx';
import siswaService from '../services/siswaService.jsx';

const buildDefaultFilters = (tab = 'pegawai') => ({
  search: '',
  role: tab === 'pegawai' ? '' : undefined,
  sub_role: tab === 'pegawai' ? '' : undefined,
  tahun_ajaran_id: tab === 'siswa' ? '' : undefined,
  tingkat_id: tab === 'siswa' ? '' : undefined,
  kelas_id: tab === 'siswa' ? '' : undefined,
  is_active: '',
  page: 1,
  per_page: 15,
  sort_by: '',
  sort_direction: ''
});

const sanitizeFiltersForTab = (filters, tab) => {
  const baseFilters = buildDefaultFilters(tab);
  const allowedKeys = Object.keys(baseFilters);
  const merged = { ...baseFilters };

  allowedKeys.forEach((key) => {
    if (filters[key] !== undefined && filters[key] !== null && filters[key] !== '') {
      merged[key] = filters[key];
    }
  });

  const sanitized = {};
  Object.entries(merged).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      sanitized[key] = value;
    }
  });

  return sanitized;
};

export const useUserManagementNew = () => {
  const normalizeUserId = (id) => String(id);
  const resolveUserId = (user) => (
    user?.id
    ?? user?.user_id
    ?? user?.userId
    ?? user?.user?.id
    ?? user?.data_pribadi_siswa?.user_id
    ?? user?.data_pribadi_siswa?.id
    ?? user?.dataPribadiSiswa?.user_id
    ?? user?.dataPribadiSiswa?.id
    ?? null
  );

  // State management
  const [state, setState] = useState({
    users: [],
    loading: false,
    selectedUsers: [],
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0
    },
    filters: buildDefaultFilters('pegawai'),
    sortConfig: null
  });

  const { enqueueSnackbar } = useSnackbar();

  // Update state helper
  const updateState = useCallback((updates) => {
    setState(prev => ({ ...prev, ...updates }));
  }, []);

  // Load users data
  const loadUsers = useCallback(async (activeTab) => {
    updateState({ loading: true });
    
    try {
      let response;
      const apiFilters = sanitizeFiltersForTab(state.filters, activeTab);
      if (activeTab === 'siswa') {
        apiFilters.kelas_scope = 'awal';
      }
      
      if (activeTab === 'pegawai') {
        response = await pegawaiService.getAll(apiFilters);
      } else {
        response = await siswaService.getAll(apiFilters);
      }
      
      // Handle response structure
      if (response?.data?.success) {
        const responseData = response.data.data;
        
        if (activeTab === 'pegawai' && responseData?.data) {
          const nextUsers = Array.isArray(responseData.data) ? responseData.data : [];
          const pageUserIds = new Set(
            nextUsers
              .map((user) => resolveUserId(user))
              .filter((id) => id !== null && id !== undefined && String(id).trim() !== '')
              .map((id) => normalizeUserId(id))
          );

          setState((prev) => ({
            ...prev,
            users: nextUsers,
            selectedUsers: prev.selectedUsers.filter((id) => pageUserIds.has(normalizeUserId(id))),
            pagination: {
              current_page: responseData.current_page || 1,
              last_page: responseData.last_page || 1,
              per_page: responseData.per_page || 15,
              total: responseData.total || 0,
              from: responseData.from || 0,
              to: responseData.to || 0
            }
          }));
        } else if (activeTab === 'siswa' && responseData) {
          const userData = responseData.data || [];
          const nextUsers = Array.isArray(userData) ? userData : [];
          const pageUserIds = new Set(
            nextUsers
              .map((user) => resolveUserId(user))
              .filter((id) => id !== null && id !== undefined && String(id).trim() !== '')
              .map((id) => normalizeUserId(id))
          );

          setState((prev) => ({
            ...prev,
            users: nextUsers,
            selectedUsers: prev.selectedUsers.filter((id) => pageUserIds.has(normalizeUserId(id))),
            pagination: {
              current_page: responseData.current_page || 1,
              last_page: responseData.last_page || 1,
              per_page: responseData.per_page || 15,
              total: responseData.total || 0,
              from: responseData.from || 0,
              to: responseData.to || 0
            }
          }));
        }
      } else {
        updateState({
          users: [],
          pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
            from: 0,
            to: 0
          }
        });
      }
    } catch (error) {
      console.error('Error loading users:', error);
      enqueueSnackbar(
        error.response?.data?.message || 'Gagal memuat data pengguna',
        { variant: 'error' }
      );
      updateState({
        users: [],
        pagination: {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0,
          from: 0,
          to: 0
        }
      });
    } finally {
      updateState({ loading: false });
    }
  }, [state.filters, updateState, enqueueSnackbar]);

  const resetFiltersForTab = useCallback((activeTab) => {
    updateState({
      filters: buildDefaultFilters(activeTab),
      sortConfig: null
    });
  }, [updateState]);

  // Filter management
  const handleFilterChange = useCallback((key, value) => {
    setState((prev) => ({
      ...prev,
      filters: {
        ...prev.filters,
        [key]: value,
        page: key === 'page' ? value : 1 // Reset to page 1 unless changing page
      }
    }));
  }, []);

  // Pagination
  const handlePageChange = useCallback((page) => {
    handleFilterChange('page', page);
  }, [handleFilterChange]);

  // Sorting
  const handleSort = useCallback((field) => {
    setState((prev) => {
      let direction = 'asc';
      if (prev.sortConfig && prev.sortConfig.field === field && prev.sortConfig.direction === 'asc') {
        direction = 'desc';
      }

      return {
        ...prev,
        sortConfig: { field, direction },
        filters: {
          ...prev.filters,
          sort_by: field,
          sort_direction: direction,
          page: 1
        }
      };
    });
  }, []);

  // User selection
  const handleSelectUser = useCallback((userId) => {
    const normalizedId = normalizeUserId(userId);

    setState((prev) => ({
      ...prev,
      selectedUsers: prev.selectedUsers.includes(normalizedId)
        ? prev.selectedUsers.filter((id) => id !== normalizedId)
        : [...prev.selectedUsers, normalizedId]
    }));
  }, []);

  const handleSelectAll = useCallback((checked) => {
    setState((prev) => {
      const currentPageIds = prev.users
        .map((user) => resolveUserId(user))
        .filter((id) => id !== null && id !== undefined && String(id).trim() !== '')
        .map((id) => normalizeUserId(id));

      const shouldSelectAll = typeof checked === 'boolean'
        ? checked
        : currentPageIds.some((id) => !prev.selectedUsers.includes(id));

      return {
        ...prev,
        selectedUsers: shouldSelectAll ? currentPageIds : []
      };
    });
  }, []);

  // User actions
  const handleDeleteUser = useCallback(async (id, activeTab) => {
    try {
      if (activeTab === 'pegawai') {
        await pegawaiService.delete(id);
      } else {
        await siswaService.delete(id);
      }
      
      enqueueSnackbar('Pengguna berhasil dihapus', { variant: 'success' });
      loadUsers(activeTab);
    } catch (error) {
      console.error('Error deleting user:', error);
      enqueueSnackbar(
        error.response?.data?.message || 'Gagal menghapus pengguna',
        { variant: 'error' }
      );
    }
  }, [loadUsers, enqueueSnackbar]);

  const toggleUserStatus = useCallback(async (userId, currentStatus, activeTab) => {
    try {
      if (activeTab === 'pegawai') {
        await pegawaiService.update(userId, { is_active: !currentStatus });
      } else {
        await siswaService.update(userId, { is_active: !currentStatus });
      }
      
      enqueueSnackbar(
        `Status pengguna berhasil ${!currentStatus ? 'diaktifkan' : 'dinonaktifkan'}`,
        { variant: 'success' }
      );
      loadUsers(activeTab);
    } catch (error) {
      console.error('Error toggling user status:', error);
      enqueueSnackbar(
        error.response?.data?.message || 'Gagal mengubah status pengguna',
        { variant: 'error' }
      );
    }
  }, [loadUsers, enqueueSnackbar]);

  const handleBulkDelete = useCallback(async (activeTab) => {
    if (state.selectedUsers.length === 0) return;

    try {
      const service = activeTab === 'pegawai' ? pegawaiService : siswaService;
      
      // Delete users one by one
      await Promise.all(state.selectedUsers.map(id => service.delete(id)));
      
      enqueueSnackbar(
        `${state.selectedUsers.length} pengguna berhasil dihapus`,
        { variant: 'success' }
      );
      updateState({ selectedUsers: [] });
      loadUsers(activeTab);
    } catch (error) {
      console.error('Error bulk deleting users:', error);
      enqueueSnackbar(
        error.response?.data?.message || 'Gagal menghapus beberapa pengguna',
        { variant: 'error' }
      );
    }
  }, [state.selectedUsers, updateState, loadUsers, enqueueSnackbar]);

  return {
    // State
    users: state.users,
    loading: state.loading,
    pagination: state.pagination,
    filters: state.filters,
    selectedUsers: state.selectedUsers,
    sortConfig: state.sortConfig,
    
    // Actions
    loadUsers,
    handleFilterChange,
    handlePageChange,
    handleSort,
    handleSelectUser,
    handleSelectAll,
    handleDeleteUser,
    toggleUserStatus,
    handleBulkDelete,
    resetFiltersForTab,
    
    // Utilities
    updateState
  };
};

export default useUserManagementNew;

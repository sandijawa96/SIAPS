import { useState, useEffect, useCallback } from 'react';
import { useSnackbar } from 'notistack';
import roleService from '../services/roleService';
import permissionService from '../services/permissionService';

export const useRoleManagement = () => {
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedRole, setSelectedRole] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [viewMode, setViewMode] = useState('category');
  const { enqueueSnackbar } = useSnackbar();

  // Fetch all roles from database
  const fetchRoles = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await roleService.getAll();
      if (response.success) {
        setRoles(response.data);
      } else {
        throw new Error(response.error || 'Gagal mengambil data role');
      }
    } catch (err) {
      const errorMessage = err.message || 'Gagal mengambil data role';
      setError(errorMessage);
      enqueueSnackbar(errorMessage, { variant: 'error' });
      setRoles([]);
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar]);

  // Fetch permissions
  const fetchPermissions = useCallback(async () => {
    try {
      const response = await permissionService.getByModule();
      if (response.success) {
        setPermissions(response.data);
      }
    } catch (err) {
      console.error('Error fetching permissions:', err);
      enqueueSnackbar('Gagal mengambil data permission', { variant: 'error' });
    }
  }, [enqueueSnackbar]);

  // Create new role
  const createRole = useCallback(async (roleData) => {
    try {
      setLoading(true);
      const response = await roleService.createRole(roleData);
      
      if (response.success) {
        enqueueSnackbar(response.message || 'Role berhasil dibuat', { variant: 'success' });
        await fetchRoles(); // Refresh roles list
        return { success: true };
      }
    } catch (err) {
      const errorMessage = err.message || 'Gagal membuat role';
      enqueueSnackbar(errorMessage, { variant: 'error' });
      return { 
        success: false, 
        errors: err.errors || null,
        message: errorMessage
      };
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar, fetchRoles]);

  // Update role
  const updateRole = useCallback(async (id, roleData) => {
    try {
      setLoading(true);
      const response = await roleService.updateRole(id, roleData);
      
      if (response.success) {
        enqueueSnackbar(response.message || 'Role berhasil diperbarui', { variant: 'success' });
        await fetchRoles(); // Refresh roles list
        return { success: true };
      }
    } catch (err) {
      const errorMessage = err.message || 'Gagal memperbarui role';
      enqueueSnackbar(errorMessage, { variant: 'error' });
      return { 
        success: false, 
        errors: err.errors || null,
        message: errorMessage
      };
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar, fetchRoles]);

  // Delete role
  const deleteRole = useCallback(async (id) => {
    try {
      setLoading(true);
      const response = await roleService.deleteRole(id);
      
      if (response.success) {
        enqueueSnackbar(response.message || 'Role berhasil dihapus', { variant: 'success' });
        await fetchRoles(); // Refresh roles list
        return { success: true };
      }
    } catch (err) {
      const errorMessage = err.message || 'Gagal menghapus role';
      enqueueSnackbar(errorMessage, { variant: 'error' });
      return { success: false, message: errorMessage };
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar, fetchRoles]);

  // Toggle role status
  const toggleRoleStatus = useCallback(async (id, currentStatus) => {
    try {
      setLoading(true);
      const response = await roleService.toggleStatus(id);
      
      if (response.success) {
        const newStatus = !currentStatus;
        enqueueSnackbar(
          `Role berhasil ${newStatus ? 'diaktifkan' : 'dinonaktifkan'}`,
          { variant: 'success' }
        );
        await fetchRoles(); // Refresh roles list
        return { success: true };
      }
    } catch (err) {
      const errorMessage = err.message || `Gagal ${currentStatus ? 'menonaktifkan' : 'mengaktifkan'} role`;
      enqueueSnackbar(errorMessage, { variant: 'error' });
      return { success: false, message: errorMessage };
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar, fetchRoles]);

  // Assign permissions to role
  const assignPermissions = useCallback(async (roleId, permissionIds) => {
    try {
      setLoading(true);
      const response = await roleService.assignPermissions(roleId, permissionIds);
      
      if (response.success) {
        enqueueSnackbar(response.message || 'Permission berhasil ditetapkan', { variant: 'success' });
        await fetchRoles(); // Refresh roles list
        return { success: true };
      }
    } catch (err) {
      const errorMessage = err.message || 'Gagal menetapkan permission';
      enqueueSnackbar(errorMessage, { variant: 'error' });
      return { success: false, message: errorMessage };
    } finally {
      setLoading(false);
    }
  }, [enqueueSnackbar, fetchRoles]);

  // Filter roles based on search term
  const filteredRoles = roles.filter(role =>
    role?.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    role?.display_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    role?.description?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Get role statistics
  const roleStats = {
    totalRoles: roles.length,
    totalPrimaryRoles: roles.filter(r => r.is_primary).length,
    totalSubRoles: roles.filter(r => !r.is_primary).length,
    activeRoles: roles.filter(r => r.is_active).length,
    inactiveRoles: roles.filter(r => !r.is_active).length,
    totalPermissions: Object.values(permissions).flat().length
  };

  // Initialize data on mount
  useEffect(() => {
    fetchRoles();
    fetchPermissions();
  }, [fetchRoles, fetchPermissions]);

  // For backward compatibility with ManajemenPengguna
  const loadRoles = useCallback(async () => {
    await fetchRoles();
  }, [fetchRoles]);

  // For backward compatibility with ManajemenPengguna
  const updateAvailableSubRoles = useCallback(async (selectedRoleId) => {
    if (!selectedRoleId) return [];
    
    try {
      const response = await roleService.getSubRoles(selectedRoleId);
      if (response.success) {
        return response.data || [];
      }
      return [];
    } catch (error) {
      console.error('Error fetching sub roles:', error);
      return [];
    }
  }, []);

  return {
    // State
    roles,
    permissions,
    loading,
    error,
    selectedRole,
    searchTerm,
    viewMode,
    filteredRoles,
    roleStats,

    // Actions
    setSelectedRole,
    setSearchTerm,
    setViewMode,
    fetchRoles,
    fetchPermissions,
    createRole,
    updateRole,
    deleteRole,
    toggleRoleStatus,
    assignPermissions,

    // Backward compatibility functions
    loadRoles,
    updateAvailableSubRoles,

    // Computed values
    primaryRoles: roles.filter(r => r.is_primary),
    subRoles: roles.filter(r => !r.is_primary),
    activeRoles: roles.filter(r => r.is_active),
    inactiveRoles: roles.filter(r => !r.is_active),
    availableSubRoles: roles.filter(r => !r.is_primary)
  };
};

export default useRoleManagement;

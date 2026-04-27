import api from './api.js';

const roleService = {
  // Get available roles
  getAvailableRoles: async () => {
    try {
      const availableResponse = await api.get('/roles/available');
      const primaryResponse = await api.get('/roles/primary');

      if (primaryResponse.data?.success && availableResponse.data?.success) {
        const primaryRoles = primaryResponse.data.data || [];
        const availableRoles = availableResponse.data.data || [];
        const subRoles = availableRoles.filter(role =>
          !primaryRoles.find(primaryRole => primaryRole.name === role.value)
        );

        return {
          success: true,
          data: {
            primaryRoles,
            subRoles
          }
        };
      }

      throw new Error('Failed to fetch roles');
    } catch (error) {
      console.error('Error fetching available roles:', error);
      throw error;
    }
  },

  // Get all roles from database
  getAll: async () => {
    try {
      const response = await api.get('/roles');
      return {
        success: true,
        data: response.data?.data || response.data || []
      };
    } catch (error) {
      console.error('Error fetching all roles:', error);
      return {
        success: false,
        data: [],
        error: error.response?.data?.message || 'Gagal mengambil data role'
      };
    }
  },

  // Get role by ID
  getById: async (id) => {
    try {
      const response = await api.get(`/roles/${id}`);
      return {
        success: true,
        data: response.data?.data || response.data
      };
    } catch (error) {
      console.error('Error fetching role by ID:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal mengambil data role',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Create new role
  createRole: async (roleData) => {
    try {
      const response = await api.post('/roles', roleData);
      return {
        success: true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Role berhasil dibuat'
      };
    } catch (error) {
      console.error('Error creating role:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal membuat role',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Update role
  updateRole: async (id, roleData) => {
    try {
      const response = await api.put(`/roles/${id}`, roleData);
      return {
        success: true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Role berhasil diperbarui'
      };
    } catch (error) {
      console.error('Error updating role:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal memperbarui role',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Delete role
  deleteRole: async (id) => {
    try {
      const response = await api.delete(`/roles/${id}`);
      return {
        success: true,
        data: response.data,
        message: response.data?.message || 'Role berhasil dihapus'
      };
    } catch (error) {
      console.error('Error deleting role:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal menghapus role',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Toggle role status
  toggleStatus: async (id) => {
    try {
      const response = await api.post(`/roles/${id}/toggle-status`);
      return {
        success: true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Status role berhasil diubah'
      };
    } catch (error) {
      console.error('Error toggling role status:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal mengubah status role',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Assign permissions to role
  assignPermissions: async (roleId, permissions) => {
    try {
      const response = await api.post(`/roles/${roleId}/assign-permissions`, { permissions });
      return {
        success: true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Permission berhasil ditetapkan'
      };
    } catch (error) {
      console.error('Error assigning permissions:', error);
      throw {
        success: false,
        message: error.response?.data?.message || 'Gagal menetapkan permission',
        errors: error.response?.data?.errors || null
      };
    }
  },

  // Get primary roles only
  getPrimaryRoles: async () => {
    try {
      const response = await api.get('/roles/primary');
      return {
        success: true,
        data: response.data?.data || []
      };
    } catch (error) {
      console.error('Error fetching primary roles:', error);
      throw error;
    }
  },

  // Get feature profile for current authenticated user
  getMyFeatureProfile: async () => {
    try {
      const response = await api.get('/roles/my-feature-profile');
      return {
        success: true,
        data: response.data?.data || null
      };
    } catch (error) {
      console.error('Error fetching my feature profile:', error);
      throw error;
    }
  },

  // Get sub roles for a primary role
  getSubRoles: async (primaryRoleId) => {
    try {
      const response = await api.get(`/roles/${primaryRoleId}/sub-roles`);
      return {
        success: true,
        data: response.data?.data || []
      };
    } catch (error) {
      console.error('Error fetching sub roles:', error);
      throw error;
    }
  }
};

export default roleService;

import api from './api.js';

const permissionService = {
  // Get permissions by module
  getByModule: async () => {
    try {
      const response = await api.get('/permissions/by-module');
      return {
        success: true,
        data: response.data?.data || response.data || {}
      };
    } catch (error) {
      console.error('Error fetching permissions by module:', error);

      return {
        success: false,
        data: {},
        error: error.response?.data?.message || 'Gagal mengambil data permission per modul'
      };
    }
  },

  // Get all permissions
  getAll: async () => {
    try {
      const response = await api.get('/permissions');
      return {
        success: true,
        data: response.data?.data || response.data || []
      };
    } catch (error) {
      console.error('Error fetching all permissions:', error);
      return {
        success: false,
        data: [],
        error: error.response?.data?.message || 'Gagal mengambil data permission'
      };
    }
  }
};

export default permissionService;

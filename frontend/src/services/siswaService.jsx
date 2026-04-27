import api from './api.js';

const siswaService = {
  // Get all siswa with filters
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/siswa', { params });
      // Handle paginated response structure
      if (response.data?.success && response.data.data) {
        // For ManajemenPengguna.jsx
        if (params.page) {
          return {
            data: {
              success: true,
              data: response.data.data
            }
          };
        }
        // For other components
        return response.data.data;
      }
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get single siswa by ID
  getById: async (id) => {
    try {
      const response = await api.get(`/siswa/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Create new siswa
  create: async (data) => {
    try {
      const formData = new FormData();
      
      // Append all data to FormData
      Object.keys(data).forEach(key => {
        if (data[key] !== null && data[key] !== undefined) {
          if (Array.isArray(data[key])) {
            data[key].forEach((item, index) => {
              formData.append(`${key}[${index}]`, item);
            });
          } else if (data[key] instanceof File) {
            formData.append(key, data[key]);
          } else {
            formData.append(key, data[key]);
          }
        }
      });

      const response = await api.post('/siswa', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Update siswa
  update: async (id, data) => {
    try {
      // If only updating is_active status, send as JSON
      if (Object.keys(data).length === 1 && 'is_active' in data) {
        const response = await api.put(`/siswa/${id}`, {
          is_active: Boolean(data.is_active)
        });
        return response.data;
      }

      const formData = new FormData();
      formData.append('_method', 'PUT');
      
      // Append all data to FormData
      Object.keys(data).forEach(key => {
        if (data[key] !== null && data[key] !== undefined) {
          if (key === 'is_active') {
            formData.append(key, data[key] ? '1' : '0');
          } else if (Array.isArray(data[key])) {
            data[key].forEach((item, index) => {
              formData.append(`${key}[${index}]`, item);
            });
          } else if (data[key] instanceof File) {
            formData.append(key, data[key]);
          } else {
            formData.append(key, data[key]);
          }
        }
      });

      const response = await api.post(`/siswa/${id}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Delete siswa
  delete: async (id) => {
    try {
      const response = await api.delete(`/siswa/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Reset password siswa
  resetPassword: async (id, data) => {
    try {
      const response = await api.post(`/siswa/${id}/reset-password`, data);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Import siswa dari Excel
  import: async (formData) => {
    try {
      // FormData already contains file and import options
      const response = await api.post('/siswa/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        // Import can take minutes for large files.
        timeout: 600000
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Import siswa dari Excel (alias untuk kompatibilitas)
  impor: async (formData) => {
    return siswaService.import(formData);
  },

  // Export siswa ke Excel
  ekspor: async () => {
    try {
      const response = await api.get('/siswa/export', {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.ms-excel'
        }
      });
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Download template import
  downloadTemplate: async () => {
    try {
      const response = await api.get('/siswa/template', {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.ms-excel'
        }
      });
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  }
};

export default siswaService;

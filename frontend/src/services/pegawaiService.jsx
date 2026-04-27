import api from './api.js';

const pegawaiService = {
  getAvailableSubRoles: async (roleId) => {
    const response = await api.get(`/pegawai/roles/${roleId}/sub-roles`);
    return response.data;
  },
  // Get all pegawai with filters
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/pegawai', { params });
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
        // For ManajemenKelas.jsx
        return response.data.data;
      }
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get all wali kelas specifically
  getAllWaliKelas: async (params = {}) => {
    try {
      // Add role filter for wali kelas
      const queryParams = {
        ...params,
        role: 'Wali Kelas' // Only get pegawai with Wali Kelas role
      };
      
      const response = await api.get('/pegawai', { params: queryParams });
      // Handle paginated response structure
      if (response.data.success && response.data.data) {
        // If it's paginated data, return the data array
        if (response.data.data.data && Array.isArray(response.data.data.data)) {
          return { data: response.data.data.data };
        }
        // If it's direct array, return as is
        if (Array.isArray(response.data.data)) {
          return { data: response.data.data };
        }
      }
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get single pegawai by ID
  getById: async (id) => {
    try {
      const response = await api.get(`/pegawai/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Create new pegawai
  create: async (data) => {
    try {
      // Check if there's a file upload
      if (data.foto_profil instanceof File) {
        const formData = new FormData();
        
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

        const response = await api.post('/pegawai', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
        return response.data;
      } else {
        // If no file upload, send as JSON
        const dataToSend = { ...data };
        if ('foto_profil' in dataToSend && !dataToSend.foto_profil) {
          delete dataToSend.foto_profil;
        }
        dataToSend.is_active = Boolean(dataToSend.is_active);

        const response = await api.post('/pegawai', dataToSend);
        return response.data;
      }
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Update pegawai
  update: async (id, data) => {
    try {
      // If only updating is_active status, send as JSON
      if (Object.keys(data).length === 1 && 'is_active' in data) {
        const response = await api.put(`/pegawai/${id}`, {
          is_active: Boolean(data.is_active)
        });
        return response.data;
      }

      // For other updates with file upload
      if (data.foto_profil instanceof File) {
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

        const response = await api.post(`/pegawai/${id}`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
        return response.data;
      } else {
        // If no file upload, send as JSON
        const dataToSend = { ...data };
        if ('foto_profil' in dataToSend && !dataToSend.foto_profil) {
          delete dataToSend.foto_profil;
        }
        dataToSend.is_active = Boolean(dataToSend.is_active);
        
        // Add _method for Laravel to handle PUT request
        dataToSend._method = 'PUT';

        const response = await api.post(`/pegawai/${id}`, dataToSend);
        return response.data;
      }
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Delete pegawai
  delete: async (id) => {
    try {
      const response = await api.delete(`/pegawai/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Reset password pegawai
  resetPassword: async (id, data) => {
    try {
      const response = await api.post(`/pegawai/${id}/reset-password`, data);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get available roles for pegawai
  getAvailableRoles: async () => {
    try {
      const response = await api.get('/roles/available');
      // Handle both array of objects and array of strings
      if (Array.isArray(response.data.data)) {
        return {
          data: response.data.data.map(role => 
            typeof role === 'string' ? role : role.value || role.name
          )
        };
      }
      return { data: [] };
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get pegawai by role
  getByRole: async (role, params = {}) => {
    try {
      const response = await api.get('/pegawai', { 
        params: { 
          role: role,
          ...params 
        } 
      });
      // Handle paginated response structure
      if (response.data.success && response.data.data) {
        // If it's paginated data, return the data array
        if (response.data.data.data && Array.isArray(response.data.data.data)) {
          return { data: response.data.data.data };
        }
        // If it's direct array, return as is
        if (Array.isArray(response.data.data)) {
          return { data: response.data.data };
        }
      }
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Export data pegawai ke Excel
  ekspor: async () => {
    try {
      const response = await api.get('/pegawai/export', {
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

  // Import data pegawai dari Excel
  import: async (formData) => {
    try {
      // FormData already contains file and import options
      const response = await api.post('/pegawai/import', formData, {
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

  // Download template import Excel
  downloadTemplate: async () => {
    try {
      const response = await api.get('/pegawai/template', {
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

export default pegawaiService;

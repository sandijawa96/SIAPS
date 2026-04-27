import api from './api';

export const tahunAjaranAPI = {
  // Get all tahun ajaran with filters
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/tahun-ajaran', { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Get specific tahun ajaran
  getById: async (id) => {
    try {
      const response = await api.get(`/tahun-ajaran/${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Create new tahun ajaran
  create: async (data) => {
    try {
      const response = await api.post('/tahun-ajaran', data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Update tahun ajaran
  update: async (id, data) => {
    try {
      const response = await api.put(`/tahun-ajaran/${id}`, data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Delete tahun ajaran
  delete: async (id) => {
    try {
      const response = await api.delete(`/tahun-ajaran/${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Transition status
  transitionStatus: async (id, status, metadata = {}) => {
    try {
      const response = await api.post(`/tahun-ajaran/${id}/transition-status`, {
        status,
        metadata
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Update preparation progress
  updateProgress: async (id, progress, metadata = {}) => {
    try {
      const response = await api.post(`/tahun-ajaran/${id}/update-progress`, {
        progress,
        metadata
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Get tahun ajaran by status
  getByStatus: async (status) => {
    try {
      const response = await api.get('/tahun-ajaran', {
        params: { status, no_pagination: true }
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // Get tahun ajaran that can manage classes
  getCanManageClasses: async () => {
    try {
      const response = await api.get('/tahun-ajaran', {
        params: { can_manage_classes: true, no_pagination: true }
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  }
};

// Status constants
export const TAHUN_AJARAN_STATUS = {
  DRAFT: 'draft',
  PREPARATION: 'preparation',
  ACTIVE: 'active',
  COMPLETED: 'completed',
  ARCHIVED: 'archived'
};

// Status display mapping
export const STATUS_DISPLAY = {
  [TAHUN_AJARAN_STATUS.DRAFT]: 'Draft',
  [TAHUN_AJARAN_STATUS.PREPARATION]: 'Persiapan',
  [TAHUN_AJARAN_STATUS.ACTIVE]: 'Aktif',
  [TAHUN_AJARAN_STATUS.COMPLETED]: 'Selesai',
  [TAHUN_AJARAN_STATUS.ARCHIVED]: 'Diarsipkan'
};

// Status colors for UI
export const STATUS_COLORS = {
  [TAHUN_AJARAN_STATUS.DRAFT]: 'bg-gray-100 text-gray-800',
  [TAHUN_AJARAN_STATUS.PREPARATION]: 'bg-yellow-100 text-yellow-800',
  [TAHUN_AJARAN_STATUS.ACTIVE]: 'bg-green-100 text-green-800',
  [TAHUN_AJARAN_STATUS.COMPLETED]: 'bg-blue-100 text-blue-800',
  [TAHUN_AJARAN_STATUS.ARCHIVED]: 'bg-red-100 text-red-800'
};

export default tahunAjaranAPI;

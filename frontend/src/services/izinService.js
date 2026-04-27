import api from './api';

export const izinService = {
  // Normalize legacy + current response payload shape
  normalizeIzinListResponse: (responseData) => {
    const payload = responseData?.data;
    const topLevelMeta = responseData?.meta && typeof responseData.meta === 'object'
      ? responseData.meta
      : {};
    const rows = Array.isArray(payload?.data)
      ? payload.data
      : Array.isArray(payload)
        ? payload
        : [];

    const meta = payload && !Array.isArray(payload)
      ? {
        current_page: payload.current_page || 1,
        last_page: payload.last_page || 1,
        per_page: payload.per_page || rows.length || 10,
        total: payload.total || rows.length || 0,
        ...topLevelMeta,
      }
      : {
        current_page: 1,
        last_page: 1,
        per_page: rows.length || 10,
        total: rows.length || 0,
        ...topLevelMeta,
      };

    return {
      success: responseData?.success ?? true,
      data: rows,
      meta,
      raw: responseData,
    };
  },

  // Get list of izin
  getIzinList: async (params = {}) => {
    const response = await api.get('/izin', { params });
    return response.data;
  },

  // Backward compatible alias
  getMyIzin: async (params = {}) => {
    const response = await api.get('/izin', { params });
    return izinService.normalizeIzinListResponse(response.data);
  },

  // Get izin statistics
  getStatistics: async (type = 'siswa') => {
    const response = await api.get('/izin/statistics', { params: { type } });
    return response.data;
  },

  getObservability: async (params = {}) => {
    const response = await api.get('/izin/observability', { params });
    return response.data;
  },

  // Get izin detail
  getIzinDetail: async (id) => {
    const response = await api.get(`/izin/${id}`);
    return response.data;
  },

  // Create new izin
  createIzin: async (data) => {
    const formData = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) {
      Object.keys(data).forEach(key => {
        if (data[key] !== undefined && data[key] !== null) {
          formData.append(key, data[key]);
        }
      });
    }

    const response = await api.post('/izin', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    return response.data;
  },

  // Update izin
  updateIzin: async (id, data) => {
    throw new Error('Edit izin belum didukung oleh backend. Gunakan hapus lalu ajukan ulang.');
  },

  // Delete izin
  deleteIzin: async (id) => {
    const response = await api.delete(`/izin/${id}`);
    return response.data;
  },

  // Backward compatible aliases
  create: async (data) => izinService.createIzin(data),
  update: async (id, data) => izinService.updateIzin(id, data),
  cancel: async (id) => izinService.deleteIzin(id),

  // Approve izin
  approveIzin: async (id, data) => {
    const response = await api.post(`/izin/${id}/approve`, data);
    return response.data;
  },

  // Reject izin
  rejectIzin: async (id, data) => {
    const response = await api.post(`/izin/${id}/reject`, data);
    return response.data;
  },

  // Download dokumen pendukung
  downloadDocument: async (id) => {
    const response = await api.get(`/izin/${id}/document`, {
      responseType: 'blob'
    });
    return response;
  },

  // Get izin for approval (admin/wali kelas)
  getForApproval: async (params = {}) => {
    const response = await api.get('/izin/approval/list', { params });
    return response.data;
  },

  // Get jenis izin/cuti options
  getJenisIzinOptions: async (type = 'auto') => {
    const response = await api.get(`/izin/jenis/${type}`);
    return response.data;
  }
};

export default izinService;

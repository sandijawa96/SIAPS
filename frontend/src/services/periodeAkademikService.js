import api from './api';

export const periodeAkademikService = {
    // Get all periode akademik with optional filters
    getAll: async (params = {}) => {
        const response = await api.get('/periode-akademik', { params });
        return response.data;
    },

    // Get single periode akademik by id
    getById: async (id) => {
        const response = await api.get(`/periode-akademik/${id}`);
        return response.data;
    },

    // Create new periode akademik
    create: async (data) => {
        const response = await api.post('/periode-akademik', data);
        return response.data;
    },

    // Update periode akademik
    update: async (id, data) => {
        const response = await api.put(`/periode-akademik/${id}`, data);
        return response.data;
    },

    // Delete periode akademik
    delete: async (id) => {
        const response = await api.delete(`/periode-akademik/${id}`);
        return response.data;
    },

    // Get current active periode
    getCurrentPeriode: async () => {
        const response = await api.get('/periode-akademik/current/periode');
        return response.data;
    },

    // Check absensi validity
    checkAbsensiValidity: async (params) => {
        const response = await api.post('/periode-akademik/check/absensi-validity', params);
        return response.data;
    }
};

export default periodeAkademikService;

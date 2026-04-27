import api from './api';

export const eventAkademikService = {
    // Get all event akademik with optional filters
    getAll: async (params = {}) => {
        const response = await api.get('/event-akademik', { params });
        return response.data;
    },

    // Get single event akademik by id
    getById: async (id) => {
        const response = await api.get(`/event-akademik/${id}`);
        return response.data;
    },

    // Create new event akademik
    create: async (data) => {
        const response = await api.post('/event-akademik', data);
        return response.data;
    },

    // Update event akademik
    update: async (id, data) => {
        const response = await api.put(`/event-akademik/${id}`, data);
        return response.data;
    },

    // Delete event akademik
    delete: async (id) => {
        const response = await api.delete(`/event-akademik/${id}`);
        return response.data;
    },

    // Get upcoming events for current user
    getUpcomingEvents: async (days = 7) => {
        const response = await api.get('/event-akademik/user/upcoming', { 
            params: { days } 
        });
        return response.data;
    },

    // Get today's events for current user
    getTodayEvents: async () => {
        const response = await api.get('/event-akademik/user/today');
        return response.data;
    },

    // Preview libur nasional
    previewLiburNasional: async (tahunAjaranId) => {
        const response = await api.post('/event-akademik/preview-libur-nasional', {
            tahun_ajaran_id: tahunAjaranId
        });
        return response.data;
    },

    // Sync libur nasional
    syncLiburNasional: async (tahunAjaranId) => {
        const response = await api.post('/event-akademik/sync-libur-nasional', {
            tahun_ajaran_id: tahunAjaranId
        });
        return response.data;
    },

    // Auto sync libur nasional
    autoSyncLiburNasional: async () => {
        const response = await api.post('/event-akademik/auto-sync-libur-nasional');
        return response.data;
    },

    // Preview kalender Indonesia (hari peringatan, bukan libur nasional)
    previewKalenderIndonesia: async (tahunAjaranId) => {
        const response = await api.post('/event-akademik/preview-kalender-indonesia', {
            tahun_ajaran_id: tahunAjaranId
        });
        return response.data;
    },

    // Sync kalender Indonesia (hari peringatan, bukan libur nasional)
    syncKalenderIndonesia: async (tahunAjaranId) => {
        const response = await api.post('/event-akademik/sync-kalender-indonesia', {
            tahun_ajaran_id: tahunAjaranId
        });
        return response.data;
    },

    // Sync kalender Indonesia lengkap (libur nasional + cuti bersama + peringatan)
    syncKalenderIndonesiaLengkap: async (tahunAjaranId, options = {}) => {
        const response = await api.post('/event-akademik/sync-kalender-indonesia-lengkap', {
            tahun_ajaran_id: tahunAjaranId,
            ...options,
        });
        return response.data;
    },

    // Auto sync kalender Indonesia untuk tahun ajaran aktif
    autoSyncKalenderIndonesia: async () => {
        const response = await api.post('/event-akademik/auto-sync-kalender-indonesia');
        return response.data;
    }
};

export default eventAkademikService;

import { eventAkademikService } from './eventAkademikService';

export const liburNasionalService = {
    // Preview libur nasional untuk tahun ajaran tertentu
    previewLiburNasional: async (tahunAjaranId) => {
        try {
            return await eventAkademikService.previewLiburNasional(tahunAjaranId);
        } catch (error) {
            console.error('Error previewing libur nasional:', error);
            throw error;
        }
    },

    // Sync libur nasional ke database
    syncLiburNasional: async (tahunAjaranId) => {
        try {
            return await eventAkademikService.syncLiburNasional(tahunAjaranId);
        } catch (error) {
            console.error('Error syncing libur nasional:', error);
            throw error;
        }
    },

    // Auto sync libur nasional untuk semua tahun ajaran aktif
    autoSyncLiburNasional: async () => {
        try {
            return await eventAkademikService.autoSyncLiburNasional();
        } catch (error) {
            console.error('Error auto syncing libur nasional:', error);
            throw error;
        }
    }
};

export default liburNasionalService;

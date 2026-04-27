import { kelasAPI } from './api';

export const resetWaliKelas = async (kelasIds) => {
  try {
    const response = await kelasAPI.post('/reset-wali', {
      kelas_ids: kelasIds
    });
    return response.data;
  } catch (error) {
    console.error('Error resetting wali kelas:', error);
    throw error;
  }
};

export { kelasAPI };

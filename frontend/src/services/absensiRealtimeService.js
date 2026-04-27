import api, { absensiAPI } from './api';
import { getServerDateString } from './serverClock';

/**
 * Service untuk mengelola data absensi realtime
 */
export const absensiRealtimeService = {
  /**
   * Mengambil data absensi hari ini dengan filter
   * @param {Object} params - Parameter filter (tanggal, status, dll)
   * @returns {Promise} Response data absensi
   */
  getTodayAttendance: async (params = {}) => {
    try {
      console.log('📊 Fetching today attendance with params:', params);
      const response = await api.get('/dashboard/today-attendance', { params });
      
      console.log('📊 API Response:', response.data);
      
      // Pastikan response memiliki struktur yang benar
      if (response.data && response.data.success) {
        return {
          success: true,
          data: response.data.data || {
            attendances: [],
            summary: {
              total: 0,
              hadir: 0,
              terlambat: 0,
              izin: 0,
              sakit: 0,
              alpha: 0,
              totalUsers: 0,
              attendancePercentage: '0%'
            }
          },
          message: response.data.message || 'Data berhasil diambil'
        };
      }
      
      // Fallback jika struktur response tidak sesuai
      return {
        success: false,
        data: {
          attendances: [],
          summary: {
            total: 0,
            hadir: 0,
            terlambat: 0,
            izin: 0,
            sakit: 0,
            alpha: 0,
            totalUsers: 0,
            attendancePercentage: '0%'
          }
        },
        message: 'Format response tidak valid'
      };
    } catch (error) {
      console.error('❌ Error fetching today attendance:', error);
      
      // Return struktur error yang konsisten
      return {
        success: false,
        data: {
          attendances: [],
          summary: {
            total: 0,
            hadir: 0,
            terlambat: 0,
            izin: 0,
            sakit: 0,
            alpha: 0,
            totalUsers: 0,
            attendancePercentage: '0%'
          }
        },
        message: error.response?.data?.message || error.message || 'Gagal mengambil data absensi'
      };
    }
  },

  /**
   * Mengambil status absensi hari ini untuk user yang sedang login
   * @returns {Promise} Response status absensi
   */
  getMyAttendanceStatus: async () => {
    try {
      console.log('🕐 Fetching my attendance status...');
      const response = await api.get('/dashboard/my-attendance-status');
      
      console.log('🕐 My Attendance Status Response:', response.data);
      
      if (response.data && response.data.success) {
        return {
          success: true,
          data: response.data.data || {
            date: getServerDateString(),
            has_attendance: false,
            has_checked_in: false,
            has_checked_out: false,
            status: 'Belum Absen',
            status_key: 'belum_absen',
            status_label: 'Belum Absen',
          },
          message: response.data.message || 'Status berhasil diambil'
        };
      }
      
      // Fallback untuk response lama
      return {
        success: true,
        data: response.data || {
          date: getServerDateString(),
          has_attendance: false,
          has_checked_in: false,
          has_checked_out: false,
          status: 'Belum Absen',
          status_key: 'belum_absen',
          status_label: 'Belum Absen',
        },
        message: 'Status berhasil diambil'
      };
    } catch (error) {
      console.error('❌ Error fetching my attendance status:', error);
      
      return {
        success: false,
        data: {
          date: getServerDateString(),
          has_attendance: false,
          has_checked_in: false,
          has_checked_out: false,
          status: 'Error',
          status_key: 'error',
          status_label: 'Error',
        },
        message: error.response?.data?.message || error.message || 'Gagal mengambil status absensi'
      };
    }
  },

  /**
   * Melakukan check-in absensi
   * @param {Object} data - Data check-in (foto, lokasi, dll)
   * @returns {Promise} Response hasil check-in
   */
  checkIn: async (data) => {
    try {
      console.log('✅ Submitting check-in:', data);
      const response = await absensiAPI.checkIn(data);
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Check-in berhasil'
      };
    } catch (error) {
      console.error('❌ Error submitting check-in:', error);
      
      return {
        success: false,
        data: null,
        message: error.response?.data?.message || error.message || 'Gagal melakukan check-in'
      };
    }
  },

  /**
   * Melakukan check-out absensi
   * @param {Object} data - Data check-out (foto, lokasi, dll)
   * @returns {Promise} Response hasil check-out
   */
  checkOut: async (data) => {
    try {
      console.log('🏁 Submitting check-out:', data);
      const response = await absensiAPI.checkOut(data);
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Check-out berhasil'
      };
    } catch (error) {
      console.error('❌ Error submitting check-out:', error);
      
      return {
        success: false,
        data: null,
        message: error.response?.data?.message || error.message || 'Gagal melakukan check-out'
      };
    }
  },

  /**
   * Mengambil riwayat absensi
   * @param {Object} params - Parameter filter dan pagination
   * @returns {Promise} Response riwayat absensi
   */
  getHistory: async (params = {}) => {
    try {
      console.log('📋 Fetching attendance history with params:', params);
      const response = await api.get('/absensi/history', { params });
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data || [],
        message: response.data?.message || 'Riwayat berhasil diambil'
      };
    } catch (error) {
      console.error('❌ Error fetching attendance history:', error);
      
      return {
        success: false,
        data: [],
        message: error.response?.data?.message || error.message || 'Gagal mengambil riwayat absensi'
      };
    }
  },

  /**
   * Mengambil statistik absensi
   * @param {Object} params - Parameter filter
   * @returns {Promise} Response statistik absensi
   */
  getStatistics: async (params = {}) => {
    try {
      console.log('📈 Fetching attendance statistics with params:', params);
      const response = await api.get('/absensi/statistics', { params });
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data || {},
        message: response.data?.message || 'Statistik berhasil diambil'
      };
    } catch (error) {
      console.error('❌ Error fetching attendance statistics:', error);
      
      return {
        success: false,
        data: {},
        message: error.response?.data?.message || error.message || 'Gagal mengambil statistik absensi'
      };
    }
  },

  /**
   * Mengambil detail absensi berdasarkan ID
   * @param {number} id - ID absensi
   * @returns {Promise} Response detail absensi
   */
  getDetail: async (id) => {
    try {
      console.log('🔍 Fetching attendance detail for ID:', id);
      const response = await api.get(`/absensi/${id}`);
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Detail berhasil diambil'
      };
    } catch (error) {
      console.error('❌ Error fetching attendance detail:', error);
      
      return {
        success: false,
        data: null,
        message: error.response?.data?.message || error.message || 'Gagal mengambil detail absensi'
      };
    }
  },

  /**
   * Validasi lokasi untuk absensi
   * @param {Object} coords - Koordinat {latitude, longitude}
   * @returns {Promise} Response validasi lokasi
   */
  validateLocation: async (coords) => {
    try {
      console.log('📍 Validating location:', coords);
      const response = await api.post('/lokasi-gps/check-distance', coords);
      
      return {
        success: response.data?.success === true,
        data: response.data?.data || response.data,
        message: response.data?.message || 'Lokasi berhasil divalidasi'
      };
    } catch (error) {
      console.error('❌ Error validating location:', error);
      
      return {
        success: false,
        data: { can_attend: false, locations: [] },
        message: error.response?.data?.message || error.message || 'Gagal memvalidasi lokasi'
      };
    }
  }
};

export default absensiRealtimeService;


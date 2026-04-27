import api from './api';
import { toServerDateInput } from './serverClock';

// Report API endpoints
export const reportAPI = {
  // Daily report
  getDailyReport: (params) => api.get('/reports/attendance/daily', { params }),

  // Date range report
  getRangeReport: (params) => api.get('/reports/attendance/range', { params }),
  
  // Monthly report
  getMonthlyReport: (params) => api.get('/reports/attendance/monthly', { params }),

  // Semester report
  getSemesterReport: (params) => api.get('/reports/attendance/semester', { params }),
  
  // Yearly report
  getYearlyReport: (params) => api.get('/reports/attendance/yearly', { params }),
  
  // Export functions
  exportExcel: (params) => api.get('/reports/export/excel', { 
    params,
    responseType: 'blob' // Important for file downloads
  }),
  
  exportPdf: (params) => api.get('/reports/export/pdf', { 
    params,
    responseType: 'blob' // Important for file downloads
  }),

  // Get statistics for dashboard cards
  getStatistics: (params) => {
    // This will use the daily report endpoint to get statistics
    return api.get('/reports/attendance/daily', { params });
  },

  // Get detailed attendance data for table
  getAttendanceData: (params) => {
    return api.get('/absensi/history', { params });
  }
};

// Helper function to format date for API
export const formatDateForAPI = (date) => {
  if (!date) return null;
  return toServerDateInput(date) || null;
};

// Helper function to build query parameters
export const buildReportParams = (filters) => {
  const params = {};
  
  if (filters.tanggalMulai) {
    params.start_date = formatDateForAPI(filters.tanggalMulai);
  }
  
  if (filters.tanggalSelesai) {
    params.end_date = formatDateForAPI(filters.tanggalSelesai);
  }
  
  if (filters.selectedTingkat && filters.selectedTingkat !== 'Semua') {
    params.tingkat_id = filters.selectedTingkat;
  }
  
  if (filters.selectedKelas && filters.selectedKelas !== 'Semua') {
    params.kelas_id = filters.selectedKelas;
  }
  
  if (filters.selectedStatus && filters.selectedStatus !== 'Semua') {
    params.status = String(filters.selectedStatus).toLowerCase();
  }

  if (filters.selectedDisciplineStatus && filters.selectedDisciplineStatus !== 'Semua') {
    params.status_disiplin = String(filters.selectedDisciplineStatus).toLowerCase();
  }
  
  // For daily report
  if (filters.tanggal) {
    params.tanggal = formatDateForAPI(filters.tanggal);
  }
  
  // For monthly report
  if (filters.bulan) {
    params.bulan = filters.bulan;
  }
  
  if (filters.tahun) {
    params.tahun = filters.tahun;
  }
  
  return params;
};

export default reportAPI;

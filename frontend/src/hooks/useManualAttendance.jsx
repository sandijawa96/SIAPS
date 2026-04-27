import { useState, useEffect, useCallback } from 'react';
import { manualAttendanceService } from '../services/manualAttendanceService';
import { getServerDateString, getServerNowDate } from '../services/serverClock';
import { toast } from 'react-hot-toast';

const toTimeMinutes = (value) => {
  const raw = String(value || '').trim();
  const match = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return null;
  }

  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (
    Number.isNaN(hour) ||
    Number.isNaN(minute) ||
    hour < 0 ||
    hour > 23 ||
    minute < 0 ||
    minute > 59
  ) {
    return null;
  }

  return (hour * 60) + minute;
};

export const useManualAttendance = () => {
  const [attendanceList, setAttendanceList] = useState([]);
  const [historyMeta, setHistoryMeta] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
    from: 0,
    to: 0,
  });
  const [users, setUsers] = useState([]);
  const [statistics, setStatistics] = useState({});
  const [pendingCheckoutList, setPendingCheckoutList] = useState([]);
  const [pendingCheckoutMeta, setPendingCheckoutMeta] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 15,
  });
  const [incidentOptions, setIncidentOptions] = useState(null);
  const [incidentOptionsLoading, setIncidentOptionsLoading] = useState(false);
  const [recentIncidentBatches, setRecentIncidentBatches] = useState([]);
  const [recentIncidentBatchesLoading, setRecentIncidentBatchesLoading] = useState(false);
  const [recentIncidentBatchesRefreshedAt, setRecentIncidentBatchesRefreshedAt] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pendingLoading, setPendingLoading] = useState(false);
  const [resolvingPending, setResolvingPending] = useState(false);
  const [error, setError] = useState(null);
  const [filters, setFilters] = useState({
    bucket: 'manual',
    status: '',
    date: '',
    user_id: '',
    start_date: '',
    end_date: '',
    search: '',
    page: 1,
    per_page: 15,
  });

  // Get manageable users
  const getUsers = useCallback(async () => {
    try {
      setLoading(true);
      const response = await manualAttendanceService.getUsers();
      setUsers(response.data || []);
    } catch (error) {
      console.error('Error fetching users:', error);
      setError('Gagal mengambil data pengguna');
      toast.error('Gagal mengambil data pengguna');
    } finally {
      setLoading(false);
    }
  }, []);

  // Get attendance history
  const getHistory = useCallback(async (customFilters = {}) => {
    const queryFilters = {
      ...filters,
      ...customFilters,
    };

    try {
      setLoading(true);
      setError(null);
      const response = await manualAttendanceService.getHistory(queryFilters);
      
      if (response.success) {
        const paginatedData = response.data || {};
        const rows = Array.isArray(paginatedData.data) ? paginatedData.data : [];
        setAttendanceList(rows);
        setHistoryMeta({
          current_page: paginatedData.current_page || queryFilters.page || 1,
          last_page: paginatedData.last_page || 1,
          total: paginatedData.total || rows.length,
          per_page: paginatedData.per_page || queryFilters.per_page || 15,
          from: paginatedData.from || (rows.length ? 1 : 0),
          to: paginatedData.to || rows.length,
        });
      } else {
        throw new Error(response.message || 'Gagal mengambil riwayat absensi');
      }
    } catch (error) {
      console.error('Error fetching attendance history:', error);
      setError(error.message || 'Gagal mengambil riwayat absensi');
      toast.error(error.message || 'Gagal mengambil riwayat absensi');
      setAttendanceList([]);
      setHistoryMeta({
        current_page: queryFilters.page || 1,
        last_page: 1,
        total: 0,
        per_page: queryFilters.per_page || 15,
        from: 0,
        to: 0,
      });
    } finally {
      setLoading(false);
    }
  }, [filters]);

  // Get statistics
  const getStatistics = useCallback(async (customFilters = {}) => {
    try {
      const queryFilters = {
        bucket: filters.bucket,
        status: filters.status,
        date: filters.date,
        user_id: filters.user_id,
        start_date: filters.start_date,
        end_date: filters.end_date,
        ...customFilters,
      };
      const response = await manualAttendanceService.getStatistics(queryFilters);
      
      if (response.success) {
        setStatistics(response.data || {});
      } else {
        throw new Error(response.message || 'Gagal mengambil statistik');
      }
    } catch (error) {
      console.error('Error fetching statistics:', error);
      toast.error('Gagal mengambil statistik absensi');
    }
  }, [filters.bucket, filters.status, filters.date, filters.user_id, filters.start_date, filters.end_date]);

  // Get pending checkout list (default H+1)
  const getPendingCheckout = useCallback(async (queryFilters = {}) => {
    try {
      setPendingLoading(true);
      setError(null);
      const response = await manualAttendanceService.getPendingCheckout(queryFilters);

      if (response.success) {
        const paginatedData = response.data || {};
        const rows = Array.isArray(paginatedData.data) ? paginatedData.data : [];

        setPendingCheckoutList(rows);
        setPendingCheckoutMeta({
          current_page: paginatedData.current_page || 1,
          last_page: paginatedData.last_page || 1,
          total: paginatedData.total || 0,
          per_page: paginatedData.per_page || queryFilters.per_page || 15,
        });
      } else {
        throw new Error(response.message || 'Gagal mengambil daftar lupa tap-out');
      }
    } catch (fetchError) {
      console.error('Error fetching pending checkout:', fetchError);
      const errorMessage = fetchError.message || 'Gagal mengambil daftar lupa tap-out';
      setError(errorMessage);
      toast.error(errorMessage);
      setPendingCheckoutList([]);
      setPendingCheckoutMeta({
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: queryFilters.per_page || 15,
      });
    } finally {
      setPendingLoading(false);
    }
  }, []);

  const getIncidentOptions = useCallback(async () => {
    try {
      setIncidentOptionsLoading(true);
      const response = await manualAttendanceService.getIncidentOptions();
      if (response.success) {
        setIncidentOptions(response.data || null);
        return response.data || null;
      }

      throw new Error(response.message || 'Gagal mengambil opsi insiden server');
    } catch (fetchError) {
      console.error('Error fetching incident options:', fetchError);
      const errorMessage = fetchError.message || 'Gagal mengambil opsi insiden server';
      setError(errorMessage);
      toast.error(errorMessage);
      throw fetchError;
    } finally {
      setIncidentOptionsLoading(false);
    }
  }, []);

  const getRecentIncidentBatches = useCallback(async (limit = 8) => {
    try {
      setRecentIncidentBatchesLoading(true);
      const response = await manualAttendanceService.getRecentIncidents(limit);
      if (response.success) {
        setRecentIncidentBatches(Array.isArray(response.data) ? response.data : []);
        setRecentIncidentBatchesRefreshedAt(getServerNowDate().getTime());
        return Array.isArray(response.data) ? response.data : [];
      }

      throw new Error(response.message || 'Gagal mengambil riwayat batch insiden server');
    } catch (fetchError) {
      console.error('Error fetching recent incident batches:', fetchError);
      const errorMessage = fetchError.message || 'Gagal mengambil riwayat batch insiden server';
      setError(errorMessage);
      toast.error(errorMessage);
      throw fetchError;
    } finally {
      setRecentIncidentBatchesLoading(false);
    }
  }, []);

  // Create manual attendance
  const createAttendance = useCallback(async (data) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await manualAttendanceService.create(data);
      
      if (response.success) {
        toast.success(response.message || 'Absensi manual berhasil dibuat');
        await getHistory(); // Refresh list
        await getStatistics(); // Refresh stats
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal membuat absensi manual');
      }
    } catch (error) {
      console.error('Error creating attendance:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Gagal membuat absensi manual';
      setError(errorMessage);
      toast.error(errorMessage);
      throw error;
    } finally {
      setLoading(false);
    }
  }, [getHistory, getStatistics]);

  // Update manual attendance
  const updateAttendance = useCallback(async (id, data) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await manualAttendanceService.update(id, data);
      
      if (response.success) {
        toast.success(response.message || 'Absensi berhasil diperbarui');
        await getHistory(); // Refresh list
        await getStatistics(); // Refresh stats
        return response.data;
      } else {
        throw new Error(response.message || 'Gagal memperbarui absensi');
      }
    } catch (error) {
      console.error('Error updating attendance:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Gagal memperbarui absensi';
      setError(errorMessage);
      toast.error(errorMessage);
      throw error;
    } finally {
      setLoading(false);
    }
  }, [getHistory, getStatistics]);

  // Delete manual attendance
  const deleteAttendance = useCallback(async (id) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await manualAttendanceService.delete(id);
      
      if (response.success) {
        toast.success(response.message || 'Absensi berhasil dihapus');
        await getHistory(); // Refresh list
        await getStatistics(); // Refresh stats
        return true;
      } else {
        throw new Error(response.message || 'Gagal menghapus absensi');
      }
    } catch (error) {
      console.error('Error deleting attendance:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Gagal menghapus absensi';
      setError(errorMessage);
      toast.error(errorMessage);
      throw error;
    } finally {
      setLoading(false);
    }
  }, [getHistory, getStatistics]);

  // Resolve missing checkout from pending list
  const resolvePendingCheckout = useCallback(async (attendanceId, data, pendingFilters = {}) => {
    try {
      setResolvingPending(true);
      setError(null);

      const response = await manualAttendanceService.resolveCheckout(attendanceId, data);

      if (response.success) {
        toast.success(response.message || 'Lupa tap-out berhasil diselesaikan');
        await Promise.all([
          getPendingCheckout(pendingFilters),
          getHistory(),
          getStatistics(),
        ]);
        return response.data;
      }

      throw new Error(response.message || 'Gagal menyelesaikan lupa tap-out');
    } catch (resolveError) {
      console.error('Error resolving pending checkout:', resolveError);
      const errorMessage = resolveError.message || 'Gagal menyelesaikan lupa tap-out';
      setError(errorMessage);
      toast.error(errorMessage);
      throw resolveError;
    } finally {
      setResolvingPending(false);
    }
  }, [getHistory, getPendingCheckout, getStatistics]);

  // Get audit logs for specific attendance
  const getAuditLogs = useCallback(async (attendanceId) => {
    try {
      setLoading(true);
      const response = await manualAttendanceService.getAuditLogs(attendanceId);
      
      if (response.success) {
        return response.data || [];
      } else {
        throw new Error(response.message || 'Gagal mengambil log audit');
      }
    } catch (error) {
      console.error('Error fetching audit logs:', error);
      toast.error('Gagal mengambil log audit');
      return [];
    } finally {
      setLoading(false);
    }
  }, []);

  // Export data
  const exportData = useCallback(async (format = 'excel') => {
    try {
      setLoading(true);
      const response = await manualAttendanceService.export({ ...filters, format });
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response]));
      const link = document.createElement('a');
      link.href = url;
      const extension = format === 'csv' ? 'csv' : 'xls';
      link.setAttribute('download', `manual-attendance-${getServerDateString()}.${extension}`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      toast.success('Data berhasil diekspor');
    } catch (error) {
      console.error('Error exporting data:', error);
      toast.error('Gagal mengekspor data');
    } finally {
      setLoading(false);
    }
  }, [filters]);

  const bulkCreateAttendance = useCallback(async (attendanceList) => {
    try {
      setLoading(true);
      setError(null);

      const response = await manualAttendanceService.bulkCreate(attendanceList);
      const summary = response?.data || {};

      if (response.success) {
        toast.success(response.message || 'Absensi massal berhasil dibuat');
      } else if ((summary.failed_count || 0) > 0 && (summary.success_count || 0) > 0) {
        toast.success(`${summary.success_count} data berhasil diproses, ${summary.failed_count} gagal`);
      } else {
        throw new Error(response.message || 'Gagal memproses absensi massal');
      }

      await getHistory();
      await getStatistics();

      return response.data;
    } catch (bulkError) {
      console.error('Error bulk creating attendance:', bulkError);
      const errorMessage = bulkError.response?.data?.message || bulkError.message || 'Gagal memproses absensi massal';
      setError(errorMessage);
      toast.error(errorMessage);
      throw bulkError;
    } finally {
      setLoading(false);
    }
  }, [getHistory, getStatistics]);

  const previewBulkAttendance = useCallback(async (operation, attendanceList) => {
    try {
      setLoading(true);
      setError(null);

      const response = await manualAttendanceService.bulkPreview(operation, attendanceList);
      if (!response.success) {
        throw new Error(response.message || 'Gagal membuat pratinjau absensi massal');
      }

      return response.data;
    } catch (previewError) {
      console.error('Error previewing bulk attendance:', previewError);
      const errorMessage = previewError.response?.data?.message || previewError.message || 'Gagal membuat pratinjau absensi massal';
      setError(errorMessage);
      toast.error(errorMessage);
      throw previewError;
    } finally {
      setLoading(false);
    }
  }, []);

  const bulkCorrectAttendance = useCallback(async (attendanceList) => {
    try {
      setLoading(true);
      setError(null);

      const response = await manualAttendanceService.bulkCorrect(attendanceList);
      const summary = response?.data || {};

      if (response.success) {
        toast.success(response.message || 'Koreksi massal berhasil diproses');
      } else if ((summary.failed_count || 0) > 0 && (summary.success_count || 0) > 0) {
        toast.success(`${summary.success_count} data berhasil dikoreksi, ${summary.failed_count} gagal`);
      } else {
        throw new Error(response.message || 'Gagal memproses koreksi massal');
      }

      await Promise.all([
        getHistory(),
        getStatistics(),
        getPendingCheckout(),
      ]);

      return response.data;
    } catch (bulkError) {
      console.error('Error bulk correcting attendance:', bulkError);
      const errorMessage = bulkError.response?.data?.message || bulkError.message || 'Gagal memproses koreksi massal';
      setError(errorMessage);
      toast.error(errorMessage);
      throw bulkError;
    } finally {
      setLoading(false);
    }
  }, [getHistory, getPendingCheckout, getStatistics]);

  const previewIncidentAttendance = useCallback(async (payload) => {
    try {
      setLoading(true);
      setError(null);
      const response = await manualAttendanceService.previewIncident(payload);
      if (!response.success) {
        throw new Error(response.message || 'Gagal membuat pratinjau insiden server');
      }

      return response.data;
    } catch (previewError) {
      console.error('Error previewing attendance incident:', previewError);
      const errorMessage = previewError.response?.data?.message || previewError.message || 'Gagal membuat pratinjau insiden server';
      setError(errorMessage);
      toast.error(errorMessage);
      throw previewError;
    } finally {
      setLoading(false);
    }
  }, []);

  const createIncidentAttendance = useCallback(async (payload) => {
    try {
      setLoading(true);
      setError(null);
      const response = await manualAttendanceService.createIncident(payload);
      if (!response.success) {
        throw new Error(response.message || 'Gagal menjadwalkan insiden server');
      }

      toast.success(response.message || 'Insiden server berhasil dijadwalkan');
      await getRecentIncidentBatches();
      return response.data;
    } catch (createError) {
      console.error('Error creating attendance incident:', createError);
      const errorMessage = createError.response?.data?.message || createError.message || 'Gagal menjadwalkan insiden server';
      setError(errorMessage);
      toast.error(errorMessage);
      throw createError;
    } finally {
      setLoading(false);
    }
  }, [getRecentIncidentBatches]);

  const getIncidentAttendanceStatus = useCallback(async (batchId) => {
    try {
      const response = await manualAttendanceService.getIncident(batchId);
      if (!response.success) {
        throw new Error(response.message || 'Gagal mengambil status insiden server');
      }

      return response.data;
    } catch (fetchError) {
      console.error('Error fetching attendance incident status:', fetchError);
      throw fetchError;
    }
  }, []);

  const exportIncidentAttendance = useCallback(async (batchId, format = 'xlsx', resultGroup = 'all') => {
    try {
      const response = await manualAttendanceService.exportIncident(batchId, format, resultGroup);
      const blob = new Blob([response.data], {
        type: response.headers?.['content-type'] || 'application/octet-stream',
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;

      const disposition = response.headers?.['content-disposition'] || '';
      const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i);
      const extension = format === 'csv' ? 'csv' : 'xlsx';
      link.setAttribute('download', fileNameMatch?.[1] || `manual-attendance-incident-${batchId}-${resultGroup}-${getServerDateString()}.${extension}`);

      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success('Hasil batch berhasil diunduh');
    } catch (exportError) {
      console.error('Error exporting attendance incident batch:', exportError);
      const errorMessage = exportError.message || 'Gagal mengekspor hasil batch insiden server';
      setError(errorMessage);
      toast.error(errorMessage);
      throw exportError;
    }
  }, []);

  // Validate attendance data
  const validateAttendanceData = useCallback((data) => {
    const errors = {};

    if (!data.user_id) {
      errors.user_id = 'Pengguna harus dipilih';
    }

    if (!data.tanggal) {
      errors.tanggal = 'Tanggal harus diisi';
    } else if (String(data.tanggal) > getServerDateString()) {
      errors.tanggal = 'Tanggal tidak boleh lebih dari hari ini';
    }

    if (!data.status) {
      errors.status = 'Status harus dipilih';
    }

    if (data.jam_masuk && data.jam_pulang) {
      const jamMasuk = toTimeMinutes(data.jam_masuk);
      const jamPulang = toTimeMinutes(data.jam_pulang);

      if (jamMasuk !== null && jamPulang !== null && jamPulang <= jamMasuk) {
        errors.jam_pulang = 'Jam pulang harus lebih besar dari jam masuk';
      }
    }

    if (!data.reason || data.reason.trim().length < 10) {
      errors.reason = 'Alasan harus diisi minimal 10 karakter';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }, []);

  // Initialize data on mount
  useEffect(() => {
    getUsers();
    getHistory();
    getStatistics();
    getRecentIncidentBatches();
  }, []);

  // Refresh data when filters change
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      getHistory();
    }, 500); // Debounce filter changes

    return () => clearTimeout(timeoutId);
  }, [filters, getHistory]);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      getStatistics();
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [filters.status, filters.date, filters.user_id, filters.start_date, filters.end_date, getStatistics]);

  return {
    // State
    attendanceList,
    pendingCheckoutList,
    pendingCheckoutMeta,
    historyMeta,
    users,
    statistics,
    loading,
    pendingLoading,
    resolvingPending,
    error,
    filters,
    
    // Actions
    setFilters,
    getUsers,
    getHistory,
    getStatistics,
    getPendingCheckout,
    createAttendance,
    updateAttendance,
    previewBulkAttendance,
    bulkCreateAttendance,
    bulkCorrectAttendance,
    incidentOptions,
    incidentOptionsLoading,
    recentIncidentBatches,
    recentIncidentBatchesLoading,
    recentIncidentBatchesRefreshedAt,
    getIncidentOptions,
    getRecentIncidentBatches,
    previewIncidentAttendance,
    createIncidentAttendance,
    getIncidentAttendanceStatus,
    exportIncidentAttendance,
    deleteAttendance,
    resolvePendingCheckout,
    getAuditLogs,
    exportData,
    validateAttendanceData,
    
    // Computed values
    totalAttendance: historyMeta.total || attendanceList.length,
    hasData: attendanceList.length > 0,
    
    // Helper functions
    refreshData: useCallback(() => {
      getHistory();
      getStatistics();
    }, [getHistory, getStatistics]),
    
    clearError: useCallback(() => setError(null), []),
    
    resetFilters: useCallback(() => {
      setFilters({
        bucket: 'manual',
        status: '',
        date: '',
        user_id: '',
        start_date: '',
        end_date: '',
        search: '',
        page: 1,
        per_page: 15,
      });
    }, [])
  };
};

export default useManualAttendance;

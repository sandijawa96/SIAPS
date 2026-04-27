import { useState, useCallback } from 'react';
import toast from 'react-hot-toast';
import { getServerIsoString } from '../services/serverClock';

export const useErrorHandling = () => {
  const [errors, setErrors] = useState([]);
  const [isRetrying, setIsRetrying] = useState(false);

  const parseError = useCallback((error, siswa = null, context = '') => {
    let errorType = 'unknown';
    let message = 'Terjadi kesalahan yang tidak diketahui';
    let details = null;
    let suggestions = [];

    // Network errors
    if (!error.response) {
      errorType = 'network';
      message = 'Koneksi ke server terputus. Periksa koneksi internet Anda.';
      suggestions = [
        'Periksa koneksi internet',
        'Coba refresh halaman',
        'Hubungi administrator jika masalah berlanjut'
      ];
    }
    // HTTP errors
    else if (error.response) {
      const status = error.response.status;
      const data = error.response.data;

      switch (status) {
        case 400:
          errorType = 'validation';
          message = data.message || 'Data yang dikirim tidak valid';
          if (data.errors) {
            details = data.errors;
            suggestions = [
              'Periksa kembali data siswa',
              'Pastikan semua field wajib terisi',
              'Pastikan format data sudah benar'
            ];
          }
          break;

        case 401:
          errorType = 'auth';
          message = 'Sesi Anda telah berakhir. Silakan login kembali.';
          suggestions = [
            'Login ulang ke sistem',
            'Periksa kredensial Anda'
          ];
          break;

        case 403:
          errorType = 'permission';
          message = 'Anda tidak memiliki izin untuk melakukan operasi ini';
          suggestions = [
            'Hubungi administrator untuk mendapatkan izin',
            'Periksa role dan permission Anda'
          ];
          break;

        case 404:
          errorType = 'not_found';
          message = 'Data siswa atau kelas tidak ditemukan';
          suggestions = [
            'Periksa apakah siswa masih terdaftar',
            'Periksa apakah kelas masih aktif',
            'Refresh data dan coba lagi'
          ];
          break;

        case 409:
          errorType = 'conflict';
          message = data.message || 'Terjadi konflik data. Siswa mungkin sudah diproses sebelumnya.';
          suggestions = [
            'Periksa status siswa saat ini',
            'Refresh data untuk melihat perubahan terbaru',
            'Pastikan siswa belum diproses sebelumnya'
          ];
          break;

        case 422:
          errorType = 'validation';
          message = data.message || 'Data tidak memenuhi kriteria validasi';
          if (data.errors) {
            details = data.errors;
            // Create specific suggestions based on validation errors
            const validationSuggestions = [];
            Object.keys(data.errors).forEach(field => {
              if (field.includes('kelas')) {
                validationSuggestions.push('Pilih kelas tujuan yang valid');
              }
              if (field.includes('tahun_ajaran')) {
                validationSuggestions.push('Pilih tahun ajaran yang aktif');
              }
              if (field.includes('tanggal')) {
                validationSuggestions.push('Periksa format tanggal');
              }
            });
            suggestions = validationSuggestions.length > 0 ? validationSuggestions : [
              'Periksa kembali semua data yang diinput',
              'Pastikan format data sudah benar'
            ];
          }
          break;

        case 500:
          errorType = 'server';
          message = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
          suggestions = [
            'Coba lagi dalam beberapa menit',
            'Hubungi administrator jika masalah berlanjut',
            'Periksa log sistem untuk detail lebih lanjut'
          ];
          break;

        default:
          errorType = 'server';
          message = data.message || `Kesalahan server (${status})`;
          suggestions = [
            'Coba lagi dalam beberapa menit',
            'Hubungi administrator jika masalah berlanjut'
          ];
      }
    }

    // Add context-specific suggestions
    if (context === 'naik-kelas') {
      suggestions.push('Pastikan kelas tujuan memiliki kapasitas yang cukup');
      suggestions.push('Periksa apakah siswa memenuhi syarat naik kelas');
    } else if (context === 'lulus') {
      suggestions.push('Pastikan siswa sudah menyelesaikan semua persyaratan');
      suggestions.push('Periksa status akademik siswa');
    } else if (context === 'keluar') {
      suggestions.push('Pastikan alasan keluar sudah valid');
      suggestions.push('Periksa dokumen pendukung');
    }

    return {
      type: errorType,
      message,
      details,
      suggestions: [...new Set(suggestions)], // Remove duplicates
      siswa,
      timestamp: getServerIsoString(),
      context
    };
  }, []);

  const addError = useCallback((error, siswa = null, context = '') => {
    const parsedError = parseError(error, siswa, context);
    setErrors(prev => [...prev, parsedError]);
    return parsedError;
  }, [parseError]);

  const clearErrors = useCallback(() => {
    setErrors([]);
  }, []);

  const removeError = useCallback((index) => {
    setErrors(prev => prev.filter((_, i) => i !== index));
  }, []);

  const retryFailedOperations = useCallback(async (retryFunction, failedItems = []) => {
    if (!retryFunction || failedItems.length === 0) return;

    setIsRetrying(true);
    const retryErrors = [];
    let successCount = 0;

    try {
      for (const item of failedItems) {
        try {
          await retryFunction(item);
          successCount++;
        } catch (error) {
          const parsedError = parseError(error, item.siswa, item.context);
          retryErrors.push(parsedError);
        }
      }

      // Update errors with retry results
      setErrors(retryErrors);

      if (successCount > 0) {
        toast.success(`${successCount} operasi berhasil di-retry`);
      }

      if (retryErrors.length > 0) {
        toast.error(`${retryErrors.length} operasi masih gagal`);
      }

      return {
        success: successCount,
        failed: retryErrors.length,
        errors: retryErrors
      };
    } finally {
      setIsRetrying(false);
    }
  }, [parseError]);

  const getErrorSummary = useCallback(() => {
    const summary = {
      total: errors.length,
      byType: {},
      bySeverity: {
        critical: 0,
        warning: 0,
        info: 0
      }
    };

    errors.forEach(error => {
      // Count by type
      summary.byType[error.type] = (summary.byType[error.type] || 0) + 1;

      // Count by severity
      if (['server', 'network', 'auth'].includes(error.type)) {
        summary.bySeverity.critical++;
      } else if (['validation', 'conflict'].includes(error.type)) {
        summary.bySeverity.warning++;
      } else {
        summary.bySeverity.info++;
      }
    });

    return summary;
  }, [errors]);

  const exportErrorReport = useCallback(() => {
    const report = {
      timestamp: getServerIsoString(),
      summary: getErrorSummary(),
      errors: errors.map(error => ({
        ...error,
        siswa: error.siswa ? {
          id: error.siswa.id,
          nama: error.siswa.nama,
          nis: error.siswa.nis,
          nisn: error.siswa.nisn
        } : null
      }))
    };

    return report;
  }, [errors, getErrorSummary]);

  const hasErrors = errors.length > 0;
  const hasCriticalErrors = errors.some(error => 
    ['server', 'network', 'auth'].includes(error.type)
  );

  return {
    errors,
    hasErrors,
    hasCriticalErrors,
    isRetrying,
    addError,
    clearErrors,
    removeError,
    retryFailedOperations,
    getErrorSummary,
    exportErrorReport,
    parseError
  };
};

export default useErrorHandling;

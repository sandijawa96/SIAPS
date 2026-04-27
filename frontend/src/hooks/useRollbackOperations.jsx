import { useState, useCallback } from 'react';
import { siswaExtendedAPI } from '../services/siswaExtendedService';
import toast from 'react-hot-toast';
import { formatServerDateTime, getServerNowDate } from '../services/serverClock';

export const useRollbackOperations = () => {
  const [loading, setLoading] = useState(false);
  const [bulkRollbackLoading, setBulkRollbackLoading] = useState(false);

  const rollbackSingleTransisi = useCallback(async (siswaId, transisi, options = {}) => {
    try {
      setLoading(true);
      
      const { onSuccess, onError, showToast = true } = options;
      
      const serverNowLabel = formatServerDateTime(getServerNowDate(), 'id-ID') || '-';
      let response;
      const rollbackData = {
        transisi_id: transisi.id,
        keterangan: `Rollback ${transisi.type} - ${serverNowLabel}`,
        ...options.data
      };

      switch (transisi.type) {
        case 'naik_kelas':
        case 'pindah_kelas':
          response = await siswaExtendedAPI.rollbackToKelas(siswaId, rollbackData);
          break;
        case 'lulus':
          response = await siswaExtendedAPI.batalkanKelulusan(siswaId, rollbackData);
          break;
        case 'keluar':
          response = await siswaExtendedAPI.kembalikanSiswa(siswaId, rollbackData);
          break;
        default:
          response = await siswaExtendedAPI.undoTransisi(siswaId, transisi.id);
      }

      if (response.data.success) {
        if (showToast) {
          toast.success(response.data.message || 'Transisi berhasil dibatalkan');
        }
        if (onSuccess) onSuccess(response.data);
        return { success: true, data: response.data };
      }
    } catch (error) {
      console.error('Error rolling back transisi:', error);
      const errorMessage = error.response?.data?.message || 'Gagal membatalkan transisi';
      
      if (options.showToast !== false) {
        toast.error(errorMessage);
      }
      if (options.onError) options.onError(error);
      
      return { success: false, error: errorMessage };
    } finally {
      setLoading(false);
    }
  }, []);

  const bulkRollbackTransisi = useCallback(async (rollbackData, options = {}) => {
    try {
      setBulkRollbackLoading(true);
      
      const { onProgress, onSuccess, onError, showToast = true } = options;
      
      const results = await Promise.allSettled(
        rollbackData.map(async (item, index) => {
          try {
            if (onProgress) {
              onProgress(index + 1, rollbackData.length, item);
            }
            
            const result = await rollbackSingleTransisi(
              item.siswaId, 
              item.transisi, 
              { ...options, showToast: false }
            );
            
            return { success: true, item, result };
          } catch (error) {
            return { success: false, item, error };
          }
        })
      );

      const successful = results.filter(result => 
        result.status === 'fulfilled' && result.value.success
      ).length;

      const failed = results.filter(result => 
        result.status === 'rejected' || 
        (result.status === 'fulfilled' && !result.value.success)
      ).length;

      const summary = {
        total: rollbackData.length,
        successful,
        failed,
        results
      };

      if (showToast) {
        if (successful > 0) {
          toast.success(`${successful} transisi berhasil dibatalkan`);
        }
        if (failed > 0) {
          toast.error(`${failed} transisi gagal dibatalkan`);
        }
      }

      if (onSuccess) onSuccess(summary);
      return summary;

    } catch (error) {
      console.error('Error in bulk rollback:', error);
      const errorMessage = 'Gagal melakukan rollback massal';
      
      if (showToast) {
        toast.error(errorMessage);
      }
      if (onError) onError(error);
      
      return { success: false, error: errorMessage };
    } finally {
      setBulkRollbackLoading(false);
    }
  }, [rollbackSingleTransisi]);

  const validateRollback = useCallback((transisi) => {
    // Validasi apakah transisi dapat di-rollback
    const transisiEpochMs = Date.parse(transisi?.tanggal_transisi || '');
    if (Number.isNaN(transisiEpochMs)) {
      return {
        canUndo: false,
        reasons: ['Tanggal transisi tidak valid'],
      };
    }

    const now = getServerNowDate();
    const hoursDiff = (now.getTime() - transisiEpochMs) / (1000 * 60 * 60);
    
    const validations = {
      canUndo: true,
      reasons: []
    };

    // Cek waktu (24 jam)
    if (hoursDiff > 24) {
      validations.canUndo = false;
      validations.reasons.push('Transisi sudah lebih dari 24 jam');
    }

    // Cek apakah sudah di-undo
    if (transisi.is_undone) {
      validations.canUndo = false;
      validations.reasons.push('Transisi sudah dibatalkan sebelumnya');
    }

    // Cek flag can_undo dari backend
    if (!transisi.can_undo) {
      validations.canUndo = false;
      validations.reasons.push('Transisi tidak dapat dibatalkan');
    }

    // Validasi khusus berdasarkan tipe
    switch (transisi.type) {
      case 'lulus':
        if (transisi.has_certificate_issued) {
          validations.canUndo = false;
          validations.reasons.push('Sertifikat sudah diterbitkan');
        }
        break;
      case 'keluar':
        if (transisi.final_documents_issued) {
          validations.canUndo = false;
          validations.reasons.push('Dokumen final sudah diterbitkan');
        }
        break;
    }

    return validations;
  }, []);

  const getUndoableTransisi = useCallback((riwayatTransisi) => {
    return riwayatTransisi.filter(transisi => {
      const validation = validateRollback(transisi);
      return validation.canUndo;
    });
  }, [validateRollback]);

  return {
    loading,
    bulkRollbackLoading,
    rollbackSingleTransisi,
    bulkRollbackTransisi,
    validateRollback,
    getUndoableTransisi
  };
};

export default useRollbackOperations;

import { useState, useCallback, useEffect } from 'react';
import { useSnackbar } from 'notistack';
import pegawaiService from '../services/pegawaiService.jsx';
import siswaService from '../services/siswaService.jsx';
import { getServerDateString, getServerNowEpochMs, getServerTimeString } from '../services/serverClock';

export const useUserModalManagement = (activeTab, onSuccess) => {
  // Modal states
  const [state, setState] = useState({
    showTambahPegawai: false,
    showTambahSiswa: false,
    showEditModal: false,
    showResetPasswordModal: false,
    showImportModal: false,
    showExportModal: false,
    selectedUser: null,
    importProgress: 0,
    exportProgress: 0,
    isImporting: false
  });

  const { enqueueSnackbar } = useSnackbar();

  // Update state helper
  const updateState = useCallback((updates) => {
    setState(prev => {
      const nextUpdates = typeof updates === 'function' ? updates(prev) : updates;
      return { ...prev, ...nextUpdates };
    });
  }, []);

  // Guard navigation while import is running.
  useEffect(() => {
    if (!state.isImporting) {
      return undefined;
    }

    const currentUrl = window.location.href;
    const guardState = { import_guard: true, ts: getServerNowEpochMs() };
    let warningCooldown = false;

    window.history.pushState(guardState, document.title, currentUrl);

    const handlePopState = () => {
      window.history.pushState(guardState, document.title, currentUrl);
      if (!warningCooldown) {
        warningCooldown = true;
        enqueueSnackbar('Import sedang berjalan. Tunggu hingga selesai sebelum pindah halaman.', {
          variant: 'warning'
        });
        setTimeout(() => {
          warningCooldown = false;
        }, 1500);
      }
    };

    const handleBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '';
      return '';
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [state.isImporting, enqueueSnackbar]);

  // Modal handlers
  const openModal = useCallback((modalType, user = null) => {
    const updates = { selectedUser: user };
    
    switch (modalType) {
      case 'tambah':
        updates[activeTab === 'pegawai' ? 'showTambahPegawai' : 'showTambahSiswa'] = true;
        break;
      case 'edit':
        updates.showEditModal = true;
        break;
      case 'resetPassword':
        updates.showResetPasswordModal = true;
        break;
      case 'import':
        updates.showImportModal = true;
        updates.importProgress = 0;
        break;
      case 'export':
        updates.showExportModal = true;
        updates.exportProgress = 0;
        break;
      default:
        break;
    }

    updateState(updates);
  }, [activeTab, updateState]);

  const closeModal = useCallback((modalType) => {
    const updates = { selectedUser: null };
    
    switch (modalType) {
      case 'tambah':
        updates[activeTab === 'pegawai' ? 'showTambahPegawai' : 'showTambahSiswa'] = false;
        break;
      case 'edit':
        updates.showEditModal = false;
        break;
      case 'resetPassword':
        updates.showResetPasswordModal = false;
        break;
      case 'import':
        updates.showImportModal = false;
        updates.importProgress = 0;
        break;
      case 'export':
        updates.showExportModal = false;
        updates.exportProgress = 0;
        break;
      default:
        break;
    }

    updateState(updates);
  }, [activeTab, updateState]);

  // Import handler
  const handleImport = useCallback(async (formData) => {
    updateState({ importProgress: 0, isImporting: true });
    
    try {
      const service = activeTab === 'pegawai' ? pegawaiService : siswaService;
      
      // Start progress simulation
      const progressInterval = setInterval(() => {
        updateState(prev => ({
          importProgress: Math.min(prev.importProgress + 10, 90)
        }));
      }, 500);

      const result = await service.import(formData);

      clearInterval(progressInterval);
      updateState({ importProgress: 100 });

      if (result.success) {
        enqueueSnackbar(
          `Berhasil mengimpor ${result.data?.imported || 0} data ${activeTab}`,
          { variant: 'success' }
        );

        closeModal('import');
        if (onSuccess) onSuccess();

        return {
          success: true,
          message: result.message,
          imported: result.data?.imported || 0,
          updated: result.data?.updated || 0,
          details: result.data
        };
      } else {
        const importError = new Error(result.message || 'Gagal mengimpor data');
        importError.details = result.data || null;
        throw importError;
      }
    } catch (error) {
      console.error('Import error:', error);
      updateState({ importProgress: 0 });
      
      // Handle validation errors
      if (error.response?.data?.errors) {
        const errorMessages = Object.values(error.response.data.errors).flat();
        throw new Error(`Validasi gagal: ${errorMessages.join(', ')}`);
      }
      
      throw error;
    } finally {
      updateState({ isImporting: false });
    }
  }, [activeTab, updateState, closeModal, onSuccess, enqueueSnackbar]);

  // Export handler
  const handleExport = useCallback(async () => {
    updateState({ exportProgress: 0 });
    
    try {
      const service = activeTab === 'pegawai' ? pegawaiService : siswaService;
      
      // Start progress simulation
      const progressInterval = setInterval(() => {
        updateState(prev => ({
          exportProgress: Math.min(prev.exportProgress + 20, 90)
        }));
      }, 300);

      const response = await service.ekspor();

      clearInterval(progressInterval);
      updateState({ exportProgress: 100 });

      // Create and trigger download
      const blob = new Blob([response.data], { 
        type: 'application/vnd.ms-excel'
      });
      
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      
      const date = getServerDateString() || 'unknown-date';
      const time = (getServerTimeString() || '00:00:00').replace(/:/g, '-');
      const filename = `Data_${activeTab === 'pegawai' ? 'Pegawai' : 'Siswa'}_${date}_${time}.xlsx`;
      
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      closeModal('export');
      enqueueSnackbar(
        `Data ${activeTab} berhasil diekspor ke Excel`,
        { variant: 'success' }
      );
    } catch (error) {
      console.error('Export error:', error);
      updateState({ exportProgress: 0 });
      
      enqueueSnackbar(
        error.response?.data?.message || `Gagal mengekspor data ${activeTab}`,
        { variant: 'error' }
      );
    }
  }, [activeTab, updateState, closeModal, enqueueSnackbar]);

  return {
    // State
    ...state,
    
    // Modal actions
    openModal,
    closeModal,
    
    // Import/Export actions
    handleImport,
    handleExport,
    
    // State updater
    updateState
  };
};

export default useUserModalManagement;

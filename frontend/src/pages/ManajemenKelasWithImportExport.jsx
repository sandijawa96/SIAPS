import React, { useState, useEffect, useMemo } from 'react';
import { kelasAPI } from '../services/kelasService';
import { tingkatAPI } from '../services/tingkatService';
import { TAHUN_AJARAN_STATUS } from '../services/tahunAjaranService';
import toast from 'react-hot-toast';
import { AlertTriangle, Users, Menu, BookOpen, Edit, Trash2 } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';

// Import hooks
import { useRealtimeKelasManagement } from '../hooks/useRealtimeKelasManagement';
import {
  useTingkatManagement,
  usePegawaiManagement,
  useKelasModals,
  useTahunAjaranManagement
} from '../hooks';

// Import components
import {
  KelasCard,
  TingkatCard,
  KelasStatistics,
  KelasSearch,
  KelasTabs,
  RealtimeStatus,
  LoadingState,
  NotificationContainer,
  useRealtimeNotifications
} from '../components/kelas';

import TahunAjaranSelector from '../components/kelas/TahunAjaranSelector';

import KelasHeader from '../components/kelas/KelasHeaderUpdated';
import ImportExportModal from '../components/kelas/modals/ImportExportModal';
import ViewSiswaModal from '../components/kelas/modals/ViewSiswaModal';
import ViewTingkatKelasModal from '../components/kelas/modals/ViewTingkatKelasModal';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';

import {
  KelasFormModal,
  TingkatFormModal,
  BulkAssignWaliModal,
  TahunAjaranFormModal
} from '../components/modals';

const ManajemenKelas = () => {
  const { hasPermission, hasAnyRole } = useAuth();
  const canViewKelas = hasPermission('view_kelas') || hasPermission('manage_kelas');
  const canManageKelas = hasPermission('manage_kelas');
  const canManageStudents = hasPermission('manage_students');
  const canManageTahunAjaran = hasPermission('manage_tahun_ajaran');
  const canManageStudentTransitions = canManageStudents || hasAnyRole([
    'Wali Kelas',
    'Wakasek Kurikulum',
    'Admin',
    'Super Admin',
  ]);
  const canManagePromotionWindow = hasPermission('manage_tahun_ajaran') || hasAnyRole([
    'Wakasek Kurikulum',
    'Admin',
    'Super Admin',
  ]);

  const [activeTab, setActiveTab] = useState('kelas');
  const [showImportExportModal, setShowImportExportModal] = useState(false);
  const [showTahunAjaranModal, setShowTahunAjaranModal] = useState(false);

  // Realtime notifications
  const { notifications, addNotification, removeNotification } = useRealtimeNotifications();

  // Custom hooks
  const {
    loading: kelasLoading,
    kelasList,
    activeTahunAjaran,
    selectedTahunAjaran,
    setSelectedTahunAjaran,
    tahunAjaranList,
    viewMode,
    setViewMode,
    searchTerm,
    setSearchTerm,
    handleDeleteKelas,
    refreshKelas,
    refreshAll,
    getTargetTahunAjaran,
    canCreateKelas,
    lastUpdated,
    isRefreshing,
    error
  } = useRealtimeKelasManagement();

  const {
    createTahunAjaran,
    updateTahunAjaran,
    transitionStatus,
    updateProgress
  } = useTahunAjaranManagement();

  const {
    loading: tingkatLoading,
    tingkatList,
    handleDeleteTingkat,
    refreshTingkat
  } = useTingkatManagement();

  const {
    pegawaiList,
    getAvailablePegawai
  } = usePegawaiManagement();

  const {
    showModal,
    selectedItem,
    showSiswaModal,
    selectedKelasForSiswa,
    showConfirmModal,
    confirmAction,
    confirmData,
    showBulkAssignModal,
    selectedClassesForWali,
    setSelectedClassesForWali,
    bulkWaliAssignments,
    setBulkWaliAssignments,
    showTingkatKelasModal,
    selectedTingkatForModal,
    tingkatKelasList,
    openAddModal,
    openEditModal,
    closeModal,
    openConfirmModal,
    closeConfirmModal,
    openBulkAssignModal,
    closeBulkAssignModal,
    openSiswaModal,
    closeSiswaModal,
    openTingkatKelasModal,
    closeTingkatKelasModal
  } = useKelasModals();

  const loading = kelasLoading || tingkatLoading;

  // Filtered data
  const filteredKelas = useMemo(() => {
    if (!Array.isArray(kelasList)) return [];
    return kelasList.filter(kelas => 
      kelas.namaKelas?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      kelas.waliKelas?.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [kelasList, searchTerm]);

  const filteredTingkat = useMemo(() => {
    return tingkatList.filter(tingkat => 
      tingkat.nama.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [tingkatList, searchTerm]);

  const effectiveTahunAjaran = useMemo(() => {
    return getTargetTahunAjaran() || activeTahunAjaran || null;
  }, [getTargetTahunAjaran, activeTahunAjaran]);

  // Handle view siswa
  const handleViewSiswa = async (kelas) => {
    try {
      const response = await kelasAPI.getSiswa(kelas.id);
      openSiswaModal({
        ...kelas,
        siswa: Array.isArray(response.data.data) ? response.data.data : []
      });
    } catch (error) {
      toast.error('Gagal memuat data siswa');
    }
  };

  // Handle view tingkat kelas
  const handleViewTingkatKelas = async (tingkat) => {
    try {
      const response = await kelasAPI.getByTingkat(tingkat.id);
      const kelasList = Array.isArray(response.data) ? response.data : [];
      openTingkatKelasModal(tingkat, kelasList);
    } catch (error) {
      toast.error('Gagal memuat data kelas');
    }
  };

  // Handle delete siswa
  const handleDeleteSiswa = (siswaId, siswaName) => {
    if (!canManageKelas) {
      toast.error('Anda tidak memiliki izin untuk mengubah anggota kelas');
      return;
    }

    openConfirmModal(
      { 
        id: siswaId, 
        name: siswaName, 
        type: 'delete-siswa',
        message: `Apakah Anda yakin ingin menonaktifkan siswa "${siswaName}" dari kelas ini?`
      },
      async () => {
        try {
          const response = await kelasAPI.removeSiswa(selectedKelasForSiswa.id, siswaId);
          if (response.data.success) {
            toast.success(response.data.message || 'Siswa berhasil dinonaktifkan dari kelas');
            // Refresh data siswa
            const refreshResponse = await kelasAPI.getSiswa(selectedKelasForSiswa.id);
            openSiswaModal({
              ...selectedKelasForSiswa,
              siswa: Array.isArray(refreshResponse.data?.data) ? refreshResponse.data.data : []
            });
          }
        } catch (error) {
          toast.error(error.response?.data?.message || 'Gagal menonaktifkan siswa dari kelas');
        }
      }
    );
  };

  // Handle submit kelas
  const handleSubmitKelas = async (e) => {
    e.preventDefault();

    if (!canManageKelas) {
      toast.error('Anda tidak memiliki izin untuk mengelola kelas');
      return;
    }

    const formData = new FormData(e.target);
    const targetTahunAjaran = getTargetTahunAjaran();
    const resolvedTahunAjaranId = Number(
      selectedItem?.tahun_ajaran_id || targetTahunAjaran?.id || 0
    );
    
    if (!resolvedTahunAjaranId) {
      toast.error('Pilih tahun ajaran terlebih dahulu.');
      return;
    }

    if (!selectedItem && !canCreateKelas()) {
      toast.error(`Tidak dapat membuat kelas untuk tahun ajaran dengan status ${targetTahunAjaran.status}`);
      return;
    }

    const data = {
      nama_kelas: formData.get('namaKelas'),
      tingkat_id: formData.get('tingkat'),
      kapasitas: parseInt(formData.get('kapasitas')),
      tahun_ajaran_id: resolvedTahunAjaranId,
      wali_kelas_id: formData.get('wali_kelas_id') || null,
      keterangan: formData.get('keterangan') || ''
    };

    try {
      if (selectedItem) {
        await kelasAPI.update(selectedItem.id, data);
        toast.success('Kelas berhasil diperbarui');
      } else {
        await kelasAPI.create(data);
        toast.success('Kelas berhasil ditambahkan');
      }
      await refreshKelas();
      closeModal();
    } catch (error) {
      const errorMessage = selectedItem ? 'Gagal memperbarui kelas' : 'Gagal menambahkan kelas';
      toast.error(errorMessage);
    }
  };

  // Handle submit tingkat
  const handleSubmitTingkat = async (e) => {
    e.preventDefault();

    if (!canManageKelas) {
      toast.error('Anda tidak memiliki izin untuk mengelola tingkat');
      return;
    }

    const formData = new FormData(e.target);
    const data = {
      nama: formData.get('nama'),
      kode: formData.get('kode'),
      deskripsi: formData.get('deskripsi'),
      urutan: selectedItem ? selectedItem.urutan : null
    };

    try {
      if (selectedItem) {
        await tingkatAPI.update(selectedItem.id, data);
        toast.success('Tingkat berhasil diperbarui');
      } else {
        await tingkatAPI.create(data);
        toast.success('Tingkat berhasil ditambahkan');
      }
      await refreshTingkat();
      closeModal();
    } catch (error) {
      const errorMessage = selectedItem ? 'Gagal memperbarui tingkat' : 'Gagal menambahkan tingkat';
      toast.error(errorMessage);
    }
  };

  // Handle bulk assign wali kelas
  const handleBulkAssignWali = async () => {
    if (!canManageKelas) {
      toast.error('Anda tidak memiliki izin untuk menugaskan wali kelas');
      return;
    }

    try {
      const targetTahunAjaran = getTargetTahunAjaran();
      const updates = selectedClassesForWali.map(async (kelasId) => {
        const waliKelasId = bulkWaliAssignments[kelasId];
        if (!waliKelasId) return;
        
        const kelas = kelasList.find(k => k.id === kelasId);
        if (!kelas) return;

        const resolvedTahunAjaranId = Number(
          kelas.tahun_ajaran_id || targetTahunAjaran?.id || activeTahunAjaran?.id || 0
        );
        if (!resolvedTahunAjaranId) {
          return;
        }
        
        await kelasAPI.update(kelasId, {
          nama_kelas: kelas.namaKelas,
          tingkat_id: kelas.tingkat_id,
          kapasitas: kelas.kapasitas,
          tahun_ajaran_id: resolvedTahunAjaranId,
          wali_kelas_id: waliKelasId,
          keterangan: kelas.keterangan || ''
        });
      });
      
      await Promise.all(updates);
      toast.success('Wali kelas berhasil ditugaskan');
      await refreshKelas();
      closeBulkAssignModal();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal menugaskan wali kelas');
    }
  };

  // Handle import/export success
  const handleImportExportSuccess = () => {
    refreshKelas();
    setShowImportExportModal(false);
  };

  if (!canViewKelas) {
    return (
      <div className="p-6">
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
          Anda tidak memiliki akses untuk melihat data kelas.
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 lg:p-6">
      {/* Realtime Status */}
      <RealtimeStatus
        lastUpdated={lastUpdated}
        isRefreshing={isRefreshing}
        onRefresh={refreshAll}
        error={error}
      />

      {/* Notifications */}
      <NotificationContainer
        notifications={notifications}
        onRemove={removeNotification}
      />

      {/* Header */}
      <KelasHeader
        activeTab={activeTab}
        activeTahunAjaran={activeTahunAjaran}
        onAddItem={() => {
          if (!canManageKelas) {
            toast.error('Anda tidak memiliki izin untuk menambah data kelas');
            return;
          }
          openAddModal();
        }}
        onBulkAssignWali={(selectedClasses) => {
          if (!canManageKelas) {
            toast.error('Anda tidak memiliki izin untuk menugaskan wali kelas');
            return;
          }
          openBulkAssignModal(selectedClasses);
        }}
        onImportExport={() => {
          if (!canManageKelas) {
            toast.error('Anda tidak memiliki izin untuk import/export data kelas');
            return;
          }
          setShowImportExportModal(true);
        }}
        onRefresh={activeTab === 'kelas' ? refreshKelas : refreshTingkat}
        kelasList={kelasList}
        loading={loading}
        canManageKelas={canManageKelas}
      />
      
      {/* Tabs */}
      <KelasTabs activeTab={activeTab} onTabChange={setActiveTab} />

      {/* Tahun Ajaran Selector - Only show for Kelas tab */}
      {activeTab === 'kelas' && (
        <TahunAjaranSelector
          tahunAjaranList={tahunAjaranList}
          selectedTahunAjaran={selectedTahunAjaran}
          setSelectedTahunAjaran={setSelectedTahunAjaran}
          viewMode={viewMode}
          setViewMode={setViewMode}
          canCreateKelas={canCreateKelas}
          getTargetTahunAjaran={getTargetTahunAjaran}
        />
      )}

      {/* Statistics */}
      <KelasStatistics kelasList={kelasList} />

      {/* Search */}
      <KelasSearch
        searchTerm={searchTerm}
        setSearchTerm={setSearchTerm}
        activeTab={activeTab}
      />

      {/* Content */}
      {activeTab === 'kelas' ? (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
          {loading ? (
            <div className="col-span-full flex justify-center items-center h-64">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
              <span className="ml-2">Memuat data...</span>
            </div>
          ) : (
            filteredKelas.map((kelas) => (
              <KelasCard
                key={kelas.id}
                kelas={kelas}
                onEdit={openEditModal}
                onDelete={(id, name) => openConfirmModal(
                  { 
                    id, 
                    name, 
                    type: 'delete-kelas',
                    message: `Apakah Anda yakin ingin menghapus kelas "${name}"? Tindakan ini tidak dapat dibatalkan.`
                  },
                  async () => {
                    try {
                      await handleDeleteKelas(id, name);
                      toast.success('Kelas berhasil dihapus');
                      await refreshKelas();
                    } catch (error) {
                      toast.error(error.response?.data?.message || 'Gagal menghapus kelas');
                    }
                  }
                )}
                onViewSiswa={handleViewSiswa}
                canManageKelas={canManageKelas}
              />
            ))
          )}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
          {filteredTingkat.map((tingkat) => (
            <TingkatCard
              key={tingkat.id}
              tingkat={tingkat}
              onEdit={openEditModal}
              onDelete={(id, name) => openConfirmModal(
                { 
                  id, 
                  name, 
                  type: 'delete-tingkat',
                  message: `Apakah Anda yakin ingin menghapus tingkat "${name}"? Tindakan ini tidak dapat dibatalkan.`
                },
                () => handleDeleteTingkat(id, name)
              )}
              onViewKelas={handleViewTingkatKelas}
              canManageKelas={canManageKelas}
            />
          ))}
        </div>
      )}

      {/* Modals */}
      {activeTab === 'kelas' ? (
        <KelasFormModal
          isOpen={showModal && canManageKelas}
          onClose={closeModal}
          onSubmit={handleSubmitKelas}
          selectedItem={selectedItem}
          tingkatList={tingkatList}
          pegawaiList={pegawaiList}
          activeTahunAjaran={activeTahunAjaran}
          getAvailablePegawai={(excludeWali) => getAvailablePegawai(excludeWali, kelasList)}
        />
      ) : (
        <TingkatFormModal
          isOpen={showModal && canManageKelas}
          onClose={closeModal}
          onSubmit={handleSubmitTingkat}
          selectedItem={selectedItem}
        />
      )}

      <BulkAssignWaliModal
        isOpen={showBulkAssignModal && canManageKelas}
        onClose={closeBulkAssignModal}
        onSubmit={handleBulkAssignWali}
        kelasList={kelasList}
        selectedClasses={selectedClassesForWali}
        setSelectedClasses={setSelectedClassesForWali}
        bulkWaliAssignments={bulkWaliAssignments}
        setBulkWaliAssignments={setBulkWaliAssignments}
        getAvailablePegawai={(excludeWali) => getAvailablePegawai(excludeWali, kelasList)}
        tingkatList={tingkatList}
        loading={loading}
      />

      {/* Import/Export Modal */}
      <ImportExportModal
        isOpen={showImportExportModal && canManageKelas}
        onClose={() => setShowImportExportModal(false)}
        onSuccess={handleImportExportSuccess}
        activeTahunAjaran={activeTahunAjaran}
      />

      {/* View Siswa Modal */}
      <ViewSiswaModal 
        open={showSiswaModal}
        onClose={closeSiswaModal}
        kelas={selectedKelasForSiswa}
        onDeleteSiswa={handleDeleteSiswa}
        onEditSiswa={() => toast.info('Fitur edit siswa akan segera tersedia')}
        kelasList={kelasList}
        tingkatList={tingkatList}
        tahunAjaranList={
          Array.isArray(tahunAjaranList) && tahunAjaranList.length > 0
            ? tahunAjaranList
            : [effectiveTahunAjaran].filter(Boolean)
        }
        activeTahunAjaran={effectiveTahunAjaran}
        onRefresh={refreshKelas}
        canManageKelas={canManageKelas}
        canManageStudents={canManageStudents}
        canManageStudentTransitions={canManageStudentTransitions}
        canManagePromotionWindow={canManagePromotionWindow}
      />

      {/* View Tingkat Classes Modal */}
      <ViewTingkatKelasModal
        open={showTingkatKelasModal}
        onClose={closeTingkatKelasModal}
        tingkat={selectedTingkatForModal}
        kelasList={tingkatKelasList}
      />

      {/* Confirmation Modal */}
      <ConfirmationModal
        open={Boolean(showConfirmModal)}
        onClose={closeConfirmModal}
        title={confirmData?.type === 'delete-siswa' ? 'Konfirmasi Hapus Siswa' : 
               confirmData?.type === 'delete-kelas' ? 'Konfirmasi Hapus Kelas' : 
               'Konfirmasi Hapus Tingkat'}
        message={confirmData?.message}
        onConfirm={async () => {
          if (confirmAction) {
            await confirmAction();
          }
          closeConfirmModal();
        }}
        confirmText="Hapus"
        confirmColor="error"
        type={confirmData?.type}
      />

      {/* Tahun Ajaran Form Modal */}
      <TahunAjaranFormModal
        isOpen={showTahunAjaranModal && canManageTahunAjaran}
        onClose={() => setShowTahunAjaranModal(false)}
        onSubmit={async (data) => {
          if (!canManageTahunAjaran) {
            toast.error('Anda tidak memiliki izin untuk mengelola tahun ajaran');
            return;
          }

          try {
            if (data.id) {
              await updateTahunAjaran(data.id, data);
              toast.success('Tahun ajaran berhasil diperbarui');
            } else {
              await createTahunAjaran(data);
              toast.success('Tahun ajaran berhasil dibuat');
            }
            setShowTahunAjaranModal(false);
            refreshKelas();
          } catch (error) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan tahun ajaran');
          }
        }}
      />
    </div>
  );
};

export default ManajemenKelas;

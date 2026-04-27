import React, { useState, useEffect } from 'react';
import { Box, Container, Snackbar, Alert } from '@mui/material';
import { useNavigate, useLocation } from 'react-router-dom';

// Custom Hooks
import useUserManagementNew from '../hooks/useUserManagementNew';
import useRoleManagementNew from '../hooks/useRoleManagementNew';
import useUserModalManagement from '../hooks/useUserModalManagement';
import { usePasswordManagement } from '../hooks';
import { useAuth } from '../hooks/useAuth';

// Components
import UserManagementHeader from '../components/users/UserManagementHeader';
import UserTabs from '../components/users/UserTabs';
import UserFilters from '../components/users/UserFiltersNew';
import UserTable from '../components/users/UserTableNew';
import FaceTemplateModal from '../components/users/FaceTemplateModal';
import UserPagination from '../components/users/UserPaginationNew';
import PersonalDataVerificationTab from '../components/users/PersonalDataVerificationTab';

// Modals
import TambahPegawaiModal from '../components/TambahPegawaiModal';
import TambahSiswa from '../components/TambahSiswa';
import EditPegawaiModal from '../components/EditPegawaiModal';
import EditSiswaModal from '../components/EditSiswaModal';
import ResetPasswordModal from '../components/ResetPasswordModal';
import ImportModal from '../components/ImportModalNew';
import ExportModal from '../components/ExportModalNew';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';
import { tingkatAPI } from '../services/tingkatService.js';
import { kelasAPI } from '../services/kelasService.js';
import { tahunAjaranAPI } from '../services/tahunAjaranService.js';
import siswaService from '../services/siswaService.jsx';
import { personalDataAPI } from '../services/api';

const ManajemenPengguna = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { hasPermission } = useAuth();
  const canAccessUserManagement = hasPermission('manage_users');
  const canAccessVerificationTab = hasPermission('view_personal_data_verification');
  const canVerifyStudentPersonalData = hasPermission('verify_personal_data_siswa');
  const canVerifyEmployeePersonalData = hasPermission('verify_personal_data_pegawai');
  const canManageFaceTemplate = hasPermission('manage_attendance_settings') || hasPermission('unlock_face_template_submit_quota');
  const verificationProfileTypeScope = canVerifyStudentPersonalData && !canVerifyEmployeePersonalData
    ? 'siswa'
    : (!canVerifyStudentPersonalData && canVerifyEmployeePersonalData ? 'pegawai' : 'all');

  // Tab state
  const [activeTab, setActiveTab] = useState(() => (
    canAccessUserManagement ? 'pegawai' : 'verifikasi'
  ));
  const [notification, setNotification] = useState({ open: false, message: '', severity: 'info' });
  const [faceTemplateState, setFaceTemplateState] = useState({ open: false, user: null });
  const [tahunAjaranOptions, setTahunAjaranOptions] = useState([]);
  const [tingkatOptions, setTingkatOptions] = useState([]);
  const [kelasOptions, setKelasOptions] = useState([]);
  const [confirmState, setConfirmState] = useState({
    open: false,
    title: '',
    message: '',
    type: 'delete',
    confirmText: 'Hapus',
    onConfirm: null,
  });
  const [verificationRows, setVerificationRows] = useState([]);
  const [verificationLoading, setVerificationLoading] = useState(false);
  const [verificationPagination, setVerificationPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });
  const [verificationFilters, setVerificationFilters] = useState({
    search: '',
    profile_type: verificationProfileTypeScope,
    status_verifikasi: 'all',
    completion_tier: 'all',
    tingkat_id: '',
    kelas_id: '',
    page: 1,
    per_page: 15,
    sort_by: 'last_personal_update_at',
    sort_direction: 'desc',
  });

  // Custom hooks
  const {
    users,
    loading,
    pagination,
    filters,
    selectedUsers,
    sortConfig,
    loadUsers,
    handleFilterChange,
    handlePageChange,
    handleSort,
    handleSelectUser,
    handleSelectAll,
    handleDeleteUser,
    toggleUserStatus,
    handleBulkDelete,
    resetFiltersForTab
  } = useUserManagementNew();

  const {
    primaryRoles,
    allSubRoles,
    availableSubRoles,
    fetchSubRoles,
    updateAvailableSubRoles
  } = useRoleManagementNew();

  const {
    newPassword,
    setNewPassword,
    confirmPassword,
    setConfirmPassword,
    handleResetPassword: resetPassword,
    resetForm: resetPasswordForm
  } = usePasswordManagement();

  // Modal management
  const {
    showTambahPegawai,
    showTambahSiswa,
    showEditModal,
    showResetPasswordModal,
    showImportModal,
    showExportModal,
    selectedUser,
    importProgress,
    exportProgress,
    openModal,
    closeModal,
    handleImport,
    handleExport
  } = useUserModalManagement(activeTab, () => loadUsers(activeTab));

  const normalizeUserId = (id) => String(id);
  const resolveUserId = (user) => (
    user?.id
    ?? user?.user_id
    ?? user?.userId
    ?? user?.user?.id
    ?? user?.data_pribadi_siswa?.user_id
    ?? user?.data_pribadi_siswa?.id
    ?? user?.dataPribadiSiswa?.user_id
    ?? user?.dataPribadiSiswa?.id
    ?? null
  );

  const getUserDisplayName = (user, tab = activeTab) => {
    if (!user) {
      return '';
    }

    if (tab === 'siswa') {
      return (
        user?.data_pribadi_siswa?.nama_lengkap
        || user?.nama_lengkap
        || user?.name
        || user?.username
        || ''
      );
    }

    return user?.nama_lengkap || user?.name || user?.username || '';
  };

  const getUserNameById = (userId, tab = activeTab) => {
    if (userId === null || userId === undefined) {
      return '';
    }

    const targetId = normalizeUserId(userId);
    const targetUser = (users || []).find((user) => {
      const resolvedId = resolveUserId(user);
      if (resolvedId === null || resolvedId === undefined) {
        return false;
      }

      return normalizeUserId(resolvedId) === targetId;
    });

    return getUserDisplayName(targetUser, tab);
  };

  const handleTabChange = (newTab) => {
    if (!canAccessUserManagement && newTab !== 'verifikasi') {
      return;
    }

    if (newTab === 'verifikasi') {
      setActiveTab(newTab);
      updateAvailableSubRoles([]);
      return;
    }

    setActiveTab(newTab);
    resetFiltersForTab(newTab);

    if (newTab !== 'pegawai') {
      updateAvailableSubRoles([]);
    }
  };

  useEffect(() => {
    const requestedTab = new URLSearchParams(location.search).get('tab');
    if (requestedTab === 'verifikasi' && canAccessVerificationTab) {
      setActiveTab('verifikasi');
      return;
    }

    if ((requestedTab === 'pegawai' || requestedTab === 'siswa') && canAccessUserManagement) {
      setActiveTab(requestedTab);
    }
  }, [location.search, canAccessUserManagement, canAccessVerificationTab]);

  const handleUserFilterChange = async (key, value) => {
    if (activeTab === 'siswa') {
      if (key === 'tahun_ajaran_id') {
        handleFilterChange('tahun_ajaran_id', value);
        handleFilterChange('kelas_id', '');
        return;
      }

      if (key === 'tingkat_id') {
        handleFilterChange('tingkat_id', value);
        handleFilterChange('kelas_id', '');
        return;
      }

      handleFilterChange(key, value);
      return;
    }

    handleFilterChange(key, value);

    if (activeTab !== 'pegawai') {
      return;
    }

    if (key === 'role') {
      handleFilterChange('sub_role', '');

      if (!value) {
        updateAvailableSubRoles([]);
        return;
      }

      const selectedPrimaryRole = primaryRoles.find((role) => role.name === value);
      if (!selectedPrimaryRole) {
        updateAvailableSubRoles([]);
        return;
      }

      await fetchSubRoles(selectedPrimaryRole.id);
    }
  };

  const handleVerificationFilterChange = (key, value) => {
    setVerificationFilters((prev) => {
      const next = {
        ...prev,
        [key]: (key === 'profile_type' && verificationProfileTypeScope !== 'all')
          ? verificationProfileTypeScope
          : value,
      };

      if (key === 'tingkat_id') {
        next.kelas_id = '';
      }

      if (key === 'per_page') {
        next.page = 1;
      }

      if (!['page', 'per_page', 'sort_by', 'sort_direction'].includes(key)) {
        next.page = 1;
      }

      return next;
    });
  };

  useEffect(() => {
    if (verificationProfileTypeScope === 'all') {
      return;
    }

    setVerificationFilters((prev) => {
      if (prev.profile_type === verificationProfileTypeScope) {
        return prev;
      }

      return {
        ...prev,
        profile_type: verificationProfileTypeScope,
        page: 1,
      };
    });
  }, [verificationProfileTypeScope]);

  const loadVerificationQueue = async () => {
    if (!canAccessVerificationTab) {
      return;
    }

    setVerificationLoading(true);
    try {
      const response = await personalDataAPI.getReviewQueue(verificationFilters);
      const payload = response?.data?.data || {};

      setVerificationRows(Array.isArray(payload.data) ? payload.data : []);
      setVerificationPagination({
        current_page: payload.current_page || 1,
        last_page: payload.last_page || 1,
        per_page: payload.per_page || verificationFilters.per_page || 15,
        total: payload.total || 0,
        from: payload.from || 0,
        to: payload.to || 0,
      });
    } catch (error) {
      setVerificationRows([]);
      setVerificationPagination((prev) => ({
        ...prev,
        current_page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0,
      }));
      showNotification(
        error.response?.data?.message || 'Gagal memuat antrean verifikasi data pribadi',
        'error'
      );
    } finally {
      setVerificationLoading(false);
    }
  };

  // Load data when tab changes
  useEffect(() => {
    if (activeTab === 'verifikasi') {
      loadVerificationQueue();
      return;
    }

    if (!canAccessUserManagement) {
      setActiveTab('verifikasi');
      return;
    }

    loadUsers(activeTab);
    // Intentionally exclude loadUsers from deps to avoid unnecessary reload cycles
    // that reset checkbox selections on user interaction.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, filters, verificationFilters, canAccessUserManagement]);

  useEffect(() => {
    if (!['siswa', 'verifikasi'].includes(activeTab)) {
      return;
    }

    const extractList = (rawResponse) => {
      const payload = rawResponse?.data ?? rawResponse;
      if (Array.isArray(payload?.data)) {
        return payload.data;
      }
      if (Array.isArray(payload)) {
        return payload;
      }

      return [];
    };

    const mapKelasOptions = (kelasData) => (
      Array.from(new Map(
        kelasData
          .map((kelasItem) => {
            const namaKelas = kelasItem?.nama_kelas ?? kelasItem?.namaKelas ?? '';
            if (!kelasItem?.id || !namaKelas) {
              return null;
            }

            const tingkatValue = kelasItem?.tingkat ?? kelasItem?.tingkat_nama ?? kelasItem?.tingkatNama ?? '';
            const tingkatNama = typeof tingkatValue === 'string'
              ? tingkatValue
              : (tingkatValue?.nama ?? tingkatValue?.name ?? '');
            const tingkatId = kelasItem?.tingkat_id ?? tingkatValue?.id ?? '';
            const tahunAjaranId = kelasItem?.tahun_ajaran_id
              ?? kelasItem?.tahunAjaran?.id
              ?? kelasItem?.tahun_ajaran?.id
              ?? '';

            return {
              id: String(kelasItem.id),
              nama_kelas: namaKelas,
              tingkat_id: tingkatId ? String(tingkatId) : '',
              tingkat_nama: tingkatNama,
              tahun_ajaran_id: tahunAjaranId ? String(tahunAjaranId) : '',
            };
          })
          .filter(Boolean)
          .map((item) => [item.id, item]),
      ).values()).sort((a, b) => String(a.nama_kelas).localeCompare(String(b.nama_kelas), 'id', {
        numeric: true,
        sensitivity: 'base',
      }))
    );

    const fetchTingkatOptions = async () => {
      try {
        const response = await tingkatAPI.getAll({ is_active: true });
        const tingkatData = extractList(response);
        setTingkatOptions(
          tingkatData
            .filter((item) => item && item.id && item.nama)
            .map((item) => ({
              id: String(item.id),
              nama: item.nama,
            }))
            .sort((a, b) => String(a.nama).localeCompare(String(b.nama), 'id', {
              numeric: true,
              sensitivity: 'base',
            })),
        );
      } catch (error) {
        console.error('Error fetching tingkat options:', error);
        setTingkatOptions([]);
      }
    };

    const fetchKelasOptions = async () => {
      try {
        const response = await kelasAPI.getAll({
          sort_by: 'nama_kelas',
          sort_direction: 'asc',
        });
        const kelasData = extractList(response?.data);
        setKelasOptions(mapKelasOptions(kelasData));
      } catch (error) {
        console.warn('Primary kelas options failed, using siswa fallback:', error);
        try {
          const siswaResponse = await siswaService.getAll({
            page: 1,
            per_page: 500,
          });
          const siswaData = extractList(siswaResponse?.data?.data);
          const fallbackKelasMap = new Map();

          siswaData.forEach((item) => {
            const kelasList = Array.isArray(item?.kelas) ? item.kelas : [];
            kelasList.forEach((kelasItem) => {
              if (kelasItem?.id && kelasItem?.nama_kelas) {
                fallbackKelasMap.set(String(kelasItem.id), {
                  id: String(kelasItem.id),
                  nama_kelas: kelasItem.nama_kelas,
                  tingkat_id: kelasItem?.tingkat_id ? String(kelasItem.tingkat_id) : '',
                  tingkat_nama: '',
                  tahun_ajaran_id: kelasItem?.pivot?.tahun_ajaran_id ? String(kelasItem.pivot.tahun_ajaran_id) : '',
                });
              }
            });
          });

          const fallbackOptions = Array.from(fallbackKelasMap.values()).sort((a, b) => (
            String(a.nama_kelas).localeCompare(String(b.nama_kelas), 'id', {
              numeric: true,
              sensitivity: 'base',
            })
          ));
          setKelasOptions(fallbackOptions);
        } catch (fallbackError) {
          console.error('Error fetching kelas options fallback:', fallbackError);
          setKelasOptions([]);
        }
      }
    };

    const fetchTahunAjaranOptions = async () => {
      try {
        const response = await tahunAjaranAPI.getAll({ no_pagination: true });
        const payload = response?.data ?? response;
        const rawRows = Array.isArray(payload?.data)
          ? payload.data
          : (Array.isArray(payload) ? payload : []);

        const rows = rawRows
          .filter((item) => item && item.id && item.nama)
          .map((item) => ({
            id: String(item.id),
            nama: item.nama,
            tanggal_mulai: item.tanggal_mulai || null,
          }))
          .sort((a, b) => {
            const aTs = Date.parse(String(a.tanggal_mulai || ''));
            const bTs = Date.parse(String(b.tanggal_mulai || ''));
            const aSafe = Number.isNaN(aTs) ? 0 : aTs;
            const bSafe = Number.isNaN(bTs) ? 0 : bTs;
            return bSafe - aSafe;
          });

        setTahunAjaranOptions(rows);
      } catch (error) {
        console.error('Error fetching tahun ajaran options:', error);
        setTahunAjaranOptions([]);
      }
    };

    fetchTahunAjaranOptions();
    fetchTingkatOptions();
    fetchKelasOptions();
  }, [activeTab]);

  // Handle success callbacks
  const handleSuccess = () => {
    closeModal('tambah');
    closeModal('edit');
    if (activeTab === 'verifikasi') {
      loadVerificationQueue();
    } else {
      loadUsers(activeTab);
    }
    showNotification('Data berhasil disimpan', 'success');
  };

  // Handle edit user
  const handleEdit = (user) => {
    openModal('edit', user);
  };

  // Handle reset password
  const handleResetPasswordClick = (user) => {
    openModal('resetPassword', user);
  };

  const handleManageFaceTemplate = (user) => {
    setFaceTemplateState({
      open: true,
      user,
    });
  };

  const handleCloseFaceTemplateModal = () => {
    setFaceTemplateState({
      open: false,
      user: null,
    });
  };

  // Handle reset password submit
  const handleResetPasswordSubmit = async () => {
    try {
      const success = await resetPassword(
        selectedUser.id, 
        activeTab, 
        selectedUser.data_pribadi_siswa || selectedUser
      );
      
      if (success) {
        closeModal('resetPassword');
        resetPasswordForm();
        if (activeTab === 'verifikasi') {
          loadVerificationQueue();
        } else {
          loadUsers(activeTab);
        }
        showNotification('Password berhasil direset', 'success');
      }
    } catch (error) {
      console.error('Error resetting password:', error);
      showNotification(
        error.response?.data?.message || 'Gagal mereset password',
        'error'
      );
    }
  };

  // Handle import success
  const handleImportSuccess = () => {
    closeModal('import');
    if (activeTab === 'verifikasi') {
      loadVerificationQueue();
    } else {
      loadUsers(activeTab);
    }
    showNotification('Data berhasil diimpor', 'success');
  };

  // Show notification
  const showNotification = (message, severity = 'info') => {
    setNotification({ open: true, message, severity });
  };

  const closeConfirmModal = () => {
    setConfirmState((prev) => ({
      ...prev,
      open: false,
      onConfirm: null,
    }));
  };

  const openConfirmModal = ({ title, message, type = 'delete', confirmText = 'Hapus', onConfirm }) => {
    setConfirmState({
      open: true,
      title,
      message,
      type,
      confirmText,
      onConfirm,
    });
  };

  const requestDeleteUser = (id) => {
    const targetTab = activeTab;
    const targetType = targetTab === 'pegawai' ? 'pegawai' : 'siswa';
    const userName = getUserNameById(id, targetTab);
    openConfirmModal({
      title: 'Konfirmasi Hapus Pengguna',
      message: userName
        ? `Apakah Anda yakin ingin menghapus ${targetType} "${userName}"?`
        : `Apakah Anda yakin ingin menghapus ${targetType} ini?`,
      type: targetTab === 'pegawai' ? 'delete' : 'delete-siswa',
      onConfirm: async () => {
        await handleDeleteUser(id, targetTab);
      },
    });
  };

  const requestBulkDelete = () => {
    if (!selectedUsers || selectedUsers.length === 0) {
      return;
    }

    const targetTab = activeTab;
    const targetType = targetTab === 'pegawai' ? 'pegawai' : 'siswa';
    const totalSelected = selectedUsers.length;
    const firstSelectedName = totalSelected === 1
      ? getUserNameById(selectedUsers[0], targetTab)
      : '';

    openConfirmModal({
      title: 'Konfirmasi Hapus Massal',
      message: totalSelected === 1 && firstSelectedName
        ? `Apakah Anda yakin ingin menghapus ${targetType} "${firstSelectedName}"?`
        : `Apakah Anda yakin ingin menghapus ${totalSelected} ${targetType} yang dipilih?`,
      type: targetTab === 'pegawai' ? 'delete' : 'delete-siswa',
      onConfirm: async () => {
        await handleBulkDelete(targetTab);
      },
    });
  };

  const requestToggleStatus = (id, currentStatus) => {
    const targetTab = activeTab;
    const targetType = targetTab === 'pegawai' ? 'pegawai' : 'siswa';
    const userName = getUserNameById(id, targetTab);
    const nextActionText = currentStatus ? 'menonaktifkan' : 'mengaktifkan';
    const confirmText = currentStatus ? 'Nonaktifkan' : 'Aktifkan';

    openConfirmModal({
      title: `Konfirmasi ${confirmText}`,
      message: userName
        ? `Apakah Anda yakin ingin ${nextActionText} ${targetType} "${userName}"?`
        : `Apakah Anda yakin ingin ${nextActionText} ${targetType} ini?`,
      type: 'warning',
      confirmText,
      onConfirm: async () => {
        await toggleUserStatus(id, currentStatus, targetTab);
      },
    });
  };

  const requestResetPassword = (user) => {
    const targetTab = activeTab;
    const targetType = targetTab === 'pegawai' ? 'pegawai' : 'siswa';
    const userName = getUserDisplayName(user, targetTab);

    openConfirmModal({
      title: 'Konfirmasi Reset Password',
      message: userName
        ? `Lanjutkan reset password untuk ${targetType} "${userName}"?`
        : `Lanjutkan reset password untuk ${targetType} ini?`,
      type: 'warning',
      confirmText: 'Lanjutkan',
      onConfirm: async () => {
        handleResetPasswordClick(user);
      },
    });
  };

  const handleOpenVerificationProfile = (item) => {
    if (!item?.user_id) {
      return;
    }

    navigate(`/manajemen-pengguna/data-pribadi/${item.user_id}?type=${item.profile_type || 'pegawai'}`);
  };

  const requestVerificationDecision = (item, action) => {
    if (!item?.user_id) {
      return;
    }

    const isStudentProfile = String(item?.profile_type || '') === 'siswa';
    if (isStudentProfile && !canVerifyStudentPersonalData) {
      showNotification('Anda tidak memiliki akses verifikasi data pribadi siswa', 'warning');
      return;
    }

    if (!isStudentProfile && !canVerifyEmployeePersonalData) {
      showNotification('Anda tidak memiliki akses verifikasi data pribadi pegawai', 'warning');
      return;
    }

    const labelMap = {
      approve: 'Setujui',
      needs_revision: 'Perlu Perbaikan',
      reset: 'Reset',
    };
    const messageMap = {
      approve: 'menyetujui',
      needs_revision: 'menandai perlu perbaikan untuk',
      reset: 'mereset status verifikasi',
    };

    const confirmText = labelMap[action] || 'Simpan';
    const userName = item.nama_lengkap || item.username || `User #${item.user_id}`;

    openConfirmModal({
      title: `Konfirmasi ${confirmText}`,
      message: `Apakah Anda yakin ingin ${messageMap[action] || 'memproses'} "${userName}"?`,
      type: action === 'approve' ? 'info' : 'warning',
      confirmText,
      onConfirm: async () => {
        try {
          await personalDataAPI.submitReviewDecision(item.user_id, { action });
          showNotification('Keputusan verifikasi berhasil disimpan', 'success');
          await loadVerificationQueue();
        } catch (error) {
          showNotification(
            error.response?.data?.message || 'Gagal menyimpan keputusan verifikasi',
            'error'
          );
        }
      },
    });
  };

  // Close notification
  const closeNotification = () => {
    setNotification({ open: false, message: '', severity: 'info' });
  };

  // Get user counts for tabs
  const getUserCounts = () => {
    if (activeTab === 'pegawai') {
      return { pegawai: pagination.total, siswa: 0, verifikasi: 0 };
    }

    if (activeTab === 'siswa') {
      return { pegawai: 0, siswa: pagination.total, verifikasi: 0 };
    }

    return { pegawai: 0, siswa: 0, verifikasi: verificationPagination.total || 0 };
  };

  return (
    <Container maxWidth="xl">
      <Box className="py-6">
        {/* Header */}
        <UserManagementHeader />

        {/* Tab Navigation */}
        <UserTabs 
          activeTab={activeTab} 
          onTabChange={handleTabChange} 
          userCounts={getUserCounts()}
          showVerificationTab={canAccessVerificationTab}
          showManagementTabs={canAccessUserManagement}
        />

        {/* Content Area */}
        <Box>
          {activeTab === 'verifikasi' ? (
            <>
              <PersonalDataVerificationTab
                rows={verificationRows}
                loading={verificationLoading}
                filters={verificationFilters}
                onFilterChange={handleVerificationFilterChange}
                availableTingkat={tingkatOptions}
                availableKelas={kelasOptions}
                onOpenProfile={handleOpenVerificationProfile}
                onDecision={requestVerificationDecision}
                canVerifySiswa={canVerifyStudentPersonalData}
                canVerifyPegawai={canVerifyEmployeePersonalData}
                lockedProfileType={verificationProfileTypeScope !== 'all' ? verificationProfileTypeScope : ''}
              />

              <UserPagination
                pagination={verificationPagination}
                onPageChange={(page) => handleVerificationFilterChange('page', page)}
                onPerPageChange={(perPage) => handleVerificationFilterChange('per_page', perPage)}
              />
            </>
          ) : (
            <>
              {/* Filters */}
              <UserFilters
                activeTab={activeTab}
                filters={filters}
                onFilterChange={handleUserFilterChange}
                onAddUser={() => openModal('tambah')}
                onExport={() => openModal('export')}
                onImport={() => openModal('import')}
                selectedUsers={selectedUsers}
                onBulkDelete={requestBulkDelete}
                availableRoles={primaryRoles}
                availableSubRoles={availableSubRoles}
                availableTahunAjaran={tahunAjaranOptions}
                availableTingkat={tingkatOptions}
                availableKelas={kelasOptions}
              />

              {/* Table */}
              <UserTable
                users={users}
                loading={loading}
                activeTab={activeTab}
                selectedUsers={selectedUsers}
                onSelectUser={handleSelectUser}
                onSelectAll={handleSelectAll}
                onEdit={handleEdit}
                onDelete={requestDeleteUser}
                onResetPassword={requestResetPassword}
                onToggleStatus={requestToggleStatus}
                onManageFaceTemplate={handleManageFaceTemplate}
                canManageFaceTemplate={canManageFaceTemplate}
                sortConfig={sortConfig}
                onSort={handleSort}
              />

              {/* Pagination */}
              <UserPagination
                pagination={pagination}
                onPageChange={handlePageChange}
                onPerPageChange={(perPage) => handleFilterChange('per_page', perPage)}
              />
            </>
          )}
        </Box>

        {/* Modals */}
        <TambahPegawaiModal
          isOpen={showTambahPegawai}
          onClose={() => closeModal('tambah')}
          onSuccess={handleSuccess}
          primaryRoles={primaryRoles}
          availableSubRoles={availableSubRoles}
          handlePrimaryRoleChange={updateAvailableSubRoles}
        />

        {showTambahSiswa && (
          <TambahSiswa
            open={showTambahSiswa}
            onClose={() => closeModal('tambah')}
            onSuccess={handleSuccess}
          />
        )}

        {showEditModal && selectedUser && activeTab === 'pegawai' && (
          <EditPegawaiModal
            isOpen={showEditModal}
            onClose={() => closeModal('edit')}
            onSubmit={handleSuccess}
            selectedUser={selectedUser}
            primaryRoles={primaryRoles}
            allSubRoles={allSubRoles}
          />
        )}

        {showEditModal && selectedUser && activeTab === 'siswa' && (
          <EditSiswaModal
            isOpen={showEditModal}
            onClose={() => closeModal('edit')}
            onSubmit={handleSuccess}
            selectedUser={selectedUser}
          />
        )}

        <ResetPasswordModal
          isOpen={showResetPasswordModal && selectedUser}
          onClose={() => {
            closeModal('resetPassword');
            resetPasswordForm();
          }}
          onSubmit={handleResetPasswordSubmit}
          newPassword={newPassword}
          setNewPassword={setNewPassword}
          confirmPassword={confirmPassword}
          setConfirmPassword={setConfirmPassword}
          userType={activeTab}
          selectedUser={selectedUser}
        />

        {/* Import Modal */}
        <ImportModal
          isOpen={showImportModal}
          onClose={() => closeModal('import')}
          onSuccess={handleImportSuccess}
          userType={activeTab}
          onImport={handleImport}
          progress={importProgress}
        />

        {/* Export Modal */}
        <ExportModal
          isOpen={showExportModal}
          onClose={() => closeModal('export')}
          onExport={handleExport}
          userType={activeTab === 'pegawai' ? 'Pegawai' : 'Siswa'}
          progress={exportProgress}
        />

        <ConfirmationModal
          open={confirmState.open}
          onClose={closeConfirmModal}
          title={confirmState.title}
          message={confirmState.message}
          type={confirmState.type}
          confirmText={confirmState.confirmText}
          onConfirm={async () => {
            if (typeof confirmState.onConfirm === 'function') {
              await confirmState.onConfirm();
            }
            closeConfirmModal();
          }}
        />

        <FaceTemplateModal
          open={faceTemplateState.open}
          user={faceTemplateState.user}
          onClose={handleCloseFaceTemplateModal}
          onUpdated={showNotification}
        />

        {/* Notification Snackbar */}
        <Snackbar
          open={notification.open}
          autoHideDuration={6000}
          onClose={closeNotification}
          anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        >
          <Alert 
            onClose={closeNotification} 
            severity={notification.severity}
            variant="filled"
            sx={{ width: '100%' }}
          >
            {notification.message}
          </Alert>
        </Snackbar>
      </Box>
    </Container>
  );
};

export default ManajemenPengguna;

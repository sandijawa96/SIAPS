import { useState } from 'react';
import { toServerDateInput } from '../services/serverClock';

export const useTahunAjaranModals = () => {
  const [showModal, setShowModal] = useState(false);
  const [selectedTahunAjaran, setSelectedTahunAjaran] = useState(null);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmAction, setConfirmAction] = useState(null);
  const [confirmData, setConfirmData] = useState(null);

  // Open add modal
  const openAddModal = () => {
    setSelectedTahunAjaran(null);
    setShowModal(true);
  };

  // Open edit modal
  const openEditModal = (tahunAjaran) => {
    // Format tanggal untuk input date (YYYY-MM-DD)
    const formattedTahunAjaran = {
      ...tahunAjaran,
      tanggal_mulai: tahunAjaran.tanggal_mulai 
        ? toServerDateInput(tahunAjaran.tanggal_mulai)
        : '',
      tanggal_selesai: tahunAjaran.tanggal_selesai 
        ? toServerDateInput(tahunAjaran.tanggal_selesai)
        : ''
    };
    
    setSelectedTahunAjaran(formattedTahunAjaran);
    setShowModal(true);
  };

  // Close modal
  const closeModal = () => {
    setShowModal(false);
    setSelectedTahunAjaran(null);
  };

  // Open confirm modal
  const openConfirmModal = (data, action) => {
    setConfirmData(data);
    setConfirmAction(() => action);
    setShowConfirmModal(true);
  };

  // Close confirm modal
  const closeConfirmModal = () => {
    setShowConfirmModal(false);
    setConfirmAction(null);
    setConfirmData(null);
  };

  // Execute confirm action
  const executeConfirmAction = async () => {
    if (confirmAction) {
      await confirmAction();
    }
    closeConfirmModal();
  };

  return {
    // Modal states
    showModal,
    selectedTahunAjaran,
    showConfirmModal,
    confirmAction,
    confirmData,
    
    // Modal actions
    openAddModal,
    openEditModal,
    closeModal,
    openConfirmModal,
    closeConfirmModal,
    executeConfirmAction
  };
};

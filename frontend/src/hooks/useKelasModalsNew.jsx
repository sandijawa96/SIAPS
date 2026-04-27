import { useState, useCallback } from 'react';

export const useKelasModalsNew = () => {
  // Modal visibility states
  const [showModal, setShowModal] = useState(false);
  const [showSiswaModal, setShowSiswaModal] = useState(false);
  const [showTambahSiswaModal, setShowTambahSiswaModal] = useState(false);
  const [showBulkAssignModal, setShowBulkAssignModal] = useState(false);
  const [showTingkatKelasModal, setShowTingkatKelasModal] = useState(false);

  // Modal data states
  const [selectedItem, setSelectedItem] = useState(null);
  const [selectedKelasForSiswa, setSelectedKelasForSiswa] = useState(null);
  const [selectedClassesForWali, setSelectedClassesForWali] = useState([]);
  const [bulkWaliAssignments, setBulkWaliAssignments] = useState({});
  const [selectedTingkatForModal, setSelectedTingkatForModal] = useState(null);
  const [tingkatKelasList, setTingkatKelasList] = useState([]);

  // Modal handlers
  const openAddModal = useCallback(() => {
    setSelectedItem(null);
    setShowModal(true);
  }, []);

  const openEditModal = useCallback((item) => {
    setSelectedItem(item);
    setShowModal(true);
  }, []);

  const closeModal = useCallback(() => {
    setShowModal(false);
    setSelectedItem(null);
  }, []);

  const openBulkAssignModal = useCallback((selectedClasses = []) => {
    setSelectedClassesForWali(selectedClasses);
    setBulkWaliAssignments({});
    setShowBulkAssignModal(true);
  }, []);

  const closeBulkAssignModal = useCallback(() => {
    setShowBulkAssignModal(false);
    setSelectedClassesForWali([]);
    setBulkWaliAssignments({});
  }, []);

  const openSiswaModal = useCallback((kelas) => {
    setSelectedKelasForSiswa(kelas);
    setShowSiswaModal(true);
  }, []);

  const closeSiswaModal = useCallback(() => {
    setShowSiswaModal(false);
    setSelectedKelasForSiswa(null);
  }, []);

  const openTingkatKelasModal = useCallback((tingkat, kelasList) => {
    setSelectedTingkatForModal(tingkat);
    setTingkatKelasList(kelasList);
    setShowTingkatKelasModal(true);
  }, []);

  const closeTingkatKelasModal = useCallback(() => {
    setShowTingkatKelasModal(false);
    setSelectedTingkatForModal(null);
    setTingkatKelasList([]);
  }, []);

  const openTambahSiswaModal = useCallback(() => {
    setShowTambahSiswaModal(true);
  }, []);

  const closeTambahSiswaModal = useCallback(() => {
    setShowTambahSiswaModal(false);
  }, []);

  // Reset all modals
  const resetAllModals = useCallback(() => {
    setShowModal(false);
    setShowSiswaModal(false);
    setShowTambahSiswaModal(false);
    setShowBulkAssignModal(false);
    setShowTingkatKelasModal(false);
    setSelectedItem(null);
    setSelectedKelasForSiswa(null);
    setSelectedClassesForWali([]);
    setBulkWaliAssignments({});
    setSelectedTingkatForModal(null);
    setTingkatKelasList([]);
  }, []);

  return {
    // Modal visibility states
    showModal,
    showSiswaModal,
    showTambahSiswaModal,
    showBulkAssignModal,
    showTingkatKelasModal,

    // Modal data states
    selectedItem,
    selectedKelasForSiswa,
    selectedClassesForWali,
    bulkWaliAssignments,
    selectedTingkatForModal,
    tingkatKelasList,

    // Modal handlers
    openAddModal,
    openEditModal,
    closeModal,
    openBulkAssignModal,
    closeBulkAssignModal,
    openSiswaModal,
    closeSiswaModal,
    openTingkatKelasModal,
    closeTingkatKelasModal,
    openTambahSiswaModal,
    closeTambahSiswaModal,
    resetAllModals,

    // Setters for controlled components
    setSelectedClassesForWali,
    setBulkWaliAssignments,
    setShowTambahSiswaModal
  };
};

export default useKelasModalsNew;

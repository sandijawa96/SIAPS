import React, { useEffect, useState } from 'react';
import { Alert, Box, Paper, Typography } from '@mui/material';
import { FileCheck2, ShieldCheck } from 'lucide-react';
import { useIzinManagement } from '../hooks/useIzinManagement';
import { IzinPegawaiStatisticsCards } from '../components/izin/pegawai/IzinPegawaiStatisticsCards';
import { IzinPegawaiFilters } from '../components/izin/pegawai/IzinPegawaiFilters';
import { IzinPegawaiTable } from '../components/izin/pegawai/IzinPegawaiTable';
import { IzinApprovalModal } from '../components/izin/IzinApprovalModal';

const PersetujuanIzinPegawai = () => {
  const {
    data,
    loading,
    statistics,
    loadingStats,
    fetchData,
    fetchStatistics,
    approveIzin,
    rejectIzin,
    getIzinDetail,
  } = useIzinManagement({ type: 'pegawai', forApproval: true });

  const [filters, setFilters] = useState({
    search: '',
    status: '',
    departemen: '',
    jenis_izin: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
  });

  const [selectedIzin, setSelectedIzin] = useState(null);
  const [modalAction, setModalAction] = useState(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [disabledMessage, setDisabledMessage] = useState('');

  useEffect(() => {
    const load = async () => {
      try {
        setDisabledMessage('');
        await Promise.all([
          fetchData(filters),
          fetchStatistics(),
        ]);
      } catch (error) {
        const message = error?.message || 'Gagal memuat data izin pegawai';
        if (message.toLowerCase().includes('dinonaktifkan')) {
          setDisabledMessage(message);
          return;
        }

        console.error('Error loading izin pegawai page:', error);
      }
    };

    load();
  }, [fetchData, fetchStatistics, filters]);

  const handleFilterChange = (newFilters) => {
    setFilters((prev) => ({
      ...prev,
      ...newFilters,
    }));
  };

  const handleView = async (id) => {
    try {
      const detail = await getIzinDetail(id);
      setSelectedIzin(detail.data);
      setModalAction('view');
      setModalOpen(true);
    } catch (error) {
      console.error('Error fetching izin detail:', error);
    }
  };

  const handleApprove = async (id) => {
    try {
      const detail = await getIzinDetail(id);
      setSelectedIzin(detail.data);
      setModalAction('approve');
      setModalOpen(true);
    } catch (error) {
      console.error('Error fetching izin detail:', error);
    }
  };

  const handleReject = async (id) => {
    try {
      const detail = await getIzinDetail(id);
      setSelectedIzin(detail.data);
      setModalAction('reject');
      setModalOpen(true);
    } catch (error) {
      console.error('Error fetching izin detail:', error);
    }
  };

  const handleModalSubmit = async (payload) => {
    try {
      if (modalAction === 'approve') {
        await approveIzin(selectedIzin.id, payload);
      } else if (modalAction === 'reject') {
        await rejectIzin(selectedIzin.id, payload);
      }
      setModalOpen(false);
      fetchData(filters);
      fetchStatistics();
    } catch (error) {
      console.error('Error submitting approval/rejection:', error);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <Box className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-xl">
            <FileCheck2 className="w-6 h-6 text-blue-600" />
          </div>
          <div className="flex-1">
            <Typography variant="h5" className="font-bold text-gray-900">
              Persetujuan Izin Pegawai
            </Typography>
            <Typography variant="body2" className="text-gray-600 mt-1">
              Kelola, review, dan proses pengajuan izin dari pegawai.
            </Typography>
            <div className="flex flex-wrap gap-2 mt-3">
              <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full border border-blue-200 bg-blue-50 text-blue-700">
                <ShieldCheck className="w-3.5 h-3.5" />
                Approval Workflow
              </span>
            </div>
          </div>
        </Box>
      </div>

      <div>
        <IzinPegawaiStatisticsCards statistics={statistics} loading={loadingStats} />
      </div>

      {disabledMessage && (
        <Alert severity="info" sx={{ borderRadius: 3 }}>
          {disabledMessage}
        </Alert>
      )}

      <Paper className="p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="mb-4">
          <Typography variant="subtitle1" className="font-semibold text-gray-900">
            Filter Data Pengajuan
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Gunakan filter untuk mempercepat proses review izin pegawai.
          </Typography>
        </div>
        <IzinPegawaiFilters filters={filters} onFilterChange={handleFilterChange} />
      </Paper>

      <IzinPegawaiTable
        data={data}
        loading={loading}
        onView={handleView}
        onApprove={handleApprove}
        onReject={handleReject}
      />

      <IzinApprovalModal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        izin={selectedIzin}
        action={modalAction}
        onSubmit={handleModalSubmit}
        type="pegawai"
      />
    </div>
  );
};

export default PersetujuanIzinPegawai;

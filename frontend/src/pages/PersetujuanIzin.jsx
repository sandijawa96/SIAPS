import React, { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';
import { Box, Paper, Typography } from '@mui/material';
import { FileCheck2, ShieldCheck } from 'lucide-react';
import { useIzinManagement } from '../hooks/useIzinManagement';
import { izinService } from '../services/izinService';
import {
  IzinStatisticsCards,
  IzinApprovalFilters,
  IzinApprovalPrioritySummary,
  IzinApprovalTable,
  IzinApprovalModal,
  IzinObservabilityPanel,
} from '../components/izin';

const PersetujuanIzin = () => {
  const [selectedIzin, setSelectedIzin] = useState(null);
  const [modalAction, setModalAction] = useState(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [observability, setObservability] = useState(null);
  const [loadingObservability, setLoadingObservability] = useState(false);
  const [observabilityError, setObservabilityError] = useState(null);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });
  const [approvalPrioritySummary, setApprovalPrioritySummary] = useState({
    total_pending: 0,
    due_today: 0,
    overdue: 0,
    upcoming: 0,
    urgent: 0,
  });

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
    downloadDocument,
  } = useIzinManagement({ type: 'siswa', forApproval: true });

  const [filters, setFilters] = useState({
    search: '',
    status: '',
    kelas_id: '',
    jenis_izin: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    per_page: 10,
  });

  const loadData = async (page = pagination.current_page, perPage = pagination.per_page, activeFilters = filters) => {
    const response = await fetchData({
      ...activeFilters,
      page,
      per_page: perPage,
    });

    if (response?.meta) {
      setPagination((prev) => ({
        ...prev,
        current_page: Number(response.meta.current_page || page),
        last_page: Number(response.meta.last_page || 1),
        total: Number(response.meta.total || 0),
        per_page: Number(response.meta.per_page || perPage),
      }));
      setApprovalPrioritySummary(response.meta.pending_review_summary || {
        total_pending: 0,
        due_today: 0,
        overdue: 0,
        upcoming: 0,
        urgent: 0,
      });
    }
  };

  const loadObservability = async () => {
    try {
      setLoadingObservability(true);
      setObservabilityError(null);
      const response = await izinService.getObservability();
      setObservability(response?.data || null);
      return response;
    } catch (error) {
      console.error('Error loading izin observability:', error);
      const message = error?.response?.data?.message || error?.message || 'Gagal memuat observability izin';
      setObservabilityError(message);
      throw error;
    } finally {
      setLoadingObservability(false);
    }
  };

  useEffect(() => {
    (async () => {
      try {
        await Promise.all([
          loadData(pagination.current_page, pagination.per_page, filters),
          fetchStatistics(),
          loadObservability(),
        ]);
      } catch (error) {
        console.error('Error loading data:', error);
        toast.error(error?.message || 'Gagal memuat data izin');
      }
    })();
  }, [pagination.current_page, pagination.per_page, filters]);

  const handleFilterChange = (nextFilters) => {
    setFilters((prev) => ({
      ...prev,
      ...nextFilters,
    }));

    setPagination((prev) => ({
      ...prev,
      current_page: 1,
      per_page: nextFilters?.per_page ? Number(nextFilters.per_page) : prev.per_page,
    }));
  };

  const handlePageChange = (page) => {
    setPagination((prev) => ({ ...prev, current_page: page }));
  };

  const handlePerPageChange = (nextPerPage) => {
    setPagination((prev) => ({
      ...prev,
      current_page: 1,
      per_page: Number(nextPerPage) || 10,
    }));
  };

  const handleApprove = (izin) => {
    setSelectedIzin(izin);
    setModalAction('approve');
    setModalOpen(true);
  };

  const handleReject = (izin) => {
    setSelectedIzin(izin);
    setModalAction('reject');
    setModalOpen(true);
  };

  const handleDownload = async (id) => {
    try {
      await downloadDocument(id);
      toast.success('Dokumen berhasil diunduh');
    } catch (error) {
      console.error('Error downloading document:', error);
      toast.error(error?.message || 'Gagal mengunduh dokumen');
    }
  };

  const handleModalSubmit = async (payload) => {
    try {
      if (!selectedIzin?.id) {
        return;
      }

      if (modalAction === 'approve') {
        await approveIzin(selectedIzin.id, payload);
        toast.success('Pengajuan izin disetujui');
      } else if (modalAction === 'reject') {
        await rejectIzin(selectedIzin.id, payload);
        toast.success('Pengajuan izin ditolak');
      }

      setModalOpen(false);
      setSelectedIzin(null);
      await Promise.all([
        loadData(pagination.current_page, pagination.per_page, filters),
        fetchStatistics(),
        loadObservability(),
      ]);
    } catch (error) {
      console.error('Error submitting approval/rejection:', error);
      toast.error(error?.message || 'Gagal memproses izin');
    }
  };

  const handleOpenDetail = async (id) => {
    try {
      const detail = await getIzinDetail(id);
      setSelectedIzin(detail?.data || null);
      setModalAction('view');
      setModalOpen(true);
    } catch (error) {
      console.error('Error fetching izin detail:', error);
      toast.error(error?.message || 'Gagal memuat detail izin');
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
              Persetujuan Izin Siswa
            </Typography>
            <Typography variant="body2" className="text-gray-600 mt-1">
              Kelola, review, dan proses pengajuan izin siswa secara terstruktur.
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
        <IzinStatisticsCards statistics={statistics} loading={loadingStats} />
      </div>

      <IzinApprovalPrioritySummary
        summary={approvalPrioritySummary}
        loading={loading}
      />

      <IzinObservabilityPanel
        data={observability}
        loading={loadingObservability}
        error={observabilityError}
        onRefresh={loadObservability}
      />

      <Paper className="p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="mb-4">
          <Typography variant="subtitle1" className="font-semibold text-gray-900">
            Filter Data Pengajuan
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Gunakan filter untuk mempercepat proses review izin.
          </Typography>
        </div>
        <IzinApprovalFilters type="siswa" filters={filters} onFilterChange={handleFilterChange} />
      </Paper>

      <IzinApprovalTable
        izinList={data}
        loading={loading}
        onApprove={handleApprove}
        onReject={handleReject}
        onDownload={handleDownload}
        pagination={pagination}
        onPageChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        onView={handleOpenDetail}
      />

      <IzinApprovalModal
        isOpen={modalOpen}
        onClose={() => {
          setModalOpen(false);
          setSelectedIzin(null);
        }}
        izin={selectedIzin}
        action={modalAction}
        onSubmit={handleModalSubmit}
        type="siswa"
      />
    </div>
  );
};

export default PersetujuanIzin;

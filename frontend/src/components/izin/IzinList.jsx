import React, { useState, useEffect } from 'react';
import { izinService } from '../../services/izinService';
import { toast } from 'react-hot-toast';
import { formatServerDate } from '../../services/serverClock';
import { resolveProfilePhotoUrl } from '../../utils/profilePhoto';

const IzinList = ({ refreshTrigger }) => {
  const [izinList, setIzinList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    status: '',
    start_date: '',
    end_date: '',
    per_page: 10
  });
  const [pagination, setPagination] = useState({});

  const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  };

  const statusLabels = {
    pending: 'Menunggu Persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak'
  };

  const jenisIzinLabels = {
    sakit: 'Sakit',
    izin: 'Izin Pribadi',
    dispensasi: 'Dispensasi Sekolah',
    tugas_sekolah: 'Tugas Sekolah',
    keperluan_keluarga: 'Urusan Keluarga',
    dinas_luar: 'Dinas Luar',
    cuti: 'Cuti'
  };

  const isPdfDocument = (izin) => {
    const raw = String(izin?.dokumen_pendukung_url || izin?.dokumen_pendukung || '').toLowerCase();
    return raw.endsWith('.pdf');
  };

  const resolveStatusLabel = (izin) => (
    izin?.status_label
      || statusLabels[String(izin?.status || '').toLowerCase()]
      || izin?.status
      || '-'
  );

  const fetchIzinList = async (page = 1) => {
    try {
      setLoading(true);
      const params = {
        ...filters,
        page
      };
      
      // Remove empty filters
      Object.keys(params).forEach(key => {
        if (params[key] === '' || params[key] === null) {
          delete params[key];
        }
      });

      const response = await izinService.getMyIzin(params);
      setIzinList(response.data || []);
      setPagination({
        current_page: response.meta?.current_page || 1,
        last_page: response.meta?.last_page || 1,
        per_page: response.meta?.per_page || Number(params.per_page || 10),
        total: response.meta?.total || 0
      });
    } catch (error) {
      console.error('Error fetching izin list:', error);
      toast.error('Gagal memuat daftar izin');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchIzinList();
  }, [refreshTrigger, filters]);

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleCancelIzin = async (id) => {
    if (!confirm('Apakah Anda yakin ingin membatalkan izin ini?')) {
      return;
    }

    try {
      await izinService.cancel(id);
      toast.success('Pengajuan izin berhasil dibatalkan');
      fetchIzinList();
    } catch (error) {
      console.error('Error canceling izin:', error);
      toast.error(error.response?.data?.message || 'Gagal membatalkan izin');
    }
  };

  const handleDownloadDocument = async (id, fileName) => {
    try {
      const response = await izinService.downloadDocument(id);
      
      // Create blob and download
      const blob = new Blob([response.data]);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = fileName || 'dokumen_izin.pdf';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Error downloading document:', error);
      toast.error('Gagal mengunduh dokumen');
    }
  };

  const formatDate = (dateString) => {
    try {
      return formatServerDate(dateString, 'id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      }) || '-';
    } catch (error) {
      return dateString;
    }
  };

  const formatDateRange = (startDate, endDate) => {
    if (startDate === endDate) {
      return formatDate(startDate);
    }
    return `${formatDate(startDate)} - ${formatDate(endDate)}`;
  };

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow-md p-6">
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-16 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-md">
      {/* Header and Filters */}
      <div className="p-6 border-b border-gray-200">
        <h2 className="text-xl font-semibold text-gray-800 mb-4">
          Riwayat Izin
        </h2>
        
        {/* Filters */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Status
            </label>
            <select
              name="status"
              value={filters.status}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Status</option>
              <option value="pending">Menunggu Persetujuan</option>
              <option value="approved">Disetujui</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Tanggal Mulai
            </label>
            <input
              type="date"
              name="start_date"
              value={filters.start_date}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Tanggal Akhir
            </label>
            <input
              type="date"
              name="end_date"
              value={filters.end_date}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Per Halaman
            </label>
            <select
              name="per_page"
              value={filters.per_page}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value={10}>10</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
            </select>
          </div>
        </div>
      </div>

      {/* Izin List */}
      <div className="p-6">
        {izinList.length === 0 ? (
          <div className="text-center py-8">
            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 className="mt-2 text-sm font-medium text-gray-900">Tidak ada izin</h3>
            <p className="mt-1 text-sm text-gray-500">Belum ada pengajuan izin yang dibuat.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {izinList.map((izin) => (
              <div key={izin.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-2">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColors[izin.status]}`}>
                        {resolveStatusLabel(izin)}
                      </span>
                      <span className="text-sm font-medium text-gray-900">
                        {izin.jenis_izin_label || jenisIzinLabels[izin.jenis_izin] || izin.jenis_izin}
                      </span>
                    </div>
                    
                    <div className="text-sm text-gray-600 mb-2">
                      <strong>Periode:</strong> {formatDateRange(izin.tanggal_mulai, izin.tanggal_selesai)}
                    </div>

                    <div className="text-sm text-gray-600 mb-2">
                      <strong>Dampak hari sekolah:</strong>{' '}
                      {typeof izin.school_days_affected === 'number'
                        ? `${izin.school_days_affected} hari sekolah`
                        : 'akan dihitung saat diproses'}
                      {typeof izin.non_working_days_skipped === 'number' && izin.non_working_days_skipped > 0
                        ? `, ${izin.non_working_days_skipped} hari non-sekolah dilewati`
                        : ''}
                    </div>
                    
                    <div className="text-sm text-gray-600 mb-2">
                      <strong>Alasan:</strong> {izin.alasan}
                    </div>

                    {izin.dokumen_pendukung && (
                      <div className="mb-3">
                        <div className="text-sm text-gray-600 mb-2">
                          <strong>Lampiran Pendukung:</strong>
                        </div>
                        {isPdfDocument(izin) ? (
                          <div className="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            Dokumen PDF terlampir
                          </div>
                        ) : (
                          <a
                            href={resolveProfilePhotoUrl(izin.dokumen_pendukung_url || izin.dokumen_pendukung) || '#'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-block"
                          >
                            <img
                              src={resolveProfilePhotoUrl(izin.dokumen_pendukung_url || izin.dokumen_pendukung) || undefined}
                              alt={`Bukti izin ${izin.id}`}
                              className="w-28 h-28 rounded-lg object-cover border border-gray-200 bg-gray-50"
                            />
                          </a>
                        )}
                      </div>
                    )}
                    
                    {izin.catatan_approval && (
                      <div className="text-sm text-gray-600 mb-2">
                        <strong>Catatan:</strong> {izin.catatan_approval}
                      </div>
                    )}
                    
                    <div className="text-xs text-gray-500">
                      Diajukan: {formatDate(izin.created_at)}
                    </div>
                  </div>
                  
                  <div className="flex flex-col space-y-2 ml-4">
                    {izin.status === 'pending' && (
                      <button
                        onClick={() => handleCancelIzin(izin.id)}
                        className="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                      >
                        Batalkan
                      </button>
                    )}
                    
                    {izin.dokumen_pendukung && (
                      <button
                        onClick={() => handleDownloadDocument(
                          izin.id,
                          izin.dokumen_pendukung_nama || `lampiran_izin_${izin.id}`
                        )}
                        className="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                      >
                        Unduh Lampiran
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination */}
        {pagination.last_page > 1 && (
          <div className="flex justify-between items-center mt-6">
            <div className="text-sm text-gray-700">
              Menampilkan {((pagination.current_page - 1) * pagination.per_page) + 1} - {Math.min(pagination.current_page * pagination.per_page, pagination.total)} dari {pagination.total} data
            </div>
            
            <div className="flex space-x-2">
              <button
                onClick={() => fetchIzinList(pagination.current_page - 1)}
                disabled={pagination.current_page === 1}
                className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Sebelumnya
              </button>
              
              <span className="px-3 py-1 text-sm bg-blue-600 text-white rounded">
                {pagination.current_page}
              </span>
              
              <button
                onClick={() => fetchIzinList(pagination.current_page + 1)}
                disabled={pagination.current_page === pagination.last_page}
                className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Selanjutnya
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default IzinList;

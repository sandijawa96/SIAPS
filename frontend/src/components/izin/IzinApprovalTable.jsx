import React from 'react';
import PropTypes from 'prop-types';
import {
  Box,
  Chip,
  IconButton,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { CheckCircle, Download, Eye, XCircle } from 'lucide-react';
import { formatServerDate } from '../../services/serverClock';

const getStatusMeta = (status, statusLabel) => {
  const normalized = String(status || '').toLowerCase();
  const map = {
    pending: { label: 'Menunggu Persetujuan', color: 'warning' },
    approved: { label: 'Disetujui', color: 'success' },
    rejected: { label: 'Ditolak', color: 'error' },
  };

  const fallback = map[normalized] || { label: status || '-', color: 'default' };
  return {
    ...fallback,
    label: statusLabel || fallback.label,
  };
};

const formatDate = (dateString) => {
  if (!dateString) {
    return '-';
  }
  try {
    return formatServerDate(dateString, 'id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    }) || '-';
  } catch {
    return dateString;
  }
};

const toInitials = (name) => (
  String(name || '')
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
);

const resolveKelasLabel = (izin) => {
  if (typeof izin?.kelas_nama === 'string' && izin.kelas_nama.trim() !== '') {
    return izin.kelas_nama;
  }

  if (typeof izin?.kelas === 'string' && izin.kelas.trim() !== '') {
    return izin.kelas;
  }

  if (izin?.kelas && typeof izin.kelas === 'object') {
    const namaKelas = typeof izin.kelas.nama_kelas === 'string' ? izin.kelas.nama_kelas.trim() : '';
    const namaTingkat = typeof izin.kelas.tingkat?.nama === 'string' ? izin.kelas.tingkat.nama.trim() : '';

    if (namaKelas && namaTingkat) {
      return `${namaTingkat} - ${namaKelas}`;
    }

    if (namaKelas) {
      return namaKelas;
    }
  }

  return '-';
};

const resolveJenisLabel = (izin) => (
  izin?.jenis_izin_label
  || (typeof izin?.jenis_izin === 'string' ? izin.jenis_izin.replaceAll('_', ' ') : '-')
);

const getPendingReviewMeta = (izin) => {
  const state = String(izin?.pending_review_state || '').toLowerCase();

  if (state === 'overdue') {
    return {
      label: izin?.pending_review_label || 'Terlambat direview',
      className: 'bg-rose-50 text-rose-700 border border-rose-200',
    };
  }

  if (state === 'due_today') {
    return {
      label: izin?.pending_review_label || 'Mulai hari ini',
      className: 'bg-amber-50 text-amber-700 border border-amber-200',
    };
  }

  return null;
};

const IzinApprovalTable = ({
  izinList,
  loading,
  onApprove,
  onReject,
  onDownload,
  pagination,
  onPageChange,
  onPerPageChange,
  onView,
}) => {
  const rows = Array.isArray(izinList) ? izinList : [];
  const total = Number(pagination?.total || 0);
  const currentPage = Number(pagination?.current_page || 1);
  const perPage = Number(pagination?.per_page || 10);
  const lastPage = Math.max(1, Number(pagination?.last_page || 1));
  const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const to = Math.min(currentPage * perPage, total);

  return (
    <>
      <TableContainer component={Paper} className="border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <Table
          sx={{
            '& .MuiTableCell-head': {
              fontWeight: 600,
              backgroundColor: '#F8FAFC',
              color: '#1F2937',
            },
            '& .MuiTableCell-root': {
              borderColor: '#E5E7EB',
            },
          }}
        >
          <TableHead>
            <TableRow>
              <TableCell width={60}>No</TableCell>
              <TableCell>Siswa</TableCell>
              <TableCell>Detail Izin</TableCell>
              <TableCell>Waktu</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  Tidak ada data izin yang tersedia
                </TableCell>
              </TableRow>
            )}

            {!loading && rows.map((izin, index) => {
              const status = getStatusMeta(izin.status, izin.status_label);
              const hasDoc = Boolean(izin.dokumen_pendukung || izin.lampiran);
              const kelasLabel = resolveKelasLabel(izin);
              const pendingReviewMeta = getPendingReviewMeta(izin);

              return (
                <TableRow key={izin.id} hover>
                  <TableCell>{(currentPage - 1) * perPage + index + 1}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-semibold">
                        {toInitials(izin.nama || izin.siswa?.nama_lengkap || izin.user?.nama_lengkap)}
                      </div>
                      <div>
                        <Typography variant="body2" className="font-semibold text-gray-900">
                          {izin.nama || izin.siswa?.nama_lengkap || izin.user?.nama_lengkap || '-'}
                        </Typography>
                        <Typography variant="caption" className="text-gray-500">
                          Kelas {kelasLabel}
                        </Typography>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" className="text-gray-900">
                      {izin.alasan || 'Tidak ada alasan'}
                    </Typography>
                    <Typography variant="caption" className="text-gray-500 capitalize">
                      Jenis: {resolveJenisLabel(izin)}
                    </Typography>
                    <Typography variant="caption" className="block text-gray-500">
                      Dampak: {typeof izin.school_days_affected === 'number' ? `${izin.school_days_affected} hari sekolah` : 'menunggu perhitungan'}
                    </Typography>
                    {pendingReviewMeta && (
                      <div className="mt-2">
                        <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${pendingReviewMeta.className}`}>
                          {pendingReviewMeta.label}
                        </span>
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2">{formatDate(izin.tanggal_mulai)}</Typography>
                    <Typography variant="caption" className="text-gray-500">
                      s/d {formatDate(izin.tanggal_selesai)}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip size="small" color={status.color} label={status.label} />
                  </TableCell>
                  <TableCell align="center">
                    <Box className="flex items-center justify-center gap-1">
                      {onView && (
                        <IconButton size="small" color="primary" onClick={() => onView(izin.id)} title="Detail">
                          <Eye className="w-4 h-4" />
                        </IconButton>
                      )}

                      {String(izin.status || '').toLowerCase() === 'pending' && (
                        <>
                          <IconButton size="small" color="success" onClick={() => onApprove(izin)} title="Setujui">
                            <CheckCircle className="w-4 h-4" />
                          </IconButton>
                          <IconButton size="small" color="error" onClick={() => onReject(izin)} title="Tolak">
                            <XCircle className="w-4 h-4" />
                          </IconButton>
                        </>
                      )}

                      {hasDoc && (
                        <IconButton
                          size="small"
                          color="info"
                          onClick={() => onDownload(izin.id, `dokumen_izin_${izin.id}.pdf`)}
                          title="Unduh Dokumen"
                        >
                          <Download className="w-4 h-4" />
                        </IconButton>
                      )}
                    </Box>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </TableContainer>

      <Paper className="mt-4 px-4 py-3 border border-gray-200 rounded-xl shadow-sm">
        <Box className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <Typography variant="body2" color="text.secondary">
            Menampilkan {from} - {to} dari {total} data
          </Typography>
          <Box className="flex items-center gap-4">
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="text.secondary">
                Per halaman:
              </Typography>
              <Select
                size="small"
                value={perPage}
                onChange={(event) => onPerPageChange && onPerPageChange(Number(event.target.value))}
                sx={{ minWidth: 84 }}
              >
                {[10, 15, 25, 50].map((size) => (
                  <MenuItem key={size} value={size}>
                    {size}
                  </MenuItem>
                ))}
              </Select>
            </Box>
            <Pagination
              page={currentPage}
              count={lastPage}
              onChange={(_, value) => onPageChange(value)}
              color="primary"
              shape="rounded"
              size="small"
            />
          </Box>
        </Box>
      </Paper>
    </>
  );
};

IzinApprovalTable.propTypes = {
  izinList: PropTypes.oneOfType([
    PropTypes.arrayOf(
      PropTypes.shape({
        id: PropTypes.number.isRequired,
        nama: PropTypes.string,
        kelas: PropTypes.string,
        alasan: PropTypes.string,
        tanggal_mulai: PropTypes.string,
        tanggal_selesai: PropTypes.string,
        status: PropTypes.string,
      }),
    ),
    PropTypes.object,
  ]),
  loading: PropTypes.bool.isRequired,
  onApprove: PropTypes.func.isRequired,
  onReject: PropTypes.func.isRequired,
  onDownload: PropTypes.func.isRequired,
  pagination: PropTypes.shape({
    current_page: PropTypes.number.isRequired,
    last_page: PropTypes.number.isRequired,
    per_page: PropTypes.number.isRequired,
    total: PropTypes.number.isRequired,
  }).isRequired,
  onPageChange: PropTypes.func.isRequired,
  onPerPageChange: PropTypes.func,
  onView: PropTypes.func,
};

IzinApprovalTable.defaultProps = {
  onPerPageChange: null,
  onView: null,
};

export default IzinApprovalTable;

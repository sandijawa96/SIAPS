import React, { useEffect, useMemo, useState } from 'react';
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
import { formatDate } from '../../../utils/dateUtils';

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

const getJenisIzinLabel = (jenis) => {
  const map = {
    sakit: 'Sakit',
    izin: 'Izin Pribadi',
    keperluan_keluarga: 'Urusan Keluarga',
    cuti: 'Cuti',
    dinas_luar: 'Dinas Luar',
  };
  return map[jenis] || jenis || '-';
};

const IzinPegawaiTable = ({ data = [], loading = false, onView, onApprove, onReject }) => {
  const rows = Array.isArray(data) ? data : [];
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  useEffect(() => {
    setPage(1);
  }, [data]);

  const totalRows = rows.length;
  const lastPage = Math.max(1, Math.ceil(totalRows / perPage));
  const safePage = Math.min(page, lastPage);

  const paginatedRows = useMemo(
    () => rows.slice((safePage - 1) * perPage, safePage * perPage),
    [rows, safePage, perPage],
  );

  const from = totalRows === 0 ? 0 : (safePage - 1) * perPage + 1;
  const to = Math.min(safePage * perPage, totalRows);

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
              <TableCell>Pegawai</TableCell>
              <TableCell>Jenis Izin</TableCell>
              <TableCell>Tanggal</TableCell>
              <TableCell>Durasi</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Diajukan</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && paginatedRows.length === 0 && (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  Tidak ada data izin pegawai
                </TableCell>
              </TableRow>
            )}

            {!loading && paginatedRows.map((izin, index) => {
              const status = getStatusMeta(izin.status, izin.status_label);

              return (
                <TableRow key={izin.id} hover>
                  <TableCell>{(safePage - 1) * perPage + index + 1}</TableCell>
                  <TableCell>
                    <div>
                      <Typography variant="body2" className="font-semibold text-gray-900">
                        {izin.user?.nama_lengkap || izin.user?.name || '-'}
                      </Typography>
                      <Typography variant="caption" className="text-gray-500">
                        {izin.user?.nip || '-'} • {izin.user?.departemen || '-'}
                      </Typography>
                    </div>
                  </TableCell>
                  <TableCell>{izin.jenis_izin_label || getJenisIzinLabel(izin.jenis_izin)}</TableCell>
                  <TableCell>
                    <Typography variant="body2">{formatDate(izin.tanggal_mulai)}</Typography>
                    {izin.tanggal_selesai && izin.tanggal_selesai !== izin.tanggal_mulai && (
                      <Typography variant="caption" className="text-gray-500">
                        s/d {formatDate(izin.tanggal_selesai)}
                      </Typography>
                    )}
                  </TableCell>
                  <TableCell>{izin.durasi_hari || 0} hari</TableCell>
                  <TableCell>
                    <Chip size="small" color={status.color} label={status.label} />
                  </TableCell>
                  <TableCell>{formatDate(izin.created_at)}</TableCell>
                  <TableCell align="center">
                    <Box className="flex items-center justify-center gap-1">
                      <IconButton size="small" color="primary" onClick={() => onView(izin.id)} title="Detail">
                        <Eye className="w-4 h-4" />
                      </IconButton>

                      {String(izin.status || '').toLowerCase() === 'pending' && (
                        <>
                          <IconButton size="small" color="success" onClick={() => onApprove(izin.id)} title="Setujui">
                            <CheckCircle className="w-4 h-4" />
                          </IconButton>
                          <IconButton size="small" color="error" onClick={() => onReject(izin.id)} title="Tolak">
                            <XCircle className="w-4 h-4" />
                          </IconButton>
                        </>
                      )}

                      {izin.dokumen_pendukung && (
                        <IconButton
                          size="small"
                          color="info"
                          onClick={() => window.open(izin.dokumen_pendukung, '_blank', 'noopener,noreferrer')}
                          title="Dokumen"
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
            Menampilkan {from} - {to} dari {totalRows} data
          </Typography>
          <Box className="flex items-center gap-4">
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="text.secondary">
                Per halaman:
              </Typography>
              <Select
                size="small"
                value={perPage}
                onChange={(event) => {
                  setPerPage(Number(event.target.value));
                  setPage(1);
                }}
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
              page={safePage}
              count={lastPage}
              onChange={(_, value) => setPage(value)}
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

export { IzinPegawaiTable };
export default IzinPegawaiTable;


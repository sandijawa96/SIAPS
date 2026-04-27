import React from 'react';
import {
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
  Box,
  Chip,
  Skeleton,
  Pagination,
  Select,
  MenuItem
} from '@mui/material';
import { motion } from 'framer-motion';

const LoadingRow = () => (
  <TableRow>
    <TableCell><Skeleton variant="text" width="80%" /></TableCell>
    <TableCell><Skeleton variant="text" width="60%" /></TableCell>
    <TableCell><Skeleton variant="text" width="40%" /></TableCell>
    <TableCell><Skeleton variant="text" width="40%" /></TableCell>
    <TableCell><Skeleton variant="text" width="40%" /></TableCell>
    <TableCell><Skeleton variant="text" width="40%" /></TableCell>
    <TableCell><Skeleton variant="text" width="40%" /></TableCell>
    <TableCell><Skeleton variant="text" width="50%" /></TableCell>
    <TableCell><Skeleton variant="text" width="50%" /></TableCell>
    <TableCell><Skeleton variant="text" width="50%" /></TableCell>
  </TableRow>
);

const getPersentaseColor = (persentase) => {
  if (persentase >= 95) return 'success';
  if (persentase >= 85) return 'warning';
  return 'error';
};

const getPelanggaranColor = (isExceeded, totalMinutes) => {
  if (isExceeded) return 'error';
  if ((totalMinutes || 0) > 0) return 'warning';
  return 'success';
};

const getStatusColor = (tone, isExceeded) => {
  if (tone === 'error') return 'error';
  if (tone === 'warning') return 'warning';
  if (tone === 'success') return 'success';
  return isExceeded ? 'error' : 'success';
};

const ReportTable = ({
  data = [],
  loading,
  error,
  page = 1,
  onPageChange = () => {},
  perPage = 25,
  onPerPageChange = () => {},
  pagination = null,
}) => {
  const totalRows = Number(pagination?.total || data.length || 0);
  const currentPage = Number(pagination?.current_page || page || 1);
  const lastPage = Math.max(1, Number(pagination?.last_page || 1));
  const from = Number(pagination?.from || (totalRows > 0 ? (currentPage - 1) * perPage + 1 : 0));
  const to = Number(pagination?.to || Math.min(currentPage * perPage, totalRows));

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay: 0.6 }}
    >
      <Paper elevation={2} className="bg-white rounded-lg shadow-sm border border-gray-100">
        <Box className="p-4 border-b border-gray-200">
          <Typography variant="h6" className="text-gray-900 font-semibold">
            Data Laporan Kehadiran
          </Typography>
          <Typography variant="body2" className="text-gray-600 mt-1">
            Ringkasan kehadiran berdasarkan filter yang dipilih
          </Typography>
        </Box>

        <TableContainer>
          <Table>
            <TableHead className="bg-gray-50">
              <TableRow>
                <TableCell className="font-semibold text-gray-700">
                  Nama
                </TableCell>
                <TableCell className="font-semibold text-gray-700">
                  Kelas
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Hadir Efektif
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Terlambat (hari/menit)
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Izin
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Belum Absen
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Alpha (hari/menit)
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  % Kehadiran
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Pelanggaran Total
                </TableCell>
                <TableCell className="font-semibold text-gray-700" align="center">
                  Status
                </TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading ? (
                // Loading skeleton
                Array.from({ length: 3 }).map((_, index) => (
                  <LoadingRow key={index} />
                ))
              ) : data.length === 0 ? (
                // Empty state
                <TableRow>
                  <TableCell colSpan={10} align="center" className="py-12">
                    <Box className="flex flex-col items-center">
                      <Typography variant="h6" className="text-gray-500 mb-2">
                        Tidak ada data
                      </Typography>
                      <Typography variant="body2" className="text-gray-400">
                        Silakan ubah filter untuk melihat data
                      </Typography>
                    </Box>
                  </TableCell>
                </TableRow>
              ) : (
                // Data rows
                data.map((item, index) => (
                  <motion.tr
                    key={index}
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.3, delay: index * 0.1 }}
                    component={TableRow}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    <TableCell>
                      <Typography variant="body2" className="font-medium text-gray-900">
                        {item.nama}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" className="font-medium text-gray-900">
                        {item.kelas || '-'}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center">
                        <Box className="w-2 h-2 bg-green-500 rounded-full mr-2"></Box>
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {item.hadir}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell align="center">
                      <Typography variant="body2" className="font-medium text-gray-900">
                        {(item.terlambat || 0)} ({item.terlambatMenit || 0}m)
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Box className="flex items-center justify-center">
                        <Box className="w-2 h-2 bg-blue-500 rounded-full mr-2"></Box>
                        <Typography variant="body2" className="font-medium text-gray-900">
                          {item.izin}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell align="center">
                      <Typography variant="body2" className="font-medium text-gray-900">
                        {item.belumAbsen || 0}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Typography variant="body2" className="font-medium text-gray-900">
                        {(item.alpha || 0)} ({item.alpaMenit || 0}m)
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Chip
                        label={`${item.persentaseKehadiran}%`}
                        color={getPersentaseColor(item.persentaseKehadiran)}
                        size="small"
                        className="font-medium"
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Box className="flex flex-col items-center gap-1">
                        <Chip
                          label={`${item.totalPelanggaranMenit || 0}m (${item.persentasePelanggaran || 0}%)`}
                          color={getPelanggaranColor(item.melewatiBatasPelanggaran, item.totalPelanggaranMenit)}
                          size="small"
                          className="font-medium"
                        />
                        <Typography variant="caption" className="text-gray-500">
                          Telat {item.terlambatMenit || 0}m | TAP {item.tapMenit || 0}m | Alpha {item.alpaMenit || 0}m
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell align="center">
                      <Chip
                        label={item.thresholdStatusLabel || (item.melewatiBatasPelanggaran ? 'Melewati Batas' : 'Dalam Batas')}
                        color={getStatusColor(item.thresholdStatusTone, item.melewatiBatasPelanggaran)}
                        size="small"
                        variant="filled"
                        className="font-medium"
                      />
                    </TableCell>
                  </motion.tr>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>

        {!loading && data.length > 0 && (
          <Box className="p-4 border-t border-gray-200 bg-gray-50">
            <Box className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <Typography variant="body2" className="text-gray-600">
                Menampilkan {from} - {to} dari total {totalRows} entri
              </Typography>
              <Box className="flex items-center gap-3">
                <Box className="flex items-center gap-2">
                  <Typography variant="body2" className="text-gray-600">
                    Per halaman:
                  </Typography>
                  <Select
                    size="small"
                    value={perPage}
                    onChange={(event) => {
                      onPerPageChange(Number(event.target.value));
                      onPageChange(1);
                    }}
                    sx={{ minWidth: 80 }}
                  >
                    {[10, 25, 50, 100].map((size) => (
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
                  size="small"
                  shape="rounded"
                />
              </Box>
            </Box>
          </Box>
        )}
      </Paper>
    </motion.div>
  );
};

export default ReportTable;

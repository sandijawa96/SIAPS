import React from 'react';
import {
  Alert,
  Box,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  IconButton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import {
  Eye,
  Navigation,
  Play,
  ShieldAlert,
  Square,
} from 'lucide-react';
import { formatServerDateTime } from '../../services/serverClock';
import { getTrackingStatusReasonLabel } from '../../utils/trackingStatus';

const STATUS_META = {
  active: { label: 'Fresh dalam area', color: 'success' },
  outside_area: { label: 'Fresh luar area', color: 'warning' },
  tracking_disabled: { label: 'Tracking nonaktif', color: 'default' },
  outside_schedule: { label: 'Di luar jadwal', color: 'info' },
  stale: { label: 'Stale', color: 'warning' },
  gps_disabled: { label: 'GPS mati', color: 'error' },
  no_data: { label: 'Belum ada data', color: 'default' },
};

const getStatusMeta = (status) => STATUS_META[status] || STATUS_META.no_data;

const getGpsQualityMeta = (status) => {
  switch (status) {
    case 'good':
      return { label: 'GPS baik', color: 'success' };
    case 'moderate':
      return { label: 'GPS sedang', color: 'warning' };
    case 'poor':
      return { label: 'GPS lemah', color: 'error' };
    default:
      return { label: 'GPS tidak diketahui', color: 'default' };
  }
};

const SummaryChip = ({ label, value, color = 'default' }) => (
  <Chip size="small" color={color} variant="outlined" label={`${label} ${value}`} />
);

const StudentList = ({
  students,
  loading,
  error,
  selectedStudent,
  onStudentSelect,
  onViewDetails = () => {},
  canManageTrackingSession = false,
  onStartTrackingSession = () => {},
  onStopTrackingSession = () => {},
  liveTrackingEnabled = true,
  pagination,
  pageSizeOptions = [25, 50, 100, 150, 200],
  onPageChange,
  onRowsPerPageChange,
}) => {
  const summary = {
    active: students.filter((student) => student.status === 'active').length,
    outside: students.filter((student) => student.status === 'outside_area').length,
    trackingDisabled: students.filter((student) => student.status === 'tracking_disabled').length,
    outsideSchedule: students.filter((student) => student.status === 'outside_schedule').length,
    stale: students.filter((student) => student.status === 'stale').length,
    gpsDisabled: students.filter((student) => student.status === 'gps_disabled').length,
    noData: students.filter((student) => student.status === 'no_data').length,
  };
  const needsAttention = summary.outside + summary.stale + summary.gpsDisabled;

  const page = Math.max(0, Number(pagination?.page || 1) - 1);
  const rowsPerPage = Number(pagination?.perPage || pageSizeOptions[0]);
  const totalRows = Number(pagination?.total || students.length || 0);

  if (error) {
    return (
      <Card className="rounded-3xl border border-rose-200 shadow-sm">
        <CardContent>
          <Alert severity="error">{error}</Alert>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="rounded-3xl border border-slate-200 shadow-sm">
      <CardContent className="space-y-4 p-5">
        <Box className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <Box>
            <Typography variant="h6" className="font-semibold text-slate-900">
              Daftar Operasional
            </Typography>
            <Typography variant="body2" className="text-slate-600">
              Fokus pada siapa siswanya, siapa penanggung jawabnya, kondisi saat ini, dan sinyal terakhir yang diterima.
            </Typography>
          </Box>

          <Box className="flex flex-wrap gap-2">
            <SummaryChip label="Total" value={totalRows} color="primary" />
            <SummaryChip label="Fresh" value={summary.active + summary.outside} color="success" />
            <SummaryChip label="Perlu tindakan" value={needsAttention} color={needsAttention > 0 ? 'warning' : 'default'} />
            <SummaryChip label="Tracking nonaktif" value={summary.trackingDisabled} />
            <SummaryChip label="Di luar jadwal" value={summary.outsideSchedule} color="info" />
            <SummaryChip label="Belum ada data" value={summary.noData} />
          </Box>
        </Box>

        {!liveTrackingEnabled ? (
          <Alert severity="info">
            Live tracking global sedang dinonaktifkan oleh admin. Aksi mulai pemantauan tambahan untuk siswa baru ditahan sampai policy diaktifkan kembali.
          </Alert>
        ) : null}

        {loading && students.length === 0 ? (
          <Box className="flex items-center justify-center gap-3 py-12">
            <CircularProgress size={32} />
            <Typography variant="body2" className="text-slate-600">
              Memuat daftar siswa...
            </Typography>
          </Box>
        ) : students.length === 0 ? (
          <Box className="rounded-2xl border border-dashed border-slate-200 py-12 text-center">
            <Typography variant="h6" className="mb-2 text-slate-500">
              Tidak ada siswa ditemukan
            </Typography>
            <Typography variant="body2" className="text-slate-400">
              Ubah filter untuk mempersempit atau memperluas hasil.
            </Typography>
          </Box>
        ) : (
          <>
            <TableContainer className="rounded-2xl border border-slate-200">
              <Table size="small" stickyHeader>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ minWidth: 220 }}>Siswa</TableCell>
                    <TableCell sx={{ minWidth: 190 }}>Scope</TableCell>
                    <TableCell sx={{ minWidth: 260 }}>Kondisi</TableCell>
                    <TableCell sx={{ minWidth: 260 }}>Sinyal Terakhir</TableCell>
                    <TableCell align="right" sx={{ minWidth: 120 }}>Aksi</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {students.map((student) => {
                    const statusMeta = getStatusMeta(student.status);
                    const gpsMeta = getGpsQualityMeta(student.gpsQualityStatus);
                    const isSelected = selectedStudent?.id === student.id;
                    const isSessionActive = Boolean(student.trackingSessionActive);
                    const areaLabel = student.status === 'gps_disabled'
                      ? (student.isInSchoolArea ? 'Lokasi terakhir di dalam area' : 'Lokasi terakhir di luar area')
                      : student.status === 'tracking_disabled'
                        ? 'Tracking dinonaktifkan admin'
                      : student.status === 'outside_schedule'
                        ? 'Tracking dijeda di luar jadwal'
                      : student.hasTrackingData
                        ? (student.isInSchoolArea ? 'Dalam area' : 'Luar area')
                        : 'Belum ada snapshot';

                    return (
                      <TableRow
                        key={student.id}
                        hover
                        selected={isSelected}
                        sx={{
                          '& td': {
                            verticalAlign: 'top',
                            py: 2,
                          },
                        }}
                      >
                        <TableCell>
                          <Typography variant="body2" className="font-semibold text-slate-900">
                            {student.name}
                          </Typography>
                          <Typography variant="caption" className="block text-slate-500">
                            {student.email || '-'}
                          </Typography>
                          {isSessionActive ? (
                            <Chip
                              size="small"
                              color="info"
                              variant="outlined"
                              label="Pemantauan tambahan aktif"
                              className="!mt-2"
                            />
                          ) : null}
                        </TableCell>

                        <TableCell>
                          <Typography variant="body2" className="font-medium text-slate-900">
                            {student.class || '-'}
                          </Typography>
                          <Typography variant="caption" className="block text-slate-500">
                            Tingkat {student.level || '-'}
                          </Typography>
                          <Typography variant="caption" className="block text-slate-500">
                            Wali {student.homeroomTeacherName || 'Belum ditentukan'}
                          </Typography>
                        </TableCell>

                        <TableCell>
                          <Box className="space-y-2">
                            <Box className="flex flex-wrap gap-2">
                              <Chip size="small" label={statusMeta.label} color={statusMeta.color} variant="outlined" />
                              <Chip size="small" label={gpsMeta.label} color={gpsMeta.color} variant="outlined" />
                              <Chip size="small" label={areaLabel} variant="outlined" />
                            </Box>
                            <Typography variant="caption" className="block text-slate-500">
                              {getTrackingStatusReasonLabel(student.trackingStatusReason, student.status)}
                            </Typography>
                          </Box>
                        </TableCell>

                        <TableCell>
                          <Typography variant="body2" className="font-medium text-slate-900">
                            {student.lastUpdate
                              ? (formatServerDateTime(student.lastUpdate, 'id-ID') || '-')
                              : 'Belum ada data'}
                          </Typography>
                          <Typography
                            variant="caption"
                            className="mt-1 block max-w-[16rem] truncate text-slate-500"
                            title={student.location?.address || '-'}
                          >
                            {student.location?.address || '-'}
                          </Typography>
                          <Typography variant="caption" className="block text-slate-500">
                            Akurasi {student.location?.accuracy ? `${student.location.accuracy} m` : '-'}
                            {student.distanceToNearest ? ` | Jarak terdekat ${Math.round(student.distanceToNearest)} m` : ''}
                          </Typography>
                        </TableCell>

                        <TableCell align="right">
                          <Box className="flex items-center justify-end gap-1">
                            {canManageTrackingSession ? (
                              <Tooltip title={
                                isSessionActive
                                  ? 'Hentikan pemantauan tambahan'
                                  : (!liveTrackingEnabled ? 'Live tracking global sedang nonaktif' : 'Mulai pemantauan tambahan')
                              }>
                                <IconButton
                                  size="small"
                                  disabled={!liveTrackingEnabled && !isSessionActive}
                                  onClick={() => {
                                    if (isSessionActive) {
                                      onStopTrackingSession(student.id);
                                    } else {
                                      onStartTrackingSession(student.id);
                                    }
                                  }}
                                >
                                  {isSessionActive ? <Square className="h-4 w-4" /> : <Play className="h-4 w-4" />}
                                </IconButton>
                              </Tooltip>
                            ) : null}

                            <Tooltip title="Fokuskan di peta">
                              <IconButton size="small" onClick={() => onStudentSelect(student)}>
                                <Navigation className="h-4 w-4" />
                              </IconButton>
                            </Tooltip>

                            <Tooltip title="Lihat detail">
                              <IconButton size="small" onClick={() => onViewDetails(student)}>
                                <Eye className="h-4 w-4" />
                              </IconButton>
                            </Tooltip>
                          </Box>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </TableContainer>

            <TablePagination
              component="div"
              count={totalRows}
              page={page}
              onPageChange={(_, nextPage) => onPageChange?.(nextPage + 1)}
              rowsPerPage={rowsPerPage}
              onRowsPerPageChange={(event) => onRowsPerPageChange?.(Number(event.target.value))}
              rowsPerPageOptions={pageSizeOptions}
              labelRowsPerPage="Baris per halaman"
            />
          </>
        )}

        {loading && students.length > 0 ? (
          <Alert severity="info" icon={<ShieldAlert className="h-4 w-4" />}>
            Data sedang diperbarui. Halaman terakhir tetap ditampilkan agar operator tidak kehilangan konteks.
          </Alert>
        ) : null}
      </CardContent>
    </Card>
  );
};

export default StudentList;

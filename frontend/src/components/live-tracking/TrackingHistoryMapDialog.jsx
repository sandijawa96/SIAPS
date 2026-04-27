import React, { useCallback, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
  useMediaQuery,
} from '@mui/material';
import { useTheme } from '@mui/material/styles';
import { Activity, Clock3, Map as MapIcon, Users } from 'lucide-react';
import { toPng } from 'html-to-image';
import TrackingHistoryRouteMap from './TrackingHistoryRouteMap';
import { formatServerDateTime } from '../../services/serverClock';

const ROUTE_COLORS = ['#2563eb', '#dc2626', '#0f766e', '#ca8a04', '#7c3aed'];

const formatDurationLabel = (minutes) => {
  const totalMinutes = Number(minutes || 0);
  if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) {
    return '0 menit';
  }

  const hours = Math.floor(totalMinutes / 60);
  const remainingMinutes = totalMinutes % 60;
  if (hours <= 0) {
    return `${remainingMinutes} menit`;
  }

  return `${hours} jam ${String(remainingMinutes).padStart(2, '0')} menit`;
};

const TrackingHistoryMapDialog = ({
  open,
  onClose,
  loading = false,
  error = null,
  data = null,
  attendanceLocations = [],
  settings = {},
  historyPolicy = {},
  compareOptions = [],
  searchText = '',
  searchLoading = false,
  onSearchTextChange = () => {},
  selectedStudentIds = [],
  onSelectedStudentIdsChange = () => {},
  filters = {},
  onFiltersChange = () => {},
  onApply = () => {},
  onDownloadPdf = async () => false,
  focusedUserId = null,
  onFocusUserChange = () => {},
  compareLimit = 5,
}) => {
  const theme = useTheme();
  const fullScreen = useMediaQuery(theme.breakpoints.down('md'));
  const exportSurfaceRef = useRef(null);
  const [exportingImage, setExportingImage] = useState(false);
  const [exportingPdf, setExportingPdf] = useState(false);
  const [exportScope, setExportScope] = useState('focus');
  const [captureScope, setCaptureScope] = useState(null);

  const sessions = useMemo(() => {
    const rawSessions = Array.isArray(data?.sessions) ? data.sessions : [];
    return rawSessions.map((session, index) => ({
      ...session,
      color: ROUTE_COLORS[index % ROUTE_COLORS.length],
    }));
  }, [data?.sessions]);

  const selectedOptions = useMemo(() => {
    const optionMap = new globalThis.Map(compareOptions.map((option) => [option.id, option]));
    return selectedStudentIds
      .map((studentId) => optionMap.get(studentId))
      .filter(Boolean);
  }, [compareOptions, selectedStudentIds]);

  const focusSession = useMemo(() => {
    if (focusedUserId) {
      const matched = sessions.find((session) => session?.user?.id === focusedUserId);
      if (matched) {
        return matched;
      }
    }

    return sessions[0] || null;
  }, [focusedUserId, sessions]);

  const studentsWithoutPoints = useMemo(
    () => sessions.filter((session) => Number(session?.statistics?.total_points || 0) === 0),
    [sessions]
  );

  const simplifiedSessions = useMemo(
    () => sessions.filter((session) => Boolean(session?.statistics?.is_route_simplified)),
    [sessions]
  );

  const summary = data?.summary || {};

  const exportSessions = useMemo(() => {
    if (captureScope === 'focus' && focusSession) {
      return [focusSession];
    }

    return sessions;
  }, [captureScope, focusSession, sessions]);

  const exportFocusSession = useMemo(() => {
    if (captureScope === 'focus') {
      return focusSession;
    }

    if (focusedUserId) {
      return exportSessions.find((session) => session?.user?.id === focusedUserId) || focusSession;
    }

    return exportSessions[0] || focusSession || null;
  }, [captureScope, exportSessions, focusSession, focusedUserId]);

  const exportFocusPoints = Array.isArray(exportFocusSession?.points) ? exportFocusSession.points : [];

  const createExportImage = useCallback(async () => {
    if (!exportSurfaceRef.current) {
      throw new Error('Area histori peta belum siap diekspor');
    }

    setCaptureScope(exportScope);

    await new Promise((resolve) => {
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(resolve);
      });
    });
    await new Promise((resolve) => window.setTimeout(resolve, 140));

    try {
      return await toPng(exportSurfaceRef.current, {
        cacheBust: true,
        backgroundColor: '#ffffff',
        pixelRatio: 2,
        skipFonts: false,
      });
    } finally {
      setCaptureScope(null);
    }
  }, [exportScope]);

  const handleExportImage = useCallback(async () => {
    try {
      setExportingImage(true);
      const dataUrl = await createExportImage();
      const anchor = document.createElement('a');
      const suffix = String(filters?.date || 'today').replace(/[^0-9-]/g, '');
      anchor.download = `histori-peta-${exportScope}-${focusSession?.user?.id || 'tracking'}-${suffix || 'today'}.png`;
      anchor.href = dataUrl;
      anchor.click();
    } catch (exportError) {
      console.error('Failed to export history map image:', exportError);
      window.alert('Ekspor gambar gagal. Coba ulangi setelah peta selesai dimuat penuh.');
    } finally {
      setExportingImage(false);
    }
  }, [createExportImage, exportScope, filters?.date, focusSession?.user?.id]);

  const handleExportPdf = useCallback(async () => {
    try {
      setExportingPdf(true);
      const downloaded = await onDownloadPdf(exportScope);
      if (!downloaded) {
        throw new Error('Gagal menyiapkan PDF histori peta');
      }
    } catch (exportError) {
      console.error('Failed to export history map PDF:', exportError);
      window.alert('Unduh PDF gagal. Coba ulangi beberapa saat lagi.');
    } finally {
      setExportingPdf(false);
    }
  }, [exportScope, onDownloadPdf]);

  return (
    <Dialog open={open} onClose={onClose} maxWidth="xl" fullWidth fullScreen={fullScreen}>
      <DialogTitle>Histori Peta Siswa</DialogTitle>
      <DialogContent dividers className="space-y-4">
        <Alert severity="info" className="rounded-2xl">
          Peta ini menampilkan titik histori yang benar-benar tersimpan, bukan setiap ping GPS. Titik baru masuk saat perpindahan minimal {Number(historyPolicy?.minDistanceMeters || 20)} meter, saat status berubah, dan checkpoint saat diam.
        </Alert>

        <Card className="rounded-3xl border border-slate-200 shadow-none">
          <CardContent className="space-y-4 p-5">
            <Box className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,2.4fr)_repeat(3,minmax(0,1fr))_auto]">
              <Autocomplete
                multiple
                options={compareOptions}
                value={selectedOptions}
                inputValue={searchText}
                loading={searchLoading}
                disableCloseOnSelect
                filterOptions={(options) => options}
                getOptionLabel={(option) => option?.label || option?.name || ''}
                isOptionEqualToValue={(option, value) => option.id === value.id}
                noOptionsText={searchText.trim().length >= 2 ? 'Siswa tidak ditemukan' : 'Ketik minimal 2 huruf'}
                loadingText="Mencari siswa..."
                onInputChange={(event, value, reason) => onSearchTextChange(value, reason)}
                onChange={(event, value) => onSelectedStudentIdsChange(value.map((option) => option.id))}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label={`Siswa (maks ${compareLimit})`}
                    placeholder="Cari siswa lintas roster"
                    helperText="Mode detail untuk 1 siswa, compare ringkas hingga 5 siswa."
                  />
                )}
              />
              <TextField
                label="Tanggal"
                type="date"
                value={filters?.date || ''}
                onChange={(event) => onFiltersChange({ date: event.target.value })}
                InputLabelProps={{ shrink: true }}
              />
              <TextField
                label="Mulai"
                type="time"
                value={filters?.startTime || ''}
                onChange={(event) => onFiltersChange({ startTime: event.target.value })}
                InputLabelProps={{ shrink: true }}
              />
              <TextField
                label="Selesai"
                type="time"
                value={filters?.endTime || ''}
                onChange={(event) => onFiltersChange({ endTime: event.target.value })}
                InputLabelProps={{ shrink: true }}
              />
              <Button
                variant="contained"
                onClick={onApply}
                disabled={loading || selectedStudentIds.length === 0}
                className="h-14 rounded-2xl"
              >
                {loading ? 'Memuat...' : 'Muat Histori'}
              </Button>
            </Box>

            <Box className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <Box className="flex items-center gap-2 text-slate-500">
                  <Users className="h-4 w-4" />
                  <Typography variant="caption">Siswa dipilih</Typography>
                </Box>
                <Typography variant="h6" className="mt-2 font-semibold text-slate-900">
                  {summary?.selected_students ?? selectedStudentIds.length}
                </Typography>
              </Box>
              <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <Box className="flex items-center gap-2 text-slate-500">
                  <MapIcon className="h-4 w-4" />
                  <Typography variant="caption">Total titik</Typography>
                </Box>
                <Typography variant="h6" className="mt-2 font-semibold text-slate-900">
                  {summary?.total_points ?? 0}
                </Typography>
              </Box>
              <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <Box className="flex items-center gap-2 text-slate-500">
                  <Activity className="h-4 w-4" />
                  <Typography variant="caption">Estimasi jarak</Typography>
                </Box>
                <Typography variant="h6" className="mt-2 font-semibold text-slate-900">
                  {Number(summary?.estimated_distance_km ?? 0).toFixed(2)} km
                </Typography>
              </Box>
              <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <Box className="flex items-center gap-2 text-slate-500">
                  <Clock3 className="h-4 w-4" />
                  <Typography variant="caption">Fokus durasi</Typography>
                </Box>
                <Typography variant="h6" className="mt-2 font-semibold text-slate-900">
                  {formatDurationLabel(focusSession?.statistics?.duration_minutes)}
                </Typography>
              </Box>
            </Box>

            {sessions.length > 0 ? (
              <Box className="flex flex-wrap gap-2">
                {sessions.map((session) => {
                  const isActive = session?.user?.id === (focusSession?.user?.id || null);
                  return (
                    <Chip
                      key={session?.user?.id || session?.color}
                      label={`${session?.user?.nama_lengkap || '-'} | ${session?.statistics?.total_points ?? 0} titik`}
                      onClick={() => onFocusUserChange(session?.user?.id || null)}
                      variant={isActive ? 'filled' : 'outlined'}
                      className={isActive ? 'text-white' : ''}
                      sx={{
                        borderColor: session?.color || '#2563eb',
                        backgroundColor: isActive ? (session?.color || '#2563eb') : 'transparent',
                        color: isActive ? '#ffffff' : (session?.color || '#2563eb'),
                        '& .MuiChip-label': {
                          fontWeight: 600,
                        },
                      }}
                    />
                  );
                })}
              </Box>
            ) : null}
          </CardContent>
        </Card>

        {error ? <Alert severity="error">{error}</Alert> : null}
        {studentsWithoutPoints.length > 0 ? (
          <Alert severity="warning" className="rounded-2xl">
            Belum ada titik histori pada filter ini untuk: {studentsWithoutPoints.map((session) => session?.user?.nama_lengkap).filter(Boolean).join(', ')}.
          </Alert>
        ) : null}
        {simplifiedSessions.length > 0 ? (
          <Alert severity="info" className="rounded-2xl">
            Jalur peta disederhanakan untuk menjaga keterbacaan. Timeline tetap menampilkan seluruh titik tersimpan.
          </Alert>
        ) : null}

        <Box ref={exportSurfaceRef} className="space-y-4 rounded-3xl bg-white">
          <Box className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2.1fr)_minmax(320px,0.9fr)]">
            <TrackingHistoryRouteMap
              sessions={exportSessions}
              focusedUserId={exportFocusSession?.user?.id || null}
              onFocusUser={onFocusUserChange}
              attendanceLocations={attendanceLocations}
              settings={settings}
              height={fullScreen ? 420 : 560}
            />

            <Card className="rounded-3xl border border-slate-200 shadow-none">
              <CardContent className="space-y-4 p-5">
                <Box>
                  <Typography variant="subtitle1" className="font-semibold text-slate-900">
                    Timeline Titik Fokus
                  </Typography>
                  <Typography variant="body2" className="mt-1 text-slate-500">
                    {exportFocusSession?.user?.nama_lengkap || 'Pilih siswa'}
                  </Typography>
                </Box>

                {exportFocusSession ? (
                  <Box className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <Typography variant="body2" className="font-medium text-slate-900">
                      {exportFocusSession?.user?.kelas || '-'} | Tingkat {exportFocusSession?.user?.tingkat || '-'}
                    </Typography>
                    <Typography variant="caption" className="mt-1 block text-slate-500">
                      {exportFocusSession?.statistics?.started_at ? (formatServerDateTime(exportFocusSession.statistics.started_at, 'id-ID') || '-') : '-'} s/d {exportFocusSession?.statistics?.ended_at ? (formatServerDateTime(exportFocusSession.statistics.ended_at, 'id-ID') || '-') : '-'}
                    </Typography>
                    <Divider className="my-3" />
                    <Box className="grid grid-cols-2 gap-2 text-sm text-slate-700">
                      <div>{exportFocusSession?.statistics?.total_points ?? 0} titik</div>
                      <div>{Number(exportFocusSession?.statistics?.estimated_distance_km ?? 0).toFixed(2)} km</div>
                      <div>{exportFocusSession?.statistics?.in_school_area ?? 0} dalam area</div>
                      <div>{exportFocusSession?.statistics?.outside_school_area ?? 0} luar area</div>
                    </Box>
                    {exportFocusSession?.statistics?.is_route_simplified ? (
                      <Typography variant="caption" className="mt-2 block text-slate-500">
                        Peta memakai {exportFocusSession?.statistics?.map_point_count ?? 0} dari {exportFocusSession?.statistics?.total_points ?? 0} titik tersimpan.
                      </Typography>
                    ) : null}
                  </Box>
                ) : null}

                <Box className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                  {exportFocusPoints.length > 0 ? exportFocusPoints.map((point) => (
                    <Box
                      key={`${exportFocusSession?.user?.id || 'session'}-${point?.id || point?.sequence}`}
                      className="rounded-2xl border border-slate-200 bg-white p-3"
                    >
                      <Box className="flex items-start justify-between gap-3">
                        <Box className="flex items-center gap-3">
                          <Chip
                            size="small"
                            label={`#${point?.sequence || '-'}`}
                            sx={{
                              backgroundColor: exportFocusSession?.color || '#2563eb',
                              color: '#ffffff',
                              fontWeight: 700,
                            }}
                          />
                          <Box>
                            <Typography variant="body2" className="font-medium text-slate-900">
                              {formatServerDateTime(point?.tracked_at, 'id-ID') || '-'}
                            </Typography>
                            <Typography variant="body2" className="text-slate-700">
                              {point?.location_name || 'Lokasi tidak diketahui'}
                            </Typography>
                          </Box>
                        </Box>
                        <Chip
                          size="small"
                          variant="outlined"
                          color={point?.is_in_school_area ? 'success' : 'warning'}
                          label={point?.is_in_school_area ? 'Dalam area' : 'Luar area'}
                        />
                      </Box>
                      <Typography variant="caption" className="mt-2 block text-slate-500">
                        Segmen {Number(point?.distance_from_previous_meters || 0).toFixed(1)} m | Kumulatif {Number(point?.cumulative_distance_meters || 0).toFixed(1)} m | Akurasi {point?.accuracy ?? '-'} m
                      </Typography>
                      {point?.transition ? (
                        <Typography variant="caption" className="mt-1 block font-medium text-slate-600">
                          {point.transition === 'enter_area' ? 'Perubahan: masuk area absensi' : 'Perubahan: keluar area absensi'}
                        </Typography>
                      ) : null}
                    </Box>
                  )) : (
                    <Typography variant="body2" className="text-slate-500">
                      Tidak ada titik histori pada rentang ini.
                    </Typography>
                  )}
                </Box>
              </CardContent>
            </Card>
          </Box>
        </Box>
      </DialogContent>
      <DialogActions>
        <Box className="mr-auto flex flex-wrap items-center gap-3">
          <Typography variant="caption" className="text-slate-500">
            Mode ekspor
          </Typography>
          <ToggleButtonGroup
            exclusive
            size="small"
            value={exportScope}
            onChange={(event, nextScope) => {
              if (nextScope) {
                setExportScope(nextScope);
              }
            }}
          >
            <ToggleButton value="focus">Fokus saja</ToggleButton>
            <ToggleButton value="compare" disabled={sessions.length <= 1}>Semua compare</ToggleButton>
          </ToggleButtonGroup>
        </Box>
        <Button onClick={handleExportImage} disabled={loading || exportingImage || exportingPdf || sessions.length === 0}>
          {exportingImage ? 'Mengekspor gambar...' : 'Ekspor PNG'}
        </Button>
        <Button onClick={handleExportPdf} disabled={loading || exportingImage || exportingPdf || sessions.length === 0}>
          {exportingPdf ? 'Menyiapkan PDF...' : 'Unduh PDF'}
        </Button>
        <Button onClick={onClose}>Tutup</Button>
      </DialogActions>
    </Dialog>
  );
};

export default TrackingHistoryMapDialog;

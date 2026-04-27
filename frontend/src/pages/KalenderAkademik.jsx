import React from 'react';
import {
  Box,
  Button,
  Chip,
  FormControl,
  IconButton,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Tab,
  Tabs,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  Edit,
  Filter,
  Plus,
  RotateCcw,
  Search,
  Trash2,
} from 'lucide-react';
import { usePeriodeAkademik } from '../hooks/usePeriodeAkademik';
import { useEventAkademik } from '../hooks/useEventAkademik';
import { useTahunAjaranManagement } from '../hooks/useTahunAjaranManagement';
import { useServerClock } from '../hooks/useServerClock';
import PeriodeAkademikFormModal from '../components/modals/PeriodeAkademikFormModal';
import EventAkademikFormModal from '../components/modals/EventAkademikFormModal';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';
import { tingkatAPI } from '../services/tingkatService';
import { kelasAPI } from '../services/kelasService';
import { eventAkademikService } from '../services/eventAkademikService';
import { formatServerDate, getServerDateString, toServerCalendarDate, toServerDateInput } from '../services/serverClock';

const EVENT_TYPES = [
  { value: 'all', label: 'Semua Jenis' },
  { value: 'ujian', label: 'Ujian' },
  { value: 'libur', label: 'Libur' },
  { value: 'kegiatan', label: 'Kegiatan' },
  { value: 'deadline', label: 'Deadline' },
  { value: 'rapat', label: 'Rapat' },
  { value: 'pelatihan', label: 'Pelatihan' },
];

const PERIOD_TYPES = [
  { value: 'all', label: 'Semua Jenis' },
  { value: 'pembelajaran', label: 'Pembelajaran' },
  { value: 'ujian', label: 'Ujian' },
  { value: 'libur', label: 'Libur' },
  { value: 'orientasi', label: 'Orientasi' },
];

const PERIOD_COLORS = { pembelajaran: 'success', ujian: 'error', libur: 'warning', orientasi: 'info' };
const EVENT_COLORS = { ujian: 'error', libur: 'warning', kegiatan: 'info', deadline: 'warning', rapat: 'secondary', pelatihan: 'primary' };
const EVENT_TONE_CLASSES = {
  holiday: 'border-red-200 bg-red-50 text-red-700',
  collectiveLeave: 'border-amber-200 bg-amber-50 text-amber-700',
  commemoration: 'border-blue-200 bg-blue-50 text-blue-700',
  exam: 'border-rose-200 bg-rose-50 text-rose-700',
  default: 'border-slate-200 bg-slate-50 text-slate-700',
};

const formatDate = (value) => {
  if (!value) return '-';
  const formatted = formatServerDate(value, 'id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  return formatted || String(value).slice(0, 10);
};

const toDateInput = (value) => (value ? toServerDateInput(value) : '');
const toTimeInput = (value) => (value ? String(value).slice(0, 5) : '');

const getMonthDays = (selectedDate) => {
  const month = selectedDate.getMonth();
  const year = selectedDate.getFullYear();
  const first = new Date(year, month, 1);
  const total = new Date(year, month + 1, 0).getDate();
  const start = first.getDay();
  const days = [];
  for (let i = start - 1; i >= 0; i -= 1) days.push({ date: new Date(year, month, -i), current: false });
  for (let day = 1; day <= total; day += 1) days.push({ date: new Date(year, month, day), current: true });
  while (days.length < 42) days.push({ date: new Date(year, month + 1, days.length - total - start + 1), current: false });
  return days;
};

const isValidCalendarDate = (value) =>
  value instanceof Date && !Number.isNaN(value.getTime());

const resolveServerCalendarDate = (value) => {
  const date = toServerCalendarDate(value);
  return isValidCalendarDate(date) ? date : null;
};

const resolveEventTone = (event) => {
  const metadata = event?.metadata && typeof event.metadata === 'object' ? event.metadata : {};
  const category = metadata?.calendar_category;

  if (event?.jenis === 'libur') {
    if (category === 'cuti_bersama') return EVENT_TONE_CLASSES.collectiveLeave;
    return EVENT_TONE_CLASSES.holiday;
  }

  if (event?.jenis === 'kegiatan' && category === 'peringatan') {
    return EVENT_TONE_CLASSES.commemoration;
  }

  if (event?.jenis === 'ujian') {
    return EVENT_TONE_CLASSES.exam;
  }

  return EVENT_TONE_CLASSES.default;
};

const getMonthBoundaries = (date, timezone) => {
  const start = new Date(date.getFullYear(), date.getMonth(), 1);
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 0);
  return {
    start: getServerDateString(start.getTime(), timezone),
    end: getServerDateString(end.getTime(), timezone),
  };
};

const KalenderAkademik = () => {
  const { serverNowMs, serverDate, timezone } = useServerClock();
  const currentServerCalendarDate = React.useMemo(
    () => resolveServerCalendarDate(serverDate || serverNowMs),
    [serverDate, serverNowMs]
  );
  const [tab, setTab] = React.useState('calendar');
  const [selectedDate, setSelectedDate] = React.useState(() => currentServerCalendarDate);
  const [selectedTahunAjaranId, setSelectedTahunAjaranId] = React.useState('');
  const [search, setSearch] = React.useState('');
  const [jenis, setJenis] = React.useState('all');
  const [eventPage, setEventPage] = React.useState(1);
  const [periodPage, setPeriodPage] = React.useState(1);
  const [eventPerPage, setEventPerPage] = React.useState(10);
  const [periodPerPage, setPeriodPerPage] = React.useState(10);
  const [showEventModal, setShowEventModal] = React.useState(false);
  const [showPeriodModal, setShowPeriodModal] = React.useState(false);
  const [selectedEvent, setSelectedEvent] = React.useState(null);
  const [selectedPeriod, setSelectedPeriod] = React.useState(null);
  const [confirmDelete, setConfirmDelete] = React.useState({ open: false, type: 'event', id: null, name: '' });
  const [tingkatOptions, setTingkatOptions] = React.useState([]);
  const [kelasOptions, setKelasOptions] = React.useState([]);
  const [syncingKalenderIndonesia, setSyncingKalenderIndonesia] = React.useState(false);
  const [syncInfo, setSyncInfo] = React.useState('');
  const [syncInfoType, setSyncInfoType] = React.useState('info');

  const { tahunAjaranList = [], loading: loadingTahun } = useTahunAjaranManagement() || {};
  const numericTahunAjaranId = selectedTahunAjaranId ? Number(selectedTahunAjaranId) : null;
  const { periodeList = [], currentPeriode, loading: loadingPeriod, createPeriode, updatePeriode, deletePeriode } =
    usePeriodeAkademik(numericTahunAjaranId) || {};
  const {
    eventList = [],
    upcomingEvents = [],
    todayEvents = [],
    loading: loadingEvent,
    createEvent,
    updateEvent,
    deleteEvent,
    refreshData: refreshEventData,
    refreshUpcoming,
    refreshToday,
  } = useEventAkademik(numericTahunAjaranId) || {};

  const hasSelectedDate = isValidCalendarDate(selectedDate);

  React.useEffect(() => {
    if (!selectedDate && currentServerCalendarDate) {
      setSelectedDate(currentServerCalendarDate);
    }
  }, [currentServerCalendarDate, selectedDate]);

  React.useEffect(() => {
    if (selectedTahunAjaranId || tahunAjaranList.length === 0) return;
    const active = tahunAjaranList.find((item) => item.status === 'active') || tahunAjaranList[0];
    setSelectedTahunAjaranId(String(active.id));
  }, [tahunAjaranList, selectedTahunAjaranId]);

  React.useEffect(() => {
    const fetchReferenceData = async () => {
      try {
        const kelasParams = { no_pagination: true };
        if (selectedTahunAjaranId) {
          kelasParams.tahun_ajaran_id = Number(selectedTahunAjaranId);
        }

        const [tingkatResponse, kelasResponse] = await Promise.all([
          tingkatAPI.getAll({ no_pagination: true }),
          kelasAPI.getAll(kelasParams),
        ]);

        const tingkatPayload = tingkatResponse?.data?.data ?? tingkatResponse?.data ?? [];
        const kelasPayload = kelasResponse?.data?.data ?? kelasResponse?.data ?? [];

        const normalizedTingkat = Array.isArray(tingkatPayload)
          ? tingkatPayload.map((item) => ({
              ...item,
              nama: item.nama ?? item.nama_tingkat ?? item.label ?? `Tingkat ${item.id}`,
            }))
          : [];

        const normalizedKelas = Array.isArray(kelasPayload)
          ? kelasPayload.map((item) => ({
              ...item,
              nama: item.nama ?? item.nama_kelas ?? item.namaKelas ?? item.label ?? `Kelas ${item.id}`,
              tingkat_id: item.tingkat_id ?? item.tingkat?.id ?? null,
            }))
          : [];

        setTingkatOptions(normalizedTingkat);
        setKelasOptions(normalizedKelas);
      } catch (error) {
        console.error('Gagal memuat referensi tingkat/kelas:', error);
        setTingkatOptions([]);
        setKelasOptions([]);
      }
    };

    fetchReferenceData();
  }, [selectedTahunAjaranId]);

  const filteredEvents = React.useMemo(() => {
    const key = search.trim().toLowerCase();
    return eventList.filter((item) => {
      const matchType = jenis === 'all' || item.jenis === jenis;
      const haystack = [item.nama, item.jenis_display, item.scope_display, item.lokasi, item.deskripsi].filter(Boolean).join(' ').toLowerCase();
      return matchType && (!key || haystack.includes(key));
    });
  }, [eventList, jenis, search]);

  const filteredPeriods = React.useMemo(() => {
    const key = search.trim().toLowerCase();
    return periodeList.filter((item) => {
      const matchType = jenis === 'all' || item.jenis === jenis;
      const haystack = [item.nama, item.jenis_display, item.semester_display, item.keterangan].filter(Boolean).join(' ').toLowerCase();
      return matchType && (!key || haystack.includes(key));
    });
  }, [periodeList, jenis, search]);

  const eventCount = Math.max(1, Math.ceil(filteredEvents.length / eventPerPage));
  const periodCount = Math.max(1, Math.ceil(filteredPeriods.length / periodPerPage));

  React.useEffect(() => {
    if (eventPage > eventCount) setEventPage(eventCount);
  }, [eventPage, eventCount]);
  React.useEffect(() => {
    if (periodPage > periodCount) setPeriodPage(periodCount);
  }, [periodPage, periodCount]);

  const currentEvents = filteredEvents.slice((eventPage - 1) * eventPerPage, eventPage * eventPerPage);
  const currentPeriods = filteredPeriods.slice((periodPage - 1) * periodPerPage, periodPage * periodPerPage);
  const calendarDays = React.useMemo(
    () => (hasSelectedDate ? getMonthDays(selectedDate) : []),
    [hasSelectedDate, selectedDate]
  );
  const serverTodayKey = React.useMemo(
    () => getServerDateString(serverNowMs, timezone),
    [serverNowMs, timezone]
  );
  const loading = loadingTahun || loadingEvent || loadingPeriod;
  const filterOptions = tab === 'periods' ? PERIOD_TYPES : EVENT_TYPES;
  const monthSummary = React.useMemo(() => {
    if (!hasSelectedDate) {
      return {
        totalEvent: 0,
        libur: 0,
        cuti: 0,
        peringatan: 0,
      };
    }

    const { start, end } = getMonthBoundaries(selectedDate, timezone);

    const inMonthEvents = eventList.filter((event) => {
      const eventStart = String(event.tanggal_mulai || '').slice(0, 10);
      const eventEnd = String(event.tanggal_selesai || event.tanggal_mulai || '').slice(0, 10);
      return eventStart <= end && eventEnd >= start;
    });

    const liburCount = inMonthEvents.filter((event) => event.jenis === 'libur').length;
    const cutiCount = inMonthEvents.filter((event) => {
      const metadata = event?.metadata && typeof event.metadata === 'object' ? event.metadata : {};
      return event.jenis === 'libur' && metadata.calendar_category === 'cuti_bersama';
    }).length;
    const peringatanCount = inMonthEvents.filter((event) => {
      const metadata = event?.metadata && typeof event.metadata === 'object' ? event.metadata : {};
      return event.jenis === 'kegiatan' && metadata.calendar_category === 'peringatan';
    }).length;

    return {
      totalEvent: inMonthEvents.length,
      libur: liburCount,
      cuti: cutiCount,
      peringatan: peringatanCount,
    };
  }, [eventList, hasSelectedDate, selectedDate, timezone]);

  const getAllEventsForDate = (date) => {
    const target = getServerDateString(date.getTime(), timezone);
    if (!target) return [];
    return eventList.filter((event) => {
      const start = String(event.tanggal_mulai || '').slice(0, 10);
      const end = String(event.tanggal_selesai || event.tanggal_mulai || '').slice(0, 10);
      return target >= start && target <= end;
    });
  };

  const getVisibleEventsForDate = (date) => {
    const target = getServerDateString(date.getTime(), timezone);
    if (!target) return [];
    return filteredEvents.filter((event) => {
      const start = String(event.tanggal_mulai || '').slice(0, 10);
      const end = String(event.tanggal_selesai || event.tanggal_mulai || '').slice(0, 10);
      return target >= start && target <= end;
    });
  };

  const getPeriodForDate = (date) => {
    const target = getServerDateString(date.getTime(), timezone);
    if (!target) return null;
    return periodeList.find((period) => {
      const start = String(period.tanggal_mulai || '').slice(0, 10);
      const end = String(period.tanggal_selesai || '').slice(0, 10);
      return target >= start && target <= end;
    });
  };

  const getDateStatus = (date, period = null) => {
    const events = getAllEventsForDate(date);
    const activePeriod = period ?? getPeriodForDate(date);
    const isWeekend = date.getDay() === 0 || date.getDay() === 6;
    const hasHolidayEvent = events.some((event) => event.jenis === 'libur' && event.is_active !== false);
    const hasHolidayPeriod = activePeriod?.jenis === 'libur' && activePeriod?.is_active !== false;
    const isHoliday = isWeekend || hasHolidayEvent || hasHolidayPeriod;
    const statusLabel = isHoliday ? 'Libur' : 'Efektif';
    const color = isHoliday ? 'error' : 'success';

    return { isHoliday, statusLabel, color };
  };

  const shouldShowPeriodChip = (period, status, isCurrentMonthCell) => {
    if (!isCurrentMonthCell || !period) return false;
    // Hindari label yang kontradiktif: hari libur tidak menampilkan chip pembelajaran.
    if (status.isHoliday && period.jenis === 'pembelajaran') return false;
    return true;
  };

  const handleSyncKalenderIndonesia = async () => {
    if (!numericTahunAjaranId) return;

    try {
      setSyncingKalenderIndonesia(true);
      setSyncInfo('');
      setSyncInfoType('info');
      const response = await eventAkademikService.syncKalenderIndonesiaLengkap(numericTahunAjaranId);
      const summary = response?.data?.summary || {};
      const liburResult = response?.data?.libur_nasional || {};
      const apiErrors = Array.isArray(liburResult?.api_errors) ? liburResult.api_errors : [];
      const syncedTotal = summary?.synced_total ?? 0;
      const skippedTotal = summary?.skipped_total ?? 0;
      const syncedLiburNasional = summary?.synced_libur_nasional ?? 0;
      const syncedCutiBersama = summary?.synced_cuti_bersama ?? 0;
      const syncedPeringatan = summary?.synced_peringatan ?? 0;
      const syncedHariBesarLibur = summary?.synced_hari_besar_libur ?? 0;
      const hadApiError = summary?.had_api_error ?? false;
      const allSkippedNoInsert = syncedTotal === 0 && skippedTotal > 0;
      const firstApiError = apiErrors.length > 0 ? apiErrors[0] : null;

      const baseSummary = `Sinkron kalender Indonesia: ${syncedTotal} tersinkron, ${skippedTotal} dilewati (Libur Nasional ${syncedLiburNasional}, Cuti Bersama ${syncedCutiBersama}, Hari Besar Libur ${syncedHariBesarLibur}, Peringatan ${syncedPeringatan}).`;

      if (allSkippedNoInsert) {
        const noNewDataMessage = `${baseSummary} Tidak ada data baru karena event kalender sudah ada.`;
        if (hadApiError) {
          setSyncInfoType('warning');
          setSyncInfo(firstApiError ? `[TIDAK ADA PERUBAHAN + WARNING API] ${noNewDataMessage} API libur bermasalah: ${firstApiError}.` : `[TIDAK ADA PERUBAHAN + WARNING API] ${noNewDataMessage} API libur sedang bermasalah.`);
        } else {
          setSyncInfoType('info');
          setSyncInfo(`[TIDAK ADA PERUBAHAN] ${noNewDataMessage}`);
        }
      } else if (hadApiError) {
        setSyncInfoType('warning');
        setSyncInfo(firstApiError ? `[SUKSES PARSIAL] ${baseSummary} Sebagian data libur/cuti dari API gagal diambil: ${firstApiError}.` : `[SUKSES PARSIAL] ${baseSummary} Sebagian data libur/cuti dari API gagal diambil.`);
      } else if (syncedTotal > 0) {
        setSyncInfoType('success');
        setSyncInfo(`[SUKSES PENUH] ${baseSummary}`);
      } else {
        setSyncInfoType('info');
        setSyncInfo(`[INFO] ${baseSummary}`);
      }

      await refreshEventData?.();
      await refreshUpcoming?.();
      await refreshToday?.();
    } catch (error) {
      console.error('Gagal sinkron kalender Indonesia:', error);
      setSyncInfoType('error');
      setSyncInfo('[GAGAL] Sinkron kalender Indo gagal. Cek log backend untuk detail.');
    } finally {
      setSyncingKalenderIndonesia(false);
    }
  };

  const resetFilters = () => {
    setSearch('');
    setJenis('all');
    setEventPage(1);
    setPeriodPage(1);
  };

  const handleSubmitEvent = async (rawData) => {
    try {
      const payload = { ...rawData, tahun_ajaran_id: numericTahunAjaranId };
      if (payload.periode_akademik_id) payload.periode_akademik_id = Number(payload.periode_akademik_id);
      if (payload.tingkat_id) payload.tingkat_id = Number(payload.tingkat_id);
      if (payload.kelas_id) payload.kelas_id = Number(payload.kelas_id);
      if (selectedEvent?.id) await updateEvent(selectedEvent.id, payload);
      else await createEvent(payload);
      setShowEventModal(false);
      setSelectedEvent(null);
    } catch (error) {
      console.error('Gagal menyimpan event akademik:', error);
    }
  };

  const handleSubmitPeriod = async (payload) => {
    try {
      const submitData = { ...payload, tahun_ajaran_id: numericTahunAjaranId };
      if (selectedPeriod?.id) await updatePeriode(selectedPeriod.id, submitData);
      else await createPeriode(submitData);
      setShowPeriodModal(false);
      setSelectedPeriod(null);
    } catch (error) {
      console.error('Gagal menyimpan periode akademik:', error);
    }
  };

  const handleDelete = async () => {
    try {
      if (confirmDelete.type === 'event') await deleteEvent(confirmDelete.id);
      else await deletePeriode(confirmDelete.id);
    } catch (error) {
      console.error('Gagal menghapus data akademik:', error);
    } finally {
      setConfirmDelete({ open: false, type: 'event', id: null, name: '' });
    }
  };

  return (
    <div className="p-6">
      <Box className="flex items-center gap-3 mb-6">
        <div className="p-2 bg-blue-100 rounded-lg">
          <Calendar className="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <Typography variant="h4" className="font-bold text-gray-900">Kalender Akademik</Typography>
          <Typography variant="body2" className="text-gray-600">Kelola periode akademik dan event sekolah</Typography>
        </div>
      </Box>

      <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <FormControl size="small" className="min-w-[240px]">
            <Select value={selectedTahunAjaranId} onChange={(event) => setSelectedTahunAjaranId(String(event.target.value))} displayEmpty>
              <MenuItem value=""><em>Pilih Tahun Ajaran</em></MenuItem>
              {tahunAjaranList.map((item) => (
                <MenuItem key={item.id} value={String(item.id)}>{item.nama} ({item.status_display || item.status})</MenuItem>
              ))}
            </Select>
          </FormControl>

          <TextField
            value={search}
            onChange={(event) => { setSearch(event.target.value); setEventPage(1); setPeriodPage(1); }}
            size="small"
            placeholder="Cari data akademik..."
            className="flex-1 lg:max-w-md"
            InputProps={{ startAdornment: <InputAdornment position="start"><Search className="w-4 h-4 text-gray-400" /></InputAdornment> }}
          />

          <FormControl size="small" className="min-w-[180px]">
            <Select
              value={jenis}
              onChange={(event) => { setJenis(event.target.value); setEventPage(1); setPeriodPage(1); }}
              startAdornment={<InputAdornment position="start"><Filter className="w-4 h-4 text-gray-400" /></InputAdornment>}
            >
              {filterOptions.map((item) => <MenuItem key={item.value} value={item.value}>{item.label}</MenuItem>)}
            </Select>
          </FormControl>
        </Box>

        <Box className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <Tabs value={tab} onChange={(_, value) => setTab(value)} textColor="primary" indicatorColor="primary" sx={{ minHeight: 36 }}>
            <Tab value="calendar" label="Kalender" />
            <Tab value="events" label={`Event (${filteredEvents.length})`} />
            <Tab value="periods" label={`Periode (${filteredPeriods.length})`} />
          </Tabs>

          <Box className="flex gap-2 flex-wrap">
            <Button variant="outlined" color="inherit" size="small" startIcon={<RotateCcw className="w-4 h-4" />} onClick={resetFilters}>Reset Filter</Button>
            <Button
              variant="outlined"
              size="small"
              disabled={!selectedTahunAjaranId || syncingKalenderIndonesia}
              onClick={handleSyncKalenderIndonesia}
            >
              {syncingKalenderIndonesia ? 'Sinkron Kalender Indonesia...' : 'Sinkron Kalender Indonesia'}
            </Button>
            {tab === 'events' && <Button variant="contained" size="small" startIcon={<Plus className="w-4 h-4" />} onClick={() => { setSelectedEvent(null); setShowEventModal(true); }}>Tambah Event</Button>}
            {tab === 'periods' && <Button variant="contained" size="small" startIcon={<Plus className="w-4 h-4" />} onClick={() => { setSelectedPeriod(null); setShowPeriodModal(true); }}>Tambah Periode</Button>}
          </Box>
        </Box>
        {syncInfo && (
          <Typography
            variant="caption"
            className={`block mt-3 ${
              syncInfoType === 'success'
                ? 'text-emerald-700'
                : syncInfoType === 'warning'
                  ? 'text-amber-700'
                  : syncInfoType === 'error'
                    ? 'text-red-700'
                    : 'text-slate-600'
            }`}
          >
            {syncInfo}
          </Typography>
        )}
      </Paper>

      {!selectedTahunAjaranId ? (
        <Paper className="p-10 border border-gray-100 shadow-sm text-center">
          <Typography variant="body1" color="text.secondary">Pilih tahun ajaran terlebih dahulu.</Typography>
        </Paper>
      ) : null}

      {selectedTahunAjaranId && tab === 'calendar' && !hasSelectedDate && (
        <Paper className="p-10 border border-gray-100 shadow-sm text-center">
          <Typography variant="body1" color="text.secondary">
            Sinkronisasi waktu server sebelum menampilkan kalender.
          </Typography>
        </Paper>
      )}

      {selectedTahunAjaranId && tab === 'calendar' && hasSelectedDate && (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <div className="lg:col-span-3">
            <Paper className="border border-gray-100 shadow-sm">
              <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                  <Typography variant="h6" className="font-semibold">{formatServerDate(selectedDate, 'id-ID', { month: 'long', year: 'numeric' }) || '-'}</Typography>
                  <Typography variant="caption" color="text.secondary">Kalender operasional sekolah</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <IconButton size="small" onClick={() => setSelectedDate(new Date(selectedDate.getFullYear(), selectedDate.getMonth() - 1, 1))}><ChevronLeft className="w-4 h-4" /></IconButton>
                  <Button
                    size="small"
                    variant="outlined"
                    disabled={!currentServerCalendarDate}
                    onClick={() => {
                      if (currentServerCalendarDate) {
                        setSelectedDate(currentServerCalendarDate);
                      }
                    }}
                  >
                    Hari Ini
                  </Button>
                  <IconButton size="small" onClick={() => setSelectedDate(new Date(selectedDate.getFullYear(), selectedDate.getMonth() + 1, 1))}><ChevronRight className="w-4 h-4" /></IconButton>
                </div>
              </div>

              <div className="p-4">
                <div className="flex flex-wrap items-center gap-2 mb-4">
                  <Chip label={`Total event: ${monthSummary.totalEvent}`} size="small" variant="outlined" />
                  <Chip label={`Libur: ${monthSummary.libur}`} size="small" color="error" variant="outlined" />
                  <Chip label={`Cuti bersama: ${monthSummary.cuti}`} size="small" color="warning" variant="outlined" />
                  <Chip label={`Peringatan: ${monthSummary.peringatan}`} size="small" color="info" variant="outlined" />
                </div>

                <div className="grid grid-cols-7 gap-2 mb-2">
                  {['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'].map((d, index) => (
                    <div key={d} className={`p-2 text-center text-sm font-semibold ${index === 0 || index === 6 ? 'text-red-500' : 'text-gray-500'}`}>{d}</div>
                  ))}
                </div>
                <div className="grid grid-cols-7 gap-2">
                  {calendarDays.map((item) => {
                    const events = getVisibleEventsForDate(item.date);
                    const period = getPeriodForDate(item.date);
                    const status = getDateStatus(item.date, period);
                    const visibleEvents = item.current ? events : [];
                    const displayPeriodChip = shouldShowPeriodChip(period, status, item.current);
                    const itemDateKey = getServerDateString(item.date.getTime(), timezone);
                    const isToday = Boolean(itemDateKey && serverTodayKey && itemDateKey === serverTodayKey);
                    const isWeekend = item.date.getDay() === 0 || item.date.getDay() === 6;
                    const dayTone = item.current
                      ? (status.isHoliday ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200')
                      : 'bg-slate-50 border-slate-200 opacity-80';
                    return (
                      <div
                        key={item.date.toISOString()}
                        onClick={() => setSelectedDate(item.date)}
                        className={`min-h-[130px] p-2 border rounded-xl ${item.current ? 'cursor-pointer' : 'cursor-default'} ${dayTone} ${isToday ? 'ring-2 ring-blue-400' : item.current ? 'hover:shadow-sm' : ''}`}
                      >
                        <div className="flex items-start justify-between mb-2">
                          <div className={`text-sm font-semibold ${item.current ? (isWeekend ? 'text-red-600' : 'text-slate-900') : 'text-slate-400'}`}>
                            {item.date.getDate()}
                          </div>
                          {isToday && item.current ? (
                            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">Hari ini</span>
                          ) : null}
                        </div>

                        {item.current ? (
                          <div className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium ${status.isHoliday ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${status.isHoliday ? 'bg-red-500' : 'bg-emerald-500'}`} />
                            {status.statusLabel}
                          </div>
                        ) : null}

                        {displayPeriodChip ? (
                          <div className="mt-2 text-[10px] text-slate-600 truncate">
                            Periode: {period.jenis_display || period.jenis}
                          </div>
                        ) : null}

                        {visibleEvents.length > 0 ? (
                          <div className="mt-2 space-y-1">
                            {visibleEvents.slice(0, 2).map((event) => (
                              <div
                                key={event.id}
                                title={event.nama}
                                className={`text-[10px] leading-tight px-1.5 py-1 rounded-md border truncate ${resolveEventTone(event)}`}
                              >
                                {event.nama}
                              </div>
                            ))}
                            {visibleEvents.length > 2 && (
                              <div className="text-[10px] text-slate-500 px-1">
                                +{visibleEvents.length - 2} event lainnya
                              </div>
                            )}
                          </div>
                        ) : null}
                      </div>
                    );
                  })}
                </div>
              </div>
            </Paper>
          </div>

          <div className="space-y-4">
            <Paper className="p-4 border border-gray-100 shadow-sm">
              <Typography variant="subtitle1" className="font-semibold mb-2">Legenda Hari</Typography>
              <Box className="space-y-2">
                <div className="flex items-center gap-2">
                  <span className="w-3 h-3 rounded-full bg-red-500" />
                  <Typography variant="caption">Libur (nasional/hari besar/cuti bersama)</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <span className="w-3 h-3 rounded-full bg-emerald-500" />
                  <Typography variant="caption">Hari efektif sekolah</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <span className="w-3 h-3 rounded-full bg-amber-500" />
                  <Typography variant="caption">Cuti bersama</Typography>
                </div>
                <div className="flex items-center gap-2">
                  <span className="w-3 h-3 rounded-full bg-blue-500" />
                  <Typography variant="caption">Peringatan hari besar (non-libur)</Typography>
                </div>
              </Box>
            </Paper>
            {currentPeriode && (
              <Paper className="p-4 border border-gray-100 shadow-sm">
                <Typography variant="subtitle1" className="font-semibold mb-2">Periode Saat Ini</Typography>
                <Typography variant="body2" className="font-medium">{currentPeriode.nama}</Typography>
                <Chip label={currentPeriode.jenis_display || currentPeriode.jenis} size="small" color={PERIOD_COLORS[currentPeriode.jenis] || 'default'} variant="outlined" sx={{ mt: 1 }} />
                <Typography variant="caption" color="text.secondary" className="block mt-2">{formatDate(currentPeriode.tanggal_mulai)} - {formatDate(currentPeriode.tanggal_selesai)}</Typography>
              </Paper>
            )}

            <Paper className="p-4 border border-gray-100 shadow-sm">
              <Typography variant="subtitle1" className="font-semibold mb-2">Event Hari Ini</Typography>
              {(todayEvents || []).length === 0 ? <Typography variant="body2" color="text.secondary">Tidak ada event hari ini</Typography> : todayEvents.slice(0, 4).map((event) => <Typography key={event.id} variant="body2" className="mb-1">{event.nama}</Typography>)}
            </Paper>

            <Paper className="p-4 border border-gray-100 shadow-sm">
              <Typography variant="subtitle1" className="font-semibold mb-2">Event Mendatang</Typography>
              {(upcomingEvents || []).length === 0 ? <Typography variant="body2" color="text.secondary">Tidak ada event mendatang</Typography> : upcomingEvents.slice(0, 4).map((event) => <Typography key={event.id} variant="body2" className="mb-1">{event.nama}</Typography>)}
            </Paper>
          </div>
        </div>
      )}

      {selectedTahunAjaranId && tab === 'events' && (
        <>
          <TableContainer component={Paper} className="shadow-sm border border-gray-100">
            <Table>
              <TableHead><TableRow className="bg-gray-50"><TableCell>Nama Event</TableCell><TableCell>Jenis</TableCell><TableCell>Tanggal</TableCell><TableCell>Status</TableCell><TableCell align="center">Aksi</TableCell></TableRow></TableHead>
              <TableBody>
                {loading ? [...Array(5)].map((_, idx) => <TableRow key={`loading-event-${idx}`}><TableCell colSpan={5}><div className="h-8 w-full animate-pulse rounded bg-gray-100" /></TableCell></TableRow>) : null}
                {!loading && currentEvents.length === 0 ? <TableRow><TableCell colSpan={5} align="center" className="py-8"><Typography variant="body2" color="text.secondary">Tidak ada event akademik</Typography></TableCell></TableRow> : null}
                {!loading && currentEvents.map((event) => (
                  <TableRow key={event.id} hover>
                    <TableCell><Typography variant="body2" className="font-semibold">{event.nama}</Typography><Typography variant="caption" color="text.secondary">{event.scope_display || 'Semua'}</Typography></TableCell>
                    <TableCell><Chip label={event.jenis_display || event.jenis} size="small" color={EVENT_COLORS[event.jenis] || 'default'} variant="outlined" /></TableCell>
                    <TableCell>{event.tanggal_display || `${formatDate(event.tanggal_mulai)} - ${formatDate(event.tanggal_selesai)}`}</TableCell>
                    <TableCell><Chip label={event.is_active ? 'Aktif' : 'Nonaktif'} size="small" color={event.is_active ? 'success' : 'default'} variant="outlined" /></TableCell>
                    <TableCell align="center">
                      <IconButton size="small" color="primary" onClick={() => { setSelectedEvent({ ...event, tanggal_mulai: toDateInput(event.tanggal_mulai), tanggal_selesai: toDateInput(event.tanggal_selesai), waktu_mulai: toTimeInput(event.waktu_mulai), waktu_selesai: toTimeInput(event.waktu_selesai), periode_akademik_id: event.periode_akademik_id ? String(event.periode_akademik_id) : '', tingkat_id: event.tingkat_id ? String(event.tingkat_id) : '', kelas_id: event.kelas_id ? String(event.kelas_id) : '' }); setShowEventModal(true); }}><Edit className="w-4 h-4" /></IconButton>
                      <IconButton size="small" color="error" onClick={() => setConfirmDelete({ open: true, type: 'event', id: event.id, name: event.nama })}><Trash2 className="w-4 h-4" /></IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          {filteredEvents.length > 0 && (
            <Paper className="p-4 mt-4 shadow-sm border border-gray-100">
              <Box className="flex flex-col sm:flex-row justify-between items-center gap-4">
                <Typography variant="body2" color="text.secondary">
                  Menampilkan {(eventPage - 1) * eventPerPage + 1} - {Math.min(eventPage * eventPerPage, filteredEvents.length)} dari {filteredEvents.length} data
                </Typography>
                <Box className="flex items-center gap-4">
                  <Box className="flex items-center gap-2">
                    <Typography variant="body2" color="text.secondary">Per halaman:</Typography>
                    <FormControl size="small">
                      <Select
                        value={eventPerPage}
                        onChange={(event) => {
                          setEventPerPage(Number(event.target.value));
                          setEventPage(1);
                        }}
                        className="min-w-[80px]"
                      >
                        <MenuItem value={10}>10</MenuItem>
                        <MenuItem value={15}>15</MenuItem>
                        <MenuItem value={25}>25</MenuItem>
                        <MenuItem value={50}>50</MenuItem>
                      </Select>
                    </FormControl>
                  </Box>
                  <Pagination
                    count={eventCount}
                    page={eventPage}
                    onChange={(_, nextPage) => setEventPage(nextPage)}
                    color="primary"
                    shape="rounded"
                    showFirstButton
                    showLastButton
                    size="small"
                  />
                </Box>
              </Box>
            </Paper>
          )}
        </>
      )}

      {selectedTahunAjaranId && tab === 'periods' && (
        <>
          <TableContainer component={Paper} className="shadow-sm border border-gray-100">
            <Table>
              <TableHead><TableRow className="bg-gray-50"><TableCell>Nama Periode</TableCell><TableCell>Jenis</TableCell><TableCell>Tanggal</TableCell><TableCell>Status</TableCell><TableCell align="center">Aksi</TableCell></TableRow></TableHead>
              <TableBody>
                {loading ? [...Array(5)].map((_, idx) => <TableRow key={`loading-period-${idx}`}><TableCell colSpan={5}><div className="h-8 w-full animate-pulse rounded bg-gray-100" /></TableCell></TableRow>) : null}
                {!loading && currentPeriods.length === 0 ? <TableRow><TableCell colSpan={5} align="center" className="py-8"><Typography variant="body2" color="text.secondary">Tidak ada periode akademik</Typography></TableCell></TableRow> : null}
                {!loading && currentPeriods.map((period) => (
                  <TableRow key={period.id} hover>
                    <TableCell><Typography variant="body2" className="font-semibold">{period.nama}</Typography><Typography variant="caption" color="text.secondary">{period.semester_display || period.semester || '-'}</Typography></TableCell>
                    <TableCell><Chip label={period.jenis_display || period.jenis} size="small" color={PERIOD_COLORS[period.jenis] || 'default'} variant="outlined" /></TableCell>
                    <TableCell>{formatDate(period.tanggal_mulai)} - {formatDate(period.tanggal_selesai)}</TableCell>
                    <TableCell><Chip label={period.status_display || (period.is_active ? 'Aktif' : 'Nonaktif')} size="small" color={period.is_active ? 'success' : 'default'} variant="outlined" /></TableCell>
                    <TableCell align="center">
                      <IconButton size="small" color="primary" onClick={() => { setSelectedPeriod({ ...period, tanggal_mulai: toDateInput(period.tanggal_mulai), tanggal_selesai: toDateInput(period.tanggal_selesai) }); setShowPeriodModal(true); }}><Edit className="w-4 h-4" /></IconButton>
                      <IconButton size="small" color="error" onClick={() => setConfirmDelete({ open: true, type: 'period', id: period.id, name: period.nama })}><Trash2 className="w-4 h-4" /></IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          {filteredPeriods.length > 0 && (
            <Paper className="p-4 mt-4 shadow-sm border border-gray-100">
              <Box className="flex flex-col sm:flex-row justify-between items-center gap-4">
                <Typography variant="body2" color="text.secondary">
                  Menampilkan {(periodPage - 1) * periodPerPage + 1} - {Math.min(periodPage * periodPerPage, filteredPeriods.length)} dari {filteredPeriods.length} data
                </Typography>
                <Box className="flex items-center gap-4">
                  <Box className="flex items-center gap-2">
                    <Typography variant="body2" color="text.secondary">Per halaman:</Typography>
                    <FormControl size="small">
                      <Select
                        value={periodPerPage}
                        onChange={(event) => {
                          setPeriodPerPage(Number(event.target.value));
                          setPeriodPage(1);
                        }}
                        className="min-w-[80px]"
                      >
                        <MenuItem value={10}>10</MenuItem>
                        <MenuItem value={15}>15</MenuItem>
                        <MenuItem value={25}>25</MenuItem>
                        <MenuItem value={50}>50</MenuItem>
                      </Select>
                    </FormControl>
                  </Box>
                  <Pagination
                    count={periodCount}
                    page={periodPage}
                    onChange={(_, nextPage) => setPeriodPage(nextPage)}
                    color="primary"
                    shape="rounded"
                    showFirstButton
                    showLastButton
                    size="small"
                  />
                </Box>
              </Box>
            </Paper>
          )}
        </>
      )}

      <PeriodeAkademikFormModal isOpen={showPeriodModal} onClose={() => { setShowPeriodModal(false); setSelectedPeriod(null); }} onSubmit={handleSubmitPeriod} initialData={selectedPeriod} />
      <EventAkademikFormModal
        isOpen={showEventModal}
        onClose={() => {
          setShowEventModal(false);
          setSelectedEvent(null);
        }}
        onSubmit={handleSubmitEvent}
        initialData={selectedEvent}
        periodeList={periodeList}
        tingkatList={tingkatOptions}
        kelasList={kelasOptions}
      />
      <ConfirmationModal open={confirmDelete.open} onClose={() => setConfirmDelete({ open: false, type: 'event', id: null, name: '' })} title={confirmDelete.type === 'event' ? 'Hapus Event Akademik' : 'Hapus Periode Akademik'} message={`Apakah Anda yakin ingin menghapus \"${confirmDelete.name}\"?`} onConfirm={handleDelete} confirmText="Hapus" type="delete" />
    </div>
  );
};

export default KalenderAkademik;

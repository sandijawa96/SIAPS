import React from 'react';
import {
  Autocomplete,
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  FormControl,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Switch,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography,
} from '@mui/material';
import {
  BookOpen,
  Calendar,
  CalendarClock,
  Clock3,
  Download,
  DoorOpen,
  Edit,
  GraduationCap,
  Hash,
  Plus,
  RotateCcw,
  Save,
  Search,
  Send,
  Settings,
  Trash2,
  Upload,
  User,
  X,
} from 'lucide-react';
import { getServerDateString, getServerNowEpochMs } from '../services/serverClock';
import { jadwalPelajaranAPI } from '../services/jadwalPelajaranService';
import { useAuth } from '../hooks/useAuth';
import ConfirmationModal from '../components/kelas/modals/ConfirmationModal';
import ExportModalAkademik from '../components/ExportModalAkademik';
import ImportModalAkademik from '../components/ImportModalAkademik';

const emptyForm = {
  guru_id: '',
  mata_pelajaran_id: '',
  kelas_id: '',
  tahun_ajaran_id: '',
  semester: 'full',
  hari: 'senin',
  jam_mulai: '',
  jam_selesai: '',
  jam_ke: '',
  jp_count: '1',
  ruangan: '',
  status: 'draft',
  is_active: true,
};

const JADWAL_EXPORT_FIELDS = [
  { id: 'no', label: 'No', default: true },
  { id: 'hari', label: 'Hari', default: true },
  { id: 'jam_ke', label: 'JP Ke', default: true },
  { id: 'jam_mulai', label: 'Jam Mulai', default: true },
  { id: 'jam_selesai', label: 'Jam Selesai', default: true },
  { id: 'kelas', label: 'Kelas', default: true },
  { id: 'mapel_kode', label: 'Kode Mapel', default: true },
  { id: 'mapel_nama', label: 'Mata Pelajaran', default: true },
  { id: 'guru_nama', label: 'Guru', default: true },
  { id: 'guru_nip', label: 'NIP Guru', default: false },
  { id: 'ruangan', label: 'Ruangan', default: true },
  { id: 'tahun_ajaran', label: 'Tahun Ajaran', default: true },
  { id: 'semester', label: 'Semester', default: true },
  { id: 'status', label: 'Status', default: true },
  { id: 'is_active', label: 'Aktif', default: false },
  { id: 'updated_at', label: 'Terakhir Diubah', default: false },
];

const extractFilename = (response, fallbackName) => {
  const disposition = response?.headers?.['content-disposition'] || response?.headers?.['Content-Disposition'];
  if (!disposition) {
    return fallbackName;
  }

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match && utf8Match[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const asciiMatch = disposition.match(/filename=\"?([^\";]+)\"?/i);
  if (asciiMatch && asciiMatch[1]) {
    return asciiMatch[1];
  }

  return fallbackName;
};

const downloadBlobResponse = (response, fallbackName) => {
  const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data]);
  const filename = extractFilename(response, fallbackName);
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

const toArray = (payload) => (Array.isArray(payload) ? payload : []);
const DAY_ORDER = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
const SEMESTER_OPTIONS = [
  { value: 'ganjil', label: 'Ganjil' },
  { value: 'genap', label: 'Genap' },
  { value: 'full', label: 'Full' },
];
const normalizeToken = (value) => String(value ?? '').trim().toLowerCase();

const parseTimeToMinutes = (value) => {
  const text = String(value ?? '').trim();
  if (!text.includes(':')) {
    return null;
  }

  const [h, m] = text.split(':');
  const hour = Number(h);
  const minute = Number(m);
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
    return null;
  }

  return (hour * 60) + minute;
};

const formatTimeHM = (value) => {
  const text = String(value ?? '').trim();
  if (!text.includes(':')) {
    return text || '--:--';
  }

  const [h, m] = text.split(':');
  const hour = Number(h);
  const minute = Number(m);
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
    return text.length >= 5 ? text.slice(0, 5) : text;
  }

  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
};

const hasTimeOverlap = (startA, endA, startB, endB) => {
  const sA = parseTimeToMinutes(startA);
  const eA = parseTimeToMinutes(endA);
  const sB = parseTimeToMinutes(startB);
  const eB = parseTimeToMinutes(endB);

  if (sA === null || eA === null || sB === null || eB === null) {
    return false;
  }

  return sA < eB && eA > sB;
};

const isScheduleRowActive = (row) => {
  const status = normalizeToken(row?.status || 'published');
  const activeFlag = row?.is_active ?? true;
  return status !== 'archived' && Boolean(activeFlag);
};

const isSemesterConflict = (existingSemester, targetSemester) => {
  const existing = normalizeToken(existingSemester);
  const target = normalizeToken(targetSemester || 'full');

  if (target === 'full') {
    return true;
  }

  if (existing === '' || existing === 'full') {
    return true;
  }

  return existing === target;
};

const isSameScheduleGroup = (currentRow, nextRow) => {
  if (!currentRow || !nextRow) {
    return false;
  }

  return (
    normalizeToken(currentRow?.hari) === normalizeToken(nextRow?.hari)
    && String(currentRow?.kelas_id || '') === String(nextRow?.kelas_id || '')
    && String(currentRow?.tahun_ajaran_id || '') === String(nextRow?.tahun_ajaran_id || '')
    && normalizeToken(currentRow?.semester || 'full') === normalizeToken(nextRow?.semester || 'full')
  );
};

const getBreakLabel = (minutes) => {
  if (minutes >= 30) {
    return 'Istirahat Panjang';
  }
  if (minutes >= 10) {
    return 'Istirahat';
  }
  return 'Jeda';
};

const fieldClassName =
  'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';
const settingsFieldClassName =
  'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';

const labelClassName = 'block text-sm font-semibold text-gray-700 mb-2';

const getDefaultYearId = (tahunAjaranRows, preferredId = null) => {
  if (preferredId) {
    return String(preferredId);
  }

  const rows = toArray(tahunAjaranRows);
  const active = rows.find((item) => item?.is_active || item?.status === 'active');
  if (active?.id) {
    return String(active.id);
  }

  return rows[0]?.id ? String(rows[0].id) : '';
};

const normalizeBreak = (item) => ({
  id: item?.id || null,
  after_jp: item?.after_jp != null ? String(item.after_jp) : '',
  break_minutes: item?.break_minutes != null ? String(item.break_minutes) : '',
  label: item?.label || '',
});

const normalizeDay = (item, dayCode, dayLabel) => ({
  id: item?.id || null,
  hari: item?.hari || dayCode,
  hari_label: item?.hari_label || dayLabel,
  is_school_day: Boolean(item?.is_school_day),
  jp_count: item?.jp_count != null ? String(item.jp_count) : '0',
  jp_minutes: item?.jp_minutes != null ? String(item.jp_minutes) : '',
  start_time: item?.start_time || '',
  notes: item?.notes || '',
  breaks: toArray(item?.breaks).map(normalizeBreak),
});

const normalizeSetting = (raw, defaultYearId, defaultSemester = 'full') => {
  const dayMap = {};
  toArray(raw?.days).forEach((item) => {
    if (item?.hari) {
      dayMap[item.hari] = item;
    }
  });

  const dayLabels = [
    ['senin', 'Senin'],
    ['selasa', 'Selasa'],
    ['rabu', 'Rabu'],
    ['kamis', 'Kamis'],
    ['jumat', 'Jumat'],
    ['sabtu', 'Sabtu'],
    ['minggu', 'Minggu'],
  ];

  return {
    id: raw?.id || null,
    tahun_ajaran_id: raw?.tahun_ajaran_id ? String(raw.tahun_ajaran_id) : String(defaultYearId || ''),
    semester: raw?.semester || defaultSemester,
    default_jp_minutes: raw?.default_jp_minutes != null ? String(raw.default_jp_minutes) : '45',
    default_start_time: raw?.default_start_time || '07:00',
    is_active: raw?.is_active !== false,
    notes: raw?.notes || '',
    days: dayLabels.map(([dayCode, dayLabel]) => normalizeDay(dayMap[dayCode], dayCode, dayLabel)),
    slot_templates: raw?.slot_templates || {},
  };
};

const buildSettingsPayload = (form) => ({
  tahun_ajaran_id: Number(form.tahun_ajaran_id),
  semester: form.semester,
  default_jp_minutes: Number(form.default_jp_minutes),
  default_start_time: form.default_start_time,
  is_active: Boolean(form.is_active),
  notes: form.notes?.trim() ? form.notes.trim() : null,
  days: toArray(form.days).map((day) => ({
    hari: day.hari,
    is_school_day: Boolean(day.is_school_day),
    jp_count: day.is_school_day ? Number(day.jp_count || 0) : 0,
    jp_minutes: day.jp_minutes ? Number(day.jp_minutes) : null,
    start_time: day.start_time || null,
    notes: day.notes?.trim() ? day.notes.trim() : null,
    breaks: day.is_school_day
      ? toArray(day.breaks)
        .filter((breakRow) => breakRow.after_jp && breakRow.break_minutes)
        .map((breakRow) => ({
          after_jp: Number(breakRow.after_jp),
          break_minutes: Number(breakRow.break_minutes),
          label: breakRow.label?.trim() ? breakRow.label.trim() : null,
        }))
      : [],
  })),
});

const ModalSelectField = ({
  label,
  icon,
  value,
  onChange,
  options,
  placeholder,
  getValue,
  getLabel,
  disabled = false,
}) => (
  <div>
    <label className={labelClassName}>{label}</label>
    <div className="relative">
      <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">{icon}</div>
      <select className={fieldClassName} value={value} onChange={onChange} disabled={disabled}>
        <option value="">{placeholder}</option>
        {options.map((item) => (
          <option key={getValue(item)} value={getValue(item)}>
            {getLabel(item)}
          </option>
        ))}
      </select>
    </div>
  </div>
);

const modalAutocompleteSx = {
  '& .MuiOutlinedInput-root': {
    minHeight: 48,
    borderRadius: '0.75rem',
    backgroundColor: '#ffffff',
    '& fieldset': {
      borderColor: '#D1D5DB',
    },
    '&:hover fieldset': {
      borderColor: '#60A5FA',
    },
    '&.Mui-focused fieldset': {
      borderColor: '#3B82F6',
      borderWidth: '2px',
    },
  },
  '& .MuiInputBase-input': {
    paddingTop: '11px',
    paddingBottom: '11px',
  },
};

const ModalSearchSelectField = ({
  label,
  icon,
  value,
  onChange,
  options,
  placeholder,
  getValue,
  getLabel,
  disabled = false,
}) => {
  const safeOptions = toArray(options);
  const selectedOption = safeOptions.find((item) => String(getValue(item)) === String(value)) || null;

  return (
    <div>
      <label className={labelClassName}>{label}</label>
      <Autocomplete
        options={safeOptions}
        value={selectedOption}
        onChange={(_event, nextValue) => onChange(nextValue ? String(getValue(nextValue)) : '')}
        getOptionLabel={(option) => String(getLabel(option) || '')}
        isOptionEqualToValue={(option, selected) => String(getValue(option)) === String(getValue(selected))}
        disabled={disabled}
        noOptionsText="Data guru tidak ditemukan"
        fullWidth
        renderOption={(props, option) => (
          <li {...props} key={String(getValue(option))}>
            {getLabel(option)}
          </li>
        )}
        renderInput={(params) => (
          <TextField
            {...params}
            placeholder={placeholder}
            sx={modalAutocompleteSx}
            InputProps={{
              ...params.InputProps,
              startAdornment: (
                <>
                  <InputAdornment position="start" sx={{ color: '#9CA3AF', ml: 0.5 }}>
                    {icon}
                  </InputAdornment>
                  {params.InputProps.startAdornment}
                </>
              ),
            }}
          />
        )}
      />
    </div>
  );
};

const ModalTextField = ({
  label,
  icon,
  type = 'text',
  value,
  onChange,
  placeholder,
  min = undefined,
  max = undefined,
  step = undefined,
  readOnly = false,
  disabled = false,
}) => (
  <div>
    <label className={labelClassName}>{label}</label>
    <div className="relative">
      <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">{icon}</div>
      <input
        className={fieldClassName}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        min={min}
        max={max}
        step={step}
        readOnly={readOnly}
        disabled={disabled}
      />
    </div>
  </div>
);

const JadwalFormModal = ({
  open,
  saving,
  mode,
  form,
  options,
  filteredOptions,
  mappingMeta,
  daySlots,
  slotLoading,
  onClose,
  onChange,
  onJamKeChange,
  onSubmit,
}) => {
  if (!open) {
    return null;
  }

  const isCreate = mode === 'create';
  const hasSlots = toArray(daySlots).length > 0;
  const startJp = Number(form.jam_ke || 0);
  const blockCountRaw = Number(form.jp_count || 1);
  const blockCount = Number.isFinite(blockCountRaw) ? Math.max(1, Math.min(12, Math.trunc(blockCountRaw))) : 1;
  const blockEndJp = startJp > 0 ? startJp + blockCount - 1 : 0;
  const tahunAjaranOptions = toArray(filteredOptions?.tahun_ajaran || options.tahun_ajaran);
  const semesterOptions = toArray(filteredOptions?.semester);
  const kelasOptions = toArray(filteredOptions?.kelas);
  const hariOptions = toArray(filteredOptions?.hari);
  const mapelOptions = toArray(filteredOptions?.mapel);
  const guruOptions = toArray(filteredOptions?.guru);
  const classDisabled = !form.tahun_ajaran_id;
  const mapelDisabled = !form.tahun_ajaran_id || !form.kelas_id;
  const guruDisabled = mapelDisabled || !form.mata_pelajaran_id;
  const hasConsecutiveSlots = !isCreate || !startJp || !hasSlots
    ? true
    : Array.from({ length: blockCount }).every((_, index) => (
      toArray(daySlots).some((slot) => Number(slot.jp_ke) === (startJp + index))
    ));
  const canSubmit = !saving
    && !slotLoading
    && hasSlots
    && Boolean(form.tahun_ajaran_id)
    && Boolean(form.kelas_id)
    && Boolean(form.mata_pelajaran_id)
    && Boolean(form.guru_id)
    && Boolean(form.jam_ke)
    && hasConsecutiveSlots;
  const submitDisabledReason = slotLoading
    ? 'Slot JP sedang dimuat...'
    : !form.tahun_ajaran_id
      ? 'Pilih Tahun Ajaran terlebih dahulu.'
      : !form.kelas_id
        ? 'Pilih Kelas terlebih dahulu.'
    : !hasSlots
      ? 'Tidak ada slot JP aktif untuk hari ini. Cek tab Pengaturan Jadwal.'
      : !form.mata_pelajaran_id
        ? 'Pilih Mata Pelajaran terlebih dahulu.'
        : !form.guru_id
          ? 'Pilih Guru yang tersedia untuk mapel ini.'
      : !form.jam_ke
        ? 'Pilih Jam Ke (JP) terlebih dahulu.'
        : !hasConsecutiveSlots
          ? `Blok JP ${startJp}-${blockEndJp} tidak tersedia pada hari ini.`
        : '';

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4 py-8">
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => !saving && onClose()} />

        <div className="relative inline-block w-full max-w-3xl overflow-hidden bg-white shadow-2xl rounded-2xl">
          <div className="px-6 py-5 bg-gradient-to-r from-blue-600 to-indigo-700">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-white/20">
                  <CalendarClock className="w-5 h-5 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">
                    {isCreate ? 'Tambah Jadwal Pelajaran' : 'Edit Jadwal Pelajaran'}
                  </h3>
                  <p className="text-sm text-blue-100">
                    Pilih JP dari pengaturan jadwal. Jam mulai dan jam selesai akan terisi otomatis.
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => !saving && onClose()}
                className="p-2 transition-colors rounded-lg hover:bg-white/20"
                disabled={saving}
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          <div className="px-6 py-6 max-h-[72vh] overflow-y-auto">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <ModalSelectField
                label="Tahun Ajaran"
                icon={<Calendar className="w-5 h-5" />}
                value={form.tahun_ajaran_id}
                onChange={(e) => onChange('tahun_ajaran_id', e.target.value)}
                options={tahunAjaranOptions}
                placeholder="Pilih Tahun Ajaran"
                getValue={(item) => String(item.id)}
                getLabel={(item) => item.nama}
              />

              <ModalSelectField
                label="Semester"
                icon={<Calendar className="w-5 h-5" />}
                value={form.semester}
                onChange={(e) => onChange('semester', e.target.value)}
                options={semesterOptions}
                placeholder="Pilih Semester"
                getValue={(item) => item.value}
                getLabel={(item) => item.label}
              />

              <ModalSelectField
                label="Kelas"
                icon={<GraduationCap className="w-5 h-5" />}
                value={form.kelas_id}
                onChange={(e) => onChange('kelas_id', e.target.value)}
                options={kelasOptions}
                placeholder={classDisabled ? 'Pilih Tahun Ajaran dulu' : 'Pilih Kelas'}
                getValue={(item) => String(item.id)}
                getLabel={(item) => item.nama_kelas}
                disabled={classDisabled}
              />

              <ModalSelectField
                label="Hari"
                icon={<CalendarClock className="w-5 h-5" />}
                value={form.hari}
                onChange={(e) => onChange('hari', e.target.value)}
                options={hariOptions}
                placeholder="Pilih Hari"
                getValue={(item) => item.value}
                getLabel={(item) => item.label}
                disabled={classDisabled}
              />

              <ModalSelectField
                label="Jam Ke (JP)"
                icon={<Clock3 className="w-5 h-5" />}
                value={form.jam_ke}
                onChange={(e) => onJamKeChange(e.target.value)}
                options={toArray(daySlots)}
                placeholder={slotLoading ? 'Memuat slot...' : 'Pilih slot JP'}
                getValue={(item) => String(item.jp_ke)}
                getLabel={(item) => item.label}
                disabled={slotLoading || classDisabled || daySlots.length === 0}
              />

              {isCreate ? (
                <ModalTextField
                  label="Jumlah JP Berurutan"
                  icon={<Hash className="w-5 h-5" />}
                  type="number"
                  value={form.jp_count || '1'}
                  onChange={(e) => onChange('jp_count', e.target.value)}
                  min={1}
                  max={12}
                  step={1}
                  placeholder="Contoh: 3"
                />
              ) : null}

              <div className="text-xs text-gray-500 md:col-span-2 -mt-2">
                {slotLoading
                  ? 'Memuat slot JP...'
                  : daySlots.length > 0
                    ? `Tersedia ${daySlots.length} slot JP untuk hari ini.`
                    : 'Tidak ada slot untuk hari ini. Cek pengaturan hari sekolah pada tab Pengaturan Jadwal.'}
              </div>

              {isCreate && form.jam_ke && (
                <div className={`text-xs md:col-span-2 ${hasConsecutiveSlots ? 'text-emerald-600' : 'text-amber-700'} -mt-2`}>
                  {hasConsecutiveSlots
                    ? `Akan dibuat rentang JP ${startJp}-${blockEndJp} untuk mapel ini.`
                    : `Rentang JP ${startJp}-${blockEndJp} tidak tersedia pada slot hari ini.`}
                </div>
              )}

              <ModalSelectField
                label="Mata Pelajaran"
                icon={<BookOpen className="w-5 h-5" />}
                value={form.mata_pelajaran_id}
                onChange={(e) => onChange('mata_pelajaran_id', e.target.value)}
                options={mapelOptions}
                placeholder={mapelDisabled ? 'Pilih Tahun Ajaran dan Kelas dulu' : 'Pilih Mata Pelajaran'}
                getValue={(item) => String(item.id)}
                getLabel={(item) => `${item.kode_mapel} - ${item.nama_mapel}`}
                disabled={mapelDisabled}
              />

              <ModalSearchSelectField
                label="Guru"
                icon={<User className="w-5 h-5" />}
                value={form.guru_id}
                onChange={(nextValue) => onChange('guru_id', nextValue)}
                options={guruOptions}
                placeholder={guruDisabled ? 'Pilih Mapel dulu' : 'Cari Guru (nama/NIP/email)'}
                getValue={(item) => String(item.id)}
                getLabel={(item) => `${item.nama_lengkap}${item.nip ? ` (${item.nip})` : ''}`}
                disabled={guruDisabled}
              />

              <div className="md:col-span-2">
                <Alert severity="info" sx={{ borderRadius: '0.75rem' }}>
                  {mapelDisabled
                    ? 'Urutan pengisian: Tahun Ajaran -> Kelas -> Mata Pelajaran -> Guru.'
                    : !form.mata_pelajaran_id
                      ? `Mapel tersedia untuk kelas ini: ${mappingMeta?.mapelCount || 0}. Pilih mapel untuk menampilkan guru.`
                      : `Guru tersedia: ${mappingMeta?.availableGuruCount || 0}${mappingMeta?.conflictGuruCount ? ` (bentrok: ${mappingMeta.conflictGuruCount})` : ''}.`}
                </Alert>
              </div>

              <ModalTextField
                label="Jam Mulai"
                icon={<Clock3 className="w-5 h-5" />}
                type="time"
                value={form.jam_mulai}
                onChange={() => {}}
                readOnly
              />

              <ModalTextField
                label="Jam Selesai"
                icon={<Clock3 className="w-5 h-5" />}
                type="time"
                value={form.jam_selesai}
                onChange={() => {}}
                readOnly
              />

              <ModalTextField
                label="Ruangan"
                icon={<DoorOpen className="w-5 h-5" />}
                value={form.ruangan}
                onChange={(e) => onChange('ruangan', e.target.value)}
                placeholder="Contoh: Lab 1"
              />

              <ModalSelectField
                label="Status"
                icon={<Edit className="w-5 h-5" />}
                value={form.status}
                onChange={(e) => onChange('status', e.target.value)}
                options={[
                  { value: 'draft', label: 'Draft' },
                  { value: 'published', label: 'Published' },
                  { value: 'archived', label: 'Archived' },
                ]}
                placeholder="Pilih Status"
                getValue={(item) => item.value}
                getLabel={(item) => item.label}
              />
            </div>
          </div>

          <div className="px-6 py-4 bg-gray-50 border-t">
            {submitDisabledReason ? (
              <p className="mb-3 text-xs font-medium text-amber-700">{submitDisabledReason}</p>
            ) : null}
            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={() => onClose()}
                className="px-6 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50"
                disabled={saving}
              >
                Batal
              </button>
              <button
                type="button"
                onClick={onSubmit}
                className="px-6 py-2 text-sm font-medium text-white transition-all bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl hover:from-blue-700 hover:to-indigo-700 disabled:opacity-70"
                disabled={!canSubmit}
                title={submitDisabledReason}
              >
                {saving ? 'Menyimpan...' : isCreate ? 'Simpan Jadwal' : 'Simpan Perubahan'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

const JadwalPelajaran = () => {
  const { hasPermission } = useAuth();
  const canManage = hasPermission('manage_jadwal_pelajaran');
  const [activeTab, setActiveTab] = React.useState(0);
  const [rows, setRows] = React.useState([]);
  const [options, setOptions] = React.useState({
    guru: [],
    kelas: [],
    mata_pelajaran: [],
    tahun_ajaran: [],
    hari_options: [],
    slot_templates: {},
    schedule_setting: null,
  });
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');
  const [flash, setFlash] = React.useState(null);
  const [search, setSearch] = React.useState('');
  const [tahunAjaranId, setTahunAjaranId] = React.useState('');
  const [semester, setSemester] = React.useState('');
  const [kelasId, setKelasId] = React.useState('');
  const [hari, setHari] = React.useState('');
  const [status, setStatus] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(15);
  const [formOpen, setFormOpen] = React.useState(false);
  const [confirmOpen, setConfirmOpen] = React.useState(false);
  const [selected, setSelected] = React.useState(null);
  const [formMode, setFormMode] = React.useState('create');
  const [form, setForm] = React.useState(emptyForm);
  const [saving, setSaving] = React.useState(false);
  const [formSlotTemplates, setFormSlotTemplates] = React.useState({});
  const [formSlotLoading, setFormSlotLoading] = React.useState(false);
  const [slotTemplateCache, setSlotTemplateCache] = React.useState({});
  const [settingsYearId, setSettingsYearId] = React.useState('');
  const [settingsSemester, setSettingsSemester] = React.useState('full');
  const [settingsForm, setSettingsForm] = React.useState(null);
  const [settingsLoading, setSettingsLoading] = React.useState(false);
  const [settingsSaving, setSettingsSaving] = React.useState(false);
  const [showExportModal, setShowExportModal] = React.useState(false);
  const [exportProgress, setExportProgress] = React.useState(0);
  const [isExporting, setIsExporting] = React.useState(false);
  const [showImportModal, setShowImportModal] = React.useState(false);
  const [importProgress, setImportProgress] = React.useState(0);
  const [isImporting, setIsImporting] = React.useState(false);

  const getCacheKey = React.useCallback((yearId, semesterValue) => `${yearId || 'none'}:${semesterValue || 'full'}`, []);

  const loadData = React.useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const [optRes, rowRes] = await Promise.all([
        jadwalPelajaranAPI.getOptions(),
        jadwalPelajaranAPI.getAll({ no_pagination: true }),
      ]);
      const optionPayload = optRes?.data?.data || {};
      const tahunAjaranRows = toArray(optionPayload.tahun_ajaran);
      const initialSetting = optionPayload.schedule_setting || null;
      const defaultYearId = getDefaultYearId(tahunAjaranRows, initialSetting?.tahun_ajaran_id);
      const defaultSemester = initialSetting?.semester || 'full';

      setOptions({
        ...optionPayload,
        slot_templates: optionPayload.slot_templates || {},
      });
      setRows(toArray(rowRes?.data?.data));

      setSettingsYearId((prev) => prev || defaultYearId);
      setSettingsSemester((prev) => prev || defaultSemester);

      if (initialSetting) {
        const normalized = normalizeSetting(initialSetting, defaultYearId, defaultSemester);
        setSettingsForm((prev) => prev || normalized);

        const key = getCacheKey(normalized.tahun_ajaran_id, normalized.semester);
        setSlotTemplateCache((prev) => ({ ...prev, [key]: normalized.slot_templates || {} }));
      }
    } catch (e) {
      setError(e?.response?.data?.message || 'Gagal memuat data jadwal pelajaran');
    } finally {
      setLoading(false);
    }
  }, [getCacheKey]);

  React.useEffect(() => {
    loadData();
  }, [loadData]);

  React.useEffect(() => {
    if (!isImporting) {
      return undefined;
    }

    const currentUrl = window.location.href;
    const guardState = { import_guard: true, ts: getServerNowEpochMs() };
    window.history.pushState(guardState, document.title, currentUrl);

    const handlePopState = () => {
      window.history.pushState(guardState, document.title, currentUrl);
      setFlash({
        severity: 'warning',
        message: 'Import sedang berjalan. Tunggu hingga selesai sebelum pindah halaman.',
      });
    };

    const handleBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '';
      return '';
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [isImporting]);

  const fetchSettings = React.useCallback(async (yearId, semesterValue) => {
    if (!yearId) {
      return;
    }

    setSettingsLoading(true);
    try {
      const response = await jadwalPelajaranAPI.getSettings({
        tahun_ajaran_id: Number(yearId),
        semester: semesterValue || 'full',
      });

      const raw = response?.data?.data || null;
      const normalized = normalizeSetting(raw, yearId, semesterValue || 'full');
      setSettingsForm(normalized);

      const key = getCacheKey(yearId, semesterValue || 'full');
      setSlotTemplateCache((prev) => ({ ...prev, [key]: normalized.slot_templates || {} }));

      setOptions((prev) => ({
        ...prev,
        schedule_setting: raw,
        slot_templates: normalized.slot_templates || {},
      }));
    } catch (e) {
      setFlash({
        severity: 'error',
        message: e?.response?.data?.message || 'Gagal memuat pengaturan jadwal',
      });
    } finally {
      setSettingsLoading(false);
    }
  }, [getCacheKey]);

  React.useEffect(() => {
    if (activeTab === 1 && settingsYearId) {
      fetchSettings(settingsYearId, settingsSemester);
    }
  }, [activeTab, settingsYearId, settingsSemester, fetchSettings]);

  const ensureFormSlotTemplates = React.useCallback(async (yearId, semesterValue) => {
    if (!yearId) {
      setFormSlotTemplates({});
      return;
    }

    const key = getCacheKey(yearId, semesterValue || 'full');
    const cached = slotTemplateCache[key];
    if (cached) {
      setFormSlotTemplates(cached);
      return;
    }

    setFormSlotLoading(true);
    try {
      const response = await jadwalPelajaranAPI.getSettings({
        tahun_ajaran_id: Number(yearId),
        semester: semesterValue || 'full',
      });

      const templates = response?.data?.data?.slot_templates || {};
      setSlotTemplateCache((prev) => ({ ...prev, [key]: templates }));
      setFormSlotTemplates(templates);
    } catch (e) {
      setFormSlotTemplates({});
      setFlash({
        severity: 'error',
        message: e?.response?.data?.message || 'Gagal memuat slot JP dari pengaturan',
      });
    } finally {
      setFormSlotLoading(false);
    }
  }, [getCacheKey, slotTemplateCache]);

  React.useEffect(() => {
    if (formOpen) {
      const yearId = form.tahun_ajaran_id || settingsYearId;
      const semesterValue = form.semester || 'full';
      ensureFormSlotTemplates(yearId, semesterValue);
    }
  }, [formOpen, form.tahun_ajaran_id, form.semester, settingsYearId, ensureFormSlotTemplates]);

  const currentDaySlots = React.useMemo(() => toArray(formSlotTemplates?.[form.hari]), [formSlotTemplates, form.hari]);
  const formTahunAjaranOptions = React.useMemo(() => toArray(options.tahun_ajaran), [options.tahun_ajaran]);
  const formKelasOptions = React.useMemo(() => (
    toArray(options.kelas).filter((item) => (
      !form.tahun_ajaran_id || String(item?.tahun_ajaran_id || '') === String(form.tahun_ajaran_id)
    ))
  ), [options.kelas, form.tahun_ajaran_id]);

  const mapelById = React.useMemo(() => {
    const lookup = new Map();
    toArray(options.mata_pelajaran).forEach((item) => {
      lookup.set(String(item.id), item);
    });
    return lookup;
  }, [options.mata_pelajaran]);

  const mapelRefToId = React.useMemo(() => {
    const lookup = new Map();
    toArray(options.mata_pelajaran).forEach((item) => {
      lookup.set(normalizeToken(item?.kode_mapel), String(item.id));
      lookup.set(normalizeToken(item?.nama_mapel), String(item.id));
    });
    return lookup;
  }, [options.mata_pelajaran]);

  const guruById = React.useMemo(() => {
    const lookup = new Map();
    toArray(options.guru).forEach((item) => {
      lookup.set(String(item.id), item);
    });
    return lookup;
  }, [options.guru]);

  const normalizedAssignments = React.useMemo(() => (
    toArray(options.penugasan_guru_mapel)
      .map((row) => {
        const status = normalizeToken(row?.status || 'aktif');
        let mapelId = row?.mata_pelajaran_id ? String(row.mata_pelajaran_id) : '';
        if (!mapelId && row?.mata_pelajaran) {
          mapelId = mapelRefToId.get(normalizeToken(row.mata_pelajaran)) || '';
        }

        return {
          guru_id: row?.guru_id ? String(row.guru_id) : '',
          kelas_id: row?.kelas_id ? String(row.kelas_id) : '',
          tahun_ajaran_id: row?.tahun_ajaran_id ? String(row.tahun_ajaran_id) : '',
          mapel_id: mapelId,
          status,
        };
      })
      .filter((row) => row.guru_id && row.kelas_id && row.tahun_ajaran_id && row.mapel_id)
      .filter((row) => row.status === '' || row.status === 'aktif' || row.status === 'active')
  ), [options.penugasan_guru_mapel, mapelRefToId]);

  const scopeAssignments = React.useMemo(() => (
    normalizedAssignments.filter((row) => (
      (!form.tahun_ajaran_id || row.tahun_ajaran_id === String(form.tahun_ajaran_id))
      && (!form.kelas_id || row.kelas_id === String(form.kelas_id))
    ))
  ), [normalizedAssignments, form.tahun_ajaran_id, form.kelas_id]);

  const formMapelOptions = React.useMemo(() => {
    if (!form.tahun_ajaran_id || !form.kelas_id) {
      return [];
    }

    const ids = [...new Set(scopeAssignments.map((row) => row.mapel_id))];
    const rows = ids
      .map((id) => mapelById.get(id))
      .filter(Boolean);

    const selectedMapel = form.mata_pelajaran_id ? mapelById.get(String(form.mata_pelajaran_id)) : null;
    if (selectedMapel && !rows.some((item) => String(item.id) === String(selectedMapel.id))) {
      rows.push(selectedMapel);
    }

    return rows;
  }, [scopeAssignments, mapelById, form.tahun_ajaran_id, form.kelas_id, form.mata_pelajaran_id]);

  const assignedGuruRows = React.useMemo(() => (
    scopeAssignments.filter((row) => (
      !form.mata_pelajaran_id || row.mapel_id === String(form.mata_pelajaran_id)
    ))
  ), [scopeAssignments, form.mata_pelajaran_id]);

  const conflictedGuruIds = React.useMemo(() => {
    if (!form.tahun_ajaran_id || !form.hari || !form.jam_mulai || !form.jam_selesai) {
      return new Set();
    }

    const set = new Set();
    const currentId = selected?.id ? Number(selected.id) : null;

    rows.forEach((row) => {
      if (!isScheduleRowActive(row)) {
        return;
      }
      if (currentId && Number(row?.id) === currentId) {
        return;
      }
      if (String(row?.tahun_ajaran_id || '') !== String(form.tahun_ajaran_id)) {
        return;
      }
      if (normalizeToken(row?.hari) !== normalizeToken(form.hari)) {
        return;
      }
      if (!isSemesterConflict(row?.semester, form.semester || 'full')) {
        return;
      }
      if (!hasTimeOverlap(row?.jam_mulai, row?.jam_selesai, form.jam_mulai, form.jam_selesai)) {
        return;
      }
      if (row?.guru_id) {
        set.add(String(row.guru_id));
      }
    });

    return set;
  }, [rows, selected?.id, form.tahun_ajaran_id, form.hari, form.jam_mulai, form.jam_selesai, form.semester]);

  const formGuruOptions = React.useMemo(() => {
    if (!form.tahun_ajaran_id || !form.kelas_id || !form.mata_pelajaran_id) {
      return [];
    }

    const ids = [...new Set(assignedGuruRows.map((row) => row.guru_id))];
    const mapped = ids
      .map((id) => guruById.get(id))
      .filter(Boolean);

    const filtered = mapped.filter((item) => !conflictedGuruIds.has(String(item.id)));

    const selectedGuru = form.guru_id ? guruById.get(String(form.guru_id)) : null;
    if (selectedGuru && !filtered.some((item) => String(item.id) === String(selectedGuru.id))) {
      filtered.push(selectedGuru);
    }

    return filtered;
  }, [
    assignedGuruRows,
    guruById,
    conflictedGuruIds,
    form.tahun_ajaran_id,
    form.kelas_id,
    form.mata_pelajaran_id,
    form.guru_id,
  ]);

  const jadwalFormFilteredOptions = React.useMemo(() => ({
    tahun_ajaran: formTahunAjaranOptions,
    semester: SEMESTER_OPTIONS,
    kelas: formKelasOptions,
    hari: toArray(options.hari_options),
    mapel: formMapelOptions,
    guru: formGuruOptions,
  }), [
    formTahunAjaranOptions,
    formKelasOptions,
    options.hari_options,
    formMapelOptions,
    formGuruOptions,
  ]);

  const jadwalFormMappingMeta = React.useMemo(() => {
    const totalGuruAssigned = [...new Set(assignedGuruRows.map((item) => item.guru_id))].length;
    const conflictCount = Math.max(0, totalGuruAssigned - formGuruOptions.length);

    return {
      mapelCount: formMapelOptions.length,
      availableGuruCount: formGuruOptions.length,
      conflictGuruCount: conflictCount,
    };
  }, [assignedGuruRows, formMapelOptions.length, formGuruOptions.length]);

  React.useEffect(() => {
    if (!formOpen || !form.jam_ke) {
      return;
    }

    const chosen = currentDaySlots.find((slot) => String(slot.jp_ke) === String(form.jam_ke));
    if (!chosen) {
      return;
    }

    setForm((prev) => ({
      ...prev,
      jam_mulai: chosen.jam_mulai || prev.jam_mulai,
      jam_selesai: chosen.jam_selesai || prev.jam_selesai,
    }));
  }, [formOpen, form.jam_ke, currentDaySlots]);

  React.useEffect(() => {
    if (!formOpen || !form.guru_id) {
      return;
    }

    const stillValid = formGuruOptions.some((item) => String(item.id) === String(form.guru_id));
    if (!stillValid) {
      setForm((prev) => ({ ...prev, guru_id: '' }));
    }
  }, [formOpen, form.guru_id, formGuruOptions]);

  const filtered = React.useMemo(() => {
    const key = search.trim().toLowerCase();

    return rows.filter((r) => {
      const text = `${r?.guru?.nama_lengkap || ''} ${r?.kelas?.nama_kelas || ''} ${r?.mata_pelajaran?.nama_mapel || ''} ${r?.ruangan || ''}`.toLowerCase();
      return (
        (!key || text.includes(key))
        && (!tahunAjaranId || String(r?.tahun_ajaran_id || '') === tahunAjaranId)
        && (!semester || String(r?.semester || '') === semester)
        && (!kelasId || String(r?.kelas_id || '') === kelasId)
        && (!hari || String(r?.hari || '') === hari)
        && (!status || String(r?.status || '') === status)
      );
    });
  }, [rows, search, tahunAjaranId, semester, kelasId, hari, status]);

  const total = filtered.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  React.useEffect(() => {
    if (page > lastPage) {
      setPage(lastPage);
    }
  }, [page, lastPage]);

  const currentRows = React.useMemo(
    () => filtered.slice((page - 1) * perPage, page * perPage),
    [filtered, page, perPage]
  );

  const currentRowsWithBreaks = React.useMemo(() => {
    const merged = [];

    currentRows.forEach((row, idx) => {
      merged.push({
        type: 'lesson',
        key: `lesson-${row.id}`,
        row,
        lessonIndex: idx,
      });

      const nextRow = currentRows[idx + 1];
      if (!isSameScheduleGroup(row, nextRow)) {
        return;
      }

      const currentJp = Number(row?.jam_ke);
      const nextJp = Number(nextRow?.jam_ke);
      if (!Number.isFinite(currentJp) || !Number.isFinite(nextJp) || nextJp !== currentJp + 1) {
        return;
      }

      const endMinutes = parseTimeToMinutes(row?.jam_selesai);
      const nextStartMinutes = parseTimeToMinutes(nextRow?.jam_mulai);
      if (endMinutes === null || nextStartMinutes === null) {
        return;
      }

      const gapMinutes = nextStartMinutes - endMinutes;
      if (gapMinutes <= 0) {
        return;
      }

      merged.push({
        type: 'break',
        key: `break-${row.id}-${nextRow.id}`,
        breakInfo: {
          hari: row?.hari_label || row?.hari || '-',
          kelas: row?.kelas?.nama_kelas || '-',
          start: formatTimeHM(row?.jam_selesai),
          end: formatTimeHM(nextRow?.jam_mulai),
          duration: gapMinutes,
          label: getBreakLabel(gapMinutes),
        },
      });
    });

    return merged;
  }, [currentRows]);

  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = total === 0 ? 0 : Math.min(page * perPage, total);

  const resetFilter = () => {
    setSearch('');
    setTahunAjaranId('');
    setSemester('');
    setKelasId('');
    setHari('');
    setStatus('');
    setPage(1);
  };

  const openCreate = () => {
    const defaultYearId = settingsYearId || getDefaultYearId(options.tahun_ajaran);
    const defaultSemester = settingsSemester || 'full';

    setSelected(null);
    setFormMode('create');
    setForm({
      ...emptyForm,
      tahun_ajaran_id: defaultYearId,
      semester: defaultSemester,
    });
    setFormOpen(true);
  };

  const openEdit = (row) => {
    setSelected(row);
    setFormMode('edit');
    setForm({
      guru_id: String(row.guru_id || ''),
      mata_pelajaran_id: String(row.mata_pelajaran_id || ''),
      kelas_id: String(row.kelas_id || ''),
      tahun_ajaran_id: String(row.tahun_ajaran_id || ''),
      semester: row.semester || 'full',
      hari: row.hari || 'senin',
      jam_mulai: row.jam_mulai || '07:00',
      jam_selesai: row.jam_selesai || '07:45',
      jam_ke: row.jam_ke ? String(row.jam_ke) : '',
      jp_count: '1',
      ruangan: row.ruangan || '',
      status: row.status || 'draft',
      is_active: row.is_active ?? true,
    });
    setFormOpen(true);
  };

  const openDelete = (row) => {
    setSelected(row);
    setConfirmOpen(true);
  };

  const handleFormChange = (key, value) => {
    setForm((prev) => {
      const next = { ...prev, [key]: value };

      if (key === 'tahun_ajaran_id' || key === 'semester' || key === 'hari' || key === 'kelas_id') {
        next.jam_ke = '';
        next.jam_mulai = '';
        next.jam_selesai = '';
      }
      if (key === 'tahun_ajaran_id' || key === 'kelas_id') {
        next.mata_pelajaran_id = '';
        next.guru_id = '';
      }
      if (key === 'mata_pelajaran_id') {
        next.guru_id = '';
      }

      return next;
    });

    if (key === 'tahun_ajaran_id' || key === 'semester' || key === 'kelas_id') {
      const yearId = key === 'tahun_ajaran_id' ? value : form.tahun_ajaran_id;
      const semesterValue = key === 'semester' ? value : form.semester;
      ensureFormSlotTemplates(yearId, semesterValue);
    }
  };

  const handleJamKeChange = (value) => {
    const chosen = currentDaySlots.find((slot) => String(slot.jp_ke) === String(value));
    setForm((prev) => ({
      ...prev,
      jam_ke: value,
      jam_mulai: chosen?.jam_mulai || '',
      jam_selesai: chosen?.jam_selesai || '',
    }));
  };

  const submit = async () => {
    if (!form.jam_ke) {
      setFlash({
        severity: 'error',
        message: 'Jam ke (JP) wajib dipilih dari slot pengaturan jadwal.',
      });
      return;
    }

    const parsedJpCount = Number(form.jp_count || 1);
    const jpCount = Number.isFinite(parsedJpCount) ? Math.max(1, Math.min(12, Math.trunc(parsedJpCount))) : 1;
    if (formMode === 'create') {
      const startJp = Number(form.jam_ke || 0);
      const enoughSlots = Array.from({ length: jpCount }).every((_, index) => (
        currentDaySlots.some((slot) => Number(slot.jp_ke) === (startJp + index))
      ));
      if (!enoughSlots) {
        setFlash({
          severity: 'error',
          message: `Rentang JP ${startJp}-${startJp + jpCount - 1} tidak tersedia pada hari ini.`,
        });
        return;
      }
    }

    setSaving(true);

    try {
      const payload = {
        ...form,
        guru_id: Number(form.guru_id),
        mata_pelajaran_id: Number(form.mata_pelajaran_id),
        kelas_id: Number(form.kelas_id),
        tahun_ajaran_id: Number(form.tahun_ajaran_id),
        jam_ke: form.jam_ke === '' ? null : Number(form.jam_ke),
        jp_count: jpCount,
        jam_mulai: form.jam_mulai || null,
        jam_selesai: form.jam_selesai || null,
      };

      let response;
      if (formMode === 'create') {
        response = await jadwalPelajaranAPI.create(payload);
      } else {
        response = await jadwalPelajaranAPI.update(selected.id, payload);
      }

      const createdCount = Number(response?.data?.created_count || 1);
      setFormOpen(false);
      setFlash({
        severity: 'success',
        message: formMode === 'create'
          ? (createdCount > 1 ? `${createdCount} jadwal berurutan berhasil ditambahkan` : 'Jadwal berhasil ditambahkan')
          : 'Jadwal berhasil diperbarui',
      });
      await loadData();
    } catch (e) {
      const conflict = e?.response?.data?.conflicts?.summary;
      const conflictMsg = conflict
        ? ` (Guru: ${conflict.guru}, Kelas: ${conflict.kelas}, Ruangan: ${conflict.ruangan})`
        : '';

      setFlash({
        severity: 'error',
        message: `${e?.response?.data?.message || 'Gagal menyimpan jadwal'}${conflictMsg}`,
      });
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!selected?.id || saving) {
      return;
    }

    setSaving(true);
    try {
      await jadwalPelajaranAPI.delete(selected.id);
      setConfirmOpen(false);
      setFlash({ severity: 'success', message: 'Jadwal berhasil dihapus' });
      await loadData();
    } catch (e) {
      setFlash({ severity: 'error', message: e?.response?.data?.message || 'Gagal menghapus jadwal' });
    } finally {
      setSaving(false);
    }
  };

  const publish = async () => {
    if (!canManage || !tahunAjaranId) {
      return;
    }

    setSaving(true);
    try {
      const payload = {
        tahun_ajaran_id: Number(tahunAjaranId),
        semester: semester || null,
        kelas_id: kelasId ? Number(kelasId) : null,
      };

      const res = await jadwalPelajaranAPI.publish(payload);
      setFlash({ severity: 'success', message: res?.data?.message || 'Publish selesai' });
      await loadData();
    } catch (e) {
      setFlash({ severity: 'error', message: e?.response?.data?.message || 'Gagal publish jadwal' });
    } finally {
      setSaving(false);
    }
  };

  const handleDownloadTemplate = async () => {
    const response = await jadwalPelajaranAPI.downloadTemplate();
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'Template_Import_Jadwal_Pelajaran.xlsx');
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const buildExportParams = React.useCallback(({ format = 'xlsx', fields = [] } = {}) => {
    const params = { format, fields };

    const keyword = search.trim();
    if (keyword) {
      params.search = keyword;
    }
    if (tahunAjaranId) {
      params.tahun_ajaran_id = tahunAjaranId;
    }
    if (semester) {
      params.semester = semester;
    }
    if (kelasId) {
      params.kelas_id = kelasId;
    }
    if (hari) {
      params.hari = hari;
    }
    if (status) {
      params.status = status;
    }

    return params;
  }, [search, tahunAjaranId, semester, kelasId, hari, status]);

  const handleExport = async ({ format = 'xlsx', fields = [] } = {}) => {
    setExportProgress(0);
    setIsExporting(true);

    const progressInterval = setInterval(() => {
      setExportProgress((prev) => Math.min(prev + 12, 90));
    }, 300);

    try {
      const response = await jadwalPelajaranAPI.exportData(buildExportParams({ format, fields }));

      clearInterval(progressInterval);
      setExportProgress(100);

      const dateStamp = getServerDateString();
      const extension = format === 'pdf' ? 'pdf' : 'xlsx';
      const fallbackName = `Jadwal_Pelajaran_${dateStamp}.${extension}`;
      downloadBlobResponse(response, fallbackName);

      const message = `Export jadwal pelajaran (${format.toUpperCase()}) berhasil`;
      setFlash({ severity: 'success', message });
      return { success: true, message };
    } catch (error) {
      clearInterval(progressInterval);
      setExportProgress(0);

      const message = error?.response?.data?.message || error?.message || 'Export jadwal pelajaran gagal';
      throw new Error(message);
    } finally {
      setIsExporting(false);
    }
  };

  const closeExportModal = () => {
    if (isExporting) {
      return;
    }

    setShowExportModal(false);
    setExportProgress(0);
  };

  const handleImport = async (formData) => {
    setImportProgress(0);
    setIsImporting(true);

    const progressInterval = setInterval(() => {
      setImportProgress((prev) => Math.min(prev + 10, 90));
    }, 500);

    try {
      const response = await jadwalPelajaranAPI.importData(formData);
      const result = response?.data || {};

      clearInterval(progressInterval);
      setImportProgress(100);

      if (!result.success) {
        const error = new Error(result.message || 'Import jadwal pelajaran gagal');
        error.details = result.data || null;
        throw error;
      }

      setFlash({ severity: 'success', message: result.message || 'Import jadwal berhasil' });
      await loadData();

      return {
        success: true,
        message: result.message || 'Import jadwal berhasil',
        details: result.data || null,
      };
    } catch (error) {
      clearInterval(progressInterval);
      setImportProgress(0);

      const message =
        error?.response?.data?.message
        || error?.message
        || 'Import jadwal gagal';
      const details = error?.response?.data?.data || error?.details || null;

      const importError = new Error(message);
      importError.details = details;
      throw importError;
    } finally {
      setIsImporting(false);
    }
  };

  const handleImportSuccess = () => {
    setShowImportModal(false);
    setImportProgress(0);
  };

  const updateSettingsField = (key, value) => {
    setSettingsForm((prev) => ({ ...prev, [key]: value }));
  };

  const updateDayField = (dayIndex, key, value) => {
    setSettingsForm((prev) => {
      const nextDays = [...toArray(prev.days)];
      const target = { ...nextDays[dayIndex] };
      target[key] = value;

      if (key === 'is_school_day' && !value) {
        target.jp_count = '0';
      }

      nextDays[dayIndex] = target;
      return { ...prev, days: nextDays };
    });
  };

  const addBreak = (dayIndex) => {
    setSettingsForm((prev) => {
      const nextDays = [...toArray(prev.days)];
      const target = { ...nextDays[dayIndex] };
      target.breaks = [...toArray(target.breaks), { id: null, after_jp: '', break_minutes: '', label: '' }];
      nextDays[dayIndex] = target;
      return { ...prev, days: nextDays };
    });
  };

  const updateBreakField = (dayIndex, breakIndex, key, value) => {
    setSettingsForm((prev) => {
      const nextDays = [...toArray(prev.days)];
      const target = { ...nextDays[dayIndex] };
      const breaks = [...toArray(target.breaks)];
      breaks[breakIndex] = { ...breaks[breakIndex], [key]: value };
      target.breaks = breaks;
      nextDays[dayIndex] = target;
      return { ...prev, days: nextDays };
    });
  };

  const removeBreak = (dayIndex, breakIndex) => {
    setSettingsForm((prev) => {
      const nextDays = [...toArray(prev.days)];
      const target = { ...nextDays[dayIndex] };
      target.breaks = toArray(target.breaks).filter((_, idx) => idx !== breakIndex);
      nextDays[dayIndex] = target;
      return { ...prev, days: nextDays };
    });
  };

  const saveSettings = async () => {
    if (!settingsForm || !canManage) {
      return;
    }

    setSettingsSaving(true);
    try {
      const payload = buildSettingsPayload(settingsForm);
      const response = await jadwalPelajaranAPI.updateSettings(payload);
      const raw = response?.data?.data || null;
      const normalized = normalizeSetting(raw, settingsForm.tahun_ajaran_id, settingsForm.semester);

      setSettingsForm(normalized);

      const key = getCacheKey(normalized.tahun_ajaran_id, normalized.semester);
      setSlotTemplateCache((prev) => ({ ...prev, [key]: normalized.slot_templates || {} }));
      setOptions((prev) => ({
        ...prev,
        schedule_setting: raw,
        slot_templates: normalized.slot_templates || {},
      }));

      setFlash({ severity: 'success', message: 'Pengaturan jadwal berhasil disimpan' });
    } catch (e) {
      setFlash({
        severity: 'error',
        message: e?.response?.data?.message || 'Gagal menyimpan pengaturan jadwal',
      });
    } finally {
      setSettingsSaving(false);
    }
  };

  return (
    <div className="p-6">
      <Box className="flex items-center gap-3 mb-6">
        <div className="p-2 bg-blue-100 rounded-lg">
          <CalendarClock className="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <Typography variant="h4" className="font-bold text-gray-900">
            Jadwal Pelajaran
          </Typography>
          <Typography variant="body2" className="text-gray-600">
            Kelola jadwal pelajaran dengan validasi konflik guru, kelas, dan ruangan
          </Typography>
        </div>
      </Box>

      {(error || flash) && (
        <Alert
          severity={error ? 'error' : flash?.severity || 'info'}
          className="mb-4"
          onClose={() => {
            setError('');
            setFlash(null);
          }}
        >
          {error || flash?.message}
        </Alert>
      )}

      <Paper className="mb-6 border border-gray-100 shadow-sm">
        <Tabs value={activeTab} onChange={(_, value) => setActiveTab(value)}>
          <Tab icon={<CalendarClock className="w-4 h-4" />} iconPosition="start" label="Data Jadwal" />
          <Tab icon={<Settings className="w-4 h-4" />} iconPosition="start" label="Pengaturan Jadwal" />
        </Tabs>
      </Paper>

      {activeTab === 0 && (
        <>

      <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
        <Box className="flex flex-col lg:flex-row gap-4 mb-4">
          <TextField
            size="small"
            fullWidth
            placeholder="Cari guru, mapel, kelas, ruangan..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-4 h-4 text-gray-400" />
                </InputAdornment>
              ),
            }}
          />

          <FormControl size="small" sx={{ minWidth: 180 }}>
            <Select
              displayEmpty
              value={tahunAjaranId}
              onChange={(e) => {
                setTahunAjaranId(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Tahun Ajaran</MenuItem>
              {toArray(options.tahun_ajaran).map((item) => (
                <MenuItem key={item.id} value={String(item.id)}>
                  {item.nama}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 150 }}>
            <Select
              displayEmpty
              value={semester}
              onChange={(e) => {
                setSemester(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Semester</MenuItem>
              <MenuItem value="ganjil">Ganjil</MenuItem>
              <MenuItem value="genap">Genap</MenuItem>
              <MenuItem value="full">Full</MenuItem>
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 150 }}>
            <Select
              displayEmpty
              value={kelasId}
              onChange={(e) => {
                setKelasId(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Kelas</MenuItem>
              {toArray(options.kelas).map((item) => (
                <MenuItem key={item.id} value={String(item.id)}>
                  {item.nama_kelas}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select
              displayEmpty
              value={hari}
              onChange={(e) => {
                setHari(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Hari</MenuItem>
              {toArray(options.hari_options).map((item) => (
                <MenuItem key={item.value} value={item.value}>
                  {item.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select
              displayEmpty
              value={status}
              onChange={(e) => {
                setStatus(e.target.value);
                setPage(1);
              }}
            >
              <MenuItem value="">Semua Status</MenuItem>
              <MenuItem value="draft">Draft</MenuItem>
              <MenuItem value="published">Published</MenuItem>
              <MenuItem value="archived">Archived</MenuItem>
            </Select>
          </FormControl>
        </Box>

        <Box className="flex flex-wrap items-center justify-between gap-3">
          <Button variant="outlined" size="small" startIcon={<RotateCcw className="w-4 h-4" />} onClick={resetFilter}>
            Reset Filter
          </Button>

          <Box className="flex items-center gap-2">
            {canManage && (
              <Button
                variant="outlined"
                size="small"
                startIcon={<Download className="w-4 h-4" />}
                onClick={() => setShowExportModal(true)}
                disabled={isExporting}
              >
                Export
              </Button>
            )}
            {canManage && (
              <Button
                variant="outlined"
                size="small"
                startIcon={<Send className="w-4 h-4" />}
                disabled={!tahunAjaranId || saving}
                onClick={publish}
              >
                Publish Draft
              </Button>
            )}
            {canManage && (
              <Button
                variant="outlined"
                size="small"
                startIcon={<Upload className="w-4 h-4" />}
                onClick={() => setShowImportModal(true)}
              >
                Import
              </Button>
            )}
            {canManage && (
              <Button variant="contained" size="small" startIcon={<Plus className="w-4 h-4" />} onClick={openCreate}>
                Tambah Jadwal
              </Button>
            )}
          </Box>
        </Box>
      </Paper>

      <TableContainer component={Paper} className="border border-gray-100 shadow-sm">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell width={60}>No</TableCell>
              <TableCell>Hari</TableCell>
              <TableCell>Jam</TableCell>
              <TableCell>Kelas</TableCell>
              <TableCell>Mapel</TableCell>
              <TableCell>Guru</TableCell>
              <TableCell>Ruangan</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="center">Aksi</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && (
              <TableRow>
                <TableCell colSpan={9} align="center">
                  Memuat data...
                </TableCell>
              </TableRow>
            )}

            {!loading && currentRows.length === 0 && (
              <TableRow>
                <TableCell colSpan={9} align="center">
                  Tidak ada data jadwal
                </TableCell>
              </TableRow>
            )}

            {!loading &&
              currentRowsWithBreaks.map((entry) => {
                if (entry.type === 'break') {
                  const info = entry.breakInfo;
                  return (
                    <TableRow key={entry.key} sx={{ backgroundColor: '#FFF8E6' }}>
                      <TableCell />
                      <TableCell>{info.hari}</TableCell>
                      <TableCell>{`${info.start} - ${info.end}`}</TableCell>
                      <TableCell>{info.kelas}</TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          color="warning"
                          variant="filled"
                          label={`${info.label} (${info.duration} menit)`}
                        />
                      </TableCell>
                      <TableCell>-</TableCell>
                      <TableCell>-</TableCell>
                      <TableCell>
                        <Chip size="small" color="warning" variant="outlined" label="Break" />
                      </TableCell>
                      <TableCell align="center">-</TableCell>
                    </TableRow>
                  );
                }

                const row = entry.row;
                return (
                  <TableRow key={entry.key} hover>
                    <TableCell>{(page - 1) * perPage + entry.lessonIndex + 1}</TableCell>
                    <TableCell>{row.hari_label || row.hari}</TableCell>
                    <TableCell>{row.time_range || `${row.jam_mulai} - ${row.jam_selesai}`}</TableCell>
                    <TableCell>{row?.kelas?.nama_kelas || '-'}</TableCell>
                    <TableCell>
                      {row?.mata_pelajaran?.kode_mapel ? `${row.mata_pelajaran.kode_mapel} - ` : ''}
                      {row?.mata_pelajaran?.nama_mapel || '-'}
                    </TableCell>
                    <TableCell>{row?.guru?.nama_lengkap || '-'}</TableCell>
                    <TableCell>{row.ruangan || '-'}</TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        label={row.status_label || row.status}
                        color={
                          row.status === 'published'
                            ? 'success'
                            : row.status === 'draft'
                              ? 'warning'
                              : 'default'
                        }
                        variant={row.status === 'published' ? 'filled' : 'outlined'}
                      />
                    </TableCell>
                    <TableCell align="center">
                      {canManage ? (
                        <Box className="flex items-center justify-center gap-1">
                          <IconButton size="small" color="primary" onClick={() => openEdit(row)}>
                            <Edit className="w-4 h-4" />
                          </IconButton>
                          <IconButton size="small" color="error" onClick={() => openDelete(row)}>
                            <Trash2 className="w-4 h-4" />
                          </IconButton>
                        </Box>
                      ) : (
                        '-'
                      )}
                    </TableCell>
                  </TableRow>
                );
              })}
          </TableBody>
        </Table>
      </TableContainer>

      <Paper className="mt-4 px-4 py-3 border border-gray-100 shadow-sm">
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
                onChange={(e) => {
                  setPerPage(Number(e.target.value));
                  setPage(1);
                }}
                sx={{ minWidth: 84 }}
              >
                {[10, 15, 25, 50].map((n) => (
                  <MenuItem key={n} value={n}>
                    {n}
                  </MenuItem>
                ))}
              </Select>
            </Box>

            <Pagination
              page={page}
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
      )}

      {activeTab === 1 && (
        <>
          <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
            <Box className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
              <Box className="flex flex-col lg:flex-row gap-3 w-full lg:w-auto">
                <FormControl size="small" sx={{ minWidth: 220 }}>
                  <Select
                    displayEmpty
                    value={settingsYearId}
                    onChange={(e) => setSettingsYearId(e.target.value)}
                  >
                    <MenuItem value="">Pilih Tahun Ajaran</MenuItem>
                    {toArray(options.tahun_ajaran).map((item) => (
                      <MenuItem key={item.id} value={String(item.id)}>
                        {item.nama}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>

                <FormControl size="small" sx={{ minWidth: 150 }}>
                  <Select value={settingsSemester} onChange={(e) => setSettingsSemester(e.target.value)}>
                    <MenuItem value="ganjil">Ganjil</MenuItem>
                    <MenuItem value="genap">Genap</MenuItem>
                    <MenuItem value="full">Full</MenuItem>
                  </Select>
                </FormControl>

                <Button
                  variant="outlined"
                  size="small"
                  startIcon={<RotateCcw className="w-4 h-4" />}
                  onClick={() => fetchSettings(settingsYearId, settingsSemester)}
                  disabled={!settingsYearId || settingsLoading}
                >
                  Muat Ulang
                </Button>
              </Box>

              <Box className="flex items-center gap-2">
                {settingsLoading && (
                  <Box className="flex items-center gap-2 text-sm text-gray-500">
                    <CircularProgress size={16} /> Memuat...
                  </Box>
                )}
                {canManage && (
                  <Button
                    variant="contained"
                    size="small"
                    startIcon={<Save className="w-4 h-4" />}
                    onClick={saveSettings}
                    disabled={!settingsForm || settingsSaving || settingsLoading}
                  >
                    {settingsSaving ? 'Menyimpan...' : 'Simpan Pengaturan'}
                  </Button>
                )}
              </Box>
            </Box>
          </Paper>

          {settingsForm && (
            <>
              <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
                <Typography variant="h6" className="font-semibold mb-4">
                  Pengaturan Umum Slot JP
                </Typography>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Durasi JP Default (menit)</label>
                    <input
                      type="number"
                      min="20"
                      max="90"
                      className={settingsFieldClassName}
                      value={settingsForm.default_jp_minutes}
                      onChange={(e) => updateSettingsField('default_jp_minutes', e.target.value)}
                      disabled={!canManage}
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Jam Mulai Default</label>
                    <input
                      type="time"
                      className={settingsFieldClassName}
                      value={settingsForm.default_start_time}
                      onChange={(e) => updateSettingsField('default_start_time', e.target.value)}
                      disabled={!canManage}
                    />
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input
                      className={settingsFieldClassName}
                      value={settingsForm.notes}
                      onChange={(e) => updateSettingsField('notes', e.target.value)}
                      placeholder="Catatan pengaturan (opsional)"
                      disabled={!canManage}
                    />
                  </div>
                </div>

                <Box className="mt-4">
                  <FormControlLabel
                    control={(
                      <Switch
                        checked={Boolean(settingsForm.is_active)}
                        onChange={(e) => updateSettingsField('is_active', e.target.checked)}
                        disabled={!canManage}
                      />
                    )}
                    label="Aktifkan pengaturan ini"
                  />
                </Box>
              </Paper>

              <Typography variant="h6" className="font-semibold mb-3">
                Pengaturan Per Hari
              </Typography>

              {toArray(settingsForm.days)
                .sort((a, b) => DAY_ORDER.indexOf(a.hari) - DAY_ORDER.indexOf(b.hari))
                .map((day, dayIndex) => (
                  <Paper key={day.hari} className="p-5 mb-4 shadow-sm border border-gray-100">
                    <Box className="flex items-center justify-between mb-3">
                      <Typography variant="subtitle1" className="font-semibold">
                        {day.hari_label}
                      </Typography>

                      <FormControlLabel
                        control={(
                          <Switch
                            checked={Boolean(day.is_school_day)}
                            onChange={(e) => updateDayField(dayIndex, 'is_school_day', e.target.checked)}
                            disabled={!canManage}
                          />
                        )}
                        label="Hari sekolah"
                      />
                    </Box>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Jumlah JP</label>
                        <input
                          type="number"
                          min="0"
                          max="16"
                          className={settingsFieldClassName}
                          value={day.jp_count}
                          onChange={(e) => updateDayField(dayIndex, 'jp_count', e.target.value)}
                          disabled={!canManage || !day.is_school_day}
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Durasi JP Override</label>
                        <input
                          type="number"
                          min="20"
                          max="90"
                          className={settingsFieldClassName}
                          value={day.jp_minutes}
                          onChange={(e) => updateDayField(dayIndex, 'jp_minutes', e.target.value)}
                          placeholder="Kosong = default"
                          disabled={!canManage || !day.is_school_day}
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Jam Mulai Override</label>
                        <input
                          type="time"
                          className={settingsFieldClassName}
                          value={day.start_time}
                          onChange={(e) => updateDayField(dayIndex, 'start_time', e.target.value)}
                          disabled={!canManage || !day.is_school_day}
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Catatan Hari</label>
                        <input
                          className={settingsFieldClassName}
                          value={day.notes}
                          onChange={(e) => updateDayField(dayIndex, 'notes', e.target.value)}
                          disabled={!canManage || !day.is_school_day}
                        />
                      </div>
                    </div>

                    <Divider className="my-3" />

                    <Box className="flex items-center justify-between mb-2">
                      <Typography variant="body2" className="font-medium text-gray-700">
                        Istirahat
                      </Typography>
                      {canManage && (
                        <Button
                          size="small"
                          variant="outlined"
                          startIcon={<Plus className="w-4 h-4" />}
                          onClick={() => addBreak(dayIndex)}
                          disabled={!day.is_school_day}
                        >
                          Tambah Istirahat
                        </Button>
                      )}
                    </Box>

                    {toArray(day.breaks).length === 0 && (
                      <Typography variant="body2" color="text.secondary">
                        Belum ada jeda istirahat.
                      </Typography>
                    )}

                    {toArray(day.breaks).map((breakRow, breakIndex) => (
                      <div key={`${day.hari}-${breakIndex}`} className="grid grid-cols-1 md:grid-cols-12 gap-2 mb-2">
                        <div className="md:col-span-3">
                          <input
                            type="number"
                            min="1"
                            max="16"
                            className={settingsFieldClassName}
                            value={breakRow.after_jp}
                            onChange={(e) => updateBreakField(dayIndex, breakIndex, 'after_jp', e.target.value)}
                            placeholder="Setelah JP"
                            disabled={!canManage || !day.is_school_day}
                          />
                        </div>

                        <div className="md:col-span-3">
                          <input
                            type="number"
                            min="1"
                            max="120"
                            className={settingsFieldClassName}
                            value={breakRow.break_minutes}
                            onChange={(e) => updateBreakField(dayIndex, breakIndex, 'break_minutes', e.target.value)}
                            placeholder="Durasi (menit)"
                            disabled={!canManage || !day.is_school_day}
                          />
                        </div>

                        <div className="md:col-span-5">
                          <input
                            className={settingsFieldClassName}
                            value={breakRow.label}
                            onChange={(e) => updateBreakField(dayIndex, breakIndex, 'label', e.target.value)}
                            placeholder="Label (opsional)"
                            disabled={!canManage || !day.is_school_day}
                          />
                        </div>

                        <div className="md:col-span-1 flex items-center justify-end">
                          {canManage && (
                            <IconButton
                              size="small"
                              color="error"
                              onClick={() => removeBreak(dayIndex, breakIndex)}
                              disabled={!day.is_school_day}
                            >
                              <Trash2 className="w-4 h-4" />
                            </IconButton>
                          )}
                        </div>
                      </div>
                    ))}
                  </Paper>
                ))}
            </>
          )}
        </>
      )}

      <JadwalFormModal
        open={formOpen}
        saving={saving}
        mode={formMode}
        form={form}
        options={options}
        filteredOptions={jadwalFormFilteredOptions}
        mappingMeta={jadwalFormMappingMeta}
        daySlots={currentDaySlots}
        slotLoading={formSlotLoading}
        onClose={() => setFormOpen(false)}
        onChange={handleFormChange}
        onJamKeChange={handleJamKeChange}
        onSubmit={submit}
      />

      <ConfirmationModal
        open={confirmOpen}
        onClose={() => !saving && setConfirmOpen(false)}
        title="Hapus Jadwal"
        message={
          <>
            Hapus jadwal <strong>{selected?.hari_label || selected?.hari}</strong> ({selected?.time_range}) untuk
            kelas <strong>{selected?.kelas?.nama_kelas || '-'}</strong>?
          </>
        }
        onConfirm={remove}
        confirmText={saving ? 'Menghapus...' : 'Hapus'}
        type="delete"
      />

      <ExportModalAkademik
        isOpen={showExportModal}
        onClose={closeExportModal}
        onExport={handleExport}
        title="Export Jadwal Pelajaran"
        subtitle="Unduh laporan resmi jadwal pelajaran (Excel/PDF)"
        entityLabel="Jadwal Pelajaran"
        fields={JADWAL_EXPORT_FIELDS}
        progress={exportProgress}
      />

      <ImportModalAkademik
        isOpen={showImportModal}
        onClose={() => !isImporting && setShowImportModal(false)}
        onSuccess={handleImportSuccess}
        onImport={handleImport}
        onDownloadTemplate={handleDownloadTemplate}
        title="Import Jadwal Pelajaran"
        subtitle="Upload file Excel untuk menambah atau memperbarui jadwal pelajaran"
        templateLabel="Download Template Jadwal"
        progress={importProgress}
      />
    </div>
  );
};

export default JadwalPelajaran;

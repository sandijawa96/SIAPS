import { useState, useEffect, useMemo, useCallback } from 'react';
import { reportAPI, buildReportParams } from '../../../services/reportService';
import { academicContextAPI, kelasAPI, tingkatAPI } from '../../../services/api';
import { getServerDateString, toServerDateInput } from '../../../services/serverClock';
import useServerClock from '../../../hooks/useServerClock';
import { toast } from 'react-hot-toast';

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeStatus = (value) => String(value || '').trim().toLowerCase();
const isStudentRecapRow = (item) => (
  Boolean(item)
  && typeof item === 'object'
  && !Object.prototype.hasOwnProperty.call(item, 'status')
  && (
    Object.prototype.hasOwnProperty.call(item, 'total_records')
    || Object.prototype.hasOwnProperty.call(item, 'user_id')
  )
);

const normalizeCollectionPayload = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (payload && Array.isArray(payload.data)) return payload.data;
  return [];
};

const normalizeKelasOption = (kelas) => ({
  id: kelas?.id,
  nama: kelas?.namaKelas || kelas?.nama_kelas || kelas?.nama || '-',
  tingkat_id: kelas?.tingkat_id ?? null,
});

const normalizeTingkatOption = (tingkat) => ({
  id: tingkat?.id,
  nama: tingkat?.nama || tingkat?.kode || '-',
});

const toDetailRows = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (!payload || typeof payload !== 'object') return [];

  const values = Object.values(payload);
  if (values.every((item) => Array.isArray(item))) {
    return values.flat();
  }

  return [];
};

const computeThresholdExceeded = (minutes, percentage, minutesThreshold, percentageThreshold) => {
  const byMinutes = minutesThreshold > 0 && minutes >= minutesThreshold;
  const byPercentage = percentageThreshold > 0 && percentage >= percentageThreshold;
  return byMinutes || byPercentage;
};

const resolveThresholdExceeded = (payload, minutes, percentage, minutesThreshold, percentageThreshold) => {
  if (payload && Object.prototype.hasOwnProperty.call(payload, 'melewati_batas_pelanggaran')) {
    return Boolean(payload.melewati_batas_pelanggaran);
  }

  return computeThresholdExceeded(minutes, percentage, minutesThreshold, percentageThreshold);
};

const buildEmptyDisciplineThresholds = (mode = 'none') => ({
  mode,
  monthly_late: {
    minutes: 0,
    limit: 0,
    exceeded: false,
  },
  semester_total_violation: {
    minutes: 0,
    limit: 0,
    exceeded: false,
  },
  semester_alpha: {
    days: 0,
    limit: 0,
    exceeded: false,
    notify_wali_kelas: true,
    notify_kesiswaan: true,
  },
  attention_needed: false,
});

const normalizeDisciplineThresholds = (payload, fallbackMode = 'none') => {
  const source = payload && typeof payload === 'object' ? payload : {};
  const base = buildEmptyDisciplineThresholds(fallbackMode);

  return {
    ...base,
    ...source,
    mode: String(source.mode || fallbackMode || 'none'),
    monthly_late: {
      ...base.monthly_late,
      ...(source.monthly_late && typeof source.monthly_late === 'object' ? source.monthly_late : {}),
    },
    semester_total_violation: {
      ...base.semester_total_violation,
      ...(source.semester_total_violation && typeof source.semester_total_violation === 'object'
        ? source.semester_total_violation
        : {}),
    },
    semester_alpha: {
      ...base.semester_alpha,
      ...(source.semester_alpha && typeof source.semester_alpha === 'object' ? source.semester_alpha : {}),
    },
    attention_needed: Boolean(source.attention_needed),
  };
};

const resolveEffectiveThresholdLimits = (
  disciplineThresholds,
  fallbackMinutes = 0,
  fallbackPercentage = 0,
  fallbackMode = 'none'
) => {
  const normalized = normalizeDisciplineThresholds(disciplineThresholds, fallbackMode);

  if (normalized.mode === 'monthly') {
    return {
      minutes: toNumber(normalized.monthly_late?.limit),
      percentage: 0,
    };
  }

  if (normalized.mode === 'semester') {
    return {
      minutes: toNumber(normalized.semester_total_violation?.limit),
      percentage: 0,
    };
  }

  return {
    minutes: toNumber(fallbackMinutes),
    percentage: toNumber(fallbackPercentage),
  };
};

const resolveDisciplineStatus = (disciplineThresholds, fallbackExceeded = false) => {
  const normalized = normalizeDisciplineThresholds(disciplineThresholds);

  if (normalized.monthly_late.exceeded) {
    return {
      label: 'Terlambat Bulanan',
      tone: 'error',
    };
  }

  if (normalized.semester_total_violation.exceeded) {
    return {
      label: 'Pelanggaran Semester',
      tone: 'error',
    };
  }

  if (normalized.semester_alpha.exceeded) {
    return {
      label: 'Alpha Semester',
      tone: 'error',
    };
  }

  if (normalized.attention_needed || fallbackExceeded) {
    return {
      label: 'Perlu Perhatian',
      tone: 'warning',
    };
  }

  if (normalized.mode === 'none') {
    return {
      label: 'Monitoring',
      tone: 'success',
    };
  }

  return {
    label: 'Dalam Batas',
    tone: 'success',
  };
};

const extractMonthYear = (value) => {
  const dateString = toServerDateInput(value);
  const match = dateString.match(/^(\d{4})-(\d{2})-/);
  if (!match) {
    const fallbackMatch = getServerDateString().match(/^(\d{4})-(\d{2})-/);
    if (fallbackMatch) {
      return {
        month: Number(fallbackMatch[2]),
        year: Number(fallbackMatch[1]),
      };
    }

    return { month: 1, year: 2000 };
  }

  return {
    month: Number(match[2]),
    year: Number(match[1]),
  };
};

const padDatePart = (value) => String(value).padStart(2, '0');

const normalizeDateInput = (value) => {
  const normalized = toServerDateInput(value);
  if (typeof normalized === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
    return normalized;
  }
  return null;
};

const clampDateInput = (value, minDate = null, maxDate = null) => {
  const normalizedValue = normalizeDateInput(value) || getServerDateString();

  if (minDate && normalizedValue < minDate) {
    return minDate;
  }

  if (maxDate && normalizedValue > maxDate) {
    return maxDate;
  }

  return normalizedValue;
};

const clampRangeToBounds = (startDate, endDate, minDate = null, maxDate = null) => {
  let start = clampDateInput(startDate, minDate, maxDate);
  let end = clampDateInput(endDate, minDate, maxDate);

  if (end < start) {
    end = start;
  }

  return {
    start_date: start,
    end_date: end,
  };
};

const normalizeAcademicContext = (payload) => {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  const tahunAjaran = payload.tahun_ajaran && typeof payload.tahun_ajaran === 'object'
    ? payload.tahun_ajaran
    : {};
  const periodeAktif = payload.periode_aktif && typeof payload.periode_aktif === 'object'
    ? payload.periode_aktif
    : {};
  const effectiveRange = payload.effective_date_range && typeof payload.effective_date_range === 'object'
    ? payload.effective_date_range
    : {};

  const tahunAjaranId = Number(tahunAjaran.id);
  const tahunAjaranNama = String(tahunAjaran.nama || '').trim();
  const semesterRaw = String(periodeAktif.semester || periodeAktif.nama || '').trim().toLowerCase();
  const semesterLabel = semesterRaw.includes('genap')
    ? 'Genap'
    : semesterRaw.includes('ganjil')
      ? 'Ganjil'
      : '';
  const compactLabel = tahunAjaranNama && semesterLabel
    ? `${tahunAjaranNama} I ${semesterLabel}`
    : (tahunAjaranNama || '');

  const startDate = normalizeDateInput(effectiveRange.start_date || tahunAjaran.tanggal_mulai);
  const endDate = normalizeDateInput(effectiveRange.end_date || tahunAjaran.tanggal_selesai);

  return {
    tahunAjaranId: Number.isFinite(tahunAjaranId) && tahunAjaranId > 0 ? tahunAjaranId : null,
    compactLabel: compactLabel || null,
    startDate,
    endDate,
  };
};

const endOfMonthDate = (year, month) => {
  const safeYear = Number(year);
  const safeMonth = Number(month);
  const lastDay = new Date(safeYear, safeMonth, 0).getDate();
  return `${safeYear}-${padDatePart(safeMonth)}-${padDatePart(lastDay)}`;
};

const addDaysDate = (value, days) => {
  const normalized = normalizeDateInput(value) || getServerDateString();
  const [year, month, day] = normalized.split('-').map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  date.setUTCDate(date.getUTCDate() + Number(days || 0));

  return [
    date.getUTCFullYear(),
    padDatePart(date.getUTCMonth() + 1),
    padDatePart(date.getUTCDate()),
  ].join('-');
};

const resolveSemesterDateRange = (value) => {
  const { year, semester } = resolveSemesterFromDate(value);
  const startMonth = semester === 1 ? 1 : 7;
  const endMonth = semester === 1 ? 6 : 12;

  return {
    start_date: `${year}-${padDatePart(startMonth)}-01`,
    end_date: endOfMonthDate(year, endMonth),
  };
};

const resolveSemesterFromDate = (value) => {
  const { month, year } = extractMonthYear(value);
  return {
    year,
    semester: month >= 7 ? 2 : 1,
  };
};

const aggregateYearlySummary = (rows) => {
  const safeRows = Array.isArray(rows) ? rows : [];

  const summary = safeRows.reduce(
    (acc, row) => {
      const totalHariKerja = toNumber(row?.total_hari_kerja);
      const workingMinutesPerDay = toNumber(row?.working_minutes_per_day);
      const totalMenitKerja = toNumber(row?.total_menit_kerja, totalHariKerja * workingMinutesPerDay);
      const totalPelanggaranMenit = toNumber(row?.total_pelanggaran_menit);

      acc.total_hari_kerja += totalHariKerja;
      acc.total_hadir += toNumber(row?.total_hadir);
      acc.total_izin += toNumber(row?.total_izin);
      acc.total_sakit += toNumber(row?.total_sakit);
      acc.total_terlambat += toNumber(row?.total_terlambat);
      acc.total_terlambat_menit += toNumber(row?.total_terlambat_menit);
      acc.total_tap_hari += toNumber(row?.total_tap_hari ?? row?.tap_hari);
      acc.total_tap_menit += toNumber(row?.total_tap_menit);
      acc.total_belum_absen += toNumber(row?.total_belum_absen ?? row?.belum_absen);
      acc.total_alpha += toNumber(row?.total_alpha);
      acc.total_alpha_menit += toNumber(row?.total_alpha_menit ?? row?.total_alpa_menit);
      acc.total_pelanggaran_menit += totalPelanggaranMenit;
      acc.total_menit_kerja += totalMenitKerja;

      return acc;
    },
    {
      total_hari_kerja: 0,
      total_hadir: 0,
      total_izin: 0,
      total_sakit: 0,
      total_terlambat: 0,
      total_terlambat_menit: 0,
      total_tap_hari: 0,
      total_tap_menit: 0,
      total_belum_absen: 0,
      total_alpha: 0,
      total_alpha_menit: 0,
      total_pelanggaran_menit: 0,
      total_menit_kerja: 0,
    }
  );

  const persentasePelanggaran = summary.total_menit_kerja > 0
    ? Number(((summary.total_pelanggaran_menit / summary.total_menit_kerja) * 100).toFixed(2))
    : 0;

  const baseline = safeRows.find((row) => row && typeof row === 'object') || {};
  const disciplineThresholds = normalizeDisciplineThresholds(
    baseline?.discipline_thresholds,
    'none'
  );
  const effectiveLimits = resolveEffectiveThresholdLimits(
    disciplineThresholds,
    baseline?.batas_pelanggaran_menit,
    baseline?.batas_pelanggaran_persen
  );

  return {
    ...summary,
    persentase_pelanggaran: persentasePelanggaran,
    batas_pelanggaran_menit: effectiveLimits.minutes,
    batas_pelanggaran_persen: effectiveLimits.percentage,
    discipline_thresholds: disciplineThresholds,
    melewati_batas_pelanggaran: computeThresholdExceeded(
      summary.total_pelanggaran_menit,
      persentasePelanggaran,
      effectiveLimits.minutes,
      effectiveLimits.percentage
    ),
  };
};

const useLaporanStatistik = () => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [loading, setLoading] = useState(true);
  const [academicContext, setAcademicContext] = useState(null);
  const [periode, setPeriode] = useState('hari');
  const [tanggalMulai, setTanggalMulai] = useState('');
  const [tanggalSelesai, setTanggalSelesai] = useState('');
  const [selectedTingkat, setSelectedTingkat] = useState('Semua');
  const [selectedStatus, setSelectedStatus] = useState('Semua');
  const [selectedDisciplineStatus, setSelectedDisciplineStatus] = useState('Semua');
  const [selectedKelas, setSelectedKelas] = useState('Semua');
  const [reportPage, setReportPage] = useState(1);
  const [reportPerPage, setReportPerPage] = useState(25);
  const [reportPagination, setReportPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: 0,
    to: 0,
  });
  
  // Data states
  const [laporanData, setLaporanData] = useState([]);
  const [rawStatistics, setRawStatistics] = useState(null);
  const [availableTingkat, setAvailableTingkat] = useState([]);
  const [availableKelas, setAvailableKelas] = useState([]);
  const [error, setError] = useState(null);

  const academicBounds = useMemo(() => ({
    minDate: academicContext?.startDate || null,
    maxDate: academicContext?.endDate || null,
  }), [academicContext]);

  useEffect(() => {
    if (!isServerClockSynced || !serverDate) {
      return;
    }

    setTanggalMulai((current) => current || serverDate);
    setTanggalSelesai((current) => current || serverDate);
  }, [isServerClockSynced, serverDate]);

  const resolveEffectiveExportRange = useCallback(() => {
    const start = toServerDateInput(tanggalMulai) || getServerDateString();
    const end = toServerDateInput(tanggalSelesai) || start;

    if (periode === 'bulan') {
      const { month, year } = extractMonthYear(start);
      return clampRangeToBounds(
        `${year}-${padDatePart(month)}-01`,
        endOfMonthDate(year, month),
        academicBounds.minDate,
        academicBounds.maxDate
      );
    }

    if (periode === 'semester') {
      const { year, semester } = resolveSemesterFromDate(start);
      const startMonth = semester === 1 ? 1 : 7;
      const endMonth = semester === 1 ? 6 : 12;
      return clampRangeToBounds(
        `${year}-${padDatePart(startMonth)}-01`,
        endOfMonthDate(year, endMonth),
        academicBounds.minDate,
        academicBounds.maxDate
      );
    }

    return clampRangeToBounds(
      start,
      end,
      academicBounds.minDate,
      academicBounds.maxDate
    );
  }, [academicBounds.maxDate, academicBounds.minDate, periode, tanggalMulai, tanggalSelesai]);

  const resolveEffectiveReportRange = useCallback(() => {
    const start = toServerDateInput(tanggalMulai) || getServerDateString();
    const end = toServerDateInput(tanggalSelesai) || start;

    if (periode === 'bulan') {
      const startParts = extractMonthYear(start);

      return clampRangeToBounds(
        `${startParts.year}-${padDatePart(startParts.month)}-01`,
        endOfMonthDate(startParts.year, startParts.month),
        academicBounds.minDate,
        academicBounds.maxDate
      );
    }

    if (periode === 'minggu') {
      return clampRangeToBounds(
        start,
        end,
        academicBounds.minDate,
        academicBounds.maxDate
      );
    }

    return clampRangeToBounds(
      start,
      end,
      academicBounds.minDate,
      academicBounds.maxDate
    );
  }, [academicBounds.maxDate, academicBounds.minDate, periode, tanggalMulai, tanggalSelesai]);

  useEffect(() => {
    let mounted = true;

    const fetchAcademicContext = async () => {
      try {
        const response = await academicContextAPI.getCurrent();
        if (!mounted) return;

        const normalized = normalizeAcademicContext(response?.data?.data);
        setAcademicContext(normalized);

        if (!normalized) {
          return;
        }

        setTanggalMulai((prev) => clampDateInput(prev, normalized.startDate, normalized.endDate));
        setTanggalSelesai((prev) => clampDateInput(prev, normalized.startDate, normalized.endDate));
      } catch (contextError) {
        if (!mounted) return;
        setAcademicContext(null);
        console.error('Error fetching academic context for reports:', contextError);
      }
    };

    fetchAcademicContext();

    return () => {
      mounted = false;
    };
  }, []);

  // Fetch available tingkat for filter
  useEffect(() => {
    const fetchTingkat = async () => {
      try {
        const response = await tingkatAPI.getAll({ is_active: true });
        const tingkatOptions = normalizeCollectionPayload(response?.data).map(normalizeTingkatOption);
        setAvailableTingkat(tingkatOptions);
      } catch (error) {
        console.error('Error fetching tingkat:', error);
      }
    };

    fetchTingkat();
  }, []);

  // Fetch available kelas for filter (dependent on selected tingkat)
  useEffect(() => {
    let mounted = true;

    const fetchKelas = async () => {
      if (selectedTingkat === 'Semua') {
        setAvailableKelas([]);
        setSelectedKelas('Semua');
        return;
      }

      try {
        const params = {
          tingkat_id: selectedTingkat,
          ...(academicContext?.tahunAjaranId ? { tahun_ajaran_id: academicContext.tahunAjaranId } : {}),
        };
        const response = await kelasAPI.getAll(params);

        const kelasOptions = normalizeCollectionPayload(response?.data).map(normalizeKelasOption);
        if (!mounted) return;

        setAvailableKelas(kelasOptions);
        setSelectedKelas((current) => {
          if (current === 'Semua') return current;
          const stillExists = kelasOptions.some((kelas) => String(kelas.id) === String(current));
          return stillExists ? current : 'Semua';
        });
      } catch (error) {
        console.error('Error fetching kelas:', error);
        if (!mounted) return;
        setAvailableKelas([]);
        setSelectedKelas('Semua');
      }
    };

    fetchKelas();

    return () => {
      mounted = false;
    };
  }, [academicContext?.tahunAjaranId, selectedTingkat]);

  useEffect(() => {
    const start = clampDateInput(tanggalMulai, academicBounds.minDate, academicBounds.maxDate);
    if (start !== tanggalMulai) {
      setTanggalMulai(start);
      return;
    }

    let nextEnd = tanggalSelesai;
    if (periode === 'hari') {
      nextEnd = start;
    } else if (periode === 'minggu') {
      nextEnd = clampDateInput(addDaysDate(start, 6), start, academicBounds.maxDate);
    } else if (periode === 'bulan') {
      const { month, year } = extractMonthYear(start);
      nextEnd = clampDateInput(endOfMonthDate(year, month), start, academicBounds.maxDate);
    } else if (periode === 'semester') {
      const semesterRange = resolveSemesterDateRange(start);
      const clamped = clampRangeToBounds(
        semesterRange.start_date,
        semesterRange.end_date,
        academicBounds.minDate,
        academicBounds.maxDate
      );
      nextEnd = clamped.end_date;
    }

    if (nextEnd !== tanggalSelesai) {
      setTanggalSelesai(nextEnd);
    }
  // Do not include tanggalSelesai: weekly end-date must remain manually editable until start/period changes.
  }, [academicBounds.maxDate, academicBounds.minDate, periode, tanggalMulai]);

  // Calculate statistics from raw data
  const statistics = useMemo(() => {
    if (!rawStatistics) {
      return {
        totalHadir: 0,
        totalTerlambat: 0,
        totalIzin: 0,
        totalBelumAbsen: 0,
        totalTapHari: 0,
        totalTapMenit: 0,
        totalAlpha: 0,
        avgKehadiran: '0.0',
        totalPelanggaranMenit: 0,
        persentasePelanggaran: '0.00',
        batasPelanggaranMenit: 0,
        batasPelanggaranPersen: 0,
        melewatiBatasPelanggaran: false,
        jumlahSiswaMelewatiBatas: 0,
        jumlahSiswaMelewatiBatasKeterlambatanBulanan: 0,
        jumlahSiswaMelewatiBatasAlphaSemester: 0,
        disciplineThresholds: buildEmptyDisciplineThresholds(
          periode === 'bulan' ? 'monthly' : periode === 'semester' ? 'semester' : 'none'
        ),
      };
    }

    const summary = Array.isArray(rawStatistics)
      ? aggregateYearlySummary(rawStatistics)
      : (rawStatistics.summary || {});
    const disciplineThresholds = normalizeDisciplineThresholds(
      summary.discipline_thresholds,
      periode === 'bulan' ? 'monthly' : periode === 'semester' ? 'semester' : 'none'
    );

    const totalHadir = toNumber(summary.total_hadir ?? summary.hadir);
    const totalTerlambat = toNumber(summary.total_terlambat ?? summary.terlambat);
    const totalIzin = toNumber(summary.total_izin ?? summary.izin);
    const totalBelumAbsen = toNumber(summary.total_belum_absen ?? summary.belum_absen);
    const totalTapHari = toNumber(summary.total_tap_hari ?? summary.tap_hari);
    const totalTapMenit = toNumber(summary.total_tap_menit ?? summary.tap_menit);
    const totalAlpha = toNumber(summary.total_alpha ?? summary.alpha);
    const denominator = toNumber(summary.total_hari_kerja ?? summary.total);
    const avgKehadiran = denominator > 0
      ? ((totalHadir / denominator) * 100).toFixed(1)
      : '0.0';

    const totalPelanggaranMenit = toNumber(summary.total_pelanggaran_menit);
    const persentasePelanggaran = toNumber(summary.persentase_pelanggaran).toFixed(2);
    const effectiveLimits = resolveEffectiveThresholdLimits(
      disciplineThresholds,
      summary.batas_pelanggaran_menit,
      summary.batas_pelanggaran_persen,
      periode === 'bulan' ? 'monthly' : periode === 'semester' ? 'semester' : 'none'
    );
    const batasPelanggaranMenit = effectiveLimits.minutes;
    const batasPelanggaranPersen = effectiveLimits.percentage;
    const jumlahSiswaMelewatiBatas = toNumber(summary.jumlah_siswa_melewati_batas_pelanggaran);
    const jumlahSiswaMelewatiBatasKeterlambatanBulanan = toNumber(
      summary.jumlah_siswa_melewati_batas_keterlambatan_bulanan
    );
    const jumlahSiswaMelewatiBatasAlphaSemester = toNumber(
      summary.jumlah_siswa_melewati_batas_alpha_semester
    );
    const melewatiBatasPelanggaran = resolveThresholdExceeded(
      summary,
      totalPelanggaranMenit,
      toNumber(persentasePelanggaran),
      batasPelanggaranMenit,
      batasPelanggaranPersen
    );

    return {
      totalHadir,
      totalTerlambat,
      totalIzin,
      totalBelumAbsen,
      totalTapHari,
      totalTapMenit,
      totalAlpha,
      avgKehadiran,
      totalPelanggaranMenit,
      persentasePelanggaran,
      batasPelanggaranMenit,
      batasPelanggaranPersen,
      melewatiBatasPelanggaran,
      jumlahSiswaMelewatiBatas,
      jumlahSiswaMelewatiBatasKeterlambatanBulanan,
      jumlahSiswaMelewatiBatasAlphaSemester,
      disciplineThresholds,
    };
  }, [periode, rawStatistics]);

  // Transform API data to table format
  const transformDataForTable = useCallback((apiData, fallbackWorkingMinutesPerDay = 480, thresholdMode = 'none') => {
    if (!apiData) return [];

    const asArray = Array.isArray(apiData) ? apiData : [];
    const isYearlyRows = asArray.length > 0 && asArray[0] && Object.prototype.hasOwnProperty.call(asArray[0], 'bulan');

    if (isYearlyRows) {
      return asArray.map((item) => {
        const workingMinutesPerDay = toNumber(item.working_minutes_per_day, fallbackWorkingMinutesPerDay);
        const totalHariKerja = toNumber(item.total_hari_kerja);
        const totalMenitKerja = toNumber(item.total_menit_kerja, totalHariKerja * workingMinutesPerDay);
        const totalPelanggaranMenit = toNumber(item.total_pelanggaran_menit);
        const persentasePelanggaran = totalMenitKerja > 0
          ? Number(((totalPelanggaranMenit / totalMenitKerja) * 100).toFixed(2))
          : 0;
        const disciplineThresholds = normalizeDisciplineThresholds(
          item.discipline_thresholds,
          thresholdMode
        );
        const effectiveLimits = resolveEffectiveThresholdLimits(
          disciplineThresholds,
          item.batas_pelanggaran_menit,
          item.batas_pelanggaran_persen,
          thresholdMode
        );
        const thresholdStatus = resolveDisciplineStatus(
          disciplineThresholds,
          resolveThresholdExceeded(
            item,
            totalPelanggaranMenit,
            persentasePelanggaran,
            effectiveLimits.minutes,
            effectiveLimits.percentage
          )
        );

        return {
          nama: `Rekap ${item.bulan || '-'}`,
          kelas: 'Semua Kelas',
          hadir: toNumber(item.total_hadir),
          terlambat: toNumber(item.total_terlambat),
          izin: toNumber(item.total_izin),
          belumAbsen: toNumber(item.total_belum_absen ?? item.belum_absen),
          alpha: toNumber(item.total_alpha),
          persentaseKehadiran: totalHariKerja > 0
            ? Number(((toNumber(item.total_hadir) / totalHariKerja) * 100).toFixed(1))
            : 0,
          terlambatMenit: toNumber(item.total_terlambat_menit),
          tapMenit: toNumber(item.total_tap_menit),
          alpaMenit: toNumber(item.total_alpha_menit ?? item.total_alpa_menit),
          totalPelanggaranMenit,
          persentasePelanggaran,
          batasPelanggaranMenit: effectiveLimits.minutes,
          batasPelanggaranPersen: effectiveLimits.percentage,
          melewatiBatasPelanggaran: resolveThresholdExceeded(
            item,
            totalPelanggaranMenit,
            persentasePelanggaran,
            effectiveLimits.minutes,
            effectiveLimits.percentage
          ),
          disciplineThresholds,
          thresholdStatusLabel: thresholdStatus.label,
          thresholdStatusTone: thresholdStatus.tone,
        };
      });
    }

    const rows = toDetailRows(apiData);

    return rows.map((item) => {
      if (isStudentRecapRow(item)) {
        const hadir = toNumber(item.hadir);
        const izin = toNumber(item.izin);
        const sakit = toNumber(item.sakit);
        const alpha = toNumber(item.alpha);
        const terlambat = toNumber(item.terlambat);
        const belumAbsen = toNumber(item.belum_absen ?? item.total_belum_absen);
        const totalRecords = toNumber(item.total_records, hadir + izin + sakit + alpha);

        const workingMinutesPerDay = toNumber(item.working_minutes_per_day, fallbackWorkingMinutesPerDay);
        const terlambatMenit = toNumber(item.terlambat_menit ?? item.late_minutes ?? item.menit_terlambat);
        const tapMenit = toNumber(item.tap_menit ?? item.tap_minutes);
        const alpaMenit = toNumber(item.alpa_menit ?? item.alpha_menit ?? item.total_alpha_menit);
        const totalPelanggaranMenit = toNumber(
          item.total_pelanggaran_menit,
          terlambatMenit + tapMenit + alpaMenit
        );
        const persentasePelanggaran = toNumber(
          item.persentase_pelanggaran,
          workingMinutesPerDay > 0 ? Number(((totalPelanggaranMenit / workingMinutesPerDay) * 100).toFixed(2)) : 0
        );
        const persentaseKehadiran = toNumber(
          item.persentase_kehadiran,
          totalRecords > 0 ? Number(((hadir / totalRecords) * 100).toFixed(1)) : 0
        );
        const batasPelanggaranMenit = toNumber(item.batas_pelanggaran_menit);
        const batasPelanggaranPersen = toNumber(item.batas_pelanggaran_persen);
        const disciplineThresholds = normalizeDisciplineThresholds(
          item.discipline_thresholds,
          thresholdMode
        );
        const effectiveLimits = resolveEffectiveThresholdLimits(
          disciplineThresholds,
          batasPelanggaranMenit,
          batasPelanggaranPersen,
          thresholdMode
        );
        const thresholdExceeded = resolveThresholdExceeded(
          item,
          totalPelanggaranMenit,
          persentasePelanggaran,
          effectiveLimits.minutes,
          effectiveLimits.percentage
        );
        const thresholdStatus = resolveDisciplineStatus(
          disciplineThresholds,
          thresholdExceeded
        );

        return {
          nama: item.nama_lengkap || item.nama || 'Unknown',
          kelas: item.kelas_nama || item.kelas || '-',
          hadir,
          terlambat,
          izin,
          belumAbsen,
          alpha,
          persentaseKehadiran,
          terlambatMenit,
          tapMenit,
          alpaMenit,
          totalPelanggaranMenit,
          persentasePelanggaran,
          batasPelanggaranMenit: effectiveLimits.minutes,
          batasPelanggaranPersen: effectiveLimits.percentage,
          melewatiBatasPelanggaran: thresholdExceeded,
          disciplineThresholds,
          thresholdStatusLabel: thresholdStatus.label,
          thresholdStatusTone: thresholdStatus.tone,
        };
      }

      const user = item.user || {};
      const status = normalizeStatus(item.status);
      const workingMinutesPerDay = toNumber(item.working_minutes_per_day, fallbackWorkingMinutesPerDay);
      const kelasName = item.kelas?.nama_kelas
        || item.kelas?.nama
        || user.kelas?.nama_kelas
        || user.kelas?.nama
        || item.kelas_nama
        || '-';

      let terlambatMenit = toNumber(item.terlambat_menit ?? item.late_minutes ?? item.menit_terlambat);
      let tapMenit = toNumber(item.tap_menit ?? item.tap_minutes);
      let alpaMenit = toNumber(item.alpa_menit ?? item.alpha_menit ?? item.total_alpha_menit);

      if (tapMenit === 0 && item.jam_masuk && !item.jam_pulang && status !== 'alpha') {
        tapMenit = Math.round(workingMinutesPerDay * 0.5);
      }
      if (alpaMenit === 0 && status === 'alpha') {
        alpaMenit = workingMinutesPerDay;
      }

      const totalPelanggaranMenit = toNumber(
        item.total_pelanggaran_menit,
        terlambatMenit + tapMenit + alpaMenit
      );
      const persentasePelanggaran = toNumber(
        item.persentase_pelanggaran,
        workingMinutesPerDay > 0 ? Number(((totalPelanggaranMenit / workingMinutesPerDay) * 100).toFixed(2)) : 0
      );
      const disciplineThresholds = normalizeDisciplineThresholds(
        item.discipline_thresholds,
        thresholdMode
      );
      const effectiveLimits = resolveEffectiveThresholdLimits(
        disciplineThresholds,
        item.batas_pelanggaran_menit,
        item.batas_pelanggaran_persen,
        thresholdMode
      );
      const thresholdExceeded = resolveThresholdExceeded(
        item,
        totalPelanggaranMenit,
        persentasePelanggaran,
        effectiveLimits.minutes,
        effectiveLimits.percentage
      );
      const thresholdStatus = resolveDisciplineStatus(
        disciplineThresholds,
        thresholdExceeded
      );

      return {
        nama: user.nama_lengkap || user.name || item.nama_lengkap || item.nama || 'Unknown',
        kelas: kelasName,
        hadir: ['hadir', 'terlambat'].includes(status) ? 1 : 0,
        terlambat: (status === 'terlambat' || terlambatMenit > 0) ? 1 : 0,
        izin: status === 'izin' ? 1 : 0,
        alpha: status === 'alpha' ? 1 : 0,
        belumAbsen: status === 'belum_absen' ? 1 : toNumber(item.belum_absen ?? item.total_belum_absen),
        persentaseKehadiran: ['hadir', 'terlambat'].includes(status) ? 100 : 0,
        terlambatMenit,
        tapMenit,
        alpaMenit,
        totalPelanggaranMenit,
        persentasePelanggaran,
        batasPelanggaranMenit: effectiveLimits.minutes,
        batasPelanggaranPersen: effectiveLimits.percentage,
        melewatiBatasPelanggaran: thresholdExceeded,
        disciplineThresholds,
        thresholdStatusLabel: thresholdStatus.label,
        thresholdStatusTone: thresholdStatus.tone,
      };
    });
  }, []);

  // Fetch data from API
  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const effectiveRange = resolveEffectiveReportRange();

      const filters = {
        tanggalMulai: effectiveRange.start_date,
        tanggalSelesai: effectiveRange.end_date,
        selectedTingkat,
        selectedStatus,
        selectedDisciplineStatus,
        selectedKelas,
        tanggal: effectiveRange.start_date, // For daily report
      };

      const params = buildReportParams(filters);
      params.page = reportPage;
      params.per_page = reportPerPage;
      params.view = 'student_recap';
      if (academicContext?.tahunAjaranId) {
        params.tahun_ajaran_id = academicContext.tahunAjaranId;
      }

      let response;
      
      // Choose API endpoint based on periode
      switch (periode) {
        case 'hari':
          response = await reportAPI.getDailyReport(params);
          break;
        case 'minggu': {
          response = await reportAPI.getRangeReport({
            ...params,
            start_date: effectiveRange.start_date,
            end_date: effectiveRange.end_date,
          });
          break;
        }
        case 'bulan': {
          const { month: bulan, year: tahun } = extractMonthYear(effectiveRange.start_date);
          response = await reportAPI.getMonthlyReport({
            ...params,
            bulan,
            tahun,
          });
          break;
        }
        case 'semester': {
          const semesterRange = resolveEffectiveExportRange();
          const { year: tahunSemester, semester } = resolveSemesterFromDate(semesterRange.start_date);
          response = await reportAPI.getSemesterReport({ ...params, tahun: tahunSemester, semester });
          break;
        }
        default:
          response = await reportAPI.getDailyReport(params);
      }

      if (response.data.success) {
        const { data } = response.data;
        setRawStatistics(data);
        
        // Transform detail data for table
        const detailPayload = Array.isArray(data) ? data : (data.detail || []);
        const fallbackWorkingMinutesPerDay = Array.isArray(data)
          ? toNumber(data.find((item) => item?.working_minutes_per_day)?.working_minutes_per_day, 480)
          : toNumber(data?.summary?.working_minutes_per_day, 480);
        const thresholdMode = periode === 'bulan'
          ? 'monthly'
          : periode === 'semester'
            ? 'semester'
            : 'none';
        const tableData = transformDataForTable(detailPayload, fallbackWorkingMinutesPerDay, thresholdMode);
        setLaporanData(tableData);

        if (data?.pagination) {
          setReportPagination({
            current_page: Number(data.pagination.current_page || reportPage),
            last_page: Number(data.pagination.last_page || 1),
            per_page: Number(data.pagination.per_page || reportPerPage),
            total: Number(data.pagination.total || tableData.length),
            from: Number(data.pagination.from || (tableData.length ? 1 : 0)),
            to: Number(data.pagination.to || tableData.length),
          });
        } else {
          setReportPagination({
            current_page: 1,
            last_page: 1,
            per_page: tableData.length || reportPerPage,
            total: tableData.length,
            from: tableData.length ? 1 : 0,
            to: tableData.length,
          });
        }
      } else {
        throw new Error(response.data.message || 'Failed to fetch data');
      }
    } catch (error) {
      console.error('Error fetching report data:', error);
      setError(error.message || 'Terjadi kesalahan saat mengambil data');
      toast.error('Gagal mengambil data laporan');
      
      // Set empty data on error
      setLaporanData([]);
      setRawStatistics(null);
      setReportPagination({
        current_page: 1,
        last_page: 1,
        per_page: reportPerPage,
        total: 0,
        from: 0,
        to: 0,
      });
    } finally {
      setLoading(false);
    }
  }, [academicContext?.tahunAjaranId, periode, reportPage, reportPerPage, resolveEffectiveExportRange, resolveEffectiveReportRange, selectedDisciplineStatus, selectedKelas, selectedStatus, selectedTingkat, transformDataForTable]);

  useEffect(() => {
    setReportPage(1);
  }, [periode, tanggalMulai, tanggalSelesai, selectedTingkat, selectedStatus, selectedDisciplineStatus, selectedKelas]);

  // Fetch data when dependencies change
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Generate report handler
  const handleGenerateReport = useCallback(() => {
    fetchData();
    toast.success('Laporan berhasil di-generate');
  }, [fetchData]);

  // Export to Excel handler
  const handleExportExcel = useCallback(async () => {
    try {
      const effectiveRange = resolveEffectiveExportRange();
      const filters = {
        tanggalMulai: effectiveRange.start_date,
        tanggalSelesai: effectiveRange.end_date,
        selectedTingkat,
        selectedStatus,
        selectedDisciplineStatus,
        selectedKelas
      };

      const params = {
        ...buildReportParams(filters),
        format: 'xlsx',
        view: 'student_recap',
        ...(academicContext?.tahunAjaranId ? { tahun_ajaran_id: academicContext.tahunAjaranId } : {}),
      };

      const response = await reportAPI.exportExcel(params);
      
      // Create blob and download
      const blob = new Blob([response.data], { 
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
      });
      
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `laporan-kehadiran-${effectiveRange.start_date}-${effectiveRange.end_date}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      toast.success('File Excel berhasil diunduh');
    } catch (error) {
      console.error('Error exporting to Excel:', error);
      toast.error('Gagal mengekspor ke Excel');
    }
  }, [academicContext?.tahunAjaranId, resolveEffectiveExportRange, selectedDisciplineStatus, selectedKelas, selectedStatus, selectedTingkat]);

  // Export to PDF handler
  const handleExportPDF = useCallback(async () => {
    try {
      const effectiveRange = resolveEffectiveExportRange();
      const filters = {
        tanggalMulai: effectiveRange.start_date,
        tanggalSelesai: effectiveRange.end_date,
        selectedTingkat,
        selectedStatus,
        selectedDisciplineStatus,
        selectedKelas
      };

      const params = {
        ...buildReportParams(filters),
        view: 'student_recap',
        ...(academicContext?.tahunAjaranId ? { tahun_ajaran_id: academicContext.tahunAjaranId } : {}),
      };

      const response = await reportAPI.exportPdf(params);
      
      // Create blob and download
      const blob = new Blob([response.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `laporan-kehadiran-${effectiveRange.start_date}-${effectiveRange.end_date}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      toast.success('File PDF berhasil diunduh');
    } catch (error) {
      console.error('Error exporting to PDF:', error);
      toast.error('Gagal mengekspor ke PDF');
    }
  }, [academicContext?.tahunAjaranId, resolveEffectiveExportRange, selectedDisciplineStatus, selectedKelas, selectedStatus, selectedTingkat]);

  return {
    loading,
    error,
    periode,
    setPeriode,
    tanggalMulai,
    setTanggalMulai,
    tanggalSelesai,
    setTanggalSelesai,
    selectedTingkat,
    setSelectedTingkat,
    selectedStatus,
    setSelectedStatus,
    selectedDisciplineStatus,
    setSelectedDisciplineStatus,
    selectedKelas,
    setSelectedKelas,
    reportPage,
    setReportPage,
    reportPerPage,
    setReportPerPage,
    reportPagination,
    laporanData,
    statistics,
    academicContextLabel: academicContext?.compactLabel || null,
    academicMinDate: academicBounds.minDate,
    academicMaxDate: academicBounds.maxDate,
    availableTingkat,
    availableKelas,
    handleGenerateReport,
    handleExportExcel,
    handleExportPDF,
    refetch: fetchData
  };
};

export default useLaporanStatistik;

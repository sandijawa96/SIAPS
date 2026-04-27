import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  FileText,
  RefreshCw,
  ShieldAlert,
  Smartphone,
  XCircle,
} from 'lucide-react';
import { absensiAPI } from '../services/api';
import { formatServerDate, getServerNowDate } from '../services/serverClock';

const STATUS_STYLES = {
  hadir: 'border-green-200 bg-green-50 text-green-700',
  terlambat: 'border-amber-200 bg-amber-50 text-amber-700',
  izin: 'border-blue-200 bg-blue-50 text-blue-700',
  sakit: 'border-cyan-200 bg-cyan-50 text-cyan-700',
  alpha: 'border-red-200 bg-red-50 text-red-700',
  libur: 'border-slate-200 bg-slate-100 text-slate-600',
};

const MONTH_LABELS = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];

const formatNumber = (value) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return '0';
  }

  return new Intl.NumberFormat('id-ID').format(parsed);
};

const formatPercentage = (value) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return '0%';
  }

  return `${new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: parsed % 1 === 0 ? 0 : 2,
  }).format(parsed)}%`;
};

const normalizeStatusKey = (status) => {
  const normalized = String(status || '').trim().toLowerCase();
  return normalized === 'alpa' ? 'alpha' : normalized;
};

const formatDuration = (item) =>
  item?.durasi_sekolah_format
  || item?.durasi_kerja_format
  || item?.durasi_kerja
  || '-';

const RecapStatCard = ({ icon: Icon, title, value, subtitle, tone = 'slate' }) => {
  const toneClasses = {
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
    green: 'border-green-200 bg-green-50 text-green-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    cyan: 'border-cyan-200 bg-cyan-50 text-cyan-700',
    red: 'border-red-200 bg-red-50 text-red-700',
    slate: 'border-slate-200 bg-slate-50 text-slate-700',
  };

  return (
    <div className={`rounded-xl border p-4 shadow-sm ${toneClasses[tone] || toneClasses.slate}`}>
      <div className="mb-3 flex items-center justify-between">
        <span className="text-sm font-medium">{title}</span>
        <Icon className="h-5 w-5" />
      </div>
      <div className="text-2xl font-semibold">{value}</div>
      <div className="mt-1 text-xs opacity-80">{subtitle}</div>
    </div>
  );
};

const RekapKehadiranSaya = () => {
  const now = getServerNowDate();
  const [selectedMonth, setSelectedMonth] = useState(now.getMonth() + 1);
  const [selectedYear, setSelectedYear] = useState(now.getFullYear());
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [statistics, setStatistics] = useState({});
  const [historyItems, setHistoryItems] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: 0,
    to: 0,
  });

  const yearOptions = useMemo(() => {
    const currentYear = now.getFullYear();
    return Array.from({ length: 8 }, (_, index) => currentYear - 6 + index).reverse();
  }, [now]);

  const activeMonthLabel = MONTH_LABELS[selectedMonth - 1] || `Bulan ${selectedMonth}`;
  const period = statistics?.period ?? {};
  const schoolDaysInMonth = Number(statistics?.total_hari_sekolah_bulan ?? statistics?.school_days_in_month ?? 0);
  const elapsedSchoolDays = Number(statistics?.total_hari_sekolah_berjalan ?? statistics?.elapsed_school_days ?? statistics?.total_hari_kerja ?? 0);
  const unrecordedDays = Number(statistics?.total_hari_tanpa_catatan ?? statistics?.unrecorded_days ?? 0);
  const totalSchoolMinutes = Number(statistics?.total_menit_sekolah ?? statistics?.total_menit_kerja ?? 0);
  const lateDays = Number(statistics?.late_days ?? statistics?.total_terlambat ?? 0);
  const lateMinutes = Number(statistics?.late_minutes ?? statistics?.total_terlambat_menit ?? 0);
  const tapDays = Number(statistics?.tap_days ?? statistics?.total_tap_hari ?? 0);
  const tapMinutes = Number(statistics?.total_tap_menit ?? 0);
  const izinDays = Number(statistics?.total_izin ?? 0);
  const sakitDays = Number(statistics?.total_sakit ?? 0);
  const evaluationEndLabel = period?.evaluation_end
    ? formatServerDate(period.evaluation_end, 'id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
    : null;

  const fetchRecap = useCallback(async () => {
    try {
      setIsLoading(true);
      setError('');

      const [statisticsResponse, historyResponse] = await Promise.all([
        absensiAPI.getStatistics({
          month: selectedMonth,
          year: selectedYear,
        }),
        absensiAPI.getHistory({
          month: selectedMonth,
          year: selectedYear,
          page,
          per_page: 12,
        }),
      ]);

      const statisticsPayload = statisticsResponse?.data?.data ?? {};
      const historyPayload = historyResponse?.data?.data ?? {};

      setStatistics(statisticsPayload);
      setHistoryItems(Array.isArray(historyPayload?.data) ? historyPayload.data : []);
      setPagination({
        current_page: Number(historyPayload?.current_page || page),
        last_page: Number(historyPayload?.last_page || 1),
        per_page: Number(historyPayload?.per_page || 12),
        total: Number(historyPayload?.total || 0),
        from: Number(historyPayload?.from || 0),
        to: Number(historyPayload?.to || 0),
      });
    } catch (caughtError) {
      setError(
        caughtError?.response?.data?.message
        || caughtError?.message
        || 'Gagal memuat rekap kehadiran'
      );
      setHistoryItems([]);
      setStatistics({});
      setPagination({
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: 0,
        from: 0,
        to: 0,
      });
    } finally {
      setIsLoading(false);
    }
  }, [page, selectedMonth, selectedYear]);

  useEffect(() => {
    fetchRecap();
  }, [fetchRecap]);

  const summaryCards = [
    {
      title: 'Persentase Hadir',
      value: formatPercentage(statistics?.attendance_percentage),
      subtitle: elapsedSchoolDays > 0
        ? `${formatNumber(statistics?.present_days)} dari ${formatNumber(elapsedSchoolDays)} hari sekolah berjalan`
        : `Periode ${activeMonthLabel} ${selectedYear}`,
      icon: CheckCircle2,
      tone: 'blue',
    },
    {
      title: 'Hari Hadir',
      value: formatNumber(statistics?.present_days),
      subtitle: evaluationEndLabel
        ? `Status hadir sampai ${evaluationEndLabel}`
        : 'Belum ada hari sekolah berjalan',
      icon: CheckCircle2,
      tone: 'green',
    },
    {
      title: 'Terlambat',
      value: formatNumber(lateDays),
      subtitle: lateDays > 0
        ? (lateMinutes > 0
          ? `${formatNumber(lateMinutes)} menit keterlambatan`
          : 'Hari terlambat tercatat, detail menit belum tersedia')
        : 'Belum ada keterlambatan pada periode ini',
      icon: AlertTriangle,
      tone: 'amber',
    },
    {
      title: 'Lupa Pulang (TAP)',
      value: formatNumber(tapDays),
      subtitle: tapDays > 0
        ? `${formatNumber(tapMinutes)} menit TAP`
        : 'Belum ada kejadian lupa pulang pada periode ini',
      icon: CalendarDays,
      tone: 'cyan',
    },
    {
      title: 'Izin & Sakit',
      value: formatNumber(izinDays + sakitDays),
      subtitle: (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="inline-flex items-center rounded-full border border-current/20 px-2 py-0.5">
            Izin: {formatNumber(izinDays)} hari
          </span>
          <span className="inline-flex items-center rounded-full border border-current/20 px-2 py-0.5">
            Sakit: {formatNumber(sakitDays)} hari
          </span>
        </div>
      ),
      icon: FileText,
      tone: 'cyan',
    },
    {
      title: 'Alpha',
      value: formatNumber(statistics?.total_alpha),
      subtitle: `${formatNumber(statistics?.total_alpha_menit)} menit alpha`,
      icon: XCircle,
      tone: 'red',
    },
  ];

  const attentionNeeded = Boolean(statistics?.discipline_thresholds?.attention_needed);

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="max-w-3xl">
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-600">
              <CalendarDays className="h-3.5 w-3.5" />
              Rekap Kehadiran Saya
            </div>
            <h1 className="text-2xl font-semibold text-slate-900">Monitoring kehadiran siswa di website</h1>
            <p className="mt-2 text-sm leading-6 text-slate-600 lg:text-base">
              Halaman ini hanya untuk melihat status harian, ringkasan bulanan, dan riwayat kehadiran.
              Absensi tetap dilakukan melalui mobile app SIAPS.
            </p>
          </div>

          <div className="flex flex-col gap-3 sm:flex-row">
            <Link
              to="/absensi-mobile-info"
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700"
            >
              <Smartphone className="h-4 w-4" />
              Lihat Info Absensi Mobile
            </Link>
            <Link
              to="/pengajuan-izin"
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
              <FileText className="h-4 w-4" />
              Pengajuan Izin
            </Link>
          </div>
        </div>
      </div>

      {attentionNeeded && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm">
          <div className="flex items-start gap-2">
            <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
            <p>
              Periode ini membutuhkan perhatian khusus. Periksa detail keterlambatan, alpha, atau izin
              untuk memastikan rekap kehadiran tetap akurat.
            </p>
          </div>
        </div>
      )}

      {period?.is_current_month ? (
        <div className="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 shadow-sm">
          Rekap bulan berjalan dihitung sampai{' '}
          <span className="font-semibold">{evaluationEndLabel || 'hari terakhir yang sudah memiliki catatan'}</span>.
          {period?.today_included
            ? ' Hari ini sudah masuk ke perhitungan karena statusnya sudah tercatat.'
            : ' Hari ini baru akan ikut dihitung setelah ada status absensi atau izin yang tercatat.'}
        </div>
      ) : null}

      {unrecordedDays > 0 ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm">
          Ada <span className="font-semibold">{formatNumber(unrecordedDays)}</span> hari sekolah berjalan yang belum
          memiliki status absensi atau izin. Persentase hadir tetap dihitung dari seluruh hari sekolah berjalan.
        </div>
      ) : null}

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Ringkasan Bulanan</h2>
            <p className="mt-1 text-sm text-slate-500">
              Pilih bulan untuk melihat statistik kehadiran dan daftar riwayat.
            </p>
          </div>

          <div className="flex flex-col gap-3 sm:flex-row">
            <label className="flex flex-col gap-1 text-sm text-slate-600">
              <span className="font-medium text-slate-700">Bulan</span>
              <select
                value={selectedMonth}
                onChange={(event) => {
                  setSelectedMonth(Number(event.target.value));
                  setPage(1);
                }}
                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                {MONTH_LABELS.map((label, index) => (
                  <option key={label} value={index + 1}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <label className="flex flex-col gap-1 text-sm text-slate-600">
              <span className="font-medium text-slate-700">Tahun</span>
              <select
                value={selectedYear}
                onChange={(event) => {
                  setSelectedYear(Number(event.target.value));
                  setPage(1);
                }}
                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                {yearOptions.map((year) => (
                  <option key={year} value={year}>
                    {year}
                  </option>
                ))}
              </select>
            </label>

            <button
              type="button"
              onClick={fetchRecap}
              disabled={isLoading}
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
              Refresh
            </button>
          </div>
        </div>

        {error ? (
          <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {summaryCards.map((card) => (
            <RecapStatCard key={card.title} {...card} />
          ))}
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-sm font-medium text-slate-600">Hari Sekolah Bulan Ini</div>
            <div className="mt-2 text-xl font-semibold text-slate-900">
              {formatNumber(schoolDaysInMonth)}
            </div>
            <div className="mt-1 text-xs text-slate-500">Hari sekolah efektif pada bulan terpilih</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-sm font-medium text-slate-600">Hari Sekolah Berjalan</div>
            <div className="mt-2 text-xl font-semibold text-slate-900">
              {formatNumber(elapsedSchoolDays)}
            </div>
            <div className="mt-1 text-xs text-slate-500">
              {evaluationEndLabel
                ? `Sudah dihitung sampai ${evaluationEndLabel}`
                : 'Belum ada hari sekolah yang masuk perhitungan'}
            </div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-sm font-medium text-slate-600">Total Menit Sekolah</div>
            <div className="mt-2 text-xl font-semibold text-slate-900">
              {formatNumber(totalSchoolMinutes)}
            </div>
            <div className="mt-1 text-xs text-slate-500">Acuan perhitungan disiplin dan kehadiran</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-sm font-medium text-slate-600">Pelanggaran Menit</div>
            <div className="mt-2 text-xl font-semibold text-slate-900">
              {formatNumber(statistics?.total_pelanggaran_menit)}
            </div>
            <div className="mt-1 text-xs text-slate-500">
              {statistics?.persentase_pelanggaran || 0}% dari telat + TAP + alpha
            </div>
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Riwayat Kehadiran</h2>
            <p className="mt-1 text-sm text-slate-500">
              Menampilkan {pagination.total ? `${pagination.from}-${pagination.to}` : '0'} dari {pagination.total} record
              untuk {activeMonthLabel} {selectedYear}.
            </p>
          </div>
        </div>

        {isLoading ? (
          <div className="mt-6 space-y-3">
            {[...Array(4)].map((_, index) => (
              <div key={`skeleton-${index}`} className="h-20 animate-pulse rounded-xl bg-slate-100" />
            ))}
          </div>
        ) : historyItems.length === 0 ? (
          <div className="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
            Tidak ada riwayat kehadiran pada periode ini.
          </div>
        ) : (
          <>
            <div className="mt-6 hidden overflow-hidden rounded-xl border border-slate-200 lg:block">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="px-4 py-3">Tanggal</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Jam Masuk</th>
                    <th className="px-4 py-3">Jam Pulang</th>
                    <th className="px-4 py-3">Durasi</th>
                    <th className="px-4 py-3">Keterangan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 bg-white">
                  {historyItems.map((item) => {
                    const statusKey = normalizeStatusKey(item?.status);
                    return (
                      <tr key={item?.id} className="align-top">
                        <td className="px-4 py-3 text-slate-700">
                          {formatServerDate(item?.tanggal, 'id-ID', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric',
                          })}
                        </td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${STATUS_STYLES[statusKey] || STATUS_STYLES.libur}`}>
                            {item?.status_label || item?.status || '-'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-slate-700">{item?.jam_masuk || '-'}</td>
                        <td className="px-4 py-3 text-slate-700">{item?.jam_pulang || '-'}</td>
                        <td className="px-4 py-3 text-slate-700">{formatDuration(item)}</td>
                        <td className="px-4 py-3 text-slate-600">{item?.keterangan || '-'}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="mt-6 space-y-3 lg:hidden">
              {historyItems.map((item) => {
                const statusKey = normalizeStatusKey(item?.status);
                return (
                  <div key={item?.id} className="rounded-xl border border-slate-200 bg-white p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-slate-900">
                          {formatServerDate(item?.tanggal, 'id-ID', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric',
                          })}
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                          {formatDuration(item)}
                        </div>
                      </div>
                      <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${STATUS_STYLES[statusKey] || STATUS_STYLES.libur}`}>
                        {item?.status_label || item?.status || '-'}
                      </span>
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                      <div className="rounded-lg bg-slate-50 px-3 py-2">
                        <div className="text-xs uppercase tracking-wide text-slate-400">Masuk</div>
                        <div className="mt-1 font-medium text-slate-700">{item?.jam_masuk || '-'}</div>
                      </div>
                      <div className="rounded-lg bg-slate-50 px-3 py-2">
                        <div className="text-xs uppercase tracking-wide text-slate-400">Pulang</div>
                        <div className="mt-1 font-medium text-slate-700">{item?.jam_pulang || '-'}</div>
                      </div>
                    </div>

                    <div className="mt-3 text-sm text-slate-600">
                      <span className="font-medium text-slate-700">Keterangan:</span> {item?.keterangan || '-'}
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-6 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={pagination.current_page <= 1 || isLoading}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <ChevronLeft className="h-4 w-4" />
                Sebelumnya
              </button>

              <div className="text-sm text-slate-500">
                Halaman <span className="font-medium text-slate-700">{pagination.current_page}</span> dari{' '}
                <span className="font-medium text-slate-700">{pagination.last_page}</span>
              </div>

              <button
                type="button"
                onClick={() => setPage((current) => Math.min(pagination.last_page, current + 1))}
                disabled={pagination.current_page >= pagination.last_page || isLoading}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Berikutnya
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default RekapKehadiranSaya;

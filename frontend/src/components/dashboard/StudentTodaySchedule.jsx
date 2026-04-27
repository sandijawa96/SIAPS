import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, ChevronRight, Clock3, GraduationCap, MapPin, RefreshCw } from 'lucide-react';
import { jadwalPelajaranAPI } from '../../services/jadwalPelajaranService';
import { formatServerDate, getServerNowDate } from '../../services/serverClock';

const DAY_NAME_TO_CODE = {
  minggu: 'minggu',
  senin: 'senin',
  selasa: 'selasa',
  rabu: 'rabu',
  kamis: 'kamis',
  jumat: 'jumat',
  "jum'at": 'jumat',
  sabtu: 'sabtu',
};

const resolveTodayDayCode = () => {
  const todayLabel = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    timeZone: 'Asia/Jakarta',
  }).format(getServerNowDate());

  const normalized = String(todayLabel || '')
    .trim()
    .toLowerCase()
    .replace(/'/g, '');

  return DAY_NAME_TO_CODE[normalized] || 'senin';
};

const extractRows = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
};

const StudentTodaySchedule = () => {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const todayDayCode = useMemo(() => resolveTodayDayCode(), []);
  const todayLabel = useMemo(() => (
    formatServerDate(getServerNowDate(), 'id-ID', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    }) || '-'
  ), []);

  const fetchSchedule = async () => {
    try {
      setLoading(true);
      setError('');

      const response = await jadwalPelajaranAPI.getMySchedule({
        no_pagination: true,
        hari: todayDayCode,
        is_active: true,
        status: 'published',
      });

      setRows(extractRows(response?.data?.data ?? response?.data));
    } catch (caughtError) {
      setRows([]);
      setError(
        caughtError?.response?.data?.message
          || caughtError?.message
          || 'Gagal memuat jadwal hari ini'
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSchedule();
  }, [todayDayCode]);

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
      <div className="mb-4 flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <CalendarDays className="h-5 w-5 text-blue-500" />
            <h3 className="text-base font-semibold text-slate-900 lg:text-lg">Jadwal Hari Ini</h3>
          </div>
          <p className="mt-1 text-sm text-slate-500">{todayLabel}</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={fetchSchedule}
            className="text-slate-400 transition hover:text-slate-600"
            title="Refresh jadwal"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <Link
            to="/jadwal-pelajaran"
            className="inline-flex items-center gap-1 text-sm font-medium text-blue-600 transition hover:text-blue-700"
          >
            Lihat semua jadwal
            <ChevronRight className="h-4 w-4" />
          </Link>
        </div>
      </div>

      {loading ? (
        <div className="space-y-3">
          {[...Array(3)].map((_, index) => (
            <div key={`schedule-skeleton-${index}`} className="h-20 animate-pulse rounded-xl bg-slate-100" />
          ))}
        </div>
      ) : error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
          Tidak ada jadwal pelajaran untuk hari ini.
        </div>
      ) : (
        <div className="max-h-96 space-y-3 overflow-y-auto pr-1">
          {rows.map((row) => (
            <div key={row.id} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-400">
                    <Clock3 className="h-3.5 w-3.5" />
                    <span>{row.time_range || `${row.jam_mulai || '--:--'} - ${row.jam_selesai || '--:--'}`}</span>
                  </div>
                  <div className="mt-2 truncate text-sm font-semibold text-slate-900">
                    {row?.mata_pelajaran?.nama_mapel || 'Mata pelajaran'}
                  </div>
                </div>
                <div className="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                  JP {row.jam_ke || '-'}
                </div>
              </div>

              <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                {row?.kelas?.nama_kelas ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1">
                    <GraduationCap className="h-3.5 w-3.5" />
                    {row.kelas.nama_kelas}
                  </span>
                ) : null}
                {row?.ruangan ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1">
                    <MapPin className="h-3.5 w-3.5" />
                    {row.ruangan}
                  </span>
                ) : null}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default StudentTodaySchedule;

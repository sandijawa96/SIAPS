import React, { useState, useEffect } from 'react';
import { Clock, Users, CheckCircle, XCircle, AlertCircle, Calendar, MapPin } from 'lucide-react';
import api from '../../services/api';
import { formatServerDate, getServerDateString } from '../../services/serverClock';

const TodayAttendance = () => {
  const [attendanceData, setAttendanceData] = useState({
    attendances: [],
    summary: {
      total: 0,
      hadir: 0,
      terlambat: 0,
      izin: 0,
      alpha: 0
    },
    date: '',
    totalStudents: 0,
    pagination: {
      total: 0,
      current_page: 1,
      per_page: 8,
      from: 0,
      to: 0
    }
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchTodayAttendance();
  }, []);

  const fetchTodayAttendance = async () => {
    try {
      setLoading(true);
      const response = await api.get('/dashboard/today-attendance', {
        params: {
          page: 1,
          per_page: 8
        }
      });
      
      // Handle new response format with success flag
      let data;
      if (response.data.success) {
        data = response.data.data;
      } else {
        data = response.data;
      }
      
      // Ensure all required fields exist with defaults
      setAttendanceData({
        attendances: data.attendances || [],
        summary: {
          total: data.summary?.total || 0,
          hadir: data.summary?.hadir || 0,
          terlambat: data.summary?.terlambat || 0,
          izin: data.summary?.izin || 0,
          alpha: data.summary?.alpha || 0,
          attendancePercentage: data.summary?.attendancePercentage || '0%'
        },
        date: data.date || getServerDateString(),
        totalStudents: data.totalUsers || data.totalStudents || 0,
        pagination: {
          total: data.pagination?.total || (data.attendances || []).length || 0,
          current_page: data.pagination?.current_page || 1,
          per_page: data.pagination?.per_page || 8,
          from: data.pagination?.from || 0,
          to: data.pagination?.to || 0
        }
      });
    } catch (err) {
      setError(err.message);
      console.error('Error fetching today attendance:', err);
      
      // Set default data on error
      setAttendanceData({
        attendances: [],
        summary: {
          total: 0,
          hadir: 0,
          terlambat: 0,
          izin: 0,
          alpha: 0,
          attendancePercentage: '0%'
        },
        date: getServerDateString(),
        totalStudents: 0,
        pagination: {
          total: 0,
          current_page: 1,
          per_page: 8,
          from: 0,
          to: 0
        }
      });
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'hadir':
        return <CheckCircle className="w-4 h-4 text-green-500" />;
      case 'terlambat':
        return <AlertCircle className="w-4 h-4 text-yellow-500" />;
      case 'izin':
        return <Clock className="w-4 h-4 text-blue-500" />;
      case 'alpha':
        return <XCircle className="w-4 h-4 text-red-500" />;
      default:
        return <Clock className="w-4 h-4 text-gray-500" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'hadir':
        return 'text-green-600 bg-green-50';
      case 'terlambat':
        return 'text-yellow-600 bg-yellow-50';
      case 'izin':
        return 'text-blue-600 bg-blue-50';
      case 'alpha':
        return 'text-red-600 bg-red-50';
      default:
        return 'text-gray-600 bg-gray-50';
    }
  };

  const formatDate = (dateString) => {
    return formatServerDate(dateString, 'id-ID', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }) || '-';
  };

  const formatAttendanceTime = (value) => {
    if (!value || value === '-') {
      return '-';
    }

    return value;
  };

  if (loading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="animate-pulse">
          <div className="mb-4 h-6 w-1/3 rounded bg-slate-200"></div>
          <div className="space-y-3">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="h-4 rounded bg-slate-200"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div className="text-center text-red-500">
          <XCircle className="w-8 h-8 mx-auto mb-2" />
          <p>Gagal memuat data absensi</p>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between gap-3">
        <div className="flex items-center space-x-2">
          <Calendar className="w-5 h-5 text-blue-500" />
          <h3 className="text-base font-semibold text-slate-900 lg:text-lg">Absensi Hari Ini</h3>
        </div>
        <span className="text-xs text-slate-500 lg:text-sm">
          {formatDate(attendanceData.date)}
        </span>
      </div>

      {/* Summary Cards */}
      <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
          <div className="mb-1 flex items-center justify-center">
            <Users className="h-4 w-4 text-slate-500" />
          </div>
          <div className="text-lg font-semibold text-slate-900">{attendanceData.totalStudents || 0}</div>
          <div className="text-xs text-slate-500">Total Siswa</div>
        </div>
        
        <div className="rounded-lg border border-green-100 bg-green-50 p-3 text-center">
          <div className="mb-1 flex items-center justify-center">
            <CheckCircle className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-lg font-semibold text-green-600">{attendanceData.summary?.hadir || 0}</div>
          <div className="text-xs text-green-500">Hadir Efektif</div>
        </div>
        
        <div className="rounded-lg border border-yellow-100 bg-yellow-50 p-3 text-center">
          <div className="mb-1 flex items-center justify-center">
            <AlertCircle className="w-4 h-4 text-yellow-500" />
          </div>
          <div className="text-lg font-semibold text-yellow-600">{attendanceData.summary?.terlambat || 0}</div>
          <div className="text-xs text-yellow-500">Terlambat</div>
        </div>
        
        <div className="rounded-lg border border-blue-100 bg-blue-50 p-3 text-center">
          <div className="mb-1 flex items-center justify-center">
            <Clock className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-lg font-semibold text-blue-600">{attendanceData.summary?.izin || 0}</div>
          <div className="text-xs text-blue-500">Izin</div>
        </div>
        
        <div className="rounded-lg border border-cyan-100 bg-cyan-50 p-3 text-center">
          <div className="mb-1 flex items-center justify-center">
            <Users className="w-4 h-4 text-cyan-600" />
          </div>
          <div className="text-lg font-semibold text-cyan-700">{attendanceData.summary?.attendancePercentage || '0%'}</div>
          <div className="text-xs text-cyan-600">Kehadiran</div>
        </div>
      </div>

      {/* Attendance Cards */}
      <div className="space-y-3">
        <h4 className="mb-3 text-sm font-semibold text-slate-700">Daftar Absensi Terbaru</h4>
        
        {attendanceData.attendances.length === 0 ? (
          <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 py-8 text-center text-slate-500">
            <Clock className="mx-auto mb-2 h-8 w-8 opacity-50" />
            <p>Belum ada data absensi hari ini</p>
          </div>
        ) : (
          <div className="max-h-80 overflow-y-auto space-y-3">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {attendanceData.attendances.map((attendance) => (
                <div key={attendance.id} className="rounded-lg border border-slate-200 bg-white p-4 transition-shadow hover:shadow-sm">
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center space-x-2">
                      {getStatusIcon(attendance.status)}
                      <div className="text-sm font-medium text-slate-900">
                        {attendance.user_name}
                      </div>
                    </div>
                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(attendance.status)}`}>
                      {attendance.status}
                    </span>
                  </div>
                  
                  <div className="space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <div className="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2">
                        <div className="mb-1 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                          <Clock className="h-3.5 w-3.5" />
                          <span>Masuk</span>
                        </div>
                        <div className="text-sm font-semibold text-slate-700">
                          {formatAttendanceTime(attendance.jam_masuk ?? attendance.time)}
                        </div>
                      </div>
                      <div className="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2">
                        <div className="mb-1 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                          <Clock className="h-3.5 w-3.5" />
                          <span>Pulang</span>
                        </div>
                        <div className="text-sm font-semibold text-slate-700">
                          {formatAttendanceTime(attendance.jam_pulang ?? attendance.jam_keluar)}
                        </div>
                      </div>
                    </div>
                    {attendance.location !== '-' && (
                      <div className="flex items-center gap-1 text-xs text-slate-500">
                        <MapPin className="w-3.5 h-3.5" />
                        <span>{attendance.location}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
            
            {attendanceData.pagination.total > attendanceData.attendances.length && (
              <div className="border-t border-slate-200 py-3 text-center">
                <span className="text-xs text-slate-500">
                  +{attendanceData.pagination.total - attendanceData.attendances.length} absensi lainnya
                </span>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default TodayAttendance;


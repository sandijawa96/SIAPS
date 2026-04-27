import { useEffect, useMemo, useState } from 'react';
import { useAuth } from './useAuth';
import { dashboardAPI } from '../services/api';
import { getStoredToken } from '../utils/authStorage';

const DEFAULT_STATS = {
  totalUsers: 0,
  totalRoles: 0,
  totalPermissions: 0,
  totalStudents: 0,
  totalTeachers: 0,
  totalActiveTeachers: 0,
  todayActivities: 0,
  todaySchedules: 0,
  todaySchedulesSchool: 0,
  totalClasses: 0,
  totalActiveClasses: 0,
  pendingApprovals: 0,
  attendanceRate: '0%',
  attendanceCount: 0,
  leaveCount: 0,
  lateCount: 0,
  totalTeachingHours: 0,
  totalStudentsTaught: 0,
  waliStudentAttendanceSummaryToday: '0/0',
  waliStudentAttendanceRateToday: '0%',
  waliStudentPresentToday: 0,
  waliStudentTotalToday: 0,
  waliStudentNotCheckedInToday: 0,
  waliPendingApprovals: 0,
  studentPresentToday: 0,
  studentTotalToday: 0,
  studentNotCheckedInToday: 0,
  studentLateToday: 0,
  studentAttendanceSummaryToday: '0/0',
  studentAttendanceRateToday: '0%',
  studentsCheckedInToday: 0,
  studentsLateToday: 0,
  studentsNotCheckedInToday: 0,
  studentPendingLeaves: 0,
  alphaToday: 0,
  myTodaySchedules: 0,
  absentTeachersToday: 0,
  notificationsToday: 0,
  waSent24h: 0,
  waFailed24h: 0,
  activeGpsLocations: 0,
  totalGpsLocations: 0,
  attendanceWithGpsToday: 0,
  attendanceWithoutGpsToday: 0,
};

const normalizeRoleName = (roleName) =>
  String(roleName || '')
    .trim()
    .toLowerCase()
    .replace(/[_\s]+/g, '_')
    .replace(/_(web|api)$/, '');

const extractApiPayload = (response) => {
  const payload = response?.data;
  if (!payload || typeof payload !== 'object') {
    return {};
  }

  if (payload.success && payload.data && typeof payload.data === 'object') {
    return payload;
  }

  if (payload.data && typeof payload.data === 'object') {
    return {
      data: payload.data,
      user_role: payload.user_role,
    };
  }

  return {
    data: payload,
    user_role: payload.user_role,
  };
};

export const useDashboard = () => {
  const { user, roles, isLoading: authLoading } = useAuth();
  const [stats, setStats] = useState(DEFAULT_STATS);
  const [systemStatus, setSystemStatus] = useState([]);
  const [recentActivities, setRecentActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [resolvedRole, setResolvedRole] = useState('');

  useEffect(() => {
    const fetchDashboardData = async () => {
      if (authLoading) {
        return;
      }

      if (!user) {
        setLoading(false);
        return;
      }

      const token = getStoredToken();
      if (!token) {
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);

      const settled = await Promise.allSettled([
        dashboardAPI.getStats(),
        dashboardAPI.getSystemStatus(),
        dashboardAPI.getRecentActivity(),
      ]);

      const [statsResult, systemStatusResult, recentActivitiesResult] = settled;
      let roleFromStats = '';

      if (statsResult.status === 'fulfilled') {
        const payload = extractApiPayload(statsResult.value);
        setStats({
          ...DEFAULT_STATS,
          ...(payload.data || {}),
        });
        roleFromStats = normalizeRoleName(payload.user_role);
      } else {
        console.error('Error fetching stats:', statsResult.reason);
        setStats(DEFAULT_STATS);
      }

      if (systemStatusResult.status === 'fulfilled') {
        const payload = extractApiPayload(systemStatusResult.value);
        const rows = Array.isArray(payload.data) ? payload.data : [];
        setSystemStatus(rows);
      } else {
        console.error('Error fetching system status:', systemStatusResult.reason);
        setSystemStatus([]);
      }

      if (recentActivitiesResult.status === 'fulfilled') {
        const payload = extractApiPayload(recentActivitiesResult.value);
        const rows = Array.isArray(payload.data) ? payload.data : [];
        setRecentActivities(rows);
      } else {
        console.error('Error fetching recent activities:', recentActivitiesResult.reason);
        setRecentActivities([]);
      }

      const roleFromAuth = normalizeRoleName(roles?.[0] || user?.role);
      setResolvedRole(roleFromStats || roleFromAuth);

      if (settled.every((item) => item.status === 'rejected')) {
        setError('Terjadi kesalahan saat memuat dashboard');
      }

      setLoading(false);
    };

    fetchDashboardData();
  }, [authLoading, roles, user]);

  const roleStats = useMemo(() => {
    switch (resolvedRole) {
      case 'super_admin':
        return {
          title: 'Dashboard Super Admin',
          description: 'Kelola seluruh sistem dari sini',
          stats: [
            {
              title: 'Total Pengguna',
              value: stats.totalUsers,
              subtitle: 'Semua pengguna sistem',
              icon: 'users',
              color: 'blue',
            },
            {
              title: 'Total Role',
              value: stats.totalRoles,
              subtitle: 'Role aktif',
              icon: 'shield',
              color: 'green',
            },
            {
              title: 'Total Permission',
              value: stats.totalPermissions,
              subtitle: 'Hak akses sistem',
              icon: 'shield',
              color: 'purple',
            },
            {
              title: 'Aktivitas Hari Ini',
              value: stats.todayActivities,
              subtitle: 'Aktivitas absensi',
              icon: 'activity',
              color: 'orange',
            },
          ],
        };

      case 'admin':
        return {
          title: 'Dashboard Admin',
          description: 'Operasional umum sekolah',
          stats: [
            {
              title: 'Total Pengguna',
              value: stats.totalUsers,
              subtitle: 'Seluruh akun aktif',
              icon: 'users',
              color: 'blue',
            },
            {
              title: 'Total Siswa',
              value: stats.totalStudents,
              subtitle: 'Siswa terdaftar',
              icon: 'userCheck',
              color: 'green',
            },
            {
              title: 'Total Guru',
              value: stats.totalTeachers,
              subtitle: 'Guru terdaftar',
              icon: 'book',
              color: 'purple',
            },
            {
              title: 'Aktivitas Hari Ini',
              value: stats.todayActivities,
              subtitle: 'Aktivitas absensi',
              icon: 'activity',
              color: 'orange',
            },
          ],
        };

      case 'guru':
      case 'guru_bk':
        return {
          title: 'Dashboard Guru',
          description: 'Ringkasan beban mengajar Anda',
          stats: [
            {
              title: 'Total Jam Pelajaran',
              value: stats.totalTeachingHours,
              subtitle: 'Akumulasi JP pada jadwal aktif',
              icon: 'book',
              color: 'purple',
            },
            {
              title: 'Jadwal Hari Ini',
              value: stats.todaySchedules,
              subtitle: `${stats.totalClasses} kelas aktif`,
              icon: 'calendar',
              color: 'blue',
            },
            {
              title: 'Jumlah Kelas Diampu',
              value: stats.totalClasses,
              subtitle: 'Kelas yang Anda ampu',
              icon: 'users',
              color: 'green',
            },
            {
              title: 'Total Siswa Diampu',
              value: stats.totalStudentsTaught,
              subtitle: 'Siswa pada kelas yang diampu',
              icon: 'userCheck',
              color: 'orange',
            },
          ],
        };

      case 'wali_kelas':
        return {
          title: 'Dashboard Wali Kelas',
          description: 'Monitor kelas wali dan izin siswa',
          stats: [
            {
              title: 'Jadwal Hari Ini',
              value: stats.todaySchedules,
              subtitle: `${stats.totalClasses} kelas aktif`,
              icon: 'calendar',
              color: 'blue',
            },
            {
              title: 'Kehadiran Kelas Wali',
              value: stats.waliStudentAttendanceSummaryToday,
              subtitle: `${stats.waliStudentAttendanceRateToday} hadir hari ini`,
              icon: 'trendingUp',
              color: 'green',
            },
            {
              title: 'Belum Absen Kelas Wali',
              value: stats.waliStudentNotCheckedInToday,
              subtitle: 'Siswa belum check-in hari ini',
              icon: 'activity',
              color: 'orange',
            },
            {
              title: 'Izin Pending Kelas Wali',
              value: stats.waliPendingApprovals,
              subtitle: 'Izin siswa kelas Anda yang pending',
              icon: 'userCheck',
              color: 'yellow',
            },
          ],
        };

      case 'wakasek_kesiswaan':
        return {
          title: 'Dashboard Wakasek Kesiswaan',
          description: 'Kontrol kehadiran dan izin siswa',
          stats: [
            {
              title: 'Jadwal Hari Ini',
              value: stats.todaySchedulesSchool,
              subtitle: 'Total jadwal sekolah hari ini',
              icon: 'calendar',
              color: 'blue',
            },
            {
              title: 'Total Kelas Aktif',
              value: stats.totalActiveClasses,
              subtitle: 'Kelas aktif berjalan',
              icon: 'book',
              color: 'green',
            },
            {
              title: 'Total Siswa',
              value: stats.totalStudents,
              subtitle: 'Siswa terdaftar',
              icon: 'users',
              color: 'purple',
            },
            {
              title: 'Siswa Sudah Absen',
              value: stats.studentsCheckedInToday,
              subtitle: 'Hadir/terlambat hari ini',
              icon: 'userCheck',
              color: 'green',
            },
            {
              title: 'Izin Pending Siswa',
              value: stats.studentPendingLeaves,
              subtitle: 'Menunggu persetujuan',
              icon: 'activity',
              color: 'orange',
            },
            {
              title: 'Siswa Terlambat',
              value: stats.studentsLateToday,
              subtitle: 'Terlambat hari ini',
              icon: 'clock',
              color: 'yellow',
            },
            {
              title: 'Siswa Belum Absen',
              value: stats.studentsNotCheckedInToday,
              subtitle: 'Belum ada record absensi',
              icon: 'shield',
              color: 'orange',
            },
            {
              title: 'Alpha Hari Ini',
              value: stats.alphaToday,
              subtitle: 'Status alpha tercatat',
              icon: 'activity',
              color: 'purple',
            },
          ],
        };

      case 'wakasek_kurikulum':
        return {
          title: 'Dashboard Wakasek Kurikulum',
          description: 'Monitoring akademik dan distribusi jadwal',
          stats: [
            {
              title: 'Jadwal Sekolah Hari Ini',
              value: stats.todaySchedulesSchool,
              subtitle: 'Total jadwal seluruh sekolah',
              icon: 'calendar',
              color: 'blue',
            },
            {
              title: 'Total Kelas Aktif',
              value: stats.totalActiveClasses,
              subtitle: 'Kelas aktif berjalan',
              icon: 'book',
              color: 'green',
            },
            {
              title: 'Total Guru Aktif',
              value: stats.totalActiveTeachers,
              subtitle: 'Guru aktif saat ini',
              icon: 'users',
              color: 'purple',
            },
            {
              title: 'Total Siswa',
              value: stats.totalStudents,
              subtitle: 'Siswa terdaftar',
              icon: 'userCheck',
              color: 'yellow',
            },
            {
              title: 'Jadwal Saya Hari Ini',
              value: stats.myTodaySchedules,
              subtitle: 'Jadwal mengajar pribadi',
              icon: 'clock',
              color: 'blue',
            },
            {
              title: 'Guru Tidak Masuk',
              value: stats.absentTeachersToday,
              subtitle: 'Placeholder sementara',
              icon: 'activity',
              color: 'orange',
            },
            {
              title: 'Alpha Hari Ini',
              value: stats.alphaToday,
              subtitle: 'Siswa status alpha',
              icon: 'shield',
              color: 'purple',
            },
            {
              title: 'Rasio Hadir Siswa',
              value: stats.studentAttendanceRateToday,
              subtitle: 'Persentase hadir hari ini',
              icon: 'trendingUp',
              color: 'green',
            },
          ],
        };

      case 'wakasek_humas':
        return {
          title: 'Dashboard Wakasek Humas',
          description: 'Monitoring komunikasi dan notifikasi',
          stats: [
            {
              title: 'Notifikasi Hari Ini',
              value: stats.notificationsToday,
              subtitle: 'Total notifikasi sistem',
              icon: 'activity',
              color: 'blue',
            },
            {
              title: 'WA Terkirim 24 Jam',
              value: stats.waSent24h,
              subtitle: 'Status sent/delivered',
              icon: 'userCheck',
              color: 'green',
            },
            {
              title: 'WA Gagal 24 Jam',
              value: stats.waFailed24h,
              subtitle: 'Perlu tindak lanjut',
              icon: 'shield',
              color: 'orange',
            },
            {
              title: 'Rasio Hadir Siswa',
              value: stats.studentAttendanceRateToday,
              subtitle: 'Persentase hadir hari ini',
              icon: 'trendingUp',
              color: 'purple',
            },
          ],
        };

      case 'wakasek_sarpras':
        return {
          title: 'Dashboard Wakasek Sarpras',
          description: 'Monitoring infrastruktur lokasi GPS absensi',
          stats: [
            {
              title: 'Lokasi GPS Aktif',
              value: stats.activeGpsLocations,
              subtitle: 'Lokasi aktif digunakan',
              icon: 'activity',
              color: 'green',
            },
            {
              title: 'Total Lokasi GPS',
              value: stats.totalGpsLocations,
              subtitle: 'Semua lokasi terdaftar',
              icon: 'users',
              color: 'blue',
            },
            {
              title: 'Absensi Dengan GPS',
              value: stats.attendanceWithGpsToday,
              subtitle: 'Record hari ini',
              icon: 'userCheck',
              color: 'purple',
            },
            {
              title: 'Absensi Tanpa GPS',
              value: stats.attendanceWithoutGpsToday,
              subtitle: 'Record hari ini',
              icon: 'shield',
              color: 'orange',
            },
          ],
        };

      case 'kepala_sekolah':
        return {
          title: 'Dashboard Kepala Sekolah',
          description: 'Ringkasan strategis sekolah hari ini',
          stats: [
            {
              title: 'Siswa Hadir Hari Ini',
              value: stats.studentPresentToday,
              subtitle: 'Total siswa hadir/terlambat',
              icon: 'userCheck',
              color: 'green',
            },
            {
              title: 'Rasio Hadir Siswa',
              value: stats.studentAttendanceRateToday,
              subtitle: 'Persentase kehadiran hari ini',
              icon: 'trendingUp',
              color: 'blue',
            },
            {
              title: 'Izin Pending',
              value: stats.studentPendingLeaves,
              subtitle: 'Menunggu persetujuan',
              icon: 'activity',
              color: 'orange',
            },
            {
              title: 'Total Guru',
              value: stats.totalTeachers,
              subtitle: 'Guru terdaftar',
              icon: 'book',
              color: 'purple',
            },
            {
              title: 'Total Siswa',
              value: stats.totalStudents,
              subtitle: 'Siswa terdaftar',
              icon: 'users',
              color: 'yellow',
            },
          ],
        };

      case 'siswa':
        return {
          title: 'Dashboard Siswa',
          description: 'Pantau kehadiran dan aktivitas Anda',
          stats: [
            {
              title: 'Kehadiran',
              value: stats.attendanceCount,
              subtitle: 'Total hadir',
              icon: 'clock',
              color: 'blue',
            },
            {
              title: 'Izin',
              value: stats.leaveCount,
              subtitle: 'Total izin',
              icon: 'userCheck',
              color: 'yellow',
            },
            {
              title: 'Terlambat',
              value: stats.lateCount,
              subtitle: 'Total keterlambatan',
              icon: 'activity',
              color: 'orange',
            },
            {
              title: 'Rasio Hadir',
              value: stats.attendanceRate,
              subtitle: 'Persentase hadir',
              icon: 'trendingUp',
              color: 'green',
            },
          ],
        };

      default:
        return {
          title: 'Dashboard',
          description: 'Selamat datang di Sistem Absensi',
          stats: [],
        };
    }
  }, [resolvedRole, stats]);

  const getRoleStats = () => roleStats;

  return {
    user,
    resolvedRole,
    stats,
    systemStatus,
    recentActivities,
    loading,
    error,
    getRoleStats,
  };
};

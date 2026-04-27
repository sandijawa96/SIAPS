import React from 'react';
import { useDashboard } from '../hooks/useDashboard';
import { useAuth } from '../hooks/useAuth';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';
import { 
  DashboardHeader,
  StatsCard,
  ActivityList,
  QuickActions,
  TodayAttendance,
  MyAttendanceStatus,
  JsaAttendanceNotice,
  StudentTodaySchedule
} from '../components/dashboard';
import LoadingScreen from '../components/LoadingScreen';

const Dashboard = () => {
  const { hasPermission, hasAnyPermission, hasAnyRole } = useAuth();
  const {
    user,
    resolvedRole,
    systemStatus,
    recentActivities,
    loading,
    error,
    getRoleStats
  } = useDashboard();

  if (loading) {
    return <LoadingScreen />;
  }

  if (error) {
    return (
      <div className="p-6 text-center">
        <p className="text-red-500">Error: {error}</p>
      </div>
    );
  }

  const { title, description, stats: roleStats } = getRoleStats();
  const isStudentRole = resolvedRole === 'siswa';
  const displayName = user?.nama_lengkap || user?.name || user?.username || 'Pengguna';
  const userPhotoUrl = resolveProfilePhotoUrl(user?.foto_profil_url || user?.foto_profil);

  const resolveStatusTone = (statusValue) => {
    const normalized = String(statusValue || '').toLowerCase();
    switch (normalized) {
      case 'online':
      case 'healthy':
        return {
          dot: 'bg-green-500',
          text: 'text-green-600',
        };
      case 'warning':
      case 'scheduled':
      case 'degraded':
        return {
          dot: 'bg-yellow-500',
          text: 'text-yellow-600',
        };
      default:
        return {
          dot: 'bg-red-500',
          text: 'text-red-600',
        };
    }
  };

  // Define quick actions based on role
  const canOpenAction = (action) => {
    if (!action) {
      return false;
    }

    if (Array.isArray(action.requiredAnyRoles) && action.requiredAnyRoles.length > 0) {
      return hasAnyRole(action.requiredAnyRoles);
    }

    if (Array.isArray(action.requiredAnyPermissions) && action.requiredAnyPermissions.length > 0) {
      return hasAnyPermission(action.requiredAnyPermissions);
    }

    if (action.requiredPermission) {
      return hasPermission(action.requiredPermission);
    }

    return true;
  };

  const getQuickActions = () => {
    const quickActions = (() => {
    switch (resolvedRole) {
      case 'super_admin':
        return [
          {
            title: 'Kelola Pengguna',
            icon: 'users',
            path: '/manajemen-pengguna',
            color: 'blue',
            requiredAnyPermissions: ['manage_users', 'view_personal_data_verification']
          },
          { title: 'Monitoring Kelas', icon: 'activity', path: '/monitoring-kelas', color: 'green', requiredAnyRoles: ['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'] },
          { title: 'Kelola Role', icon: 'shield', path: '/manajemen-role', color: 'green', requiredAnyPermissions: ['view_roles', 'manage_roles'] },
          { title: 'Pengaturan', icon: 'settings', path: '/pengaturan', color: 'purple', requiredAnyPermissions: ['manage_attendance_settings', 'manage_settings', 'manage_whatsapp', 'manage_backups', 'manage_broadcast_campaigns'] },
          { title: 'Laporan', icon: 'trendingUp', path: '/laporan-statistik', color: 'orange', requiredPermission: 'view_reports' }
        ];
      case 'admin':
        return [
          { title: 'Pengguna', icon: 'users', path: '/manajemen-pengguna', color: 'blue', requiredAnyPermissions: ['manage_users', 'view_personal_data_verification'] },
          { title: 'Data Siswa', icon: 'userCheck', path: '/data-siswa-lengkap', color: 'green', requiredAnyPermissions: ['view_siswa', 'manage_students'] },
          { title: 'Data Guru', icon: 'book', path: '/data-pegawai-lengkap', color: 'purple', requiredAnyPermissions: ['view_pegawai', 'manage_pegawai'] },
          { title: 'Aktivitas', icon: 'clock', path: '/absensi-realtime', color: 'orange', requiredPermission: 'view_absensi' },
        ];

      case 'kepala_sekolah':
        return [
          { title: 'Laporan', icon: 'trendingUp', path: '/laporan-statistik', color: 'green', requiredPermission: 'view_reports' },
          { title: 'Absensi', icon: 'clock', path: '/absensi-realtime', color: 'blue', requiredPermission: 'view_absensi' },
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'purple', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] },
          { title: 'Kalender', icon: 'book', path: '/kalender-akademik', color: 'orange', requiredAnyPermissions: ['view_tahun_ajaran', 'manage_periode_akademik', 'manage_event_akademik'] },
        ];

      case 'wakasek_kesiswaan':
        return [
          { title: 'Monitoring Kelas', icon: 'activity', path: '/monitoring-kelas', color: 'green', requiredAnyRoles: ['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'] },
          { title: 'Absensi', icon: 'clock', path: '/absensi-realtime', color: 'blue', requiredPermission: 'view_absensi' },
          { title: 'Izin Siswa', icon: 'userCheck', path: '/persetujuan-izin-siswa', color: 'purple', requiredAnyRoles: ['Super_Admin', 'Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'] },
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'green', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] },
          { title: 'Laporan', icon: 'trendingUp', path: '/laporan-statistik', color: 'orange', requiredPermission: 'view_reports' },
        ];

      case 'wakasek_kurikulum':
        return [
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'blue', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] },
          { title: 'Penugasan Guru', icon: 'users', path: '/penugasan-guru-mapel', color: 'green', requiredPermission: 'assign_guru_mapel' },
          { title: 'Transisi Siswa', icon: 'userCheck', path: '/manajemen-kelas', color: 'orange', requiredAnyPermissions: ['view_kelas', 'manage_kelas', 'view_siswa', 'manage_students'] },
          { title: 'Master Mapel', icon: 'book', path: '/master-mata-pelajaran', color: 'purple', requiredAnyPermissions: ['view_mapel', 'manage_mapel'] },
          { title: 'Laporan', icon: 'trendingUp', path: '/laporan-statistik', color: 'orange', requiredPermission: 'view_reports' },
        ];

      case 'wakasek_humas':
        return [
          { title: 'WhatsApp', icon: 'activity', path: '/whatsapp-gateway', color: 'green', requiredPermission: 'manage_whatsapp' },
          { title: 'Absensi', icon: 'clock', path: '/absensi-realtime', color: 'blue', requiredPermission: 'view_absensi' },
          { title: 'Laporan', icon: 'trendingUp', path: '/laporan-statistik', color: 'purple', requiredPermission: 'view_reports' },
          { title: 'Pengaturan', icon: 'settings', path: '/pengaturan', color: 'orange', requiredAnyPermissions: ['manage_attendance_settings', 'manage_settings', 'manage_whatsapp', 'manage_backups'] },
        ];

      case 'wakasek_sarpras':
        return [
          { title: 'Lokasi GPS', icon: 'activity', path: '/manajemen-lokasi-gps', color: 'blue', requiredPermission: 'manage_settings' },
          { title: 'Live Tracking', icon: 'clock', path: '/live-tracking', color: 'green', requiredPermission: 'view_live_tracking' },
          { title: 'Absensi', icon: 'userCheck', path: '/absensi-realtime', color: 'purple', requiredPermission: 'view_absensi' },
          { title: 'Pengaturan', icon: 'settings', path: '/pengaturan', color: 'orange', requiredAnyPermissions: ['manage_attendance_settings', 'manage_settings', 'manage_whatsapp', 'manage_backups'] },
        ];

      case 'wali_kelas':
        return [
          { title: 'Monitoring Kelas', icon: 'activity', path: '/monitoring-kelas', color: 'green', requiredAnyRoles: ['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'] },
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'blue', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] },
          { title: 'Absensi', icon: 'clock', path: '/absensi-realtime', color: 'green', requiredPermission: 'view_absensi' },
          { title: 'Transisi Siswa', icon: 'users', path: '/manajemen-kelas', color: 'orange', requiredAnyPermissions: ['view_kelas', 'manage_kelas', 'view_siswa', 'manage_students'] },
          { title: 'Izin Siswa', icon: 'userCheck', path: '/persetujuan-izin-siswa', color: 'purple', requiredAnyRoles: ['Super_Admin', 'Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'] },
        ];

      case 'guru':
      case 'guru_bk':
        return [
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'blue', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] },
          { title: 'Absensi', icon: 'clock', path: '/absensi-realtime', color: 'green', requiredPermission: 'view_absensi' },
          { title: 'Data Pribadi', icon: 'book', path: '/data-pribadi-saya', color: 'orange' },
        ];
      case 'siswa':
        return [
          { title: 'Rekap Kehadiran', icon: 'trendingUp', path: '/rekap-kehadiran-saya', color: 'purple', requiredAnyRoles: ['Siswa'] },
          { title: 'Absensi Mobile', icon: 'clock', path: '/absensi-mobile-info', color: 'blue', requiredPermission: 'view_absensi' },
          { title: 'Izin', icon: 'userCheck', path: '/pengajuan-izin', color: 'green', requiredAnyRoles: ['Siswa'] },
          { title: 'Jadwal', icon: 'calendar', path: '/jadwal-pelajaran', color: 'purple', requiredAnyPermissions: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran'] }
        ];
      default:
        return [];
    }
    })();

    return quickActions.filter(canOpenAction);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <DashboardHeader 
        title={title}
        description={description}
        userName={displayName}
        userPhotoUrl={userPhotoUrl}
      />

      {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-5">
        {roleStats.map((stat) => (
          <StatsCard
            key={`${stat.title}-${stat.icon}`}
            title={stat.title}
            value={stat.value}
            subtitle={stat.subtitle}
            icon={stat.icon}
            color={stat.color}
          />
        ))}
      </div>

      {/* Attendance Status - Full Width */}
      {isStudentRole ? <MyAttendanceStatus /> : <JsaAttendanceNotice role={resolvedRole} />}

      {/* Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {isStudentRole ? <StudentTodaySchedule /> : <TodayAttendance />}

        {/* Recent Activities */}
        <ActivityList 
          activities={recentActivities}
          title={isStudentRole ? 'Riwayat Absensi' : 'Aktivitas Terbaru'}
        />
      </div>

      {/* System Status - Full Width */}
      <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-5 lg:p-6">
        <h3 className="text-base lg:text-lg font-semibold text-slate-900 mb-4">
          Status Sistem
        </h3>
        {systemStatus.length === 0 ? (
          <p className="text-sm text-slate-500">Status sistem belum tersedia.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {systemStatus.map((status, index) => {
              const tone = resolveStatusTone(status.status);
              return (
                <div key={status.name || `status-${index}`} className="flex items-center justify-between p-4 rounded-lg border border-slate-200 bg-slate-50/60">
                  <span className="text-sm font-medium text-slate-700">{status.name}</span>
                  <div className="flex items-center space-x-2">
                    <div className={`w-3 h-3 rounded-full ${tone.dot}`} />
                    <span className={`text-sm font-medium ${tone.text}`}>
                      {status.message || status.status}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Quick Actions */}
      <QuickActions actions={getQuickActions()} />
    </div>
  );
};

export default Dashboard;

import { lazy, Suspense } from 'react';
import { createBrowserRouter, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import LoadingScreen from './components/LoadingScreen';
import ErrorBoundary from './components/ErrorBoundary';
import { FEATURE_FLAGS } from './config/features';

// Lazy loaded components
const Login = lazy(() => import('./pages/Login'));
const ResetPassword = lazy(() => import('./pages/ResetPassword'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const ManajemenPengguna = lazy(() => import('./pages/ManajemenPenggunaNew'));
const ManajemenRole = lazy(() => import('./pages/ManajemenRole'));
const ManajemenKelas = lazy(() => import('./pages/ManajemenKelasWithImportExport'));
const ManajemenTahunAjaran = lazy(() => import('./pages/ManajemenTahunAjaran'));
const AbsensiRealtime = lazy(() => import('./pages/AbsensiRealtime'));
const PersetujuanIzin = lazy(() => import('./pages/PersetujuanIzin'));
const PengajuanIzin = lazy(() => import('./pages/PengajuanIzin'));
const LaporanStatistik = lazy(() => import('./pages/LaporanStatistik'));
const KalenderAkademik = lazy(() => import('./pages/KalenderAkademik'));
const MasterMataPelajaran = lazy(() => import('./pages/MasterMataPelajaran'));
const PenugasanGuruMapel = lazy(() => import('./pages/PenugasanGuruMapel'));
const JadwalPelajaran = lazy(() => import('./pages/JadwalPelajaran'));
const ManajemenLokasiGPS = lazy(() => import('./pages/ManajemenLokasiGPS'));
const WhatsAppGateway = lazy(() => import('./pages/WhatsAppGateway'));
const BroadcastMessage = lazy(() => import('./pages/BroadcastMessage'));
const AttendanceDisciplineCaseDetail = lazy(() => import('./pages/AttendanceDisciplineCaseDetail'));
const NotificationCenter = lazy(() => import('./pages/NotificationCenter'));
const AttendanceSchemaManagement = lazy(() => import('./pages/AttendanceSchemaManagement'));
const UserSchemaManagement = lazy(() => import('./pages/UserSchemaManagement'));
const ManajemenQRCodeSiswa = lazy(() => import('./pages/ManajemenQRCodeSiswa'));
const AbsensiMobileOnlyNotice = lazy(() => import('./pages/AbsensiMobileOnlyNotice'));
const ManualAttendance = lazy(() => import('./pages/ManualAttendance'));
const MonitoringKelas = lazy(() => import('./pages/MonitoringKelas'));
const BackupManagement = lazy(() => import('./pages/BackupManagement'));
const SettingsHub = lazy(() => import('./pages/SettingsHub'));
const DataPegawaiLengkap = lazy(() => import('./pages/DataPegawaiLengkap'));
const DataSiswaLengkap = lazy(() => import('./pages/DataSiswaLengkap'));
const DataPribadiSaya = lazy(() => import('./pages/DataPribadiSaya'));
const LiveTrackingNew = lazy(() => import('./pages/LiveTrackingNew'));
const TestLiveTracking = lazy(() => import('./pages/TestLiveTracking'));
const DeviceManagement = lazy(() => import('./pages/DeviceManagement'));
const MobileReleaseManagement = lazy(() => import('./pages/MobileReleaseManagement'));
const MobileDownloadCenter = lazy(() => import('./pages/MobileDownloadCenter'));
const RekapKehadiranSaya = lazy(() => import('./pages/RekapKehadiranSaya'));
const SbtManagement = lazy(() => import('./pages/SbtManagement'));
const DapodikConnectionTest = lazy(() => import('./pages/DapodikConnectionTest'));
// const DashboardWaliKelas = lazy(() => import('./pages/DashboardWaliKelas'));

// Loading component with error boundary
const LazyComponent = ({ children }) => (
  <Suspense fallback={<LoadingScreen />}>
    {children}
  </Suspense>
);

const router = createBrowserRouter([
  // Public Routes
  {
    path: '/login',
    element: (
      <LazyComponent>
        <Login />
      </LazyComponent>
    ),
    errorElement: <ErrorBoundary />
  },
  {
    path: '/reset-password',
    element: (
      <LazyComponent>
        <ResetPassword />
      </LazyComponent>
    ),
    errorElement: <ErrorBoundary />
  },
  ...(FEATURE_FLAGS.liveTrackingTestRouteEnabled ? [{
    path: '/test-live-tracking',
    element: (
      <LazyComponent>
        <TestLiveTracking />
      </LazyComponent>
    ),
    errorElement: <ErrorBoundary />
  }] : []),
  
  // Protected Routes
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <Layout />
      </ProtectedRoute>
    ),
    errorElement: <ErrorBoundary />,
    children: [
      {
        path: '/pusat-aplikasi',
        element: (
          <LazyComponent>
            <MobileDownloadCenter />
          </LazyComponent>
        )
      },
      {
        path: '/unduh-mobile',
        element: <Navigate to="/pusat-aplikasi" replace />
      },
      {
        path: '/',
        element: (
          <LazyComponent>
            <Dashboard />
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-pengguna',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['manage_users', 'view_personal_data_verification']}>
              <ManajemenPengguna />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-pengguna/data-pribadi/:userId',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_users">
              <DataPribadiSaya />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-role',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_roles', 'manage_roles']}>
              <ManajemenRole />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/data-pribadi-saya',
        element: (
          <LazyComponent>
            <DataPribadiSaya />
          </LazyComponent>
        )
      },
      {
        path: '/data-pegawai-lengkap',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_pegawai', 'manage_pegawai']}>
              <DataPegawaiLengkap />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/data-siswa-lengkap',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_siswa', 'manage_students']}>
              <DataSiswaLengkap />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-kelas',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_kelas', 'manage_kelas']}>
              <ManajemenKelas />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/tahun-ajaran',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_tahun_ajaran', 'manage_tahun_ajaran']}>
              <ManajemenTahunAjaran />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/absensi-realtime',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_absensi">
              <AbsensiRealtime />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/persetujuan-izin-siswa',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyRoles={['Super_Admin', 'Admin', 'Wakasek_Kesiswaan', 'Wali Kelas']}>
              <PersetujuanIzin />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/monitoring-kelas',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyRoles={['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas']}>
              <MonitoringKelas />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/pengajuan-izin',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyRoles={['Siswa']}>
              <PengajuanIzin />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/rekap-kehadiran-saya',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyRoles={['Siswa']}>
              <RekapKehadiranSaya />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/notifikasi',
        element: (
          <LazyComponent>
            <NotificationCenter />
          </LazyComponent>
        )
      },
      {
        path: '/laporan-statistik',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_reports">
              <LaporanStatistik />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/kalender-akademik',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_tahun_ajaran', 'manage_periode_akademik', 'manage_event_akademik']}>
              <KalenderAkademik />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/master-mata-pelajaran',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_mapel', 'manage_mapel']}>
              <MasterMataPelajaran />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/penugasan-guru-mapel',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="assign_guru_mapel">
              <PenugasanGuruMapel />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/jadwal-pelajaran',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_jadwal_pelajaran', 'manage_jadwal_pelajaran']}>
              <JadwalPelajaran />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/pengaturan',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['manage_settings', 'manage_attendance_settings', 'manage_whatsapp', 'manage_backups', 'manage_broadcast_campaigns']}>
              <SettingsHub />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/broadcast-message',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['view_broadcast_campaigns', 'manage_broadcast_campaigns', 'send_broadcast_campaigns', 'retry_broadcast_campaigns']}>
              <BroadcastMessage />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/attendance-discipline-cases/:id',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredAnyPermissions={['manage_attendance_settings', 'send_broadcast_campaigns']}>
              <AttendanceDisciplineCaseDetail />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-lokasi-gps',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_settings">
              <ManajemenLokasiGPS />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/whatsapp-gateway',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_whatsapp">
              <WhatsAppGateway />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/pengaturan-sistem-absensi',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_attendance_settings">
              <AttendanceSchemaManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/skema-absensi',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_attendance_settings">
              <AttendanceSchemaManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-qr-code-siswa',
        element: FEATURE_FLAGS.attendanceQrEnabled ? (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_siswa">
              <ManajemenQRCodeSiswa />
            </ProtectedRoute>
          </LazyComponent>
        ) : <Navigate to="/" replace />
      },
      {
        path: '/absensi-selfie',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_absensi">
              <AbsensiMobileOnlyNotice />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/absensi-qr-code',
        element: FEATURE_FLAGS.attendanceQrEnabled ? (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_absensi">
              <AbsensiMobileOnlyNotice />
            </ProtectedRoute>
          </LazyComponent>
        ) : <Navigate to="/absensi-mobile-info" replace />
      },
      {
        path: '/absensi-mobile-info',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_absensi">
              <AbsensiMobileOnlyNotice />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/absensi-manual',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manual_attendance">
              <ManualAttendance />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/live-tracking',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="view_live_tracking">
              <LiveTrackingNew />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/device-management',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_settings">
              <DeviceManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/rilis-mobile',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_settings">
              <MobileReleaseManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/sbt-smanis',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_settings">
              <SbtManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/dapodik-test',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_settings">
              <DapodikConnectionTest />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      {
        path: '/manajemen-backup',
        element: (
          <LazyComponent>
            <ProtectedRoute requiredPermission="manage_backups">
              <BackupManagement />
            </ProtectedRoute>
          </LazyComponent>
        )
      },
      // {
      //   path: '/dashboard-wali-kelas',
      //   element: (
      //     <LazyComponent>
      //       <ProtectedRoute requiredPermission="view_wali_dashboard">
      //         <DashboardWaliKelas />
      //       </ProtectedRoute>
      //     </LazyComponent>
      //   )
      // }
    ]
  }
]);

export default router;

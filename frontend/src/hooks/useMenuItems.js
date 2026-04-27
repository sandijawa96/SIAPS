import { useMemo } from 'react';
import {
  LayoutDashboard, 
  Users, 
  Shield, 
  GraduationCap,
  Calendar,
  BookOpen,
  Clock,
  QrCode,
  FileCheck,
  BarChart2,
  MapPin,
  MessageSquare,
  Megaphone,
  Settings,
  Smartphone,
  UserCheck,
  UserPlus,
  Edit3
} from 'lucide-react';
import { FEATURE_FLAGS } from '../config/features';

export const useMenuItems = (hasPermission, hasAnyPermission = () => false, hasAnyRole = () => false) => {
  const isFeatureEnabled = (item) => {
    if (!item?.featureFlag) {
      return true;
    }
    return Boolean(FEATURE_FLAGS[item.featureFlag]);
  };

  return useMemo(() => [
    {
      id: 'dashboard',
      title: null,
      items: [
        {
          name: 'Dashboard',
          icon: LayoutDashboard,
          path: '/',
          permission: null
        }
      ]
    },
    {
      id: 'manajemen-data',
      title: 'MANAJEMEN DATA',
      items: [
        {
          name: 'Manajemen Pengguna',
          icon: Users,
          path: '/manajemen-pengguna',
          permissionsAny: ['manage_users', 'view_personal_data_verification']
        },
        {
          name: 'Verifikasi Data Pribadi',
          icon: FileCheck,
          path: '/manajemen-pengguna?tab=verifikasi',
          permission: 'view_personal_data_verification'
        },
        {
          name: 'Data Pegawai',
          icon: UserCheck,
          path: '/data-pegawai-lengkap',
          permissionsAny: ['view_pegawai', 'manage_pegawai'],
          badge: 'Baru'
        },
        {
          name: 'Data Siswa',
          icon: UserPlus,
          path: '/data-siswa-lengkap',
          permissionsAny: ['view_siswa', 'manage_students'],
          badge: 'Baru'
        },
        {
          name: 'Manajemen Role',
          icon: Shield,
          path: '/manajemen-role',
          permissionsAny: ['view_roles', 'manage_roles']
        },
        {
          name: 'Manajemen Kelas',
          icon: GraduationCap,
          path: '/manajemen-kelas',
          permissionsAny: ['view_kelas', 'manage_kelas']
        },
        {
          name: 'Tahun Ajaran',
          icon: Calendar,
          path: '/tahun-ajaran',
          permissionsAny: ['view_tahun_ajaran', 'manage_tahun_ajaran']
        },
        {
          name: 'Master Mata Pelajaran',
          icon: BookOpen,
          path: '/master-mata-pelajaran',
          permissionsAny: ['view_mapel', 'manage_mapel']
        }
      ]
    },
    {
      id: 'absensi',
      title: 'SISTEM ABSENSI',
      items: [
        {
          name: 'Absensi Realtime',
          icon: Clock,
          path: '/absensi-realtime',
          permission: 'view_absensi'
        },
        {
          name: 'Absensi Mobile',
          icon: Smartphone,
          path: '/absensi-mobile-info',
          permission: 'view_absensi',
          badge: 'Mobile App'
        },
        {
          name: 'Monitoring Kelas',
          icon: Users,
          path: '/monitoring-kelas',
          rolesAny: ['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas'],
          badge: 'Baru'
        },
        {
          name: 'Pengelolaan Absensi',
          icon: Edit3,
          path: '/absensi-manual',
          permission: 'manual_attendance',
          badge: 'Admin'
        },
        {
          name: 'QR Code Siswa',
          icon: QrCode,
          path: '/manajemen-qr-code-siswa',
          permission: 'view_siswa',
          featureFlag: 'attendanceQrEnabled'
        },
        {
          name: 'Persetujuan Izin',
          icon: FileCheck,
          children: [
            {
              name: 'Pengajuan Izin',
              path: '/pengajuan-izin',
              rolesAny: ['Siswa']
            },
            {
              name: 'Izin Siswa',
              path: '/persetujuan-izin-siswa',
              rolesAny: ['Super_Admin', 'Admin', 'Wakasek_Kesiswaan', 'Wali Kelas']
            }
          ]
        }
      ]
    },
    {
      id: 'laporan',
      title: 'LAPORAN & ANALISIS',
      items: [
        {
          name: 'Laporan & Statistik',
          icon: BarChart2,
          path: '/laporan-statistik',
          permission: 'view_reports'
        }
      ]
    },
    {
      id: 'sistem',
      title: 'PENGATURAN SISTEM',
      items: [
        {
          name: 'Pengaturan Utama',
          icon: Settings,
          path: '/pengaturan',
          permissionsAny: ['manage_settings', 'manage_attendance_settings', 'manage_whatsapp', 'manage_backups', 'manage_broadcast_campaigns'],
          badge: 'Baru'
        },
        {
          name: 'Broadcast Message',
          icon: Megaphone,
          path: '/broadcast-message',
          permissionsAny: ['view_broadcast_campaigns', 'manage_broadcast_campaigns', 'send_broadcast_campaigns', 'retry_broadcast_campaigns'],
          badge: 'Baru'
        },
        {
          name: 'Kalender Akademik',
          icon: Calendar,
          path: '/kalender-akademik',
          permissionsAny: ['view_tahun_ajaran', 'manage_periode_akademik', 'manage_event_akademik']
        },
        {
          name: 'Lokasi GPS',
          icon: MapPin,
          path: '/manajemen-lokasi-gps',
          permission: 'manage_settings'
        },
        {
          name: 'WhatsApp Gateway',
          icon: MessageSquare,
          path: '/whatsapp-gateway',
          permission: 'manage_whatsapp'
        },
        {
          name: 'Pengaturan Absensi',
          icon: Settings,
          path: '/pengaturan-sistem-absensi',
          permission: 'manage_attendance_settings',
          badge: 'Penting'
        }
      ]
    }
  ].map(section => ({
    ...section,
    items: section.items.filter(item => {
      if (!isFeatureEnabled(item)) {
        return false;
      }

      if (Array.isArray(item.rolesAny) && item.rolesAny.length > 0 && !hasAnyRole(item.rolesAny)) {
        return false;
      }

      if (Array.isArray(item.permissionsAny) && item.permissionsAny.length > 0 && !hasAnyPermission(item.permissionsAny)) {
        return false;
      }

      if (item.children) {
        const filteredChildren = item.children.filter(child => 
          isFeatureEnabled(child)
          && (!child.permission || hasPermission(child.permission))
          && (!child.permissionsAny || hasAnyPermission(child.permissionsAny))
          && (!child.rolesAny || hasAnyRole(child.rolesAny))
        );
        return filteredChildren.length > 0;
      }
      return !item.permission || hasPermission(item.permission);
    }).map(item => {
      if (item.children) {
        return {
          ...item,
          children: item.children.filter(child => 
            isFeatureEnabled(child)
            && (!child.permission || hasPermission(child.permission))
            && (!child.permissionsAny || hasAnyPermission(child.permissionsAny))
            && (!child.rolesAny || hasAnyRole(child.rolesAny))
          )
        };
      }
      return item;
    })
  })).filter(section => section.items.length > 0), [hasAnyPermission, hasAnyRole, hasPermission]);
};

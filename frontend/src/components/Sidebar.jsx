import React, { memo, useMemo, useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import {
  Box,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Typography,
  Avatar,
  Divider,
  IconButton,
  useTheme,
  Collapse
} from '@mui/material';
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
  BarChart3,
  MapPin,
  MessageSquare,
  Megaphone,
  Settings,
  UserPlus,
  X,
  ChevronDown,
  ChevronUp,
  UserCircle,
  CheckCircle,
  Navigation,
  Smartphone,
  Layers,
  UserCheck,
  HardDrive
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { useSidebarController } from '../hooks/useSidebarController';
import { FEATURE_FLAGS } from '../config/features';
import { simpleAttendanceAPI } from '../services/api';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';

const menuCategories = [
  {
    name: 'Dashboard',
    icon: LayoutDashboard,
    path: '/',
    permission: null
  },
  {
    name: 'Manajemen Pengguna',
    icon: UserCircle,
    children: [
      {
        name: 'Manajemen User',
        icon: Users,
        path: '/manajemen-pengguna',
        permission: 'manage_users'
      },
      {
        name: 'Verifikasi Data Pribadi',
        icon: CheckCircle,
        path: '/manajemen-pengguna?tab=verifikasi',
        permission: 'view_personal_data_verification'
      },
      {
        name: 'Manajemen Role',
        icon: Shield,
        path: '/manajemen-role',
        permissionsAny: ['view_roles', 'manage_roles']
      },
      {
        name: 'Data Pegawai',
        icon: Users,
        path: '/data-pegawai-lengkap',
        permissionsAny: ['view_pegawai', 'manage_pegawai']
      },
      {
        name: 'Data Siswa',
        icon: UserPlus,
        path: '/data-siswa-lengkap',
        permissionsAny: ['view_siswa', 'manage_students']
      }
    ]
  },
  {
    name: 'Manajemen Akademik',
    icon: GraduationCap,
    children: [
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
        name: 'Kalender Akademik',
        icon: Calendar,
        path: '/kalender-akademik',
        permissionsAny: ['view_tahun_ajaran', 'manage_periode_akademik', 'manage_event_akademik']
      },
      {
        name: 'Master Mata Pelajaran',
        icon: BookOpen,
        path: '/master-mata-pelajaran',
        permissionsAny: ['view_mapel', 'manage_mapel']
      },
      {
        name: 'Penugasan Guru-Mapel',
        icon: UserCheck,
        path: '/penugasan-guru-mapel',
        permission: 'assign_guru_mapel'
      },
      {
        name: 'Jadwal Pelajaran',
        icon: Clock,
        path: '/jadwal-pelajaran',
        permissionsAny: ['view_jadwal_pelajaran', 'manage_jadwal_pelajaran']
      }
    ]
  },
  {
    name: 'Sistem Absensi',
    icon: CheckCircle,
    children: [
      {
        name: 'Absensi Real-time',
        icon: Clock,
        path: '/absensi-realtime',
        permission: 'view_absensi'
      },
      {
        name: 'Absensi Mobile',
        icon: Smartphone,
        path: '/absensi-mobile-info',
        permission: 'view_absensi'
      },
      {
        name: 'Monitoring Kelas',
        icon: Users,
        path: '/monitoring-kelas',
        rolesAny: ['Super_Admin', 'Wakasek_Kesiswaan', 'Wali Kelas']
      },
      {
        name: 'Rekap Kehadiran',
        icon: BarChart3,
        path: '/rekap-kehadiran-saya',
        rolesAny: ['Siswa']
      },
      {
        name: 'Pengelolaan Absensi',
        icon: UserCheck,
        path: '/absensi-manual',
        permission: 'manual_attendance'
      },
      {
        name: 'QR Code Siswa',
        icon: QrCode,
        path: '/manajemen-qr-code-siswa',
        permission: 'view_siswa',
        featureFlag: 'attendanceQrEnabled'
      },
      {
        name: 'Live Tracking',
        icon: Navigation,
        path: '/live-tracking',
        permission: 'view_live_tracking'
      },
      {
        name: 'Pengajuan Izin',
        icon: FileCheck,
        path: '/pengajuan-izin',
        rolesAny: ['Siswa']
      },
      {
        name: 'Persetujuan Izin',
        icon: FileCheck,
        path: '/persetujuan-izin-siswa',
        rolesAny: ['Super_Admin', 'Admin', 'Wakasek_Kesiswaan', 'Wali Kelas']
      }
    ]
  },
  {
    name: 'Laporan & Analisis',
    icon: BarChart3,
    children: [
      {
        name: 'Laporan & Statistik',
        icon: BarChart3,
        path: '/laporan-statistik',
        permission: 'view_reports'
      }
    ]
  },
  {
    name: 'Pengaturan Sistem',
    icon: Settings,
    children: [
      {
        name: 'Pengaturan Utama',
        icon: Settings,
        path: '/pengaturan',
        permissionsAny: ['manage_settings', 'manage_attendance_settings', 'manage_whatsapp', 'manage_backups', 'manage_broadcast_campaigns']
      },
      {
        name: 'Broadcast Message',
        icon: Megaphone,
        path: '/broadcast-message',
        permissionsAny: ['view_broadcast_campaigns', 'manage_broadcast_campaigns', 'send_broadcast_campaigns', 'retry_broadcast_campaigns']
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
        icon: Layers,
        path: '/pengaturan-sistem-absensi',
        permission: 'manage_attendance_settings'
      },
      {
        name: 'Device Management',
        icon: Smartphone,
        path: '/device-management',
        permission: 'manage_settings'
      },
      {
        name: 'SBT SMANIS',
        icon: Smartphone,
        path: '/sbt-smanis',
        permission: 'manage_settings'
      },
      {
        name: 'Manajemen Backup',
        icon: HardDrive,
        path: '/manajemen-backup',
        permission: 'manage_backups'
      }
    ]
  }
];

const Sidebar = memo(({ onClose }) => {
  const location = useLocation();
  const { user, hasPermission, hasAnyPermission, hasAnyRole, roles = [] } = useAuth();
  const theme = useTheme();
  const { openCategories, toggleCategory, setActiveCategory } = useSidebarController();
  const [attendanceScope, setAttendanceScope] = useState(null);
  const displayName = user?.nama_lengkap || user?.name || user?.nama || user?.full_name || user?.username || 'User';
  const displayRole = user?.role || roles?.[0] || 'Role';
  const userPhotoUrl = resolveProfilePhotoUrl(user?.foto_profil_url || user?.foto_profil);

  const isActive = (path) => location.pathname === path;
  const isStudentUser = useMemo(
    () =>
      roles.some((roleName) =>
        String(roleName || '')
          .trim()
          .toLowerCase()
          .replace(/[_\s]+/g, ' ') === 'siswa'
      ),
    [roles]
  );

  const isCategoryActive = (category) => {
    if (category.path) return isActive(category.path);
    return category.children?.some(child => isActive(child.path));
  };

  const isFeatureEnabled = (item) => {
    if (!item?.featureFlag) {
      return true;
    }
    return Boolean(FEATURE_FLAGS[item.featureFlag]);
  };

  const filteredCategories = useMemo(() => {
    const isMenuAllowed = (item) => {
      if (!item) {
        return false;
      }

      if (Array.isArray(item.rolesAny) && item.rolesAny.length > 0 && !hasAnyRole(item.rolesAny)) {
        return false;
      }

      if (Array.isArray(item.permissionsAny) && item.permissionsAny.length > 0) {
        return hasAnyPermission(item.permissionsAny);
      }

      return !item.permission || hasPermission(item.permission);
    };

    const isAttendanceUserMenuAllowed = (item) => {
      if (!item?.path) return true;

      const isUserAttendancePage =
        item.path === '/absensi-selfie'
        || item.path === '/absensi-qr-code'
        || item.path === '/absensi-mobile-info';
      if (!isUserAttendancePage) return true;

      if (attendanceScope === 'siswa_only' && !isStudentUser) {
        return false;
      }

      return true;
    };

    return menuCategories.filter(category => {
      if (category.path) {
        return isFeatureEnabled(category) &&
          isAttendanceUserMenuAllowed(category) &&
          isMenuAllowed(category);
      }
      return category.children?.some(child => 
        isFeatureEnabled(child) &&
        isAttendanceUserMenuAllowed(child) &&
        isMenuAllowed(child)
      );
    }).map(category => ({
      ...category,
      children: category.children?.filter(child => 
        isFeatureEnabled(child) &&
        isAttendanceUserMenuAllowed(child) &&
        isMenuAllowed(child)
      )
    }));
  }, [attendanceScope, hasAnyPermission, hasAnyRole, hasPermission, isStudentUser]);

  useEffect(() => {
    let active = true;

    simpleAttendanceAPI
      .getGlobalSettings()
      .then((response) => {
        if (!active) return;
        // Scope absensi web dikunci ke siswa_only sesuai policy aplikasi.
        setAttendanceScope('siswa_only');
      })
      .catch(() => {
        if (!active) return;
        setAttendanceScope('siswa_only');
      });

    return () => {
      active = false;
    };
  }, []);

  // Auto-expand active categories on mount
  useEffect(() => {
    const activeCategory = filteredCategories.find(category => 
      isCategoryActive(category)
    );
    if (activeCategory && activeCategory.children) {
      setActiveCategory(activeCategory.name);
    }
  }, [location.pathname]);

  return (
    <Box
      sx={{
        height: '100vh',
        display: 'flex',
        flexDirection: 'column',
        background: `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
        color: 'white',
        position: 'relative',
        overflow: 'hidden',
        borderTopLeftRadius: 0,
        borderTopRightRadius: 0,
        borderBottomLeftRadius: 0,
        borderBottomRightRadius: 16
      }}
    >
      {/* Logo */}
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          px: 3,
          py: 2.5,
          minHeight: 64,
          borderBottom: '1px solid rgba(255,255,255,0.1)',
          background: 'rgba(255,255,255,0.05)',
          backdropFilter: 'blur(10px)',
          position: 'relative',
          zIndex: 1,
          borderTopLeftRadius: 0,
          borderTopRightRadius: 0
        }}
      >
        <Box
          component={Link}
          to="/"
          sx={{
            display: 'flex',
            alignItems: 'center',
            textDecoration: 'none',
            color: 'inherit',
            flex: 1
          }}
        >
          <Box
            sx={{
              width: 40,
              height: 40,
              borderRadius: 2,
              background: 'rgba(255,255,255,0.95)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              mr: 2,
              border: '1px solid rgba(255,255,255,0.2)',
              overflow: 'hidden',
              p: 0.5
            }}
          >
            <Box
              component="img"
              src="/icon.png"
              alt="Logo SIAPS"
              sx={{ width: '100%', height: '100%', objectFit: 'contain' }}
            />
          </Box>
          <Typography variant="h6" sx={{ fontWeight: 600, color: 'white', fontSize: '1rem' }}>
            SIAP Absensi
          </Typography>
        </Box>
        
        {/* Close button for mobile */}
        {onClose && (
          <IconButton
            onClick={onClose}
            sx={{
              display: { lg: 'none' },
              color: 'white'
            }}
          >
            <X size={20} />
          </IconButton>
        )}
      </Box>

      {/* Navigation */}
      <Box 
        sx={{ 
          flex: 1, 
          overflowY: 'auto', 
          overflowX: 'hidden',
          position: 'relative', 
          zIndex: 1,
          '&::-webkit-scrollbar': {
            width: '6px'
          },
          '&::-webkit-scrollbar-track': {
            background: 'rgba(255,255,255,0.1)',
            borderRadius: '3px'
          },
          '&::-webkit-scrollbar-thumb': {
            background: 'rgba(255,255,255,0.3)',
            borderRadius: '3px',
            '&:hover': {
              background: 'rgba(255,255,255,0.4)'
            }
          }
        }}
      >
        <List sx={{ px: 1.5, py: 1 }}>
          {filteredCategories.map((category) => {
            const Icon = category.icon;
            const active = isCategoryActive(category);
            const isOpen = openCategories[category.name];
            
            if (category.path) {
              // Single menu item
              return (
                <ListItem key={category.name} disablePadding sx={{ mb: 0.5 }}>
                  <ListItemButton
                    component={Link}
                    to={category.path}
                    onClick={onClose}
                    sx={{
                      py: 1.5,
                      px: 2,
                      borderRadius: 0,
                      backgroundColor: active ? 'rgba(255,255,255,0.12)' : 'transparent',
                      transition: 'all 0.2s ease-in-out',
                      position: 'relative',
                      '&:hover': {
                        backgroundColor: active ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.08)',
                        transform: 'translateX(2px)'
                      },
                      '&::before': active ? {
                        content: '""',
                        position: 'absolute',
                        left: 0,
                        top: '50%',
                        transform: 'translateY(-50%)',
                        width: '3px',
                        height: '60%',
                        backgroundColor: 'white',
                        borderRadius: '0 2px 2px 0'
                      } : {}
                    }}
                  >
                    <ListItemIcon sx={{ color: 'white', minWidth: 40 }}>
                      <Icon size={20} />
                    </ListItemIcon>
                    <ListItemText
                      primary={category.name}
                      primaryTypographyProps={{
                        fontSize: '0.875rem',
                        fontWeight: active ? 600 : 500,
                        color: 'white'
                      }}
                    />
                  </ListItemButton>
                </ListItem>
              );
            }

            // Category with children
            return (
              <React.Fragment key={category.name}>
                {/* Category Header */}
                <ListItem disablePadding sx={{ mb: 0.5 }}>
                  <ListItemButton
                    onClick={() => toggleCategory(category.name)}
                    sx={{
                      py: 1.5,
                      px: 2,
                      borderRadius: 0,
                      backgroundColor: active ? 'rgba(255,255,255,0.08)' : 'transparent',
                      transition: 'all 0.2s ease-in-out',
                      '&:hover': {
                        backgroundColor: 'rgba(255,255,255,0.08)',
                        transform: 'translateX(1px)'
                      }
                    }}
                  >
                    <ListItemIcon sx={{ color: 'white', minWidth: 40 }}>
                      <Icon size={20} />
                    </ListItemIcon>
                    <ListItemText
                      primary={category.name}
                      primaryTypographyProps={{
                        fontSize: '0.875rem',
                        fontWeight: 600,
                        color: 'white'
                      }}
                    />
                    <Box
                      sx={{
                        transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
                        transition: 'transform 0.2s ease-in-out'
                      }}
                    >
                      <ChevronDown size={18} />
                    </Box>
                  </ListItemButton>
                </ListItem>
                
                {/* Submenu Items */}
                <Collapse 
                  in={isOpen} 
                  timeout={200}
                >
                  <List component="div" disablePadding sx={{ pl: 1, mb: 0.5 }}>
                    {category.children?.map((child) => {
                      const ChildIcon = child.icon;
                      const childActive = isActive(child.path);
                      
                      return (
                        <ListItem key={child.name} disablePadding sx={{ mb: 0.25 }}>
                          <ListItemButton
                            component={Link}
                            to={child.path}
                            onClick={onClose}
                            sx={{
                              py: 1.25,
                              px: 2,
                              ml: 1,
                              borderRadius: 0,
                              backgroundColor: childActive ? 'rgba(255,255,255,0.12)' : 'transparent',
                              transition: 'all 0.2s ease-in-out',
                              position: 'relative',
                              '&:hover': {
                                backgroundColor: childActive ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.06)',
                                transform: 'translateX(2px)'
                              },
                              '&::before': childActive ? {
                                content: '""',
                                position: 'absolute',
                                left: 0,
                                top: '50%',
                                transform: 'translateY(-50%)',
                                width: '2px',
                                height: '50%',
                                backgroundColor: 'white',
                                borderRadius: '0 1px 1px 0'
                              } : {}
                            }}
                          >
                            <ListItemIcon sx={{ color: 'rgba(255,255,255,0.9)', minWidth: 36 }}>
                              <ChildIcon size={16} />
                            </ListItemIcon>
                            <ListItemText
                              primary={child.name}
                              primaryTypographyProps={{
                                fontSize: '0.8125rem',
                                fontWeight: childActive ? 600 : 400,
                                color: childActive ? 'white' : 'rgba(255,255,255,0.9)'
                              }}
                            />
                          </ListItemButton>
                        </ListItem>
                      );
                    })}
                  </List>
                </Collapse>
              </React.Fragment>
            );
          })}
        </List>
      </Box>

      <Box
        sx={{
          px: 1.5,
          pb: 1.5,
          position: 'relative',
          zIndex: 1
        }}
      >
        <Box
          sx={{
            position: 'relative',
            overflow: 'hidden',
            borderRadius: 3,
            background: isActive('/pusat-aplikasi')
              ? 'linear-gradient(135deg, rgba(34,211,238,0.36) 0%, rgba(59,130,246,0.28) 52%, rgba(255,255,255,0.16) 100%)'
              : 'linear-gradient(135deg, rgba(34,211,238,0.18) 0%, rgba(15,23,42,0.18) 55%, rgba(255,255,255,0.08) 100%)',
            border: '1px solid rgba(255,255,255,0.16)',
            boxShadow: isActive('/pusat-aplikasi')
              ? '0 16px 28px rgba(8,145,178,0.28)'
              : '0 10px 22px rgba(15,23,42,0.18)',
            '&::after': {
              content: '""',
              position: 'absolute',
              top: -28,
              right: -18,
              width: 108,
              height: 108,
              borderRadius: '50%',
              background: 'radial-gradient(circle, rgba(255,255,255,0.24) 0%, rgba(255,255,255,0) 72%)',
              pointerEvents: 'none'
            }
          }}
        >
          <Box sx={{ px: 2, pt: 1.4 }}>
            <Typography
              sx={{
                fontSize: '0.64rem',
                fontWeight: 700,
                letterSpacing: '0.22em',
                color: 'rgba(207,250,254,0.92)'
              }}
            >
              AKSES CEPAT
            </Typography>
          </Box>

          <ListItemButton
            component={Link}
            to="/pusat-aplikasi"
            onClick={onClose}
            sx={{
              py: 1.45,
              px: 2,
              borderRadius: 3,
              alignItems: 'flex-start',
              backgroundColor: 'transparent',
              transition: 'all 0.2s ease-in-out',
              position: 'relative',
              '&:hover': {
                backgroundColor: 'rgba(255,255,255,0.06)',
                transform: 'translateY(-1px)'
              },
              '&::before': isActive('/pusat-aplikasi') ? {
                content: '""',
                position: 'absolute',
                inset: 0,
                borderRadius: 3,
                border: '1px solid rgba(255,255,255,0.28)'
              } : {}
            }}
          >
            <ListItemIcon sx={{ color: 'white', minWidth: 48, mt: 0.15 }}>
              <Box
                sx={{
                  width: 36,
                  height: 36,
                  borderRadius: 2.5,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  background: 'rgba(255,255,255,0.18)',
                  border: '1px solid rgba(255,255,255,0.18)',
                  boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.12)'
                }}
              >
                <Smartphone size={19} />
              </Box>
            </ListItemIcon>
            <ListItemText
              primary="Pusat Aplikasi"
              secondary="Unduh aplikasi internal"
              primaryTypographyProps={{
                fontSize: '0.95rem',
                fontWeight: 700,
                color: 'white'
              }}
              secondaryTypographyProps={{
                fontSize: '0.76rem',
                sx: {
                  mt: 0.4,
                  color: 'rgba(255,255,255,0.78)',
                  lineHeight: 1.35
                }
              }}
            />
          </ListItemButton>
        </Box>
      </Box>

      {/* User info */}
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          px: 2,
          py: 2,
          gap: 2,
          borderTop: '1px solid rgba(255,255,255,0.1)',
          background: 'rgba(0,0,0,0.1)',
          position: 'relative',
          zIndex: 1
        }}
      >
        <Avatar
          src={userPhotoUrl || undefined}
          sx={{
            width: 32,
            height: 32,
            background: 'rgba(255,255,255,0.15)',
            border: '1px solid rgba(255,255,255,0.2)',
            fontSize: '0.875rem',
            fontWeight: 'bold'
          }}
        >
          {!userPhotoUrl ? displayName.charAt(0).toUpperCase() : null}
        </Avatar>
        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Typography variant="body2" sx={{ fontWeight: 600, color: 'white', fontSize: '0.875rem' }}>
            {displayName}
          </Typography>
          <Typography variant="caption" sx={{ color: 'rgba(255,255,255,0.7)', textTransform: 'capitalize', fontSize: '0.7rem' }}>
            {displayRole}
          </Typography>
        </Box>
      </Box>
    </Box>
  );
});

Sidebar.displayName = 'Sidebar';

export default Sidebar;

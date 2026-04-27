import React from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Avatar,
  Chip,
  IconButton,
  Menu,
  MenuItem,
  Typography,
  Box,
  Skeleton,
  TableSortLabel
} from '@mui/material';
import {
  MoreVertical,
  Camera,
  Edit,
  Trash2,
  Key,
  ToggleLeft,
  ToggleRight,
  User,
  GraduationCap
} from 'lucide-react';
import { resolveProfilePhotoUrl } from '../../utils/profilePhoto';

const UserTable = ({
  users = [],
  loading = false,
  activeTab,
  selectedUsers = [],
  onSelectUser,
  onSelectAll,
  onEdit,
  onDelete,
  onResetPassword,
  onToggleStatus,
  onManageFaceTemplate,
  canManageFaceTemplate = false,
  sortConfig,
  onSort
}) => {
  const normalizeUserId = (id) => String(id);
  const headerCheckboxRef = React.useRef(null);
  const resolveUserId = (user) => (
    user?.id
    ?? user?.user_id
    ?? user?.userId
    ?? user?.user?.id
    ?? user?.data_pribadi_siswa?.user_id
    ?? user?.data_pribadi_siswa?.id
    ?? user?.dataPribadiSiswa?.user_id
    ?? user?.dataPribadiSiswa?.id
    ?? null
  );
  const [anchorEl, setAnchorEl] = React.useState(null);
  const [selectedRowId, setSelectedRowId] = React.useState(null);

  const handleMenuOpen = (event, userId) => {
    setAnchorEl(event.currentTarget);
    setSelectedRowId(userId);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
    setSelectedRowId(null);
  };

  const handleMenuAction = (action) => {
    const user = users.find((u) => normalizeUserId(resolveUserId(u)) === normalizeUserId(selectedRowId));
    if (user) {
      switch (action) {
        case 'edit':
          onEdit(user);
          break;
        case 'delete':
          onDelete(selectedRowId);
          break;
        case 'resetPassword':
          onResetPassword(user);
          break;
        case 'toggleStatus':
          onToggleStatus(selectedRowId, user.is_active);
          break;
        case 'faceTemplate':
          onManageFaceTemplate?.(user);
          break;
      }
    }
    handleMenuClose();
  };

  const selectedUserIdSet = new Set((selectedUsers || []).map((id) => normalizeUserId(id)));
  const selectedOnPageCount = users.reduce((count, user) => (
    selectedUserIdSet.has(normalizeUserId(resolveUserId(user))) ? count + 1 : count
  ), 0);
  const isAllSelected = users.length > 0 && selectedOnPageCount === users.length;
  const isIndeterminate = selectedOnPageCount > 0 && selectedOnPageCount < users.length;

  React.useEffect(() => {
    if (headerCheckboxRef.current) {
      headerCheckboxRef.current.indeterminate = isIndeterminate;
    }
  }, [isIndeterminate]);

  const getDisplayName = (user) => {
    if (activeTab === 'pegawai') {
      return user.nama_lengkap || user.name || 'Tidak ada nama';
    } else {
      return user.data_pribadi_siswa?.nama_lengkap || user.nama_lengkap || user.name || 'Tidak ada nama';
    }
  };

  const getIdentifier = (user) => {
    if (activeTab === 'pegawai') {
      return user.nip || user.email || '-';
    } else {
      return user.data_pribadi_siswa?.nis || user.nis || '-';
    }
  };

  const getRoles = (user) => {
    if (activeTab === 'pegawai' && user.roles) {
      return user.roles.map(role => role.name).join(', ');
    }
    return '-';
  };

  const getKelasStartTimestamp = (kelasItem) => {
    const rawDate = kelasItem?.pivot?.tanggal_masuk ?? kelasItem?.pivot?.created_at ?? null;
    if (!rawDate) {
      return Number.POSITIVE_INFINITY;
    }

    const timestamp = Date.parse(String(rawDate));
    return Number.isNaN(timestamp) ? Number.POSITIVE_INFINITY : timestamp;
  };

  const getKelasAwal = (user) => {
    if (activeTab !== 'siswa') {
      return '-';
    }

    if (user.kelas_awal?.nama_kelas || user.kelas_awal?.namaKelas) {
      return user.kelas_awal.nama_kelas || user.kelas_awal.namaKelas;
    }

    if (Array.isArray(user.kelas) && user.kelas.length > 0) {
      const sortedByStartDate = [...user.kelas].sort((a, b) => {
        const dateDiff = getKelasStartTimestamp(a) - getKelasStartTimestamp(b);
        if (dateDiff !== 0) {
          return dateDiff;
        }

        const aId = Number(a?.id || 0);
        const bId = Number(b?.id || 0);
        return aId - bId;
      });
      const initialKelas = sortedByStartDate[0];

      return initialKelas?.nama_kelas || initialKelas?.namaKelas || '-';
    }

    // Backward compatibility for older payload shape
    if (user.data_pribadi_siswa?.kelas) {
      return user.data_pribadi_siswa.kelas.nama_kelas || user.data_pribadi_siswa.kelas.namaKelas || '-';
    }

    return '-';
  };

  const hasActiveFaceTemplate = (user) => {
    return Boolean(user?.has_active_face_template ?? user?.hasActiveFaceTemplate ?? false);
  };

  if (loading) {
    return (
      <TableContainer component={Paper} className="shadow-sm">
        <Table>
          <TableHead>
            <TableRow className="bg-gray-50">
              <TableCell padding="checkbox">
                <Skeleton variant="rectangular" width={20} height={20} />
              </TableCell>
              <TableCell><Skeleton variant="text" /></TableCell>
              <TableCell><Skeleton variant="text" /></TableCell>
              <TableCell><Skeleton variant="text" /></TableCell>
              <TableCell><Skeleton variant="text" /></TableCell>
              <TableCell><Skeleton variant="text" /></TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {[...Array(5)].map((_, index) => (
              <TableRow key={index}>
                <TableCell padding="checkbox">
                  <Skeleton variant="rectangular" width={20} height={20} />
                </TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    );
  }

  return (
    <TableContainer component={Paper} className="shadow-sm">
      <Table>
        <TableHead>
          <TableRow className="bg-gray-50">
            <TableCell padding="checkbox">
              <input
                ref={headerCheckboxRef}
                type="checkbox"
                checked={isAllSelected}
                onChange={() => {}}
                onClick={(event) => {
                  event.stopPropagation();
                  onSelectAll(!isAllSelected);
                }}
                className="h-4 w-4 cursor-pointer rounded border border-gray-300 text-blue-600 accent-blue-600"
              />
            </TableCell>
            <TableCell>
              <TableSortLabel
                active={sortConfig?.field === 'nama_lengkap'}
                direction={sortConfig?.direction || 'asc'}
                onClick={() => onSort('nama_lengkap')}
              >
                <Box className="flex items-center gap-2">
                  {activeTab === 'pegawai' ? (
                    <User className="w-4 h-4" />
                  ) : (
                    <GraduationCap className="w-4 h-4" />
                  )}
                  Nama
                </Box>
              </TableSortLabel>
            </TableCell>
            <TableCell>
              <TableSortLabel
                active={sortConfig?.field === (activeTab === 'pegawai' ? 'nip' : 'nis')}
                direction={sortConfig?.direction || 'asc'}
                onClick={() => onSort(activeTab === 'pegawai' ? 'nip' : 'nis')}
              >
                {activeTab === 'pegawai' ? 'NIP' : 'NIS'}
              </TableSortLabel>
            </TableCell>
            <TableCell>
              <TableSortLabel
                active={sortConfig?.field === 'email'}
                direction={sortConfig?.direction || 'asc'}
                onClick={() => onSort('email')}
              >
                Email
              </TableSortLabel>
            </TableCell>
            {activeTab === 'pegawai' && (
              <TableCell>
                <TableSortLabel
                  active={sortConfig?.field === 'role'}
                  direction={sortConfig?.direction || 'asc'}
                  onClick={() => onSort('role')}
                >
                  Role
                </TableSortLabel>
              </TableCell>
            )}
            {activeTab === 'siswa' && (
              <TableCell>Kelas Awal</TableCell>
            )}
            <TableCell>Status</TableCell>
            <TableCell align="center">Aksi</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {users.length === 0 ? (
            <TableRow>
              <TableCell colSpan={activeTab === 'pegawai' ? 7 : 7} align="center" className="py-8">
                <Typography variant="body2" color="textSecondary">
                  Tidak ada data {activeTab === 'pegawai' ? 'pegawai' : 'siswa'}
                </Typography>
              </TableCell>
            </TableRow>
          ) : (
            users.map((user, rowIndex) => (
              <TableRow
                key={resolveUserId(user) ?? `${user.username || 'user'}-${user.email || rowIndex}`}
                hover
                className="hover:bg-gray-50 transition-colors"
              >
                <TableCell padding="checkbox">
                  {(() => {
                    const resolvedId = resolveUserId(user);
                    const hasValidId = resolvedId !== null && resolvedId !== undefined && String(resolvedId).trim() !== '';
                    const normalizedId = hasValidId ? normalizeUserId(resolvedId) : null;

                    return (
                      <input
                        type="checkbox"
                        checked={Boolean(normalizedId && selectedUserIdSet.has(normalizedId))}
                        onChange={() => {}}
                        onClick={(event) => {
                          event.stopPropagation();
                          if (hasValidId) {
                            onSelectUser(resolvedId);
                          }
                        }}
                        disabled={!hasValidId}
                        className={`h-4 w-4 rounded border border-gray-300 text-blue-600 accent-blue-600 ${
                          hasValidId ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'
                        }`}
                      />
                    );
                  })()}
                </TableCell>
                <TableCell>
                  <Box className="flex items-center gap-3">
                    <Avatar
                      src={resolveProfilePhotoUrl(user.foto_profil_url || user.foto_profil) || undefined}
                      alt={getDisplayName(user)}
                      className="w-8 h-8"
                    >
                      {getDisplayName(user).charAt(0).toUpperCase()}
                    </Avatar>
                    <Box>
                      <Typography variant="body2" className="font-medium">
                        {getDisplayName(user)}
                      </Typography>
                      <Box className="flex items-center gap-2 flex-wrap">
                        <Typography variant="caption" color="textSecondary">
                          {user.username}
                        </Typography>
                        {activeTab === 'siswa' && (
                          <Chip
                            label={hasActiveFaceTemplate(user) ? 'Face aktif' : 'Belum face'}
                            color={hasActiveFaceTemplate(user) ? 'success' : 'default'}
                            variant="outlined"
                            size="small"
                            sx={{ height: 20, '& .MuiChip-label': { px: 1, fontSize: '0.65rem' } }}
                          />
                        )}
                      </Box>
                    </Box>
                  </Box>
                </TableCell>
                <TableCell>
                  <Typography variant="body2">
                    {getIdentifier(user)}
                  </Typography>
                </TableCell>
                <TableCell>
                  <Typography variant="body2" className="text-gray-600">
                    {user.email || '-'}
                  </Typography>
                </TableCell>
                {activeTab === 'pegawai' && (
                  <TableCell>
                    <Typography variant="body2">
                      {getRoles(user)}
                    </Typography>
                  </TableCell>
                )}
                {activeTab === 'siswa' && (
                  <TableCell>
                    <Typography variant="body2">
                      {getKelasAwal(user)}
                    </Typography>
                  </TableCell>
                )}
                <TableCell>
                  <Chip
                    label={user.is_active ? 'Aktif' : 'Non-aktif'}
                    color={user.is_active ? 'success' : 'error'}
                    variant="outlined"
                    size="small"
                  />
                </TableCell>
                <TableCell align="center">
                  {(() => {
                    const resolvedId = resolveUserId(user);
                    const hasValidId = resolvedId !== null && resolvedId !== undefined && String(resolvedId).trim() !== '';

                    return (
                  <IconButton
                    size="small"
                    onClick={(e) => hasValidId && handleMenuOpen(e, resolvedId)}
                    disabled={!hasValidId}
                    className="hover:bg-gray-100"
                  >
                    <MoreVertical className="w-4 h-4" />
                  </IconButton>
                    );
                  })()}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {/* Action Menu */}
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={handleMenuClose}
        transformOrigin={{ horizontal: 'right', vertical: 'top' }}
        anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
      >
        <MenuItem onClick={() => handleMenuAction('edit')}>
          <Edit className="w-4 h-4 mr-2" />
          Edit
        </MenuItem>
        <MenuItem onClick={() => handleMenuAction('resetPassword')}>
          <Key className="w-4 h-4 mr-2" />
          Reset Password
        </MenuItem>
        {activeTab === 'siswa' && canManageFaceTemplate && (
          <MenuItem onClick={() => handleMenuAction('faceTemplate')}>
            <Camera className="w-4 h-4 mr-2" />
            Template Wajah
          </MenuItem>
        )}
        <MenuItem onClick={() => handleMenuAction('toggleStatus')}>
          {users.find((u) => normalizeUserId(resolveUserId(u)) === normalizeUserId(selectedRowId))?.is_active ? (
            <>
              <ToggleLeft className="w-4 h-4 mr-2" />
              Nonaktifkan
            </>
          ) : (
            <>
              <ToggleRight className="w-4 h-4 mr-2" />
              Aktifkan
            </>
          )}
        </MenuItem>
        <MenuItem onClick={() => handleMenuAction('delete')} className="text-red-600">
          <Trash2 className="w-4 h-4 mr-2" />
          Hapus
        </MenuItem>
      </Menu>
    </TableContainer>
  );
};

export default UserTable;

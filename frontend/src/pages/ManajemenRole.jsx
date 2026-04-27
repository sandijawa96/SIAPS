import React, { useState } from 'react';
import {
  Box,
  Container,
  Typography,
  Paper,
  Grid,
  Card,
  CardContent,
  Chip,
  IconButton,
  Button,
  TextField,
  InputAdornment,
  Fab,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Alert,
  Skeleton,
  Tooltip,
  Switch,
  FormControlLabel,
  Divider,
  Stack
} from '@mui/material';
import {
  Plus,
  Search,
  LayoutGrid,
  List,
  Edit,
  Trash2,
  Shield,
  Users,
  Settings,
  Eye,
  EyeOff,
  AlertCircle,
  CheckCircle,
  Clock,
  Filter
} from 'lucide-react';
import { useRoleManagement } from '../hooks/useRoleManagement';
import RoleFormModal from '../components/modals/RoleFormModal';

const ManajemenRole = () => {
  const {
    roles,
    permissions,
    loading,
    selectedRole,
    searchTerm,
    viewMode,
    filteredRoles,
    roleStats,
    setSelectedRole,
    setSearchTerm,
    setViewMode,
    createRole,
    updateRole,
    deleteRole,
    toggleRoleStatus,
    primaryRoles,
    subRoles
  } = useRoleManagement();

  const [openDialog, setOpenDialog] = useState(false);
  const [deleteConfirmDialog, setDeleteConfirmDialog] = useState({ open: false, roleId: null });

  const handleEdit = (role) => {
    setSelectedRole(role);
    setOpenDialog(true);
  };

  const handleDelete = async (id) => {
    setDeleteConfirmDialog({ open: true, roleId: id });
  };

  const confirmDelete = async () => {
    if (deleteConfirmDialog.roleId) {
      await deleteRole(deleteConfirmDialog.roleId);
      setDeleteConfirmDialog({ open: false, roleId: null });
    }
  };

  const handleToggleStatus = async (id, currentStatus) => {
    await toggleRoleStatus(id, currentStatus);
  };

  const handleSubmit = async (formData) => {
    let result;
    if (selectedRole) {
      result = await updateRole(selectedRole.id, formData);
    } else {
      result = await createRole(formData);
    }

    if (result?.success) {
      setOpenDialog(false);
      setSelectedRole(null);
    }

    return result;
  };

  const StatCard = ({ title, value, subtitle, icon: Icon, color = 'primary' }) => (
    <Card className="h-full hover:shadow-lg transition-shadow duration-300">
      <CardContent className="p-6">
        <Box className="flex items-center justify-between">
          <Box>
            <Typography variant="h4" className="font-bold mb-1" color={`${color}.main`}>
              {value}
            </Typography>
            <Typography variant="h6" className="font-semibold text-gray-800 mb-1">
              {title}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {subtitle}
            </Typography>
          </Box>
          <Box className={`p-3 rounded-full bg-${color}-50`}>
            <Icon className={`w-8 h-8 text-${color}-600`} />
          </Box>
        </Box>
      </CardContent>
    </Card>
  );

  const RoleCard = ({ role }) => (
    <Card className="h-full hover:shadow-lg transition-all duration-300 border-l-4 border-l-purple-500">
      <CardContent className="p-6">
        <Box className="flex items-start justify-between mb-4">
          <Box className="flex items-center gap-3">
            <Box className="p-2 bg-purple-100 rounded-lg">
              <Shield className="w-6 h-6 text-purple-600" />
            </Box>
            <Box>
              <Typography variant="h6" className="font-semibold text-gray-800">
                {role.display_name || role.name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {role.name}
              </Typography>
            </Box>
          </Box>
          <Box className="flex items-center gap-1">
            <Chip
              label={role.is_primary ? 'Primary' : 'Sub Role'}
              size="small"
              color={role.is_primary ? 'primary' : 'secondary'}
              variant="outlined"
            />
            <Chip
              label={role.is_active ? 'Aktif' : 'Nonaktif'}
              size="small"
              color={role.is_active ? 'success' : 'error'}
              icon={role.is_active ? <CheckCircle className="w-4 h-4" /> : <Clock className="w-4 h-4" />}
            />
          </Box>
        </Box>

        {role.description && (
          <Typography variant="body2" color="text.secondary" className="mb-4">
            {role.description}
          </Typography>
        )}

        <Box className="flex items-center justify-between">
          <Box className="flex items-center gap-2">
            <Users className="w-4 h-4 text-gray-500" />
            <Typography variant="body2" color="text.secondary">
              Level: {role.level || 0}
            </Typography>
          </Box>
          <Box className="flex items-center gap-1">
            <Tooltip title="Edit Role">
              <IconButton
                size="small"
                onClick={() => handleEdit(role)}
                className="text-blue-600 hover:bg-blue-50"
              >
                <Edit className="w-4 h-4" />
              </IconButton>
            </Tooltip>
            <Tooltip title={role.is_active ? 'Nonaktifkan' : 'Aktifkan'}>
              <IconButton
                size="small"
                onClick={() => handleToggleStatus(role.id, role.is_active)}
                className={role.is_active ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'}
              >
                {role.is_active ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </IconButton>
            </Tooltip>
            <Tooltip title="Hapus Role">
              <IconButton
                size="small"
                onClick={() => handleDelete(role.id)}
                className="text-red-600 hover:bg-red-50"
              >
                <Trash2 className="w-4 h-4" />
              </IconButton>
            </Tooltip>
          </Box>
        </Box>
      </CardContent>
    </Card>
  );

  const RoleListView = () => (
    <Paper className="overflow-hidden">
      <Box className="p-4 bg-gray-50 border-b">
        <Typography variant="h6" className="font-semibold">
          Daftar Role Hierarchical
        </Typography>
      </Box>
      <Box className="divide-y">
        {/* Primary Roles with their Sub Roles */}
        {filteredRoles.filter(role => role.is_primary).map((primaryRole) => {
          const subRoles = filteredRoles.filter(role => 
            !role.is_primary && role.parent_role_id === primaryRole.id
          );
          
          return (
            <Box key={primaryRole.id}>
              {/* Primary Role */}
              <Box className="p-4 hover:bg-gray-50 transition-colors">
                <Box className="flex items-center justify-between">
                  <Box className="flex items-center gap-4">
                    <Box className="p-2 bg-purple-100 rounded-lg">
                      <Shield className="w-5 h-5 text-purple-600" />
                    </Box>
                    <Box>
                      <Typography variant="subtitle1" className="font-semibold">
                        {primaryRole.display_name || primaryRole.name}
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        {primaryRole.description || 'Tidak ada deskripsi'}
                      </Typography>
                    </Box>
                  </Box>
                  <Box className="flex items-center gap-2">
                    <Chip
                      label="Primary Role"
                      size="small"
                      color="primary"
                      variant="outlined"
                    />
                    <Chip
                      label={primaryRole.is_active ? 'Aktif' : 'Nonaktif'}
                      size="small"
                      color={primaryRole.is_active ? 'success' : 'error'}
                    />
                    <Box className="flex items-center gap-1 ml-2">
                      <Tooltip title="Edit Role">
                        <IconButton size="small" onClick={() => handleEdit(primaryRole)}>
                          <Edit className="w-4 h-4" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={primaryRole.is_active ? 'Nonaktifkan' : 'Aktifkan'}>
                        <IconButton
                          size="small"
                          onClick={() => handleToggleStatus(primaryRole.id, primaryRole.is_active)}
                        >
                          {primaryRole.is_active ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </IconButton>
                      </Tooltip>
                      <Tooltip title="Hapus Role">
                        <IconButton
                          size="small"
                          onClick={() => handleDelete(primaryRole.id)}
                          className="text-red-600"
                        >
                          <Trash2 className="w-4 h-4" />
                        </IconButton>
                      </Tooltip>
                    </Box>
                  </Box>
                </Box>
              </Box>
              
              {/* Sub Roles under this Primary Role */}
              {subRoles.map((subRole) => (
                <Box key={subRole.id} className="pl-12 pr-4 py-3 bg-gray-25 hover:bg-gray-50 transition-colors border-l-2 border-l-blue-200">
                  <Box className="flex items-center justify-between">
                    <Box className="flex items-center gap-4">
                      <Box className="p-2 bg-blue-100 rounded-lg">
                        <Settings className="w-4 h-4 text-blue-600" />
                      </Box>
                      <Box>
                        <Typography variant="subtitle2" className="font-medium">
                          {subRole.display_name || subRole.name}
                        </Typography>
                        <Typography variant="body2" color="text.secondary" className="text-sm">
                          {subRole.description || 'Tidak ada deskripsi'}
                        </Typography>
                      </Box>
                    </Box>
                    <Box className="flex items-center gap-2">
                      <Chip
                        label="Sub Role"
                        size="small"
                        color="secondary"
                        variant="outlined"
                      />
                      <Chip
                        label={subRole.is_active ? 'Aktif' : 'Nonaktif'}
                        size="small"
                        color={subRole.is_active ? 'success' : 'error'}
                      />
                      <Box className="flex items-center gap-1 ml-2">
                        <Tooltip title="Edit Role">
                          <IconButton size="small" onClick={() => handleEdit(subRole)}>
                            <Edit className="w-3 h-3" />
                          </IconButton>
                        </Tooltip>
                        <Tooltip title={subRole.is_active ? 'Nonaktifkan' : 'Aktifkan'}>
                          <IconButton
                            size="small"
                            onClick={() => handleToggleStatus(subRole.id, subRole.is_active)}
                          >
                            {subRole.is_active ? <EyeOff className="w-3 h-3" /> : <Eye className="w-3 h-3" />}
                          </IconButton>
                        </Tooltip>
                        <Tooltip title="Hapus Role">
                          <IconButton
                            size="small"
                            onClick={() => handleDelete(subRole.id)}
                            className="text-red-600"
                          >
                            <Trash2 className="w-3 h-3" />
                          </IconButton>
                        </Tooltip>
                      </Box>
                    </Box>
                  </Box>
                </Box>
              ))}
            </Box>
          );
        })}
        
        {/* Orphaned Sub Roles (sub roles without parent) */}
        {(() => {
          const orphanedSubRoles = filteredRoles.filter(role => 
            !role.is_primary && !filteredRoles.some(pr => pr.is_primary && pr.id === role.parent_role_id)
          );
          
          if (orphanedSubRoles.length > 0) {
            return (
              <Box>
                <Box className="p-3 bg-orange-50 border-l-4 border-l-orange-400">
                  <Typography variant="subtitle2" className="font-medium text-orange-800 flex items-center gap-2">
                    <AlertCircle className="w-4 h-4" />
                    Sub Roles Tanpa Parent
                  </Typography>
                </Box>
                {orphanedSubRoles.map((role) => (
                  <Box key={role.id} className="p-4 hover:bg-gray-50 transition-colors">
                    <Box className="flex items-center justify-between">
                      <Box className="flex items-center gap-4">
                        <Box className="p-2 bg-orange-100 rounded-lg">
                          <AlertCircle className="w-5 h-5 text-orange-600" />
                        </Box>
                        <Box>
                          <Typography variant="subtitle1" className="font-semibold">
                            {role.display_name || role.name}
                          </Typography>
                          <Typography variant="body2" color="text.secondary">
                            {role.description || 'Tidak ada deskripsi'}
                          </Typography>
                        </Box>
                      </Box>
                      <Box className="flex items-center gap-2">
                        <Chip
                          label="Sub Role"
                          size="small"
                          color="warning"
                          variant="outlined"
                        />
                        <Chip
                          label={role.is_active ? 'Aktif' : 'Nonaktif'}
                          size="small"
                          color={role.is_active ? 'success' : 'error'}
                        />
                        <Box className="flex items-center gap-1 ml-2">
                          <Tooltip title="Edit Role">
                            <IconButton size="small" onClick={() => handleEdit(role)}>
                              <Edit className="w-4 h-4" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={role.is_active ? 'Nonaktifkan' : 'Aktifkan'}>
                            <IconButton
                              size="small"
                              onClick={() => handleToggleStatus(role.id, role.is_active)}
                            >
                              {role.is_active ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="Hapus Role">
                            <IconButton
                              size="small"
                              onClick={() => handleDelete(role.id)}
                              className="text-red-600"
                            >
                              <Trash2 className="w-4 h-4" />
                            </IconButton>
                          </Tooltip>
                        </Box>
                      </Box>
                    </Box>
                  </Box>
                ))}
              </Box>
            );
          }
          return null;
        })()}
      </Box>
    </Paper>
  );

  return (
    <Container maxWidth="xl" className="py-6">
      {/* Header */}
      <Box className="mb-8">
        <Box className="flex items-center justify-between mb-4">
          <Box>
            <Typography variant="h4" className="font-bold text-gray-800 mb-2">
              Manajemen Role
            </Typography>
            <Typography variant="body1" color="text.secondary">
              Kelola role dan permission sistem dengan mudah
            </Typography>
          </Box>
          <Button
            variant="contained"
            startIcon={<Plus className="w-5 h-5" />}
            onClick={() => {
              setSelectedRole(null);
              setOpenDialog(true);
            }}
            className="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300"
          >
            Tambah Role
          </Button>
        </Box>
      </Box>

      {/* Statistics */}
      <Grid container spacing={3} className="mb-8">
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Total Role"
            value={roleStats.totalRoles}
            subtitle="Role terdaftar"
            icon={Shield}
            color="primary"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Primary Role"
            value={roleStats.totalPrimaryRoles}
            subtitle="Role utama"
            icon={Users}
            color="success"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Sub Role"
            value={roleStats.totalSubRoles}
            subtitle="Role turunan"
            icon={Settings}
            color="warning"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Permission"
            value={roleStats.totalPermissions}
            subtitle="Total permission"
            icon={CheckCircle}
            color="info"
          />
        </Grid>
      </Grid>

      {/* Search and Controls */}
      <Paper className="p-4 mb-6">
        <Box className="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
          <TextField
            placeholder="Cari role..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-5 h-5 text-gray-400" />
                </InputAdornment>
              ),
            }}
            className="flex-1 max-w-md"
            size="small"
          />
          
          <Box className="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
            <Button
              variant={viewMode === 'category' ? 'contained' : 'text'}
              startIcon={<LayoutGrid className="w-4 h-4" />}
              onClick={() => setViewMode('category')}
              size="small"
              className={viewMode === 'category' ? 'bg-purple-600 text-white' : 'text-gray-600'}
            >
              Kategori
            </Button>
            <Button
              variant={viewMode === 'list' ? 'contained' : 'text'}
              startIcon={<List className="w-4 h-4" />}
              onClick={() => setViewMode('list')}
              size="small"
              className={viewMode === 'list' ? 'bg-purple-600 text-white' : 'text-gray-600'}
            >
              Daftar
            </Button>
          </Box>
        </Box>
      </Paper>

      {/* Content */}
      {loading ? (
        <Grid container spacing={3}>
          {[1, 2, 3, 4].map((item) => (
            <Grid item xs={12} sm={6} md={4} lg={3} key={item}>
              <Skeleton variant="rectangular" height={200} className="rounded-lg" />
            </Grid>
          ))}
        </Grid>
      ) : filteredRoles.length === 0 ? (
        <Paper className="p-8 text-center">
          <AlertCircle className="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <Typography variant="h6" className="text-gray-600 mb-2">
            Tidak ada role ditemukan
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {searchTerm ? 'Coba ubah kata kunci pencarian' : 'Belum ada role yang dibuat'}
          </Typography>
        </Paper>
      ) : viewMode === 'category' ? (
        <Box>
          {/* Primary Roles */}
          <Box className="mb-8">
            <Typography variant="h5" className="font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <Shield className="w-6 h-6 text-purple-600" />
              Primary Roles
            </Typography>
            <Grid container spacing={3}>
              {filteredRoles.filter(role => role.is_primary).map((role) => (
                <Grid item xs={12} sm={6} md={4} lg={3} key={role.id}>
                  <RoleCard role={role} />
                </Grid>
              ))}
            </Grid>
          </Box>

          {/* Sub Roles */}
          {filteredRoles.filter(role => !role.is_primary).length > 0 && (
            <Box>
              <Typography variant="h5" className="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <Settings className="w-6 h-6 text-blue-600" />
                Sub Roles
              </Typography>
              <Grid container spacing={3}>
                {filteredRoles.filter(role => !role.is_primary).map((role) => (
                  <Grid item xs={12} sm={6} md={4} lg={3} key={role.id}>
                    <RoleCard role={role} />
                  </Grid>
                ))}
              </Grid>
            </Box>
          )}
        </Box>
      ) : (
        <RoleListView />
      )}

      {/* Role Form Modal */}
      <RoleFormModal
        isOpen={openDialog}
        onClose={() => {
          setOpenDialog(false);
          setSelectedRole(null);
        }}
        onSubmit={handleSubmit}
        selectedRole={selectedRole}
        roles={roles}
        permissions={permissions}
      />

      {/* Delete Confirmation Dialog */}
      <Dialog
        open={deleteConfirmDialog.open}
        onClose={() => setDeleteConfirmDialog({ open: false, roleId: null })}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle className="flex items-center gap-2">
          <AlertCircle className="w-6 h-6 text-red-600" />
          Konfirmasi Hapus Role
        </DialogTitle>
        <DialogContent>
          <Alert severity="warning" className="mb-4">
            Tindakan ini tidak dapat dibatalkan. Role yang dihapus akan hilang permanen.
          </Alert>
          <Typography>
            Apakah Anda yakin ingin menghapus role ini?
          </Typography>
        </DialogContent>
        <DialogActions className="p-4">
          <Button
            onClick={() => setDeleteConfirmDialog({ open: false, roleId: null })}
            variant="outlined"
          >
            Batal
          </Button>
          <Button
            onClick={confirmDelete}
            variant="contained"
            color="error"
            startIcon={<Trash2 className="w-4 h-4" />}
          >
            Hapus
          </Button>
        </DialogActions>
      </Dialog>

      {/* Floating Action Button for mobile */}
      <Fab
        color="primary"
        className="fixed bottom-6 right-6 bg-purple-600 hover:bg-purple-700 md:hidden"
        onClick={() => {
          setSelectedRole(null);
          setOpenDialog(true);
        }}
      >
        <Plus className="w-6 h-6" />
      </Fab>
    </Container>
  );
};

export default ManajemenRole;

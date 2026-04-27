import React, { useMemo } from 'react';
import { 
  Paper, 
  Box, 
  TextField, 
  FormControl, 
  InputLabel, 
  Select, 
  MenuItem, 
  Button, 
  Chip,
  InputAdornment,
  LinearProgress,
  Typography
} from '@mui/material';
import { 
  Search, 
  Download, 
  Upload, 
  Plus, 
  Trash2, 
  Users, 
  Tag 
} from 'lucide-react';

const UserFilters = ({
  activeTab,
  filters,
  onFilterChange,
  onAddUser,
  onExport,
  onImport,
  selectedUsers,
  onBulkDelete,
  availableRoles = [],
  availableSubRoles = [],
  availableTahunAjaran = [],
  availableTingkat = [],
  availableKelas = [],
  importProgress = 0,
  exportProgress = 0
}) => {
  const filteredKelasOptions = useMemo(() => {
    const selectedTahunAjaranId = String(filters?.tahun_ajaran_id ?? '').trim();
    const selectedTingkatId = String(filters?.tingkat_id ?? '').trim();
    const tingkatById = new Map(
      (availableTingkat || []).map((tingkatItem) => [String(tingkatItem.id), tingkatItem]),
    );
    const selectedTingkatName = selectedTingkatId
      ? String(tingkatById.get(selectedTingkatId)?.nama ?? '').toLowerCase()
      : '';

    const filtered = (availableKelas || []).filter((kelasItem) => {
      if (selectedTahunAjaranId) {
        const kelasTahunAjaranId = String(kelasItem?.tahun_ajaran_id ?? '').trim();
        if (!kelasTahunAjaranId || kelasTahunAjaranId !== selectedTahunAjaranId) {
          return false;
        }
      }

      if (!selectedTingkatId) {
        return true;
      }

      if (kelasItem?.tingkat_id && String(kelasItem.tingkat_id) === selectedTingkatId) {
        return true;
      }

      if (selectedTingkatName && kelasItem?.tingkat_nama) {
        return String(kelasItem.tingkat_nama).toLowerCase() === selectedTingkatName;
      }

      return false;
    });

    return [...filtered].sort((a, b) => String(a.nama_kelas).localeCompare(String(b.nama_kelas), 'id', {
      numeric: true,
      sensitivity: 'base',
    }));
  }, [availableKelas, availableTingkat, filters?.tahun_ajaran_id, filters?.tingkat_id]);

  return (
    <Paper className="p-6 mb-6 shadow-sm border border-gray-100">
      {/* Search and Filters Row */}
      <Box className="flex flex-col lg:flex-row gap-4 mb-4">
        {/* Search Field */}
        <TextField
          placeholder={`Cari ${activeTab === 'pegawai' ? 'pegawai' : 'siswa'}...`}
          value={filters.search}
          onChange={(e) => onFilterChange('search', e.target.value)}
          className="flex-1 lg:max-w-md"
          size="small"
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <Search className="w-4 h-4 text-gray-400" />
              </InputAdornment>
            ),
          }}
          sx={{
            '& .MuiOutlinedInput-root': {
              '&:hover fieldset': {
                borderColor: '#3B82F6',
              },
              '&.Mui-focused fieldset': {
                borderColor: '#3B82F6',
              },
            },
          }}
        />

        {/* Filter Controls */}
        <Box className="flex flex-wrap gap-3">
          {/* Parent Role Filter for Pegawai */}
          {activeTab === 'pegawai' && (
            <FormControl size="small" className="min-w-[160px]">
              <InputLabel>Parent Role</InputLabel>
              <Select
                value={filters.role}
                onChange={(e) => onFilterChange('role', e.target.value)}
                label="Parent Role"
                startAdornment={<Users className="w-4 h-4 text-gray-400 mr-2" />}
              >
                <MenuItem value="">
                  <em>Semua Parent Role</em>
                </MenuItem>
                {availableRoles.map(role => (
                  <MenuItem key={role.id} value={role.name}>
                    {role.display_name || role.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Sub Role Filter for Pegawai */}
          {activeTab === 'pegawai' && (
            <FormControl size="small" className="min-w-[160px]">
              <InputLabel>Sub Role</InputLabel>
              <Select
                value={filters.sub_role || ''}
                onChange={(e) => onFilterChange('sub_role', e.target.value)}
                label="Sub Role"
                disabled={!filters.role || availableSubRoles.length === 0}
                startAdornment={<Tag className="w-4 h-4 text-gray-400 mr-2" />}
              >
                <MenuItem value="">
                  <em>Semua Sub Role</em>
                </MenuItem>
                {availableSubRoles.map(role => (
                  <MenuItem key={role.id} value={role.name}>
                    {role.display_name || role.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Tahun Ajaran Filter for Siswa */}
          {activeTab === 'siswa' && (
            <FormControl size="small" className="min-w-[170px]">
              <InputLabel>Tahun Ajaran</InputLabel>
              <Select
                value={filters.tahun_ajaran_id || ''}
                onChange={(e) => onFilterChange('tahun_ajaran_id', e.target.value)}
                label="Tahun Ajaran"
              >
                <MenuItem value="">
                  <em>Semua Tahun Ajaran</em>
                </MenuItem>
                {(availableTahunAjaran || []).map((tahunAjaranItem) => (
                  <MenuItem key={tahunAjaranItem.id} value={tahunAjaranItem.id}>
                    {tahunAjaranItem.nama}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Tingkat Filter for Siswa */}
          {activeTab === 'siswa' && (
            <FormControl size="small" className="min-w-[140px]">
              <InputLabel>Tingkat Awal</InputLabel>
              <Select
                value={filters.tingkat_id || ''}
                onChange={(e) => onFilterChange('tingkat_id', e.target.value)}
                label="Tingkat Awal"
              >
                <MenuItem value="">
                  <em>Semua Tingkat Awal</em>
                </MenuItem>
                {(availableTingkat || []).map((tingkatItem) => (
                  <MenuItem key={tingkatItem.id} value={tingkatItem.id}>
                    {tingkatItem.nama}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Kelas Filter for Siswa */}
          {activeTab === 'siswa' && (
            <FormControl size="small" className="min-w-[160px]">
              <InputLabel>Kelas Awal</InputLabel>
              <Select
                value={filters.kelas_id || ''}
                onChange={(e) => onFilterChange('kelas_id', e.target.value)}
                label="Kelas Awal"
              >
                <MenuItem value="">
                  <em>Semua Kelas Awal</em>
                </MenuItem>
                {filteredKelasOptions.map((kelasItem) => (
                  <MenuItem key={kelasItem.id} value={kelasItem.id}>
                    {kelasItem.nama_kelas}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Status Filter */}
          <FormControl size="small" className="min-w-[140px]">
            <InputLabel>Status</InputLabel>
            <Select
              value={filters.is_active}
              onChange={(e) => onFilterChange('is_active', e.target.value)}
              label="Status"
            >
              <MenuItem value="">
                <em>Semua Status</em>
              </MenuItem>
              <MenuItem value="1">
                <Chip 
                  label="Aktif" 
                  size="small" 
                  color="success" 
                  variant="outlined"
                />
              </MenuItem>
              <MenuItem value="0">
                <Chip 
                  label="Non-aktif" 
                  size="small" 
                  color="error" 
                  variant="outlined"
                />
              </MenuItem>
            </Select>
          </FormControl>
        </Box>
      </Box>

      {/* Progress Bars */}
      {(importProgress > 0 || exportProgress > 0) && (
        <Box className="mb-4">
          {importProgress > 0 && (
            <Box className="mb-2">
              <Box className="flex justify-between items-center mb-1">
                <Typography variant="body2" color="primary">
                  Mengimpor data...
                </Typography>
                <Typography variant="body2" color="primary">
                  {importProgress}%
                </Typography>
              </Box>
              <LinearProgress 
                variant="determinate" 
                value={importProgress} 
                color="primary"
                sx={{ height: 6, borderRadius: 3 }}
              />
            </Box>
          )}
          {exportProgress > 0 && (
            <Box className="mb-2">
              <Box className="flex justify-between items-center mb-1">
                <Typography variant="body2" color="success.main">
                  Mengekspor data...
                </Typography>
                <Typography variant="body2" color="success.main">
                  {exportProgress}%
                </Typography>
              </Box>
              <LinearProgress 
                variant="determinate" 
                value={exportProgress} 
                color="success"
                sx={{ height: 6, borderRadius: 3 }}
              />
            </Box>
          )}
        </Box>
      )}

      {/* Action Buttons Row */}
      <Box className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        {/* Selected Items Info */}
        <Box className="flex items-center gap-2">
          {selectedUsers && selectedUsers.length > 0 && (
            <Chip
              label={`${selectedUsers.length} item dipilih`}
              color="primary"
              variant="outlined"
              size="small"
            />
          )}
        </Box>

        {/* Action Buttons */}
        <Box className="flex flex-wrap gap-2">
          {/* Bulk Delete Button */}
          {selectedUsers && selectedUsers.length > 0 && (
            <Button
              variant="outlined"
              color="error"
              size="small"
              startIcon={<Trash2 className="w-4 h-4" />}
              onClick={onBulkDelete}
              className="hover:bg-red-50"
            >
              Hapus ({selectedUsers.length})
            </Button>
          )}

          {/* Export Button */}
          <Button
            variant="outlined"
            size="small"
            startIcon={<Download className="w-4 h-4" />}
            onClick={onExport}
            disabled={exportProgress > 0}
            className="hover:bg-gray-50"
          >
            {exportProgress > 0 ? 'Mengekspor...' : 'Export'}
          </Button>

          {/* Import Button */}
          <Button
            variant="outlined"
            color="success"
            size="small"
            startIcon={<Upload className="w-4 h-4" />}
            onClick={onImport}
            disabled={importProgress > 0}
            className="hover:bg-green-50"
          >
            {importProgress > 0 ? 'Mengimpor...' : 'Import'}
          </Button>

          {/* Add User Button */}
          <Button
            variant="contained"
            size="small"
            startIcon={<Plus className="w-4 h-4" />}
            onClick={onAddUser}
            className="bg-blue-600 hover:bg-blue-700 shadow-sm"
          >
            Tambah {activeTab === 'pegawai' ? 'Pegawai' : 'Siswa'}
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default UserFilters;

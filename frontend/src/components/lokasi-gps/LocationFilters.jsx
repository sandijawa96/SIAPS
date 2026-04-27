import React, { useState } from 'react';
import {
  Box,
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip,
  Button,
  Paper,
  Typography,
  Collapse,
  IconButton
} from '@mui/material';
import {
  Search,
  Filter,
  X,
  ChevronDown,
  ChevronUp
} from 'lucide-react';

const LocationFilters = ({
  onFilterChange,
  totalCount,
  filteredCount
}) => {
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    roles: [],
    radius: 'all'
  });
  
  const [showAdvanced, setShowAdvanced] = useState(false);

  // Available filter options
  const statusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Tidak Aktif' }
  ];

  const roleOptions = ['Admin', 'Guru', 'Siswa', 'Wali Kelas'];

  const radiusOptions = [
    { value: 'all', label: 'Semua Radius' },
    { value: 'small', label: '< 50m' },
    { value: 'medium', label: '50-200m' },
    { value: 'large', label: '> 200m' }
  ];

  // Handle filter changes
  const handleFilterChange = (key, value) => {
    const newFilters = { ...filters, [key]: value };
    setFilters(newFilters);
    onFilterChange(newFilters);
  };

  // Handle role selection
  const handleRoleToggle = (role) => {
    const newRoles = filters.roles.includes(role)
      ? filters.roles.filter(r => r !== role)
      : [...filters.roles, role];
    
    handleFilterChange('roles', newRoles);
  };

  // Clear all filters
  const clearFilters = () => {
    const clearedFilters = {
      search: '',
      status: 'all',
      roles: [],
      radius: 'all'
    };
    setFilters(clearedFilters);
    onFilterChange(clearedFilters);
  };

  // Check if any filters are active
  const hasActiveFilters = filters.search || 
    filters.status !== 'all' || 
    filters.roles.length > 0 || 
    filters.radius !== 'all';

  return (
    <Paper className="p-4 mb-6">
      <Box className="flex items-center justify-between mb-4">
        <Typography variant="h6" className="font-medium">
          Filter & Pencarian
        </Typography>
        <Box className="flex items-center space-x-2">
          <Typography variant="body2" color="textSecondary">
            {filteredCount} dari {totalCount} lokasi
          </Typography>
          <IconButton
            size="small"
            onClick={() => setShowAdvanced(!showAdvanced)}
            className="text-gray-600"
          >
            {showAdvanced ? <ChevronUp /> : <ChevronDown />}
          </IconButton>
        </Box>
      </Box>

      {/* Basic Filters */}
      <Box className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        {/* Search */}
        <TextField
          fullWidth
          placeholder="Cari nama lokasi..."
          value={filters.search}
          onChange={(e) => handleFilterChange('search', e.target.value)}
          InputProps={{
            startAdornment: <Search className="w-4 h-4 mr-2 text-gray-400" />
          }}
        />

        {/* Status Filter */}
        <FormControl fullWidth>
          <InputLabel>Status</InputLabel>
          <Select
            value={filters.status}
            onChange={(e) => handleFilterChange('status', e.target.value)}
            label="Status"
          >
            {statusOptions.map(option => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </Select>
        </FormControl>

        {/* Radius Filter */}
        <FormControl fullWidth>
          <InputLabel>Radius</InputLabel>
          <Select
            value={filters.radius}
            onChange={(e) => handleFilterChange('radius', e.target.value)}
            label="Radius"
          >
            {radiusOptions.map(option => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
      </Box>

      {/* Advanced Filters */}
      <Collapse in={showAdvanced}>
        <Box className="border-t pt-4">
          <Typography variant="subtitle2" className="mb-3">
            Filter Berdasarkan Role
          </Typography>
          <Box className="flex flex-wrap gap-2 mb-4">
            {roleOptions.map(role => (
              <Chip
                key={role}
                label={role}
                onClick={() => handleRoleToggle(role)}
                color={filters.roles.includes(role) ? 'primary' : 'default'}
                variant={filters.roles.includes(role) ? 'filled' : 'outlined'}
                className="cursor-pointer"
              />
            ))}
          </Box>
        </Box>
      </Collapse>

      {/* Active Filters Display */}
      {hasActiveFilters && (
        <Box className="flex items-center justify-between pt-4 border-t">
          <Box className="flex flex-wrap gap-2">
            {filters.search && (
              <Chip
                label={`Pencarian: "${filters.search}"`}
                onDelete={() => handleFilterChange('search', '')}
                size="small"
                color="primary"
                variant="outlined"
              />
            )}
            {filters.status !== 'all' && (
              <Chip
                label={`Status: ${statusOptions.find(s => s.value === filters.status)?.label}`}
                onDelete={() => handleFilterChange('status', 'all')}
                size="small"
                color="primary"
                variant="outlined"
              />
            )}
            {filters.radius !== 'all' && (
              <Chip
                label={`Radius: ${radiusOptions.find(r => r.value === filters.radius)?.label}`}
                onDelete={() => handleFilterChange('radius', 'all')}
                size="small"
                color="primary"
                variant="outlined"
              />
            )}
            {filters.roles.map(role => (
              <Chip
                key={role}
                label={`Role: ${role}`}
                onDelete={() => handleRoleToggle(role)}
                size="small"
                color="primary"
                variant="outlined"
              />
            ))}
          </Box>
          <Button
            size="small"
            onClick={clearFilters}
            startIcon={<X className="w-4 h-4" />}
            className="text-gray-600"
          >
            Hapus Semua
          </Button>
        </Box>
      )}
    </Paper>
  );
};

export default LocationFilters;

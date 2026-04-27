import React, { useMemo } from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  InputAdornment,
  MenuItem,
  TextField,
  Typography,
} from '@mui/material';
import {
  Filter,
  Search,
  X,
} from 'lucide-react';

const STATUS_LABELS = {
  active: 'Fresh dalam area',
  outside_area: 'Fresh luar area',
  tracking_disabled: 'Tracking nonaktif',
  outside_schedule: 'Di luar jadwal',
  stale: 'Stale',
  gps_disabled: 'GPS mati',
  no_data: 'Belum ada data',
};

const TrackingFilters = ({
  filters = {},
  onFiltersChange,
  availableClasses = [],
  availableLevels = [],
  availableHomeroomTeachers = [],
  onClearFilters,
  totalResults = 0,
}) => {
  const {
    search = '',
    status = 'all',
    area = 'all',
    class: classFilter = '',
    tingkat: levelFilter = '',
    wali_kelas_id: homeroomFilter = '',
  } = filters;

  const suggestedClasses = useMemo(
    () => availableClasses.slice(0, 6),
    [availableClasses]
  );

  const activeFilters = [];
  if (search) activeFilters.push({ key: 'search', label: `Cari: ${search}`, clear: () => onFiltersChange({ ...filters, search: '' }) });
  if (status !== 'all') activeFilters.push({ key: 'status', label: `Status: ${STATUS_LABELS[status] || status}`, clear: () => onFiltersChange({ ...filters, status: 'all' }) });
  if (area !== 'all') activeFilters.push({ key: 'area', label: `Area: ${area === 'inside' ? 'Dalam area' : 'Luar area'}`, clear: () => onFiltersChange({ ...filters, area: 'all' }) });
  if (levelFilter) activeFilters.push({ key: 'tingkat', label: `Tingkat: ${levelFilter}`, clear: () => onFiltersChange({ ...filters, tingkat: '' }) });
  if (classFilter) activeFilters.push({ key: 'class', label: `Kelas: ${classFilter}`, clear: () => onFiltersChange({ ...filters, class: '' }) });
  if (homeroomFilter) {
    const teacherName = availableHomeroomTeachers.find((teacher) => String(teacher.id) === String(homeroomFilter))?.name || homeroomFilter;
    activeFilters.push({ key: 'wali', label: `Wali: ${teacherName}`, clear: () => onFiltersChange({ ...filters, wali_kelas_id: '' }) });
  }

  return (
    <Card className="rounded-[28px] border border-slate-200 shadow-sm">
      <CardContent className="space-y-4 p-5">
        <Box className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <Box>
            <Box className="flex items-center gap-2">
              <Filter className="h-4 w-4 text-slate-600" />
              <Typography variant="subtitle1" className="font-semibold text-slate-900">
                Filter Operasional
              </Typography>
            </Box>
            <Typography variant="body2" className="mt-1 text-slate-600">
              Gunakan filter untuk mempersempit roster sebelum membaca daftar atau peta.
            </Typography>
          </Box>

          <Box className="flex flex-wrap items-center gap-2">
            <Chip size="small" color="primary" variant="outlined" label={`${totalResults} hasil`} />
            {activeFilters.length > 0 ? (
              <Button size="small" variant="outlined" onClick={onClearFilters} className="!rounded-xl !border-slate-300">
                Reset filter
              </Button>
            ) : null}
          </Box>
        </Box>

        <TextField
          fullWidth
          size="small"
          placeholder="Cari nama, email, NIS, atau username"
          value={search}
          onChange={(event) => onFiltersChange({ ...filters, search: event.target.value })}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <Search className="h-4 w-4 text-slate-400" />
              </InputAdornment>
            ),
            endAdornment: search ? (
              <InputAdornment position="end">
                <Button
                  size="small"
                  onClick={() => onFiltersChange({ ...filters, search: '' })}
                  className="!min-w-0 !rounded-lg !px-2 !text-slate-500"
                >
                  <X className="h-4 w-4" />
                </Button>
              </InputAdornment>
            ) : null,
          }}
          sx={{
            '& .MuiOutlinedInput-root': {
              borderRadius: '16px',
              backgroundColor: '#f8fafc',
            },
          }}
        />

        <Box className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
          <TextField
            select
            size="small"
            fullWidth
            label="Status"
            value={status}
            onChange={(event) => onFiltersChange({ ...filters, status: event.target.value })}
          >
            <MenuItem value="all">Semua status</MenuItem>
            <MenuItem value="active">Fresh dalam area</MenuItem>
            <MenuItem value="outside_area">Fresh luar area</MenuItem>
            <MenuItem value="tracking_disabled">Tracking nonaktif</MenuItem>
            <MenuItem value="outside_schedule">Di luar jadwal</MenuItem>
            <MenuItem value="stale">Stale</MenuItem>
            <MenuItem value="gps_disabled">GPS mati</MenuItem>
            <MenuItem value="no_data">Belum ada data</MenuItem>
          </TextField>

          <TextField
            select
            size="small"
            fullWidth
            label="Area"
            value={area}
            onChange={(event) => onFiltersChange({ ...filters, area: event.target.value })}
          >
            <MenuItem value="all">Semua area</MenuItem>
            <MenuItem value="inside">Dalam area</MenuItem>
            <MenuItem value="outside">Luar area</MenuItem>
          </TextField>

          <TextField
            select
            size="small"
            fullWidth
            label="Tingkat"
            value={levelFilter}
            onChange={(event) => onFiltersChange({ ...filters, tingkat: event.target.value })}
          >
            <MenuItem value="">Semua tingkat</MenuItem>
            {availableLevels.map((level) => (
              <MenuItem key={level} value={level}>{level}</MenuItem>
            ))}
          </TextField>

          <TextField
            size="small"
            fullWidth
            label="Kelas"
            placeholder="Contoh: X-1"
            value={classFilter}
            onChange={(event) => onFiltersChange({ ...filters, class: event.target.value })}
          />

          <TextField
            select
            size="small"
            fullWidth
            label="Wali kelas"
            value={homeroomFilter}
            onChange={(event) => onFiltersChange({ ...filters, wali_kelas_id: event.target.value })}
          >
            <MenuItem value="">Semua wali kelas</MenuItem>
            {availableHomeroomTeachers.map((teacher) => (
              <MenuItem key={teacher.id || teacher.name} value={teacher.id ? String(teacher.id) : ''}>
                {teacher.name}
              </MenuItem>
            ))}
          </TextField>
        </Box>

        {suggestedClasses.length > 0 ? (
          <Typography variant="caption" className="block text-slate-500">
            Saran kelas: {suggestedClasses.join(', ')}
          </Typography>
        ) : null}

        {activeFilters.length > 0 ? (
          <Box className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
            <Box className="flex flex-wrap items-center gap-2">
              <Typography variant="caption" className="mr-1 text-slate-500">
                Filter aktif
              </Typography>
              {activeFilters.map((filter) => (
                <Chip
                  key={filter.key}
                  size="small"
                  variant="outlined"
                  label={filter.label}
                  onDelete={filter.clear}
                />
              ))}
            </Box>
          </Box>
        ) : null}
      </CardContent>
    </Card>
  );
};

export default TrackingFilters;

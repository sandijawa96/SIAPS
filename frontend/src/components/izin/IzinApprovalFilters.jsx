import React from 'react';
import PropTypes from 'prop-types';
import {
  Box,
  Button,
  FormControl,
  InputAdornment,
  MenuItem,
  Select,
  TextField,
} from '@mui/material';
import { RotateCcw, Search } from 'lucide-react';

const IzinApprovalFilters = ({ filters, onFilterChange, type = 'siswa' }) => {
  const jenisIzinOptions = type === 'pegawai'
    ? [
      { value: 'sakit', label: 'Sakit' },
      { value: 'izin', label: 'Izin' },
      { value: 'keperluan_keluarga', label: 'Keperluan Keluarga' },
      { value: 'dinas_luar', label: 'Dinas Luar' },
      { value: 'cuti', label: 'Cuti' },
    ]
    : [
      { value: 'sakit', label: 'Sakit' },
      { value: 'izin', label: 'Izin Pribadi' },
      { value: 'keperluan_keluarga', label: 'Urusan Keluarga' },
      { value: 'dispensasi', label: 'Dispensasi Sekolah' },
      { value: 'tugas_sekolah', label: 'Tugas Sekolah' },
    ];

  const handleChange = (key, value) => {
    onFilterChange({ [key]: value });
  };

  const handleReset = () => {
    onFilterChange({
      search: '',
      status: '',
      kelas_id: '',
      jenis_izin: '',
      tanggal_mulai: '',
      tanggal_selesai: '',
      per_page: 10,
    });
  };

  return (
    <Box className="space-y-4">
      <Box className="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <TextField
          placeholder={type === 'pegawai'
            ? 'Cari nama pegawai, unit, atau alasan...'
            : 'Cari nama siswa, kelas, atau alasan...'}
          value={filters.search || ''}
          onChange={(event) => handleChange('search', event.target.value)}
          size="small"
          className="xl:col-span-4"
          fullWidth
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <Search className="w-4 h-4 text-gray-400" />
              </InputAdornment>
            ),
          }}
        />

        <FormControl size="small" className="xl:col-span-2" sx={{ minWidth: 170 }}>
          <Select
            displayEmpty
            value={filters.status || ''}
            onChange={(event) => handleChange('status', event.target.value)}
          >
            <MenuItem value="">Semua Status</MenuItem>
            <MenuItem value="pending">Menunggu Persetujuan</MenuItem>
            <MenuItem value="approved">Disetujui</MenuItem>
            <MenuItem value="rejected">Ditolak</MenuItem>
          </Select>
        </FormControl>

        <FormControl size="small" className="xl:col-span-2" sx={{ minWidth: 170 }}>
          <Select
            displayEmpty
            value={filters.jenis_izin || ''}
            onChange={(event) => handleChange('jenis_izin', event.target.value)}
          >
            <MenuItem value="">Semua Jenis Izin</MenuItem>
            {jenisIzinOptions.map((item) => (
              <MenuItem key={item.value} value={item.value}>
                {item.label}
              </MenuItem>
            ))}
          </Select>
        </FormControl>

        <TextField
          type="date"
          size="small"
          value={filters.tanggal_mulai || ''}
          onChange={(event) => handleChange('tanggal_mulai', event.target.value)}
          className="xl:col-span-2"
          sx={{ minWidth: 170 }}
        />

        <TextField
          type="date"
          size="small"
          value={filters.tanggal_selesai || ''}
          onChange={(event) => handleChange('tanggal_selesai', event.target.value)}
          className="xl:col-span-2"
          sx={{ minWidth: 170 }}
        />
      </Box>

      <Box className="flex items-center justify-end gap-2">
        <Button
          variant="outlined"
          size="small"
          startIcon={<RotateCcw className="w-4 h-4" />}
          onClick={handleReset}
        >
          Reset Filter
        </Button>
      </Box>
    </Box>
  );
};

IzinApprovalFilters.propTypes = {
  filters: PropTypes.shape({
    search: PropTypes.string,
    status: PropTypes.string,
    kelas_id: PropTypes.string,
    jenis_izin: PropTypes.string,
    tanggal_mulai: PropTypes.string,
    tanggal_selesai: PropTypes.string,
    per_page: PropTypes.number,
  }).isRequired,
  onFilterChange: PropTypes.func.isRequired,
  type: PropTypes.oneOf(['siswa', 'pegawai']),
};

export default IzinApprovalFilters;

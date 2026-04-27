import React from 'react';
import { Box, Button, FormControl, InputAdornment, MenuItem, Select, TextField } from '@mui/material';
import { RotateCcw, Search } from 'lucide-react';

const IzinPegawaiFilters = ({ filters, onFilterChange }) => {
  const handleInputChange = (field, value) => {
    onFilterChange({ [field]: value });
  };

  const handleClearFilters = () => {
    onFilterChange({
      search: '',
      status: '',
      departemen: '',
      jenis_izin: '',
      tanggal_mulai: '',
      tanggal_selesai: '',
    });
  };

  return (
    <Box className="space-y-4">
      <Box className="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <TextField
          placeholder="Cari nama pegawai, NIP, atau email..."
          value={filters.search || ''}
          onChange={(event) => handleInputChange('search', event.target.value)}
          size="small"
          className="xl:col-span-3"
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
            onChange={(event) => handleInputChange('status', event.target.value)}
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
            onChange={(event) => handleInputChange('jenis_izin', event.target.value)}
          >
            <MenuItem value="">Semua Jenis Izin</MenuItem>
            <MenuItem value="sakit">Sakit</MenuItem>
            <MenuItem value="izin">Izin Pribadi</MenuItem>
            <MenuItem value="keperluan_keluarga">Urusan Keluarga</MenuItem>
            <MenuItem value="cuti">Cuti</MenuItem>
            <MenuItem value="dinas_luar">Dinas Luar</MenuItem>
          </Select>
        </FormControl>

        <FormControl size="small" className="xl:col-span-2" sx={{ minWidth: 170 }}>
          <Select
            displayEmpty
            value={filters.departemen || ''}
            onChange={(event) => handleInputChange('departemen', event.target.value)}
          >
            <MenuItem value="">Semua Departemen</MenuItem>
            <MenuItem value="guru">Guru</MenuItem>
            <MenuItem value="staff">Staff</MenuItem>
            <MenuItem value="administrasi">Administrasi</MenuItem>
            <MenuItem value="keamanan">Keamanan</MenuItem>
          </Select>
        </FormControl>

        <TextField
          type="date"
          size="small"
          value={filters.tanggal_mulai || ''}
          onChange={(event) => handleInputChange('tanggal_mulai', event.target.value)}
          className="xl:col-span-1"
          sx={{ minWidth: 160 }}
        />

        <TextField
          type="date"
          size="small"
          value={filters.tanggal_selesai || ''}
          onChange={(event) => handleInputChange('tanggal_selesai', event.target.value)}
          className="xl:col-span-2"
          sx={{ minWidth: 170 }}
        />
      </Box>

      <Box className="flex items-center justify-end gap-2">
        <Button
          variant="outlined"
          size="small"
          startIcon={<RotateCcw className="w-4 h-4" />}
          onClick={handleClearFilters}
        >
          Reset Filter
        </Button>
      </Box>
    </Box>
  );
};

export { IzinPegawaiFilters };
export default IzinPegawaiFilters;

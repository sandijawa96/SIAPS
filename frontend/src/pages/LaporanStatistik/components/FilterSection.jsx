import React from 'react';
import {
  Paper,
  Grid,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  TextField,
  Button,
  Typography,
  Box
} from '@mui/material';
import { Filter, BarChart } from 'lucide-react';
import { motion } from 'framer-motion';

const FilterSection = ({
  periode,
  setPeriode,
  tanggalMulai,
  setTanggalMulai,
  tanggalSelesai,
  setTanggalSelesai,
  selectedTingkat,
  setSelectedTingkat,
  selectedStatus,
  setSelectedStatus,
  selectedDisciplineStatus,
  setSelectedDisciplineStatus,
  selectedKelas,
  setSelectedKelas,
  academicContextLabel = null,
  academicMinDate = null,
  academicMaxDate = null,
  availableTingkat = [],
  availableKelas = [],
  handleGenerateReport
}) => {
  const startLabel = periode === 'hari'
    ? 'Tanggal'
    : periode === 'bulan'
      ? 'Tanggal Acuan Bulan'
    : periode === 'semester'
      ? 'Tanggal Acuan Semester'
      : 'Tanggal Mulai';
  const showEndDate = periode === 'minggu';
  const kelasDisabled = selectedTingkat === 'Semua';

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5 }}
    >
      <Paper 
        elevation={2} 
        className="p-6 mb-6 bg-white rounded-lg shadow-sm border border-gray-100"
      >
        <Box className="flex items-center mb-4">
          <Filter className="w-5 h-5 mr-2 text-blue-600" />
          <Typography variant="h6" className="text-gray-900 font-semibold">
            Filter Laporan
          </Typography>
        </Box>

        <Grid container spacing={3}>
          <Grid item xs={12} sm={6} md={4}>
            <FormControl fullWidth size="small">
              <InputLabel id="periode-label">Periode</InputLabel>
              <Select
                labelId="periode-label"
                value={periode}
                label="Periode"
                onChange={(e) => setPeriode(e.target.value)}
                className="bg-white"
              >
                <MenuItem value="hari">Harian</MenuItem>
                <MenuItem value="minggu">Mingguan</MenuItem>
                <MenuItem value="bulan">Bulanan</MenuItem>
                <MenuItem value="semester">Semester</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              size="small"
              type="date"
              label={startLabel}
              value={tanggalMulai}
              onChange={(e) => setTanggalMulai(e.target.value)}
              InputLabelProps={{
                shrink: true,
              }}
              inputProps={{
                min: academicMinDate || undefined,
                max: academicMaxDate || undefined,
              }}
              className="bg-white"
            />
          </Grid>

          {showEndDate && (
            <Grid item xs={12} sm={6} md={4}>
              <TextField
                fullWidth
                size="small"
                type="date"
                label="Tanggal Selesai"
                value={tanggalSelesai}
                onChange={(e) => setTanggalSelesai(e.target.value)}
                InputLabelProps={{
                  shrink: true,
                }}
                inputProps={{
                  min: tanggalMulai || academicMinDate || undefined,
                  max: academicMaxDate || undefined,
                }}
                className="bg-white"
                helperText="Otomatis +6 hari dari tanggal mulai, tetap bisa diubah."
              />
            </Grid>
          )}

          <Grid item xs={12} sm={6} md={4}>
            <FormControl fullWidth size="small">
              <InputLabel id="tingkat-label">Tingkat</InputLabel>
              <Select
                labelId="tingkat-label"
                value={selectedTingkat}
                label="Tingkat"
                onChange={(e) => {
                  setSelectedTingkat(e.target.value);
                  setSelectedKelas('Semua');
                }}
                className="bg-white"
              >
                <MenuItem value="Semua">Semua</MenuItem>
                {availableTingkat.map((tingkat) => (
                  <MenuItem key={tingkat.id} value={tingkat.id}>
                    {tingkat.nama}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>

          <Grid item xs={12} sm={6} md={4}>
            <FormControl fullWidth size="small">
              <InputLabel id="kelas-label">Kelas</InputLabel>
              <Select
                labelId="kelas-label"
                value={selectedKelas}
                label="Kelas"
                onChange={(e) => setSelectedKelas(e.target.value)}
                className="bg-white"
                disabled={kelasDisabled}
              >
                <MenuItem value="Semua">
                  {kelasDisabled ? 'Pilih tingkat dulu' : 'Semua Kelas di Tingkat Ini'}
                </MenuItem>
                {availableKelas.map((kelas) => (
                  <MenuItem key={kelas.id} value={kelas.id}>
                    {kelas.nama || kelas.namaKelas || kelas.nama_kelas || '-'}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>

          <Grid item xs={12} sm={6} md={4}>
            <FormControl fullWidth size="small">
              <InputLabel id="status-label">Status</InputLabel>
              <Select
                labelId="status-label"
                value={selectedStatus}
                label="Status"
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="bg-white"
              >
                <MenuItem value="Semua">Semua</MenuItem>
                <MenuItem value="Hadir">Hadir</MenuItem>
                <MenuItem value="Terlambat">Terlambat</MenuItem>
                <MenuItem value="Izin">Izin</MenuItem>
                <MenuItem value="Belum_Absen">Belum Absen</MenuItem>
                <MenuItem value="Alpha">Alpha</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid item xs={12} sm={6} md={4}>
            <FormControl fullWidth size="small">
              <InputLabel id="status-disiplin-label">Status Disiplin</InputLabel>
              <Select
                labelId="status-disiplin-label"
                value={selectedDisciplineStatus}
                label="Status Disiplin"
                onChange={(e) => setSelectedDisciplineStatus(e.target.value)}
                className="bg-white"
              >
                <MenuItem value="Semua">Semua</MenuItem>
                <MenuItem value="dalam_batas">Dalam Batas</MenuItem>
                <MenuItem value="monitoring_periode">Monitoring Periode</MenuItem>
                <MenuItem value="perlu_perhatian">Perlu Perhatian</MenuItem>
                <MenuItem value="melewati_batas_telat">Melewati Batas Telat</MenuItem>
                <MenuItem value="melewati_batas_total">Melewati Batas Total</MenuItem>
                <MenuItem value="melewati_batas_alpha">Melewati Batas Alpha</MenuItem>
              </Select>
            </FormControl>
          </Grid>
        </Grid>

        <Box className="mt-3">
          {academicContextLabel && (
            <Typography variant="caption" className="block text-blue-700 font-semibold mb-1">
              Konteks aktif: {academicContextLabel}
            </Typography>
          )}
          <Typography variant="caption" className="text-gray-500">
            {periode === 'bulan' && 'Bulanan membaca satu bulan penuh berdasarkan tanggal mulai yang dipilih.'}
            {periode === 'minggu' && 'Mingguan otomatis menyarankan rentang 7 hari; tanggal selesai dapat dipersingkat jika perlu.'}
            {periode === 'semester' && 'Semester ditentukan dari tanggal acuan: Januari-Juni untuk Semester 1, Juli-Desember untuk Semester 2. Tanggal akhir disiapkan otomatis.'}
            {periode === 'hari' && 'Harian membaca tanggal yang dipilih.'}
          </Typography>
          {selectedTingkat === 'Semua' && (
            <Typography variant="caption" className="block text-amber-700 mt-1">
              Pilih tingkat terlebih dahulu untuk menampilkan pilihan kelas.
            </Typography>
          )}
        </Box>

        <Box className="mt-6 flex justify-end">
          <Button
            variant="contained"
            onClick={handleGenerateReport}
            startIcon={<BarChart className="w-4 h-4" />}
            className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-sm"
            sx={{
              backgroundColor: '#2563eb',
              '&:hover': {
                backgroundColor: '#1d4ed8',
              },
              textTransform: 'none',
              fontWeight: 500
            }}
          >
            Generate Laporan
          </Button>
        </Box>
      </Paper>
    </motion.div>
  );
};

export default FilterSection;

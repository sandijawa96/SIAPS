import React from 'react';
import { Container, Typography, Box, Divider } from '@mui/material';
import { BarChart2 } from 'lucide-react';
import { motion } from 'framer-motion';

// Import komponen
import FilterSection from './components/FilterSection';
import StatisticsCards from './components/StatisticsCards';
import ReportTable from './components/ReportTable';
import ExportActions from './components/ExportActions';

// Import custom hook
import useLaporanStatistik from './hooks/useLaporanStatistik';
import { useServerClock } from '../../hooks/useServerClock';
import { formatServerDateTime } from '../../services/serverClock';

const LaporanStatistik = () => {
  const { serverNowMs, timezone } = useServerClock();
  const {
    loading,
    error,
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
    reportPage,
    setReportPage,
    reportPerPage,
    setReportPerPage,
    reportPagination,
    laporanData,
    statistics,
    academicContextLabel,
    academicMinDate,
    academicMaxDate,
    availableTingkat,
    availableKelas,
    handleGenerateReport,
    handleExportExcel,
    handleExportPDF,
    refetch
  } = useLaporanStatistik();

  return (
    <Container maxWidth="xl" className="py-6">
      {/* Header Section */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="mb-8"
      >
        <Box className="flex justify-between items-start mb-6">
          <Box>
            <Box className="flex items-center mb-2">
              <BarChart2 className="w-8 h-8 mr-3 text-blue-600" />
              <Typography 
                variant="h4" 
                className="text-gray-900 font-bold"
                sx={{ fontSize: '2rem', fontWeight: 700 }}
              >
                Laporan & Statistik
              </Typography>
            </Box>
            <Typography 
              variant="body1" 
              className="text-gray-600"
              sx={{ fontSize: '1rem' }}
            >
              Generate dan analisis laporan kehadiran secara komprehensif
            </Typography>
            {academicContextLabel && (
              <Typography
                variant="body2"
                className="text-blue-700"
                sx={{ fontSize: '0.9rem', fontWeight: 700, mt: 0.5 }}
              >
                Tahun Ajaran Aktif: {academicContextLabel}
              </Typography>
            )}
          </Box>
          
          <ExportActions 
            handleExportExcel={handleExportExcel}
            handleExportPDF={handleExportPDF}
          />
        </Box>
        
        <Divider className="mb-6" />
      </motion.div>

      {/* Filter Section */}
      <FilterSection
        periode={periode}
        setPeriode={setPeriode}
        tanggalMulai={tanggalMulai}
        setTanggalMulai={setTanggalMulai}
        tanggalSelesai={tanggalSelesai}
        setTanggalSelesai={setTanggalSelesai}
        selectedTingkat={selectedTingkat}
        setSelectedTingkat={setSelectedTingkat}
        selectedStatus={selectedStatus}
        setSelectedStatus={setSelectedStatus}
        selectedDisciplineStatus={selectedDisciplineStatus}
        setSelectedDisciplineStatus={setSelectedDisciplineStatus}
        selectedKelas={selectedKelas}
        setSelectedKelas={setSelectedKelas}
        availableTingkat={availableTingkat}
        availableKelas={availableKelas}
        academicContextLabel={academicContextLabel}
        academicMinDate={academicMinDate}
        academicMaxDate={academicMaxDate}
        handleGenerateReport={handleGenerateReport}
      />

      {/* Statistics Cards */}
      <StatisticsCards 
        statistics={statistics}
        loading={loading}
        periode={periode}
      />

      {/* Report Table */}
      <ReportTable 
        data={laporanData}
        loading={loading}
        error={error}
        page={reportPage}
        onPageChange={setReportPage}
        perPage={reportPerPage}
        onPerPageChange={setReportPerPage}
        pagination={reportPagination}
      />

      {/* Footer Info */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.5, delay: 0.8 }}
        className="mt-8"
      >
        <Box className="text-center py-4">
          <Typography variant="body2" className="text-gray-500">
            Data diperbarui secara real-time • Terakhir diperbarui: {formatServerDateTime(serverNowMs, 'id-ID') || '-'}
          </Typography>
        </Box>
      </motion.div>
    </Container>
  );
};

export default LaporanStatistik;

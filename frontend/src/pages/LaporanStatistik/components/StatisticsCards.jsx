import React from 'react';
import { Paper, Typography, Box, Grid, Skeleton } from '@mui/material';
import { 
  UserCheck, 
  Clock, 
  FileText, 
  UserX, 
  TrendingUp,
  ShieldAlert,
  TimerReset,
  LogOut
} from 'lucide-react';
import { motion } from 'framer-motion';

const StatCard = ({ title, value, icon: Icon, color, delay = 0, loading = false }) => {
  const colorClasses = {
    green: {
      bg: 'bg-green-50',
      icon: 'bg-green-500',
      text: 'text-green-600'
    },
    orange: {
      bg: 'bg-orange-50',
      icon: 'bg-orange-500',
      text: 'text-orange-600'
    },
    blue: {
      bg: 'bg-blue-50',
      icon: 'bg-blue-500',
      text: 'text-blue-600'
    },
    red: {
      bg: 'bg-red-50',
      icon: 'bg-red-500',
      text: 'text-red-600'
    },
    purple: {
      bg: 'bg-purple-50',
      icon: 'bg-purple-500',
      text: 'text-purple-600'
    },
    amber: {
      bg: 'bg-amber-50',
      icon: 'bg-amber-500',
      text: 'text-amber-600'
    },
    rose: {
      bg: 'bg-rose-50',
      icon: 'bg-rose-500',
      text: 'text-rose-600'
    }
  };

  const colors = colorClasses[color] || colorClasses.blue;

  if (loading) {
    return (
      <Paper elevation={2} className="p-6 bg-white rounded-lg shadow-sm border border-gray-100">
        <Box className="flex items-center">
          <Skeleton variant="circular" width={48} height={48} />
          <Box className="ml-4 flex-1">
            <Skeleton variant="text" width="60%" height={20} />
            <Skeleton variant="text" width="40%" height={32} />
          </Box>
        </Box>
      </Paper>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay }}
      whileHover={{ y: -2 }}
    >
      <Paper 
        elevation={2} 
        className={`p-6 ${colors.bg} rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200`}
      >
        <Box className="flex items-center">
          <Box className={`${colors.icon} p-3 rounded-lg shadow-sm`}>
            <Icon className="w-6 h-6 text-white" />
          </Box>
          <Box className="ml-4 flex-1">
            <Typography 
              variant="body2" 
              className="text-gray-600 font-medium mb-1"
            >
              {title}
            </Typography>
            <Typography 
              variant="h4" 
              className={`${colors.text} font-bold`}
              sx={{ fontSize: '1.875rem', lineHeight: 1.2 }}
            >
              {value}
            </Typography>
          </Box>
        </Box>
      </Paper>
    </motion.div>
  );
};

const StatisticsCards = ({ statistics, loading, periode = 'hari' }) => {
  const disciplineMode = statistics?.disciplineThresholds?.mode || (periode === 'bulan' ? 'monthly' : periode === 'semester' ? 'semester' : 'none');
  const monthlyLate = statistics?.disciplineThresholds?.monthly_late || {};
  const semesterViolation = statistics?.disciplineThresholds?.semester_total_violation || {};
  const semesterAlpha = statistics?.disciplineThresholds?.semester_alpha || {};

  const policyIsPeriodAligned = disciplineMode === 'monthly' || disciplineMode === 'semester';
  const statusNeedsAttention =
    disciplineMode === 'monthly'
      ? Boolean(monthlyLate.exceeded)
      : disciplineMode === 'semester'
        ? Boolean(semesterViolation.exceeded || semesterAlpha.exceeded)
        : false;

  const summaryTitle = disciplineMode === 'monthly'
    ? 'Monitoring Keterlambatan Bulanan'
    : disciplineMode === 'semester'
      ? 'Monitoring Disiplin Semester'
      : 'Ringkasan Pelanggaran Periode Ini';

  const summaryLines = disciplineMode === 'monthly'
    ? [
        `Batas terlambat bulanan: ${monthlyLate.limit || 0} menit`,
        `Akumulasi terlambat terpantau: ${monthlyLate.minutes || 0} menit`,
        `TAP terpantau: ${statistics.totalTapHari || 0} hari / ${statistics.totalTapMenit || 0} menit`,
        `Belum absen terpantau: ${statistics.totalBelumAbsen || 0}`,
        `Siswa melewati batas: ${statistics.jumlahSiswaMelewatiBatasKeterlambatanBulanan || 0}`,
      ]
    : disciplineMode === 'semester'
      ? [
          `Batas total pelanggaran semester: ${semesterViolation.limit || 0} menit`,
          `Batas alpha semester: ${semesterAlpha.limit || 0} hari`,
          `TAP terpantau: ${statistics.totalTapHari || 0} hari / ${statistics.totalTapMenit || 0} menit`,
          `Belum absen terpantau: ${statistics.totalBelumAbsen || 0}`,
          `Siswa melewati batas total/alpha: ${statistics.jumlahSiswaMelewatiBatas || 0} / ${statistics.jumlahSiswaMelewatiBatasAlphaSemester || 0}`,
        ]
      : [
          `Total pelanggaran periode ini: ${statistics.totalPelanggaranMenit || 0} menit (${statistics.persentasePelanggaran || 0}%)`,
          `TAP terpantau: ${statistics.totalTapHari || 0} hari / ${statistics.totalTapMenit || 0} menit`,
          `Belum absen terpantau: ${statistics.totalBelumAbsen || 0}`,
          `Policy aktif: telat/bulan ${monthlyLate.limit || 0}m, total/smt ${semesterViolation.limit || 0}m, alpha/smt ${semesterAlpha.limit || 0} hari`,
          'Status batas otomatis dibaca pada periode Bulan atau Semester agar tidak tercampur dengan rekap harian.',
        ];

  const statsData = [
    {
      title: 'Total Hadir',
      value: statistics.totalHadir,
      icon: UserCheck,
      color: 'green',
      delay: 0.1
    },
    {
      title: 'Total Terlambat',
      value: statistics.totalTerlambat,
      icon: Clock,
      color: 'orange',
      delay: 0.2
    },
    {
      title: 'Total Izin',
      value: statistics.totalIzin,
      icon: FileText,
      color: 'blue',
      delay: 0.3
    },
    {
      title: 'Belum Absen',
      value: statistics.totalBelumAbsen || 0,
      icon: UserX,
      color: 'amber',
      delay: 0.35
    },
    {
      title: 'TAP (Hari/Menit)',
      value: `${statistics.totalTapHari || 0} / ${statistics.totalTapMenit || 0}m`,
      icon: LogOut,
      color: 'orange',
      delay: 0.38
    },
    {
      title: 'Total Alpha',
      value: statistics.totalAlpha,
      icon: UserX,
      color: 'red',
      delay: 0.4
    },
    {
      title: 'Rata-rata Kehadiran',
      value: `${statistics.avgKehadiran}%`,
      icon: TrendingUp,
      color: 'purple',
      delay: 0.5
    },
    {
      title: 'Pelanggaran (Menit)',
      value: statistics.totalPelanggaranMenit,
      icon: TimerReset,
      color: 'amber',
      delay: 0.6
    },
    {
      title: disciplineMode === 'monthly' ? 'Lampaui Telat/Bulan' : disciplineMode === 'semester' ? 'Lampaui Alpha/Smt' : 'Pelanggaran (%)',
      value: disciplineMode === 'monthly'
        ? (statistics.jumlahSiswaMelewatiBatasKeterlambatanBulanan || 0)
        : disciplineMode === 'semester'
          ? (statistics.jumlahSiswaMelewatiBatasAlphaSemester || 0)
          : `${statistics.persentasePelanggaran}%`,
      icon: ShieldAlert,
      color: statusNeedsAttention ? 'rose' : 'green',
      delay: 0.7
    }
  ];

  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      transition={{ duration: 0.5 }}
      className="mb-6"
    >
      <Grid container spacing={3}>
        {statsData.map((stat, index) => (
          <Grid item xs={12} sm={6} md={4} lg={2.4} key={index}>
            <StatCard
              title={stat.title}
              value={stat.value}
              icon={stat.icon}
              color={stat.color}
              delay={stat.delay}
              loading={loading}
            />
          </Grid>
        ))}
      </Grid>

      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, delay: 0.8 }}
      >
        <Paper elevation={1} className="mt-4 p-4 border border-gray-200 rounded-lg bg-white">
          <Box className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <Box>
              <Typography variant="body2" className="text-gray-700 font-semibold">
                {summaryTitle}
              </Typography>
              {summaryLines.map((line) => (
                <Typography key={line} variant="caption" className="block text-gray-500 mt-0.5">
                  {line}
                </Typography>
              ))}
            </Box>
            <Box className="flex flex-col md:items-end">
              <Typography
                variant="body2"
                className={statusNeedsAttention ? 'text-rose-700 font-semibold' : 'text-emerald-700 font-semibold'}
              >
                {policyIsPeriodAligned
                  ? (statusNeedsAttention ? 'Status: Perlu Perhatian' : 'Status: Dalam Batas')
                  : 'Status: Monitoring Periode'}
              </Typography>
              <Typography variant="caption" className="text-gray-500">
                {policyIsPeriodAligned
                  ? `Siswa melewati batas: ${statistics.jumlahSiswaMelewatiBatas || 0}`
                  : 'Status batas v2 tampil pada laporan Bulan/Semester.'}
              </Typography>
            </Box>
          </Box>
        </Paper>
      </motion.div>
    </motion.div>
  );
};

export default StatisticsCards;

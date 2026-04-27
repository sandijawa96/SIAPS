import React from 'react';
import { Card, CardContent, Grid } from '@mui/material';
import { Users, School, GraduationCap, UserCheck } from 'lucide-react';

const StatCard = ({ icon: Icon, title, value, subtitle, color = 'blue' }) => {
  const getColorClasses = () => {
    switch (color) {
      case 'green':
        return {
          bg: 'bg-green-50',
          icon: 'text-green-600',
          text: 'text-green-900'
        };
      case 'yellow':
        return {
          bg: 'bg-yellow-50',
          icon: 'text-yellow-600',
          text: 'text-yellow-900'
        };
      case 'red':
        return {
          bg: 'bg-red-50',
          icon: 'text-red-600',
          text: 'text-red-900'
        };
      default:
        return {
          bg: 'bg-blue-50',
          icon: 'text-blue-600',
          text: 'text-blue-900'
        };
    }
  };

  const colors = getColorClasses();

  return (
    <Card className="hover:shadow-md transition-shadow">
      <CardContent>
        <div className="flex items-center">
          <div className={`p-3 rounded-full ${colors.bg} mr-4`}>
            <Icon className={`w-6 h-6 ${colors.icon}`} />
          </div>
          <div className="flex-1">
            <p className="text-sm text-gray-600">{title}</p>
            <p className={`text-2xl font-bold ${colors.text}`}>{value}</p>
            {subtitle && (
              <p className="text-xs text-gray-500 mt-1">{subtitle}</p>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

const KelasStatistics = ({ kelasList = [], tingkatList = [] }) => {
  const calculateStats = () => {
    const totalKelas = kelasList.length;
    const totalAnggotaAktif = kelasList.reduce((sum, kelas) => sum + (kelas.jumlahSiswa || 0), 0);
    const totalKapasitas = kelasList.reduce((sum, kelas) => sum + (kelas.kapasitas || 0), 0);
    const kelasWithWali = kelasList.filter(kelas => kelas.waliKelas && kelas.waliKelas !== 'Belum ditentukan').length;
    
    return {
      totalKelas,
      totalAnggotaAktif,
      totalKapasitas,
      kelasWithWali,
      totalTingkat: tingkatList.length,
      occupancyRate: totalKapasitas > 0 ? Math.round((totalAnggotaAktif / totalKapasitas) * 100) : 0
    };
  };

  const stats = calculateStats();

  return (
    <div className="mb-6">
      <Grid container spacing={3}>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={School}
            title="Total Kelas"
            value={stats.totalKelas}
            subtitle={`${stats.totalTingkat} tingkat`}
            color="blue"
          />
        </Grid>
        
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={Users}
            title="Anggota Kelas Aktif"
            value={stats.totalAnggotaAktif}
            subtitle={`Kapasitas tersimpan: ${stats.totalKapasitas}`}
            color="green"
          />
        </Grid>
        
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={UserCheck}
            title="Kelas Ber-Wali"
            value={stats.kelasWithWali}
            subtitle={`${stats.totalKelas - stats.kelasWithWali} belum ada wali`}
            color={stats.kelasWithWali === stats.totalKelas ? 'green' : 'yellow'}
          />
        </Grid>
        
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            icon={GraduationCap}
            title="Rasio Anggota/Kapasitas"
            value={`${stats.occupancyRate}%`}
            subtitle={`${stats.totalAnggotaAktif}/${stats.totalKapasitas} anggota aktif`}
            color={stats.occupancyRate >= 80 ? 'red' : stats.occupancyRate >= 60 ? 'yellow' : 'green'}
          />
        </Grid>
      </Grid>
    </div>
  );
};

export default KelasStatistics;

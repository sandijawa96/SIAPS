import React from 'react';
import { CheckCircle, Calendar, XCircle } from 'lucide-react';

const integerFormatter = new Intl.NumberFormat('id-ID');

const TahunAjaranStatistics = ({ tahunAjaranList }) => {
  const activeTahunAjaran = Array.isArray(tahunAjaranList) 
    ? tahunAjaranList.find(t => t.is_active) 
    : null;

  const totalTahunAjaran = Array.isArray(tahunAjaranList) 
    ? tahunAjaranList.length 
    : 0;

  const completedTahunAjaran = Array.isArray(tahunAjaranList) 
    ? tahunAjaranList.filter(t => t.status === 'Selesai').length 
    : 0;

  const stats = [
    {
      title: 'Tahun Aktif',
      value: activeTahunAjaran?.nama || '-',
      icon: CheckCircle,
      color: 'green'
    },
    {
      title: 'Total Tahun Ajaran',
      value: totalTahunAjaran,
      icon: Calendar,
      color: 'blue'
    },
    {
      title: 'Tahun Selesai',
      value: completedTahunAjaran,
      icon: XCircle,
      color: 'purple'
    }
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      {stats.map((stat, index) => (
        <div key={index} className="bg-white rounded-xl shadow-md p-6 transition-all duration-200 hover:shadow-lg">
          <div className="flex items-center">
            <div className={`p-3 rounded-xl bg-${stat.color}-100`}>
              <stat.icon className={`w-6 h-6 text-${stat.color}-600`} />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">{stat.title}</p>
              <p className="text-2xl font-bold text-gray-900">
                {typeof stat.value === 'number' ? integerFormatter.format(stat.value) : stat.value}
              </p>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default TahunAjaranStatistics;

import React from 'react';
import { Clock, CheckCircle, XCircle } from 'lucide-react';

const StatCard = ({ title, value, icon: Icon, color }) => (
  <div className="bg-white rounded-xl border border-gray-200 p-5">
    <div className="flex items-center gap-3">
      <div className={`rounded-full p-3 ${color}`}>
        <Icon className="w-6 h-6 text-white" />
      </div>
      <div>
        <h3 className="text-sm font-medium text-gray-500">{title}</h3>
        <p className="mt-1 text-2xl font-semibold text-gray-900">{value || 0}</p>
      </div>
    </div>
  </div>
);

const IzinPegawaiStatisticsCards = ({ statistics = {}, loading = false }) => {
  const stats = [
    {
      title: 'Menunggu Persetujuan',
      value: statistics.pending,
      icon: Clock,
      color: 'bg-yellow-500'
    },
    {
      title: 'Disetujui',
      value: statistics.approved,
      icon: CheckCircle,
      color: 'bg-green-500'
    },
    {
      title: 'Ditolak',
      value: statistics.rejected,
      icon: XCircle,
      color: 'bg-red-500'
    }
  ];

  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
            <div className="flex items-center">
              <div className="rounded-full bg-gray-200 p-3 w-12 h-12" />
              <div className="ml-4 space-y-2">
                <div className="h-4 bg-gray-200 rounded w-24" />
                <div className="h-6 bg-gray-200 rounded w-12" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {stats.map((stat) => (
        <StatCard key={stat.title} {...stat} />
      ))}
    </div>
  );
};

export {IzinPegawaiStatisticsCards}
export default IzinPegawaiStatisticsCards;

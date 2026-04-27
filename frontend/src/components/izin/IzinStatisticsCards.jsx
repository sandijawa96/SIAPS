import React from 'react';
import PropTypes from 'prop-types';
import { FileText, Clock, CheckCircle, XCircle } from 'lucide-react';

const StatisticCard = ({ icon: Icon, label, value, colorClass }) => (
  <div className="bg-white rounded-xl border border-gray-200 p-5">
    <div className="flex items-center gap-3">
      <div className={`p-3 rounded-full ${colorClass}`}>
        <Icon className="w-6 h-6" />
      </div>
      <div>
        <p className="text-sm font-medium text-gray-600">{label}</p>
        <p className="text-2xl font-bold text-gray-900">{value}</p>
      </div>
    </div>
  </div>
);

const IzinStatisticsCards = ({ statistics, loading = false }) => {
  const cards = [
    {
      icon: FileText,
      label: 'Total Izin',
      value: statistics.total || 0,
      colorClass: 'bg-blue-100 text-blue-600'
    },
    {
      icon: Clock,
      label: 'Menunggu Persetujuan',
      value: statistics.pending || 0,
      colorClass: 'bg-yellow-100 text-yellow-600'
    },
    {
      icon: CheckCircle,
      label: 'Disetujui',
      value: statistics.approved || 0,
      colorClass: 'bg-green-100 text-green-600'
    },
    {
      icon: XCircle,
      label: 'Ditolak',
      value: statistics.rejected || 0,
      colorClass: 'bg-red-100 text-red-600'
    }
  ];

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((item) => (
          <div key={item} className="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
            <div className="flex items-center gap-3">
              <div className="h-12 w-12 rounded-full bg-gray-200" />
              <div className="space-y-2">
                <div className="h-4 bg-gray-200 rounded w-24" />
                <div className="h-7 bg-gray-200 rounded w-12" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
      {cards.map((card, index) => (
        <StatisticCard key={index} {...card} />
      ))}
    </div>
  );
};

IzinStatisticsCards.propTypes = {
  statistics: PropTypes.shape({
    total: PropTypes.number,
    pending: PropTypes.number,
    approved: PropTypes.number,
    rejected: PropTypes.number
  }).isRequired,
  loading: PropTypes.bool,
};

export default IzinStatisticsCards;

import React from 'react';
import { Shield, Users, Key } from 'lucide-react';

const StatCard = ({ icon: Icon, title, value, color }) => (
  <div className={`bg-white rounded-xl border border-${color}-100 p-6 flex items-center gap-4`}>
    <div className={`p-3 bg-${color}-50 rounded-xl`}>
      <Icon className={`w-6 h-6 text-${color}-600`} />
    </div>
    <div>
      <p className="text-sm text-gray-600">{title}</p>
      <h3 className="text-2xl font-bold text-gray-900">{value}</h3>
    </div>
  </div>
);

const RoleStatistics = ({ stats }) => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <StatCard
        icon={Shield}
        title="Total Primary Role"
        value={stats.totalPrimaryRoles}
        color="purple"
      />
      <StatCard
        icon={Users}
        title="Total Sub Role"
        value={stats.totalSubRoles}
        color="blue"
      />
      <StatCard
        icon={Key}
        title="Total Permission"
        value={stats.totalPermissions}
        color="indigo"
      />
    </div>
  );
};

export default RoleStatistics;

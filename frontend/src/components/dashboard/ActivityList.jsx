import React from 'react';
import { Clock } from 'lucide-react';

const ActivityList = ({ activities = [], title = "Aktivitas Terbaru" }) => {
  const getStatusColor = (status) => {
    switch (status) {
      case 'success':
        return 'bg-green-500';
      case 'warning':
        return 'bg-yellow-500';
      case 'error':
        return 'bg-red-500';
      case 'info':
      default:
        return 'bg-blue-500';
    }
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-200 p-5 lg:p-6">
        <h3 className="flex items-center text-base font-semibold text-slate-900 lg:text-lg">
          <Clock className="w-5 h-5 mr-2" />
          {title}
        </h3>
      </div>
      <div className="p-5 lg:p-6">
        {activities.length === 0 ? (
          <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
            <p className="text-sm text-slate-500">Belum ada aktivitas</p>
          </div>
        ) : (
          <div className="space-y-4">
            {activities.map((activity, index) => (
              <div
                key={activity.id || activity.occurred_at || `${activity.user}-${activity.time}-${index}`}
                className="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3"
              >
                <div className={`mt-1 h-2 w-2 rounded-full ${getStatusColor(activity.status)}`} />
                <div className="flex-1">
                  <p className="text-sm text-slate-900">
                    <span className="font-medium">{activity.user}</span> {activity.action}
                  </p>
                  <p className="mt-1 text-xs text-slate-500">{activity.time}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default ActivityList;

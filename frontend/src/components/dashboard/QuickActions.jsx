import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, Shield, Settings, TrendingUp, Book, Calendar, UserCheck, Clock, Activity } from 'lucide-react';

const iconMap = {
  users: Users,
  shield: Shield,
  settings: Settings,
  trendingUp: TrendingUp,
  book: Book,
  calendar: Calendar,
  userCheck: UserCheck,
  clock: Clock,
  activity: Activity
};

const colorMap = {
  blue: 'text-blue-600',
  green: 'text-green-600',
  purple: 'text-purple-600',
  orange: 'text-orange-600',
  yellow: 'text-yellow-600'
};

const QuickActions = ({ actions = [] }) => {
  const navigate = useNavigate();

  const handleActionClick = (action) => {
    if (action.path) {
      navigate(action.path);
    } else if (action.onClick) {
      action.onClick();
    }
  };

  if (actions.length === 0) {
    return null;
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
      <h3 className="mb-4 text-base font-semibold text-slate-900 lg:text-lg">Aksi Cepat</h3>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {actions.map((action) => {
          const Icon = iconMap[action.icon] || Settings;
          const colorClass = colorMap[action.color] || colorMap.blue;
          const actionKey = action.path || action.title;

          return (
            <button
              key={actionKey}
              type="button"
              onClick={() => handleActionClick(action)}
              className="rounded-lg border border-slate-200 bg-slate-50/40 p-4 transition-all hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <Icon className={`mx-auto mb-2 h-6 w-6 ${colorClass}`} />
              <p className="text-sm font-medium text-slate-900">{action.title}</p>
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default QuickActions;

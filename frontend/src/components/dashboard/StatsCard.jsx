import React from 'react';
import { Users, UserCheck, Shield, Activity, Book, Calendar, Clock, TrendingUp } from 'lucide-react';

const iconMap = {
  users: Users,
  userCheck: UserCheck,
  shield: Shield,
  activity: Activity,
  book: Book,
  calendar: Calendar,
  clock: Clock,
  trendingUp: TrendingUp
};

const colorMap = {
  blue: {
    bg: 'bg-blue-500',
    text: 'text-blue-600',
    light: 'bg-blue-50'
  },
  green: {
    bg: 'bg-green-500',
    text: 'text-green-600',
    light: 'bg-green-50'
  },
  purple: {
    bg: 'bg-purple-500',
    text: 'text-purple-600',
    light: 'bg-purple-50'
  },
  orange: {
    bg: 'bg-orange-500',
    text: 'text-orange-600',
    light: 'bg-orange-50'
  },
  yellow: {
    bg: 'bg-yellow-500',
    text: 'text-yellow-600',
    light: 'bg-yellow-50'
  }
};

const StatsCard = ({ 
  title, 
  value, 
  subtitle, 
  icon = 'activity',
  color = 'blue',
  onClick
}) => {
  const Icon = iconMap[icon] || Activity;
  const colors = colorMap[color] || colorMap.blue;

  return (
    <div 
      className={`
        rounded-xl border border-slate-200 bg-white p-5 shadow-sm 
        transition-all hover:-translate-y-0.5 hover:shadow-md
        ${onClick ? 'cursor-pointer' : ''}
      `}
      onClick={onClick}
    >
      <div className="flex items-start gap-4">
        <div className={`${colors.bg} rounded-lg p-2.5`}>
          <Icon className="w-6 h-6 text-white" />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
          <p className="mt-1 text-2xl font-bold leading-none text-slate-900">{value}</p>
          {subtitle && (
            <p className="mt-2 text-sm text-slate-500">{subtitle}</p>
          )}
        </div>
      </div>
    </div>
  );
};

export default StatsCard;

import React from 'react';
import { Link } from 'react-router-dom';
import { MapIcon } from '@heroicons/react/24/outline';
import { useAuth } from '../../hooks/useAuth';

const LiveTrackingMenu = () => {
  const { hasPermission } = useAuth();

  if (!hasPermission('view_live_tracking')) {
    return null;
  }

  return (
    <Link
      to="/live-tracking"
      className="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
    >
      <MapIcon className="w-5 h-5 mr-3" />
      <span>Live Tracking Siswa</span>
    </Link>
  );
};

export default LiveTrackingMenu;

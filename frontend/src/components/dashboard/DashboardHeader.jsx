import React from 'react';

const DashboardHeader = ({ title, description, userName, userPhotoUrl }) => {
  const userInitial = String(userName || 'P').trim().charAt(0).toUpperCase() || 'P';

  return (
    <div className="relative overflow-hidden rounded-xl border border-sky-500/20 bg-gradient-to-r from-sky-700 via-blue-700 to-cyan-700 px-5 py-6 text-white shadow-sm lg:px-6">
      <div className="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-white/10 blur-2xl" />
      <div className="pointer-events-none absolute -bottom-12 left-1/3 h-24 w-24 rounded-full bg-cyan-300/20 blur-2xl" />
      <div className="relative flex items-start gap-4">
        <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/95 shadow-lg ring-1 ring-white/50">
          {userPhotoUrl ? (
            <img
              src={userPhotoUrl}
              alt={`Foto ${userName}`}
              className="h-full w-full object-cover"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-cyan-50 text-2xl font-bold text-sky-700">
              {userInitial}
            </div>
          )}
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-cyan-100/90">Welcome Dashboard</p>
          <h1 className="mt-1 text-2xl font-bold leading-tight">{title}</h1>
          <p className="mt-2 text-sm text-cyan-100/95 lg:text-base">
            Selamat datang, <span className="font-semibold text-white">{userName}</span>. {description}
          </p>
        </div>
      </div>
    </div>
  );
};

export default DashboardHeader;

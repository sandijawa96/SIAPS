import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import LoadingScreen from './LoadingScreen';

const ProtectedRoute = ({
  children,
  requiredPermission,
  requiredAnyPermissions = [],
  requiredRole,
  requiredAnyRoles = [],
}) => {
  const { user, isLoading, hasPermission, hasAnyPermission, hasRole, hasAnyRole } = useAuth();
  const location = useLocation();

  // Show loading screen while checking auth status
  if (isLoading) {
    return <LoadingScreen message="Memeriksa autentikasi..." />;
  }

  // Redirect to login if not authenticated
  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Check for any-of required permissions
  if (requiredAnyPermissions.length > 0 && !hasAnyPermission(requiredAnyPermissions)) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-800 mb-4">
            Akses Ditolak
          </h1>
          <p className="text-gray-600 mb-8">
            Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.
            <br />
            Diperlukan salah satu permission:
            {' '}
            <strong>{requiredAnyPermissions.join(', ')}</strong>
          </p>
          <button
            onClick={() => window.history.back()}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Kembali
          </button>
        </div>
      </div>
    );
  }

  // Check for required permission
  if (requiredPermission && !hasPermission(requiredPermission)) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-800 mb-4">
            Akses Ditolak
          </h1>
          <p className="text-gray-600 mb-8">
            Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.
            <br />
            Diperlukan permission:
            {' '}
            <strong>{requiredPermission}</strong>
          </p>
          <button
            onClick={() => window.history.back()}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Kembali
          </button>
        </div>
      </div>
    );
  }

  // Check for required role
  if (requiredRole && !hasRole(requiredRole)) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-800 mb-4">
            Akses Ditolak
          </h1>
          <p className="text-gray-600 mb-8">
            Maaf, Anda tidak memiliki role yang diperlukan untuk mengakses halaman ini.
            <br />
            Diperlukan role:
            {' '}
            <strong>{requiredRole}</strong>
          </p>
          <button
            onClick={() => window.history.back()}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Kembali
          </button>
        </div>
      </div>
    );
  }

  // Check for any-of required roles
  if (requiredAnyRoles.length > 0 && !hasAnyRole(requiredAnyRoles)) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-800 mb-4">
            Akses Ditolak
          </h1>
          <p className="text-gray-600 mb-8">
            Maaf, role Anda tidak termasuk untuk halaman ini.
            <br />
            Diperlukan salah satu role:
            {' '}
            <strong>{requiredAnyRoles.join(', ')}</strong>
          </p>
          <button
            onClick={() => window.history.back()}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Kembali
          </button>
        </div>
      </div>
    );
  }

  // If all checks pass, render the protected content
  return children;
};

export default ProtectedRoute;

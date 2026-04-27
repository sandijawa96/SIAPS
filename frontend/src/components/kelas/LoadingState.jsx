import React from 'react';
import { Loader2, AlertCircle, RefreshCw } from 'lucide-react';

export const LoadingState = ({ 
  loading, 
  error, 
  isRefreshing, 
  onRetry,
  children 
}) => {
  // Loading state
  if (loading && !isRefreshing) {
    return (
      <div className="flex flex-col items-center justify-center h-64 space-y-4">
        <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
        <div className="text-center">
          <p className="text-gray-600 font-medium">Memuat data kelas...</p>
          <p className="text-sm text-gray-500">Mohon tunggu sebentar</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="flex flex-col items-center justify-center h-64 space-y-4">
        <AlertCircle className="w-12 h-12 text-red-500" />
        <div className="text-center space-y-2">
          <p className="text-red-600 font-medium">Terjadi Kesalahan</p>
          <p className="text-sm text-gray-600">{error}</p>
          {onRetry && (
            <button
              onClick={onRetry}
              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              <RefreshCw className="w-4 h-4" />
              Coba Lagi
            </button>
          )}
        </div>
      </div>
    );
  }

  // Success state - render children
  return (
    <div className="relative">
      {/* Background refresh indicator */}
      {isRefreshing && (
        <div className="absolute top-0 left-0 right-0 z-10">
          <div className="bg-blue-50 border-l-4 border-blue-400 p-2">
            <div className="flex items-center">
              <Loader2 className="w-4 h-4 animate-spin text-blue-600 mr-2" />
              <p className="text-sm text-blue-700">Memperbarui data...</p>
            </div>
          </div>
        </div>
      )}
      
      {/* Content */}
      <div className={isRefreshing ? 'mt-12' : ''}>
        {children}
      </div>
    </div>
  );
};

// Skeleton loading untuk card kelas
export const KelasCardSkeleton = () => {
  return (
    <div className="bg-white rounded-lg shadow-md p-6 animate-pulse">
      <div className="flex justify-between items-start mb-4">
        <div className="space-y-2">
          <div className="h-6 bg-gray-200 rounded w-24"></div>
          <div className="h-4 bg-gray-200 rounded w-16"></div>
        </div>
        <div className="h-8 w-8 bg-gray-200 rounded"></div>
      </div>
      
      <div className="space-y-3">
        <div className="flex justify-between">
          <div className="h-4 bg-gray-200 rounded w-20"></div>
          <div className="h-4 bg-gray-200 rounded w-32"></div>
        </div>
        <div className="flex justify-between">
          <div className="h-4 bg-gray-200 rounded w-16"></div>
          <div className="h-4 bg-gray-200 rounded w-12"></div>
        </div>
        <div className="flex justify-between">
          <div className="h-4 bg-gray-200 rounded w-24"></div>
          <div className="h-4 bg-gray-200 rounded w-20"></div>
        </div>
      </div>
      
      <div className="mt-4 pt-4 border-t border-gray-200">
        <div className="flex gap-2">
          <div className="h-8 bg-gray-200 rounded w-20"></div>
          <div className="h-8 bg-gray-200 rounded w-16"></div>
          <div className="h-8 bg-gray-200 rounded w-16"></div>
        </div>
      </div>
    </div>
  );
};

// Grid skeleton untuk multiple cards
export const KelasGridSkeleton = ({ count = 6 }) => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
      {Array.from({ length: count }).map((_, index) => (
        <KelasCardSkeleton key={index} />
      ))}
    </div>
  );
};

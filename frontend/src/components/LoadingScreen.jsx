import React from 'react';
import { Box, CircularProgress, Typography, Skeleton } from '@mui/material';

const LoadingScreen = ({ 
  message = "Memuat...", 
  variant = "circular",
  showSkeleton = false 
}) => {
  if (showSkeleton) {
    return (
      <Box className="p-6 space-y-4">
        <Skeleton variant="rectangular" width="100%" height={60} />
        <Box className="space-y-2">
          <Skeleton variant="text" width="80%" />
          <Skeleton variant="text" width="60%" />
          <Skeleton variant="text" width="90%" />
        </Box>
        <Box className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {[...Array(6)].map((_, index) => (
            <Skeleton key={index} variant="rectangular" height={120} />
          ))}
        </Box>
      </Box>
    );
  }

  return (
    <Box 
      className="flex flex-col items-center justify-center min-h-screen bg-gray-50"
      sx={{ 
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 9999,
        backgroundColor: 'rgba(255, 255, 255, 0.9)',
        backdropFilter: 'blur(2px)'
      }}
    >
      <Box className="text-center">
        {variant === "circular" ? (
          <CircularProgress 
            size={60} 
            thickness={4}
            sx={{ 
              color: '#3B82F6',
              marginBottom: 2
            }}
          />
        ) : (
          <Box className="flex space-x-1 justify-center mb-4">
            {[...Array(3)].map((_, index) => (
              <Box
                key={index}
                className="w-3 h-3 bg-blue-500 rounded-full animate-bounce"
                style={{
                  animationDelay: `${index * 0.1}s`,
                  animationDuration: '0.6s'
                }}
              />
            ))}
          </Box>
        )}
        
        <Typography 
          variant="h6" 
          className="text-gray-700 font-medium"
          sx={{ marginBottom: 1 }}
        >
          {message}
        </Typography>
        
        <Typography 
          variant="body2" 
          className="text-gray-500"
        >
          Mohon tunggu sebentar...
        </Typography>
      </Box>
    </Box>
  );
};

// Komponen loading untuk table
export const TableLoadingScreen = ({ rows = 5, columns = 4 }) => (
  <Box className="p-4">
    <Skeleton variant="rectangular" width="100%" height={40} className="mb-4" />
    {[...Array(rows)].map((_, rowIndex) => (
      <Box key={rowIndex} className="flex space-x-4 mb-3">
        {[...Array(columns)].map((_, colIndex) => (
          <Skeleton 
            key={colIndex} 
            variant="text" 
            width={`${Math.random() * 40 + 60}%`} 
            height={20} 
          />
        ))}
      </Box>
    ))}
  </Box>
);

// Komponen loading untuk form
export const FormLoadingScreen = () => (
  <Box className="p-6 space-y-4">
    <Skeleton variant="text" width="40%" height={32} />
    <Box className="space-y-3">
      {[...Array(5)].map((_, index) => (
        <Box key={index}>
          <Skeleton variant="text" width="20%" height={20} className="mb-1" />
          <Skeleton variant="rectangular" width="100%" height={40} />
        </Box>
      ))}
    </Box>
    <Box className="flex space-x-3 pt-4">
      <Skeleton variant="rectangular" width={100} height={36} />
      <Skeleton variant="rectangular" width={80} height={36} />
    </Box>
  </Box>
);

// Komponen loading untuk card grid
export const CardGridLoadingScreen = ({ count = 6 }) => (
  <Box className="p-6">
    <Skeleton variant="text" width="30%" height={32} className="mb-6" />
    <Box className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {[...Array(count)].map((_, index) => (
        <Box key={index} className="border rounded-lg p-4">
          <Skeleton variant="rectangular" width="100%" height={120} className="mb-3" />
          <Skeleton variant="text" width="80%" height={20} className="mb-2" />
          <Skeleton variant="text" width="60%" height={16} />
        </Box>
      ))}
    </Box>
  </Box>
);

// Komponen loading untuk dashboard
export const DashboardLoadingScreen = () => (
  <Box className="p-6 space-y-6">
    {/* Header */}
    <Box className="flex justify-between items-center">
      <Skeleton variant="text" width="30%" height={32} />
      <Skeleton variant="rectangular" width={120} height={36} />
    </Box>
    
    {/* Stats Cards */}
    <Box className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {[...Array(4)].map((_, index) => (
        <Box key={index} className="bg-white p-6 rounded-lg shadow">
          <Skeleton variant="text" width="60%" height={20} className="mb-2" />
          <Skeleton variant="text" width="40%" height={32} className="mb-2" />
          <Skeleton variant="text" width="80%" height={16} />
        </Box>
      ))}
    </Box>
    
    {/* Charts */}
    <Box className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Box className="bg-white p-6 rounded-lg shadow">
        <Skeleton variant="text" width="40%" height={24} className="mb-4" />
        <Skeleton variant="rectangular" width="100%" height={300} />
      </Box>
      <Box className="bg-white p-6 rounded-lg shadow">
        <Skeleton variant="text" width="40%" height={24} className="mb-4" />
        <Skeleton variant="rectangular" width="100%" height={300} />
      </Box>
    </Box>
  </Box>
);

export default LoadingScreen;

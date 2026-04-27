import React from 'react';
import { Box, Typography, Fade } from '@mui/material';

const LocationHeader = () => (
  <Fade in timeout={500}>
    <Box>
      <Typography 
        variant="h3" 
        component="h1" 
        className="font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-2"
      >
        Manajemen Lokasi GPS
      </Typography>
      <Typography variant="h6" color="text.secondary">
        Kelola titik lokasi untuk sistem absensi dengan teknologi GPS
      </Typography>
    </Box>
  </Fade>
);

export default LocationHeader;

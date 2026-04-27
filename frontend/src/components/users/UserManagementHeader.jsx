import React from 'react';
import { Typography, Box } from '@mui/material';
import { Users } from 'lucide-react';

const UserManagementHeader = () => {
  return (
    <Box className="flex items-center gap-3 mb-6">
      <div className="p-2 bg-blue-100 rounded-lg">
        <Users className="w-6 h-6 text-blue-600" />
      </div>
      <div>
        <Typography variant="h4" className="font-bold text-gray-900">
          Manajemen Pengguna
        </Typography>
        <Typography variant="body2" className="text-gray-600">
          Kelola data pegawai dan siswa dalam sistem
        </Typography>
      </div>
    </Box>
  );
};

export default UserManagementHeader;

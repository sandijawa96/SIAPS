import React from 'react';
import { Tabs, Tab, Box, Badge } from '@mui/material';
import { Users, GraduationCap, ShieldCheck } from 'lucide-react';

const UserTabs = ({
  activeTab,
  onTabChange,
  userCounts = {},
  showVerificationTab = false,
  showManagementTabs = true,
}) => {
  const handleChange = (event, newValue) => {
    onTabChange(newValue);
  };

  return (
    <Box className="mb-6">
      <Tabs
        value={activeTab}
        onChange={handleChange}
        className="border-b border-gray-200"
        sx={{
          '& .MuiTabs-indicator': {
            backgroundColor: '#3B82F6',
          },
        }}
      >
        {showManagementTabs && (
          <Tab
            value="pegawai"
            label={
              <div className="flex items-center gap-2">
                <Users className="w-4 h-4" />
                <span>Pegawai</span>
                {userCounts.pegawai && (
                  <Badge 
                    badgeContent={userCounts.pegawai} 
                    color="primary"
                    className="ml-1"
                  />
                )}
              </div>
            }
            className="text-gray-600 hover:text-blue-600 transition-colors"
            sx={{
              '&.Mui-selected': {
                color: '#3B82F6',
              },
            }}
          />
        )}
        {showManagementTabs && (
          <Tab
            value="siswa"
            label={
              <div className="flex items-center gap-2">
                <GraduationCap className="w-4 h-4" />
                <span>Siswa</span>
                {userCounts.siswa && (
                  <Badge 
                    badgeContent={userCounts.siswa} 
                    color="primary"
                    className="ml-1"
                  />
                )}
              </div>
            }
            className="text-gray-600 hover:text-blue-600 transition-colors"
            sx={{
              '&.Mui-selected': {
                color: '#3B82F6',
              },
            }}
          />
        )}
        {showVerificationTab && (
          <Tab
            value="verifikasi"
            label={
              <div className="flex items-center gap-2">
                <ShieldCheck className="w-4 h-4" />
                <span>Verifikasi Data Pribadi</span>
                {userCounts.verifikasi ? (
                  <Badge
                    badgeContent={userCounts.verifikasi}
                    color="primary"
                    className="ml-1"
                  />
                ) : null}
              </div>
            }
            className="text-gray-600 hover:text-blue-600 transition-colors"
            sx={{
              '&.Mui-selected': {
                color: '#3B82F6',
              },
            }}
          />
        )}
      </Tabs>
    </Box>
  );
};

export default UserTabs;

import React from 'react';
import { Tabs, Tab, Box } from '@mui/material';
import { School, BookOpen } from 'lucide-react';

const KelasTabs = ({ activeTab, onTabChange }) => {
  const handleChange = (event, newValue) => {
    onTabChange(newValue);
  };

  return (
    <Box className="mb-6">
      <Tabs 
        value={activeTab} 
        onChange={handleChange}
        className="border-b border-gray-200"
        indicatorColor="primary"
        textColor="primary"
      >
        <Tab 
          value="kelas"
          icon={<School className="w-5 h-5" />}
          iconPosition="start"
          label="Kelas"
          className="text-gray-600 hover:text-blue-600 transition-colors"
        />
        <Tab 
          value="tingkat"
          icon={<BookOpen className="w-5 h-5" />}
          iconPosition="start"
          label="Tingkat"
          className="text-gray-600 hover:text-blue-600 transition-colors"
        />
      </Tabs>
    </Box>
  );
};

export default KelasTabs;

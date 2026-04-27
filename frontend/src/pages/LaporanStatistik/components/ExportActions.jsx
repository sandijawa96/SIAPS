import React from 'react';
import { Button, Box, Tooltip } from '@mui/material';
import { FileSpreadsheet, FileText, Download } from 'lucide-react';
import { motion } from 'framer-motion';

const ExportActions = ({ handleExportExcel, handleExportPDF }) => {
  const exportButtons = [
    {
      label: 'Export Excel',
      icon: FileSpreadsheet,
      onClick: handleExportExcel,
      color: 'success',
      bgColor: 'bg-green-600 hover:bg-green-700',
      delay: 0.1
    },
    {
      label: 'Export PDF',
      icon: FileText,
      onClick: handleExportPDF,
      color: 'error',
      bgColor: 'bg-red-600 hover:bg-red-700',
      delay: 0.2
    }
  ];

  return (
    <Box className="flex items-center space-x-3">
      {exportButtons.map((button, index) => (
        <motion.div
          key={index}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.3, delay: button.delay }}
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
        >
          <Tooltip title={button.label} arrow>
            <Button
              variant="outlined"
              onClick={button.onClick}
              startIcon={<button.icon className="w-4 h-4" />}
              className={`${button.bgColor} text-white border-none px-4 py-2 rounded-lg shadow-sm transition-all duration-200`}
              sx={{
                backgroundColor: button.color === 'success' ? '#16a34a' : '#dc2626',
                '&:hover': {
                  backgroundColor: button.color === 'success' ? '#15803d' : '#b91c1c',
                  borderColor: 'transparent'
                },
                textTransform: 'none',
                fontWeight: 500,
                borderColor: 'transparent'
              }}
            >
              {button.label}
            </Button>
          </Tooltip>
        </motion.div>
      ))}
    </Box>
  );
};

export default ExportActions;

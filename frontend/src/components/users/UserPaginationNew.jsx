import React from 'react';
import { 
  Box, 
  Pagination, 
  Typography, 
  FormControl, 
  Select, 
  MenuItem,
  Paper
} from '@mui/material';

const UserPagination = ({ 
  pagination = {}, 
  onPageChange,
  onPerPageChange 
}) => {
  const {
    current_page = 1,
    last_page = 1,
    per_page = 15,
    total = 0,
    from = 0,
    to = 0
  } = pagination;

  const handlePageChange = (event, page) => {
    onPageChange(page);
  };

  const handlePerPageChange = (event) => {
    if (onPerPageChange) {
      onPerPageChange(event.target.value);
    }
  };

  if (total === 0) {
    return null;
  }

  return (
    <Paper className="p-4 mt-4 shadow-sm border border-gray-100">
      <Box className="flex flex-col sm:flex-row justify-between items-center gap-4">
        {/* Info Text */}
        <Typography variant="body2" color="textSecondary">
          Menampilkan {from} - {to} dari {total} data
        </Typography>

        {/* Pagination Controls */}
        <Box className="flex items-center gap-4">
          {/* Per Page Selector */}
          {onPerPageChange && (
            <Box className="flex items-center gap-2">
              <Typography variant="body2" color="textSecondary">
                Per halaman:
              </Typography>
              <FormControl size="small">
                <Select
                  value={per_page}
                  onChange={handlePerPageChange}
                  className="min-w-[70px]"
                >
                  <MenuItem value={10}>10</MenuItem>
                  <MenuItem value={15}>15</MenuItem>
                  <MenuItem value={25}>25</MenuItem>
                  <MenuItem value={50}>50</MenuItem>
                  <MenuItem value={100}>100</MenuItem>
                </Select>
              </FormControl>
            </Box>
          )}

          {/* Pagination */}
          <Pagination
            count={last_page}
            page={current_page}
            onChange={handlePageChange}
            color="primary"
            shape="rounded"
            showFirstButton
            showLastButton
            size="small"
          />
        </Box>
      </Box>
    </Paper>
  );
};

export default UserPagination;

import React from 'react';
import { Box, Card, TextField, Button, IconButton, Tooltip, MenuItem, Slide } from '@mui/material';
import { Search, Eye, MoreVertical, Plus } from 'lucide-react';

const LocationControls = ({ 
  searchTerm, 
  onSearchChange, 
  filterStatus, 
  onFilterChange, 
  viewMode, 
  onViewModeChange, 
  onAddClick 
}) => (
  <Slide in timeout={900} direction="up">
    <Card className="p-4">
      <Box className="flex flex-col md:flex-row gap-4 items-center justify-between">
        <Box className="flex flex-col sm:flex-row gap-3 flex-1">
          <TextField
            placeholder="Cari lokasi..."
            value={searchTerm}
            onChange={(e) => onSearchChange(e.target.value)}
            InputProps={{
              startAdornment: <Search className="w-5 h-5 mr-2 text-gray-400" />
            }}
            className="min-w-[300px]"
            size="small"
          />
          
          <TextField
            select
            value={filterStatus}
            onChange={(e) => onFilterChange(e.target.value)}
            size="small"
            className="min-w-[150px]"
          >
            <MenuItem value="all">Semua Status</MenuItem>
            <MenuItem value="active">Aktif</MenuItem>
            <MenuItem value="inactive">Tidak Aktif</MenuItem>
          </TextField>
        </Box>

        <Box className="flex items-center gap-2">
          <Tooltip title={viewMode === 'table' ? 'Tampilan Kartu' : 'Tampilan Tabel'}>
            <IconButton 
              onClick={() => onViewModeChange(viewMode === 'table' ? 'cards' : 'table')}
              color="primary"
            >
              {viewMode === 'table' ? <Eye /> : <MoreVertical />}
            </IconButton>
          </Tooltip>
          
          <Button
            variant="contained"
            startIcon={<Plus />}
            onClick={onAddClick}
            className="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700"
          >
            Tambah Lokasi
          </Button>
        </Box>
      </Box>
    </Card>
  </Slide>
);

export default LocationControls;

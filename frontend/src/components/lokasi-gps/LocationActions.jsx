import React, { useState } from 'react';
import {
  Box,
  Button,
  Menu,
  MenuItem,
  Checkbox,
  Typography,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Alert
} from '@mui/material';
import {
  Plus,
  MoreVertical,
  Trash2,
  Download,
  Upload,
  MapPin,
  BarChart3,
  CheckSquare,
  Square
} from 'lucide-react';

const LocationActions = ({
  selectedLocations,
  onSelectAll,
  onDeselectAll,
  onAdd,
  onBulkDelete,
  onImportExport,
  onMonitoring,
  totalCount,
  isAllSelected
}) => {
  const [anchorEl, setAnchorEl] = useState(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

  const selectedCount = selectedLocations.length;
  const hasSelection = selectedCount > 0;

  // Handle menu open/close
  const handleMenuOpen = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
  };

  // Handle bulk delete confirmation
  const handleBulkDeleteConfirm = () => {
    onBulkDelete(selectedLocations);
    setDeleteDialogOpen(false);
  };

  return (
    <Box className="flex items-center justify-between mb-6">
      {/* Left side - Selection controls */}
      <Box className="flex items-center space-x-4">
        {/* Select All Checkbox */}
        <Box className="flex items-center space-x-2">
          <Checkbox
            checked={isAllSelected}
            indeterminate={hasSelection && !isAllSelected}
            onChange={isAllSelected ? onDeselectAll : onSelectAll}
            icon={<Square className="w-5 h-5" />}
            checkedIcon={<CheckSquare className="w-5 h-5" />}
          />
          <Typography variant="body2" color="textSecondary">
            {hasSelection 
              ? `${selectedCount} dari ${totalCount} dipilih`
              : `Pilih semua (${totalCount})`
            }
          </Typography>
        </Box>

        {/* Bulk Actions */}
        {hasSelection && (
          <Box className="flex items-center space-x-2">
            <Button
              variant="outlined"
              color="error"
              size="small"
              startIcon={<Trash2 className="w-4 h-4" />}
              onClick={() => setDeleteDialogOpen(true)}
            >
              Hapus ({selectedCount})
            </Button>
          </Box>
        )}
      </Box>

      {/* Right side - Main actions */}
      <Box className="flex items-center space-x-2">
        {/* Monitoring Button */}
        <Button
          variant="outlined"
          startIcon={<BarChart3 className="w-4 h-4" />}
          onClick={onMonitoring}
          className="hidden sm:flex"
        >
          Monitoring
        </Button>

        {/* Import/Export Button */}
        <Button
          variant="outlined"
          startIcon={<Upload className="w-4 h-4" />}
          onClick={onImportExport}
          className="hidden sm:flex"
        >
          Import/Export
        </Button>

        {/* Add Location Button */}
        <Button
          variant="contained"
          startIcon={<Plus className="w-4 h-4" />}
          onClick={onAdd}
          className="bg-blue-600 hover:bg-blue-700"
        >
          Tambah Lokasi
        </Button>

        {/* More Actions Menu (Mobile) */}
        <Button
          variant="outlined"
          onClick={handleMenuOpen}
          className="sm:hidden"
          sx={{ minWidth: 'auto', p: 1 }}
        >
          <MoreVertical className="w-4 h-4" />
        </Button>

        <Menu
          anchorEl={anchorEl}
          open={Boolean(anchorEl)}
          onClose={handleMenuClose}
          anchorOrigin={{
            vertical: 'bottom',
            horizontal: 'right',
          }}
          transformOrigin={{
            vertical: 'top',
            horizontal: 'right',
          }}
        >
          <MenuItem onClick={() => { onMonitoring(); handleMenuClose(); }}>
            <BarChart3 className="w-4 h-4 mr-2" />
            Monitoring
          </MenuItem>
          <MenuItem onClick={() => { onImportExport(); handleMenuClose(); }}>
            <Upload className="w-4 h-4 mr-2" />
            Import/Export
          </MenuItem>
        </Menu>
      </Box>

      {/* Bulk Delete Confirmation Dialog */}
      <Dialog
        open={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>
          Konfirmasi Hapus Lokasi
        </DialogTitle>
        <DialogContent>
          <Alert severity="warning" className="mb-4">
            Tindakan ini tidak dapat dibatalkan!
          </Alert>
          <Typography>
            Apakah Anda yakin ingin menghapus {selectedCount} lokasi yang dipilih?
          </Typography>
          <Box className="mt-3 p-3 bg-gray-50 rounded-lg">
            <Typography variant="body2" color="textSecondary">
              Lokasi yang akan dihapus:
            </Typography>
            <Box className="mt-2 max-h-32 overflow-y-auto">
              {selectedLocations.slice(0, 5).map((id, index) => (
                <Typography key={id} variant="body2" className="flex items-center">
                  <MapPin className="w-3 h-3 mr-1" />
                  Lokasi ID: {id}
                </Typography>
              ))}
              {selectedCount > 5 && (
                <Typography variant="body2" color="textSecondary">
                  ... dan {selectedCount - 5} lokasi lainnya
                </Typography>
              )}
            </Box>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialogOpen(false)}>
            Batal
          </Button>
          <Button
            onClick={handleBulkDeleteConfirm}
            color="error"
            variant="contained"
            startIcon={<Trash2 className="w-4 h-4" />}
          >
            Hapus Semua
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default LocationActions;

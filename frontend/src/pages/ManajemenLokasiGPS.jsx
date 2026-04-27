import React from 'react';
import {
  Container,
  Box,
  Fade,
  Skeleton,
  Grid,
  useTheme,
  useMediaQuery,
  Fab
} from '@mui/material';
import { Plus } from 'lucide-react';

// Import komponen yang sudah dibuat
import LocationHeader from '../components/lokasi-gps/LocationHeader';
import LocationStats from '../components/lokasi-gps/LocationStats';
import LocationControls from '../components/lokasi-gps/LocationControls';
import LocationTable from '../components/lokasi-gps/LocationTable';
import LocationGrid from '../components/lokasi-gps/LocationGrid';
import LocationFormModal from '../components/lokasi-gps/LocationFormModal';
import useLocationManagement from '../hooks/useLocationManagement';

const ManajemenLokasiGPS = () => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  
  const {
    // State
    loading,
    searchTerm,
    openDialog,
    selectedLocation,
    viewMode,
    filterStatus,
    stats,
    filteredLocations,
    
    // Actions
    setSearchTerm,
    setOpenDialog,
    setViewMode,
    setFilterStatus,
    handleSubmit,
    handleDelete,
    handleAdd,
    handleEdit
  } = useLocationManagement();

  return (
    <Container maxWidth="xl" className="py-6 space-y-6">
      {/* Header */}
      <LocationHeader />

      {/* Statistics */}
      <LocationStats stats={stats} />

      {/* Controls */}
      <LocationControls
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
        filterStatus={filterStatus}
        onFilterChange={setFilterStatus}
        viewMode={viewMode}
        onViewModeChange={setViewMode}
        onAddClick={handleAdd}
      />

      {/* Content */}
      <Fade in timeout={1100}>
        <Box>
          {loading ? (
            <Grid container spacing={3}>
              {[...Array(6)].map((_, index) => (
                <Grid item xs={12} sm={6} md={4} key={index}>
                  <Skeleton variant="rectangular" height={200} className="rounded-lg" />
                </Grid>
              ))}
            </Grid>
          ) : viewMode === 'cards' ? (
            <LocationGrid
              locations={filteredLocations}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          ) : (
            <LocationTable
              locations={filteredLocations}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          )}
        </Box>
      </Fade>

      {/* Form Modal */}
      <LocationFormModal
        open={openDialog}
        onClose={() => setOpenDialog(false)}
        onSubmit={handleSubmit}
        initialData={selectedLocation}
      />

      {/* Floating Action Button for Mobile */}
      {isMobile && (
        <Fab
          color="primary"
          onClick={handleAdd}
          sx={{
            position: 'fixed',
            bottom: 16,
            right: 16,
            background: 'linear-gradient(45deg, #2196F3 30%, #9C27B0 90%)',
          }}
        >
          <Plus size={24} />
        </Fab>
      )}
    </Container>
  );
};

export default ManajemenLokasiGPS;

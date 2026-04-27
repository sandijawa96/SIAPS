import React from 'react';
import {
  Grid,
  Card,
  CardContent,
  Box,
  Typography,
  Avatar,
  IconButton,
  Chip,
  Tooltip
} from '@mui/material';
import { MapPin, Edit, Trash2, Target, Scan } from 'lucide-react';

const LocationCard = ({ location, onEdit, onDelete }) => (
  <Card className="hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
    <CardContent className="p-6">
      <Box className="flex items-start justify-between mb-4">
        <Avatar 
          sx={{ 
            bgcolor: location.is_active ? 'success.main' : 'error.main',
            width: 48,
            height: 48
          }}
        >
          <MapPin size={24} />
        </Avatar>
        <Chip
          label={location.is_active ? 'Aktif' : 'Tidak Aktif'}
          color={location.is_active ? 'success' : 'error'}
          size="small"
          className="font-medium"
        />
      </Box>
      
      <Typography variant="h6" className="font-bold mb-2">
        {location.nama_lokasi}
      </Typography>
      
      <Typography variant="body2" color="text.secondary" className="mb-4 line-clamp-2">
        {location.deskripsi || 'Tidak ada deskripsi'}
      </Typography>
      
      <Box className="space-y-2 mb-4">
        <Box className="flex items-center justify-between">
          <Typography variant="body2" color="text.secondary">
            Tipe:
          </Typography>
          <Chip
            label={location.geofence_type === 'polygon' ? 'Polygon' : 'Circle'}
            size="small"
            variant="outlined"
            icon={<Scan size={14} />}
            color={location.geofence_type === 'polygon' ? 'success' : 'primary'}
          />
        </Box>

        <Box className="flex items-center justify-between">
          <Typography variant="body2" color="text.secondary">
            Koordinat:
          </Typography>
          <Typography variant="body2" className="font-mono">
            {location.latitude}, {location.longitude}
          </Typography>
        </Box>
        
        <Box className="flex items-center justify-between">
          <Typography variant="body2" color="text.secondary">
            Area:
          </Typography>
          <Chip 
            label={location.geofence_type === 'polygon' ? 'Batas polygon' : `${location.radius}m`} 
            size="small" 
            variant="outlined"
            icon={<Target size={14} />}
          />
        </Box>
      </Box>
      
      <Box className="flex justify-end space-x-1">
        <Tooltip title="Edit">
          <IconButton
            size="small"
            onClick={() => onEdit(location)}
            className="text-blue-600 hover:bg-blue-50"
          >
            <Edit size={16} />
          </IconButton>
        </Tooltip>
        <Tooltip title="Hapus">
          <IconButton
            size="small"
            onClick={() => onDelete(location.id)}
            className="text-red-600 hover:bg-red-50"
          >
            <Trash2 size={16} />
          </IconButton>
        </Tooltip>
      </Box>
    </CardContent>
  </Card>
);

const LocationGrid = ({ locations, onEdit, onDelete }) => (
  <Grid container spacing={3}>
    {locations.map((location) => (
      <Grid item xs={12} sm={6} md={4} key={location.id}>
        <LocationCard 
          location={location}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      </Grid>
    ))}
    
    {locations.length === 0 && (
      <Grid item xs={12}>
        <Card className="text-center py-12">
          <CardContent>
            <MapPin size={48} className="mx-auto text-gray-400 mb-3" />
            <Typography variant="body1" color="text.secondary">
              Belum ada lokasi
            </Typography>
          </CardContent>
        </Card>
      </Grid>
    )}
  </Grid>
);

export default LocationGrid;

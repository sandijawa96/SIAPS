import React from 'react';
import {
  Card,
  CardContent,
  Typography,
  Box,
  IconButton,
  Tooltip,
  Chip,
  Switch,
  Checkbox
} from '@mui/material';
import {
  MapPin,
  Edit,
  Trash2,
  Clock,
  Users,
  Calendar
} from 'lucide-react';

const LocationCard = ({
  location,
  onEdit,
  onDelete,
  onToggleStatus,
  selected = false,
  onToggleSelect
}) => {
  const {
    id,
    nama_lokasi,
    deskripsi,
    latitude,
    longitude,
    radius,
    is_active,
    roles,
    waktu_mulai,
    waktu_selesai,
    hari_aktif
  } = location;

  // Parse JSON strings if needed with error handling
  const parsedRoles = (() => {
    try {
      if (typeof roles === 'string') {
        return JSON.parse(roles);
      }
      return Array.isArray(roles) ? roles : [];
    } catch (error) {
      console.warn('Error parsing roles:', error);
      return [];
    }
  })();

  const parsedHari = (() => {
    try {
      if (typeof hari_aktif === 'string') {
        return JSON.parse(hari_aktif);
      }
      return Array.isArray(hari_aktif) ? hari_aktif : [];
    } catch (error) {
      console.warn('Error parsing hari_aktif:', error);
      return [];
    }
  })();

  // Format hari aktif untuk display
  const formatHariAktif = (hari) => {
    if (!Array.isArray(hari) || hari.length === 0) {
      return 'Tidak ada hari aktif';
    }
    
    const namaHari = {
      senin: 'Sen',
      selasa: 'Sel',
      rabu: 'Rab',
      kamis: 'Kam',
      jumat: 'Jum',
      sabtu: 'Sab',
      minggu: 'Min'
    };
    return hari.map(h => namaHari[h] || h).join(', ');
  };

  return (
    <Card 
      className={`hover:shadow-lg transition-all duration-200 ${
        selected ? 'ring-2 ring-blue-500 shadow-lg' : ''
      }`}
      sx={{
        position: 'relative',
        '&:hover .location-actions': {
          opacity: 1
        }
      }}
    >
      <CardContent>
        {/* Selection Checkbox */}
        {onToggleSelect && (
          <Box className="absolute top-2 left-2 z-10">
            <Checkbox
              checked={selected}
              onChange={onToggleSelect}
              size="small"
              className="bg-white rounded shadow-sm"
            />
          </Box>
        )}

        {/* Header with Status Toggle */}
        <Box className="flex justify-between items-start mb-4">
          <Box className="flex items-center space-x-2">
            <Box 
              className={`p-2 rounded-lg ${
                is_active ? 'bg-green-100' : 'bg-gray-100'
              }`}
            >
              <MapPin 
                className={`w-5 h-5 ${
                  is_active ? 'text-green-600' : 'text-gray-600'
                }`}
              />
            </Box>
            <Box>
              <Typography variant="h6" className="font-medium">
                {nama_lokasi}
              </Typography>
              <Typography variant="body2" color="textSecondary">
                {deskripsi || 'Tidak ada deskripsi'}
              </Typography>
            </Box>
          </Box>
          <Switch
            checked={is_active}
            onChange={() => onToggleStatus(id, is_active)}
            color="success"
          />
        </Box>

        {/* Location Details */}
        <Box className="space-y-3">
          {/* Coordinates */}
          <Box className="flex items-center space-x-2 text-gray-600">
            <Typography variant="body2" className="min-w-[90px]">
              Koordinat:
            </Typography>
            <Typography variant="body2">
              {latitude}, {longitude}
            </Typography>
          </Box>

          {/* Radius */}
          <Box className="flex items-center space-x-2 text-gray-600">
            <Typography variant="body2" className="min-w-[90px]">
              Radius:
            </Typography>
            <Typography variant="body2">
              {radius} meter
            </Typography>
          </Box>

          {/* Active Hours */}
          <Box className="flex items-center space-x-2 text-gray-600">
            <Clock className="w-4 h-4" />
            <Typography variant="body2">
              {waktu_mulai} - {waktu_selesai}
            </Typography>
          </Box>

          {/* Active Days */}
          <Box className="flex items-center space-x-2 text-gray-600">
            <Calendar className="w-4 h-4" />
            <Typography variant="body2">
              {formatHariAktif(parsedHari)}
            </Typography>
          </Box>

          {/* Roles */}
          <Box className="flex items-center space-x-2">
            <Users className="w-4 h-4 text-gray-600" />
            <Box className="flex flex-wrap gap-1">
              {parsedRoles.length > 0 ? (
                parsedRoles.map((role) => (
                  <Chip
                    key={role}
                    label={role}
                    size="small"
                    className="bg-blue-50 text-blue-600"
                  />
                ))
              ) : (
                <Typography variant="body2" color="textSecondary">
                  Tidak ada role
                </Typography>
              )}
            </Box>
          </Box>
        </Box>

        {/* Action Buttons */}
        <Box 
          className="location-actions opacity-0 transition-opacity duration-200 absolute top-2 right-2 bg-white rounded-lg shadow-lg p-1 flex space-x-1"
        >
          <Tooltip title="Edit Lokasi">
            <IconButton 
              size="small" 
              onClick={() => onEdit(location)}
              className="text-blue-600 hover:text-blue-800"
            >
              <Edit className="w-4 h-4" />
            </IconButton>
          </Tooltip>
          <Tooltip title="Hapus Lokasi">
            <IconButton 
              size="small" 
              onClick={() => onDelete(id)}
              className="text-red-600 hover:text-red-800"
            >
              <Trash2 className="w-4 h-4" />
            </IconButton>
          </Tooltip>
        </Box>
      </CardContent>
    </Card>
  );
};

export default LocationCard;

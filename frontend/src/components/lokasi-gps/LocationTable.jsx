import React from 'react';
import {
  Card,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Box,
  Typography,
  Avatar,
  IconButton,
  Chip,
  Tooltip
} from '@mui/material';
import { MapPin, Edit, Trash2, Target, Scan } from 'lucide-react';

const LocationTable = ({ locations, onEdit, onDelete }) => (
  <Card className="overflow-hidden">
    <TableContainer>
      <Table>
        <TableHead className="bg-gradient-to-r from-gray-50 to-gray-100">
          <TableRow>
            <TableCell className="font-semibold">Lokasi</TableCell>
            <TableCell className="font-semibold">Koordinat</TableCell>
            <TableCell className="font-semibold">Tipe Area</TableCell>
            <TableCell className="font-semibold">Area</TableCell>
            <TableCell className="font-semibold">Status</TableCell>
            <TableCell align="center" className="font-semibold">Aksi</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {locations.map((location) => (
            <TableRow 
              key={location.id} 
              hover 
              className="transition-colors duration-200"
              sx={{ 
                '&:hover': { 
                  backgroundColor: 'rgba(59, 130, 246, 0.04)' 
                } 
              }}
            >
              <TableCell>
                <Box className="flex items-center space-x-3">
                  <Avatar 
                    sx={{ 
                      bgcolor: location.is_active ? 'success.main' : 'error.main',
                      width: 40,
                      height: 40
                    }}
                  >
                    <MapPin size={20} />
                  </Avatar>
                  <Box>
                    <Typography variant="subtitle2" className="font-medium">
                      {location.nama_lokasi}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {location.deskripsi}
                    </Typography>
                  </Box>
                </Box>
              </TableCell>
              <TableCell>
                <Typography variant="body2" className="font-mono">
                  {location.latitude}, {location.longitude}
                </Typography>
              </TableCell>
              <TableCell>
                <Chip
                  label={location.geofence_type === 'polygon' ? 'Polygon' : 'Circle'}
                  size="small"
                  variant="outlined"
                  icon={<Scan size={16} />}
                  color={location.geofence_type === 'polygon' ? 'success' : 'primary'}
                />
              </TableCell>
              <TableCell>
                <Chip 
                  label={location.geofence_type === 'polygon' ? 'Batas polygon' : `${location.radius}m`} 
                  size="small" 
                  variant="outlined"
                  icon={<Target size={16} />}
                />
              </TableCell>
              <TableCell>
                <Chip
                  label={location.is_active ? 'Aktif' : 'Tidak Aktif'}
                  color={location.is_active ? 'success' : 'error'}
                  size="small"
                  className="font-medium"
                />
              </TableCell>
              <TableCell align="center">
                <Box className="flex justify-center space-x-1">
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
              </TableCell>
            </TableRow>
          ))}
          {locations.length === 0 && (
            <TableRow>
              <TableCell colSpan={6} align="center" className="py-12">
                <MapPin size={48} className="mx-auto text-gray-400 mb-3" />
                <Typography variant="body1" color="text.secondary">
                  Belum ada lokasi
                </Typography>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </TableContainer>
  </Card>
);

export default LocationTable;

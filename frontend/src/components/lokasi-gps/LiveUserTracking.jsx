import React, { useState, useEffect, useRef } from 'react';
import {
  Card,
  CardContent,
  Typography,
  Box,
  Chip,
  Avatar,
  List,
  ListItem,
  ListItemAvatar,
  ListItemText,
  Switch,
  FormControlLabel,
  Alert,
  CircularProgress,
  Tooltip,
  IconButton,
  Badge
} from '@mui/material';
import {
  MapPin,
  Users,
  Navigation,
  RefreshCw,
  Eye,
  EyeOff,
  Wifi,
  WifiOff,
  Clock
} from 'lucide-react';
import { gpsTrackingService } from '../../services/gpsTrackingService';
import { formatServerTime } from '../../services/serverClock';
import MapComponent from './MapComponent';

const LiveUserTracking = () => {
  const [activeUsers, setActiveUsers] = useState([]);
  const [gpsLocations, setGpsLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [showMap, setShowMap] = useState(true);
  const [stats, setStats] = useState({
    total_active_users: 0,
    users_in_gps_area: 0,
    users_outside_gps_area: 0,
    last_updated: null
  });

  const refreshIntervalRef = useRef(null);

  // Fetch data pengguna aktif
  const fetchActiveUsers = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await gpsTrackingService.getActiveUsersLocations();
      
      if (response.success) {
        setActiveUsers(response.data.active_users || []);
        setGpsLocations(response.data.gps_locations || []);
        setStats({
          total_active_users: response.data.total_active_users || 0,
          users_in_gps_area: response.data.users_in_gps_area || 0,
          users_outside_gps_area: response.data.total_active_users - response.data.users_in_gps_area || 0,
          last_updated: response.data.last_updated
        });
      }
    } catch (error) {
      console.error('Error fetching active users:', error);
      setError('Gagal memuat data pengguna aktif. Silakan coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  // Setup auto refresh
  useEffect(() => {
    fetchActiveUsers();

    if (autoRefresh) {
      refreshIntervalRef.current = setInterval(() => {
        fetchActiveUsers();
      }, 30000); // Refresh setiap 30 detik
    }

    return () => {
      if (refreshIntervalRef.current) {
        clearInterval(refreshIntervalRef.current);
      }
    };
  }, [autoRefresh]);

  // Handle manual refresh
  const handleRefresh = () => {
    fetchActiveUsers();
  };

  // Handle auto refresh toggle
  const handleAutoRefreshToggle = (event) => {
    setAutoRefresh(event.target.checked);
  };

  // Get user status color
  const getUserStatusColor = (user) => {
    if (user.within_gps_area) {
      return 'success';
    }
    return 'warning';
  };

  // Get user status text
  const getUserStatusText = (user) => {
    if (user.within_gps_area) {
      return `Di ${user.current_location?.nama_lokasi}`;
    }
    if (user.nearest_location) {
      return `${user.distance_to_nearest}m dari ${user.nearest_location.nama_lokasi}`;
    }
    return 'Lokasi tidak diketahui';
  };

  // Format timestamp
  const formatTimestamp = (timestamp) => {
    if (!timestamp) return '-';
    return formatServerTime(timestamp, 'id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }) || '-';
  };

  // Prepare markers for map
  const mapMarkers = [
    // GPS locations
    ...gpsLocations.map(location => ({
      latitude: parseFloat(location.latitude),
      longitude: parseFloat(location.longitude),
      nama_lokasi: location.nama_lokasi,
      radius: location.radius,
      geofence_type: location.geofence_type || 'circle',
      geofence_geojson: location.geofence_geojson || null,
      warna_marker: location.warna_marker || '#2196F3',
      type: 'gps_location',
      is_active: true
    })),
    // Active users
    ...activeUsers.map(user => ({
      latitude: parseFloat(user.latitude),
      longitude: parseFloat(user.longitude),
      nama_lokasi: user.user_name,
      type: 'active_user',
      within_gps_area: user.within_gps_area,
      accuracy: user.accuracy,
      timestamp: user.timestamp
    }))
  ];

  return (
    <Box className="space-y-6">
      {/* Header */}
      <Box className="flex justify-between items-center">
        <Box>
          <Typography variant="h6" className="font-bold text-gray-900">
            Tracking Pengguna Aktif
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Monitor lokasi pengguna secara real-time
          </Typography>
        </Box>
        
        <Box className="flex items-center space-x-3">
          <FormControlLabel
            control={
              <Switch
                checked={autoRefresh}
                onChange={handleAutoRefreshToggle}
                size="small"
              />
            }
            label={
              <Typography variant="body2" className="flex items-center">
                {autoRefresh ? <Wifi className="w-4 h-4 mr-1" /> : <WifiOff className="w-4 h-4 mr-1" />}
                Auto Refresh
              </Typography>
            }
          />
          
          <Tooltip title={showMap ? "Sembunyikan Peta" : "Tampilkan Peta"}>
            <IconButton onClick={() => setShowMap(!showMap)} size="small">
              {showMap ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
            </IconButton>
          </Tooltip>
          
          <Tooltip title="Refresh Manual">
            <IconButton onClick={handleRefresh} disabled={loading} size="small">
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>

      {/* Error Alert */}
      {error && (
        <Alert severity="error" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Statistics Cards */}
      <Box className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <Box className="flex items-center">
              <Box className="p-2 rounded-full bg-blue-100">
                <Users className="w-5 h-5 text-blue-600" />
              </Box>
              <Box className="ml-3">
                <Typography variant="body2" color="text.secondary">
                  Total Aktif
                </Typography>
                <Typography variant="h6" className="font-bold">
                  {stats.total_active_users}
                </Typography>
              </Box>
            </Box>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <Box className="flex items-center">
              <Box className="p-2 rounded-full bg-green-100">
                <MapPin className="w-5 h-5 text-green-600" />
              </Box>
              <Box className="ml-3">
                <Typography variant="body2" color="text.secondary">
                  Dalam Area GPS
                </Typography>
                <Typography variant="h6" className="font-bold">
                  {stats.users_in_gps_area}
                </Typography>
              </Box>
            </Box>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <Box className="flex items-center">
              <Box className="p-2 rounded-full bg-orange-100">
                <Navigation className="w-5 h-5 text-orange-600" />
              </Box>
              <Box className="ml-3">
                <Typography variant="body2" color="text.secondary">
                  Luar Area GPS
                </Typography>
                <Typography variant="h6" className="font-bold">
                  {stats.users_outside_gps_area}
                </Typography>
              </Box>
            </Box>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <Box className="flex items-center">
              <Box className="p-2 rounded-full bg-purple-100">
                <Clock className="w-5 h-5 text-purple-600" />
              </Box>
              <Box className="ml-3">
                <Typography variant="body2" color="text.secondary">
                  Update Terakhir
                </Typography>
                <Typography variant="body2" className="font-medium">
                  {formatTimestamp(stats.last_updated)}
                </Typography>
              </Box>
            </Box>
          </CardContent>
        </Card>
      </Box>

      {/* Map and User List */}
      <Box className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Map */}
        {showMap && (
          <Box className="lg:col-span-2">
            <Card>
              <CardContent className="p-4">
                <Typography variant="h6" className="mb-4">
                  Peta Lokasi Real-time
                </Typography>
                {loading ? (
                  <Box className="flex justify-center items-center h-96">
                    <CircularProgress />
                  </Box>
                ) : (
                  <Box className="h-96">
                    <MapComponent
                      markers={mapMarkers}
                      showLiveTracking={true}
                      height={384}
                    />
                  </Box>
                )}
              </CardContent>
            </Card>
          </Box>
        )}

        {/* User List */}
        <Box className={showMap ? "lg:col-span-1" : "lg:col-span-3"}>
          <Card>
            <CardContent className="p-4">
              <Typography variant="h6" className="mb-4">
                Pengguna Aktif ({activeUsers.length})
              </Typography>
              
              {loading ? (
                <Box className="flex justify-center py-8">
                  <CircularProgress size={24} />
                </Box>
              ) : activeUsers.length === 0 ? (
                <Box className="text-center py-8">
                  <Typography variant="body2" color="text.secondary">
                    Tidak ada pengguna aktif saat ini
                  </Typography>
                </Box>
              ) : (
                <List className="max-h-96 overflow-y-auto">
                  {activeUsers.map((user, index) => (
                    <ListItem key={user.user_id || index} className="px-0">
                      <ListItemAvatar>
                        <Badge
                          badgeContent={
                            <Box
                              className={`w-3 h-3 rounded-full ${
                                user.within_gps_area ? 'bg-green-500' : 'bg-orange-500'
                              }`}
                            />
                          }
                          overlap="circular"
                          anchorOrigin={{
                            vertical: 'bottom',
                            horizontal: 'right',
                          }}
                        >
                          <Avatar className="bg-blue-500">
                            {user.user_name?.charAt(0)?.toUpperCase() || 'U'}
                          </Avatar>
                        </Badge>
                      </ListItemAvatar>
                      
                      <ListItemText
                        primary={
                          <Typography variant="subtitle2" className="font-medium">
                            {user.user_name}
                          </Typography>
                        }
                        secondary={
                          <Box className="space-y-1">
                            <Chip
                              label={getUserStatusText(user)}
                              color={getUserStatusColor(user)}
                              size="small"
                              className="text-xs"
                            />
                            <Typography variant="caption" color="text.secondary" className="block">
                              {formatTimestamp(user.timestamp)}
                              {user.accuracy && ` • ±${Math.round(user.accuracy)}m`}
                            </Typography>
                          </Box>
                        }
                      />
                    </ListItem>
                  ))}
                </List>
              )}
            </CardContent>
          </Card>
        </Box>
      </Box>
    </Box>
  );
};

export default LiveUserTracking;

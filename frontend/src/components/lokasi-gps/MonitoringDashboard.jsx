import React from 'react';
import { 
  Box, 
  Dialog,
  DialogTitle,
  DialogContent,
  Grid,
  Card,
  CardContent,
  Typography,
  IconButton,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Divider
} from '@mui/material';
import { 
  MapPin, 
  Users, 
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Clock,
  X as CloseIcon
} from 'lucide-react';
import MapComponent from './MapComponent';

const MonitoringDashboard = ({ 
  open, 
  onClose, 
  statistics = {
    total: 0,
    aktif: 0,
    users_today: 0,
    errors: 0,
    recent_activities: [],
    error_logs: []
  }, 
  activeLocations = []
}) => {
  return (
    <Dialog 
      open={open} 
      onClose={onClose}
      maxWidth="xl"
      fullWidth
      PaperProps={{
        sx: {
          maxHeight: '90vh',
          height: '90vh'
        }
      }}
    >
      <DialogTitle className="flex justify-between items-center">
        <Typography variant="h6" component="h2">
          Monitoring Lokasi GPS
        </Typography>
        <IconButton onClick={onClose} size="small">
          <CloseIcon className="w-5 h-5" />
        </IconButton>
      </DialogTitle>

      <DialogContent>
        <Grid container spacing={3}>
          {/* Statistics Cards */}
          <Grid item xs={12} lg={3}>
            <Box className="space-y-4">
              {/* Total Locations */}
              <Card>
                <CardContent className="flex items-center space-x-4">
                  <Box className="p-3 bg-blue-100 rounded-full">
                    <MapPin className="w-6 h-6 text-blue-600" />
                  </Box>
                  <Box>
                    <Typography variant="h4" component="div">
                      {statistics.total}
                    </Typography>
                    <Typography color="textSecondary" variant="body2">
                      Total Lokasi
                    </Typography>
                  </Box>
                </CardContent>
              </Card>

              {/* Active Locations */}
              <Card>
                <CardContent className="flex items-center space-x-4">
                  <Box className="p-3 bg-green-100 rounded-full">
                    <CheckCircle2 className="w-6 h-6 text-green-600" />
                  </Box>
                  <Box>
                    <Typography variant="h4" component="div">
                      {statistics.aktif}
                    </Typography>
                    <Typography color="textSecondary" variant="body2">
                      Lokasi Aktif
                    </Typography>
                  </Box>
                </CardContent>
              </Card>

              {/* Users Today */}
              <Card>
                <CardContent className="flex items-center space-x-4">
                  <Box className="p-3 bg-purple-100 rounded-full">
                    <Users className="w-6 h-6 text-purple-600" />
                  </Box>
                  <Box>
                    <Typography variant="h4" component="div">
                      {statistics.users_today}
                    </Typography>
                    <Typography color="textSecondary" variant="body2">
                      Pengguna Hari Ini
                    </Typography>
                  </Box>
                </CardContent>
              </Card>

              {/* Error Count */}
              <Card>
                <CardContent className="flex items-center space-x-4">
                  <Box className="p-3 bg-red-100 rounded-full">
                    <AlertTriangle className="w-6 h-6 text-red-600" />
                  </Box>
                  <Box>
                    <Typography variant="h4" component="div">
                      {statistics.errors}
                    </Typography>
                    <Typography color="textSecondary" variant="body2">
                      Error Terdeteksi
                    </Typography>
                  </Box>
                </CardContent>
              </Card>

              {/* Recent Activities */}
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Aktivitas Terbaru
                  </Typography>
                  <List>
                    {(statistics.recent_activities || []).map((activity, index) => (
                      <React.Fragment key={index}>
                        <ListItem disablePadding>
                          <ListItemIcon>
                            {activity.type === 'success' ? (
                              <CheckCircle2 className="w-5 h-5 text-green-600" />
                            ) : (
                              <XCircle className="w-5 h-5 text-red-600" />
                            )}
                          </ListItemIcon>
                          <ListItemText
                            primary={activity.message}
                            secondary={
                              <Box className="flex items-center text-xs text-gray-500">
                                <Clock className="w-4 h-4 mr-1" />
                                {activity.timestamp}
                              </Box>
                            }
                          />
                        </ListItem>
                        {index < statistics.recent_activities.length - 1 && <Divider />}
                      </React.Fragment>
                    ))}
                  </List>
                </CardContent>
              </Card>
            </Box>
          </Grid>

          {/* Map */}
          <Grid item xs={12} lg={9}>
            <Card sx={{ height: '100%' }}>
              <CardContent sx={{ height: '100%', p: 0 }}>
                <MapComponent
                  markers={(activeLocations || []).map(loc => ({
                    latitude: loc.latitude,
                    longitude: loc.longitude,
                    nama_lokasi: loc.nama_lokasi,
                    deskripsi: loc.deskripsi,
                    radius: loc.radius,
                    geofence_type: loc.geofence_type || 'circle',
                    geofence_geojson: loc.geofence_geojson || null,
                    is_active: loc.is_active
                  }))}
                  readOnly={true}
                  showLiveTracking={true}
                  height="100%"
                  className="rounded-lg"
                />
              </CardContent>
            </Card>
          </Grid>

          {/* Error Logs */}
          {(statistics.error_logs || []).length > 0 && (
            <Grid item xs={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" gutterBottom className="flex items-center">
                    <AlertTriangle className="w-5 h-5 mr-2 text-red-600" />
                    Error Logs
                  </Typography>
                  <List>
                    {(statistics.error_logs || []).map((log, index) => (
                      <React.Fragment key={index}>
                        <ListItem>
                          <ListItemText
                            primary={log.message}
                            secondary={
                              <Box>
                                <Typography component="span" variant="body2">
                                  User: {log.user}
                                </Typography>
                                <br />
                                <Typography component="span" variant="body2">
                                  Location: {log.location}
                                </Typography>
                                <br />
                                <Typography component="span" variant="body2" className="flex items-center">
                                  <Clock className="w-4 h-4 mr-1" />
                                  {log.timestamp}
                                </Typography>
                              </Box>
                            }
                          />
                        </ListItem>
                        {index < statistics.error_logs.length - 1 && <Divider />}
                      </React.Fragment>
                    ))}
                  </List>
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      </DialogContent>
    </Dialog>
  );
};

export default MonitoringDashboard;

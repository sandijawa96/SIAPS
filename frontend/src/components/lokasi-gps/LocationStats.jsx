import React from 'react';
import { Grid, Card, CardContent, Box, Typography, Avatar, Slide } from '@mui/material';
import { MapPin, Target, Clock, Ruler, Users } from 'lucide-react';

const StatCard = ({ title, value, icon: Icon, color, caption }) => (
  <Card className="hover:shadow-lg transition-shadow duration-300">
    <CardContent>
      <Box className="flex items-center justify-between">
        <Box>
          <Typography variant="body2" color="text.secondary" className="mb-1">
            {title}
          </Typography>
          <Typography variant="h4" className="font-bold">
            {value}
          </Typography>
          {caption && (
            <Typography variant="body2" color="text.secondary" className="mt-1">
              {caption}
            </Typography>
          )}
        </Box>
        <Avatar 
          sx={{ 
            bgcolor: color, 
            width: 56, 
            height: 56,
            background: `linear-gradient(135deg, ${color} 0%, ${color}dd 100%)`
          }}
        >
          <Icon size={28} />
        </Avatar>
      </Box>
    </CardContent>
  </Card>
);

const LocationStats = ({ stats = {} }) => {
  // Provide default values to prevent undefined errors
  const safeStats = {
    total: 0,
    active: 0,
    inactive: 0,
    averageRadius: 0,
    minRadius: 0,
    maxRadius: 0,
    ...stats
  };

  const liveTracking = safeStats.liveTracking || {};
  const hasLiveTracking = Boolean(liveTracking.available);
  const totalActiveUsers = Number.isFinite(Number(liveTracking.totalActiveUsers))
    ? Number(liveTracking.totalActiveUsers)
    : 0;
  const usersInGpsArea = Number.isFinite(Number(liveTracking.usersInGpsArea))
    ? Number(liveTracking.usersInGpsArea)
    : 0;
  const usersOutsideGpsArea = Number.isFinite(Number(liveTracking.usersOutsideGpsArea))
    ? Number(liveTracking.usersOutsideGpsArea)
    : 0;
  const liveTrackingCaption = `Di area ${usersInGpsArea} | Di luar ${usersOutsideGpsArea}`;

  return (
    <Slide in timeout={700} direction="up">
      <Grid container spacing={3}>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Total Lokasi"
            value={safeStats.total}
            icon={MapPin}
            color="#2196F3"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Lokasi Aktif"
            value={safeStats.active}
            icon={Target}
            color="#4CAF50"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <StatCard
            title="Tidak Aktif"
            value={safeStats.inactive}
            icon={Clock}
            color="#FF9800"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          {hasLiveTracking ? (
            <StatCard
              title="Pengguna Aktif"
              value={totalActiveUsers}
              icon={Users}
              color="#9C27B0"
              caption={liveTrackingCaption}
            />
          ) : (
            <StatCard
              title="Rata-rata Radius"
              value={safeStats.averageRadius > 0 ? `${safeStats.averageRadius} m` : '0 m'}
              icon={Ruler}
              color="#03A9F4"
              caption={`Min ${safeStats.minRadius} m | Max ${safeStats.maxRadius} m`}
            />
          )}
        </Grid>
      </Grid>
    </Slide>
  );
};

export default LocationStats;

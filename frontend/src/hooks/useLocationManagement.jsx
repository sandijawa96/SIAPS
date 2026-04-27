import { useState, useEffect, useCallback } from 'react';
import { useSnackbar } from 'notistack';
import { useAuth } from './useAuth';
import { getApiUrl } from '../config/api';

const toNumberOr = (value, fallback = 0) => {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : fallback;
};

const toBoolean = (value) => {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value === 1;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    return normalized === '1' || normalized === 'true';
  }
  return false;
};

const normalizeGeoJson = (value) => {
  if (!value) return null;
  if (typeof value === 'string') {
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }
  return typeof value === 'object' ? value : null;
};

const pointInPolygon = (latitude, longitude, geoJson) => {
  const ring = geoJson?.coordinates?.[0];
  if (!Array.isArray(ring) || ring.length < 4) {
    return false;
  }

  let inside = false;
  let previousIndex = ring.length - 1;
  for (let index = 0; index < ring.length; index += 1) {
    const [x1, y1] = ring[index];
    const [x2, y2] = ring[previousIndex];
    const intersects =
      y1 > latitude !== y2 > latitude &&
      longitude < ((x2 - x1) * (latitude - y1)) / ((y2 - y1) || 1e-10) + x1;

    if (intersects) {
      inside = !inside;
    }

    previousIndex = index;
  }

  return inside;
};

const useLocationManagement = () => {
  const [locations, setLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [liveTrackingStats, setLiveTrackingStats] = useState({
    available: false,
    totalActiveUsers: 0,
    usersInGpsArea: 0,
    usersOutsideGpsArea: 0,
    lastUpdated: null
  });
  
  // UI State
  const [searchTerm, setSearchTerm] = useState('');
  const [openDialog, setOpenDialog] = useState(false);
  const [selectedLocation, setSelectedLocation] = useState(null);
  const [viewMode, setViewMode] = useState('cards');
  const [filterStatus, setFilterStatus] = useState('all');
  
  const { token, hasPermission } = useAuth();
  const canViewLiveTracking = hasPermission('view_live_tracking');
  const { enqueueSnackbar } = useSnackbar();

  // Fetch GPS locations
  const fetchLocations = useCallback(async () => {
    if (!token) return;

    try {
      setLoading(true);
      setError(null);

      const response = await fetch(getApiUrl('/lokasi-gps'), {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to fetch locations`);
      }

      const data = await response.json();
      
      if (data.success && Array.isArray(data.data)) {
        // Transform API data to match component structure
        const transformedLocations = data.data.map(location => ({
          id: location.id,
          nama_lokasi: location.nama_lokasi,
          latitude: toNumberOr(location.latitude, 0),
          longitude: toNumberOr(location.longitude, 0),
          radius: toNumberOr(location.radius, 0),
          geofence_type: location.geofence_type || 'circle',
          geofence_geojson: normalizeGeoJson(location.geofence_geojson),
          warna_marker: location.warna_marker || '#2196F3',
          deskripsi: location.deskripsi || location.keterangan || '',
          is_active: toBoolean(location.is_active),
          created_at: location.created_at,
          updated_at: location.updated_at
        }));

        setLocations(transformedLocations);
      } else {
        setLocations([]);
      }

      if (canViewLiveTracking) {
        try {
          const liveResponse = await fetch(getApiUrl('/lokasi-gps/active-users'), {
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            credentials: 'include'
          });

          if (liveResponse.ok) {
            const livePayload = await liveResponse.json();
            const liveData = livePayload?.data || {};
            const totalActiveUsers = toNumberOr(liveData.total_active_users, 0);
            const usersInGpsArea = toNumberOr(liveData.users_in_gps_area, 0);

            setLiveTrackingStats({
              available: true,
              totalActiveUsers,
              usersInGpsArea,
              usersOutsideGpsArea: Math.max(0, totalActiveUsers - usersInGpsArea),
              lastUpdated: liveData.last_updated || null
            });
          } else {
            setLiveTrackingStats({
              available: false,
              totalActiveUsers: 0,
              usersInGpsArea: 0,
              usersOutsideGpsArea: 0,
              lastUpdated: null
            });
          }
        } catch (liveError) {
          console.warn('Live tracking stats unavailable:', liveError?.message || liveError);
          setLiveTrackingStats({
            available: false,
            totalActiveUsers: 0,
            usersInGpsArea: 0,
            usersOutsideGpsArea: 0,
            lastUpdated: null
          });
        }
      } else {
        setLiveTrackingStats({
          available: false,
          totalActiveUsers: 0,
          usersInGpsArea: 0,
          usersOutsideGpsArea: 0,
          lastUpdated: null
        });
      }
    } catch (err) {
      console.error('Error fetching locations:', err);
      setError(err.message);
      setLocations([]);
      setLiveTrackingStats({
        available: false,
        totalActiveUsers: 0,
        usersInGpsArea: 0,
        usersOutsideGpsArea: 0,
        lastUpdated: null
      });
    } finally {
      setLoading(false);
    }
  }, [canViewLiveTracking, token]);

  // Add new location
  const addLocation = useCallback(async (locationData) => {
    if (!token) return false;

    try {
      const response = await fetch(getApiUrl('/lokasi-gps'), {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(locationData)
      });

      if (!response.ok) {
        throw new Error('Failed to add location');
      }

      const data = await response.json();
      
      if (data.success) {
        await fetchLocations(); // Refresh the list
        enqueueSnackbar('Lokasi GPS berhasil ditambahkan', { variant: 'success' });
        return true;
      } else {
        throw new Error(data.message || 'Failed to add location');
      }
    } catch (err) {
      console.error('Error adding location:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return false;
    }
  }, [token, fetchLocations, enqueueSnackbar]);

  // Update location
  const updateLocation = useCallback(async (id, locationData) => {
    if (!token) return false;

    try {
      const response = await fetch(getApiUrl(`/lokasi-gps/${id}`), {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(locationData)
      });

      if (!response.ok) {
        throw new Error('Failed to update location');
      }

      const data = await response.json();
      
      if (data.success) {
        await fetchLocations(); // Refresh the list
        enqueueSnackbar('Lokasi GPS berhasil diperbarui', { variant: 'success' });
        return true;
      } else {
        throw new Error(data.message || 'Failed to update location');
      }
    } catch (err) {
      console.error('Error updating location:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return false;
    }
  }, [token, fetchLocations, enqueueSnackbar]);

  // Delete location
  const deleteLocation = useCallback(async (id) => {
    if (!token) return false;

    try {
      const response = await fetch(getApiUrl(`/lokasi-gps/${id}`), {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error('Failed to delete location');
      }

      const data = await response.json();
      
      if (data.success) {
        await fetchLocations(); // Refresh the list
        enqueueSnackbar('Lokasi GPS berhasil dihapus', { variant: 'success' });
        return true;
      } else {
        throw new Error(data.message || 'Failed to delete location');
      }
    } catch (err) {
      console.error('Error deleting location:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return false;
    }
  }, [token, fetchLocations, enqueueSnackbar]);

  // Toggle location active status
  const toggleLocationStatus = useCallback(async (id, isActive) => {
    if (!token) return false;

    try {
      const response = await fetch(getApiUrl(`/lokasi-gps/${id}/toggle`), {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error('Failed to toggle location status');
      }

      const data = await response.json();
      
      if (data.success) {
        await fetchLocations(); // Refresh the list
        const nextIsActive = !isActive;
        enqueueSnackbar(
          `Lokasi GPS berhasil ${nextIsActive ? 'diaktifkan' : 'dinonaktifkan'}`, 
          { variant: 'success' }
        );
        return true;
      } else {
        throw new Error(data.message || 'Failed to toggle location status');
      }
    } catch (err) {
      console.error('Error toggling location status:', err);
      enqueueSnackbar(err.message, { variant: 'error' });
      return false;
    }
  }, [token, fetchLocations, enqueueSnackbar]);

  // Get active locations only
  const getActiveLocations = useCallback(() => {
    return locations.filter(location => location.is_active);
  }, [locations]);

  // Get location by ID
  const getLocationById = useCallback((id) => {
    return locations.find(location => location.id === id);
  }, [locations]);

  // Check if point is within any active location
  const isPointInActiveLocation = useCallback((latitude, longitude) => {
    const activeLocations = getActiveLocations();
    
    for (const location of activeLocations) {
      const isPolygon = location.geofence_type === 'polygon' && location.geofence_geojson;
      const isInside = isPolygon
        ? pointInPolygon(latitude, longitude, location.geofence_geojson)
        : calculateDistance(
            latitude,
            longitude,
            location.latitude,
            location.longitude
          ) <= location.radius;

      if (isInside) {
        return {
          isInside: true,
          location: location,
          distance: isPolygon
            ? 0
            : calculateDistance(
                latitude,
                longitude,
                location.latitude,
                location.longitude
              )
        };
      }
    }
    
    return {
      isInside: false,
      location: null,
      distance: null
    };
  }, [getActiveLocations]);

  // Calculate distance between two points (Haversine formula)
  const calculateDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371e3; // Earth's radius in meters
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lon2 - lon1) * Math.PI / 180;

    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c; // Distance in meters
  };

  // Handler functions for UI
  const handleAdd = useCallback(() => {
    setSelectedLocation(null);
    setOpenDialog(true);
  }, []);

  const handleEdit = useCallback((location) => {
    setSelectedLocation(location);
    setOpenDialog(true);
  }, []);

  const handleSubmit = useCallback(async (formData) => {
    let success = false;
    
    if (selectedLocation) {
      // Update existing location
      success = await updateLocation(selectedLocation.id, formData);
    } else {
      // Add new location
      success = await addLocation(formData);
    }
    
    if (success) {
      setOpenDialog(false);
      setSelectedLocation(null);
    }
  }, [selectedLocation, updateLocation, addLocation]);

  const handleDelete = useCallback(async (id) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus lokasi ini?')) {
      await deleteLocation(id);
    }
  }, [deleteLocation]);

  // Computed values
  const filteredLocations = locations.filter(location => {
    const matchesSearch = location.nama_lokasi.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesFilter = filterStatus === 'all' || 
      (filterStatus === 'active' && location.is_active) ||
      (filterStatus === 'inactive' && !location.is_active);
    
    return matchesSearch && matchesFilter;
  });

  const radiusValues = locations
    .filter((location) => location.geofence_type !== 'polygon')
    .map((location) => toNumberOr(location.radius, 0))
    .filter((radius) => radius > 0);

  const total = locations.length;
  const active = locations.filter((location) => location.is_active).length;
  const inactive = total - active;
  const averageRadius =
    radiusValues.length > 0
      ? Math.round(radiusValues.reduce((sum, radius) => sum + radius, 0) / radiusValues.length)
      : 0;
  const minRadius = radiusValues.length > 0 ? Math.min(...radiusValues) : 0;
  const maxRadius = radiusValues.length > 0 ? Math.max(...radiusValues) : 0;

  const stats = {
    total,
    active,
    inactive,
    averageRadius,
    minRadius,
    maxRadius,
    liveTracking: liveTrackingStats,
  };

  // Initial fetch
  useEffect(() => {
    fetchLocations();
  }, [fetchLocations]);

  return {
    // Data
    locations,
    loading,
    error,
    filteredLocations,
    stats,
    
    // UI State
    searchTerm,
    openDialog,
    selectedLocation,
    viewMode,
    filterStatus,
    
    // Setters
    setSearchTerm,
    setOpenDialog,
    setViewMode,
    setFilterStatus,
    
    // Handlers
    handleAdd,
    handleEdit,
    handleSubmit,
    handleDelete,
    
    // API functions
    fetchLocations,
    addLocation,
    updateLocation,
    deleteLocation,
    toggleLocationStatus,
    getActiveLocations,
    getLocationById,
    isPointInActiveLocation,
    calculateDistance
  };
};

export default useLocationManagement;

import React, { useEffect, useRef, useState } from 'react';
import { Box, Typography, IconButton, Tooltip, Fab } from '@mui/material';
import { MapPin, Maximize2, Minimize2, RotateCcw } from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix leaflet icon issue
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
});

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

const MapComponent = ({
  center = [-6.2088, 106.8456], // Default to Jakarta
  zoom = 13,
  markers = [],
  readOnly = false,
  onLocationSelect = () => {},
  selectedLocation = null,
  showLiveTracking = false,
  liveTrackingData = [],
  height = 400,
  className = ''
}) => {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const markersLayerRef = useRef(null);
  const circlesLayerRef = useRef(null);
  const liveTrackingLayerRef = useRef(null);
  const selectedMarkerRef = useRef(null);
  
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [mapReady, setMapReady] = useState(false);

  // Initialize map only once
  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) return;

    // Initialize map
    mapInstanceRef.current = L.map(mapRef.current, {
      zoomControl: false,
      minZoom: 4,
      maxZoom: 19
    }).setView(center, zoom);

    // Add tile layer with better caching
    const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19,
      crossOrigin: true
    });

    // Enable tile caching if available
    if ('caches' in window) {
      tileLayer.on('tileload', (event) => {
        const tile = event.tile;
        if (tile.src) {
          caches.open('map-tiles').then(cache => {
            cache.add(tile.src).catch(() => {
              // Ignore cache errors
            });
          });
        }
      });
    }

    tileLayer.addTo(mapInstanceRef.current);

    // Add zoom control to top right
    L.control.zoom({
      position: 'topright'
    }).addTo(mapInstanceRef.current);

    // Create layers
    markersLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);
    circlesLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);
    
    if (showLiveTracking) {
      liveTrackingLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);
    }

    // Add click handler if not readOnly
    if (!readOnly) {
      mapInstanceRef.current.on('click', (e) => {
        const lat = parseFloat(e.latlng.lat.toFixed(6));
        const lng = parseFloat(e.latlng.lng.toFixed(6));
        onLocationSelect({ lat, lng });
      });
    }

    // Enable hardware acceleration
    mapInstanceRef.current.getContainer().style.transform = 'translate3d(0,0,0)';

    setMapReady(true);

    // Cleanup on unmount
    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
      }
    };
  }, []); // Empty dependency array - run once only

  // Update map view when center or zoom changes (optimized)
  useEffect(() => {
    if (mapInstanceRef.current && center && zoom && mapReady) {
      // Only update view if it's significantly different
      const currentCenter = mapInstanceRef.current.getCenter();
      const currentZoom = mapInstanceRef.current.getZoom();
      
      const centerChanged = Math.abs(currentCenter.lat - center[0]) > 0.0001 || 
                           Math.abs(currentCenter.lng - center[1]) > 0.0001;
      const zoomChanged = Math.abs(currentZoom - zoom) > 0.5;
      
      if (centerChanged || zoomChanged) {
        mapInstanceRef.current.setView(center, zoom);
      }
    }
  }, [center, zoom, mapReady]);

  // Update markers when they change
  useEffect(() => {
    if (!mapInstanceRef.current || !markersLayerRef.current) return;

    // Clear existing markers and circles
    markersLayerRef.current.clearLayers();
    circlesLayerRef.current.clearLayers();

    // Add new markers
    markers.forEach(marker => {
      // Create custom marker icon
      const markerIcon = L.divIcon({
        html: `
          <div class="relative">
            <div class="flex items-center justify-center w-10 h-10 bg-white rounded-full shadow-lg border-2 border-${marker.is_active ? 'green' : 'red'}-500">
              <svg 
                xmlns="http://www.w3.org/2000/svg" 
                width="20" 
                height="20" 
                viewBox="0 0 24 24" 
                fill="none" 
                stroke="${marker.is_active ? '#10B981' : '#EF4444'}"
                stroke-width="2" 
                stroke-linecap="round" 
                stroke-linejoin="round"
              >
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
            </div>
            <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-t-4 border-transparent border-t-${marker.is_active ? 'green' : 'red'}-500"></div>
          </div>
        `,
        className: 'custom-marker',
        iconSize: [40, 50],
        iconAnchor: [20, 50],
        popupAnchor: [0, -50]
      });

      // Create marker
      const markerInstance = L.marker([marker.latitude, marker.longitude], {
        icon: markerIcon,
        title: marker.nama_lokasi
      }).addTo(markersLayerRef.current);

      const areaLabel = marker.geofence_type === 'polygon' ? 'Batas polygon' : `${marker.radius}m`;

      markerInstance.bindPopup(`
        <div class="p-3 min-w-[200px]">
          <div class="flex items-center mb-2">
            <div class="w-3 h-3 rounded-full bg-${marker.is_active ? 'green' : 'red'}-500 mr-2"></div>
            <h3 class="font-bold text-gray-900">${marker.nama_lokasi}</h3>
          </div>
          ${marker.deskripsi ? `<p class="text-sm text-gray-600 mb-2">${marker.deskripsi}</p>` : ''}
          <div class="text-xs text-gray-500 space-y-1">
            <div>Koordinat: ${marker.latitude}, ${marker.longitude}</div>
            <div>Tipe Area: ${marker.geofence_type === 'polygon' ? 'Polygon' : 'Circle'}</div>
            <div>Area: ${areaLabel}</div>
            <div>Status: ${marker.is_active ? 'Aktif' : 'Tidak Aktif'}</div>
          </div>
        </div>
      `, {
        maxWidth: 300,
        className: 'custom-popup'
      });

      const normalizedGeoJson = normalizeGeoJson(marker.geofence_geojson);
      const polygonRing = normalizedGeoJson?.coordinates?.[0];

      if (marker.geofence_type === 'polygon' && Array.isArray(polygonRing) && polygonRing.length >= 4) {
        L.polygon(
          polygonRing.map(([lng, lat]) => [lat, lng]),
          {
            fillColor: marker.is_active ? '#10B981' : '#EF4444',
            fillOpacity: 0.12,
            color: marker.is_active ? '#059669' : '#DC2626',
            weight: 2,
            opacity: 0.7
          }
        ).addTo(circlesLayerRef.current);
      } else {
        L.circle([marker.latitude, marker.longitude], {
          radius: marker.radius,
          fillColor: marker.is_active ? '#10B981' : '#EF4444',
          fillOpacity: 0.1,
          color: marker.is_active ? '#059669' : '#DC2626',
          weight: 2,
          opacity: 0.6
        }).addTo(circlesLayerRef.current);
      }
    });

    // Fit bounds if there are markers
    if (markers.length > 0) {
      const bounds = L.latLngBounds(markers.map(m => [m.latitude, m.longitude]));
      mapInstanceRef.current.fitBounds(bounds, { 
        padding: [20, 20],
        maxZoom: 16
      });
    }
  }, [markers]);

  // Update selected location marker
  useEffect(() => {
    if (!mapInstanceRef.current || !selectedLocation) return;

    // Remove previous selected marker
    if (selectedMarkerRef.current) {
      mapInstanceRef.current.removeLayer(selectedMarkerRef.current);
    }

    // Add new selected location marker
    const selectedIcon = L.divIcon({
      html: `
        <div class="relative animate-pulse">
          <div class="flex items-center justify-center w-12 h-12 bg-blue-500 rounded-full shadow-lg border-4 border-white">
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              width="24" 
              height="24" 
              viewBox="0 0 24 24" 
              fill="white"
              stroke="white"
              stroke-width="2" 
              stroke-linecap="round" 
              stroke-linejoin="round"
            >
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
          </div>
          <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-3 border-r-3 border-t-6 border-transparent border-t-blue-500"></div>
        </div>
      `,
      className: 'selected-marker',
      iconSize: [48, 60],
      iconAnchor: [24, 60]
    });

    selectedMarkerRef.current = L.marker([selectedLocation.lat, selectedLocation.lng], {
      icon: selectedIcon,
      zIndexOffset: 1000
    }).addTo(mapInstanceRef.current);

    // Center map on selected location
    mapInstanceRef.current.setView([selectedLocation.lat, selectedLocation.lng], Math.max(zoom, 16));
  }, [selectedLocation, zoom]);

  // Update live tracking markers
  useEffect(() => {
    if (!mapInstanceRef.current || !showLiveTracking || !liveTrackingLayerRef.current) return;

    // Clear existing live tracking markers
    liveTrackingLayerRef.current.clearLayers();

    // Add live tracking markers
    liveTrackingData.forEach(user => {
      const isInArea = user.is_in_school_area;
      
      const trackingIcon = L.divIcon({
        html: `
          <div class="relative">
            <div class="flex items-center justify-center w-8 h-8 bg-white rounded-full shadow-md border-2 border-${isInArea ? 'green' : 'orange'}-500">
              <div class="w-4 h-4 bg-${isInArea ? 'green' : 'orange'}-500 rounded-full"></div>
            </div>
            <div class="absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border border-white animate-ping"></div>
          </div>
        `,
        className: 'tracking-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });

      L.marker([user.latitude, user.longitude], {
        icon: trackingIcon
      })
      .bindPopup(`
        <div class="p-2">
          <h3 class="font-bold text-sm">${user.nama_lengkap}</h3>
          <p class="text-xs ${isInArea ? 'text-green-600' : 'text-orange-600'}">
            ${isInArea ? '✅ Dalam Area' : '⚠️ Luar Area'}
          </p>
          <p class="text-xs text-gray-500">Update: ${user.tracked_at}</p>
        </div>
      `)
      .addTo(liveTrackingLayerRef.current);
    });
  }, [liveTrackingData, showLiveTracking]);

  // Handle fullscreen toggle
  const toggleFullscreen = () => {
    setIsFullscreen(!isFullscreen);
    // Trigger map resize after fullscreen change
    setTimeout(() => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    }, 100);
  };

  // Reset map view
  const resetView = () => {
    if (mapInstanceRef.current) {
      mapInstanceRef.current.setView(center, zoom);
    }
  };

  return (
    <Box 
      className={`relative ${className} ${isFullscreen ? 'fixed inset-0 z-50 bg-white' : ''}`}
      sx={{ height: isFullscreen ? '100vh' : height }}
    >
      {/* Map Container */}
      <Box 
        ref={mapRef} 
        className="w-full h-full rounded-lg overflow-hidden"
        sx={{
          '& .leaflet-container': {
            height: '100%',
            width: '100%',
            borderRadius: isFullscreen ? 0 : 1
          },
          '& .custom-popup .leaflet-popup-content-wrapper': {
            borderRadius: '8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
          },
          '& .custom-marker': {
            background: 'transparent',
            border: 'none'
          },
          '& .selected-marker': {
            background: 'transparent',
            border: 'none'
          },
          '& .tracking-marker': {
            background: 'transparent',
            border: 'none'
          }
        }}
      />

      {/* Map Controls */}
      <Box className="absolute top-2 left-2 z-10 flex flex-col space-y-2">
        <Tooltip title={isFullscreen ? "Keluar Fullscreen" : "Fullscreen"}>
          <Fab
            size="small"
            onClick={toggleFullscreen}
            className="bg-white shadow-md hover:shadow-lg"
          >
            {isFullscreen ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
          </Fab>
        </Tooltip>
        
        <Tooltip title="Reset View">
          <Fab
            size="small"
            onClick={resetView}
            className="bg-white shadow-md hover:shadow-lg"
          >
            <RotateCcw className="w-4 h-4" />
          </Fab>
        </Tooltip>
      </Box>

      {/* Map Info */}
      {!readOnly && (
        <Box className="absolute bottom-2 left-2 bg-white rounded-lg shadow-md p-2 text-xs text-gray-600">
          <Typography variant="caption" className="flex items-center">
            <MapPin className="w-3 h-3 mr-1" />
            Klik pada peta untuk memilih lokasi
          </Typography>
        </Box>
      )}

      {/* Loading Overlay */}
      {!mapReady && (
        <Box className="absolute inset-0 bg-gray-100 flex items-center justify-center rounded-lg">
          <Box className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2" />
            <Typography variant="body2" color="textSecondary">
              Memuat peta...
            </Typography>
          </Box>
        </Box>
      )}
    </Box>
  );
};

export default MapComponent;


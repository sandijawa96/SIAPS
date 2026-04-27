import React, { useEffect, useRef, useState } from 'react';
import {
  Box,
  Typography,
  Button,
  Alert,
  CircularProgress,
  IconButton,
  Tooltip,
  Card,
  CardContent,
  Chip,
  ButtonGroup
} from '@mui/material';
import {
  MapPin,
  Navigation,
  ZoomIn,
  ZoomOut,
  Maximize,
  Minimize,
  Crosshair,
  RotateCcw
} from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
});

const DEFAULT_CENTER = [-6.2088, 106.8456];

const roundPoint = (lat, lng) => ({
  lat: Number.parseFloat(lat.toFixed(6)),
  lng: Number.parseFloat(lng.toFixed(6)),
});

const createMainMarkerIcon = () =>
  L.icon({
    iconUrl: 'data:image/svg+xml;base64,' + btoa(`
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32">
        <path fill="#2196F3" stroke="#ffffff" stroke-width="2" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
        <circle fill="#ffffff" cx="12" cy="9" r="3"/>
      </svg>
    `),
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32]
  });

const createVertexIcon = (index) =>
  L.divIcon({
    html: `
      <div style="
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #0f766e;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        border: 2px solid white;
        box-shadow: 0 4px 12px rgba(15, 118, 110, 0.35);
      ">
        ${index + 1}
      </div>
    `,
    className: 'polygon-vertex-marker',
    iconSize: [28, 28],
    iconAnchor: [14, 14]
  });

const MapPicker = ({
  value,
  onChange,
  height = 400,
  defaultCenter = DEFAULT_CENTER,
  defaultZoom = 13,
  radius = 100,
  mode = 'circle',
  polygonPoints = [],
  onPolygonChange
}) => {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const overlayLayerRef = useRef(null);
  const tileLayerRef = useRef(null);

  const [gettingLocation, setGettingLocation] = useState(false);
  const [locationError, setLocationError] = useState(null);
  const [currentZoom, setCurrentZoom] = useState(defaultZoom);
  const [mapLayer, setMapLayer] = useState('street');
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [accuracy, setAccuracy] = useState(null);

  const mapLayers = {
    street: {
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attribution: '© OpenStreetMap contributors',
      name: 'Street'
    },
    satellite: {
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      attribution: '© Esri',
      name: 'Satellite'
    },
    terrain: {
      url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      attribution: '© OpenTopoMap',
      name: 'Terrain'
    }
  };

  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) {
      return undefined;
    }

    const initialCenter = value ? [value.lat, value.lng] : defaultCenter;
    const map = L.map(mapRef.current, {
      zoomControl: false,
      attributionControl: true
    }).setView(initialCenter, defaultZoom);

    tileLayerRef.current = L.tileLayer(mapLayers.street.url, {
      attribution: mapLayers.street.attribution,
      maxZoom: 19
    }).addTo(map);

    overlayLayerRef.current = L.layerGroup().addTo(map);

    map.on('zoomend', () => {
      setCurrentZoom(map.getZoom());
    });

    mapInstanceRef.current = map;
    setCurrentZoom(map.getZoom());

    return () => {
      map.remove();
      mapInstanceRef.current = null;
      overlayLayerRef.current = null;
      tileLayerRef.current = null;
    };
  }, [defaultCenter, defaultZoom]);

  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map) {
      return undefined;
    }

    const handleClick = (event) => {
      const point = roundPoint(event.latlng.lat, event.latlng.lng);

      if (mode === 'polygon') {
        const nextPoints = [...(Array.isArray(polygonPoints) ? polygonPoints : []), point];
        onPolygonChange?.(nextPoints);
        return;
      }

      onChange?.(point);
    };

    map.on('click', handleClick);

    return () => {
      map.off('click', handleClick);
    };
  }, [mode, onChange, onPolygonChange, polygonPoints]);

  useEffect(() => {
    const map = mapInstanceRef.current;
    const overlay = overlayLayerRef.current;
    if (!map || !overlay) {
      return;
    }

    overlay.clearLayers();

    if (mode === 'polygon') {
      const normalizedPoints = (polygonPoints || [])
        .filter((point) => Number.isFinite(point?.lat) && Number.isFinite(point?.lng))
        .map((point) => [point.lat, point.lng]);

      if (normalizedPoints.length >= 2) {
        const polygonLayer = normalizedPoints.length >= 3
          ? L.polygon(normalizedPoints, {
              color: '#0f766e',
              weight: 3,
              fillColor: '#14b8a6',
              fillOpacity: 0.18
            })
          : L.polyline(normalizedPoints, {
              color: '#0f766e',
              weight: 3,
              dashArray: '6, 6'
            });

        polygonLayer.addTo(overlay);
      }

      (polygonPoints || []).forEach((point, index) => {
        const vertex = L.marker([point.lat, point.lng], {
          draggable: true,
          icon: createVertexIcon(index)
        }).addTo(overlay);

        vertex.on('dragend', (event) => {
          const latLng = event.target.getLatLng();
          const nextPoints = [...polygonPoints];
          nextPoints[index] = roundPoint(latLng.lat, latLng.lng);
          onPolygonChange?.(nextPoints);
        });
      });

      if (normalizedPoints.length > 0 && !value) {
        map.flyTo(normalizedPoints[0], currentZoom, { duration: 0.8 });
      }

      return;
    }

    if (!value) {
      return;
    }

    const marker = L.marker([value.lat, value.lng], {
      icon: createMainMarkerIcon(),
      draggable: true
    }).addTo(overlay);

    const areaCircle = L.circle([value.lat, value.lng], {
      radius,
      fillColor: '#2196F3',
      fillOpacity: 0.1,
      color: '#2196F3',
      weight: 2
    }).addTo(overlay);

    if (accuracy && accuracy < 500) {
      L.circle([value.lat, value.lng], {
        radius: accuracy,
        fillColor: '#4CAF50',
        fillOpacity: 0.02,
        color: '#4CAF50',
        weight: 1,
        dashArray: '10, 10'
      }).addTo(overlay);
    }

    marker.on('drag', (event) => {
      areaCircle.setLatLng(event.target.getLatLng());
    });

    marker.on('dragend', (event) => {
      const position = event.target.getLatLng();
      onChange?.(roundPoint(position.lat, position.lng));
    });

    marker.bindPopup(`
      <div style="text-align: center; padding: 8px;">
        <strong>Lokasi Terpilih</strong><br>
        <small>Lat: ${value.lat}</small><br>
        <small>Lng: ${value.lng}</small><br>
        <small>Radius: ${radius}m</small><br>
        <em style="color: #666;">Drag untuk memindahkan</em>
      </div>
    `);

    map.flyTo([value.lat, value.lng], currentZoom, { duration: 0.8 });
  }, [accuracy, currentZoom, mode, onChange, onPolygonChange, polygonPoints, radius, value]);

  useEffect(() => {
    if (!mapInstanceRef.current) {
      return;
    }

    const timeout = window.setTimeout(() => {
      mapInstanceRef.current?.invalidateSize();
    }, 120);

    return () => window.clearTimeout(timeout);
  }, [isFullscreen]);

  const changeMapLayer = (layerType) => {
    if (!mapInstanceRef.current || !tileLayerRef.current) {
      return;
    }

    mapInstanceRef.current.removeLayer(tileLayerRef.current);
    tileLayerRef.current = L.tileLayer(mapLayers[layerType].url, {
      attribution: mapLayers[layerType].attribution,
      maxZoom: 19
    }).addTo(mapInstanceRef.current);

    setMapLayer(layerType);
  };

  const zoomIn = () => {
    mapInstanceRef.current?.zoomIn();
  };

  const zoomOut = () => {
    mapInstanceRef.current?.zoomOut();
  };

  const resetView = () => {
    const map = mapInstanceRef.current;
    if (!map) {
      return;
    }

    if (mode === 'polygon' && polygonPoints.length > 0) {
      const bounds = L.latLngBounds(polygonPoints.map((point) => [point.lat, point.lng]));
      map.fitBounds(bounds, { padding: [32, 32], maxZoom: 18 });
      return;
    }

    if (value) {
      map.flyTo([value.lat, value.lng], defaultZoom, { duration: 0.8 });
      return;
    }

    map.flyTo(defaultCenter, defaultZoom, { duration: 0.8 });
  };

  const toggleFullscreen = () => {
    setIsFullscreen((previous) => !previous);
  };

  const getCurrentLocation = () => {
    if (!navigator.geolocation) {
      setLocationError('Geolocation tidak didukung oleh browser ini');
      return;
    }

    setGettingLocation(true);
    setLocationError(null);

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const point = roundPoint(position.coords.latitude, position.coords.longitude);
        setAccuracy(position.coords.accuracy);

        if (mode === 'polygon') {
          const nextPoints = polygonPoints.length > 0 ? [...polygonPoints, point] : [point];
          onPolygonChange?.(nextPoints);
          mapInstanceRef.current?.flyTo([point.lat, point.lng], 18, { duration: 0.8 });
        } else {
          onChange?.(point);
        }

        setGettingLocation(false);
      },
      (error) => {
        const messages = {
          1: 'Akses lokasi ditolak. Izinkan akses lokasi di browser.',
          2: 'Informasi lokasi tidak tersedia.',
          3: 'Permintaan lokasi melebihi batas waktu.'
        };

        setLocationError(messages[error.code] || 'Gagal mendapatkan lokasi saat ini.');
        setGettingLocation(false);
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 30000
      }
    );
  };

  return (
    <Box sx={{ width: '100%', height: 'auto' }}>
      <Card sx={{ mb: 2 }}>
        <CardContent sx={{ py: 2 }}>
          <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap', justifyContent: 'space-between' }}>
            <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
              <Button
                variant="contained"
                startIcon={gettingLocation ? <CircularProgress size={16} /> : <Navigation size={16} />}
                onClick={getCurrentLocation}
                disabled={gettingLocation}
                size="small"
                sx={{
                  background: 'linear-gradient(45deg, #2196F3, #1976D2)',
                  '&:hover': {
                    background: 'linear-gradient(45deg, #1976D2, #1565C0)'
                  }
                }}
              >
                {gettingLocation ? 'Mendapatkan Lokasi...' : 'GPS Saya'}
              </Button>

              <Button
                variant="outlined"
                startIcon={<RotateCcw size={16} />}
                onClick={resetView}
                size="small"
              >
                Reset
              </Button>

              <Tooltip title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}>
                <IconButton onClick={toggleFullscreen} size="small">
                  {isFullscreen ? <Minimize size={16} /> : <Maximize size={16} />}
                </IconButton>
              </Tooltip>
            </Box>

            <ButtonGroup size="small" variant="outlined">
              {Object.entries(mapLayers).map(([key, layer]) => (
                <Button
                  key={key}
                  onClick={() => changeMapLayer(key)}
                  variant={mapLayer === key ? 'contained' : 'outlined'}
                  size="small"
                >
                  {layer.name}
                </Button>
              ))}
            </ButtonGroup>
          </Box>

          <Box sx={{ mt: 2, display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
            <Chip
              icon={<MapPin size={16} />}
              label={mode === 'polygon' ? 'Mode Polygon' : 'Mode Circle'}
              color={mode === 'polygon' ? 'success' : 'primary'}
              variant="outlined"
              size="small"
            />
            <Chip
              icon={<Crosshair size={16} />}
              label={`Zoom: ${currentZoom}`}
              variant="outlined"
              size="small"
            />
            {mode === 'polygon' ? (
              <Chip
                label={`Titik: ${polygonPoints.length}`}
                variant="outlined"
                size="small"
              />
            ) : (
              <Chip
                label={`Radius: ${radius}m`}
                variant="outlined"
                size="small"
              />
            )}
            {accuracy && (
              <Chip
                icon={<Navigation size={16} />}
                label={`Akurasi: ±${Math.round(accuracy)}m`}
                color="success"
                variant="outlined"
                size="small"
              />
            )}
          </Box>
        </CardContent>
      </Card>

      {locationError && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setLocationError(null)}>
          {locationError}
        </Alert>
      )}

      <Box sx={{ position: 'relative' }}>
        <Box
          ref={mapRef}
          sx={{
            width: '100%',
            height: isFullscreen ? '80vh' : height,
            borderRadius: 2,
            overflow: 'hidden',
            border: '2px solid',
            borderColor: mode === 'polygon' ? 'success.main' : 'primary.main',
            boxShadow: 3,
            transition: 'all 0.3s ease'
          }}
        />

        <Box
          sx={{
            position: 'absolute',
            top: 16,
            right: 16,
            display: 'flex',
            flexDirection: 'column',
            gap: 1,
            zIndex: 1000
          }}
        >
          <Tooltip title="Zoom In">
            <IconButton
              onClick={zoomIn}
              sx={{
                bgcolor: 'white',
                boxShadow: 2,
                '&:hover': { bgcolor: 'grey.100' }
              }}
              size="small"
            >
              <ZoomIn size={20} />
            </IconButton>
          </Tooltip>
          <Tooltip title="Zoom Out">
            <IconButton
              onClick={zoomOut}
              sx={{
                bgcolor: 'white',
                boxShadow: 2,
                '&:hover': { bgcolor: 'grey.100' }
              }}
              size="small"
            >
              <ZoomOut size={20} />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>

      <Typography variant="caption" color="text.secondary" sx={{ mt: 2, display: 'block', textAlign: 'center' }}>
        {mode === 'polygon'
          ? 'Klik peta untuk menambah titik polygon. Drag titik bernomor untuk mengubah batas area.'
          : 'Klik peta untuk memilih lokasi circle, gunakan GPS Saya, atau drag marker untuk mengubah titik pusat.'}
      </Typography>
    </Box>
  );
};

export default MapPicker;

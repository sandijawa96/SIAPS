import React, { useEffect, useRef, useState, useCallback, useMemo } from 'react';
import {
  Box,
  Typography,
  IconButton,
  Tooltip,
  Fab,
  Card,
  CardContent,
  Chip,
  Button
} from '@mui/material';
import {
  MapPin,
  Maximize2,
  Minimize2,
  RotateCcw,
  Layers,
  Navigation,
  Activity,
  Clock,
  Smartphone
} from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { formatServerDateTime } from '../../services/serverClock';
import { getTrackingStatusReasonLabel } from '../../utils/trackingStatus';

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

const TrackingMap = ({
  students = [],
  selectedStudent = null,
  onStudentSelect = () => {},
  attendanceLocations = [],
  height = 600,
  className = '',
  settings = {},
  totalStudents = 0,
  pageMeta = null,
  overflowCount = 0,
}) => {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const studentsLayerRef = useRef(null);
  const schoolLayerRef = useRef(null);
  const selectedMarkerRef = useRef(null);
  const trafficLayerRef = useRef(null);
  
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [mapReady, setMapReady] = useState(false);
  const [showSchoolAreas, setShowSchoolAreas] = useState(true);
  const [showLegend, setShowLegend] = useState(false);
  const [showMapSummary, setShowMapSummary] = useState(false);

  // Map initialization state
  const [mapInitialized, setMapInitialized] = useState(false);
  const initialViewSetRef = useRef(false);
  const showLastLocation = settings.display?.showLastLocation !== false;
  const trafficLayerConfigured = Boolean(import.meta.env.VITE_TRAFFIC_TILE_URL);
  const selectedMapSummary = useMemo(() => ({
    active: students.filter((student) => student.status === 'active').length,
    outside: students.filter((student) => student.status === 'outside_area').length,
    trackingDisabled: students.filter((student) => student.status === 'tracking_disabled').length,
    outsideSchedule: students.filter((student) => student.status === 'outside_schedule').length,
    stale: students.filter((student) => student.status === 'stale').length,
    gpsDisabled: students.filter((student) => student.status === 'gps_disabled').length,
    noData: students.filter((student) => student.status === 'no_data').length,
  }), [students]);

  const overlayMetrics = useMemo(() => ([
    {
      label: 'Ditampilkan',
      value: students.length,
      tone: 'primary',
    },
    {
      label: 'Fresh dalam area',
      value: selectedMapSummary.active,
      tone: 'success',
    },
    {
      label: 'Fresh luar area',
      value: selectedMapSummary.outside,
      tone: 'warning',
    },
    {
      label: 'Di luar jadwal',
      value: selectedMapSummary.outsideSchedule,
      tone: 'info',
    },
    {
      label: 'Tracking nonaktif',
      value: selectedMapSummary.trackingDisabled,
      tone: 'default',
    },
    {
      label: 'Stale',
      value: selectedMapSummary.stale,
      tone: 'warning',
    },
    {
      label: 'GPS mati',
      value: selectedMapSummary.gpsDisabled,
      tone: 'error',
    },
    {
      label: 'Belum ada data',
      value: selectedMapSummary.noData,
      tone: 'default',
    },
    {
      label: 'Lokasi Absensi',
      value: attendanceLocations.length,
      tone: 'info',
    },
  ]), [attendanceLocations.length, selectedMapSummary.active, selectedMapSummary.gpsDisabled, selectedMapSummary.noData, selectedMapSummary.outside, selectedMapSummary.outsideSchedule, selectedMapSummary.stale, selectedMapSummary.trackingDisabled, students.length]);
  const attentionCount = useMemo(
    () => selectedMapSummary.outside + selectedMapSummary.stale + selectedMapSummary.gpsDisabled,
    [selectedMapSummary.gpsDisabled, selectedMapSummary.outside, selectedMapSummary.stale]
  );
  const compactSummaryItems = useMemo(() => ([
    {
      label: 'Ditampilkan',
      value: students.length,
      tone: 'default',
    },
    {
      label: 'Perlu perhatian',
      value: attentionCount,
      tone: attentionCount > 0 ? 'warning' : 'success',
    },
    {
      label: 'Lokasi',
      value: attendanceLocations.length,
      tone: 'info',
    },
  ]), [attendanceLocations.length, attentionCount, students.length]);

  const getTileAttribution = (theme) => {
    switch (theme) {
      case 'satellite':
        return '(c) Esri, Maxar, Earthstar Geographics';
      case 'terrain':
        return '(c) OpenTopoMap contributors';
      case 'dark':
        return '(c) CartoDB, (c) OpenStreetMap contributors';
      default:
        return '(c) OpenStreetMap contributors';
    }
  };

  // Initialize map only once
  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) return;

    // Get initial zoom from settings or default
    const initialZoom = settings.map?.defaultZoom || 13;

    // Initialize map with default center
    mapInstanceRef.current = L.map(mapRef.current, {
      zoomControl: false,
      minZoom: 4,
      maxZoom: 19,
      preferCanvas: true, // Use canvas for better performance
      zoomAnimation: true,
      fadeAnimation: true,
      markerZoomAnimation: true
    }).setView([-6.2088, 106.8456], initialZoom);

    // Get tile layer URL based on theme
    const getTileLayerUrl = (theme) => {
      switch (theme) {
        case 'satellite':
          return 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
        case 'terrain':
          return 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png';
        case 'dark':
          return 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
        default:
          return 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
      }
    };

    // Add tile layer with theme support and better error handling
    const tileLayerUrl = getTileLayerUrl(settings.map?.theme || 'default');
    const tileLayer = L.tileLayer(tileLayerUrl, {
      attribution: getTileAttribution(settings.map?.theme),
      maxZoom: 19,
      minZoom: 4,
      crossOrigin: true,
      errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', // Transparent 1x1 pixel
      keepBuffer: 8,
      updateWhenZooming: false,
      updateWhenIdle: true,
      reuseTiles: false,
      detectRetina: true,
      noWrap: false,
      bounds: null,
      unloadInvisibleTiles: false,
      updateInterval: 200,
      zoomOffset: 0,
      zoomReverse: false,
      opacity: 1,
      zIndex: 1,
      tileSize: 256
    });
    
    // Handle tile loading errors
    tileLayer.on('tileerror', (error) => {
      console.warn('Tile loading error:', error);
    });

    // Handle tile loading success
    tileLayer.on('tileload', (event) => {
      // Enable tile caching if available
      if ('caches' in window) {
        const tile = event.tile;
        if (tile.src) {
          caches.open('map-tiles').then(cache => {
            cache.add(tile.src).catch(() => {
              // Ignore cache errors
            });
          });
        }
      }
    });
    
    tileLayer.addTo(mapInstanceRef.current);

    // Add zoom control to top right
    L.control.zoom({
      position: 'topright'
    }).addTo(mapInstanceRef.current);

    // Create layers with larger initial capacity
    studentsLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);
    schoolLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);

    // Fix map size issues
    setTimeout(() => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    }, 100);

    // Handle zoom events to prevent blank tiles
    mapInstanceRef.current.on('zoomstart', () => {
      // Force redraw before zoom starts
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    });

    mapInstanceRef.current.on('zoomend', () => {
      // Force multiple refreshes after zoom to ensure tiles load
      setTimeout(() => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.invalidateSize();
          // Force tile layer refresh
          mapInstanceRef.current.eachLayer((layer) => {
            if (layer instanceof L.TileLayer) {
              layer.redraw();
            }
          });
        }
      }, 100);
      
      // Additional refresh after a longer delay
      setTimeout(() => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.invalidateSize();
        }
      }, 300);
    });

    // Handle move events
    mapInstanceRef.current.on('moveend', () => {
      setTimeout(() => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.invalidateSize();
        }
      }, 50);
    });

    // Handle resize events
    mapInstanceRef.current.on('resize', () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    });

    // Handle viewport change events
    mapInstanceRef.current.on('viewreset', () => {
      setTimeout(() => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.invalidateSize();
          // Force all tile layers to redraw
          mapInstanceRef.current.eachLayer((layer) => {
            if (layer instanceof L.TileLayer) {
              layer.redraw();
            }
          });
        }
      }, 100);
    });

    setMapReady(true);
    setMapInitialized(true);

    // Cleanup on unmount
    return () => {
      if (trafficLayerRef.current && mapInstanceRef.current) {
        mapInstanceRef.current.removeLayer(trafficLayerRef.current);
        trafficLayerRef.current = null;
      }
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
        initialViewSetRef.current = false;
      }
    };
  }, []); // Only run once on mount

  // Update map theme when settings change
  useEffect(() => {
    if (!mapInstanceRef.current) return;

    // Get tile layer URL based on theme
    const getTileLayerUrl = (theme) => {
      switch (theme) {
        case 'satellite':
          return 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
        case 'terrain':
          return 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png';
        case 'dark':
          return 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
        default:
          return 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
      }
    };

    // Remove existing tile layers
    mapInstanceRef.current.eachLayer((layer) => {
      if (layer instanceof L.TileLayer) {
        mapInstanceRef.current.removeLayer(layer);
      }
    });

    // Add new tile layer with updated theme
    const tileLayerUrl = getTileLayerUrl(settings.map?.theme || 'default');
    const tileLayer = L.tileLayer(tileLayerUrl, {
      attribution: getTileAttribution(settings.map?.theme),
      maxZoom: 19,
      crossOrigin: true,
      errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
      keepBuffer: 2,
      updateWhenZooming: false,
      updateWhenIdle: true
    });

    // Handle tile loading errors
    tileLayer.on('tileerror', (error) => {
      console.warn('Tile loading error:', error);
    });

    // Handle tile loading success
    tileLayer.on('tileload', (event) => {
      if ('caches' in window) {
        const tile = event.tile;
        if (tile.src) {
          caches.open('map-tiles').then(cache => {
            cache.add(tile.src).catch(() => {
              // Ignore cache errors
            });
          });
        }
      }
    });

    tileLayer.addTo(mapInstanceRef.current);

    // Force map refresh
    setTimeout(() => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    }, 100);

  }, [settings.map?.theme]);

  // Optional traffic layer (requires explicit tile provider URL in env)
  useEffect(() => {
    if (!mapInstanceRef.current) return;

    if (trafficLayerRef.current) {
      mapInstanceRef.current.removeLayer(trafficLayerRef.current);
      trafficLayerRef.current = null;
    }

    if (!settings.map?.showTrafficLayer) {
      return;
    }

    const trafficTileUrl = import.meta.env.VITE_TRAFFIC_TILE_URL;
    if (!trafficTileUrl) {
      return;
    }

    const trafficAttribution = import.meta.env.VITE_TRAFFIC_TILE_ATTRIBUTION || 'Traffic layer';
    const trafficLayer = L.tileLayer(trafficTileUrl, {
      attribution: trafficAttribution,
      maxZoom: 19,
      crossOrigin: true,
      opacity: 0.6
    });

    trafficLayer.addTo(mapInstanceRef.current);
    trafficLayerRef.current = trafficLayer;

    return () => {
      if (trafficLayerRef.current && mapInstanceRef.current) {
        mapInstanceRef.current.removeLayer(trafficLayerRef.current);
        trafficLayerRef.current = null;
      }
    };
  }, [settings.map?.showTrafficLayer, settings.map?.theme, isFullscreen]);

  // Set initial view when data is loaded
  useEffect(() => {
    if (!mapInstanceRef.current || !mapReady) return;

    // Always center on school locations first if available
    if (attendanceLocations.length > 0 && !initialViewSetRef.current) {
      const schoolPoints = attendanceLocations.map(location => [location.latitude, location.longitude]);
      
      if (schoolPoints.length === 1) {
        // Single school location - center on it
        const [lat, lng] = schoolPoints[0];
        mapInstanceRef.current.setView([lat, lng], settings.map?.defaultZoom || 16);
      } else {
        // Multiple school locations - fit bounds
        const bounds = L.latLngBounds(schoolPoints);
        mapInstanceRef.current.fitBounds(bounds, { 
          padding: [50, 50],
          maxZoom: settings.map?.defaultZoom || 16
        });
      }
      initialViewSetRef.current = true;
      return;
    }

    // If no school locations but have students, center on students
    if (students.length > 0 && !initialViewSetRef.current) {
      const studentPoints = students
        .filter(student => student.location && 
                          student.location.lat !== null && 
                          student.location.lng !== null &&
                          typeof student.location.lat === 'number' &&
                          typeof student.location.lng === 'number')
        .map(student => [student.location.lat, student.location.lng]);

      if (studentPoints.length > 0) {
        const bounds = L.latLngBounds(studentPoints);
        mapInstanceRef.current.fitBounds(bounds, { 
          padding: [50, 50],
          maxZoom: settings.map?.defaultZoom || 16
        });
        initialViewSetRef.current = true;
      }
    }

    // Force map refresh after setting view
    setTimeout(() => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
      }
    }, 200);

  }, [mapReady, attendanceLocations, students, settings.map?.defaultZoom]);

  // Update school areas with optimized rendering
  useEffect(() => {
    if (!mapInstanceRef.current || !schoolLayerRef.current) return;

    schoolLayerRef.current.clearLayers();

    if (showSchoolAreas) {
      attendanceLocations.forEach(location => {
        // Create school marker
        const schoolIcon = L.divIcon({
          html: `
            <div class="relative">
              <div class="flex items-center justify-center w-12 h-12 bg-blue-600 rounded-full shadow-lg border-4 border-white">
                <svg 
                  xmlns="http://www.w3.org/2000/svg" 
                  width="20" 
                  height="20" 
                  viewBox="0 0 24 24" 
                  fill="white"
                  stroke="white"
                  stroke-width="2" 
                  stroke-linecap="round" 
                  stroke-linejoin="round"
                >
                  <path d="M3 21h18"></path>
                  <path d="M5 21V7l8-4v18"></path>
                  <path d="M19 21V11l-6-4"></path>
                </svg>
              </div>
            </div>
          `,
          className: 'school-marker',
          iconSize: [48, 48],
          iconAnchor: [24, 24]
        });

        const areaLabel = location.geofence_type === 'polygon' ? 'Batas polygon' : `${location.radius}m`;

        L.marker([location.latitude, location.longitude], {
          icon: schoolIcon,
          zIndexOffset: 1000
        })
        .bindPopup(`
          <div style="padding: 12px; min-width: 200px; font-family: system-ui, -apple-system, sans-serif;">
            <h3 style="font-weight: bold; color: #1e3a8a; margin-bottom: 8px;">${location.nama_lokasi}</h3>
            ${location.deskripsi ? `<p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">${location.deskripsi}</p>` : ''}
            <div style="font-size: 12px; color: #9ca3af;">
              <div style="margin-bottom: 4px;">Koordinat: ${location.latitude}, ${location.longitude}</div>
              <div style="margin-bottom: 4px;">Tipe Area: ${location.geofence_type === 'polygon' ? 'Polygon' : 'Circle'}</div>
              <div style="margin-bottom: 4px;">Area: ${areaLabel}</div>
              <div>Area Absensi</div>
            </div>
          </div>
        `, {
          maxWidth: 250,
          closeButton: true,
          autoClose: false,
          keepInView: true,
          className: 'school-popup'
        })
        .addTo(schoolLayerRef.current);

        const normalizedGeoJson = normalizeGeoJson(location.geofence_geojson);
        const polygonRing = normalizedGeoJson?.coordinates?.[0];

        if (location.geofence_type === 'polygon' && Array.isArray(polygonRing) && polygonRing.length >= 4) {
          L.polygon(
            polygonRing.map(([lng, lat]) => [lat, lng]),
            {
              fillColor: '#2563EB',
              fillOpacity: 0.15,
              color: '#1D4ED8',
              weight: 2,
              opacity: 0.8,
              dashArray: '5, 5'
            }
          )
          .bindTooltip(`Area ${location.nama_lokasi}`, {
            permanent: false,
            direction: 'center',
            className: 'area-tooltip'
          })
          .addTo(schoolLayerRef.current);
        } else {
          L.circle([location.latitude, location.longitude], {
            radius: location.radius,
            fillColor: '#2563EB',
            fillOpacity: 0.15,
            color: '#1D4ED8',
            weight: 2,
            opacity: 0.8,
            dashArray: '5, 5'
          })
          .bindTooltip(`Area ${location.nama_lokasi} (${location.radius}m)`, {
            permanent: false,
            direction: 'center',
            className: 'area-tooltip'
          })
          .addTo(schoolLayerRef.current);
        }
      });
    }
  }, [attendanceLocations, showSchoolAreas]);

  // Update student markers
  useEffect(() => {
    if (!mapInstanceRef.current || !studentsLayerRef.current) return;

    studentsLayerRef.current.clearLayers();

    students.forEach(student => {
      // Skip students without valid location data
      if (!student.location || 
          student.location.lat === null || 
          student.location.lng === null ||
          typeof student.location.lat !== 'number' ||
          typeof student.location.lng !== 'number') {
        return;
      }

      const isActive = student.status === 'active';
      const isOutsideArea = student.status === 'outside_area';
      const isTrackingDisabled = student.status === 'tracking_disabled';
      const isOutsideSchedule = student.status === 'outside_schedule';
      const isStale = student.status === 'stale';
      const isGpsDisabled = student.status === 'gps_disabled';
      const isInArea = student.isInSchoolArea;
      
      // Determine marker color and style
      let markerColor = '#6B7280'; // gray for inactive
      let pulseColor = 'gray';
      
      if (isActive) {
        markerColor = '#10B981';
        pulseColor = 'green';
      } else if (isOutsideArea) {
        markerColor = '#F59E0B';
        pulseColor = 'orange';
      } else if (isTrackingDisabled) {
        markerColor = '#94A3B8';
        pulseColor = 'slate';
      } else if (isOutsideSchedule) {
        markerColor = '#64748B';
        pulseColor = 'slate';
      } else if (isGpsDisabled) {
        markerColor = '#E11D48';
        pulseColor = 'rose';
      } else if (isStale) {
        markerColor = '#F59E0B';
        pulseColor = 'amber';
      }

      const studentIcon = L.divIcon({
        html: `
          <div class="relative" style="z-index: 1000;">
            <div class="flex items-center justify-center w-10 h-10 bg-white rounded-full shadow-lg" style="border: 3px solid ${markerColor};">
              <div class="w-6 h-6 rounded-full" style="background-color: ${markerColor};"></div>
            </div>
            ${(isActive || isOutsideArea || isGpsDisabled) ? `<div class="absolute -top-1 -right-1 w-4 h-4 rounded-full border-2 border-white" style="background-color: ${markerColor}; animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>` : ''}
            <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-t-4 border-transparent" style="border-top-color: ${markerColor};"></div>
          </div>
        `,
        className: 'student-marker',
        iconSize: [40, 50],
        iconAnchor: [20, 50],
        popupAnchor: [0, -50]
      });

      const marker = L.marker([student.location.lat, student.location.lng], {
        icon: studentIcon,
        zIndexOffset: isActive ? 2000 : (isGpsDisabled ? 1800 : 1500)
      })
      .bindPopup(`
        <div style="padding: 16px; min-width: 250px; font-family: system-ui, -apple-system, sans-serif;">
          <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="width: 40px; height: 40px; background-color: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
              <span style="font-weight: bold; color: #2563eb;">${student.name.charAt(0)}</span>
            </div>
            <div>
              <h3 style="font-weight: bold; color: #111827; margin: 0;">${student.name}</h3>
              <p style="font-size: 14px; color: #6b7280; margin: 0;">Kelas ${student.class}</p>
            </div>
          </div>
          
          <div style="margin-bottom: 12px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
              <span style="font-size: 14px; color: #6b7280;">Status:</span>
              <span style="padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; ${
                isActive
                  ? 'background-color: #dcfce7; color: #166534;'
                  : isOutsideArea
                    ? 'background-color: #fed7aa; color: #9a3412;'
                    : isTrackingDisabled
                      ? 'background-color: #f1f5f9; color: #475569;'
                    : isGpsDisabled
                      ? 'background-color: #ffe4e6; color: #be123c;'
                    : isOutsideSchedule
                      ? 'background-color: #e0f2fe; color: #0f766e;'
                    : isStale
                      ? 'background-color: #fef3c7; color: #92400e;'
                      : 'background-color: #f3f4f6; color: #374151;'
              }">
                ${isActive ? 'Dalam area' : isOutsideArea ? 'Luar area' : isTrackingDisabled ? 'Tracking nonaktif' : isGpsDisabled ? 'GPS mati' : isOutsideSchedule ? 'Di luar jadwal' : isStale ? 'Stale' : 'Belum ada data'}
              </span>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
              <span style="font-size: 14px; color: #6b7280;">Update Terakhir:</span>
              <span style="font-size: 12px; color: #9ca3af;">${student.lastUpdate}</span>
            </div>
            
            ${student.location.accuracy ? `
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 14px; color: #6b7280;">Akurasi:</span>
                <span style="font-size: 12px; color: #9ca3af;">${student.location.accuracy}m</span>
              </div>
            ` : ''}
          </div>
          
          ${showLastLocation ? `
            <div style="font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-bottom: 12px;">
              <div style="margin-bottom: 4px;">Koordinat: ${student.location.lat.toFixed(6)}, ${student.location.lng.toFixed(6)}</div>
              <div>Lokasi: ${student.location.address || 'Alamat tidak tersedia'}</div>
            </div>
          ` : ''}
          
          <button 
            onclick="window.selectStudentFromMap('${student.id}')"
            style="width: 100%; padding: 8px 12px; background-color: #2563eb; color: white; border-radius: 8px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: background-color 0.2s;"
            onmouseover="this.style.backgroundColor='#1d4ed8'"
            onmouseout="this.style.backgroundColor='#2563eb'"
          >
            Pilih Siswa
          </button>
        </div>
      `, {
        maxWidth: 300,
        closeButton: true,
        autoClose: false,
        keepInView: true,
        className: 'custom-popup'
      })
      .addTo(studentsLayerRef.current);

      // Add accuracy circle if enabled in settings
      if (settings.display?.showAccuracyCircle && student.location.accuracy > 0) {
        L.circle([student.location.lat, student.location.lng], {
          radius: student.location.accuracy,
          fillColor: markerColor,
          fillOpacity: 0.1,
          color: markerColor,
          weight: 1,
          opacity: 0.3
        }).addTo(studentsLayerRef.current);
      }

      // Add click handler
      marker.on('click', () => {
        onStudentSelect(student);
      });
    });
  }, [students, onStudentSelect, settings.display?.showAccuracyCircle, showLastLocation]);

  // Update selected student marker
  useEffect(() => {
    if (!mapInstanceRef.current || !selectedStudent) {
      if (selectedMarkerRef.current && mapInstanceRef.current) {
        mapInstanceRef.current.removeLayer(selectedMarkerRef.current);
      }
      selectedMarkerRef.current = null;
      return;
    }

    // Check if selected student has valid location data
    if (!selectedStudent.location || 
        selectedStudent.location.lat === null || 
        selectedStudent.location.lng === null ||
        typeof selectedStudent.location.lat !== 'number' ||
        typeof selectedStudent.location.lng !== 'number') {
      return;
    }

    // Remove previous selected marker
    if (selectedMarkerRef.current) {
      mapInstanceRef.current.removeLayer(selectedMarkerRef.current);
    }

    // Add selected student highlight
    const selectedIcon = L.divIcon({
      html: `
        <div class="relative animate-pulse">
          <div class="flex items-center justify-center w-16 h-16 bg-blue-500 rounded-full shadow-xl border-4 border-white">
            <svg 
              xmlns="http://www.w3.org/2000/svg" 
              width="28" 
              height="28" 
              viewBox="0 0 24 24" 
              fill="white"
              stroke="white"
              stroke-width="2" 
              stroke-linecap="round" 
              stroke-linejoin="round"
            >
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
          <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-8 border-transparent border-t-blue-500"></div>
        </div>
      `,
      className: 'selected-student-marker',
      iconSize: [64, 80],
      iconAnchor: [32, 80],
      zIndexOffset: 1000
    });

    selectedMarkerRef.current = L.marker(
      [selectedStudent.location.lat, selectedStudent.location.lng], 
      { 
        icon: selectedIcon,
        zIndexOffset: 3000
      }
    ).addTo(mapInstanceRef.current);

    // Center map on selected student only when auto-center is enabled
    if (settings.map?.autoCenter !== false) {
      mapInstanceRef.current.setView(
        [selectedStudent.location.lat, selectedStudent.location.lng],
        Math.max(mapInstanceRef.current.getZoom(), 16)
      );
    }
  }, [selectedStudent, settings.map?.autoCenter]);

  // Handle fullscreen toggle with map recreation
  const toggleFullscreen = () => {
    const wasFullscreen = isFullscreen;
    setIsFullscreen(!isFullscreen);
    
    // Store current map state
    let currentCenter = [-6.2088, 106.8456];
    let currentZoom = 13;
    
    if (mapInstanceRef.current) {
      currentCenter = [mapInstanceRef.current.getCenter().lat, mapInstanceRef.current.getCenter().lng];
      currentZoom = mapInstanceRef.current.getZoom();
      
      // Remove the old map instance
      mapInstanceRef.current.remove();
      mapInstanceRef.current = null;
      trafficLayerRef.current = null;
    }
    
    // Recreate map after DOM update
    setTimeout(() => {
      if (!mapRef.current) return;
      
      // Initialize new map instance
      mapInstanceRef.current = L.map(mapRef.current, {
        zoomControl: false,
        minZoom: 4,
        maxZoom: 19,
        preferCanvas: true,
        zoomAnimation: true,
        fadeAnimation: true,
        markerZoomAnimation: true
      }).setView(currentCenter, currentZoom);

      // Get tile layer URL based on theme
      const getTileLayerUrl = (theme) => {
        switch (theme) {
          case 'satellite':
            return 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
          case 'terrain':
            return 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png';
          case 'dark':
            return 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
          default:
            return 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        }
      };

      // Add fresh tile layer
      const tileLayerUrl = getTileLayerUrl(settings.map?.theme || 'default');
      const tileLayer = L.tileLayer(tileLayerUrl, {
        attribution: getTileAttribution(settings.map?.theme),
        maxZoom: 19,
        minZoom: 4,
        crossOrigin: true,
        errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        keepBuffer: 4,
        updateWhenZooming: false,
        updateWhenIdle: true,
        reuseTiles: false,
        detectRetina: true
      });
      
      tileLayer.addTo(mapInstanceRef.current);

      // Add zoom control
      L.control.zoom({
        position: 'topright'
      }).addTo(mapInstanceRef.current);

      // Recreate layers
      studentsLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);
      schoolLayerRef.current = L.layerGroup().addTo(mapInstanceRef.current);

      // Force map refresh
      setTimeout(() => {
        if (mapInstanceRef.current) {
          mapInstanceRef.current.invalidateSize();
        }
      }, 100);
      
    }, wasFullscreen ? 100 : 50); // Different timing for enter vs exit fullscreen
  };

  // Reset map view
  const resetView = () => {
    if (mapInstanceRef.current) {
      const allPoints = [];
      
      // Add school locations
      attendanceLocations.forEach(location => {
        allPoints.push([location.latitude, location.longitude]);
      });
      
      // Add student locations (with null checks)
      students.forEach(student => {
        if (student.location && 
            student.location.lat !== null && 
            student.location.lng !== null &&
            typeof student.location.lat === 'number' &&
            typeof student.location.lng === 'number') {
          allPoints.push([student.location.lat, student.location.lng]);
        }
      });

      // If we have points, fit the map to show all of them
      if (allPoints.length > 0) {
        const bounds = L.latLngBounds(allPoints);
        mapInstanceRef.current.fitBounds(bounds, { 
          padding: [50, 50],
          maxZoom: 16
        });
      }
      // If no data but have school locations, center on first school
      else if (attendanceLocations.length > 0) {
        const firstSchool = attendanceLocations[0];
        mapInstanceRef.current.setView([firstSchool.latitude, firstSchool.longitude], 15);
      }
      // Default fallback
      else {
        mapInstanceRef.current.setView([-6.2088, 106.8456], 13);
      }
    }
  };

  // Toggle school areas
  const toggleSchoolAreas = () => {
    setShowSchoolAreas(!showSchoolAreas);
  };

  // Add global function for popup button
  useEffect(() => {
    window.selectStudentFromMap = (studentId) => {
      const student = students.find(s => s.id === studentId);
      if (student) {
        onStudentSelect(student);
      }
    };

    return () => {
      delete window.selectStudentFromMap;
    };
  }, [students, onStudentSelect]);

  // Handle fullscreen state changes with aggressive map refresh
  useEffect(() => {
    if (!mapInstanceRef.current) return;

    // Force multiple invalidations when fullscreen state changes
    const refreshMap = () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.invalidateSize();
        // Force all tile layers to redraw
        mapInstanceRef.current.eachLayer((layer) => {
          if (layer instanceof L.TileLayer) {
            layer.redraw();
          }
        });
      }
    };

    // Multiple refresh attempts with different delays
    setTimeout(refreshMap, 50);
    setTimeout(refreshMap, 150);
    setTimeout(refreshMap, 300);
    setTimeout(refreshMap, 600);
    setTimeout(refreshMap, 1000);

  }, [isFullscreen]);

  const getSelectedStatusMeta = (student) => {
    if (!student?.hasTrackingData) {
      return { label: 'Belum ada data', color: 'default' };
    }

    if (student.status === 'stale') {
      return { label: 'Stale', color: 'warning' };
    }

    if (student.status === 'outside_schedule') {
      return { label: 'Di luar jadwal', color: 'info' };
    }

    if (student.status === 'tracking_disabled') {
      return { label: 'Tracking nonaktif', color: 'default' };
    }

    if (student.status === 'gps_disabled') {
      return { label: 'GPS mati', color: 'error' };
    }

    if (student.status === 'outside_area') {
      return { label: 'Fresh luar area', color: 'warning' };
    }

    return { label: 'Fresh dalam area', color: 'success' };
  };

  const getSelectedGpsMeta = (status) => {
    if (status === 'good') return { label: 'GPS Baik', color: 'success' };
    if (status === 'moderate') return { label: 'GPS Sedang', color: 'warning' };
    if (status === 'poor') return { label: 'GPS Lemah', color: 'error' };
    return { label: 'GPS Tidak Diketahui', color: 'default' };
  };

  const formatSelectedUpdate = (rawTimestamp) => {
    if (!rawTimestamp) {
      return 'Belum ada data tracking';
    }

    const parsedMs = Date.parse(rawTimestamp);
    if (Number.isNaN(parsedMs)) {
      return 'Belum ada data tracking';
    }

    return formatServerDateTime(parsedMs, 'id-ID') || 'Belum ada data tracking';
  };

  const getSelectedTrackingReasonLabel = (student) => getTrackingStatusReasonLabel(
    student?.trackingStatusReason,
    student?.status
  );

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
            borderRadius: isFullscreen ? 0 : 1,
            zIndex: 1
          },
          '& .leaflet-control-container': {
            zIndex: 999
          },
          '& .leaflet-popup-pane': {
            zIndex: 1000
          },
          '& .leaflet-tooltip-pane': {
            zIndex: 1000
          },
          '& .student-marker': {
            background: 'transparent !important',
            border: 'none !important',
            zIndex: '1000 !important'
          },
          '& .selected-student-marker': {
            background: 'transparent !important',
            border: 'none !important',
            zIndex: '1001 !important'
          },
          '& .school-marker': {
            background: 'transparent !important',
            border: 'none !important',
            zIndex: '999 !important'
          },
          '& .custom-popup': {
            zIndex: '1002 !important'
          },
          '& .custom-popup .leaflet-popup-content-wrapper': {
            borderRadius: '8px',
            boxShadow: '0 10px 25px rgba(0,0,0,0.15)',
            zIndex: '1002 !important'
          },
          '& .school-popup': {
            zIndex: '1001 !important'
          },
          '& .school-popup .leaflet-popup-content-wrapper': {
            borderRadius: '8px',
            boxShadow: '0 10px 25px rgba(0,0,0,0.15)',
            backgroundColor: '#f8fafc',
            zIndex: '1001 !important'
          },
          '& .area-tooltip': {
            backgroundColor: 'rgba(37, 99, 235, 0.9)',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            fontSize: '12px',
            fontWeight: '500',
            zIndex: '1000 !important'
          }
        }}
      />

      {/* Map Controls */}
      <Box className="absolute top-4 left-4 z-[1000] flex flex-col space-y-2">
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

        <Tooltip title={showSchoolAreas ? "Sembunyikan Area Absensi" : "Tampilkan Area Absensi"}>
          <Fab
            size="small"
            onClick={toggleSchoolAreas}
            className={`shadow-md hover:shadow-lg ${
              showSchoolAreas ? 'bg-blue-500 text-white' : 'bg-white'
            }`}
          >
            <Layers className="w-4 h-4" />
          </Fab>
        </Tooltip>
      </Box>

      {settings.map?.showTrafficLayer && !trafficLayerConfigured && (
        <Box className="absolute top-4 left-20 z-[1000]">
          <Chip
            size="small"
            color="warning"
            label="Traffic layer belum dikonfigurasi"
          />
        </Box>
      )}

      <Box
        className="absolute left-20 top-4 z-[1000]"
        sx={{
          width: {
            xs: 'calc(100% - 6rem)',
            md: 'min(28rem, calc(100% - 26rem))',
          },
          minWidth: {
            md: '18rem',
          },
        }}
      >
        <Card className="rounded-2xl border border-slate-200/90 bg-white/92 shadow-md backdrop-blur">
          <CardContent className="p-3">
            <Box className="flex items-start justify-between gap-3">
              <Box>
                <Typography variant="subtitle2" className="font-semibold text-slate-900">
                  Peta Investigasi
                </Typography>
                <Typography variant="caption" className="text-slate-500">
                  Fokus pada cohort halaman ini, bukan seluruh roster.
                </Typography>
              </Box>
              <Button size="small" onClick={() => setShowMapSummary((current) => !current)}>
                {showMapSummary ? 'Ringkas' : 'Detail'}
              </Button>
            </Box>

            <Box className="mt-3 flex flex-wrap gap-2">
              {compactSummaryItems.map((metric) => (
                <Chip
                  key={metric.label}
                  size="small"
                  color={metric.tone}
                  variant={metric.tone === 'default' ? 'outlined' : 'filled'}
                  label={`${metric.label}: ${metric.value}`}
                />
              ))}
              {overflowCount > 0 ? (
                <Chip
                  size="small"
                  color="warning"
                  variant="outlined"
                  label={`+${overflowCount} marker disembunyikan`}
                />
              ) : null}
            </Box>

            {showMapSummary ? (
              <Box className="mt-3 space-y-3">
                {totalStudents > students.length ? (
                  <Typography variant="caption" className="block text-slate-500">
                    Total hasil {totalStudents}, tetapi peta hanya menampilkan {students.length} siswa pada cohort aktif agar tetap ringan.
                  </Typography>
                ) : null}

                {pageMeta?.page ? (
                  <Typography variant="caption" className="block text-slate-500">
                    Halaman {pageMeta.page} dari {pageMeta.lastPage || 1}. Gunakan daftar dan filter untuk mengganti cohort yang dipetakan.
                  </Typography>
                ) : null}

                <Box className="grid grid-cols-2 gap-2">
                  {overlayMetrics.map((metric) => (
                    <Box
                      key={metric.label}
                      className="rounded-xl border border-slate-200 bg-slate-50/90 px-3 py-2"
                    >
                      <Typography variant="caption" className="block text-slate-500">
                        {metric.label}
                      </Typography>
                      <Typography
                        variant="subtitle2"
                        className="font-semibold text-slate-900 tabular-nums"
                      >
                        {metric.value}
                      </Typography>
                    </Box>
                  ))}
                </Box>
              </Box>
            ) : null}
          </CardContent>
        </Card>
      </Box>

      {/* Selected Student Info */}
      {selectedStudent && (
        <Box className="absolute bottom-4 right-4 z-[1000]">
          <Card className="w-64 sm:w-72 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white/96 shadow-lg backdrop-blur">
            <CardContent className="p-3">
              <Box className="mb-3 flex items-start justify-between gap-2">
                <Box>
                  <Typography variant="caption" className="text-slate-500">
                    Siswa terpilih
                  </Typography>
                  <Typography variant="subtitle1" className="font-semibold text-slate-900">
                    {selectedStudent.name}
                  </Typography>
                  <Typography variant="caption" className="text-slate-500">
                    {selectedStudent.class ? `Kelas ${selectedStudent.class}` : 'Kelas belum diketahui'}
                  </Typography>
                </Box>
                <IconButton
                  size="small"
                  onClick={() => onStudentSelect(null)}
                  className="text-gray-500"
                >
                  <Minimize2 className="w-4 h-4" />
                </IconButton>
              </Box>

              <Box className="mb-3 flex flex-wrap gap-2">
                {(() => {
                  const statusMeta = getSelectedStatusMeta(selectedStudent);
                  const gpsMeta = getSelectedGpsMeta(selectedStudent.gpsQualityStatus);
                  return (
                    <>
                      <Chip
                        size="small"
                        label={statusMeta.label}
                        color={statusMeta.color}
                        icon={<MapPin className="w-3 h-3" />}
                      />
                      <Chip
                        size="small"
                        label={gpsMeta.label}
                        color={gpsMeta.color}
                        variant="outlined"
                      />
                      {selectedStudent.deviceSource ? (
                        <Chip
                          size="small"
                          label={selectedStudent.deviceSource}
                          variant="outlined"
                          icon={<Smartphone className="w-3 h-3" />}
                        />
                      ) : null}
                    </>
                  );
                })()}
              </Box>

              <Box className="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-gray-600">
                <Box className="flex items-center">
                  <Clock className="mr-2 w-4 h-4" />
                  {formatSelectedUpdate(selectedStudent.lastUpdate)}
                </Box>
                <Box className="flex items-start">
                  <Activity className="mr-2 mt-0.5 w-4 h-4 flex-none" />
                  <span>{getSelectedTrackingReasonLabel(selectedStudent)}</span>
                </Box>
                {showLastLocation && selectedStudent.hasTrackingData && (
                  <Box className="flex items-start">
                    <Navigation className="mr-2 mt-0.5 w-4 h-4 flex-none" />
                    <span className="line-clamp-2">
                      {selectedStudent.status === 'gps_disabled'
                        ? `GPS mati. Lokasi terakhir: ${selectedStudent.location.address}`
                        : selectedStudent.status === 'tracking_disabled'
                          ? `Tracking dinonaktifkan admin. Lokasi terakhir: ${selectedStudent.location.address}`
                          : selectedStudent.status === 'outside_schedule'
                            ? `Tracking dijeda. Lokasi terakhir: ${selectedStudent.location.address}`
                            : selectedStudent.location.address}
                    </span>
                  </Box>
                )}
                {selectedStudent.distanceToNearest ? (
                  <Typography variant="caption" className="block text-slate-500">
                    Jarak ke lokasi terdekat: {Math.round(selectedStudent.distanceToNearest)} m
                  </Typography>
                ) : null}
                {selectedStudent.nearestLocation?.nama_lokasi && !selectedStudent.isInSchoolArea ? (
                  <Typography variant="caption" className="block text-slate-500">
                    Lokasi terdekat: {selectedStudent.nearestLocation.nama_lokasi}
                  </Typography>
                ) : null}
              </Box>
            </CardContent>
          </Card>
        </Box>
      )}

      {/* Map Legend */}
      <Box className="absolute bottom-4 left-4 z-[1000]">
        <Tooltip title={showLegend ? 'Sembunyikan legenda' : 'Tampilkan legenda'}>
          <Fab
            size="small"
            onClick={() => setShowLegend((current) => !current)}
            className={`shadow-md hover:shadow-lg ${
              showLegend ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-white'
            }`}
          >
            <Layers className="w-4 h-4" />
          </Fab>
        </Tooltip>

        {showLegend ? (
          <Box className="mb-2 rounded-2xl border border-slate-200 bg-white/95 p-3 text-xs shadow-md backdrop-blur">
            <Typography variant="caption" className="mb-2 block font-medium text-slate-700">
              Legenda
            </Typography>
            <Box className="space-y-1.5 text-slate-700">
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-green-500" />
                <span>Fresh dalam area</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-orange-500" />
                <span>Fresh luar area</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-amber-500" />
                <span>Stale</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-slate-500" />
                <span>Di luar jadwal</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-slate-400" />
                <span>Tracking nonaktif</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-rose-500" />
                <span>GPS mati</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-gray-400" />
                <span>Belum ada data</span>
              </Box>
              <Box className="flex items-center">
                <Box className="mr-2 h-3 w-3 rounded-full bg-blue-600" />
                <span>Area absensi</span>
              </Box>
            </Box>
          </Box>
        ) : null}
      </Box>

      {/* Loading Overlay */}
      {!mapReady && (
        <Box className="absolute inset-0 z-[1001] bg-gray-100 flex items-center justify-center rounded-lg">
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

export default TrackingMap;


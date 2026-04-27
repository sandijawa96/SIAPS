import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Box,
  Fab,
  Tooltip,
  Typography,
} from '@mui/material';
import {
  Layers,
  Maximize2,
  Minimize2,
  RotateCcw,
} from 'lucide-react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { formatServerDateTime } from '../../services/serverClock';

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

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

const createBadgeIcon = (label, color, options = {}) => {
  const size = Number(options.size || 28);
  const fontSize = Number(options.fontSize || 12);
  const isFilled = options.filled !== false;
  const textColor = options.textColor || (isFilled ? '#ffffff' : color);
  const backgroundColor = isFilled ? color : '#ffffff';
  const borderColor = options.borderColor || color;

  return L.divIcon({
    className: '',
    html: `
      <div style="
        width:${size}px;
        height:${size}px;
        border-radius:999px;
        background:${backgroundColor};
        border:2px solid ${borderColor};
        color:${textColor};
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:${fontSize}px;
        font-weight:700;
        box-shadow:0 8px 18px rgba(15,23,42,0.18);
        font-family:Inter, system-ui, sans-serif;
      ">${escapeHtml(label)}</div>
    `,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -Math.round(size / 2)],
  });
};

const createPointPopupHtml = (session, point) => {
  const statusLabel = point?.is_in_school_area ? 'Dalam area' : 'Luar area';
  const transitionLabel = point?.transition === 'enter_area'
    ? 'Masuk area'
    : point?.transition === 'exit_area'
      ? 'Keluar area'
      : null;

  return `
    <div style="min-width:200px;font-family:Inter,system-ui,sans-serif;color:#0f172a;">
      <div style="font-weight:700;font-size:13px;">${escapeHtml(session?.user?.nama_lengkap || '-')}</div>
      <div style="margin-top:4px;font-size:12px;color:#475569;">Titik ${escapeHtml(point?.sequence || '-')} • ${escapeHtml(formatServerDateTime(point?.tracked_at, 'id-ID') || '-')}</div>
      <div style="margin-top:8px;font-size:12px;">${escapeHtml(point?.location_name || 'Lokasi tidak diketahui')}</div>
      <div style="margin-top:6px;font-size:11px;color:#64748b;">${escapeHtml(statusLabel)}${transitionLabel ? ` • ${escapeHtml(transitionLabel)}` : ''}</div>
      <div style="margin-top:4px;font-size:11px;color:#64748b;">Jarak segmen ${escapeHtml(point?.distance_from_previous_meters ?? 0)} m • Akurasi ${escapeHtml(point?.accuracy ?? '-')} m</div>
    </div>
  `;
};

const createRoutePopupHtml = (session) => `
  <div style="min-width:220px;font-family:Inter,system-ui,sans-serif;color:#0f172a;">
    <div style="font-weight:700;font-size:13px;">${escapeHtml(session?.user?.nama_lengkap || '-')}</div>
    <div style="margin-top:4px;font-size:12px;color:#475569;">${escapeHtml(session?.user?.kelas || '-')} • Tingkat ${escapeHtml(session?.user?.tingkat || '-')}</div>
    <div style="margin-top:8px;font-size:12px;">${escapeHtml(session?.statistics?.total_points ?? 0)} titik • ${escapeHtml(session?.statistics?.estimated_distance_km ?? 0)} km</div>
    <div style="margin-top:4px;font-size:11px;color:#64748b;">${escapeHtml(session?.statistics?.started_at ? (formatServerDateTime(session.statistics.started_at, 'id-ID') || '-') : '-')} s/d ${escapeHtml(session?.statistics?.ended_at ? (formatServerDateTime(session.statistics.ended_at, 'id-ID') || '-') : '-')}</div>
  </div>
`;

const TrackingHistoryRouteMap = ({
  sessions = [],
  focusedUserId = null,
  onFocusUser = () => {},
  attendanceLocations = [],
  height = 520,
  settings = {},
}) => {
  const mapRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const routeLayerRef = useRef(null);
  const areaLayerRef = useRef(null);
  const boundsRef = useRef(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showLegend, setShowLegend] = useState(false);
  const [showSchoolAreas, setShowSchoolAreas] = useState(true);

  const sessionsWithPoints = useMemo(
    () => sessions.filter((session) => Array.isArray(session?.points) && session.points.length > 0),
    [sessions]
  );

  const resolvedFocusedUserId = useMemo(() => {
    if (focusedUserId && sessions.some((session) => session?.user?.id === focusedUserId)) {
      return focusedUserId;
    }

    return sessions[0]?.user?.id || null;
  }, [focusedUserId, sessions]);

  const fitToContent = useCallback(() => {
    const map = mapInstanceRef.current;
    if (!map) return;

    if (boundsRef.current && boundsRef.current.isValid()) {
      map.fitBounds(boundsRef.current, { padding: [36, 36] });
      return;
    }

    map.setView(DEFAULT_CENTER, settings?.map?.defaultZoom || 13);
  }, [settings?.map?.defaultZoom]);

  useEffect(() => {
    if (!mapRef.current || mapInstanceRef.current) {
      return undefined;
    }

    const map = L.map(mapRef.current, {
      zoomControl: false,
      minZoom: 4,
      maxZoom: 19,
      preferCanvas: true,
      zoomAnimation: true,
      fadeAnimation: true,
      markerZoomAnimation: true,
    }).setView(DEFAULT_CENTER, settings?.map?.defaultZoom || 13);

    const tileLayer = L.tileLayer(getTileLayerUrl(settings?.map?.theme || 'default'), {
      attribution: getTileAttribution(settings?.map?.theme),
      maxZoom: 19,
      minZoom: 4,
      crossOrigin: true,
      detectRetina: true,
    });

    tileLayer.addTo(map);
    routeLayerRef.current = L.layerGroup().addTo(map);
    areaLayerRef.current = L.layerGroup().addTo(map);
    mapInstanceRef.current = map;

    return () => {
      routeLayerRef.current?.clearLayers();
      areaLayerRef.current?.clearLayers();
      map.remove();
      mapInstanceRef.current = null;
    };
  }, [settings?.map?.defaultZoom, settings?.map?.theme]);

  useEffect(() => {
    if (!mapInstanceRef.current) {
      return;
    }

    window.setTimeout(() => {
      mapInstanceRef.current?.invalidateSize();
      fitToContent();
    }, 180);
  }, [fitToContent, isFullscreen]);

  useEffect(() => {
    const map = mapInstanceRef.current;
    const routeLayer = routeLayerRef.current;
    const areaLayer = areaLayerRef.current;

    if (!map || !routeLayer || !areaLayer) {
      return;
    }

    routeLayer.clearLayers();
    areaLayer.clearLayers();

    let nextBounds = null;
    const extendBounds = (lat, lng) => {
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return;
      }

      if (!nextBounds) {
        nextBounds = L.latLngBounds([[lat, lng], [lat, lng]]);
        return;
      }

      nextBounds.extend([lat, lng]);
    };

    if (showSchoolAreas) {
      attendanceLocations.forEach((location) => {
        const geoJson = normalizeGeoJson(location?.geojson || location?.geofence_geojson || null);
        if (geoJson) {
          try {
            const geoJsonLayer = L.geoJSON(geoJson, {
              style: {
                color: '#2563eb',
                weight: 2,
                opacity: 0.7,
                fillColor: '#60a5fa',
                fillOpacity: 0.08,
              },
            });
            geoJsonLayer.addTo(areaLayer);
            const bounds = geoJsonLayer.getBounds();
            if (bounds?.isValid()) {
              nextBounds = nextBounds ? nextBounds.extend(bounds) : bounds;
            }
          } catch (error) {
            // Ignore malformed GeoJSON in map history overlay.
          }
          return;
        }

        const lat = Number(location?.latitude);
        const lng = Number(location?.longitude);
        const radius = Number(location?.radius || 0);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return;
        }

        L.circle([lat, lng], {
          radius: Number.isFinite(radius) && radius > 0 ? radius : 25,
          color: '#2563eb',
          weight: 2,
          opacity: 0.65,
          fillColor: '#60a5fa',
          fillOpacity: 0.08,
        })
          .bindPopup(`<div style="font-family:Inter,system-ui,sans-serif;font-size:12px;">${escapeHtml(location?.namaLokasi || location?.nama_lokasi || 'Area absensi')}</div>`)
          .addTo(areaLayer);
        extendBounds(lat, lng);
      });
    }

    sessionsWithPoints.forEach((session) => {
      const color = session?.color || '#2563eb';
      const isFocused = session?.user?.id === resolvedFocusedUserId || sessionsWithPoints.length === 1;
      const points = Array.isArray(session?.route_points) && session.route_points.length > 0
        ? session.route_points
        : (Array.isArray(session?.points) ? session.points : []);
      const latLngs = points
        .map((point) => [Number(point?.latitude), Number(point?.longitude)])
        .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

      if (latLngs.length === 0) {
        return;
      }

      latLngs.forEach(([lat, lng]) => extendBounds(lat, lng));

      const polyline = L.polyline(latLngs, {
        color,
        weight: isFocused ? 5 : 3,
        opacity: isFocused ? 0.92 : 0.55,
      })
        .bindPopup(createRoutePopupHtml(session))
        .on('click', () => onFocusUser(session?.user?.id || null));
      polyline.addTo(routeLayer);

      if (isFocused) {
        points.forEach((point) => {
          const lat = Number(point?.latitude);
          const lng = Number(point?.longitude);
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
          }

          L.marker([lat, lng], {
            icon: createBadgeIcon(String(point?.sequence || '?'), color, {
              size: point?.is_start || point?.is_end ? 30 : 28,
              fontSize: point?.sequence && point.sequence > 99 ? 10 : 12,
            }),
            keyboard: false,
          })
            .bindPopup(createPointPopupHtml(session, point))
            .on('click', () => onFocusUser(session?.user?.id || null))
            .addTo(routeLayer);
        });
        return;
      }

      const firstPoint = points[0];
      const lastPoint = points[points.length - 1];
      [
        [firstPoint, 'S'],
        [lastPoint, points.length > 1 ? 'E' : String(lastPoint?.sequence || 1)],
      ].forEach(([point, label], index) => {
        const lat = Number(point?.latitude);
        const lng = Number(point?.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return;
        }

        L.marker([lat, lng], {
          icon: createBadgeIcon(String(label), color, {
            size: 26,
            fontSize: 11,
            filled: index === 0,
          }),
          keyboard: false,
        })
          .bindPopup(createPointPopupHtml(session, point))
          .on('click', () => onFocusUser(session?.user?.id || null))
          .addTo(routeLayer);
      });
    });

    boundsRef.current = nextBounds;
    fitToContent();
  }, [attendanceLocations, fitToContent, onFocusUser, resolvedFocusedUserId, sessionsWithPoints, showSchoolAreas]);

  return (
    <Box
      className={`relative overflow-hidden rounded-3xl border border-slate-200 bg-white ${isFullscreen ? 'fixed inset-0 z-[1400] rounded-none' : ''}`}
      sx={{ height: isFullscreen ? '100vh' : height }}
    >
      <div ref={mapRef} className="h-full w-full" />

      <Box className="absolute right-3 top-3 z-[1000] flex flex-col gap-2">
        <Tooltip title={isFullscreen ? 'Keluar fullscreen' : 'Fullscreen'}>
          <Fab
            size="small"
            onClick={() => setIsFullscreen((current) => !current)}
            className="bg-white text-slate-700 shadow-md hover:bg-slate-50"
          >
            {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
          </Fab>
        </Tooltip>
        <Tooltip title="Reset view">
          <Fab
            size="small"
            onClick={fitToContent}
            className="bg-white text-slate-700 shadow-md hover:bg-slate-50"
          >
            <RotateCcw className="h-4 w-4" />
          </Fab>
        </Tooltip>
        <Tooltip title={showSchoolAreas ? 'Sembunyikan area absensi' : 'Tampilkan area absensi'}>
          <Fab
            size="small"
            onClick={() => setShowSchoolAreas((current) => !current)}
            className={showSchoolAreas ? 'bg-blue-500 text-white shadow-md hover:bg-blue-600' : 'bg-white text-slate-700 shadow-md hover:bg-slate-50'}
          >
            <Layers className="h-4 w-4" />
          </Fab>
        </Tooltip>
      </Box>

      <Box className="absolute bottom-3 right-3 z-[1000] flex flex-col items-end gap-2">
        <Tooltip title={showLegend ? 'Sembunyikan legenda' : 'Tampilkan legenda'}>
          <Fab
            size="small"
            onClick={() => setShowLegend((current) => !current)}
            className={showLegend ? 'bg-slate-900 text-white shadow-md hover:bg-slate-800' : 'bg-white text-slate-700 shadow-md hover:bg-slate-50'}
          >
            <Layers className="h-4 w-4" />
          </Fab>
        </Tooltip>
        {showLegend ? (
          <Box className="w-72 rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur">
            <Typography variant="subtitle2" className="font-semibold text-slate-900">
              Legenda histori peta
            </Typography>
            <Typography variant="caption" className="mt-1 block text-slate-500">
              Rute fokus menampilkan nomor titik lengkap. Siswa lain ditampilkan ringkas dengan penanda awal dan akhir.
            </Typography>
            <Box className="mt-3 space-y-2">
              {sessions.map((session) => (
                <Box key={session?.user?.id || session?.color} className="flex items-center gap-3">
                  <span
                    className="inline-flex h-3 w-3 rounded-full"
                    style={{ backgroundColor: session?.color || '#2563eb' }}
                  />
                  <Typography variant="body2" className="text-slate-700">
                    {session?.user?.nama_lengkap || '-'}
                  </Typography>
                </Box>
              ))}
            </Box>
          </Box>
        ) : null}
      </Box>

      {sessionsWithPoints.length === 0 ? (
        <Box className="absolute inset-0 z-[900] flex items-center justify-center bg-white/80">
          <Box className="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-5 text-center shadow-sm">
            <Typography variant="subtitle2" className="font-semibold text-slate-900">
              Belum ada titik histori untuk ditampilkan
            </Typography>
            <Typography variant="body2" className="mt-1 text-slate-600">
              Coba pilih tanggal atau siswa lain yang memiliki histori tracking tersimpan.
            </Typography>
          </Box>
        </Box>
      ) : null}
    </Box>
  );
};

export default TrackingHistoryRouteMap;


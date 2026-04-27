import React, { useEffect, useMemo, useState, memo } from 'react';
import {
  Box,
  Typography,
  Button,
  TextField,
  Modal,
  IconButton,
  Alert,
  Switch,
  Tabs,
  Tab,
  Grid,
  Stack,
  Paper,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip
} from '@mui/material';
import {
  MapPin,
  Plus,
  X,
  Target,
  Palette,
  Settings,
  Navigation,
  Crosshair,
  Scan,
  Trash2
} from 'lucide-react';
import MapPicker from '../maps/MapPicker';

const DEFAULT_FORM_DATA = {
  nama_lokasi: '',
  deskripsi: '',
  latitude: '',
  longitude: '',
  radius: 100,
  geofence_type: 'circle',
  geofence_geojson: null,
  is_active: true,
  warna_marker: '#2196F3',
  roles: '[]',
  waktu_mulai: '06:00',
  waktu_selesai: '18:00',
  hari_aktif: '["senin","selasa","rabu","kamis","jumat"]'
};

const TabPanel = memo(({ children, value, index, ...other }) => (
  <div role="tabpanel" hidden={value !== index} {...other}>
    {value === index && <Box sx={{ pt: 3 }}>{children}</Box>}
  </div>
));

const parseGeoJson = (value) => {
  if (!value) {
    return null;
  }

  if (typeof value === 'string') {
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }

  if (typeof value === 'object') {
    return value;
  }

  return null;
};

const extractPolygonPoints = (geoJsonValue) => {
  const geoJson = parseGeoJson(geoJsonValue);
  const ring = geoJson?.coordinates?.[0];

  if (!Array.isArray(ring)) {
    return [];
  }

  const points = ring
    .filter((point) => Array.isArray(point) && point.length >= 2)
    .map(([lng, lat]) => ({
      lat: Number.parseFloat(Number(lat).toFixed(6)),
      lng: Number.parseFloat(Number(lng).toFixed(6))
    }))
    .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));

  if (points.length > 1) {
    const first = points[0];
    const last = points[points.length - 1];
    if (first.lat === last.lat && first.lng === last.lng) {
      points.pop();
    }
  }

  return points;
};

const buildPolygonGeoJson = (points) => {
  if (!Array.isArray(points) || points.length < 3) {
    return null;
  }

  const ring = points.map((point) => [
    Number.parseFloat(point.lng.toFixed(6)),
    Number.parseFloat(point.lat.toFixed(6))
  ]);

  const first = ring[0];
  const last = ring[ring.length - 1];
  if (first[0] !== last[0] || first[1] !== last[1]) {
    ring.push(first);
  }

  return {
    type: 'Polygon',
    coordinates: [ring]
  };
};

const calculatePolygonCenter = (points) => {
  if (!Array.isArray(points) || points.length === 0) {
    return null;
  }

  const lat = points.reduce((total, point) => total + point.lat, 0) / points.length;
  const lng = points.reduce((total, point) => total + point.lng, 0) / points.length;

  return {
    lat: Number.parseFloat(lat.toFixed(6)),
    lng: Number.parseFloat(lng.toFixed(6))
  };
};

const LocationFormModal = memo(({
  open,
  onClose,
  onSubmit,
  initialData = null
}) => {
  const [formData, setFormData] = useState(DEFAULT_FORM_DATA);
  const [tabValue, setTabValue] = useState(0);
  const [mapLocation, setMapLocation] = useState(null);
  const [polygonPoints, setPolygonPoints] = useState([]);

  useEffect(() => {
    if (!open) {
      return;
    }

    if (initialData) {
      const geofenceType = initialData.geofence_type || 'circle';
      const nextPolygonPoints = extractPolygonPoints(initialData.geofence_geojson);
      const nextCenter = geofenceType === 'polygon'
        ? calculatePolygonCenter(nextPolygonPoints)
        : (
            initialData.latitude && initialData.longitude
              ? {
                  lat: Number.parseFloat(Number(initialData.latitude).toFixed(6)),
                  lng: Number.parseFloat(Number(initialData.longitude).toFixed(6))
                }
              : null
          );

      setFormData({
        nama_lokasi: initialData.nama_lokasi || '',
        deskripsi: initialData.deskripsi || '',
        latitude: initialData.latitude || '',
        longitude: initialData.longitude || '',
        radius: initialData.radius || 100,
        geofence_type: geofenceType,
        geofence_geojson: parseGeoJson(initialData.geofence_geojson),
        is_active: initialData.is_active ?? true,
        warna_marker: initialData.warna_marker || '#2196F3',
        roles: initialData.roles || '[]',
        waktu_mulai: initialData.waktu_mulai || '06:00',
        waktu_selesai: initialData.waktu_selesai || '18:00',
        hari_aktif: initialData.hari_aktif || '["senin","selasa","rabu","kamis","jumat"]'
      });
      setPolygonPoints(nextPolygonPoints);
      setMapLocation(nextCenter);
    } else {
      setFormData(DEFAULT_FORM_DATA);
      setPolygonPoints([]);
      setMapLocation(null);
    }

    setTabValue(0);
  }, [initialData, open]);

  const polygonGeoJson = useMemo(() => buildPolygonGeoJson(polygonPoints), [polygonPoints]);
  const polygonCenter = useMemo(() => calculatePolygonCenter(polygonPoints), [polygonPoints]);

  useEffect(() => {
    if (formData.geofence_type !== 'polygon') {
      return;
    }

    setFormData((previous) => ({
      ...previous,
      geofence_geojson: polygonGeoJson,
      latitude: polygonCenter ? polygonCenter.lat.toFixed(6) : previous.latitude,
      longitude: polygonCenter ? polygonCenter.lng.toFixed(6) : previous.longitude
    }));

    if (polygonCenter) {
      setMapLocation(polygonCenter);
    }
  }, [formData.geofence_type, polygonCenter, polygonGeoJson]);

  const handleInputChange = (field, value) => {
    setFormData((previous) => ({ ...previous, [field]: value }));
  };

  const handleTypeChange = (value) => {
    setFormData((previous) => ({
      ...previous,
      geofence_type: value,
      geofence_geojson: value === 'polygon' ? previous.geofence_geojson : null
    }));
  };

  const handleMapLocationChange = (location) => {
    setMapLocation(location);
    setFormData((previous) => ({
      ...previous,
      latitude: location.lat.toFixed(6),
      longitude: location.lng.toFixed(6)
    }));
  };

  const handlePolygonChange = (points) => {
    setPolygonPoints(points);
  };

  const handleRemoveLastPoint = () => {
    setPolygonPoints((previous) => previous.slice(0, -1));
  };

  const handleResetPolygon = () => {
    setPolygonPoints([]);
    setFormData((previous) => ({
      ...previous,
      geofence_geojson: null
    }));
  };

  const submitDisabled = !formData.nama_lokasi
    || !formData.latitude
    || !formData.longitude
    || (formData.geofence_type === 'polygon' && polygonPoints.length < 3);

  const handleSubmit = () => {
    const payload = {
      ...formData,
      geofence_type: formData.geofence_type,
      geofence_geojson: formData.geofence_type === 'polygon' ? polygonGeoJson : null,
      radius: Number.parseInt(formData.radius, 10) || 100
    };

    onSubmit(payload);
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      sx={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        p: 2
      }}
    >
      <Paper
        className="rounded-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden"
        sx={{ outline: 'none' }}
      >
        <Box className="bg-gradient-to-r from-blue-500 to-teal-600 text-white p-6">
          <Box className="flex items-center justify-between">
            <Box className="flex items-center space-x-2">
              <MapPin size={24} />
              <Typography variant="h6">
                {initialData ? 'Edit Lokasi' : 'Tambah Lokasi Baru'}
              </Typography>
            </Box>
            <IconButton onClick={onClose} sx={{ color: 'white' }}>
              <X size={24} />
            </IconButton>
          </Box>
        </Box>

        <Box sx={{ maxHeight: 'calc(90vh - 200px)', overflow: 'auto' }}>
          <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
            <Tabs
              value={tabValue}
              onChange={(event, newValue) => setTabValue(newValue)}
              variant="fullWidth"
              className="bg-gray-50"
            >
              <Tab icon={<Settings size={20} />} label="Informasi Dasar" className="font-medium" />
              <Tab icon={<MapPin size={20} />} label="Lokasi & Peta" className="font-medium" />
            </Tabs>
          </Box>

          <Box className="p-6">
            <TabPanel value={tabValue} index={0}>
              <Stack spacing={3}>
                <TextField
                  label="Nama Lokasi"
                  fullWidth
                  required
                  value={formData.nama_lokasi}
                  onChange={(event) => handleInputChange('nama_lokasi', event.target.value)}
                  placeholder="Contoh: Area Sekolah Utama"
                  InputProps={{
                    startAdornment: <MapPin size={20} className="mr-2 text-gray-400" />
                  }}
                />

                <TextField
                  label="Deskripsi"
                  fullWidth
                  multiline
                  rows={3}
                  value={formData.deskripsi}
                  onChange={(event) => handleInputChange('deskripsi', event.target.value)}
                  placeholder="Deskripsi singkat tentang lokasi..."
                />

                <FormControl fullWidth>
                  <InputLabel id="geofence-type-label">Tipe Area</InputLabel>
                  <Select
                    labelId="geofence-type-label"
                    label="Tipe Area"
                    value={formData.geofence_type}
                    onChange={(event) => handleTypeChange(event.target.value)}
                  >
                    <MenuItem value="circle">Circle</MenuItem>
                    <MenuItem value="polygon">Polygon</MenuItem>
                  </Select>
                </FormControl>

                <Alert severity="info" icon={<Scan size={18} />}>
                  {formData.geofence_type === 'circle'
                    ? 'Mode circle menggunakan satu titik pusat dan radius.'
                    : 'Mode polygon menggunakan beberapa titik batas area. Minimal 3 titik.'}
                </Alert>

                <Box className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                  <Box>
                    <Typography variant="subtitle1" className="font-medium">
                      Status Lokasi
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Aktifkan lokasi agar dapat digunakan untuk validasi absensi
                    </Typography>
                  </Box>
                  <Switch
                    checked={formData.is_active}
                    onChange={(event) => handleInputChange('is_active', event.target.checked)}
                    color="primary"
                  />
                </Box>

                <Box>
                  <Typography variant="subtitle1" className="mb-2 flex items-center">
                    <Palette size={20} className="mr-2" />
                    Warna Marker
                  </Typography>
                  <TextField
                    type="color"
                    value={formData.warna_marker}
                    onChange={(event) => handleInputChange('warna_marker', event.target.value)}
                    sx={{ width: 100, height: 56 }}
                  />
                </Box>
              </Stack>
            </TabPanel>

            <TabPanel value={tabValue} index={1}>
              <Grid container spacing={3}>
                <Grid item xs={12} md={5}>
                  <Stack spacing={3}>
                    <Alert severity="info" icon={<Crosshair size={20} />}>
                      {formData.geofence_type === 'circle'
                        ? 'Klik peta untuk memilih titik pusat, gunakan GPS, atau masukkan koordinat manual.'
                        : 'Klik peta untuk menambah titik polygon. Drag titik bernomor untuk menyesuaikan batas.'}
                    </Alert>

                    <Box>
                      <Typography variant="subtitle1" className="mb-3 font-medium flex items-center">
                        <MapPin size={18} className="mr-2" />
                        {formData.geofence_type === 'polygon' ? 'Titik Pusat Area' : 'Koordinat Lokasi'}
                      </Typography>

                      <Grid container spacing={2}>
                        <Grid item xs={12}>
                          <TextField
                            label="Latitude"
                            fullWidth
                            required
                            type="number"
                            inputProps={{ step: 0.000001 }}
                            value={formData.latitude}
                            onChange={(event) => handleInputChange('latitude', event.target.value)}
                            helperText={formData.geofence_type === 'polygon'
                              ? 'Otomatis mengikuti pusat polygon, namun tetap dapat disesuaikan manual.'
                              : 'Koordinat lintang (-90 hingga 90)'}
                          />
                        </Grid>
                        <Grid item xs={12}>
                          <TextField
                            label="Longitude"
                            fullWidth
                            required
                            type="number"
                            inputProps={{ step: 0.000001 }}
                            value={formData.longitude}
                            onChange={(event) => handleInputChange('longitude', event.target.value)}
                            helperText={formData.geofence_type === 'polygon'
                              ? 'Otomatis mengikuti pusat polygon, namun tetap dapat disesuaikan manual.'
                              : 'Koordinat bujur (-180 hingga 180)'}
                          />
                        </Grid>
                      </Grid>
                    </Box>

                    {formData.geofence_type === 'circle' ? (
                      <Box>
                        <Typography variant="subtitle1" className="mb-3 font-medium flex items-center">
                          <Target size={18} className="mr-2" />
                          Area Absensi
                        </Typography>

                        <TextField
                          label="Radius (meter)"
                          fullWidth
                          required
                          type="number"
                          inputProps={{ min: 10, max: 1000 }}
                          value={formData.radius}
                          onChange={(event) => handleInputChange('radius', event.target.value)}
                          helperText="Radius area absensi dalam meter (10-1000m)"
                        />
                      </Box>
                    ) : (
                      <Box>
                        <Typography variant="subtitle1" className="mb-3 font-medium flex items-center">
                          <Scan size={18} className="mr-2" />
                          Titik Polygon
                        </Typography>

                        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                          <Chip
                            label={`${polygonPoints.length} titik`}
                            color={polygonPoints.length >= 3 ? 'success' : 'warning'}
                            variant="outlined"
                          />
                          <Button
                            size="small"
                            variant="outlined"
                            color="warning"
                            startIcon={<Trash2 size={14} />}
                            onClick={handleRemoveLastPoint}
                            disabled={polygonPoints.length === 0}
                          >
                            Hapus Titik Terakhir
                          </Button>
                          <Button
                            size="small"
                            variant="outlined"
                            color="error"
                            startIcon={<Trash2 size={14} />}
                            onClick={handleResetPolygon}
                            disabled={polygonPoints.length === 0}
                          >
                            Reset Polygon
                          </Button>
                        </Stack>

                        <Box className="mt-3 max-h-48 overflow-auto rounded-lg border border-gray-200 p-3 bg-gray-50">
                          {polygonPoints.length === 0 ? (
                            <Typography variant="body2" color="text.secondary">
                              Belum ada titik polygon. Klik peta untuk mulai menggambar area.
                            </Typography>
                          ) : (
                            <Stack spacing={1}>
                              {polygonPoints.map((point, index) => (
                                <Typography key={`${point.lat}-${point.lng}-${index}`} variant="body2" className="font-mono">
                                  Titik {index + 1}: {point.lat.toFixed(6)}, {point.lng.toFixed(6)}
                                </Typography>
                              ))}
                            </Stack>
                          )}
                        </Box>
                      </Box>
                    )}

                    {mapLocation && (
                      <Alert severity="success" icon={<Navigation size={20} />}>
                        <Typography variant="subtitle2" className="font-medium">
                          Fokus Peta
                        </Typography>
                        <Typography variant="body2" className="font-mono">
                          {mapLocation.lat.toFixed(6)}, {mapLocation.lng.toFixed(6)}
                        </Typography>
                      </Alert>
                    )}
                  </Stack>
                </Grid>

                <Grid item xs={12} md={7}>
                  <Box>
                    <Typography variant="subtitle1" className="mb-3 font-medium flex items-center">
                      <MapPin size={18} className="mr-2" />
                      Peta Interaktif
                    </Typography>

                    <Box className="overflow-hidden shadow-lg rounded-lg">
                      <MapPicker
                        value={mapLocation}
                        onChange={handleMapLocationChange}
                        height={400}
                        radius={Number.parseInt(formData.radius, 10) || 100}
                        mode={formData.geofence_type}
                        polygonPoints={polygonPoints}
                        onPolygonChange={handlePolygonChange}
                      />
                    </Box>
                  </Box>
                </Grid>
              </Grid>
            </TabPanel>
          </Box>
        </Box>

        <Box className="p-6 bg-gray-50 border-t">
          <Box className="flex justify-end space-x-3">
            <Button onClick={onClose} startIcon={<X size={16} />}>
              Batal
            </Button>
            <Button
              onClick={handleSubmit}
              variant="contained"
              disabled={submitDisabled}
              startIcon={<Plus size={16} />}
              className="bg-gradient-to-r from-blue-500 to-teal-600 hover:from-blue-600 hover:to-teal-700"
            >
              {initialData ? 'Perbarui' : 'Simpan'}
            </Button>
          </Box>
        </Box>
      </Paper>
    </Modal>
  );
});

LocationFormModal.displayName = 'LocationFormModal';

export default LocationFormModal;

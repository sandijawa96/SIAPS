const normalizeArrayValue = (value) => {
  if (Array.isArray(value)) {
    return value;
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) {
        return parsed;
      }

      if (typeof parsed === 'string') {
        return normalizeArrayValue(parsed);
      }
    } catch (_) {
      return [];
    }
  }

  return [];
};

const toNumberOrNull = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
};

const getPolygonRing = (value) => {
  const normalized = normalizeGeoJson(value);
  if (!normalized) {
    return [];
  }

  const geometry = normalized.type === 'Feature' ? normalized.geometry : normalized;
  if (geometry?.type !== 'Polygon' || !Array.isArray(geometry.coordinates)) {
    return [];
  }

  const [ring] = geometry.coordinates;
  return Array.isArray(ring) ? ring : [];
};

export const normalizeIdArray = (value) =>
  normalizeArrayValue(value)
    .map((item) => Number(item))
    .filter((item) => Number.isInteger(item) && item > 0);

export const normalizeGeoJson = (value) => {
  if (!value) {
    return null;
  }

  if (typeof value === 'string') {
    try {
      return JSON.parse(value);
    } catch (_) {
      return null;
    }
  }

  if (typeof value === 'object') {
    return value;
  }

  return null;
};

export const normalizeLocationRows = (rows) => {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .filter(Boolean)
    .map((row) => ({
      ...row,
      id: toNumberOrNull(row.id) ?? row.id,
      latitude: toNumberOrNull(row.latitude),
      longitude: toNumberOrNull(row.longitude),
      radius: toNumberOrNull(row.radius),
      is_active: row.is_active === undefined ? true : Boolean(row.is_active),
      geofence_type: String(row.geofence_type || 'circle').trim().toLowerCase() === 'polygon'
        ? 'polygon'
        : 'circle',
      geofence_geojson: normalizeGeoJson(row.geofence_geojson),
    }));
};

export const getLocationTypeLabel = (location = {}) =>
  String(location?.geofence_type || '').trim().toLowerCase() === 'polygon'
    ? 'Polygon'
    : 'Circle';

export const getLocationAreaSummary = (location = {}) => {
  if (getLocationTypeLabel(location) === 'Polygon') {
    const ring = getPolygonRing(location?.geofence_geojson);
    const pointCount = Math.max(0, ring.length > 1 ? ring.length - 1 : ring.length);

    return pointCount >= 3 ? `${pointCount} titik batas` : 'Batas polygon';
  }

  const radius = toNumberOrNull(location?.radius);
  return radius && radius > 0 ? `${radius} m` : 'Radius belum diatur';
};

export const formatLocationDisplayLabel = (location = {}) => {
  const locationName = location?.nama_lokasi || 'Lokasi tanpa nama';
  return `${locationName} - ${getLocationTypeLabel(location)} (${getLocationAreaSummary(location)})`;
};

export const resolveSchemaLocationDetails = (schema = {}, locations = []) => {
  if (!schema?.wajib_gps) {
    return {
      mode: 'disabled',
      summary: 'GPS tidak wajib',
      locations: [],
      missingCount: 0,
    };
  }

  const normalizedLocations = normalizeLocationRows(locations);
  const selectedIds = normalizeIdArray(schema?.lokasi_gps_ids);

  if (selectedIds.length === 0) {
    const activeLocations = normalizedLocations.filter((location) => location.is_active !== false);

    return {
      mode: 'all_active',
      summary: activeLocations.length > 0
        ? `Semua lokasi aktif (${activeLocations.length} lokasi)`
        : 'Semua lokasi aktif',
      locations: activeLocations,
      missingCount: 0,
    };
  }

  const selectedLocations = normalizedLocations.filter((location) =>
    selectedIds.includes(Number(location.id))
  );

  return {
    mode: 'selected',
    summary: `${selectedIds.length} lokasi dipilih`,
    locations: selectedLocations,
    missingCount: Math.max(0, selectedIds.length - selectedLocations.length),
  };
};

export const getSchemaLocationPreviewText = (schema = {}, locations = [], maxPreview = 2) => {
  const resolution = resolveSchemaLocationDetails(schema, locations);
  if (!schema?.wajib_gps) {
    return '';
  }

  if (resolution.locations.length === 0) {
    return resolution.summary;
  }

  const previewItems = resolution.locations.slice(0, maxPreview).map(formatLocationDisplayLabel);
  const hiddenCount = Math.max(0, resolution.locations.length - previewItems.length);
  const suffix = [];

  if (hiddenCount > 0) {
    suffix.push(`+${hiddenCount} lainnya`);
  }

  if (resolution.missingCount > 0) {
    suffix.push(`${resolution.missingCount} referensi tidak ditemukan`);
  }

  return [previewItems.join(' | '), suffix.join(' | ')].filter(Boolean).join(' | ');
};

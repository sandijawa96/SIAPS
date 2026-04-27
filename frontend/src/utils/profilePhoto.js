import { getApiConfig } from '../config/api';

const ABSOLUTE_URL_PATTERN = /^https?:\/\//i;

const normalizeStoragePath = (value) => {
  const normalized = String(value).trim();
  if (!normalized) {
    return null;
  }

  const withoutApiPrefix = normalized.startsWith('/api/storage/')
    ? normalized.replace('/api/storage/', '/storage/')
    : normalized.startsWith('api/storage/')
      ? normalized.replace('api/storage/', 'storage/')
      : normalized;

  const withoutDuplicateStorage = withoutApiPrefix.startsWith('/storage/storage/')
    ? withoutApiPrefix.replace('/storage/storage/', '/storage/')
    : withoutApiPrefix.startsWith('storage/storage/')
      ? withoutApiPrefix.replace('storage/storage/', 'storage/')
      : withoutApiPrefix;

  if (withoutDuplicateStorage.startsWith('/storage/')) {
    return withoutDuplicateStorage;
  }

  if (withoutDuplicateStorage.startsWith('storage/')) {
    return `/${withoutDuplicateStorage}`;
  }

  if (withoutDuplicateStorage.startsWith('/public/')) {
    return `/storage/${withoutDuplicateStorage.replace('/public/', '')}`;
  }

  if (withoutDuplicateStorage.startsWith('public/')) {
    return `/storage/${withoutDuplicateStorage.replace('public/', '')}`;
  }

  return `/storage/${withoutDuplicateStorage.replace(/^\/+/, '')}`;
};

export const resolveProfilePhotoUrl = (value) => {
  if (!value) {
    return null;
  }

  const normalized = String(value).trim();
  if (!normalized) {
    return null;
  }

  if (normalized.startsWith('data:') || normalized.startsWith('blob:')) {
    return normalized;
  }

  if (ABSOLUTE_URL_PATTERN.test(normalized)) {
    try {
      const parsed = new URL(normalized);

      if (
        parsed.pathname.startsWith('/api/storage/') ||
        parsed.pathname.startsWith('/storage/storage/') ||
        parsed.pathname.startsWith('/public/')
      ) {
        parsed.pathname = normalizeStoragePath(parsed.pathname);
      }

      return parsed.toString();
    } catch (error) {
      return normalized;
    }
  }

  const storagePath = normalizeStoragePath(normalized);
  if (!storagePath) {
    return null;
  }

  const apiConfig = getApiConfig();
  const apiUrl = new URL(apiConfig.baseURL, window.location.origin);

  return `${apiUrl.origin}${storagePath}`;
};

export default resolveProfilePhotoUrl;

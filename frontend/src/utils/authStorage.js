const TOKEN_KEY = 'token';
const AUTH_TYPE_KEY = 'auth_type';

export const AUTH_STORAGE_KEYS = [TOKEN_KEY, AUTH_TYPE_KEY];

const clearLegacySessionStorage = () => {
  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(AUTH_TYPE_KEY);
};

export const getStoredToken = () => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (token) {
    clearLegacySessionStorage();
    return token;
  }

  const legacySessionToken = sessionStorage.getItem(TOKEN_KEY);
  if (!legacySessionToken) {
    return null;
  }

  localStorage.setItem(TOKEN_KEY, legacySessionToken);

  const legacyAuthType = sessionStorage.getItem(AUTH_TYPE_KEY);
  if (legacyAuthType) {
    localStorage.setItem(AUTH_TYPE_KEY, legacyAuthType);
  }

  clearLegacySessionStorage();

  return legacySessionToken;
};

export const getStoredAuthType = () => {
  const authType = localStorage.getItem(AUTH_TYPE_KEY);

  if (authType) {
    clearLegacySessionStorage();
    return authType;
  }

  const legacyAuthType = sessionStorage.getItem(AUTH_TYPE_KEY);
  if (!legacyAuthType) {
    return null;
  }

  localStorage.setItem(AUTH_TYPE_KEY, legacyAuthType);
  clearLegacySessionStorage();

  return legacyAuthType;
};

export const storeAuth = ({ token, authType = 'sanctum' }) => {
  clearLegacySessionStorage();

  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }

  if (authType) {
    localStorage.setItem(AUTH_TYPE_KEY, authType);
  } else {
    localStorage.removeItem(AUTH_TYPE_KEY);
  }
};

export const clearStoredAuth = () => {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(AUTH_TYPE_KEY);
  clearLegacySessionStorage();
};

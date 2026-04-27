// API Configuration
const API_CONFIG = {
  // Development - localhost
  development_localhost: {
    baseURL: 'http://localhost:8000/api',
    timeout: 20000 // Increase to 20 seconds for development
  },
  // Development - network IP
  development_network: {
    baseURL: 'http://192.168.100.105:8000/api',
    timeout: 20000 // Increase to 20 seconds for development
  },
  // Production - Menggunakan proxy lokal untuk menghindari CORS
  production: {
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    timeout: 15000
  },
  // Staging
  staging: {
    baseURL: 'https://staging-api.yourdomain.com/api',
    timeout: 15000
  }
};

// Get current environment
const getEnvironment = () => {
  // Prioritaskan variabel build Vite untuk deteksi production yang andal.
  if (import.meta.env.PROD) {
    return 'production';
  }

  // Fallback ke deteksi hostname untuk lingkungan development
  const hostname = window.location.hostname;
  
  // Jika diakses dari localhost atau 127.0.0.1
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return 'development_localhost';
  }
  
  // Jika diakses dari IP address di jaringan lokal
  if (hostname.startsWith('192.168.') || hostname.startsWith('10.') || hostname.startsWith('172.')) {
    return 'development_network';
  }
  
  if (hostname.includes('staging')) {
    return 'staging';
  }
  
  return 'production';
};


// Get API configuration for current environment
export const getApiConfig = () => {
  const env = getEnvironment();
  return API_CONFIG[env];
};

// Get full API URL
export const getApiUrl = (endpoint = '') => {
  const config = getApiConfig();
  return `${config.baseURL}${endpoint}`;
};

// Default export
export default {
  getApiConfig,
  getApiUrl,
  environment: getEnvironment()
};

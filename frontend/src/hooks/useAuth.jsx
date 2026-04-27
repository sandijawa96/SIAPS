import React, { useState, useEffect, createContext, useContext, useCallback, useRef } from 'react';
import { authAPI } from '../services/api';
import { deactivateWebPushNotifications, initializeWebPushNotifications } from '../services/pushNotificationService';
import { AUTH_STORAGE_KEYS, clearStoredAuth, getStoredToken, storeAuth } from '../utils/authStorage';

// Create Auth Context
const AuthContext = createContext();

// Auth Provider Component
export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [permissions, setPermissions] = useState([]);
  const [roles, setRoles] = useState([]);
  const [authorizationProfile, setAuthorizationProfile] = useState(null);
  const [featureLabels, setFeatureLabels] = useState([]);
  const activeTokenRef = useRef(getStoredToken());

  const uniqueStrings = useCallback((items) => {
    if (!Array.isArray(items)) {
      return [];
    }

    return Array.from(
      new Set(
        items
          .filter((item) => typeof item === 'string')
          .map((item) => item.trim())
          .filter((item) => item !== '')
      )
    );
  }, []);

  const extractRoleNames = useCallback((rolesPayload, singleRole = null) => {
    const roleNames = [];

    if (Array.isArray(rolesPayload)) {
      rolesPayload.forEach((roleItem) => {
        if (typeof roleItem === 'string' && roleItem.trim() !== '') {
          roleNames.push(roleItem.trim());
          return;
        }

        if (
          roleItem &&
          typeof roleItem === 'object' &&
          typeof roleItem.name === 'string' &&
          roleItem.name.trim() !== ''
        ) {
          roleNames.push(roleItem.name.trim());
        }
      });
    }

    if (typeof singleRole === 'string' && singleRole.trim() !== '') {
      roleNames.push(singleRole.trim());
    }

    return uniqueStrings(roleNames);
  }, [uniqueStrings]);

  const extractPermissionNames = useCallback((permissionsPayload) => {
    if (!Array.isArray(permissionsPayload)) {
      return [];
    }

    const permissionNames = [];

    permissionsPayload.forEach((permissionItem) => {
      if (typeof permissionItem === 'string' && permissionItem.trim() !== '') {
        permissionNames.push(permissionItem.trim());
        return;
      }

      if (
        permissionItem &&
        typeof permissionItem === 'object' &&
        typeof permissionItem.name === 'string' &&
        permissionItem.name.trim() !== ''
      ) {
        permissionNames.push(permissionItem.name.trim());
      }
    });

    return uniqueStrings(permissionNames);
  }, [uniqueStrings]);

  const extractFeatureLabels = useCallback((profilePayload) => {
    if (!profilePayload || typeof profilePayload !== 'object') {
      return [];
    }

    return uniqueStrings(profilePayload.features || []);
  }, [uniqueStrings]);

  const normalizeRoleName = useCallback((roleName) => {
    if (typeof roleName !== 'string') {
      return '';
    }

    return roleName
      .trim()
      .toLowerCase()
      .replace(/[_\s]+/g, ' ')
      .replace(/\s+(web|api)$/, '')
      .trim();
  }, []);

  const fetchAuthorizationProfile = useCallback(async () => {
    try {
      const response = await authAPI.myFeatureProfile();
      const profileData = response?.data?.data ?? response?.data ?? null;

      if (!profileData || typeof profileData !== 'object') {
        return null;
      }

      return profileData;
    } catch (error) {
      // Fallback to profile payload from /profile when this endpoint fails.
      return null;
    }
  }, []);

  const applyAuthorizationSnapshot = useCallback((userData, profileData = null) => {
    const fallbackRoles = extractRoleNames(userData?.roles, userData?.role);
    const fallbackPermissions = extractPermissionNames(userData?.permissions);

    const resolvedRoles = uniqueStrings(
      Array.isArray(profileData?.assigned_roles) ? profileData.assigned_roles : fallbackRoles
    );

    const resolvedPermissions = uniqueStrings(
      Array.isArray(profileData?.effective_permissions) ? profileData.effective_permissions : fallbackPermissions
    );

    setUser(userData);
    setRoles(resolvedRoles);
    setPermissions(resolvedPermissions);
    setAuthorizationProfile(profileData);
    setFeatureLabels(extractFeatureLabels(profileData));
  }, [extractFeatureLabels, extractPermissionNames, extractRoleNames, uniqueStrings]);

  const clearAuthState = useCallback(() => {
    setUser(null);
    setPermissions([]);
    setRoles([]);
    setAuthorizationProfile(null);
    setFeatureLabels([]);
  }, []);

  const redirectToLogin = useCallback(() => {
    if (window.location.pathname !== '/login') {
      window.location.href = '/login';
    }
  }, []);

  const syncAuthFromStorage = useCallback(async (options = {}) => {
    const { redirectOnMissing = false } = options;
    const token = getStoredToken();

    if (!token) {
      activeTokenRef.current = null;
      clearAuthState();

      if (redirectOnMissing) {
        redirectToLogin();
      }

      return;
    }

    try {
      const response = await authAPI.me();
      const responseData = response.data;

      let userData;
      if (responseData.data) {
        userData = responseData.data;
      } else {
        userData = responseData.user || responseData;
      }

      if (token !== getStoredToken()) {
        return;
      }

      const profileData = await fetchAuthorizationProfile();
      if (token !== getStoredToken()) {
        return;
      }

      activeTokenRef.current = token;
      applyAuthorizationSnapshot(userData, profileData);

      if (window.location.pathname === '/login') {
        window.location.href = '/';
      }
    } catch (error) {
      activeTokenRef.current = null;
      clearStoredAuth();
      clearAuthState();
      redirectToLogin();
    }
  }, [applyAuthorizationSnapshot, clearAuthState, fetchAuthorizationProfile, redirectToLogin]);

  // Load user data on mount
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        await syncAuthFromStorage();
      } catch (error) {
        // Clear invalid token
        activeTokenRef.current = null;
        clearStoredAuth();
        clearAuthState();
      } finally {
        setIsLoading(false);
      }
    };

    initializeAuth();
  }, [clearAuthState, syncAuthFromStorage]);

  useEffect(() => {
    const handleStorage = (event) => {
      if (event.storageArea !== window.localStorage) {
        return;
      }

      if (event.key !== null && !AUTH_STORAGE_KEYS.includes(event.key)) {
        return;
      }

      const nextToken = getStoredToken();
      if (nextToken === activeTokenRef.current) {
        return;
      }

      syncAuthFromStorage({ redirectOnMissing: true });
    };

    window.addEventListener('storage', handleStorage);

    return () => {
      window.removeEventListener('storage', handleStorage);
    };
  }, [syncAuthFromStorage]);

  useEffect(() => {
    if (!user) {
      return;
    }

    initializeWebPushNotifications({ userId: user.id }).catch(() => {
      // non-blocking: inbox notifications remain available without push registration
    });
  }, [user]);

  const login = async (credentials) => {
    try {
      const response = await authAPI.loginWeb(credentials);
      const responseData = response.data;

      // Handle different API response structures
      let token;
      let userData;

      if (responseData.data) {
        // Structure: { data: { access_token, user } } - Sanctum web login
        token = responseData.data.access_token || responseData.data.token;
        userData = responseData.data.user;
      } else if (responseData.access_token) {
        // Structure: { access_token, user }
        token = responseData.access_token;
        userData = responseData.user;
      } else if (responseData.token) {
        // Structure: { token, user }
        token = responseData.token;
        userData = responseData.user;
      } else {
        // Fallback: assume the response itself contains user data
        userData = responseData;
        token = responseData.token || responseData.access_token;
      }

      if (token) {
        storeAuth({
          token,
          authType: 'sanctum',
        });
        activeTokenRef.current = token;
      }

      const profileData = await fetchAuthorizationProfile();
      applyAuthorizationSnapshot(userData, profileData);

      return userData;
    } catch (error) {
      throw error;
    }
  };

  const loginSiswa = async (credentials) => {
    try {
      const response = await authAPI.loginWebSiswa(credentials);
      const responseData = response.data;

      // Handle different API response structures
      let token;
      let userData;

      if (responseData.data) {
        // Structure: { data: { access_token, user } }
        token = responseData.data.access_token || responseData.data.token;
        userData = responseData.data.user;
      } else if (responseData.access_token) {
        // Structure: { access_token, user }
        token = responseData.access_token;
        userData = responseData.user;
      } else if (responseData.token) {
        // Structure: { token, user }
        token = responseData.token;
        userData = responseData.user;
      } else {
        // Fallback: assume the response itself contains user data
        userData = responseData;
        token = responseData.token || responseData.access_token;
      }

      if (token) {
        storeAuth({
          token,
          authType: 'sanctum',
        });
        activeTokenRef.current = token;
      }

      const profileData = await fetchAuthorizationProfile();
      applyAuthorizationSnapshot(userData, profileData);

      return userData;
    } catch (error) {
      throw error;
    }
  };

  const refreshAuthorizationProfile = useCallback(async () => {
    if (!user) {
      return null;
    }

    const profileData = await fetchAuthorizationProfile();
    if (!profileData) {
      return null;
    }

    const fallbackRoles = extractRoleNames(user?.roles, user?.role);
    const fallbackPermissions = extractPermissionNames(user?.permissions);

    setRoles(
      uniqueStrings(
        Array.isArray(profileData.assigned_roles) ? profileData.assigned_roles : fallbackRoles
      )
    );
    setPermissions(
      uniqueStrings(
        Array.isArray(profileData.effective_permissions)
          ? profileData.effective_permissions
          : fallbackPermissions
      )
    );
    setAuthorizationProfile(profileData);
    setFeatureLabels(extractFeatureLabels(profileData));

    return profileData;
  }, [extractFeatureLabels, extractPermissionNames, extractRoleNames, fetchAuthorizationProfile, uniqueStrings, user]);

  const logout = useCallback(async () => {
    try {
      await deactivateWebPushNotifications();
    } catch (error) {
      // no-op
    }

    try {
      await authAPI.logout();
    } catch (error) {
      // no-op
    } finally {
      activeTokenRef.current = null;
      clearAuthState();
      clearStoredAuth();
      redirectToLogin();
    }
  }, [clearAuthState, redirectToLogin]);

  const hasPermission = useCallback((permission) => {
    if (!permission) return true;

    // Super Admin memiliki semua permission
    if (roles.some((roleName) => normalizeRoleName(roleName) === 'super admin')) {
      return true;
    }

    return permissions.includes(permission);
  }, [normalizeRoleName, permissions, roles]);

  const hasAnyPermission = useCallback((requiredPermissions = []) => {
    if (!Array.isArray(requiredPermissions) || requiredPermissions.length === 0) {
      return true;
    }

    return requiredPermissions.some((permission) => hasPermission(permission));
  }, [hasPermission]);

  const hasRole = useCallback((role) => {
    if (!role) return true;
    const normalizedRequiredRole = normalizeRoleName(role);
    return roles.some((userRoleName) => normalizeRoleName(userRoleName) === normalizedRequiredRole);
  }, [normalizeRoleName, roles]);

  const hasAnyRole = useCallback((requiredRoles = []) => {
    if (!Array.isArray(requiredRoles) || requiredRoles.length === 0) {
      return true;
    }

    return requiredRoles.some((roleName) => hasRole(roleName));
  }, [hasRole]);

  const hasFeature = useCallback((featureLabel) => {
    if (!featureLabel) {
      return true;
    }

    const normalized = String(featureLabel).trim().toLowerCase();
    if (normalized === '') {
      return true;
    }

    return featureLabels.some(
      (feature) => String(feature).trim().toLowerCase() === normalized
    );
  }, [featureLabels]);

  const value = {
    user,
    isLoading,
    token: getStoredToken(),
    roles,
    permissions,
    authorizationProfile,
    featureLabels,
    login,
    loginSiswa,
    logout,
    hasPermission,
    hasAnyPermission,
    hasRole,
    hasAnyRole,
    hasFeature,
    refreshAuthorizationProfile,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

// Custom hook to use auth context
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

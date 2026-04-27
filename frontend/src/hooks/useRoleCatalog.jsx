import { useCallback, useState } from 'react';
import { useSnackbar } from 'notistack';
import roleService from '../services/roleService';

export const useRoleCatalog = () => {
  const [catalogState, setCatalogState] = useState({
    primaryRoles: [],
    allSubRoles: [],
    availableSubRoles: [],
    loading: false,
    error: null,
  });
  const { enqueueSnackbar } = useSnackbar();

  const updateCatalogState = useCallback((updates) => {
    setCatalogState((previous) => ({ ...previous, ...updates }));
  }, []);

  const loadRoleCatalog = useCallback(async () => {
    updateCatalogState({ loading: true, error: null });

    try {
      const rolesResponse = await roleService.getAvailableRoles();

      if (!rolesResponse?.success) {
        throw new Error(rolesResponse?.error || 'Gagal memuat data role');
      }

      const primaryRoles = Array.isArray(rolesResponse.data?.primaryRoles)
        ? rolesResponse.data.primaryRoles
        : [];
      const subRoles = Array.isArray(rolesResponse.data?.subRoles)
        ? rolesResponse.data.subRoles
        : [];

      const filteredPrimaryRoles = primaryRoles.filter(
        (role) => role?.is_active && role?.name !== 'Siswa' && role?.name !== 'Super_Admin'
      );
      const filteredSubRoles = subRoles.filter((role) => role?.is_active);

      updateCatalogState({
        primaryRoles: filteredPrimaryRoles,
        allSubRoles: filteredSubRoles,
        availableSubRoles: [],
        loading: false,
        error: null,
      });

      return {
        primaryRoles: filteredPrimaryRoles,
        allSubRoles: filteredSubRoles,
      };
    } catch (error) {
      updateCatalogState({
        primaryRoles: [],
        allSubRoles: [],
        availableSubRoles: [],
        loading: false,
        error: error?.message || 'Gagal memuat data role',
      });

      enqueueSnackbar(
        error?.response?.data?.message || error?.message || 'Gagal memuat data role',
        { variant: 'error' }
      );

      return {
        primaryRoles: [],
        allSubRoles: [],
      };
    }
  }, [enqueueSnackbar, updateCatalogState]);

  const fetchSubRoles = useCallback(async (roleId) => {
    if (!roleId) {
      updateCatalogState({ availableSubRoles: [] });
      return [];
    }

    try {
      const response = await roleService.getSubRoles(roleId);
      const subRoles = response?.success && Array.isArray(response.data) ? response.data : [];
      const activeSubRoles = subRoles.filter((role) => role?.is_active);

      updateCatalogState({ availableSubRoles: activeSubRoles });
      return activeSubRoles;
    } catch (error) {
      updateCatalogState({ availableSubRoles: [] });

      if (error?.response?.status !== 404) {
        enqueueSnackbar(error?.response?.data?.message || 'Gagal memuat sub role', {
          variant: 'warning',
        });
      }

      return [];
    }
  }, [enqueueSnackbar, updateCatalogState]);

  const setAvailableSubRoles = useCallback((subRoles) => {
    updateCatalogState({
      availableSubRoles: Array.isArray(subRoles) ? subRoles : [],
    });
  }, [updateCatalogState]);

  return {
    catalogState,
    updateCatalogState,
    loadRoleCatalog,
    fetchSubRoles,
    setAvailableSubRoles,
  };
};

export default useRoleCatalog;

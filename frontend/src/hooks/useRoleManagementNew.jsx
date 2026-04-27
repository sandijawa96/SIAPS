import { useCallback, useEffect } from 'react';
import useRoleCatalog from './useRoleCatalog';

export const useRoleManagementNew = () => {
  const {
    catalogState: state,
    updateCatalogState: updateState,
    loadRoleCatalog: loadRoles,
    fetchSubRoles,
    setAvailableSubRoles: updateAvailableSubRoles,
  } = useRoleCatalog();

  // Get role by ID from all loaded roles
  const getRoleById = useCallback((roleId) => {
    const allRoles = [...state.primaryRoles, ...state.availableSubRoles];
    return allRoles.find(role => role.id === parseInt(roleId));
  }, [state.primaryRoles, state.availableSubRoles]);

  // Get role by name from all loaded roles
  const getRoleByName = useCallback((roleName) => {
    const allRoles = [...state.primaryRoles, ...state.availableSubRoles];
    return allRoles.find(role => role.name === roleName);
  }, [state.primaryRoles, state.availableSubRoles]);

  // Check if role is primary role (exists in primaryRoles array)
  const isPrimaryRole = useCallback((roleName) => {
    return state.primaryRoles.some(role => role.name === roleName);
  }, [state.primaryRoles]);

  // Check if role is sub role (exists in availableSubRoles array)
  const isSubRole = useCallback((roleName) => {
    return state.availableSubRoles.some(role => role.name === roleName);
  }, [state.availableSubRoles]);

  // Get formatted roles for display
  const getFormattedRoles = useCallback(() => {
    return {
      primary: state.primaryRoles.map(role => ({
        ...role,
        label: role.display_name || role.name,
        value: role.name,
        is_primary: true
      })),
      sub: state.availableSubRoles.map(role => ({
        ...role,
        label: role.display_name || role.name,
        value: role.name,
        is_primary: false
      }))
    };
  }, [state.primaryRoles, state.availableSubRoles]);

  // Auto-load roles on mount
  useEffect(() => {
    loadRoles();
  }, [loadRoles]);

  return {
    // State
    primaryRoles: state.primaryRoles,
    allSubRoles: state.allSubRoles,
    availableSubRoles: state.availableSubRoles,
    loading: state.loading,
    error: state.error,
    
    // Actions
    loadRoles,
    fetchSubRoles,
    updateAvailableSubRoles,
    
    // Utilities
    getRoleById,
    getRoleByName,
    isPrimaryRole,
    isSubRole,
    getFormattedRoles,
    
    // State updater
    updateState
  };
};

export default useRoleManagementNew;

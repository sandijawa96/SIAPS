import React, { useState, useEffect } from 'react';
import { X, Shield, Settings, AlertCircle } from 'lucide-react';
import PermissionSelector from '../roles/PermissionSelector';

const RoleFormModal = ({ 
  isOpen, 
  onClose, 
  onSubmit, 
  selectedRole,
  roles = [],
  permissions = {},
  submitting = false 
}) => {
  const [formErrors, setFormErrors] = useState({});
  const [formData, setFormData] = useState({
    name: '',
    display_name: '',
    description: '',
    level: 0,
    is_active: true,
    is_primary: false,
    parent_role_id: null,
    permissions: []
  });

  // Update formData when selectedRole changes
  useEffect(() => {
    if (selectedRole) {
      setFormData({
        name: selectedRole.name || '',
        display_name: selectedRole.display_name || '',
        description: selectedRole.description || '',
        level: selectedRole.level || 0,
        is_active: selectedRole.is_active ?? true,
        is_primary: selectedRole.is_primary || false,
        parent_role_id: selectedRole.parent_role_id || null,
        permissions: selectedRole.permissions ? selectedRole.permissions.map(p => p.name) : []
      });
    } else {
      // Reset form for new role
      setFormData({
        name: '',
        display_name: '',
        description: '',
        level: 0,
        is_active: true,
        is_primary: false,
        parent_role_id: null,
        permissions: []
      });
    }
    // Clear errors when role changes
    setFormErrors({});
  }, [selectedRole]);

  const handleInputChange = (e) => {
    const { name, value, checked, type } = e.target;
    if (type === 'checkbox') {
      setFormData(prev => ({
        ...prev,
        [name]: checked,
        parent_role_id: name === 'is_primary' && checked ? null : prev.parent_role_id
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }));
    }
  };

  const handlePermissionChange = (permissionName) => {
    setFormData(prev => {
      const currentPermissions = prev.permissions || [];
      return {
        ...prev,
        permissions: currentPermissions.includes(permissionName)
          ? currentPermissions.filter(p => p !== permissionName)
          : [...currentPermissions, permissionName]
      };
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validasi
    const errors = {};
    if (!formData.name) errors.name = 'Nama role wajib diisi';
    if (!formData.display_name) errors.display_name = 'Nama tampilan wajib diisi';
    if (!formData.is_primary && !formData.parent_role_id) {
      errors.parent_role_id = 'Sub role harus memiliki primary role';
    }
    
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    const result = await onSubmit(formData);
    if (!result.success && result.errors) {
      setFormErrors(result.errors);
    } else if (result.success) {
      onClose();
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {/* Backdrop */}
        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
          <div className="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>
        </div>
        
        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        {/* Modal */}
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-purple-600 to-indigo-700 px-6 py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                  <Shield className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">
                    {selectedRole ? 'Edit Role' : 'Tambah Role Baru'}
                  </h3>
                  <p className="text-purple-100 text-sm">Lengkapi data role dengan benar</p>
                </div>
              </div>
              <button
                onClick={onClose}
                className="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          {/* Content */}
          <div className="px-6 py-6 max-h-96 overflow-y-auto">
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Basic Info */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Nama Role*
                  </label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 ${
                      formErrors.name ? 'border-red-500' : ''
                    }`}
                    placeholder="Contoh: Super_Admin"
                  />
                  {formErrors.name && (
                    <p className="mt-1 text-sm text-red-500 flex items-center">
                      <AlertCircle className="w-4 h-4 mr-1" />
                      {formErrors.name}
                    </p>
                  )}
                  <p className="text-xs text-gray-500">Gunakan underscore untuk spasi</p>
                </div>

                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Nama Tampilan*
                  </label>
                  <input
                    type="text"
                    name="display_name"
                    value={formData.display_name}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 ${
                      formErrors.display_name ? 'border-red-500' : ''
                    }`}
                    placeholder="Contoh: Super Admin"
                  />
                  {formErrors.display_name && (
                    <p className="mt-1 text-sm text-red-500 flex items-center">
                      <AlertCircle className="w-4 h-4 mr-1" />
                      {formErrors.display_name}
                    </p>
                  )}
                </div>

                <div className="md:col-span-2 space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Deskripsi
                  </label>
                  <textarea
                    name="description"
                    value={formData.description}
                    onChange={handleInputChange}
                    rows="3"
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                    placeholder="Deskripsi role..."
                  />
                </div>

                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Level*
                  </label>
                  <input
                    type="number"
                    name="level"
                    value={formData.level}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                    min="0"
                  />
                  <p className="text-xs text-gray-500">Semakin tinggi level, semakin tinggi otoritasnya</p>
                </div>
              </div>

              {/* Role Settings */}
              <div className="space-y-4">
                <h4 className="text-sm font-semibold text-gray-700">
                  Pengaturan Role
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <label className="relative cursor-pointer group">
                    <input
                      type="checkbox"
                      name="is_primary"
                      checked={formData.is_primary}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      formData.is_primary
                        ? 'border-purple-500 bg-purple-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-purple-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          formData.is_primary
                            ? 'border-purple-500 bg-purple-500'
                            : 'border-gray-300'
                        }`}>
                          {formData.is_primary && (
                            <div className="w-2 h-2 bg-white rounded-full mx-auto mt-1"></div>
                          )}
                        </div>
                        <div>
                          <div className="font-semibold text-gray-900">Primary Role</div>
                          <div className="text-sm text-gray-500">Role utama</div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <label className="relative cursor-pointer group">
                    <input
                      type="checkbox"
                      name="is_active"
                      checked={formData.is_active}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      formData.is_active
                        ? 'border-green-500 bg-green-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-green-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          formData.is_active
                            ? 'border-green-500 bg-green-500'
                            : 'border-gray-300'
                        }`}>
                          {formData.is_active && (
                            <div className="w-2 h-2 bg-white rounded-full mx-auto mt-1"></div>
                          )}
                        </div>
                        <div>
                          <div className="font-semibold text-gray-900">Role Aktif</div>
                          <div className="text-sm text-gray-500">Status aktif</div>
                        </div>
                      </div>
                    </div>
                  </label>

                  {!formData.is_primary && (
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Primary Role*
                      </label>
                      <select
                        name="parent_role_id"
                        value={formData.parent_role_id || ''}
                        onChange={handleInputChange}
                        className={`w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 ${
                          formErrors.parent_role_id ? 'border-red-500' : ''
                        }`}
                      >
                        <option value="">Pilih Primary Role</option>
                        {roles
                          .filter(role => role.is_primary)
                          .map((role) => (
                            <option key={role.id} value={role.id}>
                              {role.display_name}
                            </option>
                          ))}
                      </select>
                      {formErrors.parent_role_id && (
                        <p className="mt-1 text-sm text-red-500 flex items-center">
                          <AlertCircle className="w-4 h-4 mr-1" />
                          {formErrors.parent_role_id}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Permissions */}
              <div className="space-y-4">
                <PermissionSelector
                  permissions={permissions}
                  selectedPermissions={formData.permissions}
                  onPermissionChange={handlePermissionChange}
                />
              </div>
            </form>
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting}
              className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
            >
              {submitting ? (
                <span className="flex items-center">
                  <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Menyimpan...
                </span>
              ) : (
                selectedRole ? 'Update Role' : 'Simpan Role'
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RoleFormModal;

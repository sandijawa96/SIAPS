import React, { useState, useEffect } from 'react';
import { Shield, Plus, Edit, Trash2, Key, Search, Users, Eye, AlertCircle, CheckCircle } from 'lucide-react';
import roleService from '../../services/roleService';
import permissionService from '../../services/permissionService';

const RoleTab = () => {
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [groupedPermissions, setGroupedPermissions] = useState({});
  const [showModal, setShowModal] = useState(false);
  const [showPreviewModal, setShowPreviewModal] = useState(false);
  const [editingRole, setEditingRole] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [infoMessage, setInfoMessage] = useState('');
  const [previewRoles, setPreviewRoles] = useState([]);
  const [effectivePermissions, setEffectivePermissions] = useState({});
  const [formData, setFormData] = useState({
    name: '',
    display_name: '',
    description: '',
    level: 0,
    is_active: true,
    permissions: []
  });

  useEffect(() => {
    fetchRoles();
    fetchPermissions();
  }, []);

  const fetchRoles = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await roleService.getAll();
      if (response.success) {
        setRoles(response.data);
        console.log('Fetched roles:', response.data); // Debug log
      } else {
        setRoles([]);
        setError(response.error || 'Gagal memuat data role');
      }
    } catch (error) {
      console.error('Error fetching roles:', error);
      setRoles([]);
      setError('Gagal memuat data role');
    } finally {
      setLoading(false);
    }
  };

  const fetchPermissions = async () => {
    try {
      setError('');
      const response = await permissionService.getByModule();
      if (response.success) {
        setGroupedPermissions(response.data);
        // Flatten permissions for easier access
        const allPermissions = Object.values(response.data).flat();
        setPermissions(allPermissions);
        console.log('Fetched permissions:', response.data); // Debug log
      } else {
        setGroupedPermissions({});
        setPermissions([]);
        setError(response.error || 'Gagal memuat data permission');
      }
    } catch (error) {
      console.error('Error fetching permissions:', error);
      setGroupedPermissions({});
      setPermissions([]);
      setError('Gagal memuat data permission');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    setInfoMessage('');

    try {
      const payload = {
        name: formData.name,
        display_name: formData.display_name || formData.name,
        description: formData.description || null,
        level: Number(formData.level || 0),
        is_active: Boolean(formData.is_active),
        permissions: formData.permissions,
      };

      if (editingRole) {
        await roleService.updateRole(editingRole.id, payload);
        setInfoMessage('Role berhasil diperbarui');
      } else {
        await roleService.createRole(payload);
        setInfoMessage('Role berhasil dibuat');
      }

      await fetchRoles();
      setShowModal(false);
      resetForm();
    } catch (err) {
      setError(err?.message || 'Gagal menyimpan role');
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = (role) => {
    setEditingRole(role);
    setFormData({
      name: role.name,
      display_name: role.display_name || role.name,
      description: role.description,
      permissions: role.permissions ? role.permissions.map((p) => p.name) : []
    });
    setShowModal(true);
  };

  const handleDelete = async (roleId) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus role ini?')) {
      setError('');
      setInfoMessage('');
      try {
        await roleService.deleteRole(roleId);
        setInfoMessage('Role berhasil dihapus');
        await fetchRoles();
      } catch (err) {
        setError(err?.message || 'Gagal menghapus role');
      }
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      display_name: '',
      description: '',
      level: 0,
      is_active: true,
      permissions: [],
    });
    setEditingRole(null);
  };

  const handlePermissionChange = (permissionName) => {
    const updatedPermissions = formData.permissions.includes(permissionName)
      ? formData.permissions.filter((p) => p !== permissionName)
      : [...formData.permissions, permissionName];
    
    setFormData({ ...formData, permissions: updatedPermissions });
  };

  const filteredRoles = roles.filter(role =>
    role.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    role.description?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div>
      {error && (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}
      {infoMessage && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {infoMessage}
        </div>
      )}

      {/* Header Actions */}
      <div className="flex justify-between items-center mb-6">
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Search className="h-5 w-5 text-gray-400" />
          </div>
          <input
            type="text"
            placeholder="Cari role..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
          />
        </div>
        <button
          onClick={() => setShowModal(true)}
          className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          <Plus className="w-4 h-4 mr-2" />
          Tambah Role
        </button>
      </div>

      {/* Loading State */}
      {loading && (
        <div className="flex justify-center items-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600">Memuat data role...</span>
        </div>
      )}

      {/* Empty State */}
      {!loading && roles.length === 0 && (
        <div className="bg-white shadow overflow-hidden sm:rounded-md">
          <div className="px-4 py-8 text-center">
            <Shield className="mx-auto h-12 w-12 text-gray-400" />
            <h3 className="mt-2 text-sm font-medium text-gray-900">Tidak ada role</h3>
            <p className="mt-1 text-sm text-gray-500">
              Belum ada role yang dibuat. Mulai dengan menambahkan role baru.
            </p>
            <div className="mt-6">
              <button
                onClick={() => setShowModal(true)}
                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
              >
                <Plus className="w-4 h-4 mr-2" />
                Tambah Role Pertama
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Roles Table */}
      {!loading && roles.length > 0 && (
        <div className="bg-white shadow overflow-hidden sm:rounded-md">
          <ul className="divide-y divide-gray-200">
            {filteredRoles.map((role) => (
              <li key={role.id}>
                <div className="px-4 py-4 sm:px-6">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="flex-shrink-0">
                        <Shield className="h-10 w-10 text-blue-500" />
                      </div>
                      <div className="ml-4">
                        <div className="flex items-center">
                          <p className="text-sm font-medium text-blue-600 truncate">
                            {role.display_name || role.name}
                          </p>
                          <div className="ml-2 flex-shrink-0 flex">
                            <p className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                              {role.users_count || 0} pengguna
                            </p>
                          </div>
                        </div>
                        <div className="mt-2 flex">
                          <div className="flex items-center text-sm text-gray-500">
                            <p>{role.description || 'Tidak ada deskripsi'}</p>
                          </div>
                        </div>
                        <div className="mt-2 flex items-center text-sm text-gray-500">
                          <Key className="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                          <p>{role.permissions?.length || 0} permission</p>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => handleEdit(role)}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDelete(role.id)}
                        className="text-red-600 hover:text-red-900"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                {editingRole ? 'Edit Role' : 'Tambah Role Baru'}
              </h3>
              <form onSubmit={handleSubmit}>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Nama Role
                    </label>
                    <input
                      type="text"
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Deskripsi
                    </label>
                    <textarea
                      rows={3}
                      value={formData.description}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-3">
                      Permissions
                    </label>
                    <div className="space-y-4 max-h-60 overflow-y-auto">
                      {Object.entries(groupedPermissions).map(([category, perms]) => (
                        <div key={category}>
                          <h4 className="text-sm font-medium text-gray-900 mb-2">{category}</h4>
                          <div className="space-y-2 ml-4">
                            {perms.map((permission) => (
                              <label key={permission.id} className="flex items-center">
                                <input
                                  type="checkbox"
                                  checked={formData.permissions.includes(permission.name)}
                                  onChange={() => handlePermissionChange(permission.name)}
                                  className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <span className="ml-2 text-sm text-gray-700">{permission.name}</span>
                              </label>
                            ))}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="mt-6 flex justify-end space-x-3">
                  <button
                    type="button"
                    onClick={() => {
                      setShowModal(false);
                      resetForm();
                    }}
                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={submitting}
                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                  >
                    {submitting ? 'Menyimpan...' : (editingRole ? 'Update' : 'Simpan')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Preview Modal for Multiple Roles */}
      {showPreviewModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                Preview Kombinasi Role
              </h3>
              
              <div className="space-y-4">
                {/* Selected Roles */}
                <div>
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Role yang Dipilih:</h4>
                  <div className="flex flex-wrap gap-2">
                    {previewRoles.map(role => (
                      <span key={role.id} className="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                        {role.display_name || role.name}
                      </span>
                    ))}
                  </div>
                </div>

                {/* Effective Permissions */}
                <div>
                  <h4 className="text-sm font-medium text-gray-700 mb-2">
                    Permission Efektif:
                    <span className="ml-2 text-xs text-gray-500">
                      (Total: {Object.values(effectivePermissions).flat().length})
                    </span>
                  </h4>
                  <div className="space-y-4 max-h-96 overflow-y-auto">
                    {Object.entries(effectivePermissions).map(([module, permissions]) => (
                      <div key={module} className="border rounded-lg p-3">
                        <h5 className="text-sm font-medium text-gray-900 mb-2">
                          {module}
                          <span className="ml-2 text-xs text-gray-500">
                            ({permissions.length})
                          </span>
                        </h5>
                        <div className="grid grid-cols-2 gap-2">
                          {permissions.map(permission => (
                            <div key={permission.id} className="flex items-center text-sm text-gray-600">
                              <CheckCircle className="h-4 w-4 text-green-500 mr-2" />
                              {permission.display_name}
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              <div className="mt-6 flex justify-end">
                <button
                  type="button"
                  onClick={() => setShowPreviewModal(false)}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default RoleTab;

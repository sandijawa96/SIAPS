import React, { useState, useEffect } from 'react';
import { Link2, Plus, Edit, Trash2, Shield, Users } from 'lucide-react';

const JabatanRoleMapping = () => {
  const [mappings, setMappings] = useState([]);
  const [jabatanOptions, setJabatanOptions] = useState([]);
  const [roleOptions, setRoleOptions] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [editingMapping, setEditingMapping] = useState(null);
  const [formData, setFormData] = useState({
    jabatan_id: '',
    sub_jabatan_id: '',
    role_id: '',
    is_default: false
  });

  useEffect(() => {
    fetchMappings();
    fetchJabatan();
    fetchRoles();
  }, []);

  const fetchMappings = () => {
    // TODO: Fetch from API
    setMappings([
      {
        id: 1,
        jabatan: { id: 1, nama: 'Guru' },
        sub_jabatan: { id: 1, nama: 'Wali Kelas' },
        role: { id: 2, name: 'Admin Sekolah' },
        is_default: true
      },
      {
        id: 2,
        jabatan: { id: 1, nama: 'Guru' },
        sub_jabatan: { id: 2, nama: 'Guru Mata Pelajaran' },
        role: { id: 3, name: 'Guru' },
        is_default: true
      },
      {
        id: 3,
        jabatan: { id: 3, nama: 'Pimpinan' },
        sub_jabatan: { id: 6, nama: 'Kepala Sekolah' },
        role: { id: 1, name: 'Super Admin' },
        is_default: true
      },
      {
        id: 4,
        jabatan: { id: 2, nama: 'Staff' },
        sub_jabatan: { id: 4, nama: 'Staff TU' },
        role: { id: 2, name: 'Admin Sekolah' },
        is_default: true
      }
    ]);
  };

  const fetchJabatan = () => {
    // Data dari JabatanTab.jsx
    setJabatanOptions([
      {
        id: 1,
        nama: 'Guru',
        sub_jabatan: [
          { id: 1, nama: 'Wali Kelas' },
          { id: 2, nama: 'Guru Mata Pelajaran' },
          { id: 3, nama: 'Guru BK' }
        ]
      },
      {
        id: 2,
        nama: 'Staff',
        sub_jabatan: [
          { id: 4, nama: 'Staff TU' },
          { id: 5, nama: 'Staff Perpustakaan' }
        ]
      },
      {
        id: 3,
        nama: 'Pimpinan',
        sub_jabatan: [
          { id: 6, nama: 'Kepala Sekolah' },
          { id: 7, nama: 'Wakil Kepala Sekolah' }
        ]
      }
    ]);
  };

  const fetchRoles = () => {
    // Data dari RoleTab.jsx
    setRoleOptions([
      { id: 1, name: 'Super Admin', description: 'Akses penuh ke semua fitur sistem' },
      { id: 2, name: 'Admin Sekolah', description: 'Mengelola data sekolah dan absensi' },
      { id: 3, name: 'Guru', description: 'Akses untuk guru dan pengajar' }
    ]);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    const selectedJabatan = jabatanOptions.find(j => j.id === parseInt(formData.jabatan_id));
    const selectedSubJabatan = selectedJabatan?.sub_jabatan.find(s => s.id === parseInt(formData.sub_jabatan_id));
    const selectedRole = roleOptions.find(r => r.id === parseInt(formData.role_id));

    if (editingMapping) {
      // Update existing mapping
      setMappings(mappings.map(m => 
        m.id === editingMapping.id 
          ? {
              ...m,
              jabatan: selectedJabatan,
              sub_jabatan: selectedSubJabatan,
              role: selectedRole,
              is_default: formData.is_default
            }
          : m
      ));
    } else {
      // Add new mapping
      const newMapping = {
        id: Math.max(...mappings.map(m => m.id), 0) + 1,
        jabatan: selectedJabatan,
        sub_jabatan: selectedSubJabatan,
        role: selectedRole,
        is_default: formData.is_default
      };
      setMappings([...mappings, newMapping]);
    }

    setShowModal(false);
    resetForm();
  };

  const handleEdit = (mapping) => {
    setEditingMapping(mapping);
    setFormData({
      jabatan_id: mapping.jabatan.id.toString(),
      sub_jabatan_id: mapping.sub_jabatan.id.toString(),
      role_id: mapping.role.id.toString(),
      is_default: mapping.is_default
    });
    setShowModal(true);
  };

  const handleDelete = (mappingId) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus mapping ini?')) {
      setMappings(mappings.filter(m => m.id !== mappingId));
    }
  };

  const resetForm = () => {
    setFormData({
      jabatan_id: '',
      sub_jabatan_id: '',
      role_id: '',
      is_default: false
    });
    setEditingMapping(null);
  };

  const getSubJabatanOptions = () => {
    const selectedJabatan = jabatanOptions.find(j => j.id === parseInt(formData.jabatan_id));
    return selectedJabatan?.sub_jabatan || [];
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-lg font-medium text-gray-900">Mapping Jabatan & Role</h2>
          <p className="text-sm text-gray-500">Tentukan role default untuk setiap jabatan dan sub jabatan</p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
        >
          <Plus className="w-4 h-4 mr-2" />
          Tambah Mapping
        </button>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <ul className="divide-y divide-gray-200">
          {mappings.map((mapping) => (
            <li key={mapping.id} className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="flex-shrink-0">
                    <Link2 className="h-8 w-8 text-blue-500" />
                  </div>
                  <div>
                    <div className="flex items-center space-x-2">
                      <Users className="h-4 w-4 text-gray-400" />
                      <span className="text-sm font-medium text-gray-900">
                        {mapping.jabatan.nama}
                      </span>
                      <span className="text-gray-400">→</span>
                      <span className="text-sm text-gray-600">
                        {mapping.sub_jabatan.nama}
                      </span>
                    </div>
                    <div className="flex items-center space-x-2 mt-1">
                      <Shield className="h-4 w-4 text-gray-400" />
                      <span className="text-sm text-gray-600">
                        Role: {mapping.role.name}
                      </span>
                      {mapping.is_default && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                          Default
                        </span>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  <button
                    onClick={() => handleEdit(mapping)}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    <Edit className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(mapping.id)}
                    className="text-red-600 hover:text-red-900"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                {editingMapping ? 'Edit Mapping' : 'Tambah Mapping Baru'}
              </h3>
              <form onSubmit={handleSubmit}>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Jabatan
                    </label>
                    <select
                      required
                      value={formData.jabatan_id}
                      onChange={(e) => setFormData({ 
                        ...formData, 
                        jabatan_id: e.target.value,
                        sub_jabatan_id: '' // Reset sub jabatan when jabatan changes
                      })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                      <option value="">Pilih Jabatan</option>
                      {jabatanOptions.map(jabatan => (
                        <option key={jabatan.id} value={jabatan.id}>
                          {jabatan.nama}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Sub Jabatan
                    </label>
                    <select
                      required
                      value={formData.sub_jabatan_id}
                      onChange={(e) => setFormData({ ...formData, sub_jabatan_id: e.target.value })}
                      disabled={!formData.jabatan_id}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:bg-gray-100"
                    >
                      <option value="">Pilih Sub Jabatan</option>
                      {getSubJabatanOptions().map(subJabatan => (
                        <option key={subJabatan.id} value={subJabatan.id}>
                          {subJabatan.nama}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Role
                    </label>
                    <select
                      required
                      value={formData.role_id}
                      onChange={(e) => setFormData({ ...formData, role_id: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    >
                      <option value="">Pilih Role</option>
                      {roleOptions.map(role => (
                        <option key={role.id} value={role.id}>
                          {role.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="flex items-center">
                      <input
                        type="checkbox"
                        checked={formData.is_default}
                        onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })}
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <span className="ml-2 text-sm text-gray-700">Set sebagai role default</span>
                    </label>
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
                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                  >
                    {editingMapping ? 'Update' : 'Simpan'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default JabatanRoleMapping;

import React, { useState, useEffect } from 'react';
import { X, User, Shield, Mail, UserCheck, Camera } from 'lucide-react';
import toast from 'react-hot-toast';
import roleService from '../services/roleService.jsx';
import { toServerDateInput } from '../services/serverClock';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';

const EditPegawaiModal = ({
  isOpen,
  onClose,
  onSubmit,
  selectedUser,
  primaryRoles,
  allSubRoles
}) => {
  const [editFormData, setEditFormData] = useState({});
  const [availableSubRoles, setAvailableSubRoles] = useState([]);
  const [previewImage, setPreviewImage] = useState(null);

  // Initialize form data when modal opens or user changes
  useEffect(() => {
    const initializeFormData = async () => {
      if (isOpen && selectedUser) {
        console.log('EditPegawaiModal: Initializing form data for user:', selectedUser);
        console.log('EditPegawaiModal: User roles:', selectedUser.roles);
        console.log('EditPegawaiModal: Primary roles available:', primaryRoles);
        
        const formData = {
          username: selectedUser.username || '',
          nama_lengkap: selectedUser.nama_lengkap || '',
          jenis_kelamin: selectedUser.jenis_kelamin || '',
          email: selectedUser.email || '',
          tanggal_lahir: selectedUser.tanggal_lahir ? toServerDateInput(selectedUser.tanggal_lahir) : '',
          alamat: selectedUser.alamat || '',
          no_telepon: selectedUser.no_telepon || '',
          status_kepegawaian: selectedUser.status_kepegawaian || '',
          nip: selectedUser.nip || '',
          nuptk: selectedUser.nuptk || '',
          is_active: selectedUser.is_active
        };

        // Handle role mapping
        if (selectedUser.roles && selectedUser.roles.length > 0 && Array.isArray(primaryRoles)) {
          // Find primary role (roles that are not sub-roles)
          const userRoleNames = selectedUser.roles.map(role => role.name);
          console.log('EditPegawaiModal: User role names:', userRoleNames);
          
          // Find primary role by checking which role is in primaryRoles
          const primaryRole = primaryRoles.find(role => 
            userRoleNames.includes(role.name)
          );
          
          console.log('EditPegawaiModal: Found primary role:', primaryRole);
          
          if (primaryRole) {
            formData.role = primaryRole.id;
            
            // Load sub roles using roleService
            try {
              const response = await roleService.getSubRoles(primaryRole.id);
              console.log('EditPegawaiModal: Sub roles response:', response);
              
              if (response.success) {
                const subRoles = response.data || [];
                setAvailableSubRoles(subRoles);
                console.log('EditPegawaiModal: Available sub roles:', subRoles);
                
                // Find and set selected sub role by checking which role is in userRoleNames
                // but is not the primary role
                const subRole = subRoles.find(role => 
                  userRoleNames.includes(role.name) && role.name !== primaryRole.name
                );
                
                console.log('EditPegawaiModal: Found sub role:', subRole);
                
                if (subRole) {
                  formData.sub_role = subRole.id;
                }
              }
            } catch (error) {
              console.error('EditPegawaiModal: Error loading sub roles:', error);
              setAvailableSubRoles([]);
            }
          } else {
            console.log('EditPegawaiModal: No primary role found, clearing sub roles');
            setAvailableSubRoles([]);
          }
        } else {
          console.log('EditPegawaiModal: No user roles or primary roles available');
          setAvailableSubRoles([]);
        }

        console.log('EditPegawaiModal: Final form data:', formData);
        setEditFormData(formData);
        setPreviewImage(resolveProfilePhotoUrl(selectedUser.foto_profil_url || selectedUser.foto_profil));
      }
    };

    initializeFormData();
  }, [isOpen, selectedUser, primaryRoles]);

  // Update available sub roles when primary role changes
  const updateAvailableSubRoles = (selectedRoleId) => {
    if (!selectedRoleId || !allSubRoles || !Array.isArray(allSubRoles)) {
      setAvailableSubRoles([]);
      return;
    }
    const subRolesForParent = allSubRoles.filter(
      role => parseInt(role.parent_role_id) === parseInt(selectedRoleId)
    );
    setAvailableSubRoles(subRolesForParent);
  };

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setEditFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handlePrimaryRoleChange = async (e) => {
    const selectedRoleId = parseInt(e.target.value);
    handleInputChange(e);
    
    // Reset sub role when primary role changes
    setEditFormData(prev => ({
      ...prev,
      sub_role: ''
    }));
    
    if (selectedRoleId) {
      try {
        // Call fetchSubRoles using roleService
        const response = await roleService.getSubRoles(selectedRoleId);
        
        if (response.success) {
          const subRoles = response.data || [];
          console.log('EditPegawaiModal: Received sub roles:', subRoles);
          setAvailableSubRoles(subRoles);
        } else {
          console.warn('EditPegawaiModal: No sub roles found');
          setAvailableSubRoles([]);
        }
      } catch (error) {
        console.error('EditPegawaiModal: Error loading sub roles:', error);
        setAvailableSubRoles([]);
      }
    } else {
      setAvailableSubRoles([]);
    }
  };

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Validasi ukuran file (maksimal 2MB)
      if (file.size > 2 * 1024 * 1024) {
        toast.error('Foto terlalu besar. Maksimal 2MB');
        return;
      }

      // Validasi tipe file
      const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
      if (!allowedTypes.includes(file.type)) {
        toast.error('Format foto tidak sesuai. Gunakan JPG atau PNG');
        return;
      }

      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviewImage(reader.result);
        setEditFormData(prev => ({ ...prev, foto_profil: file }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async () => {
    try {
      // Validasi frontend
      const errors = [];
      
      if (!editFormData.username?.trim()) errors.push('Username wajib diisi');
      if (!editFormData.email?.trim()) errors.push('Email wajib diisi');
      if (!editFormData.nama_lengkap?.trim()) errors.push('Nama lengkap wajib diisi');
      if (!editFormData.status_kepegawaian) errors.push('Status kepegawaian wajib dipilih');
      if (!editFormData.role) errors.push('Role wajib dipilih');
      
      // Validasi NIP untuk ASN
      if (editFormData.status_kepegawaian === 'ASN') {
        if (!editFormData.nip) {
          errors.push('NIP wajib diisi untuk pegawai ASN');
        } else if (!/^\d{18}$/.test(editFormData.nip)) {
          errors.push('NIP harus 18 digit angka');
        }
      }
      
      if (errors.length > 0) {
        toast.error(errors.join(', '));
        return;
      }

      // Prepare data
      const dataToSend = {
        username: editFormData.username,
        email: editFormData.email,
        nama_lengkap: editFormData.nama_lengkap,
        status_kepegawaian: editFormData.status_kepegawaian,
        is_active: Boolean(editFormData.is_active),
        jenis_kelamin: editFormData.jenis_kelamin,
        tanggal_lahir: editFormData.tanggal_lahir,
        alamat: editFormData.alamat,
        no_telepon: editFormData.no_telepon,
        nuptk: editFormData.nuptk
      };

      // Add NIP if status is ASN
      if (editFormData.status_kepegawaian === 'ASN' && editFormData.nip) {
        dataToSend.nip = editFormData.nip;
      }

      // Convert role IDs to names array
      const roles = [];
      if (editFormData.role) {
        const selectedRole = primaryRoles.find(role => role.id == editFormData.role);
        if (selectedRole) {
          roles.push(selectedRole.name);
        }
      }
      
      if (editFormData.sub_role) {
        const selectedSubRole = availableSubRoles.find(role => role.id == editFormData.sub_role);
        if (selectedSubRole) {
          roles.push(selectedSubRole.name);
        }
      }

      // Add roles array to dataToSend
      if (roles.length > 0) {
        dataToSend.roles = roles;
      }

      // Add foto_profil if exists
      if (editFormData.foto_profil) {
        dataToSend.foto_profil = editFormData.foto_profil;
      }

      // Import pegawaiService
      const { default: pegawaiService } = await import('../services/pegawaiService');
      
      // Update pegawai
      await pegawaiService.update(selectedUser.id, dataToSend);
      
      toast.success('Data pegawai berhasil diperbarui');
      onSubmit(); // Call parent callback to refresh data and close modal
    } catch (error) {
      console.error('Error updating pegawai:', error);
      if (error.errors) {
        const errorMessages = Object.values(error.errors).flat();
        toast.error(`Validasi gagal: ${errorMessages.join(', ')}`);
      } else if (error.message) {
        toast.error(`Error: ${error.message}`);
      } else {
        toast.error('Gagal memperbarui data pegawai');
      }
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
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                  <User className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">Edit Data Pegawai</h3>
                  <p className="text-blue-100 text-sm">Perbarui data pegawai dengan benar</p>
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
            <form className="space-y-6">
              {/* Photo Upload Section */}
              <div className="flex justify-center mb-6">
                <div className="relative">
                  <div className="w-32 h-32 rounded-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center border-4 border-white shadow-lg">
                    {previewImage ? (
                      <img
                        src={previewImage}
                        alt="Preview"
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <User className="w-16 h-16 text-gray-400" />
                    )}
                  </div>
                  <label className="absolute bottom-0 right-0 p-2 bg-blue-600 rounded-full cursor-pointer hover:bg-blue-700 transition-colors shadow-lg">
                    <Camera className="w-4 h-4 text-white" />
                    <input
                      type="file"
                      className="hidden"
                      onChange={handleImageChange}
                      accept="image/*"
                    />
                  </label>
                </div>
              </div>

              {/* Form Fields */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Username */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Username*
                  </label>
                  <div className="relative">
                    <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type="text"
                      name="username"
                      value={editFormData.username || ''}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      placeholder="Masukkan username"
                      required
                    />
                  </div>
                </div>

                {/* Email */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Email*
                  </label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type="email"
                      name="email"
                      value={editFormData.email || ''}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      placeholder="nama@email.com"
                      required
                    />
                  </div>
                </div>

                {/* Full Name */}
                <div className="md:col-span-2 space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Nama Lengkap*
                  </label>
                  <input
                    type="text"
                    name="nama_lengkap"
                    value={editFormData.nama_lengkap || ''}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    placeholder="Masukkan nama lengkap"
                    required
                  />
                </div>

                {/* Role */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Role*
                  </label>
                  <select
                    name="role"
                    value={editFormData.role || ''}
                    onChange={handlePrimaryRoleChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    required
                  >
                    <option value="">Pilih Role</option>
                    {primaryRoles?.map(role => (
                      <option key={role.id} value={role.id}>
                        {role.display_name || role.name}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Sub Role */}
                {availableSubRoles?.length > 0 && (
                  <div className="space-y-2">
                    <label className="block text-sm font-semibold text-gray-700">
                      Sub Role
                    </label>
                    <select
                      name="sub_role"
                      value={editFormData.sub_role || ''}
                      onChange={handleInputChange}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    >
                      <option value="">Pilih Sub Role</option>
                      {availableSubRoles.map(role => (
                        <option key={role.id} value={role.id}>
                          {role.display_name || role.name}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
              </div>

              {/* Status Kepegawaian */}
              <div className="space-y-4">
                <label className="block text-sm font-semibold text-gray-700">
                  Status Kepegawaian*
                </label>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label className="relative cursor-pointer group">
                    <input
                      type="radio"
                      name="status_kepegawaian"
                      value="ASN"
                      checked={editFormData.status_kepegawaian === 'ASN'}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      editFormData.status_kepegawaian === 'ASN'
                        ? 'border-blue-500 bg-blue-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-blue-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          editFormData.status_kepegawaian === 'ASN'
                            ? 'border-blue-500 bg-blue-500'
                            : 'border-gray-300'
                        }`}>
                          {editFormData.status_kepegawaian === 'ASN' && (
                            <div className="w-2 h-2 bg-white rounded-full mx-auto mt-1"></div>
                          )}
                        </div>
                        <div>
                          <div className="font-semibold text-gray-900">ASN</div>
                          <div className="text-sm text-gray-500">Aparatur Sipil Negara</div>
                        </div>
                      </div>
                    </div>
                  </label>

                  <label className="relative cursor-pointer group">
                    <input
                      type="radio"
                      name="status_kepegawaian"
                      value="Honorer"
                      checked={editFormData.status_kepegawaian === 'Honorer'}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      editFormData.status_kepegawaian === 'Honorer'
                        ? 'border-green-500 bg-green-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-green-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          editFormData.status_kepegawaian === 'Honorer'
                            ? 'border-green-500 bg-green-500'
                            : 'border-gray-300'
                        }`}>
                          {editFormData.status_kepegawaian === 'Honorer' && (
                            <div className="w-2 h-2 bg-white rounded-full mx-auto mt-1"></div>
                          )}
                        </div>
                        <div>
                          <div className="font-semibold text-gray-900">Honorer</div>
                          <div className="text-sm text-gray-500">Pegawai Kontrak</div>
                        </div>
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              {/* NIP Field - Only show for ASN */}
              {editFormData.status_kepegawaian === 'ASN' && (
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    NIP (Nomor Induk Pegawai)*
                  </label>
                  <div className="relative">
                    <Shield className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type="text"
                      name="nip"
                      value={editFormData.nip || ''}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 font-mono"
                      placeholder="18 digit angka"
                      required
                      maxLength={18}
                      pattern="\d{18}"
                    />
                  </div>
                  <p className="text-xs text-gray-500">Format: 18 digit angka tanpa spasi</p>
                </div>
              )}

              {/* Active Status */}
              <div className="flex items-center space-x-3">
                <input
                  type="checkbox"
                  name="is_active"
                  checked={editFormData.is_active || false}
                  onChange={handleInputChange}
                  className="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                />
                <label className="text-sm font-medium text-gray-700">
                  Aktifkan akun pegawai
                </label>
              </div>
            </form>
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
            >
              Simpan Perubahan
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default EditPegawaiModal;

import React from 'react';
import { X, Upload, User, Shield, Mail, Lock, UserCheck, Camera, Eye, EyeOff } from 'lucide-react';
import { usePegawaiForm } from '../hooks/usePegawaiForm';
import pegawaiService from '../services/pegawaiService.jsx';
import roleService from '../services/roleService.jsx';
import toast from 'react-hot-toast';

const TambahPegawaiModal = ({ isOpen, onClose, onSuccess, primaryRoles, availableSubRoles, handlePrimaryRoleChange }) => {
  const [showPassword, setShowPassword] = React.useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = React.useState(false);
  const [localAvailableSubRoles, setLocalAvailableSubRoles] = React.useState([]);
  
  // Use the custom hook for form management
  const {
    formData,
    previewImage,
    handleInputChange,
    handleImageChange,
    resetForm
  } = usePegawaiForm();

  // Update local sub roles when availableSubRoles prop changes
  React.useEffect(() => {
    console.log('TambahPegawaiModal: availableSubRoles prop changed:', availableSubRoles);
    setLocalAvailableSubRoles(availableSubRoles || []);
  }, [availableSubRoles]);

  // Handle primary role change with local state update
  const handlePrimaryRoleChangeLocal = async (roleId) => {
    console.log('TambahPegawaiModal: handlePrimaryRoleChangeLocal called with roleId:', roleId);
    if (handlePrimaryRoleChange) {
      try {
        const subRoles = await handlePrimaryRoleChange(roleId);
        console.log('TambahPegawaiModal: received subRoles:', subRoles);
        if (Array.isArray(subRoles)) {
          setLocalAvailableSubRoles(subRoles);
          console.log('TambahPegawaiModal: localAvailableSubRoles updated to:', subRoles);
        } else {
          console.log('TambahPegawaiModal: subRoles is not an array, clearing local state');
          setLocalAvailableSubRoles([]);
        }
      } catch (error) {
        console.error('TambahPegawaiModal: Error in handlePrimaryRoleChangeLocal:', error);
        setLocalAvailableSubRoles([]);
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
                  <h3 className="text-xl font-bold text-white">Tambah Pegawai Baru</h3>
                  <p className="text-blue-100 text-sm">Lengkapi data pegawai dengan benar</p>
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
                      value={formData.username}
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
                      value={formData.email}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      placeholder="nama@email.com"
                      required
                    />
                  </div>
                </div>

                {/* Password */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Password*
                  </label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type={showPassword ? "text" : "password"}
                      name="password"
                      value={formData.password}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      placeholder="Minimal 8 karakter"
                      required
                      minLength={8}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                    </button>
                  </div>
                </div>

                {/* Confirm Password */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Konfirmasi Password*
                  </label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type={showConfirmPassword ? "text" : "password"}
                      name="konfirmasi_password"
                      value={formData.konfirmasi_password}
                      onChange={handleInputChange}
                      className="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                      placeholder="Ulangi password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                      className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                    </button>
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
                    value={formData.nama_lengkap}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    placeholder="Masukkan nama lengkap"
                    required
                  />
                </div>

                {/* Jenis Kelamin */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Jenis Kelamin*
                  </label>
                  <select
                    name="jenis_kelamin"
                    value={formData.jenis_kelamin}
                    onChange={handleInputChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    required
                  >
                    <option value="">Pilih Jenis Kelamin</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                </div>

                {/* Role */}
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    Role*
                  </label>
                  <select
                    name="role"
                    value={formData.role}
                    onChange={async (e) => {
                      handleInputChange(e);
                      // Clear sub role when primary role changes
                      handleInputChange({
                        target: { name: 'sub_role', value: '' }
                      });
                      
                      if (e.target.value) {
                        const roleId = parseInt(e.target.value, 10);
                        console.log('TambahPegawaiModal: Role changed to:', roleId);
                        
                        try {
                          // Call fetchSubRoles using roleService
                          const response = await roleService.getSubRoles(roleId);
                          
                          if (response.success) {
                            const subRoles = response.data || [];
                            console.log('TambahPegawaiModal: Received sub roles:', subRoles);
                            setLocalAvailableSubRoles(subRoles);
                          } else {
                            console.warn('TambahPegawaiModal: No sub roles found');
                            setLocalAvailableSubRoles([]);
                          }
                        } catch (error) {
                          console.error('TambahPegawaiModal: Error loading sub roles:', error);
                          setLocalAvailableSubRoles([]);
                        }
                      } else {
                        setLocalAvailableSubRoles([]);
                      }
                    }}
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
                  {primaryRoles?.length === 0 && (
                    <p className="text-xs text-gray-500">Memuat data role...</p>
                  )}
                </div>

                {/* Sub Role - Only show if selected role has sub roles */}
                {formData.role && localAvailableSubRoles?.length > 0 && (
                  <div className="space-y-2">
                    <label className="block text-sm font-semibold text-gray-700">
                      Sub Role
                    </label>
                    <select
                      name="sub_role"
                      value={formData.sub_role}
                      onChange={handleInputChange}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                    >
                      <option value="">Pilih Sub Role</option>
                      {localAvailableSubRoles.map(role => (
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
                      checked={formData.status_kepegawaian === 'ASN'}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      formData.status_kepegawaian === 'ASN'
                        ? 'border-blue-500 bg-blue-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-blue-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          formData.status_kepegawaian === 'ASN'
                            ? 'border-blue-500 bg-blue-500'
                            : 'border-gray-300'
                        }`}>
                          {formData.status_kepegawaian === 'ASN' && (
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
                      checked={formData.status_kepegawaian === 'Honorer'}
                      onChange={handleInputChange}
                      className="sr-only"
                    />
                    <div className={`p-4 rounded-xl border-2 transition-all duration-200 ${
                      formData.status_kepegawaian === 'Honorer'
                        ? 'border-green-500 bg-green-50 shadow-md'
                        : 'border-gray-200 bg-white hover:border-green-300 group-hover:shadow-sm'
                    }`}>
                      <div className="flex items-center">
                        <div className={`w-5 h-5 rounded-full border-2 mr-3 ${
                          formData.status_kepegawaian === 'Honorer'
                            ? 'border-green-500 bg-green-500'
                            : 'border-gray-300'
                        }`}>
                          {formData.status_kepegawaian === 'Honorer' && (
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
              {formData.status_kepegawaian === 'ASN' && (
                <div className="space-y-2">
                  <label className="block text-sm font-semibold text-gray-700">
                    NIP (Nomor Induk Pegawai)*
                  </label>
                  <div className="relative">
                    <Shield className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                      type="text"
                      name="nip"
                      value={formData.nip}
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
                  checked={formData.is_active}
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
              onClick={async () => {
                try {
                  // Validasi frontend
                  const errors = [];
                  
                  if (!formData.username.trim()) errors.push('Username wajib diisi');
                  if (!formData.email.trim()) errors.push('Email wajib diisi');
                  if (!formData.password.trim()) errors.push('Password wajib diisi');
                  if (formData.password.length < 8) errors.push('Password minimal 8 karakter');
                  if (formData.password !== formData.konfirmasi_password) {
                    errors.push('Password dan konfirmasi password tidak sama');
                  }
                  if (!formData.nama_lengkap.trim()) errors.push('Nama lengkap wajib diisi');
                  if (!formData.jenis_kelamin) errors.push('Jenis kelamin wajib dipilih');
                  if (!formData.status_kepegawaian) errors.push('Status kepegawaian wajib dipilih');
                  if (!formData.role) errors.push('Role wajib dipilih');
                  
                  // Validasi NIP untuk ASN
                  if (formData.status_kepegawaian === 'ASN') {
                    if (!formData.nip) {
                      errors.push('NIP wajib diisi untuk pegawai ASN');
                    } else if (!/^\d{18}$/.test(formData.nip)) {
                      errors.push('NIP harus 18 digit angka');
                    }
                  }
                  
                  if (errors.length > 0) {
                    toast.error(errors.join(', '));
                    return;
                  }

                  // Prepare data
                  const dataToSend = {
                    username: formData.username,
                    email: formData.email,
                    password: formData.password,
                    nama_lengkap: formData.nama_lengkap,
                    jenis_kelamin: formData.jenis_kelamin,
                    status_kepegawaian: formData.status_kepegawaian,
                    is_active: Boolean(formData.is_active), // Convert to boolean
                  };

                  // Add NIP if status is ASN
                  if (formData.status_kepegawaian === 'ASN' && formData.nip) {
                    dataToSend.nip = formData.nip;
                  }

                  // Convert roles to array format expected by backend
                  const rolesToSend = [];
                  
                  // Add primary role
                  if (formData.role) {
                    const selectedRole = primaryRoles.find(role => role.id == formData.role);
                    if (selectedRole) {
                      rolesToSend.push(selectedRole.name);
                      
                      // Add sub role if selected
                      if (formData.sub_role) {
                        const selectedSubRole = localAvailableSubRoles.find(role => 
                          role.id == formData.sub_role
                        );
                        if (selectedSubRole) {
                          rolesToSend.push(selectedSubRole.name);
                          console.log('Added sub role:', selectedSubRole.name);
                        }
                      }
                    }
                  }

                  // Add roles array to dataToSend
                  if (rolesToSend.length > 0) {
                    dataToSend.roles = rolesToSend;
                    console.log('Final roles:', rolesToSend);
                  }

                  // Add foto_profil if exists
                  if (formData.foto_profil) {
                    dataToSend.foto_profil = formData.foto_profil;
                  }

                  // Create pegawai
                  await pegawaiService.create(dataToSend);
                  
                  toast.success('Pegawai berhasil ditambahkan');
                  onSuccess(); // Callback untuk refresh data
                  resetForm(); // Reset form setelah submit sukses
                  onClose();
                } catch (error) {
                  console.error('Error creating pegawai:', error);
                  if (error.errors) {
                    const errorMessages = Object.values(error.errors).flat();
                    toast.error(`Validasi gagal: ${errorMessages.join(', ')}`);
                  } else if (error.message) {
                    toast.error(`Error: ${error.message}`);
                  } else {
                    toast.error('Gagal menambahkan pegawai');
                  }
                }
              }}
              className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
            >
              Simpan Pegawai
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TambahPegawaiModal;

import React from 'react';
import { Key, User, Calendar, AlertCircle } from 'lucide-react';
import { formatServerDate, getServerDateParts } from '../services/serverClock';

const ResetPasswordModal = ({
  isOpen,
  onClose,
  onSubmit,
  newPassword,
  setNewPassword,
  confirmPassword,
  setConfirmPassword,
  userType = 'pegawai', // 'pegawai' atau 'siswa'
  selectedUser = null
}) => {
  if (!isOpen) return null;

  const isSiswa = userType === 'siswa';

  const getBirthdateParts = () => {
    if (!isSiswa || !selectedUser?.data_pribadi_siswa?.tanggal_lahir) return null;
    return getServerDateParts(selectedUser.data_pribadi_siswa.tanggal_lahir);
  };
  
  // Format tanggal lahir untuk preview
  const getBirthdatePassword = () => {
    const parts = getBirthdateParts();
    if (!parts) return '';

    const day = String(parts.day).padStart(2, '0');
    const month = String(parts.month).padStart(2, '0');
    const year = parts.year;
    return `${day}${month}${year}`;
  };

  const formatTanggalLahir = () => {
    if (!isSiswa || !selectedUser?.data_pribadi_siswa?.tanggal_lahir) return '';

    return formatServerDate(selectedUser.data_pribadi_siswa.tanggal_lahir, 'id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    }) || '';
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {/* Backdrop */}
        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
          <div className="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>
        </div>
        
        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        {/* Modal */}
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-yellow-500 to-orange-600 px-6 py-6">
            <div className="flex items-center">
              <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                <Key className="w-6 h-6 text-white" />
              </div>
              <div>
                <h3 className="text-xl font-bold text-white">Reset Password</h3>
                <p className="text-yellow-100 text-sm">
                  {isSiswa ? 'Reset password siswa ke tanggal lahir' : 'Reset password pegawai'}
                </p>
              </div>
            </div>
          </div>

          {/* Content */}
          <div className="px-6 py-6">
            {/* User Info */}
            <div className="mb-6 p-4 bg-gray-50 rounded-xl">
              <div className="flex items-center">
                <div className="p-2 bg-blue-100 rounded-lg mr-3">
                  <User className="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <p className="font-semibold text-gray-900">
                    {selectedUser?.nama_lengkap || 'Nama tidak tersedia'}
                  </p>
                  <div className="text-sm text-gray-500">
                    {isSiswa ? (
                      <p>NIS: {selectedUser?.nis || '-'}</p>
                    ) : (
                      <p>NIP: {selectedUser?.nip || '-'}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {isSiswa ? (
              /* Tampilan untuk Siswa */
              <div className="space-y-4">
                <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                  <div className="flex items-start">
                    <Calendar className="w-5 h-5 text-blue-600 mt-0.5 mr-3" />
                    <div>
                      <h4 className="font-semibold text-blue-900 mb-1">
                        Password akan direset ke tanggal lahir
                      </h4>
                      <p className="text-sm text-blue-700 mb-2">
                        Tanggal lahir: <span className="font-medium">{formatTanggalLahir()}</span>
                      </p>
                      <p className="text-sm text-blue-700">
                        Password baru: <span className="font-mono font-bold">{getBirthdatePassword()}</span>
                      </p>
                    </div>
                  </div>
                </div>

                {!selectedUser?.data_pribadi_siswa?.tanggal_lahir && (
                  <div className="p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div className="flex items-start">
                      <AlertCircle className="w-5 h-5 text-red-600 mt-0.5 mr-3" />
                      <div>
                        <h4 className="font-semibold text-red-900 mb-1">
                          Tidak dapat mereset password
                        </h4>
                        <p className="text-sm text-red-700">
                          Data tanggal lahir siswa tidak ditemukan. Silakan lengkapi data siswa terlebih dahulu.
                        </p>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              /* Tampilan untuk Pegawai */
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-2">
                    Password Baru *
                  </label>
                  <input
                    type="password"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all duration-200"
                    placeholder="Minimal 8 karakter"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-2">
                    Konfirmasi Password *
                  </label>
                  <input
                    type="password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all duration-200"
                    placeholder="Konfirmasi password baru"
                  />
                </div>

                <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                  <div className="flex items-start">
                    <AlertCircle className="w-5 h-5 text-yellow-600 mt-0.5 mr-3" />
                    <div>
                      <h4 className="font-semibold text-yellow-900 mb-1">
                        Perhatian
                      </h4>
                      <p className="text-sm text-yellow-700">
                        Password harus minimal 8 karakter dan kedua password harus sama.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors"
            >
              Batal
            </button>
            <button
              type="button"
              onClick={onSubmit}
              disabled={isSiswa ? !selectedUser?.data_pribadi_siswa?.tanggal_lahir : false}
              className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSiswa ? 'Reset ke Tanggal Lahir' : 'Reset Password'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ResetPasswordModal;

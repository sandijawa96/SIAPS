import React, { useEffect } from 'react';
import { X, User, Calendar, BookOpen, Clock } from 'lucide-react';
import { useRiwayatKelas } from '../../hooks/useRiwayatKelas';
import RiwayatKelas from '../RiwayatKelas';

const RiwayatKelasModal = ({ isOpen, onClose, siswaId, siswaName }) => {
  const { loading, riwayat, siswa, error, fetchRiwayatKelas } = useRiwayatKelas();

  useEffect(() => {
    if (isOpen && siswaId) {
      fetchRiwayatKelas(siswaId);
    }
  }, [isOpen, siswaId, fetchRiwayatKelas]);

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
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                  <BookOpen className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">Riwayat Kelas Siswa</h3>
                  <p className="text-blue-100 text-sm">
                    {siswa ? siswa.nama_lengkap : siswaName || 'Loading...'}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          {/* Content */}
          <div className="px-6 py-6 max-h-[calc(100vh-200px)] overflow-y-auto">
            {loading ? (
              <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span className="ml-2 text-gray-600">Memuat riwayat kelas...</span>
              </div>
            ) : error ? (
              <div className="text-center py-8">
                <div className="text-red-500 mb-2">
                  <X className="w-12 h-12 mx-auto" />
                </div>
                <p className="text-red-600">{error}</p>
              </div>
            ) : (
              <div className="space-y-6">
                {/* Info Siswa */}
                {siswa && (
                  <div className="bg-gray-50 rounded-lg p-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div className="flex items-center space-x-2">
                        <User className="w-5 h-5 text-gray-400" />
                        <div>
                          <p className="text-sm text-gray-500">Nama Lengkap</p>
                          <p className="font-medium">{siswa.nama_lengkap}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Calendar className="w-5 h-5 text-gray-400" />
                        <div>
                          <p className="text-sm text-gray-500">NIS</p>
                          <p className="font-medium">{siswa.nis}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Clock className="w-5 h-5 text-gray-400" />
                        <div>
                          <p className="text-sm text-gray-500">NISN</p>
                          <p className="font-medium">{siswa.nisn}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Riwayat Kelas */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <h4 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                    <BookOpen className="w-5 h-5 mr-2 text-blue-600" />
                    Riwayat Kelas
                  </h4>
                  <RiwayatKelas riwayat={riwayat} />
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end">
            <button
              onClick={onClose}
              className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RiwayatKelasModal;

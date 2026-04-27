import React, { useState } from 'react';
import { Plus, Edit, Trash2, ChevronRight } from 'lucide-react';

const JabatanTab = () => {
  const [jabatan, setJabatan] = useState([
    {
      id: 1,
      nama: 'Guru',
      deskripsi: 'Tenaga pengajar',
      sub_jabatan: [
        { id: 1, nama: 'Wali Kelas', deskripsi: 'Guru yang bertanggung jawab atas satu kelas' },
        { id: 2, nama: 'Guru Mata Pelajaran', deskripsi: 'Guru bidang studi tertentu' },
        { id: 3, nama: 'Guru BK', deskripsi: 'Guru Bimbingan Konseling' }
      ]
    },
    {
      id: 2,
      nama: 'Staff',
      deskripsi: 'Tenaga kependidikan',
      sub_jabatan: [
        { id: 4, nama: 'Staff TU', deskripsi: 'Staff Tata Usaha' },
        { id: 5, nama: 'Staff Perpustakaan', deskripsi: 'Pengelola perpustakaan' }
      ]
    },
    {
      id: 3,
      nama: 'Pimpinan',
      deskripsi: 'Jajaran pimpinan sekolah',
      sub_jabatan: [
        { id: 6, nama: 'Kepala Sekolah', deskripsi: 'Pimpinan tertinggi sekolah' },
        { id: 7, nama: 'Wakil Kepala Sekolah', deskripsi: 'Wakil pimpinan sekolah' }
      ]
    }
  ]);

  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState('jabatan'); // 'jabatan' atau 'sub_jabatan'
  const [selectedJabatan, setSelectedJabatan] = useState(null);
  const [selectedSubJabatan, setSelectedSubJabatan] = useState(null);
  const [formData, setFormData] = useState({
    nama: '',
    deskripsi: ''
  });

  const handleAdd = (type, parentJabatan = null) => {
    setModalType(type);
    setSelectedJabatan(parentJabatan);
    setSelectedSubJabatan(null);
    setFormData({ nama: '', deskripsi: '' });
    setShowModal(true);
  };

  const handleEdit = (type, item, parentJabatan = null) => {
    setModalType(type);
    setSelectedJabatan(parentJabatan);
    setSelectedSubJabatan(item);
    setFormData({
      nama: item.nama,
      deskripsi: item.deskripsi
    });
    setShowModal(true);
  };

  const handleDelete = (type, itemId, parentJabatanId = null) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus ini?')) {
      if (type === 'jabatan') {
        setJabatan(jabatan.filter(j => j.id !== itemId));
      } else {
        setJabatan(jabatan.map(j => {
          if (j.id === parentJabatanId) {
            return {
              ...j,
              sub_jabatan: j.sub_jabatan.filter(sub => sub.id !== itemId)
            };
          }
          return j;
        }));
      }
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (modalType === 'jabatan') {
      if (selectedSubJabatan) {
        // Edit jabatan
        setJabatan(jabatan.map(j => 
          j.id === selectedSubJabatan.id 
            ? { ...j, ...formData }
            : j
        ));
      } else {
        // Add new jabatan
        setJabatan([
          ...jabatan,
          {
            id: Math.max(...jabatan.map(j => j.id)) + 1,
            ...formData,
            sub_jabatan: []
          }
        ]);
      }
    } else {
      // Handle sub jabatan
      setJabatan(jabatan.map(j => {
        if (j.id === selectedJabatan.id) {
          if (selectedSubJabatan) {
            // Edit sub jabatan
            return {
              ...j,
              sub_jabatan: j.sub_jabatan.map(sub =>
                sub.id === selectedSubJabatan.id
                  ? { ...sub, ...formData }
                  : sub
              )
            };
          } else {
            // Add new sub jabatan
            return {
              ...j,
              sub_jabatan: [
                ...j.sub_jabatan,
                {
                  id: Math.max(...j.sub_jabatan.map(sub => sub.id), 0) + 1,
                  ...formData
                }
              ]
            };
          }
        }
        return j;
      }));
    }

    setShowModal(false);
    setFormData({ nama: '', deskripsi: '' });
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <h2 className="text-lg font-medium text-gray-900">Manajemen Jabatan</h2>
        <button
          onClick={() => handleAdd('jabatan')}
          className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
        >
          <Plus className="w-4 h-4 mr-2" />
          Tambah Jabatan
        </button>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <ul className="divide-y divide-gray-200">
          {jabatan.map((jab) => (
            <li key={jab.id} className="p-4">
              <div className="flex items-center justify-between mb-2">
                <div>
                  <h3 className="text-lg font-medium text-gray-900">{jab.nama}</h3>
                  <p className="text-sm text-gray-500">{jab.deskripsi}</p>
                </div>
                <div className="flex items-center space-x-2">
                  <button
                    onClick={() => handleEdit('jabatan', jab)}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    <Edit className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => handleDelete('jabatan', jab.id)}
                    className="text-red-600 hover:text-red-900"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>

              {/* Sub Jabatan */}
              <div className="ml-4 mt-2">
                <div className="flex items-center justify-between mb-2">
                  <h4 className="text-sm font-medium text-gray-700">Sub Jabatan</h4>
                  <button
                    onClick={() => handleAdd('sub_jabatan', jab)}
                    className="inline-flex items-center px-2 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                  >
                    <Plus className="w-3 h-3 mr-1" />
                    Tambah
                  </button>
                </div>
                <ul className="space-y-2">
                  {jab.sub_jabatan.map((sub) => (
                    <li key={sub.id} className="flex items-center justify-between py-2 pl-4 pr-2 rounded-md hover:bg-gray-50">
                      <div className="flex items-center">
                        <ChevronRight className="w-4 h-4 text-gray-400 mr-2" />
                        <div>
                          <p className="text-sm font-medium text-gray-900">{sub.nama}</p>
                          <p className="text-xs text-gray-500">{sub.deskripsi}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        <button
                          onClick={() => handleEdit('sub_jabatan', sub, jab)}
                          className="text-blue-600 hover:text-blue-900"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDelete('sub_jabatan', sub.id, jab.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </li>
                  ))}
                </ul>
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
                {modalType === 'jabatan' 
                  ? (selectedSubJabatan ? 'Edit Jabatan' : 'Tambah Jabatan Baru')
                  : (selectedSubJabatan ? 'Edit Sub Jabatan' : 'Tambah Sub Jabatan Baru')
                }
              </h3>
              <form onSubmit={handleSubmit}>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Nama {modalType === 'jabatan' ? 'Jabatan' : 'Sub Jabatan'}
                    </label>
                    <input
                      type="text"
                      required
                      value={formData.nama}
                      onChange={(e) => setFormData({ ...formData, nama: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700">
                      Deskripsi
                    </label>
                    <textarea
                      rows={3}
                      value={formData.deskripsi}
                      onChange={(e) => setFormData({ ...formData, deskripsi: e.target.value })}
                      className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    />
                  </div>
                </div>

                <div className="mt-6 flex justify-end space-x-3">
                  <button
                    type="button"
                    onClick={() => setShowModal(false)}
                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                  >
                    {selectedSubJabatan ? 'Update' : 'Simpan'}
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

export default JabatanTab;

import React, { useState, useEffect } from 'react';
import Modal from './Modal';
import { TAHUN_AJARAN_STATUS, STATUS_DISPLAY } from '../../services/tahunAjaranService';

const TahunAjaranFormModal = ({ isOpen, onClose, onSubmit, initialData }) => {
  const [formData, setFormData] = useState({
    id: null,
    nama: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    semester: 'full',
    status: TAHUN_AJARAN_STATUS.DRAFT,
    keterangan: ''
  });

  useEffect(() => {
    if (initialData) {
      setFormData({
        id: initialData.id || null,
        nama: initialData.nama || '',
        tanggal_mulai: initialData.tanggal_mulai || '',
        tanggal_selesai: initialData.tanggal_selesai || '',
        semester: 'full',
        status: initialData.status || TAHUN_AJARAN_STATUS.DRAFT,
        keterangan: initialData.keterangan || ''
      });
    } else {
      // Reset form when no initial data (add mode)
      setFormData({
        id: null,
        nama: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        semester: 'full',
        status: TAHUN_AJARAN_STATUS.DRAFT,
        keterangan: ''
      });
    }
  }, [initialData]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit(formData);
  };

  return (
    <Modal 
      isOpen={isOpen} 
      onClose={onClose} 
      title={initialData ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran'}
      size="lg"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block font-medium mb-1">Nama Tahun Ajaran</label>
          <input
            type="text"
            name="nama"
            value={formData.nama}
            onChange={handleChange}
            required
            placeholder="Contoh: 2025/2026"
            className="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block font-medium mb-1">Tanggal Mulai</label>
            <input
              type="date"
              name="tanggal_mulai"
              value={formData.tanggal_mulai}
              onChange={handleChange}
              required
              className="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block font-medium mb-1">Tanggal Selesai</label>
            <input
              type="date"
              name="tanggal_selesai"
              value={formData.tanggal_selesai}
              onChange={handleChange}
              required
              className="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <div>
          <label className="block font-medium mb-1">Semester</label>
          <input
            type="text"
            value="Full (dibagi otomatis menjadi Ganjil & Genap oleh sistem)"
            disabled
            className="w-full border rounded-lg px-3 py-2 bg-gray-100 text-gray-600"
          />
          <input type="hidden" name="semester" value="full" />
        </div>

        <div>
          <label className="block font-medium mb-1">Status</label>
          <select
            name="status"
            value={formData.status}
            onChange={handleChange}
            className="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            {Object.entries(STATUS_DISPLAY).map(([key, label]) => (
              <option key={key} value={key}>{label}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block font-medium mb-1">Keterangan</label>
          <textarea
            name="keterangan"
            value={formData.keterangan}
            onChange={handleChange}
            className="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows={3}
          />
        </div>

        <div className="flex justify-end space-x-2 pt-4">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 border rounded-lg hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="submit"
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            {initialData ? 'Simpan Perubahan' : 'Tambah Tahun Ajaran'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default TahunAjaranFormModal;

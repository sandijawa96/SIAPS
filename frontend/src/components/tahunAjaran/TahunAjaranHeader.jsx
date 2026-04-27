import React from 'react';
import { Plus, Calendar } from 'lucide-react';

const TahunAjaranHeader = ({ onAddTahunAjaran }) => {
  return (
    <div className="flex justify-between items-center mb-6">
      <div className="flex items-center space-x-3">
        <div className="p-3 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl">
          <Calendar className="w-6 h-6 text-white" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Manajemen Tahun Ajaran</h1>
          <p className="text-sm text-gray-600 mt-1">Kelola tahun ajaran dan periode akademik</p>
        </div>
      </div>
      <button
        onClick={onAddTahunAjaran}
        className="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 flex items-center space-x-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
      >
        <Plus className="w-5 h-5" />
        <span>Tambah Tahun Ajaran</span>
      </button>
    </div>
  );
};

export default TahunAjaranHeader;

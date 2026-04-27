import React from 'react';
import { Edit, Trash2 } from 'lucide-react';
import { formatServerDate } from '../../services/serverClock';

const integerFormatter = new Intl.NumberFormat('id-ID');

const TahunAjaranCard = ({ tahunAjaran, onEdit, onDelete, onSetActive }) => {
  const getStatusColor = (status) => {
    switch (status) {
      case 'Aktif':
        return 'bg-green-100 text-green-800';
      case 'Selesai':
        return 'bg-gray-100 text-gray-800';
      case 'Draft':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const formatDate = (dateString) => {
    return formatServerDate(dateString, 'id-ID', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }) || '-';
  };

  return (
    <div className="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all duration-200">
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <div className="flex items-center space-x-3 mb-2">
            <h3 className="text-lg font-semibold text-gray-900">{tahunAjaran.nama}</h3>
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(tahunAjaran.status)}`}>
              {tahunAjaran.status}
            </span>
            {tahunAjaran.is_active && (
              <span className="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                Aktif
              </span>
            )}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm text-gray-600">
            <div>
              <span className="font-medium">Periode:</span>
              <p>{formatDate(tahunAjaran.tanggal_mulai)} - {formatDate(tahunAjaran.tanggal_selesai)}</p>
            </div>
            <div>
              <span className="font-medium">Semester:</span>
              <p>{tahunAjaran.semester_config?.semester || tahunAjaran.semester?.charAt(0).toUpperCase() + tahunAjaran.semester?.slice(1) || '-'}</p>
            </div>
            <div>
              <span className="font-medium">Jumlah Siswa:</span>
              <p>{integerFormatter.format(Number(tahunAjaran.jumlah_siswa || 0))}</p>
            </div>
            <div>
              <span className="font-medium">Jumlah Guru:</span>
              <p>{tahunAjaran.jumlah_guru || '0'}</p>
            </div>
          </div>
          
          {(tahunAjaran.semester_config?.keterangan || tahunAjaran.keterangan) && (
            <p className="text-sm text-gray-500 mt-2">
              {tahunAjaran.semester_config?.keterangan || tahunAjaran.keterangan}
            </p>
          )}
        </div>
        
        <div className="flex items-center space-x-2 ml-4">
          {!tahunAjaran.is_active && tahunAjaran.status !== 'Selesai' && (
            <button
              onClick={() => onSetActive(tahunAjaran.id, tahunAjaran.nama)}
              className="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors duration-200"
            >
              Aktifkan
            </button>
          )}
          <button
            onClick={() => onEdit(tahunAjaran)}
            className="p-2 text-blue-600 hover:text-blue-800 transition-colors duration-200"
            title="Edit tahun ajaran"
          >
            <Edit className="w-4 h-4" />
          </button>
          {!tahunAjaran.is_active && (
            <button
              onClick={() => onDelete(tahunAjaran.id, tahunAjaran.nama)}
              className="p-2 text-red-600 hover:text-red-800 transition-colors duration-200"
              title="Hapus tahun ajaran"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default TahunAjaranCard;

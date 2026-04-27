import React from 'react';

const RiwayatKelas = ({ riwayat }) => {
  const getStatusLabel = (status) => {
    switch (String(status || '').toLowerCase()) {
      case 'aktif':
        return 'Aktif';
      case 'pindah':
        return 'Pindah';
      case 'naik_kelas':
        return 'Naik Kelas';
      case 'lulus':
        return 'Lulus';
      case 'keluar':
        return 'Keluar';
      default:
        return status || '-';
    }
  };

  if (!riwayat || riwayat.length === 0) {
    return <p className="text-gray-500">Belum ada riwayat kelas.</p>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun Ajaran</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tingkat</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Masuk</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Keluar</th>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {riwayat.map((item) => (
            <tr key={item.id} className="hover:bg-gray-50">
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.tahun_ajaran}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.nama_kelas}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.tingkat_nama}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{getStatusLabel(item.status)}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.tanggal_masuk}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.tanggal_keluar || '-'}</td>
              <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{item.keterangan || '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default RiwayatKelas;

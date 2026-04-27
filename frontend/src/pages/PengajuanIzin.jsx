import React, { useState } from 'react';
import { FilePlus2 } from 'lucide-react';
import { IzinForm, IzinList } from '../components/izin';

const PengajuanIzin = () => {
  const [refreshKey, setRefreshKey] = useState(0);

  const handleFormSuccess = () => {
    setRefreshKey((prev) => prev + 1);
  };

  return (
    <div className="p-6 space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <div className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-xl">
            <FilePlus2 className="w-6 h-6 text-blue-600" />
          </div>
          <div className="flex-1">
            <h1 className="text-2xl font-bold text-gray-900">Pengajuan Izin</h1>
            <p className="text-sm text-gray-600 mt-1">
              Ajukan izin sesuai kebutuhan dan pantau status persetujuan.
            </p>
          </div>
        </div>
      </div>

      <IzinForm onSuccess={handleFormSuccess} />
      <IzinList refreshTrigger={refreshKey} />
    </div>
  );
};

export default PengajuanIzin;

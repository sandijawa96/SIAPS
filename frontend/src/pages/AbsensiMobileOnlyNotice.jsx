import React from 'react';
import { Smartphone, Info, Monitor } from 'lucide-react';

const AbsensiMobileOnlyNotice = () => {
  return (
    <div className="mx-auto w-full max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-center gap-3">
        <Smartphone className="h-6 w-6 text-blue-600" />
        <h1 className="text-xl font-semibold text-slate-900">Absensi Hanya via Mobile App</h1>
      </div>

      <div className="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
        <p className="font-medium">Aturan utama sistem:</p>
        <p className="mt-1">
          Check-in/check-out absensi hanya dapat dilakukan melalui aplikasi mobile.
        </p>
      </div>

      <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <div className="mb-2 flex items-center gap-2 font-medium text-slate-800">
          <Monitor className="h-4 w-4" />
          Peran dashboard web
        </div>
        <p>Website dipakai untuk monitoring, laporan, dan manajemen data.</p>
      </div>

      <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <Info className="mt-0.5 h-4 w-4 flex-shrink-0" />
        <p>
          Jika Anda siswa, silakan lakukan absensi dari mobile app resmi sekolah.
        </p>
      </div>
    </div>
  );
};

export default AbsensiMobileOnlyNotice;

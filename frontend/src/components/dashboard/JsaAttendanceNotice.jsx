import React from 'react';
import { Building2, ExternalLink } from 'lucide-react';

const ROLE_LABELS = {
  super_admin: 'Super Admin',
  admin: 'Admin',
  kepala_sekolah: 'Kepala Sekolah',
  wakasek_kurikulum: 'Wakasek Kurikulum',
  wakasek_kesiswaan: 'Wakasek Kesiswaan',
  wakasek_humas: 'Wakasek Humas',
  wakasek_sarpras: 'Wakasek Sarpras',
  guru: 'Guru',
  wali_kelas: 'Wali Kelas',
  guru_bk: 'Guru BK',
};

const JsaAttendanceNotice = ({ role }) => {
  const normalizedRole = String(role || '').trim().toLowerCase();
  const roleLabel = ROLE_LABELS[normalizedRole] || 'Pegawai';

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Building2 className="h-5 w-5 text-blue-600" />
          <h3 className="text-base font-semibold text-slate-900 lg:text-lg">Status Absensi Pegawai</h3>
        </div>
        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
          <ExternalLink className="h-3.5 w-3.5" />
          Eksternal
        </span>
      </div>

      <div className="rounded-lg border border-blue-100 bg-blue-50/60 p-4">
        <p className="text-sm font-medium text-blue-900">
          Role Anda: {roleLabel}
        </p>
        <p className="mt-1 text-sm text-blue-800">
          Absensi untuk role non-siswa tidak diproses di aplikasi ini.
        </p>
        <p className="mt-1 text-sm text-blue-800">
          Gunakan aplikasi JSA (Pemprov Jawa Barat) untuk proses absensi pegawai.
        </p>
      </div>
    </div>
  );
};

export default JsaAttendanceNotice;

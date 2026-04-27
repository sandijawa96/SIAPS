import React, { useEffect, useMemo, useState } from 'react';
import { CalendarDays, FileText, ShieldCheck } from 'lucide-react';
import { izinService } from '../../services/izinService';
import { toServerDateInput } from '../../services/serverClock';
import useServerClock from '../../hooks/useServerClock';
import { toast } from 'react-hot-toast';

const fallbackJenisIzinOptions = [
  {
    value: 'sakit',
    label: 'Sakit',
    description: 'Tidak masuk karena kondisi kesehatan.',
    group: 'kesehatan',
    group_label: 'Kesehatan',
    evidence_policy: {
      rule: 'required_if_multi_day',
      hint: 'Lampiran opsional untuk sakit 1 hari. Jika sakit lebih dari 1 hari, lampiran wajib.',
      allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
    },
  },
  {
    value: 'izin',
    label: 'Izin Pribadi',
    description: 'Keperluan pribadi mendesak di luar sekolah.',
    group: 'pribadi',
    group_label: 'Pribadi & Keluarga',
    evidence_policy: {
      rule: 'optional',
      hint: 'Lampiran opsional. Unggah jika perlu memperjelas alasan pengajuan.',
      allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
    },
  },
  {
    value: 'keperluan_keluarga',
    label: 'Urusan Keluarga',
    description: 'Mendampingi atau menghadiri kebutuhan keluarga inti.',
    group: 'pribadi',
    group_label: 'Pribadi & Keluarga',
    evidence_policy: {
      rule: 'optional',
      hint: 'Lampiran opsional. Unggah jika perlu memperjelas alasan pengajuan.',
      allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
    },
  },
  {
    value: 'dispensasi',
    label: 'Dispensasi Sekolah',
    description: 'Kegiatan resmi dengan persetujuan atau penugasan sekolah.',
    group: 'sekolah',
    group_label: 'Kegiatan Sekolah',
    evidence_policy: {
      rule: 'optional',
      hint: 'Lampiran opsional. Jika ada surat tugas atau memo sekolah, unggah agar review lebih cepat.',
      allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
    },
  },
  {
    value: 'tugas_sekolah',
    label: 'Tugas Sekolah',
    description: 'Penugasan sekolah di luar kelas atau lokasi belajar biasa.',
    group: 'sekolah',
    group_label: 'Kegiatan Sekolah',
    evidence_policy: {
      rule: 'optional',
      hint: 'Lampiran opsional. Jika ada surat tugas atau memo sekolah, unggah agar review lebih cepat.',
      allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
    },
  },
];

const calculateRequestedDayCount = (startDate, endDate) => {
  const normalizedStart = toServerDateInput(startDate);
  const normalizedEnd = toServerDateInput(endDate);
  if (!normalizedStart || !normalizedEnd) {
    return 0;
  }

  const start = new Date(`${normalizedStart}T00:00:00`);
  const end = new Date(`${normalizedEnd}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
    return 0;
  }

  return Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;
};

const normalizeJenisOptions = (items) => {
  if (!Array.isArray(items)) {
    return fallbackJenisIzinOptions;
  }

  const normalized = items
    .map((item) => ({
      value: item?.value || '',
      label: item?.label || item?.value || '',
      description: item?.description || '',
      group: item?.group || 'lainnya',
      group_label: item?.group_label || 'Lainnya',
      evidence_policy: item?.evidence_policy || {
        rule: 'optional',
        hint: 'Lampiran opsional. Unggah jika perlu memperjelas alasan pengajuan.',
        allowed_label: 'JPG, JPEG, PNG, WEBP, atau PDF',
      },
    }))
    .filter((item) => item.value);

  return normalized.length > 0 ? normalized : fallbackJenisIzinOptions;
};

const IzinForm = ({ onSuccess, onCancel }) => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [loading, setLoading] = useState(false);
  const [jenisOptions, setJenisOptions] = useState(fallbackJenisIzinOptions);
  const [formData, setFormData] = useState({
    jenis_izin: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    dokumen_pendukung: null,
  });

  useEffect(() => {
    const loadJenisOptions = async () => {
      try {
        const response = await izinService.getJenisIzinOptions('siswa');
        setJenisOptions(normalizeJenisOptions(response?.data));
      } catch {
        setJenisOptions(fallbackJenisIzinOptions);
      }
    };

    loadJenisOptions();
  }, []);

  const selectedJenis = useMemo(
    () => jenisOptions.find((item) => item.value === formData.jenis_izin) || null,
    [jenisOptions, formData.jenis_izin],
  );

  const requestedDayCount = useMemo(
    () => calculateRequestedDayCount(formData.tanggal_mulai, formData.tanggal_selesai),
    [formData.tanggal_mulai, formData.tanggal_selesai],
  );

  const evidenceRule = selectedJenis?.evidence_policy?.rule || 'optional';
  const isLampiranRequired = Boolean(
    selectedJenis && (
      evidenceRule === 'required'
      || (evidenceRule === 'required_if_multi_day' && requestedDayCount > 1)
    ),
  );
  const evidenceHint = selectedJenis?.evidence_policy?.hint
    || 'Lampiran opsional. Unggah jika perlu memperjelas alasan pengajuan.';
  const evidenceAllowedLabel = selectedJenis?.evidence_policy?.allowed_label || 'JPG, JPEG, PNG, WEBP, atau PDF';

  const handleInputChange = (event) => {
    const { name, value, type, files } = event.target;

    if (type === 'file') {
      setFormData((prev) => ({
        ...prev,
        [name]: files?.[0] || null,
      }));
      return;
    }

    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const validateForm = () => {
    if (!formData.jenis_izin) {
      toast.error('Jenis izin harus dipilih');
      return false;
    }

    if (!formData.tanggal_mulai) {
      toast.error('Tanggal mulai harus diisi');
      return false;
    }

    if (!formData.tanggal_selesai) {
      toast.error('Tanggal selesai harus diisi');
      return false;
    }

    const startDate = toServerDateInput(formData.tanggal_mulai);
    const endDate = toServerDateInput(formData.tanggal_selesai);
    if (startDate && endDate && endDate < startDate) {
      toast.error('Tanggal selesai tidak boleh lebih awal dari tanggal mulai');
      return false;
    }

    if (!formData.alasan.trim()) {
      toast.error('Alasan harus diisi');
      return false;
    }

    if (formData.alasan.length > 500) {
      toast.error('Alasan tidak boleh lebih dari 500 karakter');
      return false;
    }

    if (isLampiranRequired && !formData.dokumen_pendukung) {
      toast.error('Lampiran pendukung wajib diunggah untuk pengajuan ini');
      return false;
    }

    if (formData.dokumen_pendukung) {
      const file = formData.dokumen_pendukung;
      const allowedTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/pdf',
      ];
      const maxSize = 5 * 1024 * 1024;

      if (!allowedTypes.includes(file.type)) {
        toast.error('Format lampiran harus JPG, JPEG, PNG, WEBP, atau PDF');
        return false;
      }

      if (file.size > maxSize) {
        toast.error('Ukuran lampiran tidak boleh lebih dari 5MB');
        return false;
      }
    }

    return true;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    setLoading(true);

    try {
      await izinService.createIzin(formData);
      toast.success('Pengajuan izin berhasil dikirim dan sedang ditinjau');
      setFormData({
        jenis_izin: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        dokumen_pendukung: null,
      });

      if (onSuccess) {
        onSuccess();
      }
    } catch (error) {
      console.error('Error submitting izin:', error);
      toast.error(error.response?.data?.message || 'Gagal mengajukan izin');
    } finally {
      setLoading(false);
    }
  };

  const handleReset = () => {
    setFormData({
      jenis_izin: '',
      tanggal_mulai: '',
      tanggal_selesai: '',
      alasan: '',
      dokumen_pendukung: null,
    });
  };

  const today = isServerClockSynced ? serverDate : '';

  return (
    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-xl font-semibold text-gray-900">Ajukan Izin Siswa</h2>
          <p className="text-sm text-gray-600 mt-1">
            Isi kategori, rentang tanggal, dan alasan singkat. Hari non-sekolah tidak akan ditandai saat approval.
          </p>
        </div>
        {onCancel && (
          <button
            onClick={onCancel}
            className="text-gray-500 hover:text-gray-700"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="grid grid-cols-1 lg:grid-cols-[1.15fr_0.85fr] gap-6">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Jenis Izin <span className="text-red-500">*</span>
              </label>
              <select
                name="jenis_izin"
                value={formData.jenis_izin}
                onChange={handleInputChange}
                className="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              >
                <option value="">Pilih kategori izin</option>
                {jenisOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              {selectedJenis && (
                <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <div className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <ShieldCheck className="w-4 h-4" />
                    {selectedJenis.group_label}
                  </div>
                  <p className="mt-1 text-sm text-slate-800">{selectedJenis.description}</p>
                </div>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Tanggal Mulai <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  name="tanggal_mulai"
                  value={formData.tanggal_mulai}
                  onChange={handleInputChange}
                  min={today}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Tanggal Selesai <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  name="tanggal_selesai"
                  value={formData.tanggal_selesai}
                  onChange={handleInputChange}
                  min={formData.tanggal_mulai || today}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Alasan <span className="text-red-500">*</span>
              </label>
              <textarea
                name="alasan"
                value={formData.alasan}
                onChange={handleInputChange}
                rows={4}
                maxLength={500}
                placeholder="Jelaskan alasan pengajuan secara singkat dan jelas."
                className="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                required
              />
              <div className="text-right text-sm text-gray-500 mt-1">
                {formData.alasan.length}/500 karakter
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Lampiran Pendukung {isLampiranRequired && <span className="text-red-500">*</span>}
              </label>
              <input
                type="file"
                name="dokumen_pendukung"
                onChange={handleInputChange}
                accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required={isLampiranRequired}
              />
              <div className="mt-2 text-sm text-gray-500 space-y-1">
                <p>{evidenceHint}</p>
                <p>Format yang diterima: {evidenceAllowedLabel}. Maksimal 5MB.</p>
                {formData.dokumen_pendukung && (
                  <p className="text-slate-700">File terpilih: {formData.dokumen_pendukung.name}</p>
                )}
              </div>
            </div>
          </div>

          <div className="space-y-4">
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <CalendarDays className="w-4 h-4" />
                Ringkasan Rentang
              </div>
              <div className="mt-4 space-y-3">
                <div className="flex items-baseline justify-between gap-4">
                  <span className="text-sm text-slate-600">Rentang diajukan</span>
                  <span className="text-lg font-semibold text-slate-900">
                    {requestedDayCount > 0 ? `${requestedDayCount} hari` : '-'}
                  </span>
                </div>
                <p className="text-sm text-slate-600">
                  Hari non-sekolah tidak akan ditandai saat izin diproses. Untuk sekolah, dampak final dihitung saat approval.
                </p>
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <FileText className="w-4 h-4" />
                Panduan Singkat
              </div>
              <ul className="mt-3 space-y-2 text-sm text-slate-600">
                <li>Ajukan satu pengajuan untuk satu rentang kebutuhan yang sama.</li>
                <li>Gunakan alasan yang singkat dan spesifik agar approval lebih cepat.</li>
                <li>Jika sakit lebih dari 1 hari, lampiran pendukung wajib disertakan.</li>
              </ul>
            </div>
          </div>
        </div>

        <div className="flex justify-end space-x-3 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={handleReset}
            className="px-4 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors"
          >
            Reset
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {loading ? 'Mengirim...' : 'Ajukan Izin'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default IzinForm;

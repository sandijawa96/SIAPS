import React, { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle, Download, Eye, FileText, ShieldCheck, X, XCircle } from 'lucide-react';
import { formatDate, formatDateRange, getDaysBetween } from '../../utils/dateUtils';
import { resolveProfilePhotoUrl } from '../../utils/profilePhoto';

const rejectReasonOptions = [
  'Dokumen belum jelas',
  'Tanggal pengajuan belum sesuai',
  'Alasan pengajuan belum cukup jelas',
  'Silakan ajukan ulang dengan data yang lebih lengkap',
];

const fieldClassName =
  'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';
const labelClassName = 'block text-sm font-semibold text-gray-700 mb-2';

const getStatusMeta = (status, statusLabel) => {
  const normalized = String(status || '').toLowerCase();
  const map = {
    pending: { label: 'Menunggu Persetujuan', chipClass: 'bg-yellow-100 text-yellow-800' },
    approved: { label: 'Disetujui', chipClass: 'bg-green-100 text-green-800' },
    rejected: { label: 'Ditolak', chipClass: 'bg-red-100 text-red-800' },
  };

  const fallback = map[normalized] || { label: status || '-', chipClass: 'bg-gray-100 text-gray-700' };
  return {
    ...fallback,
    label: statusLabel || fallback.label,
  };
};

const resolveSubject = (izin, type) => {
  const name = izin?.user?.nama_lengkap
    || izin?.nama
    || izin?.pegawai?.nama
    || izin?.siswa?.nama
    || '-';

  const identifier = izin?.user?.nip
    || izin?.user?.nisn
    || izin?.nip
    || izin?.nisn
    || '-';

  const classLabel = izin?.kelas?.nama_kelas
    || izin?.kelas
    || izin?.siswa?.kelas?.nama
    || '-';

  const typeLabel = type === 'siswa' ? 'Siswa' : 'Pegawai';

  return {
    name,
    identifier,
    classLabel,
    label: typeLabel,
  };
};

const resolveJenis = (izin) => {
  if (typeof izin?.jenis_izin_label === 'string' && izin.jenis_izin_label.trim() !== '') {
    return izin.jenis_izin_label;
  }

  if (typeof izin?.jenis_izin === 'string') {
    return izin.jenis_izin;
  }

  return izin?.jenis_izin?.nama || izin?.jenis_cuti?.nama || '-';
};

const getActionMeta = (action) => {
  if (action === 'approve') {
    return {
      label: 'Mode Persetujuan',
      chipClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      textareaClass: 'border-emerald-200 bg-emerald-50/40',
      hint: 'Pastikan data valid sebelum menyetujui pengajuan.',
    };
  }

  if (action === 'reject') {
    return {
      label: 'Mode Penolakan',
      chipClass: 'bg-rose-50 text-rose-700 border border-rose-200',
      textareaClass: 'border-rose-200 bg-rose-50/40',
      hint: 'Tuliskan alasan penolakan yang jelas agar pemohon dapat menindaklanjuti.',
    };
  }

  return {
    label: 'Mode Detail',
    chipClass: 'bg-blue-50 text-blue-700 border border-blue-200',
    textareaClass: 'border-gray-200 bg-gray-50/40',
    hint: 'Mode baca saja untuk meninjau detail pengajuan.',
  };
};

const getPendingReviewAlert = (izin) => {
  const state = String(izin?.pending_review_state || '').toLowerCase();

  if (state === 'overdue') {
    return {
      className: 'border-rose-200 bg-rose-50 text-rose-800',
      iconClassName: 'text-rose-600',
      title: izin?.pending_review_label || 'Pengajuan ini terlambat direview',
      description: 'Jika disetujui sekarang, sistem tetap akan mencoba menandai absensi secara retroaktif pada hari kerja yang relevan.',
    };
  }

  if (state === 'due_today') {
    return {
      className: 'border-amber-200 bg-amber-50 text-amber-800',
      iconClassName: 'text-amber-600',
      title: izin?.pending_review_label || 'Periode izin mulai hari ini',
      description: 'Tinjau segera agar status kehadiran siswa tidak tertinggal dari pengajuan yang sudah masuk.',
    };
  }

  return null;
};

const resolveDocumentImageUrl = (izin) => resolveProfilePhotoUrl(
  izin?.dokumen_pendukung_url || izin?.dokumen_pendukung
);

const isPdfDocument = (izin) => String(
  izin?.dokumen_pendukung_url || izin?.dokumen_pendukung || ''
).toLowerCase().endsWith('.pdf');

const IzinApprovalModal = ({
  isOpen,
  onClose,
  izin,
  action,
  onSubmit,
  type = 'siswa',
}) => {
  const [catatan, setCatatan] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (isOpen) {
      setCatatan(izin?.catatan || izin?.catatan_approval || '');
      setError('');
    }
  }, [isOpen, izin]);

  const modalTitle = useMemo(() => {
    switch (action) {
      case 'approve':
        return `Setujui Izin ${type === 'siswa' ? 'Siswa' : 'Pegawai'}`;
      case 'reject':
        return `Tolak Izin ${type === 'siswa' ? 'Siswa' : 'Pegawai'}`;
      case 'view':
        return `Detail Izin ${type === 'siswa' ? 'Siswa' : 'Pegawai'}`;
      default:
        return `Izin ${type === 'siswa' ? 'Siswa' : 'Pegawai'}`;
    }
  }, [action, type]);

  if (!isOpen || !izin) {
    return null;
  }

  const statusMeta = getStatusMeta(izin.status, izin.status_label);
  const subject = resolveSubject(izin, type);
  const isViewOnly = action === 'view';
  const isApproval = action === 'approve' || action === 'reject';
  const actionMeta = getActionMeta(action);
  const documentImageUrl = resolveDocumentImageUrl(izin);
  const documentIsPdf = isPdfDocument(izin);
  const pendingReviewAlert = getPendingReviewAlert(izin);

  const handleSubmit = async () => {
    if (!isApproval) {
      return;
    }

    if (action === 'reject' && !catatan.trim()) {
      setError('Catatan penolakan wajib diisi');
      return;
    }

    setLoading(true);
    setError('');

    try {
      await onSubmit({
        catatan: catatan.trim(),
        catatan_approval: catatan.trim(),
      });
      setCatatan('');
    } catch (submitError) {
      console.error('Error submitting approval:', submitError);
      setError(submitError?.message || 'Gagal memproses data izin');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4 py-8">
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => !loading && onClose()} />

        <div className="relative inline-block w-full max-w-3xl overflow-hidden bg-white shadow-2xl rounded-2xl">
          <div className="px-6 py-5 bg-gradient-to-r from-blue-600 to-indigo-700">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-white/20">
                  {isViewOnly ? <Eye className="w-5 h-5 text-white" /> : <FileText className="w-5 h-5 text-white" />}
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">{modalTitle}</h3>
                  <p className="text-sm text-blue-100">Review data izin sebelum diproses</p>
                  <div className="mt-2">
                    <span className={`inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full ${actionMeta.chipClass}`}>
                      <ShieldCheck className="w-3.5 h-3.5" />
                      {actionMeta.label}
                    </span>
                  </div>
                </div>
              </div>
              <button
                type="button"
                onClick={() => !loading && onClose()}
                className="p-2 transition-colors rounded-lg hover:bg-white/20"
                disabled={loading}
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          <div className="px-6 py-6 max-h-[72vh] overflow-y-auto space-y-5">
            <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
              <h4 className="text-sm font-semibold text-gray-900">Profil Pemohon</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                  <label className={labelClassName}>Nama {subject.label}</label>
                  <input className={fieldClassName} value={subject.name} readOnly />
                </div>

                <div>
                  <label className={labelClassName}>{type === 'siswa' ? 'NISN' : 'NIP'}</label>
                  <input className={fieldClassName} value={subject.identifier} readOnly />
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>{type === 'siswa' ? 'Kelas' : 'Unit/Departemen'}</label>
                  <input className={fieldClassName} value={subject.classLabel} readOnly />
                </div>
              </div>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
              <h4 className="text-sm font-semibold text-gray-900">Detail Pengajuan</h4>
              {pendingReviewAlert && (
                <div className={`rounded-xl border px-4 py-3 ${pendingReviewAlert.className}`}>
                  <div className="flex items-start gap-3">
                    <AlertTriangle className={`mt-0.5 h-4 w-4 ${pendingReviewAlert.iconClassName}`} />
                    <div>
                      <div className="text-sm font-semibold">{pendingReviewAlert.title}</div>
                      <div className="mt-1 text-xs leading-5">{pendingReviewAlert.description}</div>
                    </div>
                  </div>
                </div>
              )}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                  <label className={labelClassName}>Jenis Izin</label>
                  <input className={fieldClassName} value={resolveJenis(izin)} readOnly />
                </div>

                <div>
                  <label className={labelClassName}>Status</label>
                  <div className="mt-1">
                    <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${statusMeta.chipClass}`}>
                      {statusMeta.label}
                    </span>
                  </div>
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>Periode Izin</label>
                  <input
                    className={fieldClassName}
                    value={formatDateRange(izin?.tanggal_mulai, izin?.tanggal_selesai)}
                    readOnly
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Durasi: {getDaysBetween(izin?.tanggal_mulai, izin?.tanggal_selesai)} hari
                  </p>
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>Dampak Kehadiran</label>
                  <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <div className="font-semibold text-slate-900">
                      {typeof izin?.school_days_affected === 'number'
                        ? `${izin.school_days_affected} hari sekolah terdampak`
                        : 'Hari sekolah terdampak akan dihitung saat diproses'}
                    </div>
                    <div className="mt-1">
                      {typeof izin?.non_working_days_skipped === 'number' && izin.non_working_days_skipped > 0
                        ? `${izin.non_working_days_skipped} hari non-sekolah tidak akan ditandai.`
                        : 'Hari non-sekolah tidak akan ditandai sebagai izin.'}
                    </div>
                  </div>
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>Alasan</label>
                  <textarea className={fieldClassName} rows={3} value={izin?.alasan || '-'} readOnly />
                </div>
              </div>
            </div>

            {izin?.dokumen_pendukung && (
              <div className="p-4 border border-gray-200 rounded-xl bg-gray-50 space-y-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-gray-700">Lampiran Pendukung</p>
                    <p className="text-xs text-gray-500">{izin?.dokumen_pendukung_nama || 'Lampiran tersedia'}</p>
                  </div>
                  <button
                    type="button"
                    className="px-4 py-2 text-sm font-medium text-blue-700 bg-white border border-blue-200 rounded-lg hover:bg-blue-50"
                    onClick={() => window.open(`/api/izin/${izin.id}/document`, '_blank', 'noopener,noreferrer')}
                  >
                    <span className="inline-flex items-center gap-2">
                      <Download className="w-4 h-4" />
                      Unduh
                    </span>
                  </button>
                </div>

                {documentImageUrl && !documentIsPdf && (
                  <a
                    href={documentImageUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block"
                  >
                    <img
                      src={documentImageUrl}
                      alt={`Bukti dukung izin ${subject.name}`}
                      className="w-full max-h-80 object-contain rounded-xl border border-gray-200 bg-white"
                    />
                  </a>
                )}

                {documentIsPdf && (
                  <div className="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
                    Lampiran berupa PDF. Gunakan tombol unduh untuk membukanya.
                  </div>
                )}
              </div>
            )}

            {isViewOnly && (izin?.catatan || izin?.catatan_approval) && (
              <div className="rounded-xl border border-gray-200 bg-white p-4">
                <label className={labelClassName}>Catatan</label>
                <textarea className={fieldClassName} rows={3} value={izin?.catatan || izin?.catatan_approval} readOnly />
              </div>
            )}

            {isApproval && (
              <div className={`rounded-xl border p-4 ${actionMeta.textareaClass}`}>
                <label className={labelClassName}>
                  Catatan {action === 'approve' ? 'Persetujuan' : 'Penolakan'}
                  {action === 'reject' && ' *'}
                </label>
                {action === 'reject' && (
                  <div className="mb-3 flex flex-wrap gap-2">
                    {rejectReasonOptions.map((option) => (
                      <button
                        key={option}
                        type="button"
                        onClick={() => {
                          setCatatan(option);
                          if (error) {
                            setError('');
                          }
                        }}
                        className="rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                      >
                        {option}
                      </button>
                    ))}
                  </div>
                )}
                <textarea
                  className={`${fieldClassName} bg-white`}
                  rows={4}
                  value={catatan}
                  onChange={(event) => {
                    setCatatan(event.target.value);
                    if (error) {
                      setError('');
                    }
                  }}
                  placeholder={
                    action === 'approve'
                      ? 'Catatan persetujuan opsional...'
                      : 'Masukkan alasan penolakan...'
                  }
                />
                <div className="mt-2 text-xs text-gray-600 flex items-start gap-1.5">
                  <AlertTriangle className="w-3.5 h-3.5 mt-0.5" />
                  <span>{actionMeta.hint}</span>
                </div>
              </div>
            )}

            {error && (
              <div className="px-4 py-3 text-sm text-red-700 border border-red-200 rounded-xl bg-red-50">
                {error}
              </div>
            )}

            <div className="text-xs text-gray-500">Tanggal pengajuan: {formatDate(izin?.created_at)}</div>
          </div>

          <div className="px-6 py-4 bg-gray-50 border-t">
            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={onClose}
                className="px-6 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50"
                disabled={loading}
              >
                {isViewOnly ? 'Tutup' : 'Batal'}
              </button>

              {isApproval && (
                <button
                  type="button"
                  onClick={handleSubmit}
                  className={`px-6 py-2 text-sm font-medium text-white transition-all rounded-xl disabled:opacity-70 ${
                    action === 'approve'
                      ? 'bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700'
                      : 'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700'
                  }`}
                  disabled={loading}
                >
                  <span className="inline-flex items-center gap-2">
                    {action === 'approve' ? <CheckCircle className="w-4 h-4" /> : <XCircle className="w-4 h-4" />}
                    {loading ? 'Memproses...' : action === 'approve' ? 'Setujui' : 'Tolak'}
                  </span>
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export { IzinApprovalModal };
export default IzinApprovalModal;

import React, { useEffect, useState } from 'react';
import { AlertCircle, Calendar, Clock, FileText, User, X } from 'lucide-react';
import { useServerClock } from '../hooks/useServerClock';
import { formatServerDateTime } from '../services/serverClock';

const fieldClassName =
  'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white';
const labelClassName = 'block text-sm font-semibold text-gray-700 mb-2';

const extractTimeInputValue = (value) => {
  if (!value) {
    return '';
  }

  const raw = String(value).trim();
  if (!raw) {
    return '';
  }

  const directTimeMatch = raw.match(/^(\d{2}:\d{2})(?::\d{2})?$/);
  if (directTimeMatch) {
    return directTimeMatch[1];
  }

  const isoTimeMatch = raw.match(/T(\d{2}:\d{2})(?::\d{2})?/);
  if (isoTimeMatch) {
    return isoTimeMatch[1];
  }

  const genericTimeMatch = raw.match(/\b(\d{2}:\d{2})(?::\d{2})?\b/);
  if (genericTimeMatch) {
    return genericTimeMatch[1];
  }

  return '';
};

const getAuditEntries = (attendance) => {
  if (Array.isArray(attendance?.audit_logs)) {
    return attendance.audit_logs;
  }

  if (Array.isArray(attendance?.auditLogs)) {
    return attendance.auditLogs;
  }

  return [];
};

const getLeaveApprovalAuditInfo = (attendance) => {
  const auditEntries = getAuditEntries(attendance);
  if (auditEntries.length === 0) {
    return null;
  }

  const sorted = [...auditEntries].sort((left, right) => {
    const leftTime = Date.parse(left?.performed_at || left?.created_at || '') || 0;
    const rightTime = Date.parse(right?.performed_at || right?.created_at || '') || 0;
    return rightTime - leftTime;
  });

  const matched = sorted.find((entry) => entry?.metadata?.source === 'leave_approval');
  if (!matched) {
    return null;
  }

  return {
    izinId: matched?.metadata?.izin_id || attendance?.izin_id || null,
    previousStatus: matched?.metadata?.previous_status || null,
    resolvedStatus: matched?.metadata?.resolved_status || attendance?.status || null,
    performedAt: matched?.performed_at || matched?.created_at || null,
  };
};

const ManualAttendanceModal = ({
  isOpen,
  onClose,
  onSubmit,
  users = [],
  initialData = null,
  title = 'Tambah Absensi Manual',
}) => {
  const [formData, setFormData] = useState({
    user_id: '',
    tanggal: '',
    jam_masuk: '',
    jam_pulang: '',
    status: '',
    keterangan: '',
    reason: '',
  });

  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const { serverDate } = useServerClock();

  useEffect(() => {
    if (initialData) {
      setFormData({
        user_id: initialData.user_id || initialData.user?.id || '',
        tanggal: initialData.tanggal || '',
        jam_masuk: extractTimeInputValue(initialData.jam_masuk),
        jam_pulang: extractTimeInputValue(initialData.jam_pulang),
        status: initialData.status || '',
        keterangan: initialData.keterangan || '',
        reason: initialData.reason || '',
      });
    } else {
      setFormData({
        user_id: '',
        tanggal: serverDate,
        jam_masuk: '',
        jam_pulang: '',
        status: '',
        keterangan: '',
        reason: '',
      });
    }

    setErrors({});
  }, [initialData, isOpen, serverDate]);

  if (!isOpen) {
    return null;
  }

  const leaveApprovalInfo = getLeaveApprovalAuditInfo(initialData);

  const updateField = (field, value) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));

    if (errors[field]) {
      setErrors((prev) => ({
        ...prev,
        [field]: '',
      }));
    }
  };

  const validateForm = () => {
    const nextErrors = {};

    if (!formData.user_id) {
      nextErrors.user_id = 'Pengguna harus dipilih';
    }
    if (!formData.tanggal) {
      nextErrors.tanggal = 'Tanggal harus diisi';
    }
    if (!formData.status) {
      nextErrors.status = 'Status harus dipilih';
    }
    if (!formData.reason || formData.reason.trim().length < 5) {
      nextErrors.reason = 'Alasan pembuatan manual wajib diisi';
    }

    return nextErrors;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    const validationErrors = validateForm();
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    setLoading(true);
    try {
      await onSubmit(formData);
      onClose();
    } catch (submitError) {
      console.error('Error submitting manual attendance:', submitError);
      if (submitError?.response?.data?.errors) {
        setErrors(submitError.response.data.errors);
      }
    } finally {
      setLoading(false);
    }
  };

  const statusOptions = [
    { value: 'hadir', label: 'Hadir' },
    { value: 'terlambat', label: 'Terlambat' },
    { value: 'izin', label: 'Izin' },
    { value: 'sakit', label: 'Sakit' },
    { value: 'alpha', label: 'Alpha' },
  ];

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4 py-8">
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => !loading && onClose()} />

        <div className="relative inline-block w-full max-w-2xl overflow-hidden bg-white shadow-2xl rounded-2xl">
          <div className="px-6 py-5 bg-gradient-to-r from-blue-600 to-indigo-700">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-white/20">
                  <User className="w-5 h-5 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">{title}</h3>
                  <p className="text-sm text-blue-100">Isi data absensi manual dengan lengkap dan valid</p>
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

          <form onSubmit={handleSubmit}>
            <div className="px-6 py-6 max-h-[72vh] overflow-y-auto">
              {leaveApprovalInfo && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                  <div className="text-sm font-semibold text-emerald-900">Sumber data: approval izin</div>
                  <div className="mt-1 text-sm text-emerald-800">
                    Absensi ini diterapkan dari approval izin#{leaveApprovalInfo.izinId || '-'}
                    {leaveApprovalInfo.previousStatus ? ` dan menggantikan status ${leaveApprovalInfo.previousStatus}` : ''}.
                  </div>
                  {leaveApprovalInfo.performedAt && (
                    <div className="mt-1 text-xs text-emerald-700">
                      Dicatat pada {formatServerDateTime(leaveApprovalInfo.performedAt, 'id-ID') || '-'}
                    </div>
                  )}
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div className="md:col-span-2">
                  <label className={labelClassName}>Pengguna *</label>
                  <div className="relative">
                    <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                      <User className="w-5 h-5" />
                    </div>
                    <select
                      className={fieldClassName}
                      value={formData.user_id}
                      onChange={(event) => updateField('user_id', event.target.value)}
                      disabled={Boolean(initialData) || loading}
                    >
                      <option value="">Pilih pengguna...</option>
                      {users.map((user) => (
                        <option key={user.id} value={String(user.id)}>
                          {user.nama_lengkap || user.name || '-'} - {user.email || '-'}
                        </option>
                      ))}
                    </select>
                  </div>
                  {errors.user_id && <p className="mt-1 text-sm text-red-600">{errors.user_id}</p>}
                </div>

                <div>
                  <label className={labelClassName}>Tanggal *</label>
                  <div className="relative">
                    <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                      <Calendar className="w-5 h-5" />
                    </div>
                    <input
                      type="date"
                      className={fieldClassName}
                      value={formData.tanggal}
                      onChange={(event) => updateField('tanggal', event.target.value)}
                      max={serverDate}
                      disabled={Boolean(initialData) || loading}
                    />
                  </div>
                  {errors.tanggal && <p className="mt-1 text-sm text-red-600">{errors.tanggal}</p>}
                </div>

                <div>
                  <label className={labelClassName}>Status *</label>
                  <div className="relative">
                    <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                      <AlertCircle className="w-5 h-5" />
                    </div>
                    <select
                      className={fieldClassName}
                      value={formData.status}
                      onChange={(event) => updateField('status', event.target.value)}
                    >
                      <option value="">Pilih status...</option>
                      {statusOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  {errors.status && <p className="mt-1 text-sm text-red-600">{errors.status}</p>}
                </div>

                <div>
                  <label className={labelClassName}>Jam Masuk</label>
                  <div className="relative">
                    <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                      <Clock className="w-5 h-5" />
                    </div>
                    <input
                      type="time"
                      className={fieldClassName}
                      value={formData.jam_masuk}
                      onChange={(event) => updateField('jam_masuk', event.target.value)}
                    />
                  </div>
                  {errors.jam_masuk && <p className="mt-1 text-sm text-red-600">{errors.jam_masuk}</p>}
                </div>

                <div>
                  <label className={labelClassName}>Jam Pulang</label>
                  <div className="relative">
                    <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                      <Clock className="w-5 h-5" />
                    </div>
                    <input
                      type="time"
                      className={fieldClassName}
                      value={formData.jam_pulang}
                      onChange={(event) => updateField('jam_pulang', event.target.value)}
                    />
                  </div>
                  {errors.jam_pulang && <p className="mt-1 text-sm text-red-600">{errors.jam_pulang}</p>}
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>Keterangan</label>
                  <div className="relative">
                    <div className="absolute left-3 top-4 text-gray-400">
                      <FileText className="w-5 h-5" />
                    </div>
                    <textarea
                      className={fieldClassName.replace('py-3', 'py-3 min-h-[90px]')}
                      value={formData.keterangan}
                      onChange={(event) => updateField('keterangan', event.target.value)}
                      placeholder="Keterangan tambahan (opsional)..."
                      rows={3}
                    />
                  </div>
                </div>

                <div className="md:col-span-2">
                  <label className={labelClassName}>Alasan Pembuatan Manual *</label>
                  <div className="relative">
                    <div className="absolute left-3 top-4 text-gray-400">
                      <FileText className="w-5 h-5" />
                    </div>
                    <textarea
                      className={fieldClassName.replace('py-3', 'py-3 min-h-[110px]')}
                      value={formData.reason}
                      onChange={(event) => updateField('reason', event.target.value)}
                      placeholder="Jelaskan alasan pembuatan absensi manual..."
                      rows={4}
                    />
                  </div>
                  {errors.reason && <p className="mt-1 text-sm text-red-600">{errors.reason}</p>}
                </div>
              </div>

              {Object.keys(errors).length > 0 && (
                <div className="p-4 mt-5 border border-red-200 rounded-xl bg-red-50">
                  <div className="flex items-start gap-2">
                    <AlertCircle className="w-5 h-5 mt-0.5 text-red-500" />
                    <p className="text-sm text-red-700">
                      Mohon periksa kembali data. Masih ada field yang belum valid.
                    </p>
                  </div>
                </div>
              )}
            </div>

            <div className="px-6 py-4 bg-gray-50 border-t">
              <div className="flex justify-end gap-3">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-6 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50"
                  disabled={loading}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-6 py-2 text-sm font-medium text-white transition-all bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl hover:from-blue-700 hover:to-indigo-700 disabled:opacity-70"
                  disabled={loading}
                >
                  {loading ? 'Menyimpan...' : initialData ? 'Simpan Perubahan' : 'Simpan'}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default ManualAttendanceModal;

import React, { useEffect, useMemo, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  Check,
  CheckCircle2,
  GraduationCap,
  Search,
  Settings,
  UserCheck,
  Users,
  X,
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import api from '../../services/api';
import attendanceSchemaService from '../../services/attendanceSchemaService';
import useServerClock from '../../hooks/useServerClock';

const normalizeRole = (roleName) =>
  String(roleName || '')
    .trim()
    .toLowerCase()
    .replace(/[_\s]+/g, ' ');

const isStudentAttendanceUser = (user) => {
  const roles = Array.isArray(user?.roles) ? user.roles : [];
  const hasStudentRole = roles.some((role) => {
    const roleName = role?.name || role?.display_name;
    return normalizeRole(roleName) === 'siswa';
  });

  return hasStudentRole || Boolean(user?.nis) || Boolean(user?.nisn);
};

const steps = [
  { key: 'schema', label: 'Pilih Skema' },
  { key: 'students', label: 'Pilih Siswa' },
  { key: 'confirm', label: 'Konfirmasi' },
];

const BulkAssignment = ({ onComplete, onCancel }) => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [step, setStep] = useState(1);
  const [schemas, setSchemas] = useState([]);
  const [users, setUsers] = useState([]);
  const [selectedSchema, setSelectedSchema] = useState(null);
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [loadingProgress, setLoadingProgress] = useState(0);
  const [assignmentOptions, setAssignmentOptions] = useState({
    start_date: '',
    end_date: '',
    notes: '',
  });

  useEffect(() => {
    if (!isServerClockSynced || !serverDate) {
      return;
    }

    setAssignmentOptions((current) => ({
      ...current,
      start_date: current.start_date || serverDate,
    }));
  }, [isServerClockSynced, serverDate]);

  const [currentPage, setCurrentPage] = useState(1);
  const usersPerPage = 20;

  useEffect(() => {
    loadSchemas();
    loadUsers();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm]);

  const loadSchemas = async () => {
    try {
      setLoading(true);
      const response = await attendanceSchemaService.getSchemas();
      if (Array.isArray(response)) {
        setSchemas(response.filter((schema) => schema.is_active));
      } else {
        setSchemas([]);
      }
    } catch (error) {
      console.error('Error loading schemas:', error);
      toast.error('Gagal memuat daftar skema');
      setSchemas([]);
    } finally {
      setLoading(false);
    }
  };

  const loadUsers = async () => {
    try {
      setLoading(true);
      setLoadingProgress(0);

      let allUsers = [];
      let page = 1;
      let hasMore = true;

      while (hasMore) {
        const response = await api.get(`/users?per_page=100&page=${page}`, {
          timeout: 15000,
        });

        const payload = response?.data?.data;

        if (payload && typeof payload === 'object' && Array.isArray(payload.data)) {
          allUsers = [...allUsers, ...payload.data];
          setLoadingProgress(Math.round((Number(payload.current_page || page) / Number(payload.last_page || page)) * 100));
          hasMore = Number(payload.current_page || page) < Number(payload.last_page || page);
          page += 1;
        } else if (Array.isArray(payload)) {
          allUsers = [...allUsers, ...payload];
          hasMore = false;
          setLoadingProgress(100);
        } else {
          hasMore = false;
          setLoadingProgress(100);
        }

        if (hasMore) {
          await new Promise((resolve) => setTimeout(resolve, 80));
        }
      }

      setUsers(allUsers);
      toast.success(`Berhasil memuat ${allUsers.length} akun`);
    } catch (error) {
      console.error('Error loading users:', error);
      if (error.code === 'ECONNABORTED') {
        toast.error('Timeout saat memuat akun siswa');
      } else {
        toast.error('Gagal memuat daftar akun siswa');
      }
      setUsers([]);
    } finally {
      setLoading(false);
      setLoadingProgress(0);
    }
  };

  const filteredUsers = useMemo(() => {
    return users.filter((user) => {
      if (!isStudentAttendanceUser(user)) return false;

      if (!searchTerm) return true;

      const keyword = searchTerm.toLowerCase();
      return (
        user.nama_lengkap?.toLowerCase().includes(keyword) ||
        user.email?.toLowerCase().includes(keyword) ||
        user.nis?.toLowerCase().includes(keyword) ||
        user.nisn?.toLowerCase().includes(keyword)
      );
    });
  }, [users, searchTerm]);

  const totalPages = Math.max(1, Math.ceil(filteredUsers.length / usersPerPage));
  const startIndex = (currentPage - 1) * usersPerPage;
  const endIndex = startIndex + usersPerPage;
  const currentUsers = filteredUsers.slice(startIndex, endIndex);

  const toggleUser = (userId, checked) => {
    setSelectedUsers((prev) => {
      if (checked) return [...new Set([...prev, userId])];
      return prev.filter((id) => id !== userId);
    });
  };

  const selectAllFiltered = () => {
    setSelectedUsers(filteredUsers.map((user) => user.id));
  };

  const clearSelection = () => setSelectedUsers([]);

  const handleBulkAssign = async () => {
    if (!selectedSchema || selectedUsers.length === 0) {
      toast.error('Pilih skema dan minimal 1 siswa');
      return;
    }

    try {
      setLoading(true);
      await attendanceSchemaService.bulkAssign(selectedSchema.id, {
        user_ids: selectedUsers,
        start_date: assignmentOptions.start_date,
        end_date: assignmentOptions.end_date || null,
        notes: assignmentOptions.notes,
        assignment_type: 'bulk',
      });
      toast.success(`Skema diterapkan ke ${selectedUsers.length} siswa`);
      onComplete?.();
    } catch (error) {
      console.error('Error bulk assigning schema:', error);
      toast.error(error?.response?.data?.message || 'Gagal menerapkan skema ke siswa');
    } finally {
      setLoading(false);
    }
  };

  const goNext = () => {
    if (step === 1 && !selectedSchema) {
      toast.error('Pilih skema terlebih dahulu');
      return;
    }
    if (step === 2 && selectedUsers.length === 0) {
      toast.error('Pilih minimal satu siswa');
      return;
    }
    setStep((prev) => Math.min(3, prev + 1));
  };

  const goBack = () => setStep((prev) => Math.max(1, prev - 1));

  const selectedUsersPreview = useMemo(() => {
    if (selectedUsers.length === 0) return [];
    const idSet = new Set(selectedUsers);
    return filteredUsers.filter((user) => idSet.has(user.id)).slice(0, 8);
  }, [filteredUsers, selectedUsers]);

  const renderStepSchema = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Pilih Skema</h3>
        <p className="text-sm text-gray-600 mt-1">Pilih skema absensi siswa yang akan diterapkan secara massal.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {schemas.map((schema) => {
          const selected = selectedSchema?.id === schema.id;
          return (
            <button
              key={schema.id}
              type="button"
              onClick={() => setSelectedSchema(schema)}
              className={`text-left rounded-xl border p-4 transition ${
                selected ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300 bg-white'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <Settings className="h-4 w-4 text-gray-500" />
                {selected && <CheckCircle2 className="h-4 w-4 text-blue-600" />}
              </div>
              <p className="mt-2 text-sm font-semibold text-gray-900">{schema.schema_name}</p>
              <div className="mt-3 space-y-1 text-xs text-gray-600">
                <p>Jam: {schema.jam_masuk_default} - {schema.jam_pulang_default}</p>
                <p>Toleransi: {schema.toleransi_default} menit</p>
              </div>
              <div className="mt-3 flex items-center gap-2 text-[11px]">
                {schema.wajib_gps && <span className="px-2 py-1 rounded bg-emerald-100 text-emerald-700">GPS</span>}
                {schema.wajib_foto && <span className="px-2 py-1 rounded bg-blue-100 text-blue-700">Selfie</span>}
              </div>
            </button>
          );
        })}
      </div>

      {schemas.length === 0 && !loading && (
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
          Belum ada skema aktif.
        </div>
      )}
    </div>
  );

  const renderStepStudents = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Pilih Siswa</h3>
        <p className="text-sm text-gray-600 mt-1">Cari dan pilih siswa yang akan menerima skema.</p>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-3 items-center">
          <div className="relative">
            <Search className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              placeholder="Cari nama, email, NIS, atau NISN..."
              className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <button
            type="button"
            onClick={selectAllFiltered}
            className="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
          >
            <UserCheck className="h-4 w-4" />
            Pilih Semua
          </button>
          <button
            type="button"
            onClick={clearSelection}
            className="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
          >
            <X className="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>

      {loading && loadingProgress > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex items-center justify-between text-xs text-gray-600 mb-1">
            <span>Memuat data siswa...</span>
            <span>{loadingProgress}%</span>
          </div>
          <div className="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className="h-full bg-blue-600 transition-all" style={{ width: `${loadingProgress}%` }} />
          </div>
        </div>
      )}

      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div className="px-4 py-3 border-b bg-gray-50 flex items-center justify-between text-sm">
          <span className="font-medium text-gray-900">{filteredUsers.length} siswa ditemukan</span>
          <span className="text-gray-600">{selectedUsers.length} dipilih</span>
        </div>

        <div className="max-h-[420px] overflow-y-auto divide-y">
          {currentUsers.map((user) => {
            const checked = selectedUsers.includes(user.id);
            return (
              <label key={user.id} className={`flex items-start gap-3 px-4 py-3 cursor-pointer ${checked ? 'bg-blue-50' : 'hover:bg-gray-50'}`}>
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={(event) => toggleUser(user.id, event.target.checked)}
                  className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-1"
                />
                <div className="min-w-0">
                  <p className="text-sm font-medium text-gray-900">{user.nama_lengkap || `Siswa ${user.id}`}</p>
                  <p className="text-xs text-gray-500 mt-0.5">{user.email || '-'} {user.nis ? `| NIS: ${user.nis}` : ''}</p>
                </div>
              </label>
            );
          })}

          {!loading && currentUsers.length === 0 && (
            <div className="px-4 py-10 text-center text-sm text-gray-500">Tidak ada siswa yang cocok.</div>
          )}
        </div>

        {totalPages > 1 && (
          <div className="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-600">
            <span>
              Menampilkan {startIndex + 1}-{Math.min(endIndex, filteredUsers.length)} dari {filteredUsers.length}
            </span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
                disabled={currentPage === 1}
                className="px-2 py-1 border border-gray-300 rounded text-gray-700 disabled:opacity-50"
              >
                Prev
              </button>
              <span>{currentPage}/{totalPages}</span>
              <button
                type="button"
                onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
                disabled={currentPage === totalPages}
                className="px-2 py-1 border border-gray-300 rounded text-gray-700 disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );

  const renderStepConfirm = () => (
    <div className="space-y-5">
      <div>
        <h3 className="text-lg font-semibold text-gray-900">Konfirmasi Assignment</h3>
        <p className="text-sm text-gray-600 mt-1">Periksa data sebelum menerapkan skema ke siswa.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <h4 className="text-sm font-semibold text-gray-900 mb-3">Skema Terpilih</h4>
          <div className="space-y-2 text-sm text-gray-700">
            <p><span className="text-gray-500">Nama:</span> {selectedSchema?.schema_name || '-'}</p>
            <p><span className="text-gray-500">Jam:</span> {selectedSchema?.jam_masuk_default || '-'} - {selectedSchema?.jam_pulang_default || '-'}</p>
            <p><span className="text-gray-500">Toleransi:</span> {selectedSchema?.toleransi_default ?? 0} menit</p>
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <h4 className="text-sm font-semibold text-gray-900 mb-3">Siswa Terpilih</h4>
          <p className="text-2xl font-bold text-blue-700">{selectedUsers.length}</p>
          <p className="text-xs text-gray-600 mt-1">Akan menerima skema ini.</p>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Pengaturan Assignment</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label className="block">
            <span className="block text-xs font-medium text-gray-600 mb-1">Tanggal Mulai</span>
            <input
              type="date"
              value={assignmentOptions.start_date}
              onChange={(event) =>
                setAssignmentOptions((prev) => ({
                  ...prev,
                  start_date: event.target.value,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </label>
          <label className="block">
            <span className="block text-xs font-medium text-gray-600 mb-1">Tanggal Berakhir (Opsional)</span>
            <input
              type="date"
              value={assignmentOptions.end_date}
              onChange={(event) =>
                setAssignmentOptions((prev) => ({
                  ...prev,
                  end_date: event.target.value,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </label>
        </div>
        <label className="block mt-4">
          <span className="block text-xs font-medium text-gray-600 mb-1">Catatan</span>
          <textarea
            rows={3}
            value={assignmentOptions.notes}
            onChange={(event) =>
              setAssignmentOptions((prev) => ({
                ...prev,
                notes: event.target.value,
              }))
            }
            placeholder="Catatan assignment massal (opsional)"
            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </label>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Preview Siswa</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {selectedUsersPreview.map((user) => (
            <div key={user.id} className="px-3 py-2 rounded-md border border-gray-200 bg-gray-50 text-sm text-gray-800">
              {user.nama_lengkap || `Siswa ${user.id}`}
            </div>
          ))}
          {selectedUsers.length > selectedUsersPreview.length && (
            <div className="px-3 py-2 rounded-md border border-dashed border-gray-300 text-sm text-gray-600">
              +{selectedUsers.length - selectedUsersPreview.length} siswa lainnya
            </div>
          )}
        </div>
      </div>

      <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        Jika siswa sudah memiliki skema sebelumnya, assignment lama akan diganti.
      </div>
    </div>
  );

  const renderCurrentStep = () => {
    if (step === 1) return renderStepSchema();
    if (step === 2) return renderStepStudents();
    return renderStepConfirm();
  };

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
            <Users className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Assignment Massal Skema Siswa</h2>
            <p className="text-sm text-gray-600 mt-1">
              Terapkan skema absensi ke banyak siswa sekaligus dengan alur terkontrol.
            </p>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
          {steps.map((item, index) => {
            const number = index + 1;
            const active = step === number;
            const done = step > number;
            return (
              <div
                key={item.key}
                className={`rounded-lg border px-3 py-2 text-xs ${
                  active
                    ? 'border-blue-500 bg-blue-50 text-blue-800'
                    : done
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                      : 'border-gray-200 text-gray-500'
                }`}
              >
                <div className="flex items-center gap-2">
                  <span
                    className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold ${
                      active
                        ? 'bg-blue-600 text-white'
                        : done
                          ? 'bg-emerald-600 text-white'
                          : 'bg-gray-200 text-gray-700'
                    }`}
                  >
                    {done ? <Check className="h-3 w-3" /> : number}
                  </span>
                  <span className="font-medium">{item.label}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-5">{renderCurrentStep()}</div>

      <div className="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between gap-3">
        <button
          type="button"
          onClick={goBack}
          disabled={step === 1}
          className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali
        </button>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>

          {step < 3 ? (
            <button
              type="button"
              onClick={goNext}
              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
            >
              Lanjut
              <ArrowRight className="h-4 w-4" />
            </button>
          ) : (
            <button
              type="button"
              onClick={handleBulkAssign}
              disabled={loading}
              className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 disabled:opacity-60"
            >
              {loading ? <span className="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <GraduationCap className="h-4 w-4" />}
              {loading ? 'Menerapkan...' : 'Terapkan Skema'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default BulkAssignment;

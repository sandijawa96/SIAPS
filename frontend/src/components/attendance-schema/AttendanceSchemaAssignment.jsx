import React, { useEffect, useMemo, useState } from 'react';
import {
  ArrowLeft,
  Calendar,
  Check,
  Search,
  Settings,
  Users,
} from 'lucide-react';
import { toast } from 'react-hot-toast';
import attendanceSchemaService from '../../services/attendanceSchemaService';
import api from '../../services/api';
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

const AttendanceSchemaAssignment = ({ schema, onCancel }) => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [users, setUsers] = useState([]);
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [assignmentType, setAssignmentType] = useState('manual');
  const [loading, setLoading] = useState(false);
  const [loadingUsers, setLoadingUsers] = useState(true);

  const [assignmentData, setAssignmentData] = useState({
    start_date: '',
    end_date: '',
    notes: '',
  });

  useEffect(() => {
    if (!isServerClockSynced || !serverDate) {
      return;
    }

    setAssignmentData((current) => ({
      ...current,
      start_date: current.start_date || serverDate,
    }));
  }, [isServerClockSynced, serverDate]);

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      setLoadingUsers(true);

      let page = 1;
      let hasMore = true;
      const allUsers = [];

      while (hasMore && page <= 30) {
        const response = await api.get(`/users?per_page=100&page=${page}`, {
          timeout: 15000,
        });

        const payload = response?.data?.data;

        if (payload && typeof payload === 'object' && Array.isArray(payload.data)) {
          allUsers.push(...payload.data);
          hasMore = Number(payload.current_page || page) < Number(payload.last_page || page);
          page += 1;
          continue;
        }

        if (Array.isArray(payload)) {
          allUsers.push(...payload);
        } else if (Array.isArray(response?.data)) {
          allUsers.push(...response.data);
        }
        hasMore = false;
      }

      const studentUsers = allUsers
        .filter((user) => isStudentAttendanceUser(user))
        .map((user) => ({
          ...user,
          display_name: user.nama_lengkap || user.name || user.username || `Siswa ${user.id}`,
        }));

      setUsers(studentUsers);
    } catch (error) {
      console.error('Error fetching users:', error);
      toast.error('Gagal memuat daftar siswa');
      setUsers([]);
    } finally {
      setLoadingUsers(false);
    }
  };

  const filteredUsers = useMemo(() => {
    if (!searchTerm) return users;

    const keyword = searchTerm.toLowerCase();
    return users.filter((user) => {
      return (
        user.display_name?.toLowerCase().includes(keyword) ||
        user.email?.toLowerCase().includes(keyword) ||
        user.nis?.toLowerCase().includes(keyword) ||
        user.nisn?.toLowerCase().includes(keyword)
      );
    });
  }, [users, searchTerm]);

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

  const handleSubmit = async () => {
    if (assignmentType !== 'auto' && selectedUsers.length === 0) {
      toast.error('Pilih minimal satu siswa');
      return;
    }

    if (!assignmentData.start_date) {
      toast.error('Tanggal mulai harus diisi');
      return;
    }

    try {
      setLoading(true);

      if (assignmentType === 'manual') {
        if (selectedUsers.length === 1) {
          await attendanceSchemaService.assignToUser(schema.id, {
            user_id: selectedUsers[0],
            start_date: assignmentData.start_date,
            end_date: assignmentData.end_date || null,
            notes: assignmentData.notes,
            assignment_type: 'manual',
          });
        } else {
          await attendanceSchemaService.bulkAssign(schema.id, {
            user_ids: selectedUsers,
            start_date: assignmentData.start_date,
            end_date: assignmentData.end_date || null,
            notes: assignmentData.notes,
            assignment_type: 'manual',
          });
        }
        toast.success(`Skema berhasil diassign ke ${selectedUsers.length} siswa`);
      } else if (assignmentType === 'bulk') {
        await attendanceSchemaService.bulkAssign(schema.id, {
          user_ids: selectedUsers,
          start_date: assignmentData.start_date,
          end_date: assignmentData.end_date || null,
          notes: assignmentData.notes,
          assignment_type: 'bulk',
        });
        toast.success(`Bulk assign berhasil untuk ${selectedUsers.length} siswa`);
      } else {
        const userIds = selectedUsers.length > 0 ? selectedUsers : null;
        const response = await attendanceSchemaService.autoAssign(userIds, schema.id);
        toast.success(response?.message || 'Auto assignment berhasil');
      }

      onCancel?.();
    } catch (error) {
      console.error('Error assigning schema:', error);
      toast.error(error?.response?.data?.message || 'Gagal melakukan assignment skema');
    } finally {
      setLoading(false);
    }
  };

  if (!schema) {
    return (
      <div className="bg-white border border-gray-200 rounded-xl p-8 text-center">
        <p className="text-sm text-gray-500">Skema tidak ditemukan.</p>
        <button
          type="button"
          onClick={onCancel}
          className="mt-3 px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
        >
          Kembali
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5">
        <div className="flex items-start gap-3">
          <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
            <Users className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Assignment Skema ke Siswa</h2>
            <p className="text-sm text-gray-600 mt-1">
              Skema aktif: <span className="font-medium text-gray-800">{schema.schema_name}</span>
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[340px_1fr] gap-4">
        <div className="bg-white border border-gray-200 rounded-xl p-4 space-y-4 h-fit">
          <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
            <Settings className="h-4 w-4 text-blue-600" />
            Pengaturan Assignment
          </h3>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Tipe Assignment</label>
            <select
              value={assignmentType}
              onChange={(event) => setAssignmentType(event.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="manual">Manual</option>
              <option value="bulk">Bulk</option>
              <option value="auto">Auto</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Tanggal Mulai</label>
            <input
              type="date"
              value={assignmentData.start_date}
              onChange={(event) =>
                setAssignmentData((prev) => ({
                  ...prev,
                  start_date: event.target.value,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Tanggal Berakhir (Opsional)</label>
            <input
              type="date"
              value={assignmentData.end_date}
              onChange={(event) =>
                setAssignmentData((prev) => ({
                  ...prev,
                  end_date: event.target.value,
                }))
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
            <textarea
              rows={3}
              value={assignmentData.notes}
              onChange={(event) =>
                setAssignmentData((prev) => ({
                  ...prev,
                  notes: event.target.value,
                }))
              }
              placeholder="Catatan assignment (opsional)"
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
            {assignmentType === 'auto'
              ? 'Auto assignment memakai rule target pada skema ini dan akan melewati siswa yang masih punya assignment admin aktif.'
              : `Siswa dipilih: ${selectedUsers.length}`}
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <div className="px-4 py-3 border-b bg-gray-50 flex items-center justify-between gap-3">
            <h3 className="text-sm font-semibold text-gray-900">Daftar Siswa</h3>
            <span className="text-xs text-gray-600">{selectedUsers.length} dipilih</span>
          </div>

          <div className="p-4 border-b bg-white">
            <div className="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-2 items-center">
              <div className="relative">
                <Search className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(event) => setSearchTerm(event.target.value)}
                  placeholder="Cari siswa..."
                  className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <button
                type="button"
                onClick={selectAllFiltered}
                disabled={assignmentType === 'auto'}
                className="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >
                Pilih Semua
              </button>
              <button
                type="button"
                onClick={clearSelection}
                disabled={assignmentType === 'auto'}
                className="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >
                Reset
              </button>
            </div>
          </div>

          <div className="max-h-[460px] overflow-y-auto divide-y">
            {loadingUsers ? (
              <div className="px-4 py-10 text-center text-sm text-gray-500">Memuat data siswa...</div>
            ) : filteredUsers.length === 0 ? (
              <div className="px-4 py-10 text-center text-sm text-gray-500">Tidak ada siswa ditemukan.</div>
            ) : (
              filteredUsers.map((user) => {
                const checked = selectedUsers.includes(user.id);
                return (
                  <label
                    key={user.id}
                    className={`flex items-start gap-3 px-4 py-3 cursor-pointer ${checked ? 'bg-blue-50' : 'hover:bg-gray-50'} ${assignmentType === 'auto' ? 'opacity-60 cursor-not-allowed' : ''}`}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={assignmentType === 'auto'}
                      onChange={(event) => toggleUser(user.id, event.target.checked)}
                      className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-1"
                    />
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-gray-900">{user.display_name}</p>
                      <p className="text-xs text-gray-500 mt-0.5">{user.email || '-'} {user.nis ? `| NIS: ${user.nis}` : ''}</p>
                    </div>
                  </label>
                );
              })
            )}
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between gap-3">
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali
        </button>

        <button
          type="button"
          onClick={handleSubmit}
          disabled={loading}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-60"
        >
          {loading ? <span className="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <Check className="h-4 w-4" />}
          {loading ? 'Memproses...' : assignmentType === 'auto' ? 'Jalankan Auto Assignment' : 'Simpan Assignment'}
        </button>
      </div>
    </div>
  );
};

export default AttendanceSchemaAssignment;

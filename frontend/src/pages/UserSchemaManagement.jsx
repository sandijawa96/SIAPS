import React, { useEffect, useMemo, useState } from 'react';
import { Search, Users, Eye, RefreshCw, Filter, Download, ChevronLeft, ChevronRight } from 'lucide-react';
import UserSchemaInfo from '../components/user-schema/UserSchemaInfo';
import api from '../services/api';
import attendanceSchemaService from '../services/attendanceSchemaService';
import { getServerDateString } from '../services/serverClock';
import useServerClock from '../hooks/useServerClock';
import { toast } from 'react-hot-toast';

const USERS_PER_PAGE = 25;

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

const chunkArray = (arr, size) => {
  const chunks = [];
  for (let i = 0; i < arr.length; i += size) {
    chunks.push(arr.slice(i, i + size));
  }
  return chunks;
};

const UserSchemaManagement = () => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [users, setUsers] = useState([]);
  const [selectedUser, setSelectedUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [schemaFilter, setSchemaFilter] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [availableSchemas, setAvailableSchemas] = useState([]);
  const [savingAssignment, setSavingAssignment] = useState(false);
  const [assignmentForm, setAssignmentForm] = useState({
    schema_id: '',
    start_date: '',
    end_date: '',
    notes: '',
  });

  const currentServerDate = isServerClockSynced && serverDate ? serverDate : '';

  useEffect(() => {
    if (!currentServerDate) {
      return;
    }

    setAssignmentForm((current) => ({
      ...current,
      start_date: current.start_date || currentServerDate,
    }));
  }, [currentServerDate]);

  useEffect(() => {
    loadUsersWithSchemas();
    loadSchemaOptions();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, schemaFilter]);

  const loadSchemaOptions = async () => {
    try {
      const response = await api.get('/attendance-schemas');
      if (response.data?.success && Array.isArray(response.data.data)) {
        setAvailableSchemas(response.data.data);
      } else if (Array.isArray(response.data?.data)) {
        setAvailableSchemas(response.data.data);
      } else {
        setAvailableSchemas([]);
      }
    } catch (error) {
      console.error('Error loading schema options:', error);
      setAvailableSchemas([]);
    }
  };

  const loadUsersWithSchemas = async () => {
    try {
      setLoading(true);

      const allStudentUsers = [];
      let page = 1;
      let hasMore = true;

      while (hasMore && page <= 80) {
        const response = await api.get(`/users?page=${page}&per_page=100`, {
          timeout: 15000,
        });

        if (!(response.data && response.data.success)) {
          hasMore = false;
          break;
        }

        const payload = response.data.data;
        let rows = [];
        let currentPageFromApi = page;
        let lastPageFromApi = page;

        if (payload && typeof payload === 'object' && Array.isArray(payload.data)) {
          rows = payload.data;
          currentPageFromApi = Number(payload.current_page || page);
          lastPageFromApi = Number(payload.last_page || page);
        } else if (Array.isArray(payload)) {
          rows = payload;
        } else if (Array.isArray(response.data.data?.users)) {
          rows = response.data.data.users;
        }

        allStudentUsers.push(...rows.filter((user) => isStudentAttendanceUser(user)));

        hasMore = currentPageFromApi < lastPageFromApi;
        page = currentPageFromApi + 1;

        if (hasMore) {
          await new Promise((resolve) => setTimeout(resolve, 70));
        }
      }

      const schemaMap = {};
      const idChunks = chunkArray(allStudentUsers.map((user) => user.id), 200);

      for (const ids of idChunks) {
        if (ids.length === 0) continue;
        try {
          const response = await api.post(
            '/attendance-schemas/effective/bulk',
            { user_ids: ids },
            { timeout: 15000 }
          );

          if (response.data?.success && response.data?.data) {
            Object.assign(schemaMap, response.data.data);
          }
        } catch (error) {
          console.warn('Bulk effective schema batch failed:', error?.message || error);
        }
      }

      const mappedUsers = allStudentUsers.map((user) => ({
        ...user,
        effectiveSchema: schemaMap[user.id]?.schema || null,
        assignmentType: schemaMap[user.id]?.assignment_type || 'none',
      }));

      setUsers(mappedUsers);

      if (selectedUser) {
        const refreshedSelectedUser = mappedUsers.find((item) => item.id === selectedUser.id) || null;
        setSelectedUser(refreshedSelectedUser);
      }
    } catch (error) {
      console.error('Error loading student schema monitoring:', error);
      if (error.code === 'ECONNABORTED') {
        toast.error('Timeout saat memuat data monitoring siswa');
      } else {
        toast.error('Gagal memuat data monitoring siswa');
      }
      setUsers([]);
      setSelectedUser(null);
    } finally {
      setLoading(false);
    }
  };

  const filteredUsers = useMemo(() => {
    let rows = [...users];

    if (searchTerm) {
      const keyword = searchTerm.toLowerCase();
      rows = rows.filter((user) => {
        return (
          user.nama_lengkap?.toLowerCase().includes(keyword) ||
          user.email?.toLowerCase().includes(keyword) ||
          user.username?.toLowerCase().includes(keyword) ||
          user.nis?.toLowerCase().includes(keyword) ||
          user.nisn?.toLowerCase().includes(keyword)
        );
      });
    }

    if (schemaFilter) {
      rows = rows.filter((user) => Number(user.effectiveSchema?.id) === Number(schemaFilter));
    }

    return rows;
  }, [users, searchTerm, schemaFilter]);

  const stats = useMemo(() => {
    const total = users.length;
    const usersWithSchema = users.filter((user) => Boolean(user.effectiveSchema)).length;
    const manualAssignments = users.filter((user) => ['manual', 'bulk'].includes(user.assignmentType)).length;
    const usersWithoutSchema = users.filter((user) => !user.effectiveSchema).length;

    return {
      total,
      usersWithSchema,
      manualAssignments,
      usersWithoutSchema,
    };
  }, [users]);

  const totalUsers = filteredUsers.length;
  const totalPages = Math.max(1, Math.ceil(totalUsers / USERS_PER_PAGE));

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(1);
    }
  }, [currentPage, totalPages]);

  const currentUsers = useMemo(() => {
    const start = (currentPage - 1) * USERS_PER_PAGE;
    return filteredUsers.slice(start, start + USERS_PER_PAGE);
  }, [filteredUsers, currentPage]);

  const handleRefreshUser = async (userId) => {
    try {
      const response = await api.get(`/attendance-schemas/user/${userId}/effective`);
      const payload = response.data;

      let refreshedUser = null;

      setUsers((prevUsers) =>
        prevUsers.map((user) => {
          if (user.id !== userId) return user;

          if (payload?.success) {
            refreshedUser = {
              ...user,
              effectiveSchema: payload.data || null,
              assignmentType: payload.assignment_type || 'none',
            };

            return refreshedUser;
          }

          refreshedUser = {
            ...user,
            effectiveSchema: null,
            assignmentType: 'none',
          };

          return refreshedUser;
        })
      );

      if (refreshedUser && selectedUser?.id === userId) {
        setSelectedUser(refreshedUser);
      }

      toast.success('Data skema siswa diperbarui');
    } catch (error) {
      console.error('Error refreshing user schema:', error);
      toast.error('Gagal memperbarui data skema siswa');
    }
  };

  useEffect(() => {
    if (!selectedUser) {
      return;
    }

    setAssignmentForm({
      schema_id: selectedUser.effectiveSchema?.id ? String(selectedUser.effectiveSchema.id) : '',
      start_date: currentServerDate,
      end_date: '',
      notes: '',
    });
  }, [currentServerDate, selectedUser?.id, selectedUser?.effectiveSchema?.id]);

  const handleAssignSchema = async () => {
    if (!selectedUser) {
      toast.error('Pilih siswa terlebih dahulu');
      return;
    }

    if (!assignmentForm.schema_id) {
      toast.error('Pilih skema tujuan');
      return;
    }

    try {
      setSavingAssignment(true);
      await attendanceSchemaService.assignToUser(assignmentForm.schema_id, {
        user_id: selectedUser.id,
        start_date: assignmentForm.start_date,
        end_date: assignmentForm.end_date || null,
        notes: assignmentForm.notes,
        assignment_type: 'manual',
      });

      await loadUsersWithSchemas();
      await handleRefreshUser(selectedUser.id);
      toast.success('Assignment siswa berhasil disimpan');
    } catch (error) {
      console.error('Error assigning schema from monitoring:', error);
      toast.error(error?.response?.data?.message || 'Gagal menyimpan assignment siswa');
    } finally {
      setSavingAssignment(false);
    }
  };

  const exportUserSchemas = () => {
    if (filteredUsers.length === 0) {
      toast.error('Tidak ada data untuk diekspor');
      return;
    }

    const csvData = filteredUsers.map((user) => ({
      Nama: user.nama_lengkap || '',
      Email: user.email || '',
      NIS: user.nis || '-',
      NISN: user.nisn || '-',
      Role: user.roles?.map((item) => item.display_name || item.name).join(', ') || '-',
      Skema: user.effectiveSchema?.schema_name || 'Tidak ada',
      'Tipe Skema': user.effectiveSchema?.schema_type || '-',
      'Jam Masuk': user.effectiveSchema?.jam_masuk_default || '-',
      'Jam Pulang': user.effectiveSchema?.jam_pulang_default || '-',
      Toleransi: user.effectiveSchema?.toleransi_default || '-',
      'Wajib GPS': user.effectiveSchema?.wajib_gps ? 'Ya' : 'Tidak',
      'Wajib Selfie': user.effectiveSchema?.wajib_foto ? 'Ya' : 'Tidak',
      'Face Recognition': user.effectiveSchema?.face_verification_enabled ? 'Dipakai' : 'Tidak dipakai',
    }));

    const csvContent = [
      Object.keys(csvData[0]).join(','),
      ...csvData.map((row) => Object.values(row).join(',')),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `monitoring-skema-siswa-${getServerDateString() || 'server-date'}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  };

  const getSchemaStatusColor = (user) => {
    if (!user.effectiveSchema) return 'bg-red-100 text-red-800';
    if (user.assignmentType === 'manual') return 'bg-blue-100 text-blue-800';
    if (user.assignmentType === 'bulk') return 'bg-purple-100 text-purple-800';
    if (user.assignmentType === 'auto') return 'bg-green-100 text-green-800';
    if (user.assignmentType === 'default') return 'bg-gray-100 text-gray-800';
    return 'bg-gray-100 text-gray-800';
  };

  const getSchemaStatusLabel = (user) => {
    if (!user.effectiveSchema) return 'Belum ada skema';
    return user.effectiveSchema.schema_name;
  };

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse">
          <div className="h-8 bg-gray-200 rounded w-1/3 mb-6" />
          <div className="space-y-4">
            {[...Array(5)].map((_, index) => (
              <div key={index} className="h-16 bg-gray-200 rounded" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5 flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold text-gray-900">Monitoring Skema Absensi Siswa</h1>
          <p className="text-sm text-gray-600 mt-1">
            Pantau skema efektif siswa, cek coverage, dan refresh assignment jika diperlukan.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
          >
            <Filter className="h-4 w-4" />
            Filter
          </button>
          <button
            onClick={exportUserSchemas}
            className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
          >
            <Download className="h-4 w-4" />
            Export CSV
          </button>
          <button
            onClick={loadUsersWithSchemas}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </button>
        </div>
      </div>

      {showFilters && (
        <div className="bg-white border border-gray-200 p-4 rounded-xl space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Cari Siswa</label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(event) => setSearchTerm(event.target.value)}
                  placeholder="Nama, email, NIS, NISN, atau username..."
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Filter Skema</label>
              <select
                value={schemaFilter}
                onChange={(event) => setSchemaFilter(event.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Semua Skema</option>
                {availableSchemas.map((schema) => (
                  <option key={schema.id} value={schema.id}>
                    {schema.schema_name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white border border-gray-200 p-4 rounded-xl">
          <div className="flex items-center gap-3">
            <Users className="h-8 w-8 text-blue-600" />
            <div>
              <p className="text-sm text-gray-600">Total Siswa</p>
              <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
            </div>
          </div>
        </div>
        <div className="bg-white border border-gray-200 p-4 rounded-xl">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
              <div className="h-4 w-4 bg-green-600 rounded-full" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Punya Skema</p>
              <p className="text-2xl font-bold text-gray-900">{stats.usersWithSchema}</p>
            </div>
          </div>
        </div>
        <div className="bg-white border border-gray-200 p-4 rounded-xl">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
              <div className="h-4 w-4 bg-blue-600 rounded-full" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Assignment Admin</p>
              <p className="text-2xl font-bold text-gray-900">{stats.manualAssignments}</p>
            </div>
          </div>
        </div>
        <div className="bg-white border border-gray-200 p-4 rounded-xl">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 bg-red-100 rounded-full flex items-center justify-center">
              <div className="h-4 w-4 bg-red-600 rounded-full" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Tanpa Skema</p>
              <p className="text-2xl font-bold text-gray-900">{stats.usersWithoutSchema}</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white border border-gray-200 rounded-xl">
          <div className="p-4 border-b flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-900">Daftar Siswa</h2>
            <div className="text-sm text-gray-500">
              Halaman {currentPage} dari {totalPages} ({totalUsers} hasil)
            </div>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {currentUsers.length === 0 ? (
              <div className="p-8 text-center text-gray-500">
                <Users className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                <p>Tidak ada siswa yang ditemukan</p>
              </div>
            ) : (
              <div className="divide-y">
                {currentUsers.map((user) => (
                  <div
                    key={user.id}
                    className={`p-4 hover:bg-gray-50 cursor-pointer ${selectedUser?.id === user.id ? 'bg-blue-50 border-l-4 border-blue-500' : ''}`}
                    onClick={() => setSelectedUser(user)}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex-1 min-w-0">
                        <h3 className="font-medium text-gray-900 truncate">{user.nama_lengkap}</h3>
                        <p className="text-sm text-gray-500 truncate">{user.email || '-'} {user.nis ? `| NIS: ${user.nis}` : ''}</p>
                        <div className="flex items-center gap-2 mt-1">
                          <span className="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                            {user.roles?.map((item) => item.display_name || item.name).join(', ') || 'Siswa'}
                          </span>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 pl-2">
                        <span className={`text-xs px-2 py-1 rounded-full ${getSchemaStatusColor(user)}`}>
                          {getSchemaStatusLabel(user)}
                        </span>
                        <button
                          onClick={(event) => {
                            event.stopPropagation();
                            handleRefreshUser(user.id);
                          }}
                          className="p-1 text-gray-400 hover:text-gray-600"
                        >
                          <RefreshCw className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {totalPages > 1 && (
            <div className="p-4 border-t bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="text-sm text-gray-700">
                  Menampilkan {(currentPage - 1) * USERS_PER_PAGE + 1} - {Math.min(currentPage * USERS_PER_PAGE, totalUsers)} dari {totalUsers}
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
                    disabled={currentPage === 1}
                    className="px-3 py-1 text-sm border rounded-md hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                  >
                    <ChevronLeft className="h-4 w-4" />
                    Sebelumnya
                  </button>

                  <button
                    onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={currentPage === totalPages}
                    className="px-3 py-1 text-sm border rounded-md hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                  >
                    Selanjutnya
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        <div>
          {selectedUser ? (
            <div className="space-y-4">
              <UserSchemaInfo userId={selectedUser.id} userName={selectedUser.nama_lengkap} />
              <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                <div>
                  <h2 className="text-base font-semibold text-gray-900">Ubah Assignment Siswa</h2>
                  <p className="text-sm text-gray-600 mt-1">
                    Pindahkan siswa ke skema lain langsung dari layar monitoring. Assignment aktif yang overlap akan disesuaikan otomatis.
                  </p>
                </div>

                <label className="block">
                  <span className="block text-sm font-medium text-gray-700 mb-1">Skema Tujuan</span>
                  <select
                    value={assignmentForm.schema_id}
                    onChange={(event) =>
                      setAssignmentForm((prev) => ({
                        ...prev,
                        schema_id: event.target.value,
                      }))
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Pilih skema</option>
                    {availableSchemas
                      .filter((schema) => schema.is_active)
                      .map((schema) => (
                        <option key={schema.id} value={schema.id}>
                          {schema.schema_name}
                        </option>
                      ))}
                  </select>
                </label>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label className="block">
                    <span className="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</span>
                    <input
                      type="date"
                      value={assignmentForm.start_date}
                      onChange={(event) =>
                        setAssignmentForm((prev) => ({
                          ...prev,
                          start_date: event.target.value,
                        }))
                      }
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </label>
                  <label className="block">
                    <span className="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir (Opsional)</span>
                    <input
                      type="date"
                      value={assignmentForm.end_date}
                      onChange={(event) =>
                        setAssignmentForm((prev) => ({
                          ...prev,
                          end_date: event.target.value,
                        }))
                      }
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </label>
                </div>

                <label className="block">
                  <span className="block text-sm font-medium text-gray-700 mb-1">Catatan</span>
                  <textarea
                    rows={3}
                    value={assignmentForm.notes}
                    onChange={(event) =>
                      setAssignmentForm((prev) => ({
                        ...prev,
                        notes: event.target.value,
                      }))
                    }
                    placeholder="Alasan perubahan assignment (opsional)"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </label>

                <div className="flex items-center justify-end gap-2">
                  <button
                    onClick={() => handleRefreshUser(selectedUser.id)}
                    className="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                  >
                    Refresh Siswa
                  </button>
                  <button
                    onClick={handleAssignSchema}
                    disabled={savingAssignment}
                    className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-60"
                  >
                    {savingAssignment ? 'Menyimpan...' : 'Simpan Assignment'}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className="bg-white border border-gray-200 p-8 rounded-xl text-center text-gray-500">
              <Eye className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <p>Pilih siswa untuk melihat detail skema absensi</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default UserSchemaManagement;

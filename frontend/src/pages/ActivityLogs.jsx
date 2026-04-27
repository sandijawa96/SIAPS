import React, { useEffect, useMemo, useState } from 'react';
import { Filter, Download, User, RefreshCw } from 'lucide-react';
import { activityLogsAPI } from '../services/api';
import { formatServerDateTime, getServerDateString } from '../services/serverClock';

const ActivityLogs = () => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({
    startDate: '',
    endDate: '',
    user: '',
    action: '',
    module: '',
  });
  const [showFilters, setShowFilters] = useState(false);
  const [error, setError] = useState('');
  const [actionOptions, setActionOptions] = useState([]);
  const [moduleOptions, setModuleOptions] = useState([]);

  useEffect(() => {
    fetchLogs();
    fetchFilterOptions();
  }, []);

  const fetchFilterOptions = async () => {
    try {
      const response = await activityLogsAPI.getFilters();
      const payload = response?.data?.data || {};
      setActionOptions(Array.isArray(payload.actions) ? payload.actions : []);
      setModuleOptions(Array.isArray(payload.modules) ? payload.modules : []);
    } catch (err) {
      setActionOptions([]);
      setModuleOptions([]);
    }
  };

  const fetchLogs = async () => {
    setLoading(true);
    setError('');
    try {
      const params = {
        per_page: 100,
      };
      if (filters.startDate) params.date_from = filters.startDate;
      if (filters.endDate) params.date_to = filters.endDate;
      if (filters.action) params.action = filters.action;
      if (filters.module) params.module = filters.module;

      const response = await activityLogsAPI.getAll(params);
      const payload = response?.data?.data;
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      setLogs(rows);
    } catch (err) {
      setLogs([]);
      setError(err?.response?.data?.message || 'Gagal memuat log aktivitas');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters((prev) => ({ ...prev, [name]: value }));
  };

  const handleApplyFilter = () => {
    fetchLogs();
  };

  const handleExport = async () => {
    try {
      const params = {
        format: 'csv',
      };
      if (filters.startDate) params.date_from = filters.startDate;
      if (filters.endDate) params.date_to = filters.endDate;
      if (filters.action) params.action = filters.action;
      if (filters.module) params.module = filters.module;

      const response = await activityLogsAPI.export(params);
      const blob = new Blob([response.data], {
        type: response.headers?.['content-type'] || 'text/csv',
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `activity_logs_${getServerDateString() || 'server-date'}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal export log aktivitas');
    }
  };

  const getActionColor = (action) => {
    const normalized = String(action || '').toLowerCase();
    if (normalized.includes('login')) return 'bg-green-100 text-green-800';
    if (normalized.includes('create')) return 'bg-blue-100 text-blue-800';
    if (normalized.includes('update') || normalized.includes('edit')) return 'bg-yellow-100 text-yellow-800';
    if (normalized.includes('delete') || normalized.includes('remove')) return 'bg-red-100 text-red-800';
    if (normalized.includes('backup')) return 'bg-purple-100 text-purple-800';
    return 'bg-gray-100 text-gray-800';
  };

  const formatDate = (dateString) => formatServerDateTime(dateString, 'id-ID') || '-';

  const filteredLogs = useMemo(() => {
    if (!filters.user) {
      return logs;
    }
    const q = filters.user.toLowerCase();
    return logs.filter((item) => {
      const name = (item?.user?.nama_lengkap || item?.user?.name || item?.user?.email || '').toLowerCase();
      return name.includes(q);
    });
  }, [logs, filters.user]);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Log Aktivitas</h1>
        <p className="text-sm text-gray-600 mt-1">Monitor semua aktivitas dalam sistem</p>
      </div>

      {error && (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="mb-6">
        <div className="flex justify-between items-center mb-4">
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
          >
            <Filter className="w-4 h-4 mr-2" />
            Filter
          </button>

          <div className="flex space-x-2">
            <button
              onClick={fetchLogs}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <RefreshCw className="w-4 h-4 mr-2" />
              Refresh
            </button>
            <button
              onClick={handleExport}
              className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
            >
              <Download className="w-4 h-4 mr-2" />
              Export
            </button>
          </div>
        </div>

        {showFilters && (
          <div className="bg-white p-4 rounded-lg shadow mb-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input
                  type="date"
                  name="startDate"
                  value={filters.startDate}
                  onChange={handleFilterChange}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input
                  type="date"
                  name="endDate"
                  value={filters.endDate}
                  onChange={handleFilterChange}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Pengguna</label>
                <input
                  type="text"
                  name="user"
                  value={filters.user}
                  onChange={handleFilterChange}
                  placeholder="Cari pengguna..."
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Aksi</label>
                <select
                  name="action"
                  value={filters.action}
                  onChange={handleFilterChange}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                >
                  <option value="">Semua Aksi</option>
                  {actionOptions.map((action) => (
                    <option key={action} value={action}>
                      {action}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                <select
                  name="module"
                  value={filters.module}
                  onChange={handleFilterChange}
                  className="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 sm:text-sm"
                >
                  <option value="">Semua Modul</option>
                  {moduleOptions.map((module) => (
                    <option key={module} value={module}>
                      {module}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex items-end">
                <button
                  onClick={handleApplyFilter}
                  className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                >
                  Terapkan Filter
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modul</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan="6" className="px-6 py-4 text-center">
                    <RefreshCw className="w-5 h-5 animate-spin mx-auto text-gray-400" />
                  </td>
                </tr>
              ) : filteredLogs.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-6 py-4 text-center text-gray-500">Tidak ada log aktivitas</td>
                </tr>
              ) : (
                filteredLogs.map((log) => (
                  <tr key={log.id}>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{formatDate(log.created_at)}</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="flex-shrink-0 h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                          <User className="h-4 w-4 text-gray-400" />
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-gray-900">
                            {log?.user?.nama_lengkap || log?.user?.name || log?.user?.email || 'Sistem'}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getActionColor(log.action)}`}>
                        {log.action}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500">{log.notes || '-'}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{log.ip_address || '-'}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{log.module || '-'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default ActivityLogs;

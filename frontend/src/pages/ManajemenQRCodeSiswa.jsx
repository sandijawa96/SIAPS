import React, { useEffect, useMemo, useState } from 'react';
import { QrCode, Download, RefreshCw, Search, Filter } from 'lucide-react';
import { qrCodeAPI, siswaAPI } from '../services/api';

const ManajemenQRCodeSiswa = () => {
  const [loading, setLoading] = useState(false);
  const [students, setStudents] = useState([]);
  const [qrByUserId, setQrByUserId] = useState({});
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedClass, setSelectedClass] = useState('all');
  const [generatingQR, setGeneratingQR] = useState(false);
  const [selectedStudents, setSelectedStudents] = useState([]);
  const [error, setError] = useState('');
  const [infoMessage, setInfoMessage] = useState('');

  useEffect(() => {
    loadStudents();
  }, []);

  const loadStudents = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await siswaAPI.getAll({ per_page: 500 });
      const payload = response?.data?.data;
      const rows = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);

      const mapped = rows.map((item) => ({
        id: item.id,
        nis: item.nis || '-',
        nama: item.nama_lengkap || item.name || item.email || '-',
        kelas: item.kelas_nama || item.kelasNama || item.kelas?.nama_kelas || '-',
      }));

      setStudents(mapped);
    } catch (err) {
      setStudents([]);
      setError(err?.response?.data?.message || 'Gagal memuat data siswa');
    } finally {
      setLoading(false);
    }
  };

  const generateForUsers = async (userIds) => {
    if (!Array.isArray(userIds) || userIds.length === 0) {
      return;
    }

    setGeneratingQR(true);
    setError('');
    setInfoMessage('');
    try {
      const response = await qrCodeAPI.bulk({
        type: 'checkin',
        user_ids: userIds,
        expired_minutes: 30,
      });

      const qrRows = response?.data?.data?.qr_codes || [];
      const nextMap = { ...qrByUserId };
      qrRows.forEach((row) => {
        if (row?.user_id) {
          nextMap[row.user_id] = row;
        }
      });
      setQrByUserId(nextMap);
      setInfoMessage(`Berhasil generate ${qrRows.length} QR Code`);
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal generate QR Code');
    } finally {
      setGeneratingQR(false);
    }
  };

  const generateQRCode = async (studentId) => {
    await generateForUsers([studentId]);
  };

  const generateBulkQRCodes = async () => {
    if (selectedStudents.length === 0) {
      setError('Pilih siswa terlebih dahulu');
      return;
    }
    await generateForUsers(selectedStudents);
    setSelectedStudents([]);
  };

  const handleDownload = (studentId) => {
    const qrRow = qrByUserId[studentId];
    if (!qrRow?.qr_code) {
      return;
    }

    const link = document.createElement('a');
    link.href = qrRow.qr_code;
    link.download = `qr_siswa_${studentId}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  };

  const handleSelectAll = (e) => {
    if (e.target.checked) {
      setSelectedStudents(filteredStudents.map((s) => s.id));
    } else {
      setSelectedStudents([]);
    }
  };

  const handleSelectStudent = (studentId) => {
    setSelectedStudents((prev) =>
      prev.includes(studentId) ? prev.filter((id) => id !== studentId) : [...prev, studentId]
    );
  };

  const filteredStudents = useMemo(
    () =>
      students.filter((student) => {
        const normalizedSearch = searchQuery.toLowerCase();
        const matchesSearch =
          student.nama.toLowerCase().includes(normalizedSearch) || String(student.nis).includes(searchQuery);
        const matchesClass = selectedClass === 'all' || student.kelas === selectedClass;
        return matchesSearch && matchesClass;
      }),
    [students, searchQuery, selectedClass]
  );

  const classes = useMemo(() => [...new Set(students.map((s) => s.kelas).filter(Boolean))], [students]);
  const allSelected = filteredStudents.length > 0 && selectedStudents.length === filteredStudents.length;

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Manajemen QR Code Siswa</h1>
          <p className="text-sm text-gray-600 mt-1">Generate dan kelola QR Code untuk absensi siswa</p>
        </div>
        <div className="flex space-x-3">
          <button
            onClick={generateBulkQRCodes}
            disabled={selectedStudents.length === 0 || generatingQR}
            className="btn-primary flex items-center space-x-2"
          >
            {generatingQR ? (
              <>
                <RefreshCw className="w-4 h-4 animate-spin" />
                <span>Generating...</span>
              </>
            ) : (
              <>
                <QrCode className="w-4 h-4" />
                <span>Generate QR Terpilih</span>
              </>
            )}
          </button>
          <button onClick={loadStudents} className="btn-secondary flex items-center space-x-2">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            <span>Refresh Data</span>
          </button>
        </div>
      </div>

      {error && (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      )}
      {infoMessage && (
        <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {infoMessage}
        </div>
      )}

      <div className="bg-white rounded-lg shadow p-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="relative">
            <Search className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              placeholder="Cari nama atau NIS..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="form-input pl-10 w-full"
            />
          </div>

          <div className="relative">
            <Filter className="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <select
              value={selectedClass}
              onChange={(e) => setSelectedClass(e.target.value)}
              className="form-select pl-10 w-full"
            >
              <option value="all">Semua Kelas</option>
              {classes.map((kelas) => (
                <option key={kelas} value={kelas}>
                  {kelas}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input type="checkbox" checked={allSelected} onChange={handleSelectAll} className="form-checkbox" />
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIS</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan="6" className="px-6 py-4 text-center text-sm text-gray-500">
                    <RefreshCw className="w-5 h-5 animate-spin mx-auto" />
                    <span>Memuat data siswa...</span>
                  </td>
                </tr>
              ) : filteredStudents.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-6 py-4 text-center text-sm text-gray-500">
                    Tidak ada data siswa
                  </td>
                </tr>
              ) : (
                filteredStudents.map((student) => {
                  const qrRow = qrByUserId[student.id];
                  return (
                    <tr key={student.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <input
                          type="checkbox"
                          checked={selectedStudents.includes(student.id)}
                          onChange={() => handleSelectStudent(student.id)}
                          className="form-checkbox"
                        />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{student.nis}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{student.nama}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{student.kelas}</td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {qrRow?.qr_code ? (
                          <img src={qrRow.qr_code} alt="QR Code" className="w-10 h-10" />
                        ) : (
                          <span className="text-sm text-gray-500">Belum di-generate</span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => generateQRCode(student.id)}
                            disabled={generatingQR}
                            className="text-blue-600 hover:text-blue-900 disabled:text-gray-400"
                          >
                            Generate QR
                          </button>
                          {qrRow?.qr_code && (
                            <button onClick={() => handleDownload(student.id)} className="text-green-600 hover:text-green-900">
                              <Download className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 className="text-blue-800 font-medium mb-2">Catatan:</h3>
        <ul className="text-blue-700 text-sm space-y-1">
          <li>1. Jika backend QR Code masih dinonaktifkan, proses generate akan ditolak dengan pesan 403.</li>
          <li>2. QR yang berhasil dibuat berlaku sampai waktu kedaluwarsa endpoint (`expired_minutes`).</li>
          <li>3. Unduhan QR dilakukan per siswa untuk distribusi terkontrol.</li>
        </ul>
      </div>
    </div>
  );
};

export default ManajemenQRCodeSiswa;

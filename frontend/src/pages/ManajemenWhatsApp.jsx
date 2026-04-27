import React, { useState, useEffect, useMemo } from 'react';
import { Plus, MessageSquare, Edit, Trash2, Phone, RefreshCw, Send } from 'lucide-react';

const ManajemenWhatsApp = () => {
  const [loading, setLoading] = useState(true);
  const [deviceList, setDeviceList] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [selectedDevice, setSelectedDevice] = useState(null);
  const [testNumber, setTestNumber] = useState('');
  const [sendingTest, setSendingTest] = useState(false);

  // Mock data untuk development
  const mockDeviceList = useMemo(() => [
    {
      id: 1,
      nama: 'WhatsApp Gateway 1',
      nomorTelepon: '6281234567890',
      status: 'Terhubung',
      lastSync: '2024-01-20 10:30:00',
      totalPesan: 1250,
      pesanBerhasil: 1200,
      pesanGagal: 50,
      apiKey: 'wh_123456789',
      webhook: 'https://api.sekolah.id/webhook/wa1'
    },
    {
      id: 2,
      nama: 'WhatsApp Gateway 2',
      nomorTelepon: '6281234567891',
      status: 'Terputus',
      lastSync: '2024-01-20 09:15:00',
      totalPesan: 800,
      pesanBerhasil: 750,
      pesanGagal: 50,
      apiKey: 'wh_987654321',
      webhook: 'https://api.sekolah.id/webhook/wa2'
    },
    {
      id: 3,
      nama: 'WhatsApp Gateway 3',
      nomorTelepon: '6281234567892',
      status: 'Terhubung',
      lastSync: '2024-01-20 10:45:00',
      totalPesan: 500,
      pesanBerhasil: 480,
      pesanGagal: 20,
      apiKey: 'wh_456789123',
      webhook: 'https://api.sekolah.id/webhook/wa3'
    }
  ], []);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        // Simulasi API call
        setTimeout(() => {
          setDeviceList(mockDeviceList);
          setLoading(false);
        }, 1000);
      } catch (error) {
        console.error('Error fetching data:', error);
        setLoading(false);
      }
    };

    fetchData();
  }, [mockDeviceList]);

  const handleAddDevice = () => {
    setSelectedDevice(null);
    setShowModal(true);
  };

  const handleEditDevice = (device) => {
    setSelectedDevice(device);
    setShowModal(true);
  };

  const handleDeleteDevice = (id) => {
    if (window.confirm('Apakah Anda yakin ingin menghapus device ini?')) {
      setDeviceList(deviceList.filter(device => device.id !== id));
    }
  };

  const handleTestMessage = async (device) => {
    if (!testNumber) {
      alert('Masukkan nomor telepon untuk test');
      return;
    }

    try {
      setSendingTest(true);
      // Simulasi pengiriman pesan
      await new Promise(resolve => setTimeout(resolve, 2000));
      alert('Pesan test berhasil dikirim');
    } catch (error) {
      alert('Gagal mengirim pesan test');
    } finally {
      setSendingTest(false);
      setTestNumber('');
    }
  };

  const getStatusBadgeColor = (status) => {
    return status === 'Terhubung' 
      ? 'bg-green-100 text-green-800' 
      : 'bg-red-100 text-red-800';
  };

  const statistics = useMemo(() => {
    const total = deviceList.length;
    const connected = deviceList.filter(d => d.status === 'Terhubung').length;
    const totalMessages = deviceList.reduce((sum, d) => sum + d.totalPesan, 0);
    const successRate = deviceList.reduce((sum, d) => sum + d.pesanBerhasil, 0) / totalMessages * 100 || 0;

    return { total, connected, totalMessages, successRate: successRate.toFixed(1) };
  }, [deviceList]);

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Manajemen WhatsApp Gateway</h1>
          <p className="text-sm text-gray-600 mt-1">Kelola device WhatsApp untuk notifikasi</p>
        </div>
        <button
          onClick={handleAddDevice}
          className="btn-primary flex items-center space-x-2"
        >
          <Plus className="w-5 h-5" />
          <span>Tambah Device</span>
        </button>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-blue-100">
              <Phone className="w-6 h-6 text-blue-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Device</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.total}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-green-100">
              <MessageSquare className="w-6 h-6 text-green-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Terhubung</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.connected}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-yellow-100">
              <Send className="w-6 h-6 text-yellow-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Pesan</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.totalMessages}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 rounded-full bg-purple-100">
              <MessageSquare className="w-6 h-6 text-purple-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Tingkat Sukses</p>
              <p className="text-2xl font-bold text-gray-900">{statistics.successRate}%</p>
            </div>
          </div>
        </div>
      </div>

      {/* Device List */}
      <div className="bg-white rounded-lg shadow">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Device
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Statistik
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Test
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Aksi
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan="5" className="px-6 py-4 text-center">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                      <span className="ml-2">Memuat data...</span>
                    </div>
                  </td>
                </tr>
              ) : (
                deviceList.map((device) => (
                  <tr key={device.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">{device.nama}</div>
                      <div className="text-sm text-gray-500">{device.nomorTelepon}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusBadgeColor(device.status)}`}>
                        {device.status}
                      </span>
                      <div className="text-xs text-gray-500 mt-1">
                        Last sync: {device.lastSync}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        Total: {device.totalPesan} pesan
                      </div>
                      <div className="text-xs text-green-600">
                        Berhasil: {device.pesanBerhasil}
                      </div>
                      <div className="text-xs text-red-600">
                        Gagal: {device.pesanGagal}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center space-x-2">
                        <input
                          type="text"
                          placeholder="Nomor test"
                          className="form-input text-sm"
                          value={testNumber}
                          onChange={(e) => setTestNumber(e.target.value)}
                        />
                        <button
                          onClick={() => handleTestMessage(device)}
                          disabled={sendingTest}
                          className="text-blue-600 hover:text-blue-800"
                        >
                          <Send className="w-5 h-5" />
                        </button>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex items-center space-x-3">
                        <button
                          onClick={() => handleEditDevice(device)}
                          className="text-blue-600 hover:text-blue-800"
                        >
                          <Edit className="w-5 h-5" />
                        </button>
                        <button
                          onClick={() => handleDeleteDevice(device.id)}
                          className="text-red-600 hover:text-red-800"
                        >
                          <Trash2 className="w-5 h-5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal for Add/Edit Device */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 className="text-lg font-medium text-gray-900 mb-4">
              {selectedDevice ? 'Edit Device' : 'Tambah Device'}
            </h3>
            <form>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nama Device
                  </label>
                  <input
                    type="text"
                    className="form-input w-full"
                    defaultValue={selectedDevice?.nama || ''}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nomor Telepon
                  </label>
                  <input
                    type="text"
                    className="form-input w-full"
                    defaultValue={selectedDevice?.nomorTelepon || ''}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    API Key
                  </label>
                  <input
                    type="text"
                    className="form-input w-full"
                    defaultValue={selectedDevice?.apiKey || ''}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Webhook URL
                  </label>
                  <input
                    type="text"
                    className="form-input w-full"
                    defaultValue={selectedDevice?.webhook || ''}
                  />
                </div>
              </div>
              <div className="flex justify-end space-x-3 mt-6">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="btn-secondary"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="btn-primary"
                >
                  {selectedDevice ? 'Update' : 'Simpan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default ManajemenWhatsApp;

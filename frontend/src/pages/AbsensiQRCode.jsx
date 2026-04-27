import React, { useState, useRef, useEffect } from 'react';
import { QrCode, MapPin, Check, X, RefreshCw, Camera } from 'lucide-react';
import { formatServerDateTime, getServerIsoString } from '../services/serverClock';

const AbsensiQRCode = () => {
  const videoRef = useRef(null);
  const [stream, setStream] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [qrResult, setQrResult] = useState(null);
  const [gpsStatus, setGpsStatus] = useState({
    active: false,
    coords: null,
    error: null
  });
  const [attendanceStatus, setAttendanceStatus] = useState('idle'); // idle, processing, success, error
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Request camera access for QR scanning
    startCamera();
    // Request GPS access
    checkGPS();

    return () => {
      stopCamera();
    };
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const startCamera = async () => {
    try {
      const mediaStream = await navigator.mediaDevices.getUserMedia({ 
        video: { 
          facingMode: 'environment', // Use back camera for QR scanning
          width: { ideal: 1280 },
          height: { ideal: 720 }
        } 
      });
      setStream(mediaStream);
      if (videoRef.current) {
        videoRef.current.srcObject = mediaStream;
      }
      setScanning(true);
    } catch (error) {
      console.error('Error accessing camera:', error);
      alert('Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.');
    }
  };

  const stopCamera = () => {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      setScanning(false);
    }
  };

  const checkGPS = () => {
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setGpsStatus({
            active: true,
            coords: {
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
              accuracy: position.coords.accuracy
            },
            error: null
          });
        },
        (error) => {
          setGpsStatus({
            active: false,
            coords: null,
            error: error.message
          });
        },
        {
          enableHighAccuracy: true,
          timeout: 5000,
          maximumAge: 0
        }
      );
    } else {
      setGpsStatus({
        active: false,
        coords: null,
        error: 'GPS tidak didukung di perangkat ini'
      });
    }
  };

  // Simulate QR code scanning
  const simulateQRScan = () => {
    if (!gpsStatus.active) {
      alert('GPS tidak aktif. Aktifkan GPS terlebih dahulu.');
      return;
    }

    setLoading(true);
    setAttendanceStatus('processing');

    // Simulate QR code detection
    setTimeout(() => {
      const mockQRData = {
        type: 'student_attendance',
        nis: '2024001',
        nama: 'Ahmad Fauzi',
        kelas: 'XII IPA 1',
        qrCode: 'QR-2024001-AHMAD-FAUZI',
        timestamp: getServerIsoString()
      };

      setQrResult(mockQRData);
      processAttendance(mockQRData);
    }, 2000);
  };

  const processAttendance = async (qrData) => {
    try {
      // Validate QR code
      if (!qrData || qrData.type !== 'student_attendance') {
        throw new Error('QR Code tidak valid');
      }

      // Check GPS location
      if (!gpsStatus.active || !gpsStatus.coords) {
        throw new Error('GPS tidak aktif');
      }

      // Simulate location validation
      const isLocationValid = await validateLocation(gpsStatus.coords);
      if (!isLocationValid) {
        throw new Error('Lokasi tidak valid untuk absensi');
      }

      // Submit attendance
      await submitAttendance(qrData, gpsStatus.coords);

      setAttendanceStatus('success');
    } catch (error) {
      console.error('Error processing attendance:', error);
      setAttendanceStatus('error');
      alert(error.message);
    }

    setLoading(false);
  };

  const validateLocation = async (coords) => {
    // Simulate location validation against allowed areas
    return new Promise((resolve) => {
      setTimeout(() => {
        // Mock validation - in real app, check against GPS areas from backend
        const isValid = Math.random() > 0.3; // 70% success rate
        resolve(isValid);
      }, 1000);
    });
  };

  const submitAttendance = async (qrData, gpsCoords) => {
    // Simulate API call
    return new Promise((resolve) => {
      setTimeout(() => {
        console.log('Submitting attendance:', {
          student: qrData,
          gps: gpsCoords,
          timestamp: getServerIsoString(),
          method: 'qr_code'
        });
        resolve(true);
      }, 1500);
    });
  };

  const resetScan = () => {
    setQrResult(null);
    setAttendanceStatus('idle');
    setLoading(false);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Absensi QR Code</h1>
          <p className="text-sm text-gray-600 mt-1">Scan QR Code siswa untuk melakukan absensi</p>
        </div>
      </div>

      {/* Camera Preview */}
      <div className="bg-white rounded-lg shadow-lg overflow-hidden">
        <div className="relative aspect-video max-w-2xl mx-auto">
          <video
            ref={videoRef}
            autoPlay
            playsInline
            muted
            className="w-full h-full object-cover"
          />

          {/* QR Scanning Overlay */}
          {scanning && !qrResult && (
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="border-4 border-white border-dashed w-64 h-64 rounded-lg flex items-center justify-center">
                <div className="text-white text-center p-4 bg-black/50 rounded-lg">
                  <QrCode className="w-8 h-8 mx-auto mb-2" />
                  <p>Arahkan kamera ke QR Code</p>
                </div>
              </div>
            </div>
          )}

          {/* Status Indicators */}
          <div className="absolute top-4 right-4 space-y-2">
            {/* GPS Status */}
            <div className={`flex items-center space-x-2 px-3 py-1 rounded-full text-sm ${
              gpsStatus.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`}>
              <MapPin className="w-4 h-4" />
              <span>{gpsStatus.active ? 'GPS Aktif' : 'GPS Tidak Aktif'}</span>
            </div>

            {/* Scanning Status */}
            {scanning && (
              <div className="flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-sm">
                <Camera className="w-4 h-4" />
                <span>Scanning...</span>
              </div>
            )}
          </div>

          {/* Processing Overlay */}
          {loading && (
            <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
              <div className="bg-white rounded-lg p-6 text-center">
                <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-4 text-blue-600" />
                <p className="text-gray-900 font-medium">Memproses absensi...</p>
                <p className="text-gray-600 text-sm">Validasi QR Code dan lokasi GPS</p>
              </div>
            </div>
          )}
        </div>

        {/* Controls */}
        <div className="p-4 bg-gray-50 border-t">
          <div className="flex justify-center space-x-4">
            {!qrResult ? (
              <button
                onClick={simulateQRScan}
                disabled={loading || !gpsStatus.active}
                className="btn-primary flex items-center space-x-2"
              >
                <QrCode className="w-5 h-5" />
                <span>Simulasi Scan QR</span>
              </button>
            ) : (
              <button
                onClick={resetScan}
                disabled={loading}
                className="btn-secondary flex items-center space-x-2"
              >
                <RefreshCw className="w-5 h-5" />
                <span>Scan Lagi</span>
              </button>
            )}
          </div>
        </div>
      </div>

      {/* QR Result */}
      {qrResult && (
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Data QR Code</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Nama Siswa</label>
              <p className="text-gray-900">{qrResult.nama}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">NIS</label>
              <p className="text-gray-900">{qrResult.nis}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Kelas</label>
              <p className="text-gray-900">{qrResult.kelas}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Waktu Scan</label>
              <p className="text-gray-900">{formatServerDateTime(qrResult.timestamp, 'id-ID') || '-'}</p>
            </div>
          </div>
        </div>
      )}

      {/* GPS Info */}
      {gpsStatus.coords && (
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Informasi Lokasi GPS</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Latitude</label>
              <p className="text-gray-900 font-mono">{gpsStatus.coords.latitude.toFixed(6)}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Longitude</label>
              <p className="text-gray-900 font-mono">{gpsStatus.coords.longitude.toFixed(6)}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Akurasi</label>
              <p className="text-gray-900">{Math.round(gpsStatus.coords.accuracy)} meter</p>
            </div>
          </div>
        </div>
      )}

      {/* Status Messages */}
      {attendanceStatus === 'success' && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-center">
            <Check className="w-6 h-6 text-green-600 mr-3" />
            <div>
              <h3 className="text-green-800 font-medium">Absensi Berhasil!</h3>
              <p className="text-green-700 text-sm">
                QR Code berhasil dipindai dan lokasi GPS valid. Absensi telah tercatat.
              </p>
            </div>
          </div>
        </div>
      )}

      {attendanceStatus === 'error' && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center">
            <X className="w-6 h-6 text-red-600 mr-3" />
            <div>
              <h3 className="text-red-800 font-medium">Gagal Melakukan Absensi</h3>
              <p className="text-red-700 text-sm">
                {gpsStatus.error || 'QR Code tidak valid atau lokasi GPS tidak sesuai.'}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Instructions */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 className="text-blue-800 font-medium mb-2">Petunjuk Absensi QR Code:</h3>
        <ul className="text-blue-700 text-sm space-y-1">
          <li>1. Pastikan GPS aktif di perangkat Anda</li>
          <li>2. Arahkan kamera ke QR Code siswa</li>
          <li>3. Pastikan QR Code terlihat jelas dalam frame</li>
          <li>4. Tunggu hingga QR Code terbaca otomatis</li>
          <li>5. Sistem akan memvalidasi lokasi GPS secara otomatis</li>
          <li>6. Absensi akan tercatat jika semua validasi berhasil</li>
        </ul>
      </div>

      {/* GPS Error */}
      {gpsStatus.error && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-center">
            <MapPin className="w-6 h-6 text-yellow-600 mr-3" />
            <div>
              <h3 className="text-yellow-800 font-medium">Masalah GPS</h3>
              <p className="text-yellow-700 text-sm">{gpsStatus.error}</p>
              <button
                onClick={checkGPS}
                className="mt-2 text-yellow-800 underline text-sm hover:text-yellow-900"
              >
                Coba lagi
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AbsensiQRCode;

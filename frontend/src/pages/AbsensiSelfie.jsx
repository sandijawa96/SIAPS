import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Camera, RefreshCw, Check, X, MapPin, ExternalLink, Image as ImageIcon } from 'lucide-react';
import { absensiAPI, lokasiGpsAPI, dashboardAPI, simpleAttendanceAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';

const normalizeRole = (roleName) =>
  String(roleName || '')
    .trim()
    .toLowerCase()
    .replace(/[_\s]+/g, ' ');

const mapVerificationModeLabel = (mode) => {
  if (mode === 'sync_final') return 'Sync Final';
  if (mode === 'async_pending') return 'Async Pending';
  return '-';
};

const mapVerificationStatusLabel = (status) => {
  if (status === 'verified') return 'Terverifikasi';
  if (status === 'pending') return 'Menunggu Verifikasi';
  if (status === 'manual_review') return 'Perlu Review Manual';
  if (status === 'rejected') return 'Ditolak';
  return '-';
};

const getVerificationToneClass = (status) => {
  if (status === 'verified') return 'bg-green-50 border-green-200 text-green-700';
  if (status === 'pending') return 'bg-yellow-50 border-yellow-200 text-yellow-700';
  if (status === 'manual_review') return 'bg-orange-50 border-orange-200 text-orange-700';
  if (status === 'rejected') return 'bg-red-50 border-red-200 text-red-700';
  return 'bg-gray-50 border-gray-200 text-gray-700';
};

const normalizeLocationRows = (rows) => {
  if (!Array.isArray(rows)) return [];

  return rows
    .filter((row) => row && row.id)
    .map((row) => ({
      ...row,
      id: Number(row.id),
      latitude: typeof row.latitude === 'string' ? parseFloat(row.latitude) : row.latitude,
      longitude: typeof row.longitude === 'string' ? parseFloat(row.longitude) : row.longitude,
      radius: typeof row.radius === 'string' ? parseFloat(row.radius) : row.radius,
    }));
};

const toNumberOrNull = (value) => {
  const number = typeof value === 'string' ? Number.parseFloat(value) : Number(value);
  return Number.isFinite(number) ? number : null;
};

const formatCoordinate = (value) => {
  const number = toNumberOrNull(value);
  return Number.isFinite(number) ? number.toFixed(6) : '-';
};

const formatAccuracy = (value) => {
  const number = toNumberOrNull(value);
  return Number.isFinite(number) ? `${number.toFixed(1)} m` : '-';
};

const buildMapUrl = (latitude, longitude) => {
  const lat = toNumberOrNull(latitude);
  const lng = toNumberOrNull(longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }

  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    return null;
  }

  return `https://maps.google.com/?q=${lat},${lng}`;
};

const AbsensiSelfie = () => {
  const { user, roles = [] } = useAuth();
  const videoRef = useRef(null);
  const canvasRef = useRef(null);
  const faceDetectorRef = useRef(null);
  const [stream, setStream] = useState(null);
  const [photo, setPhoto] = useState(null);
  const [loading, setLoading] = useState(false);
  const [gpsStatus, setGpsStatus] = useState({
    active: false,
    coords: null,
    error: null
  });
  const [faceDetected, setFaceDetected] = useState(false);
  const [status, setStatus] = useState('idle'); // idle, capturing, success, error
  const [availableLocations, setAvailableLocations] = useState([]);
  const [selectedLocation, setSelectedLocation] = useState(null);
  const [todayStatus, setTodayStatus] = useState(null);
  const [error, setError] = useState(null);
  const [faceDetectorReady, setFaceDetectorReady] = useState(false);
  const [faceDetectorError, setFaceDetectorError] = useState(null);
  const [attendancePolicy, setAttendancePolicy] = useState(null);
  const [scopeBlockedMessage, setScopeBlockedMessage] = useState(null);
  const [lastVerification, setLastVerification] = useState(null);

  const isStudentUser = roles.some((roleName) => normalizeRole(roleName) === 'siswa');
  const isStudentAttendanceUser = useMemo(
    () => isStudentUser || Boolean(user?.nis || user?.nisn),
    [isStudentUser, user?.nis, user?.nisn]
  );
  const isAttendanceBlocked = Boolean(scopeBlockedMessage);

  useEffect(() => {
    // Muat data awal
    loadInitialData();

    if (!isStudentAttendanceUser) {
      return () => {
        stopCamera();
      };
    }

    // Inisialisasi mesin deteksi wajah
    initializeFaceDetector();
    // Request camera access
    startCamera();
    // Request GPS access
    checkGPS();

    return () => {
      stopCamera();
    };
  }, [isStudentAttendanceUser]); // eslint-disable-line react-hooks/exhaustive-deps

  // Muat data awal
  const loadInitialData = async () => {
    try {
      setLoading(true);

      if (!isStudentAttendanceUser) {
        const policyResponse = await simpleAttendanceAPI.getGlobalSettings().catch(() => null);
        if (policyResponse?.data) {
          const policyPayload = policyResponse.data.data || policyResponse.data;
          setAttendancePolicy({
            ...policyPayload,
            attendance_scope: 'siswa_only',
          });
        }
        setTodayStatus(null);
        setAvailableLocations([]);
        setSelectedLocation(null);
        return;
      }
      
      const [statusResponse, attendanceSchemaResponse, activeLocationsResponse, policyResponse] = await Promise.all([
        dashboardAPI.getMyAttendanceStatus(),
        lokasiGpsAPI.getAttendanceSchema().catch(() => null),
        lokasiGpsAPI.getActive().catch(() => null),
        simpleAttendanceAPI.getGlobalSettings().catch(() => null)
      ]);

      setTodayStatus(statusResponse.data.data);
      const schemaLocations = normalizeLocationRows(attendanceSchemaResponse?.data?.data?.locations);
      const activeLocations = normalizeLocationRows(
        activeLocationsResponse?.data?.data || activeLocationsResponse?.data || []
      );
      const resolvedLocations = schemaLocations.length > 0 ? schemaLocations : activeLocations;
      setAvailableLocations(resolvedLocations);

      if (policyResponse?.data) {
        const policyPayload = policyResponse.data.data || policyResponse.data;
        setAttendancePolicy({
          ...policyPayload,
          attendance_scope: 'siswa_only',
        });
      }
      
      // Auto-pilih lokasi pertama jika hanya ada satu
      if (resolvedLocations.length === 1) {
        setSelectedLocation(resolvedLocations[0]);
      }
      
    } catch (error) {
      console.error('Error memuat data awal:', error);
      setError('Gagal memuat data. Silakan refresh halaman.');
    } finally {
      setLoading(false);
    }
  };

  const startCamera = async () => {
    try {
      const mediaStream = await navigator.mediaDevices.getUserMedia({ 
        video: { 
          facingMode: 'user',
          width: { ideal: 1280 },
          height: { ideal: 720 }
        } 
      });
      setStream(mediaStream);
      if (videoRef.current) {
        videoRef.current.srcObject = mediaStream;
      }
    } catch (error) {
      console.error('Error accessing camera:', error);
      setStatus('error');
    }
  };

  const stopCamera = () => {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
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

  const initializeFaceDetector = () => {
    if (typeof window === 'undefined' || !('FaceDetector' in window)) {
      setFaceDetectorReady(false);
      setFaceDetectorError('Browser belum mendukung FaceDetector API.');
      return;
    }

    try {
      // Native FaceDetector API untuk validasi wajah (non-simulasi).
      faceDetectorRef.current = new window.FaceDetector({
        fastMode: true,
        maxDetectedFaces: 1
      });
      setFaceDetectorReady(true);
      setFaceDetectorError(null);
    } catch (detectorError) {
      setFaceDetectorReady(false);
      setFaceDetectorError('Gagal inisialisasi deteksi wajah.');
      console.error('FaceDetector init error:', detectorError);
    }
  };

  useEffect(() => {
    if (!isStudentAttendanceUser) {
      setScopeBlockedMessage('Absensi aplikasi ini khusus siswa. Akun Anda terdeteksi sebagai non-siswa.');
      return;
    }

    const scope = attendancePolicy?.attendance_scope || 'siswa_only';
    if (scope === 'siswa_only' && !isStudentAttendanceUser) {
      setScopeBlockedMessage('Absensi saat ini dibatasi hanya untuk siswa. Akun Anda tidak termasuk scope absensi.');
      return;
    }

    setScopeBlockedMessage(null);
  }, [attendancePolicy, isStudentAttendanceUser]);

  const detectFace = async (imageData) => {
    if (!faceDetectorRef.current) {
      setFaceDetected(false);
      return {
        valid: false,
        message: faceDetectorError || 'Deteksi wajah belum siap.'
      };
    }

    let imageBitmap = null;

    try {
      const response = await fetch(imageData);
      const blob = await response.blob();
      imageBitmap = await createImageBitmap(blob);

      const faces = await faceDetectorRef.current.detect(imageBitmap);
      const detectedFaces = Array.isArray(faces) ? faces.length : 0;

      if (detectedFaces !== 1) {
        setFaceDetected(false);
        return {
          valid: false,
          message: detectedFaces === 0
            ? 'Wajah tidak terdeteksi. Pastikan wajah terlihat jelas di kamera.'
            : 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu wajah di frame.'
        };
      }

      setFaceDetected(true);
      return { valid: true, message: null };
    } catch (detectError) {
      setFaceDetected(false);
      console.error('FaceDetector detect error:', detectError);
      return {
        valid: false,
        message: 'Deteksi wajah gagal diproses. Coba ambil ulang foto.'
      };
    } finally {
      if (imageBitmap && typeof imageBitmap.close === 'function') {
        imageBitmap.close();
      }
    }
  };

  const capturePhoto = async () => {
    if (!videoRef.current || !canvasRef.current) return;

    setStatus('capturing');
    setLoading(true);
    setError(null);

    try {
      // Ambil foto dari video stream
      const video = videoRef.current;
      const canvas = canvasRef.current;
      const context = canvas.getContext('2d');

      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      // Dapatkan data gambar
      const imageData = canvas.toDataURL('image/jpeg');
      setPhoto(imageData);

      if (!faceDetectorReady) {
        setStatus('error');
        setError(faceDetectorError || 'Deteksi wajah belum siap. Gunakan browser yang didukung.');
        setLoading(false);
        return;
      }

      // Deteksi wajah
      const faceCheck = await detectFace(imageData);
      
      if (!faceCheck.valid) {
        setStatus('error');
        setError(faceCheck.message || 'Wajah tidak terdeteksi.');
        setLoading(false);
        return;
      }

      // Periksa GPS
      if (!gpsStatus.active || !gpsStatus.coords) {
        setStatus('error');
        setError('GPS tidak aktif. Aktifkan GPS dan coba lagi.');
        setLoading(false);
        return;
      }

      // Periksa lokasi yang dipilih
      if (!selectedLocation) {
        setStatus('error');
        setError('Pilih lokasi absensi terlebih dahulu.');
        setLoading(false);
        return;
      }

      if (isAttendanceBlocked) {
        setStatus('error');
        setError(scopeBlockedMessage || 'Akun Anda tidak memiliki akses absensi sesuai scope aktif.');
        setLoading(false);
        return;
      }

      // Kirim absensi ke API
      const success = await submitAttendance(imageData, gpsStatus.coords);
      
      if (success) {
        setStatus('success');
      }

    } catch (error) {
      console.error('Error mengambil foto:', error);
      setStatus('error');
      setError('Terjadi kesalahan saat mengambil foto.');
    }

    setLoading(false);
  };

  const submitAttendance = async (imageData, gpsCoords) => {
    if (isAttendanceBlocked) {
      setError(scopeBlockedMessage || 'Akses absensi dibatasi untuk akun Anda.');
      return false;
    }

    if (!selectedLocation) {
      setError('Pilih lokasi absensi terlebih dahulu');
      return false;
    }

    try {
      // Konversi base64 ke blob
      const response = await fetch(imageData);
      const blob = await response.blob();
      
      // Buat form data
      const formData = new FormData();
      formData.append('photo', blob, 'selfie.jpg');
      formData.append('lokasi_id', selectedLocation.id);
      formData.append('latitude', gpsCoords.latitude);
      formData.append('longitude', gpsCoords.longitude);
      if (typeof gpsCoords.accuracy === 'number') {
        formData.append('accuracy', gpsCoords.accuracy);
      }
      formData.append('metode', 'selfie');
      
      // Kirim ke API
      const result = await absensiAPI.checkIn(formData);
      
      if (result.data.success) {
        const verification = result.data.verification || result.data.data?.verification || null;
        setLastVerification(verification);
        setStatus('success');
        // Muat ulang status hari ini
        loadInitialData();
        return true;
      }
      
    } catch (error) {
      console.error('Error mengirim absensi:', error);
      const errorMessage = error.response?.data?.message || 'Gagal menyimpan absensi. Silakan coba lagi.';
      setError(errorMessage);
      setStatus('error');
      return false;
    }
  };

  // Kirim check-out
  const submitCheckOut = async () => {
    if (isAttendanceBlocked) {
      setError(scopeBlockedMessage || 'Akses absensi dibatasi untuk akun Anda.');
      return;
    }

    if (!gpsStatus.coords || !selectedLocation) {
      setError('Lokasi GPS diperlukan untuk check-out.');
      return;
    }

    setLoading(true);
    setError(null);
    
    try {
      const formData = new FormData();
      formData.append('lokasi_id', selectedLocation.id);
      formData.append('latitude', gpsStatus.coords.latitude);
      formData.append('longitude', gpsStatus.coords.longitude);
      if (typeof gpsStatus.coords.accuracy === 'number') {
        formData.append('accuracy', gpsStatus.coords.accuracy);
      }
      formData.append('metode', 'selfie');
      
      if (photo) {
        const response = await fetch(photo);
        const blob = await response.blob();
        formData.append('photo', blob, 'checkout-selfie.jpg');
      }
      
      const result = await absensiAPI.checkOut(formData);
      
      if (result.data.success) {
        const verification = result.data.verification || result.data.data?.verification || null;
        setLastVerification(verification);
        setStatus('success');
        alert('Check-out berhasil!');
        setPhoto(null);
        
        // Muat ulang status hari ini
        loadInitialData();
      }
      
    } catch (error) {
      console.error('Error check-out:', error);
      const errorMessage = error.response?.data?.message || 'Gagal check-out. Silakan coba lagi.';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const retake = () => {
    setPhoto(null);
    setStatus('idle');
    setFaceDetected(false);
  };
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Absensi dengan Selfie</h1>
          <p className="text-sm text-gray-600 mt-1">Pastikan wajah terlihat jelas dan GPS aktif</p>
          {user && (
            <p className="text-sm text-blue-600 mt-1">
              {(user.nama_lengkap || user.name || 'User')} - {(roles.join(', ') || user.role || '-')}
            </p>
          )}
        </div>
      </div>

      {scopeBlockedMessage && (
        <div className="bg-red-50 border border-red-300 rounded-lg p-4">
          <p className="text-red-700 text-sm font-medium">Akses Absensi Dibatasi</p>
          <p className="text-red-600 text-sm mt-1">{scopeBlockedMessage}</p>
        </div>
      )}

      {attendancePolicy && (
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-gray-800 mb-2">Policy Absensi Aktif</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <p><span className="text-gray-500">Mode Verifikasi:</span> <span className="font-medium">{mapVerificationModeLabel(attendancePolicy.verification_mode)}</span></p>
            <p><span className="text-gray-500">Scope:</span> <span className="font-medium">Siswa Saja</span></p>
          </div>
        </div>
      )}

      {lastVerification && (
        <div className={`border rounded-lg p-4 ${getVerificationToneClass(lastVerification.status)}`}>
          <h3 className="text-sm font-semibold mb-1">Hasil Verifikasi Terakhir</h3>
          <p className="text-sm">
            Status: <span className="font-medium">{mapVerificationStatusLabel(lastVerification.status)}</span>
            {' â€¢ '}Mode: <span className="font-medium">{mapVerificationModeLabel(lastVerification.mode)}</span>
          </p>
          {typeof lastVerification.score === 'number' && (
            <p className="text-xs mt-1">Score: {Number(lastVerification.score).toFixed(3)}</p>
          )}
        </div>
      )}

      {/* Pesan Error */}
      {error && (
        <div className="bg-red-50 border border-red-300 rounded-lg p-4">
          <p className="text-red-700 text-sm">{error}</p>
        </div>
      )}

      {!isAttendanceBlocked && (
        <>
      {/* Status Hari Ini */}
      {todayStatus && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="font-medium text-blue-800 mb-2">Status Hari Ini:</h3>
          {todayStatus.has_checked_in ? (
            <div className="space-y-1 text-sm">
              <p className="text-green-600">Sudah Check-in: {todayStatus.attendance?.jam_masuk}</p>
              {todayStatus.has_checked_out ? (
                <p className="text-blue-600">Sudah Check-out: {todayStatus.attendance?.jam_pulang || todayStatus.attendance?.jam_keluar}</p>
              ) : (
                <p className="text-orange-600">Belum Check-out</p>
              )}

              {(todayStatus.location_in || todayStatus.location_out) && (
                <div className="pt-2 mt-2 border-t border-blue-200 space-y-1">
                  {todayStatus.location_in && (
                    <p className="text-blue-700">Lokasi Masuk: {todayStatus.location_in}</p>
                  )}
                  <p className="text-blue-700">
                    Koordinat Masuk: {formatCoordinate(todayStatus.latitude_in)}, {formatCoordinate(todayStatus.longitude_in)}
                    {` • Akurasi: ${formatAccuracy(todayStatus.accuracy_in)}`}
                  </p>
                  {buildMapUrl(todayStatus.latitude_in, todayStatus.longitude_in) && (
                    <a
                      href={buildMapUrl(todayStatus.latitude_in, todayStatus.longitude_in)}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1 text-xs text-blue-700 hover:text-blue-900"
                    >
                      <MapPin className="w-3.5 h-3.5" />
                      <ExternalLink className="w-3.5 h-3.5" />
                      Buka Peta Masuk
                    </a>
                  )}

                  {todayStatus.has_checked_out && (
                    <>
                      {todayStatus.location_out && (
                        <p className="text-blue-700">Lokasi Keluar: {todayStatus.location_out}</p>
                      )}
                      <p className="text-blue-700">
                        Koordinat Keluar: {formatCoordinate(todayStatus.latitude_out)}, {formatCoordinate(todayStatus.longitude_out)}
                        {` • Akurasi: ${formatAccuracy(todayStatus.accuracy_out)}`}
                      </p>
                      {buildMapUrl(todayStatus.latitude_out, todayStatus.longitude_out) && (
                        <a
                          href={buildMapUrl(todayStatus.latitude_out, todayStatus.longitude_out)}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-xs text-blue-700 hover:text-blue-900"
                        >
                          <MapPin className="w-3.5 h-3.5" />
                          <ExternalLink className="w-3.5 h-3.5" />
                          Buka Peta Keluar
                        </a>
                      )}
                    </>
                  )}
                </div>
              )}
            </div>
          ) : (
            <p className="text-orange-600 text-sm">Belum absensi hari ini</p>
          )}
        </div>
      )}
      {/* Pilihan Lokasi */}
      {availableLocations.length > 1 && (
        <div className="bg-white rounded-lg shadow p-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Pilih Lokasi Absensi:
          </label>
          <select
            value={selectedLocation?.id || ''}
            onChange={(e) => {
              const location = availableLocations.find(loc => loc.id === parseInt(e.target.value));
              setSelectedLocation(location);
            }}
            className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Pilih lokasi...</option>
            {availableLocations.map((loc) => (
              <option key={loc.id} value={loc.id}>
                {loc.nama_lokasi}
              </option>
            ))}
          </select>
        </div>
      )}

      {/* Camera Preview */}
      <div className="bg-white rounded-lg shadow-lg overflow-hidden">
        <div className="relative aspect-video max-w-2xl mx-auto">
          {!photo ? (
            <video
              ref={videoRef}
              autoPlay
              playsInline
              muted
              className="w-full h-full object-cover"
            />
          ) : (
            <img
              src={photo}
              alt="Captured"
              className="w-full h-full object-cover"
            />
          )}

          {/* Face Detection Overlay */}
          {!photo && (
            <div className="absolute inset-0 border-4 border-dashed border-white/50 rounded-lg m-8">
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="text-white text-center p-4 bg-black/50 rounded-lg">
                  <Camera className="w-8 h-8 mx-auto mb-2" />
                  <p>Posisikan wajah di dalam area ini</p>
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

            {/* Lokasi Terpilih */}
            {selectedLocation && (
              <div className="flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-sm">
                <MapPin className="w-4 h-4" />
                <span>{selectedLocation.nama_lokasi}</span>
              </div>
            )}

            {/* Face Detection Status */}
            {faceDetected && (
              <div className="flex items-center space-x-2 px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm">
                <Check className="w-4 h-4" />
                <span>Wajah Terdeteksi</span>
              </div>
            )}

            {!faceDetected && faceDetectorReady && (
              <div className="flex items-center space-x-2 px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 text-sm">
                <ImageIcon className="w-4 h-4" />
                <span>Siap Verifikasi Wajah</span>
              </div>
            )}

            {!faceDetectorReady && (
              <div className="flex items-center space-x-2 px-3 py-1 rounded-full bg-red-100 text-red-800 text-sm">
                <X className="w-4 h-4" />
                <span>Deteksi Wajah Tidak Siap</span>
              </div>
            )}
          </div>
        </div>

        {/* Controls */}
        <div className="p-4 bg-gray-50 border-t">
          <div className="flex justify-center space-x-4">
            {!photo ? (
              <button
                onClick={capturePhoto}
                disabled={
                  loading ||
                  isAttendanceBlocked ||
                  status === 'success' ||
                  !selectedLocation ||
                  !gpsStatus.active ||
                  !faceDetectorReady
                }
                className="btn-primary flex items-center space-x-2 disabled:bg-gray-400 disabled:cursor-not-allowed"
              >
                {loading ? (
                  <RefreshCw className="w-5 h-5 animate-spin" />
                ) : (
                  <Camera className="w-5 h-5" />
                )}
                <span>{loading ? 'Memproses...' : 'Ambil Foto'}</span>
              </button>
            ) : (
              <>
                <button
                  onClick={retake}
                  disabled={loading}
                  className="btn-secondary flex items-center space-x-2"
                >
                  <RefreshCw className="w-5 h-5" />
                  <span>Ambil Ulang</span>
                </button>
                {status !== 'success' && (
                  <>
                    {!todayStatus?.has_checked_in ? (
                      <button
                        onClick={() => submitAttendance(photo, gpsStatus.coords)}
                        disabled={loading || isAttendanceBlocked}
                        className="btn-primary flex items-center space-x-2"
                      >
                        <Check className="w-5 h-5" />
                        <span>Check-in</span>
                      </button>
                    ) : !todayStatus?.has_checked_out ? (
                      <button
                        onClick={submitCheckOut}
                        disabled={loading || isAttendanceBlocked}
                        className="bg-orange-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-orange-700 transition-colors flex items-center space-x-2"
                      >
                        <Check className="w-5 h-5" />
                        <span>Check-out</span>
                      </button>
                    ) : (
                      <div className="bg-gray-400 text-white px-4 py-2 rounded-lg font-medium text-center">
                        âœ… Absensi Selesai
                      </div>
                    )}
                  </>
                )}
              </>
            )}
          </div>
        </div>
      </div>

      {/* Hidden Canvas for Processing */}
      <canvas ref={canvasRef} className="hidden" />

      {/* Status Messages */}
      {status === 'success' && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-center">
            <Check className="w-6 h-6 text-green-600 mr-3" />
            <div>
              <h3 className="text-green-800 font-medium">Absensi Berhasil!</h3>
              <p className="text-green-700 text-sm">
                Foto dan lokasi GPS berhasil direkam. Absensi Anda telah tercatat.
              </p>
            </div>
          </div>
        </div>
      )}

      {status === 'error' && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center">
            <X className="w-6 h-6 text-red-600 mr-3" />
            <div>
              <h3 className="text-red-800 font-medium">Gagal Melakukan Absensi</h3>
              <p className="text-red-700 text-sm">
                {gpsStatus.error || 'Pastikan wajah terlihat jelas dan GPS aktif.'}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Instructions */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 className="text-blue-800 font-medium mb-2">Petunjuk Absensi Selfie:</h3>
        <ul className="text-blue-700 text-sm space-y-1">
          <li>1. Pastikan wajah terlihat jelas dan berada dalam frame</li>
          <li>2. Pastikan browser mendukung FaceDetector API</li>
          <li>3. Aktifkan GPS di perangkat Anda</li>
          <li>4. Pilih lokasi absensi yang sesuai</li>
          <li>5. Pastikan pencahayaan cukup</li>
          <li>6. Jangan gunakan masker saat pengambilan foto</li>
          <li>7. Check-in di pagi hari, check-out di sore hari</li>
        </ul>
      </div>
        </>
      )}
    </div>
  );
};

export default AbsensiSelfie;


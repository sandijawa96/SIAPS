import React, { useEffect, useState } from 'react';
import { User, Mail, Phone, MapPin, Camera, Save, RefreshCw } from 'lucide-react';
import { authAPI, personalDataAPI } from '../services/api';
import { resolveProfilePhotoUrl } from '../utils/profilePhoto';

const Profile = () => {
  const [profile, setProfile] = useState({
    nama: '',
    email: '',
    telepon: '',
    alamat: '',
    foto: null,
    role: '',
  });
  const [isEditing, setIsEditing] = useState(false);
  const [previewImage, setPreviewImage] = useState(null);
  const [selectedPhotoFile, setSelectedPhotoFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [infoMessage, setInfoMessage] = useState('');

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await authAPI.profile();
      const user = response?.data?.data || {};
      const firstRole = Array.isArray(user.roles) && user.roles.length > 0 ? user.roles[0] : null;
      setProfile({
        nama: user.nama_lengkap || user.name || '',
        email: user.email || '',
        telepon: user.no_hp || user.telepon || '',
        alamat: user.alamat || '',
        foto: resolveProfilePhotoUrl(user.foto_profil_url || user.foto_profil),
        role: firstRole?.display_name || firstRole?.name || user.role || '',
      });
      setPreviewImage(null);
      setSelectedPhotoFile(null);
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal memuat profil');
    } finally {
      setLoading(false);
    }
  };

  const handleImageChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      setSelectedPhotoFile(null);
      setError('Ukuran foto maksimal 2MB');
      return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
      setSelectedPhotoFile(null);
      setError('Format foto harus JPG atau PNG');
      return;
    }

    setError('');
    setSelectedPhotoFile(file);
    const reader = new FileReader();
    reader.onloadend = () => {
      setPreviewImage(reader.result);
    };
    reader.readAsDataURL(file);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setInfoMessage('');

    try {
      const payload = {
        nama_lengkap: profile.nama,
        email: profile.email,
      };

      await authAPI.updateProfile(payload);

      if (selectedPhotoFile) {
        await personalDataAPI.updateAvatar(selectedPhotoFile);
      }

      setInfoMessage('Profil berhasil diperbarui');
      setIsEditing(false);
      await fetchProfile();
    } catch (err) {
      setError(err?.response?.data?.message || 'Gagal memperbarui profil');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-4xl mx-auto flex items-center justify-center py-16 text-gray-600">
        <RefreshCw className="w-5 h-5 animate-spin mr-2" />
        Memuat profil...
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Profil Saya</h1>
        <p className="text-sm text-gray-600 mt-1">Kelola informasi profil Anda</p>
      </div>

      {error && (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}
      {infoMessage && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {infoMessage}
        </div>
      )}

      <div className="bg-white rounded-lg shadow">
        <div className="p-6 border-b">
          <div className="flex items-center space-x-4">
            <div className="relative">
              <div className="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                {previewImage || profile.foto ? (
                  <img src={previewImage || profile.foto} alt="Profile" className="w-full h-full object-cover" />
                ) : (
                  <User className="w-12 h-12 text-gray-400" />
                )}
              </div>
              {isEditing && (
                <label className="absolute bottom-0 right-0 bg-blue-500 rounded-full p-2 cursor-pointer">
                  <Camera className="w-4 h-4 text-white" />
                  <input
                    type="file"
                    className="hidden"
                    accept="image/png,image/jpeg,image/jpg"
                    onChange={handleImageChange}
                  />
                </label>
              )}
            </div>
            <div>
              <h2 className="text-xl font-semibold text-gray-900">{profile.nama || '-'}</h2>
              <p className="text-sm text-gray-600">{profile.role || '-'}</p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <User className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="text"
                  value={profile.nama}
                  onChange={(e) => setProfile((prev) => ({ ...prev, nama: e.target.value }))}
                  disabled={!isEditing}
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm disabled:bg-gray-50 disabled:text-gray-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Email</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="email"
                  value={profile.email}
                  onChange={(e) => setProfile((prev) => ({ ...prev, email: e.target.value }))}
                  disabled={!isEditing}
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm disabled:bg-gray-50 disabled:text-gray-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Phone className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="text"
                  value={profile.telepon || '-'}
                  disabled
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm bg-gray-50 text-gray-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <MapPin className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="text"
                  value={profile.alamat || '-'}
                  disabled
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm bg-gray-50 text-gray-500"
                />
              </div>
            </div>
          </div>

          <div className="mt-6 flex justify-end space-x-3">
            {isEditing ? (
              <>
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setPreviewImage(null);
                    setSelectedPhotoFile(null);
                    fetchProfile();
                  }}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300"
                >
                  {saving ? (
                    <>
                      <RefreshCw className="w-4 h-4 inline-block mr-2 animate-spin" />
                      Menyimpan...
                    </>
                  ) : (
                    <>
                      <Save className="w-4 h-4 inline-block mr-2" />
                      Simpan Perubahan
                    </>
                  )}
                </button>
              </>
            ) : (
              <button
                type="button"
                onClick={() => setIsEditing(true)}
                className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
              >
                Edit Profil
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

export default Profile;

import React, { useState, useEffect } from 'react';
import { X, User, Mail, Calendar, GraduationCap, Lock, Camera, Phone, Hash, CreditCard } from 'lucide-react';
import toast from 'react-hot-toast';
import { useNavigate } from 'react-router-dom';
import { siswaAPI, kelasAPI, tahunAjaranAPI } from '../services/api';
import { getServerDateParts, getServerDateString } from '../services/serverClock';

const normalizeLocalPhoneInput = (value = '') => {
  let digits = String(value).replace(/\D/g, '');

  if (digits.startsWith('62')) {
    digits = digits.slice(2);
  }

  if (digits.startsWith('0')) {
    digits = digits.slice(1);
  }

  return digits;
};

const buildCanonicalPhone = (localInput = '') => {
  const localDigits = normalizeLocalPhoneInput(localInput);
  if (!localDigits) {
    return '';
  }

  return `62${localDigits}`;
};

const extractYearFromDateValue = (value = '') => {
  const match = String(value).match(/^(\d{4})-\d{2}-\d{2}$/);
  return match ? match[1] : '';
};

const TambahSiswa = ({ open, onClose, onSuccess, redirectToKelas = false, selectedKelas, tahunAjaran: activeTahunAjaran }) => {
  const navigate = useNavigate();
  const [kelas, setKelas] = useState([]);
  const [tahunAjaran, setTahunAjaran] = useState([]);
  const [previewImage, setPreviewImage] = useState(null);
  
  const [formData, setFormData] = useState({
    nama_lengkap: '',
    nisn: '',
    nis: '',
    email: '',
    tanggal_lahir: '',
    tanggal_masuk: '',
    tahun_masuk: '',
    jenis_kelamin: '',
    kelas_id: '',
    tahun_ajaran_id: '',
    no_telepon_ortu: '',
    username: '',
    password: '',
    foto_profil: null
  });

  // Auto-generate username dan password
  useEffect(() => {
    if (formData.nis) {
      setFormData(prev => ({
        ...prev,
        username: formData.nis
      }));
    }
  }, [formData.nis]);

  useEffect(() => {
    if (formData.tanggal_lahir) {
      // Format tanggal lahir menjadi password (DDMMYYYY)
      const dateParts = getServerDateParts(formData.tanggal_lahir);
      if (!dateParts) {
        return;
      }

      const day = String(dateParts.day).padStart(2, '0');
      const month = String(dateParts.month).padStart(2, '0');
      const year = dateParts.year;
      const password = `${day}${month}${year}`;
      
      setFormData(prev => ({
        ...prev,
        password: password
      }));
    }
  }, [formData.tanggal_lahir]);

  // Load kelas dan tahun ajaran data
  useEffect(() => {
    if (open) {
      loadKelasData();
      loadTahunAjaranData();

      // Prefill konteks manajemen kelas: kelas + tahun ajaran + tanggal masuk default hari ini.
      setFormData((prev) => {
        const next = { ...prev };

        if (selectedKelas?.id) {
          next.kelas_id = String(selectedKelas.id);

          if (selectedKelas?.tahun_ajaran_id) {
            next.tahun_ajaran_id = String(selectedKelas.tahun_ajaran_id);
          } else if (activeTahunAjaran?.id) {
            next.tahun_ajaran_id = String(activeTahunAjaran.id);
          }

          if (!next.tanggal_masuk) {
            next.tanggal_masuk = getServerDateString();
          }

          if (!next.tahun_masuk) {
            next.tahun_masuk = extractYearFromDateValue(next.tanggal_masuk);
          }
        } else if (redirectToKelas && activeTahunAjaran?.id) {
          next.tahun_ajaran_id = String(activeTahunAjaran.id);
        }

        return next;
      });
    }
  }, [open, selectedKelas, activeTahunAjaran, redirectToKelas]);

  const loadKelasData = async () => {
    try {
      if (selectedKelas) {
        // Jika ada selectedKelas dari props, gunakan itu
        setKelas([selectedKelas]);
      } else {
        // Jika tidak ada, load semua kelas
        const response = await kelasAPI.getAll();
        const data = response.data?.data || response.data || [];
        // Pastikan data adalah array
        if (Array.isArray(data)) {
          setKelas(data);
        } else {
          console.warn('Kelas data is not an array:', data);
          setKelas([]);
        }
      }
    } catch (error) {
      console.error('Error loading kelas:', error);
      setKelas([]); // Set empty array on error
      toast.error('Gagal memuat data kelas');
    }
  };

  const loadTahunAjaranData = async () => {
    try {
      if (activeTahunAjaran) {
        // Jika ada activeTahunAjaran dari props, gunakan itu
        setTahunAjaran([activeTahunAjaran]);
      } else {
        // Jika tidak ada, load semua tahun ajaran
        const response = await tahunAjaranAPI.getAll({ no_pagination: true });
        const data = response.data?.data || [];
        // Pastikan data adalah array
        if (Array.isArray(data)) {
          setTahunAjaran(data);
        } else {
          console.warn('Tahun ajaran data is not an array:', data);
          setTahunAjaran([]);
        }
      }
    } catch (error) {
      console.error('Error loading tahun ajaran:', error);
      setTahunAjaran([]); // Set empty array on error
      toast.error('Gagal memuat data tahun ajaran');
    }
  };

  const handleFormChange = (event) => {
    const { name, value, files } = event.target;
    
    if (name === 'foto_profil' && files && files[0]) {
      const file = files[0];
      
      // Validasi ukuran file (maksimal 2MB)
      if (file.size > 2 * 1024 * 1024) {
        toast.error('Ukuran foto maksimal 2MB');
        return;
      }

      // Validasi tipe file
      const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
      if (!allowedTypes.includes(file.type)) {
        toast.error('Format foto harus JPG atau PNG');
        return;
      }

      // Preview image
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviewImage(reader.result);
      };
      reader.readAsDataURL(file);

      setFormData(prev => ({
        ...prev,
        foto_profil: file
      }));
    } else {
      if (name === 'no_telepon_ortu') {
        const normalizedLocal = normalizeLocalPhoneInput(value);
        setFormData(prev => ({
          ...prev,
          no_telepon_ortu: normalizedLocal
        }));
        return;
      }

      setFormData(prev => ({
        ...prev,
        [name]: value,
        ...(name === 'tanggal_masuk' && !prev.tahun_masuk
          ? { tahun_masuk: extractYearFromDateValue(value) }
          : {})
      }));
    }
  };

  const resetForm = () => {
    setFormData({
      nama_lengkap: '',
      nisn: '',
      nis: '',
      email: '',
      tanggal_lahir: '',
      tanggal_masuk: '',
      tahun_masuk: '',
      jenis_kelamin: '',
      kelas_id: '',
      tahun_ajaran_id: '',
      no_telepon_ortu: '',
      username: '',
      password: '',
      foto_profil: null
    });
    setPreviewImage(null);
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    const hasKelasSelection = Boolean(formData.kelas_id);
    if (hasKelasSelection && (!formData.tahun_ajaran_id || !formData.tanggal_masuk)) {
      toast.error('Jika ingin menetapkan kelas, isi Kelas, Tahun Ajaran, dan Tanggal Masuk secara lengkap');
      return;
    }
    
    try {
      const normalizedParentPhone = buildCanonicalPhone(formData.no_telepon_ortu);
      if (!/^628[0-9]{7,12}$/.test(normalizedParentPhone)) {
        toast.error('No. Handphone orang tua harus format Indonesia yang valid (8xxxxxxx).');
        return;
      }

      const formDataToSend = new FormData();
      const normalizedFormData = {
        ...formData,
        no_telepon_ortu: normalizedParentPhone,
        ...(hasKelasSelection
          ? {}
          : {
              tahun_ajaran_id: '',
              tanggal_masuk: '',
            }),
      };
      
      // Log the form data being sent
      console.log('Form Data:', normalizedFormData);
      
      // Add all form fields except alamat since it's no longer required
      Object.keys(normalizedFormData).forEach(key => {
        if (normalizedFormData[key] !== null && normalizedFormData[key] !== '') {
          formDataToSend.append(key, normalizedFormData[key]);
        }
      });

      // Log the FormData entries
      for (let pair of formDataToSend.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
      }

      const response = await siswaAPI.tambah(formDataToSend);
      console.log('API Response:', response);
      
      toast.success('Siswa berhasil ditambahkan');
      resetForm();
      onSuccess();
      onClose();

      if (redirectToKelas && formData.kelas_id) {
        const selectedKelas = kelas.find(k => k.id === parseInt(formData.kelas_id));
        navigate('/manajemen-kelas', {
          state: {
            newStudent: true,
            kelasId: formData.kelas_id,
            kelasName: selectedKelas?.namaKelas || ''
          }
        });
      }
    } catch (error) {
      console.error('Error details:', {
        response: error.response?.data,
        status: error.response?.status,
        message: error.message
      });

      if (error.response?.data?.errors) {
        // Show all validation errors
        const errorMessages = Object.entries(error.response.data.errors)
          .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
          .join('\n');
        toast.error(errorMessages);
      } else if (error.response?.data?.message) {
        toast.error(error.response.data.message);
      } else {
        toast.error('Gagal menambahkan siswa: ' + error.message);
      }
    }
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {/* Backdrop */}
        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
          <div className="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>
        </div>
        
        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        {/* Modal */}
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
          <form onSubmit={handleSubmit}>
            {/* Header */}
            <div className="bg-gradient-to-r from-green-600 to-emerald-700 px-6 py-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                    <GraduationCap className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-white">Tambah Siswa Baru</h3>
                    <p className="text-green-100 text-sm">Lengkapi data siswa dengan benar</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={handleClose}
                  className="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
                >
                  <X className="w-5 h-5 text-white" />
                </button>
              </div>
            </div>

            {/* Content */}
            <div className="px-6 py-6 max-h-[calc(100vh-200px)] overflow-y-auto">
              {/* Photo Upload Section */}
              <div className="flex justify-center mb-6">
                <div className="relative">
                  <div className="w-32 h-32 rounded-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center border-4 border-white shadow-lg">
                    {previewImage ? (
                      <img
                        src={previewImage}
                        alt="Preview"
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <User className="w-16 h-16 text-gray-400" />
                    )}
                  </div>
                  <label className="absolute bottom-0 right-0 p-2 bg-green-600 rounded-full cursor-pointer hover:bg-green-700 transition-colors shadow-lg">
                    <Camera className="w-4 h-4 text-white" />
                    <input
                      type="file"
                      className="hidden"
                      name="foto_profil"
                      onChange={handleFormChange}
                      accept="image/*"
                    />
                  </label>
                </div>
              </div>

              <div className="space-y-6">
                {/* Data Pribadi */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <h4 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                    <User className="w-5 h-5 mr-2 text-green-600" />
                    Data Pribadi
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2 space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Nama Lengkap *
                      </label>
                      <div className="relative">
                        <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="text"
                          name="nama_lengkap"
                          required
                          value={formData.nama_lengkap}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                          placeholder="Masukkan nama lengkap siswa"
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        NISN *
                      </label>
                      <div className="relative">
                        <Hash className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="text"
                          name="nisn"
                          required
                          value={formData.nisn}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                          placeholder="Masukkan NISN"
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        NIS *
                      </label>
                      <div className="relative">
                        <CreditCard className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="text"
                          name="nis"
                          required
                          value={formData.nis}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                          placeholder="Masukkan NIS"
                        />
                      </div>
                    </div>

                    <div className="md:col-span-2 space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Email *
                      </label>
                      <div className="relative">
                        <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="email"
                          name="email"
                          required
                          value={formData.email}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                          placeholder="nama@email.com"
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Tanggal Lahir *
                      </label>
                      <div className="relative">
                        <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="date"
                          name="tanggal_lahir"
                          required
                          value={formData.tanggal_lahir}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Jenis Kelamin *
                      </label>
                      <div className="relative">
                        <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <select
                          name="jenis_kelamin"
                          required
                          value={formData.jenis_kelamin}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                        >
                          <option value="">Pilih Jenis Kelamin</option>
                          <option value="L">Laki-laki</option>
                          <option value="P">Perempuan</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Data Akademik */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <h4 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                    <GraduationCap className="w-5 h-5 mr-2 text-green-600" />
                    Data Akademik
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Tahun Masuk
                      </label>
                      <div className="relative">
                        <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="number"
                          name="tahun_masuk"
                          min="1900"
                          max="2100"
                          value={formData.tahun_masuk}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                          placeholder="Contoh: 2026"
                        />
                      </div>
                      <p className="text-xs text-gray-500">
                        Data profil siswa. Nilai ini tidak ikut berpindah saat kelas aktif berubah di tahun ajaran berikutnya.
                      </p>
                    </div>

                    <div className="md:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-800">
                      Penempatan awal hanya dipakai saat siswa pertama kali masuk ke histori kelas. Perubahan kelas tahunan dilakukan dari modul kelas/transisi, bukan dari form akun.
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Kelas Awal
                      </label>
                      <div className="relative">
                        <GraduationCap className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <select
                          name="kelas_id"
                          value={formData.kelas_id}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                        >
                          <option value="">Pilih Kelas</option>
                          {Array.isArray(kelas) && kelas.map((k) => (
                            <option key={k.id} value={k.id}>
                              {k.namaKelas}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        TA Kelas Awal
                      </label>
                      <div className="relative">
                        <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <select
                          name="tahun_ajaran_id"
                          value={formData.tahun_ajaran_id}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                        >
                          <option value="">Pilih Tahun Ajaran</option>
                          {Array.isArray(tahunAjaran) && tahunAjaran.map((ta) => (
                            <option key={ta.id} value={ta.id}>
                              {ta.nama}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Tanggal Masuk Kelas Awal
                      </label>
                      <div className="relative">
                        <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="date"
                          name="tanggal_masuk"
                          value={formData.tanggal_masuk}
                          onChange={handleFormChange}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                        />
                      </div>
                    </div>

                    <div className="md:col-span-2 space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        No. Telepon Orang Tua *
                      </label>
                      <div className="relative">
                        <Phone className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <div className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm transition-all duration-200 focus-within:ring-2 focus-within:ring-green-500 focus-within:border-transparent flex items-center gap-2">
                          <span className="text-gray-600 font-medium select-none">+62</span>
                          <input
                            type="text"
                            name="no_telepon_ortu"
                            required
                            value={formData.no_telepon_ortu}
                            onChange={handleFormChange}
                            className="flex-1 min-w-0 bg-transparent outline-none text-gray-900 placeholder-gray-400"
                            placeholder="8xxxxxxxxxx"
                            inputMode="numeric"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Data Akun */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <h4 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                    <Lock className="w-5 h-5 mr-2 text-green-600" />
                    Data Akun
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Username (Auto-generate dari NIS)
                      </label>
                      <div className="relative">
                        <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="text"
                          name="username"
                          disabled
                          value={formData.username}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm bg-gray-50 text-gray-500"
                          placeholder="Username akan otomatis terisi"
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <label className="block text-sm font-semibold text-gray-700">
                        Password (Auto-generate dari Tanggal Lahir)
                      </label>
                      <div className="relative">
                        <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                          type="text"
                          name="password"
                          disabled
                          value={formData.password}
                          className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl shadow-sm bg-gray-50 text-gray-500"
                          placeholder="Password akan otomatis terisi"
                        />
                      </div>
                    </div>
                  </div>

                  <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div className="flex">
                      <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                          <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                      </div>
                      <div className="ml-3">
                        <p className="text-sm text-green-700">
                          Username akan di-generate otomatis dari NIS dan Password akan di-generate dari Tanggal Lahir (format: DDMMYYYY)
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Foto Profil Info */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <h4 className="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                    <Camera className="w-5 h-5 mr-2 text-green-600" />
                    Foto Profil
                  </h4>
                  <p className="text-sm text-gray-500">
                    Foto profil dapat diupload melalui tombol kamera di atas. Format: JPG, PNG, GIF (Maks. 2MB)
                  </p>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
              <button
                type="button"
                onClick={handleClose}
                className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors"
              >
                Batal
              </button>
              <button
                type="submit"
                className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200"
              >
                Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default TambahSiswa;

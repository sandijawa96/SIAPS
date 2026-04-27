import api from './api';

export const pegawaiAPIExtended = {
  // Get all pegawai with filters
  getAll: (params) => api.get('/pegawai', { params }),

  // Get single pegawai by ID
  getById: (id) => api.get(`/pegawai/${id}`),

  // Create new pegawai with full field support
  create: (data) => {
    const formData = new FormData();
    
    // Data Akun
    if (data.username) formData.append('username', data.username);
    if (data.email) formData.append('email', data.email);
    if (data.password) formData.append('password', data.password);
    if (data.role) formData.append('role', data.role);
    if (data.sub_role) formData.append('sub_role', data.sub_role);
    formData.append('is_active', data.is_active !== undefined ? data.is_active : true);
    
    // Data Pribadi
    if (data.nama_lengkap) formData.append('nama_lengkap', data.nama_lengkap);
    if (data.tempat_lahir) formData.append('tempat_lahir', data.tempat_lahir);
    if (data.tanggal_lahir) formData.append('tanggal_lahir', data.tanggal_lahir);
    if (data.jenis_kelamin) formData.append('jenis_kelamin', data.jenis_kelamin);
    if (data.agama) formData.append('agama', data.agama);
    
    // Data Alamat & Kontak
    if (data.alamat) formData.append('alamat', data.alamat);
    if (data.no_telepon) formData.append('no_telepon', data.no_telepon);
    
    // Data Kepegawaian
    if (data.status_kepegawaian) formData.append('status_kepegawaian', data.status_kepegawaian);
    if (data.nip) formData.append('nip', data.nip);
    if (data.nuptk) formData.append('nuptk', data.nuptk);
    if (data.golongan) formData.append('golongan', data.golongan);
    if (data.tmt_kerja) formData.append('tmt_kerja', data.tmt_kerja);
    if (data.jabatan) formData.append('jabatan', data.jabatan);
    
    // Data Pendidikan
    if (data.pendidikan_terakhir) formData.append('pendidikan_terakhir', data.pendidikan_terakhir);
    if (data.jurusan) formData.append('jurusan', data.jurusan);
    if (data.universitas) formData.append('universitas', data.universitas);
    if (data.tahun_lulus) formData.append('tahun_lulus', data.tahun_lulus);
    
    // Pengaturan Absensi - wajib_absen akan diatur otomatis berdasarkan status_kepegawaian
    if (data.metode_absensi) formData.append('metode_absensi', JSON.stringify(data.metode_absensi));
    if (data.gps_tracking !== undefined) formData.append('gps_tracking', data.gps_tracking);
    if (data.hari_kerja) formData.append('hari_kerja', JSON.stringify(data.hari_kerja));
    if (data.mengikuti_kaldik !== undefined) formData.append('mengikuti_kaldik', data.mengikuti_kaldik);
    if (data.lokasi_default !== undefined) formData.append('lokasi_default', data.lokasi_default);
    if (data.notifikasi) formData.append('notifikasi', JSON.stringify(data.notifikasi));
    
    // File foto
    if (data.foto_profil && data.foto_profil instanceof File) {
      formData.append('foto_profil', data.foto_profil);
    }

    return api.post('/pegawai', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },

  // Update existing pegawai with full field support
  update: (id, data) => {
    // Jika data sudah dalam bentuk FormData, kirim langsung
    if (data instanceof FormData) {
      return api.put(`/pegawai/${id}`, data, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
    }

    // Jika data dalam bentuk object, konversi ke FormData
    const formData = new FormData();
    
    // Data Akun
    if (data.username) formData.append('username', data.username);
    if (data.email) formData.append('email', data.email);
    if (data.role) formData.append('role', data.role);
    if (data.sub_role) formData.append('sub_role', data.sub_role);
    if (data.is_active !== undefined) formData.append('is_active', data.is_active);
    
    // Data Pribadi & Kepegawaian
    if (data.nama_lengkap) formData.append('nama_lengkap', data.nama_lengkap);
    if (data.jenis_kelamin) formData.append('jenis_kelamin', data.jenis_kelamin);
    if (data.tanggal_lahir) formData.append('tanggal_lahir', data.tanggal_lahir);
    if (data.alamat) formData.append('alamat', data.alamat);
    if (data.no_telepon) formData.append('no_telepon', data.no_telepon);
    if (data.status_kepegawaian) formData.append('status_kepegawaian', data.status_kepegawaian);
    if (data.nip) formData.append('nip', data.nip);
    
    // File foto jika ada
    if (data.foto_profil && data.foto_profil instanceof File) {
      formData.append('foto_profil', data.foto_profil);
    }

    return api.put(`/pegawai/${id}`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
  },

  // Delete pegawai
  delete: (id) => api.delete(`/pegawai/${id}`),

  // Reset pegawai password
  resetPassword: (id, data) => api.post(`/pegawai/${id}/reset-password`, data),

  // Get pegawai statistics
  getStats: () => api.get('/pegawai/stats')
};

export default pegawaiAPIExtended;

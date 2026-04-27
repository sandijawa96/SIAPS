import api from './api.js';

const pegawaiExtendedService = {
  // Get all pegawai with extended data
  getAll: async (params = {}) => {
    try {
      const response = await api.get('/pegawai-extended', { params });
      
      // Handle paginated response structure
      if (response.data?.success && response.data.data) {
        return response.data;
      }
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get single pegawai by ID with extended data
  getById: async (id) => {
    try {
      const response = await api.get(`/pegawai-extended/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Create new pegawai with extended data
  create: async (data) => {
    try {
      // Check if there's a file upload
      if (data.foto_profil instanceof File) {
        const formData = new FormData();
        
        // Append all data to FormData
        Object.keys(data).forEach(key => {
          if (data[key] !== null && data[key] !== undefined) {
            if (key === 'is_active') {
              formData.append(key, Boolean(data[key]));
            } else if (Array.isArray(data[key])) {
              data[key].forEach((item, index) => {
                formData.append(`${key}[${index}]`, item);
              });
            } else if (data[key] instanceof File) {
              formData.append(key, data[key]);
            } else {
              formData.append(key, data[key]);
            }
          }
        });

        const response = await api.post('/pegawai-extended', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
        return response.data;
      } else {
        // If no file upload, send as JSON
        const dataToSend = { ...data };
        if ('foto_profil' in dataToSend && !dataToSend.foto_profil) {
          delete dataToSend.foto_profil;
        }
        dataToSend.is_active = Boolean(dataToSend.is_active);

        const response = await api.post('/pegawai-extended', dataToSend);
        return response.data;
      }
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Update pegawai extended data (only data_kepegawaian fields)
  update: async (id, data) => {
    try {
      // For extended service, we only send data_kepegawaian fields
      const kepegawaianData = {
        no_hp: data.no_hp || data.no_telepon,
        no_telepon_kantor: data.no_telepon_kantor,
        nomor_sk: data.nomor_sk,
        tanggal_sk: data.tanggal_sk,
        golongan: data.golongan,
        tmt: data.tmt,
        masa_kontrak_mulai: data.masa_kontrak_mulai,
        masa_kontrak_selesai: data.masa_kontrak_selesai,
        nuptk: data.nuptk,
        jabatan: data.jabatan,
        sub_jabatan: data.sub_jabatan,
        pangkat_golongan: data.pangkat_golongan,
        pendidikan_terakhir: data.pendidikan_terakhir,
        jurusan: data.jurusan,
        universitas: data.universitas,
        institusi: data.institusi,
        tahun_lulus: data.tahun_lulus,
        no_ijazah: data.no_ijazah,
        gelar_depan: data.gelar_depan,
        gelar_belakang: data.gelar_belakang,
        bidang_studi: data.bidang_studi,
        mata_pelajaran: data.mata_pelajaran,
        jam_mengajar_per_minggu: data.jam_mengajar_per_minggu,
        kelas_yang_diajar: data.kelas_yang_diajar,
        nama_pasangan: data.nama_pasangan,
        pekerjaan_pasangan: data.pekerjaan_pasangan,
        jumlah_anak: data.jumlah_anak,
        data_anak: data.data_anak,
        alamat_domisili: data.alamat_domisili,
        keterangan: data.keterangan,
        sertifikat: data.sertifikat,
        pelatihan: data.pelatihan
      };

      // Remove undefined/null values
      Object.keys(kepegawaianData).forEach(key => {
        if (kepegawaianData[key] === undefined || kepegawaianData[key] === null) {
          delete kepegawaianData[key];
        }
      });

      const response = await api.put(`/pegawai-extended/${id}`, kepegawaianData);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Delete pegawai
  delete: async (id) => {
    try {
      const response = await api.delete(`/pegawai-extended/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Reset password pegawai
  resetPassword: async (id, data) => {
    try {
      const response = await api.post(`/pegawai-extended/${id}/reset-password`, data);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Export data pegawai lengkap ke Excel
  export: async (params = {}) => {
    try {
      const response = await api.get('/pegawai-extended/export', {
        params,
        responseType: 'blob',
        headers: {
          Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel'
        }
      });
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Import data pegawai dari Excel
  import: async (file) => {
    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await api.post('/pegawai-extended/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Download template import Excel
  downloadTemplate: async () => {
    try {
      const response = await api.get('/pegawai-extended/template', {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.ms-excel'
        }
      });
      return response;
    } catch (error) {
      throw error.response?.data || error;
    }
  }
};

export default pegawaiExtendedService;

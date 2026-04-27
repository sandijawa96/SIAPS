import api from './api';
import { getServerDateString } from './serverClock';

const downloadBlob = (blobData, filename) => {
  const url = window.URL.createObjectURL(new Blob([blobData]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
};

const normalizeImportError = (error) => {
  if (error.response) {
    const { status, data } = error.response;
    if (status === 422) {
      const errorMsg = data.message || 'Data tidak valid';
      const summary = data.summary || {};
      const detailErrors = data.errors || [];

      let fullMessage = `${errorMsg}\n\n`;
      if (summary.total_processed === 0) {
        fullMessage += 'Tidak ada data yang dapat diproses. Pastikan format file sesuai template.\n';
      }
      if (Array.isArray(detailErrors) && detailErrors.length > 0) {
        fullMessage += '\nDetail error:\n';
        fullMessage += detailErrors.join('\n');
      }

      throw {
        message: fullMessage,
        response: error.response,
      };
    }

    if (status === 413) {
      throw {
        message: 'File terlalu besar. Maksimal ukuran file adalah 5MB',
        response: error.response,
      };
    }

    if (status === 500) {
      throw {
        message: data.message || 'Terjadi kesalahan server saat memproses file',
        response: error.response,
      };
    }

    throw {
      message: data.message || `Terjadi kesalahan (${status})`,
      response: error.response,
    };
  }

  if (error.request) {
    throw {
      message: 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.',
      response: null,
    };
  }

  if (error.code === 'ECONNABORTED') {
    throw {
      message: 'Waktu upload habis. File mungkin terlalu besar atau koneksi lambat.',
      response: null,
    };
  }

  throw {
    message: error.message || 'Terjadi kesalahan tidak terduga',
    response: null,
  };
};

export const kelasImportExportService = {
  downloadTemplate: async () => {
    try {
      const response = await api.get('/kelas/download-template', {
        responseType: 'blob',
      });
      downloadBlob(response.data, 'template-import-kelas.xlsx');
      return { success: true };
    } catch (error) {
      console.error('Error downloading class template:', error);
      throw error;
    }
  },

  downloadPromotionTemplate: async () => {
    try {
      const response = await api.get('/kelas/download-template-naik-kelas', {
        responseType: 'blob',
      });
      downloadBlob(response.data, 'template-import-siswa-baru-naik-kelas.xlsx');
      return { success: true };
    } catch (error) {
      console.error('Error downloading promotion template:', error);
      throw error;
    }
  },

  import: async (file) => {
    try {
      kelasImportExportService.validateFile(file);

      const formData = new FormData();
      formData.append('file', file);

      const response = await api.post('/kelas/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        timeout: 60000,
      });

      return response.data;
    } catch (error) {
      console.error('Error importing classes:', error);
      normalizeImportError(error);
      return null;
    }
  },

  importPromotion: async (file, options = {}) => {
    try {
      kelasImportExportService.validateFile(file);

      const formData = new FormData();
      formData.append('file', file);
      if (options.targetTahunAjaranId) {
        formData.append('target_tahun_ajaran_id', String(options.targetTahunAjaranId));
      }
      if (options.tanggalTransisi) {
        formData.append('tanggal_transisi', String(options.tanggalTransisi));
      }

      const response = await api.post('/kelas/import-naik-kelas', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        timeout: 60000,
      });

      return response.data;
    } catch (error) {
      console.error('Error importing promotion data:', error);
      normalizeImportError(error);
      return null;
    }
  },

  export: async (tahunAjaranId = null) => {
    try {
      const params = tahunAjaranId ? { tahun_ajaran_id: tahunAjaranId } : {};
      const response = await api.get('/kelas/export', {
        params,
        responseType: 'blob',
      });

      const tahunAjaranText = tahunAjaranId ? '-filtered' : '';
      const filename = `data-kelas${tahunAjaranText}-${getServerDateString()}.xlsx`;
      downloadBlob(response.data, filename);
      return { success: true };
    } catch (error) {
      console.error('Error exporting classes:', error);
      throw error;
    }
  },

  validateFile: (file) => {
    const allowedTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-excel',
    ];
    const maxSize = 5 * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
      throw new Error('File harus berformat Excel (.xlsx atau .xls)');
    }
    if (file.size > maxSize) {
      throw new Error('Ukuran file tidak boleh lebih dari 5MB');
    }
    return true;
  },
};

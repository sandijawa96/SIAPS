import api from './api';
import { getServerDateString } from './serverClock';

export const kelasImportExportService = {
  // Download template for importing classes
  downloadTemplate: async () => {
    try {
      const response = await api.get('/kelas/download-template', {
        responseType: 'blob'
      });
      
      // Create blob link to download
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'template-import-kelas.xlsx');
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      return { success: true };
    } catch (error) {
      console.error('Error downloading template:', error);
      throw error;
    }
  },

  // Import classes from Excel file
  import: async (file) => {
    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await kelasAPI.post('/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      return response.data;
    } catch (error) {
      console.error('Error importing classes:', error);
      throw error;
    }
  },

  // Export classes to Excel
  export: async (tahunAjaranId = null) => {
    try {
      const params = tahunAjaranId ? { tahun_ajaran_id: tahunAjaranId } : {};
      
      const response = await kelasAPI.get('/export', {
        params,
        responseType: 'blob'
      });

      // Generate filename
      const tahunAjaranText = tahunAjaranId ? '-filtered' : '';
      const filename = `data-kelas${tahunAjaranText}-${getServerDateString()}.xlsx`;
      
      // Create blob link to download
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      return { success: true };
    } catch (error) {
      console.error('Error exporting classes:', error);
      throw error;
    }
  },

  // Validate file before import
  validateFile: (file) => {
    const allowedTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-excel'
    ];
    
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!allowedTypes.includes(file.type)) {
      throw new Error('File harus berformat Excel (.xlsx atau .xls)');
    }
    
    if (file.size > maxSize) {
      throw new Error('Ukuran file tidak boleh lebih dari 5MB');
    }
    
    return true;
  }
};

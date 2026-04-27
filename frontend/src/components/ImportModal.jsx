import React, { useState, useEffect } from 'react';
import { X, Upload, FileText, AlertCircle, CheckCircle, Download, Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';
import pegawaiService from '../services/pegawaiService';
import siswaService from '../services/siswaService';

const ImportModal = ({ isOpen, onClose, onSuccess, userType, onImport }) => {
  const [selectedFile, setSelectedFile] = useState(null);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState(null);
  const [progress, setProgress] = useState(0);
  const [progressMessage, setProgressMessage] = useState('');
  const [detailedErrors, setDetailedErrors] = useState([]);
  const [importMode, setImportMode] = useState('auto'); // auto, create-only, update-only
  const [updateMode, setUpdateMode] = useState('partial'); // partial, full

  if (!isOpen) return null;

  const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
      // Validasi tipe file
      const allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
      ];
      
      if (!allowedTypes.includes(file.type)) {
        toast.error('Format file harus Excel (.xlsx, .xls)');
        return;
      }

      // Validasi ukuran file (maksimal 5MB)
      if (file.size > 5 * 1024 * 1024) {
        toast.error('Ukuran file maksimal 5MB');
        return;
      }

      setSelectedFile(file);
      setImportResult(null);
      setProgress(0);
    }
  };

  const pollProgress = async (jobId) => {
    try {
      // Use appropriate endpoint based on user type
      const endpoint = userType === 'pegawai' 
        ? `/api/pegawai/import-progress/${jobId}`
        : `/api/siswa/import-progress/${jobId}`;
        
      const response = await fetch(endpoint);
      const data = await response.json();
      
      if (data.success) {
        setProgress(data.data.progress || 0);
        
        if (data.data.status === 'completed' || data.data.status === 'failed' || data.data.status === 'error') {
          return true;
        }
        
        // Continue polling if not complete
        setTimeout(() => pollProgress(jobId), 1000);
      }
    } catch (error) {
      console.error('Error polling progress:', error);
      setProgress(0);
    }
  };

  const handleImport = async () => {
    if (!selectedFile) {
      toast.error('Pilih file terlebih dahulu');
      return;
    }

    setImporting(true);
    setProgress(0);
    setProgressMessage('Memulai proses import...');
    setDetailedErrors([]);
    
    try {
      // Simulate progress steps
      setProgress(10);
      setProgressMessage('Memvalidasi file...');
      
      // Create form data with file and import options
      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('importMode', importMode);
      formData.append('updateMode', updateMode);
      
      const result = await onImport(formData);
      
      setProgress(50);
      setProgressMessage('Memproses data...');
      
      // If backend supports job tracking
      if (result.success && result.job_id) {
        await pollProgress(result.job_id);
      } else {
        // Simulate completion
        setProgress(100);
        setProgressMessage('Selesai');
      }

      setImportResult(result);
      
      if (result.success) {
        const importedCount = result.data?.imported || result.imported || 0;
        toast.success(`Berhasil mengimpor ${importedCount} data ${userType}`);
        if (importedCount > 0) {
          onSuccess();
        }
      } else {
        // Handle detailed errors
        if (result.errors && Array.isArray(result.errors)) {
          setDetailedErrors(result.errors);
        }
        toast.error(result.message || 'Gagal mengimpor data');
      }
    } catch (error) {
      console.error('Import error:', error);
      setProgress(0);
      setProgressMessage('');
      
      let errorMessage = 'Gagal mengimpor data';
      let errors = [];
      
      if (error.response?.data) {
        const errorData = error.response.data;
        errorMessage = errorData.message || errorMessage;
        
        // Handle Laravel validation errors
        if (errorData.errors) {
          errors = Object.values(errorData.errors).flat();
        }
        
        // Handle custom error details
        if (errorData.details && Array.isArray(errorData.details)) {
          errors = errorData.details;
        }
      } else if (error.message) {
        errorMessage = error.message;
      }
      
      setDetailedErrors(errors);
      toast.error(errorMessage);
      
      setImportResult({
        success: false,
        message: errorMessage,
        errors: errors
      });
    } finally {
      setImporting(false);
      setTimeout(() => {
        setProgress(0);
        setProgressMessage('');
      }, 2000);
    }
  };

  const handleClose = () => {
    setSelectedFile(null);
    setImportResult(null);
    onClose();
  };

  const downloadTemplate = async () => {
    try {
      let response;
      if (userType === 'pegawai') {
        response = await pegawaiService.downloadTemplate();
      } else if (userType === 'siswa') {
        response = await siswaService.downloadTemplate();
      }

      // Create blob and download
      const blob = new Blob([response.data], { 
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `Template_Import_${userType === 'pegawai' ? 'Pegawai' : 'Siswa'}.xlsx`);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download template error:', error);
      toast.error('Gagal mendownload template');
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {/* Backdrop */}
        <div className="fixed inset-0 transition-opacity" aria-hidden="true">
          <div className="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>
        </div>
        
        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        {/* Modal */}
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4">
            {importing && (
              <div className="mt-2">
                <div className="w-full bg-blue-200 rounded-full h-2.5">
                  <div 
                    className="bg-white h-2.5 rounded-full transition-all duration-300" 
                    style={{ width: `${progress}%` }}
                  ></div>
                </div>
                <p className="text-xs text-blue-100 mt-1 text-center">{progress}% selesai</p>
              </div>
            )}
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                  <Upload className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">
                    Import Data {userType === 'pegawai' ? 'Pegawai' : 'Siswa'}
                  </h3>
                  <p className="text-blue-100 text-sm">
                    Upload file Excel untuk mengimpor data
                  </p>
                </div>
              </div>
              <button
                onClick={handleClose}
                className="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          {/* Content */}
          <div className="px-6 py-6">
            {/* Download Template Section */}
            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
              <div className="flex items-start">
                <FileText className="w-5 h-5 text-blue-600 mt-0.5 mr-3" />
                <div className="flex-1">
                  <h4 className="text-sm font-semibold text-blue-900 mb-2">
                    Download Template
                  </h4>
                  <p className="text-sm text-blue-700 mb-3">
                    Download template Excel untuk memastikan format data yang benar
                  </p>
                  <button
                    onClick={downloadTemplate}
                    className="inline-flex items-center px-3 py-2 border border-blue-300 rounded-lg text-sm font-medium text-blue-700 bg-white hover:bg-blue-50 transition-colors"
                  >
                    <Download className="w-4 h-4 mr-2" />
                    Download Template {userType === 'pegawai' ? 'Pegawai' : 'Siswa'}
                  </button>
                </div>
              </div>
            </div>

            {/* Format Requirements */}
            <div className="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-xl">
              <h4 className="text-sm font-semibold text-gray-900 mb-3">
                Persyaratan Format File:
              </h4>
              <div className="space-y-2 text-sm text-gray-600">
                <div className="flex items-center">
                  <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                  Format file: Excel (.xlsx, .xls)
                </div>
                <div className="flex items-center">
                  <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                  Ukuran maksimal: 5MB
                </div>
                <div className="flex items-center">
                  <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                  Baris pertama harus berisi header kolom
                </div>
                <div className="flex items-center">
                  <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                  Data dimulai dari baris kedua
                </div>
                <div className="flex items-center">
                  <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                  Template sudah terformat rapi dengan contoh data
                </div>
              </div>
            </div>

            {/* Specific Requirements */}
            <div className="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
              <h4 className="text-sm font-semibold text-yellow-900 mb-3">
                Keterangan Khusus untuk {userType === 'pegawai' ? 'Pegawai' : 'Siswa'}:
              </h4>
              <div className="space-y-2 text-sm text-yellow-800">
                {userType === 'pegawai' ? (
                  <>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>status_kepegawaian: Hanya boleh diisi "ASN" atau "Honorer"</span>
                    </div>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>nip: Wajib diisi untuk status ASN (18 digit angka)</span>
                    </div>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>is_active: Akan otomatis diset aktif untuk semua data</span>
                    </div>
                  </>
                ) : (
                  <>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>tanggal_lahir: Format YYYY-MM-DD (contoh: 2008-05-15)</span>
                    </div>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>jenis_kelamin: "L" untuk Laki-laki, "P" untuk Perempuan</span>
                    </div>
                    <div className="flex items-start">
                      <AlertCircle className="w-4 h-4 mt-0.5 mr-2 text-yellow-600" />
                      <span>username dan password akan otomatis dibuat dari NIS dan tanggal lahir</span>
                    </div>
                  </>
                )}
              </div>
            </div>

            {/* Import Mode Selection */}
            <div className="mb-6 p-4 bg-purple-50 border border-purple-200 rounded-xl">
              <h4 className="text-sm font-semibold text-purple-900 mb-3">
                Mode Import
              </h4>
              <div className="space-y-3">
                <div className="flex items-center">
                  <input
                    id="auto-mode"
                    name="import-mode"
                    type="radio"
                    value="auto"
                    checked={importMode === 'auto'}
                    onChange={(e) => setImportMode(e.target.value)}
                    className="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                  />
                  <label htmlFor="auto-mode" className="ml-3 block text-sm text-purple-800">
                    <span className="font-medium">Auto (Recommended)</span>
                    <span className="block text-xs text-purple-600">Update jika NIS sudah ada, buat baru jika belum ada</span>
                  </label>
                </div>
                <div className="flex items-center">
                  <input
                    id="create-only-mode"
                    name="import-mode"
                    type="radio"
                    value="create-only"
                    checked={importMode === 'create-only'}
                    onChange={(e) => setImportMode(e.target.value)}
                    className="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                  />
                  <label htmlFor="create-only-mode" className="ml-3 block text-sm text-purple-800">
                    <span className="font-medium">Create Only</span>
                    <span className="block text-xs text-purple-600">Hanya buat data baru, skip jika NIS sudah ada</span>
                  </label>
                </div>
                <div className="flex items-center">
                  <input
                    id="update-only-mode"
                    name="import-mode"
                    type="radio"
                    value="update-only"
                    checked={importMode === 'update-only'}
                    onChange={(e) => setImportMode(e.target.value)}
                    className="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                  />
                  <label htmlFor="update-only-mode" className="ml-3 block text-sm text-purple-800">
                    <span className="font-medium">Update Only</span>
                    <span className="block text-xs text-purple-600">Hanya update data yang sudah ada, skip jika NIS belum ada</span>
                  </label>
                </div>
              </div>
              
              {/* Update Mode Selection (only show when update is involved) */}
              {(importMode === 'auto' || importMode === 'update-only') && (
                <div className="mt-4 pt-4 border-t border-purple-200">
                  <h5 className="text-sm font-medium text-purple-900 mb-2">Mode Update</h5>
                  <div className="space-y-2">
                    <div className="flex items-center">
                      <input
                        id="partial-update"
                        name="update-mode"
                        type="radio"
                        value="partial"
                        checked={updateMode === 'partial'}
                        onChange={(e) => setUpdateMode(e.target.value)}
                        className="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                      />
                      <label htmlFor="partial-update" className="ml-3 block text-sm text-purple-800">
                        <span className="font-medium">Partial Update</span>
                        <span className="block text-xs text-purple-600">Update hanya field yang diisi di Excel</span>
                      </label>
                    </div>
                    <div className="flex items-center">
                      <input
                        id="full-update"
                        name="update-mode"
                        type="radio"
                        value="full"
                        checked={updateMode === 'full'}
                        onChange={(e) => setUpdateMode(e.target.value)}
                        className="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                      />
                      <label htmlFor="full-update" className="ml-3 block text-sm text-purple-800">
                        <span className="font-medium">Full Update</span>
                        <span className="block text-xs text-purple-600">Update semua field, kosongkan field yang tidak diisi</span>
                      </label>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* File Upload */}
            <div className="mb-6">
              <label className="block text-sm font-semibold text-gray-700 mb-2">
                Pilih File untuk Diimpor
              </label>
              <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-blue-400 transition-colors">
                <div className="space-y-1 text-center">
                  <Upload className="mx-auto h-12 w-12 text-gray-400" />
                  <div className="flex text-sm text-gray-600">
                    <label className="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                      <span>Upload file</span>
                      <input
                        type="file"
                        className="sr-only"
                        accept=".xlsx,.xls"
                        onChange={handleFileChange}
                      />
                    </label>
                    <p className="pl-1">atau drag and drop</p>
                  </div>
                  <p className="text-xs text-gray-500">
                    Excel (.xlsx, .xls) hingga 5MB
                  </p>
                </div>
              </div>
              
              {selectedFile && (
                <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                  <div className="flex items-center">
                    <FileText className="w-5 h-5 text-green-600 mr-2" />
                    <span className="text-sm text-green-800 font-medium">
                      {selectedFile.name}
                    </span>
                    <span className="text-sm text-green-600 ml-2">
                      ({(selectedFile.size / 1024 / 1024).toFixed(2)} MB)
                    </span>
                  </div>
                </div>
              )}
            </div>

            {/* Progress Bar */}
            {importing && (
              <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <div className="flex items-center mb-3">
                  <Loader2 className="w-5 h-5 text-blue-600 animate-spin mr-2" />
                  <span className="text-sm font-medium text-blue-900">Sedang Mengimpor Data</span>
                </div>
                <div className="flex justify-between mb-2">
                  <span className="text-sm font-medium text-gray-700">Progress</span>
                  <span className="text-sm font-medium text-gray-700">{progress}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-3">
                  <div 
                    className="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-500 ease-out"
                    style={{ width: `${progress}%` }}
                  ></div>
                </div>
                {progressMessage && (
                  <p className="text-sm text-blue-700 mt-2 flex items-center">
                    <span className="w-2 h-2 bg-blue-500 rounded-full mr-2 animate-pulse"></span>
                    {progressMessage}
                  </p>
                )}
              </div>
            )}

            {/* Import Result */}
            {importResult && (
              <div className={`mb-6 p-4 rounded-xl border ${
                importResult.success 
                  ? 'bg-green-50 border-green-200' 
                  : 'bg-red-50 border-red-200'
              }`}>
                <div className="flex items-start">
                  {importResult.success ? (
                    <CheckCircle className="w-5 h-5 text-green-600 mt-0.5 mr-3" />
                  ) : (
                    <AlertCircle className="w-5 h-5 text-red-600 mt-0.5 mr-3" />
                  )}
                  <div className="flex-1">
                    <h4 className={`text-sm font-semibold mb-2 ${
                      importResult.success ? 'text-green-900' : 'text-red-900'
                    }`}>
                      {importResult.success ? 'Import Berhasil!' : 'Import Gagal!'}
                    </h4>
                    <p className={`text-sm ${
                      importResult.success ? 'text-green-800' : 'text-red-800'
                    }`}>
                      {importResult.message}
                    </p>
                    {importResult.details && (
                      <div className="mt-2 text-sm">
                        <p>Berhasil: {importResult.details.success || 0}</p>
                        <p>Gagal: {importResult.details.failed || 0}</p>
                        {importResult.details.errors && importResult.details.errors.length > 0 && (
                          <div className="mt-2">
                            <p className="font-medium">Error:</p>
                            <ul className="list-disc list-inside">
                              {importResult.details.errors.slice(0, 5).map((error, index) => (
                                <li key={index}>{error}</li>
                              ))}
                              {importResult.details.errors.length > 5 && (
                                <li>... dan {importResult.details.errors.length - 5} error lainnya</li>
                              )}
                            </ul>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              onClick={handleClose}
              className="px-6 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Tutup
            </button>
            <button
              type="button"
              onClick={handleImport}
              disabled={!selectedFile || importing}
              className="px-6 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {importing ? 'Mengimpor...' : 'Import Data'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ImportModal;

import React, { useState } from 'react';
import { X, Download, AlertCircle, CheckCircle } from 'lucide-react';
import toast from 'react-hot-toast';
import { getServerDateString, getServerTimeString } from '../services/serverClock';

const ExportModal = ({ isOpen, onClose, onExport, userType }) => {
  const [exporting, setExporting] = useState(false);
  const [progress, setProgress] = useState(0);
  const [exportResult, setExportResult] = useState(null);

  if (!isOpen) return null;

  const handleExport = async () => {
    setExporting(true);
    setProgress(0);
    
    try {
      // Start progress simulation
      const progressInterval = setInterval(() => {
        setProgress(prev => {
          if (prev >= 90) {
            clearInterval(progressInterval);
            return 90;
          }
          return prev + 10;
        });
      }, 500);

      const response = await onExport();
      
      // Clear interval and set to 100%
      clearInterval(progressInterval);
      setProgress(100);

      // Create blob and download
      const blob = new Blob([response.data], { 
        type: 'application/vnd.ms-excel'
      });
      
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      
      // Generate filename with server date and time
      const date = getServerDateString() || 'unknown-date';
      const time = (getServerTimeString() || '00:00:00').replace(/:/g, '-');
      const filename = `Data_${userType}_${date}_${time}.xlsx`;
      link.setAttribute('download', filename);
      
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      setExportResult({
        success: true,
        message: `Data ${userType} berhasil diekspor`
      });

      // Reset after 2 seconds
      setTimeout(() => {
        onClose();
        setExporting(false);
        setProgress(0);
        setExportResult(null);
      }, 2000);

    } catch (error) {
      console.error('Export error:', error);
      setExportResult({
        success: false,
        message: error.message || `Gagal mengekspor data ${userType}`
      });
      setExporting(false);
      setProgress(0);
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
        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          {/* Header */}
          <div className="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="p-2 bg-white bg-opacity-20 rounded-lg mr-3">
                  <Download className="w-6 h-6 text-white" />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-white">
                    Export Data {userType}
                  </h3>
                  <p className="text-blue-100 text-sm">
                    Download data dalam format Excel
                  </p>
                </div>
              </div>
              <button
                onClick={onClose}
                className="p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
                disabled={exporting}
              >
                <X className="w-5 h-5 text-white" />
              </button>
            </div>
          </div>

          {/* Content */}
          <div className="px-6 py-6">
            {/* Progress Bar */}
            {exporting && (
              <div className="mb-6">
                <div className="flex justify-between mb-2">
                  <span className="text-sm font-medium text-gray-700">Progress</span>
                  <span className="text-sm font-medium text-gray-700">{progress}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2.5">
                  <div 
                    className="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                    style={{ width: `${progress}%` }}
                  ></div>
                </div>
              </div>
            )}

            {/* Export Result */}
            {exportResult && (
              <div className={`mb-6 p-4 rounded-xl border ${
                exportResult.success 
                  ? 'bg-green-50 border-green-200' 
                  : 'bg-red-50 border-red-200'
              }`}>
                <div className="flex items-start">
                  {exportResult.success ? (
                    <CheckCircle className="w-5 h-5 text-green-600 mt-0.5 mr-3" />
                  ) : (
                    <AlertCircle className="w-5 h-5 text-red-600 mt-0.5 mr-3" />
                  )}
                  <div className="flex-1">
                    <h4 className={`text-sm font-semibold mb-2 ${
                      exportResult.success ? 'text-green-900' : 'text-red-900'
                    }`}>
                      {exportResult.success ? 'Export Berhasil!' : 'Export Gagal!'}
                    </h4>
                    <p className={`text-sm ${
                      exportResult.success ? 'text-green-800' : 'text-red-800'
                    }`}>
                      {exportResult.message}
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Export Information */}
            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
              <div className="flex items-start">
                <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-semibold text-blue-900 mb-2">
                    Informasi Export
                  </h4>
                  <ul className="space-y-2 text-sm text-blue-800">
                    <li>• File akan diexport dalam format Excel (.xlsx)</li>
                    <li>• Semua data {userType} aktif akan diexport</li>
                    <li>• Proses export mungkin membutuhkan beberapa saat</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              onClick={onClose}
              disabled={exporting}
              className="px-4 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Tutup
            </button>
            <button
              type="button"
              onClick={handleExport}
              disabled={exporting}
              className="px-4 py-2 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {exporting ? 'Mengexport...' : 'Export Data'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ExportModal;

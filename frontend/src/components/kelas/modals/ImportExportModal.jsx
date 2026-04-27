import React, { useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Chip,
  Dialog,
  DialogContent,
  DialogTitle,
  Divider,
  IconButton,
  LinearProgress,
  Slide,
  Tab,
  Tabs,
  Typography,
} from '@mui/material';
import {
  AlertTriangle,
  CheckCircle2,
  Database,
  Download,
  FileText,
  FileUp,
  Upload,
  UploadCloud,
  X,
} from 'lucide-react';
import toast from 'react-hot-toast';
import { kelasImportExportService } from '../../../services/kelasImportExportServiceFixed';

const Transition = React.forwardRef(function Transition(props, ref) {
  return <Slide direction="up" ref={ref} {...props} />;
});

const emptySummary = {
  total_processed: 0,
  imported: 0,
  promoted: 0,
  skipped: 0,
  errors: [],
};

const ImportResultCard = ({ result, mode }) => {
  if (!result) {
    return null;
  }

  const summary = result.summary || emptySummary;
  const successLabel = mode === 'promotion' ? 'Penempatan Siswa Berhasil' : 'Import Berhasil';
  const warningLabel =
    mode === 'promotion' ? 'Import Siswa Baru/Naik Kelas Selesai dengan Peringatan' : 'Import Selesai dengan Peringatan';
  const successCount = mode === 'promotion'
    ? Number(summary.imported || 0)
    : Number(summary.imported || 0);
  const promotedCount = Number(summary.promoted || 0);
  const assignedNewCount = Number(summary.assigned_new || 0);

  return (
    <Card className={`mt-4 border-0 ${result.success ? 'bg-green-50' : 'bg-yellow-50'}`}>
      <CardContent className="p-4">
        <Box className="flex items-start space-x-3">
          {result.success ? (
            <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" />
          ) : (
            <AlertTriangle className="w-5 h-5 text-yellow-600 mt-0.5" />
          )}
          <Box className="flex-1">
            <Typography variant="body2" className="font-medium mb-2">
              {result.success ? successLabel : warningLabel}
            </Typography>

            <Box className="flex flex-wrap gap-2 mb-3">
              <Chip label={`${Number(summary.total_processed || 0)} Total`} size="small" variant="outlined" />
              <Chip
                label={`${successCount} ${mode === 'promotion' ? 'Diproses' : 'Berhasil'}`}
                size="small"
                color="success"
                variant="outlined"
              />
              {mode === 'promotion' && (
                <Chip label={`${assignedNewCount} Siswa Baru`} size="small" color="info" variant="outlined" />
              )}
              {mode === 'promotion' && (
                <Chip label={`${promotedCount} Naik Kelas`} size="small" color="primary" variant="outlined" />
              )}
              <Chip label={`${Number(summary.skipped || 0)} Dilewati`} size="small" color="warning" variant="outlined" />
            </Box>

            {Array.isArray(summary.errors) && summary.errors.length > 0 && (
              <Box className="mt-3">
                <Typography variant="caption" className="font-medium text-red-700 block mb-1">
                  Error yang ditemukan:
                </Typography>
                <Box className="space-y-1">
                  {summary.errors.slice(0, 3).map((error, index) => (
                    <Typography key={index} variant="caption" className="text-red-600 block">
                      - {error}
                    </Typography>
                  ))}
                  {summary.errors.length > 3 && (
                    <Typography variant="caption" className="text-red-600 block">
                      - ... dan {summary.errors.length - 3} error lainnya
                    </Typography>
                  )}
                </Box>
              </Box>
            )}
          </Box>
        </Box>
      </CardContent>
    </Card>
  );
};

const ImportExportModal = ({ isOpen, onClose, onSuccess, activeTahunAjaran }) => {
  const [activeTab, setActiveTab] = useState('kelas');

  const [kelasFile, setKelasFile] = useState(null);
  const [promotionFile, setPromotionFile] = useState(null);

  const [importingKelas, setImportingKelas] = useState(false);
  const [importingPromotion, setImportingPromotion] = useState(false);

  const [kelasImportResult, setKelasImportResult] = useState(null);
  const [promotionImportResult, setPromotionImportResult] = useState(null);

  const handleSelectFile = (event, mode) => {
    const selectedFile = event.target.files?.[0];
    if (!selectedFile) {
      return;
    }

    try {
      kelasImportExportService.validateFile(selectedFile);
      if (mode === 'promotion') {
        setPromotionFile(selectedFile);
        setPromotionImportResult(null);
      } else {
        setKelasFile(selectedFile);
        setKelasImportResult(null);
      }
    } catch (error) {
      toast.error(error.message);
    }
  };

  const handleDownloadTemplate = async () => {
    try {
      await kelasImportExportService.downloadTemplate();
      toast.success('Template import kelas berhasil diunduh');
    } catch (error) {
      toast.error('Gagal mengunduh template import kelas');
    }
  };

  const handleDownloadPromotionTemplate = async () => {
    try {
      await kelasImportExportService.downloadPromotionTemplate();
      toast.success('Template import siswa baru/naik kelas berhasil diunduh');
    } catch (error) {
      toast.error('Gagal mengunduh template import siswa baru/naik kelas');
    }
  };

  const normalizeErrorResult = (error) => {
    if (error?.response?.data) {
      return error.response.data;
    }

    if (error?.message) {
      return {
        success: false,
        message: error.message,
        summary: {
          ...emptySummary,
          errors: [error.message],
        },
      };
    }

    return {
      success: false,
      message: 'Terjadi kesalahan tidak terduga',
      summary: {
        ...emptySummary,
        errors: ['Terjadi kesalahan tidak terduga'],
      },
    };
  };

  const handleImportKelas = async () => {
    if (!kelasFile) {
      toast.error('Pilih file terlebih dahulu');
      return;
    }

    try {
      setImportingKelas(true);
      const result = await kelasImportExportService.import(kelasFile);
      setKelasImportResult(result);

      if (result.success) {
        toast.success(result.message || 'Data kelas berhasil diimpor');
        onSuccess?.();
      } else {
        toast.error(result.message || 'Import kelas selesai dengan peringatan');
      }
    } catch (error) {
      const normalized = normalizeErrorResult(error);
      setKelasImportResult(normalized);
      toast.error(normalized.message || 'Gagal mengimpor data kelas');
    } finally {
      setImportingKelas(false);
    }
  };

  const handleImportPromotion = async () => {
    if (!promotionFile) {
      toast.error('Pilih file terlebih dahulu');
      return;
    }

    try {
      setImportingPromotion(true);
      const result = await kelasImportExportService.importPromotion(promotionFile, {
        targetTahunAjaranId: activeTahunAjaran?.id || null,
      });
      setPromotionImportResult(result);

      if (result.success) {
        toast.success(result.message || 'Import siswa baru/naik kelas berhasil');
        onSuccess?.();
      } else {
        toast.error(result.message || 'Import siswa baru/naik kelas selesai dengan peringatan');
      }
    } catch (error) {
      const normalized = normalizeErrorResult(error);
      setPromotionImportResult(normalized);
      toast.error(normalized.message || 'Gagal mengimpor data siswa baru/naik kelas');
    } finally {
      setImportingPromotion(false);
    }
  };

  const handleExport = async () => {
    try {
      await kelasImportExportService.export(activeTahunAjaran?.id);
      toast.success('Data kelas berhasil diekspor');
    } catch (error) {
      toast.error('Gagal mengekspor data kelas');
    }
  };

  const renderUploadCard = ({
    title,
    subtitle,
    onTemplateDownload,
    file,
    onFileChange,
    fileInputId,
    buttonLabel,
    onImport,
    importing,
    result,
    mode = 'kelas',
    infoBlock = null,
  }) => (
    <Card className="border border-gray-200 shadow-sm">
      <CardContent className="p-6">
        <Box className="flex items-center space-x-2 mb-4">
          <UploadCloud className="w-5 h-5 text-blue-600" />
          <Typography variant="h6" className="font-semibold text-gray-900">
            {title}
          </Typography>
        </Box>

        <Box className="space-y-4">
          <Box className="bg-blue-50 rounded-lg p-4">
            <Box className="flex items-start space-x-3">
              <FileText className="w-5 h-5 text-blue-600 mt-0.5" />
              <Box className="flex-1">
                <Typography variant="body2" className="font-medium text-blue-900 mb-1">
                  Download Template Excel
                </Typography>
                <Typography variant="caption" className="text-blue-700 mb-3 block">
                  {subtitle}
                </Typography>
                <button
                  onClick={onTemplateDownload}
                  className="px-4 py-2 text-sm font-medium text-blue-700 bg-white border border-blue-300 rounded-lg hover:bg-blue-50 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 flex items-center space-x-2"
                >
                  <Download className="w-4 h-4" />
                  <span>Download Template</span>
                </button>
              </Box>
            </Box>
          </Box>

          {infoBlock}

          <Box className="space-y-2">
            <Typography variant="body2" className="font-medium text-gray-700">
              Pilih File Excel
            </Typography>
            <Box className="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-gray-400 transition-colors">
              <input type="file" onChange={onFileChange} accept=".xlsx,.xls" className="hidden" id={fileInputId} />
              <label htmlFor={fileInputId} className="flex flex-col items-center justify-center cursor-pointer">
                <Upload className="w-8 h-8 text-gray-400 mb-2" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  {file ? file.name : 'Klik untuk pilih file Excel'}
                </Typography>
                <Typography variant="caption" className="text-gray-500 mt-1">
                  Format: .xlsx, .xls (Maksimal 5MB)
                </Typography>
              </label>
            </Box>
          </Box>

          <button
            onClick={onImport}
            disabled={!file || importing}
            className={`w-full py-3 px-4 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-lg transition-all duration-200 flex items-center justify-center space-x-2 ${
              !file || importing
                ? 'bg-gray-400 text-white cursor-not-allowed'
                : 'bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 focus:ring-blue-500 hover:shadow-xl transform hover:-translate-y-0.5'
            }`}
          >
            {importing ? (
              <>
                <Box className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></Box>
                <span>Memproses...</span>
              </>
            ) : (
              <>
                <FileUp className="w-4 h-4" />
                <span>{buttonLabel}</span>
              </>
            )}
          </button>

          {importing && (
            <Box className="space-y-2">
              <LinearProgress
                sx={{
                  borderRadius: 1,
                  height: 6,
                  backgroundColor: 'rgba(59, 130, 246, 0.1)',
                  '& .MuiLinearProgress-bar': {
                    backgroundColor: '#3B82F6',
                  },
                }}
              />
              <Typography variant="caption" className="text-gray-600 text-center block">
                Sedang memproses file, mohon tunggu...
              </Typography>
            </Box>
          )}
        </Box>

        <ImportResultCard result={result} mode={mode} />
      </CardContent>
    </Card>
  );

  return (
    <Dialog
      open={isOpen}
      onClose={onClose}
      maxWidth="md"
      fullWidth
      TransitionComponent={Transition}
      PaperProps={{
        sx: {
          borderRadius: 3,
          boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
        },
      }}
    >
      <DialogTitle sx={{ p: 0 }}>
        <Box className="flex items-center justify-between p-6 pb-2">
          <Box className="flex items-center space-x-3">
            <Box className="p-2 bg-purple-100 rounded-lg">
              <Database className="w-6 h-6 text-purple-600" />
            </Box>
            <Box>
              <Typography variant="h6" className="font-semibold text-gray-900">
                Import/Export Data Kelas
              </Typography>
              <Typography variant="body2" className="text-gray-500">
                Import kelas baru atau naik kelas massal dalam satu modal
              </Typography>
            </Box>
          </Box>
          <IconButton onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors" size="small">
            <X className="w-5 h-5" />
          </IconButton>
        </Box>
        <Divider />
      </DialogTitle>

      <DialogContent sx={{ p: 0 }}>
        <Box className="p-6 space-y-6">
          <Card className="border border-gray-200 shadow-sm">
            <CardContent className="p-2">
              <Tabs
                value={activeTab}
                onChange={(_, value) => setActiveTab(value)}
                variant="fullWidth"
                sx={{
                  '& .MuiTab-root': { textTransform: 'none', fontWeight: 600 },
                }}
              >
                <Tab value="kelas" label="Import Kelas" />
                <Tab value="promotion" label="Import Siswa Baru/Naik Kelas" />
              </Tabs>
            </CardContent>
          </Card>

          {activeTab === 'kelas' &&
            renderUploadCard({
              title: 'Import Data Kelas',
              subtitle: 'Gunakan template ini untuk memastikan format data kelas benar',
              onTemplateDownload: handleDownloadTemplate,
              file: kelasFile,
              onFileChange: (event) => handleSelectFile(event, 'kelas'),
              fileInputId: 'kelas-import-file',
              buttonLabel: 'Import Data Kelas',
              onImport: handleImportKelas,
              importing: importingKelas,
              result: kelasImportResult,
              mode: 'kelas',
            })}

          {activeTab === 'promotion' &&
            renderUploadCard({
              title: 'Import Siswa Baru / Naik Kelas',
              subtitle: 'Template berisi kolom NIS, Nama, Kelas, dan Keterangan (Siswa Baru/Naik Kelas)',
              onTemplateDownload: handleDownloadPromotionTemplate,
              file: promotionFile,
              onFileChange: (event) => handleSelectFile(event, 'promotion'),
              fileInputId: 'kelas-promotion-import-file',
              buttonLabel: 'Import Penempatan Siswa',
              onImport: handleImportPromotion,
              importing: importingPromotion,
              result: promotionImportResult,
              mode: 'promotion',
              infoBlock: (
                <Box className="bg-amber-50 rounded-lg p-4 space-y-2">
                  <Typography variant="body2" className="font-medium text-amber-900">
                    Format Kolom
                  </Typography>
                  <Box className="flex flex-wrap gap-2">
                    <Chip size="small" label="NIS (wajib)" color="warning" variant="outlined" />
                    <Chip size="small" label="Nama (penanda)" color="warning" variant="outlined" />
                    <Chip size="small" label="Kelas (wajib)" color="warning" variant="outlined" />
                    <Chip size="small" label="Keterangan (wajib)" color="warning" variant="outlined" />
                  </Box>
                  <Typography variant="caption" className="text-amber-800 block">
                    Validasi sistem menggunakan NIS, Kelas, dan Keterangan. Nama hanya sebagai penanda saat pengecekan file.
                  </Typography>
                  <Typography variant="caption" className="text-amber-800 block">
                    Keterangan: <b>Siswa Baru</b> untuk siswa tanpa kelas aktif, <b>Naik Kelas</b> untuk siswa dengan kelas aktif.
                  </Typography>
                  <Typography variant="caption" className="text-amber-800 block">
                    {activeTahunAjaran?.nama
                      ? `Konteks tahun ajaran aktif: ${activeTahunAjaran.nama}`
                      : 'Jika nama kelas ganda antar tahun ajaran dan tidak ada konteks tahun ajaran aktif, baris akan ditolak sebagai ambigu.'}
                  </Typography>
                </Box>
              ),
            })}

          <Card className="border border-gray-200 shadow-sm">
            <CardContent className="p-6">
              <Box className="flex items-center space-x-2 mb-4">
                <Download className="w-5 h-5 text-green-600" />
                <Typography variant="h6" className="font-semibold text-gray-900">
                  Export Data Kelas
                </Typography>
              </Box>

              <Box className="bg-green-50 rounded-lg p-4">
                <Box className="flex items-start space-x-3">
                  <Database className="w-5 h-5 text-green-600 mt-0.5" />
                  <Box className="flex-1">
                    <Typography variant="body2" className="font-medium text-green-900 mb-1">
                      Export ke File Excel
                    </Typography>
                    <Typography variant="caption" className="text-green-700 mb-3 block">
                      {activeTahunAjaran
                        ? `Export data kelas untuk tahun ajaran ${activeTahunAjaran.nama}`
                        : 'Export semua data kelas yang tersedia'}
                    </Typography>
                    <button
                      onClick={handleExport}
                      className="px-4 py-2 text-sm font-medium text-green-700 bg-white border border-green-300 rounded-lg hover:bg-green-50 hover:border-green-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 flex items-center space-x-2"
                    >
                      <Download className="w-4 h-4" />
                      <span>Export Data Kelas</span>
                    </button>
                  </Box>
                </Box>
              </Box>
            </CardContent>
          </Card>

          <Box className="flex justify-end pt-4 border-t border-gray-100">
            <button
              onClick={onClose}
              className="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200"
            >
              Tutup
            </button>
          </Box>
        </Box>
      </DialogContent>
    </Dialog>
  );
};

export default ImportExportModal;

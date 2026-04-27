import React from 'react';
import { 
  Dialog, 
  DialogContent, 
  DialogTitle, 
  TextField, 
  MenuItem,
  Slide,
  IconButton,
  Divider,
  Box,
  Typography
} from '@mui/material';
import { X, School, Users, BookOpen, FileText } from 'lucide-react';

const Transition = React.forwardRef(function Transition(props, ref) {
  return <Slide direction="up" ref={ref} {...props} />;
});

const KelasFormModal = ({
  isOpen,
  onClose,
  onSubmit,
  selectedItem,
  tingkatList,
  pegawaiList,
  activeTahunAjaran,
  getAvailablePegawai
}) => {
  const availablePegawai = getAvailablePegawai(selectedItem?.wali_kelas_id);

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
        }
      }}
    >
      <DialogTitle sx={{ p: 0 }}>
        <Box className="flex items-center justify-between p-6 pb-2">
          <Box className="flex items-center space-x-3">
            <Box className="p-2 bg-blue-100 rounded-lg">
              <School className="w-6 h-6 text-blue-600" />
            </Box>
            <Box>
              <Typography variant="h6" className="font-semibold text-gray-900">
                {selectedItem ? 'Edit Kelas' : 'Tambah Kelas Baru'}
              </Typography>
              <Typography variant="body2" className="text-gray-500">
                {selectedItem ? 'Perbarui informasi kelas' : 'Buat kelas baru untuk tahun ajaran'}
              </Typography>
            </Box>
          </Box>
          <IconButton 
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
            size="small"
          >
            <X className="w-5 h-5" />
          </IconButton>
        </Box>
        <Divider />
      </DialogTitle>
      
      <DialogContent sx={{ p: 0 }}>
        <Box className="p-6">
          <form onSubmit={onSubmit} className="space-y-6">
            {/* Nama Kelas */}
            <Box className="space-y-2">
              <Box className="flex items-center space-x-2 mb-2">
                <BookOpen className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Informasi Dasar
                </Typography>
              </Box>
              <TextField
                fullWidth
                label="Nama Kelas"
                name="namaKelas"
                defaultValue={selectedItem?.namaKelas || ''}
                required
                variant="outlined"
                placeholder="Contoh: XII IPA 1"
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    },
                    '&.Mui-focused': {
                      boxShadow: '0 4px 12px rgba(59, 130, 246, 0.15)',
                    }
                  }
                }}
              />
            </Box>

            {/* Tingkat dan Kapasitas */}
            <Box className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <TextField
                fullWidth
                select
                label="Tingkat"
                name="tingkat"
                defaultValue={selectedItem?.tingkat_id || ''}
                required
                variant="outlined"
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    }
                  }
                }}
              >
                {tingkatList.map((tingkat) => (
                  <MenuItem key={tingkat.id} value={tingkat.id}>
                    <Box className="flex items-center space-x-2">
                      <Box className="w-2 h-2 bg-blue-500 rounded-full"></Box>
                      <span>{tingkat.nama}</span>
                    </Box>
                  </MenuItem>
                ))}
              </TextField>

              <TextField
                fullWidth
                type="number"
                label="Kapasitas Siswa"
                name="kapasitas"
                defaultValue={selectedItem?.kapasitas || ''}
                required
                variant="outlined"
                placeholder="30"
                inputProps={{ min: 1, max: 50 }}
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    }
                  }
                }}
              />
            </Box>

            {/* Wali Kelas */}
            <Box className="space-y-2">
              <Box className="flex items-center space-x-2 mb-2">
                <Users className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Penugasan Wali Kelas
                </Typography>
              </Box>
              <TextField
                fullWidth
                select
                label="Wali Kelas"
                name="wali_kelas_id"
                defaultValue={selectedItem?.wali_kelas_id || ''}
                variant="outlined"
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    }
                  }
                }}
              >
                <MenuItem value="">
                  <Box className="flex items-center space-x-2 text-gray-500">
                    <Users className="w-4 h-4" />
                    <span>Pilih Wali Kelas (Opsional)</span>
                  </Box>
                </MenuItem>
                {availablePegawai.map((pegawai) => (
                  <MenuItem key={pegawai.id} value={pegawai.id}>
                    <Box className="flex items-center space-x-2">
                      <Box className="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                        <span className="text-white text-xs font-medium">
                          {pegawai.nama?.charAt(0)?.toUpperCase()}
                        </span>
                      </Box>
                      <Box>
                        <Typography variant="body2" className="font-medium">
                          {pegawai.nama}
                        </Typography>
                        <Typography variant="caption" className="text-gray-500">
                          {pegawai.nip || 'NIP tidak tersedia'}
                        </Typography>
                      </Box>
                    </Box>
                  </MenuItem>
                ))}
              </TextField>
            </Box>

            {/* Keterangan */}
            <Box className="space-y-2">
              <Box className="flex items-center space-x-2 mb-2">
                <FileText className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Keterangan Tambahan
                </Typography>
              </Box>
              <TextField
                fullWidth
                multiline
                rows={3}
                label="Keterangan"
                name="keterangan"
                defaultValue={selectedItem?.keterangan || ''}
                variant="outlined"
                placeholder="Tambahkan catatan atau keterangan khusus untuk kelas ini..."
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    }
                  }
                }}
              />
            </Box>

            {/* Action Buttons */}
            <Box className="flex justify-end space-x-3 pt-4 border-t border-gray-100">
              <button
                type="button"
                onClick={onClose}
                className="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200"
              >
                Batal
              </button>
              <button
                type="submit"
                className="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
              >
                {selectedItem ? 'Simpan Perubahan' : 'Tambah Kelas'}
              </button>
            </Box>
          </form>
        </Box>
      </DialogContent>
    </Dialog>
  );
};

export default KelasFormModal;

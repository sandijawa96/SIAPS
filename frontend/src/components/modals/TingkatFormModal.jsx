import React from 'react';
import { 
  Dialog, 
  DialogContent, 
  DialogTitle, 
  TextField,
  Slide,
  IconButton,
  Divider,
  Box,
  Typography
} from '@mui/material';
import { X, Layers, Hash, FileText } from 'lucide-react';

const Transition = React.forwardRef(function Transition(props, ref) {
  return <Slide direction="up" ref={ref} {...props} />;
});

const TingkatFormModal = ({
  isOpen,
  onClose,
  onSubmit,
  selectedItem
}) => {
  return (
    <Dialog 
      open={isOpen} 
      onClose={onClose} 
      maxWidth="sm" 
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
            <Box className="p-2 bg-purple-100 rounded-lg">
              <Layers className="w-6 h-6 text-purple-600" />
            </Box>
            <Box>
              <Typography variant="h6" className="font-semibold text-gray-900">
                {selectedItem ? 'Edit Tingkat' : 'Tambah Tingkat Baru'}
              </Typography>
              <Typography variant="body2" className="text-gray-500">
                {selectedItem ? 'Perbarui informasi tingkat' : 'Buat tingkat baru untuk sekolah'}
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
            {/* Nama dan Kode Tingkat */}
            <Box className="space-y-4">
              <Box className="flex items-center space-x-2 mb-2">
                <Layers className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Informasi Tingkat
                </Typography>
              </Box>

              <TextField
                fullWidth
                label="Nama Tingkat"
                name="nama"
                defaultValue={selectedItem?.nama || ''}
                required
                variant="outlined"
                placeholder="Contoh: Kelas X"
                sx={{
                  '& .MuiOutlinedInput-root': {
                    borderRadius: 2,
                    transition: 'all 0.2s ease-in-out',
                    '&:hover': {
                      boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
                    },
                    '&.Mui-focused': {
                      boxShadow: '0 4px 12px rgba(124, 58, 237, 0.15)',
                    }
                  }
                }}
              />

              <Box className="flex items-center space-x-2 mt-4 mb-2">
                <Hash className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Kode Tingkat
                </Typography>
              </Box>

              <TextField
                fullWidth
                label="Kode Tingkat"
                name="kode"
                defaultValue={selectedItem?.kode || ''}
                required
                variant="outlined"
                placeholder="Contoh: X atau 10"
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

            {/* Deskripsi */}
            <Box className="space-y-2">
              <Box className="flex items-center space-x-2 mb-2">
                <FileText className="w-4 h-4 text-gray-500" />
                <Typography variant="body2" className="font-medium text-gray-700">
                  Deskripsi Tingkat
                </Typography>
              </Box>
              <TextField
                fullWidth
                multiline
                rows={3}
                label="Deskripsi"
                name="deskripsi"
                defaultValue={selectedItem?.deskripsi || ''}
                variant="outlined"
                placeholder="Tambahkan deskripsi atau keterangan untuk tingkat ini..."
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
                className="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-purple-700 rounded-lg hover:from-purple-700 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
              >
                {selectedItem ? 'Simpan Perubahan' : 'Tambah Tingkat'}
              </button>
            </Box>
          </form>
        </Box>
      </DialogContent>
    </Dialog>
  );
};

export default TingkatFormModal;

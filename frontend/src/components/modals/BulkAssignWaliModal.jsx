import React, { useState } from 'react';
import { 
  Dialog, 
  DialogContent, 
  DialogTitle, 
  TextField, 
  MenuItem, 
  Checkbox, 
  FormControlLabel,
  Slide,
  IconButton,
  Divider,
  Box,
  Typography,
  Chip,
  Card,
  CardContent,
  Collapse
} from '@mui/material';
import { X, Users, UserCheck, School, CheckCircle2, ChevronDown, ChevronUp } from 'lucide-react';

const Transition = React.forwardRef(function Transition(props, ref) {
  return <Slide direction="up" ref={ref} {...props} />;
});

const BulkAssignWaliModal = ({
  isOpen,
  onClose,
  onSubmit,
  kelasList,
  selectedClasses,
  setSelectedClasses,
  bulkWaliAssignments,
  setBulkWaliAssignments,
  getAvailablePegawai,
  tingkatList,
  loading
}) => {
  // Initialize all tingkat as collapsed (closed)
  const [expandedTingkat, setExpandedTingkat] = useState(() => {
    return tingkatList.reduce((acc, tingkat) => {
      acc[tingkat.id] = false;
      return acc;
    }, {});
  });

  const handleClassSelection = (kelasId) => {
    if (selectedClasses.includes(kelasId)) {
      setSelectedClasses(selectedClasses.filter(id => id !== kelasId));
      const newAssignments = { ...bulkWaliAssignments };
      delete newAssignments[kelasId];
      setBulkWaliAssignments(newAssignments);
    } else {
      setSelectedClasses([...selectedClasses, kelasId]);
    }
  };

  const handleWaliAssignment = (kelasId, waliId) => {
    setBulkWaliAssignments({
      ...bulkWaliAssignments,
      [kelasId]: waliId
    });
  };

  const toggleTingkat = (tingkatId) => {
    setExpandedTingkat(prev => ({
      ...prev,
      [tingkatId]: !prev[tingkatId]
    }));
  };

  const groupedKelas = tingkatList.reduce((acc, tingkat) => {
    acc[tingkat.id] = kelasList.filter(kelas => kelas.tingkat_id === tingkat.id);
    return acc;
  }, {});

  const completedAssignments = selectedClasses.filter(id => bulkWaliAssignments[id]).length;

  // Calculate statistics for each tingkat
  const getTingkatStats = (tingkatId) => {
    const kelasInTingkat = groupedKelas[tingkatId] || [];
    const sudahAda = kelasInTingkat.filter(kelas => kelas.waliKelas && kelas.waliKelas !== 'Belum ditentukan').length;
    const belumAda = kelasInTingkat.length - sudahAda;
    return { sudahAda, belumAda, total: kelasInTingkat.length };
  };

  return (
    <Dialog 
      open={isOpen} 
      onClose={onClose} 
      maxWidth="lg" 
      fullWidth
      TransitionComponent={Transition}
      PaperProps={{
        sx: {
          borderRadius: 3,
          boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
          maxHeight: '90vh'
        }
      }}
    >
      <DialogTitle sx={{ p: 0 }}>
        <Box className="flex items-center justify-between p-6 pb-2">
          <Box className="flex items-center space-x-3">
            <Box className="p-2 bg-green-100 rounded-lg">
              <UserCheck className="w-6 h-6 text-green-600" />
            </Box>
            <Box>
              <Typography variant="h6" className="font-semibold text-gray-900">
                Tugaskan Wali Kelas Secara Massal
              </Typography>
              <Typography variant="body2" className="text-gray-500">
                Pilih kelas dan tugaskan wali kelas untuk masing-masing kelas
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
          {/* Progress Summary */}
          {selectedClasses.length > 0 && (
            <Card className="mb-6 bg-gradient-to-r from-blue-50 to-green-50 border-0">
              <CardContent className="p-4">
                <Box className="flex items-center justify-between">
                  <Box className="flex items-center space-x-3">
                    <CheckCircle2 className="w-5 h-5 text-green-600" />
                    <Typography variant="body2" className="font-medium text-gray-700">
                      Progress Penugasan
                    </Typography>
                  </Box>
                  <Box className="flex items-center space-x-2">
                    <Chip 
                      label={`${selectedClasses.length} Kelas Dipilih`}
                      size="small"
                      color="primary"
                      variant="outlined"
                    />
                    <Chip 
                      label={`${completedAssignments} Wali Ditugaskan`}
                      size="small"
                      color="success"
                      variant="outlined"
                    />
                  </Box>
                </Box>
              </CardContent>
            </Card>
          )}

          <Box className="space-y-6 max-h-96 overflow-y-auto">
            {tingkatList.map((tingkat) => {
              const kelasInTingkat = groupedKelas[tingkat.id] || [];
              if (kelasInTingkat.length === 0) return null;

              return (
                <Card key={tingkat.id} className="border border-gray-200 shadow-sm">
                  <CardContent className="p-4">
                    <Box 
                      className="flex items-center justify-between mb-4 cursor-pointer"
                      onClick={() => toggleTingkat(tingkat.id)}
                    >
                      <Box className="flex items-center space-x-2">
                        <School className="w-5 h-5 text-blue-600" />
                        <Typography variant="h6" className="font-semibold text-gray-900">
                          {tingkat.nama}
                        </Typography>
                        <Box className="flex items-center space-x-2">
                          <Chip 
                            label={`${kelasInTingkat.length} Kelas`}
                            size="small"
                            variant="outlined"
                          />
                          {(() => {
                            const stats = getTingkatStats(tingkat.id);
                            return (
                              <>
                                <Chip 
                                  label={`${stats.sudahAda} Sudah Ada Wali`}
                                  size="small"
                                  color="success"
                                  variant="outlined"
                                />
                                <Chip 
                                  label={`${stats.belumAda} Belum Ada Wali`}
                                  size="small"
                                  color="warning"
                                  variant="outlined"
                                />
                              </>
                            );
                          })()}
                        </Box>
                      </Box>
                      {expandedTingkat[tingkat.id] ? (
                        <ChevronUp className="w-5 h-5 text-gray-400" />
                      ) : (
                        <ChevronDown className="w-5 h-5 text-gray-400" />
                      )}
                    </Box>
                    
                    <Collapse in={expandedTingkat[tingkat.id] !== false} timeout="auto">
                      <Box className="space-y-3">
                      {kelasInTingkat.map((kelas) => {
                        const isSelected = selectedClasses.includes(kelas.id);
                        const availablePegawai = getAvailablePegawai(kelas.wali_kelas_id);
                        const hasAssignment = bulkWaliAssignments[kelas.id];

                        return (
                          <Card 
                            key={kelas.id} 
                            className={`border transition-all duration-200 ${
                              isSelected 
                                ? 'border-blue-300 bg-blue-50 shadow-md' 
                                : 'border-gray-200 hover:border-gray-300'
                            }`}
                          >
                            <CardContent className="p-4">
                              <Box className="flex items-center space-x-4">
                                <FormControlLabel
                                  control={
                                    <Checkbox
                                      checked={isSelected}
                                      onChange={() => handleClassSelection(kelas.id)}
                                      sx={{
                                        '&.Mui-checked': {
                                          color: '#3B82F6',
                                        }
                                      }}
                                    />
                                  }
                                  label={
                                    <Box>
                                      <Typography variant="body1" className="font-medium text-gray-900">
                                        {kelas.namaKelas}
                                      </Typography>
                                      <Typography variant="caption" className="text-gray-500">
                                        Wali saat ini: {kelas.waliKelas || 'Belum ada'}
                                      </Typography>
                                    </Box>
                                  }
                                />

                                {isSelected && (
                                  <Box className="flex-1 ml-4">
                                    <TextField
                                      select
                                      size="small"
                                      label="Pilih Wali Kelas"
                                      value={bulkWaliAssignments[kelas.id] || ''}
                                      onChange={(e) => handleWaliAssignment(kelas.id, e.target.value)}
                                      className="min-w-[250px]"
                                      sx={{
                                        '& .MuiOutlinedInput-root': {
                                          borderRadius: 2,
                                          backgroundColor: 'white',
                                          '&:hover': {
                                            boxShadow: '0 2px 4px rgba(0, 0, 0, 0.1)',
                                          }
                                        }
                                      }}
                                    >
                                      <MenuItem value="">
                                        <Box className="flex items-center space-x-2 text-gray-500">
                                          <Users className="w-4 h-4" />
                                          <span>Pilih Wali Kelas</span>
                                        </Box>
                                      </MenuItem>
                                      {availablePegawai.map((pegawai) => (
                                        <MenuItem key={pegawai.id} value={pegawai.id}>
                                          <Box className="flex items-center space-x-2">
                                            <Box className="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
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
                                )}

                                {isSelected && hasAssignment && (
                                  <CheckCircle2 className="w-5 h-5 text-green-500" />
                                )}
                              </Box>
                            </CardContent>
                          </Card>
                        );
                      })}
                      </Box>
                    </Collapse>
                  </CardContent>
                </Card>
              );
            })}
          </Box>

          {/* Action Buttons */}
          <Box className="flex justify-between items-center pt-6 border-t border-gray-100 mt-6">
            <Typography variant="body2" className="text-gray-600">
              {selectedClasses.length > 0 
                ? `${selectedClasses.length} kelas dipilih, ${completedAssignments} siap ditugaskan`
                : 'Pilih kelas untuk memulai penugasan wali kelas'
              }
            </Typography>
            
            <Box className="flex space-x-3">
              <button
                type="button"
                onClick={onClose}
                className="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200"
              >
                Batal
              </button>
              <button
                onClick={onSubmit}
                disabled={selectedClasses.length === 0 || loading}
                className={`px-6 py-2.5 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-lg transition-all duration-200 ${
                  selectedClasses.length === 0 || loading
                    ? 'bg-gray-400 cursor-not-allowed'
                    : 'bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:ring-green-500 hover:shadow-xl transform hover:-translate-y-0.5'
                }`}
              >
                {loading ? (
                  <Box className="flex items-center space-x-2">
                    <Box className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></Box>
                    <span>Menyimpan...</span>
                  </Box>
                ) : (
                  `Tugaskan ${selectedClasses.length} Wali Kelas`
                )}
              </button>
            </Box>
          </Box>
        </Box>
      </DialogContent>
    </Dialog>
  );
};

export default BulkAssignWaliModal;

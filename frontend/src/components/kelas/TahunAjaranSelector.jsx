import React from 'react';
import { 
  Select, 
  MenuItem, 
  FormControl, 
  InputLabel,
  Card,
  CardContent,
  Typography,
  Chip,
  Box,
  Button
} from '@mui/material';
import { CalendarDays, AlertCircle } from 'lucide-react';

const TahunAjaranSelector = ({
  tahunAjaranList,
  selectedTahunAjaran,
  setSelectedTahunAjaran,
  viewMode,
  setViewMode,
  canCreateKelas,
  getTargetTahunAjaran
}) => {
  const getStatusColor = (status) => {
    const colors = {
      draft: 'default',
      preparation: 'warning',
      active: 'success',
      completed: 'info',
      archived: 'error'
    };
    return colors[status] || 'default';
  };

  const getStatusLabel = (status) => {
    const labels = {
      draft: 'Draft',
      preparation: 'Persiapan',
      active: 'Aktif',
      completed: 'Selesai',
      archived: 'Diarsipkan'
    };
    return labels[status] || status;
  };

  const getSemesterLabel = (semester) => {
    const normalized = String(semester || '').toLowerCase();
    if (normalized === 'ganjil') return 'Ganjil';
    if (normalized === 'genap') return 'Genap';
    return 'Ganjil & Genap';
  };

  return (
    <Card className="mb-6">
      <CardContent>
        <Typography variant="h6" component="h2" className="mb-4">
          Pengaturan Tampilan Kelas
        </Typography>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* View Mode Selector */}
          <FormControl fullWidth>
            <InputLabel>Mode Tampilan</InputLabel>
            <Select
              value={viewMode}
              label="Mode Tampilan"
              onChange={(e) => setViewMode(e.target.value)}
            >
              <MenuItem value="active">Tahun Ajaran Aktif</MenuItem>
              <MenuItem value="selected">Tahun Ajaran Terpilih</MenuItem>
              <MenuItem value="can_manage">Yang Bisa Dikelola</MenuItem>
              <MenuItem value="all">Semua Tahun Ajaran</MenuItem>
            </Select>
          </FormControl>

          {/* Tahun Ajaran Selector */}
          {viewMode === 'selected' && (
            <FormControl fullWidth>
              <InputLabel>Pilih Tahun Ajaran</InputLabel>
              <Select
                value={selectedTahunAjaran?.id || ''}
                label="Pilih Tahun Ajaran"
                onChange={(e) => {
                  const ta = tahunAjaranList.find(t => t.id === e.target.value);
                  setSelectedTahunAjaran(ta);
                }}
              >
                <MenuItem value="">
                  <em>Pilih Tahun Ajaran</em>
                </MenuItem>
                {tahunAjaranList.map((ta) => (
                  <MenuItem key={ta.id} value={ta.id}>
                    <div className="flex items-center justify-between w-full">
                      <span>{ta.nama}</span>
                      <Box className="flex items-center gap-2">
                        <Chip
                          size="small"
                          label={getSemesterLabel(ta.semester)}
                          variant="outlined"
                        />
                        <Chip 
                          size="small" 
                          label={getStatusLabel(ta.status)}
                          color={getStatusColor(ta.status)}
                        />
                      </Box>
                    </div>
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}
        </div>

        {/* Target Info */}
        <Box className="mt-4 p-4 bg-gray-50 rounded-lg">
          <Typography variant="subtitle1" className="font-medium mb-2">
            Target Tahun Ajaran Saat Ini:
          </Typography>
          
          {getTargetTahunAjaran() ? (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <CalendarDays className="w-5 h-5 text-gray-500" />
                <Typography>
                  {getTargetTahunAjaran().nama}
                </Typography>
                <Chip 
                  size="small"
                  label={getStatusLabel(getTargetTahunAjaran().status)}
                  color={getStatusColor(getTargetTahunAjaran().status)}
                />
                <Chip
                  size="small"
                  variant="outlined"
                  label={getSemesterLabel(getTargetTahunAjaran().semester)}
                />
              </div>
              
              <div className="flex items-center gap-2">
                <Typography variant="body2" color="textSecondary">
                  Dapat membuat kelas:
                </Typography>
                {canCreateKelas() ? (
                  <Chip 
                    size="small"
                    label="Ya"
                    color="success"
                  />
                ) : (
                  <Chip 
                    size="small"
                    label="Tidak"
                    color="error"
                  />
                )}
              </div>

              {!canCreateKelas() && (
                <Typography variant="body2" color="error" className="flex items-center gap-1">
                  <AlertCircle className="w-4 h-4" />
                  Tidak dapat membuat kelas untuk tahun ajaran dengan status {getTargetTahunAjaran().status}
                </Typography>
              )}
            </div>
          ) : (
            <Typography color="textSecondary">
              Tidak ada tahun ajaran yang dipilih
            </Typography>
          )}
        </Box>

        {/* Quick Actions */}
        {viewMode === 'selected' && selectedTahunAjaran && (
          <Box className="mt-4 flex flex-wrap gap-2">
            <Button
              variant="outlined"
              size="small"
              onClick={() => setViewMode('active')}
            >
              Kembali ke Tahun Ajaran Aktif
            </Button>
            {canCreateKelas() && (
              <Button
                variant="contained"
                size="small"
                color="primary"
              >
                Buat Kelas Baru
              </Button>
            )}
          </Box>
        )}
      </CardContent>
    </Card>
  );
};

export default TahunAjaranSelector;

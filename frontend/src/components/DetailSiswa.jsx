import React from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Grid,
  Box,
  Divider
} from '@mui/material';
import { formatServerDate } from '../services/serverClock';

const DetailSiswa = ({ open, onClose, data }) => {
  if (!data) return null;

  const renderDetail = (label, value) => (
    <Grid container spacing={2} sx={{ py: 1 }}>
      <Grid item xs={4}>
        <Typography variant="body2" color="textSecondary">
          {label}
        </Typography>
      </Grid>
      <Grid item xs={8}>
        <Typography variant="body2">
          {value || '-'}
        </Typography>
      </Grid>
    </Grid>
  );

  const renderSection = (title, children) => (
    <Box sx={{ mb: 3 }}>
      <Typography variant="h6" sx={{ mb: 2, color: 'primary.main' }}>
        {title}
      </Typography>
      {children}
      <Divider sx={{ mt: 2 }} />
    </Box>
  );

  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>
        <Typography variant="h6">Detail Siswa</Typography>
      </DialogTitle>
      
      <DialogContent>
        <Box sx={{ mt: 2 }}>
          {/* Data Pribadi */}
          {renderSection('Data Pribadi', (
            <>
              {renderDetail('Nama Lengkap', data.nama_lengkap)}
              {renderDetail('Username', data.username)}
              {renderDetail('Email', data.email)}
              {renderDetail('Jenis Kelamin', data.jenis_kelamin === 'L' ? 'Laki-laki' : data.jenis_kelamin === 'P' ? 'Perempuan' : '-')}
              {renderDetail('Tanggal Lahir', data.tanggal_lahir)}
              {renderDetail('Alamat', data.alamat)}
              {renderDetail('No. Telepon', data.no_telepon)}
            </>
          ))}

          {/* Data Siswa */}
          {renderSection('Data Siswa', (
            <>
              {renderDetail('NISN', data.dataPribadiSiswa?.nisn)}
              {renderDetail('NIS', data.dataPribadiSiswa?.nis)}
              {renderDetail('Tempat Lahir', data.dataPribadiSiswa?.tempat_lahir)}
              {renderDetail('Agama', data.dataPribadiSiswa?.agama)}
              {renderDetail('Asal Sekolah', data.dataPribadiSiswa?.asal_sekolah)}
              {renderDetail('Tahun Masuk', data.dataPribadiSiswa?.tahun_masuk)}
              {renderDetail('Status Siswa', data.dataPribadiSiswa?.status)}
            </>
          ))}

          {/* Data Orang Tua */}
          {renderSection('Data Orang Tua', (
            <>
              {renderDetail('Nama Ayah', data.dataPribadiSiswa?.nama_ayah)}
              {renderDetail('Pekerjaan Ayah', data.dataPribadiSiswa?.pekerjaan_ayah)}
              {renderDetail('No. HP Ayah', data.dataPribadiSiswa?.no_hp_ayah)}
              {renderDetail('Nama Ibu', data.dataPribadiSiswa?.nama_ibu)}
              {renderDetail('Pekerjaan Ibu', data.dataPribadiSiswa?.pekerjaan_ibu)}
            </>
          ))}

          {/* Data Kelas */}
          {renderSection('Data Kelas', (
            <>
              {renderDetail('Kelas Saat Ini', data.kelas && data.kelas.length > 0 ? data.kelas[0].nama_kelas : '-')}
              {renderDetail('Tahun Ajaran', data.kelas && data.kelas.length > 0 ? data.kelas[0].pivot?.tahun_ajaran_id : '-')}
            </>
          ))}

          {/* Status Akun */}
          {renderSection('Status Akun', (
            <>
              {renderDetail('Status Aktif', data.is_active ? 'Aktif' : 'Tidak Aktif')}
              {renderDetail('Wajib Absen', data.wajib_absen ? 'Ya' : 'Tidak')}
              {renderDetail('Tanggal Dibuat', data.created_at ? (formatServerDate(data.created_at, 'id-ID') || '-') : '-')}
              {renderDetail('Terakhir Diupdate', data.updated_at ? (formatServerDate(data.updated_at, 'id-ID') || '-') : '-')}
            </>
          ))}
        </Box>
      </DialogContent>

      <DialogActions>
        <Button onClick={onClose}>Tutup</Button>
      </DialogActions>
    </Dialog>
  );
};

export default DetailSiswa;

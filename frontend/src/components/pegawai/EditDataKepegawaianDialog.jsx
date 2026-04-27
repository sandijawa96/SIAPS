import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Grid,
  TextField,
  Typography,
  Box,
  Tabs,
  Tab,
  Paper,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip,
  IconButton,
  Divider,
  CircularProgress
} from '@mui/material';
import {
  User,
  Briefcase,
  GraduationCap,
  Users,
  Award,
  Plus,
  Trash2,
  Save,
  X
} from 'lucide-react';
import { useSnackbar } from 'notistack';
import pegawaiExtendedService from '../../services/pegawaiExtendedService.jsx';

const TabPanel = ({ children, value, index, ...other }) => (
  <div
    role="tabpanel"
    hidden={value !== index}
    id={`tabpanel-${index}`}
    aria-labelledby={`tab-${index}`}
    {...other}
  >
    {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
  </div>
);

const EditDataKepegawaianDialog = ({ open, onClose, pegawai, onSuccess }) => {
  const [loading, setLoading] = useState(false);
  const [tabValue, setTabValue] = useState(0);
  const [formData, setFormData] = useState({
    // Kontak
    no_hp: '',
    no_telepon_kantor: '',
    
    // Kepegawaian
    nomor_sk: '',
    tanggal_sk: '',
    golongan: '',
    tmt: '',
    masa_kontrak_mulai: '',
    masa_kontrak_selesai: '',
    nuptk: '',
    jabatan: '',
    sub_jabatan: [],
    pangkat_golongan: '',
    
    // Pendidikan
    pendidikan_terakhir: '',
    jurusan: '',
    universitas: '',
    institusi: '',
    tahun_lulus: '',
    no_ijazah: '',
    gelar_depan: '',
    gelar_belakang: '',
    
    // Mengajar
    bidang_studi: '',
    mata_pelajaran: [],
    jam_mengajar_per_minggu: '',
    kelas_yang_diajar: [],
    
    // Keluarga
    nama_pasangan: '',
    pekerjaan_pasangan: '',
    jumlah_anak: '',
    data_anak: [],
    
    // Lainnya
    alamat_domisili: '',
    keterangan: '',
    sertifikat: [],
    pelatihan: []
  });

  const { enqueueSnackbar } = useSnackbar();

  useEffect(() => {
    if (pegawai && open) {
      setFormData({
        no_hp: pegawai.no_hp || '',
        no_telepon_kantor: pegawai.no_telepon_kantor || '',
        nomor_sk: pegawai.nomor_sk || '',
        tanggal_sk: pegawai.tanggal_sk || '',
        golongan: pegawai.golongan || '',
        tmt: pegawai.tmt || '',
        masa_kontrak_mulai: pegawai.masa_kontrak_mulai || '',
        masa_kontrak_selesai: pegawai.masa_kontrak_selesai || '',
        nuptk: pegawai.nuptk || '',
        jabatan: pegawai.jabatan || '',
        sub_jabatan: Array.isArray(pegawai.sub_jabatan) ? pegawai.sub_jabatan : 
                     pegawai.sub_jabatan ? JSON.parse(pegawai.sub_jabatan) : [],
        pangkat_golongan: pegawai.pangkat_golongan || '',
        pendidikan_terakhir: pegawai.pendidikan_terakhir || '',
        jurusan: pegawai.jurusan || '',
        universitas: pegawai.universitas || '',
        institusi: pegawai.institusi || '',
        tahun_lulus: pegawai.tahun_lulus || '',
        no_ijazah: pegawai.no_ijazah || '',
        gelar_depan: pegawai.gelar_depan || '',
        gelar_belakang: pegawai.gelar_belakang || '',
        bidang_studi: pegawai.bidang_studi || '',
        mata_pelajaran: Array.isArray(pegawai.mata_pelajaran) ? pegawai.mata_pelajaran : 
                       pegawai.mata_pelajaran ? JSON.parse(pegawai.mata_pelajaran) : [],
        jam_mengajar_per_minggu: pegawai.jam_mengajar_per_minggu || '',
        kelas_yang_diajar: Array.isArray(pegawai.kelas_yang_diajar) ? pegawai.kelas_yang_diajar : 
                          pegawai.kelas_yang_diajar ? JSON.parse(pegawai.kelas_yang_diajar) : [],
        nama_pasangan: pegawai.nama_pasangan || '',
        pekerjaan_pasangan: pegawai.pekerjaan_pasangan || '',
        jumlah_anak: pegawai.jumlah_anak || '',
        data_anak: Array.isArray(pegawai.data_anak) ? pegawai.data_anak : 
                   pegawai.data_anak ? JSON.parse(pegawai.data_anak) : [],
        alamat_domisili: pegawai.alamat_domisili || '',
        keterangan: pegawai.keterangan || '',
        sertifikat: Array.isArray(pegawai.sertifikat) ? pegawai.sertifikat : 
                   pegawai.sertifikat ? JSON.parse(pegawai.sertifikat) : [],
        pelatihan: Array.isArray(pegawai.pelatihan) ? pegawai.pelatihan : 
                  pegawai.pelatihan ? JSON.parse(pegawai.pelatihan) : []
      });
    }
  }, [pegawai, open]);

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleArrayAdd = (field, newItem) => {
    if (newItem.trim()) {
      setFormData(prev => ({
        ...prev,
        [field]: [...prev[field], newItem.trim()]
      }));
    }
  };

  const handleArrayRemove = (field, index) => {
    setFormData(prev => ({
      ...prev,
      [field]: prev[field].filter((_, i) => i !== index)
    }));
  };

  const handleSubmit = async () => {
    try {
      setLoading(true);
      
      // Prepare data for submission
      const submitData = {
        ...formData,
        // Convert arrays to JSON strings for backend
        sub_jabatan: JSON.stringify(formData.sub_jabatan),
        mata_pelajaran: JSON.stringify(formData.mata_pelajaran),
        kelas_yang_diajar: JSON.stringify(formData.kelas_yang_diajar),
        data_anak: JSON.stringify(formData.data_anak),
        sertifikat: JSON.stringify(formData.sertifikat),
        pelatihan: JSON.stringify(formData.pelatihan)
      };

      await pegawaiExtendedService.update(pegawai.id, submitData);
      
      enqueueSnackbar('Data kepegawaian berhasil diupdate', { variant: 'success' });
      onSuccess();
      onClose();
    } catch (error) {
      console.error('Error updating data kepegawaian:', error);
      enqueueSnackbar(error.message || 'Gagal mengupdate data kepegawaian', { 
        variant: 'error' 
      });
    } finally {
      setLoading(false);
    }
  };

  const ArrayInputField = ({ label, field, placeholder }) => {
    const [inputValue, setInputValue] = useState('');

    return (
      <Box>
        <Typography variant="subtitle2" gutterBottom>{label}</Typography>
        <Box display="flex" gap={1} mb={1}>
          <TextField
            fullWidth
            size="small"
            placeholder={placeholder}
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyPress={(e) => {
              if (e.key === 'Enter') {
                handleArrayAdd(field, inputValue);
                setInputValue('');
              }
            }}
          />
          <IconButton
            size="small"
            onClick={() => {
              handleArrayAdd(field, inputValue);
              setInputValue('');
            }}
            color="primary"
          >
            <Plus size={16} />
          </IconButton>
        </Box>
        <Box display="flex" flexWrap="wrap" gap={1}>
          {formData[field].map((item, index) => (
            <Chip
              key={index}
              label={item}
              size="small"
              onDelete={() => handleArrayRemove(field, index)}
              deleteIcon={<Trash2 size={12} />}
            />
          ))}
        </Box>
      </Box>
    );
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="lg" fullWidth>
      <DialogTitle>
        <Box display="flex" alignItems="center" justifyContent="space-between">
          <Box display="flex" alignItems="center" gap={2}>
            <User size={24} />
            <Box>
              <Typography variant="h6">Edit Data Kepegawaian</Typography>
              <Typography variant="body2" color="textSecondary">
                {pegawai?.nama_lengkap} • {pegawai?.nip || 'Tanpa NIP'}
              </Typography>
            </Box>
          </Box>
          <IconButton onClick={onClose}>
            <X size={20} />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent>
        <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
          <Tabs value={tabValue} onChange={(e, newValue) => setTabValue(newValue)}>
            <Tab icon={<Briefcase size={16} />} label="Kepegawaian" />
            <Tab icon={<GraduationCap size={16} />} label="Pendidikan" />
            <Tab icon={<Users size={16} />} label="Mengajar" />
            <Tab icon={<User size={16} />} label="Keluarga" />
            <Tab icon={<Award size={16} />} label="Sertifikat" />
          </Tabs>
        </Box>

        {/* Tab Kepegawaian */}
        <TabPanel value={tabValue} index={0}>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="No. HP"
                value={formData.no_hp}
                onChange={(e) => handleInputChange('no_hp', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="No. Telepon Kantor"
                value={formData.no_telepon_kantor}
                onChange={(e) => handleInputChange('no_telepon_kantor', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Nomor SK"
                value={formData.nomor_sk}
                onChange={(e) => handleInputChange('nomor_sk', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Tanggal SK"
                type="date"
                value={formData.tanggal_sk}
                onChange={(e) => handleInputChange('tanggal_sk', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Golongan"
                value={formData.golongan}
                onChange={(e) => handleInputChange('golongan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="TMT"
                value={formData.tmt}
                onChange={(e) => handleInputChange('tmt', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Masa Kontrak Mulai"
                type="date"
                value={formData.masa_kontrak_mulai}
                onChange={(e) => handleInputChange('masa_kontrak_mulai', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Masa Kontrak Selesai"
                type="date"
                value={formData.masa_kontrak_selesai}
                onChange={(e) => handleInputChange('masa_kontrak_selesai', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="NUPTK"
                value={formData.nuptk}
                onChange={(e) => handleInputChange('nuptk', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Jabatan"
                value={formData.jabatan}
                onChange={(e) => handleInputChange('jabatan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Pangkat Golongan"
                value={formData.pangkat_golongan}
                onChange={(e) => handleInputChange('pangkat_golongan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12}>
              <ArrayInputField
                label="Sub Jabatan"
                field="sub_jabatan"
                placeholder="Tambah sub jabatan..."
              />
            </Grid>
          </Grid>
        </TabPanel>

        {/* Tab Pendidikan */}
        <TabPanel value={tabValue} index={1}>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <FormControl fullWidth>
                <InputLabel>Pendidikan Terakhir</InputLabel>
                <Select
                  value={formData.pendidikan_terakhir}
                  onChange={(e) => handleInputChange('pendidikan_terakhir', e.target.value)}
                  label="Pendidikan Terakhir"
                >
                  <MenuItem value="SMA">SMA</MenuItem>
                  <MenuItem value="D3">D3</MenuItem>
                  <MenuItem value="S1">S1</MenuItem>
                  <MenuItem value="S2">S2</MenuItem>
                  <MenuItem value="S3">S3</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Jurusan"
                value={formData.jurusan}
                onChange={(e) => handleInputChange('jurusan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Universitas"
                value={formData.universitas}
                onChange={(e) => handleInputChange('universitas', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Institusi"
                value={formData.institusi}
                onChange={(e) => handleInputChange('institusi', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Tahun Lulus"
                value={formData.tahun_lulus}
                onChange={(e) => handleInputChange('tahun_lulus', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="No. Ijazah"
                value={formData.no_ijazah}
                onChange={(e) => handleInputChange('no_ijazah', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Gelar Depan"
                value={formData.gelar_depan}
                onChange={(e) => handleInputChange('gelar_depan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Gelar Belakang"
                value={formData.gelar_belakang}
                onChange={(e) => handleInputChange('gelar_belakang', e.target.value)}
              />
            </Grid>
          </Grid>
        </TabPanel>

        {/* Tab Mengajar */}
        <TabPanel value={tabValue} index={2}>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Bidang Studi"
                value={formData.bidang_studi}
                onChange={(e) => handleInputChange('bidang_studi', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Jam Mengajar per Minggu"
                type="number"
                value={formData.jam_mengajar_per_minggu}
                onChange={(e) => handleInputChange('jam_mengajar_per_minggu', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <ArrayInputField
                label="Mata Pelajaran"
                field="mata_pelajaran"
                placeholder="Tambah mata pelajaran..."
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <ArrayInputField
                label="Kelas yang Diajar"
                field="kelas_yang_diajar"
                placeholder="Tambah kelas..."
              />
            </Grid>
          </Grid>
        </TabPanel>

        {/* Tab Keluarga */}
        <TabPanel value={tabValue} index={3}>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Nama Pasangan"
                value={formData.nama_pasangan}
                onChange={(e) => handleInputChange('nama_pasangan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Pekerjaan Pasangan"
                value={formData.pekerjaan_pasangan}
                onChange={(e) => handleInputChange('pekerjaan_pasangan', e.target.value)}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                label="Jumlah Anak"
                type="number"
                value={formData.jumlah_anak}
                onChange={(e) => handleInputChange('jumlah_anak', e.target.value)}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Alamat Domisili"
                multiline
                rows={3}
                value={formData.alamat_domisili}
                onChange={(e) => handleInputChange('alamat_domisili', e.target.value)}
              />
            </Grid>
            <Grid item xs={12}>
              <ArrayInputField
                label="Data Anak"
                field="data_anak"
                placeholder="Tambah data anak (Nama, Umur, dll)..."
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Keterangan"
                multiline
                rows={3}
                value={formData.keterangan}
                onChange={(e) => handleInputChange('keterangan', e.target.value)}
              />
            </Grid>
          </Grid>
        </TabPanel>

        {/* Tab Sertifikat */}
        <TabPanel value={tabValue} index={4}>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <ArrayInputField
                label="Sertifikat"
                field="sertifikat"
                placeholder="Tambah sertifikat..."
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <ArrayInputField
                label="Pelatihan"
                field="pelatihan"
                placeholder="Tambah pelatihan..."
              />
            </Grid>
          </Grid>
        </TabPanel>
      </DialogContent>

      <DialogActions>
        <Button onClick={onClose} disabled={loading}>
          Batal
        </Button>
        <Button
          variant="contained"
          onClick={handleSubmit}
          disabled={loading}
          startIcon={loading ? <CircularProgress size={16} /> : <Save size={16} />}
        >
          {loading ? 'Menyimpan...' : 'Simpan'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default EditDataKepegawaianDialog;

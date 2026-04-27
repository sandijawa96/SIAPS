import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  Grid,
  LinearProgress,
  MenuItem,
  Paper,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography
} from '@mui/material';
import {
  AlertCircle,
  Camera,
  ClipboardList,
  Download,
  FileText,
  Lock,
  Mail,
  MapPin,
  Pencil,
  Phone,
  RefreshCw,
  Save,
  Search,
  Sparkles,
  Trash2,
  User,
  X
} from 'lucide-react';
import { useParams } from 'react-router-dom';
import { toast } from 'react-hot-toast';
import { personalDataAPI } from '../services/api';

const USER_KEYS = new Set([
  'nama_lengkap',
  'username',
  'email',
  'nis',
  'nisn',
  'nip',
  'nik',
  'status_kepegawaian',
  'jenis_kelamin',
  'tempat_lahir',
  'tanggal_lahir',
  'agama',
  'alamat',
  'rt',
  'rw',
  'kelurahan',
  'kecamatan',
  'kota_kabupaten',
  'provinsi',
  'kode_pos',
]);

const EMPTY_STRING_FIELDS = ['alamat', 'alasan_layak_pip', 'keterangan', 'alamat_domisili', 'alamat_wali'];

const PROFILE_GRADIENT = 'linear-gradient(135deg, #082f49 0%, #0f766e 54%, #f59e0b 130%)';
const PANEL_BORDER = 'rgba(15, 118, 110, 0.14)';
const SOFT_BLUE = '#f4f9ff';
const INK = '#0f2742';
const MUTED = '#64748b';
const SECTION_TONES = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#16a34a'];
const MAX_DOCUMENT_SIZE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_DOCUMENT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
const ACCEPTED_DOCUMENT_INPUT = ACCEPTED_DOCUMENT_EXTENSIONS.map((extension) => `.${extension}`).join(',');
const DOCUMENT_GROUPS_BY_PROFILE = {
  siswa: [
    {
      title: 'Identitas Siswa',
      rows: [
        { type: 'kk', label: 'Kartu Keluarga (KK)', requirement: 'required', note: 'Data keluarga utama siswa.' },
        { type: 'akta', label: 'Akta Kelahiran', requirement: 'required', note: 'Validasi identitas dan tanggal lahir.' },
        { type: 'bukti_nisn', label: 'Bukti NISN', requirement: 'required', note: 'Screenshot/lembar data NISN dari sumber resmi.' },
        { type: 'pas_foto', label: 'Pas Foto Siswa', requirement: 'required', note: 'Untuk kartu pelajar dan arsip sekolah.' },
        { type: 'ktp_siswa', label: 'KTP Siswa / Identitas Siswa', aliases: ['identitas'], requirement: 'optional', note: 'Opsional, hanya jika siswa sudah memiliki KTP.' },
        { type: 'kartu_pelajar', label: 'Kartu Pelajar', requirement: 'optional', note: 'Diisi jika kartu sudah dicetak atau tersedia.' },
      ],
    },
    {
      title: 'Akademik & PPDB SMA',
      rows: [
        { type: 'ijazah_smp', label: 'Ijazah SMP/MTs atau SKL', aliases: ['ijazah_sd', 'ijazah', 'akademik'], requirement: 'required', note: 'Dokumen kelulusan jenjang sebelumnya.' },
        { type: 'skhun', label: 'SKHUN/SHUN SMP/MTs', requirement: 'optional', note: 'Diisi jika sekolah asal menerbitkan.' },
        { type: 'rapor', label: 'Rapor SMP/MTs', requirement: 'optional', note: 'Arsip PPDB atau seleksi internal jika diperlukan.' },
        { type: 'surat_pindah', label: 'Surat Pindah / Mutasi', requirement: 'conditional', note: 'Hanya untuk siswa pindahan.' },
        { type: 'sertifikat_prestasi', label: 'Sertifikat Prestasi', requirement: 'conditional', note: 'Hanya untuk jalur prestasi atau arsip pembinaan.' },
      ],
    },
    {
      title: 'Orang Tua / Wali',
      rows: [
        { type: 'ktp_ayah', label: 'KTP Ayah', requirement: 'conditional', note: 'Jika sekolah perlu verifikasi identitas orang tua.' },
        { type: 'ktp_ibu', label: 'KTP Ibu', requirement: 'conditional', note: 'Jika sekolah perlu verifikasi identitas orang tua.' },
        { type: 'ktp_wali', label: 'KTP Wali', requirement: 'conditional', note: 'Jika penanggung jawab bukan ayah/ibu.' },
        { type: 'surat_perwalian', label: 'Surat Keterangan Wali', requirement: 'conditional', note: 'Hanya untuk siswa yang diasuh wali.' },
      ],
    },
    {
      title: 'Bantuan & Administrasi',
      rows: [
        { type: 'kip', label: 'Kartu Indonesia Pintar (KIP)', requirement: 'conditional', note: 'Jika siswa penerima/pendaftar bantuan PIP.' },
        { type: 'kks', label: 'Kartu Keluarga Sejahtera (KKS)', requirement: 'conditional', note: 'Jika keluarga memiliki KKS.' },
        { type: 'kps', label: 'KPS / PKH', requirement: 'conditional', note: 'Jika keluarga memiliki KPS/PKH.' },
        { type: 'sktm', label: 'Surat Keterangan Tidak Mampu', requirement: 'conditional', note: 'Jika dipakai untuk pengajuan bantuan.' },
        { type: 'rekening_siswa', label: 'Buku Rekening Siswa', requirement: 'conditional', note: 'Jika bantuan dicairkan ke rekening siswa.' },
      ],
    },
  ],
  pegawai: [
    {
      title: 'Dokumen Pribadi',
      rows: [
        { type: 'ktp', label: 'Kartu Tanda Penduduk (KTP)', aliases: ['identitas'], requirement: 'required', note: 'Identitas utama pegawai.' },
        { type: 'kk', label: 'Kartu Keluarga (KK)', requirement: 'required', note: 'Arsip keluarga pegawai.' },
        { type: 'pas_foto_pegawai', label: 'Pas Foto Pegawai', requirement: 'required', note: 'Untuk kartu pegawai dan arsip sekolah.' },
        { type: 'npwp', label: 'Nomor Pokok Wajib Pajak (NPWP)', requirement: 'conditional', note: 'Wajib jika terkait pajak atau payroll.' },
        { type: 'askes_bpjs', label: 'ASKES/BPJS', requirement: 'conditional', note: 'Jika sekolah mengarsipkan kepesertaan kesehatan.' },
        { type: 'akta', label: 'Akta Kelahiran', requirement: 'optional', note: 'Opsional untuk pelengkap biodata.' },
        { type: 'akta_nikah', label: 'Akta Nikah', requirement: 'conditional', note: 'Jika dibutuhkan untuk data keluarga/tunjangan.' },
        { type: 'akta_cerai', label: 'Akta Cerai', requirement: 'conditional', note: 'Jika relevan dengan status keluarga.' },
      ],
    },
    {
      title: 'Pendidikan & Sertifikasi',
      rows: [
        { type: 'ijazah_terakhir', label: 'Ijazah Terakhir', aliases: ['ijazah', 'akademik'], requirement: 'required', note: 'Dokumen pendidikan tertinggi.' },
        { type: 'transkrip_nilai', label: 'Transkrip Nilai', requirement: 'required', note: 'Pendamping ijazah terakhir.' },
        { type: 'sertifikat_pendidik', label: 'Sertifikat Pendidik', requirement: 'conditional', note: 'Untuk guru yang sudah tersertifikasi.' },
        { type: 'sertifikat_pelatihan', label: 'Sertifikat Pelatihan/Diklat', requirement: 'optional', note: 'Arsip pengembangan kompetensi.' },
        { type: 'nuptk_dokumen', label: 'Dokumen NUPTK', requirement: 'conditional', note: 'Jika pegawai memiliki NUPTK.' },
      ],
    },
    {
      title: 'Kepegawaian',
      rows: [
        { type: 'sk_pengangkatan', label: 'SK Pengangkatan / Kontrak Kerja', aliases: ['sk'], requirement: 'required', note: 'Dasar status kerja di sekolah.' },
        { type: 'sk_tugas_tambahan', label: 'SK Pembagian Tugas / Mengajar', requirement: 'required', note: 'Arsip tugas guru/tendik tahun berjalan.' },
        { type: 'sk_jabatan', label: 'SK Jabatan', requirement: 'conditional', note: 'Untuk wakasek, kepala lab, wali kelas, pembina, dan jabatan khusus.' },
        { type: 'sk_cpns', label: 'SK CPNS', requirement: 'conditional', note: 'Khusus pegawai CPNS/PNS.' },
        { type: 'sk_pns', label: 'SK PNS', requirement: 'conditional', note: 'Khusus pegawai PNS.' },
        { type: 'karpeg', label: 'KARPEG', requirement: 'conditional', note: 'Khusus pegawai yang memiliki KARPEG.' },
        { type: 'kpe', label: 'KPE', requirement: 'conditional', note: 'Khusus pegawai yang memiliki KPE.' },
        { type: 'skumptk', label: 'SKUMPTK', requirement: 'conditional', note: 'Jika sekolah/dinas menggunakan dokumen ini.' },
      ],
    },
    {
      title: 'Pemberhentian',
      rows: [
        { type: 'sk_pp', label: 'SK PP', requirement: 'conditional', note: 'Hanya untuk proses pemberhentian/pensiun.' },
        { type: 'penambahan_masa_kerja', label: 'Penambahan Masa Kerja', requirement: 'conditional', note: 'Jika ada penetapan masa kerja tambahan.' },
        { type: 'sk_pensiun_bup', label: 'SK Pensiun - BUP', requirement: 'conditional', note: 'Hanya untuk pegawai pensiun.' },
      ],
    },
  ],
};
const profileTypeLabel = {
  siswa: 'Profil Siswa',
  pegawai: 'Profil Pegawai',
};
const documentRequirementMeta = {
  required: {
    label: 'Wajib',
    color: '#b91c1c',
    background: '#fee2e2',
    border: '#fecaca',
  },
  conditional: {
    label: 'Kondisional',
    color: '#92400e',
    background: '#fef3c7',
    border: '#fde68a',
  },
  optional: {
    label: 'Opsional',
    color: '#475569',
    background: '#f1f5f9',
    border: '#cbd5e1',
  },
};

const DataPribadiSaya = () => {
  const { userId } = useParams();
  const isAdminMode = Boolean(userId);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [uploadingDocument, setUploadingDocument] = useState(false);
  const [documentsLoading, setDocumentsLoading] = useState(false);
  const [errorState, setErrorState] = useState(null);
  const [documentsError, setDocumentsError] = useState('');

  const [schema, setSchema] = useState(null);
  const [profileData, setProfileData] = useState(null);
  const [formData, setFormData] = useState({});
  const [initialData, setInitialData] = useState({});
  const [documents, setDocuments] = useState([]);
  const [nextcloudConfigured, setNextcloudConfigured] = useState(false);
  const [documentsOpen, setDocumentsOpen] = useState(false);

  const [activeTab, setActiveTab] = useState(0);
  const [isEditing, setIsEditing] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setErrorState(null);

    try {
      const [profileResponse, schemaResponse] = await Promise.all([
        isAdminMode ? personalDataAPI.getForUser(userId) : personalDataAPI.get(),
        isAdminMode ? personalDataAPI.getSchemaForUser(userId) : personalDataAPI.getSchema(),
      ]);

      const profilePayload = profileResponse?.data?.data ?? profileResponse?.data ?? null;
      const schemaPayload = schemaResponse?.data?.data ?? schemaResponse?.data ?? null;

      setProfileData(profilePayload);
      setSchema(schemaPayload);

      const flattened = {
        ...(profilePayload?.common || {}),
        ...(profilePayload?.detail || {}),
      };

      setFormData(flattened);
      setInitialData(flattened);
      setActiveTab(0);
    } catch (error) {
      const status = error?.response?.status;
      const message = error?.response?.data?.message || error?.message || 'Gagal memuat data pribadi';
      setErrorState({ status, message });
    } finally {
      setLoading(false);
    }
  }, [isAdminMode, userId]);

  const fetchDocuments = useCallback(async () => {
    setDocumentsLoading(true);
    setDocumentsError('');

    try {
      const response = isAdminMode
        ? await personalDataAPI.getDocumentsForUser(userId)
        : await personalDataAPI.getDocuments();
      const payload = response?.data?.data ?? response?.data ?? {};

      setDocuments(Array.isArray(payload.documents) ? payload.documents : []);
      setNextcloudConfigured(Boolean(payload.nextcloud_configured));
    } catch (error) {
      const message = error?.response?.data?.message || 'Gagal memuat dokumen profil';
      setDocuments([]);
      setDocumentsError(message);
    } finally {
      setDocumentsLoading(false);
    }
  }, [isAdminMode, userId]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  useEffect(() => {
    fetchDocuments();
  }, [fetchDocuments]);

  const sections = useMemo(() => schema?.sections || [], [schema]);

  const editableFields = useMemo(() => {
    const keys = new Set();
    sections.forEach((section) => {
      (section.fields || []).forEach((field) => {
        if (field?.editable && field?.key && !field.key.startsWith('active_class.')) {
          keys.add(field.key);
        }
      });
    });
    return Array.from(keys);
  }, [sections]);

  const flattenedFieldKeys = useMemo(() => {
    const keys = [];
    sections.forEach((section) => {
      (section.fields || []).forEach((field) => {
        if (field?.key) {
          keys.push(field.key);
        }
      });
    });
    return keys;
  }, [sections]);

  const changedKeys = useMemo(() => {
    return editableFields.filter((key) => (
      JSON.stringify(formData[key] ?? null) !== JSON.stringify(initialData[key] ?? null)
    ));
  }, [editableFields, formData, initialData]);

  const hasUnsavedChanges = changedKeys.length > 0;

  const filledSummary = useMemo(() => {
    const fillableKeys = flattenedFieldKeys.filter((key) => !key.startsWith('active_class.'));
    const filled = fillableKeys.filter((key) => hasDisplayValue(formData[key])).length;
    const total = Math.max(fillableKeys.length, 1);

    return {
      filled,
      total,
      percent: Math.round((filled / total) * 100),
    };
  }, [flattenedFieldKeys, formData]);

  const sectionMetrics = useMemo(() => {
    return sections.map((section) => {
      const fields = section.fields || [];
      const editable = fields.filter((field) => field?.editable && !field.key?.startsWith('active_class.')).length;
      const filled = fields.filter((field) => {
        const value = field.key?.startsWith('active_class.')
          ? profileData?.active_class?.[field.key.replace('active_class.', '')]
          : formData[field.key];
        return hasDisplayValue(value);
      }).length;
      const changed = fields.filter((field) => changedKeys.includes(field.key)).length;

      return {
        editable,
        filled,
        changed,
        total: fields.length,
        percent: fields.length ? Math.round((filled / fields.length) * 100) : 0,
      };
    });
  }, [changedKeys, formData, profileData, sections]);

  const handleChange = (key, value, type) => {
    let resolved = value;

    if (type === 'boolean') {
      if (value === '') {
        resolved = null;
      } else {
        resolved = value === 'true';
      }
    }

    setFormData((prev) => ({
      ...prev,
      [key]: resolved,
    }));
  };

  const handleCancelEdit = () => {
    if (hasUnsavedChanges && !window.confirm('Perubahan belum disimpan. Batalkan perubahan?')) {
      return;
    }

    setFormData(initialData);
    setIsEditing(false);
  };

  const handleSave = async () => {
    if (!editableFields.length) {
      return;
    }

    setSaving(true);

    try {
      const payload = {};
      editableFields.forEach((key) => {
        if (!Object.prototype.hasOwnProperty.call(formData, key)) {
          return;
        }

        const rawValue = formData[key];
        if (rawValue === undefined) {
          return;
        }

        if (rawValue === '' && EMPTY_STRING_FIELDS.includes(key)) {
          payload[key] = null;
          return;
        }

        payload[key] = rawValue;
      });

      const updateResponse = isAdminMode
        ? await personalDataAPI.updateForUser(userId, payload)
        : await personalDataAPI.update(payload);
      const updatedPayload = updateResponse?.data?.data ?? updateResponse?.data ?? null;

      if (updatedPayload) {
        const flattened = {
          ...(updatedPayload.common || {}),
          ...(updatedPayload.detail || {}),
        };
        setProfileData(updatedPayload);
        setFormData(flattened);
        setInitialData(flattened);
      } else {
        await fetchData();
      }

      setIsEditing(false);
      toast.success(isAdminMode ? 'Data pribadi user berhasil diperbarui' : 'Data pribadi berhasil diperbarui');
    } catch (error) {
      const message = error?.response?.data?.message || (isAdminMode ? 'Gagal menyimpan data pribadi user' : 'Gagal menyimpan data pribadi');
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  const handleAvatarUpload = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
      return;
    }

    if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
      toast.error('Format foto harus JPG atau PNG');
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      toast.error('Ukuran foto maksimal 2MB');
      return;
    }

    setUploadingAvatar(true);

    try {
      const response = isAdminMode
        ? await personalDataAPI.updateAvatarForUser(userId, file)
        : await personalDataAPI.updateAvatar(file);
      const payload = response?.data?.data ?? response?.data ?? {};

      setFormData((prev) => ({
        ...prev,
        foto_profil: payload.foto_profil ?? prev.foto_profil,
        foto_profil_url: payload.foto_profil_url ?? prev.foto_profil_url,
      }));

      setInitialData((prev) => ({
        ...prev,
        foto_profil: payload.foto_profil ?? prev.foto_profil,
        foto_profil_url: payload.foto_profil_url ?? prev.foto_profil_url,
      }));

      setProfileData((prev) => ({
        ...prev,
        common: {
          ...(prev?.common || {}),
          foto_profil: payload.foto_profil ?? prev?.common?.foto_profil,
          foto_profil_url: payload.foto_profil_url ?? prev?.common?.foto_profil_url,
        },
      }));

      toast.success(isAdminMode ? 'Foto profil user berhasil diperbarui' : 'Foto profil berhasil diperbarui');
    } catch (error) {
      const message = error?.response?.data?.message || (isAdminMode ? 'Gagal memperbarui foto profil user' : 'Gagal memperbarui foto profil');
      toast.error(message);
    } finally {
      setUploadingAvatar(false);
    }
  };

  const handleDocumentUpload = async (event, override = {}) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
      return;
    }

    const extension = String(file.name || '').split('.').pop()?.toLowerCase();
    if (!extension || !ACCEPTED_DOCUMENT_EXTENSIONS.includes(extension)) {
      toast.error('Format dokumen harus PDF, gambar, Word, atau Excel');
      return;
    }

    if (file.size > MAX_DOCUMENT_SIZE_BYTES) {
      toast.error('Ukuran dokumen maksimal 10MB');
      return;
    }

    const payload = new FormData();
    const resolvedDocumentType = override.documentType || 'other';
    const resolvedDocumentTitle = override.documentTitle ?? '';
    const replacementTypes = new Set([resolvedDocumentType, ...(override.replacementTypes || [])]);

    payload.append('file', file);
    payload.append('document_type', resolvedDocumentType);
    if (String(resolvedDocumentTitle).trim() !== '') {
      payload.append('title', String(resolvedDocumentTitle).trim());
    }

    setUploadingDocument(true);

    try {
      const response = isAdminMode
        ? await personalDataAPI.uploadDocumentForUser(userId, payload)
        : await personalDataAPI.uploadDocument(payload);
      const uploaded = response?.data?.data ?? null;

      if (uploaded) {
        setDocuments((prev) => [
          uploaded,
          ...prev.filter((document) => document.id !== uploaded.id && !replacementTypes.has(document.document_type)),
        ]);
      } else {
        await fetchDocuments();
      }

      toast.success('Dokumen berhasil diupload ke Nextcloud sekolah');
    } catch (error) {
      const message = error?.response?.data?.message || 'Gagal upload dokumen ke Nextcloud';
      toast.error(message);
    } finally {
      setUploadingDocument(false);
    }
  };

  const handleDocumentDelete = async (documentId) => {
    if (!window.confirm('Hapus dokumen ini dari profil dan Nextcloud sekolah?')) {
      return;
    }

    try {
      const response = isAdminMode
        ? await personalDataAPI.deleteDocumentForUser(userId, documentId)
        : await personalDataAPI.deleteDocument(documentId);

      setDocuments((prev) => prev.filter((document) => document.id !== documentId));
      toast.success(response?.data?.message || 'Dokumen berhasil dihapus');
    } catch (error) {
      const message = error?.response?.data?.message || 'Gagal menghapus dokumen';
      toast.error(message);
    }
  };

  const handleOpenDocuments = () => {
    setDocumentsOpen(true);
    fetchDocuments();
  };

  const renderField = (field) => {
    const valueFromActiveClass = field.key.startsWith('active_class.')
      ? profileData?.active_class?.[field.key.replace('active_class.', '')]
      : undefined;
    const value = valueFromActiveClass ?? formData[field.key] ?? '';
    const fieldType = field.type || 'text';
    const isReadonly = !isEditing || field.editable === false || field.key.startsWith('active_class.');
    const fieldChanged = changedKeys.includes(field.key);
    const commonFieldSx = {
      '& .MuiOutlinedInput-root': {
        borderRadius: 3,
        backgroundColor: isReadonly ? 'rgba(248, 250, 252, 0.9)' : '#ffffff',
        transition: 'box-shadow 180ms ease, background-color 180ms ease',
        '& fieldset': {
          borderColor: fieldChanged ? '#f59e0b' : 'rgba(148, 163, 184, 0.35)',
        },
        '&:hover fieldset': {
          borderColor: fieldChanged ? '#d97706' : '#0f766e',
        },
        '&.Mui-focused fieldset': {
          borderColor: '#0f766e',
        },
      },
      '& .MuiInputLabel-root.Mui-focused': {
        color: '#0f766e',
      },
    };

    if (fieldType === 'array') {
      return (
        <TextField
          fullWidth
          label={field.label}
          value={Array.isArray(value) ? JSON.stringify(value) : value || ''}
          multiline
          minRows={2}
          disabled={isReadonly}
          helperText={buildFieldHelper(field, isReadonly, fieldChanged)}
          sx={commonFieldSx}
          onChange={(event) => {
            const raw = event.target.value;
            if (!raw) {
              handleChange(field.key, null, fieldType);
              return;
            }

            try {
              const parsed = JSON.parse(raw);
              handleChange(field.key, parsed, fieldType);
            } catch (_error) {
              handleChange(field.key, raw, 'text');
            }
          }}
        />
      );
    }

    if (fieldType === 'boolean') {
      const selectValue = value === null || value === undefined ? '' : String(Boolean(value));
      return (
        <TextField
          select
          fullWidth
          label={field.label}
          value={selectValue}
          disabled={isReadonly}
          helperText={buildFieldHelper(field, isReadonly, fieldChanged)}
          sx={commonFieldSx}
          onChange={(event) => handleChange(field.key, event.target.value, fieldType)}
        >
          <MenuItem value="">-</MenuItem>
          <MenuItem value="true">Ya</MenuItem>
          <MenuItem value="false">Tidak</MenuItem>
        </TextField>
      );
    }

    const inputType =
      fieldType === 'date'
        ? 'date'
        : fieldType === 'number'
          ? 'number'
          : fieldType === 'email'
            ? 'email'
            : 'text';
    const multiline = fieldType === 'textarea';

    return (
      <TextField
        fullWidth
        label={field.label}
        value={value ?? ''}
        type={inputType}
        multiline={multiline}
        minRows={multiline ? 2 : undefined}
        disabled={isReadonly}
        InputLabelProps={fieldType === 'date' ? { shrink: true } : undefined}
        helperText={buildFieldHelper(field, isReadonly, fieldChanged)}
        sx={commonFieldSx}
        onChange={(event) => handleChange(field.key, event.target.value, fieldType)}
      />
    );
  };

  if (loading) {
    return (
      <Box
        sx={{
          minHeight: '60vh',
          display: 'grid',
          placeItems: 'center',
          background: 'radial-gradient(circle at 50% 20%, rgba(15, 118, 110, 0.08), transparent 38%)',
        }}
      >
        <Stack alignItems="center" spacing={2}>
          <CircularProgress size={34} sx={{ color: '#0f766e' }} />
          <Typography color="text.secondary" fontWeight={700}>
            Memuat profil dan skema data...
          </Typography>
        </Stack>
      </Box>
    );
  }

  if (errorState) {
    return (
      <Alert
        severity={errorState.status === 403 ? 'info' : 'error'}
        icon={<AlertCircle size={18} />}
        sx={{ borderRadius: 3 }}
      >
        {errorState.message}
      </Alert>
    );
  }

  const common = profileData?.common || {};
  const activeClass = profileData?.active_class || {};
  const currentSection = sections[activeTab] || null;
  const currentSectionMetrics = sectionMetrics[activeTab] || {
    editable: 0,
    filled: 0,
    changed: 0,
    total: 0,
    percent: 0,
  };
  const isStudent = profileData?.profile_type === 'siswa';
  const accent = isStudent ? '#0f766e' : '#2563eb';
  const primaryIdentifier = isStudent
    ? `NIS ${displayValue(common.nis)} / NISN ${displayValue(common.nisn)}`
    : `NIP ${displayValue(common.nip)} / NIK ${displayValue(common.nik)}`;
  const contactPhone = isStudent
    ? common.no_hp_siswa || common.no_hp_ortu || common.no_hp
    : common.no_hp || formData.no_hp || formData.no_telepon_kantor;
  const contactEmail = common.email || common.email_siswa || formData.email_notifikasi;
  const addressSummary = buildAddressSummary(formData);

  return (
    <Stack spacing={3} sx={{ pb: 5 }}>
      {isAdminMode && (
        <Alert severity="info" sx={{ borderRadius: 3 }}>
          Mode Admin aktif. Anda sedang mengelola data pribadi pengguna ID {userId}.
        </Alert>
      )}

      <ProfileHero
        common={common}
        profileType={profileData?.profile_type}
        isStudent={isStudent}
        isEditing={isEditing}
        hasUnsavedChanges={hasUnsavedChanges}
        changedCount={changedKeys.length}
        primaryIdentifier={primaryIdentifier}
        activeClass={activeClass}
        filledSummary={filledSummary}
        editableCount={editableFields.length}
        documentsCount={documents.length}
        documentsLoading={documentsLoading}
        uploadingAvatar={uploadingAvatar}
        onAvatarUpload={handleAvatarUpload}
        onOpenDocuments={handleOpenDocuments}
      />

      <Grid container spacing={3}>
        <Grid item xs={12} lg={4}>
          <Stack spacing={2.5}>
            <Paper elevation={0} sx={panelSx}>
              <Stack spacing={2}>
                <PanelHeader
                  icon={<User size={18} />}
                  title="Ringkasan Profil"
                  subtitle="Snapshot data utama"
                  accent={accent}
                />
                <SummaryRow icon={<Mail size={17} />} label="Email" value={displayValue(contactEmail)} />
                <SummaryRow icon={<Phone size={17} />} label="Telepon" value={displayValue(contactPhone)} />
                <SummaryRow icon={<MapPin size={17} />} label="Alamat" value={addressSummary} />
              </Stack>
            </Paper>

            <Paper elevation={0} sx={panelSx}>
              <Stack spacing={2}>
                <PanelHeader
                  icon={<ClipboardList size={18} />}
                  title="Kategori Data"
                  subtitle="Mock navigasi section"
                  accent={accent}
                />
                <Stack spacing={1}>
                  {sections.map((section, index) => (
                    <SectionButton
                      key={section.key}
                      section={section}
                      index={index}
                      selected={activeTab === index}
                      metrics={sectionMetrics[index] || {}}
                      onClick={() => setActiveTab(index)}
                    />
                  ))}
                </Stack>
              </Stack>
            </Paper>

          </Stack>
        </Grid>

        <Grid item xs={12} lg={8}>
          <Paper elevation={0} sx={{ ...panelSx, p: 0, overflow: 'hidden' }}>
            <ProfileFormHeader
              section={currentSection}
              metrics={currentSectionMetrics}
              accent={accent}
              isEditing={isEditing}
              saving={saving}
              hasUnsavedChanges={hasUnsavedChanges}
              onEdit={() => setIsEditing(true)}
              onCancel={handleCancelEdit}
              onSave={handleSave}
            />

            <Box sx={{ px: { xs: 2, md: 3 }, pt: 2 }}>
              <Tabs
                value={activeTab}
                onChange={(_, value) => setActiveTab(value)}
                variant="scrollable"
                scrollButtons="auto"
                sx={{
                  minHeight: 42,
                  '& .MuiTabs-indicator': { height: 3, borderRadius: 999, bgcolor: accent },
                  '& .MuiTab-root': { minHeight: 42, textTransform: 'none', fontWeight: 850, color: MUTED },
                  '& .Mui-selected': { color: `${accent} !important` },
                }}
              >
                {sections.map((section, index) => (
                  <Tab
                    key={section.key}
                    label={
                      <Stack direction="row" spacing={1} alignItems="center">
                        <span>{section.label}</span>
                        {sectionMetrics[index]?.changed > 0 && (
                          <Box component="span" sx={changedBadgeSx}>
                            {sectionMetrics[index].changed}
                          </Box>
                        )}
                      </Stack>
                    }
                  />
                ))}
              </Tabs>
            </Box>

            <Divider sx={{ mt: 1, borderColor: 'rgba(148, 163, 184, 0.18)' }} />

            <Box sx={{ p: { xs: 2, md: 3 } }}>
              {hasUnsavedChanges && (
                <Alert severity="warning" icon={<AlertCircle size={18} />} sx={{ mb: 2.5, borderRadius: 3 }}>
                  Ada {changedKeys.length} perubahan belum disimpan. Simpan sebelum meninggalkan halaman.
                </Alert>
              )}

              {currentSection ? (
                <Grid container spacing={2.3}>
                  {(currentSection.fields || []).map((field) => (
                    <Grid item xs={12} md={field.type === 'textarea' || field.type === 'array' ? 12 : 6} key={field.key}>
                      <Box sx={{ position: 'relative' }}>
                        {renderField(field)}
                        {(field.editable === false || field.key.startsWith('active_class.')) && (
                          <Box sx={readonlyLockSx}>
                            <Lock size={14} />
                          </Box>
                        )}
                      </Box>
                    </Grid>
                  ))}
                </Grid>
              ) : (
                <Alert severity="info" sx={{ borderRadius: 3 }}>
                  Skema data belum tersedia.
                </Alert>
              )}
            </Box>
          </Paper>
        </Grid>
      </Grid>

      <DocumentsDialog
        open={documentsOpen}
        onClose={() => setDocumentsOpen(false)}
        accent={accent}
        profileType={profileData?.profile_type}
        documents={documents}
        loading={documentsLoading}
        error={documentsError}
        nextcloudConfigured={nextcloudConfigured}
        uploading={uploadingDocument}
        onUpload={handleDocumentUpload}
        onDelete={handleDocumentDelete}
      />
    </Stack>
  );
};

const panelSx = {
  p: { xs: 2, md: 2.5 },
  borderRadius: 5,
  border: `1px solid ${PANEL_BORDER}`,
  background: 'linear-gradient(180deg, #ffffff 0%, #fbfdff 100%)',
  boxShadow: '0 18px 48px rgba(15, 23, 42, 0.08)',
};

const changedBadgeSx = {
  minWidth: 18,
  height: 18,
  borderRadius: 999,
  px: 0.7,
  display: 'grid',
  placeItems: 'center',
  bgcolor: '#fef3c7',
  color: '#92400e',
  fontSize: 11,
  fontWeight: 950,
};

const readonlyLockSx = {
  position: 'absolute',
  top: 10,
  right: 10,
  color: '#94a3b8',
  display: 'flex',
};

const ProfileHero = ({
  common,
  profileType,
  isStudent,
  isEditing,
  hasUnsavedChanges,
  changedCount,
  primaryIdentifier,
  activeClass,
  filledSummary,
  editableCount,
  documentsCount,
  documentsLoading,
  uploadingAvatar,
  onAvatarUpload,
  onOpenDocuments,
}) => (
  <Paper
    elevation={0}
    sx={{
      position: 'relative',
      overflow: 'hidden',
      p: { xs: 2.5, md: 3.5 },
      color: '#ffffff',
      borderRadius: 5,
      background: PROFILE_GRADIENT,
      boxShadow: '0 24px 70px rgba(8, 47, 73, 0.22)',
      '&::before': {
        content: '""',
        position: 'absolute',
        width: 320,
        height: 320,
        right: -120,
        top: -130,
        borderRadius: '50%',
        background: 'rgba(255, 255, 255, 0.14)',
      },
      '&::after': {
        content: '""',
        position: 'absolute',
        width: 210,
        height: 210,
        right: 90,
        bottom: -140,
        borderRadius: '50%',
        background: 'rgba(245, 158, 11, 0.28)',
      },
    }}
  >
    <Stack
      direction={{ xs: 'column', lg: 'row' }}
      spacing={3}
      alignItems={{ xs: 'stretch', lg: 'center' }}
      sx={{ position: 'relative', zIndex: 1 }}
    >
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2.5} alignItems={{ xs: 'flex-start', sm: 'center' }} sx={{ flex: 1 }}>
        <Box sx={{ position: 'relative' }}>
          <Avatar
            src={common.foto_profil_url || undefined}
            sx={{
              width: { xs: 92, md: 112 },
              height: { xs: 92, md: 112 },
              fontSize: 36,
              fontWeight: 900,
              border: '4px solid rgba(255, 255, 255, 0.55)',
              bgcolor: 'rgba(255, 255, 255, 0.16)',
              boxShadow: '0 18px 40px rgba(2, 6, 23, 0.22)',
            }}
          >
            {initials(common.nama_lengkap || common.username)}
          </Avatar>
          <Button
            component="label"
            variant="contained"
            size="small"
            disabled={uploadingAvatar}
            sx={{
              position: 'absolute',
              right: -8,
              bottom: -8,
              minWidth: 0,
              width: 42,
              height: 42,
              borderRadius: '50%',
              bgcolor: '#ffffff',
              color: '#0f766e',
              boxShadow: '0 12px 28px rgba(15, 23, 42, 0.24)',
              '&:hover': { bgcolor: '#ecfeff' },
            }}
          >
            {uploadingAvatar ? <CircularProgress size={16} /> : <Camera size={18} />}
            <input hidden type="file" accept="image/png,image/jpeg,image/jpg" onChange={onAvatarUpload} />
          </Button>
        </Box>

        <Stack spacing={1.2}>
          <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Chip
              icon={<Sparkles size={14} />}
              label={profileTypeLabel[profileType] || 'Profil Pengguna'}
              sx={{
                color: '#ffffff',
                bgcolor: 'rgba(255, 255, 255, 0.16)',
                border: '1px solid rgba(255, 255, 255, 0.24)',
                fontWeight: 800,
                '& .MuiChip-icon': { color: '#ffffff' },
              }}
            />
            {isEditing && (
              <Chip
                label={hasUnsavedChanges ? `${changedCount} perubahan belum disimpan` : 'Mode edit aktif'}
                sx={{ color: '#78350f', bgcolor: '#fef3c7', fontWeight: 800 }}
              />
            )}
          </Stack>

          <Typography variant="h3" sx={{ fontSize: { xs: 28, md: 38 }, fontWeight: 900, letterSpacing: '-0.04em' }}>
            {common.nama_lengkap || '-'}
          </Typography>
          <Typography sx={{ color: 'rgba(255, 255, 255, 0.82)', fontWeight: 700 }}>
            {primaryIdentifier}
          </Typography>
          {isStudent && hasDisplayValue(activeClass.nama_kelas) && (
            <Typography sx={{ color: 'rgba(255, 255, 255, 0.74)', fontSize: 13, fontWeight: 700 }}>
              Kelas aktif {activeClass.nama_kelas}
              {hasDisplayValue(activeClass.tahun_ajaran_nama) ? ` | ${activeClass.tahun_ajaran_nama}` : ''}
            </Typography>
          )}
        </Stack>
      </Stack>

      <Stack spacing={1.5} sx={{ minWidth: { xs: '100%', lg: 280 } }}>
        <Stack direction="row" spacing={1.2}>
          <ProfileStat label="Kelengkapan" value={`${filledSummary.percent}%`} />
          <ProfileStat label="Editable" value={editableCount} />
        </Stack>
        <Box>
          <Stack direction="row" justifyContent="space-between" sx={{ mb: 0.8 }}>
            <Typography sx={{ color: 'rgba(255,255,255,0.82)', fontSize: 12, fontWeight: 800 }}>
              Data terisi
            </Typography>
            <Typography sx={{ color: 'rgba(255,255,255,0.82)', fontSize: 12, fontWeight: 800 }}>
              {filledSummary.filled}/{filledSummary.total}
            </Typography>
          </Stack>
          <LinearProgress
            variant="determinate"
            value={filledSummary.percent}
            sx={{
              height: 9,
              borderRadius: 999,
              bgcolor: 'rgba(255,255,255,0.22)',
              '& .MuiLinearProgress-bar': { borderRadius: 999, bgcolor: '#fbbf24' },
            }}
          />
        </Box>
        <Button
          variant="contained"
          startIcon={<FileText size={17} />}
          onClick={onOpenDocuments}
          sx={{
            justifyContent: 'space-between',
            borderRadius: 3,
            px: 1.7,
            py: 1.15,
            bgcolor: '#ffffff',
            color: '#0f766e',
            textTransform: 'none',
            fontWeight: 950,
            boxShadow: '0 14px 34px rgba(2, 6, 23, 0.18)',
            '&:hover': { bgcolor: '#ecfeff' },
          }}
        >
          <span>Dokumen Digital</span>
          <Box
            component="span"
            sx={{
              ml: 1.5,
              minWidth: 28,
              height: 24,
              px: 0.9,
              borderRadius: 999,
              display: 'inline-grid',
              placeItems: 'center',
              bgcolor: '#0f766e',
              color: '#ffffff',
              fontSize: 12,
              fontWeight: 950,
            }}
          >
            {documentsLoading ? '...' : documentsCount}
          </Box>
        </Button>
      </Stack>
    </Stack>
  </Paper>
);

const ProfileFormHeader = ({
  section,
  metrics,
  accent,
  isEditing,
  saving,
  hasUnsavedChanges,
  onEdit,
  onCancel,
  onSave,
}) => (
  <Box sx={{ px: { xs: 2, md: 3 }, pt: { xs: 2, md: 2.5 } }}>
    <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={2}>
      <Stack spacing={0.8}>
        <Stack direction="row" spacing={1} alignItems="center">
          <Box sx={{ width: 38, height: 38, borderRadius: 3, display: 'grid', placeItems: 'center', bgcolor: `${accent}14`, color: accent }}>
            <ClipboardList size={19} />
          </Box>
          <Box>
            <Typography variant="h5" fontWeight={950} color={INK}>
              {section?.label || 'Data Profil'}
            </Typography>
            <Typography variant="body2" color={MUTED} fontWeight={700}>
              {metrics.editable} field dapat diedit, {Math.max((metrics.total || 0) - (metrics.editable || 0), 0)} readonly
            </Typography>
          </Box>
        </Stack>
        <LinearProgress
          variant="determinate"
          value={metrics.percent}
          sx={{
            width: { xs: '100%', sm: 280 },
            height: 8,
            borderRadius: 999,
            bgcolor: '#e2e8f0',
            '& .MuiLinearProgress-bar': { borderRadius: 999, bgcolor: accent },
          }}
        />
      </Stack>

      <Stack direction="row" spacing={1} alignItems="center" justifyContent={{ xs: 'flex-start', md: 'flex-end' }}>
        {isEditing ? (
          <>
            <Button variant="outlined" color="inherit" startIcon={<X size={16} />} onClick={onCancel}>
              Batal
            </Button>
            <Button
              variant="contained"
              startIcon={saving ? <RefreshCw size={16} className="animate-spin" /> : <Save size={16} />}
              onClick={onSave}
              disabled={saving || !hasUnsavedChanges}
              sx={{ bgcolor: accent, '&:hover': { bgcolor: accent } }}
            >
              {saving ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </>
        ) : (
          <Button variant="contained" startIcon={<Pencil size={16} />} onClick={onEdit} sx={{ bgcolor: accent, '&:hover': { bgcolor: accent } }}>
            Edit Profil
          </Button>
        )}
      </Stack>
    </Stack>
  </Box>
);

const ProfileStat = ({ label, value }) => (
  <Box sx={{ flex: 1, p: 1.4, borderRadius: 3, bgcolor: 'rgba(255, 255, 255, 0.14)', border: '1px solid rgba(255, 255, 255, 0.18)' }}>
    <Typography sx={{ color: 'rgba(255,255,255,0.72)', fontSize: 11, fontWeight: 800, textTransform: 'uppercase', letterSpacing: 0.5 }}>
      {label}
    </Typography>
    <Typography sx={{ color: '#ffffff', fontSize: 22, fontWeight: 950 }}>
      {value}
    </Typography>
  </Box>
);

const PanelHeader = ({ icon, title, subtitle, accent }) => (
  <Stack direction="row" spacing={1.5} alignItems="center">
    <Box sx={{ width: 40, height: 40, borderRadius: 3, display: 'grid', placeItems: 'center', bgcolor: `${accent}14`, color: accent }}>
      {icon}
    </Box>
    <Box>
      <Typography fontWeight={950} color={INK}>{title}</Typography>
      <Typography variant="caption" color={MUTED} fontWeight={700}>{subtitle}</Typography>
    </Box>
  </Stack>
);

const SummaryRow = ({ icon, label, value }) => (
  <Stack direction="row" spacing={1.3} alignItems="flex-start" sx={{ p: 1.4, borderRadius: 3, bgcolor: SOFT_BLUE, border: '1px solid rgba(148, 163, 184, 0.16)' }}>
    <Box sx={{ color: '#0f766e', mt: 0.2 }}>{icon}</Box>
    <Box sx={{ minWidth: 0 }}>
      <Typography variant="caption" color={MUTED} fontWeight={800}>{label}</Typography>
      <Typography fontSize={13} fontWeight={850} color={INK} sx={{ wordBreak: 'break-word' }}>{value}</Typography>
    </Box>
  </Stack>
);

const SectionButton = ({ section, index, selected, metrics, onClick }) => {
  const tone = SECTION_TONES[index % SECTION_TONES.length];

  return (
    <Button
      fullWidth
      onClick={onClick}
      sx={{
        justifyContent: 'space-between',
        textTransform: 'none',
        borderRadius: 3,
        px: 1.5,
        py: 1.2,
        color: selected ? '#ffffff' : INK,
        bgcolor: selected ? tone : 'rgba(248, 250, 252, 0.9)',
        border: `1px solid ${selected ? tone : 'rgba(148, 163, 184, 0.22)'}`,
        '&:hover': { bgcolor: selected ? tone : 'rgba(15, 118, 110, 0.08)' },
      }}
    >
      <Stack direction="row" spacing={1.2} alignItems="center">
        <Box sx={{ width: 9, height: 9, borderRadius: '50%', bgcolor: selected ? '#ffffff' : tone }} />
        <Typography fontWeight={900} fontSize={13}>{section.label}</Typography>
      </Stack>
      <Stack direction="row" spacing={0.7} alignItems="center">
        {metrics.changed > 0 && (
          <Chip
            size="small"
            label={metrics.changed}
            sx={{ height: 20, color: selected ? tone : '#92400e', bgcolor: selected ? '#ffffff' : '#fef3c7', fontWeight: 900 }}
          />
        )}
        <Typography fontSize={12} fontWeight={800} sx={{ opacity: selected ? 0.95 : 0.62 }}>
          {metrics.filled}/{metrics.total}
        </Typography>
      </Stack>
    </Button>
  );
};

const DocumentsDialog = ({
  open,
  onClose,
  accent,
  profileType,
  documents,
  loading,
  error,
  nextcloudConfigured,
  uploading,
  onUpload,
  onDelete,
}) => {
  const documentGroups = getDocumentGroupsForProfile(profileType);
  const scopeLabel = profileType === 'siswa' ? 'Siswa' : 'Pegawai';

  return (
    <Dialog
      open={open}
      onClose={onClose}
      fullWidth
      maxWidth="xl"
      PaperProps={{
        sx: {
          borderRadius: 2,
          overflow: 'hidden',
          boxShadow: '0 28px 80px rgba(15, 23, 42, 0.24)',
        },
      }}
    >
      <DialogTitle sx={{ px: 2.4, py: 1.7, borderBottom: '1px solid #d7d7d7', bgcolor: '#ffffff' }}>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ xs: 'flex-start', sm: 'center' }} justifyContent="space-between">
          <Box>
            <Typography variant="h6" fontWeight={850} color={INK}>
              Arsip Dokumen Digital {scopeLabel}
            </Typography>
            <Typography variant="body2" color={MUTED} fontWeight={650}>
              Slot dokumen mengikuti scope SMA untuk {scopeLabel.toLowerCase()}: wajib, opsional, dan kondisional. File tersimpan ke Nextcloud saat upload.
            </Typography>
          </Box>
          <Chip
            icon={<FileText size={15} />}
            label={`${documents.length} file tersimpan`}
            sx={{ color: accent, bgcolor: `${accent}14`, fontWeight: 850, '& .MuiChip-icon': { color: accent } }}
          />
        </Stack>
      </DialogTitle>

      <DialogContent sx={{ p: 0, bgcolor: '#f7f7f7' }}>
        <Box sx={{ px: 2.4, py: 1.5 }}>
          {!nextcloudConfigured && (
            <Alert severity="warning" icon={<AlertCircle size={18} />} sx={{ mb: 1.4, borderRadius: 1 }}>
              Nextcloud belum aktif di server. Isi konfigurasi `NEXTCLOUD_*` agar upload berjalan.
            </Alert>
          )}

          {error && (
            <Alert severity="warning" icon={<AlertCircle size={18} />} sx={{ mb: 1.4, borderRadius: 1 }}>
              {error}
            </Alert>
          )}

          {loading ? (
            <Stack direction="row" spacing={1.2} alignItems="center" sx={{ p: 2, color: MUTED }}>
              <CircularProgress size={18} sx={{ color: accent }} />
              <Typography variant="body2" fontWeight={800}>Memuat dokumen...</Typography>
            </Stack>
          ) : (
            <Stack spacing={1.3}>
              {documentGroups.map((group) => (
                <DocumentGroupTable
                  key={group.title}
                  group={group}
                  documents={documents}
                  accent={accent}
                  uploading={uploading}
                  nextcloudConfigured={nextcloudConfigured}
                  onUpload={onUpload}
                  onDelete={onDelete}
                />
              ))}
              <UnmappedDocumentTable
                documentGroups={documentGroups}
                documents={documents}
                accent={accent}
                uploading={uploading}
                nextcloudConfigured={nextcloudConfigured}
                onUpload={onUpload}
                onDelete={onDelete}
              />
            </Stack>
          )}
        </Box>
      </DialogContent>

      <DialogActions sx={{ px: 2.4, py: 1.5, borderTop: '1px solid #d7d7d7', bgcolor: '#ffffff' }}>
        <Button onClick={onClose} variant="outlined" color="inherit" sx={{ borderRadius: 1, textTransform: 'none', fontWeight: 850 }}>
          Tutup
        </Button>
      </DialogActions>
    </Dialog>
  );
};

const DocumentGroupTable = ({
  group,
  documents,
  accent,
  uploading,
  nextcloudConfigured,
  onUpload,
  onDelete,
}) => (
  <Box sx={{ border: '1px solid #cfcfcf', bgcolor: '#ffffff' }}>
    <Typography sx={{ px: 0, py: 0.9, fontSize: 18, fontWeight: 500, color: INK }}>
      {group.title}
    </Typography>
    <TableContainer sx={{ borderTop: '1px solid #d9d9d9', overflowX: 'auto' }}>
      <Table size="small" sx={archiveTableSx}>
        <TableHead>
          <TableRow>
            <TableCell sx={{ width: 50, textAlign: 'center' }}>No.</TableCell>
            <TableCell>Nama File</TableCell>
            <TableCell>File</TableCell>
            <TableCell>Keterangan</TableCell>
            <TableCell sx={{ width: 180 }}>Tanggal Upload</TableCell>
            <TableCell sx={{ width: 320, textAlign: 'center' }}>Pilihan</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {group.rows.map((row, index) => {
            const document = findDocumentForSlot(documents, row);

            return (
              <DocumentArchiveRow
                key={row.type}
                index={index}
                slot={row}
                document={document}
                accent={accent}
                uploading={uploading}
                nextcloudConfigured={nextcloudConfigured}
                onUpload={onUpload}
                onDelete={onDelete}
              />
            );
          })}
        </TableBody>
      </Table>
    </TableContainer>
  </Box>
);

const DocumentArchiveRow = ({
  index,
  slot,
  document,
  accent,
  uploading,
  nextcloudConfigured,
  onUpload,
  onDelete,
}) => {
  const hasFile = Boolean(document);
  const fileUrl = document?.remote_url || '';

  return (
    <TableRow hover>
      <TableCell align="center">{index + 1}</TableCell>
      <TableCell sx={{ minWidth: 230 }}>{slot.label}</TableCell>
      <TableCell sx={{ minWidth: 310 }}>
        <Stack direction="row" spacing={1.2} alignItems="center">
          <Box sx={{ color: hasFile ? '#64748b' : '#cbd5e1', display: 'flex' }}>
            <FileText size={28} strokeWidth={1.4} />
          </Box>
          {hasFile ? (
            fileUrl ? (
              <Typography
                component="a"
                href={fileUrl}
                target="_blank"
                rel="noopener noreferrer"
                sx={{ color: '#1d5f9f', textDecoration: 'none', fontSize: 13, '&:hover': { textDecoration: 'underline' } }}
              >
                {document.original_name || document.title || slot.label}
              </Typography>
            ) : (
              <Typography sx={{ color: '#1d5f9f', fontSize: 13 }}>
                {document.original_name || document.title || slot.label}
              </Typography>
            )
          ) : (
            <Typography color={MUTED} fontSize={13}>-</Typography>
          )}
        </Stack>
      </TableCell>
      <TableCell sx={{ minWidth: 240 }}>
        <Stack spacing={0.55} alignItems="flex-start">
          <RequirementChip requirement={slot.requirement} />
          <Typography fontSize={12.5} color={MUTED} lineHeight={1.35}>
            {slot.note || document?.title || slot.label}
          </Typography>
        </Stack>
      </TableCell>
      <TableCell sx={{ whiteSpace: 'nowrap' }}>{hasFile ? formatDateTime(document.created_at) : 'Belum Upload'}</TableCell>
      <TableCell>
        <Stack direction="row" spacing={0.6} alignItems="center" justifyContent="center" flexWrap="wrap" useFlexGap>
          {hasFile && (
            <>
              <Button
                component="a"
                href={fileUrl || undefined}
                target="_blank"
                rel="noopener noreferrer"
                disabled={!fileUrl}
                size="small"
                startIcon={<Search size={13} />}
                sx={{ ...archiveActionButtonSx, bgcolor: '#6aaed6', '&:hover': { bgcolor: '#4e99c2' } }}
              >
                Lihat File
              </Button>
              <Button
                component="a"
                href={fileUrl || undefined}
                download
                disabled={!fileUrl}
                size="small"
                startIcon={<Download size={13} />}
                sx={{ ...archiveActionButtonSx, bgcolor: '#6aa06a', '&:hover': { bgcolor: '#558d55' } }}
              >
                Download
              </Button>
              <Button
                size="small"
                startIcon={<Trash2 size={13} />}
                onClick={() => onDelete(document.id)}
                sx={{ ...archiveActionButtonSx, bgcolor: '#c95049', '&:hover': { bgcolor: '#ad3d37' } }}
              >
                Delete
              </Button>
            </>
          )}
          <Button
            component="label"
            size="small"
            startIcon={uploading ? <RefreshCw size={13} className="animate-spin" /> : <Pencil size={13} />}
            disabled={!nextcloudConfigured || uploading}
            sx={{ ...archiveActionButtonSx, bgcolor: '#f4a64d', '&:hover': { bgcolor: '#e19034' } }}
          >
            Edit
            <input
              hidden
              type="file"
              accept={ACCEPTED_DOCUMENT_INPUT}
              onChange={(event) => onUpload(event, {
                documentType: slot.type,
                documentTitle: slot.label,
                replacementTypes: [slot.type, ...(slot.aliases || [])],
              })}
            />
          </Button>
        </Stack>
      </TableCell>
    </TableRow>
  );
};

const UnmappedDocumentTable = ({ documentGroups, documents, accent, uploading, nextcloudConfigured, onUpload, onDelete }) => {
  const mappedTypes = new Set(documentGroups.flatMap((group) => group.rows.flatMap((row) => [row.type, ...(row.aliases || [])])));
  const unmapped = documents.filter((document) => !mappedTypes.has(document.document_type));

  if (unmapped.length === 0) {
    return null;
  }

  return (
    <Box sx={{ border: '1px solid #cfcfcf', bgcolor: '#ffffff' }}>
      <Typography sx={{ px: 0, py: 0.9, fontSize: 18, fontWeight: 500, color: INK }}>
        Dokumen Lainnya
      </Typography>
      <TableContainer sx={{ borderTop: '1px solid #d9d9d9', overflowX: 'auto' }}>
        <Table size="small" sx={archiveTableSx}>
          <TableHead>
            <TableRow>
              <TableCell sx={{ width: 50, textAlign: 'center' }}>No.</TableCell>
              <TableCell>Nama File</TableCell>
              <TableCell>File</TableCell>
              <TableCell>Keterangan</TableCell>
              <TableCell sx={{ width: 180 }}>Tanggal Upload</TableCell>
              <TableCell sx={{ width: 250, textAlign: 'center' }}>Pilihan</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {unmapped.map((document, index) => (
              <DocumentArchiveRow
                key={document.id}
                index={index}
                slot={{
                  type: document.document_type,
                  label: document.document_type_label || document.title || 'Dokumen Lainnya',
                }}
                document={document}
                accent={accent}
                uploading={uploading}
                nextcloudConfigured={nextcloudConfigured}
                onUpload={onUpload}
                onDelete={onDelete}
              />
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Box>
  );
};

const RequirementChip = ({ requirement = 'optional' }) => {
  const meta = documentRequirementMeta[requirement] || documentRequirementMeta.optional;

  return (
    <Chip
      size="small"
      label={meta.label}
      sx={{
        height: 21,
        borderRadius: 1,
        color: meta.color,
        bgcolor: meta.background,
        border: `1px solid ${meta.border}`,
        fontSize: 11,
        fontWeight: 900,
        '& .MuiChip-label': {
          px: 0.85,
        },
      }}
    />
  );
};

const archiveTableSx = {
  tableLayout: 'fixed',
  '& th': {
    bgcolor: '#efefef',
    color: '#555',
    fontSize: 13,
    fontWeight: 800,
    border: '1px solid #d8d8d8',
    py: 1.15,
  },
  '& td': {
    border: '1px solid #d8d8d8',
    color: '#1f2937',
    fontSize: 13,
    py: 1.05,
    verticalAlign: 'middle',
  },
};

const archiveActionButtonSx = {
  minHeight: 30,
  px: 1,
  borderRadius: 0,
  color: '#ffffff',
  fontSize: 12,
  fontWeight: 800,
  textTransform: 'none',
  '&.Mui-disabled': {
    color: 'rgba(255,255,255,0.82)',
    bgcolor: '#cbd5e1',
  },
};

const getDocumentGroupsForProfile = (profileType) => (
  DOCUMENT_GROUPS_BY_PROFILE[profileType] || DOCUMENT_GROUPS_BY_PROFILE.pegawai
);

const findDocumentForSlot = (documents, slot) => {
  const acceptedTypes = [slot.type, ...(slot.aliases || [])];

  return documents
    .filter((document) => acceptedTypes.includes(document.document_type))
    .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())[0] || null;
};

const buildFieldHelper = (field, isReadonly, fieldChanged) => {
  if (fieldChanged) return 'Berubah, belum disimpan';
  if (field.key.startsWith('active_class.')) return 'Data kelas aktif dari sistem';
  if (isReadonly && field.editable === false) return 'Readonly';
  if (!isReadonly && USER_KEYS.has(field.key)) return 'Data utama akun';
  return ' ';
};

const hasDisplayValue = (value) => {
  if (value === null || value === undefined) return false;
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object') return Object.keys(value).length > 0;
  return String(value).trim() !== '';
};

const displayValue = (value) => {
  const text = String(value ?? '').trim();
  return text || '-';
};

const formatDateTime = (value) => {
  if (!value) return '-';

  try {
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(value));
  } catch (_error) {
    return String(value);
  }
};

const initials = (value) => {
  const text = String(value || 'U').trim();
  if (!text) return 'U';
  return text.split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
};

const buildAddressSummary = (data) => {
  const parts = [
    data.alamat,
    data.alamat_jalan,
    data.alamat_domisili,
    data.kelurahan,
    data.kecamatan,
    data.kota_kabupaten,
    data.provinsi,
  ].map((item) => String(item ?? '').trim()).filter(Boolean);

  return parts.length ? parts.slice(0, 3).join(', ') : '-';
};

export default DataPribadiSaya;

import React, { useEffect, useState } from 'react';
import { useSnackbar } from 'notistack';
import { AlertTriangle, Database, RefreshCw, Save, Search, Wifi } from 'lucide-react';
import { dapodikAPI, tahunAjaranAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';

const initialForm = {
  base_url: 'http://182.253.36.196:5885',
  npsn: '',
  probe_path: '/',
  api_token: '',
  clear_api_token: false,
};

const stagingSources = [
  { id: 'school', label: 'Sekolah' },
  { id: 'dapodik_users', label: 'Pengguna' },
  { id: 'students', label: 'Siswa' },
  { id: 'employees', label: 'GTK' },
  { id: 'classes', label: 'Rombel' },
];

const initialStagingProgress = () => ({
  status: 'idle',
  percent: 0,
  batch: null,
  steps: stagingSources.map((source) => ({
    ...source,
    status: 'queued',
    row_count: 0,
    records_stored: 0,
    message: 'Menunggu giliran.',
  })),
  finalize: {
    label: 'Finalisasi Mapping',
    status: 'queued',
    message: 'Menunggu data staging selesai.',
  },
});

const calculateStagingPercent = (steps, finalizeStatus) => {
  const doneSources = steps.filter((step) => step.status === 'completed' || step.status === 'failed').length;
  const doneFinalize = finalizeStatus === 'completed' || finalizeStatus === 'failed' ? 1 : 0;
  const total = steps.length + 1;

  return Math.floor(((doneSources + doneFinalize) / total) * 100);
};

const reviewEntityOptions = [
  { value: '', label: 'Semua' },
  { value: 'student', label: 'Siswa' },
  { value: 'employee', label: 'GTK' },
  { value: 'class', label: 'Kelas' },
];

const reviewConfidenceOptions = [
  { value: 'problem', label: 'Masalah' },
  { value: '', label: 'Semua status' },
  { value: 'exact', label: 'Exact' },
  { value: 'probable', label: 'Probable' },
  { value: 'conflict', label: 'Conflict' },
  { value: 'unmatched', label: 'Unmatched' },
];

const applyEntityOptions = [
  { value: '', label: 'Siswa + GTK' },
  { value: 'student', label: 'Siswa' },
  { value: 'employee', label: 'GTK' },
];

const dataPanelTabs = [
  { value: 'users', label: 'Pengguna' },
  { value: 'classes', label: 'Kelas' },
];

const classPanelTabs = [
  { value: 'master', label: 'Master Kelas' },
  { value: 'memberships', label: 'Anggota Kelas' },
];

const DAPODIK_ACTION_PREVIEW_LIMIT = 5000;
const DAPODIK_INPUT_CHUNK_SIZE = 100;
const DAPODIK_APPLY_CHUNK_SIZE = 100;
const DAPODIK_CLASS_CHUNK_SIZE = 25;
const DAPODIK_CLASS_MEMBERSHIP_CHUNK_SIZE = 10;
const DAPODIK_TABLE_PAGE_SIZE = 25;
const PROCESS_ITEM_PREVIEW_LIMIT = 10;
const CONFIRMATION_ITEM_PREVIEW_LIMIT = 20;
const MANAGEABLE_TAHUN_AJARAN_STATUSES = new Set(['draft', 'preparation', 'active']);

const entityLabelMap = {
  student: 'Siswa',
  employee: 'GTK',
  class: 'Kelas',
  class_membership: 'Anggota Kelas',
};

const identifierLabelMap = {
  nis: 'NIS',
  nisn: 'NISN',
  nik: 'NIK',
  peserta_didik_id: 'Peserta Didik ID',
  anggota_rombel_id: 'Anggota Rombel ID',
  registrasi_id: 'Registrasi ID',
  rombel: 'Kelas',
  nip: 'NIP',
  nuptk: 'NUPTK',
  role: 'Role',
  tingkat: 'Tingkat',
  jurusan: 'Jurusan',
  anggota: 'Anggota',
};

const entityLabel = (entityType) => entityLabelMap[entityType] || entityType || '-';

const summarizeIdentifiers = (identifiers = {}) => (
  Object.entries(identifiers || {})
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${identifierLabelMap[key] || key}: ${value}`)
);

const summarizeClassTarget = (target = {}) => ([
  target?.tingkat?.nama ? `Tingkat: ${target.tingkat.nama}` : null,
  target?.tahun_ajaran?.nama ? `Target TA: ${target.tahun_ajaran.nama}` : null,
  target?.dapodik_tahun_ajaran ? `Dapodik TA: ${target.dapodik_tahun_ajaran}` : null,
  target?.dapodik_semester ? `Semester: ${target.dapodik_semester}` : null,
  target?.jurusan ? `Jurusan: ${target.jurusan}` : null,
  target?.wali_kelas?.nama ? `Wali: ${target.wali_kelas.nama}` : null,
  Number.isFinite(target?.member_count) ? `Anggota: ${target.member_count}` : null,
].filter(Boolean));

const summarizeClassAssignment = (assignment = {}) => ([
  assignment?.class_name ? `Kelas aktif: ${assignment.class_name}` : null,
  assignment?.status ? `Status: ${assignment.status}` : null,
].filter(Boolean));

const paginateRows = (rows = [], page = 1, pageSize = DAPODIK_TABLE_PAGE_SIZE) => {
  const normalizedPage = Math.max(1, Number(page) || 1);
  const start = (normalizedPage - 1) * pageSize;

  return rows.slice(start, start + pageSize);
};

const previewDetailLines = (item = {}) => ([
  ...summarizeIdentifiers(item.identifiers),
  ...summarizeClassTarget(item.target),
  item.account?.username ? `Username: ${item.account.username}` : null,
  item.account?.email ? `Email: ${item.account.email}` : null,
  item.class?.name ? `Target Kelas: ${item.class.name}` : null,
].filter(Boolean));

const previewNamesFromItems = (items, action) => (
  (items || [])
    .filter((item) => !action || item.action === action)
    .map((item) => ({
      key: item.key || item.mapping_id || item.class_mapping_id || `${item.entity_type}-${item.dapodik_id || item.class?.dapodik_id}-${item.name}`,
      name: item.name || item.local?.name || '-',
      meta: `${entityLabel(item.entity_type)} | Dapodik ${item.dapodik_id || item.class?.dapodik_id || '-'}`,
      detailLines: previewDetailLines(item),
    }))
);

const previewNames = (preview, action) => previewNamesFromItems(preview?.items || [], action);

const classNamesFromItems = (items = []) => (
  items.map((item) => ({
    key: item.key || item.mapping_id || item.class_mapping_id || `${item.entity_type}-${item.dapodik_id || item.class?.dapodik_id}-${item.name}`,
    name: item.name || item.class?.name || '-',
    meta: `${entityLabel(item.entity_type)} | Dapodik ${item.dapodik_id || item.class?.dapodik_id || '-'}`,
    detailLines: previewDetailLines(item),
  }))
);

const classNames = (preview, predicate) => classNamesFromItems((preview?.items || []).filter((item) => predicate(item)));

const resultNamesFromItems = (items, status) => (
  (items || [])
    .filter((item) => !status || item.status === status)
    .map((item) => ({
      key: item.key || item.mapping_id || item.class_mapping_id || `${item.entity_type}-${item.dapodik_id || item.class?.dapodik_id}-${item.name}`,
      name: item.name || item.created?.username || '-',
      meta: [
        entityLabel(item.entity_type),
        item.created?.user_id ? `SIAPS #${item.created.user_id}` : null,
        item.siaps_user_id ? `SIAPS #${item.siaps_user_id}` : null,
        item.siaps_class_id ? `Kelas #${item.siaps_class_id}` : null,
        item.created?.role ? `Role ${item.created.role}` : null,
        item.local?.roles?.length ? item.local.roles.join(', ') : null,
        item.class?.local_name ? `Kelas ${item.class.local_name}` : null,
      ].filter(Boolean).join(' | '),
      detailLines: [
        ...summarizeIdentifiers(item.identifiers),
        ...summarizeClassTarget(item.target),
        ...summarizeClassAssignment(item.current_assignment),
        item.created?.username ? `Username: ${item.created.username}` : null,
        item.created?.email ? `Email: ${item.created.email}` : null,
        item.created?.nama_kelas ? `Nama Kelas: ${item.created.nama_kelas}` : null,
        ...(item.notes || []),
      ].filter(Boolean),
    }))
);

const resultNames = (result, status) => resultNamesFromItems(result?.items || [], status);

const splitIntoChunks = (items, chunkSize) => {
  const chunks = [];

  for (let index = 0; index < items.length; index += chunkSize) {
    chunks.push(items.slice(index, index + chunkSize));
  }

  return chunks;
};

const uniqueIds = (ids = []) => [...new Set((ids || []).filter(Boolean))];

const extractApiRows = (response) => {
  const payload = response?.data?.data;
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
};

const isManageableTahunAjaran = (item) => (
  MANAGEABLE_TAHUN_AJARAN_STATUSES.has(String(item?.status || '').toLowerCase())
);

const resolvePreferredTahunAjaranId = (rows = []) => {
  const manageableRows = rows.filter(isManageableTahunAjaran);
  const candidateRows = manageableRows.length ? manageableRows : rows;
  const activeRow = candidateRows.find((item) => item?.is_active || String(item?.status || '').toLowerCase() === 'active');
  const picked = activeRow || candidateRows[0] || null;

  return picked?.id ? String(picked.id) : '';
};

const mergeNumericTrees = (target, source) => {
  const result = { ...(target || {}) };

  Object.entries(source || {}).forEach(([key, value]) => {
    if (typeof value === 'number') {
      result[key] = (result[key] || 0) + value;
      return;
    }

    if (Array.isArray(value)) {
      result[key] = value;
      return;
    }

    if (value && typeof value === 'object') {
      result[key] = mergeNumericTrees(result[key] || {}, value);
      return;
    }

    result[key] = value;
  });

  return result;
};

const mergeChunkedResult = (current, incoming) => ({
  ...(current || {}),
  ...(incoming || {}),
  batch: incoming?.batch || current?.batch || null,
  filters: incoming?.filters || current?.filters || null,
  policy: incoming?.policy || current?.policy || null,
  summary: mergeNumericTrees(current?.summary || {}, incoming?.summary || {}),
  items: [...(current?.items || []), ...(incoming?.items || [])],
  has_more: false,
});

const translateProcessStage = (stage) => {
  const labels = {
    create_user: 'Buat akun user',
    create_student_detail: 'Simpan detail siswa',
    create_employee_detail: 'Simpan detail GTK',
    assign_role: 'Pasang role',
    save_mapping: 'Simpan mapping hasil input',
    update_class: 'Update master kelas',
    create_class: 'Buat master kelas',
    save_class_mapping: 'Simpan mapping kelas',
    assign_class_member: 'Tambah anggota kelas',
    reactivate_class_member: 'Aktifkan ulang anggota kelas',
    class_sync: 'Sinkronisasi master kelas',
    class_membership_sync: 'Sinkronisasi anggota kelas',
    input_staging_batch: 'Input data baru',
    apply_staging_batch: 'Apply update data',
  };

  return labels[stage] || stage || '-';
};

const buildProcessErrorState = (error, fallbackMessage) => {
  const response = error?.response?.data || {};
  const context = response?.error_context || {};
  const details = [
    context.stage ? { label: 'Tahap', value: translateProcessStage(context.stage) } : null,
    context.entity_type ? { label: 'Entitas', value: context.entity_type === 'employee' ? 'GTK' : context.entity_type === 'student' ? 'Siswa' : context.entity_type } : null,
    context.name ? { label: 'Nama', value: context.name } : null,
    context.dapodik_id ? { label: 'Dapodik ID', value: context.dapodik_id } : null,
    context.mapping_id ? { label: 'Mapping ID', value: context.mapping_id } : null,
    context.location ? { label: 'Lokasi', value: context.location } : null,
    context.root_location ? { label: 'Akar Error', value: context.root_location } : null,
    context.detail ? { label: 'Detail Teknis', value: context.detail } : null,
  ].filter(Boolean);

  return {
    message: response?.message || response?.error || error?.message || fallbackMessage,
    details,
  };
};

const DapodikConnectionTest = () => {
  const { enqueueSnackbar } = useSnackbar();
  const [form, setForm] = useState(initialForm);
  const [hasToken, setHasToken] = useState(false);
  const [lastTest, setLastTest] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [fetchingStaging, setFetchingStaging] = useState(false);
  const [processProgress, setProcessProgress] = useState(null);
  const [stagingResult, setStagingResult] = useState(null);
  const [stagingProgress, setStagingProgress] = useState(null);
  const [reviewingStaging, setReviewingStaging] = useState(false);
  const [stagingReview, setStagingReview] = useState(null);
  const [reviewFilters, setReviewFilters] = useState({ entity_type: '', confidence: 'problem' });
  const [reviewPage, setReviewPage] = useState(1);
  const [previewingApply, setPreviewingApply] = useState(false);
  const [applyingApply, setApplyingApply] = useState(false);
  const [applyPreview, setApplyPreview] = useState(null);
  const [applyResult, setApplyResult] = useState(null);
  const [applyFilters, setApplyFilters] = useState({ entity_type: '' });
  const [applyPreviewPage, setApplyPreviewPage] = useState(1);
  const [previewingInput, setPreviewingInput] = useState(false);
  const [inputtingData, setInputtingData] = useState(false);
  const [inputPreview, setInputPreview] = useState(null);
  const [inputResult, setInputResult] = useState(null);
  const [inputFilters, setInputFilters] = useState({ entity_type: '' });
  const [inputPreviewPage, setInputPreviewPage] = useState(1);
  const [activeDataTab, setActiveDataTab] = useState('users');
  const [activeClassTab, setActiveClassTab] = useState('master');
  const [tahunAjaranOptions, setTahunAjaranOptions] = useState([]);
  const [loadingTargetYears, setLoadingTargetYears] = useState(false);
  const [targetTahunAjaranId, setTargetTahunAjaranId] = useState('');
  const [previewingClasses, setPreviewingClasses] = useState(false);
  const [syncingClasses, setSyncingClasses] = useState(false);
  const [classPreview, setClassPreview] = useState(null);
  const [classResult, setClassResult] = useState(null);
  const [classSearch, setClassSearch] = useState('');
  const [selectedClassMappingIds, setSelectedClassMappingIds] = useState([]);
  const [classPreviewPage, setClassPreviewPage] = useState(1);
  const [previewingClassMembers, setPreviewingClassMembers] = useState(false);
  const [syncingClassMembers, setSyncingClassMembers] = useState(false);
  const [classMembershipPreview, setClassMembershipPreview] = useState(null);
  const [classMembershipResult, setClassMembershipResult] = useState(null);
  const [classMembershipPage, setClassMembershipPage] = useState(1);
  const [confirmation, setConfirmation] = useState(null);

  const operationLocked = fetchingStaging || applyingApply || inputtingData || syncingClasses || syncingClassMembers;
  const currentBatchId = stagingResult?.batch?.id || stagingProgress?.batch?.id || '-';
  const connectionReady = Boolean(lastTest?.reachable && lastTest?.web_service_ready);
  const stagingPercent = fetchingStaging
    ? (stagingProgress?.percent ?? 0)
    : (stagingResult?.batch?.progress?.percentage ?? stagingProgress?.percent ?? 0);
  const exactCount = stagingReview?.summary?.by_confidence?.exact ?? 0;
  const manualReviewCount = stagingReview?.summary?.needs_manual_review ?? 0;
  const updateReadyCount = applyPreview?.summary?.update_candidates ?? 0;
  const inputReadyCount = inputPreview?.summary?.create_candidates ?? 0;
  const reviewItems = stagingReview?.items || [];
  const applyPreviewItems = applyPreview?.items || [];
  const inputPreviewItems = inputPreview?.items || [];
  const classPreviewItems = classPreview?.items || [];
  const classMembershipItems = classMembershipPreview?.items || [];
  const normalizedClassSearch = classSearch.trim().toLowerCase();
  const visibleClassItems = classPreviewItems.filter((item) => {
    if (!normalizedClassSearch) return true;

    const haystack = [
      item.name,
      item.target?.tingkat?.nama,
      item.target?.jurusan,
      item.local?.name,
      item.target?.wali_kelas?.nama,
    ].filter(Boolean).join(' ').toLowerCase();

    return haystack.includes(normalizedClassSearch);
  });
  const pagedReviewItems = paginateRows(reviewItems, reviewPage);
  const pagedApplyPreviewItems = paginateRows(applyPreviewItems, applyPreviewPage);
  const pagedInputPreviewItems = paginateRows(inputPreviewItems, inputPreviewPage);
  const pagedClassItems = paginateRows(visibleClassItems, classPreviewPage);
  const pagedClassMembershipItems = paginateRows(classMembershipItems, classMembershipPage);
  const selectedClassSet = new Set(selectedClassMappingIds);
  const selectedClassItems = classPreviewItems.filter((item) => selectedClassSet.has(item.mapping_id));
  const selectedUpdateClassItems = selectedClassItems.filter((item) => item.action === 'update_candidate');
  const selectedCreateClassItems = selectedClassItems.filter((item) => item.action === 'create_candidate');
  const selectedMembershipClassItems = selectedClassItems.filter((item) => ['update_candidate', 'no_change'].includes(item.action));
  const classUpdateReadyCount = classPreview?.summary?.update_candidates ?? 0;
  const classCreateReadyCount = classPreview?.summary?.create_candidates ?? 0;
  const classMembershipReadyCount = classMembershipPreview?.summary?.assign_candidates ?? 0;
  const manageableTahunAjaranOptions = tahunAjaranOptions.filter(isManageableTahunAjaran);
  const effectiveTahunAjaranOptions = manageableTahunAjaranOptions.length ? manageableTahunAjaranOptions : tahunAjaranOptions;
  const selectedTargetTahunAjaran = effectiveTahunAjaranOptions.find((item) => String(item.id) === String(targetTahunAjaranId)) || null;
  const targetTahunAjaranLabel = selectedTargetTahunAjaran?.nama || '-';
  const workflowSteps = activeDataTab === 'classes' ? [
    {
      step: '01',
      title: 'Ambil dan finalisasi',
      description: stagingResult?.batch
        ? `Batch ${stagingResult.batch.id} siap dipakai untuk sinkronisasi kelas.`
        : 'Mulai dari koneksi lalu ambil data Dapodik ke staging.',
      state: fetchingStaging ? 'running' : (stagingResult?.batch ? 'ready' : 'idle'),
      meta: fetchingStaging ? `${stagingPercent}% berjalan` : `${stagingResult?.batch?.status || 'belum ada batch'}`,
    },
    {
      step: '02',
      title: 'Sinkronisasi master kelas',
      description: classUpdateReadyCount + classCreateReadyCount > 0
        ? `${classUpdateReadyCount} update dan ${classCreateReadyCount} input kelas siap dipilih untuk ${targetTahunAjaranLabel}.`
        : `Belum ada preview master kelas untuk ${targetTahunAjaranLabel}.`,
      state: syncingClasses ? 'running' : (classResult || classPreview ? 'ready' : 'idle'),
      meta: classResult
        ? `${(classResult.summary?.applied_items || 0) + (classResult.summary?.created_items || 0)} kelas diproses`
        : `${selectedClassItems.length} kelas dipilih`,
    },
    {
      step: '03',
      title: 'Sinkronisasi anggota kelas',
      description: classMembershipReadyCount > 0
        ? `${classMembershipReadyCount} kandidat anggota siap diproses untuk ${targetTahunAjaranLabel}.`
        : `Belum ada preview anggota kelas untuk ${targetTahunAjaranLabel}.`,
      state: syncingClassMembers ? 'running' : (classMembershipResult || classMembershipPreview ? 'ready' : 'idle'),
      meta: classMembershipResult
        ? `${(classMembershipResult.summary?.assigned_items || 0) + (classMembershipResult.summary?.reactivated_items || 0)} anggota diproses`
        : `${selectedMembershipClassItems.length} kelas siap dipakai`,
    },
  ] : [
    {
      step: '01',
      title: 'Ambil dan finalisasi',
      description: stagingResult?.batch
        ? `Batch ${stagingResult.batch.id} siap dipakai untuk review dan aksi.`
        : 'Mulai dari koneksi lalu ambil data Dapodik ke staging.',
      state: fetchingStaging ? 'running' : (stagingResult?.batch ? 'ready' : 'idle'),
      meta: fetchingStaging ? `${stagingPercent}% berjalan` : `${stagingResult?.batch?.status || 'belum ada batch'}`,
    },
    {
      step: '02',
      title: 'Update data existing',
      description: updateReadyCount > 0
        ? `${updateReadyCount} kandidat exact siap diupdate.`
        : 'Belum ada kandidat update dari preview.',
      state: applyingApply ? 'running' : (updateReadyCount > 0 || Boolean(applyResult) ? 'ready' : 'idle'),
      meta: applyResult ? `${applyResult.summary?.applied_items ?? 0} item sudah diupdate` : `${applyPreview?.summary?.field_changes ?? 0} field berbeda`,
    },
    {
      step: '03',
      title: 'Input data baru',
      description: inputReadyCount > 0
        ? `${inputReadyCount} kandidat unmatched siap dibuatkan akun.`
        : 'Belum ada kandidat input dari preview.',
      state: inputtingData ? 'running' : (inputReadyCount > 0 || Boolean(inputResult) ? 'ready' : 'idle'),
      meta: inputResult ? `${inputResult.summary?.created_items ?? 0} akun baru dibuat` : `${inputPreview?.summary?.blocked ?? 0} item tertahan`,
    },
  ];

  const loadSettings = async () => {
    setLoading(true);
    try {
      const response = await dapodikAPI.getSettings();
      const data = response?.data?.data || {};
      setForm({
        ...initialForm,
        base_url: data.base_url || initialForm.base_url,
        npsn: data.npsn || '',
        probe_path: data.probe_path || '/',
        api_token: data.api_token || '',
        clear_api_token: false,
      });
      setHasToken(Boolean(data.has_api_token));
      setLastTest(data.last_test || null);
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Pengaturan Dapodik belum bisa dimuat.', { variant: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const resetClassSyncState = () => {
    setClassPreview(null);
    setClassResult(null);
    setSelectedClassMappingIds([]);
    setClassMembershipPreview(null);
    setClassMembershipResult(null);
    setClassPreviewPage(1);
    setClassMembershipPage(1);
  };

  const loadTahunAjaranOptions = async () => {
    setLoadingTargetYears(true);
    try {
      const response = await tahunAjaranAPI.getAll({ no_pagination: true });
      const rows = extractApiRows(response);
      setTahunAjaranOptions(rows);
      setTargetTahunAjaranId((current) => {
        if (current && rows.some((item) => String(item.id) === String(current))) {
          return current;
        }

        return resolvePreferredTahunAjaranId(rows);
      });
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Gagal memuat daftar tahun ajaran.', { variant: 'error' });
    } finally {
      setLoadingTargetYears(false);
    }
  };

  useEffect(() => {
    loadSettings();
    loadTahunAjaranOptions();
  }, []);

  const buildClassTargetParams = () => (
    targetTahunAjaranId ? { target_tahun_ajaran_id: Number(targetTahunAjaranId) } : {}
  );

  const handleChange = (event) => {
    const { name, type, checked, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleTargetTahunAjaranChange = (event) => {
    setTargetTahunAjaranId(event.target.value);
    resetClassSyncState();
  };

  const startProcess = (title, steps, details = {}) => {
    setProcessProgress({
      title,
      status: 'running',
      percent: 0,
      steps: steps.map((label) => ({ label, status: 'queued', message: 'Menunggu.' })),
      errorTitle: null,
      errorItems: [],
      ...details,
    });
  };

  const updateProcessDetails = (updates) => {
    setProcessProgress((current) => (current ? { ...current, ...updates } : current));
  };

  const updateProcessStep = (stepIndex, updates) => {
    setProcessProgress((current) => {
      if (!current) return current;

      const steps = current.steps.map((step, index) => (
        index === stepIndex ? { ...step, ...updates } : step
      ));
      const done = steps.filter((step) => ['completed', 'failed'].includes(step.status)).length;
      const running = steps.filter((step) => step.status === 'running').length;
      const hasFailed = steps.some((step) => step.status === 'failed');
      const progressUnits = done === steps.length ? done : done + (running > 0 ? 0.45 : 0);

      return {
        ...current,
        steps,
        status: hasFailed ? 'failed' : (done === steps.length ? 'completed' : 'running'),
        percent: Math.floor((progressUnits / Math.max(steps.length, 1)) * 100),
      };
    });
  };

  const handleSave = async (event) => {
    event.preventDefault();
    startProcess('Simpan Pengaturan', ['Validasi form koneksi', 'Simpan ke server', 'Muat status token']);
    setSaving(true);
    try {
      updateProcessStep(0, { status: 'completed', message: 'Form siap dikirim.' });
      const payload = {
        base_url: form.base_url.trim(),
        npsn: form.npsn.trim() || null,
        probe_path: form.probe_path.trim() || '/',
        api_token: form.api_token.trim() || null,
        clear_api_token: Boolean(form.clear_api_token),
      };
      updateProcessStep(1, { status: 'running', message: 'Menyimpan pengaturan koneksi.' });
      const response = await dapodikAPI.updateSettings(payload);
      const data = response?.data?.data || {};
      updateProcessStep(1, { status: 'completed', message: response?.data?.message || 'Pengaturan tersimpan.' });
      setHasToken(Boolean(data.has_api_token));
      setLastTest(data.last_test || null);
      setForm((current) => ({ ...current, api_token: data.api_token || '', clear_api_token: false }));
      updateProcessStep(2, { status: 'completed', message: data.has_api_token ? 'Token tersimpan.' : 'Belum ada token tersimpan.' });
      enqueueSnackbar(response?.data?.message || 'Pengaturan Dapodik berhasil disimpan.', { variant: 'success' });
    } catch (error) {
      const apiErrors = error?.response?.data?.errors;
      const firstError = apiErrors ? Object.values(apiErrors).flat().find(Boolean) : null;
      const failure = buildProcessErrorState(error, 'Gagal menyimpan pengaturan.');
      updateProcessStep(1, { status: 'failed', message: firstError || failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error',
        errorItems: failure.details,
      });
      enqueueSnackbar(firstError || failure.message || 'Gagal menyimpan pengaturan Dapodik.', { variant: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleTest = async () => {
    startProcess('Test Koneksi', ['Kirim request ke Dapodik', 'Baca respons web service', 'Simpan token jika valid']);
    setTesting(true);
    try {
      const typedToken = form.api_token.trim();
      updateProcessStep(0, { status: 'running', message: 'Menghubungi endpoint test.' });
      const response = await dapodikAPI.testConnection({
        base_url: form.base_url.trim(),
        npsn: form.npsn.trim() || null,
        probe_path: form.probe_path.trim() || '/',
        api_token: typedToken || null,
      });
      const data = response?.data?.data || null;
      setLastTest(data);
      updateProcessStep(0, { status: 'completed', message: `HTTP ${data?.status_code || '-'}.` });
      updateProcessStep(1, {
        status: data?.web_service_ready ? 'completed' : 'failed',
        message: data?.message || 'Respons diterima.',
      });
      if (data?.web_service_ready && typedToken) {
        updateProcessStep(2, { status: 'running', message: 'Menyimpan token yang valid.' });
        const saveResponse = await dapodikAPI.updateSettings({
          base_url: form.base_url.trim(),
          npsn: form.npsn.trim() || null,
          probe_path: form.probe_path.trim() || '/',
          api_token: typedToken,
          clear_api_token: false,
        });
        const settings = saveResponse?.data?.data || {};
        setHasToken(Boolean(settings.has_api_token));
        setForm((current) => ({ ...current, api_token: settings.api_token || typedToken, clear_api_token: false }));
        updateProcessStep(2, { status: 'completed', message: 'Token berhasil disimpan.' });
        enqueueSnackbar('Test berhasil dan token Dapodik sudah disimpan.', { variant: 'success' });
      } else {
        updateProcessStep(2, { status: 'completed', message: 'Tidak ada token baru yang disimpan.' });
        enqueueSnackbar(response?.data?.message || 'Test koneksi Dapodik selesai.', {
          variant: data?.web_service_ready ? 'success' : 'warning',
        });
      }
    } catch (error) {
      const data = error?.response?.data?.data || null;
      const failure = buildProcessErrorState(error, 'Koneksi gagal.');
      if (data) setLastTest(data);
      updateProcessStep(0, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Koneksi Dapodik gagal.', { variant: 'error' });
    } finally {
      setTesting(false);
    }
  };

  const updateStagingStep = (sourceId, updates) => {
    setStagingProgress((current) => {
      if (!current) return current;

      const steps = current.steps.map((step) => (step.id === sourceId ? { ...step, ...updates } : step));
      const finalizeStatus = current.finalize?.status || 'queued';

      return {
        ...current,
        steps,
        percent: calculateStagingPercent(steps, finalizeStatus),
      };
    });
  };

  const updateStagingFinalize = (updates) => {
    setStagingProgress((current) => {
      if (!current) return current;

      const finalize = { ...current.finalize, ...updates };

      return {
        ...current,
        finalize,
        percent: calculateStagingPercent(current.steps, finalize.status),
      };
    });
  };

  const mergeStagingResult = (data, fetches = null) => {
    if (!data) return;

    setStagingResult((current) => ({
      ...(current || {}),
      ...data,
      fetches: fetches || data.fetches || current?.fetches || {},
    }));
  };

  const loadStagingReview = async (batchId = stagingResult?.batch?.id, filters = reviewFilters, silent = false) => {
    if (!batchId) {
      if (!silent) enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return null;
    }

    setReviewingStaging(true);
    try {
      const confidenceFilter = filters.confidence === 'problem' ? 'problem' : filters.confidence;
      const response = await dapodikAPI.getStagingReview(batchId, {
        entity_type: filters.entity_type || undefined,
        confidence: confidenceFilter || undefined,
        limit: 100,
      });
      const data = response?.data?.data || null;
      setStagingReview(data);
      setReviewPage(1);
      if (!silent) {
        enqueueSnackbar(response?.data?.message || 'Review mapping staging berhasil dimuat.', { variant: 'success' });
      }
      return data;
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Review mapping staging gagal dimuat.', { variant: 'error' });
      return null;
    } finally {
      setReviewingStaging(false);
    }
  };

  const handleReviewFilterChange = (event) => {
    const { name, value } = event.target;
    const nextFilters = { ...reviewFilters, [name]: value };
    setReviewFilters(nextFilters);
    setReviewPage(1);

    if (stagingResult?.batch?.id) {
      loadStagingReview(stagingResult.batch.id, nextFilters, true);
    }
  };

  const loadApplyPreview = async (batchId = stagingResult?.batch?.id, filters = applyFilters, silent = false) => {
    if (!batchId) {
      if (!silent) enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return null;
    }

    setPreviewingApply(true);
    try {
      const response = await dapodikAPI.getApplyPreview(batchId, {
        entity_type: filters.entity_type || undefined,
        limit: 100,
      });
      const data = response?.data?.data || null;
      setApplyPreview(data);
      setApplyPreviewPage(1);
      if (!silent) {
        enqueueSnackbar(response?.data?.message || 'Preview update berhasil dihitung.', { variant: 'success' });
      }
      return data;
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Preview update gagal dihitung.', { variant: 'error' });
      return null;
    } finally {
      setPreviewingApply(false);
    }
  };

  const handleApplyFilterChange = (event) => {
    const { name, value } = event.target;
    const nextFilters = { ...applyFilters, [name]: value };
    setApplyFilters(nextFilters);
    setApplyResult(null);
    setApplyPreviewPage(1);

    if (stagingResult?.batch?.id) {
      loadApplyPreview(stagingResult.batch.id, nextFilters, true);
    }
  };

  const loadFullApplyCandidates = async (batchId = stagingResult?.batch?.id) => {
    const response = await dapodikAPI.getApplyPreview(batchId, {
      entity_type: applyFilters.entity_type || undefined,
      limit: DAPODIK_ACTION_PREVIEW_LIMIT,
    });

    return response?.data?.data || null;
  };

  const loadInputPreview = async (batchId = stagingResult?.batch?.id, filters = inputFilters, silent = false) => {
    if (!batchId) {
      if (!silent) enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return null;
    }

    setPreviewingInput(true);
    try {
      const response = await dapodikAPI.getInputPreview(batchId, {
        entity_type: filters.entity_type || undefined,
        limit: 100,
      });
      const data = response?.data?.data || null;
      setInputPreview(data);
      setInputPreviewPage(1);
      if (!silent) {
        enqueueSnackbar(response?.data?.message || 'Preview input data baru berhasil dihitung.', { variant: 'success' });
      }
      return data;
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Preview input data baru gagal dihitung.', { variant: 'error' });
      return null;
    } finally {
      setPreviewingInput(false);
    }
  };

  const handleInputFilterChange = (event) => {
    const { name, value } = event.target;
    const nextFilters = { ...inputFilters, [name]: value };
    setInputFilters(nextFilters);
    setInputResult(null);
    setInputPreviewPage(1);

    if (stagingResult?.batch?.id) {
      loadInputPreview(stagingResult.batch.id, nextFilters, true);
    }
  };

  const loadFullInputCandidates = async (batchId = stagingResult?.batch?.id) => {
    const response = await dapodikAPI.getInputPreview(batchId, {
      entity_type: inputFilters.entity_type || undefined,
      limit: DAPODIK_ACTION_PREVIEW_LIMIT,
    });

    return response?.data?.data || null;
  };

  const toggleClassMappingSelection = (mappingId) => {
    setClassMembershipPreview(null);
    setClassMembershipResult(null);
    setClassMembershipPage(1);
    setSelectedClassMappingIds((current) => (
      current.includes(mappingId)
        ? current.filter((id) => id !== mappingId)
        : [...current, mappingId]
    ));
  };

  const selectVisibleClasses = () => {
    setClassMembershipPreview(null);
    setClassMembershipResult(null);
    setClassMembershipPage(1);
    setSelectedClassMappingIds((current) => uniqueIds([
      ...current,
      ...visibleClassItems.map((item) => item.mapping_id),
    ]));
  };

  const clearSelectedClasses = () => {
    setSelectedClassMappingIds([]);
    setClassMembershipPreview(null);
    setClassMembershipResult(null);
    setClassMembershipPage(1);
  };

  const loadClassPreview = async (batchId = stagingResult?.batch?.id, silent = false) => {
    if (!batchId) {
      if (!silent) enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return null;
    }

    if (!targetTahunAjaranId) {
      if (!silent) enqueueSnackbar('Pilih target tahun ajaran dulu sebelum preview kelas.', { variant: 'warning' });
      return null;
    }

    setPreviewingClasses(true);
    try {
      const response = await dapodikAPI.getClassPreview(batchId, {
        ...buildClassTargetParams(),
        limit: DAPODIK_ACTION_PREVIEW_LIMIT,
      });
      const data = response?.data?.data || null;
      setClassPreview(data);
      setClassPreviewPage(1);
      setSelectedClassMappingIds((current) => current.filter((id) => (data?.items || []).some((item) => item.mapping_id === id)));

      if (!silent) {
        enqueueSnackbar(response?.data?.message || 'Preview master kelas berhasil dimuat.', { variant: 'success' });
      }

      return data;
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Preview master kelas gagal dimuat.', { variant: 'error' });
      return null;
    } finally {
      setPreviewingClasses(false);
    }
  };

  const loadClassMembershipPreview = async (
    batchId = stagingResult?.batch?.id,
    mappingIds = selectedClassMappingIds,
    silent = false,
  ) => {
    const scopedIds = uniqueIds(mappingIds);

    if (!batchId) {
      if (!silent) enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return null;
    }

    if (!scopedIds.length) {
      if (!silent) enqueueSnackbar('Pilih kelas dulu sebelum preview anggota kelas.', { variant: 'warning' });
      return null;
    }

    if (!targetTahunAjaranId) {
      if (!silent) enqueueSnackbar('Pilih target tahun ajaran dulu sebelum preview anggota kelas.', { variant: 'warning' });
      return null;
    }

    setPreviewingClassMembers(true);
    try {
      const response = await dapodikAPI.getClassMembershipPreview(batchId, {
        ...buildClassTargetParams(),
        limit: DAPODIK_ACTION_PREVIEW_LIMIT,
        mapping_ids: scopedIds,
      });
      const data = response?.data?.data || null;
      setClassMembershipPreview(data);
      setClassMembershipPage(1);

      if (!silent) {
        enqueueSnackbar(response?.data?.message || 'Preview anggota kelas berhasil dimuat.', { variant: 'success' });
      }

      return data;
    } catch (error) {
      enqueueSnackbar(error?.response?.data?.message || 'Preview anggota kelas gagal dimuat.', { variant: 'error' });
      return null;
    } finally {
      setPreviewingClassMembers(false);
    }
  };

  const handleClassSync = (mode) => {
    const batchId = stagingResult?.batch?.id;
    if (!batchId) {
      enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return;
    }

    if (!classPreview) {
      enqueueSnackbar('Muat preview master kelas dulu.', { variant: 'warning' });
      return;
    }

    if (!targetTahunAjaranId) {
      enqueueSnackbar('Pilih target tahun ajaran dulu sebelum sinkronisasi kelas.', { variant: 'warning' });
      return;
    }

    const candidates = mode === 'update' ? selectedUpdateClassItems : selectedCreateClassItems;
    if (!candidates.length) {
      enqueueSnackbar(
        mode === 'update'
          ? 'Pilih minimal satu kelas kandidat update.'
          : 'Pilih minimal satu kelas kandidat input.',
        { variant: 'info' },
      );
      return;
    }

    setConfirmation({
      type: `class-${mode}`,
      title: mode === 'update' ? 'Konfirmasi Update Master Kelas' : 'Konfirmasi Input Master Kelas',
      description: mode === 'update'
        ? 'Hanya kelas terpilih yang akan diupdate. Kelas probable dan conflict tetap ditahan.'
        : 'Hanya kelas terpilih yang akan dibuat di SIAPS. Kelas lain tidak ikut diproses.',
      confirmText: mode === 'update' ? 'Update Kelas' : 'Input Kelas',
      countLabel: 'Kelas terpilih',
      count: candidates.length,
      batchId,
      namesTitle: mode === 'update' ? 'Kelas yang akan diupdate' : 'Kelas yang akan diinput',
      names: classNamesFromItems(candidates),
      warning: `Proses hanya berjalan untuk kelas yang dipilih dan akan diarahkan ke tahun ajaran ${targetTahunAjaranLabel}. Halaman dikunci sampai sinkronisasi master kelas selesai.`,
    });
  };

  const executeClassSync = async (mode) => {
    const batchId = stagingResult?.batch?.id;
    const candidates = mode === 'update' ? selectedUpdateClassItems : selectedCreateClassItems;
    const chunks = splitIntoChunks(candidates, DAPODIK_CLASS_CHUNK_SIZE);
    let accumulatedResult = null;

    startProcess(
      mode === 'update' ? 'Update Master Kelas' : 'Input Master Kelas',
      ['Validasi kelas terpilih', 'Kirim sinkronisasi master kelas', 'Catat hasil sinkronisasi kelas', 'Refresh preview kelas'],
      {
        itemsTitle: mode === 'update' ? 'Kelas yang akan diupdate' : 'Kelas yang akan diinput',
        items: classNamesFromItems(candidates),
      },
    );
    setSyncingClasses(true);

    try {
      updateProcessStep(0, {
        status: 'completed',
        message: `${candidates.length} kelas siap diproses dalam ${Math.max(chunks.length, 1)} batch.`,
      });

      if (!candidates.length) {
        throw new Error('Tidak ada kelas terpilih untuk diproses.');
      }

      updateProcessStep(1, { status: 'running', message: `Memproses batch 1/${chunks.length}.` });

      for (let index = 0; index < chunks.length; index += 1) {
        const chunk = chunks[index];
        const response = await dapodikAPI.syncClasses(batchId, {
          ...buildClassTargetParams(),
          mode,
          confirm_sync: true,
          mapping_ids: chunk.map((item) => item.mapping_id),
        });
        const data = response?.data?.data || null;
        accumulatedResult = mergeChunkedResult(accumulatedResult, data);

        updateProcessDetails({
          itemsTitle: mode === 'update' ? 'Kelas yang berhasil diupdate' : 'Kelas yang berhasil diinput',
          items: resultNamesFromItems(accumulatedResult?.items || [], mode === 'update' ? 'applied' : 'created'),
          percent: Math.min(84, 25 + Math.floor(((index + 1) / chunks.length) * 50)),
        });
        updateProcessStep(1, {
          status: 'running',
          message: `Batch ${index + 1}/${chunks.length} selesai. ${(accumulatedResult?.summary?.applied_items || 0) + (accumulatedResult?.summary?.created_items || 0)}/${candidates.length} kelas diproses.`,
        });
      }

      setClassResult(accumulatedResult);
      updateProcessStep(1, {
        status: 'completed',
        message: `${(accumulatedResult?.summary?.applied_items || 0) + (accumulatedResult?.summary?.created_items || 0)} kelas diproses.`,
      });
      updateProcessDetails({
        itemsTitle: mode === 'update' ? 'Kelas yang berhasil diupdate' : 'Kelas yang berhasil diinput',
        items: resultNamesFromItems(accumulatedResult?.items || [], mode === 'update' ? 'applied' : 'created'),
      });
      updateProcessStep(2, {
        status: 'completed',
        message: `${(accumulatedResult?.summary?.applied_items || 0) + (accumulatedResult?.summary?.created_items || 0)} kelas tercatat.`,
      });
      updateProcessStep(3, { status: 'running', message: 'Memuat ulang preview master kelas.' });
      await loadClassPreview(batchId, true);
      if (selectedClassMappingIds.length) {
        await loadClassMembershipPreview(batchId, selectedClassMappingIds, true);
      }
      updateProcessStep(3, { status: 'completed', message: 'Preview master kelas sudah diperbarui.' });
      enqueueSnackbar(`Sinkronisasi master kelas selesai dalam ${chunks.length} batch.`, { variant: 'success' });
    } catch (error) {
      const failure = buildProcessErrorState(error, 'Sinkronisasi master kelas gagal.');
      if (accumulatedResult?.items?.length) {
        setClassResult(accumulatedResult);
        failure.details.unshift({
          label: 'Sudah diproses',
          value: `${(accumulatedResult?.summary?.applied_items || 0) + (accumulatedResult?.summary?.created_items || 0)} kelas sudah diproses sebelum berhenti`,
        });
      }
      updateProcessStep(1, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error sinkronisasi kelas',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Sinkronisasi master kelas gagal.', { variant: 'error' });
    } finally {
      setSyncingClasses(false);
    }
  };

  const handleClassMembershipSync = async () => {
    const batchId = stagingResult?.batch?.id;
    if (!batchId) {
      enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return;
    }

    const preview = await loadClassMembershipPreview(batchId, selectedClassMappingIds, true);
    const candidates = (preview?.items || []).filter((item) => ['assign_candidate', 'reactivate_candidate'].includes(item.action));

    if (!candidates.length) {
      enqueueSnackbar('Tidak ada kandidat anggota kelas dari kelas yang dipilih.', { variant: 'info' });
      return;
    }

    setConfirmation({
      type: 'class-memberships',
      title: 'Konfirmasi Sinkronisasi Anggota Kelas',
      description: 'Hanya anggota dari kelas yang dipilih yang akan diproses. Siswa yang masih aktif di kelas lain tetap ditahan.',
      confirmText: 'Sinkronkan Anggota',
      countLabel: 'Kandidat anggota',
      count: candidates.length,
      batchId,
      namesTitle: 'Anggota yang akan diproses',
      names: classNamesFromItems(candidates),
      warning: `Proses berjalan per kelompok kelas terpilih pada tahun ajaran ${targetTahunAjaranLabel}. Halaman dikunci sampai sinkronisasi anggota kelas selesai.`,
    });
  };

  const executeClassMembershipSync = async () => {
    const batchId = stagingResult?.batch?.id;
    const classIdChunks = splitIntoChunks(selectedMembershipClassItems.map((item) => item.mapping_id), DAPODIK_CLASS_MEMBERSHIP_CHUNK_SIZE);
    let accumulatedResult = null;

    startProcess(
      'Sinkronisasi Anggota Kelas',
      ['Validasi kelas terpilih', 'Kirim sinkronisasi anggota kelas', 'Catat hasil anggota kelas', 'Refresh preview anggota kelas'],
      {
        itemsTitle: 'Kelas yang dipakai untuk anggota',
        items: classNamesFromItems(selectedMembershipClassItems),
      },
    );
    setSyncingClassMembers(true);

    try {
      if (!classIdChunks.length) {
        throw new Error('Tidak ada kelas terpilih untuk sinkronisasi anggota.');
      }

      updateProcessStep(0, {
        status: 'completed',
        message: `${selectedMembershipClassItems.length} kelas siap dipakai dalam ${classIdChunks.length} batch.`,
      });
      updateProcessStep(1, { status: 'running', message: `Memproses batch 1/${classIdChunks.length}.` });

      for (let index = 0; index < classIdChunks.length; index += 1) {
        const chunkIds = classIdChunks[index];
        const response = await dapodikAPI.syncClassMemberships(batchId, {
          ...buildClassTargetParams(),
          confirm_sync: true,
          mapping_ids: chunkIds,
        });
        const data = response?.data?.data || null;
        accumulatedResult = mergeChunkedResult(accumulatedResult, data);

        updateProcessDetails({
          itemsTitle: 'Anggota kelas yang berhasil diproses',
          items: [
            ...resultNamesFromItems(accumulatedResult?.items || [], 'assigned'),
            ...resultNamesFromItems(accumulatedResult?.items || [], 'reactivated'),
          ],
          percent: Math.min(84, 25 + Math.floor(((index + 1) / classIdChunks.length) * 50)),
        });
        updateProcessStep(1, {
          status: 'running',
          message: `Batch ${index + 1}/${classIdChunks.length} selesai. ${(accumulatedResult?.summary?.assigned_items || 0) + (accumulatedResult?.summary?.reactivated_items || 0)} anggota diproses.`,
        });
      }

      setClassMembershipResult(accumulatedResult);
      updateProcessStep(1, {
        status: 'completed',
        message: `${(accumulatedResult?.summary?.assigned_items || 0) + (accumulatedResult?.summary?.reactivated_items || 0)} anggota diproses.`,
      });
      updateProcessDetails({
        itemsTitle: 'Anggota kelas yang berhasil diproses',
        items: [
          ...resultNamesFromItems(accumulatedResult?.items || [], 'assigned'),
          ...resultNamesFromItems(accumulatedResult?.items || [], 'reactivated'),
        ],
      });
      updateProcessStep(2, {
        status: 'completed',
        message: `${(accumulatedResult?.summary?.assigned_items || 0) + (accumulatedResult?.summary?.reactivated_items || 0)} anggota tercatat.`,
      });
      updateProcessStep(3, { status: 'running', message: 'Memuat ulang preview anggota kelas.' });
      await loadClassMembershipPreview(batchId, selectedClassMappingIds, true);
      updateProcessStep(3, { status: 'completed', message: 'Preview anggota kelas sudah diperbarui.' });
      enqueueSnackbar(`Sinkronisasi anggota kelas selesai dalam ${classIdChunks.length} batch.`, { variant: 'success' });
    } catch (error) {
      const failure = buildProcessErrorState(error, 'Sinkronisasi anggota kelas gagal.');
      if (accumulatedResult?.items?.length) {
        setClassMembershipResult(accumulatedResult);
        failure.details.unshift({
          label: 'Sudah diproses',
          value: `${(accumulatedResult?.summary?.assigned_items || 0) + (accumulatedResult?.summary?.reactivated_items || 0)} anggota sudah diproses sebelum berhenti`,
        });
      }
      updateProcessStep(1, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error anggota kelas',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Sinkronisasi anggota kelas gagal.', { variant: 'error' });
    } finally {
      setSyncingClassMembers(false);
    }
  };

  const handleApplyFinal = async () => {
    const batchId = stagingResult?.batch?.id;
    if (!batchId) {
      enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return;
    }

    const updateCount = applyPreview?.summary?.update_candidates || 0;
    if (updateCount < 1) {
      enqueueSnackbar('Tidak ada kandidat update data untuk diproses.', { variant: 'info' });
      return;
    }

    setConfirmation({
      type: 'apply',
      title: 'Konfirmasi Update Data',
      description: 'Update data existing dari preview ini. Kelas, user baru, dan email pegawai tidak ikut diubah.',
      confirmText: 'Update Data',
      countLabel: 'Kandidat update',
      count: updateCount,
      batchId,
      namesTitle: 'Nama yang akan diupdate',
      names: previewNames(applyPreview, 'update_candidate'),
      warning: 'Proses dijalankan bertahap per batch agar tidak timeout. Selama proses berjalan, halaman akan dikunci sampai update selesai.',
    });
  };

  const executeApplyFinal = async () => {
    const batchId = stagingResult?.batch?.id;
    let accumulatedResult = null;

    startProcess(
      'Update Data',
      ['Validasi kandidat update', 'Kirim update ke server', 'Catat nama hasil update', 'Refresh preview update'],
      {
        itemsTitle: 'Nama yang akan diupdate',
        items: previewNames(applyPreview, 'update_candidate'),
      }
    );
    setApplyingApply(true);
    try {
      updateProcessStep(0, { status: 'running', message: 'Mengambil seluruh kandidat update.' });
      const fullPreview = await loadFullApplyCandidates(batchId);
      if (fullPreview?.has_more) {
        throw new Error(`Jumlah kandidat update melebihi batas ${DAPODIK_ACTION_PREVIEW_LIMIT}. Persempit filter lebih dulu.`);
      }
      const candidates = (fullPreview?.items || []).filter((item) => item.action === 'update_candidate');
      const chunks = splitIntoChunks(candidates, DAPODIK_APPLY_CHUNK_SIZE);

      updateProcessDetails({
        itemsTitle: 'Nama yang akan diupdate',
        items: previewNamesFromItems(candidates, 'update_candidate'),
      });

      updateProcessStep(0, {
        status: 'completed',
        message: `${candidates.length} kandidat update siap diproses dalam ${Math.max(chunks.length, 1)} batch.`,
      });

      if (!candidates.length) {
        throw new Error('Tidak ada kandidat update yang tersisa saat eksekusi dimulai.');
      }

      updateProcessStep(1, { status: 'running', message: `Memproses batch 1/${chunks.length}.` });

      for (let index = 0; index < chunks.length; index += 1) {
        const chunk = chunks[index];
        const response = await dapodikAPI.applyStagingBatch(batchId, {
          entity_type: applyFilters.entity_type || undefined,
          confirm_apply: true,
          mapping_ids: chunk.map((item) => item.mapping_id),
        });
        const data = response?.data?.data || null;
        accumulatedResult = mergeChunkedResult(accumulatedResult, data);

        updateProcessDetails({
          itemsTitle: 'Nama yang berhasil diupdate',
          items: resultNamesFromItems(accumulatedResult?.items || [], 'applied'),
          percent: Math.min(84, 25 + Math.floor(((index + 1) / chunks.length) * 50)),
        });
        updateProcessStep(1, {
          status: 'running',
          message: `Batch ${index + 1}/${chunks.length} selesai. ${accumulatedResult?.summary?.applied_items || 0}/${candidates.length} item diupdate.`,
        });
      }

      setApplyResult(accumulatedResult);
      updateProcessStep(1, { status: 'completed', message: `${accumulatedResult?.summary?.applied_items || 0} item diupdate.` });
      updateProcessDetails({
        itemsTitle: 'Nama yang berhasil diupdate',
        items: resultNames(accumulatedResult, 'applied'),
      });
      updateProcessStep(2, { status: 'completed', message: `${accumulatedResult?.summary?.applied_items || 0} nama berhasil diupdate.` });
      updateProcessStep(3, { status: 'running', message: 'Menghitung ulang preview update.' });
      await loadApplyPreview(batchId, applyFilters, true);
      updateProcessStep(3, { status: 'completed', message: 'Preview update sudah diperbarui.' });
      enqueueSnackbar(`Update data Dapodik selesai dalam ${chunks.length} batch.`, { variant: 'success' });
    } catch (error) {
      const failure = buildProcessErrorState(error, 'Update data gagal.');
      if (accumulatedResult?.items?.length) {
        setApplyResult(accumulatedResult);
        failure.details.unshift({
          label: 'Sudah diproses',
          value: `${accumulatedResult?.summary?.applied_items || 0} item sudah diupdate sebelum proses berhenti`,
        });
      }
      updateProcessStep(1, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error update',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Update data Dapodik gagal.', { variant: 'error' });
    } finally {
      setApplyingApply(false);
    }
  };

  const handleInputFinal = async () => {
    const batchId = stagingResult?.batch?.id;
    if (!batchId) {
      enqueueSnackbar('Batch staging belum tersedia.', { variant: 'warning' });
      return;
    }

    const createCount = inputPreview?.summary?.create_candidates || 0;
    if (createCount < 1) {
      enqueueSnackbar('Tidak ada kandidat input data baru untuk diproses.', { variant: 'info' });
      return;
    }

    setConfirmation({
      type: 'input',
      title: 'Konfirmasi Input Data Baru',
      description: 'Input data baru dari Dapodik akan membuat akun user dan data detail untuk kandidat unmatched yang lolos validasi.',
      confirmText: 'Input Data Baru',
      countLabel: 'Kandidat input',
      count: createCount,
      batchId,
      namesTitle: 'Nama yang akan diinput',
      names: previewNames(inputPreview, 'create_candidate'),
      warning: 'Relasi kelas tidak dibuat otomatis. Proses dijalankan bertahap per batch agar tidak timeout, dan halaman dikunci sampai input selesai.',
    });
  };

  const executeInputFinal = async () => {
    const batchId = stagingResult?.batch?.id;
    let accumulatedResult = null;

    startProcess(
      'Input Data Baru',
      ['Validasi kandidat input', 'Kirim input ke server', 'Catat nama hasil input', 'Refresh preview input dan update'],
      {
        itemsTitle: 'Nama yang akan diinput',
        items: previewNames(inputPreview, 'create_candidate'),
      }
    );
    setInputtingData(true);
    try {
      updateProcessStep(0, { status: 'running', message: 'Mengambil seluruh kandidat input.' });
      const fullPreview = await loadFullInputCandidates(batchId);
      if (fullPreview?.has_more) {
        throw new Error(`Jumlah kandidat input melebihi batas ${DAPODIK_ACTION_PREVIEW_LIMIT}. Persempit filter lebih dulu.`);
      }
      const candidates = (fullPreview?.items || []).filter((item) => item.action === 'create_candidate');
      const chunks = splitIntoChunks(candidates, DAPODIK_INPUT_CHUNK_SIZE);

      updateProcessDetails({
        itemsTitle: 'Nama yang akan diinput',
        items: previewNamesFromItems(candidates, 'create_candidate'),
      });

      updateProcessStep(0, {
        status: 'completed',
        message: `${candidates.length} kandidat input siap diproses dalam ${Math.max(chunks.length, 1)} batch.`,
      });

      if (!candidates.length) {
        throw new Error('Tidak ada kandidat input yang tersisa saat eksekusi dimulai.');
      }

      updateProcessStep(1, { status: 'running', message: `Memproses batch 1/${chunks.length}.` });

      for (let index = 0; index < chunks.length; index += 1) {
        const chunk = chunks[index];
        const response = await dapodikAPI.inputStagingBatch(batchId, {
          entity_type: inputFilters.entity_type || undefined,
          confirm_input: true,
          mapping_ids: chunk.map((item) => item.mapping_id),
        });
        const data = response?.data?.data || null;
        accumulatedResult = mergeChunkedResult(accumulatedResult, data);

        updateProcessDetails({
          itemsTitle: 'Nama yang berhasil diinput',
          items: resultNamesFromItems(accumulatedResult?.items || [], 'created'),
          percent: Math.min(84, 25 + Math.floor(((index + 1) / chunks.length) * 50)),
        });
        updateProcessStep(1, {
          status: 'running',
          message: `Batch ${index + 1}/${chunks.length} selesai. ${accumulatedResult?.summary?.created_items || 0}/${candidates.length} user baru dibuat.`,
        });
      }

      setInputResult(accumulatedResult);
      updateProcessStep(1, { status: 'completed', message: `${accumulatedResult?.summary?.created_items || 0} user baru dibuat.` });
      updateProcessDetails({
        itemsTitle: 'Nama yang berhasil diinput',
        items: resultNames(accumulatedResult, 'created'),
      });
      updateProcessStep(2, { status: 'completed', message: `${accumulatedResult?.summary?.created_items || 0} nama berhasil diinput.` });
      updateProcessStep(3, { status: 'running', message: 'Menghitung ulang preview.' });
      await loadInputPreview(batchId, inputFilters, true);
      await loadApplyPreview(batchId, applyFilters, true);
      updateProcessStep(3, { status: 'completed', message: 'Preview input dan update sudah diperbarui.' });
      enqueueSnackbar(`Input data baru Dapodik selesai dalam ${chunks.length} batch.`, { variant: 'success' });
    } catch (error) {
      const failure = buildProcessErrorState(error, 'Input data baru gagal.');
      if (accumulatedResult?.items?.length) {
        setInputResult(accumulatedResult);
        failure.details.unshift({
          label: 'Sudah diproses',
          value: `${accumulatedResult?.summary?.created_items || 0} user baru sudah dibuat sebelum proses berhenti`,
        });
      }
      updateProcessStep(1, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error input',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Input data baru Dapodik gagal.', { variant: 'error' });
    } finally {
      setInputtingData(false);
    }
  };

  const handleConfirmAction = async () => {
    const action = confirmation?.type;
    setConfirmation(null);

    if (action === 'apply') {
      await executeApplyFinal();
    } else if (action === 'input') {
      await executeInputFinal();
    } else if (action === 'class-update') {
      await executeClassSync('update');
    } else if (action === 'class-input') {
      await executeClassSync('input');
    } else if (action === 'class-memberships') {
      await executeClassMembershipSync();
    }
  };

  const handleFetchStaging = async () => {
    startProcess('Ambil Data Dapodik', [
      'Buat batch pengambilan',
      'Ambil Sekolah',
      'Ambil Pengguna',
      'Ambil Siswa',
      'Ambil GTK',
      'Ambil Rombel',
      'Finalisasi mapping',
      'Siapkan jalur update dan input',
    ]);
    setFetchingStaging(true);
    setStagingProgress({ ...initialStagingProgress(), status: 'running' });
    setApplyResult(null);
    setInputResult(null);
    setClassPreview(null);
    setClassResult(null);
    setClassMembershipPreview(null);
    setClassMembershipResult(null);
    setSelectedClassMappingIds([]);

    const connectionPayload = {
      base_url: form.base_url.trim(),
      npsn: form.npsn.trim() || null,
      api_token: form.api_token.trim() || null,
    };
    const fetches = {};

    try {
      updateProcessStep(0, { status: 'running', message: 'Membuat batch staging.' });
      const createResponse = await dapodikAPI.createStagingBatch({
        ...connectionPayload,
        sources: stagingSources.map((source) => source.id),
      });
      const createdData = createResponse?.data?.data || null;
      const batch = createdData?.batch || null;
      mergeStagingResult(createdData);
      setStagingProgress((current) => (current ? { ...current, batch } : current));
      updateProcessStep(0, { status: 'completed', message: batch?.id ? `Batch ${batch.id} dibuat.` : 'Batch dibuat.' });

      if (!batch?.id) {
        throw new Error('Batch staging tidak terbentuk.');
      }

      for (const [sourceIndex, source] of stagingSources.entries()) {
        updateProcessStep(sourceIndex + 1, { status: 'running', message: `Mengambil data ${source.label}.` });
        updateStagingStep(source.id, {
          status: 'running',
          message: `Mengambil data ${source.label}.`,
          error: null,
        });

        try {
          const sourceResponse = await dapodikAPI.fetchStagingBatchSource(batch.id, {
            ...connectionPayload,
            source: source.id,
          });
          const sourceData = sourceResponse?.data?.data || {};
          const sourceStatus = sourceData.batch?.source_statuses?.[source.id] || {};
          fetches[source.id] = sourceData.fetch || {};
          mergeStagingResult(sourceData, fetches);
          setStagingProgress((current) => (current ? { ...current, batch: sourceData.batch || current.batch } : current));
          updateStagingStep(source.id, {
            status: sourceStatus.status || (sourceResponse?.data?.success ? 'completed' : 'failed'),
            row_count: sourceStatus.row_count ?? sourceData.fetch?.row_count ?? 0,
            records_stored: sourceStatus.records_stored ?? sourceData.records_stored ?? 0,
            message: sourceStatus.message || sourceData.fetch?.message || sourceResponse?.data?.message || '-',
            error: sourceStatus.error || sourceData.fetch?.error || null,
          });
          updateProcessStep(sourceIndex + 1, {
            status: sourceStatus.status === 'failed' ? 'failed' : 'completed',
            message: `${sourceStatus.records_stored ?? sourceData.records_stored ?? 0} record staging.`,
          });
        } catch (sourceError) {
          updateStagingStep(source.id, {
            status: 'failed',
            message: sourceError?.response?.data?.message || sourceError?.message || 'Sumber gagal diproses.',
            error: sourceError?.response?.data?.message || sourceError?.message || 'Sumber gagal diproses.',
          });
          updateProcessStep(sourceIndex + 1, {
            status: 'failed',
            message: sourceError?.response?.data?.message || sourceError?.message || 'Sumber gagal diproses.',
          });
        }
      }

      updateProcessStep(6, { status: 'running', message: 'Mencocokkan data Dapodik dengan SIAPS.' });
      updateStagingFinalize({
        status: 'running',
        message: 'Mencocokkan staging Dapodik dengan data lokal SIAPS.',
      });

      const finalizeResponse = await dapodikAPI.finalizeStagingBatch(batch.id, {
        base_url: connectionPayload.base_url,
        npsn: connectionPayload.npsn,
      });
      const finalData = finalizeResponse?.data?.data || null;
      mergeStagingResult(finalData, fetches);
      setStagingProgress((current) => (current ? {
        ...current,
        status: 'completed',
        batch: finalData?.batch || current.batch,
      } : current));
      updateStagingFinalize({
        status: 'completed',
        message: finalizeResponse?.data?.message || 'Finalisasi mapping selesai.',
      });
      updateProcessStep(6, { status: 'completed', message: 'Mapping update dan input selesai dihitung.' });
      updateProcessStep(7, { status: 'running', message: 'Memuat preview update dan input.' });
      await loadStagingReview(finalData?.batch?.id || batch.id, reviewFilters, true);
      await loadApplyPreview(finalData?.batch?.id || batch.id, applyFilters, true);
      await loadInputPreview(finalData?.batch?.id || batch.id, inputFilters, true);
      updateProcessStep(7, { status: 'completed', message: 'Jalur update dan input siap.' });

      const failedSources = Object.values(finalData?.batch?.source_statuses || {}).filter((status) => status.status === 'failed').length;
      enqueueSnackbar(finalizeResponse?.data?.message || 'Data Dapodik berhasil disimpan ke staging.', {
        variant: failedSources > 0 ? 'warning' : 'success',
      });
    } catch (error) {
      const data = error?.response?.data?.data || null;
      const failure = buildProcessErrorState(error, 'Pengambilan staging Dapodik gagal.');
      if (data) mergeStagingResult(data, fetches);
      setStagingProgress((current) => {
        if (!current) return current;

        return {
          ...current,
          status: 'failed',
          percent: calculateStagingPercent(current.steps, 'failed'),
          finalize: {
            ...current.finalize,
            status: 'failed',
            message: failure.message,
          },
        };
      });
      updateProcessStep(6, { status: 'failed', message: failure.message });
      updateProcessDetails({
        errorTitle: 'Lokasi error pengambilan',
        errorItems: failure.details,
      });
      enqueueSnackbar(failure.message || 'Pengambilan staging Dapodik gagal.', { variant: 'error' });
    } finally {
      setFetchingStaging(false);
    }
  };

  return (
    <div className="space-y-6">
      {confirmation ? (
        <ConfirmationDialog
          config={confirmation}
          onCancel={() => setConfirmation(null)}
          onConfirm={handleConfirmAction}
        />
      ) : null}

      {operationLocked ? (
        <OperationLockOverlay progress={processProgress} />
      ) : null}

      <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="flex items-start gap-3">
            <div className="rounded-lg bg-emerald-50 p-3 text-emerald-700">
              <Database className="h-6 w-6" />
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-widest text-emerald-600">Integrasi Satu Arah</p>
              <h1 className="mt-1 text-2xl font-bold text-slate-900">Integrasi Dapodik</h1>
              <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Ambil data Dapodik lalu proses lewat dua jalur: update user existing dan input user baru.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={loadSettings}
            disabled={loading}
            className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Muat Ulang
          </button>
        </div>

        <div className="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
          <MetricBox label="Koneksi" value={connectionReady ? 'Siap sinkron' : (lastTest ? 'Perlu cek' : 'Belum diuji')} />
          <MetricBox label="Batch Aktif" value={currentBatchId} />
          <MetricBox label="Progress Batch" value={`${stagingPercent}%`} />
          <MetricBox label="Exact" value={exactCount} />
          <MetricBox label="Manual Review" value={manualReviewCount} />
          <MetricBox
            label={activeDataTab === 'users' ? 'Siap Input Baru' : 'Kelas Dipilih'}
            value={activeDataTab === 'users' ? inputReadyCount : selectedClassItems.length}
          />
        </div>

        <div className="mt-5 grid grid-cols-1 gap-3 xl:grid-cols-3">
          {workflowSteps.map((item) => (
            <WorkflowStageCard
              key={item.step}
              step={item.step}
              title={item.title}
              description={item.description}
              state={item.state}
              meta={item.meta}
            />
          ))}
        </div>
      </div>

      <form onSubmit={handleSave} className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Pengaturan Koneksi</h2>
          <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Field label="Base URL Dapodik">
              <input
                type="url"
                name="base_url"
                value={form.base_url}
                onChange={handleChange}
                required
                placeholder="http://182.253.36.196:5885"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
              />
            </Field>
            <Field label="Probe Path">
              <input
                type="text"
                name="probe_path"
                value={form.probe_path}
                onChange={handleChange}
                placeholder="/WebService/getSekolah"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
              />
              <p className="mt-2 text-xs text-slate-500">
                Untuk test web service, gunakan path Dapodik seperti /WebService/getSekolah. NPSN akan ditambahkan otomatis.
              </p>
            </Field>
            <Field label="NPSN">
              <input
                type="text"
                name="npsn"
                value={form.npsn}
                onChange={handleChange}
                placeholder="NPSN sekolah"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
              />
            </Field>
            <Field label="Token API">
              <input
                type="text"
                name="api_token"
                value={form.api_token}
                onChange={handleChange}
                placeholder="Token dari Web Service Dapodik"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
              />
              <p className="mt-2 text-xs text-slate-500">
                Token dari menu Web Service Lokal Dapodik dikirim sebagai Bearer Token.
              </p>
              {hasToken ? (
                <label className="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                  <input
                    type="checkbox"
                    name="clear_api_token"
                    checked={form.clear_api_token}
                    onChange={handleChange}
                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                  />
                  Hapus token tersimpan
                </label>
              ) : null}
            </Field>
          </div>

          <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              onClick={handleFetchStaging}
              disabled={loading || fetchingStaging}
              className="inline-flex items-center justify-center rounded-lg border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Database className="mr-2 h-4 w-4" />
              {fetchingStaging ? 'Mengambil...' : 'Ambil Data Dapodik'}
            </button>
            <button
              type="button"
              onClick={handleTest}
              disabled={loading || testing}
              className="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Wifi className="mr-2 h-4 w-4" />
              {testing ? 'Mengetes...' : 'Test Koneksi'}
            </button>
            <button
              type="submit"
              disabled={loading || saving}
              className="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-slate-400"
            >
              <Save className="mr-2 h-4 w-4" />
              {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
            </button>
          </div>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Status Koneksi</h2>
          {lastTest ? (
            <div className="mt-4 space-y-3">
              <StatusPill ok={lastTest.reachable} label={lastTest.reachable ? 'Server terhubung' : 'Server belum terhubung'} />
              <StatusPill ok={lastTest.web_service_ready} label={lastTest.web_service_ready ? 'Web service JSON siap' : 'Endpoint belum JSON'} />
              {lastTest.dapodik_accepted === false ? (
                <StatusPill ok={false} label="Dapodik menolak request" />
              ) : null}
              <InfoLine label="HTTP" value={lastTest.status_code || '-'} />
              <InfoLine label="Status Dapodik" value={lastTest.dapodik_status_code || '-'} />
              <InfoLine label="Content-Type" value={lastTest.content_type || '-'} />
              <InfoLine label="Durasi" value={`${lastTest.duration_ms || 0} ms`} />
              <InfoLine label="NPSN" value={lastTest.npsn || '-'} />
              <InfoLine label="Waktu" value={formatServerDateTime(lastTest.checked_at, 'id-ID') || '-'} />
              <p className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{lastTest.message}</p>
              {lastTest.error ? (
                <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{lastTest.error}</p>
              ) : null}
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-500">Belum ada status koneksi.</p>
          )}
        </div>
      </form>

      {processProgress && !operationLocked ? (
        <ProcessProgressPanel progress={processProgress} />
      ) : null}

      <div className="space-y-6">
      <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Area Sinkronisasi</p>
            <h2 className="text-lg font-semibold text-slate-900">
              {activeDataTab === 'users' ? 'Pengguna SIAPS' : 'Kelas dan Anggota'}
            </h2>
            <p className="mt-1 text-sm text-slate-600">
              {activeDataTab === 'users'
                ? 'Kelola update user existing dan input user baru dari staging Dapodik.'
                : 'Kelola master kelas dan anggota kelas secara terpisah. Hanya kelas yang dipilih yang diproses.'}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {dataPanelTabs.map((tab) => (
              <button
                key={tab.value}
                type="button"
                onClick={() => setActiveDataTab(tab.value)}
                className={`rounded-lg border px-4 py-2 text-sm font-semibold ${
                  activeDataTab === tab.value
                    ? 'border-emerald-600 bg-emerald-600 text-white'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Review Mapping</p>
            <h2 className="text-lg font-semibold text-slate-900">Ringkasan Kecocokan</h2>
            <p className="mt-1 text-sm text-slate-600">
              Exact masuk jalur update. Unmatched masuk jalur input data baru. Probable dan conflict perlu dicek manual.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <select
              name="entity_type"
              value={reviewFilters.entity_type}
              onChange={handleReviewFilterChange}
              disabled={reviewingStaging}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            >
              {reviewEntityOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <select
              name="confidence"
              value={reviewFilters.confidence}
              onChange={handleReviewFilterChange}
              disabled={reviewingStaging}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            >
              {reviewConfidenceOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => loadStagingReview()}
              disabled={!stagingResult?.batch?.id || reviewingStaging}
              className="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Search className={`mr-2 h-4 w-4 ${reviewingStaging ? 'animate-spin' : ''}`} />
              {reviewingStaging ? 'Memuat...' : 'Muat Review'}
            </button>
          </div>
        </div>

        {stagingReview ? (
          <div className="mt-5 space-y-5">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
              <MetricBox label="Total Mapping" value={stagingReview.summary?.total_mappings ?? 0} />
              <MetricBox label="Exact" value={stagingReview.summary?.by_confidence?.exact ?? 0} />
              <MetricBox label="Probable" value={stagingReview.summary?.by_confidence?.probable ?? 0} />
              <MetricBox label="Conflict" value={stagingReview.summary?.by_confidence?.conflict ?? 0} />
              <MetricBox label="Unmatched" value={stagingReview.summary?.by_confidence?.unmatched ?? 0} />
              <MetricBox label="Manual Review" value={stagingReview.summary?.needs_manual_review ?? 0} />
            </div>

            <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
              <ReviewSummaryCard title="Siswa" summary={stagingReview.summary?.by_entity?.student} />
              <ReviewSummaryCard title="GTK" summary={stagingReview.summary?.by_entity?.employee} />
              <ReviewSummaryCard title="Kelas" summary={stagingReview.summary?.by_entity?.class} />
            </div>

            <details className="rounded-lg border border-slate-200 bg-white p-3">
              <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                Detail review mapping ({reviewItems.length} baris dimuat)
              </summary>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                  <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-3 py-2">Dapodik</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">SIAPS</th>
                    <th className="px-3 py-2">Perubahan</th>
                    <th className="px-3 py-2">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {pagedReviewItems.map((item) => (
                    <tr key={item.mapping_id} className="align-top">
                      <td className="min-w-72 px-3 py-3">
                        <div className="font-semibold text-slate-900">{item.name || '-'}</div>
                        <div className="mt-1 text-xs text-slate-500">{item.entity_type} | ID {item.dapodik_id || '-'}</div>
                        <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
                      </td>
                      <td className="px-3 py-3">
                        <ConfidenceBadge confidence={item.confidence} />
                        <div className="mt-2 text-xs text-slate-500">{item.match_key || '-'}</div>
                      </td>
                      <td className="min-w-60 px-3 py-3 text-slate-700">
                        {item.local ? (
                          <>
                            <div className="font-semibold text-slate-900">{item.local.name || '-'}</div>
                            <div className="mt-1 text-xs text-slate-500">
                              {item.local.type} #{item.local.id}
                              {item.local.roles?.length ? ` | ${item.local.roles.join(', ')}` : ''}
                            </div>
                          </>
                        ) : (
                          <span className="text-slate-400">Belum ada pasangan lokal</span>
                        )}
                      </td>
                      <td className="min-w-64 px-3 py-3">
                        {item.changes?.length ? (
                          <div className="flex flex-wrap gap-1">
                            {item.changes.slice(0, 8).map((field) => (
                              <span key={field} className="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{field}</span>
                            ))}
                            {item.changes.length > 8 ? (
                              <span className="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">+{item.changes.length - 8}</span>
                            ) : null}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">Tidak ada diff field utama</span>
                        )}
                      </td>
                      <td className="px-3 py-3">
                        <ReviewActionBadge action={item.recommended_action} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <PaginationControls
              total={reviewItems.length}
              page={reviewPage}
              onPageChange={setReviewPage}
            />
            </details>

            {stagingReview.has_more ? (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                Data masih ada lagi. Gunakan filter status/entity untuk mempersempit review.
              </div>
            ) : null}

            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
              Kebijakan proses: exact bisa diupdate, unmatched bisa diinput sebagai user baru, probable dan conflict ditahan untuk dicek manual.
            </div>
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500">Belum ada ringkasan. Jalankan ambil data Dapodik sampai finalisasi, lalu muat ringkasan.</p>
        )}
      </div>

      {activeDataTab === 'users' ? (
      <div className="grid grid-cols-1 gap-6 2xl:grid-cols-2">
      <div className="rounded-lg border border-sky-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">Jalur 1</p>
            <h2 className="text-lg font-semibold text-slate-900">Update Data</h2>
            <p className="mt-1 text-sm text-slate-600">
              Jalur ini mengubah user existing yang sudah cocok exact. Kelas dan rombel tidak ikut diubah.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <select
              name="entity_type"
              value={applyFilters.entity_type}
              onChange={handleApplyFilterChange}
              disabled={previewingApply}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            >
              {applyEntityOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => loadApplyPreview()}
              disabled={!stagingResult?.batch?.id || previewingApply || applyingApply}
              className="inline-flex items-center justify-center rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Search className={`mr-2 h-4 w-4 ${previewingApply ? 'animate-spin' : ''}`} />
              {previewingApply ? 'Menghitung...' : 'Preview Update'}
            </button>
            <button
              type="button"
              onClick={handleApplyFinal}
              disabled={
                !stagingResult?.batch?.id ||
                !applyPreview ||
                applyingApply ||
                previewingApply ||
                (applyPreview.summary?.update_candidates || 0) < 1
              }
              className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Save className={`mr-2 h-4 w-4 ${applyingApply ? 'animate-spin' : ''}`} />
              {applyingApply ? 'Mengupdate...' : 'Update Data'}
            </button>
          </div>
        </div>

        {applyPreview ? (
          <div className="mt-5 space-y-5">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
              <MetricBox label="Eligible" value={applyPreview.summary?.eligible ?? 0} />
              <MetricBox label="Update" value={applyPreview.summary?.update_candidates ?? 0} />
              <MetricBox label="No Change" value={applyPreview.summary?.no_change ?? 0} />
              <MetricBox label="Blocked" value={applyPreview.summary?.blocked ?? 0} />
              <MetricBox label="Field Diff" value={applyPreview.summary?.field_changes ?? 0} />
              <MetricBox label="Input" value={applyPreview.summary?.create_candidates ?? 0} />
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <MetricBox label="users" value={applyPreview.summary?.by_table?.users ?? 0} />
              <MetricBox label="data_pribadi_siswa" value={applyPreview.summary?.by_table?.data_pribadi_siswa ?? 0} />
              <MetricBox label="data_kepegawaian" value={applyPreview.summary?.by_table?.data_kepegawaian ?? 0} />
            </div>

            {applyResult ? (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div className="text-sm font-semibold text-emerald-950">Hasil Apply Terakhir</div>
                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                  <MetricBox label="Item Diupdate" value={applyResult.summary?.applied_items ?? 0} />
                  <MetricBox label="Field Diupdate" value={applyResult.summary?.applied_fields ?? 0} />
                  <MetricBox label="No Change" value={applyResult.summary?.no_change ?? 0} />
                  <MetricBox label="Tertahan" value={applyResult.summary?.blocked ?? 0} />
                  <MetricBox label="Field Manual" value={applyResult.summary?.skipped_unsafe_fields ?? 0} />
                </div>
                <p className="mt-3 text-xs text-emerald-900">
                  Field manual tidak diubah otomatis. Saat ini yang termasuk manual adalah email pegawai.
                </p>
                <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <ResultNameList
                    title="Nama yang berhasil diupdate"
                    items={resultNames(applyResult, 'applied')}
                    emptyText="Belum ada nama yang diupdate."
                  />
                  <ResultNameList
                    title="Nama yang tertahan"
                    items={resultNames(applyResult, 'blocked')}
                    emptyText="Tidak ada data tertahan."
                    tone="rose"
                  />
                </div>
              </div>
            ) : null}

            <details className="rounded-lg border border-slate-200 bg-white p-3">
              <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                Detail preview update ({applyPreviewItems.length} baris dimuat)
              </summary>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                  <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-3 py-2">Target</th>
                    <th className="px-3 py-2">Aksi</th>
                    <th className="px-3 py-2">Perubahan Field</th>
                    <th className="px-3 py-2">Blocker</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {pagedApplyPreviewItems.map((item) => (
                    <tr key={item.mapping_id} className="align-top">
                      <td className="min-w-72 px-3 py-3">
                        <div className="font-semibold text-slate-900">{item.name || item.local?.name || '-'}</div>
                        <div className="mt-1 text-xs text-slate-500">
                          {item.entity_type} | SIAPS #{item.siaps_user_id || '-'} | Dapodik {item.dapodik_id || '-'}
                        </div>
                        <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
                      </td>
                      <td className="px-3 py-3">
                        <ApplyActionBadge action={item.action} />
                        <div className="mt-2 text-xs text-slate-500">match: {item.match_key || '-'}</div>
                      </td>
                      <td className="min-w-[32rem] px-3 py-3">
                        {item.changes?.length ? (
                          <div className="space-y-2">
                            {item.changes.slice(0, 10).map((change, index) => (
                              <div key={`${change.table}-${change.field}-${index}`} className="rounded-lg bg-slate-50 p-2 text-xs text-slate-700">
                                <div className="font-semibold text-slate-900">
                                  {change.table}.{change.field}
                                  {!change.safe_auto_apply ? <span className="ml-2 text-amber-700">(review manual)</span> : null}
                                </div>
                                <div className="mt-1">
                                  <span className="text-slate-500">Lokal:</span> {formatChangeValue(change.current)}{' '}
                                  <span className="text-slate-500">Dapodik:</span> {formatChangeValue(change.incoming)}
                                </div>
                              </div>
                            ))}
                            {item.changes.length > 10 ? (
                              <div className="text-xs font-medium text-slate-500">+{item.changes.length - 10} field lagi</div>
                            ) : null}
                          </div>
                        ) : (
                          <span className="text-xs text-slate-500">Tidak ada perubahan field</span>
                        )}
                      </td>
                      <td className="min-w-56 px-3 py-3">
                        {item.blockers?.length ? (
                          <ul className="space-y-1 text-xs text-rose-700">
                            {item.blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
                          </ul>
                        ) : (
                          <span className="text-xs text-slate-500">-</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <PaginationControls
              total={applyPreviewItems.length}
              page={applyPreviewPage}
              onPageChange={setApplyPreviewPage}
            />
            </details>

            {applyPreview.has_more ? (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                Data preview masih ada lagi. Filter Siswa/GTK untuk mempersempit daftar.
              </div>
            ) : null}

            <div className="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
              Preview ini read-only. Field email pegawai ditandai review manual karena dapat memengaruhi login.
            </div>
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500">Belum ada preview update. Jalankan ambil data Dapodik sampai finalisasi, lalu klik Preview Update.</p>
        )}
      </div>

      <div className="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">Jalur 2</p>
            <h2 className="text-lg font-semibold text-slate-900">Input Data Baru</h2>
            <p className="mt-1 text-sm text-slate-600">
              Jalur ini membuat akun dan data detail dari data Dapodik yang belum punya pasangan lokal.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <select
              name="entity_type"
              value={inputFilters.entity_type}
              onChange={handleInputFilterChange}
              disabled={previewingInput}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            >
              {applyEntityOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => loadInputPreview()}
              disabled={!stagingResult?.batch?.id || previewingInput || inputtingData}
              className="inline-flex items-center justify-center rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Search className={`mr-2 h-4 w-4 ${previewingInput ? 'animate-spin' : ''}`} />
              {previewingInput ? 'Menghitung...' : 'Preview Input'}
            </button>
            <button
              type="button"
              onClick={handleInputFinal}
              disabled={
                !stagingResult?.batch?.id ||
                !inputPreview ||
                inputtingData ||
                previewingInput ||
                (inputPreview.summary?.create_candidates || 0) < 1
              }
              className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Save className={`mr-2 h-4 w-4 ${inputtingData ? 'animate-spin' : ''}`} />
              {inputtingData ? 'Menginput...' : 'Input Data Baru'}
            </button>
          </div>
        </div>

        {inputPreview ? (
          <div className="mt-5 space-y-5">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
              <MetricBox label="Eligible" value={inputPreview.summary?.eligible ?? 0} />
              <MetricBox label="Siap Input" value={inputPreview.summary?.create_candidates ?? 0} />
              <MetricBox label="Tertahan" value={inputPreview.summary?.blocked ?? 0} />
              <MetricBox label="Exact Dilewati" value={inputPreview.summary?.skipped?.existing_exact ?? 0} />
              <MetricBox label="Perlu Dicek" value={inputPreview.summary?.skipped?.needs_review ?? 0} />
            </div>

            {inputResult ? (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div className="text-sm font-semibold text-emerald-950">Hasil Input Terakhir</div>
                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
                  <MetricBox label="User Baru" value={inputResult.summary?.created_items ?? 0} />
                  <MetricBox label="Tertahan" value={inputResult.summary?.blocked ?? 0} />
                  <MetricBox label="Siswa" value={inputResult.summary?.by_entity?.student?.created_items ?? 0} />
                  <MetricBox label="GTK" value={inputResult.summary?.by_entity?.employee?.created_items ?? 0} />
                </div>
                <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <ResultNameList
                    title="Nama yang berhasil diinput"
                    items={resultNames(inputResult, 'created')}
                    emptyText="Belum ada nama yang berhasil diinput."
                  />
                  <ResultNameList
                    title="Nama yang tertahan"
                    items={resultNames(inputResult, 'blocked')}
                    emptyText="Tidak ada data tertahan."
                    tone="rose"
                  />
                </div>
              </div>
            ) : null}

            <details className="rounded-lg border border-slate-200 bg-white p-3">
              <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                Detail preview input ({inputPreviewItems.length} baris dimuat)
              </summary>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                  <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-3 py-2">Dapodik</th>
                    <th className="px-3 py-2">Akun</th>
                    <th className="px-3 py-2">Role</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Catatan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {pagedInputPreviewItems.map((item) => (
                    <tr key={item.mapping_id} className="align-top">
                      <td className="min-w-72 px-3 py-3">
                        <div className="font-semibold text-slate-900">{item.name || '-'}</div>
                        <div className="mt-1 text-xs text-slate-500">{item.entity_type} | Dapodik {item.dapodik_id || '-'}</div>
                        <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
                      </td>
                      <td className="min-w-64 px-3 py-3 text-xs text-slate-700">
                        <div><span className="text-slate-500">Username:</span> {item.account?.username || '-'}</div>
                        <div className="mt-1"><span className="text-slate-500">Email:</span> {item.account?.email || '-'}</div>
                        <div className="mt-1"><span className="text-slate-500">Password:</span> {item.account?.password_policy || '-'}</div>
                      </td>
                      <td className="px-3 py-3 text-slate-700">
                        <div className="font-semibold text-slate-900">{item.role?.name || '-'}</div>
                        {item.role?.suggested && item.role.suggested !== item.role.name ? (
                          <div className="mt-1 text-xs text-amber-700">Dapodik: {item.role.suggested}</div>
                        ) : null}
                      </td>
                      <td className="px-3 py-3">
                        <ApplyActionBadge action={item.action} />
                      </td>
                      <td className="min-w-56 px-3 py-3">
                        {item.blockers?.length ? (
                          <ul className="space-y-1 text-xs text-rose-700">
                            {item.blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
                          </ul>
                        ) : (
                          <span className="text-xs text-slate-500">Siap input</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <PaginationControls
              total={inputPreviewItems.length}
              page={inputPreviewPage}
              onPageChange={setInputPreviewPage}
            />
            </details>

            {inputPreview.has_more ? (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                Data input masih ada lagi. Filter Siswa/GTK untuk mempersempit daftar.
              </div>
            ) : null}

            <div className="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
              Input data baru tidak mengisi kelas otomatis. Relasi kelas tetap perlu proses terpisah agar riwayat kelas tidak tertimpa.
            </div>
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500">Belum ada preview input. Jalankan ambil data Dapodik sampai finalisasi, lalu klik Preview Input.</p>
        )}
      </div>
      </div>
      ) : (
      <div className="space-y-6">
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Sinkronisasi Kelas</p>
              <h2 className="text-lg font-semibold text-slate-900">
                {activeClassTab === 'master' ? 'Master Kelas' : 'Anggota Kelas'}
              </h2>
              <p className="mt-1 text-sm text-slate-600">
                {activeClassTab === 'master'
                  ? 'Preview seluruh kelas dulu, lalu pilih kelas mana saja yang akan diupdate atau diinput.'
                  : 'Anggota kelas hanya memakai kelas yang sudah dipilih pada tab master. Tidak semua kelas ikut diproses.'}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              {classPanelTabs.map((tab) => (
                <button
                  key={tab.value}
                  type="button"
                  onClick={() => setActiveClassTab(tab.value)}
                  className={`rounded-lg border px-4 py-2 text-sm font-semibold ${
                    activeClassTab === tab.value
                      ? 'border-sky-600 bg-sky-600 text-white'
                      : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Target Tahun Ajaran</p>
              <h3 className="mt-1 text-base font-semibold text-slate-900">Sinkronisasi kelas Dapodik akan menulis ke tahun ajaran ini</h3>
              <p className="mt-1 text-sm text-slate-600">
                Tahun ajaran dari Dapodik dipakai sebagai validasi. Preview dan sinkronisasi master kelas maupun anggota kelas mengikuti target SIAPS yang dipilih di sini.
              </p>
            </div>
            <div className="min-w-[280px] max-w-full">
              <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                Pilih tahun ajaran target
              </label>
              <select
                value={targetTahunAjaranId}
                onChange={handleTargetTahunAjaranChange}
                disabled={operationLocked || loadingTargetYears}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <option value="">Pilih Tahun Ajaran</option>
                {effectiveTahunAjaranOptions.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nama} | {item.status}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            <MetricBox label="Target SIAPS" value={selectedTargetTahunAjaran?.nama || '-'} />
            <MetricBox label="Status Target" value={selectedTargetTahunAjaran?.status || '-'} />
            <MetricBox label="Pilihan" value={selectedTargetTahunAjaran?.is_active ? 'Tahun aktif' : (selectedTargetTahunAjaran ? 'Tahun dipilih' : '-')} />
          </div>

          <p className="mt-3 text-xs text-slate-500">
            Mengubah target tahun ajaran akan mengosongkan preview kelas yang sedang terbuka agar hasil sinkronisasi tidak tercampur.
          </p>
        </div>

        {activeClassTab === 'master' ? (
          <div className="rounded-lg border border-sky-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">Master Kelas</p>
                <h2 className="text-lg font-semibold text-slate-900">Pilih Kelas yang Akan Diproses</h2>
                <p className="mt-1 text-sm text-slate-600">
                  Preview kelas tidak langsung mengeksekusi apa pun. Update dan input hanya berlaku untuk kelas yang dicentang.
                </p>
              </div>
              <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <input
                  type="text"
                  value={classSearch}
                  onChange={(event) => {
                    setClassSearch(event.target.value);
                    setClassPreviewPage(1);
                  }}
                  placeholder="Cari nama kelas, tingkat, jurusan"
                  className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                />
                <button
                  type="button"
                  onClick={() => loadClassPreview()}
                  disabled={!stagingResult?.batch?.id || !targetTahunAjaranId || previewingClasses || syncingClasses}
                  className="inline-flex items-center justify-center rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Search className={`mr-2 h-4 w-4 ${previewingClasses ? 'animate-spin' : ''}`} />
                  {previewingClasses ? 'Memuat...' : 'Preview Kelas'}
                </button>
                <button
                  type="button"
                  onClick={selectVisibleClasses}
                  disabled={!visibleClassItems.length || syncingClasses}
                  className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Pilih Tampil
                </button>
                <button
                  type="button"
                  onClick={clearSelectedClasses}
                  disabled={!selectedClassMappingIds.length}
                  className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Kosongkan
                </button>
                <button
                  type="button"
                  onClick={() => handleClassSync('update')}
                  disabled={!targetTahunAjaranId || !selectedUpdateClassItems.length || syncingClasses || previewingClasses}
                  className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Save className={`mr-2 h-4 w-4 ${syncingClasses ? 'animate-spin' : ''}`} />
                  Update Kelas
                </button>
                <button
                  type="button"
                  onClick={() => handleClassSync('input')}
                  disabled={!targetTahunAjaranId || !selectedCreateClassItems.length || syncingClasses || previewingClasses}
                  className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Save className={`mr-2 h-4 w-4 ${syncingClasses ? 'animate-spin' : ''}`} />
                  Input Kelas
                </button>
              </div>
            </div>

            {classPreview ? (
              <div className="mt-5 space-y-5">
                <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                  <MetricBox label="Total Kelas" value={classPreview.summary?.eligible ?? 0} />
                  <MetricBox label="Siap Update" value={classPreview.summary?.update_candidates ?? 0} />
                  <MetricBox label="Siap Input" value={classPreview.summary?.create_candidates ?? 0} />
                  <MetricBox label="Manual Review" value={classPreview.summary?.manual_review ?? 0} />
                  <MetricBox label="Tertahan" value={classPreview.summary?.blocked ?? 0} />
                  <MetricBox label="Dipilih" value={selectedClassItems.length} />
                </div>

                <div className="rounded-lg border border-sky-200 bg-sky-50 p-4">
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <MetricBox label="Dipilih Update" value={selectedUpdateClassItems.length} />
                    <MetricBox label="Dipilih Input" value={selectedCreateClassItems.length} />
                    <MetricBox label="Siap Anggota" value={selectedMembershipClassItems.length} />
                    <MetricBox label="Tampil" value={visibleClassItems.length} />
                  </div>
                </div>

                {classResult ? (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <div className="text-sm font-semibold text-emerald-950">Hasil Sinkronisasi Kelas Terakhir</div>
                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                      <MetricBox label="Diupdate" value={classResult.summary?.applied_items ?? 0} />
                      <MetricBox label="Dibuat" value={classResult.summary?.created_items ?? 0} />
                      <MetricBox label="Tidak Berubah" value={classResult.summary?.no_change ?? 0} />
                      <MetricBox label="Tertahan" value={classResult.summary?.blocked ?? 0} />
                      <MetricBox label="Field Ubah" value={classResult.summary?.applied_fields ?? 0} />
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                      <ResultNameList
                        title="Kelas yang berhasil diproses"
                        items={[
                          ...resultNamesFromItems(classResult.items || [], 'applied'),
                          ...resultNamesFromItems(classResult.items || [], 'created'),
                        ]}
                        emptyText="Belum ada kelas yang diproses."
                      />
                      <ResultNameList
                        title="Kelas yang tertahan"
                        items={resultNamesFromItems(classResult.items || [], 'blocked')}
                        emptyText="Tidak ada kelas tertahan."
                        tone="rose"
                      />
                    </div>
                  </div>
                ) : null}

                <details className="rounded-lg border border-slate-200 bg-white p-3">
                  <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                    Detail master kelas ({visibleClassItems.length} baris tampil)
                  </summary>
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                      <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th className="px-3 py-2">Pilih</th>
                        <th className="px-3 py-2">Kelas Dapodik</th>
                        <th className="px-3 py-2">Target SIAPS</th>
                        <th className="px-3 py-2">Aksi</th>
                        <th className="px-3 py-2">Perubahan / Catatan</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {pagedClassItems.map((item) => (
                        <tr key={item.mapping_id} className="align-top">
                          <td className="px-3 py-3">
                            <input
                              type="checkbox"
                              checked={selectedClassSet.has(item.mapping_id)}
                              onChange={() => toggleClassMappingSelection(item.mapping_id)}
                              className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            />
                          </td>
                          <td className="min-w-72 px-3 py-3">
                            <div className="font-semibold text-slate-900">{item.name || '-'}</div>
                            <div className="mt-1 text-xs text-slate-500">Dapodik {item.dapodik_id || '-'}</div>
                            <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
                          </td>
                          <td className="min-w-72 px-3 py-3 text-sm text-slate-700">
                            <div className="font-semibold text-slate-900">{item.local?.name || item.target?.nama_kelas || '-'}</div>
                            <div className="mt-1 text-xs text-slate-500">
                              {item.local?.tingkat || item.target?.tingkat?.nama || '-'}
                              {item.target?.jurusan ? ` | ${item.target.jurusan}` : ''}
                            </div>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                              {previewDetailLines(item).map((line) => (
                                <span key={`${item.mapping_id}-${line}`} className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700">
                                  {line}
                                </span>
                              ))}
                            </div>
                          </td>
                          <td className="px-3 py-3">
                            <ApplyActionBadge action={item.action} />
                            <div className="mt-2 text-xs text-slate-500">{item.match_key || '-'}</div>
                          </td>
                          <td className="min-w-72 px-3 py-3">
                            {item.changes?.length ? (
                              <div className="space-y-2">
                                {item.changes.map((change, index) => (
                                  <div key={`${change.table}-${change.field}-${index}`} className="rounded-lg bg-slate-50 p-2 text-xs text-slate-700">
                                    <div className="font-semibold text-slate-900">{change.table}.{change.field}</div>
                                    <div className="mt-1">
                                      <span className="text-slate-500">Lokal:</span> {formatChangeValue(change.current)}{' '}
                                      <span className="text-slate-500">Dapodik:</span> {formatChangeValue(change.incoming)}
                                    </div>
                                  </div>
                                ))}
                              </div>
                            ) : null}
                            {item.blockers?.length ? (
                              <ul className="space-y-1 text-xs text-rose-700">
                                {item.blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
                              </ul>
                            ) : null}
                            {!item.changes?.length && !item.blockers?.length && item.notes?.length ? (
                              <ul className="space-y-1 text-xs text-slate-600">
                                {item.notes.map((note) => <li key={note}>{note}</li>)}
                              </ul>
                            ) : null}
                            {!item.changes?.length && !item.blockers?.length && !item.notes?.length ? (
                              <span className="text-xs text-slate-500">Tidak ada catatan tambahan.</span>
                            ) : null}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <PaginationControls
                  total={visibleClassItems.length}
                  page={classPreviewPage}
                  onPageChange={setClassPreviewPage}
                />
                </details>

                {classPreview.has_more ? (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    Jumlah preview kelas melebihi batas tampilan. Persempit data atau proses bertahap per kelas.
                  </div>
                ) : null}
              </div>
            ) : (
              <p className="mt-4 text-sm text-slate-500">Belum ada preview kelas. Jalankan preview master kelas setelah staging selesai.</p>
            )}
          </div>
        ) : (
          <div className="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">Anggota Kelas</p>
                <h2 className="text-lg font-semibold text-slate-900">Sinkronisasi Anggota untuk Kelas Terpilih</h2>
                <p className="mt-1 text-sm text-slate-600">
                  Preview anggota hanya memakai kelas yang sudah dipilih dari tab master dan sudah siap dipakai untuk assignment.
                </p>
              </div>
              <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <button
                  type="button"
                  onClick={() => loadClassMembershipPreview()}
                  disabled={!targetTahunAjaranId || !selectedClassMappingIds.length || previewingClassMembers || syncingClassMembers}
                  className="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Search className={`mr-2 h-4 w-4 ${previewingClassMembers ? 'animate-spin' : ''}`} />
                  {previewingClassMembers ? 'Memuat...' : 'Preview Anggota'}
                </button>
                <button
                  type="button"
                  onClick={handleClassMembershipSync}
                  disabled={!targetTahunAjaranId || !selectedMembershipClassItems.length || previewingClassMembers || syncingClassMembers}
                  className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Save className={`mr-2 h-4 w-4 ${syncingClassMembers ? 'animate-spin' : ''}`} />
                  Sinkronkan Anggota
                </button>
              </div>
            </div>

            <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
              <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                <MetricBox label="Kelas Dipilih" value={selectedClassItems.length} />
                <MetricBox label="Kelas Siap Anggota" value={selectedMembershipClassItems.length} />
                <MetricBox label="Preview Anggota" value={classMembershipPreview?.summary?.eligible ?? 0} />
                <MetricBox label="Kandidat Anggota" value={(classMembershipPreview?.summary?.assign_candidates ?? 0) + (classMembershipPreview?.summary?.reactivate_candidates ?? 0)} />
              </div>
            </div>

            {classMembershipResult ? (
              <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div className="text-sm font-semibold text-emerald-950">Hasil Sinkronisasi Anggota Terakhir</div>
                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                  <MetricBox label="Ditambahkan" value={classMembershipResult.summary?.assigned_items ?? 0} />
                  <MetricBox label="Diaktifkan Ulang" value={classMembershipResult.summary?.reactivated_items ?? 0} />
                  <MetricBox label="Tidak Berubah" value={classMembershipResult.summary?.no_change ?? 0} />
                  <MetricBox label="Tertahan" value={classMembershipResult.summary?.blocked ?? 0} />
                  <MetricBox label="Kelas" value={selectedMembershipClassItems.length} />
                </div>
                <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                  <ResultNameList
                    title="Anggota yang berhasil diproses"
                    items={[
                      ...resultNamesFromItems(classMembershipResult.items || [], 'assigned'),
                      ...resultNamesFromItems(classMembershipResult.items || [], 'reactivated'),
                    ]}
                    emptyText="Belum ada anggota kelas yang diproses."
                  />
                  <ResultNameList
                    title="Anggota yang tertahan"
                    items={resultNamesFromItems(classMembershipResult.items || [], 'blocked')}
                    emptyText="Tidak ada anggota tertahan."
                    tone="rose"
                  />
                </div>
              </div>
            ) : null}

            {classMembershipPreview ? (
              <div className="mt-5 space-y-5">
                <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                  <MetricBox label="Total Baris" value={classMembershipPreview.summary?.eligible ?? 0} />
                  <MetricBox label="Tambah" value={classMembershipPreview.summary?.assign_candidates ?? 0} />
                  <MetricBox label="Aktifkan Ulang" value={classMembershipPreview.summary?.reactivate_candidates ?? 0} />
                  <MetricBox label="Tidak Berubah" value={classMembershipPreview.summary?.no_change ?? 0} />
                  <MetricBox label="Tertahan" value={classMembershipPreview.summary?.blocked ?? 0} />
                </div>

                <ClassMembershipReconciliationPanel reconciliation={classMembershipPreview.reconciliation} />

                <details className="rounded-lg border border-slate-200 bg-white p-3">
                  <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                    Detail anggota kelas ({classMembershipItems.length} baris dimuat)
                  </summary>
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                      <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th className="px-3 py-2">Kelas</th>
                        <th className="px-3 py-2">Siswa</th>
                        <th className="px-3 py-2">Status</th>
                        <th className="px-3 py-2">Kelas Aktif Saat Ini</th>
                        <th className="px-3 py-2">Catatan</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {pagedClassMembershipItems.map((item) => (
                        <tr key={item.key} className="align-top">
                          <td className="min-w-56 px-3 py-3">
                            <div className="font-semibold text-slate-900">{item.class?.name || '-'}</div>
                            <div className="mt-1 text-xs text-slate-500">SIAPS {item.class?.local_name || '-'}</div>
                          </td>
                          <td className="min-w-72 px-3 py-3">
                            <div className="font-semibold text-slate-900">{item.name || '-'}</div>
                            <div className="mt-1 text-xs text-slate-500">Dapodik {item.dapodik_id || '-'}</div>
                            <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
                          </td>
                          <td className="px-3 py-3">
                            <ApplyActionBadge action={item.action} />
                          </td>
                          <td className="min-w-56 px-3 py-3 text-slate-700">
                            {item.current_assignment?.class_name ? (
                              <>
                                <div className="font-semibold text-slate-900">{item.current_assignment.class_name}</div>
                                <div className="mt-1 text-xs text-slate-500">{item.current_assignment.status || '-'}</div>
                              </>
                            ) : (
                              <span className="text-xs text-slate-500">Belum ada</span>
                            )}
                          </td>
                          <td className="min-w-64 px-3 py-3">
                            {item.blockers?.length ? (
                              <ul className="space-y-1 text-xs text-rose-700">
                                {item.blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
                              </ul>
                            ) : item.notes?.length ? (
                              <ul className="space-y-1 text-xs text-slate-600">
                                {item.notes.map((note) => <li key={note}>{note}</li>)}
                              </ul>
                            ) : (
                              <span className="text-xs text-slate-500">Siap diproses</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <PaginationControls
                  total={classMembershipItems.length}
                  page={classMembershipPage}
                  onPageChange={setClassMembershipPage}
                />
                </details>

                {classMembershipPreview.has_more ? (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    Jumlah preview anggota melebihi batas tampilan. Kurangi jumlah kelas terpilih lalu preview ulang.
                  </div>
                ) : null}
              </div>
            ) : (
              <p className="mt-4 text-sm text-slate-500">
                {selectedClassMappingIds.length
                  ? 'Belum ada preview anggota kelas. Muat preview anggota setelah memilih kelas.'
                  : 'Pilih kelas pada tab master dulu, lalu muat preview anggota kelas.'}
              </p>
            )}
          </div>
        )}
      </div>
      )}

      <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Audit Staging</p>
            <h2 className="text-lg font-semibold text-slate-900">Ambil Data Dapodik</h2>
            <p className="mt-1 text-sm text-slate-600">
              Data diambil per sumber dulu, lalu difinalisasi menjadi jalur update dan input.
            </p>
          </div>
        {stagingResult?.batch ? (
            <StatusPill ok={stagingResult.batch.status === 'completed'} label={`Batch ${stagingResult.batch.status}`} />
          ) : null}
        </div>

        {stagingProgress ? (
          <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-sm font-semibold text-slate-900">
                  Progress ambil data {stagingProgress.batch?.id ? `batch ${stagingProgress.batch.id}` : ''}
                </div>
                <div className="mt-1 text-xs text-slate-600">
                  {fetchingStaging ? 'Proses sedang berjalan per sumber data.' : 'Proses terakhir selesai.'}
                </div>
              </div>
              <div className="text-sm font-bold text-slate-900">{stagingProgress.percent}%</div>
            </div>

            <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
              <div
                className="h-full rounded-full bg-emerald-600 transition-all duration-300"
                style={{ width: `${Math.min(100, Math.max(0, stagingProgress.percent))}%` }}
              />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
              {stagingProgress.steps.map((step) => (
                <div key={step.id} className="rounded-lg border border-slate-200 bg-white p-3">
                  <div className="flex items-center justify-between gap-3">
                    <div className="font-semibold text-slate-900">{step.label}</div>
                    <ProgressBadge status={step.status} />
                  </div>
                  <div className="mt-2 text-xs text-slate-600">{step.message || '-'}</div>
                  <div className="mt-2 text-xs text-slate-500">
                    Rows: <span className="font-semibold text-slate-700">{step.row_count || 0}</span> | Tersimpan:{' '}
                    <span className="font-semibold text-slate-700">{step.records_stored || 0}</span>
                  </div>
                  {step.error ? <div className="mt-2 text-xs font-medium text-rose-700">{step.error}</div> : null}
                </div>
              ))}

              <div className="rounded-lg border border-slate-200 bg-white p-3">
                <div className="flex items-center justify-between gap-3">
                  <div className="font-semibold text-slate-900">{stagingProgress.finalize.label}</div>
                  <ProgressBadge status={stagingProgress.finalize.status} />
                </div>
                <div className="mt-2 text-xs text-slate-600">{stagingProgress.finalize.message || '-'}</div>
              </div>
            </div>
          </div>
        ) : null}

        {stagingResult ? (
          <div className="mt-5 space-y-4">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
              <MetricBox label="Batch" value={stagingResult.batch?.id || '-'} />
              <MetricBox label="Records" value={stagingResult.batch?.totals?.records_stored ?? 0} />
              <MetricBox label="Sumber Berhasil" value={`${stagingResult.batch?.totals?.sources_successful ?? 0}/${stagingResult.batch?.totals?.sources_requested ?? 0}`} />
              <MetricBox label="Rombel Review" value={stagingResult.safeguards?.rombel_requires_review ? 'Wajib' : '-'} />
            </div>

            <details className="rounded-lg border border-slate-200 bg-white p-3">
              <summary className="cursor-pointer text-sm font-semibold text-slate-900">
                Detail endpoint sumber data
              </summary>
              <div className="mt-3 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                  <thead>
                    <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                      <th className="px-3 py-2">Sumber</th>
                      <th className="px-3 py-2">Endpoint</th>
                      <th className="px-3 py-2">Status</th>
                      <th className="px-3 py-2">Rows</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {Object.entries(stagingResult.fetches || {}).map(([key, fetch]) => (
                      <tr key={key}>
                        <td className="px-3 py-3 font-semibold text-slate-900">{key}</td>
                        <td className="px-3 py-3 text-slate-700">{fetch.endpoint}</td>
                        <td className="px-3 py-3">
                          <StatusPill ok={fetch.success} label={fetch.success ? `HTTP ${fetch.status_code}` : 'Gagal'} />
                        </td>
                        <td className="px-3 py-3 text-slate-700">{fetch.row_count ?? 0}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </details>

            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
              Final table tidak disentuh: {(stagingResult.safeguards?.final_tables_untouched || []).join(', ')}.
            </div>
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500">Belum ada batch data. Jalankan Ambil Data Dapodik setelah koneksi siap.</p>
        )}
      </div>
      </div>

    </div>
  );
};

const Field = ({ label, children }) => (
  <label className="block">
    <span className="mb-2 block text-sm font-medium text-slate-700">{label}</span>
    {children}
  </label>
);

const InfoLine = ({ label, value }) => (
  <div className="flex items-center justify-between gap-3 text-sm">
    <span className="text-slate-500">{label}</span>
    <span className="text-right font-semibold text-slate-900">{value}</span>
  </div>
);

const StatusPill = ({ ok, label }) => (
  <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${ok ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
    {label}
  </span>
);

const WorkflowStageCard = ({ step, title, description, state, meta }) => {
  const styles = {
    ready: 'border-emerald-200 bg-emerald-50/70',
    running: 'border-sky-200 bg-sky-50/80',
    idle: 'border-slate-200 bg-slate-50',
  };

  const labels = {
    ready: 'Siap',
    running: 'Berjalan',
    idle: 'Menunggu',
  };

  return (
    <div className={`rounded-lg border p-4 ${styles[state] || styles.idle}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tahap {step}</div>
          <div className="mt-2 text-base font-semibold text-slate-900">{title}</div>
        </div>
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${state === 'ready' ? 'bg-emerald-100 text-emerald-800' : state === 'running' ? 'bg-sky-100 text-sky-800' : 'bg-slate-200 text-slate-700'}`}>
          {labels[state] || labels.idle}
        </span>
      </div>
      <p className="mt-3 text-sm leading-6 text-slate-600">{description}</p>
      <div className="mt-3 text-xs font-medium text-slate-500">{meta}</div>
    </div>
  );
};

const ConfirmationDialog = ({ config, onCancel, onConfirm }) => {
  const names = config.names || [];
  const visibleNames = names.slice(0, CONFIRMATION_ITEM_PREVIEW_LIMIT);
  const hiddenCount = Math.max(0, names.length - visibleNames.length);

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-slate-950/40">
      <div className="flex h-full w-full max-w-xl flex-col bg-white shadow-xl">
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          <div className="flex items-start gap-3">
            <div className="rounded-lg bg-amber-50 p-2 text-amber-700">
              <AlertTriangle className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <h2 className="text-lg font-semibold text-slate-900">{config.title}</h2>
              <p className="mt-2 text-sm leading-6 text-slate-600">{config.description}</p>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <MetricBox label={config.countLabel || 'Total'} value={config.count ?? 0} />
            <MetricBox label="Batch" value={config.batchId || '-'} />
          </div>

          {visibleNames.length > 0 ? (
            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
              <div className="text-sm font-semibold text-slate-900">{config.namesTitle || 'Nama yang diproses'}</div>
              <div className="mt-3 max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                {visibleNames.map((item) => (
                  <div key={item.key} className="rounded-lg bg-white p-2 text-sm">
                    <div className="font-semibold text-slate-900">{item.name}</div>
                    <div className="mt-1 text-xs text-slate-500">{item.meta || '-'}</div>
                    {item.detailLines?.length ? (
                      <div className="mt-2 flex flex-wrap gap-1.5">
                        {item.detailLines.map((line) => (
                          <span key={line} className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700">
                            {line}
                          </span>
                        ))}
                      </div>
                    ) : item.notes ? (
                      <div className="mt-1 text-xs text-slate-600">{item.notes}</div>
                    ) : null}
                  </div>
                ))}
              </div>
              {hiddenCount > 0 ? (
                <div className="mt-2 text-xs font-medium text-slate-500">+{hiddenCount} nama lagi</div>
              ) : null}
            </div>
          ) : null}

          {config.warning ? (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              {config.warning}
            </div>
          ) : null}
        </div>

        <div className="flex flex-col-reverse gap-2 border-t border-slate-200 bg-white p-4 sm:flex-row sm:justify-end">
          <button
            type="button"
            onClick={onCancel}
            className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          >
            Batal
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {config.confirmText || 'Lanjutkan'}
          </button>
        </div>
      </div>
    </div>
  );
};

const OperationLockOverlay = ({ progress }) => (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/85 px-4 py-6 backdrop-blur-sm">
    <div className="w-full max-w-5xl">
      {progress ? (
        <ProcessProgressPanel progress={progress} />
      ) : (
        <div className="rounded-lg border border-slate-200 bg-white p-5 text-center shadow-xl">
          <RefreshCw className="mx-auto h-6 w-6 animate-spin text-emerald-700" />
          <div className="mt-3 text-sm font-semibold text-slate-900">Proses sedang berjalan</div>
          <div className="mt-1 text-sm text-slate-600">Halaman dikunci sampai proses selesai.</div>
        </div>
      )}
    </div>
  </div>
);

const ProcessProgressPanel = ({ progress }) => (
  <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 className="text-lg font-semibold text-slate-900">Progress Proses</h2>
        <p className="mt-1 text-sm text-slate-600">{progress.title}</p>
      </div>
      <div className="text-sm font-bold text-slate-900">{progress.percent || 0}%</div>
    </div>
    <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
      <div
        className={`h-full rounded-full transition-all duration-300 ${progress.status === 'failed' ? 'bg-rose-600' : 'bg-emerald-600'}`}
        style={{ width: `${Math.min(100, Math.max(0, progress.percent || 0))}%` }}
      />
    </div>
    <div className="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
      {(progress.steps || []).map((step) => (
        <div key={step.label} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
          <div className="flex items-center justify-between gap-3">
            <div className="font-semibold text-slate-900">{step.label}</div>
            <ProgressBadge status={step.status} />
          </div>
          <div className="mt-2 text-xs text-slate-600">{step.message || '-'}</div>
        </div>
      ))}
    </div>
    {progress.items?.length ? (
      <div className="mt-4">
        <ResultNameList
          title={progress.itemsTitle || 'Nama yang diproses'}
          items={progress.items}
          emptyText="Belum ada nama."
          maxItems={PROCESS_ITEM_PREVIEW_LIMIT}
        />
      </div>
    ) : null}
    {progress.errorItems?.length ? (
      <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
        <div className="text-sm font-semibold text-rose-950">{progress.errorTitle || 'Lokasi error'}</div>
        <div className="mt-3 space-y-2">
          {progress.errorItems.map((item) => (
            <div key={`${item.label}-${item.value}`} className="text-sm text-rose-900">
              <span className="font-semibold">{item.label}:</span> {item.value}
            </div>
          ))}
        </div>
      </div>
    ) : null}
  </div>
);

const ProgressBadge = ({ status }) => {
  const styles = {
    completed: 'bg-emerald-100 text-emerald-800',
    running: 'bg-sky-100 text-sky-800',
    failed: 'bg-rose-100 text-rose-800',
    queued: 'bg-slate-100 text-slate-600',
  };
  const labels = {
    completed: 'Selesai',
    running: 'Berjalan',
    failed: 'Gagal',
    queued: 'Antri',
  };

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${styles[status] || styles.queued}`}>
      {labels[status] || status || 'Antri'}
    </span>
  );
};

const MetricBox = ({ label, value }) => (
  <div className="rounded-lg bg-slate-50 p-3">
    <div className="text-xs text-slate-500">{label}</div>
    <div className="mt-1 text-lg font-bold text-slate-900">{value}</div>
  </div>
);

const PaginationControls = ({ total, page, onPageChange, pageSize = DAPODIK_TABLE_PAGE_SIZE }) => {
  const pageCount = Math.max(1, Math.ceil((total || 0) / pageSize));
  const safePage = Math.min(Math.max(1, page || 1), pageCount);
  const start = total ? ((safePage - 1) * pageSize) + 1 : 0;
  const end = total ? Math.min(total, safePage * pageSize) : 0;

  return (
    <div className="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
      <div>
        Menampilkan {start}-{end} dari {total || 0} baris
      </div>
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onPageChange(safePage - 1)}
          disabled={safePage <= 1}
          className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Sebelumnya
        </button>
        <span className="font-semibold text-slate-700">{safePage}/{pageCount}</span>
        <button
          type="button"
          onClick={() => onPageChange(safePage + 1)}
          disabled={safePage >= pageCount}
          className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Berikutnya
        </button>
      </div>
    </div>
  );
};

const ResultNameList = ({ title, items, emptyText, tone = 'emerald', maxItems = 20 }) => {
  const styles = tone === 'rose'
    ? 'border-rose-200 bg-rose-50 text-rose-950'
    : 'border-emerald-200 bg-white text-slate-900';
  const visibleItems = maxItems ? (items || []).slice(0, maxItems) : (items || []);
  const hiddenCount = Math.max(0, (items?.length || 0) - visibleItems.length);

  return (
    <div className={`rounded-lg border p-3 ${styles}`}>
      <div className="text-sm font-semibold">{title}</div>
      {items?.length ? (
        <div className="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
          {visibleItems.map((item) => (
            <div key={item.key} className="rounded-lg bg-white/80 p-2 text-sm">
              <div className="font-semibold text-slate-900">{item.name}</div>
              <div className="mt-1 text-xs text-slate-500">{item.meta || '-'}</div>
              {item.detailLines?.length ? (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {item.detailLines.map((line) => (
                    <span key={line} className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700">
                      {line}
                    </span>
                  ))}
                </div>
              ) : item.notes ? (
                <div className="mt-1 text-xs text-slate-600">{item.notes}</div>
              ) : null}
            </div>
          ))}
          {hiddenCount > 0 ? (
            <div className="text-xs font-medium text-slate-500">+{hiddenCount} data lagi</div>
          ) : null}
        </div>
      ) : (
        <div className="mt-2 text-xs text-slate-500">{emptyText}</div>
      )}
    </div>
  );
};

const ReconciliationSampleList = ({ title, items = [], emptyText }) => (
  <div className="rounded-lg border border-slate-200 bg-white p-3">
    <div className="text-sm font-semibold text-slate-900">{title}</div>
    {items.length ? (
      <div className="mt-3 max-h-56 space-y-2 overflow-y-auto pr-1">
        {items.map((item, index) => (
          <div key={`${item.dapodik_id || item.name || 'item'}-${index}`} className="rounded-lg bg-slate-50 p-2 text-sm">
            <div className="font-semibold text-slate-900">{item.name || 'Tanpa nama'}</div>
            <div className="mt-1 text-xs text-slate-500">
              {item.class_name ? `Kelas: ${item.class_name}` : `Dapodik: ${item.dapodik_id || '-'}`}
            </div>
            <div className="mt-2 text-xs text-slate-600">{formatReviewIdentifiers(item.identifiers)}</div>
            {item.blockers?.length ? (
              <ul className="mt-2 space-y-1 text-xs text-rose-700">
                {item.blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
              </ul>
            ) : null}
          </div>
        ))}
      </div>
    ) : (
      <div className="mt-2 text-xs text-slate-500">{emptyText}</div>
    )}
  </div>
);

const ClassMembershipReconciliationPanel = ({ reconciliation }) => {
  if (!reconciliation) {
    return null;
  }

  const masterVsMemberDelta = reconciliation.students_not_in_selected_members ?? 0;
  const hasSamples = Boolean(
    reconciliation.students_not_in_selected_members_sample?.length
    || reconciliation.blocked_missing_mapping_sample?.length
    || reconciliation.blocked_empty_student_id_sample?.length
  );

  return (
    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Rekonsiliasi</p>
        <h3 className="text-base font-semibold text-amber-950">Cek selisih siswa Dapodik dan anggota rombel</h3>
        <p className="mt-1 text-sm text-amber-900">
          Total master siswa dan anggota rombel bisa berbeda. Panel ini menunjukkan sumber selisih sebelum sinkronisasi anggota kelas.
        </p>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <MetricBox label="Siswa Exact" value={reconciliation.exact_student_mappings ?? 0} />
        <MetricBox label="Baris Anggota" value={reconciliation.member_rows ?? 0} />
        <MetricBox label="ID Siswa Unik" value={reconciliation.unique_member_student_ids ?? 0} />
        <MetricBox label="Anggota Terpetakan" value={reconciliation.mapped_member_rows ?? 0} />
        <MetricBox label="Tidak Ada di Rombel" value={masterVsMemberDelta} />
        <MetricBox label="Gagal Mapping" value={reconciliation.blocked_missing_mapping ?? 0} />
      </div>

      {reconciliation.blocked_empty_student_id ? (
        <div className="mt-3 rounded-lg border border-rose-200 bg-white p-3 text-sm text-rose-800">
          {reconciliation.blocked_empty_student_id} baris anggota rombel tidak punya peserta_didik_id, jadi tidak dipakai sebagai ID siswa.
        </div>
      ) : null}

      {hasSamples ? (
        <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
          <ReconciliationSampleList
            title="Siswa exact tidak ada di rombel terpilih"
            items={reconciliation.students_not_in_selected_members_sample || []}
            emptyText="Tidak ada sampel selisih master siswa."
          />
          <ReconciliationSampleList
            title="Anggota rombel gagal mapping"
            items={reconciliation.blocked_missing_mapping_sample || []}
            emptyText="Tidak ada anggota gagal mapping."
          />
          <ReconciliationSampleList
            title="Anggota tanpa peserta_didik_id"
            items={reconciliation.blocked_empty_student_id_sample || []}
            emptyText="Tidak ada anggota tanpa peserta_didik_id."
          />
        </div>
      ) : null}
    </div>
  );
};

const ReviewSummaryCard = ({ title, summary }) => {
  const items = [
    ['Total', summary?.total ?? 0],
    ['Exact', summary?.exact ?? 0],
    ['Probable', summary?.probable ?? 0],
    ['Conflict', summary?.conflict ?? 0],
    ['Unmatched', summary?.unmatched ?? 0],
  ];

  return (
    <div className="rounded-lg border border-slate-200 p-4">
      <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
      <div className="mt-3 grid grid-cols-2 gap-2">
        {items.map(([label, value]) => (
          <div key={label} className="rounded-lg bg-slate-50 p-3">
            <div className="text-xs text-slate-500">{label}</div>
            <div className="mt-1 text-base font-bold text-slate-900">{value}</div>
          </div>
        ))}
      </div>
    </div>
  );
};

const ConfidenceBadge = ({ confidence }) => {
  const styles = {
    exact: 'bg-emerald-100 text-emerald-800',
    probable: 'bg-sky-100 text-sky-800',
    conflict: 'bg-rose-100 text-rose-800',
    unmatched: 'bg-amber-100 text-amber-800',
  };

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${styles[confidence] || 'bg-slate-100 text-slate-700'}`}>
      {confidence || '-'}
    </span>
  );
};

const ReviewActionBadge = ({ action }) => {
  const labels = {
    update_candidate: 'Kandidat Update',
    no_change: 'Tidak Berubah',
    manual_review: 'Review Manual',
    resolve_conflict: 'Selesaikan Konflik',
    create_candidate: 'Kandidat Create',
    blocked: 'Tertahan',
    review_class_mapping: 'Review Kelas',
  };
  const risky = ['manual_review', 'resolve_conflict', 'blocked', 'review_class_mapping'].includes(action);

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${risky ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}>
      {labels[action] || action || '-'}
    </span>
  );
};

const ApplyActionBadge = ({ action }) => {
  const labels = {
    update_candidate: 'Kandidat Update',
    create_candidate: 'Kandidat Input',
    assign_candidate: 'Kandidat Tambah',
    reactivate_candidate: 'Kandidat Reaktivasi',
    no_change: 'Tidak Berubah',
    blocked: 'Tertahan',
    manual_review: 'Review Manual',
    applied: 'Diupdate',
    created: 'Dibuat',
    assigned: 'Ditambahkan',
    reactivated: 'Diaktifkan Ulang',
    skipped: 'Dilewati',
  };
  const styles = {
    update_candidate: 'bg-emerald-100 text-emerald-800',
    create_candidate: 'bg-emerald-100 text-emerald-800',
    assign_candidate: 'bg-emerald-100 text-emerald-800',
    reactivate_candidate: 'bg-sky-100 text-sky-800',
    no_change: 'bg-slate-100 text-slate-700',
    blocked: 'bg-rose-100 text-rose-800',
    manual_review: 'bg-amber-100 text-amber-800',
    applied: 'bg-emerald-100 text-emerald-800',
    created: 'bg-emerald-100 text-emerald-800',
    assigned: 'bg-emerald-100 text-emerald-800',
    reactivated: 'bg-sky-100 text-sky-800',
    skipped: 'bg-slate-100 text-slate-700',
  };

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${styles[action] || styles.no_change}`}>
      {labels[action] || action || '-'}
    </span>
  );
};

const formatReviewIdentifiers = (identifiers = {}) => {
  const text = Object.entries(identifiers || {})
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${identifierLabelMap[key] || key}: ${value}`)
    .join(' | ');

  return text || '-';
};

const formatChangeValue = (value) => {
  if (value === null || value === undefined || value === '') return '-';
  return String(value);
};

export default DapodikConnectionTest;

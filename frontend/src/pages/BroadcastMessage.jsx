import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent } from '@mui/material';
import { useSnackbar } from 'notistack';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  AlertCircle,
  BellRing,
  CheckCircle2,
  Eye,
  History,
  Image as ImageIcon,
  Link2,
  LayoutTemplate,
  Mail,
  Megaphone,
  MessageSquare,
  Newspaper,
  ShieldAlert,
  RefreshCw,
  Save,
  Send,
  Sparkles,
  Users,
  X,
} from 'lucide-react';
import { academicContextAPI, attendanceDisciplineCasesAPI, broadcastCampaignsAPI, kelasAPI, rolesAPI, whatsappAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import useServerClock from '../hooks/useServerClock';
import { formatServerDateTime, getServerDateString, getServerIsoString } from '../services/serverClock';

const STORAGE_KEY = 'broadcast-message.draft.v3';

const isDateInputValue = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || '').trim());

const addDaysToDateInput = (value, days) => {
  if (!isDateInputValue(value)) {
    return '';
  }

  const [year, month, day] = String(value).split('-').map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  date.setUTCDate(date.getUTCDate() + Number(days || 0));

  return [
    date.getUTCFullYear(),
    String(date.getUTCMonth() + 1).padStart(2, '0'),
    String(date.getUTCDate()).padStart(2, '0'),
  ].join('-');
};

const formatDateTime = (value) => formatServerDateTime(value, 'id-ID') || '-';

const defaultDisplayEndDate = () => {
  return addDaysToDateInput(getServerDateString(), 14);
};

const createDefaultDraft = () => ({
  title: '',
  message: '',
  tone: 'info',
  messageCategory: 'announcement',
  audienceMode: 'all',
  targetRole: '',
  targetKelasId: '',
  targetUserId: '',
  targetUserLabel: '',
  manualRecipients: '',
  disciplineCaseId: null,
  displayStartDate: '',
  displayEndDate: defaultDisplayEndDate(),
  channels: { inApp: true, whatsapp: false, popup: true, email: false },
  internalTargets: { web: true, mobile: true },
  popup: {
    variant: 'info',
    title: '',
    imageUrl: '',
    dismissLabel: 'Tutup',
    ctaLabel: '',
    ctaUrl: '',
    sticky: false,
  },
  whatsapp: { footer: '' },
  email: { subject: '' },
});

const createDefaultDisciplineFilters = () => ({
  status: 'all',
  parentPhone: 'all',
  search: '',
  triggeredFrom: '',
  triggeredTo: '',
});

const createDefaultDisciplineSummary = () => ({
  total: 0,
  ready_for_parent_broadcast: 0,
  parent_broadcast_sent: 0,
  parent_phone_available: 0,
  parent_phone_missing: 0,
});

const createDefaultHistoryFilters = () => ({
  status: 'all',
  messageCategory: 'all',
  createdFrom: '',
  createdTo: '',
});

const audienceOptions = {
  all: 'Semua pengguna aktif',
  role: 'Per role',
  class: 'Per kelas',
  user: 'Satu siswa / orang tua',
  manual: 'Nomor WA manual',
};

const templates = [
  ['general', 'Pengumuman Umum'],
  ['important', 'Info Penting'],
  ['flyer', 'Flyer Acara'],
  ['wa', 'Reminder WA'],
];

const messageCategoryOptions = [
  ['announcement', 'Pengumuman'],
  ['system', 'Pesan Sistem'],
];

const statusPill = {
  sent: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  partial: 'bg-amber-50 text-amber-700 border-amber-200',
  failed: 'bg-rose-50 text-rose-700 border-rose-200',
  skipped: 'bg-slate-100 text-slate-600 border-slate-200',
  processing: 'bg-blue-50 text-blue-700 border-blue-200',
};

const disciplineCaseStatusPill = {
  ready_for_parent_broadcast: 'bg-amber-50 text-amber-700 border-amber-200',
  parent_broadcast_sent: 'bg-emerald-50 text-emerald-700 border-emerald-200',
};

const disciplineCaseStatusLabel = {
  ready_for_parent_broadcast: 'Siap untuk broadcast orang tua',
  parent_broadcast_sent: 'Broadcast orang tua sudah dikirim',
};

const disciplineRuleLabel = (item) => {
  const payloadLabel = String(item?.payload?.rule_label || '').trim();
  if (payloadLabel) return payloadLabel;

  switch (item?.rule_key) {
    case 'monthly_late_limit':
      return 'Keterlambatan Bulanan';
    case 'semester_total_violation_limit':
      return 'Total Pelanggaran Semester';
    default:
      return 'Alpha Semester';
  }
};

const disciplineMetricUnit = (item) => {
  const payloadUnit = String(item?.payload?.metric_unit || item?.metric_unit || '').trim();
  if (payloadUnit) return payloadUnit;
  return item?.rule_key === 'semester_alpha_limit' ? 'hari' : 'menit';
};

const disciplineMetricTone = (item) => (
  item?.rule_key === 'semester_alpha_limit'
    ? 'bg-amber-50 text-amber-700'
    : item?.rule_key === 'monthly_late_limit'
      ? 'bg-orange-50 text-orange-700'
      : 'bg-rose-50 text-rose-700'
);

const disciplinePeriodLabel = (item) =>
  String(item?.period_label || item?.payload?.period_label || item?.payload?.semester_label || item?.semester || '-');

const readList = (response) => {
  const rows = response?.data?.data?.data || response?.data?.data || response?.data || [];
  return Array.isArray(rows) ? rows : [];
};

const parseManualRecipients = (value) =>
  Array.from(new Set(String(value || '').split(/[\n,;]+/).map((item) => item.trim()).filter(Boolean)));

const isValidHttpUrl = (value) => {
  try {
    const parsed = new URL(String(value || '').trim());
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch (_error) {
    return false;
  }
};

const extractApiErrorMessage = (error, fallbackMessage) => {
  const responseData = error?.response?.data;
  const fieldErrors = responseData?.errors;

  if (fieldErrors && typeof fieldErrors === 'object') {
    const firstFieldErrors = Object.values(fieldErrors).find((value) => Array.isArray(value) && value.length > 0);
    if (Array.isArray(firstFieldErrors) && firstFieldErrors[0]) {
      return String(firstFieldErrors[0]);
    }
  }

  return responseData?.message || error?.message || fallbackMessage;
};

const downloadBlobResponse = (response, fallbackFileName) => {
  const blob = response?.data;
  if (!(blob instanceof Blob)) {
    throw new Error('File export tidak valid');
  }

  const disposition = response?.headers?.['content-disposition'] || '';
  const matched = disposition.match(/filename="?([^"]+)"?/i);
  const fileName = matched?.[1] || fallbackFileName;
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(url);
};

const BroadcastMessage = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { hasPermission } = useAuth();
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const { enqueueSnackbar } = useSnackbar();
  const canSend = hasPermission('send_broadcast_campaigns');
  const canReadWaStatus = hasPermission('manage_whatsapp');
  const canUseEmail = canSend;

  const [draft, setDraft] = useState(createDefaultDraft);
  const [roles, setRoles] = useState([]);
  const [classes, setClasses] = useState([]);
  const [recentCampaigns, setRecentCampaigns] = useState([]);
  const [disciplineCases, setDisciplineCases] = useState([]);
  const [disciplineFilters, setDisciplineFilters] = useState(createDefaultDisciplineFilters);
  const [disciplineSummary, setDisciplineSummary] = useState(createDefaultDisciplineSummary);
  const [historyFilters, setHistoryFilters] = useState(createDefaultHistoryFilters);
  const [academicContext, setAcademicContext] = useState({ tahunAjaran: '-', periode: '-' });
  const [waStatus, setWaStatus] = useState({ configured: false, connected: false, message: 'Status gateway belum dimuat.' });
  const [workspaceTab, setWorkspaceTab] = useState('compose');
  const [previewTab, setPreviewTab] = useState('popup');
  const [popupPreviewOpen, setPopupPreviewOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [disciplineLoading, setDisciplineLoading] = useState(false);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [exportingDiscipline, setExportingDiscipline] = useState('');
  const [sending, setSending] = useState(false);
  const [lastSummary, setLastSummary] = useState(null);
  const [flyerUpload, setFlyerUpload] = useState({
    uploading: false,
    fileName: '',
    error: '',
  });

  useEffect(() => {
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      if (!stored) return;
      const parsed = JSON.parse(stored);
      const defaults = createDefaultDraft();
      setDraft({
        ...defaults,
        ...parsed,
        channels: { ...defaults.channels, ...(parsed?.channels || {}) },
        internalTargets: { ...defaults.internalTargets, ...(parsed?.internalTargets || {}) },
        popup: { ...defaults.popup, ...(parsed?.popup || {}) },
        whatsapp: { ...defaults.whatsapp, ...(parsed?.whatsapp || {}) },
        email: { ...defaults.email, ...(parsed?.email || {}) },
      });
    } catch (_error) {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  }, []);

  useEffect(() => {
    if (!isServerClockSynced || !serverDate) {
      return;
    }

    setDraft((current) => {
      if (current.displayEndDate) {
        return current;
      }

      return {
        ...current,
        displayEndDate: addDaysToDateInput(serverDate, 14),
      };
    });
  }, [isServerClockSynced, serverDate]);

  const updateField = (path, value) => {
    setDraft((prev) => {
      const next = structuredClone(prev);
      const keys = path.split('.');
      let cursor = next;
      for (let index = 0; index < keys.length - 1; index += 1) cursor = cursor[keys[index]];
      cursor[keys[keys.length - 1]] = value;
      return next;
    });
  };

  const refreshRecentCampaigns = async ({ silent = false, filters = null } = {}) => {
    try {
      setHistoryLoading(true);
      const activeFilters = filters || historyFilters;
      const response = await broadcastCampaignsAPI.getAll({
        per_page: 12,
        ...(activeFilters.status !== 'all' ? { status: activeFilters.status } : {}),
        ...(activeFilters.messageCategory !== 'all' ? { message_category: activeFilters.messageCategory } : {}),
        ...(activeFilters.createdFrom ? { created_from: activeFilters.createdFrom } : {}),
        ...(activeFilters.createdTo ? { created_to: activeFilters.createdTo } : {}),
      });
      setRecentCampaigns(readList(response));
    } catch (_error) {
      if (!silent) enqueueSnackbar('Riwayat broadcast gagal dimuat', { variant: 'warning' });
    } finally {
      setHistoryLoading(false);
    }
  };

  const refreshDisciplineCases = async ({ silent = false, filters = null } = {}) => {
    try {
      setDisciplineLoading(true);
      const activeFilters = filters || disciplineFilters;
      const response = await attendanceDisciplineCasesAPI.getAll({
        per_page: 12,
        ...(activeFilters.status !== 'all' ? { status: activeFilters.status } : {}),
        ...(activeFilters.parentPhone !== 'all' ? { parent_phone: activeFilters.parentPhone } : {}),
        ...(activeFilters.search.trim() ? { search: activeFilters.search.trim() } : {}),
        ...(activeFilters.triggeredFrom ? { triggered_from: activeFilters.triggeredFrom } : {}),
        ...(activeFilters.triggeredTo ? { triggered_to: activeFilters.triggeredTo } : {}),
      });
      setDisciplineCases(readList(response));
      setDisciplineSummary(response?.data?.meta?.summary || createDefaultDisciplineSummary());
    } catch (_error) {
      if (!silent) enqueueSnackbar('Histori alert pelanggaran gagal dimuat', { variant: 'warning' });
    } finally {
      setDisciplineLoading(false);
    }
  };

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const [roleRes, classRes, contextRes, campaignRes, disciplineCaseRes] = await Promise.allSettled([
          rolesAPI.getAll(),
          kelasAPI.getAll({ tahun_ajaran_status: 'active' }),
          academicContextAPI.getCurrent(),
          broadcastCampaignsAPI.getAll({ per_page: 8 }),
          attendanceDisciplineCasesAPI.getAll({ per_page: 12 }),
        ]);

        if (roleRes.status === 'fulfilled') {
          setRoles(readList(roleRes.value).map((row) => ({ name: row.name, label: row.display_name || row.name })).filter((row) => row.name));
        }

        if (classRes.status === 'fulfilled') {
          setClasses(
            readList(classRes.value)
              .map((row) => ({
                id: row.id,
                label: row.namaKelas || row.nama_kelas || '-',
                tahunAjaran: row.tahunAjaran?.nama || row.tahun_ajaran?.nama || row.tahun_ajaran_nama || '-',
              }))
              .filter((row) => row.id)
          );
        }

        if (contextRes.status === 'fulfilled') {
          const payload = contextRes.value?.data?.data || {};
          setAcademicContext({
            tahunAjaran: payload?.tahun_ajaran?.nama || '-',
            periode: payload?.periode_aktif?.nama || '-',
          });
        }

        if (campaignRes.status === 'fulfilled') {
          setRecentCampaigns(readList(campaignRes.value));
        }
        if (disciplineCaseRes.status === 'fulfilled') {
          setDisciplineCases(readList(disciplineCaseRes.value));
          setDisciplineSummary(disciplineCaseRes.value?.data?.meta?.summary || createDefaultDisciplineSummary());
        }

        if (canReadWaStatus) {
          const waRes = await whatsappAPI.getStatus();
          const payload = waRes?.data?.data || {};
          setWaStatus({
            configured: Boolean(payload.configured),
            connected: Boolean(payload.connected),
            message: payload.gateway_message || 'Gateway siap digunakan.',
          });
        } else {
          setWaStatus({
            configured: false,
            connected: false,
            message: 'Status gateway WhatsApp tidak ditampilkan pada permission ini.',
          });
        }
      } catch (_error) {
        enqueueSnackbar('Sebagian referensi broadcast gagal dimuat', { variant: 'warning' });
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [canReadWaStatus, enqueueSnackbar]);

  const updateDisciplineFilter = (key, value) => {
    setDisciplineFilters((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const applyDisciplineFilters = () => {
    refreshDisciplineCases();
  };

  const clearDisciplineFilters = () => {
    const next = createDefaultDisciplineFilters();
    setDisciplineFilters(next);
    refreshDisciplineCases({ silent: true, filters: next });
  };

  const updateHistoryFilter = (key, value) => {
    setHistoryFilters((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const applyHistoryFilters = () => {
    refreshRecentCampaigns();
  };

  const clearHistoryFilters = () => {
    const next = createDefaultHistoryFilters();
    setHistoryFilters(next);
    refreshRecentCampaigns({ silent: true, filters: next });
  };

  const exportDisciplineCases = async (format) => {
    try {
      setExportingDiscipline(format);
      const response = await attendanceDisciplineCasesAPI.export({
        format,
        ...(disciplineFilters.status !== 'all' ? { status: disciplineFilters.status } : {}),
        ...(disciplineFilters.parentPhone !== 'all' ? { parent_phone: disciplineFilters.parentPhone } : {}),
        ...(disciplineFilters.search.trim() ? { search: disciplineFilters.search.trim() } : {}),
        ...(disciplineFilters.triggeredFrom ? { triggered_from: disciplineFilters.triggeredFrom } : {}),
        ...(disciplineFilters.triggeredTo ? { triggered_to: disciplineFilters.triggeredTo } : {}),
      });
      downloadBlobResponse(response, `attendance-discipline-cases.${format}`);
      enqueueSnackbar(`Export ${format.toUpperCase()} berhasil dibuat`, { variant: 'success' });
    } catch (error) {
      enqueueSnackbar(extractApiErrorMessage(error, `Export ${format.toUpperCase()} gagal`), { variant: 'error' });
    } finally {
      setExportingDiscipline('');
    }
  };

  const manualRecipients = useMemo(() => parseManualRecipients(draft.manualRecipients), [draft.manualRecipients]);
  const selectedRoleLabel = useMemo(() => roles.find((row) => row.name === draft.targetRole)?.label || 'Belum dipilih', [draft.targetRole, roles]);
  const selectedClassLabel = useMemo(() => {
    const row = classes.find((item) => String(item.id) === String(draft.targetKelasId));
    return row ? `${row.label} | ${row.tahunAjaran}` : 'Belum dipilih';
  }, [classes, draft.targetKelasId]);

  const selectedAudienceLabel = draft.audienceMode === 'role'
    ? selectedRoleLabel
    : draft.audienceMode === 'class'
      ? selectedClassLabel
      : draft.audienceMode === 'user'
        ? draft.targetUserLabel || 'Satu siswa / orang tua'
      : draft.audienceMode === 'manual'
        ? `${manualRecipients.length} nomor manual`
        : 'Semua pengguna aktif';
  const selectedMessageCategoryLabel = draft.messageCategory === 'system'
    ? 'Pesan Sistem'
    : 'Pengumuman';

  const selectedChannels = useMemo(() => {
    const items = [];
    if (draft.channels.inApp) items.push(['in_app', 'Notifikasi aplikasi', BellRing]);
    if (draft.channels.popup) items.push(['popup', draft.popup.variant === 'flyer' ? 'Popup flyer' : 'Popup informasi', draft.popup.variant === 'flyer' ? Sparkles : Newspaper]);
    if (draft.channels.whatsapp) items.push(['whatsapp', 'WhatsApp', MessageSquare]);
    if (draft.channels.email) items.push(['email', 'Email', Mail]);
    return items;
  }, [draft.channels, draft.popup.variant]);

  useEffect(() => {
    if (selectedChannels.length > 0 && !selectedChannels.some(([key]) => key === previewTab)) {
      setPreviewTab(selectedChannels[0][0]);
    }
  }, [previewTab, selectedChannels]);

  const reviewIssues = useMemo(() => {
    const issues = [];
    if (!draft.title.trim()) issues.push('Judul broadcast wajib diisi.');
    if (!draft.message.trim()) issues.push('Isi pesan broadcast wajib diisi.');
    if (selectedChannels.length === 0) issues.push('Pilih minimal satu kanal.');
    if ((draft.channels.inApp || draft.channels.popup) && !draft.internalTargets.web && !draft.internalTargets.mobile) {
      issues.push('Pilih minimal satu target tampilan internal: web atau mobile app.');
    }
    if (draft.audienceMode === 'role' && !draft.targetRole) issues.push('Role target belum dipilih.');
    if (draft.audienceMode === 'class' && !draft.targetKelasId) issues.push('Kelas target belum dipilih.');
    if (draft.audienceMode === 'user' && !draft.targetUserId) issues.push('Siswa target belum dipilih.');
    if (draft.audienceMode === 'manual' && manualRecipients.length === 0) issues.push('Nomor WA manual belum diisi.');
    if (draft.audienceMode === 'manual' && !draft.channels.whatsapp) issues.push('Nomor manual hanya berlaku untuk WhatsApp.');
    if (draft.audienceMode === 'user' && !draft.channels.whatsapp) issues.push('Workflow orang tua sebaiknya memakai kanal WhatsApp.');
    if (flyerUpload.uploading) issues.push('Upload flyer masih berlangsung.');
    if (draft.channels.popup && draft.popup.variant === 'flyer' && !draft.popup.imageUrl.trim()) issues.push('Popup flyer membutuhkan gambar poster / flyer.');
    if (draft.popup.ctaLabel.trim() && !draft.popup.ctaUrl.trim()) issues.push('URL CTA popup belum diisi.');
    if (draft.popup.ctaUrl.trim() && !draft.popup.ctaLabel.trim()) issues.push('Label CTA popup belum diisi.');
    if (draft.popup.ctaUrl.trim() && !isValidHttpUrl(draft.popup.ctaUrl)) issues.push('URL CTA popup harus memakai format http:// atau https:// yang valid.');
    if (draft.messageCategory === 'announcement' && draft.displayEndDate) {
      if (isServerClockSynced && isDateInputValue(serverDate) && draft.displayEndDate < serverDate) {
        issues.push('Akhir masa tayang pengumuman sudah lewat.');
      }
    }
    if (draft.channels.whatsapp && canReadWaStatus && !waStatus.configured) issues.push('Gateway WhatsApp belum siap.');
    return issues;
  }, [canReadWaStatus, draft, flyerUpload.uploading, isServerClockSynced, manualRecipients.length, selectedChannels.length, serverDate, waStatus.configured]);

  const applyTemplate = (key) => {
    setDraft((prev) => {
      const next = structuredClone(prev);
      if (key === 'general') {
        next.tone = 'info';
        next.messageCategory = 'announcement';
        next.channels = { ...next.channels, inApp: true, popup: true, whatsapp: false, email: false };
        next.popup.variant = 'info';
        next.popup.sticky = false;
      }
      if (key === 'important') {
        next.tone = 'warning';
        next.messageCategory = 'announcement';
        next.channels = { ...next.channels, inApp: true, popup: true, whatsapp: false, email: false };
        next.popup.variant = 'info';
        next.popup.sticky = true;
      }
      if (key === 'flyer') {
        next.tone = 'info';
        next.messageCategory = 'announcement';
        next.channels = { ...next.channels, inApp: true, popup: true, whatsapp: false, email: false };
        next.popup.variant = 'flyer';
        next.popup.sticky = false;
      }
      if (key === 'wa') {
        next.tone = 'warning';
        next.messageCategory = 'system';
        next.channels = { ...next.channels, inApp: false, popup: false, whatsapp: true, email: false };
      }
      return next;
    });
    enqueueSnackbar('Template diterapkan', { variant: 'success' });
  };

  const useDisciplineCaseForBroadcast = useCallback((item) => {
    if (!item?.student?.id) {
      enqueueSnackbar('Data kasus tidak valid', { variant: 'error' });
      return;
    }

    const ruleLabel = disciplineRuleLabel(item);
    const unit = disciplineMetricUnit(item);
    const periodLabel = disciplinePeriodLabel(item);

    setDraft((prev) => ({
      ...prev,
      title: `${ruleLabel} - ${item.student.name || 'Siswa'}`,
      message: [
        `Yth. Orang Tua/Wali ${item.student.name || 'siswa'}.`,
        '',
        `Kami informasikan bahwa putra/putri Anda telah mencapai ${item.metric_value || 0} ${unit} pada indikator ${ruleLabel.toLowerCase()} untuk periode ${periodLabel}.`,
        `Batas yang ditetapkan sekolah adalah ${item.metric_limit || 0} ${unit}.`,
        '',
        'Mohon melakukan tindak lanjut dan berkoordinasi dengan pihak sekolah.'
      ].join('\n'),
      tone: 'warning',
      messageCategory: 'system',
      audienceMode: 'user',
      targetUserId: String(item.student.id),
      targetUserLabel: `${item.student.name || 'Siswa'} | ${item.kelas?.name || '-'}`,
      targetRole: '',
      targetKelasId: '',
      manualRecipients: '',
      disciplineCaseId: item.id,
      channels: { inApp: false, whatsapp: true, popup: false, email: false },
      popup: {
        ...prev.popup,
        variant: 'info',
        imageUrl: '',
        ctaLabel: '',
        ctaUrl: '',
        sticky: false,
      },
    }));
    setWorkspaceTab('compose');
    setPreviewTab('whatsapp');
    enqueueSnackbar('Kasus pelanggaran dimasukkan ke composer broadcast orang tua', { variant: 'success' });
  }, [enqueueSnackbar]);

  useEffect(() => {
    const requestedCaseId = location.state?.disciplineCaseId;
    const requestedAction = location.state?.action;
    if (!requestedCaseId || requestedAction !== 'compose') {
      return;
    }

    let active = true;
    attendanceDisciplineCasesAPI.getById(requestedCaseId)
      .then((response) => {
        if (!active) {
          return;
        }

        const item = response?.data?.data;
        if (item) {
          useDisciplineCaseForBroadcast(item);
        } else {
          enqueueSnackbar('Kasus pelanggaran tidak ditemukan', { variant: 'error' });
        }
      })
      .catch(() => {
        if (active) {
          enqueueSnackbar('Detail kasus pelanggaran gagal dimuat', { variant: 'error' });
        }
      })
      .finally(() => {
        if (active) {
          navigate(location.pathname, { replace: true, state: null });
        }
      });

    return () => {
      active = false;
    };
  }, [enqueueSnackbar, location.pathname, location.state, navigate, useDisciplineCaseForBroadcast]);

  const setPopupVariant = (variant) => {
    setDraft((prev) => ({
      ...prev,
      channels: { ...prev.channels, popup: variant !== 'none' },
      popup: { ...prev.popup, variant: variant === 'none' ? prev.popup.variant : variant },
    }));
  };

  const resetDraft = ({ preserveSummary = false } = {}) => {
    setDraft(createDefaultDraft());
    if (!preserveSummary) {
      setLastSummary(null);
    }
    setFlyerUpload({ uploading: false, fileName: '', error: '' });
    setPopupPreviewOpen(false);
    window.localStorage.removeItem(STORAGE_KEY);
  };

  const handleSaveDraft = () => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
      enqueueSnackbar('Draft tersimpan di browser ini', { variant: 'success' });
    } catch (_error) {
      enqueueSnackbar('Draft gagal disimpan', { variant: 'error' });
    }
  };

  const handleFlyerFileChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
      return;
    }

    try {
      setFlyerUpload({
        uploading: true,
        fileName: file.name,
        error: '',
      });

      const response = await broadcastCampaignsAPI.uploadFlyer(file);
      const uploaded = response?.data?.data || {};

      updateField('popup.imageUrl', uploaded.url || '');
      setFlyerUpload({
        uploading: false,
        fileName: uploaded.name || file.name,
        error: '',
      });
      enqueueSnackbar('Gambar flyer berhasil diupload', { variant: 'success' });
    } catch (error) {
      const message = extractApiErrorMessage(error, 'Upload flyer gagal');
      setFlyerUpload({
        uploading: false,
        fileName: file.name,
        error: message,
      });
      enqueueSnackbar(message, { variant: 'error' });
    }
  };

  const handleSend = async () => {
    try {
      if (!canSend) throw new Error('Anda tidak memiliki akses untuk mengirim broadcast.');
      if (reviewIssues.length > 0) throw new Error(reviewIssues[0]);
      setSending(true);

      const payload = {
        title: draft.title.trim(),
        message: draft.message.trim(),
        type: draft.tone,
        message_category: draft.messageCategory,
        display_start_at: draft.displayStartDate || null,
        display_end_at: draft.displayEndDate || null,
        expires_at: draft.displayEndDate || null,
        channels: {
          in_app: draft.channels.inApp,
          popup: draft.channels.popup,
          whatsapp: draft.channels.whatsapp,
          email: draft.channels.email,
        },
        audience: {
          mode: draft.audienceMode,
          role: draft.targetRole || null,
          kelas_id: draft.targetKelasId ? Number(draft.targetKelasId) : null,
          user_id: draft.targetUserId ? Number(draft.targetUserId) : null,
          manual_recipients: manualRecipients,
        },
        data: {
          source: 'broadcast_message_page',
          message_category: draft.messageCategory,
          discipline_case_id: draft.disciplineCaseId || null,
          presentation: {
            in_app: draft.channels.inApp,
            popup: draft.channels.popup,
            targets: {
              web: draft.internalTargets.web,
              mobile: draft.internalTargets.mobile,
            },
          },
        },
        ...(draft.channels.popup ? {
          popup: {
            variant: draft.popup.variant,
            title: draft.popup.title.trim() || draft.title.trim(),
            image_url: draft.popup.variant === 'flyer' ? draft.popup.imageUrl.trim() || null : null,
            dismiss_label: draft.popup.dismissLabel.trim() || 'Tutup',
            cta_label: draft.popup.ctaLabel.trim() || null,
            cta_url: draft.popup.ctaUrl.trim() || null,
            sticky: draft.popup.sticky === true,
          },
        } : {}),
        ...(draft.channels.whatsapp ? {
          whatsapp: { footer: draft.whatsapp.footer.trim() || null },
        } : {}),
        ...(draft.channels.email ? {
          email: { subject: draft.email.subject.trim() || draft.title.trim() },
        } : {}),
      };

      const response = await broadcastCampaignsAPI.create(payload);

      const campaign = response?.data?.data;
      setLastSummary({ at: campaign?.sent_at || getServerIsoString(), results: campaign?.summary?.channels || [] });
      await Promise.all([
        refreshRecentCampaigns({ silent: true }),
        refreshDisciplineCases({ silent: true }),
      ]);
      resetDraft({ preserveSummary: true });
      setWorkspaceTab('history');
      enqueueSnackbar('Broadcast berhasil diproses', { variant: 'success' });
    } catch (error) {
      enqueueSnackbar(extractApiErrorMessage(error, 'Broadcast gagal diproses'), { variant: 'error' });
    } finally {
      setSending(false);
    }
  };

  const preview = useMemo(() => {
    if (previewTab === 'whatsapp') {
      return (
        <div className="rounded-[28px] border border-emerald-200 bg-[#eafbf2] p-5">
          <div className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">WhatsApp</div>
          <div className="mt-4 rounded-3xl bg-white p-4 shadow-sm">
            <div className="font-semibold text-slate-900">{draft.title || 'Judul broadcast'}</div>
            <p className="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{draft.message || 'Isi pesan WhatsApp.'}</p>
            {draft.whatsapp.footer ? <div className="mt-4 border-t border-slate-200 pt-3 text-xs text-slate-500">{draft.whatsapp.footer}</div> : null}
          </div>
        </div>
      );
    }

    if (previewTab === 'popup' && draft.popup.variant === 'info') {
      return (
        <div className="rounded-[28px] border border-sky-200 bg-[#eff8ff] p-5">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700"><Newspaper className="h-4 w-4" />Popup informasi</div>
          <div className="mt-4 rounded-3xl border border-sky-100 bg-white p-5 shadow-sm">
            <div className="text-lg font-semibold text-slate-900">{draft.popup.title || draft.title || 'Informasi'}</div>
            <p className="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{draft.message || 'Isi popup informasi.'}</p>
          </div>
        </div>
      );
    }

    if (previewTab === 'popup') {
      return (
        <div className="rounded-[28px] border border-slate-200 bg-white p-5">
          <div className="flex items-center justify-between gap-3">
            <div>
              <div className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Popup flyer</div>
              <div className="mt-2 text-lg font-semibold text-slate-900">{draft.popup.title || draft.title || 'Judul flyer'}</div>
            </div>
            <button type="button" onClick={() => setPopupPreviewOpen(true)} className="rounded-full border border-slate-200 p-2 text-slate-500 hover:bg-slate-50"><Eye className="h-4 w-4" /></button>
          </div>
          {draft.popup.imageUrl ? (
            <img src={draft.popup.imageUrl} alt="Popup preview" className="mt-5 h-64 w-full rounded-3xl border border-slate-200 bg-white object-contain" />
          ) : (
            <div className="mt-5 flex h-64 items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 text-slate-400">
              <div className="text-center">
                <ImageIcon className="mx-auto h-8 w-8" />
                <div className="mt-2 text-sm">Upload gambar flyer / poster</div>
              </div>
            </div>
          )}
        </div>
      );
    }

    return (
      <div className="rounded-[28px] border border-slate-200 bg-white p-5">
        <div className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">{previewTab === 'email' ? 'Email' : 'Notifikasi aplikasi'}</div>
        <div className="mt-3 text-lg font-semibold text-slate-900">{previewTab === 'email' ? draft.email.subject || draft.title || 'Subjek email' : draft.title || 'Judul pengumuman'}</div>
        <p className="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600">{draft.message || 'Konten preview.'}</p>
      </div>
    );
  }, [draft, previewTab]);

  return (
    <div className="space-y-6">
      <div className="rounded-[30px] bg-[linear-gradient(135deg,#0f172a_0%,#1d4ed8_45%,#0f766e_100%)] p-6 text-white shadow-lg">
        <div className="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-100">Communication Center</div>
        <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h1 className="text-3xl font-bold">Broadcast Message</h1>
            <p className="mt-2 max-w-2xl text-sm text-slate-100/90">Alur dibuat ulang agar operator fokus ke target, kanal, isi pesan, lalu review.</p>
          </div>
          <div className="flex flex-wrap gap-3 text-sm">
            <span className="rounded-full bg-white/10 px-3 py-1.5">Tahun ajaran {academicContext.tahunAjaran}</span>
            <span className="rounded-full bg-white/10 px-3 py-1.5">Periode {academicContext.periode}</span>
            <span className="rounded-full bg-white/10 px-3 py-1.5">{loading ? 'Memuat referensi...' : 'Siap dipakai'}</span>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        {[
          ['compose', 'Buat Broadcast', Megaphone],
          ['discipline', 'Alert Pelanggaran', ShieldAlert],
          ['history', 'Riwayat', History],
        ].map(([key, label, Icon]) => (
          <button key={key} type="button" onClick={() => setWorkspaceTab(key)} className={`inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold ${workspaceTab === key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600'}`}>
            <Icon className="h-4 w-4" />
            {label}
          </button>
        ))}
      </div>

      {workspaceTab === 'compose' ? (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1.08fr_0.92fr]">
          <div className="space-y-6">
            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Megaphone className="h-4 w-4 text-blue-600" />Langkah 0 · Kategori pesan</div>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {messageCategoryOptions.map(([key, label]) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => updateField('messageCategory', key)}
                    className={`rounded-3xl border px-4 py-3.5 text-left ${draft.messageCategory === key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'}`}
                  >
                    <div className="text-sm font-semibold">{label}</div>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><LayoutTemplate className="h-4 w-4 text-blue-600" />Template cepat</div>
              <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {templates.map(([key, label]) => (
                  <button key={key} type="button" onClick={() => applyTemplate(key)} className="rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-left hover:border-slate-300 hover:bg-white">
                    <div className="text-sm font-semibold text-slate-900">{label}</div>
                  </button>
                ))}
              </div>
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Users className="h-4 w-4 text-blue-600" />Langkah 1 · Penerima</div>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {Object.entries(audienceOptions).map(([key, label]) => (
                  <button key={key} type="button" onClick={() => updateField('audienceMode', key)} className={`rounded-3xl border px-4 py-3.5 text-left ${draft.audienceMode === key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'}`}>
                    <div className="text-sm font-semibold">{label}</div>
                  </button>
                ))}
              </div>
              {draft.audienceMode === 'role' ? <select value={draft.targetRole} onChange={(event) => updateField('targetRole', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700"><option value="">Pilih role target</option>{roles.map((row) => <option key={row.name} value={row.name}>{row.label}</option>)}</select> : null}
              {draft.audienceMode === 'class' ? <select value={draft.targetKelasId} onChange={(event) => updateField('targetKelasId', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700"><option value="">Pilih kelas target</option>{classes.map((row) => <option key={row.id} value={row.id}>{row.label} | {row.tahunAjaran}</option>)}</select> : null}
              {draft.audienceMode === 'user' ? <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{draft.targetUserLabel || 'Pilih dari tab Alert Pelanggaran untuk mengisi target siswa/orang tua.'}</div> : null}
              {draft.audienceMode === 'manual' ? <textarea rows={4} value={draft.manualRecipients} onChange={(event) => updateField('manualRecipients', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700" placeholder="628123456789, 628987654321" /> : null}
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Megaphone className="h-4 w-4 text-blue-600" />Langkah 2 · Kanal</div>
              <div className="mt-4 grid gap-3 md:grid-cols-3">
                {[['inApp', 'Notifikasi aplikasi', BellRing, true], ['whatsapp', 'WhatsApp', MessageSquare, true], ['email', 'Email', Mail, canUseEmail]].map(([key, label, Icon, enabled]) => (
                  <button key={key} type="button" disabled={!enabled} onClick={() => enabled && updateField(`channels.${key}`, !draft.channels[key])} className={`rounded-3xl border px-4 py-3.5 text-left ${draft.channels[key] ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'} ${enabled ? '' : 'cursor-not-allowed opacity-60'}`}>
                    <div className="flex items-center gap-3">
                      <div className="rounded-2xl bg-slate-100 p-2 text-slate-700"><Icon className="h-4 w-4" /></div>
                      <div className="text-sm font-semibold text-slate-900">{label}</div>
                    </div>
                  </button>
                ))}
              </div>
              {draft.channels.inApp || draft.channels.popup ? (
                <>
                  <div className="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Target tampilan internal</div>
                  <div className="mt-3 grid gap-3 md:grid-cols-2">
                    {[
                      ['web', 'Frontend Web', 'Tampilkan di dashboard web, inbox web, dan popup web.'],
                      ['mobile', 'Mobile App', 'Tampilkan di aplikasi mobile, inbox mobile, dan popup mobile.'],
                    ].map(([key, label, desc]) => (
                      <button
                        key={key}
                        type="button"
                        onClick={() => updateField(`internalTargets.${key}`, !draft.internalTargets[key])}
                        className={`rounded-3xl border px-4 py-3.5 text-left ${draft.internalTargets[key] ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white'}`}
                      >
                        <div className="text-sm font-semibold text-slate-900">{label}</div>
                      </button>
                    ))}
                  </div>
                </>
              ) : null}
              <div className="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tampilan popup</div>
              <div className="mt-3 grid gap-3 md:grid-cols-3">
                {[['none', 'Tanpa popup', Sparkles], ['info', 'Popup informasi', Newspaper], ['flyer', 'Popup flyer', Sparkles]].map(([variant, label, Icon]) => {
                  const active = (draft.channels.popup ? draft.popup.variant : 'none') === variant;
                  return <button key={variant} type="button" onClick={() => setPopupVariant(variant)} className={`rounded-3xl border px-4 py-3.5 text-left ${active ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white'}`}><div className="flex items-center gap-3"><div className="rounded-2xl bg-slate-100 p-2 text-slate-700"><Icon className="h-4 w-4" /></div><div className="text-sm font-semibold text-slate-900">{label}</div></div></button>;
                })}
              </div>
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Megaphone className="h-4 w-4 text-blue-600" />Langkah 3 · Isi pesan</div>
              <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_220px]">
                <input type="text" value={draft.title} onChange={(event) => updateField('title', event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700" placeholder="Judul broadcast" />
                <select value={draft.tone} onChange={(event) => updateField('tone', event.target.value)} className="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700"><option value="info">Informasi</option><option value="success">Sukses</option><option value="warning">Peringatan</option><option value="error">Penting</option></select>
              </div>
              <textarea rows={8} value={draft.message} onChange={(event) => updateField('message', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700" placeholder="Isi pengumuman, instruksi, atau informasi utama." />
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><History className="h-4 w-4 text-blue-600" />Masa tayang inbox & popup</div>
              <div className="mt-2 text-sm leading-6 text-slate-500">
                Pengumuman aktif selama periode ini. Setelah berakhir, pengumuman masuk arsip API dan tidak menghitung unread aktif.
              </div>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <label className="block">
                  <span className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Mulai tampil</span>
                  <input type="date" value={draft.displayStartDate} onChange={(event) => updateField('displayStartDate', event.target.value)} className="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700" />
                  <span className="mt-2 block text-xs leading-5 text-slate-500">Kosongkan untuk tampil segera saat broadcast diproses.</span>
                </label>
                <label className="block">
                  <span className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Akhir masa tayang</span>
                  <input type="date" value={draft.displayEndDate} onChange={(event) => updateField('displayEndDate', event.target.value)} className="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700" />
                  <span className="mt-2 block text-xs leading-5 text-slate-500">Default 14 hari. Kosongkan hanya untuk pesan sistem yang memang permanen.</span>
                </label>
              </div>
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Sparkles className="h-4 w-4 text-blue-600" />Langkah 4 · Opsi khusus kanal</div>
              <div className="mt-4 space-y-5">
                {draft.channels.popup ? <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                  <div className="text-sm font-semibold text-slate-900">{draft.popup.variant === 'flyer' ? 'Pengaturan popup flyer' : 'Pengaturan popup informasi'}</div>
                  <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <input type="text" value={draft.popup.title} onChange={(event) => updateField('popup.title', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Judul popup" />
                    <input type="text" value={draft.popup.dismissLabel} onChange={(event) => updateField('popup.dismissLabel', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Label tombol tutup" />
                    {draft.popup.variant === 'flyer' ? <>
                      <div className="rounded-2xl border border-slate-200 bg-white p-4 md:col-span-2">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                          <div>
                            <div className="text-sm font-semibold text-slate-900">Gambar flyer / poster</div>
                            <div className="mt-1 text-xs leading-5 text-slate-500">Upload JPG, JPEG, PNG, atau WEBP. Maksimal 10MB. URL publik akan diisi otomatis oleh sistem.</div>
                          </div>
                          <label className={`inline-flex cursor-pointer items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold ${flyerUpload.uploading ? 'bg-slate-200 text-slate-500' : 'bg-slate-900 text-white hover:bg-slate-800'}`}>
                            <ImageIcon className="h-4 w-4" />
                            {flyerUpload.uploading ? 'Mengupload...' : draft.popup.imageUrl ? 'Ganti gambar' : 'Pilih gambar'}
                            <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" onChange={handleFlyerFileChange} className="hidden" disabled={flyerUpload.uploading} />
                          </label>
                        </div>
                        {flyerUpload.fileName ? <div className="mt-3 text-xs font-medium text-slate-600">File terakhir: {flyerUpload.fileName}</div> : null}
                        {flyerUpload.error ? <div className="mt-2 text-xs font-medium text-rose-600">{flyerUpload.error}</div> : null}
                        <input type="text" value={draft.popup.imageUrl} readOnly className="mt-4 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500" placeholder="URL publik flyer akan muncul di sini" />
                        {draft.popup.imageUrl ? <div className="mt-3 flex justify-end"><button type="button" onClick={() => { updateField('popup.imageUrl', ''); setFlyerUpload((prev) => ({ ...prev, error: '' })); }} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Hapus gambar</button></div> : null}
                      </div>
                      <input type="text" value={draft.popup.ctaLabel} onChange={(event) => updateField('popup.ctaLabel', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Label CTA popup" />
                      <input type="url" value={draft.popup.ctaUrl} onChange={(event) => updateField('popup.ctaUrl', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="URL CTA popup" />
                    </> : null}
                  </div>
                  <label className="mt-4 flex items-center gap-3 text-sm text-slate-700"><input type="checkbox" checked={draft.popup.sticky} onChange={(event) => updateField('popup.sticky', event.target.checked)} className="h-4 w-4 rounded border-slate-300" />Popup wajib ditutup manual</label>
                </div> : null}
                {draft.channels.whatsapp ? <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5"><div className="text-sm font-semibold text-slate-900">Pengaturan WhatsApp</div><input type="text" value={draft.whatsapp.footer} onChange={(event) => updateField('whatsapp.footer', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Footer WhatsApp, mis. - Admin SMAN 1 Sumber" /></div> : null}
                {draft.channels.email ? <div className="rounded-3xl border border-slate-200 bg-slate-50 p-5"><div className="text-sm font-semibold text-slate-900">Pengaturan Email</div><input type="text" value={draft.email.subject} onChange={(event) => updateField('email.subject', event.target.value)} className="mt-4 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Subjek email" /><div className="mt-3 text-xs leading-5 text-slate-500">Email dikirim ke alamat email user aktif yang sesuai target role, kelas, atau seluruh user aktif.</div></div> : null}
              </div>
            </div>
          </div>
          <div className="space-y-6">
            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="text-sm font-semibold text-slate-900">Review sebelum kirim</div>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4"><div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Penerima</div><div className="mt-2 text-sm font-semibold text-slate-900">{selectedAudienceLabel}</div></div>
                <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4"><div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kategori</div><div className="mt-2 text-sm font-semibold text-slate-900">{selectedMessageCategoryLabel}</div></div>
                <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4"><div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">WhatsApp</div><div className="mt-2 text-sm font-semibold text-slate-900">{waStatus.connected ? 'Gateway terhubung' : waStatus.configured ? 'Gateway terkonfigurasi' : 'Gateway belum siap'}</div><div className="mt-2 text-xs leading-5 text-slate-500">{waStatus.message}</div></div>
              </div>
              {draft.channels.inApp || draft.channels.popup ? (
                <div className="mt-3 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                  <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tampil di</div>
                  <div className="mt-2 text-sm font-semibold text-slate-900">
                    {[
                      draft.internalTargets.web ? 'Frontend Web' : null,
                      draft.internalTargets.mobile ? 'Mobile App' : null,
                    ].filter(Boolean).join(' | ') || 'Belum dipilih'}
                  </div>
                </div>
              ) : null}
              <div className="mt-5"><div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kanal terpilih</div><div className="mt-3 flex flex-wrap gap-2">{selectedChannels.length === 0 ? <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500">Belum ada kanal</span> : selectedChannels.map(([key, label, Icon]) => <span key={key} className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"><Icon className="h-3.5 w-3.5" />{label}</span>)}</div></div>
              <div className="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4"><div className="flex items-center gap-2 text-sm font-semibold text-slate-900">{reviewIssues.length === 0 ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <AlertCircle className="h-4 w-4 text-amber-600" />}Checklist pengiriman</div><div className="mt-3 space-y-2">{reviewIssues.length === 0 ? <div className="text-sm text-emerald-700">Semua syarat minimum sudah terpenuhi.</div> : reviewIssues.map((issue) => <div key={issue} className="text-sm text-slate-600">{issue}</div>)}</div></div>
              <div className="mt-6 grid gap-3"><button type="button" onClick={handleSend} disabled={sending || !canSend} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:bg-slate-400"><Send className="h-4 w-4" />{sending ? 'Memproses...' : 'Kirim broadcast'}</button><div className="grid gap-3 sm:grid-cols-2"><button type="button" onClick={handleSaveDraft} className="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"><Save className="h-4 w-4" />Simpan draft</button><button type="button" onClick={resetDraft} className="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-500"><RefreshCw className="h-4 w-4" />Reset</button></div></div>
            </div>

            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center justify-between gap-3">
                <div><div className="text-sm font-semibold text-slate-900">Preview</div><div className="mt-1 text-sm text-slate-500">Operator cukup melihat kanal yang sedang aktif.</div></div>
                {draft.channels.popup ? <button type="button" onClick={() => setPopupPreviewOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><Eye className="h-4 w-4" />Popup</button> : null}
              </div>
              <div className="mt-4 flex flex-wrap gap-2">{selectedChannels.length === 0 ? <span className="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-500">Belum ada kanal terpilih</span> : selectedChannels.map(([key, label, Icon]) => <button key={key} type="button" onClick={() => setPreviewTab(key)} className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium ${previewTab === key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}`}><Icon className="h-4 w-4" />{label}</button>)}</div>
              <div className="mt-5">{preview}</div>
            </div>
          </div>
        </div>
      ) : workspaceTab === 'discipline' ? (
        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between gap-3">
            <div>
              <div className="text-sm font-semibold text-slate-900">Histori alert pelanggaran</div>
              <div className="mt-1 text-sm text-slate-500">Kasus yang sudah mencapai ambang disiplin dan siap diteruskan ke orang tua melalui modul broadcast.</div>
            </div>
            <div className="flex flex-wrap gap-2">
              <button type="button" onClick={() => exportDisciplineCases('csv')} disabled={exportingDiscipline !== ''} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 hover:bg-slate-50 disabled:opacity-60">
                {exportingDiscipline === 'csv' ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Export CSV
              </button>
              <button type="button" onClick={() => exportDisciplineCases('pdf')} disabled={exportingDiscipline !== ''} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 hover:bg-slate-50 disabled:opacity-60">
                {exportingDiscipline === 'pdf' ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Export PDF
              </button>
              <button type="button" onClick={() => refreshDisciplineCases()} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 hover:bg-slate-50"><RefreshCw className="h-4 w-4" />Muat ulang</button>
            </div>
          </div>
          <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            {[
              ['Total kasus', disciplineSummary.total, 'bg-slate-100 text-slate-700'],
              ['Siap broadcast', disciplineSummary.ready_for_parent_broadcast, 'bg-amber-50 text-amber-700'],
              ['Sudah dibroadcast', disciplineSummary.parent_broadcast_sent, 'bg-emerald-50 text-emerald-700'],
              ['Nomor tersedia', disciplineSummary.parent_phone_available, 'bg-emerald-50 text-emerald-700'],
              ['Nomor belum ada', disciplineSummary.parent_phone_missing, 'bg-rose-50 text-rose-700'],
            ].map(([label, value, style]) => (
              <div key={label} className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{label}</div>
                <div className={`mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold ${style}`}>{value}</div>
              </div>
            ))}
          </div>
          <div className="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <div className="grid gap-3 xl:grid-cols-[1.2fr_220px_220px_180px_180px_auto_auto]">
              <input
                type="text"
                value={disciplineFilters.search}
                onChange={(event) => updateDisciplineFilter('search', event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    applyDisciplineFilters();
                  }
                }}
                className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                placeholder="Cari nama siswa, username, email, NIS, NISN, atau kelas"
              />
              <select value={disciplineFilters.status} onChange={(event) => updateDisciplineFilter('status', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                <option value="all">Semua status</option>
                <option value="ready_for_parent_broadcast">Siap broadcast</option>
                <option value="parent_broadcast_sent">Sudah dibroadcast</option>
              </select>
              <select value={disciplineFilters.parentPhone} onChange={(event) => updateDisciplineFilter('parentPhone', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                <option value="all">Semua nomor orang tua</option>
                <option value="available">Nomor tersedia</option>
                <option value="missing">Nomor belum tersedia</option>
              </select>
              <input type="date" value={disciplineFilters.triggeredFrom} onChange={(event) => updateDisciplineFilter('triggeredFrom', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
              <input type="date" value={disciplineFilters.triggeredTo} onChange={(event) => updateDisciplineFilter('triggeredTo', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
              <button type="button" onClick={applyDisciplineFilters} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">
                <RefreshCw className="h-4 w-4" />
                Terapkan
              </button>
              <button type="button" onClick={clearDisciplineFilters} className="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-white">
                Reset
              </button>
            </div>
          </div>
          <div className="mt-5 space-y-3">
            {disciplineLoading ? (
              <div className="rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Memuat histori alert pelanggaran...</div>
            ) : disciplineCases.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Belum ada alert pelanggaran yang tercatat.</div>
            ) : disciplineCases.map((item) => (
              <div key={item.id} className="rounded-3xl border border-slate-200 p-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div className="font-semibold text-slate-900">{item.student?.name || 'Siswa'} | {item.kelas?.name || '-'}</div>
                    <div className="mt-1 text-xs text-slate-500">{disciplinePeriodLabel(item)} | {item.tahun_ajaran_ref || '-'} | Update terakhir {formatDateTime(item.last_triggered_at)}</div>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                      <span className={`rounded-full px-3 py-1 ${disciplineMetricTone(item)}`}>{disciplineRuleLabel(item)} {item.metric_value || 0} {disciplineMetricUnit(item)}</span>
                      <span className="rounded-full bg-slate-100 px-3 py-1">Batas {item.metric_limit || 0} {disciplineMetricUnit(item)}</span>
                      <span className={`rounded-full border px-3 py-1 ${disciplineCaseStatusPill[item.status] || disciplineCaseStatusPill.ready_for_parent_broadcast}`}>{disciplineCaseStatusLabel[item.status] || disciplineCaseStatusLabel.ready_for_parent_broadcast}</span>
                      <span className={`rounded-full px-3 py-1 ${item.parent_phone_available ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>{item.parent_phone_available ? 'Nomor orang tua tersedia' : 'Nomor orang tua belum tersedia'}</span>
                    </div>
                    {item.broadcast_campaign ? (
                      <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex flex-wrap items-center gap-2">
                          <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Broadcast terakhir</div>
                          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${item.broadcast_campaign.message_category === 'system' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'}`}>
                            {item.broadcast_campaign.message_category === 'system' ? 'Pesan Sistem' : 'Pengumuman'}
                          </span>
                          <span className={`rounded-full border px-3 py-1 text-xs font-semibold ${statusPill[item.broadcast_campaign.status] || statusPill.processing}`}>
                            {item.broadcast_campaign.status || 'processing'}
                          </span>
                        </div>
                        <div className="mt-2 text-sm font-semibold text-slate-900">{item.broadcast_campaign.title}</div>
                        <div className="mt-1 text-xs text-slate-500">
                          Dibuat {formatDateTime(item.broadcast_campaign.created_at)}
                          {' | '}
                          Diproses {formatDateTime(item.broadcast_campaign.sent_at)}
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                          <span className="rounded-full bg-slate-100 px-3 py-1">Target {item.broadcast_campaign.total_target || 0}</span>
                          <span className="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Sent {item.broadcast_campaign.sent_count || 0}</span>
                          <span className="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Failed {item.broadcast_campaign.failed_count || 0}</span>
                        </div>
                        {Array.isArray(item.broadcast_campaign.summary) && item.broadcast_campaign.summary.length > 0 ? (
                          <div className="mt-3 grid gap-2 md:grid-cols-2">
                            {item.broadcast_campaign.summary.map((row, index) => (
                              <div key={`${item.id}-${row.channel || index}`} className="rounded-2xl border border-slate-200 bg-white p-3">
                                <div className="text-sm font-semibold text-slate-900">{row.channel || 'Kanal'}</div>
                                <div className="mt-1 text-xs text-slate-500">
                                  {row.skipped ? (row.note || 'Dilewati') : `Target ${row.target_count || 0} | Sent ${row.sent || 0} | Failed ${row.failed || 0}`}
                                </div>
                                {row.note && !row.skipped ? <div className="mt-2 text-xs text-amber-700">{row.note}</div> : null}
                              </div>
                            ))}
                          </div>
                        ) : null}
                      </div>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      onClick={() => navigate(`/attendance-discipline-cases/${item.id}`)}
                      className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                      <Eye className="h-4 w-4" />
                      Lihat Detail
                    </button>
                    <button
                      type="button"
                      onClick={() => useDisciplineCaseForBroadcast(item)}
                      disabled={!item.parent_phone_available}
                      className="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:bg-slate-300"
                    >
                      <Link2 className="h-4 w-4" />
                      {item.status === 'parent_broadcast_sent' ? 'Broadcast Ulang ke Orang Tua' : 'Gunakan untuk Broadcast'}
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <div className="space-y-6">
            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="text-sm font-semibold text-slate-900">Ringkasan riwayat</div>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                  ['Total', recentCampaigns.length, 'bg-slate-100 text-slate-700'],
                  ['Sent', recentCampaigns.filter((row) => row.status === 'sent').length, 'bg-emerald-50 text-emerald-700'],
                  ['Partial', recentCampaigns.filter((row) => row.status === 'partial').length, 'bg-amber-50 text-amber-700'],
                  ['Failed', recentCampaigns.filter((row) => row.status === 'failed').length, 'bg-rose-50 text-rose-700'],
                ].map(([label, value, style]) => <div key={label} className="rounded-3xl border border-slate-200 bg-slate-50 p-4"><div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{label}</div><div className={`mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold ${style}`}>{value}</div></div>)}
              </div>
            </div>
            {lastSummary ? <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div className="text-sm font-semibold text-slate-900">Ringkasan pengiriman terakhir</div><div className="mt-2 text-sm text-slate-500">{formatDateTime(lastSummary.at)}</div><div className="mt-4 space-y-3">{lastSummary.results.map((row) => <div key={row.channel} className="rounded-2xl border border-slate-200 p-4 text-sm text-slate-700"><div className="font-semibold text-slate-900">{row.channel}</div><div className="mt-1">{row.skipped ? row.note || 'Dilewati' : `Terkirim ${row.sent || 0}${row.failed ? ` | Gagal ${row.failed}` : ''}`}</div></div>)}</div></div> : null}
          </div>
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between gap-3"><div><div className="text-sm font-semibold text-slate-900">Riwayat broadcast</div><div className="mt-1 text-sm text-slate-500">Daftar broadcast terbaru yang sudah diproses sistem.</div></div><button type="button" onClick={() => refreshRecentCampaigns()} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 hover:bg-slate-50"><RefreshCw className="h-4 w-4" />Muat ulang</button></div>
            <div className="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4">
              <div className="grid gap-3 md:grid-cols-[1fr_220px_180px_180px_auto_auto]">
                <select value={historyFilters.messageCategory} onChange={(event) => updateHistoryFilter('messageCategory', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                  <option value="all">Semua kategori</option>
                  <option value="announcement">Pengumuman</option>
                  <option value="system">Pesan Sistem</option>
                </select>
                <select value={historyFilters.status} onChange={(event) => updateHistoryFilter('status', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                  <option value="all">Semua status</option>
                  <option value="sent">Sent</option>
                  <option value="partial">Partial</option>
                  <option value="failed">Failed</option>
                  <option value="skipped">Skipped</option>
                  <option value="processing">Processing</option>
                </select>
                <input type="date" value={historyFilters.createdFrom} onChange={(event) => updateHistoryFilter('createdFrom', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
                <input type="date" value={historyFilters.createdTo} onChange={(event) => updateHistoryFilter('createdTo', event.target.value)} className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
                <button type="button" onClick={applyHistoryFilters} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">
                  <RefreshCw className="h-4 w-4" />
                  Terapkan
                </button>
                <button type="button" onClick={clearHistoryFilters} className="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-white">
                  Reset
                </button>
              </div>
            </div>
            <div className="mt-5 space-y-3">
              {historyLoading ? <div className="rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Memuat riwayat broadcast...</div> : recentCampaigns.length === 0 ? <div className="rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Belum ada kampanye broadcast yang tercatat.</div> : recentCampaigns.map((campaign) => <div key={campaign.id} className="rounded-3xl border border-slate-200 p-4"><div className="flex items-start justify-between gap-3"><div><div className="font-semibold text-slate-900">{campaign.title || 'Tanpa judul'}</div><div className="mt-1 text-xs text-slate-500">{formatDateTime(campaign.created_at)}</div></div><span className={`rounded-full border px-3 py-1 text-xs font-semibold ${statusPill[campaign.status] || statusPill.processing}`}>{campaign.status || 'processing'}</span></div><div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-600"><span className={`rounded-full px-3 py-1 ${campaign.message_category === 'system' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'}`}>{campaign.message_category === 'system' ? 'Pesan Sistem' : 'Pengumuman'}</span><span className="rounded-full bg-slate-100 px-3 py-1">{campaign.audience?.mode === 'role' ? campaign.audience.role || 'Role' : campaign.audience?.mode === 'class' ? `Kelas #${campaign.audience?.kelas_id || '-'}` : campaign.audience?.mode === 'user' ? `User #${campaign.audience?.user_id || '-'}` : campaign.audience?.mode === 'manual' ? `${Array.isArray(campaign.audience?.manual_recipients) ? campaign.audience.manual_recipients.length : 0} nomor manual` : 'Semua pengguna aktif'}</span><span className="rounded-full bg-slate-100 px-3 py-1">Target {campaign.total_target || 0}</span><span className="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Sent {campaign.sent_count || 0}</span><span className="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Failed {campaign.failed_count || 0}</span></div></div>)}
            </div>
          </div>
        </div>
      )}

      <Dialog open={popupPreviewOpen} onClose={() => setPopupPreviewOpen(false)} maxWidth={draft.popup.variant === 'info' ? 'sm' : 'md'} fullWidth>
        <DialogContent sx={{ p: 0 }}>
          <div className="relative bg-white">
            <button type="button" onClick={() => setPopupPreviewOpen(false)} className="absolute right-4 top-4 z-10 rounded-full p-1 text-slate-500 hover:bg-slate-100"><X className="h-5 w-5" /></button>
            {draft.popup.variant === 'flyer' ? (
              <>
                <div className="px-10 pb-8 pt-6">
                  <div className="text-xl font-semibold text-emerald-700">{draft.popup.title || draft.title || 'Berita'}</div>
                  <div className="mt-6 text-center">
                    <div className="text-2xl font-semibold text-slate-800">{draft.popup.title || draft.title || 'Judul flyer / berita'}</div>
                    {draft.popup.imageUrl ? <img src={draft.popup.imageUrl} alt="Popup preview" className="mt-8 max-h-[420px] w-full rounded-3xl border border-slate-200 bg-white object-contain" /> : <div className="mt-8 flex min-h-[320px] items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 text-slate-400"><div className="text-center"><ImageIcon className="mx-auto h-10 w-10" /><div className="mt-3 text-sm">Upload gambar flyer / poster untuk preview penuh.</div></div></div>}
                    <p className="mx-auto mt-6 max-w-2xl whitespace-pre-line text-sm leading-7 text-slate-600">{draft.message || 'Isi pengumuman popup.'}</p>
                  </div>
                </div>
                <div className="border-t border-slate-100 px-10 py-5">
                  <div className="flex items-center justify-between"><button type="button" onClick={() => setPopupPreviewOpen(false)} className="text-sm font-semibold text-emerald-600">{draft.popup.dismissLabel || 'Tutup'}</button>{draft.popup.ctaLabel ? <button type="button" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{draft.popup.ctaLabel}</button> : null}</div>
                </div>
              </>
            ) : (
              <div className="p-8">
                <div className="rounded-[28px] border border-sky-200 bg-[#eef7ff] p-6">
                  <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700"><Newspaper className="h-4 w-4" />Popup informasi</div>
                  <div className="mt-4 text-2xl font-semibold text-slate-900">{draft.popup.title || draft.title || 'Informasi'}</div>
                  <p className="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600">{draft.message || 'Isi popup informasi.'}</p>
                  <div className="mt-6 flex items-center justify-between border-t border-sky-100 pt-4"><button type="button" onClick={() => setPopupPreviewOpen(false)} className="text-sm font-semibold text-emerald-600">{draft.popup.dismissLabel || 'Tutup'}</button><span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500">{draft.popup.sticky ? 'Wajib ditutup manual' : 'Bisa ditutup biasa'}</span></div>
                </div>
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default BroadcastMessage;

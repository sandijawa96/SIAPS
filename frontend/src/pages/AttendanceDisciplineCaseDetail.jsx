import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useSnackbar } from 'notistack';
import {
  ArrowLeft,
  BellRing,
  Link2,
  MessageSquare,
  RefreshCw,
  ShieldAlert,
  Users,
} from 'lucide-react';
import { attendanceDisciplineCasesAPI } from '../services/api';
import { formatServerDateTime } from '../services/serverClock';

const statusPill = {
  ready_for_parent_broadcast: 'bg-amber-50 text-amber-700 border-amber-200',
  parent_broadcast_sent: 'bg-emerald-50 text-emerald-700 border-emerald-200',
};

const statusLabel = {
  ready_for_parent_broadcast: 'Siap untuk broadcast orang tua',
  parent_broadcast_sent: 'Broadcast orang tua sudah dikirim',
};

const campaignStatusPill = {
  sent: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  partial: 'bg-amber-50 text-amber-700 border-amber-200',
  failed: 'bg-rose-50 text-rose-700 border-rose-200',
  skipped: 'bg-slate-100 text-slate-600 border-slate-200',
  processing: 'bg-blue-50 text-blue-700 border-blue-200',
};

const audienceLabel = {
  wali_kelas: 'Wali Kelas',
  kesiswaan: 'Kesiswaan',
};

const ruleLabel = (item) => item?.rule_label || item?.payload?.rule_label || 'Pelanggaran';
const metricUnit = (item) => item?.metric_unit || item?.payload?.metric_unit || 'menit';
const metricToneClass = (item) => (
  item?.rule_key === 'semester_alpha_limit'
    ? 'bg-amber-50 text-amber-700'
    : item?.rule_key === 'monthly_late_limit'
      ? 'bg-orange-50 text-orange-700'
      : 'bg-rose-50 text-rose-700'
);
const periodLabel = (item) => item?.period_label || item?.payload?.period_label || item?.payload?.semester_label || item?.semester || '-';

const formatDateTime = (value) => formatServerDateTime(value, 'id-ID') || '-';

const AttendanceDisciplineCaseDetail = () => {
  const navigate = useNavigate();
  const { enqueueSnackbar } = useSnackbar();
  const { id } = useParams();
  const [item, setItem] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadDetail = async () => {
    if (!id) {
      setError('ID kasus tidak valid');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');
    try {
      const response = await attendanceDisciplineCasesAPI.getById(id);
      setItem(response?.data?.data || null);
    } catch (loadError) {
      setItem(null);
      setError(loadError?.response?.data?.message || 'Detail kasus pelanggaran gagal dimuat');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDetail();
  }, [id]);

  const parentContactCount = useMemo(
    () => (Array.isArray(item?.parent_contacts) ? item.parent_contacts.filter((row) => row?.available).length : 0),
    [item]
  );

  return (
    <div className="space-y-6">
      <div className="rounded-[30px] bg-[linear-gradient(135deg,#0f172a_0%,#1d4ed8_45%,#0f766e_100%)] p-6 text-white shadow-lg">
        <div className="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-100">Discipline Case Audit</div>
        <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h1 className="text-3xl font-bold">Detail Alert Pelanggaran</h1>
            <p className="mt-2 max-w-2xl text-sm text-slate-100/90">Audit satu kasus pelanggaran, jejak alert internal, dan status broadcast orang tua.</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <button type="button" onClick={() => navigate('/broadcast-message')} className="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">
              <ArrowLeft className="h-4 w-4" />
              Kembali ke Broadcast
            </button>
            <button
              type="button"
              onClick={() => navigate('/broadcast-message', { state: { disciplineCaseId: item?.id, action: 'compose' } })}
              disabled={!item?.id}
              className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-60"
            >
              <Link2 className="h-4 w-4" />
              Buka Composer Broadcast
            </button>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="rounded-3xl border border-dashed border-slate-200 bg-white px-4 py-8 text-sm text-slate-500">Memuat detail kasus pelanggaran...</div>
      ) : error ? (
        <div className="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-5 text-sm text-rose-700">{error}</div>
      ) : !item ? (
        <div className="rounded-3xl border border-dashed border-slate-200 bg-white px-4 py-8 text-sm text-slate-500">Data kasus tidak ditemukan.</div>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            {[
              ['Status', statusLabel[item.status] || statusLabel.ready_for_parent_broadcast, statusPill[item.status] || statusPill.ready_for_parent_broadcast],
              [ruleLabel(item), `${item.metric_value || 0} ${metricUnit(item)}`, metricToneClass(item)],
              ['Batas', `${item.metric_limit || 0} ${metricUnit(item)}`, 'bg-slate-100 text-slate-700'],
              ['Kontak tersedia', `${parentContactCount}`, 'bg-emerald-50 text-emerald-700'],
              ['Alert internal', `${Array.isArray(item.alerts) ? item.alerts.length : 0}`, 'bg-blue-50 text-blue-700'],
            ].map(([label, value, style]) => (
              <div key={label} className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{label}</div>
                <div className={`mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold ${style}`}>{value}</div>
              </div>
            ))}
          </div>

          <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div className="space-y-6">
              <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <Users className="h-4 w-4 text-blue-600" />
                  Identitas siswa
                </div>
                <div className="mt-4 space-y-3 text-sm text-slate-700">
                  <div><span className="font-semibold text-slate-900">Nama:</span> {item.student?.name || '-'}</div>
                  <div><span className="font-semibold text-slate-900">Kelas:</span> {item.kelas?.name || '-'}</div>
                  <div><span className="font-semibold text-slate-900">NIS:</span> {item.student?.nis || '-'}</div>
                  <div><span className="font-semibold text-slate-900">NISN:</span> {item.student?.nisn || '-'}</div>
                  <div><span className="font-semibold text-slate-900">Username:</span> {item.student?.username || '-'}</div>
                  <div><span className="font-semibold text-slate-900">Email:</span> {item.student?.email || '-'}</div>
                  <div><span className="font-semibold text-slate-900">Indikator:</span> {ruleLabel(item)}</div>
                  <div><span className="font-semibold text-slate-900">Periode:</span> {periodLabel(item)}</div>
                  <div><span className="font-semibold text-slate-900">Tahun ajaran:</span> {item.tahun_ajaran_ref || '-'}</div>
                  <div><span className="font-semibold text-slate-900">Pertama terpicu:</span> {formatDateTime(item.first_triggered_at)}</div>
                  <div><span className="font-semibold text-slate-900">Terakhir diperbarui:</span> {formatDateTime(item.last_triggered_at)}</div>
                </div>
              </div>

              <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <MessageSquare className="h-4 w-4 text-blue-600" />
                  Kontak orang tua / wali
                </div>
                <div className="mt-4 space-y-3">
                  {(item.parent_contacts || []).map((contact) => (
                    <div key={contact.label} className="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                      <div className="font-semibold text-slate-900">{contact.label}</div>
                      <div className={contact.available ? 'text-slate-700' : 'text-slate-400'}>
                        {contact.value || 'Belum tersedia'}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-6">
              <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <BellRing className="h-4 w-4 text-blue-600" />
                  Jejak alert internal
                </div>
                <div className="mt-4 space-y-3">
                  {(item.alerts || []).length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Belum ada jejak alert internal yang tercatat.</div>
                  ) : (
                    item.alerts.map((alert) => (
                      <div key={alert.id} className="rounded-2xl border border-slate-200 p-4">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            {audienceLabel[alert.audience] || alert.audience || 'Penerima'}
                          </span>
                          <span className="text-sm font-semibold text-slate-900">{alert.recipient?.name || '-'}</span>
                        </div>
                        <div className="mt-2 text-xs text-slate-500">{formatDateTime(alert.triggered_at)}</div>
                        <div className="mt-3 grid gap-3 md:grid-cols-2">
                          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">In-App</div>
                            <div className="mt-2 text-sm font-semibold text-slate-900">{alert.notification?.title || 'Tidak ada notifikasi internal'}</div>
                            <div className="mt-1 text-xs text-slate-500">
                              {alert.notification ? (alert.notification.is_read ? 'Sudah dibaca' : 'Belum dibaca') : 'Tidak tercatat'}
                            </div>
                          </div>
                          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">WhatsApp Operasional</div>
                            <div className="mt-2 text-sm font-semibold text-slate-900">{alert.whatsapp?.phone_number || 'Tidak ada pengiriman WA'}</div>
                            <div className="mt-1 text-xs text-slate-500">
                              {alert.whatsapp ? `${alert.whatsapp.status || '-'} | ${formatDateTime(alert.whatsapp.sent_at)}` : 'Tidak tercatat'}
                            </div>
                            {alert.whatsapp?.error_message ? <div className="mt-2 text-xs text-rose-600">{alert.whatsapp.error_message}</div> : null}
                          </div>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>

              <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <ShieldAlert className="h-4 w-4 text-blue-600" />
                  Broadcast orang tua
                </div>
                {!item.broadcast_campaign ? (
                  <div className="mt-4 rounded-2xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Belum ada broadcast orang tua yang terhubung ke kasus ini.</div>
                ) : (
                  <div className="mt-4 rounded-2xl border border-slate-200 p-4">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={`rounded-full border px-3 py-1 text-xs font-semibold ${campaignStatusPill[item.broadcast_campaign.status] || campaignStatusPill.processing}`}>
                        {item.broadcast_campaign.status || 'processing'}
                      </span>
                      <span className={`rounded-full px-3 py-1 text-xs font-semibold ${item.broadcast_campaign.message_category === 'system' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'}`}>
                        {item.broadcast_campaign.message_category === 'system' ? 'Pesan Sistem' : 'Pengumuman'}
                      </span>
                    </div>
                    <div className="mt-3 text-lg font-semibold text-slate-900">{item.broadcast_campaign.title || 'Tanpa judul'}</div>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                      <span className="rounded-full bg-slate-100 px-3 py-1">Target {item.broadcast_campaign.total_target || 0}</span>
                      <span className="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Sent {item.broadcast_campaign.sent_count || 0}</span>
                      <span className="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Failed {item.broadcast_campaign.failed_count || 0}</span>
                    </div>
                    <div className="mt-3 text-xs text-slate-500">
                      Dibuat {formatDateTime(item.broadcast_campaign.created_at)} | Diproses {formatDateTime(item.broadcast_campaign.sent_at)}
                    </div>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                      {(item.broadcast_campaign.summary || []).map((row, index) => (
                        <div key={`${row.channel || index}`} className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                          <div className="text-sm font-semibold text-slate-900">{row.channel || 'Kanal'}</div>
                          <div className="mt-1 text-xs text-slate-500">
                            {row.skipped ? (row.note || 'Dilewati') : `Target ${row.target_count || 0} | Sent ${row.sent || 0} | Failed ${row.failed || 0}`}
                          </div>
                          {row.note && !row.skipped ? <div className="mt-2 text-xs text-amber-700">{row.note}</div> : null}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="flex justify-end">
            <button type="button" onClick={loadDetail} className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
              <RefreshCw className="h-4 w-4" />
              Muat ulang detail
            </button>
          </div>
        </>
      )}
    </div>
  );
};

export default AttendanceDisciplineCaseDetail;

import React from 'react';
import PropTypes from 'prop-types';
import { Activity, BellRing, MessageSquare, RefreshCw, ServerCrash, TimerReset } from 'lucide-react';
import { formatServerDateTime } from '../../services/serverClock';

const badgeClassByHealth = {
  healthy: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  degraded: 'border-rose-200 bg-rose-50 text-rose-700',
  warning: 'border-amber-200 bg-amber-50 text-amber-700',
};

const cardConfigs = [
  {
    key: 'notification_pending',
    label: 'Queue Push Pending',
    icon: BellRing,
    colorClass: 'bg-sky-100 text-sky-700',
    valueResolver: (data) => data?.queue?.notifications?.pending || 0,
    helperResolver: (data) => `Queue ${data?.queue?.notifications?.queue || 'izin-notifications'}`,
  },
  {
    key: 'whatsapp_pending',
    label: 'Queue WA Pending',
    icon: MessageSquare,
    colorClass: 'bg-violet-100 text-violet-700',
    valueResolver: (data) => data?.queue?.whatsapp?.pending || 0,
    helperResolver: (data) => `Delayed ${data?.queue?.whatsapp?.delayed || 0}`,
  },
  {
    key: 'failed_jobs',
    label: 'Job Gagal Window',
    icon: ServerCrash,
    colorClass: 'bg-rose-100 text-rose-700',
    valueResolver: (data) => data?.failures?.summary?.failed_jobs_window_count || 0,
    helperResolver: () => 'Job izin di queue/worker',
  },
  {
    key: 'wa_failed',
    label: 'WA Gagal Window',
    icon: Activity,
    colorClass: 'bg-amber-100 text-amber-700',
    valueResolver: (data) => data?.delivery?.whatsapp?.failed_window || 0,
    helperResolver: (data) => `Sent ${data?.delivery?.whatsapp?.sent_window || 0}`,
  },
];

const formatDateTime = (value) => {
  return formatServerDateTime(value, 'id-ID', { dateStyle: 'medium', timeStyle: 'short' }) || '-';
};

const IzinObservabilityPanel = ({ data, loading, error, onRefresh }) => {
  const health = data?.health?.status || 'warning';
  const healthBadgeClass = badgeClassByHealth[health] || badgeClassByHealth.warning;
  const issues = Array.isArray(data?.health?.issues) ? data.health.issues : [];
  const recentJobFailures = Array.isArray(data?.failures?.recent_jobs) ? data.failures.recent_jobs : [];
  const recentWaFailures = Array.isArray(data?.delivery?.whatsapp?.recent_failures) ? data.delivery.whatsapp.recent_failures : [];

  return (
    <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h2 className="text-lg font-semibold text-slate-900">Observability Izin</h2>
            <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${healthBadgeClass}`}>
              {health === 'healthy' ? 'Sehat' : health === 'degraded' ? 'Perlu Tindakan' : 'Perlu Perhatian'}
            </span>
            <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
              <TimerReset className="mr-1.5 h-3.5 w-3.5" />
              Window {data?.window_hours || 24} jam
            </span>
          </div>
          <p className="mt-2 text-sm text-slate-600">
            Memantau durasi request izin, kesehatan queue notifikasi, dan hasil pengiriman push/WhatsApp untuk workflow izin siswa.
          </p>
        </div>
        <button
          type="button"
          onClick={onRefresh}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          Muat Ulang
        </button>
      </div>

      {error ? (
        <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {issues.length > 0 ? (
        <div className="mt-4 flex flex-wrap gap-2">
          {issues.map((issue) => (
            <span
              key={issue}
              className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700"
            >
              {issue}
            </span>
          ))}
        </div>
      ) : null}

      <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {cardConfigs.map((card) => {
          const Icon = card.icon;
          return (
            <div key={card.key} className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
              <div className="flex items-start gap-3">
                <div className={`rounded-2xl p-3 ${card.colorClass}`}>
                  <Icon className="h-5 w-5" />
                </div>
                <div>
                  <div className="text-sm font-medium text-slate-600">{card.label}</div>
                  <div className="mt-1 text-2xl font-bold text-slate-900">
                    {loading ? '...' : card.valueResolver(data)}
                  </div>
                  <div className="mt-1 text-xs text-slate-500">{card.helperResolver(data)}</div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div className="rounded-2xl border border-slate-200 p-4">
          <div className="text-sm font-semibold text-slate-900">Dispatch Aplikasi</div>
          <div className="mt-3 space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Dibuat pada window</span>
              <span className="font-semibold text-slate-900">{data?.delivery?.in_app?.created_window || 0}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Approval request</span>
              <span className="font-semibold text-slate-900">{data?.delivery?.in_app?.approval_requests_window || 0}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Result decision</span>
              <span className="font-semibold text-slate-900">{data?.delivery?.in_app?.decision_results_window || 0}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Unread saat ini</span>
              <span className="font-semibold text-slate-900">{data?.delivery?.in_app?.unread_current || 0}</span>
            </div>
            <div className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500">
              Terakhir dibuat: {formatDateTime(data?.delivery?.in_app?.latest_created_at)}
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 p-4">
          <div className="text-sm font-semibold text-slate-900">Failure Job Terbaru</div>
          <div className="mt-3 space-y-3">
            {recentJobFailures.length === 0 ? (
              <div className="rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-500">Belum ada job izin gagal pada window ini.</div>
            ) : recentJobFailures.map((item) => (
              <div key={item.id} className="rounded-xl border border-rose-100 bg-rose-50/70 px-3 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div className="text-sm font-semibold text-slate-900">{item.queue}</div>
                  <div className="text-xs text-slate-500">{formatDateTime(item.failed_at)}</div>
                </div>
                <div className="mt-2 text-xs text-slate-600 break-words">{item.message}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 p-4">
          <div className="text-sm font-semibold text-slate-900">WA Gagal Terbaru</div>
          <div className="mt-3 space-y-3">
            {recentWaFailures.length === 0 ? (
              <div className="rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-500">Belum ada pengiriman WA izin yang gagal pada window ini.</div>
            ) : recentWaFailures.map((item) => (
              <div key={item.id} className="rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div className="text-sm font-semibold text-slate-900">{item.phone_number}</div>
                  <div className="text-xs text-slate-500">{formatDateTime(item.created_at)}</div>
                </div>
                <div className="mt-2 text-xs text-slate-600 break-words">{item.error_message || 'Gateway tidak memberikan detail error'}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

IzinObservabilityPanel.propTypes = {
  data: PropTypes.shape({
    window_hours: PropTypes.number,
    health: PropTypes.shape({
      status: PropTypes.string,
      issues: PropTypes.arrayOf(PropTypes.string),
    }),
    queue: PropTypes.object,
    delivery: PropTypes.object,
    failures: PropTypes.object,
  }),
  loading: PropTypes.bool,
  error: PropTypes.string,
  onRefresh: PropTypes.func,
};

IzinObservabilityPanel.defaultProps = {
  data: null,
  loading: false,
  error: null,
  onRefresh: () => {},
};

export default IzinObservabilityPanel;

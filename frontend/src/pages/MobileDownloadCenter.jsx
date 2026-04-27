import React, { useEffect, useMemo, useState } from 'react';
import { useSnackbar } from 'notistack';
import {
  AlertCircle,
  Download,
  ExternalLink,
  Lock,
  ShieldCheck,
  Smartphone,
} from 'lucide-react';
import { mobileReleasesAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import { formatServerDateTime } from '../services/serverClock';

const formatDateTime = (value) => {
  return formatServerDateTime(value, 'id-ID', { dateStyle: 'medium', timeStyle: 'short' }) || '-';
};

const formatFileSize = (value) => {
  const size = Number(value);
  if (!Number.isFinite(size) || size <= 0) {
    return null;
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  let current = size;
  let index = 0;

  while (current >= 1024 && index < units.length - 1) {
    current /= 1024;
    index += 1;
  }

  return `${current.toFixed(current >= 100 || index === 0 ? 0 : 1)} ${units[index]}`;
};

const normalizeRole = (roleName) => String(roleName || '')
  .trim()
  .toLowerCase()
  .replace(/[_\s]+/g, ' ');

const resolveAudienceFromRoles = (roles) => (
  Array.isArray(roles) && roles.some((roleName) => normalizeRole(roleName) === 'siswa')
    ? 'siswa'
    : 'staff'
);

const openDirectDownload = (downloadUrl) => {
  window.location.assign(downloadUrl);
};

const platformLabelFallback = {
  android: 'Android APK',
  ios: 'iPhone / iOS',
};

const audienceBadgeStyles = {
  all: 'bg-emerald-100 text-emerald-800',
  siswa: 'bg-sky-100 text-sky-800',
  staff: 'bg-amber-100 text-amber-800',
};

const compareReleaseFreshness = (left, right) => {
  const leftBuild = Number(left?.build_number) || 0;
  const rightBuild = Number(right?.build_number) || 0;
  if (leftBuild !== rightBuild) {
    return rightBuild - leftBuild;
  }

  const leftPublishedAt = left?.published_at ? Date.parse(left.published_at) || 0 : 0;
  const rightPublishedAt = right?.published_at ? Date.parse(right.published_at) || 0 : 0;
  if (leftPublishedAt !== rightPublishedAt) {
    return rightPublishedAt - leftPublishedAt;
  }

  return (Number(right?.id) || 0) - (Number(left?.id) || 0);
};

const MobileDownloadCenter = () => {
  const { enqueueSnackbar } = useSnackbar();
  const { roles = [], user } = useAuth();
  const [releases, setReleases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [downloadingId, setDownloadingId] = useState(null);

  const currentAudience = useMemo(() => resolveAudienceFromRoles(roles), [roles]);
  const currentAudienceLabel = currentAudience === 'siswa' ? 'Siswa' : 'Pegawai Sekolah';

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError('');

      try {
        const response = await mobileReleasesAPI.getCatalogList();
        setReleases(Array.isArray(response?.data?.data) ? response.data.data : []);
      } catch (requestError) {
        setError(requestError?.response?.data?.message || 'Pusat aplikasi belum tersedia.');
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const groupedApps = useMemo(() => {
    const latestByPlatform = new Map();

    releases.forEach((release) => {
      const releaseKey = `${release.app_key || `app-${release.id}`}|${release.platform || 'unknown'}`;
      const current = latestByPlatform.get(releaseKey);

      if (!current || compareReleaseFreshness(current, release) > 0) {
        latestByPlatform.set(releaseKey, release);
      }
    });

    const grouped = new Map();

    Array.from(latestByPlatform.values()).forEach((release) => {
      const appKey = release.app_key || `app-${release.id}`;
      if (!grouped.has(appKey)) {
        grouped.set(appKey, {
          app_key: appKey,
          app_name: release.app_name || release.app_label || appKey,
          app_description: release.app_description || '',
          target_audience: release.target_audience || 'all',
          target_audience_label: release.target_audience_label || 'Semua Akun',
          items: [],
        });
      }

      grouped.get(appKey).items.push(release);
    });

    return Array.from(grouped.values())
      .map((app) => ({
        ...app,
        items: [...app.items].sort((left, right) => {
          if (left.platform === right.platform) {
            return (right.build_number || 0) - (left.build_number || 0);
          }

          if (left.platform === 'android') {
            return -1;
          }

          if (right.platform === 'android') {
            return 1;
          }

          return String(left.platform).localeCompare(String(right.platform));
        }),
      }))
      .sort((left, right) => String(left.app_name).localeCompare(String(right.app_name)));
  }, [releases]);

  const handleDownload = async (release) => {
    if (!release) {
      return;
    }

    if (release.download_kind === 'external_url' && release.download_url) {
      window.open(release.download_url, '_blank', 'noopener,noreferrer');
      return;
    }

    if (release.download_kind !== 'managed_asset') {
      enqueueSnackbar('Unduhan belum tersedia untuk release ini.', { variant: 'warning' });
      return;
    }

    try {
      setDownloadingId(release.id);
      const response = await mobileReleasesAPI.getDownloadLink(release.id);
      const downloadUrl = response?.data?.data?.download_url;

      if (!downloadUrl) {
        throw new Error('Link unduhan sementara tidak tersedia.');
      }

      openDirectDownload(downloadUrl);
      enqueueSnackbar(`Unduhan ${release.app_name || release.app_label} disiapkan. Jika belum muncul, cek izin download browser.`, { variant: 'success' });
    } catch (downloadError) {
      enqueueSnackbar(
        downloadError?.response?.data?.message || downloadError?.message || 'Gagal mengunduh aplikasi internal.',
        { variant: 'error' }
      );
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(8,145,178,0.12),_transparent_34%),linear-gradient(180deg,_#f8fafc,_#eef2ff_45%,_#f8fafc)]">
      <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white/90 shadow-xl backdrop-blur">
          <div className="grid gap-8 px-6 py-8 lg:grid-cols-[1.25fr,0.95fr] lg:px-10">
            <div className="space-y-4">
              <div className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200">
                <Lock size={14} />
                Internal App Center
              </div>
              <div className="space-y-3">
                <h1 className="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">
                  Pusat Aplikasi Internal
                </h1>
                <p className="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                  Halaman ini hanya tersedia setelah login. Release SIAPS dan aplikasi internal lain seperti APK ujian
                  dikonsolidasikan di satu tempat, lalu difilter sesuai akun yang sedang aktif. Yang ditampilkan hanya
                  versi terbaru aktif per aplikasi dan platform.
                </p>
              </div>
              <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Akun Aktif</p>
                  <p className="mt-2 text-sm font-semibold text-slate-900">{user?.nama_lengkap || user?.name || 'User'}</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Audience</p>
                  <p className="mt-2 text-sm font-semibold text-slate-900">{currentAudienceLabel}</p>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Aplikasi Tersedia</p>
                  <p className="mt-2 text-sm font-semibold text-slate-900">{groupedApps.length}</p>
                </div>
              </div>
            </div>

            <div className="rounded-[1.75rem] bg-slate-950 p-6 text-white">
              <div className="flex items-center gap-3">
                <ShieldCheck className="text-cyan-300" size={26} />
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-cyan-200">Kebijakan Distribusi</p>
                  <h2 className="text-xl font-semibold">Private By Default</h2>
                </div>
              </div>
              <div className="mt-6 space-y-4 text-sm text-slate-200">
                <p>
                  Semua file terkelola didownload melalui endpoint yang memerlukan autentikasi. Link statis publik tidak
                  dipakai untuk katalog ini.
                </p>
                <p>
                  Release yang tampil di sini sudah disaring berdasarkan audience akun Anda. Aplikasi khusus siswa tidak
                  akan ditampilkan ke pegawai, dan sebaliknya.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-8">
          {loading ? (
            <div className="rounded-[2rem] border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
              Memuat katalog aplikasi internal...
            </div>
          ) : error ? (
            <div className="rounded-[2rem] border border-amber-200 bg-amber-50 p-8 shadow-sm">
              <div className="flex items-start gap-3">
                <AlertCircle className="mt-0.5 text-amber-600" size={20} />
                <div>
                  <h2 className="text-lg font-semibold text-amber-900">Pusat Aplikasi Belum Siap</h2>
                  <p className="mt-2 text-sm text-amber-800">{error}</p>
                </div>
              </div>
            </div>
          ) : groupedApps.length === 0 ? (
            <div className="rounded-[2rem] border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
              Belum ada aplikasi internal yang tersedia untuk akun ini.
            </div>
          ) : (
            <div className="space-y-6">
              {groupedApps.map((app) => (
                <section key={app.app_key} className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-lg">
                  <div className="border-b border-slate-100 bg-[linear-gradient(135deg,_#0f172a,_#164e63)] px-6 py-6 text-white">
                    <div className="flex flex-wrap items-center gap-3">
                      <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">
                        <Smartphone size={14} />
                        {app.app_name}
                      </span>
                      <span className={`rounded-full px-3 py-1 text-xs font-semibold ${audienceBadgeStyles[app.target_audience] || audienceBadgeStyles.all}`}>
                        {app.target_audience_label}
                      </span>
                    </div>
                    <div className="mt-4 space-y-2">
                      <h2 className="text-2xl font-semibold">{app.app_name}</h2>
                      <p className="max-w-3xl text-sm text-slate-200">
                        {app.app_description || 'Release aktif aplikasi ini tersedia di bawah sesuai platform yang didukung.'}
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-5 px-6 py-6 lg:grid-cols-2">
                    {app.items.map((release) => {
                      const fileSize = formatFileSize(release.file_size_bytes);
                      const isManagedAsset = release.download_kind === 'managed_asset';
                      const isDownloading = downloadingId === release.id;

                      return (
                        <article key={release.id} className="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                          <div className="flex flex-wrap items-center gap-3">
                            <span className="rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                              {platformLabelFallback[release.platform] || release.platform_label}
                            </span>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${release.update_mode === 'required' ? 'bg-amber-200 text-amber-900' : 'bg-emerald-200 text-emerald-900'}`}>
                              {release.update_mode === 'required' ? 'Required Update' : 'Optional / Catalog'}
                            </span>
                          </div>

                          <div className="mt-4 space-y-1">
                            <h3 className="text-lg font-semibold text-slate-900">{release.platform_label}</h3>
                            <p className="text-sm text-slate-600">
                              Versi {release.public_version} ({release.build_number})
                            </p>
                          </div>

                          <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Dipublikasikan</p>
                              <p className="mt-2 text-sm font-medium text-slate-800">{formatDateTime(release.published_at)}</p>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ukuran File</p>
                              <p className="mt-2 text-sm font-medium text-slate-800">{fileSize || '-'}</p>
                            </div>
                          </div>

                          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Release Notes</p>
                            <p className="mt-2 whitespace-pre-line text-sm text-slate-600">
                              {release.release_notes || 'Belum ada catatan rilis untuk item ini.'}
                            </p>
                          </div>

                          {release.distribution_notes ? (
                            <div className="mt-4 rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Instruksi Distribusi</p>
                              <p className="mt-2 whitespace-pre-line text-sm text-cyan-900">{release.distribution_notes}</p>
                            </div>
                          ) : null}

                          <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Checksum SHA-256</p>
                              <p className="mt-2 break-all text-sm text-slate-700">{release.checksum_sha256 || '-'}</p>
                            </div>
                            <div className="rounded-2xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sumber Unduh</p>
                              <p className="mt-2 text-sm text-slate-700">
                                {isManagedAsset ? 'File internal terkelola' : release.download_kind === 'external_url' ? 'Tautan eksternal' : '-'}
                              </p>
                            </div>
                          </div>

                          {release.download_url ? (
                            <button
                              type="button"
                              onClick={() => handleDownload(release)}
                              disabled={isDownloading}
                              className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                              {isManagedAsset ? <Download size={16} /> : <ExternalLink size={16} />}
                              {isDownloading
                                ? 'Menyiapkan Unduhan...'
                                : isManagedAsset
                                  ? 'Unduh Aplikasi'
                                  : 'Buka Jalur Instalasi'}
                            </button>
                          ) : (
                            <div className="mt-5 rounded-2xl border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500">
                              Admin belum menetapkan file atau tautan unduh untuk release ini.
                            </div>
                          )}
                        </article>
                      );
                    })}
                  </div>
                </section>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  );
};

export default MobileDownloadCenter;

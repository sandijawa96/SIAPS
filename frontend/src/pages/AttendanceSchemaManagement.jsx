import React, { useMemo, useState } from 'react';
import {
  ArrowLeft,
  ChevronRight,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  Sparkles,
  UserPlus,
  Users,
  Zap,
} from 'lucide-react';
import AttendanceSchemaList from '../components/attendance-schema/AttendanceSchemaList';
import AttendanceSchemaFormSimple from '../components/attendance-schema/AttendanceSchemaFormSimple';
import AttendanceSchemaView from '../components/attendance-schema/AttendanceSchemaView';
import AttendanceSchemaAssignment from '../components/attendance-schema/AttendanceSchemaAssignment';
import QuickSetupWizard from '../components/attendance-schema/QuickSetupWizard';
import BulkAssignment from '../components/attendance-schema/BulkAssignment';
import AttendanceGlobalSettingsPanel from '../components/attendance-schema/AttendanceGlobalSettingsPanel';
import UserSchemaManagement from './UserSchemaManagement';

const AttendanceSchemaManagement = () => {
  const [activeTab, setActiveTab] = useState('overview');
  const [currentView, setCurrentView] = useState('list');
  const [selectedSchema, setSelectedSchema] = useState(null);

  const tabs = useMemo(
    () => [
      {
        key: 'overview',
        label: 'Ringkasan',
        description: 'Lihat alur kerja dan navigasi utama pengaturan absensi.',
        icon: LayoutDashboard,
      },
      {
        key: 'global',
        label: 'Pengaturan Global',
        description: 'Atur mode verifikasi, kebijakan wajah, disiplin default, dan policy operasional.',
        icon: ShieldCheck,
      },
      {
        key: 'quick-setup',
        label: 'Setup Cepat',
        description: 'Buat skema dasar siswa dalam wizard singkat sebelum assignment.',
        icon: Zap,
      },
      {
        key: 'schemas',
        label: 'Kelola Skema',
        description: 'Kelola daftar skema, edit detail, dan assignment per skema.',
        icon: Settings,
      },
      {
        key: 'bulk-assign',
        label: 'Assignment Massal',
        description: 'Tetapkan skema ke banyak siswa sekaligus.',
        icon: UserPlus,
      },
      {
        key: 'users',
        label: 'Monitoring Siswa',
        description: 'Pantau skema efektif yang dipakai tiap siswa.',
        icon: Users,
      },
    ],
    []
  );

  const activeTabMeta = useMemo(
    () => tabs.find((tab) => tab.key === activeTab) || tabs[0],
    [activeTab, tabs]
  );

  const handleEdit = (schema) => {
    setSelectedSchema(schema);
    setCurrentView('form');
  };

  const handleView = (schema) => {
    setSelectedSchema(schema);
    setCurrentView('view');
  };

  const handleAssign = (schema) => {
    setSelectedSchema(schema);
    setCurrentView('assign');
  };

  const handleSave = () => {
    setCurrentView('list');
    setSelectedSchema(null);
  };

  const handleCancel = () => {
    setCurrentView('list');
    setSelectedSchema(null);
  };

  const handleTabChange = (nextTab) => {
    setActiveTab(nextTab);
    if (nextTab !== 'schemas') {
      setCurrentView('list');
      setSelectedSchema(null);
    }
  };

  const handleQuickSetupComplete = () => {
    setActiveTab('schemas');
    setCurrentView('list');
  };

  const handleBulkAssignComplete = () => {
    setActiveTab('users');
  };

  const renderOverview = () => (
    <div className="space-y-6">
      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <div className="flex items-start gap-4">
          <div className="h-12 w-12 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center">
            <Sparkles className="h-6 w-6" />
          </div>
          <div className="flex-1">
            <h2 className="text-xl font-semibold text-gray-900">Alur Pengaturan Absensi</h2>
            <p className="text-sm text-gray-600 mt-1">
              Gunakan urutan ini agar konfigurasi lebih mudah: atur policy global, buat skema, lalu assignment ke siswa.
            </p>
            <div className="flex flex-wrap gap-2 mt-3">
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                Mode: Geolocation + Selfie + Face
              </span>
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                Scope: Siswa Saja
              </span>
              <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                QR: Nonaktif Sementara
              </span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        {[
          {
            title: '1. Pengaturan Global',
            text: 'Kunci mode verifikasi, disiplin default, dan policy operasional.',
            tabKey: 'global',
            icon: ShieldCheck,
            tone: 'bg-blue-50 border-blue-200 text-blue-800',
          },
          {
            title: '2. Setup Cepat',
            text: 'Buat baseline skema siswa, lalu lanjutkan detail dan assignment di menu lain.',
            tabKey: 'quick-setup',
            icon: Zap,
            tone: 'bg-purple-50 border-purple-200 text-purple-800',
          },
          {
            title: '3. Kelola Skema',
            text: 'Atur detail jam, toleransi, GPS, dan foto.',
            tabKey: 'schemas',
            icon: Settings,
            tone: 'bg-emerald-50 border-emerald-200 text-emerald-800',
          },
          {
            title: '4. Assignment & Monitoring',
            text: 'Tetapkan skema lalu pantau skema aktif tiap siswa.',
            tabKey: 'bulk-assign',
            icon: Users,
            tone: 'bg-orange-50 border-orange-200 text-orange-800',
          },
        ].map((item) => {
          const Icon = item.icon;
          return (
            <button
              key={item.title}
              type="button"
              onClick={() => handleTabChange(item.tabKey)}
              className={`text-left p-4 rounded-xl border transition hover:shadow-sm ${item.tone}`}
            >
              <div className="flex items-center justify-between gap-2">
                <Icon className="h-5 w-5" />
                <ChevronRight className="h-4 w-4 opacity-80" />
              </div>
              <p className="mt-3 font-semibold text-sm">{item.title}</p>
              <p className="mt-1 text-xs opacity-90">{item.text}</p>
            </button>
          );
        })}
      </div>

      <div className="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 className="text-base font-semibold text-gray-900 mb-3">Akses Cepat</h3>
        <div className="flex flex-wrap gap-2">
          {tabs
            .filter((tab) => tab.key !== 'overview')
            .map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.key}
                  type="button"
                  onClick={() => handleTabChange(tab.key)}
                  className="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                >
                  <Icon className="h-4 w-4" />
                  {tab.label}
                </button>
              );
            })}
        </div>
      </div>
    </div>
  );

  const renderSchemaContent = () => {
    switch (currentView) {
      case 'form':
        return (
          <AttendanceSchemaFormSimple
            schema={selectedSchema}
            onSave={handleSave}
            onCancel={handleCancel}
          />
        );
      case 'view':
        return (
          <AttendanceSchemaView
            schema={selectedSchema}
            onEdit={() => handleEdit(selectedSchema)}
            onCancel={handleCancel}
          />
        );
      case 'assign':
        return (
          <AttendanceSchemaAssignment
            schema={selectedSchema}
            onCancel={handleCancel}
          />
        );
      default:
        return (
          <AttendanceSchemaList
            onEdit={handleEdit}
            onView={handleView}
            onAssign={handleAssign}
          />
        );
    }
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'global':
        return <AttendanceGlobalSettingsPanel />;
      case 'quick-setup':
        return (
          <QuickSetupWizard
            onComplete={handleQuickSetupComplete}
            onCancel={() => handleTabChange('overview')}
          />
        );
      case 'bulk-assign':
        return (
          <BulkAssignment
            onComplete={handleBulkAssignComplete}
            onCancel={() => handleTabChange('overview')}
          />
        );
      case 'schemas':
        return renderSchemaContent();
      case 'users':
        return <UserSchemaManagement />;
      default:
        return renderOverview();
    }
  };

  const renderSchemaSubHeader = () => {
    if (activeTab !== 'schemas') return null;

    if (currentView === 'list') {
      return (
        <div className="mb-4">
          <h2 className="text-xl font-semibold text-gray-900">Kelola Skema</h2>
          <p className="text-sm text-gray-600">Tambah, ubah, lihat, dan assignment skema absensi siswa.</p>
        </div>
      );
    }

    const subTitleMap = {
      form: selectedSchema ? 'Edit Skema' : 'Tambah Skema',
      view: 'Detail Skema',
      assign: 'Assignment Skema',
    };

    return (
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={handleCancel}
          className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali ke Daftar
        </button>
        <div className="text-sm text-gray-500">Kelola Skema</div>
        <ChevronRight className="h-4 w-4 text-gray-400" />
        <div className="text-sm font-medium text-gray-800">{subTitleMap[currentView] || 'Detail'}</div>
      </div>
    );
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="container mx-auto px-4 py-6 space-y-6">
        <div className="bg-white border border-gray-200 rounded-2xl p-6">
          <div className="flex flex-col gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Pengaturan Sistem Absensi</h1>
              <p className="text-sm text-gray-600 mt-1">
                Panel terpusat untuk konfigurasi absensi siswa: policy global, skema, assignment, dan monitoring.
              </p>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.key;
                return (
                  <button
                    key={tab.key}
                    type="button"
                    onClick={() => handleTabChange(tab.key)}
                    className={`text-left rounded-xl border p-3 transition ${
                      isActive
                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="inline-flex items-center gap-2">
                        <Icon className="h-4 w-4" />
                        <span className="font-medium text-sm">{tab.label}</span>
                      </div>
                    </div>
                    <p className={`mt-1 text-xs ${isActive ? 'text-blue-100' : 'text-gray-500'}`}>
                      {tab.description}
                    </p>
                  </button>
                );
              })}
            </div>
          </div>
        </div>
        {activeTab !== 'overview' && (
          <div className="bg-white border border-gray-200 rounded-2xl p-5">
            <h2 className="text-lg font-semibold text-gray-900">{activeTabMeta.label}</h2>
            <p className="text-sm text-gray-600 mt-1">{activeTabMeta.description}</p>
          </div>
        )}

        {renderSchemaSubHeader()}

        {renderContent()}
      </div>
    </div>
  );
};

export default AttendanceSchemaManagement;


import React, { useState } from 'react';
import RoleTab from './RoleManagement/RoleTab';
import JabatanTab from './RoleManagement/JabatanTab';
import JabatanRoleMapping from './RoleManagement/JabatanRoleMapping';

const RoleManagement = () => {
  const [activeTab, setActiveTab] = useState('role');

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Manajemen Role & Jabatan</h1>
        <p className="text-sm text-gray-600 mt-1">Kelola role, permission, jabatan dan mapping role-jabatan</p>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="-mb-px flex space-x-8" aria-label="Tabs">
          <button
            onClick={() => setActiveTab('role')}
            className={`${
              activeTab === 'role'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
          >
            Role & Permission
          </button>
          <button
            onClick={() => setActiveTab('jabatan')}
            className={`${
              activeTab === 'jabatan'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
          >
            Jabatan & Sub Jabatan
          </button>
          <button
            onClick={() => setActiveTab('mapping')}
            className={`${
              activeTab === 'mapping'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
          >
            Mapping Jabatan-Role
          </button>
        </nav>
      </div>

      {/* Tab Content */}
      <div className="bg-white rounded-lg shadow">
        <div className="p-6">
          {activeTab === 'role' && <RoleTab />}
          {activeTab === 'jabatan' && <JabatanTab />}
          {activeTab === 'mapping' && <JabatanRoleMapping />}
        </div>
      </div>
    </div>
  );
};

export default RoleManagement;

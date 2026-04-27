import React from 'react';
import RoleCard from './RoleCard';

const RoleList = ({ 
  roles, 
  loading, 
  onEdit, 
  onDelete, 
  onToggleStatus 
}) => {
  if (loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
        <span className="ml-3 text-gray-600">Memuat data role...</span>
      </div>
    );
  }

  const primaryRoles = roles.filter(role => !role.parent_role_id);

  if (primaryRoles.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <div className="text-gray-400 mb-4">
          <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">Belum ada role</h3>
        <p className="text-gray-600">Mulai dengan menambahkan role baru untuk sistem Anda.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {primaryRoles.map((primaryRole) => {
        const subRoles = roles.filter(role => role.parent_role_id === primaryRole.id);
        
        return (
          <RoleCard
            key={primaryRole.id}
            role={primaryRole}
            subRoles={subRoles}
            onEdit={onEdit}
            onDelete={onDelete}
            onToggleStatus={onToggleStatus}
          />
        );
      })}
    </div>
  );
};

export default RoleList;

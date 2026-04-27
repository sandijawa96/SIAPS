import React from 'react';
import { Shield, Plus } from 'lucide-react';

const RoleHeader = ({ onAddRole }) => {
  return (
    <div className="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-2xl p-8 mb-8 text-white">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <div className="p-3 bg-white bg-opacity-20 rounded-xl">
            <Shield className="w-8 h-8" />
          </div>
          <div>
            <h1 className="text-3xl font-bold">Manajemen Role</h1>
            <p className="text-purple-100 mt-2">
              Kelola role dan hak akses pengguna sistem
            </p>
          </div>
        </div>
        <button
          onClick={onAddRole}
          className="flex items-center gap-2 bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 backdrop-blur-sm"
        >
          <Plus className="w-5 h-5" />
          Tambah Role
        </button>
      </div>
    </div>
  );
};

export default RoleHeader;

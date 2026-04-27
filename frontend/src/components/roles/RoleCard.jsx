import React, { useState } from 'react';
import { 
  Shield, 
  User, 
  ChevronDown, 
  Edit2, 
  Trash2, 
  Check, 
  X, 
  Key,
  Users
} from 'lucide-react';

const RoleCard = ({ 
  role, 
  subRoles = [], 
  onEdit, 
  onDelete, 
  onToggleStatus 
}) => {
  const [isExpanded, setIsExpanded] = useState(false);

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200">
      {/* Primary Role Header */}
      <div className="p-6">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-4">
            <div className="p-3 bg-purple-50 rounded-xl">
              <Shield className="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                {role.display_name}
              </h3>
              <p className="text-sm text-gray-600 mt-1">
                {role.description || 'Tidak ada deskripsi'}
              </p>
              <div className="flex items-center gap-2 mt-2">
                <span className="text-xs text-gray-500">
                  Level {role.level}
                </span>
                <span className="text-xs text-gray-300">•</span>
                <span className="text-xs text-gray-500">
                  {role.permissions?.length || 0} Permission
                </span>
              </div>
            </div>
          </div>
          
          {subRoles.length > 0 && (
            <button
              onClick={() => setIsExpanded(!isExpanded)}
              className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <ChevronDown 
                className={`w-5 h-5 transform transition-transform duration-200 ${
                  isExpanded ? 'rotate-180' : ''
                }`} 
              />
            </button>
          )}
        </div>

        {/* Role Tags */}
        <div className="flex flex-wrap gap-2 mt-4">
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
            Primary Role
          </span>
          
          <button
            onClick={() => onToggleStatus(role.id, role.is_active)}
            className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              role.is_active
                ? 'bg-green-100 text-green-800 hover:bg-green-200'
                : 'bg-red-100 text-red-800 hover:bg-red-200'
            }`}
          >
            {role.is_active ? (
              <Check className="w-3 h-3 mr-1" />
            ) : (
              <X className="w-3 h-3 mr-1" />
            )}
            {role.is_active ? 'Aktif' : 'Non-aktif'}
          </button>

          {subRoles.length > 0 && (
            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              <Users className="w-3 h-3 mr-1" />
              {subRoles.length} Sub Role
            </span>
          )}
        </div>

        {/* Actions */}
        <div className="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100">
          <button
            onClick={() => onEdit(role)}
            className="inline-flex items-center px-3 py-2 text-sm text-purple-600 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-colors"
          >
            <Edit2 className="w-4 h-4 mr-1" />
            Edit
          </button>
          <button
            onClick={() => onDelete(role.id)}
            className="inline-flex items-center px-3 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
          >
            <Trash2 className="w-4 h-4 mr-1" />
            Hapus
          </button>
        </div>
      </div>

      {/* Sub Roles */}
      {isExpanded && subRoles.length > 0 && (
        <div className="border-t border-gray-200 bg-gray-50 p-6">
          <h4 className="text-sm font-medium text-gray-700 mb-4 flex items-center gap-2">
            <User className="w-4 h-4" />
            Sub Roles ({subRoles.length})
          </h4>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {subRoles.map((subRole) => (
              <div
                key={subRole.id}
                className="bg-white rounded-lg border border-orange-200 p-4 hover:shadow-sm transition-shadow"
              >
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-orange-50 rounded-lg">
                      <User className="w-4 h-4 text-orange-600" />
                    </div>
                    <div>
                      <h5 className="font-medium text-gray-900">
                        {subRole.display_name}
                      </h5>
                      <p className="text-xs text-gray-600 mt-1">
                        {subRole.description || 'Tidak ada deskripsi'}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Sub Role Tags */}
                <div className="flex flex-wrap gap-2 mb-3">
                  <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Sub Role
                  </span>
                  
                  <button
                    onClick={() => onToggleStatus(subRole.id, subRole.is_active)}
                    className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium transition-colors ${
                      subRole.is_active
                        ? 'bg-green-100 text-green-800 hover:bg-green-200'
                        : 'bg-red-100 text-red-800 hover:bg-red-200'
                    }`}
                  >
                    {subRole.is_active ? (
                      <Check className="w-3 h-3 mr-1" />
                    ) : (
                      <X className="w-3 h-3 mr-1" />
                    )}
                    {subRole.is_active ? 'Aktif' : 'Non-aktif'}
                  </button>

                  <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <Key className="w-3 h-3 mr-1" />
                    {subRole.permissions?.length || 0}
                  </span>
                </div>

                {/* Sub Role Actions */}
                <div className="flex items-center gap-2 pt-2 border-t border-gray-100">
                  <button
                    onClick={() => onEdit(subRole)}
                    className="inline-flex items-center px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded transition-colors"
                  >
                    <Edit2 className="w-3 h-3 mr-1" />
                    Edit
                  </button>
                  <button
                    onClick={() => onDelete(subRole.id)}
                    className="inline-flex items-center px-2 py-1 text-xs text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
                  >
                    <Trash2 className="w-3 h-3 mr-1" />
                    Hapus
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default RoleCard;

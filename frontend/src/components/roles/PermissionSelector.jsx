import React, { useState } from 'react';
import { 
  ChevronDown, 
  ChevronRight, 
  Shield, 
  Users, 
  BookOpen, 
  BarChart3, 
  Settings, 
  GraduationCap,
  Megaphone
} from 'lucide-react';

const PermissionSelector = ({ permissions = {}, selectedPermissions = [], onPermissionChange }) => {
  const [expandedCategories, setExpandedCategories] = useState({
    'Kelas Management': true,
    'User Management': true,
    'Academic Management': true,
    'Communication & Broadcast': true,
    'System Management': true
  });

  // Kategorisasi permission berdasarkan nama
  const categorizePermissions = (permissions) => {
    const categories = {
      'Kelas Management': {
        icon: BookOpen,
        color: 'blue',
        permissions: []
      },
      'User Management': {
        icon: Users,
        color: 'green',
        permissions: []
      },
      'Academic Management': {
        icon: GraduationCap,
        color: 'purple',
        permissions: []
      },
      'Communication & Broadcast': {
        icon: Megaphone,
        color: 'orange',
        permissions: []
      },
      'System Management': {
        icon: Settings,
        color: 'gray',
        permissions: []
      },
      'Reports & Analytics': {
        icon: BarChart3,
        color: 'orange',
        permissions: []
      }
    };

    // Flatten all permissions from all modules
    const allPermissions = [];
    Object.entries(permissions).forEach(([module, modulePermissions]) => {
      modulePermissions.forEach(permission => {
        allPermissions.push({
          ...permission,
          module
        });
      });
    });

    // Categorize permissions
    allPermissions.forEach(permission => {
      const permName = permission.name.toLowerCase();
      
      if (permName.includes('kelas') || permName.includes('tingkat') || permName.includes('wali')) {
        categories['Kelas Management'].permissions.push(permission);
      } else if (permName.includes('user') || permName.includes('pegawai') || permName.includes('siswa') || permName.includes('role')) {
        categories['User Management'].permissions.push(permission);
      } else if (
        permName.includes('absensi')
        || permName.includes('izin')
        || permName.includes('tahun_ajaran')
        || permName.includes('face')
        || permName.includes('template')
      ) {
        categories['Academic Management'].permissions.push(permission);
      } else if (
        permName.includes('broadcast')
        || permName.includes('notification')
        || permName.includes('whatsapp')
        || permName.includes('email')
        || permName.includes('qrcode')
      ) {
        categories['Communication & Broadcast'].permissions.push(permission);
      } else if (permName.includes('dashboard') || permName.includes('report') || permName.includes('export') || permName.includes('statistics')) {
        categories['Reports & Analytics'].permissions.push(permission);
      } else if (permName.includes('setting') || permName.includes('backup') || permName.includes('restore')) {
        categories['System Management'].permissions.push(permission);
      } else {
        // Default ke System Management
        categories['System Management'].permissions.push(permission);
      }
    });

    // Remove empty categories
    Object.keys(categories).forEach(key => {
      if (categories[key].permissions.length === 0) {
        delete categories[key];
      }
    });

    return categories;
  };

  const toggleCategory = (categoryName) => {
    setExpandedCategories(prev => ({
      ...prev,
      [categoryName]: !prev[categoryName]
    }));
  };

  const handleCategorySelectAll = (categoryPermissions, isAllSelected) => {
    categoryPermissions.forEach(permission => {
      if (isAllSelected && selectedPermissions.includes(permission.name)) {
        onPermissionChange(permission.name); // Remove
      } else if (!isAllSelected && !selectedPermissions.includes(permission.name)) {
        onPermissionChange(permission.name); // Add
      }
    });
  };

  const getColorClasses = (color) => {
    const colorMap = {
      blue: {
        bg: 'bg-blue-50',
        text: 'text-blue-600',
        border: 'border-blue-200',
        badge: 'bg-blue-100 text-blue-800'
      },
      green: {
        bg: 'bg-green-50',
        text: 'text-green-600',
        border: 'border-green-200',
        badge: 'bg-green-100 text-green-800'
      },
      purple: {
        bg: 'bg-purple-50',
        text: 'text-purple-600',
        border: 'border-purple-200',
        badge: 'bg-purple-100 text-purple-800'
      },
      orange: {
        bg: 'bg-orange-50',
        text: 'text-orange-600',
        border: 'border-orange-200',
        badge: 'bg-orange-100 text-orange-800'
      },
      gray: {
        bg: 'bg-gray-50',
        text: 'text-gray-600',
        border: 'border-gray-200',
        badge: 'bg-gray-100 text-gray-800'
      }
    };
    return colorMap[color] || colorMap.gray;
  };

  const categorizedPermissions = categorizePermissions(permissions);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
          <Shield className="w-4 h-4" />
          Permissions ({selectedPermissions.length} dipilih)
        </h4>
        <div className="text-xs text-gray-500">
          Total {Object.values(categorizedPermissions).reduce((sum, cat) => sum + cat.permissions.length, 0)} permissions
        </div>
      </div>

      <div className="space-y-3">
        {Object.entries(categorizedPermissions).map(([categoryName, category]) => {
          const isExpanded = expandedCategories[categoryName];
          const colors = getColorClasses(category.color);
          const IconComponent = category.icon;
          
          const selectedInCategory = category.permissions.filter(p => 
            selectedPermissions.includes(p.name)
          ).length;
          const totalInCategory = category.permissions.length;
          const isAllSelected = selectedInCategory === totalInCategory;
          const isPartialSelected = selectedInCategory > 0 && selectedInCategory < totalInCategory;

          return (
            <div key={categoryName} className={`border ${colors.border} rounded-lg overflow-hidden`}>
              {/* Category Header */}
              <div 
                className={`${colors.bg} p-4 cursor-pointer hover:opacity-80 transition-opacity`}
                onClick={() => toggleCategory(categoryName)}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <IconComponent className={`w-5 h-5 ${colors.text}`} />
                    <div>
                      <h5 className="font-medium text-gray-900">{categoryName}</h5>
                      <p className="text-sm text-gray-600">
                        {selectedInCategory} dari {totalInCategory} permission dipilih
                      </p>
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleCategorySelectAll(category.permissions, isAllSelected);
                      }}
                      className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                        isAllSelected 
                          ? `${colors.badge} hover:opacity-80`
                          : isPartialSelected
                          ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
                          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      }`}
                    >
                      {isAllSelected ? 'Hapus Semua' : isPartialSelected ? 'Pilih Semua' : 'Pilih Semua'}
                    </button>
                    
                    {isExpanded ? (
                      <ChevronDown className="w-4 h-4 text-gray-400" />
                    ) : (
                      <ChevronRight className="w-4 h-4 text-gray-400" />
                    )}
                  </div>
                </div>
              </div>

              {/* Category Content */}
              {isExpanded && (
                <div className="p-4 bg-white border-t border-gray-100">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {category.permissions.map((permission) => {
                      const isSelected = selectedPermissions.includes(permission.name);
                      
                      return (
                        <label
                          key={permission.name}
                          className="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg cursor-pointer group transition-colors"
                        >
                          <div className="flex-1 min-w-0">
                            <div className="text-sm font-medium text-gray-900 truncate">
                              {permission.display_name || permission.name}
                            </div>
                            <div className="text-xs text-gray-500 truncate">
                              {permission.module} • {permission.name}
                            </div>
                          </div>
                          
                          <div className="ml-3 flex-shrink-0">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => onPermissionChange(permission.name)}
                              className={`w-4 h-4 rounded border-gray-300 focus:ring-2 transition-colors ${colors.text.replace('text-', 'text-')} focus:ring-${category.color}-500`}
                            />
                          </div>
                        </label>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Summary */}
      {selectedPermissions.length > 0 && (
        <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <div className="flex items-center gap-2">
            <Shield className="w-4 h-4 text-blue-600" />
            <span className="text-sm font-medium text-blue-900">
              {selectedPermissions.length} permission dipilih
            </span>
          </div>
          <div className="mt-2 flex flex-wrap gap-1">
            {selectedPermissions.slice(0, 5).map(permName => (
              <span key={permName} className="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                {permName}
              </span>
            ))}
            {selectedPermissions.length > 5 && (
              <span className="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                +{selectedPermissions.length - 5} lainnya
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default PermissionSelector;

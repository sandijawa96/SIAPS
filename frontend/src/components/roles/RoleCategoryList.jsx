import React, { useState } from 'react';
import { 
  Shield, 
  User, 
  ChevronDown, 
  ChevronRight,
  Users,
  Settings,
  BookOpen,
  Calendar,
  FileText,
  BarChart3
} from 'lucide-react';
import RoleCard from './RoleCard';

const RoleCategoryList = ({ 
  roles, 
  loading, 
  onEdit, 
  onDelete, 
  onToggleStatus 
}) => {
  const [expandedCategories, setExpandedCategories] = useState({
    'administrative': true,
    'academic': true,
    'operational': true,
    'student': true
  });

  if (loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
        <span className="ml-3 text-gray-600">Memuat data role...</span>
      </div>
    );
  }

  // Kategorisasi role berdasarkan nama dan level
  const categorizeRoles = (roles) => {
    const categories = {
      administrative: {
        title: 'Administrative Roles',
        description: 'Role untuk administrasi sistem dan manajemen',
        icon: Settings,
        color: 'purple',
        roles: []
      },
      academic: {
        title: 'Academic Roles', 
        description: 'Role untuk kegiatan akademik dan pembelajaran',
        icon: BookOpen,
        color: 'blue',
        roles: []
      },
      operational: {
        title: 'Operational Roles',
        description: 'Role untuk operasional sehari-hari',
        icon: Users,
        color: 'green',
        roles: []
      },
      student: {
        title: 'Student Roles',
        description: 'Role untuk siswa dan pembelajaran',
        icon: User,
        color: 'orange',
        roles: []
      }
    };

    roles.forEach(role => {
      const roleName = role.name.toLowerCase();
      
      if (roleName.includes('admin') || roleName.includes('super')) {
        categories.administrative.roles.push(role);
      } else if (roleName.includes('wali') || roleName.includes('guru') || roleName.includes('teacher')) {
        categories.academic.roles.push(role);
      } else if (roleName.includes('staff') || roleName.includes('pegawai') || roleName.includes('operator')) {
        categories.operational.roles.push(role);
      } else if (roleName.includes('siswa') || roleName.includes('student')) {
        categories.student.roles.push(role);
      } else {
        // Default ke operational jika tidak cocok kategori lain
        categories.operational.roles.push(role);
      }
    });

    return categories;
  };

  const toggleCategory = (categoryKey) => {
    setExpandedCategories(prev => ({
      ...prev,
      [categoryKey]: !prev[categoryKey]
    }));
  };

  const categories = categorizeRoles(roles);
  const hasRoles = roles.length > 0;

  if (!hasRoles) {
    return (
      <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <div className="text-gray-400 mb-4">
          <Shield className="w-16 h-16 mx-auto" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">Belum ada role</h3>
        <p className="text-gray-600">Mulai dengan menambahkan role baru untuk sistem Anda.</p>
      </div>
    );
  }

  const getColorClasses = (color) => {
    const colorMap = {
      purple: {
        bg: 'bg-purple-50',
        text: 'text-purple-600',
        border: 'border-purple-200',
        badge: 'bg-purple-100 text-purple-800'
      },
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
      orange: {
        bg: 'bg-orange-50',
        text: 'text-orange-600',
        border: 'border-orange-200',
        badge: 'bg-orange-100 text-orange-800'
      }
    };
    return colorMap[color] || colorMap.purple;
  };

  return (
    <div className="space-y-6">
      {Object.entries(categories).map(([categoryKey, category]) => {
        if (category.roles.length === 0) return null;
        
        const isExpanded = expandedCategories[categoryKey];
        const colors = getColorClasses(category.color);
        const IconComponent = category.icon;

        return (
          <div key={categoryKey} className="bg-white rounded-xl border border-gray-200 shadow-sm">
            {/* Category Header */}
            <div 
              className="p-6 cursor-pointer hover:bg-gray-50 transition-colors"
              onClick={() => toggleCategory(categoryKey)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className={`p-3 ${colors.bg} rounded-xl`}>
                    <IconComponent className={`w-6 h-6 ${colors.text}`} />
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">
                      {category.title}
                    </h3>
                    <p className="text-sm text-gray-600 mt-1">
                      {category.description}
                    </p>
                    <div className="flex items-center gap-2 mt-2">
                      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${colors.badge}`}>
                        {category.roles.length} Role{category.roles.length > 1 ? 's' : ''}
                      </span>
                      <span className="text-xs text-gray-500">
                        Total {category.roles.reduce((sum, role) => sum + (role.permissions?.length || 0), 0)} Permissions
                      </span>
                    </div>
                  </div>
                </div>
                
                <div className="flex items-center gap-2">
                  {isExpanded ? (
                    <ChevronDown className="w-5 h-5 text-gray-400" />
                  ) : (
                    <ChevronRight className="w-5 h-5 text-gray-400" />
                  )}
                </div>
              </div>
            </div>

            {/* Category Content */}
            {isExpanded && (
              <div className={`border-t ${colors.border} bg-gray-50 p-6`}>
                <div className="space-y-4">
                  {category.roles.map((role) => {
                    const subRoles = roles.filter(r => r.parent_role_id === role.id);
                    
                    return (
                      <div key={role.id} className="bg-white rounded-lg border border-gray-200">
                        <RoleCard
                          role={role}
                          subRoles={subRoles}
                          onEdit={onEdit}
                          onDelete={onDelete}
                          onToggleStatus={onToggleStatus}
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default RoleCategoryList;

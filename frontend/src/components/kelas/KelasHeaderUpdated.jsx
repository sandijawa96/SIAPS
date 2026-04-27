import React from 'react';
import { 
  Button,
  Chip,
  IconButton,
  Tooltip
} from '@mui/material';
import { 
  Plus, 
  Users, 
  CalendarDays,
  AlertCircle,
  RefreshCw,
  FileUp
} from 'lucide-react';

const KelasHeader = ({
  activeTab,
  activeTahunAjaran,
  onAddItem,
  onBulkAssignWali,
  onImportExport,
  onRefresh,
  kelasList = [],
  loading = false,
  canManageKelas = true
}) => {
  return (
    <div className="mb-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {activeTab === 'kelas' ? 'Manajemen Kelas' : 'Manajemen Tingkat'}
          </h1>
          <p className="text-sm text-gray-600 mt-1">
            {activeTab === 'kelas' 
              ? 'Kelola data kelas dan wali kelas' 
              : 'Kelola tingkat kelas dan strukturnya'}
          </p>
        </div>

        <div className="flex items-center gap-3">
          {/* Refresh Button */}
          <Tooltip title="Refresh Data">
            <IconButton 
              onClick={onRefresh}
              className={`text-gray-600 hover:text-gray-900 ${loading ? 'animate-spin' : ''}`}
            >
              <RefreshCw className="w-5 h-5" />
            </IconButton>
          </Tooltip>

          {/* Import/Export Button - Only show for Kelas tab and manager */}
          {activeTab === 'kelas' && canManageKelas && (
            <Button
              variant="outlined"
              onClick={onImportExport}
              startIcon={<FileUp className="w-4 h-4" />}
              className="hidden md:flex"
            >
              Import/Export
            </Button>
          )}

          {/* Bulk Assign Button - Only show for Kelas tab and manager */}
          {activeTab === 'kelas' && canManageKelas && (
            <Button
              variant="outlined"
              onClick={() => onBulkAssignWali([])}
              startIcon={<Users className="w-4 h-4" />}
              className="hidden md:flex"
              disabled={!kelasList.length}
            >
              Tugaskan Wali Kelas
            </Button>
          )}

          {/* Add Button */}
          {canManageKelas && (
            <Button
              variant="contained"
              onClick={onAddItem}
              startIcon={<Plus className="w-4 h-4" />}
              className="bg-blue-600 hover:bg-blue-700 text-white"
            >
              {activeTab === 'kelas' ? 'Tambah Kelas' : 'Tambah Tingkat'}
            </Button>
          )}
        </div>
      </div>

      {/* Active Tahun Ajaran Info */}
      <div className="mt-4 flex flex-wrap items-center gap-2">
        <div className="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg">
          <CalendarDays className="w-4 h-4 text-gray-500" />
          <span className="text-sm text-gray-600">Tahun Ajaran Aktif:</span>
          {activeTahunAjaran ? (
            <Chip 
              label={activeTahunAjaran.nama}
              color="primary"
              size="small"
            />
          ) : (
            <div className="flex items-center gap-2 text-yellow-600">
              <AlertCircle className="w-4 h-4" />
              <span className="text-sm font-medium">Belum ada tahun ajaran aktif</span>
            </div>
          )}
        </div>

        {activeTab === 'kelas' && (
          <div className="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg">
            <Users className="w-4 h-4 text-gray-500" />
            <span className="text-sm text-gray-600">Total Kelas:</span>
            <span className="text-sm font-medium">{kelasList.length}</span>
          </div>
        )}
      </div>

      {/* Mobile Action Buttons */}
      <div className="md:hidden flex flex-wrap gap-2 mt-4">
        {activeTab === 'kelas' && canManageKelas && (
          <>
            <Button
              variant="outlined"
              onClick={onImportExport}
              startIcon={<FileUp className="w-4 h-4" />}
              fullWidth
            >
              Import/Export
            </Button>
            <Button
              variant="outlined"
              onClick={() => onBulkAssignWali([])}
              startIcon={<Users className="w-4 h-4" />}
              fullWidth
              disabled={!kelasList.length}
            >
              Tugaskan Wali Kelas
            </Button>
          </>
        )}
      </div>
    </div>
  );
};

export default KelasHeader;

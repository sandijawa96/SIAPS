import React, { useState, useEffect } from 'react';
import { Users, Clock, CheckCircle, AlertCircle, X, Play } from 'lucide-react';
import { toast } from 'react-hot-toast';
import VirtualizedUserList from './VirtualizedUserList';
import api from '../../services/api';
import useServerClock from '../../hooks/useServerClock';

const BulkAssignmentOptimized = ({ isOpen, onClose, selectedSchema }) => {
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [isAssigning, setIsAssigning] = useState(false);
  const [assignmentProgress, setAssignmentProgress] = useState(null);
  const [assignmentOptions, setAssignmentOptions] = useState({
    start_date: '',
    end_date: '',
    assignment_type: 'manual'
  });

  useEffect(() => {
    if (!isOpen || !isServerClockSynced || !serverDate) {
      return;
    }

    setAssignmentOptions((current) => ({
      ...current,
      start_date: current.start_date || serverDate,
    }));
  }, [isOpen, isServerClockSynced, serverDate]);

  // Reset when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setSelectedUsers([]);
      setAssignmentProgress(null);
    }
  }, [isOpen]);

  const handleBulkAssignment = async () => {
    if (selectedUsers.length === 0) {
      toast.error('Pilih minimal satu user untuk assignment');
      return;
    }

    if (!selectedSchema) {
      toast.error('Schema tidak ditemukan');
      return;
    }

    setIsAssigning(true);
    setAssignmentProgress({
      status: 'starting',
      progress: 0,
      total: selectedUsers.length,
      processed: 0,
      errors: []
    });

    try {
      const response = await api.post('/bulk-assignment/assign', {
        user_ids: selectedUsers.map(u => u.id),
        schema_id: selectedSchema.id,
        ...assignmentOptions
      });

      if (response.data.success) {
        const results = response.data.data;
        
        setAssignmentProgress({
          status: 'completed',
          progress: 100,
          total: results.total_users,
          processed: results.processed,
          skipped: results.skipped,
          errors: results.errors,
          processing_time: results.processing_time
        });

        if (results.errors.length === 0) {
          toast.success(`Berhasil assign ${results.processed} user dalam ${results.processing_time}s`);
        } else {
          toast.warning(`Assignment selesai dengan ${results.errors.length} error`);
        }
      } else {
        throw new Error(response.data.message || 'Assignment gagal');
      }
    } catch (error) {
      console.error('Bulk assignment error:', error);
      setAssignmentProgress({
        status: 'failed',
        progress: 0,
        total: selectedUsers.length,
        processed: 0,
        errors: [error.response?.data?.message || error.message || 'Assignment gagal']
      });
      toast.error('Assignment gagal');
    } finally {
      setIsAssigning(false);
    }
  };

  const resetAssignment = () => {
    setSelectedUsers([]);
    setAssignmentProgress(null);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div className="flex items-center gap-3">
            <Users className="h-6 w-6 text-blue-600" />
            <div>
              <h2 className="text-xl font-semibold text-gray-900">
                Bulk Assignment (Optimized)
              </h2>
              {selectedSchema && (
                <p className="text-sm text-gray-600">
                  Schema: {selectedSchema.schema_name}
                </p>
              )}
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="h-5 w-5 text-gray-500" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 flex overflow-hidden">
          {/* User Selection */}
          <div className="flex-1 p-6 overflow-hidden">
            <VirtualizedUserList
              selectedUsers={selectedUsers}
              onSelectionChange={setSelectedUsers}
              height={500}
              showSchemaInfo={true}
            />
          </div>

          {/* Assignment Panel */}
          <div className="w-80 border-l border-gray-200 p-6 overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Assignment Options
            </h3>

            {/* Options Form */}
            <div className="space-y-4 mb-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tanggal Mulai
                </label>
                <input
                  type="date"
                  value={assignmentOptions.start_date}
                  onChange={(e) => setAssignmentOptions(prev => ({
                    ...prev,
                    start_date: e.target.value
                  }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tanggal Berakhir (Opsional)
                </label>
                <input
                  type="date"
                  value={assignmentOptions.end_date}
                  onChange={(e) => setAssignmentOptions(prev => ({
                    ...prev,
                    end_date: e.target.value
                  }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tipe Assignment
                </label>
                <select
                  value={assignmentOptions.assignment_type}
                  onChange={(e) => setAssignmentOptions(prev => ({
                    ...prev,
                    assignment_type: e.target.value
                  }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="manual">Manual</option>
                  <option value="auto">Auto</option>
                </select>
              </div>
            </div>

            {/* Progress Display */}
            {assignmentProgress && (
              <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                <div className="flex items-center gap-2 mb-2">
                  {assignmentProgress.status === 'completed' ? (
                    <CheckCircle className="h-5 w-5 text-green-600" />
                  ) : assignmentProgress.status === 'failed' ? (
                    <AlertCircle className="h-5 w-5 text-red-600" />
                  ) : (
                    <Clock className="h-5 w-5 text-blue-600 animate-spin" />
                  )}
                  <span className="font-medium text-gray-900">
                    {assignmentProgress.status === 'completed' ? 'Selesai' :
                     assignmentProgress.status === 'failed' ? 'Gagal' : 'Memproses...'}
                  </span>
                </div>

                <div className="space-y-2 text-sm text-gray-600">
                  <div>Total: {assignmentProgress.total}</div>
                  <div>Diproses: {assignmentProgress.processed}</div>
                  {assignmentProgress.skipped > 0 && (
                    <div>Dilewati: {assignmentProgress.skipped}</div>
                  )}
                  {assignmentProgress.processing_time && (
                    <div>Waktu: {assignmentProgress.processing_time}s</div>
                  )}
                </div>

                {assignmentProgress.errors.length > 0 && (
                  <div className="mt-2">
                    <div className="text-sm font-medium text-red-600 mb-1">
                      Errors ({assignmentProgress.errors.length}):
                    </div>
                    <div className="text-xs text-red-600 max-h-20 overflow-y-auto">
                      {assignmentProgress.errors.map((error, index) => (
                        <div key={index}>• {error}</div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Action Buttons */}
            <div className="space-y-3">
              <button
                onClick={handleBulkAssignment}
                disabled={selectedUsers.length === 0 || isAssigning}
                className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <Play className="h-4 w-4" />
                {isAssigning ? 'Memproses...' : `Assign ${selectedUsers.length} User`}
              </button>

              {assignmentProgress && (
                <button
                  onClick={resetAssignment}
                  className="w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                >
                  Reset
                </button>
              )}

              <button
                onClick={onClose}
                className="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Tutup
              </button>
            </div>

            {/* Performance Info */}
            <div className="mt-6 p-3 bg-blue-50 rounded-lg">
              <div className="text-xs text-blue-800">
                <div className="font-medium mb-1">Performance Features:</div>
                <div>• Virtual scrolling untuk 1000+ users</div>
                <div>• Batch processing (100 users/batch)</div>
                <div>• Debounced search (300ms)</div>
                <div>• Optimized caching</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BulkAssignmentOptimized;

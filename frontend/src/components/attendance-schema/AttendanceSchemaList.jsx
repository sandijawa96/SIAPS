import React, { useState, useEffect } from 'react';
import { 
  Card, 
  CardHeader, 
  CardTitle, 
  CardContent 
} from '../ui/Card';
import { Button } from '../ui/Button';
import { Badge } from '../ui/Badge';
import { 
  Plus, 
  Edit, 
  Trash2, 
  Eye, 
  ToggleLeft, 
  ToggleRight,
  Star,
  StarOff,
  Users,
  Clock,
  MapPin,
  Camera,
  Smartphone
} from 'lucide-react';
import attendanceSchemaService from '../../services/attendanceSchemaService';
import { toast } from 'react-hot-toast';
import {
  getSchemaLocationPreviewText,
  resolveSchemaLocationDetails,
} from '../../utils/locationGeofence';

const AttendanceSchemaList = ({ onEdit, onView, onAssign }) => {
  const [schemas, setSchemas] = useState([]);
  const [availableLocations, setAvailableLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState({});

  useEffect(() => {
    fetchSchemas();
  }, []);

  const fetchSchemas = async () => {
    try {
      setLoading(true);
      const [response, locations] = await Promise.all([
        attendanceSchemaService.getAllSchemas(),
        attendanceSchemaService.getGpsLocations(),
      ]);
      setSchemas(response.data || []);
      setAvailableLocations(locations);
    } catch (error) {
      console.error('Error fetching schemas:', error);
      toast.error('Gagal memuat daftar skema absensi');
    } finally {
      setLoading(false);
    }
  };

  const handleToggleActive = async (id) => {
    try {
      setActionLoading(prev => ({ ...prev, [id]: 'toggle' }));
      await attendanceSchemaService.toggleActive(id);
      toast.success('Status skema berhasil diubah');
      fetchSchemas();
    } catch (error) {
      console.error('Error toggling schema:', error);
      toast.error('Gagal mengubah status skema');
    } finally {
      setActionLoading(prev => ({ ...prev, [id]: null }));
    }
  };

  const handleSetDefault = async (id) => {
    try {
      setActionLoading(prev => ({ ...prev, [id]: 'default' }));
      await attendanceSchemaService.setDefault(id);
      toast.success('Skema default berhasil diubah');
      fetchSchemas();
    } catch (error) {
      console.error('Error setting default schema:', error);
      toast.error('Gagal mengubah skema default');
    } finally {
      setActionLoading(prev => ({ ...prev, [id]: null }));
    }
  };

  const handleDelete = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus skema ini?')) {
      return;
    }

    try {
      setActionLoading(prev => ({ ...prev, [id]: 'delete' }));
      await attendanceSchemaService.deleteSchema(id);
      toast.success('Skema berhasil dihapus');
      fetchSchemas();
    } catch (error) {
      console.error('Error deleting schema:', error);
      toast.error('Gagal menghapus skema');
    } finally {
      setActionLoading(prev => ({ ...prev, [id]: null }));
    }
  };

  const getSchemaTypeColor = (type) => {
    const colors = {
      global: 'bg-gray-100 text-gray-800',
      siswa: 'bg-blue-100 text-blue-800',
      honorer: 'bg-yellow-100 text-yellow-800',
      asn: 'bg-green-100 text-green-800',
      guru_honorer: 'bg-purple-100 text-purple-800',
      staff_asn: 'bg-indigo-100 text-indigo-800',
      role: 'bg-purple-100 text-purple-800',
      status: 'bg-orange-100 text-orange-800',
      user: 'bg-pink-100 text-pink-800'
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
  };

  const getTargetInfo = (schema) => {
    const parts = [];
    
    if (schema.target_role) {
      parts.push(`Role: ${schema.target_role}`);
    }
    
    if (schema.target_status) {
      parts.push(`Status: ${schema.target_status}`);
    }
    
    if (parts.length === 0) {
      if (schema.schema_type === 'global') {
        return 'Berlaku untuk semua siswa';
      } else {
        return `Tipe: ${schema.schema_type}`;
      }
    }
    
    return parts.join(' | ');
  };

  const formatTime = (time) => {
    if (!time) return '-';
    return time.substring(0, 5); // HH:MM format
  };

  const getEffectiveStudentSchedule = (schema) => ({
    jamMasuk: schema?.siswa_jam_masuk || schema?.jam_masuk_default,
    jamPulang: schema?.siswa_jam_pulang || schema?.jam_pulang_default,
    toleransi:
      schema?.siswa_toleransi !== undefined && schema?.siswa_toleransi !== null
        ? schema.siswa_toleransi
        : schema?.toleransi_default,
  });

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="bg-white border border-gray-200 rounded-xl p-5 flex justify-between items-center">
        <div>
          <h2 className="text-lg font-semibold text-gray-900">Daftar Skema Absensi</h2>
          <p className="text-sm text-gray-600 mt-1">Kelola template absensi siswa dan parameter wajibnya.</p>
        </div>
        <Button onClick={() => onEdit?.(null)} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white">
          <Plus className="h-4 w-4" />
          Tambah Skema
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {schemas.map((schema) => (
          <Card key={schema.id} className="relative">
            {(() => {
              const schedule = getEffectiveStudentSchedule(schema);
              const locationResolution = resolveSchemaLocationDetails(schema, availableLocations);
              const locationPreview = getSchemaLocationPreviewText(schema, availableLocations, 2);

              return (
                <>
                  <CardHeader className="pb-3">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <CardTitle className="text-lg flex items-center gap-2">
                          {schema.schema_name}
                          {schema.is_default && (
                            <Star className="h-4 w-4 text-yellow-500 fill-current" />
                          )}
                        </CardTitle>
                        <div className="flex items-center gap-2 mt-2">
                          <Badge className={getSchemaTypeColor(schema.schema_type)}>
                            {schema.schema_type}
                          </Badge>
                          <Badge variant={schema.is_active ? 'success' : 'secondary'}>
                            {schema.is_active ? 'Aktif' : 'Nonaktif'}
                          </Badge>
                          {schema.is_mandatory && (
                            <Badge variant="destructive">Wajib</Badge>
                          )}
                        </div>
                      </div>
                    </div>
                  </CardHeader>

                  <CardContent className="space-y-3">
              {/* Target Info */}
              <div className="flex items-center gap-2 text-sm text-gray-600">
                <Users className="h-4 w-4 text-gray-700" />
                <span>{getTargetInfo(schema)}</span>
              </div>

              {/* Working Hours */}
              <div className="flex items-center gap-2 text-sm text-gray-600">
                <Clock className="h-4 w-4 text-gray-700" />
                <span>
                  {formatTime(schedule.jamMasuk)} - {formatTime(schedule.jamPulang)}
                  {schedule.toleransi !== undefined && schedule.toleransi !== null && ` (+/-${schedule.toleransi}m)`}
                </span>
              </div>

              {/* Requirements */}
              <div className="flex items-center gap-4 text-sm text-gray-600">
                {schema.wajib_gps && (
                  <div className="flex items-center gap-1">
                    <MapPin className="h-4 w-4 text-green-600" />
                    <span>GPS</span>
                  </div>
                )}
                {schema.wajib_foto && (
                  <div className="flex items-center gap-1">
                    <Camera className="h-4 w-4 text-blue-600" />
                    <span>Foto</span>
                  </div>
                )}
              </div>

              {schema.wajib_gps && (
                <div className="flex items-start gap-2 text-sm text-gray-600">
                  <MapPin className="h-4 w-4 text-emerald-600 mt-0.5" />
                  <div className="min-w-0">
                    <div>{locationResolution.summary}</div>
                    {locationPreview && (
                      <div className="text-xs text-gray-500 line-clamp-2">{locationPreview}</div>
                    )}
                  </div>
                </div>
              )}

              {/* Priority */}
              {schema.priority > 0 && (
                <div className="text-sm text-gray-600">
                  Priority: {schema.priority}
                </div>
              )}

              {/* Description */}
              {schema.schema_description && (
                <p className="text-sm text-gray-600 line-clamp-2">
                  {schema.schema_description}
                </p>
              )}

              {/* Actions */}
              <div className="flex items-center justify-between pt-2 border-t">
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onView?.(schema)}
                    className="h-8 w-8 p-0 hover:bg-blue-50"
                  >
                    <Eye className="h-4 w-4 text-blue-600 hover:text-blue-700" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onEdit?.(schema)}
                    className="h-8 w-8 p-0 hover:bg-green-50"
                  >
                    <Edit className="h-4 w-4 text-green-600 hover:text-green-700" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onAssign?.(schema)}
                    className="h-8 w-8 p-0 hover:bg-purple-50"
                  >
                    <Users className="h-4 w-4 text-purple-600 hover:text-purple-700" />
                  </Button>
                </div>

                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleSetDefault(schema.id)}
                    disabled={actionLoading[schema.id] === 'default' || schema.is_default}
                    className="h-8 w-8 p-0 hover:bg-yellow-50"
                  >
                    {schema.is_default ? (
                      <Star className="h-4 w-4 text-yellow-500 fill-current" />
                    ) : (
                      <StarOff className="h-4 w-4 text-gray-600 hover:text-yellow-600" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleToggleActive(schema.id)}
                    disabled={actionLoading[schema.id] === 'toggle'}
                    className="h-8 w-8 p-0 hover:bg-gray-50"
                  >
                    {schema.is_active ? (
                      <ToggleRight className="h-4 w-4 text-green-600" />
                    ) : (
                      <ToggleLeft className="h-4 w-4 text-gray-500" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDelete(schema.id)}
                    disabled={actionLoading[schema.id] === 'delete' || schema.is_default}
                    className="h-8 w-8 p-0 hover:bg-red-50"
                  >
                    <Trash2 className="h-4 w-4 text-red-600 hover:text-red-700" />
                  </Button>
                </div>
              </div>
                  </CardContent>
                </>
              );
            })()}
          </Card>
        ))}
      </div>

      {schemas.length === 0 && (
        <div className="text-center py-12">
          <div className="text-gray-500 mb-4">
            <Smartphone className="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>Belum ada skema absensi yang dibuat</p>
          </div>
          <Button onClick={() => onEdit?.(null)} className="bg-blue-600 hover:bg-blue-700 text-white">
            Buat Skema Pertama
          </Button>
        </div>
      )}
    </div>
  );
};

export default AttendanceSchemaList;

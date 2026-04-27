import React from 'react';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Typography,
} from '@mui/material';
import { Filter, ShieldAlert, Users } from 'lucide-react';

const MetricTile = ({ label, value }) => (
  <Box className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5">
    <Typography variant="caption" className="block text-slate-500">
      {label}
    </Typography>
    <Typography variant="body1" className="font-semibold text-slate-900">
      {value}
    </Typography>
  </Box>
);

const TrackingHomeroomSummary = ({
  rows = [],
  selectedHomeroomTeacherId = '',
  onHomeroomTeacherSelect,
  onClearHomeroomTeacherFilter,
}) => {
  if (!rows.length) {
    return null;
  }

  return (
    <Card className="rounded-3xl border border-slate-200 shadow-sm">
      <CardContent className="space-y-4 p-5">
        <Box className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <Box>
            <Typography variant="h6" className="font-semibold text-slate-900">
              Ringkasan Per Wali Kelas
            </Typography>
            <Typography variant="body2" className="text-slate-600">
              Lihat exception berdasarkan penanggung jawab kelas supaya tindak lanjut lebih jelas.
            </Typography>
          </Box>
          <Chip
            size="small"
            variant="outlined"
            icon={<Users className="h-3.5 w-3.5" />}
            label={`${rows.length} wali kelas`}
          />
        </Box>

        {selectedHomeroomTeacherId ? (
          <Alert severity="info" action={<Button color="inherit" size="small" onClick={() => onClearHomeroomTeacherFilter?.()}>Reset</Button>}>
            Filter wali kelas aktif.
          </Alert>
        ) : null}

        <Box className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          {rows.map((row) => {
            const isSelected = String(selectedHomeroomTeacherId) === String(row.wali_kelas_id ?? '');

            return (
              <Card
                key={row.wali_kelas_id ?? `homeroom-${row.wali_kelas_name}`}
                className={`rounded-3xl border shadow-sm ${
                  isSelected ? 'border-blue-300 bg-blue-50/70' : 'border-slate-200 bg-white'
                }`}
              >
                <CardContent className="space-y-4 p-5">
                  <Box className="flex items-start justify-between gap-3">
                    <Box>
                      <Typography variant="h6" className="font-semibold text-slate-900">
                        {row.wali_kelas_name}
                      </Typography>
                      <Typography variant="body2" className="text-slate-600">
                        {row.class_count} kelas, {row.total} siswa
                      </Typography>
                    </Box>
                    <Chip
                      size="small"
                      color={Number(row.exception_rate || 0) > 0 ? 'warning' : 'success'}
                      variant="outlined"
                      icon={<ShieldAlert className="h-3.5 w-3.5" />}
                      label={`${row.exception_rate}%`}
                    />
                  </Box>

                  <Box className="grid grid-cols-2 gap-3">
                    <MetricTile label="Fresh" value={row.active} />
                    <MetricTile label="Luar area" value={row.outside_area} />
                    <MetricTile label="Stale" value={row.stale} />
                    <MetricTile label="GPS mati" value={row.gps_disabled} />
                  </Box>

                  <Box className="flex flex-wrap gap-2">
                    {Number(row.tracking_disabled || 0) > 0 ? (
                      <Chip size="small" variant="outlined" label={`Tracking nonaktif ${row.tracking_disabled}`} />
                    ) : null}
                    {Number(row.outside_schedule || 0) > 0 ? (
                      <Chip size="small" color="info" variant="outlined" label={`Di luar jadwal ${row.outside_schedule}`} />
                    ) : null}
                    <Chip size="small" variant="outlined" label={`No data ${row.no_data}`} />
                    <Chip size="small" variant="outlined" label={`Tingkat ${row.level_count}`} />
                  </Box>

                  <Button
                    fullWidth
                    variant={isSelected ? 'contained' : 'outlined'}
                    startIcon={<Filter className="h-3.5 w-3.5" />}
                    onClick={() => onHomeroomTeacherSelect?.(row.wali_kelas_id)}
                    disabled={!row.wali_kelas_id}
                  >
                    {isSelected ? 'Wali kelas aktif' : 'Fokus wali kelas'}
                  </Button>
                </CardContent>
              </Card>
            );
          })}
        </Box>
      </CardContent>
    </Card>
  );
};

export default TrackingHomeroomSummary;

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
import { Filter, Layers, TrendingUp } from 'lucide-react';

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

const TrackingLevelSummary = ({
  rows = [],
  selectedLevel = '',
  onLevelSelect,
  onClearLevelFilter,
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
              Ringkasan Per Tingkat
            </Typography>
            <Typography variant="body2" className="text-slate-600">
              Pakai tingkat sebagai pintu masuk paling cepat sebelum fokus ke wali kelas atau kelas.
            </Typography>
          </Box>
          <Chip size="small" icon={<Layers className="h-3.5 w-3.5" />} variant="outlined" label={`${rows.length} tingkat`} />
        </Box>

        {selectedLevel ? (
          <Alert severity="info" action={<Button color="inherit" size="small" onClick={() => onClearLevelFilter?.()}>Reset</Button>}>
            Filter tingkat aktif: <strong>{selectedLevel}</strong>
          </Alert>
        ) : null}

        <Box className="grid grid-cols-1 gap-4 xl:grid-cols-3">
          {rows.map((row) => {
            const isSelected = selectedLevel === row.level_name;

            return (
              <Card
                key={row.level_name}
                className={`rounded-3xl border shadow-sm ${
                  isSelected ? 'border-blue-300 bg-blue-50/70' : 'border-slate-200 bg-white'
                }`}
              >
                <CardContent className="space-y-4 p-5">
                  <Box className="flex items-start justify-between gap-3">
                    <Box>
                      <Typography variant="h6" className="font-semibold text-slate-900">
                        {row.level_name}
                      </Typography>
                      <Typography variant="body2" className="text-slate-600">
                        {row.total} siswa, {row.tracked} terekam
                      </Typography>
                    </Box>
                    <Chip
                      size="small"
                      color={Number(row.exception_rate || 0) > 0 ? 'warning' : 'success'}
                      variant="outlined"
                      icon={<TrendingUp className="h-3.5 w-3.5" />}
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
                  </Box>

                  <Button
                    fullWidth
                    variant={isSelected ? 'contained' : 'outlined'}
                    startIcon={<Filter className="h-3.5 w-3.5" />}
                    onClick={() => onLevelSelect?.(row.level_name)}
                  >
                    {isSelected ? 'Tingkat aktif' : 'Fokus tingkat'}
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

export default TrackingLevelSummary;

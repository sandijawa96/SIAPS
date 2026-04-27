import React, { useMemo } from 'react';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { Filter, Layers, ShieldAlert } from 'lucide-react';

const TrackingClassSummary = ({
  rows = [],
  selectedClass = '',
  onClassSelect,
  onClearClassFilter,
}) => {
  const totals = useMemo(() => {
    const totalClasses = rows.length;
    const classesWithExceptions = rows.filter((row) => Number(row.exception_count || 0) > 0).length;
    const worstClass = rows[0] || null;

    return {
      totalClasses,
      classesWithExceptions,
      worstClass,
    };
  }, [rows]);

  if (!rows.length) {
    return (
      <Card className="rounded-3xl border border-slate-200 shadow-sm">
        <CardContent className="space-y-2 p-5">
          <Typography variant="h6" className="font-semibold text-slate-900">
            Ringkasan Per Kelas
          </Typography>
          <Typography variant="body2" className="text-slate-600">
            Belum ada data kelas pada scope filter ini.
          </Typography>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="rounded-3xl border border-slate-200 shadow-sm">
      <CardContent className="space-y-4 p-5">
        <Box className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
          <Box>
            <Typography variant="h6" className="font-semibold text-slate-900">
              Ringkasan Per Kelas
            </Typography>
            <Typography variant="body2" className="text-slate-600">
              Gunakan tabel ini untuk turun dari level wali atau tingkat ke kelas yang paling bermasalah.
            </Typography>
          </Box>

          <Box className="flex flex-wrap gap-2">
            <Chip size="small" icon={<Layers className="h-3.5 w-3.5" />} label={`${totals.totalClasses} kelas`} />
            <Chip size="small" color="warning" variant="outlined" icon={<ShieldAlert className="h-3.5 w-3.5" />} label={`${totals.classesWithExceptions} punya exception`} />
            {totals.worstClass ? (
              <Chip size="small" color="error" variant="outlined" label={`Tertinggi ${totals.worstClass.class_name} (${totals.worstClass.exception_rate}%)`} />
            ) : null}
          </Box>
        </Box>

        {selectedClass ? (
          <Alert
            severity="info"
            action={<Button color="inherit" size="small" onClick={() => onClearClassFilter?.()}>Reset</Button>}
          >
            Filter kelas aktif: <strong>{selectedClass}</strong>
          </Alert>
        ) : null}

        <TableContainer className="rounded-2xl border border-slate-200">
          <Table size="small" stickyHeader>
            <TableHead>
              <TableRow>
                <TableCell>Kelas</TableCell>
                <TableCell align="right">Total</TableCell>
                <TableCell align="right">Terekam</TableCell>
                <TableCell>Exception</TableCell>
                <TableCell align="right">No data</TableCell>
                <TableCell align="right">Rate</TableCell>
                <TableCell align="right">Aksi</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((row) => {
                const isSelected = selectedClass === row.class_name;
                const hasException = Number(row.exception_count || 0) > 0;

                return (
                  <TableRow key={row.class_name} hover selected={isSelected}>
                    <TableCell>
                      <Typography variant="body2" className="font-semibold text-slate-900">
                        {row.class_name}
                      </Typography>
                    </TableCell>
                    <TableCell align="right">{row.total}</TableCell>
                    <TableCell align="right">
                      <Typography variant="body2" className="font-medium text-slate-900">
                        {row.tracked}
                      </Typography>
                      <Typography variant="caption" className="text-slate-500">
                        {row.tracked_rate}%
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Box className="flex flex-wrap gap-1">
                        <Chip size="small" variant="outlined" label={`Luar ${row.outside_area}`} />
                        <Chip size="small" variant="outlined" label={`Stale ${row.stale}`} />
                        <Chip size="small" variant="outlined" label={`GPS ${row.gps_disabled}`} />
                        {Number(row.tracking_disabled || 0) > 0 ? (
                          <Chip size="small" variant="outlined" label={`Nonaktif ${row.tracking_disabled}`} />
                        ) : null}
                        {Number(row.outside_schedule || 0) > 0 ? (
                          <Chip size="small" color="info" variant="outlined" label={`Jeda ${row.outside_schedule}`} />
                        ) : null}
                      </Box>
                    </TableCell>
                    <TableCell align="right">{row.no_data}</TableCell>
                    <TableCell align="right">
                      <Chip size="small" color={hasException ? 'warning' : 'success'} variant="outlined" label={`${row.exception_rate}%`} />
                    </TableCell>
                    <TableCell align="right">
                      <Button
                        size="small"
                        variant={isSelected ? 'contained' : 'outlined'}
                        startIcon={<Filter className="h-3.5 w-3.5" />}
                        onClick={() => onClassSelect?.(row.class_name)}
                      >
                        {isSelected ? 'Aktif' : 'Fokus'}
                      </Button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </TableContainer>
      </CardContent>
    </Card>
  );
};

export default TrackingClassSummary;

import React from 'react';
import { Dialog, DialogContent, DialogTitle, TextField, MenuItem, Checkbox, FormControlLabel } from '@mui/material';

const BulkAssignWaliModalNew = ({
  isOpen,
  onClose,
  onSubmit,
  kelasList,
  selectedClasses,
  setSelectedClasses,
  bulkWaliAssignments,
  setBulkWaliAssignments,
  getAvailablePegawai,
  tingkatList,
  loading
}) => {
  const handleClassSelection = (kelasId) => {
    if (selectedClasses.includes(kelasId)) {
      setSelectedClasses(selectedClasses.filter(id => id !== kelasId));
      const newAssignments = { ...bulkWaliAssignments };
      delete newAssignments[kelasId];
      setBulkWaliAssignments(newAssignments);
    } else {
      setSelectedClasses([...selectedClasses, kelasId]);
    }
  };

  const handleWaliAssignment = (kelasId, waliId) => {
    setBulkWaliAssignments({
      ...bulkWaliAssignments,
      [kelasId]: waliId
    });
  };

  const groupedKelas = tingkatList.reduce((acc, tingkat) => {
    acc[tingkat.id] = kelasList.filter(kelas => kelas.tingkat_id === tingkat.id);
    return acc;
  }, {});

  return (
    <Dialog open={isOpen} onClose={onClose} maxWidth="lg" fullWidth>
      <DialogTitle>
        Tugaskan Wali Kelas Secara Massal
      </DialogTitle>
      <DialogContent>
        <div className="py-4">
          <p className="text-sm text-gray-600 mb-4">
            Pilih kelas yang ingin ditugaskan wali kelas, kemudian pilih wali kelas untuk masing-masing kelas.
          </p>

          <div className="space-y-6">
            {tingkatList.map((tingkat) => {
              const kelasInTingkat = groupedKelas[tingkat.id] || [];
              if (kelasInTingkat.length === 0) return null;

              return (
                <div key={tingkat.id} className="border rounded-lg p-4">
                  <h3 className="font-semibold text-lg mb-3">{tingkat.nama}</h3>
                  <div className="space-y-3">
                    {kelasInTingkat.map((kelas) => {
                      const isSelected = selectedClasses.includes(kelas.id);
                      const availablePegawai = getAvailablePegawai(kelas.wali_kelas_id);

                      return (
                        <div key={kelas.id} className="flex items-center space-x-4 p-3 border rounded">
                          <FormControlLabel
                            control={
                              <Checkbox
                                checked={isSelected}
                                onChange={() => handleClassSelection(kelas.id)}
                              />
                            }
                            label={kelas.namaKelas}
                          />

                          {isSelected && (
                            <div className="flex-1">
                              <TextField
                                select
                                size="small"
                                label="Pilih Wali Kelas"
                                value={bulkWaliAssignments[kelas.id] || ''}
                                onChange={(e) => handleWaliAssignment(kelas.id, e.target.value)}
                                className="min-w-[200px]"
                              >
                                <MenuItem value="">Pilih Wali Kelas</MenuItem>
                                {availablePegawai.map((pegawai) => (
                                  <MenuItem key={pegawai.id} value={pegawai.id}>
                                    {pegawai.nama}
                                  </MenuItem>
                                ))}
                              </TextField>
                            </div>
                          )}

                          <div className="text-sm text-gray-500">
                            Wali saat ini: {kelas.waliKelas || 'Belum ada'}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })}
          </div>

          <div className="flex justify-end space-x-2 pt-6 border-t mt-6">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
            >
              Batal
            </button>
            <button
              onClick={onSubmit}
              disabled={selectedClasses.length === 0 || loading}
              className="btn-primary"
            >
              {loading ? 'Menyimpan...' : `Tugaskan ${selectedClasses.length} Wali Kelas`}
            </button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default BulkAssignWaliModalNew;

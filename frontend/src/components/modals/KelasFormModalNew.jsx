import React from 'react';
import { Dialog, DialogContent, DialogTitle, TextField, MenuItem } from '@mui/material';

const KelasFormModalNew = ({
  isOpen,
  onClose,
  onSubmit,
  selectedItem,
  tingkatList,
  pegawaiList,
  activeTahunAjaran,
  getAvailablePegawai
}) => {
  const availablePegawai = getAvailablePegawai(selectedItem?.wali_kelas_id);

  return (
    <Dialog open={isOpen} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>
        {selectedItem ? 'Edit Kelas' : 'Tambah Kelas Baru'}
      </DialogTitle>
      <DialogContent>
        <form onSubmit={onSubmit} className="space-y-4 py-4">
          <TextField
            fullWidth
            label="Nama Kelas"
            name="namaKelas"
            defaultValue={selectedItem?.namaKelas || ''}
            required
          />

          <TextField
            fullWidth
            select
            label="Tingkat"
            name="tingkat"
            defaultValue={selectedItem?.tingkat_id || ''}
            required
          >
            {tingkatList.map((tingkat) => (
              <MenuItem key={tingkat.id} value={tingkat.id}>
                {tingkat.nama}
              </MenuItem>
            ))}
          </TextField>

          <TextField
            fullWidth
            type="number"
            label="Kapasitas"
            name="kapasitas"
            defaultValue={selectedItem?.kapasitas || ''}
            required
          />

          <TextField
            fullWidth
            select
            label="Wali Kelas"
            name="wali_kelas_id"
            defaultValue={selectedItem?.wali_kelas_id || ''}
          >
            <MenuItem value="">Pilih Wali Kelas</MenuItem>
            {availablePegawai.map((pegawai) => (
              <MenuItem key={pegawai.id} value={pegawai.id}>
                {pegawai.nama}
              </MenuItem>
            ))}
          </TextField>

          <TextField
            fullWidth
            multiline
            rows={3}
            label="Keterangan"
            name="keterangan"
            defaultValue={selectedItem?.keterangan || ''}
          />

          <div className="flex justify-end space-x-2 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
            >
              Batal
            </button>
            <button
              type="submit"
              className="btn-primary"
            >
              {selectedItem ? 'Simpan Perubahan' : 'Tambah Kelas'}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default KelasFormModalNew;

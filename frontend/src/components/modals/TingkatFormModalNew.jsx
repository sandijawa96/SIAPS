import React from 'react';
import { Dialog, DialogContent, DialogTitle, TextField } from '@mui/material';

const TingkatFormModalNew = ({
  isOpen,
  onClose,
  onSubmit,
  selectedItem
}) => {
  return (
    <Dialog open={isOpen} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>
        {selectedItem ? 'Edit Tingkat' : 'Tambah Tingkat Baru'}
      </DialogTitle>
      <DialogContent>
        <form onSubmit={onSubmit} className="space-y-4 py-4">
          <TextField
            fullWidth
            label="Nama Tingkat"
            name="nama"
            defaultValue={selectedItem?.nama || ''}
            required
          />

          <TextField
            fullWidth
            label="Kode Tingkat"
            name="kode"
            defaultValue={selectedItem?.kode || ''}
            required
          />

          <TextField
            fullWidth
            multiline
            rows={3}
            label="Deskripsi"
            name="deskripsi"
            defaultValue={selectedItem?.deskripsi || ''}
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
              {selectedItem ? 'Simpan Perubahan' : 'Tambah Tingkat'}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default TingkatFormModalNew;

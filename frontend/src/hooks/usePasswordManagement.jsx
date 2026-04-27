import { useState } from 'react';
import pegawaiService from '../services/pegawaiService';
import siswaService from '../services/siswaService';
import toast from 'react-hot-toast';
import { getServerDateParts } from '../services/serverClock';

export const usePasswordManagement = () => {
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const handleResetPassword = async (userId, userType, dataPribadi = null) => {
    try {
      if (userType === 'pegawai') {
        // Validasi untuk pegawai
        if (newPassword !== confirmPassword) {
          toast.error('Password dan konfirmasi password tidak sama');
          return false;
        }

        if (newPassword.length < 8) {
          toast.error('Password minimal 8 karakter');
          return false;
        }

        await pegawaiService.resetPassword(userId, { password: newPassword });
        toast.success('Password pegawai berhasil direset');
      } else {
        // Reset password siswa ke tanggal lahir
        if (!dataPribadi?.tanggal_lahir) {
          toast.error('Tanggal lahir siswa tidak ditemukan. Tidak dapat mereset password.');
          return false;
        }

        // Format tanggal lahir ke DDMMYYYY
        const dateParts = getServerDateParts(dataPribadi.tanggal_lahir);
        if (!dateParts) {
          toast.error('Format tanggal lahir siswa tidak valid');
          return false;
        }

        const day = String(dateParts.day).padStart(2, '0');
        const month = String(dateParts.month).padStart(2, '0');
        const year = dateParts.year;
        const birthdatePassword = `${day}${month}${year}`;

        await siswaService.resetPassword(userId, { 
          password: birthdatePassword,
          reset_to_birthdate: true 
        });
        
        toast.success(`Password siswa berhasil direset ke tanggal lahir (${birthdatePassword})`);
      }
      
      resetForm();
      return true;
    } catch (error) {
      toast.error(error.response?.data?.message || 'Gagal reset password');
      console.error('Error resetting password:', error);
      return false;
    }
  };

  const resetForm = () => {
    setNewPassword('');
    setConfirmPassword('');
  };

  return {
    newPassword,
    setNewPassword,
    confirmPassword,
    setConfirmPassword,
    handleResetPassword,
    resetForm
  };
};

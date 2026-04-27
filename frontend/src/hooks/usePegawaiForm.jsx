import { useState } from 'react';
import toast from 'react-hot-toast';

export const usePegawaiForm = () => {
  const [formData, setFormData] = useState({
    username: '',
    nama_lengkap: '',
    jenis_kelamin: '',
    tanggal_lahir: '',
    alamat: '',
    no_telepon: '',
    role: '',
    sub_role: '',
    status_kepegawaian: '',
    nip: '',
    nuptk: '',
    email: '',
    password: '',
    konfirmasi_password: '',
    foto_profil: null,
    is_active: true
  });

  const [previewImage, setPreviewImage] = useState(null);

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Validasi ukuran file (maksimal 2MB)
      if (file.size > 2 * 1024 * 1024) {
        toast.error('Foto terlalu besar. Maksimal 2MB');
        return;
      }

      // Validasi tipe file
      const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
      if (!allowedTypes.includes(file.type)) {
        toast.error('Format foto tidak sesuai. Gunakan JPG atau PNG');
        return;
      }

      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviewImage(reader.result);
        setFormData(prev => ({ ...prev, foto_profil: file }));
      };
      reader.readAsDataURL(file);
    }
  };

  const resetForm = () => {
    setFormData({
      username: '',
      nama_lengkap: '',
      jenis_kelamin: '',
      tanggal_lahir: '',
      alamat: '',
      no_telepon: '',
      role: '',
      sub_role: '',
      status_kepegawaian: '',
      nip: '',
      nuptk: '',
      email: '',
      password: '',
      konfirmasi_password: '',
      foto_profil: null,
      is_active: true
    });
    setPreviewImage(null);
  };

  return {
    formData,
    setFormData,
    previewImage,
    setPreviewImage,
    handleInputChange,
    handleImageChange,
    resetForm
  };
};

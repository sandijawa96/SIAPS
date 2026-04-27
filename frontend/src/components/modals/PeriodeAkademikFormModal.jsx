import React from 'react';
import { useForm } from 'react-hook-form';
import Modal from './Modal';

const PeriodeAkademikFormModal = ({ isOpen, onClose, onSubmit, initialData = null }) => {
    const { register, handleSubmit, formState: { errors }, reset } = useForm({
        defaultValues: initialData || {
            nama: '',
            jenis: 'pembelajaran',
            tanggal_mulai: '',
            tanggal_selesai: '',
            semester: 'ganjil',
            is_active: true,
            keterangan: ''
        }
    });

    React.useEffect(() => {
        if (initialData) {
            reset(initialData);
        }
    }, [initialData, reset]);

    const handleFormSubmit = (data) => {
        onSubmit(data);
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={initialData ? 'Edit Periode Akademik' : 'Tambah Periode Akademik'}
        >
            <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nama Periode
                    </label>
                    <input
                        type="text"
                        {...register('nama', { required: 'Nama periode wajib diisi' })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Contoh: Pembelajaran Semester Ganjil"
                    />
                    {errors.nama && (
                        <p className="mt-1 text-sm text-red-600">{errors.nama.message}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Jenis Periode
                    </label>
                    <select
                        {...register('jenis', { required: 'Jenis periode wajib dipilih' })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="pembelajaran">Pembelajaran</option>
                        <option value="ujian">Ujian</option>
                        <option value="libur">Libur</option>
                        <option value="orientasi">Orientasi</option>
                    </select>
                    {errors.jenis && (
                        <p className="mt-1 text-sm text-red-600">{errors.jenis.message}</p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Mulai
                        </label>
                        <input
                            type="date"
                            {...register('tanggal_mulai', { required: 'Tanggal mulai wajib diisi' })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        {errors.tanggal_mulai && (
                            <p className="mt-1 text-sm text-red-600">{errors.tanggal_mulai.message}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Selesai
                        </label>
                        <input
                            type="date"
                            {...register('tanggal_selesai', { required: 'Tanggal selesai wajib diisi' })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        {errors.tanggal_selesai && (
                            <p className="mt-1 text-sm text-red-600">{errors.tanggal_selesai.message}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Semester
                    </label>
                    <select
                        {...register('semester', { required: 'Semester wajib dipilih' })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="ganjil">Ganjil</option>
                        <option value="genap">Genap</option>
                        <option value="both">Ganjil & Genap</option>
                    </select>
                    {errors.semester && (
                        <p className="mt-1 text-sm text-red-600">{errors.semester.message}</p>
                    )}
                </div>

                <div>
                    <label className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            {...register('is_active')}
                            className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">Aktif</span>
                    </label>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Keterangan
                    </label>
                    <textarea
                        {...register('keterangan')}
                        rows={3}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Keterangan tambahan (opsional)"
                    />
                </div>

                <div className="flex justify-end space-x-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                    >
                        {initialData ? 'Simpan' : 'Tambah'}
                    </button>
                </div>
            </form>
        </Modal>
    );
};

export default PeriodeAkademikFormModal;

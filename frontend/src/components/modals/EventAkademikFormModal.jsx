import React from 'react';
import { useForm } from 'react-hook-form';
import Modal from './Modal';

const EventAkademikFormModal = ({ 
    isOpen, 
    onClose, 
    onSubmit, 
    initialData = null,
    periodeList = [],
    tingkatList = [],
    kelasList = []
}) => {
    const { register, handleSubmit, formState: { errors }, reset, watch } = useForm({
        defaultValues: initialData || {
            nama: '',
            jenis: 'kegiatan',
            tanggal_mulai: '',
            tanggal_selesai: '',
            waktu_mulai: '',
            waktu_selesai: '',
            periode_akademik_id: '',
            tingkat_id: '',
            kelas_id: '',
            is_wajib: false,
            is_active: true,
            deskripsi: '',
            lokasi: ''
        }
    });

    const selectedTingkat = watch('tingkat_id');

    React.useEffect(() => {
        if (initialData) {
            reset(initialData);
        }
    }, [initialData, reset]);

    const handleFormSubmit = (data) => {
        // Clean up empty values
        const cleanData = { ...data };
        if (!cleanData.tanggal_selesai) delete cleanData.tanggal_selesai;
        if (!cleanData.waktu_mulai) delete cleanData.waktu_mulai;
        if (!cleanData.waktu_selesai) delete cleanData.waktu_selesai;
        if (!cleanData.periode_akademik_id) delete cleanData.periode_akademik_id;
        if (!cleanData.tingkat_id) delete cleanData.tingkat_id;
        if (!cleanData.kelas_id) delete cleanData.kelas_id;
        
        onSubmit(cleanData);
    };

    // Filter kelas based on selected tingkat
    const filteredKelas = selectedTingkat 
        ? kelasList.filter(kelas => kelas.tingkat_id === parseInt(selectedTingkat))
        : kelasList;

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={initialData ? 'Edit Event Akademik' : 'Tambah Event Akademik'}
            size="lg"
        >
            <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nama Event
                    </label>
                    <input
                        type="text"
                        {...register('nama', { required: 'Nama event wajib diisi' })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Contoh: Ujian Tengah Semester"
                    />
                    {errors.nama && (
                        <p className="mt-1 text-sm text-red-600">{errors.nama.message}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Jenis Event
                    </label>
                    <select
                        {...register('jenis', { required: 'Jenis event wajib dipilih' })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="kegiatan">Kegiatan</option>
                        <option value="ujian">Ujian</option>
                        <option value="libur">Libur</option>
                        <option value="deadline">Deadline</option>
                        <option value="rapat">Rapat</option>
                        <option value="pelatihan">Pelatihan</option>
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
                            {...register('tanggal_selesai')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p className="mt-1 text-xs text-gray-500">Kosongkan jika event hanya satu hari</p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Waktu Mulai
                        </label>
                        <input
                            type="time"
                            {...register('waktu_mulai')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Waktu Selesai
                        </label>
                        <input
                            type="time"
                            {...register('waktu_selesai')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Periode Akademik
                    </label>
                    <select
                        {...register('periode_akademik_id')}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">Pilih Periode (Opsional)</option>
                        {periodeList.map(periode => (
                            <option key={periode.id} value={periode.id}>
                                {periode.nama} ({periode.jenis_display})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Tingkat
                        </label>
                        <select
                            {...register('tingkat_id')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Semua Tingkat</option>
                            {tingkatList.map(tingkat => (
                                <option key={tingkat.id} value={tingkat.id}>
                                    {tingkat.nama}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Kelas
                        </label>
                        <select
                            {...register('kelas_id')}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            disabled={!selectedTingkat}
                        >
                            <option value="">
                                {selectedTingkat ? 'Semua Kelas di Tingkat' : 'Pilih Tingkat Dulu'}
                            </option>
                            {filteredKelas.map(kelas => (
                                <option key={kelas.id} value={kelas.id}>
                                    {kelas.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Lokasi
                    </label>
                    <input
                        type="text"
                        {...register('lokasi')}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Contoh: Aula Sekolah, Ruang Kelas, Online"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Deskripsi
                    </label>
                    <textarea
                        {...register('deskripsi')}
                        rows={3}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Deskripsi detail tentang event ini"
                    />
                </div>

                <div className="flex items-center space-x-6">
                    <label className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            {...register('is_wajib')}
                            className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">Event Wajib</span>
                    </label>

                    <label className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            {...register('is_active')}
                            className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">Aktif</span>
                    </label>
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

export default EventAkademikFormModal;

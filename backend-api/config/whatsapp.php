<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway Defaults
    |--------------------------------------------------------------------------
    |
    | Nilai ini menjadi fallback jika pengaturan runtime belum disimpan lewat
    | endpoint /api/whatsapp/settings.
    |
    */
    'api_url' => env('WHATSAPP_API_URL', ''),
    'api_key' => env('WHATSAPP_API_KEY', ''),
    'sender' => env('WHATSAPP_PHONE_NUMBER', ''),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Runtime
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 20),
    'retry_times' => (int) env('WHATSAPP_RETRY_TIMES', 2),
    'retry_sleep_ms' => (int) env('WHATSAPP_RETRY_SLEEP_MS', 300),

    /*
    |--------------------------------------------------------------------------
    | Failed Notification Auto Retry
    |--------------------------------------------------------------------------
    */
    'auto_retry' => [
        'enabled' => (bool) env('WHATSAPP_AUTO_RETRY_ENABLED', true),
        'batch_size' => (int) env('WHATSAPP_AUTO_RETRY_BATCH_SIZE', 100),
        'cooldown_seconds' => (int) env('WHATSAPP_AUTO_RETRY_COOLDOWN_SECONDS', 300),
    ],

    'webhook_events' => [
        'retention_days' => (int) env('WHATSAPP_WEBHOOK_RETENTION_DAYS', 30),
        'max_items' => (int) env('WHATSAPP_WEBHOOK_MAX_ITEMS', 50),
        'max_string_length' => (int) env('WHATSAPP_WEBHOOK_MAX_STRING_LENGTH', 500),
    ],

    'skip_logs' => [
        'retention_days' => (int) env('WHATSAPP_SKIP_LOG_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Templates
    |--------------------------------------------------------------------------
    |
    | Default template di bawah menjadi fallback bila belum ada override runtime
    | dari halaman WhatsApp Gateway. Isi template dapat memakai placeholder
    | sederhana seperti {student_name}, {class_name}, {alpha_days}, dst.
    |
    */
    'automations' => [
        'attendance_checkin' => [
            'label' => 'Absensi Masuk',
            'type' => 'absensi',
            'audience' => 'Orang tua siswa',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Konfirmasi Absensi Masuk\n\nNama: *{student_name}*\nKelas: *{class_name}*\nTanggal: *{date_label}*\nDetail: Masuk pukul *{time_label}*, status *{status_label}*{manual_label}.\nRef: *{reference}*\n\nJika ada ketidaksesuaian, hubungi wali kelas/kesiswaan.",
            'placeholders' => ['student_name', 'class_name', 'date_label', 'time_label', 'status_label', 'manual_label', 'reference'],
        ],
        'attendance_checkout' => [
            'label' => 'Absensi Pulang',
            'type' => 'absensi',
            'audience' => 'Orang tua siswa',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Konfirmasi Absensi Pulang\n\nNama: *{student_name}*\nKelas: *{class_name}*\nTanggal: *{date_label}*\nDetail: Masuk *{check_in_label}*, pulang *{time_label}*{manual_label}, durasi *{duration_label}*.\nRef: *{reference}*\n\nJika ada ketidaksesuaian, hubungi wali kelas/kesiswaan.",
            'placeholders' => ['student_name', 'class_name', 'date_label', 'check_in_label', 'time_label', 'manual_label', 'duration_label', 'reference'],
        ],
        'izin_submitted' => [
            'label' => 'Pengajuan Izin Diterima',
            'type' => 'izin',
            'audience' => 'Orang tua siswa',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Pengajuan Izin Diterima\n\nNama: *{student_name}*\nKelas: *{class_name}*\nJenis: *{jenis_label}*\nPeriode: *{date_range}*\nStatus: *Menunggu review*\nRef: *{reference}*\n\nPantau status terbaru di aplikasi.",
            'placeholders' => ['student_name', 'class_name', 'jenis_label', 'date_range', 'reference'],
        ],
        'izin_decision' => [
            'label' => 'Hasil Pengajuan Izin',
            'type' => 'izin',
            'audience' => 'Orang tua siswa',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Hasil Pengajuan Izin\n\nNama: *{student_name}*\nKelas: *{class_name}*\nJenis: *{jenis_label}*\nPeriode: *{date_range}*\nKeputusan: *{decision_label}*\n{approval_note_block}Ref: *{reference}*\n\nTerima kasih.",
            'placeholders' => ['student_name', 'class_name', 'jenis_label', 'date_range', 'decision_label', 'approval_note_block', 'reference'],
        ],
        'discipline_alpha_semester_limit_wali_kelas' => [
            'label' => 'Alpha Semester ke Wali Kelas',
            'type' => 'reminder',
            'audience' => 'Wali kelas',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Batas Alpha Semester\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nAlpha semester: *{alpha_days} hari*\nBatas: *{alpha_limit} hari*\nPeriode: *{semester_label} {tahun_ajaran_name}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'alpha_days', 'alpha_limit', 'semester_label', 'tahun_ajaran_name', 'reference'],
        ],
        'discipline_alpha_semester_limit_kesiswaan' => [
            'label' => 'Alpha Semester ke Kesiswaan',
            'type' => 'reminder',
            'audience' => 'Wakasek kesiswaan',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Batas Alpha Semester\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nAlpha semester: *{alpha_days} hari*\nBatas: *{alpha_limit} hari*\nPeriode: *{semester_label} {tahun_ajaran_name}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'alpha_days', 'alpha_limit', 'semester_label', 'tahun_ajaran_name', 'reference'],
        ],
        'discipline_monthly_late_limit_wali_kelas' => [
            'label' => 'Terlambat Bulanan ke Wali Kelas',
            'type' => 'reminder',
            'audience' => 'Wali kelas',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Keterlambatan Bulanan\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nIndikator: *{metric_label}*\nNilai saat ini: *{metric_value} {metric_unit}*\nBatas: *{metric_limit} {metric_unit}*\nPeriode: *{period_label}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'metric_label', 'metric_value', 'metric_limit', 'metric_unit', 'period_label', 'reference'],
        ],
        'discipline_monthly_late_limit_kesiswaan' => [
            'label' => 'Terlambat Bulanan ke Kesiswaan',
            'type' => 'reminder',
            'audience' => 'Wakasek kesiswaan',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Keterlambatan Bulanan\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nIndikator: *{metric_label}*\nNilai saat ini: *{metric_value} {metric_unit}*\nBatas: *{metric_limit} {metric_unit}*\nPeriode: *{period_label}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'metric_label', 'metric_value', 'metric_limit', 'metric_unit', 'period_label', 'reference'],
        ],
        'discipline_total_violation_semester_limit_wali_kelas' => [
            'label' => 'Total Pelanggaran Semester ke Wali Kelas',
            'type' => 'reminder',
            'audience' => 'Wali kelas',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Total Pelanggaran Semester\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nIndikator: *{metric_label}*\nNilai saat ini: *{metric_value} {metric_unit}*\nBatas: *{metric_limit} {metric_unit}*\nPeriode: *{period_label}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'metric_label', 'metric_value', 'metric_limit', 'metric_unit', 'period_label', 'reference'],
        ],
        'discipline_total_violation_semester_limit_kesiswaan' => [
            'label' => 'Total Pelanggaran Semester ke Kesiswaan',
            'type' => 'reminder',
            'audience' => 'Wakasek kesiswaan',
            'enabled' => true,
            'footer' => 'Pesan otomatis SIAPS',
            'template' => "Peringatan Total Pelanggaran Semester\n\nPenerima: *{recipient_name}*\nSiswa: *{student_name}*\nKelas: *{class_name}*\nIndikator: *{metric_label}*\nNilai saat ini: *{metric_value} {metric_unit}*\nBatas: *{metric_limit} {metric_unit}*\nPeriode: *{period_label}*\nRef: *{reference}*\n\nMohon tindak lanjuti sesuai kebijakan sekolah.",
            'placeholders' => ['recipient_name', 'student_name', 'class_name', 'metric_label', 'metric_value', 'metric_limit', 'metric_unit', 'period_label', 'reference'],
        ],
    ],
];

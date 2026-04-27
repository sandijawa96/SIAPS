<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attendance Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle fitur absensi tertentu tanpa menghapus implementasi model/controller.
    | Set ATTENDANCE_QR_ENABLED=true jika ingin mengaktifkan kembali QR attendance.
    |
    */
    'features' => [
        'qr_code_enabled' => env('ATTENDANCE_QR_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Alpha Settings
    |--------------------------------------------------------------------------
    |
    | Menandai alpha otomatis untuk siswa yang sudah memiliki sinyal login
    | mobile app (device terikat) namun tidak melakukan absensi pada hari kerja.
    |
    */
    'auto_alpha' => [
        'enabled' => filter_var(env('ATTENDANCE_AUTO_ALPHA_ENABLED', true), FILTER_VALIDATE_BOOL),
        'run_time' => env('ATTENDANCE_AUTO_ALPHA_RUN_TIME', '23:50'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discipline Threshold Alert Settings
    |--------------------------------------------------------------------------
    |
    | Menjalankan evaluasi threshold disiplin otomatis untuk indikator yang
    | diatur alertable pada schema absensi, mencakup:
    | - keterlambatan bulanan
    | - total pelanggaran semester
    | - alpha semester
    | Notifikasi internal selalu mengikuti toggle per indikator pada schema,
    | sedangkan kanal WhatsApp masih bisa dikontrol terpisah dari gateway.
    |
    */
    'discipline_alerts' => [
        'enabled' => filter_var(env('ATTENDANCE_DISCIPLINE_ALERTS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'run_time' => env('ATTENDANCE_DISCIPLINE_ALERTS_RUN_TIME', '23:57'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Tracking Settings
    |--------------------------------------------------------------------------
    |
    | Konfigurasi runtime untuk snapshot realtime, status stale, kualitas GPS,
    | dan retensi history tracking.
    |
    */
    'live_tracking' => [
        'enabled' => filter_var(env('LIVE_TRACKING_ENABLED', true), FILTER_VALIDATE_BOOL),
        'stale_seconds' => (int) env('LIVE_TRACKING_STALE_SECONDS', 300),
        'snapshot_expire_hours_after_midnight' => (int) env('LIVE_TRACKING_SNAPSHOT_EXPIRE_HOURS_AFTER_MIDNIGHT', 6),
        'retention_days' => (int) env('LIVE_TRACKING_RETENTION_DAYS', 30),
        'cleanup_time' => env('LIVE_TRACKING_CLEANUP_TIME', '02:15'),
        'current_store_rebuild_time' => env('LIVE_TRACKING_CURRENT_STORE_REBUILD_TIME', '00:10'),
        'read_current_store_enabled' => filter_var(env('LIVE_TRACKING_CURRENT_STORE_READ_ENABLED', true), FILTER_VALIDATE_BOOL),
        'min_distance_meters' => (int) env('LIVE_TRACKING_MIN_DISTANCE_METERS', 20),
        'persist_idle_seconds' => (int) env('LIVE_TRACKING_PERSIST_IDLE_SECONDS', 300),
        'gps_quality' => [
            'good_max_accuracy' => (float) env('LIVE_TRACKING_GPS_GOOD_MAX_ACCURACY', 20),
            'moderate_max_accuracy' => (float) env('LIVE_TRACKING_GPS_MODERATE_MAX_ACCURACY', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GPS Validation Settings
    |--------------------------------------------------------------------------
    |
    | accuracy_grace_meters memberi toleransi tambahan terhadap nilai akurasi
    | GPS device agar submit tidak terlalu sensitif saat sinyal berfluktuasi.
    |
    */
    'gps' => [
        'accuracy_grace_meters' => (float) env('ATTENDANCE_GPS_ACCURACY_GRACE_METERS', 0),
        'block_mocked' => filter_var(env('ATTENDANCE_GPS_BLOCK_MOCKED', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Event Reporting
    |--------------------------------------------------------------------------
    |
    | Mencatat sinyal kecurangan/suspicious behavior yang mudah dibaca pada
    | laporan evaluasi sekolah, seperti Fake GPS, device mismatch, percobaan
    | dari web/browser, dan submit di luar geofence.
    |
    */
    'security' => [
        'event_logging_enabled' => filter_var(env('ATTENDANCE_SECURITY_EVENT_LOGGING_ENABLED', true), FILTER_VALIDATE_BOOL),
        'rollout_mode' => env('ATTENDANCE_SECURITY_ROLLOUT_MODE', 'warning_mode'),
        'warn_user' => filter_var(env('ATTENDANCE_SECURITY_WARN_USER', true), FILTER_VALIDATE_BOOL),
        'allow_submit_with_security_warnings' => filter_var(env('ATTENDANCE_SECURITY_ALLOW_SUBMIT_WITH_WARNINGS', true), FILTER_VALIDATE_BOOL),
        'store_raw_payload' => filter_var(env('ATTENDANCE_SECURITY_STORE_RAW_PAYLOAD', true), FILTER_VALIDATE_BOOL),
        'timestamp_tolerance_seconds' => (int) env('ATTENDANCE_SECURITY_TIMESTAMP_TOLERANCE_SECONDS', 180),
        'nonce_ttl_seconds' => (int) env('ATTENDANCE_SECURITY_NONCE_TTL_SECONDS', 300),
        'duplicate_submit_window_seconds' => (int) env('ATTENDANCE_SECURITY_DUPLICATE_WINDOW_SECONDS', 10),
        'stale_location_seconds' => (int) env('ATTENDANCE_SECURITY_STALE_LOCATION_SECONDS', 120),
        'time_drift_warning_seconds' => (int) env('ATTENDANCE_SECURITY_TIME_DRIFT_WARNING_SECONDS', 90),
        'time_drift_reject_seconds' => (int) env('ATTENDANCE_SECURITY_TIME_DRIFT_REJECT_SECONDS', 300),
        'impossible_travel_speed_kmh' => (int) env('ATTENDANCE_SECURITY_IMPOSSIBLE_TRAVEL_SPEED_KMH', 300),
        'duplicate_coordinate_window_days' => (int) env('ATTENDANCE_SECURITY_DUPLICATE_COORDINATE_WINDOW_DAYS', 7),
        'duplicate_coordinate_limit' => (int) env('ATTENDANCE_SECURITY_DUPLICATE_COORDINATE_LIMIT', 5),
        'trusted_wifi_ssids' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATTENDANCE_SECURITY_TRUSTED_WIFI_SSIDS', ''))))),
        'trusted_wifi_bssids' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATTENDANCE_SECURITY_TRUSTED_WIFI_BSSIDS', ''))))),
        'app' => [
            'expected_android_package' => env('ATTENDANCE_SECURITY_EXPECTED_ANDROID_PACKAGE', 'id.sch.sman1sumbercirebon.siaps'),
            'expected_ios_bundle' => env('ATTENDANCE_SECURITY_EXPECTED_IOS_BUNDLE'),
            'allowed_installers' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATTENDANCE_SECURITY_ALLOWED_INSTALLERS', 'com.android.vending,com.google.android.packageinstaller,com.miui.packageinstaller'))))),
            'expected_android_signatures' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATTENDANCE_SECURITY_EXPECTED_ANDROID_SIGNATURES', ''))))),
        ],
        'request_signing' => [
            'enabled' => filter_var(env('ATTENDANCE_SECURITY_REQUEST_SIGNING_ENABLED', false), FILTER_VALIDATE_BOOL),
            'key' => env('ATTENDANCE_SECURITY_REQUEST_SIGNING_KEY'),
            'max_age_seconds' => (int) env('ATTENDANCE_SECURITY_REQUEST_SIGNING_MAX_AGE_SECONDS', 180),
        ],
        'thresholds' => [
            'warning_score' => (int) env('ATTENDANCE_SECURITY_WARNING_SCORE', 25),
            'manual_review_score' => (int) env('ATTENDANCE_SECURITY_MANUAL_REVIEW_SCORE', 50),
            'reject_score' => (int) env('ATTENDANCE_SECURITY_REJECT_SCORE', 80),
            'critical_score' => (int) env('ATTENDANCE_SECURITY_CRITICAL_SCORE', 100),
        ],
        'signals' => [
            'mock_location' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_MOCK_LOCATION_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_MOCK_LOCATION_SCORE', 60),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_MOCK_LOCATION_SEVERITY', 'high'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_MOCK_LOCATION_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'mock_provider' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_MOCK_PROVIDER_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_MOCK_PROVIDER_SCORE', 45),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_MOCK_PROVIDER_SEVERITY', 'high'),
            ],
            'developer_options' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_DEVELOPER_OPTIONS_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_DEVELOPER_OPTIONS_SCORE', 20),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_DEVELOPER_OPTIONS_SEVERITY', 'medium'),
            ],
            'gps_accuracy_low' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_GPS_ACCURACY_LOW_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_GPS_ACCURACY_LOW_SCORE', 10),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_GPS_ACCURACY_LOW_SEVERITY', 'low'),
            ],
            'outside_geofence' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_OUTSIDE_GEOFENCE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_OUTSIDE_GEOFENCE_SCORE', 35),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_OUTSIDE_GEOFENCE_SEVERITY', 'medium'),
            ],
            'stale_location' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_STALE_LOCATION_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_STALE_LOCATION_SCORE', 20),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_STALE_LOCATION_SEVERITY', 'medium'),
            ],
            'time_drift' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_TIME_DRIFT_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_TIME_DRIFT_SCORE', 15),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_TIME_DRIFT_SEVERITY', 'medium'),
            ],
            'emulator' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_EMULATOR_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_EMULATOR_SCORE', 40),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_EMULATOR_SEVERITY', 'high'),
            ],
            'root_or_jailbreak' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_ROOT_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_ROOT_SCORE', 45),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_ROOT_SEVERITY', 'high'),
            ],
            'adb_or_usb_debugging' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_ADB_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_ADB_SCORE', 20),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_ADB_SEVERITY', 'medium'),
            ],
            'device_spoofing' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_DEVICE_SPOOFING_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_DEVICE_SPOOFING_SCORE', 55),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_DEVICE_SPOOFING_SEVERITY', 'high'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_DEVICE_SPOOFING_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'app_clone' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_APP_CLONE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_APP_CLONE_SCORE', 35),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_APP_CLONE_SEVERITY', 'high'),
            ],
            'app_tampering' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_APP_TAMPERING_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_APP_TAMPERING_SCORE', 55),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_APP_TAMPERING_SEVERITY', 'critical'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_APP_TAMPERING_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'instrumentation' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_INSTRUMENTATION_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_INSTRUMENTATION_SCORE', 70),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_INSTRUMENTATION_SEVERITY', 'critical'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_INSTRUMENTATION_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'signature_mismatch' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_SIGNATURE_MISMATCH_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_SIGNATURE_MISMATCH_SCORE', 55),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_SIGNATURE_MISMATCH_SEVERITY', 'critical'),
            ],
            'request_replay' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_REQUEST_REPLAY_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_REQUEST_REPLAY_SCORE', 65),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_REQUEST_REPLAY_SEVERITY', 'critical'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_REQUEST_REPLAY_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'duplicate_frequency' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_FREQUENCY_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_FREQUENCY_SCORE', 20),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_FREQUENCY_SEVERITY', 'medium'),
            ],
            'forged_metadata' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_FORGED_METADATA_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_FORGED_METADATA_SCORE', 30),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_FORGED_METADATA_SEVERITY', 'high'),
            ],
            'mobile_policy_violation' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_MOBILE_POLICY_VIOLATION_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_MOBILE_POLICY_VIOLATION_SCORE', 25),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_MOBILE_POLICY_VIOLATION_SEVERITY', 'medium'),
                'block_in_strict' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_MOBILE_POLICY_VIOLATION_BLOCK', true), FILTER_VALIDATE_BOOL),
            ],
            'impossible_travel' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_IMPOSSIBLE_TRAVEL_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_IMPOSSIBLE_TRAVEL_SCORE', 35),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_IMPOSSIBLE_TRAVEL_SEVERITY', 'high'),
            ],
            'duplicate_coordinate_pattern' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_COORDINATE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_COORDINATE_SCORE', 15),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_DUPLICATE_COORDINATE_SEVERITY', 'medium'),
            ],
            'suspicious_network' => [
                'enabled' => filter_var(env('ATTENDANCE_SECURITY_SIGNAL_SUSPICIOUS_NETWORK_ENABLED', true), FILTER_VALIDATE_BOOL),
                'score' => (int) env('ATTENDANCE_SECURITY_SIGNAL_SUSPICIOUS_NETWORK_SCORE', 10),
                'severity' => env('ATTENDANCE_SECURITY_SIGNAL_SUSPICIOUS_NETWORK_SEVERITY', 'low'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Face Verification Settings
    |--------------------------------------------------------------------------
    |
    | Konfigurasi dasar verifikasi wajah untuk absensi. Implementasi engine real
    | dapat diintegrasikan bertahap tanpa mengubah alur endpoint absensi.
    |
    */
    'face' => [
        'enabled' => filter_var(env('ATTENDANCE_FACE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'template_required' => filter_var(env('ATTENDANCE_FACE_TEMPLATE_REQUIRED', true), FILTER_VALIDATE_BOOL),
        'default_mode' => env('ATTENDANCE_FACE_MODE_DEFAULT', 'async_pending'),
        'queue' => env('ATTENDANCE_FACE_QUEUE', 'face-verification'),
        'threshold' => (float) env('ATTENDANCE_FACE_THRESHOLD', 0.363),
        'engine_version' => env('ATTENDANCE_FACE_ENGINE_VERSION', 'opencv-yunet-sface-v1'),
        'result_when_template_missing' => env('ATTENDANCE_FACE_TEMPLATE_MISSING_RESULT', 'verified'),
        'reject_to_manual_review' => filter_var(env('ATTENDANCE_FACE_REJECT_TO_REVIEW', true), FILTER_VALIDATE_BOOL),
        'skip_when_photo_missing' => filter_var(env('ATTENDANCE_FACE_SKIP_WHEN_PHOTO_MISSING', true), FILTER_VALIDATE_BOOL),
        'service_url' => rtrim((string) env('ATTENDANCE_FACE_SERVICE_URL', 'http://127.0.0.1:9001'), '/'),
        'service_token' => env('ATTENDANCE_FACE_SERVICE_TOKEN'),
        'connect_timeout' => (float) env('ATTENDANCE_FACE_SERVICE_CONNECT_TIMEOUT', 1.5),
        'request_timeout' => (float) env('ATTENDANCE_FACE_SERVICE_REQUEST_TIMEOUT', 5.0),
    ],
];

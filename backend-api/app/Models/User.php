<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Carbon\Carbon;
use App\Support\RoleNames;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles {
        HasRoles::getAllPermissions as protected getAllPermissionsFromTrait;
        HasRoles::hasPermissionTo as protected hasPermissionToFromTrait;
    }

    /**
     * The guard name for Spatie Permission
     */
    protected $guard_name = 'web';

    /**
     * Nama tabel di database
     */
    protected $table = 'users';

    /**
     * Atribut yang dapat diisi secara massal
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'nama_lengkap',
        'nisn',
        'nis',
        'nip',
        'nik',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
        'foto_profil',
        'status_kepegawaian',
        'nuptk',
        'is_active',
        'notifikasi',
        'device_id',
        'device_name',
        'device_bound_at',
        'device_locked',
        'device_info',
        'last_device_activity',
        'created_by'
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Accessors that should always be present in serialized payloads.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'foto_profil_url',
    ];

    /**
     * Casting atribut ke tipe data tertentu
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tanggal_lahir' => 'date:Y-m-d',
        'tanggal_sk' => 'date',
        'tmt' => 'date',
        'masa_kontrak_mulai' => 'date',
        'masa_kontrak_selesai' => 'date',
        'is_active' => 'boolean',
        'device_bound_at' => 'datetime',
        'device_locked' => 'boolean',
        'device_info' => 'array',
        'last_device_activity' => 'datetime',
        'sub_jabatan' => 'array',
        'notifikasi' => 'array',
        'jumlah_anak' => 'integer'
    ];

    /**
     * Relasi one-to-many dengan Absensi
     */
    public function absensi()
    {
        return $this->hasMany(Absensi::class, 'user_id');
    }

    /**
     * Relasi one-to-many dengan PengajuanIzin
     */
    public function pengajuanIzin()
    {
        return $this->hasMany(PengajuanIzin::class, 'user_id');
    }

    /**
     * Relasi many-to-many dengan Kelas (untuk siswa)
     */
    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'kelas_siswa', 'siswa_id', 'kelas_id')
            ->withPivot('tahun_ajaran_id', 'is_active', 'status', 'tanggal_masuk', 'tanggal_keluar', 'keterangan')
            ->withTimestamps();
    }

    /**
     * Relasi one-to-many dengan Kelas (untuk wali kelas)
     */
    public function kelasWali()
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    /**
     * Relasi dengan DataPribadiSiswa
     */
    public function dataPribadiSiswa()
    {
        return $this->hasOne(DataPribadiSiswa::class);
    }

    /**
     * Relasi dengan DataKepegawaian
     */
    public function dataKepegawaian()
    {
        return $this->hasOne(DataKepegawaian::class);
    }

    /**
     * Relasi one-to-many dengan dokumen profil yang disimpan di storage sekolah.
     */
    public function personalDocuments()
    {
        return $this->hasMany(UserPersonalDocument::class, 'user_id');
    }

    /**
     * Relasi dengan UserAttendanceOverride
     */
    public function userAttendanceOverride()
    {
        return $this->hasOne(UserAttendanceOverride::class);
    }

    /**
     * Relasi one-to-many dengan UserFaceTemplate
     */
    public function faceTemplates()
    {
        return $this->hasMany(UserFaceTemplate::class, 'user_id');
    }

    /**
     * Relasi one-to-one dengan state kuota submit template wajah mandiri.
     */
    public function faceTemplateSubmissionState()
    {
        return $this->hasOne(UserFaceTemplateSubmissionState::class, 'user_id');
    }

    /**
     * Relasi one-to-many dengan AttendanceFaceVerification
     */
    public function attendanceFaceVerifications()
    {
        return $this->hasMany(AttendanceFaceVerification::class, 'user_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class, 'user_id');
    }

    /**
     * Accessor untuk URL foto profil
     */
    public function getFotoProfilUrlAttribute()
    {
        return self::resolveStoredPhotoUrl(
            $this->foto_profil,
            $this->updated_at?->timestamp
        );
    }

    public static function resolveStoredPhotoUrl(?string $path, int|string|null $version = null): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = trim($path);
        if ($normalized === '') {
            return null;
        }

        if (
            str_starts_with($normalized, 'http://') ||
            str_starts_with($normalized, 'https://') ||
            str_starts_with($normalized, 'data:') ||
            str_starts_with($normalized, 'blob:')
        ) {
            return self::appendPhotoVersion($normalized, $version);
        }

        $normalized = ltrim($normalized, '/');

        if (str_starts_with($normalized, 'api/storage/')) {
            $normalized = substr($normalized, 4);
        }

        if (str_starts_with($normalized, 'storage/storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (str_starts_with($normalized, 'storage/')) {
            return self::appendPhotoVersion(asset($normalized), $version);
        }

        if (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, 7);
        }

        return self::appendPhotoVersion(asset('storage/' . ltrim($normalized, '/')), $version);
    }

    private static function appendPhotoVersion(string $url, int|string|null $version = null): string
    {
        if ($version === null || $version === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . rawurlencode((string) $version);
    }

    /**
     * Scope untuk filter user aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk filter berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->role($role);
    }

    /**
     * Relasi one-to-many dengan ActivityLog
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'causer_id');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'name' => $this->nama_lengkap,
            'email' => $this->email,
            'username' => $this->username,
            'roles' => $this->roles->pluck('name'),
            'device_id' => $this->device_id,
            'device_locked' => $this->device_locked
        ];
    }

    /**
     * Get all permissions from all roles
     */
    public function getAllPermissions()
    {
        // Jika user adalah superadmin, return semua permission
        if ($this->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN))) {
            return Permission::query()
                ->where('guard_name', $this->guard_name)
                ->get();
        }

        return $this->getAllPermissionsFromTrait();
    }

    /**
     * Override hasPermissionTo untuk mendukung multiple role
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN))) {
            return true;
        }

        try {
            return $this->hasPermissionToFromTrait($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Check if user's device is locked to a specific device
     */
    public function isDeviceLocked()
    {
        return $this->device_locked && !empty($this->device_id);
    }

    /**
     * Check if the provided device ID matches the user's registered device
     */
    public function isValidDevice($deviceId)
    {
        if (!$this->isDeviceLocked()) {
            return true; // Device not locked, allow any device
        }

        return $this->device_id === $deviceId;
    }

    /**
     * Register a new device for the user
     */
    public function registerDevice($deviceId, $deviceName, $deviceInfo = [])
    {
        $this->update([
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'device_bound_at' => now(),
            'device_locked' => true,
            'device_info' => $deviceInfo,
            'last_device_activity' => now()
        ]);
    }

    /**
     * Reset user's device registration
     */
    public function resetDevice()
    {
        $this->update([
            'device_id' => null,
            'device_name' => null,
            'device_bound_at' => null,
            'device_locked' => false,
            'device_info' => null,
            'last_device_activity' => null
        ]);

        // Revoke all Sanctum tokens
        $this->tokens()->delete();
    }

    /**
     * Update last device activity
     */
    public function updateDeviceActivity()
    {
        $this->update(['last_device_activity' => now()]);
    }
}

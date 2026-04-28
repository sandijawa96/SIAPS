<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_key',
        'app_name',
        'app_description',
        'target_audience',
        'bundle_identifier',
        'platform',
        'release_channel',
        'public_version',
        'build_number',
        'download_url',
        'asset_path',
        'asset_disk',
        'asset_original_name',
        'asset_mime_type',
        'checksum_sha256',
        'file_size_bytes',
        'release_notes',
        'distribution_notes',
        'update_mode',
        'minimum_supported_version',
        'minimum_supported_build_number',
        'is_active',
        'is_published',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'file_size_bytes' => 'integer',
        'minimum_supported_build_number' => 'integer',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'app_label',
        'platform_label',
        'target_audience_label',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', strtolower(trim($platform)));
    }

    public function scopeForApp(Builder $query, string $appKey): Builder
    {
        return $query->where('app_key', strtolower(trim($appKey)));
    }

    public function scopeForChannel(Builder $query, string $releaseChannel): Builder
    {
        return $query->where('release_channel', strtolower(trim($releaseChannel)));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function updatePolicies()
    {
        return $this->hasMany(MobileUpdatePolicy::class, 'mobile_release_id');
    }

    public function getPlatformLabelAttribute(): string
    {
        return match (strtolower((string) $this->platform)) {
            'android' => 'Android',
            'ios' => 'iPhone / iOS',
            default => strtoupper((string) $this->platform),
        };
    }

    public function getAppLabelAttribute(): string
    {
        $name = trim((string) $this->app_name);

        return $name !== '' ? $name : strtoupper((string) $this->app_key);
    }

    public function getTargetAudienceLabelAttribute(): string
    {
        return match (strtolower((string) $this->target_audience)) {
            'siswa' => 'Siswa',
            'staff' => 'Pegawai Sekolah',
            default => 'Semua Akun',
        };
    }
}

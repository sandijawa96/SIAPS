<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDisciplineOverride extends Model
{
    use HasFactory;

    public const SCOPE_TINGKAT = 'tingkat';
    public const SCOPE_KELAS = 'kelas';
    public const SCOPE_USER = 'user';

    protected $table = 'attendance_discipline_overrides';

    protected $fillable = [
        'scope_type',
        'target_tingkat_id',
        'target_kelas_id',
        'target_user_id',
        'is_active',
        'discipline_thresholds_enabled',
        'total_violation_minutes_semester_limit',
        'alpha_days_semester_limit',
        'late_minutes_monthly_limit',
        'semester_total_violation_mode',
        'notify_wali_kelas_on_total_violation_limit',
        'notify_kesiswaan_on_total_violation_limit',
        'semester_alpha_mode',
        'monthly_late_mode',
        'notify_wali_kelas_on_late_limit',
        'notify_kesiswaan_on_late_limit',
        'notify_wali_kelas_on_alpha_limit',
        'notify_kesiswaan_on_alpha_limit',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discipline_thresholds_enabled' => 'boolean',
        'total_violation_minutes_semester_limit' => 'integer',
        'alpha_days_semester_limit' => 'integer',
        'late_minutes_monthly_limit' => 'integer',
        'notify_wali_kelas_on_total_violation_limit' => 'boolean',
        'notify_kesiswaan_on_total_violation_limit' => 'boolean',
        'notify_wali_kelas_on_late_limit' => 'boolean',
        'notify_kesiswaan_on_late_limit' => 'boolean',
        'notify_wali_kelas_on_alpha_limit' => 'boolean',
        'notify_kesiswaan_on_alpha_limit' => 'boolean',
    ];

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class, 'target_tingkat_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'target_kelas_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTargetId(): ?int
    {
        return match ($this->scope_type) {
            self::SCOPE_TINGKAT => $this->target_tingkat_id ? (int) $this->target_tingkat_id : null,
            self::SCOPE_KELAS => $this->target_kelas_id ? (int) $this->target_kelas_id : null,
            self::SCOPE_USER => $this->target_user_id ? (int) $this->target_user_id : null,
            default => null,
        };
    }

    public function getScopeLabelAttribute(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_TINGKAT => $this->tingkat?->nama
                ? 'Tingkat ' . $this->tingkat->nama
                : 'Tingkat #' . ($this->target_tingkat_id ?? '-'),
            self::SCOPE_KELAS => $this->kelas?->nama_kelas
                ? 'Kelas ' . $this->kelas->nama_kelas
                : 'Kelas #' . ($this->target_kelas_id ?? '-'),
            self::SCOPE_USER => $this->targetUser?->nama_lengkap
                ? 'Siswa ' . $this->targetUser->nama_lengkap
                : 'Siswa #' . ($this->target_user_id ?? '-'),
            default => 'Target tidak dikenal',
        };
    }
}

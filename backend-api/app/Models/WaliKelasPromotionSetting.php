<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaliKelasPromotionSetting extends Model
{
    use HasFactory;

    protected $table = 'wali_kelas_promotion_settings';

    protected $fillable = [
        'kelas_id',
        'tahun_ajaran_id',
        'is_enabled',
        'open_at',
        'close_at',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'open_at' => 'datetime',
        'close_at' => 'datetime',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isOpenAt(?Carbon $referenceTime = null): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $now = ($referenceTime ?? now())->copy()->setTimezone(config('app.timezone'));
        $openAt = $this->open_at ? $this->open_at->copy()->setTimezone(config('app.timezone')) : null;
        $closeAt = $this->close_at ? $this->close_at->copy()->setTimezone(config('app.timezone')) : null;

        if ($openAt && $now->lt($openAt)) {
            return false;
        }

        if ($closeAt && $now->gt($closeAt)) {
            return false;
        }

        return true;
    }
}

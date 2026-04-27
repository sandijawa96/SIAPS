<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalPelajaranSettingDay extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran_setting_days';

    protected $fillable = [
        'setting_id',
        'hari',
        'is_school_day',
        'jp_count',
        'jp_minutes',
        'start_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_school_day' => 'boolean',
        ];
    }

    public function setting()
    {
        return $this->belongsTo(JadwalPelajaranSetting::class, 'setting_id');
    }

    public function breaks()
    {
        return $this->hasMany(JadwalPelajaranSettingBreak::class, 'day_setting_id')
            ->orderBy('after_jp');
    }
}

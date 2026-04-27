<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalPelajaranSetting extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran_settings';

    protected $fillable = [
        'tahun_ajaran_id',
        'semester',
        'default_jp_minutes',
        'default_start_time',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function days()
    {
        return $this->hasMany(JadwalPelajaranSettingDay::class, 'setting_id');
    }
}

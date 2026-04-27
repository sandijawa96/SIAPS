<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalPelajaranSettingBreak extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran_setting_breaks';

    protected $fillable = [
        'day_setting_id',
        'after_jp',
        'break_minutes',
        'label',
    ];

    public function daySetting()
    {
        return $this->belongsTo(JadwalPelajaranSettingDay::class, 'day_setting_id');
    }
}

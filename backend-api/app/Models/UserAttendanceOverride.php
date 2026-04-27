<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAttendanceOverride extends Model
{
    use HasFactory;

    protected $table = 'user_attendance_overrides';

    protected $fillable = [
        'user_id',
        'jam_masuk',
        'jam_pulang',
        'toleransi',
        'wajib_gps',
        'wajib_foto',
        'hari_kerja',
        'lokasi_gps_ids',
        'keterangan',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'wajib_gps' => 'boolean',
        'wajib_foto' => 'boolean',
        'hari_kerja' => 'array',
        'lokasi_gps_ids' => 'array',
        'is_active' => 'boolean',
        'jam_masuk' => 'string',
        'jam_pulang' => 'string',
        'toleransi' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

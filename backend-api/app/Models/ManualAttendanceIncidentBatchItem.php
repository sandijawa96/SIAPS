<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualAttendanceIncidentBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'user_id',
        'kelas_id',
        'tingkat_id',
        'attendance_id',
        'nama_lengkap',
        'email',
        'kelas_label',
        'tingkat_label',
        'result_code',
        'result_label',
        'message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ManualAttendanceIncidentBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance()
    {
        return $this->belongsTo(Absensi::class, 'attendance_id');
    }
}

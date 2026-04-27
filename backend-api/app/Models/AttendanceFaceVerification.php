<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceFaceVerification extends Model
{
    use HasFactory;

    protected $table = 'attendance_face_verifications';

    protected $fillable = [
        'absensi_id',
        'user_id',
        'check_type',
        'score',
        'threshold',
        'result',
        'reason_code',
        'engine_version',
        'processing_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'threshold' => 'decimal:4',
            'processing_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function absensi()
    {
        return $this->belongsTo(Absensi::class, 'absensi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


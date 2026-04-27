<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFaceTemplateSubmissionState extends Model
{
    use HasFactory;

    protected $table = 'user_face_template_submission_states';

    protected $fillable = [
        'user_id',
        'self_submit_count',
        'unlock_allowance_remaining',
        'last_submitted_at',
        'last_unlocked_at',
        'last_unlocked_by',
    ];

    protected function casts(): array
    {
        return [
            'self_submit_count' => 'integer',
            'unlock_allowance_remaining' => 'integer',
            'last_submitted_at' => 'datetime',
            'last_unlocked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastUnlockedBy()
    {
        return $this->belongsTo(User::class, 'last_unlocked_by');
    }
}

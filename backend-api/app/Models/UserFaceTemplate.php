<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFaceTemplate extends Model
{
    use HasFactory;

    protected $table = 'user_face_templates';

    protected $fillable = [
        'user_id',
        'template_vector',
        'template_path',
        'template_version',
        'quality_score',
        'enrolled_at',
        'enrolled_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'decimal:4',
            'enrolled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSecurityCaseEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'attendance_security_case_evidence';

    protected $fillable = [
        'case_id',
        'uploaded_by',
        'evidence_type',
        'title',
        'description',
        'file_disk',
        'file_path',
        'file_original_name',
        'file_mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'metadata',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(AttendanceSecurityCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function toArrayPayload(): array
    {
        return [
            'id' => (int) $this->id,
            'evidence_type' => $this->evidence_type,
            'title' => $this->title,
            'description' => $this->description,
            'file_original_name' => $this->file_original_name,
            'file_mime_type' => $this->file_mime_type,
            'file_size_bytes' => $this->file_size_bytes !== null ? (int) $this->file_size_bytes : null,
            'checksum_sha256' => $this->checksum_sha256,
            'metadata' => is_array($this->metadata) ? $this->metadata : null,
            'uploaded_by' => $this->uploader ? [
                'id' => (int) $this->uploader->id,
                'name' => $this->uploader->nama_lengkap,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

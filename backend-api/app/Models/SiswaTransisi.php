<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaTransisi extends Model
{
    use HasFactory;

    protected $table = 'siswa_transisi';

    protected $fillable = [
        'siswa_id',
        'type',
        'kelas_asal_id',
        'kelas_tujuan_id',
        'tahun_ajaran_id',
        'tanggal_transisi',
        'keterangan',
        'processed_by',
        'is_undone',
        'can_undo',
        'undone_by',
        'undone_at',
        'undo_reason'
    ];

    protected $casts = [
        'tanggal_transisi' => 'date',
        'is_undone' => 'boolean',
        'can_undo' => 'boolean',
        'undone_at' => 'datetime'
    ];

    /**
     * Relasi ke siswa
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'siswa_id');
    }

    /**
     * Relasi ke kelas asal
     */
    public function kelasAsal(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_asal_id');
    }

    /**
     * Relasi ke kelas tujuan
     */
    public function kelasTujuan(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_tujuan_id');
    }

    /**
     * Relasi ke tahun ajaran
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    /**
     * Relasi ke user yang memproses
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Relasi ke user yang undo
     */
    public function undoneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    /**
     * Scope untuk transisi yang bisa di-undo
     */
    public function scopeCanUndo($query)
    {
        return $query->where('can_undo', true)
            ->where('is_undone', false)
            ->where('tanggal_transisi', '>=', now()->subHours(24));
    }

    /**
     * Scope untuk transisi yang belum di-undo
     */
    public function scopeNotUndone($query)
    {
        return $query->where('is_undone', false);
    }

    /**
     * Scope untuk transisi berdasarkan siswa
     */
    public function scopeBySiswa($query, $siswaId)
    {
        return $query->where('siswa_id', $siswaId);
    }

    /**
     * Scope untuk transisi berdasarkan type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check apakah transisi bisa di-undo
     */
    public function canBeUndone(): bool
    {
        if ($this->is_undone || !$this->can_undo) {
            return false;
        }

        // Hanya bisa undo dalam 24 jam
        $hoursDiff = now()->diffInHours($this->tanggal_transisi);
        return $hoursDiff <= 24;
    }

    /**
     * Undo transisi
     */
    public function undo($undoneBy, $reason = null): bool
    {
        if (!$this->canBeUndone()) {
            return false;
        }

        $this->update([
            'is_undone' => true,
            'undone_by' => $undoneBy,
            'undone_at' => now(),
            'undo_reason' => $reason
        ]);

        return true;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    use HasFactory;

    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'kode_mapel',
        'nama_mapel',
        'kelompok',
        'tingkat_id',
        'is_active',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tingkat()
    {
        return $this->belongsTo(Tingkat::class, 'tingkat_id');
    }

    public function guruAssignments()
    {
        return $this->hasMany(GuruMataPelajaran::class, 'mata_pelajaran_id');
    }

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class, 'mata_pelajaran_id');
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tingkat extends Model
{
    protected $table = 'tingkat';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
        'urutan',
        'is_active'
    ];

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'tingkat_id');
    }

    /**
     * Get the number of classes for this tingkat
     */
    public function getJumlahKelasAttribute()
    {
        return $this->kelas()->count();
    }
}

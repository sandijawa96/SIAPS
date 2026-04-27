<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileUpdatePolicy extends Model
{
    protected $fillable = [
        'mobile_release_id',
        'audience',
        'update_mode',
        'minimum_supported_version',
        'minimum_supported_build_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'minimum_supported_build_number' => 'integer',
    ];

    public function release()
    {
        return $this->belongsTo(MobileRelease::class, 'mobile_release_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

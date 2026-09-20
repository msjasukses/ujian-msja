<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mata pelajaran — dibaca dari database "datacenter" (READ-ONLY). */
class MataPelajaran extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'mata_pelajaran';

    protected $fillable = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', 1);
    }
}

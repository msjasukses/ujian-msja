<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tingkat kelas (X, XI, XII) — dibaca dari database "datacenter" (READ-ONLY). */
class TingkatKelas extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'tingkat_kelas';

    protected $fillable = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', 1);
    }

    public function scopeUrut($query)
    {
        return $query->orderBy('urutan')->orderBy('nomor');
    }
}

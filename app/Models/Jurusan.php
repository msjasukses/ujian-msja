<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jurusan / program keahlian — database "datacenter" (READ-ONLY). */
class Jurusan extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'jurusan';

    protected $fillable = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', 1);
    }

    /** Nama lengkap beserta singkatannya, untuk dropdown. */
    public function getLabelAttribute(): string
    {
        return $this->nama_jurusan.($this->singkatan ? ' ('.$this->singkatan.')' : '');
    }

    /** Singkatan bila ada, untuk kolom tabel yang sempit. */
    public function getRingkasAttribute(): string
    {
        return $this->singkatan ?: $this->nama_jurusan;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tahun ajaran — dibaca dari database "datacenter" (READ-ONLY). */
class TahunAjaran extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'tahun_ajaran';

    protected $fillable = [];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_aktif' => 'boolean',
    ];

    /** Tahun ajaran yang ditandai aktif di datacenter; fallback ke yang terbaru. */
    public static function aktif(): ?self
    {
        return static::where('is_aktif', 1)->first()
            ?? static::orderByDesc('kode_tahun_ajaran')->first();
    }
}

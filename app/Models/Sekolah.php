<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Identitas sekolah — database "datacenter" (READ-ONLY). Dipakai pada kop laporan. */
class Sekolah extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'sekolah';

    protected $fillable = [];

    public static function profil(): ?self
    {
        return static::query()->first();
    }
}

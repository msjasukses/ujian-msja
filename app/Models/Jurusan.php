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
}

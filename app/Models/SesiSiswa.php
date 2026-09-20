<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sesi yang sah untuk satu akun siswa; hanya boleh ada satu. */
class SesiSiswa extends Model
{
    protected $table = 'sesi_siswa';

    protected $fillable = ['siswa_id', 'token', 'ip_address', 'user_agent', 'login_at', 'last_seen_at'];

    protected $casts = ['login_at' => 'datetime', 'last_seen_at' => 'datetime'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penempatan siswa pada rombel per tahun ajaran — database "datacenter"
 * (READ-ONLY). Siswa tidak punya kolom kelas langsung, jadi tabel inilah
 * yang menentukan siswa mana yang ikut ujian sebuah kelas.
 */
class SiswaRombel extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'siswa_rombel';

    protected $fillable = [];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function rombel()
    {
        return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id');
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
}

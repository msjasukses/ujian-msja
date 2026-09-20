<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penugasan guru mengajar mapel di rombel — database "datacenter" (READ-ONLY). */
class GuruMapel extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'guru_mapel';

    protected $fillable = [];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
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

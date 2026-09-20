<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rombongan belajar / kelas — dibaca dari database "datacenter" (READ-ONLY). */
class RombonganBelajar extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'rombongan_belajar';

    protected $fillable = [];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    /**
     * Wali kelas rombel ini. Datacenter tidak punya tabel wali_kelas
     * tersendiri — penunjukannya nempel di kolom wali_kelas_id.
     */
    public function waliKelas()
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_id');
    }

    public function siswaRombel(): HasMany
    {
        return $this->hasMany(SiswaRombel::class, 'rombongan_belajar_id');
    }

    /** Id siswa yang terdaftar di rombel ini. @return array<int,int> */
    public function siswaIds(): array
    {
        return SiswaRombel::where('rombongan_belajar_id', $this->id)
            ->pluck('siswa_id')->map('intval')->all();
    }

    public function getNamaLengkapAttribute(): string
    {
        return trim($this->nama_rombel.' ('.($this->tahunAjaran->nama_tahun_ajaran ?? '-').')');
    }
}

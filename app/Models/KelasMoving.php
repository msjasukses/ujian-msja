<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelas moving — rombongan lintas rombel yang mengambil satu mata pelajaran
 * yang sama. Disusun di Data Center (koneksi "datacenter", READ-ONLY), jadi
 * aplikasi ujian hanya membacanya untuk menentukan kandidat peserta.
 *
 * Anggotanya ada di tabel kelas_moving_siswa, bukan di siswa_rombel: seorang
 * siswa tetap punya rombel asal, sekaligus ikut satu kelas moving per mapel.
 */
class KelasMoving extends Model
{
    protected $connection = 'datacenter';

    protected $table = 'kelas_moving';

    protected $fillable = [];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(KelasMovingSiswa::class, 'kelas_moving_id');
    }

    /** Id siswa anggota kelas moving ini. @return array<int,int> */
    public function siswaIds(): array
    {
        return $this->anggota()->pluck('siswa_id')->map('intval')->all();
    }

    /** Nama berikut ruangnya, untuk label pilihan. */
    public function getNamaLengkapAttribute(): string
    {
        return $this->nama_kelas_moving.($this->ruang ? ' ('.$this->ruang.')' : '');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}

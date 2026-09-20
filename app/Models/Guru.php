<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Data guru/PTK dibaca dari database "datacenter" (READ-ONLY) dan sekaligus
 * menjadi penyedia autentikasi guard "guru" (login memakai NIP + password
 * yang sudah tersimpan di datacenter).
 */
class Guru extends Authenticatable
{
    protected $connection = 'datacenter';

    protected $table = 'guru';

    /** Read-only dari sisi aplikasi ujian. */
    protected $fillable = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_aktif' => 'boolean',
    ];

    /**
     * Sama seperti pada App\Models\Siswa: kolom identitas autentikasi tetap
     * "id". Masuk memakai NIP ditangani lewat kredensial pada Auth::attempt.
     */
    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'guru_id');
    }

    /** Rombel yang diwalikan guru ini. */
    public function rombelWali(): HasMany
    {
        return $this->hasMany(RombonganBelajar::class, 'wali_kelas_id');
    }

    public function getNamaAttribute(): string
    {
        return (string) $this->nama_ptk;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', 1);
    }

    /**
     * Id mata pelajaran yang diampu guru ini (gabungan kolom guru.mata_pelajaran_id
     * dan penugasan pada tabel guru_mapel).
     *
     * @return array<int, int>
     */
    public function mapelIds(?int $tahunAjaranId = null): array
    {
        $ids = GuruMapel::where('guru_id', $this->id)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('mata_pelajaran_id')
            ->all();

        if ($this->mata_pelajaran_id) {
            $ids[] = (int) $this->mata_pelajaran_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}

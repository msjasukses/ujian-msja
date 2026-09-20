<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Data siswa dibaca dari database "datacenter" (READ-ONLY). Aplikasi ujian
 * tidak pernah menulis ke tabel ini — $fillable sengaja dikosongkan.
 *
 * Model ini juga dipakai sebagai penyedia autentikasi guard "siswa":
 * peserta ujian login memakai NISN + password yang sudah ada di datacenter.
 */
class Siswa extends Authenticatable
{
    protected $connection = 'datacenter';

    protected $table = 'siswa';

    /** Read-only: tidak ada kolom yang boleh di-mass assign dari aplikasi ini. */
    protected $fillable = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_aktif' => 'boolean',
    ];

    /**
     * Catatan: getAuthIdentifierName() sengaja TIDAK dioverride menjadi
     * "nisn". Login memakai NISN sudah ditangani lewat kredensial
     * Auth::attempt(['nisn' => ..., 'password' => ...]); mengubah kolom
     * identitas akan membuat Auth::id() mengembalikan NISN, bukan id, dan
     * merusak setiap perbandingan siswa_id di aplikasi ini.
     */
    public function rombel(): HasOne
    {
        return $this->hasOne(SiswaRombel::class, 'siswa_id');
    }

    public function rombelSemua(): HasMany
    {
        return $this->hasMany(SiswaRombel::class, 'siswa_id');
    }

    /** Rombel siswa pada tahun ajaran tertentu (default: tahun ajaran aktif). */
    public function rombelPada(?int $tahunAjaranId = null): ?RombonganBelajar
    {
        $tahunAjaranId ??= TahunAjaran::aktif()?->id;

        $penempatan = SiswaRombel::where('siswa_id', $this->id)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->latest('id')
            ->first();

        return $penempatan?->rombel;
    }

    public function getNamaAttribute(): string
    {
        return (string) $this->nama_siswa;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', 1)->where('status_siswa', 'Aktif');
    }
}

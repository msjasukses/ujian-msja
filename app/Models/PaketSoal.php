<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paket soal = hasil menu "Pemilihan Soal yang Diujikan". Satu paket memuat
 * butir-butir soal terpilih beserta urutan dan bobotnya, lalu dipakai oleh
 * satu atau beberapa jadwal ujian.
 */
class PaketSoal extends Model
{
    use MilikGuru;

    protected $table = 'paket_soal';

    /** @var array<int, string> */
    public const JENIS_UJIAN = [
        'UH' => 'Ulangan Harian',
        'PTS' => 'Penilaian Tengah Semester',
        'PAS' => 'Penilaian Akhir Semester',
        'PAT' => 'Penilaian Akhir Tahun',
        'US' => 'Ujian Sekolah',
        'TO' => 'Try Out',
    ];

    protected $fillable = [
        'kode_paket',
        'nama_paket',
        'mata_pelajaran_id',
        'tingkat_kelas_id',
        'tahun_ajaran',
        'semester',
        'jenis_ujian',
        'deskripsi',
        'acak_soal',
        'acak_opsi',
        'guru_id',
        'is_aktif',
    ];

    protected $casts = [
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'is_aktif' => 'boolean',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(PaketSoalDetail::class)->orderBy('nomor_urut');
    }

    public function soal()
    {
        return $this->belongsToMany(Soal::class, 'paket_soal_detail')
            ->withPivot(['nomor_urut', 'bobot'])
            ->withTimestamps()
            ->orderBy('paket_soal_detail.nomor_urut');
    }

    public function ujian(): HasMany
    {
        return $this->hasMany(Ujian::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function tingkatKelas()
    {
        return $this->belongsTo(TingkatKelas::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function getTotalBobotAttribute(): float
    {
        return (float) $this->detail()->sum('bobot');
    }

    public function getJumlahSoalAttribute(): int
    {
        return $this->detail()->count();
    }

    /** Nomor urut berikutnya saat menambah soal ke paket. */
    public function nomorUrutBerikutnya(): int
    {
        return ((int) $this->detail()->max('nomor_urut')) + 1;
    }

    /** Rapikan nomor urut jadi 1..n setelah ada penghapusan/penyisipan. */
    public function rapikanUrutan(): void
    {
        $this->detail()->orderBy('nomor_urut')->orderBy('id')
            ->get()
            ->each(fn ($d, $i) => $d->update(['nomor_urut' => $i + 1]));
    }
}

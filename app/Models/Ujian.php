<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal ujian hasil menu "Registrasi Ujian": memasangkan satu paket soal
 * dengan kelas peserta, jendela waktu, durasi, token dan KKM.
 */
class Ujian extends Model
{
    use MilikGuru;

    protected $table = 'ujian';

    public const DRAFT = 'draft';

    public const AKTIF = 'aktif';

    public const SELESAI = 'selesai';

    /** @var array<string, string> */
    public const STATUS = [
        self::DRAFT => 'Draft',
        self::AKTIF => 'Aktif',
        self::SELESAI => 'Selesai',
    ];

    protected $fillable = [
        'kode_ujian',
        'nama_ujian',
        'paket_soal_id',
        'mata_pelajaran_id',
        'tahun_ajaran',
        'semester',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'token',
        'kkm',
        'tampilkan_hasil',
        'acak_soal',
        'acak_opsi',
        'proteksi_ketat',
        'maks_pelanggaran',
        'status',
        'ujian_induk_id',
        'is_remidial',
        'guru_id',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'kkm' => 'decimal:2',
        'tampilkan_hasil' => 'boolean',
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'proteksi_ketat' => 'boolean',
        'maks_pelanggaran' => 'integer',
        'is_remidial' => 'boolean',
    ];

    public function paketSoal()
    {
        return $this->belongsTo(PaketSoal::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function ujianInduk()
    {
        return $this->belongsTo(Ujian::class, 'ujian_induk_id');
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(UjianKelas::class);
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(UjianPeserta::class);
    }

    public function log(): HasMany
    {
        return $this->hasMany(UjianLog::class);
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjut::class);
    }

    /** Id rombel peserta ujian ini. @return array<int,int> */
    public function rombelIds(): array
    {
        return $this->kelas()->pluck('rombongan_belajar_id')->map('intval')->all();
    }

    /** Ujian sedang berada dalam jendela waktu pelaksanaan. */
    public function getSedangBerlangsungAttribute(): bool
    {
        return $this->status === self::AKTIF
            && now()->betweenIncluded($this->waktu_mulai, $this->waktu_selesai);
    }

    public function getBelumMulaiAttribute(): bool
    {
        return now()->lt($this->waktu_mulai);
    }

    public function getSudahLewatAttribute(): bool
    {
        return now()->gt($this->waktu_selesai);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::AKTIF);
    }

    /** Nilai maksimum yang bisa dicapai (total bobot butir di paket). */
    public function getTotalBobotAttribute(): float
    {
        return (float) PaketSoalDetail::where('paket_soal_id', $this->paket_soal_id)->sum('bobot');
    }
}

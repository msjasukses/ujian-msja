<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris = satu siswa yang terdaftar pada satu ujian, sekaligus wadah
 * hasil akhirnya (nilai, jumlah benar/salah, waktu pengerjaan).
 */
class UjianPeserta extends Model
{
    protected $table = 'ujian_peserta';

    public const TERDAFTAR = 'terdaftar';

    public const MULAI = 'mulai';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    /** @var array<string, string> */
    public const STATUS = [
        self::TERDAFTAR => 'Belum Mengerjakan',
        self::MULAI => 'Sedang Mengerjakan',
        self::SELESAI => 'Selesai',
        self::DIBATALKAN => 'Dibatalkan',
    ];

    protected $fillable = [
        'ujian_id',
        'siswa_id',
        'rombongan_belajar_id',
        'nomor_peserta',
        'status',
        'waktu_mulai',
        'waktu_selesai',
        'sisa_detik',
        'nilai',
        'skor_objektif',
        'skor_essay',
        'jumlah_benar',
        'jumlah_salah',
        'jumlah_kosong',
        'reset_count',
        'pelanggaran',
        'dikunci_at',
        'essay_dinilai',
        'ip_address',
        'browser',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'nilai' => 'decimal:2',
        'skor_objektif' => 'decimal:2',
        'skor_essay' => 'decimal:2',
        'essay_dinilai' => 'boolean',
        'dikunci_at' => 'datetime',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function rombel()
    {
        return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(UjianJawaban::class)->orderBy('nomor_urut');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Sisa waktu pengerjaan dalam detik, dibatasi jendela waktu ujian. */
    public function sisaWaktuDetik(): int
    {
        if ($this->status !== self::MULAI || ! $this->waktu_mulai) {
            return 0;
        }

        $batasDurasi = $this->waktu_mulai->copy()->addMinutes($this->ujian->durasi_menit);
        $batas = $batasDurasi->min($this->ujian->waktu_selesai);

        return max(0, now()->diffInSeconds($batas, false));
    }

    /** Lulus KKM ujian yang bersangkutan. */
    public function getLulusAttribute(): ?bool
    {
        if ($this->nilai === null) {
            return null;
        }

        return (float) $this->nilai >= (float) $this->ujian->kkm;
    }

    public function scopeSelesai($query)
    {
        return $query->where('status', self::SELESAI);
    }
}

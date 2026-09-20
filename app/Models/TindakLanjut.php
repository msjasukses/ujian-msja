<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rencana remidial / pengayaan yang lahir dari hasil sebuah ujian. */
class TindakLanjut extends Model
{
    protected $table = 'tindak_lanjut';

    public const REMIDIAL = 'remidial';

    public const PENGAYAAN = 'pengayaan';

    /** @var array<string, string> */
    public const BENTUK_REMIDIAL = [
        'tes_ulang' => 'Tes Ulang',
        'pembelajaran_ulang' => 'Pembelajaran Ulang',
        'tutor_sebaya' => 'Tutor Sebaya',
        'tugas_individu' => 'Tugas Individu',
    ];

    /** @var array<string, string> */
    public const BENTUK_PENGAYAAN = [
        'proyek' => 'Proyek / Penelitian Kecil',
        'soal_hots' => 'Latihan Soal HOTS',
        'tutor_sebaya' => 'Menjadi Tutor Sebaya',
        'materi_lanjut' => 'Materi Pengembangan',
    ];

    protected $fillable = [
        'ujian_id',
        'siswa_id',
        'jenis',
        'nilai_awal',
        'nilai_akhir',
        'tanggal',
        'bentuk',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nilai_awal' => 'decimal:2',
        'nilai_akhir' => 'decimal:2',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function getBentukLabelAttribute(): string
    {
        $peta = $this->jenis === self::PENGAYAAN ? self::BENTUK_PENGAYAAN : self::BENTUK_REMIDIAL;

        return $peta[$this->bentuk] ?? (string) $this->bentuk;
    }
}

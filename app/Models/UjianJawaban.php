<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UjianJawaban extends Model
{
    protected $table = 'ujian_jawaban';

    protected $fillable = [
        'ujian_peserta_id',
        'soal_id',
        'nomor_urut',
        'jawaban',
        'urutan_opsi',
        'ragu',
        'is_benar',
        'skor',
    ];

    protected $casts = [
        'jawaban' => 'array',
        'urutan_opsi' => 'array',
        'ragu' => 'boolean',
        'is_benar' => 'boolean',
        'skor' => 'decimal:2',
    ];

    public function peserta()
    {
        return $this->belongsTo(UjianPeserta::class, 'ujian_peserta_id');
    }

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }

    /** Peserta sudah mengisi sesuatu pada butir ini. */
    public function getTerisiAttribute(): bool
    {
        $j = $this->jawaban;

        if ($j === null || $j === [] || $j === '') {
            return false;
        }

        // Essay disimpan sebagai {"teks": "..."} — spasi kosong dianggap belum diisi.
        if (is_array($j) && array_key_exists('teks', $j)) {
            return trim((string) $j['teks']) !== '';
        }

        return true;
    }

    /** Jawaban essay dalam bentuk teks polos. */
    public function getTeksEssayAttribute(): string
    {
        return (string) ($this->jawaban['teks'] ?? '');
    }
}

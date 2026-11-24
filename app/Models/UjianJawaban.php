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

    /**
     * Opsi butir ini menurut tampilan yang berlaku bagi peserta.
     *
     * Yang diacak hanya isi opsinya; abjadnya tetap urut dari atas ke bawah
     * mengikuti huruf asli soal. Jadi tiap baris punya dua identitas:
     *  - key   : huruf asli opsi. Inilah yang dikirim ke server dan dicocokkan
     *            dengan kunci, sehingga koreksi tidak terpengaruh pengacakan.
     *  - label : huruf yang dibaca peserta, selalu menurut posisi barisnya.
     * Tanpa pengacakan, keduanya sama.
     *
     * @param  string  $sisi  'kiri' (opsi/pernyataan) atau 'kanan' (pasangan penjodohan)
     * @return array<int, array{key: string, label: string, text: string}>
     */
    public function opsiTampil(string $sisi = 'kiri'): array
    {
        $soal = $this->soal;

        if (! $soal) {
            return [];
        }

        $penjodohan = $soal->jenis === Soal::PENJODOHAN;

        if ($sisi === 'kanan') {
            $asli = $penjodohan ? $soal->opsiKanan() : [];
            $bolehDiacak = $penjodohan;
        } else {
            $asli = $soal->opsiPilihan();
            $bolehDiacak = in_array($soal->jenis, [Soal::PG, Soal::PG_KOMPLEKS, Soal::BENAR_SALAH_KOMPLEKS], true);
        }

        $asli = collect($asli)->values();
        $tampil = $asli;

        if ($bolehDiacak && $this->urutan_opsi) {
            $peta = $asli->keyBy('key');
            $tampil = collect($this->urutan_opsi)
                ->map(fn ($k) => $peta[$k] ?? null)
                ->filter()
                ->values();
        }

        // Abjad diambil dari daftar asli menurut posisi baris, bukan dari opsi
        // yang menempatinya. Itulah yang membuat huruf tetap urut meski isinya
        // berpindah tempat.
        return $tampil->map(fn ($o, $i) => [
            'key' => (string) $o['key'],
            'label' => (string) ($asli[$i]['key'] ?? $o['key']),
            'text' => (string) ($o['text'] ?? ''),
        ])->all();
    }

    /** Jawaban essay dalam bentuk teks polos. */
    public function getTeksEssayAttribute(): string
    {
        return (string) ($this->jawaban['teks'] ?? '');
    }
}

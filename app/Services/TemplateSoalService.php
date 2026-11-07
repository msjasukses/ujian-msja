<?php

namespace App\Services;

use App\Models\Soal;
use App\Support\Template\Daftar;
use App\Support\Template\Template;
use Illuminate\Validation\Rule;

/**
 * Perakit butir soal dari template.
 *
 * Tugasnya dua: menyusun aturan validasi dari keterangan kolom yang
 * dinyatakan tiap template, lalu menyerahkan isian yang sudah bersih ke
 * template itu. Aturan tidak ditulis ulang per template supaya formulir yang
 * dilihat guru, kolom yang divalidasi, dan kolom yang dipakai menggambar
 * selamanya berasal dari satu daftar yang sama.
 */
class TemplateSoalService
{
    /**
     * Jenis butir yang bisa dihasilkan template. Penjodohan dan PG kompleks
     * sengaja tidak ada: keduanya butuh beberapa gambar sekaligus, bukan satu
     * gambar dengan satu jawaban.
     *
     * @var array<string, string>
     */
    public const JENIS = [
        Soal::PG => 'Pilihan Ganda',
        Soal::ESSAY => 'Isian Singkat',
        Soal::BENAR_SALAH => 'Benar / Salah',
    ];

    /**
     * Aturan validasi untuk isian sebuah template, beserta nama kolomnya
     * agar pesan kesalahan menyebut "Panjang", bukan "isi.panjang".
     *
     * Kolom yang sedang tidak tampil — ukuran lingkaran saat yang dipilih
     * persegi — tidak ikut divalidasi, persis seperti ia tidak ikut dikirim.
     *
     * @return array{0: array<string, array>, 1: array<string, string>}
     */
    public function aturan(string $kode, array $isi): array
    {
        /** @var class-string<Template> $kelas */
        $kelas = Daftar::kelas($kode);

        $aturan = [];
        $nama = [];

        foreach ($kelas::kolomAktif($isi) as $kolom) {
            $kunci = 'isi.'.$kolom['nama'];
            $nama[$kunci] = $kolom['label'];

            $aturan[$kunci] = match ($kolom['jenis']) {
                'angka' => array_filter([
                    'required',
                    ($kolom['langkah'] ?? null) === 1 ? 'integer' : 'numeric',
                    isset($kolom['min']) ? 'min:'.$kolom['min'] : null,
                    isset($kolom['max']) ? 'max:'.$kolom['max'] : null,
                ]),
                // Rule::in, bukan "in:a,b": nilai pilihan boleh memuat koma —
                // "3,14" misalnya — dan aturan berbentuk teks akan memecahnya
                // menjadi dua nilai yang keduanya salah.
                'pilihan', 'pilihan-ikon' => ['required', Rule::in(array_keys($kolom['pilihan']))],
                'warna' => ['required', Rule::in(array_keys(Template::WARNA))],
                'centang' => ['nullable', 'boolean'],
                default => ['nullable', 'string', 'max:200'],
            };
        }

        return [$aturan, $nama];
    }

    /**
     * Pemeriksaan silang kolom milik template — isian yang masing-masing sah
     * tetapi tidak masuk akal bila digabung.
     *
     * @return array<string, string> kunci sudah berawalan "isi."
     */
    public function periksa(string $kode, array $isi): array
    {
        $kelas = Daftar::kelas($kode);

        return collect($kelas::periksa($isi))
            ->mapWithKeys(fn ($pesan, $kolom) => ['isi.'.$kolom => $pesan])
            ->all();
    }

    /** @return array<string, mixed> */
    public function rakit(string $kode, array $isi, string $jenis, bool $pernyataanBenar = true): array
    {
        return Daftar::buat($kode)->rakit($isi, $jenis, $pernyataanBenar);
    }
}

<?php

namespace App\Support\Template;

use App\Support\Benda;

/**
 * Template membilang benda: menghitung banyak benda pada gambar.
 *
 * Soal paling dasar di SD, dan yang paling melelahkan dibuat manual — tiap
 * butir perlu gambar dengan jumlah benda yang berbeda. Di sini guru cukup
 * mengganti satu angka.
 *
 * Benda ditata sepuluh-sepuluh per baris begitu jumlahnya melewati dua belas,
 * mengikuti cara membilang yang diajarkan di kelas: satu baris penuh berarti
 * satu puluhan.
 */
class MembilangBenda extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'banyak' => 'Berapa banyak benda',
        'puluhan' => 'Berapa puluhan dan satuan',
    ];

    public static function kode(): string
    {
        return 'membilang-benda';
    }

    public static function nama(): string
    {
        return 'Membilang Benda';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Hitung banyak benda bergambar (apel, bintang, bunga, mobil) sampai 100.';
    }

    public static function ikon(): string
    {
        return 'bi-hash';
    }

    public static function kataKunci(): array
    {
        return ['membilang', 'menghitung', 'banyak benda', 'bilangan', 'puluhan', 'satuan', 'sd', 'kelas 1'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'banyak'),
            self::kolomAngka('banyak', 'Banyak benda', 24, 1, 100, ['langkah' => 1]),
            self::kolomBenda(),
            self::kolomWarna('merah'),
        ];
    }

    protected function susun(array $isi): array
    {
        $banyak = (int) $isi['banyak'];
        $benda = $isi['benda'];
        $nama = Benda::sebutan($benda);
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        $svg = self::svg($banyak, $benda, $warnaIsi, $garis);
        $keterangan = "Gambar {$banyak} buah {$nama}.";

        if (($isi['tanya'] ?? 'banyak') === 'puluhan') {
            $puluhan = intdiv($banyak, 10);
            $satuan = $banyak % 10;
            $jawab = fn (int $p, int $s) => "{$p} puluhan {$s} satuan";

            return [
                'judul' => 'Membilang '.$banyak.' '.$nama,
                'teks' => "Perhatikan gambar {$nama} berikut. Banyaknya {$nama} itu ada berapa puluhan dan berapa satuan?",
                'pernyataan' => "Perhatikan gambar {$nama} berikut. Banyaknya {$nama} itu %s.",
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => $this->pilihanPuluhan($puluhan, $satuan),
                'kata_kunci' => $jawab($puluhan, $satuan),
                'pembahasan' => "Satu baris penuh berisi 10 {$nama}. Ada {$puluhan} baris penuh "
                    ."dan {$satuan} sisanya, jadi {$banyak} = {$puluhan} puluhan {$satuan} satuan.",
            ];
        }

        return [
            'judul' => 'Membilang '.$banyak.' '.$nama,
            'teks' => "Perhatikan gambar berikut. Ada berapa banyak {$nama} pada gambar itu?",
            'pernyataan' => "Perhatikan gambar berikut. Banyak {$nama} pada gambar itu ada %s.",
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) $n, 'urut' => $n],
                array_merge([$banyak], array_map(
                    'intval',
                    // Keliru membilang biasanya meleset satu dua, atau
                    // tertukar puluhannya pada bilangan dua angka.
                    self::saring($banyak, [$banyak + 1, $banyak - 1, $banyak + 2, $banyak - 2, $banyak + 10], 0),
                )),
            ),
            'kata_kunci' => (string) $banyak,
            'pembahasan' => "Dihitung satu per satu, banyak {$nama} pada gambar ada {$banyak}.",
        ];
    }

    /**
     * Pengecoh nilai tempat: puluhan dan satuan tertukar — kekeliruan khas
     * pada bilangan dua angka — serta meleset satu baris atau satu benda.
     *
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function pilihanPuluhan(int $puluhan, int $satuan): array
    {
        $kandidat = [
            [$puluhan, $satuan],
            [$satuan, $puluhan],
            [$puluhan + 1, $satuan],
            [$puluhan, $satuan + 1],
            [max(0, $puluhan - 1), $satuan],
        ];

        $dipakai = [];
        $pilihan = [];

        foreach ($kandidat as [$p, $s]) {
            $teks = "{$p} puluhan {$s} satuan";

            if ($p < 0 || $s < 0 || $s > 9 || isset($dipakai[$teks])) {
                continue;
            }

            $dipakai[$teks] = true;
            $pilihan[] = ['teks' => $teks, 'urut' => $p * 10 + $s];
        }

        return $pilihan;
    }

    public static function svg(int $banyak, string $benda, string $isi, string $garis): string
    {
        [$kanvas] = Benda::kanvas($banyak, $benda, $isi, $garis);

        return $kanvas->render();
    }
}

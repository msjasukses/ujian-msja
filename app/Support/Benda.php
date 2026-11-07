<?php

namespace App\Support;

/**
 * Gambar benda sehari-hari untuk soal berhitung tingkat dasar.
 *
 * Soal SD menghitung apel, bintang, dan mobil-mobilan, bukan bilangan
 * telanjang. Bendanya dikumpulkan di sini supaya seluruh template yang
 * memerlukan — membilang, penjumlahan bergambar, pembagian, perkalian
 * susunan, perbandingan — memakai gambar yang sama persis, dan menambah
 * satu benda baru cukup sekali untuk semuanya.
 *
 * Semuanya dirangkai dari bentuk dasar kanvas (lingkaran, elips, kotak,
 * poligon), bukan dari jalur bezier, supaya ikut terskala dengan benar pada
 * ukuran berapa pun tanpa perhitungan tambahan.
 */
class Benda
{
    /** @var array<string, string> */
    public const JENIS = [
        'apel' => 'Apel',
        'bintang' => 'Bintang',
        'bunga' => 'Bunga',
        'mobil' => 'Mobil',
        'bola' => 'Bola',
        'pensil' => 'Pensil',
        'buku' => 'Buku',
        'permen' => 'Permen',
        'ikan' => 'Ikan',
        'daun' => 'Daun',
        'kotak' => 'Kotak',
        'lingkaran' => 'Lingkaran',
    ];

    /**
     * Gambar satu benda berpusat di $pusat dengan jari-jari $r dalam satuan
     * kanvas. Semua benda dibuat muat di dalam lingkaran berjari-jari $r itu,
     * sehingga pemanggil bisa menatanya berderet tanpa perlu tahu bentuknya.
     */
    public static function gambar(Kanvas $k, array $pusat, float $r, string $jenis, string $isi, string $garis): void
    {
        [$x, $y] = $pusat;

        match ($jenis) {
            'apel' => self::apel($k, $x, $y, $r, $isi, $garis),
            'bintang' => $k->poligon(self::titikBintang($x, $y, $r), $isi, $garis, 1.5),
            'bunga' => self::bunga($k, $x, $y, $r, $isi, $garis),
            'mobil' => self::mobil($k, $x, $y, $r, $isi, $garis),
            'bola' => self::bola($k, $x, $y, $r, $isi, $garis),
            'pensil' => self::pensil($k, $x, $y, $r, $isi, $garis),
            'buku' => self::buku($k, $x, $y, $r, $isi, $garis),
            'permen' => self::permen($k, $x, $y, $r, $isi, $garis),
            'ikan' => self::ikan($k, $x, $y, $r, $isi, $garis),
            'daun' => self::daun($k, $x, $y, $r, $isi, $garis),
            'kotak' => $k->persegi([$x - $r * 0.8, $y - $r * 0.8], $r * 1.6, $r * 1.6, $isi, $garis, 1.5),
            default => $k->lingkaran([$x, $y], $r * 0.85, $isi, $garis, 1.5),
        };
    }

    /** Sebutan benda dalam kalimat soal, mis. "3 buah apel". */
    public static function sebutan(string $jenis): string
    {
        return mb_strtolower(self::JENIS[$jenis] ?? $jenis);
    }

    /**
     * Titik pusat sejumlah benda yang ditata baris demi baris, dari kiri atas.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function kisi(int $banyak, int $perBaris, array $pangkal, float $jarak): array
    {
        $titik = [];

        for ($i = 0; $i < $banyak; $i++) {
            $titik[] = [
                $pangkal[0] + ($i % $perBaris) * $jarak,
                $pangkal[1] + intdiv($i, $perBaris) * $jarak,
            ];
        }

        return $titik;
    }

    /**
     * Kanvas berisi sejumlah benda yang ditata rapi dan pas memenuhi ruang.
     *
     * Tiap baris diletakkan di tengah, termasuk baris terakhir yang biasanya
     * lebih pendek — kalau dibiarkan rata kiri, kumpulan benda terlihat
     * seperti dua kelompok yang berbeda dan siswa ikut salah membilang.
     *
     * @return array{0: Kanvas, 1: array<int, array{0: float, 1: float}>, 2: float}
     *                                                                              kanvas, titik pusat tiap
     *                                                                              benda, dan jari-jarinya
     */
    public static function kanvas(int $banyak, string $jenis, string $isi, string $garis, float $atas = 28, float $bawah = 28): array
    {
        $banyak = max(1, $banyak);
        $perBaris = self::perBaris($banyak);
        $baris = (int) ceil($banyak / $perBaris);

        $kanvas = new Kanvas;

        $jarak = min(
            (Kanvas::LEBAR - 56) / $perBaris,
            (Kanvas::TINGGI - $atas - $bawah) / $baris,
            68,
        );
        $r = $jarak * 0.38;
        $pangkalY = $atas + (Kanvas::TINGGI - $atas - $bawah - $baris * $jarak) / 2 + $jarak / 2;

        $titik = [];

        for ($i = 0; $i < $banyak; $i++) {
            $b = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, $banyak - $b * $perBaris);
            $x = (Kanvas::LEBAR - ($isiBaris - 1) * $jarak) / 2 + ($i % $perBaris) * $jarak;
            $y = $pangkalY + $b * $jarak;

            $titik[] = [$x, $y];
            self::gambar($kanvas, [$x, $y], $r, $jenis, $isi, $garis);
        }

        return [$kanvas, $titik, $r];
    }

    /**
     * Banyak benda per baris yang enak dilihat: barisnya tidak terlalu
     * panjang, dan sisa baris terakhir tidak tinggal satu dua benda.
     */
    public static function perBaris(int $banyak): int
    {
        return match (true) {
            $banyak <= 5 => $banyak,
            $banyak <= 12 => (int) ceil($banyak / 2),
            $banyak <= 30 => 10,
            default => 10,
        };
    }

    // =====================================================================

    protected static function apel(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->garis([$x, $y - $r * 0.5], [$x, $y - $r], $garis, 2)
            ->elips([$x + $r * 0.35, $y - $r * 0.85], $r * 0.3, $r * 0.16, $isi, $garis, 1.2)
            ->lingkaran([$x - $r * 0.32, $y + $r * 0.1], $r * 0.56, $isi, $garis, 1.5)
            ->lingkaran([$x + $r * 0.32, $y + $r * 0.1], $r * 0.56, $isi, $garis, 1.5);
    }

    /** @return array<int, array{0: float, 1: float}> */
    protected static function titikBintang(float $x, float $y, float $r): array
    {
        $titik = [];

        for ($i = 0; $i < 10; $i++) {
            $jari = $i % 2 === 0 ? $r : $r * 0.42;
            $sudut = deg2rad($i * 36 - 90);
            $titik[] = [$x + $jari * cos($sudut), $y + $jari * sin($sudut)];
        }

        return $titik;
    }

    protected static function bunga(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        for ($i = 0; $i < 5; $i++) {
            $sudut = deg2rad($i * 72 - 90);
            $k->lingkaran([$x + $r * 0.55 * cos($sudut), $y + $r * 0.55 * sin($sudut)], $r * 0.42, $isi, $garis, 1.2);
        }

        $k->lingkaran([$x, $y], $r * 0.26, '#ffffff', $garis, 1.2);
    }

    protected static function mobil(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->persegi([$x - $r * 0.45, $y - $r * 0.75], $r * 0.9, $r * 0.45, $isi, $garis, 1.3)
            ->persegi([$x - $r, $y - $r * 0.3], $r * 2, $r * 0.6, $isi, $garis, 1.5)
            ->lingkaran([$x - $r * 0.55, $y + $r * 0.4], $r * 0.26, $garis, $garis, 1)
            ->lingkaran([$x + $r * 0.55, $y + $r * 0.4], $r * 0.26, $garis, $garis, 1);
    }

    protected static function bola(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->lingkaran([$x, $y], $r * 0.85, $isi, $garis, 1.5)
            ->elips([$x, $y], $r * 0.85, $r * 0.32, 'none', $garis, 1)
            ->elips([$x, $y], $r * 0.32, $r * 0.85, 'none', $garis, 1);
    }

    protected static function pensil(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->persegi([$x - $r * 0.3, $y - $r * 0.9], $r * 0.6, $r * 1.3, $isi, $garis, 1.3)
            ->poligon([
                [$x - $r * 0.3, $y + $r * 0.4],
                [$x + $r * 0.3, $y + $r * 0.4],
                [$x, $y + $r],
            ], '#ffffff', $garis, 1.3);
    }

    protected static function buku(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->persegi([$x - $r * 0.7, $y - $r * 0.85], $r * 1.4, $r * 1.7, $isi, $garis, 1.5)
            ->garis([$x - $r * 0.4, $y - $r * 0.85], [$x - $r * 0.4, $y + $r * 0.85], $garis, 1.2);
    }

    protected static function permen(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->poligon([[$x - $r, $y - $r * 0.5], [$x - $r * 0.4, $y], [$x - $r, $y + $r * 0.5]], $isi, $garis, 1.2)
            ->poligon([[$x + $r, $y - $r * 0.5], [$x + $r * 0.4, $y], [$x + $r, $y + $r * 0.5]], $isi, $garis, 1.2)
            ->lingkaran([$x, $y], $r * 0.5, $isi, $garis, 1.5);
    }

    protected static function ikan(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->poligon([
            [$x + $r * 0.45, $y],
            [$x + $r, $y - $r * 0.5],
            [$x + $r, $y + $r * 0.5],
        ], $isi, $garis, 1.2)
            ->elips([$x - $r * 0.15, $y], $r * 0.7, $r * 0.45, $isi, $garis, 1.5)
            ->lingkaran([$x - $r * 0.5, $y - $r * 0.1], $r * 0.08, $garis, $garis, 1);
    }

    protected static function daun(Kanvas $k, float $x, float $y, float $r, string $isi, string $garis): void
    {
        $k->elips([$x, $y], $r * 0.5, $r * 0.9, $isi, $garis, 1.5)
            ->garis([$x, $y - $r * 0.9], [$x, $y + $r * 0.9], $garis, 1.2);
    }
}

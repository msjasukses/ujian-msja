<?php

namespace App\Support;

/**
 * Penulisan angka bergaya Indonesia untuk isi soal.
 *
 * Dikumpulkan di satu tempat supaya gambar, teks pertanyaan, opsi, dan
 * pembahasan tidak pernah menulis bilangan yang sama dengan dua cara —
 * "12,5 cm" di gambar sementara "12.50 cm" di kunci jawabannya.
 */
class Angka
{
    /**
     * Koma sebagai pemisah desimal, titik sebagai pemisah ribuan, dan
     * desimal hanya ditulis bila memang ada.
     */
    public static function id(float $x, int $desimal = 2): string
    {
        $bulat = abs($x - round($x)) < 0.5 / 10 ** $desimal;

        return number_format($x, $bulat ? 0 : $desimal, ',', '.');
    }

    /**
     * Angka dengan desimal seperlunya: 4,5 ditulis "4,5" bukan "4,500",
     * sedangkan 0,056 tetap utuh.
     *
     * Dipakai untuk hasil hitungan yang ketelitiannya berbeda-beda dalam
     * satu template — mol, massa, dan volume pada reaksi yang sama. Dengan
     * desimal tetap, "4,500 gram" mudah terbaca sebagai empat ribu lima
     * ratus gram, sebab titik dan koma bertukar arti di sini.
     */
    public static function ringkas(float $x, int $maks = 3): string
    {
        for ($desimal = 0; $desimal < $maks; $desimal++) {
            if (abs($x - round($x, $desimal)) < 0.5 / 10 ** ($maks + 1)) {
                return number_format($x, $desimal, ',', '.');
            }
        }

        return number_format($x, $maks, ',', '.');
    }

    /** Angka beserta satuannya, mis. "40 cm²". */
    public static function dengan(float $x, string $satuan, int $desimal = 2): string
    {
        return self::id($x, $desimal).($satuan === '' ? '' : ' '.$satuan);
    }

    /** Satuan luas dan volume ditulis berpangkat: cm², cm³. */
    public static function pangkat(string $satuan, int $pangkat): string
    {
        return $satuan.($pangkat === 2 ? '²' : ($pangkat === 3 ? '³' : ''));
    }

    /** Pukul bergaya Indonesia, mis. "07.45". */
    public static function pukul(int $jam, int $menit): string
    {
        return sprintf('%02d.%02d', $jam, $menit);
    }
}

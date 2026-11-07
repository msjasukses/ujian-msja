<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template vektor: panah pada kertas berpetak.
 *
 * Vektor diperkenalkan sebagai panah yang punya arah dan panjang, bukan
 * sebagai pasangan angka. Karena itu gambarnya berupa panah di atas petak,
 * dan komponennya dibaca dari banyaknya petak yang dilalui — persis cara
 * yang dipakai di kelas sebelum rumusnya diperkenalkan.
 */
class Vektor extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'komponen' => 'Komponen vektor',
        'besar' => 'Besar (panjang) vektor',
        'jumlah' => 'Jumlah dua vektor',
    ];

    public static function kode(): string
    {
        return 'vektor';
    }

    public static function nama(): string
    {
        return 'Vektor';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Vektor sebagai panah di kertas berpetak: komponen, besar vektor, '
            .'dan penjumlahan dua vektor.';
    }

    public static function ikon(): string
    {
        return 'bi-arrow-up-right';
    }

    public static function kataKunci(): array
    {
        return ['vektor', 'komponen', 'resultan', 'panah', 'besar vektor', 'arah'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'komponen'),
            self::kolomAngka('ax', 'Vektor a — komponen x', 4, -8, 8, ['langkah' => 1]),
            self::kolomAngka('ay', 'Vektor a — komponen y', 3, -8, 8, ['langkah' => 1]),
            self::kolomAngka('bx', 'Vektor b — komponen x', -2, -8, 8, [
                'langkah' => 1, 'tampil' => ['tanya' => ['jumlah']],
            ]),
            self::kolomAngka('by', 'Vektor b — komponen y', 2, -8, 8, [
                'langkah' => 1, 'tampil' => ['tanya' => ['jumlah']],
            ]),
            self::kolomWarna('merah'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if ((int) ($isi['ax'] ?? 0) === 0 && (int) ($isi['ay'] ?? 0) === 0) {
            return ['ax' => 'Vektor nol tidak bisa digambar sebagai panah.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');
        $ax = (int) $isi['ax'];
        $ay = (int) $isi['ay'];

        $tulis = fn (int $x, int $y) => "({$x}, {$y})";

        if (($isi['tanya'] ?? 'komponen') === 'jumlah') {
            $bx = (int) $isi['bx'];
            $by = (int) $isi['by'];
            $benar = $tulis($ax + $bx, $ay + $by);

            return [
                'judul' => 'Resultan '.$benar,
                'teks' => 'Perhatikan gambar dua vektor berikut. Berapakah hasil penjumlahan a + b?',
                'pernyataan' => 'Perhatikan gambar dua vektor berikut. Hasil penjumlahan a + b adalah %s.',
                'svg' => self::svg([
                    ['x' => $ax, 'y' => $ay, 'nama' => 'a', 'dari' => [0, 0]],
                    ['x' => $bx, 'y' => $by, 'nama' => 'b', 'dari' => [$ax, $ay]],
                ], $warnaIsi, $garis),
                'keterangan' => "Vektor a {$tulis($ax, $ay)} dilanjutkan vektor b {$tulis($bx, $by)}.",
                'pilihan' => array_map(
                    fn ($t) => ['teks' => $t, 'urut' => $t],
                    array_values(array_unique([
                        $benar,
                        // Dikurangkan, komponennya tertukar, hanya salah
                        // satu komponen yang dijumlahkan, atau dikalikan.
                        $tulis($ax - $bx, $ay - $by),
                        $tulis($ax + $by, $ay + $bx),
                        $tulis($ax + $bx, $ay - $by),
                        $tulis($ax * $bx, $ay * $by),
                    ])),
                ),
                'kata_kunci' => $benar,
                'pembahasan' => 'Komponen dijumlahkan satu per satu: ('.$ax.' + '.$bx.', '.$ay.' + '.$by
                    .') = '.$benar.'.',
            ];
        }

        $gambar = self::svg([['x' => $ax, 'y' => $ay, 'nama' => 'a', 'dari' => [0, 0]]], $warnaIsi, $garis);
        $keterangan = 'Panah vektor a dari titik asal sejauh '.$ax.' petak mendatar dan '.$ay.' petak tegak.';

        if (($isi['tanya'] ?? '') === 'besar') {
            $besar = round(hypot($ax, $ay), 2);

            return [
                'judul' => '|a| = '.Angka::id($besar),
                'teks' => 'Perhatikan gambar vektor berikut. Berapakah besar (panjang) vektor a?',
                'pernyataan' => 'Perhatikan gambar vektor berikut. Besar vektor a adalah %s.',
                'svg' => $gambar,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                    array_merge([$besar], self::saring($besar, [
                        // Menjumlahkan komponennya tanpa dikuadratkan adalah
                        // kekeliruan yang sama dengan pada Pythagoras.
                        abs($ax) + abs($ay), abs(abs($ax) - abs($ay)), $ax * $ax + $ay * $ay, $besar + 1,
                    ])),
                ),
                'kata_kunci' => Angka::id($besar),
                'pembahasan' => '|a| = √(x² + y²) = √('.($ax * $ax).' + '.($ay * $ay).') = √'
                    .($ax * $ax + $ay * $ay).' = '.Angka::id($besar).'.',
            ];
        }

        $benar = $tulis($ax, $ay);

        return [
            'judul' => 'Vektor '.$benar,
            'teks' => 'Perhatikan gambar vektor berikut. Berapakah komponen vektor a?',
            'pernyataan' => 'Perhatikan gambar vektor berikut. Komponen vektor a adalah %s.',
            'svg' => $gambar,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_values(array_unique([
                    $benar,
                    $tulis($ay, $ax),
                    $tulis(-$ax, $ay),
                    $tulis($ax, -$ay),
                ])),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Panahnya bergeser '.$ax.' petak mendatar dan '.$ay
                .' petak tegak, jadi komponennya '.$benar.'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** @param  array<int, array{x: int, y: int, nama: string, dari: array}>  $vektor */
    public static function svg(array $vektor, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $batas = 6;

        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $sel = min((Kanvas::LEBAR - 60) / (2 * $batas), (Kanvas::TINGGI - 40) / (2 * $batas));
        $px = fn (float $x, float $y) => [$pusat[0] + $x * $sel, $pusat[1] - $y * $sel];

        for ($i = -$batas; $i <= $batas; $i++) {
            $kanvas->garis($px($i, -$batas), $px($i, $batas), '#dfe3e7', 1)
                ->garis($px(-$batas, $i), $px($batas, $i), '#dfe3e7', 1);
        }

        $kanvas->garis($px(-$batas, 0), $px($batas, 0), Kanvas::HITAM, 2)
            ->garis($px(0, -$batas), $px(0, $batas), Kanvas::HITAM, 2);

        $warnaKe = [$garis, '#2e7d32'];

        foreach ($vektor as $i => $v) {
            $warna = $warnaKe[$i % count($warnaKe)];
            $dari = $px($v['dari'][0], $v['dari'][1]);
            $ke = $px($v['dari'][0] + $v['x'], $v['dari'][1] + $v['y']);

            $kanvas->garis($dari, $ke, $warna, 3);

            $arah = Kanvas::searah([$ke[0] - $dari[0], $ke[1] - $dari[1]]);
            $sisi = [-$arah[1], $arah[0]];
            $pangkal = [$ke[0] - $arah[0] * 14, $ke[1] - $arah[1] * 14];

            $kanvas->poligon([
                $ke,
                [$pangkal[0] + $sisi[0] * 7, $pangkal[1] + $sisi[1] * 7],
                [$pangkal[0] - $sisi[0] * 7, $pangkal[1] - $sisi[1] * 7],
            ], $warna, $warna, 1);

            $kanvas->teksPx([
                ($dari[0] + $ke[0]) / 2 - $arah[1] * 16,
                ($dari[1] + $ke[1]) / 2 + $arah[0] * 16 + 5,
            ], $v['nama'], ['ukuran' => 18, 'tebal' => true, 'warna' => $warna]);
        }

        return $kanvas->render();
    }
}

<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template kongruen & kesebangunan.
 *
 * Dua bangun digambar berdampingan dengan perbandingan sisi yang sebenarnya,
 * sehingga "sebangun" terlihat sebagai bentuk yang sama tetapi berbeda
 * ukuran. Soal bayangan tiang dipakai sebagai penerapannya: dua segitiga
 * siku-siku yang sebangun, satu dibentuk tiang dan bayangannya, satu lagi
 * oleh orang dan bayangannya.
 */
class Kesebangunan extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'sisi' => 'Panjang sisi yang belum diketahui',
        'faktor' => 'Faktor skala kedua bangun',
        'tiang' => 'Tinggi tiang dari panjang bayangan',
    ];

    public static function kode(): string
    {
        return 'kesebangunan';
    }

    public static function nama(): string
    {
        return 'Kongruen & Kesebangunan';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Dua bangun sebangun berdampingan: sisi yang belum diketahui, faktor '
            .'skala, serta penerapannya pada bayangan tiang dan orang.';
    }

    public static function ikon(): string
    {
        return 'bi-bounding-box-circles';
    }

    public static function kataKunci(): array
    {
        return ['kongruen', 'sebangun', 'kesebangunan', 'faktor skala', 'sisi bersesuaian', 'bayangan'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'sisi'),
            self::kolomAngka('panjang_1', 'Bangun 1 — panjang', 8, 1, 500, [
                'tampil' => ['tanya' => ['sisi', 'faktor']],
            ]),
            self::kolomAngka('lebar_1', 'Bangun 1 — lebar', 6, 1, 500, [
                'tampil' => ['tanya' => ['sisi', 'faktor']],
            ]),
            self::kolomAngka('panjang_2', 'Bangun 2 — panjang', 12, 1, 500, [
                'tampil' => ['tanya' => ['sisi', 'faktor']],
            ]),
            self::kolomAngka('tinggi_orang', 'Tinggi orang (m)', 1.5, 0.1, 10, [
                'tampil' => ['tanya' => ['tiang']],
            ]),
            self::kolomAngka('bayangan_orang', 'Bayangan orang (m)', 2, 0.1, 50, [
                'tampil' => ['tanya' => ['tiang']],
            ]),
            self::kolomAngka('bayangan_tiang', 'Bayangan tiang (m)', 8, 0.1, 200, [
                'tampil' => ['tanya' => ['tiang']],
            ]),
            self::kolomWarna('jingga'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        if (($isi['tanya'] ?? 'sisi') === 'tiang') {
            return $this->tiang(
                (float) $isi['tinggi_orang'],
                (float) $isi['bayangan_orang'],
                (float) $isi['bayangan_tiang'],
                $warnaIsi,
                $garis,
            );
        }

        $p1 = (float) $isi['panjang_1'];
        $l1 = (float) $isi['lebar_1'];
        $p2 = (float) $isi['panjang_2'];
        $faktor = round($p2 / $p1, 2);
        $l2 = round($l1 * $faktor, 2);

        $svg = self::svgDuaBangun($p1, $l1, $p2, $l2, $warnaIsi, $garis);
        $keterangan = 'Dua persegi panjang sebangun: '.Angka::id($p1).' × '.Angka::id($l1)
            .' dan '.Angka::id($p2).' × ?';

        if (($isi['tanya'] ?? '') === 'faktor') {
            return [
                'judul' => 'Faktor skala '.Angka::id($faktor),
                'teks' => 'Perhatikan gambar dua bangun sebangun berikut. Berapakah faktor skala '
                    .'dari bangun pertama ke bangun kedua?',
                'pernyataan' => 'Perhatikan gambar dua bangun sebangun berikut. Faktor skala dari bangun '
                    .'pertama ke bangun kedua adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                    array_merge([$faktor], self::saring($faktor, [
                        round($p1 / $p2, 2), round($p2 - $p1, 2), $faktor * 2, $faktor + 1,
                    ])),
                ),
                'kata_kunci' => Angka::id($faktor),
                'pembahasan' => 'Faktor skala = sisi bersesuaian bangun kedua ÷ bangun pertama = '
                    .Angka::id($p2).' ÷ '.Angka::id($p1).' = '.Angka::id($faktor).'.',
            ];
        }

        return [
            'judul' => 'Sisi yang dicari '.Angka::id($l2),
            'teks' => 'Perhatikan gambar dua bangun sebangun berikut. Berapakah panjang sisi '
                .'yang belum diketahui pada bangun kedua?',
            'pernyataan' => 'Perhatikan gambar dua bangun sebangun berikut. Panjang sisi yang belum '
                .'diketahui pada bangun kedua adalah %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, 'cm'), 'urut' => $n],
                array_merge([$l2], self::saring($l2, [
                    // Menambahkan selisih, bukan mengalikan faktor skala,
                    // adalah kekeliruan pokok pada kesebangunan.
                    round($l1 + ($p2 - $p1), 2), round($l1 * $p2 / $l1, 2), round($l1 / $faktor, 2), $l1,
                ])),
            ),
            'kata_kunci' => Angka::id($l2),
            'pembahasan' => 'Sisi yang bersesuaian sebanding: '.Angka::id($p1).' : '.Angka::id($p2)
                .' = '.Angka::id($l1).' : x<br>x = '.Angka::id($l1).' × '.Angka::id($p2).' ÷ '
                .Angka::id($p1).' = '.Angka::dengan($l2, 'cm').'.',
        ];
    }

    protected function tiang(float $tinggiOrang, float $bayanganOrang, float $bayanganTiang, string $isi, string $garis): array
    {
        $tinggi = round($tinggiOrang / $bayanganOrang * $bayanganTiang, 2);

        return [
            'judul' => 'Tinggi tiang '.Angka::dengan($tinggi, 'm'),
            'teks' => 'Perhatikan gambar berikut. Pada saat yang sama, orang setinggi '
                .Angka::dengan($tinggiOrang, 'm').' berbayangan '.Angka::dengan($bayanganOrang, 'm')
                .', sedangkan bayangan tiang '.Angka::dengan($bayanganTiang, 'm')
                .'. Berapakah tinggi tiang itu?',
            'pernyataan' => 'Perhatikan gambar berikut. Orang setinggi '.Angka::dengan($tinggiOrang, 'm')
                .' berbayangan '.Angka::dengan($bayanganOrang, 'm').' dan bayangan tiang '
                .Angka::dengan($bayanganTiang, 'm').', sehingga tinggi tiang itu %s.',
            'svg' => self::svgTiang($tinggiOrang, $bayanganOrang, $tinggi, $bayanganTiang, $isi, $garis),
            'keterangan' => 'Tiang dan orang beserta bayangannya pada saat yang sama.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, 'm'), 'urut' => $n],
                array_merge([$tinggi], self::saring($tinggi, [
                    round($bayanganTiang / $bayanganOrang * $tinggiOrang + $tinggiOrang, 2),
                    round($bayanganOrang / $tinggiOrang * $bayanganTiang, 2),
                    $bayanganTiang, $tinggi + 1,
                ])),
            ),
            'kata_kunci' => Angka::id($tinggi),
            'pembahasan' => 'Kedua segitiga sebangun, jadi tinggi : bayangan sama perbandingannya.<br>'
                .Angka::id($tinggiOrang).' : '.Angka::id($bayanganOrang).' = t : '.Angka::id($bayanganTiang)
                .'<br>t = '.Angka::id($tinggiOrang).' × '.Angka::id($bayanganTiang).' ÷ '
                .Angka::id($bayanganOrang).' = '.Angka::dengan($tinggi, 'm').'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgDuaBangun(float $p1, float $l1, float $p2, float $l2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        // Keduanya diskalakan dengan faktor yang sama supaya perbandingan
        // ukurannya di layar jujur terhadap angkanya.
        $skala = min(180 / max($p1, $p2), 150 / max($l1, $l2));

        $gambar = function (float $kiri, float $p, float $l, string $labelP, string $labelL) use ($kanvas, $skala, $isi, $garis) {
            $lebar = $p * $skala;
            $tinggi = $l * $skala;
            $atas = 180 - $tinggi / 2;

            $kanvas->tambah('<rect x="'.round($kiri, 1).'" y="'.round($atas, 1).'" width="'.round($lebar, 1)
                .'" height="'.round($tinggi, 1).'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>')
                ->teksPx([$kiri + $lebar / 2, $atas + $tinggi + 24], $labelP, ['ukuran' => 14])
                ->teksPx([$kiri - 10, $atas + $tinggi / 2 + 5], $labelL, ['anchor' => 'end', 'ukuran' => 14]);
        };

        $gambar(50, $p1, $l1, Angka::id($p1), Angka::id($l1));
        $gambar(270, $p2, $l2, Angka::id($p2), '?');

        return $kanvas->render();
    }

    public static function svgTiang(float $tinggiOrang, float $bayanganOrang, float $tinggiTiang, float $bayanganTiang, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $tanah = 250;
        $skala = min(170 / $tinggiTiang, 190 / ($bayanganTiang + $bayanganOrang + 1));

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 3);

        // Orang di kiri, tiang di kanan; bayangan keduanya jatuh ke kanan.
        $xOrang = 60;
        $tOrang = $tinggiOrang * $skala;
        $bOrang = $bayanganOrang * $skala;

        $kanvas->garis([$xOrang, $tanah], [$xOrang, $tanah - $tOrang], $garis, 6)
            ->garis([$xOrang, $tanah - $tOrang], [$xOrang + $bOrang, $tanah], Kanvas::HITAM, 1.5, true)
            ->garis([$xOrang, $tanah], [$xOrang + $bOrang, $tanah], '#8a929a', 5)
            ->teksPx([$xOrang - 10, $tanah - $tOrang / 2], Angka::id($tinggiOrang).' m', [
                'anchor' => 'end', 'ukuran' => 13,
            ])
            ->teksPx([$xOrang + $bOrang / 2, $tanah + 20], Angka::id($bayanganOrang).' m', ['ukuran' => 13]);

        $xTiang = 240;
        $tTiang = $tinggiTiang * $skala;
        $bTiang = $bayanganTiang * $skala;

        $kanvas->tambah('<rect x="'.($xTiang - 5).'" y="'.round($tanah - $tTiang, 1).'" width="10" height="'
            .round($tTiang, 1).'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
            ->garis([$xTiang, $tanah - $tTiang], [$xTiang + $bTiang, $tanah], Kanvas::HITAM, 1.5, true)
            ->garis([$xTiang, $tanah], [$xTiang + $bTiang, $tanah], '#8a929a', 5)
            ->teksPx([$xTiang - 12, $tanah - $tTiang / 2], '?', [
                'anchor' => 'end', 'ukuran' => 20, 'tebal' => true,
            ])
            ->teksPx([$xTiang + $bTiang / 2, $tanah + 20], Angka::id($bayanganTiang).' m', ['ukuran' => 13]);

        return $kanvas->render();
    }
}

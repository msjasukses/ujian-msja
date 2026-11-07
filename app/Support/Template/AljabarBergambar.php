<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template aljabar bergambar: ubin aljabar dan timbangan seimbang.
 *
 * Dua alat peraga yang dipakai sebelum lambang aljabar diperkenalkan. Ubin
 * memperlihatkan bentuk aljabar sebagai kumpulan benda — satu ubin panjang
 * adalah x, satu ubin kecil adalah 1 — sedangkan timbangan memperlihatkan
 * persamaan sebagai keseimbangan yang harus dijaga ketika kedua sisi
 * dikurangi atau dibagi sama banyak.
 */
class AljabarBergambar extends Template
{
    /** @var array<string, string> */
    public const MODE = [
        'ubin' => 'Ubin aljabar',
        'timbangan' => 'Timbangan seimbang',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'bentuk' => 'Bentuk aljabar yang digambarkan',
        'hasil' => 'Hasil penjumlahan dua kelompok ubin',
        'nilai_x' => 'Nilai x',
    ];

    public static function kode(): string
    {
        return 'aljabar-bergambar';
    }

    public static function nama(): string
    {
        return 'Aljabar Bergambar';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Ubin aljabar untuk membaca dan menjumlahkan bentuk aljabar, serta '
            .'timbangan seimbang untuk persamaan linear satu variabel.';
    }

    public static function ikon(): string
    {
        return 'bi-infinity';
    }

    public static function kataKunci(): array
    {
        return ['aljabar', 'ubin', 'variabel', 'suku', 'persamaan linear', 'timbangan', 'nilai x'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'mode',
                'label' => 'Alat peraga',
                'jenis' => 'pilihan',
                'pilihan' => self::MODE,
                'awal' => 'ubin',
            ],
            self::kolomAngka('x1', 'Kelompok 1 — banyak ubin x', 2, 0, 8, [
                'langkah' => 1, 'tampil' => ['mode' => ['ubin']],
            ]),
            self::kolomAngka('c1', 'Kelompok 1 — banyak ubin satuan', 3, 0, 12, [
                'langkah' => 1, 'tampil' => ['mode' => ['ubin']],
            ]),
            self::kolomAngka('x2', 'Kelompok 2 — banyak ubin x', 1, 0, 8, [
                'langkah' => 1, 'tampil' => ['mode' => ['ubin']],
            ]),
            self::kolomAngka('c2', 'Kelompok 2 — banyak ubin satuan', 4, 0, 12, [
                'langkah' => 1, 'tampil' => ['mode' => ['ubin']],
            ]),
            self::kolomAngka('koefisien', 'Banyak kantong x di kiri', 3, 1, 8, [
                'langkah' => 1, 'tampil' => ['mode' => ['timbangan']],
            ]),
            self::kolomAngka('tambahan', 'Banyak beban satuan di kiri', 2, 0, 15, [
                'langkah' => 1, 'tampil' => ['mode' => ['timbangan']],
            ]),
            self::kolomAngka('kanan', 'Banyak beban satuan di kanan', 14, 1, 60, [
                'langkah' => 1, 'tampil' => ['mode' => ['timbangan']],
            ]),
            self::kolomTanya(self::TANYA, 'bentuk'),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['mode'] ?? '') === 'timbangan') {
            $sisa = (int) ($isi['kanan'] ?? 0) - (int) ($isi['tambahan'] ?? 0);
            $koefisien = max(1, (int) ($isi['koefisien'] ?? 1));

            if ($sisa <= 0) {
                return ['kanan' => 'Beban di kanan harus lebih berat daripada beban satuan di kiri.'];
            }

            if ($sisa % $koefisien !== 0) {
                return ['kanan' => 'Agar nilai x bulat, selisih kedua sisi ('.$sisa
                    .') harus habis dibagi '.$koefisien.'.'];
            }

            return [];
        }

        if ((int) ($isi['x1'] ?? 0) + (int) ($isi['c1'] ?? 0) < 1) {
            return ['x1' => 'Kelompok pertama tidak boleh kosong.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        if (($isi['mode'] ?? 'ubin') === 'timbangan') {
            return $this->timbangan($isi, $warnaIsi, $garis);
        }

        $x1 = (int) $isi['x1'];
        $c1 = (int) $isi['c1'];
        $x2 = (int) $isi['x2'];
        $c2 = (int) $isi['c2'];

        $dua = ($isi['tanya'] ?? 'bentuk') === 'hasil';
        $svg = self::svgUbin($x1, $c1, $dua ? $x2 : null, $dua ? $c2 : null, $warnaIsi, $garis);
        $keterangan = 'Ubin panjang bernilai x dan ubin kecil bernilai 1.';

        if ($dua) {
            $benar = self::bentuk($x1 + $x2, $c1 + $c2);

            return [
                'judul' => $benar,
                'teks' => 'Perhatikan gambar dua kelompok ubin aljabar berikut. '
                    .'Bila digabungkan, bentuk aljabarnya menjadi ...',
                'pernyataan' => 'Perhatikan gambar dua kelompok ubin aljabar berikut. '
                    .'Bila digabungkan, bentuk aljabarnya menjadi %s.',
                'svg' => $svg,
                'keterangan' => $keterangan.' Kelompok 1: '.self::bentuk($x1, $c1)
                    .', kelompok 2: '.self::bentuk($x2, $c2).'.',
                'pilihan' => self::pilihanBentuk($x1 + $x2, $c1 + $c2, [
                    // Menjumlahkan ubin x dengan ubin satuan — "suku tak
                    // sejenis dijumlahkan" — adalah kekeliruan pokoknya.
                    [$x1 + $x2 + $c1 + $c2, 0], [$x1 * $x2, $c1 + $c2], [$x1 + $x2, $c1 * $c2],
                ]),
                'kata_kunci' => $benar,
                'pembahasan' => 'Ubin x dijumlahkan dengan ubin x, ubin satuan dengan ubin satuan: '
                    ."({$x1} + {$x2})x + ({$c1} + {$c2}) = {$benar}.",
            ];
        }

        $benar = self::bentuk($x1, $c1);

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar ubin aljabar berikut. Bentuk aljabar yang digambarkan adalah ...',
            'pernyataan' => 'Perhatikan gambar ubin aljabar berikut. Bentuk aljabar yang digambarkan adalah %s.',
            'svg' => $svg,
            'keterangan' => $keterangan." Ada {$x1} ubin x dan {$c1} ubin satuan.",
            'pilihan' => self::pilihanBentuk($x1, $c1, [[$c1, $x1], [$x1 + $c1, 0], [$x1, $c1 + 1]]),
            'kata_kunci' => $benar,
            'pembahasan' => "Ada {$x1} ubin panjang (bernilai x) dan {$c1} ubin kecil (bernilai 1), "
                ."jadi bentuknya {$benar}.",
        ];
    }

    protected function timbangan(array $isi, string $warnaIsi, string $garis): array
    {
        $a = (int) $isi['koefisien'];
        $b = (int) $isi['tambahan'];
        $c = (int) $isi['kanan'];
        $x = (int) (($c - $b) / $a);

        $persamaan = self::bentuk($a, $b).' = '.$c;

        return [
            'judul' => 'x = '.$x,
            'teks' => 'Perhatikan gambar timbangan berikut yang dalam keadaan seimbang. '
                .'Bila tiap kantong berisi x benda, berapakah nilai x?',
            'pernyataan' => 'Perhatikan gambar timbangan berikut yang dalam keadaan seimbang. '
                .'Bila tiap kantong berisi x benda, nilai x adalah %s.',
            'svg' => self::svgTimbangan($a, $b, $c, $warnaIsi, $garis),
            'keterangan' => "Timbangan seimbang: {$a} kantong dan {$b} beban satuan di kiri, "
                ."{$c} beban satuan di kanan.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
                array_merge([$x], self::saring($x, [
                    // Lupa mengurangi beban satuan dulu, atau lupa membagi
                    // dengan banyak kantong.
                    $c - $b, (int) ($c / $a), $c - $b - $a, $x + 1, $x - 1,
                ], 0)),
            ),
            'kata_kunci' => (string) $x,
            'pembahasan' => "Persamaannya {$persamaan}.<br>Kedua sisi dikurangi {$b}: {$a}x = ".($c - $b)
                .".<br>Kedua sisi dibagi {$a}: x = {$x}.",
        ];
    }

    /** Tulisan bentuk aljabar yang rapi: koefisien 1 dan suku nol tidak ditulis. */
    public static function bentuk(int $x, int $c): string
    {
        if ($x === 0) {
            return (string) $c;
        }

        $suku = $x === 1 ? 'x' : $x.'x';

        return $c === 0 ? $suku : $suku.' + '.$c;
    }

    /** @return array<int, array{teks: string, urut: string}> */
    protected static function pilihanBentuk(int $x, int $c, array $kandidat): array
    {
        $teks = array_map(fn ($p) => self::bentuk((int) $p[0], (int) $p[1]), array_merge([[$x, $c]], $kandidat));

        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_values(array_unique($teks)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgUbin(int $x1, int $c1, ?int $x2, ?int $c2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $dua = $x2 !== null;

        self::kelompok($kanvas, $x1, $c1, 24, $dua ? 210 : 456, $isi, $garis);

        if ($dua) {
            $kanvas->teksPx([234, 160], '+', ['ukuran' => 34, 'tebal' => true]);
            self::kelompok($kanvas, $x2, $c2, 258, 456, $isi, $garis);
        }

        return $kanvas->render();
    }

    /** Satu kelompok ubin: ubin panjang berderet, lalu ubin kecil di bawahnya. */
    protected static function kelompok(Kanvas $k, int $x, int $c, float $kiri, float $kanan, string $isi, string $garis): void
    {
        $lebarUbin = 26;
        $tinggiUbin = 78;
        $kecil = 24;

        $tengah = ($kiri + $kanan) / 2;

        for ($i = 0; $i < $x; $i++) {
            $px = $tengah - ($x - 1) * ($lebarUbin + 8) / 2 + $i * ($lebarUbin + 8);

            $k->tambah('<rect x="'.round($px - $lebarUbin / 2, 1).'" y="60" width="'.$lebarUbin
                .'" height="'.$tinggiUbin.'" rx="3" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
                ->teksPx([$px, 105], 'x', ['ukuran' => 16, 'tebal' => true]);
        }

        $perBaris = min(6, max(1, $c));

        for ($i = 0; $i < $c; $i++) {
            $baris = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, $c - $baris * $perBaris);
            $px = $tengah - ($isiBaris - 1) * ($kecil + 6) / 2 + ($i % $perBaris) * ($kecil + 6);

            $k->tambah('<rect x="'.round($px - $kecil / 2, 1).'" y="'.(170 + $baris * ($kecil + 6))
                .'" width="'.$kecil.'" height="'.$kecil.'" rx="3" fill="#ffffff" stroke="'.$garis
                .'" stroke-width="2"/>')
                ->teksPx([$px, 170 + $baris * ($kecil + 6) + 17], '1', ['ukuran' => 13]);
        }
    }

    public static function svgTimbangan(int $a, int $b, int $c, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kawat = Kanvas::HITAM;

        $tengah = Kanvas::LEBAR / 2;
        $batang = 200;

        $kanvas->garis([$tengah - $batang, 110], [$tengah + $batang, 110], $kawat, 4)
            ->poligon([[$tengah, 110], [$tengah - 26, 176], [$tengah + 26, 176]], '#cfd4da', $kawat)
            ->garis([$tengah - 170, 176], [$tengah + 170, 176], $kawat, 3);

        // Piring kiri: kantong-kantong berisi x, lalu beban satuan.
        for ($i = 0; $i < $a; $i++) {
            $x = $tengah - 160 + $i * 36;
            $kanvas->tambah('<path d="M '.($x - 14).' 72 L '.($x + 14).' 72 L '.($x + 18).' 104 L '
                .($x - 18).' 104 Z" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
                ->teksPx([$x, 96], 'x', ['ukuran' => 15, 'tebal' => true]);
        }

        for ($i = 0; $i < $b; $i++) {
            $kanvas->lingkaran([$tengah - 160 + $a * 36 + ($i % 5) * 20, 92 - intdiv($i, 5) * 20], 8,
                '#ffffff', $garis, 1.5);
        }

        // Piring kanan: beban satuan saja.
        for ($i = 0; $i < $c; $i++) {
            $kanvas->lingkaran([
                $tengah + 40 + ($i % 7) * 20,
                92 - intdiv($i, 7) * 20,
            ], 8, '#ffffff', $garis, 1.5);
        }

        $kanvas->teksPx([$tengah - 110, 206], 'kiri', ['ukuran' => 13, 'warna' => '#4b5563'])
            ->teksPx([$tengah + 110, 206], 'kanan', ['ukuran' => 13, 'warna' => '#4b5563'])
            ->teksPx([$tengah, 240], 'seimbang', ['ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }
}

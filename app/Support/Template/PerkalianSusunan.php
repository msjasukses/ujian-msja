<?php

namespace App\Support\Template;

use App\Support\Benda;
use App\Support\Kanvas;

/**
 * Template perkalian susunan: benda berbaris dan papan bilangan.
 *
 * Dua cara memandang perkalian yang dipakai di kelas. Susunan baris × kolom
 * memperlihatkan perkalian sebagai penjumlahan berulang; papan bilangan
 * memperlihatkan kelipatan sebagai pola, dan dari pola itulah KPK dan FPB
 * dibaca — bukan dihafal rumusnya.
 */
class PerkalianSusunan extends Template
{
    /** @var array<string, string> */
    public const MODE = [
        'susunan' => 'Susunan benda (baris × kolom)',
        'papan' => 'Papan bilangan (kelipatan)',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Banyak benda seluruhnya',
        'kalimat' => 'Kalimat perkaliannya',
        'penjumlahan' => 'Bentuk penjumlahan berulangnya',
        'kpk' => 'KPK dua bilangan',
        'fpb' => 'FPB dua bilangan',
    ];

    /** Banyak kolom papan bilangan; sepuluh supaya polanya terbaca menurun. */
    protected const SEBARIS = 10;

    public static function kode(): string
    {
        return 'perkalian-susunan';
    }

    public static function nama(): string
    {
        return 'Perkalian Susunan (Array)';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Benda disusun baris × kolom dan penjumlahan berulang, '
            .'serta kelipatan, KPK, dan FPB pada papan bilangan.';
    }

    public static function ikon(): string
    {
        return 'bi-grid-3x2-gap';
    }

    public static function kataKunci(): array
    {
        return ['perkalian', 'array', 'susunan', 'penjumlahan berulang', 'kelipatan', 'kpk', 'fpb', 'faktor'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'mode',
                'label' => 'Bentuk gambar',
                'jenis' => 'pilihan',
                'pilihan' => self::MODE,
                'awal' => 'susunan',
            ],
            self::kolomAngka('baris', 'Banyak baris', 4, 1, 10, [
                'langkah' => 1, 'tampil' => ['mode' => ['susunan']],
            ]),
            self::kolomAngka('kolom', 'Banyak kolom', 3, 1, 12, [
                'langkah' => 1, 'tampil' => ['mode' => ['susunan']],
            ]),
            self::kolomBenda('bola', 6) + ['tampil' => ['mode' => ['susunan']]],
            self::kolomAngka('bilangan_1', 'Bilangan pertama', 4, 2, 20, [
                'langkah' => 1, 'tampil' => ['mode' => ['papan']],
            ]),
            self::kolomAngka('bilangan_2', 'Bilangan kedua', 6, 2, 20, [
                'langkah' => 1, 'tampil' => ['mode' => ['papan']],
            ]),
            self::kolomTanya(array_slice(self::TANYA, 0, 3), 'hasil', 6) + ['tampil' => ['mode' => ['susunan']]],
            [
                'nama' => 'tanya_papan',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_slice(self::TANYA, 3, 2),
                'awal' => 'kpk',
                'lebar' => 6,
                'tampil' => ['mode' => ['papan']],
            ],
            self::kolomWarna('ungu'),
        ];
    }

    protected function susun(array $isi): array
    {
        return ($isi['mode'] ?? 'susunan') === 'papan'
            ? $this->papan($isi)
            : $this->susunan($isi);
    }

    // =====================================================================
    // Susunan benda
    // =====================================================================

    protected function susunan(array $isi): array
    {
        $baris = (int) $isi['baris'];
        $kolom = (int) $isi['kolom'];
        $hasil = $baris * $kolom;

        $benda = $isi['benda'];
        $nama = Benda::sebutan($benda);
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $cerita = "Perhatikan gambar berikut. {$nama} disusun menjadi {$baris} baris, "
            ."tiap baris berisi {$kolom} {$nama}.";
        $berulang = implode(' + ', array_fill(0, $baris, (string) $kolom)).' = '.$hasil;

        $dasar = [
            'judul' => "{$baris} × {$kolom} = {$hasil}",
            'svg' => self::svgSusunan($baris, $kolom, $benda, $warnaIsi, $garis),
            'keterangan' => "Susunan {$baris} baris × {$kolom} kolom berisi {$nama}.",
            'pembahasan' => "{$baris} baris masing-masing berisi {$kolom}, jadi {$berulang}, "
                ."atau {$baris} × {$kolom} = {$hasil}.",
        ];

        return match ($isi['tanya'] ?? 'hasil') {
            'kalimat' => $dasar + [
                'teks' => $cerita.' Kalimat perkalian yang sesuai adalah ...',
                'pernyataan' => $cerita.' Kalimat perkalian yang sesuai adalah %s.',
                'pilihan' => array_map(
                    fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                    array_values(array_unique([
                        "{$baris} × {$kolom} = {$hasil}",
                        "{$baris} + {$kolom} = ".($baris + $kolom),
                        "{$baris} × {$kolom} = ".($hasil + $kolom),
                        "{$kolom} × {$baris} = ".($hasil + 1),
                    ])),
                ),
                'kata_kunci' => "{$baris} × {$kolom} = {$hasil}",
            ],
            'penjumlahan' => $dasar + [
                'teks' => $cerita.' Bentuk penjumlahan berulang yang sesuai adalah ...',
                'pernyataan' => $cerita.' Bentuk penjumlahan berulang yang sesuai adalah %s.',
                'pilihan' => array_map(
                    fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                    array_values(array_unique([
                        $berulang,
                        implode(' + ', array_fill(0, $kolom, (string) $baris)).' = '.$hasil,
                        implode(' + ', array_fill(0, max(2, $baris), (string) $kolom)).' = '.($hasil + $kolom),
                        "{$baris} + {$kolom} = ".($baris + $kolom),
                    ])),
                ),
                'kata_kunci' => $berulang,
            ],
            default => $dasar + [
                'teks' => $cerita." Berapa banyak {$nama} seluruhnya?",
                'pernyataan' => $cerita." Banyak {$nama} seluruhnya ada %s.",
                'pilihan' => array_map(
                    fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
                    array_merge([$hasil], self::saring($hasil, [
                        $baris + $kolom, $hasil + $kolom, $hasil - $kolom, $hasil + 1, $hasil * 2,
                    ], 0)),
                ),
                'kata_kunci' => (string) $hasil,
            ],
        };
    }

    public static function svgSusunan(int $baris, int $kolom, string $benda, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $jarak = min((Kanvas::LEBAR - 60) / $kolom, (Kanvas::TINGGI - 60) / $baris, 60);
        $r = $jarak * 0.38;

        $pangkalX = (Kanvas::LEBAR - ($kolom - 1) * $jarak) / 2;
        $pangkalY = (Kanvas::TINGGI - ($baris - 1) * $jarak) / 2;

        for ($b = 0; $b < $baris; $b++) {
            for ($k = 0; $k < $kolom; $k++) {
                Benda::gambar($kanvas, [$pangkalX + $k * $jarak, $pangkalY + $b * $jarak], $r, $benda, $isi, $garis);
            }
        }

        return $kanvas->render();
    }

    // =====================================================================
    // Papan bilangan
    // =====================================================================

    protected function papan(array $isi): array
    {
        $a = (int) $isi['bilangan_1'];
        $b = (int) $isi['bilangan_2'];
        $tanya = $isi['tanya_papan'] ?? 'kpk';

        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $kpk = (int) ($a * $b / self::fpb($a, $b));
        $fpb = self::fpb($a, $b);
        $benar = $tanya === 'kpk' ? $kpk : $fpb;

        $kelipatan = fn (int $n) => implode(', ', array_map(fn ($i) => $n * $i, range(1, 5))).', ...';
        $faktor = fn (int $n) => implode(', ', self::faktor($n));

        $cerita = "Perhatikan papan bilangan berikut. Kelipatan {$a} ditandai lingkaran "
            ."dan kelipatan {$b} ditandai kotak.";

        return [
            'judul' => ($tanya === 'kpk' ? 'KPK' : 'FPB')." {$a} dan {$b} = {$benar}",
            'teks' => $cerita.' Berapakah '.($tanya === 'kpk' ? 'KPK' : 'FPB')." dari {$a} dan {$b}?",
            'pernyataan' => $cerita.' '.($tanya === 'kpk' ? 'KPK' : 'FPB')." dari {$a} dan {$b} adalah %s.",
            'svg' => self::svgPapan($a, $b, $warnaIsi, $garis),
            'keterangan' => "Papan bilangan 1–100 dengan kelipatan {$a} dan {$b} ditandai.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Tertukar antara KPK dan FPB adalah kekeliruan paling
                    // sering pada materi ini, jadi keduanya saling menjadi
                    // pengecoh.
                    $tanya === 'kpk' ? $fpb : $kpk, $a * $b, $a, $b, $benar + 1,
                ], 0)),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => $tanya === 'kpk'
                ? "Kelipatan {$a}: ".$kelipatan($a)."<br>Kelipatan {$b}: ".$kelipatan($b)
                    ."<br>Kelipatan persekutuan terkecilnya adalah {$kpk}."
                : "Faktor {$a}: ".$faktor($a)."<br>Faktor {$b}: ".$faktor($b)
                    ."<br>Faktor persekutuan terbesarnya adalah {$fpb}.",
        ];
    }

    public static function svgPapan(int $a, int $b, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $sel = 26;
        $pangkalX = (Kanvas::LEBAR - self::SEBARIS * $sel) / 2;
        $pangkalY = 18;

        for ($n = 1; $n <= 100; $n++) {
            $kolom = ($n - 1) % self::SEBARIS;
            $baris = intdiv($n - 1, self::SEBARIS);
            $x = $pangkalX + $kolom * $sel;
            $y = $pangkalY + $baris * $sel;

            $kanvas->tambah('<rect x="'.$x.'" y="'.$y.'" width="'.$sel.'" height="'.$sel
                .'" fill="#ffffff" stroke="#d6dade" stroke-width="1"/>');

            // Kelipatan yang sama ditandai dua-duanya: justru pada bilangan
            // bertanda ganda itulah KPK dan FPB dibaca.
            if ($n % $b === 0) {
                $kanvas->tambah('<rect x="'.($x + 3).'" y="'.($y + 3).'" width="'.($sel - 6).'" height="'.($sel - 6)
                    .'" fill="none" stroke="'.$garis.'" stroke-width="2"/>');
            }

            if ($n % $a === 0) {
                $kanvas->lingkaran([$x + $sel / 2, $y + $sel / 2], $sel / 2 - 2, $isi, $garis, 1.5);
            }

            $kanvas->teksPx([$x + $sel / 2, $y + $sel / 2 + 4], (string) $n, ['ukuran' => 11]);
        }

        return $kanvas->render();
    }

    /** @return array<int, int> */
    protected static function faktor(int $n): array
    {
        $hasil = [];

        for ($i = 1; $i <= $n; $i++) {
            if ($n % $i === 0) {
                $hasil[] = $i;
            }
        }

        return $hasil;
    }

    protected static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, abs($a));
    }
}

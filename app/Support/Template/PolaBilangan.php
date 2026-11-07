<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template pola gambar & bilangan.
 *
 * Tiga wujud pola yang dipakai berurutan sepanjang sekolah: pola gambar yang
 * berulang di kelas awal, pola titik yang bertumbuh sebagai jembatan, lalu
 * barisan bilangan dengan rumus suku ke-n. Ketiganya disatukan supaya guru
 * bisa menaikkan tingkat kesulitannya tanpa berpindah template.
 */
class PolaBilangan extends Template
{
    /** @var array<string, string> */
    public const JENIS = [
        'gambar' => 'Pola gambar berulang',
        'bertumbuh' => 'Pola titik bertumbuh',
        'bilangan' => 'Barisan bilangan',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'berikutnya' => 'Suku berikutnya',
        'suku_ke_n' => 'Suku ke-n',
        'rumus' => 'Rumus suku ke-n',
    ];

    /** @var array<string, string> bentuk yang dipakai pola berulang */
    public const BENTUK = [
        'lingkaran' => 'Lingkaran',
        'segitiga' => 'Segitiga',
        'persegi' => 'Persegi',
        'bintang' => 'Bintang',
    ];

    public static function kode(): string
    {
        return 'pola-bilangan';
    }

    public static function nama(): string
    {
        return 'Pola Gambar & Bilangan';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Lanjutkan pola gambar berulang, pola titik yang bertumbuh, atau '
            .'barisan aritmetika: suku berikutnya, suku ke-n, dan rumusnya.';
    }

    public static function ikon(): string
    {
        return 'bi-puzzle';
    }

    public static function kataKunci(): array
    {
        return ['pola', 'barisan', 'aritmetika', 'suku', 'beda', 'urutan', 'deret'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'jenis_pola',
                'label' => 'Jenis pola',
                'jenis' => 'pilihan',
                'pilihan' => self::JENIS,
                'awal' => 'bilangan',
            ],
            [
                'nama' => 'urutan',
                'label' => 'Urutan bentuk yang berulang',
                'jenis' => 'teks',
                'awal' => 'lingkaran, segitiga, persegi',
                'bantuan' => 'Dipisah koma. Pilihan bentuk: '.implode(', ', array_keys(self::BENTUK)).'.',
                'tampil' => ['jenis_pola' => ['gambar']],
            ],
            self::kolomAngka('suku_awal', 'Suku pertama', 3, -100, 1000, [
                'langkah' => 1, 'tampil' => ['jenis_pola' => ['bilangan', 'bertumbuh']],
            ]),
            self::kolomAngka('beda', 'Beda tiap suku', 4, -100, 100, [
                'langkah' => 1, 'tampil' => ['jenis_pola' => ['bilangan', 'bertumbuh']],
            ]),
            self::kolomAngka('banyak', 'Banyak suku yang digambar', 5, 3, 8, ['langkah' => 1]),
            self::kolomAngka('n', 'Suku ke-', 10, 1, 200, [
                'langkah' => 1, 'tampil' => ['tanya' => ['suku_ke_n']],
            ]),
            self::kolomTanya(self::TANYA, 'berikutnya', 6),
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['jenis_pola'] ?? '') !== 'gambar') {
            if ((int) ($isi['beda'] ?? 0) === 0) {
                return ['beda' => 'Beda 0 membuat semua sukunya sama; polanya tidak terbentuk.'];
            }

            if (($isi['jenis_pola'] ?? '') === 'bertumbuh' && (int) ($isi['suku_awal'] ?? 0) < 1) {
                return ['suku_awal' => 'Pola titik tidak bisa dimulai dari bilangan nol atau minus.'];
            }

            if (($isi['jenis_pola'] ?? '') === 'bertumbuh' && (int) ($isi['beda'] ?? 0) < 1) {
                return ['beda' => 'Pola titik hanya bisa bertumbuh, jadi bedanya harus positif.'];
            }

            return [];
        }

        $urutan = self::pecah((string) ($isi['urutan'] ?? ''));

        if (count($urutan) < 2) {
            return ['urutan' => 'Tuliskan minimal dua bentuk yang berulang.'];
        }

        foreach ($urutan as $bentuk) {
            if (! isset(self::BENTUK[$bentuk])) {
                return ['urutan' => "\"{$bentuk}\" bukan bentuk yang dikenal."];
            }
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');
        $banyak = (int) $isi['banyak'];

        if (($isi['jenis_pola'] ?? '') === 'gambar') {
            return $this->polaGambar(self::pecah((string) $isi['urutan']), $banyak, $warnaIsi, $garis);
        }

        $awal = (int) $isi['suku_awal'];
        $beda = (int) $isi['beda'];
        $suku = fn (int $n) => $awal + ($n - 1) * $beda;
        $deret = array_map($suku, range(1, $banyak));

        $bertumbuh = ($isi['jenis_pola'] ?? '') === 'bertumbuh';
        $svg = $bertumbuh
            ? self::svgBertumbuh($deret, $warnaIsi, $garis)
            : self::svgBilangan($deret, $warnaIsi, $garis);

        $cerita = $bertumbuh
            ? 'Perhatikan pola titik berikut.'
            : 'Perhatikan barisan bilangan berikut.';
        $keterangan = ($bertumbuh ? 'Pola titik' : 'Barisan').' '.implode(', ', $deret).', ...';
        $arah = $beda < 0 ? 'berkurang' : 'bertambah';
        $rumus = self::rumus($awal, $beda);

        $pembahasan = "Tiap suku {$arah} ".abs($beda).' dari suku sebelumnya. '
            ."Suku pertama {$awal} dan bedanya {$beda}, jadi U(n) = {$rumus}.";

        return match ($isi['tanya'] ?? 'berikutnya') {
            'suku_ke_n' => (function () use ($isi, $suku, $cerita, $svg, $keterangan, $pembahasan, $beda) {
                $n = (int) $isi['n'];
                $benar = $suku($n);

                return [
                    'judul' => "Suku ke-{$n} = {$benar}",
                    'teks' => $cerita." Berapakah suku ke-{$n} pada pola itu?",
                    'pernyataan' => $cerita." Suku ke-{$n} pada pola itu adalah %s.",
                    'svg' => $svg,
                    'keterangan' => $keterangan,
                    'pilihan' => self::pilihanAngka($benar, [
                        // Menghitung n kali beda, bukan (n−1) kali, adalah
                        // kekeliruan paling sering pada rumus suku ke-n.
                        $benar + $beda, $benar - $beda, $benar * 2, $benar + 1,
                    ]),
                    'kata_kunci' => (string) $benar,
                    'pembahasan' => $pembahasan." Suku ke-{$n} = {$benar}.",
                ];
            })(),
            'rumus' => [
                'judul' => 'Rumus U(n) = '.$rumus,
                'teks' => $cerita.' Rumus suku ke-n pola itu adalah ...',
                'pernyataan' => $cerita.' Rumus suku ke-n pola itu adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($teks) => ['teks' => 'U(n) = '.$teks, 'urut' => $teks],
                    array_values(array_unique([
                        $rumus,
                        // Tiga kekeliruan khas: memakai n·b (bukan (n−1)·b),
                        // menukar peran suku pertama dengan bedanya, dan
                        // salah tanda pada suku tetapnya.
                        self::teksRumus($beda, $awal),
                        self::teksRumus($awal, $beda),
                        self::teksRumus($beda, -($awal - $beda)),
                        self::teksRumus($beda + 1, $awal - $beda),
                    ])),
                ),
                'kata_kunci' => 'U(n) = '.$rumus,
                'pembahasan' => $pembahasan,
            ],
            default => (function () use ($suku, $banyak, $cerita, $svg, $keterangan, $pembahasan, $beda) {
                $benar = $suku($banyak + 1);

                return [
                    'judul' => 'Suku berikutnya '.$benar,
                    'teks' => $cerita.' Bilangan berapakah yang menggantikan tanda tanya?',
                    'pernyataan' => $cerita.' Bilangan yang menggantikan tanda tanya adalah %s.',
                    'svg' => $svg,
                    'keterangan' => $keterangan,
                    'pilihan' => self::pilihanAngka($benar, [
                        $benar + $beda, $benar - $beda, $benar + 1, $benar * 2,
                    ]),
                    'kata_kunci' => (string) $benar,
                    'pembahasan' => $pembahasan." Suku berikutnya = {$benar}.",
                ];
            })(),
        };
    }

    protected function polaGambar(array $urutan, int $banyak, string $isi, string $garis): array
    {
        $deret = [];

        for ($i = 0; $i < $banyak; $i++) {
            $deret[] = $urutan[$i % count($urutan)];
        }

        $benar = $urutan[$banyak % count($urutan)];

        return [
            'judul' => 'Pola '.implode('-', $urutan),
            'teks' => 'Perhatikan pola gambar berikut. Bentuk apakah yang menggantikan tanda tanya?',
            'pernyataan' => 'Perhatikan pola gambar berikut. Bentuk yang menggantikan tanda tanya adalah %s.',
            'svg' => self::svgPolaGambar($deret, $isi, $garis),
            'keterangan' => 'Pola berulang: '.implode(', ', $deret).', ...',
            'pilihan' => array_map(
                fn ($b) => ['teks' => self::BENTUK[$b], 'urut' => self::BENTUK[$b]],
                array_merge([$benar], array_values(array_diff(array_keys(self::BENTUK), [$benar]))),
            ),
            'kata_kunci' => self::BENTUK[$benar],
            'pembahasan' => 'Polanya berulang setiap '.count($urutan).' bentuk: '
                .implode(', ', array_map(fn ($b) => self::BENTUK[$b], $urutan))
                .'. Jadi bentuk berikutnya adalah '.self::BENTUK[$benar].'.',
        ];
    }

    /** Bentuk baku suku ke-n barisan aritmetika: a + (n − 1)b, dirapikan. */
    protected static function rumus(int $awal, int $beda): string
    {
        return self::teksRumus($beda, $awal - $beda);
    }

    /** Tulisan "kn + c" yang rapi: koefisien 1 dan suku tetap 0 tidak ditulis. */
    protected static function teksRumus(int $koefisien, int $tetap): string
    {
        $kn = match ($koefisien) {
            1 => 'n',
            -1 => '−n',
            default => $koefisien.'n',
        };

        return $tetap === 0 ? $kn : $kn.' '.($tetap < 0 ? '−' : '+').' '.abs($tetap);
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanAngka(int $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat, 0, false)),
        );
    }

    /** @return array<int, string> */
    protected static function pecah(string $teks): array
    {
        return array_values(array_filter(array_map(
            fn ($x) => mb_strtolower(trim($x)),
            explode(',', $teks),
        ), fn ($x) => $x !== ''));
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgBilangan(array $deret, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kotak = array_merge(array_map('strval', $deret), ['?']);

        $lebar = min(84, (Kanvas::LEBAR - 40) / count($kotak) - 10);
        $tinggi = 74;
        $y = (Kanvas::TINGGI - $tinggi) / 2;

        foreach ($kotak as $i => $teks) {
            $x = (Kanvas::LEBAR - count($kotak) * ($lebar + 10)) / 2 + $i * ($lebar + 10);
            $akhir = $i === count($kotak) - 1;

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.$y.'" width="'.round($lebar, 1).'" height="'.$tinggi
                .'" rx="8" fill="'.($akhir ? '#ffffff' : $isi).'" stroke="'.$garis.'" stroke-width="2.5"'
                .($akhir ? ' stroke-dasharray="6 4"' : '').'/>')
                ->teksPx([$x + $lebar / 2, $y + $tinggi / 2 + 9], $teks, ['ukuran' => 26, 'tebal' => true]);
        }

        return $kanvas->render();
    }

    public static function svgBertumbuh(array $deret, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kelompok = count($deret) + 1;
        $lebar = (Kanvas::LEBAR - 20) / $kelompok;

        foreach ($deret as $i => $banyak) {
            $perBaris = max(1, (int) ceil(sqrt($banyak)));
            $jarak = min(14, ($lebar - 16) / $perBaris);
            $pusatX = 10 + $lebar * ($i + 0.5);

            for ($t = 0; $t < $banyak; $t++) {
                $barisKe = intdiv($t, $perBaris);
                $isiBaris = min($perBaris, $banyak - $barisKe * $perBaris);

                $kanvas->lingkaran([
                    $pusatX - ($isiBaris - 1) * $jarak / 2 + ($t % $perBaris) * $jarak,
                    150 - ((int) ceil($banyak / $perBaris) - 1) * $jarak / 2 + $barisKe * $jarak,
                ], $jarak * 0.36, $isi, $garis, 1.2);
            }

            $kanvas->teksPx([$pusatX, 236], 'pola '.($i + 1), ['ukuran' => 13, 'warna' => '#4b5563'])
                ->teksPx([$pusatX, 258], (string) $banyak, ['ukuran' => 16, 'tebal' => true]);
        }

        $kanvas->teksPx([10 + $lebar * (count($deret) + 0.5), 150], '?', ['ukuran' => 42, 'tebal' => true])
            ->teksPx([10 + $lebar * (count($deret) + 0.5), 236], 'pola '.($kelompok), [
                'ukuran' => 13, 'warna' => '#4b5563',
            ]);

        return $kanvas->render();
    }

    public static function svgPolaGambar(array $deret, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kelompok = count($deret) + 1;
        $lebar = (Kanvas::LEBAR - 20) / $kelompok;
        $r = min(32, $lebar * 0.36);

        foreach ($deret as $i => $bentuk) {
            self::bentuk($kanvas, 10 + $lebar * ($i + 0.5), 150, $r, $bentuk, $isi, $garis);
        }

        $x = 10 + $lebar * (count($deret) + 0.5);
        $kanvas->tambah('<rect x="'.round($x - $r, 1).'" y="'.(150 - $r).'" width="'.round($r * 2, 1)
            .'" height="'.round($r * 2, 1).'" rx="8" fill="#ffffff" stroke="'.$garis
            .'" stroke-width="2" stroke-dasharray="6 4"/>')
            ->teksPx([$x, 162], '?', ['ukuran' => 34, 'tebal' => true]);

        return $kanvas->render();
    }

    protected static function bentuk(Kanvas $k, float $x, float $y, float $r, string $bentuk, string $isi, string $garis): void
    {
        match ($bentuk) {
            'segitiga' => $k->poligon([[$x, $y - $r], [$x + $r, $y + $r], [$x - $r, $y + $r]], $isi, $garis, 2),
            'persegi' => $k->persegi([$x - $r * 0.9, $y - $r * 0.9], $r * 1.8, $r * 1.8, $isi, $garis, 2),
            'bintang' => $k->poligon(
                array_map(
                    fn ($i) => [
                        $x + ($i % 2 === 0 ? $r : $r * 0.42) * cos(deg2rad($i * 36 - 90)),
                        $y + ($i % 2 === 0 ? $r : $r * 0.42) * sin(deg2rad($i * 36 - 90)),
                    ],
                    range(0, 9),
                ),
                $isi, $garis, 2,
            ),
            default => $k->lingkaran([$x, $y], $r, $isi, $garis, 2),
        };
    }
}

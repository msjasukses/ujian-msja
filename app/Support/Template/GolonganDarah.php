<?php

namespace App\Support\Template;

use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template pewarisan golongan darah sistem ABO.
 *
 * Berbeda dengan persilangan monohibrid biasa, golongan darah ditentukan
 * tiga alel: I^A dan I^B sama kuat dan keduanya mengalahkan I^O. Karena itu
 * genotipe tidak cukup dibaca "ada huruf besar atau tidak", dan itulah yang
 * membuat siswa keliru — maka papan Punnett-nya hanya menampilkan genotipe
 * anak, bukan golongan darahnya. Menerjemahkan genotipe menjadi golongan
 * darah justru pekerjaan yang diujikan.
 */
class GolonganDarah extends Template
{
    /**
     * Genotipe yang mungkin dimiliki seseorang beserta alelnya.
     *
     * @var array<string, array{alel: array<int, string>, golongan: string}>
     */
    public const GENOTIPE = [
        'AA' => ['alel' => ['A', 'A'], 'golongan' => 'A'],
        'AO' => ['alel' => ['A', 'O'], 'golongan' => 'A'],
        'BB' => ['alel' => ['B', 'B'], 'golongan' => 'B'],
        'BO' => ['alel' => ['B', 'O'], 'golongan' => 'B'],
        'AB' => ['alel' => ['A', 'B'], 'golongan' => 'AB'],
        'OO' => ['alel' => ['O', 'O'], 'golongan' => 'O'],
    ];

    /** Urutan baku golongan darah, dipakai untuk menuliskan himpunan jawaban. */
    public const URUTAN = ['A', 'B', 'AB', 'O'];

    /** @var array<string, string> */
    public const TANYA = [
        'golongan' => 'Golongan darah yang mungkin',
        'persen' => 'Peluang sebuah golongan darah',
        'tidak_mungkin' => 'Golongan darah yang tidak mungkin',
    ];

    public static function kode(): string
    {
        return 'golongan-darah';
    }

    public static function nama(): string
    {
        return 'Pewarisan Golongan Darah';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Pewarisan golongan darah sistem ABO dengan papan Punnett: golongan '
            .'yang mungkin dimiliki anak, peluangnya, dan yang tidak mungkin muncul.';
    }

    public static function ikon(): string
    {
        return 'bi-droplet-fill';
    }

    public static function kataKunci(): array
    {
        return ['golongan darah', 'abo', 'alel ganda', 'pewarisan sifat', 'genetika',
            'punnett', 'genotipe', 'fenotipe', 'kodominan', 'transfusi', 'biologi'];
    }

    public static function kolom(): array
    {
        $pilihan = array_combine(array_keys(self::GENOTIPE), array_map(
            fn ($g, $k) => 'Golongan '.$g['golongan'].' ('.Rumus::polos(self::tulisGenotipe($k)).')',
            self::GENOTIPE,
            array_keys(self::GENOTIPE),
        ));

        return [
            self::kolomTanya(self::TANYA, 'golongan'),
            [
                'nama' => 'ayah',
                'label' => 'Genotipe ayah',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                'awal' => 'AO',
                'lebar' => 6,
            ],
            [
                'nama' => 'ibu',
                'label' => 'Genotipe ibu',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                // Nilai bawaan harus sah untuk semua jenis pertanyaan.
                // AO x BO dapat menurunkan keempat golongan, sehingga soal
                // "yang tidak mungkin" jadi tanpa jawaban; AO x AO tidak.
                'awal' => 'AO',
                'lebar' => 6,
            ],
            [
                'nama' => 'sasaran',
                'label' => 'Golongan darah yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(self::URUTAN, array_map(
                    fn ($g) => 'Golongan '.$g,
                    self::URUTAN,
                )),
                'awal' => 'O',
                'tampil' => ['tanya' => ['persen']],
            ],
            self::kolomWarna('merah'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? 'golongan') !== 'tidak_mungkin') {
            return [];
        }

        $mustahil = array_values(array_diff(self::URUTAN, self::golonganAnak(
            self::GENOTIPE[$isi['ayah'] ?? 'AO']['alel'] ?? ['A', 'O'],
            self::GENOTIPE[$isi['ibu'] ?? 'BO']['alel'] ?? ['B', 'O'],
        )));

        if ($mustahil !== []) {
            return [];
        }

        return ['ibu' => 'Pasangan itu dapat menurunkan keempat golongan darah, sehingga tidak '
            .'ada golongan yang tidak mungkin dan soalnya tanpa jawaban. Coba pasangan lain, '
            .'misalnya AB dengan A heterozigot.'];
    }

    /** Lambang genotipe dengan penanda pangkat, misalnya I^AI^O. */
    public static function tulisGenotipe(string $kunci): string
    {
        return implode('', array_map(
            fn ($a) => 'I^'.$a,
            self::GENOTIPE[$kunci]['alel'] ?? ['O', 'O'],
        ));
    }

    /** Lambang sepasang alel, dipakai untuk isi kotak papan Punnett. */
    public static function tulisPasangan(array $pasangan): string
    {
        return implode('', array_map(fn ($a) => 'I^'.$a, self::urutkanAlel($pasangan)));
    }

    /**
     * Mengurutkan sepasang alel menurut kebiasaan penulisan: I^A sebelum
     * I^B, dan I^O selalu di belakang.
     */
    public static function urutkanAlel(array $pasangan): array
    {
        $peringkat = ['A' => 0, 'B' => 1, 'O' => 2];

        usort($pasangan, fn ($x, $y) => $peringkat[$x] <=> $peringkat[$y]);

        return $pasangan;
    }

    /** Golongan darah dari sepasang alel. */
    public static function golongan(array $pasangan): string
    {
        $adaA = in_array('A', $pasangan, true);
        $adaB = in_array('B', $pasangan, true);

        return match (true) {
            $adaA && $adaB => 'AB',
            $adaA => 'A',
            $adaB => 'B',
            default => 'O',
        };
    }

    /** Keempat kotak papan Punnett dari alel kedua induk. */
    public static function kotak(array $ayah, array $ibu): array
    {
        $kotak = [];

        foreach ($ibu as $y) {
            foreach ($ayah as $x) {
                $kotak[] = [$x, $y];
            }
        }

        return $kotak;
    }

    /** Golongan darah yang mungkin muncul, menurut urutan bakunya. */
    public static function golonganAnak(array $ayah, array $ibu): array
    {
        $ada = array_map(fn ($p) => self::golongan($p), self::kotak($ayah, $ibu));

        return array_values(array_filter(self::URUTAN, fn ($g) => in_array($g, $ada, true)));
    }

    /** Menuliskan himpunan golongan darah sebagai kalimat Indonesia. */
    public static function tulisHimpunan(array $golongan): string
    {
        $golongan = array_values($golongan);

        if ($golongan === []) {
            return 'tidak ada';
        }

        if (count($golongan) === 1) {
            return $golongan[0].' saja';
        }

        $akhir = array_pop($golongan);

        return implode(', ', $golongan).' dan '.$akhir;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        $kunciAyah = isset(self::GENOTIPE[$isi['ayah'] ?? '']) ? $isi['ayah'] : 'AO';
        $kunciIbu = isset(self::GENOTIPE[$isi['ibu'] ?? '']) ? $isi['ibu'] : 'BO';

        $ayah = self::GENOTIPE[$kunciAyah];
        $ibu = self::GENOTIPE[$kunciIbu];

        $kotak = self::kotak($ayah['alel'], $ibu['alel']);
        $mungkin = self::golonganAnak($ayah['alel'], $ibu['alel']);

        $bahan = [
            'svg' => self::svgPunnett($kunciAyah, $kunciIbu, $warnaIsi, $garis),
            'keterangan' => 'Papan Punnett persilangan golongan darah '
                .Rumus::polos(self::tulisGenotipe($kunciAyah)).' dengan '
                .Rumus::polos(self::tulisGenotipe($kunciIbu)).'.',
        ];

        $awalan = 'Seorang ayah bergolongan darah '.$ayah['golongan'].' bergenotipe '
            .Rumus::html(self::tulisGenotipe($kunciAyah)).' menikah dengan seorang ibu '
            .'bergolongan darah '.$ibu['golongan'].' bergenotipe '
            .Rumus::html(self::tulisGenotipe($kunciIbu)).'.';

        return $bahan + match ($isi['tanya'] ?? 'golongan') {
            'persen' => $this->persen($awalan, $kotak,
                in_array($isi['sasaran'] ?? '', self::URUTAN, true) ? $isi['sasaran'] : 'O'),
            'tidak_mungkin' => $this->tidakMungkin($awalan, $mungkin),
            default => $this->golonganMungkin($awalan, $ayah, $ibu, $mungkin),
        };
    }

    protected function golonganMungkin(string $awalan, array $ayah, array $ibu, array $mungkin): array
    {
        $benar = self::tulisHimpunan($mungkin);

        // Tiap pengecoh satu salah paham yang nyata: mengira anak hanya
        // mewarisi golongan salah satu orang tuanya, mengira semua golongan
        // mungkin, dan mengira yang muncul justru yang bukan milik induknya.
        $kandidat = [
            self::tulisHimpunan(self::URUTAN),
            self::tulisHimpunan(array_values(array_filter(
                self::URUTAN,
                fn ($g) => in_array($g, [$ayah['golongan'], $ibu['golongan']], true),
            ))),
            self::tulisHimpunan(array_values(array_diff(self::URUTAN, $mungkin))),
            self::tulisHimpunan(array_values(array_unique(array_merge($mungkin, ['O'])))),
            self::tulisHimpunan([$mungkin[0]]),
            self::tulisHimpunan(['A', 'B']),
            // Satu golongan terlewat — cadangan untuk pasangan yang memang
            // dapat menurunkan keempat golongan, sehingga pengecoh "semua
            // golongan" justru berkembar dengan kuncinya.
            self::tulisHimpunan(array_slice($mungkin, 0, max(1, count($mungkin) - 1))),
            self::tulisHimpunan(array_slice($mungkin, 1) ?: [$mungkin[0]]),
        ];

        $pengecoh = [];

        foreach ($kandidat as $teks) {
            if ($teks !== $benar && $teks !== 'tidak ada' && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'judul' => 'Anak mungkin bergolongan '.$benar,
            'teks' => $awalan.' Perhatikan papan Punnett berikut. Golongan darah yang mungkin '
                .'dimiliki anak-anak mereka adalah ...',
            'pernyataan' => $awalan.' Golongan darah yang mungkin dimiliki anak mereka adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::uraianKotak($ayah, $ibu)
                .'<br>Golongan darah yang muncul: <strong>'.$benar.'</strong>.',
        ];
    }

    protected function persen(string $awalan, array $kotak, string $sasaran): array
    {
        $cocok = count(array_filter($kotak, fn ($p) => self::golongan($p) === $sasaran));
        $benar = (int) round($cocok / count($kotak) * 100);

        return [
            'judul' => 'Peluang golongan '.$sasaran.' = '.$benar.'%',
            'teks' => $awalan.' Perhatikan papan Punnett berikut. Berapa persen kemungkinan '
                .'anak mereka bergolongan darah '.$sasaran.'?',
            'pernyataan' => $awalan.' Kemungkinan anak mereka bergolongan darah '.$sasaran
                .' adalah %s.',
            // Papan 2 × 2 hanya menghasilkan 0, 25, 50, 75, atau 100 persen,
            // jadi pengecohnya seluruh nilai lain yang mungkin — siswa yang
            // salah membilang kotak tetap menemukan jawabannya.
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n.'%', 'urut' => $n],
                array_merge([$benar], array_values(array_diff([0, 25, 50, 75, 100], [$benar]))),
            ),
            'kata_kunci' => $benar.'%',
            'pembahasan' => 'Dari empat kotak papan Punnett, '.$cocok.' kotak bergolongan darah '
                .$sasaran.' ('.implode(', ', array_map(
                    fn ($p) => Rumus::html(self::tulisPasangan($p)).' = '.self::golongan($p),
                    $kotak,
                )).').<br>Peluangnya '.$cocok.'/4 × 100% = <strong>'.$benar.'%</strong>.',
        ];
    }

    /**
     * Golongan darah yang tidak mungkin muncul.
     *
     * Yang ditanyakan himpunannya, bukan satu golongan. Kalau ditanyakan satu
     * golongan, soalnya hanya sah ketika tepat satu golongan mustahil — dan
     * keadaan itu selalu menyisakan O sebagai jawabannya, sehingga siswa bisa
     * menjawab benar tanpa melihat papan Punnett sama sekali.
     */
    protected function tidakMungkin(string $awalan, array $mungkin): array
    {
        $mustahil = array_values(array_diff(self::URUTAN, $mungkin));
        $benar = self::tulisHimpunan($mustahil);

        // Pengecohnya: justru golongan yang mungkin, keempat golongan, dan
        // himpunan mustahil yang kurang atau kelebihan satu anggota.
        $kandidat = [
            self::tulisHimpunan($mungkin),
            self::tulisHimpunan(self::URUTAN),
            self::tulisHimpunan(array_slice($mustahil, 0, max(1, count($mustahil) - 1))),
            self::tulisHimpunan(array_values(array_unique(array_merge($mustahil, [$mungkin[0]])))),
            self::tulisHimpunan(array_slice($mustahil, 1) ?: [$mustahil[0]]),
            self::tulisHimpunan(['AB', 'O']),
            self::tulisHimpunan(['O']),
        ];

        $pengecoh = [];

        foreach ($kandidat as $teks) {
            if ($teks !== $benar && $teks !== 'tidak ada' && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'judul' => 'Tidak mungkin bergolongan '.$benar,
            'teks' => $awalan.' Perhatikan papan Punnett berikut. Golongan darah yang '
                .'<strong>tidak mungkin</strong> dimiliki anak mereka adalah ...',
            'pernyataan' => $awalan.' Golongan darah yang tidak mungkin dimiliki anak mereka '
                .'adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Keempat kotak papan Punnett hanya menghasilkan golongan '
                .self::tulisHimpunan($mungkin).'.<br>Golongan <strong>'.$benar
                .'</strong> tidak pernah muncul, karena tidak ada kotak yang memuat pasangan '
                .'alel yang menghasilkannya.',
        ];
    }

    /** Uraian isi keempat kotak, untuk pembahasan. */
    protected static function uraianKotak(array $ayah, array $ibu): string
    {
        $bagian = array_map(
            fn ($p) => Rumus::html(self::tulisPasangan($p)).' → '.self::golongan($p),
            self::kotak($ayah['alel'], $ibu['alel']),
        );

        // Penanda pangkat harus dilewatkan Rumus lebih dahulu; $bagian sudah
        // berupa HTML, jadi seluruh kalimat tidak boleh diolah sekaligus.
        return Rumus::html('I^A').' dan '.Rumus::html('I^B').' sama kuat, keduanya mengalahkan '
            .Rumus::html('I^O').'. Keempat kotak papan Punnett berisi: '
            .implode('; ', $bagian).'.';
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Papan Punnett 2 × 2 berisi genotipe anak.
     *
     * Golongan darahnya sengaja tidak dituliskan di dalam kotak: menerjemahkan
     * genotipe menjadi golongan darah itulah yang diujikan.
     */
    public static function svgPunnett(string $kunciAyah, string $kunciIbu, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $ayah = self::GENOTIPE[$kunciAyah]['alel'];
        $ibu = self::GENOTIPE[$kunciIbu]['alel'];

        $lebar = 92.0;
        $tinggi = 54.0;
        $kiri = 240 - 1.5 * $lebar;
        $atas = 78.0;

        $kanvas
            ->teksPx([240, 26], 'Papan Punnett golongan darah', ['ukuran' => 15, 'tebal' => true])
            ->rumusPx([240, 48], self::tulisGenotipe($kunciAyah).' × '.self::tulisGenotipe($kunciIbu), [
                'ukuran' => 15, 'tebal' => true,
            ]);

        // Kotak sudut dibiarkan kosong; gamet ayah di barisnya dan gamet ibu
        // di lajurnya, seperti papan Punnett di buku pelajaran.
        $kanvas->tambah('<rect x="'.round($kiri, 1).'" y="'.$atas.'" width="'.$lebar
            .'" height="'.$tinggi.'" fill="#f3f4f6" stroke="'.$garis.'" stroke-width="1.8"/>');

        foreach ($ayah as $k => $alel) {
            $x = $kiri + ($k + 1) * $lebar;

            $kanvas
                ->tambah('<rect x="'.round($x, 1).'" y="'.$atas.'" width="'.$lebar
                    .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.8"/>')
                ->rumusPx([$x + $lebar / 2, $atas + $tinggi / 2 + 6], 'I^'.$alel, [
                    'ukuran' => 17, 'tebal' => true,
                ]);
        }

        foreach ($ibu as $b => $alelIbu) {
            $y = $atas + ($b + 1) * $tinggi;

            $kanvas
                ->tambah('<rect x="'.round($kiri, 1).'" y="'.round($y, 1).'" width="'.$lebar
                    .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.8"/>')
                ->rumusPx([$kiri + $lebar / 2, $y + $tinggi / 2 + 6], 'I^'.$alelIbu, [
                    'ukuran' => 17, 'tebal' => true,
                ]);

            foreach ($ayah as $k => $alelAyah) {
                $x = $kiri + ($k + 1) * $lebar;

                $kanvas
                    ->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.$lebar
                        .'" height="'.$tinggi.'" fill="#ffffff" stroke="'.$garis.'" stroke-width="1.8"/>')
                    ->rumusPx([$x + $lebar / 2, $y + $tinggi / 2 + 6],
                        self::tulisPasangan([$alelAyah, $alelIbu]), ['ukuran' => 17]);
            }
        }

        return $kanvas
            ->teksPx([$kiri + 2 * $lebar, $atas - 10], 'gamet ayah', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->teksPx([$kiri + $lebar / 2, $atas + 3 * $tinggi + 20], 'gamet ibu', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->teksPx([240, $atas + 3 * $tinggi + 44],
                'kotak berisi genotipe anak, bukan golongan darahnya', [
                    'ukuran' => 11, 'warna' => '#6b7280',
                ])
            ->render();
    }
}

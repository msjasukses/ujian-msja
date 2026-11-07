<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template persamaan reaksi & stoikiometri.
 *
 * Persamaan reaksinya digambar dari daftar zat beserta koefisien dan massa
 * molekul relatifnya, sehingga soal menyetarakan, perbandingan koefisien,
 * dan perhitungan mol maupun massa semuanya dihitung dari persamaan yang
 * sama dengan yang tergambar. Menambah reaksi cukup menuliskan satu baris.
 */
class ReaksiStoikiometri extends Template
{
    /**
     * Reaksi beserta zat yang terlibat: rumus, koefisien setara, dan massa
     * molekul relatifnya.
     *
     * @var array<string, array{nama: string, zat: array<int, array{rumus: string, koef: int, mr: float, hasil: bool}>}>
     */
    public const REAKSI = [
        'air' => [
            'nama' => 'pembentukan air',
            'zat' => [
                ['rumus' => 'H_2', 'koef' => 2, 'mr' => 2, 'hasil' => false],
                ['rumus' => 'O_2', 'koef' => 1, 'mr' => 32, 'hasil' => false],
                ['rumus' => 'H_2O', 'koef' => 2, 'mr' => 18, 'hasil' => true],
            ],
        ],
        'amonia' => [
            'nama' => 'pembentukan amonia',
            'zat' => [
                ['rumus' => 'N_2', 'koef' => 1, 'mr' => 28, 'hasil' => false],
                ['rumus' => 'H_2', 'koef' => 3, 'mr' => 2, 'hasil' => false],
                ['rumus' => 'NH_3', 'koef' => 2, 'mr' => 17, 'hasil' => true],
            ],
        ],
        'metana' => [
            'nama' => 'pembakaran metana',
            'zat' => [
                ['rumus' => 'CH_4', 'koef' => 1, 'mr' => 16, 'hasil' => false],
                ['rumus' => 'O_2', 'koef' => 2, 'mr' => 32, 'hasil' => false],
                ['rumus' => 'CO_2', 'koef' => 1, 'mr' => 44, 'hasil' => true],
                ['rumus' => 'H_2O', 'koef' => 2, 'mr' => 18, 'hasil' => true],
            ],
        ],
        'besi' => [
            'nama' => 'perkaratan besi',
            'zat' => [
                ['rumus' => 'Fe', 'koef' => 4, 'mr' => 56, 'hasil' => false],
                ['rumus' => 'O_2', 'koef' => 3, 'mr' => 32, 'hasil' => false],
                ['rumus' => 'Fe_2O_3', 'koef' => 2, 'mr' => 160, 'hasil' => true],
            ],
        ],
        'kapur' => [
            'nama' => 'penguraian batu kapur',
            'zat' => [
                ['rumus' => 'CaCO_3', 'koef' => 1, 'mr' => 100, 'hasil' => false],
                ['rumus' => 'CaO', 'koef' => 1, 'mr' => 56, 'hasil' => true],
                ['rumus' => 'CO_2', 'koef' => 1, 'mr' => 44, 'hasil' => true],
            ],
        ],
        'seng' => [
            'nama' => 'seng dengan asam klorida',
            'zat' => [
                ['rumus' => 'Zn', 'koef' => 1, 'mr' => 65, 'hasil' => false],
                ['rumus' => 'HCl', 'koef' => 2, 'mr' => 36.5, 'hasil' => false],
                ['rumus' => 'ZnCl_2', 'koef' => 1, 'mr' => 136, 'hasil' => true],
                ['rumus' => 'H_2', 'koef' => 1, 'mr' => 2, 'hasil' => true],
            ],
        ],
    ];

    /** Volume satu mol gas pada keadaan standar, dalam liter. */
    public const MOLAR = 22.4;

    /** @var array<string, string> */
    public const TANYA = [
        'setarakan' => 'Koefisien yang hilang',
        'perbandingan' => 'Perbandingan koefisien',
        'mol' => 'Mol dari massa yang diketahui',
        'massa' => 'Massa dari mol yang diketahui',
        'volume' => 'Volume gas pada keadaan standar',
        'stoikiometri' => 'Massa zat lain dari massa yang diketahui',
    ];

    public static function kode(): string
    {
        return 'reaksi-stoikiometri';
    }

    public static function nama(): string
    {
        return 'Reaksi & Stoikiometri';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Persamaan reaksi bergambar: menyetarakan koefisien, perbandingan '
            .'koefisien, serta perhitungan mol, massa, dan volume gas.';
    }

    public static function ikon(): string
    {
        return 'bi-arrow-right-short';
    }

    public static function kataKunci(): array
    {
        return ['reaksi', 'persamaan reaksi', 'menyetarakan', 'koefisien', 'stoikiometri',
            'mol', 'massa', 'volume', 'molar', 'keadaan standar', 'stp', 'mr',
            'massa molekul relatif', 'pereaksi', 'hasil reaksi'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'reaksi',
                'label' => 'Reaksi',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($r) => ucfirst($r['nama']).': '.self::tulis($r['zat']), self::REAKSI),
                'awal' => 'air',
            ],
            self::kolomTanya(self::TANYA, 'setarakan'),
            self::kolomAngka('zat_ke', 'Zat ke- dalam persamaan', 1, 1, 4, [
                'langkah' => 1,
                'tampil' => ['tanya' => ['setarakan', 'mol', 'massa', 'volume', 'stoikiometri']],
                'bantuan' => 'Dihitung dari kiri, termasuk hasil reaksinya.',
            ]),
            self::kolomAngka('zat_tujuan', 'Zat yang ditanyakan, ke-', 3, 1, 4, [
                'langkah' => 1, 'tampil' => ['tanya' => ['stoikiometri']],
            ]),
            self::kolomAngka('massa_diketahui', 'Massa yang diketahui (gram)', 36, 0.1, 100000, [
                'tampil' => ['tanya' => ['mol', 'stoikiometri']],
            ]),
            self::kolomAngka('mol_diketahui', 'Mol yang diketahui', 2, 0.1, 10000, [
                'tampil' => ['tanya' => ['massa', 'volume']],
            ]),
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $zat = self::pilih($isi)['zat'];
        $tanya = $isi['tanya'] ?? 'setarakan';

        if ($tanya === 'perbandingan') {
            return [];
        }

        if ((int) ($isi['zat_ke'] ?? 1) > count($zat)) {
            return ['zat_ke' => 'Reaksi ini hanya melibatkan '.count($zat).' zat.'];
        }

        if ($tanya !== 'stoikiometri') {
            return [];
        }

        if ((int) ($isi['zat_tujuan'] ?? 1) > count($zat)) {
            return ['zat_tujuan' => 'Reaksi ini hanya melibatkan '.count($zat).' zat.'];
        }

        if ((int) $isi['zat_tujuan'] === (int) $isi['zat_ke']) {
            return ['zat_tujuan' => 'Pilih zat yang berbeda dengan zat yang diketahui massanya.'];
        }

        return [];
    }

    /** @return array{nama: string, zat: array<int, array<string, mixed>>} */
    protected static function pilih(array $isi): array
    {
        return self::REAKSI[$isi['reaksi'] ?? 'air'] ?? self::REAKSI['air'];
    }

    /** Persamaan reaksi sebagai teks biasa, untuk label pilihan di formulir. */
    public static function tulis(array $zat): string
    {
        $kiri = [];
        $kanan = [];

        foreach ($zat as $z) {
            $tulis = ($z['koef'] > 1 ? $z['koef'] : '').Rumus::polos($z['rumus']);
            $z['hasil'] ? $kanan[] = $tulis : $kiri[] = $tulis;
        }

        return implode(' + ', $kiri).' → '.implode(' + ', $kanan);
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $reaksi = self::pilih($isi);
        $zat = $reaksi['zat'];
        $tanya = $isi['tanya'] ?? 'setarakan';
        $ke = min((int) ($isi['zat_ke'] ?? 1), count($zat)) - 1;

        if ($tanya === 'setarakan') {
            return $this->setarakan($reaksi, $zat, $ke, $warnaIsi, $garis);
        }

        if ($tanya === 'perbandingan') {
            return $this->perbandingan($reaksi, $zat, $warnaIsi, $garis);
        }

        if ($tanya === 'stoikiometri') {
            return $this->stoikiometri(
                $reaksi, $zat, $ke,
                min((int) ($isi['zat_tujuan'] ?? 1), count($zat)) - 1,
                (float) ($isi['massa_diketahui'] ?? 36), $warnaIsi, $garis,
            );
        }

        return $this->hitungan($reaksi, $zat, $ke, $tanya, $isi, $warnaIsi, $garis);
    }

    protected function setarakan(array $reaksi, array $zat, int $ke, string $warnaIsi, string $garis): array
    {
        $benar = $zat[$ke]['koef'];
        $rumus = Rumus::html($zat[$ke]['rumus']);

        return [
            'judul' => 'Koefisien '.Rumus::polos($zat[$ke]['rumus']).' = '.$benar,
            'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Angka yang tepat '
                ."untuk menggantikan tanda tanya di depan {$rumus} adalah ...",
            'pernyataan' => 'Pada persamaan reaksi '.$reaksi['nama'].' berikut, koefisien '
                ."{$rumus} yang tepat adalah %s.",
            'svg' => self::svgPersamaan($zat, $ke, null, null, $warnaIsi, $garis),
            'keterangan' => 'Persamaan reaksi '.$reaksi['nama'].' dengan satu koefisien disembunyikan.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) $n, 'urut' => $n],
                array_merge([$benar], array_values(array_filter(
                    array_unique([$benar + 1, $benar - 1, $benar + 2, 2, 3, 1, 4]),
                    fn ($n) => $n >= 1 && $n !== $benar,
                ))),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => 'Persamaan reaksi setara bila jumlah atom tiap unsur sama di kedua '
                .'ruas. Persamaan yang setara adalah '.self::tulis($zat).', sehingga koefisien '
                .Rumus::polos($zat[$ke]['rumus']).' adalah '.$benar.'.',
        ];
    }

    protected function perbandingan(array $reaksi, array $zat, string $warnaIsi, string $garis): array
    {
        $koef = array_column($zat, 'koef');
        $benar = implode(' : ', $koef);
        $urut = implode(' : ', array_map(fn ($z) => Rumus::html($z['rumus']), $zat));

        return [
            'judul' => 'Perbandingan koefisien '.$benar,
            'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Perbandingan '
                ."koefisien {$urut} berturut-turut adalah ...",
            'pernyataan' => 'Pada persamaan reaksi '.$reaksi['nama'].' berikut, perbandingan '
                ."koefisien {$urut} adalah %s.",
            'svg' => self::svgPersamaan($zat, null, null, null, $warnaIsi, $garis),
            'keterangan' => 'Persamaan reaksi setara '.$reaksi['nama'].'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya urutan yang tertukar dan perbandingan yang
                // disamaratakan — dua kekeliruan yang khas saat membaca
                // koefisien terlalu cepat.
                array_merge([$benar], array_values(array_diff(array_unique([
                    implode(' : ', array_reverse($koef)),
                    implode(' : ', self::tukar($koef, 0, 1)),
                    implode(' : ', array_fill(0, count($koef), 1)),
                    implode(' : ', array_map(fn ($n) => $n + 1, $koef)),
                    implode(' : ', self::tukar($koef, count($koef) - 2, count($koef) - 1)),
                    // Satu zat yang luput disetarakan. Cadangan untuk reaksi
                    // yang seluruh koefisiennya satu, tempat menukar dan
                    // membalik urutan menghasilkan perbandingan yang sama.
                    ...array_map(
                        fn ($i) => implode(' : ', array_map(
                            fn ($n, $j) => $j === $i ? $n * 2 : $n,
                            $koef, array_keys($koef),
                        )),
                        array_keys($koef),
                    ),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Perbandingan mol zat yang bereaksi sama dengan perbandingan '
                .'koefisiennya pada persamaan setara. Dari '.self::tulis($zat)
                .', perbandingannya '.$benar.'.',
        ];
    }

    /** @return array<int, int> */
    protected static function tukar(array $koef, int $i, int $j): array
    {
        [$koef[$i], $koef[$j]] = [$koef[$j], $koef[$i]];

        return $koef;
    }

    protected function hitungan(array $reaksi, array $zat, int $ke, string $tanya, array $isi, string $warnaIsi, string $garis): array
    {
        $ini = $zat[$ke];
        $rumus = Rumus::html($ini['rumus']);
        $mr = $ini['mr'];

        if ($tanya === 'mol') {
            $massa = (float) ($isi['massa_diketahui'] ?? 36);
            $benar = round($massa / $mr, 3);

            return [
                'judul' => Angka::ringkas($benar).' mol '.Rumus::polos($ini['rumus']),
                'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Bila massa '
                    ."{$rumus} yang bereaksi ".Angka::id($massa).' gram, berapa molnya? '
                    ."(M<sub>r</sub> {$rumus} = ".Angka::id($mr).')',
                'pernyataan' => 'Pada reaksi '.$reaksi['nama'].' berikut, '.Angka::id($massa)
                    ." gram {$rumus} setara dengan %s.",
                'svg' => self::svgPersamaan($zat, null, $ke, Angka::id($massa).' gram', $warnaIsi, $garis),
                'keterangan' => 'Persamaan reaksi '.$reaksi['nama'].' dengan massa '
                    .Rumus::polos($ini['rumus']).' yang diketahui.',
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::ringkas($n).' mol', 'urut' => $n],
                    array_merge([$benar], self::saring($benar, [
                        $massa * $mr, $mr / $massa, $massa, $benar * 2, $benar / 2, $benar * $mr,
                    ], 3)),
                ),
                'kata_kunci' => Angka::ringkas($benar),
                'pembahasan' => 'Mol = massa ÷ M<sub>r</sub> = '.Angka::id($massa).' ÷ '
                    .Angka::id($mr).' = '.Angka::ringkas($benar).' mol.',
            ];
        }

        $mol = (float) ($isi['mol_diketahui'] ?? 2);

        if ($tanya === 'volume') {
            $benar = round($mol * self::MOLAR, 3);

            return [
                'judul' => Angka::ringkas($benar).' liter '.Rumus::polos($ini['rumus']),
                'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Berapakah volume '
                    .Angka::id($mol)." mol {$rumus} pada keadaan standar (STP)?",
                'pernyataan' => 'Pada reaksi '.$reaksi['nama'].' berikut, volume '.Angka::id($mol)
                    ." mol {$rumus} pada keadaan standar adalah %s.",
                'svg' => self::svgPersamaan($zat, null, $ke, Angka::id($mol).' mol', $warnaIsi, $garis),
                'keterangan' => 'Persamaan reaksi '.$reaksi['nama'].' dengan mol '
                    .Rumus::polos($ini['rumus']).' yang diketahui.',
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::ringkas($n).' liter', 'urut' => $n],
                    array_merge([$benar], self::saring($benar, [
                        $mol / self::MOLAR, $mol * $mr, self::MOLAR, $benar / 2, $benar * 2, $mol,
                    ], 3)),
                ),
                'kata_kunci' => Angka::ringkas($benar),
                'pembahasan' => 'Satu mol gas pada keadaan standar bervolume '.Angka::id(self::MOLAR)
                    .' liter.<br>Volume = mol × 22,4 = '.Angka::id($mol).' × '.Angka::id(self::MOLAR)
                    .' = '.Angka::ringkas($benar).' liter.',
            ];
        }

        $benar = round($mol * $mr, 3);

        return [
            'judul' => Angka::ringkas($benar).' gram '.Rumus::polos($ini['rumus']),
            'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Berapa gram massa '
                .Angka::id($mol)." mol {$rumus}? (M<sub>r</sub> {$rumus} = ".Angka::id($mr).')',
            'pernyataan' => 'Pada reaksi '.$reaksi['nama'].' berikut, massa '.Angka::id($mol)
                ." mol {$rumus} adalah %s.",
            'svg' => self::svgPersamaan($zat, null, $ke, Angka::id($mol).' mol', $warnaIsi, $garis),
            'keterangan' => 'Persamaan reaksi '.$reaksi['nama'].' dengan mol '
                .Rumus::polos($ini['rumus']).' yang diketahui.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' gram', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    $mol / $mr, $mr / $mol, $mr, $benar * 2, $benar / 2, $mol + $mr,
                ], 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => 'Massa = mol × M<sub>r</sub> = '.Angka::id($mol).' × '.Angka::id($mr)
                .' = '.Angka::ringkas($benar).' gram.',
        ];
    }

    protected function stoikiometri(array $reaksi, array $zat, int $ke, int $tujuan, float $massa, string $warnaIsi, string $garis): array
    {
        $dari = $zat[$ke];
        $ke2 = $zat[$tujuan];

        $molDari = $massa / $dari['mr'];
        $molTujuan = $molDari * $ke2['koef'] / $dari['koef'];
        $benar = round($molTujuan * $ke2['mr'], 3);

        $rumusDari = Rumus::html($dari['rumus']);
        $rumusTujuan = Rumus::html($ke2['rumus']);

        return [
            'judul' => Angka::ringkas($benar).' gram '.Rumus::polos($ke2['rumus']),
            'teks' => 'Perhatikan persamaan reaksi '.$reaksi['nama'].' berikut. Bila '
                .Angka::id($massa)." gram {$rumusDari} bereaksi sempurna, berapa gram {$rumusTujuan} "
                ."yang terlibat? (M<sub>r</sub> {$rumusDari} = ".Angka::id($dari['mr'])
                .", M<sub>r</sub> {$rumusTujuan} = ".Angka::id($ke2['mr']).')',
            'pernyataan' => 'Pada reaksi '.$reaksi['nama'].' berikut, '.Angka::id($massa)
                ." gram {$rumusDari} setara dengan %s {$rumusTujuan}.",
            'svg' => self::svgPersamaan($zat, null, $ke, Angka::id($massa).' gram', $warnaIsi, $garis, $tujuan),
            'keterangan' => 'Persamaan reaksi '.$reaksi['nama'].'; massa '
                .Rumus::polos($dari['rumus']).' diketahui, massa '.Rumus::polos($ke2['rumus']).' ditanyakan.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' gram', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Pengecoh utamanya melupakan perbandingan koefisien, lalu
                    // membalik perbandingannya — dua kekeliruan terbesar pada
                    // hitungan stoikiometri.
                    $molDari * $ke2['mr'],
                    $molDari * $dari['koef'] / $ke2['koef'] * $ke2['mr'],
                    $massa,
                    $massa * $ke2['mr'] / $dari['mr'],
                    $molTujuan,
                    $benar * 2,
                ], 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => 'Mol '.Rumus::polos($dari['rumus']).' = massa ÷ M<sub>r</sub> = '
                .Angka::id($massa).' ÷ '.Angka::id($dari['mr']).' = '.Angka::ringkas($molDari).' mol.<br>'
                .'Perbandingan koefisien '.Rumus::polos($dari['rumus']).' : '.Rumus::polos($ke2['rumus'])
                .' = '.$dari['koef'].' : '.$ke2['koef'].', sehingga mol '.Rumus::polos($ke2['rumus'])
                .' = '.Angka::ringkas($molDari).' × '.$ke2['koef'].'/'.$dari['koef'].' = '
                .Angka::ringkas($molTujuan).' mol.<br>Massa = mol × M<sub>r</sub> = '
                .Angka::ringkas($molTujuan).' × '.Angka::id($ke2['mr']).' = '.Angka::ringkas($benar).' gram.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Persamaan reaksi: koefisien di dalam kotak, rumus zat di sampingnya,
     * dan tanda panah memisahkan pereaksi dari hasil reaksi.
     *
     * @param  array<int, array<string, mixed>>  $zat
     * @param  int|null  $sembunyi  koefisien yang diganti tanda tanya
     * @param  int|null  $ditandai  zat yang diberi keterangan di bawahnya
     * @param  int|null  $ditanya  zat yang diberi keterangan "? gram"
     */
    public static function svgPersamaan(array $zat, ?int $sembunyi, ?int $ditandai, ?string $catatan, string $isi, string $garis, ?int $ditanya = null): string
    {
        $kanvas = new Kanvas;

        $ukuran = 22;
        $lebarKotak = 34;
        $pemisah = 36;

        $total = self::lebarPersamaan($zat, $ukuran, $lebarKotak, $pemisah);

        // Persamaan yang panjang dikecilkan hurufnya, bukan dipotong: yang
        // hilang sebagian justru membuat soalnya tidak terjawab.
        if ($total > 448) {
            $ukuran = max(13, (int) floor($ukuran * 448 / $total));
            $lebarKotak = 28;
            $pemisah = 22;
            $total = self::lebarPersamaan($zat, $ukuran, $lebarKotak, $pemisah);
        }

        $lebar = fn ($z) => $lebarKotak + 8 + mb_strlen(Rumus::polos($z['rumus'])) * $ukuran * 0.58;

        $y = 148;
        $x = (Kanvas::LEBAR - $total) / 2;
        $letak = [];

        foreach ($zat as $i => $z) {
            // Tanda panah menggantikan tanda tambah tepat sebelum zat hasil
            // reaksi yang pertama.
            if ($i > 0) {
                $awalHasil = $z['hasil'] && ! $zat[$i - 1]['hasil'];

                $kanvas->teksPx([$x - $pemisah / 2, $y + 8], $awalHasil ? '→' : '+', [
                    'ukuran' => $ukuran + ($awalHasil ? 4 : 0), 'tebal' => true,
                ]);
            }

            $iniSembunyi = $sembunyi === $i;

            $kanvas->kotakPx([$x + $lebarKotak / 2, $y], $lebarKotak, 40,
                $iniSembunyi ? '?' : (string) $z['koef'],
                $iniSembunyi ? $isi : '#f2f4f6', $garis, [
                    'ukuran' => $ukuran, 'tebal' => true, 'sudut' => 6,
                    'tebal_garis' => $iniSembunyi ? 3.5 : 1.8,
                ]);

            $tengah = $x + $lebarKotak + 8 + ($lebar($z) - $lebarKotak - 8) / 2;
            $kanvas->rumusPx([$tengah, $y + 9], $z['rumus'], ['ukuran' => $ukuran, 'tebal' => true]);

            $letak[$i] = $tengah;
            $x += $lebar($z) + $pemisah;
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 60], 'angka di dalam kotak = koefisien reaksi', [
            'ukuran' => 12, 'warna' => '#6b7280',
        ]);

        foreach ([[$ditandai, $catatan, Kanvas::HITAM], [$ditanya, '? gram', $garis]] as [$ke, $tulis, $warna]) {
            if ($ke === null || $tulis === null) {
                continue;
            }

            $kanvas->garis([$letak[$ke], $y + 26], [$letak[$ke], $y + 54], $warna, 2)
                ->teksPx([$letak[$ke], $y + 74], $tulis, ['ukuran' => 16, 'tebal' => true, 'warna' => $warna]);
        }

        return $kanvas->render();
    }

    /** Lebar seluruh persamaan pada ukuran huruf tertentu, dalam piksel. */
    protected static function lebarPersamaan(array $zat, int $ukuran, float $lebarKotak, float $pemisah): float
    {
        return array_sum(array_map(
            fn ($z) => $lebarKotak + 8 + mb_strlen(Rumus::polos($z['rumus'])) * $ukuran * 0.58,
            $zat,
        )) + (count($zat) - 1) * $pemisah;
    }
}

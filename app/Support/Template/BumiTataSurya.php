<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template bumi & tata surya.
 *
 * Seluruh gambarnya tersusun dari lingkaran, jadi bisa dibangkitkan dengan
 * benar tanpa ilustrasi: urutan planet, kedudukan matahari–bumi–bulan saat
 * gerhana, fase bulan sebagai bagian yang tersinari, dan lapisan bumi
 * sebagai lingkaran sepusat.
 */
class BumiTataSurya extends Template
{
    /**
     * Planet berurutan dari matahari beserta warnanya.
     *
     * @var array<int, array{nama: string, warna: string, r: float}>
     */
    public const PLANET = [
        ['nama' => 'Merkurius', 'warna' => '#9e9e9e', 'r' => 8],
        ['nama' => 'Venus', 'warna' => '#e0b974', 'r' => 12],
        ['nama' => 'Bumi', 'warna' => '#3f7fd0', 'r' => 13],
        ['nama' => 'Mars', 'warna' => '#c15a3a', 'r' => 10],
        ['nama' => 'Yupiter', 'warna' => '#d9a441', 'r' => 24],
        ['nama' => 'Saturnus', 'warna' => '#e3cf9a', 'r' => 21],
        ['nama' => 'Uranus', 'warna' => '#8fd0d8', 'r' => 16],
        ['nama' => 'Neptunus', 'warna' => '#4f6fd0', 'r' => 15],
    ];

    /**
     * Lapisan bumi dari luar ke dalam.
     *
     * @var array<int, array{nama: string, warna: string}>
     */
    public const LAPISAN = [
        ['nama' => 'Kerak bumi', 'warna' => '#7a9e5a'],
        ['nama' => 'Selubung (mantel)', 'warna' => '#d9a441'],
        ['nama' => 'Inti luar', 'warna' => '#e06c3a'],
        ['nama' => 'Inti dalam', 'warna' => '#c43c2a'],
    ];

    /** @var array<string, string> */
    public const FASE = [
        'baru' => 'Bulan baru',
        'sabit' => 'Bulan sabit',
        'separuh' => 'Bulan separuh (perbani)',
        'purnama' => 'Bulan purnama',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'planet' => 'Nama planet pada urutan tertentu',
        'gerhana' => 'Jenis gerhana dari kedudukan benda langit',
        'fase_bulan' => 'Fase bulan yang tergambar',
        'lapisan' => 'Lapisan bumi yang ditandai',
    ];

    public static function kode(): string
    {
        return 'bumi-tata-surya';
    }

    public static function nama(): string
    {
        return 'Bumi & Tata Surya';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Urutan planet, gerhana matahari dan bulan, fase bulan, serta '
            .'lapisan-lapisan bumi.';
    }

    public static function ikon(): string
    {
        return 'bi-globe-americas';
    }

    public static function kataKunci(): array
    {
        return ['tata surya', 'planet', 'gerhana', 'bulan', 'matahari', 'bumi',
            'lapisan bumi', 'rotasi', 'revolusi', 'astronomi'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'planet'),
            self::kolomAngka('urutan', 'Planet urutan ke- (dari matahari)', 5, 1, 8, [
                'langkah' => 1, 'tampil' => ['tanya' => ['planet']],
            ]),
            [
                'nama' => 'gerhana',
                'label' => 'Kedudukan yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => ['matahari' => 'Bulan di antara matahari dan bumi', 'bulan' => 'Bumi di antara matahari dan bulan'],
                'awal' => 'matahari',
                'tampil' => ['tanya' => ['gerhana']],
            ],
            [
                'nama' => 'fase',
                'label' => 'Fase yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => self::FASE,
                'awal' => 'sabit',
                'tampil' => ['tanya' => ['fase_bulan']],
            ],
            self::kolomAngka('lapisan_ke', 'Lapisan ke- (dari luar)', 1, 1, 4, [
                'langkah' => 1, 'tampil' => ['tanya' => ['lapisan']],
            ]),
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($isi['tanya'] ?? 'planet') {
            'gerhana' => $this->gerhana($isi['gerhana'] ?? 'matahari'),
            'fase_bulan' => $this->faseBulan($isi['fase'] ?? 'sabit'),
            'lapisan' => $this->lapisan((int) ($isi['lapisan_ke'] ?? 1)),
            default => $this->planet((int) ($isi['urutan'] ?? 5), $warnaIsi, $garis),
        };
    }

    protected function planet(int $urutan, string $warnaIsi, string $garis): array
    {
        $benar = self::PLANET[$urutan - 1]['nama'];
        $lain = array_values(array_diff(array_column(self::PLANET, 'nama'), [$benar]));

        return [
            'judul' => 'Planet ke-'.$urutan.': '.$benar,
            'teks' => 'Perhatikan gambar tata surya berikut. Planet yang ditandai tanda tanya adalah ...',
            'pernyataan' => 'Perhatikan gambar tata surya berikut. Planet yang ditandai tanda tanya adalah %s.',
            'svg' => self::svgTataSurya($urutan),
            'keterangan' => 'Delapan planet berurutan dari matahari; planet ke-'.$urutan.' ditandai.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                // Pengecohnya planet tetangga — itulah yang paling sering
                // tertukar urutannya. Pada planet terluar jendelanya digeser
                // ke dalam, sebab di luar Neptunus tidak ada tetangga lagi.
                array_merge([$benar], array_slice(array_values(array_filter(
                    array_column(self::PLANET, 'nama'),
                    fn ($n) => $n !== $benar,
                )), min(count(self::PLANET) - 4, max(0, $urutan - 3)), 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan planet dari matahari: '
                .implode(', ', array_column(self::PLANET, 'nama')).". Urutan ke-{$urutan} adalah {$benar}.",
        ];
    }

    protected function gerhana(string $jenis): array
    {
        $benar = $jenis === 'matahari' ? 'Gerhana matahari' : 'Gerhana bulan';

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar kedudukan matahari, bumi, dan bulan berikut. '
                .'Peristiwa apakah yang terjadi?',
            'pernyataan' => 'Perhatikan gambar kedudukan matahari, bumi, dan bulan berikut. '
                .'Peristiwa yang terjadi adalah %s.',
            'svg' => self::svgGerhana($jenis),
            'keterangan' => $jenis === 'matahari'
                ? 'Bulan berada di antara matahari dan bumi.'
                : 'Bumi berada di antara matahari dan bulan.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$benar], array_values(array_diff(
                    ['Gerhana matahari', 'Gerhana bulan', 'Rotasi bumi', 'Revolusi bumi'],
                    [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $jenis === 'matahari'
                ? 'Bulan berada di antara matahari dan bumi sehingga bayangan bulan jatuh ke bumi. '
                    .'Peristiwa itu disebut gerhana matahari.'
                : 'Bumi berada di antara matahari dan bulan sehingga bulan masuk ke bayangan bumi. '
                    .'Peristiwa itu disebut gerhana bulan.',
        ];
    }

    protected function faseBulan(string $fase): array
    {
        $benar = self::FASE[$fase];

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar bulan berikut. Bagian terang pada gambar menunjukkan fase ...',
            'pernyataan' => 'Perhatikan gambar bulan berikut. Bagian terangnya menunjukkan fase %s.',
            'svg' => self::svgFase($fase),
            'keterangan' => 'Bulan dengan bagian terang sesuai fase '.mb_strtolower($benar).'.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$benar], array_values(array_diff(array_values(self::FASE), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Fase bulan ditentukan oleh seberapa besar bagian bulan yang tersinari '
                .'matahari dan terlihat dari bumi. Gambar itu menunjukkan '.mb_strtolower($benar).'.',
        ];
    }

    protected function lapisan(int $ke): array
    {
        $benar = self::LAPISAN[$ke - 1]['nama'];

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar penampang bumi berikut. Lapisan yang ditandai huruf X adalah ...',
            'pernyataan' => 'Perhatikan gambar penampang bumi berikut. Lapisan yang ditandai huruf X adalah %s.',
            'svg' => self::svgLapisan($ke),
            'keterangan' => 'Penampang bumi dengan empat lapisan; lapisan ke-'.$ke.' dari luar ditandai X.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$benar], array_values(array_diff(array_column(self::LAPISAN, 'nama'), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Lapisan bumi dari luar ke dalam: '
                .implode(', ', array_column(self::LAPISAN, 'nama')).". Lapisan ke-{$ke} adalah {$benar}.",
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgTataSurya(int $ditandai): string
    {
        $kanvas = new Kanvas(latar: '#10141c');

        $matahari = [26, Kanvas::TINGGI / 2];

        $kanvas->lingkaran($matahari, 56, '#f6c445', '#c08a10', 2);

        $jarak = (Kanvas::LEBAR - 110) / 8;

        foreach (self::PLANET as $i => $planet) {
            $x = 96 + $i * $jarak;
            $y = Kanvas::TINGGI / 2;

            // Garis edarnya digambar samar supaya urutannya terbaca sebagai
            // orbit, bukan sebagai deretan bola biasa.
            $kanvas->elips($matahari, $x - $matahari[0], ($x - $matahari[0]) * 0.42, 'none', '#39404d', 1);

            if ($i + 1 === $ditandai) {
                $kanvas->lingkaran([$x, $y], $planet['r'] + 10, 'none', '#f6c445', 2.5)
                    ->teksPx([$x, $y - $planet['r'] - 18], '?', [
                        'ukuran' => 24, 'tebal' => true, 'warna' => '#f6c445',
                    ]);
            }

            $kanvas->lingkaran([$x, $y], $planet['r'], $planet['warna'], '#10141c', 1);

            if ($planet['nama'] === 'Saturnus') {
                $kanvas->elips([$x, $y], $planet['r'] + 12, ($planet['r'] + 12) * 0.3, 'none', '#e3cf9a', 2);
            }
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, Kanvas::TINGGI - 16], 'urutan planet dari matahari', [
            'ukuran' => 12, 'warna' => '#8a929a',
        ]);

        return $kanvas->render();
    }

    public static function svgGerhana(string $jenis): string
    {
        $kanvas = new Kanvas(latar: '#10141c');
        $y = Kanvas::TINGGI / 2;

        $kanvas->lingkaran([40, $y], 54, '#f6c445', '#c08a10', 2)
            ->teksPx([40, $y + 78], 'Matahari', ['ukuran' => 12, 'warna' => '#e4e7ea']);

        // Berkas cahaya matahari digambar mendatar ke kanan; benda yang ada
        // di tengahlah yang menghalanginya.
        foreach ([-26, 0, 26] as $geser) {
            $kanvas->garis([94, $y + $geser], [Kanvas::LEBAR - 20, $y + $geser], '#f6c44533', 2);
        }

        [$tengah, $namaTengah, $jauh, $namaJauh, $rTengah, $rJauh] = $jenis === 'matahari'
            ? [240, 'Bulan', 390, 'Bumi', 18, 44]
            : [240, 'Bumi', 390, 'Bulan', 44, 18];

        foreach ([[$tengah, $rTengah, $namaTengah], [$jauh, $rJauh, $namaJauh]] as [$x, $r, $nama]) {
            $kanvas->lingkaran([$x, $y], $r, $nama === 'Bumi' ? '#3f7fd0' : '#c9ccd1', '#10141c', 1.5)
                ->teksPx([$x, $y + $r + 24], $nama, ['ukuran' => 12, 'warna' => '#e4e7ea']);
        }

        // Bayangan benda penghalang: kerucut gelap ke arah benda di belakangnya.
        $kanvas->poligon([
            [$tengah + $rTengah, $y - $rTengah],
            [$jauh + $rJauh + 30, $y - 4],
            [$jauh + $rJauh + 30, $y + 4],
            [$tengah + $rTengah, $y + $rTengah],
        ], '#00000099', 'none', 0);

        return $kanvas->render();
    }

    public static function svgFase(string $fase): string
    {
        $kanvas = new Kanvas(latar: '#10141c');
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $r = 100;

        $kanvas->lingkaran($pusat, $r, '#2a303a', '#4d5663', 2);

        match ($fase) {
            'purnama' => $kanvas->lingkaran($pusat, $r, '#eceff3', '#aab1b8', 2),
            'separuh' => $kanvas->tambah('<path d="M '.$pusat[0].' '.($pusat[1] - $r).' A '.$r.' '.$r
                .' 0 0 1 '.$pusat[0].' '.($pusat[1] + $r).' Z" fill="#eceff3"/>'),
            'sabit' => $kanvas->tambah('<path d="M '.$pusat[0].' '.($pusat[1] - $r).' A '.$r.' '.$r
                .' 0 0 1 '.$pusat[0].' '.($pusat[1] + $r).' A '.($r * 0.55).' '.$r
                .' 0 0 0 '.$pusat[0].' '.($pusat[1] - $r).' Z" fill="#eceff3"/>'),
            default => null,
        };

        $kanvas->lingkaran($pusat, $r, 'none', '#aab1b8', 2)
            ->teksPx([$pusat[0], Kanvas::TINGGI - 16], 'bagian terang terlihat dari bumi', [
                'ukuran' => 12, 'warna' => '#8a929a',
            ]);

        return $kanvas->render();
    }

    public static function svgLapisan(int $ditandai): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $jari = [128, 100, 66, 34];

        foreach (self::LAPISAN as $i => $lapisan) {
            $kanvas->lingkaran($pusat, $jari[$i], $lapisan['warna'], Kanvas::HITAM, 2);
        }

        // Huruf X ditaruh di tengah tebal lapisan yang ditanyakan, dengan
        // penunjuk keluar lingkaran supaya tidak menutupi lapisan lain.
        $luar = $jari[$ditandai - 1];
        $dalam = $jari[$ditandai] ?? 0;
        $tengah = ($luar + $dalam) / 2;

        $sudut = deg2rad(-35);
        $titik = [$pusat[0] + $tengah * cos($sudut), $pusat[1] + $tengah * sin($sudut)];
        $ujung = [$pusat[0] + ($jari[0] + 46) * cos($sudut), $pusat[1] + ($jari[0] + 46) * sin($sudut)];

        $kanvas->garis($titik, $ujung, Kanvas::HITAM, 2)
            ->lingkaran($titik, 5, '#ffffff', Kanvas::HITAM, 2)
            ->teksPx([$ujung[0] + 10, $ujung[1] + 6], 'X', [
                'anchor' => 'start', 'ukuran' => 22, 'tebal' => true,
            ]);

        return $kanvas->render();
    }
}

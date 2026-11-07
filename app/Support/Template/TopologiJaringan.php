<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template topologi jaringan.
 *
 * Tiap bagan dibangkitkan dari aturan sambungannya — satu kabel utama, satu
 * pusat, satu lingkaran, saling terhubung, atau bertingkat — sehingga gambar
 * dan ciri yang ditanyakan selalu sejalan. Pertanyaan tentang akibat
 * putusnya sambungan pun dijawab dari gambarnya: terlihat ada atau tidaknya
 * jalur cadangan.
 */
class TopologiJaringan extends Template
{
    /**
     * Lima topologi beserta ciri dan akibat putusnya sambungan utamanya.
     *
     * @var array<string, array{nama: string, ciri: string, putus: string, akibat: string}>
     */
    public const TOPOLOGI = [
        'bus' => [
            'nama' => 'Topologi bus',
            'ciri' => 'semua komputer terhubung pada satu kabel utama',
            'putus' => 'kabel utamanya putus',
            'akibat' => 'seluruh jaringan terganggu karena tidak ada jalur lain',
        ],
        'bintang' => [
            'nama' => 'Topologi bintang (star)',
            'ciri' => 'semua komputer terhubung ke satu perangkat pusat',
            'putus' => 'perangkat pusatnya rusak',
            'akibat' => 'seluruh jaringan terganggu karena tidak ada jalur lain',
        ],
        'cincin' => [
            'nama' => 'Topologi cincin (ring)',
            'ciri' => 'tiap komputer terhubung ke dua komputer di sampingnya membentuk lingkaran',
            'putus' => 'salah satu sambungannya putus',
            'akibat' => 'seluruh jaringan terganggu karena data tidak dapat menyelesaikan putarannya',
        ],
        'jala' => [
            'nama' => 'Topologi jala (mesh)',
            'ciri' => 'tiap komputer terhubung langsung ke semua komputer lain',
            'putus' => 'salah satu sambungannya putus',
            'akibat' => 'jaringan tetap berjalan karena masih ada jalur lain',
        ],
        'pohon' => [
            'nama' => 'Topologi pohon (tree)',
            'ciri' => 'beberapa jaringan bintang disusun bertingkat',
            'putus' => 'sambungan sebuah cabang putus',
            'akibat' => 'hanya komputer di bawah cabang itu yang terganggu',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'nama' => 'Nama topologi yang digambar',
        'ciri' => 'Ciri susunan sambungannya',
        'akibat' => 'Akibat bila sambungan utamanya putus',
    ];

    public static function kode(): string
    {
        return 'topologi-jaringan';
    }

    public static function nama(): string
    {
        return 'Topologi Jaringan';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Bagan lima topologi jaringan — bus, bintang, cincin, jala, dan '
            .'pohon: mengenali namanya, ciri sambungannya, dan akibat bila putus.';
    }

    public static function ikon(): string
    {
        return 'bi-hdd-network';
    }

    public static function kataKunci(): array
    {
        return ['topologi', 'jaringan', 'komputer', 'bus', 'bintang', 'star', 'cincin',
            'ring', 'mesh', 'jala', 'pohon', 'tree', 'lan', 'hub', 'switch', 'informatika'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'topologi',
                'label' => 'Topologi yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($t) => $t['nama'], self::TOPOLOGI),
                'awal' => 'bintang',
            ],
            self::kolomTanya(self::TANYA, 'nama'),
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $kunci = isset(self::TOPOLOGI[$isi['topologi'] ?? '']) ? $isi['topologi'] : 'bintang';
        $topologi = self::TOPOLOGI[$kunci];
        $tanya = $isi['tanya'] ?? 'nama';

        $bahan = [
            'svg' => self::svgTopologi($kunci, $warnaIsi, $garis),
            'keterangan' => $topologi['nama'].': '.$topologi['ciri'].'.',
        ];

        $awalan = 'Perhatikan bagan jaringan komputer berikut.';

        if ($tanya === 'ciri') {
            return $bahan + [
                'judul' => $topologi['nama'].': cirinya',
                'teks' => $awalan.' Susunan sambungan pada bagan itu adalah ...',
                'pernyataan' => $awalan.' Pada bagan itu %s.',
                'pilihan' => self::pilihan($kunci, 'ciri'),
                'kata_kunci' => $topologi['ciri'],
                'pembahasan' => 'Pada '.mb_strtolower($topologi['nama']).', '.$topologi['ciri']
                    .'. Itulah yang terlihat pada bagannya.',
            ];
        }

        if ($tanya === 'akibat') {
            return $bahan + [
                'judul' => $topologi['nama'].': akibat putus',
                'teks' => $awalan.' Apa yang terjadi bila '.$topologi['putus'].'?',
                'pernyataan' => $awalan.' Bila '.$topologi['putus'].', %s.',
                'pilihan' => self::pilihan($kunci, 'akibat'),
                'kata_kunci' => $topologi['akibat'],
                'pembahasan' => 'Pada bagan itu, '.$topologi['ciri'].'. Karena itu bila '
                    .$topologi['putus'].', '.$topologi['akibat'].'.',
            ];
        }

        return $bahan + [
            'judul' => $topologi['nama'],
            'teks' => $awalan.' Bagan itu menunjukkan ...',
            'pernyataan' => $awalan.' Bagan itu menunjukkan %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$topologi['nama']], array_values(array_diff(
                    array_column(self::TOPOLOGI, 'nama'), [$topologi['nama']],
                ))),
            ),
            'kata_kunci' => $topologi['nama'],
            'pembahasan' => 'Pada bagan itu '.$topologi['ciri'].', yang merupakan ciri '
                .mb_strtolower($topologi['nama']).'.',
        ];
    }

    /**
     * Pilihan berupa ciri atau akibat dari topologi lain. Semuanya pernyataan
     * yang benar untuk salah satu topologi pada daftar, jadi tidak ada
     * pilihan yang bisa dicoret hanya karena terdengar mustahil.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihan(string $kunci, string $bagian): array
    {
        $benar = self::TOPOLOGI[$kunci][$bagian];

        return array_map(
            fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
            array_merge([$benar], array_values(array_diff(
                array_unique(array_column(self::TOPOLOGI, $bagian)), [$benar],
            ))),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Satu komputer: layar beserta kakinya, cukup untuk dikenali sebagai simpul. */
    protected static function komputer(Kanvas $kanvas, array $pusat, string $isi, string $garis): Kanvas
    {
        [$x, $y] = [$pusat[0] - 17, $pusat[1] - 14];

        return $kanvas
            ->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="34" height="24" rx="3" fill="'
                .$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
            ->garis([$pusat[0], $pusat[1] + 10], [$pusat[0], $pusat[1] + 16], $garis, 2)
            ->garis([$pusat[0] - 10, $pusat[1] + 16], [$pusat[0] + 10, $pusat[1] + 16], $garis, 2.5);
    }

    /** Titik-titik simpul sebuah topologi beserta sambungannya. */
    public static function svgTopologi(string $kunci, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [240, 158];

        $kanvas->teksPx([240, 30], 'Bagan jaringan komputer', ['ukuran' => 15, 'tebal' => true]);

        // Sambungan digambar lebih dahulu supaya ujungnya tertutup gambar
        // komputernya, bukan menempel di sisinya.
        $titik = match ($kunci) {
            'bus' => self::bus($kanvas, $garis),
            'bintang' => self::bintang($kanvas, $pusat, $isi, $garis),
            'cincin' => self::melingkar($kanvas, $pusat, 6, false, $garis),
            'jala' => self::melingkar($kanvas, $pusat, 5, true, $garis),
            default => self::pohon($kanvas, $isi, $garis),
        };

        foreach ($titik as $t) {
            self::komputer($kanvas, $t, $isi, $garis);
        }

        return $kanvas->render();
    }

    /** @return array<int, array<int, float>> */
    protected static function bus(Kanvas $kanvas, string $garis): array
    {
        $titik = [];

        $kanvas->garis([48, 158], [432, 158], Kanvas::HITAM, 3.5);

        foreach ([70, 138, 206, 274, 342, 410] as $i => $x) {
            $y = $i % 2 === 0 ? 96 : 220;
            $titik[] = [$x, $y];

            $kanvas->garis([$x, $y + ($i % 2 === 0 ? 18 : -16)], [$x, 158], $garis, 2);
        }

        $kanvas->teksPx([240, 286], 'satu kabel utama menghubungkan semuanya', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $titik;
    }

    /** @return array<int, array<int, float>> */
    protected static function bintang(Kanvas $kanvas, array $pusat, string $isi, string $garis): array
    {
        $titik = [];

        for ($i = 0; $i < 6; $i++) {
            $sudut = deg2rad(-90 + $i * 60);
            $t = [$pusat[0] + cos($sudut) * 112, $pusat[1] + sin($sudut) * 98];
            $titik[] = $t;

            $kanvas->garis($pusat, $t, $garis, 2);
        }

        $kanvas->kotakPx($pusat, 62, 34, 'hub', $isi, $garis, ['ukuran' => 13, 'tebal' => true])
            ->teksPx([240, 286], 'semua sambungan melewati satu perangkat pusat', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ]);

        return $titik;
    }

    /**
     * Simpul yang ditata melingkar: disambung berurutan untuk cincin, atau
     * disambung ke semua simpul lain untuk jala.
     *
     * @return array<int, array<int, float>>
     */
    protected static function melingkar(Kanvas $kanvas, array $pusat, int $banyak, bool $penuh, string $garis): array
    {
        $titik = [];

        for ($i = 0; $i < $banyak; $i++) {
            $sudut = deg2rad(-90 + $i * 360 / $banyak);
            $titik[] = [$pusat[0] + cos($sudut) * 128, $pusat[1] + sin($sudut) * 102];
        }

        foreach ($titik as $i => $a) {
            foreach ($titik as $j => $b) {
                if ($j <= $i || (! $penuh && $j !== $i + 1 && ! ($i === 0 && $j === $banyak - 1))) {
                    continue;
                }

                $kanvas->garis($a, $b, $garis, 2);
            }
        }

        $kanvas->teksPx([240, 286], $penuh
            ? 'tiap komputer tersambung langsung ke semua komputer lain'
            : 'tiap komputer tersambung ke dua komputer di sampingnya',
            ['ukuran' => 11, 'warna' => '#6b7280']);

        return $titik;
    }

    /** @return array<int, array<int, float>> */
    protected static function pohon(Kanvas $kanvas, string $isi, string $garis): array
    {
        $akar = [240, 66];
        $cabang = [[136, 158], [344, 158]];
        $daun = [[72, 244], [196, 244], [284, 244], [408, 244]];

        foreach ($cabang as $i => $c) {
            $kanvas->garis($akar, $c, $garis, 2);

            foreach (array_slice($daun, $i * 2, 2) as $d) {
                $kanvas->garis($c, $d, $garis, 2);
            }
        }

        $kanvas->kotakPx($akar, 62, 32, 'hub', $isi, $garis, ['ukuran' => 13, 'tebal' => true]);

        foreach ($cabang as $c) {
            $kanvas->kotakPx($c, 56, 30, 'hub', '#f2f4f6', $garis, ['ukuran' => 12]);
        }

        $kanvas->teksPx([240, 286], 'beberapa jaringan bintang disusun bertingkat', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $daun;
    }
}

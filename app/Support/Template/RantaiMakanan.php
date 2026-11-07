<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template rantai makanan: peran organisme dan akibat terputusnya rantai.
 *
 * Bagannya dibangkitkan dari daftar organisme berurutan, sehingga peran tiap
 * organisme, tingkat trofiknya, dan akibat hilangnya salah satu di antaranya
 * selalu dihitung dari rantai yang sama dengan yang tergambar. Ekosistemnya
 * tinggal ditambahkan di tetapan di bawah untuk memperbanyak variasi soal.
 */
class RantaiMakanan extends Template
{
    /**
     * Rantai makanan per ekosistem, berurutan dari produsen ke konsumen
     * puncak, beserta penguraiannya.
     *
     * @var array<string, array{nama: string, rantai: array<int, string>, pengurai: string}>
     */
    public const EKOSISTEM = [
        'sawah' => [
            'nama' => 'sawah',
            'rantai' => ['padi', 'belalang', 'katak', 'ular sawah', 'elang'],
            'pengurai' => 'jamur',
        ],
        'laut' => [
            'nama' => 'laut',
            'rantai' => ['fitoplankton', 'zooplankton', 'ikan kecil', 'ikan tongkol', 'hiu'],
            'pengurai' => 'bakteri',
        ],
        'kebun' => [
            'nama' => 'kebun',
            'rantai' => ['daun jambu', 'ulat', 'burung pipit', 'burung elang'],
            'pengurai' => 'jamur',
        ],
        'padang' => [
            'nama' => 'padang rumput',
            'rantai' => ['rumput', 'zebra', 'singa'],
            'pengurai' => 'bakteri',
        ],
        'kolam' => [
            'nama' => 'kolam',
            'rantai' => ['lumut', 'ikan nila', 'bangau'],
            'pengurai' => 'bakteri',
        ],
    ];

    /** @var array<int, string> */
    public const ROMAWI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

    /** @var array<string, string> */
    public const TANYA = [
        'peran' => 'Peran organisme pada urutan tertentu',
        'trofik' => 'Tingkat trofik organisme',
        'organisme' => 'Organisme yang hilang dari rantai',
        'akibat' => 'Akibat bila satu organisme musnah',
        'pengurai' => 'Peran jamur dan bakteri',
    ];

    public static function kode(): string
    {
        return 'rantai-makanan';
    }

    public static function nama(): string
    {
        return 'Rantai & Jaring Makanan';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Bagan rantai makanan di lima ekosistem: peran tiap organisme, '
            .'tingkat trofik, dan akibat bila satu mata rantai musnah.';
    }

    public static function ikon(): string
    {
        return 'bi-link';
    }

    public static function kataKunci(): array
    {
        return ['rantai makanan', 'jaring makanan', 'ekosistem', 'produsen', 'konsumen',
            'pengurai', 'dekomposer', 'trofik', 'ekologi', 'herbivora', 'karnivora'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'ekosistem',
                'label' => 'Ekosistem',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($e) => ucfirst($e['nama']).' ('.implode(' → ', $e['rantai']).')',
                    self::EKOSISTEM,
                ),
                'awal' => 'sawah',
            ],
            self::kolomTanya(self::TANYA, 'peran'),
            self::kolomAngka('posisi', 'Organisme urutan ke- (dari produsen)', 3, 1, 5, [
                'langkah' => 1, 'tampil' => ['tanya' => ['peran', 'trofik', 'organisme', 'akibat']],
            ]),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $rantai = self::pilih($isi)['rantai'];
        $posisi = (int) ($isi['posisi'] ?? 1);
        $tanya = $isi['tanya'] ?? 'peran';

        if (! in_array($tanya, ['peran', 'trofik', 'organisme', 'akibat'], true)) {
            return [];
        }

        if ($posisi > count($rantai)) {
            return ['posisi' => 'Rantai ini hanya punya '.count($rantai).' organisme.'];
        }

        // Organisme di ujung rantai tidak punya dua tetangga sekaligus,
        // sehingga akibat hilangnya hanya bisa dinyatakan sebagian — soalnya
        // jadi tidak setara dengan pengecohnya.
        if ($tanya === 'akibat' && ($posisi < 2 || $posisi >= count($rantai))) {
            return ['posisi' => 'Pilih organisme di tengah rantai, antara urutan ke-2 dan ke-'
                .(count($rantai) - 1).'.'];
        }

        return [];
    }

    /** @return array{nama: string, rantai: array<int, string>, pengurai: string} */
    protected static function pilih(array $isi): array
    {
        return self::EKOSISTEM[$isi['ekosistem'] ?? 'sawah'] ?? self::EKOSISTEM['sawah'];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $eko = self::pilih($isi);
        $rantai = $eko['rantai'];
        $tanya = $isi['tanya'] ?? 'peran';
        $ke = min((int) ($isi['posisi'] ?? 1), count($rantai));
        $i = $ke - 1;

        $tandai = $tanya === 'pengurai' ? count($rantai) + 1 : $ke;
        $svg = self::svgRantai($rantai, $eko['pengurai'], $eko['nama'], $tandai,
            $tanya === 'organisme', $warnaIsi, $garis);

        $bahan = [
            'svg' => $svg,
            'keterangan' => 'Rantai makanan di ekosistem '.$eko['nama'].': '
                .implode(' → ', $rantai).'.',
        ];

        $awalan = 'Perhatikan bagan rantai makanan pada ekosistem '.$eko['nama'].' berikut.';

        return $bahan + match ($tanya) {
            'trofik' => $this->trofik($awalan, $rantai, $i),
            'organisme' => $this->organisme($awalan, $eko, $i),
            'akibat' => $this->akibat($awalan, $rantai, $i),
            'pengurai' => $this->pengurai($awalan, $eko),
            default => $this->peran($awalan, $rantai, $i),
        };
    }

    /** Peran organisme pada urutan tertentu: produsen, atau konsumen tingkat ke-berapa. */
    protected static function sebutanPeran(int $i): string
    {
        return $i === 0 ? 'Produsen' : 'Konsumen tingkat '.self::ROMAWI[$i - 1];
    }

    protected function peran(string $awalan, array $rantai, int $i): array
    {
        $benar = self::sebutanPeran($i);
        $nama = $rantai[$i];

        return [
            'judul' => ucfirst($nama).' sebagai '.mb_strtolower($benar),
            'teks' => $awalan." Apa peran {$nama} dalam rantai makanan tersebut?",
            'pernyataan' => $awalan." Peran {$nama} dalam rantai makanan tersebut adalah %s.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya peran yang bertetangga pada rantai yang sama
                // beserta "pengurai" — dua kekeliruan yang paling sering
                // muncul: salah menghitung tingkat, dan menyamakan hewan
                // pemakan bangkai dengan pengurai.
                array_merge([$benar], array_values(array_diff(array_unique([
                    self::sebutanPeran(max(0, $i - 1)),
                    self::sebutanPeran($i + 1),
                    'Pengurai (dekomposer)',
                    'Produsen',
                    self::sebutanPeran($i + 2),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $i === 0
                ? ucfirst($nama).' berada di awal rantai dan dapat membuat makanannya sendiri '
                    .'melalui fotosintesis, sehingga berperan sebagai produsen.'
                : ucfirst($nama).' memakan '.$rantai[$i - 1].' yang berada tepat sebelumnya. '
                    .'Karena berada pada urutan ke-'.($i + 1).' setelah produsen, '
                    .$nama.' berperan sebagai '.mb_strtolower($benar).'.',
        ];
    }

    protected function trofik(string $awalan, array $rantai, int $i): array
    {
        $benar = 'Tingkat trofik ke-'.($i + 1);
        $nama = $rantai[$i];

        return [
            'judul' => ucfirst($nama).' pada tingkat trofik ke-'.($i + 1),
            'teks' => $awalan." Pada tingkat trofik keberapakah {$nama} berada?",
            'pernyataan' => $awalan." Organisme {$nama} berada pada %s.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => 'Tingkat trofik ke-'.$n, 'urut' => $n],
                // Tingkat di sekitarnya, karena yang keliru biasanya bergeser
                // satu — bukan meleset jauh.
                array_merge([$i + 1], array_values(array_filter(
                    array_unique([$i, $i + 2, $i - 1, $i + 3, 2, 3, 4]),
                    fn ($n) => $n >= 1 && $n !== $i + 1,
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Tingkat trofik dihitung mulai dari produsen sebagai tingkat pertama: '
                .implode(' → ', array_map(
                    fn ($n, $k) => $n.' (ke-'.($k + 1).')',
                    $rantai, array_keys($rantai),
                )).". Jadi {$nama} berada pada tingkat trofik ke-".($i + 1).'.',
        ];
    }

    protected function organisme(string $awalan, array $eko, int $i): array
    {
        $rantai = $eko['rantai'];
        $benar = $rantai[$i];

        return [
            'judul' => 'Mata rantai yang hilang: '.$benar,
            'teks' => $awalan.' Organisme yang tepat untuk mengisi kotak bertanda tanya adalah ...',
            'pernyataan' => $awalan.' Kotak bertanda tanya pada bagan itu seharusnya diisi %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya organisme lain dari rantai yang sama: yang
                // keliru bukan karena tidak mengenal hewannya, melainkan
                // karena salah menempatkan urutan makan-dimakan.
                array_merge([$benar], array_values(array_filter(
                    array_merge($rantai, [$eko['pengurai']]),
                    fn ($t) => $t !== $benar,
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $i === 0
                ? 'Kotak pertama diisi produsen, yaitu organisme yang membuat makanannya sendiri. '
                    .'Pada ekosistem '.$eko['nama'].' perannya dipegang '.$benar.'.'
                : ucfirst($rantai[$i - 1]).' dimakan oleh '.$benar
                    .(isset($rantai[$i + 1]) ? ', dan '.$benar.' dimakan oleh '.$rantai[$i + 1] : '')
                    .'. Jadi kotak itu diisi '.$benar.'.',
        ];
    }

    protected function akibat(string $awalan, array $rantai, int $i): array
    {
        $hilang = $rantai[$i];
        $sebelum = $rantai[$i - 1];
        $sesudah = $rantai[$i + 1];

        $benar = 'Populasi '.$sebelum.' meningkat dan populasi '.$sesudah.' menurun';

        return [
            'judul' => 'Akibat musnahnya '.$hilang,
            'teks' => $awalan." Apa yang terjadi bila {$hilang} musnah dari ekosistem tersebut?",
            'pernyataan' => $awalan." Bila {$hilang} musnah, yang terjadi adalah %s.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                [
                    $benar,
                    // Pengecoh utamanya kebalikan arahnya — kekeliruan yang
                    // muncul saat siswa tertukar mana pemakan dan mana yang
                    // dimakan.
                    'Populasi '.$sebelum.' menurun dan populasi '.$sesudah.' meningkat',
                    'Populasi '.$sebelum.' dan '.$sesudah.' keduanya meningkat',
                    'Populasi '.$sebelum.' dan '.$sesudah.' keduanya menurun',
                ],
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($hilang).' adalah pemakan '.$sebelum.', sehingga tanpa '
                .$hilang.' populasi '.$sebelum.' tidak lagi ditekan dan meningkat. '
                .'Sebaliknya '.$sesudah.' kehilangan makanannya, sehingga populasinya menurun.',
        ];
    }

    protected function pengurai(string $awalan, array $eko): array
    {
        $benar = 'Pengurai (dekomposer)';
        $nama = $eko['pengurai'];

        return [
            'judul' => ucfirst($nama).' sebagai pengurai',
            'teks' => $awalan." Apa peran {$nama} yang digambar dengan garis putus-putus?",
            'pernyataan' => $awalan." Peran {$nama} pada bagan itu adalah %s.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                [$benar, 'Produsen', 'Konsumen tingkat I', 'Konsumen puncak'],
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($nama).' menguraikan sisa tubuh semua organisme yang mati '
                .'menjadi zat hara yang kembali dipakai produsen. Karena itu '.$nama
                .' berperan sebagai pengurai atau dekomposer, dan digambar menerima aliran '
                .'dari seluruh mata rantai.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bagan rantai makanan: deretan kotak berpanah, dengan kotak pengurai di
     * bawahnya yang menerima aliran dari seluruh mata rantai.
     *
     * @param  array<int, string>  $rantai
     * @param  int  $tandai  urutan yang ditonjolkan (1..n), atau n+1 untuk pengurai
     * @param  bool  $sembunyi  kotak yang ditonjolkan diisi tanda tanya
     */
    public static function svgRantai(array $rantai, string $pengurai, string $ekosistem, int $tandai, bool $sembunyi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $n = count($rantai);
        $jarak = 26;
        $lebar = min(104.0, (Kanvas::LEBAR - 40 - ($n - 1) * $jarak) / $n);
        $tinggi = 54;
        $y = 116;
        $x0 = (Kanvas::LEBAR - $n * $lebar - ($n - 1) * $jarak) / 2 + $lebar / 2;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 40], 'Rantai makanan di ekosistem '.$ekosistem, [
            'ukuran' => 16, 'tebal' => true,
        ]);

        $pusat = [];

        foreach ($rantai as $k => $nama) {
            $pusat[$k] = [$x0 + $k * ($lebar + $jarak), $y];
            $ini = $tandai === $k + 1;

            $kanvas->kotakPx($pusat[$k], $lebar, $tinggi, $ini && $sembunyi ? '?' : $nama,
                $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini && $sembunyi ? 26 : 12,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 2,
                ]);
        }

        for ($k = 1; $k < $n; $k++) {
            $kanvas->panahKotakPx($pusat[$k - 1], $pusat[$k], $lebar, $tinggi, Kanvas::HITAM, 2.2);
        }

        // Pengurai digambar sebagai kotak terpisah yang menerima aliran dari
        // setiap mata rantai, bukan sebagai mata rantai berikutnya: yang
        // mati pada tingkat mana pun berakhir di sana.
        $kotakPengurai = [Kanvas::LEBAR / 2, 240];
        $iniPengurai = $tandai === $n + 1;

        // Ujung panahnya dikipaskan sedikit ke arah kotak asalnya supaya
        // kepala panah yang satu tidak menimpa yang lain di satu titik.
        foreach ($pusat as $p) {
            $kanvas->panahPx(
                [$p[0], $y + $tinggi / 2 + 4],
                [$kotakPengurai[0] + ($p[0] - $kotakPengurai[0]) * 0.2, $kotakPengurai[1] - 28],
                '#8a9199', 1.6, true,
            );
        }

        $kanvas->kotakPx($kotakPengurai, 148, 46, $pengurai, $iniPengurai ? $isi : '#f4f6f8', $garis, [
            'ukuran' => 13, 'tebal' => $iniPengurai, 'tebal_garis' => $iniPengurai ? 3.5 : 2,
        ])->teksPx([Kanvas::LEBAR / 2, 286], 'garis putus-putus = yang mati menuju pengurai', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }
}

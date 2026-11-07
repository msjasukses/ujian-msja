<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template grafik permintaan & penawaran.
 *
 * Kedua kurvanya digambar lewat titik keseimbangan yang diisi guru, bukan
 * sebaliknya, sehingga harga dan jumlah keseimbangan yang menjadi kunci
 * selalu tepat berada di perpotongan kedua garis pada gambarnya. Soal
 * pergeseran kurva memakai gambar yang sama ditambah satu garis putus-putus,
 * sehingga siswa membaca akibatnya dari grafik, bukan menghafal empat
 * kemungkinan.
 */
class GrafikEkonomi extends Template
{
    /**
     * Pergeseran kurva beserta akibatnya pada titik keseimbangan.
     *
     * @var array<string, array{nama: string, kurva: string, kanan: bool, harga: string, jumlah: string, sebab: string}>
     */
    public const GESER = [
        'permintaan_naik' => [
            'nama' => 'permintaan bertambah',
            'kurva' => 'permintaan',
            'kanan' => true,
            'harga' => 'naik',
            'jumlah' => 'bertambah',
            'sebab' => 'pembeli bertambah banyak sehingga pada tiap harga jumlah yang diminta lebih besar',
        ],
        'permintaan_turun' => [
            'nama' => 'permintaan berkurang',
            'kurva' => 'permintaan',
            'kanan' => false,
            'harga' => 'turun',
            'jumlah' => 'berkurang',
            'sebab' => 'pembeli berkurang sehingga pada tiap harga jumlah yang diminta lebih kecil',
        ],
        'penawaran_naik' => [
            'nama' => 'penawaran bertambah',
            'kurva' => 'penawaran',
            'kanan' => true,
            'harga' => 'turun',
            'jumlah' => 'bertambah',
            'sebab' => 'penjual bertambah banyak sehingga pada tiap harga jumlah yang ditawarkan lebih besar',
        ],
        'penawaran_turun' => [
            'nama' => 'penawaran berkurang',
            'kurva' => 'penawaran',
            'kanan' => false,
            'harga' => 'naik',
            'jumlah' => 'berkurang',
            'sebab' => 'penjual berkurang sehingga pada tiap harga jumlah yang ditawarkan lebih kecil',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'keseimbangan' => 'Harga dan jumlah keseimbangan',
        'kurva' => 'Kurva mana yang permintaan atau penawaran',
        'pergeseran' => 'Akibat bergesernya sebuah kurva',
        'hukum' => 'Hukum permintaan atau penawaran',
    ];

    public static function kode(): string
    {
        return 'grafik-ekonomi';
    }

    public static function nama(): string
    {
        return 'Grafik Permintaan & Penawaran';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Kurva permintaan dan penawaran: membaca titik keseimbangan, '
            .'mengenali kedua kurva, akibat pergeserannya, serta hukum permintaan.';
    }

    public static function ikon(): string
    {
        return 'bi-graph-down-arrow';
    }

    public static function kataKunci(): array
    {
        return ['ekonomi', 'permintaan', 'penawaran', 'kurva', 'harga keseimbangan',
            'titik keseimbangan', 'ekuilibrium', 'hukum permintaan', 'hukum penawaran',
            'pasar', 'grafik', 'pergeseran kurva'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'keseimbangan'),
            self::kolomAngka('harga', 'Harga keseimbangan (Rp)', 6000, 100, 10000000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['keseimbangan']],
            ]),
            self::kolomAngka('jumlah', 'Jumlah keseimbangan (unit)', 40, 1, 100000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['keseimbangan']],
            ]),
            [
                'nama' => 'susunan',
                'label' => 'Kurva I pada gambar',
                'jenis' => 'pilihan',
                'pilihan' => [
                    'permintaan' => 'Kurva I = permintaan, kurva II = penawaran',
                    'penawaran' => 'Kurva I = penawaran, kurva II = permintaan',
                ],
                'awal' => 'permintaan',
                'tampil' => ['tanya' => ['kurva']],
            ],
            [
                'nama' => 'geser',
                'label' => 'Yang terjadi di pasar',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($g) => ucfirst($g['nama']), self::GESER),
                'awal' => 'permintaan_naik',
                'tampil' => ['tanya' => ['pergeseran']],
            ],
            [
                'nama' => 'hukum',
                'label' => 'Hukum yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => ['permintaan' => 'Hukum permintaan', 'penawaran' => 'Hukum penawaran'],
                'awal' => 'permintaan',
                'tampil' => ['tanya' => ['hukum']],
            ],
            self::kolomWarna('jingga'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        $harga = (float) ($isi['harga'] ?? 6000);
        $jumlah = (float) ($isi['jumlah'] ?? 40);

        return match ($isi['tanya'] ?? 'keseimbangan') {
            'kurva' => $this->kurva($isi['susunan'] ?? 'permintaan', $garis),
            'pergeseran' => $this->pergeseran($isi['geser'] ?? 'permintaan_naik', $warnaIsi, $garis),
            'hukum' => $this->hukum($isi['hukum'] ?? 'permintaan', $garis),
            default => $this->keseimbangan($harga, $jumlah, $garis),
        };
    }

    protected function keseimbangan(float $harga, float $jumlah, string $garis): array
    {
        $rp = fn (float $n) => 'Rp'.number_format($n, 0, ',', '.').',00';
        $benar = $rp($harga).' dan '.Angka::id($jumlah).' unit';

        return [
            'judul' => 'Keseimbangan '.$rp($harga).' / '.Angka::id($jumlah).' unit',
            'teks' => 'Perhatikan kurva permintaan dan penawaran berikut. Berapakah harga '
                .'keseimbangan dan jumlah keseimbangan pasar tersebut?',
            'pernyataan' => 'Pada kurva permintaan dan penawaran berikut, harga dan jumlah '
                .'keseimbangannya adalah %s.',
            'svg' => self::svgGrafik($harga, $jumlah, 'permintaan', null, true, $garis),
            'keterangan' => 'Kurva permintaan dan penawaran berpotongan pada harga '.$rp($harga)
                .' dan jumlah '.Angka::id($jumlah).' unit.',
            'pilihan' => array_map(
                fn ($p) => ['teks' => $p, 'urut' => $p],
                // Pengecoh utamanya tertukarnya sumbu: harga dibaca sebagai
                // jumlah dan sebaliknya, lalu nilai pada ujung sumbu yang
                // dikira titik keseimbangan.
                array_merge([$benar], array_values(array_diff(array_unique([
                    $rp($jumlah).' dan '.Angka::id($harga).' unit',
                    $rp($harga * 2).' dan '.Angka::id($jumlah * 2).' unit',
                    $rp($harga).' dan '.Angka::id($jumlah * 2).' unit',
                    $rp($harga * 2).' dan '.Angka::id($jumlah).' unit',
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Titik keseimbangan adalah perpotongan kurva permintaan dengan kurva '
                .'penawaran. Dari titik itu ditarik garis putus-putus ke sumbu tegak untuk membaca '
                .'harganya, dan ke sumbu mendatar untuk membaca jumlahnya.<br>Harga keseimbangan = '
                .$rp($harga).' dan jumlah keseimbangan = '.Angka::id($jumlah).' unit.',
        ];
    }

    protected function kurva(string $susunan, string $garis): array
    {
        // Kurva permintaan miring turun ke kanan, kurva penawaran miring naik
        // ke kanan. Yang ditanyakan selalu kurva II supaya labelnya tidak
        // selalu jatuh pada jawaban yang sama.
        $kurvaDua = $susunan === 'permintaan' ? 'penawaran' : 'permintaan';
        $turun = $kurvaDua === 'permintaan';

        $benar = ucfirst($kurvaDua).', karena miring '.($turun ? 'turun' : 'naik').' ke kanan';

        return [
            'judul' => 'Kurva II = '.$kurvaDua,
            'teks' => 'Perhatikan grafik berikut. Kurva II pada grafik itu menunjukkan ...',
            'pernyataan' => 'Pada grafik berikut, kurva II menunjukkan %s.',
            'svg' => self::svgGrafik(0, 0, $susunan, null, false, $garis),
            'keterangan' => 'Dua kurva berlabel I dan II; kurva '.$kurvaDua.' adalah yang ke-II.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Keempat pilihannya menyilangkan nama kurva dengan arah
                // kemiringannya, sehingga yang hafal namanya saja tetap
                // harus membaca gambarnya.
                array_merge([$benar], array_values(array_diff([
                    'Permintaan, karena miring turun ke kanan',
                    'Permintaan, karena miring naik ke kanan',
                    'Penawaran, karena miring turun ke kanan',
                    'Penawaran, karena miring naik ke kanan',
                ], [$benar]))),
            ),
            'kata_kunci' => ucfirst($kurvaDua),
            'pembahasan' => 'Kurva permintaan miring turun ke kanan karena makin tinggi harga '
                .'makin sedikit barang yang diminta. Kurva penawaran miring naik ke kanan karena '
                .'makin tinggi harga makin banyak barang yang ditawarkan.<br>Kurva II pada gambar '
                .'miring '.($turun ? 'turun' : 'naik').' ke kanan, jadi kurva itu adalah kurva '
                .$kurvaDua.'.',
        ];
    }

    protected function pergeseran(string $kunci, string $warnaIsi, string $garis): array
    {
        $geser = self::GESER[$kunci] ?? self::GESER['permintaan_naik'];
        $benar = 'Harga keseimbangan '.$geser['harga'].' dan jumlahnya '.$geser['jumlah'];

        return [
            'judul' => ucfirst($geser['nama']),
            'teks' => 'Perhatikan grafik berikut. Garis putus-putus menunjukkan kurva '
                .$geser['kurva'].' setelah '.$geser['nama'].'. Apa akibatnya terhadap titik '
                .'keseimbangan pasar?',
            'pernyataan' => 'Pada grafik berikut, setelah '.$geser['nama'].' maka %s.',
            'svg' => self::svgGrafik(0, 0, 'permintaan', $kunci, false, $garis),
            'keterangan' => 'Kurva '.$geser['kurva'].' bergeser ke '
                .($geser['kanan'] ? 'kanan' : 'kiri').'; titik keseimbangan berpindah.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Empat kemungkinan yang menyilangkan arah harga dengan arah
                // jumlah; tiga di antaranya memang terjadi pada pergeseran
                // yang lain, jadi tidak ada pilihan yang mustahil.
                array_merge([$benar], array_values(array_diff([
                    'Harga keseimbangan naik dan jumlahnya bertambah',
                    'Harga keseimbangan naik dan jumlahnya berkurang',
                    'Harga keseimbangan turun dan jumlahnya bertambah',
                    'Harga keseimbangan turun dan jumlahnya berkurang',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Ketika '.$geser['nama'].', '.$geser['sebab'].', sehingga kurva '
                .$geser['kurva'].' bergeser ke '.($geser['kanan'] ? 'kanan' : 'kiri').'.<br>'
                .'Perpotongannya dengan kurva yang satu lagi berpindah, dan titik keseimbangan '
                .'yang baru memberi harga yang '.$geser['harga'].' serta jumlah yang '
                .$geser['jumlah'].'.',
        ];
    }

    protected function hukum(string $jenis, string $garis): array
    {
        $permintaan = $jenis === 'permintaan';

        $benar = $permintaan
            ? 'Makin tinggi harga, makin sedikit barang yang diminta'
            : 'Makin tinggi harga, makin banyak barang yang ditawarkan';

        return [
            'judul' => 'Hukum '.$jenis,
            'teks' => 'Perhatikan grafik berikut. Bunyi hukum '.$jenis.' yang sesuai dengan '
                .'kurvanya adalah ...',
            'pernyataan' => 'Pada grafik berikut, hukum '.$jenis.' berbunyi: %s.',
            'svg' => self::svgGrafik(0, 0, 'permintaan', null, false, $garis),
            'keterangan' => 'Kurva permintaan (I) miring turun ke kanan dan kurva penawaran (II) '
                .'miring naik ke kanan.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Makin tinggi harga, makin sedikit barang yang diminta',
                    'Makin tinggi harga, makin banyak barang yang ditawarkan',
                    'Makin tinggi harga, makin banyak barang yang diminta',
                    'Makin tinggi harga, makin sedikit barang yang ditawarkan',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $permintaan
                ? 'Hukum permintaan: bila harga naik, jumlah barang yang diminta turun, dan '
                    .'sebaliknya. Itulah sebabnya kurva permintaan miring turun ke kanan.'
                : 'Hukum penawaran: bila harga naik, jumlah barang yang ditawarkan ikut naik, dan '
                    .'sebaliknya. Itulah sebabnya kurva penawaran miring naik ke kanan.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Batas bidang gambar grafik, dalam piksel. */
    public const KIRI = 78;

    public const KANAN = 430;

    public const ATAS = 68;

    public const BAWAH = 250;

    /**
     * Grafik permintaan dan penawaran.
     *
     * Kedua garis dibuat melalui titik tengah bidang gambar, sehingga
     * perpotongannya pasti tepat di titik keseimbangan yang dilabeli.
     *
     * @param  string|null  $geser  kunci pergeseran, atau null bila tidak ada
     * @param  bool  $berangka  tulis nilai harga dan jumlah pada sumbunya
     */
    public static function svgGrafik(float $harga, float $jumlah, string $susunan, ?string $geser, bool $berangka, string $garis): string
    {
        $kanvas = new Kanvas;

        $tengah = [(self::KIRI + self::KANAN) / 2, (self::ATAS + self::BAWAH) / 2];

        $kanvas
            ->garis([self::KIRI, 44], [self::KIRI, self::BAWAH], Kanvas::HITAM, 2.5)
            ->garis([self::KIRI, self::BAWAH], [456, self::BAWAH], Kanvas::HITAM, 2.5)
            ->teksPx([self::KIRI - 8, 40], 'P', ['ukuran' => 15, 'tebal' => true, 'anchor' => 'end'])
            ->teksPx([460, self::BAWAH + 5], 'Q', ['ukuran' => 15, 'tebal' => true, 'anchor' => 'start']);

        // Permintaan turun ke kanan, penawaran naik ke kanan; keduanya
        // melintasi titik tengah bidang.
        $permintaan = [[self::KIRI, self::ATAS], [self::KANAN, self::BAWAH]];
        $penawaran = [[self::KIRI, self::BAWAH], [self::KANAN, self::ATAS]];

        if ($geser !== null) {
            $ubah = self::GESER[$geser];
            $asal = $ubah['kurva'] === 'permintaan' ? $permintaan : $penawaran;
            $jarak = $ubah['kanan'] ? 74 : -74;

            $kanvas->garisPatah(self::potong([
                [$asal[0][0] + $jarak, $asal[0][1]],
                [$asal[1][0] + $jarak, $asal[1][1]],
            ]), $garis, 3, true);
        }

        foreach ([[$permintaan, 'permintaan'], [$penawaran, 'penawaran']] as [$titik, $jenis]) {
            $nomor = $susunan === $jenis ? 'I' : 'II';

            // Nomor kurva ditaruh di sisi dalam ujungnya, bukan di luar:
            // di luar ia berdempetan dengan nama sumbu Q.
            $kanvas->garisPatah($titik, Kanvas::HITAM, 3)
                ->teksPx([$titik[1][0] - 13, $titik[1][1] + ($jenis === 'permintaan' ? -14 : 20)], $nomor, [
                    'ukuran' => 16, 'tebal' => true,
                ]);
        }

        if (! $berangka) {
            return $kanvas->render();
        }

        $rp = 'Rp'.number_format($harga, 0, ',', '.').',00';

        return $kanvas
            ->garis([self::KIRI, $tengah[1]], $tengah, '#8a9199', 2, true)
            ->garis([$tengah[0], self::BAWAH], $tengah, '#8a9199', 2, true)
            ->lingkaran($tengah, 6, '#ffffff', $garis, 3)
            ->teksPx([self::KIRI - 8, $tengah[1] + 5], $rp, ['ukuran' => 13, 'anchor' => 'end'])
            ->teksPx([$tengah[0], self::BAWAH + 22], Angka::id($jumlah).' unit', ['ukuran' => 13])
            ->teksPx([$tengah[0] + 14, $tengah[1] - 12], 'E', ['ukuran' => 15, 'tebal' => true])
            ->render();
    }

    /**
     * Potong ruas garis agar tetap di dalam bidang gambar. Kurva yang
     * bergeser bisa melewati sumbu, dan garis yang menjulur keluar bidang
     * terbaca sebagai kesalahan gambar.
     *
     * @param  array<int, array<int, float>>  $titik
     * @return array<int, array<int, float>>
     */
    protected static function potong(array $titik): array
    {
        [$a, $b] = $titik;
        $lebar = $b[0] - $a[0];

        foreach ([0, 1] as $i) {
            $x = min(self::KANAN, max(self::KIRI, $titik[$i][0]));

            // Titik yang digeser keluar bidang ditarik kembali ke tepinya
            // sambil mengikuti kemiringan garisnya, bukan sekadar dipotong.
            $titik[$i] = [$x, $a[1] + ($x - $a[0]) / $lebar * ($b[1] - $a[1])];
        }

        return $titik;
    }
}

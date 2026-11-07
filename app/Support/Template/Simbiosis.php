<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template simbiosis: hubungan antara dua makhluk hidup.
 *
 * Gambarnya hanya menyebut kedua makhluk hidup dan apa yang terjadi di
 * antara keduanya secara apa adanya — "lebah mengisap nektar bunga" —
 * tanpa tanda untung atau rugi. Menyimpulkan siapa yang diuntungkan dan
 * siapa yang dirugikan dari peristiwanya itulah yang diujikan; kalau tanda
 * plus dan minus dibubuhkan pada gambarnya, jenis simbiosisnya tinggal
 * dibaca.
 *
 * Makhluk hidup pertama pada tiap pasangan selalu pihak yang diuntungkan,
 * sehingga soal tentang keuntungan selalu punya jawaban.
 */
class Simbiosis extends Template
{
    /** @var array<string, array{nama: string, arti: string}> */
    public const JENIS = [
        'mutualisme' => [
            'nama' => 'Mutualisme',
            'arti' => 'kedua pihak sama-sama diuntungkan',
        ],
        'komensalisme' => [
            'nama' => 'Komensalisme',
            'arti' => 'satu pihak diuntungkan dan pihak lain tidak dirugikan',
        ],
        'parasitisme' => [
            'nama' => 'Parasitisme',
            'arti' => 'satu pihak diuntungkan dan pihak lain dirugikan',
        ],
    ];

    /** @var array<string, string> */
    public const AKIBAT = [
        'untung' => 'diuntungkan',
        'netral' => 'tidak diuntungkan dan tidak dirugikan',
        'rugi' => 'dirugikan',
    ];

    /**
     * Pasangan makhluk hidup. Anggota "satu" selalu yang diuntungkan.
     *
     * @var array<string, array{satu: string, dua: string, jenis: string, peristiwa: string, akibat_dua: string, untung: string, nasib_dua: string}>
     */
    public const PASANGAN = [
        'lebah_bunga' => [
            'satu' => 'Lebah', 'dua' => 'Bunga', 'jenis' => 'mutualisme',
            'peristiwa' => 'Lebah hinggap di bunga untuk mengisap nektarnya. Serbuk sari '
                .'menempel di tubuh lebah dan terbawa ke bunga lain.',
            'akibat_dua' => 'untung',
            'untung' => 'memperoleh nektar bunga sebagai makanannya',
            'nasib_dua' => 'penyerbukannya terbantu oleh serbuk sari yang terbawa lebah',
        ],
        'jalak_kerbau' => [
            'satu' => 'Burung jalak', 'dua' => 'Kerbau', 'jenis' => 'mutualisme',
            'peristiwa' => 'Burung jalak hinggap di punggung kerbau dan memakan kutu yang '
                .'menempel di kulitnya.',
            'akibat_dua' => 'untung',
            'untung' => 'memperoleh kutu di punggung kerbau sebagai makanannya',
            'nasib_dua' => 'kulitnya bersih dari kutu yang mengganggu',
        ],
        'rhizobium_kacang' => [
            'satu' => 'Bakteri Rhizobium', 'dua' => 'Akar tanaman kacang', 'jenis' => 'mutualisme',
            'peristiwa' => 'Bakteri Rhizobium hidup di dalam bintil akar tanaman kacang dan '
                .'mengikat nitrogen dari udara.',
            'akibat_dua' => 'untung',
            'untung' => 'memperoleh tempat hidup dan zat makanan dari akar tanaman',
            'nasib_dua' => 'memperoleh nitrogen yang diikat bakteri untuk tumbuh',
        ],
        'badut_anemon' => [
            'satu' => 'Ikan badut', 'dua' => 'Anemon laut', 'jenis' => 'mutualisme',
            'peristiwa' => 'Ikan badut berlindung di antara tentakel anemon laut yang beracun, '
                .'dan sisa makanannya dimakan anemon.',
            'akibat_dua' => 'untung',
            'untung' => 'memperoleh perlindungan dari pemangsa di antara tentakel anemon',
            'nasib_dua' => 'memperoleh sisa makanan ikan badut dan terjaga kebersihannya',
        ],
        'anggrek_mangga' => [
            'satu' => 'Anggrek', 'dua' => 'Pohon mangga', 'jenis' => 'komensalisme',
            'peristiwa' => 'Anggrek menempel pada batang pohon mangga. Anggrek membuat '
                .'makanannya sendiri dan tidak mengisap zat makanan dari pohon.',
            'akibat_dua' => 'netral',
            'untung' => 'memperoleh tempat menempel yang lebih dekat dengan cahaya matahari',
            'nasib_dua' => 'zat makanannya tidak diambil sehingga tidak terpengaruh',
        ],
        'remora_hiu' => [
            'satu' => 'Ikan remora', 'dua' => 'Ikan hiu', 'jenis' => 'komensalisme',
            'peristiwa' => 'Ikan remora menempel pada tubuh ikan hiu dan memakan sisa makanan '
                .'yang berjatuhan ketika hiu makan.',
            'akibat_dua' => 'netral',
            'untung' => 'memperoleh sisa makanan dan perlindungan dari pemangsa',
            'nasib_dua' => 'makanannya tidak berkurang sehingga tidak terpengaruh',
        ],
        'paku_pohon' => [
            'satu' => 'Paku tanduk rusa', 'dua' => 'Pohon besar', 'jenis' => 'komensalisme',
            'peristiwa' => 'Paku tanduk rusa tumbuh menempel pada batang pohon besar dan '
                .'berfotosintesis sendiri.',
            'akibat_dua' => 'netral',
            'untung' => 'memperoleh tempat tumbuh yang cukup cahaya',
            'nasib_dua' => 'tidak kehilangan zat makanan maupun air',
        ],
        'benalu_inang' => [
            'satu' => 'Benalu', 'dua' => 'Pohon inang', 'jenis' => 'parasitisme',
            'peristiwa' => 'Benalu menancapkan akarnya ke batang pohon inang dan mengisap air '
                .'serta zat makanan dari pembuluh angkutnya.',
            'akibat_dua' => 'rugi',
            'untung' => 'memperoleh air dan zat makanan dari pohon inang',
            'nasib_dua' => 'kehilangan air dan zat makanan sehingga pertumbuhannya terhambat',
        ],
        'tali_putri' => [
            'satu' => 'Tali putri', 'dua' => 'Tanaman inang', 'jenis' => 'parasitisme',
            'peristiwa' => 'Tali putri yang tidak berdaun hijau membelit tanaman inang dan '
                .'mengisap zat makanan dari batangnya.',
            'akibat_dua' => 'rugi',
            'untung' => 'memperoleh zat makanan karena tidak dapat berfotosintesis sendiri',
            'nasib_dua' => 'zat makanannya diambil sehingga lama-kelamaan mengering',
        ],
        'cacing_pita' => [
            'satu' => 'Cacing pita', 'dua' => 'Manusia', 'jenis' => 'parasitisme',
            'peristiwa' => 'Cacing pita hidup di usus manusia dan menyerap sari makanan yang '
                .'sudah dicerna.',
            'akibat_dua' => 'rugi',
            'untung' => 'memperoleh sari makanan di usus tanpa perlu mencernanya sendiri',
            'nasib_dua' => 'sari makanannya berkurang sehingga mudah kekurangan gizi',
        ],
        'kutu_manusia' => [
            'satu' => 'Kutu rambut', 'dua' => 'Manusia', 'jenis' => 'parasitisme',
            'peristiwa' => 'Kutu rambut hidup di kulit kepala manusia dan mengisap darahnya.',
            'akibat_dua' => 'rugi',
            'untung' => 'memperoleh darah sebagai makanannya',
            'nasib_dua' => 'kehilangan darah dan kulit kepalanya terasa gatal',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'jenis' => 'Jenis simbiosisnya',
        'akibat' => 'Untung dan ruginya kedua pihak',
        'peran' => 'Keuntungan pihak pertama',
        'contoh_lain' => 'Contoh lain yang sejenis',
    ];

    public static function kode(): string
    {
        return 'simbiosis';
    }

    public static function nama(): string
    {
        return 'Simbiosis';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Hubungan antara dua makhluk hidup: menentukan jenis simbiosisnya, '
            .'pihak yang untung dan rugi, serta contoh lain yang sejenis.';
    }

    public static function ikon(): string
    {
        return 'bi-link-45deg';
    }

    public static function kataKunci(): array
    {
        return ['simbiosis', 'mutualisme', 'komensalisme', 'parasitisme', 'ekosistem',
            'interaksi makhluk hidup', 'parasit', 'inang', 'benalu', 'lebah', 'jalak',
            'anggrek', 'remora', 'biologi', 'ipa'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'jenis'),
            [
                'nama' => 'pasangan',
                'label' => 'Pasangan makhluk hidup',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($p) => $p['satu'].' dan '.mb_strtolower($p['dua']),
                    self::PASANGAN,
                ),
                'awal' => 'lebah_bunga',
            ],
            self::kolomWarna('hijau'),
        ];
    }

    /** Pasangan lain yang jenis simbiosisnya sama. */
    public static function sejenis(string $kunci): array
    {
        return array_keys(array_filter(
            self::PASANGAN,
            fn ($p, $k) => $k !== $kunci && $p['jenis'] === self::PASANGAN[$kunci]['jenis'],
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** Menuliskan sepasang makhluk hidup sebagai pilihan jawaban. */
    public static function tulisPasangan(string $kunci): string
    {
        $p = self::PASANGAN[$kunci];

        return $p['satu'].' dan '.mb_strtolower($p['dua']);
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $kunci = isset(self::PASANGAN[$isi['pasangan'] ?? '']) ? $isi['pasangan'] : 'lebah_bunga';
        $pasangan = self::PASANGAN[$kunci];

        $bahan = [
            'svg' => self::svgHubungan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Hubungan antara '.mb_strtolower($pasangan['satu']).' dan '
                .mb_strtolower($pasangan['dua']).'.',
        ];

        $awalan = 'Perhatikan bagan hubungan dua makhluk hidup berikut.';

        return $bahan + match ($isi['tanya'] ?? 'jenis') {
            'akibat' => $this->akibat($awalan, $pasangan),
            'peran' => $this->peran($awalan, $kunci, $pasangan),
            'contoh_lain' => $this->contohLain($awalan, $kunci, $pasangan),
            default => $this->jenisSimbiosis($awalan, $pasangan),
        };
    }

    protected function jenisSimbiosis(string $awalan, array $pasangan): array
    {
        $benar = self::JENIS[$pasangan['jenis']]['nama'];

        return [
            'judul' => $pasangan['satu'].' & '.mb_strtolower($pasangan['dua']).': '.$benar,
            'teks' => $awalan.' Hubungan antara '.mb_strtolower($pasangan['satu']).' dan '
                .mb_strtolower($pasangan['dua']).' disebut simbiosis ...',
            'pernyataan' => $awalan.' Hubungan antara '.mb_strtolower($pasangan['satu'])
                .' dan '.mb_strtolower($pasangan['dua']).' disebut simbiosis %s.',
            // Jenis simbiosis hanya tiga, jadi ditambah satu istilah yang
            // memang ada dalam ekologi tetapi bukan simbiosis.
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_merge(
                    array_column(self::JENIS, 'nama'),
                    ['Kompetisi'],
                ), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst(mb_strtolower($pasangan['satu'])).' '
                .$pasangan['untung'].', sedangkan '.mb_strtolower($pasangan['dua']).' '
                .$pasangan['nasib_dua'].'.<br>Hubungan yang '
                .self::JENIS[$pasangan['jenis']]['arti'].' disebut simbiosis <strong>'
                .mb_strtolower($benar).'</strong>.',
        ];
    }

    protected function akibat(string $awalan, array $pasangan): array
    {
        $satu = mb_strtolower($pasangan['satu']);
        $dua = mb_strtolower($pasangan['dua']);

        $susun = fn (string $a, string $b) => ucfirst($satu).' '.self::AKIBAT[$a]
            .', '.$dua.' '.self::AKIBAT[$b].'.';

        $benar = $susun('untung', $pasangan['akibat_dua']);

        // Keempat pilihan menyebut nasib kedua pihak sekaligus. Kalau yang
        // ditanyakan hanya satu pihak, jawabannya cuma tiga kemungkinan dan
        // pilihan keempatnya pasti mengada-ada.
        $semua = [
            $susun('untung', 'untung'),
            $susun('untung', 'netral'),
            $susun('untung', 'rugi'),
            $susun('rugi', 'untung'),
        ];

        return [
            'judul' => $pasangan['satu'].' & '.$dua.': untung–'
                .self::PASANGAN_SINGKAT[$pasangan['akibat_dua']],
            'teks' => $awalan.' Pernyataan yang benar tentang hubungan '.$satu.' dan '
                .$dua.' adalah ...',
            'pernyataan' => $awalan.' Tentang hubungan '.$satu.' dan '.$dua.': %s',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t === $benar ? 0 : 1 + array_search($t, $semua, true)],
                array_merge([$benar], array_values(array_diff($semua, [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($satu).' '.$pasangan['untung'].', jadi '.$satu
                .' diuntungkan.<br>'.ucfirst($dua).' '.$pasangan['nasib_dua'].', jadi '
                .$dua.' '.self::AKIBAT[$pasangan['akibat_dua']].'.<br>Hubungan itu termasuk '
                .'simbiosis '.mb_strtolower(self::JENIS[$pasangan['jenis']]['nama']).'.',
        ];
    }

    protected function peran(string $awalan, string $kunci, array $pasangan): array
    {
        $benar = ucfirst($pasangan['untung']);

        // Pengecohnya keuntungan nyata dari pasangan lain: semuanya benar
        // sebagai pernyataan, hanya salah pasangannya.
        $lain = array_values(array_diff(array_keys(self::PASANGAN), [$kunci]));
        $pengecoh = [];

        foreach ($lain as $k) {
            $teks = ucfirst(self::PASANGAN[$k]['untung']);

            if ($teks !== $benar && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'judul' => 'Keuntungan '.mb_strtolower($pasangan['satu']),
            'teks' => $awalan.' Keuntungan yang diperoleh '.mb_strtolower($pasangan['satu'])
                .' dari hubungan itu adalah ...',
            'pernyataan' => $awalan.' Keuntungan yang diperoleh '
                .mb_strtolower($pasangan['satu']).' adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $pasangan['untung'],
            'pembahasan' => 'Pada peristiwa itu, '.mb_strtolower($pasangan['satu']).' '
                .$pasangan['untung'].'.<br>Itulah keuntungan yang membuat hubungan ini '
                .'termasuk simbiosis '
                .mb_strtolower(self::JENIS[$pasangan['jenis']]['nama']).'.',
        ];
    }

    protected function contohLain(string $awalan, string $kunci, array $pasangan): array
    {
        $jenis = mb_strtolower(self::JENIS[$pasangan['jenis']]['nama']);
        $sejenis = self::sejenis($kunci);
        $benar = self::tulisPasangan($sejenis[0]);

        // Pengecohnya pasangan nyata dari jenis simbiosis yang lain.
        $pengecoh = array_map(
            fn ($k) => self::tulisPasangan($k),
            array_values(array_filter(
                array_keys(self::PASANGAN),
                fn ($k) => self::PASANGAN[$k]['jenis'] !== $pasangan['jenis'],
            )),
        );

        return [
            'judul' => 'Contoh '.$jenis.' lain',
            'teks' => $awalan.' Hubungan itu termasuk simbiosis '.$jenis
                .'. Contoh lain simbiosis '.$jenis.' adalah ...',
            'pernyataan' => $awalan.' Contoh lain simbiosis '.$jenis.' adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Simbiosis '.$jenis.' adalah hubungan yang '
                .self::JENIS[$pasangan['jenis']]['arti'].'.<br>Pada pasangan '
                .mb_strtolower($benar).', '
                .mb_strtolower(self::PASANGAN[$sejenis[0]]['satu']).' '
                .self::PASANGAN[$sejenis[0]]['untung'].' dan '
                .mb_strtolower(self::PASANGAN[$sejenis[0]]['dua']).' '
                .self::PASANGAN[$sejenis[0]]['nasib_dua'].', sehingga termasuk simbiosis '
                .$jenis.' juga.',
        ];
    }

    /** Kata singkat untuk judul butir. */
    public const PASANGAN_SINGKAT = [
        'untung' => 'untung',
        'netral' => 'netral',
        'rugi' => 'rugi',
    ];

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Dua makhluk hidup dan peristiwa di antara keduanya.
     *
     * Tidak ada tanda untung atau rugi pada panahnya: menyimpulkan nasib
     * kedua pihak dari peristiwanya itulah yang diujikan.
     */
    public static function svgHubungan(string $kunci, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $p = self::PASANGAN[$kunci];

        $lebarKotak = 160.0;
        $tinggiKotak = 64.0;
        $y = 104.0;

        $kanvas
            ->teksPx([240, 32], 'Hubungan Dua Makhluk Hidup', ['ukuran' => 15, 'tebal' => true])
            ->kotakPx([94, $y], $lebarKotak, $tinggiKotak, $p['satu'], $isi, $garis, ['ukuran' => 14])
            ->kotakPx([386, $y], $lebarKotak, $tinggiKotak, $p['dua'], $isi, $garis, ['ukuran' => 14])
            // Panah dua arah: keduanya berhubungan, tetapi siapa yang
            // diuntungkan tidak dinyatakan.
            ->panahPx([240 - 26, $y], [94 + $lebarKotak / 2 + 4, $y], $garis, 2.5)
            ->panahPx([240 + 26, $y], [386 - $lebarKotak / 2 - 4, $y], $garis, 2.5)
            ->teksPx([240, $y + 6], '?', ['ukuran' => 22, 'tebal' => true, 'warna' => $garis])
            ->teksBaris([240, 196], Kanvas::baris($p['peristiwa'], 400, 13), ['ukuran' => 13])
            ->teksPx([240, 272], 'tanda untung atau rugi sengaja tidak digambarkan', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ]);

        return $kanvas->render();
    }
}

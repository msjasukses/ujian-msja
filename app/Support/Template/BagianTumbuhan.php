<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template bagian tumbuhan: organ tumbuhan, bagian bunga, dan fotosintesis.
 *
 * Ketiga gambarnya dibangun dari bentuk dasar — bidang membulat berujung
 * runcing untuk kelopak, mahkota, dan helai daun — sehingga seluruhnya
 * dihasilkan sendiri oleh server tanpa gambar jadi. Pada bagan fotosintesis,
 * zat yang ditanyakan sengaja diganti huruf X: gambarnya memberi tahu arah
 * masuk dan keluarnya, tetapi tidak memberi tahu jawabannya.
 */
class BagianTumbuhan extends Template
{
    /**
     * Bagian bunga beserta fungsinya, ujung penunjuk, dan letak huruf X-nya.
     *
     * @var array<string, array{nama: string, fungsi: string, titik: array<int, float>, label: array<int, float>}>
     */
    public const BUNGA = [
        'mahkota' => [
            'nama' => 'Mahkota bunga',
            'fungsi' => 'menarik perhatian serangga penyerbuk',
            'titik' => [222, 88],
            'label' => [240, 26],
        ],
        'kelopak' => [
            'nama' => 'Kelopak bunga',
            'fungsi' => 'melindungi bunga ketika masih kuncup',
            'titik' => [294, 62],
            'label' => [416, 32],
        ],
        'benang_sari' => [
            'nama' => 'Benang sari',
            'fungsi' => 'menjadi alat kelamin jantan penghasil serbuk sari',
            'titik' => [288, 164],
            'label' => [436, 206],
        ],
        'putik' => [
            'nama' => 'Putik',
            'fungsi' => 'menjadi alat kelamin betina tempat terjadinya pembuahan',
            'titik' => [240, 118],
            'label' => [56, 70],
        ],
        'tangkai' => [
            'nama' => 'Tangkai bunga',
            'fungsi' => 'menopang bunga dan menghubungkannya dengan batang',
            'titik' => [240, 266],
            'label' => [136, 270],
        ],
    ];

    /**
     * Organ tumbuhan beserta fungsinya dan letak tandanya pada gambar.
     *
     * @var array<string, array{nama: string, fungsi: string, titik: ?array<int, float>, label: array<int, float>}>
     */
    public const ORGAN = [
        'akar' => [
            'nama' => 'Akar',
            'fungsi' => 'menyerap air dan zat hara dari dalam tanah',
            'titik' => [205, 278],
            'label' => [92, 282],
        ],
        'batang' => [
            'nama' => 'Batang',
            'fungsi' => 'menyalurkan air dari akar ke daun dan menopang tumbuhan',
            'titik' => null,
            'label' => [240, 226],
        ],
        'daun' => [
            'nama' => 'Daun',
            'fungsi' => 'menjadi tempat berlangsungnya fotosintesis',
            'titik' => [303, 140],
            'label' => [424, 120],
        ],
        'bunga' => [
            'nama' => 'Bunga',
            'fungsi' => 'menjadi alat perkembangbiakan generatif',
            'titik' => [252, 50],
            'label' => [124, 38],
        ],
        'buah' => [
            'nama' => 'Buah',
            'fungsi' => 'melindungi biji dan menyimpan cadangan makanan',
            'titik' => [136, 104],
            'label' => [54, 62],
        ],
        'biji' => [
            'nama' => 'Biji',
            'fungsi' => 'menjadi calon tumbuhan baru',
            'titik' => [150, 92],
            'label' => [44, 142],
        ],
    ];

    /**
     * Pasangan zat yang dipakai sebagai pilihan jawaban fotosintesis. Tiap
     * pasangan benar-benar terkait fotosintesis, hanya salah letaknya —
     * tertukar antara yang diperlukan dan yang dihasilkan.
     *
     * @var array<int, string>
     */
    public const ZAT = [
        'Karbon dioksida dan air',
        'Oksigen dan glukosa',
        'Oksigen dan air',
        'Karbon dioksida dan glukosa',
        'Air dan glukosa',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'organ' => 'Nama organ tumbuhan yang ditandai',
        'fungsi_organ' => 'Fungsi organ tumbuhan yang ditandai',
        'bagian_bunga' => 'Nama bagian bunga yang ditandai',
        'fungsi_bunga' => 'Fungsi bagian bunga yang ditandai',
        'foto_bahan' => 'Zat yang diperlukan untuk fotosintesis',
        'foto_hasil' => 'Zat yang dihasilkan fotosintesis',
        'foto_tempat' => 'Tempat berlangsungnya fotosintesis',
    ];

    public static function kode(): string
    {
        return 'bagian-tumbuhan';
    }

    public static function nama(): string
    {
        return 'Bagian Tumbuhan & Fotosintesis';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Organ tumbuhan dan fungsinya, bagian-bagian bunga, serta bagan '
            .'fotosintesis dengan zat yang diperlukan dan dihasilkannya.';
    }

    public static function ikon(): string
    {
        return 'bi-flower1';
    }

    public static function kataKunci(): array
    {
        return ['tumbuhan', 'akar', 'batang', 'daun', 'bunga', 'buah', 'biji',
            'mahkota', 'kelopak', 'benang sari', 'putik', 'penyerbukan',
            'fotosintesis', 'klorofil', 'kloroplas', 'stomata', 'glukosa'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'organ'),
            [
                'nama' => 'organ',
                'label' => 'Organ yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($o) => $o['nama'], self::ORGAN),
                'awal' => 'daun',
                'tampil' => ['tanya' => ['organ', 'fungsi_organ']],
            ],
            [
                'nama' => 'bagian',
                'label' => 'Bagian bunga yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($b) => $b['nama'], self::BUNGA),
                'awal' => 'putik',
                'tampil' => ['tanya' => ['bagian_bunga', 'fungsi_bunga']],
            ],
            self::kolomWarna('merah'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');
        $tanya = $isi['tanya'] ?? 'organ';

        return match ($tanya) {
            'bagian_bunga', 'fungsi_bunga' => $this->bagianBunga(
                $isi['bagian'] ?? 'putik', $tanya === 'fungsi_bunga', $warnaIsi, $garis,
            ),
            'foto_bahan' => $this->fotosintesisZat('bahan', $garis),
            'foto_hasil' => $this->fotosintesisZat('hasil', $garis),
            'foto_tempat' => $this->fotosintesisTempat($garis),
            default => $this->organTumbuhan(
                $isi['organ'] ?? 'daun', $tanya === 'fungsi_organ', $warnaIsi, $garis,
            ),
        };
    }

    protected function organTumbuhan(string $kunci, bool $tanyaFungsi, string $warnaIsi, string $garis): array
    {
        $organ = self::ORGAN[$kunci] ?? self::ORGAN['daun'];

        return [
            'judul' => $organ['nama'].($tanyaFungsi ? ': fungsinya' : ''),
            'teks' => 'Perhatikan gambar tumbuhan berikut. Bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk ...' : 'adalah ...'),
            'pernyataan' => 'Pada gambar tumbuhan berikut, bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk %s.' : 'adalah %s.'),
            'svg' => self::svgTumbuhan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Tumbuhan berbunga dan berbuah dengan '
                .mb_strtolower($organ['nama']).' ditandai X.',
            'pilihan' => self::pilihanBagian($organ, $tanyaFungsi, self::ORGAN),
            'kata_kunci' => $tanyaFungsi ? $organ['fungsi'] : $organ['nama'],
            'pembahasan' => 'Bagian yang ditandai X adalah '.mb_strtolower($organ['nama'])
                .', yang berfungsi '.$organ['fungsi'].'.',
        ];
    }

    protected function bagianBunga(string $kunci, bool $tanyaFungsi, string $warnaIsi, string $garis): array
    {
        $bagian = self::BUNGA[$kunci] ?? self::BUNGA['putik'];

        return [
            'judul' => $bagian['nama'].($tanyaFungsi ? ': fungsinya' : ''),
            'teks' => 'Perhatikan gambar bunga sempurna berikut. Bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk ...' : 'adalah ...'),
            'pernyataan' => 'Pada gambar bunga sempurna berikut, bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk %s.' : 'adalah %s.'),
            'svg' => self::svgBunga($kunci, $warnaIsi, $garis),
            'keterangan' => 'Bunga sempurna dengan '.mb_strtolower($bagian['nama']).' ditandai X.',
            'pilihan' => self::pilihanBagian($bagian, $tanyaFungsi, self::BUNGA),
            'kata_kunci' => $tanyaFungsi ? $bagian['fungsi'] : $bagian['nama'],
            'pembahasan' => 'Bagian yang ditandai X adalah '.mb_strtolower($bagian['nama'])
                .', yang berfungsi '.$bagian['fungsi'].'.',
        ];
    }

    protected function fotosintesisZat(string $sisi, string $garis): array
    {
        $bahan = $sisi === 'bahan';
        $benar = $bahan ? self::ZAT[0] : self::ZAT[1];
        $kata = $bahan ? 'diperlukan' : 'dihasilkan';

        return [
            'judul' => 'Zat yang '.$kata.': '.$benar,
            'teks' => 'Perhatikan bagan fotosintesis berikut. Zat yang ditandai huruf X, yaitu '
                .'zat yang '.$kata.' pada proses itu, adalah ...',
            'pernyataan' => 'Pada bagan fotosintesis berikut, zat yang ditandai huruf X adalah %s.',
            'svg' => self::svgFotosintesis($sisi, $garis),
            'keterangan' => 'Bagan fotosintesis pada daun; zat yang '.$kata.' ditandai X.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(self::ZAT, [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Fotosintesis berlangsung menurut: karbon dioksida + air, dengan '
                .'bantuan cahaya matahari dan klorofil, menghasilkan glukosa + oksigen. Jadi zat '
                .'yang '.$kata.' adalah '.mb_strtolower($benar).'.',
        ];
    }

    protected function fotosintesisTempat(string $garis): array
    {
        $benar = 'Kloroplas';

        return [
            'judul' => 'Fotosintesis berlangsung di kloroplas',
            'teks' => 'Perhatikan bagan fotosintesis berikut. Bagian sel daun tempat proses itu '
                .'berlangsung adalah ...',
            'pernyataan' => 'Pada bagan fotosintesis berikut, proses itu berlangsung di dalam %s.',
            'svg' => self::svgFotosintesis('lengkap', $garis),
            'keterangan' => 'Bagan fotosintesis pada daun beserta zat yang masuk dan keluar.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya bagian daun dan organel lain yang memang
                // terlibat pada pernapasan atau pertukaran gas, sehingga
                // yang keliru tertukar perannya, bukan asal memilih.
                [$benar, 'Stomata', 'Mitokondria', 'Epidermis'],
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Fotosintesis berlangsung di dalam kloroplas, yaitu organel yang '
                .'mengandung klorofil. Stomata hanya menjadi jalan keluar masuk gas, sedangkan '
                .'mitokondria adalah tempat respirasi sel.',
        ];
    }

    /**
     * Pilihan jawaban untuk soal menandai bagian: nama bagian lain pada
     * gambar yang sama, atau fungsi bagian lain pada gambar itu. Keduanya
     * tampak di hadapan siswa, sehingga tidak ada pilihan yang bisa dicoret
     * hanya karena terdengar asing.
     *
     * @param  array<string, array<string, mixed>>  $semua
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanBagian(array $bagian, bool $tanyaFungsi, array $semua): array
    {
        $benar = $tanyaFungsi ? $bagian['fungsi'] : $bagian['nama'];
        $daftar = array_column($semua, $tanyaFungsi ? 'fungsi' : 'nama');

        return array_map(
            fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
            array_merge([$benar], array_values(array_diff($daftar, [$benar]))),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bidang membulat berujung runcing. Kelopak, mahkota, dan helai daun
     * memakai bentuk yang sama — yang berbeda hanya pangkal, arah, panjang,
     * dan lebarnya.
     */
    protected static function helai(array $pangkal, float $derajat, float $panjang, float $lebar): string
    {
        $a = deg2rad($derajat);
        [$dx, $dy] = [cos($a), sin($a)];

        $titik = fn (float $sisi) => round($pangkal[0] + $dx * $panjang * 0.45 - $dy * $lebar * $sisi, 1)
            .' '.round($pangkal[1] + $dy * $panjang * 0.45 + $dx * $lebar * $sisi, 1);

        $awal = round($pangkal[0], 1).' '.round($pangkal[1], 1);

        return 'M '.$awal
            .' Q '.$titik(1).' '.round($pangkal[0] + $dx * $panjang, 1).' '
            .round($pangkal[1] + $dy * $panjang, 1)
            .' Q '.$titik(-1).' '.$awal.' Z';
    }

    protected static function bidang(Kanvas $kanvas, string $path, string $isi, string $garis, float $tebal = 2): Kanvas
    {
        return $kanvas->tambah('<path d="'.$path.'" fill="'.$isi.'" stroke="'.$garis
            .'" stroke-width="'.$tebal.'" stroke-linejoin="round"/>');
    }

    /** Huruf X beralas putih, dengan penunjuk ke bagian yang dimaksud. */
    protected static function tandai(Kanvas $kanvas, ?array $titik, array $label, string $garis): Kanvas
    {
        if ($titik !== null) {
            $kanvas->garis($titik, $label, Kanvas::HITAM, 2)
                ->lingkaran($titik, 4.5, '#ffffff', Kanvas::HITAM, 2);
        }

        return $kanvas
            ->lingkaran($label, 13, '#ffffff', $garis, 2.5)
            ->teksPx([$label[0], $label[1] + 7], 'X', ['ukuran' => 20, 'tebal' => true]);
    }

    /** Bunga sempurna dilihat dari depan; satu bagiannya ditandai huruf X. */
    public static function svgBunga(string $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [240, 136];

        // Tangkainya digambar lebih dahulu supaya pangkalnya tertutup
        // kelopak, persis seperti bunga yang dilihat dari depan.
        $kanvas->tambah('<line x1="240" y1="136" x2="240" y2="288" stroke="#4e7d2a" '
            .'stroke-width="9" stroke-linecap="round"/>');

        foreach ([-54, 18, 90, 162, 234] as $sudut) {
            self::bidang($kanvas, self::helai($pusat, $sudut, 106, 30), '#6fa544', '#2f5a18');
        }

        foreach ([-90, -18, 54, 126, 198] as $sudut) {
            self::bidang($kanvas, self::helai($pusat, $sudut, 86, 34), $isi, $garis);
        }

        // Benang sari: enam tangkai pendek berkepala sari di ujungnya.
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad($i * 60 + 30);
            $kanvas->garis($pusat, [$pusat[0] + cos($a) * 48, $pusat[1] + sin($a) * 48], '#8a6d1f', 2)
                ->elips([$pusat[0] + cos($a) * 56, $pusat[1] + sin($a) * 56], 12, 8, '#f4c430', '#8a6d1f', 1.5);
        }

        $kanvas
            ->lingkaran($pusat, 15, '#f7e7a1', '#8a6d1f', 2)
            ->tambah('<line x1="240" y1="136" x2="240" y2="118" stroke="#8a6d1f" stroke-width="3"/>')
            ->lingkaran([240, 114], 10, '#e8a0b4', '#9c4663', 2);

        $bagian = self::BUNGA[$ditandai] ?? self::BUNGA['putik'];
        self::tandai($kanvas, $bagian['titik'], $bagian['label'], $garis);

        return $kanvas->render();
    }

    /** Tumbuhan berbunga dan berbuah; satu organnya ditandai huruf X. */
    public static function svgTumbuhan(string $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas->tambah('<rect x="0" y="256" width="480" height="44" fill="#e4d7bf"/>')
            ->tambah('<line x1="0" y1="256" x2="480" y2="256" stroke="#9a8560" stroke-width="2"/>');

        foreach ([[168, 298], [240, 299], [312, 298]] as $ujung) {
            $kanvas->garis([240, 256], $ujung, '#a9793f', 4);
        }

        foreach ([[196, 282], [284, 282]] as $cabang) {
            $kanvas->garis($cabang, [$cabang[0] + ($cabang[0] < 240 ? -26 : 26), 294], '#a9793f', 2.5);
        }

        $kanvas->tambah('<rect x="232" y="88" width="16" height="168" fill="#5f8f33" '
            .'stroke="#3f6b22" stroke-width="2"/>');

        foreach ([[232, 140, 180], [248, 140, 0], [232, 194, 168], [248, 194, 12]] as [$x, $y, $sudut]) {
            self::bidang($kanvas, self::helai([$x, $y], $sudut, 88, 27), '#8fbf63', '#3f6b22');
        }

        // Buah bertangkai dengan bijinya digambar bergaris putus-putus,
        // cara lazim menunjukkan bagian yang berada di dalam.
        $kanvas->garis([236, 116], [160, 100], '#3f6b22', 3)
            ->lingkaran([150, 92], 26, '#e8894a', '#a8541f', 2.5)
            ->elips([150, 92], 8, 12, '#fdf3dc', '#a8541f', 2, true);

        foreach ([-90, -18, 54, 126, 198] as $sudut) {
            self::bidang($kanvas, self::helai([240, 66], $sudut, 26, 11), $isi, $garis, 1.8);
        }

        $kanvas->lingkaran([240, 66], 8, '#f4c430', '#8a6d1f', 1.8);

        $organ = self::ORGAN[$ditandai] ?? self::ORGAN['daun'];
        self::tandai($kanvas, $organ['titik'], $organ['label'], $garis);

        return $kanvas->render();
    }

    /**
     * Bagan fotosintesis: sehelai daun dengan zat yang masuk dan keluar.
     * Zat yang ditanyakan diganti huruf X — bagannya menunjukkan arahnya,
     * bukan jawabannya.
     */
    public static function svgFotosintesis(string $sisi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas->lingkaran([54, 52], 24, '#f6c445', '#c08a10', 2);

        for ($i = 0; $i < 8; $i++) {
            $a = deg2rad($i * 45);
            $kanvas->garis(
                [54 + cos($a) * 30, 52 + sin($a) * 30],
                [54 + cos($a) * 40, 52 + sin($a) * 40],
                '#c08a10', 2.5,
            );
        }

        self::bidang($kanvas, self::helai([136, 168], 0, 230, 62), '#8fbf63', '#3f6b22', 2.5);

        $kanvas->garis([144, 168], [360, 168], '#3f6b22', 2.5);

        for ($i = 1; $i <= 3; $i++) {
            $x = 160 + $i * 48;
            $kanvas->garis([$x, 168], [$x + 24, 144], '#3f6b22', 1.6)
                ->garis([$x, 168], [$x + 24, 192], '#3f6b22', 1.6);
        }

        $kanvas->panahPx([92, 86], [168, 128], '#c08a10', 2.6)
            ->teksPx([112, 128], 'cahaya matahari', ['ukuran' => 12, 'tebal' => true, 'warna' => '#8a6d1f']);

        $tampilMasuk = $sisi === 'hasil' || $sisi === 'lengkap';
        $tampilKeluar = $sisi === 'bahan' || $sisi === 'lengkap';

        $kanvas
            ->panahPx([444, 118], [362, 148], Kanvas::HITAM, 2.6)
            ->teksPx([438, 102], $tampilMasuk ? 'karbon dioksida' : 'X', [
                'ukuran' => $tampilMasuk ? 12 : 22, 'tebal' => true, 'anchor' => 'end',
            ])
            ->panahPx([250, 272], [250, 224], Kanvas::HITAM, 2.6)
            ->teksPx([250, 288], $tampilMasuk ? 'air' : 'X', ['ukuran' => $tampilMasuk ? 12 : 22, 'tebal' => true])
            ->panahPx([334, 200], [438, 240], $garis, 2.6)
            ->teksPx([446, 262], $tampilKeluar ? 'oksigen' : 'X', [
                'ukuran' => $tampilKeluar ? 12 : 22, 'tebal' => true, 'anchor' => 'end',
            ])
            ->panahPx([160, 198], [62, 238], $garis, 2.6)
            ->teksPx([52, 262], $tampilKeluar ? 'glukosa' : 'X', [
                'ukuran' => $tampilKeluar ? 12 : 22, 'tebal' => true, 'anchor' => 'start',
            ]);

        return $kanvas->render();
    }
}

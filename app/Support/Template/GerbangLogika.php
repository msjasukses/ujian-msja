<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template gerbang logika.
 *
 * Lambang gerbangnya digambar dengan bentuk bakunya — busur untuk OR, sisi
 * melengkung untuk AND, gelembung kecil untuk pembalik — bukan kotak
 * berlabel. Mengenali bentuk itu bagian dari yang diujikan, jadi nama
 * gerbangnya tidak pernah dituliskan pada lambangnya.
 *
 * Keluaran yang ditanyakan selalu diganti tanda tanya pada gambarnya.
 */
class GerbangLogika extends Template
{
    /**
     * Gerbang yang didukung. "bentuk" menentukan rupa lambangnya, "masukan"
     * banyaknya masukan, dan "balik" apakah keluarannya dibalik gelembung.
     *
     * @var array<string, array{nama: string, bentuk: string, masukan: int, balik: bool, aturan: string}>
     */
    public const GERBANG = [
        'and' => [
            'nama' => 'AND', 'bentuk' => 'and', 'masukan' => 2, 'balik' => false,
            'aturan' => 'keluarannya 1 hanya jika kedua masukannya 1',
        ],
        'or' => [
            'nama' => 'OR', 'bentuk' => 'or', 'masukan' => 2, 'balik' => false,
            'aturan' => 'keluarannya 1 jika salah satu atau kedua masukannya 1',
        ],
        'not' => [
            'nama' => 'NOT', 'bentuk' => 'not', 'masukan' => 1, 'balik' => true,
            'aturan' => 'keluarannya kebalikan masukannya',
        ],
        'nand' => [
            'nama' => 'NAND', 'bentuk' => 'and', 'masukan' => 2, 'balik' => true,
            'aturan' => 'keluarannya 0 hanya jika kedua masukannya 1',
        ],
        'nor' => [
            'nama' => 'NOR', 'bentuk' => 'or', 'masukan' => 2, 'balik' => true,
            'aturan' => 'keluarannya 1 hanya jika kedua masukannya 0',
        ],
        'xor' => [
            'nama' => 'XOR', 'bentuk' => 'xor', 'masukan' => 2, 'balik' => false,
            'aturan' => 'keluarannya 1 jika kedua masukannya berbeda',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'keluaran' => 'Keluaran sebuah gerbang',
        'lambang' => 'Nama gerbang dari lambangnya',
        'tabel' => 'Nama gerbang dari tabel kebenarannya',
        'rangkaian' => 'Keluaran dua gerbang berangkai',
    ];

    public static function kode(): string
    {
        return 'gerbang-logika';
    }

    public static function nama(): string
    {
        return 'Gerbang Logika';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Gerbang AND, OR, NOT, NAND, NOR, dan XOR: membaca keluarannya, '
            .'mengenali lambang dan tabel kebenarannya, serta merangkai dua gerbang.';
    }

    public static function ikon(): string
    {
        return 'bi-cpu';
    }

    public static function kataKunci(): array
    {
        return ['gerbang logika', 'logic gate', 'and', 'or', 'not', 'nand', 'nor', 'xor',
            'tabel kebenaran', 'aljabar boolean', 'biner', 'rangkaian logika',
            'informatika', 'tik'];
    }

    public static function kolom(): array
    {
        $nama = array_map(fn ($g) => 'Gerbang '.$g['nama'], self::GERBANG);

        return [
            self::kolomTanya(self::TANYA, 'keluaran'),
            [
                'nama' => 'gerbang',
                'label' => 'Gerbang',
                'jenis' => 'pilihan',
                'pilihan' => $nama,
                'awal' => 'and',
            ],
            [
                'nama' => 'gerbang_dua',
                'label' => 'Gerbang kedua (dirangkai setelahnya)',
                'jenis' => 'pilihan',
                // Gerbang kedua menerima keluaran gerbang pertama dan satu
                // masukan baru, jadi harus bermasukan dua.
                'pilihan' => array_map(
                    fn ($g) => 'Gerbang '.$g['nama'],
                    array_filter(self::GERBANG, fn ($g) => $g['masukan'] === 2),
                ),
                'awal' => 'or',
                'tampil' => ['tanya' => ['rangkaian']],
            ],
            [
                'nama' => 'a',
                'label' => 'Masukan A',
                'jenis' => 'pilihan',
                'pilihan' => ['0' => '0 (mati)', '1' => '1 (hidup)'],
                'awal' => '1',
                'tampil' => ['tanya' => ['rangkaian']],
            ],
            [
                'nama' => 'b',
                'label' => 'Masukan B',
                'jenis' => 'pilihan',
                'pilihan' => ['0' => '0 (mati)', '1' => '1 (hidup)'],
                'awal' => '0',
                'tampil' => ['tanya' => ['rangkaian']],
            ],
            [
                'nama' => 'c',
                'label' => 'Masukan C (ke gerbang kedua)',
                'jenis' => 'pilihan',
                'pilihan' => ['0' => '0 (mati)', '1' => '1 (hidup)'],
                'awal' => '1',
                'tampil' => ['tanya' => ['rangkaian']],
            ],
            self::kolomWarna('ungu'),
        ];
    }

    /** Keluaran sebuah gerbang untuk masukan yang diberikan. */
    public static function hitung(string $kunci, int $a, int $b = 0): int
    {
        $pokok = match ($kunci) {
            'not' => 1 - $a,
            'or', 'nor' => ($a | $b),
            'xor' => $a ^ $b,
            default => ($a & $b),
        };

        return in_array($kunci, ['nand', 'nor'], true) ? 1 - $pokok : $pokok;
    }

    /** Tabel kebenaran sebuah gerbang: baris [a, b, keluaran]. */
    public static function tabelKebenaran(string $kunci): array
    {
        if (self::GERBANG[$kunci]['masukan'] === 1) {
            return [[0, null, self::hitung($kunci, 0)], [1, null, self::hitung($kunci, 1)]];
        }

        $baris = [];

        foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$a, $b]) {
            $baris[] = [$a, $b, self::hitung($kunci, $a, $b)];
        }

        return $baris;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $kunci = isset(self::GERBANG[$isi['gerbang'] ?? '']) ? $isi['gerbang'] : 'and';
        $a = (int) ($isi['a'] ?? 1);
        $b = (int) ($isi['b'] ?? 0);

        return match ($isi['tanya'] ?? 'keluaran') {
            'lambang' => $this->lambang($kunci, $warnaIsi, $garis),
            'tabel' => $this->tabel($kunci, $warnaIsi, $garis),
            'rangkaian' => $this->rangkaian(
                $kunci,
                isset(self::GERBANG[$isi['gerbang_dua'] ?? '']) && self::GERBANG[$isi['gerbang_dua']]['masukan'] === 2
                    ? $isi['gerbang_dua'] : 'or',
                $a, $b, (int) ($isi['c'] ?? 1), $warnaIsi, $garis,
            ),
            default => $this->keluaran($kunci, $warnaIsi, $garis),
        };
    }

    /**
     * Keluaran gerbang untuk seluruh kemungkinan masukannya.
     *
     * Yang ditanyakan sengaja satu kolom penuh, bukan keluaran untuk satu
     * pasang masukan. Keluaran satu pasang masukan hanya bernilai 0 atau 1,
     * sehingga dua dari empat pilihannya pasti bernilai sama dan butirnya
     * bisa dijawab dengan menebak salah satu dari dua angka.
     */
    protected function keluaran(string $kunci, string $warnaIsi, string $garis): array
    {
        $gerbang = self::GERBANG[$kunci];
        $satu = $gerbang['masukan'] === 1;

        $benar = self::kolomKeluaran($kunci);

        // Pengecohnya kolom keluaran gerbang lain — tiap pengecoh pola yang
        // benar-benar ada, hanya milik gerbang yang keliru. Untuk gerbang
        // NOT yang barisnya cuma dua, dipakai seluruh kemungkinan kolomnya.
        $pengecoh = $satu
            ? ['0, 1', '0, 0', '1, 1']
            : array_values(array_filter(
                array_map(
                    fn ($lain) => self::kolomKeluaran($lain),
                    array_keys(array_filter(self::GERBANG, fn ($g) => $g['masukan'] === 2)),
                ),
                fn ($kolom) => $kolom !== $benar,
            ));

        return [
            'svg' => self::svgSoalKolom($kunci, $warnaIsi, $garis),
            'keterangan' => 'Lambang gerbang '.$gerbang['nama'].' beserta tabel kebenarannya '
                .'yang kolom keluarannya dikosongkan.',
            'judul' => 'Kolom keluaran '.$gerbang['nama'].': '.$benar,
            'teks' => 'Perhatikan lambang gerbang logika dan tabel di sampingnya. Kolom Y '
                .'dari baris teratas sampai terbawah berisi ...',
            'pernyataan' => 'Pada tabel kebenaran gerbang berikut, kolom Y dari atas ke bawah '
                .'berisi %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::ciriBentuk($kunci).'<br>Gerbang '.$gerbang['nama'].' '
                .$gerbang['aturan'].', sehingga kolom keluarannya dari atas ke bawah '
                .'berisi <strong>'.$benar.'</strong>.',
        ];
    }

    /** Kolom keluaran sebuah gerbang, ditulis berderet dengan koma. */
    public static function kolomKeluaran(string $kunci): string
    {
        return implode(', ', array_map(
            fn ($baris) => (string) $baris[2],
            self::tabelKebenaran($kunci),
        ));
    }

    protected function lambang(string $kunci, string $warnaIsi, string $garis): array
    {
        $gerbang = self::GERBANG[$kunci];

        return [
            'svg' => self::svgLambang($kunci, $warnaIsi, $garis),
            'keterangan' => 'Lambang gerbang logika '.$gerbang['nama'].' tanpa namanya.',
            'judul' => 'Lambang gerbang '.$gerbang['nama'],
            'teks' => 'Lambang gerbang logika berikut adalah gerbang ...',
            'pernyataan' => 'Lambang gerbang logika berikut adalah gerbang %s.',
            'pilihan' => self::pilihanNama($kunci),
            'kata_kunci' => $gerbang['nama'],
            'pembahasan' => self::ciriBentuk($kunci).'<br>Jadi lambang itu gerbang <strong>'
                .$gerbang['nama'].'</strong>, yang '.$gerbang['aturan'].'.',
        ];
    }

    protected function tabel(string $kunci, string $warnaIsi, string $garis): array
    {
        $gerbang = self::GERBANG[$kunci];
        $baris = self::tabelKebenaran($kunci);

        $urut = implode(', ', array_map(fn ($r) => (string) $r[2], $baris));

        return [
            'svg' => self::svgTabel($kunci, $warnaIsi, $garis),
            'keterangan' => 'Tabel kebenaran dengan kolom keluaran '.$urut.'.',
            'judul' => 'Tabel kebenaran gerbang '.$gerbang['nama'],
            'teks' => 'Perhatikan tabel kebenaran berikut. Tabel itu milik gerbang ...',
            'pernyataan' => 'Tabel kebenaran berikut adalah milik gerbang %s.',
            'pilihan' => self::pilihanNama($kunci),
            'kata_kunci' => $gerbang['nama'],
            'pembahasan' => 'Kolom keluaran tabel itu berturut-turut '.$urut.'.<br>'
                .'Pola itu cocok dengan gerbang <strong>'.$gerbang['nama'].'</strong>, yang '
                .$gerbang['aturan'].'.',
        ];
    }

    protected function rangkaian(string $satu, string $dua, int $a, int $b, int $c, string $warnaIsi, string $garis): array
    {
        $g1 = self::GERBANG[$satu];
        $g2 = self::GERBANG[$dua];

        // Gerbang bermasukan satu hanya memakai A; B diabaikan supaya
        // kalimat soalnya tidak menyebut masukan yang tidak ada.
        $antara = self::hitung($satu, $a, $b);
        $benar = self::hitung($dua, $antara, $c);

        $sebut = $g1['masukan'] === 1
            ? 'A = '.$a.' dan C = '.$c
            : 'A = '.$a.', B = '.$b.', dan C = '.$c;

        return [
            'svg' => self::svgRangkai($satu, $dua, $a, $b, $c, $warnaIsi, $garis),
            'keterangan' => 'Rangkaian gerbang '.$g1['nama'].' diteruskan ke gerbang '
                .$g2['nama'].'.',
            'judul' => $g1['nama'].' → '.$g2['nama'].' = '.$benar,
            'teks' => 'Perhatikan rangkaian dua gerbang logika berikut. Jika '.$sebut
                .', berapakah keluaran Y?',
            'pernyataan' => 'Pada rangkaian berikut dengan '.$sebut.', keluaran Y = %s.',
            'pilihan' => self::pilihanRangkai($benar, $antara, $g1['nama'], $g2['nama']),
            'kata_kunci' => 'Y = '.$benar,
            'pembahasan' => 'Hitung gerbang pertama dahulu. Gerbang '.$g1['nama'].' '
                .$g1['aturan'].', sehingga keluarannya '.$antara.'.<br>'
                .'Keluaran itu menjadi masukan gerbang '.$g2['nama'].' bersama C = '.$c
                .'. Gerbang '.$g2['nama'].' '.$g2['aturan'].', sehingga <strong>Y = '
                .$benar.'</strong>.',
        ];
    }

    // =====================================================================
    // Pilihan jawaban
    // =====================================================================

    /** Nama gerbang sebagai pilihan, yang benar di depan. */
    protected static function pilihanNama(string $kunci): array
    {
        $benar = self::GERBANG[$kunci]['nama'];

        // Pengecohnya didahulukan gerbang yang lambang atau tabelnya mirip:
        // sepasang gerbang yang hanya berbeda gelembung pembalik.
        $mirip = match ($kunci) {
            'and' => ['NAND', 'OR', 'XOR'],
            'nand' => ['AND', 'NOR', 'OR'],
            'or' => ['NOR', 'XOR', 'AND'],
            'nor' => ['OR', 'NAND', 'XOR'],
            'xor' => ['OR', 'NOR', 'AND'],
            default => ['AND', 'OR', 'NAND'],
        };

        return array_map(
            fn ($t) => ['teks' => 'Gerbang '.$t, 'urut' => $t],
            array_merge([$benar], $mirip),
        );
    }

    /**
     * Pilihan untuk soal rangkaian: pasangan nilai antara dan keluaran akhir.
     *
     * Keluaran akhirnya saja hanya 0 atau 1, jadi yang dijadikan pilihan
     * pasangan (keluaran gerbang pertama, keluaran akhir). Empat pasangan
     * yang mungkin menjadi empat pilihan yang benar-benar berbeda, dan tiap
     * pengecoh menunjukkan di langkah mana hitungannya meleset.
     */
    protected static function pilihanRangkai(int $benar, int $antara, string $satu, string $dua): array
    {
        $pilihan = [];

        foreach ([[$antara, $benar], [1 - $antara, $benar], [$antara, 1 - $benar], [1 - $antara, 1 - $benar]] as [$p, $q]) {
            $pilihan[] = [
                'teks' => 'Keluaran gerbang '.$satu.' = '.$p.', sehingga Y = '.$q,
                'urut' => $p * 2 + $q,
            ];
        }

        return $pilihan;
    }

    /** Ciri bentuk lambang tiap gerbang, untuk pembahasan. */
    protected static function ciriBentuk(string $kunci): string
    {
        $pokok = match (self::GERBANG[$kunci]['bentuk']) {
            'and' => 'Sisi kirinya lurus dan sisi kanannya setengah lingkaran — ciri keluarga gerbang AND.',
            'or' => 'Sisi kirinya melengkung ke dalam dan ujung kanannya lancip — ciri keluarga gerbang OR.',
            'xor' => 'Bentuknya seperti gerbang OR, tetapi ada satu busur tambahan di sebelah kirinya — ciri gerbang XOR.',
            default => 'Bentuknya segitiga dengan gelembung kecil di ujungnya — ciri gerbang pembalik NOT.',
        };

        return $pokok.(self::GERBANG[$kunci]['balik'] && self::GERBANG[$kunci]['bentuk'] !== 'not'
            ? ' Gelembung kecil di ujung keluarannya menandakan keluarannya dibalik.'
            : '');
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Menggambar satu lambang gerbang pada kedudukan tertentu.
     *
     * @return array{0: string, 1: float} potongan SVG dan titik x keluarannya
     */
    protected static function bentuk(string $kunci, float $x, float $y, string $isi, string $garis): array
    {
        $g = self::GERBANG[$kunci];
        $svg = '';

        $ujung = match ($g['bentuk']) {
            'not' => $x + 54,
            'and' => $x + 66,
            default => $x + 70,
        };

        $jalur = match ($g['bentuk']) {
            'and' => 'M'.$x.','.($y - 34).' L'.($x + 32).','.($y - 34)
                .' A34,34 0 0 1 '.($x + 32).','.($y + 34).' L'.$x.','.($y + 34).' Z',
            'not' => 'M'.$x.','.($y - 30).' L'.($x + 54).','.$y.' L'.$x.','.($y + 30).' Z',
            default => 'M'.$x.','.($y - 34).' Q'.($x + 34).','.($y - 34).' '.($x + 70).','.$y
                .' Q'.($x + 34).','.($y + 34).' '.$x.','.($y + 34)
                .' Q'.($x + 18).','.$y.' '.$x.','.($y - 34).' Z',
        };

        $svg .= '<path d="'.$jalur.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>';

        // Busur tambahan di kiri badan, penanda XOR.
        if ($g['bentuk'] === 'xor') {
            $svg .= '<path d="M'.($x - 11).','.($y - 34).' Q'.($x + 7).','.$y.' '
                .($x - 11).','.($y + 34).'" fill="none" stroke="'.$garis.'" stroke-width="2.5"/>';
        }

        // Gelembung pembalik di ujung keluaran.
        if ($g['balik']) {
            $svg .= '<circle cx="'.($ujung + 8).'" cy="'.$y.'" r="8" fill="#ffffff" stroke="'
                .$garis.'" stroke-width="2.5"/>';
            $ujung += 16;
        }

        return [$svg, (float) $ujung];
    }

    /** Lambang satu gerbang dengan kaki masukan berlabel dan keluaran Y. */
    public static function svgLambang(string $kunci, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $g = self::GERBANG[$kunci];

        $x = 190.0;
        $y = 160.0;

        [$badan, $ujung] = self::bentuk($kunci, $x, $y, $isi, $garis);

        $kanvas->teksPx([240, 36], 'Lambang gerbang logika', ['ukuran' => 16, 'tebal' => true]);

        // Kaki masukan: satu di tengah untuk NOT, dua terpisah untuk sisanya.
        $kaki = $g['masukan'] === 1 ? [[$y, 'A']] : [[$y - 18, 'A'], [$y + 18, 'B']];
        $pangkal = $g['bentuk'] === 'xor' ? $x - 11 : $x;

        foreach ($kaki as [$ky, $label]) {
            $kanvas
                ->garis([$pangkal - 56, $ky], [$pangkal + 2, $ky], Kanvas::HITAM, 2.5)
                ->teksPx([$pangkal - 66, $ky + 5], $label, ['ukuran' => 15, 'tebal' => true]);
        }

        return $kanvas
            ->tambah($badan)
            ->garis([$ujung, $y], [$ujung + 56, $y], Kanvas::HITAM, 2.5)
            ->teksPx([$ujung + 68, $y + 5], 'Y', ['ukuran' => 15, 'tebal' => true])
            ->teksPx([240, 266], 'nama gerbangnya sengaja tidak dituliskan', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }

    /**
     * Lambang gerbang di kiri dan tabel kebenarannya di kanan, dengan kolom
     * keluaran dikosongkan — itulah yang ditanyakan.
     */
    public static function svgSoalKolom(string $kunci, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $g = self::GERBANG[$kunci];
        $satu = $g['masukan'] === 1;

        $kanvas->teksPx([240, 30], 'Gerbang logika dan tabel kebenarannya', [
            'ukuran' => 14, 'tebal' => true,
        ]);

        // Lambangnya di separuh kiri kanvas.
        $x = 62.0;
        $y = 154.0;

        [$badan, $ujung] = self::bentuk($kunci, $x, $y, $isi, $garis);

        $kaki = $satu ? [[$y, 'A']] : [[$y - 18, 'A'], [$y + 18, 'B']];
        $pangkal = $g['bentuk'] === 'xor' ? $x - 11 : $x;

        foreach ($kaki as [$ky, $label]) {
            $kanvas
                ->garis([$pangkal - 36, $ky], [$pangkal + 2, $ky], Kanvas::HITAM, 2.5)
                ->teksPx([$pangkal - 46, $ky + 5], $label, ['ukuran' => 14, 'tebal' => true]);
        }

        $kanvas
            ->tambah($badan)
            ->garis([$ujung, $y], [$ujung + 34, $y], Kanvas::HITAM, 2.5)
            ->teksPx([$ujung + 46, $y + 5], 'Y', ['ukuran' => 14, 'tebal' => true]);

        // Tabelnya di separuh kanan, kolom Y diisi tanda tanya.
        $baris = [$satu ? ['A', 'Y'] : ['A', 'B', 'Y']];

        foreach (self::tabelKebenaran($kunci) as $r) {
            $baris[] = $satu
                ? [(string) $r[0], '?']
                : [(string) $r[0], (string) $r[1], '?'];
        }

        $lebar = $satu ? [84.0, 104.0] : [52.0, 52.0, 84.0];
        $tinggiBaris = 32.0;
        $atas = $satu ? 116.0 : 84.0;

        return $kanvas
            ->tabelPx([252.0, $atas], $lebar, $tinggiBaris, $baris, $isi, $garis, 14)
            ->teksPx([240, 282], 'kolom Y belum diisi', ['ukuran' => 11, 'warna' => '#6b7280'])
            ->render();
    }

    /** Tabel kebenaran lengkap sebuah gerbang, tanpa namanya. */
    public static function svgTabel(string $kunci, string $isi, string $garis): string
    {
        $g = self::GERBANG[$kunci];
        $satu = $g['masukan'] === 1;

        $baris = [$satu ? ['A', 'Keluaran'] : ['A', 'B', 'Keluaran']];

        foreach (self::tabelKebenaran($kunci) as $r) {
            $baris[] = $satu
                ? [(string) $r[0], (string) $r[2]]
                : [(string) $r[0], (string) $r[1], (string) $r[2]];
        }

        $lebar = $satu ? [100.0, 140.0] : [80.0, 80.0, 140.0];
        $tinggiBaris = 34.0;
        $kiri = 240 - array_sum($lebar) / 2;
        $atas = 86.0;

        return (new Kanvas)
            ->teksPx([240, 46], 'Tabel kebenaran', ['ukuran' => 16, 'tebal' => true])
            ->tabelPx([$kiri, $atas], $lebar, $tinggiBaris, $baris, $isi, $garis, 14)
            ->teksPx([240, $atas + count($baris) * $tinggiBaris + 26],
                'gerbang apakah yang menghasilkan kolom keluaran ini?', [
                    'ukuran' => 11, 'warna' => '#6b7280',
                ])
            ->render();
    }

    /** Dua gerbang berangkai: keluaran yang pertama menjadi masukan kedua. */
    public static function svgRangkai(string $satu, string $dua, int $a, int $b, int $c, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $g1 = self::GERBANG[$satu];
        $y1 = 116.0;
        $y2 = 168.0;

        [$badan1, $ujung1] = self::bentuk($satu, 128.0, $y1, $isi, $garis);
        [$badan2, $ujung2] = self::bentuk($dua, 296.0, $y2, $isi, $garis);

        $kanvas->teksPx([240, 34], 'Rangkaian gerbang logika', ['ukuran' => 15, 'tebal' => true]);

        // Kaki masukan gerbang pertama.
        $pangkal1 = $g1['bentuk'] === 'xor' ? 117.0 : 128.0;
        $kaki = $g1['masukan'] === 1
            ? [[$y1, 'A', $a]]
            : [[$y1 - 18, 'A', $a], [$y1 + 18, 'B', $b]];

        foreach ($kaki as [$ky, $label, $nilai]) {
            $kanvas
                ->garis([54, $ky], [$pangkal1 + 2, $ky], Kanvas::HITAM, 2.5)
                ->teksPx([44, $ky + 5], $label, ['ukuran' => 14, 'tebal' => true])
                ->teksPx([86, $ky - 9], (string) $nilai, [
                    'ukuran' => 14, 'tebal' => true, 'warna' => $garis,
                ]);
        }

        // Kawat penghubung: keluaran gerbang pertama turun ke kaki atas
        // gerbang kedua. Nilainya tidak dituliskan — itu langkah antara
        // yang harus dihitung sendiri.
        $pangkal2 = self::GERBANG[$dua]['bentuk'] === 'xor' ? 285.0 : 296.0;

        $kanvas
            ->tambah($badan1)
            ->tambah($badan2)
            ->garisPatah([
                [$ujung1, $y1], [$ujung1 + 26, $y1],
                [$ujung1 + 26, $y2 - 18], [$pangkal2 + 2, $y2 - 18],
            ], Kanvas::HITAM, 2.5)
            // Kaki masukan C gerbang kedua, ditarik dari tepi kiri.
            ->garisPatah([
                [54, 236], [$ujung1 + 50, 236],
                [$ujung1 + 50, $y2 + 18], [$pangkal2 + 2, $y2 + 18],
            ], Kanvas::HITAM, 2.5)
            ->teksPx([44, 241], 'C', ['ukuran' => 14, 'tebal' => true])
            ->teksPx([86, 227], (string) $c, ['ukuran' => 14, 'tebal' => true, 'warna' => $garis])
            ->garis([$ujung2, $y2], [$ujung2 + 52, $y2], Kanvas::HITAM, 2.5)
            ->teksPx([$ujung2 + 64, $y2 + 5], 'Y', ['ukuran' => 15, 'tebal' => true])
            ->teksPx([$ujung2 + 26, $y2 - 10], '?', ['ukuran' => 18, 'tebal' => true, 'warna' => $garis]);

        return $kanvas
            ->teksPx([240, 278], 'keluaran gerbang pertama menjadi masukan gerbang kedua', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

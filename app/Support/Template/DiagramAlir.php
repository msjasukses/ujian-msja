<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template diagram alir & algoritma.
 *
 * Bagannya disusun dari langkah-langkah algoritma, bukan digambar per soal,
 * sehingga bentuk tiap simbol dan jalur percabangannya selalu sesuai dengan
 * algoritma yang ditanyakan. Soal menelusuri keluaran pun dihitung dari
 * algoritma yang sama dengan yang tergambar, jadi kuncinya tidak mungkin
 * berselisih dengan bagannya.
 */
class DiagramAlir extends Template
{
    /**
     * Algoritma beserta langkahnya. Yang bersyarat punya percabangan ya dan
     * tidak; yang tidak bersyarat punya satu langkah proses.
     *
     * @var array<string, array<string, mixed>>
     */
    public const ALGORITMA = [
        'genap' => [
            'nama' => 'menentukan bilangan genap atau ganjil',
            'masukan' => 'Masukkan N',
            'syarat' => 'N habis dibagi 2?',
            'ya' => 'GENAP',
            'tidak' => 'GANJIL',
            'pakai_b' => false,
        ],
        'lulus' => [
            'nama' => 'menentukan kelulusan siswa',
            'masukan' => 'Masukkan nilai',
            'syarat' => 'nilai ≥ 75?',
            'ya' => 'LULUS',
            'tidak' => 'TIDAK LULUS',
            'pakai_b' => false,
        ],
        'terbesar' => [
            'nama' => 'mencari bilangan terbesar',
            'masukan' => 'Masukkan A dan B',
            'syarat' => 'A > B?',
            'ya' => 'Tulis A',
            'tidak' => 'Tulis B',
            'pakai_b' => true,
        ],
        'luas' => [
            'nama' => 'menghitung luas persegi panjang',
            'masukan' => 'Masukkan p dan l',
            'proses' => 'L ← p × l',
            'keluaran' => 'Tulis L',
            'pakai_b' => true,
        ],
    ];

    /**
     * Simbol diagram alir beserta kegunaannya.
     *
     * @var array<string, array{nama: string, fungsi: string}>
     */
    public const SIMBOL = [
        'kapsul' => [
            'nama' => 'Kapsul (terminator)',
            'fungsi' => 'menandai awal dan akhir sebuah algoritma',
        ],
        'jajar' => [
            'nama' => 'Jajar genjang',
            'fungsi' => 'menyatakan masukan data atau keluaran hasil',
        ],
        'persegi' => [
            'nama' => 'Persegi panjang',
            'fungsi' => 'menyatakan proses atau perhitungan',
        ],
        'belah' => [
            'nama' => 'Belah ketupat',
            'fungsi' => 'menyatakan pengambilan keputusan yang bercabang',
        ],
    ];

    /** Langkah yang dapat ditandai pada bagan. */
    public const LANGKAH = [
        'mulai' => 'Langkah paling awal',
        'masukan' => 'Langkah memasukkan data',
        'tengah' => 'Langkah di tengah bagan',
        'keluaran' => 'Langkah menuliskan hasil',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'keluaran' => 'Hasil yang dituliskan untuk masukan tertentu',
        'nama_simbol' => 'Nama simbol yang ditandai',
        'fungsi_simbol' => 'Kegunaan simbol yang ditandai',
    ];

    public static function kode(): string
    {
        return 'diagram-alir';
    }

    public static function nama(): string
    {
        return 'Diagram Alir & Algoritma';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Bagan alir empat algoritma dasar: menelusuri keluarannya untuk '
            .'masukan tertentu, serta mengenali nama dan kegunaan tiap simbolnya.';
    }

    public static function ikon(): string
    {
        return 'bi-diagram-2';
    }

    public static function kataKunci(): array
    {
        return ['diagram alir', 'flowchart', 'algoritma', 'informatika', 'pemrograman',
            'percabangan', 'keputusan', 'terminator', 'belah ketupat', 'jajar genjang',
            'masukan', 'keluaran', 'proses', 'logika'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'algoritma',
                'label' => 'Algoritma',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => ucfirst($a['nama']), self::ALGORITMA),
                'awal' => 'genap',
            ],
            self::kolomTanya(self::TANYA, 'keluaran'),
            self::kolomAngka('nilai_a', 'Masukan pertama', 7, 0, 1000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['keluaran']],
            ]),
            self::kolomAngka('nilai_b', 'Masukan kedua', 4, 0, 1000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['keluaran']],
            ]),
            [
                'nama' => 'langkah',
                'label' => 'Langkah yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => self::LANGKAH,
                'awal' => 'tengah',
                'tampil' => ['tanya' => ['nama_simbol', 'fungsi_simbol']],
            ],
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $algoritma = self::ALGORITMA[$isi['algoritma'] ?? 'genap'] ?? self::ALGORITMA['genap'];

        // Algoritma bersyarat menuliskan hasilnya di dalam cabang, jadi tidak
        // punya langkah keluaran tersendiri untuk ditandai.
        if (in_array($isi['tanya'] ?? '', ['nama_simbol', 'fungsi_simbol'], true)
            && ($isi['langkah'] ?? '') === 'keluaran'
            && isset($algoritma['syarat'])) {
            return ['langkah' => 'Algoritma ini menuliskan hasilnya di dalam percabangan, '
                .'bukan pada langkah keluaran tersendiri.'];
        }

        if (($isi['tanya'] ?? '') === 'keluaran'
            && ($algoritma['syarat'] ?? '') === 'A > B?'
            && (int) ($isi['nilai_a'] ?? 0) === (int) ($isi['nilai_b'] ?? 0)) {
            return ['nilai_b' => 'Isi dua bilangan yang berbeda agar ada yang terbesar.'];
        }

        return [];
    }

    /** Simbol yang dipakai sebuah langkah pada algoritma tertentu. */
    public static function simbolLangkah(string $langkah, array $algoritma): string
    {
        return match ($langkah) {
            'mulai' => 'kapsul',
            'tengah' => isset($algoritma['syarat']) ? 'belah' : 'persegi',
            default => 'jajar',
        };
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $kunci = isset(self::ALGORITMA[$isi['algoritma'] ?? '']) ? $isi['algoritma'] : 'genap';
        $algoritma = self::ALGORITMA[$kunci];
        $tanya = $isi['tanya'] ?? 'keluaran';

        if ($tanya === 'keluaran') {
            return $this->keluaran($kunci, $algoritma, (int) ($isi['nilai_a'] ?? 7),
                (int) ($isi['nilai_b'] ?? 4), $warnaIsi, $garis);
        }

        $langkah = isset(self::LANGKAH[$isi['langkah'] ?? '']) ? $isi['langkah'] : 'tengah';

        return $this->simbol($kunci, $algoritma, $langkah, $tanya === 'fungsi_simbol', $warnaIsi, $garis);
    }

    protected function keluaran(string $kunci, array $algoritma, int $a, int $b, string $warnaIsi, string $garis): array
    {
        $pakaiB = $algoritma['pakai_b'];
        $diketahui = match ($kunci) {
            'terbesar' => "A = {$a} dan B = {$b}",
            'luas' => "p = {$a} dan l = {$b}",
            'lulus' => "nilai = {$a}",
            default => "N = {$a}",
        };

        $bahan = [
            'svg' => self::svgAlir($algoritma, null, $warnaIsi, $garis),
            'keterangan' => 'Diagram alir untuk '.$algoritma['nama'].'.',
            'teks' => 'Perhatikan diagram alir berikut. Bila dimasukkan '.$diketahui
                .', apa yang dituliskan program itu?',
            'pernyataan' => 'Pada diagram alir berikut, masukan '.$diketahui
                .' menghasilkan tulisan %s.',
        ];

        if ($kunci === 'luas') {
            $benar = $a * $b;

            return $bahan + [
                'judul' => 'Luas '.Angka::id($benar),
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                    array_merge([$benar], self::saring($benar, [
                        // Pengecohnya keliru memakai rumus keliling, atau
                        // menjumlahkan alih-alih mengalikan.
                        $a + $b, 2 * ($a + $b), $benar / 2, $a * $b * 2, abs($a - $b),
                    ], 0)),
                ),
                'kata_kunci' => Angka::id($benar),
                'pembahasan' => 'Bagan itu tidak punya percabangan: setelah p dan l dimasukkan, '
                    .'langkah prosesnya menghitung L ← p × l.<br>L = '.$a.' × '.$b.' = '
                    .Angka::id($benar).', lalu nilai itulah yang dituliskan.',
            ];
        }

        if ($kunci === 'terbesar') {
            $benar = max($a, $b);
            $jalur = $a > $b ? 'Ya' : 'Tidak';

            return $bahan + [
                'judul' => 'Keluaran '.Angka::id($benar),
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                    array_merge([$benar], self::saring($benar, [
                        min($a, $b), $a + $b, abs($a - $b), $benar * 2,
                    ], 0)),
                ),
                'kata_kunci' => Angka::id($benar),
                'pembahasan' => "Syaratnya A > B, yaitu {$a} > {$b}, bernilai "
                    .($a > $b ? 'benar' : 'salah').". Jalur yang dilalui adalah jalur {$jalur}, "
                    .'yang menuliskan '.($a > $b ? 'A' : 'B').' = '.Angka::id($benar).'.',
            ];
        }

        // Dua hal diuji sekaligus: jalur mana yang dilalui dan apa yang
        // dituliskannya. Pilihan menyilangkan keduanya, sehingga yang hafal
        // jawabannya saja tetap harus membaca syaratnya.
        $lewatYa = $kunci === 'genap' ? $a % 2 === 0 : $a >= 75;
        $benar = 'Jalur '.($lewatYa ? 'Ya' : 'Tidak').', menuliskan '
            .($lewatYa ? $algoritma['ya'] : $algoritma['tidak']);

        $syarat = $kunci === 'genap'
            ? "{$a} habis dibagi 2 bernilai ".($lewatYa ? 'benar' : 'salah')
            : "{$a} ≥ 75 bernilai ".($lewatYa ? 'benar' : 'salah');

        return $bahan + [
            'judul' => $benar,
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Jalur Ya, menuliskan '.$algoritma['ya'],
                    'Jalur Tidak, menuliskan '.$algoritma['tidak'],
                    'Jalur Ya, menuliskan '.$algoritma['tidak'],
                    'Jalur Tidak, menuliskan '.$algoritma['ya'],
                ], [$benar]))),
            ),
            'kata_kunci' => $lewatYa ? $algoritma['ya'] : $algoritma['tidak'],
            'pembahasan' => 'Pada belah ketupat, '.$syarat.'.<br>Karena itu program mengikuti '
                .'jalur '.($lewatYa ? 'Ya' : 'Tidak').', lalu menuliskan '
                .($lewatYa ? $algoritma['ya'] : $algoritma['tidak']).'.',
        ];
    }

    protected function simbol(string $kunci, array $algoritma, string $langkah, bool $tanyaFungsi, string $warnaIsi, string $garis): array
    {
        $simbol = self::SIMBOL[self::simbolLangkah($langkah, $algoritma)];
        $benar = $tanyaFungsi ? ucfirst($simbol['fungsi']) : $simbol['nama'];

        return [
            'svg' => self::svgAlir($algoritma, $langkah, $warnaIsi, $garis),
            'keterangan' => 'Diagram alir untuk '.$algoritma['nama'].'; satu simbol ditandai X.',
            'judul' => $simbol['nama'].($tanyaFungsi ? ': kegunaannya' : ''),
            'teks' => 'Perhatikan diagram alir berikut. Simbol yang ditandai huruf X '
                .($tanyaFungsi ? 'digunakan untuk ...' : 'disebut ...'),
            'pernyataan' => 'Pada diagram alir berikut, simbol yang ditandai huruf X '
                .($tanyaFungsi ? 'digunakan untuk %s.' : 'disebut %s.'),
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                // Pengecohnya simbol diagram alir yang lain — semuanya ada
                // pada bagan yang sama, jadi tidak ada yang bisa dicoret
                // hanya karena tidak dikenal.
                array_merge(
                    [$tanyaFungsi ? $simbol['fungsi'] : $simbol['nama']],
                    array_values(array_diff(
                        array_column(self::SIMBOL, $tanyaFungsi ? 'fungsi' : 'nama'),
                        [$tanyaFungsi ? $simbol['fungsi'] : $simbol['nama']],
                    )),
                ),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Simbol yang ditandai X berbentuk '
                .mb_strtolower($simbol['nama']).', yang digunakan untuk '.$simbol['fungsi'].'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bagan alir sebuah algoritma. Algoritma bersyarat digambar bercabang
     * dua yang bertemu kembali sebelum berakhir; yang tidak bersyarat
     * digambar lurus ke bawah.
     *
     * @param  string|null  $ditandai  langkah yang diberi huruf X
     */
    public static function svgAlir(array $algoritma, ?string $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $bersyarat = isset($algoritma['syarat']);

        // Tiap simpul membawa sendiri letak huruf X-nya, sebab ruang kosong
        // di sekitarnya berbeda-beda: di samping untuk simpul yang berderet
        // lurus, di bawah untuk belah ketupat yang kedua sisinya bercabang.
        $simpul = $bersyarat
            ? [
                ['mulai', 'kapsul', [240, 34], 120, 30, 'MULAI', [326, 34]],
                ['masukan', 'jajar', [240, 88], 180, 36, $algoritma['masukan'], [356, 88]],
                ['tengah', 'belah', [240, 152], 190, 60, $algoritma['syarat'], [240, 196]],
            ]
            : [
                ['mulai', 'kapsul', [240, 34], 120, 30, 'MULAI', [326, 34]],
                ['masukan', 'jajar', [240, 92], 170, 36, $algoritma['masukan'], [351, 92]],
                ['tengah', 'persegi', [240, 152], 170, 40, $algoritma['proses'], [351, 152]],
                ['keluaran', 'jajar', [240, 212], 170, 36, $algoritma['keluaran'], [351, 212]],
                ['selesai', 'kapsul', [240, 270], 120, 30, 'SELESAI', [326, 270]],
            ];

        foreach ($simpul as $i => [$peran, $bentuk, $pusat, $w, $h, $teks, $tanda]) {
            $ini = $ditandai === $peran;

            self::simbolPx($kanvas, $bentuk, $pusat, $w, $h, $teks, $ini ? $isi : '#ffffff', $garis);

            if ($i > 0) {
                $kanvas->panahPx(
                    [240, $simpul[$i - 1][2][1] + $simpul[$i - 1][4] / 2],
                    [240, $pusat[1] - $h / 2], Kanvas::HITAM, 2.2,
                );
            }

            if ($ini) {
                self::huruf($kanvas, $tanda, $garis);
            }
        }

        if (! $bersyarat) {
            return $kanvas->render();
        }

        // Dua cabang keluar dari sisi kiri dan kanan belah ketupat, lalu
        // bertemu kembali pada satu titik sebelum kapsul SELESAI.
        foreach ([[145, 116, 'Ya', $algoritma['ya']], [335, 364, 'Tidak', $algoritma['tidak']]] as [$sisi, $x, $label, $teks]) {
            $kanvas
                ->garis([$sisi, 152], [$x, 152], Kanvas::HITAM, 2.2)
                ->panahPx([$x, 152], [$x, 204], Kanvas::HITAM, 2.2)
                ->teksPx([($sisi + $x) / 2, 144], $label, ['ukuran' => 13, 'tebal' => true]);

            self::simbolPx($kanvas, 'jajar', [$x, 223], 150, 38, 'Tulis "'.$teks.'"', '#ffffff', $garis);

            $kanvas
                ->garis([$x, 242], [$x, 258], Kanvas::HITAM, 2.2)
                ->garis([$x, 258], [240, 258], Kanvas::HITAM, 2.2);
        }

        $kanvas->panahPx([240, 258], [240, 265], Kanvas::HITAM, 2.2);
        self::simbolPx($kanvas, 'kapsul', [240, 282], 120, 30, 'SELESAI', '#ffffff', $garis);

        return $kanvas->render();
    }

    /** Satu simbol diagram alir beserta tulisan di dalamnya. */
    protected static function simbolPx(Kanvas $kanvas, string $bentuk, array $pusat, float $w, float $h, string $teks, string $isi, string $garis): Kanvas
    {
        [$x, $y] = [$pusat[0] - $w / 2, $pusat[1] - $h / 2];

        match ($bentuk) {
            'kapsul' => $kanvas->tambah('<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h
                .'" rx="'.($h / 2).'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>'),
            'jajar' => $kanvas->tambah('<polygon points="'.($x + 16).','.$y.' '.($x + $w).','.$y
                .' '.($x + $w - 16).','.($y + $h).' '.$x.','.($y + $h).'" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5" stroke-linejoin="round"/>'),
            'belah' => $kanvas->tambah('<polygon points="'.$pusat[0].','.$y.' '.($x + $w).','.$pusat[1]
                .' '.$pusat[0].','.($y + $h).' '.$x.','.$pusat[1].'" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5" stroke-linejoin="round"/>'),
            default => $kanvas->tambah('<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h
                .'" rx="3" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>'),
        };

        // Belah ketupat hanya lebar di tengahnya, jadi tulisannya dipecah
        // lebih sempit daripada lebar simbolnya.
        $lebarTeks = $bentuk === 'belah' ? $w * 0.62 : $w - 26;

        return $kanvas->teksBaris($pusat, Kanvas::baris($teks, $lebarTeks, 13), ['ukuran' => 13]);
    }

    /** Huruf X beralas putih yang menandai sebuah simbol. */
    protected static function huruf(Kanvas $kanvas, array $di, string $garis): Kanvas
    {
        return $kanvas
            ->lingkaran($di, 14, '#ffffff', $garis, 3)
            ->teksPx([$di[0], $di[1] + 7], 'X', ['ukuran' => 20, 'tebal' => true]);
    }
}

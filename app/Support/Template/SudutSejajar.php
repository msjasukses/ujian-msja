<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template sudut pada dua garis sejajar yang dipotong garis lain.
 *
 * Kemiringan garis pemotongnya dihitung dari besar sudut yang diketahui,
 * jadi sudut yang tergambar benar-benar sebesar yang tertulis — bukan
 * gambar baku yang dipakai ulang untuk angka berapa pun. Siswa yang
 * mengira-ira dari gambar akan sampai pada jawaban yang sama dengan yang
 * menghitung, dan itu memang yang diharapkan.
 */
class SudutSejajar extends Template
{
    /**
     * Nama hubungan tiap pasangan sudut. Pasangan ditulis dengan nomor yang
     * lebih kecil di depan; pasangan yang tidak tercantum memang tidak punya
     * sebutan baku, dan soal menanyakan hubungannya ditolak.
     *
     * @var array<string, string>
     */
    public const HUBUNGAN = [
        '1-5' => 'Sehadap', '2-6' => 'Sehadap', '3-7' => 'Sehadap', '4-8' => 'Sehadap',
        '1-4' => 'Bertolak belakang', '2-3' => 'Bertolak belakang',
        '5-8' => 'Bertolak belakang', '6-7' => 'Bertolak belakang',
        '3-6' => 'Berseberangan dalam', '4-5' => 'Berseberangan dalam',
        '1-8' => 'Berseberangan luar', '2-7' => 'Berseberangan luar',
        '3-5' => 'Sepihak dalam', '4-6' => 'Sepihak dalam',
        '1-7' => 'Sepihak luar', '2-8' => 'Sepihak luar',
        '1-2' => 'Berpelurus', '3-4' => 'Berpelurus', '5-6' => 'Berpelurus', '7-8' => 'Berpelurus',
        '1-3' => 'Berpelurus', '2-4' => 'Berpelurus', '5-7' => 'Berpelurus', '6-8' => 'Berpelurus',
    ];

    /**
     * Sudut bernomor ini sama besar dengan sudut yang diketahui; sisanya
     * merupakan pelurusnya. Kedua garis sejajar membuat keempatnya sama.
     *
     * @var array<int, int>
     */
    public const SAMA = [1, 4, 5, 8];

    /** @var array<string, string> */
    public const TANYA = [
        'besar' => 'Besar sudut yang ditanyakan',
        'hubungan' => 'Nama hubungan kedua sudut',
    ];

    public static function kode(): string
    {
        return 'sudut-sejajar';
    }

    public static function nama(): string
    {
        return 'Sudut pada Dua Garis Sejajar';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Dua garis sejajar dipotong garis lain: menghitung besar sudut yang '
            .'lain serta menamai hubungannya — sehadap, berseberangan, atau sepihak.';
    }

    public static function ikon(): string
    {
        return 'bi-slash-lg';
    }

    public static function kataKunci(): array
    {
        return ['sudut', 'garis sejajar', 'garis transversal', 'sehadap', 'berseberangan',
            'sepihak', 'bertolak belakang', 'berpelurus', 'dalam', 'luar', 'geometri'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomAngka('besar', 'Besar sudut yang diketahui (°)', 65, 20, 160, [
                'langkah' => 1, 'lebar' => 12,
            ]),
            self::kolomAngka('diketahui', 'Nomor sudut yang diketahui', 1, 1, 8, [
                'langkah' => 1, 'lebar' => 6,
            ]),
            self::kolomAngka('ditanya', 'Nomor sudut yang ditanyakan', 5, 1, 8, [
                'langkah' => 1, 'lebar' => 6,
            ]),
            self::kolomTanya(self::TANYA, 'besar'),
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $a = (int) ($isi['diketahui'] ?? 1);
        $b = (int) ($isi['ditanya'] ?? 5);

        if ($a === $b) {
            return ['ditanya' => 'Pilih nomor sudut yang berbeda dengan yang diketahui.'];
        }

        if (($isi['tanya'] ?? 'besar') === 'hubungan' && self::hubungan($a, $b) === null) {
            return ['ditanya' => 'Pasangan sudut '.$a.' dan '.$b.' tidak punya sebutan baku. '
                .'Pilih pasangan lain, misalnya yang sehadap atau yang berseberangan.'];
        }

        return [];
    }

    /** Nama hubungan dua sudut, atau null bila pasangannya tak bernama. */
    public static function hubungan(int $a, int $b): ?string
    {
        $kunci = min($a, $b).'-'.max($a, $b);

        return self::HUBUNGAN[$kunci] ?? null;
    }

    /**
     * Besar sebuah sudut bernomor tertentu bila sudut nomor $dari bernilai
     * $besar. Pada dua garis sejajar hanya ada dua nilai yang mungkin:
     * sudutnya sendiri dan pelurusnya.
     */
    public static function nilai(int $nomor, int $dari, float $besar): float
    {
        $sekelompok = in_array($nomor, self::SAMA, true) === in_array($dari, self::SAMA, true);

        return $sekelompok ? $besar : 180 - $besar;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $besar = (float) ($isi['besar'] ?? 65);
        $a = (int) ($isi['diketahui'] ?? 1);
        $b = (int) ($isi['ditanya'] ?? 5);

        if ($a === $b) {
            $b = $a === 8 ? 1 : $a + 1;
        }

        $bahan = [
            'svg' => self::svgSejajar($besar, $a, $b, $warnaIsi, $garis),
            'keterangan' => 'Dua garis sejajar dipotong sebuah garis; sudut '.$a.' besarnya '
                .Angka::id($besar).'° dan sudut '.$b.' ditanyakan.',
        ];

        $awalan = 'Perhatikan gambar dua garis sejajar yang dipotong sebuah garis berikut. '
            .'Sudut nomor '.$a.' besarnya '.Angka::id($besar).'°.';

        return $bahan + (($isi['tanya'] ?? 'besar') === 'hubungan'
            ? $this->namaHubungan($awalan, $a, $b)
            : $this->besarSudut($awalan, $a, $b, $besar));
    }

    protected function besarSudut(string $awalan, int $a, int $b, float $besar): array
    {
        $benar = self::nilai($b, $a, $besar);
        $sama = abs($benar - $besar) < 0.0001;
        $nama = self::hubungan($a, $b);

        return [
            'judul' => 'Sudut '.$b.' = '.Angka::ringkas($benar).'°',
            'teks' => $awalan.' Berapakah besar sudut nomor '.$b.'?',
            'pernyataan' => $awalan.' Besar sudut nomor '.$b.' adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).'°', 'urut' => $n],
                array_merge([$benar], self::saring($benar, array_filter([
                    // Pengecoh terpentingnya pelurus jawabannya: yang keliru
                    // biasanya memakai hubungan yang salah, dan hasilnya
                    // tepat 180° dikurangi jawaban yang benar.
                    180 - $benar, 90, $benar / 2, $benar * 2, 180 - $benar / 2,
                    // Sudut lurus dan yang lebih besar darinya dibuang: tidak
                    // satu pun sudut pada gambar ini bisa sebesar itu.
                ], fn ($n) => $n < 180), 2)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => ($nama === null
                ? 'Pada dua garis sejajar yang dipotong sebuah garis, tiap sudut hanya punya dua '
                    .'kemungkinan besar: sama dengan sudut yang diketahui, atau pelurusnya.'
                : 'Sudut '.$a.' dan sudut '.$b.' '.mb_strtolower($nama).'.')
                .'<br>'.($sama
                    ? 'Keduanya sama besar, jadi sudut '.$b.' = '.Angka::ringkas($benar).'°.'
                    : 'Keduanya berpelurus, jadi sudut '.$b.' = 180° − '.Angka::id($besar).'° = '
                        .Angka::ringkas($benar).'°.'),
        ];
    }

    protected function namaHubungan(string $awalan, int $a, int $b): array
    {
        $benar = self::hubungan($a, $b) ?? 'Sehadap';

        return [
            'judul' => 'Sudut '.$a.' dan '.$b.': '.mb_strtolower($benar),
            'teks' => $awalan.' Sudut nomor '.$a.' dan sudut nomor '.$b.' disebut sudut ...',
            'pernyataan' => $awalan.' Sudut nomor '.$a.' dan sudut nomor '.$b.' disebut sudut %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya sebutan hubungan yang lain — semuanya ada pada
                // gambar yang sama, jadi tak satu pun dapat dicoret hanya
                // karena terdengar asing.
                array_merge([$benar], array_values(array_diff(
                    array_unique(array_values(self::HUBUNGAN)), [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::penjelasanHubungan($benar).'<br>Pada gambar itu sudut '.$a
                .' dan sudut '.$b.' memenuhi kedudukan tersebut, sehingga keduanya disebut sudut '
                .mb_strtolower($benar).'.',
        ];
    }

    /** Ciri kedudukan tiap jenis pasangan sudut. */
    protected static function penjelasanHubungan(string $nama): string
    {
        return match ($nama) {
            'Sehadap' => 'Sudut sehadap terletak pada sisi yang sama terhadap garis pemotong dan '
                .'pada kedudukan yang sama terhadap kedua garis sejajar; besarnya sama.',
            'Bertolak belakang' => 'Sudut bertolak belakang berada di satu titik potong dan saling '
                .'berhadapan melalui titik itu; besarnya sama.',
            'Berseberangan dalam' => 'Sudut berseberangan dalam berada di antara kedua garis '
                .'sejajar dan di sisi yang berlawanan terhadap garis pemotong; besarnya sama.',
            'Berseberangan luar' => 'Sudut berseberangan luar berada di luar kedua garis sejajar '
                .'dan di sisi yang berlawanan terhadap garis pemotong; besarnya sama.',
            'Sepihak dalam' => 'Sudut sepihak dalam berada di antara kedua garis sejajar dan di '
                .'sisi yang sama terhadap garis pemotong; jumlahnya 180°.',
            'Sepihak luar' => 'Sudut sepihak luar berada di luar kedua garis sejajar dan di sisi '
                .'yang sama terhadap garis pemotong; jumlahnya 180°.',
            default => 'Sudut berpelurus terletak berdampingan pada satu garis lurus; '
                .'jumlahnya 180°.',
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Dua garis sejajar yang dipotong sebuah garis, dengan kedelapan sudutnya
     * bernomor.
     *
     * Kemiringan garis pemotong dihitung dari besar sudut yang diketahui,
     * sehingga sudut yang tergambar sungguh sebesar angka pada soalnya.
     */
    public static function svgSejajar(float $besar, int $diketahui, int $ditanya, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$atas, $bawah] = [100, 220];
        $jarak = $bawah - $atas;

        // Garis pemotong turun ke kanan membentuk sudut sebesar $besar
        // terhadap arah mendatar; itulah sudut nomor 4 — dan nomor 1, yang
        // bertolak belakang dengannya.
        $rad = deg2rad($besar);
        $geser = $jarak / tan($rad);

        $px = 240 - $geser / 2;
        $qx = 240 + $geser / 2;

        $kanvas
            ->garis([24, $atas], [456, $atas], Kanvas::HITAM, 2.5)
            ->garis([24, $bawah], [456, $bawah], Kanvas::HITAM, 2.5);

        // Garis pemotong dipanjangkan melewati kedua titik potong supaya
        // kedelapan sudutnya benar-benar terbentuk.
        $arah = [cos($rad), sin($rad)];
        $kanvas->garis(
            [$px - $arah[0] * 72, $atas - $arah[1] * 72],
            [$qx + $arah[0] * 72, $bawah + $arah[1] * 72],
            Kanvas::HITAM, 2.5,
        );

        // Tiap sudut diberi nomor pada garis bagi daerahnya, searah jarum jam
        // dari arah mendatar ke kanan.
        $sudutLabel = [
            4 => $besar / 2,
            3 => ($besar + 180) / 2,
            1 => 180 + $besar / 2,
            2 => 270 + $besar / 2,
        ];

        foreach ([[$px, $atas, [1, 2, 3, 4]], [$qx, $bawah, [5, 6, 7, 8]]] as [$x, $y, $nomor]) {
            foreach ([1 => $nomor[0], 2 => $nomor[1], 3 => $nomor[2], 4 => $nomor[3]] as $pola => $n) {
                $arahLabel = deg2rad($sudutLabel[$pola]);
                $titik = [$x + cos($arahLabel) * 36, $y + sin($arahLabel) * 36];

                $kanvas->lingkaran($titik, 13,
                    $n === $diketahui ? $isi : '#ffffff',
                    $n === $ditanya ? $garis : '#9aa1a9',
                    $n === $ditanya ? 3.5 : 1.5)
                    ->teksPx([$titik[0], $titik[1] + 5], (string) $n, [
                        'ukuran' => 14, 'tebal' => $n === $diketahui || $n === $ditanya,
                    ]);
            }
        }

        return $kanvas
            ->teksPx([240, 34], 'Sudut '.$diketahui.' = '.Angka::id($besar).'°, sudut '
                .$ditanya.' ditanyakan', ['ukuran' => 14, 'tebal' => true])
            ->teksPx([240, 290], 'kedua garis mendatar sejajar', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

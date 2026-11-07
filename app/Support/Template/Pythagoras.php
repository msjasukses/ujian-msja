<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template Pythagoras & trigonometri pada segitiga siku-siku.
 *
 * Segitiganya digambar dengan perbandingan sisi yang sebenarnya, jadi sudut
 * yang terlihat di layar sesuai dengan angka yang tertulis. Itu penting pada
 * soal trigonometri: siswa yang menebak dari gambar harus mendapat tebakan
 * yang konsisten dengan hitungannya, bukan gambar yang menyesatkan.
 */
class Pythagoras extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'sisi_miring' => 'Panjang sisi miring',
        'sisi_siku' => 'Panjang sisi siku-siku',
        'jenis' => 'Jenis segitiga dari ketiga sisinya',
        'trigonometri' => 'Nilai sin, cos, atau tan',
    ];

    /** @var array<string, string> */
    public const FUNGSI = [
        'sin' => 'sin (depan ÷ miring)',
        'cos' => 'cos (samping ÷ miring)',
        'tan' => 'tan (depan ÷ samping)',
    ];

    public static function kode(): string
    {
        return 'pythagoras';
    }

    public static function nama(): string
    {
        return 'Pythagoras & Trigonometri';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Segitiga siku-siku berlabel: mencari sisi miring atau sisi siku-siku, '
            .'menentukan jenis segitiga, serta nilai sin, cos, dan tan.';
    }

    public static function ikon(): string
    {
        return 'bi-triangle-half';
    }

    public static function kataKunci(): array
    {
        return ['pythagoras', 'sisi miring', 'segitiga siku-siku', 'trigonometri', 'sin', 'cos', 'tan'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'sisi_miring'),
            self::kolomAngka('sisi_a', 'Sisi tegak (depan sudut)', 3, 1, 1000, [
                'tampil' => ['tanya' => ['sisi_miring', 'jenis', 'trigonometri']],
            ]),
            self::kolomAngka('sisi_b', 'Sisi alas (samping sudut)', 4, 1, 1000, [
                'tampil' => ['tanya' => ['sisi_miring', 'jenis', 'trigonometri']],
            ]),
            self::kolomAngka('sisi_c', 'Sisi terpanjang', 5, 1, 2000, [
                'tampil' => ['tanya' => ['jenis']],
            ]),
            self::kolomAngka('miring', 'Panjang sisi miring', 13, 1, 2000, [
                'tampil' => ['tanya' => ['sisi_siku']],
            ]),
            self::kolomAngka('diketahui', 'Sisi siku-siku yang diketahui', 5, 1, 2000, [
                'tampil' => ['tanya' => ['sisi_siku']],
            ]),
            [
                'nama' => 'fungsi',
                'label' => 'Fungsi yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::FUNGSI,
                'awal' => 'sin',
                'tampil' => ['tanya' => ['trigonometri']],
            ],
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') === 'sisi_siku'
            && (float) ($isi['diketahui'] ?? 0) >= (float) ($isi['miring'] ?? 0)) {
            return ['diketahui' => 'Sisi siku-siku harus lebih pendek daripada sisi miringnya.'];
        }

        if (($isi['tanya'] ?? '') === 'jenis') {
            $a = (float) ($isi['sisi_a'] ?? 0);
            $b = (float) ($isi['sisi_b'] ?? 0);
            $c = (float) ($isi['sisi_c'] ?? 0);

            if ($c < max($a, $b)) {
                return ['sisi_c' => 'Sisi terpanjang tidak boleh lebih pendek daripada sisi lainnya.'];
            }

            if ($a + $b <= $c) {
                return ['sisi_c' => 'Ketiga panjang itu tidak membentuk segitiga.'];
            }
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        return match ($isi['tanya'] ?? 'sisi_miring') {
            'sisi_siku' => $this->sisiSiku((float) $isi['miring'], (float) $isi['diketahui'], $warnaIsi, $garis),
            'jenis' => $this->jenisSegitiga((float) $isi['sisi_a'], (float) $isi['sisi_b'], (float) $isi['sisi_c'], $warnaIsi, $garis),
            'trigonometri' => $this->trigonometri((float) $isi['sisi_a'], (float) $isi['sisi_b'], $isi['fungsi'] ?? 'sin', $warnaIsi, $garis),
            default => $this->sisiMiring((float) $isi['sisi_a'], (float) $isi['sisi_b'], $warnaIsi, $garis),
        };
    }

    protected function sisiMiring(float $a, float $b, string $isi, string $garis): array
    {
        $c = round(hypot($a, $b), 2);

        return [
            'judul' => 'Sisi miring '.Angka::dengan($c, 'cm'),
            'teks' => 'Perhatikan gambar segitiga siku-siku berikut. Berapakah panjang sisi miringnya?',
            'pernyataan' => 'Perhatikan gambar segitiga siku-siku berikut. Panjang sisi miringnya adalah %s.',
            'svg' => self::svg($a, $b, Angka::dengan($a, 'cm'), Angka::dengan($b, 'cm'), '?', $isi, $garis),
            'keterangan' => 'Segitiga siku-siku dengan sisi tegak '.Angka::dengan($a, 'cm')
                .' dan alas '.Angka::dengan($b, 'cm').'.',
            'pilihan' => self::pilihanPanjang($c, [
                // Menjumlahkan sisinya tanpa dikuadratkan adalah kekeliruan
                // paling sering pada Pythagoras.
                $a + $b, abs($a - $b), $a * $a + $b * $b, $c + 1,
            ]),
            'kata_kunci' => Angka::id($c),
            'pembahasan' => 'c² = a² + b² = '.Angka::id($a * $a).' + '.Angka::id($b * $b).' = '
                .Angka::id($a * $a + $b * $b).'<br>c = √'.Angka::id($a * $a + $b * $b).' = '
                .Angka::dengan($c, 'cm').'.',
        ];
    }

    protected function sisiSiku(float $c, float $a, string $isi, string $garis): array
    {
        $b = round(sqrt($c * $c - $a * $a), 2);

        return [
            'judul' => 'Sisi siku-siku '.Angka::dengan($b, 'cm'),
            'teks' => 'Perhatikan gambar segitiga siku-siku berikut. Berapakah panjang sisi yang belum diketahui?',
            'pernyataan' => 'Perhatikan gambar segitiga siku-siku berikut. Panjang sisi yang belum diketahui adalah %s.',
            'svg' => self::svg($a, $b, Angka::dengan($a, 'cm'), '?', Angka::dengan($c, 'cm'), $isi, $garis),
            'keterangan' => 'Segitiga siku-siku dengan sisi miring '.Angka::dengan($c, 'cm')
                .' dan satu sisi siku-siku '.Angka::dengan($a, 'cm').'.',
            'pilihan' => self::pilihanPanjang($b, [
                $c - $a, $c + $a, round(hypot($a, $c), 2), $b + 1,
            ]),
            'kata_kunci' => Angka::id($b),
            'pembahasan' => 'b² = c² − a² = '.Angka::id($c * $c).' − '.Angka::id($a * $a).' = '
                .Angka::id($c * $c - $a * $a).'<br>b = √'.Angka::id($c * $c - $a * $a).' = '
                .Angka::dengan($b, 'cm').'.',
        ];
    }

    protected function jenisSegitiga(float $a, float $b, float $c, string $isi, string $garis): array
    {
        $kiri = $c * $c;
        $kanan = $a * $a + $b * $b;

        $benar = match (true) {
            abs($kiri - $kanan) < 0.0001 => 'Segitiga siku-siku',
            $kiri > $kanan => 'Segitiga tumpul',
            default => 'Segitiga lancip',
        };

        $tanda = match (true) {
            abs($kiri - $kanan) < 0.0001 => '=',
            $kiri > $kanan => '>',
            default => '<',
        };

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar segitiga berikut. Segitiga itu termasuk jenis ...',
            'pernyataan' => 'Perhatikan gambar segitiga berikut. Segitiga itu termasuk %s.',
            'svg' => self::svgBebas($a, $b, $c, $isi, $garis),
            'keterangan' => 'Segitiga dengan sisi '.Angka::id($a).', '.Angka::id($b).', dan '.Angka::id($c).'.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$benar], array_values(array_diff(
                    ['Segitiga lancip', 'Segitiga siku-siku', 'Segitiga tumpul', 'Segitiga sama sisi'],
                    [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Bandingkan kuadrat sisi terpanjang dengan jumlah kuadrat dua sisi lainnya: '
                .Angka::id($kiri).' '.$tanda.' '.Angka::id($a * $a).' + '.Angka::id($b * $b)
                .', jadi segitiganya '.mb_strtolower(str_replace('Segitiga ', '', $benar)).'.',
        ];
    }

    protected function trigonometri(float $a, float $b, string $fungsi, string $isi, string $garis): array
    {
        $c = hypot($a, $b);

        $nilai = [
            'sin' => [$a, $c],
            'cos' => [$b, $c],
            'tan' => [$a, $b],
        ];

        [$atas, $bawah] = $nilai[$fungsi];
        $benar = self::pecahan($atas, $bawah);

        $kandidat = array_map(
            fn ($p) => self::pecahan($p[0], $p[1]),
            [[$atas, $bawah], [$bawah, $atas], ...array_values($nilai)],
        );

        return [
            'judul' => $fungsi.' α = '.$benar,
            'teks' => "Perhatikan gambar segitiga siku-siku berikut. Berapakah nilai {$fungsi} α?",
            'pernyataan' => "Perhatikan gambar segitiga siku-siku berikut. Nilai {$fungsi} α adalah %s.",
            'svg' => self::svg($a, $b, Angka::dengan($a, 'cm'), Angka::dengan($b, 'cm'),
                Angka::dengan(round($c, 2), 'cm'), $isi, $garis, 'α'),
            'keterangan' => 'Segitiga siku-siku dengan sisi depan '.Angka::id($a).', samping '
                .Angka::id($b).', dan miring '.Angka::id(round($c, 2)).'.',
            'pilihan' => array_map(
                fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                array_slice(array_values(array_unique($kandidat)), 0, self::JUMLAH_OPSI),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::FUNGSI[$fungsi].' → '.$fungsi.' α = '.Angka::id($atas).'/'
                .Angka::id(round($bawah, 2)).' = '.$benar.'.',
        ];
    }

    /** Pecahan yang sudah disederhanakan bila kedua angkanya bulat. */
    protected static function pecahan(float $atas, float $bawah): string
    {
        if (abs($atas - round($atas)) < 0.0001 && abs($bawah - round($bawah)) < 0.0001) {
            $pembagi = self::fpb((int) round($atas), (int) round($bawah));

            return (int) (round($atas) / $pembagi).'/'.(int) (round($bawah) / $pembagi);
        }

        return Angka::id(round($atas / $bawah, 2));
    }

    protected static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, abs($a));
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanPanjang(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::dengan($n, 'cm'), 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(float $a, float $b, string $labelA, string $labelB, string $labelC, string $isi, string $garis, string $sudut = ''): string
    {
        $kanvas = new Kanvas;
        // Titik sudut siku-siku di kanan bawah; sisi tegak a, alas b.
        $kanvas->pas([[0, 0], [$b, $a]], 70);

        $kiri = [0, $a];
        $kanan = [$b, $a];
        $atas = [$b, 0];

        $kanvas->poligon([$kiri, $kanan, $atas], $isi, $garis, 2.5)
            ->siku($kanan, [-1, 0], [0, -1], $garis, 12);

        $px = fn (array $p) => $kanvas->px($p);
        $tengahAlas = $px([$b / 2, $a]);
        $tengahTegak = $px([$b, $a / 2]);
        $tengahMiring = $px([$b / 2, $a / 2]);

        $kanvas->teksPx([$tengahAlas[0], $tengahAlas[1] + 24], $labelB, ['ukuran' => 15])
            ->teksPx([$tengahTegak[0] + 12, $tengahTegak[1] + 5], $labelA, ['anchor' => 'start', 'ukuran' => 15])
            ->teksPx([$tengahMiring[0] - 14, $tengahMiring[1] - 10], $labelC, ['anchor' => 'end', 'ukuran' => 15]);

        if ($sudut !== '') {
            $sudutPx = $px($kiri);
            $kanvas->teksPx([$sudutPx[0] + 26, $sudutPx[1] - 10], $sudut, ['ukuran' => 18, 'tebal' => true]);
        }

        return $kanvas->render();
    }

    /** Segitiga sembarang dari ketiga panjang sisinya. */
    public static function svgBebas(float $a, float $b, float $c, string $isi, string $garis): string
    {
        // Titik ketiga dicari dengan perpotongan dua lingkaran, sehingga
        // segitiga yang tergambar benar-benar bersisi seperti yang tertulis.
        $x = ($c * $c + $b * $b - $a * $a) / (2 * $c);
        $y = sqrt(max(0.0001, $b * $b - $x * $x));

        $kanvas = new Kanvas;
        $kanvas->pas([[0, 0], [$c, $y]], 70);

        $A = [0, $y];
        $B = [$c, $y];
        $C = [$x, 0];

        $kanvas->poligon([$A, $B, $C], $isi, $garis, 2.5);

        $tengah = fn (array $p, array $q) => $kanvas->px([($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2]);

        $bawah = $tengah($A, $B);
        $kiri = $tengah($A, $C);
        $kanan = $tengah($B, $C);

        $kanvas->teksPx([$bawah[0], $bawah[1] + 24], Angka::id($c), ['ukuran' => 15])
            ->teksPx([$kiri[0] - 12, $kiri[1]], Angka::id($b), ['anchor' => 'end', 'ukuran' => 15])
            ->teksPx([$kanan[0] + 12, $kanan[1]], Angka::id($a), ['anchor' => 'start', 'ukuran' => 15]);

        return $kanvas->render();
    }
}

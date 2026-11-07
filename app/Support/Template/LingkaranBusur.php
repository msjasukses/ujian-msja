<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template lingkaran: unsur, panjang busur, luas juring, dan sudut keliling.
 *
 * Satu gambar lingkaran melayani seluruh materinya. Pada soal unsur, bagian
 * yang ditanyakan ditebalkan; pada soal busur dan juring, juringnya diarsir
 * beserta sudut pusatnya; pada soal sudut keliling, kedua sudut digambar
 * menghadap tali busur yang sama — itulah yang membuat hubungan "setengah"
 * terlihat, bukan sekadar dihafal.
 */
class LingkaranBusur extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'unsur' => 'Nama unsur yang ditandai',
        'busur' => 'Panjang busur',
        'juring' => 'Luas juring',
        'sudut_keliling' => 'Sudut keliling',
    ];

    /** @var array<string, string> unsur lingkaran yang bisa ditandai */
    public const UNSUR = [
        'jari_jari' => 'Jari-jari',
        'diameter' => 'Diameter',
        'busur' => 'Busur',
        'tali_busur' => 'Tali busur',
        'juring' => 'Juring',
        'tembereng' => 'Tembereng',
    ];

    public static function kode(): string
    {
        return 'lingkaran-busur';
    }

    public static function nama(): string
    {
        return 'Lingkaran: Busur & Juring';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Unsur-unsur lingkaran, panjang busur dan luas juring dari sudut '
            .'pusat, serta hubungan sudut pusat dengan sudut keliling.';
    }

    public static function ikon(): string
    {
        return 'bi-circle-half';
    }

    public static function kataKunci(): array
    {
        return ['lingkaran', 'busur', 'juring', 'tembereng', 'tali busur', 'sudut pusat', 'sudut keliling'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'juring'),
            self::kolomAngka('jari_jari', 'Jari-jari', 14, 1, 1000, [
                'tampil' => ['tanya' => ['busur', 'juring']],
            ]),
            self::kolomAngka('sudut', 'Sudut pusat (derajat)', 90, 1, 359, [
                'langkah' => 1, 'tampil' => ['tanya' => ['busur', 'juring', 'sudut_keliling']],
            ]),
            [
                'nama' => 'unsur',
                'label' => 'Unsur yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => self::UNSUR,
                'awal' => 'juring',
                'tampil' => ['tanya' => ['unsur']],
            ],
            [
                'nama' => 'pi',
                'label' => 'Nilai π',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(array_keys(BangunDatar::PI), array_keys(BangunDatar::PI)),
                'awal' => '22/7',
                'lebar' => 6,
                'tampil' => ['tanya' => ['busur', 'juring']],
            ],
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        $tanya = $isi['tanya'] ?? 'juring';
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($tanya) {
            'unsur' => $this->unsur($isi['unsur'] ?? 'juring', $warnaIsi, $garis),
            'sudut_keliling' => $this->sudutKeliling((int) $isi['sudut'], $warnaIsi, $garis),
            default => $this->ukuran($tanya, $isi, $warnaIsi, $garis),
        };
    }

    protected function unsur(string $unsur, string $isi, string $garis): array
    {
        $benar = self::UNSUR[$unsur];

        return [
            'judul' => 'Unsur lingkaran: '.$benar,
            'teks' => 'Perhatikan gambar lingkaran berikut. Bagian yang ditandai tebal disebut ...',
            'pernyataan' => 'Perhatikan gambar lingkaran berikut. Bagian yang ditandai tebal disebut %s.',
            'svg' => self::svgUnsur($unsur, $isi, $garis),
            'keterangan' => 'Lingkaran dengan bagian '.mb_strtolower($benar).' ditandai tebal.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$benar], array_values(array_diff(array_values(self::UNSUR), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::arti($unsur),
        ];
    }

    protected function sudutKeliling(int $pusat, string $isi, string $garis): array
    {
        $keliling = $pusat / 2;

        return [
            'judul' => 'Sudut keliling '.Angka::id($keliling).'°',
            'teks' => "Perhatikan gambar berikut. Sudut pusatnya {$pusat}°. "
                .'Berapakah besar sudut keliling yang menghadap busur yang sama?',
            'pernyataan' => "Perhatikan gambar berikut. Dengan sudut pusat {$pusat}°, "
                .'besar sudut keliling yang menghadap busur yang sama adalah %s.',
            'svg' => self::svgSudutKeliling($pusat, $isi, $garis),
            'keterangan' => "Sudut pusat {$pusat}° dan sudut keliling menghadap busur yang sama.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n).'°', 'urut' => $n],
                array_merge([$keliling], self::saring($keliling, [
                    // Menyamakan dengan sudut pusat, atau justru
                    // melipatduakannya, adalah dua arah kekeliruan yang sama.
                    $pusat, $pusat * 2, 180 - $pusat, 360 - $pusat, $keliling + 15,
                ])),
            ),
            'kata_kunci' => Angka::id($keliling),
            'pembahasan' => 'Sudut keliling yang menghadap busur yang sama besarnya setengah sudut pusat, '
                ."jadi {$pusat}° ÷ 2 = ".Angka::id($keliling).'°.',
        ];
    }

    protected function ukuran(string $tanya, array $isi, string $warnaIsi, string $garis): array
    {
        $r = (float) $isi['jari_jari'];
        $sudut = (int) $isi['sudut'];
        [$pi, $piTeks] = BangunDatar::PI[$isi['pi'] ?? '22/7'] ?? BangunDatar::PI['22/7'];

        $keliling = 2 * $pi * $r;
        $luas = $pi * $r * $r;
        $bagian = $sudut / 360;

        $busur = round($bagian * $keliling, 2);
        $juring = round($bagian * $luas, 2);
        $benar = $tanya === 'busur' ? $busur : $juring;
        $satuan = $tanya === 'busur' ? 'cm' : 'cm²';

        $sebutan = $tanya === 'busur' ? 'panjang busur' : 'luas juring';
        $rumus = $tanya === 'busur'
            ? "({$sudut}/360) × 2 × {$piTeks} × ".Angka::id($r)
            : "({$sudut}/360) × {$piTeks} × ".Angka::id($r).' × '.Angka::id($r);

        return [
            'judul' => ucfirst($sebutan).' '.Angka::dengan($benar, $satuan),
            'teks' => 'Perhatikan gambar juring lingkaran berikut dengan jari-jari '.Angka::dengan($r, 'cm')
                ." dan sudut pusat {$sudut}°. Berapakah {$sebutan} itu?",
            'pernyataan' => 'Perhatikan gambar juring lingkaran berikut dengan jari-jari '.Angka::dengan($r, 'cm')
                ." dan sudut pusat {$sudut}°. {$sebutan} itu adalah %s.",
            'svg' => self::svgJuring($sudut, $r, $warnaIsi, $garis),
            'keterangan' => 'Lingkaran berjari-jari '.Angka::dengan($r, 'cm')." dengan juring bersudut pusat {$sudut}°.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, $satuan), 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Menghitung seluruh lingkaran, atau tertukar antara
                    // keliling dan luas, adalah dua kekeliruan terbesarnya.
                    $tanya === 'busur' ? $keliling : $luas,
                    $tanya === 'busur' ? $juring : $busur,
                    $benar * 2, $benar / 2, $benar + $r,
                ])),
            ),
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => ucfirst($sebutan).' = (sudut pusat ÷ 360) × '
                .($tanya === 'busur' ? 'keliling lingkaran' : 'luas lingkaran')
                .' = '.$rumus.' = '.Angka::dengan($benar, $satuan).'.',
        ];
    }

    protected static function arti(string $unsur): string
    {
        return match ($unsur) {
            'jari_jari' => 'Jari-jari adalah ruas garis dari titik pusat ke tepi lingkaran.',
            'diameter' => 'Diameter adalah tali busur yang melalui titik pusat; panjangnya dua kali jari-jari.',
            'busur' => 'Busur adalah bagian lengkung tepi lingkaran di antara dua titik.',
            'tali_busur' => 'Tali busur adalah ruas garis lurus yang menghubungkan dua titik pada lingkaran.',
            'tembereng' => 'Tembereng adalah daerah yang dibatasi tali busur dan busurnya.',
            default => 'Juring adalah daerah yang dibatasi dua jari-jari dan busur di antaranya.',
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    protected static function titik(array $pusat, float $r, float $derajat): array
    {
        return [
            $pusat[0] + $r * cos(deg2rad($derajat - 90)),
            $pusat[1] + $r * sin(deg2rad($derajat - 90)),
        ];
    }

    public static function svgJuring(int $sudut, float $r, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $jari = 110;

        $kanvas->lingkaran($pusat, $jari, '#ffffff', $garis, 2.5)
            ->juring($pusat, $jari, 0, $sudut, $isi, $garis, 2.5)
            ->lingkaran($pusat, 4, $garis, $garis, 1);

        $tengah = self::titik($pusat, $jari * 0.55, $sudut / 2);
        $kanvas->teksPx([$tengah[0], $tengah[1] + 5], $sudut.'°', ['ukuran' => 16, 'tebal' => true]);

        $jariTengah = self::titik($pusat, $jari * 0.5, 0);
        $kanvas->teksPx([$jariTengah[0] + 10, $jariTengah[1]], Angka::id($r).' cm', [
            'anchor' => 'start', 'ukuran' => 14,
        ]);

        return $kanvas->render();
    }

    public static function svgUnsur(string $unsur, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $jari = 110;

        $kanvas->lingkaran($pusat, $jari, '#ffffff', $garis, 2);

        $a = self::titik($pusat, $jari, 40);
        $b = self::titik($pusat, $jari, 150);
        $tebal = '#b23a5c';

        match ($unsur) {
            'jari_jari' => $kanvas->garis($pusat, self::titik($pusat, $jari, 60), $tebal, 5),
            'diameter' => $kanvas->garis(self::titik($pusat, $jari, 270), self::titik($pusat, $jari, 90), $tebal, 5),
            'busur' => $kanvas->tambah('<path d="M '.implode(' ', $a).' A '.$jari.' '.$jari
                .' 0 0 1 '.implode(' ', $b).'" fill="none" stroke="'.$tebal.'" stroke-width="6"/>'),
            'tali_busur' => $kanvas->garis($a, $b, $tebal, 5),
            'tembereng' => $kanvas->tambah('<path d="M '.implode(' ', $a).' A '.$jari.' '.$jari
                .' 0 0 1 '.implode(' ', $b).' Z" fill="'.$isi.'" stroke="'.$tebal.'" stroke-width="4"/>'),
            default => $kanvas->juring($pusat, $jari, 40, 150, $isi, $tebal, 4),
        };

        $kanvas->lingkaran($pusat, 4, $garis, $garis, 1)
            ->teksPx([$pusat[0] - 14, $pusat[1] + 5], 'O', ['ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgSudutKeliling(int $pusat, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $o = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2 + 10];
        $jari = 110;

        // Sudut pusat dibuka simetris terhadap garis tegak, dan titik sudut
        // kelilingnya diletakkan di busur besar yang berseberangan.
        $a = self::titik($o, $jari, 180 - $pusat / 2);
        $b = self::titik($o, $jari, 180 + $pusat / 2);
        $c = self::titik($o, $jari, 0);

        $kanvas->lingkaran($o, $jari, '#ffffff', $garis, 2)
            ->juring($o, $jari, 180 - $pusat / 2, 180 + $pusat / 2, $isi, $garis, 2)
            ->garis($a, $c, $garis, 2.5)
            ->garis($b, $c, $garis, 2.5)
            ->lingkaran($o, 4, $garis, $garis, 1);

        $kanvas->teksPx([$o[0], $o[1] + 40], $pusat.'°', ['ukuran' => 16, 'tebal' => true])
            ->teksPx([$c[0], $c[1] - 14], '?', ['ukuran' => 22, 'tebal' => true]);

        return $kanvas->render();
    }
}

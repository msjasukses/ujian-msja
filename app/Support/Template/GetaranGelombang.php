<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template getaran, gelombang & cahaya.
 *
 * Gelombangnya digambar dari jumlah gelombang yang dimasukkan guru, jadi
 * panjang gelombang yang menjadi kunci benar-benar terbaca dari gambar:
 * membagi panjang tali dengan banyak gelombang yang tampak. Pada soal
 * pemantulan, sudut datang dan sudut pantul digambar sama besar, sehingga
 * siswa bisa memeriksa jawabannya dengan melihat.
 */
class GetaranGelombang extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'panjang_gelombang' => 'Panjang gelombang',
        'cepat_rambat' => 'Cepat rambat gelombang',
        'periode' => 'Periode dan frekuensi getaran',
        'pemantulan' => 'Sudut pantul pada cermin datar',
    ];

    public static function kode(): string
    {
        return 'getaran-gelombang';
    }

    public static function nama(): string
    {
        return 'Getaran, Gelombang & Cahaya';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Panjang gelombang dan amplitudo dari gambar, cepat rambat, periode dan '
            .'frekuensi getaran bandul, serta pemantulan pada cermin datar.';
    }

    public static function ikon(): string
    {
        return 'bi-activity';
    }

    public static function kataKunci(): array
    {
        return ['getaran', 'gelombang', 'amplitudo', 'periode', 'frekuensi', 'cepat rambat',
            'bandul', 'pemantulan', 'cermin', 'cahaya'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'panjang_gelombang'),
            self::kolomAngka('panjang_tali', 'Panjang tali / medium (m)', 6, 0.1, 1000, [
                'tampil' => ['tanya' => ['panjang_gelombang', 'cepat_rambat']],
            ]),
            self::kolomAngka('jumlah_gelombang', 'Banyak gelombang yang tampak', 3, 1, 6, [
                'langkah' => 0.5, 'tampil' => ['tanya' => ['panjang_gelombang', 'cepat_rambat']],
            ]),
            self::kolomAngka('frekuensi', 'Frekuensi (Hz)', 4, 0.1, 10000, [
                'tampil' => ['tanya' => ['cepat_rambat']],
            ]),
            self::kolomAngka('getaran', 'Banyak getaran', 20, 1, 1000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['periode']],
            ]),
            self::kolomAngka('waktu', 'Waktu yang diperlukan (sekon)', 5, 0.1, 1000, [
                'tampil' => ['tanya' => ['periode']],
            ]),
            self::kolomAngka('sudut_datang', 'Sudut datang (derajat)', 35, 1, 89, [
                'langkah' => 1, 'tampil' => ['tanya' => ['pemantulan']],
            ]),
            self::kolomWarna('ungu'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        return match ($isi['tanya'] ?? 'panjang_gelombang') {
            'cepat_rambat' => $this->cepatRambat($isi, $warnaIsi, $garis),
            'periode' => $this->periode($isi, $warnaIsi, $garis),
            'pemantulan' => $this->pemantulan((int) $isi['sudut_datang'], $warnaIsi, $garis),
            default => $this->panjangGelombang($isi, $warnaIsi, $garis),
        };
    }

    protected function panjangGelombang(array $isi, string $warnaIsi, string $garis): array
    {
        $tali = (float) $isi['panjang_tali'];
        $n = (float) $isi['jumlah_gelombang'];
        $lambda = round($tali / $n, 2);

        return [
            'judul' => 'Panjang gelombang '.Angka::dengan($lambda, 'm'),
            'teks' => 'Perhatikan gambar gelombang berikut. Panjang tali itu '.Angka::dengan($tali, 'm')
                .'. Berapakah panjang gelombangnya?',
            'pernyataan' => 'Perhatikan gambar gelombang berikut. Pada tali sepanjang '
                .Angka::dengan($tali, 'm').', panjang gelombangnya adalah %s.',
            'svg' => self::svgGelombang($n, $tali, $warnaIsi, $garis),
            'keterangan' => 'Gelombang sebanyak '.Angka::id($n).' gelombang pada tali sepanjang '
                .Angka::dengan($tali, 'm').'.',
            'pilihan' => self::pilihan($lambda, 'm', [
                // Membagi dengan banyak bukit, bukan banyak gelombang penuh,
                // adalah kekeliruan paling sering di sini.
                round($tali / ($n * 2), 2), $tali, $n, $lambda * 2,
            ]),
            'kata_kunci' => Angka::id($lambda),
            'pembahasan' => 'Satu gelombang terdiri atas satu bukit dan satu lembah. Pada gambar ada '
                .Angka::id($n).' gelombang sepanjang '.Angka::dengan($tali, 'm').'.<br>λ = '
                .Angka::id($tali).' ÷ '.Angka::id($n).' = '.Angka::dengan($lambda, 'm').'.',
        ];
    }

    protected function cepatRambat(array $isi, string $warnaIsi, string $garis): array
    {
        $tali = (float) $isi['panjang_tali'];
        $n = (float) $isi['jumlah_gelombang'];
        $f = (float) $isi['frekuensi'];
        $lambda = round($tali / $n, 4);
        $v = round($lambda * $f, 2);

        return [
            'judul' => 'Cepat rambat '.Angka::dengan($v, 'm/s'),
            'teks' => 'Perhatikan gambar gelombang berikut pada tali sepanjang '.Angka::dengan($tali, 'm')
                .'. Bila frekuensinya '.Angka::dengan($f, 'Hz')
                .', berapakah cepat rambat gelombangnya?',
            'pernyataan' => 'Perhatikan gambar gelombang berikut pada tali sepanjang '
                .Angka::dengan($tali, 'm').' dengan frekuensi '.Angka::dengan($f, 'Hz')
                .'; cepat rambatnya %s.',
            'svg' => self::svgGelombang($n, $tali, $warnaIsi, $garis),
            'keterangan' => Angka::id($n).' gelombang pada tali '.Angka::dengan($tali, 'm')
                .', frekuensi '.Angka::dengan($f, 'Hz').'.',
            'pilihan' => self::pilihan($v, 'm/s', [
                round($lambda / $f, 2), $tali * $f, $lambda, $v * 2,
            ]),
            'kata_kunci' => Angka::id($v),
            'pembahasan' => 'λ = '.Angka::id($tali).' ÷ '.Angka::id($n).' = '.Angka::dengan($lambda, 'm')
                .'<br>v = λ × f = '.Angka::id($lambda).' × '.Angka::id($f).' = '.Angka::dengan($v, 'm/s').'.',
        ];
    }

    protected function periode(array $isi, string $warnaIsi, string $garis): array
    {
        $n = (float) $isi['getaran'];
        $t = (float) $isi['waktu'];
        $periode = round($t / $n, 4);
        $frekuensi = round($n / $t, 2);

        return [
            'judul' => 'Periode '.Angka::dengan($periode, 'sekon'),
            'teks' => 'Perhatikan gambar bandul berikut. Bandul itu melakukan '.Angka::id($n)
                .' getaran dalam waktu '.Angka::dengan($t, 'sekon').'. Berapakah periode getarannya?',
            'pernyataan' => 'Perhatikan gambar bandul berikut yang melakukan '.Angka::id($n)
                .' getaran dalam '.Angka::dengan($t, 'sekon').'; periode getarannya %s.',
            'svg' => self::svgBandul($warnaIsi, $garis),
            'keterangan' => 'Bandul berayun; '.Angka::id($n).' getaran dalam '.Angka::dengan($t, 'sekon').'.',
            'pilihan' => self::pilihan($periode, 'sekon', [
                // Periode dan frekuensi saling terbalik, dan keduanya memang
                // paling sering tertukar.
                $frekuensi, $n * $t, $periode * 2, $periode / 2,
            ]),
            'kata_kunci' => Angka::id($periode),
            'pembahasan' => 'T = waktu ÷ banyak getaran = '.Angka::id($t).' ÷ '.Angka::id($n).' = '
                .Angka::dengan($periode, 'sekon').'.<br>Frekuensinya kebalikan periode, yaitu '
                .Angka::dengan($frekuensi, 'Hz').'.',
        ];
    }

    protected function pemantulan(int $datang, string $warnaIsi, string $garis): array
    {
        return [
            'judul' => 'Sudut pantul '.$datang.'°',
            'teks' => 'Perhatikan gambar pemantulan cahaya pada cermin datar berikut. '
                ."Bila sudut datangnya {$datang}°, berapakah besar sudut pantulnya?",
            'pernyataan' => 'Perhatikan gambar pemantulan cahaya pada cermin datar berikut. '
                ."Bila sudut datangnya {$datang}°, sudut pantulnya adalah %s.",
            'svg' => self::svgCermin($datang, $warnaIsi, $garis),
            'keterangan' => "Sinar datang dan sinar pantul pada cermin datar dengan sudut datang {$datang}°.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => ((int) $n).'°', 'urut' => $n],
                array_merge([$datang], self::saring($datang, [
                    // Mengukur dari permukaan cermin, bukan dari garis normal,
                    // menghasilkan sudut pelurusnya.
                    90 - $datang, 180 - $datang, 2 * $datang, $datang + 10,
                ], 0)),
            ),
            'kata_kunci' => $datang.'°',
            'pembahasan' => 'Hukum pemantulan: sudut datang sama dengan sudut pantul, keduanya diukur '
                ."terhadap garis normal. Jadi sudut pantulnya juga {$datang}°.",
        ];
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihan(float $benar, string $satuan, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::dengan($n, $satuan), 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgGelombang(float $jumlah, float $panjang, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 40;
        $kanan = Kanvas::LEBAR - 40;
        $sumbu = 150;
        $amplitudo = 62;

        $kanvas->garis([$kiri, $sumbu], [$kanan, $sumbu], '#aab1b8', 1.5, true);

        // Gelombangnya dicuplik rapat supaya lengkungnya mulus.
        $titik = [];

        for ($i = 0; $i <= 240; $i++) {
            $x = $kiri + ($kanan - $kiri) * $i / 240;
            $titik[] = [$x, $sumbu - $amplitudo * sin(2 * M_PI * $jumlah * $i / 240)];
        }

        $kanvas->garisPatah($titik, $garis, 3.5);

        // Garis ukur panjang tali di bawah gelombang.
        $ukur = $sumbu + 100;

        $kanvas->garis([$kiri, $ukur], [$kanan, $ukur], Kanvas::HITAM, 1.5)
            ->garis([$kiri, $ukur - 8], [$kiri, $ukur + 8], Kanvas::HITAM, 1.5)
            ->garis([$kanan, $ukur - 8], [$kanan, $ukur + 8], Kanvas::HITAM, 1.5)
            ->teksPx([($kiri + $kanan) / 2, $ukur + 24], Angka::dengan($panjang, 'm'), [
                'ukuran' => 15, 'tebal' => true,
            ]);

        return $kanvas->render();
    }

    public static function svgBandul(string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $gantung = [Kanvas::LEBAR / 2, 46];
        $panjang = 160;
        $sudut = deg2rad(26);

        $kanvas->garis([$gantung[0] - 90, $gantung[1]], [$gantung[0] + 90, $gantung[1]], Kanvas::HITAM, 5);

        // Dua kedudukan ujung ayunan digambar samar, kedudukan tengah tegas:
        // itulah yang membuat "satu getaran" terbaca sebagai bolak-balik.
        foreach ([-1, 1] as $arah) {
            $ujung = [
                $gantung[0] + $arah * $panjang * sin($sudut),
                $gantung[1] + $panjang * cos($sudut),
            ];

            $kanvas->garis($gantung, $ujung, '#aab1b8', 1.5, true)
                ->lingkaran($ujung, 20, '#ffffff', '#aab1b8', 2);
        }

        $tengah = [$gantung[0], $gantung[1] + $panjang];

        $kanvas->garis($gantung, $tengah, Kanvas::HITAM, 2.5)
            ->lingkaran($tengah, 24, $isi, $garis, 3)
            ->tambah('<path d="M '.($gantung[0] - 70).' '.($gantung[1] + 190).' Q '.$gantung[0].' '
                .($gantung[1] + 220).' '.($gantung[0] + 70).' '.($gantung[1] + 190)
                .'" fill="none" stroke="'.$garis.'" stroke-width="2" stroke-dasharray="5 4"/>')
            ->teksPx([$gantung[0], $gantung[1] + 238], 'satu getaran = bolak-balik penuh', [
                'ukuran' => 12, 'warna' => '#4b5563',
            ]);

        return $kanvas->render();
    }

    public static function svgCermin(int $datang, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $titik = [Kanvas::LEBAR / 2, 220];
        $panjang = 190;

        $kanvas->tambah('<rect x="60" y="'.$titik[1].'" width="'.(Kanvas::LEBAR - 120)
            .'" height="16" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

        for ($x = 66; $x < Kanvas::LEBAR - 60; $x += 14) {
            $kanvas->garis([$x, $titik[1] + 16], [$x - 10, $titik[1] + 30], $garis, 1.5);
        }

        $kanvas->garis($titik, [$titik[0], $titik[1] - $panjang - 20], Kanvas::HITAM, 1.5, true)
            ->teksPx([$titik[0] + 8, $titik[1] - $panjang - 26], 'garis normal', [
                'anchor' => 'start', 'ukuran' => 12, 'warna' => '#4b5563',
            ]);

        $rad = deg2rad($datang);

        foreach ([[-1, 'sinar datang', 'start'], [1, 'sinar pantul', 'end']] as [$arah, $nama, $anchor]) {
            $ujung = [
                $titik[0] + $arah * $panjang * sin($rad),
                $titik[1] - $panjang * cos($rad),
            ];

            $kanvas->garis($ujung, $titik, Kanvas::HITAM, 3);
        }

        // Kedua sudut diberi busur dan angka; yang dicari ditandai tanya.
        $kanvas->tambah('<path d="M '.($titik[0] - 56 * sin($rad)).' '.($titik[1] - 56 * cos($rad))
            .' A 56 56 0 0 1 '.$titik[0].' '.($titik[1] - 56).'" fill="none" stroke="#b23a5c" stroke-width="2.5"/>')
            ->tambah('<path d="M '.$titik[0].' '.($titik[1] - 56).' A 56 56 0 0 1 '
                .($titik[0] + 56 * sin($rad)).' '.($titik[1] - 56 * cos($rad))
                .'" fill="none" stroke="#b23a5c" stroke-width="2.5"/>')
            ->teksPx([$titik[0] - 40, $titik[1] - 70], $datang.'°', ['anchor' => 'end', 'ukuran' => 15, 'tebal' => true])
            ->teksPx([$titik[0] + 40, $titik[1] - 70], '?', ['anchor' => 'start', 'ukuran' => 20, 'tebal' => true]);

        return $kanvas->render();
    }
}

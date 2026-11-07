<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template alat ukur & besaran: membaca alat dan mengenali besarannya.
 *
 * Skala alatnya digambar dari angka yang dimasukkan guru, bukan dari gambar
 * jadi. Pada jangka sorong itu penting: garis nonius yang berimpit dengan
 * garis skala utama memang dihitung dari nilainya, sehingga gambar dan kunci
 * jawaban tidak mungkin berselisih — dan siswa benar-benar harus mencari
 * garis yang berimpit, bukan menebak.
 */
class AlatUkurBesaran extends Template
{
    /**
     * Alat beserta besaran, satuan skala, dan satuan SI-nya.
     *
     * @var array<string, array{nama: string, besaran: string, satuan: string, si: string, teliti: float}>
     */
    public const ALAT = [
        'jangka_sorong' => [
            'nama' => 'Jangka sorong', 'besaran' => 'Panjang', 'satuan' => 'cm',
            'si' => 'meter (m)', 'teliti' => 0.01,
        ],
        'mikrometer' => [
            'nama' => 'Mikrometer sekrup', 'besaran' => 'Panjang', 'satuan' => 'mm',
            'si' => 'meter (m)', 'teliti' => 0.01,
        ],
        'neraca_ohaus' => [
            'nama' => 'Neraca Ohaus', 'besaran' => 'Massa', 'satuan' => 'gram',
            'si' => 'kilogram (kg)', 'teliti' => 0.1,
        ],
        'stopwatch' => [
            'nama' => 'Stopwatch', 'besaran' => 'Waktu', 'satuan' => 'sekon',
            'si' => 'sekon (s)', 'teliti' => 1,
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Hasil pengukuran',
        'besaran' => 'Besaran yang diukur',
        'satuan_si' => 'Satuan SI besaran itu',
    ];

    /** Besaran pokok beserta satuan SI-nya, dipakai sebagai pilihan jawaban. */
    public const BESARAN = [
        'Panjang' => 'meter (m)',
        'Massa' => 'kilogram (kg)',
        'Waktu' => 'sekon (s)',
        'Suhu' => 'kelvin (K)',
        'Kuat arus' => 'ampere (A)',
    ];

    public static function kode(): string
    {
        return 'alat-ukur-besaran';
    }

    public static function nama(): string
    {
        return 'Alat Ukur & Besaran';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Membaca jangka sorong, mikrometer sekrup, neraca Ohaus, dan stopwatch; '
            .'besaran pokok, satuan SI, dan alat ukurnya.';
    }

    public static function ikon(): string
    {
        return 'bi-crosshair';
    }

    public static function kataKunci(): array
    {
        return ['alat ukur', 'jangka sorong', 'mikrometer', 'neraca ohaus', 'stopwatch',
            'besaran pokok', 'satuan si', 'pengukuran'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'alat',
                'label' => 'Alat ukur',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => $a['nama'], self::ALAT),
                'awal' => 'jangka_sorong',
            ],
            self::kolomAngka('sorong', 'Hasil ukur jangka sorong (cm)', 3.47, 0.01, 9.99, [
                'langkah' => 0.01, 'tampil' => ['alat' => ['jangka_sorong']],
            ]),
            self::kolomAngka('mikro', 'Hasil ukur mikrometer (mm)', 4.63, 0.01, 24.99, [
                'langkah' => 0.01, 'tampil' => ['alat' => ['mikrometer']],
            ]),
            self::kolomAngka('massa', 'Hasil ukur neraca (gram)', 273.5, 0.1, 610, [
                'langkah' => 0.1, 'tampil' => ['alat' => ['neraca_ohaus']],
            ]),
            self::kolomAngka('waktu', 'Hasil ukur stopwatch (sekon)', 38, 1, 59, [
                'langkah' => 1, 'tampil' => ['alat' => ['stopwatch']],
            ]),
            self::kolomTanya(self::TANYA, 'hasil'),
            self::kolomWarna('abu'),
        ];
    }

    protected function susun(array $isi): array
    {
        $kode = $isi['alat'] ?? 'jangka_sorong';
        $alat = self::ALAT[$kode];
        [$warnaIsi, $garis] = self::warna($isi, 'abu');

        $nilai = (float) match ($kode) {
            'mikrometer' => $isi['mikro'],
            'neraca_ohaus' => $isi['massa'],
            'stopwatch' => $isi['waktu'],
            default => $isi['sorong'],
        };

        $svg = self::svg($kode, $nilai, $warnaIsi, $garis);
        $cerita = 'Perhatikan gambar '.mb_strtolower($alat['nama']).' berikut.';
        $keterangan = $alat['nama'].' menunjukkan '.Angka::dengan($nilai, $alat['satuan']).'.';
        $tanya = $isi['tanya'] ?? 'hasil';

        if ($tanya === 'besaran') {
            return [
                'judul' => $alat['nama'].' mengukur '.mb_strtolower($alat['besaran']),
                'teks' => $cerita.' Besaran apakah yang diukur alat itu?',
                'pernyataan' => $cerita.' Besaran yang diukur alat itu adalah %s.',
                'svg' => $svg,
                'keterangan' => $alat['nama'].' beserta skalanya.',
                'pilihan' => array_map(
                    fn ($n) => ['teks' => $n, 'urut' => $n],
                    array_merge([$alat['besaran']], array_values(array_diff(
                        array_keys(self::BESARAN), [$alat['besaran']],
                    ))),
                ),
                'kata_kunci' => $alat['besaran'],
                'pembahasan' => $alat['nama'].' dipakai mengukur '.mb_strtolower($alat['besaran'])
                    .', dan satuan SI-nya '.$alat['si'].'.',
            ];
        }

        if ($tanya === 'satuan_si') {
            return [
                'judul' => 'Satuan SI '.$alat['si'],
                'teks' => $cerita.' Apakah satuan SI dari besaran yang diukur alat itu?',
                'pernyataan' => $cerita.' Satuan SI dari besaran yang diukur alat itu adalah %s.',
                'svg' => $svg,
                'keterangan' => $alat['nama'].' beserta skalanya.',
                'pilihan' => array_map(
                    fn ($n) => ['teks' => $n, 'urut' => $n],
                    array_merge([$alat['si']], array_values(array_diff(
                        array_values(self::BESARAN), [$alat['si']],
                    ))),
                ),
                'kata_kunci' => $alat['si'],
                'pembahasan' => $alat['nama'].' mengukur '.mb_strtolower($alat['besaran'])
                    .'. Satuan SI besaran itu '.$alat['si'].', sedangkan skala pada alatnya '
                    .'memakai '.$alat['satuan'].'.',
            ];
        }

        return [
            'judul' => 'Hasil ukur '.Angka::dengan($nilai, $alat['satuan']),
            'teks' => $cerita.' Berapakah hasil pengukuran yang ditunjukkannya?',
            'pernyataan' => $cerita.' Hasil pengukuran yang ditunjukkannya adalah %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, $alat['satuan']), 'urut' => $n],
                array_merge([$nilai], self::saring($nilai, self::pengecoh($kode, $nilai), 2)),
            ),
            'kata_kunci' => Angka::id($nilai),
            'pembahasan' => self::carabaca($kode, $nilai),
        ];
    }

    /**
     * Pengecoh membaca alat: skala utamanya saja tanpa skala nonius, atau
     * nonius yang dihitung sebagai satuan skala utama — dua kekeliruan yang
     * benar-benar terjadi saat siswa pertama kali memakai alatnya.
     *
     * @return array<int, float>
     */
    protected static function pengecoh(string $kode, float $nilai): array
    {
        return match ($kode) {
            'jangka_sorong' => (function () use ($nilai) {
                $utama = floor($nilai * 10) / 10;
                $nonius = (int) round(($nilai - $utama) * 100);

                return [$utama, $utama + $nonius / 10, $nilai + 0.1, $nilai - 0.1, $nilai + 0.05];
            })(),
            'mikrometer' => (function () use ($nilai) {
                $utama = floor($nilai * 2) / 2;
                $putar = round(($nilai - $utama) * 100);

                return [$utama, $utama + $putar / 10, $nilai + 0.5, $nilai - 0.5, $nilai + 0.05];
            })(),
            'neraca_ohaus' => [
                $nilai - floor($nilai / 100) * 100, floor($nilai / 10) * 10,
                $nilai + 10, $nilai - 10, $nilai + 100,
            ],
            default => [$nilai + 5, $nilai - 5, $nilai + 10, $nilai + 1],
        };
    }

    protected static function carabaca(string $kode, float $nilai): string
    {
        return match ($kode) {
            'jangka_sorong' => (function () use ($nilai) {
                $utama = floor($nilai * 10) / 10;
                $nonius = (int) round(($nilai - $utama) * 100);

                return 'Skala utama terbaca '.Angka::dengan($utama, 'cm').' (garis terakhir sebelum angka '
                    .'nol nonius). Garis nonius yang berimpit garis skala utama adalah garis ke-'.$nonius
                    .', bernilai '.Angka::dengan($nonius * 0.01, 'cm').'.<br>Hasilnya '
                    .Angka::id($utama).' + '.Angka::id($nonius * 0.01).' = '.Angka::dengan($nilai, 'cm').'.';
            })(),
            'mikrometer' => (function () use ($nilai) {
                $utama = floor($nilai * 2) / 2;
                $putar = (int) round(($nilai - $utama) * 100);

                return 'Skala utama terbaca '.Angka::dengan($utama, 'mm').', skala putar menunjuk angka '
                    .$putar.' yang bernilai '.Angka::dengan($putar * 0.01, 'mm').'.<br>Hasilnya '
                    .Angka::id($utama).' + '.Angka::id($putar * 0.01).' = '.Angka::dengan($nilai, 'mm').'.';
            })(),
            'neraca_ohaus' => (function () use ($nilai) {
                $ratus = floor($nilai / 100) * 100;
                $puluh = floor(($nilai - $ratus) / 10) * 10;
                $satuan = round($nilai - $ratus - $puluh, 1);

                return 'Jumlahkan kedudukan ketiga antingnya: '.Angka::id($ratus).' + '.Angka::id($puluh)
                    .' + '.Angka::id($satuan).' = '.Angka::dengan($nilai, 'gram').'.';
            })(),
            default => 'Jarum stopwatch berhenti pada angka '.Angka::id($nilai)
                .', jadi waktunya '.Angka::dengan($nilai, 'sekon').'.',
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $kode, float $nilai, string $isi, string $garis): string
    {
        return match ($kode) {
            'mikrometer' => self::mikrometer($nilai, $isi, $garis),
            'neraca_ohaus' => self::ohaus($nilai, $isi, $garis),
            'stopwatch' => self::stopwatch($nilai, $isi, $garis),
            default => self::jangkaSorong($nilai, $isi, $garis),
        };
    }

    /**
     * Jangka sorong: skala utama bermilimeter di atas, skala nonius
     * bergeser di bawahnya. Satu bagian nonius panjangnya 0,9 mm, sehingga
     * garis ke-n berimpit dengan garis skala utama tepat saat hasil ukurnya
     * memuat n per seratus sentimeter.
     */
    protected static function jangkaSorong(float $nilai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 40;
        $skala = (Kanvas::LEBAR - 80) / 6;   // 6 cm memenuhi lebar kanvas
        $utamaY = 140;
        // Nonius menempel tepat di bawah garis skala utama; kalau diberi
        // jarak, garis yang berimpit tidak bisa lagi dilihat berimpit.
        $noniusY = $utamaY;

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.($utamaY - 48).'" width="'.(6 * $skala)
            .'" height="48" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

        for ($mm = 0; $mm <= 60; $mm++) {
            $x = $kiri + $mm / 10 * $skala;
            $panjang = $mm % 10 === 0 ? 22 : ($mm % 5 === 0 ? 15 : 9);

            $kanvas->garis([$x, $utamaY], [$x, $utamaY - $panjang], Kanvas::HITAM, $mm % 10 === 0 ? 1.8 : 1);

            if ($mm % 10 === 0) {
                $kanvas->teksPx([$x, $utamaY - 28], (string) ($mm / 10), ['ukuran' => 13, 'tebal' => true]);
            }
        }

        $nol = $kiri + $nilai * $skala;

        $kanvas->tambah('<rect x="'.round($nol, 1).'" y="'.$noniusY.'" width="'.round(1.0 * $skala, 1)
            .'" height="44" fill="#ffffff" stroke="'.$garis.'" stroke-width="2"/>');

        // Sepuluh bagian nonius sepanjang 9 mm seluruhnya.
        for ($i = 0; $i <= 10; $i++) {
            $x = $nol + $i * 0.09 * $skala;

            $kanvas->garis([$x, $noniusY], [$x, $noniusY + 18], Kanvas::HITAM, 1.2);

            if ($i % 5 === 0) {
                $kanvas->teksPx([$x, $noniusY + 34], (string) $i, ['ukuran' => 11]);
            }
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 232], 'skala utama dalam cm · nonius 10 bagian', [
            'ukuran' => 12, 'warna' => '#4b5563',
        ]);

        return $kanvas->render();
    }

    /**
     * Mikrometer sekrup: skala utama setengah milimeter pada selubung, dan
     * skala putar 50 bagian pada bidal yang masing-masing bernilai 0,01 mm.
     */
    protected static function mikrometer(float $nilai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $utama = floor($nilai * 2) / 2;
        $putar = (int) round(($nilai - $utama) * 100);

        $kiri = 40;
        $skala = 26;                   // satu milimeter pada selubung
        $sumbu = 150;
        $panjangSelubung = 9 * $skala;

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.($sumbu - 20).'" width="'.$panjangSelubung
            .'" height="40" rx="6" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
            ->garis([$kiri, $sumbu], [$kiri + $panjangSelubung, $sumbu], Kanvas::HITAM, 1.5);

        for ($i = 0; $i <= 18; $i++) {
            $x = $kiri + $i * $skala / 2;
            $atas = $i % 2 === 0;

            $kanvas->garis([$x, $sumbu], [$x, $sumbu + ($atas ? -14 : 14)], Kanvas::HITAM, 1.4);

            if ($atas && $i % 4 === 0) {
                $kanvas->teksPx([$x, $sumbu - 20], (string) ($i / 2), ['ukuran' => 11]);
            }
        }

        // Bidal digambar tepat di belakang nilai skala utamanya.
        $bidalX = $kiri + ($utama + 0.5) * $skala;

        $kanvas->tambah('<rect x="'.round($bidalX, 1).'" y="'.($sumbu - 42).'" width="150" height="84" rx="8" fill="#ffffff" stroke="'
            .$garis.'" stroke-width="2.5"/>');

        for ($i = -2; $i <= 2; $i++) {
            $tanda = ($putar + $i + 50) % 50;
            $y = $sumbu + $i * 18;

            $kanvas->garis([$bidalX, $y], [$bidalX + ($i === 0 ? 34 : 22), $y], Kanvas::HITAM, $i === 0 ? 2 : 1.2)
                ->teksPx([$bidalX + 44, $y + 5], str_pad((string) $tanda, 2, '0', STR_PAD_LEFT), [
                    'anchor' => 'start', 'ukuran' => 13, 'tebal' => $i === 0,
                ]);
        }

        $kanvas->garis([$kiri, $sumbu], [$bidalX, $sumbu], '#b23a5c', 2)
            ->teksPx([Kanvas::LEBAR / 2, 246], 'skala utama 0,5 mm · skala putar 50 bagian (0,01 mm)', [
                'ukuran' => 12, 'warna' => '#4b5563',
            ]);

        return $kanvas->render();
    }

    /** Neraca Ohaus tiga lengan: ratusan, puluhan, dan satuan. */
    protected static function ohaus(float $nilai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $ratus = floor($nilai / 100) * 100;
        $puluh = floor(($nilai - $ratus) / 10) * 10;
        $satuan = round($nilai - $ratus - $puluh, 1);

        $lengan = [
            ['maks' => 500, 'langkah' => 100, 'nilai' => $ratus, 'y' => 80],
            ['maks' => 100, 'langkah' => 10, 'nilai' => $puluh, 'y' => 150],
            ['maks' => 10, 'langkah' => 1, 'nilai' => $satuan, 'y' => 220],
        ];

        $kiri = 60;
        $panjang = Kanvas::LEBAR - 120;

        foreach ($lengan as $l) {
            $kanvas->tambah('<rect x="'.$kiri.'" y="'.($l['y'] - 9).'" width="'.$panjang
                .'" height="18" rx="4" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

            for ($v = 0; $v <= $l['maks']; $v += $l['langkah']) {
                $x = $kiri + $v / $l['maks'] * $panjang;

                $kanvas->garis([$x, $l['y'] - 9], [$x, $l['y'] - 20], Kanvas::HITAM, 1.3)
                    ->teksPx([$x, $l['y'] - 24], (string) $v, ['ukuran' => 10]);
            }

            $anting = $kiri + $l['nilai'] / $l['maks'] * $panjang;

            $kanvas->poligon([
                [$anting, $l['y'] - 9],
                [$anting - 9, $l['y'] + 16],
                [$anting + 9, $l['y'] + 16],
            ], '#b23a5c', Kanvas::HITAM, 1.5);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 272], 'neraca Ohaus tiga lengan (gram)', [
            'ukuran' => 12, 'warna' => '#4b5563',
        ]);

        return $kanvas->render();
    }

    protected static function stopwatch(float $detik, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2 + 6];
        $r = 108;

        $kanvas->lingkaran([$pusat[0], $pusat[1] - $r - 16], 12, $isi, Kanvas::HITAM, 2)
            ->lingkaran($pusat, $r, '#ffffff', Kanvas::HITAM, 3);

        for ($d = 0; $d < 60; $d++) {
            $sudut = deg2rad($d * 6 - 90);
            $panjang = $d % 5 === 0 ? 14 : 7;

            $kanvas->garis(
                [$pusat[0] + ($r - $panjang) * cos($sudut), $pusat[1] + ($r - $panjang) * sin($sudut)],
                [$pusat[0] + $r * cos($sudut), $pusat[1] + $r * sin($sudut)],
                Kanvas::HITAM,
                $d % 5 === 0 ? 2 : 1,
            );

            if ($d % 10 === 0) {
                $kanvas->teksPx([
                    $pusat[0] + ($r - 32) * cos($sudut),
                    $pusat[1] + ($r - 32) * sin($sudut) + 4,
                ], (string) $d, ['ukuran' => 12]);
            }
        }

        $sudut = deg2rad($detik * 6 - 90);

        $kanvas->garis($pusat, [
            $pusat[0] + ($r - 24) * cos($sudut),
            $pusat[1] + ($r - 24) * sin($sudut),
        ], '#b23a5c', 4)
            ->lingkaran($pusat, 6, Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([$pusat[0], $pusat[1] + 54], 'sekon', ['ukuran' => 12, 'warna' => '#4b5563']);

        return $kanvas->render();
    }
}

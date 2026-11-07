<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template mengukur panjang, berat, dan isi.
 *
 * Benda pada penggaris sengaja bisa diletakkan tidak mulai dari angka nol —
 * itulah justru inti soal mengukur: panjangnya adalah selisih kedua ujung,
 * bukan angka yang terbaca di ujung kanan. Pengecohnya memang menyediakan
 * angka ujung kanan itu.
 */
class AlatUkurDasar extends Template
{
    /** @var array<string, array{nama: string, satuan: string, besaran: string}> */
    public const ALAT = [
        'penggaris' => ['nama' => 'Penggaris', 'satuan' => 'cm', 'besaran' => 'panjang'],
        'timbangan' => ['nama' => 'Timbangan jarum', 'satuan' => 'gram', 'besaran' => 'berat'],
        'gelas_ukur' => ['nama' => 'Gelas ukur', 'satuan' => 'ml', 'besaran' => 'volume'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Hasil pengukuran',
        'konversi' => 'Hasil dalam satuan lain',
    ];

    /**
     * Satuan tujuan beserta pengalinya terhadap satuan alat.
     *
     * @var array<string, array<string, float>>
     */
    public const KONVERSI = [
        'cm' => ['mm' => 10, 'm' => 0.01],
        'gram' => ['kg' => 0.001, 'ons' => 0.01],
        'ml' => ['liter' => 0.001, 'cm³' => 1],
    ];

    public static function kode(): string
    {
        return 'alat-ukur-dasar';
    }

    public static function nama(): string
    {
        return 'Mengukur Panjang, Berat & Isi';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Membaca penggaris (termasuk benda yang tidak mulai dari 0), timbangan '
            .'jarum, dan gelas ukur, serta mengubah satuannya.';
    }

    public static function ikon(): string
    {
        return 'bi-rulers';
    }

    public static function kataKunci(): array
    {
        return ['mengukur', 'penggaris', 'timbangan', 'gelas ukur', 'panjang', 'berat', 'volume', 'satuan'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'alat',
                'label' => 'Alat ukur',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => $a['nama'], self::ALAT),
                'awal' => 'penggaris',
            ],
            self::kolomAngka('mulai', 'Benda mulai dari angka', 2, 0, 18, [
                'langkah' => 1, 'tampil' => ['alat' => ['penggaris']],
            ]),
            self::kolomAngka('panjang', 'Panjang benda (cm)', 7, 1, 20, [
                'langkah' => 1, 'tampil' => ['alat' => ['penggaris']],
            ]),
            self::kolomAngka('berat', 'Berat terbaca (gram)', 750, 0, 1000, [
                'langkah' => 10, 'tampil' => ['alat' => ['timbangan']],
            ]),
            self::kolomAngka('volume', 'Volume terbaca (ml)', 350, 0, 500, [
                'langkah' => 10, 'tampil' => ['alat' => ['gelas_ukur']],
            ]),
            [
                'nama' => 'satuan_tujuan',
                'label' => 'Diubah ke satuan',
                'jenis' => 'pilihan',
                'pilihan' => ['mm' => 'mm', 'm' => 'm', 'kg' => 'kg', 'ons' => 'ons', 'liter' => 'liter', 'cm³' => 'cm³'],
                'awal' => 'mm',
                'lebar' => 6,
                'tampil' => ['tanya' => ['konversi']],
            ],
            self::kolomTanya(self::TANYA, 'hasil', 6),
            self::kolomWarna('jingga'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $alat = $isi['alat'] ?? 'penggaris';

        if ($alat === 'penggaris' && (int) ($isi['mulai'] ?? 0) + (int) ($isi['panjang'] ?? 0) > 20) {
            return ['panjang' => 'Bendanya melewati ujung penggaris 20 cm.'];
        }

        if (($isi['tanya'] ?? '') === 'konversi') {
            $satuan = self::ALAT[$alat]['satuan'];

            if (! isset(self::KONVERSI[$satuan][$isi['satuan_tujuan'] ?? ''])) {
                return ['satuan_tujuan' => 'Satuan itu tidak sesuai dengan besaran yang diukur alat ini. '
                    .'Pilih '.implode(' atau ', array_keys(self::KONVERSI[$satuan])).'.'];
            }
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $alat = $isi['alat'] ?? 'penggaris';
        $spek = self::ALAT[$alat];
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        [$nilai, $svg, $keterangan, $pengecoh] = match ($alat) {
            'timbangan' => [
                (float) $isi['berat'],
                self::svgTimbangan((float) $isi['berat'], $warnaIsi, $garis),
                'Timbangan jarum berskala 0 sampai 1.000 gram.',
                [(float) $isi['berat'] + 50, (float) $isi['berat'] - 50, 1000 - (float) $isi['berat']],
            ],
            'gelas_ukur' => [
                (float) $isi['volume'],
                self::svgGelas((float) $isi['volume'], $warnaIsi, $garis),
                'Gelas ukur berskala 0 sampai 500 ml.',
                [(float) $isi['volume'] + 50, (float) $isi['volume'] - 50, 500 - (float) $isi['volume']],
            ],
            default => (function () use ($isi, $warnaIsi, $garis) {
                $mulai = (int) $isi['mulai'];
                $panjang = (int) $isi['panjang'];

                return [
                    (float) $panjang,
                    self::svgPenggaris($mulai, $panjang, $warnaIsi, $garis),
                    "Benda di atas penggaris, dari angka {$mulai} sampai ".($mulai + $panjang).'.',
                    // Membaca angka ujung kanan, bukan selisihnya, adalah
                    // kekeliruan pokok pada soal penggaris.
                    [$mulai + $panjang, $mulai, $panjang + 1, $panjang - 1],
                ];
            })(),
        };

        $satuan = $spek['satuan'];
        $cerita = 'Perhatikan gambar '.mb_strtolower($spek['nama']).' berikut.';

        if (($isi['tanya'] ?? 'hasil') === 'konversi') {
            $tujuan = $isi['satuan_tujuan'];
            $pengali = self::KONVERSI[$satuan][$tujuan];
            $hasil = round($nilai * $pengali, 4);

            return [
                'judul' => Angka::dengan($nilai, $satuan).' = '.Angka::dengan($hasil, $tujuan),
                'teks' => $cerita." Berapakah hasil pengukuran itu bila dinyatakan dalam {$tujuan}?",
                'pernyataan' => $cerita.' Hasil pengukuran itu sama dengan %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::dengan($n, $tujuan), 'urut' => $n],
                    array_merge([$hasil], self::saring($hasil, [
                        // Mengubah satuan ke arah yang terbalik adalah
                        // kekeliruan konversi yang paling sering terjadi.
                        // Geseran sepuluh dan seribu tetap disediakan karena
                        // pada satuan yang nilainya sama (ml dan cm³) kedua
                        // kandidat pertama berimpit dengan kuncinya.
                        round($nilai / $pengali, 4), $nilai,
                        $hasil * 10, $hasil / 10, $hasil * 1000, $hasil / 1000,
                    ], 4)),
                ),
                'kata_kunci' => Angka::id($hasil),
                'pembahasan' => Angka::dengan($nilai, $satuan).' × '.Angka::id($pengali).' = '
                    .Angka::dengan($hasil, $tujuan).'.',
            ];
        }

        return [
            'judul' => 'Hasil ukur '.Angka::dengan($nilai, $satuan),
            'teks' => $cerita.' Berapakah hasil pengukuran yang ditunjukkannya?',
            'pernyataan' => $cerita.' Hasil pengukuran yang ditunjukkannya adalah %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, $satuan), 'urut' => $n],
                array_merge([$nilai], self::saring($nilai, $pengecoh)),
            ),
            'kata_kunci' => Angka::id($nilai),
            'pembahasan' => $alat === 'penggaris'
                ? 'Panjang benda adalah selisih kedua ujungnya: '.($isi['mulai'] + $isi['panjang']).' − '
                    .$isi['mulai'].' = '.Angka::dengan($nilai, $satuan).'.'
                : 'Jarum/permukaan berhenti pada garis '.Angka::id($nilai).', jadi hasil pengukurannya '
                    .Angka::dengan($nilai, $satuan).'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgPenggaris(int $mulai, int $panjang, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 30;
        $kanan = Kanvas::LEBAR - 30;
        $atas = 150;
        $tinggi = 70;
        $sel = ($kanan - $kiri) / 20;

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.$atas.'" width="'.($kanan - $kiri).'" height="'.$tinggi
            .'" rx="4" fill="#fdf3dc" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>');

        for ($i = 0; $i <= 20; $i++) {
            $x = $kiri + $i * $sel;
            $kanvas->garis([$x, $atas], [$x, $atas + ($i % 5 === 0 ? 26 : 16)], Kanvas::HITAM, $i % 5 === 0 ? 2 : 1);

            if ($i % 5 === 0 || $sel > 20) {
                $kanvas->teksPx([$x, $atas + 46], (string) $i, ['ukuran' => 12]);
            }
        }

        // Bendanya diletakkan tepat di atas penggaris supaya kedua ujungnya
        // bisa dibaca terhadap skala.
        $kanvas->tambah('<rect x="'.round($kiri + $mulai * $sel, 1).'" y="'.($atas - 48)
            .'" width="'.round($panjang * $sel, 1).'" height="36" rx="6" fill="'.$isi
            .'" stroke="'.$garis.'" stroke-width="2.5"/>');

        return $kanvas->render();
    }

    public static function svgTimbangan(float $berat, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2 + 10];
        $r = 110;

        $kanvas->lingkaran($pusat, $r, '#ffffff', Kanvas::HITAM, 3);

        // Skala melingkar penuh: 0 di atas, berputar searah jarum jam.
        for ($g = 0; $g <= 1000; $g += 50) {
            $sudut = deg2rad($g / 1000 * 360 - 90);
            $panjang = $g % 100 === 0 ? 16 : 9;

            $kanvas->garis(
                [$pusat[0] + ($r - $panjang) * cos($sudut), $pusat[1] + ($r - $panjang) * sin($sudut)],
                [$pusat[0] + $r * cos($sudut), $pusat[1] + $r * sin($sudut)],
                Kanvas::HITAM,
                $g % 100 === 0 ? 2 : 1,
            );

            if ($g % 200 === 0 && $g < 1000) {
                $kanvas->teksPx([
                    $pusat[0] + ($r - 34) * cos($sudut),
                    $pusat[1] + ($r - 34) * sin($sudut) + 4,
                ], (string) $g, ['ukuran' => 12]);
            }
        }

        $sudut = deg2rad($berat / 1000 * 360 - 90);
        $kanvas->garis($pusat, [
            $pusat[0] + ($r - 26) * cos($sudut),
            $pusat[1] + ($r - 26) * sin($sudut),
        ], $garis, 5)
            ->lingkaran($pusat, 7, $garis, $garis, 1)
            ->teksPx([$pusat[0], $pusat[1] + 62], 'gram', ['ukuran' => 13, 'warna' => '#4b5563']);

        return $kanvas->render();
    }

    public static function svgGelas(float $volume, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = Kanvas::LEBAR / 2 - 70;
        $lebar = 140;
        $atas = 30;
        $bawah = 260;

        $y = fn (float $v) => $bawah - $v / 500 * ($bawah - $atas);

        $kanvas->tambah('<rect x="'.round($kiri + 4, 1).'" y="'.round($y($volume), 1).'" width="'.($lebar - 8)
            .'" height="'.round($bawah - $y($volume) - 4, 1).'" fill="'.$isi.'"/>');

        $kanvas->tambah('<path d="M '.$kiri.' '.$atas.' L '.$kiri.' '.$bawah.' L '.($kiri + $lebar).' '.$bawah
            .' L '.($kiri + $lebar).' '.$atas.'" fill="none" stroke="'.Kanvas::HITAM.'" stroke-width="3"/>');

        for ($v = 0; $v <= 500; $v += 50) {
            $garisY = $y($v);
            $panjang = $v % 100 === 0 ? 30 : 16;

            $kanvas->garis([$kiri, $garisY], [$kiri + $panjang, $garisY], Kanvas::HITAM, $v % 100 === 0 ? 2 : 1);

            if ($v % 100 === 0) {
                $kanvas->teksPx([$kiri + $lebar + 10, $garisY + 5], (string) $v, [
                    'anchor' => 'start', 'ukuran' => 12,
                ]);
            }
        }

        $kanvas->garis([$kiri + 4, $y($volume)], [$kiri + $lebar - 4, $y($volume)], $garis, 3)
            ->teksPx([$kiri + $lebar + 10, $atas + 10], 'ml', ['anchor' => 'start', 'ukuran' => 13]);

        return $kanvas->render();
    }
}

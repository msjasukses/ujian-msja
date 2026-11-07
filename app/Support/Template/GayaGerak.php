<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template gaya & gerak.
 *
 * Resultan gaya digambar sebagai panah pada balok dengan panjang sebanding
 * besarnya, jadi arah resultannya bisa diduga dari gambar sebelum dihitung.
 * Grafik gerak digambar dari angka yang sama yang dipakai menghitung
 * jawabannya, sehingga luas di bawah grafik v–t benar-benar sama dengan
 * jarak yang menjadi kuncinya.
 */
class GayaGerak extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'resultan' => 'Resultan gaya pada balok',
        'percepatan' => 'Percepatan (hukum Newton II)',
        'grafik_v' => 'Jarak dari grafik v–t',
        'kelajuan' => 'Kelajuan rata-rata dari lintasan',
    ];

    public static function kode(): string
    {
        return 'gaya-gerak';
    }

    public static function nama(): string
    {
        return 'Gaya & Gerak';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Resultan gaya pada balok, hukum Newton II, jarak dari grafik v–t, '
            .'dan kelajuan rata-rata dari gambar lintasan.';
    }

    public static function ikon(): string
    {
        return 'bi-person-walking';
    }

    public static function kataKunci(): array
    {
        return ['gaya', 'resultan', 'newton', 'percepatan', 'kelajuan', 'kecepatan',
            'grafik', 'gerak', 'glbb', 'jarak'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'resultan'),
            self::kolomAngka('gaya_kiri', 'Gaya ke kiri (N)', 15, 0, 1000, [
                'tampil' => ['tanya' => ['resultan']],
            ]),
            self::kolomAngka('gaya_kanan', 'Gaya ke kanan (N)', 40, 0, 1000, [
                'tampil' => ['tanya' => ['resultan']],
            ]),
            self::kolomAngka('gaya_kanan_2', 'Gaya kedua ke kanan (N)', 0, 0, 1000, [
                'tampil' => ['tanya' => ['resultan']],
            ]),
            self::kolomAngka('massa', 'Massa benda (kg)', 5, 0.1, 10000, [
                'tampil' => ['tanya' => ['percepatan']],
            ]),
            self::kolomAngka('gaya', 'Gaya yang bekerja (N)', 40, 0.1, 100000, [
                'tampil' => ['tanya' => ['percepatan']],
            ]),
            self::kolomAngka('kecepatan', 'Kecepatan tetap (m/s)', 12, 0.1, 500, [
                'tampil' => ['tanya' => ['grafik_v']],
            ]),
            self::kolomAngka('waktu', 'Lama bergerak (sekon)', 8, 0.1, 500, [
                'tampil' => ['tanya' => ['grafik_v', 'kelajuan']],
            ]),
            self::kolomAngka('jarak', 'Panjang lintasan (m)', 120, 0.1, 100000, [
                'tampil' => ['tanya' => ['kelajuan']],
            ]),
            self::kolomWarna('merah'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') !== 'resultan') {
            return [];
        }

        $kiri = (float) ($isi['gaya_kiri'] ?? 0);
        $kanan = (float) ($isi['gaya_kanan'] ?? 0) + (float) ($isi['gaya_kanan_2'] ?? 0);

        if ($kiri + $kanan <= 0) {
            return ['gaya_kanan' => 'Isi minimal satu gaya yang bekerja pada balok.'];
        }

        if (abs($kanan - $kiri) < 0.0001) {
            return ['gaya_kanan' => 'Kedua arah sama besar; resultannya nol dan tidak ada yang dihitung.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        return match ($isi['tanya'] ?? 'resultan') {
            'percepatan' => $this->percepatan($isi, $warnaIsi, $garis),
            'grafik_v' => $this->grafik($isi, $warnaIsi, $garis),
            'kelajuan' => $this->kelajuan($isi, $warnaIsi, $garis),
            default => $this->resultan($isi, $warnaIsi, $garis),
        };
    }

    protected function resultan(array $isi, string $warnaIsi, string $garis): array
    {
        $kiri = (float) $isi['gaya_kiri'];
        $kanan = (float) $isi['gaya_kanan'] + (float) $isi['gaya_kanan_2'];
        $r = round(abs($kanan - $kiri), 2);
        $arah = $kanan > $kiri ? 'kanan' : 'kiri';

        return [
            'judul' => 'Resultan '.Angka::dengan($r, 'N').' ke '.$arah,
            'teks' => 'Perhatikan gambar gaya-gaya yang bekerja pada balok berikut. '
                .'Berapakah besar resultan gayanya?',
            'pernyataan' => 'Perhatikan gambar gaya-gaya yang bekerja pada balok berikut. '
                .'Besar resultan gayanya adalah %s.',
            'svg' => self::svgBalok((float) $isi['gaya_kiri'], (float) $isi['gaya_kanan'],
                (float) $isi['gaya_kanan_2'], $warnaIsi, $garis),
            'keterangan' => 'Balok dengan gaya '.Angka::id($kiri).' N ke kiri dan '
                .Angka::id($kanan).' N ke kanan.',
            'pilihan' => self::pilihan($r, 'N', [
                // Menjumlahkan semua gaya tanpa memperhatikan arahnya adalah
                // kekeliruan pokok pada resultan gaya.
                $kiri + $kanan, $kiri, $kanan, $r * 2,
            ]),
            'kata_kunci' => Angka::id($r),
            'pembahasan' => 'Gaya yang berlawanan arah dikurangkan: '.Angka::id($kanan).' N − '
                .Angka::id($kiri).' N = '.Angka::dengan($r, 'N').', arahnya ke '.$arah.'.',
        ];
    }

    protected function percepatan(array $isi, string $warnaIsi, string $garis): array
    {
        $m = (float) $isi['massa'];
        $f = (float) $isi['gaya'];
        $a = round($f / $m, 2);

        return [
            'judul' => 'Percepatan '.Angka::dengan($a, 'm/s²'),
            'teks' => 'Perhatikan gambar berikut. Benda bermassa '.Angka::dengan($m, 'kg')
                .' didorong dengan gaya '.Angka::dengan($f, 'N')
                .'. Berapakah percepatan yang dialaminya?',
            'pernyataan' => 'Perhatikan gambar berikut. Benda bermassa '.Angka::dengan($m, 'kg')
                .' yang didorong gaya '.Angka::dengan($f, 'N').' mengalami percepatan %s.',
            'svg' => self::svgDorong($m, $f, $warnaIsi, $garis),
            'keterangan' => 'Benda bermassa '.Angka::dengan($m, 'kg').' didorong gaya '
                .Angka::dengan($f, 'N').'.',
            'pilihan' => self::pilihan($a, 'm/s²', [
                // Membalik rumus menjadi m ÷ F, atau justru mengalikannya.
                round($m / $f, 2), $f * $m, $a * 2, $a / 2,
            ]),
            'kata_kunci' => Angka::id($a),
            'pembahasan' => 'Hukum Newton II: F = m × a, jadi a = F ÷ m = '.Angka::id($f).' ÷ '
                .Angka::id($m).' = '.Angka::dengan($a, 'm/s²').'.',
        ];
    }

    protected function grafik(array $isi, string $warnaIsi, string $garis): array
    {
        $v = (float) $isi['kecepatan'];
        $t = (float) $isi['waktu'];
        $s = round($v * $t, 2);

        return [
            'judul' => 'Jarak '.Angka::dengan($s, 'm'),
            'teks' => 'Perhatikan grafik v–t berikut. Berapakah jarak yang ditempuh benda '
                .'selama '.Angka::dengan($t, 'sekon').' itu?',
            'pernyataan' => 'Perhatikan grafik v–t berikut. Jarak yang ditempuh benda selama '
                .Angka::dengan($t, 'sekon').' adalah %s.',
            'svg' => self::svgGrafik($v, $t, $warnaIsi, $garis),
            'keterangan' => 'Grafik kecepatan terhadap waktu: kecepatan tetap '
                .Angka::dengan($v, 'm/s').' selama '.Angka::dengan($t, 'sekon').'.',
            'pilihan' => self::pilihan($s, 'm', [
                round($v / $t, 2), $v + $t, $s / 2, $s * 2,
            ]),
            'kata_kunci' => Angka::id($s),
            'pembahasan' => 'Jarak sama dengan luas daerah di bawah grafik v–t: s = v × t = '
                .Angka::id($v).' × '.Angka::id($t).' = '.Angka::dengan($s, 'm').'.',
        ];
    }

    protected function kelajuan(array $isi, string $warnaIsi, string $garis): array
    {
        $s = (float) $isi['jarak'];
        $t = (float) $isi['waktu'];
        $v = round($s / $t, 2);

        return [
            'judul' => 'Kelajuan '.Angka::dengan($v, 'm/s'),
            'teks' => 'Perhatikan gambar lintasan berikut. Benda menempuh seluruh lintasan '
                .Angka::dengan($s, 'm').' dalam waktu '.Angka::dengan($t, 'sekon')
                .'. Berapakah kelajuan rata-ratanya?',
            'pernyataan' => 'Perhatikan gambar lintasan berikut. Kelajuan rata-rata benda yang menempuh '
                .Angka::dengan($s, 'm').' dalam '.Angka::dengan($t, 'sekon').' adalah %s.',
            'svg' => self::svgLintasan($s, $t, $warnaIsi, $garis),
            'keterangan' => 'Lintasan sepanjang '.Angka::dengan($s, 'm').' ditempuh dalam '
                .Angka::dengan($t, 'sekon').'.',
            'pilihan' => self::pilihan($v, 'm/s', [
                round($t / $s, 2), $s * $t, $v * 2, $v / 2,
            ]),
            'kata_kunci' => Angka::id($v),
            'pembahasan' => 'v = s ÷ t = '.Angka::id($s).' ÷ '.Angka::id($t).' = '
                .Angka::dengan($v, 'm/s').'.',
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

    public static function svgBalok(float $kiri, float $kanan, float $kanan2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 220;

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="190" y="'.($tanah - 80).'" width="100" height="80" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>');

        $terbesar = max(1, $kiri, $kanan, $kanan2);
        $panah = fn (float $besar, float $dariX, int $arah, float $y) => [
            'panjang' => 30 + 110 * ($besar / $terbesar),
            'x' => $dariX,
            'arah' => $arah,
            'y' => $y,
        ];

        $daftar = array_filter([
            $kiri > 0 ? $panah($kiri, 190, -1, $tanah - 56) : null,
            $kanan > 0 ? $panah($kanan, 290, 1, $tanah - 56) : null,
            $kanan2 > 0 ? $panah($kanan2, 290, 1, $tanah - 24) : null,
        ]);

        $nilai = array_values(array_filter([$kiri, $kanan, $kanan2], fn ($n) => $n > 0));

        foreach (array_values($daftar) as $i => $p) {
            $ujung = $p['x'] + $p['arah'] * $p['panjang'];

            $kanvas->garis([$p['x'], $p['y']], [$ujung, $p['y']], Kanvas::HITAM, 3)
                ->poligon([
                    [$ujung + $p['arah'] * 10, $p['y']],
                    [$ujung - $p['arah'] * 6, $p['y'] - 8],
                    [$ujung - $p['arah'] * 6, $p['y'] + 8],
                ], Kanvas::HITAM, Kanvas::HITAM, 1)
                ->teksPx([$ujung + $p['arah'] * 18, $p['y'] - 12], Angka::dengan($nilai[$i], 'N'), [
                    'anchor' => $p['arah'] > 0 ? 'start' : 'end', 'ukuran' => 14, 'tebal' => true,
                ]);
        }

        return $kanvas->render();
    }

    public static function svgDorong(float $m, float $f, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 220;

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="200" y="'.($tanah - 86).'" width="110" height="86" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>')
            ->teksPx([255, $tanah - 38], Angka::dengan($m, 'kg'), ['ukuran' => 17, 'tebal' => true]);

        $kanvas->garis([70, $tanah - 44], [192, $tanah - 44], Kanvas::HITAM, 3)
            ->poligon([[200, $tanah - 44], [186, $tanah - 52], [186, $tanah - 36]],
                Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([70, $tanah - 56], 'F = '.Angka::dengan($f, 'N'), [
                'anchor' => 'start', 'ukuran' => 15, 'tebal' => true,
            ]);

        return $kanvas->render();
    }

    public static function svgGrafik(float $v, float $t, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 60;
        $bawah = 240;
        $kanan = Kanvas::LEBAR - 40;
        $atas = 50;

        $px = fn (float $x, float $y) => [
            $kiri + $x / ($t * 1.25) * ($kanan - $kiri),
            $bawah - $y / ($v * 1.3) * ($bawah - $atas),
        ];

        // Daerah di bawah grafik diarsir: itulah jarak yang ditanyakan.
        $kanvas->poligon([$px(0, 0), $px($t, 0), $px($t, $v), $px(0, $v)], $isi, $garis, 2);

        $kanvas->garis([$kiri, $bawah], [$kanan, $bawah], Kanvas::HITAM, 2.5)
            ->garis([$kiri, $bawah], [$kiri, $atas], Kanvas::HITAM, 2.5)
            ->teksPx([$kanan, $bawah + 24], 't (sekon)', ['anchor' => 'end', 'ukuran' => 13])
            ->teksPx([$kiri - 10, $atas], 'v (m/s)', ['anchor' => 'end', 'ukuran' => 13]);

        $kanvas->garis($px(0, $v), $px($t, $v), $garis, 3)
            ->teksPx([$px(0, $v)[0] - 10, $px(0, $v)[1] + 5], Angka::id($v), [
                'anchor' => 'end', 'ukuran' => 13, 'tebal' => true,
            ])
            ->teksPx([$px($t, 0)[0], $bawah + 20], Angka::id($t), ['ukuran' => 13, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgLintasan(float $s, float $t, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $y = 170;

        $kanvas->tambah('<rect x="50" y="'.($y - 22).'" width="'.(Kanvas::LEBAR - 100)
            .'" height="44" rx="8" fill="#e3e5e8" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>')
            ->garis([50, $y], [Kanvas::LEBAR - 50, $y], '#ffffff', 3, true);

        $kanvas->lingkaran([86, $y], 16, $isi, $garis, 2.5)
            ->teksPx([86, $y - 32], 'mulai', ['ukuran' => 12, 'warna' => '#4b5563'])
            ->teksPx([Kanvas::LEBAR - 86, $y - 32], 'selesai', ['ukuran' => 12, 'warna' => '#4b5563']);

        $kanvas->garis([50, $y + 48], [Kanvas::LEBAR - 50, $y + 48], Kanvas::HITAM, 1.5)
            ->garis([50, $y + 40], [50, $y + 56], Kanvas::HITAM, 1.5)
            ->garis([Kanvas::LEBAR - 50, $y + 40], [Kanvas::LEBAR - 50, $y + 56], Kanvas::HITAM, 1.5)
            ->teksPx([Kanvas::LEBAR / 2, $y + 74], Angka::dengan($s, 'm').' ditempuh '
                .Angka::dengan($t, 'sekon'), ['ukuran' => 14, 'tebal' => true]);

        return $kanvas->render();
    }
}

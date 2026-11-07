<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template suhu: membaca termometer dan mengubah satuan suhu.
 *
 * Termometer digambar berskala Celsius karena itu yang dipakai sehari-hari
 * di Indonesia; satuan lain muncul sebagai yang ditanyakan. Pengecohnya pun
 * disusun dari kekeliruan konversi yang khas — lupa menambah 32 pada
 * Fahrenheit, atau membalik pecahan 4/5 menjadi 5/4 pada Reamur.
 */
class Termometer extends Template
{
    /**
     * Satuan suhu beserta cara menghitungnya dari Celsius: nama, lambang,
     * dan rumusnya.
     *
     * @var array<string, array{nama: string, lambang: string, rumus: string}>
     */
    public const SKALA = [
        'reamur' => ['nama' => 'Reamur', 'lambang' => '°R', 'rumus' => '⁴⁄₅ × C'],
        'fahrenheit' => ['nama' => 'Fahrenheit', 'lambang' => '°F', 'rumus' => '(⁹⁄₅ × C) + 32'],
        'kelvin' => ['nama' => 'Kelvin', 'lambang' => 'K', 'rumus' => 'C + 273'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'baca' => 'Membaca termometer',
        'konversi' => 'Mengubah ke satuan lain',
        'kalor' => 'Kalor untuk menaikkan suhu',
        'pemuaian' => 'Pertambahan panjang karena memuai',
    ];

    /** Batas bawah dan atas skala yang digambar. */
    protected const MIN = -10;

    protected const MAKS = 110;

    public static function kode(): string
    {
        return 'suhu-termometer';
    }

    public static function nama(): string
    {
        return 'Suhu & Kalor';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Membaca termometer, mengubah skala suhu ke Reamur, Fahrenheit dan Kelvin, '
            .'menghitung kalor, serta pemuaian panjang.';
    }

    public static function ikon(): string
    {
        return 'bi-thermometer-half';
    }

    public static function kataKunci(): array
    {
        return ['suhu', 'termometer', 'celsius', 'reamur', 'fahrenheit', 'kelvin', 'konversi', 'kalor'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'konversi',
            ],
            [
                'nama' => 'suhu',
                'label' => 'Suhu yang ditunjukkan (°C)',
                'jenis' => 'angka',
                'awal' => 60,
                'min' => self::MIN,
                'max' => self::MAKS,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'skala',
                'label' => 'Diubah ke satuan',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($s) => $s['nama'], self::SKALA),
                'awal' => 'reamur',
                'lebar' => 6,
                'tampil' => ['tanya' => ['konversi']],
            ],
            self::kolomAngka('massa', 'Massa zat (kg)', 2, 0.01, 1000, [
                'tampil' => ['tanya' => ['kalor']],
            ]),
            self::kolomAngka('kalor_jenis', 'Kalor jenis (J/kg°C)', 4200, 1, 10000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['kalor']],
            ]),
            self::kolomAngka('kenaikan', 'Kenaikan suhu (°C)', 30, 0.1, 500, [
                'tampil' => ['tanya' => ['kalor', 'pemuaian']],
            ]),
            self::kolomAngka('panjang_awal', 'Panjang mula-mula (m)', 2, 0.01, 1000, [
                'tampil' => ['tanya' => ['pemuaian']],
            ]),
            self::kolomAngka('koefisien', 'Koefisien muai panjang (×10⁻⁶ /°C)', 12, 0.1, 500, [
                'tampil' => ['tanya' => ['pemuaian']],
            ]),
            self::kolomWarna('merah'),
        ];
    }

    protected function susun(array $isi): array
    {
        $c = (float) $isi['suhu'];
        $svg = self::svg($c, $isi['warna'] ?? 'merah');
        $keterangan = 'Termometer berskala Celsius dari '.self::MIN.' sampai '.self::MAKS.' derajat.';

        if (($isi['tanya'] ?? '') === 'kalor') {
            return $this->kalor($isi, $svg);
        }

        if (($isi['tanya'] ?? '') === 'pemuaian') {
            return $this->pemuaian($isi);
        }

        if (($isi['tanya'] ?? 'baca') === 'baca') {
            $pilihan = array_map(
                fn ($n) => ['teks' => Angka::dengan($n, '°C'), 'urut' => $n],
                array_merge([$c], array_filter(
                    [$c + 10, $c - 10, $c + 5, $c - 5],
                    fn ($n) => $n >= self::MIN && $n <= self::MAKS,
                )),
            );

            return [
                'judul' => 'Termometer '.Angka::dengan($c, '°C'),
                'teks' => 'Perhatikan gambar termometer berikut. Berapakah suhu yang ditunjukkannya?',
                'pernyataan' => 'Perhatikan gambar termometer berikut. Suhu yang ditunjukkannya adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => $pilihan,
                'kata_kunci' => Angka::id($c),
                'pembahasan' => 'Permukaan cairan berhenti pada garis '.Angka::id($c)
                    .', jadi suhunya '.Angka::dengan($c, '°C').'.',
            ];
        }

        $skala = self::SKALA[$isi['skala'] ?? 'reamur'] ?? self::SKALA['reamur'];
        $benar = self::ubah($c, $isi['skala'] ?? 'reamur');
        $lambang = $skala['lambang'];

        return [
            'judul' => 'Suhu '.Angka::dengan($c, '°C').' ke '.$skala['nama'],
            'teks' => 'Perhatikan gambar termometer berikut. Berapakah suhu tersebut bila dinyatakan '
                .'dalam satuan '.$skala['nama'].'?',
            'pernyataan' => 'Perhatikan gambar termometer berikut. Suhu tersebut sama dengan %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, $lambang), 'urut' => $n],
                array_merge([$benar], $this->pengecoh($c, $isi['skala'] ?? 'reamur', $benar)),
            ),
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => $skala['nama'].' = '.$skala['rumus'].' = '
                .$this->langkah($c, $isi['skala'] ?? 'reamur').' = '.Angka::dengan($benar, $lambang).'.',
        ];
    }

    /**
     * Kalor yang diperlukan menaikkan suhu zat, Q = m × c × ΔT. Termometer
     * tetap dipakai sebagai gambarnya karena kenaikan suhu itulah yang
     * diukur, dan siswa perlu melihat bahwa yang dipakai rumus adalah
     * selisih suhu, bukan suhu akhirnya.
     */
    protected function kalor(array $isi, string $svg): array
    {
        $m = (float) $isi['massa'];
        $c = (float) $isi['kalor_jenis'];
        $dt = (float) $isi['kenaikan'];
        $q = round($m * $c * $dt, 2);

        return [
            'judul' => 'Kalor '.Angka::dengan($q, 'joule'),
            'teks' => 'Perhatikan gambar termometer berikut. Zat bermassa '.Angka::dengan($m, 'kg')
                .' dengan kalor jenis '.Angka::dengan($c, 'J/kg°C').' dipanaskan sehingga suhunya naik '
                .Angka::dengan($dt, '°C').'. Berapakah kalor yang diperlukan?',
            'pernyataan' => 'Perhatikan gambar termometer berikut. Kalor untuk menaikkan suhu zat '
                .Angka::dengan($m, 'kg').' berkalor jenis '.Angka::dengan($c, 'J/kg°C').' sebesar '
                .Angka::dengan($dt, '°C').' adalah %s.',
            'svg' => $svg,
            'keterangan' => 'Termometer sebagai alat ukur kenaikan suhu '.Angka::dengan($dt, '°C').'.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, 'joule'), 'urut' => $n],
                array_merge([$q], self::saring($q, [
                    // Salah satu faktor terlewat — massa atau kenaikan suhu —
                    // adalah kekeliruan pokok pada rumus kalor.
                    $c * $dt, $m * $c, $m * $dt, $q * 2,
                ])),
            ),
            'kata_kunci' => Angka::id($q),
            'pembahasan' => 'Q = m × c × ΔT = '.Angka::id($m).' × '.Angka::id($c).' × '.Angka::id($dt)
                .' = '.Angka::dengan($q, 'joule').'.',
        ];
    }

    /** Pemuaian panjang, ΔL = L₀ × α × ΔT, dengan α dalam satuan 10⁻⁶ /°C. */
    protected function pemuaian(array $isi): array
    {
        $l0 = (float) $isi['panjang_awal'];
        $alfa = (float) $isi['koefisien'];
        $dt = (float) $isi['kenaikan'];
        $dl = round($l0 * $alfa * 1e-6 * $dt, 6);

        return [
            'judul' => 'Pertambahan panjang '.Angka::dengan($dl, 'm', 6),
            'teks' => 'Perhatikan gambar batang logam berikut. Batang sepanjang '.Angka::dengan($l0, 'm')
                .' berkoefisien muai panjang '.Angka::id($alfa).' × 10⁻⁶ /°C dipanaskan sehingga suhunya naik '
                .Angka::dengan($dt, '°C').'. Berapakah pertambahan panjangnya?',
            'pernyataan' => 'Perhatikan gambar batang logam berikut. Pertambahan panjang batang '
                .Angka::dengan($l0, 'm').' yang suhunya naik '.Angka::dengan($dt, '°C').' adalah %s.',
            'svg' => self::svgBatang($l0, $isi['warna'] ?? 'merah'),
            'keterangan' => 'Batang logam sepanjang '.Angka::dengan($l0, 'm')
                .' sebelum dan sesudah dipanaskan.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, 'm', 6), 'urut' => $n],
                array_merge([$dl], self::saring($dl, [
                    // Lupa mengalikan faktor 10⁻⁶, atau menjumlahkan panjang
                    // awal dengan pertambahannya.
                    round($l0 * $alfa * $dt, 6), round($l0 + $dl, 6), $dl * 10, $dl / 10,
                ], 6)),
            ),
            'kata_kunci' => Angka::id($dl, 6),
            'pembahasan' => 'ΔL = L₀ × α × ΔT = '.Angka::id($l0).' × '.Angka::id($alfa)
                .' × 10⁻⁶ × '.Angka::id($dt).' = '.Angka::dengan($dl, 'm', 6).'.',
        ];
    }

    /** Batang logam sebelum dan sesudah memuai; selisihnya dilebih-lebihkan. */
    public static function svgBatang(float $panjang, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['merah'];

        $kanvas = new Kanvas;
        $kiri = 50;
        $lebar = 320;

        foreach ([[100, $lebar, 'sebelum dipanaskan'], [190, $lebar + 44, 'sesudah dipanaskan']] as [$y, $l, $teks]) {
            $kanvas->tambah('<rect x="'.$kiri.'" y="'.$y.'" width="'.$l.'" height="34" rx="4" fill="'
                .$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>')
                ->teksPx([$kiri, $y - 10], $teks, ['anchor' => 'start', 'ukuran' => 13, 'warna' => '#4b5563']);
        }

        $kanvas->garis([$kiri + $lebar, 190], [$kiri + $lebar, 250], Kanvas::HITAM, 1.5, true)
            ->garis([$kiri + $lebar + 44, 224], [$kiri + $lebar + 44, 250], Kanvas::HITAM, 1.5, true)
            ->teksPx([$kiri + $lebar + 22, 268], 'ΔL', ['ukuran' => 16, 'tebal' => true])
            ->teksPx([$kiri + $lebar / 2, 92], 'L₀ = '.Angka::dengan($panjang, 'm'), [
                'ukuran' => 14, 'tebal' => true,
            ]);

        return $kanvas->render();
    }

    /**
     * Kekeliruan konversi yang khas: pecahannya dibalik, tambahan 32 pada
     * Fahrenheit dilupakan, dan 273 pada Kelvin justru dikurangkan.
     *
     * @return array<int, float>
     */
    protected function pengecoh(float $c, string $skala, float $benar): array
    {
        $kandidat = match ($skala) {
            'fahrenheit' => [9 / 5 * $c, 5 / 9 * $c + 32, $c + 32, 4 / 5 * $c + 32],
            'kelvin' => [$c - 273, 273 - $c, $c + 100],
            default => [5 / 4 * $c, $c - 4 / 5 * $c, 4 / 5 * $c + 32],
        };

        // Suhu boleh nol atau minus, jadi kandidat semacam itu tidak dibuang.
        return self::saring($benar, array_merge($kandidat, [$benar * 2, $benar + 10, $benar / 2]), 2, false);
    }

    protected function langkah(float $c, string $skala): string
    {
        $f = fn (float $x) => Angka::id($x);

        return match ($skala) {
            'fahrenheit' => '(⁹⁄₅ × '.$f($c).') + 32 = '.$f(9 / 5 * $c).' + 32',
            'kelvin' => $f($c).' + 273',
            default => '⁴⁄₅ × '.$f($c),
        };
    }

    public static function ubah(float $c, string $skala): float
    {
        return round(match ($skala) {
            'fahrenheit' => 9 / 5 * $c + 32,
            'kelvin' => $c + 273,
            default => 4 / 5 * $c,
        }, 2);
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(float $suhu, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['merah'];

        $kanvas = new Kanvas;

        $tengah = 170;
        $lebar = 24;
        $atas = 30;
        $bawah = 238;
        $bulb = 22;

        // Kedudukan tegak sebuah nilai suhu pada tabung termometer.
        $y = fn (float $v) => $bawah - ($v - self::MIN) / (self::MAKS - self::MIN) * ($bawah - $atas);

        $kanvas->tambah('<rect x="'.($tengah - $lebar / 2).'" y="'.$atas.'" width="'.$lebar
            .'" height="'.($bawah - $atas + 10).'" rx="'.($lebar / 2).'" fill="#ffffff" stroke="'
            .Kanvas::HITAM.'" stroke-width="2"/>');

        // Cairan digambar sebagai satu batang dari bola sampai angka yang
        // ditunjuk, lalu bolanya di atasnya, supaya sambungannya tidak
        // terlihat sebagai garis.
        $tinggi = $bawah - $y(max(self::MIN, min(self::MAKS, $suhu)));

        $kanvas->tambah('<rect x="'.($tengah - $lebar / 2 + 5).'" y="'.round($bawah - $tinggi, 1)
            .'" width="'.($lebar - 10).'" height="'.round($tinggi + 20, 1).'" fill="'.$isi.'"/>');

        $kanvas->lingkaran([$tengah, $bawah + $bulb], $bulb, $isi, Kanvas::HITAM, 2);

        for ($v = self::MIN; $v <= self::MAKS; $v += 5) {
            $panjang = $v % 10 === 0 ? 14 : 7;
            $garisY = round($y($v), 1);

            $kanvas->tambah('<line x1="'.($tengah + $lebar / 2).'" y1="'.$garisY.'" x2="'
                .($tengah + $lebar / 2 + $panjang).'" y2="'.$garisY.'" stroke="'.Kanvas::HITAM.'" stroke-width="1.5"/>');

            if ($v % 20 === 0) {
                $kanvas->teksPx([$tengah + $lebar / 2 + 20, $garisY + 5], (string) $v, [
                    'anchor' => 'start', 'ukuran' => 13,
                ]);
            }
        }

        $kanvas->teksPx([$tengah + $lebar / 2 + 20, $atas - 10], '°C', ['anchor' => 'start', 'ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }
}

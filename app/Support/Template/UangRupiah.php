<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template uang rupiah: menghitung uang, kembalian, dan kecukupannya.
 *
 * Uangnya digambar sebagai lembaran dan koin, bukan ditulis sebagai angka,
 * karena yang dilatih justru mengenali pecahan uang lalu menjumlahkannya.
 * Pecahan yang bukan pecahan resmi ditolak — soal uang kehilangan maknanya
 * begitu memuat lembaran yang tidak pernah ada.
 */
class UangRupiah extends Template
{
    /** @var array<int, int> pecahan rupiah yang beredar, dari besar ke kecil */
    public const PECAHAN = [100000, 75000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

    /** Pecahan sebesar ini ke bawah digambar sebagai koin. */
    protected const KOIN = 1000;

    /** @var array<string, string> */
    public const TANYA = [
        'jumlah' => 'Jumlah seluruh uang',
        'kembalian' => 'Uang kembalian',
        'cukup' => 'Cukup atau tidak untuk membayar',
    ];

    public static function kode(): string
    {
        return 'uang-rupiah';
    }

    public static function nama(): string
    {
        return 'Uang Rupiah';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Menghitung uang bergambar, kembalian, serta menilai cukup atau '
            .'tidaknya untuk membayar.';
    }

    public static function ikon(): string
    {
        return 'bi-cash-coin';
    }

    public static function kataKunci(): array
    {
        return ['uang', 'rupiah', 'kembalian', 'belanja', 'harga', 'pecahan uang', 'sd'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'jumlah'),
            [
                'nama' => 'uang',
                'label' => 'Pecahan uang yang digambar',
                'jenis' => 'teks',
                'awal' => '20000, 10000, 5000, 2000',
                'bantuan' => 'Dipisah koma. Pecahan yang dikenal: '
                    .implode(', ', array_map(fn ($n) => number_format($n, 0, ',', '.'), self::PECAHAN)).'.',
            ],
            self::kolomAngka('harga', 'Harga barang (Rp)', 32000, 100, 10000000, [
                'langkah' => 100,
                'tampil' => ['tanya' => ['kembalian', 'cukup']],
            ]),
            [
                'nama' => 'barang',
                'label' => 'Nama barang',
                'jenis' => 'teks',
                'awal' => 'sebuah buku tulis',
                'lebar' => 6,
                'tampil' => ['tanya' => ['kembalian', 'cukup']],
            ],
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $uang = self::pecah((string) ($isi['uang'] ?? ''));

        if (count($uang) < 1) {
            return ['uang' => 'Tuliskan minimal satu pecahan uang.'];
        }

        if (count($uang) > 10) {
            return ['uang' => 'Paling banyak 10 lembar/koin agar gambarnya tetap terbaca.'];
        }

        foreach ($uang as $n) {
            if (! in_array($n, self::PECAHAN, true)) {
                return ['uang' => number_format($n, 0, ',', '.').' bukan pecahan rupiah yang beredar.'];
            }
        }

        if (($isi['tanya'] ?? '') === 'kembalian' && (int) ($isi['harga'] ?? 0) >= array_sum($uang)) {
            return ['harga' => 'Agar ada kembalian, harga barang harus lebih kecil daripada uangnya.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $uang = self::pecah((string) $isi['uang']);
        $total = array_sum($uang);
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $svg = self::svg($uang, $warnaIsi, $garis);
        $rp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
        $rincian = implode(' + ', array_map($rp, $uang));
        $keterangan = 'Gambar '.count($uang).' pecahan uang: '.implode(', ', array_map($rp, $uang)).'.';
        $cerita = 'Perhatikan gambar uang berikut.';

        if (($isi['tanya'] ?? 'jumlah') === 'jumlah') {
            return [
                'judul' => 'Uang '.$rp($total),
                'teks' => $cerita.' Berapakah jumlah seluruh uang itu?',
                'pernyataan' => $cerita.' Jumlah seluruh uang itu adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => self::pilihanRupiah($total, [
                    $total - min($uang), $total + min($uang), $total * 10, $total / 10,
                ]),
                'kata_kunci' => number_format($total, 0, ',', '.'),
                'pembahasan' => $rincian.' = '.$rp($total).'.',
            ];
        }

        $harga = (int) $isi['harga'];
        $barang = self::bebas($isi, 'barang', 'sebuah barang');
        $selisih = $total - $harga;

        if (($isi['tanya'] ?? '') === 'kembalian') {
            return [
                'judul' => 'Kembalian '.$rp($selisih),
                'teks' => $cerita." Uang itu dipakai membeli {$barang} seharga ".$rp($harga)
                    .'. Berapakah uang kembaliannya?',
                'pernyataan' => $cerita." Uang itu dipakai membeli {$barang} seharga ".$rp($harga)
                    .', sehingga kembaliannya %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => self::pilihanRupiah($selisih, [
                    $harga, $total, $harga - $total, $selisih + 1000, $selisih - 1000,
                ]),
                'kata_kunci' => number_format($selisih, 0, ',', '.'),
                'pembahasan' => 'Jumlah uang '.$rincian.' = '.$rp($total).'.<br>Kembalian = '
                    .$rp($total).' − '.$rp($harga).' = '.$rp($selisih).'.',
            ];
        }

        $benar = match (true) {
            $selisih > 0 => 'Cukup, bersisa '.$rp($selisih),
            $selisih === 0 => 'Cukup dan uangnya pas',
            default => 'Tidak cukup, kurang '.$rp(-$selisih),
        };

        return [
            'judul' => $benar,
            'teks' => $cerita." Uang itu dipakai membeli {$barang} seharga ".$rp($harga).'. Pernyataan yang benar adalah ...',
            'pernyataan' => $cerita." Untuk membeli {$barang} seharga ".$rp($harga).', uang itu %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                array_values(array_unique([
                    $benar,
                    'Cukup dan uangnya pas',
                    'Cukup, bersisa '.$rp(abs($selisih) + 1000),
                    'Tidak cukup, kurang '.$rp(abs($selisih) + 2000),
                ])),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Jumlah uang '.$rp($total).', harga '.$rp($harga).'. '
                .($selisih >= 0 ? 'Uangnya cukup' : 'Uangnya kurang').', selisihnya '.$rp(abs($selisih)).'.',
        ];
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanRupiah(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => 'Rp '.number_format($n, 0, ',', '.'), 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat, 0)),
        );
    }

    /** @return array<int, int> */
    protected static function pecah(string $teks): array
    {
        return array_values(array_filter(array_map(
            fn ($x) => (int) preg_replace('/[^0-9]/', '', $x),
            explode(',', $teks),
        ), fn ($n) => $n > 0));
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(array $uang, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $perBaris = min(3, max(1, count($uang)));
        $baris = (int) ceil(count($uang) / $perBaris);

        $lebar = min(150, (Kanvas::LEBAR - 40) / $perBaris - 14);
        $tinggi = min(74, (Kanvas::TINGGI - 40) / $baris - 14);

        foreach ($uang as $i => $nominal) {
            $barisKe = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, count($uang) - $barisKe * $perBaris);
            $x = (Kanvas::LEBAR - $isiBaris * ($lebar + 14)) / 2 + ($i % $perBaris) * ($lebar + 14);
            $y = (Kanvas::TINGGI - $baris * ($tinggi + 14)) / 2 + $barisKe * ($tinggi + 14);

            if ($nominal <= self::KOIN) {
                // Koin digambar bundar supaya bedanya dengan lembaran terlihat
                // sekilas, seperti saat memegang uangnya sungguhan.
                $r = min($tinggi, $lebar) / 2;
                $kanvas->lingkaran([$x + $lebar / 2, $y + $tinggi / 2], $r, '#e9e3c8', '#9a8c4a', 2.5)
                    ->lingkaran([$x + $lebar / 2, $y + $tinggi / 2], $r - 5, 'none', '#9a8c4a', 1);
            } else {
                $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($lebar, 1)
                    .'" height="'.round($tinggi, 1).'" rx="6" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>')
                    ->tambah('<rect x="'.round($x + 7, 1).'" y="'.round($y + 7, 1).'" width="'.round($lebar - 14, 1)
                        .'" height="'.round($tinggi - 14, 1).'" rx="4" fill="none" stroke="'.$garis
                        .'" stroke-width="1" stroke-dasharray="4 3"/>');
            }

            $kanvas->teksPx([$x + $lebar / 2, $y + $tinggi / 2 + 6], 'Rp '.number_format($nominal, 0, ',', '.'), [
                'ukuran' => $nominal <= self::KOIN ? 13 : 16, 'tebal' => true,
            ]);
        }

        return $kanvas->render();
    }
}

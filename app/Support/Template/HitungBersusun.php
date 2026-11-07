<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template hitung bersusun: penjumlahan sampai pembagian porogapit.
 *
 * Yang diuji bukan hasilnya saja melainkan cara menyusunnya — satuan di
 * bawah satuan, puluhan di bawah puluhan. Karena itu angkanya digambar
 * benar-benar bersusun, bukan ditulis mendatar di teks soal, dan pengecohnya
 * memuat hasil salah susun yang khas: angka yang tidak sejajar.
 */
class HitungBersusun extends Template
{
    /** @var array<string, array{0: string, 1: string}> lambang & sebutan */
    public const OPERASI = [
        'tambah' => ['+', 'Penjumlahan'],
        'kurang' => ['−', 'Pengurangan'],
        'kali' => ['×', 'Perkalian'],
        'bagi' => [':', 'Pembagian (porogapit)'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Hasil hitungannya',
        'hilang' => 'Bilangan yang belum diketahui',
    ];

    public static function kode(): string
    {
        return 'hitung-bersusun';
    }

    public static function nama(): string
    {
        return 'Hitung Bersusun';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Penjumlahan, pengurangan, dan perkalian bersusun serta pembagian '
            .'porogapit: hasilnya atau bilangan yang belum diketahui.';
    }

    public static function ikon(): string
    {
        return 'bi-list-ol';
    }

    public static function kataKunci(): array
    {
        return ['bersusun', 'porogapit', 'penjumlahan', 'pengurangan', 'perkalian', 'pembagian', 'menyimpan'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'operasi',
                'label' => 'Operasi',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($o) => $o[1], self::OPERASI),
                'awal' => 'tambah',
            ],
            self::kolomAngka('bilangan_1', 'Bilangan pertama', 376, 1, 999999, ['langkah' => 1]),
            self::kolomAngka('bilangan_2', 'Bilangan kedua', 148, 1, 9999, ['langkah' => 1]),
            self::kolomTanya(self::TANYA, 'hasil', 6),
            self::kolomWarna('abu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $a = (int) ($isi['bilangan_1'] ?? 0);
        $b = (int) ($isi['bilangan_2'] ?? 0);

        return match ($isi['operasi'] ?? '') {
            'kurang' => $b > $a ? ['bilangan_2' => 'Pengurangnya tidak boleh lebih besar daripada bilangan pertama.'] : [],
            'bagi' => $a % $b !== 0
                ? ['bilangan_2' => 'Agar porogapitnya habis dibagi, pilih pembagi yang membagi habis '.$a.'.']
                : [],
            default => [],
        };
    }

    protected function susun(array $isi): array
    {
        $a = (int) $isi['bilangan_1'];
        $b = (int) $isi['bilangan_2'];
        $operasi = $isi['operasi'] ?? 'tambah';
        [$lambang, $sebutan] = self::OPERASI[$operasi];

        $hasil = match ($operasi) {
            'kurang' => $a - $b,
            'kali' => $a * $b,
            'bagi' => intdiv($a, $b),
            default => $a + $b,
        };

        [$warnaIsi, $garis] = self::warna($isi, 'abu');
        $cerita = 'Perhatikan hitungan bersusun berikut.';
        $kalimat = "{$a} {$lambang} {$b} = {$hasil}";

        if (($isi['tanya'] ?? 'hasil') === 'hilang') {
            return [
                'judul' => $kalimat,
                'teks' => $cerita.' Bilangan berapakah yang harus menempati tempat yang kosong?',
                'pernyataan' => $cerita.' Bilangan yang menempati tempat kosong adalah %s.',
                'svg' => self::svg($operasi, $a, null, $hasil, $warnaIsi, $garis),
                'keterangan' => mb_strtolower($sebutan)." bersusun: {$kalimat}.",
                'pilihan' => self::pilihanAngka($b, [$a, $hasil, $b + 1, $b - 1, $b + 10]),
                'kata_kunci' => (string) $b,
                'pembahasan' => "Tempat kosong itu diisi {$b}, sehingga {$kalimat}.",
            ];
        }

        return [
            'judul' => $kalimat,
            'teks' => $cerita.' Berapakah hasilnya?',
            'pernyataan' => $cerita.' Hasilnya adalah %s.',
            'svg' => self::svg($operasi, $a, $b, null, $warnaIsi, $garis),
            'keterangan' => mb_strtolower($sebutan)." bersusun: {$kalimat}.",
            // Lupa menyimpan atau meminjam menggeser hasilnya tepat sepuluh
            // atau seratus — itulah pengecoh yang paling berguna di sini.
            'pilihan' => self::pilihanAngka($hasil, [
                $hasil - 10, $hasil + 10, $hasil - 100, $hasil + 100, $hasil + 1,
            ]),
            'kata_kunci' => (string) $hasil,
            'pembahasan' => $kalimat.'.',
        ];
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanAngka(int $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat, 0)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Angka yang bernilai null digambar sebagai kotak kosong — itulah yang
     * harus dicari siswa.
     */
    public static function svg(string $operasi, ?int $a, ?int $b, ?int $hasil, string $isi, string $garis): string
    {
        return $operasi === 'bagi'
            ? self::porogapit($a, $b, $hasil, $isi, $garis)
            : self::tumpuk(self::OPERASI[$operasi][0], $a, $b, $hasil, $isi, $garis);
    }

    protected static function tumpuk(string $lambang, ?int $a, ?int $b, ?int $hasil, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanan = 320;
        $baris = [120, 176, 244];

        $kanvas->teksPx([$kanan, $baris[0]], self::teksAngka($a), ['anchor' => 'end', 'ukuran' => 42]);
        $kanvas->teksPx([$kanan, $baris[1]], self::teksAngka($b), ['anchor' => 'end', 'ukuran' => 42]);
        $kanvas->teksPx([$kanan - 148, $baris[1]], $lambang, ['anchor' => 'start', 'ukuran' => 42]);

        $kanvas->garis([$kanan - 160, $baris[1] + 20], [$kanan + 12, $baris[1] + 20], Kanvas::HITAM, 3);

        $kanvas->teksPx([$kanan, $baris[2]], $hasil === null ? '?' : (string) $hasil, [
            'anchor' => 'end', 'ukuran' => 42, 'tebal' => true, 'warna' => $garis,
        ]);

        return $kanvas->render();
    }

    protected static function porogapit(?int $a, ?int $b, ?int $hasil, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $x = 200;
        $y = 170;

        // Tanda porogapit: satu garis tegak di kiri bilangan yang dibagi,
        // disambung garis mendatar di atasnya.
        $kanvas->teksPx([$x - 16, $y], self::teksAngka($b), ['anchor' => 'end', 'ukuran' => 40])
            ->teksPx([$x + 16, $y], self::teksAngka($a), ['anchor' => 'start', 'ukuran' => 40])
            ->garis([$x, $y + 14], [$x, $y - 42], Kanvas::HITAM, 3)
            ->garis([$x, $y - 42], [$x + 180, $y - 42], Kanvas::HITAM, 3);

        $kanvas->teksPx([$x + 16, $y - 56], $hasil === null ? '?' : (string) $hasil, [
            'anchor' => 'start', 'ukuran' => 40, 'tebal' => true, 'warna' => $garis,
        ]);

        return $kanvas->render();
    }

    protected static function teksAngka(?int $n): string
    {
        return $n === null ? '. . .' : (string) $n;
    }
}

<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template peluang: dadu, koin, dan kelereng dalam kantong.
 *
 * Ruang sampelnya digambar utuh — keenam mata dadu, kedua sisi koin, seluruh
 * kelereng — supaya peluang terbaca sebagai perbandingan banyak kejadian
 * terhadap banyak seluruh kemungkinan, bukan sebagai rumus yang dihafal.
 */
class Peluang extends Template
{
    /** @var array<string, string> */
    public const ALAT = [
        'dadu' => 'Dadu',
        'koin' => 'Uang logam',
        'kelereng' => 'Kelereng dalam kantong',
    ];

    /** @var array<string, string> kejadian pada pelemparan satu dadu */
    public const KEJADIAN = [
        'genap' => 'mata dadu genap',
        'ganjil' => 'mata dadu ganjil',
        'prima' => 'mata dadu bilangan prima',
        'lebih_4' => 'mata dadu lebih dari 4',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'peluang' => 'Peluang kejadian',
        'ruang_sampel' => 'Banyak anggota ruang sampel',
        'frekuensi' => 'Frekuensi harapan',
    ];

    public static function kode(): string
    {
        return 'peluang';
    }

    public static function nama(): string
    {
        return 'Peluang';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Dadu, uang logam, dan kelereng: peluang kejadian, banyak anggota '
            .'ruang sampel, serta frekuensi harapan.';
    }

    public static function ikon(): string
    {
        return 'bi-dice-5';
    }

    public static function kataKunci(): array
    {
        return ['peluang', 'dadu', 'koin', 'kelereng', 'ruang sampel', 'frekuensi harapan', 'statistika'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'alat',
                'label' => 'Alat percobaan',
                'jenis' => 'pilihan',
                'pilihan' => self::ALAT,
                'awal' => 'dadu',
            ],
            [
                'nama' => 'kejadian',
                'label' => 'Kejadian yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::KEJADIAN,
                'awal' => 'genap',
                'tampil' => ['alat' => ['dadu']],
            ],
            self::kolomAngka('banyak_koin', 'Banyak uang logam', 2, 1, 4, [
                'langkah' => 1, 'tampil' => ['alat' => ['koin']],
            ]),
            self::kolomAngka('merah', 'Kelereng merah', 4, 0, 15, [
                'langkah' => 1, 'tampil' => ['alat' => ['kelereng']],
            ]),
            self::kolomAngka('biru', 'Kelereng biru', 6, 0, 15, [
                'langkah' => 1, 'tampil' => ['alat' => ['kelereng']],
            ]),
            self::kolomAngka('hijau', 'Kelereng hijau', 2, 0, 15, [
                'langkah' => 1, 'tampil' => ['alat' => ['kelereng']],
            ]),
            self::kolomAngka('percobaan', 'Banyak percobaan', 120, 1, 10000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['frekuensi']],
            ]),
            self::kolomTanya(self::TANYA, 'peluang'),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['alat'] ?? '') === 'kelereng') {
            $jumlah = (int) ($isi['merah'] ?? 0) + (int) ($isi['biru'] ?? 0) + (int) ($isi['hijau'] ?? 0);

            if ($jumlah < 2) {
                return ['merah' => 'Isi minimal dua kelereng seluruhnya.'];
            }

            if ((int) ($isi['merah'] ?? 0) < 1) {
                return ['merah' => 'Kelereng yang ditanyakan peluangnya harus ada minimal satu.'];
            }
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');
        $alat = $isi['alat'] ?? 'dadu';

        [$berhasil, $semua, $kalimat, $svg, $keterangan] = match ($alat) {
            'koin' => $this->koin((int) $isi['banyak_koin'], $warnaIsi, $garis),
            'kelereng' => $this->kelereng($isi, $warnaIsi, $garis),
            default => $this->dadu($isi['kejadian'] ?? 'genap', $warnaIsi, $garis),
        };

        $tanya = $isi['tanya'] ?? 'peluang';

        if ($tanya === 'ruang_sampel') {
            return [
                'judul' => 'Ruang sampel '.$semua,
                'teks' => 'Perhatikan gambar berikut. Ada berapa banyak anggota ruang sampelnya?',
                'pernyataan' => 'Perhatikan gambar berikut. Banyak anggota ruang sampelnya ada %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
                    array_merge([$semua], self::saring($semua, [
                        $berhasil, $semua * 2, $semua - 1, $semua + 1, $semua / 2,
                    ], 0)),
                ),
                'kata_kunci' => (string) $semua,
                'pembahasan' => 'Seluruh kemungkinan yang dapat terjadi ada '.$semua.'.',
            ];
        }

        $peluang = self::pecahan($berhasil, $semua);

        if ($tanya === 'frekuensi') {
            $percobaan = (int) ($isi['percobaan'] ?? 1);
            $harapan = round($berhasil / $semua * $percobaan, 2);

            return [
                'judul' => 'Frekuensi harapan '.Angka::id($harapan),
                'teks' => "Perhatikan gambar berikut. Percobaan itu dilakukan {$percobaan} kali. "
                    ."Berapakah frekuensi harapan kejadian {$kalimat}?",
                'pernyataan' => "Perhatikan gambar berikut. Bila percobaan itu dilakukan {$percobaan} kali, "
                    ."frekuensi harapan kejadian {$kalimat} adalah %s.",
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n).' kali', 'urut' => $n],
                    array_merge([$harapan], self::saring($harapan, [
                        $percobaan - $harapan, $percobaan / 2, $harapan * 2, $harapan / 2, $berhasil,
                    ])),
                ),
                'kata_kunci' => Angka::id($harapan),
                'pembahasan' => "Peluang kejadian {$kalimat} = {$peluang}.<br>Frekuensi harapan = peluang × "
                    ."banyak percobaan = {$peluang} × {$percobaan} = ".Angka::id($harapan).' kali.',
            ];
        }

        return [
            'judul' => 'Peluang '.$peluang,
            'teks' => "Perhatikan gambar berikut. Berapakah peluang kejadian {$kalimat}?",
            'pernyataan' => "Perhatikan gambar berikut. Peluang kejadian {$kalimat} adalah %s.",
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => $this->pilihanPeluang($berhasil, $semua),
            'kata_kunci' => $peluang,
            'pembahasan' => "Banyak kejadian yang diharapkan ada {$berhasil}, seluruh kemungkinan ada {$semua}. "
                ."Peluang = {$berhasil}/{$semua} = {$peluang}.",
        ];
    }

    /** @return array{0: int, 1: int, 2: string, 3: string, 4: string} */
    protected function dadu(string $kejadian, string $isi, string $garis): array
    {
        $mata = match ($kejadian) {
            'ganjil' => [1, 3, 5],
            'prima' => [2, 3, 5],
            'lebih_4' => [5, 6],
            default => [2, 4, 6],
        };

        return [
            count($mata),
            6,
            self::KEJADIAN[$kejadian],
            self::svgDadu($mata, $isi, $garis),
            'Keenam mata dadu, yang memenuhi kejadian ditandai.',
        ];
    }

    /** @return array{0: int, 1: int, 2: string, 3: string, 4: string} */
    protected function koin(int $banyak, string $isi, string $garis): array
    {
        return [
            1,
            2 ** $banyak,
            "seluruhnya muncul sisi angka pada {$banyak} uang logam",
            self::svgKoin($banyak, $isi, $garis),
            "{$banyak} uang logam dilempar bersamaan.",
        ];
    }

    /** @return array{0: int, 1: int, 2: string, 3: string, 4: string} */
    protected function kelereng(array $isi, string $warnaIsi, string $garis): array
    {
        $merah = (int) $isi['merah'];
        $jumlah = $merah + (int) $isi['biru'] + (int) $isi['hijau'];

        return [
            $merah,
            $jumlah,
            'terambil kelereng merah',
            self::svgKelereng($merah, (int) $isi['biru'], (int) $isi['hijau'], $garis),
            "Kantong berisi {$merah} kelereng merah, ".$isi['biru'].' biru, dan '.$isi['hijau'].' hijau.',
        ];
    }

    /**
     * Pengecoh peluang: kejadian dibanding kejadian yang gagal (bukan
     * terhadap seluruh ruang sampel), serta pecahan yang terbalik.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected function pilihanPeluang(int $berhasil, int $semua): array
    {
        $kandidat = [
            [$berhasil, $semua],
            [$semua - $berhasil, $semua],
            [$berhasil, max(1, $semua - $berhasil)],
            [$semua, $berhasil],
            [1, $semua],
            // Meleset satu kejadian: cadangan untuk ruang sampel yang kecil,
            // tempat pengecoh di atas banyak yang berimpit — pada pelemparan
            // satu uang logam, "bukan angka" dan "angka" sama-sama 1/2.
            [$berhasil, $semua + 1],
            [$berhasil + 1, $semua],
        ];

        $dipakai = [];
        $pilihan = [];

        foreach ($kandidat as [$a, $b]) {
            if ($a < 1 || $b < 1) {
                continue;
            }

            $teks = self::pecahan($a, $b);

            if (isset($dipakai[$teks])) {
                continue;
            }

            $dipakai[$teks] = true;
            $pilihan[] = ['teks' => $teks, 'urut' => (string) round($a / $b, 4)];
        }

        return $pilihan;
    }

    /** Pecahan sederhana; bernilai satu ditulis sebagai 1. */
    public static function pecahan(int $atas, int $bawah): string
    {
        $pembagi = self::fpb($atas, $bawah);
        $a = (int) ($atas / $pembagi);
        $b = (int) ($bawah / $pembagi);

        return $b === 1 ? (string) $a : $a.'/'.$b;
    }

    protected static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, abs($a));
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** @param  array<int, int>  $ditandai */
    public static function svgDadu(array $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $sisi = 92;
        $jarak = 14;
        $kiri = (Kanvas::LEBAR - 3 * $sisi - 2 * $jarak) / 2;
        $atas = (Kanvas::TINGGI - 2 * $sisi - $jarak) / 2;

        // Titik mata dadu pada petak 3×3; tiap mata memakai kedudukan baku.
        $pola = [
            1 => [[1, 1]],
            2 => [[0, 0], [2, 2]],
            3 => [[0, 0], [1, 1], [2, 2]],
            4 => [[0, 0], [2, 0], [0, 2], [2, 2]],
            5 => [[0, 0], [2, 0], [1, 1], [0, 2], [2, 2]],
            6 => [[0, 0], [2, 0], [0, 1], [2, 1], [0, 2], [2, 2]],
        ];

        for ($n = 1; $n <= 6; $n++) {
            $kolom = ($n - 1) % 3;
            $baris = intdiv($n - 1, 3);
            $x = $kiri + $kolom * ($sisi + $jarak);
            $y = $atas + $baris * ($sisi + $jarak);
            $tandai = in_array($n, $ditandai, true);

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.$sisi.'" height="'.$sisi
                .'" rx="12" fill="'.($tandai ? $isi : '#ffffff').'" stroke="'.$garis
                .'" stroke-width="'.($tandai ? 4 : 2).'"/>');

            foreach ($pola[$n] as [$kx, $ky]) {
                $kanvas->lingkaran([
                    $x + $sisi * (0.24 + $kx * 0.26),
                    $y + $sisi * (0.24 + $ky * 0.26),
                ], 7, Kanvas::HITAM, Kanvas::HITAM, 1);
            }
        }

        return $kanvas->render();
    }

    public static function svgKoin(int $banyak, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $r = min(52, (Kanvas::LEBAR - 60) / (2 * $banyak));

        for ($i = 0; $i < $banyak; $i++) {
            $x = (Kanvas::LEBAR - ($banyak - 1) * $r * 2.4) / 2 + $i * $r * 2.4;

            $kanvas->lingkaran([$x, Kanvas::TINGGI / 2], $r, '#e9e3c8', '#9a8c4a', 3)
                ->lingkaran([$x, Kanvas::TINGGI / 2], $r - 7, 'none', '#9a8c4a', 1.5)
                ->teksPx([$x, Kanvas::TINGGI / 2 + 8], $i % 2 === 0 ? 'A' : 'G', [
                    'ukuran' => 28, 'tebal' => true,
                ]);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, Kanvas::TINGGI - 24],
            'A = angka, G = gambar', ['ukuran' => 13, 'warna' => '#4b5563']);

        return $kanvas->render();
    }

    public static function svgKelereng(int $merah, int $biru, int $hijau, string $garis): string
    {
        $kanvas = new Kanvas;
        $jumlah = $merah + $biru + $hijau;

        // Kantongnya digambar sebagai bidang membulat supaya seluruh kelereng
        // terbaca sebagai satu kumpulan yang diambil acak.
        $kanvas->tambah('<path d="M 120 70 Q 240 30 360 70 L 400 250 Q 240 290 80 250 Z" fill="#f2f4f6" '
            .'stroke="'.Kanvas::HITAM.'" stroke-width="2.5"/>');

        $warna = array_merge(
            array_fill(0, $merah, '#e4556a'),
            array_fill(0, $biru, '#3f7fd0'),
            array_fill(0, $hijau, '#4caf50'),
        );

        $perBaris = min(5, max(1, $jumlah));
        $jarak = min(52, 240 / $perBaris);
        $baris = (int) ceil($jumlah / $perBaris);

        foreach ($warna as $i => $w) {
            $barisKe = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, $jumlah - $barisKe * $perBaris);

            $kanvas->lingkaran([
                Kanvas::LEBAR / 2 - ($isiBaris - 1) * $jarak / 2 + ($i % $perBaris) * $jarak,
                160 - ($baris - 1) * $jarak / 2 + $barisKe * $jarak,
            ], $jarak * 0.36, $w, Kanvas::HITAM, 1.5);
        }

        return $kanvas->render();
    }
}

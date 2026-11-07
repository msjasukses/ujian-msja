<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template pewarisan sifat: persilangan monohibrid dengan papan Punnett.
 *
 * Isi keempat kotak papannya dihitung dari genotipe kedua induk, bukan
 * dituliskan per soal. Karena berasal dari papan yang sama, perbandingan
 * fenotipe, perbandingan genotipe, persentase, dan banyak keturunan yang
 * diharapkan tidak mungkin berselisih dengan gambar yang dilihat siswa.
 */
class PewarisanSifat extends Template
{
    /**
     * Sifat yang disilangkan beserta huruf gennya. Huruf besar selalu alel
     * dominan dan huruf kecil alel resesif, mengikuti kebiasaan penulisan
     * genotipe di buku pelajaran.
     *
     * @var array<string, array{nama: string, huruf: string, dominan: string, resesif: string}>
     */
    public const SIFAT = [
        'bunga' => ['nama' => 'warna bunga', 'huruf' => 'M', 'dominan' => 'merah', 'resesif' => 'putih'],
        'biji' => ['nama' => 'bentuk biji', 'huruf' => 'B', 'dominan' => 'bulat', 'resesif' => 'kisut'],
        'batang' => ['nama' => 'tinggi batang', 'huruf' => 'T', 'dominan' => 'tinggi', 'resesif' => 'pendek'],
        'rasa' => ['nama' => 'rasa buah', 'huruf' => 'A', 'dominan' => 'manis', 'resesif' => 'asam'],
        'kulit' => ['nama' => 'warna kulit biji', 'huruf' => 'H', 'dominan' => 'hitam', 'resesif' => 'kuning'],
    ];

    /** @var array<string, string> */
    public const GENOTIPE = [
        'dominan' => 'Homozigot dominan (AA)',
        'hetero' => 'Heterozigot (Aa)',
        'resesif' => 'Homozigot resesif (aa)',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'fenotipe' => 'Perbandingan fenotipe keturunan',
        'genotipe' => 'Perbandingan genotipe keturunan',
        'persen' => 'Persentase keturunan berfenotipe resesif',
        'banyak' => 'Banyak keturunan berfenotipe dominan',
        'gamet' => 'Gamet yang dibentuk induk pertama',
    ];

    /**
     * Perbandingan yang lazim muncul pada persilangan monohibrid. Dipakai
     * sebagai pengecoh karena inilah angka-angka yang sudah dihafal siswa,
     * sehingga yang keliru memasangkannya tetap menemukan jawabannya di
     * daftar — bukan sekadar menebak.
     *
     * @var array<int, string>
     */
    public const RASIO_DUA = ['3 : 1', '1 : 1', '1 : 3', '1 : 0', '0 : 1', '2 : 1', '1 : 2'];

    /** @var array<int, string> */
    public const RASIO_TIGA = ['1 : 2 : 1', '1 : 1 : 0', '0 : 1 : 1', '1 : 0 : 1',
        '0 : 0 : 1', '1 : 0 : 0', '0 : 1 : 0', '1 : 1 : 1', '2 : 1 : 1'];

    public static function kode(): string
    {
        return 'pewarisan-sifat';
    }

    public static function nama(): string
    {
        return 'Pewarisan Sifat';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Persilangan monohibrid berpapan Punnett: perbandingan fenotipe, '
            .'genotipe, persentase, dan banyak keturunan yang diharapkan.';
    }

    public static function ikon(): string
    {
        return 'bi-grid-3x3';
    }

    public static function kataKunci(): array
    {
        return ['pewarisan sifat', 'genetika', 'punnett', 'monohibrid', 'persilangan',
            'genotipe', 'fenotipe', 'dominan', 'resesif', 'heterozigot', 'mendel', 'gamet'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'sifat',
                'label' => 'Sifat yang diamati',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($s) => ucfirst($s['nama']).' ('.$s['dominan'].' × '.$s['resesif'].')',
                    self::SIFAT,
                ),
                'awal' => 'bunga',
            ],
            [
                'nama' => 'induk1',
                'label' => 'Genotipe induk pertama',
                'jenis' => 'pilihan',
                'pilihan' => self::GENOTIPE,
                'awal' => 'hetero',
                'lebar' => 6,
            ],
            [
                'nama' => 'induk2',
                'label' => 'Genotipe induk kedua',
                'jenis' => 'pilihan',
                'pilihan' => self::GENOTIPE,
                'awal' => 'hetero',
                'lebar' => 6,
            ],
            self::kolomTanya(self::TANYA, 'fenotipe'),
            self::kolomAngka('keturunan', 'Banyak keturunan seluruhnya', 400, 4, 10000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['banyak']],
            ]),
            self::kolomWarna('hijau'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $sifat = self::SIFAT[$isi['sifat'] ?? 'bunga'] ?? self::SIFAT['bunga'];
        $huruf = $sifat['huruf'];
        $kecil = mb_strtolower($huruf);

        // Gamet kedua induk tidak diringkas lebih dahulu: papan 2 × 2 adalah
        // bentuk yang dipakai di buku, dan menyeragamkannya membuat seluruh
        // perbandingan selalu dihitung dari empat kotak.
        $alel1 = self::alel($isi['induk1'] ?? 'hetero', $huruf);
        $alel2 = self::alel($isi['induk2'] ?? 'hetero', $huruf);
        $tulis1 = implode('', $alel1);
        $tulis2 = implode('', $alel2);

        $sel = [];

        foreach ($alel2 as $y) {
            foreach ($alel1 as $x) {
                $sel[] = ctype_upper($x) ? $x.$y : $y.$x;
            }
        }

        $dominan = count(array_filter($sel, fn ($g) => str_contains($g, $huruf)));
        $resesif = count($sel) - $dominan;

        $gen = [
            $huruf.$huruf => count(array_filter($sel, fn ($g) => $g === $huruf.$huruf)),
            $huruf.$kecil => count(array_filter($sel, fn ($g) => $g === $huruf.$kecil)),
            $kecil.$kecil => count(array_filter($sel, fn ($g) => $g === $kecil.$kecil)),
        ];

        $bahan = [
            'svg' => self::svgPunnett($alel1, $alel2, $sel, $tulis1, $tulis2, $warnaIsi, $garis),
            'keterangan' => "Papan Punnett persilangan {$tulis1} × {$tulis2}.",
        ];

        $awalan = "Tanaman bergenotipe {$tulis1} disilangkan dengan tanaman bergenotipe {$tulis2}. "
            ."Sifat {$sifat['nama']} {$sifat['dominan']} ({$huruf}) dominan terhadap "
            ."{$sifat['resesif']} ({$kecil}).";

        return $bahan + match ($isi['tanya'] ?? 'fenotipe') {
            'genotipe' => $this->rasioGenotipe($awalan, $gen),
            'persen' => $this->persen($awalan, $resesif, count($sel), $sifat),
            'banyak' => $this->banyak($awalan, $dominan, $resesif, count($sel), (int) ($isi['keturunan'] ?? 400), $sifat),
            'gamet' => $this->gamet($awalan, $alel1, $tulis1, $huruf, $kecil),
            default => $this->rasioFenotipe($awalan, $dominan, $resesif, $sifat),
        };
    }

    /** Alel yang dimiliki satu induk. */
    protected static function alel(string $pilihan, string $huruf): array
    {
        $kecil = mb_strtolower($huruf);

        return match ($pilihan) {
            'resesif' => [$kecil, $kecil],
            'hetero' => [$huruf, $kecil],
            default => [$huruf, $huruf],
        };
    }

    protected function rasioFenotipe(string $awalan, int $dominan, int $resesif, array $sifat): array
    {
        $benar = self::rasio([$dominan, $resesif]);
        $urut = "{$sifat['dominan']} : {$sifat['resesif']}";

        return [
            'judul' => 'Perbandingan fenotipe '.$benar,
            'teks' => $awalan." Perhatikan papan Punnett berikut. Perbandingan fenotipe {$urut} "
                .'pada keturunannya adalah ...',
            'pernyataan' => $awalan." Pada papan Punnett berikut, perbandingan fenotipe {$urut} "
                .'keturunannya adalah %s.',
            'pilihan' => self::pilihanRasio($benar, self::RASIO_DUA),
            'kata_kunci' => $benar,
            'pembahasan' => "Dari empat kotak papan Punnett, {$dominan} kotak berfenotipe "
                ."{$sifat['dominan']} (memuat alel dominan) dan {$resesif} kotak berfenotipe "
                ."{$sifat['resesif']}.<br>Perbandingan {$urut} = {$dominan} : {$resesif} = {$benar}.",
        ];
    }

    protected function rasioGenotipe(string $awalan, array $gen): array
    {
        $benar = self::rasio(array_values($gen));
        $urut = implode(' : ', array_keys($gen));

        return [
            'judul' => 'Perbandingan genotipe '.$benar,
            'teks' => $awalan." Perhatikan papan Punnett berikut. Perbandingan genotipe {$urut} "
                .'pada keturunannya adalah ...',
            'pernyataan' => $awalan." Pada papan Punnett berikut, perbandingan genotipe {$urut} "
                .'keturunannya adalah %s.',
            'pilihan' => self::pilihanRasio($benar, self::RASIO_TIGA),
            'kata_kunci' => $benar,
            'pembahasan' => 'Banyaknya tiap genotipe pada keempat kotak: '
                .implode(', ', array_map(fn ($g, $n) => "{$g} = {$n}", array_keys($gen), $gen))
                .".<br>Perbandingan {$urut} = ".implode(' : ', array_values($gen))." = {$benar}.",
        ];
    }

    protected function persen(string $awalan, int $resesif, int $semua, array $sifat): array
    {
        $benar = (int) round($resesif / $semua * 100);

        return [
            'judul' => 'Keturunan '.$sifat['resesif'].' '.$benar.'%',
            'teks' => $awalan.' Perhatikan papan Punnett berikut. Berapa persen keturunannya yang '
                ."berfenotipe {$sifat['resesif']}?",
            'pernyataan' => $awalan." Pada papan Punnett berikut, keturunan berfenotipe {$sifat['resesif']} "
                .'sebanyak %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n.'%', 'urut' => $n],
                // Pengecohnya persentase lain yang mungkin muncul pada
                // persilangan monohibrid, bukan angka sembarang.
                array_merge([$benar], array_values(array_diff(
                    array_unique([100 - $benar, 25, 50, 75, 100, 0]),
                    [$benar],
                ))),
            ),
            'kata_kunci' => $benar.'%',
            'pembahasan' => "Dari {$semua} kotak papan Punnett, {$resesif} kotak berfenotipe "
                ."{$sifat['resesif']}.<br>Persentase = {$resesif}/{$semua} × 100% = {$benar}%.",
        ];
    }

    protected function banyak(string $awalan, int $dominan, int $resesif, int $semua, int $keturunan, array $sifat): array
    {
        $benar = round($dominan / $semua * $keturunan, 2);

        return [
            'judul' => Angka::id($benar).' keturunan '.$sifat['dominan'],
            'teks' => $awalan." Dari {$keturunan} keturunan yang dihasilkan, berapa banyak yang "
                ."diharapkan berfenotipe {$sifat['dominan']}?",
            'pernyataan' => $awalan." Dari {$keturunan} keturunan, yang diharapkan berfenotipe "
                ."{$sifat['dominan']} ada %s.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n).' tanaman', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    $resesif / $semua * $keturunan,
                    $keturunan / 2,
                    $keturunan / 4,
                    $keturunan * 3 / 4,
                    $keturunan,
                    $dominan,
                ])),
            ),
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => "Peluang berfenotipe {$sifat['dominan']} = {$dominan}/{$semua}.<br>"
                ."Banyak yang diharapkan = {$dominan}/{$semua} × {$keturunan} = "
                .Angka::id($benar).' tanaman.',
        ];
    }

    protected function gamet(string $awalan, array $alel, string $tulis, string $huruf, string $kecil): array
    {
        $tunggal = $alel[0] === $alel[1];
        $benar = $tunggal ? $alel[0].' saja' : $huruf.' dan '.$kecil;

        return [
            'judul' => 'Gamet '.$tulis.': '.$benar,
            'teks' => $awalan." Gamet yang dapat dibentuk oleh induk bergenotipe {$tulis} adalah ...",
            'pernyataan' => $awalan." Gamet yang dapat dibentuk induk bergenotipe {$tulis} adalah %s.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_filter([
                    $huruf.' dan '.$kecil,
                    $huruf.' saja',
                    $kecil.' saja',
                    $huruf.$huruf.' dan '.$kecil.$kecil,
                ], fn ($t) => $t !== $benar))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $tunggal
                ? "Genotipe {$tulis} hanya memuat satu macam alel, sehingga seluruh gametnya "
                    .'sama, yaitu '.$alel[0].'. Gamet memuat satu alel, bukan sepasang.'
                : "Genotipe {$tulis} memuat dua macam alel. Saat pembentukan gamet, pasangan alel "
                    ."memisah sehingga terbentuk dua macam gamet: {$huruf} dan {$kecil}.",
        ];
    }

    /**
     * Pengecoh perbandingan: diambil dari daftar perbandingan yang lazim,
     * dengan urutan masuk dipertahankan — yang paling sering tertukar lebih
     * dahulu.
     *
     * @param  array<int, string>  $daftar
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanRasio(string $benar, array $daftar): array
    {
        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_merge([$benar], array_values(array_filter($daftar, fn ($t) => $t !== $benar))),
        );
    }

    /** Perbandingan yang sudah disederhanakan, mis. 2 : 2 menjadi 1 : 1. */
    protected static function rasio(array $hitung): string
    {
        $pembagi = max(1, (int) array_reduce($hitung, fn ($p, $n) => self::fpb((int) $p, (int) $n), 0));

        return implode(' : ', array_map(fn ($n) => (int) ($n / $pembagi), $hitung));
    }

    protected static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Papan Punnett: gamet induk pertama di tepi atas, gamet induk kedua di
     * tepi kiri, dan keturunannya di keempat kotak di dalamnya.
     */
    public static function svgPunnett(array $kolom, array $baris, array $sel, string $induk1, string $induk2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $petak = 62;
        $x0 = (Kanvas::LEBAR - 3 * $petak) / 2;
        $y0 = 78;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 36], "Persilangan {$induk1} × {$induk2}", [
            'ukuran' => 18, 'tebal' => true,
        ])->teksPx([Kanvas::LEBAR / 2, 58],
            "gamet {$induk1} di tepi atas, gamet {$induk2} di tepi kiri",
            ['ukuran' => 11, 'warna' => '#4b5563']);

        $tepi = ['sudut' => 0, 'ukuran' => 20, 'tebal' => true];

        // Sudut kiri atas dibiarkan kosong: yang dipertemukan di dalam kotak
        // adalah gamet pada tepi atas dan tepi kirinya.
        $kanvas->kotakPx([$x0 + $petak / 2, $y0 + $petak / 2], $petak, $petak, '×', '#eef1f4', $garis, $tepi);

        foreach (array_values($kolom) as $i => $g) {
            $kanvas->kotakPx([$x0 + ($i + 1.5) * $petak, $y0 + $petak / 2], $petak, $petak, $g, '#eef1f4', $garis, $tepi);
        }

        foreach (array_values($baris) as $j => $g) {
            $kanvas->kotakPx([$x0 + $petak / 2, $y0 + ($j + 1.5) * $petak], $petak, $petak, $g, '#eef1f4', $garis, $tepi);
        }

        foreach (array_values($sel) as $k => $g) {
            $kanvas->kotakPx([
                $x0 + (($k % 2) + 1.5) * $petak,
                $y0 + (intdiv($k, 2) + 1.5) * $petak,
            ], $petak, $petak, $g, $isi, $garis, $tepi);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, $y0 + 3 * $petak + 26],
            'keempat kotak di dalam = keturunan (F1)', ['ukuran' => 12, 'warna' => '#4b5563']);

        return $kanvas->render();
    }
}

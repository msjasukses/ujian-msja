<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template bidang koordinat: letak titik, kuadran, gradien, dan grafik fungsi.
 *
 * Bidangnya digambar berpetak lengkap dengan angka pada kedua sumbu, jadi
 * siswa benar-benar membaca koordinat dari gambar. Pengecohnya pun disusun
 * dari kekeliruan membaca itu: absis dan ordinat tertukar, atau tandanya
 * terbalik karena salah kuadran.
 */
class BidangKoordinat extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'koordinat' => 'Koordinat titik',
        'kuadran' => 'Kuadran letak titik',
        'gradien' => 'Gradien garis melalui dua titik',
        'nilai_fungsi' => 'Nilai fungsi dari grafik',
    ];

    public static function kode(): string
    {
        return 'bidang-koordinat';
    }

    public static function nama(): string
    {
        return 'Bidang Koordinat & Grafik Fungsi';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Koordinat titik, kuadran, gradien garis melalui dua titik, '
            .'dan membaca nilai fungsi dari grafiknya.';
    }

    public static function ikon(): string
    {
        return 'bi-graph-up';
    }

    public static function kataKunci(): array
    {
        return ['koordinat', 'kartesius', 'absis', 'ordinat', 'kuadran', 'gradien', 'grafik', 'fungsi'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'koordinat'),
            self::kolomAngka('x1', 'Titik A — absis (x)', 3, -10, 10, ['langkah' => 1]),
            self::kolomAngka('y1', 'Titik A — ordinat (y)', 4, -10, 10, ['langkah' => 1]),
            self::kolomAngka('x2', 'Titik B — absis (x)', -2, -10, 10, [
                'langkah' => 1, 'tampil' => ['tanya' => ['gradien']],
            ]),
            self::kolomAngka('y2', 'Titik B — ordinat (y)', -1, -10, 10, [
                'langkah' => 1, 'tampil' => ['tanya' => ['gradien']],
            ]),
            self::kolomAngka('m', 'Gradien garis (m)', 2, -10, 10, [
                'tampil' => ['tanya' => ['nilai_fungsi']],
            ]),
            self::kolomAngka('c', 'Konstanta garis (c)', -1, -10, 10, [
                'tampil' => ['tanya' => ['nilai_fungsi']],
            ]),
            self::kolomAngka('x_dicari', 'Nilai x yang ditanyakan', 3, -10, 10, [
                'langkah' => 1, 'tampil' => ['tanya' => ['nilai_fungsi']],
            ]),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') === 'kuadran'
            && ((int) ($isi['x1'] ?? 0) === 0 || (int) ($isi['y1'] ?? 0) === 0)) {
            return ['x1' => 'Titik yang tepat pada sumbu tidak berada di kuadran mana pun.'];
        }

        if (($isi['tanya'] ?? '') === 'gradien'
            && (int) ($isi['x1'] ?? 0) === (int) ($isi['x2'] ?? 0)) {
            return ['x2' => 'Dua titik dengan absis sama membentuk garis tegak yang gradiennya tidak terdefinisi.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');
        $x1 = (int) $isi['x1'];
        $y1 = (int) $isi['y1'];

        return match ($isi['tanya'] ?? 'koordinat') {
            'kuadran' => $this->kuadran($x1, $y1, $warnaIsi, $garis),
            'gradien' => $this->gradien($x1, $y1, (int) $isi['x2'], (int) $isi['y2'], $warnaIsi, $garis),
            'nilai_fungsi' => $this->nilaiFungsi((float) $isi['m'], (float) $isi['c'], (int) $isi['x_dicari'], $warnaIsi, $garis),
            default => $this->koordinat($x1, $y1, $warnaIsi, $garis),
        };
    }

    protected function koordinat(int $x, int $y, string $isi, string $garis): array
    {
        $benar = "({$x}, {$y})";

        return [
            'judul' => 'Koordinat '.$benar,
            'teks' => 'Perhatikan bidang koordinat berikut. Berapakah koordinat titik A?',
            'pernyataan' => 'Perhatikan bidang koordinat berikut. Koordinat titik A adalah %s.',
            'svg' => self::svg([['x' => $x, 'y' => $y, 'nama' => 'A']], null, $isi, $garis),
            'keterangan' => "Titik A pada ({$x}, {$y}).",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_values(array_unique([
                    $benar,
                    // Menukar absis dengan ordinat, atau membalik tandanya,
                    // adalah dua kekeliruan membaca koordinat yang paling umum.
                    "({$y}, {$x})",
                    '('.(-$x).", {$y})",
                    "({$x}, ".(-$y).')',
                ])),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => "Dari titik asal, titik A bergeser {$x} satuan mendatar (absis) dan {$y} "
                ."satuan tegak (ordinat), jadi koordinatnya {$benar}.",
        ];
    }

    protected function kuadran(int $x, int $y, string $isi, string $garis): array
    {
        $nomor = match (true) {
            $x > 0 && $y > 0 => 'I',
            $x < 0 && $y > 0 => 'II',
            $x < 0 && $y < 0 => 'III',
            default => 'IV',
        };

        return [
            'judul' => "Titik ({$x}, {$y}) di kuadran {$nomor}",
            'teks' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) terletak di kuadran ...",
            'pernyataan' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) terletak di kuadran %s.",
            'svg' => self::svg([['x' => $x, 'y' => $y, 'nama' => 'A']], null, $isi, $garis, true),
            'keterangan' => "Titik A pada ({$x}, {$y}).",
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n, 'urut' => $n],
                array_merge([$nomor], array_values(array_diff(['I', 'II', 'III', 'IV'], [$nomor]))),
            ),
            'kata_kunci' => $nomor,
            'pembahasan' => 'Absisnya '.($x > 0 ? 'positif' : 'negatif').' dan ordinatnya '
                .($y > 0 ? 'positif' : 'negatif').", jadi titik itu ada di kuadran {$nomor}.",
        ];
    }

    protected function gradien(int $x1, int $y1, int $x2, int $y2, string $isi, string $garis): array
    {
        $m = ($y2 - $y1) / ($x2 - $x1);
        $benar = Angka::id(round($m, 2));

        return [
            'judul' => 'Gradien '.$benar,
            'teks' => 'Perhatikan garis pada bidang koordinat berikut. Berapakah gradien garis itu?',
            'pernyataan' => 'Perhatikan garis pada bidang koordinat berikut. Gradien garis itu adalah %s.',
            'svg' => self::svg([
                ['x' => $x1, 'y' => $y1, 'nama' => 'A'],
                ['x' => $x2, 'y' => $y2, 'nama' => 'B'],
            ], ['dari' => [$x1, $y1], 'ke' => [$x2, $y2]], $isi, $garis),
            'keterangan' => "Garis melalui A ({$x1}, {$y1}) dan B ({$x2}, {$y2}).",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([round($m, 2)], self::saring(round($m, 2), [
                    // Membalik rumus menjadi Δx/Δy, atau salah tanda, adalah
                    // kekeliruan khas pada gradien.
                    $m === 0.0 ? 1 : round(1 / $m, 2), -$m, round($m + 1, 2), round($m - 1, 2),
                ], 2, false)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'm = (y₂ − y₁) ÷ (x₂ − x₁) = ('.$y2.' − '.$y1.') ÷ ('.$x2.' − '.$x1.') = '
                .($y2 - $y1).' ÷ '.($x2 - $x1).' = '.$benar.'.',
        ];
    }

    protected function nilaiFungsi(float $m, float $c, int $x, string $isi, string $garis): array
    {
        $y = $m * $x + $c;
        $benar = Angka::id(round($y, 2));
        $rumus = Angka::id($m).'x '.($c < 0 ? '−' : '+').' '.Angka::id(abs($c));

        return [
            'judul' => "f({$x}) = {$benar}",
            'teks' => "Perhatikan grafik fungsi berikut. Berapakah nilai fungsi untuk x = {$x}?",
            'pernyataan' => "Perhatikan grafik fungsi berikut. Nilai fungsi untuk x = {$x} adalah %s.",
            'svg' => self::svgGaris($m, $c, $x, $isi, $garis),
            'keterangan' => "Grafik garis lurus f(x) = {$rumus}.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([round($y, 2)], self::saring(round($y, 2), [
                    round($m * $x, 2), round($x + $c, 2), round($y + 1, 2), round(-$y, 2),
                ], 2, false)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => "f(x) = {$rumus}<br>f({$x}) = ".Angka::id($m).' × '.$x.' '
                .($c < 0 ? '−' : '+').' '.Angka::id(abs($c)).' = '.$benar.'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * @param  array<int, array{x: int, y: int, nama: string}>  $titik
     * @param  array{dari: array, ke: array}|null  $garisLurus
     */
    public static function svg(array $titik, ?array $garisLurus, string $isi, string $garis, bool $labelKuadran = false): string
    {
        $kanvas = new Kanvas;
        $batas = 6;

        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $sel = min((Kanvas::LEBAR - 60) / (2 * $batas), (Kanvas::TINGGI - 40) / (2 * $batas));

        $px = fn (float $x, float $y) => [$pusat[0] + $x * $sel, $pusat[1] - $y * $sel];

        for ($i = -$batas; $i <= $batas; $i++) {
            $kanvas->garis($px($i, -$batas), $px($i, $batas), '#dfe3e7', 1)
                ->garis($px(-$batas, $i), $px($batas, $i), '#dfe3e7', 1);
        }

        $kanvas->garis($px(-$batas, 0), $px($batas, 0), Kanvas::HITAM, 2)
            ->garis($px(0, -$batas), $px(0, $batas), Kanvas::HITAM, 2);

        for ($i = -$batas; $i <= $batas; $i++) {
            if ($i === 0) {
                continue;
            }

            $kanvas->teksPx([$px($i, 0)[0], $px($i, 0)[1] + 16], (string) $i, ['ukuran' => 11, 'warna' => '#4b5563'])
                ->teksPx([$px(0, $i)[0] - 10, $px(0, $i)[1] + 4], (string) $i, [
                    'anchor' => 'end', 'ukuran' => 11, 'warna' => '#4b5563',
                ]);
        }

        if ($labelKuadran) {
            foreach ([['I', 3, 3], ['II', -3, 3], ['III', -3, -3], ['IV', 3, -3]] as [$nama, $x, $y]) {
                $kanvas->teksPx($px($x, $y), $nama, ['ukuran' => 18, 'warna' => '#aab1b8', 'tebal' => true]);
            }
        }

        if ($garisLurus) {
            // Garis dipanjangkan melewati kedua titik supaya terbaca sebagai
            // garis, bukan sebagai ruas yang berhenti di titiknya.
            [$x1, $y1] = $garisLurus['dari'];
            [$x2, $y2] = $garisLurus['ke'];
            $m = ($y2 - $y1) / ($x2 - $x1);

            $kanvas->garis(
                $px(-$batas, $y1 + $m * (-$batas - $x1)),
                $px($batas, $y1 + $m * ($batas - $x1)),
                $garis,
                2.5,
            );
        }

        foreach ($titik as $t) {
            $p = $px($t['x'], $t['y']);
            $kanvas->lingkaran($p, 6, $isi, $garis, 2.5)
                ->teksPx([$p[0] + 12, $p[1] - 10], $t['nama'], [
                    'anchor' => 'start', 'ukuran' => 16, 'tebal' => true,
                ]);
        }

        return $kanvas->render();
    }

    public static function svgGaris(float $m, float $c, int $x, string $isi, string $garis): string
    {
        return self::svg(
            [['x' => 0, 'y' => (int) round($c), 'nama' => '']],
            ['dari' => [0, $c], 'ke' => [1, $m + $c]],
            $isi,
            $garis,
        );
    }
}

<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template transformasi geometri: translasi, refleksi, rotasi, dan dilatasi.
 *
 * Titik asal dan bayangannya digambar pada satu bidang koordinat, jadi
 * hubungan keduanya terlihat — bukan sekadar dihitung dengan rumus. Pada
 * soal "jenis transformasi", justru gambar itulah satu-satunya petunjuk.
 */
class TransformasiGeometri extends Template
{
    /** @var array<string, string> */
    public const JENIS = [
        'translasi' => 'Translasi (pergeseran)',
        'refleksi' => 'Refleksi (pencerminan)',
        'rotasi' => 'Rotasi (perputaran)',
        'dilatasi' => 'Dilatasi (perkalian)',
    ];

    /** @var array<string, string> */
    public const SUMBU = [
        'x' => 'sumbu X',
        'y' => 'sumbu Y',
        'asal' => 'titik asal O',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'bayangan' => 'Koordinat bayangan',
        'jenis' => 'Jenis transformasinya',
    ];

    public static function kode(): string
    {
        return 'transformasi-geometri';
    }

    public static function nama(): string
    {
        return 'Transformasi Geometri';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Translasi, refleksi, rotasi, dan dilatasi sebuah titik pada bidang '
            .'koordinat: koordinat bayangan atau jenis transformasinya.';
    }

    public static function ikon(): string
    {
        return 'bi-arrows-move';
    }

    public static function kataKunci(): array
    {
        return ['transformasi', 'translasi', 'refleksi', 'pencerminan', 'rotasi', 'dilatasi', 'bayangan'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'jenis_ubah',
                'label' => 'Jenis transformasi',
                'jenis' => 'pilihan',
                'pilihan' => self::JENIS,
                'awal' => 'refleksi',
            ],
            self::kolomAngka('x', 'Titik A — absis (x)', 3, -8, 8, ['langkah' => 1]),
            self::kolomAngka('y', 'Titik A — ordinat (y)', 2, -8, 8, ['langkah' => 1]),
            self::kolomAngka('a', 'Geser mendatar (a)', 2, -8, 8, [
                'langkah' => 1, 'tampil' => ['jenis_ubah' => ['translasi']],
            ]),
            self::kolomAngka('b', 'Geser tegak (b)', -3, -8, 8, [
                'langkah' => 1, 'tampil' => ['jenis_ubah' => ['translasi']],
            ]),
            [
                'nama' => 'sumbu',
                'label' => 'Dicerminkan terhadap',
                'jenis' => 'pilihan',
                'pilihan' => self::SUMBU,
                'awal' => 'x',
                'tampil' => ['jenis_ubah' => ['refleksi']],
            ],
            [
                'nama' => 'sudut',
                'label' => 'Diputar sejauh',
                'jenis' => 'pilihan',
                'pilihan' => ['90' => '90° berlawanan jarum jam', '180' => '180°', '270' => '270° berlawanan jarum jam'],
                'awal' => '90',
                'tampil' => ['jenis_ubah' => ['rotasi']],
            ],
            self::kolomAngka('k', 'Faktor dilatasi (k)', 2, -5, 5, [
                'tampil' => ['jenis_ubah' => ['dilatasi']],
            ]),
            self::kolomTanya(self::TANYA, 'bayangan'),
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['jenis_ubah'] ?? '') === 'dilatasi' && (float) ($isi['k'] ?? 0) === 0.0) {
            return ['k' => 'Faktor dilatasi 0 memetakan semua titik ke titik asal.'];
        }

        [$bx, $by] = self::bayangan($isi);

        if (abs($bx) > 9 || abs($by) > 9) {
            return ['x' => 'Bayangannya keluar dari bidang koordinat yang digambar.'];
        }

        return [];
    }

    /** @return array{0: float, 1: float} */
    public static function bayangan(array $isi): array
    {
        $x = (float) ($isi['x'] ?? 0);
        $y = (float) ($isi['y'] ?? 0);

        return match ($isi['jenis_ubah'] ?? 'refleksi') {
            'translasi' => [$x + (float) ($isi['a'] ?? 0), $y + (float) ($isi['b'] ?? 0)],
            'rotasi' => match ((string) ($isi['sudut'] ?? '90')) {
                '180' => [-$x, -$y],
                '270' => [$y, -$x],
                default => [-$y, $x],
            },
            'dilatasi' => [$x * (float) ($isi['k'] ?? 1), $y * (float) ($isi['k'] ?? 1)],
            default => match ($isi['sumbu'] ?? 'x') {
                'y' => [-$x, $y],
                'asal' => [-$x, -$y],
                default => [$x, -$y],
            },
        };
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $x = (int) $isi['x'];
        $y = (int) $isi['y'];
        [$bx, $by] = array_map('intval', self::bayangan($isi));

        $jenis = $isi['jenis_ubah'] ?? 'refleksi';
        $sebutan = self::sebutan($isi);
        $svg = self::svg($x, $y, $bx, $by, $warnaIsi, $garis);
        $keterangan = "Titik A ({$x}, {$y}) dan bayangannya A' ({$bx}, {$by}) pada bidang koordinat.";

        if (($isi['tanya'] ?? 'bayangan') === 'jenis') {
            $benar = self::JENIS[$jenis];

            return [
                'judul' => $benar,
                'teks' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) dipetakan menjadi "
                    ."A' ({$bx}, {$by}). Transformasi apakah yang terjadi?",
                'pernyataan' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) dipetakan menjadi "
                    ."A' ({$bx}, {$by}) oleh %s.",
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => $n, 'urut' => $n],
                    array_merge([$benar], array_values(array_diff(array_values(self::JENIS), [$benar]))),
                ),
                'kata_kunci' => $benar,
                'pembahasan' => "A ({$x}, {$y}) menjadi A' ({$bx}, {$by}), yaitu {$sebutan}.",
            ];
        }

        $benar = "({$bx}, {$by})";

        return [
            'judul' => "Bayangan {$benar}",
            'teks' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) dikenai {$sebutan}. "
                .'Berapakah koordinat bayangannya?',
            'pernyataan' => "Perhatikan bidang koordinat berikut. Titik A ({$x}, {$y}) dikenai {$sebutan}, "
                .'sehingga bayangannya %s.',
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_values(array_unique([
                    $benar,
                    // Hanya salah satu komponen yang diubah, atau keduanya
                    // tertukar — dua kekeliruan khas pada transformasi.
                    "({$by}, {$bx})",
                    '('.(-$bx).", {$by})",
                    "({$bx}, ".(-$by).')',
                    "({$x}, {$y})",
                ])),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($sebutan)." memetakan ({$x}, {$y}) menjadi {$benar}.",
        ];
    }

    protected static function sebutan(array $isi): string
    {
        return match ($isi['jenis_ubah'] ?? 'refleksi') {
            'translasi' => 'translasi sejauh ('.(int) $isi['a'].', '.(int) $isi['b'].')',
            'rotasi' => 'rotasi sejauh '.$isi['sudut'].'° berpusat di titik asal',
            'dilatasi' => 'dilatasi dengan faktor '.$isi['k'].' berpusat di titik asal',
            default => 'refleksi terhadap '.self::SUMBU[$isi['sumbu'] ?? 'x'],
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(int $x, int $y, int $bx, int $by, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $batas = max(5, abs($x), abs($y), abs($bx), abs($by));

        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $sel = min((Kanvas::LEBAR - 60) / (2 * $batas), (Kanvas::TINGGI - 40) / (2 * $batas));
        $px = fn (float $a, float $b) => [$pusat[0] + $a * $sel, $pusat[1] - $b * $sel];

        for ($i = -$batas; $i <= $batas; $i++) {
            $kanvas->garis($px($i, -$batas), $px($i, $batas), '#dfe3e7', 1)
                ->garis($px(-$batas, $i), $px($batas, $i), '#dfe3e7', 1);
        }

        $kanvas->garis($px(-$batas, 0), $px($batas, 0), Kanvas::HITAM, 2)
            ->garis($px(0, -$batas), $px(0, $batas), Kanvas::HITAM, 2);

        // Garis putus-putus dari titik ke bayangannya membuat perpindahannya
        // terbaca sebagai satu gerakan, bukan dua titik yang tak berhubungan.
        $kanvas->garis($px($x, $y), $px($bx, $by), $garis, 1.5, true);

        foreach ([[$x, $y, 'A', $isi], [$bx, $by, "A'", '#ffffff']] as [$a, $b, $nama, $warna]) {
            $p = $px($a, $b);
            $kanvas->lingkaran($p, 6, $warna, $garis, 2.5)
                ->teksPx([$p[0] + 12, $p[1] - 10], $nama, ['anchor' => 'start', 'ukuran' => 16, 'tebal' => true]);
        }

        return $kanvas->render();
    }
}

<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template garis bilangan: bilangan yang ditunjuk dan lompatan operasi.
 *
 * Garis bilangan dipakai dua kali di sekolah dengan maksud berbeda: sebagai
 * alat membaca letak bilangan (termasuk bilangan negatif, pecahan, dan
 * desimal), lalu sebagai alat memperagakan penjumlahan dan pengurangan
 * sebagai lompatan. Keduanya memakai gambar yang sama, jadi disatukan.
 */
class GarisBilangan extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'ditunjuk' => 'Bilangan yang ditunjuk panah',
        'lompatan' => 'Hasil lompatan',
        'pertidaksamaan' => 'Perbandingan dua titik',
    ];

    public static function kode(): string
    {
        return 'garis-bilangan';
    }

    public static function nama(): string
    {
        return 'Garis Bilangan';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Bilangan bulat, pecahan, dan desimal yang ditunjuk panah, lompatan '
            .'penjumlahan dan pengurangan, serta membandingkan dua titik.';
    }

    public static function ikon(): string
    {
        return 'bi-arrow-left-right';
    }

    public static function kataKunci(): array
    {
        return ['garis bilangan', 'bilangan bulat', 'negatif', 'lompatan', 'urutan', 'pertidaksamaan'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'ditunjuk'),
            self::kolomAngka('batas_bawah', 'Batas kiri', -5, -100, 100),
            self::kolomAngka('batas_atas', 'Batas kanan', 10, -100, 200),
            self::kolomAngka('langkah', 'Jarak antargaris', 1, 0.1, 50),
            self::kolomAngka('titik', 'Bilangan yang ditunjuk (titik A)', 3, -100, 200, [
                'tampil' => ['tanya' => ['ditunjuk', 'pertidaksamaan']],
            ]),
            self::kolomAngka('titik_2', 'Titik B', 7, -100, 200, [
                'tampil' => ['tanya' => ['pertidaksamaan']],
            ]),
            self::kolomAngka('awal', 'Mulai dari bilangan', 2, -100, 200, [
                'tampil' => ['tanya' => ['lompatan']],
            ]),
            self::kolomAngka('lompat', 'Lompat sejauh (minus untuk ke kiri)', 5, -100, 100, [
                'tampil' => ['tanya' => ['lompatan']],
            ]),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $bawah = (float) ($isi['batas_bawah'] ?? 0);
        $atas = (float) ($isi['batas_atas'] ?? 0);
        $langkah = (float) ($isi['langkah'] ?? 1);

        if ($atas <= $bawah) {
            return ['batas_atas' => 'Batas kanan harus lebih besar daripada batas kiri.'];
        }

        if (($atas - $bawah) / $langkah > 40) {
            return ['langkah' => 'Garisnya akan terlalu padat; perbesar jarak antargaris.'];
        }

        // Tiap bilangan yang akan ditandai harus muat di dalam garis yang
        // digambar; kalau tidak, penunjuknya jatuh di luar kanvas.
        $wajib = match ($isi['tanya'] ?? '') {
            'ditunjuk' => ['titik'],
            'pertidaksamaan' => ['titik', 'titik_2'],
            'lompatan' => ['awal'],
            default => [],
        };

        foreach ($wajib as $kolom) {
            $nilai = (float) ($isi[$kolom] ?? 0);

            if ($nilai < $bawah || $nilai > $atas) {
                return [$kolom => 'Bilangan itu berada di luar garis yang digambar.'];
            }
        }

        if (($isi['tanya'] ?? '') === 'lompatan') {
            $hasil = (float) $isi['awal'] + (float) $isi['lompat'];

            if ($hasil < $bawah || $hasil > $atas) {
                return ['lompat' => 'Hasil lompatannya keluar dari garis yang digambar.'];
            }
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $bawah = (float) $isi['batas_bawah'];
        $atas = (float) $isi['batas_atas'];
        $langkah = (float) $isi['langkah'];
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($isi['tanya'] ?? 'ditunjuk') {
            'lompatan' => $this->lompatan($bawah, $atas, $langkah, (float) $isi['awal'], (float) $isi['lompat'], $warnaIsi, $garis),
            'pertidaksamaan' => $this->pertidaksamaan($bawah, $atas, $langkah, (float) $isi['titik'], (float) $isi['titik_2'], $warnaIsi, $garis),
            default => $this->ditunjuk($bawah, $atas, $langkah, (float) $isi['titik'], $warnaIsi, $garis),
        };
    }

    protected function ditunjuk(float $bawah, float $atas, float $langkah, float $titik, string $isi, string $garis): array
    {
        return [
            'judul' => 'Garis bilangan: '.Angka::id($titik),
            'teks' => 'Perhatikan garis bilangan berikut. Bilangan berapakah yang ditunjuk anak panah?',
            'pernyataan' => 'Perhatikan garis bilangan berikut. Bilangan yang ditunjuk anak panah adalah %s.',
            'svg' => self::svg($bawah, $atas, $langkah, [['nilai' => $titik, 'label' => '?']], [], $isi, $garis),
            'keterangan' => 'Garis bilangan '.Angka::id($bawah).' sampai '.Angka::id($atas)
                .', panah menunjuk '.Angka::id($titik).'.',
            'pilihan' => self::pilihanAngka($titik, [
                $titik + $langkah, $titik - $langkah, -$titik, $titik + 2 * $langkah,
            ]),
            'kata_kunci' => Angka::id($titik),
            'pembahasan' => 'Dihitung dari '.Angka::id($bawah).' dengan jarak antargaris '
                .Angka::id($langkah).', panah berhenti pada '.Angka::id($titik).'.',
        ];
    }

    protected function lompatan(float $bawah, float $atas, float $langkah, float $awal, float $lompat, string $isi, string $garis): array
    {
        $hasil = $awal + $lompat;
        $tanda = $lompat < 0 ? '−' : '+';
        $kalimat = Angka::id($awal).' '.$tanda.' '.Angka::id(abs($lompat)).' = '.Angka::id($hasil);

        return [
            'judul' => 'Lompatan '.$kalimat,
            'teks' => 'Perhatikan garis bilangan berikut. Berapakah hasil lompatan yang ditunjukkan anak panah itu?',
            'pernyataan' => 'Perhatikan garis bilangan berikut. Hasil lompatan yang ditunjukkan anak panah itu adalah %s.',
            'svg' => self::svg($bawah, $atas, $langkah, [], [['dari' => $awal, 'ke' => $hasil]], $isi, $garis),
            'keterangan' => 'Lompatan dari '.Angka::id($awal).' sejauh '.Angka::id(abs($lompat))
                .' ke '.($lompat < 0 ? 'kiri' : 'kanan').'.',
            'pilihan' => self::pilihanAngka($hasil, [
                $awal - $lompat, $hasil + $langkah, $hasil - $langkah, $awal,
            ]),
            'kata_kunci' => Angka::id($hasil),
            'pembahasan' => 'Lompatan ke '.($lompat < 0 ? 'kiri berarti dikurangi' : 'kanan berarti ditambah')
                .', jadi '.$kalimat.'.',
        ];
    }

    protected function pertidaksamaan(float $bawah, float $atas, float $langkah, float $a, float $b, string $isi, string $garis): array
    {
        $benar = $a < $b ? Angka::id($a).' < '.Angka::id($b) : ($a > $b
            ? Angka::id($a).' > '.Angka::id($b)
            : Angka::id($a).' = '.Angka::id($b));

        // Keempat bentuk pernyataan yang mungkin ditulis siswa, termasuk yang
        // membalik urutan kedua titiknya.
        $kandidat = array_unique([
            $benar,
            Angka::id($a).' > '.Angka::id($b),
            Angka::id($a).' < '.Angka::id($b),
            Angka::id($b).' < '.Angka::id($a),
            Angka::id($a).' = '.Angka::id($b),
        ]);

        return [
            'judul' => 'Perbandingan '.$benar,
            'teks' => 'Perhatikan garis bilangan berikut. Pernyataan yang benar tentang kedua titik itu adalah ...',
            'pernyataan' => 'Perhatikan garis bilangan berikut. Tentang kedua titik itu berlaku %s.',
            'svg' => self::svg($bawah, $atas, $langkah, [
                ['nilai' => $a, 'label' => 'A'],
                ['nilai' => $b, 'label' => 'B'],
            ], [], $isi, $garis),
            'keterangan' => 'Titik A pada '.Angka::id($a).' dan titik B pada '.Angka::id($b).'.',
            'pilihan' => array_map(fn ($teks) => ['teks' => $teks, 'urut' => $teks], array_values($kandidat)),
            'kata_kunci' => $benar,
            'pembahasan' => 'Pada garis bilangan, bilangan yang letaknya lebih kiri selalu lebih kecil. '
                .'Titik A ada di '.Angka::id($a).' dan titik B di '.Angka::id($b).', jadi '.$benar.'.',
        ];
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanAngka(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat, 2, false)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * @param  array<int, array{nilai: float, label: string}>  $penunjuk
     * @param  array<int, array{dari: float, ke: float}>  $lompatan
     */
    public static function svg(float $bawah, float $atas, float $langkah, array $penunjuk, array $lompatan, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 40;
        $kanan = Kanvas::LEBAR - 40;
        $y = 190;

        $x = fn (float $nilai) => $kiri + ($nilai - $bawah) / ($atas - $bawah) * ($kanan - $kiri);

        // Garisnya sedikit melewati kedua batas, dengan mata panah di ujung —
        // garis bilangan memang tidak berhenti pada angka terakhir.
        $kanvas->garis([$kiri - 22, $y], [$kanan + 22, $y], Kanvas::HITAM, 2.5);

        foreach ([[$kiri - 22, -1], [$kanan + 22, 1]] as [$ujung, $arah]) {
            $kanvas->poligon([
                [$ujung + $arah * 10, $y],
                [$ujung - $arah * 4, $y - 6],
                [$ujung - $arah * 4, $y + 6],
            ], Kanvas::HITAM, Kanvas::HITAM, 1);
        }

        for ($n = $bawah; $n <= $atas + 0.0001; $n += $langkah) {
            $px = $x($n);
            $kanvas->garis([$px, $y - 8], [$px, $y + 8], Kanvas::HITAM, 1.8)
                ->teksPx([$px, $y + 28], Angka::id($n), ['ukuran' => 12]);
        }

        foreach ($penunjuk as $p) {
            $px = $x($p['nilai']);
            $kanvas->garis([$px, $y - 70], [$px, $y - 12], $garis, 3)
                ->poligon([[$px, $y - 4], [$px - 7, $y - 18], [$px + 7, $y - 18]], $garis, $garis, 1)
                ->teksPx([$px, $y - 80], $p['label'], ['ukuran' => 20, 'tebal' => true, 'warna' => $garis]);
        }

        foreach ($lompatan as $l) {
            $dari = $x($l['dari']);
            $ke = $x($l['ke']);
            $tinggi = 54;

            $kanvas->tambah('<path d="M '.round($dari, 1).' '.($y - 10).' Q '.round(($dari + $ke) / 2, 1)
                .' '.($y - 10 - $tinggi).' '.round($ke, 1).' '.($y - 10)
                .'" fill="none" stroke="'.$garis.'" stroke-width="3"/>');

            $arah = $ke >= $dari ? 1 : -1;
            $kanvas->poligon([
                [$ke, $y - 4],
                [$ke - $arah * 12, $y - 20],
                [$ke + $arah * 2, $y - 22],
            ], $garis, $garis, 1);

            $kanvas->lingkaran([$dari, $y], 5, $isi, $garis, 2)
                ->teksPx([($dari + $ke) / 2, $y - 10 - $tinggi / 2 - 8], 'lompat '
                    .Angka::id(abs($l['ke'] - $l['dari'])), ['ukuran' => 13, 'tebal' => true, 'warna' => $garis]);
        }

        return $kanvas->render();
    }
}

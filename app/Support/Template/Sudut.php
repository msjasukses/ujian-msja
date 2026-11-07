<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template sudut: membaca busur derajat, jenis sudut, dan sudut yang hilang.
 *
 * Busur derajat digambar lengkap dengan garis skalanya, jadi soal "berapa
 * besar sudut itu" benar-benar menuntut siswa membaca alat — bukan menebak
 * dari bentuk. Pada mode lainnya busur justru tidak digambar, karena yang
 * diuji bukan membaca melainkan menghitung.
 */
class Sudut extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'besar' => 'Besar sudut pada busur derajat',
        'jenis' => 'Jenis sudut',
        'pelurus' => 'Sudut pelurus (berpenyiku lurus)',
        'penyiku' => 'Sudut penyiku',
        'segitiga' => 'Sudut ketiga pada segitiga',
    ];

    /** @var array<string, array{0: int, 1: int}> batas tiap jenis sudut */
    public const JENIS = [
        'Sudut lancip' => [1, 89],
        'Sudut siku-siku' => [90, 90],
        'Sudut tumpul' => [91, 179],
        'Sudut lurus' => [180, 180],
        'Sudut refleks' => [181, 359],
    ];

    public static function kode(): string
    {
        return 'sudut';
    }

    public static function nama(): string
    {
        return 'Sudut';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Mengukur besar sudut dengan busur derajat, jenis sudut, sudut pelurus '
            .'dan penyiku, serta sudut pada segitiga.';
    }

    public static function ikon(): string
    {
        return 'bi-triangle';
    }

    public static function kataKunci(): array
    {
        return ['sudut', 'busur derajat', 'lancip', 'tumpul', 'siku-siku', 'pelurus', 'penyiku', 'derajat'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'besar'),
            self::kolomAngka('besar_sudut', 'Besar sudut (derajat)', 65, 1, 179, [
                'langkah' => 1,
                'tampil' => ['tanya' => ['besar', 'jenis', 'pelurus', 'penyiku']],
            ]),
            self::kolomAngka('sudut_1', 'Sudut pertama (derajat)', 50, 1, 178, [
                'langkah' => 1, 'tampil' => ['tanya' => ['segitiga']],
            ]),
            self::kolomAngka('sudut_2', 'Sudut kedua (derajat)', 60, 1, 178, [
                'langkah' => 1, 'tampil' => ['tanya' => ['segitiga']],
            ]),
            self::kolomWarna('jingga'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $tanya = $isi['tanya'] ?? '';

        if ($tanya === 'segitiga' && (int) ($isi['sudut_1'] ?? 0) + (int) ($isi['sudut_2'] ?? 0) >= 180) {
            return ['sudut_2' => 'Jumlah dua sudut pada segitiga harus kurang dari 180°.'];
        }

        if ($tanya === 'penyiku' && (int) ($isi['besar_sudut'] ?? 0) >= 90) {
            return ['besar_sudut' => 'Sudut penyiku hanya ada bila sudutnya kurang dari 90°.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $tanya = $isi['tanya'] ?? 'besar';
        $a = (int) ($isi['besar_sudut'] ?? 0);
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        return match ($tanya) {
            'jenis' => $this->jenisSudut($a, $warnaIsi, $garis),
            'pelurus', 'penyiku' => $this->pasangan($a, $tanya, $warnaIsi, $garis),
            'segitiga' => $this->segitiga((int) $isi['sudut_1'], (int) $isi['sudut_2'], $warnaIsi, $garis),
            default => $this->busur($a, $warnaIsi, $garis),
        };
    }

    protected function busur(int $a, string $isi, string $garis): array
    {
        return [
            'judul' => "Sudut {$a}°",
            'teks' => 'Perhatikan gambar berikut. Berapakah besar sudut yang ditunjukkan busur derajat itu?',
            'pernyataan' => 'Perhatikan gambar berikut. Besar sudut yang ditunjukkan busur derajat itu adalah %s.',
            'svg' => self::svgBusur($a, $isi, $garis),
            'keterangan' => "Busur derajat dengan sinar pada {$a}°.",
            'pilihan' => self::pilihanDerajat($a, [180 - $a, $a + 10, $a - 10, $a + 5]),
            'kata_kunci' => $a.'°',
            // Membaca skala luar padahal sinarnya diukur dari skala dalam
            // adalah kekeliruan paling sering pada busur derajat.
            'pembahasan' => "Sinar kedua berhenti pada garis {$a} pada skala yang dimulai dari 0 di sisi kanan, "
                ."jadi besar sudutnya {$a}°. Bila dibaca dari skala satunya akan terbaca ".(180 - $a).'°.',
        ];
    }

    protected function jenisSudut(int $a, string $isi, string $garis): array
    {
        $benar = self::namaJenis($a);

        return [
            'judul' => "Jenis sudut {$a}°",
            'teks' => "Perhatikan gambar sudut berikut yang besarnya {$a}°. Sudut itu termasuk jenis ...",
            'pernyataan' => "Perhatikan gambar sudut berikut yang besarnya {$a}°. Sudut itu termasuk %s.",
            'svg' => self::svgSudut($a, $a.'°', $isi, $garis),
            'keterangan' => "Sudut {$a}° antara dua sinar garis.",
            'pilihan' => array_map(
                fn ($nama) => ['teks' => $nama, 'urut' => self::JENIS[$nama][0]],
                array_merge([$benar], array_values(array_diff(array_keys(self::JENIS), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::aturanJenis($benar)." Karena besarnya {$a}°, sudut itu "
                .mb_strtolower($benar).'.',
        ];
    }

    protected function pasangan(int $a, string $tanya, string $isi, string $garis): array
    {
        $penuh = $tanya === 'pelurus' ? 180 : 90;
        $benar = $penuh - $a;
        $sebutan = $tanya === 'pelurus' ? 'pelurus' : 'penyiku';

        return [
            'judul' => "Sudut {$sebutan} dari {$a}°",
            'teks' => "Perhatikan gambar berikut. Besar sudut {$sebutan} dari sudut {$a}° adalah ...",
            'pernyataan' => "Perhatikan gambar berikut. Besar sudut {$sebutan} dari sudut {$a}° adalah %s.",
            'svg' => $tanya === 'pelurus'
                ? self::svgPelurus($a, $isi, $garis)
                : self::svgPenyiku($a, $isi, $garis),
            'keterangan' => "Dua sudut berdampingan yang jumlahnya {$penuh}°, salah satunya {$a}°.",
            'pilihan' => self::pilihanDerajat($benar, [$a, $penuh + $a, 360 - $a, $benar + 10]),
            'kata_kunci' => $benar.'°',
            'pembahasan' => "Dua sudut {$sebutan} berjumlah {$penuh}°, jadi sudut yang dicari = "
                ."{$penuh}° − {$a}° = {$benar}°.",
        ];
    }

    protected function segitiga(int $a, int $b, string $isi, string $garis): array
    {
        $c = 180 - $a - $b;

        return [
            'judul' => "Sudut ketiga {$c}°",
            'teks' => 'Perhatikan gambar segitiga berikut. Berapakah besar sudut yang belum diketahui?',
            'pernyataan' => 'Perhatikan gambar segitiga berikut. Besar sudut yang belum diketahui adalah %s.',
            'svg' => self::svgSegitiga($a, $b, $isi, $garis),
            'keterangan' => "Segitiga dengan dua sudut diketahui: {$a}° dan {$b}°.",
            'pilihan' => self::pilihanDerajat($c, [$a + $b, 90 - $c, $c + 10, 360 - $a - $b]),
            'kata_kunci' => $c.'°',
            'pembahasan' => "Jumlah sudut segitiga selalu 180°, jadi sudut ketiga = 180° − {$a}° − {$b}° = {$c}°.",
        ];
    }

    public static function namaJenis(int $a): string
    {
        foreach (self::JENIS as $nama => [$bawah, $atas]) {
            if ($a >= $bawah && $a <= $atas) {
                return $nama;
            }
        }

        return 'Sudut lancip';
    }

    protected static function aturanJenis(string $nama): string
    {
        return match ($nama) {
            'Sudut siku-siku' => 'Sudut siku-siku besarnya tepat 90°.',
            'Sudut tumpul' => 'Sudut tumpul besarnya antara 90° dan 180°.',
            'Sudut lurus' => 'Sudut lurus besarnya tepat 180°.',
            'Sudut refleks' => 'Sudut refleks besarnya lebih dari 180°.',
            default => 'Sudut lancip besarnya kurang dari 90°.',
        };
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanDerajat(int $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => ((int) $n).'°', 'urut' => $n],
            array_merge([$benar], self::saring($benar, array_merge($kandidat, [
                $benar + 20, $benar - 20, $benar + 30,
            ]), 0)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Titik pada jarak $r dari pusat busur, diukur dari sinar mendatar kanan. */
    protected static function arah(array $pusat, float $derajat, float $r): array
    {
        return [
            $pusat[0] + $r * cos(deg2rad($derajat)),
            $pusat[1] - $r * sin(deg2rad($derajat)),
        ];
    }

    public static function svgBusur(int $a, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [240, 258];
        $r = 160;

        // Badan busur: setengah lingkaran bening dengan tepi tegas.
        $kanvas->tambah('<path d="M '.($pusat[0] - $r).' '.$pusat[1].' A '.$r.' '.$r.' 0 0 1 '
            .($pusat[0] + $r).' '.$pusat[1].' Z" fill="'.$isi.'" fill-opacity="0.35" stroke="'
            .$garis.'" stroke-width="2"/>');

        for ($d = 0; $d <= 180; $d += 5) {
            $panjang = $d % 10 === 0 ? 14 : 8;
            $kanvas->garis(
                self::arah($pusat, $d, $r),
                self::arah($pusat, $d, $r - $panjang),
                $garis,
                $d % 10 === 0 ? 1.5 : 1,
            );

            if ($d % 30 === 0) {
                $kanvas->teksPx(self::arah($pusat, $d, $r - 28), (string) $d, ['ukuran' => 12, 'geser' => [0, 4]]);
            }
        }

        // Kedua sinar sudut; yang pertama berimpit dengan garis 0°.
        $kanvas->garis($pusat, self::arah($pusat, 0, $r + 36), Kanvas::HITAM, 3)
            ->garis($pusat, self::arah($pusat, $a, $r + 36), Kanvas::HITAM, 3)
            ->lingkaran($pusat, 4, Kanvas::HITAM, Kanvas::HITAM, 1);

        $kanvas->tambah('<path d="M '.implode(' ', self::arah($pusat, 0, 44))
            .' A 44 44 0 '.($a > 180 ? 1 : 0).' 0 '.implode(' ', self::arah($pusat, $a, 44))
            .'" fill="none" stroke="#b23a5c" stroke-width="2.5"/>');

        return $kanvas->render();
    }

    public static function svgSudut(int $a, string $label, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [150, 220];
        $r = 240;

        $kanvas->garis($pusat, self::arah($pusat, 0, $r), Kanvas::HITAM, 3)
            ->garis($pusat, self::arah($pusat, min($a, 179), $r * 0.8), Kanvas::HITAM, 3)
            ->lingkaran($pusat, 4, Kanvas::HITAM, Kanvas::HITAM, 1);

        $kanvas->tambah('<path d="M '.implode(' ', self::arah($pusat, 0, 50))
            .' A 50 50 0 0 0 '.implode(' ', self::arah($pusat, min($a, 179), 50))
            .'" fill="'.$isi.'" fill-opacity="0.5" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->teksPx(self::arah($pusat, min($a, 179) / 2, 78), $label, ['ukuran' => 16, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgPelurus(int $a, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [240, 200];
        $r = 200;

        $kanvas->garis(self::arah($pusat, 180, $r), self::arah($pusat, 0, $r), Kanvas::HITAM, 3)
            ->garis($pusat, self::arah($pusat, $a, $r * 0.85), Kanvas::HITAM, 3)
            ->lingkaran($pusat, 4, Kanvas::HITAM, Kanvas::HITAM, 1);

        $kanvas->tambah('<path d="M '.implode(' ', self::arah($pusat, 0, 48))
            .' A 48 48 0 0 0 '.implode(' ', self::arah($pusat, $a, 48))
            .'" fill="'.$isi.'" fill-opacity="0.5" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->teksPx(self::arah($pusat, $a / 2, 76), $a.'°', ['ukuran' => 16, 'tebal' => true])
            ->teksPx(self::arah($pusat, ($a + 180) / 2, 76), '?', ['ukuran' => 20, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgPenyiku(int $a, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [160, 250];
        $r = 230;

        $kanvas->garis($pusat, self::arah($pusat, 0, $r), Kanvas::HITAM, 3)
            ->garis($pusat, self::arah($pusat, 90, $r * 0.9), Kanvas::HITAM, 3)
            ->garis($pusat, self::arah($pusat, $a, $r * 0.95), Kanvas::HITAM, 3)
            ->siku($pusat, [1, 0], [0, -1], Kanvas::HITAM, 14)
            ->lingkaran($pusat, 4, Kanvas::HITAM, Kanvas::HITAM, 1);

        $kanvas->tambah('<path d="M '.implode(' ', self::arah($pusat, 0, 56))
            .' A 56 56 0 0 0 '.implode(' ', self::arah($pusat, $a, 56))
            .'" fill="'.$isi.'" fill-opacity="0.5" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->teksPx(self::arah($pusat, $a / 2, 84), $a.'°', ['ukuran' => 16, 'tebal' => true])
            ->teksPx(self::arah($pusat, ($a + 90) / 2, 84), '?', ['ukuran' => 20, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgSegitiga(int $a, int $b, string $isi, string $garis): string
    {
        $c = 180 - $a - $b;

        // Titik ketiga dicari dari kedua sudut yang diketahui, jadi segitiga
        // yang tergambar benar-benar bersudut seperti yang tertulis.
        $alas = 300;
        $A = [90, 250];
        $B = [$A[0] + $alas, $A[1]];
        $tinggi = $alas * tan(deg2rad($a)) * tan(deg2rad($b)) / (tan(deg2rad($a)) + tan(deg2rad($b)));
        $C = [$A[0] + $tinggi / tan(deg2rad($a)), $A[1] - $tinggi];

        $kanvas = new Kanvas;
        $kanvas->poligon([$A, $B, $C], $isi, $garis, 2.5);

        $kanvas->teksPx([$A[0] + 26, $A[1] - 12], $a.'°', ['ukuran' => 15, 'tebal' => true])
            ->teksPx([$B[0] - 26, $B[1] - 12], $b.'°', ['ukuran' => 15, 'tebal' => true])
            ->teksPx([$C[0], $C[1] + 28], '?', ['ukuran' => 20, 'tebal' => true]);

        return $kanvas->render();
    }
}

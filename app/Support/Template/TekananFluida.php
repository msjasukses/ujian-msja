<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template tekanan & fluida.
 *
 * Lima keadaan yang semuanya memakai gagasan yang sama — gaya dibagi luas —
 * tetapi tampak berbeda bagi siswa: balok di atas lantai, air pada kedalaman
 * tertentu, dongkrak hidrolik, benda yang terapung, dan pipa U berisi dua
 * zat cair. Gambarnya dibuat sesuai angka yang dimasukkan, termasuk tinggi
 * kolom zat cair pada pipa U, sehingga gambar tidak pernah berlawanan
 * dengan jawabannya.
 */
class TekananFluida extends Template
{
    /** Percepatan gravitasi yang dipakai di sekolah. */
    public const GRAVITASI = 10;

    /** @var array<string, string> */
    public const TANYA = [
        'zat_padat' => 'Tekanan zat padat',
        'hidrostatis' => 'Tekanan hidrostatis',
        'hidrolik' => 'Gaya pada dongkrak hidrolik',
        'apung' => 'Gaya apung (Archimedes)',
        'pipa_u' => 'Tinggi kolom pada pipa U',
    ];

    public static function kode(): string
    {
        return 'tekanan-fluida';
    }

    public static function nama(): string
    {
        return 'Tekanan & Fluida';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Tekanan zat padat, tekanan hidrostatis, dongkrak hidrolik, gaya apung, '
            .'dan pipa U berisi dua zat cair.';
    }

    public static function ikon(): string
    {
        return 'bi-droplet';
    }

    public static function kataKunci(): array
    {
        return ['tekanan', 'hidrostatis', 'pascal', 'archimedes', 'gaya apung', 'hidrolik',
            'bejana berhubungan', 'pipa u', 'massa jenis', 'fluida'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'zat_padat'),
            self::kolomAngka('gaya', 'Gaya / berat benda (N)', 240, 0.1, 100000, [
                'tampil' => ['tanya' => ['zat_padat']],
            ]),
            self::kolomAngka('luas', 'Luas bidang tekan (m²)', 0.4, 0.001, 100, [
                'langkah' => 0.001, 'tampil' => ['tanya' => ['zat_padat']],
            ]),
            self::kolomAngka('massa_jenis', 'Massa jenis zat cair (kg/m³)', 1000, 1, 20000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['hidrostatis', 'apung']],
            ]),
            self::kolomAngka('kedalaman', 'Kedalaman titik (m)', 2.5, 0.1, 500, [
                'tampil' => ['tanya' => ['hidrostatis']],
            ]),
            self::kolomAngka('volume', 'Volume benda tercelup (m³)', 0.02, 0.0001, 100, [
                'langkah' => 0.0001, 'tampil' => ['tanya' => ['apung']],
            ]),
            self::kolomAngka('luas_1', 'Luas penampang kecil (cm²)', 20, 1, 10000, [
                'tampil' => ['tanya' => ['hidrolik']],
            ]),
            self::kolomAngka('luas_2', 'Luas penampang besar (cm²)', 500, 1, 100000, [
                'tampil' => ['tanya' => ['hidrolik']],
            ]),
            self::kolomAngka('gaya_1', 'Gaya pada penampang kecil (N)', 40, 0.1, 100000, [
                'tampil' => ['tanya' => ['hidrolik']],
            ]),
            self::kolomAngka('rho_1', 'Massa jenis zat cair 1 (kg/m³)', 800, 1, 20000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['pipa_u']],
            ]),
            self::kolomAngka('tinggi_1', 'Tinggi kolom zat cair 1 (cm)', 10, 0.1, 100, [
                'tampil' => ['tanya' => ['pipa_u']],
            ]),
            self::kolomAngka('rho_2', 'Massa jenis zat cair 2 (kg/m³)', 1000, 1, 20000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['pipa_u']],
            ]),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') === 'hidrolik'
            && (float) ($isi['luas_2'] ?? 0) <= (float) ($isi['luas_1'] ?? 0)) {
            return ['luas_2' => 'Penampang besar harus lebih luas daripada penampang kecil.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($isi['tanya'] ?? 'zat_padat') {
            'hidrostatis' => $this->hidrostatis($isi, $warnaIsi, $garis),
            'hidrolik' => $this->hidrolik($isi, $warnaIsi, $garis),
            'apung' => $this->apung($isi, $warnaIsi, $garis),
            'pipa_u' => $this->pipaU($isi, $warnaIsi, $garis),
            default => $this->zatPadat($isi, $warnaIsi, $garis),
        };
    }

    protected function zatPadat(array $isi, string $warnaIsi, string $garis): array
    {
        $f = (float) $isi['gaya'];
        $a = (float) $isi['luas'];
        $p = round($f / $a, 2);

        return [
            'judul' => 'Tekanan '.Angka::dengan($p, 'N/m²'),
            'teks' => 'Perhatikan gambar berikut. Sebuah balok menekan lantai dengan gaya '
                .Angka::dengan($f, 'N').' pada bidang seluas '.Angka::dengan($a, 'm²')
                .'. Berapakah tekanan balok itu pada lantai?',
            'pernyataan' => 'Perhatikan gambar berikut. Balok bergaya '.Angka::dengan($f, 'N')
                .' pada bidang seluas '.Angka::dengan($a, 'm²').' memberi tekanan %s.',
            'svg' => self::svgBalok($f, $a, $warnaIsi, $garis),
            'keterangan' => 'Balok di atas lantai dengan gaya tekan '.Angka::dengan($f, 'N')
                .' dan luas bidang '.Angka::dengan($a, 'm²').'.',
            'pilihan' => self::pilihan($p, 'N/m²', [
                // Mengalikan, bukan membagi, dan membalik pembagiannya.
                $f * $a, $a / $f, $p * 2, $p / 2,
            ]),
            'kata_kunci' => Angka::id($p),
            'pembahasan' => 'P = F ÷ A = '.Angka::id($f).' ÷ '.Angka::id($a).' = '
                .Angka::dengan($p, 'N/m²').' (pascal).',
        ];
    }

    protected function hidrostatis(array $isi, string $warnaIsi, string $garis): array
    {
        $rho = (float) $isi['massa_jenis'];
        $h = (float) $isi['kedalaman'];
        $p = round($rho * self::GRAVITASI * $h, 2);

        return [
            'judul' => 'Tekanan hidrostatis '.Angka::dengan($p, 'N/m²'),
            'teks' => 'Perhatikan gambar berikut. Sebuah titik berada '.Angka::dengan($h, 'm')
                .' di bawah permukaan zat cair bermassa jenis '.Angka::dengan($rho, 'kg/m³')
                .'. Berapakah tekanan hidrostatis pada titik itu? (g = 10 m/s²)',
            'pernyataan' => 'Perhatikan gambar berikut. Pada titik '.Angka::dengan($h, 'm')
                .' di bawah permukaan zat cair bermassa jenis '.Angka::dengan($rho, 'kg/m³')
                .', tekanan hidrostatisnya %s.',
            'svg' => self::svgBejana($h, $warnaIsi, $garis),
            'keterangan' => 'Bejana berisi zat cair dengan titik ukur pada kedalaman '
                .Angka::dengan($h, 'm').'.',
            'pilihan' => self::pilihan($p, 'N/m²', [
                // Lupa mengalikan g, atau memakai tinggi bejana alih-alih
                // kedalaman titiknya.
                $rho * $h, $rho * self::GRAVITASI, $p * 2, $p / 10,
            ]),
            'kata_kunci' => Angka::id($p),
            'pembahasan' => 'P = ρ × g × h = '.Angka::id($rho).' × 10 × '.Angka::id($h).' = '
                .Angka::dengan($p, 'N/m²').'.',
        ];
    }

    protected function hidrolik(array $isi, string $warnaIsi, string $garis): array
    {
        $a1 = (float) $isi['luas_1'];
        $a2 = (float) $isi['luas_2'];
        $f1 = (float) $isi['gaya_1'];
        $f2 = round($f1 * $a2 / $a1, 2);

        return [
            'judul' => 'Gaya angkat '.Angka::dengan($f2, 'N'),
            'teks' => 'Perhatikan gambar dongkrak hidrolik berikut. Penampang kecil seluas '
                .Angka::dengan($a1, 'cm²').' ditekan dengan gaya '.Angka::dengan($f1, 'N')
                .'. Berapakah gaya yang dihasilkan pada penampang besar seluas '
                .Angka::dengan($a2, 'cm²').'?',
            'pernyataan' => 'Perhatikan gambar dongkrak hidrolik berikut. Gaya '.Angka::dengan($f1, 'N')
                .' pada penampang '.Angka::dengan($a1, 'cm²').' menghasilkan gaya %s pada penampang '
                .Angka::dengan($a2, 'cm²').'.',
            'svg' => self::svgHidrolik($a1, $a2, $f1, $warnaIsi, $garis),
            'keterangan' => 'Dongkrak hidrolik dengan penampang '.Angka::id($a1).' cm² dan '
                .Angka::id($a2).' cm².',
            'pilihan' => self::pilihan($f2, 'N', [
                // Membalik perbandingan luasnya adalah kekeliruan pokok.
                round($f1 * $a1 / $a2, 2), $f1 * $a2, $f1, $f2 / 2,
            ]),
            'kata_kunci' => Angka::id($f2),
            'pembahasan' => 'Tekanan pada kedua penampang sama: F₁ ÷ A₁ = F₂ ÷ A₂.<br>F₂ = F₁ × A₂ ÷ A₁ = '
                .Angka::id($f1).' × '.Angka::id($a2).' ÷ '.Angka::id($a1).' = '.Angka::dengan($f2, 'N').'.',
        ];
    }

    protected function apung(array $isi, string $warnaIsi, string $garis): array
    {
        $rho = (float) $isi['massa_jenis'];
        $v = (float) $isi['volume'];
        $fa = round($rho * self::GRAVITASI * $v, 2);

        return [
            'judul' => 'Gaya apung '.Angka::dengan($fa, 'N'),
            'teks' => 'Perhatikan gambar berikut. Sebuah benda tercelup seluruhnya dengan volume '
                .Angka::dengan($v, 'm³').' di dalam zat cair bermassa jenis '
                .Angka::dengan($rho, 'kg/m³').'. Berapakah gaya apung yang dialaminya? (g = 10 m/s²)',
            'pernyataan' => 'Perhatikan gambar berikut. Benda bervolume '.Angka::dengan($v, 'm³')
                .' yang tercelup dalam zat cair bermassa jenis '.Angka::dengan($rho, 'kg/m³')
                .' mengalami gaya apung %s.',
            'svg' => self::svgApung($warnaIsi, $garis),
            'keterangan' => 'Benda tercelup seluruhnya di dalam zat cair, dengan gaya apung ke atas.',
            'pilihan' => self::pilihan($fa, 'N', [
                $rho * $v, $rho * self::GRAVITASI, $fa * 2, $fa / 10,
            ]),
            'kata_kunci' => Angka::id($fa),
            'pembahasan' => 'Fₐ = ρ × g × V = '.Angka::id($rho).' × 10 × '.Angka::id($v).' = '
                .Angka::dengan($fa, 'N').'.',
        ];
    }

    protected function pipaU(array $isi, string $warnaIsi, string $garis): array
    {
        $rho1 = (float) $isi['rho_1'];
        $h1 = (float) $isi['tinggi_1'];
        $rho2 = (float) $isi['rho_2'];
        $h2 = round($rho1 * $h1 / $rho2, 2);

        return [
            'judul' => 'Tinggi kolom '.Angka::dengan($h2, 'cm'),
            'teks' => 'Perhatikan gambar pipa U berikut. Kaki kiri berisi zat cair bermassa jenis '
                .Angka::dengan($rho1, 'kg/m³').' setinggi '.Angka::dengan($h1, 'cm')
                .', kaki kanan berisi zat cair bermassa jenis '.Angka::dengan($rho2, 'kg/m³')
                .'. Berapakah tinggi kolom zat cair di kaki kanan?',
            'pernyataan' => 'Perhatikan gambar pipa U berikut. Tinggi kolom zat cair di kaki kanan adalah %s.',
            'svg' => self::svgPipaU($h1, $h2, $warnaIsi, $garis),
            'keterangan' => 'Pipa U dengan kolom '.Angka::dengan($h1, 'cm').' di kiri dan '
                .Angka::dengan($h2, 'cm').' di kanan.',
            'pilihan' => self::pilihan($h2, 'cm', [
                round($rho2 * $h1 / $rho1, 2), $h1, $h2 * 2, $h2 / 2,
            ]),
            'kata_kunci' => Angka::id($h2),
            'pembahasan' => 'Tekanan di bidang batas sama besar: ρ₁ × h₁ = ρ₂ × h₂.<br>h₂ = '
                .Angka::id($rho1).' × '.Angka::id($h1).' ÷ '.Angka::id($rho2).' = '
                .Angka::dengan($h2, 'cm').'.',
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

    public static function svgBalok(float $f, float $a, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 230;

        $kanvas->garis([30, $tanah], [Kanvas::LEBAR - 30, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="160" y="'.($tanah - 90).'" width="160" height="90" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>');

        // Panah gaya menekan ke bawah, digambar dari atas balok.
        $kanvas->garis([240, 60], [240, $tanah - 94], Kanvas::HITAM, 3)
            ->poligon([[240, $tanah - 86], [232, $tanah - 104], [248, $tanah - 104]],
                Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([252, 76], 'F = '.Angka::dengan($f, 'N'), [
                'anchor' => 'start', 'ukuran' => 15, 'tebal' => true,
            ]);

        $kanvas->teksPx([240, $tanah + 26], 'luas bidang tekan A = '.Angka::dengan($a, 'm²'), ['ukuran' => 14]);

        return $kanvas->render();
    }

    public static function svgBejana(float $h, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 120;
        $lebar = 240;
        $atas = 50;
        $dasar = 250;

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.$atas.'" width="'.$lebar.'" height="'.($dasar - $atas)
            .'" fill="'.$isi.'" fill-opacity="0.6" stroke="'.Kanvas::HITAM.'" stroke-width="3"/>');

        // Titik ukur ditaruh di tengah kedalaman supaya panahnya muat.
        $titikY = $atas + 130;

        $kanvas->garis([$kiri - 44, $atas], [$kiri - 44, $titikY], Kanvas::HITAM, 1.5, true)
            ->garis([$kiri - 52, $atas], [$kiri + 8, $atas], Kanvas::HITAM, 1.2)
            ->garis([$kiri - 52, $titikY], [$kiri + 8, $titikY], Kanvas::HITAM, 1.2)
            ->teksPx([$kiri - 52, ($atas + $titikY) / 2 + 5], 'h = '.Angka::dengan($h, 'm'), [
                'anchor' => 'end', 'ukuran' => 15, 'tebal' => true,
            ])
            ->lingkaran([$kiri + $lebar / 2, $titikY], 7, '#b23a5c', Kanvas::HITAM, 2)
            ->teksPx([$kiri + $lebar / 2 + 16, $titikY + 5], 'titik P', [
                'anchor' => 'start', 'ukuran' => 14,
            ]);

        return $kanvas->render();
    }

    public static function svgHidrolik(float $a1, float $a2, float $f1, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        // Lebar kedua tabung dibuat sebanding dengan akar luasnya, jadi
        // perbedaan penampang terlihat masuk akal.
        $lebar1 = max(26, min(70, 24 * sqrt($a1 / $a1)));
        $lebar2 = max($lebar1 + 20, min(170, $lebar1 * sqrt($a2 / $a1)));

        $dasar = 240;
        $x1 = 110;
        $x2 = 330;

        $kanvas->tambah('<path d="M '.($x1 - $lebar1 / 2).' 120 L '.($x1 - $lebar1 / 2).' '.($dasar - 30)
            .' Q '.($x1 - $lebar1 / 2).' '.$dasar.' '.($x1 + $lebar1 / 2 + 10).' '.$dasar
            .' L '.($x2 - $lebar2 / 2 - 10).' '.$dasar.' Q '.($x2 - $lebar2 / 2).' '.$dasar.' '
            .($x2 - $lebar2 / 2).' '.($dasar - 30).' L '.($x2 - $lebar2 / 2).' 100 L '
            .($x2 + $lebar2 / 2).' 100 L '.($x2 + $lebar2 / 2).' '.$dasar.' L '
            .($x1 + $lebar1 / 2).' '.$dasar.' L '.($x1 + $lebar1 / 2).' 120 Z" fill="'.$isi
            .'" fill-opacity="0.6" stroke="'.Kanvas::HITAM.'" stroke-width="2.5"/>');

        foreach ([[$x1, $lebar1, 120, 'A₁', $a1], [$x2, $lebar2, 100, 'A₂', $a2]] as [$x, $lebar, $y, $nama, $luas]) {
            $kanvas->tambah('<rect x="'.round($x - $lebar / 2, 1).'" y="'.($y - 14).'" width="'.round($lebar, 1)
                .'" height="14" fill="#9aa2aa" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>')
                ->teksPx([$x, $y + 24], $nama.' = '.Angka::id($luas).' cm²', ['ukuran' => 13, 'tebal' => true]);
        }

        $kanvas->garis([$x1, 42], [$x1, 100], Kanvas::HITAM, 3)
            ->poligon([[$x1, 108], [$x1 - 8, 92], [$x1 + 8, 92]], Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([$x1, 34], 'F₁ = '.Angka::dengan($f1, 'N'), ['ukuran' => 14, 'tebal' => true])
            ->teksPx([$x2, 72], 'F₂ = ?', ['ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgApung(string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 90;
        $lebar = 300;
        $atas = 60;
        $dasar = 250;

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.$atas.'" width="'.$lebar.'" height="'.($dasar - $atas)
            .'" fill="'.$isi.'" fill-opacity="0.55" stroke="'.Kanvas::HITAM.'" stroke-width="3"/>')
            ->tambah('<rect x="200" y="140" width="90" height="70" fill="#8d6e63" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5"/>');

        $kanvas->garis([245, 130], [245, 70], Kanvas::HITAM, 3)
            ->poligon([[245, 60], [236, 78], [254, 78]], Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([258, 76], 'Fₐ', ['anchor' => 'start', 'ukuran' => 17, 'tebal' => true])
            ->teksPx([245, 236], 'benda tercelup seluruhnya', ['ukuran' => 13, 'warna' => '#37404a']);

        return $kanvas->render();
    }

    public static function svgPipaU(float $h1, float $h2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 150;
        $kanan = 330;
        $lebarPipa = 44;
        $dasar = 250;

        // Tinggi gambar diskalakan dari kolom tertinggi supaya keduanya muat
        // dan perbandingannya tetap jujur.
        $skala = 150 / max($h1, $h2);

        $kanvas->tambah('<path d="M '.($kiri - $lebarPipa / 2).' 50 L '.($kiri - $lebarPipa / 2).' '.($dasar - 20)
            .' Q '.($kiri - $lebarPipa / 2).' '.$dasar.' '.($kiri + $lebarPipa / 2).' '.$dasar
            .' L '.($kanan - $lebarPipa / 2).' '.$dasar.' Q '.($kanan + $lebarPipa / 2).' '.$dasar.' '
            .($kanan + $lebarPipa / 2).' '.($dasar - 20).' L '.($kanan + $lebarPipa / 2).' 50" '
            .'fill="none" stroke="'.Kanvas::HITAM.'" stroke-width="3"/>');

        foreach ([[$kiri, $h1, $isi, 'h₁'], [$kanan, $h2, '#d9a441', 'h₂']] as [$x, $h, $warna, $nama]) {
            $tinggi = $h * $skala;

            $kanvas->tambah('<rect x="'.round($x - $lebarPipa / 2 + 2, 1).'" y="'.round($dasar - $tinggi, 1)
                .'" width="'.($lebarPipa - 4).'" height="'.round($tinggi, 1).'" fill="'.$warna
                .'" fill-opacity="0.75"/>')
                ->teksPx([$x, $dasar - $tinggi - 12], $nama, ['ukuran' => 15, 'tebal' => true]);
        }

        $kanvas->tambah('<rect x="'.($kiri + $lebarPipa / 2).'" y="'.($dasar - 4).'" width="'
            .($kanan - $kiri - $lebarPipa).'" height="4" fill="#d9a441" fill-opacity="0.75"/>')
            ->teksPx([Kanvas::LEBAR / 2, $dasar + 28], 'bidang batas kedua zat cair', [
                'ukuran' => 12, 'warna' => '#4b5563',
            ]);

        return $kanvas->render();
    }
}

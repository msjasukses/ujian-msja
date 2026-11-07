<?php

namespace App\Support\Template;

use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template struktur atom: model Bohr beserta konfigurasi elektronnya.
 *
 * Gambar kulit dan elektronnya tidak digambar per unsur melainkan dihitung
 * dari nomor atomnya, sehingga lambang unsur, banyak partikel, konfigurasi,
 * golongan, dan periode mustahil berselisih dengan yang tampak di gambar.
 * Menambah unsur cukup menuliskan nomor atom dan nomor massanya.
 */
class StrukturAtom extends Template
{
    /**
     * Unsur beserta nomor atom (z) dan nomor massanya (a), dibatasi sampai
     * kalsium — di atas itu pengisian kulitnya tidak lagi mengikuti pola
     * 2-8-8 yang dipakai di tingkat sekolah.
     *
     * @var array<string, array{nama: string, z: int, a: int}>
     */
    public const ATOM = [
        'H' => ['nama' => 'Hidrogen', 'z' => 1, 'a' => 1],
        'He' => ['nama' => 'Helium', 'z' => 2, 'a' => 4],
        'Li' => ['nama' => 'Litium', 'z' => 3, 'a' => 7],
        'Be' => ['nama' => 'Berilium', 'z' => 4, 'a' => 9],
        'B' => ['nama' => 'Boron', 'z' => 5, 'a' => 11],
        'C' => ['nama' => 'Karbon', 'z' => 6, 'a' => 12],
        'N' => ['nama' => 'Nitrogen', 'z' => 7, 'a' => 14],
        'O' => ['nama' => 'Oksigen', 'z' => 8, 'a' => 16],
        'F' => ['nama' => 'Fluorin', 'z' => 9, 'a' => 19],
        'Ne' => ['nama' => 'Neon', 'z' => 10, 'a' => 20],
        'Na' => ['nama' => 'Natrium', 'z' => 11, 'a' => 23],
        'Mg' => ['nama' => 'Magnesium', 'z' => 12, 'a' => 24],
        'Al' => ['nama' => 'Aluminium', 'z' => 13, 'a' => 27],
        'Si' => ['nama' => 'Silikon', 'z' => 14, 'a' => 28],
        'P' => ['nama' => 'Fosforus', 'z' => 15, 'a' => 31],
        'S' => ['nama' => 'Belerang', 'z' => 16, 'a' => 32],
        'Cl' => ['nama' => 'Klorin', 'z' => 17, 'a' => 35],
        'Ar' => ['nama' => 'Argon', 'z' => 18, 'a' => 40],
        'K' => ['nama' => 'Kalium', 'z' => 19, 'a' => 39],
        'Ca' => ['nama' => 'Kalsium', 'z' => 20, 'a' => 40],
    ];

    /** Daya tampung tiap kulit menurut pola yang dipakai di sekolah. */
    public const MUAT = [2, 8, 8, 2];

    /** @var array<int, string> */
    public const GOLONGAN = [1 => 'IA', 2 => 'IIA', 3 => 'IIIA', 4 => 'IVA',
        5 => 'VA', 6 => 'VIA', 7 => 'VIIA', 8 => 'VIIIA'];

    /** @var array<int, string> */
    public const KULIT = ['K', 'L', 'M', 'N'];

    /** @var array<string, string> */
    public const TANYA = [
        'partikel' => 'Banyak proton, elektron, dan neutron',
        'konfigurasi' => 'Konfigurasi elektron',
        'valensi' => 'Elektron valensi',
        'golongan' => 'Golongan dan periode',
        'ion' => 'Banyak elektron setelah menjadi ion',
    ];

    public static function kode(): string
    {
        return 'struktur-atom';
    }

    public static function nama(): string
    {
        return 'Struktur Atom';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Model atom Bohr dua puluh unsur pertama: banyak partikel penyusun, '
            .'konfigurasi elektron, elektron valensi, golongan, periode, dan ion.';
    }

    public static function ikon(): string
    {
        return 'bi-record-circle';
    }

    public static function kataKunci(): array
    {
        return ['atom', 'struktur atom', 'bohr', 'proton', 'elektron', 'neutron',
            'nomor atom', 'nomor massa', 'konfigurasi elektron', 'kulit', 'valensi',
            'golongan', 'periode', 'ion', 'kation', 'anion', 'unsur'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'unsur',
                'label' => 'Unsur',
                'jenis' => 'pilihan',
                // array_combine, bukan array_map dua larik: array_map yang
                // diberi lebih dari satu larik selalu menomori ulang hasilnya,
                // dan lambang unsurnya — yang dipakai sebagai nilai pilihan —
                // ikut hilang.
                'pilihan' => array_combine(array_keys(self::ATOM), array_map(
                    fn ($a, $l) => $a['nama'].' ('.$l.'), nomor atom '.$a['z'],
                    self::ATOM, array_keys(self::ATOM),
                )),
                'awal' => 'Na',
            ],
            self::kolomTanya(self::TANYA, 'partikel'),
            self::kolomAngka('muatan', 'Muatan ionnya', 1, -3, 3, [
                'langkah' => 1,
                'tampil' => ['tanya' => ['ion']],
                'bantuan' => 'Positif untuk kation (melepas elektron), negatif untuk anion.',
            ]),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') !== 'ion') {
            return [];
        }

        $muatan = (int) ($isi['muatan'] ?? 0);

        if ($muatan === 0) {
            return ['muatan' => 'Muatan nol berarti atomnya netral, bukan ion.'];
        }

        $z = (self::ATOM[$isi['unsur'] ?? ''] ?? self::ATOM['Na'])['z'];

        if ($z - $muatan < 0) {
            return ['muatan' => 'Unsur itu hanya punya '.$z.' elektron, tidak cukup untuk dilepas sebanyak itu.'];
        }

        return [];
    }

    /** Banyak elektron pada tiap kulit, dari kulit K ke luar. */
    public static function konfigurasi(int $elektron): array
    {
        $kulit = [];

        foreach (self::MUAT as $muat) {
            if ($elektron <= 0) {
                break;
            }

            $kulit[] = min($muat, $elektron);
            $elektron -= min($muat, $elektron);
        }

        return $kulit;
    }

    /** Golongan utama sebuah unsur dari konfigurasi elektronnya. */
    public static function golongan(array $kulit): string
    {
        // Helium menyimpang: elektron valensinya dua, tetapi kulitnya sudah
        // penuh sehingga ia tergolong gas mulia, bukan golongan IIA.
        if ($kulit === [2]) {
            return 'VIIIA';
        }

        return self::GOLONGAN[end($kulit)] ?? 'VIIIA';
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $lambang = isset(self::ATOM[$isi['unsur'] ?? '']) ? $isi['unsur'] : 'Na';
        $atom = self::ATOM[$lambang];
        $z = $atom['z'];
        $neutron = $atom['a'] - $z;
        $kulit = self::konfigurasi($z);

        $bahan = [
            'svg' => self::svgBohr($lambang, $atom, $kulit, $warnaIsi, $garis),
            'keterangan' => 'Model atom Bohr '.$atom['nama'].' ('.$lambang.'), nomor atom '
                .$z.' dan nomor massa '.$atom['a'].'.',
        ];

        $awalan = 'Perhatikan gambar model atom '.$atom['nama'].' berikut.';

        return $bahan + match ($isi['tanya'] ?? 'partikel') {
            'konfigurasi' => $this->konfigurasiElektron($awalan, $atom, $z, $kulit),
            'valensi' => $this->valensi($awalan, $atom, $kulit),
            'golongan' => $this->golonganPeriode($awalan, $atom, $kulit),
            'ion' => $this->ion($awalan, $lambang, $atom, $z, $neutron, (int) ($isi['muatan'] ?? 1)),
            default => $this->partikel($awalan, $atom, $z, $neutron),
        };
    }

    protected function partikel(string $awalan, array $atom, int $z, int $neutron): array
    {
        $benar = "{$z}, {$z}, {$neutron}";

        return [
            'judul' => $atom['nama'].': '.$benar,
            'teks' => $awalan.' Banyaknya proton, elektron, dan neutron pada atom itu '
                .'berturut-turut adalah ...',
            'pernyataan' => $awalan.' Banyak proton, elektron, dan neutronnya berturut-turut adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya kekeliruan yang khas: neutron dikira sama
                // dengan nomor massa, atau urutan ketiganya tertukar.
                array_merge([$benar], array_values(array_diff(array_unique([
                    "{$z}, {$z}, {$atom['a']}",
                    "{$neutron}, {$z}, {$z}",
                    "{$z}, {$neutron}, {$z}",
                    "{$atom['a']}, {$z}, {$neutron}",
                    "{$z}, {$atom['a']}, {$neutron}",
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Nomor atom = banyak proton = '.$z.'. Atom netral, jadi banyak '
                .'elektronnya sama dengan protonnya = '.$z.'.<br>Neutron = nomor massa − nomor atom = '
                .$atom['a'].' − '.$z.' = '.$neutron.'.',
        ];
    }

    protected function konfigurasiElektron(string $awalan, array $atom, int $z, array $kulit): array
    {
        $benar = implode(', ', $kulit);

        return [
            'judul' => $atom['nama'].': '.$benar,
            'teks' => $awalan.' Konfigurasi elektron atom tersebut adalah ...',
            'pernyataan' => $awalan.' Konfigurasi elektron atom tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya konfigurasi unsur tetangga dan konfigurasi yang
                // kulitnya tidak diisi penuh lebih dahulu — dua kekeliruan
                // yang paling sering muncul.
                array_merge([$benar], array_values(array_diff(array_unique([
                    implode(', ', self::konfigurasi($z + 1)),
                    implode(', ', self::konfigurasi(max(1, $z - 1))),
                    implode(', ', self::salahIsi($z)),
                    implode(', ', self::konfigurasi($z + 2)),
                    implode(', ', self::konfigurasi(max(1, $z - 2))),
                    implode(', ', self::konfigurasi($z + 3)),
                    implode(', ', self::konfigurasi($z + 4)),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Elektron mengisi kulit dari dalam ke luar, dan kulit yang lebih dalam '
                .'diisi penuh lebih dahulu: kulit K menampung 2 elektron, kulit L dan M 8 elektron. '
                .'Dengan '.$z.' elektron, konfigurasinya menjadi '.$benar.'.',
        ];
    }

    /**
     * Konfigurasi keliru yang khas: kulit kedua dibatasi dua elektron seperti
     * kulit pertama, sehingga sisanya terdorong ke kulit berikutnya.
     */
    protected static function salahIsi(int $elektron): array
    {
        $kulit = [];

        foreach ([2, 2, 8, 8] as $muat) {
            if ($elektron <= 0) {
                break;
            }

            $kulit[] = min($muat, $elektron);
            $elektron -= min($muat, $elektron);
        }

        return $kulit;
    }

    protected function valensi(string $awalan, array $atom, array $kulit): array
    {
        $benar = (int) end($kulit);

        return [
            'judul' => $atom['nama'].': valensi '.$benar,
            'teks' => $awalan.' Berapakah elektron valensi atom tersebut?',
            'pernyataan' => $awalan.' Elektron valensi atom tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) $n, 'urut' => $n],
                array_merge([$benar], array_values(array_filter(
                    // Pengecohnya banyak kulit (tertukar dengan periode),
                    // elektron pada kulit sebelumnya, dan selisihnya terhadap
                    // delapan — angka yang dipakai saat menghitung ikatan.
                    array_unique([count($kulit), $kulit[count($kulit) - 2] ?? 8, 8 - $benar, $benar + 1, $benar - 1, 8]),
                    fn ($n) => $n >= 1 && $n !== $benar,
                ))),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => 'Elektron valensi adalah banyak elektron pada kulit terluar. '
                .'Konfigurasinya '.implode(', ', $kulit).', sehingga kulit terluar ('
                .self::KULIT[count($kulit) - 1].') berisi '.$benar.' elektron.',
        ];
    }

    protected function golonganPeriode(string $awalan, array $atom, array $kulit): array
    {
        $golongan = self::golongan($kulit);
        $valensi = (int) end($kulit);
        $periode = count($kulit);
        $benar = 'Golongan '.$golongan.', periode '.$periode;

        return [
            'judul' => $atom['nama'].': '.$benar,
            'teks' => $awalan.' Atom tersebut terletak pada ...',
            'pernyataan' => $awalan.' Atom tersebut terletak pada %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecoh utamanya golongan dan periode yang tertukar —
                // keduanya sama-sama dibaca dari konfigurasi yang sama.
                array_merge([$benar], array_values(array_diff(array_unique([
                    'Golongan '.self::GOLONGAN[min(8, $periode)].', periode '.$valensi,
                    'Golongan '.$golongan.', periode '.($periode + 1),
                    'Golongan '.self::GOLONGAN[min(8, $valensi + 1)].', periode '.$periode,
                    'Golongan '.$golongan.', periode '.max(1, $periode - 1),
                    'Golongan '.self::GOLONGAN[max(1, $valensi - 1)].', periode '.$periode,
                    'Golongan '.self::GOLONGAN[min(8, $valensi + 2)].', periode '.$periode,
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Konfigurasinya '.implode(', ', $kulit).'. Golongan utama dibaca dari '
                .'elektron valensinya dan periode dari banyak kulit yang terisi, sehingga atom ini '
                .'ada di '.mb_strtolower($benar).'.',
        ];
    }

    protected function ion(string $awalan, string $lambang, array $atom, int $z, int $neutron, int $muatan): array
    {
        // Kation melepas elektron sehingga elektronnya berkurang sebanyak
        // muatannya; anion menangkap elektron sehingga bertambah.
        $benar = $z - $muatan;
        $tulis = $lambang.'^'.($muatan > 0 ? $muatan.'+' : abs($muatan).'-');
        $ion = Rumus::html($tulis);

        return [
            'judul' => Rumus::polos($tulis).': '.$benar.' elektron',
            'teks' => $awalan." Bila atom itu membentuk ion {$ion}, berapakah banyak elektronnya?",
            'pernyataan' => $awalan." Ion {$ion} memiliki elektron sebanyak %s.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) $n, 'urut' => $n],
                array_merge([$benar], array_values(array_filter(
                    // Pengecoh utamanya arah yang terbalik: kation dikira
                    // menangkap elektron dan anion dikira melepasnya.
                    array_unique([$z + $muatan, $z, $neutron, $atom['a'] - $muatan,
                        $z - 2 * $muatan, $z + 2 * $muatan, $atom['a'], $neutron + $muatan]),
                    fn ($n) => $n >= 0 && $n !== $benar,
                ))),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => 'Atom netralnya punya '.$z.' elektron. '
                .($muatan > 0
                    ? 'Ion bermuatan +'.$muatan.' terbentuk karena atom melepas '.$muatan
                        .' elektron, sehingga elektronnya tinggal '.$z.' − '.$muatan.' = '.$benar.'.'
                    : 'Ion bermuatan '.$muatan.' terbentuk karena atom menangkap '.abs($muatan)
                        .' elektron, sehingga elektronnya menjadi '.$z.' + '.abs($muatan).' = '.$benar.'.')
                .'<br>Banyak protonnya tetap '.$z.'; yang berubah hanya elektronnya.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Model atom Bohr: inti bertuliskan banyak proton dan neutron,
     * dikelilingi kulit berisi titik elektron sebanyak konfigurasinya.
     *
     * @param  array<int, int>  $kulit
     */
    public static function svgBohr(string $lambang, array $atom, array $kulit, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $pusat = [240, 158];
        $jari = [46, 76, 106, 134];

        foreach ($kulit as $k => $banyak) {
            $kanvas->lingkaran($pusat, $jari[$k], 'none', '#b6bec7', 1.5);

            for ($i = 0; $i < $banyak; $i++) {
                $sudut = deg2rad(-90 + $i * 360 / $banyak);

                $kanvas->lingkaran([
                    $pusat[0] + $jari[$k] * cos($sudut),
                    $pusat[1] + $jari[$k] * sin($sudut),
                ], 6, $isi, $garis, 1.5);
            }

            // Nama kulitnya ditaruh di sisi kiri, di antara dua lingkaran,
            // supaya tidak pernah bertabrakan dengan titik elektron di atas.
            $kanvas->teksPx([$pusat[0] - $jari[$k] + 13, $pusat[1] - 4], self::KULIT[$k], [
                'ukuran' => 11, 'warna' => '#8a9199',
            ]);
        }

        $kanvas
            ->lingkaran($pusat, 26, '#fbcfd9', '#b23a5c', 2)
            ->teksPx([$pusat[0], $pusat[1] - 2], $atom['z'].'p', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([$pusat[0], $pusat[1] + 13], ($atom['a'] - $atom['z']).'n', ['ukuran' => 12]);

        // Lambang unsur beserta nomor massa dan nomor atomnya, ditulis
        // seperti di buku: nomor massa di kiri atas, nomor atom di kiri bawah.
        $kanvas
            ->teksPx([56, 42], (string) $atom['a'], ['ukuran' => 14, 'anchor' => 'end'])
            ->teksPx([56, 62], (string) $atom['z'], ['ukuran' => 14, 'anchor' => 'end'])
            ->teksPx([60, 58], $lambang, ['ukuran' => 30, 'tebal' => true, 'anchor' => 'start'])
            ->teksPx([62, 84], $atom['nama'], ['ukuran' => 12, 'warna' => '#4b5563', 'anchor' => 'start'])
            ->lingkaran([392, 50], 6, $isi, $garis, 1.5)
            ->teksPx([404, 55], '= elektron', ['ukuran' => 12, 'warna' => '#4b5563', 'anchor' => 'start']);

        return $kanvas->render();
    }
}

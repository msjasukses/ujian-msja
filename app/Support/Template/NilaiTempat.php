<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template nilai tempat: blok Dienes dan tabel nilai tempat.
 *
 * Blok Dienes memperlihatkan bahwa "2" pada bilangan 234 bukan dua melainkan
 * dua ratusan — dan itu terlihat dari besar bloknya, bukan dari hafalan.
 * Tabel nilai tempat dipakai untuk bilangan yang terlalu besar digambar
 * sebagai blok.
 */
class NilaiTempat extends Template
{
    /** @var array<string, string> */
    public const TAMPILAN = [
        'blok' => 'Blok Dienes',
        'tabel' => 'Tabel nilai tempat',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'bilangan' => 'Bilangan yang digambarkan',
        'nilai_angka' => 'Nilai suatu angka',
        'bentuk_panjang' => 'Bentuk panjangnya',
    ];

    /** @var array<int, string> nama tempat dari satuan ke atas */
    public const TEMPAT = ['satuan', 'puluhan', 'ratusan', 'ribuan'];

    public static function kode(): string
    {
        return 'nilai-tempat';
    }

    public static function nama(): string
    {
        return 'Nilai Tempat & Blok Dienes';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Bilangan digambarkan dengan blok Dienes atau tabel nilai tempat: '
            .'nilai tiap angka, bentuk panjang, dan membaca bilangannya.';
    }

    public static function ikon(): string
    {
        return 'bi-stack';
    }

    public static function kataKunci(): array
    {
        return ['nilai tempat', 'blok dienes', 'ribuan', 'ratusan', 'puluhan', 'satuan', 'bentuk panjang'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'bilangan'),
            self::kolomAngka('bilangan', 'Bilangan', 234, 1, 9999, ['langkah' => 1]),
            [
                'nama' => 'tampilan',
                'label' => 'Digambarkan sebagai',
                'jenis' => 'pilihan',
                'pilihan' => self::TAMPILAN,
                'awal' => 'blok',
                'lebar' => 6,
            ],
            [
                'nama' => 'tempat',
                'label' => 'Nilai angka pada tempat',
                'jenis' => 'pilihan',
                'pilihan' => ['ratusan' => 'Ratusan', 'puluhan' => 'Puluhan', 'satuan' => 'Satuan', 'ribuan' => 'Ribuan'],
                'awal' => 'ratusan',
                'lebar' => 6,
                'tampil' => ['tanya' => ['nilai_angka']],
            ],
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $bilangan = (int) ($isi['bilangan'] ?? 0);
        $tempat = array_search($isi['tempat'] ?? 'satuan', self::TEMPAT, true);

        if (($isi['tanya'] ?? '') === 'nilai_angka' && $bilangan < 10 ** $tempat) {
            return ['tempat' => 'Bilangan itu tidak punya angka pada tempat '.$isi['tempat'].'.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $bilangan = (int) $isi['bilangan'];
        $angka = self::angkaTiapTempat($bilangan);
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $svg = ($isi['tampilan'] ?? 'blok') === 'tabel'
            ? self::svgTabel($bilangan, $warnaIsi, $garis)
            : self::svgBlok($bilangan, $warnaIsi, $garis);

        $sebutan = ($isi['tampilan'] ?? 'blok') === 'tabel' ? 'tabel nilai tempat' : 'blok Dienes';
        $cerita = "Perhatikan gambar {$sebutan} berikut.";
        $panjang = self::bentukPanjang($bilangan);

        $keterangan = ucfirst($sebutan).' untuk bilangan '.$bilangan.': '
            .implode(', ', array_map(
                fn ($n, $i) => $n.' '.self::TEMPAT[$i],
                $angka,
                array_keys($angka),
            )).'.';

        return match ($isi['tanya'] ?? 'bilangan') {
            'nilai_angka' => (function () use ($isi, $bilangan, $angka, $cerita, $svg, $keterangan) {
                $tempat = $isi['tempat'] ?? 'ratusan';
                $urutan = (int) array_search($tempat, self::TEMPAT, true);
                $digit = $angka[$urutan] ?? 0;
                $nilai = $digit * 10 ** $urutan;

                return [
                    'judul' => "Nilai angka {$digit} pada {$bilangan}",
                    'teks' => $cerita." Berapakah nilai angka yang menempati tempat {$tempat} pada bilangan {$bilangan}?",
                    'pernyataan' => $cerita." Nilai angka yang menempati tempat {$tempat} pada bilangan {$bilangan} adalah %s.",
                    'svg' => $svg,
                    'keterangan' => $keterangan,
                    // Menyebut angkanya saja ("2") alih-alih nilainya ("200")
                    // adalah kekeliruan pokok pada materi nilai tempat.
                    'pilihan' => self::pilihanAngka($nilai, [$digit, $nilai * 10, $nilai / 10, $bilangan]),
                    'kata_kunci' => (string) $nilai,
                    'pembahasan' => "Angka {$digit} menempati tempat {$tempat}, jadi nilainya "
                        ."{$digit} × ".number_format(10 ** $urutan, 0, ',', '.')." = {$nilai}.",
                ];
            })(),
            'bentuk_panjang' => [
                'judul' => 'Bentuk panjang '.$bilangan,
                'teks' => $cerita." Bentuk panjang dari bilangan {$bilangan} adalah ...",
                'pernyataan' => $cerita." Bentuk panjang dari bilangan {$bilangan} adalah %s.",
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                    array_values(array_unique([
                        $panjang,
                        // Menuliskan angkanya saja, membaliknya, atau menukar
                        // nilai tempat antarangka — tiga kekeliruan yang
                        // semuanya terbaca jelas oleh guru.
                        self::bentukPanjang($bilangan, true),
                        self::bentukPanjang((int) strrev((string) $bilangan)),
                        implode(' + ', array_reverse(str_split((string) $bilangan))),
                        self::bentukPanjang($bilangan * 10),
                    ])),
                ),
                'kata_kunci' => $panjang,
                'pembahasan' => "Tiap angka ditulis menurut nilai tempatnya: {$bilangan} = {$panjang}.",
            ],
            default => [
                'judul' => 'Bilangan '.$bilangan,
                'teks' => $cerita.' Bilangan berapakah yang digambarkan?',
                'pernyataan' => $cerita.' Bilangan yang digambarkan adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => self::pilihanAngka($bilangan, [
                    (int) strrev((string) $bilangan), $bilangan + 100, $bilangan - 10, $bilangan + 1,
                ]),
                'kata_kunci' => (string) $bilangan,
                'pembahasan' => "Jumlahkan tiap nilai tempatnya: {$panjang} = {$bilangan}.",
            ],
        };
    }

    /**
     * Angka tiap tempat, dari satuan ke atas, supaya indeksnya sekaligus
     * menjadi pangkat sepuluhnya.
     *
     * @return array<int, int>
     */
    public static function angkaTiapTempat(int $bilangan): array
    {
        $angka = array_reverse(array_map('intval', str_split((string) $bilangan)));

        return $angka;
    }

    public static function bentukPanjang(int $bilangan, bool $keliru = false): string
    {
        $bagian = [];

        foreach (array_reverse(self::angkaTiapTempat($bilangan), true) as $urutan => $digit) {
            if ($digit === 0) {
                continue;
            }

            // Bentuk yang keliru menuliskan angkanya saja, bukan nilainya —
            // itulah pengecoh yang paling pantas untuk soal bentuk panjang.
            $bagian[] = $keliru ? (string) $digit : (string) ($digit * 10 ** $urutan);
        }

        return implode(' + ', $bagian);
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihanAngka(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
            array_merge([$benar], self::saring($benar, array_merge($kandidat, [
                $benar + 1, $benar * 2, $benar + 10,
            ]), 0)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgBlok(int $bilangan, string $isi, string $garis): string
    {
        $angka = self::angkaTiapTempat($bilangan);
        $kanvas = new Kanvas;

        $x = 24;
        $sel = 7;

        // Dari ribuan ke satuan, sehingga urutannya sama dengan cara menulis
        // bilangannya.
        foreach ([3, 2, 1, 0] as $urutan) {
            $banyak = $angka[$urutan] ?? 0;

            for ($i = 0; $i < $banyak; $i++) {
                $lebar = match ($urutan) {
                    3 => 10 * $sel, 2 => 10 * $sel, 1 => $sel, default => $sel,
                };
                $tinggi = match ($urutan) {
                    3 => 10 * $sel, 2 => 10 * $sel, 1 => 10 * $sel, default => $sel,
                };

                $y = 160 - $tinggi / 2;

                $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.$lebar
                    .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.5"/>');

                // Blok ribuan diberi garis serong supaya terbaca sebagai
                // tumpukan sepuluh plat ratusan, bukan satu plat yang sama.
                if ($urutan === 3) {
                    for ($g = 1; $g < 4; $g++) {
                        $kanvas->garis([$x + $g * 4, $y], [$x + $lebar, $y + $tinggi - $g * 4], $garis, 0.8);
                    }
                }

                if ($urutan >= 1) {
                    for ($g = 1; $g < 10; $g++) {
                        $kanvas->garis([$x, $y + $g * $sel], [$x + $lebar, $y + $g * $sel], $garis, 0.6);
                    }
                }

                if ($urutan >= 2) {
                    for ($g = 1; $g < 10; $g++) {
                        $kanvas->garis([$x + $g * $sel, $y], [$x + $g * $sel, $y + $tinggi], $garis, 0.6);
                    }
                }

                $x += $lebar + 6;
            }

            if ($banyak > 0) {
                $x += 14;
            }
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 272], 'ribuan · ratusan · puluhan · satuan', [
            'ukuran' => 13, 'warna' => '#4b5563',
        ]);

        return $kanvas->render();
    }

    public static function svgTabel(int $bilangan, string $isi, string $garis): string
    {
        $angka = self::angkaTiapTempat($bilangan);
        $judul = ['Ribuan', 'Ratusan', 'Puluhan', 'Satuan'];

        $kanvas = new Kanvas;

        $lebar = 96;
        $tinggi = 64;
        $kiri = (Kanvas::LEBAR - $lebar * 4) / 2;
        $atas = 90;

        foreach ($judul as $i => $nama) {
            $x = $kiri + $i * $lebar;
            $urutan = 3 - $i;

            $kanvas->tambah('<rect x="'.$x.'" y="'.$atas.'" width="'.$lebar.'" height="'.$tinggi
                .'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
                ->tambah('<rect x="'.$x.'" y="'.($atas + $tinggi).'" width="'.$lebar.'" height="'.$tinggi
                    .'" fill="#ffffff" stroke="'.$garis.'" stroke-width="2"/>');

            $kanvas->teksPx([$x + $lebar / 2, $atas + 40], $nama, ['ukuran' => 15, 'tebal' => true])
                ->teksPx([$x + $lebar / 2, $atas + $tinggi + 46], (string) ($angka[$urutan] ?? 0), [
                    'ukuran' => 30, 'tebal' => true,
                ]);
        }

        return $kanvas->render();
    }
}

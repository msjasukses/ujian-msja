<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template wujud zat & campuran.
 *
 * Seluruh gambarnya berupa model partikel dan alat sederhana yang tersusun
 * dari bentuk dasar, sehingga susunan partikel tiap wujud, golongan zat,
 * dan cara pemisahan campuran dapat dibangkitkan tanpa ilustrasi jadi.
 * Model partikelnya memakai sebaran tetap — gambar soal yang sama selalu
 * persis sama setiap kali dibuka.
 */
class WujudZat extends Template
{
    /** @var array<string, string> */
    public const WUJUD = ['padat' => 'Padat', 'cair' => 'Cair', 'gas' => 'Gas'];

    /**
     * Perubahan wujud beserta arah panahnya pada segitiga padat–cair–gas.
     *
     * @var array<string, array{nama: string, dari: string, ke: string, kalor: string}>
     */
    public const PERUBAHAN = [
        'mencair' => ['nama' => 'Mencair (melebur)', 'dari' => 'padat', 'ke' => 'cair', 'kalor' => 'menyerap'],
        'membeku' => ['nama' => 'Membeku', 'dari' => 'cair', 'ke' => 'padat', 'kalor' => 'melepas'],
        'menguap' => ['nama' => 'Menguap', 'dari' => 'cair', 'ke' => 'gas', 'kalor' => 'menyerap'],
        'mengembun' => ['nama' => 'Mengembun', 'dari' => 'gas', 'ke' => 'cair', 'kalor' => 'melepas'],
        'menyublim' => ['nama' => 'Menyublim', 'dari' => 'padat', 'ke' => 'gas', 'kalor' => 'menyerap'],
        'deposisi' => ['nama' => 'Mengkristal (deposisi)', 'dari' => 'gas', 'ke' => 'padat', 'kalor' => 'melepas'],
    ];

    /** @var array<string, string> */
    public const ZAT = [
        'unsur' => 'Unsur',
        'senyawa' => 'Senyawa',
        'campuran_unsur' => 'Campuran dua unsur',
        'campuran_senyawa' => 'Campuran dua senyawa',
    ];

    /**
     * Cara pemisahan campuran beserta dasar pemisahan dan contohnya.
     *
     * @var array<string, array{nama: string, dasar: string, contoh: string}>
     */
    public const PEMISAHAN = [
        'filtrasi' => [
            'nama' => 'Filtrasi (penyaringan)',
            'dasar' => 'perbedaan ukuran partikelnya',
            'contoh' => 'memisahkan pasir dari air',
        ],
        'destilasi' => [
            'nama' => 'Destilasi (penyulingan)',
            'dasar' => 'perbedaan titik didihnya',
            'contoh' => 'memisahkan air dari air laut',
        ],
        'kristalisasi' => [
            'nama' => 'Kristalisasi (penguapan)',
            'dasar' => 'perbedaan titik didih antara zat terlarut dan pelarutnya',
            'contoh' => 'membuat garam dari air laut',
        ],
        'kromatografi' => [
            'nama' => 'Kromatografi',
            'dasar' => 'perbedaan kecepatan merambat pada kertas',
            'contoh' => 'memisahkan warna penyusun tinta',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'susunan' => 'Gambar susunan partikel tiap wujud',
        'sifat' => 'Sifat partikel pada suatu wujud',
        'perubahan' => 'Nama perubahan wujud pada bagan',
        'klasifikasi' => 'Golongan zat dari model partikelnya',
        'pemisahan' => 'Cara pemisahan campuran pada gambar',
        'dasar' => 'Dasar pemisahan campurannya',
    ];

    public static function kode(): string
    {
        return 'wujud-zat';
    }

    public static function nama(): string
    {
        return 'Wujud Zat & Campuran';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Model partikel tiga wujud zat, bagan perubahan wujud, golongan '
            .'unsur–senyawa–campuran, serta alat pemisahan campuran.';
    }

    public static function ikon(): string
    {
        return 'bi-thermometer-snow';
    }

    public static function kataKunci(): array
    {
        return ['wujud zat', 'padat', 'cair', 'gas', 'partikel', 'perubahan wujud',
            'mencair', 'membeku', 'menguap', 'mengembun', 'menyublim', 'unsur', 'senyawa',
            'campuran', 'homogen', 'heterogen', 'filtrasi', 'destilasi', 'kristalisasi',
            'kromatografi', 'pemisahan campuran'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'susunan'),
            [
                'nama' => 'wujud',
                'label' => 'Wujud pada kotak P',
                'jenis' => 'pilihan',
                'pilihan' => self::WUJUD,
                'awal' => 'padat',
                'tampil' => ['tanya' => ['susunan', 'sifat']],
            ],
            [
                'nama' => 'perubahan',
                'label' => 'Perubahan yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($p) => $p['nama'], self::PERUBAHAN),
                'awal' => 'menyublim',
                'tampil' => ['tanya' => ['perubahan']],
            ],
            [
                'nama' => 'zat',
                'label' => 'Zat yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => self::ZAT,
                'awal' => 'senyawa',
                'tampil' => ['tanya' => ['klasifikasi']],
            ],
            [
                'nama' => 'cara',
                'label' => 'Alat yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($p) => $p['nama'], self::PEMISAHAN),
                'awal' => 'filtrasi',
                'tampil' => ['tanya' => ['pemisahan', 'dasar']],
            ],
            self::kolomWarna('biru'),
        ];
    }

    /**
     * Urutan wujud pada ketiga kotak, diputar sesuai wujud yang dipilih guru
     * untuk kotak P. Tanpa pemutaran itu jawabannya selalu "P, Q, R" dan
     * butirnya terjawab tanpa melihat gambar.
     *
     * @return array<int, string>
     */
    public static function urutanKotak(string $awal): array
    {
        $wujud = array_keys(self::WUJUD);
        $mulai = max(0, array_search($awal, $wujud, true));

        return [$wujud[$mulai], $wujud[($mulai + 2) % 3], $wujud[($mulai + 1) % 3]];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($isi['tanya'] ?? 'susunan') {
            'sifat' => $this->sifat($isi['wujud'] ?? 'padat', $warnaIsi, $garis),
            'perubahan' => $this->perubahan($isi['perubahan'] ?? 'menyublim', $warnaIsi, $garis),
            'klasifikasi' => $this->klasifikasi($isi['zat'] ?? 'senyawa', $warnaIsi, $garis),
            'pemisahan', 'dasar' => $this->pemisahan(
                $isi['cara'] ?? 'filtrasi', ($isi['tanya'] ?? '') === 'dasar', $warnaIsi, $garis,
            ),
            default => $this->susunan($isi['wujud'] ?? 'padat', $warnaIsi, $garis),
        };
    }

    protected function susunan(string $awal, string $warnaIsi, string $garis): array
    {
        $urutan = self::urutanKotak($awal);
        $huruf = ['P', 'Q', 'R'];

        // Jawabannya ditulis sebagai urutan huruf untuk padat, cair, gas.
        $benar = implode(', ', array_map(
            fn ($w) => $huruf[array_search($w, $urutan, true)],
            array_keys(self::WUJUD),
        ));

        return [
            'judul' => 'Padat, cair, gas = '.$benar,
            'teks' => 'Perhatikan model susunan partikel tiga zat berikut. Kotak yang menunjukkan '
                .'zat padat, zat cair, dan gas berturut-turut adalah ...',
            'pernyataan' => 'Pada model susunan partikel berikut, zat padat, zat cair, dan gas '
                .'berturut-turut ditunjukkan kotak %s.',
            'svg' => self::svgSusunan($urutan, $warnaIsi, $garis),
            'keterangan' => 'Tiga kotak model partikel: P '.$urutan[0].', Q '.$urutan[1]
                .', R '.$urutan[2].'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya urutan huruf lain yang mungkin — semuanya
                // memakai ketiga huruf, jadi tidak ada yang bisa dicoret
                // hanya karena bentuknya ganjil.
                array_merge([$benar], array_values(array_diff([
                    'P, Q, R', 'P, R, Q', 'Q, P, R', 'Q, R, P', 'R, P, Q', 'R, Q, P',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Partikel zat padat tersusun rapat dan teratur, partikel zat cair '
                .'rapat tetapi tidak teratur, sedangkan partikel gas berjauhan dan tersebar. '
                .'Dari gambar: P = '.$urutan[0].', Q = '.$urutan[1].', R = '.$urutan[2]
                .', sehingga urutan padat, cair, gas adalah '.$benar.'.',
        ];
    }

    protected function sifat(string $awal, string $warnaIsi, string $garis): array
    {
        $urutan = self::urutanKotak($awal);
        $huruf = ['P', 'Q', 'R'];
        $ke = $huruf[0];

        $benar = match ($urutan[0]) {
            'padat' => 'Partikelnya rapat dan tersusun teratur',
            'cair' => 'Partikelnya rapat tetapi susunannya tidak teratur',
            default => 'Partikelnya berjauhan dan bergerak bebas',
        };

        return [
            'judul' => 'Sifat partikel '.$urutan[0],
            'teks' => 'Perhatikan model susunan partikel tiga zat berikut. Pernyataan yang tepat '
                ."mengenai partikel pada kotak {$ke} adalah ...",
            'pernyataan' => "Pada model susunan partikel berikut, partikel pada kotak {$ke} %s.",
            'svg' => self::svgSusunan($urutan, $warnaIsi, $garis),
            'keterangan' => 'Tiga kotak model partikel: P '.$urutan[0].', Q '.$urutan[1]
                .', R '.$urutan[2].'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Partikelnya rapat dan tersusun teratur',
                    'Partikelnya rapat tetapi susunannya tidak teratur',
                    'Partikelnya berjauhan dan bergerak bebas',
                    'Partikelnya berjauhan tetapi tersusun teratur',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Kotak '.$ke.' menunjukkan zat '.$urutan[0].'. '.$benar
                .', sehingga zat '.$urutan[0].' '
                .match ($urutan[0]) {
                    'padat' => 'bentuk dan volumenya tetap.',
                    'cair' => 'volumenya tetap tetapi bentuknya mengikuti wadahnya.',
                    default => 'bentuk maupun volumenya mengikuti wadahnya.',
                },
        ];
    }

    protected function perubahan(string $kunci, string $warnaIsi, string $garis): array
    {
        $ubah = self::PERUBAHAN[$kunci] ?? self::PERUBAHAN['menyublim'];
        $benar = $ubah['nama'];

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan bagan perubahan wujud berikut. Perubahan yang ditandai huruf X '
                .'disebut ...',
            'pernyataan' => 'Pada bagan perubahan wujud berikut, perubahan yang ditandai huruf X '
                .'disebut %s.',
            'svg' => self::svgPerubahan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Bagan perubahan wujud; panah dari '.$ubah['dari'].' ke '
                .$ubah['ke'].' ditandai X.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecoh pertamanya perubahan yang arahnya berlawanan —
                // itulah pasangan yang paling sering tertukar.
                array_merge([$benar], array_values(array_diff(array_unique(array_merge(
                    [self::kebalikan($kunci)],
                    array_column(self::PERUBAHAN, 'nama'),
                )), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Panah itu menunjukkan perubahan dari wujud '.$ubah['dari'].' menjadi '
                .$ubah['ke'].', yang disebut '.mb_strtolower($benar).'. Perubahan ini '
                .$ubah['kalor'].' kalor.',
        ];
    }

    /** Nama perubahan wujud yang arahnya berlawanan. */
    protected static function kebalikan(string $kunci): string
    {
        $ubah = self::PERUBAHAN[$kunci] ?? self::PERUBAHAN['menyublim'];

        foreach (self::PERUBAHAN as $lain) {
            if ($lain['dari'] === $ubah['ke'] && $lain['ke'] === $ubah['dari']) {
                return $lain['nama'];
            }
        }

        return $ubah['nama'];
    }

    protected function klasifikasi(string $kunci, string $warnaIsi, string $garis): array
    {
        $benar = self::ZAT[$kunci] ?? self::ZAT['senyawa'];

        return [
            'judul' => 'Model partikel: '.$benar,
            'teks' => 'Perhatikan model partikel berikut. Gambar itu menunjukkan ...',
            'pernyataan' => 'Model partikel berikut menunjukkan %s.',
            'svg' => self::svgKlasifikasi($kunci, $warnaIsi, $garis),
            'keterangan' => 'Model partikel sebuah zat: '.mb_strtolower($benar).'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_values(self::ZAT), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Unsur tersusun dari satu jenis atom saja, senyawa dari dua jenis atom '
                .'atau lebih yang terikat menjadi satu molekul, dan campuran memuat lebih dari satu '
                .'jenis partikel yang tidak terikat satu sama lain.<br>'
                .match ($kunci) {
                    'unsur' => 'Pada gambar hanya ada satu jenis bulatan yang berdiri sendiri, jadi zat itu unsur.',
                    'senyawa' => 'Pada gambar semua partikelnya sama dan tiap partikel tersusun dari dua '
                        .'jenis bulatan yang terikat, jadi zat itu senyawa.',
                    'campuran_unsur' => 'Pada gambar ada dua jenis bulatan yang sama-sama berdiri sendiri '
                        .'dan tidak terikat, jadi zat itu campuran dua unsur.',
                    default => 'Pada gambar ada dua jenis molekul yang berbeda dan tidak saling terikat, '
                        .'jadi zat itu campuran dua senyawa.',
                },
        ];
    }

    protected function pemisahan(string $kunci, bool $tanyaDasar, string $warnaIsi, string $garis): array
    {
        $cara = self::PEMISAHAN[$kunci] ?? self::PEMISAHAN['filtrasi'];
        $benar = $tanyaDasar ? ucfirst($cara['dasar']) : $cara['nama'];

        return [
            'judul' => $cara['nama'].($tanyaDasar ? ': dasarnya' : ''),
            'teks' => 'Perhatikan gambar alat pemisahan campuran berikut. '
                .($tanyaDasar
                    ? 'Pemisahan dengan alat itu didasarkan pada ...'
                    : 'Cara pemisahan yang digambarkan adalah ...'),
            'pernyataan' => 'Pada gambar alat pemisahan campuran berikut, '
                .($tanyaDasar ? 'pemisahannya didasarkan pada %s.' : 'cara pemisahannya adalah %s.'),
            'svg' => self::svgPemisahan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Alat '.mb_strtolower($cara['nama']).', misalnya untuk '
                .$cara['contoh'].'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                array_merge(
                    [$tanyaDasar ? $cara['dasar'] : $cara['nama']],
                    array_values(array_diff(
                        array_column(self::PEMISAHAN, $tanyaDasar ? 'dasar' : 'nama'),
                        [$tanyaDasar ? $cara['dasar'] : $cara['nama']],
                    )),
                ),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($cara['nama']).' memisahkan campuran berdasarkan '
                .$cara['dasar'].', misalnya untuk '.$cara['contoh'].'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Satu kotak model partikel menurut wujudnya: padat tersusun teratur dan
     * rapat, cair rapat tetapi tak beraturan dan mengendap ke bawah, gas
     * sedikit dan tersebar ke seluruh ruang.
     */
    protected static function partikelWujud(Kanvas $kanvas, float $x, float $y, float $lebar, float $tinggi, string $wujud, string $isi, string $garis): Kanvas
    {
        // Ukuran bulatannya sama untuk ketiga wujud: yang membedakan ketiganya
        // adalah susunan dan jaraknya, bukan besar partikelnya.
        $jari = 12;

        if ($wujud === 'padat') {
            $jarak = 30;
            $kiri = $x + ($lebar - 3 * $jarak) / 2;
            $atas = $y + ($tinggi - 3 * $jarak) / 2;

            for ($b = 0; $b <= 3; $b++) {
                for ($k = 0; $k <= 3; $k++) {
                    $kanvas->lingkaran([$kiri + $k * $jarak, $atas + $b * $jarak], $jari, $isi, $garis, 1.8);
                }
            }

            return $kanvas;
        }

        // Zat cair memakai partikel sebanyak zat padat, hanya tak beraturan
        // dan terkumpul di bagian bawah wadahnya; gas jauh lebih sedikit dan
        // tersebar ke seluruh ruang.
        [$banyak, $atas, $tingginya] = $wujud === 'cair'
            ? [14, $y + $tinggi * 0.3, $tinggi * 0.62]
            : [6, $y + 14, $tinggi - 28];

        foreach (Kanvas::sebaran($banyak, $x + 14, $atas, $lebar - 28, $tingginya) as $titik) {
            $kanvas->lingkaran($titik, $jari, $isi, $garis, 1.8);
        }

        return $kanvas;
    }

    /** Tiga kotak model partikel berlabel P, Q, dan R. */
    public static function svgSusunan(array $urutan, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $lebar = 142;
        $tinggi = 164;
        $jarak = 14;
        $x0 = (Kanvas::LEBAR - 3 * $lebar - 2 * $jarak) / 2;

        foreach (['P', 'Q', 'R'] as $i => $huruf) {
            $x = $x0 + $i * ($lebar + $jarak);

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="56" width="'.$lebar.'" height="'.$tinggi
                .'" rx="8" fill="#f8fafb" stroke="'.Kanvas::HITAM.'" stroke-width="2.5"/>');

            self::partikelWujud($kanvas, $x, 56, $lebar, $tinggi, $urutan[$i], $isi, $garis);

            $kanvas->teksPx([$x + $lebar / 2, 246], $huruf, ['ukuran' => 20, 'tebal' => true]);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 36], 'Model susunan partikel tiga zat', [
            'ukuran' => 15, 'tebal' => true,
        ])->teksPx([Kanvas::LEBAR / 2, 278], 'tiap bulatan mewakili satu partikel zat', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }

    /** Segitiga padat–cair–gas dengan enam panah perubahan wujud. */
    public static function svgPerubahan(string $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $letak = [
            'padat' => [104, 212],
            'cair' => [376, 212],
            'gas' => [240, 70],
        ];

        [$lebar, $tinggi] = [128, 50];

        // Panah digambar lebih dahulu supaya pangkalnya tertutup kotak, dan
        // tiap pasangan arah digeser sedikit agar keduanya terlihat terpisah.
        foreach (self::PERUBAHAN as $kunci => $ubah) {
            $ini = $kunci === $ditandai;
            $dari = $letak[$ubah['dari']];
            $ke = $letak[$ubah['ke']];

            $arah = Kanvas::searah([$ke[0] - $dari[0], $ke[1] - $dari[1]]);
            $geser = [-$arah[1] * 13, $arah[0] * 13];

            // Pangkas dari pusat kotak yang sebenarnya, baru geser hasilnya.
            // Memangkas dari titik yang sudah tergeser membuat panah berhenti
            // terlalu jauh dari kotaknya pada pasangan yang menyerong.
            $tepi = Kanvas::tepiKotak($arah, $lebar / 2 + 6, $tinggi / 2 + 6);

            $kanvas->panahPx(
                [$dari[0] + $arah[0] * $tepi + $geser[0], $dari[1] + $arah[1] * $tepi + $geser[1]],
                [$ke[0] - $arah[0] * $tepi + $geser[0], $ke[1] - $arah[1] * $tepi + $geser[1]],
                $ini ? $garis : '#9aa1a9', $ini ? 3.5 : 1.8,
            );

            if ($ini) {
                $tengah = [
                    ($dari[0] + $ke[0]) / 2 + $geser[0] * 2.4,
                    ($dari[1] + $ke[1]) / 2 + $geser[1] * 2.4,
                ];

                $kanvas->lingkaran($tengah, 15, '#ffffff', $garis, 2.5)
                    ->teksPx([$tengah[0], $tengah[1] + 8], 'X', ['ukuran' => 22, 'tebal' => true]);
            }
        }

        foreach ($letak as $wujud => $titik) {
            $kanvas->kotakPx($titik, $lebar, $tinggi, self::WUJUD[$wujud], $isi, $garis, [
                'ukuran' => 18, 'tebal' => true,
            ]);
        }

        return $kanvas->render();
    }

    /** Model partikel sebuah zat: unsur, senyawa, atau campuran keduanya. */
    public static function svgKlasifikasi(string $jenis, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas->tambah('<rect x="62" y="52" width="356" height="196" rx="10" fill="#f8fafb" '
            .'stroke="'.Kanvas::HITAM.'" stroke-width="2.5"/>');

        [$biruIsi, $biruGaris] = self::WARNA['biru'];
        [$merahIsi, $merahGaris] = self::WARNA['merah'];
        [$kuningIsi, $kuningGaris] = self::WARNA['kuning'];
        [$hijauIsi, $hijauGaris] = self::WARNA['hijau'];

        // Tiap bentuk ditulis sebagai daftar bulatan bersimpangan terhadap
        // titik sebarannya, sehingga molekul terbaca sebagai satu kesatuan.
        $bentuk = match ($jenis) {
            'unsur' => array_fill(0, 11, [[0, 0, 14, $biruIsi, $biruGaris]]),
            'senyawa' => array_fill(0, 8, [[-11, 0, 14, $biruIsi, $biruGaris], [12, 0, 11, $merahIsi, $merahGaris]]),
            'campuran_unsur' => array_merge(
                array_fill(0, 6, [[0, 0, 14, $biruIsi, $biruGaris]]),
                array_fill(0, 6, [[0, 0, 12, $kuningIsi, $kuningGaris]]),
            ),
            default => array_merge(
                array_fill(0, 4, [[-11, 0, 14, $biruIsi, $biruGaris], [12, 0, 11, $merahIsi, $merahGaris]]),
                array_fill(0, 4, [[-10, 0, 13, $hijauIsi, $hijauGaris], [11, 0, 13, $kuningIsi, $kuningGaris]]),
            ),
        };

        // Campuran disebar berselang-seling supaya kedua jenis partikelnya
        // benar-benar bercampur, bukan berkumpul di dua sisi kotak.
        if (str_starts_with($jenis, 'campuran')) {
            $separuh = (int) (count($bentuk) / 2);
            $selang = [];

            for ($i = 0; $i < $separuh; $i++) {
                $selang[] = $bentuk[$i];
                $selang[] = $bentuk[$i + $separuh];
            }

            $bentuk = $selang;
        }

        foreach (Kanvas::sebaran(count($bentuk), 90, 76, 300, 148) as $i => $titik) {
            foreach ($bentuk[$i] as [$dx, $dy, $r, $warnaIsi, $warnaGaris]) {
                $kanvas->lingkaran([$titik[0] + $dx, $titik[1] + $dy], $r, $warnaIsi, $warnaGaris, 1.8);
            }
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 34], 'Model partikel suatu zat', [
            'ukuran' => 15, 'tebal' => true,
        ])->teksPx([Kanvas::LEBAR / 2, 274], 'warna bulatan yang berbeda = jenis atom yang berbeda', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }

    /** Alat pemisahan campuran, digambar dari bentuk dasar. */
    public static function svgPemisahan(string $cara, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 32], 'Alat pemisahan campuran', [
            'ukuran' => 15, 'tebal' => true,
        ]);

        match ($cara) {
            'destilasi' => self::alatDestilasi($kanvas, $isi, $garis),
            'kristalisasi' => self::alatKristalisasi($kanvas, $isi, $garis),
            'kromatografi' => self::alatKromatografi($kanvas, $isi, $garis),
            default => self::alatFiltrasi($kanvas, $isi, $garis),
        };

        return $kanvas->render();
    }

    protected static function alatFiltrasi(Kanvas $kanvas, string $isi, string $garis): Kanvas
    {
        // Corong beserta kertas saringnya, residu tertahan di atas kertas dan
        // filtrat menetes ke gelas di bawahnya.
        return $kanvas
            ->tambah('<path d="M 166 70 L 314 70 L 250 160 L 240 160 Z" fill="#eef2f6" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->tambah('<path d="M 176 80 L 304 80 L 248 150 L 242 150 Z" fill="#ffffff" stroke="'
                .$garis.'" stroke-width="2" stroke-dasharray="5 4"/>')
            ->lingkaran([214, 92], 7, '#9b8160', '#5f4e39', 1.5)
            ->lingkaran([240, 96], 6, '#9b8160', '#5f4e39', 1.5)
            ->lingkaran([264, 90], 7, '#9b8160', '#5f4e39', 1.5)
            ->lingkaran([238, 110], 5, '#9b8160', '#5f4e39', 1.5)
            ->garis([245, 162], [245, 196], $garis, 2, true)
            ->tambah('<path d="M 186 180 L 186 268 L 304 268 L 304 180" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->tambah('<rect x="188" y="222" width="114" height="44" fill="'.$isi.'"/>')
            ->teksPx([344, 96], 'ampas', ['ukuran' => 13, 'anchor' => 'start'])
            ->teksPx([326, 248], 'hasil saringan', ['ukuran' => 13, 'anchor' => 'start']);
    }

    protected static function alatDestilasi(Kanvas $kanvas, string $isi, string $garis): Kanvas
    {
        return $kanvas
            ->lingkaran([110, 170], 44, $isi, Kanvas::HITAM, 2.5)
            ->tambah('<rect x="100" y="104" width="20" height="30" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5"/>')
            // Pipa pendingin: tabung miring berselubung air.
            ->tambah('<path d="M 120 112 L 300 176 L 300 206 L 120 142 Z" fill="#dff0fb" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->garis([134, 124], [296, 182], '#7ba7c7', 2)
            ->garis([132, 136], [294, 194], '#7ba7c7', 2)
            ->garis([300, 196], [334, 224], Kanvas::HITAM, 2.5)
            ->tambah('<path d="M 320 222 L 320 272 L 392 272 L 392 222" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->tambah('<rect x="322" y="250" width="68" height="20" fill="'.$isi.'"/>')
            // Nyala pembakar di bawah labu.
            ->tambah('<path d="M 110 226 Q 96 248 110 262 Q 124 248 110 226 Z" fill="#f6a23c" stroke="'
                .'#c0621b" stroke-width="2"/>')
            ->teksPx([110, 284], 'pemanas', ['ukuran' => 12, 'warna' => '#4b5563'])
            ->teksPx([206, 128], 'pendingin', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([400, 262], 'hasil', ['ukuran' => 13, 'anchor' => 'start']);
    }

    protected static function alatKristalisasi(Kanvas $kanvas, string $isi, string $garis): Kanvas
    {
        $kanvas
            ->tambah('<path d="M 150 166 Q 240 236 330 166 Z" fill="'.$isi.'" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->garis([142, 166], [338, 166], Kanvas::HITAM, 3);

        // Uap air yang meninggalkan larutan, dan butiran yang tertinggal.
        foreach ([[196, 0], [240, -14], [284, 0]] as [$x, $naik]) {
            $kanvas->tambah('<path d="M '.$x.' '.(150 + $naik).' q -12 -18 0 -30 q 12 -14 0 -30" '
                .'fill="none" stroke="#9aa1a9" stroke-width="2.5"/>');
        }

        foreach ([[212, 190], [240, 198], [268, 190], [226, 204], [254, 204]] as $butir) {
            $kanvas->lingkaran($butir, 6, '#ffffff', $garis, 1.8);
        }

        return $kanvas
            ->tambah('<path d="M 240 240 Q 224 266 240 282 Q 256 266 240 240 Z" fill="#f6a23c" '
                .'stroke="#c0621b" stroke-width="2"/>')
            ->teksPx([240, 56], 'uap', ['ukuran' => 13, 'tebal' => true])
            ->teksPx([352, 200], 'butiran yang tertinggal', ['ukuran' => 12, 'anchor' => 'start']);
    }

    protected static function alatKromatografi(Kanvas $kanvas, string $isi, string $garis): Kanvas
    {
        $kanvas
            ->tambah('<path d="M 150 62 L 150 268 L 330 268 L 330 62" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="2.5" stroke-linejoin="round"/>')
            ->tambah('<rect x="152" y="232" width="176" height="34" fill="'.$isi.'"/>')
            ->tambah('<rect x="222" y="70" width="36" height="186" fill="#fdfbf4" stroke="'
                .Kanvas::HITAM.'" stroke-width="2"/>');

        // Noda warna yang merambat berbeda jauh pada kertasnya.
        foreach ([['#e4556a', 108], ['#3f7fd0', 146], ['#4caf50', 182]] as [$warna, $y]) {
            $kanvas->elips([240, $y], 13, 8, $warna, $warna, 1);
        }

        return $kanvas
            ->garis([216, 222], [300, 222], '#8a9199', 1.5, true)
            ->teksPx([304, 226], 'garis awal', ['ukuran' => 11, 'anchor' => 'start', 'warna' => '#4b5563'])
            ->teksPx([194, 100], 'kertas', ['ukuran' => 12, 'anchor' => 'end'])
            ->teksPx([346, 252], 'pelarut', ['ukuran' => 12, 'anchor' => 'start']);
    }
}

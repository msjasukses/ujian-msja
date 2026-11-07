<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template siklus hidup & daur: metamorfosis hewan dan daur air.
 *
 * Keduanya dibangkitkan dari daftar tahap yang berurutan lalu digambar
 * sebagai lingkaran tahap — bentuk yang menjelaskan sendiri bahwa urutannya
 * berulang, bukan berhenti di tahap terakhir. Karena tahapnya cuma teks
 * berurutan, menambah hewan atau daur baru tinggal menambah satu baris di
 * tetapannya.
 */
class SiklusHidup extends Template
{
    /**
     * Hewan beserta tahap daur hidupnya, berurutan dari telur.
     *
     * @var array<string, array{nama: string, metamorfosis: string, tahap: array<int, string>}>
     */
    public const HEWAN = [
        'kupu' => [
            'nama' => 'kupu-kupu',
            'metamorfosis' => 'sempurna',
            'tahap' => ['telur', 'larva (ulat)', 'pupa (kepompong)', 'kupu-kupu dewasa'],
        ],
        'katak' => [
            'nama' => 'katak',
            'metamorfosis' => 'sempurna',
            'tahap' => ['telur', 'kecebong', 'katak berekor', 'katak dewasa'],
        ],
        'nyamuk' => [
            'nama' => 'nyamuk',
            'metamorfosis' => 'sempurna',
            'tahap' => ['telur', 'jentik-jentik', 'pupa', 'nyamuk dewasa'],
        ],
        'lalat' => [
            'nama' => 'lalat',
            'metamorfosis' => 'sempurna',
            'tahap' => ['telur', 'larva (belatung)', 'pupa', 'lalat dewasa'],
        ],
        'belalang' => [
            'nama' => 'belalang',
            'metamorfosis' => 'tidak sempurna',
            'tahap' => ['telur', 'nimfa', 'belalang dewasa'],
        ],
        'kecoak' => [
            'nama' => 'kecoak',
            'metamorfosis' => 'tidak sempurna',
            'tahap' => ['telur', 'nimfa', 'kecoak dewasa'],
        ],
        'capung' => [
            'nama' => 'capung',
            'metamorfosis' => 'tidak sempurna',
            'tahap' => ['telur', 'nimfa', 'capung dewasa'],
        ],
    ];

    /** Tahap daur air beserta istilah ilmiahnya. */
    public const DAUR = [
        'penguapan air laut' => 'Evaporasi',
        'pengembunan menjadi awan' => 'Kondensasi',
        'hujan turun ke bumi' => 'Presipitasi',
        'air meresap dan mengalir ke laut' => 'Infiltrasi',
    ];

    /** @var array<string, string> */
    public const TOPIK = [
        'metamorfosis' => 'Metamorfosis hewan',
        'daur_air' => 'Daur air',
    ];

    /** @var array<string, string> */
    public const TANYA_HEWAN = [
        'berikut' => 'Tahap setelah tahap tertentu',
        'hilang' => 'Tahap yang hilang dari bagan',
        'jenis' => 'Jenis metamorfosisnya',
    ];

    /** @var array<string, string> */
    public const TANYA_DAUR = [
        'berikut' => 'Tahap setelah tahap tertentu',
        'hilang' => 'Tahap yang hilang dari bagan',
        'istilah' => 'Istilah ilmiah suatu tahap',
    ];

    /**
     * Tahap yang sering dipakai siswa walaupun tidak ada pada daur hewan yang
     * ditanyakan — terutama pupa, yang dikira dialami semua serangga.
     *
     * @var array<int, string>
     */
    public const PENGECOH_TAHAP = ['pupa (kepompong)', 'nimfa', 'larva (ulat)'];

    public static function kode(): string
    {
        return 'siklus-hidup';
    }

    public static function nama(): string
    {
        return 'Siklus Hidup & Daur';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Lingkaran tahap daur hidup tujuh hewan dan daur air: tahap '
            .'berikutnya, tahap yang hilang, dan jenis metamorfosisnya.';
    }

    public static function ikon(): string
    {
        return 'bi-arrow-repeat';
    }

    public static function kataKunci(): array
    {
        return ['siklus hidup', 'daur hidup', 'metamorfosis', 'sempurna', 'tidak sempurna',
            'kupu-kupu', 'katak', 'belalang', 'nimfa', 'pupa', 'kepompong',
            'daur air', 'evaporasi', 'kondensasi', 'presipitasi'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'topik',
                'label' => 'Daur yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => self::TOPIK,
                'awal' => 'metamorfosis',
            ],
            [
                'nama' => 'hewan',
                'label' => 'Hewan',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($h) => ucfirst($h['nama']).' ('.count($h['tahap']).' tahap)',
                    self::HEWAN,
                ),
                'awal' => 'kupu',
                'tampil' => ['topik' => ['metamorfosis']],
            ],
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_HEWAN,
                'awal' => 'berikut',
                'tampil' => ['topik' => ['metamorfosis']],
            ],
            [
                'nama' => 'tanya_daur',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_DAUR,
                'awal' => 'berikut',
                'tampil' => ['topik' => ['daur_air']],
            ],
            self::kolomAngka('posisi', 'Tahap ke- (dari awal)', 2, 1, 4, [
                'langkah' => 1,
            ]),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $tahap = self::tahap($isi);

        if ((int) ($isi['posisi'] ?? 1) > count($tahap)) {
            return ['posisi' => 'Daur ini hanya punya '.count($tahap).' tahap.'];
        }

        return [];
    }

    /** @return array<int, string> */
    protected static function tahap(array $isi): array
    {
        if (($isi['topik'] ?? 'metamorfosis') === 'daur_air') {
            return array_keys(self::DAUR);
        }

        return (self::HEWAN[$isi['hewan'] ?? 'kupu'] ?? self::HEWAN['kupu'])['tahap'];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $daurAir = ($isi['topik'] ?? 'metamorfosis') === 'daur_air';
        $hewan = self::HEWAN[$isi['hewan'] ?? 'kupu'] ?? self::HEWAN['kupu'];

        $tahap = self::tahap($isi);
        $judul = $daurAir ? 'Daur air' : 'Daur hidup '.$hewan['nama'];
        $tanya = ($daurAir ? $isi['tanya_daur'] ?? 'berikut' : $isi['tanya'] ?? 'berikut');

        $i = min((int) ($isi['posisi'] ?? 1), count($tahap)) - 1;
        $tandai = $tanya === 'jenis' ? 0 : $i + 1;

        $bahan = [
            'svg' => self::svgSiklus($tahap, $judul, $tandai, $tanya === 'hilang', $warnaIsi, $garis),
            'keterangan' => $judul.': '.implode(' → ', $tahap).', lalu berulang.',
        ];

        $awalan = 'Perhatikan bagan '.mb_strtolower($judul).' berikut.';

        return $bahan + match ($tanya) {
            'hilang' => $this->hilang($awalan, $tahap, $i, $judul),
            'jenis' => $this->jenisMetamorfosis($awalan, $hewan),
            'istilah' => $this->istilah($awalan, $tahap, $i),
            default => $this->berikut($awalan, $tahap, $i, $judul),
        };
    }

    protected function berikut(string $awalan, array $tahap, int $i, string $judul): array
    {
        // Tahap sesudah yang terakhir kembali ke tahap pertama: daurnya
        // memang berulang, dan itulah yang hendak ditanamkan.
        $ke = ($i + 1) % count($tahap);
        $benar = $tahap[$ke];

        return [
            'judul' => 'Setelah '.$tahap[$i].': '.$benar,
            'teks' => $awalan.' Tahap yang terjadi setelah '.$tahap[$i].' adalah ...',
            'pernyataan' => $awalan.' Tahap setelah '.$tahap[$i].' adalah %s.',
            'pilihan' => self::pilihanTahap($benar, $tahap),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan tahap pada '.mb_strtolower($judul).': '
                .implode(' → ', $tahap).', lalu kembali ke '.$tahap[0]
                .'. Setelah '.$tahap[$i].' datang '.$benar.'.',
        ];
    }

    protected function hilang(string $awalan, array $tahap, int $i, string $judul): array
    {
        $benar = $tahap[$i];

        return [
            'judul' => 'Tahap yang hilang: '.$benar,
            'teks' => $awalan.' Tahap yang tepat untuk mengisi kotak bertanda tanya adalah ...',
            'pernyataan' => $awalan.' Kotak bertanda tanya pada bagan itu seharusnya diisi %s.',
            'pilihan' => self::pilihanTahap($benar, $tahap),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan tahap pada '.mb_strtolower($judul).': '
                .implode(' → ', $tahap).'. Kotak ke-'.($i + 1).' diisi '.$benar
                .($i > 0 ? ', karena berada setelah '.$tahap[$i - 1].'.' : ', tahap paling awal.'),
        ];
    }

    protected function jenisMetamorfosis(string $awalan, array $hewan): array
    {
        $benar = 'Metamorfosis '.$hewan['metamorfosis'];
        $sempurna = $hewan['metamorfosis'] === 'sempurna';

        return [
            'judul' => ucfirst($hewan['nama']).': '.mb_strtolower($benar),
            'teks' => $awalan.' Daur hidup '.$hewan['nama'].' tersebut tergolong ...',
            'pernyataan' => $awalan.' Daur hidup '.$hewan['nama'].' tersebut tergolong %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Metamorfosis sempurna',
                    'Metamorfosis tidak sempurna',
                    'Tidak mengalami metamorfosis',
                    'Metamorfosis sebagian',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $sempurna
                ? 'Daur hidup '.$hewan['nama'].' melalui tahap '
                    .implode(' → ', $hewan['tahap']).'. Karena ada tahap pupa dan bentuk '
                    .'mudanya sangat berbeda dengan yang dewasa, metamorfosisnya tergolong sempurna.'
                : 'Daur hidup '.$hewan['nama'].' melalui tahap '
                    .implode(' → ', $hewan['tahap']).'. Tidak ada tahap pupa dan bentuk nimfanya '
                    .'sudah menyerupai yang dewasa, sehingga metamorfosisnya tergolong tidak sempurna.',
        ];
    }

    protected function istilah(string $awalan, array $tahap, int $i): array
    {
        $benar = self::DAUR[$tahap[$i]];

        return [
            'judul' => ucfirst($tahap[$i]).' = '.$benar,
            'teks' => $awalan.' Tahap '.$tahap[$i].' disebut juga ...',
            'pernyataan' => $awalan.' Tahap '.$tahap[$i].' disebut juga %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_values(self::DAUR), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Istilah tiap tahap daur air: '
                .implode(', ', array_map(
                    fn ($tahapNama, $istilah) => $tahapNama.' = '.$istilah,
                    array_keys(self::DAUR), self::DAUR,
                )).'. Jadi '.$tahap[$i].' disebut '.$benar.'.',
        ];
    }

    /**
     * Pengecoh tahap: tahap lain dari daur yang sama lebih dahulu — yang
     * keliru biasanya tertukar urutannya, bukan tidak mengenal tahapnya —
     * lalu tahap yang dikira ada padahal tidak, seperti pupa pada hewan
     * bermetamorfosis tidak sempurna.
     *
     * @param  array<int, string>  $tahap
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanTahap(string $benar, array $tahap): array
    {
        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_merge([$benar], array_values(array_diff(
                array_unique(array_merge($tahap, self::PENGECOH_TAHAP)),
                [$benar],
            ))),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Lingkaran tahap: kotak-kotak yang ditata pada sebuah lingkaran dan
     * dihubungkan panah, termasuk panah dari tahap terakhir kembali ke tahap
     * pertama. Nama daurnya ditaruh di tengah lingkaran, bukan di atas
     * gambar, supaya ruang kanvasnya tidak terbuang.
     *
     * @param  array<int, string>  $tahap
     * @param  int  $tandai  tahap yang ditonjolkan (1..n), 0 berarti tidak ada
     */
    public static function svgSiklus(array $tahap, string $judul, int $tandai, bool $sembunyi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $n = count($tahap);
        $pusat = [Kanvas::LEBAR / 2, 160];
        [$rx, $ry] = [152, 94];
        [$lebar, $tinggi] = [128, 44];

        $kanvas->teksBaris($pusat, Kanvas::baris($judul, 148, 15), [
            'ukuran' => 15, 'tebal' => true, 'warna' => '#4b5563',
        ]);

        $letak = [];

        foreach (array_values($tahap) as $k => $nama) {
            $sudut = deg2rad(-90 + $k * 360 / $n);
            $letak[$k] = [$pusat[0] + $rx * cos($sudut), $pusat[1] + $ry * sin($sudut)];
            $ini = $tandai === $k + 1;

            $kanvas->kotakPx($letak[$k], $lebar, $tinggi, $ini && $sembunyi ? '?' : $nama,
                $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini && $sembunyi ? 24 : 12,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 2,
                ]);
        }

        for ($k = 0; $k < $n; $k++) {
            $kanvas->panahKotakPx($letak[$k], $letak[($k + 1) % $n], $lebar, $tinggi, Kanvas::HITAM, 2.2);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 292], 'panah = urutan tahap; daurnya berulang', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }
}

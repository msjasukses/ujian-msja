<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template garis waktu peristiwa sejarah Indonesia.
 *
 * Titik peristiwanya ditata berjarak sama, bukan sebanding tahunnya, sebab
 * 1945 dan 1949 hanya berselisih empat tahun dan labelnya akan bertumpuk
 * kalau digambar berskala. Keterangan di bawah gambar menyebutkan hal itu
 * supaya siswa tidak menyimpulkan panjang jarak dari gambarnya, dan soal
 * selisih tahun pun dihitung dari angka tahunnya, bukan dari jaraknya.
 *
 * Yang ditanyakan selalu disembunyikan dari gambar — nama peristiwanya atau
 * tahunnya diganti tanda tanya — sehingga butirnya tidak bisa dijawab hanya
 * dengan membaca gambar.
 */
class GarisWaktu extends Template
{
    /**
     * Peristiwa sejarah Indonesia, berurutan menurut tahunnya.
     *
     * @var array<string, array{tahun: int, nama: string, singkat: string, catatan: string}>
     */
    public const PERISTIWA = [
        'budi_utomo' => [
            'tahun' => 1908,
            'nama' => 'Berdirinya Budi Utomo',
            'singkat' => 'Budi Utomo',
            'catatan' => 'organisasi pergerakan nasional pertama, kini diperingati sebagai Hari Kebangkitan Nasional',
        ],
        'sumpah_pemuda' => [
            'tahun' => 1928,
            'nama' => 'Sumpah Pemuda',
            'singkat' => 'Sumpah Pemuda',
            'catatan' => 'Kongres Pemuda II di Batavia yang mencetuskan satu nusa, satu bangsa, dan satu bahasa',
        ],
        'proklamasi' => [
            'tahun' => 1945,
            'nama' => 'Proklamasi Kemerdekaan',
            'singkat' => 'Proklamasi',
            'catatan' => 'pembacaan teks proklamasi pada 17 Agustus di Jalan Pegangsaan Timur 56',
        ],
        'kmb' => [
            'tahun' => 1949,
            'nama' => 'Pengakuan kedaulatan',
            'singkat' => 'Pengakuan kedaulatan',
            'catatan' => 'hasil Konferensi Meja Bundar di Den Haag',
        ],
        'pemilu' => [
            'tahun' => 1955,
            'nama' => 'Pemilu pertama',
            'singkat' => 'Pemilu pertama',
            'catatan' => 'pemilihan umum pertama untuk memilih anggota DPR dan Konstituante',
        ],
        'supersemar' => [
            'tahun' => 1966,
            'nama' => 'Supersemar',
            'singkat' => 'Supersemar',
            'catatan' => 'Surat Perintah Sebelas Maret yang menandai peralihan ke Orde Baru',
        ],
        'reformasi' => [
            'tahun' => 1998,
            'nama' => 'Awal masa Reformasi',
            'singkat' => 'Reformasi',
            'catatan' => 'berakhirnya Orde Baru setelah Presiden Soeharto menyatakan berhenti',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'peristiwa' => 'Nama peristiwa pada sebuah tahun',
        'tahun' => 'Tahun sebuah peristiwa',
        'selisih' => 'Selisih tahun dua peristiwa',
        'urutan' => 'Urutan tiga peristiwa berurutan',
    ];

    public static function kode(): string
    {
        return 'garis-waktu';
    }

    public static function nama(): string
    {
        return 'Garis Waktu Sejarah';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Garis waktu peristiwa sejarah Indonesia: menamai peristiwa pada sebuah '
            .'tahun, menyebut tahunnya, menghitung selisih tahun, dan mengurutkannya.';
    }

    public static function ikon(): string
    {
        return 'bi-hourglass-split';
    }

    public static function kataKunci(): array
    {
        return ['garis waktu', 'sejarah', 'kronologi', 'peristiwa sejarah', 'budi utomo',
            'sumpah pemuda', 'proklamasi', 'kemerdekaan', 'reformasi', 'orde baru',
            'konferensi meja bundar', 'pemilu', 'urutan peristiwa', 'ips'];
    }

    public static function kolom(): array
    {
        $pilihan = array_map(fn ($p) => $p['nama'].' ('.$p['tahun'].')', self::PERISTIWA);

        return [
            self::kolomTanya(self::TANYA, 'peristiwa'),
            [
                'nama' => 'peristiwa',
                'label' => 'Peristiwa',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                'awal' => 'proklamasi',
            ],
            [
                'nama' => 'peristiwa_dua',
                'label' => 'Peristiwa kedua',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                'awal' => 'reformasi',
                'tampil' => ['tanya' => ['selisih']],
            ],
            self::kolomWarna('jingga'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? 'peristiwa') === 'selisih'
            && ($isi['peristiwa'] ?? '') === ($isi['peristiwa_dua'] ?? '')) {
            return ['peristiwa_dua' => 'Pilih dua peristiwa yang berbeda.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        $satu = isset(self::PERISTIWA[$isi['peristiwa'] ?? '']) ? $isi['peristiwa'] : 'proklamasi';
        $dua = isset(self::PERISTIWA[$isi['peristiwa_dua'] ?? '']) ? $isi['peristiwa_dua'] : 'reformasi';

        if ($dua === $satu) {
            $dua = $satu === 'reformasi' ? 'proklamasi' : 'reformasi';
        }

        return match ($isi['tanya'] ?? 'peristiwa') {
            'tahun' => $this->tahun($satu, $warnaIsi, $garis),
            'selisih' => $this->selisih($satu, $dua, $warnaIsi, $garis),
            'urutan' => $this->urutanPeristiwa($satu, $warnaIsi, $garis),
            default => $this->peristiwa($satu, $warnaIsi, $garis),
        };
    }

    protected function peristiwa(string $kunci, string $warnaIsi, string $garis): array
    {
        $benar = self::PERISTIWA[$kunci];
        $tahun = $benar['tahun'];

        return [
            // Namanya disembunyikan dari gambar, tahunnya tetap terlihat.
            'svg' => self::svgGaris([$kunci], [], [], $kunci, $warnaIsi, $garis),
            'keterangan' => 'Garis waktu sejarah Indonesia; nama peristiwa tahun '.$tahun
                .' diganti tanda tanya.',
            'judul' => $tahun.': '.$benar['nama'],
            'teks' => 'Perhatikan garis waktu berikut. Peristiwa yang terjadi pada tahun '
                .$tahun.' adalah ...',
            'pernyataan' => 'Pada garis waktu berikut, peristiwa yang terjadi pada tahun '
                .$tahun.' adalah %s.',
            'pilihan' => self::pilihanNama($kunci),
            'kata_kunci' => $benar['nama'],
            'pembahasan' => 'Titik bertanda tahun '.$tahun.' pada garis waktu itu adalah '
                .$benar['nama'].' — '.$benar['catatan'].'.<br>Peristiwa lain pada garis waktu '
                .'itu bertahun berbeda, sehingga tidak mungkin menjadi jawabannya.',
        ];
    }

    protected function tahun(string $kunci, string $warnaIsi, string $garis): array
    {
        $benar = self::PERISTIWA[$kunci];
        $tahun = $benar['tahun'];

        // Pengecohnya tahun peristiwa lain pada garis waktu yang sama —
        // tertukar peristiwa, bukan angka yang dikarang.
        $pengecoh = array_values(array_diff(array_column(self::PERISTIWA, 'tahun'), [$tahun]));

        return [
            // Tahunnya disembunyikan, namanya tetap terlihat.
            'svg' => self::svgGaris([], [$kunci], [], $kunci, $warnaIsi, $garis),
            'keterangan' => 'Garis waktu sejarah Indonesia; tahun peristiwa '.$benar['nama']
                .' diganti tanda tanya.',
            'judul' => $benar['nama'].': '.$tahun,
            'teks' => 'Perhatikan garis waktu berikut. '.$benar['nama'].' terjadi pada tahun ...',
            'pernyataan' => 'Pada garis waktu berikut, '.$benar['nama']
                .' terjadi pada tahun %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => (string) $t, 'urut' => $t],
                array_merge([$tahun], $pengecoh),
            ),
            'kata_kunci' => (string) $tahun,
            'pembahasan' => $benar['nama'].' adalah '.$benar['catatan'].', yang terjadi pada '
                .'tahun '.$tahun.'.<br>Pada garis waktu itu letaknya '
                .self::letakUrutan($kunci).'.',
        ];
    }

    protected function selisih(string $satu, string $dua, string $warnaIsi, string $garis): array
    {
        // Urutannya ditertibkan dahulu supaya kalimat soalnya selalu
        // menyebut peristiwa yang lebih dahulu di depan.
        [$awal, $akhir] = self::PERISTIWA[$satu]['tahun'] <= self::PERISTIWA[$dua]['tahun']
            ? [$satu, $dua] : [$dua, $satu];

        $t1 = self::PERISTIWA[$awal]['tahun'];
        $t2 = self::PERISTIWA[$akhir]['tahun'];
        $benar = $t2 - $t1;

        $pengecoh = self::saring((float) $benar, [
            // Dibulatkan ke dasawarsa dahulu, kesalahan yang lazim ketika
            // selisihnya dikira-kira dari gambar.
            (round($t2 / 10) - round($t1 / 10)) * 10,
            // Satuan tahunnya saja yang dikurangkan.
            abs($t2 % 10 - $t1 % 10) + ((int) ($t2 / 10) - (int) ($t1 / 10)) * 10,
            $benar + 10,
            $benar - 10,
            $benar + 1,
            $benar - 1,
            $benar + 20,
        ], 0);

        return [
            'svg' => self::svgGaris([], [], [$awal, $akhir], null, $warnaIsi, $garis),
            'keterangan' => 'Garis waktu sejarah Indonesia; peristiwa tahun '.$t1.' dan '.$t2
                .' ditandai.',
            'judul' => 'Selisih '.$t1.'–'.$t2.' = '.$benar.' tahun',
            'teks' => 'Perhatikan garis waktu berikut. Berapa tahun jarak antara '
                .self::PERISTIWA[$awal]['nama'].' dan '.self::PERISTIWA[$akhir]['nama'].'?',
            'pernyataan' => 'Pada garis waktu berikut, jarak antara '
                .self::PERISTIWA[$awal]['nama'].' dan '.self::PERISTIWA[$akhir]['nama']
                .' adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n).' tahun', 'urut' => $n],
                array_merge([(float) $benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar.' tahun',
            'pembahasan' => 'Jarak dua peristiwa dihitung dengan mengurangkan tahunnya, bukan '
                .'dari jarak titiknya pada gambar — garis waktu itu tidak berskala.<br>'
                .$t2.' − '.$t1.' = <strong>'.$benar.' tahun</strong>.',
        ];
    }

    /**
     * Mengurutkan tiga peristiwa yang berdekatan.
     *
     * Soal urutan tidak cukup membandingkan dua peristiwa: pilihannya hanya
     * dua nama dan dua pengecoh sisanya pasti tidak masuk hitungan siswa.
     * Karena itu yang diurutkan tiga peristiwa berurutan, dan pilihannya
     * susunan ketiganya — satu benar dan tiga susunan yang tertukar.
     */
    protected function urutanPeristiwa(string $satu, string $warnaIsi, string $garis): array
    {
        $semua = array_keys(self::PERISTIWA);

        // Tiga peristiwa diambil mulai dari yang dipilih guru. Kalau
        // pilihannya dekat ujung, awalnya digeser mundur supaya tetap dapat
        // tiga peristiwa yang berurutan.
        $mulai = min(
            (int) array_search($satu, $semua, true),
            count($semua) - 3,
        );

        $tiga = array_slice($semua, $mulai, 3);
        $singkat = array_map(fn ($k) => self::PERISTIWA[$k]['singkat'], $tiga);

        $benar = implode(' → ', $singkat);

        // Tiga susunan yang tertukar: dua pertama bertukar, dua terakhir
        // bertukar, dan seluruhnya dibalik — kekeliruan yang nyata, bukan
        // acakan yang asal berbeda.
        $pengecoh = [
            implode(' → ', [$singkat[1], $singkat[0], $singkat[2]]),
            implode(' → ', [$singkat[0], $singkat[2], $singkat[1]]),
            implode(' → ', array_reverse($singkat)),
        ];

        $tahun = array_map(fn ($k) => self::PERISTIWA[$k]['tahun'], $tiga);

        return [
            // Nama dan tahun ketiganya disembunyikan supaya urutannya tidak
            // bisa dibaca langsung dari gambar.
            'svg' => self::svgGaris($tiga, $tiga, $tiga, null, $warnaIsi, $garis),
            'keterangan' => 'Garis waktu sejarah Indonesia; tiga titik yang diurutkan ditandai '
                .'tanpa nama dan tahunnya.',
            'judul' => 'Urutan: '.$benar,
            'teks' => 'Urutan peristiwa berikut dari yang paling awal adalah ...',
            'pernyataan' => 'Urutan peristiwa dari yang paling awal adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], $pengecoh),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Tahun ketiga peristiwa itu: '
                .implode(', ', array_map(
                    fn ($n, $t) => $n.' ('.$t.')',
                    $singkat, $tahun,
                )).'.<br>Diurutkan dari tahun terkecil: <strong>'.$benar.'</strong>.',
        ];
    }

    /** Pilihan berupa nama peristiwa, yang benar di depan. */
    protected static function pilihanNama(string $kunci): array
    {
        $benar = self::PERISTIWA[$kunci]['nama'];

        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_merge([$benar], array_values(array_diff(
                array_column(self::PERISTIWA, 'nama'), [$benar],
            ))),
        );
    }

    /** Kalimat singkat tentang kedudukan sebuah peristiwa pada garis waktu. */
    protected static function letakUrutan(string $kunci): string
    {
        $urut = array_search($kunci, array_keys(self::PERISTIWA), true);
        $banyak = count(self::PERISTIWA);

        return match (true) {
            $urut === 0 => 'paling kiri, yaitu yang paling awal',
            $urut === $banyak - 1 => 'paling kanan, yaitu yang paling akhir',
            default => 'titik ke-'.($urut + 1).' dari kiri',
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Garis waktu dengan tujuh titik berjarak sama.
     *
     * @param  array<int, string>  $tanpaNama  peristiwa yang namanya diganti "?"
     * @param  array<int, string>  $tanpaTahun  peristiwa yang tahunnya diganti "?"
     * @param  array<int, string>  $ditandai  peristiwa yang titiknya dipertegas
     */
    public static function svgGaris(array $tanpaNama, array $tanpaTahun, array $ditandai, ?string $sorot, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 40;
        $kanan = 440;
        $sumbuY = 152;
        $banyak = count(self::PERISTIWA);
        $jarak = ($kanan - $kiri) / $banyak;

        $kanvas
            ->teksPx([240, 28], 'Garis Waktu Sejarah Indonesia', ['ukuran' => 15, 'tebal' => true])
            ->panahPx([$kiri - 8, $sumbuY], [$kanan + 12, $sumbuY], Kanvas::HITAM, 2.5);

        $i = 0;

        foreach (self::PERISTIWA as $kunci => $peristiwa) {
            $x = $kiri + ($i + 0.5) * $jarak;
            $atas = $i % 2 === 0;
            $tegas = in_array($kunci, $ditandai, true) || $kunci === $sorot;

            $namaTertutup = in_array($kunci, $tanpaNama, true);
            $tahunTertutup = in_array($kunci, $tanpaTahun, true);

            // Kalau nama dan tahunnya sama-sama ditutup, cukup satu tanda
            // tanya: dua tanda tanya bertumpuk pada satu titik hanya
            // membuat gambarnya ramai tanpa menambah keterangan apa pun.
            $nama = $namaTertutup ? ($tahunTertutup ? '' : '?') : $peristiwa['nama'];
            $tahun = $tahunTertutup ? '?' : (string) $peristiwa['tahun'];

            // Label diletakkan bergantian di atas dan di bawah garis supaya
            // tetangga di sisi yang sama berjarak dua kali lipat.
            $yTahun = $atas ? $sumbuY - 40 : $sumbuY + 48;
            $yNama = $atas ? $sumbuY - 70 : $sumbuY + 74;

            $kanvas
                ->garis([$x, $sumbuY + ($atas ? -8 : 8)], [$x, $atas ? $yTahun + 10 : $yTahun - 22],
                    '#c4c9cf', 1.2)
                ->tambah('<circle cx="'.round($x, 1).'" cy="'.$sumbuY.'" r="'.($tegas ? 8 : 5)
                    .'" fill="'.($tegas ? $garis : $isi).'" stroke="'.$garis
                    .'" stroke-width="'.($tegas ? 2.5 : 1.5).'"/>')
                ->teksPx([$x, $yTahun], $tahun, [
                    'ukuran' => $tahun === '?' ? 17 : 13, 'tebal' => true,
                    'warna' => $tahun === '?' ? $garis : Kanvas::HITAM,
                ])
                ->teksBaris([$x, $yNama], $nama === '' ? [] : Kanvas::baris($nama, $jarak * 2 - 10, 10), [
                    'ukuran' => $nama === '?' ? 17 : 10,
                    'tebal' => $nama === '?',
                    'warna' => $nama === '?' ? $garis : '#4b5563',
                ]);

            $i++;
        }

        return $kanvas
            ->teksPx([240, 286], 'titiknya berjarak sama, tidak sebanding tahunnya', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

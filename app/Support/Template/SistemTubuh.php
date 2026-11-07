<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template organ & sistem tubuh: urutan organ dan fungsinya.
 *
 * Yang digambar adalah bagan alur, bukan ilustrasi anatomi. Gambar organ
 * yang benar-benar menyerupai aslinya tidak dapat dibangkitkan dari isian
 * guru — ia harus digambar satu per satu — sedangkan yang diuji di soal
 * urutan dan fungsi organ justru alurnya: apa yang dilalui setelah apa, dan
 * apa yang dikerjakan tiap organ. Alur itulah yang dibangkitkan di sini.
 */
class SistemTubuh extends Template
{
    /**
     * Sistem tubuh beserta organ yang dilaluinya secara berurutan dan fungsi
     * tiap organnya.
     *
     * @var array<string, array{nama: string, alur: array<int, string>, fungsi: array<string, string>}>
     */
    public const SISTEM = [
        'pencernaan' => [
            'nama' => 'sistem pencernaan makanan',
            'alur' => ['mulut', 'kerongkongan', 'lambung', 'usus halus', 'usus besar', 'anus'],
            'fungsi' => [
                'mulut' => 'mengunyah makanan dan memecah amilum dengan enzim amilase',
                'kerongkongan' => 'mendorong makanan menuju lambung dengan gerak peristaltik',
                'lambung' => 'mencerna protein dengan enzim pepsin dan asam klorida',
                'usus halus' => 'menyerap sari-sari makanan ke dalam darah',
                'usus besar' => 'menyerap air dan membusukkan sisa makanan',
                'anus' => 'mengeluarkan sisa pencernaan berupa feses',
            ],
        ],
        'pernapasan' => [
            'nama' => 'sistem pernapasan',
            'alur' => ['rongga hidung', 'faring', 'trakea', 'bronkus', 'bronkiolus', 'alveolus'],
            'fungsi' => [
                'rongga hidung' => 'menyaring, menghangatkan, dan melembapkan udara yang masuk',
                'faring' => 'menjadi jalan bersama bagi udara dan makanan',
                'trakea' => 'menyalurkan udara ke paru-paru dan menahan kotoran dengan rambut getar',
                'bronkus' => 'bercabang membawa udara ke paru-paru kanan dan kiri',
                'bronkiolus' => 'menyalurkan udara ke gelembung-gelembung paru-paru',
                'alveolus' => 'menjadi tempat pertukaran oksigen dan karbon dioksida',
            ],
        ],
        'peredaran' => [
            'nama' => 'peredaran darah',
            'alur' => ['bilik kiri', 'aorta', 'seluruh tubuh', 'serambi kanan', 'bilik kanan',
                'paru-paru', 'serambi kiri'],
            'fungsi' => [
                'bilik kiri' => 'memompa darah kaya oksigen ke seluruh tubuh',
                'aorta' => 'membawa darah kaya oksigen keluar dari jantung',
                'seluruh tubuh' => 'menjadi tempat sel menyerap oksigen dan melepas karbon dioksida',
                'serambi kanan' => 'menerima darah kaya karbon dioksida dari seluruh tubuh',
                'bilik kanan' => 'memompa darah menuju paru-paru',
                'paru-paru' => 'menjadi tempat darah melepas karbon dioksida dan mengikat oksigen',
                'serambi kiri' => 'menerima darah kaya oksigen dari paru-paru',
            ],
        ],
        'ekskresi' => [
            'nama' => 'sistem ekskresi urine',
            'alur' => ['ginjal', 'ureter', 'kandung kemih', 'uretra'],
            'fungsi' => [
                'ginjal' => 'menyaring darah dan membentuk urine',
                'ureter' => 'menyalurkan urine dari ginjal ke kandung kemih',
                'kandung kemih' => 'menampung urine untuk sementara',
                'uretra' => 'mengeluarkan urine dari dalam tubuh',
            ],
        ],
        'saraf' => [
            'nama' => 'gerak refleks',
            'alur' => ['reseptor (kulit)', 'saraf sensorik', 'sumsum tulang belakang',
                'saraf motorik', 'efektor (otot)'],
            'fungsi' => [
                'reseptor (kulit)' => 'menerima rangsang dari luar tubuh',
                'saraf sensorik' => 'membawa rangsang dari reseptor menuju pusat saraf',
                'sumsum tulang belakang' => 'menjadi pusat gerak refleks tanpa melalui otak',
                'saraf motorik' => 'membawa perintah dari pusat saraf menuju efektor',
                'efektor (otot)' => 'menanggapi rangsang dengan melakukan gerakan',
            ],
        ],
    ];

    /**
     * Pengacakan tetap untuk bagan soal urutan. Sengaja ditulis, bukan
     * diacak: gambar soal yang sama harus selalu persis sama, termasuk saat
     * guru membuka kembali butir yang sudah disimpannya.
     *
     * @var array<int, array<int, int>>
     */
    public const ACAK = [
        4 => [2, 0, 3, 1],
        5 => [3, 0, 4, 1, 2],
        6 => [4, 1, 5, 0, 3, 2],
        7 => [5, 2, 6, 0, 4, 1, 3],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'berikut' => 'Organ setelah organ tertentu',
        'hilang' => 'Organ yang hilang dari bagan',
        'fungsi' => 'Fungsi sebuah organ',
        'urutan' => 'Urutan organ yang benar',
    ];

    public static function kode(): string
    {
        return 'sistem-tubuh';
    }

    public static function nama(): string
    {
        return 'Organ & Sistem Tubuh';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Bagan alur lima sistem tubuh — pencernaan, pernapasan, peredaran '
            .'darah, ekskresi, dan gerak refleks: urutan organ dan fungsinya.';
    }

    public static function ikon(): string
    {
        return 'bi-heart-pulse';
    }

    public static function kataKunci(): array
    {
        return ['organ', 'sistem tubuh', 'pencernaan', 'pernapasan', 'peredaran darah',
            'ekskresi', 'saraf', 'refleks', 'lambung', 'usus', 'alveolus', 'ginjal',
            'jantung', 'anatomi', 'fisiologi'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'sistem',
                'label' => 'Sistem tubuh',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($s) => ucfirst($s['nama']).' ('.count($s['alur']).' tahap)',
                    self::SISTEM,
                ),
                'awal' => 'pencernaan',
            ],
            self::kolomTanya(self::TANYA, 'berikut'),
            self::kolomAngka('posisi', 'Organ ke- (dari awal)', 3, 1, 7, [
                'langkah' => 1, 'tampil' => ['tanya' => ['berikut', 'hilang', 'fungsi']],
            ]),
            self::kolomWarna('merah'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $alur = self::pilih($isi)['alur'];

        if (($isi['tanya'] ?? 'berikut') === 'urutan') {
            return [];
        }

        if ((int) ($isi['posisi'] ?? 1) > count($alur)) {
            return ['posisi' => 'Sistem ini hanya melalui '.count($alur).' organ.'];
        }

        return [];
    }

    /** @return array{nama: string, alur: array<int, string>, fungsi: array<string, string>} */
    protected static function pilih(array $isi): array
    {
        return self::SISTEM[$isi['sistem'] ?? 'pencernaan'] ?? self::SISTEM['pencernaan'];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        $sistem = self::pilih($isi);
        $alur = $sistem['alur'];
        $tanya = $isi['tanya'] ?? 'berikut';
        $i = min((int) ($isi['posisi'] ?? 1), count($alur)) - 1;

        if ($tanya === 'urutan') {
            return $this->urutanOrgan($sistem, $warnaIsi, $garis);
        }

        $bahan = [
            'svg' => self::svgAlur($alur, $sistem['nama'], $i + 1, $tanya === 'hilang', true, $warnaIsi, $garis),
            'keterangan' => 'Alur '.$sistem['nama'].': '.implode(' → ', $alur).'.',
        ];

        $awalan = 'Perhatikan bagan alur '.$sistem['nama'].' berikut.';

        return $bahan + match ($tanya) {
            'hilang' => $this->hilang($awalan, $sistem, $i),
            'fungsi' => $this->fungsi($awalan, $sistem, $i),
            default => $this->berikut($awalan, $sistem, $i),
        };
    }

    protected function berikut(string $awalan, array $sistem, int $i): array
    {
        $alur = $sistem['alur'];

        if ($i + 1 >= count($alur)) {
            // Organ terakhir tidak punya penerus; yang ditanyakan dibalik
            // menjadi organ sebelumnya supaya butirnya tetap sah.
            return $this->sebelum($awalan, $sistem, $i);
        }

        $benar = $alur[$i + 1];

        return [
            'judul' => 'Setelah '.$alur[$i].': '.$benar,
            'teks' => $awalan.' Organ yang dilalui setelah '.$alur[$i].' adalah ...',
            'pernyataan' => $awalan.' Organ yang dilalui setelah '.$alur[$i].' adalah %s.',
            'pilihan' => self::pilihanOrgan($benar, $alur),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan pada '.$sistem['nama'].': '.implode(' → ', $alur)
                .'. Setelah '.$alur[$i].' adalah '.$benar.', yang berfungsi '
                .$sistem['fungsi'][$benar].'.',
        ];
    }

    protected function sebelum(string $awalan, array $sistem, int $i): array
    {
        $alur = $sistem['alur'];
        $benar = $alur[$i - 1];

        return [
            'judul' => 'Sebelum '.$alur[$i].': '.$benar,
            'teks' => $awalan.' Organ yang dilalui sebelum '.$alur[$i].' adalah ...',
            'pernyataan' => $awalan.' Organ yang dilalui sebelum '.$alur[$i].' adalah %s.',
            'pilihan' => self::pilihanOrgan($benar, $alur),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan pada '.$sistem['nama'].': '.implode(' → ', $alur)
                .'. Sebelum '.$alur[$i].' adalah '.$benar.', yang berfungsi '
                .$sistem['fungsi'][$benar].'.',
        ];
    }

    protected function hilang(string $awalan, array $sistem, int $i): array
    {
        $alur = $sistem['alur'];
        $benar = $alur[$i];

        return [
            'judul' => 'Organ yang hilang: '.$benar,
            'teks' => $awalan.' Organ yang tepat untuk mengisi kotak bertanda tanya adalah ...',
            'pernyataan' => $awalan.' Kotak bertanda tanya pada bagan itu seharusnya diisi %s.',
            'pilihan' => self::pilihanOrgan($benar, $alur),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan pada '.$sistem['nama'].': '.implode(' → ', $alur)
                .'. Kotak ke-'.($i + 1).' diisi '.$benar.', yang berfungsi '
                .$sistem['fungsi'][$benar].'.',
        ];
    }

    protected function fungsi(string $awalan, array $sistem, int $i): array
    {
        $organ = $sistem['alur'][$i];
        $benar = ucfirst($sistem['fungsi'][$organ]);

        return [
            'judul' => ucfirst($organ).': fungsinya',
            'teks' => $awalan.' Organ yang ditandai pada bagan itu, yaitu '.$organ.', berfungsi untuk ...',
            'pernyataan' => $awalan.' Organ '.$organ.' pada bagan itu berfungsi untuk %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                // Pengecohnya fungsi organ lain pada sistem yang sama: tiap
                // pilihan merupakan fungsi yang nyata, hanya tertukar
                // organnya — persis kekeliruan yang hendak diperiksa.
                array_merge([$sistem['fungsi'][$organ]], array_values(array_diff(
                    array_values($sistem['fungsi']), [$sistem['fungsi'][$organ]],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => ucfirst($organ).' berada pada urutan ke-'.($i + 1).' dalam '
                .$sistem['nama'].' dan berfungsi '.$sistem['fungsi'][$organ].'.',
        ];
    }

    protected function urutanOrgan(array $sistem, string $warnaIsi, string $garis): array
    {
        $alur = $sistem['alur'];
        $benar = implode(' → ', $alur);

        return [
            'svg' => self::svgAlur($alur, $sistem['nama'], 0, false, false, $warnaIsi, $garis),
            'keterangan' => 'Organ-organ pada '.$sistem['nama'].', tidak berurutan.',
            'judul' => 'Urutan '.$sistem['nama'],
            'teks' => 'Perhatikan organ-organ pada '.$sistem['nama'].' berikut. Urutan yang '
                .'benar dari awal sampai akhir adalah ...',
            'pernyataan' => 'Pada '.$sistem['nama'].' berikut, urutan yang benar adalah %s.',
            'pilihan' => array_map(
                fn ($u) => ['teks' => $u, 'urut' => $u],
                // Pengecohnya urutan yang benar dengan dua organ tertukar —
                // bukan urutan acak, supaya yang hampir hafal tetap harus
                // memeriksa letak tiap organ.
                array_merge([$benar], array_values(array_diff(array_unique([
                    implode(' → ', self::tukar($alur, 1, 2)),
                    implode(' → ', self::tukar($alur, 0, 1)),
                    implode(' → ', self::tukar($alur, count($alur) - 2, count($alur) - 1)),
                    implode(' → ', self::tukar($alur, 2, 3)),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan yang benar pada '.$sistem['nama'].' adalah '.$benar.'.',
        ];
    }

    /** @return array<int, string> */
    protected static function tukar(array $alur, int $i, int $j): array
    {
        [$alur[$i], $alur[$j]] = [$alur[$j], $alur[$i]];

        return $alur;
    }

    /**
     * Pengecoh organ: organ lain pada sistem yang sama. Yang keliru biasanya
     * mengenal semua organnya tetapi salah menempatkan urutannya.
     *
     * @param  array<int, string>  $alur
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanOrgan(string $benar, array $alur): array
    {
        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_merge([$benar], array_values(array_diff($alur, [$benar]))),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bagan alur berkelok: kotak organ ditata tiga per baris, baris berikutnya
     * berbalik arah, sehingga alur sepanjang tujuh organ pun tetap muat tanpa
     * mengecilkan tulisannya.
     *
     * @param  array<int, string>  $alur
     * @param  int  $tandai  organ yang ditonjolkan (1..n), 0 berarti tidak ada
     * @param  bool  $berurutan  false berarti organnya diacak dan panahnya dilepas
     */
    public static function svgAlur(array $alur, string $judul, int $tandai, bool $sembunyi, bool $berurutan, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $n = count($alur);
        $perBaris = 3;
        $baris = (int) ceil($n / $perBaris);
        [$lebar, $tinggi] = [124, 48];
        $kolom = [84, 240, 396];
        $y0 = 74 + (3 - $baris) * 39;

        $tampil = $berurutan
            ? array_values($alur)
            : array_map(fn ($k) => $alur[$k], self::ACAK[$n] ?? array_keys($alur));

        $kanvas->teksPx([Kanvas::LEBAR / 2, 28], ucfirst($judul), ['ukuran' => 16, 'tebal' => true]);

        $letak = [];

        foreach ($tampil as $k => $nama) {
            $r = intdiv($k, $perBaris);

            // Baris genap mengalir ke kanan, baris ganjil berbalik ke kiri,
            // sehingga kotak terakhir sebuah baris selalu bersebelahan tegak
            // dengan kotak pertama baris berikutnya. Pada bagan tak berurutan
            // tidak ada panah yang harus bersambung, jadi baris yang tidak
            // penuh ditaruh di tengah supaya tidak terlihat timpang.
            $c = $r % 2 === 0 ? $k % $perBaris : $perBaris - 1 - ($k % $perBaris);
            $isiBaris = min($perBaris, $n - $r * $perBaris);

            $letak[$k] = [
                $berurutan
                    ? $kolom[$c]
                    : Kanvas::LEBAR / 2 + (($k % $perBaris) - ($isiBaris - 1) / 2) * 156,
                $y0 + $r * ($tinggi + 30),
            ];

            $ini = $berurutan && $tandai === $k + 1;

            $kanvas->kotakPx($letak[$k], $lebar, $tinggi, $ini && $sembunyi ? '?' : $nama,
                $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini && $sembunyi ? 26 : 12,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 2,
                ]);
        }

        if ($berurutan) {
            for ($k = 1; $k < $n; $k++) {
                $kanvas->panahKotakPx($letak[$k - 1], $letak[$k], $lebar, $tinggi, Kanvas::HITAM, 2.2);
            }
        } else {
            $kanvas->teksPx([Kanvas::LEBAR / 2, 288], 'kotak di atas belum tersusun berurutan', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ]);
        }

        return $kanvas->render();
    }
}

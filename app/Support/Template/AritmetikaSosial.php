<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template aritmetika sosial: untung-rugi, diskon, bruto-neto-tara, dan
 * bunga tunggal.
 *
 * Keempat topiknya memakai satu gambar yang sama — kartu keterangan seperti
 * nota atau label harga — sehingga yang berganti hanya barisnya. Angka pada
 * kartu itu persis angka yang dipakai menghitung kunci, jadi soal tidak
 * pernah menyebut harga yang berbeda dengan yang tertera.
 *
 * Pengecohnya diambil dari kekeliruan yang nyata: menjawab potongannya
 * padahal yang ditanya harga bayarnya, menghitung persentase untung
 * terhadap harga jual alih-alih harga beli, dan melupakan lama menabung
 * pada bunga tunggal.
 */
class AritmetikaSosial extends Template
{
    /** @var array<string, string> */
    public const TOPIK = [
        'untung_rugi' => 'Untung atau rugi',
        'diskon' => 'Diskon',
        'bruto' => 'Bruto, neto, dan tara',
        'bunga' => 'Bunga tunggal tabungan',
    ];

    /** @var array<string, string> */
    public const TANYA_UNTUNG = [
        'nominal' => 'Besar untung atau ruginya',
        'persen' => 'Persentase untung atau ruginya',
    ];

    /** @var array<string, string> */
    public const TANYA_DISKON = [
        'bayar' => 'Harga yang harus dibayar',
        'potongan' => 'Besar potongan harganya',
    ];

    /** @var array<string, string> */
    public const TANYA_BRUTO = [
        'neto' => 'Berat neto',
        'tara' => 'Berat tara',
    ];

    /** @var array<string, string> */
    public const TANYA_BUNGA = [
        'bunga' => 'Besar bunga yang diperoleh',
        'total' => 'Jumlah tabungan seluruhnya',
    ];

    public static function kode(): string
    {
        return 'aritmetika-sosial';
    }

    public static function nama(): string
    {
        return 'Aritmetika Sosial';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Nota dan label harga: untung-rugi beserta persentasenya, diskon, '
            .'bruto-neto-tara, serta bunga tunggal tabungan.';
    }

    public static function ikon(): string
    {
        return 'bi-tags';
    }

    public static function kataKunci(): array
    {
        return ['aritmetika sosial', 'untung', 'rugi', 'persentase untung', 'diskon',
            'potongan harga', 'bruto', 'neto', 'tara', 'bunga tunggal', 'tabungan',
            'harga jual', 'harga beli', 'jual beli', 'persen'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'topik',
                'label' => 'Topik',
                'jenis' => 'pilihan',
                'pilihan' => self::TOPIK,
                'awal' => 'untung_rugi',
            ],
            [
                'nama' => 'barang',
                'label' => 'Nama barang',
                'jenis' => 'teks',
                'awal' => 'sepeda bekas',
                'tampil' => ['topik' => ['untung_rugi', 'diskon']],
            ],
            self::kolomAngka('beli', 'Harga pembelian (Rp)', 800000, 100, 1000000000, [
                'langkah' => 1, 'tampil' => ['topik' => ['untung_rugi']],
            ]),
            self::kolomAngka('jual', 'Harga penjualan (Rp)', 1000000, 100, 1000000000, [
                'langkah' => 1, 'tampil' => ['topik' => ['untung_rugi']],
            ]),
            [
                'nama' => 'tanya_untung',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_UNTUNG,
                'awal' => 'nominal',
                'tampil' => ['topik' => ['untung_rugi']],
            ],
            self::kolomAngka('harga', 'Harga sebelum diskon (Rp)', 250000, 100, 1000000000, [
                'langkah' => 1, 'tampil' => ['topik' => ['diskon']],
            ]),
            self::kolomAngka('diskon', 'Besar diskon (%)', 20, 1, 99, [
                'langkah' => 1, 'tampil' => ['topik' => ['diskon']],
            ]),
            [
                'nama' => 'tanya_diskon',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_DISKON,
                'awal' => 'bayar',
                'tampil' => ['topik' => ['diskon']],
            ],
            [
                'nama' => 'isi_karung',
                'label' => 'Isi karung',
                'jenis' => 'teks',
                'awal' => 'beras',
                'tampil' => ['topik' => ['bruto']],
            ],
            self::kolomAngka('bruto', 'Bruto (kg)', 50, 1, 100000, [
                'tampil' => ['topik' => ['bruto']],
            ]),
            self::kolomAngka('tara', 'Tara (%)', 2, 1, 50, [
                'tampil' => ['topik' => ['bruto']],
            ]),
            [
                'nama' => 'tanya_bruto',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_BRUTO,
                'awal' => 'neto',
                'tampil' => ['topik' => ['bruto']],
            ],
            self::kolomAngka('modal', 'Uang yang ditabung (Rp)', 2000000, 1000, 1000000000, [
                'langkah' => 1, 'tampil' => ['topik' => ['bunga']],
            ]),
            self::kolomAngka('bunga_persen', 'Bunga per tahun (%)', 6, 1, 100, [
                'tampil' => ['topik' => ['bunga']],
            ]),
            self::kolomAngka('lama', 'Lama menabung (bulan)', 8, 1, 120, [
                'langkah' => 1, 'tampil' => ['topik' => ['bunga']],
            ]),
            [
                'nama' => 'tanya_bunga',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA_BUNGA,
                'awal' => 'bunga',
                'tampil' => ['topik' => ['bunga']],
            ],
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['topik'] ?? 'untung_rugi') !== 'untung_rugi') {
            return [];
        }

        // Harga jual yang sama dengan harga belinya tidak untung dan tidak
        // rugi, sehingga tidak ada nilai yang dapat ditanyakan.
        if (abs((float) ($isi['jual'] ?? 0) - (float) ($isi['beli'] ?? 0)) < 0.0001) {
            return ['jual' => 'Harga jual sama dengan harga beli, jadi tidak ada untung maupun rugi.'];
        }

        return [];
    }

    /** Rupiah bergaya Indonesia, mis. "Rp120.000,00". */
    public static function rp(float $nilai): string
    {
        return 'Rp'.number_format($nilai, 2, ',', '.');
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        return match ($isi['topik'] ?? 'untung_rugi') {
            'diskon' => $this->diskon($isi, $warnaIsi, $garis),
            'bruto' => $this->bruto($isi, $warnaIsi, $garis),
            'bunga' => $this->bunga($isi, $warnaIsi, $garis),
            default => $this->untungRugi($isi, $warnaIsi, $garis),
        };
    }

    protected function untungRugi(array $isi, string $warnaIsi, string $garis): array
    {
        $barang = self::bebas($isi, 'barang', 'barang');
        $beli = (float) ($isi['beli'] ?? 800000);
        $jual = (float) ($isi['jual'] ?? 1000000);

        $untung = $jual > $beli;
        $selisih = abs($jual - $beli);
        $kata = $untung ? 'untung' : 'rugi';

        $svg = self::svgKartu('Catatan Jual Beli', [
            // Gambar memakai nilai aslinya — Kanvas meloloskannya sendiri —
            // dengan nilai pengganti yang sama seperti pada teks butirnya.
            ['Barang', trim((string) ($isi['barang'] ?? '')) ?: 'barang'],
            ['Harga pembelian', self::rp($beli)],
            ['Harga penjualan', self::rp($jual)],
        ], null, null, $warnaIsi, $garis);

        $awalan = "Perhatikan catatan jual beli {$barang} berikut.";

        if (($isi['tanya_untung'] ?? 'nominal') === 'persen') {
            $benar = round($selisih / $beli * 100, 2);
            // Pembagi yang keliru: persentase dihitung terhadap harga jual,
            // padahal patokannya selalu harga pembelian.
            $keliru = round($selisih / $jual * 100, 2);

            return [
                'svg' => $svg,
                'keterangan' => 'Catatan jual beli '.$barang.': beli '.self::rp($beli)
                    .', jual '.self::rp($jual).'.',
                'judul' => 'Persentase '.$kata.' '.Angka::ringkas($benar, 2).'%',
                'teks' => $awalan." Berapa persen {$kata} yang diperoleh?",
                'pernyataan' => $awalan." Persentase {$kata}nya adalah %s.",
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::ringkas($n, 2).'%', 'urut' => $n],
                    array_merge([$benar], self::saring($benar, [
                        $keliru, $benar * 2, $benar / 2, 100 - $benar,
                    ], 2)),
                ),
                'kata_kunci' => Angka::ringkas($benar, 2).'%',
                'pembahasan' => ucfirst($kata).' = |'.self::rp($jual).' − '.self::rp($beli).'| = '
                    .self::rp($selisih).'.<br>Persentase '.$kata.' selalu dihitung terhadap '
                    .'<strong>harga pembelian</strong>, bukan harga penjualan.<br>'
                    .'Persentase = '.self::rp($selisih).' ÷ '.self::rp($beli).' × 100% = '
                    .Angka::ringkas($benar, 2).'%.',
            ];
        }

        $benar = ($untung ? 'Untung ' : 'Rugi ').self::rp($selisih);

        return [
            'svg' => $svg,
            'keterangan' => 'Catatan jual beli '.$barang.': beli '.self::rp($beli)
                .', jual '.self::rp($jual).'.',
            'judul' => ucfirst($kata).' '.self::rp($selisih),
            'teks' => $awalan.' Untung atau rugikah penjualan itu, dan berapa besarnya?',
            'pernyataan' => $awalan.' Penjualan itu %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_unique([
                    // Pengecoh pertamanya besarnya benar tetapi arahnya
                    // terbalik — kekeliruan yang paling sering terjadi.
                    ($untung ? 'Rugi ' : 'Untung ').self::rp($selisih),
                    ($untung ? 'Untung ' : 'Rugi ').self::rp($jual),
                    ($untung ? 'Untung ' : 'Rugi ').self::rp($beli),
                    ($untung ? 'Rugi ' : 'Untung ').self::rp($jual),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Harga penjualan '.($untung ? 'lebih tinggi' : 'lebih rendah')
                .' daripada harga pembelian, jadi penjualan itu '.$kata.'.<br>'
                .ucfirst($kata).' = |'.self::rp($jual).' − '.self::rp($beli).'| = '
                .self::rp($selisih).'.',
        ];
    }

    protected function diskon(array $isi, string $warnaIsi, string $garis): array
    {
        $barang = self::bebas($isi, 'barang', 'barang');
        $harga = (float) ($isi['harga'] ?? 250000);
        $persen = (float) ($isi['diskon'] ?? 20);

        $potongan = round($harga * $persen / 100, 2);
        $bayar = round($harga - $potongan, 2);

        $svg = self::svgKartu('Label Harga', [
            ['Barang', trim((string) ($isi['barang'] ?? '')) ?: 'barang'],
            ['Harga', self::rp($harga)],
        ], 'DISKON '.Angka::id($persen).'%', self::rp($harga), $warnaIsi, $garis);

        $awalan = "Perhatikan label harga {$barang} berikut.";
        $keterangan = 'Label harga '.$barang.': '.self::rp($harga).' dengan diskon '
            .Angka::id($persen).'%.';

        if (($isi['tanya_diskon'] ?? 'bayar') === 'potongan') {
            return [
                'svg' => $svg,
                'keterangan' => $keterangan,
                'judul' => 'Potongan '.self::rp($potongan),
                'teks' => $awalan.' Berapakah besar potongan harganya?',
                'pernyataan' => $awalan.' Besar potongan harganya adalah %s.',
                'pilihan' => self::pilihanRupiah($potongan, [$bayar, $harga, $harga + $potongan]),
                'kata_kunci' => self::rp($potongan),
                'pembahasan' => 'Potongan = persentase diskon × harga awal = '.Angka::id($persen)
                    .'% × '.self::rp($harga).' = '.self::rp($potongan).'.',
            ];
        }

        return [
            'svg' => $svg,
            'keterangan' => $keterangan,
            'judul' => 'Bayar '.self::rp($bayar),
            'teks' => $awalan.' Berapakah harga yang harus dibayar?',
            'pernyataan' => $awalan.' Harga yang harus dibayar adalah %s.',
            // Pengecoh pertamanya potongan harganya sendiri: yang ditanya
            // harga bayarnya, bukan besar diskonnya.
            'pilihan' => self::pilihanRupiah($bayar, [$potongan, $harga, $harga + $potongan]),
            'kata_kunci' => self::rp($bayar),
            'pembahasan' => 'Potongan = '.Angka::id($persen).'% × '.self::rp($harga).' = '
                .self::rp($potongan).'.<br>Harga bayar = harga awal − potongan = '
                .self::rp($harga).' − '.self::rp($potongan).' = '.self::rp($bayar).'.',
        ];
    }

    protected function bruto(array $isi, string $warnaIsi, string $garis): array
    {
        $muatan = self::bebas($isi, 'isi_karung', 'beras');
        $bruto = (float) ($isi['bruto'] ?? 50);
        $persen = (float) ($isi['tara'] ?? 2);

        $tara = round($bruto * $persen / 100, 3);
        $neto = round($bruto - $tara, 3);

        $svg = self::svgKartu('Keterangan Karung', [
            ['Isi', trim((string) ($isi['isi_karung'] ?? '')) ?: 'beras'],
            ['Bruto', Angka::ringkas($bruto).' kg'],
            ['Tara', Angka::id($persen).'%'],
        ], null, null, $warnaIsi, $garis);

        $awalan = "Perhatikan keterangan sekarung {$muatan} berikut.";
        $keterangan = 'Karung '.$muatan.' berbruto '.Angka::ringkas($bruto).' kg dengan tara '
            .Angka::id($persen).'%.';
        $dasar = 'Tara = '.Angka::id($persen).'% × '.Angka::ringkas($bruto).' kg = '
            .Angka::ringkas($tara).' kg.<br>Neto = bruto − tara = '.Angka::ringkas($bruto)
            .' − '.Angka::ringkas($tara).' = '.Angka::ringkas($neto).' kg.';

        [$benar, $kandidat, $nama] = ($isi['tanya_bruto'] ?? 'neto') === 'tara'
            ? [$tara, [$neto, $bruto, $bruto + $tara], 'tara']
            : [$neto, [$tara, $bruto, $bruto + $tara], 'neto'];

        return [
            'svg' => $svg,
            'keterangan' => $keterangan,
            'judul' => ucfirst($nama).' '.Angka::ringkas($benar).' kg',
            'teks' => $awalan.' Berapakah '.$nama.'nya?',
            'pernyataan' => $awalan.' Berat '.$nama.'nya adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' kg', 'urut' => $n],
                array_merge([$benar], self::saring($benar, $kandidat, 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => 'Bruto adalah berat seluruhnya, tara berat kemasannya, dan neto berat '
                .'isinya saja.<br>'.$dasar,
        ];
    }

    protected function bunga(array $isi, string $warnaIsi, string $garis): array
    {
        $modal = (float) ($isi['modal'] ?? 2000000);
        $persen = (float) ($isi['bunga_persen'] ?? 6);
        $lama = (float) ($isi['lama'] ?? 8);

        $setahun = round($modal * $persen / 100, 2);
        $bunga = round($setahun * $lama / 12, 2);
        $total = round($modal + $bunga, 2);

        $svg = self::svgKartu('Buku Tabungan', [
            ['Uang ditabung', self::rp($modal)],
            ['Bunga per tahun', Angka::id($persen).'%'],
            ['Lama menabung', Angka::ringkas($lama).' bulan'],
        ], null, null, $warnaIsi, $garis);

        $awalan = 'Perhatikan keterangan tabungan berikut.';
        $keterangan = 'Tabungan '.self::rp($modal).', bunga '.Angka::id($persen)
            .'% per tahun, selama '.Angka::ringkas($lama).' bulan.';
        $dasar = 'Bunga tunggal = modal × suku bunga setahun × lama dalam tahun.<br>'
            .'Bunga = '.self::rp($modal).' × '.Angka::id($persen).'% × '.Angka::ringkas($lama)
            .'/12 = '.self::rp($bunga).'.';

        if (($isi['tanya_bunga'] ?? 'bunga') === 'total') {
            return [
                'svg' => $svg,
                'keterangan' => $keterangan,
                'judul' => 'Tabungan akhir '.self::rp($total),
                'teks' => $awalan.' Berapakah jumlah tabungan seluruhnya setelah waktu itu?',
                'pernyataan' => $awalan.' Jumlah tabungan seluruhnya menjadi %s.',
                'pilihan' => self::pilihanRupiah($total, [$bunga, $modal, $modal + $setahun]),
                'kata_kunci' => self::rp($total),
                'pembahasan' => $dasar.'<br>Jumlah tabungan = modal + bunga = '.self::rp($modal)
                    .' + '.self::rp($bunga).' = '.self::rp($total).'.',
            ];
        }

        return [
            'svg' => $svg,
            'keterangan' => $keterangan,
            'judul' => 'Bunga '.self::rp($bunga),
            'teks' => $awalan.' Berapakah bunga yang diperolehnya?',
            'pernyataan' => $awalan.' Bunga yang diperolehnya adalah %s.',
            // Pengecoh terpentingnya bunga setahun penuh: lama menabung
            // terlupa dikalikan.
            'pilihan' => self::pilihanRupiah($bunga, [$setahun, $total, $setahun * $lama]),
            'kata_kunci' => self::rp($bunga),
            'pembahasan' => $dasar.'<br>Perhatikan lamanya: '.Angka::ringkas($lama).' bulan berarti '
                .Angka::ringkas($lama).'/12 tahun, bukan setahun penuh.',
        ];
    }

    /**
     * Pilihan jawaban berupa rupiah.
     *
     * @param  array<int, float>  $kandidat
     * @return array<int, array{teks: string, urut: float}>
     */
    protected static function pilihanRupiah(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => self::rp($n), 'urut' => $n],
            array_merge([$benar], self::saring($benar, array_merge($kandidat, [
                $benar * 2, $benar / 2,
            ]), 2)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Kartu keterangan: nota, label harga, atau buku tabungan. Tiap baris
     * berisi nama keterangan di kiri dan nilainya di kanan.
     *
     * @param  array<int, array{0: string, 1: string}>  $baris
     * @param  string|null  $lencana  tulisan miring di pojok, mis. "DISKON 20%"
     * @param  string|null  $dicoret  nilai yang dicoret karena sudah tidak berlaku
     */
    public static function svgKartu(string $judul, array $baris, ?string $lencana, ?string $dicoret, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$x, $lebar] = [66, 348];
        $tinggiKepala = 44;
        $tinggiBaris = 46;
        $tinggi = $tinggiKepala + count($baris) * $tinggiBaris;
        $y = (Kanvas::TINGGI - $tinggi) / 2;

        $kanvas
            ->tambah('<rect x="'.$x.'" y="'.round($y, 1).'" width="'.$lebar.'" height="'.$tinggi
                .'" rx="10" fill="#ffffff" stroke="'.$garis.'" stroke-width="2.5"/>')
            ->tambah('<path d="M '.($x + 10).' '.round($y, 1).' h '.($lebar - 20).' a 10 10 0 0 1 10 10 v '
                .($tinggiKepala - 10).' h -'.$lebar.' v -'.($tinggiKepala - 10)
                .' a 10 10 0 0 1 10 -10 Z" fill="'.$isi.'"/>')
            ->teksPx([$x + $lebar / 2, $y + 29], $judul, ['ukuran' => 16, 'tebal' => true]);

        foreach ($baris as $i => [$nama, $nilai]) {
            $tengah = $y + $tinggiKepala + $i * $tinggiBaris + $tinggiBaris / 2;

            if ($i > 0) {
                $kanvas->garis([$x + 14, $tengah - $tinggiBaris / 2], [$x + $lebar - 14, $tengah - $tinggiBaris / 2],
                    '#e3e6ea', 1.5);
            }

            $kanvas
                ->teksPx([$x + 20, $tengah + 5], $nama, ['anchor' => 'start', 'ukuran' => 14, 'warna' => '#4b5563'])
                ->teksPx([$x + $lebar - 20, $tengah + 5], $nilai, [
                    'anchor' => 'end', 'ukuran' => 15, 'tebal' => true,
                ]);

            // Harga yang dicoret: garis mendatar menembus tulisannya, cara
            // label toko menunjukkan harga yang sudah tidak berlaku.
            if ($dicoret !== null && $nilai === $dicoret) {
                $kanvas->garis([$x + $lebar - 20 - mb_strlen($nilai) * 8.4, $tengah],
                    [$x + $lebar - 18, $tengah], '#b23a5c', 2.5);
            }
        }

        if ($lencana !== null) {
            $kanvas
                ->kotakPx([400, $y + 30], 118, 42, $lencana, '#fbcfd9', '#b23a5c', [
                    'ukuran' => 14, 'tebal' => true,
                ]);
        }

        return $kanvas->render();
    }
}

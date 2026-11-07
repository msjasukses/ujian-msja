<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template diagram arus lingkar kegiatan ekonomi dua sektor.
 *
 * Bagannya hanya memperlihatkan rangkanya: dua pelaku dan empat arus
 * bernomor beserta arahnya. Nama pasar dan isi tiap arus sengaja tidak
 * dituliskan, sebab justru itulah yang ditanyakan — kalau dituliskan,
 * soalnya tinggal menyalin keterangan gambar.
 */
class ArusEkonomi extends Template
{
    /** @var array<string, string> */
    public const PELAKU = [
        'rtk' => 'Rumah Tangga Konsumen',
        'rtp' => 'Rumah Tangga Produsen',
    ];

    /**
     * Empat arus pada model dua sektor, berurutan dari atas ke bawah pada
     * bagannya. Nomornya = kunci array ini.
     *
     * @var array<int, array{dari: string, isi: string, pasar: string}>
     */
    public const ARUS = [
        1 => [
            'dari' => 'rtk',
            'isi' => 'Penyerahan faktor produksi: tenaga kerja, tanah, modal, dan keahlian',
            'pasar' => 'faktor',
        ],
        2 => [
            'dari' => 'rtp',
            'isi' => 'Pembayaran balas jasa faktor produksi: upah, sewa, bunga, dan laba',
            'pasar' => 'faktor',
        ],
        3 => [
            'dari' => 'rtp',
            'isi' => 'Penyerahan barang dan jasa hasil produksi',
            'pasar' => 'barang',
        ],
        4 => [
            'dari' => 'rtk',
            'isi' => 'Pembayaran atas barang dan jasa yang dibeli',
            'pasar' => 'barang',
        ],
    ];

    /** @var array<string, string> */
    public const PASAR = [
        'faktor' => 'Pasar faktor produksi',
        'barang' => 'Pasar barang dan jasa',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'arus' => 'Isi sebuah arus bernomor',
        'pasar' => 'Pasar tempat arus itu berlangsung',
        'pelaku' => 'Pelaku yang mengirim arus itu',
    ];

    public static function kode(): string
    {
        return 'arus-ekonomi';
    }

    public static function nama(): string
    {
        return 'Arus Lingkar Ekonomi';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Diagram arus lingkar kegiatan ekonomi dua sektor: membaca isi tiap arus '
            .'bernomor, pasar tempatnya berlangsung, serta pelaku yang mengirimnya.';
    }

    public static function ikon(): string
    {
        return 'bi-currency-exchange';
    }

    public static function kataKunci(): array
    {
        return ['arus lingkar', 'circular flow', 'kegiatan ekonomi', 'dua sektor', 'ekonomi',
            'rumah tangga konsumen', 'rumah tangga produsen', 'faktor produksi', 'balas jasa',
            'pasar barang', 'pelaku ekonomi', 'ips'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'arus'),
            self::kolomAngka('nomor', 'Arus nomor', 1, 1, 4, ['langkah' => 1]),
            self::kolomWarna('hijau'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $nomor = (int) ($isi['nomor'] ?? 1);
        $nomor = isset(self::ARUS[$nomor]) ? $nomor : 1;
        $arus = self::ARUS[$nomor];

        $bahan = [
            'svg' => self::svgArus($nomor, $warnaIsi, $garis),
            'keterangan' => 'Diagram arus lingkar ekonomi dua sektor dengan empat arus bernomor; '
                .'arus nomor '.$nomor.' ditandai.',
        ];

        $awalan = 'Perhatikan diagram arus lingkar kegiatan ekonomi berikut.';
        $letak = $nomor <= 2 ? 'pasangan arus bagian atas' : 'pasangan arus bagian bawah';

        return $bahan + match ($isi['tanya'] ?? 'arus') {
            'pasar' => $this->pasar($awalan, $nomor, $arus, $letak),
            'pelaku' => $this->pelaku($awalan, $nomor, $arus),
            default => $this->arus($awalan, $nomor, $arus, $letak),
        };
    }

    protected function arus(string $awalan, int $nomor, array $arus, string $letak): array
    {
        $benar = $arus['isi'];

        return [
            'judul' => 'Arus '.$nomor.': '.mb_strtolower(mb_substr($benar, 0, 28)).'…',
            'teks' => $awalan.' Arus yang diberi nomor '.$nomor.' menunjukkan ...',
            'pernyataan' => $awalan.' Arus yang diberi nomor '.$nomor.' menunjukkan %s.',
            // Pengecohnya tiga arus lain pada diagram yang sama, jadi
            // semuanya benar sebagai pernyataan tetapi hanya satu yang
            // cocok dengan nomor dan arah panahnya.
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(
                    array_column(self::ARUS, 'isi'), [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Arus nomor '.$nomor.' termasuk '.$letak.', yaitu arus yang melewati '
                .mb_strtolower(self::PASAR[$arus['pasar']]).'. Panahnya berangkat dari '
                .self::PELAKU[$arus['dari']].'.<br>Jadi arus itu adalah '.mb_strtolower($benar).'.',
        ];
    }

    protected function pasar(string $awalan, int $nomor, array $arus, string $letak): array
    {
        $benar = self::PASAR[$arus['pasar']];

        return [
            'judul' => 'Arus '.$nomor.': '.$benar,
            'teks' => $awalan.' Arus nomor '.$nomor.' berlangsung di ...',
            'pernyataan' => $awalan.' Arus nomor '.$nomor.' berlangsung di %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_merge(
                    array_values(self::PASAR),
                    // Dua pasar yang memang ada, tetapi bukan bagian dari
                    // model dua sektor ini.
                    ['Pasar uang', 'Pasar modal'],
                ), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Pada model dua sektor ada dua pasar: pasar faktor produksi untuk arus '
                .'faktor produksi beserta balas jasanya, dan pasar barang dan jasa untuk arus '
                .'barang beserta pembayarannya.<br>Arus nomor '.$nomor.' adalah '
                .mb_strtolower($arus['isi']).' dan termasuk '.$letak.', sehingga berlangsung di '
                .mb_strtolower($benar).'.',
        ];
    }

    protected function pelaku(string $awalan, int $nomor, array $arus): array
    {
        $benar = self::PELAKU[$arus['dari']];
        $tujuan = self::PELAKU[$arus['dari'] === 'rtk' ? 'rtp' : 'rtk'];

        return [
            'judul' => 'Arus '.$nomor.' dari '.mb_strtoupper($arus['dari']),
            'teks' => $awalan.' Arus nomor '.$nomor.' berangkat dari ...',
            'pernyataan' => $awalan.' Arus nomor '.$nomor.' berangkat dari %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_merge(
                    array_values(self::PELAKU),
                    // Dua pelaku ekonomi lain yang baru muncul pada model
                    // tiga dan empat sektor.
                    ['Pemerintah', 'Masyarakat luar negeri'],
                ), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Arah panah menunjukkan dari siapa ke siapa arus itu bergerak.<br>'
                .'Panah nomor '.$nomor.' berangkat dari kotak '.$benar.' menuju '.$tujuan
                .', karena arus itu berupa '.mb_strtolower($arus['isi']).'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Dua kotak pelaku dengan empat panah bernomor di antaranya. */
    public static function svgArus(int $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $lebarKotak = 136;
        $tinggiKotak = 142;
        $kiri = 92;
        $kanan = 388;
        $tengahY = 156;

        $kanvas
            ->teksPx([240, 30], 'Arus Lingkar Kegiatan Ekonomi', ['ukuran' => 15, 'tebal' => true])
            ->kotakPx([$kiri, $tengahY], $lebarKotak, $tinggiKotak,
                self::PELAKU['rtk'], $isi, $garis, ['ukuran' => 13])
            ->kotakPx([$kanan, $tengahY], $lebarKotak, $tinggiKotak,
                self::PELAKU['rtp'], $isi, $garis, ['ukuran' => 13]);

        $tepiKiri = $kiri + $lebarKotak / 2;
        $tepiKanan = $kanan - $lebarKotak / 2;

        foreach (self::ARUS as $nomor => $arus) {
            // Keempat panah ditata merata pada tinggi kotaknya supaya
            // ujungnya benar-benar menyentuh kotak pelakunya.
            $y = $tengahY - $tinggiKotak / 2 + $nomor * $tinggiKotak / (count(self::ARUS) + 1);

            $tebal = $nomor === $ditandai ? 3.0 : 1.8;
            $warna = $nomor === $ditandai ? $garis : '#9aa1a9';

            [$a, $b] = $arus['dari'] === 'rtk'
                ? [[$tepiKiri, $y], [$tepiKanan, $y]]
                : [[$tepiKanan, $y], [$tepiKiri, $y]];

            // Lingkaran nomornya diisi putih supaya angkanya tetap terbaca
            // meski garis panahnya lewat di bawahnya.
            $kanvas
                ->panahPx($a, $b, $warna, $tebal)
                ->tambah('<circle cx="240" cy="'.round($y, 1).'" r="13" fill="#ffffff" stroke="'
                    .$warna.'" stroke-width="'.($nomor === $ditandai ? 2.5 : 1.5).'"/>')
                ->teksPx([240, $y + 5], (string) $nomor, [
                    'ukuran' => 14, 'tebal' => true,
                    'warna' => $nomor === $ditandai ? Kanvas::HITAM : '#6b7280',
                ]);
        }

        return $kanvas
            ->teksPx([240, 264], 'arus nomor '.$ditandai.' digambar lebih tebal', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

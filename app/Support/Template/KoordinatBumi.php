<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template koordinat bumi: garis lintang, garis bujur, dan iklimnya.
 *
 * Titiknya selalu jatuh tepat pada perpotongan garis kisi, sehingga
 * koordinatnya dapat dibaca pasti dengan menghitung garis — bukan dikira-
 * kira. Itulah sebabnya pilihan lintang dan bujurnya dibatasi pada
 * kelipatan garis kisi yang tergambar.
 */
class KoordinatBumi extends Template
{
    /**
     * Daerah iklim menurut jarak lintangnya dari khatulistiwa.
     *
     * @var array<int, array{nama: string, sampai: float}>
     */
    public const IKLIM = [
        ['nama' => 'Tropis', 'sampai' => 23.5],
        ['nama' => 'Subtropis', 'sampai' => 40],
        ['nama' => 'Sedang', 'sampai' => 66.5],
        ['nama' => 'Dingin (kutub)', 'sampai' => 90],
    ];

    /** @var array<string, string> */
    public const LINTANG = ['15' => '15°', '30' => '30°', '45' => '45°', '60' => '60°', '75' => '75°'];

    /** @var array<string, string> */
    public const BUJUR = ['30' => '30°', '60' => '60°', '90' => '90°', '120' => '120°', '150' => '150°'];

    /** @var array<string, string> */
    public const ARAH_LINTANG = ['LU' => 'LU (Lintang Utara)', 'LS' => 'LS (Lintang Selatan)'];

    /** @var array<string, string> */
    public const ARAH_BUJUR = ['BT' => 'BT (Bujur Timur)', 'BB' => 'BB (Bujur Barat)'];

    /** @var array<string, string> */
    public const TANYA = [
        'koordinat' => 'Koordinat titik yang ditandai',
        'iklim' => 'Daerah iklim tempat itu',
        'belahan' => 'Belahan bumi tempat itu',
    ];

    public static function kode(): string
    {
        return 'koordinat-bumi';
    }

    public static function nama(): string
    {
        return 'Koordinat Bumi & Iklim';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Kisi garis lintang dan garis bujur: membaca koordinat sebuah titik, '
            .'menentukan daerah iklimnya, serta belahan bumi tempatnya berada.';
    }

    public static function ikon(): string
    {
        return 'bi-globe2';
    }

    public static function kataKunci(): array
    {
        return ['garis lintang', 'garis bujur', 'koordinat', 'khatulistiwa', 'greenwich',
            'lintang utara', 'lintang selatan', 'bujur timur', 'bujur barat', 'iklim',
            'tropis', 'subtropis', 'kutub', 'peta', 'geografi', 'belahan bumi'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'koordinat'),
            [
                'nama' => 'lintang',
                'label' => 'Garis lintang',
                'jenis' => 'pilihan',
                'pilihan' => self::LINTANG,
                'awal' => '30',
                'lebar' => 6,
            ],
            [
                'nama' => 'arah_lintang',
                'label' => 'Arah lintangnya',
                'jenis' => 'pilihan',
                'pilihan' => self::ARAH_LINTANG,
                'awal' => 'LU',
                'lebar' => 6,
            ],
            [
                'nama' => 'bujur',
                'label' => 'Garis bujur',
                'jenis' => 'pilihan',
                'pilihan' => self::BUJUR,
                'awal' => '60',
                'lebar' => 6,
            ],
            [
                'nama' => 'arah_bujur',
                'label' => 'Arah bujurnya',
                'jenis' => 'pilihan',
                'pilihan' => self::ARAH_BUJUR,
                'awal' => 'BT',
                'lebar' => 6,
            ],
            self::kolomWarna('merah'),
        ];
    }

    /** Nama daerah iklim pada suatu lintang. */
    public static function iklim(float $lintang): string
    {
        foreach (self::IKLIM as $daerah) {
            if (abs($lintang) <= $daerah['sampai']) {
                return $daerah['nama'];
            }
        }

        return 'Dingin (kutub)';
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        $lintang = (float) (isset(self::LINTANG[$isi['lintang'] ?? '']) ? $isi['lintang'] : '30');
        $bujur = (float) (isset(self::BUJUR[$isi['bujur'] ?? '']) ? $isi['bujur'] : '60');
        $arahL = isset(self::ARAH_LINTANG[$isi['arah_lintang'] ?? '']) ? $isi['arah_lintang'] : 'LU';
        $arahB = isset(self::ARAH_BUJUR[$isi['arah_bujur'] ?? '']) ? $isi['arah_bujur'] : 'BT';

        $tulis = self::tulisKoordinat($lintang, $arahL, $bujur, $arahB);

        $bahan = [
            'svg' => self::svgKisi($lintang, $arahL, $bujur, $arahB, $warnaIsi, $garis),
            'keterangan' => 'Kisi garis lintang dan bujur; titik X pada '.$tulis.'.',
        ];

        $awalan = 'Perhatikan kisi garis lintang dan garis bujur berikut.';

        return $bahan + match ($isi['tanya'] ?? 'koordinat') {
            'iklim' => $this->daerahIklim($awalan, $lintang, $arahL, $tulis),
            'belahan' => $this->belahan($awalan, $arahL, $arahB),
            default => $this->koordinat($awalan, $lintang, $arahL, $bujur, $arahB, $tulis),
        };
    }

    /** Koordinat sebagai tulisan baku, mis. "30° LU, 60° BT". */
    public static function tulisKoordinat(float $lintang, string $arahL, float $bujur, string $arahB): string
    {
        return number_format($lintang, 0).'° '.$arahL.', '.number_format($bujur, 0).'° '.$arahB;
    }

    protected function koordinat(string $awalan, float $lintang, string $arahL, float $bujur, string $arahB, string $benar): array
    {
        $lawanL = $arahL === 'LU' ? 'LS' : 'LU';
        $lawanB = $arahB === 'BT' ? 'BB' : 'BT';

        return [
            'judul' => 'Titik X: '.$benar,
            'teks' => $awalan.' Titik X terletak pada koordinat ...',
            'pernyataan' => $awalan.' Titik X terletak pada koordinat %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_unique([
                    // Pengecoh terpentingnya tertukarnya lintang dengan
                    // bujur, lalu arah yang terbalik.
                    self::tulisKoordinat($bujur, $arahL, $lintang, $arahB),
                    self::tulisKoordinat($lintang, $lawanL, $bujur, $arahB),
                    self::tulisKoordinat($lintang, $arahL, $bujur, $lawanB),
                    self::tulisKoordinat($lintang, $lawanL, $bujur, $lawanB),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Garis lintang dihitung dari khatulistiwa ke utara (LU) atau ke '
                .'selatan (LS), sedangkan garis bujur dihitung dari garis Greenwich ke timur (BT) '
                .'atau ke barat (BB).<br>Titik X berada '.number_format($lintang, 0).'° di sebelah '
                .($arahL === 'LU' ? 'utara' : 'selatan').' khatulistiwa dan '
                .number_format($bujur, 0).'° di sebelah '.($arahB === 'BT' ? 'timur' : 'barat')
                .' Greenwich, jadi koordinatnya '.$benar.'.<br>Lintang selalu ditulis lebih dahulu.',
        ];
    }

    protected function daerahIklim(string $awalan, float $lintang, string $arahL, string $tulis): array
    {
        $benar = self::iklim($lintang);

        return [
            'judul' => 'Iklim '.mb_strtolower($benar),
            'teks' => $awalan.' Titik X berada di daerah iklim ...',
            'pernyataan' => $awalan.' Titik X berada di daerah iklim %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(
                    array_column(self::IKLIM, 'nama'), [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Daerah iklim ditentukan oleh jarak lintangnya dari khatulistiwa: '
                .'tropis 0°–23,5°, subtropis 23,5°–40°, sedang 40°–66,5°, dan dingin 66,5°–90°.<br>'
                .'Titik X berada pada '.number_format($lintang, 0).'° '.$arahL
                .', sehingga termasuk daerah iklim '.mb_strtolower($benar).'.<br>'
                .'Perhatikan bahwa yang menentukan hanya lintangnya; bujur tidak berpengaruh.',
        ];
    }

    protected function belahan(string $awalan, string $arahL, string $arahB): array
    {
        $utara = $arahL === 'LU';
        $timur = $arahB === 'BT';
        $benar = 'Belahan bumi '.($utara ? 'utara' : 'selatan').' dan bujur '
            .($timur ? 'timur' : 'barat');

        return [
            'judul' => $benar,
            'teks' => $awalan.' Titik X berada di ...',
            'pernyataan' => $awalan.' Titik X berada di %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Keempat gabungan utara–selatan dengan timur–barat; tidak
                // ada yang mustahil, jadi tak satu pun bisa dicoret langsung.
                array_merge([$benar], array_values(array_diff([
                    'Belahan bumi utara dan bujur timur',
                    'Belahan bumi utara dan bujur barat',
                    'Belahan bumi selatan dan bujur timur',
                    'Belahan bumi selatan dan bujur barat',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Khatulistiwa membagi bumi menjadi belahan utara dan selatan, '
                .'sedangkan garis Greenwich membaginya menjadi bujur timur dan bujur barat.<br>'
                .'Titik X berada di '.($utara ? 'atas' : 'bawah').' khatulistiwa dan di sebelah '
                .($timur ? 'kanan' : 'kiri').' garis Greenwich, jadi '.mb_strtolower($benar).'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Kisi garis lintang dan bujur beserta satu titik bertanda X. Khatulistiwa
     * dan garis Greenwich ditebalkan karena keduanya titik nol perhitungan.
     */
    public static function svgKisi(float $lintang, string $arahL, float $bujur, string $arahB, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$kiri, $kanan, $atas, $bawah] = [66.0, 450.0, 60.0, 252.0];
        $tengahX = ($kiri + $kanan) / 2;
        $tengahY = ($atas + $bawah) / 2;

        $px = fn (float $b) => $tengahX + $b / 180 * ($kanan - $tengahX);
        $py = fn (float $l) => $tengahY - $l / 90 * ($tengahY - $atas);

        $kanvas->teksPx([240, 34], 'Kisi garis lintang dan garis bujur', ['ukuran' => 15, 'tebal' => true]);

        for ($b = -180; $b <= 180; $b += 30) {
            $kanvas->garis([$px($b), $atas], [$px($b), $bawah], '#d6dade', 1);
        }

        for ($l = -90; $l <= 90; $l += 15) {
            $kanvas->garis([$kiri, $py($l)], [$kanan, $py($l)], '#d6dade', 1);
        }

        // Khatulistiwa dan Greenwich: dua garis nol yang menjadi patokan.
        $kanvas
            ->garis([$kiri, $py(0)], [$kanan, $py(0)], Kanvas::HITAM, 2.5)
            ->garis([$px(0), $atas], [$px(0), $bawah], Kanvas::HITAM, 2.5)
            ->teksPx([$kanan + 4, $py(0) - 6], '0° khatulistiwa', [
                'anchor' => 'end', 'ukuran' => 11, 'warna' => '#4b5563',
            ])
            ->teksPx([$px(0), $atas - 6], '0° Greenwich', ['ukuran' => 11, 'warna' => '#4b5563']);

        foreach ([[60, 'LU'], [30, 'LU'], [30, 'LS'], [60, 'LS']] as [$nilai, $arah]) {
            $kanvas->teksPx([$kiri - 8, $py($arah === 'LU' ? $nilai : -$nilai) + 4],
                $nilai.'° '.$arah, ['anchor' => 'end', 'ukuran' => 10, 'warna' => '#4b5563']);
        }

        foreach ([[120, 'BB'], [60, 'BB'], [60, 'BT'], [120, 'BT']] as [$nilai, $arah]) {
            $kanvas->teksPx([$px($arah === 'BT' ? $nilai : -$nilai), $bawah + 18],
                $nilai.'° '.$arah, ['ukuran' => 10, 'warna' => '#4b5563']);
        }

        $x = $px($arahB === 'BT' ? $bujur : -$bujur);
        $y = $py($arahL === 'LU' ? $lintang : -$lintang);

        return $kanvas
            ->garis([$px(0), $y], [$x, $y], $garis, 1.8, true)
            ->garis([$x, $py(0)], [$x, $y], $garis, 1.8, true)
            ->lingkaran([$x, $y], 9, $isi, $garis, 3)
            ->teksPx([$x + 18, $y - 10], 'X', ['ukuran' => 18, 'tebal' => true])
            ->teksPx([240, 286], 'garis tegak tiap 30° bujur, garis mendatar tiap 15° lintang', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

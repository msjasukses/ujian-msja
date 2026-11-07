<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template sel volta.
 *
 * Gambarnya sengaja tidak menuliskan mana anode dan mana katode — justru itu
 * yang ditanyakan. Yang tergambar hanya susunan alatnya, sedangkan potensial
 * elektrodenya diberikan pada teks soal, sehingga siswa harus menentukan
 * sendiri peran tiap elektrode dari angka itu. Logam di sebelah kiri tidak
 * selalu anode: letaknya pada gambar tidak menentukan perannya.
 */
class SelVolta extends Template
{
    /**
     * Logam beserta ion, potensial elektrode standar (volt), dan banyak
     * elektron yang dilepas atau diterimanya.
     *
     * @var array<string, array{nama: string, ion: string, e: float, n: int}>
     */
    public const LOGAM = [
        'Mg' => ['nama' => 'Magnesium', 'ion' => 'Mg^2+', 'e' => -2.37, 'n' => 2],
        'Zn' => ['nama' => 'Seng', 'ion' => 'Zn^2+', 'e' => -0.76, 'n' => 2],
        'Fe' => ['nama' => 'Besi', 'ion' => 'Fe^2+', 'e' => -0.44, 'n' => 2],
        'Ni' => ['nama' => 'Nikel', 'ion' => 'Ni^2+', 'e' => -0.25, 'n' => 2],
        'Pb' => ['nama' => 'Timbal', 'ion' => 'Pb^2+', 'e' => -0.13, 'n' => 2],
        'Cu' => ['nama' => 'Tembaga', 'ion' => 'Cu^2+', 'e' => 0.34, 'n' => 2],
        'Ag' => ['nama' => 'Perak', 'ion' => 'Ag^+', 'e' => 0.80, 'n' => 1],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'elektrode' => 'Anode, katode, dan arah elektron',
        'potensial' => 'Potensial sel (E° sel)',
        'notasi' => 'Notasi sel',
        'reaksi' => 'Reaksi yang terjadi di anode',
    ];

    public static function kode(): string
    {
        return 'sel-volta';
    }

    public static function nama(): string
    {
        return 'Sel Volta';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Rangkaian sel volta dua elektrode: menentukan anode dan katode, '
            .'arah aliran elektron, potensial sel, notasi sel, dan reaksi elektrodenya.';
    }

    public static function ikon(): string
    {
        return 'bi-battery-half';
    }

    public static function kataKunci(): array
    {
        return ['sel volta', 'elektrokimia', 'anode', 'katode', 'potensial sel',
            'notasi sel', 'jembatan garam', 'oksidasi', 'reduksi', 'elektron',
            'deret volta', 'galvani'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'kiri',
                'label' => 'Logam di sebelah kiri',
                'jenis' => 'pilihan',
                'pilihan' => self::pilihanLogam(),
                'awal' => 'Zn',
                'lebar' => 6,
            ],
            [
                'nama' => 'kanan',
                'label' => 'Logam di sebelah kanan',
                'jenis' => 'pilihan',
                'pilihan' => self::pilihanLogam(),
                'awal' => 'Cu',
                'lebar' => 6,
            ],
            self::kolomTanya(self::TANYA, 'elektrode'),
            self::kolomWarna('abu'),
        ];
    }

    /**
     * Daftar logam sebagai isian pilihan, beserta potensial elektrodenya
     * supaya guru dapat memilih tanpa membuka tabel lain.
     *
     * array_combine dipakai karena array_map yang diberi lebih dari satu
     * larik selalu menomori ulang hasilnya, dan lambang logamnya — yang
     * menjadi nilai pilihan — ikut hilang.
     *
     * @return array<string, string>
     */
    protected static function pilihanLogam(): array
    {
        return array_combine(array_keys(self::LOGAM), array_map(
            fn ($l, $s) => $s.' — '.$l['nama'].', E° = '.Angka::id($l['e']).' V',
            self::LOGAM, array_keys(self::LOGAM),
        ));
    }

    public static function periksa(array $isi): array
    {
        if (($isi['kiri'] ?? 'Zn') === ($isi['kanan'] ?? 'Cu')) {
            return ['kanan' => 'Pilih dua logam yang berbeda; sel volta butuh beda potensial.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'abu');

        $kiri = isset(self::LOGAM[$isi['kiri'] ?? '']) ? $isi['kiri'] : 'Zn';
        $kanan = isset(self::LOGAM[$isi['kanan'] ?? '']) ? $isi['kanan'] : 'Cu';

        if ($kiri === $kanan) {
            $kanan = $kiri === 'Zn' ? 'Cu' : 'Zn';
        }

        // Yang berpotensial lebih rendah mengalami oksidasi, jadi dialah
        // anodenya — bukan yang kebetulan digambar di sebelah kiri.
        $anode = self::LOGAM[$kiri]['e'] < self::LOGAM[$kanan]['e'] ? $kiri : $kanan;
        $katode = $anode === $kiri ? $kanan : $kiri;

        $bahan = [
            'svg' => self::svgSel($kiri, $kanan, $warnaIsi, $garis),
            'keterangan' => 'Sel volta dengan elektrode '.$kiri.' di sebelah kiri dan '
                .$kanan.' di sebelah kanan.',
        ];

        $awalan = 'Perhatikan rangkaian sel volta berikut. Diketahui E° '
            .self::potensialTeks($kiri).' dan E° '.self::potensialTeks($kanan).'.';

        return $bahan + match ($isi['tanya'] ?? 'elektrode') {
            'potensial' => $this->potensial($awalan, $anode, $katode),
            'notasi' => $this->notasi($awalan, $anode, $katode),
            'reaksi' => $this->reaksi($awalan, $anode, $katode),
            default => $this->elektrode($awalan, $anode, $katode),
        };
    }

    /** Mis. "Cu²⁺|Cu = +0,34 V". */
    protected static function potensialTeks(string $lambang): string
    {
        $logam = self::LOGAM[$lambang];

        return Rumus::html($logam['ion']).'|'.$lambang.' = '
            .($logam['e'] > 0 ? '+' : '').Angka::id($logam['e']).' V';
    }

    /** Reaksi oksidasi sebuah logam, mis. "Zn → Zn^2+ + 2e^-". */
    public static function oksidasi(string $lambang): string
    {
        $logam = self::LOGAM[$lambang];

        return $lambang.' → '.$logam['ion'].' + '.($logam['n'] > 1 ? $logam['n'] : '').'e^-';
    }

    /** Reaksi reduksi sebuah ion logam, mis. "Cu^2+ + 2e^- → Cu". */
    public static function reduksi(string $lambang): string
    {
        $logam = self::LOGAM[$lambang];

        return $logam['ion'].' + '.($logam['n'] > 1 ? $logam['n'] : '').'e^- → '.$lambang;
    }

    protected function elektrode(string $awalan, string $anode, string $katode): array
    {
        $benar = "{$anode} sebagai anode dan {$katode} sebagai katode, "
            ."elektron mengalir dari {$anode} ke {$katode}";

        return [
            'judul' => 'Anode '.$anode.', katode '.$katode,
            'teks' => $awalan.' Pernyataan yang tepat mengenai sel tersebut adalah ...',
            'pernyataan' => $awalan.' Pada sel tersebut, %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Keempat pilihannya menyilangkan dua hal yang dinilai
                // sekaligus: peran tiap elektrode dan arah aliran elektron.
                // Siswa yang hafal salah satunya saja tetap terjaring.
                [
                    $benar,
                    "{$anode} sebagai anode dan {$katode} sebagai katode, "
                        ."elektron mengalir dari {$katode} ke {$anode}",
                    "{$katode} sebagai anode dan {$anode} sebagai katode, "
                        ."elektron mengalir dari {$anode} ke {$katode}",
                    "{$katode} sebagai anode dan {$anode} sebagai katode, "
                        ."elektron mengalir dari {$katode} ke {$anode}",
                ],
            ),
            'kata_kunci' => 'Anode '.$anode.', katode '.$katode,
            'pembahasan' => 'Logam yang potensial elektrodenya lebih rendah lebih mudah '
                .'teroksidasi, sehingga menjadi anode. E° '.$anode.' = '
                .Angka::id(self::LOGAM[$anode]['e']).' V lebih rendah daripada E° '.$katode
                .' = '.Angka::id(self::LOGAM[$katode]['e']).' V, jadi '.$anode.' adalah anode dan '
                .$katode.' katode.<br>Elektron hasil oksidasi mengalir lewat kawat dari anode '
                .'menuju katode, yaitu dari '.$anode.' ke '.$katode.'.',
        ];
    }

    protected function potensial(string $awalan, string $anode, string $katode): array
    {
        $ea = self::LOGAM[$anode]['e'];
        $ek = self::LOGAM[$katode]['e'];
        $benar = round($ek - $ea, 2);

        return [
            'judul' => 'E° sel = '.Angka::id($benar).' V',
            'teks' => $awalan.' Berapakah potensial sel (E° sel) rangkaian tersebut?',
            'pernyataan' => $awalan.' Potensial sel rangkaian tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => ($n > 0 ? '+' : '').Angka::id($n).' V', 'urut' => $n],
                // Tandanya ikut diuji, jadi pengecoh bernilai negatif tidak
                // dibuang: membalik katode dengan anode adalah kekeliruan
                // yang paling sering terjadi.
                array_merge([$benar], self::saring($benar, [
                    -$benar, $ea + $ek, -($ea + $ek), $ek, $ea, abs($ea) + abs($ek),
                ], 2, false)),
            ),
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => 'E° sel = E° katode − E° anode.<br>Katodenya '.$katode.' ('
                .Angka::id($ek).' V) dan anodenya '.$anode.' ('.Angka::id($ea).' V), sehingga '
                .'E° sel = '.Angka::id($ek).' − ('.Angka::id($ea).') = '.Angka::id($benar).' V.<br>'
                .'Hasilnya selalu positif pada sel volta yang berlangsung spontan.',
        ];
    }

    protected function notasi(string $awalan, string $anode, string $katode): array
    {
        $ionA = self::LOGAM[$anode]['ion'];
        $ionK = self::LOGAM[$katode]['ion'];

        $benar = "{$anode} | {$ionA} || {$ionK} | {$katode}";

        return [
            'judul' => 'Notasi sel '.Rumus::polos($benar),
            'teks' => $awalan.' Notasi sel yang tepat untuk rangkaian tersebut adalah ...',
            'pernyataan' => $awalan.' Notasi sel rangkaian tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => Rumus::html($t), 'urut' => Rumus::polos($t)],
                [
                    $benar,
                    // Terbalik: katode ditulis di kiri, anode di kanan.
                    "{$katode} | {$ionK} || {$ionA} | {$anode}",
                    // Logam dan ionnya tertukar di tiap ruas.
                    "{$ionA} | {$anode} || {$katode} | {$ionK}",
                    "{$anode} | {$ionK} || {$ionA} | {$katode}",
                ],
            ),
            'kata_kunci' => Rumus::polos($benar),
            'pembahasan' => 'Notasi sel ditulis anode | ion anode || ion katode | katode. '
                .'Garis tunggal memisahkan dua wujud yang berbeda, garis ganda mewakili jembatan '
                .'garam.<br>Anodenya '.$anode.' dan katodenya '.$katode.', sehingga notasinya '
                .Rumus::html($benar).'.',
        ];
    }

    protected function reaksi(string $awalan, string $anode, string $katode): array
    {
        $benar = self::oksidasi($anode);

        return [
            'judul' => 'Reaksi anode: '.Rumus::polos($benar),
            'teks' => $awalan.' Reaksi yang berlangsung di anode sel tersebut adalah ...',
            'pernyataan' => $awalan.' Reaksi yang berlangsung di anodenya adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => Rumus::html($t), 'urut' => Rumus::polos($t)],
                // Dua hal disilangkan sekaligus: logam mana yang bereaksi di
                // anode, dan apakah reaksinya oksidasi atau reduksi.
                [
                    $benar,
                    self::reduksi($anode),
                    self::oksidasi($katode),
                    self::reduksi($katode),
                ],
            ),
            'kata_kunci' => Rumus::polos($benar),
            'pembahasan' => 'Di anode selalu berlangsung oksidasi, yaitu pelepasan elektron. '
                .'Anodenya '.$anode.' karena potensial elektrodenya lebih rendah, sehingga '
                .'reaksinya '.Rumus::html($benar).'.<br>Reaksi di katode adalah reduksi, yaitu '
                .Rumus::html(self::reduksi($katode)).'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Rangkaian sel volta: dua gelas berisi larutan, elektrode logam yang
     * terhubung kawat lewat voltmeter, dan jembatan garam di antaranya.
     * Tidak ada tulisan "anode" atau "katode" — itulah yang ditanyakan.
     */
    public static function svgSel(string $kiri, string $kanan, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        foreach ([[60, $kiri, 'end', 104], [280, $kanan, 'start', 376]] as [$x, $lambang, $anchor, $xLabel]) {
            $elektrode = $x + 58;

            $kanvas
                ->tambah('<path d="M '.$x.' 118 L '.$x.' 258 L '.($x + 140).' 258 L '
                    .($x + 140).' 118" fill="none" stroke="'.Kanvas::HITAM
                    .'" stroke-width="3" stroke-linejoin="round"/>')
                ->tambah('<rect x="'.($x + 2).'" y="150" width="136" height="107" fill="#dff0fb"/>')
                ->tambah('<rect x="'.$elektrode.'" y="86" width="22" height="150" fill="'.$isi
                    .'" stroke="'.$garis.'" stroke-width="2"/>')
                ->teksPx([$xLabel, 80], $lambang, ['ukuran' => 20, 'tebal' => true, 'anchor' => $anchor])
                ->rumusPx([$x + 70, 282], 'larutan '.self::LOGAM[$lambang]['ion'], ['ukuran' => 14]);
        }

        // Kawat penghubung beserta voltmeternya, terputus di tengah supaya
        // lingkaran voltmeternya tidak tertimpa garis.
        $kanvas
            ->garis([129, 86], [129, 52], Kanvas::HITAM, 2.5)
            ->garis([129, 52], [218, 52], Kanvas::HITAM, 2.5)
            ->garis([262, 52], [351, 52], Kanvas::HITAM, 2.5)
            ->garis([351, 52], [351, 86], Kanvas::HITAM, 2.5)
            ->lingkaran([240, 52], 22, '#ffffff', Kanvas::HITAM, 2.5)
            ->teksPx([240, 60], 'V', ['ukuran' => 20, 'tebal' => true]);

        // Jembatan garam: pipa terbalik yang kedua ujungnya tercelup ke
        // dalam larutan di kedua gelas.
        $kanvas
            ->tambah('<path d="M 175 182 L 175 108 L 305 108 L 305 182" fill="none" stroke="#c8ccd2" '
                .'stroke-width="15" stroke-linejoin="round" stroke-linecap="round"/>')
            ->tambah('<path d="M 175 182 L 175 108 L 305 108 L 305 182" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="1.5" stroke-linejoin="round"/>')
            ->teksPx([240, 134], 'jembatan garam', ['ukuran' => 12, 'warna' => '#4b5563']);

        return $kanvas->render();
    }
}

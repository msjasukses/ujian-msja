<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template bentuk & perubahan energi.
 *
 * Alatnya digambar polos tanpa tulisan apa pun, karena yang diuji justru
 * mengenali alat lalu menyimpulkan perubahan energinya. Begitu nama alatnya
 * ikut tertulis di gambar, soalnya berubah menjadi hafalan daftar.
 */
class BentukEnergi extends Template
{
    /** Percepatan gravitasi yang dipakai di sekolah. */
    public const GRAVITASI = 10;

    /**
     * Alat beserta perubahan energi dan sumbernya.
     *
     * @var array<string, array{nama: string, dari: string, ke: string, sumber: string}>
     */
    public const ALAT = [
        'setrika' => ['nama' => 'Setrika listrik', 'dari' => 'listrik', 'ke' => 'kalor', 'sumber' => 'Energi listrik'],
        'kipas' => ['nama' => 'Kipas angin', 'dari' => 'listrik', 'ke' => 'gerak', 'sumber' => 'Energi listrik'],
        'lampu' => ['nama' => 'Lampu pijar', 'dari' => 'listrik', 'ke' => 'cahaya', 'sumber' => 'Energi listrik'],
        'radio' => ['nama' => 'Radio', 'dari' => 'listrik', 'ke' => 'bunyi', 'sumber' => 'Energi listrik'],
        'aki' => ['nama' => 'Aki', 'dari' => 'kimia', 'ke' => 'listrik', 'sumber' => 'Energi kimia'],
        'dinamo' => ['nama' => 'Dinamo sepeda', 'dari' => 'gerak', 'ke' => 'listrik', 'sumber' => 'Energi gerak'],
        'panel_surya' => ['nama' => 'Panel surya', 'dari' => 'cahaya', 'ke' => 'listrik', 'sumber' => 'Energi matahari'],
        'kincir_air' => ['nama' => 'Kincir air', 'dari' => 'gerak', 'ke' => 'listrik', 'sumber' => 'Energi air'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'perubahan' => 'Perubahan energi pada alat',
        'sumber' => 'Sumber energi yang dimanfaatkan',
        'potensial' => 'Energi potensial',
        'kinetik' => 'Energi kinetik',
        'usaha' => 'Usaha oleh sebuah gaya',
    ];

    public static function kode(): string
    {
        return 'bentuk-energi';
    }

    public static function nama(): string
    {
        return 'Bentuk & Perubahan Energi';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Perubahan energi pada alat bergambar, sumber energi yang dimanfaatkan, '
            .'serta energi potensial, kinetik, dan usaha.';
    }

    public static function ikon(): string
    {
        return 'bi-brightness-high';
    }

    public static function kataKunci(): array
    {
        return ['energi', 'perubahan energi', 'sumber energi', 'terbarukan', 'potensial',
            'kinetik', 'usaha', 'listrik', 'kalor'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'perubahan'),
            [
                'nama' => 'alat',
                'label' => 'Alat yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => $a['nama'], self::ALAT),
                'awal' => 'setrika',
                'tampil' => ['tanya' => ['perubahan', 'sumber']],
            ],
            self::kolomAngka('massa', 'Massa benda (kg)', 4, 0.1, 10000, [
                'tampil' => ['tanya' => ['potensial', 'kinetik']],
            ]),
            self::kolomAngka('tinggi', 'Ketinggian (m)', 5, 0.1, 1000, [
                'tampil' => ['tanya' => ['potensial']],
            ]),
            self::kolomAngka('kecepatan', 'Kecepatan (m/s)', 6, 0.1, 500, [
                'tampil' => ['tanya' => ['kinetik']],
            ]),
            self::kolomAngka('gaya', 'Gaya (N)', 25, 0.1, 100000, [
                'tampil' => ['tanya' => ['usaha']],
            ]),
            self::kolomAngka('jarak', 'Perpindahan searah gaya (m)', 8, 0.1, 10000, [
                'tampil' => ['tanya' => ['usaha']],
            ]),
            self::kolomWarna('jingga'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        return match ($isi['tanya'] ?? 'perubahan') {
            'sumber' => $this->sumber($isi, $warnaIsi, $garis),
            'potensial' => $this->potensial($isi, $warnaIsi, $garis),
            'kinetik' => $this->kinetik($isi, $warnaIsi, $garis),
            'usaha' => $this->usaha($isi, $warnaIsi, $garis),
            default => $this->perubahan($isi, $warnaIsi, $garis),
        };
    }

    protected function perubahan(array $isi, string $warnaIsi, string $garis): array
    {
        $kode = $isi['alat'] ?? 'setrika';
        $alat = self::ALAT[$kode];
        $benar = 'Energi '.$alat['dari'].' → energi '.$alat['ke'];

        // Pengecohnya berupa perubahan energi alat lain yang sama-sama ada
        // di daftar, jadi semuanya terdengar masuk akal.
        $lain = [];

        foreach (self::ALAT as $k => $a) {
            $teks = 'Energi '.$a['dari'].' → energi '.$a['ke'];

            if ($teks !== $benar) {
                $lain[$teks] = true;
            }
        }

        $lain['Energi '.$alat['ke'].' → energi '.$alat['dari']] = true;

        return [
            'judul' => $alat['nama'],
            'teks' => 'Perhatikan gambar alat berikut. Perubahan energi yang terjadi pada alat itu adalah ...',
            'pernyataan' => 'Perhatikan gambar alat berikut. Perubahan energi yang terjadi padanya adalah %s.',
            'svg' => self::svgAlat($kode, $warnaIsi, $garis),
            'keterangan' => 'Gambar '.mb_strtolower($alat['nama']).'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice(array_keys($lain), 0, self::JUMLAH_OPSI - 1)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $alat['nama'].' mengubah energi '.$alat['dari'].' menjadi energi '
                .$alat['ke'].'.',
        ];
    }

    protected function sumber(array $isi, string $warnaIsi, string $garis): array
    {
        $kode = $isi['alat'] ?? 'setrika';
        $alat = self::ALAT[$kode];
        $benar = $alat['sumber'];

        $semua = array_values(array_unique(array_map(fn ($a) => $a['sumber'], self::ALAT)));

        return [
            'judul' => $alat['nama'].' — '.$benar,
            'teks' => 'Perhatikan gambar alat berikut. Sumber energi yang dimanfaatkan alat itu adalah ...',
            'pernyataan' => 'Perhatikan gambar alat berikut. Sumber energi yang dimanfaatkannya adalah %s.',
            'svg' => self::svgAlat($kode, $warnaIsi, $garis),
            'keterangan' => 'Gambar '.mb_strtolower($alat['nama']).'.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff($semua, [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $alat['nama'].' bekerja dengan memanfaatkan '.mb_strtolower($benar).'.',
        ];
    }

    protected function potensial(array $isi, string $warnaIsi, string $garis): array
    {
        $m = (float) $isi['massa'];
        $h = (float) $isi['tinggi'];
        $ep = round($m * self::GRAVITASI * $h, 2);

        return [
            'judul' => 'Energi potensial '.Angka::dengan($ep, 'joule'),
            'teks' => 'Perhatikan gambar berikut. Benda bermassa '.Angka::dengan($m, 'kg')
                .' berada pada ketinggian '.Angka::dengan($h, 'm')
                .'. Berapakah energi potensialnya? (g = 10 m/s²)',
            'pernyataan' => 'Perhatikan gambar berikut. Energi potensial benda bermassa '
                .Angka::dengan($m, 'kg').' pada ketinggian '.Angka::dengan($h, 'm').' adalah %s.',
            'svg' => self::svgKetinggian($m, $h, $warnaIsi, $garis),
            'keterangan' => 'Benda '.Angka::dengan($m, 'kg').' pada ketinggian '.Angka::dengan($h, 'm').'.',
            'pilihan' => self::pilihan($ep, [$m * $h, $m * self::GRAVITASI, $ep / 2, $ep * 2]),
            'kata_kunci' => Angka::id($ep),
            'pembahasan' => 'Ep = m × g × h = '.Angka::id($m).' × 10 × '.Angka::id($h).' = '
                .Angka::dengan($ep, 'joule').'.',
        ];
    }

    protected function kinetik(array $isi, string $warnaIsi, string $garis): array
    {
        $m = (float) $isi['massa'];
        $v = (float) $isi['kecepatan'];
        $ek = round(0.5 * $m * $v * $v, 2);

        return [
            'judul' => 'Energi kinetik '.Angka::dengan($ek, 'joule'),
            'teks' => 'Perhatikan gambar berikut. Benda bermassa '.Angka::dengan($m, 'kg')
                .' bergerak dengan kecepatan '.Angka::dengan($v, 'm/s')
                .'. Berapakah energi kinetiknya?',
            'pernyataan' => 'Perhatikan gambar berikut. Energi kinetik benda bermassa '
                .Angka::dengan($m, 'kg').' yang bergerak '.Angka::dengan($v, 'm/s').' adalah %s.',
            'svg' => self::svgBergerak($m, $v, $warnaIsi, $garis),
            'keterangan' => 'Benda '.Angka::dengan($m, 'kg').' bergerak '.Angka::dengan($v, 'm/s').'.',
            'pilihan' => self::pilihan($ek, [
                // Lupa mengkuadratkan kecepatan, atau lupa membagi dua.
                0.5 * $m * $v, $m * $v * $v, $m * $v, $ek * 2,
            ]),
            'kata_kunci' => Angka::id($ek),
            'pembahasan' => 'Ek = ½ × m × v² = ½ × '.Angka::id($m).' × '.Angka::id($v).'² = ½ × '
                .Angka::id($m).' × '.Angka::id($v * $v).' = '.Angka::dengan($ek, 'joule').'.',
        ];
    }

    protected function usaha(array $isi, string $warnaIsi, string $garis): array
    {
        $f = (float) $isi['gaya'];
        $s = (float) $isi['jarak'];
        $w = round($f * $s, 2);

        return [
            'judul' => 'Usaha '.Angka::dengan($w, 'joule'),
            'teks' => 'Perhatikan gambar berikut. Sebuah gaya '.Angka::dengan($f, 'N')
                .' memindahkan benda sejauh '.Angka::dengan($s, 'm')
                .' searah gaya itu. Berapakah usaha yang dilakukannya?',
            'pernyataan' => 'Perhatikan gambar berikut. Usaha oleh gaya '.Angka::dengan($f, 'N')
                .' yang memindahkan benda sejauh '.Angka::dengan($s, 'm').' adalah %s.',
            'svg' => self::svgUsaha($f, $s, $warnaIsi, $garis),
            'keterangan' => 'Gaya '.Angka::dengan($f, 'N').' memindahkan benda sejauh '
                .Angka::dengan($s, 'm').'.',
            'pilihan' => self::pilihan($w, [$f + $s, round($f / $s, 2), $w / 2, $w * 2]),
            'kata_kunci' => Angka::id($w),
            'pembahasan' => 'W = F × s = '.Angka::id($f).' × '.Angka::id($s).' = '
                .Angka::dengan($w, 'joule').'.',
        ];
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected static function pilihan(float $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::dengan($n, 'joule'), 'urut' => $n],
            array_merge([$benar], self::saring($benar, $kandidat)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svgAlat(string $kode, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];

        match ($kode) {
            'kipas' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->lingkaran($pusat, 92, '#ffffff', $garis, 3);

                for ($i = 0; $i < 4; $i++) {
                    $sudut = deg2rad($i * 90);
                    $kanvas->elips(
                        [$pusat[0] + 48 * cos($sudut), $pusat[1] + 48 * sin($sudut)],
                        abs(cos($sudut)) > 0.5 ? 44 : 20,
                        abs(cos($sudut)) > 0.5 ? 20 : 44,
                        $isi, $garis, 2,
                    );
                }

                $kanvas->lingkaran($pusat, 16, $garis, $garis, 2)
                    ->garis([$pusat[0], $pusat[1] + 92], [$pusat[0], 270], Kanvas::HITAM, 5);
            })(),
            'lampu' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->lingkaran([$pusat[0], $pusat[1] - 24], 72, $isi, $garis, 3)
                    ->tambah('<rect x="'.($pusat[0] - 28).'" y="'.($pusat[1] + 44).'" width="56" height="52" '
                        .'rx="6" fill="#aab1b8" stroke="'.$garis.'" stroke-width="2.5"/>');

                for ($i = 1; $i <= 3; $i++) {
                    $kanvas->garis([$pusat[0] - 28, $pusat[1] + 44 + $i * 13], [$pusat[0] + 28, $pusat[1] + 44 + $i * 13],
                        $garis, 1.5);
                }

                $kanvas->garisPatah([
                    [$pusat[0] - 20, $pusat[1] + 40], [$pusat[0] - 10, $pusat[1] - 10],
                    [$pusat[0], $pusat[1] + 10], [$pusat[0] + 10, $pusat[1] - 10],
                    [$pusat[0] + 20, $pusat[1] + 40],
                ], $garis, 2.5);
            })(),
            'radio' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->tambah('<rect x="'.($pusat[0] - 120).'" y="'.($pusat[1] - 70).'" width="240" height="140" '
                    .'rx="10" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="3"/>')
                    ->lingkaran([$pusat[0] - 56, $pusat[1]], 40, '#ffffff', $garis, 2.5)
                    ->lingkaran([$pusat[0] + 60, $pusat[1] + 24], 18, '#ffffff', $garis, 2.5)
                    ->tambah('<rect x="'.($pusat[0] + 20).'" y="'.($pusat[1] - 50).'" width="80" height="22" '
                        .'fill="#ffffff" stroke="'.$garis.'" stroke-width="2"/>')
                    ->garis([$pusat[0] + 100, $pusat[1] - 70], [$pusat[0] + 150, $pusat[1] - 130], Kanvas::HITAM, 3);
            })(),
            'aki' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->tambah('<rect x="'.($pusat[0] - 100).'" y="'.($pusat[1] - 60).'" width="200" height="130" '
                    .'rx="8" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="3"/>')
                    ->tambah('<rect x="'.($pusat[0] - 76).'" y="'.($pusat[1] - 82).'" width="30" height="24" '
                        .'fill="#8a929a" stroke="'.$garis.'" stroke-width="2"/>')
                    ->tambah('<rect x="'.($pusat[0] + 46).'" y="'.($pusat[1] - 82).'" width="30" height="24" '
                        .'fill="#8a929a" stroke="'.$garis.'" stroke-width="2"/>')
                    ->teksPx([$pusat[0] - 61, $pusat[1] - 94], '+', ['ukuran' => 22, 'tebal' => true])
                    ->teksPx([$pusat[0] + 61, $pusat[1] - 94], '−', ['ukuran' => 22, 'tebal' => true]);
            })(),
            'dinamo' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->tambah('<rect x="'.($pusat[0] - 40).'" y="'.($pusat[1] - 30).'" width="80" height="110" '
                    .'rx="10" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="3"/>')
                    ->lingkaran([$pusat[0], $pusat[1] - 52], 30, '#8a929a', $garis, 3)
                    ->lingkaran([$pusat[0], $pusat[1] - 52], 10, $garis, $garis, 1)
                    ->garis([$pusat[0] - 40, $pusat[1] + 80], [$pusat[0] - 110, $pusat[1] + 110], Kanvas::HITAM, 3)
                    ->garis([$pusat[0] + 40, $pusat[1] + 80], [$pusat[0] + 110, $pusat[1] + 110], Kanvas::HITAM, 3);
            })(),
            'panel_surya' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->poligon([
                    [$pusat[0] - 130, $pusat[1] + 60], [$pusat[0] - 80, $pusat[1] - 40],
                    [$pusat[0] + 130, $pusat[1] - 40], [$pusat[0] + 80, $pusat[1] + 60],
                ], $isi, $garis, 3);

                for ($i = 1; $i < 4; $i++) {
                    $kanvas->garis(
                        [$pusat[0] - 130 + $i * 52, $pusat[1] + 60],
                        [$pusat[0] - 80 + $i * 52, $pusat[1] - 40],
                        $garis, 1.5,
                    );
                }

                $kanvas->lingkaran([$pusat[0] + 120, $pusat[1] - 100], 28, '#f6c445', '#c08a10', 2.5)
                    ->garis([$pusat[0] - 20, $pusat[1] + 60], [$pusat[0] - 20, $pusat[1] + 100], Kanvas::HITAM, 4);
            })(),
            'kincir_air' => (function () use ($kanvas, $pusat, $isi, $garis) {
                $kanvas->lingkaran($pusat, 86, '#ffffff', $garis, 3)
                    ->lingkaran($pusat, 16, $garis, $garis, 2);

                for ($i = 0; $i < 8; $i++) {
                    $sudut = deg2rad($i * 45);
                    $kanvas->garis($pusat, [
                        $pusat[0] + 86 * cos($sudut),
                        $pusat[1] + 86 * sin($sudut),
                    ], $garis, 3);
                }

                $kanvas->tambah('<rect x="0" y="'.($pusat[1] + 70).'" width="'.Kanvas::LEBAR
                    .'" height="60" fill="'.$isi.'" fill-opacity="0.6"/>');
            })(),
            default => (function () use ($kanvas, $pusat, $isi, $garis) {
                // Setrika: alas berujung lancip dengan pegangan melengkung.
                $kanvas->poligon([
                    [$pusat[0] - 110, $pusat[1] + 60], [$pusat[0] + 110, $pusat[1] + 60],
                    [$pusat[0] + 110, $pusat[1] + 20], [$pusat[0] - 130, $pusat[1] + 20],
                ], $isi, $garis, 3)
                    ->tambah('<path d="M '.($pusat[0] - 90).' '.($pusat[1] + 20).' Q '.$pusat[0].' '
                        .($pusat[1] - 90).' '.($pusat[0] + 90).' '.($pusat[1] + 20)
                        .'" fill="none" stroke="'.$garis.'" stroke-width="16" stroke-linecap="round"/>')
                    ->garis([$pusat[0] + 110, $pusat[1] + 30], [$pusat[0] + 170, $pusat[1] - 10], Kanvas::HITAM, 3);
            })(),
        };

        return $kanvas->render();
    }

    public static function svgKetinggian(float $m, float $h, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 260;
        $atas = 60;

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="200" y="'.($atas - 10).'" width="90" height="56" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>')
            ->teksPx([245, $atas + 24], Angka::dengan($m, 'kg'), ['ukuran' => 16, 'tebal' => true]);

        $kanvas->garis([150, $atas + 46], [150, $tanah], Kanvas::HITAM, 1.5, true)
            ->garis([140, $atas + 46], [200, $atas + 46], Kanvas::HITAM, 1.2)
            ->teksPx([138, ($atas + $tanah) / 2], 'h = '.Angka::dengan($h, 'm'), [
                'anchor' => 'end', 'ukuran' => 15, 'tebal' => true,
            ]);

        return $kanvas->render();
    }

    public static function svgBergerak(float $m, float $v, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 220;

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="140" y="'.($tanah - 80).'" width="110" height="80" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>')
            ->teksPx([195, $tanah - 34], Angka::dengan($m, 'kg'), ['ukuran' => 16, 'tebal' => true]);

        $kanvas->garis([260, $tanah - 40], [360, $tanah - 40], Kanvas::HITAM, 3)
            ->poligon([[372, $tanah - 40], [356, $tanah - 49], [356, $tanah - 31]],
                Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([316, $tanah - 52], 'v = '.Angka::dengan($v, 'm/s'), ['ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }

    public static function svgUsaha(float $f, float $s, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $tanah = 210;

        $kanvas->garis([20, $tanah], [Kanvas::LEBAR - 20, $tanah], Kanvas::HITAM, 4)
            ->tambah('<rect x="90" y="'.($tanah - 70).'" width="90" height="70" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2.5"/>');

        $kanvas->garis([30, $tanah - 36], [82, $tanah - 36], Kanvas::HITAM, 3)
            ->poligon([[90, $tanah - 36], [76, $tanah - 44], [76, $tanah - 28]],
                Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([30, $tanah - 48], 'F = '.Angka::dengan($f, 'N'), [
                'anchor' => 'start', 'ukuran' => 14, 'tebal' => true,
            ]);

        $kanvas->garis([135, $tanah + 32], [400, $tanah + 32], Kanvas::HITAM, 1.5)
            ->garis([135, $tanah + 24], [135, $tanah + 40], Kanvas::HITAM, 1.5)
            ->garis([400, $tanah + 24], [400, $tanah + 40], Kanvas::HITAM, 1.5)
            ->teksPx([268, $tanah + 58], 's = '.Angka::dengan($s, 'm'), ['ukuran' => 15, 'tebal' => true])
            ->tambah('<rect x="355" y="'.($tanah - 70).'" width="90" height="70" fill="'.$isi
                .'" fill-opacity="0.3" stroke="'.$garis.'" stroke-width="2" stroke-dasharray="6 4"/>');

        return $kanvas->render();
    }
}

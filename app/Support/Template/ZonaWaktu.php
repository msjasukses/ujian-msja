<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template zona waktu Indonesia.
 *
 * Ketiga zona digambar sebagai pita bujur bersekala, dan kotanya ditandai
 * tepat pada bujurnya masing-masing. Jadi soal "kota ini masuk zona mana"
 * benar-benar dijawab dengan membaca letaknya terhadap garis batas 112,5°
 * dan 127,5° BT, bukan dengan menghafal daftar kota.
 */
class ZonaWaktu extends Template
{
    /**
     * Tiga zona waktu Indonesia beserta batas bujur dan selisihnya terhadap
     * GMT. Batasnya memang tidak bulat: 112,5° dan 127,5° BT.
     *
     * @var array<string, array{nama: string, penuh: string, gmt: int, dari: float, sampai: float}>
     */
    public const ZONA = [
        'wib' => ['nama' => 'WIB', 'penuh' => 'Waktu Indonesia Barat', 'gmt' => 7,
            'dari' => 95, 'sampai' => 112.5],
        'wita' => ['nama' => 'WITA', 'penuh' => 'Waktu Indonesia Tengah', 'gmt' => 8,
            'dari' => 112.5, 'sampai' => 127.5],
        'wit' => ['nama' => 'WIT', 'penuh' => 'Waktu Indonesia Timur', 'gmt' => 9,
            'dari' => 127.5, 'sampai' => 141],
    ];

    /**
     * Kota beserta bujurnya. Zonanya tidak ditulis melainkan dihitung dari
     * bujurnya, supaya daftar ini tidak mungkin bertentangan dengan batas
     * zona di atas.
     *
     * @var array<string, float>
     */
    public const KOTA = [
        'Banda Aceh' => 95.3,
        'Medan' => 98.7,
        'Jakarta' => 106.8,
        'Pontianak' => 109.3,
        'Denpasar' => 115.2,
        'Makassar' => 119.4,
        'Manado' => 124.8,
        'Ambon' => 128.2,
        'Sorong' => 131.3,
        'Jayapura' => 140.7,
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'zona' => 'Zona waktu sebuah kota',
        'selisih' => 'Pukul berapa di kota lain',
    ];

    public static function kode(): string
    {
        return 'zona-waktu';
    }

    public static function nama(): string
    {
        return 'Zona Waktu Indonesia';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Pita bujur tiga zona waktu Indonesia: menentukan zona sebuah kota '
            .'dari letak bujurnya, serta menghitung pukul berapa di kota lain.';
    }

    public static function ikon(): string
    {
        return 'bi-clock-history';
    }

    public static function kataKunci(): array
    {
        return ['zona waktu', 'wib', 'wita', 'wit', 'waktu indonesia barat', 'tengah',
            'timur', 'bujur', 'gmt', 'perbedaan waktu', 'geografi', 'peta'];
    }

    public static function kolom(): array
    {
        $kota = array_combine(array_keys(self::KOTA), array_map(
            fn ($bujur, $nama) => $nama.' ('.Angka::id($bujur, 1).'° BT)',
            self::KOTA, array_keys(self::KOTA),
        ));

        return [
            self::kolomTanya(self::TANYA, 'zona'),
            [
                'nama' => 'kota',
                'label' => 'Kota asal',
                'jenis' => 'pilihan',
                'pilihan' => $kota,
                'awal' => 'Jakarta',
                'lebar' => 6,
            ],
            [
                'nama' => 'tujuan',
                'label' => 'Kota tujuan',
                'jenis' => 'pilihan',
                'pilihan' => $kota,
                'awal' => 'Jayapura',
                'tampil' => ['tanya' => ['selisih']],
                'lebar' => 6,
            ],
            self::kolomAngka('jam', 'Pukul berapa di kota asal (jam)', 8, 0, 23, [
                'langkah' => 1, 'tampil' => ['tanya' => ['selisih']], 'lebar' => 6,
            ]),
            self::kolomAngka('menit', 'Menitnya', 30, 0, 59, [
                'langkah' => 1, 'tampil' => ['tanya' => ['selisih']], 'lebar' => 6,
            ]),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? 'zona') !== 'selisih') {
            return [];
        }

        $asal = self::zona($isi['kota'] ?? 'Jakarta');
        $tujuan = self::zona($isi['tujuan'] ?? 'Jayapura');

        if ($asal === $tujuan) {
            return ['tujuan' => 'Kedua kota berada di zona waktu yang sama, jadi tidak ada '
                .'selisih waktu yang dapat dihitung. Pilih kota di zona lain.'];
        }

        return [];
    }

    /** Zona waktu sebuah kota, dihitung dari bujurnya. */
    public static function zona(string $kota): string
    {
        $bujur = self::KOTA[$kota] ?? self::KOTA['Jakarta'];

        foreach (self::ZONA as $kunci => $zona) {
            if ($bujur < $zona['sampai']) {
                return $kunci;
            }
        }

        return 'wit';
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $kota = isset(self::KOTA[$isi['kota'] ?? '']) ? $isi['kota'] : 'Jakarta';

        if (($isi['tanya'] ?? 'zona') === 'selisih') {
            $tujuan = isset(self::KOTA[$isi['tujuan'] ?? '']) ? $isi['tujuan'] : 'Jayapura';

            return $this->selisih($kota, $tujuan, (int) ($isi['jam'] ?? 8),
                (int) ($isi['menit'] ?? 30), $warnaIsi, $garis);
        }

        return $this->zonaKota($kota, $warnaIsi, $garis);
    }

    protected function zonaKota(string $kota, string $warnaIsi, string $garis): array
    {
        $kunci = self::zona($kota);
        $zona = self::ZONA[$kunci];
        $benar = $zona['nama'].' (GMT+'.$zona['gmt'].')';

        return [
            'svg' => self::svgPita([$kota], $warnaIsi, $garis),
            'keterangan' => $kota.' terletak pada '.Angka::id(self::KOTA[$kota], 1)
                .'° BT, sehingga masuk zona '.$zona['nama'].'.',
            'judul' => $kota.': '.$zona['nama'],
            'teks' => 'Perhatikan pita zona waktu Indonesia berikut. Kota '.$kota
                .' termasuk zona waktu ...',
            'pernyataan' => 'Pada pita zona waktu Indonesia berikut, kota '.$kota
                .' termasuk zona waktu %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Tiga zona dengan selisih GMT-nya yang benar, ditambah satu
                // yang namanya benar tetapi GMT-nya keliru: nama zona dan
                // selisih waktunya diuji sekaligus.
                array_merge([$benar], array_values(array_diff(array_unique(array_merge(
                    array_map(fn ($z) => $z['nama'].' (GMT+'.$z['gmt'].')', array_values(self::ZONA)),
                    [$zona['nama'].' (GMT+'.($zona['gmt'] + 1).')'],
                )), [$benar]))),
            ),
            'kata_kunci' => $zona['nama'],
            'pembahasan' => 'Batas zona waktu Indonesia ada pada 112,5° BT dan 127,5° BT.<br>'
                .$kota.' terletak pada '.Angka::id(self::KOTA[$kota], 1).'° BT, yaitu di antara '
                .Angka::id($zona['dari'], 1).'° dan '.Angka::id($zona['sampai'], 1).'° BT, '
                .'sehingga termasuk '.$zona['penuh'].' ('.$zona['nama'].') yang selisihnya '
                .'GMT+'.$zona['gmt'].'.',
        ];
    }

    protected function selisih(string $kota, string $tujuan, int $jam, int $menit, string $warnaIsi, string $garis): array
    {
        $zonaAsal = self::ZONA[self::zona($kota)];
        $zonaTujuan = self::ZONA[self::zona($tujuan)];
        $beda = $zonaTujuan['gmt'] - $zonaAsal['gmt'];

        $awal = Angka::pukul($jam, $menit);
        $benar = Angka::pukul(($jam + $beda + 24) % 24, $menit);

        return [
            'svg' => self::svgPita([$kota, $tujuan], $warnaIsi, $garis),
            'keterangan' => $kota.' ('.$zonaAsal['nama'].') dan '.$tujuan.' ('
                .$zonaTujuan['nama'].') berselisih '.abs($beda).' jam.',
            'judul' => $awal.' '.$zonaAsal['nama'].' = '.$benar.' '.$zonaTujuan['nama'],
            'teks' => 'Perhatikan pita zona waktu Indonesia berikut. Ketika di '.$kota
                .' menunjukkan pukul '.$awal.', pukul berapakah di '.$tujuan.'?',
            'pernyataan' => 'Ketika di '.$kota.' pukul '.$awal.', di '.$tujuan.' pukul %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_unique([
                    // Pengecoh terpentingnya arah yang terbalik: selisihnya
                    // dikurangkan padahal seharusnya ditambahkan.
                    Angka::pukul(($jam - $beda + 24) % 24, $menit),
                    $awal,
                    Angka::pukul(($jam + 2 * $beda + 48) % 24, $menit),
                    Angka::pukul(($jam + ($beda > 0 ? 1 : -1) + 24) % 24, $menit),
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => $kota.' berada di zona '.$zonaAsal['nama'].' (GMT+'.$zonaAsal['gmt']
                .') dan '.$tujuan.' di zona '.$zonaTujuan['nama'].' (GMT+'.$zonaTujuan['gmt'].').<br>'
                .'Selisihnya '.abs($beda).' jam, dan '.$tujuan.' berada di sebelah '
                .($beda > 0 ? 'timur' : 'barat').' sehingga waktunya '
                .($beda > 0 ? 'lebih awal' : 'lebih lambat').'.<br>Pukul '.$awal.' '
                .($beda > 0 ? '+ ' : '− ').abs($beda).' jam = pukul '.$benar.'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Pita bujur 95°–141° BT yang terbagi tiga zona. Kota yang terlibat soal
     * ditandai tepat pada bujurnya; kota lain tidak digambar supaya pitanya
     * tidak penuh tulisan.
     *
     * @param  array<int, string>  $ditandai
     */
    public static function svgPita(array $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$kiri, $kanan] = [40.0, 448.0];
        [$dari, $sampai] = [95.0, 141.0];
        $y = 108;
        $tinggi = 64;

        $x = fn (float $bujur) => $kiri + ($bujur - $dari) / ($sampai - $dari) * ($kanan - $kiri);

        $kanvas->teksPx([240, 36], 'Zona Waktu Indonesia', ['ukuran' => 16, 'tebal' => true]);

        foreach (self::ZONA as $i => $zona) {
            $a = $x($zona['dari']);
            $b = $x($zona['sampai']);
            [$isiPita, $garisPita] = array_values(self::WARNA)[$i === 'wib' ? 0 : ($i === 'wita' ? 2 : 5)];

            $kanvas
                ->tambah('<rect x="'.round($a, 1).'" y="'.$y.'" width="'.round($b - $a, 1)
                    .'" height="'.$tinggi.'" fill="'.$isiPita.'" stroke="'.$garisPita.'" stroke-width="2"/>')
                // Selisih GMT tiap zona sengaja tidak dituliskan pada pita.
                // Kalau dituliskan, soal zona maupun soal selisih waktu
                // tinggal disalin dari gambarnya; yang boleh dibaca dari
                // gambar hanyalah letak kotanya di antara garis batas.
                // Namanya ditulis di bagian atas pita, bukan di tengahnya,
                // sebab penanda kotanya duduk tepat di tengah pita.
                ->teksPx([($a + $b) / 2, $y + 20], $zona['nama'], [
                    'ukuran' => 17, 'tebal' => true,
                ]);
        }

        // Garis batas antarzona beserta bujurnya: dari sinilah zona sebuah
        // kota dibaca.
        foreach ([112.5, 127.5] as $batas) {
            $kanvas
                ->garis([$x($batas), $y - 12], [$x($batas), $y + $tinggi + 10], Kanvas::HITAM, 2, true)
                ->teksPx([$x($batas), $y - 18], Angka::id($batas, 1).'° BT', ['ukuran' => 12, 'tebal' => true]);
        }

        // Sumbu bujur di bawah pita.
        $kanvas->garis([$kiri, $y + $tinggi + 28], [$kanan, $y + $tinggi + 28], Kanvas::HITAM, 2);

        foreach ([95, 105, 115, 125, 135] as $tanda) {
            $kanvas
                ->garis([$x($tanda), $y + $tinggi + 23], [$x($tanda), $y + $tinggi + 33], Kanvas::HITAM, 1.5)
                ->teksPx([$x($tanda), $y + $tinggi + 50], $tanda.'°', ['ukuran' => 11, 'warna' => '#4b5563']);
        }

        // Kota pertama ditandai di atas pita dan kota kedua di bawahnya.
        // Keduanya kerap berdekatan bujurnya, dan nama yang bertumpuk membuat
        // soalnya tidak terbaca.
        foreach (array_values($ditandai) as $k => $nama) {
            $bujur = self::KOTA[$nama] ?? 106.8;
            $atas = $k % 2 === 0;
            $tinggiLabel = $atas ? $y - 40 : $y + $tinggi + 76;

            $kanvas
                ->lingkaran([$x($bujur), $y + $tinggi / 2], 6, '#ffffff', $garis, 3)
                // Garis penghubungnya berhenti di tepi pita, tidak menembusnya,
                // supaya tidak memotong nama zona yang tertulis di dalam pita.
                ->garis([$x($bujur), $atas ? $y : $y + $tinggi],
                    [$x($bujur), $tinggiLabel + ($atas ? 6 : -12)], $garis, 1.5, true)
                ->teksPx([$x($bujur), $tinggiLabel], $nama, ['ukuran' => 13, 'tebal' => true]);
        }

        return $kanvas
            ->teksPx([240, 286], 'batas zona: 112,5° BT dan 127,5° BT', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

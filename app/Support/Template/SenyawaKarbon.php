<?php

namespace App\Support\Template;

use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template senyawa karbon: rumus struktur alkana, alkena, dan alkuna.
 *
 * Rumus strukturnya digambar dari aturan dasarnya saja — tiap atom karbon
 * mengikat empat pasangan — sehingga banyak atom H di setiap karbon dihitung
 * dari ikatan rangkap yang ada, bukan dituliskan per senyawa. Karena itu
 * nama, rumus molekul, dan gambar strukturnya tidak mungkin berselisih.
 */
class SenyawaKarbon extends Template
{
    /**
     * Deret homolog beserta akhiran nama dan jenis ikatan rangkapnya.
     *
     * @var array<string, array{nama: string, akhiran: string, orde: int, ikatan: string, jenuh: bool, umum: string, minimal: int}>
     */
    public const DERET = [
        'alkana' => [
            'nama' => 'Alkana',
            'akhiran' => 'ana',
            'orde' => 1,
            'ikatan' => 'Ikatan tunggal',
            'jenuh' => true,
            'umum' => 'C_nH_2n+2',
            'minimal' => 1,
        ],
        'alkena' => [
            'nama' => 'Alkena',
            'akhiran' => 'ena',
            'orde' => 2,
            'ikatan' => 'Ikatan rangkap dua',
            'jenuh' => false,
            'umum' => 'C_nH_2n',
            'minimal' => 2,
        ],
        'alkuna' => [
            'nama' => 'Alkuna',
            'akhiran' => 'una',
            'orde' => 3,
            'ikatan' => 'Ikatan rangkap tiga',
            'jenuh' => false,
            'umum' => 'C_nH_2n-2',
            'minimal' => 2,
        ],
    ];

    /** @var array<int, string> */
    public const AWALAN = [1 => 'met', 2 => 'et', 3 => 'prop', 4 => 'but', 5 => 'pent',
        6 => 'heks', 7 => 'hept', 8 => 'okt'];

    /** @var array<string, string> */
    public const TANYA = [
        'nama' => 'Nama senyawanya',
        'rumus' => 'Rumus molekulnya',
        'ikatan' => 'Jenis ikatan antara dua karbon pertama',
        'jenuh' => 'Jenuh atau tak jenuh',
        'deret' => 'Deret homolognya',
    ];

    public static function kode(): string
    {
        return 'senyawa-karbon';
    }

    public static function nama(): string
    {
        return 'Senyawa Karbon';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Rumus struktur alkana, alkena, dan alkuna sampai delapan atom '
            .'karbon: nama senyawa, rumus molekul, jenis ikatan, dan deret homolognya.';
    }

    public static function ikon(): string
    {
        return 'bi-hexagon';
    }

    public static function kataKunci(): array
    {
        return ['senyawa karbon', 'hidrokarbon', 'alkana', 'alkena', 'alkuna',
            'rumus struktur', 'rumus molekul', 'deret homolog', 'ikatan rangkap',
            'jenuh', 'tak jenuh', 'metana', 'etena', 'organik'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'deret',
                'label' => 'Deret homolog',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($d) => $d['nama'].' ('.$d['ikatan'].')', self::DERET),
                'awal' => 'alkana',
            ],
            self::kolomAngka('jumlah_c', 'Banyak atom karbon', 3, 1, 8, ['langkah' => 1]),
            self::kolomTanya(self::TANYA, 'nama'),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $deret = self::DERET[$isi['deret'] ?? 'alkana'] ?? self::DERET['alkana'];

        if ((int) ($isi['jumlah_c'] ?? 1) < $deret['minimal']) {
            return ['jumlah_c' => $deret['nama'].' butuh sedikitnya '.$deret['minimal']
                .' atom karbon untuk membentuk ikatan rangkapnya.'];
        }

        return [];
    }

    /**
     * Orde ikatan antarkarbon, berurutan dari pasangan pertama. Hanya
     * pasangan pertama yang dibuat rangkap; sisanya tunggal.
     *
     * @return array<int, int>
     */
    public static function ikatan(int $n, int $orde): array
    {
        $ikatan = array_fill(0, max(0, $n - 1), 1);

        if ($n >= 2) {
            $ikatan[0] = $orde;
        }

        return $ikatan;
    }

    /**
     * Banyak atom hidrogen pada tiap karbon: sisa dari empat ikatan setelah
     * dikurangi ikatan ke karbon tetangganya.
     *
     * @param  array<int, int>  $ikatan
     * @return array<int, int>
     */
    public static function hidrogen(int $n, array $ikatan): array
    {
        $hidrogen = [];

        for ($i = 0; $i < $n; $i++) {
            $hidrogen[] = 4 - (($ikatan[$i - 1] ?? 0) + ($ikatan[$i] ?? 0));
        }

        return $hidrogen;
    }

    /** Rumus molekul, mis. CH_4 atau C_3H_6; angka satu tidak ditulis. */
    public static function rumusMolekul(int $n, int $hidrogen): string
    {
        return ($n > 1 ? 'C_'.$n : 'C').'H_'.$hidrogen;
    }

    /** Nama senyawa dari banyak karbon dan akhiran deretnya. */
    public static function namaSenyawa(int $n, string $akhiran): string
    {
        return (self::AWALAN[$n] ?? self::AWALAN[1]).$akhiran;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $kunci = isset(self::DERET[$isi['deret'] ?? '']) ? $isi['deret'] : 'alkana';
        $deret = self::DERET[$kunci];
        $n = max($deret['minimal'], min(8, (int) ($isi['jumlah_c'] ?? 3)));

        $ikatan = self::ikatan($n, $deret['orde']);
        $hidrogen = self::hidrogen($n, $ikatan);
        $totalH = array_sum($hidrogen);

        $bahan = [
            'svg' => self::svgStruktur($n, $ikatan, $hidrogen, $warnaIsi, $garis),
            'keterangan' => 'Rumus struktur '.self::namaSenyawa($n, $deret['akhiran']).', '
                .$n.' atom karbon dan '.$totalH.' atom hidrogen.',
        ];

        $awalan = 'Perhatikan rumus struktur senyawa karbon berikut.';

        return $bahan + match ($isi['tanya'] ?? 'nama') {
            'rumus' => $this->rumus($awalan, $n, $totalH, $deret),
            'ikatan' => $this->jenisIkatan($awalan, $deret),
            'jenuh' => $this->jenuh($awalan, $n, $deret),
            'deret' => $this->deretHomolog($awalan, $n, $deret),
            default => $this->namanya($awalan, $n, $deret),
        };
    }

    protected function namanya(string $awalan, int $n, array $deret): array
    {
        $benar = self::namaSenyawa($n, $deret['akhiran']);

        $lainDeret = array_values(array_filter(
            array_map(fn ($d) => self::namaSenyawa($n, $d['akhiran']), array_values(self::DERET)),
            fn ($t) => $t !== $benar,
        ));

        return [
            'judul' => ucfirst($benar),
            'teks' => $awalan.' Nama senyawa tersebut adalah ...',
            'pernyataan' => $awalan.' Nama senyawa tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                // Pengecohnya nama dengan awalan sama tetapi akhiran berbeda
                // (salah membaca ikatan rangkapnya) dan nama dengan akhiran
                // sama tetapi awalan berbeda (salah menghitung atom karbon).
                // Keduanya diselang-seling supaya empat pilihan yang tampil
                // selalu memuat kedua macam kekeliruan sekaligus.
                array_merge([$benar], array_values(array_diff(array_unique([
                    self::namaSenyawa(min(8, $n + 1), $deret['akhiran']),
                    $lainDeret[0] ?? '',
                    self::namaSenyawa(max(1, $n - 1), $deret['akhiran']),
                    $lainDeret[1] ?? '',
                    self::namaSenyawa(min(8, $n + 2), $deret['akhiran']),
                ]), [$benar, '']))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Rantainya terdiri atas '.$n.' atom karbon, sehingga awalan namanya "'
                .self::AWALAN[$n].'". Ikatan antarkarbonnya '
                .mb_strtolower($deret['ikatan']).', sehingga akhirannya "-'.$deret['akhiran']
                .'". Namanya '.$benar.'.',
        ];
    }

    protected function rumus(string $awalan, int $n, int $totalH, array $deret): array
    {
        $benar = self::rumusMolekul($n, $totalH);

        return [
            'judul' => 'Rumus molekul '.Rumus::polos($benar),
            'teks' => $awalan.' Rumus molekul senyawa tersebut adalah ...',
            'pernyataan' => $awalan.' Rumus molekul senyawa tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => Rumus::html($t), 'urut' => Rumus::polos($t)],
                // Pengecohnya rumus deret lain pada jumlah karbon yang sama —
                // selisihnya hanya dua atom hidrogen, persis kekeliruan yang
                // muncul saat ikatan rangkapnya terlewat.
                array_merge([$benar], array_values(array_diff(array_unique([
                    self::rumusMolekul($n, 2 * $n + 2),
                    self::rumusMolekul($n, 2 * $n),
                    self::rumusMolekul($n, 2 * $n - 2),
                    self::rumusMolekul(min(8, $n + 1), $totalH + 2),
                    self::rumusMolekul(max(1, $n - 1), max(2, $totalH - 2)),
                ]), [$benar]))),
            ),
            'kata_kunci' => Rumus::polos($benar),
            'pembahasan' => 'Hitung atom pada gambarnya: ada '.$n.' atom C dan '.$totalH
                .' atom H, sehingga rumus molekulnya '.Rumus::html($benar).'.<br>'
                .'Rumus umum '.mb_strtolower($deret['nama']).' adalah '
                .Rumus::html($deret['umum']).', dan untuk n = '.$n.' hasilnya sama.',
        ];
    }

    protected function jenisIkatan(string $awalan, array $deret): array
    {
        $benar = $deret['ikatan'];

        return [
            'judul' => $benar,
            'teks' => $awalan.' Ikatan antara atom karbon pertama dan kedua pada struktur itu adalah ...',
            'pernyataan' => $awalan.' Ikatan antara atom karbon pertama dan kedua adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Ikatan tunggal',
                    'Ikatan rangkap dua',
                    'Ikatan rangkap tiga',
                    'Ikatan ion',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Banyak garis di antara dua atom karbon menunjukkan orde ikatannya: '
                .'satu garis berarti ikatan tunggal, dua garis ikatan rangkap dua, dan tiga garis '
                .'ikatan rangkap tiga. Pada gambar ada '.$deret['orde'].' garis, jadi ikatannya '
                .mb_strtolower($benar).'.',
        ];
    }

    protected function jenuh(string $awalan, int $n, array $deret): array
    {
        $benar = $deret['jenuh'] ? 'Hidrokarbon jenuh' : 'Hidrokarbon tak jenuh';

        return [
            'judul' => $benar,
            'teks' => $awalan.' Senyawa tersebut tergolong ...',
            'pernyataan' => $awalan.' Senyawa tersebut tergolong %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Hidrokarbon jenuh',
                    'Hidrokarbon tak jenuh',
                    'Senyawa anorganik',
                    'Senyawa ion',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Hidrokarbon disebut jenuh bila seluruh ikatan antarkarbonnya tunggal, '
                .'dan tak jenuh bila ada ikatan rangkap.<br>Pada struktur itu ikatan antarkarbonnya '
                .mb_strtolower($deret['ikatan']).', sehingga senyawanya tergolong '
                .mb_strtolower($benar).'.',
        ];
    }

    protected function deretHomolog(string $awalan, int $n, array $deret): array
    {
        $benar = $deret['nama'];

        return [
            'judul' => 'Deret '.$benar,
            'teks' => $awalan.' Senyawa tersebut termasuk deret homolog ...',
            'pernyataan' => $awalan.' Senyawa tersebut termasuk deret homolog %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_merge(
                    array_column(self::DERET, 'nama'), ['Alkohol'],
                ), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Deret homolog ditentukan oleh jenis ikatan antarkarbonnya: alkana '
                .'berikatan tunggal, alkena berikatan rangkap dua, dan alkuna berikatan rangkap '
                .'tiga.<br>Struktur itu '.mb_strtolower($deret['ikatan']).', jadi termasuk '
                .mb_strtolower($benar).' dengan rumus umum '.Rumus::html($deret['umum']).'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Rumus struktur mendatar: rantai karbon berderet, atom hidrogen di atas
     * dan di bawah tiap karbon, dan sisanya di kedua ujung rantai.
     *
     * @param  array<int, int>  $ikatan
     * @param  array<int, int>  $hidrogen
     */
    public static function svgStruktur(int $n, array $ikatan, array $hidrogen, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $y = 152;
        $jarak = $n > 1 ? min(76.0, 356 / ($n - 1)) : 0;
        $x0 = (Kanvas::LEBAR - ($n - 1) * $jarak) / 2;

        // Ikatan antarkarbon digambar lebih dahulu supaya ujungnya tertutup
        // huruf C, bukan menempel di sisinya.
        foreach ($ikatan as $i => $orde) {
            $kiri = $x0 + $i * $jarak;
            $simpangan = match ($orde) {
                2 => [-5, 5],
                3 => [-8, 0, 8],
                default => [0],
            };

            foreach ($simpangan as $geser) {
                $kanvas->garis([$kiri + 16, $y + $geser], [$kiri + $jarak - 16, $y + $geser], Kanvas::HITAM, 2.5);
            }
        }

        foreach ($hidrogen as $i => $banyak) {
            $x = $x0 + $i * $jarak;

            // Urutan arahnya dipilih supaya rantai tetap terbaca mendatar:
            // hidrogen mengisi atas dan bawah lebih dahulu, dan baru melebar
            // ke samping pada ujung rantai.
            $arah = match (true) {
                $n === 1 => ['atas', 'bawah', 'kiri', 'kanan'],
                $i === 0 => ['atas', 'bawah', 'kiri'],
                $i === $n - 1 => ['atas', 'bawah', 'kanan'],
                default => ['atas', 'bawah'],
            };

            foreach (array_slice($arah, 0, $banyak) as $ke) {
                [$dx, $dy] = match ($ke) {
                    'atas' => [0, -1],
                    'bawah' => [0, 1],
                    'kiri' => [-1, 0],
                    default => [1, 0],
                };

                $kanvas
                    ->garis([$x + $dx * 15, $y + $dy * 14], [$x + $dx * 36, $y + $dy * 36], Kanvas::HITAM, 2.5)
                    ->teksPx([$x + $dx * 52, $y + $dy * 48 + 7], 'H', ['ukuran' => 21, 'tebal' => true]);
            }

            $kanvas
                ->lingkaran([$x, $y], 15, $isi, $garis, 2)
                ->teksPx([$x, $y + 8], 'C', ['ukuran' => 22, 'tebal' => true]);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 36], 'Rumus struktur', ['ukuran' => 15, 'tebal' => true])
            ->teksPx([Kanvas::LEBAR / 2, 282], 'tiap garis mewakili sepasang elektron ikatan', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ]);

        return $kanvas->render();
    }
}

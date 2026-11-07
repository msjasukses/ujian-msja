<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template piramida penduduk.
 *
 * Bentuk piramidanya digambar dari angka tiap kelompok umur, bukan dari
 * gambar jadi, sehingga ciri yang ditanyakan — alas lebar, hampir sama
 * rata, atau alas menyempit — benar-benar terlihat pada gambarnya. Guru
 * cukup memilih jenis piramidanya dan angkanya menyesuaikan.
 */
class PiramidaPenduduk extends Template
{
    /** Kelompok umur, dari yang termuda di dasar piramida. */
    public const UMUR = ['0–14', '15–29', '30–44', '45–59', '60+'];

    /**
     * Tiga bentuk piramida beserta angka penduduknya dalam juta jiwa.
     *
     * @var array<string, array{nama: string, ciri: string, laki: array<int, float>, perempuan: array<int, float>}>
     */
    public const BENTUK = [
        'ekspansif' => [
            'nama' => 'Ekspansif (limas)',
            'ciri' => 'angka kelahirannya tinggi sehingga penduduk usia mudanya paling banyak',
            'laki' => [24, 16, 11, 6, 3],
            'perempuan' => [23, 16, 11, 7, 3],
        ],
        'stasioner' => [
            'nama' => 'Stasioner (granat)',
            'ciri' => 'angka kelahiran dan kematiannya seimbang sehingga tiap kelompok umur hampir sama banyak',
            'laki' => [12, 13, 12, 10, 7],
            'perempuan' => [12, 13, 12, 10, 8],
        ],
        'konstruktif' => [
            'nama' => 'Konstruktif (batu nisan)',
            'ciri' => 'angka kelahirannya rendah sehingga penduduk usia tuanya lebih banyak daripada yang muda',
            'laki' => [6, 9, 12, 14, 12],
            'perempuan' => [6, 9, 12, 14, 15],
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'jenis' => 'Jenis piramida yang digambar',
        'ciri' => 'Ciri kependudukan yang ditunjukkannya',
        'kelompok' => 'Kelompok umur terbanyak',
    ];

    public static function kode(): string
    {
        return 'piramida-penduduk';
    }

    public static function nama(): string
    {
        return 'Piramida Penduduk';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Piramida penduduk ekspansif, stasioner, dan konstruktif: mengenali '
            .'jenisnya, ciri kependudukannya, serta kelompok umur terbanyaknya.';
    }

    public static function ikon(): string
    {
        return 'bi-bar-chart-steps';
    }

    public static function kataKunci(): array
    {
        return ['piramida penduduk', 'kependudukan', 'demografi', 'ekspansif', 'stasioner',
            'konstruktif', 'kelompok umur', 'angka kelahiran', 'usia produktif', 'geografi'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'bentuk',
                'label' => 'Bentuk piramida',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($b) => $b['nama'], self::BENTUK),
                'awal' => 'ekspansif',
            ],
            self::kolomTanya(self::TANYA, 'jenis'),
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $kunci = isset(self::BENTUK[$isi['bentuk'] ?? '']) ? $isi['bentuk'] : 'ekspansif';
        $bentuk = self::BENTUK[$kunci];

        $jumlah = array_map(
            fn ($l, $p) => $l + $p,
            $bentuk['laki'],
            $bentuk['perempuan'],
        );

        $bahan = [
            'svg' => self::svgPiramida($bentuk, $warnaIsi, $garis),
            'keterangan' => 'Piramida penduduk '.mb_strtolower($bentuk['nama']).' dengan lima '
                .'kelompok umur.',
        ];

        $awalan = 'Perhatikan piramida penduduk berikut.';

        return $bahan + match ($isi['tanya'] ?? 'jenis') {
            'ciri' => [
                'judul' => $bentuk['nama'].': cirinya',
                'teks' => $awalan.' Piramida itu menunjukkan bahwa daerah tersebut ...',
                'pernyataan' => $awalan.' Piramida itu menunjukkan bahwa daerah tersebut %s.',
                'pilihan' => array_map(
                    fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                    // Pengecohnya ciri bentuk piramida yang lain — semuanya
                    // ciri yang nyata, hanya salah pasangan bentuknya. Bentuk
                    // piramidanya hanya tiga, jadi ditambah satu ciri bentuk
                    // regresif supaya pilihannya genap empat.
                    array_merge([$bentuk['ciri']], array_values(array_diff(array_merge(
                        array_column(self::BENTUK, 'ciri'),
                        ['jumlah penduduknya menyusut karena angka kematian lebih tinggi '
                            .'daripada angka kelahiran'],
                    ), [$bentuk['ciri']]))),
                ),
                'kata_kunci' => $bentuk['ciri'],
                'pembahasan' => 'Piramida itu beralas '
                    .match ($kunci) {
                        'ekspansif' => 'lebar lalu menyempit tajam ke atas',
                        'stasioner' => 'hampir sama lebar dari bawah sampai tengah',
                        default => 'sempit dan melebar di bagian atas',
                    }.', ciri piramida '.mb_strtolower($bentuk['nama']).'.<br>Artinya daerah itu '
                    .$bentuk['ciri'].'.',
            ],
            'kelompok' => $this->kelompok($awalan, $jumlah),
            default => [
                'judul' => $bentuk['nama'],
                'teks' => $awalan.' Piramida penduduk itu tergolong jenis ...',
                'pernyataan' => $awalan.' Piramida penduduk itu tergolong jenis %s.',
                'pilihan' => array_map(
                    fn ($t) => ['teks' => $t, 'urut' => $t],
                    array_merge([$bentuk['nama']], array_values(array_diff(array_merge(
                        array_column(self::BENTUK, 'nama'),
                        ['Regresif (penduduk menyusut)'],
                    ), [$bentuk['nama']]))),
                ),
                'kata_kunci' => $bentuk['nama'],
                'pembahasan' => 'Bentuk piramida dibaca dari lebar alasnya dibandingkan puncaknya.<br>'
                    .'Piramida itu '.$bentuk['ciri'].', yang merupakan ciri piramida '
                    .mb_strtolower($bentuk['nama']).'.',
            ],
        };
    }

    /** @param array<int, float> $jumlah */
    protected function kelompok(string $awalan, array $jumlah): array
    {
        $benar = self::UMUR[array_search(max($jumlah), $jumlah, true)].' tahun';

        return [
            'judul' => 'Kelompok terbanyak '.$benar,
            'teks' => $awalan.' Kelompok umur manakah yang penduduknya paling banyak?',
            'pernyataan' => $awalan.' Kelompok umur dengan penduduk terbanyak adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(
                    array_map(fn ($u) => $u.' tahun', self::UMUR), [$benar],
                ))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Banyak penduduk tiap kelompok umur dibaca dari panjang batangnya, '
                .'laki-laki dan perempuan dijumlahkan: '
                .implode(', ', array_map(
                    fn ($u, $n) => $u.' = '.Angka::id($n).' juta',
                    self::UMUR, $jumlah,
                )).'.<br>Yang terbanyak adalah kelompok '.$benar.'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Piramida penduduk: batang laki-laki ke kiri dan perempuan ke kanan. */
    public static function svgPiramida(array $bentuk, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $tengah = 240;
        $lebarLabel = 42;
        $maks = max(array_merge($bentuk['laki'], $bentuk['perempuan']));
        $skala = 148 / max(1, $maks);

        $tinggiBaris = 30;
        $jarak = 6;
        $banyak = count(self::UMUR);
        $dasar = 236;

        $kanvas
            ->teksPx([$tengah, 32], 'Piramida Penduduk', ['ukuran' => 16, 'tebal' => true])
            ->teksPx([$tengah - 110, 54], 'Laki-laki', ['ukuran' => 13, 'tebal' => true])
            ->teksPx([$tengah + 110, 54], 'Perempuan', ['ukuran' => 13, 'tebal' => true]);

        foreach (self::UMUR as $i => $umur) {
            // Kelompok termuda digambar paling bawah, seperti piramida
            // penduduk pada umumnya.
            $y = $dasar - ($i + 1) * ($tinggiBaris + $jarak);

            foreach ([['laki', -1], ['perempuan', 1]] as [$sisi, $arah]) {
                $panjang = $bentuk[$sisi][$i] * $skala;
                $x = $arah < 0 ? $tengah - $lebarLabel - $panjang : $tengah + $lebarLabel;

                $kanvas
                    ->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'
                        .round($panjang, 1).'" height="'.$tinggiBaris.'" fill="'.$isi
                        .'" stroke="'.$garis.'" stroke-width="1.5"/>')
                    ->teksPx([$x + ($arah < 0 ? -6 : $panjang + 6), $y + $tinggiBaris / 2 + 5],
                        Angka::id($bentuk[$sisi][$i]), [
                            'anchor' => $arah < 0 ? 'end' : 'start',
                            'ukuran' => 11, 'warna' => '#4b5563',
                        ]);
            }

            $kanvas->teksPx([$tengah, $y + $tinggiBaris / 2 + 5], $umur, ['ukuran' => 12, 'tebal' => true]);
        }

        return $kanvas
            ->garis([$tengah - $lebarLabel, $dasar - $banyak * ($tinggiBaris + $jarak) - 4],
                [$tengah - $lebarLabel, $dasar], '#9aa1a9', 1.5)
            ->garis([$tengah + $lebarLabel, $dasar - $banyak * ($tinggiBaris + $jarak) - 4],
                [$tengah + $lebarLabel, $dasar], '#9aa1a9', 1.5)
            ->garis([40, $dasar], [440, $dasar], Kanvas::HITAM, 2)
            ->teksPx([$tengah, 262], 'kelompok umur (tahun) · angka dalam juta jiwa', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

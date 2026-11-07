<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template skala peta: jarak pada peta dan jarak sebenarnya.
 *
 * Kekeliruan yang paling sering terjadi pada soal ini bukan perkaliannya,
 * melainkan satuannya — hasil dalam sentimeter dikira kilometer. Karena itu
 * pengecohnya memang dibuat berselisih kelipatan sepuluh ribu dan seratus
 * ribu, supaya siswa yang lupa mengubah satuan menemukan jawabannya di
 * daftar dan guru bisa melihat kekeliruan itu dari sebaran jawaban.
 */
class SkalaPeta extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'sebenarnya' => 'Jarak sebenarnya',
        'peta' => 'Jarak pada peta',
    ];

    /** Sentimeter dalam satu kilometer; dipakai dua arah. */
    protected const CM_PER_KM = 100000;

    public static function kode(): string
    {
        return 'skala-peta';
    }

    public static function nama(): string
    {
        return 'Skala Peta';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Peta dengan dua kota dan skalanya. Tanya jarak sebenarnya '
            .'dari jarak pada peta, atau sebaliknya.';
    }

    public static function ikon(): string
    {
        return 'bi-map';
    }

    public static function kataKunci(): array
    {
        return ['peta', 'skala', 'jarak', 'kilometer', 'denah', 'geografi', 'ips'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'sebenarnya',
            ],
            [
                'nama' => 'skala',
                'label' => 'Skala peta 1 : ...',
                'jenis' => 'angka',
                'awal' => 1500000,
                'min' => 100,
                'max' => 100000000,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'jarak_peta',
                'label' => 'Jarak pada peta (cm)',
                'jenis' => 'angka',
                'awal' => 4,
                'min' => 0.1,
                'max' => 1000,
                'lebar' => 6,
                'tampil' => ['tanya' => ['sebenarnya']],
            ],
            [
                'nama' => 'jarak_nyata',
                'label' => 'Jarak sebenarnya (km)',
                'jenis' => 'angka',
                'awal' => 60,
                'min' => 0.1,
                'max' => 100000,
                'lebar' => 6,
                'tampil' => ['tanya' => ['peta']],
            ],
            [
                'nama' => 'kota_a',
                'label' => 'Nama tempat pertama',
                'jenis' => 'teks',
                'awal' => 'Kota A',
                'lebar' => 6,
            ],
            [
                'nama' => 'kota_b',
                'label' => 'Nama tempat kedua',
                'jenis' => 'teks',
                'awal' => 'Kota B',
                'lebar' => 6,
            ],
            self::kolomWarna('hijau'),
        ];
    }

    protected function susun(array $isi): array
    {
        $skala = (float) $isi['skala'];
        // Nama kota dipakai dua kali: apa adanya untuk gambar, dan dalam
        // bentuk yang sudah diloloskan untuk teks butir yang berisi HTML.
        $a = trim((string) ($isi['kota_a'] ?? '')) ?: 'Kota A';
        $b = trim((string) ($isi['kota_b'] ?? '')) ?: 'Kota B';
        $aman = [self::bebas($isi, 'kota_a', 'Kota A'), self::bebas($isi, 'kota_b', 'Kota B')];

        $skalaTeks = '1 : '.number_format($skala, 0, ',', '.');
        $awalan = "Perhatikan peta berikut. Jarak {$aman[0]} ke {$aman[1]} diukur pada peta berskala {$skalaTeks}.";

        if (($isi['tanya'] ?? 'sebenarnya') === 'sebenarnya') {
            $peta = (float) $isi['jarak_peta'];
            $nyata = round($peta * $skala / self::CM_PER_KM, 2);

            return [
                'judul' => 'Skala peta '.$skalaTeks,
                'teks' => $awalan.' Berapakah jarak sebenarnya kedua kota itu?',
                'pernyataan' => $awalan.' Jarak sebenarnya kedua kota itu adalah %s.',
                'svg' => self::svg($a, $b, Angka::dengan($peta, 'cm'), $skalaTeks, $isi['warna'] ?? 'hijau'),
                'keterangan' => "Peta dengan {$a} dan {$b}, berjarak ".Angka::dengan($peta, 'cm')
                    ." pada peta, skala {$skalaTeks}.",
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::dengan($n, 'km'), 'urut' => $n],
                    array_merge([$nyata], self::saring($nyata, [
                        $nyata * 10, $nyata / 10, $peta * $skala / 1000, $nyata * 100, $nyata / 100,
                    ])),
                ),
                'kata_kunci' => Angka::id($nyata),
                'pembahasan' => 'Jarak sebenarnya = jarak pada peta × skala = '.Angka::id($peta).' cm × '
                    .number_format($skala, 0, ',', '.').' = '.number_format($peta * $skala, 0, ',', '.')
                    .' cm.<br>1 km = '.number_format(self::CM_PER_KM, 0, ',', '.').' cm, jadi '
                    .number_format($peta * $skala, 0, ',', '.').' cm = '.Angka::dengan($nyata, 'km').'.',
            ];
        }

        $nyata = (float) $isi['jarak_nyata'];
        $peta = round($nyata * self::CM_PER_KM / $skala, 2);

        return [
            'judul' => 'Skala peta '.$skalaTeks,
            'teks' => $awalan.' Jarak sebenarnya kedua kota itu '.Angka::dengan($nyata, 'km')
                .'. Berapakah jarak keduanya pada peta?',
            'pernyataan' => $awalan.' Jarak sebenarnya kedua kota itu '.Angka::dengan($nyata, 'km')
                .', sehingga jarak keduanya pada peta adalah %s.',
            'svg' => self::svg($a, $b, '... cm', $skalaTeks, $isi['warna'] ?? 'hijau'),
            'keterangan' => "Peta dengan {$a} dan {$b}, skala {$skalaTeks}.",
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::dengan($n, 'cm'), 'urut' => $n],
                array_merge([$peta], self::saring($peta, [
                    $peta * 10, $peta / 10, $nyata * 1000 / $skala, $peta * 100, $peta / 100,
                ])),
            ),
            'kata_kunci' => Angka::id($peta),
            'pembahasan' => 'Jarak sebenarnya '.Angka::id($nyata).' km = '
                .number_format($nyata * self::CM_PER_KM, 0, ',', '.').' cm.<br>'
                .'Jarak pada peta = jarak sebenarnya ÷ skala = '
                .number_format($nyata * self::CM_PER_KM, 0, ',', '.').' ÷ '
                .number_format($skala, 0, ',', '.').' = '.Angka::dengan($peta, 'cm').'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $a, string $b, string $jarak, string $skalaTeks, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['hijau'];

        $kanvas = new Kanvas(latar: '#eef6fb');

        // Daratan digambar sebagai satu bidang bersudut banyak; bentuk
        // persisnya tidak penting, yang penting peta terbaca sebagai peta
        // dan bukan sebagai diagram.
        $kanvas->poligon([
            [40, 60], [150, 36], [250, 58], [360, 40], [440, 86],
            [420, 180], [330, 214], [220, 196], [120, 222], [46, 170],
        ], $isi, $garis, 2);

        $kotaA = [130, 110];
        $kotaB = [330, 150];

        $kanvas->garis($kotaA, $kotaB, Kanvas::HITAM, 2, true)
            ->lingkaran($kotaA, 7, '#ffffff', Kanvas::HITAM, 2.5)
            ->lingkaran($kotaB, 7, '#ffffff', Kanvas::HITAM, 2.5)
            ->teksPx([$kotaA[0], $kotaA[1] - 14], $a, ['ukuran' => 14, 'tebal' => true])
            ->teksPx([$kotaB[0], $kotaB[1] - 14], $b, ['ukuran' => 14, 'tebal' => true])
            ->teksPx([($kotaA[0] + $kotaB[0]) / 2, ($kotaA[1] + $kotaB[1]) / 2 + 22], $jarak, [
                'ukuran' => 14, 'tebal' => true,
            ]);

        $kanvas->teksPx([Kanvas::LEBAR / 2, 268], 'Skala '.$skalaTeks, ['ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }
}

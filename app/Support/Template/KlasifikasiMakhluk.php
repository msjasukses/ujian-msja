<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template klasifikasi makhluk hidup: urutan takson dan kelompok vertebrata.
 *
 * Dua bagan yang berbeda watak. Urutan takson digambar sebagai corong yang
 * menyempit — bentuknya sendiri sudah menjelaskan bahwa makin ke bawah
 * anggotanya makin sedikit tetapi cirinya makin mirip. Kelompok vertebrata
 * digambar sebagai bagan bercabang dengan contoh anggotanya, sehingga
 * pertanyaannya dapat dijawab dari gambar, bukan semata dari hafalan.
 */
class KlasifikasiMakhluk extends Template
{
    /**
     * Urutan takson dari yang tertinggi ke yang terendah.
     *
     * @var array<int, string>
     */
    public const TAKSON = [
        'Kingdom (kerajaan)',
        'Filum / Divisi',
        'Kelas',
        'Ordo (bangsa)',
        'Famili (suku)',
        'Genus (marga)',
        'Spesies (jenis)',
    ];

    /**
     * Kelompok hewan bertulang belakang beserta ciri khas dan contohnya.
     *
     * @var array<string, array{pendek: string, nama: string, ciri: string, anggota: array<int, string>}>
     */
    public const KELOMPOK = [
        'pisces' => [
            'pendek' => 'Pisces',
            'nama' => 'Pisces (ikan)',
            'ciri' => 'bernapas dengan insang dan tubuhnya bersisik',
            'anggota' => ['ikan mas', 'lele', 'tongkol'],
        ],
        'amphibia' => [
            'pendek' => 'Amphibia',
            'nama' => 'Amphibia (amfibi)',
            'ciri' => 'hidup di air dan di darat serta berkulit lembap',
            'anggota' => ['katak', 'kodok', 'salamander'],
        ],
        'reptilia' => [
            'pendek' => 'Reptilia',
            'nama' => 'Reptilia (hewan melata)',
            'ciri' => 'tubuhnya bersisik kering dan bergerak melata',
            'anggota' => ['ular', 'kadal', 'penyu'],
        ],
        'aves' => [
            'pendek' => 'Aves',
            'nama' => 'Aves (burung)',
            'ciri' => 'tubuhnya berbulu dan berkembang biak dengan bertelur',
            'anggota' => ['merpati', 'ayam', 'elang'],
        ],
        'mammalia' => [
            'pendek' => 'Mammalia',
            'nama' => 'Mammalia (hewan menyusui)',
            'ciri' => 'berkelenjar susu dan tubuhnya berambut',
            'anggota' => ['sapi', 'lumba-lumba', 'kelelawar'],
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'takson' => 'Takson yang hilang dari urutannya',
        'kelompok' => 'Kelompok dari contoh anggotanya',
        'ciri' => 'Ciri khas sebuah kelompok',
    ];

    public static function kode(): string
    {
        return 'klasifikasi-makhluk';
    }

    public static function nama(): string
    {
        return 'Klasifikasi Makhluk Hidup';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Corong urutan takson dari kingdom sampai spesies, serta bagan '
            .'lima kelompok hewan bertulang belakang beserta ciri dan contohnya.';
    }

    public static function ikon(): string
    {
        return 'bi-diagram-3';
    }

    public static function kataKunci(): array
    {
        return ['klasifikasi', 'takson', 'taksonomi', 'kingdom', 'filum', 'kelas', 'ordo',
            'famili', 'genus', 'spesies', 'vertebrata', 'pisces', 'amphibia', 'reptilia',
            'aves', 'mammalia', 'kunci determinasi'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'takson'),
            self::kolomAngka('takson_ke', 'Takson ke- (dari yang tertinggi)', 3, 1, 7, [
                'langkah' => 1, 'tampil' => ['tanya' => ['takson']],
            ]),
            [
                'nama' => 'kelompok',
                'label' => 'Kelompok hewan',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($k) => $k['nama'], self::KELOMPOK),
                'awal' => 'pisces',
                'tampil' => ['tanya' => ['kelompok', 'ciri']],
            ],
            self::kolomWarna('ungu'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $tanya = $isi['tanya'] ?? 'takson';

        if ($tanya === 'takson') {
            return $this->takson((int) ($isi['takson_ke'] ?? 3), $warnaIsi, $garis);
        }

        $kunci = $isi['kelompok'] ?? 'pisces';
        $kelompok = self::KELOMPOK[$kunci] ?? self::KELOMPOK['pisces'];

        return $tanya === 'ciri'
            ? $this->ciri($kunci, $kelompok, $warnaIsi, $garis)
            : $this->kelompok($kunci, $kelompok, $warnaIsi, $garis);
    }

    protected function takson(int $ke, string $warnaIsi, string $garis): array
    {
        $benar = self::TAKSON[$ke - 1];

        return [
            'judul' => 'Takson ke-'.$ke.': '.$benar,
            'teks' => 'Perhatikan bagan urutan takson berikut. Takson yang ditandai huruf X adalah ...',
            'pernyataan' => 'Pada bagan urutan takson berikut, takson yang ditandai huruf X adalah %s.',
            'svg' => self::svgTakson($ke, $warnaIsi, $garis),
            'keterangan' => 'Urutan takson dari yang tertinggi ke yang terendah; takson ke-'
                .$ke.' ditandai X.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya takson yang bertetangga: yang keliru hampir
                // selalu bergeser satu tingkat, bukan meleset jauh.
                array_merge([$benar], array_values(array_diff(array_unique([
                    self::TAKSON[$ke] ?? self::TAKSON[$ke - 2] ?? self::TAKSON[1],
                    self::TAKSON[$ke - 2] ?? self::TAKSON[$ke] ?? self::TAKSON[0],
                    self::TAKSON[$ke + 1] ?? self::TAKSON[max(0, $ke - 3)],
                    self::TAKSON[max(0, $ke - 3)],
                    ...self::TAKSON,
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Urutan takson dari yang tertinggi ke yang terendah: '
                .implode(' → ', self::TAKSON).'. Urutan ke-'.$ke.' adalah '.$benar.'.',
        ];
    }

    protected function kelompok(string $kunci, array $kelompok, string $warnaIsi, string $garis): array
    {
        $benar = $kelompok['nama'];

        return [
            'judul' => 'Kelompok '.$kelompok['pendek'],
            'teks' => 'Perhatikan bagan pengelompokan hewan bertulang belakang berikut. '
                .'Kelompok yang ditandai tanda tanya adalah ...',
            'pernyataan' => 'Pada bagan pengelompokan hewan bertulang belakang berikut, kelompok '
                .'yang ditandai tanda tanya adalah %s.',
            'svg' => self::svgVertebrata($kunci, true, $warnaIsi, $garis),
            'keterangan' => 'Lima kelompok hewan bertulang belakang beserta contohnya; '
                .'salah satu nama kelompoknya disembunyikan.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(
                    array_column(self::KELOMPOK, 'nama'), [$benar],
                ))),
            ),
            'kata_kunci' => $kelompok['pendek'],
            'pembahasan' => implode(', ', $kelompok['anggota']).' sama-sama '
                .$kelompok['ciri'].'. Ketiganya termasuk kelompok '.$benar.'.',
        ];
    }

    protected function ciri(string $kunci, array $kelompok, string $warnaIsi, string $garis): array
    {
        $benar = ucfirst($kelompok['ciri']);

        return [
            'judul' => 'Ciri '.$kelompok['pendek'],
            'teks' => 'Perhatikan bagan pengelompokan hewan bertulang belakang berikut. '
                .'Ciri khas kelompok '.$kelompok['pendek'].' yang ditandai pada bagan itu adalah ...',
            'pernyataan' => 'Pada bagan berikut, ciri khas kelompok '.$kelompok['pendek'].' adalah %s.',
            'svg' => self::svgVertebrata($kunci, false, $warnaIsi, $garis),
            'keterangan' => 'Lima kelompok hewan bertulang belakang; kelompok '
                .$kelompok['pendek'].' ditandai.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                // Pengecohnya ciri kelompok lain pada bagan yang sama,
                // sehingga yang menjawab asal tetap memilih ciri yang nyata.
                array_merge([$kelompok['ciri']], array_values(array_diff(
                    array_column(self::KELOMPOK, 'ciri'), [$kelompok['ciri']],
                ))),
            ),
            'kata_kunci' => $kelompok['ciri'],
            'pembahasan' => 'Kelompok '.$kelompok['nama'].' beranggotakan antara lain '
                .implode(', ', $kelompok['anggota']).'. Ciri khasnya adalah '.$kelompok['ciri'].'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Corong takson: kotak yang menyempit ke bawah. Nama takson yang
     * ditanyakan diganti huruf X, sedangkan takson lainnya tetap tampak
     * supaya siswa dapat menyimpulkannya dari urutan di sekitarnya.
     */
    public static function svgTakson(int $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $n = count(self::TAKSON);
        $tinggi = 28;
        $jarak = 5;
        $y0 = 36;

        foreach (self::TAKSON as $i => $nama) {
            $lebar = 384 - $i * 40;
            $ini = $i + 1 === $ditandai;

            $kanvas->kotakPx([Kanvas::LEBAR / 2, $y0 + $i * ($tinggi + $jarak) + $tinggi / 2],
                $lebar, $tinggi, $ini ? 'X' : $nama, $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini ? 18 : 13,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 1.8,
                    'sudut' => 6,
                ]);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, $y0 + $n * ($tinggi + $jarak) + 18],
            'makin ke bawah: anggotanya makin sedikit, cirinya makin mirip',
            ['ukuran' => 11, 'warna' => '#6b7280']);

        return $kanvas->render();
    }

    /**
     * Bagan lima kelompok vertebrata beserta contoh anggotanya. Contohnya
     * selalu ditampilkan — itulah yang membuat soalnya terjawab dari gambar
     * dan bukan sekadar dari ingatan.
     */
    public static function svgVertebrata(string $ditandai, bool $sembunyi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $akar = [Kanvas::LEBAR / 2, 52];
        $kanvas->kotakPx($akar, 170, 40, 'Vertebrata', '#eef1f4', $garis, [
            'ukuran' => 15, 'tebal' => true,
        ]);

        $n = count(self::KELOMPOK);
        $lebar = 84;
        $jarak = 7;
        $x0 = (Kanvas::LEBAR - $n * $lebar - ($n - 1) * $jarak) / 2 + $lebar / 2;
        $y = 148;

        $i = 0;

        foreach (self::KELOMPOK as $kunci => $kelompok) {
            $x = $x0 + $i++ * ($lebar + $jarak);
            $ini = $kunci === $ditandai;

            // Panahnya berangkat dari tepi bawah kotak akar, bukan dari
            // pusatnya, supaya pangkalnya tidak tersembunyi di dalam kotak.
            $kanvas->panahPx([$akar[0], $akar[1] + 24], [$x, $y - 26], '#6b7280', 1.6);

            $kanvas->kotakPx([$x, $y], $lebar, 38, $ini && $sembunyi ? '?' : $kelompok['pendek'],
                $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini && $sembunyi ? 22 : 12,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 2,
                ]);

            foreach ($kelompok['anggota'] as $k => $anggota) {
                $kanvas->teksPx([$x, $y + 36 + $k * 15], $anggota, [
                    'ukuran' => 11, 'warna' => '#4b5563',
                ]);
            }
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 288], 'tulisan di bawah tiap kotak = contoh anggotanya', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }
}

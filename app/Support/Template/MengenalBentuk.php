<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template mengenal bentuk: nama bangun dan ciri-cirinya.
 *
 * Bangun digambar polos tanpa ukuran, karena yang ditanyakan memang bukan
 * hitungan melainkan pengenalan: namanya, banyak sisi, titik sudut, atau
 * rusuknya. Ciri tiap bangun dikumpulkan dalam satu daftar supaya jawaban
 * dan pembahasannya tidak mungkin berselisih.
 */
class MengenalBentuk extends Template
{
    /**
     * Ciri tiap bangun: sisi, titik sudut, rusuk, dan kelompoknya.
     *
     * @var array<string, array{nama: string, sisi: int, sudut: int, rusuk: ?int, ruang: bool}>
     */
    public const BANGUN = [
        'segitiga' => ['nama' => 'Segitiga', 'sisi' => 3, 'sudut' => 3, 'rusuk' => null, 'ruang' => false],
        'persegi' => ['nama' => 'Persegi', 'sisi' => 4, 'sudut' => 4, 'rusuk' => null, 'ruang' => false],
        'persegi_panjang' => ['nama' => 'Persegi panjang', 'sisi' => 4, 'sudut' => 4, 'rusuk' => null, 'ruang' => false],
        'jajar_genjang' => ['nama' => 'Jajar genjang', 'sisi' => 4, 'sudut' => 4, 'rusuk' => null, 'ruang' => false],
        'trapesium' => ['nama' => 'Trapesium', 'sisi' => 4, 'sudut' => 4, 'rusuk' => null, 'ruang' => false],
        'belah_ketupat' => ['nama' => 'Belah ketupat', 'sisi' => 4, 'sudut' => 4, 'rusuk' => null, 'ruang' => false],
        'segi_lima' => ['nama' => 'Segi lima', 'sisi' => 5, 'sudut' => 5, 'rusuk' => null, 'ruang' => false],
        'segi_enam' => ['nama' => 'Segi enam', 'sisi' => 6, 'sudut' => 6, 'rusuk' => null, 'ruang' => false],
        'lingkaran' => ['nama' => 'Lingkaran', 'sisi' => 1, 'sudut' => 0, 'rusuk' => null, 'ruang' => false],
        'kubus' => ['nama' => 'Kubus', 'sisi' => 6, 'sudut' => 8, 'rusuk' => 12, 'ruang' => true],
        'balok' => ['nama' => 'Balok', 'sisi' => 6, 'sudut' => 8, 'rusuk' => 12, 'ruang' => true],
        'prisma_segitiga' => ['nama' => 'Prisma segitiga', 'sisi' => 5, 'sudut' => 6, 'rusuk' => 9, 'ruang' => true],
        'limas_segi_empat' => ['nama' => 'Limas segi empat', 'sisi' => 5, 'sudut' => 5, 'rusuk' => 8, 'ruang' => true],
        'tabung' => ['nama' => 'Tabung', 'sisi' => 3, 'sudut' => 0, 'rusuk' => 2, 'ruang' => true],
        'kerucut' => ['nama' => 'Kerucut', 'sisi' => 2, 'sudut' => 1, 'rusuk' => 1, 'ruang' => true],
        'bola' => ['nama' => 'Bola', 'sisi' => 1, 'sudut' => 0, 'rusuk' => 0, 'ruang' => true],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'nama' => 'Nama bangunnya',
        'sisi' => 'Banyak sisi',
        'sudut' => 'Banyak titik sudut',
        'rusuk' => 'Banyak rusuk',
    ];

    public static function kode(): string
    {
        return 'mengenal-bentuk';
    }

    public static function nama(): string
    {
        return 'Mengenal Bentuk';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Kenali bangun datar dan bangun ruang: namanya, banyak sisi, '
            .'titik sudut, dan rusuknya.';
    }

    public static function ikon(): string
    {
        return 'bi-bounding-box';
    }

    public static function kataKunci(): array
    {
        return ['bentuk', 'bangun datar', 'bangun ruang', 'sisi', 'titik sudut', 'rusuk', 'ciri'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'bangun',
                'label' => 'Bangun yang digambar',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($b) => $b['nama'], self::BANGUN),
                'awal' => 'kubus',
            ],
            self::kolomTanya(self::TANYA, 'nama'),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $bangun = self::BANGUN[$isi['bangun'] ?? ''] ?? null;

        if (($isi['tanya'] ?? '') === 'rusuk' && $bangun && ! $bangun['ruang']) {
            return ['tanya' => 'Rusuk hanya dimiliki bangun ruang; pilih bangun ruang atau pertanyaan lain.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $kode = $isi['bangun'] ?? 'trapesium';
        $bangun = self::BANGUN[$kode];
        $svg = self::svg($kode, $isi['warna'] ?? 'hijau');
        $keterangan = 'Gambar '.mb_strtolower($bangun['nama']).' tanpa ukuran.';
        $cerita = 'Perhatikan gambar bangun berikut.';
        $tanya = $isi['tanya'] ?? 'nama';

        if ($tanya === 'nama') {
            return [
                'judul' => $bangun['nama'],
                'teks' => $cerita.' Bangun apakah itu?',
                'pernyataan' => $cerita.' Bangun itu adalah %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => $n, 'urut' => $n],
                    array_merge([$bangun['nama']], self::namaSerupa($kode)),
                ),
                'kata_kunci' => $bangun['nama'],
                'pembahasan' => self::ciri($bangun).' Bangun itu disebut '.mb_strtolower($bangun['nama']).'.',
            ];
        }

        $benar = (int) $bangun[$tanya];
        $sebutan = ['sisi' => 'sisi', 'sudut' => 'titik sudut', 'rusuk' => 'rusuk'][$tanya];

        return [
            'judul' => $bangun['nama'].': '.$benar.' '.$sebutan,
            'teks' => $cerita." Ada berapa {$sebutan} pada bangun itu?",
            'pernyataan' => $cerita." Banyak {$sebutan} pada bangun itu ada %s.",
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Tertukar antara banyak sisi, titik sudut, dan rusuk
                    // adalah kekeliruan khas pada materi ini.
                    $bangun['sisi'], $bangun['sudut'], $bangun['rusuk'] ?? $benar + 2,
                    $benar + 1, $benar + 2, $benar - 1,
                ], 0, false)),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => self::ciri($bangun),
        ];
    }

    /** Nama bangun lain yang mudah tertukar: sekelompok dan sejumlah sisi. */
    protected static function namaSerupa(string $kode): array
    {
        $bangun = self::BANGUN[$kode];

        $serumpun = array_filter(
            self::BANGUN,
            fn ($b, $k) => $k !== $kode && $b['ruang'] === $bangun['ruang'],
            ARRAY_FILTER_USE_BOTH,
        );

        // Yang jumlah sisinya sama didahulukan: itulah yang paling sering
        // tertukar, bukan bangun yang bentuknya jauh berbeda.
        uasort($serumpun, fn ($a, $b) => abs($a['sisi'] - $bangun['sisi']) <=> abs($b['sisi'] - $bangun['sisi']));

        return array_values(array_map(fn ($b) => $b['nama'], array_slice($serumpun, 0, 3, true)));
    }

    protected static function ciri(array $bangun): string
    {
        $ciri = mb_strtolower($bangun['nama']).' mempunyai '.$bangun['sisi'].' sisi dan '
            .$bangun['sudut'].' titik sudut';

        return ucfirst($bangun['ruang'] ? $ciri.' serta '.$bangun['rusuk'].' rusuk.' : $ciri.'.');
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $kode, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['hijau'];

        // Bangun ruang dipinjam dari template bangun ruang supaya gambarnya
        // sama persis dengan yang dilihat siswa pada soal volume — hanya
        // ukurannya yang tidak ditulis, karena di sini tidak dipakai.
        if (self::BANGUN[$kode]['ruang']) {
            return BangunRuang::svg($kode, [
                'rusuk' => 6, 'panjang' => 8, 'lebar' => 4, 'tinggi' => 5,
                'alas_segitiga' => 6, 'tinggi_segitiga' => 5, 'tinggi_prisma' => 10,
                'sisi_alas' => 8, 'tinggi_limas' => 9,
                'jari_jari' => 5, 'tinggi_tabung' => 9,
            ], '', $warna, false);
        }

        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        $r = 100;

        $segi = fn (int $n, float $putar = 0) => array_map(
            fn ($i) => [
                $pusat[0] + $r * cos(deg2rad($i * 360 / $n - 90 + $putar)),
                $pusat[1] + $r * sin(deg2rad($i * 360 / $n - 90 + $putar)),
            ],
            range(0, $n - 1),
        );

        match ($kode) {
            'lingkaran' => $kanvas->lingkaran($pusat, $r, $isi, $garis, 2.5),
            'persegi' => $kanvas->persegi([$pusat[0] - 85, $pusat[1] - 85], 170, 170, $isi, $garis, 2.5),
            'persegi_panjang' => $kanvas->persegi([$pusat[0] - 130, $pusat[1] - 70], 260, 140, $isi, $garis, 2.5),
            'jajar_genjang' => $kanvas->poligon([
                [$pusat[0] - 90, $pusat[1] + 70], [$pusat[0] + 110, $pusat[1] + 70],
                [$pusat[0] + 90, $pusat[1] - 70], [$pusat[0] - 110, $pusat[1] - 70],
            ], $isi, $garis, 2.5),
            'trapesium' => $kanvas->poligon([
                [$pusat[0] - 130, $pusat[1] + 70], [$pusat[0] + 130, $pusat[1] + 70],
                [$pusat[0] + 70, $pusat[1] - 70], [$pusat[0] - 70, $pusat[1] - 70],
            ], $isi, $garis, 2.5),
            'belah_ketupat' => $kanvas->poligon([
                [$pusat[0], $pusat[1] - 100], [$pusat[0] + 80, $pusat[1]],
                [$pusat[0], $pusat[1] + 100], [$pusat[0] - 80, $pusat[1]],
            ], $isi, $garis, 2.5),
            'segitiga' => $kanvas->poligon($segi(3), $isi, $garis, 2.5),
            'segi_lima' => $kanvas->poligon($segi(5), $isi, $garis, 2.5),
            'segi_enam' => $kanvas->poligon($segi(6, 30), $isi, $garis, 2.5),
            default => $kanvas->poligon($segi(4, 45), $isi, $garis, 2.5),
        };

        return $kanvas->render();
    }
}

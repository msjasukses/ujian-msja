<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template jaring-jaring kubus dan balok.
 *
 * Bentuk jaring-jaring 1–4–1: empat persegi berderet sebagai sabuk, satu
 * tutup di atas dan satu alas di bawah. Dari susunan itu pasangan sisi yang
 * berhadapan bisa ditentukan tanpa melipat: pada sabuk, sisi yang berjarak
 * dua selalu berhadapan, sedangkan tutup dan alas berhadapan satu sama lain
 * di mana pun keduanya menempel.
 *
 * Letak tutup dan alas bisa digeser, sehingga satu template menghasilkan
 * beberapa jaring-jaring berbeda yang semuanya sah.
 */
class JaringJaring extends Template
{
    /** @var array<string, string> */
    public const BANGUN = [
        'kubus' => 'Kubus',
        'balok' => 'Balok',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'berhadapan' => 'Sisi yang berhadapan',
        'alas' => 'Sisi alas bila tutupnya ditentukan',
        'bangun' => 'Bangun yang terbentuk',
    ];

    public static function kode(): string
    {
        return 'jaring-jaring';
    }

    public static function nama(): string
    {
        return 'Jaring-jaring';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Jaring-jaring kubus dan balok: menentukan sisi yang berhadapan '
            .'dan bangun yang terbentuk bila dilipat.';
    }

    public static function ikon(): string
    {
        return 'bi-grid';
    }

    public static function kataKunci(): array
    {
        return ['jaring-jaring', 'kubus', 'balok', 'sisi berhadapan', 'melipat', 'bangun ruang'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'bangun',
                'label' => 'Bangun yang dibuka',
                'jenis' => 'pilihan',
                'pilihan' => self::BANGUN,
                'awal' => 'kubus',
                'lebar' => 6,
            ],
            self::kolomAngka('bentuk_ke', 'Bentuk jaring-jaring ke-', 1, 1, 4, ['langkah' => 1]),
            [
                'nama' => 'sisi',
                'label' => 'Sisi yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(range('A', 'F'), range('A', 'F')),
                'awal' => 'B',
                'lebar' => 6,
                'tampil' => ['tanya' => ['berhadapan']],
            ],
            self::kolomTanya(self::TANYA, 'berhadapan'),
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        $bangun = $isi['bangun'] ?? 'kubus';
        $bentukKe = (int) ($isi['bentuk_ke'] ?? 1);
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $susunan = self::susunan($bentukKe);
        $lawan = self::berhadapan($susunan);
        $svg = self::svg($susunan, $bangun, $warnaIsi, $garis);
        $nama = self::BANGUN[$bangun];
        $cerita = "Perhatikan gambar jaring-jaring {$nama} berikut.";
        $keterangan = "Jaring-jaring {$nama} berbentuk 1–4–1 dengan enam sisi bertanda A sampai F.";

        if (($isi['tanya'] ?? 'berhadapan') === 'bangun') {
            return [
                'judul' => 'Jaring-jaring '.$nama,
                'teks' => 'Perhatikan gambar jaring-jaring berikut. Bangun apakah yang terbentuk bila dilipat?',
                'pernyataan' => 'Perhatikan gambar jaring-jaring berikut. Bila dilipat, jaring-jaring itu membentuk %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => array_map(
                    fn ($n) => ['teks' => $n, 'urut' => $n],
                    array_merge([$nama], array_values(array_diff(
                        ['Kubus', 'Balok', 'Prisma segitiga', 'Limas segi empat'],
                        [$nama],
                    ))),
                ),
                'kata_kunci' => $nama,
                'pembahasan' => 'Keenam sisinya '.($bangun === 'kubus'
                    ? 'berbentuk persegi yang sama besar, jadi bangun yang terbentuk kubus.'
                    : 'berbentuk persegi panjang dengan tiga pasang ukuran yang berbeda, jadi bangun yang terbentuk balok.'),
            ];
        }

        if (($isi['tanya'] ?? '') === 'alas') {
            $tutup = $susunan['tutup']['huruf'];
            $alas = $susunan['alas']['huruf'];

            return [
                'judul' => "Alas {$alas} bila tutupnya {$tutup}",
                'teks' => $cerita." Bila sisi {$tutup} menjadi tutup, sisi manakah yang menjadi alasnya?",
                'pernyataan' => $cerita." Bila sisi {$tutup} menjadi tutup, alasnya adalah sisi %s.",
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => self::pilihanHuruf($alas, $susunan),
                'kata_kunci' => $alas,
                'pembahasan' => 'Sisi yang menempel di atas sabuk dan sisi yang menempel di bawahnya '
                    ."selalu berhadapan ketika dilipat, jadi alasnya sisi {$alas}.",
            ];
        }

        $sisi = $isi['sisi'] ?? 'B';
        $benar = $lawan[$sisi];

        return [
            'judul' => "Sisi {$sisi} berhadapan dengan {$benar}",
            'teks' => $cerita." Bila dilipat menjadi {$nama}, sisi yang berhadapan dengan sisi {$sisi} adalah ...",
            'pernyataan' => $cerita." Bila dilipat menjadi {$nama}, sisi yang berhadapan dengan sisi {$sisi} adalah sisi %s.",
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => self::pilihanHuruf($benar, $susunan, $sisi),
            'kata_kunci' => $benar,
            'pembahasan' => self::alasan($susunan, $sisi, $benar),
        ];
    }

    /**
     * Pengecoh sisi: huruf lain pada jaring-jaring itu sendiri, terutama sisi
     * yang bersebelahan — sisi bersebelahan memang tidak mungkin berhadapan,
     * dan itulah yang sedang diuji.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanHuruf(string $benar, array $susunan, string $kecuali = ''): array
    {
        $semua = array_merge(
            array_column($susunan['sabuk'], 'huruf'),
            [$susunan['tutup']['huruf'], $susunan['alas']['huruf']],
        );

        $lain = array_values(array_diff($semua, [$benar, $kecuali]));

        return array_map(
            fn ($h) => ['teks' => $h, 'urut' => $h],
            array_merge([$benar], array_slice($lain, 0, self::JUMLAH_OPSI - 1)),
        );
    }

    protected static function alasan(array $susunan, string $sisi, string $benar): string
    {
        $sabuk = array_column($susunan['sabuk'], 'huruf');

        if (in_array($sisi, $sabuk, true)) {
            return 'Keempat sisi yang berderet membentuk sabuk. Pada sabuk, sisi yang berjarak dua '
                ."selalu berhadapan, jadi sisi {$sisi} berhadapan dengan sisi {$benar}.";
        }

        return 'Sisi yang menempel di atas sabuk menjadi tutup dan yang menempel di bawahnya menjadi alas. '
            ."Keduanya berhadapan, jadi sisi {$sisi} berhadapan dengan sisi {$benar}.";
    }

    /**
     * Susunan jaring-jaring: empat persegi sabuk beserta tutup dan alasnya,
     * masing-masing dengan kedudukan kolom-baris dan hurufnya.
     *
     * @return array{sabuk: array<int, array{kolom: int, baris: int, huruf: string}>,
     *               tutup: array{kolom: int, baris: int, huruf: string},
     *               alas: array{kolom: int, baris: int, huruf: string}}
     */
    public static function susunan(int $bentukKe): array
    {
        // Tutup dan alas digeser ke kolom yang berbeda untuk tiap bentuk,
        // sehingga guru bisa menyiapkan beberapa butir yang tidak serupa.
        $kolomTutup = [1 => 0, 2 => 1, 3 => 2, 4 => 3][$bentukKe] ?? 0;
        $kolomAlas = [1 => 2, 2 => 3, 3 => 0, 4 => 1][$bentukKe] ?? 2;

        $sel = [];

        // Huruf diberikan menurut urutan membaca: kiri ke kanan, atas ke bawah.
        $sel[] = ['kolom' => $kolomTutup, 'baris' => 0, 'peran' => 'tutup'];

        for ($i = 0; $i < 4; $i++) {
            $sel[] = ['kolom' => $i, 'baris' => 1, 'peran' => 'sabuk'];
        }

        $sel[] = ['kolom' => $kolomAlas, 'baris' => 2, 'peran' => 'alas'];

        $sabuk = [];
        $tutup = $alas = null;

        foreach ($sel as $i => $s) {
            $s['huruf'] = chr(65 + $i);

            match ($s['peran']) {
                'tutup' => $tutup = $s,
                'alas' => $alas = $s,
                default => $sabuk[] = $s,
            };
        }

        return ['sabuk' => $sabuk, 'tutup' => $tutup, 'alas' => $alas];
    }

    /**
     * Pasangan sisi yang berhadapan.
     *
     * @return array<string, string>
     */
    public static function berhadapan(array $susunan): array
    {
        $sabuk = array_column($susunan['sabuk'], 'huruf');

        $lawan = [
            $sabuk[0] => $sabuk[2],
            $sabuk[2] => $sabuk[0],
            $sabuk[1] => $sabuk[3],
            $sabuk[3] => $sabuk[1],
            $susunan['tutup']['huruf'] => $susunan['alas']['huruf'],
            $susunan['alas']['huruf'] => $susunan['tutup']['huruf'],
        ];

        return $lawan;
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(array $susunan, string $bangun, string $isi, string $garis): string
    {
        $sel = array_merge($susunan['sabuk'], [$susunan['tutup'], $susunan['alas']]);

        $kanvas = new Kanvas;

        // Balok dibedakan dari kubus lewat perbandingan sisinya: perseginya
        // dibuat memanjang, persis seperti sisi balok yang sesungguhnya.
        $lebar = 76;
        $tinggi = $bangun === 'balok' ? 54 : 76;

        $pangkalX = (Kanvas::LEBAR - 4 * $lebar) / 2;
        $pangkalY = (Kanvas::TINGGI - 3 * $tinggi) / 2;

        foreach ($sel as $s) {
            $x = $pangkalX + $s['kolom'] * $lebar;
            $y = $pangkalY + $s['baris'] * $tinggi;

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.$lebar.'" height="'.$tinggi
                .'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>')
                ->teksPx([$x + $lebar / 2, $y + $tinggi / 2 + 9], $s['huruf'], ['ukuran' => 26, 'tebal' => true]);
        }

        return $kanvas->render();
    }
}

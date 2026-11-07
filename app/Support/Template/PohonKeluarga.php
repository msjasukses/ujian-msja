<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template pohon keluarga.
 *
 * Hubungan kekerabatan tidak dituliskan satu per satu melainkan disimpulkan
 * dari susunan keluarganya — siapa anak siapa dan siapa menikah dengan siapa.
 * Karena itu guru cukup memilih dua orang pada bagan, dan kunci jawabannya
 * dihitung; hubungan yang tidak punya sebutan baku dalam bahasa Indonesia
 * ditolak sejak di formulir, bukan dijawab asal.
 */
class PohonKeluarga extends Template
{
    /**
     * Anggota keluarga: nama, jenis kelamin, orang tuanya, pasangannya,
     * urutan kelahiran di antara saudara sekandung, serta letaknya pada
     * bagan (baris dan titik tengah mendatar dalam piksel).
     *
     * @var array<string, array{nama: string, l: bool, ortu: array<int, string>, pasangan: ?string, urut: int, baris: int, x: float}>
     */
    public const ORANG = [
        'kakek' => ['nama' => 'Pak Hasan', 'l' => true, 'ortu' => [], 'pasangan' => 'nenek',
            'urut' => 1, 'baris' => 0, 'x' => 168],
        'nenek' => ['nama' => 'Bu Aminah', 'l' => false, 'ortu' => [], 'pasangan' => 'kakek',
            'urut' => 1, 'baris' => 0, 'x' => 312],
        'ayah' => ['nama' => 'Pak Budi', 'l' => true, 'ortu' => ['kakek', 'nenek'], 'pasangan' => 'ibu',
            'urut' => 1, 'baris' => 1, 'x' => 56],
        'ibu' => ['nama' => 'Bu Sinta', 'l' => false, 'ortu' => [], 'pasangan' => 'ayah',
            'urut' => 1, 'baris' => 1, 'x' => 190],
        'bibi' => ['nama' => 'Bu Rina', 'l' => false, 'ortu' => ['kakek', 'nenek'], 'pasangan' => 'paman',
            'urut' => 2, 'baris' => 1, 'x' => 300],
        'paman' => ['nama' => 'Pak Deni', 'l' => true, 'ortu' => [], 'pasangan' => 'bibi',
            'urut' => 1, 'baris' => 1, 'x' => 428],
        'andi' => ['nama' => 'Andi', 'l' => true, 'ortu' => ['ayah', 'ibu'], 'pasangan' => null,
            'urut' => 1, 'baris' => 2, 'x' => 56],
        'dewi' => ['nama' => 'Dewi', 'l' => false, 'ortu' => ['ayah', 'ibu'], 'pasangan' => null,
            'urut' => 2, 'baris' => 2, 'x' => 190],
        'fajar' => ['nama' => 'Fajar', 'l' => true, 'ortu' => ['bibi', 'paman'], 'pasangan' => null,
            'urut' => 1, 'baris' => 2, 'x' => 364],
    ];

    /**
     * Seluruh sebutan kekerabatan yang dapat menjadi jawaban. Dipakai juga
     * sebagai sumber pengecoh, sehingga tiap pilihan selalu berupa sebutan
     * yang nyata — bukan kata yang bisa dicoret karena terdengar asing.
     *
     * @var array<int, string>
     */
    public const SEBUTAN = ['Ayah', 'Ibu', 'Anak', 'Kakek', 'Nenek', 'Cucu', 'Kakak', 'Adik',
        'Paman', 'Bibi', 'Keponakan', 'Sepupu', 'Mertua', 'Menantu', 'Ipar', 'Suami', 'Istri'];

    /** @var array<string, string> */
    public const TANYA = [
        'hubungan' => 'Hubungan seseorang terhadap yang lain',
        'generasi' => 'Berapa generasi di atas atau di bawah',
    ];

    public static function kode(): string
    {
        return 'pohon-keluarga';
    }

    public static function nama(): string
    {
        return 'Pohon Keluarga';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Bagan silsilah tiga generasi: menentukan sebutan kekerabatan antara '
            .'dua anggota keluarga beserta jarak generasinya.';
    }

    public static function ikon(): string
    {
        return 'bi-people';
    }

    public static function kataKunci(): array
    {
        return ['pohon keluarga', 'silsilah', 'kekerabatan', 'keluarga', 'kakek', 'nenek',
            'paman', 'bibi', 'sepupu', 'keponakan', 'cucu', 'menantu', 'mertua', 'ipar',
            'generasi', 'ips'];
    }

    public static function kolom(): array
    {
        $pilihan = array_map(fn ($o) => $o['nama'], self::ORANG);

        return [
            [
                'nama' => 'a',
                'label' => 'Orang yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                // Pasangan awalnya sengaja yang segaris keturunan, sebab
                // soal jarak generasi hanya berlaku di sana — isian awal
                // harus sah untuk tiap pilihan "yang ditanyakan".
                'awal' => 'kakek',
                'lebar' => 6,
            ],
            [
                'nama' => 'b',
                'label' => 'Terhadap siapa',
                'jenis' => 'pilihan',
                'pilihan' => $pilihan,
                'awal' => 'andi',
                'lebar' => 6,
            ],
            self::kolomTanya(self::TANYA, 'hubungan'),
            self::kolomWarna('ungu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $a = $isi['a'] ?? 'kakek';
        $b = $isi['b'] ?? 'andi';

        if ($a === $b) {
            return ['b' => 'Pilih dua orang yang berbeda.'];
        }

        if (($isi['tanya'] ?? 'hubungan') === 'generasi') {
            if (self::selisihGenerasi($a, $b) === null) {
                return ['b' => 'Keduanya tidak satu garis keturunan, jadi jarak generasinya tidak terhitung.'];
            }

            return [];
        }

        if (self::hubungan($a, $b) === null) {
            return ['b' => 'Hubungan keduanya tidak punya sebutan baku; pilih pasangan yang lain.'];
        }

        return [];
    }

    // =====================================================================
    // Penalaran kekerabatan
    // =====================================================================

    /** @return array<int, string> */
    protected static function ortu(string $id): array
    {
        return self::ORANG[$id]['ortu'] ?? [];
    }

    protected static function pasangan(string $id): ?string
    {
        return self::ORANG[$id]['pasangan'] ?? null;
    }

    /**
     * Saudara sekandung: yang orang tuanya sama persis. Yang menikah masuk
     * ke keluarga ini tanpa orang tua pada bagan, jadi daftar orang tua yang
     * kosong tidak pernah dianggap sama.
     *
     * @return array<int, string>
     */
    protected static function saudara(string $id): array
    {
        $ortu = self::ortu($id);

        if ($ortu === []) {
            return [];
        }

        return array_values(array_filter(
            array_keys(self::ORANG),
            fn ($lain) => $lain !== $id && self::ortu($lain) === $ortu,
        ));
    }

    /**
     * Saudara seseorang beserta pasangan tiap saudaranya — inilah yang
     * disebut paman dan bibi, termasuk yang menjadi paman karena menikah.
     *
     * @return array<int, string>
     */
    protected static function saudaraIpar(string $id): array
    {
        $hasil = [];

        foreach (self::saudara($id) as $saudara) {
            $hasil[] = $saudara;

            if (self::pasangan($saudara) !== null) {
                $hasil[] = self::pasangan($saudara);
            }
        }

        return $hasil;
    }

    /** @return array<int, string> */
    protected static function kakekNenek(string $id): array
    {
        $hasil = [];

        foreach (self::ortu($id) as $ortu) {
            $hasil = array_merge($hasil, self::ortu($ortu));
        }

        return $hasil;
    }

    /**
     * Sebutan untuk "A adalah ... dari B", atau null bila hubungan keduanya
     * tidak punya sebutan baku pada bagan ini.
     */
    public static function hubungan(string $a, string $b): ?string
    {
        if ($a === $b || ! isset(self::ORANG[$a], self::ORANG[$b])) {
            return null;
        }

        $lakiA = self::ORANG[$a]['l'];

        return match (true) {
            self::pasangan($b) === $a => $lakiA ? 'Suami' : 'Istri',
            in_array($a, self::ortu($b), true) => $lakiA ? 'Ayah' : 'Ibu',
            in_array($b, self::ortu($a), true) => 'Anak',
            in_array($a, self::kakekNenek($b), true) => $lakiA ? 'Kakek' : 'Nenek',
            in_array($b, self::kakekNenek($a), true) => 'Cucu',
            in_array($a, self::saudara($b), true) => self::ORANG[$a]['urut'] < self::ORANG[$b]['urut']
                ? 'Kakak' : 'Adik',
            self::pamanBibi($a, $b) => $lakiA ? 'Paman' : 'Bibi',
            self::pamanBibi($b, $a) => 'Keponakan',
            self::sepupu($a, $b) => 'Sepupu',
            self::mertua($a, $b) => 'Mertua',
            self::mertua($b, $a) => 'Menantu',
            self::ipar($a, $b) => 'Ipar',
            default => null,
        };
    }

    /** A adalah saudara — kandung atau karena menikah — dari orang tua B. */
    protected static function pamanBibi(string $a, string $b): bool
    {
        foreach (self::ortu($b) as $ortu) {
            if (in_array($a, self::saudaraIpar($ortu), true)) {
                return true;
            }
        }

        return false;
    }

    /** Orang tua keduanya bersaudara kandung. */
    protected static function sepupu(string $a, string $b): bool
    {
        foreach (self::ortu($a) as $ortuA) {
            foreach (self::ortu($b) as $ortuB) {
                if (in_array($ortuA, self::saudara($ortuB), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A adalah orang tua dari pasangan B. */
    protected static function mertua(string $a, string $b): bool
    {
        $pasangan = self::pasangan($b);

        return $pasangan !== null && in_array($a, self::ortu($pasangan), true);
    }

    /** A bersaudara dengan pasangan B, atau menikah dengan saudara B. */
    protected static function ipar(string $a, string $b): bool
    {
        $pasanganB = self::pasangan($b);
        $pasanganA = self::pasangan($a);

        return ($pasanganB !== null && in_array($a, self::saudara($pasanganB), true))
            || ($pasanganA !== null && in_array($pasanganA, self::saudara($b), true));
    }

    /**
     * Selisih generasi antara dua orang yang satu garis keturunan: positif
     * bila A berada di atas B. Null bila keduanya tidak segaris, misalnya
     * sepupu atau ipar, karena "berapa generasi" tidak berlaku di sana.
     */
    public static function selisihGenerasi(string $a, string $b): ?int
    {
        if (! isset(self::ORANG[$a], self::ORANG[$b]) || $a === $b) {
            return null;
        }

        foreach ([[$a, $b, 1], [$b, $a, -1]] as [$atas, $bawah, $tanda]) {
            if (in_array($atas, self::ortu($bawah), true)) {
                return $tanda;
            }

            if (in_array($atas, self::kakekNenek($bawah), true)) {
                return 2 * $tanda;
            }
        }

        return null;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'ungu');

        $a = isset(self::ORANG[$isi['a'] ?? '']) ? $isi['a'] : 'kakek';
        $b = isset(self::ORANG[$isi['b'] ?? '']) ? $isi['b'] : 'andi';

        if ($a === $b) {
            $b = $a === 'andi' ? 'kakek' : 'andi';
        }

        $namaA = self::ORANG[$a]['nama'];
        $namaB = self::ORANG[$b]['nama'];

        $bahan = [
            'svg' => self::svgPohon($a, $b, $warnaIsi, $garis),
            'keterangan' => 'Bagan silsilah tiga generasi; '.$namaA.' dan '.$namaB.' ditandai.',
        ];

        $awalan = 'Perhatikan bagan silsilah keluarga berikut.';

        if (($isi['tanya'] ?? 'hubungan') === 'generasi') {
            return $bahan + $this->generasi($awalan, $a, $b, $namaA, $namaB);
        }

        $benar = self::hubungan($a, $b) ?? 'Sepupu';

        return $bahan + [
            'judul' => $namaA.' adalah '.mb_strtolower($benar).' '.$namaB,
            'teks' => $awalan." Apa hubungan {$namaA} terhadap {$namaB}?",
            'pernyataan' => $awalan." {$namaA} adalah %s dari {$namaB}.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecoh pertamanya hubungan yang sama tetapi terbalik
                // arahnya — kekeliruan terbesar saat membaca silsilah.
                array_merge([$benar], array_values(array_diff(array_unique(array_merge(
                    [self::hubungan($b, $a) ?? ''],
                    self::SEBUTAN,
                )), [$benar, '']))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => self::jalan($a, $b, $namaA, $namaB, $benar),
        ];
    }

    protected function generasi(string $awalan, string $a, string $b, string $namaA, string $namaB): array
    {
        $selisih = self::selisihGenerasi($a, $b) ?? 1;
        $atas = $selisih > 0;
        $jarak = abs($selisih);

        $benar = $jarak.' generasi di '.($atas ? 'atas' : 'bawah').' '.$namaB;

        return [
            'judul' => $namaA.': '.$jarak.' generasi di '.($atas ? 'atas' : 'bawah'),
            'teks' => $awalan." Pada silsilah itu, {$namaA} berada berapa generasi dari {$namaB}?",
            'pernyataan' => $awalan." Pada silsilah itu, {$namaA} berada %s.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(array_unique([
                    // Pengecoh utamanya arah yang terbalik, lalu jarak yang
                    // meleset satu generasi.
                    $jarak.' generasi di '.($atas ? 'bawah' : 'atas').' '.$namaB,
                    ($jarak + 1).' generasi di '.($atas ? 'atas' : 'bawah').' '.$namaB,
                    ($jarak + 1).' generasi di '.($atas ? 'bawah' : 'atas').' '.$namaB,
                    max(1, $jarak - 1).' generasi di '.($atas ? 'atas' : 'bawah').' '.$namaB,
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Tiap baris pada bagan adalah satu generasi. '.$namaA.' berada '
                .$jarak.' baris di '.($atas ? 'atas' : 'bawah').' '.$namaB.', dan hubungan '
                .'keduanya adalah '.mb_strtolower(self::hubungan($a, $b) ?? 'keturunan').'.',
        ];
    }

    /** Penjelasan hubungan yang menelusuri bagannya, bukan menyebut hasilnya saja. */
    protected static function jalan(string $a, string $b, string $namaA, string $namaB, string $benar): string
    {
        $ortuB = array_map(fn ($o) => self::ORANG[$o]['nama'], self::ortu($b));
        $ortuA = array_map(fn ($o) => self::ORANG[$o]['nama'], self::ortu($a));

        $asal = match (true) {
            $ortuB !== [] => 'Orang tua '.$namaB.' adalah '.implode(' dan ', $ortuB).'. ',
            $ortuA !== [] => 'Orang tua '.$namaA.' adalah '.implode(' dan ', $ortuA).'. ',
            default => '',
        };

        return $asal.'Ditelusuri pada bagan, '.$namaA.' adalah '.mb_strtolower($benar).' dari '
            .$namaB.'.<br>Perhatikan arahnya: kebalikannya, '.$namaB.' adalah '
            .mb_strtolower(self::hubungan($b, $a) ?? 'kerabat').' dari '.$namaA.'.';
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bagan silsilah: tiap baris satu generasi, garis rangkap mendatar
     * menandai pernikahan, dan garis tegak menandai keturunan.
     */
    public static function svgPohon(string $a, string $b, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$lebar, $tinggi] = [100, 40];
        $baris = [70, 160, 250];

        $letak = fn (string $id) => [self::ORANG[$id]['x'], $baris[self::ORANG[$id]['baris']]];

        // Garis pernikahan digambar lebih dahulu agar tertutup kotaknya, lalu
        // garis keturunan turun dari titik tengah tiap pasangan.
        foreach (['kakek' => 'nenek', 'ayah' => 'ibu', 'bibi' => 'paman'] as $satu => $dua) {
            $kiri = $letak($satu);
            $kanan = $letak($dua);
            $tengah = [($kiri[0] + $kanan[0]) / 2, $kiri[1]];

            foreach ([-3, 3] as $geser) {
                $kanvas->garis([$kiri[0] + $lebar / 2, $kiri[1] + $geser],
                    [$kanan[0] - $lebar / 2, $kanan[1] + $geser], Kanvas::HITAM, 2);
            }

            $anak = array_values(array_filter(
                array_keys(self::ORANG),
                fn ($id) => self::ortu($id) === [$satu, $dua] || self::ortu($id) === [$dua, $satu],
            ));

            if ($anak === []) {
                continue;
            }

            $turun = $tengah[1] + $tinggi / 2 + 22;
            $kanvas->garis($tengah, [$tengah[0], $turun], Kanvas::HITAM, 2);

            foreach ($anak as $id) {
                $kanvas
                    ->garis([$tengah[0], $turun], [$letak($id)[0], $turun], Kanvas::HITAM, 2)
                    ->garis([$letak($id)[0], $turun], [$letak($id)[0], $letak($id)[1] - $tinggi / 2], Kanvas::HITAM, 2);
            }
        }

        foreach (self::ORANG as $id => $orang) {
            $ditandai = $id === $a || $id === $b;

            $kanvas->kotakPx($letak($id), $lebar, $tinggi, $orang['nama'],
                $ditandai ? $isi : '#ffffff', $ditandai ? $garis : '#9aa1a9', [
                    'ukuran' => 12, 'tebal' => $ditandai, 'tebal_garis' => $ditandai ? 3 : 1.8,
                ]);
        }

        $kanvas->teksPx([Kanvas::LEBAR / 2, 292], 'garis rangkap = pernikahan, garis tegak = keturunan', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }
}

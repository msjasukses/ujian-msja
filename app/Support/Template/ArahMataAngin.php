<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template peta & arah mata angin.
 *
 * Denahnya ditata sebagai petak tiga kali tiga dengan satu tempat di
 * tengahnya, sehingga arah tempat mana pun terhadap tempat di tengah selalu
 * tepat jatuh pada salah satu dari delapan mata angin — bukan "agak ke
 * tenggara" yang tidak punya jawaban tunggal. Mawar kompasnya dibangkitkan
 * dari sudut tiap arah, jadi nama, sudut, dan gambarnya selalu sejalan.
 */
class ArahMataAngin extends Template
{
    /**
     * Delapan mata angin beserta sudutnya dari utara, searah jarum jam, dan
     * letaknya pada petak denah (kolom, baris).
     *
     * @var array<string, array{nama: string, singkat: string, derajat: int, petak: array<int, int>}>
     */
    public const ARAH = [
        'utara' => ['nama' => 'Utara', 'singkat' => 'U', 'derajat' => 0, 'petak' => [1, 0]],
        'timur_laut' => ['nama' => 'Timur laut', 'singkat' => 'TL', 'derajat' => 45, 'petak' => [2, 0]],
        'timur' => ['nama' => 'Timur', 'singkat' => 'T', 'derajat' => 90, 'petak' => [2, 1]],
        'tenggara' => ['nama' => 'Tenggara', 'singkat' => 'TG', 'derajat' => 135, 'petak' => [2, 2]],
        'selatan' => ['nama' => 'Selatan', 'singkat' => 'S', 'derajat' => 180, 'petak' => [1, 2]],
        'barat_daya' => ['nama' => 'Barat daya', 'singkat' => 'BD', 'derajat' => 225, 'petak' => [0, 2]],
        'barat' => ['nama' => 'Barat', 'singkat' => 'B', 'derajat' => 270, 'petak' => [0, 1]],
        'barat_laut' => ['nama' => 'Barat laut', 'singkat' => 'BL', 'derajat' => 315, 'petak' => [0, 0]],
    ];

    /** Tempat pada tiap arah dari tempat yang berada di tengah denah. */
    public const TEMPAT = [
        'utara' => 'Puskesmas',
        'timur_laut' => 'Pasar',
        'timur' => 'Sekolah',
        'tenggara' => 'Sawah',
        'selatan' => 'Kantor Desa',
        'barat_daya' => 'Kebun',
        'barat' => 'Rumah Andi',
        'barat_laut' => 'Lapangan',
    ];

    public const PUSAT = 'Masjid';

    /** @var array<string, string> */
    public const TANYA = [
        'peta' => 'Arah sebuah tempat pada denah',
        'kompas' => 'Nama arah yang ditandai pada kompas',
        'lawan' => 'Arah yang berlawanan',
        'derajat' => 'Sudut sebuah arah dari utara',
    ];

    public static function kode(): string
    {
        return 'arah-mata-angin';
    }

    public static function nama(): string
    {
        return 'Peta & Arah Mata Angin';
    }

    public static function mapel(): string
    {
        return 'IPS';
    }

    public static function ringkasan(): string
    {
        return 'Denah sebuah desa dan mawar kompas delapan arah: membaca arah '
            .'antartempat, menamai arah, arah berlawanan, serta sudutnya dari utara.';
    }

    public static function ikon(): string
    {
        return 'bi-compass';
    }

    public static function kataKunci(): array
    {
        return ['arah mata angin', 'kompas', 'denah', 'peta', 'utara', 'selatan', 'timur',
            'barat', 'timur laut', 'tenggara', 'barat daya', 'barat laut', 'geografi',
            'letak', 'denah sekolah'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'peta'),
            [
                'nama' => 'tempat',
                'label' => 'Tempat yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(array_keys(self::TEMPAT), array_map(
                    fn ($t, $a) => $t.' (di sebelah '.mb_strtolower(self::ARAH[$a]['nama']).')',
                    self::TEMPAT, array_keys(self::TEMPAT),
                )),
                'awal' => 'tenggara',
                'tampil' => ['tanya' => ['peta']],
            ],
            [
                'nama' => 'balik',
                'label' => 'Dibalik: arah '.self::PUSAT.' dari tempat itu',
                'jenis' => 'centang',
                'awal' => false,
                'tampil' => ['tanya' => ['peta']],
            ],
            [
                'nama' => 'arah',
                'label' => 'Arah yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => $a['nama'].' ('.$a['derajat'].'°)', self::ARAH),
                'awal' => 'tenggara',
                'tampil' => ['tanya' => ['kompas', 'lawan', 'derajat']],
            ],
            self::kolomWarna('merah'),
        ];
    }

    /** Arah yang berlawanan dengan sebuah arah. */
    public static function lawanArah(string $kunci): string
    {
        $derajat = (self::ARAH[$kunci]['derajat'] + 180) % 360;

        foreach (self::ARAH as $lain => $arah) {
            if ($arah['derajat'] === $derajat) {
                return $lain;
            }
        }

        return $kunci;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'merah');

        $arah = isset(self::ARAH[$isi['arah'] ?? '']) ? $isi['arah'] : 'tenggara';

        return match ($isi['tanya'] ?? 'peta') {
            'kompas' => $this->kompas($arah, $warnaIsi, $garis),
            'lawan' => $this->lawan($arah, $warnaIsi, $garis),
            'derajat' => $this->derajat($arah, $warnaIsi, $garis),
            default => $this->peta(
                isset(self::TEMPAT[$isi['tempat'] ?? '']) ? $isi['tempat'] : 'tenggara',
                (bool) ($isi['balik'] ?? false), $warnaIsi, $garis,
            ),
        };
    }

    protected function peta(string $kunci, bool $balik, string $warnaIsi, string $garis): array
    {
        $tempat = self::TEMPAT[$kunci];

        // Arah tempat di tengah dilihat dari tempat lain adalah kebalikan
        // arah tempat itu dilihat dari tengah.
        $jawab = $balik ? self::lawanArah($kunci) : $kunci;
        $benar = self::ARAH[$jawab]['nama'];

        [$dari, $ke] = $balik ? [$tempat, self::PUSAT] : [self::PUSAT, $tempat];

        return [
            'judul' => $ke.' di sebelah '.mb_strtolower($benar).' '.$dari,
            'teks' => "Perhatikan denah berikut. Dari {$dari}, {$ke} terletak di sebelah ...",
            'pernyataan' => "Pada denah berikut, dari {$dari} letak {$ke} ada di sebelah %s.",
            'svg' => self::svgDenah($kunci, $balik, $warnaIsi, $garis),
            'keterangan' => 'Denah desa; '.$tempat.' berada di sebelah '
                .mb_strtolower(self::ARAH[$kunci]['nama']).' '.self::PUSAT.'.',
            'pilihan' => self::pilihanArah($jawab),
            'kata_kunci' => $benar,
            'pembahasan' => 'Pada denah, bagian atas selalu menunjukkan utara, bagian bawah '
                .'selatan, kanan timur, dan kiri barat.<br>'.$ke.' berada di petak sebelah '
                .mb_strtolower($benar).' dari '.$dari.', jadi jawabannya '.mb_strtolower($benar).'.',
        ];
    }

    protected function kompas(string $kunci, string $warnaIsi, string $garis): array
    {
        $benar = self::ARAH[$kunci]['nama'];

        return [
            'judul' => 'Arah '.mb_strtolower($benar),
            'teks' => 'Perhatikan mawar kompas berikut. Arah yang ditandai huruf X adalah ...',
            'pernyataan' => 'Pada mawar kompas berikut, arah yang ditandai huruf X adalah %s.',
            'svg' => self::svgKompas($kunci, false, $warnaIsi, $garis),
            'keterangan' => 'Mawar kompas delapan arah; arah '.mb_strtolower($benar).' ditandai X.',
            'pilihan' => self::pilihanArah($kunci),
            'kata_kunci' => $benar,
            'pembahasan' => 'Searah jarum jam dari atas: '
                .implode(', ', array_map(fn ($a) => $a['nama'], self::ARAH))
                .'. Arah yang ditandai X membentuk sudut '.self::ARAH[$kunci]['derajat']
                .'° dari utara, yaitu '.mb_strtolower($benar).'.',
        ];
    }

    protected function lawan(string $kunci, string $warnaIsi, string $garis): array
    {
        $lawan = self::lawanArah($kunci);
        $benar = self::ARAH[$lawan]['nama'];
        $nama = self::ARAH[$kunci]['nama'];

        return [
            'judul' => 'Lawan '.mb_strtolower($nama).' = '.mb_strtolower($benar),
            'teks' => 'Perhatikan mawar kompas berikut. Arah X adalah '.mb_strtolower($nama)
                .'. Arah yang berlawanan dengannya adalah ...',
            'pernyataan' => 'Pada mawar kompas berikut, arah yang berlawanan dengan '
                .mb_strtolower($nama).' adalah %s.',
            'svg' => self::svgKompas($kunci, true, $warnaIsi, $garis),
            'keterangan' => 'Mawar kompas; arah '.mb_strtolower($nama).' ditandai X dan arah '
                .'berlawanannya ditandai titik.',
            // Pengecoh pertamanya arah itu sendiri — kekeliruan yang muncul
            // saat yang dibaca ujung panah yang salah.
            'pilihan' => self::pilihanArah($lawan, $kunci),
            'kata_kunci' => $benar,
            'pembahasan' => 'Arah yang berlawanan selalu berselisih 180°. Sudut '
                .mb_strtolower($nama).' adalah '.self::ARAH[$kunci]['derajat'].'°, sehingga '
                .'lawannya '.self::ARAH[$kunci]['derajat'].'° + 180° = '
                .self::ARAH[$lawan]['derajat'].'°, yaitu '.mb_strtolower($benar).'.',
        ];
    }

    protected function derajat(string $kunci, string $warnaIsi, string $garis): array
    {
        $benar = self::ARAH[$kunci]['derajat'];
        $nama = self::ARAH[$kunci]['nama'];

        return [
            'judul' => mb_strtolower($nama).' = '.$benar.'°',
            'teks' => 'Perhatikan mawar kompas berikut. Arah X adalah '.mb_strtolower($nama)
                .'. Berapa besar sudutnya diukur dari utara searah jarum jam?',
            'pernyataan' => 'Pada mawar kompas berikut, sudut arah '.mb_strtolower($nama)
                .' dari utara adalah %s.',
            'svg' => self::svgKompas($kunci, false, $warnaIsi, $garis),
            'keterangan' => 'Mawar kompas; arah '.mb_strtolower($nama).' ditandai X.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => $n.'°', 'urut' => $n],
                // Pengecohnya sudut arah tetangga dan sudut arah yang
                // berlawanan — dua kekeliruan yang khas saat berhitung
                // dari utara.
                array_merge([$benar], array_values(array_diff(array_unique([
                    ($benar + 180) % 360,
                    ($benar + 45) % 360,
                    ($benar + 315) % 360,
                    ($benar + 90) % 360,
                    ($benar + 270) % 360,
                ]), [$benar]))),
            ),
            'kata_kunci' => $benar.'°',
            'pembahasan' => 'Sudut diukur dari utara (0°) searah jarum jam, dan tiap mata angin '
                .'berselisih 45°: '.implode(', ', array_map(
                    fn ($a) => $a['nama'].' '.$a['derajat'].'°', self::ARAH,
                )).'. Jadi '.mb_strtolower($nama).' = '.$benar.'°.',
        ];
    }

    /**
     * Pilihan jawaban berupa nama arah. Arah yang disebut lebih dahulu
     * dipakai sebagai pengecoh utama bila diberikan.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected static function pilihanArah(string $benar, ?string $utama = null): array
    {
        $nama = self::ARAH[$benar]['nama'];

        $kandidat = array_merge(
            $utama === null ? [] : [self::ARAH[$utama]['nama']],
            // Arah bertetangga lebih dahulu: yang keliru biasanya meleset
            // satu mata angin, bukan menyebut arah yang berseberangan.
            [
                self::ARAH[self::geser($benar, 1)]['nama'],
                self::ARAH[self::geser($benar, -1)]['nama'],
                self::ARAH[self::lawanArah($benar)]['nama'],
            ],
            array_map(fn ($a) => $a['nama'], array_values(self::ARAH)),
        );

        return array_map(
            fn ($t) => ['teks' => $t, 'urut' => $t],
            array_merge([$nama], array_values(array_diff(array_unique($kandidat), [$nama]))),
        );
    }

    /** Mata angin yang bergeser beberapa langkah searah jarum jam. */
    protected static function geser(string $kunci, int $langkah): string
    {
        $daftar = array_keys(self::ARAH);
        $ke = (array_search($kunci, $daftar, true) + $langkah + 8) % 8;

        return $daftar[$ke];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Mawar kompas delapan arah. Arah yang ditanyakan diganti huruf X,
     * sedangkan arah lainnya tetap bernama supaya siswa dapat menyimpulkan
     * dari arah di sekitarnya.
     */
    public static function svgKompas(string $ditandai, bool $tandaiLawan, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $pusat = [240, 146];
        $jari = 86;
        $lawan = self::lawanArah($ditandai);

        $kanvas->lingkaran($pusat, $jari, '#f8fafb', '#c2c8ce', 2);

        foreach (self::ARAH as $kunci => $arah) {
            $sudut = deg2rad($arah['derajat'] - 90);
            $ujung = [$pusat[0] + cos($sudut) * $jari, $pusat[1] + sin($sudut) * $jari];
            $ini = $kunci === $ditandai;

            $kanvas->panahPx($pusat, $ujung, $ini ? $garis : '#9aa1a9', $ini ? 4 : 1.8);

            $label = [$pusat[0] + cos($sudut) * ($jari + 26), $pusat[1] + sin($sudut) * ($jari + 26)];

            if ($ini) {
                $kanvas->lingkaran($label, 15, '#ffffff', $garis, 3)
                    ->teksPx([$label[0], $label[1] + 8], 'X', ['ukuran' => 22, 'tebal' => true]);

                continue;
            }

            // Arah berlawanan ditandai titik saja pada soal yang menanyakan
            // lawannya: ia menunjukkan letaknya tanpa menyebut namanya.
            if ($tandaiLawan && $kunci === $lawan) {
                $kanvas->lingkaran($label, 11, $isi, $garis, 2.5);

                continue;
            }

            $kanvas->teksPx([$label[0], $label[1] + 5], $arah['singkat'], [
                'ukuran' => 16, 'tebal' => true,
            ]);
        }

        $kanvas
            ->lingkaran($pusat, 7, Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([240, 290], 'U = utara, TL = timur laut, T = timur, TG = tenggara, '
                .'S = selatan, BD = barat daya, B = barat, BL = barat laut',
                ['ukuran' => 10, 'warna' => '#6b7280']);

        return $kanvas->render();
    }

    /** Denah desa: petak tiga kali tiga dengan sebuah tempat di tiap petak. */
    public static function svgDenah(string $ditandai, bool $balik, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$lebar, $tinggi] = [112, 66];
        $x0 = (Kanvas::LEBAR - 3 * $lebar) / 2 - 24;
        $y0 = 54;

        $letak = fn (array $petak) => [
            $x0 + ($petak[0] + 0.5) * $lebar,
            $y0 + ($petak[1] + 0.5) * $tinggi,
        ];

        foreach (self::ARAH as $kunci => $arah) {
            $ini = $kunci === $ditandai;

            $kanvas->kotakPx($letak($arah['petak']), $lebar, $tinggi, self::TEMPAT[$kunci],
                $ini && ! $balik ? $isi : '#ffffff', $ini ? $garis : '#9aa1a9', [
                    'ukuran' => 12, 'tebal' => $ini, 'sudut' => 0,
                    'tebal_garis' => $ini ? 3 : 1.5,
                ]);
        }

        $kanvas->kotakPx($letak([1, 1]), $lebar, $tinggi, self::PUSAT,
            $balik ? $isi : '#f2f4f6', $garis, [
                'ukuran' => 13, 'tebal' => true, 'sudut' => 0, 'tebal_garis' => $balik ? 3 : 2,
            ]);

        // Penunjuk utara di pojok kanan, di luar petak, supaya denahnya
        // terbaca tanpa harus menghafal bahwa atas berarti utara.
        $kanvas
            ->panahPx([438, 128], [438, 72], Kanvas::HITAM, 3)
            ->teksPx([438, 60], 'U', ['ukuran' => 18, 'tebal' => true])
            ->teksPx([438, 148], 'utara', ['ukuran' => 11, 'warna' => '#6b7280'])
            ->teksPx([Kanvas::LEBAR / 2 - 24, 282], 'Denah Desa Sukamaju', [
                'ukuran' => 13, 'tebal' => true,
            ]);

        return $kanvas->render();
    }
}

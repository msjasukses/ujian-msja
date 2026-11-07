<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template sistem bilangan biner, oktal, desimal, dan heksadesimal.
 *
 * Gambarnya berupa tabel nilai tempat: tiap lajur diberi pangkat basisnya
 * beserta nilainya, dan baris di bawahnya diisi angka bilangan yang
 * dikonversi. Dari situ siswa melihat bahwa konversi hanyalah menjumlahkan
 * nilai tempat yang angkanya tidak nol — bukan aturan hafalan.
 *
 * Hasil konversinya sendiri tidak pernah tertulis di gambar.
 */
class SistemBilangan extends Template
{
    /**
     * Basis yang didukung beserta lambang angkanya.
     *
     * @var array<string, array{nama: string, basis: int, angka: string}>
     */
    public const BASIS = [
        'biner' => ['nama' => 'biner', 'basis' => 2, 'angka' => '01'],
        'oktal' => ['nama' => 'oktal', 'basis' => 8, 'angka' => '01234567'],
        'desimal' => ['nama' => 'desimal', 'basis' => 10, 'angka' => '0123456789'],
        'heksadesimal' => ['nama' => 'heksadesimal', 'basis' => 16, 'angka' => '0123456789ABCDEF'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'ke_desimal' => 'Dari basis lain ke desimal',
        'dari_desimal' => 'Dari desimal ke basis lain',
        'nilai_tempat' => 'Nilai tempat sebuah angka',
        'tambah_biner' => 'Penjumlahan dua bilangan biner',
    ];

    public static function kode(): string
    {
        return 'sistem-bilangan';
    }

    public static function nama(): string
    {
        return 'Sistem Bilangan Biner';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Konversi bilangan biner, oktal, dan heksadesimal ke desimal dan '
            .'sebaliknya, nilai tempat tiap angka, serta penjumlahan biner.';
    }

    public static function ikon(): string
    {
        return 'bi-123';
    }

    public static function kataKunci(): array
    {
        return ['biner', 'oktal', 'heksadesimal', 'desimal', 'konversi bilangan',
            'sistem bilangan', 'basis', 'bit', 'nilai tempat', 'penjumlahan biner',
            'informatika', 'tik', 'komputer'];
    }

    public static function kolom(): array
    {
        $basis = array_map(
            fn ($b) => ucfirst($b['nama']).' (basis '.$b['basis'].')',
            array_filter(self::BASIS, fn ($b) => $b['basis'] !== 10),
        );

        return [
            self::kolomTanya(self::TANYA, 'ke_desimal'),
            [
                'nama' => 'basis',
                'label' => 'Basis bilangannya',
                'jenis' => 'pilihan',
                'pilihan' => $basis,
                'awal' => 'biner',
                'tampil' => ['tanya' => ['ke_desimal', 'dari_desimal', 'nilai_tempat']],
            ],
            self::kolomAngka('nilai', 'Bilangan (ditulis desimal)', 45, 2, 255, [
                'langkah' => 1,
                'bantuan' => 'Ditulis dalam desimal; template yang mengubahnya ke basis lain.',
                'tampil' => ['tanya' => ['ke_desimal', 'dari_desimal', 'nilai_tempat']],
            ]),
            self::kolomAngka('tempat', 'Angka ke- (dari kanan)', 1, 1, 8, [
                'langkah' => 1,
                'bantuan' => 'Angka pada tempat itu tidak boleh 0, sebab nilainya pasti 0 '
                    .'dan soalnya tidak lagi menguji apa pun.',
                'tampil' => ['tanya' => ['nilai_tempat']],
            ]),
            self::kolomAngka('suku_satu', 'Bilangan pertama (ditulis desimal)', 13, 1, 120, [
                'langkah' => 1, 'tampil' => ['tanya' => ['tambah_biner']],
            ]),
            self::kolomAngka('suku_dua', 'Bilangan kedua (ditulis desimal)', 11, 1, 120, [
                'langkah' => 1, 'tampil' => ['tanya' => ['tambah_biner']],
            ]),
            self::kolomWarna('abu'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? 'ke_desimal') !== 'nilai_tempat') {
            return [];
        }

        $basis = self::BASIS[$isi['basis'] ?? 'biner'] ?? self::BASIS['biner'];
        $panjang = strlen(self::tulis((int) ($isi['nilai'] ?? 45), $basis['basis']));

        $tempat = (int) ($isi['tempat'] ?? 1);

        if ($tempat > $panjang) {
            return ['tempat' => 'Bilangan '.$basis['nama'].' itu hanya punya '.$panjang
                .' angka, jadi angka ke-'.$tempat.' tidak ada. Perbesar bilangannya '
                .'atau pilih tempat yang lebih kecil.'];
        }

        // Angka 0 pada tempat itu membuat jawabannya pasti 0, dan semua
        // pengecoh yang dihitung darinya ikut menjadi 0. Butirnya berhenti
        // menguji apa pun, jadi ditolak di sini dengan keterangan yang jelas.
        $tulisan = self::tulis((int) ($isi['nilai'] ?? 45), $basis['basis']);

        if ($tulisan[$panjang - $tempat] === '0') {
            return ['tempat' => 'Angka ke-'.$tempat.' dari kanan pada '.$basis['nama'].' '
                .$tulisan.' adalah 0, sehingga nilainya pasti 0. Pilih tempat lain atau '
                .'ganti bilangannya.'];
        }

        return [];
    }

    /** Menulis sebuah bilangan desimal dalam basis lain, huruf besar. */
    public static function tulis(int $nilai, int $basis): string
    {
        return strtoupper(base_convert((string) $nilai, 10, $basis));
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'abu');

        if (($isi['tanya'] ?? 'ke_desimal') === 'tambah_biner') {
            return $this->tambahBiner(
                max(1, (int) ($isi['suku_satu'] ?? 13)),
                max(1, (int) ($isi['suku_dua'] ?? 11)),
                $warnaIsi, $garis,
            );
        }

        $kunci = isset(self::BASIS[$isi['basis'] ?? '']) && ($isi['basis'] ?? '') !== 'desimal'
            ? $isi['basis'] : 'biner';
        $basis = self::BASIS[$kunci];
        $nilai = max(2, (int) ($isi['nilai'] ?? 45));

        return match ($isi['tanya'] ?? 'ke_desimal') {
            'dari_desimal' => $this->dariDesimal($basis, $nilai, $warnaIsi, $garis),
            'nilai_tempat' => $this->nilaiTempat($basis, $nilai,
                max(1, (int) ($isi['tempat'] ?? 1)), $warnaIsi, $garis),
            default => $this->keDesimal($basis, $nilai, $warnaIsi, $garis),
        };
    }

    protected function keDesimal(array $basis, int $nilai, string $warnaIsi, string $garis): array
    {
        $tulisan = self::tulis($nilai, $basis['basis']);
        $b = $basis['basis'];

        // Pengecohnya kekeliruan yang khas: nilai tempatnya digeser satu
        // (dihitung mulai pangkat satu, bukan nol), angkanya sekadar
        // dijumlahkan, dan basisnya tertukar dengan basis lain.
        $pengecoh = self::saring((float) $nilai, [
            $nilai * $b,
            array_sum(array_map(fn ($a) => (int) base_convert($a, $b, 10), str_split($tulisan))),
            self::bacaDenganBasis($tulisan, $b === 2 ? 8 : 2),
            self::bacaDenganBasis($tulisan, $b === 16 ? 8 : 16),
            (int) $tulisan === 0 ? $nilai + 1 : (int) $tulisan,
            $nilai + 1,
            $nilai - 1,
        ], 0);

        return [
            'svg' => self::svgTempat($basis, $tulisan, null, $warnaIsi, $garis),
            'keterangan' => 'Tabel nilai tempat bilangan '.$basis['nama'].' '.$tulisan.'.',
            'judul' => $tulisan.' ('.$b.') = '.$nilai,
            'teks' => 'Bilangan '.$basis['nama'].' <strong>'.$tulisan
                .'</strong> jika diubah ke bilangan desimal bernilai ...',
            'pernyataan' => 'Bilangan '.$basis['nama'].' '.$tulisan
                .' sama dengan %s dalam bilangan desimal.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([(float) $nilai], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => (string) $nilai,
            'pembahasan' => 'Tiap angka dikalikan nilai tempatnya, yaitu '.$b
                .' pangkat kedudukannya dari kanan dimulai dari nol:<br>'
                .self::uraian($tulisan, $b).' = <strong>'.$nilai.'</strong>.',
        ];
    }

    protected function dariDesimal(array $basis, int $nilai, string $warnaIsi, string $garis): array
    {
        $b = $basis['basis'];
        $benar = self::tulis($nilai, $b);

        // Pengecohnya penulisan bilangan yang sama pada basis lain dan satu
        // angka yang tertukar — bukan deretan angka yang dikarang.
        $kandidat = [
            self::tulis($nilai, $b === 2 ? 8 : 2),
            self::tulis($nilai, $b === 16 ? 8 : 16),
            self::tulis($nilai + 1, $b),
            self::tulis($nilai - 1, $b),
            strrev($benar),
            self::tulis($nilai * 2, $b),
        ];

        $pengecoh = [];

        foreach ($kandidat as $teks) {
            if ($teks !== $benar && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'svg' => self::svgBagi($nilai, $b, $warnaIsi, $garis),
            'keterangan' => 'Cara pembagian berulang untuk mengubah '.$nilai.' ke basis '.$b.'.',
            'judul' => $nilai.' = '.$benar.' ('.$b.')',
            'teks' => 'Bilangan desimal <strong>'.Angka::id($nilai)
                .'</strong> jika ditulis dalam bilangan '.$basis['nama'].' menjadi ...',
            'pernyataan' => 'Bilangan desimal '.Angka::id($nilai).' ditulis '.$basis['nama']
                .' menjadi %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Bilangan desimal dibagi '.$b.' berulang-ulang, lalu sisanya dibaca '
                .'dari bawah ke atas:<br>'.self::uraianBagi($nilai, $b)
                .'<br>Dibaca dari bawah: <strong>'.$benar.'</strong>.',
        ];
    }

    protected function nilaiTempat(array $basis, int $nilai, int $tempat, string $warnaIsi, string $garis): array
    {
        $b = $basis['basis'];
        $tulisan = self::tulis($nilai, $b);
        $panjang = strlen($tulisan);
        $tempat = min($tempat, $panjang);

        // Kedudukan dihitung dari kanan mulai nol, jadi angka ke-1 dari
        // kanan berpangkat nol.
        $pangkat = $tempat - 1;
        $angka = $tulisan[$panjang - $tempat];
        $digit = (int) base_convert($angka, $b, 10);
        $benar = $digit * $b ** $pangkat;

        $pengecoh = self::saring((float) $benar, [
            // Kedudukan dihitung mulai satu, bukan nol.
            $digit * $b ** $tempat,
            // Tempatnya dihitung dari kiri.
            (int) base_convert($tulisan[$tempat - 1], $b, 10) * $b ** ($panjang - $tempat),
            // Angkanya dibaca tanpa dikalikan nilai tempat.
            $digit,
            // Nilai tempatnya saja, angkanya lupa dikalikan.
            $b ** $pangkat,
            // Pangkat dikira perkalian biasa.
            $digit * $b * $pangkat,
            // Nilai seluruh bilangannya, bukan satu angkanya saja.
            $nilai,
            $benar * $b,
            $benar + $digit,
            $b ** ($pangkat + 1),
            // Nilai tempat satu tingkat di atas angka paling kiri —
            // cadangan untuk bilangan pendek seperti 11, yang pengecoh
            // lainnya banyak berkembar.
            $b ** $panjang,
        ], 0);

        return [
            'svg' => self::svgTempat($basis, $tulisan, $tempat, $warnaIsi, $garis),
            'keterangan' => 'Tabel nilai tempat bilangan '.$basis['nama'].' '.$tulisan
                .'; angka ke-'.$tempat.' dari kanan ditandai.',
            'judul' => 'Angka ke-'.$tempat.' pada '.$tulisan.' bernilai '.$benar,
            'teks' => 'Pada bilangan '.$basis['nama'].' <strong>'.$tulisan.'</strong>, angka ke-'
                .$tempat.' dari kanan (angka <strong>'.$angka.'</strong>) bernilai ...',
            'pernyataan' => 'Pada bilangan '.$basis['nama'].' '.$tulisan.', angka ke-'.$tempat
                .' dari kanan bernilai %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([(float) $benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => 'Kedudukan angka dihitung dari kanan mulai dari nol, sehingga angka '
                .'ke-'.$tempat.' berkedudukan '.$pangkat.'.<br>Nilainya = '.$angka.' × '.$b
                .'<sup>'.$pangkat.'</sup>'.' = '.$digit.' × '.$b ** $pangkat
                .' = <strong>'.$benar.'</strong>.',
        ];
    }

    protected function tambahBiner(int $satu, int $dua, string $warnaIsi, string $garis): array
    {
        $jumlah = $satu + $dua;
        $a = self::tulis($satu, 2);
        $b = self::tulis($dua, 2);
        $benar = self::tulis($jumlah, 2);

        // Pengecoh yang khas pada penjumlahan biner: simpanan lupa
        // dituliskan sehingga tiap kedudukan dijumlahkan sendiri-sendiri,
        // dan hasil yang kelebihan atau kekurangan satu.
        $kandidat = [
            self::tanpaSimpanan($a, $b),
            self::tulis($jumlah + 1, 2),
            self::tulis(max(1, $jumlah - 1), 2),
            self::tulis($jumlah * 2, 2),
            self::tulis(abs($satu - $dua) ?: $jumlah + 2, 2),
        ];

        $pengecoh = [];

        foreach ($kandidat as $teks) {
            if ($teks !== $benar && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'svg' => self::svgTambah($a, $b, $warnaIsi, $garis),
            'keterangan' => 'Penjumlahan bersusun bilangan biner '.$a.' dan '.$b.'.',
            'judul' => $a.' + '.$b.' = '.$benar,
            'teks' => 'Hasil penjumlahan bilangan biner <strong>'.$a.'</strong> + <strong>'
                .$b.'</strong> adalah ...',
            'pernyataan' => 'Hasil penjumlahan bilangan biner '.$a.' + '.$b.' adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Pada penjumlahan biner, 1 + 1 = 10, artinya ditulis 0 dengan '
                .'simpanan 1 ke kedudukan sebelah kiri.<br>Dalam desimal '.$a.' = '.$satu
                .' dan '.$b.' = '.$dua.', jumlahnya '.$satu.' + '.$dua.' = '.$jumlah
                .'.<br>'.$jumlah.' ditulis biner menjadi <strong>'.$benar.'</strong>.',
        ];
    }

    // =====================================================================
    // Hitungan pembantu
    // =====================================================================

    /** Membaca deretan angka seolah-olah bertulis basis lain; 0 bila tak sah. */
    protected static function bacaDenganBasis(string $tulisan, int $basis): int
    {
        $angka = substr(self::BASIS['heksadesimal']['angka'], 0, $basis);

        foreach (str_split($tulisan) as $a) {
            if (! str_contains($angka, $a)) {
                return 0;
            }
        }

        return (int) base_convert($tulisan, $basis, 10);
    }

    /** Uraian perkalian nilai tempat, untuk pembahasan. */
    protected static function uraian(string $tulisan, int $basis): string
    {
        $bagian = [];
        $panjang = strlen($tulisan);

        foreach (str_split($tulisan) as $i => $angka) {
            $pangkat = $panjang - $i - 1;
            $digit = (int) base_convert($angka, $basis, 10);

            $bagian[] = $digit.'×'.$basis.'<sup>'.$pangkat.'</sup>';
        }

        $hasil = [];

        foreach (str_split($tulisan) as $i => $angka) {
            $hasil[] = (int) base_convert($angka, $basis, 10) * $basis ** ($panjang - $i - 1);
        }

        return implode(' + ', $bagian).'<br>= '.implode(' + ', $hasil);
    }

    /** Uraian pembagian berulang, untuk pembahasan. */
    protected static function uraianBagi(int $nilai, int $basis): string
    {
        $baris = [];

        while ($nilai > 0) {
            $sisa = $nilai % $basis;
            $baris[] = $nilai.' : '.$basis.' = '.intdiv($nilai, $basis).' sisa '
                .strtoupper(base_convert((string) $sisa, 10, $basis));
            $nilai = intdiv($nilai, $basis);
        }

        return implode('<br>', $baris);
    }

    /** Penjumlahan biner tanpa simpanan — kekeliruan yang dijadikan pengecoh. */
    protected static function tanpaSimpanan(string $a, string $b): string
    {
        $panjang = max(strlen($a), strlen($b));
        $a = str_pad($a, $panjang, '0', STR_PAD_LEFT);
        $b = str_pad($b, $panjang, '0', STR_PAD_LEFT);

        $hasil = '';

        for ($i = 0; $i < $panjang; $i++) {
            $hasil .= (string) ((int) $a[$i] + (int) $b[$i]);
        }

        return ltrim($hasil, '0') ?: '0';
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Tabel nilai tempat: pangkat basis di atas, angka bilangannya di bawah. */
    public static function svgTempat(array $basis, string $tulisan, ?int $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $b = $basis['basis'];
        $angka = str_split($tulisan);
        $panjang = count($angka);

        $lebar = min(64.0, 380 / max(1, $panjang));
        $kiri = 240 - $panjang * $lebar / 2;
        $atas = 96.0;
        $tinggi = 42.0;

        $kanvas->teksPx([240, 34], 'Nilai tempat bilangan '.$basis['nama'], [
            'ukuran' => 15, 'tebal' => true,
        ]);

        foreach ($angka as $i => $a) {
            $pangkat = $panjang - $i - 1;
            $x = $kiri + $i * $lebar;
            $tegas = $ditandai !== null && $pangkat === $ditandai - 1;

            $kanvas
                // Baris atas: nilai tempat tiap lajur.
                ->tambah('<rect x="'.round($x, 1).'" y="'.$atas.'" width="'.round($lebar, 1)
                    .'" height="'.$tinggi.'" fill="'.($tegas ? $isi : '#f3f4f6')
                    .'" stroke="'.$garis.'" stroke-width="1.5"/>')
                ->rumusPx([$x + $lebar / 2, $atas + 20], $b.'^'.$pangkat, ['ukuran' => 14])
                ->teksPx([$x + $lebar / 2, $atas + 36], (string) ($b ** $pangkat), [
                    'ukuran' => 11, 'warna' => '#4b5563',
                ])
                // Baris bawah: angka bilangannya.
                ->tambah('<rect x="'.round($x, 1).'" y="'.($atas + $tinggi).'" width="'
                    .round($lebar, 1).'" height="'.$tinggi.'" fill="'.($tegas ? $isi : '#ffffff')
                    .'" stroke="'.$garis.'" stroke-width="'.($tegas ? 3 : 1.5).'"/>')
                ->teksPx([$x + $lebar / 2, $atas + $tinggi + 28], $a, [
                    'ukuran' => 20, 'tebal' => true,
                ]);
        }

        $kanvas
            ->teksPx([240, $atas - 14], 'nilai tempat', ['ukuran' => 11, 'warna' => '#6b7280'])
            ->teksPx([240, $atas + 2 * $tinggi + 24], 'bilangan '.$basis['nama'].': '.$tulisan, [
                'ukuran' => 14, 'tebal' => true,
            ]);

        if ($ditandai !== null) {
            $kanvas->teksPx([240, $atas + 2 * $tinggi + 50],
                'angka ke-'.$ditandai.' dari kanan ditandai', [
                    'ukuran' => 11, 'warna' => '#6b7280',
                ]);
        }

        return $kanvas->render();
    }

    /** Tangga pembagian berulang, tanpa menuliskan hasil bacaannya. */
    public static function svgBagi(int $nilai, int $basis, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $baris = [];

        while ($nilai > 0) {
            $baris[] = [
                (string) $nilai,
                (string) intdiv($nilai, $basis),
                strtoupper(base_convert((string) ($nilai % $basis), 10, $basis)),
            ];
            $nilai = intdiv($nilai, $basis);
        }

        $tinggiBaris = min(30.0, 180 / max(1, count($baris)));
        $atas = 86.0;
        $lebar = [120.0, 110.0, 90.0];
        $kiri = 240 - array_sum($lebar) / 2;

        $kanvas
            ->teksPx([240, 34], 'Pembagian berulang dengan '.$basis, ['ukuran' => 15, 'tebal' => true])
            ->tabelPx([$kiri, $atas - $tinggiBaris], $lebar, $tinggiBaris, array_merge(
                [['dibagi', 'hasil bagi', 'sisa']],
                $baris,
            ), $isi, $garis, 12);

        // Panah pembacaan sisa dari bawah ke atas; isinya tidak dituliskan.
        $kananTabel = $kiri + array_sum($lebar);
        $bawah = $atas + count($baris) * $tinggiBaris;

        return $kanvas
            ->panahPx([$kananTabel + 22, $bawah - 6], [$kananTabel + 22, $atas + 6], $garis, 2)
            ->teksPx([$kananTabel + 10, ($atas + $bawah) / 2], 'dibaca', [
                'anchor' => 'start', 'ukuran' => 10, 'warna' => '#6b7280',
            ])
            ->teksPx([240, $bawah + 32], 'sisa pembagian dibaca dari baris terbawah ke atas', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }

    /** Penjumlahan bersusun dua bilangan biner, tanpa hasilnya. */
    public static function svgTambah(string $a, string $b, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $panjang = max(strlen($a), strlen($b)) + 1;
        $lebar = min(40.0, 360 / $panjang);
        $kanan = 240 + $panjang * $lebar / 2;
        $atas = 92.0;

        $kanvas->teksPx([240, 36], 'Penjumlahan biner', ['ukuran' => 16, 'tebal' => true]);

        foreach ([[$a, $atas], [$b, $atas + 40]] as [$bilangan, $y]) {
            foreach (array_reverse(str_split($bilangan)) as $i => $angka) {
                $kanvas->teksPx([$kanan - ($i + 0.5) * $lebar, $y], $angka, [
                    'ukuran' => 22, 'tebal' => true,
                ]);
            }
        }

        $kiriGaris = $kanan - $panjang * $lebar;

        return $kanvas
            ->teksPx([$kiriGaris - 6, $atas + 40], '+', ['ukuran' => 22, 'tebal' => true])
            ->garis([$kiriGaris - 16, $atas + 54], [$kanan + 6, $atas + 54], Kanvas::HITAM, 2.5)
            // Baris hasil dibiarkan kosong — itulah yang ditanyakan.
            ->tambah('<rect x="'.round($kiriGaris - 16, 1).'" y="'.($atas + 62).'" width="'
                .round($kanan - $kiriGaris + 22, 1).'" height="40" rx="8" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="2" stroke-dasharray="6 5"/>')
            ->teksPx([240, $atas + 88], '?', ['ukuran' => 24, 'tebal' => true, 'warna' => $garis])
            ->teksPx([240, $atas + 132], '1 + 1 = 10, yaitu tulis 0 simpan 1 ke kiri', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template penyajian data: diagram batang, garis, piktogram, lingkaran, dan
 * tabel.
 *
 * Datanya diketik guru sebagai dua baris — nama kategori dan angkanya —
 * lalu bentuk penyajiannya tinggal dipilih. Satu kumpulan data yang sama
 * karenanya dapat disajikan lima cara, dan pertanyaan membaca datanya tetap
 * sama persis untuk kelimanya: itulah yang memang diuji, bahwa siswa dapat
 * membaca data dari bentuk penyajian mana pun.
 *
 * Melayani Matematika maupun IPS tanpa perlu template terpisah; yang
 * berganti cuma isi datanya.
 */
class DiagramData extends Template
{
    /** @var array<string, string> */
    public const BENTUK = [
        'batang' => 'Diagram batang',
        'garis' => 'Diagram garis',
        'piktogram' => 'Piktogram (diagram lambang)',
        'lingkaran' => 'Diagram lingkaran',
        'tabel' => 'Tabel data',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'tertinggi' => 'Kategori dengan nilai tertinggi',
        'terendah' => 'Kategori dengan nilai terendah',
        'tertentu' => 'Nilai satu kategori',
        'selisih' => 'Selisih tertinggi dan terendah',
        'jumlah' => 'Jumlah seluruh data',
        'persen' => 'Persentase satu kategori',
        'sudut' => 'Besar sudut juring (digambar sebagai lingkaran)',
    ];

    /**
     * Warna juring diagram lingkaran. Dipilih sendiri, bukan mengikuti warna
     * yang dipilih guru: tiap juring harus terbedakan dari tetangganya,
     * sedangkan warna pilihan guru hanya satu.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const PALET = [
        ['#bfe0fb', '#1c6ea4'], ['#fdeab0', '#a97d10'], ['#c9ecc9', '#2e7d32'],
        ['#fbcfd9', '#b23a5c'], ['#ded3f8', '#6a4bbc'], ['#fddcb9', '#c0621b'],
        ['#cfe8e6', '#2a7f78'], ['#d7d9f5', '#4a4fa8'],
    ];

    /** Batas banyak kategori; lebih dari ini tulisannya tidak lagi terbaca. */
    protected const MAKS = 8;

    /** Batas banyak lambang pada satu baris piktogram. */
    protected const MAKS_LAMBANG = 12;

    public static function kode(): string
    {
        return 'diagram-data';
    }

    public static function nama(): string
    {
        return 'Diagram & Tabel Data';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Ketik datanya sekali, sajikan sebagai diagram batang, garis, '
            .'piktogram, lingkaran, atau tabel. Tanya nilai tertinggi, selisih, '
            .'jumlah, persentase, atau besar sudut juringnya.';
    }

    public static function ikon(): string
    {
        return 'bi-bar-chart';
    }

    public static function kataKunci(): array
    {
        return ['diagram', 'grafik', 'batang', 'garis', 'piktogram', 'lingkaran',
            'tabel', 'data', 'statistika', 'penyajian data', 'juring', 'persentase', 'ips'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'bentuk',
                'label' => 'Bentuk penyajian',
                'jenis' => 'pilihan',
                'pilihan' => self::BENTUK,
                'awal' => 'batang',
            ],
            [
                'nama' => 'judul',
                'label' => 'Judul diagram',
                'jenis' => 'teks',
                'awal' => 'Banyak Buku yang Dibaca Siswa',
            ],
            [
                'nama' => 'kategori',
                'label' => 'Nama kategori',
                'jenis' => 'teks',
                'awal' => 'Senin, Selasa, Rabu, Kamis, Jumat',
                'bantuan' => 'Dipisah koma, paling banyak '.self::MAKS.' kategori.',
            ],
            [
                'nama' => 'nilai',
                'label' => 'Angka tiap kategori',
                'jenis' => 'teks',
                'awal' => '4, 7, 5, 9, 6',
                'bantuan' => 'Dipisah koma, banyaknya harus sama dengan kategori. Untuk angka desimal, pisahkan dengan titik koma: 4,5; 6; 7,2.',
            ],
            [
                'nama' => 'satuan',
                'label' => 'Satuan data',
                'jenis' => 'teks',
                'awal' => 'buku',
                'lebar' => 6,
            ],
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'tertinggi',
                'lebar' => 6,
            ],
            [
                'nama' => 'kategori_ke',
                'label' => 'Kategori ke-',
                'jenis' => 'angka',
                'awal' => 2,
                'min' => 1,
                'max' => self::MAKS,
                'langkah' => 1,
                'lebar' => 6,
                'tampil' => ['tanya' => ['tertentu', 'persen', 'sudut']],
            ],
            self::kolomWarna(),
        ];
    }

    public static function periksa(array $isi): array
    {
        $kategori = self::daftar((string) ($isi['kategori'] ?? ''));
        $teksNilai = self::daftar((string) ($isi['nilai'] ?? ''));

        if (count($kategori) < 2) {
            return ['kategori' => 'Isi minimal dua kategori, dipisah koma.'];
        }

        if (count($kategori) > self::MAKS) {
            return ['kategori' => 'Paling banyak '.self::MAKS.' kategori agar diagramnya tetap terbaca.'];
        }

        if (count($teksNilai) !== count($kategori)) {
            return ['nilai' => 'Banyak angka ('.count($teksNilai).') harus sama dengan banyak kategori ('
                .count($kategori).').'];
        }

        foreach ($teksNilai as $n) {
            if (! is_numeric(str_replace(',', '.', $n))) {
                return ['nilai' => "\"{$n}\" bukan angka."];
            }
        }

        $nilai = self::angka($teksNilai);
        $tanya = $isi['tanya'] ?? 'tertinggi';
        $bentuk = self::bentuk($isi);

        if (in_array($tanya, ['tertentu', 'persen', 'sudut'], true)
            && (int) ($isi['kategori_ke'] ?? 0) > count($kategori)) {
            return ['kategori_ke' => 'Hanya ada '.count($kategori).' kategori.'];
        }

        if (in_array($tanya, ['persen', 'sudut'], true) && array_sum($nilai) <= 0) {
            return ['nilai' => 'Jumlah seluruh data harus lebih dari nol untuk menghitung persentase.'];
        }

        if ($bentuk === 'piktogram') {
            return self::periksaPiktogram($nilai);
        }

        return [];
    }

    /**
     * Piktogram hanya jujur bila tiap nilai merupakan kelipatan bulat dari
     * satu lambang. Lambang setengah membuat nilainya tidak lagi terbaca
     * pasti, padahal justru itu yang ditanyakan.
     *
     * @param  array<int, float>  $nilai
     * @return array<string, string>
     */
    protected static function periksaPiktogram(array $nilai): array
    {
        foreach ($nilai as $n) {
            if ($n < 0 || abs($n - round($n)) > 0.0001) {
                return ['nilai' => 'Piktogram hanya untuk bilangan bulat tak negatif.'];
            }
        }

        $satuan = self::satuanLambang($nilai);

        if (max($nilai) / $satuan > self::MAKS_LAMBANG) {
            return ['nilai' => 'Angkanya terlalu beragam untuk piktogram: dengan satu lambang = '
                .Angka::id($satuan).', barisnya butuh lebih dari '.self::MAKS_LAMBANG.' lambang. '
                .'Pakai angka yang kelipatan sama, atau pilih bentuk penyajian lain.'];
        }

        return [];
    }

    /**
     * Bentuk penyajian yang benar-benar dipakai.
     *
     * Juring hanya ada pada diagram lingkaran, jadi soal yang menanyakan
     * sudut juring selalu digambar melingkar apa pun pilihan guru — menolak
     * gabungannya hanya akan memaksa guru menebak-nebak bentuk mana yang
     * diizinkan. Label pilihannya menyebutkan hal ini supaya tidak mengejutkan.
     */
    public static function bentuk(array $isi): string
    {
        if (($isi['tanya'] ?? '') === 'sudut') {
            return 'lingkaran';
        }

        return isset(self::BENTUK[$isi['bentuk'] ?? '']) ? $isi['bentuk'] : 'batang';
    }

    /** Nilai satu lambang piktogram: pembagi terbesar seluruh datanya. */
    public static function satuanLambang(array $nilai): int
    {
        $pembagi = 0;

        foreach ($nilai as $n) {
            $pembagi = self::fpb($pembagi, (int) round($n));
        }

        return max(1, $pembagi);
    }

    protected static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
    }

    protected function susun(array $isi): array
    {
        $kategori = self::daftar((string) $isi['kategori']);
        $nilai = self::angka(self::daftar((string) $isi['nilai']));
        $satuan = trim((string) ($isi['satuan'] ?? ''));
        $judul = trim((string) ($isi['judul'] ?? ''));
        $bentuk = self::bentuk($isi);
        $tanya = $isi['tanya'] ?? 'tertinggi';

        $maks = max($nilai);
        $min = min($nilai);
        $jumlah = array_sum($nilai);

        // Nama kategori, satuan, dan judul diketik guru. Gambar memakai nilai
        // aslinya — Kanvas meloloskannya sendiri — sedangkan teks butir yang
        // berisi HTML memakai salinan yang sudah diloloskan.
        $kategoriAman = array_map(fn ($k) => e($k), $kategori);
        $satuanAman = e($satuan);
        $namaMaks = $kategoriAman[array_search($maks, $nilai, true)];
        $namaMin = $kategoriAman[array_search($min, $nilai, true)];

        $ke = max(1, min(count($kategori), (int) ($isi['kategori_ke'] ?? 1))) - 1;
        $sebutan = mb_strtolower(self::BENTUK[$bentuk] ?? 'diagram');
        $awalan = $judul === ''
            ? 'Perhatikan '.$sebutan.' berikut.'
            : 'Perhatikan '.$sebutan.' "'.e($judul).'" berikut.';

        [$teks, $pernyataan, $pilihan, $pembahasan] = match ($tanya) {
            'tertinggi' => [
                'Kategori manakah yang nilainya paling tinggi?',
                'Nilai paling tinggi ada pada %s.',
                $this->pilihanNama($kategoriAman, $namaMaks),
                "Nilai paling besar ada pada {$namaMaks}, yaitu "
                    .Angka::dengan($maks, $satuanAman).'.',
            ],
            'terendah' => [
                'Kategori manakah yang nilainya paling rendah?',
                'Nilai paling rendah ada pada %s.',
                $this->pilihanNama($kategoriAman, $namaMin),
                "Nilai paling kecil ada pada {$namaMin}, yaitu "
                    .Angka::dengan($min, $satuanAman).'.',
            ],
            'tertentu' => [
                "Berapakah nilai pada {$kategoriAman[$ke]}?",
                "Nilai pada {$kategoriAman[$ke]} adalah %s.",
                $this->pilihanAngka($nilai[$ke], array_values(array_diff($nilai, [$nilai[$ke]])), $satuanAman),
                "Nilai {$kategoriAman[$ke]} terbaca ".Angka::dengan($nilai[$ke], $satuanAman).'.',
            ],
            'selisih' => [
                'Berapakah selisih nilai tertinggi dan nilai terendah?',
                'Selisih nilai tertinggi dan terendah adalah %s.',
                $this->pilihanAngka($maks - $min, [$maks, $min, $maks + $min, $jumlah], $satuanAman),
                "Nilai tertinggi {$namaMaks} = ".Angka::id($maks).", nilai terendah {$namaMin} = "
                    .Angka::id($min).'. Selisihnya '.Angka::id($maks).' − '.Angka::id($min).' = '
                    .Angka::dengan($maks - $min, $satuanAman).'.',
            ],
            'persen' => $this->persen($kategoriAman[$ke], $nilai[$ke], $jumlah, $satuanAman),
            'sudut' => $this->sudut($kategoriAman[$ke], $nilai[$ke], $jumlah, $satuanAman),
            default => [
                'Berapakah jumlah seluruh data pada diagram tersebut?',
                'Jumlah seluruh data pada diagram tersebut adalah %s.',
                $this->pilihanAngka($jumlah, [$maks, $maks - $min, $jumlah - $min], $satuanAman),
                'Jumlah seluruh data = '.implode(' + ', array_map(fn ($n) => Angka::id($n), $nilai))
                    .' = '.Angka::dengan($jumlah, $satuanAman).'.',
            ],
        };

        return [
            'judul' => $judul === '' ? self::nama() : $judul,
            'teks' => $awalan.' '.$teks,
            'pernyataan' => $awalan.' '.$pernyataan,
            'svg' => self::svg($bentuk, $judul, $kategori, $nilai, $satuan, $isi['warna'] ?? 'biru'),
            'keterangan' => ucfirst($sebutan).' '.count($kategori).' kategori: '
                .implode(', ', array_map(
                    fn ($k, $n) => $k.' '.Angka::id($n),
                    $kategori,
                    $nilai,
                )).'.',
            'pilihan' => $pilihan,
            'pembahasan' => $pembahasan,
        ];
    }

    /** @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: string} */
    protected function persen(string $nama, float $nilai, float $jumlah, string $satuan): array
    {
        $benar = round($nilai / $jumlah * 100, 1);

        return [
            "Berapa persen bagian {$nama} dari seluruh data?",
            "Bagian {$nama} adalah %s dari seluruh data.",
            array_map(
                fn ($n) => ['teks' => Angka::id($n, 1).'%', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Pengecoh utamanya membagi dengan angka yang salah:
                    // dengan nilai terbesar, atau dengan banyak kategori.
                    100 - $benar, $nilai, $jumlah - $nilai, $benar * 2, $benar / 2,
                ], 1)),
            ),
            'Persentase = nilai kategori ÷ jumlah seluruh data × 100%.<br>'
                .Angka::id($nilai).' ÷ '.Angka::id($jumlah).' × 100% = '.Angka::id($benar, 1).'%.',
        ];
    }

    /** @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: string} */
    protected function sudut(string $nama, float $nilai, float $jumlah, string $satuan): array
    {
        $benar = round($nilai / $jumlah * 360, 1);

        return [
            "Berapakah besar sudut juring {$nama}?",
            "Besar sudut juring {$nama} adalah %s.",
            array_map(
                fn ($n) => ['teks' => Angka::id($n, 1).'°', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Pengecoh terpentingnya memakai 180° — setengah
                    // lingkaran dikira satu putaran penuh.
                    round($nilai / $jumlah * 180, 1), 360 - $benar,
                    round($nilai / $jumlah * 100, 1), $benar * 2, $benar / 2,
                ], 1)),
            ),
            'Satu lingkaran penuh 360°, jadi sudut juring = nilai kategori ÷ jumlah seluruh '
                .'data × 360°.<br>'.Angka::id($nilai).' ÷ '.Angka::id($jumlah).' × 360° = '
                .Angka::id($benar, 1).'°.',
        ];
    }

    /**
     * Pengecoh untuk jawaban berupa nama kategori: kategori lain yang ada di
     * diagram itu juga, bukan nama karangan.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected function pilihanNama(array $kategori, string $benar): array
    {
        $lain = array_values(array_diff($kategori, [$benar]));

        return array_map(
            fn ($nama) => ['teks' => $nama, 'urut' => $nama],
            array_merge([$benar], array_slice($lain, 0, self::JUMLAH_OPSI - 1)),
        );
    }

    /**
     * Pengecoh untuk jawaban berupa angka: angka lain yang memang tertera di
     * diagram, lalu kekeliruan hitung yang lazim.
     *
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function pilihanAngka(float $benar, array $kandidat, string $satuan): array
    {
        $nilai = array_merge(
            [$benar],
            self::saring($benar, array_merge($kandidat, [$benar + 1, $benar - 1, $benar * 2])),
        );

        return array_map(
            fn ($n) => ['teks' => Angka::dengan($n, $satuan), 'urut' => $n],
            array_slice($nilai, 0, self::JUMLAH_OPSI),
        );
    }

    /**
     * Angka bergaya Indonesia menjadi pecahan: "4,5" dibaca 4.5.
     *
     * @param  array<int, string>  $teks
     * @return array<int, float>
     */
    protected static function angka(array $teks): array
    {
        return array_map(fn ($n) => (float) str_replace(',', '.', $n), $teks);
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Satu kumpulan data, lima bentuk penyajian. Semua memakai judul dan
     * satuan yang sama, sehingga soal yang sama dapat dipindahkan dari satu
     * bentuk ke bentuk lain tanpa mengubah apa pun selain gambarnya.
     *
     * @param  array<int, string>  $kategori
     * @param  array<int, float>  $nilai
     */
    public static function svg(string $bentuk, string $judul, array $kategori, array $nilai, string $satuan, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['biru'];

        $kanvas = new Kanvas;

        if ($judul !== '') {
            $kanvas->teksPx([Kanvas::LEBAR / 2, 24], $judul, ['ukuran' => 15, 'tebal' => true]);
        }

        $atas = $judul === '' ? 34 : 50;

        match ($bentuk) {
            'garis' => self::sumbuNilai($kanvas, $kategori, $nilai, $satuan, $atas, $isi, $garis, true),
            'piktogram' => self::gambarPiktogram($kanvas, $kategori, $nilai, $satuan, $atas, $isi, $garis),
            'lingkaran' => self::gambarLingkaran($kanvas, $kategori, $nilai, $atas),
            'tabel' => self::gambarTabel($kanvas, $kategori, $nilai, $satuan, $atas, $isi, $garis),
            default => self::sumbuNilai($kanvas, $kategori, $nilai, $satuan, $atas, $isi, $garis, false),
        };

        return $kanvas->render();
    }

    /**
     * Bidang bersumbu untuk diagram batang dan diagram garis. Keduanya
     * memakai sumbu dan skala yang sama persis — yang berbeda hanya batang
     * atau titik berpenghubung — supaya siswa melihat bahwa keduanya
     * menyajikan data yang sama.
     */
    protected static function sumbuNilai(Kanvas $kanvas, array $kategori, array $nilai, string $satuan, float $atas, string $isi, string $garis, bool $garisPatah): Kanvas
    {
        $kiri = 46;
        $kanan = Kanvas::LEBAR - 18;
        $bawah = Kanvas::TINGGI - 44;

        // Sumbu tegak dibulatkan ke atas supaya puncak tertinggi tidak
        // menempel ke tepi kanvas dan angkanya masih muat ditulis.
        $skalaMaks = max(1, ceil(max($nilai) * 1.15));
        $langkah = max(1, (int) ceil($skalaMaks / 5));
        $skalaMaks = $langkah * (int) ceil($skalaMaks / $langkah);

        for ($v = 0; $v <= $skalaMaks; $v += $langkah) {
            $y = $bawah - ($v / $skalaMaks) * ($bawah - $atas);

            $kanvas->tambah('<line x1="'.$kiri.'" y1="'.round($y, 1).'" x2="'.$kanan.'" y2="'.round($y, 1)
                .'" stroke="#d6dade" stroke-width="1"/>')
                ->teksPx([$kiri - 8, $y + 4], Angka::id($v), [
                    'anchor' => 'end', 'ukuran' => 12, 'warna' => '#4b5563',
                ]);
        }

        $lebarKotak = ($kanan - $kiri) / count($nilai);
        $titik = [];

        foreach ($nilai as $i => $n) {
            $tengah = $kiri + $lebarKotak * ($i + 0.5);
            $tinggi = ($n / $skalaMaks) * ($bawah - $atas);
            $titik[] = [$tengah, $bawah - $tinggi];

            if (! $garisPatah) {
                $lebarBatang = $lebarKotak * 0.56;

                $kanvas->tambah('<rect x="'.round($tengah - $lebarBatang / 2, 1).'" y="'.round($bawah - $tinggi, 1)
                    .'" width="'.round($lebarBatang, 1).'" height="'.round($tinggi, 1)
                    .'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.5"/>');
            }

            $kanvas->teksPx([$tengah, $bawah - $tinggi - 8], Angka::id($n), ['ukuran' => 13, 'tebal' => true])
                ->teksPx([$tengah, $bawah + 18], $kategori[$i], ['ukuran' => 12]);
        }

        if ($garisPatah) {
            $kanvas->garisPatah($titik, $garis, 2.5);

            foreach ($titik as $t) {
                $kanvas->lingkaran($t, 5, $isi, $garis, 2);
            }
        }

        return self::sumbu($kanvas, $kiri, $kanan, $atas, $bawah, $satuan);
    }

    protected static function sumbu(Kanvas $kanvas, float $kiri, float $kanan, float $atas, float $bawah, string $satuan): Kanvas
    {
        $kanvas->tambah('<line x1="'.$kiri.'" y1="'.$atas.'" x2="'.$kiri.'" y2="'.$bawah
            .'" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>')
            ->tambah('<line x1="'.$kiri.'" y1="'.$bawah.'" x2="'.$kanan.'" y2="'.$bawah
                .'" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>');

        if ($satuan !== '') {
            $kanvas->teksPx([8, $atas - 10], $satuan, [
                'anchor' => 'start', 'ukuran' => 12, 'warna' => '#4b5563',
            ]);
        }

        return $kanvas;
    }

    /**
     * Piktogram: tiap baris sebanyak nilainya dibagi nilai satu lambang.
     * Nilai satu lambang ditulis di bawah gambar, sebab tanpa keterangan itu
     * piktogram tidak dapat dibaca.
     */
    protected static function gambarPiktogram(Kanvas $kanvas, array $kategori, array $nilai, string $satuan, float $atas, string $isi, string $garis): Kanvas
    {
        $satuanLambang = self::satuanLambang($nilai);
        $banyak = count($nilai);

        $jarakBaris = min(34.0, (Kanvas::TINGGI - 44 - $atas) / $banyak);
        $y0 = $atas + $jarakBaris / 2;

        foreach ($nilai as $i => $n) {
            $y = $y0 + $i * $jarakBaris;

            $kanvas->teksPx([124, $y + 5], $kategori[$i], ['anchor' => 'end', 'ukuran' => 12]);

            for ($k = 0; $k < (int) round($n / $satuanLambang); $k++) {
                $kanvas->lingkaran([144 + $k * 24, $y], 9, $isi, $garis, 1.8);
            }
        }

        $kanvas
            ->garis([132, $atas - 6], [132, $y0 + ($banyak - 1) * $jarakBaris + $jarakBaris / 2], '#d6dade', 1.5)
            ->lingkaran([152, Kanvas::TINGGI - 24], 9, $isi, $garis, 1.8)
            ->teksPx([168, Kanvas::TINGGI - 19], '= '.Angka::dengan($satuanLambang, $satuan), [
                'anchor' => 'start', 'ukuran' => 13,
            ]);

        return $kanvas;
    }

    /**
     * Diagram lingkaran beserta keterangan di sebelahnya. Persentase ditulis
     * di dalam juring yang cukup lebar saja; juring tipis labelnya akan
     * tumpang-tindih, dan nilainya tetap terbaca pada keterangan.
     */
    protected static function gambarLingkaran(Kanvas $kanvas, array $kategori, array $nilai, float $atas): Kanvas
    {
        $jumlah = array_sum($nilai);
        $pusat = [146, ($atas + Kanvas::TINGGI - 20) / 2];
        $jari = min(96.0, (Kanvas::TINGGI - 28 - $atas) / 2);

        $mulai = 0.0;

        foreach ($nilai as $i => $n) {
            [$isiJuring, $garisJuring] = self::PALET[$i % count(self::PALET)];
            $sudut = $jumlah > 0 ? $n / $jumlah * 360 : 0;

            $kanvas->juring($pusat, $jari, $mulai, $mulai + $sudut, $isiJuring, $garisJuring, 2);

            if ($sudut >= 30) {
                $tengah = deg2rad($mulai + $sudut / 2 - 90);

                $kanvas->teksPx([
                    $pusat[0] + cos($tengah) * $jari * 0.62,
                    $pusat[1] + sin($tengah) * $jari * 0.62 + 5,
                ], Angka::id($n / $jumlah * 100, 1).'%', ['ukuran' => 12, 'tebal' => true]);
            }

            $mulai += $sudut;
        }

        $jarakBaris = min(26.0, (Kanvas::TINGGI - 40 - $atas) / count($nilai));
        $y0 = ($atas + Kanvas::TINGGI - 20) / 2 - ($jarakBaris * (count($nilai) - 1)) / 2;

        foreach ($nilai as $i => $n) {
            [$isiJuring, $garisJuring] = self::PALET[$i % count(self::PALET)];
            $y = $y0 + $i * $jarakBaris;

            $kanvas->persegi([268, $y - 7], 14, 14, $isiJuring, $garisJuring, 1.5)
                ->teksPx([290, $y + 5], $kategori[$i].': '.Angka::id($n), [
                    'anchor' => 'start', 'ukuran' => 12,
                ]);
        }

        return $kanvas;
    }

    /** Tabel data dua lajur: nama kategori dan angkanya. */
    protected static function gambarTabel(Kanvas $kanvas, array $kategori, array $nilai, string $satuan, float $atas, string $isi, string $garis): Kanvas
    {
        $banyak = count($nilai);
        $tinggiBaris = min(32.0, (Kanvas::TINGGI - 30 - $atas) / ($banyak + 1));
        $lebar = [206.0, 128.0];
        $x0 = (Kanvas::LEBAR - array_sum($lebar)) / 2;

        $baris = array_merge(
            [['Kategori', $satuan === '' ? 'Nilai' : 'Jumlah ('.$satuan.')']],
            array_map(fn ($k, $n) => [$k, Angka::id($n)], $kategori, $nilai),
        );

        return $kanvas->tabelPx([$x0, $atas], $lebar, $tinggiBaris, $baris, $isi, $garis);
    }
}

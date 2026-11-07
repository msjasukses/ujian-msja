<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template pecahan: membaca, menyenilaikan, membandingkan, dan menghitungnya.
 *
 * Tiga cara menggambarkannya disediakan karena ketiganya mengajarkan hal yang
 * berbeda. Lingkaran dan pita memperlihatkan pecahan sebagai bagian dari satu
 * benda utuh; kumpulan benda memperlihatkannya sebagai bagian dari sekelompok
 * benda. Siswa yang hanya pernah melihat lingkaran sering tertahan begitu
 * soalnya berganti ke bentuk yang lain.
 *
 * Soal yang melibatkan dua pecahan selalu digambar sebagai dua pita sepanjang
 * satu satuan yang ditumpuk. Hanya dengan panjang keseluruhan yang sama dua
 * pecahan berpenyebut berbeda dapat dibandingkan dengan melihat; dua lingkaran
 * berdampingan membuat siswa membandingkan banyak juringnya, bukan luasnya.
 */
class Pecahan extends Template
{
    /** @var array<string, string> */
    public const BENTUK = [
        'lingkaran' => 'Lingkaran',
        'pita' => 'Pita',
        'kumpulan' => 'Kumpulan benda',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'arsir' => 'Bagian yang diarsir',
        'tidak_arsir' => 'Bagian yang tidak diarsir',
        'senilai' => 'Pecahan yang senilai',
        'sederhana' => 'Bentuk paling sederhana',
        'desimal' => 'Diubah ke bentuk desimal',
        'persen' => 'Diubah ke bentuk persen',
        'banding' => 'Membandingkan dua pecahan',
        'jumlah' => 'Menjumlahkan dua pecahan',
        'kurang' => 'Mengurangkan dua pecahan',
    ];

    /** Yang ditanyakan dengan dua pecahan sekaligus. */
    public const BERPASANGAN = ['banding', 'jumlah', 'kurang'];

    protected const IKON = [
        'lingkaran' => '<circle cx="16" cy="16" r="12"/><path d="M16 4 L16 28 M4 16 L28 16"/>',
        'pita' => '<rect x="2" y="11" width="28" height="10"/><path d="M9 11 L9 21 M16 11 L16 21 M23 11 L23 21"/>',
        'kumpulan' => '<circle cx="9" cy="11" r="4"/><circle cx="23" cy="11" r="4"/>'
            .'<circle cx="9" cy="23" r="4"/><circle cx="23" cy="23" r="4"/>',
    ];

    /** Banyak benda per baris pada gambar kumpulan benda. */
    protected const SEBARIS = 6;

    public static function kode(): string
    {
        return 'pecahan';
    }

    public static function nama(): string
    {
        return 'Pecahan';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Pecahan dari gambar yang diarsir, pecahan senilai, bentuk paling '
            .'sederhana, desimal dan persen, serta membandingkan dan menghitung dua pecahan.';
    }

    public static function ikon(): string
    {
        return 'bi-pie-chart';
    }

    public static function kataKunci(): array
    {
        return ['pecahan', 'pembilang', 'penyebut', 'arsir', 'bagian', 'pecahan senilai',
            'menyederhanakan pecahan', 'membandingkan pecahan', 'menjumlahkan pecahan',
            'mengurangkan pecahan', 'desimal', 'persen', 'kpk', 'fpb', 'sd', 'smp'];
    }

    public static function kolom(): array
    {
        $tunggal = array_values(array_diff(array_keys(self::TANYA), self::BERPASANGAN));

        return [
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'arsir',
            ],
            [
                'nama' => 'bentuk',
                'label' => 'Bentuk gambar',
                'jenis' => 'pilihan-ikon',
                'pilihan' => self::BENTUK,
                'ikon' => self::IKON,
                'awal' => 'lingkaran',
                // Soal dua pecahan selalu digambar sebagai pita bertumpuk,
                // jadi pilihan bentuknya tidak berlaku di sana.
                'tampil' => ['tanya' => $tunggal],
            ],
            [
                'nama' => 'penyebut',
                'label' => 'Banyak bagian (penyebut)',
                'jenis' => 'angka',
                'awal' => 4,
                'min' => 2,
                'max' => 12,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'pembilang',
                'label' => 'Bagian yang diarsir (pembilang)',
                'jenis' => 'angka',
                // Nilai bawaan harus sah untuk SEMUA jenis pertanyaan
                // sekaligus: 2/4 dapat disederhanakan dan desimalnya
                // berhenti, sedangkan 3/4 akan ditolak soal menyederhanakan.
                'awal' => 2,
                'min' => 1,
                'max' => 12,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'penyebut_dua',
                'label' => 'Penyebut pecahan kedua',
                'jenis' => 'angka',
                'awal' => 6,
                'min' => 2,
                'max' => 12,
                'langkah' => 1,
                'lebar' => 6,
                'tampil' => ['tanya' => self::BERPASANGAN],
            ],
            [
                'nama' => 'pembilang_dua',
                'label' => 'Pembilang pecahan kedua',
                'jenis' => 'angka',
                'awal' => 1,
                'min' => 1,
                'max' => 12,
                'langkah' => 1,
                'lebar' => 6,
                'tampil' => ['tanya' => self::BERPASANGAN],
            ],
            self::kolomWarna(),
        ];
    }

    public static function periksa(array $isi): array
    {
        $tanya = $isi['tanya'] ?? 'arsir';
        $p = (int) ($isi['pembilang'] ?? 0);
        $q = (int) ($isi['penyebut'] ?? 0);

        if ($p >= $q) {
            return ['pembilang' => 'Bagian yang diarsir harus lebih sedikit daripada banyak bagiannya.'];
        }

        if ($tanya === 'sederhana' && self::fpb($p, $q) === 1) {
            return ['pembilang' => 'Pecahan '.$p.'/'.$q.' sudah paling sederhana, jadi tidak ada '
                .'yang perlu disederhanakan. Pilih pembilang dan penyebut yang punya faktor '
                .'persekutuan, misalnya 4/6 atau 6/9.'];
        }

        if (in_array($tanya, ['desimal', 'persen'], true) && ! self::desimalBerakhir($p, $q)) {
            return ['penyebut' => 'Pecahan '.$p.'/'.$q.' menghasilkan desimal yang tidak pernah '
                .'berhenti ('.$p.' : '.$q.' = '.rtrim(number_format($p / $q, 4, ',', ''), '0')
                .'...), sehingga jawabannya tidak bisa ditulis tepat. Pakai penyebut 2, 4, 5, 8, '
                .'atau 10.'];
        }

        if (! in_array($tanya, self::BERPASANGAN, true)) {
            return [];
        }

        $p2 = (int) ($isi['pembilang_dua'] ?? 0);
        $q2 = (int) ($isi['penyebut_dua'] ?? 0);

        if ($p2 >= $q2) {
            return ['pembilang_dua' => 'Pembilang pecahan kedua harus lebih kecil daripada penyebutnya.'];
        }

        // Dua pecahan yang tertulis persis sama tidak ada yang dibandingkan,
        // dan pilihan jawabannya pun berkembar. Pecahan senilai yang
        // tertulis berbeda — 2/4 dengan 1/2 — justru soal yang bagus,
        // jadi yang ditolak hanya yang tulisannya sama persis.
        if ($tanya === 'banding' && $p === $p2 && $q === $q2) {
            return ['pembilang_dua' => 'Kedua pecahannya tertulis sama persis, jadi tidak ada '
                .'yang dibandingkan. Ubah salah satunya — misalnya '.$p.'/'.$q.' dengan '
                .($p * 2).'/'.($q * 2).' untuk menguji pecahan senilai.'];
        }

        if ($tanya === 'jumlah' && $p / $q + $p2 / $q2 > 1) {
            return ['pembilang_dua' => 'Jumlah kedua pecahan itu lebih dari satu utuh, sehingga '
                .'tidak dapat digambarkan sebagai bagian dari satu pita. Perkecil salah satu '
                .'pecahannya.'];
        }

        if ($tanya === 'kurang' && $p / $q <= $p2 / $q2) {
            return ['pembilang_dua' => 'Pecahan pertama harus lebih besar daripada pecahan kedua '
                .'supaya hasil pengurangannya tidak negatif.'];
        }

        return [];
    }

    // =====================================================================
    // Hitungan pembantu
    // =====================================================================

    /** Faktor persekutuan terbesar. */
    public static function fpb(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, abs($a));
    }

    /** Kelipatan persekutuan terkecil. */
    public static function kpk(int $a, int $b): int
    {
        return intdiv($a, self::fpb($a, $b)) * $b;
    }

    /**
     * Apakah p/q berujung pada desimal yang berhenti.
     *
     * Setelah disederhanakan, penyebutnya harus habis dibagi 2 dan 5 saja —
     * sisa faktor lain membuat desimalnya berulang tanpa henti.
     */
    public static function desimalBerakhir(int $p, int $q): bool
    {
        $sisa = intdiv($q, self::fpb($p, $q));

        foreach ([2, 5] as $faktor) {
            while ($sisa % $faktor === 0) {
                $sisa = intdiv($sisa, $faktor);
            }
        }

        return $sisa === 1;
    }

    /** Menuliskan pecahan apa adanya. */
    public static function tulis(int $p, int $q): string
    {
        return $p.'/'.$q;
    }

    /**
     * Menuliskan hasil hitungan: berpenyebut satu ditulis sebagai bilangan
     * bulat, sebab "1/1" bukan jawaban yang pernah ditulis siapa pun.
     */
    public static function tulisHasil(int $p, int $q): string
    {
        return $q === 1 ? (string) $p : $p.'/'.$q;
    }

    /** Menyederhanakan sepasang pembilang-penyebut. */
    public static function sederhanakan(int $p, int $q): array
    {
        $g = self::fpb($p, $q);

        return [intdiv($p, $g), intdiv($q, $g)];
    }

    protected function susun(array $isi): array
    {
        $q = (int) $isi['penyebut'];
        $p = (int) $isi['pembilang'];
        $tanya = $isi['tanya'] ?? 'arsir';
        $warna = $isi['warna'] ?? 'biru';
        [$warnaIsi, $garis] = self::WARNA[$warna] ?? self::WARNA['biru'];

        if (in_array($tanya, self::BERPASANGAN, true)) {
            $q2 = (int) ($isi['penyebut_dua'] ?? 6);
            $p2 = (int) ($isi['pembilang_dua'] ?? 1);

            return match ($tanya) {
                'jumlah' => $this->hitungDua($p, $q, $p2, $q2, true, $warnaIsi, $garis),
                'kurang' => $this->hitungDua($p, $q, $p2, $q2, false, $warnaIsi, $garis),
                default => $this->banding($p, $q, $p2, $q2, $warnaIsi, $garis),
            };
        }

        $bentuk = $isi['bentuk'] ?? 'lingkaran';

        return match ($tanya) {
            'senilai' => $this->senilai($bentuk, $p, $q, $warna),
            'sederhana' => $this->sederhana($bentuk, $p, $q, $warna),
            'desimal' => $this->desimal($bentuk, $p, $q, $warna),
            'persen' => $this->persen($bentuk, $p, $q, $warna),
            default => $this->arsir($bentuk, $p, $q, $tanya, $warna),
        };
    }

    // =====================================================================
    // Satu pecahan
    // =====================================================================

    protected function arsir(string $bentuk, int $diarsir, int $q, string $tanya, string $warna): array
    {
        // Yang ditanyakan boleh bagian yang tidak diarsir; gambarnya tetap
        // sama, hanya pembilang jawabannya yang berganti.
        $p = $tanya === 'arsir' ? $diarsir : $q - $diarsir;
        $sebutan = self::sebutan($bentuk);
        $bagian = $tanya === 'arsir' ? 'diarsir' : 'tidak diarsir';

        return [
            'judul' => 'Pecahan '.$p.'/'.$q,
            'teks' => "Perhatikan gambar {$sebutan} berikut. "
                ."Pecahan yang menunjukkan bagian yang {$bagian} adalah ...",
            'pernyataan' => "Perhatikan gambar {$sebutan} berikut. "
                ."Bagian yang {$bagian} menunjukkan pecahan %s.",
            'svg' => self::svg($bentuk, $q, $diarsir, $warna),
            'keterangan' => ucfirst($sebutan)." dibagi {$q} bagian sama besar, {$diarsir} bagian diarsir.",
            'pilihan' => $this->pilihan($p, $q),
            'kata_kunci' => $p.'/'.$q,
            'pembahasan' => "Seluruh gambar dibagi {$q} bagian sama besar (penyebut), "
                ."dan {$p} bagian di antaranya {$bagian} (pembilang). Pecahannya {$p}/{$q}.",
        ];
    }

    protected function senilai(string $bentuk, int $p, int $q, string $warna): array
    {
        $benar = self::tulis($p * 2, $q * 2);

        return [
            'judul' => $p.'/'.$q.' senilai '.$benar,
            'teks' => 'Perhatikan gambar '.self::sebutan($bentuk).' berikut. Pecahan yang '
                .'senilai dengan bagian yang diarsir adalah ...',
            'pernyataan' => 'Pada gambar berikut, pecahan yang senilai dengan bagian yang '
                .'diarsir adalah %s.',
            'svg' => self::svg($bentuk, $q, $p, $warna),
            'keterangan' => ucfirst(self::sebutan($bentuk)).' dibagi '.$q.' bagian sama besar, '
                .$p.' bagian diarsir.',
            // Tiap pengecoh satu kekeliruan yang jelas: menambah angka yang
            // sama ke pembilang dan penyebut, serta mengalikan salah satunya
            // saja.
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                [
                    $benar,
                    self::tulis($p + 1, $q + 1),
                    self::tulis($p * 2, $q),
                    self::tulis($p, $q * 2),
                ],
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Bagian yang diarsir menunjukkan pecahan '.$p.'/'.$q.'.<br>'
                .'Pecahan senilai diperoleh dengan mengalikan pembilang <em>dan</em> penyebut '
                .'dengan bilangan yang sama: '.$p.'/'.$q.' = ('.$p.'×2)/('.$q.'×2) = <strong>'
                .$benar.'</strong>.<br>Menambah bilangan yang sama ke pembilang dan penyebut '
                .'tidak menghasilkan pecahan senilai.',
        ];
    }

    protected function sederhana(string $bentuk, int $p, int $q, string $warna): array
    {
        $g = self::fpb($p, $q);
        [$a, $b] = self::sederhanakan($p, $q);
        $benar = self::tulis($a, $b);

        // Pengecohnya: hanya pembilang yang dibagi, hanya penyebut yang
        // dibagi, dan FPB-nya dikurangkan alih-alih dibagikan.
        $kandidat = [
            self::tulis(intdiv($p, $g), $q),
            self::tulis($p, intdiv($q, $g)),
            $p - $g >= 1 ? self::tulis($p - $g, $q - $g) : null,
            self::tulis($b, $a),
            self::tulis($p, $q),
        ];

        $pengecoh = [];

        foreach (array_filter($kandidat) as $teks) {
            if ($teks !== $benar && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        return [
            'judul' => $p.'/'.$q.' = '.$benar,
            'teks' => 'Perhatikan gambar '.self::sebutan($bentuk).' berikut. Bentuk paling '
                .'sederhana dari pecahan yang diarsir adalah ...',
            'pernyataan' => 'Pada gambar berikut, bentuk paling sederhana dari pecahan yang '
                .'diarsir adalah %s.',
            'svg' => self::svg($bentuk, $q, $p, $warna),
            'keterangan' => ucfirst(self::sebutan($bentuk)).' dibagi '.$q.' bagian sama besar, '
                .$p.' bagian diarsir.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Bagian yang diarsir menunjukkan pecahan '.$p.'/'.$q.'.<br>'
                .'FPB dari '.$p.' dan '.$q.' adalah '.$g.'. Pembilang <em>dan</em> penyebut '
                .'sama-sama dibagi '.$g.': ('.$p.':'.$g.')/('.$q.':'.$g.') = <strong>'
                .$benar.'</strong>.',
        ];
    }

    protected function desimal(string $bentuk, int $p, int $q, string $warna): array
    {
        $benar = $p / $q;

        // Pengecoh yang khas: pembilang dan penyebut dibaca sebagai angka di
        // kiri dan kanan koma, pembagiannya terbalik, dan letak komanya
        // bergeser satu tempat.
        $kandidat = [
            (float) ($p.'.'.$q),
            $q / $p,
            $benar * 10,
            $benar / 10,
            $benar + 0.1,
        ];

        $pengecoh = self::saring($benar, $kandidat, 4);

        return [
            'judul' => $p.'/'.$q.' = '.Angka::ringkas($benar, 4),
            'teks' => 'Perhatikan gambar '.self::sebutan($bentuk).' berikut. Pecahan yang '
                .'diarsir jika ditulis dalam bentuk desimal menjadi ...',
            'pernyataan' => 'Pada gambar berikut, pecahan yang diarsir sama dengan %s '
                .'dalam bentuk desimal.',
            'svg' => self::svg($bentuk, $q, $p, $warna),
            'keterangan' => ucfirst(self::sebutan($bentuk)).' dibagi '.$q.' bagian sama besar, '
                .$p.' bagian diarsir.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n, 4), 'urut' => $n],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar, 4),
            'pembahasan' => 'Bagian yang diarsir menunjukkan pecahan '.$p.'/'.$q.'.<br>'
                .'Pecahan berarti pembilang dibagi penyebut: '.$p.' : '.$q.' = <strong>'
                .Angka::ringkas($benar, 4).'</strong>.<br>Jadi '.$p.'/'.$q.' bukan ditulis '
                .$p.','.$q.'.',
        ];
    }

    protected function persen(string $bentuk, int $p, int $q, string $warna): array
    {
        $benar = $p / $q * 100;

        // Pengecoh: bagian yang tidak diarsir, pembagian terbalik, dan
        // pembilang saja yang dianggap persennya.
        $pengecoh = self::saring($benar, [
            ($q - $p) / $q * 100,
            $q / $p * 100,
            (float) $p,
            $p / $q,
            $benar / 10,
        ], 2);

        return [
            'judul' => $p.'/'.$q.' = '.Angka::ringkas($benar).'%',
            'teks' => 'Perhatikan gambar '.self::sebutan($bentuk).' berikut. Bagian yang '
                .'diarsir sama dengan ...',
            'pernyataan' => 'Pada gambar berikut, bagian yang diarsir sama dengan %s.',
            'svg' => self::svg($bentuk, $q, $p, $warna),
            'keterangan' => ucfirst(self::sebutan($bentuk)).' dibagi '.$q.' bagian sama besar, '
                .$p.' bagian diarsir.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).'%', 'urut' => $n],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar).'%',
            'pembahasan' => 'Bagian yang diarsir menunjukkan pecahan '.$p.'/'.$q.'.<br>'
                .'Persen berarti per seratus, jadi pecahannya dikalikan 100%: '
                .$p.'/'.$q.' × 100% = <strong>'.Angka::ringkas($benar).'%</strong>.',
        ];
    }

    // =====================================================================
    // Dua pecahan
    // =====================================================================

    protected function banding(int $p, int $q, int $p2, int $q2, string $warnaIsi, string $garis): array
    {
        $a = self::tulis($p, $q);
        $b = self::tulis($p2, $q2);
        $kpk = self::kpk($q, $q2);

        $benar = match (true) {
            $p / $q > $p2 / $q2 => $a.' lebih besar daripada '.$b,
            $p / $q < $p2 / $q2 => $b.' lebih besar daripada '.$a,
            default => $a.' sama besar dengan '.$b,
        };

        $semua = [
            $a.' lebih besar daripada '.$b,
            $b.' lebih besar daripada '.$a,
            $a.' sama besar dengan '.$b,
            // Pengecoh keempat berupa anggapan yang lazim: pecahan
            // berpenyebut berbeda dikira tidak dapat dibandingkan.
            $q === $q2
                ? 'Tidak dapat ditentukan karena pembilangnya harus disamakan dahulu'
                : 'Tidak dapat ditentukan karena penyebutnya berbeda',
        ];

        return [
            'judul' => $a.' vs '.$b,
            'teks' => 'Perhatikan dua pita berikut. Pernyataan yang benar tentang pecahan '
                .$a.' dan '.$b.' adalah ...',
            'pernyataan' => 'Tentang pecahan '.$a.' dan '.$b.' pada gambar berikut: %s.',
            'svg' => self::svgDua($p, $q, $p2, $q2, $warnaIsi, $garis),
            'keterangan' => 'Dua pita sama panjang; pita pertama '.$a.' diarsir, pita kedua '
                .$b.' diarsir.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t === $benar ? 0 : 1 + array_search($t, $semua, true)],
                array_merge([$benar], array_values(array_diff($semua, [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Dua pecahan berpenyebut berbeda dibandingkan setelah penyebutnya '
                .'disamakan. KPK dari '.$q.' dan '.$q2.' adalah '.$kpk.'.<br>'
                .$a.' = '.self::tulis($p * intdiv($kpk, $q), $kpk).' dan '
                .$b.' = '.self::tulis($p2 * intdiv($kpk, $q2), $kpk).'.<br>'
                .'Karena pembilangnya '.($p * intdiv($kpk, $q)).' dan '
                .($p2 * intdiv($kpk, $q2)).', maka <strong>'.$benar.'</strong>.',
        ];
    }

    protected function hitungDua(int $p, int $q, int $p2, int $q2, bool $tambah, string $warnaIsi, string $garis): array
    {
        $a = self::tulis($p, $q);
        $b = self::tulis($p2, $q2);
        $kpk = self::kpk($q, $q2);

        $pa = $p * intdiv($kpk, $q);
        $pb = $p2 * intdiv($kpk, $q2);
        $hasil = $tambah ? $pa + $pb : $pa - $pb;

        [$ha, $hb] = self::sederhanakan($hasil, $kpk);
        $benar = self::tulisHasil($ha, $hb);

        $tanda = $tambah ? '+' : '−';
        $kerja = $tambah ? 'menjumlahkan' : 'mengurangkan';

        // Pengecoh pertama kekeliruan paling sering: pembilang dengan
        // pembilang dan penyebut dengan penyebut. Sisanya: penyebut tidak
        // disamakan, dan hasilnya lupa disederhanakan.
        $kandidat = [
            self::tulisHasil(
                $tambah ? $p + $p2 : max(1, $p - $p2),
                $tambah ? $q + $q2 : max(1, abs($q - $q2)),
            ),
            self::tulisHasil($tambah ? $p + $p2 : max(1, $p - $p2), $q),
            self::tulisHasil($hasil, $kpk),
            // Lupa menghitung salah satu suku: hanya satu pecahan yang
            // disamakan penyebutnya lalu ditulis sebagai jawaban.
            self::tulisHasil($pa, $kpk),
            self::tulisHasil($pb, $kpk),
            self::tulisHasil($tambah ? $pa + $pb : max(1, $pa - $pb), $kpk * 2),
            self::tulisHasil($hb, $ha),
        ];

        $pengecoh = [];

        foreach ($kandidat as $teks) {
            if ($teks !== $benar && ! in_array($teks, $pengecoh, true)) {
                $pengecoh[] = $teks;
            }
        }

        $penyederhanaan = $hasil === $ha
            ? ''
            : '<br>Disederhanakan dengan FPB '.self::fpb($hasil, $kpk).': '
                .self::tulisHasil($hasil, $kpk).' = <strong>'.$benar.'</strong>.';

        return [
            'judul' => $a.' '.$tanda.' '.$b.' = '.$benar,
            'teks' => 'Perhatikan dua pita berikut. Hasil '.$a.' '.$tanda.' '.$b
                .' dalam bentuk paling sederhana adalah ...',
            'pernyataan' => 'Hasil '.$a.' '.$tanda.' '.$b
                .' dalam bentuk paling sederhana adalah %s.',
            'svg' => self::svgDua($p, $q, $p2, $q2, $warnaIsi, $garis),
            'keterangan' => 'Dua pita sama panjang; pita pertama '.$a.' diarsir, pita kedua '
                .$b.' diarsir.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Pecahan hanya dapat '.$kerja.' setelah penyebutnya disamakan — '
                .'pembilang dengan pembilang dan penyebut dengan penyebut tidak boleh langsung '
                .$kerja.'.<br>KPK dari '.$q.' dan '.$q2.' adalah '.$kpk.', sehingga '
                .$a.' = '.self::tulis($pa, $kpk).' dan '.$b.' = '.self::tulis($pb, $kpk).'.<br>'
                .self::tulis($pa, $kpk).' '.$tanda.' '.self::tulis($pb, $kpk).' = '
                .self::tulis($hasil, $kpk).'.'.$penyederhanaan
                .($penyederhanaan === '' ? '<br>Hasilnya <strong>'.$benar.'</strong>.' : ''),
        ];
    }

    /** Sebutan bentuk gambarnya di dalam kalimat soal. */
    protected static function sebutan(string $bentuk): string
    {
        return $bentuk === 'kumpulan' ? 'kumpulan benda' : mb_strtolower(self::BENTUK[$bentuk] ?? 'lingkaran');
    }

    /**
     * Pengecoh pecahan: kurang atau lebih satu bagian — keliru membilang —
     * serta pecahan terbalik, kekeliruan yang paling sering muncul karena
     * pembilang dan penyebut tertukar perannya.
     *
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function pilihan(int $p, int $q): array
    {
        $kandidat = [
            [$p, $q],                                   // kunci, selalu paling depan
            [$p - 1, $q],
            [$q, $p],                                   // terbalik
            $p + 1 < $q ? [$p + 1, $q] : null,          // "4/4" bukan pengecoh, itu satu utuh
            [$q - $p, $q],                              // bagian yang sebaliknya
            [$p - 2, $q],
            [$p, $q - $p],
            // Cadangan untuk pecahan sependek 1/2, yang pengecoh di atas
            // banyak berkembar atau jatuh ke bilangan tak sah.
            [$p, $q + 1],
            [$p + 1, $q + 1],
        ];

        $dipakai = [];
        $pilihan = [];

        foreach (array_filter($kandidat) as [$a, $b]) {
            $teks = $a.'/'.$b;

            if ($a < 1 || $b < 1 || isset($dipakai[$teks])) {
                continue;
            }

            $dipakai[$teks] = true;
            $pilihan[] = ['teks' => $teks, 'urut' => $a / $b];
        }

        return $pilihan;
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $bentuk, int $penyebut, int $diarsir, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['biru'];

        return match ($bentuk) {
            'pita' => self::pita($penyebut, $diarsir, $isi, $garis),
            'kumpulan' => self::kumpulan($penyebut, $diarsir, $isi, $garis),
            default => self::lingkaran($penyebut, $diarsir, $isi, $garis),
        };
    }

    protected static function lingkaran(int $penyebut, int $diarsir, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kanvas->pas([[0, 0], [2, 2]], 40);

        $sudut = 360 / $penyebut;

        for ($i = 0; $i < $penyebut; $i++) {
            $kanvas->juring([1, 1], 1, $i * $sudut, ($i + 1) * $sudut, $i < $diarsir ? $isi : '#ffffff', $garis);
        }

        return $kanvas->render();
    }

    protected static function pita(int $penyebut, int $diarsir, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        // Pita dibuat selalu selebar satu satuan penuh supaya lebar tiap
        // bagian mengecil seiring bertambahnya pembagi, bukan pitanya yang
        // memanjang keluar kanvas.
        $kanvas->pas([[0, 0], [$penyebut, $penyebut / 3]], 50);

        for ($i = 0; $i < $penyebut; $i++) {
            $kanvas->persegi([$i, 0], 1, $penyebut / 3, $i < $diarsir ? $isi : '#ffffff', $garis);
        }

        return $kanvas->render();
    }

    protected static function kumpulan(int $penyebut, int $diarsir, string $isi, string $garis): string
    {
        $kolom = min(self::SEBARIS, $penyebut);
        $baris = (int) ceil($penyebut / $kolom);

        $kanvas = new Kanvas;
        $kanvas->pas([[0, 0], [$kolom * 2, $baris * 2]], 40);

        for ($i = 0; $i < $penyebut; $i++) {
            $kanvas->lingkaran(
                [($i % $kolom) * 2 + 1, intdiv($i, $kolom) * 2 + 1],
                0.8,
                $i < $diarsir ? $isi : '#ffffff',
                $garis,
            );
        }

        return $kanvas->render();
    }

    /**
     * Dua pita sama panjang yang ditumpuk.
     *
     * Panjang keseluruhannya sengaja dibuat sama persis: hanya dengan begitu
     * dua pecahan berpenyebut berbeda dapat dibandingkan dari panjang bagian
     * yang diarsir, bukan dari banyak kotaknya.
     */
    public static function svgDua(int $p, int $q, int $p2, int $q2, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kiri = 70.0;
        $lebar = 340.0;
        $tinggi = 52.0;

        $kanvas->teksPx([240, 36], 'Dua pita yang sama panjang', ['ukuran' => 15, 'tebal' => true]);

        foreach ([[$p, $q, 78.0], [$p2, $q2, 168.0]] as [$a, $b, $y]) {
            $lebarBagian = $lebar / $b;

            for ($i = 0; $i < $b; $i++) {
                $kanvas->tambah('<rect x="'.round($kiri + $i * $lebarBagian, 1).'" y="'.$y
                    .'" width="'.round($lebarBagian, 1).'" height="'.$tinggi.'" fill="'
                    .($i < $a ? $isi : '#ffffff').'" stroke="'.$garis.'" stroke-width="1.8"/>');
            }

            $kanvas->teksPx([$kiri - 14, $y + $tinggi / 2 + 6], self::tulis($a, $b), [
                'anchor' => 'end', 'ukuran' => 15, 'tebal' => true,
            ]);
        }

        // Garis tegak di kedua ujung, penegas bahwa panjang awalnya sama.
        foreach ([$kiri, $kiri + $lebar] as $x) {
            $kanvas->garis([$x, 68], [$x, 236], '#9aa1a9', 1.2, true);
        }

        return $kanvas
            ->teksPx([240, 262], 'kedua pita berawal dan berakhir pada garis yang sama', [
                'ukuran' => 11, 'warna' => '#6b7280',
            ])
            ->render();
    }
}

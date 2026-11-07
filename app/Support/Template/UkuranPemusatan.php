<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template ukuran pemusatan data: rata-rata, median, modus, dan jangkauan.
 *
 * Guru cukup mengetik datanya apa adanya — boleh berantakan, tidak perlu
 * diurutkan — lalu template yang menyusun tabel frekuensinya dan menghitung
 * kuncinya. Karena keduanya berasal dari daftar yang sama, gambar dan kunci
 * tidak mungkin berselisih.
 *
 * Pengecohnya sengaja berupa ukuran pemusatan yang lain: rata-rata dijawab
 * median, modus dijawab nilai terbesar, median dijawab nilai tengah dari
 * daftar yang belum diurutkan. Semuanya kekeliruan yang nyata, sehingga
 * sebaran jawaban siswa langsung menunjukkan salah paham mana yang terjadi.
 */
class UkuranPemusatan extends Template
{
    /** @var array<string, string> */
    public const BENTUK = [
        'tabel' => 'Tabel frekuensi',
        'titik' => 'Diagram titik',
        'daftar' => 'Daftar data apa adanya',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'mean' => 'Rata-rata (mean)',
        'median' => 'Median (nilai tengah)',
        'modus' => 'Modus (paling sering muncul)',
        'jangkauan' => 'Jangkauan (data terbesar − terkecil)',
    ];

    /** Batas banyak data; lebih dari ini tabelnya tidak lagi terbaca. */
    protected const MAKS = 30;

    /** Batas banyak nilai berbeda, dan batas frekuensi tertinggi. */
    protected const MAKS_NILAI = 9;

    protected const MAKS_FREKUENSI = 9;

    public static function kode(): string
    {
        return 'ukuran-pemusatan';
    }

    public static function nama(): string
    {
        return 'Rata-rata, Median & Modus';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Ketik datanya, tabel frekuensi atau diagram titiknya dibuatkan. '
            .'Tanya rata-rata, median, modus, atau jangkauannya.';
    }

    public static function ikon(): string
    {
        return 'bi-calculator';
    }

    public static function kataKunci(): array
    {
        return ['statistika', 'rata-rata', 'mean', 'median', 'modus', 'jangkauan',
            'ukuran pemusatan', 'data', 'tabel frekuensi', 'diagram titik', 'nilai tengah'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'data',
                'label' => 'Data',
                'jenis' => 'teks',
                'awal' => '7, 8, 6, 7, 9, 7, 8, 6, 7, 8',
                'bantuan' => 'Dipisah koma, tidak perlu diurutkan. Paling banyak '.self::MAKS.' data. Untuk angka desimal, pisahkan dengan titik koma: 7,5; 8; 6,5.',
            ],
            [
                'nama' => 'bentuk',
                'label' => 'Bentuk penyajian',
                'jenis' => 'pilihan',
                'pilihan' => self::BENTUK,
                'awal' => 'tabel',
                'lebar' => 6,
            ],
            self::kolomTanya(self::TANYA, 'mean', 6),
            [
                'nama' => 'satuan',
                'label' => 'Satuan data',
                'jenis' => 'teks',
                'awal' => '',
                'lebar' => 6,
            ],
            self::kolomWarna(),
        ];
    }

    public static function periksa(array $isi): array
    {
        $teks = self::daftar((string) ($isi['data'] ?? ''));

        if (count($teks) < 3) {
            return ['data' => 'Isi minimal tiga data, dipisah koma.'];
        }

        if (count($teks) > self::MAKS) {
            return ['data' => 'Paling banyak '.self::MAKS.' data agar gambarnya tetap terbaca.'];
        }

        foreach ($teks as $n) {
            if (! is_numeric(str_replace(',', '.', $n))) {
                return ['data' => "\"{$n}\" bukan angka."];
            }
        }

        $frekuensi = self::frekuensi(self::angka($teks));

        if (count($frekuensi) > self::MAKS_NILAI) {
            return ['data' => 'Ada '.count($frekuensi).' nilai berbeda; paling banyak '
                .self::MAKS_NILAI.' agar tabel dan diagramnya tetap terbaca.'];
        }

        if (max($frekuensi) > self::MAKS_FREKUENSI) {
            return ['data' => 'Ada nilai yang muncul '.max($frekuensi).' kali; paling banyak '
                .self::MAKS_FREKUENSI.' kali agar diagram titiknya muat.'];
        }

        if (($isi['tanya'] ?? '') !== 'modus') {
            return [];
        }

        // Modus hanya punya satu jawaban bila ada tepat satu nilai yang paling
        // sering muncul. Data tanpa modus, atau bermodus dua, tidak dapat
        // dijadikan butir pilihan ganda berkunci tunggal.
        $tertinggi = max($frekuensi);

        if ($tertinggi < 2) {
            return ['data' => 'Semua nilai muncul sekali saja, jadi datanya tidak punya modus.'];
        }

        if (count(array_keys($frekuensi, $tertinggi, true)) > 1) {
            return ['data' => 'Ada lebih dari satu nilai yang sama-sama paling sering muncul, '
                .'jadi modusnya tidak tunggal.'];
        }

        return [];
    }

    /**
     * Banyak kemunculan tiap nilai, terurut menaik menurut nilainya.
     *
     * Kuncinya sengaja berupa teks: PHP memangkas kunci array bertipe pecahan
     * menjadi bilangan bulat, sehingga data 7,5 dan 7 akan tergabung.
     *
     * @param  array<int, float>  $data
     * @return array<string, int>
     */
    public static function frekuensi(array $data): array
    {
        $hitung = [];

        foreach ($data as $n) {
            $kunci = (string) $n;
            $hitung[$kunci] = ($hitung[$kunci] ?? 0) + 1;
        }

        uksort($hitung, fn ($a, $b) => (float) $a <=> (float) $b);

        return $hitung;
    }

    /** Median: nilai tengah data yang sudah diurutkan. */
    public static function nilaiTengah(array $data): float
    {
        sort($data);
        $n = count($data);
        $tengah = intdiv($n, 2);

        return $n % 2 === 1 ? $data[$tengah] : ($data[$tengah - 1] + $data[$tengah]) / 2;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $data = self::angka(self::daftar((string) $isi['data']));
        $satuan = e(trim((string) ($isi['satuan'] ?? '')));
        $bentuk = $isi['bentuk'] ?? 'tabel';

        $banyak = count($data);
        $jumlah = array_sum($data);
        $frekuensi = self::frekuensi($data);

        $mean = round($jumlah / $banyak, 3);
        $median = self::nilaiTengah($data);
        $jangkauan = max($data) - min($data);
        $modus = (float) array_search(max($frekuensi), $frekuensi, true);

        $bahan = [
            'svg' => self::svg($bentuk, $data, $frekuensi, $warnaIsi, $garis),
            'keterangan' => 'Data '.$banyak.' nilai, disajikan sebagai '
                .mb_strtolower(self::BENTUK[$bentuk] ?? 'tabel').'.',
        ];

        $awalan = 'Perhatikan '.mb_strtolower(self::BENTUK[$bentuk] ?? 'tabel').' berikut.';

        return $bahan + match ($isi['tanya'] ?? 'mean') {
            'median' => $this->median($awalan, $data, $mean, $median, $modus, $jangkauan, $satuan),
            'modus' => $this->modus($awalan, $frekuensi, $data, $mean, $median, $modus, $satuan),
            'jangkauan' => $this->jangkauan($awalan, $data, $mean, $median, $jangkauan, $satuan),
            default => $this->mean($awalan, $banyak, $jumlah, $frekuensi, $mean, $median, $modus, $jangkauan, $satuan),
        };
    }

    protected function mean(string $awalan, int $banyak, float $jumlah, array $frekuensi, float $mean, float $median, float $modus, float $jangkauan, string $satuan): array
    {
        return [
            'judul' => 'Rata-rata '.Angka::ringkas($mean),
            'teks' => $awalan.' Berapakah rata-rata (mean) data tersebut?',
            'pernyataan' => $awalan.' Rata-rata data tersebut adalah %s.',
            'pilihan' => self::pilihanNilai($mean, [
                // Pengecoh terpentingnya membagi dengan banyak nilai yang
                // berbeda, bukan dengan banyak datanya — kekeliruan yang
                // muncul persis karena melihat tabel frekuensi.
                round($jumlah / count($frekuensi), 3), $median, $modus, $jangkauan,
            ], $satuan),
            'kata_kunci' => Angka::ringkas($mean),
            'pembahasan' => 'Rata-rata = jumlah seluruh data ÷ banyak data.<br>Jumlahnya '
                .Angka::id($jumlah).' dan datanya ada '.$banyak.' buah, jadi rata-rata = '
                .Angka::id($jumlah).' ÷ '.$banyak.' = '.Angka::ringkas($mean).'.<br>'
                .'Perhatikan pembaginya: banyak <em>data</em>, bukan banyak nilai yang berbeda.',
        ];
    }

    /** Median: nilai tengah setelah data diurutkan lebih dahulu. */
    protected function median(string $awalan, array $data, float $mean, float $median, float $modus, float $jangkauan, string $satuan): array
    {
        $urut = $data;
        sort($urut);
        $n = count($urut);
        $tengah = intdiv($n, 2);

        $langkah = $n % 2 === 1
            ? 'Banyak datanya ganjil ('.$n.'), jadi mediannya satu nilai yang tepat di tengah, '
                .'yaitu data ke-'.($tengah + 1).' = '.Angka::id($urut[$tengah]).'.'
            : 'Banyak datanya genap ('.$n.'), jadi mediannya rata-rata dua nilai tengah, yaitu '
                .'data ke-'.$tengah.' dan ke-'.($tengah + 1).': ('.Angka::id($urut[$tengah - 1])
                .' + '.Angka::id($urut[$tengah]).') ÷ 2 = '.Angka::ringkas($median).'.';

        return [
            'judul' => 'Median '.Angka::ringkas($median),
            'teks' => $awalan.' Berapakah median (nilai tengah) data tersebut?',
            'pernyataan' => $awalan.' Median data tersebut adalah %s.',
            'pilihan' => self::pilihanNilai($median, [
                // Pengecoh terpentingnya nilai tengah dari daftar yang belum
                // diurutkan — kekeliruan terbesar pada soal median.
                $data[intdiv(count($data), 2)], $mean, $modus, $jangkauan,
            ], $satuan),
            'kata_kunci' => Angka::ringkas($median),
            'pembahasan' => 'Median dicari setelah data diurutkan lebih dahulu: '
                .implode(', ', array_map(fn ($n) => Angka::id($n), $urut)).'.<br>'.$langkah,
        ];
    }

    protected function modus(string $awalan, array $frekuensi, array $data, float $mean, float $median, float $modus, string $satuan): array
    {
        $sering = max($frekuensi);

        return [
            'judul' => 'Modus '.Angka::ringkas($modus),
            'teks' => $awalan.' Berapakah modus data tersebut?',
            'pernyataan' => $awalan.' Modus data tersebut adalah %s.',
            'pilihan' => self::pilihanNilai($modus, [
                // Dua kekeliruan khas: menjawab berapa kali modus itu muncul,
                // dan menyamakan modus dengan nilai terbesar.
                (float) $sering, max($data), $median, $mean, min($data),
            ], $satuan),
            'kata_kunci' => Angka::ringkas($modus),
            'pembahasan' => 'Modus adalah nilai yang paling sering muncul. Banyak kemunculan tiap '
                .'nilai: '.implode(', ', array_map(
                    fn ($n, $f) => Angka::id((float) $n).' sebanyak '.$f.' kali',
                    array_keys($frekuensi), $frekuensi,
                )).'.<br>Yang paling sering adalah '.Angka::ringkas($modus).', muncul '.$sering
                .' kali. Yang ditanyakan nilainya, bukan berapa kali ia muncul.',
        ];
    }

    protected function jangkauan(string $awalan, array $data, float $mean, float $median, float $jangkauan, string $satuan): array
    {
        return [
            'judul' => 'Jangkauan '.Angka::ringkas($jangkauan),
            'teks' => $awalan.' Berapakah jangkauan data tersebut?',
            'pernyataan' => $awalan.' Jangkauan data tersebut adalah %s.',
            'pilihan' => self::pilihanNilai($jangkauan, [
                max($data), min($data), max($data) + min($data), $mean, $median,
            ], $satuan),
            'kata_kunci' => Angka::ringkas($jangkauan),
            'pembahasan' => 'Jangkauan = data terbesar − data terkecil = '.Angka::id(max($data))
                .' − '.Angka::id(min($data)).' = '.Angka::ringkas($jangkauan).'.',
        ];
    }

    /**
     * Pilihan jawaban berupa angka beserta satuannya.
     *
     * @param  array<int, float>  $kandidat
     * @return array<int, array{teks: string, urut: float}>
     */
    protected static function pilihanNilai(float $benar, array $kandidat, string $satuan): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::ringkas($n).($satuan === '' ? '' : ' '.$satuan), 'urut' => $n],
            // Data boleh bernilai nol atau negatif — suhu, selisih skor —
            // jadi pengecoh yang minus tidak dibuang.
            array_merge([$benar], self::saring($benar, array_merge($kandidat, [
                $benar + 1, $benar - 1, $benar * 2,
            ]), 3, false)),
        );
    }

    /**
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
     * Tiga bentuk penyajian untuk data yang sama. Diagram titik membuat modus
     * terlihat sebagai tumpukan tertinggi, sedangkan tabel frekuensi membuat
     * rata-rata lebih mudah dihitung — guru memilih yang sesuai maksud soalnya.
     *
     * @param  array<int, float>  $data
     * @param  array<string, int>  $frekuensi
     */
    public static function svg(string $bentuk, array $data, array $frekuensi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        return (match ($bentuk) {
            'titik' => self::gambarTitik($kanvas, $frekuensi, $isi, $garis),
            'daftar' => self::gambarDaftar($kanvas, $data, $isi, $garis),
            default => self::gambarTabel($kanvas, $frekuensi, $isi, $garis),
        })->render();
    }

    /** @param array<string, int> $frekuensi */
    protected static function gambarTabel(Kanvas $kanvas, array $frekuensi, string $isi, string $garis): Kanvas
    {
        $tinggiBaris = min(30.0, 218 / (count($frekuensi) + 1));
        $lebar = [168.0, 148.0];
        $x0 = (Kanvas::LEBAR - array_sum($lebar)) / 2;

        $baris = array_merge(
            [['Nilai', 'Frekuensi']],
            array_map(fn ($n, $f) => [Angka::id((float) $n), (string) $f], array_keys($frekuensi), $frekuensi),
        );

        return $kanvas
            ->teksPx([Kanvas::LEBAR / 2, 30], 'Tabel frekuensi data', ['ukuran' => 15, 'tebal' => true])
            ->tabelPx([$x0, 46], $lebar, $tinggiBaris, $baris, $isi, $garis)
            ->teksPx([Kanvas::LEBAR / 2, 46 + ($tinggiBaris * (count($frekuensi) + 1)) + 22],
                'frekuensi = banyaknya data yang bernilai segitu',
                ['ukuran' => 11, 'warna' => '#6b7280']);
    }

    /**
     * Diagram titik: satu titik mewakili satu data, ditumpuk di atas nilainya.
     * Tumpukan tertinggi langsung menunjukkan modusnya.
     *
     * @param  array<string, int>  $frekuensi
     */
    protected static function gambarTitik(Kanvas $kanvas, array $frekuensi, string $isi, string $garis): Kanvas
    {
        $sumbuY = 248;
        $banyak = count($frekuensi);
        $jarak = min(64.0, 380 / max(1, $banyak));
        $x0 = Kanvas::LEBAR / 2 - ($jarak * ($banyak - 1)) / 2;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 30], 'Diagram titik', ['ukuran' => 15, 'tebal' => true])
            ->garis([$x0 - $jarak / 2, $sumbuY], [$x0 + $jarak * ($banyak - 0.5), $sumbuY], Kanvas::HITAM, 2);

        $i = 0;

        foreach ($frekuensi as $nilai => $f) {
            $x = $x0 + $i++ * $jarak;

            for ($k = 0; $k < $f; $k++) {
                $kanvas->lingkaran([$x, $sumbuY - 14 - $k * 20], 8, $isi, $garis, 1.8);
            }

            $kanvas->teksPx([$x, $sumbuY + 20], Angka::id((float) $nilai), ['ukuran' => 13, 'tebal' => true]);
        }

        return $kanvas->teksPx([Kanvas::LEBAR / 2, 284], 'satu titik mewakili satu data', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);
    }

    /**
     * Daftar data apa adanya, belum diurutkan — bentuk yang tepat untuk soal
     * median, sebab langkah pertamanya justru mengurutkan sendiri.
     *
     * @param  array<int, float>  $data
     */
    protected static function gambarDaftar(Kanvas $kanvas, array $data, string $isi, string $garis): Kanvas
    {
        $perBaris = min(10, max(5, (int) ceil(count($data) / 2)));
        $lebar = min(44.0, 420 / $perBaris);
        $tinggi = 36;
        $baris = (int) ceil(count($data) / $perBaris);
        $y0 = (Kanvas::TINGGI - 40 - $baris * $tinggi) / 2 + 40;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 30], 'Data hasil pengamatan', ['ukuran' => 15, 'tebal' => true]);

        foreach ($data as $i => $n) {
            $kolom = $i % $perBaris;
            $isiBaris = min($perBaris, count($data) - intdiv($i, $perBaris) * $perBaris);

            $kanvas->kotakPx([
                Kanvas::LEBAR / 2 - ($isiBaris - 1) * $lebar / 2 + $kolom * $lebar,
                $y0 + intdiv($i, $perBaris) * $tinggi + $tinggi / 2,
            ], $lebar - 4, $tinggi - 6, Angka::id($n), $isi, $garis, ['ukuran' => 14, 'sudut' => 4]);
        }

        return $kanvas->teksPx([Kanvas::LEBAR / 2, Kanvas::TINGGI - 20],
            'data belum diurutkan', ['ukuran' => 11, 'warna' => '#6b7280']);
    }
}

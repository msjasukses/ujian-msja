<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template lembar kerja (spreadsheet): alamat sel dan rumus fungsinya.
 *
 * Gambarnya berupa lembar kerja sungguhan lengkap dengan huruf lajur dan
 * nomor baris, sebab justru membaca alamat sel dari kepala lajur dan nomor
 * barisnya itulah yang diujikan. Tidak ada sel yang disorot: sorotan akan
 * menunjukkan sel mana yang dimaksud, sehingga siswa tidak perlu lagi
 * menghitung alamatnya sendiri.
 */
class LembarKerja extends Template
{
    /** Kepala tabel pada baris 1. */
    public const KEPALA = ['Nama', 'Ulangan 1', 'Ulangan 2', 'Jumlah'];

    /**
     * Isi baris 2 sampai 5: nama, nilai ulangan 1, dan nilai ulangan 2.
     *
     * @var array<int, array{0: string, 1: int, 2: int}>
     */
    public const DATA = [
        ['Andi', 80, 75],
        ['Bintang', 92, 85],
        ['Citra', 70, 96],
        ['Dewi', 85, 88],
    ];

    /** Baris pertama dan terakhir yang berisi angka. */
    public const BARIS_AWAL = 2;

    public const BARIS_AKHIR = 5;

    /**
     * Fungsi lembar kerja yang didukung.
     *
     * @var array<string, array{nama: string, maksud: string}>
     */
    public const FUNGSI = [
        'SUM' => ['nama' => 'SUM', 'maksud' => 'menjumlahkan seluruh nilai'],
        'AVERAGE' => ['nama' => 'AVERAGE', 'maksud' => 'menghitung rata-rata nilai'],
        'MAX' => ['nama' => 'MAX', 'maksud' => 'mencari nilai tertinggi'],
        'MIN' => ['nama' => 'MIN', 'maksud' => 'mencari nilai terendah'],
        'COUNT' => ['nama' => 'COUNT', 'maksud' => 'menghitung banyaknya data berupa angka'],
    ];

    /** @var array<string, string> */
    public const LAJUR = ['B' => 'B (Ulangan 1)', 'C' => 'C (Ulangan 2)'];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Hasil sebuah rumus',
        'rumus' => 'Rumus untuk suatu keperluan',
        'alamat' => 'Alamat sel sebuah nilai',
        'isi' => 'Isi sebuah alamat sel',
    ];

    public static function kode(): string
    {
        return 'lembar-kerja';
    }

    public static function nama(): string
    {
        return 'Lembar Kerja & Rumusnya';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Lembar kerja pengolah angka: membaca alamat sel, isi sel, menyusun '
            .'rumus SUM, AVERAGE, MAX, MIN, COUNT, serta menghitung hasilnya.';
    }

    public static function ikon(): string
    {
        return 'bi-file-earmark-spreadsheet';
    }

    public static function kataKunci(): array
    {
        return ['lembar kerja', 'spreadsheet', 'excel', 'pengolah angka', 'sel', 'alamat sel',
            'rumus', 'fungsi', 'sum', 'average', 'max', 'min', 'count', 'rentang sel',
            'informatika', 'tik'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'hasil'),
            [
                'nama' => 'fungsi',
                'label' => 'Fungsi',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($f) => $f['nama'].' — '.$f['maksud'],
                    self::FUNGSI,
                ),
                'awal' => 'SUM',
                'tampil' => ['tanya' => ['hasil', 'rumus']],
            ],
            [
                'nama' => 'lajur',
                'label' => 'Lajur yang dipakai',
                'jenis' => 'pilihan',
                'pilihan' => self::LAJUR,
                'awal' => 'B',
            ],
            self::kolomAngka('baris', 'Nomor baris sel', 3, self::BARIS_AWAL, self::BARIS_AKHIR, [
                'langkah' => 1,
                'tampil' => ['tanya' => ['alamat', 'isi']],
            ]),
            self::kolomWarna('hijau'),
        ];
    }

    /** Nilai satu lajur angka, berurutan dari baris 2 sampai 5. */
    public static function nilai(string $lajur): array
    {
        return array_column(self::DATA, $lajur === 'C' ? 2 : 1);
    }

    /** Hasil sebuah fungsi atas satu lajur angka. */
    public static function hitung(string $fungsi, string $lajur): float
    {
        $nilai = self::nilai($lajur);

        return (float) match ($fungsi) {
            'AVERAGE' => array_sum($nilai) / count($nilai),
            'MAX' => max($nilai),
            'MIN' => min($nilai),
            'COUNT' => count($nilai),
            default => array_sum($nilai),
        };
    }

    /** Rentang sel satu lajur angka, misalnya B2:B5. */
    public static function rentang(string $lajur): string
    {
        return $lajur.self::BARIS_AWAL.':'.$lajur.self::BARIS_AKHIR;
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $lajur = isset(self::LAJUR[$isi['lajur'] ?? '']) ? $isi['lajur'] : 'B';
        $fungsi = isset(self::FUNGSI[$isi['fungsi'] ?? '']) ? $isi['fungsi'] : 'SUM';
        $baris = min(self::BARIS_AKHIR, max(self::BARIS_AWAL, (int) ($isi['baris'] ?? 3)));

        $bahan = [
            'svg' => self::svgLembar($warnaIsi, $garis),
            'keterangan' => 'Lembar kerja berisi nama dan dua nilai ulangan empat siswa.',
        ];

        return $bahan + match ($isi['tanya'] ?? 'hasil') {
            'rumus' => $this->rumus($fungsi, $lajur),
            'alamat' => $this->alamat($lajur, $baris),
            'isi' => $this->isiSel($lajur, $baris),
            default => $this->hasil($fungsi, $lajur),
        };
    }

    protected function hasil(string $fungsi, string $lajur): array
    {
        $rentang = self::rentang($lajur);
        $benar = self::hitung($fungsi, $lajur);

        // Pengecohnya hasil fungsi lain atas rentang yang sama. Siswa yang
        // keliru membaca fungsinya menemukan jawabannya di daftar, sehingga
        // butirnya benar-benar menunjukkan di mana kekeliruannya.
        $pengecoh = self::saring($benar, array_map(
            fn ($lain) => self::hitung($lain, $lajur),
            array_values(array_diff(array_keys(self::FUNGSI), [$fungsi])),
        ));

        return [
            'judul' => '='.$fungsi.'('.$rentang.') = '.Angka::ringkas($benar),
            'teks' => 'Perhatikan lembar kerja berikut. Hasil rumus <strong>='.$fungsi.'('
                .$rentang.')</strong> adalah ...',
            'pernyataan' => 'Pada lembar kerja berikut, rumus ='.$fungsi.'('.$rentang
                .') menghasilkan %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n), 'urut' => $n],
                array_merge([$benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => 'Rentang '.$rentang.' berarti sel '.$lajur.self::BARIS_AWAL
                .' sampai '.$lajur.self::BARIS_AKHIR.', yang berisi '
                .implode(', ', array_map(fn ($n) => Angka::id($n), self::nilai($lajur)))
                .'.<br>Fungsi '.$fungsi.' '.self::FUNGSI[$fungsi]['maksud'].', sehingga '
                .'hasilnya <strong>'.Angka::ringkas($benar).'</strong>.',
        ];
    }

    protected function rumus(string $fungsi, string $lajur): array
    {
        $benar = '='.$fungsi.'('.self::rentang($lajur).')';

        // Pengecohnya kekeliruan yang khas: rentang menyertakan baris kepala,
        // rentang menyertakan baris kosong di bawahnya, dan fungsi tertukar.
        $lain = $fungsi === 'SUM' ? 'AVERAGE' : 'SUM';

        $pengecoh = [
            '='.$fungsi.'('.$lajur.'1:'.$lajur.self::BARIS_AKHIR.')',
            '='.$fungsi.'('.$lajur.self::BARIS_AWAL.':'.$lajur.(self::BARIS_AKHIR + 1).')',
            '='.$lain.'('.self::rentang($lajur).')',
        ];

        return [
            'judul' => 'Rumus '.self::FUNGSI[$fungsi]['maksud'],
            'teks' => 'Perhatikan lembar kerja berikut. Rumus yang tepat untuk '
                .self::FUNGSI[$fungsi]['maksud'].' pada lajur '.$lajur.' adalah ...',
            'pernyataan' => 'Pada lembar kerja berikut, rumus untuk '
                .self::FUNGSI[$fungsi]['maksud'].' pada lajur '.$lajur.' adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], $pengecoh),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Angka pada lajur '.$lajur.' hanya ada di baris '.self::BARIS_AWAL
                .' sampai '.self::BARIS_AKHIR.'. Baris 1 berisi kepala tabel yang berupa '
                .'tulisan, jadi tidak ikut dihitung.<br>Fungsi untuk '
                .self::FUNGSI[$fungsi]['maksud'].' adalah '.$fungsi.', sehingga rumusnya '
                .'<strong>'.$benar.'</strong>.',
        ];
    }

    protected function alamat(string $lajur, int $baris): array
    {
        $nilai = self::nilai($lajur)[$baris - self::BARIS_AWAL];
        $benar = $lajur.$baris;
        $lajurLain = $lajur === 'B' ? 'C' : 'B';

        // Pengecohnya kekeliruan yang khas: nomor baris dan huruf lajur
        // tertulis terbalik, barisnya dihitung dari data bukan dari nomor
        // baris lembar kerja, dan lajurnya tertukar.
        $pengecoh = [
            $baris.$lajur,
            $lajur.($baris - 1),
            $lajurLain.$baris,
        ];

        return [
            'judul' => 'Nilai '.$nilai.' ada di sel '.$benar,
            'teks' => 'Perhatikan lembar kerja berikut. Nilai <strong>'.$nilai
                .'</strong> milik '.self::DATA[$baris - self::BARIS_AWAL][0]
                .' berada pada alamat sel ...',
            'pernyataan' => 'Pada lembar kerja berikut, nilai '.$nilai.' milik '
                .self::DATA[$baris - self::BARIS_AWAL][0].' berada pada sel %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], $pengecoh),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Alamat sel ditulis huruf lajurnya dahulu, baru nomor barisnya.<br>'
                .'Nilai '.$nilai.' berada di lajur '.$lajur.' dan baris ke-'.$baris
                .', sehingga alamatnya <strong>'.$benar.'</strong>.',
        ];
    }

    protected function isiSel(string $lajur, int $baris): array
    {
        $benar = self::nilai($lajur)[$baris - self::BARIS_AWAL];
        $lajurLain = $lajur === 'B' ? 'C' : 'B';

        // Pengecohnya isi sel tetangga: satu baris di atas, satu baris di
        // bawah, dan lajur sebelahnya pada baris yang sama.
        $tetangga = self::nilai($lajur);
        $pengecoh = self::saring((float) $benar, [
            $tetangga[max(0, $baris - self::BARIS_AWAL - 1)],
            $tetangga[min(count($tetangga) - 1, $baris - self::BARIS_AWAL + 1)],
            self::nilai($lajurLain)[$baris - self::BARIS_AWAL],
            $tetangga[0],
            $tetangga[count($tetangga) - 1],
        ], 0);

        return [
            'judul' => 'Sel '.$lajur.$baris.' berisi '.$benar,
            'teks' => 'Perhatikan lembar kerja berikut. Sel <strong>'.$lajur.$baris
                .'</strong> berisi nilai ...',
            'pernyataan' => 'Pada lembar kerja berikut, sel '.$lajur.$baris.' berisi %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([(float) $benar], array_slice($pengecoh, 0, 3)),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => 'Sel '.$lajur.$baris.' adalah pertemuan lajur '.$lajur
                .' dengan baris ke-'.$baris.', yaitu nilai '
                .mb_strtolower(self::KEPALA[$lajur === 'C' ? 2 : 1])
                .' milik '.self::DATA[$baris - self::BARIS_AWAL][0].'.<br>Isinya '
                .'<strong>'.$benar.'</strong>.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Lembar kerja lengkap dengan kepala lajur dan nomor baris. */
    public static function svgLembar(string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $lebarNomor = 34.0;
        $lebar = [116.0, 92.0, 92.0, 76.0];
        $tinggi = 28.0;
        $kiri = 240 - ($lebarNomor + array_sum($lebar)) / 2;
        $atas = 62.0;

        $kanvas->teksPx([240, 36], 'Lembar kerja pengolah angka', ['ukuran' => 15, 'tebal' => true]);

        // Baris kepala: huruf lajur A sampai D.
        $x = $kiri + $lebarNomor;

        foreach (['A', 'B', 'C', 'D'] as $i => $huruf) {
            $kanvas
                ->tambah('<rect x="'.round($x, 1).'" y="'.$atas.'" width="'.$lebar[$i]
                    .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.5"/>')
                ->teksPx([$x + $lebar[$i] / 2, $atas + 19], $huruf, ['ukuran' => 14, 'tebal' => true]);

            $x += $lebar[$i];
        }

        $kanvas->tambah('<rect x="'.round($kiri, 1).'" y="'.$atas.'" width="'.$lebarNomor
            .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.5"/>');

        // Isi lembar kerja: baris 1 kepala tabel, 2-5 data, 6 dibiarkan kosong.
        $baris = [self::KEPALA];

        foreach (self::DATA as $d) {
            $baris[] = [$d[0], (string) $d[1], (string) $d[2], ''];
        }

        $baris[] = ['Rata-rata', '', '', ''];

        foreach ($baris as $b => $sel) {
            $y = $atas + ($b + 1) * $tinggi;

            $kanvas
                ->tambah('<rect x="'.round($kiri, 1).'" y="'.round($y, 1).'" width="'.$lebarNomor
                    .'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="1.5"/>')
                ->teksPx([$kiri + $lebarNomor / 2, $y + 19], (string) ($b + 1), [
                    'ukuran' => 13, 'tebal' => true,
                ]);

            $x = $kiri + $lebarNomor;

            foreach ($sel as $k => $teks) {
                // Tulisan rata kiri, angka rata kanan — seperti lembar kerja
                // sungguhan, dan itu menjadi petunjuk mana data berupa angka.
                $angka = $teks !== '' && is_numeric($teks);

                $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'
                    .$lebar[$k].'" height="'.$tinggi.'" fill="#ffffff" stroke="#c9ced4" stroke-width="1"/>');

                if ($teks !== '') {
                    $kanvas->teksPx([
                        $angka ? $x + $lebar[$k] - 10 : $x + 10,
                        $y + 19,
                    ], $teks, [
                        'anchor' => $angka ? 'end' : 'start',
                        'ukuran' => 13,
                        'tebal' => $b === 0,
                    ]);
                }

                $x += $lebar[$k];
            }
        }

        return $kanvas
            ->teksPx([240, $atas + (count($baris) + 1) * $tinggi + 22],
                'huruf di atas menunjukkan lajur, angka di kiri menunjukkan baris', [
                    'ukuran' => 11, 'warna' => '#6b7280',
                ])
            ->render();
    }
}

<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template diagram energi & laju reaksi.
 *
 * Seluruh diagramnya dihitung dari tiga angka saja — entalpi pereaksi,
 * entalpi puncak, dan entalpi hasil reaksi — sehingga guru dapat membuat
 * soal eksoterm maupun endoterm dari template yang sama, dan nilai ΔH serta
 * energi aktivasi yang menjadi kuncinya selalu sama dengan yang terbaca
 * pada sumbu gambarnya.
 */
class DiagramEnergi extends Template
{
    /**
     * Faktor yang mempercepat laju reaksi beserta alasannya. Pilihan
     * jawabannya berupa alasan saja, bukan "faktor + alasan", supaya butir
     * tidak terjawab hanya dengan mencocokkan nama faktornya.
     *
     * @var array<string, array{nama: string, alasan: string}>
     */
    public const FAKTOR = [
        'suhu' => [
            'nama' => 'menaikkan suhu',
            'alasan' => 'partikel bergerak lebih cepat sehingga tumbukan efektifnya bertambah',
        ],
        'konsentrasi' => [
            'nama' => 'memperbesar konsentrasi',
            'alasan' => 'jumlah partikel dalam tiap satuan volume bertambah banyak',
        ],
        'luas' => [
            'nama' => 'menghaluskan zat padat yang bereaksi',
            'alasan' => 'luas permukaan sentuh antarpartikelnya bertambah',
        ],
        'katalis' => [
            'nama' => 'menambahkan katalis',
            'alasan' => 'energi aktivasi reaksinya menjadi lebih rendah',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'jenis' => 'Jenis reaksi: eksoterm atau endoterm',
        'entalpi' => 'Besar perubahan entalpi (ΔH)',
        'aktivasi' => 'Besar energi aktivasi',
        'katalis' => 'Pengaruh katalis pada diagram',
        'laju' => 'Alasan sebuah faktor mempercepat reaksi',
    ];

    public static function kode(): string
    {
        return 'diagram-energi';
    }

    public static function nama(): string
    {
        return 'Diagram Energi & Laju Reaksi';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Diagram tingkat energi: membedakan eksoterm dan endoterm, membaca '
            .'ΔH dan energi aktivasi, pengaruh katalis, serta faktor laju reaksi.';
    }

    public static function ikon(): string
    {
        return 'bi-graph-up-arrow';
    }

    public static function kataKunci(): array
    {
        return ['entalpi', 'diagram energi', 'eksoterm', 'endoterm', 'termokimia',
            'energi aktivasi', 'katalis', 'laju reaksi', 'tumbukan', 'delta h'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'jenis'),
            self::kolomAngka('h_reaktan', 'Entalpi pereaksi (kJ)', 150, -2000, 2000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['jenis', 'entalpi', 'aktivasi', 'katalis']],
            ]),
            self::kolomAngka('h_puncak', 'Entalpi puncak (kJ)', 310, -2000, 2000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['jenis', 'entalpi', 'aktivasi', 'katalis']],
            ]),
            self::kolomAngka('h_produk', 'Entalpi hasil reaksi (kJ)', 40, -2000, 2000, [
                'langkah' => 1, 'tampil' => ['tanya' => ['jenis', 'entalpi', 'aktivasi', 'katalis']],
            ]),
            [
                'nama' => 'faktor',
                'label' => 'Faktor yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($f) => ucfirst($f['nama']), self::FAKTOR),
                'awal' => 'suhu',
                'tampil' => ['tanya' => ['laju']],
            ],
            self::kolomWarna('jingga'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? 'jenis') === 'laju') {
            return [];
        }

        $puncak = (float) ($isi['h_puncak'] ?? 0);

        // Puncak diagram adalah keadaan peralihan: ia harus lebih tinggi
        // daripada pereaksi maupun hasil reaksi, kalau tidak reaksinya tidak
        // punya energi aktivasi sama sekali.
        if ($puncak <= (float) ($isi['h_reaktan'] ?? 0) || $puncak <= (float) ($isi['h_produk'] ?? 0)) {
            return ['h_puncak' => 'Entalpi puncak harus lebih tinggi daripada pereaksi dan hasil reaksi.'];
        }

        if (abs((float) $isi['h_reaktan'] - (float) $isi['h_produk']) < 0.001) {
            return ['h_produk' => 'Beda entalpinya nol; isi nilai yang berbeda dengan entalpi pereaksi.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');

        if (($isi['tanya'] ?? 'jenis') === 'laju') {
            return $this->laju($isi['faktor'] ?? 'suhu', $warnaIsi, $garis);
        }

        $reaktan = (float) ($isi['h_reaktan'] ?? 150);
        $puncak = (float) ($isi['h_puncak'] ?? 310);
        $produk = (float) ($isi['h_produk'] ?? 40);

        $delta = round($produk - $reaktan, 2);
        $aktivasi = round($puncak - $reaktan, 2);
        $eksoterm = $produk < $reaktan;

        $tanya = $isi['tanya'] ?? 'jenis';

        $bahan = [
            'svg' => self::svgDiagram($reaktan, $puncak, $produk, $tanya === 'katalis', $warnaIsi, $garis),
            'keterangan' => 'Diagram tingkat energi: entalpi pereaksi '.Angka::id($reaktan)
                .' kJ, puncak '.Angka::id($puncak).' kJ, hasil reaksi '.Angka::id($produk).' kJ.',
        ];

        $awalan = 'Perhatikan diagram tingkat energi berikut.';

        return $bahan + match ($tanya) {
            'entalpi' => $this->entalpi($awalan, $reaktan, $produk, $delta, $aktivasi, $eksoterm),
            'aktivasi' => $this->aktivasi($awalan, $reaktan, $puncak, $delta, $aktivasi),
            'katalis' => $this->katalis($awalan, $aktivasi),
            default => $this->jenisReaksi($awalan, $reaktan, $produk, $delta, $eksoterm),
        };
    }

    protected function jenisReaksi(string $awalan, float $reaktan, float $produk, float $delta, bool $eksoterm): array
    {
        $benar = $eksoterm
            ? 'Eksoterm, karena entalpi hasil reaksi lebih rendah daripada pereaksi'
            : 'Endoterm, karena entalpi hasil reaksi lebih tinggi daripada pereaksi';

        return [
            'judul' => $eksoterm ? 'Reaksi eksoterm' : 'Reaksi endoterm',
            'teks' => $awalan.' Reaksi yang digambarkan tergolong ...',
            'pernyataan' => $awalan.' Reaksi yang digambarkan tergolong %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Dua pengecohnya memasangkan nama yang benar dengan alasan
                // yang keliru. Itu memisahkan siswa yang menghafal namanya
                // dari yang benar-benar membaca diagramnya.
                array_merge([$benar], array_values(array_diff([
                    'Endoterm, karena entalpi hasil reaksi lebih tinggi daripada pereaksi',
                    'Eksoterm, karena entalpi hasil reaksi lebih tinggi daripada pereaksi',
                    'Endoterm, karena entalpi hasil reaksi lebih rendah daripada pereaksi',
                    'Eksoterm, karena entalpi hasil reaksi lebih rendah daripada pereaksi',
                ], [$benar]))),
            ),
            'kata_kunci' => $eksoterm ? 'Eksoterm' : 'Endoterm',
            'pembahasan' => 'Entalpi pereaksi '.Angka::id($reaktan).' kJ dan entalpi hasil reaksi '
                .Angka::id($produk).' kJ, sehingga ΔH = '.Angka::id($produk).' − '.Angka::id($reaktan)
                .' = '.Angka::id($delta).' kJ.<br>'
                .($eksoterm
                    ? 'ΔH bertanda negatif: sistem melepaskan kalor, jadi reaksinya eksoterm.'
                    : 'ΔH bertanda positif: sistem menyerap kalor, jadi reaksinya endoterm.'),
        ];
    }

    protected function entalpi(string $awalan, float $reaktan, float $produk, float $delta, float $aktivasi, bool $eksoterm): array
    {
        return [
            'judul' => 'ΔH = '.Angka::id($delta).' kJ',
            'teks' => $awalan.' Berapakah perubahan entalpi (ΔH) reaksi tersebut?',
            'pernyataan' => $awalan.' Perubahan entalpi (ΔH) reaksi tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => ($n > 0 ? '+' : '').Angka::id($n).' kJ', 'urut' => $n],
                // Tandanya boleh negatif, jadi pengecoh yang nol atau minus
                // tidak dibuang: justru tanda itulah yang paling sering
                // keliru dibaca.
                array_merge([$delta], self::saring($delta, [
                    -$delta, $aktivasi, -$aktivasi, $reaktan + $produk, $produk, $reaktan,
                ], 2, false)),
            ),
            'kata_kunci' => Angka::id($delta),
            'pembahasan' => 'ΔH = entalpi hasil reaksi − entalpi pereaksi = '.Angka::id($produk)
                .' − '.Angka::id($reaktan).' = '.Angka::id($delta).' kJ.<br>'
                .'Tandanya '.($eksoterm ? 'negatif karena kalor dilepaskan' : 'positif karena kalor diserap')
                .'. Perhatikan bahwa ΔH diukur dari pereaksi ke hasil reaksi, bukan dari puncaknya.',
        ];
    }

    protected function aktivasi(string $awalan, float $reaktan, float $puncak, float $delta, float $aktivasi): array
    {
        return [
            'judul' => 'Energi aktivasi '.Angka::id($aktivasi).' kJ',
            'teks' => $awalan.' Berapakah energi aktivasi reaksi tersebut?',
            'pernyataan' => $awalan.' Energi aktivasi reaksi tersebut adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n).' kJ', 'urut' => $n],
                array_merge([$aktivasi], self::saring($aktivasi, [
                    // Pengecoh utamanya mengukur dari dasar diagram, bukan
                    // dari tingkat pereaksinya, dan tertukar dengan ΔH.
                    $puncak, abs($delta), $aktivasi - $delta, $puncak + $reaktan, $aktivasi / 2,
                    // Cadangan untuk diagram berentalpi negatif, tempat
                    // pengecoh di atas banyak yang terbuang karena bernilai
                    // minus atau berimpit satu sama lain.
                    abs($puncak), abs($reaktan), $aktivasi * 3,
                ])),
            ),
            'kata_kunci' => Angka::id($aktivasi),
            'pembahasan' => 'Energi aktivasi adalah selisih entalpi puncak dengan entalpi pereaksi, '
                .'bukan tinggi puncak dari dasar diagram.<br>Ea = '.Angka::id($puncak).' − '
                .Angka::id($reaktan).' = '.Angka::id($aktivasi).' kJ.',
        ];
    }

    protected function katalis(string $awalan, float $aktivasi): array
    {
        $benar = 'Menurunkan energi aktivasi sehingga reaksi berlangsung lebih cepat';

        return [
            'judul' => 'Pengaruh katalis',
            'teks' => $awalan.' Garis putus-putus pada diagram itu menunjukkan jalannya reaksi '
                .'setelah ditambah katalis. Pengaruh katalis tersebut adalah ...',
            'pernyataan' => $awalan.' Garis putus-putusnya menunjukkan bahwa katalis %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                [
                    $benar,
                    'Menaikkan energi aktivasi sehingga reaksi berlangsung lebih cepat',
                    // Pengecoh terpenting: katalis dikira ikut mengubah ΔH.
                    // Pada diagram, kedua ujungnya justru tidak bergeser.
                    'Menurunkan perubahan entalpi (ΔH) reaksi',
                    'Menaikkan entalpi hasil reaksi',
                ],
            ),
            'kata_kunci' => 'Menurunkan energi aktivasi',
            'pembahasan' => 'Katalis menyediakan jalan reaksi lain yang energi aktivasinya lebih '
                .'rendah daripada '.Angka::id($aktivasi).' kJ, sehingga lebih banyak partikel mampu '
                .'melampauinya dan reaksi menjadi lebih cepat.<br>Perhatikan bahwa kedua ujung '
                .'garis putus-putus berimpit dengan garis semula: katalis tidak mengubah entalpi '
                .'pereaksi maupun hasil reaksi, jadi ΔH-nya tetap.',
        ];
    }

    protected function laju(string $kunci, string $warnaIsi, string $garis): array
    {
        $faktor = self::FAKTOR[$kunci] ?? self::FAKTOR['suhu'];
        $benar = ucfirst($faktor['alasan']);

        return [
            'svg' => self::svgTumbukan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Dua wadah berisi partikel: sebelah kiri sebelum dan sebelah kanan '
                .'setelah '.$faktor['nama'].'.',
            'judul' => 'Laju reaksi: '.$faktor['nama'],
            'teks' => 'Perhatikan gambar partikel pereaksi berikut. Mengapa '.$faktor['nama']
                .' membuat reaksi berlangsung lebih cepat?',
            'pernyataan' => 'Pada gambar partikel pereaksi berikut, '.$faktor['nama']
                .' mempercepat reaksi karena %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                array_merge([$faktor['alasan']], array_values(array_diff(
                    array_column(self::FAKTOR, 'alasan'), [$faktor['alasan']],
                ))),
            ),
            'kata_kunci' => $faktor['alasan'],
            'pembahasan' => 'Reaksi berlangsung melalui tumbukan antarpartikel yang cukup berenergi. '
                .ucfirst($faktor['nama']).' mempercepatnya karena '.$faktor['alasan'].'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Diagram tingkat energi: dataran pereaksi, puncak keadaan peralihan,
     * lalu dataran hasil reaksi, dengan nilai entalpinya tertulis di sumbu.
     */
    public static function svgDiagram(float $reaktan, float $puncak, float $produk, bool $katalis, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $bawah = min($reaktan, $puncak, $produk);
        $rentang = max(0.001, max($reaktan, $puncak, $produk) - $bawah);
        $y = fn (float $h) => 236 - ($h - $bawah) / $rentang * 150;

        [$yr, $yp, $yq] = [$y($reaktan), $y($puncak), $y($produk)];

        $kanvas
            ->garis([74, 44], [74, 258], Kanvas::HITAM, 2)
            ->garis([74, 258], [452, 258], Kanvas::HITAM, 2)
            ->teksPx([74, 30], 'Entalpi (kJ)', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([270, 282], 'jalannya reaksi', ['ukuran' => 12, 'warna' => '#4b5563']);

        foreach ([[$reaktan, $yr], [$puncak, $yp], [$produk, $yq]] as [$nilai, $tinggi]) {
            $kanvas->garis([74, $tinggi], [440, $tinggi], '#c8cdd3', 1, true)
                ->teksPx([68, $tinggi + 5], Angka::id($nilai), ['ukuran' => 12, 'anchor' => 'end']);
        }

        if ($katalis) {
            // Puncak berkatalis ditaruh di tengah antara pereaksi dan puncak
            // semula; kedua ujungnya sengaja dibiarkan berimpit karena
            // katalis tidak mengubah entalpi pereaksi maupun hasil reaksi.
            $yk = ($yr + $yp) / 2;

            $kanvas->tambah('<path d="M 100 '.round($yr, 1).' L 155 '.round($yr, 1)
                .' C 195 '.round($yr, 1).' 205 '.round($yk, 1).' 240 '.round($yk, 1)
                .' C 275 '.round($yk, 1).' 285 '.round($yq, 1).' 325 '.round($yq, 1)
                .' L 440 '.round($yq, 1).'" fill="none" stroke="'.$garis
                .'" stroke-width="2.5" stroke-dasharray="7 5"/>');
        }

        $kanvas->tambah('<path d="M 100 '.round($yr, 1).' L 155 '.round($yr, 1)
            .' C 195 '.round($yr, 1).' 205 '.round($yp, 1).' 240 '.round($yp, 1)
            .' C 275 '.round($yp, 1).' 285 '.round($yq, 1).' 325 '.round($yq, 1)
            .' L 440 '.round($yq, 1).'" fill="none" stroke="'.Kanvas::HITAM.'" stroke-width="3"/>');

        // Panah energi aktivasi di bawah puncak, dan panah ΔH pada dataran
        // hasil reaksi — keduanya berkepala dua karena menyatakan selisih.
        self::selisih($kanvas, 240, $yr, $yp, 'Ea', 'end', 230, $garis);

        if (abs($yr - $yq) > 6) {
            self::selisih($kanvas, 392, $yr, $yq, 'ΔH', 'start', 402, $garis);
        }

        $kanvas
            ->teksPx([120, $yr - 12], 'pereaksi', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([404, $yq - 12], 'hasil reaksi', ['ukuran' => 12, 'tebal' => true]);

        return $kanvas->render();
    }

    /** Panah berkepala dua beserta namanya, untuk menyatakan selisih entalpi. */
    protected static function selisih(Kanvas $kanvas, float $x, float $atas, float $bawah, string $nama, string $anchor, float $xLabel, string $warna): Kanvas
    {
        return $kanvas
            ->panahPx([$x, $atas], [$x, $bawah], $warna, 2)
            ->panahPx([$x, $bawah], [$x, $atas], $warna, 2)
            ->teksPx([$xLabel, ($atas + $bawah) / 2 + 5], $nama, [
                'ukuran' => 15, 'tebal' => true, 'anchor' => $anchor, 'warna' => $warna,
            ]);
    }

    /**
     * Dua wadah partikel: sebelah kiri sebelum, sebelah kanan setelah faktor
     * itu diterapkan. Yang berubah hanya hal yang memang diubah faktornya —
     * banyak partikel, ukuran butiran, kecepatan, atau adanya katalis.
     */
    public static function svgTumbukan(string $faktor, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas->teksPx([Kanvas::LEBAR / 2, 30], 'Sebelum dan sesudah '
            .self::FAKTOR[$faktor]['nama'], ['ukuran' => 15, 'tebal' => true]);

        foreach ([[42, 'sebelum', false], [250, 'sesudah', true]] as [$x, $label, $sesudah]) {
            $kanvas->tambah('<rect x="'.$x.'" y="60" width="188" height="178" rx="10" fill="#f6f8fa" '
                .'stroke="'.Kanvas::HITAM.'" stroke-width="2.5"/>')
                ->teksPx([$x + 94, 258], $label, ['ukuran' => 13, 'tebal' => true]);

            $banyak = $faktor === 'konsentrasi' && $sesudah ? 18 : 9;
            $jari = $faktor === 'luas' && $sesudah ? 7 : 13;
            $banyak = $faktor === 'luas' && $sesudah ? 24 : $banyak;

            foreach (Kanvas::sebaran($banyak, $x + 16, 76, 156, 146) as $i => $titik) {
                $kanvas->lingkaran($titik, $jari, $isi, $garis, 1.8);

                // Suhu yang naik digambar sebagai jejak gerak: makin panjang
                // ekornya, makin cepat partikelnya bergerak.
                if ($faktor === 'suhu') {
                    $panjang = $sesudah ? 22 : 9;
                    $sudut = deg2rad(($i * 47) % 360);

                    $kanvas->garis($titik, [
                        $titik[0] + cos($sudut) * ($jari + $panjang),
                        $titik[1] + sin($sudut) * ($jari + $panjang),
                    ], '#8a9199', 2);
                }
            }

            if ($faktor === 'katalis' && $sesudah) {
                $kanvas->kotakPx([$x + 94, 150], 110, 36, 'katalis', '#fdeab0', '#a97d10', [
                    'ukuran' => 13, 'tebal' => true,
                ]);
            }
        }

        return $kanvas->render();
    }
}

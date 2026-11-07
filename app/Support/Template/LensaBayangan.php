<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template lensa & pembentukan bayangan.
 *
 * Gambarnya sengaja hanya menampilkan susunan alatnya — lensa, titik fokus,
 * titik pusat kelengkungan, dan benda beserta jaraknya — tanpa bayangan dan
 * tanpa sinar istimewa. Bayangan itulah jawabannya: kalau ikut digambar,
 * siswa tinggal mengukurnya dengan penggaris dan rumus lensa tidak pernah
 * dipakai. Yang tergambar adalah apa yang diketahui, bukan apa yang dicari.
 *
 * Jarak bayangan, perbesaran, tinggi bayangan, dan sifatnya semuanya
 * dihitung dari satu rumus yang sama, sehingga keempatnya mustahil saling
 * berselisih.
 */
class LensaBayangan extends Template
{
    /**
     * Jenis lensa beserta tanda jarak fokusnya. Lensa cekung berfokus maya,
     * sehingga jarak fokusnya bertanda negatif dalam rumus.
     *
     * @var array<string, array{nama: string, tanda: int, sifat: string}>
     */
    public const LENSA = [
        'cembung' => [
            'nama' => 'lensa cembung',
            'tanda' => 1,
            'sifat' => 'mengumpulkan sinar (konvergen)',
        ],
        'cekung' => [
            'nama' => 'lensa cekung',
            'tanda' => -1,
            'sifat' => 'menyebarkan sinar (divergen)',
        ],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'jarak' => 'Jarak bayangan ke lensa',
        'perbesaran' => 'Perbesaran bayangan',
        'tinggi' => 'Tinggi bayangan',
        'sifat' => 'Sifat bayangan yang terbentuk',
    ];

    /**
     * Keempat sifat bayangan yang mungkin. Nyata selalu terbalik dan maya
     * selalu tegak, jadi hanya empat gabungan ini yang benar-benar terjadi —
     * itulah yang dipakai sebagai pilihan jawaban.
     *
     * @var array<int, string>
     */
    public const SIFAT = [
        'Nyata, terbalik, diperbesar',
        'Nyata, terbalik, diperkecil',
        'Maya, tegak, diperbesar',
        'Maya, tegak, diperkecil',
    ];

    public static function kode(): string
    {
        return 'lensa-bayangan';
    }

    public static function nama(): string
    {
        return 'Lensa & Pembentukan Bayangan';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Lensa cembung dan cekung beserta titik fokusnya: menghitung jarak '
            .'bayangan, perbesaran, tinggi bayangan, dan menentukan sifatnya.';
    }

    public static function ikon(): string
    {
        return 'bi-eyeglasses';
    }

    public static function kataKunci(): array
    {
        return ['lensa', 'cembung', 'cekung', 'konvergen', 'divergen', 'bayangan',
            'titik fokus', 'perbesaran', 'nyata', 'maya', 'optik', 'cahaya',
            'rumus lensa', 'jarak bayangan'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'jenis',
                'label' => 'Jenis lensa',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($l) => ucfirst($l['nama']).', '.$l['sifat'], self::LENSA),
                'awal' => 'cembung',
            ],
            self::kolomAngka('fokus', 'Jarak fokus (cm)', 10, 2, 100, ['langkah' => 1]),
            self::kolomAngka('jarak_benda', 'Jarak benda ke lensa (cm)', 30, 1, 300, ['langkah' => 1]),
            self::kolomAngka('tinggi_benda', 'Tinggi benda (cm)', 4, 1, 50, ['langkah' => 1]),
            self::kolomTanya(self::TANYA, 'jarak'),
            self::kolomWarna('biru'),
        ];
    }

    public static function periksa(array $isi): array
    {
        $jenis = isset(self::LENSA[$isi['jenis'] ?? '']) ? $isi['jenis'] : 'cembung';
        $fokus = self::LENSA[$jenis]['tanda'] * (float) ($isi['fokus'] ?? 10);
        $benda = (float) ($isi['jarak_benda'] ?? 30);

        // Benda tepat di titik fokus lensa cembung: sinar biasnya sejajar dan
        // bayangannya di tak hingga, jadi tidak ada angka yang bisa dijawab.
        if (abs($benda - $fokus) < 0.0001) {
            return ['jarak_benda' => 'Benda tepat di titik fokus, sehingga bayangannya di tak '
                .'hingga dan tidak punya jarak yang dapat dihitung.'];
        }

        if (abs(self::jarakBayangan($benda, $fokus)) > 400) {
            return ['jarak_benda' => 'Benda terlalu dekat ke titik fokus: bayangannya lebih dari '
                .'400 cm dari lensa sehingga tidak muat digambar. Jauhkan atau dekatkan bendanya.'];
        }

        // Bila bendanya jauh sekali dibanding fokusnya, titik F tergambar
        // menempel pada badan lensa dan susunannya tidak lagi terbaca.
        if ($benda > 8 * abs($fokus)) {
            return ['jarak_benda' => 'Jarak bendanya lebih dari delapan kali jarak fokus, sehingga '
                .'titik F tergambar menempel pada lensa. Dekatkan bendanya atau perbesar fokusnya.'];
        }

        return [];
    }

    /** Jarak bayangan dari rumus lensa 1/f = 1/s + 1/s'. */
    public static function jarakBayangan(float $benda, float $fokus): float
    {
        return $benda * $fokus / ($benda - $fokus);
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        $jenis = isset(self::LENSA[$isi['jenis'] ?? '']) ? $isi['jenis'] : 'cembung';
        $lensa = self::LENSA[$jenis];

        $fokusMutlak = (float) ($isi['fokus'] ?? 10);
        $fokus = $lensa['tanda'] * $fokusMutlak;
        $benda = (float) ($isi['jarak_benda'] ?? 30);
        $tinggi = (float) ($isi['tinggi_benda'] ?? 4);

        // Besaran turunan dihitung dari jarak bayangan yang belum dibulatkan:
        // membulatkan dua kali membuat tinggi bayangan meleset pada angka
        // terakhirnya. Yang dibulatkan hanya yang ditampilkan.
        $tepat = self::jarakBayangan($benda, $fokus);
        $bayangan = round($tepat, 3);

        // Perbesaran bertanda: negatif berarti terbalik. Yang ditanyakan di
        // sekolah besarnya saja, tetapi tandanya dipakai menentukan sifatnya.
        $perbesaran = -$tepat / $benda;
        $besar = round(abs($perbesaran), 3);
        $tinggiBayangan = round(abs($perbesaran) * $tinggi, 3);
        $nyata = $bayangan > 0;

        $bahan = [
            'svg' => self::svgLensa($jenis, $fokusMutlak, $benda, $tinggi, $warnaIsi, $garis),
            'keterangan' => ucfirst($lensa['nama']).' berjarak fokus '.Angka::id($fokusMutlak)
                .' cm; benda setinggi '.Angka::id($tinggi).' cm berada '.Angka::id($benda)
                .' cm di depan lensa.',
        ];

        $diketahui = 'Sebuah benda setinggi '.Angka::id($tinggi).' cm diletakkan '
            .Angka::id($benda).' cm di depan '.$lensa['nama'].' yang jarak fokusnya '
            .Angka::id($fokusMutlak).' cm.';

        return $bahan + match ($isi['tanya'] ?? 'jarak') {
            'perbesaran' => $this->perbesaran($diketahui, $benda, $bayangan, $besar),
            'tinggi' => $this->tinggi($diketahui, $tinggi, $besar, $tinggiBayangan, $bayangan, $benda),
            'sifat' => $this->sifat($diketahui, $nyata, $besar, $bayangan, $benda, $fokus),
            default => $this->jarak($diketahui, $benda, $fokus, $bayangan, $nyata),
        };
    }

    protected function jarak(string $diketahui, float $benda, float $fokus, float $bayangan, bool $nyata): array
    {
        $benar = round(abs($bayangan), 3);

        return [
            'judul' => 'Jarak bayangan '.Angka::ringkas($benar).' cm',
            'teks' => $diketahui.' Berapakah jarak bayangan ke lensa?',
            'pernyataan' => $diketahui.' Jarak bayangannya ke lensa adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' cm', 'urut' => $n],
                array_merge([$benar], self::saring($benar, [
                    // Pengecoh terpentingnya salah tanda pada rumus lensa:
                    // 1/s' dikira 1/f + 1/s, bukan 1/f − 1/s.
                    abs($benda * $fokus / ($benda + $fokus)),
                    abs($benda - $fokus), $benda, abs($fokus), $benar * 2,
                ], 3)),
            ),
            'kata_kunci' => Angka::ringkas($benar),
            'pembahasan' => 'Rumus lensa: 1/f = 1/s + 1/s&prime;, sehingga 1/s&prime; = 1/f − 1/s.<br>'
                .'1/s&prime; = 1/'.Angka::id($fokus).' − 1/'.Angka::id($benda).', jadi s&prime; = '
                .Angka::ringkas($bayangan).' cm.<br>'
                .($nyata
                    ? 'Tandanya positif, jadi bayangannya nyata dan berada di belakang lensa, '
                        .'sejauh '.Angka::ringkas($benar).' cm.'
                    : 'Tandanya negatif, jadi bayangannya maya dan berada di depan lensa — sepihak '
                        .'dengan bendanya — sejauh '.Angka::ringkas($benar).' cm.'),
        ];
    }

    protected function perbesaran(string $diketahui, float $benda, float $bayangan, float $besar): array
    {
        return [
            'judul' => 'Perbesaran '.Angka::ringkas($besar).' kali',
            'teks' => $diketahui.' Berapakah perbesaran bayangannya?',
            'pernyataan' => $diketahui.' Perbesaran bayangannya adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' kali', 'urut' => $n],
                array_merge([$besar], self::saring($besar, [
                    // Pengecoh utamanya membalik perbandingannya: s dibagi
                    // s&prime;, bukan sebaliknya.
                    $besar > 0 ? round(1 / $besar, 3) : 0,
                    abs($bayangan), $benda, $besar * 2, $besar / 2,
                ], 3)),
            ),
            'kata_kunci' => Angka::ringkas($besar),
            'pembahasan' => 'Perbesaran M = |s&prime; ÷ s|, yaitu jarak bayangan dibagi jarak benda.<br>'
                .'M = |'.Angka::ringkas($bayangan).' ÷ '.Angka::id($benda).'| = '
                .Angka::ringkas($besar).' kali.<br>'
                .($besar > 1
                    ? 'Karena lebih dari 1, bayangannya diperbesar.'
                    : ($besar < 1 ? 'Karena kurang dari 1, bayangannya diperkecil.'
                        : 'Karena sama dengan 1, bayangannya sama besar dengan bendanya.')),
        ];
    }

    protected function tinggi(string $diketahui, float $tinggi, float $besar, float $tinggiBayangan, float $bayangan, float $benda): array
    {
        return [
            'judul' => 'Tinggi bayangan '.Angka::ringkas($tinggiBayangan).' cm',
            'teks' => $diketahui.' Berapakah tinggi bayangannya?',
            'pernyataan' => $diketahui.' Tinggi bayangannya adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::ringkas($n).' cm', 'urut' => $n],
                array_merge([$tinggiBayangan], self::saring($tinggiBayangan, [
                    // Perbesarannya dibalik, atau tingginya tidak dikalikan
                    // sama sekali — dua kekeliruan yang paling sering muncul.
                    $besar > 0 ? round($tinggi / $besar, 3) : 0,
                    $tinggi, $besar, abs($bayangan), $tinggiBayangan * 2,
                ], 3)),
            ),
            'kata_kunci' => Angka::ringkas($tinggiBayangan),
            'pembahasan' => 'Perbesaran M = |s&prime; ÷ s| = |'.Angka::ringkas($bayangan).' ÷ '
                .Angka::id($benda).'| = '.Angka::ringkas($besar).' kali.<br>'
                .'Tinggi bayangan h&prime; = M × h = '.Angka::ringkas($besar).' × '
                .Angka::id($tinggi).' = '.Angka::ringkas($tinggiBayangan).' cm.',
        ];
    }

    protected function sifat(string $diketahui, bool $nyata, float $besar, float $bayangan, float $benda, float $fokus): array
    {
        $ukuran = match (true) {
            $besar > 1 => 'diperbesar',
            $besar < 1 => 'diperkecil',
            default => 'sama besar',
        };

        $benar = ($nyata ? 'Nyata, terbalik, ' : 'Maya, tegak, ').$ukuran;

        return [
            'judul' => $benar,
            'teks' => $diketahui.' Bagaimanakah sifat bayangan yang terbentuk?',
            'pernyataan' => $diketahui.' Sifat bayangan yang terbentuk adalah %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff(self::SIFAT, [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Jarak bayangan s&prime; = '.Angka::ringkas($bayangan).' cm, bertanda '
                .($nyata ? 'positif' : 'negatif').'.<br>'
                .($nyata
                    ? 'Bayangan dengan s&prime; positif berada di belakang lensa, dapat ditangkap '
                        .'layar, jadi nyata — dan bayangan nyata pada lensa selalu terbalik.'
                    : 'Bayangan dengan s&prime; negatif berada sepihak dengan bendanya, tidak dapat '
                        .'ditangkap layar, jadi maya — dan bayangan maya pada lensa selalu tegak.')
                .'<br>Perbesarannya M = '.Angka::ringkas($besar).' kali, sehingga bayangannya '
                .$ukuran.'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Susunan lensa dan benda: sumbu utama, lensa, titik fokus dan 2F di
     * kedua sisi, serta benda beserta jarak dan tingginya. Bayangannya tidak
     * digambar — itulah yang ditanyakan.
     */
    public static function svgLensa(string $jenis, float $fokus, float $benda, float $tinggi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $sumbu = 150;
        $pusat = 240;

        // Skala mendatar dipilih agar yang terjauh di antara benda dan 2F
        // tetap muat; tinggi bendanya digambar tetap, sebab yang perlu
        // terbaca adalah letaknya terhadap F dan 2F, bukan perbandingan
        // tinggi dengan jarak.
        $skala = 196 / max($benda, 2 * $fokus);
        $tinggiPx = 54;

        $kanvas
            ->garis([22, $sumbu], [458, $sumbu], '#8a9199', 2)
            ->teksPx([240, 36], ucfirst(self::LENSA[$jenis]['nama']), ['ukuran' => 14, 'tebal' => true]);

        // Titik fokus dan 2F di kedua sisi lensa: siswa membandingkan letak
        // bendanya terhadap kedua titik itu untuk menduga sifat bayangannya.
        // Labelnya di bawah badan lensa supaya tidak tertimpa.
        foreach ([[-2, '2F'], [-1, 'F'], [1, 'F'], [2, '2F']] as [$arah, $nama]) {
            $x = $pusat + $arah * $fokus * $skala;

            $kanvas->garis([$x, $sumbu - 7], [$x, $sumbu + 7], Kanvas::HITAM, 2)
                ->teksPx([$x, $sumbu + 72], $nama, ['ukuran' => 13, 'tebal' => true]);
        }

        self::bentukLensa($kanvas, $jenis, $pusat, $sumbu, $isi, $garis);

        // Benda: panah tegak ke atas dari sumbu utama.
        $xBenda = $pusat - $benda * $skala;

        $kanvas
            ->panahPx([$xBenda, $sumbu], [$xBenda, $sumbu - $tinggiPx], $garis, 3.5)
            ->teksPx([$xBenda, $sumbu - $tinggiPx - 10], 'h = '.Angka::id($tinggi).' cm', [
                'ukuran' => 12, 'tebal' => true,
            ])
            ->teksPx([$pusat + $fokus * $skala, $sumbu + 90], 'f = '.Angka::id($fokus).' cm', [
                'ukuran' => 11, 'warna' => '#4b5563',
            ]);

        self::ukuran($kanvas, $xBenda, $pusat, $sumbu + 118, 's = '.Angka::id($benda).' cm');

        return $kanvas->render();
    }

    /** Lensa cembung digambar menggembung, lensa cekung menguncup di tengah. */
    protected static function bentukLensa(Kanvas $kanvas, string $jenis, float $x, float $sumbu, string $isi, string $garis): Kanvas
    {
        [$atas, $bawah] = [$sumbu - 58, $sumbu + 58];

        if ($jenis === 'cekung') {
            return $kanvas->tambah('<path d="M '.($x - 15).' '.$atas.' L '.($x + 15).' '.$atas
                .' Q '.$x.' '.$sumbu.' '.($x + 15).' '.$bawah.' L '.($x - 15).' '.$bawah
                .' Q '.$x.' '.$sumbu.' '.($x - 15).' '.$atas.' Z" fill="'.$isi.'" stroke="'
                .$garis.'" stroke-width="2.5" stroke-linejoin="round"/>');
        }

        return $kanvas->tambah('<path d="M '.$x.' '.$atas.' Q '.($x + 20).' '.$sumbu.' '.$x.' '.$bawah
            .' Q '.($x - 20).' '.$sumbu.' '.$x.' '.$atas.' Z" fill="'.$isi.'" stroke="'
            .$garis.'" stroke-width="2.5"/>');
    }

    /** Garis ukur berkepala dua beserta keterangannya. */
    protected static function ukuran(Kanvas $kanvas, float $dari, float $ke, float $y, string $nama): Kanvas
    {
        return $kanvas
            ->garis([$dari, $y - 10], [$dari, $y + 6], '#8a9199', 1.5)
            ->garis([$ke, $y - 10], [$ke, $y + 6], '#8a9199', 1.5)
            ->panahPx([$dari, $y], [$ke, $y], '#4b5563', 1.8)
            ->panahPx([$ke, $y], [$dari, $y], '#4b5563', 1.8)
            ->teksPx([($dari + $ke) / 2, $y - 8], $nama, [
                'ukuran' => 12, 'tebal' => true, 'warna' => '#4b5563',
            ]);
    }
}

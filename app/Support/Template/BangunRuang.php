<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template bangun ruang: volume dan luas permukaan tujuh bangun.
 *
 * Gambarnya memakai proyeksi miring — kedalaman digambar menyerong ke kanan
 * atas dan diperpendek, persis cara buku pelajaran menggambar kubus. Rusuk
 * yang terhalang badan bangun digambar putus-putus; tanpa itu gambar kubus
 * dan gambar segi enam sulit dibedakan.
 */
class BangunRuang extends Template
{
    public const SATUAN = ['mm', 'cm', 'dm', 'm'];

    /** @var array<string, string> */
    public const TANYA = [
        'volume' => 'Volume',
        'luas_permukaan' => 'Luas permukaan',
    ];

    /** @var array<string, string> */
    public const BENTUK = [
        'kubus' => 'Kubus',
        'balok' => 'Balok',
        'prisma_segitiga' => 'Prisma Segitiga',
        'limas_segi_empat' => 'Limas Segi Empat',
        'tabung' => 'Tabung',
        'kerucut' => 'Kerucut',
        'bola' => 'Bola',
    ];

    protected const IKON = [
        'kubus' => '<path d="M6 11 L18 11 L18 25 L6 25 Z M6 11 L12 5 L24 5 L18 11 M18 25 L24 19 L24 5"/>',
        'balok' => '<path d="M4 12 L20 12 L20 24 L4 24 Z M4 12 L10 6 L26 6 L20 12 M20 24 L26 18 L26 6"/>',
        'prisma_segitiga' => '<path d="M5 25 L13 9 L21 25 Z M13 9 L21 5 L29 21 L21 25 M5 25 L13 21 L29 21"/>',
        'limas_segi_empat' => '<path d="M16 4 L4 22 L20 26 L28 16 Z M4 22 L28 16 M16 4 L20 26"/>',
        'tabung' => '<path d="M8 8 a8 3 0 1 0 16 0 a8 3 0 1 0 -16 0 M8 8 L8 24 a8 3 0 0 0 16 0 L24 8"/>',
        'kerucut' => '<path d="M16 4 L7 24 a9 3 0 0 0 18 0 Z"/>',
        'bola' => '<circle cx="16" cy="16" r="11"/><ellipse cx="16" cy="16" rx="11" ry="4"/>',
    ];

    /** Pemendekan rusuk kedalaman pada proyeksi miring. */
    protected const DALAM = 0.45;

    public static function kode(): string
    {
        return 'bangun-ruang';
    }

    public static function nama(): string
    {
        return 'Bangun Ruang';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Kubus, balok, prisma, limas, tabung, kerucut, dan bola. '
            .'Tanya volume atau luas permukaannya.';
    }

    public static function ikon(): string
    {
        return 'bi-box';
    }

    public static function kataKunci(): array
    {
        return ['volume', 'luas permukaan', 'kubus', 'balok', 'tabung', 'kerucut', 'bola', 'limas', 'prisma'];
    }

    public static function kolom(): array
    {
        $ukuran = fn (string $nama, string $label, float $awal, array $bentuk) => [
            'nama' => $nama,
            'label' => $label,
            'jenis' => 'angka',
            'awal' => $awal,
            'min' => 0.1,
            'max' => 1000,
            'lebar' => 6,
            'tampil' => ['bentuk' => $bentuk],
        ];

        return [
            [
                'nama' => 'bentuk',
                'label' => 'Jenis bangun ruang',
                'jenis' => 'pilihan-ikon',
                'pilihan' => self::BENTUK,
                'ikon' => self::IKON,
                'awal' => 'balok',
            ],
            // Rusuk 6 membuat volume dan luas permukaan kubus kebetulan
            // sama-sama 216; angka awal yang begitu menyamarkan kekeliruan
            // guru yang salah memilih pertanyaan.
            $ukuran('rusuk', 'Panjang rusuk', 5, ['kubus']),
            $ukuran('panjang', 'Panjang', 6, ['balok']),
            $ukuran('lebar', 'Lebar', 3, ['balok']),
            $ukuran('tinggi', 'Tinggi', 4, ['balok']),
            $ukuran('alas_segitiga', 'Alas segitiga', 6, ['prisma_segitiga']),
            $ukuran('tinggi_segitiga', 'Tinggi segitiga', 4, ['prisma_segitiga']),
            $ukuran('tinggi_prisma', 'Tinggi prisma', 12, ['prisma_segitiga']),
            $ukuran('sisi_alas', 'Sisi alas', 10, ['limas_segi_empat']),
            $ukuran('tinggi_limas', 'Tinggi limas', 12, ['limas_segi_empat']),
            $ukuran('jari_jari', 'Jari-jari', 7, ['tabung', 'kerucut', 'bola']),
            $ukuran('tinggi_tabung', 'Tinggi', 10, ['tabung', 'kerucut']),
            [
                'nama' => 'satuan',
                'label' => 'Satuan',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(self::SATUAN, self::SATUAN),
                'awal' => 'cm',
                'lebar' => 6,
            ],
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'volume',
                'lebar' => 6,
            ],
            [
                'nama' => 'pi',
                'label' => 'Nilai π',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(array_keys(BangunDatar::PI), array_keys(BangunDatar::PI)),
                'awal' => '3,14',
                'lebar' => 6,
                'tampil' => ['bentuk' => ['tabung', 'kerucut', 'bola']],
            ],
            self::kolomWarna(),
        ];
    }

    protected function susun(array $isi): array
    {
        $bentuk = $isi['bentuk'];
        $satuan = $isi['satuan'];
        $tanya = $isi['tanya'];
        [$pi, $piTeks] = BangunDatar::PI[$isi['pi'] ?? '3,14'] ?? BangunDatar::PI['3,14'];

        $hitung = self::nilai($bentuk, $isi, $tanya, $pi, $piTeks);
        $benar = $hitung['nilai'];
        $satuanJawab = Angka::pangkat($satuan, $tanya === 'volume' ? 3 : 2);

        $nama = mb_strtolower(self::BENTUK[$bentuk]);
        $label = self::TANYA[$tanya];

        $lawan = self::nilai($bentuk, $isi, $tanya === 'volume' ? 'luas_permukaan' : 'volume', $pi, $piTeks)['nilai'];

        $pilihan = [['teks' => Angka::dengan($benar, $satuanJawab), 'urut' => $benar]];

        $pengecoh = self::saring($benar, array_merge(
            [$lawan],
            self::setengahJadi($bentuk, $isi, $pi),
            [$benar * 3, $benar * 2, $benar / 2, self::jumlahUkuran($isi)],
        ));

        foreach ($pengecoh as $n) {
            $pilihan[] = ['teks' => Angka::dengan($n, $satuanJawab), 'urut' => $n];
        }

        return [
            'judul' => self::BENTUK[$bentuk],
            'teks' => "Perhatikan gambar {$nama} berikut. Berapakah ".mb_strtolower($label)." {$nama} tersebut?",
            'pernyataan' => "Perhatikan gambar {$nama} berikut. {$label} {$nama} tersebut adalah %s.",
            'svg' => self::svg($bentuk, $isi, $satuan, $isi['warna'] ?? 'biru'),
            'keterangan' => self::BENTUK[$bentuk].' dengan '.$this->daftarUkuran($isi, $satuan).'.',
            'pilihan' => $pilihan,
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => $label.' '.$nama.' = '.$hitung['simbol']
                .' = '.$hitung['angka'].' = '.$pilihan[0]['teks'],
        ];
    }

    /**
     * Hasil antara yang memang muncul di tengah pengerjaan — luas satu sisi,
     * luas alas, luas selimut — dan karena itu sering dikira jawaban akhir.
     *
     * Pengecoh semacam ini lebih berguna daripada angka acak: guru bisa
     * melihat dari sebaran jawaban di laporan bahwa siswa berhenti satu
     * langkah terlalu awal, bukan sekadar menebak.
     *
     * @return array<int, float>
     */
    protected static function setengahJadi(string $bentuk, array $isi, float $pi): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return match ($bentuk) {
            'kubus' => [$n('rusuk') ** 2, 4 * $n('rusuk') ** 2],
            'balok' => [
                $n('panjang') * $n('lebar'),
                $n('panjang') * $n('tinggi'),
                $n('lebar') * $n('tinggi'),
            ],
            'prisma_segitiga' => [
                $n('alas_segitiga') * $n('tinggi_segitiga') / 2,
                $n('alas_segitiga') * $n('tinggi_segitiga') * $n('tinggi_prisma'),
            ],
            'limas_segi_empat' => [$n('sisi_alas') ** 2, $n('sisi_alas') ** 2 * $n('tinggi_limas')],
            'tabung' => [$pi * $n('jari_jari') ** 2, 2 * $pi * $n('jari_jari') * $n('tinggi_tabung')],
            'kerucut' => [$pi * $n('jari_jari') ** 2, $pi * $n('jari_jari') ** 2 * $n('tinggi_tabung')],
            'bola' => [$pi * $n('jari_jari') ** 2, 4 * $pi * $n('jari_jari') ** 3],
            default => [],
        };
    }

    protected function daftarUkuran(array $isi, string $satuan): string
    {
        $ukuran = [];

        foreach (self::kolomAktif($isi) as $kolom) {
            if ($kolom['jenis'] === 'angka') {
                $ukuran[] = mb_strtolower($kolom['label']).' '.Angka::dengan((float) $isi[$kolom['nama']], $satuan);
            }
        }

        return implode(', ', $ukuran);
    }

    // =====================================================================
    // Hitungan
    // =====================================================================

    /**
     * Panjang yang dihitung sendiri: kaki segitiga pada prisma, tinggi sisi
     * tegak limas, dan garis pelukis kerucut.
     *
     * @return array<string, float>
     */
    public static function turunan(string $bentuk, array $isi): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return match ($bentuk) {
            'prisma_segitiga' => ['kaki' => hypot($n('alas_segitiga') / 2, $n('tinggi_segitiga'))],
            'limas_segi_empat' => ['tinggi_sisi_tegak' => hypot($n('tinggi_limas'), $n('sisi_alas') / 2)],
            'kerucut' => ['garis_pelukis' => hypot($n('jari_jari'), $n('tinggi_tabung'))],
            default => [],
        };
    }

    /** @return array{nilai: float, simbol: string, angka: string} */
    public static function nilai(string $bentuk, array $isi, string $tanya, float $pi = 3.14, ?string $piTeks = null): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);
        $d = self::turunan($bentuk, $isi);
        $f = fn (float $x) => Angka::id($x);
        $p = $piTeks ?? Angka::id($pi);

        [$nilai, $simbol, $angka] = match ("{$bentuk}.{$tanya}") {
            'kubus.volume' => [
                $n('rusuk') ** 3, 's × s × s', $f($n('rusuk')).' × '.$f($n('rusuk')).' × '.$f($n('rusuk')),
            ],
            'kubus.luas_permukaan' => [
                6 * $n('rusuk') ** 2, '6 × s × s', '6 × '.$f($n('rusuk')).' × '.$f($n('rusuk')),
            ],
            'balok.volume' => [
                $n('panjang') * $n('lebar') * $n('tinggi'),
                'p × l × t',
                $f($n('panjang')).' × '.$f($n('lebar')).' × '.$f($n('tinggi')),
            ],
            'balok.luas_permukaan' => [
                2 * ($n('panjang') * $n('lebar') + $n('panjang') * $n('tinggi') + $n('lebar') * $n('tinggi')),
                '2 × (p×l + p×t + l×t)',
                '2 × ('.$f($n('panjang') * $n('lebar')).' + '.$f($n('panjang') * $n('tinggi'))
                    .' + '.$f($n('lebar') * $n('tinggi')).')',
            ],
            'prisma_segitiga.volume' => [
                $n('alas_segitiga') * $n('tinggi_segitiga') / 2 * $n('tinggi_prisma'),
                'luas alas × tinggi prisma',
                '½ × '.$f($n('alas_segitiga')).' × '.$f($n('tinggi_segitiga')).' × '.$f($n('tinggi_prisma')),
            ],
            'prisma_segitiga.luas_permukaan' => [
                2 * ($n('alas_segitiga') * $n('tinggi_segitiga') / 2)
                    + ($n('alas_segitiga') + 2 * $d['kaki']) * $n('tinggi_prisma'),
                '(2 × luas alas) + (keliling alas × tinggi prisma)',
                '(2 × '.$f($n('alas_segitiga') * $n('tinggi_segitiga') / 2).') + ('
                    .$f($n('alas_segitiga') + 2 * $d['kaki']).' × '.$f($n('tinggi_prisma')).')',
            ],
            'limas_segi_empat.volume' => [
                $n('sisi_alas') ** 2 * $n('tinggi_limas') / 3,
                '⅓ × luas alas × t',
                '⅓ × '.$f($n('sisi_alas') ** 2).' × '.$f($n('tinggi_limas')),
            ],
            'limas_segi_empat.luas_permukaan' => [
                $n('sisi_alas') ** 2 + 2 * $n('sisi_alas') * $d['tinggi_sisi_tegak'],
                'luas alas + (4 × luas segitiga sisi tegak)',
                $f($n('sisi_alas') ** 2).' + (4 × ½ × '.$f($n('sisi_alas')).' × '.$f($d['tinggi_sisi_tegak']).')',
            ],
            'tabung.volume' => [
                $pi * $n('jari_jari') ** 2 * $n('tinggi_tabung'),
                'π × r × r × t',
                $p.' × '.$f($n('jari_jari')).' × '.$f($n('jari_jari')).' × '.$f($n('tinggi_tabung')),
            ],
            'tabung.luas_permukaan' => [
                2 * $pi * $n('jari_jari') * ($n('jari_jari') + $n('tinggi_tabung')),
                '2 × π × r × (r + t)',
                '2 × '.$p.' × '.$f($n('jari_jari')).' × ('.$f($n('jari_jari')).' + '.$f($n('tinggi_tabung')).')',
            ],
            'kerucut.volume' => [
                $pi * $n('jari_jari') ** 2 * $n('tinggi_tabung') / 3,
                '⅓ × π × r × r × t',
                '⅓ × '.$p.' × '.$f($n('jari_jari')).' × '.$f($n('jari_jari')).' × '.$f($n('tinggi_tabung')),
            ],
            'kerucut.luas_permukaan' => [
                $pi * $n('jari_jari') * ($n('jari_jari') + $d['garis_pelukis']),
                'π × r × (r + s)',
                $p.' × '.$f($n('jari_jari')).' × ('.$f($n('jari_jari')).' + '.$f($d['garis_pelukis']).')',
            ],
            'bola.volume' => [
                4 / 3 * $pi * $n('jari_jari') ** 3,
                '⁴⁄₃ × π × r × r × r',
                '⁴⁄₃ × '.$p.' × '.$f($n('jari_jari') ** 3),
            ],
            'bola.luas_permukaan' => [
                4 * $pi * $n('jari_jari') ** 2,
                '4 × π × r × r',
                '4 × '.$p.' × '.$f($n('jari_jari')).' × '.$f($n('jari_jari')),
            ],
            default => [0.0, '', ''],
        };

        return ['nilai' => round($nilai, 2), 'simbol' => $simbol, 'angka' => $angka];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * @param  bool  $berlabel  Ukuran ikut ditulis pada rusuknya. Dimatikan
     *                          saat gambar hanya dipakai untuk mengenali
     *                          bentuk, bukan untuk dihitung.
     */
    public static function svg(string $bentuk, array $isi, string $satuan, string $warna, bool $berlabel = true): string
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);
        [$isiWarna, $garis] = self::WARNA[$warna] ?? self::WARNA['biru'];
        $l = fn (float $x) => $berlabel ? Angka::dengan($x, $satuan) : '';

        return match ($bentuk) {
            'kubus' => self::balok($n('rusuk'), $n('rusuk'), $n('rusuk'), $isiWarna, $garis, [
                'bawah' => $l($n('rusuk')),
            ]),
            'balok' => self::balok($n('panjang'), $n('lebar'), $n('tinggi'), $isiWarna, $garis, [
                'bawah' => $l($n('panjang')),
                'kiri' => $l($n('tinggi')),
                'dalam' => $l($n('lebar')),
            ]),
            'prisma_segitiga' => self::prisma(
                $n('alas_segitiga'), $n('tinggi_segitiga'), $n('tinggi_prisma'), $isiWarna, $garis,
                $l($n('alas_segitiga')), $l($n('tinggi_segitiga')), $l($n('tinggi_prisma')),
            ),
            'limas_segi_empat' => self::limas(
                $n('sisi_alas'), $n('tinggi_limas'), $isiWarna, $garis,
                $l($n('sisi_alas')), $l($n('tinggi_limas')),
            ),
            'tabung' => self::tabung($n('jari_jari'), $n('tinggi_tabung'), $isiWarna, $garis,
                $l($n('jari_jari')), $l($n('tinggi_tabung'))),
            'kerucut' => self::kerucut($n('jari_jari'), $n('tinggi_tabung'), $isiWarna, $garis,
                $l($n('jari_jari')), $l($n('tinggi_tabung'))),
            'bola' => self::bola($n('jari_jari'), $isiWarna, $garis, $l($n('jari_jari'))),
            default => (new Kanvas)->render(),
        };
    }

    /**
     * Proyeksi titik ruang (x ke kanan, y ke atas, z ke belakang) menjadi
     * titik gambar dua dimensi.
     */
    protected static function proyeksi(float $x, float $y, float $z): array
    {
        return [$x + self::DALAM * $z, -$y - self::DALAM * $z];
    }

    /** Tulisan di tengah sebuah ruas, digeser menjauh sejauh beberapa piksel. */
    protected static function labelRuas(Kanvas $k, array $a, array $b, array $geser, string $teks): void
    {
        if ($teks === '') {
            return;
        }

        $pa = $k->px($a);
        $pb = $k->px($b);

        $k->teksPx([($pa[0] + $pb[0]) / 2 + $geser[0], ($pa[1] + $pb[1]) / 2 + $geser[1]], $teks, [
            'anchor' => $geser[0] > 4 ? 'start' : ($geser[0] < -4 ? 'end' : 'middle'),
        ]);
    }

    protected static function balok(float $p, float $l, float $t, string $isi, string $garis, array $label): string
    {
        $v = fn (float $x, float $y, float $z) => self::proyeksi($x, $y, $z);

        // Delapan titik sudut: huruf depan d/b = depan/belakang,
        // lalu bawah/atas dan kiri/kanan.
        $dbk = $v(0, 0, 0);
        $dbn = $v($p, 0, 0);
        $dak = $v(0, $t, 0);
        $dan = $v($p, $t, 0);
        $bbk = $v(0, 0, $l);
        $bbn = $v($p, 0, $l);
        $bak = $v(0, $t, $l);
        $ban = $v($p, $t, $l);

        $kanvas = new Kanvas;
        $kanvas->pas([$dbk, $dbn, $dak, $dan, $bbk, $bbn, $bak, $ban]);

        $kanvas->poligon([$dbk, $dbn, $dan, $dak], $isi, $garis)        // sisi depan
            ->poligon([$dak, $dan, $ban, $bak], $isi, $garis)           // sisi atas
            ->poligon([$dbn, $bbn, $ban, $dan], $isi, $garis);          // sisi kanan

        // Tiga rusuk yang terhalang badan bangun, semuanya bertemu di titik
        // sudut belakang-bawah-kiri.
        foreach ([$bbn, $bak, $dbk] as $ujung) {
            $kanvas->garis($bbk, $ujung, $garis, 1.5, true);
        }

        if (isset($label['bawah'])) {
            self::labelRuas($kanvas, $dbk, $dbn, [0, 20], $label['bawah']);
        }
        if (isset($label['kiri'])) {
            self::labelRuas($kanvas, $dbk, $dak, [-10, 4], $label['kiri']);
        }
        if (isset($label['dalam'])) {
            self::labelRuas($kanvas, $dbn, $bbn, [14, 10], $label['dalam']);
        }

        return $kanvas->render();
    }

    protected static function prisma(float $a, float $ts, float $tp, string $isi, string $garis, string $la, string $lts, string $ltp): string
    {
        $v = fn (float $x, float $y, float $z) => self::proyeksi($x, $y, $z);

        $depanKiri = $v(0, 0, 0);
        $depanKanan = $v($a, 0, 0);
        $depanPuncak = $v($a / 2, $ts, 0);
        $belakangKiri = $v(0, 0, $tp);
        $belakangKanan = $v($a, 0, $tp);
        $belakangPuncak = $v($a / 2, $ts, $tp);

        $kanvas = new Kanvas;
        $kanvas->pas([$depanKiri, $depanKanan, $depanPuncak, $belakangKiri, $belakangKanan, $belakangPuncak]);

        // Sisi tegak kanan dan alas digambar lebih dulu, lalu segitiga depan
        // di atasnya, supaya rusuk depan tidak tertimpa bidang di belakangnya.
        $kanvas->poligon([$depanKanan, $belakangKanan, $belakangPuncak, $depanPuncak], $isi, $garis)
            ->poligon([$depanKiri, $depanKanan, $belakangKanan, $belakangKiri], $isi, $garis)
            ->poligon([$depanKiri, $depanKanan, $depanPuncak], $isi, $garis)
            ->garis($depanPuncak, $belakangPuncak, $garis, 2)
            ->garis($belakangKiri, $belakangPuncak, $garis, 1.5, true)
            ->garis($belakangKiri, $depanKiri, $garis, 1.5, true)
            ->garis([$a / 2, 0], $depanPuncak, $garis, 1.5, true);

        self::labelRuas($kanvas, $depanKiri, $depanKanan, [0, 20], $la);
        self::labelRuas($kanvas, [$a / 2, 0], $depanPuncak, [8, 4], $lts);
        self::labelRuas($kanvas, $depanKanan, $belakangKanan, [16, 8], $ltp);

        return $kanvas->render();
    }

    protected static function limas(float $s, float $t, string $isi, string $garis, string $ls, string $lt): string
    {
        $v = fn (float $x, float $y, float $z) => self::proyeksi($x, $y, $z);

        $depanKiri = $v(0, 0, 0);
        $depanKanan = $v($s, 0, 0);
        $belakangKanan = $v($s, 0, $s);
        $belakangKiri = $v(0, 0, $s);
        $puncak = $v($s / 2, $t, $s / 2);
        $pusatAlas = $v($s / 2, 0, $s / 2);

        $kanvas = new Kanvas;
        $kanvas->pas([$depanKiri, $depanKanan, $belakangKanan, $belakangKiri, $puncak]);

        $kanvas->poligon([$depanKiri, $depanKanan, $belakangKanan, $belakangKiri], $isi, $garis)
            ->poligon([$depanKiri, $depanKanan, $puncak], $isi, $garis)
            ->poligon([$depanKanan, $belakangKanan, $puncak], $isi, $garis)
            ->garis($belakangKiri, $puncak, $garis, 1.5, true)
            ->garis($puncak, $pusatAlas, $garis, 1.5, true);

        self::labelRuas($kanvas, $depanKiri, $depanKanan, [0, 20], $ls);
        self::labelRuas($kanvas, $puncak, $pusatAlas, [10, 4], $lt);

        return $kanvas->render();
    }

    protected static function tabung(float $r, float $t, string $isi, string $garis, string $lr, string $lt): string
    {
        $ry = $r * self::DALAM;

        $kanvas = new Kanvas;
        $kanvas->pas([[-$r, -$ry], [$r, $t + $ry]]);

        // Alas digambar utuh lebih dulu; badan tabung yang digambar sesudahnya
        // menutup separuh belakangnya, menyisakan lengkung depan saja.
        $kanvas->elips([0, $t], $r, $ry, $isi, $garis)
            ->poligon([[-$r, 0], [$r, 0], [$r, $t], [-$r, $t]], $isi, 'none', 0)
            ->garis([-$r, 0], [-$r, $t], $garis, 2)
            ->garis([$r, 0], [$r, $t], $garis, 2)
            ->elips([0, 0], $r, $ry, $isi, $garis)
            ->garis([0, 0], [$r, 0], $garis, 1.5, true);

        self::labelRuas($kanvas, [0, 0], [$r, 0], [0, -8], $lr);
        self::labelRuas($kanvas, [$r, 0], [$r, $t], [12, 4], $lt);

        return $kanvas->render();
    }

    protected static function kerucut(float $r, float $t, string $isi, string $garis, string $lr, string $lt): string
    {
        $ry = $r * self::DALAM;

        $kanvas = new Kanvas;
        $kanvas->pas([[-$r, -$ry], [$r, $t + $ry]]);

        $kanvas->poligon([[-$r, $t], [$r, $t], [0, 0]], $isi, $garis)
            ->elips([0, $t], $r, $ry, $isi, $garis)
            ->garis([0, $t], [$r, $t], $garis, 1.5, true)
            ->garis([0, 0], [0, $t], $garis, 1.5, true);

        self::labelRuas($kanvas, [0, $t], [$r, $t], [0, 16], $lr);
        self::labelRuas($kanvas, [0, 0], [0, $t], [8, 4], $lt);

        return $kanvas->render();
    }

    protected static function bola(float $r, string $isi, string $garis, string $lr): string
    {
        $kanvas = new Kanvas;
        $kanvas->pas([[-$r, -$r], [$r, $r]]);

        $kanvas->lingkaran([0, 0], $r, $isi, $garis)
            ->elips([0, 0], $r, $r * self::DALAM, 'none', $garis, 1.5, true)
            ->garis([0, 0], [$r, 0], $garis, 1.5, true);

        self::labelRuas($kanvas, [0, 0], [$r, 0], [0, -8], $lr);

        return $kanvas->render();
    }
}

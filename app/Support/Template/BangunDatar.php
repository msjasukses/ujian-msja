<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template bangun datar: luas dan keliling sembilan bangun.
 *
 * Satu berkas untuk tiga hal yang harus selalu sepakat — ukuran apa yang
 * diminta tiap bangun, bagaimana luas dan kelilingnya dihitung, dan seperti
 * apa gambarnya. Begitu ketiganya terpisah, mudah sekali gambar memuat angka
 * yang tidak dipakai rumus: soalnya tampak benar, kuncinya yang meleset.
 *
 * Ukuran turunan — sisi miring, kaki trapesium, sisi belah ketupat — sengaja
 * tidak diminta dari guru melainkan dihitung di turunan(), jadi tidak ada
 * peluang gambar bertentangan dengan kunci jawabannya.
 */
class BangunDatar extends Template
{
    public const SATUAN = ['mm', 'cm', 'dm', 'm', 'km'];

    /** @var array<string, string> */
    public const TANYA = [
        'luas' => 'Luas',
        'keliling' => 'Keliling',
    ];

    /** @var array<string, array{0: float, 1: string}> */
    public const PI = [
        '3,14' => [3.14, '3,14'],
        '22/7' => [22 / 7, '22/7'],
    ];

    /** @var array<string, string> */
    public const BENTUK = [
        'persegi' => 'Persegi',
        'persegi_panjang' => 'Persegi Panjang',
        'segitiga_siku' => 'Segitiga Siku-siku',
        'segitiga_sama_kaki' => 'Segitiga Sama Kaki',
        'jajar_genjang' => 'Jajar Genjang',
        'trapesium_sama_kaki' => 'Trapesium Sama Kaki',
        'belah_ketupat' => 'Belah Ketupat',
        'layang_layang' => 'Layang-layang',
        'lingkaran' => 'Lingkaran',
    ];

    /** Siluet kecil tiap bangun untuk tombol pemilih di formulir template. */
    protected const IKON = [
        'persegi' => '<rect x="8" y="8" width="16" height="16"/>',
        'persegi_panjang' => '<rect x="3" y="10" width="26" height="12"/>',
        'segitiga_siku' => '<polygon points="5,27 5,5 27,27"/>',
        'segitiga_sama_kaki' => '<polygon points="16,5 29,27 3,27"/>',
        'jajar_genjang' => '<polygon points="11,8 30,8 21,24 2,24"/>',
        'trapesium_sama_kaki' => '<polygon points="9,9 23,9 29,24 3,24"/>',
        'belah_ketupat' => '<polygon points="16,3 28,16 16,29 4,16"/>',
        'layang_layang' => '<polygon points="16,2 27,13 16,30 5,13"/>',
        'lingkaran' => '<circle cx="16" cy="16" r="12"/>',
    ];

    /** Jarak label dari garis yang dilabelinya. */
    protected const JARAK_LABEL = 16;

    public static function kode(): string
    {
        return 'bangun-datar';
    }

    public static function nama(): string
    {
        return 'Bangun Datar';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Persegi, persegi panjang, segitiga, jajar genjang, trapesium, belah ketupat, '
            .'layang-layang, dan lingkaran. Tanya luas atau kelilingnya.';
    }

    public static function ikon(): string
    {
        return 'bi-pentagon';
    }

    public static function kataKunci(): array
    {
        return ['luas', 'keliling', 'geometri', 'persegi', 'segitiga', 'lingkaran', 'trapesium'];
    }

    public static function kolom(): array
    {
        $ukuran = fn (string $nama, string $label, float $awal, array $bentuk) => [
            'nama' => $nama,
            'label' => $label,
            'jenis' => 'angka',
            'awal' => $awal,
            'min' => 0.1,
            'max' => 10000,
            'lebar' => 6,
            'tampil' => ['bentuk' => $bentuk],
        ];

        $segitiga = ['segitiga_siku', 'segitiga_sama_kaki', 'jajar_genjang'];

        return [
            [
                'nama' => 'bentuk',
                'label' => 'Jenis bangun datar',
                'jenis' => 'pilihan-ikon',
                'pilihan' => self::BENTUK,
                'ikon' => self::IKON,
                'awal' => 'persegi_panjang',
            ],
            $ukuran('sisi', 'Sisi', 8, ['persegi']),
            $ukuran('panjang', 'Panjang', 8, ['persegi_panjang']),
            $ukuran('lebar', 'Lebar', 5, ['persegi_panjang']),
            $ukuran('alas', 'Alas', 8, $segitiga),
            $ukuran('sisi_atas', 'Sisi atas', 6, ['trapesium_sama_kaki']),
            $ukuran('sisi_bawah', 'Sisi bawah', 12, ['trapesium_sama_kaki']),
            $ukuran('tinggi', 'Tinggi', 6, [...$segitiga, 'trapesium_sama_kaki']),
            $ukuran('sisi_miring', 'Sisi miring', 8, ['jajar_genjang']),
            $ukuran('diagonal_1', 'Diagonal mendatar', 12, ['belah_ketupat', 'layang_layang']),
            $ukuran('diagonal_2', 'Diagonal tegak', 16, ['belah_ketupat']),
            $ukuran('bagian_atas', 'Diagonal tegak bagian atas', 8, ['layang_layang']),
            $ukuran('bagian_bawah', 'Diagonal tegak bagian bawah', 16, ['layang_layang']),
            $ukuran('jari_jari', 'Jari-jari', 7, ['lingkaran']),
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
                'awal' => 'luas',
                'lebar' => 6,
            ],
            [
                'nama' => 'pi',
                'label' => 'Nilai π',
                'jenis' => 'pilihan',
                'pilihan' => array_combine(array_keys(self::PI), array_keys(self::PI)),
                'awal' => '3,14',
                'lebar' => 6,
                'tampil' => ['bentuk' => ['lingkaran']],
            ],
            self::kolomWarna(),
        ];
    }

    public static function periksa(array $isi): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return match ($isi['bentuk'] ?? '') {
            'jajar_genjang' => $n('sisi_miring') <= $n('tinggi')
                ? ['sisi_miring' => 'Sisi miring harus lebih panjang daripada tinggi.']
                : [],
            'trapesium_sama_kaki' => $n('sisi_bawah') <= $n('sisi_atas')
                ? ['sisi_bawah' => 'Sisi bawah harus lebih panjang daripada sisi atas.']
                : [],
            default => [],
        };
    }

    protected function susun(array $isi): array
    {
        $bentuk = $isi['bentuk'];
        $satuan = $isi['satuan'];
        $tanya = $isi['tanya'];
        [$pi, $piTeks] = self::PI[$isi['pi'] ?? '3,14'] ?? self::PI['3,14'];

        $hitung = self::nilai($bentuk, $isi, $tanya, $pi, $piTeks);
        $benar = $hitung['nilai'];
        $satuanJawab = Angka::pangkat($satuan, $tanya === 'luas' ? 2 : 1);

        $nama = mb_strtolower(self::BENTUK[$bentuk]);
        $label = self::TANYA[$tanya];

        $pilihan = [['teks' => Angka::dengan($benar, $satuanJawab), 'urut' => $benar]];

        foreach ($this->pengecoh($bentuk, $isi, $tanya, $benar, $pi, $piTeks) as $n) {
            $pilihan[] = ['teks' => Angka::dengan($n, $satuanJawab), 'urut' => $n];
        }

        return [
            'judul' => self::BENTUK[$bentuk],
            'teks' => "Perhatikan gambar {$nama} berikut. Berapakah ".mb_strtolower($label)." {$nama} tersebut?",
            'pernyataan' => "Perhatikan gambar {$nama} berikut. {$label} {$nama} tersebut adalah %s.",
            'svg' => self::svg($bentuk, $isi, $satuan, $isi['warna'] ?? 'biru', $tanya),
            'keterangan' => $this->keterangan($bentuk, $isi, $satuan),
            'pilihan' => $pilihan,
            'kata_kunci' => Angka::id($benar),
            'pembahasan' => $this->pembahasan($bentuk, $isi, $tanya, $satuan, $hitung, $pilihan[0]['teks']),
        ];
    }

    /** @return array<int, float> */
    protected function pengecoh(string $bentuk, array $isi, string $tanya, float $benar, float $pi, string $piTeks): array
    {
        return self::pengecohUmum(
            $benar,
            self::nilai($bentuk, $isi, $tanya === 'luas' ? 'keliling' : 'luas', $pi, $piTeks)['nilai'],
            self::jumlahUkuran($isi),
        );
    }

    /**
     * Pembahasan yang menampilkan rumusnya dulu, baru penggantian angkanya.
     * Pada soal keliling, panjang sisi yang harus dicari sendiri — sisi
     * miring, kaki trapesium — ditulis lebih dahulu sebagai langkah terpisah.
     */
    protected function pembahasan(string $bentuk, array $isi, string $tanya, string $satuan, array $hitung, string $jawaban): string
    {
        $baris = [];

        if ($tanya === 'keliling') {
            foreach (self::turunan($bentuk, $isi) as $nama => $nilai) {
                // "geser" hanya keperluan menggambar jajar genjang, bukan
                // ukuran yang punya arti bagi siswa.
                if ($nama === 'geser') {
                    continue;
                }

                $baris[] = ucfirst(str_replace('_', ' ', $nama)).' = '.Angka::dengan($nilai, $satuan);
            }
        }

        $baris[] = self::TANYA[$tanya].' '.mb_strtolower(self::BENTUK[$bentuk])
            .' = '.$hitung['simbol']
            .' = '.$hitung['angka']
            .' = '.$jawaban;

        return implode('<br>', $baris);
    }

    /** Keterangan gambar, dibaca guru saat memeriksa butirnya sekali lagi. */
    protected function keterangan(string $bentuk, array $isi, string $satuan): string
    {
        $ukuran = [];

        foreach (self::kolomAktif($isi) as $kolom) {
            if ($kolom['jenis'] === 'angka') {
                $ukuran[] = mb_strtolower($kolom['label']).' '.Angka::dengan((float) $isi[$kolom['nama']], $satuan);
            }
        }

        return self::BENTUK[$bentuk].' dengan '.implode(', ', $ukuran).'.';
    }

    // =====================================================================
    // Hitungan
    // =====================================================================

    /**
     * Panjang yang tidak diminta dari guru melainkan dihitung — sisi miring
     * segitiga siku-siku, kaki trapesium, sisi belah ketupat, dan seterusnya.
     *
     * @return array<string, float>
     */
    public static function turunan(string $bentuk, array $isi): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return match ($bentuk) {
            'segitiga_siku' => ['sisi_miring' => hypot($n('alas'), $n('tinggi'))],
            'segitiga_sama_kaki' => ['kaki' => hypot($n('alas') / 2, $n('tinggi'))],
            'trapesium_sama_kaki' => ['kaki' => hypot(($n('sisi_bawah') - $n('sisi_atas')) / 2, $n('tinggi'))],
            'belah_ketupat' => ['sisi' => hypot($n('diagonal_1') / 2, $n('diagonal_2') / 2)],
            'layang_layang' => [
                'sisi_pendek' => hypot($n('diagonal_1') / 2, $n('bagian_atas')),
                'sisi_panjang' => hypot($n('diagonal_1') / 2, $n('bagian_bawah')),
            ],
            // Pergeseran puncak jajar genjang terhadap alasnya; dari sinilah
            // gambar tahu sejauh apa sisi miringnya harus condong.
            'jajar_genjang' => ['geser' => sqrt(max(0, $n('sisi_miring') ** 2 - $n('tinggi') ** 2))],
            default => [],
        };
    }

    /**
     * Luas atau keliling sebuah bangun, lengkap dengan bentuk rumus dan
     * penggantian angkanya — ketiganya dihitung sekaligus agar pembahasan
     * yang tertulis di soal tidak mungkin berbeda dari kunci jawabannya.
     *
     * @return array{nilai: float, simbol: string, angka: string}
     */
    public static function nilai(string $bentuk, array $isi, string $tanya, float $pi = 3.14, ?string $piTeks = null): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);
        $d = self::turunan($bentuk, $isi);
        $f = fn (float $x) => Angka::id($x);
        $p = $piTeks ?? Angka::id($pi);

        [$nilai, $simbol, $angka] = match ("{$bentuk}.{$tanya}") {
            'persegi.luas' => [
                $n('sisi') ** 2, 's × s', $f($n('sisi')).' × '.$f($n('sisi')),
            ],
            'persegi.keliling' => [
                4 * $n('sisi'), '4 × s', '4 × '.$f($n('sisi')),
            ],
            'persegi_panjang.luas' => [
                $n('panjang') * $n('lebar'), 'p × l', $f($n('panjang')).' × '.$f($n('lebar')),
            ],
            'persegi_panjang.keliling' => [
                2 * ($n('panjang') + $n('lebar')),
                '2 × (p + l)',
                '2 × ('.$f($n('panjang')).' + '.$f($n('lebar')).')',
            ],
            'segitiga_siku.luas', 'segitiga_sama_kaki.luas' => [
                $n('alas') * $n('tinggi') / 2, '½ × a × t', '½ × '.$f($n('alas')).' × '.$f($n('tinggi')),
            ],
            'segitiga_siku.keliling' => [
                $n('alas') + $n('tinggi') + $d['sisi_miring'],
                'a + t + sisi miring',
                $f($n('alas')).' + '.$f($n('tinggi')).' + '.$f($d['sisi_miring']),
            ],
            'segitiga_sama_kaki.keliling' => [
                $n('alas') + 2 * $d['kaki'],
                'a + 2 × kaki',
                $f($n('alas')).' + 2 × '.$f($d['kaki']),
            ],
            'jajar_genjang.luas' => [
                $n('alas') * $n('tinggi'), 'a × t', $f($n('alas')).' × '.$f($n('tinggi')),
            ],
            'jajar_genjang.keliling' => [
                2 * ($n('alas') + $n('sisi_miring')),
                '2 × (a + sisi miring)',
                '2 × ('.$f($n('alas')).' + '.$f($n('sisi_miring')).')',
            ],
            'trapesium_sama_kaki.luas' => [
                ($n('sisi_atas') + $n('sisi_bawah')) * $n('tinggi') / 2,
                '½ × (sisi atas + sisi bawah) × t',
                '½ × ('.$f($n('sisi_atas')).' + '.$f($n('sisi_bawah')).') × '.$f($n('tinggi')),
            ],
            'trapesium_sama_kaki.keliling' => [
                $n('sisi_atas') + $n('sisi_bawah') + 2 * $d['kaki'],
                'sisi atas + sisi bawah + 2 × kaki',
                $f($n('sisi_atas')).' + '.$f($n('sisi_bawah')).' + 2 × '.$f($d['kaki']),
            ],
            'belah_ketupat.luas', 'layang_layang.luas' => [
                $n('diagonal_1') * self::diagonalTegak($bentuk, $isi) / 2,
                '½ × d₁ × d₂',
                '½ × '.$f($n('diagonal_1')).' × '.$f(self::diagonalTegak($bentuk, $isi)),
            ],
            'belah_ketupat.keliling' => [
                4 * $d['sisi'], '4 × sisi', '4 × '.$f($d['sisi']),
            ],
            'layang_layang.keliling' => [
                2 * ($d['sisi_pendek'] + $d['sisi_panjang']),
                '2 × (sisi pendek + sisi panjang)',
                '2 × ('.$f($d['sisi_pendek']).' + '.$f($d['sisi_panjang']).')',
            ],
            'lingkaran.luas' => [
                $pi * $n('jari_jari') ** 2,
                'π × r × r',
                $p.' × '.$f($n('jari_jari')).' × '.$f($n('jari_jari')),
            ],
            'lingkaran.keliling' => [
                2 * $pi * $n('jari_jari'),
                '2 × π × r',
                '2 × '.$p.' × '.$f($n('jari_jari')),
            ],
            default => [0.0, '', ''],
        };

        return ['nilai' => round($nilai, 2), 'simbol' => $simbol, 'angka' => $angka];
    }

    /** Diagonal tegak layang-layang adalah jumlah bagian atas dan bawahnya. */
    protected static function diagonalTegak(string $bentuk, array $isi): float
    {
        return $bentuk === 'layang_layang'
            ? (float) ($isi['bagian_atas'] ?? 0) + (float) ($isi['bagian_bawah'] ?? 0)
            : (float) ($isi['diagonal_2'] ?? 0);
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Gambar bangun dalam bentuk SVG.
     *
     * Panjang sisi yang semestinya dihitung sendiri oleh siswa — sisi miring,
     * kaki, sisi belah ketupat — hanya ikut tertulis saat yang ditanyakan
     * keliling. Pada soal luas angka itu tidak diperlukan, dan mencantumkannya
     * justru membocorkan jalan pintas.
     */
    public static function svg(string $bentuk, array $isi, string $satuan, string $warna, string $tanya): string
    {
        $rencana = self::rencana($bentuk, $isi, $satuan, $tanya);
        [$isiWarna, $garis] = self::WARNA[$warna] ?? self::WARNA['biru'];

        $kanvas = new Kanvas;
        $kanvas->pas(self::jangkauan($rencana));

        if ($lingkaran = $rencana['lingkaran']) {
            $kanvas->lingkaran($lingkaran['pusat'], $lingkaran['jari'], $isiWarna, $garis);
        }

        if ($rencana['titik']) {
            $kanvas->poligon($rencana['titik'], $isiWarna, $garis);
        }

        foreach ($rencana['putus'] as $ruas) {
            $kanvas->garis($ruas[0], $ruas[1], $garis, 1.5, true);
        }

        foreach ($rencana['siku'] as $siku) {
            $kanvas->siku($siku['di'], $siku['arah1'], $siku['arah2'], $garis);
        }

        $titik = array_map($kanvas->px(...), $rencana['titik']);
        $pusat = $lingkaran ? $kanvas->px($lingkaran['pusat']) : self::pusat($titik);

        foreach ($rencana['label'] as $label) {
            // Label sisi ditaruh di luar bangun: arahnya ditentukan dengan
            // mendorong titik tengah sisi menjauh dari pusat bangun, jadi
            // satu aturan ini melayani sisi tegak, mendatar, maupun miring.
            if (isset($label['sisi'])) {
                [$i, $j] = $label['sisi'];
                $tengah = [($titik[$i][0] + $titik[$j][0]) / 2, ($titik[$i][1] + $titik[$j][1]) / 2];
                $arah = Kanvas::searah([$tengah[0] - $pusat[0], $tengah[1] - $pusat[1]]);
            } else {
                $tengah = $kanvas->px($label['di']);
                $arah = Kanvas::searah($label['arah']);
            }

            // Teks SVG berpatokan pada garis alasnya, bukan tengahnya; tanpa
            // geseran tegak ini label di bawah bangun terlihat menempel ke
            // garis, sedangkan label di atasnya menggantung terlalu jauh.
            $kanvas->teksPx([
                $tengah[0] + $arah[0] * self::JARAK_LABEL,
                $tengah[1] + $arah[1] * self::JARAK_LABEL + ($arah[1] > 0.35 ? 10 : ($arah[1] < -0.35 ? -2 : 4)),
            ], $label['teks'], [
                'anchor' => $arah[0] > 0.35 ? 'start' : ($arah[0] < -0.35 ? 'end' : 'middle'),
            ]);
        }

        return $kanvas->render();
    }

    /**
     * Semua titik yang harus muat di dalam kanvas, termasuk kotak pembatas
     * lingkaran yang tidak punya titik sudut.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function jangkauan(array $rencana): array
    {
        $titik = $rencana['titik'];

        foreach ($rencana['putus'] as $ruas) {
            $titik = array_merge($titik, $ruas);
        }

        if ($rencana['lingkaran']) {
            [$cx, $cy] = $rencana['lingkaran']['pusat'];
            $r = $rencana['lingkaran']['jari'];
            $titik[] = [$cx - $r, $cy - $r];
            $titik[] = [$cx + $r, $cy + $r];
        }

        return $titik;
    }

    /** Titik tengah sebuah poligon, dipakai sebagai acuan arah label. */
    protected static function pusat(array $titik): array
    {
        if (! $titik) {
            return [Kanvas::LEBAR / 2, Kanvas::TINGGI / 2];
        }

        return [
            array_sum(array_column($titik, 0)) / count($titik),
            array_sum(array_column($titik, 1)) / count($titik),
        ];
    }

    /**
     * Susunan gambar sebuah bangun dalam satuan aslinya — titik sudut, garis
     * putus-putus, tanda siku-siku, dan label ukurannya. Penskalaan ke piksel
     * dikerjakan belakangan, jadi bagian ini bisa dibaca seperti gambar di
     * buku: titik (0,0) ada di kiri atas.
     *
     * @return array{titik: array, lingkaran: ?array, putus: array, siku: array, label: array}
     */
    protected static function rencana(string $bentuk, array $isi, string $satuan, string $tanya): array
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);
        $d = self::turunan($bentuk, $isi);
        // Sisi turunan hanya ditulis pada soal keliling — lihat svg().
        $kll = $tanya === 'keliling';
        $l = fn (float $x) => Angka::dengan($x, $satuan);

        $kosong = ['titik' => [], 'lingkaran' => null, 'putus' => [], 'siku' => [], 'label' => []];

        return match ($bentuk) {
            'persegi' => [
                'titik' => [[0, 0], [$n('sisi'), 0], [$n('sisi'), $n('sisi')], [0, $n('sisi')]],
                'siku' => [['di' => [0, $n('sisi')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                'label' => [
                    ['sisi' => [3, 2], 'teks' => $l($n('sisi'))],
                    ['sisi' => [1, 2], 'teks' => $l($n('sisi'))],
                ],
            ] + $kosong,

            'persegi_panjang' => [
                'titik' => [[0, 0], [$n('panjang'), 0], [$n('panjang'), $n('lebar')], [0, $n('lebar')]],
                'siku' => [['di' => [0, $n('lebar')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                'label' => [
                    ['sisi' => [3, 2], 'teks' => $l($n('panjang'))],
                    ['sisi' => [1, 2], 'teks' => $l($n('lebar'))],
                ],
            ] + $kosong,

            'segitiga_siku' => [
                'titik' => [[0, 0], [0, $n('tinggi')], [$n('alas'), $n('tinggi')]],
                'siku' => [['di' => [0, $n('tinggi')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                'label' => array_values(array_filter([
                    ['sisi' => [1, 2], 'teks' => $l($n('alas'))],
                    ['sisi' => [0, 1], 'teks' => $l($n('tinggi'))],
                    $kll ? ['sisi' => [2, 0], 'teks' => $l($d['sisi_miring'])] : null,
                ])),
            ] + $kosong,

            'segitiga_sama_kaki' => [
                'titik' => [[$n('alas') / 2, 0], [$n('alas'), $n('tinggi')], [0, $n('tinggi')]],
                'putus' => [[[$n('alas') / 2, 0], [$n('alas') / 2, $n('tinggi')]]],
                'siku' => [['di' => [$n('alas') / 2, $n('tinggi')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                'label' => array_values(array_filter([
                    ['sisi' => [2, 1], 'teks' => $l($n('alas'))],
                    ['di' => [$n('alas') / 2, $n('tinggi') / 2], 'arah' => [1, 0], 'teks' => $l($n('tinggi'))],
                    $kll ? ['sisi' => [2, 0], 'teks' => $l($d['kaki'])] : null,
                ])),
            ] + $kosong,

            'jajar_genjang' => [
                'titik' => [
                    [$d['geser'], 0],
                    [$d['geser'] + $n('alas'), 0],
                    [$n('alas'), $n('tinggi')],
                    [0, $n('tinggi')],
                ],
                'putus' => [[[$d['geser'], 0], [$d['geser'], $n('tinggi')]]],
                'siku' => [['di' => [$d['geser'], $n('tinggi')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                'label' => [
                    ['sisi' => [3, 2], 'teks' => $l($n('alas'))],
                    ['sisi' => [1, 2], 'teks' => $l($n('sisi_miring'))],
                    ['di' => [$d['geser'], $n('tinggi') / 2], 'arah' => [1, 0], 'teks' => $l($n('tinggi'))],
                ],
            ] + $kosong,

            'trapesium_sama_kaki' => (function () use ($n, $d, $l, $kll, $kosong) {
                $selisih = ($n('sisi_bawah') - $n('sisi_atas')) / 2;

                return [
                    'titik' => [
                        [$selisih, 0],
                        [$selisih + $n('sisi_atas'), 0],
                        [$n('sisi_bawah'), $n('tinggi')],
                        [0, $n('tinggi')],
                    ],
                    'putus' => [[[$selisih, 0], [$selisih, $n('tinggi')]]],
                    'siku' => [['di' => [$selisih, $n('tinggi')], 'arah1' => [1, 0], 'arah2' => [0, -1]]],
                    'label' => array_values(array_filter([
                        ['sisi' => [0, 1], 'teks' => $l($n('sisi_atas'))],
                        ['sisi' => [3, 2], 'teks' => $l($n('sisi_bawah'))],
                        ['di' => [$selisih, $n('tinggi') / 2], 'arah' => [1, 0], 'teks' => $l($n('tinggi'))],
                        $kll ? ['sisi' => [1, 2], 'teks' => $l($d['kaki'])] : null,
                    ])),
                ] + $kosong;
            })(),

            // Belah ketupat dan layang-layang menggambarkan diagonalnya hanya
            // pada soal luas, dan panjang sisinya hanya pada soal keliling.
            // Bukan sekadar supaya tidak berdesakan: diagonal tidak dipakai
            // menghitung keliling, dan sisi tidak dipakai menghitung luas,
            // jadi yang tidak terpakai memang tidak perlu ada di gambar.
            // Labelnya ditaruh di ujung garis putus-putus, di luar bangun —
            // bagian dalam kedua bangun ini terlalu sempit untuk ditulisi.
            'belah_ketupat' => (function () use ($n, $d, $l, $kll, $kosong) {
                $x = $n('diagonal_1');
                $y = $n('diagonal_2');

                return [
                    'titik' => [[$x / 2, 0], [$x, $y / 2], [$x / 2, $y], [0, $y / 2]],
                    'putus' => $kll ? [] : [[[0, $y / 2], [$x, $y / 2]], [[$x / 2, 0], [$x / 2, $y]]],
                    'label' => $kll
                        ? [['sisi' => [3, 2], 'teks' => $l($d['sisi'])]]
                        : [
                            ['di' => [0, $y / 2], 'arah' => [-1, 0], 'teks' => $l($x)],
                            ['di' => [$x / 2, $y], 'arah' => [0, 1], 'teks' => $l($y)],
                        ],
                ] + $kosong;
            })(),

            'layang_layang' => (function () use ($n, $d, $l, $kll, $kosong) {
                $x = $n('diagonal_1');
                $atas = $n('bagian_atas');
                $y = $atas + $n('bagian_bawah');

                return [
                    'titik' => [[$x / 2, 0], [$x, $atas], [$x / 2, $y], [0, $atas]],
                    'putus' => $kll ? [] : [[[0, $atas], [$x, $atas]], [[$x / 2, 0], [$x / 2, $y]]],
                    'label' => $kll
                        ? [
                            ['sisi' => [0, 1], 'teks' => $l($d['sisi_pendek'])],
                            ['sisi' => [1, 2], 'teks' => $l($d['sisi_panjang'])],
                        ]
                        : [
                            ['di' => [0, $atas], 'arah' => [-1, 0], 'teks' => $l($x)],
                            ['di' => [$x / 2, $y], 'arah' => [0, 1], 'teks' => $l($y)],
                        ],
                ] + $kosong;
            })(),

            'lingkaran' => [
                'lingkaran' => ['pusat' => [$n('jari_jari'), $n('jari_jari')], 'jari' => $n('jari_jari')],
                'putus' => [[[$n('jari_jari'), $n('jari_jari')], [2 * $n('jari_jari'), $n('jari_jari')]]],
                'label' => [
                    ['di' => [$n('jari_jari') * 1.5, $n('jari_jari')], 'arah' => [0, -1], 'teks' => $l($n('jari_jari'))],
                ],
            ] + $kosong,

            default => $kosong,
        };
    }
}

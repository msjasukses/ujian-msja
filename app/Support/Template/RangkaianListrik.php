<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template rangkaian listrik: hambatan pengganti dan kuat arus.
 *
 * Gambar skemanya bukan hiasan. Susunan seri dan paralel memakai rumus yang
 * berbeda jauh, dan yang membedakannya bagi siswa adalah bentuk rangkaian —
 * bukan kalimat soal. Karena itu pengecohnya pun diambil dari hasil rumus
 * susunan yang satunya: siswa yang salah mengenali gambar akan menemukan
 * jawabannya di daftar, dan butirnya benar-benar menguji pembacaan skema.
 */
class RangkaianListrik extends Template
{
    /** @var array<string, string> */
    public const SUSUNAN = [
        'seri' => 'Seri',
        'paralel' => 'Paralel',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hambatan' => 'Hambatan pengganti',
        'arus' => 'Kuat arus',
        'amperemeter' => 'Membaca amperemeter',
        'magnet' => 'Kutub magnet',
        'daya' => 'Daya rangkaian',
        'energi' => 'Energi listrik alat rumah tangga',
        'biaya' => 'Biaya listrik yang dibayar',
    ];

    /** @var array<string, string> sifat dua kutub magnet yang berdekatan */
    public const KUTUB = [
        'U' => 'Utara',
        'S' => 'Selatan',
    ];

    protected const IKON = [
        'seri' => '<path d="M2 16 L8 16 M8 11 L15 11 L15 21 L8 21 Z M15 16 L19 16 M19 11 L26 11 L26 21 L19 21 Z M26 16 L30 16"/>',
        'paralel' => '<path d="M2 16 L8 16 M8 6 L8 26 M8 6 L24 6 M8 26 L24 26 '
            .'M11 1 L21 1 L21 11 L11 11 Z M11 21 L21 21 L21 31 L11 31 Z M24 6 L24 26 M24 16 L30 16"/>',
    ];

    public static function kode(): string
    {
        return 'rangkaian-listrik';
    }

    public static function nama(): string
    {
        return 'Listrik & Magnet';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Rangkaian seri dan paralel (hambatan pengganti, hukum Ohm), membaca '
            .'amperemeter, serta tarik-menarik kutub magnet.';
    }

    public static function ikon(): string
    {
        return 'bi-lightning-charge';
    }

    public static function kataKunci(): array
    {
        return ['listrik', 'hambatan', 'resistor', 'seri', 'paralel', 'ohm', 'arus', 'ampere', 'fisika'];
    }

    public static function kolom(): array
    {
        $hambatan = fn (string $nama, string $label, float $awal, array $tampil = []) => [
            'nama' => $nama,
            'label' => $label,
            'jenis' => 'angka',
            'awal' => $awal,
            'min' => 0.1,
            'max' => 1000,
            'lebar' => 6,
        ] + ($tampil ? ['tampil' => $tampil] : []);

        return [
            [
                'nama' => 'susunan',
                'label' => 'Susunan rangkaian',
                'jenis' => 'pilihan-ikon',
                'pilihan' => self::SUSUNAN,
                'ikon' => self::IKON,
                'awal' => 'seri',
            ],
            [
                'nama' => 'banyak',
                'label' => 'Banyak hambatan',
                'jenis' => 'pilihan',
                'pilihan' => ['2' => '2 hambatan', '3' => '3 hambatan'],
                'awal' => '2',
                'lebar' => 6,
            ],
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'hambatan',
                'lebar' => 6,
            ],
            $hambatan('r1', 'R₁ (ohm)', 6),
            $hambatan('r2', 'R₂ (ohm)', 3),
            $hambatan('r3', 'R₃ (ohm)', 6, ['banyak' => ['3']]),
            [
                'nama' => 'tegangan',
                'label' => 'Tegangan sumber (volt)',
                'jenis' => 'angka',
                'awal' => 12,
                'min' => 0.1,
                'max' => 1000,
                'lebar' => 6,
            ],
            self::kolomAngka('batas_ukur', 'Batas ukur amperemeter (A)', 5, 0.1, 100, [
                'tampil' => ['tanya' => ['amperemeter']],
            ]),
            self::kolomAngka('terbaca', 'Jarum menunjuk (A)', 3.2, 0, 100, [
                'langkah' => 0.1, 'tampil' => ['tanya' => ['amperemeter']],
            ]),
            [
                'nama' => 'sifat',
                'label' => 'Yang terjadi pada kedua magnet',
                'jenis' => 'pilihan',
                'pilihan' => ['tarik' => 'Tarik-menarik', 'tolak' => 'Tolak-menolak'],
                'awal' => 'tarik',
                'tampil' => ['tanya' => ['magnet']],
            ],
            [
                'nama' => 'kutub_kiri',
                'label' => 'Kutub magnet kiri yang menghadap',
                'jenis' => 'pilihan',
                'pilihan' => self::KUTUB,
                'awal' => 'U',
                'lebar' => 6,
                'tampil' => ['tanya' => ['magnet']],
            ],
            [
                'nama' => 'alat',
                'label' => 'Alat listrik',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($a) => $a['nama'].' ('.$a['watt'].' watt)', self::ALAT),
                'awal' => 'setrika',
                'tampil' => ['tanya' => ['energi', 'biaya']],
            ],
            self::kolomAngka('banyak_alat', 'Banyaknya alat', 1, 1, 20, [
                'langkah' => 1, 'tampil' => ['tanya' => ['energi', 'biaya']],
            ]),
            self::kolomAngka('jam', 'Lama pemakaian (jam per hari)', 2, 0.5, 24, [
                'langkah' => 0.5, 'tampil' => ['tanya' => ['energi', 'biaya']],
            ]),
            self::kolomAngka('hari', 'Lamanya (hari)', 30, 1, 31, [
                'langkah' => 1, 'tampil' => ['tanya' => ['energi', 'biaya']],
            ]),
            self::kolomAngka('tarif', 'Tarif listrik (Rp per kWh)', 1500, 100, 5000, [
                'langkah' => 50, 'tampil' => ['tanya' => ['biaya']],
            ]),
            self::kolomWarna('kuning'),
        ];
    }

    protected function susun(array $isi): array
    {
        if (($isi['tanya'] ?? '') === 'amperemeter') {
            return $this->amperemeter($isi);
        }

        if (($isi['tanya'] ?? '') === 'magnet') {
            return $this->magnet($isi);
        }

        // Soal energi dan biaya tidak memakai rangkaian sama sekali:
        // yang dibaca tabel pemakaian alat rumah tangga.
        if (in_array($isi['tanya'] ?? '', ['energi', 'biaya'], true)) {
            return $this->pemakaian($isi, ($isi['tanya'] ?? '') === 'biaya');
        }

        $susunan = $isi['susunan'];
        $hambatan = self::hambatan($isi);
        $tegangan = (float) $isi['tegangan'];

        $rt = self::pengganti($susunan, $hambatan);
        $lawan = self::pengganti($susunan === 'seri' ? 'paralel' : 'seri', $hambatan);

        $daftar = implode(', ', array_map(
            fn ($r, $i) => 'R'.self::angkaKecil($i + 1).' = '.Angka::dengan($r, 'Ω'),
            $hambatan,
            array_keys($hambatan),
        ));

        $awalan = 'Perhatikan gambar rangkaian listrik berikut.';
        $keterangan = 'Rangkaian '.mb_strtolower(self::SUSUNAN[$susunan]).' dengan '.$daftar
            .' dan sumber tegangan '.Angka::dengan($tegangan, 'volt').'.';

        if (($isi['tanya'] ?? 'hambatan') === 'hambatan') {
            return [
                'judul' => 'Rangkaian '.self::SUSUNAN[$susunan],
                'teks' => $awalan.' Berapakah hambatan penggantinya?',
                'pernyataan' => $awalan.' Hambatan penggantinya adalah %s.',
                'svg' => self::svg($susunan, $hambatan, $tegangan, $isi['warna'] ?? 'kuning'),
                'keterangan' => $keterangan,
                'pilihan' => $this->pilihan($rt, [$lawan, array_sum($hambatan), $rt * 2, $rt / 2], 'Ω'),
                'kata_kunci' => Angka::id($rt),
                'pembahasan' => self::rumus($susunan, $hambatan).' = '.Angka::dengan($rt, 'Ω').'.',
            ];
        }

        if (($isi['tanya'] ?? '') === 'daya') {
            return $this->daya($isi, $susunan, $hambatan, $tegangan, $rt, $lawan, $awalan, $keterangan);
        }

        $arus = $tegangan / $rt;

        return [
            'judul' => 'Kuat arus rangkaian '.self::SUSUNAN[$susunan],
            'teks' => $awalan.' Berapakah kuat arus yang mengalir pada rangkaian tersebut?',
            'pernyataan' => $awalan.' Kuat arus yang mengalir pada rangkaian tersebut adalah %s.',
            'svg' => self::svg($susunan, $hambatan, $tegangan, $isi['warna'] ?? 'kuning'),
            'keterangan' => $keterangan,
            'pilihan' => $this->pilihan($arus, [
                $tegangan / $lawan, $tegangan * $rt, $rt / $tegangan, $arus * 2,
            ], 'A'),
            'kata_kunci' => Angka::id($arus),
            'pembahasan' => self::rumus($susunan, $hambatan).' = '.Angka::dengan($rt, 'Ω').'.<br>'
                .'I = V ÷ R = '.Angka::id($tegangan).' ÷ '.Angka::id($rt).' = '.Angka::dengan($arus, 'A').'.',
        ];
    }

    /** Membaca jarum amperemeter terhadap batas ukur yang dipilih. */
    protected function amperemeter(array $isi): array
    {
        $batas = (float) $isi['batas_ukur'];
        $terbaca = min((float) $isi['terbaca'], $batas);
        [$warnaIsi, $garis] = self::warna($isi, 'kuning');

        return [
            'judul' => 'Amperemeter '.Angka::dengan($terbaca, 'A'),
            'teks' => 'Perhatikan gambar amperemeter berikut dengan batas ukur '
                .Angka::dengan($batas, 'A').'. Berapakah kuat arus yang terbaca?',
            'pernyataan' => 'Perhatikan gambar amperemeter berikut dengan batas ukur '
                .Angka::dengan($batas, 'A').'. Kuat arus yang terbaca adalah %s.',
            'svg' => self::svgAmperemeter($terbaca, $batas, $warnaIsi, $garis),
            'keterangan' => 'Amperemeter berbatas ukur '.Angka::dengan($batas, 'A')
                .' dengan jarum pada '.Angka::dengan($terbaca, 'A').'.',
            'pilihan' => $this->pilihan($terbaca, [
                // Membaca angka skala tanpa memperhitungkan batas ukur, atau
                // justru menghitungnya terbalik.
                $batas - $terbaca, $terbaca * 10, $terbaca / 10, $batas,
            ], 'A'),
            'kata_kunci' => Angka::id($terbaca),
            'pembahasan' => 'Jarum berhenti pada angka '.Angka::id($terbaca).' dari skala yang '
                .'batas ukurnya '.Angka::dengan($batas, 'A').', jadi kuat arusnya '
                .Angka::dengan($terbaca, 'A').'.',
        ];
    }

    /** Kutub magnet yang belum diketahui, disimpulkan dari sifat keduanya. */
    protected function magnet(array $isi): array
    {
        $kiri = $isi['kutub_kiri'] ?? 'U';
        $tarik = ($isi['sifat'] ?? 'tarik') === 'tarik';
        [$warnaIsi, $garis] = self::warna($isi, 'kuning');

        // Kutub tak sejenis tarik-menarik, kutub sejenis tolak-menolak.
        $benar = $tarik
            ? ($kiri === 'U' ? 'S' : 'U')
            : $kiri;

        $sifat = $tarik ? 'tarik-menarik' : 'tolak-menolak';

        return [
            'judul' => 'Kutub X = '.self::KUTUB[$benar],
            'teks' => "Perhatikan gambar dua magnet berikut. Keduanya {$sifat}. "
                .'Kutub yang ditandai X adalah kutub ...',
            'pernyataan' => "Perhatikan gambar dua magnet berikut. Karena keduanya {$sifat}, "
                .'kutub yang ditandai X adalah kutub %s.',
            'svg' => self::svgMagnet($kiri, $tarik, $warnaIsi, $garis),
            'keterangan' => 'Dua batang magnet berdekatan; kutub kiri '.self::KUTUB[$kiri]
                .' dan kutub kanan ditandai X.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                [self::KUTUB[$benar], self::KUTUB[$benar === 'U' ? 'S' : 'U'],
                    'Kutub utara dan selatan sekaligus', 'Bukan kutub magnet'],
            ),
            'kata_kunci' => self::KUTUB[$benar],
            'pembahasan' => $tarik
                ? 'Kutub tak sejenis tarik-menarik. Karena kutub kiri '.self::KUTUB[$kiri]
                    .' dan keduanya tarik-menarik, kutub X adalah '.self::KUTUB[$benar].'.'
                : 'Kutub sejenis tolak-menolak. Karena kutub kiri '.self::KUTUB[$kiri]
                    .' dan keduanya tolak-menolak, kutub X juga '.self::KUTUB[$benar].'.',
        ];
    }

    public static function svgAmperemeter(float $terbaca, float $batas, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2, 250];
        $r = 170;

        // Skala busur dari 150° sampai 30° seperti amperemeter jarum.
        $sudut = fn (float $nilai) => deg2rad(180 - $nilai / $batas * 120 - 30);
        $titik = fn (float $nilai, float $jarak) => [
            $pusat[0] + $jarak * cos($sudut($nilai)),
            $pusat[1] - $jarak * sin($sudut($nilai)),
        ];

        $kanvas->tambah('<rect x="60" y="40" width="'.(Kanvas::LEBAR - 120).'" height="200" rx="10" fill="'
            .$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>');

        $langkah = $batas / 10;

        for ($i = 0; $i <= 10; $i++) {
            $nilai = $i * $langkah;

            $kanvas->garis($titik($nilai, $r - ($i % 5 === 0 ? 20 : 12)), $titik($nilai, $r), Kanvas::HITAM,
                $i % 5 === 0 ? 2 : 1);

            if ($i % 5 === 0) {
                $p = $titik($nilai, $r - 36);
                $kanvas->teksPx([$p[0], $p[1] + 5], Angka::id($nilai), ['ukuran' => 13]);
            }
        }

        $kanvas->garis($pusat, $titik($terbaca, $r - 26), '#b23a5c', 4)
            ->lingkaran($pusat, 7, Kanvas::HITAM, Kanvas::HITAM, 1)
            ->teksPx([$pusat[0], $pusat[1] - 54], 'A', ['ukuran' => 22, 'tebal' => true]);

        return $kanvas->render();
    }

    /**
     * Dua batang magnet berhadapan. Kutub magnet kanan yang menghadap
     * sengaja ditulis X, dan kutub jauhnya tidak dilabeli sama sekali —
     * kalau dilabeli, jawabannya langsung terbaca tanpa perlu memakai
     * aturan tarik-menarik.
     */
    public static function svgMagnet(string $kiri, bool $tarik, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $y = 110;
        $tinggi = 72;
        $lebar = 180;

        $batang = function (float $x, string $kutubKiri, string $kutubKanan) use ($kanvas, $y, $tinggi, $lebar, $isi, $garis) {
            $kanvas->tambah('<rect x="'.$x.'" y="'.$y.'" width="'.$lebar.'" height="'.$tinggi
                .'" rx="6" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2.5"/>')
                ->garis([$x + $lebar / 2, $y], [$x + $lebar / 2, $y + $tinggi], $garis, 2)
                ->teksPx([$x + $lebar / 4, $y + $tinggi / 2 + 11], $kutubKiri, ['ukuran' => 30, 'tebal' => true])
                ->teksPx([$x + $lebar * 3 / 4, $y + $tinggi / 2 + 11], $kutubKanan, ['ukuran' => 30, 'tebal' => true]);
        };

        $batang(30, $kiri === 'U' ? 'S' : 'U', $kiri);
        $batang(270, 'X', '');

        // Dua panah di bawahnya memperagakan sifat yang diketahui: saling
        // mendekat bila tarik-menarik, saling menjauh bila tolak-menolak.
        $tengah = Kanvas::LEBAR / 2;

        foreach ([-1, 1] as $arah) {
            $pangkal = $tengah + $arah * ($tarik ? 86 : 20);
            $ujung = $tengah + $arah * ($tarik ? 24 : 86);

            $kanvas->garis([$pangkal, 232], [$ujung, 232], Kanvas::HITAM, 3)
                ->poligon([
                    [$ujung + $arah * 12, 232],
                    [$ujung - $arah * 4, 225],
                    [$ujung - $arah * 4, 239],
                ], Kanvas::HITAM, Kanvas::HITAM, 1);
        }

        $kanvas->teksPx([$tengah, 268], $tarik ? 'tarik-menarik' : 'tolak-menolak', [
            'ukuran' => 15, 'tebal' => true,
        ]);

        return $kanvas->render();
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected function pilihan(float $benar, array $kandidat, string $satuan): array
    {
        return array_map(
            fn ($n) => ['teks' => Angka::dengan($n, $satuan), 'urut' => $n],
            array_merge([round($benar, 2)], self::saring($benar, $kandidat)),
        );
    }

    /** @return array<int, float> */
    public static function hambatan(array $isi): array
    {
        $daftar = [(float) ($isi['r1'] ?? 0), (float) ($isi['r2'] ?? 0)];

        if ((int) ($isi['banyak'] ?? 2) === 3) {
            $daftar[] = (float) ($isi['r3'] ?? 0);
        }

        return $daftar;
    }

    public static function pengganti(string $susunan, array $hambatan): float
    {
        if ($susunan === 'seri') {
            return round(array_sum($hambatan), 2);
        }

        $jumlah = array_sum(array_map(fn ($r) => 1 / $r, $hambatan));

        return round(1 / $jumlah, 2);
    }

    protected static function rumus(string $susunan, array $hambatan): string
    {
        $angka = array_map(fn ($r) => Angka::id($r), $hambatan);

        if ($susunan === 'seri') {
            return 'Rangkaian seri: R = R₁ + R₂'.(count($hambatan) > 2 ? ' + R₃' : '')
                .' = '.implode(' + ', $angka);
        }

        return 'Rangkaian paralel: 1/R = 1/R₁ + 1/R₂'.(count($hambatan) > 2 ? ' + 1/R₃' : '')
            .' = '.implode(' + ', array_map(fn ($a) => '1/'.$a, $angka));
    }

    /** Angka kecil di bawah garis, dipakai untuk R₁, R₂, R₃. */
    protected static function angkaKecil(int $n): string
    {
        return ['₁', '₂', '₃', '₄', '₅'][$n - 1] ?? (string) $n;
    }

    /**
     * Alat listrik rumah tangga beserta dayanya dalam watt.
     *
     * @var array<string, array{nama: string, watt: int}>
     */
    public const ALAT = [
        'lampu' => ['nama' => 'Lampu LED', 'watt' => 10],
        'kipas' => ['nama' => 'Kipas angin', 'watt' => 50],
        'televisi' => ['nama' => 'Televisi', 'watt' => 80],
        'kulkas' => ['nama' => 'Kulkas', 'watt' => 120],
        'rice_cooker' => ['nama' => 'Penanak nasi', 'watt' => 350],
        'setrika' => ['nama' => 'Setrika', 'watt' => 300],
        'mesin_cuci' => ['nama' => 'Mesin cuci', 'watt' => 400],
        'pompa' => ['nama' => 'Pompa air', 'watt' => 250],
    ];

    /**
     * Energi listrik dalam kWh.
     *
     * Daya dalam watt dibagi 1000 lebih dahulu menjadi kilowatt, lalu
     * dikalikan lama pemakaian dalam jam.
     */
    public static function energi(int $watt, int $banyak, float $jam, int $hari): float
    {
        return $watt * $banyak / 1000 * $jam * $hari;
    }

    protected function daya(array $isi, string $susunan, array $hambatan, float $tegangan, float $rt, float $lawan, string $awalan, string $keterangan): array
    {
        $arus = $tegangan / $rt;
        $benar = $tegangan * $arus;

        return [
            'judul' => 'Daya rangkaian '.self::SUSUNAN[$susunan],
            'teks' => $awalan.' Berapakah daya listrik yang dipakai rangkaian tersebut?',
            'pernyataan' => $awalan.' Daya listrik yang dipakai rangkaian tersebut adalah %s.',
            'svg' => self::svg($susunan, $hambatan, $tegangan, $isi['warna'] ?? 'kuning'),
            'keterangan' => $keterangan,
            // Pengecohnya kekeliruan rumus yang khas: V × R, I² × V,
            // hambatan dipakai dari susunan yang satunya, dan lupa
            // mengalikan tegangan.
            'pilihan' => $this->pilihan($benar, [
                $tegangan * $lawan === 0.0 ? $benar * 3 : $tegangan * ($tegangan / $lawan),
                $tegangan * $rt,
                $arus,
                $benar / 2,
                $benar * 2,
                $arus * $arus,
            ], 'watt'),
            'kata_kunci' => Angka::id(round($benar, 2)),
            'pembahasan' => self::rumus($susunan, $hambatan).' = '.Angka::dengan($rt, 'Ω').'.<br>'
                .'I = V ÷ R = '.Angka::id($tegangan).' ÷ '.Angka::id($rt).' = '
                .Angka::dengan(round($arus, 2), 'A').'.<br>'
                .'P = V × I = '.Angka::id($tegangan).' × '.Angka::id(round($arus, 2))
                .' = <strong>'.Angka::dengan(round($benar, 2), 'watt').'</strong>.',
        ];
    }

    protected function pemakaian(array $isi, bool $berbiaya): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'kuning');

        $kunci = isset(self::ALAT[$isi['alat'] ?? '']) ? $isi['alat'] : 'setrika';
        $alat = self::ALAT[$kunci];

        $banyak = max(1, (int) ($isi['banyak_alat'] ?? 1));
        $jam = max(0.5, (float) ($isi['jam'] ?? 2));
        $hari = max(1, (int) ($isi['hari'] ?? 30));
        $tarif = max(1, (int) ($isi['tarif'] ?? 1500));

        $kwh = self::energi($alat['watt'], $banyak, $jam, $hari);
        // Biaya listrik dibulatkan ke rupiah penuh; tagihan tidak pernah
        // ditulis sampai sen.
        $biaya = round($kwh * $tarif);

        $svg = self::svgPemakaian($alat, $banyak, $jam, $hari, $berbiaya ? $tarif : null, $warnaIsi, $garis);
        $sebut = $banyak === 1
            ? 'sebuah '.mb_strtolower($alat['nama'])
            : $banyak.' buah '.mb_strtolower($alat['nama']);

        $keterangan = 'Tabel pemakaian '.$sebut.' berdaya '.$alat['watt'].' watt selama '
            .Angka::id($jam).' jam sehari, '.$hari.' hari.';

        $langkah = 'Daya seluruhnya = '.$banyak.' × '.$alat['watt'].' watt = '
            .($alat['watt'] * $banyak).' watt = '.Angka::id($alat['watt'] * $banyak / 1000)
            .' kW.<br>Lama pemakaian = '.Angka::id($jam).' jam/hari × '.$hari.' hari = '
            .Angka::id($jam * $hari).' jam.<br>W = daya × waktu = '
            .Angka::id($alat['watt'] * $banyak / 1000).' kW × '.Angka::id($jam * $hari)
            .' jam = <strong>'.Angka::id($kwh).' kWh</strong>.';

        if (! $berbiaya) {
            return [
                'svg' => $svg,
                'keterangan' => $keterangan,
                'judul' => 'Energi '.Angka::id($kwh).' kWh',
                'teks' => 'Perhatikan tabel pemakaian alat listrik berikut. Berapa kWh energi '
                    .'listrik yang terpakai?',
                'pernyataan' => 'Pada tabel pemakaian alat listrik berikut, energi yang '
                    .'terpakai adalah %s.',
                // Pengecohnya kekeliruan satuan yang paling sering: lupa
                // membagi 1000, lupa mengalikan banyak harinya, dan lupa
                // mengalikan banyak alatnya.
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::id($n).' kWh', 'urut' => $n],
                    array_merge([round($kwh, 2)], array_slice(self::saring($kwh, [
                        $kwh * 1000,
                        $kwh / $hari,
                        $banyak > 1 ? $kwh / $banyak : $kwh / $jam,
                        $kwh / $jam,
                        $kwh * 2,
                    ], 2), 0, 3)),
                ),
                'kata_kunci' => Angka::id($kwh).' kWh',
                'pembahasan' => 'Energi listrik dihitung dalam kilowatt-jam, jadi dayanya '
                    .'dibagi 1000 lebih dahulu.<br>'.$langkah,
            ];
        }

        return [
            'svg' => $svg,
            'keterangan' => $keterangan.' Tarif Rp'.Angka::id($tarif).' per kWh.',
            'judul' => 'Biaya Rp'.Angka::id($biaya),
            'teks' => 'Perhatikan tabel pemakaian alat listrik berikut. Berapa biaya listrik '
                .'yang harus dibayar?',
            'pernyataan' => 'Pada tabel pemakaian alat listrik berikut, biaya listrik yang '
                .'harus dibayar adalah %s.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => 'Rp'.Angka::id(round($n), 0), 'urut' => $n],
                array_merge([round($biaya, 2)], array_slice(self::saring($biaya, [
                    // Lupa membagi 1000: watt langsung dikalikan tarif.
                    $biaya * 1000,
                    // Tarifnya lupa dikalikan, yang terjawab justru kWh-nya.
                    $kwh,
                    // Hanya sehari yang dihitung.
                    $biaya / $hari,
                    $biaya / $jam,
                    $biaya * 2,
                ], 2), 0, 3)),
            ),
            'kata_kunci' => 'Rp'.Angka::id($biaya, 0),
            'pembahasan' => 'Biaya listrik dihitung dari energi dalam kWh dikalikan tarifnya.<br>'
                .$langkah.'<br>Biaya = '.Angka::id($kwh).' kWh × Rp'.Angka::id($tarif)
                .' = <strong>Rp'.Angka::id($biaya).'</strong>.',
        ];
    }

    /**
     * Tabel pemakaian alat listrik: dayanya, banyaknya, dan lama pemakaian.
     *
     * Energi maupun biayanya tidak dituliskan — itulah yang dihitung.
     */
    public static function svgPemakaian(array $alat, int $banyak, float $jam, int $hari, ?int $tarif, string $isi, string $garis): string
    {
        $baris = [
            ['Keterangan', 'Besarnya'],
            ['Alat listrik', $alat['nama']],
            ['Daya tiap alat', $alat['watt'].' watt'],
            ['Banyaknya', $banyak.' buah'],
            ['Lama pemakaian', Angka::id($jam).' jam/hari'],
            ['Lamanya', $hari.' hari'],
        ];

        if ($tarif !== null) {
            $baris[] = ['Tarif listrik', 'Rp'.Angka::id($tarif).'/kWh'];
        }

        $lebar = [196.0, 168.0];
        $tinggiBaris = 30.0;
        $kiri = 240 - array_sum($lebar) / 2;
        $atas = 62.0;

        return (new Kanvas)
            ->teksPx([240, 38], 'Pemakaian Alat Listrik', ['ukuran' => 16, 'tebal' => true])
            ->tabelPx([$kiri, $atas], $lebar, $tinggiBaris, $baris, $isi, $garis, 13)
            ->teksPx([240, $atas + count($baris) * $tinggiBaris + 24],
                '1 kWh = 1000 watt yang dipakai selama satu jam', [
                    'ukuran' => 11, 'warna' => '#6b7280',
                ])
            ->render();
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $susunan, array $hambatan, float $tegangan, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['kuning'];
        $kanvas = new Kanvas;

        $kawat = Kanvas::HITAM;

        if ($susunan === 'seri') {
            $kiri = 60;
            $kanan = 420;
            $atas = 90;
            $bawah = 230;

            $kanvas->garisPatah([[$kiri, $atas], [$kanan, $atas], [$kanan, $bawah], [$kiri, $bawah], [$kiri, $atas]], $kawat, 2);

            foreach ($hambatan as $i => $r) {
                $x = $kiri + ($kanan - $kiri) * ($i + 1) / (count($hambatan) + 1);
                self::resistor($kanvas, $x, $atas, $isi, $garis, 'R'.self::angkaKecil($i + 1).' = '.Angka::id($r).' Ω');
            }

            self::baterai($kanvas, ($kiri + $kanan) / 2, $bawah, $tegangan, $kawat);

            return $kanvas->render();
        }

        $kiri = 150;
        $kanan = 360;
        $atas = 70;
        $jarak = 55;
        $bawah = $atas + $jarak * (count($hambatan) - 1);
        $kabel = $bawah + 60;

        foreach ($hambatan as $i => $r) {
            $y = $atas + $jarak * $i;
            $kanvas->garis([$kiri, $y], [$kanan, $y], $kawat, 2);
            self::resistor($kanvas, ($kiri + $kanan) / 2, $y, $isi, $garis, 'R'.self::angkaKecil($i + 1).' = '.Angka::id($r).' Ω');
        }

        $kanvas->garis([$kiri, $atas], [$kiri, $bawah], $kawat, 2)
            ->garis([$kanan, $atas], [$kanan, $bawah], $kawat, 2)
            ->garisPatah([[$kiri, $bawah], [$kiri, $kabel], [$kanan, $kabel], [$kanan, $bawah]], $kawat, 2);

        self::baterai($kanvas, ($kiri + $kanan) / 2, $kabel, $tegangan, $kawat);

        return $kanvas->render();
    }

    /** Lambang hambatan: kotak memotong kawat, dengan namanya di atasnya. */
    protected static function resistor(Kanvas $kanvas, float $x, float $y, string $isi, string $garis, string $label): void
    {
        $lebar = 56;
        $tinggi = 24;

        $kanvas->tambah('<rect x="'.round($x - $lebar / 2, 1).'" y="'.round($y - $tinggi / 2, 1)
            .'" width="'.$lebar.'" height="'.$tinggi.'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->teksPx([$x, $y - $tinggi / 2 - 8], $label, ['ukuran' => 13, 'tebal' => true]);
    }

    /** Lambang sumber tegangan: sepasang garis panjang-pendek pada kawat. */
    protected static function baterai(Kanvas $kanvas, float $x, float $y, float $tegangan, string $warna): void
    {
        $kanvas->tambah('<line x1="'.($x - 6).'" y1="'.($y - 18).'" x2="'.($x - 6).'" y2="'.($y + 18)
            .'" stroke="'.$warna.'" stroke-width="2.5"/>')
            ->tambah('<line x1="'.($x + 6).'" y1="'.($y - 9).'" x2="'.($x + 6).'" y2="'.($y + 9)
                .'" stroke="'.$warna.'" stroke-width="5"/>');

        $kanvas->teksPx([$x, $y + 36], Angka::dengan($tegangan, 'volt'), ['ukuran' => 13, 'tebal' => true]);
    }
}

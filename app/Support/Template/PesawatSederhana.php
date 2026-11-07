<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template pesawat sederhana: tuas, katrol, dan bidang miring.
 *
 * Keempat alat memakai rumus yang berbeda, tetapi gagasannya satu — gaya
 * yang diperkecil ditebus dengan jarak yang diperpanjang. Karena itu gaya
 * kuasa dan keuntungan mekanis disediakan sebagai dua pertanyaan dari gambar
 * yang sama: yang berganti hanya apa yang diminta, bukan alatnya.
 */
class PesawatSederhana extends Template
{
    /** @var array<string, string> */
    public const JENIS = [
        'tuas' => 'Tuas (Pengungkit)',
        'katrol_tetap' => 'Katrol Tetap',
        'katrol_bergerak' => 'Katrol Bergerak',
        'bidang_miring' => 'Bidang Miring',
        'roda_berporos' => 'Roda Berporos',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'gaya' => 'Gaya kuasa',
        'keuntungan' => 'Keuntungan mekanis',
    ];

    protected const IKON = [
        'tuas' => '<path d="M3 14 L29 14 M13 14 L16 22 L19 14 M3 10 L3 18 M29 10 L29 18"/>',
        'katrol_tetap' => '<circle cx="16" cy="11" r="7"/><path d="M4 4 L28 4 M16 4 L16 4 M9 11 L9 26 M23 11 L23 24"/>',
        'katrol_bergerak' => '<circle cx="16" cy="18" r="7"/><path d="M4 3 L28 3 M9 3 L9 18 M23 3 L23 18 M16 25 L16 30"/>',
        'bidang_miring' => '<path d="M3 27 L29 27 L29 8 Z"/>',
        'roda_berporos' => '<circle cx="16" cy="16" r="13"/><circle cx="16" cy="16" r="5"/>',
    ];

    public static function kode(): string
    {
        return 'pesawat-sederhana';
    }

    public static function nama(): string
    {
        return 'Pesawat Sederhana';
    }

    public static function mapel(): string
    {
        return 'IPA';
    }

    public static function ringkasan(): string
    {
        return 'Tuas, katrol tetap, katrol bergerak, dan bidang miring. '
            .'Tanya gaya kuasa atau keuntungan mekanisnya.';
    }

    public static function ikon(): string
    {
        return 'bi-gear';
    }

    public static function kataKunci(): array
    {
        return ['pesawat sederhana', 'tuas', 'pengungkit', 'katrol', 'bidang miring',
            'keuntungan mekanis', 'gaya', 'fisika'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'jenis_alat',
                'label' => 'Jenis pesawat sederhana',
                'jenis' => 'pilihan-ikon',
                'pilihan' => self::JENIS,
                'ikon' => self::IKON,
                'awal' => 'tuas',
            ],
            [
                'nama' => 'beban',
                'label' => 'Berat beban (newton)',
                'jenis' => 'angka',
                'awal' => 600,
                'min' => 1,
                'max' => 100000,
                'lebar' => 6,
            ],
            [
                'nama' => 'lengan_beban',
                'label' => 'Lengan beban (cm)',
                'jenis' => 'angka',
                'awal' => 50,
                'min' => 1,
                'max' => 1000,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['tuas']],
            ],
            [
                'nama' => 'lengan_kuasa',
                'label' => 'Lengan kuasa (cm)',
                'jenis' => 'angka',
                'awal' => 150,
                'min' => 1,
                'max' => 1000,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['tuas']],
            ],
            [
                'nama' => 'tinggi',
                'label' => 'Tinggi bidang miring (m)',
                'jenis' => 'angka',
                'awal' => 2,
                'min' => 0.1,
                'max' => 1000,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['bidang_miring']],
            ],
            [
                'nama' => 'panjang',
                'label' => 'Panjang bidang miring (m)',
                'jenis' => 'angka',
                'awal' => 6,
                'min' => 0.1,
                'max' => 1000,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['bidang_miring']],
            ],
            [
                'nama' => 'jari_roda',
                'label' => 'Jari-jari roda (cm)',
                'jenis' => 'angka',
                'awal' => 40,
                'min' => 1,
                'max' => 500,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['roda_berporos']],
            ],
            [
                'nama' => 'jari_poros',
                'label' => 'Jari-jari poros (cm)',
                'jenis' => 'angka',
                'awal' => 8,
                'min' => 1,
                'max' => 500,
                'lebar' => 6,
                'tampil' => ['jenis_alat' => ['roda_berporos']],
            ],
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'gaya',
            ],
            self::kolomWarna('jingga'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['jenis_alat'] ?? '') === 'bidang_miring'
            && (float) ($isi['panjang'] ?? 0) <= (float) ($isi['tinggi'] ?? 0)) {
            return ['panjang' => 'Panjang bidang miring harus lebih besar daripada tingginya.'];
        }

        if (($isi['jenis_alat'] ?? '') === 'roda_berporos'
            && (float) ($isi['jari_roda'] ?? 0) <= (float) ($isi['jari_poros'] ?? 0)) {
            return ['jari_roda' => 'Jari-jari roda harus lebih besar daripada jari-jari porosnya.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $alat = $isi['jenis_alat'];
        $w = (float) $isi['beban'];
        $km = self::keuntungan($alat, $isi);
        $gaya = round($w / $km, 2);
        $nama = mb_strtolower(self::JENIS[$alat]);
        $awalan = "Perhatikan gambar {$nama} berikut.";

        if (($isi['tanya'] ?? 'gaya') === 'gaya') {
            return [
                'judul' => self::JENIS[$alat],
                'teks' => $awalan.' Berapakah gaya kuasa yang diperlukan untuk mengangkat beban itu?',
                'pernyataan' => $awalan.' Gaya kuasa yang diperlukan untuk mengangkat beban itu adalah %s.',
                'svg' => self::svg($alat, $isi),
                'keterangan' => $this->keterangan($alat, $isi),
                'pilihan' => array_map(
                    fn ($n) => ['teks' => Angka::dengan($n, 'N'), 'urut' => $n],
                    // Pada katrol tetap dan katrol bergerak, gaya kuasanya
                    // sama dengan berat beban atau separuhnya, sehingga
                    // beberapa kandidat berimpit dengan kuncinya; kelipatan
                    // yang lebih jauh disediakan sebagai cadangan.
                    array_merge([$gaya], self::saring($gaya, [
                        $w, $w * $km, $w / 2, $w * 2, $gaya * 2, $gaya / 2, $gaya * 4, $w / 4,
                    ])),
                ),
                'kata_kunci' => Angka::id($gaya),
                'pembahasan' => $this->pembahasan($alat, $isi).'<br>'
                    .'F = w ÷ KM = '.Angka::id($w).' ÷ '.Angka::id($km).' = '.Angka::dengan($gaya, 'N').'.',
            ];
        }

        return [
            'judul' => self::JENIS[$alat],
            'teks' => $awalan.' Berapakah keuntungan mekanis pesawat sederhana itu?',
            'pernyataan' => $awalan.' Keuntungan mekanis pesawat sederhana itu adalah %s.',
            'svg' => self::svg($alat, $isi),
            'keterangan' => $this->keterangan($alat, $isi),
            'pilihan' => array_map(
                fn ($n) => ['teks' => Angka::id($n), 'urut' => $n],
                array_merge([$km], self::saring($km, [1 / $km, $km + 1, $km * 2, $km / 2, $w / $km])),
            ),
            'kata_kunci' => Angka::id($km),
            'pembahasan' => $this->pembahasan($alat, $isi),
        ];
    }

    /** Keuntungan mekanis: berapa kali gaya yang harus dikeluarkan mengecil. */
    public static function keuntungan(string $alat, array $isi): float
    {
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return round(match ($alat) {
            'tuas' => $n('lengan_kuasa') / max(0.0001, $n('lengan_beban')),
            'katrol_bergerak' => 2,
            'bidang_miring' => $n('panjang') / max(0.0001, $n('tinggi')),
            'roda_berporos' => $n('jari_roda') / max(0.0001, $n('jari_poros')),
            default => 1,
        }, 2);
    }

    protected function pembahasan(string $alat, array $isi): string
    {
        $n = fn (string $k) => Angka::id((float) ($isi[$k] ?? 0));

        return match ($alat) {
            'tuas' => 'Pada tuas, KM = lengan kuasa ÷ lengan beban = '.$n('lengan_kuasa').' ÷ '
                .$n('lengan_beban').' = '.Angka::id(self::keuntungan($alat, $isi)).'.',
            'katrol_tetap' => 'Katrol tetap hanya mengubah arah gaya, tidak memperkecilnya, '
                .'sehingga keuntungan mekanisnya 1.',
            'katrol_bergerak' => 'Pada katrol bergerak, beban ditahan dua tali, '
                .'sehingga keuntungan mekanisnya 2.',
            'roda_berporos' => 'Pada roda berporos, KM = jari-jari roda ÷ jari-jari poros = '
                .$n('jari_roda').' ÷ '.$n('jari_poros').' = '.Angka::id(self::keuntungan($alat, $isi)).'.',
            default => 'Pada bidang miring, KM = panjang bidang ÷ tinggi = '.$n('panjang').' ÷ '
                .$n('tinggi').' = '.Angka::id(self::keuntungan($alat, $isi)).'.',
        };
    }

    protected function keterangan(string $alat, array $isi): string
    {
        $n = fn (string $k) => Angka::id((float) ($isi[$k] ?? 0));
        $beban = 'beban '.$n('beban').' N';

        return match ($alat) {
            'tuas' => "Tuas dengan {$beban}, lengan beban ".$n('lengan_beban')
                .' cm, dan lengan kuasa '.$n('lengan_kuasa').' cm.',
            'bidang_miring' => 'Bidang miring setinggi '.$n('tinggi').' m dan sepanjang '
                .$n('panjang')." m, dengan {$beban}.",
            'roda_berporos' => 'Roda berjari-jari '.$n('jari_roda').' cm pada poros berjari-jari '
                .$n('jari_poros')." cm, dengan {$beban}.",
            default => self::JENIS[$alat].' dengan '.$beban.'.',
        };
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(string $alat, array $isi): string
    {
        [$warnaIsi, $garis] = self::warna($isi, 'jingga');
        $n = fn (string $k) => (float) ($isi[$k] ?? 0);

        return match ($alat) {
            'tuas' => self::tuas($n('lengan_beban'), $n('lengan_kuasa'), $n('beban'), $warnaIsi, $garis),
            'katrol_tetap' => self::katrolTetap($n('beban'), $warnaIsi, $garis),
            'katrol_bergerak' => self::katrolBergerak($n('beban'), $warnaIsi, $garis),
            'roda_berporos' => self::rodaBerporos($n('jari_roda'), $n('jari_poros'), $n('beban'), $warnaIsi, $garis),
            default => self::bidangMiring($n('tinggi'), $n('panjang'), $n('beban'), $warnaIsi, $garis),
        };
    }

    /** Panah bergaris lurus dengan mata panah di ujungnya. */
    protected static function panah(Kanvas $kanvas, array $dari, array $ke, string $warna): void
    {
        $kanvas->garis($dari, $ke, $warna, 2.5);

        $arah = Kanvas::searah([$ke[0] - $dari[0], $ke[1] - $dari[1]]);
        $sisi = [-$arah[1], $arah[0]];
        $pangkal = [$ke[0] - $arah[0] * 12, $ke[1] - $arah[1] * 12];

        $kanvas->poligon([
            $ke,
            [$pangkal[0] + $sisi[0] * 6, $pangkal[1] + $sisi[1] * 6],
            [$pangkal[0] - $sisi[0] * 6, $pangkal[1] - $sisi[1] * 6],
        ], $warna, $warna, 1);
    }

    /** Kotak beban beserta tulisan beratnya. */
    protected static function beban(Kanvas $kanvas, float $x, float $atas, float $w, string $isi, string $garis): void
    {
        $kanvas->tambah('<rect x="'.round($x - 26, 1).'" y="'.round($atas, 1)
            .'" width="52" height="40" rx="4" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->teksPx([$x, $atas + 25], Angka::id($w).' N', ['ukuran' => 13, 'tebal' => true]);
    }

    protected static function tuas(float $lb, float $lk, float $w, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kawat = Kanvas::HITAM;

        // Titik tumpu diletakkan sesuai perbandingan kedua lengannya, jadi
        // gambar ikut berubah saat guru mengganti angkanya.
        $kiri = 60;
        $kanan = 420;
        $y = 140;
        $tumpu = $kiri + ($kanan - $kiri) * ($lb / max(0.0001, $lb + $lk));

        $kanvas->tambah('<rect x="'.$kiri.'" y="'.($y - 7).'" width="'.($kanan - $kiri)
            .'" height="14" rx="3" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="2"/>');

        $kanvas->poligon([[$tumpu, $y + 7], [$tumpu - 26, $y + 56], [$tumpu + 26, $y + 56]], '#cfd4da', $kawat);

        self::beban($kanvas, $kiri + 20, $y - 55, $w, $isi, $garis);
        $kanvas->garis([$kiri + 20, $y - 15], [$kiri + 20, $y - 7], $kawat, 2);

        self::panah($kanvas, [$kanan - 20, $y - 72], [$kanan - 20, $y - 12], $kawat);
        $kanvas->teksPx([$kanan - 20, $y - 82], 'F', ['ukuran' => 15, 'tebal' => true]);

        // Ukuran kedua lengan digambar sebagai garis ukur di bawah batang.
        $ukur = $y + 78;
        $kanvas->garis([$kiri + 20, $ukur], [$tumpu, $ukur], $kawat, 1.5)
            ->garis([$tumpu, $ukur], [$kanan - 20, $ukur], $kawat, 1.5)
            ->garis([$tumpu, $y + 56], [$tumpu, $ukur + 6], $kawat, 1, true);

        $kanvas->teksPx([($kiri + 20 + $tumpu) / 2, $ukur + 20], Angka::id($lb).' cm', ['ukuran' => 13])
            ->teksPx([($tumpu + $kanan - 20) / 2, $ukur + 20], Angka::id($lk).' cm', ['ukuran' => 13]);

        return $kanvas->render();
    }

    protected static function katrolTetap(float $w, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kawat = Kanvas::HITAM;

        $x = 240;
        $y = 90;
        $r = 42;

        $kanvas->garis([$x - 80, 32], [$x + 80, 32], $kawat, 3)
            ->garis([$x, 32], [$x, $y - $r], $kawat, 2)
            ->lingkaran([$x, $y], $r, $isi, $garis, 2.5)
            ->lingkaran([$x, $y], 5, $garis, $garis, 1)
            ->garis([$x - $r, $y], [$x - $r, 200], $kawat, 2)
            ->garis([$x + $r, $y], [$x + $r, 180], $kawat, 2);

        self::beban($kanvas, $x - $r, 200, $w, $isi, $garis);
        self::panah($kanvas, [$x + $r, 180], [$x + $r, 236], $kawat);
        $kanvas->teksPx([$x + $r + 16, 220], 'F', ['anchor' => 'start', 'ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }

    protected static function katrolBergerak(float $w, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $kawat = Kanvas::HITAM;

        $x = 230;
        $y = 150;
        $r = 42;

        $kanvas->garis([$x - 100, 30], [$x + 120, 30], $kawat, 3)
            ->garis([$x - $r, 30], [$x - $r, $y], $kawat, 2)
            ->lingkaran([$x, $y], $r, $isi, $garis, 2.5)
            ->lingkaran([$x, $y], 5, $garis, $garis, 1)
            ->garis([$x + $r, $y], [$x + $r, 44], $kawat, 2)
            ->garis([$x, $y + $r], [$x, $y + $r + 14], $kawat, 2);

        self::beban($kanvas, $x, $y + $r + 14, $w, $isi, $garis);
        self::panah($kanvas, [$x + $r, 80], [$x + $r, 32], $kawat);
        $kanvas->teksPx([$x + $r + 16, 70], 'F', ['anchor' => 'start', 'ukuran' => 15, 'tebal' => true]);

        return $kanvas->render();
    }

    /**
     * Roda berporos: dua lingkaran sepusat dengan perbandingan jari-jari
     * yang sebenarnya, jadi keuntungan mekanisnya bisa diperkirakan dari
     * gambar sebelum dihitung.
     */
    protected static function rodaBerporos(float $jariRoda, float $jariPoros, float $w, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;
        $pusat = [Kanvas::LEBAR / 2 - 40, Kanvas::TINGGI / 2];

        $skala = 110 / max(0.0001, $jariRoda);
        $rRoda = 110;
        $rPoros = max(14, $jariPoros * $skala);

        $kanvas->lingkaran($pusat, $rRoda, $isi, $garis, 3)
            ->lingkaran($pusat, $rPoros, '#8a929a', $garis, 2.5)
            ->lingkaran($pusat, 4, $garis, $garis, 1);

        // Gaya kuasa bekerja pada tepi roda, beban tergantung pada porosnya.
        $kanvas->garis([$pusat[0] + $rRoda, $pusat[1]], [$pusat[0] + $rRoda, $pusat[1] - 70], Kanvas::HITAM, 2.5);
        self::panah($kanvas, [$pusat[0] + $rRoda, $pusat[1] - 70], [$pusat[0] + $rRoda, $pusat[1] - 10], Kanvas::HITAM);
        $kanvas->teksPx([$pusat[0] + $rRoda + 12, $pusat[1] - 60], 'F', [
            'anchor' => 'start', 'ukuran' => 17, 'tebal' => true,
        ]);

        $kanvas->garis([$pusat[0] + $rPoros, $pusat[1]], [$pusat[0] + $rPoros, $pusat[1] + 60], Kanvas::HITAM, 2);
        self::beban($kanvas, $pusat[0] + $rPoros, $pusat[1] + 60, $w, $isi, $garis);

        $kanvas->garis($pusat, [$pusat[0] - $rRoda, $pusat[1]], $garis, 1.5, true)
            ->teksPx([$pusat[0] - $rRoda / 2, $pusat[1] - 10], Angka::id($jariRoda).' cm', ['ukuran' => 13])
            ->teksPx([$pusat[0] - $rPoros / 2, $pusat[1] + 20], Angka::id($jariPoros).' cm', ['ukuran' => 12]);

        return $kanvas->render();
    }

    protected static function bidangMiring(float $tinggi, float $panjang, float $w, string $isi, string $garis): string
    {
        // Alas segitiga dihitung dengan Pythagoras supaya kemiringan yang
        // tergambar sesuai dengan angka tinggi dan panjang yang ditulis.
        $alas = sqrt(max(0.0001, $panjang ** 2 - $tinggi ** 2));

        $kanvas = new Kanvas;
        $kanvas->pas([[0, 0], [$alas, $tinggi]], 70);

        $kawat = Kanvas::HITAM;

        $kanvas->poligon([[0, $tinggi], [$alas, $tinggi], [$alas, 0]], $isi, $garis)
            ->siku([$alas, $tinggi], [-1, 0], [0, -1], $garis);

        $tengah = [$alas * 0.45, $tinggi * 0.55];
        $pxTengah = $kanvas->px($tengah);

        $kanvas->tambah('<rect x="'.round($pxTengah[0] - 20, 1).'" y="'.round($pxTengah[1] - 34, 1)
            .'" width="40" height="30" rx="3" fill="#ffffff" stroke="'.$kawat.'" stroke-width="2"/>')
            ->teksPx([$pxTengah[0], $pxTengah[1] - 14], Angka::id($w).' N', ['ukuran' => 12, 'tebal' => true]);

        $miring = $kanvas->px([$alas * 0.78, $tinggi * 0.22]);
        $kanvas->teksPx([$miring[0] - 6, $miring[1] - 10], Angka::id($panjang).' m', ['anchor' => 'end', 'ukuran' => 14]);

        $tegak = $kanvas->px([$alas, $tinggi / 2]);
        $kanvas->teksPx([$tegak[0] + 12, $tegak[1] + 4], Angka::id($tinggi).' m', ['anchor' => 'start', 'ukuran' => 14]);

        return $kanvas->render();
    }
}

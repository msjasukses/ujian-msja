<?php

namespace App\Support;

/**
 * Kanvas SVG untuk gambar yang menyertai soal hasil template.
 *
 * SVG dipilih karena isinya cuma garis dan teks: tidak perlu GD maupun
 * berkas font di server, tajam pada ukuran berapa pun, dan mudah diperiksa
 * dalam pengujian karena hasilnya tetap berupa teks. Peramban yang
 * mengubahnya menjadi PNG sebelum diunggah ke bank soal.
 *
 * Koordinat yang diterima semua metode adalah "satuan gambar". Template yang
 * memanggil pas() lebih dulu boleh memakai satuan aslinya — sentimeter,
 * derajat, rupiah — dan kanvas yang mengurus penskalaan serta peletakan di
 * tengah. Template yang tidak memanggil pas() berarti menggambar langsung
 * dalam piksel.
 */
class Kanvas
{
    public const LEBAR = 480;

    public const TINGGI = 300;

    /** Ruang tepi bawaan; selebar label terpanjang yang masuk akal. */
    public const TEPI = 60;

    public const HITAM = '#1f2937';

    /** @var array<int, string> */
    protected array $isi = [];

    protected float $skala = 1;

    protected float $geserX = 0;

    protected float $geserY = 0;

    protected float $pangkalX = 0;

    protected float $pangkalY = 0;

    public function __construct(
        public readonly int $lebar = self::LEBAR,
        public readonly int $tinggi = self::TINGGI,
        protected string $latar = '#ffffff',
    ) {}

    /**
     * Pas-kan sekumpulan titik ke dalam kanvas: diperbesar sampai memenuhi
     * ruang yang tersisa, lalu diletakkan di tengah.
     *
     * Satu faktor skala dipakai untuk lebar dan tinggi sekaligus. Meregangkan
     * salah satunya memang membuat gambar lebih besar, tetapi perbandingan
     * sisi di layar lantas berbohong terhadap angka yang tertulis di labelnya.
     *
     * @param  array<int, array{0: float, 1: float}>  $titik
     */
    public function pas(array $titik, int $tepi = self::TEPI): static
    {
        if ($titik === []) {
            return $this;
        }

        $x = array_column($titik, 0);
        $y = array_column($titik, 1);

        $this->pangkalX = min($x);
        $this->pangkalY = min($y);

        $lebarG = max(0.0001, max($x) - $this->pangkalX);
        $tinggiG = max(0.0001, max($y) - $this->pangkalY);

        $this->skala = min(($this->lebar - 2 * $tepi) / $lebarG, ($this->tinggi - 2 * $tepi) / $tinggiG);
        $this->geserX = ($this->lebar - $lebarG * $this->skala) / 2;
        $this->geserY = ($this->tinggi - $tinggiG * $this->skala) / 2;

        return $this;
    }

    /** @return array{0: float, 1: float} */
    public function px(array $p): array
    {
        return [
            round($this->geserX + ($p[0] - $this->pangkalX) * $this->skala, 1),
            round($this->geserY + ($p[1] - $this->pangkalY) * $this->skala, 1),
        ];
    }

    /** Panjang satuan gambar dalam piksel. */
    public function panjang(float $satuan): float
    {
        return $satuan * $this->skala;
    }

    public function poligon(array $titik, string $isi, string $garis, float $tebal = 2): static
    {
        $t = implode(' ', array_map(fn ($p) => implode(',', $this->px($p)), $titik));

        return $this->tambah('<polygon points="'.$t.'" fill="'.$isi.'" stroke="'.$garis
            .'" stroke-width="'.$tebal.'" stroke-linejoin="round"/>');
    }

    public function garisPatah(array $titik, string $garis, float $tebal = 2, bool $putus = false): static
    {
        $t = implode(' ', array_map(fn ($p) => implode(',', $this->px($p)), $titik));

        return $this->tambah('<polyline points="'.$t.'" fill="none" stroke="'.$garis
            .'" stroke-width="'.$tebal.'"'.($putus ? ' stroke-dasharray="6 4"' : '').'/>');
    }

    public function garis(array $a, array $b, string $garis, float $tebal = 1.5, bool $putus = false): static
    {
        [$x1, $y1] = $this->px($a);
        [$x2, $y2] = $this->px($b);

        return $this->tambah('<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="'.$garis
            .'" stroke-width="'.$tebal.'"'.($putus ? ' stroke-dasharray="6 4"' : '').' stroke-linecap="round"/>');
    }

    public function persegi(array $kiriAtas, float $lebar, float $tinggi, string $isi, string $garis, float $tebal = 2): static
    {
        [$x, $y] = $this->px($kiriAtas);

        return $this->tambah('<rect x="'.$x.'" y="'.$y.'" width="'.round($this->panjang($lebar), 1)
            .'" height="'.round($this->panjang($tinggi), 1).'" fill="'.$isi.'" stroke="'.$garis
            .'" stroke-width="'.$tebal.'"/>');
    }

    public function lingkaran(array $pusat, float $jari, string $isi, string $garis, float $tebal = 2): static
    {
        [$x, $y] = $this->px($pusat);

        return $this->tambah('<circle cx="'.$x.'" cy="'.$y.'" r="'.round($this->panjang($jari), 1)
            .'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="'.$tebal.'"/>');
    }

    public function elips(array $pusat, float $rx, float $ry, string $isi, string $garis, float $tebal = 2, bool $putus = false): static
    {
        [$x, $y] = $this->px($pusat);

        return $this->tambah('<ellipse cx="'.$x.'" cy="'.$y.'" rx="'.round($this->panjang($rx), 1)
            .'" ry="'.round($this->panjang($ry), 1).'" fill="'.$isi.'" stroke="'.$garis
            .'" stroke-width="'.$tebal.'"'.($putus ? ' stroke-dasharray="5 4"' : '').'/>');
    }

    /**
     * Juring lingkaran — sepotong "kue" dari sudut mulai sampai sudut akhir,
     * diukur dalam derajat searah jarum jam dari arah pukul 12.
     */
    public function juring(array $pusat, float $jari, float $mulai, float $akhir, string $isi, string $garis, float $tebal = 2): static
    {
        [$cx, $cy] = $this->px($pusat);
        $r = round($this->panjang($jari), 1);

        // Satu juring penuh tidak bisa digambar sebagai busur: titik awal dan
        // titik akhirnya berimpit, sehingga busurnya menjadi panjang nol.
        if ($akhir - $mulai >= 359.999) {
            return $this->tambah('<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$isi
                .'" stroke="'.$garis.'" stroke-width="'.$tebal.'"/>');
        }

        $titik = function (float $derajat) use ($cx, $cy, $r) {
            $rad = deg2rad($derajat - 90);

            return round($cx + $r * cos($rad), 1).' '.round($cy + $r * sin($rad), 1);
        };

        $besar = ($akhir - $mulai) > 180 ? 1 : 0;

        return $this->tambah('<path d="M '.$cx.' '.$cy.' L '.$titik($mulai)
            .' A '.$r.' '.$r.' 0 '.$besar.' 1 '.$titik($akhir).' Z" fill="'.$isi
            .'" stroke="'.$garis.'" stroke-width="'.$tebal.'" stroke-linejoin="round"/>');
    }

    /**
     * Tulisan pada gambar.
     *
     * @param  array{anchor?: string, ukuran?: int, warna?: string, tebal?: bool, geser?: array{0: float, 1: float}}  $opsi
     */
    public function teks(array $di, string $isi, array $opsi = []): static
    {
        return $this->teksPx($this->px($di), $isi, $opsi);
    }

    /** Tulisan pada kedudukan piksel yang sudah dihitung pemanggilnya. */
    public function teksPx(array $px, string $isi, array $opsi = []): static
    {
        [$x, $y] = $px;
        $geser = $opsi['geser'] ?? [0, 0];

        return $this->tambah('<text x="'.round($x + $geser[0], 1).'" y="'.round($y + $geser[1], 1)
            .'" font-size="'.($opsi['ukuran'] ?? 14).'" fill="'.($opsi['warna'] ?? self::HITAM).'"'
            .(($opsi['tebal'] ?? false) ? ' font-weight="600"' : '')
            .' text-anchor="'.($opsi['anchor'] ?? 'middle').'">'
            .htmlspecialchars($isi, ENT_QUOTES, 'UTF-8').'</text>');
    }

    /**
     * Tanda sudut siku-siku di sebuah titik sudut, menghadap dua arah yang
     * diberikan (vektor arah, panjangnya tidak penting).
     */
    public function siku(array $sudut, array $arah1, array $arah2, string $warna, float $sisi = 10): static
    {
        [$x, $y] = $this->px($sudut);
        $a = self::searah($arah1);
        $b = self::searah($arah2);

        $titik = [
            [$x + $a[0] * $sisi, $y + $a[1] * $sisi],
            [$x + ($a[0] + $b[0]) * $sisi, $y + ($a[1] + $b[1]) * $sisi],
            [$x + $b[0] * $sisi, $y + $b[1] * $sisi],
        ];

        $t = implode(' ', array_map(fn ($p) => round($p[0], 1).','.round($p[1], 1), $titik));

        return $this->tambah('<polyline points="'.$t.'" fill="none" stroke="'.$warna.'" stroke-width="1.5"/>');
    }

    /** Vektor arah sepanjang satu satuan. */
    public static function searah(array $v): array
    {
        $panjang = hypot($v[0], $v[1]);

        return $panjang < 0.0001 ? [0.0, 1.0] : [$v[0] / $panjang, $v[1] / $panjang];
    }

    /** Anak panah dari satu titik gambar ke titik gambar lainnya. */
    public function panah(array $a, array $b, string $warna, float $tebal = 2, bool $putus = false): static
    {
        return $this->panahPx($this->px($a), $this->px($b), $warna, $tebal, $putus);
    }

    /**
     * Anak panah antara dua kedudukan piksel.
     *
     * Kepala panahnya digambar sebagai bidang, bukan sebagai penanda
     * (marker) SVG. Penanda tidak ikut terbawa saat peramban menyalin gambar
     * ini ke kanvas untuk diubah menjadi PNG, sehingga panahnya akan
     * kehilangan ujungnya justru pada berkas yang tersimpan di bank soal.
     */
    public function panahPx(array $a, array $b, string $warna, float $tebal = 2, bool $putus = false, float $kepala = 9): static
    {
        [$dx, $dy] = self::searah([$b[0] - $a[0], $b[1] - $a[1]]);

        // Garisnya berhenti di pangkal kepala panah; kalau diteruskan sampai
        // ujung, garis yang tebal menyembul melewati kepalanya.
        $pangkal = [$b[0] - $dx * $kepala, $b[1] - $dy * $kepala];
        $lebar = $kepala * 0.5;

        return $this
            ->tambah('<line x1="'.round($a[0], 1).'" y1="'.round($a[1], 1).'" x2="'.round($pangkal[0], 1)
                .'" y2="'.round($pangkal[1], 1).'" stroke="'.$warna.'" stroke-width="'.$tebal.'"'
                .($putus ? ' stroke-dasharray="6 4"' : '').' stroke-linecap="round"/>')
            ->tambah('<polygon points="'.round($b[0], 1).','.round($b[1], 1).' '
                .round($pangkal[0] - $dy * $lebar, 1).','.round($pangkal[1] + $dx * $lebar, 1).' '
                .round($pangkal[0] + $dy * $lebar, 1).','.round($pangkal[1] - $dx * $lebar, 1)
                .'" fill="'.$warna.'"/>');
    }

    /**
     * Anak panah antara dua kotak: pangkal dan ujungnya berhenti di tepi
     * kotaknya, bukan di titik pusatnya, supaya panahnya tidak tertimpa
     * kotak yang dihubungkannya.
     */
    public function panahKotakPx(array $a, array $b, float $lebar, float $tinggi, string $warna, float $tebal = 2, bool $putus = false): static
    {
        $arah = self::searah([$b[0] - $a[0], $b[1] - $a[1]]);
        $tepi = self::tepiKotak($arah, $lebar / 2 + 5, $tinggi / 2 + 5);

        // Kotak yang berdempetan tidak diberi panah: panah sepanjang beberapa
        // piksel hanya terbaca sebagai noda di antara dua kotak.
        if (hypot($b[0] - $a[0], $b[1] - $a[1]) <= 2 * $tepi + 8) {
            return $this;
        }

        return $this->panahPx(
            [$a[0] + $arah[0] * $tepi, $a[1] + $arah[1] * $tepi],
            [$b[0] - $arah[0] * $tepi, $b[1] - $arah[1] * $tepi],
            $warna, $tebal, $putus,
        );
    }

    /** Jarak dari pusat kotak ke tepinya pada suatu arah. */
    public static function tepiKotak(array $arah, float $setengahLebar, float $setengahTinggi): float
    {
        return min(
            abs($arah[0]) < 0.0001 ? INF : $setengahLebar / abs($arah[0]),
            abs($arah[1]) < 0.0001 ? INF : $setengahTinggi / abs($arah[1]),
        );
    }

    /**
     * Kotak berlabel — satu simpul pada bagan: rantai makanan, daur hidup,
     * urutan organ, papan Punnett. Ukuran dan kedudukannya dalam piksel
     * karena bagan seperti itu ditata sendiri oleh pemanggilnya.
     */
    public function kotakPx(array $pusat, float $lebar, float $tinggi, string $label, string $isi, string $garis, array $opsi = []): static
    {
        $ukuran = $opsi['ukuran'] ?? 12;

        $this->tambah('<rect x="'.round($pusat[0] - $lebar / 2, 1).'" y="'.round($pusat[1] - $tinggi / 2, 1)
            .'" width="'.round($lebar, 1).'" height="'.round($tinggi, 1).'" rx="'.($opsi['sudut'] ?? 10)
            .'" fill="'.$isi.'" stroke="'.$garis.'" stroke-width="'.($opsi['tebal_garis'] ?? 2).'"/>');

        return $this->teksBaris($pusat, self::baris($label, $lebar - 12, $ukuran), $opsi);
    }

    /**
     * Rumus kimia pada kedudukan piksel: angka setelah "_" diturunkan dan
     * angka setelah "^" dinaikkan. Lihat App\Support\Rumus untuk penandanya.
     */
    public function rumusPx(array $px, string $teks, array $opsi = []): static
    {
        $ukuran = $opsi['ukuran'] ?? 14;
        $kecil = round($ukuran * 0.72, 1);
        $geser = ['biasa' => 0.0, 'bawah' => round($ukuran * 0.24, 1), 'atas' => round($ukuran * -0.42, 1)];

        $isi = '';
        $kini = 0.0;

        // dy pada tspan bersifat menumpuk, jadi yang dituliskan selisihnya
        // terhadap simpangan yang sedang berlaku — bukan simpangan mutlaknya.
        foreach (Rumus::potong($teks) as [$bagian, $kedudukan]) {
            $isi .= '<tspan dy="'.round($geser[$kedudukan] - $kini, 1).'" font-size="'
                .($kedudukan === 'biasa' ? $ukuran : $kecil).'">'
                .htmlspecialchars($bagian, ENT_QUOTES, 'UTF-8').'</tspan>';

            $kini = $geser[$kedudukan];
        }

        return $this->tambah('<text x="'.round($px[0], 1).'" y="'.round($px[1], 1)
            .'" font-size="'.$ukuran.'" fill="'.($opsi['warna'] ?? self::HITAM).'"'
            .(($opsi['tebal'] ?? false) ? ' font-weight="600"' : '')
            .' text-anchor="'.($opsi['anchor'] ?? 'middle').'">'.$isi.'</text>');
    }

    /**
     * Tabel berlajur dengan baris pertama sebagai kepalanya.
     *
     * @param  array<int, array<int, string>>  $baris  baris pertama = kepala tabel
     * @param  array<int, float>  $lebar  lebar tiap lajur, searah dengan isi barisnya
     */
    public function tabelPx(array $kiriAtas, array $lebar, float $tinggiBaris, array $baris, string $isiKepala, string $garis, int $ukuran = 13): static
    {
        foreach (array_values($baris) as $b => $sel) {
            $y = $kiriAtas[1] + $b * $tinggiBaris;
            $x = $kiriAtas[0];
            $kepala = $b === 0;

            foreach (array_values($sel) as $k => $teks) {
                $lebarSel = $lebar[$k] ?? end($lebar);

                $this->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($lebarSel, 1)
                    .'" height="'.round($tinggiBaris, 1).'" fill="'.($kepala ? $isiKepala : '#ffffff')
                    .'" stroke="'.$garis.'" stroke-width="1.5"/>')
                    ->teksPx([$x + $lebarSel / 2, $y + $tinggiBaris / 2 + $ukuran * 0.36], $teks, [
                        'ukuran' => $ukuran, 'tebal' => $kepala,
                    ]);

                $x += $lebarSel;
            }
        }

        return $this;
    }

    /** Beberapa baris tulisan yang dipusatkan pada satu kedudukan piksel. */
    public function teksBaris(array $px, array $baris, array $opsi = []): static
    {
        $ukuran = $opsi['ukuran'] ?? 12;
        $baris = array_values($baris);

        foreach ($baris as $i => $isi) {
            $this->teksPx([
                $px[0],
                $px[1] + ($i - (count($baris) - 1) / 2) * ($ukuran + 3) + $ukuran * 0.35,
            ], $isi, $opsi);
        }

        return $this;
    }

    /**
     * Sebaran titik yang tetap di dalam sebuah bidang — partikel zat
     * terlarut, butiran pereaksi, dan sejenisnya.
     *
     * Memakai deret R2: tiap titik berikutnya digeser sejauh pecahan tetap
     * yang tak sepadan, sehingga berapa pun banyak titiknya selalu merata ke
     * seluruh bidang. Sisa bagi biasa menghasilkan titik yang berkumpul di
     * beberapa jalur ketika titiknya sedikit, dan angka acak membuat gambar
     * soal yang sama berbeda tiap kali dibuka.
     *
     * @return array<int, array<int, float>>
     */
    public static function sebaran(int $banyak, float $x, float $y, float $lebar, float $tinggi): array
    {
        $titik = [];

        for ($i = 0; $i < $banyak; $i++) {
            $titik[] = [
                $x + (0.08 + fmod(0.12 + $i * 0.7548776662, 1) * 0.84) * $lebar,
                $y + (0.08 + fmod(0.37 + $i * 0.5698402909, 1) * 0.84) * $tinggi,
            ];
        }

        return $titik;
    }

    /**
     * Pecah label menjadi beberapa baris agar muat selebar yang diberikan.
     *
     * Lebar hurufnya ditaksir, bukan diukur: server tidak memuat berkas font
     * sehingga lebar sebenarnya hanya diketahui peramban. Taksirannya
     * sengaja dibuat sedikit berlebih — label yang pecah satu baris lebih
     * awal masih terbaca, label yang meluber keluar kotaknya tidak.
     *
     * @return array<int, string>
     */
    public static function baris(string $teks, float $lebarPx, int $ukuran = 12): array
    {
        $perBaris = max(1, (int) floor($lebarPx / ($ukuran * 0.56)));
        $baris = [''];

        foreach (explode(' ', $teks) as $kata) {
            $i = count($baris) - 1;
            $gabung = $baris[$i] === '' ? $kata : $baris[$i].' '.$kata;

            if ($baris[$i] === '' || mb_strlen($gabung) <= $perBaris) {
                $baris[$i] = $gabung;

                continue;
            }

            $baris[] = $kata;
        }

        return $baris;
    }

    public function tambah(string $svg): static
    {
        $this->isi[] = $svg;

        return $this;
    }

    public function render(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$this->lebar.' '.$this->tinggi.'"'
            .' width="'.$this->lebar.'" height="'.$this->tinggi.'" font-family="Arial, Helvetica, sans-serif">'
            .'<rect width="'.$this->lebar.'" height="'.$this->tinggi.'" fill="'.$this->latar.'"/>'
            .implode('', $this->isi)
            .'</svg>';
    }
}

<?php

namespace App\Support\Template;

use App\Support\Benda;
use App\Support\Kanvas;

/**
 * Template penjumlahan & pengurangan bergambar.
 *
 * Penjumlahan digambar sebagai dua kelompok benda yang digabung, pengurangan
 * sebagai satu kelompok yang sebagian dicoret. Keduanya sengaja memakai
 * gambar yang sama bentuknya, karena yang sedang diajarkan justru bedanya:
 * menggabungkan atau mengambil.
 *
 * Selain hasilnya, yang bisa ditanyakan adalah kalimat matematikanya dan
 * bilangan yang belum diketahui — dua bentuk soal yang menguji pemahaman
 * yang sama dari arah yang berbeda.
 */
class PenjumlahanBergambar extends Template
{
    /** @var array<string, string> */
    public const OPERASI = [
        'tambah' => 'Penjumlahan (digabung)',
        'kurang' => 'Pengurangan (diambil)',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Hasilnya',
        'kalimat' => 'Kalimat matematikanya',
        'hilang' => 'Bilangan yang belum diketahui',
    ];

    public static function kode(): string
    {
        return 'penjumlahan-bergambar';
    }

    public static function nama(): string
    {
        return 'Penjumlahan & Pengurangan Bergambar';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Gabung atau ambil sebagian kelompok benda, cari bilangan yang belum '
            .'diketahui, lalu pilih kalimat matematikanya.';
    }

    public static function ikon(): string
    {
        return 'bi-plus-slash-minus';
    }

    public static function kataKunci(): array
    {
        return ['penjumlahan', 'pengurangan', 'tambah', 'kurang', 'kalimat matematika', 'sd', 'kelas 1'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'operasi',
                'label' => 'Operasi',
                'jenis' => 'pilihan',
                'pilihan' => self::OPERASI,
                'awal' => 'tambah',
            ],
            self::kolomAngka('bilangan_1', 'Bilangan pertama', 5, 1, 50, ['langkah' => 1]),
            self::kolomAngka('bilangan_2', 'Bilangan kedua', 3, 1, 50, ['langkah' => 1]),
            self::kolomBenda('bintang'),
            self::kolomTanya(self::TANYA, 'hasil', 6),
            self::kolomWarna('kuning'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['operasi'] ?? '') === 'kurang'
            && (int) ($isi['bilangan_2'] ?? 0) >= (int) ($isi['bilangan_1'] ?? 0)) {
            return ['bilangan_2' => 'Yang diambil harus lebih sedikit daripada yang tersedia.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $a = (int) $isi['bilangan_1'];
        $b = (int) $isi['bilangan_2'];
        $tambah = ($isi['operasi'] ?? 'tambah') === 'tambah';
        $hasil = $tambah ? $a + $b : $a - $b;

        $benda = $isi['benda'];
        $nama = Benda::sebutan($benda);
        [$warnaIsi, $garis] = self::warna($isi, 'kuning');

        $tanda = $tambah ? '+' : '−';
        $kalimat = fn (int $x) => "{$a} {$tanda} {$b} = {$x}";
        $svg = self::svg($tambah, $a, $b, $benda, $warnaIsi, $garis);

        $keterangan = $tambah
            ? "Dua kelompok {$nama}: {$a} dan {$b}."
            : "Satu kelompok berisi {$a} {$nama}, {$b} di antaranya dicoret.";

        $cerita = $tambah
            ? "Perhatikan gambar berikut. Dua kelompok {$nama} digabungkan."
            : "Perhatikan gambar berikut. Dari sekelompok {$nama}, yang dicoret diambil.";

        $dasar = [
            'judul' => $kalimat($hasil),
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pembahasan' => $kalimat($hasil).'.',
        ];

        return match ($isi['tanya'] ?? 'hasil') {
            'kalimat' => $dasar + [
                'teks' => $cerita.' Kalimat matematika yang sesuai dengan gambar itu adalah ...',
                'pernyataan' => $cerita.' Kalimat matematika yang sesuai dengan gambar itu adalah %s.',
                'pilihan' => $this->pilihanKalimat($a, $b, $tanda, $hasil),
                'kata_kunci' => $kalimat($hasil),
            ],
            'hilang' => $dasar + [
                'teks' => $cerita." Lengkapilah kalimat matematikanya: {$a} {$tanda} ... = {$hasil}",
                'pernyataan' => $cerita." Pada kalimat {$a} {$tanda} ... = {$hasil}, bilangan yang belum diketahui adalah %s.",
                'pilihan' => $this->pilihanAngka($b),
                'kata_kunci' => (string) $b,
                'pembahasan' => "{$a} {$tanda} ... = {$hasil}, jadi bilangan yang belum diketahui adalah {$b}. "
                    .$kalimat($hasil).'.',
            ],
            default => $dasar + [
                'teks' => $cerita." Berapa banyak {$nama} sekarang?",
                'pernyataan' => $cerita." Banyak {$nama} sekarang ada %s.",
                'pilihan' => $this->pilihanAngka($hasil),
                'kata_kunci' => (string) $hasil,
            ],
        };
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected function pilihanAngka(int $benar): array
    {
        return array_map(
            fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
            array_merge([$benar], self::saring($benar, [
                $benar + 1, $benar - 1, $benar + 2, $benar - 2, $benar + 10,
            ], 0)),
        );
    }

    /**
     * Pengecoh kalimat matematika: tandanya ditukar dan hasilnya meleset
     * satu. Keduanya kekeliruan yang terbaca jelas oleh guru — yang pertama
     * berarti siswa salah membaca gambar, yang kedua salah berhitung.
     *
     * @return array<int, array{teks: string, urut: string}>
     */
    protected function pilihanKalimat(int $a, int $b, string $tanda, int $hasil): array
    {
        $lawan = $tanda === '+' ? '−' : '+';

        $kandidat = [
            "{$a} {$tanda} {$b} = {$hasil}",
            "{$a} {$lawan} {$b} = ".($tanda === '+' ? $a - $b : $a + $b),
            "{$a} {$tanda} {$b} = ".($hasil + 1),
            "{$b} {$tanda} {$a} = ".($hasil + 2),
        ];

        return array_map(fn ($teks) => ['teks' => $teks, 'urut' => $teks], array_unique($kandidat));
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(bool $tambah, int $a, int $b, string $benda, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        if ($tambah) {
            // Dua kelompok dipisahkan tanda tambah di tengah, masing-masing
            // menempati separuh kanvas.
            self::kelompok($kanvas, $a, 24, 40, 210, 260, $benda, $isi, $garis);
            self::kelompok($kanvas, $b, 270, 40, 456, 260, $benda, $isi, $garis);

            $kanvas->teksPx([240, 160], '+', ['ukuran' => 40, 'tebal' => true]);

            return $kanvas->render();
        }

        // Pengurangan: satu kelompok, yang diambil ditandai silang.
        $titik = self::kelompok($kanvas, $a, 40, 40, 440, 260, $benda, $isi, $garis);

        foreach (array_slice($titik, $a - $b) as [$x, $y, $r]) {
            $kanvas->garis([$x - $r, $y - $r], [$x + $r, $y + $r], '#b23a5c', 3)
                ->garis([$x + $r, $y - $r], [$x - $r, $y + $r], '#b23a5c', 3);
        }

        return $kanvas->render();
    }

    /**
     * Tata sejumlah benda rapat di dalam sebuah kotak, lalu kembalikan titik
     * pusat beserta jari-jarinya supaya pemanggil bisa menandainya.
     *
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    protected static function kelompok(Kanvas $k, int $banyak, float $x1, float $y1, float $x2, float $y2, string $benda, string $isi, string $garis): array
    {
        $banyak = max(1, $banyak);
        $perBaris = max(1, (int) ceil(sqrt($banyak * ($x2 - $x1) / max(1, $y2 - $y1))));
        $baris = (int) ceil($banyak / $perBaris);

        $jarak = min(($x2 - $x1) / $perBaris, ($y2 - $y1) / $baris, 60);
        $r = $jarak * 0.38;
        $pangkalY = ($y1 + $y2) / 2 - ($baris - 1) * $jarak / 2;

        $titik = [];

        for ($i = 0; $i < $banyak; $i++) {
            $barisKe = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, $banyak - $barisKe * $perBaris);
            $x = ($x1 + $x2) / 2 - ($isiBaris - 1) * $jarak / 2 + ($i % $perBaris) * $jarak;
            $y = $pangkalY + $barisKe * $jarak;

            Benda::gambar($k, [$x, $y], $r, $benda, $isi, $garis);
            $titik[] = [$x, $y, $r];
        }

        return $titik;
    }
}

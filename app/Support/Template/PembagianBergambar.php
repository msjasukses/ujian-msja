<?php

namespace App\Support\Template;

use App\Support\Benda;
use App\Support\Kanvas;

/**
 * Template pembagian bergambar: membagi rata benda ke beberapa kelompok.
 *
 * Pembagian diperkenalkan sebagai "dibagi rata", bukan sebagai lawan
 * perkalian, jadi gambarnya memang berupa kotak-kotak kelompok yang isinya
 * sama banyak. Sisa pembagian digambar di luar kotak — itulah yang membuat
 * gagasan "sisa" terlihat, bukan sekadar disebut.
 */
class PembagianBergambar extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'hasil' => 'Isi tiap kelompok',
        'sisa' => 'Sisa pembagian',
        'kalimat' => 'Kalimat matematikanya',
    ];

    public static function kode(): string
    {
        return 'pembagian-bergambar';
    }

    public static function nama(): string
    {
        return 'Pembagian';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Bagi rata benda ke beberapa kelompok, lalu tanya isi tiap kelompok '
            .'atau sisa pembagiannya.';
    }

    public static function ikon(): string
    {
        return 'bi-slash-square';
    }

    public static function kataKunci(): array
    {
        return ['pembagian', 'bagi', 'sisa', 'kelompok', 'dibagi rata', 'sd'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomAngka('banyak', 'Banyak benda', 12, 2, 60, ['langkah' => 1]),
            self::kolomAngka('kelompok', 'Banyak kelompok', 4, 2, 8, ['langkah' => 1]),
            self::kolomBenda('permen'),
            self::kolomTanya(self::TANYA, 'hasil', 6),
            self::kolomWarna('hijau'),
        ];
    }

    public static function periksa(array $isi): array
    {
        if ((int) ($isi['kelompok'] ?? 0) > (int) ($isi['banyak'] ?? 0)) {
            return ['kelompok' => 'Kelompoknya tidak boleh lebih banyak daripada bendanya.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $banyak = (int) $isi['banyak'];
        $kelompok = (int) $isi['kelompok'];
        $tiap = intdiv($banyak, $kelompok);
        $sisa = $banyak % $kelompok;

        $benda = $isi['benda'];
        $nama = Benda::sebutan($benda);
        [$warnaIsi, $garis] = self::warna($isi, 'hijau');

        $cerita = "Perhatikan gambar berikut. {$banyak} {$nama} dibagi rata ke dalam {$kelompok} kotak.";
        $kalimat = "{$banyak} : {$kelompok} = {$tiap}".($sisa > 0 ? " sisa {$sisa}" : '');

        $dasar = [
            'judul' => $kalimat,
            'svg' => self::svg($banyak, $kelompok, $benda, $warnaIsi, $garis),
            'keterangan' => "{$kelompok} kotak berisi {$tiap} {$nama}"
                .($sisa > 0 ? ", dan {$sisa} {$nama} di luar kotak" : '').'.',
            'pembahasan' => "{$banyak} {$nama} dibagi rata ke {$kelompok} kotak: tiap kotak berisi {$tiap} "
                .($sisa > 0 ? "{$nama}, dan tersisa {$sisa} yang belum kebagian. " : "{$nama}. ")
                .$kalimat.'.',
        ];

        return match ($isi['tanya'] ?? 'hasil') {
            'sisa' => $dasar + [
                'teks' => $cerita.' Berapa benda yang tersisa?',
                'pernyataan' => $cerita.' Benda yang tersisa ada %s.',
                'pilihan' => $this->pilihanAngka($sisa, [$tiap, $kelompok, $sisa + 1, $sisa + 2]),
                'kata_kunci' => (string) $sisa,
            ],
            'kalimat' => $dasar + [
                'teks' => $cerita.' Kalimat matematika yang sesuai adalah ...',
                'pernyataan' => $cerita.' Kalimat matematika yang sesuai adalah %s.',
                'pilihan' => array_map(
                    fn ($teks) => ['teks' => $teks, 'urut' => $teks],
                    array_values(array_unique([
                        $kalimat,
                        "{$banyak} : {$kelompok} = ".($tiap + 1),
                        "{$kelompok} : {$banyak} = {$tiap}",
                        "{$banyak} × {$kelompok} = ".($banyak * $kelompok),
                    ])),
                ),
                'kata_kunci' => $kalimat,
            ],
            default => $dasar + [
                'teks' => $cerita." Berapa banyak {$nama} dalam setiap kotak?",
                'pernyataan' => $cerita." Setiap kotak berisi %s {$nama}.",
                'pilihan' => $this->pilihanAngka($tiap, [$tiap + 1, $tiap - 1, $kelompok, $sisa]),
                'kata_kunci' => (string) $tiap,
            ],
        };
    }

    /** @return array<int, array{teks: string, urut: float}> */
    protected function pilihanAngka(int $benar, array $kandidat): array
    {
        return array_map(
            fn ($n) => ['teks' => (string) (int) $n, 'urut' => $n],
            array_merge([$benar], self::saring($benar, array_merge($kandidat, [
                $benar + 3, $benar + 4, $benar + 5,
            ]), 0)),
        );
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(int $banyak, int $kelompok, string $benda, string $isi, string $garis): string
    {
        $tiap = intdiv($banyak, $kelompok);
        $sisa = $banyak % $kelompok;

        $kanvas = new Kanvas;

        $perBaris = min(4, $kelompok);
        $baris = (int) ceil($kelompok / $perBaris);
        $tinggiKotak = ($sisa > 0 ? 200 : 250) / $baris - 12;
        $lebarKotak = (Kanvas::LEBAR - 40) / $perBaris - 12;

        for ($i = 0; $i < $kelompok; $i++) {
            $kolom = $i % $perBaris;
            $barisKe = intdiv($i, $perBaris);
            $x = 20 + $kolom * ($lebarKotak + 12);
            $y = 20 + $barisKe * ($tinggiKotak + 12);

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($lebarKotak, 1)
                .'" height="'.round($tinggiKotak, 1).'" rx="8" fill="#ffffff" stroke="'.Kanvas::HITAM
                .'" stroke-width="2"/>');

            self::taruh($kanvas, $tiap, $x + 6, $y + 6, $x + $lebarKotak - 6, $y + $tinggiKotak - 6, $benda, $isi, $garis);
        }

        if ($sisa > 0) {
            $kanvas->teksPx([20, 246], 'Sisa:', ['anchor' => 'start', 'ukuran' => 14, 'tebal' => true]);
            self::taruh($kanvas, $sisa, 70, 222, 70 + $sisa * 40, 272, $benda, $isi, $garis);
        }

        return $kanvas->render();
    }

    /** Tata benda rapat di dalam sebuah kotak. */
    protected static function taruh(Kanvas $k, int $banyak, float $x1, float $y1, float $x2, float $y2, string $benda, string $isi, string $garis): void
    {
        if ($banyak < 1) {
            return;
        }

        $perBaris = max(1, (int) ceil(sqrt($banyak * ($x2 - $x1) / max(1, $y2 - $y1))));
        $barisBanyak = (int) ceil($banyak / $perBaris);

        $jarak = min(($x2 - $x1) / $perBaris, ($y2 - $y1) / $barisBanyak, 44);
        $r = $jarak * 0.38;
        $pangkalY = ($y1 + $y2) / 2 - ($barisBanyak - 1) * $jarak / 2;

        for ($i = 0; $i < $banyak; $i++) {
            $barisKe = intdiv($i, $perBaris);
            $isiBaris = min($perBaris, $banyak - $barisKe * $perBaris);

            Benda::gambar($k, [
                ($x1 + $x2) / 2 - ($isiBaris - 1) * $jarak / 2 + ($i % $perBaris) * $jarak,
                $pangkalY + $barisKe * $jarak,
            ], $r, $benda, $isi, $garis);
        }
    }
}

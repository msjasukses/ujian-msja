<?php

namespace App\Support\Template;

use App\Support\Angka;
use App\Support\Kanvas;

/**
 * Template jam & waktu: membaca jam analog dan menghitung waktu selesai.
 *
 * Jarum pendek sengaja digambar ikut bergeser mengikuti menitnya — pukul
 * 07.45 membuatnya hampir menyentuh angka 8. Itulah justru yang membuat soal
 * ini bermakna: siswa yang membaca jarum pendek sebagai "8" akan menjawab
 * 08.45, dan jawaban keliru itu memang disediakan sebagai pengecoh.
 */
class JamWaktu extends Template
{
    /** @var array<string, string> */
    public const TANYA = [
        'baca' => 'Membaca jam',
        'selesai' => 'Pukul berapa selesai',
    ];

    public static function kode(): string
    {
        return 'jam-waktu';
    }

    public static function nama(): string
    {
        return 'Jam & Waktu';
    }

    public static function mapel(): string
    {
        return 'Matematika';
    }

    public static function ringkasan(): string
    {
        return 'Jam dinding analog: membaca waktu yang ditunjukkan, '
            .'atau menghitung pukul berapa kegiatan berakhir.';
    }

    public static function ikon(): string
    {
        return 'bi-clock';
    }

    public static function kataKunci(): array
    {
        return ['jam', 'waktu', 'pukul', 'menit', 'durasi', 'jadwal', 'sd'];
    }

    public static function kolom(): array
    {
        return [
            [
                'nama' => 'tanya',
                'label' => 'Yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => self::TANYA,
                'awal' => 'baca',
            ],
            [
                'nama' => 'jam',
                'label' => 'Jam yang ditunjukkan',
                'jenis' => 'angka',
                'awal' => 7,
                'min' => 0,
                'max' => 23,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'menit',
                'label' => 'Menit yang ditunjukkan',
                'jenis' => 'angka',
                'awal' => 45,
                'min' => 0,
                'max' => 59,
                'langkah' => 1,
                'lebar' => 6,
            ],
            [
                'nama' => 'durasi_jam',
                'label' => 'Lama kegiatan (jam)',
                'jenis' => 'angka',
                'awal' => 1,
                'min' => 0,
                'max' => 12,
                'langkah' => 1,
                'lebar' => 6,
                'tampil' => ['tanya' => ['selesai']],
            ],
            [
                'nama' => 'durasi_menit',
                'label' => 'Lama kegiatan (menit)',
                'jenis' => 'angka',
                'awal' => 30,
                'min' => 0,
                'max' => 59,
                'langkah' => 1,
                'lebar' => 6,
                'tampil' => ['tanya' => ['selesai']],
            ],
            [
                'nama' => 'kegiatan',
                'label' => 'Nama kegiatan',
                'jenis' => 'teks',
                'awal' => 'belajar kelompok',
                'tampil' => ['tanya' => ['selesai']],
            ],
            self::kolomWarna(),
        ];
    }

    public static function periksa(array $isi): array
    {
        if (($isi['tanya'] ?? '') === 'selesai'
            && (int) ($isi['durasi_jam'] ?? 0) === 0
            && (int) ($isi['durasi_menit'] ?? 0) === 0) {
            return ['durasi_menit' => 'Lama kegiatan tidak boleh nol jam nol menit.'];
        }

        return [];
    }

    protected function susun(array $isi): array
    {
        $jam = (int) $isi['jam'];
        $menit = (int) $isi['menit'];
        $svg = self::svg($jam, $menit, $isi['warna'] ?? 'putih');
        $keterangan = 'Jam dinding dengan jarum pendek dan jarum panjang.';

        if (($isi['tanya'] ?? 'baca') === 'baca') {
            return [
                'judul' => 'Jam '.Angka::pukul($jam, $menit),
                'teks' => 'Perhatikan gambar jam berikut. Pukul berapakah yang ditunjukkan jam tersebut?',
                'pernyataan' => 'Perhatikan gambar jam berikut. Jam tersebut menunjukkan pukul %s.',
                'svg' => $svg,
                'keterangan' => $keterangan,
                'pilihan' => $this->pilihanBaca($jam, $menit),
                'kata_kunci' => Angka::pukul($jam, $menit),
                'pembahasan' => $this->pembahasanBaca($jam, $menit),
            ];
        }

        $dj = (int) $isi['durasi_jam'];
        $dm = (int) $isi['durasi_menit'];
        $kegiatan = self::bebas($isi, 'kegiatan', 'kegiatan');
        $lama = self::lama($dj, $dm);

        $akhir = ($jam * 60 + $menit + $dj * 60 + $dm) % (24 * 60);

        return [
            'judul' => 'Waktu selesai '.self::dariMenit($akhir),
            'teks' => "Perhatikan gambar jam berikut. {$kegiatan} dimulai pada waktu yang ditunjukkan jam itu "
                ."dan berlangsung selama {$lama}. Pukul berapakah {$kegiatan} selesai?",
            'pernyataan' => "Perhatikan gambar jam berikut. {$kegiatan} dimulai pada waktu yang ditunjukkan jam itu "
                ."dan berlangsung selama {$lama}, sehingga selesai pukul %s.",
            'svg' => $svg,
            'keterangan' => $keterangan,
            'pilihan' => $this->pilihanSelesai($jam, $menit, $dj, $dm),
            'kata_kunci' => self::dariMenit($akhir),
            'pembahasan' => 'Mulai pukul '.Angka::pukul($jam, $menit)." ditambah {$lama} = pukul "
                .self::dariMenit($akhir).'.',
        ];
    }

    /**
     * Pengecoh membaca jam: jarum pendek dibaca sebagai angka berikutnya,
     * jarum panjang dibaca sebagai angka yang ditunjuknya alih-alih
     * menitnya, dan menit dihitung mundur dari 60.
     *
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function pilihanBaca(int $jam, int $menit): array
    {
        $kandidat = [
            [$jam, $menit],
            [($jam + 1) % 24, $menit],
            [$jam, intdiv($menit, 5)],
            [$jam, (60 - $menit) % 60],
            [($jam + 11) % 24, $menit],
        ];

        return $this->rapikan($kandidat);
    }

    /**
     * Pengecoh menghitung waktu selesai: menitnya lupa dijumlahkan, jamnya
     * lupa dijumlahkan, dan lamanya justru dikurangkan.
     *
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function pilihanSelesai(int $jam, int $menit, int $dj, int $dm): array
    {
        $mulai = $jam * 60 + $menit;
        $lama = $dj * 60 + $dm;

        // Lewat tengah malam dihitung berputar kembali ke pukul 00.00,
        // termasuk untuk pengecoh yang justru mengurangkan lamanya.
        $hari = 24 * 60;
        $jadi = fn (int $m) => [intdiv(($m % $hari + $hari) % $hari, 60), ($m % $hari + $hari) % $hari % 60];

        return $this->rapikan(array_map($jadi, [
            $mulai + $lama,         // benar
            $mulai + $dj * 60,      // menitnya lupa dijumlahkan
            $mulai + $dm,           // jamnya lupa dijumlahkan
            $mulai - $lama,         // justru dikurangkan
            $mulai + $lama + 60,
        ]));
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $kandidat
     * @return array<int, array{teks: string, urut: float}>
     */
    protected function rapikan(array $kandidat): array
    {
        $dipakai = [];
        $pilihan = [];

        foreach ($kandidat as [$j, $m]) {
            $teks = Angka::pukul($j, $m);

            if (isset($dipakai[$teks])) {
                continue;
            }

            $dipakai[$teks] = true;
            $pilihan[] = ['teks' => $teks, 'urut' => $j * 60 + $m];
        }

        return $pilihan;
    }

    protected function pembahasanBaca(int $jam, int $menit): string
    {
        $pendek = $jam % 12 === 0 ? 12 : $jam % 12;
        $berikut = $pendek === 12 ? 1 : $pendek + 1;

        $panjang = $menit % 5 === 0
            ? 'Jarum panjang menunjuk angka '.($menit === 0 ? 12 : intdiv($menit, 5))
            : 'Jarum panjang berada '.$menit.' menit setelah angka 12';

        return "Jarum pendek berada di antara angka {$pendek} dan {$berikut}, jadi jamnya {$pendek}. "
            ."{$panjang}, jadi menitnya {$menit}. Waktu yang ditunjukkan pukul "
            .Angka::pukul($jam, $menit).'.';
    }

    protected static function lama(int $jam, int $menit): string
    {
        $bagian = [];

        if ($jam > 0) {
            $bagian[] = $jam.' jam';
        }
        if ($menit > 0) {
            $bagian[] = $menit.' menit';
        }

        return $bagian === [] ? '0 menit' : implode(' ', $bagian);
    }

    protected static function dariMenit(int $menit): string
    {
        return Angka::pukul(intdiv($menit, 60), $menit % 60);
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    public static function svg(int $jam, int $menit, string $warna): string
    {
        [$isi, $garis] = self::WARNA[$warna] ?? self::WARNA['putih'];

        $kanvas = new Kanvas;
        $kanvas->pas([[0, 0], [2, 2]], 30);

        // Titik pada jarak r dari pusat, diukur searah jarum jam dari pukul 12.
        $titik = fn (float $derajat, float $r) => [
            1 + $r * sin(deg2rad($derajat)),
            1 - $r * cos(deg2rad($derajat)),
        ];

        $kanvas->lingkaran([1, 1], 1, $isi, $garis, 3);

        for ($i = 0; $i < 60; $i++) {
            $panjang = $i % 5 === 0 ? 0.1 : 0.05;
            $kanvas->garis(
                $titik($i * 6, 1 - $panjang),
                $titik($i * 6, 0.97),
                $garis,
                $i % 5 === 0 ? 2.5 : 1,
            );
        }

        for ($i = 1; $i <= 12; $i++) {
            $kanvas->teks($titik($i * 30, 0.76), (string) $i, ['ukuran' => 22, 'tebal' => true, 'geser' => [0, 8]]);
        }

        // Jarum pendek ikut bergeser mengikuti menit — tiap menit memutarnya
        // setengah derajat — sehingga gambarnya jujur terhadap jam sungguhan.
        $kanvas->garis([1, 1], $titik(($jam % 12) * 30 + $menit * 0.5, 0.5), $garis, 7)
            ->garis([1, 1], $titik($menit * 6, 0.8), $garis, 4.5)
            ->lingkaran([1, 1], 0.05, $garis, $garis, 1);

        return $kanvas->render();
    }
}

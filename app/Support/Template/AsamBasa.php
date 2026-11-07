<?php

namespace App\Support\Template;

use App\Support\Kanvas;
use App\Support\Rumus;

/**
 * Template asam, basa & pH.
 *
 * Skala pH-nya digambar berwarna seperti indikator universal, dan penanda
 * pH larutan diletakkan dari angka yang sama dengan yang dipakai menghitung
 * kunci — jadi siswa benar-benar membaca gambarnya, bukan menghafal nama
 * larutan. Soal menghitung pH asam dan basa kuat sengaja tidak memberi
 * penanda apa pun pada skalanya: skala itu hanya acuan, jawabannya dihitung.
 */
class AsamBasa extends Template
{
    /**
     * Larutan sehari-hari beserta pH-nya. Angka pH-nya bulat supaya penanda
     * pada skala jatuh tepat di satu kotak dan tidak ada yang bisa
     * diperdebatkan saat soalnya dibahas di kelas.
     *
     * @var array<string, array{nama: string, ph: int}>
     */
    public const LARUTAN = [
        'jeruk' => ['nama' => 'air jeruk', 'ph' => 2],
        'cuka' => ['nama' => 'larutan cuka', 'ph' => 3],
        'hujan' => ['nama' => 'air hujan', 'ph' => 5],
        'susu' => ['nama' => 'susu segar', 'ph' => 6],
        'murni' => ['nama' => 'air murni', 'ph' => 7],
        'garam' => ['nama' => 'larutan garam dapur', 'ph' => 7],
        'soda' => ['nama' => 'larutan soda kue', 'ph' => 9],
        'sabun' => ['nama' => 'air sabun', 'ph' => 11],
        'kapur' => ['nama' => 'air kapur', 'ph' => 12],
        'naoh' => ['nama' => 'larutan natrium hidroksida', 'ph' => 13],
    ];

    /**
     * Asam dan basa kuat bervalensi satu. Hanya yang bervalensi satu dipakai
     * supaya pH-nya langsung sama dengan pangkat kemolarannya, tanpa langkah
     * tambahan yang belum diajarkan di tingkat ini.
     *
     * @var array<string, array{rumus: string, nama: string, asam: bool}>
     */
    public const PEKAT = [
        'hcl' => ['rumus' => 'HCl', 'nama' => 'asam klorida', 'asam' => true],
        'hno3' => ['rumus' => 'HNO_3', 'nama' => 'asam nitrat', 'asam' => true],
        'naoh' => ['rumus' => 'NaOH', 'nama' => 'natrium hidroksida', 'asam' => false],
        'koh' => ['rumus' => 'KOH', 'nama' => 'kalium hidroksida', 'asam' => false],
    ];

    /** @var array<string, string> */
    public const KEMOLARAN = [
        '0.1' => '0,1 M',
        '0.01' => '0,01 M',
        '0.001' => '0,001 M',
        '0.0001' => '0,0001 M',
    ];

    /**
     * Warna indikator universal untuk pH 0 sampai 14. Merah di ujung asam,
     * hijau di tengah, ungu di ujung basa — urutan yang sama dengan kartu
     * warna yang ditempel pada wadah kertas indikator.
     *
     * @var array<int, string>
     */
    public const WARNA_PH = [
        '#d32f2f', '#e14b28', '#ef6c2a', '#f59120', '#f7b520', '#f3d41d', '#c7d92e',
        '#4caf50', '#36b3a0', '#2f9fd0', '#2f6fd0', '#4a4fc0', '#6a3fb5', '#7a2fa0', '#6b1f7a',
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'sifat' => 'Sifat larutan beserta alasannya',
        'lakmus' => 'Perubahan warna kertas lakmus',
        'hitung' => 'pH asam atau basa kuat dari kemolarannya',
    ];

    public static function kode(): string
    {
        return 'asam-basa';
    }

    public static function nama(): string
    {
        return 'Asam, Basa & pH';
    }

    public static function mapel(): string
    {
        return 'Kimia';
    }

    public static function ringkasan(): string
    {
        return 'Skala pH berwarna dan kertas lakmus: menentukan sifat larutan, '
            .'perubahan warna lakmus, serta menghitung pH asam dan basa kuat.';
    }

    public static function ikon(): string
    {
        return 'bi-droplet-half';
    }

    public static function kataKunci(): array
    {
        return ['asam', 'basa', 'netral', 'ph', 'poh', 'indikator', 'lakmus',
            'indikator universal', 'larutan', 'kimia', 'asam kuat', 'basa kuat',
            'kemolaran', 'molaritas'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'sifat'),
            [
                'nama' => 'larutan',
                'label' => 'Larutan yang diuji',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($l) => ucfirst($l['nama']).' (pH '.$l['ph'].')', self::LARUTAN),
                'awal' => 'cuka',
                'tampil' => ['tanya' => ['sifat', 'lakmus']],
            ],
            [
                'nama' => 'zat',
                'label' => 'Zat terlarut',
                'jenis' => 'pilihan',
                'pilihan' => array_map(
                    fn ($z) => Rumus::polos($z['rumus']).' — '.$z['nama'].' ('.($z['asam'] ? 'asam' : 'basa').' kuat)',
                    self::PEKAT,
                ),
                'awal' => 'hcl',
                'tampil' => ['tanya' => ['hitung']],
                'lebar' => 6,
            ],
            [
                'nama' => 'kemolaran',
                'label' => 'Kemolaran larutan',
                'jenis' => 'pilihan',
                'pilihan' => self::KEMOLARAN,
                'awal' => '0.01',
                'tampil' => ['tanya' => ['hitung']],
                'lebar' => 6,
            ],
            self::kolomWarna('biru'),
        ];
    }

    /** Sifat larutan dari pH-nya. */
    public static function sifatPh(int $ph): string
    {
        return match (true) {
            $ph < 7 => 'Asam',
            $ph > 7 => 'Basa',
            default => 'Netral',
        };
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        if (($isi['tanya'] ?? 'sifat') === 'hitung') {
            return $this->hitung(
                isset(self::PEKAT[$isi['zat'] ?? '']) ? $isi['zat'] : 'hcl',
                isset(self::KEMOLARAN[$isi['kemolaran'] ?? '']) ? $isi['kemolaran'] : '0.01',
                $warnaIsi, $garis,
            );
        }

        $kunci = isset(self::LARUTAN[$isi['larutan'] ?? '']) ? $isi['larutan'] : 'cuka';
        $larutan = self::LARUTAN[$kunci];

        return ($isi['tanya'] ?? 'sifat') === 'lakmus'
            ? $this->lakmus($larutan, $warnaIsi, $garis)
            : $this->sifat($larutan, $warnaIsi, $garis);
    }

    protected function sifat(array $larutan, string $warnaIsi, string $garis): array
    {
        $ph = $larutan['ph'];
        $sifat = self::sifatPh($ph);

        $benar = match ($sifat) {
            'Asam' => 'Asam, karena pH-nya kurang dari 7',
            'Basa' => 'Basa, karena pH-nya lebih dari 7',
            default => 'Netral, karena pH-nya sama dengan 7',
        };

        return [
            'judul' => ucfirst($larutan['nama']).' bersifat '.mb_strtolower($sifat),
            'teks' => 'Perhatikan hasil pengujian '.$larutan['nama'].' dengan indikator universal '
                .'berikut. Larutan itu bersifat ...',
            'pernyataan' => 'Pada hasil pengujian '.$larutan['nama'].' berikut, larutan itu bersifat %s.',
            'svg' => self::svgUji($larutan['nama'], $ph, null, false, $warnaIsi, $garis),
            'keterangan' => ucfirst($larutan['nama']).' menunjukkan pH '.$ph.' pada skala indikator universal.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya memasangkan nama sifat dengan alasan yang
                // keliru, sehingga yang hanya hafal "cuka itu asam" tetap
                // harus membaca skalanya.
                array_merge([$benar], array_values(array_diff([
                    'Asam, karena pH-nya kurang dari 7',
                    'Basa, karena pH-nya lebih dari 7',
                    'Netral, karena pH-nya sama dengan 7',
                    'Asam, karena pH-nya lebih dari 7',
                ], [$benar]))),
            ),
            'kata_kunci' => $sifat,
            'pembahasan' => 'Penanda pada skala menunjukkan pH '.$ph.'.<br>Larutan dengan pH kurang '
                .'dari 7 bersifat asam, tepat 7 bersifat netral, dan lebih dari 7 bersifat basa. '
                .'Karena pH-nya '.$ph.', '.$larutan['nama'].' bersifat '.mb_strtolower($sifat).'.',
        ];
    }

    protected function lakmus(array $larutan, string $warnaIsi, string $garis): array
    {
        $ph = $larutan['ph'];
        $sifat = self::sifatPh($ph);

        $benar = match ($sifat) {
            'Asam' => 'Lakmus merah tetap merah dan lakmus biru menjadi merah',
            'Basa' => 'Lakmus merah menjadi biru dan lakmus biru tetap biru',
            default => 'Lakmus merah dan lakmus biru sama-sama tidak berubah',
        };

        return [
            'judul' => 'Lakmus pada '.$larutan['nama'],
            'teks' => 'Perhatikan hasil pengujian '.$larutan['nama'].' berikut. Bila kertas lakmus '
                .'merah dan kertas lakmus biru dicelupkan ke dalamnya, apa yang terjadi?',
            'pernyataan' => 'Bila lakmus merah dan lakmus biru dicelupkan ke dalam '
                .$larutan['nama'].' berikut, %s.',
            'svg' => self::svgUji($larutan['nama'], $ph, null, true, $warnaIsi, $garis),
            'keterangan' => ucfirst($larutan['nama']).' ber-pH '.$ph.' diuji dengan kertas lakmus merah dan biru.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Lakmus merah tetap merah dan lakmus biru menjadi merah',
                    'Lakmus merah menjadi biru dan lakmus biru tetap biru',
                    'Lakmus merah dan lakmus biru sama-sama tidak berubah',
                    // Pilihan yang mustahil ini sengaja ada: kedua kertas
                    // bertukar warna hanya masuk akal bila siswa mengira
                    // lakmus berubah mengikuti warnanya sendiri.
                    'Lakmus merah menjadi biru dan lakmus biru menjadi merah',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Skala menunjukkan pH '.$ph.', jadi '.$larutan['nama'].' bersifat '
                .mb_strtolower($sifat).'.<br>Dalam larutan asam kedua kertas lakmus berwarna merah, '
                .'dalam larutan basa keduanya berwarna biru, dan dalam larutan netral keduanya '
                .'tidak berubah. Karena itu '.mb_strtolower($benar).'.',
        ];
    }

    protected function hitung(string $kunci, string $kemolaran, string $warnaIsi, string $garis): array
    {
        $zat = self::PEKAT[$kunci];
        $pangkat = (int) round(-log10((float) $kemolaran));

        // Asam kuat bervalensi satu: [H+] sama dengan kemolarannya, jadi
        // pH = pangkatnya. Basa kuat menghasilkan pOH sebesar itu, dan
        // pH-nya 14 dikurangi pOH.
        $benar = $zat['asam'] ? $pangkat : 14 - $pangkat;
        $rumus = Rumus::html($zat['rumus']);
        $molarTeks = self::KEMOLARAN[$kemolaran];

        return [
            'judul' => 'pH '.Rumus::polos($zat['rumus']).' '.$molarTeks.' = '.$benar,
            'teks' => "Sebanyak satu liter larutan {$rumus} {$molarTeks} diuji keasamannya. "
                .'Berapakah pH larutan itu?',
            'pernyataan' => "Larutan {$rumus} {$molarTeks} memiliki pH sebesar %s.",
            'svg' => self::svgUji(Rumus::polos($zat['rumus']).' '.$molarTeks, null, $molarTeks, false, $warnaIsi, $garis),
            'keterangan' => 'Larutan '.Rumus::polos($zat['rumus']).' '.$molarTeks.', '
                .($zat['asam'] ? 'asam' : 'basa').' kuat bervalensi satu.',
            'pilihan' => array_map(
                fn ($n) => ['teks' => (string) $n, 'urut' => $n],
                array_merge([$benar], array_values(array_filter(array_unique([
                    // Pengecoh terpentingnya tertukar antara pH dan pOH —
                    // kekeliruan yang hampir selalu muncul pada soal basa.
                    14 - $benar, $pangkat, 14 - $pangkat, $benar + 1, $benar - 1, 7,
                ]), fn ($n) => $n >= 0 && $n <= 14 && $n !== $benar))),
            ),
            'kata_kunci' => (string) $benar,
            'pembahasan' => $zat['asam']
                ? Rumus::html($zat['rumus']).' adalah asam kuat bervalensi satu, jadi '
                    .'[H<sup>+</sup>] = kemolarannya = '.$molarTeks.'.<br>pH = −log [H<sup>+</sup>] = −log 10<sup>−'
                    .$pangkat.'</sup> = '.$benar.'.'
                : Rumus::html($zat['rumus']).' adalah basa kuat bervalensi satu, jadi '
                    .'[OH<sup>−</sup>] = kemolarannya = '.$molarTeks.'.<br>pOH = −log [OH<sup>−</sup>] = '
                    .$pangkat.', lalu pH = 14 − pOH = 14 − '.$pangkat.' = '.$benar.'.<br>'
                    .'Perhatikan langkah terakhirnya: yang dihitung lebih dahulu adalah pOH, bukan pH.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Gelas larutan beserta skala pH indikator universal di bawahnya.
     *
     * @param  int|null  $ph  pH yang ditandai pada skala; null berarti skala
     *                        hanya dipajang sebagai acuan karena pH-nya justru
     *                        yang ditanyakan
     * @param  bool  $lakmus  tambahkan dua kertas lakmus di samping gelasnya
     */
    public static function svgUji(string $nama, ?int $ph, ?string $label, bool $lakmus, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        // Gelas: warna isinya mengikuti warna indikator pada pH itu, supaya
        // gambar dan skala di bawahnya bercerita hal yang sama.
        $warnaIsi = $ph === null ? $isi : self::WARNA_PH[max(0, min(14, $ph))];

        $kanvas
            ->tambah('<path d="M 46 48 L 46 150 L 126 150 L 126 48" fill="none" stroke="'
                .Kanvas::HITAM.'" stroke-width="3" stroke-linejoin="round"/>')
            ->tambah('<rect x="48" y="86" width="76" height="63" fill="'.$warnaIsi.'" opacity="0.85"/>')
            ->teksPx([86, 172], $nama, ['ukuran' => 13, 'tebal' => true]);

        if ($ph !== null) {
            $kanvas->teksPx([86, 70], 'pH '.$ph, ['ukuran' => 15, 'tebal' => true]);
        }

        if ($label !== null) {
            $kanvas->teksPx([86, 70], $label, ['ukuran' => 14, 'tebal' => true]);
        }

        if ($lakmus) {
            self::kertasLakmus($kanvas, 196, '#d6455d', 'lakmus merah');
            self::kertasLakmus($kanvas, 300, '#3f7fd0', 'lakmus biru');

            $kanvas->teksPx([404, 108], '?', ['ukuran' => 34, 'tebal' => true]);
        }

        return self::skalaPh($kanvas, $ph)->render();
    }

    /** Sehelai kertas lakmus sebelum dicelupkan. */
    protected static function kertasLakmus(Kanvas $kanvas, float $x, string $warna, string $nama): Kanvas
    {
        return $kanvas
            ->tambah('<rect x="'.$x.'" y="54" width="30" height="92" rx="4" fill="'.$warna
                .'" stroke="'.Kanvas::HITAM.'" stroke-width="2"/>')
            ->teksPx([$x + 15, 166], $nama, ['ukuran' => 11, 'warna' => '#4b5563']);
    }

    /**
     * Skala pH 0–14 berwarna seperti kartu indikator universal. Bila sebuah
     * pH ditandai, penandanya berupa panah ke bawah tepat di atas kotaknya.
     */
    protected static function skalaPh(Kanvas $kanvas, ?int $ph): Kanvas
    {
        $x0 = 24;
        $lebar = 432 / 15;
        $y = 212;

        foreach (self::WARNA_PH as $i => $warna) {
            $x = $x0 + $i * $lebar;

            $kanvas->tambah('<rect x="'.round($x, 1).'" y="'.$y.'" width="'.round($lebar, 1)
                .'" height="28" fill="'.$warna.'" stroke="#ffffff" stroke-width="1"/>')
                ->teksPx([$x + $lebar / 2, $y + 44], (string) $i, ['ukuran' => 12]);
        }

        if ($ph !== null) {
            $kanvas->panahPx([$x0 + ($ph + 0.5) * $lebar, $y - 26], [$x0 + ($ph + 0.5) * $lebar, $y - 4],
                Kanvas::HITAM, 3);
        }

        return $kanvas
            ->teksPx([24 + $lebar * 3.5, $y + 64], 'asam', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([24 + $lebar * 7.5, $y + 64], 'netral', ['ukuran' => 12, 'tebal' => true])
            ->teksPx([24 + $lebar * 11.5, $y + 64], 'basa', ['ukuran' => 12, 'tebal' => true]);
    }
}

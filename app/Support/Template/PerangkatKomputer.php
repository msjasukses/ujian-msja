<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template perangkat & sistem komputer.
 *
 * Yang digambar adalah bagan sistem komputer — masukan, pemrosesan,
 * keluaran, dan penyimpanan beserta arah alirannya — bukan gambar tiap
 * perangkat. Yang diuji memang penggolongannya: perangkat mana masuk blok
 * mana, dan tiap blok bertugas apa. Nama blok yang ditanyakan disembunyikan
 * pada gambarnya, jadi butirnya tidak terjawab hanya dengan menyalin label.
 */
class PerangkatKomputer extends Template
{
    /**
     * Empat blok sistem komputer beserta tugas dan letaknya pada bagan.
     *
     * @var array<string, array{nama: string, fungsi: string, pusat: array<int, float>, tanda: array<int, float>}>
     */
    public const BLOK = [
        'masukan' => [
            'nama' => 'Perangkat masukan (input)',
            'fungsi' => 'memasukkan data dan perintah ke dalam komputer',
            'pusat' => [90, 118], 'tanda' => [90, 68],
        ],
        'pemrosesan' => [
            'nama' => 'Perangkat pemrosesan',
            'fungsi' => 'mengolah data yang masuk menjadi informasi',
            'pusat' => [240, 118], 'tanda' => [240, 68],
        ],
        'keluaran' => [
            'nama' => 'Perangkat keluaran (output)',
            'fungsi' => 'menampilkan atau mengeluarkan hasil olahan',
            'pusat' => [390, 118], 'tanda' => [390, 68],
        ],
        'penyimpanan' => [
            'nama' => 'Perangkat penyimpanan (storage)',
            'fungsi' => 'menyimpan data dan program agar dapat dipakai kembali',
            'pusat' => [240, 228], 'tanda' => [322, 228],
        ],
    ];

    /**
     * Perangkat beserta bloknya. Urutannya menentukan perangkat mana yang
     * lebih dahulu dipakai sebagai pengecoh.
     *
     * @var array<string, array{nama: string, blok: string}>
     */
    public const PERANGKAT = [
        'keyboard' => ['nama' => 'Papan ketik (keyboard)', 'blok' => 'masukan'],
        'mouse' => ['nama' => 'Tetikus (mouse)', 'blok' => 'masukan'],
        'pemindai' => ['nama' => 'Pemindai (scanner)', 'blok' => 'masukan'],
        'mikrofon' => ['nama' => 'Mikrofon', 'blok' => 'masukan'],
        'monitor' => ['nama' => 'Monitor', 'blok' => 'keluaran'],
        'pencetak' => ['nama' => 'Pencetak (printer)', 'blok' => 'keluaran'],
        'pengeras' => ['nama' => 'Pengeras suara (speaker)', 'blok' => 'keluaran'],
        'prosesor' => ['nama' => 'Prosesor (CPU)', 'blok' => 'pemrosesan'],
        'harddisk' => ['nama' => 'Cakram keras (harddisk)', 'blok' => 'penyimpanan'],
        'flashdisk' => ['nama' => 'Flashdisk', 'blok' => 'penyimpanan'],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'nama_blok' => 'Nama blok yang ditandai',
        'fungsi_blok' => 'Tugas blok yang ditandai',
        'contoh' => 'Perangkat yang termasuk sebuah blok',
        'golongan' => 'Blok tempat sebuah perangkat',
    ];

    public static function kode(): string
    {
        return 'perangkat-komputer';
    }

    public static function nama(): string
    {
        return 'Perangkat & Sistem Komputer';
    }

    public static function mapel(): string
    {
        return 'Informatika';
    }

    public static function ringkasan(): string
    {
        return 'Bagan sistem komputer — masukan, pemrosesan, keluaran, dan '
            .'penyimpanan: nama tiap blok, tugasnya, serta perangkat yang termasuk di dalamnya.';
    }

    public static function ikon(): string
    {
        return 'bi-pc-display';
    }

    public static function kataKunci(): array
    {
        return ['perangkat keras', 'hardware', 'komputer', 'masukan', 'input', 'keluaran',
            'output', 'pemrosesan', 'prosesor', 'cpu', 'penyimpanan', 'storage', 'keyboard',
            'monitor', 'printer', 'harddisk', 'flashdisk', 'informatika'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'nama_blok'),
            [
                'nama' => 'blok',
                'label' => 'Blok yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($b) => $b['nama'], self::BLOK),
                'awal' => 'pemrosesan',
                'tampil' => ['tanya' => ['nama_blok', 'fungsi_blok', 'contoh']],
            ],
            [
                'nama' => 'perangkat',
                'label' => 'Perangkat yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($p) => $p['nama'], self::PERANGKAT),
                'awal' => 'pencetak',
                'tampil' => ['tanya' => ['golongan']],
            ],
            self::kolomWarna('abu'),
        ];
    }

    /**
     * Perangkat pada sebuah blok, berurutan seperti pada tetapannya.
     *
     * @return array<int, string>
     */
    public static function isiBlok(string $blok): array
    {
        return array_values(array_map(
            fn ($p) => $p['nama'],
            array_filter(self::PERANGKAT, fn ($p) => $p['blok'] === $blok),
        ));
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'abu');

        $tanya = $isi['tanya'] ?? 'nama_blok';

        if ($tanya === 'golongan') {
            return $this->golongan(
                isset(self::PERANGKAT[$isi['perangkat'] ?? '']) ? $isi['perangkat'] : 'pencetak',
                $warnaIsi, $garis,
            );
        }

        $kunci = isset(self::BLOK[$isi['blok'] ?? '']) ? $isi['blok'] : 'pemrosesan';
        $blok = self::BLOK[$kunci];

        // Nama blok yang ditanyakan disembunyikan; pada soal contoh perangkat
        // namanya justru ditampilkan karena yang diuji isinya, bukan namanya.
        $sembunyi = $tanya !== 'contoh';

        $bahan = [
            'svg' => self::svgSistem($kunci, $sembunyi, $warnaIsi, $garis),
            'keterangan' => 'Bagan sistem komputer; blok '.mb_strtolower($blok['nama']).' ditandai X.',
        ];

        $awalan = 'Perhatikan bagan sistem komputer berikut.';

        if ($tanya === 'contoh') {
            $daftar = self::isiBlok($kunci);
            $benar = $daftar[0];

            return $bahan + [
                'judul' => $blok['nama'].': '.$benar,
                'teks' => $awalan.' Perangkat yang termasuk blok bertanda X adalah ...',
                'pernyataan' => $awalan.' Perangkat yang termasuk blok bertanda X adalah %s.',
                'pilihan' => array_map(
                    fn ($t) => ['teks' => $t, 'urut' => $t],
                    // Pengecohnya perangkat dari blok yang lain — semuanya
                    // perangkat nyata, hanya salah tempat.
                    array_merge([$benar], array_values(array_diff(
                        array_column(self::PERANGKAT, 'nama'), $daftar,
                    ))),
                ),
                'kata_kunci' => $benar,
                'pembahasan' => 'Blok bertanda X adalah '.mb_strtolower($blok['nama'])
                    .', yang bertugas '.$blok['fungsi'].'.<br>Yang termasuk di dalamnya antara lain '
                    .implode(', ', $daftar).'.',
            ];
        }

        $tanyaFungsi = $tanya === 'fungsi_blok';
        $benar = $tanyaFungsi ? ucfirst($blok['fungsi']) : $blok['nama'];

        return $bahan + [
            'judul' => $blok['nama'].($tanyaFungsi ? ': tugasnya' : ''),
            'teks' => $awalan.' Blok yang ditandai huruf X '
                .($tanyaFungsi ? 'bertugas untuk ...' : 'adalah ...'),
            'pernyataan' => $awalan.' Blok yang ditandai huruf X '
                .($tanyaFungsi ? 'bertugas untuk %s.' : 'adalah %s.'),
            'pilihan' => array_map(
                fn ($t) => ['teks' => ucfirst($t), 'urut' => $t],
                array_merge(
                    [$tanyaFungsi ? $blok['fungsi'] : $blok['nama']],
                    array_values(array_diff(
                        array_column(self::BLOK, $tanyaFungsi ? 'fungsi' : 'nama'),
                        [$tanyaFungsi ? $blok['fungsi'] : $blok['nama']],
                    )),
                ),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Letak blok X pada bagan menunjukkan perannya: '
                .match ($kunci) {
                    'masukan' => 'ia berada di awal dan panahnya menuju pemrosesan, '
                        .'jadi itulah perangkat masukan',
                    'keluaran' => 'ia menerima panah dari pemrosesan dan tidak meneruskannya, '
                        .'jadi itulah perangkat keluaran',
                    'penyimpanan' => 'panahnya dua arah dengan pemrosesan — data dapat disimpan '
                        .'lalu diambil kembali — jadi itulah perangkat penyimpanan',
                    default => 'ia berada di antara masukan dan keluaran, jadi itulah perangkat pemrosesan',
                }.'. Blok itu bertugas '.$blok['fungsi'].'.',
        ];
    }

    protected function golongan(string $kunci, string $warnaIsi, string $garis): array
    {
        $perangkat = self::PERANGKAT[$kunci];
        $blok = self::BLOK[$perangkat['blok']];

        return [
            'svg' => self::svgSistem(null, false, $warnaIsi, $garis),
            'keterangan' => 'Bagan sistem komputer dengan keempat bloknya.',
            'judul' => $perangkat['nama'].': '.$blok['nama'],
            'teks' => 'Perhatikan bagan sistem komputer berikut. '.$perangkat['nama']
                .' termasuk blok ...',
            'pernyataan' => 'Pada bagan sistem komputer berikut, '.$perangkat['nama']
                .' termasuk blok %s.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$blok['nama']], array_values(array_diff(
                    array_column(self::BLOK, 'nama'), [$blok['nama']],
                ))),
            ),
            'kata_kunci' => $blok['nama'],
            'pembahasan' => $perangkat['nama'].' dipakai untuk '.$blok['fungsi']
                .', sehingga termasuk '.mb_strtolower($blok['nama']).'.<br>'
                .'Perangkat lain pada blok yang sama antara lain '
                .implode(', ', array_values(array_diff(self::isiBlok($perangkat['blok']), [$perangkat['nama']])))
                .'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /**
     * Bagan sistem komputer: masukan menuju pemrosesan, pemrosesan menuju
     * keluaran, dan penyimpanan bertukar data dua arah dengan pemrosesan.
     *
     * @param  string|null  $ditandai  blok yang diberi huruf X
     * @param  bool  $sembunyi  nama blok yang ditandai diganti huruf X
     */
    public static function svgSistem(?string $ditandai, bool $sembunyi, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        [$lebar, $tinggi] = [120, 60];

        $kanvas->teksPx([240, 36], 'Bagan sistem komputer', ['ukuran' => 15, 'tebal' => true]);

        foreach (self::BLOK as $kunci => $blok) {
            $ini = $kunci === $ditandai;

            $kanvas->kotakPx($blok['pusat'], $lebar, $tinggi,
                $ini && $sembunyi ? 'X' : ucfirst($kunci),
                $ini ? $isi : '#ffffff', $garis, [
                    'ukuran' => $ini && $sembunyi ? 26 : 13,
                    'tebal' => $ini,
                    'tebal_garis' => $ini ? 3.5 : 2,
                ]);

            // Blok yang namanya tetap tampak diberi huruf X di ruang kosong
            // terdekat, yang berbeda letaknya untuk tiap blok.
            if ($ini && ! $sembunyi) {
                $kanvas->lingkaran($blok['tanda'], 14, '#ffffff', $garis, 3)
                    ->teksPx([$blok['tanda'][0], $blok['tanda'][1] + 7], 'X', [
                        'ukuran' => 20, 'tebal' => true,
                    ]);
            }
        }

        $kanvas
            ->panahKotakPx(self::BLOK['masukan']['pusat'], self::BLOK['pemrosesan']['pusat'],
                $lebar, $tinggi, Kanvas::HITAM, 2.5)
            ->panahKotakPx(self::BLOK['pemrosesan']['pusat'], self::BLOK['keluaran']['pusat'],
                $lebar, $tinggi, Kanvas::HITAM, 2.5);

        // Penyimpanan bertukar data dua arah: data disimpan dan diambil
        // kembali, dan itulah yang membedakannya dari keluaran.
        foreach ([['pemrosesan', 'penyimpanan'], ['penyimpanan', 'pemrosesan']] as [$dari, $ke]) {
            $kanvas->panahKotakPx(
                [self::BLOK[$dari]['pusat'][0] + ($dari === 'pemrosesan' ? -14 : 14), self::BLOK[$dari]['pusat'][1]],
                [self::BLOK[$ke]['pusat'][0] + ($dari === 'pemrosesan' ? -14 : 14), self::BLOK[$ke]['pusat'][1]],
                $lebar, $tinggi, Kanvas::HITAM, 2.5,
            );
        }

        $kanvas->teksPx([240, 286], 'panah menunjukkan arah aliran data', [
            'ukuran' => 11, 'warna' => '#6b7280',
        ]);

        return $kanvas->render();
    }
}

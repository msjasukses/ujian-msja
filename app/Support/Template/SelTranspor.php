<?php

namespace App\Support\Template;

use App\Support\Kanvas;

/**
 * Template sel & membran: difusi, osmosis, dan bagian-bagian sel tumbuhan.
 *
 * Gambar difusi dan osmosisnya sengaja tidak memuat panah arah perpindahan —
 * panah itu justru jawabannya. Yang digambar hanya kadar kedua larutan, dan
 * siswa yang menyimpulkan arahnya sendiri. Satu gambar yang sama karenanya
 * dapat dipakai untuk dua pertanyaan berlawanan: zat terlarut berpindah ke
 * arah yang encer, air justru ke arah yang pekat.
 */
class SelTranspor extends Template
{
    /**
     * Bagian sel tumbuhan beserta fungsinya dan tempat tandanya di gambar.
     * 'titik' adalah ujung penunjuk pada bagian yang dimaksud, 'label'
     * tempat huruf X-nya; bila titiknya kosong, huruf X langsung ditaruh di
     * dalam bagian itu karena ruangnya cukup lebar.
     *
     * @var array<string, array{nama: string, fungsi: string, titik: ?array<int, float>, label: array<int, float>}>
     */
    public const ORGANEL = [
        'inti' => [
            'nama' => 'Inti sel (nukleus)',
            'fungsi' => 'mengatur seluruh kegiatan sel dan menyimpan materi genetik',
            'titik' => [352, 96],
            'label' => [440, 54],
        ],
        'membran' => [
            'nama' => 'Membran sel',
            'fungsi' => 'mengatur zat yang keluar dan masuk sel',
            'titik' => [56, 154],
            'label' => [18, 158],
        ],
        'dinding' => [
            'nama' => 'Dinding sel',
            'fungsi' => 'memberi bentuk tetap dan melindungi sel tumbuhan',
            'titik' => [240, 52],
            'label' => [240, 28],
        ],
        'vakuola' => [
            'nama' => 'Vakuola',
            'fungsi' => 'menyimpan cadangan makanan dan sisa metabolisme',
            'titik' => null,
            'label' => [176, 152],
        ],
        'kloroplas' => [
            'nama' => 'Kloroplas',
            'fungsi' => 'tempat berlangsungnya fotosintesis',
            'titik' => [96, 212],
            'label' => [44, 278],
        ],
        'mitokondria' => [
            'nama' => 'Mitokondria',
            'fungsi' => 'tempat respirasi sel untuk menghasilkan energi',
            'titik' => [338, 206],
            'label' => [432, 266],
        ],
        'ribosom' => [
            'nama' => 'Ribosom',
            'fungsi' => 'tempat pembentukan protein',
            'titik' => [272, 176],
            'label' => [280, 282],
        ],
        'sitoplasma' => [
            'nama' => 'Sitoplasma',
            'fungsi' => 'tempat berlangsungnya reaksi kimia di dalam sel',
            'titik' => null,
            'label' => [240, 112],
        ],
    ];

    /** Kadar di luar dan di dalam sel untuk tiap jenis larutan, dalam persen. */
    public const TONISITAS = [
        'hipertonis' => [25, 5],
        'hipotonis' => [3, 20],
        'isotonis' => [10, 10],
    ];

    /** @var array<string, string> */
    public const TANYA = [
        'arah' => 'Arah perpindahan zat melalui membran',
        'tonisitas' => 'Larutan mana yang lebih pekat',
        'keadaan_sel' => 'Keadaan sel di dalam larutan',
        'organel' => 'Nama bagian sel yang ditandai',
        'fungsi' => 'Fungsi bagian sel yang ditandai',
    ];

    public static function kode(): string
    {
        return 'sel-transpor';
    }

    public static function nama(): string
    {
        return 'Sel & Membran';
    }

    public static function mapel(): string
    {
        return 'Biologi';
    }

    public static function ringkasan(): string
    {
        return 'Difusi dan osmosis melalui membran semipermeabel, keadaan sel '
            .'dalam larutan hipertonis dan hipotonis, serta bagian-bagian sel tumbuhan.';
    }

    public static function ikon(): string
    {
        return 'bi-circle-square';
    }

    public static function kataKunci(): array
    {
        return ['sel', 'membran', 'difusi', 'osmosis', 'hipertonis', 'hipotonis', 'isotonis',
            'plasmolisis', 'krenasi', 'lisis', 'turgid', 'organel', 'vakuola', 'kloroplas',
            'mitokondria', 'nukleus', 'transpor', 'semipermeabel'];
    }

    public static function kolom(): array
    {
        return [
            self::kolomTanya(self::TANYA, 'arah'),
            [
                'nama' => 'zat',
                'label' => 'Zat yang ditanyakan',
                'jenis' => 'pilihan',
                'pilihan' => [
                    'terlarut' => 'Zat terlarut (difusi)',
                    'air' => 'Air (osmosis)',
                ],
                'awal' => 'terlarut',
                'tampil' => ['tanya' => ['arah']],
            ],
            self::kolomAngka('kadar_a', 'Kadar larutan A (%)', 20, 0, 100, [
                'langkah' => 1, 'tampil' => ['tanya' => ['arah', 'tonisitas']],
            ]),
            self::kolomAngka('kadar_b', 'Kadar larutan B (%)', 5, 0, 100, [
                'langkah' => 1, 'tampil' => ['tanya' => ['arah', 'tonisitas']],
            ]),
            [
                'nama' => 'sel',
                'label' => 'Jenis sel',
                'jenis' => 'pilihan',
                'pilihan' => ['hewan' => 'Sel hewan', 'tumbuhan' => 'Sel tumbuhan'],
                'awal' => 'hewan',
                'tampil' => ['tanya' => ['keadaan_sel']],
                'lebar' => 6,
            ],
            [
                'nama' => 'larutan',
                'label' => 'Larutan tempat sel direndam',
                'jenis' => 'pilihan',
                'pilihan' => [
                    'hipertonis' => 'Hipertonis (lebih pekat daripada sel)',
                    'hipotonis' => 'Hipotonis (lebih encer daripada sel)',
                    'isotonis' => 'Isotonis (sama pekat dengan sel)',
                ],
                'awal' => 'hipertonis',
                'tampil' => ['tanya' => ['keadaan_sel']],
                'lebar' => 6,
            ],
            [
                'nama' => 'organel',
                'label' => 'Bagian sel yang ditandai',
                'jenis' => 'pilihan',
                'pilihan' => array_map(fn ($o) => $o['nama'], self::ORGANEL),
                'awal' => 'inti',
                'tampil' => ['tanya' => ['organel', 'fungsi']],
            ],
            self::kolomWarna('biru'),
        ];
    }

    protected function susun(array $isi): array
    {
        [$warnaIsi, $garis] = self::warna($isi, 'biru');

        return match ($isi['tanya'] ?? 'arah') {
            'tonisitas' => $this->tonisitas((int) ($isi['kadar_a'] ?? 20), (int) ($isi['kadar_b'] ?? 5), $garis),
            'keadaan_sel' => $this->keadaanSel($isi['sel'] ?? 'hewan', $isi['larutan'] ?? 'hipertonis', $warnaIsi, $garis),
            'organel', 'fungsi' => $this->bagianSel($isi['organel'] ?? 'inti', ($isi['tanya'] ?? '') === 'fungsi', $warnaIsi, $garis),
            default => $this->arah($isi['zat'] ?? 'terlarut', (int) ($isi['kadar_a'] ?? 20), (int) ($isi['kadar_b'] ?? 5), $garis),
        };
    }

    protected function arah(string $zat, int $a, int $b, string $garis): array
    {
        $air = $zat === 'air';
        $nama = $air ? 'air' : 'zat terlarut';

        // Zat terlarut berpindah ke arah yang lebih encer; air justru
        // sebaliknya, ke arah yang kadarnya lebih pekat.
        $benar = match (true) {
            $a === $b => 'Tidak terjadi perpindahan bersih',
            ($a > $b) !== $air => 'Dari A ke B',
            default => 'Dari B ke A',
        };

        return [
            'judul' => ucfirst($nama).': '.mb_strtolower($benar),
            'teks' => 'Perhatikan gambar dua larutan yang dipisahkan membran semipermeabel berikut. '
                ."Ke arah manakah {$nama} berpindah?",
            'pernyataan' => 'Pada gambar dua larutan yang dipisahkan membran semipermeabel berikut, '
                ."{$nama} berpindah %s.",
            'svg' => self::svgMembran($a, $b, $garis),
            'keterangan' => "Larutan A berkadar {$a}% dan larutan B berkadar {$b}%, dipisahkan "
                .'membran semipermeabel.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Dari A ke B',
                    'Dari B ke A',
                    'Tidak terjadi perpindahan bersih',
                    'Dari A dan B bersamaan ke arah membran',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => match (true) {
                $a === $b => "Kadar kedua larutan sama ({$a}%), sehingga keduanya isotonis dan "
                    .'tidak ada perpindahan bersih — perpindahan tetap terjadi ke dua arah dengan laju yang sama.',
                $air => 'Pada osmosis, air berpindah dari larutan yang lebih encer ke larutan yang '
                    .'lebih pekat. Larutan '.($a > $b ? 'B' : 'A').' lebih encer ('
                    .min($a, $b).'%) dan larutan '.($a > $b ? 'A' : 'B').' lebih pekat ('
                    .max($a, $b).'%), jadi air berpindah '.mb_strtolower($benar).'.',
                default => 'Pada difusi, zat terlarut berpindah dari kadar tinggi ke kadar rendah. '
                    .'Larutan '.($a > $b ? 'A' : 'B').' berkadar lebih tinggi ('.max($a, $b).'%) '
                    .'daripada larutan '.($a > $b ? 'B' : 'A').' ('.min($a, $b).'%), jadi zat '
                    .'terlarut berpindah '.mb_strtolower($benar).'.',
            },
        ];
    }

    protected function tonisitas(int $a, int $b, string $garis): array
    {
        $benar = match (true) {
            $a === $b => 'Larutan A dan B isotonis',
            $a > $b => 'Larutan A hipertonis terhadap larutan B',
            default => 'Larutan B hipertonis terhadap larutan A',
        };

        return [
            'judul' => $benar,
            'teks' => 'Perhatikan gambar dua larutan yang dipisahkan membran semipermeabel berikut. '
                .'Pernyataan yang tepat mengenai kedua larutan itu adalah ...',
            'pernyataan' => 'Pada gambar dua larutan yang dipisahkan membran semipermeabel berikut, '
                .'pernyataan yang tepat adalah %s.',
            'svg' => self::svgMembran($a, $b, $garis),
            'keterangan' => "Larutan A berkadar {$a}% dan larutan B berkadar {$b}%.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff([
                    'Larutan A hipertonis terhadap larutan B',
                    'Larutan B hipertonis terhadap larutan A',
                    'Larutan A dan B isotonis',
                    'Larutan A dan B sama-sama hipotonis',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => 'Larutan yang kadarnya lebih tinggi disebut hipertonis, yang lebih rendah '
                ."hipotonis, dan yang sama disebut isotonis. Kadar A = {$a}% dan kadar B = {$b}%, "
                .'sehingga '.mb_strtolower($benar).'.',
        ];
    }

    protected function keadaanSel(string $sel, string $larutan, string $warnaIsi, string $garis): array
    {
        [$luar, $dalam] = self::TONISITAS[$larutan] ?? self::TONISITAS['hipertonis'];
        $tumbuhan = $sel === 'tumbuhan';

        $benar = match (true) {
            $luar === $dalam => 'Tetap seperti semula',
            $tumbuhan && $luar > $dalam => 'Mengerut dan membran terlepas dari dinding sel (plasmolisis)',
            $tumbuhan => 'Mengembang dan menjadi tegang (turgid)',
            $luar > $dalam => 'Mengerut (krenasi)',
            default => 'Mengembang lalu pecah (lisis)',
        };

        $nama = $tumbuhan ? 'sel tumbuhan' : 'sel hewan';

        return [
            'judul' => ucfirst($nama).' dalam larutan '.$larutan,
            'teks' => "Sebuah {$nama} dimasukkan ke dalam larutan seperti pada gambar berikut. "
                .'Apa yang akan terjadi pada sel tersebut?',
            'pernyataan' => "Bila sebuah {$nama} dimasukkan ke dalam larutan seperti pada gambar "
                .'berikut, sel itu akan %s.',
            'svg' => self::svgSel($tumbuhan, $luar, $dalam, $warnaIsi, $garis),
            'keterangan' => ucfirst($nama)." berkadar {$dalam}% di dalam larutan berkadar {$luar}%.",
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                // Pengecohnya keadaan sel yang lain pada bagan yang sama —
                // termasuk keadaan yang hanya mungkin pada jenis sel yang
                // berbeda, kekeliruan yang justru paling sering terjadi.
                array_merge([$benar], array_values(array_diff([
                    $tumbuhan ? 'Mengerut (krenasi)' : 'Mengerut dan membran terlepas dari dinding sel (plasmolisis)',
                    $tumbuhan ? 'Mengembang lalu pecah (lisis)' : 'Mengembang dan menjadi tegang (turgid)',
                    'Tetap seperti semula',
                    $tumbuhan ? 'Mengembang dan menjadi tegang (turgid)' : 'Mengembang lalu pecah (lisis)',
                    $tumbuhan ? 'Mengerut dan membran terlepas dari dinding sel (plasmolisis)' : 'Mengerut (krenasi)',
                ], [$benar]))),
            ),
            'kata_kunci' => $benar,
            'pembahasan' => match (true) {
                $luar === $dalam => "Kadar larutan di luar sel ({$luar}%) sama dengan kadar cairan "
                    ."sel ({$dalam}%), sehingga larutannya isotonis. Air masuk dan keluar sama "
                    .'banyak dan bentuk selnya tidak berubah.',
                $luar > $dalam => "Kadar larutan di luar sel ({$luar}%) lebih tinggi daripada cairan "
                    ."sel ({$dalam}%), jadi larutannya hipertonis. Air keluar dari sel sehingga sel "
                    .($tumbuhan
                        ? 'mengerut dan membran selnya terlepas dari dinding sel. Peristiwa itu disebut plasmolisis.'
                        : 'mengerut. Peristiwa itu disebut krenasi.'),
                default => "Kadar larutan di luar sel ({$luar}%) lebih rendah daripada cairan sel "
                    ."({$dalam}%), jadi larutannya hipotonis. Air masuk ke dalam sel sehingga sel "
                    .($tumbuhan
                        ? 'mengembang dan menjadi tegang. Dinding selnya menahan sel agar tidak pecah, '
                            .'dan keadaan itu disebut turgid.'
                        : 'mengembang. Karena tidak punya dinding sel, sel hewan dapat pecah — peristiwa '
                            .'itu disebut lisis.'),
            },
        ];
    }

    protected function bagianSel(string $kunci, bool $tanyaFungsi, string $warnaIsi, string $garis): array
    {
        $organel = self::ORGANEL[$kunci] ?? self::ORGANEL['inti'];
        $benar = $tanyaFungsi ? ucfirst($organel['fungsi']) : $organel['nama'];

        $pengecoh = $tanyaFungsi
            ? array_map(fn ($o) => ucfirst($o['fungsi']), self::ORGANEL)
            : array_map(fn ($o) => $o['nama'], self::ORGANEL);

        return [
            'judul' => $organel['nama'].($tanyaFungsi ? ': fungsinya' : ''),
            'teks' => 'Perhatikan gambar sel tumbuhan berikut. Bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk ...' : 'adalah ...'),
            'pernyataan' => 'Pada gambar sel tumbuhan berikut, bagian yang ditandai huruf X '
                .($tanyaFungsi ? 'berfungsi untuk %s.' : 'adalah %s.'),
            'svg' => self::svgSelTumbuhan($kunci, $warnaIsi, $garis),
            'keterangan' => 'Sel tumbuhan dengan '.mb_strtolower($organel['nama']).' ditandai X.',
            'pilihan' => array_map(
                fn ($t) => ['teks' => $t, 'urut' => $t],
                array_merge([$benar], array_values(array_diff($pengecoh, [$benar]))),
            ),
            'kata_kunci' => $tanyaFungsi ? $organel['fungsi'] : $organel['nama'],
            'pembahasan' => 'Bagian yang ditandai X adalah '.mb_strtolower($organel['nama'])
                .', yang berfungsi '.$organel['fungsi'].'.',
        ];
    }

    // =====================================================================
    // Gambar
    // =====================================================================

    /** Banyak titik yang mewakili suatu kadar, dibatasi agar tetap terhitung mata. */
    protected static function titikKadar(int $kadar): int
    {
        return min(24, (int) round($kadar / 4));
    }

    protected static function bejana(Kanvas $kanvas, string $garis): Kanvas
    {
        return $kanvas
            ->tambah('<rect x="58" y="88" width="364" height="154" fill="#eaf4fb"/>')
            ->tambah('<path d="M 56 58 L 56 244 L 424 244 L 424 58" fill="none" stroke="'
                .$garis.'" stroke-width="3.5" stroke-linejoin="round"/>');
    }

    /**
     * Dua larutan berkadar berbeda yang dipisahkan membran semipermeabel.
     * Arah perpindahannya tidak digambar: itulah yang ditanyakan.
     */
    public static function svgMembran(int $a, int $b, string $garis): string
    {
        $kanvas = self::bejana(new Kanvas, $garis);

        foreach (Kanvas::sebaran(self::titikKadar($a), 74, 104, 140, 126) as $t) {
            $kanvas->lingkaran($t, 5, '#1c6ea4', '#14506f', 1);
        }

        foreach (Kanvas::sebaran(self::titikKadar($b), 258, 104, 140, 126) as $t) {
            $kanvas->lingkaran($t, 5, '#1c6ea4', '#14506f', 1);
        }

        $kanvas
            ->tambah('<line x1="240" y1="54" x2="240" y2="246" stroke="'.Kanvas::HITAM
                .'" stroke-width="4" stroke-dasharray="7 5"/>')
            ->teksPx([148, 80], 'A — '.$a.'%', ['ukuran' => 16, 'tebal' => true])
            ->teksPx([332, 80], 'B — '.$b.'%', ['ukuran' => 16, 'tebal' => true])
            ->teksPx([Kanvas::LEBAR / 2, 266], 'garis putus-putus = membran semipermeabel',
                ['ukuran' => 12, 'warna' => '#4b5563'])
            ->teksPx([Kanvas::LEBAR / 2, 285], 'titik biru = zat terlarut',
                ['ukuran' => 11, 'warna' => '#6b7280']);

        return $kanvas->render();
    }

    /** Sebuah sel yang direndam dalam larutan; kadar keduanya tertulis. */
    public static function svgSel(bool $tumbuhan, int $luar, int $dalam, string $isi, string $garis): string
    {
        $kanvas = self::bejana(new Kanvas, $garis);

        // Titik di luar sel dilewati bila jatuh di daerah selnya, supaya
        // tidak terbaca seolah sudah berada di dalam sel.
        foreach (Kanvas::sebaran(self::titikKadar($luar) * 2, 70, 100, 340, 132) as $t) {
            if (hypot($t[0] - 240, $t[1] - 158) < 86) {
                continue;
            }

            $kanvas->lingkaran($t, 5, '#1c6ea4', '#14506f', 1);
        }

        if ($tumbuhan) {
            $kanvas->persegi([176, 112], 128, 92, '#dff0d8', '#2e7d32', 3)
                ->persegi([184, 120], 112, 76, $isi, $garis, 2);
        } else {
            $kanvas->lingkaran([240, 158], 54, $isi, $garis, 2.5);
        }

        foreach (Kanvas::sebaran(self::titikKadar($dalam), 204, 132, 72, 52) as $t) {
            $kanvas->lingkaran($t, 4.5, '#1c6ea4', '#14506f', 1);
        }

        $kanvas
            ->teksPx([Kanvas::LEBAR / 2, 264], 'kadar larutan di luar sel = '.$luar.'%',
                ['ukuran' => 13, 'tebal' => true])
            ->teksPx([Kanvas::LEBAR / 2, 284], 'kadar cairan di dalam sel = '.$dalam.'%',
                ['ukuran' => 13, 'tebal' => true]);

        return $kanvas->render();
    }

    /** Sel tumbuhan beserta bagian-bagiannya; satu bagian ditandai huruf X. */
    public static function svgSelTumbuhan(string $ditandai, string $isi, string $garis): string
    {
        $kanvas = new Kanvas;

        $kanvas
            ->tambah('<rect x="34" y="52" width="412" height="204" rx="14" fill="#e6efd8" '
                .'stroke="#5a7a34" stroke-width="3"/>')
            ->tambah('<rect x="56" y="74" width="368" height="160" rx="26" fill="#fdf7ec" '
                .'stroke="#c0621b" stroke-width="2.5"/>')
            ->elips([176, 152], 76, 54, '#cfe6f7', '#1c6ea4', 2)
            ->lingkaran([340, 118], 36, '#ded3f8', '#6a4bbc', 2)
            ->lingkaran([340, 118], 13, '#6a4bbc', '#6a4bbc', 1)
            ->elips([338, 206], 32, 16, '#fbcfd9', '#b23a5c', 2)
            ->garisPatah([[318, 206], [328, 199], [338, 212], [348, 199], [358, 206]], '#b23a5c', 1.5);

        foreach ([[92, 96], [96, 212], [268, 96]] as $kloroplas) {
            $kanvas->elips($kloroplas, 19, 11, '#9ccc65', '#2e7d32', 1.8);
        }

        foreach ([[272, 176], [300, 160], [258, 210]] as $ribosom) {
            $kanvas->lingkaran($ribosom, 4.5, '#596066', '#3a4046', 1);
        }

        $organel = self::ORGANEL[$ditandai] ?? self::ORGANEL['inti'];

        if ($organel['titik'] !== null) {
            $kanvas
                ->garis($organel['titik'], $organel['label'], Kanvas::HITAM, 2)
                ->lingkaran($organel['titik'], 4.5, '#ffffff', Kanvas::HITAM, 2);
        }

        // Huruf X diberi alas putih agar tetap terbaca di atas bagian yang
        // berwarna, misalnya saat yang ditandai vakuola atau sitoplasma.
        $kanvas
            ->lingkaran($organel['label'], 13, '#ffffff', $garis, 2.5)
            ->teksPx([$organel['label'][0], $organel['label'][1] + 7], 'X', [
                'ukuran' => 20, 'tebal' => true,
            ]);

        return $kanvas->render();
    }
}

<?php

namespace App\Support\Template;

use App\Models\Soal;
use App\Support\Benda;

/**
 * Satu template soal: sekumpulan isian yang dirakit menjadi butir utuh.
 *
 * Template menyatakan isian apa yang diminta dari guru lewat kolom(), lalu
 * menyusun butirnya lewat susun(). Sisanya — menyeleksi opsi, mengurutkannya,
 * menetapkan kunci, dan mengubah butir yang sama menjadi pilihan ganda, isian
 * singkat, atau benar/salah — dikerjakan di kelas ini supaya seluruh template
 * berperilaku sama dan hanya ada satu tempat untuk memperbaikinya.
 *
 * Yang dikembalikan hanya bahan, belum butir yang tersimpan: formulir input
 * soal yang mengisikannya, sehingga guru tetap bisa menyunting kalimatnya,
 * menukar topik, atau menambah opsi sebelum menyimpan.
 */
abstract class Template
{
    /** Banyaknya opsi pada butir pilihan ganda hasil template. */
    public const JUMLAH_OPSI = 4;

    /**
     * Warna isi beserta warna garisnya. Keduanya dipasangkan supaya garis
     * tepi selalu cukup gelap untuk terbaca di atas isinya, termasuk saat
     * gambar dicetak hitam putih.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const WARNA = [
        'biru' => ['#bfe0fb', '#1c6ea4'],
        'kuning' => ['#fdeab0', '#a97d10'],
        'hijau' => ['#c9ecc9', '#2e7d32'],
        'merah' => ['#fbcfd9', '#b23a5c'],
        'ungu' => ['#ded3f8', '#6a4bbc'],
        'jingga' => ['#fddcb9', '#c0621b'],
        'abu' => ['#e3e5e8', '#596066'],
        'putih' => ['#ffffff', '#37404a'],
    ];

    abstract public static function kode(): string;

    abstract public static function nama(): string;

    /** Mata pelajaran tempat template ini dikelompokkan di daftar. */
    abstract public static function mapel(): string;

    /** Satu kalimat penjelas di kartu daftar template. */
    abstract public static function ringkasan(): string;

    /** Nama ikon Bootstrap Icons, mis. "bi-pentagon". */
    abstract public static function ikon(): string;

    /**
     * Isian yang diminta dari guru.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public static function kolom(): array;

    /**
     * Bahan butir soal: teks, gambar, pilihan jawaban, dan pembahasannya.
     * Pilihan pertama selalu jawaban yang benar — pengurutannya belakangan.
     *
     * @return array{judul?: string, teks: string, pernyataan: string, svg?: ?string,
     *               keterangan?: string, pilihan: array<int, array{teks: string, urut?: float|string}>,
     *               kata_kunci?: string, pembahasan: string}
     */
    abstract protected function susun(array $isi): array;

    /**
     * Kata yang ikut dicari saat guru mengetik di kotak pencarian daftar
     * template, selain nama dan ringkasannya.
     *
     * @return array<int, string>
     */
    public static function kataKunci(): array
    {
        return [];
    }

    /** Jenis butir yang bisa dihasilkan template ini. */
    public static function jenis(): array
    {
        return [Soal::PG, Soal::ESSAY, Soal::BENAR_SALAH];
    }

    /**
     * Pemeriksaan yang tidak bisa dinyatakan sebagai aturan validasi per
     * kolom: isian yang masing-masing sah, tetapi digabung tidak masuk akal.
     *
     * @return array<string, string> nama kolom => pesan; kosong berarti sah
     */
    public static function periksa(array $isi): array
    {
        return [];
    }

    /**
     * Kolom yang sedang berlaku untuk isian tertentu. Kolom bisa bergantung
     * pada pilihan kolom lain — ukuran yang diminta persegi berbeda dari yang
     * diminta trapesium — dan yang tidak tampil tidak ikut divalidasi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function kolomAktif(array $isi): array
    {
        $aktif = [];

        foreach (static::kolom() as $kolom) {
            foreach ($kolom['tampil'] ?? [] as $bergantung => $nilai) {
                if (! in_array((string) ($isi[$bergantung] ?? ''), array_map('strval', $nilai), true)) {
                    continue 2;
                }
            }

            $aktif[] = $kolom;
        }

        return $aktif;
    }

    /**
     * Isian awal template, dipakai saat guru baru membukanya.
     *
     * @return array<string, mixed>
     */
    public static function awal(): array
    {
        $awal = [];

        foreach (static::kolom() as $kolom) {
            $awal[$kolom['nama']] = $kolom['awal'] ?? null;
        }

        return $awal;
    }

    /**
     * Keterangan template untuk ditampilkan peramban: kartu di daftar
     * template sekaligus formulir isiannya.
     *
     * @return array<string, mixed>
     */
    public static function katalog(): array
    {
        return [
            'kode' => static::kode(),
            'nama' => static::nama(),
            'mapel' => static::mapel(),
            'ringkasan' => static::ringkasan(),
            'ikon' => static::ikon(),
            'kolom' => static::kolom(),
            'jenis' => static::jenis(),
            'awal' => static::awal(),
            'cari' => mb_strtolower(implode(' ', array_merge(
                [static::nama(), static::mapel(), static::ringkasan()],
                static::kataKunci(),
            ))),
        ];
    }

    /**
     * Rakit butir soal yang siap dituang ke formulir input soal.
     *
     * @param  bool  $pernyataanBenar  Khusus butir benar/salah: pernyataannya
     *                                 dibuat benar, atau sengaja dibuat keliru.
     * @return array<string, mixed>
     */
    public function rakit(array $isi, string $jenis, bool $pernyataanBenar = true): array
    {
        $h = $this->susun($isi);
        $pilihan = array_values($h['pilihan']);
        $jawaban = $pilihan[0]['teks'];

        $butir = [
            'kode' => static::kode(),
            'judul' => $h['judul'] ?? static::nama(),
            'jenis' => $jenis,
            'svg' => $h['svg'] ?? null,
            'keterangan' => $h['keterangan'] ?? null,
            'pembahasan' => $h['pembahasan'],
            'jawaban' => $jawaban,
            'opsi' => [],
            'kunci' => [],
        ];

        if ($jenis === Soal::BENAR_SALAH) {
            // Pernyataan yang keliru memakai pengecoh pertama, bukan angka
            // sembarang, supaya butirnya tetap menguji pemahaman.
            $dipakai = $pernyataanBenar ? $jawaban : ($pilihan[1]['teks'] ?? $jawaban);

            return $butir + [
                'teks' => sprintf($h['pernyataan'], $dipakai),
                'kunci_bs' => $pernyataanBenar ? 'benar' : 'salah',
            ];
        }

        $butir['teks'] = $h['teks'];

        if ($jenis === Soal::ESSAY) {
            return $butir + [
                'kunci_essay' => $jawaban,
                'kata_kunci' => $h['kata_kunci'] ?? $jawaban,
            ];
        }

        // Opsi diurutkan menaik seperti kebiasaan soal matematika. Letak
        // kuncinya jadi berpindah-pindah dengan sendirinya, tidak selalu di
        // huruf yang sama seperti kalau jawaban benar ditaruh paling depan.
        $pilihan = array_slice($pilihan, 0, self::JUMLAH_OPSI);
        usort($pilihan, self::urutan(...));

        foreach ($pilihan as $i => $p) {
            $huruf = chr(65 + $i);
            $benar = $p['teks'] === $jawaban;

            $butir['opsi'][] = ['key' => $huruf, 'teks' => $p['teks'], 'benar' => $benar];

            if ($benar) {
                $butir['kunci'] = [$huruf];
            }
        }

        return $butir;
    }

    /**
     * Urutan opsi: menurut nilainya bila opsi memang berupa bilangan, selain
     * itu menurut abjad. Yang penting bukan urutannya melainkan bahwa ia
     * tidak bergantung pada opsi mana yang benar.
     */
    protected static function urutan(array $a, array $b): int
    {
        $x = $a['urut'] ?? $a['teks'];
        $y = $b['urut'] ?? $b['teks'];

        if (is_numeric($x) && is_numeric($y)) {
            return (float) $x <=> (float) $y;
        }

        return strnatcasecmp((string) $x, (string) $y);
    }

    /**
     * Saring daftar nilai pengecoh: buang yang nol atau negatif, yang sama
     * dengan kunci, dan yang kembar — lalu kembalikan sesuai urutan
     * masuknya, karena pemanggil menuliskan kandidatnya dari kekeliruan yang
     * paling sering terjadi ke yang paling jarang.
     *
     * @param  array<int, float>  $kandidat
     * @param  bool  $positifSaja  Besaran yang tidak mungkin negatif — panjang,
     *                             luas, berat — membuang kandidat nol atau
     *                             minus; suhu dan ketinggian tidak.
     * @return array<int, float>
     */
    protected static function saring(float $benar, array $kandidat, int $desimal = 2, bool $positifSaja = true): array
    {
        // Kuncinya sengaja berupa teks: PHP memangkas kunci array bertipe
        // pecahan menjadi bilangan bulat, sehingga 3,47 dan 3,40 akan
        // dianggap kembar dan salah satunya terbuang diam-diam.
        $dipakai = [(string) round($benar, $desimal) => true];
        $hasil = [];

        foreach ($kandidat as $n) {
            $n = round($n, $desimal);

            if (($positifSaja && $n <= 0) || isset($dipakai[(string) $n])) {
                continue;
            }

            $dipakai[(string) $n] = true;
            $hasil[] = $n;
        }

        return $hasil;
    }

    /**
     * Pengecoh yang lazim untuk soal berhitung: tertukar dengan besaran lain
     * dari benda yang sama (luas dijawab keliling, volume dijawab luas
     * permukaan), lupa membagi atau mengalikan, dan sekadar menjumlahkan
     * ukuran yang tertulis di gambar.
     *
     * Angka acak sengaja dihindari. Pengecoh yang cuma "angka lain" membuat
     * siswa yang keliru hitung tidak menemukan jawabannya di daftar, lalu
     * menebak — dan butirnya berhenti mengukur apa pun.
     *
     * @return array<int, float>
     */
    protected static function pengecohUmum(float $benar, float $lawan, float $jumlah): array
    {
        return self::saring($benar, [
            $lawan, $benar * 2, $jumlah, $benar / 2, $jumlah * 2, $benar + $jumlah, $benar * 4, $benar * 3,
        ]);
    }

    /**
     * Teks bebas yang diketik guru, siap disisipkan ke dalam teks butir.
     *
     * Teks butir — pertanyaan, opsi, dan pembahasan — memang berisi HTML:
     * rumus kimia memakai <sub>, pembahasan memakai <br>. Ketikan guru adalah
     * data, bukan markah, sehingga diloloskan di sini. Yang masuk ke gambar
     * tetap memakai nilai aslinya, sebab Kanvas meloloskan sendiri saat
     * menulis ke SVG dan meloloskan dua kali akan menampilkan "&amp;".
     */
    protected static function bebas(array $isi, string $kolom, string $bawaan = ''): string
    {
        $teks = trim((string) ($isi[$kolom] ?? ''));

        return e($teks === '' ? $bawaan : $teks);
    }

    /**
     * Daftar yang diketik guru, dipecah menjadi bagian-bagiannya.
     *
     * Titik koma didahulukan bila ada. Tanpa itu, bilangan desimal bergaya
     * Indonesia mustahil diketik: pada daftar berkoma, "4,5" terbaca sebagai
     * dua data — diam-diam, tanpa pesan kesalahan — sehingga diagramnya
     * menggambarkan data yang bukan dimaksud guru. Dengan titik koma, guru
     * dapat menulis "4,5; 6; 7,2" dan koma tetap berarti desimal.
     *
     * @return array<int, string>
     */
    protected static function daftar(string $teks): array
    {
        $pemisah = str_contains($teks, ';') ? ';' : ',';

        return array_values(array_filter(
            array_map('trim', explode($pemisah, $teks)),
            fn ($bagian) => $bagian !== '',
        ));
    }

    /** Jumlah semua isian berupa angka yang sedang berlaku. */
    protected static function jumlahUkuran(array $isi): float
    {
        return array_sum(array_map(
            fn ($kolom) => (float) ($isi[$kolom['nama']] ?? 0),
            array_filter(static::kolomAktif($isi), fn ($k) => $k['jenis'] === 'angka'),
        ));
    }

    /** Warna isi dan garis dari nama warna yang dipilih guru. */
    protected static function warna(array $isi, string $bawaan = 'biru'): array
    {
        return self::WARNA[$isi['warna'] ?? $bawaan] ?? self::WARNA[$bawaan];
    }

    /** Daftar warna siap pakai sebagai kolom isian. */
    protected static function kolomWarna(string $awal = 'biru'): array
    {
        // Nama warna yang salah tulis tidak akan tertangkap oleh penyapuan,
        // sebab penyapuan selalu mengisi lajur warna dengan nama yang sah;
        // yang jebol hanya nilai bawaannya. Karena itu dijaga di sini.
        if (! isset(self::WARNA[$awal])) {
            throw new InvalidArgumentException(
                'Warna bawaan "'.$awal.'" tidak ada. Yang tersedia: '
                .implode(', ', array_keys(self::WARNA)).'.',
            );
        }

        return ['nama' => 'warna', 'label' => 'Warna', 'jenis' => 'warna', 'awal' => $awal];
    }

    /** Pilihan benda sehari-hari, dipakai template berhitung tingkat dasar. */
    protected static function kolomBenda(string $awal = 'apel', int $lebar = 6): array
    {
        return [
            'nama' => 'benda',
            'label' => 'Benda yang digambar',
            'jenis' => 'pilihan',
            'pilihan' => Benda::JENIS,
            'awal' => $awal,
            'lebar' => $lebar,
        ];
    }

    /** Kolom "yang ditanyakan" yang bentuknya selalu sama di tiap template. */
    protected static function kolomTanya(array $pilihan, string $awal, int $lebar = 12): array
    {
        return [
            'nama' => 'tanya',
            'label' => 'Yang ditanyakan',
            'jenis' => 'pilihan',
            'pilihan' => $pilihan,
            'awal' => $awal,
            'lebar' => $lebar,
        ];
    }

    /** Kolom angka bulat dengan batas, bentuk isian yang paling sering dipakai. */
    protected static function kolomAngka(string $nama, string $label, float $awal, float $min, float $max, array $tambahan = []): array
    {
        return [
            'nama' => $nama,
            'label' => $label,
            'jenis' => 'angka',
            'awal' => $awal,
            'min' => $min,
            'max' => $max,
            'lebar' => 6,
        ] + $tambahan;
    }
}

<?php

namespace App\Services;

use App\Models\Soal;
use App\Support\ExcelExport;
use App\Support\RumusLatex;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import bank soal dari berkas Excel (.xlsx/.xls) atau CSV.
 *
 * Format satu baris = satu butir soal, dengan kolom:
 *
 *   A jenis              pg | pg_kompleks | essay | penjodohan | benar_salah
 *   B pertanyaan         teks soal
 *   C..G opsi_a..opsi_e  isi pilihan jawaban
 *   H kunci              lihat aturan per jenis di bawah
 *   I bobot              angka, default 1
 *   J level_kognitif     C1..C6 (opsional)
 *   K tingkat_kesukaran  mudah | sedang | sukar (opsional)
 *   L pembahasan         opsional
 *
 * Aturan kolom kunci & opsi per jenis soal:
 *   pg           opsi_a..e diisi, kunci satu huruf, mis. "C"
 *   pg_kompleks  opsi_a..e diisi, kunci beberapa huruf, mis. "A,C,D"
 *   benar_salah  opsi dikosongkan, kunci "benar" atau "salah"
 *   essay        opsi dikosongkan, kunci = jawaban model.
 *                Kata kunci penilaian boleh ditulis di opsi_a, pisahkan koma.
 *   penjodohan   tiap opsi berisi pasangan "pernyataan ## jodohnya",
 *                mis. opsi_a = "Ibu kota Jepang ## Tokyo". Kolom kunci
 *                dibiarkan kosong — pasangan sudah tergambar dari opsi.
 */
class ImportSoalExcelService
{
    /** @var array<int, string> */
    public const HEADER = [
        'jenis', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
        'kunci', 'bobot', 'level_kognitif', 'tingkat_kesukaran', 'pembahasan',
    ];

    /** Pemisah antara pernyataan kiri dan pasangan kanan pada soal penjodohan. */
    protected const PEMISAH_JODOH = '##';

    public function __construct(protected GambarSoalService $gambar) {}

    /**
     * Baca berkas dan kembalikan daftar soal siap simpan + daftar galat.
     *
     * @return array{soal: array<int, array<string, mixed>>, galat: array<int, string>}
     */
    public function baca(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $baris = $sheet->toArray(null, true, false, false);
        $gambar = $this->kumpulkanGambar($sheet);

        $soal = [];
        $galat = [];

        foreach ($baris as $i => $kolom) {
            $nomorBaris = $i + 1;

            // Lewati baris judul dan baris kosong.
            if ($this->barisKosong($kolom)) {
                continue;
            }
            if ($nomorBaris === 1 && $this->tampakSepertiHeader($kolom)) {
                continue;
            }

            try {
                $soal[] = $this->petakanBaris($this->tempelkanGambar($kolom, $gambar[$nomorBaris] ?? []));
            } catch (\InvalidArgumentException $e) {
                $galat[] = "Baris {$nomorBaris}: {$e->getMessage()}";
            }
        }

        if ($soal === [] && $galat === []) {
            $galat[] = 'Tidak ada baris data yang bisa dibaca dari berkas ini.';
        }

        return compact('soal', 'galat');
    }

    // =====================================================================
    // Gambar yang tertanam di dalam berkas
    // =====================================================================

    /**
     * Kumpulkan gambar yang ditempel di lembar kerja, dikelompokkan menurut
     * sel tempatnya diletakkan.
     *
     * Posisi sel itulah yang menentukan gambar tersebut milik siapa: gambar di
     * kolom pertanyaan menjadi bagian pertanyaan, gambar di kolom opsi_a
     * menjadi bagian opsi A. Dengan begitu soal pilihan ganda bergambar bisa
     * disusun di Excel tanpa aturan penulisan tambahan — guru cukup menempel
     * gambarnya di sel yang tepat.
     *
     * @return array<int, array<int, array<int, string>>> nomor baris => indeks kolom => alamat
     */
    protected function kumpulkanGambar(Worksheet $sheet): array
    {
        $hasil = [];

        foreach ($sheet->getDrawingCollection() as $gambar) {
            $biner = $this->binerGambar($gambar);

            if ($biner === null) {
                continue;
            }

            $alamat = $this->gambar->dariBiner($biner);

            if ($alamat === null) {
                continue;
            }

            [$huruf, $nomorBaris] = Coordinate::coordinateFromString($gambar->getCoordinates());
            $indeks = Coordinate::columnIndexFromString($huruf) - 1;

            $hasil[(int) $nomorBaris][$indeks][] = $alamat;
        }

        return $hasil;
    }

    /** Isi biner sebuah gambar lembar kerja, apa pun cara penyimpanannya. */
    protected function binerGambar(object $gambar): ?string
    {
        // Gambar yang dibangun di memori (mis. hasil tulis PhpSpreadsheet)
        // hanya tersedia sebagai sumber daya GD.
        if ($gambar instanceof MemoryDrawing) {
            $sumber = $gambar->getImageResource();

            if ($sumber === null) {
                return null;
            }

            ob_start();
            imagepng($sumber);

            return ob_get_clean() ?: null;
        }

        if (! method_exists($gambar, 'getPath')) {
            return null;
        }

        $jalur = $gambar->getPath();

        if (str_starts_with($jalur, 'data:image/')) {
            $biner = base64_decode(substr($jalur, strpos($jalur, ',') + 1), true);

            return $biner === false ? null : $biner;
        }

        // Berkas xlsx yang dibaca menyimpan gambarnya di dalam arsip, sehingga
        // jalurnya berbentuk "zip://berkas.xlsx#xl/media/image1.png".
        $biner = @file_get_contents($jalur);

        return $biner === false ? null : $biner;
    }

    /**
     * Sisipkan tag gambar ke sel yang bersangkutan sebelum baris dipetakan.
     *
     * @param  array<int, mixed>  $kolom
     * @param  array<int, array<int, string>>  $gambar
     * @return array<int, mixed>
     */
    protected function tempelkanGambar(array $kolom, array $gambar): array
    {
        foreach ($gambar as $indeks => $alamatSemua) {
            $tag = implode(' ', array_map(
                fn ($alamat) => '<img src="'.e($alamat).'" alt="Gambar soal">',
                $alamatSemua
            ));

            $kolom[$indeks] = trim(((string) ($kolom[$indeks] ?? '')).' '.$tag);
        }

        return $kolom;
    }

    /**
     * @param  array<int, mixed>  $kolom
     * @return array<string, mixed>
     */
    protected function petakanBaris(array $kolom): array
    {
        $ambil = fn (int $i) => trim((string) ($kolom[$i] ?? ''));

        $jenis = $this->normalkanJenis($ambil(0));
        $pertanyaan = $ambil(1);

        if ($pertanyaan === '') {
            throw new \InvalidArgumentException('kolom pertanyaan kosong.');
        }

        $opsiMentah = array_values(array_filter([
            $ambil(2), $ambil(3), $ambil(4), $ambil(5), $ambil(6),
        ], fn ($v) => $v !== ''));

        $kunciMentah = $ambil(7);
        [$opsi, $kunci] = $this->susunOpsiDanKunci($jenis, $opsiMentah, $kunciMentah);

        $bobot = $ambil(8);

        return [
            'jenis' => $jenis,
            // Rumus LaTeX pada lembar kerja diurai seperti pada naskah Word.
            'pertanyaan' => RumusLatex::keHtml($pertanyaan),
            'opsi' => $opsi,
            'kunci' => $kunci,
            'bobot' => is_numeric($bobot) ? (float) $bobot : 1.0,
            'level_kognitif' => strtoupper($ambil(9)) ?: null,
            'tingkat_kesukaran' => strtolower($ambil(10)) ?: null,
            'pembahasan' => $ambil(11) ?: null,
        ];
    }

    /**
     * @param  array<int, string>  $opsiMentah
     * @return array{0: array|null, 1: array}
     */
    protected function susunOpsiDanKunci(string $jenis, array $opsiMentah, string $kunciMentah): array
    {
        $huruf = range('A', 'E');

        if ($jenis === Soal::BENAR_SALAH) {
            $k = strtolower($kunciMentah);
            $k = in_array($k, ['benar', 'b', 'true', 'ya', '1'], true) ? 'benar' : 'salah';

            return [null, [$k]];
        }

        if ($jenis === Soal::ESSAY) {
            if ($kunciMentah === '') {
                throw new \InvalidArgumentException('soal essay wajib mengisi kunci (jawaban model).');
            }
            $kataKunci = $opsiMentah[0] ?? '';

            return [null, [
                'jawaban' => $kunciMentah,
                'kata_kunci' => $kataKunci === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $kataKunci)))),
            ]];
        }

        if ($jenis === Soal::PENJODOHAN) {
            $kiri = [];
            $kanan = [];
            $kunci = [];

            foreach ($opsiMentah as $i => $pasangan) {
                if (! str_contains($pasangan, self::PEMISAH_JODOH)) {
                    throw new \InvalidArgumentException(
                        'soal penjodohan harus ditulis "pernyataan '.self::PEMISAH_JODOH.' jodohnya".'
                    );
                }
                [$kiriTeks, $kananTeks] = array_map('trim', explode(self::PEMISAH_JODOH, $pasangan, 2));
                $nomor = (string) ($i + 1);

                $kiri[] = ['key' => $nomor, 'text' => RumusLatex::keHtml($kiriTeks)];
                $kanan[] = ['key' => $huruf[$i], 'text' => RumusLatex::keHtml($kananTeks)];
                $kunci[$nomor] = $huruf[$i];
            }

            if (count($kiri) < 2) {
                throw new \InvalidArgumentException('soal penjodohan minimal 2 pasangan.');
            }

            return [['kiri' => $kiri, 'kanan' => $kanan], $kunci];
        }

        // pg & pg_kompleks
        if (count($opsiMentah) < 2) {
            throw new \InvalidArgumentException('pilihan ganda minimal punya 2 opsi.');
        }

        $opsi = [];
        foreach ($opsiMentah as $i => $teks) {
            $opsi[] = ['key' => $huruf[$i], 'text' => RumusLatex::keHtml($teks)];
        }

        $kunci = array_values(array_filter(array_map(
            fn ($v) => strtoupper(trim($v)),
            preg_split('/[,;\s]+/', $kunciMentah) ?: []
        )));

        if ($kunci === []) {
            throw new \InvalidArgumentException('kolom kunci belum diisi.');
        }

        $tersedia = array_column($opsi, 'key');
        foreach ($kunci as $k) {
            if (! in_array($k, $tersedia, true)) {
                throw new \InvalidArgumentException("kunci \"{$k}\" tidak ada di daftar opsi.");
            }
        }

        if ($jenis === Soal::PG && count($kunci) > 1) {
            throw new \InvalidArgumentException('pilihan ganda biasa hanya boleh punya satu kunci — gunakan jenis pg_kompleks.');
        }

        return [$opsi, $kunci];
    }

    protected function normalkanJenis(string $jenis): string
    {
        $j = strtolower(str_replace([' ', '-'], '_', trim($jenis)));

        $alias = [
            'pg' => Soal::PG,
            'pilihan_ganda' => Soal::PG,
            'pg_kompleks' => Soal::PG_KOMPLEKS,
            'pilihan_ganda_kompleks' => Soal::PG_KOMPLEKS,
            'pgk' => Soal::PG_KOMPLEKS,
            'essay' => Soal::ESSAY,
            'esai' => Soal::ESSAY,
            'uraian' => Soal::ESSAY,
            'penjodohan' => Soal::PENJODOHAN,
            'menjodohkan' => Soal::PENJODOHAN,
            'benar_salah' => Soal::BENAR_SALAH,
            'bs' => Soal::BENAR_SALAH,
        ];

        if (! isset($alias[$j])) {
            throw new \InvalidArgumentException(
                'jenis soal "'.$jenis.'" tidak dikenali. Gunakan: '.implode(', ', array_keys(Soal::JENIS)).'.'
            );
        }

        return $alias[$j];
    }

    /** @param array<int, mixed> $kolom */
    protected function barisKosong(array $kolom): bool
    {
        foreach ($kolom as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $kolom */
    protected function tampakSepertiHeader(array $kolom): bool
    {
        $a = strtolower(trim((string) ($kolom[0] ?? '')));
        $b = strtolower(trim((string) ($kolom[1] ?? '')));

        return $a === 'jenis' || $b === 'pertanyaan';
    }

    /** Bangun berkas template kosong berisi header + contoh tiap jenis soal. */
    public function template(): StreamedResponse
    {
        $contoh = [
            [
                'pg', 'Ibu kota Provinsi Jawa Barat adalah ...',
                'Bandung', 'Semarang', 'Surabaya', 'Serang', '',
                'A', 1, 'C1', 'mudah', 'Bandung merupakan ibu kota Jawa Barat.',
            ],
            [
                'pg_kompleks', 'Manakah yang termasuk bilangan prima? (jawaban boleh lebih dari satu)',
                '2', '4', '7', '9', '11',
                'A,C,E', 2, 'C2', 'sedang', 'Bilangan prima hanya habis dibagi 1 dan dirinya sendiri.',
            ],
            [
                'benar_salah', 'Air mendidih pada suhu 100 derajat Celsius di tekanan 1 atm.',
                '', '', '', '', '',
                'benar', 1, 'C1', 'mudah', '',
            ],
            [
                'essay', 'Jelaskan proses terjadinya hujan!',
                'evaporasi, kondensasi, presipitasi', '', '', '', '',
                'Air menguap (evaporasi), mengembun menjadi awan (kondensasi), lalu turun sebagai hujan (presipitasi).',
                5, 'C4', 'sedang', 'Perhatikan kelengkapan tiga tahapan siklus air.',
            ],
            [
                'penjodohan', 'Jodohkan negara berikut dengan ibu kotanya!',
                'Jepang ## Tokyo', 'Korea Selatan ## Seoul', 'Thailand ## Bangkok', 'Vietnam ## Hanoi', '',
                '', 4, 'C1', 'mudah', '',
            ],
        ];

        return ExcelExport::make('Template Soal')
            ->header(self::HEADER)
            ->rows($contoh)
            ->spasi(2)
            ->rows([
                ['PETUNJUK PENGISIAN'],
                ['1. Hapus baris contoh di atas sebelum mengisi data Anda.'],
                ['2. Kolom jenis diisi salah satu: '.implode(' / ', array_keys(Soal::JENIS)).'.'],
                ['3. pg -> kunci satu huruf (mis. A). pg_kompleks -> beberapa huruf dipisah koma (mis. A,C).'],
                ['4. benar_salah -> kosongkan opsi, isi kunci dengan "benar" atau "salah".'],
                ['5. essay -> kosongkan opsi, kunci diisi jawaban model. Kata kunci penilaian boleh ditulis di opsi_a, dipisah koma.'],
                ['6. penjodohan -> tiap opsi ditulis "pernyataan '.self::PEMISAH_JODOH.' jodohnya", kolom kunci dikosongkan.'],
                ['7. Topik, mata pelajaran dan tingkat kelas dipilih sekali di form import, tidak perlu ditulis per baris.'],
            ])
            ->unduh('template-import-soal.xlsx');
    }
}

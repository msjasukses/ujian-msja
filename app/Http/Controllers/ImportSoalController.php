<?php

namespace App\Http\Controllers;

use App\Models\Soal;
use App\Models\Topik;
use App\Services\ImportSoalExcelService;
use App\Services\ImportSoalWordService;
use App\Support\Pengguna;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Import bank soal dari Word (.docx) dan Excel (.xlsx/.xls/.csv).
 *
 * Alurnya dua langkah: berkas diurai lebih dulu menjadi pratinjau supaya
 * guru bisa memeriksa hasil pembacaan (termasuk baris yang gagal dibaca),
 * baru kemudian disimpan. Hasil penguraian dititipkan di session antara dua
 * langkah itu, jadi berkas tidak perlu diunggah dua kali.
 */
class ImportSoalController extends Controller
{
    protected const SESSION_KEY = 'import_soal_pratinjau';

    public function form(Request $r)
    {
        return view('soal.import', [
            'sumber' => $r->get('sumber', 'word'),
            'daftarTopik' => Topik::aktif()->milikPengguna()->orderBy('nama_topik')->get(),
            'pratinjau' => session(self::SESSION_KEY),
        ]);
    }

    /** Langkah 1 — unggah berkas, urai, tampilkan pratinjau. */
    public function pratinjau(Request $r, ImportSoalWordService $word, ImportSoalExcelService $excel)
    {
        $r->validate([
            'sumber' => 'required|in:word,excel',
            'berkas' => [
                'required',
                'file',
                'max:20480',
                $r->input('sumber') === 'word' ? 'mimes:docx' : 'mimes:xlsx,xls,csv,txt',
            ],
            'topik_id' => ['nullable', 'integer', Rule::in(Topik::milikPengguna()->pluck('id')->all())],
            'mata_pelajaran_id' => Referensi::aturanMapel(),
            'tingkat_kelas_id' => Referensi::aturanTingkat(),
            ...Referensi::aturanJurusan(),
        ], [], [
            'berkas' => 'Berkas',
        ]);

        $path = $r->file('berkas')->getRealPath();

        try {
            $hasil = $r->input('sumber') === 'word'
                ? $word->baca($path)
                : $excel->baca($path);
        } catch (\Throwable $e) {
            return back()->with('error', 'Berkas gagal dibaca: '.$e->getMessage());
        }

        $pratinjau = [
            'sumber' => $r->input('sumber'),
            'nama_berkas' => $r->file('berkas')->getClientOriginalName(),
            'topik_id' => $r->input('topik_id'),
            'mata_pelajaran_id' => $r->input('mata_pelajaran_id'),
            'tingkat_kelas_id' => $r->input('tingkat_kelas_id'),
            'jurusan_id' => Referensi::pakaiJurusan() ? $r->input('jurusan_id') : null,
            'soal' => $hasil['soal'],
            'galat' => $hasil['galat'],
        ];

        // Disimpan menetap, bukan dengan ->with() yang bersifat flash. Flash
        // hanya bertahan satu permintaan: begitu halaman pratinjau selesai
        // digambar, datanya hilang — dan penekanan tombol Simpan sesudahnya
        // selalu berujung "pratinjau sudah kedaluwarsa". Isinya dibuang
        // sendiri setelah disimpan atau dibatalkan.
        $r->session()->put(self::SESSION_KEY, $pratinjau);

        return redirect()->route('soal.import.form', ['sumber' => $r->input('sumber')]);
    }

    /** Langkah 2 — simpan butir yang dicentang pada pratinjau. */
    public function simpan(Request $r)
    {
        $pratinjau = $r->session()->get(self::SESSION_KEY);

        if (! $pratinjau) {
            return redirect()->route('soal.import.form')
                ->with('error', 'Data pratinjau sudah kedaluwarsa. Silakan unggah berkas kembali.');
        }

        $r->validate([
            'pilih' => 'required|array|min:1',
            'pilih.*' => 'integer',
        ], [], ['pilih' => 'Butir soal']);

        $terpilih = array_flip($r->input('pilih'));
        $tahunAjaran = Referensi::namaTahunAjaranAktif();
        $jumlah = 0;

        DB::transaction(function () use ($pratinjau, $terpilih, $tahunAjaran, &$jumlah) {
            foreach ($pratinjau['soal'] as $i => $butir) {
                if (! isset($terpilih[$i])) {
                    continue;
                }

                Soal::create($butir + [
                    'topik_id' => $pratinjau['topik_id'] ?: null,
                    'mata_pelajaran_id' => $pratinjau['mata_pelajaran_id'] ?: null,
                    'tingkat_kelas_id' => $pratinjau['tingkat_kelas_id'] ?: null,
                    // Pratinjau yang dibuat sebelum ada pilihan jurusan tidak
                    // punya kunci ini.
                    'jurusan_id' => ($pratinjau['jurusan_id'] ?? null) ?: null,
                    'tahun_ajaran' => $tahunAjaran,
                    'guru_id' => Pengguna::guruId(),
                    'is_aktif' => true,
                ]);
                $jumlah++;
            }
        });

        $r->session()->forget(self::SESSION_KEY);

        return redirect()->route('soal.index')
            ->with('success', "{$jumlah} butir soal berhasil diimpor dari {$pratinjau['nama_berkas']}.");
    }

    public function batal(Request $r)
    {
        $r->session()->forget(self::SESSION_KEY);

        return redirect()->route('soal.import.form')->with('success', 'Pratinjau dibatalkan.');
    }

    /** Unduh berkas template Excel beserta contoh tiap jenis soal. */
    public function templateExcel(ImportSoalExcelService $excel)
    {
        return $excel->template();
    }

    /**
     * Unduh contoh naskah Word yang formatnya dikenali parser.
     *
     * Contohnya disusun bertabel — satu tabel per butir, baris kepala memuat
     * jenis soalnya — karena begitulah naskah soal biasa ditulis di sekolah:
     * rapi saat dicetak, dan kolom kiri yang sempit menjadi tempat nomor dan
     * huruf opsi. Naskah tanpa tabel tetap diterima; keduanya dibaca oleh
     * pengurai yang sama.
     */
    public function templateWord()
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $judul = ['bold' => true, 'size' => 13];
        $catatan = ['italic' => true, 'size' => 9, 'color' => '666666'];

        $section->addText('CONTOH NASKAH SOAL UNTUK IMPORT', $judul);
        $section->addText(
            'Satu tabel = satu butir soal. Baris pertama menyebut jenis soalnya, '.
            'kolom kiri berisi nomor butir lalu huruf opsi, dan baris penutup memuat '.
            'JAWABAN serta BOBOT. Baris LEVEL, KESUKARAN, KATA KUNCI, dan PEMBAHASAN '.
            'boleh dihapus bila tidak dipakai. Tabelnya boleh ditambah, dihapus, atau '.
            'disalin sebanyak yang diperlukan — nomornya tidak harus berurutan.',
            $catatan
        );
        $section->addText(
            'Naskah biasa tanpa tabel juga terbaca: tulis "1. pertanyaan", '.
            'baris opsi "A. ...", lalu "JAWABAN: A".',
            $catatan
        );
        $section->addTextBreak();

        // Seluruh keterangan ditaruh di atas, sebelum butir pertama. Kalimat
        // apa pun yang berdiri sesudah tabel terakhir akan terbaca sebagai
        // lanjutan pertanyaan butir terakhir — pengurai tidak punya cara
        // membedakan catatan penulis dari isi soalnya.
        foreach ($this->contohNaskahWord() as $butir) {
            $this->tabelContohSoal($section, $butir);
            $section->addTextBreak();
        }

        $tmp = tempnam(sys_get_temp_dir(), 'soal').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        return response()->download($tmp, 'template-import-soal.docx')->deleteFileAfterSend(true);
    }

    /**
     * Isi contoh untuk tiap jenis soal.
     *
     * Tiap butir berupa daftar baris tabel: string menjadi baris selebar
     * tabel (penanda seperti "JAWABAN : A"), sedangkan pasangan [kiri, kanan]
     * menjadi baris dua kolom (nomor + pertanyaan, huruf + opsi, pernyataan +
     * kunci, atau pasangan penjodohan).
     *
     * @return array<int, array<int, string|array{0: string, 1: string}>>
     */
    protected function contohNaskahWord(): array
    {
        return [
            [
                'JENIS SOAL : PILIHAN GANDA',
                ['1', 'Ibu kota Provinsi Jawa Barat adalah ...'],
                ['A', 'Bandung'],
                ['B', 'Semarang'],
                ['C', 'Surabaya'],
                ['D', 'Serang'],
                'JAWABAN : A',
                'BOBOT : 1',
                'LEVEL : C1',
                'KESUKARAN : mudah',
                'PEMBAHASAN : Bandung merupakan ibu kota Provinsi Jawa Barat.',
            ],
            [
                'JENIS SOAL : PILIHAN GANDA KOMPLEKS (JAWABAN LEBIH DARI SATU)',
                ['2', 'Manakah yang termasuk bilangan prima?'],
                ['A', '2'],
                ['B', '4'],
                ['C', '7'],
                ['D', '9'],
                ['E', '11'],
                'JAWABAN : A, C, E',
                'BOBOT : 2',
            ],
            [
                'JENIS SOAL : PENJODOHAN',
                ['3', 'Jodohkan negara berikut dengan ibu kotanya!'],
                ['Jepang', 'Tokyo'],
                ['Korea Selatan', 'Seoul'],
                ['Thailand', 'Bangkok'],
                ['Vietnam', 'Hanoi'],
                'BOBOT : 4',
            ],
            [
                'JENIS SOAL : BENAR/SALAH (BIASA)',
                ['4', 'Air mendidih pada suhu 100 derajat Celsius di tekanan 1 atm.'],
                'benar',
                'salah',
                'JAWABAN : benar',
                'BOBOT : 1',
            ],
            [
                'JENIS SOAL : BENAR/SALAH (KOMPLEKS)',
                ['5', 'Tentukan benar atau salah setiap pernyataan tentang pernapasan manusia berikut!'],
                ['Oksigen diserap di alveolus', 'benar'],
                ['Udara masuk melalui kerongkongan', 'salah'],
                ['Diafragma membantu proses pernapasan', 'benar'],
                ['Karbon dioksida dikeluarkan saat menghirup', 'salah'],
                'BOBOT : 4',
                'PEMBAHASAN : Kerongkongan adalah saluran makanan; saluran udara adalah tenggorokan.',
            ],
            [
                'JENIS SOAL : ESSAY (URAIAN)',
                ['6', 'Jelaskan proses terjadinya hujan!'],
                // Essay tidak punya opsi: yang wajib hanyalah baris JAWABAN,
                // yang dipakai guru sebagai acuan saat menilai. KATA KUNCI
                // bersifat opsional dan ditulis dipisah koma.
                'JAWABAN : Air menguap (evaporasi), lalu mengembun menjadi awan (kondensasi), '
                    .'dan akhirnya turun kembali ke bumi sebagai hujan (presipitasi).',
                'KATA KUNCI : evaporasi, kondensasi, presipitasi',
                'BOBOT : 5',
                'LEVEL : C2',
                'KESUKARAN : sedang',
            ],
        ];
    }

    /**
     * Gambar satu tabel contoh.
     *
     * @param  array<int, string|array{0: string, 1: string}>  $butir
     */
    protected function tabelContohSoal(Section $section, array $butir): void
    {
        $tabel = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'AAAAAA',
            'cellMargin' => 70,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $tebal = ['bold' => true];
        $lebarKiri = 1800;
        $lebarKanan = 7600;

        foreach ($butir as $baris) {
            $tabel->addRow();

            if (is_array($baris)) {
                $tabel->addCell($lebarKiri)->addText($baris[0], $tebal);
                $tabel->addCell($lebarKanan)->addText($baris[1]);

                continue;
            }

            // Baris penanda memakai satu sel selebar tabel.
            $tabel->addCell($lebarKiri + $lebarKanan, ['gridSpan' => 2])->addText($baris, $tebal);
        }
    }
}

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
            'daftarTopik' => Topik::aktif()->orderBy('nama_topik')->get(),
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
            'topik_id' => 'nullable|integer|exists:topik,id',
            'mata_pelajaran_id' => Referensi::aturanMapel(),
            'tingkat_kelas_id' => Referensi::aturanTingkat(),
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

    /** Unduh contoh naskah Word yang formatnya dikenali parser. */
    public function templateWord()
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $judul = ['bold' => true, 'size' => 13];
        $catatan = ['italic' => true, 'size' => 9, 'color' => '666666'];

        $section->addText('CONTOH NASKAH SOAL UNTUK IMPORT', $judul);
        $section->addText(
            'Tulis tiap soal diawali nomor. Baris opsi diawali huruf A., B., dst. '.
            'Kunci ditulis pada baris "JAWABAN:" atau "KUNCI:". Baris PEMBAHASAN, BOBOT, '.
            'LEVEL dan KESUKARAN bersifat opsional.',
            $catatan
        );
        $section->addTextBreak();

        $baris = [
            '1. Ibu kota Provinsi Jawa Barat adalah ...',
            'A. Bandung',
            'B. Semarang',
            'C. Surabaya',
            'D. Serang',
            'JAWABAN: A',
            'BOBOT: 1',
            'LEVEL: C1',
            'KESUKARAN: mudah',
            'PEMBAHASAN: Bandung merupakan ibu kota Provinsi Jawa Barat.',
            '',
            '2. Manakah yang termasuk bilangan prima? (jawaban boleh lebih dari satu)',
            'A. 2',
            'B. 4',
            'C. 7',
            'D. 9',
            'E. 11',
            'JAWABAN: A, C, E',
            'BOBOT: 2',
            '',
            '3. Air mendidih pada suhu 100 derajat Celsius di tekanan 1 atm.',
            'JAWABAN: benar',
            '',
            '4. Jelaskan proses terjadinya hujan!',
            'JENIS: essay',
            'JAWABAN: Air menguap (evaporasi), mengembun menjadi awan (kondensasi), lalu turun sebagai hujan (presipitasi).',
            'BOBOT: 5',
            '',
            '5. Jodohkan negara berikut dengan ibu kotanya!',
            'JENIS: penjodohan',
            'Jepang ## Tokyo',
            'Korea Selatan ## Seoul',
            'Thailand ## Bangkok',
            'Vietnam ## Hanoi',
            'BOBOT: 4',
        ];

        foreach ($baris as $teks) {
            $teks === '' ? $section->addTextBreak() : $section->addText($teks);
        }

        $namaFile = 'template-import-soal.docx';
        $tmp = tempnam(sys_get_temp_dir(), 'soal').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        return response()->download($tmp, $namaFile)->deleteFileAfterSend(true);
    }
}

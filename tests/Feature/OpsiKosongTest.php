<?php

namespace Tests\Feature;

use App\Models\Soal;
use App\Models\User;
use App\Support\TeksSoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Opsi jawaban yang dibiarkan kosong diisi hurufnya sendiri saat disimpan.
 *
 * Melayani soal yang pilihannya memang berupa huruf — mis. "Pernyataan yang
 * benar ditunjukkan oleh nomor ..." dengan opsi A, B, C, D — sekaligus
 * mencegah opsi hampa lolos ke naskah ujian tanpa disadari.
 */
class OpsiKosongTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin);
    }

    // =====================================================================
    // Formulir input soal
    // =====================================================================

    public function test_opsi_kosong_diisi_hurufnya(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Pernyataan yang benar ditunjukkan oleh pilihan ...',
            'bobot' => 1,
            'opsi_text' => ['', '', '', ''],
            'kunci_pg' => ['C'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->firstOrFail();

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            collect($soal->opsi)->pluck('text')->all()
        );
    }

    /** Opsi yang terisi tidak diganggu; hanya yang kosong yang dilengkapi. */
    public function test_hanya_opsi_kosong_yang_dilengkapi(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Ibu kota Jawa Barat adalah ...',
            'bobot' => 1,
            'opsi_text' => ['Bandung', '', 'Surabaya', ''],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $this->assertSame(
            ['Bandung', 'B', 'Surabaya', 'D'],
            collect(Soal::latest('id')->firstOrFail()->opsi)->pluck('text')->all()
        );
    }

    /**
     * Kolom yang bagi guru tampak kosong sering menyisakan &nbsp;, <br>, atau
     * span kosong dari editor — semuanya tetap terhitung kosong.
     */
    public function test_sisa_penanda_editor_tetap_terhitung_kosong(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Perhatikan pilihan berikut ...',
            'bobot' => 1,
            'opsi_text' => ['&nbsp;', '<br>', '<span></span>', '   '],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            collect(Soal::latest('id')->firstOrFail()->opsi)->pluck('text')->all()
        );
    }

    /**
     * Opsi yang isinya hanya gambar bukan opsi kosong.
     *
     * Ini bentuk yang lazim pada soal bangun datar dan grafik — menggantinya
     * dengan huruf akan menghapus gambar yang justru menjadi pilihan jawaban.
     */
    public function test_opsi_bergambar_tidak_dianggap_kosong(): void
    {
        $gambar = '<img src="/storage/soal/ab/contoh.png" alt="Gambar soal">';

        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Bangun datar manakah yang punya empat sisi sama panjang?',
            'bobot' => 1,
            'opsi_text' => [$gambar, '', 'Segitiga', ''],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $opsi = collect(Soal::latest('id')->firstOrFail()->opsi)->pluck('text')->all();

        $this->assertStringContainsString('<img', $opsi[0]);
        $this->assertSame(['B', 'Segitiga', 'D'], array_slice($opsi, 1));
    }

    /** Penjodohan tidak ikut aturan ini — pasangan kosong tak bermakna. */
    public function test_pasangan_penjodohan_kosong_tetap_ditolak(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PENJODOHAN,
            'pertanyaan' => 'Jodohkan negara dengan ibu kotanya!',
            'bobot' => 2,
            'jodoh_kiri' => ['Jepang', ''],
            'jodoh_kanan' => ['Tokyo', 'Seoul'],
        ])->assertSessionHasErrors('jodoh_kiri.1');

        $this->assertSame(0, Soal::count());
    }

    /** Kunci tetap wajib dicentang meski seluruh opsi dikosongkan. */
    public function test_kunci_tetap_wajib(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Soal tanpa kunci',
            'bobot' => 1,
            'opsi_text' => ['', ''],
        ])->assertSessionHasErrors('kunci_pg');

        $this->assertSame(0, Soal::count());
    }

    // =====================================================================
    // Impor Word
    // =====================================================================

    /**
     * Naskah boleh menuliskan "A." tanpa isi untuk opsi bergambar. Bila
     * ternyata tidak ada gambar yang menyusul, opsinya diisi hurufnya —
     * bukan dibiarkan hampa.
     */
    public function test_impor_word_mengisi_opsi_yang_kosong(): void
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        foreach ([
            '1. Pernyataan yang benar ditunjukkan oleh pilihan ...',
            'A.',
            'B.',
            'C. Semarang',
            'JAWABAN: C',
        ] as $teks) {
            $section->addText($teks);
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->get(route('soal.import.form'))->assertOk();
        $this->post('/soal/import/simpan', ['pilih' => [0]])->assertRedirect(route('soal.index'));

        $this->assertSame(
            ['A', 'B', 'Semarang'],
            collect(Soal::firstOrFail()->opsi)->pluck('text')->all()
        );
    }

    // =====================================================================
    // Pemeriksaan kekosongan
    // =====================================================================

    public function test_pemeriksaan_kosong(): void
    {
        foreach (['', '   ', '&nbsp;', '<br>', '<span></span>', "\n\t"] as $kosong) {
            $this->assertTrue(TeksSoal::kosong($kosong), "\"{$kosong}\" seharusnya terhitung kosong.");
        }

        foreach (['A', '0', '<img src="/storage/soal/x.png">', '<sup>2</sup>'] as $berisi) {
            $this->assertFalse(TeksSoal::kosong($berisi), "\"{$berisi}\" seharusnya terhitung berisi.");
        }
    }
}
